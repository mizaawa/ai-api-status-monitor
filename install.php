<?php
/**
 * 安装向导
 * 
 * 安装器全程使用表单提交的数据库凭证直接操作数据库，
 * 不依赖已写入的配置文件。
 * 配置文件：能自动生成最好，不能则显示代码让用户手动创建。
 */
require_once __DIR__ . '/config.php';

if (!function_exists('h')) {
    function h(mixed $value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

// 如果已安装则跳转
if (is_installed()) {
    header('Location: index.php');
    exit;
}

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';

/**
 * 生成 data/config.php 文件内容
 */
function build_config_content(array $cfg): string {
    $export = var_export($cfg, true);
    return <<<PHP
<?php
/**
 * 配置文件 - 由安装向导生成
 * 如果此文件不存在，请手动创建 data/ 目录并粘贴此内容
 */
return {$export};
PHP;
}

/**
 * 尝试写入配置文件，失败则返回需要手动创建的代码
 */
function try_save_config_file(array $cfg): string|true {
    if (!is_dir(CONFIG_DIR)) {
        @mkdir(CONFIG_DIR, 0755, true);
    }
    $content = build_config_content($cfg);
    if (@file_put_contents(CONFIG_FILE, $content) !== false) {
        return true;
    }
    // 写不了，返回给用户手动创建
    return $content;
}

/**
 * 用表单提交的凭证直接连接数据库（不依赖配置文件）
 */
function connect_db_from_post(): PDO {
    $host = trim($_POST['db_host'] ?? '127.0.0.1');
    $port = trim($_POST['db_port'] ?? '3306');
    $user = trim($_POST['db_user'] ?? 'root');
    $pass = $_POST['db_pass'] ?? '';
    $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

// ========== 请求处理 ==========

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ------ 步骤1: 数据库配置 ------
    if ($action === 'test_db') {
        $dbName = trim($_POST['db_name'] ?? 'ai_monitor');
        $dbPrefix = trim($_POST['db_prefix'] ?? 'ai_');

        try {
            $pdo = connect_db_from_post();
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$dbName}`");

            require_once __DIR__ . '/includes/functions.php';
            create_tables($pdo, $dbPrefix);

            // 保存表单数据到 session，后续步骤使用
            session_start();
            $_SESSION['install_db'] = [
                'db_host' => trim($_POST['db_host']),
                'db_port' => trim($_POST['db_port']),
                'db_name' => $dbName,
                'db_user' => trim($_POST['db_user']),
                'db_pass' => $_POST['db_pass'],
                'db_prefix' => $dbPrefix,
            ];

            $success = '数据库连接成功，表已创建！';

            // 尝试写入配置文件
            $newConfig = [
                'db_host' => trim($_POST['db_host']),
                'db_port' => trim($_POST['db_port']),
                'db_name' => $dbName,
                'db_user' => trim($_POST['db_user']),
                'db_pass' => $_POST['db_pass'],
                'db_prefix' => $dbPrefix,
                'site_name' => 'AI 监控面板',
                'site_icon' => '',
            ];
            $writeResult = try_save_config_file($newConfig);

            if ($writeResult === true) {
                // 自动写入成功，正常到下一步
                $step = 2;
            } else {
                // 自动写入失败，展示手动创建界面
                $_SESSION['install_config_content'] = $writeResult;
                $step = 2;
            }
        } catch (PDOException $e) {
            $error = '数据库连接失败：' . $e->getMessage();
        }
    }

    // ------ 确认配置文件已手动创建 ------
    if ($action === 'confirm_config') {
        if (file_exists(CONFIG_FILE)) {
            $step = 2;
        } else {
            $error = '未检测到配置文件，请先创建后再点击确认';
        }
    }

    // ------ 重新尝试写入 ------
    if ($action === 'retry_write') {
        session_start();
        if (isset($_SESSION['install_db'])) {
            $cfg = $_SESSION['install_db'];
            $newConfig = [
                'db_host' => $cfg['db_host'],
                'db_port' => $cfg['db_port'],
                'db_name' => $cfg['db_name'],
                'db_user' => $cfg['db_user'],
                'db_pass' => $cfg['db_pass'],
                'db_prefix' => $cfg['db_prefix'],
                'site_name' => 'AI 监控面板',
                'site_icon' => '',
            ];
            $r = try_save_config_file($newConfig);
            if ($r === true) {
                $success = '配置文件已成功写入！';
                unset($_SESSION['install_config_content']);
                $step = 2;
            } else {
                $_SESSION['install_config_content'] = $r;
                $error = '仍然无法写入，请按下方指引手动创建';
            }
        }
    }

    // ------ 步骤2: 创建管理员 ------
    if ($action === 'create_admin') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (strlen($username) < 3) {
            $error = '用户名至少3个字符';
        } elseif (strlen($password) < 6) {
            $error = '密码至少6个字符';
        } elseif ($password !== $confirmPassword) {
            $error = '两次密码输入不一致';
        } else {
            try {
                session_start();
                $db = $_SESSION['install_db'] ?? [];

                // 同样用表单凭证直接连库
                $dsn = "mysql:host={$db['db_host']};port={$db['db_port']};dbname={$db['db_name']};charset=utf8mb4";
                $pdo = new PDO($dsn, $db['db_user'], $db['db_pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                // 临时覆盖全局 db() 函数使用的常量
                define('_INSTALL_DB_HOST', $db['db_host']);
                define('_INSTALL_DB_PORT', $db['db_port']);
                define('_INSTALL_DB_NAME', $db['db_name']);
                define('_INSTALL_DB_USER', $db['db_user']);
                define('_INSTALL_DB_PASS', $db['db_pass']);
                define('_INSTALL_DB_PREFIX', $db['db_prefix']);

                require_once __DIR__ . '/includes/functions.php';

                $hash = password_hash($password, PASSWORD_BCRYPT);
                $pdo->prepare("INSERT INTO {$db['db_prefix']}users (username, password) VALUES (?, ?)")
                    ->execute([$username, $hash]);
                $pdo->prepare("INSERT INTO {$db['db_prefix']}settings (setting_key, setting_value) VALUES (?, ?)")
                    ->execute(['site_name', 'AI 监控面板']);
                $pdo->prepare("INSERT INTO {$db['db_prefix']}settings (setting_key, setting_value) VALUES (?, ?)")
                    ->execute(['check_interval', '60']);

                // 尝试创建安装锁文件（可选，不影响使用）
                if (is_dir(CONFIG_DIR) || @mkdir(CONFIG_DIR, 0755, true)) {
                    @file_put_contents(INSTALL_LOCK, date('Y-m-d H:i:s'));
                }

                // 自动登录
                session_start();
                $stmt = $pdo->prepare("SELECT id, username FROM {$db['db_prefix']}users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                $_SESSION['admin_id'] = (int)$user['id'];
                $_SESSION['admin_name'] = $user['username'];
                $_SESSION['install_db'] = null; // 清理安装 session

                $step = 3;
            } catch (Exception $e) {
                $error = '创建管理员失败：' . $e->getMessage();
            }
        }
    }
}

// 判断当前是否需要展示手动创建配置的界面
$showManualConfig = false;
$configContent = '';
if ($step === 2 && !file_exists(CONFIG_FILE)) {
    session_start();
    if (!empty($_SESSION['install_config_content'])) {
        $showManualConfig = true;
        $configContent = $_SESSION['install_config_content'];
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>安装 - AI 监控面板</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center py-12">
    <div class="w-full max-w-2xl mx-4">

        <!-- 安装步骤指示 -->
        <div class="flex items-center justify-center mb-8 space-x-4">
            <div class="flex items-center">
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold <?= $step >= 1 ? 'bg-blue-600 text-white' : 'bg-gray-300 text-gray-600' ?>">1</div>
                <span class="ml-2 text-sm <?= $step >= 1 ? 'text-blue-600 font-medium' : 'text-gray-400' ?>">数据库配置</span>
            </div>
            <div class="w-12 h-px <?= $step >= 2 ? 'bg-blue-600' : 'bg-gray-300' ?>"></div>
            <div class="flex items-center">
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold <?= $step >= 2 ? 'bg-blue-600 text-white' : 'bg-gray-300 text-gray-600' ?>">2</div>
                <span class="ml-2 text-sm <?= $step >= 2 ? 'text-blue-600 font-medium' : 'text-gray-400' ?>">管理员账户</span>
            </div>
            <div class="w-12 h-px <?= $step >= 3 ? 'bg-blue-600' : 'bg-gray-300' ?>"></div>
            <div class="flex items-center">
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold <?= $step >= 3 ? 'bg-blue-600 text-white' : 'bg-gray-300 text-gray-600' ?>">3</div>
                <span class="ml-2 text-sm <?= $step >= 3 ? 'text-blue-600 font-medium' : 'text-gray-400' ?>">完成</span>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-8">
            <?php if ($error): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm"><?= $error ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm"><?= $success ?></div>
            <?php endif; ?>

            <?php if ($step === 1): ?>
                <h1 class="text-2xl font-bold text-gray-800 mb-2">数据库配置</h1>
                <p class="text-gray-500 mb-6">请输入您的 MySQL 数据库连接信息</p>
                <form method="post" class="space-y-4">
                    <div class="grid grid-cols-3 gap-4">
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">数据库主机</label>
                            <input type="text" name="db_host" value="127.0.0.1" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">端口</label>
                            <input type="text" name="db_port" value="3306" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">数据库名</label>
                        <input type="text" name="db_name" value="ai_monitor" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">数据库用户名</label>
                        <input type="text" name="db_user" value="root" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">数据库密码</label>
                        <input type="password" name="db_pass" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">表前缀</label>
                        <input type="text" name="db_prefix" value="ai_" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <button type="submit" name="action" value="test_db" class="w-full bg-blue-600 text-white py-2.5 rounded-lg hover:bg-blue-700 font-medium transition">测试连接并下一步</button>
                </form>

            <?php elseif ($step === 2 && $showManualConfig): ?>
                <!-- 手动创建配置文件 -->
                <div class="text-center mb-6">
                    <div class="text-4xl mb-2">📄</div>
                    <h1 class="text-2xl font-bold text-gray-800 mb-2">创建配置文件</h1>
                    <p class="text-gray-500 mb-2">系统无法自动写入配置文件，请手动创建。</p>
                </div>

                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6 text-sm text-yellow-800">
                    <p class="font-medium mb-1">操作步骤：</p>
                    <ol class="list-decimal list-inside space-y-1">
                        <li>在项目根目录下创建文件夹 <code class="text-xs bg-yellow-100 px-1">data/</code></li>
                        <li>在 <code class="text-xs bg-yellow-100 px-1">data/</code> 目录下创建文件 <code class="text-xs bg-yellow-100 px-1">config.php</code></li>
                        <li>将下方代码完整复制到 <code class="text-xs bg-yellow-100 px-1">data/config.php</code> 中并保存</li>
                        <li>点击「我已创建，继续安装」</li>
                    </ol>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">复制以下代码到 <code>data/config.php</code>：</label>
                    <textarea readonly rows="16" class="w-full p-4 font-mono text-xs border border-gray-300 rounded-lg bg-gray-50 select-all"><?= h($configContent) ?></textarea>
                </div>

                <form method="post" class="space-y-3">
                    <button type="submit" name="action" value="confirm_config" class="w-full bg-blue-600 text-white py-2.5 rounded-lg hover:bg-blue-700 font-medium transition">我已创建，继续安装</button>
                    <button type="submit" name="action" value="retry_write" class="w-full py-2 border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 transition text-sm">重新尝试自动写入</button>
                </form>

            <?php elseif ($step === 2): ?>
                <h1 class="text-2xl font-bold text-gray-800 mb-2">创建管理员账户</h1>
                <p class="text-gray-500 mb-6">设置后台管理员的登录信息</p>
                <form method="post" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">用户名</label>
                        <input type="text" name="username" value="admin" required minlength="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">密码</label>
                        <input type="password" name="password" required minlength="6" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">确认密码</label>
                        <input type="password" name="confirm_password" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="flex space-x-3 pt-2">
                        <a href="install.php?step=1" class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition text-center">上一步</a>
                        <button type="submit" name="action" value="create_admin" class="flex-1 bg-blue-600 text-white py-2.5 rounded-lg hover:bg-blue-700 font-medium transition">完成安装</button>
                    </div>
                </form>

            <?php elseif ($step === 3): ?>
                <div class="text-center py-6">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800 mb-2">安装完成！</h1>
                    <p class="text-gray-500 mb-8">AI 监控面板已成功安装，现在可以开始使用了。</p>
                    <div class="flex justify-center space-x-4">
                        <a href="index.php" class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 font-medium transition">进入首页</a>
                        <a href="admin/index.php" class="bg-gray-800 text-white px-8 py-3 rounded-lg hover:bg-gray-900 font-medium transition">进入后台</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>