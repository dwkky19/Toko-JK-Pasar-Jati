<?php
$db = getDB();
$id = (int)($_GET['id'] ?? 0);

// --- Security: Build query with access control (non-admin can only see own transactions) ---
$sql = "SELECT t.*, u.name as cashier FROM transactions t JOIN users u ON t.user_id = u.id WHERE t.id = ?";
$params = [$id];
if (!isAdmin()) {
    $sql .= " AND t.user_id = ?";
    $params[] = $_SESSION['user_id'];
}
$tx = $db->prepare($sql);
$tx->execute($params);
$transaction = $tx->fetch();

if (!$transaction) {
    echo '<div class="empty-state"><i data-lucide="file-x" style="width:64px;height:64px;"></i><p>Transaksi tidak ditemukan</p><a href="'.APP_URL.'/index.php?page=transactions" class="btn btn-secondary">← Kembali</a></div>';
    return;
}

$items = $db->prepare("SELECT * FROM transaction_items WHERE transaction_id = ?");
$items->execute([$id]);
$txItems = $items->fetchAll();

$settings = [];
$sStmt = $db->query("SELECT setting_key, setting_value FROM settings");
foreach ($sStmt->fetchAll() as $s) $settings[$s['setting_key']] = $s['setting_value'];
$methods = ['cash'=>'💵 Tunai','qris'=>'📱 QRIS','transfer'=>'🏦 Transfer'];
?>

