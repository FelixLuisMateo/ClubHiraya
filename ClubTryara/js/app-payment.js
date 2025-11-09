/**
 * app-payment.js — Complete payment modal + save+print flow
 *
 * - Builds a payment modal (Cash / GCash / Bank Transfer)
 * - Reads order and totals robustly (window.appActions.gatherCartForPayload, window.getOrder, window.order, DOM fallback)
 * - Posts to php/save_and_print.php (saves into sales_report & sales_items)
 * - Opens php/print_receipt_payment.php?sales_id=ID to print (or uses appActions.preparePrintAndOpen if present)
 * - Handles non-JSON server responses (shows server HTML error in new window for debugging)
 * - Keeps order clearing compatible (clearOrder(), window.clearOrder, renderOrder())
 *
 * Save as ClubTryara/js/app-payment.js and hard-refresh (Ctrl+F5).
 */

(function () {
  'use strict';

  // small helper
  function $id(id) { return document.getElementById(id); }

  // ----------------------
  // Order / totals helpers
  // ----------------------
  async function fetchServerRatesOnce() {
    if (window._app_payment_rates) return window._app_payment_rates;
    let serviceRate = 0.10, taxRate = 0.12;
    try {
      const res = await fetch('api/get_settings.php', { cache: 'no-store', credentials: 'same-origin' });
      if (res.ok) {
        const s = await res.json();
        serviceRate = (Number(s.service_charge) || (serviceRate * 100)) / 100;
        taxRate = (Number(s.tax) || (taxRate * 100)) / 100;
      }
    } catch (e) {
      // ignore and use defaults
    }
    window._app_payment_rates = { serviceRate, taxRate };
    return window._app_payment_rates;
  }

  // Try multiple ways to read order array across different app variants
  function readOrderArrayBestEffort() {
    try {
      if (window.appActions && typeof window.appActions.gatherCartForPayload === 'function') {
        const raw = window.appActions.gatherCartForPayload();
        if (Array.isArray(raw)) return raw;
      }
    } catch (e) { /* ignore */ }

    try {
      if (typeof window.getOrder === 'function') {
        const ord = window.getOrder();
        if (Array.isArray(ord)) return ord;
      }
    } catch (e) { /* ignore */ }

    if (Array.isArray(window.order)) return window.order;

    // DOM fallback - parse the rendered order list (best-effort)
    try {
      const rows = document.querySelectorAll('#orderList .order-item');
      if (!rows || rows.length === 0) return [];
      const out = [];
      rows.forEach(r => {
        const nameEl = r.querySelector('.order-item-name');
        const qtyEl = r.querySelector('.order-qty-input');
        const priceEl = r.querySelector('.order-line-price');
        const name = nameEl ? nameEl.textContent.trim() : '';
        const qty = qtyEl ? Number(qtyEl.value || qtyEl.textContent || 1) : 1;
        // get numeric price from data attribute set by setupPriceToggle (we set dataset.pricePhp there)
        let line_total = 0;
        if (priceEl && priceEl.dataset && priceEl.dataset.pricePhp) line_total = Number(priceEl.dataset.pricePhp || 0);
        else if (priceEl) line_total = Number((priceEl.textContent || '').replace(/[^\d.-]/g, '')) || 0;
        const id = r.dataset && r.dataset.id ? Number(r.dataset.id) : null;
        const unit_price = qty ? (line_total / qty) : 0;
        out.push({
          id: id,
          name: name,
          qty: qty,
          price: unit_price,
          line_total: line_total
        });
      });
      return out;
    } catch (e) {
      return [];
    }
  }

  // compute totals (tries computeNumbers if available)
  async function computeTotalsFromOrder() {
    try {
      if (typeof window.computeNumbers === 'function') {
        const n = window.computeNumbers();
        if (n && typeof n.payable !== 'undefined') return n;
      }
    } catch (e) { /* ignore */ }

    const ord = readOrderArrayBestEffort();
    if (!Array.isArray(ord) || ord.length === 0) return { subtotal: 0, serviceCharge: 0, tax: 0, discountAmount: 0, tablePrice: 0, payable: 0 };

    const rates = await fetchServerRatesOnce();
    let subtotal = 0;
    for (const it of ord) {
      const qty = Number(it.qty || it.quantity || 1);
      const unit = Number(it.unit_price ?? it.price ?? it.amount ?? 0);
      const line = Number(it.line_total ?? (qty * unit));
      subtotal += (isNaN(line) ? qty * unit : line);
    }
    const serviceCharge = subtotal * (rates.serviceRate || 0.10);
    const tax = subtotal * (rates.taxRate || 0.12);
    const discountAmount = 0;
    const tablePrice = parseFloat(document.body.dataset.reservedTablePrice) || 0;
    const payable = subtotal + serviceCharge + tax - discountAmount + tablePrice;
    const round = v => Math.round((Number(v) + Number.EPSILON) * 100) / 100;
    return {
      subtotal: round(subtotal),
      serviceCharge: round(serviceCharge),
      tax: round(tax),
      discountAmount: round(discountAmount),
      tablePrice: round(tablePrice),
      payable: round(payable)
    };
  }

  // ----------------------
  // Payment modal builder
  // ----------------------
  function createPaymentModal() {
    // single instance
    if ($id('paymentModal')) return $id('paymentModal');

    const overlay = document.createElement('div');
    overlay.id = 'paymentModal';
    overlay.style.position = 'fixed';
    overlay.style.left = 0; overlay.style.top = 0; overlay.style.right = 0; overlay.style.bottom = 0;
    overlay.style.background = 'rgba(0,0,0,0.45)';
    overlay.style.display = 'flex';
    overlay.style.alignItems = 'center';
    overlay.style.justifyContent = 'center';
    overlay.style.zIndex = 99999;

    const card = document.createElement('div');
    card.style.width = '820px';
    card.style.maxWidth = '98%';
    card.style.maxHeight = '92vh';
    card.style.overflow = 'auto';
    card.style.background = '#fff';
    card.style.borderRadius = '12px';
    card.style.padding = '20px';
    card.style.boxSizing = 'border-box';
    card.style.boxShadow = '0 12px 36px rgba(0,0,0,0.35)';
    card.setAttribute('role', 'dialog');
    card.setAttribute('aria-modal', 'true');

    // header
    const header = document.createElement('div');
    header.style.display = 'flex'; header.style.justifyContent = 'space-between'; header.style.alignItems = 'center';
    const title = document.createElement('div'); title.textContent = 'How would you like to pay?'; title.style.fontWeight = 800; title.style.fontSize = '18px';
    const closeBtn = document.createElement('button'); closeBtn.innerHTML = '&times;'; closeBtn.style.fontSize = '22px'; closeBtn.style.border='none'; closeBtn.style.background='transparent'; closeBtn.style.cursor='pointer';
    closeBtn.setAttribute('aria-label','Close'); closeBtn.addEventListener('click', () => overlay.remove());
    header.appendChild(title); header.appendChild(closeBtn);
    card.appendChild(header);

    // methods
    const methodsRow = document.createElement('div');
    methodsRow.style.display = 'flex'; methodsRow.style.gap = '10px'; methodsRow.style.marginTop = '14px';
    const methods = ['Cash','GCash','Bank Transfer'];
    const methodButtons = {};
    methods.forEach(m => {
      const b = document.createElement('button');
      b.type = 'button'; b.className = 'payment-method-btn'; b.textContent = m; b.style.flex='1'; b.style.padding='10px 14px';
      b.style.borderRadius='10px'; b.style.border='2px solid #ddd'; b.style.cursor='pointer';
      b.dataset.method = m; b.addEventListener('click', () => selectMethod(m));
      methodButtons[m] = b; methodsRow.appendChild(b);
    });
    card.appendChild(methodsRow);

    // content & totals
    const content = document.createElement('div'); content.id = 'paymentModalContent'; content.style.marginTop = '16px';
    const detailsWrap = document.createElement('div'); detailsWrap.style.display='flex'; detailsWrap.style.gap='18px'; detailsWrap.style.marginTop='14px';
    const rightSummary = document.createElement('div'); rightSummary.style.minWidth='260px'; rightSummary.style.maxWidth='34%'; rightSummary.style.borderLeft='1px solid #eee'; rightSummary.style.paddingLeft='12px'; rightSummary.style.boxSizing='border-box';
    const totalsWrap = document.createElement('div'); totalsWrap.id = 'paymentTotals'; totalsWrap.style.fontSize='16px'; totalsWrap.style.color='#222';
    rightSummary.appendChild(totalsWrap);
    detailsWrap.appendChild(content); detailsWrap.appendChild(rightSummary); card.appendChild(detailsWrap);

    // footer buttons
    const footer = document.createElement('div');
    footer.style.display='flex'; footer.style.justifyContent='flex-end'; footer.style.alignItems='center'; footer.style.marginTop='18px';
    const btnCancel = document.createElement('button'); btnCancel.type='button'; btnCancel.textContent='Cancel'; btnCancel.style.padding='10px 16px'; btnCancel.style.borderRadius='8px';
    btnCancel.style.border='1px solid #ccc'; btnCancel.style.background='#fff'; btnCancel.style.cursor='pointer'; btnCancel.addEventListener('click', ()=>overlay.remove());
    const btnPay = document.createElement('button'); btnPay.type='button'; btnPay.id='paymentConfirmBtn'; btnPay.textContent='Save & Print';
    btnPay.style.padding='10px 16px'; btnPay.style.borderRadius='8px'; btnPay.style.border='none'; btnPay.style.background='#d33fd3'; btnPay.style.color='#fff'; btnPay.style.cursor='pointer'; btnPay.style.marginLeft='10px';
    footer.appendChild(btnCancel); footer.appendChild(btnPay); card.appendChild(footer);
    overlay.appendChild(card);
    document.body.appendChild(overlay);

    // state & helpers
    let selectedMethod = null;
    let totalsUpdater = null;

    async function updateTotalsDisplay() {
      try {
        if (typeof window.computeNumbers === 'function') {
          const n = window.computeNumbers();
          if (n && typeof n.payable !== 'undefined') { renderTotals(n); return; }
        }
      } catch (e) { /* ignore */ }
      const n = await computeTotalsFromOrder();
      renderTotals(n);
    }

    function renderTotals(nums) {
      const symbol = (window.APP_SETTINGS && window.APP_SETTINGS.currency === 'PHP') ? '₱' : '₱';
      totalsWrap.innerHTML = '';
      const tt = document.createElement('div'); tt.style.fontWeight = 800; tt.style.marginBottom = '8px'; tt.textContent = 'Summary';
      totalsWrap.appendChild(tt);
      function row(label, value, bold) {
        const r = document.createElement('div'); r.style.display='flex'; r.style.justifyContent='space-between'; r.style.margin='6px 0';
        const l = document.createElement('div'); l.textContent = label;
        const v = document.createElement('div'); v.style.fontWeight = bold ? 800 : 600; v.textContent = symbol + Number(value || 0).toFixed(2);
        r.appendChild(l); r.appendChild(v); totalsWrap.appendChild(r);
      }
      const n = nums || { subtotal:0, serviceCharge:0, tax:0, discountAmount:0, tablePrice:0, payable:0 };
      row('Subtotal', n.subtotal); row('Service Charge', n.serviceCharge); row('Tax', n.tax); row('Discount', n.discountAmount);
      if (n.tablePrice && Number(n.tablePrice) > 0) row('Reserved', n.tablePrice);
      const sep = document.createElement('div'); sep.style.height='1px'; sep.style.background='#eee'; sep.style.margin='8px 0'; totalsWrap.appendChild(sep);
      row('Payable', n.payable, true);
    }

    // payment UIs
    function buildCashUI() {
      content.innerHTML = '';
      const note = document.createElement('div'); note.textContent = 'Enter cash amount given by customer (system will compute change).'; note.style.fontSize='13px'; note.style.color='#444';
      content.appendChild(note);
      const input = document.createElement('input'); input.type='number'; input.id='paymentCashGiven'; input.min='0'; input.step='0.01';
      input.style.marginTop='8px'; input.style.padding='8px'; input.style.width='100%'; input.style.boxSizing='border-box';
      content.appendChild(input);
      const changeRow = document.createElement('div'); changeRow.id='paymentChange'; changeRow.style.marginTop='8px'; changeRow.style.fontWeight=700; changeRow.textContent='Change: ₱ 0.00';
      content.appendChild(changeRow);
      input.addEventListener('input', async () => {
        const given = parseFloat(input.value || 0);
        const nums = (typeof window.computeNumbers === 'function') ? window.computeNumbers() : await computeTotalsFromOrder();
        const change = given - (nums.payable || 0);
        changeRow.textContent = 'Change: ₱ ' + (change >= 0 ? change.toFixed(2) : '0.00');
      });
      startTotalsUpdater(); updateTotalsDisplay();
    }

    function buildGcashUI() {
      content.innerHTML = '';
      const p = document.createElement('div'); p.textContent = 'Enter payer name and reference (GCash).'; p.style.fontSize='13px'; p.style.color='#444';
      content.appendChild(p);
      const name = document.createElement('input'); name.type='text'; name.id='paymentGcashName'; name.placeholder='Payer name'; name.style.marginTop='8px'; name.style.padding='8px'; name.style.width='100%'; content.appendChild(name);
      const ref = document.createElement('input'); ref.type='text'; ref.id='paymentGcashRef'; ref.placeholder='GCash reference'; ref.style.marginTop='8px'; ref.style.padding='8px'; ref.style.width='100%'; content.appendChild(ref);
      startTotalsUpdater(); updateTotalsDisplay();
    }

    function buildBankUI() {
      content.innerHTML = '';
      const p = document.createElement('div'); p.textContent = 'Enter payer name and bank reference.'; p.style.fontSize='13px'; p.style.color='#444';
      content.appendChild(p);
      const name = document.createElement('input'); name.type='text'; name.id='paymentBankName'; name.placeholder='Payer name'; name.style.marginTop='8px'; name.style.padding='8px'; name.style.width='100%'; content.appendChild(name);
      const ref = document.createElement('input'); ref.type='text'; ref.id='paymentBankRef'; ref.placeholder='Bank reference'; ref.style.marginTop='8px'; ref.style.padding='8px'; ref.style.width='100%'; content.appendChild(ref);
      startTotalsUpdater(); updateTotalsDisplay();
    }

    function selectMethod(method) {
      selectedMethod = method;
      Object.keys(methodButtons).forEach(k => {
        methodButtons[k].style.borderColor = (k===method) ? '#000' : '#ddd';
        methodButtons[k].style.background = (k===method) ? '#f5f5f8' : '#fff';
      });
      stopTotalsUpdater();
      if (method === 'Cash') buildCashUI();
      else if (method === 'GCash') buildGcashUI();
      else if (method === 'Bank Transfer') buildBankUI();
      updateTotalsDisplay();
    }

    function startTotalsUpdater() { stopTotalsUpdater(); totalsUpdater = setInterval(updateTotalsDisplay, 600); }
    function stopTotalsUpdater() { if (totalsUpdater) { clearInterval(totalsUpdater); totalsUpdater = null; } }

    function getSelectedMethod() { return selectedMethod; }
    function close() { stopTotalsUpdater(); overlay.remove(); }

    overlay.modalApi = { selectMethod, getSelectedMethod, updateTotalsDisplay, close };

    // default
    selectMethod('Cash');

    // watch for removal
    const mo = new MutationObserver(()=>{ if (!document.body.contains(overlay)) stopTotalsUpdater(); });
    mo.observe(document.body, { childList:true, subtree:true });

    return overlay;
  }

  // ----------------------
  // Collect items for server payload
  // - ensure menu_item_id is included when available
  // ----------------------
  function collectItemsForPayload() {
    // 1) appActions.gatherCartForPayload
    try {
      if (window.appActions && typeof window.appActions.gatherCartForPayload === 'function') {
        const raw = window.appActions.gatherCartForPayload() || [];
        return raw.map(i => ({
          menu_item_id: i.id ?? null,
          item_name: i.name ?? i.item_name ?? i.title ?? '',
          qty: Number(i.qty || i.quantity || 1),
          unit_price: Number(i.price ?? i.unit_price ?? i.amount ?? 0),
          line_total: Number(i.line_total ?? ((Number(i.qty || 1)) * Number(i.price ?? i.unit_price ?? i.amount ?? 0)))
        }));
      }
    } catch (e) {}

    // 2) window.getOrder() or window.order
    try {
      let arr = null;
      if (typeof window.getOrder === 'function') arr = window.getOrder();
      else if (Array.isArray(window.order)) arr = window.order;
      if (Array.isArray(arr) && arr.length > 0) {
        return arr.map(i => ({
          menu_item_id: i.id ?? null,
          item_name: i.name ?? i.item_name ?? i.title ?? '',
          qty: Number(i.qty || i.quantity || 1),
          unit_price: Number(i.price ?? i.unit_price ?? i.amount ?? 0),
          line_total: Number(i.line_total ?? (Number(i.qty || 1) * Number(i.price ?? i.unit_price ?? i.amount ?? 0)))
        }));
      }
    } catch (e) {}

    // 3) DOM fallback
    const dom = readOrderArrayBestEffort();
    if (Array.isArray(dom) && dom.length > 0) {
      return dom.map(i => ({
        menu_item_id: i.id ?? null,
        item_name: i.name ?? i.item_name ?? '',
        qty: Number(i.qty || 1),
        unit_price: Number(i.price || i.unit_price || 0),
        line_total: Number(i.line_total || 0)
      }));
    }

    return [];
  }

  async function getTotalsForPayload() {
    try {
      if (typeof window.computeNumbers === 'function') {
        const n = window.computeNumbers();
        if (n && typeof n.payable !== 'undefined') return n;
      }
    } catch (e) {}
    return await computeTotalsFromOrder();
  }

  // ----------------------
  // Save to server (save_and_print.php)
  // - robustly handle non-JSON responses (PHP warnings/errors produce HTML)
  // ----------------------
  async function saveSaleToServer(payload) {
    const endpoint = 'php/save_and_print.php';
    const res = await fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json;charset=utf-8' },
      credentials: 'same-origin',
      body: JSON.stringify(payload)
    });

    // Try parse JSON, but if server returned HTML (PHP error), return { _rawText: ... } so caller can show it
    const text = await res.text();
    try {
      const json = JSON.parse(text);
      return json;
    } catch (parseErr) {
      // not JSON -> return object describing the error and include raw HTML/text for debugging
      return { ok: false, parseError: parseErr.message, rawText: text, httpStatus: res.status, httpStatusText: res.statusText };
    }
  }

  // ----------------------
  // Clear order UI (compatible with multiple app variants)
  // ----------------------
  function clearOrderUI() {
    try {
      if (typeof clearOrder === 'function') { clearOrder(); return; }
    } catch (e) {}
    try {
      if (typeof window.clearOrder === 'function') { window.clearOrder(); return; }
    } catch (e) {}
    // fallback: clear window.order and call renderOrder if present
    try { window.order = []; } catch (e) {}
    if (typeof renderOrder === 'function') renderOrder();
    else {
      const ol = document.getElementById('orderList'); if (ol) ol.innerHTML = '';
      const oc = document.getElementById('orderCompute'); if (oc) oc.innerHTML = '';
    }
  }

  // ----------------------
  // Proceed: build payload, call save_and_print.php, open print page
  // ----------------------
  async function proceedSaveFlow(paymentMethod, paymentDetails, modalApi) {
    const items = collectItemsForPayload();
    if (!items || items.length === 0) {
      alert('No items in the order.');
      return { ok: false, error: 'empty' };
    }

    const totals = await getTotalsForPayload();

    // reserved table meta
    let reserved = null;
    try {
      if (window.appActions && typeof window.appActions.getReservedTable === 'function') reserved = window.appActions.getReservedTable();
      else {
        const raw = sessionStorage.getItem('clubtryara:selected_table_v1');
        if (raw) reserved = JSON.parse(raw);
      }
    } catch (e) { reserved = null; }

    const payload = {
      items: items,
      totals: totals,
      payment_method: paymentMethod,
      payment_details: paymentDetails || {},
      table: reserved || null,
      created_by: (window.currentUser && window.currentUser.id) ? window.currentUser.id : null,
      note: (document.getElementById('draftNameInput') ? (document.getElementById('draftNameInput').value || '') : '') || (reserved && reserved.name ? reserved.name : '')
    };

    const btn = $id('paymentConfirmBtn');
    if (btn) { btn.disabled = true; btn.textContent = 'Saving...'; }

    try {
      const result = await saveSaleToServer(payload);

      // If result contains rawText -> server returned HTML (likely a PHP error). Open it in window for debugging.
      if (result && result.rawText) {
        const w = window.open('', '_blank');
        if (w) {
          w.document.open();
          w.document.write(result.rawText);
          w.document.close();
          alert('Server returned non-JSON response. Opened debug page in new tab. Check server logs for PHP errors.');
        } else {
          // can't open window
          alert('Server returned an error HTML response; please check server logs. Response snippet:\n\n' + (result.rawText.substring(0, 800)));
        }
        if (btn) { btn.disabled = false; btn.textContent = 'Save & Print'; }
        return { ok: false, error: 'server_html' };
      }

      if (!result || !result.ok) {
        alert('Failed to save sale: ' + (result && (result.error || result.message) ? (result.error || result.message) : 'Unknown error'));
        if (btn) { btn.disabled = false; btn.textContent = 'Save & Print'; }
        return { ok:false, error: 'save_failed', raw: result };
      }

      const salesId = result.id;

      // print using server-rendered receipt
      try {
        if (window.appActions && typeof window.appActions.preparePrintAndOpen === 'function') {
          // allow app-specific printing flow
          const printTotals = Object.assign({}, totals, { saleId: salesId });
          window.appActions.preparePrintAndOpen(items, printTotals, reserved, { saleId: salesId, payment_method: paymentMethod, payment_details: paymentDetails });
        } else {
          const url = 'php/print_receipt_payment.php?sales_id=' + encodeURIComponent(salesId);
          const w = window.open(url, '_blank', 'width=820,height=920');
          if (!w) alert('Saved but could not open receipt — please allow popups.');
        }
      } catch (err) {
        console.error('Print flow failed', err);
      }

      // clear and close
      clearOrderUI();
      if (modalApi && typeof modalApi.close === 'function') modalApi.close();
      if (btn) { btn.disabled = false; btn.textContent = 'Save & Print'; }

      alert('Sale saved (ID: ' + salesId + ').');
      return { ok:true, id: salesId };
    } catch (err) {
      console.error('save exception', err);
      alert('Error saving sale: ' + (err.message || err));
      if (btn) { btn.disabled = false; btn.textContent = 'Save & Print'; }
      return { ok:false, error: err.message || 'exception' };
    }
  }

  // ----------------------
  // Wire Proceed button to open modal and handle confirm
  // ----------------------
  function wireProceedButton() {
    const proceedBtn = document.getElementById('proceedBtn') || document.querySelector('.proceed-btn');
    if (!proceedBtn) return;

    proceedBtn.addEventListener('click', function (e) {
      e.preventDefault();
      const overlay = createPaymentModal();
      const modalApi = overlay.modalApi;
      const confirmBtn = $id('paymentConfirmBtn');
      if (!confirmBtn) return;

      confirmBtn.onclick = async function () {
        const selected = modalApi.getSelectedMethod();
        if (!selected) { alert('Please select a payment method.'); return; }

        // collect method-specific details
        let details = {};
        if (selected === 'Cash') {
          const giv = $id('paymentCashGiven'); const given = giv ? Number(giv.value || 0) : 0;
          const totals = await getTotalsForPayload();
          if (given < (totals.payable || 0)) {
            if (!confirm('Given cash is less than total. Record anyway?')) return;
          }
          details = { given: given, change: (given - (totals.payable || 0)) };
        } else if (selected === 'GCash') {
          const name = $id('paymentGcashName') ? $id('paymentGcashName').value.trim() : '';
          const ref = $id('paymentGcashRef') ? $id('paymentGcashRef').value.trim() : '';
          if (!name || !ref) { alert('Please provide payer name and reference for GCash.'); return; }
          details = { name, ref };
        } else if (selected === 'Bank Transfer') {
          const name = $id('paymentBankName') ? $id('paymentBankName').value.trim() : '';
          const ref = $id('paymentBankRef') ? $id('paymentBankRef').value.trim() : '';
          if (!name || !ref) { alert('Please provide payer name and bank reference.'); return; }
          details = { name, ref };
        }

        await proceedSaveFlow(selected.toLowerCase().replace(/\s+/g,'_'), details, modalApi);
      };
    });
  }

  // Initialize
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', wireProceedButton);
  else wireProceedButton();

  // expose api
  window.appPayments = window.appPayments || {};
  window.appPayments.openPaymentModal = function (method) {
    const overlay = createPaymentModal();
    if (method) overlay.modalApi.selectMethod(method);
    return overlay.modalApi;
  };

})();