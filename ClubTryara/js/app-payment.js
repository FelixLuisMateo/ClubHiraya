// app-payments.js
// Payment modal + proceed flow:
// - opens a payment modal (Cash / GCash / Bank Transfer)
// - collects payment details
// - posts sale to php/save_sale.php (does NOT decrement stock)
// - prints using appActions.preparePrintAndOpen (if present)
// - clears the current order UI (calls clearOrder() or uses app's renderOrder())

(function () {
  // helper: safe query selectors
  function $id(id) { return document.getElementById(id); }

  // Build and show payment modal (one-off creation)
  function createPaymentModal() {
    // If already exists, return it
    if ($id('paymentModal')) return $id('paymentModal');

    const overlay = document.createElement('div');
    overlay.id = 'paymentModal';
    overlay.style.position = 'fixed';
    overlay.style.left = 0;
    overlay.style.top = 0;
    overlay.style.right = 0;
    overlay.style.bottom = 0;
    overlay.style.background = 'rgba(0,0,0,0.45)';
    overlay.style.display = 'flex';
    overlay.style.alignItems = 'center';
    overlay.style.justifyContent = 'center';
    overlay.style.zIndex = 99999;

    const card = document.createElement('div');
    card.style.width = '520px';
    card.style.maxWidth = '94%';
    card.style.background = '#fff';
    card.style.borderRadius = '12px';
    card.style.padding = '18px';
    card.style.boxSizing = 'border-box';
    card.style.boxShadow = '0 12px 36px rgba(0,0,0,0.35)';
    card.setAttribute('role', 'dialog');
    card.setAttribute('aria-modal', 'true');

    // Header
    const header = document.createElement('div');
    header.style.display = 'flex';
    header.style.justifyContent = 'space-between';
    header.style.alignItems = 'center';
    const h1 = document.createElement('div');
    h1.textContent = 'How would you like to pay?';
    h1.style.fontWeight = 800;
    h1.style.fontSize = '16px';
    header.appendChild(h1);
    const closeBtn = document.createElement('button');
    closeBtn.innerHTML = '&times;';
    closeBtn.style.fontSize = '20px';
    closeBtn.style.border = 'none';
    closeBtn.style.background = 'transparent';
    closeBtn.style.cursor = 'pointer';
    closeBtn.setAttribute('aria-label', 'Close');
    closeBtn.addEventListener('click', () => overlay.remove());
    header.appendChild(closeBtn);
    card.appendChild(header);

    // Methods row
    const methodsRow = document.createElement('div');
    methodsRow.style.display = 'flex';
    methodsRow.style.gap = '10px';
    methodsRow.style.marginTop = '12px';

    const methods = ['Cash', 'GCash', 'Bank Transfer'];
    const methodButtons = {};
    methods.forEach(m => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'payment-method-btn';
      b.textContent = m;
      b.style.flex = '1';
      b.style.padding = '8px 12px';
      b.style.borderRadius = '10px';
      b.style.border = '2px solid #ddd';
      b.style.cursor = 'pointer';
      b.dataset.method = m;
      b.addEventListener('click', () => selectMethod(m));
      methodButtons[m] = b;
      methodsRow.appendChild(b);
    });
    card.appendChild(methodsRow);

    // Content area for method-specific fields
    const content = document.createElement('div');
    content.id = 'paymentModalContent';
    content.style.marginTop = '14px';
    card.appendChild(content);

    // Footer: show totals and action buttons
    const footer = document.createElement('div');
    footer.style.display = 'flex';
    footer.style.justifyContent = 'space-between';
    footer.style.alignItems = 'center';
    footer.style.marginTop = '16px';

    const totalsWrap = document.createElement('div');
    totalsWrap.style.fontSize = '14px';
    totalsWrap.style.color = '#222';
    totalsWrap.id = 'paymentTotals';
    footer.appendChild(totalsWrap);

    const actions = document.createElement('div');
    actions.style.display = 'flex';
    actions.style.gap = '8px';

    const btnCancel = document.createElement('button');
    btnCancel.type = 'button';
    btnCancel.textContent = 'Cancel';
    btnCancel.style.padding = '8px 14px';
    btnCancel.style.borderRadius = '8px';
    btnCancel.style.border = '1px solid #ccc';
    btnCancel.style.background = '#fff';
    btnCancel.style.cursor = 'pointer';
    btnCancel.addEventListener('click', () => overlay.remove());
    actions.appendChild(btnCancel);

    const btnPay = document.createElement('button');
    btnPay.type = 'button';
    btnPay.id = 'paymentConfirmBtn';
    btnPay.textContent = 'Save & Print';
    btnPay.style.padding = '8px 14px';
    btnPay.style.borderRadius = '8px';
    btnPay.style.border = 'none';
    btnPay.style.background = '#d33fd3';
    btnPay.style.color = '#fff';
    btnPay.style.cursor = 'pointer';
    actions.appendChild(btnPay);

    footer.appendChild(actions);
    card.appendChild(footer);

    overlay.appendChild(card);
    document.body.appendChild(overlay);

    // State
    let selectedMethod = null;

    // helper to display totals
    function updateTotalsDisplay(totals) {
      const symbol = (window.APP_SETTINGS && window.APP_SETTINGS.currency === 'PHP') ? '₱' : '₱';
      // prefer computeNumbers if available
      const nums = (typeof computeNumbers === 'function') ? computeNumbers() : (totals || { payable: 0 });
      totalsWrap.innerHTML = `<div style="font-weight:700">Total: ${symbol} ${Number(nums.payable).toFixed(2)}</div>`;
      if (nums.subtotal !== undefined) {
        totalsWrap.innerHTML += `<div style="font-size:12px;color:#666">Subtotal ${symbol}${nums.subtotal.toFixed(2)}</div>`;
      }
    }

    // method-specific UI builders
    function buildCashUI() {
      content.innerHTML = '';
      const note = document.createElement('div');
      note.textContent = 'Enter cash amount given by customer (system will compute change).';
      note.style.fontSize = '13px';
      note.style.color = '#444';
      content.appendChild(note);

      const input = document.createElement('input');
      input.type = 'number';
      input.id = 'paymentCashGiven';
      input.min = '0';
      input.step = '0.01';
      input.style.marginTop = '8px';
      input.style.padding = '8px';
      input.style.width = '100%';
      input.style.boxSizing = 'border-box';
      content.appendChild(input);

      const changeRow = document.createElement('div');
      changeRow.id = 'paymentChange';
      changeRow.style.marginTop = '8px';
      changeRow.style.fontWeight = 700;
      changeRow.textContent = 'Change: ₱ 0.00';
      content.appendChild(changeRow);

      input.addEventListener('input', () => {
        const given = parseFloat(input.value || 0);
        const nums = (typeof computeNumbers === 'function') ? computeNumbers() : { payable: 0 };
        const change = given - (nums.payable || 0);
        changeRow.textContent = 'Change: ₱ ' + (change >= 0 ? change.toFixed(2) : '0.00');
      });
    }

    function buildGcashUI() {
      content.innerHTML = '';
      const p1 = document.createElement('div'); p1.textContent = 'Enter payer name and reference number (GCash).'; p1.style.fontSize='13px'; p1.style.color='#444';
      content.appendChild(p1);

      const name = document.createElement('input'); name.type='text'; name.placeholder='Payer name'; name.id='paymentGcashName';
      name.style.marginTop='8px'; name.style.padding='8px'; name.style.width='100%'; content.appendChild(name);

      const ref = document.createElement('input'); ref.type='text'; ref.placeholder='GCash reference / transaction ID'; ref.id='paymentGcashRef';
      ref.style.marginTop='8px'; ref.style.padding='8px'; ref.style.width='100%'; content.appendChild(ref);
    }

    function buildBankUI() {
      content.innerHTML = '';
      const p1 = document.createElement('div'); p1.textContent = 'Enter payer and bank transfer reference.'; p1.style.fontSize='13px'; p1.style.color='#444';
      content.appendChild(p1);

      const name = document.createElement('input'); name.type='text'; name.placeholder='Payer name'; name.id='paymentBankName';
      name.style.marginTop='8px'; name.style.padding='8px'; name.style.width='100%'; content.appendChild(name);

      const ref = document.createElement('input'); ref.type='text'; ref.placeholder='Bank reference / transaction ID'; ref.id='paymentBankRef';
      ref.style.marginTop='8px'; ref.style.padding='8px'; ref.style.width='100%'; content.appendChild(ref);
    }

    // method select
    function selectMethod(method) {
      selectedMethod = method;
      Object.keys(methodButtons).forEach(k => {
        methodButtons[k].style.borderColor = (k === method) ? '#000' : '#ddd';
        methodButtons[k].style.background = (k === method) ? '#f5f5f8' : '#fff';
      });
      if (method === 'Cash') buildCashUI();
      else if (method === 'GCash') buildGcashUI();
      else if (method === 'Bank Transfer') buildBankUI();

      // show totals (computeNumbers may be available on page)
      updateTotalsDisplay();
    }

    // confirm handler — will be wired by outer code (we expose to window)
    // return: { getSelected: fn, close: fn }
    function getSelectedMethod() { return selectedMethod; }
    function close() { overlay.remove(); }

    // expose some helpers on the node for outer use
    overlay.modalApi = { selectMethod, getSelectedMethod, updateTotalsDisplay, close };

    // wire confirm button externally later by grabbing $id('paymentConfirmBtn')
    return overlay;
  }

  // collect current cart into payload items (structure saved by save_sale.php)
  function collectItemsForPayload() {
    // prefer appActions.gatherCartForPayload
    if (window.appActions && typeof window.appActions.gatherCartForPayload === 'function') {
      const raw = window.appActions.gatherCartForPayload();
      // normalize into expected fields
      return (raw || []).map(i => ({
        menu_item_id: i.id ?? null,
        item_name: i.name ?? i.item_name ?? i.title ?? null,
        qty: Number(i.qty || i.quantity || 1),
        unit_price: Number(i.price || i.unit_price || 0),
        line_total: Number((i.qty || 1) * (i.price || i.unit_price || 0))
      }));
    }
    // fallback: window.order
    if (Array.isArray(window.order)) {
      return window.order.map(i => ({
        menu_item_id: i.id ?? null,
        item_name: i.name ?? i.title ?? null,
        qty: Number(i.qty || 1),
        unit_price: Number(i.price || 0),
        line_total: Number((i.qty || 1) * (i.price || 0))
      }));
    }
    return [];
  }

  // helper to get totals (uses computeNumbers if present)
  function getTotalsForPayload() {
    if (typeof computeNumbers === 'function') {
      return computeNumbers();
    }
    // fallback compute
    const items = collectItemsForPayload();
    const subtotal = items.reduce((s, it) => s + (Number(it.line_total) || 0), 0);
    const payable = subtotal;
    return { subtotal, serviceCharge: 0, tax: 0, discountAmount: 0, tablePrice: 0, payable };
  }

  // Save sale to server endpoint
  async function saveSaleToServer(payload) {
    const endpoint = 'php/save_sale.php';
    const res = await fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json;charset=utf-8' },
      credentials: 'same-origin',
      body: JSON.stringify(payload)
    });
    return res.json();
  }

  // Clear order UI after success
  function clearOrderUI() {
    // If your app exposes clearOrder(), call it
    try {
      if (typeof clearOrder === 'function') { clearOrder(); return; }
      // else reset global order and render
      window.order = [];
      if (typeof renderOrder === 'function') renderOrder();
      else {
        const ol = document.getElementById('orderList'); if (ol) ol.innerHTML = '';
        const oc = document.getElementById('orderCompute'); if (oc) oc.innerHTML = '';
      }
    } catch (e) {
      console.warn('clearOrderUI failed', e);
    }
  }

  // Compose payload and perform save/print flow
  async function proceedSaveFlow(paymentMethod, paymentDetails, modalApi) {
    const items = collectItemsForPayload();
    if (!items || items.length === 0) {
      alert('No items in the order.');
      return { ok: false, error: 'empty' };
    }

    const totals = getTotalsForPayload();
    // Compose server payload: table_no from reserved if present
    let reserved = null;
    try {
      if (window.appActions && typeof window.appActions.getReservedTable === 'function') {
        reserved = window.appActions.getReservedTable();
      } else {
        const raw = sessionStorage.getItem('clubtryara:selected_table_v1');
        if (raw) reserved = JSON.parse(raw);
      }
    } catch (e) { reserved = null; }

    const serverPayload = {
      table_no: reserved ? (reserved.table || reserved.name || reserved.id || null) : null,
      created_by: (window.currentUser && window.currentUser.id) ? window.currentUser.id : null,
      total_amount: Number(totals.payable || 0),
      discount: Number(totals.discountAmount || 0),
      service_charge: Number(totals.serviceCharge || 0),
      payment_method: paymentMethod,
      note: (document.getElementById('draftNameInput') ? (document.getElementById('draftNameInput').value || '') : '') || (reserved && reserved.name ? reserved.name : ''),
      items: items,
      payment_details: paymentDetails || {}
    };

    // disable buttons to prevent double-clicks
    const confirmBtn = document.getElementById('paymentConfirmBtn');
    if (confirmBtn) { confirmBtn.disabled = true; confirmBtn.textContent = 'Saving...'; }

    try {
      const result = await saveSaleToServer(serverPayload);
      if (!result || !result.ok) {
        alert('Failed to save sale: ' + (result && result.error ? result.error : 'Unknown error'));
        if (confirmBtn) { confirmBtn.disabled = false; confirmBtn.textContent = 'Save & Print'; }
        return { ok: false, error: result && result.error ? result.error : 'save_failed' };
      }

      const saleId = result.id;

      // Print: use appActions.preparePrintAndOpen if available
      try {
        const printMeta = { saleId: saleId, payment_method: paymentMethod, payment_details: paymentDetails };
        const printTotals = Object.assign({}, totals, { saleId });
        if (window.appActions && typeof window.appActions.preparePrintAndOpen === 'function') {
          window.appActions.preparePrintAndOpen(items, printTotals, reserved, printMeta);
        } else {
          // fallback open print_receipt.php in a new tab and pass via post
          const w = window.open('', '_blank', 'width=820,height=920');
          if (w) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'php/print_receipt.php';
            form.target = w.name;
            const f1 = document.createElement('input'); f1.type='hidden'; f1.name='cart'; f1.value = JSON.stringify(items); form.appendChild(f1);
            const f2 = document.createElement('input'); f2.type='hidden'; f2.name='totals'; f2.value = JSON.stringify(printTotals); form.appendChild(f2);
            const f3 = document.createElement('input'); f3.type='hidden'; f3.name='meta'; f3.value = JSON.stringify(printMeta); form.appendChild(f3);
            document.body.appendChild(form); form.submit(); form.remove();
          } else {
            alert('Please allow popup to print receipt.');
          }
        }
      } catch (err) {
        console.error('Print failed', err);
      }

      // Clear order UI and close modal
      clearOrderUI();
      if (modalApi && typeof modalApi.close === 'function') modalApi.close();

      alert('Sale saved (ID: ' + saleId + ').');
      return { ok: true, id: saleId };
    } catch (err) {
      console.error('save failed', err);
      alert('Error saving sale: ' + (err.message || err));
      if (confirmBtn) { confirmBtn.disabled = false; confirmBtn.textContent = 'Save & Print'; }
      return { ok: false, error: err.message || 'exception' };
    }
  }

  // wire the proceed button
  function wireProceedButton() {
    const proceedBtn = document.getElementById('proceedBtn') || document.querySelector('.proceed-btn');
    if (!proceedBtn) return;

    proceedBtn.addEventListener('click', function (e) {
      e.preventDefault();

      // create modal
      const overlay = createPaymentModal();
      const modalApi = overlay.modalApi;
      // default select Cash
      modalApi.selectMethod('Cash');

      // wire confirm
      const confirmBtn = document.getElementById('paymentConfirmBtn');
      confirmBtn.onclick = async function () {
        const selected = modalApi.getSelectedMethod();
        if (!selected) { alert('Please select a payment method.'); return; }

        // collect payment details by method
        let details = {};
        if (selected === 'Cash') {
          const givenInput = $id('paymentCashGiven');
          const given = givenInput ? Number(givenInput.value || 0) : 0;
          const totals = getTotalsForPayload();
          if (given < (totals.payable || 0)) {
            if (!confirm('Given cash is less than total. Record anyway?')) return;
          }
          details = { given: given, change: (given - (totals.payable || 0)) };
        } else if (selected === 'GCash') {
          const name = $id('paymentGcashName') ? $id('paymentGcashName').value.trim() : '';
          const ref = $id('paymentGcashRef') ? $id('paymentGcashRef').value.trim() : '';
          if (!name || !ref) {
            alert('Please provide payer name and reference for GCash.');
            return;
          }
          details = { name, ref };
        } else if (selected === 'Bank Transfer') {
          const name = $id('paymentBankName') ? $id('paymentBankName').value.trim() : '';
          const ref = $id('paymentBankRef') ? $id('paymentBankRef').value.trim() : '';
          if (!name || !ref) {
            alert('Please provide payer name and bank reference.');
            return;
          }
          details = { name, ref };
        }

        // proceed to save -> print
        await proceedSaveFlow(selected, details, modalApi);
      };
    });
  }

  // Initialize on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', wireProceedButton);
  } else {
    wireProceedButton();
  }

  // Expose for debugging
  window.appPayments = window.appPayments || {};
  window.appPayments.openPaymentModal = function (method) {
    const overlay = createPaymentModal();
    if (method) overlay.modalApi.selectMethod(method);
    return overlay.modalApi;
  };

})();