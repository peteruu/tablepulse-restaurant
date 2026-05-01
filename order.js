const TablePulseOrder = (() => {
  const ORDER_KEY = 'tablepulse.orders.v1';
  const CART_KEY = 'tablepulse.cart.v1';
  const MENU_URL = 'api/menu.php';
  const ORDER_URL = 'api/orders.php';
  const SAMPLE_MENU_URL = 'menu.sample.json';

  const $ = (id) => document.getElementById(id);
  let menu = { categories: [], items: [] };
  let activeCategory = 'all';
  let cart = [];
  let lastOrder = null;

  function tableNumber() {
    const params = new URLSearchParams(window.location.search);
    return params.get('t') || params.get('table') || '1';
  }

  function money(value) {
    return `${Number(value || 0).toFixed(2)} €`;
  }

  function uid() {
    return crypto?.randomUUID ? crypto.randomUUID() : `order-${Date.now()}-${Math.random().toString(16).slice(2)}`;
  }

  async function json(url, options = {}) {
    const response = await fetch(url, options);
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.error || data.message || `${url} ${response.status}`);
    return data;
  }

  async function loadMenu() {
    try {
      const data = await json(MENU_URL);
      $('menuState').textContent = data.source === 'dotypos' ? 'Dotykačka menu' : 'configured menu';
      $('menuState').className = data.source === 'dotypos' ? 'pill online' : 'pill';
      return normalizeMenu(data);
    } catch {
      const data = await json(SAMPLE_MENU_URL);
      $('menuState').textContent = 'demo menu';
      $('menuState').className = 'pill';
      return normalizeMenu(data);
    }
  }

  function normalizeMenu(data) {
    const categories = (data.categories || []).filter((category) => category.visible !== false);
    const visibleIds = new Set(categories.map((category) => String(category.id)));
    const items = (data.items || []).filter((item) => visibleIds.has(String(item.categoryId)) && item.visible !== false);
    return { categories, items };
  }

  function loadCart() {
    try { return JSON.parse(sessionStorage.getItem(CART_KEY) || '[]'); }
    catch { return []; }
  }

  function saveCart() {
    sessionStorage.setItem(CART_KEY, JSON.stringify(cart));
  }

  function loadLocalOrders() {
    try { return JSON.parse(localStorage.getItem(ORDER_KEY) || '[]'); }
    catch { return []; }
  }

  function saveLocalOrder(order) {
    localStorage.setItem(ORDER_KEY, JSON.stringify([order, ...loadLocalOrders()]));
  }

  function renderCategories() {
    const tabs = [{ id: 'all', name: 'All' }, ...menu.categories];
    $('categoryTabs').replaceChildren(...tabs.map((category) => {
      const button = document.createElement('button');
      button.className = category.id === activeCategory ? 'category-tab active' : 'category-tab';
      button.textContent = category.name;
      button.addEventListener('click', () => {
        activeCategory = category.id;
        renderCategories();
        renderMenu();
      });
      return button;
    }));
  }

  function renderMenu() {
    const items = activeCategory === 'all' ? menu.items : menu.items.filter((item) => item.categoryId === activeCategory);
    $('menuList').replaceChildren(...items.map((item) => {
      const article = document.createElement('article');
      article.className = 'menu-item';
      article.innerHTML = `
        <div>
          <h3>${escapeHtml(item.name)}</h3>
          <p>${escapeHtml(item.description || '')}</p>
          <strong>${money(item.price)}</strong>
        </div>
        <button class="secondary" type="button">Add</button>
      `;
      article.querySelector('button').addEventListener('click', () => addItem(item));
      return article;
    }));
  }

  function addItem(item) {
    const existing = cart.find((line) => line.id === item.id && (line.note || '') === '');
    if (existing) existing.qty += 1;
    else cart.push({ ...item, qty: 1, note: '' });
    saveCart();
    renderCart();
  }

  function renderCart() {
    $('cartList').replaceChildren(...cart.map((line) => {
      const row = document.createElement('div');
      row.className = 'cart-line';
      row.innerHTML = `
        <div class="cart-line-main">
          <strong>${escapeHtml(line.name)}</strong>
          <span>${money(line.price)} × ${line.qty}</span>
          <label class="item-note-label">
            Item note
            <input class="item-note" value="${escapeHtml(line.note || '')}" placeholder="e.g. no onion" maxlength="160">
          </label>
        </div>
        <div class="qty-controls">
          <button class="secondary" data-action="minus" aria-label="Remove one ${escapeHtml(line.name)}">−</button>
          <button class="secondary" data-action="plus" aria-label="Add one ${escapeHtml(line.name)}">+</button>
        </div>
      `;
      row.querySelector('[data-action="minus"]').addEventListener('click', () => changeQty(line.id, -1, line.note || ''));
      row.querySelector('[data-action="plus"]').addEventListener('click', () => changeQty(line.id, 1, line.note || ''));
      row.querySelector('.item-note').addEventListener('input', (event) => updateItemNote(line.id, line.note || '', event.target.value));
      return row;
    }));
    $('cartEmpty').style.display = cart.length ? 'none' : 'block';
    $('cartTotal').textContent = money(cart.reduce((sum, line) => sum + Number(line.price || 0) * line.qty, 0));
    $('sendOrderBtn').disabled = cart.length === 0;
  }

  function findLine(id, note) {
    return cart.find((line) => line.id === id && (line.note || '') === note);
  }

  function changeQty(id, delta, note = '') {
    const line = findLine(id, note);
    if (!line) return;
    line.qty += delta;
    cart = cart.filter((item) => item.qty > 0);
    saveCart();
    renderCart();
  }

  function updateItemNote(id, previousNote, nextNote) {
    const line = findLine(id, previousNote);
    if (!line) return;
    line.note = nextNote.trimStart();
    saveCart();
  }

  function buildOrder() {
    return {
      id: uid(),
      table: tableNumber(),
      note: $('orderNote').value.trim(),
      items: cart.map((line) => ({
        id: line.id,
        name: line.name,
        price: Number(line.price || 0),
        qty: line.qty,
        note: (line.note || '').trim(),
        dotyposProductId: line.dotyposProductId || null
      })),
      total: cart.reduce((sum, line) => sum + Number(line.price || 0) * line.qty, 0),
      status: 'new',
      createdAt: new Date().toISOString()
    };
  }

  async function sendOrder() {
    if (!cart.length) return;
    const order = buildOrder();
    const button = $('sendOrderBtn');
    button.disabled = true;
    button.textContent = 'Sending…';
    setNotice('', '');

    try {
      const result = await json(ORDER_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(order)
      });
      lastOrder = result.order || order;
      showConfirmation(result.mode || 'sent', true);
    } catch (error) {
      saveLocalOrder(order);
      lastOrder = order;
      showConfirmation('local-demo', false, error.message);
    } finally {
      cart = [];
      saveCart();
      $('orderNote').value = '';
      renderCart();
      button.textContent = 'Send order';
      button.disabled = false;
    }
  }

  function showConfirmation(mode, online, error = '') {
    const panel = $('confirmationPanel');
    const title = $('confirmationTitle');
    const detail = $('confirmationDetail');
    const modeText = {
      live: 'Order sent to Dotykačka.',
      'dry-run': 'Dry-run order prepared successfully.',
      queued_missing_credentials: 'Order queued until Dotykačka credentials are configured.',
      queued_send_failed: 'Order saved because Dotykačka sending failed.',
      'local-demo': 'Order saved in this browser demo.'
    }[mode] || 'Order received.';

    title.textContent = 'Thank you — order received';
    detail.textContent = `${modeText} Reference: ${shortId(lastOrder?.id || '')}${error ? ` (${error})` : ''}`;
    panel.hidden = false;
    panel.scrollIntoView({ behavior: 'smooth', block: 'center' });
    setNotice(online ? 'Order received.' : 'Saved locally for demo/testing.', online ? 'ok' : 'error');
  }

  function shortId(id) {
    return String(id || '').slice(0, 8).toUpperCase() || 'N/A';
  }

  function setNotice(text, kind = 'ok') {
    const notice = $('orderNotice');
    notice.textContent = text;
    notice.className = `notice ${kind}`;
  }

  function escapeHtml(value) {
    return String(value || '').replace(/[&<>'"]/g, (char) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
    })[char]);
  }

  async function init() {
    $('orderTableNumber').textContent = tableNumber();
    cart = loadCart();
    menu = await loadMenu();
    renderCategories();
    renderMenu();
    renderCart();
    $('sendOrderBtn').addEventListener('click', sendOrder);
    $('newOrderBtn').addEventListener('click', () => {
      $('confirmationPanel').hidden = true;
      setNotice('', '');
    });
  }

  return { init, _private: { buildOrder, normalizeMenu } };
})();

if (typeof module !== 'undefined') module.exports = TablePulseOrder;
