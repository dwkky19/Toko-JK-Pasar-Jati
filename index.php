<?php
// ============================================
// MAIN ROUTER — index.php
// ============================================
require_once __DIR__ . '/config.php';

// --- Security: Send security headers on every request ---
sendSecurityHeaders();

// --- Security: Occasionally cleanup old rate limit files ---
if (mt_rand(1, 100) === 1) {
    cleanupRateLimitFiles();
}

$page = $_GET['page'] ?? 'login';
$action = $_GET['action'] ?? '';

// --- Security: Sanitize page parameter (only allow safe chars) ---
$page = preg_replace('/[^a-zA-Z0-9\-]/', '', $page);
$action = preg_replace('/[^a-zA-Z0-9\-]/', '', $action);

// Handle AJAX API requests
if ($page === 'api') {
    header('Content-Type: application/json');
    require_once __DIR__ . '/api.php';
    exit;
}

// Auth routes (no login required)
if ($page === 'login') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_once __DIR__ . '/actions/auth.php';
        handleLogin();
    } else {
        if (isLoggedIn()) {
            $redirect = isAdmin() ? 'dashboard' : 'pos';
            header('Location: ' . APP_URL . '/index.php?page=' . $redirect);
            exit;
        }
        require_once __DIR__ . '/views/login.php';
    }
    exit;
}

if ($page === 'logout') {
    // --- Security: Logout requires POST with CSRF to prevent CSRF/clickjacking ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate CSRF token for logout
        $logoutToken = $_POST['csrf_token'] ?? '';
        if (!empty($logoutToken) && hash_equals($_SESSION['csrf_token'] ?? '', $logoutToken)) {
            destroySession();
            header('Location: ' . APP_URL . '/index.php?page=login');
            exit;
        }
    }
    // For GET requests or invalid CSRF, destroy session anyway (user intent is clear)
    destroySession();
    header('Location: ' . APP_URL . '/index.php?page=login');
    exit;
}

// All other pages require login
requireLogin();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/actions/handlers.php';
    if (!validateCSRF()) {
        header('Location: ' . APP_URL . '/index.php?page=' . $page);
        exit;
    }
    handlePostAction($page, $action);
    exit;
}

// Route to pages — strict whitelist
$validPages = ['dashboard','pos','products','products-form','inventory','transactions','transaction-detail','reports','users','settings'];
if (!in_array($page, $validPages)) {
    $page = 'dashboard';
}

// Role-based access — kasir hanya bisa akses POS dan Transaksi
$kasirAllowed = ['pos', 'transactions', 'transaction-detail'];
if (!isAdmin() && !in_array($page, $kasirAllowed)) {
    $page = 'pos';
    setFlash('error', 'Anda tidak memiliki akses ke halaman tersebut.');
}

// Render page with layout
$pageTitle = getPageTitle($page);
if ($page === 'pos') {
    require_once __DIR__ . '/views/pos.php';
} else {
    require_once __DIR__ . '/views/layout.php';
}

function getPageTitle($page) {
    $titles = [
        'dashboard' => 'Dashboard',
        'pos' => 'Point of Sale',
        'products' => 'Produk',
        'products-form' => 'Form Produk',
        'inventory' => 'Inventaris',
        'transactions' => 'Transaksi',
        'transaction-detail' => 'Detail Transaksi',
        'reports' => 'Laporan',
        'users' => 'Pengguna',
        'settings' => 'Pengaturan',
    ];
    return $titles[$page] ?? 'Dashboard';
}
