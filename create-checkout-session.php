<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']); exit;
}

$cfgFile = __DIR__ . '/stripe-config.php';
if (!file_exists($cfgFile)) {
    echo json_encode(['error' => 'Payment not configured. Please call +1 704-377-6626.']); exit;
}
require_once $cfgFile;

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['items']) || empty($input['customer'])) {
    echo json_encode(['error' => 'Invalid order data']); exit;
}

$customer = $input['customer'];
$items    = $input['items'];
$notes    = trim($input['notes'] ?? '');
$subtotal = floatval($input['subtotal'] ?? 0);

// Validate all items have a real price
foreach ($items as $item) {
    if (floatval($item['price'] ?? 0) < 0.50) {
        echo json_encode(['error' => 'One or more items have no price set. Please call us at +1 704-377-6626 for a quote.']);
        exit;
    }
}

// Build Stripe line_items array
$lineItems = [];
foreach ($items as $item) {
    $price = floatval($item['price']);
    $name  = substr(($item['name'] ?? 'Item') . ' — Size ' . ($item['size'] ?? ''), 0, 127);
    $lineItems[] = [
        'price_data' => [
            'currency'     => 'usd',
            'product_data' => ['name' => $name],
            'unit_amount'  => (int) round($price * 100),
        ],
        'quantity' => max(1, (int)($item['qty'] ?? 1)),
    ];
}
// Flat shipping fee
$lineItems[] = [
    'price_data' => [
        'currency'     => 'usd',
        'product_data' => ['name' => 'Standard Shipping'],
        'unit_amount'  => 499,
    ],
    'quantity' => 1,
];

$base    = 'https://rebuild.vistecprints.com';
$payload = [
    'mode'           => 'payment',
    'customer_email' => $customer['email'] ?? '',
    'success_url'    => $base . '/stripe-success.php?sid={CHECKOUT_SESSION_ID}',
    'cancel_url'     => $base . '/checkout.html?cancelled=1',
    'line_items'     => $lineItems,
];

// Call Stripe API
$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($payload),
    CURLOPT_USERPWD        => STRIPE_SECRET_KEY . ':',
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT        => 15,
]);
$res  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($err) {
    echo json_encode(['error' => 'Connection to payment provider failed. Please try again or call +1 704-377-6626.']); exit;
}

$data = json_decode($res, true);
if ($code !== 200 || empty($data['url'])) {
    $msg = $data['error']['message'] ?? 'Payment setup failed. Please try again.';
    echo json_encode(['error' => $msg]); exit;
}

// Save a pending order — finalized in stripe-success.php after confirmed payment
$orderId    = 'VGX-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
$pendingDir = __DIR__ . '/pending_orders';
if (!is_dir($pendingDir)) mkdir($pendingDir, 0755, true);

$pending = [
    'orderId'       => $orderId,
    'stripeSession' => $data['id'],
    'status'        => 'pending_payment',
    'date'          => date('c'),
    'customer'      => $customer,
    'items'         => $items,
    'subtotal'      => $subtotal,
    'shipping'      => 4.99,
    'total'         => $subtotal + 4.99,
    'notes'         => $notes,
];
file_put_contents($pendingDir . '/' . $data['id'] . '.json',
    json_encode($pending, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode(['url' => $data['url'], 'orderId' => $orderId]);
