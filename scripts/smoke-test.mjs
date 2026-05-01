const base = process.argv[2] || 'http://127.0.0.1:8090';

async function getJson(path, expected = 200) {
  const response = await fetch(`${base}${path}`);
  const data = await response.json();
  assert(response.status === expected, `${path} returned ${response.status}, expected ${expected}`);
  return data;
}

async function postJson(path, body, expected = 200) {
  const response = await fetch(`${base}${path}`, {
    method: 'POST',
    headers: { 'content-type': 'application/json' },
    body: JSON.stringify(body),
  });
  const data = await response.json();
  assert(response.status === expected, `${path} returned ${response.status}, expected ${expected}`);
  return data;
}

function assert(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

const health = await getJson('/api/health.php');
assert(health.ok, 'health ok=false');
assert(health.curl, 'php curl extension is not enabled');

const menu = await getJson('/api/menu.php');
assert(Array.isArray(menu.items) && menu.items.length > 0, 'menu has no items');
assert(Array.isArray(menu.categories) && menu.categories.length > 0, 'menu has no categories');

const tables = await getJson('/api/tables.php');
assert(tables.mappedTables && Object.keys(tables.mappedTables).length > 0, 'no mapped tables');

const order = await postJson('/api/orders.php', {
  id: `smoke-${Date.now()}`,
  table: '7',
  note: 'Smoke test order note',
  items: [
    {
      id: 'burger',
      name: 'House burger',
      price: 12.9,
      qty: 1,
      note: 'No onion',
      dotyposProductId: 123456,
    },
  ],
  createdAt: new Date().toISOString(),
});

assert(order.ok, 'order response ok=false');
assert(order.order?.dotyposPayload?.action === 'order/create', 'missing Dotypos order/create payload');
assert(order.order?.dotyposPayload?.items?.[0]?.note === 'No onion', 'item note not mapped into Dotypos payload');

const pos = await getJson('/api/pos-actions.php?table=7', 400);
assert(String(pos.error || '').includes('missing'), 'expected missing credentials warning from pos-actions');

console.log(JSON.stringify({
  ok: true,
  php: health.php,
  menuItems: menu.items.length,
  categories: menu.categories.length,
  orderMode: order.mode,
  posActionsWithoutCredentials: pos.error,
}, null, 2));
