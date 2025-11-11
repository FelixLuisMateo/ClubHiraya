// ../js/table.js
// Full replacement with defensive fixes, duration support, and reservation cancel button.

document.addEventListener('DOMContentLoaded', () => {
  // small global error logging to surface client errors quickly
  window.addEventListener('error', (ev) => {
    // log to console (visible in DevTools)
    console.error('Uncaught error:', ev.message, ev.filename, ev.lineno, ev.colno, ev.error);
  });
  window.addEventListener('unhandledrejection', (ev) => {
    console.error('Unhandled promise rejection:', ev.reason);
  });

  // API endpoints
  const API_GET = '../api/get_tables.php';
  const API_UPDATE = '../api/update_table.php';
  const API_DELETE = '../api/delete_table.php';
  const API_CREATE = '../api/create_table.php';
  const API_GET_STATUS_BY_DATE = '../api/get_table_status_by_date.php';
  const API_CREATE_RESERVATION = '../api/create_reservation.php';
  const API_DELETE_RESERVATION = '../api/delete_reservation.php';

  let tablesData = [];

  // DOM refs
  const viewHeader = document.getElementById('viewHeader');
  const viewContent = document.getElementById('viewContent');
  let cardsGrid = document.getElementById('cardsGrid');
  const searchInput = document.getElementById('searchInput');
  const searchClear = document.getElementById('searchClear');
  const filterButtons = document.querySelectorAll('.filter-btn');
  const partyControl = document.getElementById('partyControl');
  const partySelect = document.getElementById('partySelect');
  const partySortControl = document.getElementById('partySortControl');
  const partySortSelect = document.getElementById('partySortSelect');

  const state = {
    filter: 'all',
    search: '',
    partySeats: 'any',
    partySort: 'asc',
    date: '',
    time: '',
    selectedId: null,
  };

  // init default date/time
  (function ensureDefaultDateTime() {
    if (!state.date) {
      const now = new Date();
      state.date = now.toISOString().slice(0, 10);
    }
    if (!state.time) {
      const now = new Date();
      const hh = String(now.getHours()).padStart(2, '0');
      const mm = String(now.getMinutes()).padStart(2, '0');
      state.time = `${hh}:${mm}`;
    }
  })();

  // helpers
  function capitalize(s) { return s && s.length ? s[0].toUpperCase() + s.slice(1) : ''; }
  function escapeHtml(text = '') {
    return String(text).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]);
  }
  function formatDateForHeader(dateIso, timeStr) {
    try {
      if (!dateIso) return '';
      const dt = new Date(dateIso + 'T' + (timeStr || '00:00'));
      const options = { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' };
      const datePart = dt.toLocaleDateString(undefined, options);
      const timePart = timeStr ? dt.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' }) : '';
      return `${datePart}${timePart ? ' • ' + timePart : ''}`;
    } catch (e) {
      return `${dateIso} ${timeStr || ''}`;
    }
  }

  // status utilities
  function nextStatusFor(current) {
    const order = ['available', 'reserved', 'occupied'];
    const idx = order.indexOf(current);
    if (idx === -1) return 'reserved';
    return order[(idx + 1) % order.length];
  }

  async function changeTableStatus(tableId, newStatus, refreshCallback) {
    try {
      const res = await fetch(API_UPDATE, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: Number(tableId), status: newStatus })
      });
      const j = await res.json().catch(e => { throw new Error('Invalid JSON response from update_table.php: ' + e.message); });
      if (!j.success) throw new Error(j.error || 'Update failed');
      await loadTables();
      if (typeof refreshCallback === 'function') refreshCallback();
    } catch (err) {
      console.error('changeTableStatus error', err);
      alert('Failed to change cabin status: ' + err.message);
    }
  }

  // Create reservation starting now for X minutes
  async function createReservationNow(tableId, minutes, guest = '') {
    if (!tableId) throw new Error('Invalid table id');
    const now = new Date();
    const date = now.toISOString().slice(0, 10);
    const hh = String(now.getHours()).padStart(2, '0');
    const mm = String(now.getMinutes()).padStart(2, '0');
    const startTime = `${hh}:${mm}`;
    const payload = {
      table_id: Number(tableId),
      date,
      start_time: startTime,
      duration: Number(minutes),
      guest: guest || ''
    };
    const res = await fetch(API_CREATE_RESERVATION, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const j = await res.json().catch(e => { throw new Error('Invalid JSON response from create_reservation.php: ' + e.message); });
    if (!j.success) throw new Error(j.error || 'Create reservation failed');
    return j;
  }

  // Cancel reservation helper - call api/delete_reservation.php and refresh current view
  async function cancelReservation(reservationId, tableId) {
    if (!reservationId) return;
    if (!confirm('Cancel this reservation?')) return;
    try {
      const res = await fetch(API_DELETE_RESERVATION, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: Number(reservationId) })
      });
      const j = await res.json().catch(e => { throw new Error('Invalid JSON from delete_reservation.php: ' + e.message); });
      if (!j.success) throw new Error(j.error || 'Cancel failed');
      showToast('Reservation cancelled', { background: '#c62828' });
      // Refresh the page data depending on current view
      if (state.filter === 'date') {
        if (state.time) loadTableStatusForDateTime(state.date, state.time);
        else loadTableStatusForDate(state.date);
      } else if (state.filter === 'time') {
        loadTableStatusForDateTime(state.date, state.time);
      } else {
        await loadTables();
      }
    } catch (err) {
      console.error('cancelReservation error', err);
      alert('Failed to cancel reservation: ' + (err && err.message ? err.message : err));
    }
  }

  // --- Time monitors ---
  let _timeMonitorInterval = null;
  const notifiedReservations = new Set();

  function startTimeMonitors() {
    stopTimeMonitors();
    try {
      if (typeof updateTimeMonitors === 'function') updateTimeMonitors();
    } catch (e) {
      console.error('updateTimeMonitors call failed during startTimeMonitors:', e);
    }
    _timeMonitorInterval = setInterval(() => {
      try {
        if (typeof updateTimeMonitors === 'function') updateTimeMonitors();
      } catch (e) {
        console.error('updateTimeMonitors threw in interval:', e);
      }
    }, 30 * 1000);
  }
  function stopTimeMonitors() {
    if (_timeMonitorInterval) {
      clearInterval(_timeMonitorInterval);
      _timeMonitorInterval = null;
    }
  }

  function formatDuration(ms) {
    if (ms < 0) ms = Math.abs(ms);
    const totalSec = Math.floor(ms / 1000);
    const days = Math.floor(totalSec / 86400);
    const hours = Math.floor((totalSec % 86400) / 3600);
    const mins = Math.floor((totalSec % 3600) / 60);
    if (days > 0) return `${days}d ${hours}h`;
    if (hours > 0) return `${hours}h ${mins}m`;
    return `${mins}m`;
  }

  function showToast(message, opts = {}) {
    const id = 'tables-toast-container';
    let container = document.getElementById(id);
    if (!container) {
      container = document.createElement('div');
      container.id = id;
      container.style.position = 'fixed';
      container.style.right = '16px';
      container.style.top = '16px';
      container.style.zIndex = 10000;
      document.body.appendChild(container);
    }
    const t = document.createElement('div');
    t.className = 'tables-toast';
    t.style.background = opts.background || 'rgba(0,0,0,0.85)';
    t.style.color = '#fff';
    t.style.padding = '10px 12px';
    t.style.marginTop = '8px';
    t.style.borderRadius = '8px';
    t.style.boxShadow = '0 6px 18px rgba(0,0,0,0.2)';
    t.style.fontWeight = '700';
    t.textContent = message;
    container.appendChild(t);
    setTimeout(() => {
      t.style.transition = 'opacity 300ms ease, transform 300ms ease';
      t.style.opacity = '0';
      t.style.transform = 'translateY(-6px)';
    }, 4000);
    setTimeout(() => t.remove(), 4400);
  }

  function playBeep() {
    try {
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      if (!AudioCtx) return;
      const ctx = new AudioCtx();
      const o = ctx.createOscillator();
      const g = ctx.createGain();
      o.type = 'sine';
      o.frequency.value = 880;
      g.gain.value = 0.0001;
      o.connect(g);
      g.connect(ctx.destination);
      const now = ctx.currentTime;
      g.gain.exponentialRampToValueAtTime(0.12, now + 0.01);
      o.start(now);
      g.gain.exponentialRampToValueAtTime(0.0001, now + 0.24);
      o.stop(now + 0.25);
      setTimeout(() => {
        try { ctx.close(); } catch (e) {}
      }, 400);
    } catch (e) {}
  }

  async function ensureNotificationPermission() {
    if (!('Notification' in window)) return false;
    if (Notification.permission === 'granted') return true;
    if (Notification.permission === 'denied') return false;
    return Notification.requestPermission().then(p => p === 'granted');
  }

  async function triggerEndNotification(reservationId, cabinName, endDtStr) {
    try {
      if (reservationId && notifiedReservations.has(String(reservationId))) return;
      if (reservationId) notifiedReservations.add(String(reservationId));

      const title = `Cabin time ended`;
      const body = cabinName ? `${cabinName} reservation has ended${endDtStr ? ' at ' + endDtStr : ''}.` : `A reservation has ended.`;
      showToast(`${body}`, { background: '#c62828' });
      playBeep();

      const allowed = await ensureNotificationPermission();
      if (allowed) {
        const n = new Notification(title, { body, tag: 'cabin-end-' + (reservationId || Math.random()) });
        n.onclick = () => { try { window.focus(); } catch (e) {} };
      }
    } catch (e) {
      console.error('triggerEndNotification error', e);
    }
  }

  function updateTimeMonitors() {
    const nodes = document.querySelectorAll('.time-monitor');
    const now = new Date();
    nodes.forEach(el => {
      try {
        const start = el.dataset.start;
        const end = el.dataset.end;
        if (!start && !end) {
          el.textContent = '';
          return;
        }
        const startIso = start ? start.replace(' ', 'T') : null;
        const endIso = end ? end.replace(' ', 'T') : null;
        const dStart = startIso ? new Date(startIso) : null;
        const dEnd = endIso ? new Date(endIso) : null;

        if (dStart && now < dStart) {
          const ms = dStart - now;
          el.textContent = `Starts in ${formatDuration(ms)}`;
          el.classList.remove('ongoing');
          el.classList.remove('ended');
        } else if (dStart && dEnd && now >= dStart && now <= dEnd) {
          const ms = dEnd - now;
          el.textContent = `Ends in ${formatDuration(ms)}`;
          el.classList.add('ongoing');
          el.classList.remove('ended');
        } else if (dEnd && now > dEnd) {
          const ms = now - dEnd;
          el.textContent = `Ended ${formatDuration(ms)} ago`;
          el.classList.remove('ongoing');
          el.classList.add('ended');
          const resId = el.dataset.reservationId || el.dataset.resId || '';
          const cabinName = el.dataset.cabinName || el.dataset.tableName || '';
          if (resId) {
            if (!notifiedReservations.has(String(resId))) {
              triggerEndNotification(resId, cabinName, (dEnd ? dEnd.toLocaleString() : ''));
            }
          } else {
            const key = `no-res-${cabinName}-${el.dataset.end}`;
            if (!notifiedReservations.has(key)) {
              notifiedReservations.add(key);
              triggerEndNotification(key, cabinName, (dEnd ? dEnd.toLocaleString() : ''));
            }
          }
        } else {
          el.textContent = '';
          el.classList.remove('ongoing');
          el.classList.remove('ended');
        }
      } catch (e) {
        console.error('Error while updating single time-monitor element:', e, el);
      }
    });
  }

  // --- Renderers ---
  function renderCardsInto(container, data) {
    container.innerHTML = '';
    if (!data.length) {
      container.innerHTML = '<div style="padding:18px; font-weight:700">No cabins found</div>';
      return;
    }
    data.forEach(tbl => {
      const status = tbl.status || 'available';
      const statusDotColor =
        status === 'available' ? '#00b256' :
        status === 'reserved' ? '#ffd400' :
        '#d20000';
      const card = document.createElement('div');
      card.className = 'table-card';
      card.setAttribute('role', 'button');
      card.setAttribute('tabindex', '0');
      card.dataset.id = tbl.id;
      if (state.selectedId === tbl.id) card.classList.add('active');

      card.innerHTML = `
        <div class="title">${escapeHtml(tbl.name)}</div>
        <div class="status-row">
          <span class="status-dot" style="background:${statusDotColor}"></span>
          <span class="status-label">${capitalize(status)}</span>
        </div>
        <div class="seats-row"><span>🛏️</span> ${escapeHtml(String(tbl.seats || tbl.party_size || ''))} Beds</div>
        ${tbl.guest ? `<div class="guest">${escapeHtml(tbl.guest)}</div>` : ''}
        <div class="card-actions" aria-hidden="false">
          <button class="icon-btn status-btn" aria-label="Change status" title="Change status">⚑</button>
          <button class="icon-btn edit-btn" aria-label="Edit cabin" title="Edit">✎</button>
          <button class="icon-btn delete-btn" aria-label="Delete cabin" title="Delete">✖</button>
        </div>
      `;

      card.addEventListener('click', () => setSelected(tbl.id));
      card.addEventListener('keydown', ev => {
        if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); setSelected(tbl.id); }
      });

      const editBtn = card.querySelector('.edit-btn');
      const deleteBtn = card.querySelector('.delete-btn');
      const statusBtn = card.querySelector('.status-btn');

      if (editBtn) editBtn.addEventListener('click', e => { e.stopPropagation(); openEditModal(tbl); });
      if (deleteBtn) deleteBtn.addEventListener('click', e => { e.stopPropagation(); confirmDelete(tbl); });

      if (statusBtn) {
        // Unified choice handler for All view (tbl variable)
        statusBtn.addEventListener('click', async (e) => {
          e.stopPropagation();
          try {
            const current = tbl.status || 'available';
            const choice = prompt('Change status to: (r)eserved, (o)ccupied (set timer), (a)vailable? Enter r / o / a and press OK. Cancel to abort.');
            if (choice === null) return;
            const c = String(choice).trim().toLowerCase();
            const tableId = tbl.id;

            if (c === 'r') {
              if (!confirm(`Change status of "${tbl.name}" from "${current}" to "reserved"?`)) return;
              await changeTableStatus(tableId, 'reserved', () => renderView());
              return;
            } else if (c === 'o') {
              const durInput = prompt('Set occupied duration in minutes (e.g. 90). Leave blank to mark occupied without timer:');
              if (durInput === null) return;
              const minutes = durInput.trim() === '' ? 0 : parseInt(durInput.trim(), 10);
              if (!isNaN(minutes) && minutes > 0) {
                const guest = prompt('Guest name (optional):') || '';
                try {
                  await createReservationNow(tableId, minutes, guest);
                  await changeTableStatus(tableId, 'occupied', () => renderView());
                } catch (err) {
                  console.error('createReservationNow failed', err);
                  alert('Failed to create reservation and mark occupied: ' + err.message);
                }
                return;
              } else {
                if (!confirm(`Mark "${tbl.name}" as occupied without timer?`)) return;
                await changeTableStatus(tableId, 'occupied', () => renderView());
                return;
              }
            } else if (c === 'a') {
              if (!confirm(`Change status of "${tbl.name}" from "${current}" to "available"?`)) return;
              await changeTableStatus(tableId, 'available', () => renderView());
              return;
            } else {
              alert('No action taken. Enter r, o, or a next time (or cancel).');
              return;
            }
          } catch (err) {
            console.error('status button handler error (All view):', err);
            alert('An error occurred: ' + (err && err.message ? err.message : err));
          }
        });
      }

      container.appendChild(card);
    });
  }

  function setSelected(id) {
    state.selectedId = id;
    document.querySelectorAll('.table-card').forEach(c => c.classList.toggle('active', c.dataset.id == id));
    const selected = tablesData.find(t => t.id === Number(id));
    if (selected) console.info('Selected cabin:', selected.name);
  }

  // Views
  function renderAllView() {
    viewHeader.innerHTML = '<h1>All Cabins</h1>';
    viewContent.innerHTML = `<div class="cards-grid" id="cardsGrid" role="list"></div>`;
    cardsGrid = document.getElementById('cardsGrid');

    if (partyControl) {
      partyControl.setAttribute('aria-hidden', 'true');
      partyControl.classList.remove('visible');
      partyControl.style.display = '';
    }
    if (partySortControl) {
      partySortControl.setAttribute('aria-hidden', 'true');
      partySortControl.style.display = 'none';
    }

    const s = state.search.trim().toLowerCase();
    let filtered = tablesData;
    if (s) filtered = tablesData.filter(t => t.name.toLowerCase().includes(s));
    renderCardsInto(cardsGrid, filtered);

    startTimeMonitors();
  }

  function renderPartyView() {
    viewHeader.innerHTML = '<h1>Party Size</h1>';
    if (partyControl) {
      partyControl.setAttribute('aria-hidden', 'false');
      partyControl.classList.add('visible');
      partyControl.style.display = '';
    }
    if (partySortControl) {
      partySortControl.setAttribute('aria-hidden', 'false');
      partySortControl.style.display = 'block';
    }
    viewContent.innerHTML = `<div class="cards-grid" id="cardsGrid" role="list"></div>`;
    cardsGrid = document.getElementById('cardsGrid');

    let filtered = tablesData;
    if (state.partySeats !== 'any') {
      const v = Number(state.partySeats);
      const [min, max] = v === 2 ? [1, 2] : v === 4 ? [3, 4] : v === 6 ? [5, 6] : [0, 0];
      filtered = tablesData.filter(t => t.seats >= min && t.seats <= max);
    }
    if (state.partySort === 'asc') filtered = filtered.slice().sort((a, b) => a.seats - b.seats);
    else if (state.partySort === 'desc') filtered = filtered.slice().sort((a, b) => b.seats - a.seats);

    renderCardsInto(cardsGrid, filtered);
    stopTimeMonitors();
  }

  function renderDateView() {
    const subtitle = formatDateForHeader(state.date, state.time);
    viewHeader.innerHTML = `<h1>Date</h1>${subtitle ? `<div class="view-subtitle">${escapeHtml(subtitle)}</div>` : ''}`;

    if (partyControl) {
      partyControl.setAttribute('aria-hidden', 'true');
      partyControl.classList.remove('visible');
      partyControl.style.display = '';
    }
    if (partySortControl) {
      partySortControl.setAttribute('aria-hidden', 'true');
      partySortControl.style.display = 'none';
    }

    viewContent.innerHTML = `
      <div class="date-controls" role="region" aria-label="Date and time controls">
        <div class="picker-wrap" aria-hidden="false">
          <label class="label" for="viewDatePicker" style="display:none">Date</label>
          <input type="date" id="viewDatePicker" value="${state.date || ''}" aria-label="Pick a date" />
          <span class="divider" aria-hidden="true"></span>
          <label class="label" for="viewTimePicker" style="display:none">Time</label>
          <input type="time" id="viewTimePicker" value="${state.time || ''}" aria-label="Pick a time" />
          <button id="btnClearDateTime" class="btn" title="Clear date/time" aria-label="Clear date/time">Clear</button>
        </div>

        <div style="display:flex; gap:8px; align-items:center;">
          <button id="btnSearchSlot" class="btn primary" title="Show availability for chosen date/time">Show Availability</button>
          <button id="btnAddReservationSlot" class="btn add" title="Create a new reservation">+ New Reservation</button>
        </div>
      </div>

      <div id="tableStatusGrid" class="cards-grid"></div>
    `;

    const datePicker = document.getElementById('viewDatePicker');
    if (datePicker) {
      datePicker.value = state.date || '';
      datePicker.addEventListener('change', e => {
        state.date = e.target.value;
        const vs = viewHeader.querySelector('.view-subtitle');
        if (vs) vs.textContent = formatDateForHeader(state.date, state.time);
        loadTableStatusForDate(state.date);
      });
      if (state.date) loadTableStatusForDate(state.date);
    }

    const timePicker = document.getElementById('viewTimePicker');
    if (timePicker) {
      timePicker.value = state.time || '';
      timePicker.addEventListener('change', e => {
        state.time = e.target.value;
        const vs = viewHeader.querySelector('.view-subtitle');
        if (vs) vs.textContent = formatDateForHeader(state.date, state.time);
      });
    }

    document.getElementById('btnSearchSlot')?.addEventListener('click', () => {
      if (!state.date) return alert('Please select a date first.');
      if (!state.time) return alert('Please select a time first.');
      loadTableStatusForDateTime(state.date, state.time);
    });

    document.getElementById('btnAddReservationSlot')?.addEventListener('click', () => {
      if (!state.date || !state.time) return alert('Please select date and time first to create a reservation.');
      openNewReservationModal();
    });

    document.getElementById('btnClearDateTime')?.addEventListener('click', () => {
      const now = new Date();
      state.date = now.toISOString().slice(0, 10);
      const hh = String(now.getHours()).padStart(2, '0');
      const mm = String(now.getMinutes()).padStart(2, '0');
      state.time = `${hh}:${mm}`;
      document.getElementById('viewDatePicker').value = state.date;
      document.getElementById('viewTimePicker').value = state.time;
      const vs = viewHeader.querySelector('.view-subtitle');
      if (vs) vs.textContent = formatDateForHeader(state.date, state.time);
      loadTableStatusForDate(state.date);
    });

    startTimeMonitors();
  }

  async function loadTableStatusForDate(date) {
    const grid = document.getElementById('tableStatusGrid');
    if (!grid) {
      console.error('loadTableStatusForDate: #tableStatusGrid not found in DOM');
      return;
    }
    grid.innerHTML = 'Loading...';
    try {
      const res = await fetch(`${API_GET_STATUS_BY_DATE}?date=${encodeURIComponent(date)}`);
      const json = await res.json().catch(e => { throw new Error('Invalid JSON from get_table_status_by_date.php: ' + e.message); });
      if (!json.success) throw new Error(json.error || 'API failed');
      const tables = json.data;
      grid.innerHTML = '';

      ['available', 'reserved', 'occupied'].forEach(status => {
        const list = tables.filter(t => t.status === status);
        if (!list.length) return;
        const header = document.createElement('div');
        header.className = 'table-status-header';
        header.style.gridColumn = '1 / -1';
        header.innerHTML = `<h2>${capitalize(status)}</h2>`;
        grid.appendChild(header);
        list.forEach(t => {
          const card = document.createElement('div');
          card.className = 'table-card';

          const startAttr = (t.start_time ? `${date} ${t.start_time}` : (t.start_dt ? t.start_dt : ''));
          const endAttr = (t.end_time ? `${date} ${t.end_time}` : (t.end_dt ? t.end_dt : ''));
          const rawResId = t.reservation_id || '';
          const resIdEscaped = rawResId ? escapeHtml(String(rawResId)) : '';
          const cabinName = escapeHtml(t.name || '');
          const statusDotColor = status==='available'?'#00b256':status==='reserved'?'#ffd400':'#d20000';

          // compute duration (minutes) if API didn't provide it
          let duration = '';
          if (typeof t.duration_minutes !== 'undefined' && t.duration_minutes !== null) {
            duration = t.duration_minutes;
          } else if (startAttr && endAttr) {
            try {
              const sIso = startAttr.replace(' ', 'T');
              const eIso = endAttr.replace(' ', 'T');
              const d1 = new Date(sIso);
              const d2 = new Date(eIso);
              if (!isNaN(d1) && !isNaN(d2)) {
                duration = Math.round((d2 - d1) / 60000); // minutes
              }
            } catch (e) { duration = ''; }
          }

          card.innerHTML = `
            <div class="title">${escapeHtml(t.name)}</div>
            <div class="seats-row"><span>🛏️</span> ${escapeHtml(t.seats)} Beds</div>
            <div class="status-row">
              <span class="status-dot" style="background:${statusDotColor}"></span>
              <span class="status-label">${capitalize(status)}</span>
            </div>
            ${t.guest ? `<div class="guest">${escapeHtml(t.guest)}</div>` : ''}
            ${(t.start_time && t.end_time) ? `<div class="time-range">${t.start_time} - ${t.end_time}${duration ? ' • ' + duration + ' min' : ''}</div>` : ''}
            <div class="time-monitor" data-start="${escapeHtml(startAttr)}" data-end="${escapeHtml(endAttr)}" data-duration="${escapeHtml(String(duration))}" data-reservation-id="${resIdEscaped}" data-cabin-name="${cabinName}"></div>
            <div class="card-actions" aria-hidden="false">
              <button class="icon-btn status-btn" aria-label="Change status" title="Change status">⚑</button>
              ${rawResId ? `<button class="icon-btn cancel-res-btn" aria-label="Cancel reservation" title="Cancel reservation">🗑</button>` : ''}
            </div>
          `;
          grid.appendChild(card);

          const statusBtn = card.querySelector('.status-btn');
          const cancelBtn = card.querySelector('.cancel-res-btn');

          if (statusBtn) {
            // Handler for Date view (t variable)
            statusBtn.addEventListener('click', async (ev) => {
              ev.stopPropagation();
              try {
                const current = t.status || 'available';
                const choice = prompt('Change status to: (r)eserved, (o)ccupied (set timer), (a)vailable? Enter r / o / a and press OK. Cancel to abort.');
                if (choice === null) return;
                const c = String(choice).trim().toLowerCase();
                const tableId = t.table_id || t.id;
                if (c === 'r') {
                  if (!confirm(`Change status of "${t.name}" from "${current}" to "reserved"?`)) return;
                  await changeTableStatus(tableId, 'reserved', () => {
                    if (state.time) loadTableStatusForDateTime(state.date, state.time);
                    else loadTableStatusForDate(state.date);
                  });
                  return;
                } else if (c === 'o') {
                  const durInput = prompt('Set occupied duration in minutes (e.g. 90). Leave blank to mark occupied without timer:');
                  if (durInput === null) return;
                  const minutes = durInput.trim() === '' ? 0 : parseInt(durInput.trim(), 10);
                  if (!isNaN(minutes) && minutes > 0) {
                    const guest = prompt('Guest name (optional):') || '';
                    try {
                      await createReservationNow(tableId, minutes, guest);
                      await changeTableStatus(tableId, 'occupied', () => {
                        if (state.time) loadTableStatusForDateTime(state.date, state.time);
                        else loadTableStatusForDate(state.date);
                      });
                    } catch (err) {
                      console.error('createReservationNow failed (Date view)', err);
                      alert('Failed to create reservation and mark occupied: ' + err.message);
                    }
                    return;
                  } else {
                    if (!confirm(`Mark "${t.name}" as occupied without timer?`)) return;
                    await changeTableStatus(tableId, 'occupied', () => {
                      if (state.time) loadTableStatusForDateTime(state.date, state.time);
                      else loadTableStatusForDate(state.date);
                    });
                    return;
                  }
                } else if (c === 'a') {
                  if (!confirm(`Change status of "${t.name}" from "${current}" to "available"?`)) return;
                  await changeTableStatus(tableId, 'available', () => {
                    if (state.time) loadTableStatusForDateTime(state.date, state.time);
                    else loadTableStatusForDate(state.date);
                  });
                  return;
                } else {
                  alert('No action taken. Enter r, o, or a next time (or cancel).');
                  return;
                }
              } catch (err) {
                console.error('status button handler error (Date view):', err);
                alert('An error occurred: ' + (err && err.message ? err.message : err));
              }
            });
          }

          if (cancelBtn) {
            cancelBtn.addEventListener('click', (ev) => {
              ev.stopPropagation();
              const reservationId = rawResId || null;
              const tableId = t.table_id || t.id || null;
              cancelReservation(reservationId, tableId);
            });
          }
        });
      });
      // call updateTimeMonitors safely
      try {
        if (typeof updateTimeMonitors === 'function') updateTimeMonitors();
      } catch (e) {
        console.error('updateTimeMonitors threw after rendering date view:', e);
      }
    } catch (err) {
      if (grid) {
        grid.innerHTML = `<div style="color:#900">Error loading cabins for date: ${escapeHtml(err.message)}</div>`;
      } else {
        console.error('Error loading cabins for date and grid missing:', err);
      }
    }
  }

  async function loadTableStatusForDateTime(date, time) {
    const grid = document.getElementById('tableStatusGrid');
    if (!grid) {
      console.error('loadTableStatusForDateTime: #tableStatusGrid not found in DOM');
      return;
    }
    grid.innerHTML = 'Loading...';
    try {
      const res = await fetch(`../api/get_availability.php?date=${encodeURIComponent(date)}&time=${encodeURIComponent(time)}`);
      const json = await res.json().catch(e => { throw new Error('Invalid JSON from get_availability.php: ' + e.message); });
      if (!json.success) throw new Error(json.error || 'API failed');
      const tables = json.data;
      grid.innerHTML = '';

      ['available', 'reserved', 'occupied'].forEach(status => {
        const list = tables.filter(t => t.status === status);
        if (!list.length) return;
        const header = document.createElement('div');
        header.className = 'table-status-header';
        header.style.gridColumn = '1 / -1';
        header.innerHTML = `<h2>${capitalize(status)}</h2>`;
        grid.appendChild(header);
        list.forEach(t => {
          const card = document.createElement('div');
          card.className = 'table-card';

          const startAttr = (t.start_time ? `${date} ${t.start_time}` : (t.start || ''));
          const endAttr = (t.end_time ? `${date} ${t.end_time}` : (t.end || ''));
          const rawResId = t.reservation_id || '';
          const resIdEscaped = rawResId ? escapeHtml(String(rawResId)) : '';
          const cabinName = escapeHtml(t.name || '');
          const statusDotColor = status==='available'?'#00b256':status==='reserved'?'#ffd400':'#d20000';

          // compute duration (minutes) if API didn't provide it
          let duration = '';
          if (typeof t.duration_minutes !== 'undefined' && t.duration_minutes !== null) {
            duration = t.duration_minutes;
          } else if (startAttr && endAttr) {
            try {
              const sIso = startAttr.replace(' ', 'T');
              const eIso = endAttr.replace(' ', 'T');
              const d1 = new Date(sIso);
              const d2 = new Date(eIso);
              if (!isNaN(d1) && !isNaN(d2)) {
                duration = Math.round((d2 - d1) / 60000); // minutes
              }
            } catch (e) { duration = ''; }
          }

          card.innerHTML = `
            <div class="title">${escapeHtml(t.name)}</div>
            <div class="seats-row"><span>🛏️</span> ${escapeHtml(t.seats)} Beds</div>
            <div class="status-row">
              <span class="status-dot" style="background:${statusDotColor}"></span>
              <span class="status-label">${capitalize(status)}</span>
            </div>
            ${t.guest ? `<div class="guest">${escapeHtml(t.guest)}</div>` : ''}
            ${(t.start_time && t.end_time) ? `<div class="time-range">${t.start_time} - ${t.end_time}${duration ? ' • ' + duration + ' min' : ''}</div>` : ''}
            <div class="time-monitor" data-start="${escapeHtml(startAttr)}" data-end="${escapeHtml(endAttr)}" data-duration="${escapeHtml(String(duration))}" data-reservation-id="${resIdEscaped}" data-cabin-name="${cabinName}"></div>
            <div class="card-actions" aria-hidden="false">
              <button class="icon-btn status-btn" aria-label="Change status" title="Change status">⚑</button>
              ${rawResId ? `<button class="icon-btn cancel-res-btn" aria-label="Cancel reservation" title="Cancel reservation">🗑</button>` : ''}
            </div>
          `;
          grid.appendChild(card);

          const statusBtn = card.querySelector('.status-btn');
          const cancelBtn = card.querySelector('.cancel-res-btn');

          if (statusBtn) {
            // Handler for Time view (t variable)
            statusBtn.addEventListener('click', async (ev) => {
              ev.stopPropagation();
              try {
                const current = t.status || 'available';
                const choice = prompt('Change status to: (r)eserved, (o)ccupied (set timer), (a)vailable? Enter r / o / a and press OK. Cancel to abort.');
                if (choice === null) return;
                const c = String(choice).trim().toLowerCase();
                const tableId = t.id;
                if (c === 'r') {
                  if (!confirm(`Change status of "${t.name}" from "${current}" to "reserved"?`)) return;
                  await changeTableStatus(tableId, 'reserved', () => loadTableStatusForDateTime(state.date, state.time));
                  return;
                } else if (c === 'o') {
                  const durInput = prompt('Set occupied duration in minutes (e.g. 90). Leave blank to mark occupied without timer:');
                  if (durInput === null) return;
                  const minutes = durInput.trim() === '' ? 0 : parseInt(durInput.trim(), 10);
                  if (!isNaN(minutes) && minutes > 0) {
                    const guest = prompt('Guest name (optional):') || '';
                    try {
                      await createReservationNow(tableId, minutes, guest);
                      await changeTableStatus(tableId, 'occupied', () => loadTableStatusForDateTime(state.date, state.time));
                    } catch (err) {
                      console.error('createReservationNow failed (Time view)', err);
                      alert('Failed to create reservation and mark occupied: ' + err.message);
                    }
                    return;
                  } else {
                    if (!confirm(`Mark "${t.name}" as occupied without timer?`)) return;
                    await changeTableStatus(tableId, 'occupied', () => loadTableStatusForDateTime(state.date, state.time));
                    return;
                  }
                } else if (c === 'a') {
                  if (!confirm(`Change status of "${t.name}" from "${current}" to "available"?`)) return;
                  await changeTableStatus(tableId, 'available', () => loadTableStatusForDateTime(state.date, state.time));
                  return;
                } else {
                  alert('No action taken. Enter r, o, or a next time (or cancel).');
                  return;
                }
              } catch (err) {
                console.error('status button handler error (Time view):', err);
                alert('An error occurred: ' + (err && err.message ? err.message : err));
              }
            });
          }

          if (cancelBtn) {
            cancelBtn.addEventListener('click', (ev) => {
              ev.stopPropagation();
              const reservationId = rawResId || null;
              const tableId = t.id || null;
              cancelReservation(reservationId, tableId);
            });
          }
        });
      });

      // call updateTimeMonitors safely
      try {
        if (typeof updateTimeMonitors === 'function') updateTimeMonitors();
      } catch (e) {
        console.error('updateTimeMonitors threw after rendering time view:', e);
      }
    } catch (err) {
      if (grid) {
        grid.innerHTML = `<div style="color:#900">Error loading cabins for date/time: ${escapeHtml(err.message)}</div>`;
      } else {
        console.error('Error loading cabins for date/time and grid missing:', err);
      }
    }
  }

  // Time view helpers
  function generateTimeSlots(start = '10:00', end = '23:00', interval = 30) {
    function toMinutes(hhmm) {
      const [h, m] = String(hhmm).split(':').map(Number);
      return h * 60 + m;
    }
    function toHHMM(mins) {
      const h = Math.floor(mins / 60);
      const m = mins % 60;
      return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
    }
    const startMin = toMinutes(start);
    const endMin = toMinutes(end);
    const slots = [];
    for (let t = startMin; t <= endMin; t += Math.max(1, interval)) {
      slots.push(toHHMM(t));
    }
    return slots;
  }

  function renderTimeView() {
    const subtitle = formatDateForHeader(state.date, state.time);
    viewHeader.innerHTML = `<h1>Time</h1>${subtitle ? `<div class="view-subtitle">${escapeHtml(subtitle)}</div>` : ''}`;

    if (partyControl) {
      partyControl.setAttribute('aria-hidden', 'true');
      partyControl.classList.remove('visible');
      partyControl.style.display = '';
    }
    if (partySortControl) {
      partySortControl.setAttribute('aria-hidden', 'true');
      partySortControl.style.display = 'none';
    }

    viewContent.innerHTML = `
      <div class="date-controls" role="region" aria-label="Date and time controls">
        <div class="picker-wrap" aria-hidden="false" style="align-items:center;">
          <label class="label" for="viewDatePicker" style="display:none">Date</label>
          <input type="date" id="viewDatePicker" value="${state.date || ''}" aria-label="Pick a date" />
          <span class="divider" aria-hidden="true"></span>
        </div>

        <div style="display:flex; gap:8px; align-items:center;">
          <button id="btnSearchSlot" class="btn primary" title="Show availability for chosen date/time">Show Availability</button>
          <button id="btnAddReservationSlot" class="btn add" title="Create a new reservation">+ New Reservation</button>
        </div>
      </div>

      <div id="timeSlotsContainer" class="time-container">
        <div class="time-grid" id="timeGrid" aria-label="Available time slots"></div>
      </div>

      <div id="tableStatusGrid" class="cards-grid" style="margin-top:12px;"></div>
    `;

    const datePicker = document.getElementById('viewDatePicker');
    if (datePicker) {
      datePicker.value = state.date || '';
      datePicker.addEventListener('change', e => {
        state.date = e.target.value;
        const vs = viewHeader.querySelector('.view-subtitle');
        if (vs) vs.textContent = formatDateForHeader(state.date, state.time);
      });
    }

    const slots = generateTimeSlots('10:00', '23:00', 30);
    const grid = document.getElementById('timeGrid');
    if (grid) {
      grid.innerHTML = '';
      slots.forEach(ts => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'time-slot';
        btn.dataset.time = ts;
        btn.textContent = ts;
        if (state.time === ts) btn.classList.add('selected');
        btn.addEventListener('click', (ev) => {
          ev.preventDefault();
          const prev = grid.querySelector('.time-slot.selected');
          if (prev) prev.classList.remove('selected');
          btn.classList.add('selected');
          state.time = ts;
          const vs = viewHeader.querySelector('.view-subtitle');
          if (vs) vs.textContent = formatDateForHeader(state.date, state.time);
          if (state.date && state.time) loadTableStatusForDateTime(state.date, state.time);
        });
        grid.appendChild(btn);
      });
    }

    document.getElementById('btnSearchSlot')?.addEventListener('click', () => {
      if (!state.date) return alert('Please select a date first.');
      if (!state.time) return alert('Please select a time first.');
      loadTableStatusForDateTime(state.date, state.time);
    });

    document.getElementById('btnAddReservationSlot')?.addEventListener('click', () => {
      if (!state.date || !state.time) return alert('Please select date and time first to create a reservation.');
      openNewReservationModal();
    });

    stopTimeMonitors();
  }

  // Modals & actions (unchanged)
  function openEditModal(table) {
    const isNew = !table || !table.id;
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';
    overlay.innerHTML = `
      <div class="modal" role="dialog" aria-modal="true" aria-label="${isNew ? 'Create Cabin' : 'Edit ' + escapeHtml(table.name)}">
        <h3>${isNew ? 'New Cabin' : 'Edit ' + escapeHtml(table.name)}</h3>
        <div class="form-row">
          <label for="modalName">Cabin Name</label>
          <input id="modalName" type="text" value="${table && table.name ? escapeHtml(table.name) : ''}" />
        </div>
        <div class="form-row">
          <label for="modalSeats">Beds</label>
          <input id="modalSeats" type="number" min="1" max="50" value="${table && table.seats ? table.seats : 2}" />
        </div>
        <div class="form-row">
          <label for="modalGuest">Guest (optional)</label>
          <input id="modalGuest" type="text" value="${table && table.guest ? escapeHtml(table.guest) : ''}" />
        </div>
        <div class="modal-actions">
          <button id="modalCancel" class="btn">Cancel</button>
          <button id="modalSave" class="btn primary">${isNew ? 'Create' : 'Save'}</button>
        </div>
      </div>
    `;
    document.body.appendChild(overlay);

    const modalName = overlay.querySelector('#modalName');
    const modalSeats = overlay.querySelector('#modalSeats');
    const modalGuest = overlay.querySelector('#modalGuest');
    overlay.querySelector('#modalCancel').addEventListener('click', () => overlay.remove());

    overlay.querySelector('#modalSave').addEventListener('click', async () => {
      const name = modalName.value.trim() || (table && table.name) || 'Cabin';
      const seats = parseInt(modalSeats.value, 10) || 2;
      const guest = modalGuest.value.trim();
      if (isNew) {
        try {
          const res = await fetch(API_CREATE, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, seats, guest })
          });
          const j = await res.json();
          if (!j.success) throw new Error(j.error || 'Create failed');
          await loadTables();
          overlay.remove();
          return;
        } catch (err) {
          console.warn('API create failed:', err);
        }
      } else {
        try {
          const payload = { id: table.id, seats, name, guest };
          const res = await fetch(API_UPDATE, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
          });
          const j = await res.json();
          if (!j.success) throw new Error(j.error || 'Update failed');
          await loadTables();
          overlay.remove();
        } catch (err) {
          alert('Failed to update cabin: ' + err.message);
        }
      }
    });

    overlay.addEventListener('click', ev => { if (ev.target === overlay) overlay.remove(); });
    setTimeout(() => modalName.focus(), 50);
  }

  async function confirmDelete(table) {
    if (!confirm(`Delete ${table.name}? This action cannot be undone.`)) return;
    try {
      const res = await fetch(API_DELETE, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json; charset=utf-8' },
        body: JSON.stringify({ id: table.id })
      });
      const j = await res.json();
      if (!j.success) throw new Error(j.error || 'Delete failed');
      await loadTables();
    } catch (err) {
      alert('Failed to delete cabin: ' + err.message);
    }
  }

  function openNewTableModal() { openEditModal({}); }

  function openNewReservationModal() {
    if (!state.date || !state.time) return alert('Please select date and time first!');
    fetch(`../api/get_availability.php?date=${encodeURIComponent(state.date)}&time=${encodeURIComponent(state.time)}`)
      .then(res => res.json())
      .then(json => {
        if (!json.success) throw new Error(json.error || 'API failed');
        const available = json.data.filter(t => t.status === 'available');
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay';
        overlay.innerHTML = `
          <div class="modal" role="dialog" aria-modal="true">
            <h3>New Reservation</h3>
            <div class="form-row">
              <label for="modalTableSelect">Cabin</label>
              <select id="modalTableSelect">
                ${available.map(t => `<option value="${t.id}">${escapeHtml(t.name)} (${t.seats} beds)</option>`).join('')}
              </select>
            </div>
            <div class="form-row">
              <label>Date</label><input type="date" id="modalDate" value="${state.date}" readonly />
            </div>
            <div class="form-row">
              <label>Time</label><input type="time" id="modalTime" value="${state.time}" readonly />
            </div>
            <div class="form-row">
              <label for="modalGuest">Guest Name</label><input id="modalGuest" type="text" />
            </div>
            <div class="modal-actions">
              <button id="modalCancel" class="btn">Cancel</button>
              <button id="modalSave" class="btn primary">Create</button>
            </div>
          </div>
        `;
        document.body.appendChild(overlay);

        overlay.querySelector('#modalCancel').addEventListener('click', () => overlay.remove());
        overlay.querySelector('#modalSave').addEventListener('click', () => {
          const table_id = overlay.querySelector('#modalTableSelect').value;
          const guest = overlay.querySelector('#modalGuest').value.trim();
          fetch(API_CREATE_RESERVATION, {
            method: 'POST',
            headers: {"Content-Type": "application/json"},
            body: JSON.stringify({
              table_id,
              date: state.date,
              start_time: state.time,
              guest
            })
          }).then(res => res.json()).then(j => {
            if (!j.success) throw new Error(j.error || 'Create reservation failed');
            overlay.remove();
            loadTableStatusForDateTime(state.date, state.time);
          }).catch(err => alert("Create reservation failed: " + err.message));
        });
        overlay.addEventListener('click', ev => { if (ev.target === overlay) overlay.remove(); });
      })
      .catch(err => alert('Failed to fetch availability: ' + err.message));
  }

  // Search & filters
  if (searchInput) {
    searchInput.addEventListener('input', e => { state.search = e.target.value; renderView(); });
  }
  if (searchClear) {
    searchClear.addEventListener('click', () => { if (searchInput) searchInput.value = ''; state.search = ''; renderView(); });
  }
  if (filterButtons && filterButtons.length) {
    filterButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        filterButtons.forEach(b => { b.classList.remove('active'); b.setAttribute('aria-selected', 'false'); });
        btn.classList.add('active');
        btn.setAttribute('aria-selected', 'true');
        state.filter = btn.dataset.filter;
        renderView();
      });
    });
  }
  if (partySelect) {
    partySelect.addEventListener('change', e => { state.partySeats = e.target.value; renderView(); });
    state.partySeats = partySelect.value || 'any';
  }
  if (partySortSelect) {
    partySortSelect.addEventListener('change', e => { state.partySort = e.target.value; renderView(); });
    state.partySort = partySortSelect.value || 'default';
  }

  function renderView() {
    switch (state.filter) {
      case 'party': renderPartyView(); break;
      case 'date': renderDateView(); break;
      case 'time': renderTimeView(); break;
      default: renderAllView();
    }
  }

  document.getElementById('btnAddReservation')?.addEventListener('click', e => { e.preventDefault(); openNewTableModal(); });
  document.getElementById('fabNew')?.addEventListener('click', e => { e.preventDefault(); openNewTableModal(); });

  // modal CSS injection (unchanged)
  (function injectModalCss() {
    if (document.getElementById('modal-styles')) return;
    const css = `
      .modal-overlay { position: fixed; left:0; top:0; right:0; bottom:0; background: rgba(0,0,0,0.45);
        display:flex; align-items:center; justify-content:center; z-index:9999; }
      .modal { background: #fff; padding:18px; border-radius:10px; width:420px; max-width:95%;
        box-shadow:0 8px 28px rgba(0,0,0,0.4); }
      .modal h3 { margin:0 0 12px 0; font-size:18px; }
      .form-row { margin-bottom:10px; display:flex; flex-direction:column; gap:6px; }
      .form-row label { font-weight:700; font-size:13px; }
      .form-row input, .form-row select { padding:8px 10px; font-size:14px; border-radius:6px; border:1px solid #ddd; }
      .modal-actions { display:flex; justify-content:flex-end; gap:8px; margin-top:8px; }
      .btn { padding:8px 12px; border-radius:8px; border:1px solid #ccc; background:#f5f5f5; cursor:pointer; }
      .btn.primary { background:#001b89; color:#fff; border-color:#001b89; }
      #tables-toast-container { font-family: Inter, Poppins, sans-serif; }
    `;
    const s = document.createElement('style');
    s.id = 'modal-styles';
    s.textContent = css;
    document.head.appendChild(s);
  })();

  // initial load
  async function loadTables() {
    try {
      const res = await fetch(API_GET, { cache: 'no-store' });
      const json = await res.json().catch(e => { throw new Error('Invalid JSON from get_tables.php: ' + e.message); });
      if (!json.success) throw new Error(json.error || 'Failed to load cabins');
      tablesData = json.data.map(t => ({
        id: Number(t.id),
        name: t.name,
        status: t.status,
        seats: Number(t.seats),
        guest: t.guest || ""
      }));
      renderView();
    } catch (err) {
      console.error('loadTables error', err);
      tablesData = [
        { id: 1, name: 'Cabin 1', status: 'occupied', seats: 6, guest: 'Taenamo Jiro' },
        { id: 2, name: 'Cabin 2', status: 'reserved', seats: 4, guest: 'WOwmsi' },
        { id: 3, name: 'Cabin 3', status: 'available', seats: 2, guest: '' },
      ];
      const grid = document.getElementById('cardsGrid');
      if (grid) grid.innerHTML = `<div style="padding:18px;color:#900">Local fallback data (API failed).</div>`;
      renderView();
    }
  }

  // Expose for debugging
  window._tablesApp = { data: tablesData, state, renderView, renderCardsInto, openEditModal, loadTables };

  loadTables();
});