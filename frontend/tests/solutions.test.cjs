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
const { solutions, solutionScenes } = require('../src/data/solutions.mock.ts');
const { solutionsRepository } = require('../src/lib/solutions/repository.ts');
const { resolveScene } = require('../src/lib/solutions/domain.ts');
const { MAX_ENQUIRY_QUANTITY, readEnquiry } = require('../src/lib/enquiry/domain.ts');

test('five original anchors and two concepts resolve real catalogue identities, links and local images', async () => {
  const categories = await solutionsRepository.getSolutionCategories();
  assert.deepEqual(categories.map((c) => c.slug), ['hospitality', 'restaurants', 'offices', 'residential', 'retail']);
  const scenes = (await Promise.all(categories.map((c) => solutionsRepository.getSolutionScenes(c.id)))).flat();
  assert.ok(new Set(scenes.map((s) => s.categoryId)).size >= 2);
  const ids = scenes.flatMap((s) => [s.id, ...s.images.map((i) => i.id), ...s.items.map((i) => i.id), ...s.hotspots.map((h) => h.id)]);
  assert.equal(ids.length, new Set(ids).size);
  for (const scene of scenes) {
    assert.equal(scene.isConcept, true);
    assert.ok(scene.images.length && scene.items.length && scene.hotspots.length);
    for (const image of scene.images) assert.ok(fs.existsSync(path.join(__dirname, '../public', image.src)));
    for (const item of scene.items) {
      assert.ok(item.product && item.href);
      assert.equal(item.matchType, 'suggested');
      assert.ok(products.some((p) => p.id === item.product.id && p.sku === item.product.sku));
      assert.ok(item.href.endsWith('/' + item.product.slug));
    }
  }
  assert.deepEqual(await solutionsRepository.getSolutionScenes('offices'), []);
  assert.deepEqual(await solutionsRepository.getSolutionScenes('unknown'), []);
});
test('draft scenes and unpublished categories stay out of reads', async () => {
  const scene = solutionScenes[0], category = solutions[0];
  try {
    scene.status = 'draft';
    assert.deepEqual(await solutionsRepository.getSolutionScenes(category.id), []);
    scene.status = 'published'; category.isPublished = false;
    assert.deepEqual(await solutionsRepository.getSolutionScenes(category.id), []);
  } finally { scene.status = 'published'; category.isPublished = true; }
});
test('missing data, invalid quantities, cross-image/item references and unsafe coordinates are handled', () => {
  const scene = structuredClone(solutionScenes[0]);
  scene.images.push({ ...scene.images[0], id: 'second-image', sortOrder: -1 });
  scene.hotspots.push({ ...scene.hotspots[0], id: 'second-marker', imageId: 'second-image' });
  for (const [index, override] of [{ x: -1 }, { y: 1.01 }, { x: NaN }, { imageId: 'foreign' }, { solutionItemId: 'foreign' }].entries()) scene.hotspots.push({ ...scene.hotspots[0], id: `invalid-${index}`, ...override });
  const resolved = resolveScene(scene, products);
  assert.equal(resolved.images[0].id, 'second-image');
  assert.equal(resolved.hotspots.length, 3);
  assert.equal(resolved.hotspots.filter((h) => h.imageId === 'second-image').length, 1);
  scene.items[0].matchType = 'exact';
  assert.equal(resolveScene(scene, products).items[0].matchType, 'suggested');
  scene.items[0].recommendedQuantity = 1.5;
  assert.equal(resolveScene(scene, products).items.length, 1);
  scene.images = [];
  assert.equal(resolveScene(scene, products).hotspots.length, 0);
  assert.equal(resolveScene(scene, products).coverImageId, null);
  assert.equal(resolveScene(solutionScenes[0], []).hotspots.length, 0);
  assert.ok(resolveScene(solutionScenes[0], []).items.every((i) => !i.product && !i.href));
});
test('composition add is atomic, additive by SKU and persisted by FE-05', () => {
  let saved = null;
  global.window = { localStorage: { getItem: () => saved, setItem: (_key, value) => { saved = value; } } };
  const { enquiryStore: store } = require('../src/lib/enquiry/store.ts');
  const items = solutionScenes[0].items.map((item) => ({ productId: item.productId, sku: products.find((p) => p.id === item.productId).sku, quantity: item.recommendedQuantity }));
  store.addItems(items); store.addItems(items);
  assert.equal(store.getItems().length, 2);
  assert.equal(store.getTotalQuantity(), 6);
  assert.deepEqual(readEnquiry(saved), store.getItems());
  const before = saved;
  assert.throws(() => store.addItems([items[0], { ...items[1], quantity: MAX_ENQUIRY_QUANTITY }]));
  assert.equal(saved, before);
  assert.equal(store.getTotalQuantity(), 6);
  window.localStorage.setItem = () => { throw Error('blocked'); };
  assert.equal(store.addItems(items).persistent, false);
  assert.equal(store.getTotalQuantity(), 9);
  delete global.window;
});
