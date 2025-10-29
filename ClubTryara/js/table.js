// file: ../js/table.js
// Updated to bind "Create Reservation" modal to api/create_reservation.php.
// - Adds openReservationModal() and wires global New/FAB buttons to it.
// - On successful reservation creation, reloads tables and availability.

document.addEventListener('DOMContentLoaded', () => {
  /* --------------------------
     CONFIG
     -------------------------- */
  const API_GET = '../api/get_tables.php';
  const API_UPDATE = '../api/update_table.php';
  const API_AVAILABILITY = '../api/get_availability.php';
  const API_CREATE_RESERVATION = '../api/create_reservation.php'; // new endpoint
  const CARDS_GRID_ID = 'cardsGrid';
  const LOCALSTORAGE_ALLOW_LARGER = 'tables_allow_larger';

  /* --------------------------
     STATE
     -------------------------- */
  const state = {
    tables: [],
    filter: 'all',
    search: '',
    partySeats: 'any',
    allowLarger: false,
    date: '',
    time: '',
    duration: 90,
    availableIds: null,
    selectedId: null
  };

  /* --------------------------
     DOM REFS
     -------------------------- */
  const refs = {
    viewHeader: document.getElementById('viewHeader'),
    viewContent: document.getElementById('viewContent'),
    searchInput: document.getElementById('searchInput'),
    searchClear: document.getElementById('searchClear'),
    filterButtons: Array.from(document.querySelectorAll('.filter-btn')),
    partyControl: document.getElementById('partyControl'),
    partySelect: document.getElementById('partySelect'),
    allowLargerCheckbox: document.getElementById('allowLarger'),
    partyBuckets: Array.from(document.querySelectorAll('.bucket-btn')),
    dateInput: document.getElementById('filterDateInput'),
    timeInput: document.getElementById('filterTimeInput'),
    btnAddReservation: document.getElementById('btnAddReservation'),
    fabNew: document.getElementById('fabNew')
  };

  if (!refs.viewHeader || !refs.viewContent) {
    console.error('UI containers missing (#viewHeader or #viewContent)');
    return;
  }

  /* --------------------------
     UTILITIES
     -------------------------- */
  const escapeHtml = (text = '') => String(text).replace(/[&<>"']/g, (m) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]);
  const capitalize = (s = '') => (s ? s[0].toUpperCase() + s.slice(1) : '');
  const createElementFromHTML = (html) => { const t = document.createElement('template'); t.innerHTML = html.trim(); return t.content.firstElementChild; };
  const getCardsGrid = () => document.getElementById(CARDS_GRID_ID);
  const showToast = (msg, t = 2200) => {
    const id = 'simple-toast'; let el = document.getElementById(id);
    if (!el) { el = document.createElement('div'); el.id = id; el.style.position = 'fixed'; el.style.right = '18px'; el.style.bottom = '18px'; el.style.zIndex = '99999'; document.body.appendChild(el); }
    const it = document.createElement('div'); it.textContent = msg; it.style.background = '#111'; it.style.color = '#fff'; it.style.padding = '8px 12px'; it.style.marginTop = '8px'; it.style.borderRadius = '8px'; it.style.boxShadow = '0 6px 18px rgba(0,0,0,0.18)'; el.appendChild(it); setTimeout(() => it.remove(), t);
  };

  /* --------------------------
     API HELPERS
     -------------------------- */
  async function fetchJson(url, options = {}) {
    const res = await fetch(url, options);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
  }

  async function loadTables() {
    try {
      const json = await fetchJson(API_GET, { cache: 'no-store' });
      if (!json.success) throw new Error(json.error || 'Invalid response');
      state.tables = (json.data || []).map(t => ({ id: Number(t.id), name: t.name, status: t.status, seats: Number(t.seats), guest: t.guest || '' }));
    } catch (err) {
      console.warn('loadTables fallback:', err);
      state.tables = [
        { id: 1, name: 'Table 1', status: 'occupied', seats: 6, guest: '' },
        { id: 2, name: 'Table 2', status: 'available', seats: 4, guest: '' },
        { id: 3, name: 'Table 3', status: 'reserved', seats: 2, guest: '' },
        { id: 4, name: 'Table 4', status: 'occupied', seats: 4, guest: '' },
        { id: 5, name: 'Table 5', status: 'available', seats: 2, guest: '' },
        { id: 6, name: 'Table 6', status: 'available', seats: 8, guest: '' },
        { id: 7, name: 'Table 7', status: 'available', seats: 2, guest: '' },
        { id: 8, name: 'Table 8', status: 'reserved', seats: 4, guest: '' },
        { id: 9, name: 'Table 9', status: 'occupied', seats: 6, guest: '' }
      ];
      const grid = getCardsGrid(); if (grid) grid.innerHTML = `<div style="padding:18px;color:#900">Using local fallback data (API load failed).</div>`;
    }
    // restore allow-larger preference
    state.allowLarger = localStorage.getItem(LOCALSTORAGE_ALLOW_LARGER) === '1';
    if (refs.allowLargerCheckbox) refs.allowLargerCheckbox.checked = state.allowLarger;
    renderView();
    updateAvailabilityIfNeeded();
  }

  async function sendUpdate(payload) {
    const res = await fetchJson(API_UPDATE, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    return res;
  }

  /* --------------------------
     AVAILABILITY helpers
     -------------------------- */
  async function fetchAvailability({ seats = 1, date = '', time = '', duration = 90 } = {}) {
    const params = new URLSearchParams(); params.set('seats', String(seats));
    if (date) params.set('date', date); if (time) params.set('time', time); if (duration) params.set('duration', String(duration));
    try {
      const json = await fetchJson(`../api/get_availability.php?${params.toString()}`, { cache: 'no-store' });
      if (!json.success) throw new Error(json.error || 'Availability API error');
      return (json.data || []).map(r => Number(r.id));
    } catch (err) {
      console.warn('fetchAvailability failed', err);
      return null;
    }
  }

  async function updateAvailabilityIfNeeded() {
    const seats = state.partySeats === 'any' ? 1 : Number(state.partySeats);
    if (state.filter === 'party' || (state.date && state.time)) {
      const ids = await fetchAvailability({ seats, date: state.date, time: state.time, duration: state.duration });
      state.availableIds = Array.isArray(ids) ? ids : null;
    } else {
      state.availableIds = null;
    }
    applyAvailabilityHighlights();
  }

  function applyAvailabilityHighlights() {
    const cards = document.querySelectorAll('.table-card');
    if (!state.availableIds) { cards.forEach(c => { c.classList.remove('available','unavailable'); }); return; }
    const set = new Set(state.availableIds.map(id => Number(id)));
    cards.forEach(c => { const id = Number(c.dataset.id); if (set.has(id)) { c.classList.add('available'); c.classList.remove('unavailable'); } else { c.classList.add('unavailable'); c.classList.remove('available'); } });
  }

  /* --------------------------
     PARTY helpers (bucket mapping, compute matches)
     -------------------------- */
  function bucketRange(bucketVal) {
    if (bucketVal === 2) return [1,2];
    if (bucketVal === 4) return [3,4];
    if (bucketVal === 6) return [5,6];
    return [7, 999];
  }

  function computePartyMatches(tables, bucket) {
    if (!bucket || bucket === 'any') return { exact: tables.slice(), larger: [] };
    const v = Number(bucket); const [min,max] = bucketRange(v);
    const exact = [], larger = [];
    tables.forEach(t => { if (t.seats >= min && t.seats <= max) exact.push(t); else if (t.seats > max) larger.push(t); });
    exact.sort((a,b) => a.seats-b.seats || a.id-b.id); larger.sort((a,b) => a.seats-b.seats || a.id-b.id);
    return { exact, larger };
  }

  function filterBySearchAndParty(tables = []) {
    const q = (state.search || '').trim().toLowerCase(); let out = tables.slice();
    if (q) out = out.filter(t => (t.name + ' ' + (t.guest || '')).toLowerCase().includes(q));
    if (state.filter === 'party' && state.partySeats !== 'any') {
      const { exact, larger } = computePartyMatches(out, state.partySeats);
      return state.allowLarger ? exact.concat(larger) : exact;
    }
    return out;
  }

  /* --------------------------
     RENDER: cards and views
     -------------------------- */
  function renderCard(table, opts = {}) {
    const statusColor = table.status === 'available' ? '#00b256' : table.status === 'reserved' ? '#ffd400' : '#d20000';
    const card = document.createElement('div'); card.className = 'table-card' + (opts.light ? ' light' : ''); card.tabIndex = 0; card.dataset.id = table.id;
    if (state.selectedId && Number(state.selectedId) === Number(table.id)) card.classList.add('active');
    card.innerHTML = `
      <div class="title">${escapeHtml(table.name)}</div>
      <div class="status-row"><span class="status-dot" style="background:${statusColor}"></span><span class="status-label">${escapeHtml(capitalize(table.status))}</span></div>
      <div class="seats-row"><span style="font-size:18px">👥</span><div>${escapeHtml(String(table.seats))} Seats</div></div>
      ${table.guest ? `<div class="guest">${escapeHtml(table.guest)}</div>` : ''}
      <div class="card-actions" aria-hidden="false">
        <button class="icon-btn edit-btn" aria-label="Edit table" title="Edit">✎</button>
        <button class="icon-btn toggle-btn" aria-label="Toggle status" title="Toggle">⟳</button>
        <button class="icon-btn clear-btn" aria-label="Clear table" title="Clear">🗑</button>
      </div>
    `;
    card.addEventListener('click', () => setSelected(table.id));
    card.addEventListener('keydown', (ev) => { if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); setSelected(table.id); }});
    card.querySelector('.edit-btn')?.addEventListener('click', (e) => { e.stopPropagation(); openEditModal(table); });
    card.querySelector('.toggle-btn')?.addEventListener('click', (e) => { e.stopPropagation(); quickToggleStatus(table); });
    card.querySelector('.clear-btn')?.addEventListener('click', (e) => { e.stopPropagation(); confirmClear(table); });
    return card;
  }

  function renderCardsInto(container, data = [], opts = {}) {
    container.innerHTML = ''; if (!data || data.length === 0) { container.innerHTML = '<div style="padding:18px; font-weight:700">No tables found</div>'; return; }
    const frag = document.createDocumentFragment(); data.forEach(t => frag.appendChild(renderCard(t, opts))); container.appendChild(frag); applyAvailabilityHighlights();
  }

  function renderAllView() {
    refs.viewHeader.innerHTML = '<h1>All Tables</h1>'; refs.viewContent.innerHTML = `<div class="cards-grid" id="${CARDS_GRID_ID}" role="list"></div>`; refs.partyControl?.setAttribute('aria-hidden','true'); refs.dateInput?.setAttribute('aria-hidden','true'); refs.timeInput?.setAttribute('aria-hidden','true');
    renderCardsInto(getCardsGrid(), filterBySearchAndParty(state.tables), { light: false });
  }

  function renderPartyView() {
    const bucketText = refs.partySelect && refs.partySelect.value !== 'any' ? refs.partySelect.options[refs.partySelect.selectedIndex].text + ' Persons' : 'Any'; const allowText = state.allowLarger ? ' (allowing larger tables)' : '';
    refs.viewHeader.innerHTML = `<h1>Party Size</h1><div class="view-subtitle">Party Size: <strong>${escapeHtml(bucketText)}</strong>${allowText}</div>`;
    refs.viewContent.innerHTML = `<div class="cards-grid" id="${CARDS_GRID_ID}" role="list"></div>`; refs.partyControl?.setAttribute('aria-hidden','false'); refs.dateInput?.setAttribute('aria-hidden','true'); refs.timeInput?.setAttribute('aria-hidden','true');
    const { exact, larger } = computePartyMatches(state.tables, state.partySeats);
    refs.viewHeader.innerHTML += `<div class="party-counts"><span class="exact-count">Exact: <strong>${exact.length}</strong></span> <span class="larger-count">Larger: <strong>${larger.length}</strong></span></div>`;
    // banner when no exact but larger exist and allowLarger not enabled
    if ((!exact || exact.length === 0) && larger.length > 0 && !state.allowLarger && state.partySeats !== 'any') {
      const banner = createElementFromHTML(`<div class="no-matches-banner" role="status" aria-live="polite">No exact matches. <button class="show-larger-btn">Show larger tables</button></div>`);
      refs.viewHeader.appendChild(banner);
      banner.querySelector('.show-larger-btn')?.addEventListener('click', () => { setAllowLarger(true); renderView(); showToast('Showing larger tables'); });
    }
    const toRender = state.partySeats === 'any' ? state.tables.slice() : (state.allowLarger ? exact.concat(larger) : exact);
    renderCardsInto(getCardsGrid(), toRender, { light: true });
  }

  function renderDateView() {
    refs.viewHeader.innerHTML = '<h1>Date</h1>'; refs.viewContent.innerHTML = `<div class="date-layout"><div class="calendar" aria-hidden="true"><div class="calendar-box">Calendar<br><small>(placeholder)</small></div></div><div class="side-cards" id="sideCards"></div></div>`;
    refs.partyControl?.setAttribute('aria-hidden','true'); refs.dateInput?.setAttribute('aria-hidden','false'); refs.timeInput?.setAttribute('aria-hidden','true');
    const reservations = state.tables.filter(t => t.status !== 'available').slice(0,3); renderCardsInto(document.getElementById('sideCards'), reservations, { light: false });
  }

  function renderTimeView() {
    refs.viewHeader.innerHTML = '<h1>Time</h1>'; refs.viewContent.innerHTML = `<div class="date-layout"><div class="calendar" aria-hidden="true"><div class="calendar-box">Calendar<br><small>(placeholder)</small></div></div><div class="time-container"><div class="time-grid" id="timeGrid"></div></div></div>`;
    refs.partyControl?.setAttribute('aria-hidden','true'); refs.dateInput?.setAttribute('aria-hidden','true'); refs.timeInput?.setAttribute('aria-hidden','false');
    const times = ['4:00 PM','6:00 PM','7:00 PM','9:00 PM','12:00 PM','1:00 PM']; const timeGrid = document.getElementById('timeGrid'); timeGrid.innerHTML = '';
    times.forEach(t => { const btn = document.createElement('button'); btn.className = 'time-slot'; btn.textContent = t; btn.addEventListener('click', () => { document.querySelectorAll('.time-slot').forEach(b => b.classList.remove('selected')); btn.classList.add('selected'); }); timeGrid.appendChild(btn); });
  }

  function renderView() { switch (state.filter) { case 'party': renderPartyView(); break; case 'date': renderDateView(); break; case 'time': renderTimeView(); break; default: renderAllView(); } updateAvailabilityIfNeeded(); }

  /* --------------------------
     Reservation modal + create flow
     -------------------------- */
  // Open a reservation creation modal (used by "New" buttons)
  // Modal allows: party size, date, time, duration, guest, optional choose table (auto-assigned if none)
  async function openReservationModal() {
    // Build modal HTML
    const overlay = createElementFromHTML('<div class="modal-overlay"></div>');
    const modalHtml = `
      <div class="modal" role="dialog" aria-modal="true" aria-label="Create Reservation">
        <h3>Create Reservation</h3>
        <div class="form-row"><label for="resParty">Party Size</label><input id="resParty" type="number" min="1" value="${state.partySeats === 'any' ? 2 : state.partySeats}"></div>
        <div class="form-row"><label for="resDate">Date</label><input id="resDate" type="date" value="${state.date || ''}"></div>
        <div class="form-row"><label for="resTime">Time</label><input id="resTime" type="time" value="${state.time || ''}"></div>
        <div class="form-row"><label for="resDuration">Duration (minutes)</label><input id="resDuration" type="number" min="15" step="15" value="${state.duration}"></div>
        <div class="form-row"><label for="resGuest">Guest Name</label><input id="resGuest" type="text" placeholder="Guest name"></div>
        <div class="form-row"><label for="resTable">Prefer table (optional)</label>
          <select id="resTable"><option value="">Auto assign best fit</option></select>
        </div>
        <div class="modal-actions"><button id="resCancel" class="btn">Cancel</button><button id="resSave" class="btn primary">Create Reservation</button></div>
      </div>`;
    const modal = createElementFromHTML(modalHtml);
    overlay.appendChild(modal);
    document.body.appendChild(overlay);

    // Prefill available tables for the selected party/date/time (best-effort)
    const resTable = modal.querySelector('#resTable');
    const resParty = modal.querySelector('#resParty');
    const resDate = modal.querySelector('#resDate');
    const resTime = modal.querySelector('#resTime');
    const resDuration = modal.querySelector('#resDuration');
    const resGuest = modal.querySelector('#resGuest');

    // Populate table dropdown using current state.tables filtered by seats >= party
    function populateTableOptions(partySize) {
      // clear options
      resTable.innerHTML = '<option value="">Auto assign best fit</option>';
      const candidates = state.tables.filter(t => t.seats >= partySize).sort((a,b) => a.seats - b.seats);
      candidates.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = `${c.name} — ${c.seats} seats (${c.status})`;
        resTable.appendChild(opt);
      });
    }
    populateTableOptions(Number(resParty.value || 2));

    // If user changes party size update the dropdown
    resParty.addEventListener('input', () => populateTableOptions(Number(resParty.value || 1)));

    // Attempt to show only available tables if date/time provided (call availability)
    async function refreshAvailableOptions() {
      const party = Number(resParty.value || 1);
      const date = resDate.value;
      const time = resTime.value;
      const duration = Number(resDuration.value || state.duration);
      if (date && time) {
        const availIds = await fetchAvailability({ seats: party, date, time, duration });
        if (Array.isArray(availIds)) {
          // mark options not available
          Array.from(resTable.options).forEach(opt => {
            if (!opt.value) return;
            opt.disabled = !availIds.includes(Number(opt.value));
            opt.style.opacity = opt.disabled ? '0.5' : '';
          });
        }
      }
    }

    // wire refresh when date/time/party/duration change
    [resParty, resDate, resTime, resDuration].forEach(el => el.addEventListener('change', refreshAvailableOptions));

    // Cancel
    modal.querySelector('#resCancel').addEventListener('click', () => overlay.remove());

    // Save: call create_reservation.php
    modal.querySelector('#resSave').addEventListener('click', async () => {
      const party = Number(resParty.value || 1);
      const date = resDate.value;
      const time = resTime.value;
      const duration = Number(resDuration.value || state.duration);
      const guest = resGuest.value.trim();
      const tableId = resTable.value ? Number(resTable.value) : null;

      if (!date || !time) {
        alert('Please select date and time for the reservation.');
        return;
      }

      const start = `${date} ${time}:00`;
      const payload = {
        table_id: tableId,
        start,
        duration,
        party_size: party,
        guest
      };

      try {
        // Call create endpoint
        const resp = await fetchJson(API_CREATE_RESERVATION, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        if (!resp.success) throw new Error(resp.error || 'Create failed');
        // Success: reload data and availability
        await loadTables();
        await updateAvailabilityIfNeeded();
        overlay.remove();
        showToast(`Reservation created (table ${resp.table_id || 'auto'})`);
      } catch (err) {
        alert('Failed to create reservation: ' + (err.message || err));
        console.error(err);
      }
    });

    overlay.addEventListener('click', (ev) => { if (ev.target === overlay) overlay.remove(); });

    // Focus first field
    setTimeout(() => resParty.focus(), 50);

    // initial availability marking of options
    refreshAvailableOptions();
  }

  /* --------------------------
     OTHER ACTIONS (quick toggle/clear/create table)
     -------------------------- */
  function setSelected(id) { state.selectedId = id; document.querySelectorAll('.table-card').forEach(c => c.classList.toggle('active', c.dataset.id == id)); }
  function updateLocalTable(id, updates) { const idx = state.tables.findIndex(t => Number(t.id) === Number(id)); if (idx === -1) return false; state.tables[idx] = { ...state.tables[idx], ...updates }; return true; }
  async function quickToggleStatus(table) { const next = table.status === 'available' ? 'reserved' : table.status === 'reserved' ? 'occupied' : 'available'; const prev = { ...table }; updateLocalTable(table.id, { status: next }); renderView(); try { if (API_UPDATE) { await sendUpdate({ id: table.id, status: next }); await loadTables(); } showToast('Status updated'); } catch (err) { updateLocalTable(table.id, prev); renderView(); showToast('Failed to update status'); console.warn(err); } }
  async function confirmClear(table) { if (!confirm(`Clear ${table.name}?`)) return; const prev = { ...table }; updateLocalTable(table.id, { status: 'available', guest: '' }); renderView(); try { if (API_UPDATE) { await sendUpdate({ id: table.id, status: 'available', guest: '' }); await loadTables(); } showToast('Table cleared'); } catch (err) { updateLocalTable(table.id, prev); renderView(); showToast('Failed to clear table'); console.warn(err); } }

  /* --------------------------
     RENDER & MISC (re-using prior code)
     -------------------------- */
  function renderCard(table, opts = {}) {
    const statusColor = table.status === 'available' ? '#00b256' : table.status === 'reserved' ? '#ffd400' : '#d20000';
    const card = document.createElement('div'); card.className = 'table-card' + (opts.light ? ' light' : ''); card.tabIndex = 0; card.dataset.id = table.id;
    if (state.selectedId && Number(state.selectedId) === Number(table.id)) card.classList.add('active');
    card.innerHTML = `<div class="title">${escapeHtml(table.name)}</div><div class="status-row"><span class="status-dot" style="background:${statusColor}"></span><span class="status-label">${escapeHtml(capitalize(table.status))}</span></div><div class="seats-row"><span style="font-size:18px">👥</span><div>${escapeHtml(String(table.seats))} Seats</div></div>${table.guest ? `<div class="guest">${escapeHtml(table.guest)}</div>` : ''}<div class="card-actions" aria-hidden="false"><button class="icon-btn edit-btn" aria-label="Edit table" title="Edit">✎</button><button class="icon-btn toggle-btn" aria-label="Toggle status" title="Toggle">⟳</button><button class="icon-btn clear-btn" aria-label="Clear table" title="Clear">🗑</button></div>`;
    card.addEventListener('click', () => setSelected(table.id));
    card.addEventListener('keydown', (ev) => { if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); setSelected(table.id); }});
    card.querySelector('.edit-btn')?.addEventListener('click', (e) => { e.stopPropagation(); openEditModal(table); });
    card.querySelector('.toggle-btn')?.addEventListener('click', (e) => { e.stopPropagation(); quickToggleStatus(table); });
    card.querySelector('.clear-btn')?.addEventListener('click', (e) => { e.stopPropagation(); confirmClear(table); });
    return card;
  }

  function renderCardsInto(container, data = [], opts = {}) {
    container.innerHTML = ''; if (!data || data.length === 0) { container.innerHTML = '<div style="padding:18px; font-weight:700">No tables found</div>'; return; }
    const frag = document.createDocumentFragment(); data.forEach(t => frag.appendChild(renderCard(t, opts))); container.appendChild(frag); applyAvailabilityHighlights();
  }

  function renderAllView() { refs.viewHeader.innerHTML = '<h1>All Tables</h1>'; refs.viewContent.innerHTML = `<div class="cards-grid" id="${CARDS_GRID_ID}" role="list"></div>`; refs.partyControl?.setAttribute('aria-hidden','true'); refs.dateInput?.setAttribute('aria-hidden','true'); refs.timeInput?.setAttribute('aria-hidden','true'); renderCardsInto(getCardsGrid(), filterBySearchAndParty(state.tables), { light: false }); }
  function renderPartyView() { const bucketText = refs.partySelect && refs.partySelect.value !== 'any' ? refs.partySelect.options[refs.partySelect.selectedIndex].text + ' Persons' : 'Any'; const allowText = state.allowLarger ? ' (allowing larger tables)' : ''; refs.viewHeader.innerHTML = `<h1>Party Size</h1><div class="view-subtitle">Party Size: <strong>${escapeHtml(bucketText)}</strong>${allowText}</div>`; refs.viewContent.innerHTML = `<div class="cards-grid" id="${CARDS_GRID_ID}" role="list"></div>`; refs.partyControl?.setAttribute('aria-hidden','false'); refs.dateInput?.setAttribute('aria-hidden','true'); refs.timeInput?.setAttribute('aria-hidden','true'); const { exact, larger } = computePartyMatches(state.tables, state.partySeats); refs.viewHeader.innerHTML += `<div class="party-counts"><span class="exact-count">Exact: <strong>${exact.length}</strong></span> <span class="larger-count">Larger: <strong>${larger.length}</strong></span></div>`; if ((!exact || exact.length === 0) && larger.length > 0 && !state.allowLarger && state.partySeats !== 'any') { const banner = createElementFromHTML(`<div class="no-matches-banner" role="status" aria-live="polite">No exact matches. <button class="show-larger-btn">Show larger tables</button></div>`); refs.viewHeader.appendChild(banner); banner.querySelector('.show-larger-btn')?.addEventListener('click', () => { setAllowLarger(true); renderView(); showToast('Showing larger tables'); }); } const toRender = state.partySeats === 'any' ? state.tables.slice() : (state.allowLarger ? exact.concat(larger) : exact); renderCardsInto(getCardsGrid(), toRender, { light: true }); }
  function renderDateView() { refs.viewHeader.innerHTML = '<h1>Date</h1>'; refs.viewContent.innerHTML = `<div class="date-layout"><div class="calendar" aria-hidden="true"><div class="calendar-box">Calendar<br><small>(placeholder)</small></div></div><div class="side-cards" id="sideCards"></div></div>`; refs.partyControl?.setAttribute('aria-hidden','true'); refs.dateInput?.setAttribute('aria-hidden','false'); refs.timeInput?.setAttribute('aria-hidden','true'); const reservations = state.tables.filter(t => t.status !== 'available').slice(0,3); renderCardsInto(document.getElementById('sideCards'), reservations, { light: false }); }
  function renderTimeView() { refs.viewHeader.innerHTML = '<h1>Time</h1>'; refs.viewContent.innerHTML = `<div class="date-layout"><div class="calendar" aria-hidden="true"><div class="calendar-box">Calendar<br><small>(placeholder)</small></div></div><div class="time-container"><div class="time-grid" id="timeGrid"></div></div></div>`; refs.partyControl?.setAttribute('aria-hidden','true'); refs.dateInput?.setAttribute('aria-hidden','true'); refs.timeInput?.setAttribute('aria-hidden','false'); const times = ['4:00 PM','6:00 PM','7:00 PM','9:00 PM','12:00 PM','1:00 PM']; const timeGrid = document.getElementById('timeGrid'); timeGrid.innerHTML = ''; times.forEach(t => { const btn = document.createElement('button'); btn.className = 'time-slot'; btn.textContent = t; btn.addEventListener('click', () => { document.querySelectorAll('.time-slot').forEach(b => b.classList.remove('selected')); btn.classList.add('selected'); }); timeGrid.appendChild(btn); }); }
  function renderView() { switch (state.filter) { case 'party': renderPartyView(); break; case 'date': renderDateView(); break; case 'time': renderTimeView(); break; default: renderAllView(); } updateAvailabilityIfNeeded(); }

  /* --------------------------
     EVENT WIRING
     -------------------------- */
  refs.searchInput?.addEventListener('input', (e) => { state.search = e.target.value; renderView(); });
  refs.searchClear?.addEventListener('click', () => { if (refs.searchInput) refs.searchInput.value = ''; state.search = ''; renderView(); });

  refs.filterButtons.forEach(btn => { btn.addEventListener('click', () => { refs.filterButtons.forEach(b => b.classList.remove('active')); btn.classList.add('active'); state.filter = btn.dataset.filter || 'all'; refs.partyControl?.classList.toggle('visible', state.filter === 'party'); renderView(); }); });

  refs.partySelect?.addEventListener('change', (e) => { state.partySeats = e.target.value; renderView(); updateAvailabilityIfNeeded(); });
  state.partySeats = refs.partySelect?.value || 'any';
  refs.allowLargerCheckbox?.addEventListener('change', (e) => { state.allowLarger = e.target.checked; localStorage.setItem(LOCALSTORAGE_ALLOW_LARGER, state.allowLarger ? '1' : '0'); renderView(); });

  refs.partyBuckets?.forEach(btn => { btn.addEventListener('click', () => { refs.partyBuckets.forEach(b => b.classList.remove('active')); btn.classList.add('active'); const seats = btn.dataset.seats || 'any'; if (refs.partySelect) refs.partySelect.value = seats; state.partySeats = seats; state.filter = 'party'; refs.filterButtons.forEach(b => b.classList.remove('active')); document.getElementById('filterParty')?.classList.add('active'); renderView(); updateAvailabilityIfNeeded(); }); });

  refs.dateInput?.addEventListener('change', (e) => { state.date = e.target.value; updateAvailabilityIfNeeded(); renderView(); });
  refs.timeInput?.addEventListener('change', (e) => { state.time = e.target.value; updateAvailabilityIfNeeded(); renderView(); });

  refs.btnAddReservation?.addEventListener('click', (e) => { e.preventDefault(); openReservationModal(); });
  refs.fabNew?.addEventListener('click', (e) => { e.preventDefault(); openReservationModal(); });

  /* --------------------------
     BOOT
     -------------------------- */
  loadTables();

  /* expose for debugging */
  window._tablesApp = { state, loadTables, renderView, openReservationModal };
});