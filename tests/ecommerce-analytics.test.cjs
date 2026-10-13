const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const events = [];
const context = { TextEncoder, URL, location: { origin: 'https://vistecprints.com', pathname: '/checkout.html' },
  document: { referrer: 'https://vistecprints.com/products.html?email=secret@example.com' },
  sessionStorage: { getItem: () => '1' }, window: { gtag: (...args) => events.push(args) } };
vm.createContext(context);
vm.runInContext(fs.readFileSync('ecommerce-analytics.js', 'utf8'), context);
const analytics = context.window.vpAnalytics;
const line = { id: 'shirt-1', price: 20, qty: 5, name: 'Jane secret@example.com',
  img: 'data:image/png;base64,PRIVATE', customer: { email: 'secret@example.com' }, notes: 'private' };
analytics.add(line, 2);
assert.equal(events[0][0], 'event');
assert.equal(events[0][1], 'add_to_cart');
assert.equal(events[0][2].value, 40);
assert.equal(events[0][2].items[0].quantity, 2); // Added delta, not cumulative cart qty.
assert.equal(events[0][2].currency, 'USD');
assert.equal(events[0][2].debug_mode, true);
assert.equal(events[0][2].page_referrer, 'https://vistecprints.com/products.html');
assert(!JSON.stringify(events).includes('secret'));
assert(!JSON.stringify(events).includes('PRIVATE'));
analytics.begin([line, { id: 'second', price: 9.995, qty: 1 }]);
assert.equal(events[1][1], 'begin_checkout');
assert.equal(events[1][2].value, 110);
analytics.add({ isCustomDesign: true, price: 25, name: 'Personal Artwork' }, 1);
assert.equal(events[2][2].items[0].item_id, 'custom-design');
analytics.add({ price: NaN }, 1);
assert.equal(events.length, 3);
context.sessionStorage.getItem = () => { throw Error('storage blocked'); };
analytics.add(line, 1);
assert.equal(events.length, 4);
assert(!('debug_mode' in events[3][2]));
context.document.referrer = 'https://checkout.stripe.com/c/pay/cs_test_SECRET';
analytics.purchase({ transaction_id: 'vp-test', currency: 'USD', value: 40, items: [] });
assert.equal(events[4][2].page_referrer, '');
assert(!JSON.stringify(events[4]).includes('cs_test_SECRET'));
context.window.gtag = () => { throw Error('tag failure'); };
assert.doesNotThrow(() => analytics.add(line, 1));
for (const name of ['index.html', 'products.html', 'checkout.html', 'designer.html']) {
  const html = fs.readFileSync(name, 'utf8');
  assert(html.includes('src="ecommerce-analytics.js"'));
  for (const match of html.matchAll(/<script\b([^>]*)>([\s\S]*?)<\/script>/g)) {
    if (/application\/ld\+json/.test(match[1])) continue;
    if (match[2].trim()) new vm.Script(match[2], { filename: name });
  }
}
console.log('PASS: payload privacy, values, quantity deltas, quote IDs, debug storage failures, nonblocking analytics, inline JS syntax');
