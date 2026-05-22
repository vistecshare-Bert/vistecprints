<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']); exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// --- reCAPTCHA verification ---
$cfgFile = __DIR__ . '/admin-config.php';
if (file_exists($cfgFile)) {
    require_once $cfgFile;
    $token = trim($input['recaptchaToken'] ?? '');
    if (empty($token)) {
        echo json_encode(['error' => 'Please complete the reCAPTCHA verification.']); exit;
    }
    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'secret'   => RECAPTCHA_SECRET,
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]),
        CURLOPT_TIMEOUT => 10,
    ]);
    $verify = json_decode(curl_exec($ch), true);
    curl_close($ch);
    if (!($verify['success'] ?? false)) {
        echo json_encode(['error' => 'reCAPTCHA failed. Please try again.']); exit;
    }
}

// --- Input sanitization ---
$name    = strip_tags(trim($input['name']    ?? ''));
$email   = strip_tags(trim($input['email']   ?? ''));
$phone   = strip_tags(trim($input['phone']   ?? ''));
$product = strip_tags(trim($input['product'] ?? ''));
$qty     = strip_tags(trim($input['qty']     ?? ''));
$budget  = strip_tags(trim($input['budget']  ?? ''));
$notes   = strip_tags(trim($input['notes']   ?? ''));

// Extended fields from the Quick Quote calculator
$garmentType    = strip_tags(trim($input['garment_type']    ?? ''));
$printLocation  = strip_tags(trim($input['print_location']  ?? ''));
$printMethod    = strip_tags(trim($input['print_method']    ?? ''));
$estimatedPrice = strip_tags(trim($input['estimated_price'] ?? ''));
$estimatedUnit  = strip_tags(trim($input['estimated_unit']  ?? ''));
$discountPct    = (int)($input['discount_pct'] ?? 0);
$artworkStatus  = strip_tags(trim($input['artwork_status']  ?? ''));

if (!$name || !$email) {
    echo json_encode(['error' => 'Name and email are required.']); exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['error' => 'Please enter a valid email address.']); exit;
}

// --- Save record ---
$quoteId   = 'QR-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
$quotesDir = __DIR__ . '/quotes';
if (!is_dir($quotesDir)) mkdir($quotesDir, 0755, true);

$record = [
    'id'             => $quoteId,
    'date'           => date('c'),
    'status'         => 'new',
    'name'           => $name,
    'email'          => $email,
    'phone'          => $phone,
    'product'        => $product,
    'qty'            => $qty,
    'budget'         => $budget,
    'notes'          => $notes,
    'garment_type'   => $garmentType,
    'print_location' => $printLocation,
    'print_method'   => $printMethod,
    'estimated_price'=> $estimatedPrice,
    'estimated_unit' => $estimatedUnit,
    'discount_pct'   => $discountPct,
    'artwork_status' => $artworkStatus,
    'ip'             => $_SERVER['REMOTE_ADDR'] ?? '',
];
file_put_contents($quotesDir . '/' . $quoteId . '.json',
    json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Protect the directory from direct web access
$htaccess = $quotesDir . '/.htaccess';
if (!file_exists($htaccess)) file_put_contents($htaccess, "Deny from all\n");

// --- Send email ---
$to      = 'vistecfx@gmail.com';
$subject = "Quote Request [$quoteId] from $name";
$printDetails = $garmentType
    ? ucwords(str_replace('_',' ',$garmentType)) . ' · ' . ucwords(str_replace('_',' ',$printLocation)) . ' · ' . ucwords(str_replace('_',' ',$printMethod))
    : ($product ?: '—');

$message =
    "New quote request from rebuild.vistecprints.com\n" .
    str_repeat('-', 40) . "\n\n" .
    "ID:       $quoteId\n" .
    "Name:     $name\n" .
    "Email:    $email\n" .
    "Phone:    " . ($phone   ?: '—') . "\n\n" .
    "Garment:  $printDetails\n" .
    "Qty:      " . ($qty     ?: '—') . "\n" .
    ($estimatedPrice ? "Estimate: \$$estimatedPrice total / \$$estimatedUnit ea" . ($discountPct ? " (−{$discountPct}% vol. discount)" : "") . "\n" : '') .
    "Artwork:  " . ($artworkStatus ?: '—') . "\n\n" .
    "Notes:\n" . ($notes ?: '—') . "\n\n" .
    str_repeat('-', 40) . "\n" .
    "View in admin: https://rebuild.vistecprints.com/admin.php\n";

$headers =
    "From: noreply@rebuild.vistecprints.com\r\n" .
    "Reply-To: $email\r\n" .
    "Content-Type: text/plain; charset=UTF-8\r\n";

mail($to, $subject, $message, $headers);

echo json_encode(['success' => true, 'id' => $quoteId]);
