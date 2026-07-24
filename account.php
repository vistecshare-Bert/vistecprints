<?php
session_start();

$user = $_SESSION['vp_user'] ?? null;

// Get this user's orders if logged in
$myOrders = [];
if ($user) {
    $ordersFile = __DIR__ . '/orders.json';
    if (file_exists($ordersFile)) {
        $all      = json_decode(file_get_contents($ordersFile), true) ?: [];
        $email    = $user['email'] ?? '';
        $myOrders = array_values(array_filter($all, fn($o) => ($o['customer']['email'] ?? '') === $email));
    }
}

function statusColor($s) {
    return match($s) {
        'fulfilled' => '#1e7e45',
        'cancelled' => '#c00',
        default     => '#e65c00',
    };
}
function statusBg($s) {
    return match($s) {
        'fulfilled' => '#f0faf4',
        'cancelled' => '#fff0f0',
        default     => '#fff3e0',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>My Account — VistecPrints</title>
<link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png"/>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
:root{
  --black:#0a0a0a;--dark:#111;--dark2:#1a1a1a;--dark3:#222;
  --gold:#D49848;--gold-light:#E8B96A;
  --white:#fff;--offwhite:#F0EDE8;--gray:#888;
}
body{background:var(--black);color:var(--offwhite);font-family:'DM Sans',sans-serif;min-height:100vh;}

/* NAV — matches site */
nav{position:sticky;top:0;left:0;right:0;z-index:200;background:rgba(10,10,10,.96);backdrop-filter:blur(12px);border-bottom:1px solid rgba(212,152,72,.15);padding:0 5%;}
.nav-inner{display:flex;align-items:center;justify-content:space-between;height:100px;}
.logo img{height:90px;width:auto;display:block;}
.nav-links{display:flex;align-items:center;gap:32px;list-style:none;}
.nav-links a{color:var(--gray);text-decoration:none;font-size:13px;letter-spacing:1px;text-transform:uppercase;font-weight:500;transition:color .2s;}
.nav-links a:hover{color:var(--gold);}
.nav-right{display:flex;align-items:center;gap:20px;}
.nav-phone{color:var(--gold);font-size:13px;font-weight:500;text-decoration:none;}
.nav-cta{background:var(--gold);color:var(--black);padding:10px 22px;border-radius:2px;font-size:13px;font-weight:600;text-decoration:none;letter-spacing:1px;text-transform:uppercase;}

/* PAGE */
.page{max-width:900px;margin:0 auto;padding:56px 28px 100px;}

/* PROFILE CARD */
.profile-card{display:flex;align-items:center;gap:24px;background:var(--dark2);border:1px solid #2a2a2a;border-radius:4px;padding:28px 32px;margin-bottom:48px;}
.profile-avatar{width:72px;height:72px;border-radius:50%;border:3px solid var(--gold);object-fit:cover;flex-shrink:0;}
.profile-avatar-placeholder{width:72px;height:72px;border-radius:50%;border:3px solid #333;background:#222;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.profile-info h1{font-family:'Bebas Neue',sans-serif;font-size:28px;letter-spacing:2px;color:var(--offwhite);margin-bottom:4px;}
.profile-info p{font-size:13px;color:var(--gray);}
.profile-info .joined{font-size:11px;color:#555;margin-top:6px;letter-spacing:1px;text-transform:uppercase;font-family:'Courier New',monospace;}
.profile-actions{margin-left:auto;display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end;}
.btn-outline-gold{background:none;border:1px solid rgba(212,152,72,.4);color:var(--gold);padding:8px 18px;border-radius:2px;font-size:12px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;cursor:pointer;font-family:inherit;text-decoration:none;display:inline-block;transition:all .15s;}
.btn-outline-gold:hover{background:rgba(212,152,72,.1);border-color:var(--gold);}

/* NOT LOGGED IN */
.not-logged-in{text-align:center;padding:80px 20px;}
.not-logged-in h2{font-family:'Bebas Neue',sans-serif;font-size:32px;letter-spacing:2px;color:var(--offwhite);margin-bottom:12px;}
.not-logged-in p{font-size:14px;color:var(--gray);margin-bottom:32px;}

/* SECTION */
.section-label{font-family:'Courier New',monospace;font-size:11px;letter-spacing:3px;text-transform:uppercase;color:var(--gold);margin-bottom:20px;padding-bottom:12px;border-bottom:1px solid #2a2a2a;}

/* ORDERS */
.order-card{background:var(--dark2);border:1px solid #2a2a2a;border-radius:3px;margin-bottom:16px;overflow:hidden;}
.order-head{padding:14px 20px;background:var(--dark3);border-bottom:1px solid #222;display:flex;align-items:center;gap:16px;flex-wrap:wrap;}
.order-id{font-family:'Bebas Neue',sans-serif;font-size:17px;letter-spacing:1px;color:var(--offwhite);}
.order-date{font-size:12px;color:var(--gray);}
.order-total{font-weight:700;color:var(--gold);margin-left:auto;font-size:15px;}
.order-status{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;}
.order-body{padding:16px 20px;}
.order-items{list-style:none;display:flex;flex-direction:column;gap:10px;}
.order-item{display:flex;align-items:center;gap:12px;padding:8px 0;border-bottom:1px solid #222;}
.order-item:last-child{border-bottom:none;}
.order-item-thumb{width:48px;height:48px;object-fit:contain;background:#1a1a1a;border-radius:3px;border:1px solid #333;flex-shrink:0;}
.order-item-thumb-ph{width:48px;height:48px;background:#1a1a1a;border-radius:3px;border:1px solid #333;flex-shrink:0;}
.order-item-name{font-size:13px;color:var(--offwhite);flex:1;}
.order-item-size{font-size:12px;color:var(--gray);}
.order-item-price{font-size:13px;color:var(--gold);white-space:nowrap;font-weight:600;}
.empty-orders{text-align:center;padding:48px 20px;color:var(--gray);}
.empty-orders a{color:var(--gold);}

@media(max-width:600px){
  .profile-card{flex-direction:column;text-align:center;}
  .profile-actions{margin-left:0;justify-content:center;}
  .order-head{gap:10px;}
  .order-total{margin-left:0;}
}
</style>
</head>
<body>

<nav>
  <div class="nav-inner">
    <a href="index.html" class="logo"><img src="images/Logo.png" alt="VistecPrints"/></a>
    <ul class="nav-links">
      <li><a href="index.html">Home</a></li>
      <li><a href="products.html">Shop</a></li>
      <li><a href="designs.html">Designs</a></li>
      <li><a href="designer.html">Designer</a></li>
      <li><a href="about.html">About</a></li>
      <li><a href="contact.html">Contact</a></li>
    </ul>
    <div class="nav-right">
      <a href="tel:+17043776626" class="nav-phone">+1 704-377-6626</a>
      <a href="quote.html" class="nav-cta">Get a Quote</a>
    </div>
  </div>
</nav>

<div class="page">

<?php if (!$user): ?>
  <!-- NOT LOGGED IN -->
  <div class="not-logged-in">
    <h2>Sign In to View Your Account</h2>
    <p>Track your orders and manage your designs.</p>
    <button class="btn-outline-gold" onclick="vpAuth.open()">Sign Up / Login with Google</button>
  </div>

<?php else: ?>
  <!-- PROFILE CARD -->
  <div class="profile-card">
    <?php if (!empty($user['picture'])): ?>
      <img class="profile-avatar" src="<?= htmlspecialchars($user['picture']) ?>" alt="Profile photo" referrerpolicy="no-referrer"/>
    <?php else: ?>
      <div class="profile-avatar-placeholder">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#555" stroke-width="1.5"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
      </div>
    <?php endif; ?>
    <div class="profile-info">
      <h1><?= htmlspecialchars($user['name'] ?? 'Account') ?></h1>
      <p><?= htmlspecialchars($user['email'] ?? '') ?></p>
      <p class="joined">Member since <?= date('M Y', strtotime($user['createdAt'] ?? 'now')) ?> &nbsp;&middot;&nbsp; <?= count($myOrders) ?> order<?= count($myOrders) !== 1 ? 's' : '' ?></p>
    </div>
    <div class="profile-actions">
      <a href="products.html" class="btn-outline-gold">Shop Now</a>
      <button class="btn-outline-gold" onclick="vpAuth.logout()">Sign Out</button>
    </div>
  </div>

  <!-- ORDER HISTORY -->
  <div id="orders">
    <p class="section-label">Order History</p>

    <?php if (empty($myOrders)): ?>
      <div class="empty-orders">
        <p>No orders yet. <a href="products.html">Start shopping &rarr;</a></p>
      </div>
    <?php else: ?>
      <?php foreach ($myOrders as $o):
        $status = $o['status'] ?? 'pending';
        $items  = $o['items']  ?? [];
      ?>
      <div class="order-card">
        <div class="order-head">
          <span class="order-id"><?= htmlspecialchars($o['id']) ?></span>
          <span class="order-date"><?= date('M j, Y', strtotime($o['date'] ?? 'now')) ?></span>
          <span class="order-status" style="background:<?= statusBg($status) ?>;color:<?= statusColor($status) ?>;border:1px solid <?= statusColor($status) ?>44;"><?= ucfirst($status) ?></span>
          <span class="order-total">$<?= number_format($o['total'] ?? 0, 2) ?></span>
        </div>
        <div class="order-body">
          <ul class="order-items">
            <?php foreach ($items as $item):
              $hasImg = !empty($item['img']) && str_starts_with($item['img'], 'data:');
            ?>
            <li class="order-item">
              <?php if ($hasImg): ?>
                <img class="order-item-thumb" src="<?= htmlspecialchars($item['img']) ?>" alt="Design"/>
              <?php else: ?>
                <div class="order-item-thumb-ph"></div>
              <?php endif; ?>
              <span class="order-item-name">
                <?= htmlspecialchars($item['name'] ?? '') ?>
                <span class="order-item-size"> &mdash; <?= htmlspecialchars($item['size'] ?? '') ?></span>
              </span>
              <span class="order-item-price"><?= htmlspecialchars($item['priceStr'] ?? '') ?> &times;<?= intval($item['qty'] ?? 1) ?></span>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
<?php endif; ?>

</div>

<script src="auth-modal.js"></script>
</body>
</html>
