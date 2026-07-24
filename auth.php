<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$CLIENT_ID = '896451806994-mbc9bqqlhca9mgs7m0vuhknlcrruoud6.apps.googleusercontent.com';
$usersFile = __DIR__ . '/users.json';

function loadUsers($file) {
    if (!file_exists($file)) return [];
    return json_decode(file_get_contents($file), true) ?: [];
}
function saveUsers($file, $users) {
    file_put_contents($file, json_encode(array_values($users), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
function sessionUser($user) {
    // Never put passwordHash in the session
    $u = $user;
    unset($u['passwordHash']);
    return $u;
}

$action = $_GET['action'] ?? '';

// ── Email / Password Login ────────────────────────────────
if ($action === 'login') {
    $email    = trim($_POST['email']    ?? '');
    $password =      $_POST['password'] ?? '';

    if (!$email || !$password) {
        echo json_encode(['success' => false, 'error' => 'Email and password are required.']); exit;
    }

    $users = loadUsers($usersFile);
    $found = null;
    foreach ($users as $u) {
        if (strtolower($u['email'] ?? '') === strtolower($email)) { $found = $u; break; }
    }

    if (!$found) {
        echo json_encode(['success' => false, 'error' => 'No account found with that email.']); exit;
    }
    if (empty($found['passwordHash'])) {
        echo json_encode(['success' => false, 'error' => 'This account uses Google sign-in. Use the Google button below.']); exit;
    }
    if (!password_verify($password, $found['passwordHash'])) {
        echo json_encode(['success' => false, 'error' => 'Incorrect password.']); exit;
    }

    $su = sessionUser($found);
    $_SESSION['vp_user'] = $su;
    echo json_encode(['success' => true, 'user' => $su]);
    exit;
}

// ── Email / Password Register ─────────────────────────────
if ($action === 'register') {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password =      $_POST['password'] ?? '';

    if (!$name || !$email || !$password) {
        echo json_encode(['success' => false, 'error' => 'All fields are required.']); exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Please enter a valid email address.']); exit;
    }
    if (strlen($password) < 8) {
        echo json_encode(['success' => false, 'error' => 'Password must be at least 8 characters.']); exit;
    }

    $users = loadUsers($usersFile);
    foreach ($users as $u) {
        if (strtolower($u['email'] ?? '') === strtolower($email)) {
            echo json_encode(['success' => false, 'error' => 'An account with that email already exists.']); exit;
        }
    }

    $user = [
        'id'           => 'USR-' . strtoupper(substr(md5($email . time()), 0, 8)),
        'email'        => $email,
        'name'         => $name,
        'picture'      => '',
        'passwordHash' => password_hash($password, PASSWORD_DEFAULT),
        'createdAt'    => date('c'),
    ];
    array_unshift($users, $user);
    saveUsers($usersFile, $users);

    $su = sessionUser($user);
    $_SESSION['vp_user'] = $su;
    echo json_encode(['success' => true, 'user' => $su]);
    exit;
}

// ── Google Sign-In ────────────────────────────────────────
if ($action === 'google') {
    $credential = $_POST['credential'] ?? '';
    if (!$credential) { echo json_encode(['success' => false, 'error' => 'No credential provided']); exit; }

    $ctx  = stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true]]);
    $res  = @file_get_contents('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential), false, $ctx);
    $data = json_decode($res, true);

    if (!$data || !empty($data['error_description']) || !empty($data['error'])) {
        echo json_encode(['success' => false, 'error' => 'Token verification failed']); exit;
    }
    if (($data['aud'] ?? '') !== $CLIENT_ID) {
        echo json_encode(['success' => false, 'error' => 'Invalid token audience']); exit;
    }

    $googleId = $data['sub']     ?? '';
    $email    = $data['email']   ?? '';
    $name     = $data['name']    ?? '';
    $picture  = $data['picture'] ?? '';

    $users   = loadUsers($usersFile);
    $userIdx = null;

    // Match by googleId first, then by email (links Google to an existing email account)
    foreach ($users as $i => $u) {
        if (($u['googleId'] ?? '') === $googleId) { $userIdx = $i; break; }
    }
    if ($userIdx === null) {
        foreach ($users as $i => $u) {
            if (strtolower($u['email'] ?? '') === strtolower($email)) { $userIdx = $i; break; }
        }
    }

    if ($userIdx === null) {
        $user = [
            'id'        => 'USR-' . strtoupper(substr(md5($googleId . time()), 0, 8)),
            'googleId'  => $googleId,
            'email'     => $email,
            'name'      => $name,
            'picture'   => $picture,
            'createdAt' => date('c'),
        ];
        array_unshift($users, $user);
    } else {
        $users[$userIdx]['googleId'] = $googleId;
        $users[$userIdx]['name']     = $name;
        $users[$userIdx]['picture']  = $picture;
        $user = $users[$userIdx];
    }
    saveUsers($usersFile, $users);

    $su = sessionUser($user);
    $_SESSION['vp_user'] = $su;
    echo json_encode(['success' => true, 'user' => $su]);
    exit;
}

// ── Logout ────────────────────────────────────────────────
if ($action === 'logout') {
    unset($_SESSION['vp_user']);
    echo json_encode(['success' => true]);
    exit;
}

// ── Session status ────────────────────────────────────────
if ($action === 'status') {
    if (!empty($_SESSION['vp_user'])) {
        $ordersFile = __DIR__ . '/orders.json';
        $allOrders  = file_exists($ordersFile) ? (json_decode(file_get_contents($ordersFile), true) ?: []) : [];
        $email      = $_SESSION['vp_user']['email'] ?? '';
        $count      = count(array_filter($allOrders, fn($o) => ($o['customer']['email'] ?? '') === $email));
        echo json_encode(['loggedIn' => true, 'user' => $_SESSION['vp_user'], 'orderCount' => $count]);
    } else {
        echo json_encode(['loggedIn' => false]);
    }
    exit;
}

// ── Orders for logged-in user ─────────────────────────────
if ($action === 'orders') {
    if (empty($_SESSION['vp_user'])) { echo json_encode(['success' => false, 'error' => 'Not logged in']); exit; }
    $ordersFile = __DIR__ . '/orders.json';
    $allOrders  = file_exists($ordersFile) ? (json_decode(file_get_contents($ordersFile), true) ?: []) : [];
    $email      = $_SESSION['vp_user']['email'];
    $myOrders   = array_values(array_filter($allOrders, fn($o) => ($o['customer']['email'] ?? '') === $email));
    echo json_encode(['success' => true, 'orders' => $myOrders]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);
