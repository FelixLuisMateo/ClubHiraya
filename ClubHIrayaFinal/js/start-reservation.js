// Attach Start buttons for existing reservations and call api/start_reservation.php
// Load this after table.js so helpers like showToast, formatCurrencyPhp, and state/load functions exist.

(() => {
  const API_START_RESERVATION = '../api/start_reservation.php';

  // Helper: attach a Start button to reservation cards in the current #tableStatusGrid
  function attachStartButtons() {
    const grid = document.getElementById('tableStatusGrid');
    if (!grid) return;
    grid.querySelectorAll('.table-card').forEach(card => {
      try {
        // don't add twice
        if (card.querySelector('.start-res-btn')) return;

        // find reservation id from time-monitor (rendered by table.js)
        const monitor = card.querySelector('.time-monitor');
        const reservationId = monitor && (monitor.dataset.reservationId || monitor.dataset.resId) ? (monitor.dataset.reservationId || monitor.dataset.resId) : null;

        // check status label
        const statusLabel = (card.querySelector('.status-label')?.textContent || '').trim().toLowerCase();

        if (reservationId && statusLabel === 'reserved') {
          const actions = card.querySelector('.card-actions');
          if (!actions) return;
          const btn = document.createElement('button');
          btn.className = 'icon-btn start-res-btn';
          btn.type = 'button';
          btn.title = 'Start reservation now';
          btn.setAttribute('aria-label', 'Start reservation now');
          btn.textContent = '▶';
          btn.dataset.reservationId = reservationId;
          // Prefer table id from card dataset if set
          if (card.dataset.id) btn.dataset.tableId = card.dataset.id;
          actions.insertBefore(btn, actions.firstChild);
        }
      } catch (e) {
        console.error('attachStartButtons error', e, card);
      }
    });
  }

  // Delegated click listener for start buttons
  document.addEventListener('click', async (ev) => {
    const el = ev.target.closest && ev.target.closest('.start-res-btn');
    if (!el) return;
    ev.preventDefault();
    ev.stopPropagation();

    const resId = el.dataset.reservationId;
    if (!resId) return alert('Reservation id missing');

    if (!confirm('Start this reservation now and mark the cabin occupied?')) return;

    try {
      const r = await fetch(API_START_RESERVATION, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: Number(resId) })
      });
      const j = await r.json().catch(e => { throw new Error('Invalid JSON response from start_reservation.php: ' + e.message); });
      if (!j.success) throw new Error(j.error || 'Failed to start reservation');

      // show toast including total price if available
      const msg = j.total_price ? `Started • ${formatCurrencyPhp(j.total_price)}` : 'Reservation started';
      try { showToast(msg, { background: '#2b8cff' }); } catch (e) { console.info(msg); }

      // refresh whichever view is active (use same logic table.js uses)
      try {
        if (window._tablesApp && window._tablesApp.state) {
          const state = window._tablesApp.state;
          if (state.filter === 'date') {
            if (state.time) loadTableStatusForDateTime(state.date, state.time);
            else loadTableStatusForDate(state.date);
          } else if (state.filter === 'time') {
            loadTableStatusForDateTime(state.date, state.time);
          } else {
            // default: reload all tables
            loadTables();
          }
        } else {
          // fallback: reload page
          window.location.reload();
        }
      } catch (e) {
        console.error('Error refreshing after start', e);
        window.location.reload();
      }
    } catch (err) {
      console.error('start reservation failed', err);
      alert('Failed to start reservation: ' + (err && err.message ? err.message : err));
    }
  });

  // Expose so other code can call after rendering
  window.attachStartButtons = attachStartButtons;

  // If table.js already finished rendering, attempt to attach now on DOMContentLoaded
  document.addEventListener('DOMContentLoaded', () => {
    // small delay so table.js render has finished
    setTimeout(() => {
      try { attachStartButtons(); } catch (e) {}
    }, 120);
  });

  // Also observe #tableStatusGrid for changes and attach when nodes appear
  const observer = new MutationObserver((list) => {
    for (const m of list) {
      if (m.addedNodes && m.addedNodes.length) {
        try { attachStartButtons(); } catch (e) {}
        break;
      }
    }
  });
  const grid = document.getElementById('tableStatusGrid');
  if (grid) observer.observe(grid, { childList: true, subtree: true });

})();