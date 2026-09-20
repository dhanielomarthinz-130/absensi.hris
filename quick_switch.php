<?php
// quick_switch.php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Auth.php';

$db = Database::getConnection();

if (isset($_GET['username'])) {
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([trim($_GET['username'])]);
    $userId = $stmt->fetchColumn();
} else {
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 1;
}

if ($userId && Auth::quickLogin((int)$userId)) {
    $role = Auth::role();
    if ($role === 'superadmin' || $role === 'admin') {
        header('Location: ' . BASE_URL . '/admin/index.php');
    } elseif ($role === 'supervisor') {
        header('Location: ' . BASE_URL . '/supervisor/index.php');
    } else {
        header('Location: ' . BASE_URL . '/operator/index.php');
    }
    exit;
}

header('Location: ' . BASE_URL . '/index.php');
exit;
