<?php
// track.php — silent visitor logger
// Called via JS fetch() on every page load. Returns 204 No Content.
// Stores anonymised visits in visits/YYYY-MM.jsonl (one JSON record per line).

header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store, no-cache');
header('Content-Length: 0');

// Never track the admin page
$page = strtolower($_GET['page'] ?? 'unknown');
if ($page === 'admin') { http_response_code(204); exit; }

$page   = substr(preg_replace('/[^a-z0-9\-_]/', '', $page), 0, 40);
$ref    = $_GET['ref'] ?? '';
$refHost = $ref ? (parse_url($ref, PHP_URL_HOST) ?: '') : '';

// Anonymise IP — daily rotating hash so we can count uniques without storing IPs
$ip     = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
$ipHash = substr(hash('sha256', $ip . '|' . date('Y-m-d')), 0, 10);

// Detect device type (rough)
$ua     = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
$device = (str_contains($ua, 'mobile') || str_contains($ua, 'android') || str_contains($ua, 'iphone')) ? 'm' : 'd';

// Create visits directory if needed
$dir = __DIR__ . '/visits';
if (!is_dir($dir)) @mkdir($dir, 0755, true);

// Protect the directory from direct browsing
$htaccess = $dir . '/.htaccess';
if (!file_exists($htaccess)) {
    file_put_contents($htaccess, "Order deny,allow\nDeny from all\n");
}

$file  = $dir . '/' . date('Y-m') . '.jsonl';
$entry = json_encode([
    't'  => date('Y-m-d H:i'),
    'p'  => $page,
    'r'  => $refHost,
    'dv' => $device,
    'ih' => $ipHash,
]) . "\n";

@file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);
http_response_code(204);
