const base = process.argv[2] || 'http://127.0.0.1:8090';
const auth = process.argv[3] || process.env.TABLEPULSE_SMOKE_AUTH || '';

function headers(extra = {}) {
  return auth ? { ...extra, authorization: `Basic ${Buffer.from(auth).toString('base64')}` } : extra;
}

async function getJson(path, expected = 200, authenticated = false) {
  const response = await fetch(`${base}${path}`, { headers: authenticated ? headers() : {} });
  const data = await response.json();
  assert(response.status === expected, `${path} returned ${response.status}, expected ${expected}`);
  return data;
}

async function postJson(path, body, expected = 200, authenticated = false) {
  const response = await fetch(`${base}${path}`, {
    method: 'POST',
    headers: headers({ 'content-type': 'application/json' }),
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
const authEnabled = Boolean(health.auth?.enabled);

const menu = await getJson('/api/menu.php');
assert(Array.isArray(menu.items) && menu.items.length > 0, 'menu has no items');
assert(Array.isArray(menu.categories) && menu.categories.length > 0, 'menu has no categories');

if (authEnabled && !auth) {
  const protectedResponse = await getJson('/api/settings.php', 401);
  assert(String(protectedResponse.error || '').includes('Authentication'), 'protected endpoint did not require auth');
  console.log(JSON.stringify({
    ok: true,
    php: health.php,
    auth: 'enabled; protected endpoints require credentials',
    menuItems: menu.items.length,
    categories: menu.categories.length,
  }, null, 2));
} else {

const tables = await getJson('/api/tables.php', 200, authEnabled);
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

const pos = await getJson('/api/pos-actions.php?table=7', 400, authEnabled);
assert(String(pos.error || '').includes('missing'), 'expected missing credentials warning from pos-actions');

console.log(JSON.stringify({
  ok: true,
  php: health.php,
  auth: authEnabled ? 'enabled' : 'disabled',
  menuItems: menu.items.length,
  categories: menu.categories.length,
  orderMode: order.mode,
  posActionsWithoutCredentials: pos.error,
}, null, 2));
}
