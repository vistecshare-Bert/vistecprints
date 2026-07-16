<?php
// Auto-deploy webhook — called by GitHub Actions on every push
$key_file = '/home3/vistecpr/.vistec_deploy_key';
if (!file_exists($key_file)) { http_response_code(500); die('Deploy key not configured'); }
$expected = trim(file_get_contents($key_file));
$provided = $_GET['token'] ?? '';
if (!$provided || !hash_equals($expected, $provided)) { http_response_code(403); die('Forbidden'); }

$repo = '/home3/vistecpr/vistecprints-git';
$dests = [
    '/home3/vistecpr/public_html/vistecprints.com',  // live vistecprints.com
];

// Use stored GitHub token so fetch works even if the cloned remote URL expires
$ghTokenFile = '/home3/vistecpr/.github_token';
$fetchUrl = 'https://github.com/vistecshare-Bert/vistecprints.git';
if (file_exists($ghTokenFile)) {
    $ghToken = trim(file_get_contents($ghTokenFile));
    $fetchUrl = "https://$ghToken@github.com/vistecshare-Bert/vistecprints.git";
}
exec("cd $repo && git remote set-url origin " . escapeshellarg($fetchUrl) . " 2>&1");
exec("cd $repo && git fetch origin 2>&1", $outFetch, $codeFetch);
exec("cd $repo && git reset --hard origin/main 2>&1", $outReset, $codeReset);
$out1 = array_merge($outFetch, ['fetch_exit:'.$codeFetch], $outReset, ['reset_exit:'.$codeReset]);

// Files/dirs that live only on the server — never overwrite from git
$excludes = [
    '.git',
    '.cpanel.yml',
    'products.json',          // admin-managed product catalog
    'orders.json',            // stripe orders
    'admin/users.json',       // admin user accounts
    'stripe-config.php',      // secrets
    'admin-config.php',       // secrets
    'carolina-config.php',    // secrets
    'carolina-products.json', // synced supplier data
    'carolina-sync-meta.json',
    'carolina-ids.json',
    'pending_orders/',
    'orders/',                // DTF orders + artwork uploads
    'quotes/',
    'contacts/',
    'visits/',
    'images/decorated/',      // uploaded decorated product images
    'images/designs/',        // uploaded design files
];

$excludeFlags = implode(' ', array_map(fn($e) => '--exclude=' . escapeshellarg($e), $excludes));

$results = [];
foreach ($dests as $dest) {
    // rsync: only update code files, preserve server-only data
    $cmd = "/usr/bin/rsync -a --delete --chmod=D755,F644 $excludeFlags " . escapeshellarg($repo . '/') . ' ' . escapeshellarg($dest . '/') . ' 2>&1';
    exec($cmd, $outSync, $codeSync);
    // Ensure destination dir stays world-readable regardless of git clone perms
    exec('chmod 755 ' . escapeshellarg($dest));
    $results[$dest] = ($codeSync === 0 ? 'success' : 'failed: ' . implode(' ', $outSync));
}

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'git'    => implode("\n", $out1),
    'copy'   => $results,
]);
