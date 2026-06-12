<?php
// Auto-deploy webhook — called by GitHub Actions on every push
$key_file = '/home3/vistecpr/.vistec_deploy_key';
if (!file_exists($key_file)) { http_response_code(500); die('Deploy key not configured'); }
$expected = trim(file_get_contents($key_file));
$provided = $_GET['token'] ?? '';
if (!$provided || !hash_equals($expected, $provided)) { http_response_code(403); die('Forbidden'); }

$repo = '/home3/vistecpr/vistecprints-git';
$dest = '/home3/vistecpr/public_html/rebuild';

exec("cd $repo && git fetch --all && git reset --hard origin/main 2>&1", $out1, $code1);
exec("/bin/cp -Rf $repo/. $dest/ 2>&1", $out2, $code2);
exec("/bin/rm -rf $dest/.git $dest/.cpanel.yml 2>&1");

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'git' => implode("\n", $out1),
    'copy' => ($code2 === 0 ? 'success' : 'failed'),
]);
