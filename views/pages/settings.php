<?php
$db = getDB();
$settings = [];
$stmt = $db->query("SELECT setting_key, setting_value FROM settings");
foreach ($stmt->fetchAll() as $s) $settings[$s['setting_key']] = $s['setting_value'];
?>

<div style="max-width:600px;">
    <div class="card">
        <h3 style="font-weight:700;margin-bottom:var(--sp-5);">⚙️ Pengaturan Toko</h3>
        <form method="POST" action="<?= APP_URL ?>/index.php?page=settings">
            <?= csrfField() ?>
            <div class="form-group">
                <label class="form-label">Nama Toko</label>
                <input type="text" name="store_name" class="form-input" value="<?= htmlspecialchars($settings['store_name'] ?? '') ?>" placeholder="Toko JK Pasar Jati">
            </div>
            <div class="form-group">
                <label class="form-label">Alamat</label>
                <input type="text" name="store_address" class="form-input" value="<?= htmlspecialchars($settings['store_address'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">No. Telepon</label>
                <input type="text" name="store_phone" class="form-input" value="<?= htmlspecialchars($settings['store_phone'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Footer Struk</label>
                <input type="text" name="receipt_footer" class="form-input" value="<?= htmlspecialchars($settings['receipt_footer'] ?? '') ?>" placeholder="Terima kasih telah berbelanja!">
            </div>
            <div class="form-group">
                <label class="form-label">Batas Minimum Stok Default</label>
                <input type="number" name="default_min_stock" class="form-input" value="<?= htmlspecialchars($settings['default_min_stock'] ?? '5') ?>" min="1">
            </div>
            <button type="submit" class="btn btn-primary">💾 Simpan Pengaturan</button>
        </form>
    </div>

    <?php if (isAdmin()): ?>
    <div class="card mt-6">
        <h3 style="font-weight:700;margin-bottom:var(--sp-5);">👤 Profil Saya</h3>
        <div style="display:flex;align-items:center;gap:var(--sp-4);margin-bottom:var(--sp-4);">
            <div class="user-avatar" style="width:56px;height:56px;font-size:var(--fs-xl);"><?= strtoupper(substr(currentUser()['name'], 0, 1)) ?></div>
            <div>
                <div class="font-bold" style="font-size:var(--fs-md);"><?= htmlspecialchars(currentUser()['name']) ?></div>
                <div class="text-secondary"><?= currentUser()['username'] ?> — <?= ucfirst(currentUser()['role']) ?></div>
            </div>
        </div>
        <p class="text-sm text-muted">Untuk mengubah password, gunakan menu Pengguna.</p>
    </div>
    <?php endif; ?>
</div>
