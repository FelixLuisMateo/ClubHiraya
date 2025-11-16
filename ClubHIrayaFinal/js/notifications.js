// notifications.js
// Frontend notifications module for Club Hiraya admin pages
// - Reads settings.notifications from localStorage (or Settings.js if you already set it)
// - Polls for new orders and low-stock items
// - Plays sounds (click + alert) when enabled
// - Provides simple toasts

(function(){
  const DEFAULT_POLL_INTERVAL_MS = 8000; // 8s for orders/stock polling
  const ORDER_POLL_URL = 'api/get_new_orders.php'; // needs server endpoint (see notes)
  const LOW_STOCK_POLL_URL = 'api/get_low_stock.php'; // needs server endpoint (see notes)
  const NOTIF_KEY = 'clubtryara_notifications';
  const audioClickSrc = 'assets/sounds/click.mp3'; // put a small click file here
  const audioAlertSrc = 'assets/sounds/alert.mp3'; // put an alert sound file here

  // util: load settings from localStorage (or from Settings.js exported object if you use it)
  function loadSettings() {
    try {
      // prefer a global Settings object if present
      if (window.loadSettings && typeof window.loadSettings === 'function') {
        return window.loadSettings().notifications || { sound: false, orderAlerts: false, lowStockAlerts: false };
      }
    } catch (e) {}
    const raw = localStorage.getItem('appSettings');
    if (!raw) return { sound: false, orderAlerts: false, lowStockAlerts: false };
    try {
      const parsed = JSON.parse(raw);
      return parsed.notifications || { sound: false, orderAlerts: false, lowStockAlerts: false };
    } catch (e) {
      return { sound: false, orderAlerts: false, lowStockAlerts: false };
    }
  }

  // simple toast UI
  function toast(msg, opts = {}) {
    const containerId = 'notif-toast-container';
    let container = document.getElementById(containerId);
    if (!container) {
      container = document.createElement('div');
      container.id = containerId;
      container.style = 'position:fixed;right:18px;bottom:18px;z-index:99999;display:flex;flex-direction:column;gap:8px;max-width:320px;';
      document.body.appendChild(container);
    }
    const el = document.createElement('div');
    el.style = 'background:#111;color:#fff;padding:10px 14px;border-radius:8px;box-shadow:0 6px 18px rgba(0,0,0,0.2);font-size:13px;';
    el.textContent = msg;
    container.appendChild(el);
    setTimeout(() => {
      el.style.transition = 'opacity 300ms ease';
      el.style.opacity = '0';
      setTimeout(() => container.removeChild(el), 320);
    }, opts.duration || 5000);
  }

  // audio players
  const clickAudio = new Audio(audioClickSrc);
  const alertAudio = new Audio(audioAlertSrc);
  let soundEnabled = false;

  function playClick() {
    if (!soundEnabled) return;
    try { clickAudio.currentTime = 0; clickAudio.play().catch(()=>{}); } catch (e) {}
  }
  function playAlert() {
    if (!soundEnabled) return;
    try { alertAudio.currentTime = 0; alertAudio.play().catch(()=>{}); } catch (e) {}
  }

  // attach click-sound to product-like elements
  function wireClickSound() {
    // look for elements with class 'product-card' or 'order-item' or data-sound-click
    document.addEventListener('click', function(e){
      const el = e.target.closest('.product-card, .order-item, [data-sound-click]');
      if (!el) return;
      playClick();
    });
  }

  // polling helpers (server endpoints must exist)
  let lastOrderCheckId = Number(localStorage.getItem('lastOrderCheckId') || 0);

  async function fetchJson(url, timeout = 8000) {
    try {
      const controller = new AbortController();
      const id = setTimeout(()=> controller.abort(), timeout);
      const res = await fetch(url, {cache:'no-store', credentials:'same-origin', signal: controller.signal});
      clearTimeout(id);
      if (!res.ok) return null;
      return await res.json();
    } catch (e) {
      return null;
    }
  }

  // handle new orders response shape: { ok:true, newest_id: 123, new_orders: [ ... ] }
  async function pollForOrders() {
    const settings = loadSettings();
    if (!settings.orderAlerts) return;
    const url = ORDER_POLL_URL + '?since=' + encodeURIComponent(lastOrderCheckId || 0);
    const json = await fetchJson(url);
    if (!json || !json.ok) return;
    if (json.new_orders && json.new_orders.length) {
      json.new_orders.forEach(o => {
        toast('New order: ' + (o.id || o.sale_id || o.order_id) + (o.total_amount ? ' — ₱' + Number(o.total_amount).toFixed(2) : ''));
      });
      playAlert();
      // update last id
      lastOrderCheckId = json.newest_id || (json.new_orders[json.new_orders.length - 1].id || lastOrderCheckId);
      localStorage.setItem('lastOrderCheckId', String(lastOrderCheckId));
    }
  }

  // handle low-stock response: { ok:true, low: [ {id, name, qty} ] }
  async function pollForLowStock() {
    const settings = loadSettings();
    if (!settings.lowStockAlerts) return;
    const json = await fetchJson(LOW_STOCK_POLL_URL);
    if (!json || !json.ok) return;
    if (json.low && json.low.length) {
      json.low.forEach(it => {
        toast('Low stock: ' + it.name + ' — ' + (it.qty ?? '?') + ' left');
      });
      playAlert();
    }
  }

  // init polling loop
  function startPolling() {
    const settings = loadSettings();
    soundEnabled = !!settings.sound;
    // run immediate
    pollForOrders(); pollForLowStock();
    setInterval(async () => {
      const s = loadSettings();
      soundEnabled = !!s.sound;
      if (s.orderAlerts) await pollForOrders();
      if (s.lowStockAlerts) await pollForLowStock();
    }, DEFAULT_POLL_INTERVAL_MS);
  }

  // expose a small API
  window.ClubTryaraNotifs = {
    init: function() {
      wireClickSound();
      startPolling();
    },
    playClick: playClick,
    playAlert: playAlert,
    setSoundEnabled: function(b){ soundEnabled = !!b; const s = loadSettings(); s.sound = !!b; const st = JSON.parse(localStorage.getItem('appSettings') || '{}'); st.notifications = st.notifications || {}; st.notifications.sound = !!b; localStorage.setItem('appSettings', JSON.stringify(st)); }
  };

  // auto init when DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      window.ClubTryaraNotifs.init();
    });
  } else {
    window.ClubTryaraNotifs.init();
  }

})();
