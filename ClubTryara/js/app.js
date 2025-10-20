/**
 * app.js - enhanced and ready to paste
 * - Attempts to fetch products from php/get_products.php; falls back to sample data if not available.
 * - Renders categories and products, supports search, adds/removes items, quantity controls.
 * - Handles draft modal (save to server if available, otherwise logs to console).
 * - Includes small UX improvements: Esc to close modals, refresh/new order buttons, handlers for Bill Out / Proceed.
 */

document.addEventListener('DOMContentLoaded', () => {
  // DOM refs
  const foodsGrid = document.getElementById('foodsGrid');
  const categoryTabs = document.getElementById('categoryTabs');
  const searchBox = document.getElementById('searchBox');
  const orderList = document.getElementById('orderList');
  const orderCompute = document.getElementById('orderCompute');

  const draftModal = document.getElementById('draftModal');
  const draftBtn = document.getElementById('draftBtn');
  const closeDraftModal = document.getElementById('closeDraftModal');
  const saveDraftBtn = document.getElementById('saveDraftBtn');
  const draftNameInput = document.getElementById('draftNameInput');

  const newOrderBtn = document.getElementById('newOrderBtn');
  const refreshBtn = document.getElementById('refreshBtn');
  const billOutBtn = document.getElementById('billOutBtn');

  let products = [];
  let categories = [];
  let currentCategory = null;
  let order = [];

  // Fetch products from server; fallback to sample data
  async function loadProducts() {
    try {
      const res = await fetch('php/get_products.php', { cache: 'no-store' });
      if (!res.ok) throw new Error('Server returned ' + res.status);
      const data = await res.json();
      products = Array.isArray(data) ? data : [];
    } catch (err) {
      console.warn('Failed to load php/get_products.php. Falling back to sample data.', err);
      products = [
        { id: 1, name: 'Adobo', price: 150, category: 'Main', image: 'assets/adobo.jpg' },
        { id: 2, name: 'Sinigang', price: 140, category: 'Main', image: 'assets/sinigang.jpg' },
        { id: 3, name: 'Halo-Halo', price: 80, category: 'Dessert', image: 'assets/halohalo.jpg' },
        { id: 4, name: 'Lumpia', price: 70, category: 'Appetizer', image: 'assets/lumpia.jpg' },
        // Add extra samples so layout is visible
        { id: 5, name: 'Kare-Kare', price: 180, category: 'Main', image: 'assets/karekare.jpg' },
        { id: 6, name: 'Leche Flan', price: 60, category: 'Dessert', image: 'assets/lecheflan.jpg' }
      ];
    }
    buildCategoryList();
    renderProducts();
  }

  function buildCategoryList() {
    const set = new Set(products.map(p => p.category || 'Uncategorized'));
    categories = Array.from(set);
    renderCategoryTabs();
  }

  function renderCategoryTabs() {
    categoryTabs.innerHTML = '';
    const allBtn = document.createElement('button');
    allBtn.className = 'category-btn';
    allBtn.type = 'button';
    allBtn.textContent = 'All';
    allBtn.addEventListener('click', () => { currentCategory = null; setActiveCategory(null); renderProducts(); });
    categoryTabs.appendChild(allBtn);

    categories.forEach(cat => {
      const btn = document.createElement('button');
      btn.className = 'category-btn';
      btn.type = 'button';
      btn.textContent = cat;
      btn.addEventListener('click', () => { currentCategory = cat; setActiveCategory(cat); renderProducts(); });
      categoryTabs.appendChild(btn);
    });

    // if categories exist, set first as active visually (All remains default)
    setActiveCategory(null);
  }

  function setActiveCategory(cat) {
    Array.from(categoryTabs.children).forEach(btn => {
      if (cat === null && btn.textContent === 'All') {
        btn.classList.add('active');
      } else if (btn.textContent === cat) {
        btn.classList.add('active');
      } else {
        btn.classList.remove('active');
      }
    });
  }

  function renderProducts() {
    const q = (searchBox.value || '').trim().toLowerCase();
    const visible = products.filter(p => {
      if (currentCategory && p.category !== currentCategory) return false;
      if (!q) return true;
      return p.name.toLowerCase().includes(q) || (p.category && p.category.toLowerCase().includes(q));
    });

    foodsGrid.innerHTML = '';
    if (visible.length === 0) {
      const msg = document.createElement('div');
      msg.style.padding = '12px';
      msg.style.color = '#666';
      msg.textContent = 'No products found.';
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

      foodsGrid.appendChild(card);
    });
  }

  function addToOrder(prod) {
    const index = order.findIndex(i => i.id === prod.id);
    if (index >= 0) {
      order[index].qty += 1;
    } else {
      order.push({ id: prod.id, name: prod.name, price: Number(prod.price) || 0, qty: 1 });
    }
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

  function renderOrder() {
    orderList.innerHTML = '';
    if (order.length === 0) {
      orderList.textContent = 'No items in order.';
      orderCompute.textContent = '';
      return;
    }
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

    const total = order.reduce((s, i) => s + i.price * i.qty, 0);
    orderCompute.innerHTML = `<div class="compute-row total">Total <strong>₱${total.toFixed(2)}</strong></div>`;
  }

  // Draft modal handling
  function openDraftModal() {
    draftModal.classList.remove('hidden');
    draftNameInput.focus();
  }
  function closeDraftModalFn() {
    draftModal.classList.add('hidden');
  }

  if (draftBtn) draftBtn.addEventListener('click', () => openDraftModal());
  if (closeDraftModal) closeDraftModal.addEventListener('click', () => closeDraftModalFn());

  // Save draft (tries server, falls back to console)
  if (saveDraftBtn) saveDraftBtn.addEventListener('click', async () => {
    const name = (draftNameInput.value || '').trim();
    const payload = { name, order, timestamp: new Date().toISOString() };
    try {
      const res = await fetch('php/save_draft.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      if (!res.ok) throw new Error('Server rejected draft');
      alert('Draft saved successfully.');
      closeDraftModalFn();
    } catch (err) {
      console.warn('Save draft failed, storing locally (console).', err);
      console.log('Draft payload:', payload);
      alert('Draft saved locally (see console).');
      closeDraftModalFn();
    }
  });

  // New order (clears)
  if (newOrderBtn) newOrderBtn.addEventListener('click', () => {
    if (confirm('Clear current order and start a new one?')) {
      order = [];
      renderOrder();
    }
  });

  // Refresh button: reload products and reset UI
  if (refreshBtn) refreshBtn.addEventListener('click', async () => {
    await loadProducts();
    order = [];
    renderOrder();
  });

  // Bill Out & Proceed (demo hooks)
  if (billOutBtn) billOutBtn.addEventListener('click', () => {
    if (order.length === 0) { alert('No items to bill.'); return; }
    // In a real app send order to server & navigate to billing screen
    console.log('Bill out order:', order);
    alert('Bill out invoked. Check console for order details.');
  });

  const proceedBtn = document.querySelector('.proceed-btn');
  if (proceedBtn) proceedBtn.addEventListener('click', () => {
    if (order.length === 0) { alert('No items to proceed.'); return; }
    console.log('Proceed with order:', order);
    alert('Proceed invoked. Check console for order details.');
  });

  // Close modal on Escape
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !draftModal.classList.contains('hidden')) {
      closeDraftModalFn();
    }
  });

  // Search input
  if (searchBox) {
    searchBox.addEventListener('input', debounce(() => renderProducts(), 180));
  }

  // Utility: simple debounce
  function debounce(fn, wait = 150) {
    let t;
    return (...args) => {
      clearTimeout(t);
      t = setTimeout(() => fn(...args), wait);
    };
  }

  // initial load
  loadProducts();

});