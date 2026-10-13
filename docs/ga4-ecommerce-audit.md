# VistecPrints GA4 ecommerce audit and validation

Audited main at `e5ef01284eb5802d9fbd938308b3ae0fd861dbbc` on 2026-10-13.
GA4 stream: `G-5YY561Z1J8`. The latest deployment run was successful on
2026-10-10: https://github.com/vistecshare-Bert/vistecprints/actions/runs/38063950880.

## Findings and implementation

The source contains direct Google tags but no explicit GA4 ecommerce events,
no GTM container, no Measurement Protocol sender, and no Stripe payment webhook.
`cart-track.php` logs to first-party JSONL files; it does not forward events to GA4.
Repository inspection cannot establish what an external integration, GA4 event
rule, hosting injection, or external GTM deployment already sends. Inspect the
live tag network requests and GA4 configuration before enabling this patch.

| Event | Trigger | Implementation |
| --- | --- | --- |
| `add_to_cart` | Successful cart persistence on homepage/products, positive quantity changes, and designer additions | `ecommerce-analytics.js`, index/products/designer hooks; only added quantity, not cumulative cart quantity |
| `begin_checkout` | First rendering of a nonempty, priced payment cart on the checkout page | Checkout hook fires once per document, including a quote cart that becomes payable after removal; a reload or cancellation return starts a new checkout visit |
| `purchase` | Stripe returns HTTP 200 and `payment_status=paid`, a matching pending order exists, and charged amount reconciles | PHP builds an allowlisted payload before pending-order deletion and atomically claims a durable transaction marker before rendering the browser event |

All ecommerce events include currency, merchandise value, and item IDs/prices/
quantities. Product IDs are represented as stable `sku-` plus FNV-1a hashes of
catalog IDs; this is a reporting identifier, not a security mechanism. Customer
fields are never used as ID inputs. Custom designs use `custom-design`. Product
names, custom artwork titles, sizes/colors, garment text, notes, images, customer
name/email/phone/address, and Stripe customer/payment credentials are excluded.
The checkout request now preserves product ID and the custom-design flag so
purchase IDs match cart IDs. No Stripe line items, methods, charges, success or
cancel URLs, receipts, or quote-submission behavior are changed.

Purchase `transaction_id` is `vp-` plus SHA-256 of the verified Checkout Session
ID (not a raw bearer session URL or a customer identifier). The marker uses
exclusive `fopen(..., 'x')`, so concurrent responses can emit at most one event.
Google's transaction-ID deduplication adds another safeguard. Do not delete
production markers. All workers must share this durable directory if hosting
is expanded beyond the current single server.

GA4 purchase value excludes the $4.99 shipping line item; shipping is reported
separately, tax is zero. The expected total must equal the sum of the Stripe-
rounded unit prices times quantities plus 499 cents. The patch fails closed for
unsupported currency, discounts/tax, invalid quantities, missing orders, test
payments by default, and amounts that do not reconcile. Update this mapping
before changing shipping, discounts, or tax configuration. Old pending orders
without IDs use the generic custom-design item ID.

Tracked pages strip query strings/fragments from GA4 page location/referrer.
The success page disables automatic page views and sets `Referrer-Policy:
no-referrer` so the Stripe session query is not sent as a GA4 URL or an outgoing
referrer. Tracking does not override existing consent settings. Review any
external tags and existing enhanced measurement settings separately.

## Deployment and coverage limits

`main` pushes auto-deploy; this change belongs on a review branch until tested.
The PHP helpers require PHP 7.4+ (the existing storefront already uses arrow
functions). Ensure the PHP process can create the marker directory. On the
current HostGator layout the default is `/home3/vistecpr/.vistec-ga4-purchases`,
above public_html. Alternatively set `VISTEC_GA4_PURCHASE_DIR` to an absolute,
writable, non-public, durable path. A storage failure suppresses tracking,
not payment processing. Inspect marker creation as part of deployment QA.

This implementation guarantees at-most-once **emission**, not guaranteed GA4
delivery. It records a marker before the browser loads the tag. A blocker,
network failure, denied analytics consent, interrupted response, or a customer
who never returns from Stripe can cause a purchase to be absent in GA4. Refresh
is intentionally not a retry. Existing order finalization is also return-page
based and has pre-existing file-write/concurrency limitations; this patch does
not claim to fix fulfillment idempotency. It does not backfill older purchases.

For complete payment coverage, the exact next integration is a signature-
verified Stripe webhook for `checkout.session.completed` and
`checkout.session.async_payment_succeeded`, gating on `paid`; store an outbox
under a unique transaction key, send purchases from that outbox through GA4
Measurement Protocol with consented original client/session IDs captured at
checkout, and retry failures with the same transaction ID. Store the MP secret
only server-side and validate the payload at Google's debug endpoint first.
Switch off this browser purchase sender when activating the webhook sender;
do not run two purchase sources. That requires Stripe webhook configuration,
a GA4 MP secret, consent/attribution decisions, and persistent queue ownership,
which are not available in this repository audit.

