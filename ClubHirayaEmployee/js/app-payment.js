/**
 * app-payment.js — Payment modal + save/print flow (with note + cabin optional)
 * Cleaned + defensive version — exposes appPayments, debug logging, dual table fields
 */
(function () {
  'use strict';

  // Debug banner so we can confirm file loaded
  try {
    console.info('INFO: app-payment.js loaded');
  } catch (e) { /* ignore */ }

  function $id(id) { return document.getElementById(id); }

  /* ---------------------
     Server rates helper
  ----------------------*/
  async function fetchServerRatesOnce() {
    if (window._app_payment_rates) return window._app_payment_rates;
    let sR = 0.10, tR = 0.12;
    try {
      const r = await fetch('api/get_settings.php', { cache: 'no-store', credentials: 'same-origin' });
      if (r.ok) {
        const s = await r.json();
        sR = (Number(s.service_charge) || 10) / 100;
        tR = (Number(s.tax) || 12) / 100;
      }
    } catch (e) { console.debug('fetchServerRatesOnce failed', e); }
    window._app_payment_rates = { serviceRate: sR, taxRate: tR };
    return window._app_payment_rates;
  }

  /* ---------------------
     Read order items best-effort
  ----------------------*/
  function readOrderArrayBestEffort() {
    try {
      if (window.appActions && typeof window.appActions.gatherCartForPayload === 'function') {
        const raw = window.appActions.gatherCartForPayload();
        if (Array.isArray(raw)) return raw;
      }
    } catch (e) { console.debug('gatherCartForPayload failed', e); }

    try {
      if (typeof window.getOrder === 'function') {
        const o = window.getOrder();
        if (Array.isArray(o)) return o;
      }
    } catch (e) { console.debug('getOrder failed', e); }

    if (Array.isArray(window.order)) return window.order;

    try {
      const rows = document.querySelectorAll('#orderList .order-item');
      if (!rows.length) return [];
      const out = [];
      rows.forEach(r => {
        const name = r.querySelector('.order-item-name')?.textContent.trim() || '';
        const qty = Number(r.querySelector('.order-qty-input')?.value || 1);
        const priceEl = r.querySelector('.order-item-price');
        let line = 0;
        if (priceEl?.dataset?.pricePhp) line = Number(priceEl.dataset.pricePhp);
        else line = Number((priceEl?.textContent || '').replace(/[^\d.-]/g, '')) || 0;
        const id = r.dataset?.id ? Number(r.dataset.id) : null;
        const unit = qty ? line / qty : 0;
        out.push({ id, name, qty, price: unit, line_total: line });
      });
      return out;
    } catch (e) {
      console.debug('DOM order read failed', e);
      return [];
    }
  }

  /* ---------------------
     Compute totals (fallback)
  ----------------------*/
  async function computeTotalsFromOrder() {
    try {
      if (typeof window.computeNumbers === 'function') {
        const n = window.computeNumbers();
        if (n && typeof n.payable !== 'undefined') return n;
      }
    } catch (e) { console.debug('computeNumbers hook failed', e); }

    const ord = readOrderArrayBestEffort();
    if (!ord.length) return { subtotal: 0, serviceCharge: 0, tax: 0, discountAmount: 0, tablePrice: 0, payable: 0 };

    const rates = await fetchServerRatesOnce();
    let subtotal = 0;
    for (const it of ord) {
      const q = Number(it.qty || 1), u = Number(it.price || 0);
      subtotal += q * u;
    }

    const svc = subtotal * (rates.serviceRate || 0.10),
      tax = subtotal * (rates.taxRate || 0.12),
      disc = subtotal * (window.discountRate || 0),
      tbl = parseFloat(document.body.dataset.reservedTablePrice) || 0,
      pay = subtotal + svc + tax - disc + tbl;

    const r = v => Math.round((Number(v) + Number.EPSILON) * 100) / 100;

    return {
      subtotal: r(subtotal),
      serviceCharge: r(svc),
      tax: r(tax),
      discountAmount: r(disc),
      tablePrice: r(tbl),
      payable: r(pay)
    };
  }

  /* ---------------------
     Helpers
  ----------------------*/
  function escapeHtml(s) { return String(s).replace(/[&<>"']/g, function (m) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]; }); }

  /* ---------------------
     UI: Create payment modal
     Returns overlay element with modalApi { getSelectedMethod, close }
  ----------------------*/
  function createPaymentModal() {
    // if already present, return it
    if ($id('paymentModal')) return $id('paymentModal');

    // container
    const overlay = document.createElement('div');
    overlay.id = 'paymentModal';
    overlay.style = 'position:fixed;inset:0;background:rgba(0,0,0,0.45);display:flex;align-items:center;justify-content:center;z-index:99999;padding:16px;';

    // card
    const card = document.createElement('div');
    card.setAttribute('role', 'dialog');
    card.setAttribute('aria-modal', 'true');
    card.style = 'width:820px;max-width:980px;max-height:92vh;overflow:auto;background:#fff;border-radius:12px;padding:18px;box-shadow:0 12px 36px rgba(0,0,0,0.35);display:flex;flex-direction:column;gap:12px;';
    overlay.append(card);

    // header
    const header = document.createElement('div');
    header.style = 'display:flex;align-items:center;justify-content:space-between;gap:8px;';
    const title = document.createElement('div');
    title.textContent = 'How would you like to pay?';
    title.style = 'font-weight:800;font-size:18px;';
    const closeBtn = document.createElement('button');
    closeBtn.innerHTML = '&times;';
    closeBtn.style = 'font-size:22px;border:none;background:transparent;cursor:pointer;';
    closeBtn.onclick = () => overlay.remove();
    header.append(title, closeBtn);
    card.append(header);

    // main
    const main = document.createElement('div');
    main.style = 'display:flex;gap:18px;flex-wrap:wrap;';
    card.append(main);

    // left column
    const left = document.createElement('div');
    left.style = 'flex:1;min-width:340px;max-width:64%;';
    main.append(left);

    // payment method buttons
    const methodRow = document.createElement('div');
    methodRow.style = 'display:flex;gap:10px;margin-bottom:10px;';
    const methods = ['Cash', 'GCash', 'Bank Transfer'];
    const btns = {};
    methods.forEach(m => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'pmethod-btn';
      b.textContent = m;
      b.style = 'flex:1;padding:10px;border-radius:8px;border:2px solid #ddd;background:#fff;cursor:pointer;font-weight:600;';
      b.onclick = () => selectMethod(m);
      btns[m] = b;
      methodRow.append(b);
    });
    left.append(methodRow);

    // inputs area
    const inputsArea = document.createElement('div');
    inputsArea.id = 'paymentInputsArea';
    inputsArea.style = 'margin-top:6px;';
    left.append(inputsArea);

    // note area
    const noteWrap = document.createElement('div');
    noteWrap.style = 'margin-top:12px;';
    const noteLabel = document.createElement('label');
    noteLabel.textContent = 'Note (optional):';
    noteLabel.style = 'display:block;font-size:13px;margin-bottom:6px;';
    const noteInput = document.createElement('textarea');
    noteInput.id = 'paymentNote';
    noteInput.placeholder = 'Add a note for this sale...';
    noteInput.style = 'width:100%;min-height:56px;padding:8px;border-radius:8px;border:1px solid #ddd;resize:vertical;';
    noteWrap.append(noteLabel, noteInput);
    left.append(noteWrap);

    // right
    const right = document.createElement('div');
    right.style = 'width:300px;min-width:240px;border-left:1px solid #efefef;padding-left:14px;';
    main.append(right);

    const totalsWrap = document.createElement('div');
    totalsWrap.id = 'paymentTotals';
    totalsWrap.style = 'font-size:14px;';
    right.append(totalsWrap);

    const cabinWrap = document.createElement('div');
    cabinWrap.id = 'paymentCabin';
    cabinWrap.style = 'margin-top:8px;font-size:13px;color:#333;';
    right.append(cabinWrap);

    const payerPreview = document.createElement('div');
    payerPreview.id = 'paymentPayerPreview';
    payerPreview.style = 'margin-top:10px;font-size:13px;color:#222;';
    right.append(payerPreview);

    // footer
    const footer = document.createElement('div');
    footer.style = 'display:flex;justify-content:flex-end;gap:10px;margin-top:12px;';
    const cancel = document.createElement('button');
    cancel.type = 'button';
    cancel.textContent = 'Cancel';
    cancel.style = 'padding:10px 14px;border:1px solid #ccc;border-radius:8px;background:#fff;cursor:pointer;';
    cancel.onclick = () => overlay.remove();

    const confirm = document.createElement('button');
    confirm.id = 'paymentConfirmBtn';
    confirm.type = 'button';
    confirm.textContent = 'Save & Print';
    confirm.disabled = true;
    confirm.style = 'padding:10px 14px;border:none;border-radius:8px;background:#d33fd3;color:#fff;cursor:pointer;font-weight:700;';

    footer.append(cancel, confirm);
    card.append(footer);

    // state
    let selectedMethod = 'Cash';
    let updateTimer = null;

    async function updateTotalsUI() {
      const n = await computeTotalsFromOrder();
      const s = '₱';
      totalsWrap.innerHTML = '<div style="font-weight:700;margin-bottom:6px">Summary</div>';
      function add(label, value, strong) {
        const row = document.createElement('div');
        row.style = 'display:flex;justify-content:space-between;margin:4px 0;';
        row.innerHTML = `<div>${label}</div><div style="font-weight:${strong ? 800 : 600}">${s}${Number(value || 0).toFixed(2)}</div>`;
        totalsWrap.append(row);
      }
      add('Subtotal', n.subtotal);
      add('Service', n.serviceCharge);
      add('Tax', n.tax);
      add('Discount', n.discountAmount);
      if (n.tablePrice > 0) add('Reserved', n.tablePrice);
      add('Payable', n.payable, true);

      // cabin preview
      let reserved = null;
      try {
        const raw = sessionStorage.getItem('clubtryara:selected_table_v1');
        if (raw) reserved = JSON.parse(raw);
      } catch (e) { reserved = null; }
      if (reserved) {
        const name = reserved.name || reserved.table || reserved.table_number || '';
        const price = parseFloat(reserved.price || 0);
        cabinWrap.innerHTML = `<div style="font-weight:700">Cabin</div><div>${escapeHtml(name)} ${price ? ' — ' + s + Number(price).toFixed(2) : ''}</div>`;
      } else {
        cabinWrap.innerHTML = `<div style="font-weight:700">Cabin</div><div>No cabin selected</div>`;
      }

      updatePayerPreview();
      validateConfirmButton();
    }

    function startAutoUpdate() { stopAutoUpdate(); updateTimer = setInterval(updateTotalsUI, 700); }
    function stopAutoUpdate() { if (updateTimer) clearInterval(updateTimer); updateTimer = null; }

    // build inputs for each method
    function buildCashInputs() {
      inputsArea.innerHTML = '';
      const lbl = document.createElement('div');
      lbl.textContent = 'Enter cash amount given by customer:';
      lbl.style = 'font-size:13px;color:#444;margin-bottom:6px;';
      const inp = document.createElement('input');
      inp.type = 'number';
      inp.id = 'paymentCashGiven';
      inp.min = '0';
      inp.step = '0.01';
      inp.style = 'padding:8px;width:100%;border-radius:8px;border:1px solid #ddd;';
      inputsArea.append(lbl, inp);

      const changeEl = document.createElement('div');
      changeEl.id = 'paymentChange';
      changeEl.style = 'margin-top:8px;font-weight:700;';
      changeEl.textContent = 'Change: ₱0.00';
      inputsArea.append(changeEl);

      inp.oninput = async () => {
        const g = parseFloat(inp.value || 0);
        const n = await computeTotalsFromOrder();
        const c = g - (n.payable || 0);
        changeEl.textContent = 'Change: ₱' + (c >= 0 ? c.toFixed(2) : '0.00');
        validateConfirmButton();
        updatePayerPreview();
      };

      startAutoUpdate();
      updateTotalsUI();
    }

    function buildGCInputs() {
      inputsArea.innerHTML = '';
      const lbl = document.createElement('div');
      lbl.textContent = 'Enter payer name & reference (GCash):';
      lbl.style = 'font-size:13px;color:#444;margin-bottom:6px;';
      const n = document.createElement('input');
      n.id = 'paymentGcashName';
      n.placeholder = 'Payer name';
      n.style = 'padding:8px;width:100%;border-radius:8px;border:1px solid #ddd;margin-bottom:8px;';
      const r = document.createElement('input');
      r.id = 'paymentGcashRef';
      r.placeholder = 'GCash ref';
      r.style = 'padding:8px;width:100%;border-radius:8px;border:1px solid #ddd;';
      inputsArea.append(lbl, n, r);
      startAutoUpdate();
      updateTotalsUI();
    }

    function buildBankInputs() {
      inputsArea.innerHTML = '';
      const lbl = document.createElement('div');
      lbl.textContent = 'Enter payer name & bank reference:';
      lbl.style = 'font-size:13px;color:#444;margin-bottom:6px;';
      const n = document.createElement('input');
      n.id = 'paymentBankName';
      n.placeholder = 'Payer name';
      n.style = 'padding:8px;width:100%;border-radius:8px;border:1px solid #ddd;margin-bottom:8px;';
      const r = document.createElement('input');
      r.id = 'paymentBankRef';
      r.placeholder = 'Bank ref';
      r.style = 'padding:8px;width:100%;border-radius:8px;border:1px solid #ddd;';
      inputsArea.append(lbl, n, r);
      startAutoUpdate();
      updateTotalsUI();
    }

    function selectMethod(m) {
      selectedMethod = m;
      Object.keys(btns).forEach(k => {
        btns[k].style.borderColor = (k === m) ? '#000' : '#ddd';
        btns[k].style.background = (k === m) ? '#fafafa' : '#fff';
      });

      if (m === 'Cash') buildCashInputs();
      else if (m === 'GCash') buildGCInputs();
      else buildBankInputs();

      validateConfirmButton();
    }

    function updatePayerPreview() {
      let text = '';
      if (selectedMethod === 'Cash') {
        const g = parseFloat($id('paymentCashGiven')?.value || 0);
        const totals = (window.computeNumbers && typeof window.computeNumbers === 'function') ? window.computeNumbers() : null;
        const payable = totals ? (totals.payable || 0) : 0;
        const change = g - payable;
        text = `<div style="font-weight:700">Payment</div>
                <div>Method: Cash Payment</div>
                <div>Given: ₱${Number(g || 0).toFixed(2)}</div>
                <div>Change: ₱${(change > 0 ? change : 0).toFixed(2)}</div>`;
      } else if (selectedMethod === 'GCash') {
        const n = $id('paymentGcashName')?.value || '';
        const r = $id('paymentGcashRef')?.value || '';
        text = `<div style="font-weight:700">Payment</div>
                <div>Method: GCash</div>
                <div>Payer: ${escapeHtml(n)}</div>
                <div>Ref: ${escapeHtml(r)}</div>`;
      } else {
        const n = $id('paymentBankName')?.value || '';
        const r = $id('paymentBankRef')?.value || '';
        text = `<div style="font-weight:700">Payment</div>
                <div>Method: Bank Transfer</div>
                <div>Payer: ${escapeHtml(n)}</div>
                <div>Ref: ${escapeHtml(r)}</div>`;
      }
      payerPreview.innerHTML = text;
    }

    function validateConfirmButton() {
      const btn = $id('paymentConfirmBtn');
      if (!btn) return;
      if (selectedMethod === 'Cash') {
        const given = parseFloat($id('paymentCashGiven')?.value || 0);
        if (isNaN(given) || given < 0) { btn.disabled = true; return; }
      } else if (selectedMethod === 'GCash') {
        const name = ($id('paymentGcashName')?.value || '').trim();
        const ref = ($id('paymentGcashRef')?.value || '').trim();
        if (!name || !ref) { btn.disabled = true; return; }
      } else {
        const name = ($id('paymentBankName')?.value || '').trim();
        const ref = ($id('paymentBankRef')?.value || '').trim();
        if (!name || !ref) { btn.disabled = true; return; }
      }
      btn.disabled = false;
    }

    // confirm handler
    confirm.onclick = async () => {
      confirm.disabled = true;
      confirm.textContent = 'Saving...';

      let details = {};
      let normalizedMethod = '';
      if (selectedMethod === 'Cash') {
        const g = parseFloat($id('paymentCashGiven')?.value || 0);
        const nTotals = await computeTotalsFromOrder();
        details = { given: g, change: Math.round((g - (nTotals.payable || 0) + Number.EPSILON) * 100) / 100 };
        normalizedMethod = 'cash';
      } else if (selectedMethod === 'GCash') {
        const nm = ($id('paymentGcashName')?.value || '').trim();
        const rf = ($id('paymentGcashRef')?.value || '').trim();
        details = { name: nm, ref: rf };
        normalizedMethod = 'gcash';
      } else {
        const nm = ($id('paymentBankName')?.value || '').trim();
        const rf = ($id('paymentBankRef')?.value || '').trim();
        details = { name: nm, ref: rf };
        normalizedMethod = 'bank_transfer';
      }

      const noteVal = ($id('paymentNote')?.value || '').trim();
      const totals = await getTotalsForPayload();
      const items = collectItemsForPayload();
      let reserved = null;
      try { const raw = sessionStorage.getItem('clubtryara:selected_table_v1'); if (raw) reserved = JSON.parse(raw); } catch (e) { reserved = null; }

      // Build payload with both table and table_no for compatibility
      const payload = {
        // both keys for different server endpoints
        table: reserved || (reserved ? reserved : null),
        table_no: reserved?.name || reserved?.table || reserved?.table_number || "",

        total_amount: totals.payable || 0,
        discount: totals.discountAmount || 0,
        service_charge: totals.serviceCharge || 0,
        payment_method: normalizedMethod,
        note: noteVal || window.orderNote || "",

        subtotal: totals.subtotal || 0,
        tax: totals.tax || 0,
        discount_type: window.discountType || "Regular",

        cash_given: details.given || 0,
        change_amount: details.change || 0,

        cabin_name: reserved?.name || reserved?.table || reserved?.table_number || "",
        cabin_price: reserved?.price || 0,

        // if cash -> null, otherwise object
        payment_details: (normalizedMethod === 'cash') ? null : details,

        items: items
      };

      try {
        const res = await saveSaleToServer(payload);
        const id = res.id || 0;

        const meta = {
          sale_id: id,
          payment_method: (normalizedMethod === 'cash') ? 'Cash Payment' : (normalizedMethod === 'gcash' ? 'GCash' : 'Bank Transfer'),
          payment_details: details,
          cashGiven: details.given || 0,
          change: details.change || 0,
          cabin_name: reserved?.name || reserved?.table || reserved?.table_number || "",
          cabin_price: reserved?.price || 0,
          discountType: window.discountType || "Regular",
          discountRate: window.discountRate || 0,
          tax: totals.tax,
          serviceCharge: totals.serviceCharge,
          discountAmount: totals.discountAmount,
          subtotal: totals.subtotal,
          payable: totals.payable,
          note: noteVal || window.orderNote || ""
        };

        // open print page
        const form = document.createElement('form');
        form.action = 'php/print_receipt_payment.php';
        form.method = 'POST';
        form.innerHTML = `
          <input type="hidden" name="cart" value='${JSON.stringify(items)}'>
          <input type="hidden" name="totals" value='${JSON.stringify(totals)}'>
          <input type="hidden" name="reserved" value='${JSON.stringify(reserved || {})}'>
          <input type="hidden" name="meta" value='${JSON.stringify(meta)}'>
          <input type="hidden" name="note" value='${(meta.note || "").replace(/'/g,"&#39;").replace(/"/g,"&quot;")}'>
        `;
        document.body.appendChild(form);
        form.submit();

        clearOrderUI();
        overlay.remove();
        alert('Sale saved (ID:' + id + ')');

      } catch (err) {
        console.error('save error', err);
        alert('Save error: ' + (err && err.error ? err.error : JSON.stringify(err)));
      } finally {
        confirm.disabled = false;
        confirm.textContent = 'Save & Print';
      }
    };

    overlay.addEventListener('keydown', (ev) => {
      if (ev.key === 'Escape') overlay.remove();
    });

    // initial selection
    selectMethod('Cash');

    inputsArea.addEventListener('input', () => {
      updatePayerPreview();
      validateConfirmButton();
    });

    updateTotalsUI();

    overlay.modalApi = {
      getSelectedMethod: () => selectedMethod,
      close: () => { stopAutoUpdate(); overlay.remove(); },
      setMethod: (m) => selectMethod(m)
    };

    document.body.appendChild(overlay);
    return overlay;
  } // end createPaymentModal

  /* ---------------------
     collect items
  ----------------------*/
  function collectItemsForPayload() {
    const arr = readOrderArrayBestEffort();
    return arr.map(i => ({
      menu_item_id: i.id || null,
      item_name: i.name || '',
      qty: Number(i.qty || 1),
      unit_price: Number(i.price || 0),
      line_total: Number(i.line_total || 0)
    }));
  }

  async function getTotalsForPayload() {
    try {
      if (typeof window.computeNumbers === 'function') {
        const n = window.computeNumbers();
        if (n && typeof n.payable !== 'undefined') return n;
      }
    } catch (e) { console.debug('getTotalsForPayload computeNumbers failed', e); }
    return await computeTotalsFromOrder();
  }

  /* ---------------------
     Server call
  ----------------------*/
  async function saveSaleToServer(p) {
    const r = await fetch('php/save_and_print.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(p)
    });
    const t = await r.text();
    try { return JSON.parse(t); } catch (e) { return { ok: false, raw: t }; }
  }

  function clearOrderUI() {
    try { if (typeof clearOrder === 'function') return clearOrder(); } catch (e) { }
    try { window.order = []; } catch (e) { }
    if (typeof renderOrder === 'function') renderOrder();
  }

  /* ---------------------
     wire proceed button
  ----------------------*/
  function wireProceed() {
    const b = document.getElementById('proceedBtn') || document.querySelector('.proceed-btn');
    if (!b) {
      console.debug('proceed button not found');
      return;
    }
    b.onclick = e => {
      e.preventDefault();
      const modal = createPaymentModal();
      if (!modal) {
        console.warn('createPaymentModal returned falsy');
      }
    };
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', wireProceed);
  else wireProceed();

  // expose for debugging & external usage
  window.appPayments = window.appPayments || {
    openPaymentModal: function (m) {
      const o = createPaymentModal();
      if (m) o.modalApi && o.modalApi.setMethod && o.modalApi.setMethod(m);
      return o.modalApi || null;
    }
  };

  // discount buttons behavior
  document.addEventListener('click', e => {
    const btn = e.target.closest('.discount-btn');
    if (!btn) return;
    const type = btn.dataset.type || btn.textContent.trim();
    window.discountType = type;
    if (/senior/i.test(type)) window.discountRate = 0.20;
    else if (/pwd/i.test(type)) window.discountRate = 0.15;
    else window.discountRate = 0;
  });

  document.querySelectorAll('.discount-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const type = btn.dataset.type;
      let rate = 0;
      if (type === 'Senior Citizen') rate = 0.20;
      else if (type === 'PWD') rate = 0.15;
      else rate = 0;
      window.discountType = type;
      window.discountRate = rate;
      console.log('Discount set:', window.discountType, window.discountRate);
    });
  });

})(); // end IIFE
