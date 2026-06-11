<?php
session_start();
if (empty($_SESSION['vp_admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Not authorized']);
    exit;
}
header('Content-Type: application/json');

require_once __DIR__ . '/carolina-api.php';

$configFile   = __DIR__ . '/carolina-config.php';
$catalogFile  = __DIR__ . '/carolina-products.json';
$metaFile     = __DIR__ . '/carolina-sync-meta.json';
$productsFile = __DIR__ . '/products.json';

function loadConfig($file) {
    if (!file_exists($file)) return null;
    $c = @include $file;
    return is_array($c) ? $c : null;
}
function loadJSON($file) {
    if (!file_exists($file)) return [];
    $d = json_decode(file_get_contents($file), true);
    return is_array($d) ? $d : [];
}
function saveJSON($file, $data) {
    file_put_contents($file, json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ── WSDL debug — shows exactly what the server returns ───────────────────
if ($action === 'wsdl_debug') {
    $url = trim($_GET['url'] ?? 'https://promostandards.carolinamade.com/cgi-bin/ws/productData.php?wsdl');
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 PHP-SOAP/PromoStandards',
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HEADER         => true,
    ]);
    $raw      = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $curlErr  = curl_error($ch);
    curl_close($ch);
    echo json_encode([
        'url'       => $url,
        'final_url' => $finalUrl,
        'http_code' => $httpCode,
        'curl_error'=> $curlErr,
        'preview'   => substr($raw, 0, 1000),
        'is_wsdl'   => (strpos($raw, 'wsdl') !== false || strpos($raw, 'definitions') !== false),
    ]);
    exit;
}

// ── Save credentials ──────────────────────────────────────────────────────
if ($action === 'save_config') {
    $u = trim($_POST['username'] ?? '');
    $p = trim($_POST['password'] ?? '');
    if (!$u || !$p) { echo json_encode(['error' => 'Username and password required']); exit; }
    $content = "<?php\nreturn [\n    'username' => " . var_export($u, true) . ",\n    'password' => " . var_export($p, true) . ",\n];\n";
    file_put_contents($configFile, $content);
    echo json_encode(['ok' => true, 'message' => 'Credentials saved']); exit;
}

// ── Test connection ───────────────────────────────────────────────────────
if ($action === 'test') {
    $cfg = loadConfig($configFile);
    if (!$cfg) { echo json_encode(['ok' => false, 'error' => 'No credentials saved yet']); exit; }
    $api = new CarolinaMadeAPI($cfg['username'], $cfg['password']);
    echo json_encode($api->testConnection()); exit;
}

// ── Get meta (last sync info) ─────────────────────────────────────────────
if ($action === 'meta') {
    $meta = loadJSON($metaFile);
    $catalog = loadJSON($catalogFile);
    $meta['product_count'] = count($catalog);
    $meta['has_config']    = file_exists($configFile);
    echo json_encode($meta); exit;
}

// ── Get product ID list only (fast — no full sync needed) ────────────────
if ($action === 'ids') {
    $cfg = loadConfig($configFile);
    if (!$cfg) { echo json_encode(['error' => 'No credentials saved yet']); exit; }

    $idsFile = __DIR__ . '/carolina-ids.json';
    // Cache IDs for 1 hour so repeated page loads don't re-call the API
    if (!file_exists($idsFile) || (time() - filemtime($idsFile)) > 3600) {
        $api = new CarolinaMadeAPI($cfg['username'], $cfg['password']);
        $ids = $api->getProductIds();
        file_put_contents($idsFile, json_encode($ids));
    } else {
        $ids = json_decode(file_get_contents($idsFile), true) ?: [];
    }

    $q = strtolower(trim($_GET['q'] ?? ''));
    if ($q) {
        $ids = array_values(array_filter($ids, fn($id) => stripos($id, $q) !== false));
    }
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 50;
    $total   = count($ids);
    $slice   = array_slice($ids, ($page - 1) * $perPage, $perPage);
    echo json_encode(['ids' => $slice, 'total' => $total, 'page' => $page, 'pages' => (int)ceil($total / $perPage)]); exit;
}

