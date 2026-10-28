<?php
/**
 * Packing slip PDFs for orders: build, save, email, and track printed status.
 *
 * Self-contained: needs only this packing-slip/ folder (config.php, template.php, lib/dompdf)
 * and the store's orders.json record shape. See packing-slip/README.md.
 */

require_once __DIR__ . '/Code128.php';

final class PackingSlip
{
    private const SIZE_RANK = [
        'XS' => 0, 'S' => 1, 'M' => 2, 'L' => 3, 'XL' => 4,
        'XXL' => 5, '2XL' => 5, 'XXXL' => 6, '3XL' => 6, '4XL' => 7, '5XL' => 8, '6XL' => 9,
    ];

    private array $cfg;
    private string $siteRoot;
    private string $storageDir;
    private ?array $productImgByName = null;

    public function __construct(?array $cfg = null, ?string $siteRoot = null)
    {
        $this->cfg        = $cfg ?? require dirname(__DIR__) . '/config.php';
        $this->siteRoot   = rtrim($siteRoot ?? dirname(__DIR__, 2), '/\\');
        $this->storageDir = $this->siteRoot . '/' . trim($this->cfg['storage_dir'] ?? 'orders/packing-slips', '/');
    }

    // ── Public API ──────────────────────────────────────────

    /**
     * Called once an order is confirmed paid. Saves + emails the slip.
     * Never throws: on failure it logs and alerts by email, so checkout is never affected.
     */
    public function processPaidOrder(array $order): bool
    {
        try {
            $path = $this->save($order);
            $this->emailSlip($order, $path);
            return true;
        } catch (\Throwable $e) {
            $this->reportFailure($order['id'] ?? '?', $e);
            return false;
        }
    }

    /** Renders and saves one order's slip; returns the PDF file path. */
    public function save(array $order): string
    {
        $id = $this->safeId($order['id'] ?? '');
        $this->ensureStorage();
        $path = $this->storageDir . '/' . $id . '.pdf';
        if (file_put_contents($path, $this->pdf([$order]), LOCK_EX) === false) {
            throw new RuntimeException("Could not write {$path}");
        }
        $this->updateStatus([$id], fn($s) => array_merge($s, ['generated_at' => date('c')]));
        return $path;
    }

    /** PDF bytes for one or more orders (several = merged, each starting on a new page). */
    public function pdf(array $orders): string
    {
        if (!$orders) throw new InvalidArgumentException('No orders given');
        $slips = array_map(fn($o) => $this->prepare($o), array_values($orders));

        // "Page X of Y" is per order, so in a merged PDF we need to know where each order starts
        $counts = count($slips) === 1 ? null : array_map(fn($s) => $this->render([$s])->getCanvas()->get_page_count(), $slips);
        $dompdf = $this->render($slips, $counts);
        return $dompdf->output();
    }

    public function pdfPath(string $orderId): string
    {
        return $this->storageDir . '/' . $this->safeId($orderId) . '.pdf';
    }

    /** ['VGX-…' => ['generated_at' => …, 'emailed_at' => …, 'printed_at' => …|null], …] */
    public function status(): array
    {
        $file = $this->storageDir . '/status.json';
        $data = is_file($file) ? json_decode((string)file_get_contents($file), true) : [];
        return is_array($data) ? $data : [];
    }

    public function setPrinted(array $orderIds, bool $printed): void
    {
        $ids = array_map(fn($id) => $this->safeId((string)$id), $orderIds);
        $this->updateStatus($ids, fn($s) => array_merge($s, ['printed_at' => $printed ? date('c') : null]));
    }

    // ── Order data → template data ───────────────────────────

