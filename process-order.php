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

// Save the customer's design preview (if the item carries one) as the DTF artwork,
// so staff can see *what* was ordered, not just a name/qty line.
$artwork     = '';
$artworkName = '';
foreach ($items as $item) {
    $img = $item['img'] ?? '';
    if (is_string($img) && preg_match('/^data:image\/(png|jpe?g|gif|webp);base64,(.+)$/i', $img, $m)) {
        $ext  = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
        $data = base64_decode($m[2]);
        if ($data !== false) {
            $artDir = $dtfDir . 'artwork/';
            if (!is_dir($artDir)) mkdir($artDir, 0755, true);
            $artFile = $dtfId . '_artwork.' . $ext;
            if (file_put_contents($artDir . $artFile, $data)) {
                $artwork     = 'orders/artwork/' . $artFile;
                $artworkName = ($item['name'] ?? 'Design') . ' preview.' . $ext;
            }
        }
        break;
    }
}

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
    'artwork'         => $artwork,
    'artwork_name'    => $artworkName,
];
file_put_contents($dtfDir . $dtfId . '.json', json_encode($dtfRec, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// ── Email to store ─────────────────────────────────────
$toStore   = 'vistecshare@gmail.com';
$subjStore = "[New Order] #{$orderId} — {$customer['name']}";
$artworkImgTag = '';
if (!empty($artwork)) {
    $artworkUrl = 'https://vistecprints.com/' . $artwork;
    $artworkImgTag = "<p><img src='" . htmlspecialchars($artworkUrl) . "' alt='Design preview' style='max-width:300px;border:1px solid #ccc;border-radius:4px;display:block;'/></p>";
}
$bodyStore = "
<html><body style='font-family:sans-serif;color:#222;white-space:pre-wrap;'>
<h2 style='margin:0 0 8px;'>New order received</h2>
<p style='margin:0 0 4px;'><strong>Order ID:</strong> " . htmlspecialchars($orderId) . "</p>
<p style='margin:0 0 4px;'><strong>Date:</strong> " . date('M j, Y g:i A') . "</p>
<p style='margin:0 0 4px;'><strong>Customer:</strong> " . htmlspecialchars($customer['name']) . "</p>
<p style='margin:0 0 4px;'><strong>Email:</strong> " . htmlspecialchars($customer['email']) . "</p>
<p style='margin:0 0 4px;'><strong>Phone:</strong> " . htmlspecialchars($customer['phone']) . "</p>
<p style='margin:0 0 12px;'><strong>Ship to:</strong> " . htmlspecialchars($addr) . "</p>
" . ($notes ? "<p style='margin:0 0 12px;'><strong>Notes:</strong> " . htmlspecialchars($notes) . "</p>" : "") . "
<pre style='font-family:sans-serif;margin:0 0 12px;'>" . htmlspecialchars($itemLines) . "</pre>
<p style='margin:0 0 4px;'>Subtotal: \$" . number_format($subtotal, 2) . "</p>
<p style='margin:0 0 4px;'>Shipping: \$" . number_format($shipping, 2) . "</p>
<p style='margin:0 0 12px;'><strong>TOTAL: \$" . number_format($total, 2) . "</strong></p>
{$artworkImgTag}
<p style='margin-top:16px;'><a href='https://vistecprints.com/admin/dashboard.php?tab=dtf'>View in DTF Pipeline</a></p>
</body></html>";

$headersStore  = "MIME-Version: 1.0\r\n";
$headersStore .= "Content-Type: text/html; charset=UTF-8\r\n";
$headersStore .= "From: orders@vistecprints.com\r\nReply-To: {$customer['email']}";
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

$headersCust = "From: orders@vistecprints.com";
mail($customer['email'], $subjCust, $bodyCust, $headersCust);

echo json_encode(['success' => true, 'orderId' => $orderId]);
