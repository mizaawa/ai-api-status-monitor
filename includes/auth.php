<?php
/**
 * 认证模块
 */
require_once __DIR__ . '/db.php';

function is_admin_logged_in(): bool {
    return isset($_SESSION['admin_id']) && $_SESSION['admin_id'] > 0;
}

function require_admin(): void {
    session_start();
    if (!is_admin_logged_in()) {
        header('Location: admin/login.php');
        exit;
    }
}

function admin_login(string $username, string $password): bool {
    $user = db_get('users', 'username = ?', [$username]);
    if ($user && password_verify($password, $user['password'])) {
        session_start();
        $_SESSION['admin_id'] = (int)$user['id'];
        $_SESSION['admin_name'] = $user['username'];
        return true;
    }
    return false;
}

function admin_logout(): void {
    session_start();
    session_destroy();
}

function get_current_admin(): ?array {
    if (!isset($_SESSION['admin_id'])) return null;
    return db_get('users', 'id = ?', [$_SESSION['admin_id']]);
}