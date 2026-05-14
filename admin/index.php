<?php
session_start();
if (!empty($_SESSION['vp_admin'])) {
    header('Location: dashboard.php');
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = trim($_POST['password'] ?? '');
    if ($u === 'Admin' && $p === 'LetsgoVistec2026!') {
        $_SESSION['vp_admin'] = true;
        header('Location: dashboard.php');
        exit;
    }
    $error = 'Invalid username or password.';
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>VistecPrints Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{background:#0a0a0a;font-family:'DM Sans',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;}
.card{background:#111;border:1px solid rgba(212,152,72,0.2);padding:48px 40px;width:100%;max-width:400px;border-radius:4px;}
.logo{font-family:'Bebas Neue',sans-serif;font-size:28px;letter-spacing:3px;color:#D49848;text-align:center;margin-bottom:4px;}
.logo span{color:#fff;}
.subtitle{text-align:center;font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#555;margin-bottom:36px;}
label{display:block;font-size:12px;letter-spacing:1px;text-transform:uppercase;color:#888;margin-bottom:6px;}
input{width:100%;background:#1a1a1a;border:1px solid #333;color:#f0ede8;padding:12px 16px;font-size:14px;font-family:'DM Sans',sans-serif;border-radius:2px;outline:none;margin-bottom:20px;}
input:focus{border-color:rgba(212,152,72,0.5);}
button{width:100%;background:#D49848;color:#000;border:none;padding:14px;font-size:13px;font-weight:700;letter-spacing:2px;text-transform:uppercase;cursor:pointer;font-family:'DM Sans',sans-serif;border-radius:2px;transition:background 0.2s;}
button:hover{background:#E8B96A;}
.error{background:rgba(220,50,50,0.1);border:1px solid rgba(220,50,50,0.3);color:#ff6b6b;padding:12px 16px;border-radius:2px;font-size:13px;margin-bottom:20px;}
</style>
</head>
<body>
<div class="card">
  <div class="logo">VISTEC<span>PRINTS</span></div>
  <div class="subtitle">Admin Panel</div>
  <?php if ($error): ?>
  <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST">
    <label>Username</label>
    <input type="text" name="username" autocomplete="username" required/>
    <label>Password</label>
    <input type="password" name="password" autocomplete="current-password" required/>
    <button type="submit">Log In</button>
  </form>
</div>
</body>
</html>
