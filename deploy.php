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

exec("cd $repo && git fetch --all && git reset --hard origin/main 2>&1", $out1, $code1);

$results = [];
foreach ($dests as $dest) {
    exec("/bin/cp -Rf $repo/. $dest/ 2>&1", $outCopy, $codeCopy);
    exec("/bin/rm -rf $dest/.git $dest/.cpanel.yml 2>&1");
    $results[$dest] = ($codeCopy === 0 ? 'success' : 'failed');
}

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'git'    => implode("\n", $out1),
    'copy'   => $results,
]);
