<?php
/**
 * 配置加载器
 */

define('ROOT_PATH', __DIR__);
define('CONFIG_DIR', ROOT_PATH . '/data');
define('CONFIG_FILE', CONFIG_DIR . '/config.php');
define('INSTALL_LOCK', CONFIG_DIR . '/install.lock');

// 加载配置
function load_config(): array {
    if (!file_exists(CONFIG_FILE)) {
        return [];
    }
    return (array) require CONFIG_FILE;
}

// 保存配置（生成 PHP 配置文件）
function save_config(array $config): bool {
    if (!is_dir(CONFIG_DIR)) {
        @mkdir(CONFIG_DIR, 0755, true);
    }
    if (!is_dir(CONFIG_DIR) || !is_writable(CONFIG_DIR)) {
        return false;
    }
    $content = "<?php\n/**\n * 配置文件 - 由安装向导自动生成\n */\nreturn " . var_export($config, true) . ";\n";
    return file_put_contents(CONFIG_FILE, $content) !== false;
}

// 检查是否已安装
// 优先用 install.lock，没有则尝试连接数据库验证（适配无法写文件的服务器）
function is_installed(): bool {
    static $cached = null;
    if ($cached !== null) return $cached;

    if (!file_exists(CONFIG_FILE)) {
        return $cached = false;
    }

    // install.lock 存在则直接通过
    if (file_exists(INSTALL_LOCK)) {
        return $cached = true;
    }

    // 没有 install.lock 时，尝试连接数据库验证是否已安装
    try {
        $cfg = require CONFIG_FILE;
        $pdo = new PDO(
            "mysql:host={$cfg['db_host']};port={$cfg['db_port']};dbname={$cfg['db_name']};charset=utf8mb4",
            $cfg['db_user'], $cfg['db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]
        );
        // 检查是否有管理员用户
        $stmt = $pdo->query("SELECT COUNT(*) as c FROM {$cfg['db_prefix']}users");
        $cached = (int)$stmt->fetch()['c'] > 0;
        return $cached;
    } catch (Exception $e) {
        return $cached = false;
    }
}

$config = load_config();

// 是否正在执行安装（兼容 SCRIPT_NAME 和 SCRIPT_FILENAME）
$scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');
$scriptFile = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
$isInstalling = ($scriptName === 'install.php' || $scriptFile === 'install.php');

if (!is_installed() && !$isInstalling) {
    header('Location: install.php');
    exit;
}

// 数据库配置常量
define('DB_HOST', $config['db_host'] ?? '');
define('DB_PORT', $config['db_port'] ?? '3306');
define('DB_NAME', $config['db_name'] ?? '');
define('DB_USER', $config['db_user'] ?? '');
define('DB_PASS', $config['db_pass'] ?? '');
define('DB_PREFIX', $config['db_prefix'] ?? 'ai_');

define('SITE_NAME', $config['site_name'] ?? 'AI 监控面板');
define('SITE_ICON', $config['site_icon'] ?? '');