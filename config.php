<?php
// ============================================
// CONFIG — Database & App Configuration
// ============================================

// --- Secure session configuration ---
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.gc_maxlifetime', 3600); // 1 hour session lifetime

// --- Security: Set Secure cookie flag when on HTTPS ---
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', 1);
}

session_start();

define('APP_NAME', 'Toko JK Pasar Jati');
define('APP_URL', '/penjualan');
define('DB_HOST', 'localhost');
define('DB_NAME', 'toko_jk_pasar_jati');
define('DB_USER', 'root');
define('DB_PASS', '');

// --- Security: Rate limit storage directory ---
define('RATE_LIMIT_DIR', sys_get_temp_dir() . '/tokojk_ratelimit');

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
            // Log the real error, show generic message to user
            error_log('Database connection failed: ' . $e->getMessage());
            die(json_encode(['error' => 'Koneksi database gagal. Hubungi administrator.']));
        }
    }
    return $pdo;
}

// --- XSS Protection Helper ---
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
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
        // Check if this is an AJAX/API request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
            || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Sesi telah berakhir. Silakan login kembali.']);
            exit;
        }
        header('Location: ' . APP_URL . '/index.php?page=login');
        exit;
    }
    // Validate session integrity
    checkSessionSecurity();
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        http_response_code(403);
        echo '<h1>403 — Akses Ditolak</h1>';
        exit;
    }
}

// --- Session Security (Enhanced with IP binding) ---
function checkSessionSecurity() {
    // Check user agent consistency (detect session hijacking)
    $currentUA = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (isset($_SESSION['_user_agent'])) {
        if ($_SESSION['_user_agent'] !== hash('sha256', $currentUA)) {
            // Possible session hijacking — destroy session
            destroySession();
            header('Location: ' . APP_URL . '/index.php?page=login');
            exit;
        }
    }

    // --- Security: Check IP binding (partial — first 3 octets for mobile support) ---
    $currentIP = getClientIPHash();
    if (isset($_SESSION['_ip_hash'])) {
        if ($_SESSION['_ip_hash'] !== $currentIP) {
            // IP changed significantly — possible session hijacking
            destroySession();
            header('Location: ' . APP_URL . '/index.php?page=login');
            exit;
        }
    }

    // Check session last activity (auto-expire after 1 hour of inactivity)
    if (isset($_SESSION['_last_activity']) && (time() - $_SESSION['_last_activity']) > 3600) {
        destroySession();
        header('Location: ' . APP_URL . '/index.php?page=login');
        exit;
    }
    $_SESSION['_last_activity'] = time();
}

// --- Security: Get partial IP hash (first 3 octets for IPv4) ---
function getClientIPHash() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    // For IPv4, hash first 3 octets to allow last-octet changes (mobile networks)
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        $partial = $parts[0] . '.' . $parts[1] . '.' . $parts[2];
        return hash('sha256', $partial);
    }
    // For IPv6, hash first 4 groups
    return hash('sha256', substr($ip, 0, strrpos($ip, ':')));
}

