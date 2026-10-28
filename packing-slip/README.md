# Packing slips

Creates a print-ready PDF packing slip for every paid order, emails it to the shop, and keeps a
printed / not-printed list in the admin dashboard (**Packing Slips** tab).

## Files

| Path | What it is |
|---|---|
| `config.php` | Store details, logo, footer text, paper size, show prices, emails. **Edit this.** |
| `template.php` | The slip's HTML/CSS. Edit to change the look (Dompdf: use tables, not flexbox/grid). |
| `src/PackingSlip.php` | Builds, saves, emails and tracks slips. |
| `src/Code128.php` | Order-number barcode. |
| `lib/dompdf/` | Dompdf 3.1.6 PDF library (bundled; no Composer needed). |

Generated slips go to `orders/packing-slips/` (set by `storage_dir`), which is blocked from the web
and also holds `status.json` (printed flags) and `errors.log`.

## Common changes

- **Logo:** put the image in the site (e.g. `images/Logo.png`) and set `'logo'` in `config.php`.
  It's converted to grayscale for black & white printers (`'logo_grayscale' => false` to turn off).
- **Footer:** `thank_you`, `return_policy`, `social` in `config.php`.
- **Letter ↔ 4×6 thermal:** `'paper' => 'letter'` or `'paper' => '4x6'`.
- **Show prices:** `'show_prices' => true`.

## Test

    php test-packing-slip.php

Writes sample PDFs to `packing-slip/test-output/` — nothing is emailed.

## Reusing for another store

Copy this whole folder into the other site, edit `config.php`, and call after payment is confirmed:

    require_once __DIR__ . '/packing-slip/src/PackingSlip.php';
    (new PackingSlip())->processPaidOrder($order);   // never throws

`$order` uses the same shape as this site's `orders.json` records (`id`, `date`, `customer`,
`items[]` with `name`/`size`/`qty`/`color`/`garment`/`img`, `notes`, `subtotal`, `shipping`, `total`).
