<?php
$cfgFile = __DIR__ . '/stripe-config.php';
if (!file_exists($cfgFile)) { die('Payment configuration missing.'); }
require_once $cfgFile;

$sid = trim($_GET['sid'] ?? '');
if (!$sid || !preg_match('/^cs_/', $sid)) {
    http_response_code(400); die('Invalid session.');
}

// Verify payment status with Stripe
$ch = curl_init('https://api.stripe.com/v1/checkout/sessions/' . urlencode($sid));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERPWD        => STRIPE_SECRET_KEY . ':',
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT        => 15,
]);
$res  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$session   = json_decode($res, true);
$isPaid    = ($code === 200 && ($session['payment_status'] ?? '') === 'paid');

// Load pending order
$pendingFile = __DIR__ . '/pending_orders/' . $sid . '.json';
$order = [];
if (file_exists($pendingFile)) {
    $order = json_decode(file_get_contents($pendingFile), true) ?? [];
}

if ($isPaid && !empty($order) && ($order['status'] ?? '') === 'pending_payment') {
    // Finalize the order
    $order['status']      = 'paid';
    $order['paidAt']      = date('c');
    $order['stripeEmail'] = $session['customer_details']['email'] ?? ($order['customer']['email'] ?? '');

    $ordersDir = __DIR__ . '/orders';
    if (!is_dir($ordersDir)) mkdir($ordersDir, 0755, true);
    file_put_contents($ordersDir . '/' . $order['orderId'] . '.json',
        json_encode($order, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    unlink($pendingFile);

    // ── Auto-create DTF pipeline entry ──────────────────────────
    $dtfDir   = __DIR__ . '/orders/';
    $dtfId    = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(md5($order['orderId']), 0, 6));
    $dtfItems = $order['items'] ?? [];
    $garmentTypes = array_unique(array_filter(array_map(fn($i) => trim($i['garment'] ?? ''), $dtfItems)));
    $itemLines = '';
    foreach ($dtfItems as $it) {
        $itemLines .= '  - ' . ($it['name'] ?? 'Item') . ' | Size: ' . ($it['size'] ?? '?') . ' ×' . (int)($it['qty'] ?? 1) . "\n";
    }
    $dtfRec = [
        'id'              => $dtfId,
        'web_order_id'    => $order['orderId'],
        'customer_name'   => $order['customer']['name']  ?? '',
        'customer_email'  => $order['stripeEmail'],
        'customer_phone'  => $order['customer']['phone'] ?? '',
        'garment_type'    => implode(', ', $garmentTypes),
        'qty'             => array_sum(array_map(fn($i) => intval($i['qty'] ?? 1), $dtfItems)),
        'print_location'  => '',
        'sizes_breakdown' => '',
        'print_type'      => '',
        'price_charged'   => number_format(floatval($order['total'] ?? 0), 2),
        'production_cost' => '',
        'due_date'        => '',
        'notes'           => 'Stripe order #' . $order['orderId'] . "\n" . trim($itemLines),
        'stage'           => 'new_order',
        'date'            => date('c'),
        'source'          => 'web_order',
    ];
    file_put_contents($dtfDir . $dtfId . '.json', json_encode($dtfRec, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // ── Send customer receipt ────────────────────────────────────
    $toEmail  = $order['stripeEmail'] ?: ($order['customer']['email'] ?? '');
    $custName = $order['customer']['name'] ?? 'Customer';
    $addrLine = trim(implode(', ', array_filter([
        $order['customer']['address']['line1'] ?? '',
        $order['customer']['address']['city']  ?? '',
        $order['customer']['address']['state'] ?? '',
        $order['customer']['address']['zip']   ?? '',
    ])));
    $orderTotal = number_format(floatval($order['total'] ?? ($order['subtotal'] ?? 0) + 4.99), 2);

    if ($toEmail) {
        $itemRows = '';
        foreach ($order['items'] ?? [] as $it) {
            $itName  = htmlspecialchars($it['name'] ?? 'Item');
            $itSize  = htmlspecialchars($it['size'] ?? '—');
            $itQty   = (int)($it['qty'] ?? 1);
            $itPrice = number_format(floatval($it['price'] ?? 0) * $itQty, 2);
            $itemRows .= "
            <tr>
              <td style='padding:10px 0;border-bottom:1px solid #2a2a2a;color:#F0EDE8;font-size:13px;'>{$itName} &mdash; Size {$itSize}</td>
              <td style='padding:10px 0;border-bottom:1px solid #2a2a2a;color:#D49848;font-size:13px;text-align:right;'>&times;{$itQty} &nbsp; \${$itPrice}</td>
            </tr>";
        }

        $receiptHtml = "<!DOCTYPE html><html><head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#0a0a0a;font-family:Arial,sans-serif;'>
<table width='100%' cellpadding='0' cellspacing='0' style='background:#0a0a0a;padding:40px 20px;'>
<tr><td align='center'>
<table width='560' cellpadding='0' cellspacing='0' style='max-width:560px;width:100%;'>

  <tr><td style='padding-bottom:28px;'>
    <h1 style='font-family:Arial,sans-serif;font-size:28px;letter-spacing:4px;color:#D49848;margin:0;'>VISTEC GRAPH<span style='color:#fff;'>X</span></h1>
    <p style='color:#888;font-size:12px;margin:4px 0 0;'>Charlotte, NC &bull; +1 704-377-6626</p>
  </td></tr>

  <tr><td style='background:#1a1a1a;border:1px solid #2a2a2a;border-radius:4px;padding:28px;'>
    <p style='color:#D49848;font-size:11px;letter-spacing:2px;text-transform:uppercase;margin:0 0 6px;'>Order Confirmed</p>
    <h2 style='color:#F0EDE8;font-size:22px;margin:0 0 20px;'>Thank you, " . htmlspecialchars($custName) . "!</h2>
    <p style='color:#888;font-size:13px;line-height:1.7;margin:0 0 24px;'>Your payment was received and we&rsquo;re getting started on your order. We&rsquo;ll contact you with updates.</p>

    <p style='color:#888;font-size:11px;letter-spacing:1.5px;text-transform:uppercase;margin:0 0 4px;'>Order ID</p>
    <p style='color:#D49848;font-size:18px;font-weight:bold;letter-spacing:2px;margin:0 0 20px;'>" . htmlspecialchars($order['orderId']) . "</p>

    <table width='100%' cellpadding='0' cellspacing='0'>{$itemRows}</table>

    <table width='100%' cellpadding='0' cellspacing='0' style='margin-top:16px;'>
      <tr>
        <td style='color:#888;font-size:12px;'>Shipping</td>
        <td style='color:#F0EDE8;font-size:12px;text-align:right;'>\$4.99</td>
      </tr>
      <tr>
        <td style='color:#F0EDE8;font-size:15px;font-weight:bold;padding-top:10px;border-top:1px solid #333;'>Total Charged</td>
        <td style='color:#D49848;font-size:15px;font-weight:bold;padding-top:10px;border-top:1px solid #333;text-align:right;'>\${$orderTotal}</td>
      </tr>
    </table>

    " . ($addrLine ? "<p style='color:#888;font-size:12px;margin:20px 0 0;'>Ship to: <span style='color:#F0EDE8;'>" . htmlspecialchars($addrLine) . "</span></p>" : "") . "
  </td></tr>

  <tr><td style='padding-top:24px;'>
    <p style='color:#555;font-size:12px;line-height:1.7;margin:0;'>Questions? Reply to this email or call us at <a href='tel:+17043776626' style='color:#D49848;'>+1 704-377-6626</a>.</p>
  </td></tr>

</table>
</td></tr></table>
</body></html>";

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: Vistec GraphX <noreply@vistecprints.com>\r\n";
        $headers .= "Reply-To: orders@vistecprints.com\r\n";
        mail($toEmail, 'Order Confirmed — ' . $order['orderId'] . ' | Vistec GraphX', $receiptHtml, $headers);
    }

    // ── Send admin notification ──────────────────────────────────
    $adminEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : null;
    if ($adminEmail) {
        $adminLines = "New order received!\n\nOrder ID: {$order['orderId']}\nCustomer: {$custName}\nEmail: {$toEmail}\nTotal: \${$orderTotal}\n";
        foreach ($order['items'] ?? [] as $it) {
            $adminLines .= '  - ' . ($it['name'] ?? 'Item') . ' Size ' . ($it['size'] ?? '?') . ' ×' . (int)($it['qty'] ?? 1) . "\n";
        }
        $adminLines .= "\nView in dashboard: https://rebuild.vistecprints.com/admin/dashboard.php?tab=orders";
        $aHeaders  = "From: Vistec GraphX <noreply@vistecprints.com>\r\n";
        $aHeaders .= "Reply-To: {$toEmail}\r\n";
        mail($adminEmail, 'New Order — ' . $order['orderId'], $adminLines, $aHeaders);
    }
}

$orderId  = $order['orderId']  ?? '—';
$customer = $order['customer'] ?? [];
$items    = $order['items']    ?? [];
$subtotal = floatval($order['subtotal'] ?? 0);
$total    = floatval($order['total']    ?? 0);
$name     = htmlspecialchars($customer['name']  ?? '');
$email    = htmlspecialchars($customer['email'] ?? '');
$addr     = $customer['address'] ?? [];
$addrStr  = htmlspecialchars(
    ($addr['line1'] ?? '') . ', ' .
    ($addr['city']  ?? '') . ', ' .
    ($addr['state'] ?? '') . ' ' .
    ($addr['zip']   ?? '')
);
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title><?= $isPaid ? 'Order Confirmed' : 'Payment Pending' ?> — Vistec GraphX</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
:root{--black:#0a0a0a;--dark:#111111;--dark2:#1a1a1a;--dark3:#222222;--gold:#D49848;--gold-light:#E8B96A;--gold-dim:#9A6B2E;--white:#FFFFFF;--offwhite:#F0EDE8;--gray:#888888;--red:#e05555;}
body{background:var(--black);color:var(--offwhite);font-family:'DM Sans',sans-serif;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px 5%;}
nav{position:fixed;top:0;left:0;right:0;background:rgba(10,10,10,0.97);backdrop-filter:blur(12px);border-bottom:1px solid rgba(212,152,72,0.15);padding:0 5%;}
.nav-inner{display:flex;align-items:center;justify-content:space-between;height:80px;}
.logo img{height:70px;width:auto;display:block;}
.card{max-width:560px;width:100%;text-align:center;margin-top:100px;}
.icon-wrap{width:88px;height:88px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 28px;}
.icon-wrap.success{border:2px solid var(--gold);color:var(--gold);}
.icon-wrap.pending{border:2px solid var(--gray);color:var(--gray);}
.big-title{font-family:'Bebas Neue',sans-serif;font-size:clamp(40px,8vw,60px);letter-spacing:3px;margin-bottom:10px;}
.big-title.success{color:var(--gold);}
.big-title.pending{color:var(--gray);}
.sub{font-size:14px;color:var(--gray);line-height:1.7;margin-bottom:32px;max-width:440px;margin-left:auto;margin-right:auto;}
.order-card{background:var(--dark2);border:1px solid rgba(212,152,72,0.15);border-radius:4px;padding:22px 26px;margin-bottom:28px;text-align:left;}
.order-row{display:flex;justify-content:space-between;font-size:13px;color:var(--gray);padding:6px 0;}
.order-row:not(:last-child){border-bottom:1px solid rgba(255,255,255,0.05);}
.order-row strong{color:var(--offwhite);font-weight:500;}
.order-id{font-family:'Bebas Neue',sans-serif;font-size:20px;letter-spacing:1.5px;color:var(--gold);margin-bottom:16px;}
.items-list{margin:8px 0 0;padding:0;list-style:none;}
.items-list li{font-size:12px;color:var(--gray);padding:4px 0;}
.items-list li span{color:var(--offwhite);}
.total-row{display:flex;justify-content:space-between;font-size:15px;font-weight:600;color:var(--offwhite);padding-top:12px;margin-top:6px;border-top:1px solid rgba(255,255,255,0.08);}
.total-row span:last-child{color:var(--gold);}
.cta{display:inline-block;background:var(--gold);color:var(--black);padding:15px 36px;border-radius:3px;text-decoration:none;font-size:13px;font-weight:700;letter-spacing:2px;text-transform:uppercase;transition:background 0.2s;}
.cta:hover{background:var(--gold-light);}
.help{margin-top:20px;font-size:12px;color:var(--gray);}
.help a{color:var(--gold-dim);text-decoration:none;}
.help a:hover{color:var(--gold);}
</style>
</head>
<body>

<nav>
  <div class="nav-inner">
    <a href="index.html"><img src="images/Logo.png" alt="Vistec GraphX" style="height:70px;"/></a>
  </div>
</nav>

<div class="card">

<?php if ($isPaid): ?>
  <div class="icon-wrap success">
    <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="20 6 9 17 4 12"/></svg>
  </div>
  <h1 class="big-title success">Order Confirmed!</h1>
  <p class="sub">Payment received. We'll have your order ready and will reach out to <strong style="color:var(--offwhite);"><?= $email ?></strong> with updates.</p>

  <div class="order-card">
    <div class="order-id"><?= htmlspecialchars($orderId) ?></div>

    <?php if ($name): ?>
    <div class="order-row"><span>Name</span><strong><?= $name ?></strong></div>
    <?php endif; ?>

    <?php if ($email): ?>
    <div class="order-row"><span>Email</span><strong><?= $email ?></strong></div>
    <?php endif; ?>

    <?php if (trim($addrStr, ', ')): ?>
    <div class="order-row"><span>Ship to</span><strong><?= $addrStr ?></strong></div>
    <?php endif; ?>

    <?php if (!empty($items)): ?>
    <div class="order-row" style="flex-direction:column;gap:4px;">
      <span style="margin-bottom:4px;">Items</span>
      <ul class="items-list">
        <?php foreach ($items as $it): ?>
          <li>
            <span><?= htmlspecialchars($it['name'] ?? 'Item') ?></span>
            — Size <?= htmlspecialchars($it['size'] ?? '?') ?>
            × <?= (int)($it['qty'] ?? 1) ?>
            &nbsp;<span style="color:var(--gold);">$<?= number_format(floatval($it['price'] ?? 0), 2) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <div class="total-row">
      <span>Total Charged</span>
      <span>$<?= number_format($total ?: ($subtotal + 4.99), 2) ?></span>
    </div>
  </div>

  <a href="products.html" class="cta">Continue Shopping</a>
  <p class="help">Questions? Call us at <a href="tel:+17043776626">+1 704-377-6626</a></p>
  <script>localStorage.removeItem('vp_cart');</script>

<?php else: ?>
  <div class="icon-wrap pending">
    <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
  </div>
  <h1 class="big-title pending">Payment Pending</h1>
  <p class="sub">We haven't received confirmation of your payment yet. If you completed checkout, it may take a moment to process. Please don't pay twice.</p>

  <?php if ($orderId !== '—'): ?>
  <div class="order-card">
    <div class="order-id"><?= htmlspecialchars($orderId) ?></div>
    <div class="order-row"><span>Status</span><strong style="color:var(--gray);">Awaiting payment confirmation</strong></div>
    <?php if ($email): ?>
    <div class="order-row"><span>Email</span><strong><?= $email ?></strong></div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <a href="checkout.html" class="cta" style="background:var(--dark2);color:var(--offwhite);border:1px solid rgba(212,152,72,0.3);">Return to Checkout</a>
  <p class="help">Need help? Call <a href="tel:+17043776626">+1 704-377-6626</a></p>
<?php endif; ?>

</div>
</body>
</html>
