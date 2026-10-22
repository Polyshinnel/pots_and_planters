const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const ts = require('typescript');
const Module = require('node:module');

const resolve = Module._resolveFilename;
Module._resolveFilename = function (id, ...args) {
  return resolve.call(this, id.startsWith('@/') ? path.join(__dirname, '../src', id.slice(2)) : id, ...args);
};
const load = Module._load;
Module._load = function (id, ...args) {
  return id === 'server-only' ? {} : load.call(this, id, ...args);
};
require.extensions['.ts'] = (module, filename) => module._compile(ts.transpileModule(fs.readFileSync(filename, 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText, filename);

const { getContactsResult } = require('../src/lib/contacts.server.ts');
const deva = {
  id: 'deva-harmony-trading', name: 'DEVA HARMONY TRADING', description: null,
  address: 'Dubai', city: 'Dubai', country: 'UAE', phones: [{ label: '+971 50 000 0000', href: 'tel:+971500000000' }],
  emails: [], instagram: null,
};
const originalFetch = global.fetch;
const originalError = console.error;

test('loads the Laravel contacts envelope with no-store and preserves API showroom order', async () => {
  let request;
  process.env.API_INTERNAL_URL = 'http://api.test/api';
  global.fetch = async (url, init) => {
    request = { url, init };
    return new Response(JSON.stringify({ data: { primary: deva, whatsappUrl: 'https://api.whatsapp.com/send/?phone=971500000000', showrooms: [deva] } }), { status: 200 });
  };
  const result = await getContactsResult();
  assert.deepEqual(result, { status: 'success', data: { primary: deva, whatsappUrl: 'https://api.whatsapp.com/send/?phone=971500000000', showrooms: [deva] } });
  assert.equal(request.url, 'http://api.test/api/v1/contacts');
  assert.equal(request.init.cache, 'no-store');
});

test('keeps a successful empty response distinct from an API failure', async () => {
  process.env.API_INTERNAL_URL = 'http://api.test/api';
  global.fetch = async () => new Response(JSON.stringify({ data: { primary: null, whatsappUrl: null, showrooms: [] } }), { status: 200 });
  assert.deepEqual(await getContactsResult(), { status: 'success', data: { primary: null, whatsappUrl: null, showrooms: [] } });
});

test('returns an explicit error state for HTTP and network failures', async () => {
  process.env.API_INTERNAL_URL = 'http://api.test/api';
  console.error = () => {};
  global.fetch = async () => new Response('unavailable', { status: 503 });
  assert.deepEqual(await getContactsResult(), { status: 'error' });
  global.fetch = async () => { throw new TypeError('network down'); };
  assert.deepEqual(await getContactsResult(), { status: 'error' });
});

test.after(() => {
  global.fetch = originalFetch;
  console.error = originalError;
});
