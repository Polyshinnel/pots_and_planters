const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const source = fs.readFileSync(require('node:path').join(__dirname, '../src/components/solutions/SolutionGallery.tsx'), 'utf8');

test('Solutions Product positions cannot invoke enquiry writes', () => {
  assert.match(source, /<Button size="compact-sm" variant="outline" disabled>Add to Enquiry<\/Button>/);
  assert.match(source, /<Button fullWidth disabled>Add Solution to Enquiry<\/Button>/);
  assert.doesNotMatch(source, /enquiryStore\.(?:addItem|addItems)\s*\(/);
  assert.doesNotMatch(source, /onClick=\{\(\)\s*=>\s*add\(/);
  assert.doesNotMatch(source, /useEnquiry/);
});
