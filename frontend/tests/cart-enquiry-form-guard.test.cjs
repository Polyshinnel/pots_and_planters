const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
test('Cart uses real UUID store, batch resolve and independent attempt; never clears the selection', () => {
 const source = fs.readFileSync('src/components/enquiry/EnquiryCartLayout.tsx', 'utf8');
 for (const text of ['enquiryStoreV2.getState()', 'resolveProducts(items)', "type: 'cart_enquiry'", 'submitWithAttempt(payload', "pagePath: '/cart'", 'setReference(result.result.enquiryId)']) assert.ok(source.includes(text), text);
 assert.doesNotMatch(source, /catalog.mock|legacyItems|removeLegacy|\.clear\(/);
});
