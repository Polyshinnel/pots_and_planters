const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const ts = require('typescript');
const Module = require('node:module');
const resolve = Module._resolveFilename;
Module._resolveFilename = function (id, ...args) { return resolve.call(this, id.startsWith('@/') ? path.join(__dirname, '../src', id.slice(2)) : id, ...args); };
require.extensions['.ts'] = (module, filename) => module._compile(ts.transpileModule(fs.readFileSync(filename, 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText, filename);
const api = require('../src/lib/enquiry/submit-v2.ts');
const A = '123e4567-e89b-42d3-a456-426614174000';
const B = '123e4567-e89b-42d3-a456-426614174001';
const product = { type: 'product_enquiry', apiVersion: 2, product: { productId: A, quantity: 3 }, name: 'Alex', phone: '+971 50 123 4567', email: 'alex@example.test', company: null, message: 'Quote', source: { pagePath: '/product/a', placement: 'product' }, ignored: 'nope' };
const cart = { type: 'cart_enquiry', apiVersion: 2, items: [{ productId: B, quantity: 2 }, { productId: A, quantity: 1 }], name: 'Alex', phone: '+971501234567', email: 'alex@example.test' };
function fixture(seed) {
  const map = new Map(seed ? [['potsandplanters.enquiry.submit-attempt.v2', seed]] : []);
  const storage = { getItem: (k) => map.get(k) ?? null, setItem: (k, v) => map.set(k, v) };
  let sequence = 0;
  return { map, manager: api.createAttemptManager({ storage: () => storage, now: () => 1000, random: (bytes) => bytes.fill(++sequence) }) };
}
const mockSha = { subtle: { digest: async (_alg, bytes) => { const input = new Uint8Array(bytes); let hash = 0; for (const byte of input) hash = (hash * 31 + byte) >>> 0; return new Uint8Array(32).fill(hash & 255).buffer; } } };

test('v2 serializer whitelists exact product/cart fields and validates backend boundaries', () => {
  assert.deepEqual(JSON.parse(api.serializeEnquiryV2(product)), { type: 'product_enquiry', apiVersion: 2, name: 'Alex', phone: '+971 50 123 4567', email: 'alex@example.test', company: null, message: 'Quote', source: { pagePath: '/product/a', placement: 'product' }, product: { productId: A, quantity: 3 } });
  assert.deepEqual(JSON.parse(api.serializeEnquiryV2(cart)), { type: 'cart_enquiry', apiVersion: 2, name: 'Alex', phone: '+971501234567', email: 'alex@example.test', items: cart.items });
  assert.throws(() => api.serializeEnquiryV2({ ...cart, items: [...cart.items, cart.items[0]] }));
  assert.throws(() => api.serializeEnquiryV2({ ...product, source: { pagePath: '//evil.test' } }));
});

test('adapter accepts persisted 200 replay and 201 create; malformed success is protocol failure', async () => {
  const oldFetch = global.fetch;
  try {
    for (const status of [200, 201]) {
      global.fetch = async (url, init) => {
        assert.equal(url, '/api/v1/enquiries'); assert.equal(init.headers['Idempotency-Key'], 'key-key-key-key-0001');
        assert.deepEqual(JSON.parse(init.body), JSON.parse(api.serializeEnquiryV2(product)));
        return Response.json({ data: { status: 'persisted', enquiryId: A, notificationStatus: 'skipped_disabled' } }, { status });
      };
      const result = await api.submitProductEnquiryV2(product, 'key-key-key-key-0001');
      assert.equal(result.ok, true); assert.equal(result.replayed, status === 200);
    }
    global.fetch = async () => Response.json({ data: { status: 'queued', enquiryId: A } }, { status: 201 });
    const malformed = await api.submitProductEnquiryV2(product, 'key-key-key-key-0001');
    assert.equal(malformed.code, 'protocol_error'); assert.equal(malformed.uncertain, true);
  } finally { global.fetch = oldFetch; }
});

test('adapter maps validation, unavailable UUIDs and conflict, and uncertain network/server outcomes', async () => {
  const oldFetch = global.fetch;
  try {
    global.fetch = async () => Response.json({ error: { code: 'validation_failed', requestId: 'req-1', fields: { email: ['Invalid'], name: 'Required' } } }, { status: 422 });
    let result = await api.submitCartEnquiryV2(cart, 'key-key-key-key-0001');
    assert.equal(result.fieldErrors.email[0], 'Invalid'); assert.equal(result.requestId, 'req-1'); assert.equal(result.uncertain, false);
    global.fetch = async () => Response.json({ error: { code: 'products_unavailable', products: [A] } }, { status: 422 });
    result = await api.submitCartEnquiryV2(cart, 'key-key-key-key-0001'); assert.deepEqual(result.unavailableProductIds, [A]);
    global.fetch = async () => Response.json({ error: { code: 'idempotency_conflict' } }, { status: 409 });
    result = await api.submitCartEnquiryV2(cart, 'key-key-key-key-0001'); assert.equal(result.code, 'idempotency_conflict');
    for (const status of [429, 503]) { global.fetch = async () => Response.json({ error: { code: 'limited' } }, { status }); result = await api.submitCartEnquiryV2(cart, 'key-key-key-key-0001'); assert.equal(result.ok, false); assert.equal(result.retryable, true); }
    global.fetch = async () => { throw new Error('offline'); };
    result = await api.submitCartEnquiryV2(cart, 'key-key-key-key-0001'); assert.equal(result.uncertain, true);
  } finally { global.fetch = oldFetch; }
});

test('manager stores metadata only, reuses key after reload, and keeps scenarios independent', async () => {
  const { map, manager } = fixture();
  const fingerprint = await api.fingerprintEnquiryV2(product);
  const created = manager.prepareAttempt('product_enquiry', fingerprint);
  assert.equal(created.ok, true); assert.match(created.attempt.key, /^[A-Za-z0-9._:-]{16,128}$/);
  const raw = map.get('potsandplanters.enquiry.submit-attempt.v2');
  assert.deepEqual(Object.keys(JSON.parse(raw).attempts.product_enquiry).sort(), ['createdAt', 'key', 'payloadFingerprint', 'status']);
  for (const value of [product.name, product.phone, product.email, product.message, A]) assert.ok(!raw.includes(value));
  const reload = api.createAttemptManager({ storage: () => ({ getItem: (k) => map.get(k) ?? null, setItem: (k, v) => map.set(k, v) }), now: () => 1001, random: (b) => b.fill(9) });
  assert.equal(reload.prepareAttempt('product_enquiry', fingerprint).attempt.key, created.attempt.key);
  const cartFingerprint = await api.fingerprintEnquiryV2(cart, mockSha);
  assert.notEqual(reload.prepareAttempt('cart_enquiry', cartFingerprint).attempt.key, created.attempt.key);
  assert.equal(manager.markPersisted('product_enquiry', created.attempt.key, A).ok, true);
  assert.equal(reload.prepareAttempt('product_enquiry', fingerprint).code, 'already_persisted');
});

test('fingerprint retains cart order and is SHA-256; attempt change/expiry/conflict require explicit action', async () => {
  const first = await api.fingerprintEnquiryV2(cart, mockSha);
  const second = await api.fingerprintEnquiryV2({ ...cart, items: [...cart.items].reverse() }, mockSha);
  assert.notEqual(first, second); // item ordering is part of the canonical payload
  const { manager } = fixture(); const created = manager.prepareAttempt('cart_enquiry', first);
  assert.equal(manager.prepareAttempt('cart_enquiry', 'cd'.repeat(32)).code, 'payload_changed_requires_confirmation');
  assert.equal(manager.markConflict('cart_enquiry', created.attempt.key).ok, true);
  assert.equal(manager.prepareAttempt('cart_enquiry', first).code, 'idempotency_conflict');
  assert.equal(manager.startNewAttempt('cart_enquiry', first, false).code, 'payload_changed_requires_confirmation');
  const newAttempt = manager.startNewAttempt('cart_enquiry', first, true); assert.equal(newAttempt.ok, true); assert.notEqual(newAttempt.attempt.key, created.attempt.key);
  const expired = api.createAttemptManager({ storage: () => ({ getItem: () => JSON.stringify({ version: 1, attempts: { cart_enquiry: { key: 'old-key-000000000000', payloadFingerprint: first, createdAt: 0, status: 'uncertain' } } }), setItem() {} }), now: () => 86400001 });
  assert.equal(expired.prepareAttempt('cart_enquiry', first).code, 'attempt_expired_unknown_outcome');
});

test('storage failures/read-back mismatch block attempt creation and submit orchestration persists before fetch', async () => {
  const broken = api.createAttemptManager({ storage: () => ({ getItem: () => null, setItem() {} }), random: (b) => b.fill(1) });
  assert.equal(broken.prepareAttempt('product_enquiry', 'ab'.repeat(32)).code, 'attempt_storage_unavailable');
  const { manager, map } = fixture(); const original = global.fetch; let storedBeforeFetch = false;
  global.fetch = async () => { storedBeforeFetch = JSON.parse(managerStorageValue(map)).attempts.product_enquiry.status === 'pending'; return Response.json({ error: { code: 'persistence_unavailable' } }, { status: 503 }); };
  try {
    const result = await api.submitWithAttempt(product, manager);
    assert.equal(storedBeforeFetch, true); assert.equal(result.result.uncertain, true);
    const record = JSON.parse(managerStorageValue(map)); assert.equal(record.attempts.product_enquiry.status, 'uncertain');
  } finally { global.fetch = original; }
});

test('reload of an unchanged persisted Product payload restores its reference without another request', async () => {
  const { manager } = fixture();
  const fingerprint = await api.fingerprintEnquiryV2(product);
  const created = manager.prepareAttempt('product_enquiry', fingerprint);
  assert.equal(created.ok, true);
  manager.markPersisted('product_enquiry', created.attempt.key, '123e4567-e89b-42d3-a456-426614174099');
  const originalFetch = global.fetch;
  global.fetch = async () => { throw new Error('persisted attempt must not send'); };
  try {
    const result = await api.submitWithAttempt(product, manager);
    assert.equal(result.ok, true);
    assert.equal(result.result.enquiryId, '123e4567-e89b-42d3-a456-426614174099');
  } finally { global.fetch = originalFetch; }
});

test('reload of an unchanged persisted Cart payload restores its reference without another request', async () => {
  const { manager } = fixture();
  const fingerprint = await api.fingerprintEnquiryV2(cart);
  const created = manager.prepareAttempt('cart_enquiry', fingerprint);
  assert.equal(created.ok, true);
  manager.markPersisted('cart_enquiry', created.attempt.key, '123e4567-e89b-42d3-a456-426614174099');
  const originalFetch = global.fetch;
  global.fetch = async () => { throw new Error('persisted attempt must not send'); };
  try {
    const result = await api.submitWithAttempt(cart, manager);
    assert.equal(result.ok, true);
    assert.equal(result.result.enquiryId, '123e4567-e89b-42d3-a456-426614174099');
  } finally { global.fetch = originalFetch; }
});

function managerStorageValue(map) { return map.get('potsandplanters.enquiry.submit-attempt.v2'); }

test('attempt module import performs no browser storage access during SSR', () => {
  const previous = global.window; delete global.window;
  try { assert.doesNotThrow(() => api.createAttemptManager()); } finally { global.window = previous; }
});
