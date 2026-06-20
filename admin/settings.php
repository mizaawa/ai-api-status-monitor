<?php
/**
 * 网站设置
 */
require_once __DIR__ . '/../includes/auth.php';
require_admin();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'site_settings') {
        $baseUrl = normalize_site_base_url((string)($_POST['site_base_url'] ?? ''));
        if ($baseUrl !== '' && !preg_match('#^https?://#i', $baseUrl)) {
            $error = '基础 URL 必须以 http:// 或 https:// 开头';
        } else {
            $newConfig = load_config();
            $newConfig['site_base_url'] = $baseUrl;

            if (!save_config($newConfig)) {
                $error = '基础 URL 保存失败，请确认 data/config.php 或 data 目录可写';
            } else {
                $GLOBALS['config'] = $newConfig;
                apply_site_base_url_override($newConfig);
                set_setting('site_name', trim($_POST['site_name'] ?? 'AI 监控面板'));
                set_setting('site_icon', trim($_POST['site_icon'] ?? ''));
                set_setting('check_interval', (string)(int)($_POST['check_interval'] ?? 60));
                set_setting('hero_kicker', trim($_POST['hero_kicker'] ?? 'Live Model Status'));
                set_setting('hero_title', trim($_POST['hero_title'] ?? 'Service Monitor'));
                set_setting('hero_description', trim($_POST['hero_description'] ?? '黑白极简状态面板，实时读取模型接口可用性、对话延迟、端点 PING 与最近检测轨迹。'));
                set_setting('hero_tags', trim($_POST['hero_tags'] ?? 'Liquid Glass UI,Realtime Insight,Black / White'));
                $message = '设置已保存';
            }
        }
    }

    if ($action === 'change_password') {
        $currentPw = $_POST['current_password'] ?? '';
        $newPw = $_POST['new_password'] ?? '';
        $confirmPw = $_POST['confirm_password'] ?? '';

        $admin = get_current_admin();
        if (!password_verify($currentPw, $admin['password'])) {
            $error = '当前密码不正确';
        } elseif (strlen($newPw) < 6) {
            $error = '新密码至少6个字符';
        } elseif ($newPw !== $confirmPw) {
            $error = '两次密码输入不一致';
        } else {
            db_update('users', ['password' => password_hash($newPw, PASSWORD_BCRYPT)], 'id = ?', [(int)$admin['id']]);
            $message = '密码已修改';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>网站设置 - <?= site_title() ?></title>
    <?= site_icon_tag() ?>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="flex flex-col md:flex-row min-h-screen">
        <!-- 侧边栏 -->
        <aside class="w-full md:w-64 bg-gray-900 text-white shrink-0">
            <div class="p-5 border-b border-gray-800 flex items-center justify-between">
                <h1 class="text-lg font-bold">🤖 管理后台</h1>
                <button type="button" onclick="document.getElementById('adminNav').classList.toggle('hidden')" class="md:hidden p-2 -mr-2 text-gray-300 hover:text-white" aria-label="切换菜单">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>
            <nav id="adminNav" class="hidden md:block p-3 md:p-4 space-y-1">
                <a href="<?= h(site_url('admin/index.php')) ?>" class="flex items-center space-x-3 px-4 py-2.5 text-gray-300 hover:bg-gray-800 rounded-lg text-sm transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    <span>仪表盘</span>
                </a>
                <a href="<?= h(site_url('admin/groups.php')) ?>" class="flex items-center space-x-3 px-4 py-2.5 text-gray-300 hover:bg-gray-800 rounded-lg text-sm transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    <span>分组管理</span>
                </a>
                <a href="<?= h(site_url('admin/channels.php')) ?>" class="flex items-center space-x-3 px-4 py-2.5 text-gray-300 hover:bg-gray-800 rounded-lg text-sm transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span>渠道管理</span>
                </a>
                <a href="<?= h(site_url('admin/settings.php')) ?>" class="flex items-center space-x-3 px-4 py-2.5 bg-blue-600 rounded-lg text-sm font-medium">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span>网站设置</span>
                </a>
                <a href="<?= h(site_url('admin/editor.php')) ?>" class="flex items-center space-x-3 px-4 py-2.5 text-gray-300 hover:bg-gray-800 rounded-lg text-sm transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    <span>首页编辑</span>
                </a>
                <hr class="my-3 border-gray-800">
                <a href="<?= h(site_url('index.php')) ?>" class="flex items-center space-x-3 px-4 py-2.5 text-gray-300 hover:bg-gray-800 rounded-lg text-sm transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <span>返回首页</span>
                </a>
                <a href="<?= h(site_url('admin/logout.php')) ?>" class="flex items-center space-x-3 px-4 py-2.5 text-gray-300 hover:bg-gray-800 rounded-lg text-sm transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    <span>退出登录</span>
                </a>
            </nav>
        </aside>

        <main class="flex-1 min-w-0 p-4 md:p-8">
            <div class="mb-8">
                <h2 class="text-2xl font-bold text-gray-800">网站设置</h2>
                <p class="text-gray-500">管理网站基本配置</p>
            </div>

            <?php if ($message): ?>
            <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm"><?= h($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-600 text-sm"><?= h($error) ?></div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- 基本设置 -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">基本设置</h3>
                    <form method="post" class="space-y-4">
                        <input type="hidden" name="action" value="site_settings">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">网站标题</label>
                            <input type="text" name="site_name" value="<?= h(get_setting('site_name', 'AI 监控面板')) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">站点基础 URL</label>
                            <input type="url" name="site_base_url" value="<?= h(configured_site_base_url()) ?>" placeholder="https://check.zakuzaku.cc" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <p class="mt-1 text-xs text-gray-400">内网穿透场景填写公网入口。保存后后台入口、登录跳转和前台请求会优先使用该地址。</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">网站图标 URL</label>
                            <input type="url" name="site_icon" value="<?= h(get_setting('site_icon', '')) ?>" placeholder="favicon.ico 或图片链接" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <p class="mt-1 text-xs text-gray-400">留空则使用默认图标</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">检测间隔（秒）</label>
                            <input type="number" name="check_interval" value="<?= (int)get_setting('check_interval', '60') ?>" min="10" max="3600" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <p class="mt-1 text-xs text-gray-400">前台自动刷新间隔，单位秒（10~3600）</p>
                        </div>

                        <div class="pt-4 border-t border-gray-100">
                            <h4 class="text-sm font-semibold text-gray-800 mb-3">首页 Hero 文案</h4>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">顶部小标题</label>
                                    <input type="text" name="hero_kicker" value="<?= h(get_setting('hero_kicker', 'Live Model Status')) ?>" placeholder="Live Model Status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">主标题</label>
                                    <input type="text" name="hero_title" value="<?= h(get_setting('hero_title', 'Service Monitor')) ?>" placeholder="Service Monitor" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <p class="mt-1 text-xs text-gray-400">首页会按第一个空格自动拆成两行展示。</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">描述文案</label>
                                    <textarea name="hero_description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="黑白极简状态面板，实时读取模型接口可用性、对话延迟、端点 PING 与最近检测轨迹。"><?= h(get_setting('hero_description', '黑白极简状态面板，实时读取模型接口可用性、对话延迟、端点 PING 与最近检测轨迹。')) ?></textarea>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">胶囊标签</label>
                                    <input type="text" name="hero_tags" value="<?= h(get_setting('hero_tags', 'Liquid Glass UI,Realtime Insight,Black / White')) ?>" placeholder="Liquid Glass UI,Realtime Insight,Black / White" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <p class="mt-1 text-xs text-gray-400">多个标签用英文逗号分隔。</p>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2.5 rounded-lg hover:bg-blue-700 font-medium transition text-sm">保存设置</button>
                    </form>
                </div>

                <!-- 修改密码 -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">修改密码</h3>
                    <form method="post" class="space-y-4">
                        <input type="hidden" name="action" value="change_password">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">当前密码</label>
                            <input type="password" name="current_password" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">新密码</label>
                            <input type="password" name="new_password" required minlength="6" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">确认新密码</label>
                            <input type="password" name="confirm_password" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <button type="submit" class="bg-gray-800 text-white px-6 py-2.5 rounded-lg hover:bg-gray-900 font-medium transition text-sm">修改密码</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>