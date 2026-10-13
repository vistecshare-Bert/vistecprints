<?php
require __DIR__ . '/../ga4-purchase.php';
function check($condition, $label) { if (!$condition) throw new Exception($label); }
$order = ['stripeSession' => 'cs_test_example', 'status' => 'pending_payment',
    'customer' => ['name' => 'Jane', 'email' => 'private@example.com'], 'notes' => 'PRIVATE',
    'items' => [['id' => 'shirt-1', 'name' => 'PRIVATE ARTWORK', 'price' => 20, 'qty' => 2]]];
$session = ['id' => 'cs_test_example', 'mode' => 'payment', 'payment_status' => 'paid',
    'currency' => 'usd', 'livemode' => true, 'amount_total' => 4499];
$payload = vpGa4PurchasePayload($order, $session);
check((float)$payload['value'] === 40.0 && $payload['shipping'] === 4.99, 'merchandise value excludes shipping');
check($payload['items'][0]['item_id'] === 'sku-' . hash('fnv1a32', 'shirt-1'), 'stable product ID');
check(strpos(json_encode($payload), 'PRIVATE') === false && strpos(json_encode($payload), 'private@example.com') === false, 'no PII');
foreach ([['payment_status', 'unpaid'], ['payment_status', 'no_payment_required'], ['amount_total', 4498],
    ['id', 'cs_test_other'], ['mode', 'subscription'], ['currency', 'eur'], ['livemode', false]] as [$key, $value]) {
    $bad = $session; $bad[$key] = $value;
    check(vpGa4PurchasePayload($order, $bad) === null, 'reject ' . $key . ' ' . $value);
}
$bad = $session; $bad['total_details']['amount_discount'] = 100;
check(vpGa4PurchasePayload($order, $bad) === null, 'reject unsupported discount');
$bad = $order; $bad['status'] = 'paid';
check(vpGa4PurchasePayload($bad, $session) === null, 'reject processed order');
$bad = $order; $bad['items'][0]['qty'] = 1.5;
check(vpGa4PurchasePayload($bad, $session) === null, 'reject fractional qty');
$bad = $order; unset($bad['items'][0]['id']);
check(vpGa4PurchasePayload($bad, $session)['items'][0]['item_id'] === 'custom-design', 'legacy pending cart fallback');
putenv('VISTEC_GA4_INCLUDE_TEST_PURCHASES=1');
$test = $session; $test['livemode'] = false;
check(vpGa4PurchasePayload($order, $test) !== null, 'explicit test opt in');
putenv('VISTEC_GA4_INCLUDE_TEST_PURCHASES');
$dir = sys_get_temp_dir() . '/vp-ga4-test-' . bin2hex(random_bytes(8));
check(vpGa4ClaimPurchase($dir, $payload['transaction_id']), 'first claim');
check(!vpGa4ClaimPurchase($dir, $payload['transaction_id']), 'duplicate rejected');
check(!vpGa4ClaimPurchase($dir, '../../bad'), 'invalid transaction ID');
check(!vpGa4ClaimPurchase(__FILE__, $payload['transaction_id']), 'storage failure fails closed');
foreach (glob($dir . '/*') as $file) unlink($file);
rmdir($dir);
echo "PASS: payment gates, Stripe amount reconciliation, privacy, test isolation, durable deduplication\n";
