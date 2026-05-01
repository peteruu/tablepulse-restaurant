const TablePulse = (() => {
  const STORAGE_KEY = 'tablepulse.tickets.v1';
  const ORDER_KEY = 'tablepulse.orders.v1';
  const API_URL = 'api/tickets.php';
  const ORDER_API_URL = 'api/orders.php';
  const statusLabels = {
    new: 'New',
    doing: 'Doing',
    done: 'Done',
    received: 'Received',
    dry_run: 'Dry run',
    queued_missing_credentials: 'Queued · missing credentials',
    queued_send_failed: 'Queued · send failed',
    sent_to_dotypos: 'Sent to Dotykačka'
  };
  const typeLabels = {
    service: 'Call waiter',
    payment: 'Ask for bill',
    cleaning: 'Clean table',
    order_issue: 'Order issue',
    feedback: 'Feedback'
  };

  const $ = (id) => document.getElementById(id);

  function getTableFromUrl() {
    const params = new URLSearchParams(window.location.search);
    return params.get('t') || params.get('table') || '1';
  }

  function nowIso() {
    return new Date().toISOString();
  }

  function makeId() {
    if (crypto && crypto.randomUUID) return crypto.randomUUID();
    return `ticket-${Date.now()}-${Math.random().toString(16).slice(2)}`;
  }

  function loadLocal() {
    try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]'); }
    catch { return []; }
  }

  function saveLocal(tickets) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(tickets));
  }

  function loadLocalOrders() {
    try { return JSON.parse(localStorage.getItem(ORDER_KEY) || '[]'); }
    catch { return []; }
  }

  function saveLocalOrders(orders) {
    localStorage.setItem(ORDER_KEY, JSON.stringify(orders));
  }

  async function apiRequest(method = 'GET', body = null, id = null) {
    const url = id ? `${API_URL}?id=${encodeURIComponent(id)}` : API_URL;
    const response = await fetch(url, {
      method,
      headers: { 'Content-Type': 'application/json' },
      body: body ? JSON.stringify(body) : null
    });
    if (!response.ok) throw new Error(`API ${response.status}`);
    return response.json();
  }

  async function orderApiRequest(method = 'GET', body = null, id = null) {
    const url = id ? `${ORDER_API_URL}?id=${encodeURIComponent(id)}` : ORDER_API_URL;
    const response = await fetch(url, {
      method,
      headers: { 'Content-Type': 'application/json' },
      body: body ? JSON.stringify(body) : null
    });
    if (!response.ok) throw new Error(`Order API ${response.status}`);
    return response.json();
  }

  async function listTickets() {
    try {
      const data = await apiRequest('GET');
      saveLocal(data.tickets || []);
      return { tickets: data.tickets || [], online: true };
    } catch {
      return { tickets: loadLocal(), online: false };
    }
  }

  async function createTicket(input) {
    const ticket = {
      id: makeId(),
      table: String(input.table || '1'),
      type: input.type,
      message: (input.message || '').trim(),
      status: 'new',
      createdAt: nowIso(),
      updatedAt: nowIso()
    };

    const local = [ticket, ...loadLocal()];
    saveLocal(local);

    try {
      const data = await apiRequest('POST', ticket);
      if (data.ticket) {
        const merged = [data.ticket, ...loadLocal().filter((item) => item.id !== ticket.id)];
        saveLocal(merged);
        return { ticket: data.ticket, online: true };
      }
    } catch {
      // local demo mode
    }
    return { ticket, online: false };
  }

  async function updateStatus(id, status) {
    const local = loadLocal().map((ticket) => ticket.id === id ? { ...ticket, status, updatedAt: nowIso() } : ticket);
    saveLocal(local);
    try {
      await apiRequest('PATCH', { status }, id);
      return true;
    } catch {
      return false;
    }
  }

  async function listOrders() {
    try {
      const data = await orderApiRequest('GET');
      saveLocalOrders(data.orders || []);
      return { orders: data.orders || [], online: true };
    } catch {
      return { orders: loadLocalOrders(), online: false };
    }
  }

  async function updateOrderStatus(id, status) {
    const local = loadLocalOrders().map((order) => order.id === id ? { ...order, status, updatedAt: nowIso() } : order);
    saveLocalOrders(local);
    try {
      await orderApiRequest('PATCH', { status }, id);
      return true;
    } catch {
      return false;
    }
  }

  function formatTime(iso) {
    try { return new Intl.DateTimeFormat(undefined, { hour: '2-digit', minute: '2-digit' }).format(new Date(iso)); }
    catch { return ''; }
  }

  function setNotice(text, kind = 'ok') {
    const notice = $('notice');
    if (!notice) return;
    notice.textContent = text;
    notice.className = `notice ${kind}`;
  }

  function initGuest() {
    const table = getTableFromUrl();
    $('tableNumber').textContent = table;
    $('ticketForm').addEventListener('submit', async (event) => {
      event.preventDefault();
      const type = $('type').value;
      const message = $('message').value;
      const button = event.submitter;
      button.disabled = true;
      button.textContent = 'Sending…';
      const result = await createTicket({ table, type, message });
      $('message').value = '';
      setNotice(result.online ? 'Sent to staff. Thank you.' : 'Saved in local demo mode. Open dashboard to see it.');
      button.disabled = false;
      button.textContent = 'Send request';
    });
  }

  function renderStats(tickets) {
    $('statOpen').textContent = tickets.filter((ticket) => ticket.status !== 'done').length;
    $('statDone').textContent = tickets.filter((ticket) => ticket.status === 'done').length;
    $('statTotal').textContent = tickets.length;
  }

  function renderTicket(ticket) {
    const article = document.createElement('article');
    article.className = 'ticket';
    article.innerHTML = `
      <div>
        <h3>Table ${escapeHtml(ticket.table)} · ${escapeHtml(typeLabels[ticket.type] || ticket.type)}</h3>
        <p>${escapeHtml(ticket.message || 'No message added')}</p>
        <div class="ticket-meta">
          <span class="tag ${escapeHtml(ticket.status)}">${escapeHtml(statusLabels[ticket.status] || ticket.status)}</span>
          <span class="tag">${escapeHtml(formatTime(ticket.createdAt))}</span>
        </div>
      </div>
      <div class="ticket-actions">
        <button class="secondary" data-status="new">New</button>
        <button class="secondary" data-status="doing">Doing</button>
        <button class="secondary" data-status="done">Done</button>
      </div>
    `;
    article.querySelectorAll('button[data-status]').forEach((button) => {
      button.addEventListener('click', async () => {
        await updateStatus(ticket.id, button.dataset.status);
        await refreshDashboard();
      });
    });
    return article;
  }

  function renderOrder(order) {
    const article = document.createElement('article');
    article.className = 'ticket';
    const items = (order.items || []).map((item) => `<li>${escapeHtml(item.qty || 1)}× ${escapeHtml(item.name)} · ${escapeHtml(money(item.price || 0))}</li>`).join('');
    article.innerHTML = `
      <div>
        <h3>Table ${escapeHtml(order.table)} · ${escapeHtml(money(order.total || orderTotal(order)))}</h3>
        ${order.note ? `<p>${escapeHtml(order.note)}</p>` : '<p>No note added</p>'}
        <ul class="order-items">${items}</ul>
        <div class="ticket-meta">
          <span class="tag ${escapeHtml(statusClass(order.status || 'new'))}">${escapeHtml(statusLabels[order.status] || order.status || 'New')}</span>
          <span class="tag">${escapeHtml(formatTime(order.createdAt || order.receivedAt))}</span>
        </div>
      </div>
      <div class="ticket-actions">
        <button class="secondary" data-status="new">New</button>
        <button class="secondary" data-status="doing">Doing</button>
        <button class="secondary" data-status="done">Done</button>
      </div>
    `;
    article.querySelectorAll('button[data-status]').forEach((button) => {
      button.addEventListener('click', async () => {
        await updateOrderStatus(order.id, button.dataset.status);
        await refreshDashboard();
      });
    });
    return article;
  }

  function money(value) {
    return `${Number(value || 0).toFixed(2)} €`;
  }

  function orderTotal(order) {
    return (order.items || []).reduce((sum, item) => sum + Number(item.price || 0) * Number(item.qty || 1), 0);
  }

  function statusClass(status) {
    if (['done', 'sent_to_dotypos'].includes(status)) return 'done';
    if (['doing', 'dry_run', 'received'].includes(status)) return 'doing';
    return 'new';
  }

  function escapeHtml(value) {
    return String(value || '').replace(/[&<>'"]/g, (char) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
    })[char]);
  }

  async function refreshDashboard() {
    const [{ tickets, online }, { orders, online: ordersOnline }] = await Promise.all([listTickets(), listOrders()]);
    const filter = $('statusFilter').value;
    const visible = tickets
      .filter((ticket) => filter === 'all' || ticket.status === filter)
      .sort((a, b) => String(b.createdAt).localeCompare(String(a.createdAt)));

    renderStats(tickets);
    $('syncState').textContent = online ? 'PHP backend online' : 'local demo';
    $('syncState').className = online ? 'pill online' : 'pill';
    $('ticketList').replaceChildren(...visible.map(renderTicket));
    $('emptyState').style.display = visible.length ? 'none' : 'block';

    const visibleOrders = orders
      .filter((order) => filter === 'all' || (order.status || 'new') === filter)
      .sort((a, b) => String(b.createdAt || b.receivedAt || '').localeCompare(String(a.createdAt || a.receivedAt || '')));
    $('orderSyncState').textContent = ordersOnline ? 'PHP backend online' : 'local demo';
    $('orderSyncState').className = ordersOnline ? 'pill online' : 'pill';
    $('orderList').replaceChildren(...visibleOrders.map(renderOrder));
    $('orderEmptyState').style.display = visibleOrders.length ? 'none' : 'block';
  }

  function initDashboard() {
    $('refreshBtn').addEventListener('click', refreshDashboard);
    $('statusFilter').addEventListener('change', refreshDashboard);
    $('exportBtn').addEventListener('click', () => {
      const blob = new Blob([JSON.stringify({ tickets: loadLocal(), orders: loadLocalOrders() }, null, 2)], { type: 'application/json' });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = `tablepulse-export-${new Date().toISOString().slice(0, 10)}.json`;
      a.click();
      URL.revokeObjectURL(a.href);
    });
    $('linkForm').addEventListener('submit', (event) => {
      event.preventDefault();
      const table = $('tableInput').value || '1';
      const url = new URL('index.html', window.location.href);
      url.searchParams.set('t', table);
      $('tableLink').textContent = url.href;
    });
    $('linkForm').dispatchEvent(new Event('submit'));
    refreshDashboard();
  }

  return { initGuest, initDashboard, createTicket, listTickets, updateStatus, _private: { loadLocal, saveLocal } };
})();

if (typeof module !== 'undefined') module.exports = TablePulse;
