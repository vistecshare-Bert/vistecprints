<?php
/**
 * Creates sample packing slips from fake orders so you can check the look.
 *   php test-packing-slip.php
 * Output: packing-slip/test-output/*.pdf   (nothing is emailed or saved to real orders)
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__ . '/packing-slip/src/PackingSlip.php';

$customer = [
    'name'    => 'Jordan Sample',
    'email'   => 'jordan@example.com',
    'phone'   => '704-555-0142',
    'address' => ['line1' => '1200 Example Ave', 'city' => 'Charlotte', 'state' => 'NC', 'zip' => '28203'],
];

$small = [
    'id'       => 'VGX-20261029-TEST01',
    'date'     => date('c'),
    'paidAt'   => date('c'),
    'paid'     => true,
    'customer' => $customer,
    'notes'    => "Please gift wrap — it's a birthday present!\n\nPaid via Stripe.",
    'items'    => [[
        'id' => 'vp_sample1', 'name' => 'No Kings Tee', 'size' => 'L', 'qty' => 1,
        'price' => 24.99, 'priceStr' => '$24.99', 'garment' => 'Gildan 5000 — Heavy Cotton 100%',
        'color' => 'Black · Full cover front', 'variant' => ['color' => 'Black', 'style' => 'Full cover front'],
        'img' => 'images/Logo.png',
    ]],
    'subtotal' => 24.99, 'shipping' => 4.99, 'total' => 29.98,
    // Different billing address → "Bill to" column appears
    'billing'  => ['name' => 'Pat Sample', 'line1' => '55 Billing Rd', 'city' => 'Raleigh', 'state' => 'NC', 'postal_code' => '27601', 'country' => 'US'],
];

// 25 lines across products, colors and sizes, deliberately shuffled to show the sorting
$sizes    = ['3XL', 'S', 'XL', 'M', 'XS', 'L', '2XL'];
$products = [
    ['Thick-Fil-a Tee', 'Gildan 5000 — Heavy Cotton 100%', 'White'],
    ['Anime V3-42',     'Gildan 6400 — SoftStyle 100%',   'Black'],
    ['Vistec G Hoodie', 'Hoodie — Heavy Blend 50/50',     'Sport Grey'],
    ['Custom Design — T-Shirt', 'Gildan 5000 (Red) · Front & Back', ''],
];
$items = [];
for ($i = 0; $i < 25; $i++) {
    [$name, $garment, $color] = $products[$i % 4];
    $items[] = [
        'id' => 'vp_' . (1000 + $i % 4), 'name' => $name, 'size' => $sizes[$i % 7], 'qty' => 1 + $i % 3,
        'price' => 22.5, 'priceStr' => '$22.50', 'garment' => $garment, 'color' => $color,
        'img' => $i % 4 === 3 ? '' : 'images/Logo.png',
    ];
}
$large = [
    'id' => 'VGX-20261029-TEST25', 'date' => date('c'), 'paidAt' => date('c'), 'paid' => true,
    'customer' => $customer, 'notes' => '', 'items' => $items,
    'subtotal' => 900.00, 'shipping' => 4.99, 'total' => 904.99,
];

$outDir = __DIR__ . '/packing-slip/test-output';
if (!is_dir($outDir)) mkdir($outDir, 0755, true);
$cfg = require __DIR__ . '/packing-slip/config.php';

$runs = [
    'letter-1-item.pdf'          => [$cfg, [$small]],
    'letter-25-items.pdf'        => [$cfg, [$large]],
    'letter-with-prices.pdf'     => [array_merge($cfg, ['show_prices' => true]), [$small]],
    'letter-merged-2-orders.pdf' => [$cfg, [$small, $large]],
    '4x6-1-item.pdf'             => [array_merge($cfg, ['paper' => '4x6']), [$small]],
    '4x6-25-items.pdf'           => [array_merge($cfg, ['paper' => '4x6']), [$large]],
];
$failed = 0;
foreach ($runs as $file => [$c, $orders]) {
    try {
        $t = microtime(true);
        $bytes = (new PackingSlip($c, __DIR__))->pdf($orders);
        file_put_contents("$outDir/$file", $bytes);
        preg_match_all('#/Type\s*/Page[^s]#', $bytes, $m);
        printf("OK   %-28s %3d page(s)  %6.1f KB  %.1fs\n", $file, count($m[0]), strlen($bytes) / 1024, microtime(true) - $t);
    } catch (\Throwable $e) {
        $failed++;
        echo "FAIL $file: " . $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ")\n";
    }
}
echo $failed ? "\n{$failed} failed.\n" : "\nAll samples written to packing-slip/test-output/\n";
exit($failed ? 1 : 0);
