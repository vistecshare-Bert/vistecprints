<?php
// Serve products.json with no-cache headers so HostGator never caches stale data.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

$file = __DIR__ . '/products.json';
echo file_exists($file) ? file_get_contents($file) : '[]';
