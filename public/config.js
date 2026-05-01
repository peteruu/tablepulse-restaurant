const TablePulseConfig = (() => {
  const $ = (id) => document.getElementById(id);
  const api = {
    settings: 'api/settings.php',
    menu: 'api/menu.php',
    tables: 'api/tables.php'
  };

  let categories = [];
  let selectedCategoryIds = new Set();
  let mappedTables = {};

  async function json(url, options = {}) {
    const response = await fetch(url, options);
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.error || data.message || `${url} ${response.status}`);
    return data;
  }

  async function loadAll(refresh = false) {
    setNotice('Loading…');
    const suffix = refresh ? '?refresh=1' : '';
    const [settings, menu, tables] = await Promise.all([
      json(api.settings),
      json(`${api.menu}${suffix}`),
      json(`${api.tables}${suffix}`)
    ]);

    selectedCategoryIds = new Set((settings.settings?.visibleCategoryIds || []).map(String));
    $('orderMode').value = settings.settings?.orderMode || 'dry-run';
    categories = menu.categories || [];
    mappedTables = tables.mappedTables || {};

    renderHealth(settings.health || {});
    renderCategories(menu.source || 'unknown');
    renderTables(tables.dotyposTables || []);
    setNotice('Loaded.', 'ok');
  }

  function renderHealth(health) {
    const ready = Boolean(health.canSendPosActions);
    $('configHealthPill').textContent = ready ? 'ready for live' : 'not live yet';
    $('configHealthPill').className = ready ? 'pill online' : 'pill';
    $('healthList').innerHTML = Object.entries({
      'Cloud ID': health.cloudIdSet ? 'set' : 'missing',
      'Branch ID': health.branchIdSet ? 'set' : 'missing',
      'Refresh token': health.refreshTokenSet ? 'set' : 'missing',
      'Can send POS actions': ready ? 'yes' : 'no',
      'Default order mode': health.orderMode || 'dry-run'
    }).map(([key, value]) => `<div><dt>${escapeHtml(key)}</dt><dd>${escapeHtml(value)}</dd></div>`).join('');
  }

  function renderCategories(source) {
    $('categorySource').textContent = source;
    if (!categories.length) {
      $('categoryChecklist').innerHTML = '<p class="muted">No categories loaded yet.</p>';
      return;
    }

    $('categoryChecklist').replaceChildren(...categories.map((category) => {
      const label = document.createElement('label');
      label.className = 'check-card';
      label.innerHTML = `
        <input type="checkbox" value="${escapeHtml(category.id)}" ${selectedCategoryIds.has(String(category.id)) ? 'checked' : ''}>
        <span>${escapeHtml(category.name)}</span>
        <small>${escapeHtml(category.id)}</small>
      `;
      label.querySelector('input').addEventListener('change', (event) => {
        if (event.target.checked) selectedCategoryIds.add(String(category.id));
        else selectedCategoryIds.delete(String(category.id));
      });
      return label;
    }));
  }

  function renderTables(dotyposTables = []) {
    const ids = new Set([...Object.keys(mappedTables), '1', '2', '7']);
    $('tableRows').replaceChildren(...[...ids].sort(naturalSort).map((qr) => tableRow(qr, mappedTables[qr] || {}, dotyposTables)));
  }

  function tableRow(qr, table, dotyposTables) {
    const row = document.createElement('div');
    row.className = 'table-map-row';
    row.innerHTML = `
      <label>QR table<input data-field="qr" value="${escapeHtml(qr)}"></label>
      <label>Name<input data-field="name" value="${escapeHtml(table.name || `Table ${qr}`)}"></label>
      <label>Dotykačka table ID<input data-field="dotypos_table_id" value="${escapeHtml(table.dotypos_table_id ?? '')}" list="dotyposTableIds"></label>
      <button class="secondary" data-action="remove" type="button">Remove</button>
    `;
    row.querySelector('[data-action="remove"]').addEventListener('click', () => row.remove());

    if (!$('dotyposTableIds')) {
      const datalist = document.createElement('datalist');
      datalist.id = 'dotyposTableIds';
      datalist.innerHTML = dotyposTables.map((table) => `<option value="${escapeHtml(table.id)}">${escapeHtml(table.name || table.locationName || table.id)}</option>`).join('');
      document.body.appendChild(datalist);
    }

    return row;
  }

  function collectTables() {
    const tables = {};
    document.querySelectorAll('.table-map-row').forEach((row) => {
      const qr = row.querySelector('[data-field="qr"]').value.trim();
      if (!qr) return;
      tables[qr] = {
        name: row.querySelector('[data-field="name"]').value.trim() || `Table ${qr}`,
        dotypos_table_id: normalizeId(row.querySelector('[data-field="dotypos_table_id"]').value.trim())
      };
    });
    return tables;
  }

  function normalizeId(value) {
    if (value === '') return null;
    return /^\d+$/.test(value) ? Number(value) : value;
  }

  async function saveAll() {
    setNotice('Saving…');
    const settingsPayload = {
      visibleCategoryIds: [...selectedCategoryIds],
      orderMode: $('orderMode').value
    };
    const tablesPayload = { tables: collectTables() };

    await json(api.settings, {
      method: 'POST',
      headers: { 'content-type': 'application/json' },
      body: JSON.stringify(settingsPayload)
    });
    await json(api.tables, {
      method: 'POST',
      headers: { 'content-type': 'application/json' },
      body: JSON.stringify(tablesPayload)
    });
    mappedTables = tablesPayload.tables;
    setNotice('Saved. These values are stored in data/settings.json and data/table-map.json.', 'ok');
  }

  function addTable() {
    const next = String(document.querySelectorAll('.table-map-row').length + 1);
    $('tableRows').appendChild(tableRow(next, { name: `Table ${next}`, dotypos_table_id: null }, []));
  }

  function naturalSort(a, b) {
    return String(a).localeCompare(String(b), undefined, { numeric: true });
  }

  function setNotice(text, kind = 'ok') {
    $('configNotice').textContent = text;
    $('configNotice').className = `notice ${kind}`;
  }

  function escapeHtml(value) {
    return String(value || '').replace(/[&<>'"]/g, (char) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
    })[char]);
  }

  async function init() {
    $('refreshConfigBtn').addEventListener('click', () => loadAll(true).catch((error) => setNotice(error.message, 'error')));
    $('saveConfigBtn').addEventListener('click', () => saveAll().catch((error) => setNotice(error.message, 'error')));
    $('addTableBtn').addEventListener('click', addTable);
    await loadAll(false).catch((error) => setNotice(error.message, 'error'));
  }

  return { init };
})();
