// Reservation interaction logic + dragging support for map images
// - Dragging: all <img> inside .map-inner are movable except those with class 'background-img'.
// - When dragging an image that sits inside a .map-object, the parent .map-object position is updated (left/top).
// - If the image is inside some other wrapper (e.g. .bar-wrapper), that wrapper will be moved instead.
// - Dragging updates inline style.left and style.top and removes bottom if present (to avoid conflicts).
// - Dragging will not fire click selection: small movement suppresses click behavior.

document.addEventListener('DOMContentLoaded', () => {
  const PRICE = { cabin: 5000, hut: 1000, table: 0 };
  const objects = Array.from(document.querySelectorAll('.map-object'));
  const selectedList = document.getElementById('selectedList');
  const proceedBtn = document.getElementById('proceedBtn');
  const enterBtn = document.getElementById('enterBtn');
  const btnAdd = document.getElementById('btnAdd');
  const btnDraft = document.getElementById('btnDraft');
  const btnRefresh = document.getElementById('btnRefresh');
  const payloadInput = document.getElementById('payload');

  // modal elements
  const modal = document.getElementById('modal');
  const modalBackdrop = document.getElementById('modalBackdrop');
  const modalBody = document.getElementById('modalBody');
  const modalClose = document.getElementById('modalClose');

  // helper: mark button color according to occupied
  function refreshVisual(el) {
    const occupied = el.dataset.occupied === 'true';
    const btn = el.querySelector('.state-btn');
    if (!btn) return;
    if (occupied) {
      el.classList.add('occupied');
      btn.style.background = '#ff6b6b';
      btn.style.color = '#700000';
    } else {
      el.classList.remove('occupied');
      btn.style.background = '#2fe44a';
      btn.style.color = '#003300';
    }
  }

  // initialize visuals
  objects.forEach(o => refreshVisual(o));

  // Keep track of selected-for-action ids
  const selectedIds = new Set();

  // Toggle selection when clicking center button
  document.querySelectorAll('.state-btn').forEach(btn => {
    btn.addEventListener('click', (ev) => {
      ev.stopPropagation();
      const parent = btn.closest('.map-object');
      if (!parent) return;
      const id = parent.dataset.id;
      if (!id) return;
      if (selectedIds.has(id)) {
        selectedIds.delete(id);
        parent.classList.remove('selected-item');
      } else {
        selectedIds.add(id);
        parent.classList.add('selected-item');
      }
    });
  });

  // Also allow clicking the entire object to toggle selection
  objects.forEach(o => {
    o.addEventListener('click', () => {
      const id = o.dataset.id;
      if (!id) return;
      if (selectedIds.has(id)) {
        selectedIds.delete(id);
        o.classList.remove('selected-item');
      } else {
        selectedIds.add(id);
        o.classList.add('selected-item');
      }
    });
  });

  // Render right-side occupied list
  function renderOccupiedList() {
    selectedList.innerHTML = '';
    const occupied = objects.filter(o => o.dataset.occupied === 'true');
    if (occupied.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'item';
      empty.textContent = 'No occupied spots';
      selectedList.appendChild(empty);
      return;
    }
    occupied.forEach(o => {
      const id = o.dataset.id;
      const type = o.dataset.type;
      const cust = o.dataset.customer || '';
      const days = parseInt(o.dataset.days || '0', 10) || 0;
      const price = PRICE[type] * days;
      const item = document.createElement('div');
      item.className = 'item';
      item.innerHTML = `<div>&gt; ${id} ${type.toUpperCase()}</div><div><small>${cust ? cust : ''}</small>${price ? ' ₱' + price.toLocaleString() : ''}</div>`;
      // clicking row shows details
      item.addEventListener('click', () => {
        if (cust || days) {
          showModal(`<p><strong>${id}</strong> (${type})<br>Customer: ${cust || '—'}<br>Days: ${days || '—'}<br>Price: ${price ? '₱' + price.toLocaleString() : '—'}</p>`);
        } else {
          showModal(`<p><strong>${id}</strong> (${type})<br>No customer recorded.</p>`);
        }
      });
      selectedList.appendChild(item);
    });
  }

  // Proceed: occupy selected available cabins/huts (and tables can be reserved but they have no price)
  proceedBtn.addEventListener('click', () => {
    if (selectedIds.size === 0) {
      alert('Select slots (green) on the map to reserve, then click Proceed.');
      return;
    }
    // gather selected elements
    const elems = Array.from(selectedIds).map(id => objects.find(o => o.dataset.id === id)).filter(Boolean);
    const availableElems = elems.filter(e => e.dataset.occupied !== 'true');
    if (availableElems.length === 0) {
      alert('No available items selected to reserve.');
      return;
    }

    // Prompt once for customer name and days (days only relevant for cabins/huts; for tables days is ignored)
    const cust = prompt(`Enter customer name for ${availableElems.length} selected item(s):`, '') || '';
    let days = 1;
    // If any selected is cabin or hut, ask days
    if (availableElems.some(e => e.dataset.type === 'cabin' || e.dataset.type === 'hut')) {
      const d = prompt('How many days will they stay? (enter a number)', '1');
      days = Math.max(1, parseInt(d, 10) || 1);
    }

    // Occupy each selected available element
    availableElems.forEach(el => {
      el.dataset.occupied = 'true';
      if (cust.trim()) el.dataset.customer = cust.trim();
      if (el.dataset.type === 'cabin' || el.dataset.type === 'hut') el.dataset.days = String(days);
      refreshVisual(el);
      // remove selection
      selectedIds.delete(el.dataset.id);
      el.classList.remove('selected-item');
    });

    renderOccupiedList();

    // Also set payload for server (optional) - we'll set JSON of current occupied spots
    const occupiedPayload = objects.filter(o => o.dataset.occupied === 'true').map(o => ({
      id: o.dataset.id,
      type: o.dataset.type,
      customer: o.dataset.customer || '',
      days: parseInt(o.dataset.days || '0', 10) || 0
    }));
    payloadInput.value = JSON.stringify(occupiedPayload);
  });

  // Enter: checkout selected occupied cabins/huts (clears them)
  enterBtn.addEventListener('click', () => {
    if (selectedIds.size === 0) {
      alert('Select occupied slots you want to check out, then click Enter.');
      return;
    }
    const elems = Array.from(selectedIds).map(id => objects.find(o => o.dataset.id === id)).filter(Boolean);
    const occupiedSelected = elems.filter(e => e.dataset.occupied === 'true');
    if (occupiedSelected.length === 0) {
      alert('No occupied items selected to check out.');
      return;
    }
    if (!confirm(`Check out ${occupiedSelected.length} occupied item(s)?`)) return;
    occupiedSelected.forEach(el => {
      el.dataset.occupied = 'false';
      delete el.dataset.customer;
      delete el.dataset.days;
      refreshVisual(el);
      selectedIds.delete(el.dataset.id);
      el.classList.remove('selected-item');
    });
    renderOccupiedList();

    // update payload for server if needed
    const occupiedPayload = objects.filter(o => o.dataset.occupied === 'true').map(o => ({
      id: o.dataset.id,
      type: o.dataset.type,
      customer: o.dataset.customer || '',
      days: parseInt(o.dataset.days || '0', 10) || 0
    }));
    payloadInput.value = JSON.stringify(occupiedPayload);
  });

  // Add New - clear everything
  btnAdd.addEventListener('click', () => {
    if (!confirm('Clear all reservations and reset map?')) return;
    objects.forEach(o => {
      o.dataset.occupied = 'false';
      delete o.dataset.customer;
      delete o.dataset.days;
      o.classList.remove('selected-item');
      selectedIds.delete(o.dataset.id);
      refreshVisual(o);
    });
    renderOccupiedList();
    payloadInput.value = '';
  });

  // Draft - save snapshot to localStorage
  btnDraft.addEventListener('click', () => {
    const snapshot = objects.map(o => ({
      id: o.dataset.id,
      type: o.dataset.type,
      occupied: o.dataset.occupied === 'true',
      customer: o.dataset.customer || '',
      days: parseInt(o.dataset.days || '0', 10) || 0,
      // record current inline position so draft preserves moved positions
      left: o.style.left || '',
      top: o.style.top || '',
      bottom: o.style.bottom || ''
    }));
    localStorage.setItem('hiraya_reservation_draft', JSON.stringify(snapshot));
    btnDraft.classList.add('saved');
    setTimeout(() => btnDraft.classList.remove('saved'), 800);
    alert('Draft saved locally.');
  });

  // Refresh
  btnRefresh.addEventListener('click', () => location.reload());

  // Modal helpers
  function showModal(html) {
    modalBody.innerHTML = html;
    modal.style.display = 'flex';
    modal.setAttribute('aria-hidden', 'false');
  }
  function hideModal() {
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden', 'true');
  }
  modalBackdrop.addEventListener('click', hideModal);
  modalClose.addEventListener('click', hideModal);

  // Restore draft if exists
  const draft = localStorage.getItem('hiraya_reservation_draft');
  if (draft) {
    try {
      const parsed = JSON.parse(draft);
      parsed.forEach(item => {
        const el = objects.find(o => o.dataset.id === item.id);
        if (!el) return;
        el.dataset.occupied = item.occupied ? 'true' : 'false';
        if (item.customer) el.dataset.customer = item.customer;
        if (item.days) el.dataset.days = String(item.days);
        // restore saved position if present
        if (item.left) el.style.left = item.left;
        if (item.top) el.style.top = item.top;
        if (item.bottom) el.style.bottom = item.bottom;
        refreshVisual(el);
      });
      renderOccupiedList();
    } catch (e) { /* ignore parse errors */ }
  } else {
    renderOccupiedList();
  }

  // Submit handler - if you click the hidden form submission later, payloadInput already contains occupied JSON
  document.getElementById('proceedForm').addEventListener('submit', (e) => {
    // ensure payload is updated
    const occupiedPayload = objects.filter(o => o.dataset.occupied === 'true').map(o => ({
      id: o.dataset.id,
      type: o.dataset.type,
      customer: o.dataset.customer || '',
      days: parseInt(o.dataset.days || '0', 10) || 0
    }));
    payloadInput.value = JSON.stringify(occupiedPayload);
  });


  /* -----------------------------
     Drag / Move support for images
     -----------------------------
     Behavior:
     - All <img> inside .map-inner are movable EXCEPT images with class 'background-img'.
     - When dragging an <img>, we move its closest positioned container:
         * prefer closest('.map-object'), otherwise closest('.bar-wrapper'), otherwise the image itself.
     - We update style.left and style.top of that container (remove bottom if present).
     - Small drags (<6px) will not trigger click selection.
  */

  const mapInner = document.querySelector('.map-inner');
  if (!mapInner) return;

  let dragState = null; // {container, img, startX, startY, offsetX, offsetY, moved}

  function getContainerForImage(img) {
    const mapObj = img.closest('.map-object');
    if (mapObj) return mapObj;
    const wrapper = img.closest('.bar-wrapper');
    if (wrapper) return wrapper;
    return img;
  }

  function onPointerDown(ev) {
    // support touch and mouse
    const isTouch = ev.type === 'touchstart';
    const point = isTouch ? ev.touches[0] : ev;
    const target = ev.target;
    if (!(target instanceof HTMLImageElement)) return;
    if (!mapInner.contains(target)) return;
    if (target.classList.contains('background-img')) return; // skip background images

    ev.preventDefault && ev.preventDefault();

    const img = target;
    const container = getContainerForImage(img);
    const rect = mapInner.getBoundingClientRect();
    const contRect = container.getBoundingClientRect();

    // calculate cursor offset within container
    const offsetX = point.clientX - contRect.left;
    const offsetY = point.clientY - contRect.top;

    dragState = {
      container,
      img,
      startX: point.clientX,
      startY: point.clientY,
      offsetX,
      offsetY,
      moved: false,
      mapRect: rect
    };

    img.classList.add('dragging');

    // attach move/up listeners on document to track outside the element
    if (isTouch) {
      document.addEventListener('touchmove', onPointerMove, { passive: false });
      document.addEventListener('touchend', onPointerUp);
      document.addEventListener('touchcancel', onPointerUp);
    } else {
      document.addEventListener('mousemove', onPointerMove);
      document.addEventListener('mouseup', onPointerUp);
    }
  }

  function onPointerMove(ev) {
    if (!dragState) return;
    const isTouch = ev.type === 'touchmove';
    const point = isTouch ? ev.touches[0] : ev;
    ev.preventDefault && ev.preventDefault();

    const dx = point.clientX - dragState.startX;
    const dy = point.clientY - dragState.startY;
    if (!dragState.moved && Math.hypot(dx, dy) > 3) dragState.moved = true;

    // compute new position relative to mapInner
    const mapRect = dragState.mapRect;
    const newLeft = point.clientX - mapRect.left - dragState.offsetX;
    const newTop = point.clientY - mapRect.top - dragState.offsetY;

    // apply to container
    dragState.container.style.left = Math.round(newLeft) + 'px';
    dragState.container.style.top = Math.round(newTop) + 'px';
    // remove bottom if present (to avoid mixing top and bottom)
    dragState.container.style.removeProperty('bottom');
  }

  function onPointerUp(ev) {
    if (!dragState) return;
    const wasMoved = dragState.moved;
    dragState.img.classList.remove('dragging');

    // cleanup listeners
    document.removeEventListener('mousemove', onPointerMove);
    document.removeEventListener('mouseup', onPointerUp);
    document.removeEventListener('touchmove', onPointerMove);
    document.removeEventListener('touchend', onPointerUp);
    document.removeEventListener('touchcancel', onPointerUp);

    // If we moved, suppress a following click event that might select the object.
    if (wasMoved) {
      // temporarily block click on the dragged image and its container
      const blockClickHandler = function (e) {
        e.stopImmediatePropagation();
        e.preventDefault && e.preventDefault();
      };
      dragState.img.addEventListener('click', blockClickHandler, { once: true, capture: true });
      const cont = dragState.container;
      cont.addEventListener('click', blockClickHandler, { once: true, capture: true });
    }

    // Save new inline position to dataset (optional) so Draft can persist positions
    // We'll store current left/top as inline style already; draft saves style.left/style.top.
    dragState = null;
  }

  // attach pointerdown on each image inside mapInner except those marked background-img
  function attachDraggables() {
    const imgs = Array.from(mapInner.querySelectorAll('img'));
    imgs.forEach(img => {
      // skip explicit background images
      if (img.classList.contains('background-img')) return;
      // ensure pointerdown handler not added multiple times
      img.addEventListener('mousedown', onPointerDown);
      img.addEventListener('touchstart', onPointerDown, { passive: false });
    });
  }

  attachDraggables();

  // If you dynamically add images later, call attachDraggables() again.

  /* -----------------------------
     End of drag support
     ----------------------------- */

});