<?php
/**
 * 后台首页
 */
require_once __DIR__ . '/../includes/auth.php';
require_admin();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$groupCount = count(db_all('groups'));
$channelCount = count(db_all('channels'));
$activeChannels = count(db_all('channels', 'is_active = 1'));

// 最近检测记录
$recentLogs = [];
try {
    $stmt = db()->query(
        "SELECT l.*, c.name as channel_name, g.name as group_name
         FROM " . tn('monitor_logs') . " l
         JOIN " . tn('channels') . " c ON l.channel_id = c.id
         JOIN " . tn('groups') . " g ON c.group_id = g.id
         ORDER BY l.checked_at DESC LIMIT 10"
    );
    $recentLogs = $stmt->fetchAll();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>后台管理 - <?= site_title() ?></title>
    <?= site_icon_tag() ?>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <!-- 侧边栏 -->
        <aside class="w-64 bg-gray-900 text-white">
            <div class="p-5 border-b border-gray-800">
                <h1 class="text-lg font-bold">🤖 管理后台</h1>
            </div>
            <nav class="p-4 space-y-1">
                <a href="index.php" class="flex items-center space-x-3 px-4 py-2.5 bg-blue-600 rounded-lg text-sm font-medium">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    <span>仪表盘</span>
                </a>
                <a href="groups.php" class="flex items-center space-x-3 px-4 py-2.5 text-gray-300 hover:bg-gray-800 rounded-lg text-sm transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    <span>分组管理</span>
                </a>
                <a href="channels.php" class="flex items-center space-x-3 px-4 py-2.5 text-gray-300 hover:bg-gray-800 rounded-lg text-sm transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span>渠道管理</span>
                </a>
                <a href="settings.php" class="flex items-center space-x-3 px-4 py-2.5 text-gray-300 hover:bg-gray-800 rounded-lg text-sm transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span>网站设置</span>
                </a>
                <a href="editor.php" class="flex items-center space-x-3 px-4 py-2.5 text-gray-300 hover:bg-gray-800 rounded-lg text-sm transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    <span>首页编辑</span>
                </a>
                <hr class="my-3 border-gray-800">
                <a href="../index.php" class="flex items-center space-x-3 px-4 py-2.5 text-gray-300 hover:bg-gray-800 rounded-lg text-sm transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <span>返回首页</span>
                </a>
                <a href="logout.php" class="flex items-center space-x-3 px-4 py-2.5 text-gray-300 hover:bg-gray-800 rounded-lg text-sm transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    <span>退出登录</span>
                </a>
            </nav>
        </aside>

        <!-- 主内容 -->
        <main class="flex-1 p-8">
            <div class="mb-8">
                <h2 class="text-2xl font-bold text-gray-800">仪表盘</h2>
                <p class="text-gray-500">欢迎回来，<?= h($_SESSION['admin_name']) ?></p>
            </div>

            <!-- 统计数据 -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm text-gray-500">分组总数</span>
                        <span class="text-blue-600">📦</span>
                    </div>
                    <p class="text-3xl font-bold text-gray-800"><?= $groupCount ?></p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm text-gray-500">渠道总数</span>
                        <span class="text-blue-600">🔌</span>
                    </div>
                    <p class="text-3xl font-bold text-gray-800"><?= $channelCount ?></p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm text-gray-500">在线渠道</span>
                        <span class="text-green-600">✅</span>
                    </div>
                    <p class="text-3xl font-bold text-gray-800"><?= $activeChannels ?></p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm text-gray-500">检测间隔</span>
                        <span class="text-blue-600">⏱️</span>
                    </div>
                    <p class="text-3xl font-bold text-gray-800"><?= get_setting('check_interval', '60') ?>s</p>
                </div>
            </div>

            <!-- 最近检测记录 -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-800">最近检测记录</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 text-gray-500">
                                <th class="text-left px-6 py-3 font-medium">时间</th>
                                <th class="text-left px-6 py-3 font-medium">分组</th>
                                <th class="text-left px-6 py-3 font-medium">渠道</th>
                                <th class="text-left px-6 py-3 font-medium">延迟</th>
                                <th class="text-left px-6 py-3 font-medium">状态码</th>
                                <th class="text-left px-6 py-3 font-medium">错误信息</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php if (empty($recentLogs)): ?>
                            <tr><td colspan="6" class="px-6 py-8 text-center text-gray-400">暂无检测记录</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentLogs as $log): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-3 text-gray-600"><?= h($log['checked_at']) ?></td>
                                    <td class="px-6 py-3"><?= h($log['group_name']) ?></td>
                                    <td class="px-6 py-3 font-medium"><?= h($log['channel_name']) ?></td>
                                    <td class="px-6 py-3">
                                        <span class="font-mono <?= $log['latency'] < 5000 ? 'text-green-600' : ($log['latency'] < 20000 ? 'text-red-600' : 'text-yellow-600') ?>">
                                            <?= format_latency((int)$log['latency']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-3">
                                        <span class="px-2 py-0.5 rounded text-xs <?= $log['status_code'] == 200 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                                            <?= $log['status_code'] ?: '--' ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 text-gray-400 max-w-xs truncate"><?= h($log['error_message'] ?? '') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>