const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const form = fs.readFileSync(path.join(__dirname, '../src/components/forms/ProductEnquiryForm.tsx'), 'utf8');

test('Product form submits only public UUID and approved v2 fields through attempt manager', () => {
  assert.match(form, /submitWithAttempt\(request, attemptManager\.current!, explicitNewAttempt\.current\)/);
  assert.match(form, /apiVersion: 2/);
  assert.match(form, /productId: product\.id, quantity: payload\.quantity/);
  assert.match(form, /placement: 'product_detail'/);
  assert.doesNotMatch(form, /sku: payload|slug: payload|productName|priceAed/);
  assert.match(form, /NEXT_PUBLIC_PRODUCT_ENQUIRY_SUBMIT_ENABLED === 'true'/);
  assert.match(form, /privacyNotice/);
  assert.doesNotMatch(form, /checkbox|consent/);
});

