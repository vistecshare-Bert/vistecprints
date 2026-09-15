<?php
// cart-track.php — silent add-to-cart logger
// Called via JS fetch() whenever a customer adds an item to their cart.
// Stores anonymised events in cart_events/YYYY-MM.jsonl (one JSON record per line).

header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store, no-cache');
header('Content-Length: 0');

$input = json_decode(file_get_contents('php://input'), true) ?: [];

$productId = substr(preg_replace('/[^a-zA-Z0-9_\-]/', '', $input['id'] ?? ''), 0, 60);
$name      = substr(trim($input['name'] ?? ''), 0, 120);
$size      = substr(preg_replace('/[^a-zA-Z0-9]/', '', $input['size'] ?? ''), 0, 10);
$qty       = max(1, min(99, (int)($input['qty'] ?? 1)));
$price     = round(floatval($input['price'] ?? 0), 2);

if ($productId === '' && $name === '') { http_response_code(204); exit; }

// Anonymise IP — daily rotating hash so we can count unique visitors without storing IPs
$ip     = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
$ipHash = substr(hash('sha256', $ip . '|' . date('Y-m-d')), 0, 10);

$dir = __DIR__ . '/cart_events';
if (!is_dir($dir)) @mkdir($dir, 0755, true);

// Protect the directory from direct browsing
$htaccess = $dir . '/.htaccess';
if (!file_exists($htaccess)) {
    file_put_contents($htaccess, "Order deny,allow\nDeny from all\n");
}

$file  = $dir . '/' . date('Y-m') . '.jsonl';
$entry = json_encode([
    't'   => date('Y-m-d H:i'),
    'id'  => $productId,
    'nm'  => $name,
    'sz'  => $size,
    'qty' => $qty,
    'pr'  => $price,
    'ih'  => $ipHash,
]) . "\n";

@file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);
http_response_code(204);
