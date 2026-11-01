const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const ts = require('typescript');
const Module = require('node:module');
const resolve = Module._resolveFilename;
Module._resolveFilename = function (id, ...args) { return resolve.call(this, id.startsWith('@/') ? path.join(__dirname, '../src', id.slice(2)) : id, ...args); };
require.extensions['.ts'] = (module, filename) => module._compile(ts.transpileModule(fs.readFileSync(filename, 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText, filename);
const api = require('../src/lib/forms/enquiries.client.ts');
const contact = { type: 'contact', name: 'Alex', phone: '+971501234567', email: 'alex@example.test', message: 'Please call.' };
const catalogue = { type: 'catalogue_download', name: 'Alex', phone: '+971501234567', email: 'alex@example.test', catalogueId: 'a1b2c3d4-e5f6-4789-8abc-1234567890ab', role: 'architect' };
const grant = { token: 'a'.repeat(64), expiresAt: '2026-10-22T12:15:00+00:00', downloadPath: '/api/v1/catalogues/download', enquiryHeader: 'X-Catalogue-Enquiry' };

test('contact submits the typed payload with one idempotency key and accepts persisted response', async () => {
  process.env.NEXT_PUBLIC_ENQUIRIES_ENABLED = 'true';
  const calls = [];
  global.fetch = async (...args) => { calls.push(args); return Response.json({ data: { status: 'persisted', enquiryId: 'b1b2c3d4-e5f6-4789-8abc-1234567890ab', notificationStatus: 'pending' } }, { status: 201 }); };
  const result = await api.submitEnquiry(contact, 'contact-attempt-00001');
  await api.submitEnquiry(contact, 'contact-attempt-00001');
  assert.equal(result.status, 'persisted');
  assert.equal(result.leadId, 'b1b2c3d4-e5f6-4789-8abc-1234567890ab');
  assert.equal(calls.length, 2);
  for (const [url, init] of calls) {
    assert.equal(url, '/api/v1/enquiries');
    assert.equal(init.headers['Idempotency-Key'], 'contact-attempt-00001');
    assert.equal(init.cache, 'no-store');
    assert.deepEqual(JSON.parse(init.body), contact);
  }
});

test('catalogue requires a valid download grant and accepts an idempotent replay', async () => {
  process.env.NEXT_PUBLIC_ENQUIRIES_ENABLED = 'true';
  global.fetch = async () => Response.json({ data: { status: 'persisted', enquiryId: 'b1b2c3d4-e5f6-4789-8abc-1234567890ab', downloadGrant: grant } }, { status: 200 });
  const result = await api.submitEnquiry(catalogue, 'catalogue-attempt-00001');
  assert.deepEqual(result.downloadGrant, grant);
  global.fetch = async () => Response.json({ data: { status: 'persisted', enquiryId: 'b1b2c3d4-e5f6-4789-8abc-1234567890ab' } }, { status: 201 });
  await assert.rejects(api.submitEnquiry(catalogue, 'catalogue-attempt-00002'), (error) => error.status === 502);
});

test('API failure statuses preserve safe field errors and distinguish conflict, validation, rate limit and outages', async () => {
  process.env.NEXT_PUBLIC_ENQUIRIES_ENABLED = 'true';
  for (const [status, code, body, field] of [
    [409, 'idempotency_conflict', { error: { code: 'idempotency_conflict' } }, null],
    [422, 'validation_failed', { error: { code: 'validation_failed', fields: { email: ['Enter a valid email.'] } } }, 'email'],
    [404, 'resource_unavailable', { error: { code: 'resource_unavailable' } }, null],
    [429, 'rate_limit', { error: { code: 'rate_limit' } }, null],
    [503, 'persistence_unavailable', { error: { code: 'persistence_unavailable' } }, null],
  ]) {
    global.fetch = async () => Response.json(body, { status });
    await assert.rejects(api.submitEnquiry(contact, `contact-attempt-${status}`), (error) => {
      assert.equal(error.status, status);
      assert.equal(error.code, code);
      if (field) assert.ok(error.fieldErrors[field]);
      assert.ok(error.message.length > 0);
      return true;
    });
  }
  global.fetch = async () => { throw new TypeError('offline'); };
  await assert.rejects(api.submitEnquiry(contact, 'contact-attempt-network'), (error) => error.status === 0 && /retained/.test(error.message));
});

test('catalogue download sends Bearer and enquiry headers without putting the token in the URL', async () => {
  const originalCreate = URL.createObjectURL;
  const originalRevoke = URL.revokeObjectURL;
  const appended = [];
  const clicked = [];
  global.document = { createElement: () => ({ click() { clicked.push(this); }, remove() {} }), body: { append: (anchor) => appended.push(anchor) } };
  global.window = { setTimeout: (callback) => callback() };
  URL.createObjectURL = () => 'blob:catalogue';
  URL.revokeObjectURL = () => {};
  let call;
  global.fetch = async (...args) => { call = args; return new Response('%PDF-test', { status: 200, headers: { 'content-type': 'application/pdf' } }); };
  try {
    await api.downloadCatalogue(grant, 'b1b2c3d4-e5f6-4789-8abc-1234567890ab');
    assert.equal(call[0], '/api/v1/catalogues/download');
    assert.equal(call[1].headers.Authorization, `Bearer ${grant.token}`);
    assert.equal(call[1].headers['X-Catalogue-Enquiry'], 'b1b2c3d4-e5f6-4789-8abc-1234567890ab');
    assert.equal(call[1].cache, 'no-store');
    assert.ok(!call[0].includes(grant.token));
    assert.equal(appended.length, 1);
    assert.equal(clicked.length, 1);
    assert.equal(clicked[0].download, 'catalogue.pdf');
  } finally {
    URL.createObjectURL = originalCreate;
    URL.revokeObjectURL = originalRevoke;
    delete global.document;
    delete global.window;
  }
});

test('submissions remain disabled when the privacy approval flag is absent', async () => {
  delete process.env.NEXT_PUBLIC_ENQUIRIES_ENABLED;
  const originalFetch = global.fetch;
  global.fetch = async () => { throw new Error('must not call API'); };
  try { assert.deepEqual(await api.submitEnquiry(contact, 'contact-attempt-disabled'), { status: 'unavailable' }); }
  finally { global.fetch = originalFetch; }
});
