const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const ts = require('typescript');
const Module = require('node:module');
const resolve = Module._resolveFilename;
Module._resolveFilename = function (id, ...args) { return resolve.call(this, id.startsWith('@/') ? path.join(__dirname, '../src', id.slice(2)) : id, ...args); };
require.extensions['.ts'] = (module, filename) => module._compile(ts.transpileModule(fs.readFileSync(filename, 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText, filename);
const { products, categories } = require('../src/data/catalog.mock.ts');
const { catalogRepository } = require('../src/lib/catalog/repository.ts');
const { productImages, productSpecifications, primaryCategory, selectRelatedProducts } = require('../src/lib/catalog/product-detail.ts');
const { formatSpecifications } = require('../src/lib/catalog/format-specifications.ts');
const { readEnquiry, addEnquiryItem, MAX_ENQUIRY_QUANTITY } = require('../src/lib/enquiry/domain.ts');
const { productWhatsAppUrl } = require('../src/lib/enquiry/whatsapp.ts');
const product = products[0];
const item = { productId: product.id, sku: product.sku, quantity: 2 };

test('all published products resolve; missing and unpublished do not', async () => {
  for (const p of products.filter((p) => p.published)) assert.equal((await catalogRepository.getProductBySlug(p.slug)).id, p.id);
  for (const slug of ['missing', 'unpublished-sample']) assert.equal(await catalogRepository.getProductBySlug(slug), undefined);
});
test('primary category is explicit, with stable category-ID order fallback', () => {
  assert.equal(primaryCategory({ ...product, primaryCategoryId: 'indoor' }, categories).id, 'indoor');
  assert.equal(primaryCategory({ ...product, primaryCategoryId: 'missing' }, [...categories].reverse()).id, 'outdoor');
  assert.equal(primaryCategory({ ...product, categoryIds: [] }, categories), undefined);
});
test('gallery handles empty, single, multiple, sorted and duplicate images', () => {
  assert.equal(productImages(product).length, 1);
  assert.deepEqual(productImages({ ...product, images: [] }), []);
  assert.equal(productImages({ ...product, images: undefined }).length, 1);
  const images = productImages({ ...product, images: [{ src: '/b', alt: 'Side', order: 2 }, { src: '/a', alt: '', order: 1 }, { src: '/b', alt: 'Side', order: 2 }] });
  assert.deepEqual(images.map((i) => i.src), ['/a', '/b']);
  assert.equal(images[0].alt, product.name);
});
test('specifications omit illustrative filter data, blank and invalid values', () => {
  assert.deepEqual(productSpecifications(product).map((r) => r.label), ['Dimensions', 'Finish']);
  assert.deepEqual(productSpecifications({ ...product, specifications: undefined, details: { material: ' ', usage: 'Indoor' } }), [{ label: 'Usage', value: 'Indoor' }]);
  assert.equal(formatSpecifications({ diameterCm: NaN, heightCm: -1, finish: '' }), '');
  assert.equal(formatSpecifications({ diameterCm: 67, heightCm: 67, finish: 'Bronze patina' }), 'Ø 67 × H 67 cm · Bronze patina');
});
test('related products share categories, are published, unique and limited', async () => {
  const entries = await catalogRepository.getRelatedProducts(product);
  assert.equal(entries.length, 4);
  assert.equal(new Set(entries.map((p) => p.sku)).size, entries.length);
  assert.ok(entries.every((p) => p.id !== product.id && p.published && p.categoryIds.some((id) => product.categoryIds.includes(id))));
  assert.deepEqual(selectRelatedProducts([product, { ...products[1], published: false }], product), []);
  assert.deepEqual(selectRelatedProducts(products, { ...product, categoryIds: [] }), []);
});
test('add merges SKU quantity, retains variants, rejects invalid input and overflow', () => {
  const first = addEnquiryItem([], item);
  assert.deepEqual(addEnquiryItem(first, item), [{ ...item, quantity: 4 }]);
  assert.equal(first[0].quantity, 2);
  assert.equal(addEnquiryItem(first, { ...item, sku: 'variant' }).length, 2);
  for (const quantity of [0, -1, 1.5, NaN, Infinity, MAX_ENQUIRY_QUANTITY + 1]) assert.throws(() => addEnquiryItem(first, { ...item, quantity }));
  assert.throws(() => addEnquiryItem([{ ...item, quantity: MAX_ENQUIRY_QUANTITY }], item));
  assert.throws(() => addEnquiryItem(first, { ...item, productId: 'changed' }));
});
test('storage recovers from corrupted, old, invalid and duplicated entries', () => {
  for (const raw of [null, '{broken', 'null', '[]', '{"version":2,"items":[]}', '{"version":1,"items":{}}']) assert.deepEqual(readEnquiry(raw), []);
  assert.deepEqual(readEnquiry(JSON.stringify({ version: 1, items: [null, item, item, { ...item, productId: 'wrong' }, { sku: 'x', quantity: 1 }, { ...item, quantity: -4 }] })), [{ ...item, quantity: 4 }]);
});
test('WhatsApp uses config, encoding, SKU, quantity and absolute URL', () => {
  assert.equal(productWhatsAppUrl(null, product, 2, 'https://example.test'), null);
  assert.equal(productWhatsAppUrl('javascript:bad', product, 2, 'https://example.test'), null);
  const url = new URL(productWhatsAppUrl('https://wa.me/971500000000', { ...product, name: 'A & B / planter' }, 3, 'https://example.test'));
  assert.equal(url.pathname, '/971500000000');
  for (const text of ['A & B / planter', 'Model: 25526', 'quantity: 3', 'https://example.test/product/coral-planter-25526']) assert.ok(url.searchParams.get('text').includes(text));
});
test('shared store persists and keeps selection in memory when storage fails', () => {
  let saved = null;
  global.window = { localStorage: { getItem: () => null, setItem: (_key, value) => { saved = value; } } };
  const { enquiryStore } = require('../src/lib/enquiry/store.ts');
  assert.equal(enquiryStore.addItem(item.productId, item.sku, 2).persistent, true);
  assert.equal(enquiryStore.getTotalQuantity(), 2);
  assert.equal(readEnquiry(saved)[0].quantity, 2);
  window.localStorage.setItem = () => { throw new Error('Blocked'); };
  assert.equal(enquiryStore.addItem(item.productId, item.sku, 1).persistent, false);
  assert.equal(enquiryStore.getItems()[0].quantity, 3);
  delete global.window;
});
