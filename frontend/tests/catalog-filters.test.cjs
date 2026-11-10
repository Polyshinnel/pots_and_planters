const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const ts = require('typescript');
require.extensions['.ts'] = (module, filename) => module._compile(ts.transpileModule(fs.readFileSync(filename, 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText, filename);
const domain = require('../src/lib/catalog/domain.ts');

test('filter URL parser preserves repeated selections, false, exact text and decimal zero ranges', () => {
  const params = new URLSearchParams('filter%5Bmaterial_type%5D%5B%5D=ceramic&filter%5Bmaterial_type%5D%5B%5D=metal&filter%5Bis_outdoor%5D%5B%5D=false&filter%5Bfinish%5D%5B%5D=Polished&filter%5Bheight%5D%5Bmin%5D=0.000000&filter%5Bheight%5D%5Bmax%5D=2.500000');
  const parsed = domain.parseFilterParams(params);
  assert.equal(parsed.invalid, false);
  assert.deepEqual(parsed.filters, { material_type: ['ceramic', 'metal'], is_outdoor: ['false'], finish: ['Polished'], height: { min: '0.000000', max: '2.500000' } });
  const query = { filters: parsed.filters, sort: 'price-desc', page: 2 };
  const serialized = domain.queryString(query);
  assert.deepEqual(domain.parseFilterParams(new URLSearchParams(serialized)).filters, parsed.filters);
});

test('malformed filters fail closed and unrelated query parameters survive state changes', () => {
  assert.equal(domain.parseFilterParams(new URLSearchParams('filter%5Bheight%5D%5Bmin%5D=')).invalid, true);
  assert.equal(domain.parseFilterParams(new URLSearchParams('filter%5Bheight%5D%5Bwat%5D=1')).invalid, true);
  const query = { filters: { enabled: ['false'] }, sort: 'newest', page: 1 };
  assert.equal(domain.queryString(query, false, false, new URLSearchParams('utm_source=newsletter&sort=newest&page=9')).includes('utm_source=newsletter'), true);
  assert.equal(domain.queryString(query).includes('filter%5Benabled%5D%5B%5D=false'), true);
  assert.equal(domain.parseFilterParams(new URLSearchParams('filter%5Bheight%5D%5Bmin%5D=2&filter%5Bheight%5D%5Bmax%5D=1')).invalid, true);
  assert.equal(domain.parseFilterParams(new URLSearchParams('filter%5Bheight%5D%5Bmin%5D=1.1234567')).invalid, true);
});
