<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Toko JK Pasar Jati — Sistem Manajemen Penjualan Modern">
    <title><?= htmlspecialchars($pageTitle) ?> — Toko JK Pasar Jati</title>

    <!-- Frameworks CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/animate.css@4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <!-- App CSS -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css?v=<?= time() ?>">

    <!-- Frameworks JS (defer) -->
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.49.0/dist/apexcharts.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.344.0/dist/umd/lucide.min.js"></script>
</head>
<body>
<?php $user = currentUser(); $currentPage = $page; ?>
<div class="app-layout">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon">JK</div>
            <span class="brand-text">Toko JK</span>
        </div>

        <nav class="sidebar-nav">
            <a href="<?= APP_URL ?>/index.php?page=dashboard" class="nav-item <?= $currentPage==='dashboard'?'active':'' ?>">
                <i data-lucide="layout-dashboard"></i>
                <span class="nav-label">Dashboard</span>
            </a>
            <a href="<?= APP_URL ?>/index.php?page=pos" class="nav-item <?= $currentPage==='pos'?'active':'' ?>">
                <i data-lucide="shopping-cart"></i>
                <span class="nav-label">Point of Sale</span>
            </a>
            <a href="<?= APP_URL ?>/index.php?page=products" class="nav-item <?= in_array($currentPage,['products','products-form'])?'active':'' ?>">
                <i data-lucide="package"></i>
                <span class="nav-label">Produk</span>
            </a>
            <?php if (isAdmin()): ?>
            <a href="<?= APP_URL ?>/index.php?page=inventory" class="nav-item <?= $currentPage==='inventory'?'active':'' ?>">
                <i data-lucide="warehouse"></i>
                <span class="nav-label">Inventaris</span>
            </a>
            <?php endif; ?>
            <a href="<?= APP_URL ?>/index.php?page=transactions" class="nav-item <?= in_array($currentPage,['transactions','transaction-detail'])?'active':'' ?>">
                <i data-lucide="file-text"></i>
                <span class="nav-label">Transaksi</span>
            </a>
            <?php if (isAdmin()): ?>
            <a href="<?= APP_URL ?>/index.php?page=reports" class="nav-item <?= $currentPage==='reports'?'active':'' ?>">
                <i data-lucide="bar-chart-3"></i>
                <span class="nav-label">Laporan</span>
            </a>

            <div class="nav-separator"><span class="nav-separator-text">Pengaturan</span></div>

            <a href="<?= APP_URL ?>/index.php?page=users" class="nav-item <?= $currentPage==='users'?'active':'' ?>">
                <i data-lucide="users"></i>
                <span class="nav-label">Pengguna</span>
            </a>
            <?php endif; ?>
            <a href="<?= APP_URL ?>/index.php?page=settings" class="nav-item <?= $currentPage==='settings'?'active':'' ?>">
                <i data-lucide="settings"></i>
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
                <i data-lucide="log-out"></i>
                <span class="nav-label">Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <header class="top-header">
            <div class="header-left">
                <button class="toggle-sidebar" onclick="toggleSidebar()">
                    <i data-lucide="menu" style="width:20px;height:20px;"></i>
                </button>
                <h1 class="page-title"><?= htmlspecialchars($pageTitle) ?></h1>
            </div>
            <div class="header-right">
                <span class="text-sm text-secondary" id="headerClock"><?= date('d M Y, H:i') ?></span>
                <div class="user-avatar" style="width:32px;height:32px;font-size:0.75rem;"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
            </div>
        </header>

        <div class="page-content animate__animated animate__fadeIn" style="animation-duration:0.3s;">
            <?php
            $flash = getFlash();
            if ($flash): ?>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const Swal2 = window.Swal;
                if (Swal2) {
                    Swal2.fire({
                        toast: true,
                        position: 'top-end',
                        icon: '<?= $flash['type'] === 'error' ? 'error' : 'success' ?>',
                        title: '<?= addslashes(htmlspecialchars($flash['message'])) ?>',
                        showConfirmButton: false,
                        timer: 3500,
                        timerProgressBar: true,
                        background: '#FFFFFF',
                        color: '#1E293B',
                        customClass: { popup: 'animate__animated animate__slideInRight' }
                    });
                }
            });
            </script>
            <?php endif; ?>

            <?php require_once __DIR__ . '/pages/' . str_replace('-', '_', $page) . '.php'; ?>
        </div>
    </main>
</div>

<script>
// Initialize Lucide icons
document.addEventListener('DOMContentLoaded', function() {
    if (window.lucide) lucide.createIcons();
});

// Sidebar toggle
function toggleSidebar() {
    const sb = document.getElementById('sidebar');
    if (window.innerWidth <= 1024) {
        sb.classList.toggle('open');
    } else {
        sb.classList.toggle('collapsed');
    }
}

// Live clock in header
function updateHeaderClock() {
    const el = document.getElementById('headerClock');
    if (el) {
        const now = new Date();
        const opts = { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' };
        el.textContent = now.toLocaleDateString('id-ID', opts);
    }
}
setInterval(updateHeaderClock, 30000);
</script>
</body>
</html>
