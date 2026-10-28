<?php
/**
 * Packing slip HTML/CSS — edit freely to change the look.
 * Rendered by Dompdf (CSS 2.1 + some CSS3; no flexbox/grid — use tables for layout).
 *
 * Available: $slips (array of prepared orders), $cfg (config.php), $compact (true on 4×6), $logo (src or '').
 */
if (!function_exists('ps_e')) {
    function ps_e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
$showPrices = !empty($cfg['show_prices']);
$showThumbs = !$compact && !empty($cfg['show_thumbnails']);
?><!DOCTYPE html>
<html><head><meta charset="UTF-8"/>
<style>
@page { margin: <?= $compact ? '0.22in 0.14in 0.22in' : '0.6in 0.5in 0.6in' ?>; }
* { box-sizing: border-box; }
body { font-family: 'DejaVu Sans', sans-serif; font-size: <?= $compact ? '7pt' : '9pt' ?>; color: #000; margin: 0; }
table { border-collapse: collapse; width: 100%; }
td, th { vertical-align: top; }

.head td { padding: 0; }
.logo { height: <?= $compact ? '0.45in' : '0.8in' ?>; margin-bottom: 4px; }
.store-name { font-size: <?= $compact ? '11pt' : '15pt' ?>; font-weight: bold; }
.store-info { font-size: <?= $compact ? '6.5pt' : '8pt' ?>; line-height: 1.35; color: #222; }
.doc-title { font-size: <?= $compact ? '7pt' : '9pt' ?>; letter-spacing: 2px; text-transform: uppercase; color: #444; }
.order-no { font-size: <?= $compact ? '10.5pt' : '16pt' ?>; font-weight: bold; margin: 2px 0 4px; white-space: nowrap; }
.barcode { width: <?= $compact ? '1.5in' : '2.6in' ?>; height: <?= $compact ? '0.32in' : '0.5in' ?>; }
.meta { font-size: <?= $compact ? '6.5pt' : '8.5pt' ?>; line-height: 1.5; white-space: nowrap; }

.rule { border-top: 2px solid #000; margin: <?= $compact ? '4px 0' : '10px 0' ?>; }
.label { font-size: <?= $compact ? '6pt' : '7.5pt' ?>; font-weight: bold; letter-spacing: 1.5px; text-transform: uppercase; color: #444; margin-bottom: <?= $compact ? '1px' : '3px' ?>; }
.addr { font-size: <?= $compact ? '7.5pt' : '10.5pt' ?>; line-height: <?= $compact ? '1.25' : '1.4' ?>; }
.addr .name { font-weight: bold; }

.items { margin-top: <?= $compact ? '6px' : '12px' ?>; }
.items thead { display: table-header-group; }
.items th { font-size: <?= $compact ? '6pt' : '7.5pt' ?>; text-transform: uppercase; letter-spacing: 1px; text-align: left; border-bottom: 1.5px solid #000; padding: 4px 4px; }
.items td { border-bottom: 0.75px solid #999; padding: <?= $compact ? '3px 3px' : '5px 4px' ?>; vertical-align: middle; }
.items tr { page-break-inside: avoid; }
.box { display: inline-block; width: 10px; height: 10px; border: 1.25px solid #000; }
.thumb { width: 0.55in; height: 0.55in; }
.sub { font-size: <?= $compact ? '6pt' : '7.5pt' ?>; color: #444; }
.num { text-align: center; }
.qty { text-align: center; font-weight: bold; font-size: <?= $compact ? '8pt' : '10.5pt' ?>; }
.right { text-align: right; }

.totals { margin-top: 8px; }
.totals td { padding: 2px 4px; }
.notes { margin-top: <?= $compact ? '5px' : '10px' ?>; border: 1px solid #000; padding: <?= $compact ? '3px 5px' : '6px 8px' ?>; page-break-inside: avoid; }
.packer { margin-top: <?= $compact ? '6px' : '18px' ?>; page-break-inside: avoid; }
.packer td { padding: <?= $compact ? '2px 0' : '4px 0' ?>; font-size: <?= $compact ? '7pt' : '9pt' ?>; }
.line { border-bottom: 1px solid #000; display: inline-block; width: <?= $compact ? '1.1in' : '2.2in' ?>; }
.footer { margin-top: <?= $compact ? '6px' : '18px' ?>; text-align: center; font-size: <?= $compact ? '5.5pt' : '8.5pt' ?>; line-height: <?= $compact ? '1.3' : '1.5' ?>; page-break-inside: avoid; }
.footer .thanks { font-weight: bold; font-size: <?= $compact ? '6.5pt' : '10pt' ?>; }
</style>
</head><body>
<?php foreach ($slips as $n => $s): ?>
<div class="slip"<?= $n > 0 ? ' style="page-break-before:always;"' : '' ?>>

  <!-- Header: store | order -->
  <table class="head"><tr>
    <td style="width:55%;">
      <?php if ($logo): ?><img class="logo" src="<?= ps_e($logo) ?>" alt=""/><br/><?php endif; ?>
      <div class="store-name"><?= ps_e($cfg['store_name'] ?? '') ?></div>
      <div class="store-info">
        <?php if (!$compact && !empty($cfg['tagline'])): ?><?= ps_e($cfg['tagline']) ?><br/><?php endif; ?>
        <?= implode('<br/>', array_map('ps_e', (array)($cfg['address'] ?? []))) ?><br/>
        <?= ps_e($cfg['website'] ?? '') ?><br/>
        <?= ps_e($cfg['email'] ?? '') ?> · <span style="white-space:nowrap;"><?= ps_e($cfg['phone'] ?? '') ?></span>
      </div>
    </td>
    <?php if ($compact): ?>
    <td style="width:45%;" class="right"><div class="doc-title">Packing Slip</div></td>
  </tr></table>
  <!-- 4×6: order number + barcode get their own full-width row -->
  <table class="head" style="margin-top:4px;"><tr>
    <td>
      <div class="order-no">#<?= ps_e($s['id']) ?></div>
      <div class="meta">
        <b>Ordered</b> <?= ps_e($s['date']) ?><br/>
        <b>Ship via</b> <?= ps_e($s['shipping']) ?>
      </div>
    </td>
    <td class="right" style="width:1.55in;"><img class="barcode" src="<?= $s['barcode'] ?>" alt=""/></td>
  </tr></table>
    <?php else: ?>
    <td style="width:45%;" class="right">
      <div class="doc-title">Packing Slip</div>
      <div class="order-no">#<?= ps_e($s['id']) ?></div>
      <img class="barcode" src="<?= $s['barcode'] ?>" alt=""/>
      <div class="meta">
        <b>Order date</b> <?= ps_e($s['date']) ?><br/>
        <b>Shipping</b> <?= ps_e($s['shipping']) ?>
      </div>
    </td>
  </tr></table>
    <?php endif; ?>

  <div class="rule"></div>

  <!-- Ship to / Bill to -->
  <table><tr>
    <td style="width:<?= $s['bill_to'] ? '50%' : '100%' ?>;">
      <div class="label">Ship to</div>
      <div class="addr">
        <?php foreach ($s['ship_to'] as $i => $l): ?>
          <?= $i === 0 ? '<span class="name">' . ps_e($l) . '</span>' : ps_e($l) ?><br/>
        <?php endforeach; ?>
        <?php if ($s['phone']): ?><span class="sub"><?= ps_e($s['phone']) ?></span><?php endif; ?>
      </div>
    </td>
    <?php if ($s['bill_to']): ?>
    <td style="width:50%;">
      <div class="label">Bill to</div>
      <div class="addr"><?= implode('<br/>', array_map('ps_e', $s['bill_to'])) ?></div>
    </td>
    <?php endif; ?>
  </tr></table>

  <!-- Items -->
  <table class="items">
    <thead><tr>
      <th style="width:<?= $compact ? '14px' : '22px' ?>;"></th>
      <?php if ($showThumbs): ?><th style="width:0.62in;"></th><?php endif; ?>
      <?php if (!$compact): ?><th>SKU</th><?php endif; ?>
      <th>Item</th>
      <?php if (!$compact): ?><th>Print</th><?php endif; ?>
      <th class="num">Size</th>
      <?php if (!$compact): ?><th>Color</th><?php endif; ?>
      <th class="num">Qty</th>
      <?php if ($showPrices): ?><th class="right">Price</th><?php endif; ?>
    </tr></thead>
    <tbody>
    <?php foreach ($s['lines'] as $l): ?>
      <tr>
        <td><span class="box"></span></td>
        <?php if ($showThumbs): ?><td><?php if ($l['img']): ?><img class="thumb" src="<?= ps_e($l['img']) ?>" alt=""/><?php endif; ?></td><?php endif; ?>
        <?php if (!$compact): ?><td class="sub"><?= ps_e($l['sku'] ?: '—') ?></td><?php endif; ?>
        <td>
          <?= ps_e($l['name']) ?>
          <?php if ($compact): ?>
            <?php $extra = array_filter([$l['color'], $l['print']]); ?>
            <?php if ($extra): ?><br/><span class="sub"><?= ps_e(implode(' · ', $extra)) ?></span><?php endif; ?>
          <?php elseif ($l['garment']): ?><br/><span class="sub"><?= ps_e($l['garment']) ?></span><?php endif; ?>
        </td>
        <?php if (!$compact): ?><td><?= ps_e($l['print'] ?: '—') ?></td><?php endif; ?>
        <td class="num"><b><?= ps_e($l['size']) ?></b></td>
        <?php if (!$compact): ?><td><?= ps_e($l['color'] ?: '—') ?></td><?php endif; ?>
        <td class="qty"><?= (int)$l['qty'] ?></td>
        <?php if ($showPrices): ?><td class="right"><?= ps_e($l['price']) ?></td><?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <table class="totals"><tr>
    <td><b>Total items: <?= (int)$s['item_count'] ?></b> <span class="sub">(<?= count($s['lines']) ?> line<?= count($s['lines']) === 1 ? '' : 's' ?>)</span></td>
    <?php if ($showPrices): ?>
    <td class="right">
      Subtotal $<?= number_format($s['subtotal'], 2) ?><br/>
      Shipping $<?= number_format($s['ship_cost'], 2) ?><br/>
      <b>Total $<?= number_format($s['total'], 2) ?></b>
    </td>
    <?php endif; ?>
  </tr></table>

  <?php if ($s['notes']): ?>
  <div class="notes">
    <div class="label">Customer notes</div>
    <?= nl2br(ps_e($s['notes'])) ?>
  </div>
  <?php endif; ?>

  <table class="packer"><tr>
    <td>Packed by: <span class="line">&nbsp;</span></td>
    <td class="right">Date: <span class="line" style="width:<?= $compact ? '0.8in' : '1.4in' ?>;">&nbsp;</span></td>
  </tr></table>

  <div class="footer">
    <?php if (!empty($cfg['thank_you'])): ?><div class="thanks"><?= ps_e($cfg['thank_you']) ?></div><?php endif; ?>
    <?php if (!empty($cfg['return_policy'])): ?><div><?= ps_e($cfg['return_policy']) ?></div><?php endif; ?>
    <?php if (!empty($cfg['social'])): ?><div><?= ps_e($cfg['social']) ?></div><?php endif; ?>
  </div>

</div>
<?php endforeach; ?>
</body></html>
