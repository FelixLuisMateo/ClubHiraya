// script.js - selection + right-panel list + payload/draft handling
// Dragging removed entirely. Only cabins (data-type="cabin") are interactive/clickable.
// Tables and bahaykubo (huts) are made non-interactive: their state buttons are hidden and they are not focusable.

document.addEventListener('DOMContentLoaded', () => {
  const container = document.querySelector('.map-inner');
  if (!container) return;

  // Only cabin elements are interactive
  const cabinElements = Array.from(container.querySelectorAll('.map-object[data-type="cabin"]'));
  // Non-cabin elements should not be clickable/focusable
  const nonInteractive = Array.from(container.querySelectorAll('.map-object:not([data-type="cabin"])'));

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

  // Make non-cabin objects clearly non-interactive
  nonInteractive.forEach(el => {
    // hide any state button if present and prevent pointer/focus
    const btn = el.querySelector('.state-btn');
    if (btn) {
      btn.style.display = 'none';
      btn.setAttribute('aria-hidden', 'true');
    }
    // prevent keyboard focus/clicks
    el.tabIndex = -1;
    el.style.pointerEvents = 'none';
  });

  // Utilities
  function makeId(id) { return String(id); }
  function cssEscape(str) { return String(str).replace(/(["\\])/g, '\\$1'); }

  function createListRow(item) {
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
    if (selected.has(id)) return;
    const data = {
      id,
      type: el.dataset.type || el.getAttribute('data-type') || 'unknown',
      customer: '',
      days: 1
    };
    selected.set(id, data);
    el.classList.add('selected-item');
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

  // Wire up cabin elements only (no dragging)
  cabinElements.forEach(el => {
    el.tabIndex = 0;
    el.setAttribute('role', 'button');
    // clicking element toggles selection (unless click is on inner controls)
    el.addEventListener('click', (ev) => {
      if (ev.target.closest('input') || ev.target.closest('button')) return;
      toggleSelect(el);
    });
    // keyboard support
    el.addEventListener('keydown', (ev) => {
      if (ev.key === 'Enter' || ev.key === ' ') {
        ev.preventDefault();
        toggleSelect(el);
      }
    });

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
  });

  // Modal handling (basic)
  function openModal(title, bodyEl) {
    modalTitle.textContent = title || 'Details';
    modalBody.innerHTML = '';
    modalBody.appendChild(bodyEl);
    modal.style.display = 'flex';
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
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

  let lastFocused = null;
  function trapFocus(root) {
    lastFocused = document.activeElement;
    const focusable = root.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
    if (focusable.length) focusable[0].focus();
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

  // double-click to edit selected list row via modal
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

    const custLabel = document.createElement('label'); custLabel.textContent = 'Customer';
    const custInput = document.createElement('input'); custInput.type = 'text'; custInput.value = data.customer || '';
    const daysLabel = document.createElement('label'); daysLabel.textContent = 'Days';
    const daysInput = document.createElement('input'); daysInput.type = 'number'; daysInput.min = '1'; daysInput.value = data.days || 1;
    const saveBtn = document.createElement('button'); saveBtn.type = 'button'; saveBtn.textContent = 'Save';
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

  // Proceed: validate and submit payload
  proceedBtn.addEventListener('click', (ev) => {
    ev.preventDefault();
    const items = [];
    for (const [id, data] of selected) {
      const days = parseInt(data.days || '0', 10);
      if (!data.customer || !data.customer.trim()) {
        alert(`Please enter customer name for ${id}`);
        return;
      }
      if (!days || days <= 0) {
        alert(`Please enter valid days for ${id}`);
        return;
      }
      items.push({ id: data.id, type: data.type, customer: data.customer, days: days });
    }
    payloadInput.value = JSON.stringify(items);
    proceedBtn.disabled = true;
    form.submit();
    setTimeout(() => proceedBtn.disabled = false, 2000);
  });

  // Enter: mark selected cabins as occupied (visual only)
  enterBtn.addEventListener('click', (ev) => {
    ev.preventDefault();
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

  // Draft button: save selected data to localStorage
  btnDraft && btnDraft.addEventListener('click', (ev) => {
    ev.preventDefault();
    const draft = [];
    for (const [id, data] of selected) {
      draft.push({ id: data.id, type: data.type, customer: data.customer || '', days: data.days || 1 });
    }
    try {
      localStorage.setItem('hiraya_map_draft', JSON.stringify(draft));
      alert('Draft saved locally.');
    } catch (err) {
      console.warn('Could not save draft', err);
      alert('Unable to save draft.');
    }
  });

  // Load draft if present
  function loadDraft() {
    try {
      const raw = localStorage.getItem('hiraya_map_draft');
      if (!raw) return;
      const arr = JSON.parse(raw);
      if (!Array.isArray(arr)) return;
      for (const it of arr) {
        const el = container.querySelector(`.map-object[data-id="${cssEscape(String(it.id))}"]`);
        if (el && el.dataset.type === 'cabin') {
          selected.set(String(it.id), {
            id: String(it.id),
            type: it.type || 'cabin',
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

  // Clear selection helper (wired to the + button)
  const btnAdd = document.getElementById('btnAdd');
  if (btnAdd) {
    btnAdd.addEventListener('click', () => {
      for (const id of Array.from(selected.keys())) deselectItem(id);
    });
  }

  // Sync existing occupied visuals
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

  // Expose small API for debugging
  window.hiraya = {
    selected,
    selectItemById(id) {
      const el = container.querySelector(`.map-object[data-id="${cssEscape(String(id))}"]`);
      if (el && el.dataset.type === 'cabin') selectItem(el);
    },
    deselectItemById(id) { deselectItem(id); },
    getPayload() {
      const items = [];
      for (const [id, data] of selected) items.push(data);
      return items;
    }
  };
});