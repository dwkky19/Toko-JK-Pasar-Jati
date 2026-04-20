<?php
$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$tx = $db->prepare("SELECT t.*, u.name as cashier FROM transactions t JOIN users u ON t.user_id = u.id WHERE t.id = ?");
$tx->execute([$id]);
$transaction = $tx->fetch();

if (!$transaction) {
    echo '<div class="empty-state"><p>Transaksi tidak ditemukan</p><a href="'.APP_URL.'/index.php?page=transactions" class="btn btn-secondary">← Kembali</a></div>';
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
    <a href="<?= APP_URL ?>/index.php?page=transactions" class="btn btn-ghost btn-sm">← Kembali ke Transaksi</a>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--sp-6);max-width:900px;">
    <!-- Detail -->
    <div class="card">
        <h3 style="font-weight:700;margin-bottom:var(--sp-4);">Detail Transaksi</h3>
        <table style="width:100%;font-size:var(--fs-sm);">
            <tr><td class="text-secondary" style="padding:6px 0;">Invoice</td><td class="font-bold"><?= $transaction['invoice_number'] ?></td></tr>
            <tr><td class="text-secondary" style="padding:6px 0;">Tanggal</td><td><?= date('d/m/Y H:i', strtotime($transaction['created_at'])) ?></td></tr>
            <tr><td class="text-secondary" style="padding:6px 0;">Kasir</td><td><?= htmlspecialchars($transaction['cashier']) ?></td></tr>
            <tr><td class="text-secondary" style="padding:6px 0;">Metode</td><td><?= $methods[$transaction['payment_method']] ?? $transaction['payment_method'] ?></td></tr>
            <tr><td class="text-secondary" style="padding:6px 0;">Status</td><td><?= $transaction['status']==='voided'?'<span class="badge badge-danger">Void</span>':'<span class="badge badge-success">Selesai</span>' ?></td></tr>
        </table>

        <div style="margin-top:var(--sp-5);">
            <h4 style="font-size:var(--fs-sm);font-weight:600;margin-bottom:var(--sp-3);color:var(--text-secondary);">Item</h4>
            <?php foreach ($txItems as $item): ?>
            <div style="display:flex;justify-content:space-between;padding:var(--sp-2) 0;border-bottom:1px solid var(--border);font-size:var(--fs-sm);">
                <div>
                    <div class="font-bold"><?= htmlspecialchars($item['product_name']) ?></div>
                    <div class="text-muted"><?= $item['variant_info'] ?> × <?= $item['quantity'] ?></div>
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
        <h3 style="font-weight:700;margin-bottom:var(--sp-4);">Preview Struk</h3>
        <div class="receipt" id="receiptContent">
            <div class="receipt-header">
                <h3><?= $settings['store_name'] ?? 'Toko JK Pasar Jati' ?></h3>
                <p><?= $settings['store_address'] ?? '' ?></p>
                <p><?= $settings['store_phone'] ?? '' ?></p>
            </div>
            <div class="receipt-divider"></div>
            <p><?= $transaction['invoice_number'] ?><br><?= date('d/m/Y H:i', strtotime($transaction['created_at'])) ?><br>Kasir: <?= htmlspecialchars($transaction['cashier']) ?></p>
            <div class="receipt-divider"></div>
            <?php foreach ($txItems as $item): ?>
            <div><?= $item['product_name'] ?></div>
            <div class="receipt-item"><span>&nbsp; <?= $item['variant_info'] ?> x<?= $item['quantity'] ?></span><span>Rp <?= number_format($item['subtotal'],0,',','.') ?></span></div>
            <?php endforeach; ?>
            <div class="receipt-divider"></div>
            <?php if ($transaction['discount_amount'] > 0): ?>
            <div class="receipt-item"><span>Diskon</span><span>-Rp <?= number_format($transaction['discount_amount'],0,',','.') ?></span></div>
            <?php endif; ?>
            <div class="receipt-item receipt-total"><span>TOTAL</span><span>Rp <?= number_format($transaction['total'],0,',','.') ?></span></div>
            <div class="receipt-item"><span>Bayar</span><span>Rp <?= number_format($transaction['payment_amount'],0,',','.') ?></span></div>
            <?php if ($transaction['change_amount'] > 0): ?>
            <div class="receipt-item"><span>Kembali</span><span>Rp <?= number_format($transaction['change_amount'],0,',','.') ?></span></div>
            <?php endif; ?>
            <div class="receipt-divider"></div>
            <p style="text-align:center;"><?= $settings['receipt_footer'] ?? 'Terima kasih!' ?></p>
        </div>
        <button class="btn btn-primary mt-4 w-full" onclick="printReceipt()">🖨️ Cetak Struk</button>
    </div>
</div>

<script>
function printReceipt() {
    const content = document.getElementById('receiptContent').innerHTML;
    const win = window.open('','_blank','width=320,height=600');
    win.document.write('<html><head><title>Struk</title><style>body{font-family:monospace;font-size:12px;width:280px;margin:0 auto;padding:20px;}.center{text-align:center;}.line,.receipt-divider{border-top:1px dashed #000;margin:8px 0;}.receipt-item,.row{display:flex;justify-content:space-between;}.receipt-total,.bold{font-weight:bold;}.receipt-header{text-align:center;}</style></head><body>'+content+'</body></html>');
    win.document.close();
    win.print();
}
</script>
