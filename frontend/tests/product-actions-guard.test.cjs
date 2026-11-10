const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../src/components/product/ProductActions.tsx'), 'utf8');

test('Product API details cannot add products to the enquiry store before BE-08.5.5', () => {
  assert.match(source, /disabled[^>]*>Add to Enquiry<\/Button>/);
  assert.match(source, /Adding products to your enquiry is temporarily unavailable/);
  assert.doesNotMatch(source, /enquiryStore|useEnquiry/);
  assert.doesNotMatch(source, /Add to Enquiry<\/Button>[^\n]*onClick/);
});
