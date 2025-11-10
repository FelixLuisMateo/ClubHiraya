// script.js - enhanced for dragging + selection + right-panel list + payload/draft handling
// Replaces earlier simpler drag-only script with selection and form integration.

document.addEventListener('DOMContentLoaded', () => {
  const container = document.querySelector('.map-inner');
  if (!container) return;

  const objects = Array.from(container.querySelectorAll('.map-object'));
  const selectedListEl = document.getElementById('selectedList');
  const payloadInput = document.getElementById('payload');
  const proceedBtn = document.getElementById('proceedBtn');
  const enterBtn = document.getElementById('enterBtn');
  const btnDraft = document.getElementById('btnDraft');
  const modal = document.getElementById('modal');
  const modalBackdrop = document.getElementById('modalBackdrop');
  const modalClose = document.getElementById('modalClose');
  const modalBody = document.getElementById('modalBody');
  const modalTitle = document.getElementById('modalTitle');
  const form = document.getElementById('proceedForm');

  // Track selected items (Map id => data object)
  const selected = new Map();

  // --- Utilities ---
  function makeId(id) { return String(id); }

  function createListRow(item) {
    // item: { id, type, customer, days }
    const row = document.createElement('div');
    row.className = 'item';
    row.dataset.id = item.id;

    const left = document.createElement('div');
    left.style.display = 'flex';
    left.style.flexDirection = 'column';
    left.style.gap = '6px';
    left.style.minWidth = '0';

    const title = document.createElement('div');
    title.textContent = `${item.id} (${item.type})`;
    title.style.fontWeight = '900';
    left.appendChild(title);

    const smallRow = document.createElement('div');
    smallRow.style.display = 'flex';
    smallRow.style.gap = '8px';
    smallRow.style.alignItems = 'center';

    const custInput = document.createElement('input');
    custInput.type = 'text';
    custInput.placeholder = 'Customer';
    custInput.value = item.customer || '';
    custInput.style.flex = '1';
    custInput.style.padding = '6px';
    custInput.style.borderRadius = '6px';
    custInput.style.border = '1px solid #dcdcdc';
    custInput.addEventListener('input', () => {
      const s = selected.get(item.id);
      if (s) s.customer = custInput.value;
    });

    const daysInput = document.createElement('input');
    daysInput.type = 'number';
    daysInput.min = '1';
    daysInput.placeholder = 'Days';
    daysInput.value = item.days || '';
    daysInput.style.width = '82px';
    daysInput.style.padding = '6px';
    daysInput.style.borderRadius = '6px';
    daysInput.style.border = '1px solid #dcdcdc';
    daysInput.addEventListener('input', () => {
      const s = selected.get(item.id);
      if (s) s.days = parseInt(daysInput.value || '0', 10);
    });

    smallRow.appendChild(custInput);
    smallRow.appendChild(daysInput);

    left.appendChild(smallRow);

    const right = document.createElement('div');
    right.style.display = 'flex';
    right.style.flexDirection = 'column';
    right.style.gap = '8px';
    right.style.alignItems = 'flex-end';

    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.textContent = 'Remove';
    removeBtn.style.background = '#eee';
    removeBtn.style.border = '0';
    removeBtn.style.padding = '8px';
    removeBtn.style.borderRadius = '8px';
    removeBtn.style.cursor = 'pointer';
    removeBtn.addEventListener('click', () => {
      deselectItem(item.id);
    });

    right.appendChild(removeBtn);

    row.appendChild(left);
    row.appendChild(right);

    return row;
  }

  function renderSelectedList() {
    selectedListEl.innerHTML = '';
    for (const [id, data] of selected) {
      const row = createListRow(data);
      selectedListEl.appendChild(row);
    }
  }

  function selectItem(el) {
    const id = makeId(el.dataset.id || el.getAttribute('data-id'));
    if (!id) return;
    if (selected.has(id)) return; // already selected
    const data = {
      id,
      type: el.dataset.type || el.getAttribute('data-type') || 'unknown',
      customer: '',
      days: 1
    };
    selected.set(id, data);
    el.classList.add('selected-item');
    // aria
    const btn = el.querySelector('.state-btn');
    if (btn) btn.setAttribute('aria-pressed', 'true');
    renderSelectedList();
  }

  function deselectItem(id) {
    const key = makeId(id);
    if (!selected.has(key)) return;
    selected.delete(key);
    const el = container.querySelector(`.map-object[data-id="${cssEscape(key)}"]`);
    if (el) {
      el.classList.remove('selected-item');
      const btn = el.querySelector('.state-btn');
      if (btn) btn.setAttribute('aria-pressed', 'false');
    }
    renderSelectedList();
  }

  function toggleSelect(el) {
    const id = makeId(el.dataset.id || el.getAttribute('data-id'));
    if (!id) return;
    if (selected.has(id)) deselectItem(id);
    else selectItem(el);
  }

  function cssEscape(str) {
    // minimal escape for querySelector attribute selector usage
    return String(str).replace(/(["\\])/g, '\\$1');
  }

  // --- Dragging (pointer events) ---
  objects.forEach(el => {
    // make interactive and focusable
    el.style.touchAction = 'none';
    el.tabIndex = 0;
    el.setAttribute('role', 'button');

    // Avoid starting drag when user clicks on the small state button or on inputs
    el.addEventListener('pointerdown', function (e) {
      // If click is on the state button or inside a button, treat as selection/click only
      if (e.target.closest('.state-btn') || e.target.closest('button') || e.target.closest('input')) {
        return; // let other handlers manage it
      }
      // Only primary pointer to drag
      if (e.button && e.button !== 0) return;
      startDrag(e, el);
    });

    // state button toggles selection
    const stateBtn = el.querySelector('.state-btn');
    if (stateBtn) {
      stateBtn.type = 'button';
      stateBtn.tabIndex = 0;
      stateBtn.setAttribute('aria-pressed', 'false');
      stateBtn.addEventListener('click', (ev) => {
        ev.stopPropagation();
        toggleSelect(el);
      });
      stateBtn.addEventListener('keydown', (ev) => {
        if (ev.key === 'Enter' || ev.key === ' ') {
          ev.preventDefault();
          toggleSelect(el);
        }
      });
    }

    // allow keyboard activation on the whole element
    el.addEventListener('keydown', (ev) => {
      if (ev.key === 'Enter' || ev.key === ' ') {
        ev.preventDefault();
        toggleSelect(el);
      }
    });

    // also allow click on the element to toggle selection
    el.addEventListener('click', (ev) => {
      // ignore clicks originated from state button (already handled)
      if (ev.target.closest('.state-btn')) return;
      toggleSelect(el);
    });
  });

  function startDrag(e, el) {
    el.setPointerCapture(e.pointerId);
    el.classList.add('dragging');

    const parentRect = container.getBoundingClientRect();
    let elRect = el.getBoundingClientRect();

    const offsetX = e.clientX - elRect.left;
    const offsetY = e.clientY - elRect.top;

    function onMove(ev) {
      ev.preventDefault();
      elRect = el.getBoundingClientRect(); // recalc for responsive
      const x = ev.clientX - parentRect.left - offsetX;
      const y = ev.clientY - parentRect.top - offsetY;

      const maxX = Math.max(0, parentRect.width - elRect.width);
      const maxY = Math.max(0, parentRect.height - elRect.height);
      const nx = Math.max(0, Math.min(x, maxX));
      const ny = Math.max(0, Math.min(y, maxY));

      el.style.left = nx + 'px';
      el.style.top = ny + 'px';
      el.style.bottom = '';
    }

    function onUp() {
      try { el.releasePointerCapture(e.pointerId); } catch (_) {}
      el.classList.remove('dragging');
      document.removeEventListener('pointermove', onMove);
      document.removeEventListener('pointerup', onUp);
      document.removeEventListener('pointercancel', onUp);
    }

    document.addEventListener('pointermove', onMove, { passive: false });
    document.addEventListener('pointerup', onUp);
    document.addEventListener('pointercancel', onUp);
  }

  // --- Modal handling (simple accessibility features) ---
  function openModal(title, bodyEl) {
    modalTitle.textContent = title || 'Details';
    modalBody.innerHTML = '';
    modalBody.appendChild(bodyEl);
    modal.style.display = 'flex';
    modal.setAttribute('aria-hidden', 'false');
    modal.querySelector('.modal-content').focus();
    document.body.style.overflow = 'hidden';
    // trap focus
    trapFocus(modal);
  }

  function closeModal() {
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    releaseTrapFocus();
  }

  modalBackdrop.addEventListener('click', closeModal);
  modalClose.addEventListener('click', closeModal);
  document.addEventListener('keydown', (ev) => {
    if (ev.key === 'Escape' && modal.style.display === 'flex') closeModal();
  });

  // focus trap (very small) - keeps tab cycling inside modal
  let lastFocused = null;
  function trapFocus(root) {
    lastFocused = document.activeElement;
    const focusable = root.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
    if (focusable.length) {
      focusable[0].focus();
    } else {
      root.querySelector('.modal-content').focus();
    }
    // add keydown handler
    root._trapHandler = function (ev) {
      if (ev.key !== 'Tab') return;
      const nodes = Array.from(root.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'))
        .filter(n => !n.disabled && n.offsetParent !== null);
      if (!nodes.length) return;
      const first = nodes[0];
      const last = nodes[nodes.length - 1];
      if (ev.shiftKey && document.activeElement === first) {
        ev.preventDefault();
        last.focus();
      } else if (!ev.shiftKey && document.activeElement === last) {
        ev.preventDefault();
        first.focus();
      }
    };
    root.addEventListener('keydown', root._trapHandler);
  }
  function releaseTrapFocus() {
    if (modal && modal._trapHandler) {
      modal.removeEventListener('keydown', modal._trapHandler);
      modal._trapHandler = null;
    }
    if (lastFocused) lastFocused.focus();
    lastFocused = null;
  }

  // Example: open modal to edit a single selected item
  // double-click an item in the selected-list row to open modal for that item
  selectedListEl.addEventListener('dblclick', (ev) => {
    const row = ev.target.closest('.item');
    if (!row) return;
    const id = row.dataset.id;
    const data = selected.get(id);
    if (!data) return;

    const body = document.createElement('div');
    body.style.display = 'flex';
    body.style.flexDirection = 'column';
    body.style.gap = '8px';

    const custLabel = document.createElement('label');
    custLabel.textContent = 'Customer';
    const custInput = document.createElement('input');
    custInput.type = 'text';
    custInput.value = data.customer || '';
    custInput.style.padding = '6px';

    const daysLabel = document.createElement('label');
    daysLabel.textContent = 'Days';
    const daysInput = document.createElement('input');
    daysInput.type = 'number';
    daysInput.min = '1';
    daysInput.value = data.days || 1;
    daysInput.style.padding = '6px';

    const saveBtn = document.createElement('button');
    saveBtn.type = 'button';
    saveBtn.textContent = 'Save';
    saveBtn.style.marginTop = '10px';
    saveBtn.addEventListener('click', () => {
      data.customer = custInput.value;
      data.days = parseInt(daysInput.value || '0', 10);
      renderSelectedList();
      closeModal();
    });

    body.appendChild(custLabel);
    body.appendChild(custInput);
    body.appendChild(daysLabel);
    body.appendChild(daysInput);
    body.appendChild(saveBtn);

    openModal(`Edit ${data.id}`, body);
  });

  // --- Proceed / Enter / Draft logic ---
  proceedBtn.addEventListener('click', (ev) => {
    ev.preventDefault();
    const items = [];
    for (const [id, data] of selected) {
      // basic validation
      const days = parseInt(data.days || '0', 10);
      if (!data.customer || !data.customer.trim()) {
        alert(`Please enter customer name for ${id}`);
        return;
      }
      if (!days || days <= 0) {
        alert(`Please enter valid days for ${id}`);
        return;
      }
      items.push({
        id: data.id,
        type: data.type,
        customer: data.customer,
        days: days
      });
    }
    // serialize and submit
    payloadInput.value = JSON.stringify(items);
    // short disable to prevent double submit
    proceedBtn.disabled = true;
    form.submit();
    setTimeout(() => proceedBtn.disabled = false, 2000);
  });

  enterBtn.addEventListener('click', (ev) => {
    ev.preventDefault();
    // Quick enter: mark selected items as occupied and clear selection
    for (const [id, data] of selected) {
      const el = container.querySelector(`.map-object[data-id="${cssEscape(id)}"]`);
      if (el) {
        el.dataset.occupied = 'true';
        el.classList.add('occupied');
        const btn = el.querySelector('.state-btn');
        if (btn) {
          btn.style.background = 'var(--red)';
          btn.style.color = '#700000';
          btn.setAttribute('aria-pressed', 'true');
        }
      }
    }
    selected.clear();
    renderSelectedList();
  });

  btnDraft && btnDraft.addEventListener('click', (ev) => {
    ev.preventDefault();
    const draft = [];
    for (const [id, data] of selected) {
      draft.push({
        id: data.id,
        type: data.type,
        customer: data.customer || '',
        days: data.days || 1
      });
    }
    try {
      localStorage.setItem('hiraya_map_draft', JSON.stringify(draft));
      alert('Draft saved locally.');
    } catch (err) {
      console.warn('Could not save draft', err);
      alert('Unable to save draft.');
    }
  });

  // load draft on start
  function loadDraft() {
    try {
      const raw = localStorage.getItem('hiraya_map_draft');
      if (!raw) return;
      const arr = JSON.parse(raw);
      if (!Array.isArray(arr)) return;
      for (const it of arr) {
        const el = container.querySelector(`.map-object[data-id="${cssEscape(String(it.id))}"]`);
        if (el) {
          selected.set(String(it.id), {
            id: String(it.id),
            type: it.type || el.dataset.type || 'unknown',
            customer: it.customer || '',
            days: it.days || 1
          });
          el.classList.add('selected-item');
          const btn = el.querySelector('.state-btn');
          if (btn) btn.setAttribute('aria-pressed', 'true');
        }
      }
      renderSelectedList();
    } catch (err) {
      console.warn('Could not load draft', err);
    }
  }
  loadDraft();

  // Clear selection helper (wired to the top-left + button if you want)
  const btnAdd = document.getElementById('btnAdd');
  if (btnAdd) {
    btnAdd.addEventListener('click', () => {
      // Clear all selections
      for (const id of Array.from(selected.keys())) deselectItem(id);
    });
  }

  // Make sure any element that was programmatically marked occupied has visual style
  (function syncOccupiedVisuals() {
    const occ = container.querySelectorAll('.map-object[data-occupied="true"]');
    occ.forEach(el => {
      el.classList.add('occupied');
      const btn = el.querySelector('.state-btn');
      if (btn) {
        btn.style.background = 'var(--red)';
        btn.style.color = '#700000';
        btn.setAttribute('aria-pressed', 'true');
      }
    });
  })();

  // expose small API on window for debugging / manual use
  window.hiraya = {
    selected,
    selectItemById(id) {
      const el = container.querySelector(`.map-object[data-id="${cssEscape(String(id))}"]`);
      if (el) selectItem(el);
    },
    deselectItemById(id) { deselectItem(id); },
    getPayload() {
      const items = [];
      for (const [id, data] of selected) items.push(data);
      return items;
    }
  };
});