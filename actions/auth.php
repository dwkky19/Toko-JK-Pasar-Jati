<?php
// Auth handler
function handleLogin() {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        setFlash('error', 'Username dan password harus diisi.');
        header('Location: ' . APP_URL . '/index.php?page=login');
        exit;
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        setFlash('error', 'Username atau password salah.');
        header('Location: ' . APP_URL . '/index.php?page=login');
        exit;
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_username'] = $user['username'];
    $_SESSION['user_role'] = $user['role'];

    $redirect = $user['role'] === 'admin' ? 'dashboard' : 'pos';
    header('Location: ' . APP_URL . '/index.php?page=' . $redirect);
    exit;
}
