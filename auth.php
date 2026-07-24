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

$action = $_GET['action'] ?? '';

// ── Google Sign-In ────────────────────────────────────────
if ($action === 'google') {
    $credential = $_POST['credential'] ?? '';
    if (!$credential) { echo json_encode(['success' => false, 'error' => 'No credential provided']); exit; }

    $ctx = stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true]]);
    $res = @file_get_contents('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential), false, $ctx);
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
    foreach ($users as $i => $u) {
        if (($u['googleId'] ?? '') === $googleId) { $userIdx = $i; break; }
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
        $users[$userIdx]['name']    = $name;
        $users[$userIdx]['picture'] = $picture;
        $user = $users[$userIdx];
    }
    saveUsers($usersFile, $users);

    $_SESSION['vp_user'] = $user;
    echo json_encode(['success' => true, 'user' => $user]);
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
