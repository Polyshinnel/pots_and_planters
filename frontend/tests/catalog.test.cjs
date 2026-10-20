const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const ts = require('typescript');
const Module = require('node:module');
// Compile domain fixtures in the test process using the already installed TypeScript.
const resolve = Module._resolveFilename;
Module._resolveFilename = function (id, ...args) {
  return resolve.call(this, id.startsWith('@/') ? path.join(__dirname, '../src', id.slice(2)) : id, ...args);
};
require.extensions['.ts'] = (module, filename) => module._compile(ts.transpileModule(fs.readFileSync(filename, 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText, filename);
const domain = require('../src/lib/catalog/domain.ts');
const { products, categories } = require('../src/data/catalog.mock.ts');
const { catalogues } = require('../src/data/catalogues.mock.ts');
const { selectCatalogues, cataloguesPath } = require('../src/lib/catalog/catalogues.ts');
const { catalogRepository } = require('../src/lib/catalog/repository.ts');
const query = (overrides = {}) => ({ filters: {}, sort: 'featured', page: 1, perPage: 12, ...overrides });
const select = (q) => domain.selectProducts(products, query(q));

test('published products paginate and hidden products are excluded', () => {
  assert.equal(select().total, 16);
  assert.equal(select().products.length, 12);
  assert.equal(select({ page: 2 }).products.length, 4);
  assert.equal(select({ page: 99 }).page, 2);
  assert.equal(select({ page: -4 }).page, 1);
});
test('top-level categories select assigned products once', () => {
  const outdoor = select({ categoryId: 'outdoor', perPage: 99 });
  assert.equal(outdoor.total, 9);
  assert.equal(new Set(outdoor.products.map((p) => p.id)).size, 9);
  assert.equal(select({ categoryId: 'outdoor-floor' }).total, 0);
  assert.ok(select({ categoryId: 'indoor' }).products.some((p) => p.id === '25526'));
  assert.equal(select({ categoryId: 'artificial', perPage: 99 }).total, 2);
});
test('OR inside a group, AND across groups, accurate no-results and clearing', () => {
  const a = select({ filters: { color: ['black'] } }).total;
  const b = select({ filters: { color: ['bronze'] } }).total;
  assert.equal(select({ filters: { color: ['black', 'bronze'] } }).total, a + b);
  const result = select({ filters: { color: ['bronze'], material: ['fiberglass'] } });
  assert.ok(result.total > 0 && result.total < b);
  assert.ok(result.products.every((p) => p.attributes.material.includes('fiberglass') && p.attributes.color.includes('bronze')));
  assert.equal(select({ filters: { color: ['bronze'], material: ['synthetic'] } }).total, 0);
  assert.equal(select({ filters: {} }).total, 16);
});
test('facet options come only from the category scope', () => {
  const result = select({ categoryId: 'artificial' });
  assert.deepEqual(result.facets.find((f) => f.key === 'material').values, ['synthetic']);
  assert.deepEqual(result.facets.find((f) => f.key === 'color').values, ['green']);
});
test('query parser ignores unknown keys/values, canonicalizes repeated and comma selections', () => {
  const scope = select();
  const parsed = domain.parseQuery(new URLSearchParams('material=unknown&color=bronze,black&color=black&sort=bogus&page=-2&evil=1'), scope.facets, scope.sorts);
  assert.deepEqual(parsed.filters, { color: ['black', 'bronze'] });
  assert.equal(parsed.page, 1);
  assert.equal(parsed.sort, 'featured');
  assert.equal(domain.queryString(parsed), '?color=black%2Cbronze');
  assert.deepEqual(domain.parseQuery(new URLSearchParams(domain.queryString(parsed)), scope.facets, scope.sorts).filters, parsed.filters);
  for (const page of ['1.5', 'NaN', 'Infinity', '9007199254740992']) assert.equal(domain.parseQuery(new URLSearchParams({ page }), scope.facets, scope.sorts).page, 1);
});
test('stable name sorting works in both directions', () => {
  const asc = select({ sort: 'name-asc', perPage: 99 }).products;
  const desc = select({ sort: 'name-desc', perPage: 99 }).products;
  assert.ok(asc[0].name.localeCompare(asc.at(-1).name, 'en') < 0);
  assert.equal(desc[0].name, asc.at(-1).name);
  assert.deepEqual(select().products.map((p) => p.id), select().products.map((p) => p.id));
});
test('mock fixture prices stay in range; numeric sorting and enquiry handling work', () => {
  assert.ok(products.filter((p) => p.published).every((p) => p.priceAed >= 100 && p.priceAed <= 2000));
  assert.ok(select().sorts.some((s) => s.value === 'price-asc'));
  const priced = [null, 50, 100, 0].map((priceAed, i) => ({ ...products[0], id: String(i), priceAed }));
  for (const [sort, expected] of [['price-asc', [0, 50, 100, null]], ['price-desc', [100, 50, 0, null]]]) {
    assert.deepEqual(domain.selectProducts(priced, query({ sort })).products.map((p) => p.priceAed), expected);
  }
  assert.equal(domain.formatPrice(null), 'Price on enquiry');
  assert.equal(domain.formatPrice(50), 'AED 50');
  assert.equal(domain.formatPrice(1462), 'AED 1,462');
  assert.equal(domain.formatPrice(1462.5), 'AED 1,462.5');
});
test('route and fixture integrity: unique top-level categories, IDs/slugs, assignments, and local images', () => {
  assert.equal(new Set(categories.map((c) => c.path)).size, categories.length);
  assert.deepEqual(categories.map((category) => category.id), ['outdoor', 'indoor', 'artificial']);
  assert.equal(new Set(products.map((p) => p.id)).size, products.length);
  assert.equal(new Set(products.map((p) => p.slug)).size, products.length);
  for (const product of products) {
    assert.ok(product.categoryIds.every((id) => categories.some((c) => c.id === id)));
    if (product.image) assert.ok(fs.existsSync(path.join(__dirname, '../public', product.image)));
  }
});

test('catalogues exclude drafts, support multiple categories and require explicit relationships', () => {
  const shared = { ...catalogues[0], categoryIds: ['outdoor', 'indoor'] };
  const draft = { ...shared, id: 'draft', isPublished: false };
  assert.deepEqual(selectCatalogues([draft, shared]), [shared]);
  for (const categoryId of ['outdoor', 'indoor']) assert.deepEqual(selectCatalogues([draft, shared], categoryId), [shared]);
  for (const categoryId of ['unknown', '']) assert.deepEqual(selectCatalogues([draft, shared], categoryId), []);
  assert.deepEqual(selectCatalogues([]), []);
});
test('catalogue repository returns published relationships independently of product results', async () => {
  assert.equal((await catalogRepository.getCatalogues()).length, 6);
  assert.equal((await catalogRepository.getCataloguesByCategory('outdoor'))[0].id, 'royal');
  assert.deepEqual(await catalogRepository.getCataloguesByCategory('unknown'), []);
  assert.deepEqual(await catalogRepository.getCataloguesByCategory('unknown'), []);
  const empty = await catalogRepository.getProducts(query({ categoryId: 'outdoor', filters: { color: ['bronze'], material: ['polystone'], size: ['small'] } }));
  assert.equal(empty.total, 0);
  assert.ok((await catalogRepository.getCataloguesByCategory('outdoor')).length > 0);
});
test('catalogue navigation uses category slugs and safely encodes query values', () => {
  assert.equal(cataloguesPath(), '/catalogues');
  for (const category of categories) assert.equal(new URL(cataloguesPath(category.slug), 'https://example.test').searchParams.get('category'), category.slug);
  assert.equal(cataloguesPath('outdoor-pots'), '/catalogues?category=outdoor-pots');
  assert.equal(new URL(cataloguesPath('a & b/c'), 'https://example.test').searchParams.get('category'), 'a & b/c');
});
test('catalogue fixtures have unique identities, valid category links and existing real covers', () => {
  assert.equal(new Set(catalogues.map((c) => c.id)).size, catalogues.length);
  assert.equal(new Set(catalogues.map((c) => c.slug)).size, catalogues.length);
  for (const catalogue of catalogues) {
    assert.ok(catalogue.title && catalogue.description);
    assert.ok(catalogue.categoryIds.every((id) => categories.some((category) => category.id === id)));
    assert.ok(fs.existsSync(path.join(__dirname, '../public', catalogue.coverImage)));
  }
});
