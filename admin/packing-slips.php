<?php
// Packing slip actions for the dashboard's "Packing Slips" tab (paid orders only)
session_start();
if (empty($_SESSION['vp_admin'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../packing-slip/src/PackingSlip.php';

function back($msg, $type = 'success') {
    header('Location: dashboard.php?tab=slips&msg=' . urlencode($msg) . '&type=' . $type);
    exit;
}

function sendPdf($bytes, $filename) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($bytes));
    header('Cache-Control: no-store');
    echo $bytes;
    exit;
}

$orders = json_decode((string)@file_get_contents(__DIR__ . '/../orders.json'), true) ?: [];
$paid = [];
foreach ($orders as $o) {
    $isPaid = !empty($o['paid']) || strpos($o['notes'] ?? '', 'Paid via Stripe.') !== false;
    if ($isPaid && !empty($o['id'])) $paid[$o['id']] = $o;
}

$ps     = new PackingSlip();
$action = $_REQUEST['action'] ?? '';
$id     = (string)($_REQUEST['id'] ?? '');

try {
    if ($action === 'view') {
        if (!isset($paid[$id])) back('Order not found or not paid.', 'warning');
        $path = $ps->pdfPath($id);
        if (!is_file($path)) $path = $ps->save($paid[$id]);
        sendPdf(file_get_contents($path), "packing-slip-{$id}.pdf");
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') back('Unknown action.', 'warning');

    if ($action === 'regenerate') {
        if (!isset($paid[$id])) back('Order not found or not paid.', 'warning');
        $ps->save($paid[$id]);
        back("Packing slip for {$id} regenerated.");
    }

    if ($action === 'printed') {
        if (!isset($paid[$id])) back('Order not found or not paid.', 'warning');
        $on = !empty($_POST['printed']);
        $ps->setPrinted([$id], $on);
        back("{$id} marked " . ($on ? 'printed.' : 'not printed.'));
    }

    if ($action === 'print') {
        if (!empty($_POST['all_unprinted'])) {
            $status = $ps->status();
            $ids = array_values(array_filter(array_keys($paid), fn($i) => empty($status[$i]['printed_at'])));
        } else {
            $ids = array_values(array_filter((array)($_POST['ids'] ?? []), fn($i) => isset($paid[$i])));
        }
        if (!$ids) back('Nothing to print.', 'warning');
        // Oldest first, so the stack comes out in the order the orders came in
        $list = array_reverse(array_map(fn($i) => $paid[$i], $ids));
        $bytes = $ps->pdf($list);
        $ps->setPrinted($ids, true);
        sendPdf($bytes, 'packing-slips-' . date('Ymd-His') . '.pdf');
    }

    back('Unknown action.', 'warning');
} catch (\Throwable $e) {
    error_log('[packing-slip admin] ' . $e->getMessage());
    back('Packing slip error: ' . $e->getMessage(), 'warning');
}