<div style="margin-bottom:var(--sp-4);">
    <a href="<?= APP_URL ?>/index.php?page=transactions" class="btn btn-ghost btn-sm"><i data-lucide="arrow-left" style="width:14px;height:14px;"></i> Kembali ke Transaksi</a>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--sp-6);max-width:900px;">
    <!-- Detail -->
    <div class="card">
        <h3 style="font-weight:700;margin-bottom:var(--sp-4);display:flex;align-items:center;gap:var(--sp-2);"><i data-lucide="file-text" style="width:18px;height:18px;color:var(--accent);"></i> Detail Transaksi</h3>
        <table style="width:100%;font-size:var(--fs-sm);">
            <tr><td class="text-secondary" style="padding:6px 0;">Invoice</td><td class="font-bold"><?= e($transaction['invoice_number']) ?></td></tr>
            <tr><td class="text-secondary" style="padding:6px 0;">Tanggal</td><td><?= date('d/m/Y H:i', strtotime($transaction['created_at'])) ?></td></tr>
            <tr><td class="text-secondary" style="padding:6px 0;">Kasir</td><td><?= htmlspecialchars($transaction['cashier']) ?></td></tr>
            <tr><td class="text-secondary" style="padding:6px 0;">Metode</td><td><?= $methods[$transaction['payment_method']] ?? e($transaction['payment_method']) ?></td></tr>
            <tr><td class="text-secondary" style="padding:6px 0;">Status</td><td><?= $transaction['status']==='voided'?'<span class="badge badge-danger">Void</span>':'<span class="badge badge-success">Selesai</span>' ?></td></tr>
        </table>

        <div style="margin-top:var(--sp-5);">
            <h4 style="font-size:var(--fs-sm);font-weight:600;margin-bottom:var(--sp-3);color:var(--text-secondary);">Item</h4>
            <?php foreach ($txItems as $item): ?>
            <div style="display:flex;justify-content:space-between;padding:var(--sp-2) 0;border-bottom:1px solid var(--border);font-size:var(--fs-sm);">
                <div>
                    <div class="font-bold"><?= e($item['product_name']) ?></div>
                    <div class="text-muted"><?= e($item['variant_info']) ?> × <?= (int)$item['quantity'] ?></div>
                </div>
                <div class="font-bold text-accent"><?= formatRupiah($item['subtotal']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="margin-top:var(--sp-4);padding-top:var(--sp-3);border-top:1px solid var(--border);">
            <div style="display:flex;justify-content:space-between;font-size:var(--fs-sm);margin-bottom:4px;"><span class="text-secondary">Subtotal</span><span><?= formatRupiah($transaction['subtotal']) ?></span></div>
            <?php if ($transaction['discount_amount'] > 0): ?>
            <div style="display:flex;justify-content:space-between;font-size:var(--fs-sm);margin-bottom:4px;"><span class="text-secondary">Diskon</span><span class="text-danger">-<?= formatRupiah($transaction['discount_amount']) ?></span></div>
            <?php endif; ?>
            <div style="display:flex;justify-content:space-between;font-weight:700;font-size:var(--fs-md);margin-top:var(--sp-2);"><span>Total</span><span class="text-accent"><?= formatRupiah($transaction['total']) ?></span></div>
            <div style="display:flex;justify-content:space-between;font-size:var(--fs-sm);margin-top:4px;"><span class="text-secondary">Bayar</span><span><?= formatRupiah($transaction['payment_amount']) ?></span></div>
            <?php if ($transaction['change_amount'] > 0): ?>
            <div style="display:flex;justify-content:space-between;font-size:var(--fs-sm);"><span class="text-secondary">Kembalian</span><span class="text-success"><?= formatRupiah($transaction['change_amount']) ?></span></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Receipt Preview -->
    <div class="card">
        <h3 style="font-weight:700;margin-bottom:var(--sp-4);display:flex;align-items:center;gap:var(--sp-2);"><i data-lucide="printer" style="width:18px;height:18px;color:var(--accent);"></i> Preview Struk</h3>
        <div style="background:#fff;color:#000;padding:var(--sp-6);border-radius:var(--radius-md);font-family:monospace;font-size:12px;max-width:300px;margin:0 auto;box-shadow:var(--shadow-md);" id="receiptContent">
            <div style="text-align:center;margin-bottom:var(--sp-4);">
                <h3 style="font-size:14px;"><?= e($settings['store_name'] ?? 'Toko JK Pasar Jati') ?></h3>
                <p><?= e($settings['store_address'] ?? '') ?></p>
                <p><?= e($settings['store_phone'] ?? '') ?></p>
            </div>
            <div style="border-top:1px dashed #CBD5E1;margin:8px 0;"></div>
            <p><?= e($transaction['invoice_number']) ?><br><?= date('d/m/Y H:i', strtotime($transaction['created_at'])) ?><br>Kasir: <?= e($transaction['cashier']) ?></p>
            <div style="border-top:1px dashed #CBD5E1;margin:8px 0;"></div>
            <?php foreach ($txItems as $item): ?>
            <div><?= e($item['product_name']) ?></div>
            <div style="display:flex;justify-content:space-between;"><span>&nbsp; <?= e($item['variant_info']) ?> x<?= (int)$item['quantity'] ?></span><span>Rp <?= number_format($item['subtotal'],0,',','.') ?></span></div>
            <?php endforeach; ?>
            <div style="border-top:1px dashed #CBD5E1;margin:8px 0;"></div>
            <?php if ($transaction['discount_amount'] > 0): ?>
            <div style="display:flex;justify-content:space-between;"><span>Diskon</span><span>-Rp <?= number_format($transaction['discount_amount'],0,',','.') ?></span></div>
            <?php endif; ?>
            <div style="display:flex;justify-content:space-between;font-weight:bold;font-size:14px;"><span>TOTAL</span><span>Rp <?= number_format($transaction['total'],0,',','.') ?></span></div>
            <div style="display:flex;justify-content:space-between;"><span>Bayar</span><span>Rp <?= number_format($transaction['payment_amount'],0,',','.') ?></span></div>
            <?php if ($transaction['change_amount'] > 0): ?>
            <div style="display:flex;justify-content:space-between;"><span>Kembali</span><span>Rp <?= number_format($transaction['change_amount'],0,',','.') ?></span></div>
            <?php endif; ?>
            <div style="border-top:1px dashed #CBD5E1;margin:8px 0;"></div>
            <p style="text-align:center;"><?= e($settings['receipt_footer'] ?? 'Terima kasih!') ?></p>
        </div>
        <button class="btn btn-primary mt-4 w-full" onclick="printReceipt()"><i data-lucide="printer" style="width:16px;height:16px;"></i> Cetak Struk</button>
    </div>
</div>

<script>
function printReceipt() {
    const content = document.getElementById('receiptContent').innerHTML;
    const win = window.open('','_blank','width=320,height=600');
    win.document.write('<html><head><title>Struk</title><style>body{font-family:monospace;font-size:12px;width:280px;margin:0 auto;padding:20px;}</style></head><body>'+content+'</body></html>');
    win.document.close();
    win.print();
}
</script>
