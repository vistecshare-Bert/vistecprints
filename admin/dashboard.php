<?php
session_start();
if (empty($_SESSION['vp_admin'])) {
    header('Location: index.php');
    exit;
}

$jsonFile = __DIR__ . '/../products.json';

function loadProducts($file) {
    if (!file_exists($file)) return [];
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function saveProducts($file, $products) {
    if (!is_writable($file) && !is_writable(dirname($file))) {
        die('Error: products.json is not writable. Go to File Manager, right-click products.json â†’ Permissions â†’ set to 666.');
    }
    file_put_contents($file, json_encode(array_values($products), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function handleImageUpload($cat) {
    if (empty($_FILES['img_file']['name'])) return null;

    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $mime    = $_FILES['img_file']['type'];
    if (!in_array($mime, $allowed)) return null;

    $uploadDir = __DIR__ . '/../images/' . $cat . '/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $ext      = strtolower(pathinfo($_FILES['img_file']['name'], PATHINFO_EXTENSION));
    $filename = uniqid('img_') . '.' . $ext;
    $dest     = $uploadDir . $filename;

    if (move_uploaded_file($_FILES['img_file']['tmp_name'], $dest)) {
        return 'images/' . $cat . '/' . $filename;
    }
    return null;
}

$catFile = __DIR__ . '/categories.json';
$defaultCategories = [
    'fun'      => 'Fun Print',
    'anime'    => 'Anime',
    'boots'    => 'Boots on the Ground',
    'no_kings' => 'No Kings',
    'jesus'    => 'Jesus Christ',
];
if (file_exists($catFile)) {
    $loaded = json_decode(file_get_contents($catFile), true);
    $categories = is_array($loaded) ? $loaded : $defaultCategories;
} else {
    $categories = $defaultCategories;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Non-product actions: handle and exit before loading products ──
    if ($action === 'order_status') {
        $oFile = __DIR__ . '/../orders.json';
        $orders = file_exists($oFile) ? (json_decode(file_get_contents($oFile), true) ?: []) : [];
        $orderId = $_POST['order_id'] ?? ''; $newSt = $_POST['status'] ?? 'pending';
        foreach ($orders as &$o) { if ($o['id'] === $orderId) { $o['status'] = $newSt; break; } }
        unset($o);
        file_put_contents($oFile, json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        header('Location: dashboard.php?tab=orders&msg=' . urlencode("Order {$orderId} marked {$newSt}.") . '&type=success'); exit;
    }
    if ($action === 'order_delete') {
        $oFile = __DIR__ . '/../orders.json';
        $orders = file_exists($oFile) ? (json_decode(file_get_contents($oFile), true) ?: []) : [];
        $orderId = $_POST['order_id'] ?? '';
        $orders = array_values(array_filter($orders, fn($o) => $o['id'] !== $orderId));
        file_put_contents($oFile, json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        header('Location: dashboard.php?tab=orders&msg=Order+deleted.&type=warning'); exit;
    }
    if ($action === 'quote_status') {
        $qFile = __DIR__ . '/../quotes/' . basename($_POST['quote_id'] ?? '') . '.json';
        if (file_exists($qFile)) { $q = json_decode(file_get_contents($qFile), true) ?: []; $q['status'] = $_POST['status'] ?? 'new'; file_put_contents($qFile, json_encode($q, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); }
        header('Location: dashboard.php?tab=quotes&msg=Quote+updated.&type=success'); exit;
    }
    if ($action === 'quote_delete') {
        $qFile = __DIR__ . '/../quotes/' . basename($_POST['quote_id'] ?? '') . '.json';
        if (file_exists($qFile)) unlink($qFile);
        header('Location: dashboard.php?tab=quotes&msg=Quote+deleted.&type=warning'); exit;
    }
    if ($action === 'contact_status') {
        $cFile = __DIR__ . '/../contacts/' . basename($_POST['contact_id'] ?? '') . '.json';
        if (file_exists($cFile)) { $c = json_decode(file_get_contents($cFile), true) ?: []; $c['status'] = $_POST['status'] ?? 'new'; file_put_contents($cFile, json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); }
        header('Location: dashboard.php?tab=contacts&msg=Contact+updated.&type=success'); exit;
    }
    if ($action === 'contact_delete') {
        $cFile = __DIR__ . '/../contacts/' . basename($_POST['contact_id'] ?? '') . '.json';
        if (file_exists($cFile)) unlink($cFile);
        header('Location: dashboard.php?tab=contacts&msg=Contact+deleted.&type=warning'); exit;
    }
    if ($action === 'dtf_status') {
        $dFile = __DIR__ . '/../orders/' . basename($_POST['order_id'] ?? '') . '.json';
        if (file_exists($dFile)) { $d = json_decode(file_get_contents($dFile), true) ?: []; $d['stage'] = $_POST['stage'] ?? 'new_order'; file_put_contents($dFile, json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); }
        header('Location: dashboard.php?tab=dtf&msg=Order+updated.&type=success'); exit;
    }
    if ($action === 'dtf_add') {
        $dDir = __DIR__ . '/../orders/';
        if (!is_dir($dDir)) mkdir($dDir, 0755, true);
        $oid = 'ORD-' . date('Ymd') . '-' . substr(uniqid(), -6);
        $rec = [
            'id'              => $oid,
            'customer_name'   => trim($_POST['customer_name']   ?? ''),
            'customer_email'  => trim($_POST['customer_email']  ?? ''),
            'customer_phone'  => trim($_POST['customer_phone']  ?? ''),
            'garment_type'    => trim($_POST['garment_type']    ?? ''),
            'qty'             => (int)($_POST['qty'] ?? 1),
            'print_location'  => trim($_POST['print_location']  ?? ''),
            'sizes_breakdown' => trim($_POST['sizes_breakdown'] ?? ''),
            'print_type'      => trim($_POST['print_type']      ?? ''),
            'price_charged'   => trim($_POST['price_charged']   ?? ''),
            'production_cost' => trim($_POST['production_cost'] ?? ''),
            'due_date'        => trim($_POST['due_date']        ?? ''),
            'notes'           => trim($_POST['notes']           ?? ''),
            'stage'           => 'new_order',
            'date'            => date('c'),
            'source'          => 'manual',
        ];
        if (!empty($_FILES['artwork']['name'])) {
            $artDir = __DIR__ . '/../orders/artwork/';
            if (!is_dir($artDir)) mkdir($artDir, 0755, true);
            $ext     = strtolower(pathinfo($_FILES['artwork']['name'], PATHINFO_EXTENSION));
            $artFile = $oid . '_artwork.' . $ext;
            if (move_uploaded_file($_FILES['artwork']['tmp_name'], $artDir . $artFile)) {
                $rec['artwork']      = 'orders/artwork/' . $artFile;
                $rec['artwork_name'] = basename($_FILES['artwork']['name']);
            }
        }
        file_put_contents($dDir . $oid . '.json', json_encode($rec, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        header('Location: dashboard.php?tab=dtf&msg=' . urlencode("Order {$oid} created.") . '&type=success'); exit;
    }
    if ($action === 'dtf_edit') {
        $dFile = __DIR__ . '/../orders/' . basename($_POST['order_id'] ?? '') . '.json';
        if (file_exists($dFile)) {
            $d = json_decode(file_get_contents($dFile), true) ?: [];
            foreach (['customer_name','customer_email','customer_phone','garment_type','print_location','sizes_breakdown','print_type','price_charged','production_cost','due_date','notes'] as $f) {
                $d[$f] = trim($_POST[$f] ?? $d[$f] ?? '');
            }
            $d['qty']   = (int)($_POST['qty']  ?? $d['qty']   ?? 1);
            $d['stage'] = trim($_POST['stage'] ?? $d['stage'] ?? 'new_order');
            if (!empty($_FILES['artwork']['name'])) {
                $artDir  = __DIR__ . '/../orders/artwork/';
                if (!is_dir($artDir)) mkdir($artDir, 0755, true);
                $ext     = strtolower(pathinfo($_FILES['artwork']['name'], PATHINFO_EXTENSION));
                $artFile = $d['id'] . '_artwork.' . $ext;
                if (move_uploaded_file($_FILES['artwork']['tmp_name'], $artDir . $artFile)) {
                    $d['artwork']      = 'orders/artwork/' . $artFile;
                    $d['artwork_name'] = basename($_FILES['artwork']['name']);
                }
            }
            file_put_contents($dFile, json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        header('Location: dashboard.php?tab=dtf&msg=Order+updated.&type=success'); exit;
    }
    if ($action === 'dtf_delete') {
        $dFile = __DIR__ . '/../orders/' . basename($_POST['order_id'] ?? '') . '.json';
        if (file_exists($dFile)) unlink($dFile);
        header('Location: dashboard.php?tab=dtf&msg=Order+deleted.&type=warning'); exit;
    }

    $products = loadProducts($jsonFile);

    // -- CATEGORY MANAGEMENT --
    if ($action === 'add_category') {
        $key   = preg_replace('/[^a-z0-9_]/', '_', strtolower(trim($_POST['cat_key'] ?? '')));
        $label = trim($_POST['cat_label'] ?? '');
        if ($key && $label) {
            $categories[$key] = $label;
            file_put_contents($catFile, json_encode($categories, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        header('Location: dashboard.php?tab=decorated');
        exit;
    }
    if ($action === 'delete_category') {
        $key = trim($_POST['cat_key'] ?? '');
        if ($key && isset($categories[$key])) {
            unset($categories[$key]);
            file_put_contents($catFile, json_encode($categories, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        header('Location: dashboard.php?tab=decorated');
        exit;
    }

    // â”€â”€ CREATE DECORATED PRODUCT â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    if ($action === 'add_decorated') {
        $cat     = trim($_POST['cat']     ?? 'fun');
        $name    = trim($_POST['name']    ?? '');
        $price   = trim($_POST['price']   ?? '');
        $badge   = trim($_POST['badge']   ?? '');
        $garment = trim($_POST['garment'] ?? '');
        $sizes   = array_values($_POST['sizes']  ?? []);
        $colors  = array_values($_POST['colors'] ?? []);
        $preview = $_POST['preview_data'] ?? '';
        $id      = uniqid('dec_');
        $imgPath = '';

        // Save canvas capture as product image
        if ($preview) {
            $raw = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $preview));
            $decDir = __DIR__ . '/../images/decorated/';
            if (!is_dir($decDir)) mkdir($decDir, 0755, true);
            file_put_contents($decDir . $id . '.png', $raw);
            $imgPath = 'images/decorated/' . $id . '.png';
        }

        // Save uploaded design file
        $designPath = '';
        if (!empty($_FILES['design_file']['name'])) {
            $ext = strtolower(pathinfo($_FILES['design_file']['name'], PATHINFO_EXTENSION));
            $dDir = __DIR__ . '/../images/designs/';
            if (!is_dir($dDir)) mkdir($dDir, 0755, true);
            $df = $id . '_design.' . $ext;
            move_uploaded_file($_FILES['design_file']['tmp_name'], $dDir . $df);
            $designPath = 'images/designs/' . $df;
        }

        $products[] = [
            'id'         => $id,
            'name'       => $name,
            'cat'        => $cat,
            'badge'      => $badge,
            'img'        => $imgPath,
            'price'      => $price,
            'garment'    => $garment,
            'decorated'  => true,
            'sizes'      => $sizes,
            'colors'     => $colors,
            'design_img' => $designPath,
        ];
        saveProducts($jsonFile, $products);
        header('Location: dashboard.php?tab=decorated&msg=' . urlencode($name . ' saved!') . '&type=success');
        exit;
    }

    if ($action === 'add' || $action === 'edit') {
        $cat      = trim($_POST['cat'] ?? 'fun');
        $uploaded = handleImageUpload($cat);
        $img      = $uploaded ?? trim($_POST['img'] ?? '');

        $product = [
            'id'      => trim($_POST['id'] ?? uniqid('vp_')),
            'name'    => trim($_POST['name'] ?? ''),
            'cat'     => $cat,
            'badge'   => trim($_POST['badge'] ?? ''),
            'img'     => $img,
            'price'   => trim($_POST['price'] ?? ''),
            'garment' => trim($_POST['garment'] ?? ''),
        ];

        if ($action === 'add') {
            $products[] = $product;
            $msg = 'Product added successfully!';
        } else {
            $idx = (int)($_POST['index'] ?? -1);
            if (isset($products[$idx])) {
                if (empty($product['img'])) $product['img'] = $products[$idx]['img'];
                $products[$idx] = $product;
                $msg = 'Product updated successfully!';
            }
        }
        saveProducts($jsonFile, $products);
        $msgType = 'success';
    }

    if ($action === 'delete') {
        $idx = (int)($_POST['index'] ?? -1);
        if (isset($products[$idx])) {
            array_splice($products, $idx, 1);
            saveProducts($jsonFile, $products);
            $msg = 'Product deleted.';
            $msgType = 'warning';
        }
    }

    header('Location: dashboard.php?msg=' . urlencode($msg ?? '') . '&type=' . ($msgType ?? 'success'));
    exit;
}

$products  = loadProducts($jsonFile);
$flashMsg  = $_GET['msg'] ?? '';
$flashType = $_GET['type'] ?? 'success';

$ordersFile = __DIR__ . '/../orders.json';
function loadOrders($file) {
    if (!file_exists($file)) return [];
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}
$orders = loadOrders($ordersFile);
// Load quotes
$quotesDir = __DIR__ . '/../quotes/';
$quotes = [];
if (is_dir($quotesDir)) {
    foreach (glob($quotesDir . '*.json') as $f) {
        $q = json_decode(file_get_contents($f), true);
        if (is_array($q)) $quotes[] = $q;
    }
    usort($quotes, fn($a,$b) => strcmp($b['date']??'', $a['date']??''));
}
// Load contacts
$contactsDir = __DIR__ . '/../contacts/';
$contacts = [];
if (is_dir($contactsDir)) {
    foreach (glob($contactsDir . '*.json') as $f) {
        $c = json_decode(file_get_contents($f), true);
        if (is_array($c)) $contacts[] = $c;
    }
    usort($contacts, fn($a,$b) => strcmp($b['date']??'', $a['date']??''));
}
// Load DTF orders
$dtfDir = __DIR__ . '/../orders/';
$dtfOrders = [];
if (is_dir($dtfDir)) {
    foreach (glob($dtfDir . '*.json') as $f) {
        $d = json_decode(file_get_contents($f), true);
        if (is_array($d)) $dtfOrders[] = $d;
    }
    usort($dtfOrders, fn($a,$b) => strcmp($b['date']??'', $a['date']??''));
}
$newQuotes    = count(array_filter($quotes,    fn($q) => ($q['status']??'new') === 'new'));
$newContacts  = count(array_filter($contacts,  fn($c) => ($c['status']??'new') === 'new'));
$newDtfOrders = count(array_filter($dtfOrders, fn($d) => ($d['stage']??'')    === 'new_order'));
$activeTab = $_GET['tab'] ?? 'products';
$pendingCount = count(array_filter($orders, fn($o) => ($o['status'] ?? '') === 'pending'));
$carolinaMeta = [];
$carolinaMetaFile = __DIR__ . '/../carolina-sync-meta.json';
if (file_exists($carolinaMetaFile)) { $carolinaMeta = json_decode(file_get_contents($carolinaMetaFile), true) ?: []; }
$carolinaHasConfig = file_exists(__DIR__ . '/../carolina-config.php');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>VistecPrints &mdash; Product Manager</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
:root{--gold:#D49848;--gold-l:#E8B96A;--black:#0a0a0a;--dark:#111;--white:#fff;--gray:#888;--red:#e05555;}
body{background:#f4f4f4;font-family:'DM Sans',sans-serif;font-size:14px;}

.sidebar{position:fixed;top:0;left:0;bottom:0;width:220px;background:var(--black);display:flex;flex-direction:column;z-index:50;}
.sb-logo{padding:24px 20px;border-bottom:1px solid #1a1a1a;font-family:'Bebas Neue',sans-serif;font-size:22px;letter-spacing:3px;color:var(--gold);}
.sb-logo span{color:var(--white);}
.sb-label{padding:20px 20px 8px;font-size:10px;letter-spacing:2px;text-transform:uppercase;color:#444;}
.sb-nav{list-style:none;padding:0 12px;}
.sb-nav li a{display:flex;align-items:center;gap:10px;padding:10px 12px;color:#888;text-decoration:none;font-size:13px;border-radius:3px;transition:all 0.15s;}
.sb-nav li a:hover,.sb-nav li a.active{background:rgba(212,152,72,0.1);color:var(--gold);}
.sb-nav li a svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0;}
.sb-footer{margin-top:auto;padding:16px 20px;border-top:1px solid #1a1a1a;}
.sb-footer a{color:#555;font-size:12px;text-decoration:none;display:flex;align-items:center;gap:8px;transition:color 0.15s;}
.sb-footer a:hover{color:var(--red);}
.sb-footer svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;}

.main{margin-left:220px;min-height:100vh;}
.topbar{background:#fff;border-bottom:1px solid #e8e8e8;padding:0 32px;height:60px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:40;}
.topbar h1{font-family:'Bebas Neue',sans-serif;font-size:22px;letter-spacing:1px;color:#111;}
.btn{display:inline-flex;align-items:center;gap:6px;padding:10px 20px;font-size:12px;font-weight:600;letter-spacing:1.5px;text-transform:uppercase;border:none;border-radius:2px;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all 0.15s;}
.btn-gold{background:var(--gold);color:#000;}
.btn-gold:hover{background:var(--gold-l);}
.btn-outline{background:transparent;border:1px solid #ddd;color:#555;}
.btn-outline:hover{border-color:#aaa;color:#111;}
.btn-red{background:transparent;border:1px solid rgba(224,85,85,0.3);color:var(--red);}
.btn-red:hover{background:rgba(224,85,85,0.08);}
.btn-sm{padding:6px 14px;font-size:11px;}
.content{padding:28px 32px;}

.flash{padding:12px 18px;border-radius:3px;margin-bottom:24px;font-size:13px;font-weight:500;}
.flash-success{background:#f0faf4;border:1px solid #a3d9b5;color:#1e7e45;}
.flash-warning{background:#fff8f0;border:1px solid #f5c88a;color:#9a5c00;}

.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px;}
.stat-card{background:#fff;border:1px solid #e8e8e8;border-radius:3px;padding:20px 24px;}
.stat-card .num{font-family:'Bebas Neue',sans-serif;font-size:36px;color:var(--gold);line-height:1;}
.stat-card .lbl{font-size:12px;color:#888;letter-spacing:1px;text-transform:uppercase;margin-top:4px;}

.table-wrap{background:#fff;border:1px solid #e8e8e8;border-radius:3px;overflow:hidden;}
.table-header{padding:16px 24px;border-bottom:1px solid #f0f0f0;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;}
.table-header h2{font-size:15px;font-weight:600;color:#111;}
.filter-tabs{display:flex;gap:4px;flex-wrap:wrap;}
.ftab{background:transparent;border:1px solid #e0e0e0;color:#888;padding:6px 14px;font-size:11px;letter-spacing:1px;text-transform:uppercase;cursor:pointer;font-family:'DM Sans',sans-serif;border-radius:2px;transition:all 0.15s;}
.ftab.active,.ftab:hover{background:var(--gold);border-color:var(--gold);color:#000;}
table{width:100%;border-collapse:collapse;}
thead tr{background:#fafafa;border-bottom:2px solid #f0f0f0;}
th{padding:12px 16px;text-align:left;font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:#888;font-weight:600;}
td{padding:12px 16px;border-bottom:1px solid #f5f5f5;vertical-align:middle;}
tr:last-child td{border-bottom:none;}
tr:hover td{background:#fafafa;}
.prod-img{width:56px;height:56px;object-fit:cover;border-radius:3px;background:#f0f0f0;display:block;border:1px solid #eee;}
.prod-img-placeholder{width:56px;height:56px;background:#f0f0f0;border-radius:3px;display:flex;align-items:center;justify-content:center;font-size:10px;color:#bbb;letter-spacing:1px;border:1px solid #eee;}
.prod-name{font-weight:500;color:#111;}
.prod-id{font-size:11px;color:#aaa;margin-top:2px;}
.cat-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:500;}
.cat-fun{background:#fff3e0;color:#e65c00;}
.cat-anime{background:#e8f0ff;color:#1a56cc;}
.cat-boots{background:#f0ffe0;color:#2d6a00;}
.cat-jesus{background:#fff0f8;color:#a0006e;}
.cat-no_kings{background:#f5f0ff;color:#5c00c7;}
.badge-tag{display:inline-block;padding:2px 8px;background:var(--gold);color:#000;font-size:10px;font-weight:700;letter-spacing:1px;border-radius:2px;}
.actions{display:flex;gap:6px;}

/* MODAL */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:200;align-items:center;justify-content:center;padding:20px;}
.modal-overlay.open{display:flex;}
.modal{background:#fff;width:100%;max-width:580px;border-radius:4px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.3);max-height:90vh;overflow-y:auto;}
.modal-head{background:var(--black);padding:20px 24px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:10;}
.modal-head h3{font-family:'Bebas Neue',sans-serif;font-size:20px;letter-spacing:1px;color:var(--white);}
.modal-close{background:none;border:none;color:#666;cursor:pointer;font-size:22px;line-height:1;padding:4px;}
.modal-close:hover{color:#fff;}
.modal-body{padding:24px;}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.form-group{margin-bottom:16px;}
.form-group label{display:block;font-size:11px;letter-spacing:1px;text-transform:uppercase;color:#888;margin-bottom:6px;font-weight:600;}
.form-group input,.form-group select{width:100%;border:1px solid #ddd;padding:10px 12px;font-size:13px;font-family:'DM Sans',sans-serif;border-radius:2px;outline:none;color:#111;}
.form-group input:focus,.form-group select:focus{border-color:var(--gold);}
.form-group .hint{font-size:11px;color:#aaa;margin-top:4px;}

/* IMAGE UPLOAD */
.img-upload-area{border:2px dashed #ddd;border-radius:4px;padding:20px;text-align:center;cursor:pointer;transition:all 0.2s;position:relative;background:#fafafa;}
.img-upload-area:hover{border-color:var(--gold);background:#fffbf5;}
.img-upload-area input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}
.img-upload-area svg{width:32px;height:32px;stroke:#ccc;fill:none;stroke-width:1.5;margin-bottom:8px;}
.img-upload-area p{font-size:13px;color:#aaa;margin:0;}
.img-upload-area span{font-size:11px;color:#bbb;}
.upload-preview-wrap{display:none;align-items:center;gap:12px;margin-top:12px;padding:10px;background:#f5f5f5;border-radius:3px;}
.upload-preview-wrap img{width:60px;height:60px;object-fit:cover;border-radius:3px;border:1px solid #eee;}
.upload-preview-wrap .preview-info{flex:1;}
.upload-preview-wrap .preview-name{font-size:12px;font-weight:500;color:#333;}
.upload-preview-wrap .preview-size{font-size:11px;color:#aaa;}
.upload-preview-wrap .remove-img{background:none;border:none;color:var(--red);cursor:pointer;font-size:18px;line-height:1;}
.current-img-wrap{display:flex;align-items:center;gap:10px;padding:10px;background:#f5f5f5;border-radius:3px;margin-bottom:10px;}
.current-img-wrap img{width:56px;height:56px;object-fit:cover;border-radius:3px;border:1px solid #eee;}
.current-img-wrap .cur-label{font-size:11px;color:#888;text-transform:uppercase;letter-spacing:1px;}
.current-img-wrap .cur-path{font-size:12px;color:#555;word-break:break-all;}
.divider{text-align:center;color:#ccc;font-size:11px;letter-spacing:2px;text-transform:uppercase;margin:12px 0;position:relative;}
.divider::before,.divider::after{content:'';position:absolute;top:50%;width:42%;height:1px;background:#eee;}
.divider::before{left:0;}.divider::after{right:0;}

.modal-foot{padding:16px 24px;border-top:1px solid #f0f0f0;display:flex;justify-content:flex-end;gap:10px;}
.empty-state{text-align:center;padding:60px 20px;color:#bbb;}

/* ORDERS */
.order-card{background:#fff;border:1px solid #e8e8e8;border-radius:3px;margin-bottom:16px;overflow:hidden;}
.order-card-head{padding:14px 20px;background:#fafafa;border-bottom:1px solid #f0f0f0;display:flex;align-items:center;gap:16px;flex-wrap:wrap;}
.order-id{font-family:'Bebas Neue',sans-serif;font-size:18px;letter-spacing:1px;color:#111;}
.order-date{font-size:12px;color:#aaa;}
.order-status{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;letter-spacing:0.5px;text-transform:uppercase;}
.status-pending{background:#fff3e0;color:#e65c00;border:1px solid #f5c88a;}
.status-fulfilled{background:#f0faf4;color:#1e7e45;border:1px solid #a3d9b5;}
.status-cancelled{background:#fff0f0;color:#c00;border:1px solid #f5a0a0;}
.order-total{font-weight:700;color:var(--gold);margin-left:auto;font-size:15px;}
.order-card-body{display:grid;grid-template-columns:1fr 1fr;gap:0;}
@media(max-width:700px){.order-card-body{grid-template-columns:1fr;}}
.order-section{padding:16px 20px;border-right:1px solid #f0f0f0;}
.order-section:last-child{border-right:none;}
.order-section h4{font-size:10px;letter-spacing:2px;text-transform:uppercase;color:#aaa;margin-bottom:10px;}
.order-section p{font-size:13px;color:#333;margin-bottom:4px;}
.order-items-list{list-style:none;}
.order-items-list li{font-size:13px;color:#333;padding:5px 0;border-bottom:1px solid #f5f5f5;display:flex;justify-content:space-between;}
.order-items-list li:last-child{border-bottom:none;}
.order-card-foot{padding:12px 20px;border-top:1px solid #f0f0f0;display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
.status-select{border:1px solid #ddd;padding:7px 10px;font-size:12px;font-family:'DM Sans',sans-serif;border-radius:2px;color:#555;background:#fff;cursor:pointer;}
.sb-badge{background:var(--red);color:#fff;font-size:9px;font-weight:700;padding:2px 6px;border-radius:10px;margin-left:auto;}

/* DECORATED CREATOR */
.dec-creator{display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start;margin-bottom:28px;}
@media(max-width:1100px){.dec-creator{grid-template-columns:1fr;}}
.dec-canvas-col{background:#1e1e1e;border:1px solid #333;border-radius:4px;padding:12px;}
.dec-form-col{background:#fff;border:1px solid #e8e8e8;border-radius:3px;padding:20px;}
.dec-section-title{font-size:10px;letter-spacing:2px;text-transform:uppercase;color:#888;font-weight:600;margin-bottom:10px;}
.dec-toolbar{display:flex;align-items:center;gap:4px;padding:6px 0 10px;flex-wrap:wrap;}
.dec-tb-btn{display:inline-flex;align-items:center;gap:4px;padding:5px 10px;background:#2a2a2a;border:1px solid #444;border-radius:3px;color:#ccc;font-size:11px;font-family:'DM Sans',sans-serif;cursor:pointer;transition:all 0.15s;white-space:nowrap;}
.dec-tb-btn:hover{background:#3a3a3a;border-color:#666;color:#fff;}
.dec-tb-btn.icon-only{padding:5px 8px;}
.dec-tb-sep{width:1px;height:24px;background:#444;margin:0 2px;}
.dec-canvas-area{position:relative;width:520px;height:520px;background:#111;border-radius:3px;overflow:hidden;margin:0 auto;}
.dec-mockup-img{position:absolute;inset:0;width:100%;height:100%;object-fit:contain;pointer-events:none;z-index:0;}
.dec-mockup-color{position:absolute;inset:0;z-index:1;mix-blend-mode:multiply;pointer-events:none;transition:background 0.2s;}
.dec-print-zone{position:absolute;pointer-events:none;border:2px dashed rgba(212,152,72,0.55);border-radius:2px;z-index:4;box-sizing:border-box;}
.dec-controls-row{display:flex;align-items:center;gap:16px;padding:10px 4px 6px;flex-wrap:wrap;}
.dec-ctrl-group{display:flex;align-items:center;gap:4px;}
.dec-ctrl-label{font-size:9px;letter-spacing:1.5px;text-transform:uppercase;color:#666;font-weight:600;margin-right:4px;}
.mockup-btn{padding:4px 10px;border:1px solid #444;border-radius:2px;background:#2a2a2a;font-size:11px;cursor:pointer;font-family:'DM Sans',sans-serif;letter-spacing:.5px;transition:all 0.15s;color:#ccc;}
.mockup-btn.active,.mockup-btn:hover{background:var(--gold);border-color:var(--gold);color:#000;}
.dec-swatches{display:flex;flex-wrap:wrap;gap:6px;}
.dec-swatch{width:22px;height:22px;border-radius:50%;cursor:pointer;border:2px solid transparent;transition:all 0.2s;position:relative;}
.dec-swatch.active{border-color:var(--gold);transform:scale(1.15);}
.dec-swatch[data-color="gray"]{background:#8c8c8c;}
.dec-swatch[data-color="white"]{background:#f0f0f0;box-shadow:0 0 0 1px #aaa inset;}
.dec-swatch[data-color="black"]{background:#1a1a1a;box-shadow:0 0 0 1px #555 inset;}
.dec-swatch[data-color="navy"]{background:#1a2744;}
.dec-swatch[data-color="red"]{background:#c0392b;}
.dec-swatch[data-color="royal"]{background:#2850a0;}
.dec-swatch[data-color="forest"]{background:#1e5e38;}
.dec-swatch[data-color="maroon"]{background:#6b1a1a;}
.dec-props-panel{background:#242424;border:1px solid #333;border-radius:3px;padding:10px 12px;margin-top:8px;display:none;}
.dec-props-panel.visible{display:block;}
.dec-prop-group{display:flex;align-items:center;gap:6px;margin-bottom:8px;flex-wrap:wrap;}
.dec-prop-group:last-child{margin-bottom:0;}
.dec-prop-label{font-size:9px;letter-spacing:1.5px;text-transform:uppercase;color:#666;font-weight:600;min-width:46px;}
.dec-prop-input{background:#1a1a1a;border:1px solid #444;border-radius:2px;color:#ddd;font-size:12px;padding:4px 7px;font-family:'DM Sans',sans-serif;}
.dec-prop-input[type="color"]{width:32px;height:26px;padding:1px 2px;cursor:pointer;}
.dec-prop-input[type="number"]{width:60px;}
.dec-prop-input[type="range"]{width:80px;cursor:pointer;}
.dec-style-btn{padding:4px 9px;background:#2a2a2a;border:1px solid #444;border-radius:2px;color:#ccc;font-size:12px;font-family:'DM Sans',sans-serif;cursor:pointer;transition:all 0.15s;}
.dec-style-btn.active,.dec-style-btn:hover{background:var(--gold);border-color:var(--gold);color:#000;}
.form-color-row{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:4px;}
.fcswatch{width:26px;height:26px;border-radius:50%;cursor:pointer;border:2px solid #ddd;transition:all 0.15s;flex-shrink:0;}
.fcswatch.on{border-color:var(--gold);box-shadow:0 0 0 2px #fff,0 0 0 4px var(--gold);}
.fcswatch[data-color="gray"]{background:#8c8c8c;}
.fcswatch[data-color="white"]{background:#f0f0f0;border-color:#ccc;}
.fcswatch[data-color="black"]{background:#1a1a1a;}
.fcswatch[data-color="navy"]{background:#1a2744;}
.fcswatch[data-color="red"]{background:#c0392b;}
.fcswatch[data-color="royal"]{background:#2850a0;}
.fcswatch[data-color="forest"]{background:#1e5e38;}
.fcswatch[data-color="maroon"]{background:#6b1a1a;}
.size-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin-bottom:4px;}
.size-check{display:none;}
.size-check+.size-label{display:block;text-align:center;padding:7px 4px;border:1px solid #ddd;border-radius:2px;font-size:12px;font-weight:500;cursor:pointer;transition:all 0.15s;}
.size-check:checked+.size-label{background:var(--gold);border-color:var(--gold);color:#000;}
.dec-form-foot{display:flex;gap:10px;margin-top:20px;}

/* carolina made panels */
.cm-card{background:#fff;border:1px solid #e8e8e8;border-radius:3px;overflow:hidden;}
.cm-card-head{display:flex;align-items:center;gap:8px;padding:14px 20px;background:#f9f9f9;border-bottom:1px solid #e8e8e8;font-size:12px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#444;}
.cm-card-head svg{stroke:#888;flex-shrink:0;}
.cm-card-body{padding:20px;}
.cm-card table th,.cm-card table td{padding:10px 14px;border-bottom:1px solid #f0f0f0;font-size:12px;text-align:left;vertical-align:middle;}
.cm-card table th{font-size:10px;letter-spacing:1px;text-transform:uppercase;color:#888;font-weight:600;background:#fafafa;}
.cm-card table tr:hover td{background:#fafafa;}

/* quotes / contacts status badges */
.q-status{display:inline-block;padding:3px 10px;border-radius:10px;font-size:11px;font-weight:600;letter-spacing:.5px;text-transform:uppercase;}
.q-new{background:#fff3cd;color:#856404;}
.q-contacted,.q-replied{background:#d1ecf1;color:#0c5460;}
.q-completed,.q-closed{background:#d4edda;color:#155724;}

/* notes row in tables */
.notes-row td{background:#fafafa;color:#888;font-size:12px;font-style:italic;padding:6px 16px !important;border-top:none !important;}

/* kanban board */
.kanban{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;align-items:start;}
@media(max-width:1100px){.kanban{grid-template-columns:repeat(2,1fr);}}
.kanban-col{background:#fff;border:1px solid #e8e8e8;border-radius:3px;overflow:hidden;}
.kanban-header{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:#f9f9f9;border-bottom:1px solid #e8e8e8;font-size:11px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:#444;}
.kanban-count{background:#e0e0e0;color:#555;font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px;}
.kanban-cards{padding:10px;display:flex;flex-direction:column;gap:8px;min-height:80px;}
.kanban-empty{text-align:center;color:#ccc;font-size:12px;padding:16px 0;}
.kanban-card{background:#fff;border:1px solid #e8e8e8;border-radius:3px;padding:12px;transition:box-shadow 0.15s;}
.kanban-card:hover{box-shadow:0 2px 8px rgba(0,0,0,.07);}
.kanban-card-id{font-size:10px;color:#aaa;letter-spacing:.5px;margin-bottom:4px;}
.kanban-card-name{font-weight:600;font-size:13px;color:#111;margin-bottom:4px;}
.kanban-card-meta{font-size:11px;color:#888;margin-bottom:2px;}
.kanban-card-notes{font-size:11px;color:#aaa;font-style:italic;margin:6px 0;border-top:1px dashed #eee;padding-top:6px;}
.kanban-card-foot{display:flex;align-items:center;gap:6px;margin-top:10px;flex-wrap:wrap;}
.dtf-due{font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;display:inline-block;margin-bottom:4px;}
.dtf-due.overdue{background:#fff0f0;color:#c00;border:1px solid #f5a0a0;}
.dtf-due.due-soon{background:#fff8e0;color:#9a6000;border:1px solid #f5d06a;}
.dtf-due.on-track{background:#f0faf4;color:#1e7e45;border:1px solid #a3d9b5;}
.dtf-artwork-link{font-size:11px;color:var(--gold);text-decoration:none;display:inline-flex;align-items:center;gap:3px;font-weight:500;}
.dtf-artwork-link:hover{text-decoration:underline;}
.source-badge{font-size:9px;padding:1px 6px;border-radius:3px;letter-spacing:.5px;text-transform:uppercase;font-weight:700;}
.source-order{background:#e8f0ff;color:#1a56cc;}
.source-manual{background:#f0f0f0;color:#888;}
</style>
</head>
<body>

<aside class="sidebar">
  <div class="sb-logo">VISTEC<span>PRINTS</span></div>
  <div class="sb-label">Manage</div>
  <ul class="sb-nav">
    <li>
      <a href="dashboard.php?tab=products" <?= $activeTab==='products' ? 'class="active"' : '' ?>>
        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
        Products
      </a>
    </li>
    <li>
      <a href="dashboard.php?tab=orders" <?= $activeTab==='orders' ? 'class="active"' : '' ?> style="justify-content:flex-start;">
        <svg viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
        Orders
        <?php if ($pendingCount > 0): ?><span class="sb-badge"><?= $pendingCount ?></span><?php endif; ?>
      </a>
    </li>
    <li>
      <a href="dashboard.php?tab=decorated" <?= $activeTab==='decorated' ? 'class="active"' : '' ?>>
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M8 12l2 2 4-4"/></svg>
        Decorated
      </a>
    </li>
    <li>
      <a href="dashboard.php?tab=quotes" <?= $activeTab==='quotes' ? 'class="active"' : '' ?>>
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        Quotes
        <?php if ($newQuotes > 0): ?><span class="sb-badge"><?= $newQuotes ?></span><?php endif; ?>
      </a>
    </li>
    <li>
      <a href="dashboard.php?tab=contacts" <?= $activeTab==='contacts' ? 'class="active"' : '' ?>>
        <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        Contacts
        <?php if ($newContacts > 0): ?><span class="sb-badge"><?= $newContacts ?></span><?php endif; ?>
      </a>
    </li>
    <li>
      <a href="dashboard.php?tab=dtf" <?= $activeTab==='dtf' ? 'class="active"' : '' ?>>
        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/></svg>
        DTF Pipeline
        <?php if ($newDtfOrders > 0): ?><span class="sb-badge"><?= $newDtfOrders ?></span><?php endif; ?>
      </a>
    </li>
  </ul>
  <div class="sb-label">Integrations</div>
  <ul class="sb-nav" style="padding-bottom:4px;">
    <li>
      <a href="dashboard.php?tab=carolina" <?= $activeTab==='carolina' ? 'class="active"' : '' ?>>
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 8 12 12 14 14"/></svg>
        Carolina Made
        <?php if (!$carolinaHasConfig): ?><span class="sb-badge" style="background:#888;">SET UP</span><?php endif; ?>
      </a>
    </li>
  </ul>
  <div class="sb-label">Tools</div>
  <ul class="sb-nav" style="padding-bottom:8px;">
    <li>
      <a href="products.html">
        <svg viewBox="0 0 24 24"><path d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
        Supplier Catalog
      </a>
    </li>
    <li>
      <a href="../index.html" target="_blank">
        <svg viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
        View Website
      </a>
    </li>
  </ul>
  <div class="sb-footer">
    <a href="logout.php">
      <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Logout
    </a>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <h1><?php
      if ($activeTab === 'orders')   echo 'Orders';
      elseif ($activeTab === 'decorated') echo 'Decorated Products';
      elseif ($activeTab === 'quotes')   echo 'Quote Requests';
      elseif ($activeTab === 'contacts') echo 'Contact Submissions';
      elseif ($activeTab === 'dtf')      echo 'DTF Pipeline';
      elseif ($activeTab === 'carolina') echo 'Carolina Made Connector';
      else echo 'Products';
    ?></h1>
    <?php if ($activeTab === 'products'): ?>
    <button class="btn btn-gold" onclick="openModal()">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Add Product
    </button>
    <?php elseif ($activeTab === 'dtf'): ?>
    <button class="btn btn-gold" onclick="openDtfModal()">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      New Order
    </button>
    <?php endif; ?>
  </div>

  <div class="content">
    <?php if ($flashMsg): ?>
    <div class="flash flash-<?= htmlspecialchars($flashType) ?>"><?= htmlspecialchars($flashMsg) ?></div>
    <?php endif; ?>

    <?php if ($activeTab === 'orders'): ?>
    <!-- ===== ORDERS TAB ===== -->
    <div class="stats">
      <div class="stat-card"><div class="num"><?= count($orders) ?></div><div class="lbl">Total Orders</div></div>
      <div class="stat-card"><div class="num"><?= $pendingCount ?></div><div class="lbl">Pending</div></div>
      <div class="stat-card"><div class="num"><?= count(array_filter($orders, fn($o) => ($o['status']??'') === 'fulfilled')) ?></div><div class="lbl">Fulfilled</div></div>
      <div class="stat-card"><div class="num">$<?= number_format(array_sum(array_map(fn($o) => $o['total']??0, $orders)), 2) ?></div><div class="lbl">Total Revenue</div></div>
    </div>

    <?php if (empty($orders)): ?>
    <div class="empty-state"><p>No orders yet. Orders placed on the website will appear here.</p></div>
    <?php else: ?>
    <?php foreach ($orders as $o):
      $status = $o['status'] ?? 'pending';
      $cust   = $o['customer'] ?? [];
      $addr   = $cust['address'] ?? [];
    ?>
    <div class="order-card">
      <div class="order-card-head">
        <span class="order-id"><?= htmlspecialchars($o['id']) ?></span>
        <span class="order-date"><?= date('M j, Y g:i A', strtotime($o['date'] ?? 'now')) ?></span>
        <span class="order-status status-<?= htmlspecialchars($status) ?>"><?= ucfirst($status) ?></span>
        <span class="order-total">$<?= number_format($o['total'] ?? 0, 2) ?></span>
      </div>
      <div class="order-card-body">
        <div class="order-section">
          <h4>Customer</h4>
          <p><strong><?= htmlspecialchars($cust['name'] ?? 'â€”') ?></strong></p>
          <p><?= htmlspecialchars($cust['email'] ?? '') ?></p>
          <p><?= htmlspecialchars($cust['phone'] ?? '') ?></p>
          <p style="margin-top:8px;color:#888;"><?= htmlspecialchars(($addr['line1']??'') . ', ' . ($addr['city']??'') . ', ' . ($addr['state']??'') . ' ' . ($addr['zip']??'')) ?></p>
          <?php if (!empty($o['notes'])): ?><p style="margin-top:8px;font-style:italic;color:#888;font-size:12px;">"<?= htmlspecialchars($o['notes']) ?>"</p><?php endif; ?>
        </div>
        <div class="order-section">
          <h4>Items Â· Subtotal $<?= number_format($o['subtotal']??0,2) ?> + $<?= number_format($o['shipping']??4.99,2) ?> shipping</h4>
          <ul class="order-items-list">
          <?php foreach (($o['items'] ?? []) as $item): ?>
            <li>
              <span><?= htmlspecialchars($item['name'] ?? '') ?> â€” Size <?= htmlspecialchars($item['size'] ?? '') ?></span>
              <span><?= htmlspecialchars($item['priceStr'] ?? '') ?> Ã—<?= intval($item['qty'] ?? 1) ?></span>
            </li>
          <?php endforeach; ?>
          </ul>
        </div>
      </div>
      <div class="order-card-foot">
        <form method="POST" style="display:flex;align-items:center;gap:8px;">
          <input type="hidden" name="action" value="order_status"/>
          <input type="hidden" name="order_id" value="<?= htmlspecialchars($o['id']) ?>"/>
          <select name="status" class="status-select">
            <option value="pending"   <?= $status==='pending'   ?'selected':'' ?>>Pending</option>
            <option value="fulfilled" <?= $status==='fulfilled' ?'selected':'' ?>>Fulfilled</option>
            <option value="cancelled" <?= $status==='cancelled' ?'selected':'' ?>>Cancelled</option>
          </select>
          <button type="submit" class="btn btn-outline btn-sm">Update Status</button>
        </form>
        <form method="POST" onsubmit="return confirm('Delete this order?')" style="margin-left:auto;">
          <input type="hidden" name="action" value="order_delete"/>
          <input type="hidden" name="order_id" value="<?= htmlspecialchars($o['id']) ?>"/>
          <button type="submit" class="btn btn-red btn-sm">Delete</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php elseif ($activeTab === 'decorated'): ?>
    <!-- ===== DECORATED TAB ===== -->
    <?php
      $decProducts = array_values(array_filter($products, fn($p) => !empty($p['decorated'])));
    ?>

    <form method="POST" id="decForm" enctype="multipart/form-data" onsubmit="captureAndSubmit(event)">
      <input type="hidden" name="action" value="add_decorated"/>
      <input type="hidden" name="preview_data" id="decPreviewData"/>

      <div class="dec-creator">
        <!-- LEFT: Full Canvas Column -->
        <div class="dec-canvas-col">

          <div class="dec-toolbar">
            <button type="button" class="dec-tb-btn icon-only" id="decBtnUndo" title="Undo">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
            </button>
            <button type="button" class="dec-tb-btn icon-only" id="decBtnRedo" title="Redo">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 11-2.13-9.36L23 10"/></svg>
            </button>
            <div class="dec-tb-sep"></div>
            <button type="button" class="dec-tb-btn" id="decBtnText">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="4 7 4 4 20 4 20 7"/><line x1="9" y1="20" x2="15" y2="20"/><line x1="12" y1="4" x2="12" y2="20"/></svg>
              Add Text
            </button>
            <label class="dec-tb-btn" style="cursor:pointer;">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
              Upload Art
              <input type="file" name="design_file" id="decDesignFile" accept="image/png,image/jpeg,image/webp" style="display:none" onchange="loadDesignToCanvas(this)"/>
            </label>
            <div class="dec-tb-sep"></div>
            <button type="button" class="dec-tb-btn icon-only" id="decBtnFwd" title="Bring Forward">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="8" y="8" width="14" height="14" rx="2"/><rect x="2" y="2" width="14" height="14" rx="2" fill="#2a2a2a"/></svg>
            </button>
            <button type="button" class="dec-tb-btn icon-only" id="decBtnBck" title="Send Backward">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="14" height="14" rx="2"/><rect x="8" y="8" width="14" height="14" rx="2" fill="#2a2a2a"/></svg>
            </button>
            <div class="dec-tb-sep"></div>
            <button type="button" class="dec-tb-btn icon-only" id="decBtnDelete" title="Delete Selected">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
            </button>
            <button type="button" class="dec-tb-btn" id="decBtnClear" style="margin-left:auto;">Clear All</button>
          </div>

          <div class="dec-canvas-area" id="decMockupWrap">
            <img class="dec-mockup-img" id="decMockupImg" src="../mockups/tshirt-white-front.png" alt="mockup" crossorigin="anonymous" onerror="this.style.opacity='0.2'"/>
            <div class="dec-mockup-color" id="decMockupColor" style="background:#8c8c8c;"></div>
            <canvas id="decCanvas" width="520" height="520"></canvas>
            <div class="dec-print-zone" id="decPrintZone"></div>
          </div>

          <div class="dec-controls-row">
            <div class="dec-ctrl-group">
              <span class="dec-ctrl-label">Garment</span>
              <button type="button" class="mockup-btn active" data-garment="tshirt" onclick="setDecGarment('tshirt',this)">T-Shirt</button>
              <button type="button" class="mockup-btn" data-garment="tshirt-female" onclick="setDecGarment('tshirt-female',this)">Women's Tee</button>
              <button type="button" class="mockup-btn" data-garment="hoodie" onclick="setDecGarment('hoodie',this)">Hoodie</button>
              <button type="button" class="mockup-btn" data-garment="hoodie-female" onclick="setDecGarment('hoodie-female',this)">Women's Hoodie</button>
            </div>
            <div class="dec-ctrl-group">
              <span class="dec-ctrl-label">View</span>
              <button type="button" class="mockup-btn active" data-view="front" onclick="setDecView('front',this)">Front</button>
              <button type="button" class="mockup-btn" data-view="back" onclick="setDecView('back',this)">Back</button>
            </div>
            <div class="dec-ctrl-group">
              <div class="dec-swatches">
                <div class="dec-swatch active" data-color="gray"   title="Gray"         onclick="setDecColor('gray',this)"></div>
                <div class="dec-swatch"        data-color="white"  title="White"        onclick="setDecColor('white',this)"></div>
                <div class="dec-swatch"        data-color="black"  title="Black"        onclick="setDecColor('black',this)"></div>
                <div class="dec-swatch"        data-color="navy"   title="Navy"         onclick="setDecColor('navy',this)"></div>
                <div class="dec-swatch"        data-color="red"    title="Red"          onclick="setDecColor('red',this)"></div>
                <div class="dec-swatch"        data-color="royal"  title="Royal Blue"   onclick="setDecColor('royal',this)"></div>
                <div class="dec-swatch"        data-color="forest" title="Forest Green" onclick="setDecColor('forest',this)"></div>
                <div class="dec-swatch"        data-color="maroon" title="Maroon"       onclick="setDecColor('maroon',this)"></div>
              </div>
            </div>
          </div>

          <div class="dec-props-panel" id="decPropsPanel">
            <div id="decTextProps">
              <div class="dec-prop-group">
                <span class="dec-prop-label">Font</span>
                <select class="dec-prop-input" id="decPropFont" style="width:120px;">
                  <option value="Arial">Arial</option>
                  <option value="Times New Roman">Times New Roman</option>
                  <option value="Georgia">Georgia</option>
                  <option value="Verdana">Verdana</option>
                  <option value="Impact">Impact</option>
                </select>
                <input type="number" class="dec-prop-input" id="decPropSize" value="40" min="8" max="200" title="Font Size"/>
                <input type="color" class="dec-prop-input" id="decPropColor" value="#ffffff" title="Text Color"/>
                <button type="button" class="dec-style-btn" id="decBtnBold"><strong>B</strong></button>
                <button type="button" class="dec-style-btn" id="decBtnItalic"><em>I</em></button>
              </div>
            </div>
            <div id="decImageProps" style="display:none;">
              <div class="dec-prop-group">
                <span class="dec-prop-label">Opacity</span>
                <input type="range" class="dec-prop-input" id="decPropOpacity" min="0" max="100" value="100"/>
                <span id="decOpacityVal" style="color:#aaa;font-size:11px;min-width:30px;">100%</span>
                <button type="button" class="dec-style-btn" id="decBtnFlipX">&#8596;</button>
                <button type="button" class="dec-style-btn" id="decBtnFlipY">&#8597;</button>
              </div>
            </div>
          </div>

        </div><!-- /.dec-canvas-col -->

        <div class="dec-form-col">
          <div class="dec-section-title">Product Details</div>

          <div class="form-group">
            <label>Product Name *</label>
            <input type="text" name="name" required placeholder="e.g. Anime Galaxy Tee"/>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Category *</label>
              <select name="cat">
                <?php foreach ($categories as $key => $label): ?>
                <option value="<?= $key ?>"><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Price</label>
              <input type="text" name="price" placeholder="$25.00"/>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Badge <span style="font-weight:300;text-transform:none;">(optional)</span></label>
              <input type="text" name="badge" placeholder="New, Hot, Sale..."/>
            </div>
            <div class="form-group">
              <label>Garment</label>
              <input type="text" name="garment" placeholder="Heavy Cotton T-Shirt"/>
            </div>
          </div>

          <div class="form-group">
            <label>Available Sizes</label>
            <div class="size-grid">
              <?php foreach (['S','M','L','XL','2XL','3XL','4XL','5XL'] as $sz): ?>
              <div>
                <input type="checkbox" class="size-check" id="sz_<?= $sz ?>" name="sizes[]" value="<?= $sz ?>"/>
                <label class="size-label" for="sz_<?= $sz ?>"><?= $sz ?></label>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="form-group">
            <label>Available Colors</label>
            <div class="form-color-row">
              <?php foreach (['gray'=>'Gray','white'=>'White','black'=>'Black','navy'=>'Navy','red'=>'Red','royal'=>'Royal Blue','forest'=>'Forest','maroon'=>'Maroon'] as $k=>$v): ?>
              <div class="fcswatch" data-color="<?= $k ?>" data-name="<?= $v ?>" title="<?= $v ?>" onclick="toggleFormColor(this)"></div>
              <?php endforeach; ?>
            </div>
            <div id="decColorsHidden"></div>
          </div>

          <div class="dec-form-foot">
            <a href="dashboard.php?tab=products" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-gold">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
              Save Product
            </button>
          </div>
        </div><!-- /.dec-form-col -->
      </div><!-- /.dec-creator -->
    </form>

    <!-- Manage Categories -->
    <div style="background:#fff;border:1px solid #e8e8e8;border-radius:3px;padding:20px;margin-bottom:24px;">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
        <h3 style="font-size:13px;font-weight:600;letter-spacing:1px;text-transform:uppercase;color:#333;">Manage Categories</h3>
      </div>
      <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;">
        <?php foreach ($categories as $k => $v): ?>
        <div style="display:inline-flex;align-items:center;gap:6px;background:#f5f5f5;border:1px solid #e0e0e0;border-radius:3px;padding:5px 10px;font-size:12px;">
          <span><?= htmlspecialchars($v) ?></span>
          <span style="color:#aaa;font-size:10px;"><?= htmlspecialchars($k) ?></span>
          <form method="POST" style="display:inline;" onsubmit="return confirm('Delete category \'<?= htmlspecialchars($v) ?>\'?')">
            <input type="hidden" name="action" value="delete_category"/>
            <input type="hidden" name="cat_key" value="<?= htmlspecialchars($k) ?>"/>
            <button type="submit" style="background:none;border:none;cursor:pointer;color:#c0392b;font-size:14px;line-height:1;padding:0 2px;" title="Delete">&times;</button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>
      <form method="POST" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
        <input type="hidden" name="action" value="add_category"/>
        <div>
          <label style="font-size:11px;color:#888;display:block;margin-bottom:4px;">Category Key (no spaces)</label>
          <input type="text" name="cat_key" required placeholder="e.g. sports" style="border:1px solid #ddd;padding:7px 10px;border-radius:2px;font-size:12px;width:150px;"/>
        </div>
        <div>
          <label style="font-size:11px;color:#888;display:block;margin-bottom:4px;">Display Name</label>
          <input type="text" name="cat_label" required placeholder="e.g. Sports &amp; Athletics" style="border:1px solid #ddd;padding:7px 10px;border-radius:2px;font-size:12px;width:200px;"/>
        </div>
        <button type="submit" class="btn btn-gold" style="align-self:flex-end;">+ Add Category</button>
      </form>
    </div>

    <!-- Existing decorated products -->
    <?php if (!empty($decProducts)): ?>
    <div class="table-wrap">
      <div class="table-header"><h2>Existing Decorated Products</h2></div>
      <table>
        <thead><tr><th>Image</th><th>Name</th><th>Category</th><th>Price</th><th>Sizes</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($products as $i => $p):
            if (empty($p['decorated'])) continue;
            $imgSrc = $p['img'] ?? '';
            $displaySrc = $imgSrc ? '../' . $imgSrc : '';
          ?>
          <tr>
            <td><?php if ($displaySrc): ?><img src="<?= htmlspecialchars($displaySrc) ?>" class="prod-img" alt="" onerror="this.style.display='none'"/><?php else: ?><div class="prod-img-placeholder">NO IMG</div><?php endif; ?></td>
            <td><div class="prod-name"><?= htmlspecialchars($p['name']) ?></div><div class="prod-id">ID: <?= htmlspecialchars($p['id']) ?></div></td>
            <td><span class="cat-badge cat-<?= htmlspecialchars($p['cat']) ?>"><?= htmlspecialchars($categories[$p['cat']] ?? $p['cat']) ?></span></td>
            <td><?= htmlspecialchars($p['price'] ?? 'â€”') ?></td>
            <td style="font-size:12px;color:#666;"><?= htmlspecialchars(implode(', ', $p['sizes'] ?? [])) ?></td>
            <td>
              <form method="POST" onsubmit="return confirm('Delete this product?')" style="display:inline;">
                <input type="hidden" name="action" value="delete"/>
                <input type="hidden" name="index" value="<?= $i ?>"/>
                <button type="submit" class="btn btn-red btn-sm">Delete</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <?php elseif ($activeTab === 'quotes'): ?>
    <!-- ===== QUOTES TAB ===== -->
    <div class="stats">
      <div class="stat-card"><div class="num"><?= count($quotes) ?></div><div class="lbl">Total Quotes</div></div>
      <div class="stat-card"><div class="num"><?= $newQuotes ?></div><div class="lbl">New</div></div>
      <div class="stat-card"><div class="num"><?= count(array_filter($quotes, fn($q) => ($q['status']??'') === 'contacted')) ?></div><div class="lbl">Contacted</div></div>
      <div class="stat-card"><div class="num"><?= count(array_filter($quotes, fn($q) => ($q['status']??'') === 'completed')) ?></div><div class="lbl">Completed</div></div>
    </div>
    <?php if (empty($quotes)): ?>
    <div class="empty-state"><p>No quote requests yet. Quotes submitted via the website will appear here.</p></div>
    <?php else: ?>
    <div class="table-wrap">
      <div class="table-header"><h2>Quote Requests</h2></div>
      <table>
        <thead><tr><th>Date</th><th>Name</th><th>Contact</th><th>Garment / Details</th><th>Qty</th><th>Estimate</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($quotes as $q):
          $qs = $q['status'] ?? 'new';
          $qid = $q['id'] ?? '';
        ?>
        <tr>
          <td style="color:#888;font-size:12px;white-space:nowrap;"><?= date('M j, Y', strtotime($q['date'] ?? 'now')) ?></td>
          <td><div class="prod-name"><?= htmlspecialchars($q['name'] ?? '—') ?></div><div class="prod-id"><?= htmlspecialchars($qid) ?></div></td>
          <td style="font-size:12px;color:#555;"><?= htmlspecialchars($q['email'] ?? '') ?><br><?= htmlspecialchars($q['phone'] ?? '') ?></td>
          <td style="font-size:12px;color:#555;">
            <?php if (!empty($q['garment_type'])): ?><strong><?= htmlspecialchars($q['garment_type']) ?></strong> — <?= htmlspecialchars($q['print_location'] ?? '') ?> / <?= htmlspecialchars($q['print_method'] ?? '') ?><?php else: ?><?= htmlspecialchars(substr($q['notes'] ?? '—', 0, 60)) ?><?php endif; ?>
          </td>
          <td style="text-align:center;"><?= htmlspecialchars($q['qty'] ?? '—') ?></td>
          <td style="text-align:right;font-weight:600;"><?= isset($q['estimated_price']) ? '$' . number_format((float)$q['estimated_price'], 2) : '—' ?></td>
          <td><span class="q-status q-<?= htmlspecialchars($qs) ?>"><?= ucfirst($qs) ?></span></td>
          <td>
            <form method="POST" style="display:inline-flex;align-items:center;gap:6px;">
              <input type="hidden" name="action" value="quote_status"/>
              <input type="hidden" name="quote_id" value="<?= htmlspecialchars($qid) ?>"/>
              <select name="status" class="status-select">
                <option value="new"       <?= $qs==='new'       ?'selected':'' ?>>New</option>
                <option value="contacted" <?= $qs==='contacted' ?'selected':'' ?>>Contacted</option>
                <option value="completed" <?= $qs==='completed' ?'selected':'' ?>>Completed</option>
              </select>
              <button type="submit" class="btn btn-outline btn-sm">Save</button>
            </form>
            <form method="POST" onsubmit="return confirm('Delete this quote?')" style="display:inline;margin-left:4px;">
              <input type="hidden" name="action" value="quote_delete"/>
              <input type="hidden" name="quote_id" value="<?= htmlspecialchars($qid) ?>"/>
              <button type="submit" class="btn btn-red btn-sm">Delete</button>
            </form>
          </td>
        </tr>
        <?php if (!empty($q['notes'])): ?>
        <tr class="notes-row"><td colspan="8"><em><?= htmlspecialchars($q['notes']) ?></em></td></tr>
        <?php endif; ?>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <?php elseif ($activeTab === 'contacts'): ?>
    <!-- ===== CONTACTS TAB ===== -->
    <div class="stats">
      <div class="stat-card"><div class="num"><?= count($contacts) ?></div><div class="lbl">Total Contacts</div></div>
      <div class="stat-card"><div class="num"><?= $newContacts ?></div><div class="lbl">New</div></div>
      <div class="stat-card"><div class="num"><?= count(array_filter($contacts, fn($c) => ($c['status']??'') === 'replied')) ?></div><div class="lbl">Replied</div></div>
      <div class="stat-card"><div class="num"><?= count(array_filter($contacts, fn($c) => ($c['status']??'') === 'closed')) ?></div><div class="lbl">Closed</div></div>
    </div>
    <?php if (empty($contacts)): ?>
    <div class="empty-state"><p>No contact submissions yet. Messages sent via the Contact page will appear here.</p></div>
    <?php else: ?>
    <div class="table-wrap">
      <div class="table-header"><h2>Contact Submissions</h2></div>
      <table>
        <thead><tr><th>Date</th><th>Name</th><th>Contact</th><th>Type</th><th>Message</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($contacts as $c):
          $cs = $c['status'] ?? 'new';
          $cid = $c['id'] ?? '';
        ?>
        <tr>
          <td style="color:#888;font-size:12px;white-space:nowrap;"><?= date('M j, Y', strtotime($c['date'] ?? 'now')) ?></td>
          <td><div class="prod-name"><?= htmlspecialchars($c['name'] ?? '—') ?></div><div class="prod-id"><?= htmlspecialchars($cid) ?></div></td>
          <td style="font-size:12px;color:#555;"><?= htmlspecialchars($c['email'] ?? '') ?><br><?= htmlspecialchars($c['phone'] ?? '') ?></td>
          <td><span class="cat-badge" style="background:#f0f0f0;color:#555;"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $c['type'] ?? 'general'))) ?></span></td>
          <td style="font-size:12px;color:#555;max-width:260px;"><?= htmlspecialchars(substr($c['message'] ?? '', 0, 100)) ?><?= strlen($c['message']??'') > 100 ? '…' : '' ?></td>
          <td><span class="q-status q-<?= htmlspecialchars($cs) ?>"><?= ucfirst($cs) ?></span></td>
          <td>
            <form method="POST" style="display:inline-flex;align-items:center;gap:6px;">
              <input type="hidden" name="action" value="contact_status"/>
              <input type="hidden" name="contact_id" value="<?= htmlspecialchars($cid) ?>"/>
              <select name="status" class="status-select">
                <option value="new"     <?= $cs==='new'     ?'selected':'' ?>>New</option>
                <option value="replied" <?= $cs==='replied' ?'selected':'' ?>>Replied</option>
                <option value="closed"  <?= $cs==='closed'  ?'selected':'' ?>>Closed</option>
              </select>
              <button type="submit" class="btn btn-outline btn-sm">Save</button>
            </form>
            <form method="POST" onsubmit="return confirm('Delete this contact?')" style="display:inline;margin-left:4px;">
              <input type="hidden" name="action" value="contact_delete"/>
              <input type="hidden" name="contact_id" value="<?= htmlspecialchars($cid) ?>"/>
              <button type="submit" class="btn btn-red btn-sm">Delete</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <?php elseif ($activeTab === 'dtf'): ?>
    <!-- ===== DTF PIPELINE TAB ===== -->
    <?php
      $dtfOverdue = count(array_filter($dtfOrders, fn($d) =>
        !empty($d['due_date']) && ($d['stage']??'') !== 'shipped' && strtotime($d['due_date']) < time()
      ));
    ?>
    <div class="stats">
      <div class="stat-card"><div class="num"><?= count($dtfOrders) ?></div><div class="lbl">Total Orders</div></div>
      <div class="stat-card"><div class="num"><?= count(array_filter($dtfOrders, fn($d) => ($d['stage']??'') === 'new_order')) ?></div><div class="lbl">New</div></div>
      <div class="stat-card"><div class="num"><?= count(array_filter($dtfOrders, fn($d) => in_array($d['stage']??'', ['artwork_approval','ready_to_print']))) ?></div><div class="lbl">In Progress</div></div>
      <div class="stat-card"><div class="num" style="<?= $dtfOverdue > 0 ? 'color:var(--red)' : '' ?>"><?= $dtfOverdue ?></div><div class="lbl">Overdue</div></div>
    </div>

    <div class="kanban">
      <?php
      $stages = [
        'new_order'        => 'New Order',
        'artwork_approval' => 'Artwork Approval',
        'ready_to_print'   => 'Ready to Print',
        'shipped'          => 'Shipped',
      ];
      foreach ($stages as $stageKey => $stageLabel):
        $stageOrders = array_filter($dtfOrders, fn($d) => ($d['stage']??'new_order') === $stageKey);
      ?>
      <div class="kanban-col">
        <div class="kanban-header">
          <?= htmlspecialchars($stageLabel) ?>
          <span class="kanban-count"><?= count($stageOrders) ?></span>
        </div>
        <div class="kanban-cards">
          <?php if (empty($stageOrders)): ?>
          <div class="kanban-empty">No orders</div>
          <?php endif; ?>
          <?php foreach ($stageOrders as $d):
            $did = $d['id'] ?? '';
          ?>
          <?php
            $dueDate  = $d['due_date'] ?? '';
            $dueCls   = ''; $dueLabel = '';
            if ($dueDate && $stageKey !== 'shipped') {
                $ts   = strtotime($dueDate);
                $diff = $ts - time();
                if ($diff < 0)          { $dueCls = 'overdue';  $dueLabel = 'OVERDUE: ' . date('M j', $ts); }
                elseif ($diff < 172800) { $dueCls = 'due-soon'; $dueLabel = 'Due ' . date('M j', $ts); }
                else                    { $dueCls = 'on-track';  $dueLabel = 'Due ' . date('M j', $ts); }
            }
          ?>
          <div class="kanban-card">
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;">
              <div class="kanban-card-id" style="margin:0;"><?= htmlspecialchars($did) ?></div>
              <span class="source-badge <?= ($d['source']??'manual') !== 'manual' ? 'source-order' : 'source-manual' ?>">
                <?= ($d['source']??'manual') !== 'manual' ? 'Web' : 'Manual' ?>
              </span>
            </div>
            <?php if ($dueLabel): ?><div class="dtf-due <?= $dueCls ?>"><?= htmlspecialchars($dueLabel) ?></div><?php endif; ?>
            <div class="kanban-card-name"><?= htmlspecialchars($d['customer_name'] ?? '—') ?></div>
            <div class="kanban-card-meta">
              <?= htmlspecialchars($d['garment_type'] ?? '') ?>
              <?php if (!empty($d['print_type'])): ?> · <?= htmlspecialchars($d['print_type']) ?><?php endif; ?>
              &nbsp;·&nbsp; Qty <?= intval($d['qty'] ?? 0) ?>
            </div>
            <?php if (!empty($d['sizes_breakdown'])): ?>
            <div class="kanban-card-meta" style="font-size:11px;color:#aaa;"><?= htmlspecialchars($d['sizes_breakdown']) ?></div>
            <?php endif; ?>
            <?php if (!empty($d['print_location'])): ?>
            <div class="kanban-card-meta" style="font-size:11px;"><?= htmlspecialchars($d['print_location']) ?></div>
            <?php endif; ?>
            <?php if (!empty($d['customer_email'])): ?>
            <div class="kanban-card-meta"><?= htmlspecialchars($d['customer_email']) ?></div>
            <?php endif; ?>
            <?php if (!empty($d['price_charged'])): ?>
            <div class="kanban-card-meta" style="font-weight:600;color:var(--gold);">
              $<?= htmlspecialchars($d['price_charged']) ?>
              <?php if (!empty($d['production_cost'])): ?>
              <span style="font-weight:400;color:#aaa;font-size:11px;">&nbsp;(cost $<?= htmlspecialchars($d['production_cost']) ?>)</span>
              <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($d['artwork'])): ?>
            <div style="margin:6px 0;">
              <a href="../<?= htmlspecialchars($d['artwork']) ?>" download class="dtf-artwork-link">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <?= htmlspecialchars($d['artwork_name'] ?? 'Download Artwork') ?>
              </a>
            </div>
            <?php endif; ?>
            <?php if (!empty($d['notes'])): ?>
            <div class="kanban-card-notes"><?= htmlspecialchars(substr($d['notes'], 0, 80)) ?></div>
            <?php endif; ?>
            <div class="kanban-card-foot">
              <form method="POST" style="display:inline-flex;align-items:center;gap:4px;">
                <input type="hidden" name="action" value="dtf_status"/>
                <input type="hidden" name="order_id" value="<?= htmlspecialchars($did) ?>"/>
                <select name="stage" class="status-select" style="font-size:11px;padding:3px 6px;">
                  <?php foreach ($stages as $sk => $sl): ?>
                  <option value="<?= $sk ?>" <?= $stageKey===$sk?'selected':'' ?>><?= htmlspecialchars($sl) ?></option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-outline btn-sm">Move</button>
              </form>
              <button class="btn btn-outline btn-sm" onclick='openDtfEditModal(<?= htmlspecialchars(json_encode($d), ENT_QUOTES) ?>)'>Edit</button>
              <form method="POST" onsubmit="return confirm('Delete order?')" style="display:inline;">
                <input type="hidden" name="action" value="dtf_delete"/>
                <input type="hidden" name="order_id" value="<?= htmlspecialchars($did) ?>"/>
                <button type="submit" class="btn btn-red btn-sm">✕</button>
              </form>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- DTF Order Modal (new + edit) -->
    <div class="modal-overlay" id="dtfModalOverlay">
      <div class="modal" style="max-width:640px;">
        <div class="modal-head">
          <h3 id="dtfModalTitle">New DTF Order</h3>
          <button class="modal-close" onclick="closeDtfModal()">&times;</button>
        </div>
        <div class="modal-body">
          <form method="POST" id="dtfForm" enctype="multipart/form-data">
            <input type="hidden" name="action"   id="dtfFormAction" value="dtf_add"/>
            <input type="hidden" name="order_id" id="dtfOrderId"    value=""/>
            <div class="form-row">
              <div class="form-group">
                <label>Customer Name *</label>
                <input type="text" name="customer_name" id="dtfCustName" required placeholder="Full name"/>
              </div>
              <div class="form-group">
                <label>Phone</label>
                <input type="text" name="customer_phone" id="dtfCustPhone" placeholder="555-000-0000"/>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Email</label>
                <input type="email" name="customer_email" id="dtfCustEmail" placeholder="customer@email.com"/>
              </div>
              <div class="form-group">
                <label>Due Date</label>
                <input type="date" name="due_date" id="dtfDueDate"/>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Garment Type</label>
                <input type="text" name="garment_type" id="dtfGarment" placeholder="T-Shirt, Hoodie…"/>
              </div>
              <div class="form-group">
                <label>Print Type</label>
                <select name="print_type" id="dtfPrintType" style="width:100%;border:1px solid #ddd;padding:10px 12px;font-size:13px;font-family:'DM Sans',sans-serif;border-radius:2px;color:#111;">
                  <option value="">— Select —</option>
                  <option value="Full Color">Full Color</option>
                  <option value="White Ink">White Ink</option>
                  <option value="Specialty">Specialty</option>
                </select>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Total Quantity *</label>
                <input type="number" name="qty" id="dtfQty" required min="1" value="1"/>
              </div>
              <div class="form-group">
                <label>Print Location</label>
                <input type="text" name="print_location" id="dtfPrintLocation" placeholder="Full Front, Left Chest…"/>
              </div>
            </div>
            <div class="form-group">
              <label>Size Breakdown <span style="font-weight:300;text-transform:none;">(e.g. S:2, M:5, L:3)</span></label>
              <input type="text" name="sizes_breakdown" id="dtfSizes" placeholder="S:2, M:5, L:3, XL:2"/>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Price Charged</label>
                <input type="text" name="price_charged" id="dtfPriceCharged" placeholder="150.00"/>
              </div>
              <div class="form-group">
                <label>Production Cost</label>
                <input type="text" name="production_cost" id="dtfProdCost" placeholder="45.00"/>
              </div>
            </div>
            <div class="form-group" id="dtfStageGroup" style="display:none;">
              <label>Stage</label>
              <select name="stage" id="dtfStage" style="width:100%;border:1px solid #ddd;padding:10px 12px;font-size:13px;font-family:'DM Sans',sans-serif;border-radius:2px;color:#111;">
                <option value="new_order">New Order</option>
                <option value="artwork_approval">Artwork Approval</option>
                <option value="ready_to_print">Ready to Print</option>
                <option value="shipped">Shipped</option>
              </select>
            </div>
            <div class="form-group">
              <label>Artwork File <span style="font-weight:300;text-transform:none;">(PNG, JPG, PDF, AI, EPS, SVG)</span></label>
              <div class="img-upload-area" style="padding:12px;">
                <input type="file" name="artwork" id="dtfArtworkFile" accept=".png,.jpg,.jpeg,.pdf,.ai,.eps,.svg" onchange="dtfPreviewArtwork(this)"/>
                <svg viewBox="0 0 24 24"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0018 9h-1.26A8 8 0 103 16.3"/></svg>
                <p style="margin:0;font-size:12px;color:#aaa;">Click to attach artwork file</p>
              </div>
              <div id="dtfArtworkPreview" style="display:none;margin-top:8px;padding:8px 12px;background:#f5f5f5;border-radius:3px;font-size:12px;color:#555;align-items:center;gap:8px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                <span id="dtfArtworkName"></span>
              </div>
              <div id="dtfExistingArtwork" style="display:none;margin-top:6px;font-size:12px;color:#888;">
                Current: <a id="dtfExistingArtworkLink" href="#" download class="dtf-artwork-link"></a>
                <span style="color:#aaa;">&nbsp;(upload new file to replace)</span>
              </div>
            </div>
            <div class="form-group">
              <label>Notes</label>
              <textarea name="notes" id="dtfNotes" rows="3" placeholder="Colors, special instructions, deadline details…" style="width:100%;border:1px solid #ddd;padding:8px;border-radius:2px;font-family:'DM Sans',sans-serif;font-size:13px;resize:vertical;"></textarea>
            </div>
            <div class="modal-foot" style="padding:0;margin-top:8px;">
              <button type="button" class="btn btn-outline" onclick="closeDtfModal()">Cancel</button>
              <button type="submit" class="btn btn-gold" id="dtfSubmitBtn">Create Order</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <?php elseif ($activeTab === 'carolina'): ?>
    <!-- ===== CAROLINA MADE TAB ===== -->
    <div class="stats">
      <div class="stat-card"><div class="num"><?= number_format($carolinaMeta['product_count'] ?? 0) ?></div><div class="lbl">Products Synced</div></div>
      <div class="stat-card"><div class="num" style="font-size:20px;"><?= $carolinaMeta['last_full_sync'] ? date('M j', strtotime($carolinaMeta['last_full_sync'])) : '—' ?></div><div class="lbl">Last Full Sync</div></div>
      <div class="stat-card"><div class="num" style="font-size:20px;"><?= $carolinaMeta['last_price_sync'] ? date('M j', strtotime($carolinaMeta['last_price_sync'])) : '—' ?></div><div class="lbl">Last Price Sync</div></div>
      <div class="stat-card"><div class="num" style="font-size:20px;"><?= $carolinaMeta['last_inventory_sync'] ? date('M j', strtotime($carolinaMeta['last_inventory_sync'])) : '—' ?></div><div class="lbl">Last Inventory Sync</div></div>
    </div>

    <!-- Settings Panel -->
    <div class="cm-card" style="margin-bottom:20px;">
      <div class="cm-card-head">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83l-.06.06a2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
        Connection Settings
      </div>
      <div class="cm-card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
          <div class="form-group" style="margin:0;">
            <label>Carolina Made Username</label>
            <input type="text" id="cmUsername" placeholder="PromoStandards username" style="width:100%;border:1px solid #ddd;padding:8px 10px;border-radius:2px;font-size:13px;"/>
          </div>
          <div class="form-group" style="margin:0;">
            <label>Password</label>
            <input type="password" id="cmPassword" placeholder="PromoStandards password" style="width:100%;border:1px solid #ddd;padding:8px 10px;border-radius:2px;font-size:13px;"/>
          </div>
        </div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
          <button class="btn btn-gold" onclick="cmSaveConfig()">Save Credentials</button>
          <button class="btn btn-outline" onclick="cmTestConnection()">Test Connection</button>
          <span id="cmTestResult" style="font-size:13px;color:#888;"></span>
        </div>
        <div style="margin-top:16px;background:#f9f9f9;border:1px solid #eee;border-radius:2px;padding:12px;">
          <div style="font-size:10px;letter-spacing:1.5px;text-transform:uppercase;color:#888;font-weight:700;margin-bottom:8px;">Live Endpoints</div>
          <?php foreach ([
            'productData.php' => 'Product Data',
            'pricing.php'     => 'Pricing',
            'mediaContent.php'=> 'Media Content',
            'inventory.php'   => 'Inventory',
          ] as $ep => $label): ?>
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
            <span style="font-size:11px;color:#aaa;min-width:100px;"><?= $label ?></span>
            <code style="font-size:11px;color:#555;">https://promostandards.carolinamade.com/cgi-bin/ws/<?= $ep ?></code>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Sync Controls -->
    <div class="cm-card" style="margin-bottom:20px;">
      <div class="cm-card-head">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
        Sync Controls
      </div>
      <div class="cm-card-body">
        <div id="cmSyncStatus" style="font-size:13px;color:#888;margin-bottom:14px;">
          <?php if ($carolinaMeta['last_full_sync'] ?? false): ?>
          Last full sync: <strong><?= date('M j, Y g:i A', strtotime($carolinaMeta['last_full_sync'])) ?></strong> &nbsp;·&nbsp; <?= number_format($carolinaMeta['product_count'] ?? 0) ?> products
          <?php else: ?>
          No sync run yet. Click <strong>Full Sync</strong> to pull all Carolina Made products.
          <?php endif; ?>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
          <button class="btn btn-gold" onclick="cmSync('sync_full')">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"/><path d="M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
            Full Sync
          </button>
          <button class="btn btn-outline" onclick="cmSync('sync_prices')">Update Prices</button>
          <button class="btn btn-outline" onclick="cmSync('sync_inventory')">Update Inventory</button>
          <span id="cmSyncResult" style="font-size:13px;color:#888;"></span>
        </div>
        <div id="cmProgress" style="display:none;margin-top:12px;">
          <div style="background:#f0f0f0;border-radius:2px;height:6px;overflow:hidden;">
            <div id="cmProgressBar" style="height:100%;background:var(--gold);width:0%;transition:width 0.3s;"></div>
          </div>
          <div id="cmProgressText" style="font-size:12px;color:#888;margin-top:6px;"></div>
        </div>
      </div>
    </div>

    <!-- Catalog Browser — on-demand, no full sync required -->
    <div class="cm-card">
      <div class="cm-card-head" style="justify-content:space-between;">
        <span style="display:flex;align-items:center;gap:8px;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
          Product Catalog
          <span style="font-size:10px;font-weight:400;color:#aaa;text-transform:none;letter-spacing:0;">— click any row to load details</span>
        </span>
        <div style="display:flex;gap:8px;align-items:center;">
          <input type="text" id="cmSearch" placeholder="Filter by product ID…" style="border:1px solid #ddd;padding:6px 10px;border-radius:2px;font-size:12px;width:180px;" oninput="cmSearchDebounce()"/>
          <button class="btn btn-outline btn-sm" onclick="cmLoadIds(1)">Load IDs</button>
        </div>
      </div>

      <!-- Detail drawer — shown when a product row is clicked -->
      <div id="cmDetailDrawer" style="display:none;border-bottom:1px solid #e8e8e8;background:#fafafa;padding:20px 24px;">
        <div id="cmDetailContent"></div>
      </div>

      <div id="cmCatalogWrap">
        <div class="empty-state" style="padding:40px;">
          <p>Click <strong>Load IDs</strong> to fetch the product list from Carolina Made.<br>
          <small style="color:#aaa;">This loads only the ID list (~2,689 items) — no bulk download.</small></p>
        </div>
      </div>
      <div id="cmPagination" style="display:flex;align-items:center;justify-content:space-between;padding:12px 20px;border-top:1px solid #eee;font-size:12px;color:#888;"></div>
    </div>

    <script>
    (function() {
      let cmPage = 1, cmQuery = '', cmDebounceTimer = null;

      window.cmSearchDebounce = function() {
        clearTimeout(cmDebounceTimer);
        cmDebounceTimer = setTimeout(() => { cmQuery = document.getElementById('cmSearch').value; cmPage = 1; cmLoadIds(1); }, 350);
      };

      window.cmLoadIds = function(page) {
        if (page) cmPage = page;
        const wrap = document.getElementById('cmCatalogWrap');
        wrap.innerHTML = '<div style="padding:24px;text-align:center;color:#aaa;font-size:13px;">Loading product IDs…</div>';
        document.getElementById('cmDetailDrawer').style.display = 'none';
        fetch('../carolina-sync.php?action=ids&page=' + cmPage + '&q=' + encodeURIComponent(cmQuery))
          .then(r => r.json()).then(data => {
            if (data.error) { wrap.innerHTML = `<div class="empty-state" style="padding:40px;"><p>Error: ${data.error}</p></div>`; return; }
            if (!data.ids || data.ids.length === 0) { wrap.innerHTML = '<div class="empty-state" style="padding:40px;"><p>No product IDs found.</p></div>'; return; }
            let html = '<table style="width:100%;"><thead><tr><th>Product ID</th><th>Name</th><th>Price</th><th>Colors</th><th>Sizes</th><th></th></tr></thead><tbody>';
            data.ids.forEach(id => {
              html += `<tr class="cm-id-row" style="cursor:pointer;" onclick="cmLoadProduct('${id}', this)">
                <td style="font-family:monospace;font-size:12px;color:#555;">${id}</td>
                <td id="cmName_${id}" style="color:#aaa;font-size:12px;">click to load</td>
                <td id="cmPrice_${id}" style="font-size:12px;color:#888;">—</td>
                <td id="cmColors_${id}" style="font-size:11px;color:#888;">—</td>
                <td id="cmSizes_${id}" style="font-size:11px;color:#888;">—</td>
                <td><button class="btn btn-outline btn-sm" onclick="event.stopPropagation();cmImportOne('${id}')">Import</button></td>
              </tr>`;
            });
            html += '</tbody></table>';
            wrap.innerHTML = html;
            // Pagination
            let phtml = `<span>${data.total.toLocaleString()} products</span><div style="display:flex;gap:4px;flex-wrap:wrap;">`;
            for (let i = 1; i <= data.pages; i++) {
              if (data.pages > 10 && Math.abs(i - cmPage) > 2 && i !== 1 && i !== data.pages) { if (i === 2 || i === data.pages - 1) phtml += '<span style="padding:4px;">…</span>'; continue; }
              phtml += `<button onclick="cmLoadIds(${i})" style="padding:4px 9px;border:1px solid ${i===cmPage?'var(--gold)':'#ddd'};background:${i===cmPage?'var(--gold)':'#fff'};color:${i===cmPage?'#000':'#555'};border-radius:2px;cursor:pointer;font-size:11px;">${i}</button>`;
            }
            phtml += '</div>';
            document.getElementById('cmPagination').innerHTML = phtml;
          }).catch(e => { wrap.innerHTML = `<div class="empty-state" style="padding:40px;"><p>Request failed: ${e.message}</p></div>`; });
      };

      window.cmLoadProduct = function(id, row) {
        const drawer = document.getElementById('cmDetailDrawer');
        const content = document.getElementById('cmDetailContent');
        // Toggle off if already showing this product
        if (drawer.dataset.id === id && drawer.style.display !== 'none') { drawer.style.display = 'none'; drawer.dataset.id = ''; return; }
        drawer.dataset.id = id;
        drawer.style.display = 'block';
        content.innerHTML = '<div style="color:#aaa;font-size:13px;">Fetching product ' + id + ' from Carolina Made…</div>';
        fetch('../carolina-sync.php?action=product&id=' + encodeURIComponent(id))
          .then(r => r.json()).then(data => {
            if (data.error) { content.innerHTML = `<span style="color:#e05555;">Error: ${data.error}</span>`; return; }
            const p = data.product;
            // Populate the row cells too
            const nameEl = document.getElementById('cmName_' + id);
            if (nameEl) { nameEl.textContent = p.name || id; nameEl.style.color = '#111'; nameEl.style.fontWeight = '600'; }
            const priceEl = document.getElementById('cmPrice_' + id);
            if (priceEl) { priceEl.textContent = p.price || '—'; priceEl.style.color = '#333'; priceEl.style.fontWeight = '600'; }
            const colorsEl = document.getElementById('cmColors_' + id);
            if (colorsEl) colorsEl.textContent = (p.colors||[]).slice(0,5).join(', ') + ((p.colors||[]).length>5?'…':'');
            const sizesEl = document.getElementById('cmSizes_' + id);
            if (sizesEl) sizesEl.textContent = (p.sizes||[]).join(', ');
            // Detail view
            const img = p.image ? `<img src="${p.image}" style="width:80px;height:80px;object-fit:cover;border-radius:3px;border:1px solid #eee;flex-shrink:0;" onerror="this.style.display='none'"/>` : '';
            content.innerHTML = `
              <div style="display:flex;gap:20px;align-items:flex-start;">
                ${img}
                <div style="flex:1;">
                  <div style="font-size:16px;font-weight:700;margin-bottom:4px;">${p.name||id}</div>
                  <div style="font-size:12px;color:#888;margin-bottom:10px;">${p.description||''}</div>
                  <div style="display:flex;gap:24px;flex-wrap:wrap;font-size:13px;">
                    <span><strong>Price:</strong> ${p.price||'—'}</span>
                    <span><strong>Colors:</strong> ${(p.colors||[]).join(', ')||'—'}</span>
                    <span><strong>Sizes:</strong> ${(p.sizes||[]).join(', ')||'—'}</span>
                  </div>
                </div>
                <button class="btn btn-gold btn-sm" onclick="cmImportOne('${id}')">Import to Shop</button>
              </div>`;
          }).catch(e => { content.innerHTML = `<span style="color:#e05555;">Request failed: ${e.message}</span>`; });
      };

      window.cmImportOne = function(id) {
        fetch('../carolina-sync.php?action=product&id=' + encodeURIComponent(id))
          .then(r => r.json()).then(data => {
            if (data.error || !data.product) { alert('Could not load product data: ' + (data.error||'unknown error')); return; }
            const fd = new FormData(); fd.append('action', 'import'); fd.append('ids[]', id);
            return fetch('../carolina-sync.php', { method:'POST', body:fd });
          }).then(r => r && r.json()).then(d => {
            if (!d) return;
            if (d.ok) alert(d.imported > 0 ? 'Product imported to your shop!' : 'Product is already in your shop.');
            else alert('Import error: ' + (d.error||'unknown'));
          }).catch(e => alert('Error: ' + e.message));
      };

      window.cmSaveConfig = function() {
        const u = document.getElementById('cmUsername').value.trim();
        const p = document.getElementById('cmPassword').value.trim();
        if (!u || !p) { alert('Enter username and password'); return; }
        const fd = new FormData();
        fd.append('action', 'save_config'); fd.append('username', u); fd.append('password', p);
        fetch('../carolina-sync.php', { method:'POST', body:fd })
          .then(r => r.json()).then(d => {
            document.getElementById('cmTestResult').textContent = d.ok ? '✓ Credentials saved' : '✗ ' + d.error;
            document.getElementById('cmTestResult').style.color = d.ok ? '#1e7e45' : '#e05555';
          });
      };

      window.cmTestConnection = function() {
        const el = document.getElementById('cmTestResult');
        el.textContent = 'Testing…'; el.style.color = '#888';
        fetch('../carolina-sync.php?action=test')
          .then(r => r.json()).then(d => {
            if (d.ok) { el.textContent = `✓ Connected — ${d.product_count.toLocaleString()} products available`; el.style.color = '#1e7e45'; }
            else { el.textContent = '✗ ' + (d.error || 'Connection failed'); el.style.color = '#e05555'; }
          }).catch(() => { el.textContent = '✗ Request failed'; el.style.color = '#e05555'; });
      };

      window.cmSync = function(action) {
        const labels = { sync_full:'Full Sync', sync_prices:'Price Update', sync_inventory:'Inventory Update' };
        const resultEl = document.getElementById('cmSyncResult');
        const progress = document.getElementById('cmProgress');
        resultEl.textContent = labels[action] + ' running…'; resultEl.style.color = '#888';
        progress.style.display = 'block';
        document.getElementById('cmProgressBar').style.width = '30%';
        document.getElementById('cmProgressText').textContent = 'Connecting to Carolina Made…';
        const fd = new FormData(); fd.append('action', action);
        fetch('../carolina-sync.php', { method:'POST', body:fd })
          .then(r => r.json()).then(d => {
            progress.style.display = 'none';
            if (d.ok) {
              const msg = action === 'sync_full' ? `✓ Synced ${d.synced.toLocaleString()} products (${d.errors} errors)` : `✓ Updated ${d.updated.toLocaleString()} products`;
              resultEl.textContent = msg; resultEl.style.color = '#1e7e45';
              if (action === 'sync_full') document.getElementById('cmSyncStatus').innerHTML = `Last full sync: <strong>just now</strong> &nbsp;·&nbsp; ${d.total.toLocaleString()} products`;
            } else { resultEl.textContent = '✗ ' + (d.error || 'Sync failed'); resultEl.style.color = '#e05555'; }
          }).catch(() => { progress.style.display = 'none'; resultEl.textContent = '✗ Request failed'; resultEl.style.color = '#e05555'; });
      };
    })();
    </script>

    <?php else: ?>
    <!-- ===== PRODUCTS TAB ===== -->
    <?php $regProducts = array_filter($products, fn($p) => empty($p['decorated'])); ?>

    <div class="stats">
      <div class="stat-card"><div class="num"><?= count($regProducts) ?></div><div class="lbl">Total Products</div></div>
      <div class="stat-card"><div class="num"><?= count(array_filter($regProducts, fn($p) => $p['cat'] === 'anime')) ?></div><div class="lbl">Anime</div></div>
      <div class="stat-card"><div class="num"><?= count(array_filter($regProducts, fn($p) => $p['cat'] === 'fun')) ?></div><div class="lbl">Fun Print</div></div>
      <div class="stat-card"><div class="num"><?= count(array_filter($regProducts, fn($p) => !empty($p['badge']))) ?></div><div class="lbl">Featured</div></div>
    </div>

    <div class="table-wrap">
      <div class="table-header">
        <h2>All Products</h2>
        <div class="filter-tabs">
          <button class="ftab active" data-cat="all">All</button>
          <?php foreach ($categories as $key => $label): ?>
          <button class="ftab" data-cat="<?= $key ?>"><?= $label ?></button>
          <?php endforeach; ?>
        </div>
      </div>

      <?php if (empty($products)): ?>
      <div class="empty-state"><p>No products yet. Click <strong>Add Product</strong> to get started.</p></div>
      <?php else: ?>
      <table id="productTable">
        <thead>
          <tr>
            <th>Image</th>
            <th>Product Name</th>
            <th>Category</th>
            <th>Badge</th>
            <th>Price</th>
            <th>Blank Product</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($products as $i => $p):
            if (!empty($p['decorated'])) continue;
            $imgSrc = $p['img'] ?? '';
            $isLocal = $imgSrc && strpos($imgSrc, 'http') !== 0;
            $displaySrc = $isLocal ? '../' . $imgSrc : $imgSrc;
          ?>
          <tr data-cat="<?= htmlspecialchars($p['cat']) ?>">
            <td>
              <?php if ($imgSrc): ?>
              <img src="<?= htmlspecialchars($displaySrc) ?>" alt="" class="prod-img" onerror="this.style.display='none'"/>
              <?php else: ?>
              <div class="prod-img-placeholder">NO IMG</div>
              <?php endif; ?>
            </td>
            <td>
              <div class="prod-name"><?= htmlspecialchars($p['name']) ?></div>
              <div class="prod-id">ID: <?= htmlspecialchars($p['id']) ?></div>
            </td>
            <td><span class="cat-badge cat-<?= htmlspecialchars($p['cat']) ?>"><?= htmlspecialchars($categories[$p['cat']] ?? $p['cat']) ?></span></td>
            <td><?php if (!empty($p['badge'])): ?><span class="badge-tag"><?= htmlspecialchars($p['badge']) ?></span><?php else: ?><span style="color:#ccc">â€”</span><?php endif; ?></td>
            <td><?= htmlspecialchars($p['price'] ?? 'â€”') ?></td>
            <td style="color:#666;max-width:160px;"><?= htmlspecialchars($p['garment'] ?? 'â€”') ?></td>
            <td>
              <div class="actions">
                <button class="btn btn-outline btn-sm" onclick="editProduct(<?= $i ?>, <?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)">Edit</button>
                <form method="POST" onsubmit="return confirm('Delete this product?')" style="display:inline;">
                  <input type="hidden" name="action" value="delete"/>
                  <input type="hidden" name="index" value="<?= $i ?>"/>
                  <button type="submit" class="btn btn-red btn-sm">Delete</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

    <?php endif; // end products tab ?>
  </div>
</div>

<!-- MODAL -->
<div class="modal-overlay" id="modalOverlay">
  <div class="modal">
    <div class="modal-head">
      <h3 id="modalTitle">Add Product</h3>
      <button class="modal-close" onclick="closeModal()">&times;</button>
    </div>
    <div class="modal-body">
      <form method="POST" id="productForm" enctype="multipart/form-data">
        <input type="hidden" name="action" id="formAction" value="add"/>
        <input type="hidden" name="index" id="formIndex" value=""/>
        <input type="hidden" name="img" id="fImgExisting" value=""/>

        <div class="form-row">
          <div class="form-group">
            <label>Product Name *</label>
            <input type="text" name="name" id="fName" required placeholder="e.g. Anime V3-42"/>
          </div>
          <div class="form-group">
            <label>Category *</label>
            <select name="cat" id="fCat" onchange="updateUploadHint()">
              <?php foreach ($categories as $key => $label): ?>
              <option value="<?= $key ?>"><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label>Product Image</label>

          <!-- Current image (edit mode) -->
          <div class="current-img-wrap" id="currentImgWrap" style="display:none;">
            <img id="currentImgThumb" src="" alt=""/>
            <div>
              <div class="cur-label">Current Image</div>
              <div class="cur-path" id="currentImgPath"></div>
            </div>
          </div>

          <!-- Upload area -->
          <div class="img-upload-area" id="uploadArea">
            <input type="file" name="img_file" id="imgFileInput" accept="image/jpeg,image/png,image/webp,image/gif" onchange="previewUpload(this)"/>
            <svg viewBox="0 0 24 24"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0018 9h-1.26A8 8 0 103 16.3"/></svg>
            <p id="uploadHint">Click or drag to upload image</p>
            <span id="uploadSub">Saves to <strong>images/<span id="catFolder">fun</span>/</strong> &nbsp;|&nbsp; JPG, PNG, WEBP</span>
          </div>

          <!-- Upload preview -->
          <div class="upload-preview-wrap" id="uploadPreviewWrap">
            <img id="uploadPreviewImg" src="" alt=""/>
            <div class="preview-info">
              <div class="preview-name" id="uploadPreviewName"></div>
              <div class="preview-size" id="uploadPreviewSize"></div>
            </div>
            <button type="button" class="remove-img" onclick="removeUpload()" title="Remove">&times;</button>
          </div>

        </div>

        <div class="form-group">
          <label>Product ID (DecoNetwork / VistecPrints ID)</label>
          <input type="text" name="id" id="fId" placeholder="e.g. 26858611"/>
          <div class="hint">Links to vistecprints.com/shop/view_product/{id}</div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Price</label>
            <input type="text" name="price" id="fPrice" placeholder="$20.00"/>
          </div>
          <div class="form-group">
            <label>Badge <span style="font-weight:300;text-transform:none;">(optional)</span></label>
            <input type="text" name="badge" id="fBadge" placeholder="New, Hot, Sale..."/>
          </div>
        </div>

        <div class="form-group">
          <label>Blank Product / Garment</label>
          <input type="text" name="garment" id="fGarment" placeholder="e.g. Heavy Cotton 100% Cotton T-Shirt"/>
        </div>

        <div class="modal-foot" style="padding:0;margin-top:8px;">
          <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
          <button type="submit" class="btn btn-gold" id="submitBtn">Add Product</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openModal(edit) {
  document.getElementById('modalOverlay').classList.add('open');
  if (!edit) {
    document.getElementById('modalTitle').textContent = 'Add Product';
    document.getElementById('formAction').value = 'add';
    document.getElementById('formIndex').value = '';
    document.getElementById('submitBtn').textContent = 'Add Product';
    document.getElementById('productForm').reset();
    document.getElementById('fImgExisting').value = '';
    document.getElementById('currentImgWrap').style.display = 'none';
    document.getElementById('uploadPreviewWrap').style.display = 'none';
    updateUploadHint();
  }
}

function closeModal() {
  document.getElementById('modalOverlay').classList.remove('open');
}

function editProduct(idx, data) {
  openModal(true);
  document.getElementById('modalTitle').textContent = 'Edit Product';
  document.getElementById('formAction').value = 'edit';
  document.getElementById('formIndex').value = idx;
  document.getElementById('submitBtn').textContent = 'Save Changes';
  document.getElementById('fName').value    = data.name    || '';
  document.getElementById('fCat').value     = data.cat     || 'fun';
  document.getElementById('fId').value      = data.id      || '';
  document.getElementById('fPrice').value   = data.price   || '';
  document.getElementById('fBadge').value   = data.badge   || '';
  document.getElementById('fGarment').value = data.garment || '';
  document.getElementById('fImgExisting').value = data.img || '';

  // Show current image
  if (data.img) {
    const src = data.img.startsWith('http') ? data.img : '../' + data.img;
    document.getElementById('currentImgThumb').src = src;
    document.getElementById('currentImgPath').textContent = data.img;
    document.getElementById('currentImgWrap').style.display = 'flex';
  } else {
    document.getElementById('currentImgWrap').style.display = 'none';
  }

  document.getElementById('uploadPreviewWrap').style.display = 'none';
  updateUploadHint();
}

function updateUploadHint() {
  const cat = document.getElementById('fCat').value;
  document.getElementById('catFolder').textContent = cat;
}

function previewUpload(input) {
  if (!input.files || !input.files[0]) return;
  const file = input.files[0];
  const reader = new FileReader();
  reader.onload = e => {
    document.getElementById('uploadPreviewImg').src = e.target.result;
    document.getElementById('uploadPreviewName').textContent = file.name;
    document.getElementById('uploadPreviewSize').textContent = (file.size / 1024).toFixed(1) + ' KB';
    document.getElementById('uploadPreviewWrap').style.display = 'flex';
    document.getElementById('fImgExisting').value = '';
  };
  reader.readAsDataURL(file);
}

function removeUpload() {
  document.getElementById('imgFileInput').value = '';
  document.getElementById('uploadPreviewWrap').style.display = 'none';
}

document.getElementById('modalOverlay').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});

document.querySelectorAll('.ftab').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.ftab').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
    const cat = this.dataset.cat;
    document.querySelectorAll('#productTable tbody tr').forEach(row => {
      row.style.display = (cat === 'all' || row.dataset.cat === cat) ? '' : 'none';
    });
  });
});

// â”€â”€ DECORATED CREATOR â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
(function() {
  if (!document.getElementById('decCanvas')) return;

  const SIZE = 520;

  const DEC_MOCKUPS = {
    tshirt: {
      gray:   { front:'../mockups/tshirt-gray-front.png',   back:'../mockups/tshirt-gray-back.png'   },
      white:  { front:'../mockups/tshirt-white-front.png',  back:'../mockups/tshirt-white-back.png'  },
      black:  { front:'../mockups/tshirt-black-front.png',  back:'../mockups/tshirt-black-back.png'  },
      navy:   { front:'../mockups/tshirt-navy-front.png',   back:'../mockups/tshirt-navy-back.png'   },
      red:    { front:'../mockups/tshirt-red-front.png',    back:'../mockups/tshirt-red-back.png'    },
      royal:  { front:'../mockups/tshirt-royal-front.png',  back:'../mockups/tshirt-royal-back.png'  },
      forest: { front:'../mockups/tshirt-forest-front.png', back:'../mockups/tshirt-forest-back.png' },
      maroon: { front:'../mockups/tshirt-maroon-front.png', back:'../mockups/tshirt-maroon-back.png' },
    },
    hoodie: {
      gray:   { front:'../mockups/hoodie-gray-front.png',   back:'../mockups/hoodie-gray-back.png'   },
      white:  { front:'../mockups/hoodie-white-front.png',  back:'../mockups/hoodie-white-back.png'  },
      black:  { front:'../mockups/hoodie-black-front.png',  back:'../mockups/hoodie-black-back.png'  },
      navy:   { front:'../mockups/hoodie-navy-front.png',   back:'../mockups/hoodie-navy-back.png'   },
      red:    { front:'../mockups/hoodie-red-front.png',    back:'../mockups/hoodie-red-back.png'    },
      royal:  { front:'../mockups/hoodie-royal-front.png',  back:'../mockups/hoodie-royal-back.png'  },
      forest: { front:'../mockups/hoodie-forest-front.png', back:'../mockups/hoodie-forest-back.png' },
      maroon: { front:'../mockups/hoodie-maroon-front.png', back:'../mockups/hoodie-maroon-back.png' },
    },
    'tshirt-female': {
      gray:   { front:'../mockups/tshirt-female-gray-front.png',   back:'../mockups/tshirt-female-gray-back.png'   },
      white:  { front:'../mockups/tshirt-female-white-front.png',  back:'../mockups/tshirt-female-white-back.png'  },
      black:  { front:'../mockups/tshirt-female-black-front.png',  back:'../mockups/tshirt-female-black-back.png'  },
      navy:   { front:'../mockups/tshirt-female-navy-front.png',   back:'../mockups/tshirt-female-navy-back.png'   },
      red:    { front:'../mockups/tshirt-female-red-front.png',    back:'../mockups/tshirt-female-red-back.png'    },
      royal:  { front:'../mockups/tshirt-female-royal-front.png',  back:'../mockups/tshirt-female-royal-back.png'  },
      forest: { front:'../mockups/tshirt-female-forest-front.png', back:'../mockups/tshirt-female-forest-back.png' },
      maroon: { front:'../mockups/tshirt-female-maroon-front.png', back:'../mockups/tshirt-female-maroon-back.png' },
    },
    'hoodie-female': {
      gray:   { front:'../mockups/hoodie-female-gray-front.png',   back:'../mockups/hoodie-female-gray-back.png'   },
      white:  { front:'../mockups/hoodie-female-white-front.png',  back:'../mockups/hoodie-female-white-back.png'  },
      black:  { front:'../mockups/hoodie-female-black-front.png',  back:'../mockups/hoodie-female-black-back.png'  },
      navy:   { front:'../mockups/hoodie-female-navy-front.png',   back:'../mockups/hoodie-female-navy-back.png'   },
      red:    { front:'../mockups/hoodie-female-red-front.png',    back:'../mockups/hoodie-female-red-back.png'    },
      royal:  { front:'../mockups/hoodie-female-royal-front.png',  back:'../mockups/hoodie-female-royal-back.png'  },
      forest: { front:'../mockups/hoodie-female-forest-front.png', back:'../mockups/hoodie-female-forest-back.png' },
      maroon: { front:'../mockups/hoodie-female-maroon-front.png', back:'../mockups/hoodie-female-maroon-back.png' },
    },
  };

  const DEC_COLOR_HEX = {
    gray:'#8c8c8c', white:null, black:'#1e1e1e', navy:'#1a2744',
    red:'#c0392b', royal:'#2850a0', forest:'#1e5e38', maroon:'#6b1a1a',
  };

  const PRINT_ZONES = {
    front:{ left:0.33, top:0.28, width:0.34, height:0.32 },
    back: { left:0.32, top:0.27, width:0.36, height:0.34 },
  };

  let fc = null;
  let decGarment = 'tshirt', decColor = 'gray', decView = 'front';
  let history = [], historyIndex = -1;

  function pushHistory() {
    const json = JSON.stringify(fc.toJSON());
    history = history.slice(0, historyIndex + 1);
    history.push(json);
    if (history.length > 40) history.shift();
    historyIndex = history.length - 1;
  }

  function undo() {
    if (historyIndex <= 0) return;
    historyIndex--;
    fc.loadFromJSON(history[historyIndex], () => { fc.renderAll(); updatePropsPanel(); });
  }

  function redo() {
    if (historyIndex >= history.length - 1) return;
    historyIndex++;
    fc.loadFromJSON(history[historyIndex], () => { fc.renderAll(); updatePropsPanel(); });
  }

  function updateDecPrintZone() {
    const z = PRINT_ZONES[decView];
    const el = document.getElementById('decPrintZone');
    if (!el) return;
    el.style.left   = (z.left   * SIZE) + 'px';
    el.style.top    = (z.top    * SIZE) + 'px';
    el.style.width  = (z.width  * SIZE) + 'px';
    el.style.height = (z.height * SIZE) + 'px';
  }

  const DEC_GARMENT_LABELS = {
    tshirt:'T-Shirt', 'tshirt-female':"Women's Tee",
    hoodie:'Hoodie',  'hoodie-female':"Women's Hoodie",
  };

  function updateDecMockup() {
    const map  = DEC_MOCKUPS[decGarment] || DEC_MOCKUPS.tshirt;
    const base = map.white || map.gray;
    const src  = base[decView] || base.front;
    const fallback = src.replace('-female-', '-');
    const img = document.getElementById('decMockupImg');
    img.onerror = () => { img.onerror = null; img.src = fallback; };
    img.src = src;
    const hex = DEC_COLOR_HEX[decColor];
    document.getElementById('decMockupColor').style.background = hex || 'transparent';
    updateDecPrintZone();
  }

  function updatePropsPanel() {
    const obj = fc.getActiveObject();
    const panel = document.getElementById('decPropsPanel');
    const textDiv = document.getElementById('decTextProps');
    const imgDiv  = document.getElementById('decImageProps');
    if (!obj) { panel.classList.remove('visible'); return; }
    panel.classList.add('visible');
    if (obj.type === 'i-text' || obj.type === 'text') {
      textDiv.style.display = '';
      imgDiv.style.display  = 'none';
      document.getElementById('decPropFont').value  = obj.fontFamily || 'Arial';
      document.getElementById('decPropSize').value  = Math.round(obj.fontSize || 40);
      document.getElementById('decPropColor').value = obj.fill || '#ffffff';
      document.getElementById('decBtnBold').classList.toggle('active', obj.fontWeight === 'bold');
      document.getElementById('decBtnItalic').classList.toggle('active', obj.fontStyle === 'italic');
    } else {
      textDiv.style.display = 'none';
      imgDiv.style.display  = '';
      const opVal = Math.round((obj.opacity || 1) * 100);
      document.getElementById('decPropOpacity').value = opVal;
      document.getElementById('decOpacityVal').textContent = opVal + '%';
    }
  }

  function initCanvas() {
    fc = new fabric.Canvas('decCanvas', {width: SIZE, height: SIZE});
    fc.wrapperEl.style.position = 'relative';
    fc.wrapperEl.style.zIndex   = '2';

    fabric.Object.prototype.set({
      cornerColor: '#D49848',
      cornerStrokeColor: '#9A6B2E',
      cornerStyle: 'circle',
      cornerSize: 10,
      transparentCorners: false,
      borderColor: '#D49848',
      borderDashArray: [5, 3],
      padding: 8,
    });

    updateDecMockup();
    pushHistory();

    fc.on('object:modified', pushHistory);
    fc.on('object:added',    pushHistory);
    fc.on('object:removed',  pushHistory);
    fc.on('selection:created',  updatePropsPanel);
    fc.on('selection:updated',  updatePropsPanel);
    fc.on('selection:cleared',  updatePropsPanel);
    fc.on('object:modified', updatePropsPanel);

    document.getElementById('decBtnUndo').addEventListener('click', undo);
    document.getElementById('decBtnRedo').addEventListener('click', redo);
    document.getElementById('decBtnDelete').addEventListener('click', () => {
      const obj = fc.getActiveObject();
      if (obj) { fc.remove(obj); fc.discardActiveObject(); fc.renderAll(); }
    });
    document.getElementById('decBtnClear').addEventListener('click', () => {
      if (!confirm('Clear all objects from canvas?')) return;
      fc.getObjects().slice().forEach(o => fc.remove(o));
      fc.renderAll(); pushHistory();
    });
    document.getElementById('decBtnFwd').addEventListener('click', () => {
      const obj = fc.getActiveObject();
      if (obj) { fc.bringForward(obj); fc.renderAll(); pushHistory(); }
    });
    document.getElementById('decBtnBck').addEventListener('click', () => {
      const obj = fc.getActiveObject();
      if (obj) { fc.sendBackwards(obj); fc.renderAll(); pushHistory(); }
    });
    document.getElementById('decBtnText').addEventListener('click', () => {
      const z = PRINT_ZONES[decView];
      const txt = new fabric.IText('Your Text', {
        left: (z.left + z.width / 2) * SIZE,
        top:  (z.top  + z.height/ 2) * SIZE,
        originX: 'center', originY: 'center',
        fontSize: 40, fill: '#ffffff', fontFamily: 'Arial',
        selectable: true, hasControls: true,
      });
      fc.add(txt); fc.setActiveObject(txt); fc.renderAll(); updatePropsPanel();
    });

    document.getElementById('decPropFont').addEventListener('change', e => {
      const obj = fc.getActiveObject();
      if (obj && (obj.type==='i-text'||obj.type==='text')) { obj.set('fontFamily', e.target.value); fc.renderAll(); }
    });
    document.getElementById('decPropSize').addEventListener('input', e => {
      const obj = fc.getActiveObject();
      if (obj && (obj.type==='i-text'||obj.type==='text')) { obj.set('fontSize', parseInt(e.target.value)||40); fc.renderAll(); }
    });
    document.getElementById('decPropColor').addEventListener('input', e => {
      const obj = fc.getActiveObject();
      if (obj && (obj.type==='i-text'||obj.type==='text')) { obj.set('fill', e.target.value); fc.renderAll(); }
    });
    document.getElementById('decBtnBold').addEventListener('click', function() {
      const obj = fc.getActiveObject(); if (!obj) return;
      const isBold = obj.fontWeight === 'bold';
      obj.set('fontWeight', isBold ? 'normal' : 'bold');
      this.classList.toggle('active', !isBold); fc.renderAll();
    });
    document.getElementById('decBtnItalic').addEventListener('click', function() {
      const obj = fc.getActiveObject(); if (!obj) return;
      const isItalic = obj.fontStyle === 'italic';
      obj.set('fontStyle', isItalic ? 'normal' : 'italic');
      this.classList.toggle('active', !isItalic); fc.renderAll();
    });
    document.getElementById('decPropOpacity').addEventListener('input', function() {
      const obj = fc.getActiveObject(); if (!obj) return;
      obj.set('opacity', parseInt(this.value) / 100);
      document.getElementById('decOpacityVal').textContent = this.value + '%';
      fc.renderAll();
    });
    document.getElementById('decBtnFlipX').addEventListener('click', () => {
      const obj = fc.getActiveObject();
      if (obj) { obj.set('flipX', !obj.flipX); fc.renderAll(); pushHistory(); }
    });
    document.getElementById('decBtnFlipY').addEventListener('click', () => {
      const obj = fc.getActiveObject();
      if (obj) { obj.set('flipY', !obj.flipY); fc.renderAll(); pushHistory(); }
    });

    document.addEventListener('keydown', e => {
      if (!document.getElementById('decCanvas')) return;
      if (e.target.tagName==='INPUT'||e.target.tagName==='TEXTAREA'||e.target.tagName==='SELECT') return;
      if ((e.ctrlKey||e.metaKey) && e.key==='z') { e.preventDefault(); undo(); }
      if ((e.ctrlKey||e.metaKey) && e.key==='y') { e.preventDefault(); redo(); }
      if ((e.key==='Delete'||e.key==='Backspace') && fc.getActiveObject()) {
        const obj = fc.getActiveObject();
        if (obj.type !== 'i-text') { fc.remove(obj); fc.renderAll(); }
      }
    });
  }

  window.setDecGarment = function(type, btn) {
    decGarment = type;
    document.querySelectorAll('[data-garment]').forEach(b => b.classList.remove('active'));
    btn.classList.add('active'); updateDecMockup();
  };

  window.setDecView = function(view, btn) {
    decView = view;
    document.querySelectorAll('[data-view]').forEach(b => b.classList.remove('active'));
    btn.classList.add('active'); updateDecMockup();
  };

  window.setDecColor = function(color, el) {
    decColor = color;
    document.querySelectorAll('.dec-swatch').forEach(s => s.classList.remove('active'));
    el.classList.add('active'); updateDecMockup();
  };

  window.toggleFormColor = function(el) {
    el.classList.toggle('on');
    const name = el.dataset.name;
    const hidden = document.getElementById('decColorsHidden');
    const existing = hidden.querySelector(`input[value="${name}"]`);
    if (el.classList.contains('on')) {
      if (!existing) {
        const inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'colors[]'; inp.value = name;
        hidden.appendChild(inp);
      }
    } else { if (existing) existing.remove(); }
  };

  window.loadDesignToCanvas = function(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
      fabric.Image.fromURL(e.target.result, img => {
        const z = PRINT_ZONES[decView];
        const zW = z.width * SIZE, zH = z.height * SIZE;
        const scale = Math.min(zW / img.width, zH / img.height, 1);
        img.set({
          left: (z.left + z.width / 2) * SIZE,
          top:  (z.top  + z.height/ 2) * SIZE,
          originX:'center', originY:'center',
          scaleX: scale, scaleY: scale,
          selectable: true, hasControls: true,
        });
        fc.add(img); fc.setActiveObject(img); fc.renderAll(); updatePropsPanel();
      });
    };
    reader.readAsDataURL(input.files[0]);
  };

  window.captureAndSubmit = function(e) {
    e.preventDefault();
    fc.discardActiveObject(); fc.renderAll();
    const off = document.createElement('canvas');
    off.width = off.height = SIZE;
    const ctx = off.getContext('2d');
    const mockImg = document.getElementById('decMockupImg');
    try { ctx.drawImage(mockImg, 0, 0, SIZE, SIZE); } catch(_) {}
    const hex = DEC_COLOR_HEX[decColor];
    if (hex) {
      ctx.globalCompositeOperation = 'multiply';
      ctx.fillStyle = hex; ctx.fillRect(0, 0, SIZE, SIZE);
      ctx.globalCompositeOperation = 'source-over';
    }
    const fabricImg = new Image();
    fabricImg.onload = () => {
      ctx.drawImage(fabricImg, 0, 0, SIZE, SIZE);
      document.getElementById('decPreviewData').value = off.toDataURL('image/png');
      e.target.submit();
    };
    fabricImg.src = fc.toDataURL({format:'png'});
  };

  initCanvas();
})();

// DTF modal
function openDtfModal() {
  document.getElementById('dtfModalTitle').textContent  = 'New DTF Order';
  document.getElementById('dtfFormAction').value        = 'dtf_add';
  document.getElementById('dtfOrderId').value           = '';
  document.getElementById('dtfSubmitBtn').textContent   = 'Create Order';
  document.getElementById('dtfStageGroup').style.display = 'none';
  document.getElementById('dtfForm').reset();
  document.getElementById('dtfArtworkPreview').style.display  = 'none';
  document.getElementById('dtfExistingArtwork').style.display = 'none';
  document.getElementById('dtfModalOverlay').classList.add('open');
}
function openDtfEditModal(data) {
  document.getElementById('dtfModalTitle').textContent   = 'Edit Order — ' + (data.id || '');
  document.getElementById('dtfFormAction').value         = 'dtf_edit';
  document.getElementById('dtfOrderId').value            = data.id            || '';
  document.getElementById('dtfSubmitBtn').textContent    = 'Save Changes';
  document.getElementById('dtfStageGroup').style.display = '';
  document.getElementById('dtfCustName').value           = data.customer_name   || '';
  document.getElementById('dtfCustPhone').value          = data.customer_phone  || '';
  document.getElementById('dtfCustEmail').value          = data.customer_email  || '';
  document.getElementById('dtfDueDate').value            = data.due_date        || '';
  document.getElementById('dtfGarment').value            = data.garment_type    || '';
  document.getElementById('dtfPrintType').value          = data.print_type      || '';
  document.getElementById('dtfQty').value                = data.qty             || 1;
  document.getElementById('dtfPrintLocation').value      = data.print_location  || '';
  document.getElementById('dtfSizes').value              = data.sizes_breakdown || '';
  document.getElementById('dtfPriceCharged').value       = data.price_charged   || '';
  document.getElementById('dtfProdCost').value           = data.production_cost || '';
  document.getElementById('dtfStage').value              = data.stage           || 'new_order';
  document.getElementById('dtfNotes').value              = data.notes           || '';
  document.getElementById('dtfArtworkPreview').style.display  = 'none';
  document.getElementById('dtfArtworkFile').value        = '';
  if (data.artwork) {
    const link = document.getElementById('dtfExistingArtworkLink');
    link.href        = '../' + data.artwork;
    link.textContent = data.artwork_name || 'Download current artwork';
    document.getElementById('dtfExistingArtwork').style.display = 'block';
  } else {
    document.getElementById('dtfExistingArtwork').style.display = 'none';
  }
  document.getElementById('dtfModalOverlay').classList.add('open');
}
function closeDtfModal() {
  document.getElementById('dtfModalOverlay').classList.remove('open');
}
function dtfPreviewArtwork(input) {
  if (!input.files || !input.files[0]) return;
  document.getElementById('dtfArtworkName').textContent       = input.files[0].name;
  document.getElementById('dtfArtworkPreview').style.display  = 'flex';
}
const dtfOverlay = document.getElementById('dtfModalOverlay');
if (dtfOverlay) dtfOverlay.addEventListener('click', function(e){ if(e.target===this) closeDtfModal(); });
</script>
</body>
</html>
