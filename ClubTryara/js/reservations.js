// ../js/reservation.js
// Minimal reservation modal UI wiring for tables.php
// Updated: supports a hidden input #resTableId to let the modal submit target a specific table_id.

(function () {
  const backdrop = document.getElementById('reservationModalBackdrop');
  const form = document.getElementById('reservationForm');
  const openBtn = document.getElementById('btnAddReservation');
  const fabBtn = document.getElementById('fabNew');
  const cancelBtn = document.getElementById('resCancel');
  const statusEl = document.getElementById('resStatus');

  const inputDate = document.getElementById('resDate');
  const inputTime = document.getElementById('resTime');
  const inputParty = document.getElementById('resParty');
  const inputDuration = document.getElementById('resDuration');
  const inputGuest = document.getElementById('resGuest');
  const inputTableId = document.getElementById('resTableId'); // NEW hidden input

  // Initialize flatpickr for modal inputs if available
  function initModalPickers() {
    if (window.flatpickr && inputDate) {
      flatpickr(inputDate, {
        dateFormat: 'Y-m-d',
        allowInput: true,
        altInput: false
      });
    }
    if (window.flatpickr && inputTime) {
      flatpickr(inputTime, {
        enableTime: true,
        noCalendar: true,
        dateFormat: 'H:i',
        time_24hr: true,
        allowInput: true
      });
    }
  }

  // helper to open modal and set sensible defaults
  function openModal() {
    // default date = today if blank
    const now = new Date();
    const yyyy = now.getFullYear();
    const mm = String(now.getMonth() + 1).padStart(2, '0');
    const dd = String(now.getDate()).padStart(2, '0');

    if (!inputDate.value) inputDate.value = `${yyyy}-${mm}-${dd}`;
    // default time if blank
    if (!inputTime.value) {
      let h = now.getHours() + 1;
      if (h < 18) h = 19;
      if (h > 23) h = 19;
      inputTime.value = String(h).padStart(2, '0') + ':00';
    }

    statusEl.textContent = '';
    backdrop.classList.add('open');
    backdrop.setAttribute('aria-hidden', 'false');

    // focus first field
    setTimeout(() => inputDate.focus(), 50);
    document.addEventListener('keydown', handleEsc);
  }

  function closeModal() {
    backdrop.classList.remove('open');
    backdrop.setAttribute('aria-hidden', 'true');
    statusEl.textContent = '';
    // clear prefilled table id when closing
    if (inputTableId) inputTableId.value = '';
    document.removeEventListener('keydown', handleEsc);
  }

  function handleEsc(e) {
    if (e.key === 'Escape') closeModal();
  }

  // minimal client-side validation
  function validate() {
    const date = inputDate.value;
    const time = inputTime.value;
    const party = Number(inputParty.value);
    const duration = Number(inputDuration.value);
    if (!date) return 'Please select a date.';
    if (!time) return 'Please select a time.';
    if (!party || party < 1) return 'Please enter a valid party size.';
    if (!duration || duration < 15) return 'Please enter a valid duration (>=15).';
    return '';
  }

  async function submitForm(e) {
    e.preventDefault();
    statusEl.textContent = '';
    const err = validate();
    if (err) {
      statusEl.style.color = 'crimson';
      statusEl.textContent = err;
      return;
    }

    // compose start datetime "YYYY-MM-DD HH:MM:SS"
    const start = `${inputDate.value} ${inputTime.value}:00`;
    const payload = {
      start: start,
      duration: Number(inputDuration.value),
      party_size: Number(inputParty.value),
      guest: inputGuest.value.trim()
    };

    // include table_id if prefilled
    if (inputTableId && inputTableId.value) {
      const tid = Number(inputTableId.value);
      if (!Number.isNaN(tid) && tid > 0) payload.table_id = tid;
    }

    try {
      // disable submit
      const submitBtn = document.getElementById('resSubmit');
      submitBtn.disabled = true;
      submitBtn.textContent = 'Creating...';
      statusEl.style.color = '#222';
      statusEl.textContent = 'Creating reservation...';

      const res = await fetch('../api/create_reservation.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      const data = await res.json().catch(() => ({ success: false, error: 'Invalid JSON response' }));

      if (!res.ok || !data.success) {
        statusEl.style.color = 'crimson';
        statusEl.textContent = data.error || `Error: HTTP ${res.status}`;
        submitBtn.disabled = false;
        submitBtn.textContent = 'Create';
        return;
      }

      // success
      statusEl.style.color = '#008800';
      statusEl.textContent = `Reservation created (id: ${data.reservation_id}) — assigned table ${data.table_id}.`;
      // brief delay so user can read
      setTimeout(() => {
        closeModal();
        location.reload();
      }, 900);
    } catch (err) {
      statusEl.style.color = 'crimson';
      statusEl.textContent = err.message || 'Network error';
      const submitBtn = document.getElementById('resSubmit');
      submitBtn.disabled = false;
      submitBtn.textContent = 'Create';
    }
  }

  // click outside modal to close
  backdrop.addEventListener('click', (ev) => {
    if (ev.target === backdrop) closeModal();
  });

  // wire buttons
  if (openBtn) openBtn.addEventListener('click', openModal);
  if (fabBtn) fabBtn.addEventListener('click', openModal);
  if (cancelBtn) cancelBtn.addEventListener('click', (e) => { e.preventDefault(); closeModal(); });

  if (form) form.addEventListener('submit', submitForm);

  // initialize pickers for modal inputs if flatpickr present
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initModalPickers);
  } else {
    initModalPickers();
  }
})();