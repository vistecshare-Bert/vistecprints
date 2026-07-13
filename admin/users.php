<?php
session_start();
if (empty($_SESSION['vp_admin'])) { header('Location: index.php'); exit; }

$currentUser = $_SESSION['vp_user'] ?? [];
$vpRole = $currentUser['role'] ?? '';
if ($vpRole !== 'admin' && $vpRole !== '') {
    header('Location: dashboard.php');
    exit;
}

$usersFile = __DIR__ . '/users.json';
$users     = file_exists($usersFile) ? (json_decode(file_get_contents($usersFile), true) ?: []) : [];

$flashMsg  = $_GET['msg']  ?? '';
$flashType = $_GET['type'] ?? 'success';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>VistecPrints — User Management</title>
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
.btn-red{background:transparent;border:1px solid rgba(224,85,85,0.3);color:var(--red);}
.btn-red:hover{background:rgba(224,85,85,0.08);}
.btn-sm{padding:6px 14px;font-size:11px;}
.content{padding:28px 32px;}

.flash{padding:12px 18px;border-radius:3px;margin-bottom:24px;font-size:13px;font-weight:500;}
.flash-success{background:#f0faf4;border:1px solid #a3d9b5;color:#1e7e45;}
.flash-warning{background:#fff8f0;border:1px solid #f5c88a;color:#9a5c00;}
.flash-error{background:#fff0f0;border:1px solid #f5a0a0;color:#a00;}

.table-wrap{background:#fff;border:1px solid #e8e8e8;border-radius:3px;overflow:hidden;}
.table-header{padding:16px 24px;border-bottom:1px solid #f0f0f0;display:flex;align-items:center;justify-content:space-between;}
.table-header h2{font-size:15px;font-weight:600;color:#111;}
table{width:100%;border-collapse:collapse;}
thead tr{background:#fafafa;border-bottom:2px solid #f0f0f0;}
th{padding:12px 16px;text-align:left;font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:#888;font-weight:600;}
td{padding:13px 16px;border-bottom:1px solid #f5f5f5;vertical-align:middle;}
tr:last-child td{border-bottom:none;}
tr:hover td{background:#fafafa;}
.user-name{font-weight:500;color:#111;}
.user-sub{font-size:11px;color:#aaa;margin-top:2px;}
.role-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;letter-spacing:.5px;}
.role-admin{background:#fff3e0;color:#e65c00;border:1px solid #f5c88a;}
.role-staff{background:#e8f0ff;color:#1a56cc;border:1px solid #b0c8f8;}
.you-badge{display:inline-block;padding:2px 7px;background:#f0f0f0;color:#888;font-size:10px;border-radius:10px;margin-left:6px;letter-spacing:.5px;}
.actions{display:flex;gap:6px;align-items:center;}
.empty-state{text-align:center;padding:60px 20px;color:#bbb;}
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
    <h1>User Management</h1>
    <a href="edit_user.php" class="btn btn-gold">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Add User
    </a>
  </div>

  <div class="content">
    <?php if ($flashMsg): ?>
    <div class="flash flash-<?= htmlspecialchars($flashType) ?>"><?= htmlspecialchars($flashMsg) ?></div>
    <?php endif; ?>

    <div class="table-wrap">
      <div class="table-header">
        <h2>All Users (<?= count($users) ?>)</h2>
      </div>
      <?php if (empty($users)): ?>
      <div class="empty-state"><p>No users found.</p></div>
      <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Name</th>
            <th>Username</th>
            <th>Role</th>
            <th>Created</th>
            <th>Last Login</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u):
            $isMe = ($u['username'] === ($currentUser['username'] ?? ''));
          ?>
          <tr>
            <td>
              <div class="user-name">
                <?= htmlspecialchars($u['name']) ?>
                <?php if ($isMe): ?><span class="you-badge">You</span><?php endif; ?>
              </div>
              <div class="user-sub"><?= htmlspecialchars($u['id'] ?? '') ?></div>
            </td>
            <td><?= htmlspecialchars($u['username']) ?></td>
            <td><span class="role-badge role-<?= htmlspecialchars($u['role'] ?? 'staff') ?>"><?= ucfirst($u['role'] ?? 'staff') ?></span></td>
            <td style="color:#888;font-size:12px;white-space:nowrap;"><?= $u['created'] ? date('M j, Y', strtotime($u['created'])) : '—' ?></td>
            <td style="color:#888;font-size:12px;white-space:nowrap;"><?= $u['last_login'] ? date('M j, Y g:i A', strtotime($u['last_login'])) : 'Never' ?></td>
            <td>
              <div class="actions">
                <a href="edit_user.php?id=<?= urlencode($u['id'] ?? '') ?>" class="btn btn-outline btn-sm">Edit</a>
                <?php if (!$isMe): ?>
                <form method="POST" action="delete_user.php" onsubmit="return confirm('Delete user &quot;<?= htmlspecialchars(addslashes($u['username'])) ?>&quot;? This cannot be undone.')">
                  <input type="hidden" name="id" value="<?= htmlspecialchars($u['id'] ?? '') ?>"/>
                  <button type="submit" class="btn btn-red btn-sm">Delete</button>
                </form>
                <?php else: ?>
                <span style="font-size:11px;color:#ccc;padding:6px 8px;">Can't delete self</span>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
