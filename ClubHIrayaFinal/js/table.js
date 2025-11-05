// Improved All/Party/Date Table Logic with Sorting and Guest field in modal
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

  // State
  const state = {
    filter: 'all',
    search: '',
    partySeats: 'any',
    partySort: 'asc',
    date: '',
    selectedId: null,
  };

  function capitalize(s) { return s && s.length ? s[0].toUpperCase() + s.slice(1) : ''; }
  function escapeHtml(text = '') {
    return String(text).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]);
  }

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
      card.innerHTML = `
        <div class="title">${escapeHtml(tbl.name)}</div>
        <div class="status-row">
          <span class="status-dot" style="background:${statusDotColor}"></span>
          <span class="status-label">${capitalize(status)}</span>
        </div>
        <div class="seats-row"><span>👥</span> ${escapeHtml(String(tbl.seats))} Seats</div>
        ${tbl.guest ? `<div class="guest">${escapeHtml(tbl.guest)}</div>` : ''}
        <div class="card-actions" aria-hidden="false">
          <button class="icon-btn edit-btn" aria-label="Edit table" title="Edit">✎</button>
          <button class="icon-btn delete-btn" aria-label="Delete table" title="Delete">✖</button>
        </div>
      `;
      card.addEventListener('click', () => setSelected(tbl.id));
      card.addEventListener('keydown', ev => {
        if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); setSelected(tbl.id); }
      });
      const editBtn = card.querySelector('.edit-btn');
      const deleteBtn = card.querySelector('.delete-btn');
      if (editBtn) editBtn.addEventListener('click', e => { e.stopPropagation(); openEditModal(tbl); });
      if (deleteBtn) deleteBtn.addEventListener('click', e => { e.stopPropagation(); confirmDelete(tbl); });
      container.appendChild(card);
    });
  }

  function setSelected(id) {
    state.selectedId = id;
    document.querySelectorAll('.table-card').forEach(c => c.classList.toggle('active', c.dataset.id == id));
    const selected = tablesData.find(t => t.id === Number(id));
    if (selected) console.info('Selected table:', selected.name);
  }

  function renderAllView() {
    viewHeader.innerHTML = '<h1>All Tables</h1>';
    viewContent.innerHTML = `<div class="cards-grid" id="cardsGrid" role="list"></div>`;
    cardsGrid = document.getElementById('cardsGrid');
    partyControl && partyControl.setAttribute('aria-hidden', 'true');
    partySortControl && partySortControl.setAttribute('aria-hidden', 'true'); // <-- HIDE sort control in All
    // Search filter
    const s = state.search.trim().toLowerCase();
    let filtered = tablesData;
    if (s) filtered = tablesData.filter(t => t.name.toLowerCase().includes(s));
    renderCardsInto(cardsGrid, filtered);
  }

  function renderPartyView() {
    viewHeader.innerHTML = '<h1>Party Size</h1>';
    partyControl && partyControl.setAttribute('aria-hidden', 'false');
    partySortControl && partySortControl.setAttribute('aria-hidden', 'false'); // <-- SHOW sort control in Party
    viewContent.innerHTML = `<div class="cards-grid" id="cardsGrid" role="list"></div>`;
    cardsGrid = document.getElementById('cardsGrid');
    let filtered = tablesData;
    if (state.partySeats !== 'any') {
      const v = Number(state.partySeats);
      const [min, max] = v === 2 ? [1, 2] : v === 4 ? [3, 4] : v === 6 ? [5, 6] : [7, 8];
      filtered = tablesData.filter(t => t.seats >= min && t.seats <= max);
    }
    // Sort by seats
    if (state.partySort === 'asc') {
      filtered = filtered.slice().sort((a, b) => a.seats - b.seats);
    } else {
      filtered = filtered.slice().sort((a, b) => b.seats - a.seats);
    }
    renderCardsInto(cardsGrid, filtered);
  }

  function renderDateView() {
    viewHeader.innerHTML = '<h1>Date</h1>';
    partyControl && partyControl.setAttribute('aria-hidden', 'true');
    partySortControl && partySortControl.setAttribute('aria-hidden', 'true'); // <-- HIDE sort control in Date
    viewContent.innerHTML = `
      <div style="margin-bottom:10px">
        <input type="date" id="viewDatePicker" value="${state.date || ''}" aria-label="Pick a date">
      </div>
      <div id="tableStatusGrid" class="cards-grid"></div>
    `;
    const datePicker = document.getElementById('viewDatePicker');
    if (datePicker) {
      datePicker.addEventListener('change', e => {
        state.date = e.target.value;
        loadTableStatusForDate(state.date);
      });
      if (state.date) loadTableStatusForDate(state.date);
    }
  }

  async function loadTableStatusForDate(date) {
    const grid = document.getElementById('tableStatusGrid');
    grid.innerHTML = 'Loading...';
    try {
      const res = await fetch(`${API_GET_STATUS_BY_DATE}?date=${encodeURIComponent(date)}`);
      const json = await res.json();
      if (!json.success) throw new Error(json.error || 'API failed');
      const tables = json.data;
      ['available', 'reserved', 'occupied'].forEach(status => {
        const list = tables.filter(t => t.status === status);
        if (!list.length) return;
        const header = document.createElement('div');
        header.className = 'table-status-header';
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
          `;
          grid.appendChild(card);
        });
      });
    } catch (err) {
      grid.innerHTML = `<div style="color:#900">Error loading tables for date: ${err.message}</div>`;
    }
  }

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

  async function confirmDelete(table) {
    if (!confirm(`Delete ${table.name}? This action cannot be undone.`)) return;
    try {
      const res = await fetch(API_DELETE, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
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

  // Wire up filter buttons
  if (filterButtons && filterButtons.length) {
    filterButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        filterButtons.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        state.filter = btn.dataset.filter;
        renderView();
      });
    });
  }
  // Party size select restore
  if (partySelect) {
    partySelect.addEventListener('change', e => { state.partySeats = e.target.value; renderView(); });
    state.partySeats = partySelect.value || 'any';
  }
  // Party sort select event
  if (partySortSelect) {
    partySortSelect.addEventListener('change', e => { state.partySort = e.target.value; renderView(); });
    state.partySort = partySortSelect.value || 'asc';
  }

  // Search box
  if (searchInput) {
    searchInput.addEventListener('input', e => { state.search = e.target.value; renderView(); });
  }
  if (searchClear) {
    searchClear.addEventListener('click', () => { if (searchInput) searchInput.value = ''; state.search = ''; renderView(); });
  }

  // Add buttons
  document.getElementById('btnAddReservation')?.addEventListener('click', e => {
    e.preventDefault(); openNewTableModal();
  });
  document.getElementById('fabNew')?.addEventListener('click', e => {
    e.preventDefault(); openNewTableModal();
  });

  // Modal CSS injection
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

  // Main view router
  function renderView() {
    switch (state.filter) {
      case 'party': renderPartyView(); break;
      case 'date': renderDateView(); break;
      default: renderAllView();
    }
  }

  // Initial load
  loadTables();
  window._tablesApp = { data: tablesData, state, renderView, renderCardsInto, openEditModal, loadTables };
});