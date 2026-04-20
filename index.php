<?php
// ============================================
// MAIN ROUTER — index.php
// ============================================
require_once __DIR__ . '/config.php';

$page = $_GET['page'] ?? 'login';
$action = $_GET['action'] ?? '';

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
            header('Location: ' . APP_URL . '/index.php?page=dashboard');
            exit;
        }
        require_once __DIR__ . '/views/login.php';
    }
    exit;
}

if ($page === 'logout') {
    // Clear all session variables
    $_SESSION = [];
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    // Destroy the session
    session_destroy();
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

// Route to pages
$validPages = ['dashboard','pos','products','products-form','inventory','transactions','transaction-detail','reports','users','settings'];
if (!in_array($page, $validPages)) {
    $page = 'dashboard';
}

// Role-based access
$adminOnly = ['inventory','reports','users','settings','products-form'];
if (in_array($page, $adminOnly) && !isAdmin()) {
    $page = 'dashboard';
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
