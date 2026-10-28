<?php
/**
 * Packing slip settings — edit this file, not the code.
 * To reuse the packing slip module for another store, copy the whole packing-slip/
 * folder and change the values below.
 */
return [

    // ── Store details (header) ──────────────────────────────
    'store_name' => 'VistecPrints',
    'tagline'    => 'Custom Apparel & Full-Color Printing',
    'address'    => ['701 Morris St', 'Charlotte, NC 28202'],
    'website'    => 'vistecprints.com',
    'email'      => 'vistecfx@gmail.com',
    'phone'      => '704-377-6626',

    // Logo path relative to the website root (the folder containing index.html).
    // PNG or JPG. Leave '' to show the store name as text instead.
    'logo' => 'images/Logo.png',
    // Convert the logo to grayscale so it prints cleanly on a black & white laser printer
    'logo_grayscale' => true,

    // ── Paper ───────────────────────────────────────────────
    // 'letter' = US Letter 8.5×11 (regular printer)
    // '4x6'    = 4×6 thermal label printer (compact layout: no thumbnails/SKU column)
    'paper' => 'letter',

    // ── Content ─────────────────────────────────────────────
    'show_prices'      => false,             // true = show item prices and order totals
    'show_thumbnails'  => true,              // small design/product image on each line (letter size only)
    'shipping_method'  => 'Standard Shipping', // used when an order doesn't record its own method
    'timezone'         => 'America/New_York',  // order dates print in this time zone

    // ── Footer ──────────────────────────────────────────────
    'thank_you'     => 'Thank you for supporting a Charlotte print shop!',
    'return_policy' => 'All items are custom printed, so sales are final. If your order arrived damaged or wrong, contact us within 7 days at vistecfx@gmail.com or 704-377-6626 and we\'ll make it right.',
    'social'        => 'Instagram @mrvistec',

    // ── Automation ──────────────────────────────────────────
    // Each paid order's packing slip PDF is emailed here as an attachment
    'order_email' => 'vistecshare@gmail.com',
    // Gets an alert if a slip ever fails to generate (the order itself still goes through)
    'alert_email' => 'vistecshare@gmail.com',
    'from_email'  => 'VistecPrints <noreply@vistecprints.com>',

    // Where PDFs, the printed/unprinted list and the error log are kept. Must be writable
    // and NOT web-accessible (an .htaccess deny file is created inside it automatically).
    // Relative to the website root.
    'storage_dir' => 'orders/packing-slips',
];
