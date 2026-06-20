<?php
/**
 * 退出登录
 */
require_once __DIR__ . '/../includes/auth.php';
admin_logout();
header('Location: ' . site_url('admin/login.php'));
exit;