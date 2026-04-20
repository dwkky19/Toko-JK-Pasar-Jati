<?php
$db = getDB();
$tab = $_GET['tab'] ?? 'summary';
$suppliers = $db->query("SELECT * FROM suppliers ORDER BY name")->fetchAll();
$variants = $db->query("SELECT pv.*, p.name as product_name FROM product_variants pv JOIN products p ON pv.product_id = p.id ORDER BY p.name, pv.color, pv.size")->fetchAll();
$movements = $db->query("SELECT sm.*, pv.sku, pv.size, pv.color, p.name as product_name, u.name as user_name, s.name as supplier_name
    FROM stock_movements sm
    JOIN product_variants pv ON sm.variant_id = pv.id
    JOIN products p ON pv.product_id = p.id
    JOIN users u ON sm.user_id = u.id
    LEFT JOIN suppliers s ON sm.supplier_id = s.id
    ORDER BY sm.created_at DESC LIMIT 50")->fetchAll();
?>

<div class="tabs">
    <button class="tab-btn <?= $tab==='summary'?'active':'' ?>" onclick="switchTab('summary')"><i data-lucide="bar-chart-2" style="width:14px;height:14px;"></i> Ringkasan Stok</button>
    <button class="tab-btn <?= $tab==='in'?'active':'' ?>" onclick="switchTab('in')"><i data-lucide="arrow-down-circle" style="width:14px;height:14px;"></i> Stok Masuk</button>
    <button class="tab-btn <?= $tab==='out'?'active':'' ?>" onclick="switchTab('out')"><i data-lucide="arrow-up-circle" style="width:14px;height:14px;"></i> Stok Keluar</button>
    <button class="tab-btn <?= $tab==='history'?'active':'' ?>" onclick="switchTab('history')"><i data-lucide="history" style="width:14px;height:14px;"></i> Riwayat</button>
</div>

<!-- Summary Tab -->
<div class="tab-panel <?= $tab==='summary'?'active':'' ?>" id="tab-summary">
    <div class="table-wrapper">
        <table class="data-table">
            <thead><tr><th>Produk</th><th>SKU</th><th>Ukuran</th><th>Warna</th><th>Stok</th><th>Min</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($variants as $v):
                $status = $v['stock'] == 0 ? 'danger' : ($v['stock'] <= $v['min_stock'] ? 'warning' : 'success');
                $statusText = $v['stock'] == 0 ? '⛔ Habis' : ($v['stock'] <= $v['min_stock'] ? '⚠️ Low Stock' : '🟢 Aman');
            ?>
            <tr>
                <td class="font-bold"><?= htmlspecialchars($v['product_name']) ?></td>
                <td class="text-sm text-secondary"><?= htmlspecialchars($v['sku']) ?></td>
                <td><?= $v['size'] ?></td>
                <td><span class="color-dot" style="background:<?= getColorHexPHP($v['color']) ?>;vertical-align:middle;margin-right:4px;"></span><?= $v['color'] ?></td>
                <td class="font-bold text-<?= $status ?>"><?= $v['stock'] ?></td>
                <td class="text-secondary"><?= $v['min_stock'] ?></td>
                <td><span class="badge badge-<?= $status ?> <?= $status==='danger'?'pulse':'' ?>"><?= $statusText ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Stock In Tab -->
<div class="tab-panel <?= $tab==='in'?'active':'' ?>" id="tab-in">
    <div class="card" style="max-width:600px;">
        <h3 style="margin-bottom:var(--sp-4);font-weight:700;display:flex;align-items:center;gap:var(--sp-2);"><i data-lucide="arrow-down-circle" style="width:18px;height:18px;color:var(--success);"></i> Catat Stok Masuk</h3>
        <form method="POST" action="<?= APP_URL ?>/index.php?page=inventory&tab=in">
            <?= csrfField() ?>
            <input type="hidden" name="type" value="in">
            <div class="form-group">
                <label class="form-label">Pilih Varian Produk *</label>
                <select name="variant_id" class="form-select" required>
                    <option value="">-- Pilih Produk --</option>
                    <?php foreach ($variants as $v): ?>
                    <option value="<?= $v['id'] ?>"><?= $v['product_name'] ?> — <?= $v['color'] ?> <?= $v['size'] ?> (Stok: <?= $v['stock'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Jumlah *</label>
                    <input type="number" name="quantity" class="form-input" required min="1" placeholder="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Supplier</label>
                    <select name="supplier_id" class="form-select">
                        <option value="">-- Opsional --</option>
                        <?php foreach ($suppliers as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Catatan</label>
                <input type="text" name="reason" class="form-input" placeholder="Contoh: Restok dari supplier">
            </div>
            <button type="submit" class="btn btn-success"><i data-lucide="plus-circle" style="width:16px;height:16px;"></i> Tambah Stok</button>
        </form>
    </div>
</div>

<!-- Stock Out Tab -->
<div class="tab-panel <?= $tab==='out'?'active':'' ?>" id="tab-out">
    <div class="card" style="max-width:600px;">
        <h3 style="margin-bottom:var(--sp-4);font-weight:700;display:flex;align-items:center;gap:var(--sp-2);"><i data-lucide="arrow-up-circle" style="width:18px;height:18px;color:var(--danger);"></i> Catat Stok Keluar</h3>
        <form method="POST" action="<?= APP_URL ?>/index.php?page=inventory&tab=out">
            <?= csrfField() ?>
            <input type="hidden" name="type" value="out">
            <div class="form-group">
                <label class="form-label">Pilih Varian Produk *</label>
                <select name="variant_id" class="form-select" required>
                    <option value="">-- Pilih Produk --</option>
                    <?php foreach ($variants as $v): if ($v['stock'] > 0): ?>
                    <option value="<?= $v['id'] ?>"><?= $v['product_name'] ?> — <?= $v['color'] ?> <?= $v['size'] ?> (Stok: <?= $v['stock'] ?>)</option>
                    <?php endif; endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Jumlah *</label>
                <input type="number" name="quantity" class="form-input" required min="1" placeholder="0">
            </div>
            <div class="form-group">
                <label class="form-label">Alasan *</label>
                <select name="reason" class="form-select" required>
                    <option value="">-- Pilih Alasan --</option>
                    <option value="Retur">Retur</option>
                    <option value="Rusak">Barang Rusak</option>
                    <option value="Hilang">Hilang</option>
                    <option value="Lainnya">Lainnya</option>
                </select>
            </div>
            <button type="submit" class="btn btn-danger"><i data-lucide="minus-circle" style="width:16px;height:16px;"></i> Kurangi Stok</button>
        </form>
    </div>
</div>

<!-- History Tab -->
<div class="tab-panel <?= $tab==='history'?'active':'' ?>" id="tab-history">
    <div class="table-wrapper">
        <table class="data-table">
            <thead><tr><th>Tanggal</th><th>Produk</th><th>Varian</th><th>Tipe</th><th>Qty</th><th>Oleh</th><th>Catatan</th></tr></thead>
            <tbody>
            <?php foreach ($movements as $m): ?>
            <tr>
                <td class="text-sm"><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></td>
                <td class="font-bold"><?= htmlspecialchars($m['product_name']) ?></td>
                <td class="text-sm"><?= $m['color'] ?> - <?= $m['size'] ?></td>
                <td><span class="badge <?= $m['type']==='in'?'badge-success':'badge-danger' ?>"><?= $m['type']==='in'?'📥 Masuk':'📤 Keluar' ?></span></td>
                <td class="font-bold"><?= $m['type']==='in'?'+':'-' ?><?= $m['quantity'] ?></td>
                <td class="text-sm"><?= htmlspecialchars($m['user_name']) ?></td>
                <td class="text-sm text-secondary"><?= htmlspecialchars($m['reason'] ?? '') ?><?= $m['supplier_name'] ? ' ('.$m['supplier_name'].')' : '' ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($movements)): ?>
            <tr><td colspan="7" class="text-center text-secondary" style="padding:var(--sp-6);">Belum ada riwayat pergerakan stok</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
function getColorHexPHP($color) {
    $map = ['Red'=>'#e74c3c','Blue'=>'#3498db','Black'=>'#4a5568','White'=>'#e2e8f0','Pink'=>'#e91e90','Cream'=>'#f5e6ca','Grey'=>'#718096','Brown'=>'#8b4513','Green'=>'#27ae60','Yellow'=>'#f1c40f','Navy'=>'#1e3a5f'];
    return $map[$color] ?? '#64748B';
}
?>

<script>
function switchTab(name) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelector(`[onclick="switchTab('${name}')"]`).classList.add('active');
    document.getElementById('tab-' + name).classList.add('active');
}
</script>
