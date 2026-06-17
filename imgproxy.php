<?php
/**
 * Reverse-proxy for vistecprints.com images.
 * Adds Access-Control-Allow-Origin so images can be drawn onto an HTML
 * <canvas> and exported via toDataURL() without a SecurityError.
 *
 * Usage: imgproxy.php?url=https%3A%2F%2Fwww.vistecprints.com%2F...
 */

$url = filter_input(INPUT_GET, 'url', FILTER_SANITIZE_URL);
if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
    http_response_code(400); exit('Bad request');
}

// Only proxy our own storefront — block any other host
$host = parse_url($url, PHP_URL_HOST);
if (!in_array($host, ['www.vistecprints.com', 'vistecprints.com'], true)) {
    http_response_code(403); exit('Forbidden');
}

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    http_response_code(204); exit;
}

header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=604800, immutable'); // 7-day browser cache

// Fetch image — curl preferred, file_get_contents fallback
$data = false;
if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; VistecProxy/1.0)',
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw !== false && $code >= 200 && $code < 400) $data = $raw;
} else {
    $ctx = stream_context_create(['http' => [
        'timeout'       => 15,
        'ignore_errors' => true,
        'user_agent'    => 'VistecProxy/1.0',
    ]]);
    $data = @file_get_contents($url, false, $ctx);
}

if ($data === false || $data === '') {
    http_response_code(502); exit('Upstream error');
}

$ext  = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
$mime = ($ext === 'png') ? 'image/png'
      : (($ext === 'gif') ? 'image/gif'
      : (($ext === 'webp') ? 'image/webp'
      : 'image/jpeg'));

header("Content-Type: $mime");
header('Content-Length: ' . strlen($data));
echo $data;
