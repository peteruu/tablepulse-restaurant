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
    if (!response.ok) throw new Error(`${url} ${response.status}`);
    return response.json();
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
    const existing = cart.find((line) => line.id === item.id);
    if (existing) existing.qty += 1;
    else cart.push({ ...item, qty: 1 });
    saveCart();
    renderCart();
  }

  function renderCart() {
    $('cartList').replaceChildren(...cart.map((line) => {
      const row = document.createElement('div');
      row.className = 'cart-line';
      row.innerHTML = `
        <div>
          <strong>${escapeHtml(line.name)}</strong>
          <span>${money(line.price)} × ${line.qty}</span>
        </div>
        <div class="qty-controls">
          <button class="secondary" data-action="minus">−</button>
          <button class="secondary" data-action="plus">+</button>
        </div>
      `;
      row.querySelector('[data-action="minus"]').addEventListener('click', () => changeQty(line.id, -1));
      row.querySelector('[data-action="plus"]').addEventListener('click', () => changeQty(line.id, 1));
      return row;
    }));
    $('cartEmpty').style.display = cart.length ? 'none' : 'block';
    $('cartTotal').textContent = money(cart.reduce((sum, line) => sum + Number(line.price || 0) * line.qty, 0));
    $('sendOrderBtn').disabled = cart.length === 0;
  }

  function changeQty(id, delta) {
    cart = cart.map((line) => line.id === id ? { ...line, qty: line.qty + delta } : line).filter((line) => line.qty > 0);
    saveCart();
    renderCart();
  }

  async function sendOrder() {
    if (!cart.length) return;
    const order = {
      id: uid(),
      table: tableNumber(),
      note: $('orderNote').value.trim(),
      items: cart.map((line) => ({
        id: line.id,
        name: line.name,
        price: Number(line.price || 0),
        qty: line.qty,
        dotyposProductId: line.dotyposProductId || null
      })),
      total: cart.reduce((sum, line) => sum + Number(line.price || 0) * line.qty, 0),
      status: 'new',
      createdAt: new Date().toISOString()
    };

    const button = $('sendOrderBtn');
    button.disabled = true;
    button.textContent = 'Sending…';

    let online = false;
    try {
      const result = await json(ORDER_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(order)
      });
      online = Boolean(result.ok);
    } catch {
      const orders = loadLocalOrders();
      localStorage.setItem(ORDER_KEY, JSON.stringify([order, ...orders]));
    }

    cart = [];
    saveCart();
    $('orderNote').value = '';
    renderCart();
    $('orderNotice').textContent = online ? 'Order sent to restaurant system.' : 'Order saved in demo mode for dashboard.';
    $('orderNotice').className = 'notice ok';
    button.textContent = 'Send order';
    button.disabled = false;
  }

  function loadLocalOrders() {
    try { return JSON.parse(localStorage.getItem(ORDER_KEY) || '[]'); }
    catch { return []; }
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
  }

  return { init };
})();
