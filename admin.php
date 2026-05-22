<?php
session_start();

$cfgFile = __DIR__ . '/admin-config.php';
if (!file_exists($cfgFile)) {
    die('<div style="font-family:sans-serif;padding:60px;color:#e05555;background:#111;min-height:100vh;">
        <h2>Setup required</h2>
        <p>Create <code>admin-config.php</code> on the server based on <code>admin-config.example.php</code>, then refresh.</p>
    </div>');
}
require_once $cfgFile;

// --- Logout ---
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php'); exit;
}

// --- Login POST ---
$loginError = '';
if (!empty($_POST['password']) && !isset($_SESSION['vg_admin'])) {
    if (hash_equals(ADMIN_PASSWORD, $_POST['password'])) {
        $_SESSION['vg_admin'] = true;
        session_regenerate_id(true);
        header('Location: admin.php'); exit;
    }
    $loginError = 'Incorrect password.';
}

$isAuth = !empty($_SESSION['vg_admin']);

// --- AJAX actions ---
if ($isAuth && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    // ── Quotes ──
    if (in_array($action, ['status','delete'])) {
        $quotesDir = __DIR__ . '/quotes';
        $id   = preg_replace('/[^A-Z0-9\-]/', '', strtoupper($_POST['id'] ?? ''));
        $file = $quotesDir . '/' . $id . '.json';
        if ($action === 'status' && file_exists($file)) {
            $q = json_decode(file_get_contents($file), true);
            $allowed = ['new', 'contacted', 'completed'];
            $q['status'] = in_array($_POST['status'], $allowed) ? $_POST['status'] : $q['status'];
            file_put_contents($file, json_encode($q, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            echo json_encode(['success' => true]);
        } elseif ($action === 'delete' && file_exists($file)) {
            unlink($file); echo json_encode(['success' => true]);
        } else { http_response_code(400); echo json_encode(['error' => 'Not found.']); }

    // ── Contacts ──
    } elseif (in_array($action, ['contact_status','contact_delete'])) {
        $contactsDir = __DIR__ . '/contacts';
        $id   = preg_replace('/[^A-Z0-9\-]/', '', strtoupper($_POST['id'] ?? ''));
        $file = $contactsDir . '/' . $id . '.json';
        if ($action === 'contact_status' && file_exists($file)) {
            $c = json_decode(file_get_contents($file), true);
            $allowed = ['new', 'replied', 'resolved'];
            $c['status'] = in_array($_POST['status'], $allowed) ? $_POST['status'] : $c['status'];
            file_put_contents($file, json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            echo json_encode(['success' => true]);
        } elseif ($action === 'contact_delete' && file_exists($file)) {
            unlink($file); echo json_encode(['success' => true]);
        } else { http_response_code(400); echo json_encode(['error' => 'Not found.']); }

    // ── Orders ──
    } elseif ($action === 'order_create') {
        $ordersDir = __DIR__ . '/orders';
        if (!is_dir($ordersDir)) mkdir($ordersDir, 0755, true);
        $htaccess = $ordersDir . '/.htaccess';
        if (!file_exists($htaccess)) file_put_contents($htaccess, "Deny from all\n");
        $year  = date('Y');
        $count = count(glob($ordersDir . '/MT-*.json')) + 1;
        $orderId = 'MT-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
        $order = [
            'id'           => $orderId,
            'date'         => date('c'),
            'status'       => 'new_order',
            'customer'     => strip_tags(trim($_POST['customer'] ?? '')),
            'email'        => strip_tags(trim($_POST['email'] ?? '')),
            'garment'      => strip_tags(trim($_POST['garment'] ?? '')),
            'print_method' => strip_tags(trim($_POST['print_method'] ?? '')),
            'qty'          => (int)($_POST['qty'] ?? 0),
            'price'        => strip_tags(trim($_POST['price'] ?? '')),
            'notes'        => strip_tags(trim($_POST['notes'] ?? '')),
        ];
        file_put_contents($ordersDir . '/' . $orderId . '.json',
            json_encode($order, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo json_encode(['success' => true, 'id' => $orderId]);

    } elseif ($action === 'order_status') {
        $ordersDir = __DIR__ . '/orders';
        $id   = preg_replace('/[^A-Z0-9\-]/', '', strtoupper($_POST['id'] ?? ''));
        $file = $ordersDir . '/' . $id . '.json';
        if (file_exists($file)) {
            $o = json_decode(file_get_contents($file), true);
            $allowed = ['new_order','artwork_approval','ready_to_print','shipped'];
            $o['status'] = in_array($_POST['status'], $allowed) ? $_POST['status'] : $o['status'];
            if (!empty($_POST['tracking'])) $o['tracking'] = strip_tags(trim($_POST['tracking']));
            file_put_contents($file, json_encode($o, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            echo json_encode(['success' => true]);
        } else { http_response_code(400); echo json_encode(['error' => 'Order not found.']); }

    } elseif ($action === 'order_delete') {
        $ordersDir = __DIR__ . '/orders';
        $id   = preg_replace('/[^A-Z0-9\-]/', '', strtoupper($_POST['id'] ?? ''));
        $file = $ordersDir . '/' . $id . '.json';
        if (file_exists($file)) { unlink($file); echo json_encode(['success' => true]); }
        else { http_response_code(400); echo json_encode(['error' => 'Not found.']); }

    } else {
        http_response_code(400); echo json_encode(['error' => 'Unknown action.']);
    }
    exit;
}

// --- Load analytics ---
$analytics = ['today'=>0,'week'=>0,'month'=>0,'total'=>0,'pages'=>[],'days7'=>[],'recent'=>[],'uniques'=>[],'mobile'=>0,'desktop'=>0];
$visitsDir = __DIR__ . '/visits';
if ($isAuth && is_dir($visitsDir)) {
    $today     = date('Y-m-d');
    $weekAgo   = date('Y-m-d', strtotime('-6 days'));
    $thisMonth = date('Y-m');
    // Build 7-day buckets
    for ($i = 6; $i >= 0; $i--) {
        $analytics['days7'][date('Y-m-d', strtotime("-$i days"))] = 0;
    }
    // Read current + previous month files (covers week boundary)
    $files = array_filter([
        $visitsDir . '/' . date('Y-m', strtotime('-1 month')) . '.jsonl',
        $visitsDir . '/' . $thisMonth . '.jsonl',
    ], 'file_exists');
    foreach ($files as $f) {
        $lines = file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $v = json_decode($line, true);
            if (!$v) continue;
            $d = substr($v['t'] ?? '', 0, 10);
            $analytics['total']++;
            $analytics['uniques'][$v['ih'] ?? ''] = true;
            if ($d === $today)   $analytics['today']++;
            if ($d >= $weekAgo)  $analytics['week']++;
            if (substr($d,0,7) === $thisMonth) $analytics['month']++;
            if (isset($analytics['days7'][$d])) $analytics['days7'][$d]++;
            $p = $v['p'] ?? 'unknown';
            $analytics['pages'][$p] = ($analytics['pages'][$p] ?? 0) + 1;
            if (($v['dv'] ?? 'd') === 'm') $analytics['mobile']++; else $analytics['desktop']++;
            // Keep last 15 recent visits
            if (count($analytics['recent']) < 15) $analytics['recent'][] = $v;
        }
    }
    arsort($analytics['pages']);
    $analytics['uniques'] = count($analytics['uniques']);
}

// --- Load quotes ---
$quotes = [];
$quotesDir = __DIR__ . '/quotes';
if ($isAuth && is_dir($quotesDir)) {
    foreach (glob($quotesDir . '/*.json') as $f) {
        $q = json_decode(file_get_contents($f), true);
        if ($q) $quotes[] = $q;
    }
    usort($quotes, fn($a, $b) => strcmp($b['date'], $a['date']));
}

$countNew       = count(array_filter($quotes, fn($q) => ($q['status'] ?? '') === 'new'));
$countContacted = count(array_filter($quotes, fn($q) => ($q['status'] ?? '') === 'contacted'));
$countCompleted = count(array_filter($quotes, fn($q) => ($q['status'] ?? '') === 'completed'));

// --- Load contacts ---
$contacts = [];
$contactsDir = __DIR__ . '/contacts';
if ($isAuth && is_dir($contactsDir)) {
    foreach (glob($contactsDir . '/*.json') as $f) {
        $c = json_decode(file_get_contents($f), true);
        if ($c) $contacts[] = $c;
    }
    usort($contacts, fn($a, $b) => strcmp($b['date'], $a['date']));
}
$cntContactNew      = count(array_filter($contacts, fn($c) => ($c['status'] ?? '') === 'new'));
$cntContactReplied  = count(array_filter($contacts, fn($c) => ($c['status'] ?? '') === 'replied'));
$cntContactResolved = count(array_filter($contacts, fn($c) => ($c['status'] ?? '') === 'resolved'));

// --- Load orders ---
$orders = [];
$ordersDir = __DIR__ . '/orders';
if ($isAuth && is_dir($ordersDir)) {
    foreach (glob($ordersDir . '/*.json') as $f) {
        $o = json_decode(file_get_contents($f), true);
        if ($o) $orders[] = $o;
    }
    usort($orders, fn($a, $b) => strcmp($b['date'], $a['date']));
}
$oNew      = array_values(array_filter($orders, fn($o) => ($o['status'] ?? '') === 'new_order'));
$oArtwork  = array_values(array_filter($orders, fn($o) => ($o['status'] ?? '') === 'artwork_approval'));
$oPrint    = array_values(array_filter($orders, fn($o) => ($o['status'] ?? '') === 'ready_to_print'));
$oShipped  = array_values(array_filter($orders, fn($o) => ($o['status'] ?? '') === 'shipped'));

function statusLabel(string $s): string {
    return match($s) {
        'contacted' => 'Contacted',
        'completed' => 'Completed',
        default     => 'New',
    };
}
function statusColor(string $s): string {
    return match($s) {
        'contacted' => '#4A90D9',
        'completed' => '#4CAF50',
        default     => '#D49848',
    };
}
function contactStatusLabel(string $s): string {
    return match($s) { 'replied'=>'Replied', 'resolved'=>'Resolved', default=>'New' };
}
function contactStatusColor(string $s): string {
    return match($s) { 'replied'=>'#4A90D9', 'resolved'=>'#4CAF50', default=>'#D49848' };
}
function orderStatusLabel(string $s): string {
    return match($s) {
        'artwork_approval' => 'Artwork Approval',
        'ready_to_print'   => 'Ready to Print',
        'shipped'          => 'Shipped',
        default            => 'New Order',
    };
}
function orderStatusColor(string $s): string {
    return match($s) {
        'artwork_approval' => '#F5A623',
        'ready_to_print'   => '#4A90D9',
        'shipped'          => '#4CAF50',
        default            => '#D49848',
    };
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Admin Dashboard · Vistec GraphX</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
:root{--black:#0a0a0a;--dark:#111;--dark2:#1a1a1a;--dark3:#222;--dark4:#2a2a2a;--gold:#D49848;--gold-light:#E8B96A;--gold-dim:#9A6B2E;--white:#fff;--off:#F0EDE8;--gray:#888;--gray2:#555;--red:#e05555;--blue:#4A90D9;--green:#4CAF50;}
body{background:var(--black);color:var(--off);font-family:'DM Sans',sans-serif;min-height:100vh;}

/* NAV */
nav{background:var(--dark);border-bottom:1px solid rgba(212,152,72,0.2);padding:0 32px;display:flex;align-items:center;justify-content:space-between;height:64px;}
.nav-brand{font-family:'Bebas Neue',sans-serif;font-size:22px;letter-spacing:2px;color:var(--gold);}
.nav-brand span{color:var(--gray);font-size:13px;letter-spacing:1px;margin-left:10px;font-family:'DM Sans',sans-serif;text-transform:uppercase;}
.nav-logout{color:var(--gray);font-size:12px;text-decoration:none;letter-spacing:1px;text-transform:uppercase;transition:color 0.2s;}
.nav-logout:hover{color:var(--gold);}
.nav-site{color:var(--gray2);font-size:12px;text-decoration:none;letter-spacing:1px;text-transform:uppercase;margin-right:20px;transition:color 0.2s;}
.nav-site:hover{color:var(--off);}

/* LOGIN */
.login-wrap{max-width:380px;margin:120px auto;padding:0 24px;}
.login-title{font-family:'Bebas Neue',sans-serif;font-size:40px;letter-spacing:2px;color:var(--white);margin-bottom:6px;}
.login-sub{font-size:13px;color:var(--gray);margin-bottom:32px;}
.login-err{background:rgba(224,85,85,0.12);border:1px solid rgba(224,85,85,0.4);border-radius:3px;padding:10px 14px;font-size:13px;color:var(--red);margin-bottom:16px;}
.login-field{display:flex;flex-direction:column;gap:6px;margin-bottom:16px;}
.login-field label{font-size:10px;letter-spacing:2px;text-transform:uppercase;color:var(--gray);}
.login-field input{background:var(--dark2);border:1px solid rgba(255,255,255,0.1);color:var(--off);padding:12px 14px;border-radius:3px;font-size:15px;font-family:'DM Sans',sans-serif;outline:none;transition:border-color 0.2s;}
.login-field input:focus{border-color:var(--gold-dim);}
.login-btn{width:100%;background:var(--gold);color:var(--black);border:none;padding:14px;font-size:13px;font-weight:700;letter-spacing:2px;text-transform:uppercase;cursor:pointer;border-radius:3px;font-family:'DM Sans',sans-serif;transition:background 0.2s;margin-top:4px;}
.login-btn:hover{background:var(--gold-light);}

/* TABS */
.main-tabs{display:flex;gap:0;border-bottom:1px solid rgba(255,255,255,0.08);margin-bottom:32px;overflow-x:auto;}
.main-tab{background:none;border:none;color:var(--gray);padding:14px 24px;font-size:12px;font-weight:600;letter-spacing:1.5px;text-transform:uppercase;cursor:pointer;font-family:'DM Sans',sans-serif;border-bottom:2px solid transparent;white-space:nowrap;transition:all 0.2s;}
.main-tab:hover{color:var(--off);}
.main-tab.active{color:var(--gold);border-bottom-color:var(--gold);}
.tab-badge{background:rgba(212,152,72,0.15);color:var(--gold);font-size:10px;padding:2px 7px;border-radius:10px;margin-left:6px;font-weight:700;}
.tab-panel{display:none;}
.tab-panel.active{display:block;}

/* DASHBOARD */
.dash{max-width:1400px;margin:0 auto;padding:40px 32px;}
@media(max-width:700px){.dash{padding:24px 16px;}}
.dash-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:32px;flex-wrap:wrap;gap:16px;}
.dash-title{font-family:'Bebas Neue',sans-serif;font-size:36px;letter-spacing:2px;color:var(--white);}
.stats{display:flex;gap:16px;flex-wrap:wrap;}
.stat{background:var(--dark2);border:1px solid rgba(255,255,255,0.07);border-radius:4px;padding:12px 20px;text-align:center;min-width:90px;}
.stat-num{font-family:'Bebas Neue',sans-serif;font-size:28px;letter-spacing:1px;}
.stat-lbl{font-size:10px;letter-spacing:1.5px;text-transform:uppercase;color:var(--gray);margin-top:2px;}

/* KANBAN */
.kanban{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;align-items:start;}
@media(max-width:900px){.kanban{grid-template-columns:repeat(2,1fr);}}
@media(max-width:560px){.kanban{grid-template-columns:1fr;}}
.kanban-col{background:var(--dark2);border:1px solid rgba(255,255,255,0.07);border-radius:4px;overflow:hidden;}
.kanban-head{padding:14px 16px;border-bottom:1px solid rgba(255,255,255,0.07);display:flex;align-items:center;justify-content:space-between;}
.kanban-head-title{font-size:11px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;}
.kanban-count{font-family:'Bebas Neue',sans-serif;font-size:20px;letter-spacing:1px;}
.kanban-body{padding:12px;display:flex;flex-direction:column;gap:10px;min-height:80px;}
.order-card{background:var(--dark3);border:1px solid rgba(255,255,255,0.08);border-radius:3px;padding:14px;}
.order-card-id{font-size:10px;font-family:monospace;color:var(--gold);margin-bottom:6px;}
.order-card-name{font-size:14px;font-weight:600;color:var(--off);margin-bottom:4px;}
.order-card-meta{font-size:11px;color:var(--gray);margin-bottom:10px;line-height:1.6;}
.order-card-actions{display:flex;gap:6px;flex-wrap:wrap;}
.oc-btn{font-size:10px;font-weight:700;letter-spacing:0.8px;text-transform:uppercase;padding:5px 10px;border-radius:2px;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all 0.15s;}
.oc-adv{background:rgba(212,152,72,0.15);color:var(--gold);border:1px solid rgba(212,152,72,0.3);}
.oc-adv:hover{background:rgba(212,152,72,0.25);}
.oc-del{background:rgba(224,85,85,0.1);color:var(--red);border:1px solid rgba(224,85,85,0.2);margin-left:auto;}
.oc-del:hover{background:rgba(224,85,85,0.2);}

/* NEW ORDER MODAL */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:300;align-items:center;justify-content:center;}
.modal-overlay.open{display:flex;}
.modal{background:var(--dark2);border:1px solid rgba(212,152,72,0.2);border-radius:4px;padding:32px;width:100%;max-width:500px;max-height:90vh;overflow-y:auto;}
.modal-title{font-family:'Bebas Neue',sans-serif;font-size:28px;letter-spacing:2px;color:var(--off);margin-bottom:24px;}
.modal-field{margin-bottom:14px;}
.modal-field label{display:block;font-size:10px;letter-spacing:2px;text-transform:uppercase;color:var(--gray);margin-bottom:6px;}
.modal-field input,.modal-field select,.modal-field textarea{width:100%;background:var(--dark3);border:1px solid rgba(255,255,255,0.1);color:var(--off);padding:10px 12px;border-radius:3px;font-size:13px;font-family:'DM Sans',sans-serif;outline:none;}
.modal-field input:focus,.modal-field select:focus,.modal-field textarea:focus{border-color:var(--gold-dim);}
.modal-field select option{background:var(--dark2);}
.modal-field textarea{resize:vertical;min-height:70px;}
.modal-btns{display:flex;gap:10px;margin-top:20px;}
.modal-submit{flex:1;background:var(--gold);color:var(--black);border:none;padding:12px;font-size:12px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;cursor:pointer;border-radius:3px;font-family:'DM Sans',sans-serif;}
.modal-submit:hover{background:var(--gold-light);}
.modal-cancel{background:transparent;border:1px solid rgba(255,255,255,0.1);color:var(--gray);padding:12px 20px;border-radius:3px;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:12px;}

/* FILTERS */
.filters{display:flex;gap:8px;margin-bottom:24px;flex-wrap:wrap;}
.filter-btn{background:var(--dark2);border:1px solid rgba(255,255,255,0.1);color:var(--gray);padding:7px 16px;border-radius:3px;font-size:12px;letter-spacing:1px;text-transform:uppercase;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all 0.2s;}
.filter-btn:hover,.filter-btn.active{background:var(--gold);color:var(--black);border-color:var(--gold);}

/* TABLE */
.table-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
thead th{font-size:10px;letter-spacing:2px;text-transform:uppercase;color:var(--gray);padding:10px 14px;text-align:left;border-bottom:1px solid rgba(255,255,255,0.08);white-space:nowrap;}
tbody tr{border-bottom:1px solid rgba(255,255,255,0.05);cursor:pointer;transition:background 0.15s;}
tbody tr:hover{background:rgba(255,255,255,0.03);}
tbody tr.expanded{background:rgba(212,152,72,0.04);}
td{padding:13px 14px;font-size:13px;vertical-align:top;}
td.date-col{color:var(--gray);font-size:12px;white-space:nowrap;}
td.name-col{font-weight:500;color:var(--white);}
td.email-col{color:var(--gray);font-size:12px;}
.status-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;letter-spacing:0.8px;text-transform:uppercase;}
.expand-row{display:none;}
.expand-row td{padding:0;}
.expand-inner{padding:20px 14px 24px;background:var(--dark2);border-radius:3px;display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;margin-bottom:12px;}
.detail-field label{font-size:10px;letter-spacing:1.5px;text-transform:uppercase;color:var(--gray);display:block;margin-bottom:4px;}
.detail-field p{font-size:13px;color:var(--off);line-height:1.5;}
.detail-notes{grid-column:1/-1;}
.row-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:0 14px 20px;background:var(--dark2);}
.status-select{background:var(--dark3);border:1px solid rgba(255,255,255,0.1);color:var(--off);padding:7px 10px;border-radius:3px;font-size:12px;font-family:'DM Sans',sans-serif;cursor:pointer;outline:none;}
.status-select:focus{border-color:var(--gold-dim);}
.act-btn{padding:7px 16px;border-radius:3px;font-size:12px;font-weight:600;letter-spacing:1px;text-transform:uppercase;cursor:pointer;border:none;font-family:'DM Sans',sans-serif;transition:all 0.2s;}
.btn-save{background:var(--gold);color:var(--black);}
.btn-save:hover{background:var(--gold-light);}
.btn-email{background:transparent;border:1px solid rgba(74,144,217,0.5);color:var(--blue);}
.btn-email:hover{background:rgba(74,144,217,0.1);}
.btn-del{background:transparent;border:1px solid rgba(224,85,85,0.4);color:var(--red);margin-left:auto;}
.btn-del:hover{background:rgba(224,85,85,0.1);}

/* EMPTY */
.empty{text-align:center;padding:80px 20px;color:var(--gray);}
.empty svg{opacity:0.2;margin-bottom:16px;}
.empty h3{font-family:'Bebas Neue',sans-serif;font-size:28px;letter-spacing:2px;color:var(--gray2);margin-bottom:8px;}

/* ANALYTICS */
.section-divider{border:none;border-top:1px solid rgba(255,255,255,0.07);margin:36px 0;}
.section-title{font-family:'Bebas Neue',sans-serif;font-size:28px;letter-spacing:2px;color:var(--white);margin-bottom:20px;}
.analytics-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:28px;}
@media(max-width:700px){.analytics-grid{grid-template-columns:1fr;}}
.analytics-card{background:var(--dark2);border:1px solid rgba(255,255,255,0.07);border-radius:4px;padding:20px 24px;}
.analytics-card h4{font-size:10px;letter-spacing:2px;text-transform:uppercase;color:var(--gray);margin-bottom:16px;}
.big-stats{display:flex;gap:20px;flex-wrap:wrap;}
.big-stat{flex:1;min-width:80px;}
.big-stat-num{font-family:'Bebas Neue',sans-serif;font-size:36px;letter-spacing:1px;line-height:1;}
.big-stat-lbl{font-size:10px;letter-spacing:1.5px;text-transform:uppercase;color:var(--gray);margin-top:4px;}
/* 7-day chart */
.chart{display:flex;align-items:flex-end;gap:6px;height:80px;}
.chart-col{display:flex;flex-direction:column;align-items:center;gap:4px;flex:1;}
.chart-bar{width:100%;background:var(--gold-dim);border-radius:2px 2px 0 0;min-height:2px;transition:background 0.15s;}
.chart-bar:hover{background:var(--gold);}
.chart-day{font-size:9px;color:var(--gray);letter-spacing:0.5px;text-transform:uppercase;}
.chart-num{font-size:9px;color:var(--gray);margin-top:2px;}
/* Top pages */
.page-bar-row{display:flex;align-items:center;gap:10px;margin-bottom:10px;}
.page-bar-label{font-size:12px;color:var(--off);width:100px;flex-shrink:0;text-transform:capitalize;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.page-bar-track{flex:1;background:var(--dark3);border-radius:2px;height:8px;overflow:hidden;}
.page-bar-fill{height:100%;background:var(--gold-dim);border-radius:2px;}
.page-bar-count{font-size:11px;color:var(--gray);width:36px;text-align:right;flex-shrink:0;}
/* Device split */
.device-row{display:flex;gap:20px;}
.device-item{display:flex;align-items:center;gap:8px;}
.device-dot{width:8px;height:8px;border-radius:50%;}
.device-lbl{font-size:12px;color:var(--gray);}
.device-val{font-size:13px;color:var(--off);font-weight:600;}
/* Recent visits */
.recent-table{width:100%;border-collapse:collapse;font-size:12px;}
.recent-table th{color:var(--gray);font-size:10px;letter-spacing:1.5px;text-transform:uppercase;padding:6px 8px;text-align:left;border-bottom:1px solid rgba(255,255,255,0.07);}
.recent-table td{padding:7px 8px;border-bottom:1px solid rgba(255,255,255,0.04);color:var(--off);}
.recent-table td.dim{color:var(--gray);}
.badge-m{display:inline-block;background:rgba(74,144,217,0.15);color:#4A90D9;font-size:9px;padding:1px 6px;border-radius:10px;letter-spacing:0.5px;}
.badge-d{display:inline-block;background:rgba(212,152,72,0.12);color:var(--gold-dim);font-size:9px;padding:1px 6px;border-radius:10px;letter-spacing:0.5px;}
</style>
</head>
<body>

<nav>
  <div class="nav-brand">Vistec GraphX <span>Admin</span></div>
  <?php if ($isAuth): ?>
  <div style="display:flex;align-items:center;">
    <a href="index.html" class="nav-site" target="_blank">← View Site</a>
    <a href="admin.php?logout=1" class="nav-logout">Log Out</a>
  </div>
  <?php endif; ?>
</nav>

<?php if (!$isAuth): ?>
<!-- LOGIN -->
<div class="login-wrap">
  <div class="login-title">Admin Login</div>
  <div class="login-sub">Vistec GraphX admin dashboard.</div>
  <?php if ($loginError): ?>
  <div class="login-err"><?= htmlspecialchars($loginError) ?></div>
  <?php endif; ?>
  <form method="POST">
    <div class="login-field">
      <label>Password</label>
      <input type="password" name="password" autofocus autocomplete="current-password"/>
    </div>
    <button class="login-btn" type="submit">Sign In</button>
  </form>
</div>

<?php else: ?>
<!-- DASHBOARD -->
<div class="dash">

  <!-- ── MAIN TABS ── -->
  <div class="main-tabs">
    <button class="main-tab active" onclick="showTab('analytics',this)">Analytics</button>
    <button class="main-tab" onclick="showTab('quotes',this)">
      Quotes <?php if($countNew>0): ?><span class="tab-badge"><?= $countNew ?></span><?php endif; ?>
    </button>
    <button class="main-tab" onclick="showTab('contacts',this)">
      Contacts <?php if($cntContactNew>0): ?><span class="tab-badge"><?= $cntContactNew ?></span><?php endif; ?>
    </button>
    <button class="main-tab" onclick="showTab('orders',this)">
      DTF Orders <?php if(count($oNew)>0): ?><span class="tab-badge"><?= count($oNew) ?></span><?php endif; ?>
    </button>
  </div>

  <!-- ── TAB: ANALYTICS ── -->
  <div class="tab-panel active" id="tab-analytics">
  <div class="section-title">Site Analytics</div>
  <?php
    $max7 = max(array_values($analytics['days7'])) ?: 1;
    $topPages = array_slice($analytics['pages'], 0, 5, true);
    $maxPage  = $topPages ? max(array_values($topPages)) : 1;
    $totalDev = max(1, $analytics['mobile'] + $analytics['desktop']);
    $mobPct   = round($analytics['mobile'] / $totalDev * 100);
    $dskPct   = 100 - $mobPct;
    $pageLabels = ['home'=>'Home','products'=>'Products','designs'=>'Designs','designer'=>'Designer','checkout'=>'Checkout','unknown'=>'Other'];
  ?>
  <div class="analytics-grid">

    <!-- Visitor counts -->
    <div class="analytics-card">
      <h4>Visitors</h4>
      <div class="big-stats">
        <div class="big-stat">
          <div class="big-stat-num" style="color:var(--gold)"><?= number_format($analytics['today']) ?></div>
          <div class="big-stat-lbl">Today</div>
        </div>
        <div class="big-stat">
          <div class="big-stat-num" style="color:#4A90D9"><?= number_format($analytics['week']) ?></div>
          <div class="big-stat-lbl">7 Days</div>
        </div>
        <div class="big-stat">
          <div class="big-stat-num" style="color:#4CAF50"><?= number_format($analytics['month']) ?></div>
          <div class="big-stat-lbl">This Month</div>
        </div>
        <div class="big-stat">
          <div class="big-stat-num" style="color:var(--off)"><?= number_format($analytics['total']) ?></div>
          <div class="big-stat-lbl">All Time</div>
        </div>
      </div>
      <div style="margin-top:16px;padding-top:14px;border-top:1px solid rgba(255,255,255,0.06);">
        <div class="device-row">
          <div class="device-item">
            <div class="device-dot" style="background:#4A90D9"></div>
            <span class="device-lbl">Mobile</span>
            <span class="device-val"><?= $mobPct ?>%</span>
          </div>
          <div class="device-item">
            <div class="device-dot" style="background:var(--gold-dim)"></div>
            <span class="device-lbl">Desktop</span>
            <span class="device-val"><?= $dskPct ?>%</span>
          </div>
          <div class="device-item" style="margin-left:auto">
            <span class="device-lbl">Unique visitors (today)</span>
            <span class="device-val" style="margin-left:8px"><?= number_format($analytics['uniques']) ?></span>
          </div>
        </div>
      </div>
    </div>

    <!-- 7-day chart -->
    <div class="analytics-card">
      <h4>Last 7 Days</h4>
      <div class="chart">
        <?php foreach ($analytics['days7'] as $d => $cnt):
          $h = max(2, round($cnt / $max7 * 72));
        ?>
        <div class="chart-col">
          <div class="chart-num"><?= $cnt ?: '' ?></div>
          <div class="chart-bar" style="height:<?= $h ?>px" title="<?= $d ?>: <?= $cnt ?> visits"></div>
          <div class="chart-day"><?= date('D', strtotime($d)) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Top pages -->
    <div class="analytics-card">
      <h4>Top Pages</h4>
      <?php if (empty($topPages)): ?>
        <p style="color:var(--gray);font-size:13px;">No data yet.</p>
      <?php else: foreach ($topPages as $pg => $cnt):
        $pct = round($cnt / $maxPage * 100);
        $lbl = $pageLabels[$pg] ?? ucfirst($pg);
      ?>
      <div class="page-bar-row">
        <div class="page-bar-label"><?= htmlspecialchars($lbl) ?></div>
        <div class="page-bar-track"><div class="page-bar-fill" style="width:<?= $pct ?>%"></div></div>
        <div class="page-bar-count"><?= number_format($cnt) ?></div>
      </div>
      <?php endforeach; endif; ?>
    </div>

    <!-- Recent visits -->
    <div class="analytics-card">
      <h4>Recent Visits</h4>
      <?php if (empty($analytics['recent'])): ?>
        <p style="color:var(--gray);font-size:13px;">No visits recorded yet.</p>
      <?php else: ?>
      <table class="recent-table">
        <thead><tr><th>Time</th><th>Page</th><th>Referrer</th><th>Device</th></tr></thead>
        <tbody>
        <?php foreach (array_reverse($analytics['recent']) as $v):
          $lbl = $pageLabels[$v['p'] ?? ''] ?? ucfirst($v['p'] ?? '?');
        ?>
        <tr>
          <td class="dim"><?= htmlspecialchars(substr($v['t']??'',11,5)) ?></td>
          <td><?= htmlspecialchars($lbl) ?></td>
          <td class="dim"><?= htmlspecialchars($v['r'] ? $v['r'] : '—') ?></td>
          <td><?php if(($v['dv']??'d')==='m'): ?><span class="badge-m">Mobile</span><?php else: ?><span class="badge-d">Desktop</span><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

  </div>
  </div><!-- /tab-analytics -->

  <!-- ── TAB: QUOTES ── -->
  <div class="tab-panel" id="tab-quotes">
  <div class="dash-header">
    <div>
      <div class="dash-title">Quote Requests</div>
      <div style="font-size:13px;color:var(--gray);margin-top:4px;"><?= count($quotes) ?> total</div>
    </div>
    <div class="stats">
      <div class="stat">
        <div class="stat-num" style="color:var(--gold);"><?= $countNew ?></div>
        <div class="stat-lbl">New</div>
      </div>
      <div class="stat">
        <div class="stat-num" style="color:var(--blue);"><?= $countContacted ?></div>
        <div class="stat-lbl">Contacted</div>
      </div>
      <div class="stat">
        <div class="stat-num" style="color:var(--green);"><?= $countCompleted ?></div>
        <div class="stat-lbl">Done</div>
      </div>
    </div>
  </div>

  <div class="filters">
    <button class="filter-btn active" onclick="filterQuotes('all', this)">All (<?= count($quotes) ?>)</button>
    <button class="filter-btn" onclick="filterQuotes('new', this)">New (<?= $countNew ?>)</button>
    <button class="filter-btn" onclick="filterQuotes('contacted', this)">Contacted (<?= $countContacted ?>)</button>
    <button class="filter-btn" onclick="filterQuotes('completed', this)">Completed (<?= $countCompleted ?>)</button>
  </div>

  <?php if (empty($quotes)): ?>
  <div class="empty">
    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
    <h3>No Quote Requests Yet</h3>
    <p>Submissions will appear here once customers fill out the quote form.</p>
  </div>
  <?php else: ?>
  <div class="table-wrap">
    <table id="quotesTable">
      <thead>
        <tr>
          <th>Date</th>
          <th>Name</th>
          <th>Email</th>
          <th>Item</th>
          <th>Qty</th>
          <th>Budget</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($quotes as $q):
          $id      = htmlspecialchars($q['id']      ?? '');
          $status  = $q['status'] ?? 'new';
          $date    = date('M j, Y g:ia', strtotime($q['date'] ?? 'now'));
          $color   = statusColor($status);
          $label   = statusLabel($status);
        ?>
        <tr data-id="<?= $id ?>" data-status="<?= htmlspecialchars($status) ?>" onclick="toggleRow(this)">
          <td class="date-col"><?= $date ?></td>
          <td class="name-col"><?= htmlspecialchars($q['name']  ?? '') ?></td>
          <td class="email-col"><?= htmlspecialchars($q['email'] ?? '') ?></td>
          <td><?= htmlspecialchars($q['product'] ?: '—') ?></td>
          <td><?= htmlspecialchars($q['qty']     ?: '—') ?></td>
          <td><?= htmlspecialchars($q['budget']  ?: '—') ?></td>
          <td>
            <span class="status-badge" style="background:<?= $color ?>22;color:<?= $color ?>;border:1px solid <?= $color ?>44;">
              <?= $label ?>
            </span>
          </td>
        </tr>
        <tr class="expand-row" id="exp-<?= $id ?>">
          <td colspan="7">
            <div class="expand-inner">
              <div class="detail-field">
                <label>Quote ID</label>
                <p style="font-family:monospace;color:var(--gold);"><?= $id ?></p>
              </div>
              <div class="detail-field">
                <label>Phone</label>
                <p><?= htmlspecialchars($q['phone'] ?: '—') ?></p>
              </div>
              <div class="detail-field">
                <label>Item Type</label>
                <p><?= htmlspecialchars($q['product'] ?: '—') ?></p>
              </div>
              <div class="detail-field">
                <label>Quantity</label>
                <p><?= htmlspecialchars($q['qty'] ?: '—') ?></p>
              </div>
              <div class="detail-field">
                <label>Budget</label>
                <p><?= htmlspecialchars($q['budget'] ?: '—') ?></p>
              </div>
              <div class="detail-field">
                <label>Submitted</label>
                <p><?= htmlspecialchars($date) ?></p>
              </div>
              <?php if (!empty($q['notes'])): ?>
              <div class="detail-field detail-notes">
                <label>Notes / Details</label>
                <p style="white-space:pre-wrap;"><?= htmlspecialchars($q['notes']) ?></p>
              </div>
              <?php endif; ?>
            </div>
            <div class="row-actions">
              <select class="status-select" id="sel-<?= $id ?>">
                <option value="new"       <?= $status === 'new'       ? 'selected' : '' ?>>New</option>
                <option value="contacted" <?= $status === 'contacted' ? 'selected' : '' ?>>Contacted</option>
                <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
              </select>
              <button class="act-btn btn-save" onclick="saveStatus('<?= $id ?>')">Save Status</button>
              <a class="act-btn btn-email"
                 href="mailto:<?= htmlspecialchars($q['email']) ?>?subject=Re: Your Quote Request (<?= $id ?>)"
                 onclick="event.stopPropagation()">
                Reply by Email
              </a>
              <button class="act-btn btn-del" onclick="deleteQuote('<?= $id ?>', event)">Delete</button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
  </div><!-- /tab-quotes -->

  <!-- ── TAB: CONTACTS ── -->
  <div class="tab-panel" id="tab-contacts">
  <div class="dash-header">
    <div>
      <div class="dash-title">Contact Forms</div>
      <div style="font-size:13px;color:var(--gray);margin-top:4px;"><?= count($contacts) ?> total</div>
    </div>
    <div class="stats">
      <div class="stat"><div class="stat-num" style="color:var(--gold);"><?= $cntContactNew ?></div><div class="stat-lbl">New</div></div>
      <div class="stat"><div class="stat-num" style="color:var(--blue);"><?= $cntContactReplied ?></div><div class="stat-lbl">Replied</div></div>
      <div class="stat"><div class="stat-num" style="color:var(--green);"><?= $cntContactResolved ?></div><div class="stat-lbl">Resolved</div></div>
    </div>
  </div>
  <div class="filters">
    <button class="filter-btn active" onclick="filterContacts('all',this)">All (<?= count($contacts) ?>)</button>
    <button class="filter-btn" onclick="filterContacts('new',this)">New (<?= $cntContactNew ?>)</button>
    <button class="filter-btn" onclick="filterContacts('replied',this)">Replied (<?= $cntContactReplied ?>)</button>
    <button class="filter-btn" onclick="filterContacts('resolved',this)">Resolved (<?= $cntContactResolved ?>)</button>
  </div>
  <?php if (empty($contacts)): ?>
  <div class="empty">
    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
    <h3>No Contact Forms Yet</h3>
    <p>Submissions from contact.html will appear here.</p>
  </div>
  <?php else: ?>
  <div class="table-wrap">
    <table id="contactsTable">
      <thead><tr><th>Date</th><th>Name</th><th>Email</th><th>Inquiry</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($contacts as $c):
        $cid    = htmlspecialchars($c['id'] ?? '');
        $cst    = $c['status'] ?? 'new';
        $cdate  = date('M j, Y g:ia', strtotime($c['date'] ?? 'now'));
        $ccolor = contactStatusColor($cst);
        $clabel = contactStatusLabel($cst);
      ?>
      <tr data-id="<?= $cid ?>" data-status="<?= htmlspecialchars($cst) ?>" onclick="toggleContactRow(this)">
        <td class="date-col"><?= $cdate ?></td>
        <td class="name-col"><?= htmlspecialchars($c['name'] ?? '') ?></td>
        <td class="email-col"><?= htmlspecialchars($c['email'] ?? '') ?></td>
        <td><?= htmlspecialchars($c['inquiry'] ?? '—') ?></td>
        <td><span class="status-badge" style="background:<?= $ccolor ?>22;color:<?= $ccolor ?>;border:1px solid <?= $ccolor ?>44;"><?= $clabel ?></span></td>
      </tr>
      <tr class="expand-row" id="cexp-<?= $cid ?>">
        <td colspan="5">
          <div class="expand-inner">
            <div class="detail-field"><label>Contact ID</label><p style="font-family:monospace;color:var(--gold);"><?= $cid ?></p></div>
            <div class="detail-field"><label>Phone</label><p><?= htmlspecialchars($c['phone'] ?? '—') ?></p></div>
            <div class="detail-field"><label>Inquiry Type</label><p><?= htmlspecialchars($c['inquiry'] ?? '—') ?></p></div>
            <div class="detail-field"><label>Submitted</label><p><?= htmlspecialchars($cdate) ?></p></div>
            <?php if (!empty($c['message'])): ?>
            <div class="detail-field detail-notes"><label>Message</label><p style="white-space:pre-wrap;"><?= htmlspecialchars($c['message']) ?></p></div>
            <?php endif; ?>
          </div>
          <div class="row-actions">
            <select class="status-select" id="csel-<?= $cid ?>">
              <option value="new"      <?= $cst === 'new'      ? 'selected' : '' ?>>New</option>
              <option value="replied"  <?= $cst === 'replied'  ? 'selected' : '' ?>>Replied</option>
              <option value="resolved" <?= $cst === 'resolved' ? 'selected' : '' ?>>Resolved</option>
            </select>
            <button class="act-btn btn-save" onclick="saveContactStatus('<?= $cid ?>')">Save Status</button>
            <a class="act-btn btn-email"
               href="mailto:<?= htmlspecialchars($c['email']) ?>?subject=Re: Your Inquiry (<?= $cid ?>)"
               onclick="event.stopPropagation()">Reply by Email</a>
            <button class="act-btn btn-del" onclick="deleteContact('<?= $cid ?>', event)">Delete</button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
  </div><!-- /tab-contacts -->

  <!-- ── TAB: ORDERS / DTF PIPELINE ── -->
  <div class="tab-panel" id="tab-orders">
  <div class="dash-header">
    <div>
      <div class="dash-title">DTF Orders</div>
      <div style="font-size:13px;color:var(--gray);margin-top:4px;"><?= count($orders) ?> total orders</div>
    </div>
    <button class="act-btn oc-adv" style="padding:10px 20px;font-size:12px;" onclick="document.getElementById('newOrderModal').classList.add('open')">+ New Order</button>
  </div>

  <!-- KANBAN BOARD -->
  <div class="kanban">
    <?php
    $cols = [
      ['key'=>'new_order',        'label'=>'New Orders',       'color'=>'#D49848', 'items'=>$oNew],
      ['key'=>'artwork_approval', 'label'=>'Artwork Approval', 'color'=>'#F5A623', 'items'=>$oArtwork],
      ['key'=>'ready_to_print',   'label'=>'Ready to Print',   'color'=>'#4A90D9', 'items'=>$oPrint],
      ['key'=>'shipped',          'label'=>'Shipped',          'color'=>'#4CAF50', 'items'=>$oShipped],
    ];
    $nextStatus = ['new_order'=>'artwork_approval','artwork_approval'=>'ready_to_print','ready_to_print'=>'shipped'];
    foreach ($cols as $col):
    ?>
    <div class="kanban-col">
      <div class="kanban-head">
        <span class="kanban-head-title" style="color:<?= $col['color'] ?>"><?= $col['label'] ?></span>
        <span class="kanban-count" style="color:<?= $col['color'] ?>"><?= count($col['items']) ?></span>
      </div>
      <div class="kanban-body">
        <?php if (empty($col['items'])): ?>
          <div style="color:var(--gray);font-size:12px;text-align:center;padding:16px 0;">No orders</div>
        <?php else: foreach ($col['items'] as $o):
          $oid = htmlspecialchars($o['id'] ?? '');
          $next = $nextStatus[$col['key']] ?? null;
        ?>
        <div class="order-card" id="oc-<?= $oid ?>">
          <div class="order-card-id"><?= $oid ?></div>
          <div class="order-card-name"><?= htmlspecialchars($o['customer'] ?? '') ?></div>
          <div class="order-card-meta">
            <?= htmlspecialchars($o['garment'] ?? '') ?><br>
            <?= htmlspecialchars(str_replace('_',' ',ucfirst($o['print_method'] ?? ''))) ?> · <?= (int)($o['qty'] ?? 0) ?> pcs
            <?php if (!empty($o['price'])): ?> · <?= htmlspecialchars($o['price']) ?><?php endif; ?>
            <?php if (!empty($o['email'])): ?><br><span style="font-size:10px;"><?= htmlspecialchars($o['email']) ?></span><?php endif; ?>
            <?php if (!empty($o['tracking'])): ?><br>Track: <?= htmlspecialchars($o['tracking']) ?><?php endif; ?>
          </div>
          <div class="order-card-actions">
            <?php if ($next): ?>
            <button class="oc-btn oc-adv" onclick="advanceOrder('<?= $oid ?>','<?= $next ?>')" title="Move to next stage">
              <?= $col['key'] === 'ready_to_print' ? 'Mark Shipped' : 'Advance →' ?>
            </button>
            <?php endif; ?>
            <?php if (!empty($o['email'])): ?>
            <a class="oc-btn" style="background:rgba(74,144,217,0.1);color:#4A90D9;border:1px solid rgba(74,144,217,0.2);text-decoration:none;"
               href="mailto:<?= htmlspecialchars($o['email']) ?>?subject=Order Update (<?= $oid ?>)">Email</a>
            <?php endif; ?>
            <button class="oc-btn oc-del" onclick="deleteOrder('<?= $oid ?>')">Del</button>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  </div><!-- /tab-orders -->

</div><!-- /dash -->
<?php endif; ?>

<!-- NEW ORDER MODAL -->
<div class="modal-overlay" id="newOrderModal">
  <div class="modal">
    <div class="modal-title">New DTF Order</div>
    <div class="modal-field"><label>Customer Name *</label><input type="text" id="mo_customer" placeholder="Jane Smith"/></div>
    <div class="modal-field"><label>Email</label><input type="email" id="mo_email" placeholder="jane@email.com"/></div>
    <div class="modal-field"><label>Garment</label><input type="text" id="mo_garment" placeholder="e.g. Gildan 5000 Black T-Shirt, 2XL"/></div>
    <div class="modal-field">
      <label>Print Method</label>
      <select id="mo_method">
        <option value="dtf">DTF Transfer</option>
        <option value="screen_print">Screen Print</option>
        <option value="embroidery">Embroidery</option>
        <option value="sublimation">Sublimation</option>
      </select>
    </div>
    <div class="modal-field"><label>Quantity</label><input type="number" id="mo_qty" placeholder="12" min="1"/></div>
    <div class="modal-field"><label>Price / Total</label><input type="text" id="mo_price" placeholder="e.g. $185.00"/></div>
    <div class="modal-field"><label>Notes</label><textarea id="mo_notes" placeholder="Colors, design details, deadline..."></textarea></div>
    <div class="modal-btns">
      <button class="modal-cancel" onclick="document.getElementById('newOrderModal').classList.remove('open')">Cancel</button>
      <button class="modal-submit" onclick="createOrder()">Create Order</button>
    </div>
  </div>
</div>

<script>
let currentFilter = 'all';

function toggleRow(tr) {
  const id  = tr.dataset.id;
  const exp = document.getElementById('exp-' + id);
  const open = exp.style.display === 'table-row';
  // close all
  document.querySelectorAll('.expand-row').forEach(r => r.style.display = 'none');
  document.querySelectorAll('tbody tr[data-id]').forEach(r => r.classList.remove('expanded'));
  if (!open) {
    exp.style.display = 'table-row';
    tr.classList.add('expanded');
  }
}

function filterQuotes(status, btn) {
  currentFilter = status;
  document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('tbody tr[data-id]').forEach(tr => {
    const exp = document.getElementById('exp-' + tr.dataset.id);
    const show = status === 'all' || tr.dataset.status === status;
    tr.style.display = show ? '' : 'none';
    if (exp) exp.style.display = 'none';
  });
}

async function saveStatus(id) {
  const sel    = document.getElementById('sel-' + id);
  const status = sel.value;
  const res    = await fetch('admin.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `action=status&id=${encodeURIComponent(id)}&status=${encodeURIComponent(status)}`
  });
  const data = await res.json();
  if (data.success) {
    const tr     = document.querySelector(`tr[data-id="${id}"]`);
    const colors = { new:'#D49848', contacted:'#4A90D9', completed:'#4CAF50' };
    const labels = { new:'New', contacted:'Contacted', completed:'Completed' };
    const c      = colors[status] || colors.new;
    const l      = labels[status] || 'New';
    tr.querySelector('.status-badge').textContent = l;
    tr.querySelector('.status-badge').style.cssText =
      `background:${c}22;color:${c};border:1px solid ${c}44;`;
    tr.dataset.status = status;
    filterQuotes(currentFilter, document.querySelector('.filter-btn.active'));
  }
}

async function deleteQuote(id, e) {
  e.stopPropagation();
  if (!confirm('Delete this quote request? This cannot be undone.')) return;
  const res  = await fetch('admin.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `action=delete&id=${encodeURIComponent(id)}`
  });
  const data = await res.json();
  if (data.success) {
    document.querySelector(`tr[data-id="${id}"]`).remove();
    const exp = document.getElementById('exp-' + id);
    if (exp) exp.remove();
  }
}

// ── TABS ─────────────────────────────────────────────────────────────────────
function showTab(name, btn) {
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.main-tab').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + name).classList.add('active');
  btn.classList.add('active');
}

// ── CONTACTS ─────────────────────────────────────────────────────────────────
let currentContactFilter = 'all';

function toggleContactRow(tr) {
  const id  = tr.dataset.id;
  const exp = document.getElementById('cexp-' + id);
  const open = exp.style.display === 'table-row';
  document.querySelectorAll('[id^="cexp-"]').forEach(r => r.style.display = 'none');
  document.querySelectorAll('#contactsTable tbody tr[data-id]').forEach(r => r.classList.remove('expanded'));
  if (!open) { exp.style.display = 'table-row'; tr.classList.add('expanded'); }
}

function filterContacts(status, btn) {
  currentContactFilter = status;
  document.querySelectorAll('#tab-contacts .filter-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('#contactsTable tbody tr[data-id]').forEach(tr => {
    const exp  = document.getElementById('cexp-' + tr.dataset.id);
    const show = status === 'all' || tr.dataset.status === status;
    tr.style.display = show ? '' : 'none';
    if (exp) exp.style.display = 'none';
  });
}

async function saveContactStatus(id) {
  const sel    = document.getElementById('csel-' + id);
  const status = sel.value;
  const res    = await fetch('admin.php', {
    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: `action=contact_status&id=${encodeURIComponent(id)}&status=${encodeURIComponent(status)}`
  });
  const data = await res.json();
  if (data.success) {
    const tr     = document.querySelector(`#contactsTable tr[data-id="${id}"]`);
    const colors = { new:'#D49848', replied:'#4A90D9', resolved:'#4CAF50' };
    const labels = { new:'New', replied:'Replied', resolved:'Resolved' };
    const c = colors[status] || colors.new, l = labels[status] || 'New';
    tr.querySelector('.status-badge').textContent = l;
    tr.querySelector('.status-badge').style.cssText = `background:${c}22;color:${c};border:1px solid ${c}44;`;
    tr.dataset.status = status;
    filterContacts(currentContactFilter, document.querySelector('#tab-contacts .filter-btn.active'));
  }
}

async function deleteContact(id, e) {
  e.stopPropagation();
  if (!confirm('Delete this contact submission?')) return;
  const res  = await fetch('admin.php', {
    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: `action=contact_delete&id=${encodeURIComponent(id)}`
  });
  const data = await res.json();
  if (data.success) {
    document.querySelector(`#contactsTable tr[data-id="${id}"]`)?.remove();
    document.getElementById('cexp-' + id)?.remove();
  }
}

// ── ORDERS ───────────────────────────────────────────────────────────────────
async function advanceOrder(id, nextStatus) {
  const res  = await fetch('admin.php', {
    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: `action=order_status&id=${encodeURIComponent(id)}&status=${encodeURIComponent(nextStatus)}`
  });
  const data = await res.json();
  if (data.success) location.reload();
}

async function deleteOrder(id) {
  if (!confirm('Delete order ' + id + '? This cannot be undone.')) return;
  const res  = await fetch('admin.php', {
    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: `action=order_delete&id=${encodeURIComponent(id)}`
  });
  const data = await res.json();
  if (data.success) { document.getElementById('oc-' + id)?.remove(); }
}

async function createOrder() {
  const customer = document.getElementById('mo_customer').value.trim();
  const email    = document.getElementById('mo_email').value.trim();
  const garment  = document.getElementById('mo_garment').value.trim();
  const method   = document.getElementById('mo_method').value;
  const qty      = document.getElementById('mo_qty').value;
  const price    = document.getElementById('mo_price').value.trim();
  const notes    = document.getElementById('mo_notes').value.trim();
  if (!customer) { alert('Customer name is required.'); return; }
  const res  = await fetch('admin.php', {
    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({action:'order_create',customer,email,garment,print_method:method,qty,price,notes}).toString()
  });
  const data = await res.json();
  if (data.success) { document.getElementById('newOrderModal').classList.remove('open'); location.reload(); }
  else alert(data.error || 'Failed to create order.');
}

// Close modal on overlay click
document.getElementById('newOrderModal')?.addEventListener('click', function(e) {
  if (e.target === this) this.classList.remove('open');
});
</script>
</body>
</html>