    private function prepare(array $order): array
    {
        $cust  = $order['customer'] ?? [];
        $addr  = $cust['address'] ?? [];
        $items = $order['items'] ?? [];

        // Custom designs send their preview image only on the first size line — share it
        $imgByName = [];
        foreach ($items as $it) {
            if (!empty($it['img']) && !isset($imgByName[$it['name'] ?? ''])) $imgByName[$it['name'] ?? ''] = $it['img'];
        }

        $lines = [];
        foreach ($items as $it) {
            $name    = trim((string)($it['name'] ?? 'Item'));
            $garment = trim((string)($it['garment'] ?? ''));
            $variant = is_array($it['variant'] ?? null) ? $it['variant'] : [];
            $color   = trim((string)($variant['color'] ?? ''));
            $print   = trim((string)($variant['style'] ?? ''));
            if (!$variant && !empty($it['color'])) $color = trim((string)$it['color']);
            // Designer items: "Gildan 5000 (Black) · Front & Back"
            if (preg_match('/^(.*?)\s*\(([^)]+)\)\s*·\s*(.+)$/u', $garment, $m)) {
                $garment = $m[1];
                $color   = $color ?: $m[2];
                $print   = $print ?: $m[3];
            }
            $qty = max(1, (int)($it['qty'] ?? 1));
            $lines[] = [
                'sku'     => (string)($it['id'] ?? ''),
                'name'    => $name,
                'garment' => $garment,
                'print'   => $print,
                'size'    => trim((string)($it['size'] ?? '')),
                'color'   => $color,
                'qty'     => $qty,
                'price'   => $it['priceStr'] ?? (isset($it['price']) ? '$' . number_format((float)$it['price'], 2) : ''),
                'img'     => $this->imageSrc(($it['img'] ?? '') ?: ($imgByName[$name] ?? ''), $name),
            ];
        }
        // Sorted for pulling stock: product, then color, then size (XS → 3XL…)
        usort($lines, function ($a, $b) {
            return strnatcasecmp($a['name'], $b['name'])
                ?: strnatcasecmp($a['color'], $b['color'])
                ?: ($this->sizeRank($a['size']) <=> $this->sizeRank($b['size']))
                ?: strnatcasecmp($a['size'], $b['size']);
        });

        $shipTo = array_values(array_filter([
            $cust['name'] ?? '',
            $addr['line1'] ?? '',
            $addr['line2'] ?? '',
            trim(($addr['city'] ?? '') . ', ' . ($addr['state'] ?? '') . ' ' . ($addr['zip'] ?? ''), ' ,'),
        ], 'strlen'));

        $billTo = null;
        $bill = $order['billing'] ?? null;
        if (is_array($bill) && !empty($bill['line1'])) {
            $differs = $this->norm(($bill['line1'] ?? '') . ($bill['postal_code'] ?? ''))
                    !== $this->norm(($addr['line1'] ?? '') . ($addr['zip'] ?? ''));
            if ($differs) {
                $billTo = array_values(array_filter([
                    $bill['name'] ?? '',
                    $bill['line1'] ?? '',
                    $bill['line2'] ?? '',
                    trim(($bill['city'] ?? '') . ', ' . ($bill['state'] ?? '') . ' ' . ($bill['postal_code'] ?? ''), ' ,'),
                    (($bill['country'] ?? 'US') !== 'US') ? $bill['country'] : '',
                ], 'strlen'));
            }
        }

        // stripe-success.php appends this bookkeeping line to the customer's notes
        $notes = trim(preg_replace('/\s*Paid via Stripe\.\s*$/', '', (string)($order['notes'] ?? '')));
        $id    = (string)($order['id'] ?? '');

        return [
            'id'        => $id,
            'barcode'   => 'data:image/svg+xml;base64,' . base64_encode(PackingSlipCode128::svg($id, 1, 36)),
            'date'      => $this->fmtDate($order['paidAt'] ?? $order['date'] ?? ''),
            'shipping'  => $order['shipping_method'] ?? $this->cfg['shipping_method'] ?? 'Standard Shipping',
            'ship_to'   => $shipTo,
            'phone'     => $cust['phone'] ?? '',
            'bill_to'   => $billTo,
            'lines'     => $lines,
            'item_count'=> array_sum(array_column($lines, 'qty')),
            'notes'     => $notes,
            'subtotal'  => (float)($order['subtotal'] ?? 0),
            'ship_cost' => (float)($order['shipping'] ?? 0),
            'total'     => (float)($order['total'] ?? 0),
        ];
    }

    // ── Rendering ───────────────────────────────────────────

