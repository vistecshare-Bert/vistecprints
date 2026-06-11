<?php
// ============================================================
//  download-designs.php  — v2
//  Downloads design images as PNG with transparency.
//  1. Tries design.png from source (real transparency)
//  2. Falls back to design.jpg → removes white background via GD
//  3. Saves everything to design-images/{id}.png
//
//  USAGE: visit /download-designs.php?key=vistec2025
//  DELETE this file after the download completes.
// ============================================================

define('SECRET_KEY', 'vistec2025');

if (($_GET['key'] ?? '') !== SECRET_KEY) {
    http_response_code(403);
    die('<h2 style="font-family:sans-serif;color:red">Access denied. Append ?key=vistec2025 to the URL.</h2>');
}

if (!function_exists('imagecreatefromstring')) {
    die('<h2 style="font-family:sans-serif;color:red">PHP GD extension is not enabled on this server. Please enable it in cPanel → PHP Extensions.</h2>');
}

$dir = __DIR__ . '/design-images';
if (!is_dir($dir)) mkdir($dir, 0755, true);

// ── All design IDs ──────────────────────────────────────────
$designIds = [
    // Boots on the Ground
    604939926,604945586,604949531,604949786,604949971,604950201,604950361,604950496,
    604950736,604951006,604954816,604957611,604957746,604957966,604958081,608304386,
    608307586,608307846,608307951,608315661,608316616,608316681,608316811,608317016,
    608317136,608317226,608317341,608317481,608317621,608317711,608317761,608317826,
    608317961,608318096,608318266,608318356,608318446,608318531,615015311,615015546,
    615015666,615015746,624741461,624741551,625485816,625486171,625487091,625487166,
    625489876,625489956,626103671,
    // Fun Print
    605883461,605883466,605883471,605883476,605883481,605883486,605883491,
    // Women · Denim Boss Lady
    605883696,605883701,605883706,605883711,605883716,605883721,605883726,605883731,
    605883736,605883741,605883746,605883751,605883756,605883761,605883766,605883771,
    605883776,605883781,605883786,605883791,605883986,605883991,605883996,605884001,
    605884006,605884011,605884016,605884021,605884026,605884031,605884036,605884041,
    605884046,605884051,605884056,605884061,605884066,605884221,605884226,605884231,
    // Anime
    605883526,605883531,605883536,605883541,605883546,605883551,605883556,605883561,
    605883566,605883571,605883576,605883581,605883586,605883591,605883596,605883601,
    605883606,605883611,605883616,605883621,605883626,605883631,605883636,605883641,
    605883646,605883651,
    // Celebrations
    592188816,592188391,592188411,592188441,592188466,592188511,592188526,592188556,
    592188581,592188601,592188621,592188641,592188666,592188691,592188711,592188741,
    592188756,592188771,592189036,592189106,591742066,591742011,591742031,591741981,
    // Elements
    488399763,488399633,488399648,488399658,488399668,488399678,488399698,488399708,
    488399718,488399733,488399743,488399753,488399828,488593353,488593343,488593333,
    488399848,488399838,488399798,488399778,488399508,488399383,488399403,488399418,
    488399428,488399438,488399448,488399458,488399468,488399478,488399488,488399498,
    488399593,488399573,488399583,488399558,
    // Fantasy
    488398298,488399048,
    // Government
    619429616,592756116,592756081,619429881,619429861,619429831,619429801,619429776,
    619429691,619429666,619429906,619429586,619429556,619429536,619429511,619429451,
    619429426,619429411,619429371,619429351,592756286,592756151,592756136,592756096,
    592756056,619429336,592755961,592755936,592755906,592755866,592755836,592755806,
    592755771,592756391,592756621,592756586,592756566,592756521,
];

// ── White-background removal using PHP GD ───────────────────
// Flood-fill from all 4 corners to remove outer white/near-white area.
// This preserves internal white areas (e.g. white text inside a design).
function removeWhiteBg($imgData, $threshold = 230) {
    $src = @imagecreatefromstring($imgData);
    if (!$src) return false;

    $w = imagesx($src);
    $h = imagesy($src);

    // Work in RGBA
    $out = imagecreatetruecolor($w, $h);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    $fullTrans = imagecolorallocatealpha($out, 0, 0, 0, 127);
    imagefilledrectangle($out, 0, 0, $w, $h, $fullTrans);
    imagecopy($out, $src, 0, 0, 0, 0, $w, $h);
    imagedestroy($src);

    // BFS flood-fill from all 4 corners
    $visited = array_fill(0, $w * $h, false);
    $stack   = [[0, 0], [$w - 1, 0], [0, $h - 1], [$w - 1, $h - 1]];

    while (!empty($stack)) {
        [$x, $y] = array_pop($stack);
        if ($x < 0 || $x >= $w || $y < 0 || $y >= $h) continue;
        $idx = $y * $w + $x;
        if ($visited[$idx]) continue;
        $visited[$idx] = true;

        $c = imagecolorat($out, $x, $y);
        $a = ($c >> 24) & 0x7F; // alpha (127 = fully transparent in GD)
        if ($a >= 100) {
            // Already mostly transparent — keep spreading
            imagesetpixel($out, $x, $y, $fullTrans);
        } else {
            $r = ($c >> 16) & 0xFF;
            $g = ($c >> 8)  & 0xFF;
            $b =  $c        & 0xFF;
            if ($r >= $threshold && $g >= $threshold && $b >= $threshold) {
                imagesetpixel($out, $x, $y, $fullTrans);
            } else {
                continue; // Hit a non-white pixel — stop spreading this path
            }
        }

        $stack[] = [$x + 1, $y];
        $stack[] = [$x - 1, $y];
        $stack[] = [$x, $y + 1];
        $stack[] = [$x, $y - 1];
    }

    ob_start();
    imagepng($out);
    $result = ob_get_clean();
    imagedestroy($out);
    return $result;
}

