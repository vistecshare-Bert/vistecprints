<?php
// Diagnostic + fix: remove duplicates by name AND by img (same image = same product)
// DELETE THIS FILE after running.
$file     = __DIR__ . '/products.json';
$products = json_decode(file_get_contents($file), true);

// Find dupes by img path (same image used twice = duplicate listing)
$seenImg  = [];
$seenName = [];
$dupes    = [];
$cleaned  = [];

foreach ($products as $p) {
    $nameKey = strtolower(trim($p['name'] ?? ''));
    $imgKey  = strtolower(trim($p['img']  ?? ''));

    if ($imgKey && isset($seenImg[$imgKey])) {
        $dupes[] = "DUPE IMG  id={$p['id']} name={$p['name']}";
        continue;
    }
    if ($nameKey && isset($seenName[$nameKey])) {
        $dupes[] = "DUPE NAME id={$p['id']} name={$p['name']}";
        continue;
    }
    if ($imgKey)  $seenImg[$imgKey]   = true;
    if ($nameKey) $seenName[$nameKey] = true;
    $cleaned[] = $p;
}

$removed = count($products) - count($cleaned);
if ($removed > 0) {
    file_put_contents($file, json_encode($cleaned, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

echo "Before: " . count($products) . "  After: " . count($cleaned) . "  Removed: $removed\n\n";
echo "Duplicates found:\n";
echo implode("\n", $dupes) ?: 'None';
