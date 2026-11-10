const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const ts = require('typescript');
const Module = require('node:module');
const resolve = Module._resolveFilename;
Module._resolveFilename = function (id, ...args) { return resolve.call(this, id.startsWith('@/') ? path.join(__dirname, '../src', id.slice(2)) : id, ...args); };
const load = Module._load;
Module._load = function (id, ...args) { return id === 'server-only' ? {} : load.call(this, id, ...args); };
require.extensions['.ts'] = (module, filename) => module._compile(ts.transpileModule(fs.readFileSync(filename, 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText, filename);
const api = require('../src/lib/catalog/products.server.ts');
const { formatPrice } = require('../src/lib/format-price.ts');
const specification = { code: 'diameter', label: 'Diameter', value: '67.00', displayText: 'Ø 67.00 cm' };
function listDto(overrides = {}) {
  return { id: 'public-uuid-1', slug: 'current-planter', sku: 'PP-001', name: 'Current planter', brand: { id: 'brand-uuid', name: 'Studio' }, priceAed: '12345678901234567890.1250', currency: 'AED', categoryIds: ['category-uuid'], primaryCategory: { id: 'category-uuid', slug: 'outdoor-pots', name: 'Outdoor Pots', path: '/shop/outdoor-pots' }, canonicalPath: '/shop/outdoor-pots/current-planter', cardImage: { url: '/storage/ready-card.webp', alt: 'Planter front' }, cardSpecifications: [specification], cardSpecificationsText: specification.displayText, ...overrides };
}
function setFetch(response) { const calls = []; global.fetch = async (url, init) => { calls.push([String(url), init]); return response; }; return calls; }

test('list adapter sends category slug, API sort and pagination; maps only public DTO data', async () => {
  process.env.API_INTERNAL_URL = 'http://backend/api';
  const calls = setFetch({ ok: true, json: async () => ({ data: [listDto()], meta: { page: 2, perPage: 12, total: 20, lastPage: 2, sort: 'name-asc' }, facets: [] }) });
  const result = await api.getProducts({ categoryId: 'category-uuid', filters: {}, sort: 'name-asc', page: 2, perPage: 12 }, 'outdoor-pots');
  const url = new URL(calls[0][0]);
  assert.equal(url.pathname, '/api/v1/products');
  assert.equal(url.searchParams.get('category'), 'outdoor-pots');
  assert.equal(url.searchParams.get('sort'), 'name-asc');
  assert.equal(url.searchParams.get('page'), '2');
  assert.equal(url.searchParams.get('perPage'), '12');
  assert.equal(calls[0][1].cache, 'no-store');
  assert.equal(result.products[0].id, 'public-uuid-1');
  assert.equal(result.products[0].priceAed, '12345678901234567890.1250');
  assert.deepEqual(result.products[0].cardSpecifications, [specification]);
  assert.equal(result.products[0].canonicalPath, '/shop/outdoor-pots/current-planter');
  assert.equal(JSON.stringify(result).includes('storage/private'), false);
  assert.equal(formatPrice(result.products[0].priceAed), 'AED 12,345,678,901,234,567,890.125');
});

test('list adapter serializes repeated options, false and exact decimal bounds using Laravel filter grammar', async () => {
  const calls = setFetch({ ok: true, json: async () => ({ data: [], meta: { page: 1, perPage: 12, total: 0, lastPage: 1, sort: 'newest' }, facets: [
    { code: 'material_type', label: 'Material', type: 'multiselect', displayType: 'multi_options', options: [{ value: 'ceramic & stone', label: 'Ceramic & stone', count: 3 }] },
    { code: 'is_outdoor', label: 'Outdoor', type: 'boolean', displayType: 'boolean', options: [{ value: 'false', label: 'No', count: 2 }] },
    { code: 'height', label: 'Height', type: 'number', displayType: 'range', min: '0.000000', max: '999999999999.125000' },
  ] }) });
  const result = await api.getProducts({ filters: { material_type: ['ceramic & stone', 'metal'], is_outdoor: ['false'], height: { min: '0.000000', max: '999999999999.125000' } }, sort: 'price-desc', page: 3, perPage: 12 });
  const url = new URL(calls[0][0]);
  assert.deepEqual(url.searchParams.getAll('filter[material_type][]'), ['ceramic & stone', 'metal']);
  assert.equal(url.searchParams.get('filter[is_outdoor][]'), 'false');
  assert.equal(url.searchParams.get('filter[height][min]'), '0.000000');
  assert.equal(url.searchParams.get('filter[height][max]'), '999999999999.125000');
  assert.equal(url.searchParams.get('sort'), 'price-desc');
  assert.equal(url.searchParams.get('page'), '3');
  assert.equal(result.facets[0].options[0].count, 3);
  assert.equal(result.facets[2].min, '0.000000');
});

test('null prices and null card images remain explicit and empty 200 is not an error', async () => {
  setFetch({ ok: true, json: async () => ({ data: [listDto({ priceAed: null, cardImage: null })], meta: { page: 1, perPage: 12, total: 1, lastPage: 1, sort: 'newest' }, facets: [] }) });
  const result = await api.getProducts({ filters: {}, sort: 'newest', page: 1, perPage: 12 });
  assert.equal(result.products[0].priceAed, null);
  assert.equal(result.products[0].image, null);
  assert.equal(formatPrice(null), 'Price on enquiry');
  setFetch({ ok: true, json: async () => ({ data: [], meta: { page: 1, perPage: 12, total: 0, lastPage: 1, sort: 'newest' }, facets: [] }) });
  assert.equal((await api.getProducts({ filters: {}, sort: 'newest', page: 1, perPage: 12 })).total, 0);
});

test('featured is normalized to newest at the adapter boundary and detail preserves SEO, HTML, attributes and media', async () => {
  const dto = { ...listDto(), series: null, descriptionHtml: '<p>Sanitized description</p>', seo: { title: 'Manual title', description: null, ogImage: null, robots: 'noindex,follow' }, images: [{ url: '/storage/ready.webp', cardUrl: null, thumbUrl: null, alt: 'Main', order: 0, isMain: true }], attributes: [{ id: 'attr-uuid', code: 'diameter', label: 'Diameter', shortLabel: 'Ø', type: 'number', value: '67.00', unit: 'cm', options: [] }] };
  const calls = setFetch({ ok: true, json: async () => ({ data: dto }) });
  const product = await api.getProductBySlug('current planter');
  assert.equal(new URL(calls[0][0]).pathname, '/api/v1/products/current%20planter');
  assert.equal(product.priceAed, '12345678901234567890.1250');
  assert.equal(product.descriptionHtml, '<p>Sanitized description</p>');
  assert.equal(product.seo.robots, 'noindex,follow');
  assert.equal(product.attributesDto[0].value, '67.00');
  assert.equal(product.images[0].src, '/storage/ready.webp');
  const featuredCalls = setFetch({ ok: true, json: async () => ({ data: [], meta: { page: 1, perPage: 8, total: 0, lastPage: 1, sort: 'newest' }, facets: [] }) });
  await api.getProducts({ filters: {}, sort: 'featured', page: 1, perPage: 8 });
  assert.equal(new URL(featuredCalls[0][0]).searchParams.get('sort'), 'newest');
});

test('404 detail becomes missing product; 422 and 503 stay visible errors', async () => {
  setFetch({ ok: false, status: 404 });
  assert.equal(await api.getProductBySlug('not-here'), undefined);
  for (const status of [422, 503]) {
    setFetch({ ok: false, status });
    await assert.rejects(api.getProducts({ filters: {}, sort: 'newest', page: 1, perPage: 12 }), new RegExp(`API request failed: ${status}`));
  }
});

test('related fetch is bounded to primary category, excludes current public ID, and makes no extra pages', async () => {
  const current = { id: 'self', primaryCategoryId: 'category-uuid', primaryCategorySlug: 'outdoor-pots' };
  const calls = setFetch({ ok: true, json: async () => ({ data: [listDto({ id: 'self' }), listDto({ id: 'other-1' })], meta: { page: 1, perPage: 5, total: 2, lastPage: 1, sort: 'newest' }, facets: [] }) });
  const related = await api.getRelatedProducts(current, 4);
  assert.equal(related.length, 1);
  assert.equal(related[0].id, 'other-1');
  const url = new URL(calls[0][0]);
  assert.equal(url.searchParams.get('category'), 'outdoor-pots');
  assert.equal(url.searchParams.get('sort'), 'newest');
  assert.equal(url.searchParams.get('perPage'), '5');
});