function fetchUrl($url) {
    $ctx = stream_context_create([
        'http' => [
            'timeout'        => 15,
            'user_agent'     => 'Mozilla/5.0 (compatible; VistecPrints/1.0)',
            'ignore_errors'  => true,
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    return @file_get_contents($url, false, $ctx);
}

function isPng($data) {
    return $data && strlen($data) > 8 && substr($data, 0, 8) === "\x89PNG\r\n\x1a\n";
}

// ── Stream output ────────────────────────────────────────────
@ob_end_flush();
header('Content-Type: text/html; charset=utf-8');
header('X-Accel-Buffering: no');

$total   = count($designIds);
$done    = 0;
$skipped = 0;
$newPng  = 0;
$fromJpg = 0;
$failed  = 0;
$errors  = [];

echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
<title>Downloading Design Images</title>
<style>
body{font-family:monospace;background:#0a0a0a;color:#f0ede8;padding:30px;font-size:13px;line-height:1.8;}
h1{font-family:sans-serif;color:#D49848;margin-bottom:8px;}
.sub{color:#888;margin-bottom:24px;font-size:12px;}
.bar-wrap{background:#222;border-radius:4px;height:12px;width:min(500px,100%);margin:0 0 20px;}
.bar{background:#D49848;height:100%;border-radius:4px;width:0%;transition:width 0.3s;}
.ok{color:#6fcf97;} .png{color:#56ccf2;} .skip{color:#888;} .fail{color:#eb5757;}
.summary{margin-top:28px;padding:20px 24px;background:#1a1a1a;border:1px solid rgba(212,152,72,0.3);border-radius:4px;max-width:600px;}
a{color:#D49848;}
</style></head><body>
<h1>Design Image Downloader</h1>
<p class="sub">Downloading ' . $total . ' images as transparent PNG to <strong>design-images/</strong></p>
<div class="bar-wrap"><div class="bar" id="bar"></div></div>
<div id="log">';
flush();

foreach ($designIds as $id) {
    $dest = $dir . '/' . $id . '.png';

    // Skip if already downloaded and valid
    if (file_exists($dest) && filesize($dest) > 500) {
        echo '<span class="skip">SKIP  ' . $id . '</span><br>';
        $skipped++;
    } else {
        // Try PNG from source first
        $pngUrl  = 'https://www.vistecprints.com/aimages/' . $id . '/1/0/design.png';
        $pngData = fetchUrl($pngUrl);

        if (isPng($pngData) && strlen($pngData) > 1000) {
            // Source serves real PNG — save directly
            file_put_contents($dest, $pngData);
            echo '<span class="png">PNG   ' . $id . ' (' . round(strlen($pngData)/1024,1) . ' KB — transparent from source)</span><br>';
            $newPng++;
        } else {
            // Fall back to JPG, then remove white background
            $jpgUrl  = 'https://www.vistecprints.com/aimages/' . $id . '/1/0/design.jpg';
            $jpgData = fetchUrl($jpgUrl);

            if ($jpgData && strlen($jpgData) > 1000) {
                $pngOut = removeWhiteBg($jpgData);
                if ($pngOut) {
                    file_put_contents($dest, $pngOut);
                    echo '<span class="ok">JPG→PNG ' . $id . ' (' . round(strlen($pngOut)/1024,1) . ' KB — white bg removed)</span><br>';
                    $fromJpg++;
                } else {
                    // GD failed — save raw JPG as fallback
                    file_put_contents($dir . '/' . $id . '.jpg', $jpgData);
                    echo '<span class="fail">GD FAIL ' . $id . ' (saved as .jpg fallback)</span><br>';
                    $failed++;
                    $errors[] = $id;
                }
            } else {
                echo '<span class="fail">FAIL  ' . $id . '</span><br>';
                $failed++;
                $errors[] = $id;
            }
        }
    }

    $done++;
    $pct = round($done / $total * 100);
    echo '<script>document.getElementById("bar").style.width="' . $pct . '%"</script>';
    flush();
    usleep(80000);
}

echo '</div><div class="summary">';
echo '<p style="color:#D49848;font-size:15px;font-weight:bold;margin-bottom:12px;">✓ Done!</p>';
echo '<p class="png">PNG from source: ' . $newPng . '</p>';
echo '<p class="ok">JPG → transparent PNG: ' . $fromJpg . '</p>';
echo '<p class="skip">Skipped (already existed): ' . $skipped . '</p>';
if ($failed > 0) {
    echo '<p class="fail">Failed: ' . $failed . ' (' . implode(', ', $errors) . ')</p>';
}
echo '<br><p style="color:#666;font-size:12px;">You can now delete <strong>download-designs.php</strong> from your server.</p>';
echo '<p style="margin-top:12px"><a href="designs.html">→ View Design Library</a></p>';
echo '</div></body></html>';
