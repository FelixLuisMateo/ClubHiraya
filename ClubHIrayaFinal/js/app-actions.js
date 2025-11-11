/**
 * app-actions.js (fixed)
 *
 * Minimal, safe helper module used by the POS.
 * - Exposes gatherCartForPayload() and getReservedTable()
 * - Provides preparePrintAndOpen() which now posts to the new payment-aware receipt:
 *     php/print_receipt_payment.php
 *
 * Save as ClubTryara/js/app-actions.js (replace existing).
 */

(function () {
  // Use the payment-aware print endpoint (ensure path correct relative to the page that loads this script)
  // If your POS pages live at /ClubHiraya/clubtryara/, the relative 'php/print_receipt_payment.php' will resolve correctly.
  const PRINT_ENDPOINT = 'php/print_receipt_payment.php';

  /**
   * gatherCartForPayload
   * Return a normalized array of items for server payloads.
   * Prefer window.order if available; otherwise fall back to an empty array.
   */
  function gatherCartForPayload() {
    try {
      if (Array.isArray(window.order)) return window.order.map(i => ({
        id: i.id,
        name: i.name,
        price: i.price,
        qty: i.qty,
        // keep legacy-compatible keys too
        item_name: i.name,
        unit_price: i.price,
        line_total: (Number(i.qty || 0) * Number(i.price || 0))
      }));
    } catch (e) { /* ignore */ }
    return [];
  }

  /**
   * getReservedTable
   * Return the reservation/cabin object recorded in sessionStorage or via tablesSelect helper.
   */
  function getReservedTable() {
    try {
      if (window.tablesSelect && typeof window.tablesSelect.getSelectedTable === 'function') {
        return window.tablesSelect.getSelectedTable();
      }
      const raw = sessionStorage.getItem('clubtryara:selected_table_v1');
      if (raw) return JSON.parse(raw);
    } catch (e) { /* ignore */ }
    return null;
  }

  /**
   * preparePrintAndOpen(cart, totals, reserved, meta)
   *
   * Build a form, post data to PRINT_ENDPOINT and open in a new window for printing.
   * This helper intentionally posts these fields:
   *  - cart  (array)
   *  - totals (object)
   *  - reserved (object)           <-- used by print_receipt_payment.php
   *  - meta (object)               <-- includes payment_method/payment_details
   *
   * Using POST keeps large payloads safe and avoids query string issues.
   */
  async function preparePrintAndOpen(cart, totals, reserved, meta = {}) {
    try {
      const w = window.open('', '_blank', 'width=820,height=920');
      if (!w) {
        alert('Please allow popups for printing.');
        return;
      }

      // Build a form that targets the new window
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = PRINT_ENDPOINT;
      form.target = w.name;

      const cartInput = document.createElement('input');
      cartInput.type = 'hidden';
      cartInput.name = 'cart';
      cartInput.value = JSON.stringify(cart || []);
      form.appendChild(cartInput);

      const totalsInput = document.createElement('input');
      totalsInput.type = 'hidden';
      totalsInput.name = 'totals';
      totalsInput.value = JSON.stringify(totals || {});
      form.appendChild(totalsInput);

      const reservedInput = document.createElement('input');
      reservedInput.type = 'hidden';
      reservedInput.name = 'reserved';
      reservedInput.value = JSON.stringify(reserved || {});
      form.appendChild(reservedInput);

      const metaInput = document.createElement('input');
      metaInput.type = 'hidden';
      metaInput.name = 'meta';
      metaInput.value = JSON.stringify(meta || {});
      form.appendChild(metaInput);

      // Helpful hidden flag so PHP can detect this is the payment print path if needed
      const _flag = document.createElement('input');
      _flag.type = 'hidden';
      _flag.name = '__from_app_actions';
      _flag.value = '1';
      form.appendChild(_flag);

      document.body.appendChild(form);
      form.submit();
      document.body.removeChild(form);
    } catch (err) {
      console.error('preparePrintAndOpen error', err);
      alert('Failed to prepare print: ' + (err.message || err));
    }
  }

  // Expose small API
  window.appActions = window.appActions || {};
  window.appActions.preparePrintAndOpen = preparePrintAndOpen;
  window.appActions.getReservedTable = getReservedTable;
  window.appActions.gatherCartForPayload = gatherCartForPayload;

  // NOTHING ELSE here: do not auto-wire proceed button in this small helper.
})();
