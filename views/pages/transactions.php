<?php
$db = getDB();
$search = $_GET['search'] ?? '';
$method = $_GET['method'] ?? '';
$perPage = 15;
$pageNum = max(1, (int) ($_GET['p'] ?? 1));
$offset = ($pageNum - 1) * $perPage;

$where = "1=1";
$params = [];
if (!isAdmin()) {
    $where .= " AND t.user_id = ?";
    $params[] = $_SESSION['user_id'];
}
if ($search) {
    $where .= " AND t.invoice_number LIKE ?";
    $params[] = "%$search%";
}
if ($method) {
    $where .= " AND t.payment_method = ?";
    $params[] = $method;
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM transactions t WHERE $where");
$countStmt->execute($params);
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / $perPage);

$stmt = $db->prepare("SELECT t.*, u.name as cashier FROM transactions t JOIN users u ON t.user_id = u.id WHERE $where ORDER BY t.created_at DESC LIMIT ? OFFSET ?");
$params[] = $perPage;
$params[] = $offset;
$stmt->execute($params);
$transactions = $stmt->fetchAll();
?>

<div class="toolbar">
    <div class="toolbar-left">
        <form method="GET" action="<?= APP_URL ?>/index.php" style="display:flex;gap:var(--sp-3);align-items:center;">
            <input type="hidden" name="page" value="transactions">
            <div style="position:relative;">
                <i data-lucide="search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);width:18px;height:18px;color:var(--text-muted);"></i>
                <input type="text" name="search" class="form-input" style="padding-left:38px;width:220px;"
                    placeholder="Cari no. transaksi..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <select name="method" class="form-select" style="width:140px;" onchange="this.form.submit()">
                <option value="">Semua Metode</option>
                <option value="cash" <?= $method === 'cash' ? 'selected' : '' ?>>💵 Tunai</option>
                <option value="qris" <?= $method === 'qris' ? 'selected' : '' ?>>📱 QRIS</option>
                <option value="transfer" <?= $method === 'transfer' ? 'selected' : '' ?>>🏦 Transfer</option>
            </select>
            <button type="submit" class="btn btn-secondary btn-sm"><i data-lucide="search" style="width:14px;height:14px;"></i> Cari</button>
        </form>
    </div>
</div>

<?php if (empty($transactions)): ?>
    <div class="empty-state">
        <i data-lucide="file-text" style="width:64px;height:64px;"></i>
        <p>Belum ada transaksi</p>
    </div>
<?php else: ?>
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Waktu</th>
                    <th>Kasir</th>
                    <th>Total</th>
                    <th>Metode</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $t):
                    $methods = ['cash' => '💵 Tunai', 'qris' => '📱 QRIS', 'transfer' => '🏦 Transfer'];
                    ?>
                    <tr>
                        <td class="font-bold"><?= e($t['invoice_number']) ?></td>
                        <td class="text-sm text-secondary"><?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></td>
                        <td><?= htmlspecialchars($t['cashier']) ?></td>
                        <td class="font-bold text-accent"><?= formatRupiah($t['total']) ?></td>
                        <td><?= $methods[$t['payment_method']] ?? e($t['payment_method']) ?></td>
                        <td>
                            <?php if ($t['status'] === 'voided'): ?>
                                <span class="badge badge-danger">Void</span>
                            <?php else: ?>
                                <span class="badge badge-success">✅ Selesai</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display:flex;gap:var(--sp-2);">
                                <a href="<?= APP_URL ?>/index.php?page=transaction-detail&id=<?= $t['id'] ?>"
                                    class="btn btn-ghost btn-sm"><i data-lucide="eye" style="width:14px;height:14px;"></i> Detail</a>
                                <?php if (isAdmin() && $t['status'] !== 'voided'): ?>
                                    <form method="POST" action="<?= APP_URL ?>/index.php?page=transactions&action=void"
                                        onsubmit="return confirm('Yakin batalkan transaksi ini? Stok akan dikembalikan.')">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm"><i data-lucide="x-circle" style="width:14px;height:14px;"></i> Void</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <span>Menampilkan <?= $offset + 1 ?>-<?= min($offset + $perPage, $totalItems) ?> dari <?= $totalItems ?></span>
            <div class="pagination-links">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="<?= APP_URL ?>/index.php?page=transactions&p=<?= $i ?>&search=<?= urlencode($search) ?>&method=<?= urlencode($method) ?>"
                        class="page-link <?= $i === $pageNum ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>