    private function render(array $slips, ?array $pageCounts = null): \Dompdf\Dompdf
    {
        require_once dirname(__DIR__) . '/lib/dompdf/autoload.inc.php';

        $cfg     = $this->cfg;
        $compact = ($cfg['paper'] ?? 'letter') === '4x6';
        $logo    = $this->logoSrc();
        ob_start();
        include dirname(__DIR__) . '/template.php';
        $html = ob_get_clean();

        $options = new \Dompdf\Options();
        $options->setChroot([$this->siteRoot]);
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('DejaVu Sans');
        $options->setTempDir(sys_get_temp_dir());

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->setPaper($compact ? [0, 0, 288, 432] : 'letter', 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        // Page numbers + a running header on continuation pages, both per order
        $counts = $pageCounts ?? [$dompdf->getCanvas()->get_page_count()];
        $map = [];
        foreach ($counts as $i => $n) {
            for ($p = 1; $p <= $n; $p++) $map[] = [$i, $p, $n];
        }
        $store = $cfg['store_name'] ?? '';
        $dompdf->getCanvas()->page_script(function ($pageNum, $pageCount, $canvas, $fontMetrics) use ($map, $slips, $store, $compact) {
            [$i, $local, $total] = $map[$pageNum - 1] ?? [0, $pageNum, $pageCount];
            $font = $fontMetrics->getFont('DejaVu Sans');
            $size = $compact ? 6 : 8;
            $w    = $canvas->get_width();
            $h    = $canvas->get_height();
            // Side/top/bottom page margins in points — keep in sync with @page in template.php
            $side = $compact ? 10 : 36;
            $top  = $compact ? 16 : 43;
            $text = "Page {$local} of {$total}";
            $canvas->text($w - $side - $fontMetrics->getTextWidth($text, $font, $size), $h - $top / 2 - $size / 2, $text, $font, $size, [0.3, 0.3, 0.3]);
            if ($local > 1) {
                $head = trim("{$store} — Order #" . ($slips[$i]['id'] ?? '') . ' (continued)');
                $canvas->text($side, $top / 2 - $size / 2 - 1, $head, $fontMetrics->getFont('DejaVu Sans', 'bold'), $size + 1, [0, 0, 0]);
            }
        });
        return $dompdf;
    }

    /** data: URI or absolute file path Dompdf can read, or '' */
    private function imageSrc($img, string $name): string
    {
        if (empty($this->cfg['show_thumbnails']) || ($this->cfg['paper'] ?? 'letter') === '4x6') return '';
        $img = is_string($img) ? $img : '';
        if (preg_match('#^data:image/(png|jpe?g|gif);base64,[A-Za-z0-9+/=\s]+$#i', $img)) return $img;
        if (!$this->isSafePath($img)) $img = $this->productImage($name);
        if ($img && $this->isSafePath($img) && is_file($this->siteRoot . '/' . $img)) return $this->siteRoot . '/' . $img;
        return '';
    }

    private function productImage(string $name): string
    {
        if ($this->productImgByName === null) {
            $this->productImgByName = [];
            $file = $this->siteRoot . '/products.json';
            $products = is_file($file) ? json_decode((string)file_get_contents($file), true) : [];
            foreach (is_array($products) ? $products : [] as $p) {
                if (!empty($p['name']) && $this->isSafePath($p['img'] ?? '')) $this->productImgByName[$p['name']] ??= $p['img'];
            }
        }
        return $this->productImgByName[$name] ?? '';
    }

    private function logoSrc(): string
    {
        $rel = $this->cfg['logo'] ?? '';
        if (!$rel || !$this->isSafePath($rel) || !is_file($this->siteRoot . '/' . $rel)) return '';
        $file = $this->siteRoot . '/' . $rel;
        if (empty($this->cfg['logo_grayscale']) || !function_exists('imagefilter')) return $file;

        $im = @imagecreatefromstring((string)file_get_contents($file));
        if (!$im) return $file;
        imagealphablending($im, false);
        imagesavealpha($im, true);
        imagefilter($im, IMG_FILTER_GRAYSCALE);
        ob_start();
        imagepng($im);
        imagedestroy($im);
        return 'data:image/png;base64,' . base64_encode((string)ob_get_clean());
    }

    // ── Email + failure handling ─────────────────────────────

    private function emailSlip(array $order, string $path): void
    {
        $to = $this->cfg['order_email'] ?? '';
        if (!$to) return;
        $id   = $this->safeId($order['id'] ?? '');
        $cust = $order['customer']['name'] ?? '';
        $text = "New paid order #{$id}" . ($cust ? " from {$cust}" : '') . ".\n\nThe packing slip is attached."
              . "\nAll slips: https://" . ($this->cfg['website'] ?? '') . "/admin/dashboard.php?tab=slips\n";
        $subject = '[' . ($this->cfg['store_name'] ?? 'Store') . "] New order #{$id} – packing slip";
        if (!$this->mailWithAttachment($to, $subject, $text, (string)file_get_contents($path), "packing-slip-{$id}.pdf")) {
            throw new RuntimeException('mail() refused the packing slip email');
        }
        $this->updateStatus([$id], fn($s) => array_merge($s, ['emailed_at' => date('c')]));
    }

    private function mailWithAttachment(string $to, string $subject, string $text, string $data, string $filename): bool
    {
        $b = 'ps_' . bin2hex(random_bytes(8));
        $headers = "From: " . ($this->cfg['from_email'] ?? 'noreply@localhost') . "\r\n"
                 . "MIME-Version: 1.0\r\n"
                 . "Content-Type: multipart/mixed; boundary=\"{$b}\"";
        $body = "--{$b}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$text}\r\n"
              . "--{$b}\r\nContent-Type: application/pdf; name=\"{$filename}\"\r\n"
              . "Content-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"{$filename}\"\r\n\r\n"
              . chunk_split(base64_encode($data)) . "--{$b}--\r\n";
        return mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
    }

    private function reportFailure(string $orderId, \Throwable $e): void
    {
        $msg = date('c') . " order {$orderId}: " . get_class($e) . ': ' . $e->getMessage()
             . ' in ' . $e->getFile() . ':' . $e->getLine();
        try {
            $this->ensureStorage();
            @file_put_contents($this->storageDir . '/errors.log', $msg . "\n", FILE_APPEND | LOCK_EX);
        } catch (\Throwable $ignored) {
            error_log('[packing-slip] ' . $msg);
        }
        $to = $this->cfg['alert_email'] ?? '';
        if ($to) {
            @mail($to, '[' . ($this->cfg['store_name'] ?? 'Store') . "] Packing slip FAILED for order #{$orderId}",
                "The order went through normally, but its packing slip could not be created.\n\n{$msg}\n\n"
                . "You can retry from the admin dashboard → Packing Slips → Regenerate.",
                'From: ' . ($this->cfg['from_email'] ?? 'noreply@localhost'));
        }
    }

    // ── Helpers ─────────────────────────────────────────────

    private function ensureStorage(): void
    {
        if (!is_dir($this->storageDir) && !mkdir($this->storageDir, 0755, true) && !is_dir($this->storageDir)) {
            throw new RuntimeException("Could not create {$this->storageDir}");
        }
        $ht = $this->storageDir . '/.htaccess';
        if (!is_file($ht)) {
            file_put_contents($ht, "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n"
                . "<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n");
        }
    }

    private function updateStatus(array $ids, callable $fn): void
    {
        $this->ensureStorage();
        $fh = fopen($this->storageDir . '/status.json', 'c+');
        if (!$fh) return;
        flock($fh, LOCK_EX);
        $data = json_decode((string)stream_get_contents($fh), true);
        $data = is_array($data) ? $data : [];
        foreach ($ids as $id) $data[$id] = $fn($data[$id] ?? ['printed_at' => null]);
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        flock($fh, LOCK_UN);
        fclose($fh);
    }

    private function safeId(string $id): string
    {
        if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id)) throw new InvalidArgumentException('Invalid order id');
        return $id;
    }

    private function isSafePath($p): bool
    {
        return is_string($p) && preg_match('#^[\w\-/]+\.(png|jpe?g|gif)$#i', $p) && strpos($p, '..') === false;
    }

    private function sizeRank(string $size): int
    {
        return self::SIZE_RANK[strtoupper(str_replace(' ', '', $size))] ?? 99;
    }

    private function norm(string $s): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', $s));
    }

    private function fmtDate(string $iso): string
    {
        try {
            $d = new \DateTime($iso ?: 'invalid');
            $d->setTimezone(new \DateTimeZone($this->cfg['timezone'] ?? date_default_timezone_get()));
            return $d->format('M j, Y g:i A');
        } catch (\Exception $e) {
            return '';
        }
    }
}
