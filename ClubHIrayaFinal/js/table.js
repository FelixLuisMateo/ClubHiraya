// ../js/table.js
// Tables UI: list, party/date/time views, availability, reservations + status change button
// Requirements: expects API endpoints under ../api/ (get_tables.php, create_table.php,
// update_table.php, delete_table.php, get_table_status_by_date.php, get_availability.php, create_reservation.php)

document.addEventListener('DOMContentLoaded', () => {
  // API endpoints
  const API_GET = '../api/get_tables.php';
  const API_UPDATE = '../api/update_table.php';
  const API_DELETE = '../api/delete_table.php';
  const API_CREATE = '../api/create_table.php';
  const API_GET_STATUS_BY_DATE = '../api/get_table_status_by_date.php';

  let tablesData = [];

  // DOM references
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

  // State - include time so date/time view doesn't read undefined
  const state = {
    filter: 'all',
    search: '',
    partySeats: 'any',
    partySort: 'asc',
    date: '',
    time: '',
    selectedId: null,
  };

  // Ensure default date/time on initial load (today & current time)
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

  // Helpers
  function capitalize(s) { return s && s.length ? s[0].toUpperCase() + s.slice(1) : ''; }
  function escapeHtml(text = '') {
    return String(text).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]);
  }

  // Friendly formatted subtitle for date/time display
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

  // Status utilities
  // cycle: available -> reserved -> occupied -> available
  function nextStatusFor(current) {
    const order = ['available', 'reserved', 'occupied'];
    const idx = order.indexOf(current);
    if (idx === -1) return 'reserved';
    return order[(idx + 1) % order.length];
  }

  // send status update to server (uses api/update_table.php)
  async function changeTableStatus(tableId, newStatus, refreshCallback) {
    try {
      const res = await fetch(API_UPDATE, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: Number(tableId), status: newStatus })
      });
      const j = await res.json();
      if (!j.success) throw new Error(j.error || 'Update failed');
      // Refresh main tables data
      await loadTables();
      if (typeof refreshCallback === 'function') refreshCallback();
    } catch (err) {
      alert('Failed to change table status: ' + err.message);
    }
  }

  // Load tables list from API
  async function loadTables() {
    try {
      const res = await fetch(API_GET, { cache: 'no-store' });
      if (!res.ok) throw new Error('Network response was not ok: ' + res.status);
      const json = await res.json();
      if (!json.success) throw new Error(json.error || 'Failed to load tables');
      tablesData = json.data.map(t => ({
        id: Number(t.id),
        name: t.name,
        status: t.status,
        seats: Number(t.seats),
        guest: t.guest || ""
      }));
      renderView();
    } catch (err) {
      // Fallback sample data if API fails
      tablesData = [
        { id: 1, name: 'Table 1', status: 'occupied', seats: 6, guest: 'Taenamo Jiro' },
        { id: 2, name: 'Table 2', status: 'reserved', seats: 4, guest: 'WOwmsi' },
        { id: 3, name: 'Table 3', status: 'available', seats: 2, guest: '' },
      ];
      const grid = document.getElementById('cardsGrid');
      if (grid) grid.innerHTML = `<div style="padding:18px;color:#900">Local fallback data (API failed).</div>`;
      renderView();
    }
  }

  // Renders a list of table cards into a container
  function renderCardsInto(container, data) {
    container.innerHTML = '';
    if (!data.length) {
      container.innerHTML = '<div style="padding:18px; font-weight:700">No tables found</div>';
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

      // Card HTML includes a status button (⚑) that cycles status
      card.innerHTML = `
        <div class="title">${escapeHtml(tbl.name)}</div>
        <div class="status-row">
          <span class="status-dot" style="background:${statusDotColor}"></span>
          <span class="status-label">${capitalize(status)}</span>
        </div>
        <div class="seats-row"><span>👥</span> ${escapeHtml(String(tbl.seats))} Seats</div>
        ${tbl.guest ? `<div class="guest">${escapeHtml(tbl.guest)}</div>` : ''}
        <div class="card-actions" aria-hidden="false">
          <button class="icon-btn status-btn" aria-label="Change status" title="Change status">⚑</button>
          <button class="icon-btn edit-btn" aria-label="Edit table" title="Edit">✎</button>
          <button class="icon-btn delete-btn" aria-label="Delete table" title="Delete">✖</button>
        </div>
      `;

      // Interaction bindings
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
        statusBtn.addEventListener('click', async (e) => {
          e.stopPropagation();
          const current = tbl.status || 'available';
          const next = nextStatusFor(current);
          if (!confirm(`Change status of "${tbl.name}" from "${current}" to "${next}" ?`)) return;
          await changeTableStatus(tbl.id, next, () => {
            // Refresh the current view appropriately
            if (state.filter === 'date' && state.date) {
              if (state.time) loadTableStatusForDateTime(state.date, state.time);
              else loadTableStatusForDate(state.date);
            } else {
              renderView();
            }
          });
        });
      }

      container.appendChild(card);
    });
  }

  function setSelected(id) {
    state.selectedId = id;
    document.querySelectorAll('.table-card').forEach(c => c.classList.toggle('active', c.dataset.id == id));
    const selected = tablesData.find(t => t.id === Number(id));
    if (selected) console.info('Selected table:', selected.name);
  }

  // Views
  function renderAllView() {
    viewHeader.innerHTML = '<h1>All Tables</h1>';
    viewContent.innerHTML = `<div class="cards-grid" id="cardsGrid" role="list"></div>`;
    cardsGrid = document.getElementById('cardsGrid');
    // hide party controls
    if (partyControl) {
      partyControl.setAttribute('aria-hidden', 'true');
      partyControl.classList.remove('visible');
      partyControl.style.display = '';
    }
    if (partySortControl) {
      partySortControl.setAttribute('aria-hidden', 'true');
      partySortControl.style.display = 'none';
    }
    // Search filter
    const s = state.search.trim().toLowerCase();
    let filtered = tablesData;
    if (s) filtered = tablesData.filter(t => t.name.toLowerCase().includes(s));
    renderCardsInto(cardsGrid, filtered);
  }

  function renderPartyView() {
    viewHeader.innerHTML = '<h1>Party Size</h1>';
    if (partyControl) {
      partyControl.setAttribute('aria-hidden', 'false');
      partyControl.classList.add('visible'); // CSS: .party-size-control.visible -> display:flex
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
      const [min, max] = v === 2 ? [1, 2] : v === 4 ? [3, 4] : v === 6 ? [5, 6] : [7, 8];
      filtered = tablesData.filter(t => t.seats >= min && t.seats <= max);
    }
    // Sorting
    if (state.partySort === 'asc') {
      filtered = filtered.slice().sort((a, b) => a.seats - b.seats);
    } else if (state.partySort === 'desc') {
      filtered = filtered.slice().sort((a, b) => b.seats - a.seats);
    }
    renderCardsInto(cardsGrid, filtered);
  }

  // Date view (single authoritative definition)
  function renderDateView() {
    // header with subtitle showing the currently selected date/time
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

    // Render the date-controls markup that matches the CSS
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

    // Wire up inputs and buttons
    const datePicker = document.getElementById('viewDatePicker');
    if (datePicker) {
      datePicker.value = state.date || '';
      datePicker.addEventListener('change', e => {
        state.date = e.target.value;
        // update header subtitle immediately
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
  }

  // Date-only status loading
  async function loadTableStatusForDate(date) {
    const grid = document.getElementById('tableStatusGrid');
    if (!grid) return;
    grid.innerHTML = 'Loading...';
    try {
      const res = await fetch(`${API_GET_STATUS_BY_DATE}?date=${encodeURIComponent(date)}`);
      const json = await res.json();
      if (!json.success) throw new Error(json.error || 'API failed');
      const tables = json.data;

      // Clear the loading text before rendering results
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
          card.innerHTML = `
            <div class="title">${escapeHtml(t.name)}</div>
            <div class="seats-row"><span>👥</span> ${escapeHtml(t.seats)} Seats</div>
            <div class="status-row">
              <span class="status-dot" style="background:${status==='available'?'#00b256':status==='reserved'?'#ffd400':'#d20000'}"></span>
              <span class="status-label">${capitalize(status)}</span>
            </div>
            ${t.guest ? `<div class="guest">${escapeHtml(t.guest)}</div>` : ''}
            ${(t.start_time && t.end_time) ? `<div class="time-range">${t.start_time} - ${t.end_time}</div>` : ''}
            <div class="card-actions" aria-hidden="false">
              <button class="icon-btn status-btn" aria-label="Change status" title="Change status">⚑</button>
            </div>
          `;
          grid.appendChild(card);

          const statusBtn = card.querySelector('.status-btn');
          if (statusBtn) {
            statusBtn.addEventListener('click', async (ev) => {
              ev.stopPropagation();
              const current = t.status || 'available';
              const next = nextStatusFor(current);
              if (!confirm(`Change status of "${t.name}" from "${current}" to "${next}" ?`)) return;
              await changeTableStatus(t.table_id || t.id, next, () => {
                if (state.time) loadTableStatusForDateTime(state.date, state.time);
                else loadTableStatusForDate(state.date);
              });
            });
          }
        });
      });
    } catch (err) {
      grid.innerHTML = `<div style="color:#900">Error loading tables for date: ${escapeHtml(err.message)}</div>`;
    }
  }

  // Date+time availability loading
  async function loadTableStatusForDateTime(date, time) {
    const grid = document.getElementById('tableStatusGrid');
    if (!grid) return;
    grid.innerHTML = 'Loading...';
    try {
      const res = await fetch(`../api/get_availability.php?date=${encodeURIComponent(date)}&time=${encodeURIComponent(time)}`);
      const json = await res.json();
      if (!json.success) throw new Error(json.error || 'API failed');
      const tables = json.data;

      // Clear the loading text before rendering results
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
          card.innerHTML = `
            <div class="title">${escapeHtml(t.name)}</div>
            <div class="seats-row"><span>👥</span> ${escapeHtml(t.seats)} Seats</div>
            <div class="status-row">
              <span class="status-dot" style="background:${status==='available'?'#00b256':status==='reserved'?'#ffd400':'#d20000'}"></span>
              <span class="status-label">${capitalize(status)}</span>
            </div>
            ${t.guest ? `<div class="guest">${escapeHtml(t.guest)}</div>` : ''}
            <div class="card-actions" aria-hidden="false">
              <button class="icon-btn status-btn" aria-label="Change status" title="Change status">⚑</button>
            </div>
          `;
          grid.appendChild(card);

          const statusBtn = card.querySelector('.status-btn');
          if (statusBtn) {
            statusBtn.addEventListener('click', async (ev) => {
              ev.stopPropagation();
              const current = t.status || 'available';
              const next = nextStatusFor(current);
              if (!confirm(`Change status of "${t.name}" from "${current}" to "${next}" ?`)) return;
              await changeTableStatus(t.id, next, () => {
                loadTableStatusForDateTime(state.date, state.time);
              });
            });
          }
        });
      });
    } catch (err) {
      grid.innerHTML = `<div style="color:#900">Error loading tables for date/time: ${escapeHtml(err.message)}</div>`;
    }
  }

  // Time view helpers and renderer

  // Helper: generate an array of time strings between start and end (inclusive) at interval minutes
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

  // Render the Time view: date picker + grid of time slots
  function renderTimeView() {
    // header subtitle uses the same helper as date view
    const subtitle = formatDateForHeader(state.date, state.time);
    viewHeader.innerHTML = `<h1>Time</h1>${subtitle ? `<div class="view-subtitle">${escapeHtml(subtitle)}</div>` : ''}`;

    // hide party controls same as date view
    if (partyControl) {
      partyControl.setAttribute('aria-hidden', 'true');
      partyControl.classList.remove('visible');
      partyControl.style.display = '';
    }
    if (partySortControl) {
      partySortControl.setAttribute('aria-hidden', 'true');
      partySortControl.style.display = 'none';
    }

    // Build markup: date picker + time grid
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

    // Wire up date picker
    const datePicker = document.getElementById('viewDatePicker');
    if (datePicker) {
      datePicker.value = state.date || '';
      datePicker.addEventListener('change', e => {
        state.date = e.target.value;
        // update header subtitle immediately
        const vs = viewHeader.querySelector('.view-subtitle');
        if (vs) vs.textContent = formatDateForHeader(state.date, state.time);
        // don't auto-load availability here; wait for time selection or Show Availability
      });
    }

    // Generate default time slots (you can change start/end/interval as needed)
    const slots = generateTimeSlots('10:00', '23:00', 30); // every 30 mins

    const grid = document.getElementById('timeGrid');
    if (grid) {
      grid.innerHTML = '';
      slots.forEach(ts => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'time-slot';
        btn.dataset.time = ts;
        btn.textContent = ts;
        // mark selected if matches state.time
        if (state.time === ts) btn.classList.add('selected');
        btn.addEventListener('click', (ev) => {
          ev.preventDefault();
          // deselect previous and select current
          const prev = grid.querySelector('.time-slot.selected');
          if (prev) prev.classList.remove('selected');
          btn.classList.add('selected');
          state.time = ts;
          // update header subtitle
          const vs = viewHeader.querySelector('.view-subtitle');
          if (vs) vs.textContent = formatDateForHeader(state.date, state.time);
          // auto-load availability when selecting a time (optional)
          if (state.date && state.time) loadTableStatusForDateTime(state.date, state.time);
        });
        grid.appendChild(btn);
      });
    }

    // wire up Show Availability and New Reservation buttons
    document.getElementById('btnSearchSlot')?.addEventListener('click', () => {
      if (!state.date) return alert('Please select a date first.');
      if (!state.time) return alert('Please select a time first.');
      loadTableStatusForDateTime(state.date, state.time);
    });

    document.getElementById('btnAddReservationSlot')?.addEventListener('click', () => {
      if (!state.date || !state.time) return alert('Please select date and time first to create a reservation.');
      openNewReservationModal();
    });
  }

  // Modal for creating/editing a table
  function openEditModal(table) {
    const isNew = !table || !table.id;
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';
    overlay.innerHTML = `
      <div class="modal" role="dialog" aria-modal="true" aria-label="${isNew ? 'Create Table' : 'Edit ' + escapeHtml(table.name)}">
        <h3>${isNew ? 'New Table' : 'Edit ' + escapeHtml(table.name)}</h3>
        <div class="form-row">
          <label for="modalName">Table Name</label>
          <input id="modalName" type="text" value="${table && table.name ? escapeHtml(table.name) : ''}" />
        </div>
        <div class="form-row">
          <label for="modalSeats">Seats</label>
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
      const name = modalName.value.trim() || (table && table.name) || 'Table';
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
          alert('Failed to update table: ' + err.message);
        }
      }
    });

    overlay.addEventListener('click', ev => { if (ev.target === overlay) overlay.remove(); });
    setTimeout(() => modalName.focus(), 50);
  }

  // Delete table
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
      alert('Failed to delete table: ' + err.message);
    }
  }

  // Add Table modal
  function openNewTableModal() {
    openEditModal({});
  }

  // New reservation modal (date/time already filled)
  function openNewReservationModal() {
    if (!state.date || !state.time) return alert('Please select date and time first!');
    // Fetch available tables for chosen slot
    fetch(`../api/get_availability.php?date=${encodeURIComponent(state.date)}&time=${encodeURIComponent(state.time)}`)
      .then(res => res.json())
      .then(json => {
        if (!json.success) throw new Error(json.error || 'API failed');
        const available = json.data.filter(t => t.status === 'available');
        // Modal HTML
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay';
        overlay.innerHTML = `
          <div class="modal" role="dialog" aria-modal="true">
            <h3>New Reservation</h3>
            <div class="form-row">
              <label for="modalTableSelect">Table</label>
              <select id="modalTableSelect">
                ${available.map(t => `<option value="${t.id}">${escapeHtml(t.name)} (${t.seats} seats)</option>`).join('')}
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
          // POST reservation to backend (requires api/create_reservation.php)
          fetch('../api/create_reservation.php', {
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
            // reload that date/time's status
            loadTableStatusForDateTime(state.date, state.time);
          }).catch(err => alert("Create reservation failed: " + err.message));
        });
        overlay.addEventListener('click', ev => { if (ev.target === overlay) overlay.remove(); });
      })
      .catch(err => alert('Failed to fetch availability: ' + err.message));
  }

  // Search box
  if (searchInput) {
    searchInput.addEventListener('input', e => { state.search = e.target.value; renderView(); });
  }
  if (searchClear) {
    searchClear.addEventListener('click', () => { if (searchInput) searchInput.value = ''; state.search = ''; renderView(); });
  }

  // Wire up filter buttons
  if (filterButtons && filterButtons.length) {
    filterButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        // Update active visual state and aria-selected for accessibility
        filterButtons.forEach(b => {
          b.classList.remove('active');
          b.setAttribute('aria-selected', 'false');
        });
        btn.classList.add('active');
        btn.setAttribute('aria-selected', 'true');

        state.filter = btn.dataset.filter;
        renderView();
      });
    });
  }

  // Party size control
  if (partySelect) {
    partySelect.addEventListener('change', e => { state.partySeats = e.target.value; renderView(); });
    state.partySeats = partySelect.value || 'any';
  }
  if (partySortSelect) {
    partySortSelect.addEventListener('change', e => { state.partySort = e.target.value; renderView(); });
    state.partySort = partySortSelect.value || 'default';
  }

  // Main view router (handle 'time' with dedicated view)
  function renderView() {
    switch (state.filter) {
      case 'party': renderPartyView(); break;
      case 'date': renderDateView(); break;
      case 'time': renderTimeView(); break;
      default: renderAllView();
    }
  }

  // Add buttons
  document.getElementById('btnAddReservation')?.addEventListener('click', e => {
    e.preventDefault(); openNewTableModal();
  });
  document.getElementById('fabNew')?.addEventListener('click', e => {
    e.preventDefault(); openNewTableModal();
  });

  // Modal CSS injection (keeps modal styling available if not present)
  (function injectModalCss() {
    if (document.getElementById('modal-styles')) return;
    const css = `
      .modal-overlay {
        position: fixed; left:0; top:0; right:0; bottom:0; background: rgba(0,0,0,0.45);
        display:flex; align-items:center; justify-content:center; z-index:9999;}
      .modal {background: #fff; padding:18px; border-radius:10px; width:420px; max-width:95%; box-shadow:0 8px 28px rgba(0,0,0,0.4);}
      .modal h3 { margin:0 0 12px 0; font-size:18px;}
      .form-row { margin-bottom:10px; display:flex; flex-direction:column; gap:6px;}
      .form-row label { font-weight:700; font-size:13px;}
      .form-row input { padding:8px 10px; font-size:14px; border-radius:6px; border:1px solid #ddd;}
      .modal-actions { display:flex; justify-content:flex-end; gap:8px; margin-top:8px;}
      .btn { padding:8px 12px; border-radius:8px; border:1px solid #ccc; background:#f5f5f5; cursor:pointer;}
      .btn.primary { background:#001b89; color:#fff; border-color:#001b89;}
    `;
    const s = document.createElement('style');
    s.id = 'modal-styles';
    s.textContent = css;
    document.head.appendChild(s);
  })();

  // Initial load
  loadTables();
  window._tablesApp = { data: tablesData, state, renderView, renderCardsInto, openEditModal, loadTables };
});