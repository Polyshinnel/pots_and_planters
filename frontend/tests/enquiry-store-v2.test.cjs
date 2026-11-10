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
const { parseV2Envelope, parseLegacyV1Envelope, serializeV2, addV2, removeV2, setV2Quantity } = domain;
const A = '123e4567-e89b-42d3-a456-426614174000';
const B = '123e4567-e89b-42d3-a456-426614174001';
const envelope = (items) => JSON.stringify({ version: 2, items });

test('v2 parser accepts empty and multiple UUID rows and rejects corrupt or noncanonical data', () => {
  assert.deepEqual(parseV2Envelope(envelope([])), { ok: true, value: [] });
  assert.deepEqual(parseV2Envelope(envelope([{ productId: A, quantity: 1 }, { productId: B, quantity: 7 }])), { ok: true, value: [{ productId: A, quantity: 1 }, { productId: B, quantity: 7 }] });
  for (const raw of ['{bad', '{"version":3,"items":[]}', envelope([{ productId: 'sku-1', quantity: 1 }]), envelope([{ productId: A, quantity: 0 }]), envelope([{ productId: A, quantity: -1 }]), envelope([{ productId: A, quantity: 1.5 }]), envelope([{ productId: A, quantity: '1' }]), envelope([{ productId: A, quantity: 1000000 }]), envelope([{ productId: A, quantity: 1, sku: 'x' }]), envelope([{ productId: A, quantity: 1 }, { productId: A, quantity: 2 }]), envelope(Array.from({ length: 101 }, (_, i) => ({ productId: `123e4567-e89b-42d3-a456-${String(i).padStart(12, '0')}`, quantity: 1 })))]) assert.equal(parseV2Envelope(raw).ok, false);
  assert.throws(() => serializeV2([{ productId: A, quantity: 0 }]));
});

test('legacy is separately validated, unresolved and never UUID-migrated or merged by SKU', () => {
  const rows = [{ productId: A, sku: 'SAME', quantity: 2 }, { productId: 'legacy-fixture-7', sku: 'SAME', quantity: 3 }];
  assert.deepEqual(parseLegacyV1Envelope(JSON.stringify({ version: 1, items: rows })), { ok: true, value: rows });
  assert.equal(parseLegacyV1Envelope('{broken').ok, false);
  assert.equal(parseLegacyV1Envelope('{"version":8,"items":[]}').ok, false);
  assert.deepEqual(parseLegacyV1Envelope(JSON.stringify({ version: 1, items: [{ productId: A, sku: 'SAME', quantity: 2 }, { productId: A, sku: 'SAME', quantity: 3 }] })).value.length, 2);
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

test('store commits v2 only, keeps v1 byte-for-byte and reports storage/read-back failures', () => {
  const rawV1 = JSON.stringify({ version: 1, items: [{ productId: A, sku: 'same', quantity: 2 }, { productId: B, sku: 'same', quantity: 4 }] });
  const fixture = setupStorage({ 'potsandplanters.enquiry.v1': rawV1 });
  const store = freshStore();
  assert.equal(store.getState().status, 'not_hydrated');
  store.subscribe(() => {});
  assert.equal(store.add(A, 2).ok, true);
  assert.equal(store.getState().status, 'legacy_present');
  assert.deepEqual(store.getState().legacyItems.map((item) => item.sku), ['same', 'same']);
  assert.equal(fixture.values.get('potsandplanters.enquiry.v1'), rawV1);
  assert.equal(fixture.values.get('potsandplanters.enquiry.v2'), envelope([{ productId: A, quantity: 2 }]));
  fixture.setWriteBehavior(() => { throw new Error('quota'); });
  const failure = store.add(B, 1);
  assert.equal(failure.ok, false);
  assert.equal(failure.code, 'storage_unavailable');
  assert.equal(store.getState().persistent, false);
  assert.equal(store.getState().items.length, 2);
});

test('corrupt v1/v2 blocks writes without blocking independent valid v2 reads', () => {
  setupStorage({ 'potsandplanters.enquiry.v1': '{bad', 'potsandplanters.enquiry.v2': envelope([{ productId: A, quantity: 3 }]) });
  const store = freshStore();
  const result = store.add(B, 1);
  assert.equal(result.ok, false);
  assert.equal(store.getState().status, 'corrupt');
  assert.deepEqual(store.getState().items, [{ productId: A, quantity: 3 }]);
  assert.equal(store.getState().v1Corrupt, true);
  setupStorage({ 'potsandplanters.enquiry.v2': envelope([{ productId: A, quantity: 1 }, { productId: A, quantity: 2 }]) });
  const corruptV2 = freshStore();
  corruptV2.subscribe(() => {});
  assert.equal(corruptV2.add(B, 1).code, 'corrupt');
  assert.equal(corruptV2.getState().status, 'corrupt');
});

test('read-back mismatch fails and storage events reread both namespaces', () => {
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
  assert.equal(store.getState().status, 'legacy_present');
});

test('explicit legacy removal targets one source index, preserves order and keeps the v1 envelope', () => {
  const rows = [{ productId: A, sku: 'DUP', quantity: 2 }, { productId: B, sku: 'DUP', quantity: 4 }, { productId: 'legacy', sku: 'LAST', quantity: 1 }];
  const original = JSON.stringify({ version: 1, items: rows });
  const fixture = setupStorage({ 'potsandplanters.enquiry.v1': original });
  const store = freshStore();
  store.subscribe(() => {});
  const removed = store.removeLegacyAt(1);
  assert.equal(removed.ok, true);
  assert.deepEqual(JSON.parse(fixture.values.get('potsandplanters.enquiry.v1')), { version: 1, items: [rows[0], rows[2]] });
  assert.deepEqual(store.getState().legacyItems.map((item) => [item.sourceIndex, item.sku]), [[0, 'DUP'], [1, 'LAST']]);
  assert.equal(fixture.values.has('potsandplanters.enquiry.v2'), false);
});

test('legacy remove refuses corrupt bytes and leaves v1 unchanged on storage write failure', () => {
  const corrupt = '{broken';
  let fixture = setupStorage({ 'potsandplanters.enquiry.v1': corrupt });
  let store = freshStore(); store.subscribe(() => {});
  assert.equal(store.removeLegacyAt(0).ok, false);
  assert.equal(fixture.values.get('potsandplanters.enquiry.v1'), corrupt);
  const original = JSON.stringify({ version: 1, items: [{ productId: A, sku: 'DUP', quantity: 1 }] });
  fixture = setupStorage({ 'potsandplanters.enquiry.v1': original });
  store = freshStore(); store.subscribe(() => {});
  fixture.setWriteBehavior(() => { throw new Error('quota'); });
  const failed = store.removeLegacyAt(0);
  assert.equal(failed.ok, false);
  assert.equal(failed.code, 'storage_unavailable');
  assert.equal(fixture.values.get('potsandplanters.enquiry.v1'), original);
  assert.equal(store.getState().legacyItems.length, 1);
});

test('legacy removal refuses a stale displayed snapshot after external storage changes', () => {
  const original = JSON.stringify({ version: 1, items: [{ productId: A, sku: 'FIRST', quantity: 1 }] });
  const fixture = setupStorage({ 'potsandplanters.enquiry.v1': original });
  const store = freshStore(); store.subscribe(() => {});
  const changed = JSON.stringify({ version: 1, items: [{ productId: B, sku: 'OTHER', quantity: 1 }] });
  fixture.values.set('potsandplanters.enquiry.v1', changed);
  assert.equal(store.removeLegacyAt(0).ok, false);
  assert.equal(fixture.values.get('potsandplanters.enquiry.v1'), changed);
});
