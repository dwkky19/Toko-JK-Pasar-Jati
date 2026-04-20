<?php
$db = getDB();
$id = $_GET['id'] ?? null;
$product = null;
$variants = [];

if ($id) {
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    if ($product) {
        $vStmt = $db->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY color, size");
        $vStmt->execute([$id]);
        $variants = $vStmt->fetchAll();
    }
}

$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$allSizes = ['XS','S','M','L','XL','XXL','28','29','30','31','32','33','34','36','All Size'];
$defaultColors = ['Black','White','Red','Blue','Navy','Grey','Cream','Pink','Brown','Green'];
$colorHex = ['Black'=>'#4a5568','White'=>'#e2e8f0','Red'=>'#e74c3c','Blue'=>'#3498db','Navy'=>'#1e3a5f','Grey'=>'#718096','Cream'=>'#f5e6ca','Pink'=>'#e91e90','Brown'=>'#8b4513','Green'=>'#27ae60'];
?>

<div style="max-width:700px;">
    <div style="margin-bottom:var(--sp-4);">
        <a href="<?= APP_URL ?>/index.php?page=products" class="btn btn-ghost btn-sm"><i data-lucide="arrow-left" style="width:14px;height:14px;"></i> Kembali ke Produk</a>
    </div>

    <div class="card">
        <h2 style="font-size:var(--fs-lg);font-weight:700;margin-bottom:var(--sp-5);display:flex;align-items:center;gap:var(--sp-2);">
            <i data-lucide="<?= $product ? 'pencil' : 'plus-circle' ?>" style="width:20px;height:20px;color:var(--accent);"></i>
            <?= $product ? 'Edit Produk' : 'Tambah Produk Baru' ?>
        </h2>

        <form method="POST" action="<?= APP_URL ?>/index.php?page=products-form" enctype="multipart/form-data">
            <?= csrfField() ?>
            <?php if ($product): ?>
            <input type="hidden" name="id" value="<?= $product['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label">Nama Produk *</label>
                <input type="text" name="name" class="form-input" required value="<?= htmlspecialchars($product['name'] ?? '') ?>" placeholder="Contoh: Kemeja Flanel Premium">
            </div>

            <div class="form-group">
                <label class="form-label">Kode Barang *</label>
                <input type="text" name="kode_barang" class="form-input" required value="<?= htmlspecialchars($product['kode_barang'] ?? '') ?>" placeholder="Contoh: BRG-0001" style="font-family:monospace;font-weight:600;letter-spacing:0.05em;">
                <div style="font-size:var(--fs-xs);color:var(--text-muted);margin-top:var(--sp-1);">Kode unik untuk identifikasi barang</div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Kategori *</label>
                    <select name="category_id" class="form-select" required>
                        <option value="">Pilih Kategori</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ($product['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Brand / Merek</label>
                    <input type="text" name="brand" class="form-input" value="<?= htmlspecialchars($product['brand'] ?? '') ?>" placeholder="Contoh: Urban Style">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Deskripsi</label>
                <textarea name="description" class="form-textarea" placeholder="Deskripsi singkat produk..."><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Harga Modal (HPP) *</label>
                    <input type="number" name="cost_price" class="form-input" required min="0" value="<?= $product['cost_price'] ?? '' ?>" placeholder="0" id="costPrice" oninput="calcMargin()">
                </div>
                <div class="form-group">
                    <label class="form-label">Harga Jual *</label>
                    <input type="number" name="sell_price" class="form-input" required min="0" value="<?= $product['sell_price'] ?? '' ?>" placeholder="0" id="sellPrice" oninput="calcMargin()">
                </div>
            </div>
            <div id="marginDisplay" style="margin-bottom:var(--sp-4);font-size:var(--fs-sm);"></div>

            <div class="form-group">
                <label class="form-label">Foto Produk</label>
                <?php if ($product && $product['image']): ?>
                <div style="margin-bottom:var(--sp-2);"><img src="<?= APP_URL ?>/uploads/products/<?= $product['image'] ?>" style="width:80px;height:80px;object-fit:cover;border-radius:var(--radius-md);border:1px solid var(--border);"></div>
                <?php endif; ?>
                <input type="file" name="image" class="form-input" accept="image/*">
            </div>

            <div class="form-group">
                <label class="form-label">Ukuran</label>
                <div style="display:flex;flex-wrap:wrap;gap:var(--sp-2);">
                    <?php
                    $existingSizes = array_unique(array_column($variants, 'size'));
                    foreach ($allSizes as $size): ?>
                    <label style="display:flex;align-items:center;gap:4px;padding:var(--sp-2) var(--sp-3);background:var(--bg-elevated);border-radius:var(--radius-md);cursor:pointer;font-size:var(--fs-sm);border:1px solid var(--border);transition:all var(--dur-fast);">
                        <input type="checkbox" name="sizes[]" value="<?= $size ?>" <?= in_array($size, $existingSizes) ? 'checked' : '' ?>> <?= $size ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Warna</label>
                <div style="display:flex;flex-wrap:wrap;gap:var(--sp-2);">
                    <?php
                    $existingColors = array_unique(array_column($variants, 'color'));
                    foreach ($defaultColors as $color): ?>
                    <label style="display:flex;align-items:center;gap:4px;padding:var(--sp-2) var(--sp-3);background:var(--bg-elevated);border-radius:var(--radius-md);cursor:pointer;font-size:var(--fs-sm);border:1px solid var(--border);transition:all var(--dur-fast);">
                        <input type="checkbox" name="colors[]" value="<?= $color ?>" <?= in_array($color, $existingColors) ? 'checked' : '' ?>>
                        <span class="color-dot" style="background:<?= $colorHex[$color] ?? '#64748B' ?>;"></span> <?= $color ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if (!empty($variants)): ?>
            <div class="form-group">
                <label class="form-label">Varian Saat Ini</label>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead><tr><th>SKU</th><th>Ukuran</th><th>Warna</th><th>Stok</th></tr></thead>
                        <tbody>
                        <?php foreach ($variants as $v): ?>
                        <tr><td class="text-sm"><?= htmlspecialchars($v['sku']) ?></td><td><?= $v['size'] ?></td><td><?= $v['color'] ?></td><td class="font-bold"><?= $v['stock'] ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <div style="display:flex;gap:var(--sp-3);justify-content:flex-end;margin-top:var(--sp-6);">
                <a href="<?= APP_URL ?>/index.php?page=products" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary"><i data-lucide="save" style="width:16px;height:16px;"></i> <?= $product ? 'Update' : 'Simpan' ?> Produk</button>
            </div>
        </form>
    </div>
</div>

<script>
function calcMargin() {
    const cost = parseFloat(document.getElementById('costPrice').value) || 0;
    const sell = parseFloat(document.getElementById('sellPrice').value) || 0;
    const el = document.getElementById('marginDisplay');
    if (cost > 0 && sell > 0) {
        const margin = ((sell - cost) / sell * 100).toFixed(1);
        const profit = sell - cost;
        el.innerHTML = `<span class="badge ${margin >= 30 ? 'badge-success' : 'badge-warning'}">Margin: ${margin}%</span> <span style="margin-left:8px;">Profit: Rp ${Number(profit).toLocaleString('id-ID')} / pcs</span>`;
    } else { el.innerHTML = ''; }
}
calcMargin();
</script>