function getClientIP() {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function destroySession() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

function regenerateSession() {
    session_regenerate_id(true);
    $_SESSION['_user_agent'] = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    $_SESSION['_ip_hash'] = getClientIPHash();
    $_SESSION['_last_activity'] = time();
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

// --- CSRF Protection (with token rotation) ---
function generateCSRFToken() {
    // Always generate a fresh token for each form render
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
    // Rotate token after successful validation (single-use)
    unset($_SESSION['csrf_token']);
    return true;
}

// Validate CSRF for AJAX/JSON requests (reads from header)
function validateCSRFAjax() {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        return false;
    }
    // Rotate token after use
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return true;
}

// Get current CSRF token without regenerating (for AJAX pages that need it)
function getCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// --- Rate Limiting (IP-based, file-backed — NOT session-based) ---
function checkRateLimit($key, $maxAttempts = 5, $windowSeconds = 900) {
    $ip = getClientIP();
    $file = getRateLimitFile($key, $ip);
    $now = time();

    $attempts = readRateLimitData($file);

    // Remove old attempts outside the window
    $attempts = array_filter($attempts, function($timestamp) use ($now, $windowSeconds) {
        return ($now - $timestamp) < $windowSeconds;
    });

    // Write back cleaned data
    writeRateLimitData($file, $attempts);

    if (count($attempts) >= $maxAttempts) {
        return false; // Rate limited
    }

    return true; // Allowed
}

function recordRateLimit($key) {
    $ip = getClientIP();
    $file = getRateLimitFile($key, $ip);

    $attempts = readRateLimitData($file);
    $attempts[] = time();

    writeRateLimitData($file, $attempts);
}

function clearRateLimit($key) {
    $ip = getClientIP();
    $file = getRateLimitFile($key, $ip);
    if (file_exists($file)) {
        @unlink($file);
    }
}

function getRateLimitFile($key, $ip) {
    $dir = RATE_LIMIT_DIR;
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    // Use hash to prevent directory traversal via key or IP
    $safeKey = hash('sha256', $key . '_' . $ip);
    return $dir . '/' . $safeKey . '.json';
}

function readRateLimitData($file) {
    if (!file_exists($file)) return [];
    $content = @file_get_contents($file);
    if ($content === false) return [];
    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

function writeRateLimitData($file, $data) {
    @file_put_contents($file, json_encode(array_values($data)), LOCK_EX);
}

// --- Security: Cleanup old rate limit files (call occasionally) ---
function cleanupRateLimitFiles($maxAge = 3600) {
    $dir = RATE_LIMIT_DIR;
    if (!is_dir($dir)) return;
    foreach (glob($dir . '/*.json') as $file) {
        if (filemtime($file) < (time() - $maxAge)) {
            @unlink($file);
        }
    }
}

// --- Security: Password Complexity Validation ---
function validatePasswordComplexity($password) {
    if (strlen($password) < 8) {
        return 'Password minimal 8 karakter.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return 'Password harus mengandung minimal 1 huruf besar.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        return 'Password harus mengandung minimal 1 huruf kecil.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        return 'Password harus mengandung minimal 1 angka.';
    }
    return null; // Valid
}

// --- Security: Secure password hashing with explicit cost ---
function securePasswordHash($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

// --- Security: Secure file upload processing ---
function processUploadedImage($tmpFile, $uploadDir, $prefix = 'product') {
    // Validate MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($tmpFile);
    $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    if (!isset($allowedMimes[$mimeType])) {
        return ['error' => 'Tipe file tidak valid. File harus berupa gambar JPG, PNG, atau WebP.'];
    }

    // Validate it's a real image
    $imageInfo = @getimagesize($tmpFile);
    if ($imageInfo === false) {
        return ['error' => 'File bukan gambar yang valid.'];
    }

    // Check file size (max 2MB)
    if (filesize($tmpFile) > 2 * 1024 * 1024) {
        return ['error' => 'Ukuran file maksimal 2MB.'];
    }

    // Determine extension from MIME (not user input)
    $ext = $allowedMimes[$mimeType];

    // Generate secure random filename
    $filename = $prefix . '_' . bin2hex(random_bytes(16)) . '.' . $ext;

    // Ensure upload directory exists
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $destPath = $uploadDir . $filename;

    // --- Security: Re-process image through GD to strip EXIF/embedded code ---
    $reprocessed = false;
    if (function_exists('imagecreatefromjpeg') && $mimeType === 'image/jpeg') {
        $img = @imagecreatefromjpeg($tmpFile);
        if ($img !== false) {
            imagejpeg($img, $destPath, 90);
            imagedestroy($img);
            $reprocessed = true;
        }
    } elseif (function_exists('imagecreatefrompng') && $mimeType === 'image/png') {
        $img = @imagecreatefrompng($tmpFile);
        if ($img !== false) {
            imagesavealpha($img, true);
            imagepng($img, $destPath, 8);
            imagedestroy($img);
            $reprocessed = true;
        }
    } elseif (function_exists('imagecreatefromwebp') && $mimeType === 'image/webp') {
        $img = @imagecreatefromwebp($tmpFile);
        if ($img !== false) {
            imagewebp($img, $destPath, 90);
            imagedestroy($img);
            $reprocessed = true;
        }
    }

    // Fallback: move the file if GD failed
    if (!$reprocessed) {
        move_uploaded_file($tmpFile, $destPath);
    }

    // Verify the output file is still a valid image
    if (!@getimagesize($destPath)) {
        @unlink($destPath);
        return ['error' => 'Gagal memproses gambar.'];
    }

    return ['filename' => $filename];
}

// --- Security Headers (Enhanced with CSP) ---
function sendSecurityHeaders() {
    // Prevent clickjacking
    header('X-Frame-Options: DENY');
    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');
    // XSS Protection (legacy browsers)
    header('X-XSS-Protection: 1; mode=block');
    // Referrer policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // Permissions policy
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    // --- Security: Content Security Policy ---
    $csp = implode('; ', [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net",
        "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
        "img-src 'self' data: blob:",
        "font-src 'self' https://cdn.jsdelivr.net",
        "connect-src 'self'",
        "frame-src 'none'",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
    ]);
    header('Content-Security-Policy: ' . $csp);

    // Cache control for authenticated pages
    if (isLoggedIn()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
}
