<?php
session_start();
if (!empty($_SESSION['vp_admin'])) {
    header('Location: dashboard.php');
    exit;
}

$usersFile = __DIR__ . '/users.json';

function loadUsers($file) {
    if (!file_exists($file)) return [];
    return json_decode(file_get_contents($file), true) ?: [];
}

$error    = '';
$users    = loadUsers($usersFile);
$firstRun = empty($users);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($firstRun) {
        $name     = trim($_POST['name']     ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password']      ?? '';
        $confirm  = $_POST['confirm']       ?? '';

        if (!$name || !$username || !$password) {
            $error = 'All fields are required.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $error = 'Username may only contain letters, numbers, and underscores.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } else {
            $newUser = [
                'id'            => uniqid('u_'),
                'name'          => $name,
                'username'      => $username,
                'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                'role'          => 'admin',
                'created'       => date('c'),
                'last_login'    => null,
            ];
            file_put_contents($usersFile, json_encode([$newUser], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            @chmod($usersFile, 0600);

            $_SESSION['vp_admin'] = true;
            $_SESSION['vp_user']  = [
                'id'       => $newUser['id'],
                'username' => $username,
                'name'     => $name,
                'role'     => 'admin',
            ];
            header('Location: dashboard.php');
            exit;
        }

    } else {
        $u = trim($_POST['username'] ?? '');
        $p = $_POST['password']     ?? '';

        $matched = null;
        foreach ($users as $user) {
            if ($user['username'] === $u && password_verify($p, $user['password_hash'] ?? '')) {
                $matched = $user;
                break;
            }
        }

        if ($matched) {
            $users = array_map(function ($user) use ($u) {
                if ($user['username'] === $u) $user['last_login'] = date('c');
                return $user;
            }, $users);
            file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $_SESSION['vp_admin'] = true;
            $_SESSION['vp_user']  = [
                'id'       => $matched['id'],
                'username' => $matched['username'],
                'name'     => $matched['name'],
                'role'     => $matched['role'],
            ];
            header('Location: dashboard.php');
            exit;
        }

        $error = 'Invalid username or password.';
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>VistecPrints Admin<?= $firstRun ? ' — Setup' : '' ?></title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{background:#0a0a0a;font-family:'DM Sans',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;}
.card{background:#111;border:1px solid rgba(212,152,72,0.2);padding:48px 40px;width:100%;max-width:420px;border-radius:4px;}
.logo{font-family:'Bebas Neue',sans-serif;font-size:28px;letter-spacing:3px;color:#D49848;text-align:center;margin-bottom:4px;}
.logo span{color:#fff;}
.subtitle{text-align:center;font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#555;margin-bottom:36px;}
.setup-banner{background:rgba(212,152,72,0.08);border:1px solid rgba(212,152,72,0.25);border-radius:3px;padding:12px 16px;font-size:12px;color:#c8a060;letter-spacing:.5px;margin-bottom:24px;line-height:1.6;}
label{display:block;font-size:12px;letter-spacing:1px;text-transform:uppercase;color:#888;margin-bottom:6px;}
.input-wrap{position:relative;margin-bottom:20px;}
input[type=text],input[type=password]{width:100%;background:#1a1a1a;border:1px solid #333;color:#f0ede8;padding:12px 16px;font-size:14px;font-family:'DM Sans',sans-serif;border-radius:2px;outline:none;}
input:focus{border-color:rgba(212,152,72,0.5);}
.eye-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:#555;cursor:pointer;padding:4px;}
.eye-toggle:hover{color:#aaa;}
.eye-toggle svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
button[type=submit]{width:100%;background:#D49848;color:#000;border:none;padding:14px;font-size:13px;font-weight:700;letter-spacing:2px;text-transform:uppercase;cursor:pointer;font-family:'DM Sans',sans-serif;border-radius:2px;transition:background 0.2s;margin-top:4px;}
button[type=submit]:hover{background:#E8B96A;}
.error{background:rgba(220,50,50,0.1);border:1px solid rgba(220,50,50,0.3);color:#ff6b6b;padding:12px 16px;border-radius:2px;font-size:13px;margin-bottom:20px;}
.hint{font-size:11px;color:#444;margin-top:-14px;margin-bottom:18px;}
</style>
</head>
<body>
<div class="card">
  <div class="logo">VISTEC<span>PRINTS</span></div>
  <div class="subtitle"><?= $firstRun ? 'First-Time Setup' : 'Admin Panel' ?></div>

  <?php if ($firstRun): ?>
  <div class="setup-banner">
    No admin account found. Create your first admin user to get started.
  </div>
  <?php endif; ?>

  <?php if ($error): ?>
  <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if ($firstRun): ?>
  <!-- ── First-run setup form ── -->
  <form method="POST">
    <label>Full Name</label>
    <div class="input-wrap">
      <input type="text" name="name" required autocomplete="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"/>
    </div>

    <label>Username</label>
    <div class="input-wrap">
      <input type="text" name="username" required autocomplete="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"/>
    </div>
    <p class="hint">Letters, numbers, underscores only.</p>

    <div class="form-row">
      <div>
        <label>Password</label>
        <div class="input-wrap">
          <input type="password" name="password" id="pw1" required autocomplete="new-password"/>
          <button type="button" class="eye-toggle" onclick="toggle('pw1',this)" title="Show/hide">
            <svg id="eye1on" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            <svg id="eye1off" viewBox="0 0 24 24" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
          </button>
        </div>
      </div>
      <div>
        <label>Confirm</label>
        <div class="input-wrap">
          <input type="password" name="confirm" id="pw2" required autocomplete="new-password"/>
          <button type="button" class="eye-toggle" onclick="toggle('pw2',this)" title="Show/hide">
            <svg id="eye2on" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            <svg id="eye2off" viewBox="0 0 24 24" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
          </button>
        </div>
      </div>
    </div>
    <p class="hint">Minimum 8 characters.</p>

    <button type="submit">Create Admin Account</button>
  </form>

  <?php else: ?>
  <!-- ── Login form ── -->
  <form method="POST">
    <label>Username</label>
    <div class="input-wrap">
      <input type="text" name="username" autocomplete="username" required/>
    </div>
    <label>Password</label>
    <div class="input-wrap">
      <input type="password" name="password" id="pw1" autocomplete="current-password" required/>
      <button type="button" class="eye-toggle" onclick="toggle('pw1',this)" title="Show/hide">
        <svg id="eye1on" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        <svg id="eye1off" viewBox="0 0 24 24" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
      </button>
    </div>
    <button type="submit">Log In</button>
  </form>
  <?php endif; ?>
</div>
<script>
function toggle(id, btn) {
  const inp = document.getElementById(id);
  const num = id.replace('pw','');
  const on  = document.getElementById('eye'+num+'on');
  const off = document.getElementById('eye'+num+'off');
  if (inp.type === 'password') {
    inp.type = 'text';
    on.style.display  = 'none';
    off.style.display = '';
  } else {
    inp.type = 'password';
    on.style.display  = '';
    off.style.display = 'none';
  }
}
</script>
</body>
</html>
