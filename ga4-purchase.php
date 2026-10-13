<?php
// Pure payload builder: payment status and amounts come from Stripe, never the URL.
function vpGa4PurchasePayload(array $order, array $session): ?array {
    if (($session['payment_status'] ?? '') !== 'paid' || ($session['mode'] ?? '') !== 'payment'
        || empty($session['id']) || ($order['stripeSession'] ?? '') !== $session['id']
        || ($order['status'] ?? '') !== 'pending_payment'
        || strtolower($session['currency'] ?? '') !== 'usd'
        || empty($order['items']) || count($order['items']) > 200) return null;
    // Test purchases are disabled in production. Enable explicitly on a test deployment.
    if (($session['livemode'] ?? false) !== true && getenv('VISTEC_GA4_INCLUDE_TEST_PURCHASES') !== '1') return null;
    $items = [];
    $merchandiseCents = 0;
    foreach ($order['items'] as $line) {
        $price = $line['price'] ?? null;
        $quantity = $line['qty'] ?? null;
        if (!is_numeric($price) || !is_numeric($quantity) || (float)$price < 0.5
            || (int)$quantity < 1 || (float)$quantity !== (float)(int)$quantity) return null;
        $cents = (int)round((float)$price * 100);
        $merchandiseCents += $cents * (int)$quantity;
        $id = (empty($line['id']) || ($line['isCustomDesign'] ?? false) === true) ? 'custom-design' : 'sku-' . hash('fnv1a32', (string)$line['id']);
        $items[] = ['item_id' => $id, 'price' => $cents / 100, 'quantity' => (int)$quantity];
    }
    // Current checkout uses a $4.99 shipping LINE ITEM, not Stripe shipping_cost.
    // Fail closed if a future discount/tax/shipping change invalidates this mapping.
    if ((int)($session['amount_total'] ?? -1) !== $merchandiseCents + 499
        || (int)($session['total_details']['amount_tax'] ?? 0) !== 0
        || (int)($session['total_details']['amount_discount'] ?? 0) !== 0) return null;
    return ['transaction_id' => 'vp-' . hash('sha256', $session['id']),
        'currency' => 'USD', 'value' => $merchandiseCents / 100,
        'shipping' => 4.99, 'tax' => 0, 'items' => $items];
}

function vpGa4ClaimPurchase(string $directory, string $transactionId): bool {
    if (!preg_match('/^vp-[a-f0-9]{64}$/D', $transactionId)) return false;
    // Default storage is outside the document root and survives rsync --delete.
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) return false;
    $file = @fopen($directory . '/' . $transactionId . '.sent', 'x');
    if (!$file) return false; // Atomic exclusive creation: refreshes/tabs cannot reclaim.
    fclose($file);
    @chmod($directory . '/' . $transactionId . '.sent', 0600);
    return true;
}
