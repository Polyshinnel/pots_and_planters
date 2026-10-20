const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const ts = require('typescript');
const Module = require('node:module');
const resolve = Module._resolveFilename;
Module._resolveFilename = function (id, ...args) { return resolve.call(this, id.startsWith('@/') ? path.join(__dirname, '../src', id.slice(2)) : id, ...args); };
require.extensions['.ts'] = (module, filename) => module._compile(ts.transpileModule(fs.readFileSync(filename, 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText, filename);
const { products } = require('../src/data/catalog.mock.ts');
const { readEnquiry, addEnquiryItem, setEnquiryQuantity, MAX_ENQUIRY_QUANTITY } = require('../src/lib/enquiry/domain.ts');
const { deriveEnquiry, resolveEnquiryLine } = require('../src/lib/enquiry/lines.ts');
const { buildEnquiryPayload, validateEnquiryForm } = require('../src/lib/enquiry/submission.ts');
const product = products[0];
const item = { productId: product.id, sku: product.sku, quantity: 2 };
const form = { name: ' Alex ', phone: '+44 (20) 7123-4567', email: ' alex@example.test ', company: ' Project ', comments: ' Hello ' };

test('SKU merge 2 + 3, unit count and unique count use one cart', () => {
  const items = addEnquiryItem([item], { ...item, quantity: 3 });
  assert.deepEqual(items, [{ ...item, quantity: 5 }]);
  assert.equal(deriveEnquiry(items).totalQuantity, 5);
  assert.equal(deriveEnquiry(items).uniqueLineCount, 1);
});
test('quantity boundary rejects empty, zero, negative, fractions, nonfinite and oversized values', () => {
  for (const quantity of ['', undefined, 0, -1, 1.5, NaN, Infinity, MAX_ENQUIRY_QUANTITY + 1]) assert.throws(() => setEnquiryQuantity([item], item.sku, quantity));
  assert.equal(setEnquiryQuantity([item], item.sku, MAX_ENQUIRY_QUANTITY)[0].quantity, MAX_ENQUIRY_QUANTITY);
  assert.equal(item.quantity, 2);
});
test('version 1 restoration handles corruption, old versions, invalid rows and SKU conflicts', () => {
  for (const raw of ['{broken', 'null', '[]', '{"version":0,"items":[]}', '{"version":2,"items":[]}', '{"version":1,"items":{}}']) assert.deepEqual(readEnquiry(raw), []);
  assert.deepEqual(readEnquiry(JSON.stringify({ version: 1, items: [null, item, { ...item, quantity: 3 }, { ...item, productId: 'conflict' }] })), [{ ...item, quantity: 5 }]);
});
test('projection matches existing informational mock prices and handles missing models', () => {
  const line = resolveEnquiryLine(item);
  assert.equal(line.name, product.name);
  assert.equal(line.unitPriceAed, product.priceAed);
  assert.ok(line.href.startsWith('/shop/'));
  assert.ok(line.attributes.every((attribute) => ['Dimensions', 'Finish'].includes(attribute.label)));
  const unknown = resolveEnquiryLine({ productId: 'removed', sku: 'old', quantity: 1 });
  assert.equal(unknown.available, false);
  assert.equal(unknown.href, null);
  assert.equal(unknown.unitPriceAed, null);
});
test('known and unknown subtotals use only verified numeric prices', () => {
  const previous = { priceVerified: product.priceVerified, priceAed: product.priceAed };
  try {
    product.priceVerified = true;
    product.priceAed = 125;
    const known = deriveEnquiry([item]);
    assert.equal(known.knownPriceSubtotalAed, 250);
    assert.equal(known.hasUnpricedItems, false);
    const mixed = deriveEnquiry([item, { productId: 'unknown', sku: 'unknown', quantity: 4 }]);
    assert.equal(mixed.knownPriceSubtotalAed, 250);
    assert.equal(mixed.hasUnpricedItems, true);
    product.priceAed = NaN;
    assert.equal(resolveEnquiryLine(item).unitPriceAed, null);
  } finally { Object.assign(product, previous); }
});
test('required form validation supports international phones and optional fields', () => {
  assert.deepEqual(validateEnquiryForm(form), {});
  assert.deepEqual(Object.keys(validateEnquiryForm({ ...form, name: '', phone: '', email: '' })).sort(), ['email', 'name', 'phone']);
  for (const phone of ['+971 50 123 4567', '+1 (202) 555-0123', '+49 30 12345678', '020 7123 4567']) assert.equal(validateEnquiryForm({ ...form, phone }).phone, undefined);
  for (const phone of ['abc12345678', '123', '++971501234567', '12+345678', '1234567890123456']) assert.ok(validateEnquiryForm({ ...form, phone }).phone);
  for (const email of ['bad', 'x@', 'x@y', 'x y@example.test']) assert.ok(validateEnquiryForm({ ...form, email }).email);
  assert.ok(validateEnquiryForm({ ...form, comments: 'x'.repeat(5001) }).comments);
});
test('payload snapshots selection and trims contact data without altering the cart', () => {
  const cart = [item];
  const payload = buildEnquiryPayload(cart, form);
  assert.equal(payload.version, 1);
  assert.equal(payload.currency, 'AED');
  assert.deepEqual(payload.contact, { name: 'Alex', phone: form.phone, email: 'alex@example.test', company: 'Project', comments: 'Hello' });
  assert.deepEqual(payload.items[0], { lineKey: item.sku, productId: item.productId, sku: item.sku, name: product.name, quantity: 2, attributes: resolveEnquiryLine(item).attributes, informationalUnitPriceAed: product.priceAed });
  payload.items[0].quantity = 99;
  assert.equal(item.quantity, 2);
  assert.throws(() => buildEnquiryPayload([], form));
  assert.throws(() => buildEnquiryPayload(cart, { ...form, email: 'bad' }));
  assert.throws(() => buildEnquiryPayload([item, item], form));
  assert.deepEqual(cart, [item]);
});
test('store edits synchronously, persists, clamps stepper and keeps memory on storage failure', () => {
  let saved = JSON.stringify({ version: 1, items: [item] });
  global.window = { localStorage: { getItem: () => saved, setItem: (_key, value) => { saved = value; } } };
  const { enquiryStore: store } = require('../src/lib/enquiry/store.ts');
  store.increment(item.sku);
  assert.equal(store.getTotalQuantity(), 3);
  store.decrement(item.sku); store.decrement(item.sku); store.decrement(item.sku);
  assert.equal(store.getItems()[0].quantity, 1);
  store.setQuantity(item.sku, MAX_ENQUIRY_QUANTITY); store.increment(item.sku);
  assert.equal(store.getTotalQuantity(), MAX_ENQUIRY_QUANTITY);
  assert.throws(() => store.setQuantity(item.sku, NaN));
  store.setQuantity(item.sku, 5);
  assert.equal(readEnquiry(saved)[0].quantity, 5);
  buildEnquiryPayload(store.getItems(), form);
  assert.equal(store.getTotalQuantity(), 5);
  window.localStorage.setItem = () => { throw Error('Blocked'); };
  assert.equal(store.setQuantity(item.sku, 6).persistent, false);
  assert.equal(store.getTotalQuantity(), 6);
  store.removeItem(item.sku);
  assert.equal(store.getItems().length, 0);
  store.addItem(item.productId, item.sku, 2);
  store.clearCart();
  assert.equal(store.getTotalQuantity(), 0);
  delete global.window;
});
