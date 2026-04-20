<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — Toko JK Pasar Jati</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
</head>
<body>
<?php $user = currentUser(); $currentPage = $page; ?>
<div class="app-layout">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon">JK</div>
            <span class="brand-text">Toko JK Pasar Jati</span>
        </div>

        <nav class="sidebar-nav">
            <a href="<?= APP_URL ?>/index.php?page=dashboard" class="nav-item <?= $currentPage==='dashboard'?'active':'' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                <span class="nav-label">Dashboard</span>
            </a>
            <a href="<?= APP_URL ?>/index.php?page=pos" class="nav-item <?= $currentPage==='pos'?'active':'' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                <span class="nav-label">Point of Sale</span>
            </a>
            <a href="<?= APP_URL ?>/index.php?page=products" class="nav-item <?= in_array($currentPage,['products','products-form'])?'active':'' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                <span class="nav-label">Produk</span>
            </a>
            <?php if (isAdmin()): ?>
            <a href="<?= APP_URL ?>/index.php?page=inventory" class="nav-item <?= $currentPage==='inventory'?'active':'' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                <span class="nav-label">Inventaris</span>
            </a>
            <?php endif; ?>
            <a href="<?= APP_URL ?>/index.php?page=transactions" class="nav-item <?= in_array($currentPage,['transactions','transaction-detail'])?'active':'' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                <span class="nav-label">Transaksi</span>
            </a>
            <?php if (isAdmin()): ?>
            <a href="<?= APP_URL ?>/index.php?page=reports" class="nav-item <?= $currentPage==='reports'?'active':'' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                <span class="nav-label">Laporan</span>
            </a>

            <div class="nav-separator"><span class="nav-separator-text">Pengaturan</span></div>

            <a href="<?= APP_URL ?>/index.php?page=users" class="nav-item <?= $currentPage==='users'?'active':'' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span class="nav-label">Pengguna</span>
            </a>
            <?php endif; ?>
            <a href="<?= APP_URL ?>/index.php?page=settings" class="nav-item <?= $currentPage==='settings'?'active':'' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                <span class="nav-label">Pengaturan</span>
            </a>
        </nav>

        <div class="sidebar-footer" style="position:relative; z-index:10;">
            <div class="sidebar-user">
                <div class="user-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
                <div class="user-info">
                    <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
                    <div class="user-role"><?= $user['role'] ?></div>
                </div>
            </div>
            <a href="<?= APP_URL ?>/index.php?page=logout" class="nav-item logout-btn" id="logoutBtn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                <span class="nav-label">Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <header class="top-header">
            <div class="header-left">
                <button class="toggle-sidebar" onclick="toggleSidebar()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <h1 class="page-title"><?= htmlspecialchars($pageTitle) ?></h1>
            </div>
            <div class="header-right">
                <span class="text-sm text-secondary"><?= date('d M Y, H:i') ?></span>
                <div class="user-avatar" style="width:32px;height:32px;font-size:0.75rem;"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
            </div>
        </header>

        <div class="page-content fade-in-up">
            <?php
            $flash = getFlash();
            if ($flash): ?>
            <div class="toast-container" id="toastContainer">
                <div class="toast <?= $flash['type'] ?>" onclick="this.remove()">
                    <?= htmlspecialchars($flash['message']) ?>
                </div>
            </div>
            <script>setTimeout(()=>{document.getElementById('toastContainer')?.remove()},4000);</script>
            <?php endif; ?>

            <?php require_once __DIR__ . '/pages/' . str_replace('-', '_', $page) . '.php'; ?>
        </div>
    </main>
</div>

<script>
function toggleSidebar() {
    const sb = document.getElementById('sidebar');
    if (window.innerWidth <= 1024) {
        sb.classList.toggle('open');
    } else {
        sb.classList.toggle('collapsed');
    }
}

// Auto-hide toast
document.querySelectorAll('.toast').forEach(t => {
    setTimeout(() => t.style.opacity = '0', 3500);
    setTimeout(() => t.remove(), 4000);
});
</script>
</body>
</html>
