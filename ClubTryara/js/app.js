/**
 * Updated app.js
 * - Drafts: full localStorage-based draft manager (list/load/delete). Clicking the Drafts button shows previous drafts.
 * - Discounts: selectable choices only (Regular = 0%, Senior Citizen = 20%, PWD = 20%). Discount is calculated as percentage of subtotal.
 * - Removed Hold Order creation in the compute area and removed any Bill Out handler/UI usage (existing bill out button removed from DOM if present).
 * - Keeps order management (add/remove/qty), category tabs (no "All"), search and proceed behavior.
 *
 * Replace your existing ClubTryara/js/app.js with this file.
 */

document.addEventListener('DOMContentLoaded', () => {
  // DOM refs
  const foodsGrid = document.getElementById('foodsGrid');
  const categoryTabs = document.getElementById('categoryTabs');
  const searchBox = document.getElementById('searchBox');
  const orderList = document.getElementById('orderList');
  const orderCompute = document.getElementById('orderCompute');

  const draftModal = document.getElementById('draftModal'); // modal wrapper
  // we'll build modal-content dynamically inside .modal-content element
  const draftModalContent = draftModal.querySelector('.modal-content');

  const draftBtn = document.getElementById('draftBtn');
  const closeDraftModalFallback = document.getElementById('closeDraftModal'); // might be replaced later

  const newOrderBtn = document.getElementById('newOrderBtn');
  const refreshBtn = document.getElementById('refreshBtn');
  const billOutBtn = document.getElementById('billOutBtn'); // will be removed if present

  // desired order for categories (fixed)
  const desiredOrder = [
    "Main Course",
    "Appetizer",
    "Soup",
    "Salad",
    "Seafoods",
    "Pasta & Noodles",
    "Sides",
    "Drinks"
  ];

  // tax/service rates (as previously used)
  const SERVICE_RATE = 0.10;
  const TAX_RATE = 0.12;

  // Discount types
  const DISCOUNT_TYPES = {
    'Regular': 0.00,
    'Senior Citizen': 0.20,
    'PWD': 0.20
  };

  let products = [];
  let categories = [];
  let currentCategory = null;
  let order = [];
  // discountRate expressed as decimal (e.g., 0.2). Default Regular
  let discountRate = DISCOUNT_TYPES['Regular'];
  let discountType = 'Regular';
  let noteValue = '';

  // Remove Bill Out button from DOM to meet the user's request
  if (billOutBtn) {
    billOutBtn.remove();
  }

  // ---------- PRODUCT LOADING ----------
  async function loadProducts() {
    try {
      const res = await fetch('php/get_products.php', { cache: 'no-store' });
      if (!res.ok) throw new Error('Server returned ' + res.status);
      const data = await res.json();
      if (Array.isArray(data)) products = data;
      else if (Array.isArray(data.foods)) products = data.foods;
      else products = [];
    } catch (err) {
      console.warn('Failed to load php/get_products.php — using sample data', err);
      products = [
        { id: 1, name: 'Lechon Baka', price: 420, category: 'Main Course', image: 'assets/lechon_baka.jpg', description: '' },
        { id: 2, name: 'Hoisin BBQ Pork Ribs', price: 599, category: 'Main Course', image: 'assets/ribs.jpg', description: '' },
        { id: 3, name: 'Mango Habanero', price: 439, category: 'Main Course', image: 'assets/mango.jpg', description: '' },
        { id: 4, name: 'Smoked Carbonara', price: 349, category: 'Pasta & Noodles', image: 'assets/carbonara.jpg', description: '' },
        { id: 5, name: 'Mozzarella Poppers', price: 280, category: 'Appetizer', image: 'assets/poppers.jpg', description: '' },
        { id: 6, name: 'Salmon Tare-Tare', price: 379, category: 'Seafoods', image: 'assets/salmon.jpg', description: '' }
      ];
    }

    buildCategoryList();
    // choose initial category: first desired that exists, else first available
    const found = desiredOrder.find(c => categories.includes(c));
    currentCategory = found || (categories.length ? categories[0] : null);
    renderCategoryTabs();
    renderProducts();
    renderOrder();
  }

  function buildCategoryList() {
    const set = new Set(products.map(p => String(p.category || '').trim()).filter(Boolean));
    categories = Array.from(set);
  }

  // ---------- CATEGORIES (no "All" button) ----------
  function renderCategoryTabs() {
    categoryTabs.innerHTML = '';

    desiredOrder.forEach(cat => {
      const btn = document.createElement('button');
      btn.className = 'category-btn';
      btn.type = 'button';
      btn.textContent = cat;
      btn.dataset.category = cat;
      if (!categories.includes(cat)) {
        btn.classList.add('empty-category');
        btn.title = 'No items in this category';
      }
      btn.addEventListener('click', () => {
        currentCategory = cat;
        setActiveCategory(cat);
        renderProducts();
      });
      categoryTabs.appendChild(btn);
    });

    // extras if DB has categories not in desired list
    const extras = categories.filter(c => !desiredOrder.includes(c));
    extras.forEach(cat => {
      const btn = document.createElement('button');
      btn.className = 'category-btn';
      btn.type = 'button';
      btn.textContent = cat;
      btn.dataset.category = cat;
      btn.addEventListener('click', () => {
        currentCategory = cat;
        setActiveCategory(cat);
        renderProducts();
      });
      categoryTabs.appendChild(btn);
    });

    setActiveCategory(currentCategory);
  }

  function setActiveCategory(cat) {
    Array.from(categoryTabs.children).forEach(btn => {
      if (btn.dataset.category === cat) btn.classList.add('active');
      else btn.classList.remove('active');
    });
  }

  // ---------- PRODUCTS ----------
  function renderProducts() {
    const q = (searchBox.value || '').trim().toLowerCase();
    const visible = products.filter(p => {
      if (currentCategory && p.category !== currentCategory) return false;
      if (!q) return true;
      return (p.name && p.name.toLowerCase().includes(q)) ||
             (p.description && p.description.toLowerCase().includes(q));
    });

    foodsGrid.innerHTML = '';
    if (visible.length === 0) {
      const msg = document.createElement('div');
      msg.style.padding = '12px';
      msg.style.color = '#666';
      msg.textContent = 'No products found in this category.';
      foodsGrid.appendChild(msg);
      return;
    }

    visible.forEach(prod => {
      const card = document.createElement('div');
      card.className = 'food-card';
      card.setAttribute('data-id', prod.id);

      const img = document.createElement('img');
      img.src = prod.image || 'assets/placeholder.png';
      img.alt = prod.name || 'Product image';
      card.appendChild(img);

      const label = document.createElement('div');
      label.className = 'food-label';
      label.textContent = prod.name;
      card.appendChild(label);

      const price = document.createElement('div');
      price.className = 'food-price';
      price.textContent = '₱' + (Number(prod.price) || 0).toFixed(2);
      card.appendChild(price);

      const addBtn = document.createElement('button');
      addBtn.type = 'button';
      addBtn.textContent = 'Add';
      addBtn.style.marginTop = '8px';
      addBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        addToOrder(prod);
      });
      card.appendChild(addBtn);

      card.addEventListener('click', () => addToOrder(prod));
      foodsGrid.appendChild(card);
    });
  }

  // ---------- ORDER MANAGEMENT ----------
  function addToOrder(prod) {
    const idx = order.findIndex(i => i.id === prod.id);
    if (idx >= 0) order[idx].qty += 1;
    else order.push({ id: prod.id, name: prod.name, price: Number(prod.price) || 0, qty: 1 });
    renderOrder();
  }
  function removeFromOrder(prodId) {
    order = order.filter(i => i.id !== prodId);
    renderOrder();
  }
  function changeQty(prodId, qty) {
    const idx = order.findIndex(i => i.id === prodId);
    if (idx >= 0) {
      order[idx].qty = Math.max(0, Math.floor(qty));
      if (order[idx].qty === 0) removeFromOrder(prodId);
    }
    renderOrder();
  }

  // ---------- COMPUTATIONS ----------
  function roundCurrency(n) {
    return Math.round((n + Number.EPSILON) * 100) / 100;
  }
  function computeNumbers() {
    const subtotal = order.reduce((s, i) => s + (i.price * i.qty), 0);
    const serviceCharge = subtotal * SERVICE_RATE;
    const tax = subtotal * TAX_RATE;
    const discountAmount = subtotal * (discountRate || 0);
    const payable = subtotal + serviceCharge + tax - discountAmount;
    return {
      subtotal: roundCurrency(subtotal),
      serviceCharge: roundCurrency(serviceCharge),
      tax: roundCurrency(tax),
      discountAmount: roundCurrency(discountAmount),
      payable: roundCurrency(payable)
    };
  }

  // ---------- RENDER ORDER + COMPUTE UI ----------
  function renderOrder() {
    orderList.innerHTML = '';
    if (order.length === 0) {
      orderList.textContent = 'No items in order.';
    } else {
      order.forEach(item => {
        const row = document.createElement('div');
        row.className = 'order-item';

        const name = document.createElement('div');
        name.className = 'order-item-name';
        name.textContent = item.name;
        row.appendChild(name);

        // qty controls
        const qtyWrap = document.createElement('div');
        qtyWrap.style.display = 'flex';
        qtyWrap.style.alignItems = 'center';
        qtyWrap.style.gap = '6px';

        const btnMinus = document.createElement('button');
        btnMinus.type = 'button';
        btnMinus.className = 'order-qty-btn';
        btnMinus.textContent = '−';
        btnMinus.title = 'Decrease';
        btnMinus.addEventListener('click', () => changeQty(item.id, item.qty - 1));
        qtyWrap.appendChild(btnMinus);

        const qtyInput = document.createElement('input');
        qtyInput.className = 'order-qty-input';
        qtyInput.type = 'number';
        qtyInput.value = item.qty;
        qtyInput.min = 0;
        qtyInput.addEventListener('change', () => changeQty(item.id, Number(qtyInput.value)));
        qtyWrap.appendChild(qtyInput);

        const btnPlus = document.createElement('button');
        btnPlus.type = 'button';
        btnPlus.className = 'order-qty-btn';
        btnPlus.textContent = '+';
        btnPlus.title = 'Increase';
        btnPlus.addEventListener('click', () => changeQty(item.id, item.qty + 1));
        qtyWrap.appendChild(btnPlus);

        row.appendChild(qtyWrap);

        const price = document.createElement('div');
        price.textContent = '₱' + (item.price * item.qty).toFixed(2);
        price.style.minWidth = '80px';
        price.style.textAlign = 'right';
        row.appendChild(price);

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'remove-item-btn';
        removeBtn.innerHTML = '×';
        removeBtn.title = 'Remove';
        removeBtn.addEventListener('click', () => removeFromOrder(item.id));
        row.appendChild(removeBtn);

        orderList.appendChild(row);
      });
    }

    // compute and show in orderCompute area
    const nums = computeNumbers();
    orderCompute.innerHTML = '';

    // compute actions (Discount choices & Note)
    const actions = document.createElement('div');
    actions.className = 'compute-actions';

    // Discount button toggles discount panel
    const discountBtn = document.createElement('button');
    discountBtn.className = 'compute-btn discount';
    discountBtn.textContent = 'Discount';
    actions.appendChild(discountBtn);

    // Note button toggles note input
    const noteBtn = document.createElement('button');
    noteBtn.className = 'compute-btn note';
    noteBtn.textContent = 'Note';
    actions.appendChild(noteBtn);

    orderCompute.appendChild(actions);

    // interactive area: discount choices and note input
    const interactiveWrap = document.createElement('div');
    interactiveWrap.style.marginBottom = '8px';

    // Discount choices panel (hidden by default)
    const discountPanel = document.createElement('div');
    discountPanel.style.display = 'none';
    discountPanel.style.gap = '8px';
    discountPanel.style.marginBottom = '8px';

    Object.keys(DISCOUNT_TYPES).forEach(type => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'compute-btn';
      btn.textContent = `${type} ${DISCOUNT_TYPES[type] > 0 ? `(${(DISCOUNT_TYPES[type]*100).toFixed(0)}%)` : ''}`;
      btn.style.marginRight = '6px';
      // mark currently selected
      if (type === discountType) {
        btn.classList.add('active');
      }
      btn.addEventListener('click', () => {
        discountType = type;
        discountRate = DISCOUNT_TYPES[type];
        // refresh UI selection
        Array.from(discountPanel.children).forEach(c => c.classList.remove('active'));
        btn.classList.add('active');
        renderOrder();
      });
      discountPanel.appendChild(btn);
    });

    // Note input
    const noteInput = document.createElement('textarea');
    noteInput.value = noteValue || '';
    noteInput.placeholder = 'Order note...';
    noteInput.style.width = '100%';
    noteInput.style.minHeight = '48px';
    noteInput.style.borderRadius = '6px';
    noteInput.style.border = '1px solid #ccc';
    noteInput.addEventListener('input', () => { noteValue = noteInput.value; });

    // Hide note and discount panels by default
    discountPanel.style.display = 'none';
    noteInput.style.display = 'none';

    interactiveWrap.appendChild(discountPanel);
    interactiveWrap.appendChild(noteInput);
    orderCompute.appendChild(interactiveWrap);

    // Toggle handlers
    discountBtn.addEventListener('click', () => {
      discountPanel.style.display = discountPanel.style.display === 'none' ? 'flex' : 'none';
      noteInput.style.display = 'none';
    });
    noteBtn.addEventListener('click', () => {
      noteInput.style.display = noteInput.style.display === 'none' ? 'block' : 'none';
      discountPanel.style.display = 'none';
    });

    // numeric rows
    function makeRow(label, value, isTotal=false) {
      const r = document.createElement('div');
      r.className = 'compute-row' + (isTotal ? ' total' : '');
      const l = document.createElement('div'); l.className='label'; l.textContent = label;
      const v = document.createElement('div'); v.className='value'; v.textContent = '₱' + Number(value).toFixed(2);
      r.appendChild(l); r.appendChild(v);
      return r;
    }

    orderCompute.appendChild(makeRow('Subtotal', nums.subtotal));
    orderCompute.appendChild(makeRow('Service Charge', nums.serviceCharge));
    orderCompute.appendChild(makeRow('Tax', nums.tax));
    orderCompute.appendChild(makeRow(`Discount (${discountType})`, nums.discountAmount));
    orderCompute.appendChild(makeRow('Payable Amount', nums.payable, true));

    // bottom action buttons: only Proceed (Hold & Bill Out removed per request)
    const btns = document.createElement('div');
    btns.className = 'order-buttons';

    const proceed = document.createElement('button');
    proceed.className = 'proceed-btn';
    proceed.textContent = 'Proceed';
    proceed.addEventListener('click', () => {
      if (order.length === 0) { alert('No items to proceed.'); return; }
      console.log('Proceeding with order:', { order, discountType, discountRate, note: noteValue, compute: computeNumbers() });
      alert('Proceed invoked. Check console for order details.');
    });

    btns.appendChild(proceed);
    orderCompute.appendChild(btns);
  }

  // ---------- DRAFTS (localStorage manager) ----------
  function getLocalDrafts() {
    try {
      const raw = localStorage.getItem('local_drafts') || '[]';
      const arr = JSON.parse(raw);
      if (Array.isArray(arr)) return arr;
      return [];
    } catch (e) {
      console.error('Failed to parse local_drafts', e);
      return [];
    }
  }
  function saveLocalDrafts(arr) {
    localStorage.setItem('local_drafts', JSON.stringify(arr || []));
  }

  // Build and open drafts modal content
  function openDraftsModal() {
    // Build modal HTML inside draftModalContent
    draftModalContent.innerHTML = '';
    // Close button
    const closeBtn = document.createElement('button');
    closeBtn.className = 'close-btn';
    closeBtn.id = 'closeDraftModal_js';
    closeBtn.setAttribute('aria-label', 'Close dialog');
    closeBtn.innerHTML = '&times;';
    closeBtn.addEventListener('click', () => draftModal.classList.add('hidden'));
    draftModalContent.appendChild(closeBtn);

    // Title
    const h3 = document.createElement('h3');
    h3.textContent = 'Drafts';
    draftModalContent.appendChild(h3);

    // Draft list container
    const listWrap = document.createElement('div');
    listWrap.style.maxHeight = '320px';
    listWrap.style.overflowY = 'auto';
    listWrap.style.marginBottom = '10px';
    listWrap.id = 'draftList';
    draftModalContent.appendChild(listWrap);

    // New draft section
    const newLabel = document.createElement('div');
    newLabel.style.margin = '6px 0';
    newLabel.textContent = 'Save current order as draft';
    draftModalContent.appendChild(newLabel);

    const draftNameInputNew = document.createElement('input');
    draftNameInputNew.type = 'text';
    draftNameInputNew.id = 'draftNameInput_js';
    draftNameInputNew.placeholder = 'Draft name or note...';
    draftNameInputNew.style.width = '95%';
    draftNameInputNew.style.marginBottom = '8px';
    draftModalContent.appendChild(draftNameInputNew);

    const saveDraftBtnNew = document.createElement('button');
    saveDraftBtnNew.id = 'saveDraftBtn_js';
    saveDraftBtnNew.type = 'button';
    saveDraftBtnNew.textContent = 'Save Draft';
    saveDraftBtnNew.style.padding = '6px 24px';
    saveDraftBtnNew.style.fontSize = '16px';
    saveDraftBtnNew.style.background = '#d51ecb';
    saveDraftBtnNew.style.color = '#fff';
    saveDraftBtnNew.style.border = 'none';
    saveDraftBtnNew.style.borderRadius = '7px';
    saveDraftBtnNew.style.cursor = 'pointer';
    draftModalContent.appendChild(saveDraftBtnNew);

    // Populate existing drafts
    function refreshDraftList() {
      listWrap.innerHTML = '';
      const drafts = getLocalDrafts();
      if (drafts.length === 0) {
        const p = document.createElement('div');
        p.style.color = '#666';
        p.textContent = 'No drafts saved.';
        listWrap.appendChild(p);
        return;
      }
      drafts.forEach((d, i) => {
        const row = document.createElement('div');
        row.style.display = 'flex';
        row.style.justifyContent = 'space-between';
        row.style.alignItems = 'center';
        row.style.padding = '8px';
        row.style.borderBottom = '1px solid #eee';

        const left = document.createElement('div');
        left.style.flex = '1';
        const name = document.createElement('div');
        name.textContent = d.name || ('Draft ' + (i+1));
        name.style.fontWeight = '600';
        const meta = document.createElement('div');
        meta.textContent = d.created ? new Date(d.created).toLocaleString() : '';
        meta.style.fontSize = '12px';
        meta.style.color = '#666';
        left.appendChild(name);
        left.appendChild(meta);

        const actions = document.createElement('div');
        actions.style.display = 'flex';
        actions.style.gap = '6px';

        const loadBtn = document.createElement('button');
        loadBtn.type = 'button';
        loadBtn.textContent = 'Load';
        loadBtn.style.padding = '6px 10px';
        loadBtn.style.cursor = 'pointer';
        loadBtn.addEventListener('click', () => {
          // load draft into order
          order = Array.isArray(d.order) ? JSON.parse(JSON.stringify(d.order)) : [];
          discountType = d.discountType || 'Regular';
          discountRate = DISCOUNT_TYPES[discountType] || 0;
          noteValue = d.note || '';
          // update UI
          draftModal.classList.add('hidden');
          setActiveCategory(currentCategory);
          renderProducts();
          renderOrder();
        });

        const delBtn = document.createElement('button');
        delBtn.type = 'button';
        delBtn.textContent = 'Delete';
        delBtn.style.padding = '6px 10px';
        delBtn.style.cursor = 'pointer';
        delBtn.style.background = '#ff4d4d';
        delBtn.style.color = '#fff';
        delBtn.addEventListener('click', () => {
          const arr = getLocalDrafts();
          arr.splice(i, 1);
          saveLocalDrafts(arr);
          refreshDraftList();
        });

        actions.appendChild(loadBtn);
        actions.appendChild(delBtn);

        row.appendChild(left);
        row.appendChild(actions);
        listWrap.appendChild(row);
      });
    }

    refreshDraftList();

    // Save draft handler
    saveDraftBtnNew.addEventListener('click', () => {
      const name = (draftNameInputNew.value || '').trim() || ('Draft ' + new Date().toLocaleString());
      const payload = {
        name,
        order: JSON.parse(JSON.stringify(order || [])),
        discountType,
        discountRate,
        note: noteValue,
        created: new Date().toISOString()
      };
      const arr = getLocalDrafts();
      arr.push(payload);
      saveLocalDrafts(arr);
      alert('Draft saved locally.');
      draftNameInputNew.value = '';
      refreshDraftList();
    });

    // show modal
    draftModal.classList.remove('hidden');
  }

  // Hook draft button
  if (draftBtn) {
    draftBtn.addEventListener('click', () => {
      openDraftsModal();
    });
  }
  // fallback close if any existing close element exists
  if (closeDraftModalFallback) {
    closeDraftModalFallback.addEventListener('click', () => draftModal.classList.add('hidden'));
  }

  // ---------- OTHER UI HANDLERS ----------
  if (newOrderBtn) newOrderBtn.addEventListener('click', () => {
    if (confirm('Clear current order and start a new one?')) {
      order = [];
      discountRate = DISCOUNT_TYPES['Regular'];
      discountType = 'Regular';
      noteValue = '';
      renderOrder();
    }
  });

  if (refreshBtn) refreshBtn.addEventListener('click', async () => {
    await loadProducts();
    order = [];
    discountRate = DISCOUNT_TYPES['Regular'];
    discountType = 'Regular';
    noteValue = '';
    renderOrder();
  });

  // ensure proceed button in DOM (if present elsewhere) also works
  const proceedBtnDom = document.querySelector('.proceed-btn');
  if (proceedBtnDom) {
    proceedBtnDom.addEventListener('click', () => {
      if (order.length === 0) { alert('No items to proceed.'); return; }
      console.log('Proceed with order:', { order, discountType, discountRate, note: noteValue, compute: computeNumbers() });
      alert('Proceed invoked. Check console for details.');
    });
  }

  // Escape closes modal
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !draftModal.classList.contains('hidden')) {
      draftModal.classList.add('hidden');
    }
  });

  // Search input
  if (searchBox) {
    let to;
    searchBox.addEventListener('input', () => {
      clearTimeout(to);
      to = setTimeout(() => { renderProducts(); }, 180);
    });
  }

  // initial load
  loadProducts();
});