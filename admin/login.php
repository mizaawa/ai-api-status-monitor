<?php
/**
 * 管理员登录
 */
require_once __DIR__ . '/../config.php';
session_start();

if (isset($_SESSION['admin_id'])) {
    header('Location: ' . site_url('admin/index.php'));
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$error = '';

// 简单的登录限速：基于会话记录失败次数，连续失败后短时锁定，减缓暴力破解
$MAX_ATTEMPTS = 5;
$LOCK_SECONDS = 300;
$now = time();
$attempts = (int)($_SESSION['login_attempts'] ?? 0);
$lockUntil = (int)($_SESSION['login_lock_until'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($lockUntil > $now) {
        $wait = (int)ceil(($lockUntil - $now) / 60);
        $error = "尝试次数过多，请在约 {$wait} 分钟后再试";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if (admin_login($username, $password)) {
            unset($_SESSION['login_attempts'], $_SESSION['login_lock_until']);
            header('Location: ' . site_url('admin/index.php'));
            exit;
        }
        $attempts++;
        $_SESSION['login_attempts'] = $attempts;
        if ($attempts >= $MAX_ATTEMPTS) {
            $_SESSION['login_lock_until'] = $now + $LOCK_SECONDS;
            $_SESSION['login_attempts'] = 0;
            $error = "尝试次数过多，已临时锁定，请约 " . ($LOCK_SECONDS / 60) . " 分钟后再试";
        } else {
            // 失败后做轻微延迟，进一步拖慢自动化爆破
            usleep(300000);
            $remaining = $MAX_ATTEMPTS - $attempts;
            $error = "用户名或密码错误（还可尝试 {$remaining} 次）";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - <?= site_title() ?></title>
    <?= site_icon_tag() ?>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md mx-4">
        <div class="bg-white rounded-xl shadow-lg p-8">
            <div class="text-center mb-8">
                <span class="text-4xl">🤖</span>
                <h1 class="text-2xl font-bold text-gray-800 mt-2">管理员登录</h1>
            </div>
            <?php if ($error): ?>
            <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-600 text-sm"><?= h($error) ?></div>
            <?php endif; ?>
            <form method="post" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">用户名</label>
                    <input type="text" name="username" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">密码</label>
                    <input type="password" name="password" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white py-2.5 rounded-lg hover:bg-blue-700 font-medium transition">登录</button>
            </form>
            <div class="mt-4 text-center">
                <a href="<?= h(site_url('index.php')) ?>" class="text-sm text-gray-400 hover:text-gray-600 transition">← 返回首页</a>
            </div>
        </div>
    </div>
</body>
</html>