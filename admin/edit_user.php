<?php
session_start();
if (empty($_SESSION['vp_admin'])) { header('Location: index.php'); exit; }

$currentUser = $_SESSION['vp_user'] ?? [];
if (($currentUser['role'] ?? '') !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$usersFile = __DIR__ . '/users.json';
$users     = file_exists($usersFile) ? (json_decode(file_get_contents($usersFile), true) ?: []) : [];

$editId  = $_GET['id'] ?? '';
$editing = null;
if ($editId) {
    foreach ($users as $u) {
        if (($u['id'] ?? '') === $editId) { $editing = $u; break; }
    }
    if (!$editing) {
        header('Location: users.php?msg=User+not+found.&type=error');
        exit;
    }
}

$isEditingSelf = $editing && ($editing['username'] === ($currentUser['username'] ?? ''));
$pageTitle     = $editing ? 'Edit User' : 'Add User';
$flashMsg  = $_GET['msg']  ?? '';
$flashType = $_GET['type'] ?? 'success';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>VistecPrints — <?= $pageTitle ?></title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
:root{--gold:#D49848;--gold-l:#E8B96A;--black:#0a0a0a;--dark:#111;--white:#fff;--gray:#888;--red:#e05555;}
body{background:#f4f4f4;font-family:'DM Sans',sans-serif;font-size:14px;}

.sidebar{position:fixed;top:0;left:0;bottom:0;width:220px;background:var(--black);display:flex;flex-direction:column;z-index:50;}
.sb-logo{padding:24px 20px;border-bottom:1px solid #1a1a1a;font-family:'Bebas Neue',sans-serif;font-size:22px;letter-spacing:3px;color:var(--gold);}
.sb-logo span{color:var(--white);}
.sb-label{padding:20px 20px 8px;font-size:10px;letter-spacing:2px;text-transform:uppercase;color:#444;}
.sb-nav{list-style:none;padding:0 12px;}
.sb-nav li a{display:flex;align-items:center;gap:10px;padding:10px 12px;color:#888;text-decoration:none;font-size:13px;border-radius:3px;transition:all 0.15s;}
.sb-nav li a:hover,.sb-nav li a.active{background:rgba(212,152,72,0.1);color:var(--gold);}
.sb-nav li a svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0;}
.sb-footer{margin-top:auto;padding:16px 20px;border-top:1px solid #1a1a1a;}
.sb-footer a{color:#555;font-size:12px;text-decoration:none;display:flex;align-items:center;gap:8px;transition:color 0.15s;}
.sb-footer a:hover{color:var(--red);}
.sb-footer svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;}

.main{margin-left:220px;min-height:100vh;}
.topbar{background:#fff;border-bottom:1px solid #e8e8e8;padding:0 32px;height:60px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:40;}
.topbar h1{font-family:'Bebas Neue',sans-serif;font-size:22px;letter-spacing:1px;color:#111;}
.btn{display:inline-flex;align-items:center;gap:6px;padding:10px 20px;font-size:12px;font-weight:600;letter-spacing:1.5px;text-transform:uppercase;border:none;border-radius:2px;cursor:pointer;font-family:'DM Sans',sans-serif;text-decoration:none;transition:all 0.15s;}
.btn-gold{background:var(--gold);color:#000;}
.btn-gold:hover{background:var(--gold-l);}
.btn-outline{background:transparent;border:1px solid #ddd;color:#555;}
.btn-outline:hover{border-color:#aaa;color:#111;}
.content{padding:28px 32px;}

.form-card{background:#fff;border:1px solid #e8e8e8;border-radius:3px;max-width:560px;}
.form-card-head{padding:18px 24px;border-bottom:1px solid #f0f0f0;}
.form-card-head h2{font-size:15px;font-weight:600;color:#111;}
.form-card-body{padding:24px;}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.form-group{margin-bottom:18px;}
.form-group label{display:block;font-size:11px;letter-spacing:1px;text-transform:uppercase;color:#888;margin-bottom:6px;font-weight:600;}
.form-group input,.form-group select{width:100%;border:1px solid #ddd;padding:10px 12px;font-size:13px;font-family:'DM Sans',sans-serif;border-radius:2px;outline:none;color:#111;background:#fff;}
.form-group input:focus,.form-group select:focus{border-color:var(--gold);}
.form-group .hint{font-size:11px;color:#aaa;margin-top:5px;}
.input-wrap{position:relative;}
.input-wrap input{padding-right:42px;}
.eye-toggle{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:#aaa;cursor:pointer;padding:4px;}
.eye-toggle:hover{color:#555;}
.eye-toggle svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;}
.form-card-foot{padding:16px 24px;border-top:1px solid #f0f0f0;display:flex;align-items:center;justify-content:flex-end;gap:10px;}
.notice{background:#fffbf0;border:1px solid #f5d06a;border-radius:3px;padding:10px 14px;font-size:12px;color:#7a5c00;margin-bottom:18px;}
.flash{padding:12px 18px;border-radius:3px;margin-bottom:20px;font-size:13px;font-weight:500;}
.flash-error{background:#fff0f0;border:1px solid #f5a0a0;color:#a00;}
.flash-success{background:#f0faf4;border:1px solid #a3d9b5;color:#1e7e45;}
</style>
</head>
<body>

<aside class="sidebar">
  <div class="sb-logo">VISTEC<span>PRINTS</span></div>
  <div class="sb-label">Manage</div>
  <ul class="sb-nav">
    <li><a href="dashboard.php">
      <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
      Dashboard
    </a></li>
    <li><a href="users.php" class="active">
      <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
      Users
    </a></li>
  </ul>
  <div class="sb-footer">
    <a href="logout.php">
      <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Logout
    </a>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <h1><?= $pageTitle ?></h1>
    <a href="users.php" class="btn btn-outline">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
      Back to Users
    </a>
  </div>

  <div class="content">
    <div class="form-card">
      <div class="form-card-head">
        <h2><?= $editing ? 'Edit: ' . htmlspecialchars($editing['name']) : 'Create New User' ?></h2>
      </div>
      <form method="POST" action="save_user.php">
        <?php if ($editing): ?>
        <input type="hidden" name="id" value="<?= htmlspecialchars($editing['id'] ?? '') ?>"/>
        <?php endif; ?>

        <div class="form-card-body">

          <?php if ($flashMsg): ?>
          <div class="flash flash-<?= htmlspecialchars($flashType) ?>"><?= htmlspecialchars($flashMsg) ?></div>
          <?php endif; ?>

          <?php if ($editing && $isEditingSelf): ?>
          <div class="notice">You are editing your own account. You cannot change your own role.</div>
          <?php endif; ?>

          <div class="form-row">
            <div class="form-group">
              <label>Full Name *</label>
              <input type="text" name="name" required
                value="<?= htmlspecialchars($editing['name'] ?? '') ?>"
                autocomplete="name"/>
            </div>
            <div class="form-group">
              <label>Username *</label>
              <input type="text" name="username" required
                value="<?= htmlspecialchars($editing['username'] ?? '') ?>"
                autocomplete="off"/>
              <p class="hint">Letters, numbers, underscores only.</p>
            </div>
          </div>

          <div class="form-group">
            <label>Role *</label>
            <select name="role" <?= $isEditingSelf ? 'disabled' : '' ?>>
              <option value="admin" <?= ($editing['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin — full access incl. user management</option>
              <option value="staff" <?= ($editing['role'] ?? '') === 'staff' ? 'selected' : '' ?>>Staff — access to products, orders, quotes</option>
            </select>
            <?php if ($isEditingSelf): ?>
            <input type="hidden" name="role" value="<?= htmlspecialchars($editing['role'] ?? 'admin') ?>"/>
            <p class="hint">You cannot change your own role.</p>
            <?php endif; ?>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label><?= $editing ? 'New Password' : 'Password *' ?></label>
              <div class="input-wrap">
                <input type="password" name="password" id="pw1" autocomplete="new-password"
                  <?= $editing ? '' : 'required' ?>>
                <button type="button" class="eye-toggle" onclick="togglePw('pw1','e1on','e1off')" title="Show/hide">
                  <svg id="e1on" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  <svg id="e1off" viewBox="0 0 24 24" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                </button>
              </div>
              <?php if ($editing): ?>
              <p class="hint">Leave blank to keep current password.</p>
              <?php else: ?>
              <p class="hint">Minimum 8 characters.</p>
              <?php endif; ?>
            </div>
            <div class="form-group">
              <label><?= $editing ? 'Confirm New Password' : 'Confirm Password *' ?></label>
              <div class="input-wrap">
                <input type="password" name="confirm" id="pw2" autocomplete="new-password"
                  <?= $editing ? '' : 'required' ?>>
                <button type="button" class="eye-toggle" onclick="togglePw('pw2','e2on','e2off')" title="Show/hide">
                  <svg id="e2on" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  <svg id="e2off" viewBox="0 0 24 24" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                </button>
              </div>
            </div>
          </div>

        </div>

        <div class="form-card-foot">
          <a href="users.php" class="btn btn-outline">Cancel</a>
          <button type="submit" class="btn btn-gold">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            <?= $editing ? 'Save Changes' : 'Create User' ?>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function togglePw(inputId, onId, offId) {
  const inp = document.getElementById(inputId);
  const on  = document.getElementById(onId);
  const off = document.getElementById(offId);
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
