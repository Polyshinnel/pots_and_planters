const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../src/components/product/ProductActions.tsx'), 'utf8');

test('Product details add only a resolved public UUID behind the dedicated local feature flag', () => {
  assert.match(source, /NEXT_PUBLIC_PRODUCT_ENQUIRIES_ENABLED === 'true'/);
  assert.match(source, /resolveProducts\(\[\{ productId: product\.id, quantity: value \}\]\)/);
  assert.match(source, /enquiryStoreV2\.add\(product\.id, value\)/);
  assert.match(source, /lines\[0\]\?\.status !== 'available'/);
  assert.match(source, /disabled=\{!productEnquiriesEnabled \|\| !valid \|\| adding\}/);
  assert.doesNotMatch(source, /submitEnquiry|fetch\(['"]\/api\/v1\/enquiries/);
});
