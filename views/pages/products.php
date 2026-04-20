<?php
$db = getDB();
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$perPage = 10;
$pageNum = max(1, (int)($_GET['p'] ?? 1));
$offset = ($pageNum - 1) * $perPage;

$where = "1=1";
$params = [];
if ($search) { $where .= " AND (p.name LIKE ? OR p.brand LIKE ? OR p.kode_barang LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($category) { $where .= " AND p.category_id = ?"; $params[] = $category; }

$countStmt = $db->prepare("SELECT COUNT(*) FROM products p WHERE $where");
$countStmt->execute($params);
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / $perPage);

$stmt = $db->prepare("SELECT p.*, c.name as category_name,
    (SELECT SUM(pv.stock) FROM product_variants pv WHERE pv.product_id = p.id) as total_stock,
    (SELECT COUNT(*) FROM product_variants pv WHERE pv.product_id = p.id) as variant_count
    FROM products p JOIN categories c ON p.category_id = c.id WHERE $where ORDER BY p.created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$products = $stmt->fetchAll();
$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();
?>

<div class="toolbar">
    <div class="toolbar-left">
        <form method="GET" action="<?= APP_URL ?>/index.php" class="search-bar" style="display:flex;gap:var(--sp-3);align-items:center;">
            <input type="hidden" name="page" value="products">
            <div style="position:relative;">
                <svg style="position:absolute;left:12px;top:50%;transform:translateY(-50%);width:18px;height:18px;color:var(--text-muted);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" name="search" class="form-input" style="padding-left:38px;width:250px;" placeholder="Cari produk..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <select name="category" class="form-select" style="width:150px;" onchange="this.form.submit()">
                <option value="">Semua Kategori</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-secondary btn-sm">Cari</button>
            <?php if ($search || $category): ?>
            <a href="<?= APP_URL ?>/index.php?page=products" class="btn btn-ghost btn-sm">Reset</a>
            <?php endif; ?>
        </form>
    </div>
    <?php if (isAdmin()): ?>
    <div class="toolbar-right">
        <a href="<?= APP_URL ?>/index.php?page=products-form" class="btn btn-primary">+ Tambah Produk</a>
    </div>
    <?php endif; ?>
</div>

<?php if (empty($products)): ?>
<div class="empty-state">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
    <p>Belum ada produk</p>
    <?php if(isAdmin()): ?><a href="<?= APP_URL ?>/index.php?page=products-form" class="btn btn-primary">+ Tambah Produk Pertama</a><?php endif; ?>
</div>
<?php else: ?>
<div class="table-wrapper">
    <table class="data-table">
        <thead>
            <tr><th>Kode</th><th>Produk</th><th>Kategori</th><th>HPP</th><th>Harga Jual</th><th>Margin</th><th>Stok</th><th>Varian</th><?php if(isAdmin()): ?><th>Aksi</th><?php endif; ?></tr>
        </thead>
        <tbody>
        <?php foreach ($products as $p):
            $margin = $p['sell_price'] > 0 ? round(($p['sell_price'] - $p['cost_price']) / $p['sell_price'] * 100) : 0;
            $stockClass = ($p['total_stock'] ?? 0) <= 5 ? (($p['total_stock'] ?? 0) == 0 ? 'text-danger' : 'text-warning') : 'text-success';
        ?>
            <tr>
                <td><code style="font-size:var(--fs-xs);background:var(--bg-elevated);padding:2px 6px;border-radius:var(--radius-sm);color:var(--accent);font-weight:600;"><?= htmlspecialchars($p['kode_barang'] ?? '-') ?></code></td>
                <td>
                    <div style="display:flex;align-items:center;gap:var(--sp-3);">
                        <div style="width:40px;height:40px;background:var(--bg-elevated);border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0;overflow:hidden;">
                            <?php if($p['image']): ?><img src="<?= APP_URL ?>/uploads/products/<?= $p['image'] ?>" style="width:100%;height:100%;object-fit:cover;"><?php else: ?>👕<?php endif; ?>
                        </div>
                        <div>
                            <div class="font-bold"><?= htmlspecialchars($p['name']) ?></div>
                            <div class="text-xs text-muted"><?= htmlspecialchars($p['brand'] ?? '') ?></div>
                        </div>
                    </div>
                </td>
                <td><span class="badge badge-gold"><?= htmlspecialchars($p['category_name']) ?></span></td>
                <td class="text-secondary"><?= formatRupiah($p['cost_price']) ?></td>
                <td class="font-bold text-accent"><?= formatRupiah($p['sell_price']) ?></td>
                <td><span class="badge <?= $margin >= 30 ? 'badge-success' : 'badge-warning' ?>"><?= $margin ?>%</span></td>
                <td class="<?= $stockClass ?> font-bold"><?= $p['total_stock'] ?? 0 ?></td>
                <td><?= $p['variant_count'] ?> varian</td>
                <?php if (isAdmin()): ?>
                <td>
                    <div style="display:flex;gap:var(--sp-2);">
                        <a href="<?= APP_URL ?>/index.php?page=products-form&id=<?= $p['id'] ?>" class="btn btn-ghost btn-sm" title="Edit">✏️</a>
                        <form method="POST" action="<?= APP_URL ?>/index.php?page=products&action=delete" onsubmit="return confirm('Yakin hapus produk ini?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm" title="Hapus">🗑️</button>
                        </form>
                    </div>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
<div class="pagination">
    <span>Menampilkan <?= $offset+1 ?>-<?= min($offset+$perPage, $totalItems) ?> dari <?= $totalItems ?> produk</span>
    <div class="pagination-links">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="<?= APP_URL ?>/index.php?page=products&p=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>" class="page-link <?= $i === $pageNum ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>
