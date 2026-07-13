<?php
session_start();
if (empty($_SESSION['vp_admin'])) { header('Location: index.php'); exit; }

$currentUser = $_SESSION['vp_user'] ?? [];
if (($currentUser['role'] ?? '') !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: users.php');
    exit;
}

$deleteId  = trim($_POST['id'] ?? '');
$usersFile = __DIR__ . '/users.json';
$users     = file_exists($usersFile) ? (json_decode(file_get_contents($usersFile), true) ?: []) : [];

if (!$deleteId) {
    header('Location: users.php?msg=Invalid+request.&type=error');
    exit;
}

// Find the target user
$target = null;
foreach ($users as $u) {
    if (($u['id'] ?? '') === $deleteId) { $target = $u; break; }
}
if (!$target) {
    header('Location: users.php?msg=User+not+found.&type=error');
    exit;
}

// Block deleting yourself
if (($currentUser['id'] ?? '') === $deleteId) {
    header('Location: users.php?msg=' . urlencode('You cannot delete your own account.') . '&type=error');
    exit;
}

// Block deleting the last admin
if (($target['role'] ?? '') === 'admin') {
    $adminCount = 0;
    foreach ($users as $u) {
        if (($u['role'] ?? '') === 'admin') $adminCount++;
    }
    if ($adminCount <= 1) {
        header('Location: users.php?msg=' . urlencode('Cannot delete the last admin account. Promote another user to admin first.') . '&type=error');
        exit;
    }
}

$users = array_values(array_filter($users, fn($u) => ($u['id'] ?? '') !== $deleteId));
file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$name = htmlspecialchars($target['name'] ?? $target['username'] ?? 'User');
header('Location: users.php?msg=' . urlencode($name . ' has been deleted.') . '&type=warning');
exit;
