const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const ts = require('typescript');
const Module = require('node:module');
const resolve = Module._resolveFilename;
Module._resolveFilename = function (id, ...args) { return resolve.call(this, id.startsWith('@/') ? path.join(__dirname, '../src', id.slice(2)) : id, ...args); };
require.extensions['.ts'] = (module, filename) => module._compile(ts.transpileModule(fs.readFileSync(filename, 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020, esModuleInterop: true } }).outputText, filename);
const { resolveProducts, formatResolvedPrice } = require('../src/lib/enquiry/product-resolve.client.ts');
const A = '123e4567-e89b-42d3-a456-426614174000';
const B = '123e4567-e89b-42d3-a456-426614174001';
function dto(id, overrides = {}) { return { id, slug: 'pot', sku: 'SKU-1', name: 'Pot', brand: { id: 'brand', name: 'Brand' }, priceAed: '123456789.1250', currency: 'AED', categoryIds: [], primaryCategory: { id: 'category', slug: 'pots', name: 'Pots', path: '/shop/pots' }, canonicalPath: '/shop/pots/pot', cardImage: null, cardSpecifications: [], cardSpecificationsText: '', ...overrides }; }
function response(data) { global.fetch = async (url, options) => ({ ok: true, json: async () => { assert.equal(url, '/api/v1/products/resolve'); assert.deepEqual(JSON.parse(options.body), { ids: [A, B] }); return { data }; } }); }

test('empty input makes no request and batch response order and exact decimal formatting are preserved', async () => {
  global.fetch = () => { throw new Error('unexpected request'); };
  assert.deepEqual(await resolveProducts([]), []);
  response([{ id: A, status: 'available', product: dto(A) }, { id: B, status: 'unavailable' }]);
  const lines = await resolveProducts([{ productId: A, quantity: 2 }, { productId: B, quantity: 4 }]);
  assert.deepEqual(lines.map((line) => line.status), ['available', 'unavailable']);
  assert.equal(lines[0].product.priceAed, '123456789.1250');
  assert.equal(formatResolvedPrice(lines[0].product.priceAed), 'AED 123,456,789.1250');
  assert.equal(formatResolvedPrice(null), 'Price on enquiry');
});

test('invalid, duplicate, missing, unexpected or out-of-order resolve responses fail atomically', async () => {
  for (const data of [[], [{ id: A, status: 'unavailable' }], [{ id: B, status: 'unavailable' }, { id: A, status: 'unavailable' }], [{ id: A, status: 'available', product: dto(B) }, { id: B, status: 'unavailable' }], [{ id: A, status: 'unknown' }, { id: B, status: 'unavailable' }]]) {
    response(data);
    await assert.rejects(resolveProducts([{ productId: A, quantity: 1 }, { productId: B, quantity: 1 }]));
  }
  await assert.rejects(resolveProducts([{ productId: A, quantity: 1 }, { productId: A, quantity: 1 }]));
});

test('HTTP failures remain errors and never turn an item unavailable', async () => {
  global.fetch = async () => ({ ok: false, status: 503 });
  await assert.rejects(resolveProducts([{ productId: A, quantity: 1 }]));
});
