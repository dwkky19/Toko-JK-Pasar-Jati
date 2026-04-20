<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Toko JK Pasar Jati</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
</head>
<body>
<div class="login-page">
    <div class="login-card">
        <div class="login-brand">
            <div class="brand-icon" style="font-size:var(--fs-md);">JK</div>
            <h1>Toko JK Pasar Jati</h1>
            <p>Sistem Manajemen Penjualan</p>
        </div>

        <?php $flash = getFlash(); if ($flash): ?>
        <div class="login-error">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            <?= htmlspecialchars($flash['message']) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="<?= APP_URL ?>/index.php?page=login">
            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-input" placeholder="Masukkan username" required autofocus>
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-input" placeholder="Masukkan password" required>
            </div>
            <button type="submit" class="login-btn">Masuk</button>
        </form>

        <p style="text-align:center; margin-top: 1.5rem; font-size: 0.75rem; color: var(--text-muted);">
            Demo: admin / admin123 &nbsp;|&nbsp; kasir / kasir123
        </p>
    </div>
</div>
</body>
</html>
