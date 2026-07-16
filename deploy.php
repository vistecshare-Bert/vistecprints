<?php
// Auto-deploy webhook — called by GitHub Actions on every push
$key_file = '/home3/vistecpr/.vistec_deploy_key';
if (!file_exists($key_file)) { http_response_code(500); die('Deploy key not configured'); }
$expected = trim(file_get_contents($key_file));
$provided = $_GET['token'] ?? '';
if (!$provided || !hash_equals($expected, $provided)) { http_response_code(403); die('Forbidden'); }

$dest = '/home3/vistecpr/public_html/vistecprints.com';
$tmp  = sys_get_temp_dir();
$zip  = "$tmp/vistec_deploy.zip";
$dir  = "$tmp/vistec_extract";

$ghToken = '';
$ghTokenFile = '/home3/vistecpr/.github_token';
if (file_exists($ghTokenFile)) {
    $ghToken = trim(file_get_contents($ghTokenFile));
}

// Use exact commit SHA from GitHub Actions if provided, otherwise fall back to main
$sha    = preg_replace('/[^a-f0-9]/i', '', $_GET['sha'] ?? '');
$ref    = $sha ?: 'main';
$zipUrl = "https://api.github.com/repos/vistecshare-Bert/vistecprints/zipball/$ref";
$ctx = stream_context_create(['http' => [
    'method'          => 'GET',
    'header'          => "Authorization: token $ghToken\r\nUser-Agent: VistecDeploy/1.0\r\n",
    'follow_location' => 1,
    'timeout'         => 60,
]]);
$zipContent = file_get_contents($zipUrl, false, $ctx);
if (!$zipContent || strlen($zipContent) < 1000) {
    http_response_code(500);
    die(json_encode(['status' => 'error', 'msg' => 'ZIP download failed', 'size' => strlen($zipContent ?: '')]));
}
file_put_contents($zip, $zipContent);

// Extract ZIP
exec("rm -rf " . escapeshellarg($dir) . " && mkdir -p " . escapeshellarg($dir));
exec("unzip -q " . escapeshellarg($zip) . " -d " . escapeshellarg($dir) . " 2>&1", $unzipOut, $unzipCode);
if ($unzipCode !== 0) {
    http_response_code(500);
    die(json_encode(['status' => 'error', 'msg' => 'Unzip failed', 'detail' => implode("\n", $unzipOut)]));
}

// GitHub ZIP extracts into a single subdir named "owner-repo-HASH/"
$subdirs = glob($dir . '/*/');
if (empty($subdirs)) {
    http_response_code(500);
    die(json_encode(['status' => 'error', 'msg' => 'No extracted directory found']));
}
$src = rtrim($subdirs[0], '/') . '/';
$ref = basename(rtrim($subdirs[0], '/'));

// Files that live only on the server — never overwrite from GitHub
$excludes = [
    'products.json', 'orders.json', 'admin/users.json',
    'stripe-config.php', 'admin-config.php', 'carolina-config.php',
    'carolina-products.json', 'carolina-sync-meta.json', 'carolina-ids.json',
    'pending_orders/', 'orders/', 'quotes/', 'contacts/', 'visits/',
    'images/decorated/', 'images/designs/',
];
$excludeFlags = implode(' ', array_map(fn($e) => '--exclude=' . escapeshellarg($e), $excludes));
$cmd = "/usr/bin/rsync -a --delete --chmod=D755,F644 $excludeFlags " . escapeshellarg($src) . ' ' . escapeshellarg($dest . '/') . ' 2>&1';
exec($cmd, $syncOut, $syncCode);
exec('chmod 755 ' . escapeshellarg($dest));

// Cleanup
exec("rm -f " . escapeshellarg($zip));
exec("rm -rf " . escapeshellarg($dir));

http_response_code(200);
echo json_encode([
    'status' => $syncCode === 0 ? 'ok' : 'sync_error',
    'ref'    => $ref,
    'size'   => strlen($zipContent),
    'sync'   => $syncCode === 0 ? 'success' : implode("\n", $syncOut),
]);
