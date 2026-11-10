const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const ts = require('typescript');
const Module = require('node:module');
const resolve = Module._resolveFilename;
Module._resolveFilename = function (id, ...args) { return resolve.call(this, id.startsWith('@/') ? path.join(__dirname, '../src', id.slice(2)) : id, ...args); };
require.extensions['.ts'] = (module, filename) => module._compile(ts.transpileModule(fs.readFileSync(filename, 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020, esModuleInterop: true } }).outputText, filename);
const domain = require('../src/lib/enquiry/store-v2.ts');
const { parseV2Envelope, serializeV2, addV2, removeV2, setV2Quantity } = domain;
const A = '123e4567-e89b-42d3-a456-426614174000';
const B = '123e4567-e89b-42d3-a456-426614174001';
const envelope = (items) => JSON.stringify({ version: 2, items });

test('v2 parser accepts empty and multiple UUID rows and rejects corrupt or noncanonical data', () => {
  assert.deepEqual(parseV2Envelope(envelope([])), { ok: true, value: [] });
  assert.deepEqual(parseV2Envelope(envelope([{ productId: A, quantity: 1 }, { productId: B, quantity: 7 }])), { ok: true, value: [{ productId: A, quantity: 1 }, { productId: B, quantity: 7 }] });
  for (const raw of ['{bad', '{"version":3,"items":[]}', envelope([{ productId: 'sku-1', quantity: 1 }]), envelope([{ productId: A, quantity: 0 }]), envelope([{ productId: A, quantity: -1 }]), envelope([{ productId: A, quantity: 1.5 }]), envelope([{ productId: A, quantity: '1' }]), envelope([{ productId: A, quantity: 1000000 }]), envelope([{ productId: A, quantity: 1, sku: 'x' }]), envelope([{ productId: A, quantity: 1 }, { productId: A, quantity: 2 }]), envelope(Array.from({ length: 101 }, (_, i) => ({ productId: `123e4567-e89b-42d3-a456-${String(i).padStart(12, '0')}`, quantity: 1 })))]) assert.equal(parseV2Envelope(raw).ok, false);
  assert.throws(() => serializeV2([{ productId: A, quantity: 0 }]));
});

test('UUID store operations preserve order, merge by UUID, enforce limits and reject invalid inputs', () => {
  let items = addV2([], A, 2).items;
  items = addV2(items, B, 1).items;
  items = addV2(items, A, 3).items;
  assert.deepEqual(items, [{ productId: A, quantity: 5 }, { productId: B, quantity: 1 }]);
  assert.deepEqual(setV2Quantity(items, A, 8).items, [{ productId: A, quantity: 8 }, { productId: B, quantity: 1 }]);
  assert.deepEqual(removeV2(items, A).items, [{ productId: B, quantity: 1 }]);
  assert.equal(addV2([], 'SKU-123', 1).ok, false);
  assert.equal(addV2([], A, 0).ok, false);
  assert.equal(addV2([{ productId: A, quantity: 999999 }], A, 1).ok, false);
  assert.equal(setV2Quantity([{ productId: A, quantity: 1 }], A, 0).ok, false);
  const hundred = Array.from({ length: 100 }, (_, i) => ({ productId: `123e4567-e89b-42d3-a456-${String(i).padStart(12, '0')}`, quantity: 1 }));
  assert.equal(addV2(hundred, A, 1).ok, false);
});

function setupStorage(seed = {}) {
  const values = new Map(Object.entries(seed));
  let setBehavior = (key, value) => values.set(key, value);
  const handlers = new Map();
  global.window = {
    localStorage: { getItem: (key) => values.has(key) ? values.get(key) : null, setItem: (key, value) => setBehavior(key, value) },
    addEventListener: (name, fn) => handlers.set(name, fn), removeEventListener: () => {},
  };
  return { values, handlers, setWriteBehavior: (fn) => { setBehavior = fn; } };
}
function freshStore() {
  delete require.cache[require.resolve('../src/lib/enquiry/store-v2.ts')];
  return require('../src/lib/enquiry/store-v2.ts').enquiryStoreV2;
}

test('UUID store ignores old fixtures and reports storage failures', () => {
  const rawV1 = JSON.stringify({ version: 1, items: [{ productId: A, sku: 'same', quantity: 2 }, { productId: B, sku: 'same', quantity: 4 }] });
  const fixture = setupStorage({ 'potsandplanters.enquiry.v1': rawV1 });
  const store = freshStore();
  assert.equal(store.getState().status, 'not_hydrated');
  store.subscribe(() => {});
  assert.equal(store.add(A, 2).ok, true);
  assert.equal(store.getState().status, 'ready');
  assert.equal(fixture.values.get('potsandplanters.enquiry.v1'), rawV1);
  assert.equal(fixture.values.get('potsandplanters.enquiry.v2'), envelope([{ productId: A, quantity: 2 }]));
  fixture.setWriteBehavior(() => { throw new Error('quota'); });
  const failure = store.add(B, 1);
  assert.equal(failure.ok, false);
  assert.equal(failure.code, 'storage_unavailable');
  assert.equal(store.getState().persistent, false);
  assert.equal(store.getState().items.length, 2);
});

test('unknown old storage is ignored; corrupt UUID storage blocks writes', () => {
  setupStorage({ 'potsandplanters.enquiry.v1': '{bad', 'potsandplanters.enquiry.v2': envelope([{ productId: A, quantity: 3 }]) });
  const store = freshStore();
  const result = store.add(B, 1);
  assert.equal(result.ok, true);
  assert.equal(store.getState().status, 'ready');
  assert.deepEqual(store.getState().items, [{ productId: A, quantity: 3 }, { productId: B, quantity: 1 }]);
  setupStorage({ 'potsandplanters.enquiry.v2': envelope([{ productId: A, quantity: 1 }, { productId: A, quantity: 2 }]) });
  const corruptV2 = freshStore();
  corruptV2.subscribe(() => {});
  assert.equal(corruptV2.add(B, 1).code, 'corrupt');
  assert.equal(corruptV2.getState().status, 'corrupt');
});

test('read-back mismatch fails and storage events reread UUID storage', () => {
  const fixture = setupStorage();
  const store = freshStore();
  store.subscribe(() => {});
  fixture.setWriteBehavior((key) => fixture.values.set(key, envelope([])));
  const mismatch = store.add(A, 1);
  assert.equal(mismatch.ok, false);
  assert.equal(store.getState().persistent, false);
  fixture.values.set('potsandplanters.enquiry.v2', envelope([{ productId: B, quantity: 4 }]));
  fixture.values.set('potsandplanters.enquiry.v1', JSON.stringify({ version: 1, items: [{ productId: 'old-id', sku: 'same', quantity: 1 }] }));
  fixture.handlers.get('storage')({ key: 'potsandplanters.enquiry.v2' });
  assert.deepEqual(store.getState().items, [{ productId: B, quantity: 4 }]);
  assert.equal(store.getState().status, 'ready');
});
