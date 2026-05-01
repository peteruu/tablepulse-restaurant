const TablePulseQr = (() => {
  const $ = (id) => document.getElementById(id);
  const TABLES_URL = 'api/tables.php';
  let mappedTables = {};

  async function json(url) {
    const response = await fetch(url);
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.error || `${url} ${response.status}`);
    return data;
  }

  async function loadTables() {
    try {
      const data = await json(TABLES_URL);
      mappedTables = data.mappedTables || {};
      setNotice(`Loaded ${Object.keys(mappedTables).length} mapped tables.`, 'ok');
    } catch (error) {
      mappedTables = { 1: { name: 'Table 1' }, 2: { name: 'Table 2' }, 7: { name: 'Table 7' } };
      setNotice(`Using demo tables. ${error.message}`, 'error');
    }
    renderQrCodes();
  }

  function defaultBaseUrl() {
    const url = new URL('order.html', window.location.href);
    url.search = '';
    return url.href;
  }

  function orderUrl(tableNumber) {
    const url = new URL($('baseUrl').value || defaultBaseUrl(), window.location.href);
    url.searchParams.set('t', tableNumber);
    return url.href;
  }

  function renderQrCodes() {
    const subtitle = $('qrSubtitle').value || 'Scan to order from your table';
    const tables = Object.entries(mappedTables).sort(([a], [b]) => String(a).localeCompare(String(b), undefined, { numeric: true }));
    $('qrSheet').replaceChildren(...tables.map(([qrTable, table]) => qrCard(qrTable, table, subtitle)));
  }

  function qrCard(qrTable, table, subtitle) {
    const url = orderUrl(qrTable);
    const card = document.createElement('article');
    card.className = 'qr-card';
    const qr = qrcode(0, 'M');
    qr.addData(url);
    qr.make();
    card.innerHTML = `
      <div class="qr-brand">TablePulse</div>
      <h2>${escapeHtml(table.name || `Table ${qrTable}`)}</h2>
      <p>${escapeHtml(subtitle)}</p>
      <div class="qr-code">${qr.createSvgTag({ cellSize: 5, margin: 2 })}</div>
      <strong>Table ${escapeHtml(qrTable)}</strong>
      <small>${escapeHtml(url)}</small>
    `;
    return card;
  }

  function setNotice(text, kind = 'ok') {
    $('qrNotice').textContent = text;
    $('qrNotice').className = `notice ${kind}`;
  }

  function escapeHtml(value) {
    return String(value || '').replace(/[&<>'"]/g, (char) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
    })[char]);
  }

  async function init() {
    $('baseUrl').value = defaultBaseUrl();
    $('refreshQrBtn').addEventListener('click', loadTables);
    $('generateQrBtn').addEventListener('click', renderQrCodes);
    $('qrSubtitle').addEventListener('input', renderQrCodes);
    $('baseUrl').addEventListener('input', renderQrCodes);
    $('printQrBtn').addEventListener('click', () => window.print());
    await loadTables();
  }

  return { init };
})();
