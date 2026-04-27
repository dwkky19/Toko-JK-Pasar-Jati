<?php
// Auth handler
function handleLogin() {
    if (!validateCSRF()) {
        header('Location: ' . APP_URL . '/index.php?page=login');
        exit;
    }

    // --- Security: Rate limiting (max 5 attempts per 15 minutes) ---
    if (!checkRateLimit('login')) {
        setFlash('error', 'Terlalu banyak percobaan login. Silakan tunggu 15 menit.');
        header('Location: ' . APP_URL . '/index.php?page=login');
        exit;
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        setFlash('error', 'Username dan password harus diisi.');
        header('Location: ' . APP_URL . '/index.php?page=login');
        exit;
    }

    // --- Security: Sanitize username (alphanumeric + underscore only) ---
    if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
        recordRateLimit('login');
        setFlash('error', 'Username atau password salah.');
        header('Location: ' . APP_URL . '/index.php?page=login');
        exit;
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        // Record failed attempt for rate limiting
        recordRateLimit('login');
        setFlash('error', 'Username atau password salah.');
        header('Location: ' . APP_URL . '/index.php?page=login');
        exit;
    }

    // --- Security: Clear rate limit on successful login ---
    clearRateLimit('login');

    // --- Security: Regenerate session ID to prevent session fixation ---
    regenerateSession();

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_username'] = $user['username'];
    $_SESSION['user_role'] = $user['role'];

    $redirect = $user['role'] === 'admin' ? 'dashboard' : 'pos';
    header('Location: ' . APP_URL . '/index.php?page=' . $redirect);
    exit;
}
