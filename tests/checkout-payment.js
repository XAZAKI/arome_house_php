const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
let method = 'Transfer Bank';
let selectedBank = 'BCA';
let onChange;
const guides = ['Transfer Bank', 'COD', 'QRIS'].map(value => ({ dataset: { paymentGuide: value }, hidden: false }));
const banks = ['BCA', 'BRI', 'BNI', 'Mandiri'].map(value => ({ dataset: { paymentBank: value }, hidden: false }));
const uniqueNotice = {dataset: {enabled: 'true'}, hidden: false};
const elements = {
  'payment-method-note': {}, 'payment-bank-detail': {}, 'checkout-shipping': {},
  'checkout-total': { dataset: { subtotal: '100000' } }
};
const form = {
  querySelector(selector) {
    if (selector === '[data-transfer-unique-notice]') return uniqueNotice;
    if (selector === '[name="payment_type"]:checked') return { value: method };
    if (selector === '[name="bank"]:checked') return { value: selectedBank };
    if (selector === '[name="shipping_method"]:checked') return { dataset: { cost: '16000' } };
    throw new Error(`Unexpected selector: ${selector}`);
  },
  querySelectorAll(selector) { return selector === '[data-payment-guide]' ? guides : banks; },
  addEventListener(event, callback) { onChange = callback; }
};
vm.runInNewContext(fs.readFileSync(require('node:path').join(__dirname, '../assets/js/checkout-payment.js'), 'utf8'), {
  document: { addEventListener(event, callback) { callback(); }, querySelector() { return form; }, getElementById(id) { return elements[id]; } }, Intl
});
function check(expectedMethod, expectedBank) {
  assert.deepEqual(guides.filter(panel => !panel.hidden).map(panel => panel.dataset.paymentGuide), [expectedMethod]);
  assert.deepEqual(banks.filter(panel => !panel.hidden).map(panel => panel.dataset.paymentBank), expectedBank ? [expectedBank] : []);
  assert.equal(elements['payment-bank-detail'].hidden, expectedMethod !== 'Transfer Bank');
  assert.equal(uniqueNotice.hidden, expectedMethod !== 'Transfer Bank' || uniqueNotice.dataset.enabled === 'false');
}
check('Transfer Bank', 'BCA');
method = 'COD'; onChange(); check('COD');
method = 'QRIS'; onChange(); check('QRIS');
method = 'Transfer Bank'; selectedBank = 'Mandiri'; onChange(); check('Transfer Bank', 'Mandiri');
uniqueNotice.dataset.enabled = 'false'; onChange(); check('Transfer Bank', 'Mandiri');
assert.equal(elements['checkout-total'].textContent, 'Rp 116.000');
console.log('PASS: initial instructions, COD/QRIS switching, selected bank, and shipping total.');
