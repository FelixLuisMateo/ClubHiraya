// ../js/calendar.js
// Inline calendar + timeslots controller for tables.php
// - Renders an inline flatpickr calendar
// - Loads reservations for selected date (api/list_reservations.php)
// - Renders timeslots; clicking a timeslot fetches availability (api/get_availability.php)
// - Clicking "Reserve" on an available table pre-fills the modal (date/time/party/table_id) and opens it.
// - Only shows the calendar panel when Date or Time filter is active (prevents overlap)

(function () {
  const inlineCalendarEl = document.getElementById('inlineCalendar');
  const selectedDateHeader = document.getElementById('selectedDateHeader');
  const reservationsList = document.getElementById('reservationsList');
  const timesGrid = document.getElementById('timesGrid');
  const availabilityList = document.getElementById('availabilityList');
  const dateTimePanel = document.querySelector('.date-time-panel');

  // Filter buttons
  const filterDateBtn = document.getElementById('filterDate');
  const filterTimeBtn = document.getElementById('filterTime');
  const filterAllBtn = document.getElementById('filterAll'); // used to hide panel again

  // Common timeslots (customize as needed)
  const TIMES = [
    '10:00','11:00','12:00','13:00','14:00',
    '15:00','16:00','17:00','18:00','19:00',
    '20:00','21:00','22:00'
  ];

  let selectedDate = null;
  let selectedTimeBtn = null;

  // hide date-time panel initially (so calendar won't always show)
  if (dateTimePanel) dateTimePanel.style.display = 'none';

  function renderTimes() {
    if (!timesGrid) return;
    timesGrid.innerHTML = '';
    TIMES.forEach(t => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'time-slot-btn';
      btn.textContent = t;
      btn.dataset.time = t;
      btn.addEventListener('click', () => {
        if (!selectedDate) return;
        // mark selected
        if (selectedTimeBtn) selectedTimeBtn.classList.remove('selected');
        btn.classList.add('selected');
        selectedTimeBtn = btn;
        fetchAvailability(selectedDate, t);
      });
      timesGrid.appendChild(btn);
    });
  }

  // Show/hide panel when user selects filters
  function showDatePanel() {
    if (!dateTimePanel) return;
    dateTimePanel.style.display = 'flex';
  }
  function hideDatePanel() {
    if (!dateTimePanel) return;
    dateTimePanel.style.display = 'none';
  }

  // Initialize filter button wiring (simple toggles)
  function initFilterButtons() {
    if (filterDateBtn) {
      filterDateBtn.addEventListener('click', () => {
        showDatePanel();
      });
    }
    if (filterTimeBtn) {
      filterTimeBtn.addEventListener('click', () => {
        showDatePanel();
      });
    }
    if (filterAllBtn) {
      filterAllBtn.addEventListener('click', () => {
        hideDatePanel();
      });
    }
    // If you have other filter buttons, you can add hideDatePanel on their click as needed.
  }

  // initialize inline calendar
  function initCalendar() {
    if (!inlineCalendarEl || !window.flatpickr) return;
    // create inline calendar inside the inlineCalendar element
    flatpickr(inlineCalendarEl, {
      inline: true,
      dateFormat: 'Y-m-d',
      defaultDate: new Date(),
      minDate: 'today',
      onChange: function (selectedDates, dateStr) {
        selectedDate = dateStr;
        if (selectedDateHeader) selectedDateHeader.textContent = `Date: ${dateStr}`;
        // reset selections
        if (selectedTimeBtn) selectedTimeBtn.classList.remove('selected');
        selectedTimeBtn = null;
        if (availabilityList) availabilityList.innerHTML = 'Pick a time to see availability';
        // load reservations for the date
        fetchReservations(dateStr);
      }
    });

    // Set initial date and render times (but panel is hidden by default)
    const todayStr = (new Date()).toISOString().slice(0,10);
    selectedDate = todayStr;
    if (selectedDateHeader) selectedDateHeader.textContent = `Date: ${todayStr}`;
    renderTimes();
    fetchReservations(todayStr);
  }

  // Helper to parse JSON but show raw error text on failure
  async function fetchJsonOrText(url) {
    const res = await fetch(url);
    const text = await res.text();
    try {
      const json = JSON.parse(text);
      return { ok: res.ok, json, text };
    } catch (e) {
      return { ok: res.ok, json: null, text };
    }
  }

  // fetch reservations for a date
  async function fetchReservations(date) {
    if (!reservationsList) return;
    reservationsList.innerHTML = 'Loading...';
    try {
      const result = await fetchJsonOrText(`../api/list_reservations.php?date=${encodeURIComponent(date)}`);
      if (!result.ok) {
        reservationsList.innerHTML = `<div class="res-item">Error loading reservations: ${result.text}</div>`;
        return;
      }
      if (!result.json || !result.json.success) {
        // show server response text or JSON error message
        const err = result.json && result.json.error ? result.json.error : result.text;
        reservationsList.innerHTML = `<div class="res-item">Network error: ${escapeHtml(err)}</div>`;
        return;
      }
      const rows = result.json.data;
      if (!rows || rows.length === 0) {
        reservationsList.innerHTML = '<div class="res-item">No reservations for this date.</div>';
        return;
      }
      reservationsList.innerHTML = '';
      rows.forEach(r => {
        const item = document.createElement('div');
        item.className = 'res-item';
        const start = r.start ? r.start.replace(' ', ' at ') : '';
        item.innerHTML = `<strong>${escapeHtml(r.table_name || ('Table ' + (r.table_id||'')))}</strong>
                          <div>${escapeHtml(String(r.party_size || ''))} seat(s) — ${escapeHtml(r.guest || 'Guest')}</div>
                          <div style="font-size:13px;color:#666">${escapeHtml(start)} — status: ${escapeHtml(r.status)}</div>`;
        reservationsList.appendChild(item);
      });
    } catch (err) {
      reservationsList.innerHTML = `<div class="res-item">Network error: ${escapeHtml(err.message || err)}</div>`;
    }
  }

  // fetch availability for selected date/time
  async function fetchAvailability(date, time) {
    if (!availabilityList) return;
    availabilityList.innerHTML = 'Loading availability...';
    // read desired seats/party size from reservation modal input if present
    const partyInput = document.getElementById('resParty');
    const seats = partyInput ? Number(partyInput.value) || 1 : 1;
    const durationInput = document.getElementById('resDuration');
    const duration = durationInput ? Number(durationInput.value) || 90 : 90;

    try {
      const url = `../api/get_availability.php?date=${encodeURIComponent(date)}&time=${encodeURIComponent(time)}&duration=${encodeURIComponent(duration)}&seats=${encodeURIComponent(seats)}`;
      const result = await fetchJsonOrText(url);
      if (!result.ok) {
        availabilityList.innerHTML = `<div class="avail-item">Error: ${escapeHtml(result.text)}</div>`;
        return;
      }
      if (!result.json || !result.json.success) {
        const err = result.json && result.json.error ? result.json.error : result.text;
        availabilityList.innerHTML = `<div class="avail-item">Network error: ${escapeHtml(err)}</div>`;
        return;
      }
      const rows = result.json.data;
      if (!rows || rows.length === 0) {
        availabilityList.innerHTML = '<div class="avail-item">No available tables for this time.</div>';
        return;
      }

      availabilityList.innerHTML = '';
      rows.forEach(t => {
        const el = document.createElement('div');
        el.className = 'avail-item';
        el.innerHTML = `<div>
                          <strong>${escapeHtml(t.name || ('Table ' + t.id))}</strong>
                          <div style="font-size:13px;color:#666">${escapeHtml(String(t.seats))} seat(s) — ${escapeHtml(t.status||'')}</div>
                        </div>
                        <div>
                          <button type="button" class="btn ghost" data-table-id="${t.id}">Reserve</button>
                        </div>`;
        // wire reserve button
        const btn = el.querySelector('button');
        btn.addEventListener('click', () => {
          openReservationModalWith(t.id, date, time, seats, duration);
        });
        availabilityList.appendChild(el);
      });
    } catch (err) {
      availabilityList.innerHTML = `<div class="avail-item">Network error: ${escapeHtml(err.message || err)}</div>`;
    }
  }

  // prefill reservation modal fields and open it
  function openReservationModalWith(tableId, date, time, party_size, duration) {
    // set hidden table id
    const resTableId = document.getElementById('resTableId');
    const resDate = document.getElementById('resDate');
    const resTime = document.getElementById('resTime');
    const resParty = document.getElementById('resParty');
    const resDuration = document.getElementById('resDuration');

    if (resTableId) resTableId.value = tableId || '';
    if (resDate) resDate.value = date || '';
    if (resTime) resTime.value = time || '';
    if (resParty) resParty.value = party_size || 1;
    if (resDuration) resDuration.value = duration || 90;

    // ensure the modal open button exists and trigger it. reservation.js listens to this click.
    const openBtn = document.getElementById('btnAddReservation') || document.getElementById('fabNew');
    if (openBtn) {
      openBtn.click();
      // focus modal submit after short delay (modal open animation)
      setTimeout(() => {
        const submit = document.getElementById('resSubmit');
        if (submit) submit.focus();
      }, 300);
    } else {
      alert('Reservation modal not found — please open the New reservation modal manually.');
    }
  }

  function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  // start
  function start() {
    initFilterButtons();
    initCalendar();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();