// ── Fetch one product on demand ───────────────────────────────────────────
if ($action === 'product') {
    $cfg = loadConfig($configFile);
    if (!$cfg) { echo json_encode(['error' => 'No credentials saved yet']); exit; }

    $pid = trim($_GET['id'] ?? '');
    if (!$pid) { echo json_encode(['error' => 'No product ID provided']); exit; }

    // Serve from local cache if already synced
    $catalog = loadJSON($catalogFile);
    foreach ($catalog as $p) {
        if ($p['id'] === $pid) { echo json_encode(['product' => $p, 'cached' => true]); exit; }
    }

    // Fetch live from Carolina Made
    $api = new CarolinaMadeAPI($cfg['username'], $cfg['password']);
    try {
        $raw = $api->getProduct($pid);
        $p   = CarolinaMadeAPI::parseProduct($raw, $pid);
        if (!$p) { echo json_encode(['error' => 'No data returned for ' . $pid]); exit; }
        echo json_encode(['product' => $p, 'cached' => false]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ── Browse cached catalog (used after full sync) ──────────────────────────
if ($action === 'catalog') {
    $catalog = loadJSON($catalogFile);
    $q       = strtolower(trim($_GET['q'] ?? ''));
    if ($q) {
        $catalog = array_values(array_filter($catalog, fn($p) =>
            stripos($p['name']        ?? '', $q) !== false ||
            stripos($p['id']          ?? '', $q) !== false ||
            stripos($p['description'] ?? '', $q) !== false
        ));
    }
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 50;
    $total   = count($catalog);
    $slice   = array_slice($catalog, ($page - 1) * $perPage, $perPage);
    echo json_encode(['products' => $slice, 'total' => $total, 'page' => $page, 'pages' => (int)ceil($total / $perPage)]); exit;
}

// ── Import selected products into products.json ───────────────────────────
if ($action === 'import') {
    $ids     = (array)($_POST['ids'] ?? []);
    if (empty($ids)) { echo json_encode(['error' => 'No products selected']); exit; }

    $catalog   = loadJSON($catalogFile);
    $byId      = [];
    foreach ($catalog as $p) { $byId[$p['id']] = $p; }

    $products    = loadJSON($productsFile);
    $existingIds = array_column($products, 'id');
    $imported    = 0;

    foreach ($ids as $id) {
        if (in_array($id, $existingIds)) continue;
        $src = $byId[$id] ?? null;
        if (!$src) continue;
        $products[] = [
            'id'      => $src['id'],
            'name'    => $src['name'],
            'cat'     => 'blanks',
            'badge'   => '',
            'img'     => $src['image'] ?? '',
            'price'   => $src['price'] ?? '',
            'garment' => $src['description'] ?? '',
        ];
        $imported++;
    }

    saveJSON($productsFile, $products);
    echo json_encode(['ok' => true, 'imported' => $imported]); exit;
}

// ── Full sync ─────────────────────────────────────────────────────────────
if ($action === 'sync_full') {
    $cfg = loadConfig($configFile);
    if (!$cfg) { echo json_encode(['error' => 'No credentials saved yet']); exit; }

    set_time_limit(600);
    ini_set('memory_limit', '256M');

    $api     = new CarolinaMadeAPI($cfg['username'], $cfg['password']);
    $catalog = loadJSON($catalogFile);
    $byId    = [];
    foreach ($catalog as $p) { $byId[$p['id']] = $p; }

    $ids     = $api->getProductIds();
    $synced  = 0; $errors = 0;

    foreach ($ids as $pid) {
        try {
            $raw = $api->getProduct($pid);
            $p   = CarolinaMadeAPI::parseProduct($raw, $pid);
            if ($p) {
                $byId[$pid] = array_merge($byId[$pid] ?? [], $p);
                $synced++;
            }
        } catch (Exception $e) {
            $errors++;
        }
        if ($synced % 100 === 0) {
            saveJSON($catalogFile, array_values($byId));
        }
    }

    saveJSON($catalogFile, array_values($byId));
    $meta = ['last_full_sync' => date('c'), 'product_count' => count($byId), 'last_sync_errors' => $errors];
    file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT));
    echo json_encode(['ok' => true, 'synced' => $synced, 'errors' => $errors, 'total' => count($byId)]); exit;
}

// ── Prices-only sync ──────────────────────────────────────────────────────
if ($action === 'sync_prices') {
    $cfg = loadConfig($configFile);
    if (!$cfg) { echo json_encode(['error' => 'No credentials saved yet']); exit; }

    set_time_limit(600);
    $api     = new CarolinaMadeAPI($cfg['username'], $cfg['password']);
    $catalog = loadJSON($catalogFile);
    $updated = 0;

    foreach ($catalog as &$p) {
        $res = $api->getPricing($p['id']);
        if ($res && isset($res->Configuration->PartArray->Part)) {
            $parts = $res->Configuration->PartArray->Part;
            if (!is_array($parts)) $parts = [$parts];
            foreach ($parts as $part) {
                if (isset($part->PartPriceArray->PartPrice)) {
                    $pps = $part->PartPriceArray->PartPrice;
                    if (!is_array($pps)) $pps = [$pps];
                    foreach ($pps as $pp) {
                        if (isset($pp->price) && (float)$pp->price > 0) {
                            $p['price'] = '$' . number_format((float)$pp->price, 2);
                            $updated++;
                            break 2;
                        }
                    }
                }
            }
        }
    }
    unset($p);

    saveJSON($catalogFile, $catalog);
    $meta = loadJSON($metaFile);
    $meta['last_price_sync'] = date('c');
    file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT));
    echo json_encode(['ok' => true, 'updated' => $updated]); exit;
}

// ── Inventory-only sync ───────────────────────────────────────────────────
if ($action === 'sync_inventory') {
    $cfg = loadConfig($configFile);
    if (!$cfg) { echo json_encode(['error' => 'No credentials saved yet']); exit; }

    set_time_limit(600);
    $api     = new CarolinaMadeAPI($cfg['username'], $cfg['password']);
    $catalog = loadJSON($catalogFile);
    $updated = 0;

    foreach ($catalog as &$p) {
        $res = $api->getInventory($p['id']);
        if ($res && isset($res->Inventory->PartInventoryArray->PartInventory)) {
            $parts = $res->Inventory->PartInventoryArray->PartInventory;
            if (!is_array($parts)) $parts = [$parts];
            $total = 0;
            foreach ($parts as $part) {
                $total += (int)($part->QuantityAvailable->Quantity->value ?? 0);
            }
            $p['inventory'] = $total;
            $updated++;
        }
    }
    unset($p);

    saveJSON($catalogFile, $catalog);
    $meta = loadJSON($metaFile);
    $meta['last_inventory_sync'] = date('c');
    file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT));
    echo json_encode(['ok' => true, 'updated' => $updated]); exit;
}

echo json_encode(['error' => 'Unknown action']);
