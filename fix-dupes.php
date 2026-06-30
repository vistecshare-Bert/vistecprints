<?php
// Remove the 3 known duplicate products by ID. DELETE THIS FILE after running.
$file     = __DIR__ . '/products.json';
$products = json_decode(file_get_contents($file), true);
$before   = count($products);

$remove = [
    'fun_i_m_ok_broken_mpb81qwo',
    'fun_the_woman_of_my_dreams_wears_pink_and_gr_mpb81r2e',
    'no_kings_no_kings__7_mpb8290h',
];

$cleaned = array_values(array_filter($products, fn($p) => !in_array($p['id'], $remove)));
file_put_contents($file, json_encode($cleaned, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

echo "Done. Removed " . ($before - count($cleaned)) . " duplicate(s). Total: " . count($cleaned);
