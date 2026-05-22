<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']); exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// --- Input sanitization ---
$name    = strip_tags(trim($input['name']    ?? ''));
$email   = strip_tags(trim($input['email']   ?? ''));
$phone   = strip_tags(trim($input['phone']   ?? ''));
$inquiry = strip_tags(trim($input['inquiry'] ?? ''));
$message = strip_tags(trim($input['message'] ?? ''));

if (!$name || !$email) {
    echo json_encode(['error' => 'Name and email are required.']); exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['error' => 'Please enter a valid email address.']); exit;
}
if (!$inquiry || !$message) {
    echo json_encode(['error' => 'Inquiry type and message are required.']); exit;
}

// --- Save record ---
$contactId   = 'CT-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
$contactsDir = __DIR__ . '/contacts';
if (!is_dir($contactsDir)) mkdir($contactsDir, 0755, true);

$record = [
    'id'      => $contactId,
    'date'    => date('c'),
    'status'  => 'new',
    'name'    => $name,
    'email'   => $email,
    'phone'   => $phone,
    'inquiry' => $inquiry,
    'message' => $message,
    'ip'      => $_SERVER['REMOTE_ADDR'] ?? '',
];
file_put_contents($contactsDir . '/' . $contactId . '.json',
    json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Protect directory from direct web access
$htaccess = $contactsDir . '/.htaccess';
if (!file_exists($htaccess)) file_put_contents($htaccess, "Deny from all\n");

// --- Send email notification ---
$to      = 'vistecfx@gmail.com';
$subject = "Contact Form [$contactId] — $inquiry — $name";
$body    =
    "New contact form submission from rebuild.vistecprints.com\n" .
    str_repeat('-', 40) . "\n\n" .
    "ID:       $contactId\n" .
    "Name:     $name\n" .
    "Email:    $email\n" .
    "Phone:    " . ($phone ?: '—') . "\n" .
    "Inquiry:  $inquiry\n\n" .
    "Message:\n$message\n\n" .
    str_repeat('-', 40) . "\n" .
    "Received: " . date('D, d M Y H:i:s T') . "\n";

$headers =
    "From: noreply@rebuild.vistecprints.com\r\n" .
    "Reply-To: $email\r\n" .
    "Content-Type: text/plain; charset=UTF-8\r\n";

mail($to, $subject, $body, $headers);

echo json_encode(['success' => true, 'id' => $contactId]);
