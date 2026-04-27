<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Toko JK Pasar Jati</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css?v=<?= time() ?>">
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.344.0/dist/umd/lucide.min.js" crossorigin="anonymous"></script>
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
            <i data-lucide="alert-circle" style="width:16px;height:16px;flex-shrink:0;"></i>
            <?= htmlspecialchars($flash['message']) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="<?= APP_URL ?>/index.php?page=login" id="loginForm" autocomplete="off">
            <?= csrfField() ?>
            <div class="form-group">
                <label class="form-label">Username</label>
                <div style="position:relative;">
                    <input type="text" name="username" class="form-input" style="padding-left:40px;" placeholder="Masukkan username" required autofocus>
                    <i data-lucide="user" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);width:18px;height:18px;color:var(--text-muted);"></i>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <div style="position:relative;">
                    <input type="password" name="password" id="pwdInput" class="form-input" style="padding-left:40px;padding-right:40px;" placeholder="Masukkan password" required>
                    <i data-lucide="lock" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);width:18px;height:18px;color:var(--text-muted);"></i>
                    <button type="button" onclick="togglePwd()" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);padding:4px;">
                        <i data-lucide="eye" id="eyeIcon" style="width:18px;height:18px;"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="login-btn" id="loginBtn">
                <span id="loginText">Masuk</span>
            </button>
        </form>

        <p style="text-align:center; margin-top: 1.5rem; font-size: 0.75rem; color: var(--text-muted);">
            &copy; <?= date('Y') ?> Toko JK Pasar Jati
        </p>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() { if(window.lucide) lucide.createIcons(); });
function togglePwd() {
    const inp = document.getElementById('pwdInput');
    inp.type = inp.type === 'password' ? 'text' : 'password';
}
document.getElementById('loginForm').addEventListener('submit', function() {
    const btn = document.getElementById('loginBtn');
    btn.disabled = true;
    document.getElementById('loginText').textContent = 'Memproses...';
});
</script>
</body>
</html>
