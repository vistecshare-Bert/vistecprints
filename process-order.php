<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['customer']) || empty($input['items'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid order data']);
    exit;
}

$customer = $input['customer'];
$items    = $input['items'];
$subtotal = floatval($input['subtotal'] ?? 0);
$shipping = 4.99;
$total    = $subtotal + $shipping;
$notes    = trim($input['notes'] ?? '');
$orderId  = 'VGX-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));

$order = [
    'id'       => $orderId,
    'date'     => date('c'),
    'status'   => 'pending',
    'customer' => $customer,
    'notes'    => $notes,
    'items'    => $items,
    'subtotal' => $subtotal,
    'shipping' => $shipping,
    'total'    => $total,
];

// ── Save to orders.json ────────────────────────────────
$ordersFile = __DIR__ . '/orders.json';
$orders = [];
if (file_exists($ordersFile)) {
    $raw = file_get_contents($ordersFile);
    $orders = json_decode($raw, true) ?: [];
}
array_unshift($orders, $order); // newest first
$saved = file_put_contents($ordersFile, json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
if ($saved === false) {
    echo json_encode(['success' => false, 'error' => 'Could not save order. Please call us at 704-377-6626.']);
    exit;
}

// ── Build item list text ────────────────────────────────
$itemLines = '';
foreach ($items as $item) {
    $qty      = intval($item['qty'] ?? 1);
    $name     = $item['name'] ?? 'Item';
    $size     = $item['size'] ?? 'N/A';
    $garment  = $item['garment'] ?? '';
    $price    = $item['priceStr'] ?? '';
    $itemLines .= "  - {$name} | Size: {$size} | {$garment} | {$price} x{$qty}\n";
}
$addr = "{$customer['address']['line1']}, {$customer['address']['city']}, {$customer['address']['state']} {$customer['address']['zip']}";

// ── Auto-create DTF pipeline entry ─────────────────────
$dtfDir = __DIR__ . '/orders/';
if (!is_dir($dtfDir)) mkdir($dtfDir, 0755, true);
$dtfId         = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(md5($orderId), 0, 6));
$garmentTypes  = array_unique(array_filter(array_map(fn($i) => trim($i['garment'] ?? ''), $items)));
$dtfRec = [
    'id'              => $dtfId,
    'web_order_id'    => $orderId,
    'customer_name'   => $customer['name']  ?? '',
    'customer_email'  => $customer['email'] ?? '',
    'customer_phone'  => $customer['phone'] ?? '',
    'garment_type'    => implode(', ', $garmentTypes),
    'qty'             => array_sum(array_map(fn($i) => intval($i['qty'] ?? 1), $items)),
    'print_location'  => '',
    'sizes_breakdown' => '',
    'print_type'      => '',
    'price_charged'   => number_format($total, 2),
    'production_cost' => '',
    'due_date'        => '',
    'notes'           => "Web order #{$orderId}\n" . trim($itemLines),
    'stage'           => 'new_order',
    'date'            => date('c'),
    'source'          => 'web_order',
];
file_put_contents($dtfDir . $dtfId . '.json', json_encode($dtfRec, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// ── Email to store ─────────────────────────────────────
$toStore   = 'vistecshare@gmail.com';
$subjStore = "[New Order] #{$orderId} — {$customer['name']}";
$bodyStore  = "NEW ORDER RECEIVED\n";
$bodyStore .= str_repeat('=', 40) . "\n\n";
$bodyStore .= "Order ID : {$orderId}\n";
$bodyStore .= "Date     : " . date('M j, Y g:i A') . "\n\n";
$bodyStore .= "CUSTOMER\n";
$bodyStore .= "Name   : {$customer['name']}\n";
$bodyStore .= "Email  : {$customer['email']}\n";
$bodyStore .= "Phone  : {$customer['phone']}\n\n";
$bodyStore .= "SHIP TO\n{$addr}\n\n";
if ($notes) $bodyStore .= "NOTES\n{$notes}\n\n";
$bodyStore .= "ITEMS\n{$itemLines}\n";
$bodyStore .= "Subtotal : \$" . number_format($subtotal, 2) . "\n";
$bodyStore .= "Shipping : \$" . number_format($shipping, 2) . "\n";
$bodyStore .= "TOTAL    : \$" . number_format($total, 2) . "\n\n";
$bodyStore .= "View orders: https://rebuild.vistecprints.com/admin/\n";

$headersStore = "From: orders@rebuild.vistecprints.com\r\nReply-To: {$customer['email']}";
mail($toStore, $subjStore, $bodyStore, $headersStore);

// ── Confirmation email to customer ─────────────────────
$subjCust = "Order Confirmed #{$orderId} — Vistec GraphX";
$bodyCust  = "Hi {$customer['name']},\n\n";
$bodyCust .= "Thank you for your order! We've received it and will reach out within 1 business day.\n\n";
$bodyCust .= "ORDER #{$orderId}\n";
$bodyCust .= str_repeat('-', 30) . "\n";
$bodyCust .= $itemLines . "\n";
$bodyCust .= "Subtotal : \$" . number_format($subtotal, 2) . "\n";
$bodyCust .= "Shipping : \$" . number_format($shipping, 2) . "\n";
$bodyCust .= "TOTAL    : \$" . number_format($total, 2) . "\n\n";
$bodyCust .= "SHIPPING TO\n{$addr}\n\n";
if ($notes) $bodyCust .= "Your notes: {$notes}\n\n";
$bodyCust .= "PAYMENT\nWe will contact you at {$customer['email']} with PayPal/Zelle/Cash App payment instructions. ";
$bodyCust .= "Your order will ship once payment is confirmed.\n\n";
$bodyCust .= "Questions? Reply to this email or call: +1 704-377-6626\n\n";
$bodyCust .= "— Vistec GraphX\nCharlotte, NC | vistecprints.com";

$headersCust = "From: orders@rebuild.vistecprints.com";
mail($customer['email'], $subjCust, $bodyCust, $headersCust);

echo json_encode(['success' => true, 'orderId' => $orderId]);