## GA4 DebugView and Stripe test validation

1. Use an isolated test deployment with a separate GA4 test property/stream.
   Replace `G-5YY561Z1J8` in the four tagged storefront pages, the JS helper,
   and stripe-success.php with that test stream's ID. Use Stripe test/sandbox
   keys in the server-only stripe-config.php. The existing checkout endpoint
   hardcodes https://vistecprints.com: on the test deployment only, change
   `$base` in create-checkout-session.php to your test origin so Stripe returns
   to the isolated deployment. Do not replace production keys or routing.
   Disable/redirect customer/admin email and use isolated order storage there.
2. On that test deployment set `VISTEC_GA4_INCLUDE_TEST_PURCHASES=1` for the PHP
   process. Do not enable this in production. Otherwise test purchases are
   deliberately suppressed. Use a unique marker directory for test/staging.
3. In the same browser tab run `sessionStorage.setItem('vp_ga4_debug', '1')`
   in DevTools and reload. Open GA4 Admin > DebugView and select your device.
   Google Tag Assistant can also enable debugging. Consent must allow analytics
   and the tag must not be blocked to see events. Inspect Network `g/collect`
   requests as well as DebugView; validate the destination measurement ID.
4. Add two $20 shirts: expect one `add_to_cart`, quantity 2, value 40, USD.
   Add one more of the same item: expect quantity 1/value 20, not quantity 3.
   Increase cart quantity by one: expect another quantity 1 event. Decreases
   and removal must not emit add_to_cart. Repeat from both homepage/products.
   A designer addition uses custom-design and its estimated price, never its
   artwork/name. Do not count those estimates as collected revenue.
5. Open checkout: expect one begin_checkout per document with merchandise
   value (no shipping). Removing an item should not fire it again. Empty or
   quote-only carts must not emit it; submitting a quote must never emit a
   purchase. A mixed custom/priced cart follows the existing payable flow.
6. Complete a new Stripe test payment with card 4242 4242 4242 4242, a future
   expiry, and any three-digit CVC. Verify the session is paid in Stripe.
   For two $20 items, expect purchase value 40, shipping 4.99, tax 0, currency
   USD, amount charged 44.99, matching hashed item IDs and quantity 2, and one
   nonempty stable transaction_id. Check GA4 requests for forbidden customer
   fields, artwork data, and the `sid` URL query.
7. Refresh the success page, revisit it, and open its URL in another tab.
   Expect no additional purchase requests or DebugView events and exactly one
   marker for the session. Make another real test order: its new transaction
   must produce a different ID. Google also deduplicates by transaction ID.
8. Cancel Stripe checkout and test a declined card (4000 0000 0000 0002):
   cart remains available and there is no purchase. Opening an unpaid session's
   return URL, a missing/invalid sid, or an unrecognized pending order must
   not emit purchase. A mocked Stripe API timeout must not emit purchase.
9. Verify Cash App Pay still appears when eligible; use Stripe's Cash App Pay
   sandbox instructions for success/cancellation. Check shipping, receipt,
   order and DTF records, and quote-request behavior against the baseline.
10. Run local/CI checks below. Remove the session debug flag when done using
    `sessionStorage.removeItem('vp_ga4_debug')`. After production rollout,
    inspect GA4 ecommerce reports (allow processing time) and compare distinct
    transaction counts with Stripe. Expect the browser-return coverage limits
    above. Verify `purchase` is a key event and remove any overlapping external
    purchase sender or custom conversion rule before rollout. Configure Stripe
    domains as unwanted referrals if attribution is being reset on return.

## Automated checks

```
node tests/ecommerce-analytics.test.cjs
php -l ga4-purchase.php
php -l stripe-success.php
php tests/ga4-purchase.test.php
```

The PR workflow runs these checks without deploying. Tests exercise event
privacy/values, invalid data, storage/tag failure isolation, inline JS syntax,
payment status/currency/mode/order/amount gates, explicit test opt-in, and the
first-versus-duplicate durable claim. They do not replace an end-to-end Stripe
transaction or observation in the actual GA4 property.

## Official references

- https://developers.google.com/analytics/devguides/collection/ga4/ecommerce
- https://support.google.com/analytics/answer/12313109
- https://support.google.com/analytics/answer/7201382
- https://docs.stripe.com/api/checkout/sessions/object
- https://docs.stripe.com/checkout/fulfillment
- https://docs.stripe.com/testing
