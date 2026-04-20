<?php
// ============================================
// CONFIG — Database & App Configuration
// ============================================
session_start();

define('APP_NAME', 'Toko JK Pasar Jati');
define('APP_URL', '/penjualan');
define('DB_HOST', 'localhost');
define('DB_NAME', 'toko_jk_pasar_jati');
define('DB_USER', 'root');
define('DB_PASS', '');

// Database connection
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// Auth helpers
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function currentUser() {
    if (!isLoggedIn()) return null;
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'username' => $_SESSION['user_username'],
        'role' => $_SESSION['user_role'],
    ];
}

function isAdmin() {
    return isLoggedIn() && $_SESSION['user_role'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/index.php?page=login');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        http_response_code(403);
        echo '<h1>403 — Akses Ditolak</h1>';
        exit;
    }
}

// Format currency
function formatRupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

// Generate invoice number
function generateInvoice() {
    $date = date('Ymd');
    $prefix = 'TRX-' . $date . '-';
    $db = getDB();
    $stmt = $db->prepare("SELECT invoice_number FROM transactions WHERE invoice_number LIKE ? ORDER BY invoice_number DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    if ($last) {
        $lastNum = (int)substr($last, -3);
        $count = $lastNum + 1;
    } else {
        $count = 1;
    }
    return $prefix . str_pad($count, 3, '0', STR_PAD_LEFT);
}

// Flash message
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// CSRF Protection
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

function validateCSRF() {
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        setFlash('error', 'Sesi keamanan tidak valid. Silakan coba lagi.');
        return false;
    }
    return true;
}
