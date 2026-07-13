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

$usersFile = __DIR__ . '/users.json';
$users     = file_exists($usersFile) ? (json_decode(file_get_contents($usersFile), true) ?: []) : [];

$editId   = trim($_POST['id']       ?? '');
$name     = trim($_POST['name']     ?? '');
$username = trim($_POST['username'] ?? '');
$role     = trim($_POST['role']     ?? 'staff');
$password = $_POST['password']      ?? '';
$confirm  = $_POST['confirm']       ?? '';

$isEditing    = !empty($editId);
$isEditingSelf = $isEditing && ($currentUser['id'] ?? '') === $editId;

// ── Validate role value ──
if (!in_array($role, ['admin', 'staff'], true)) $role = 'staff';

// ── Prevent self-role change ──
if ($isEditingSelf) {
    $role = $currentUser['role'] ?? 'admin';
}

// ── Required fields ──
if (!$name || !$username) {
    redirect('Name and username are required.', 'error');
}

// ── Username format ──
if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    redirect('Username may only contain letters, numbers, and underscores.', 'error');
}

// ── Username uniqueness ──
foreach ($users as $u) {
    if ($u['username'] === $username && ($u['id'] ?? '') !== $editId) {
        redirect('Username "' . htmlspecialchars($username) . '" is already taken.', 'error');
    }
}

// ── Password validation ──
$newHash = null;
if ($password !== '') {
    if (strlen($password) < 8) {
        redirect('Password must be at least 8 characters.', 'error');
    }
    if ($password !== $confirm) {
        redirect('Passwords do not match.', 'error');
    }
    $newHash = password_hash($password, PASSWORD_BCRYPT);
} elseif (!$isEditing) {
    redirect('Password is required for new users.', 'error');
}

// ── Last-admin guard: cannot demote the only admin ──
if ($isEditing && $role !== 'admin') {
    $adminCount = 0;
    foreach ($users as $u) {
        if (($u['role'] ?? '') === 'admin' && ($u['id'] ?? '') !== $editId) $adminCount++;
    }
    if ($adminCount === 0) {
        redirect('Cannot change role — at least one admin must remain.', 'error');
    }
}

if ($isEditing) {
    $found = false;
    foreach ($users as &$u) {
        if (($u['id'] ?? '') === $editId) {
            $u['name']     = $name;
            $u['username'] = $username;
            $u['role']     = $role;
            if ($newHash !== null) $u['password_hash'] = $newHash;
            $found = true;

            // Keep session in sync if editing self
            if ($isEditingSelf) {
                $_SESSION['vp_user']['name']     = $name;
                $_SESSION['vp_user']['username'] = $username;
            }
            break;
        }
    }
    unset($u);
    if (!$found) redirect('User not found.', 'error');
    $msg = 'User "' . $name . '" updated successfully.';
} else {
    $newUser = [
        'id'            => uniqid('u_'),
        'name'          => $name,
        'username'      => $username,
        'password_hash' => $newHash,
        'role'          => $role,
        'created'       => date('c'),
        'last_login'    => null,
    ];
    $users[] = $newUser;
    $msg = 'User "' . $name . '" created successfully.';
}

file_put_contents($usersFile, json_encode(array_values($users), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
redirect($msg, 'success');

function redirect($msg, $type) {
    $editId = trim($_POST['id'] ?? '');
    if ($type === 'error') {
        $back = $editId ? 'edit_user.php?id=' . urlencode($editId) : 'edit_user.php';
        header('Location: ' . $back . '?msg=' . urlencode($msg) . '&type=error');
    } else {
        header('Location: users.php?msg=' . urlencode($msg) . '&type=' . urlencode($type));
    }
    exit;
}
