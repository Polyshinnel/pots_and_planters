const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const ts = require('typescript');
const Module = require('node:module');
const resolve = Module._resolveFilename;
Module._resolveFilename = function (id, ...args) { return resolve.call(this, id.startsWith('@/') ? path.join(__dirname, '../src', id.slice(2)) : id, ...args); };
require.extensions['.ts'] = (module, filename) => module._compile(ts.transpileModule(fs.readFileSync(filename, 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText, filename);
const { validateLead } = require('../src/lib/forms/validation.ts');
const { buildContactPayload, buildProductPayload, buildCataloguePayload } = require('../src/lib/forms/payloads.ts');
const { catalogueRoles } = require('../src/lib/forms/contracts.ts');
const { MAX_ENQUIRY_QUANTITY } = require('../src/lib/enquiry/domain.ts');
const { catalogues } = require('../src/data/catalogues.mock.ts');
const { products } = require('../src/data/catalog.mock.ts');
const values = { name: ' Alex\0 ', phone: ' +44 (20) 7123-4567 ', email: ' alex@example.test ', company: ' Studio ', message: ' Hello\nWorld ', role: 'architect', roleOther: '' };

test('required fields, whitespace, email, international phones and lengths', () => {
  assert.deepEqual(validateLead(values, 'contact'), {});
  for (const field of ['name', 'phone', 'email', 'message']) assert.ok(validateLead({ ...values, [field]: ' \t ' }, 'contact')[field]);
  for (const email of ['a@b', 'a@@b.test', 'a b@example.test', 'a@b.test\nx']) assert.ok(validateLead({ ...values, email }, 'contact').email);
  for (const phone of ['+971 50 123 4567', '+1 (202) 555-0123', '+49 30 12345678', '020 7123 4567']) assert.equal(validateLead({ ...values, phone }, 'contact').phone, undefined);
  for (const phone of ['123', '++123456789', '12+3456789', 'phone1234567', '1'.repeat(16)]) assert.ok(validateLead({ ...values, phone }, 'contact').phone);
  for (const [field, max] of [['name', 200], ['email', 254], ['phone', 60], ['company', 200], ['message', 5000]]) assert.ok(validateLead({ ...values, [field]: 'x'.repeat(max + 1) }, 'contact')[field]);
});
test('optional message/company and boundary sanitization produce minimal immutable snapshots', () => {
  const payload = buildContactPayload(values, { pagePath: '/contacts?email=secret#fragment', placement: ' contact ' });
  assert.equal(payload.type, 'contact');
  assert.equal(payload.name, 'Alex');
  assert.equal(payload.message, 'Hello\nWorld');
  assert.deepEqual(payload.source, { pagePath: '/contacts', placement: 'contact' });
  assert.equal(payload.email, 'alex@example.test');
  assert.ok(!('createdAt' in payload));
  assert.equal(values.name, ' Alex\0 ');
  assert.throws(() => buildContactPayload({ ...values, message: '' }));
  assert.throws(() => buildContactPayload(values, { pagePath: 'https://example.test' }));
  const product = buildProductPayload({ ...values, company: '', message: '' }, products[0], '2');
  assert.ok(!('company' in product));
  assert.ok(!('message' in product));
});
test('single-product payload preserves actual identifiers and quantity boundaries', () => {
  const product = products[0];
  const payload = buildProductPayload(values, product, '3');
  assert.deepEqual([payload.type, payload.productId, payload.sku, payload.quantity], ['product_enquiry', product.id, product.sku, 3]);
  for (const quantity of ['', ' ', '0', '-1', '1.5', 'NaN', 'Infinity', String(MAX_ENQUIRY_QUANTITY + 1)]) assert.throws(() => buildProductPayload(values, product, quantity));
  assert.equal(buildProductPayload(values, product, String(MAX_ENQUIRY_QUANTITY)).quantity, MAX_ENQUIRY_QUANTITY);
  assert.throws(() => buildProductPayload(values, { ...product, id: '' }, '1'));
  assert.throws(() => buildProductPayload(values, { ...product, sku: '' }, '1'));
  assert.ok(!('items' in payload));
  assert.ok(!('price' in payload));
});
test('all ten B2B roles are stable, required and validated against runtime input', () => {
  assert.deepEqual(catalogueRoles.map((role) => role.value), ['interior_designer', 'architect', 'landscape_designer', 'contractor', 'hospitality', 'procurement', 'developer', 'reseller', 'private_customer', 'other']);
  for (const { value } of catalogueRoles) {
    const payload = buildCataloguePayload({ ...values, role: value, message: '', roleOther: ' Consultant ' }, catalogues[0]);
    assert.equal(payload.catalogueId, catalogues[0].id);
    assert.equal(payload.role, value);
    assert.equal(payload.roleOther, value === 'other' ? 'Consultant' : undefined);
    assert.equal(payload.type, 'catalogue_download');
    assert.ok(!('pdfUrl' in payload));
  }
  for (const role of ['', 'admin', 'ARCHITECT']) assert.throws(() => buildCataloguePayload({ ...values, role }, catalogues[0]));
  assert.equal(buildCataloguePayload({ ...values, role: 'other', roleOther: '' }, catalogues[0]).roleOther, undefined);
  assert.throws(() => buildCataloguePayload({ ...values, role: 'other', roleOther: 'x'.repeat(201) }, catalogues[0]));
  assert.throws(() => buildCataloguePayload(values, { ...catalogues[0], id: '' }));
});
