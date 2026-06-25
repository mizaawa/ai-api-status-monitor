<?php
/**
 * 工具函数
 */

/**
 * 获取站点设置
 */
function get_setting(string $key, string $default = ''): string {
    try {
        $row = db_get('settings', 'setting_key = ?', [$key]);
        return $row ? $row['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * 更新站点设置
 */
function set_setting(string $key, string $value): void {
    $exists = db_get('settings', 'setting_key = ?', [$key]);
    if ($exists) {
        db_update('settings', ['setting_value' => $value], 'setting_key = ?', [$key]);
    } else {
        db_insert('settings', ['setting_key' => $key, 'setting_value' => $value]);
    }
}

/**
 * 安全输出
 */
if (!function_exists('h')) {
    function h(mixed $value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * 获取站点标题
 */
function site_title(): string {
    $title = get_setting('site_name', 'AI 监控面板');
    return h($title);
}

/**
 * 获取站点图标
 */
function site_icon_tag(): string {
    $icon = get_setting('site_icon', '');
    if ($icon) {
        return '<link rel="icon" href="' . h($icon) . '">';
    }
    return '<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🤖</text></svg>">';
}

/**
 * 格式化毫秒
 */
function format_latency(?int $ms): string {
    if ($ms === null) return '--';
    if ($ms < 5000) return $ms . ' ms';
    return number_format($ms / 1000, 1) . ' s';
}

/**
 * 检测端点基础连通延迟
 */
function check_endpoint_ping(string $apiUrl): ?int {
    $baseUrl = rtrim($apiUrl, '/');
    $start = microtime(true);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $baseUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return null;
    }
    return (int)((microtime(true) - $start) * 1000);
}

/**
 * 检查 OpenAI 格式的 API（支持从分组继承配置）
 */
function check_ai_api(string $apiKey, string $apiUrl, string $model, ?int $groupId = null): array {
    // 如果 apiKey 或 apiUrl 为空，尝试从分组继承
    if (($apiKey === '' || $apiUrl === '') && $groupId > 0) {
        $group = db_get('groups', 'id = ?', [$groupId]);
        if ($group) {
            if ($apiKey === '' && !empty($group['default_api_key'])) {
                $apiKey = $group['default_api_key'];
            }
            if ($apiUrl === '' && !empty($group['default_api_url'])) {
                $apiUrl = $group['default_api_url'];
            }
        }
    }

    // 仍然缺少必要参数则返回错误
    if ($apiKey === '' || $apiUrl === '') {
        return ['latency' => 0, 'ping_latency' => null, 'status_code' => 0, 'error' => '缺少 API Key 或接口地址', 'success' => false];
    }

    $pingLatency = check_endpoint_ping($apiUrl);
    $start = microtime(true);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => rtrim($apiUrl, '/') . '/chat/completions',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => $model,
            'messages' => [['role' => 'user', 'content' => 'hi']],
            'max_tokens' => 1,
        ]),
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $latency = (int)((microtime(true) - $start) * 1000);

    if ($error) {
        return ['latency' => $latency, 'ping_latency' => $pingLatency, 'status_code' => 0, 'error' => $error, 'success' => false];
    }

    $data = json_decode($response, true);
    if ($httpCode === 200 && isset($data['choices'])) {
        return ['latency' => $latency, 'ping_latency' => $pingLatency, 'status_code' => $httpCode, 'error' => null, 'success' => true];
    }

    $errMsg = $data['error']['message'] ?? 'HTTP ' . $httpCode;
    return ['latency' => $latency, 'ping_latency' => $pingLatency, 'status_code' => $httpCode, 'error' => $errMsg, 'success' => false];
}

/**
 * 创建数据库表
 */
function create_tables(PDO $pdo, string $prefix): void {
    $sql = "
    CREATE TABLE IF NOT EXISTS `{$prefix}settings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `setting_key` VARCHAR(100) NOT NULL UNIQUE,
        `setting_value` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS `{$prefix}users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(50) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS `{$prefix}groups` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `description` TEXT,
        `icon_url` VARCHAR(500) DEFAULT '',
        `provider_name` VARCHAR(100) DEFAULT '',
        `display_order` INT DEFAULT 0,
        `is_active` TINYINT(1) DEFAULT 1,
        `check_interval` INT DEFAULT 60,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS `{$prefix}channels` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `group_id` INT NOT NULL,
        `name` VARCHAR(100) NOT NULL,
        `api_key` VARCHAR(500) NOT NULL,
        `api_url` VARCHAR(500) NOT NULL DEFAULT 'https://api.openai.com/v1',
        `model` VARCHAR(200) NOT NULL DEFAULT 'gpt-3.5-turbo',
        `is_active` TINYINT(1) DEFAULT 1,
        `last_latency` INT DEFAULT NULL,
        `last_ping_latency` INT DEFAULT NULL,
        `last_status` INT DEFAULT NULL,
        `last_error` TEXT,
        `last_check_at` TIMESTAMP NULL DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`group_id`) REFERENCES `{$prefix}groups`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS `{$prefix}monitor_logs` (
        `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
        `channel_id` INT NOT NULL,
        `latency` INT DEFAULT NULL,
        `ping_latency` INT DEFAULT NULL,
        `status_code` INT DEFAULT NULL,
        `error_message` TEXT,
        `checked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_channel_time` (`channel_id`, `checked_at`),
        INDEX `idx_checked_at` (`checked_at`),
        FOREIGN KEY (`channel_id`) REFERENCES `{$prefix}channels`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS `{$prefix}home_content` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `content_type` VARCHAR(50) DEFAULT 'html',
        `content` TEXT,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    foreach (explode(';', $sql) as $statement) {
        $statement = trim($statement);
        if ($statement) {
            $pdo->exec($statement);
        }
    }
}

/**
 * 检查数据表字段是否存在
 */
function db_column_exists(string $table, string $column): bool {
    try {
        $stmt = db()->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
        );
        $stmt->execute([tn($table), $column]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        try {
            $stmt = db()->prepare("SHOW COLUMNS FROM " . tn($table) . " LIKE ?");
            $stmt->execute([$column]);
            return (bool)$stmt->fetch();
        } catch (Exception $e) {
            return false;
        }
    }
}

/**
 * 兼容旧版本数据库：补充新增字段
 */
function ensure_monitor_schema_columns(): void {
    $columns = [
        ['groups', 'icon_url', "ALTER TABLE " . tn('groups') . " ADD COLUMN `icon_url` VARCHAR(500) DEFAULT ''"],
        ['groups', 'provider_name', "ALTER TABLE " . tn('groups') . " ADD COLUMN `provider_name` VARCHAR(100) DEFAULT ''"],
        ['groups', 'default_api_url', "ALTER TABLE " . tn('groups') . " ADD COLUMN `default_api_url` VARCHAR(500) DEFAULT NULL"],
        ['groups', 'default_api_key', "ALTER TABLE " . tn('groups') . " ADD COLUMN `default_api_key` VARCHAR(500) DEFAULT NULL"],
        ['channels', 'last_ping_latency', "ALTER TABLE " . tn('channels') . " ADD COLUMN `last_ping_latency` INT DEFAULT NULL"],
        ['monitor_logs', 'ping_latency', "ALTER TABLE " . tn('monitor_logs') . " ADD COLUMN `ping_latency` INT DEFAULT NULL"],
    ];

    foreach ($columns as [$table, $column, $sql]) {
        if (db_column_exists($table, $column)) {
            continue;
        }
        try {
            db()->exec($sql);
        } catch (Exception $e) {
            // 保持入口可打开，具体保存/查询处会给出可执行的修复提示
        }
    }
}

/**
 * 获取缺失的监控兼容字段
 */
function get_missing_monitor_schema_columns(): array {
    $columns = [
        ['groups', 'icon_url'],
        ['groups', 'provider_name'],
        ['groups', 'default_api_url'],
        ['groups', 'default_api_key'],
        ['channels', 'last_ping_latency'],
        ['monitor_logs', 'ping_latency'],
    ];
    $missing = [];
    foreach ($columns as [$table, $column]) {
        if (!db_column_exists($table, $column)) {
            $missing[] = [$table, $column];
        }
    }
    return $missing;
}

function monitor_schema_fix_sql(): string {
    $definitions = [
        'groups.icon_url' => "ALTER TABLE " . tn('groups') . " ADD COLUMN `icon_url` VARCHAR(500) DEFAULT '';",
        'groups.provider_name' => "ALTER TABLE " . tn('groups') . " ADD COLUMN `provider_name` VARCHAR(100) DEFAULT '';",
        'groups.default_api_url' => "ALTER TABLE " . tn('groups') . " ADD COLUMN `default_api_url` VARCHAR(500) DEFAULT NULL;",
        'groups.default_api_key' => "ALTER TABLE " . tn('groups') . " ADD COLUMN `default_api_key` VARCHAR(500) DEFAULT NULL;",
        'channels.last_ping_latency' => "ALTER TABLE " . tn('channels') . " ADD COLUMN `last_ping_latency` INT DEFAULT NULL;",
        'monitor_logs.ping_latency' => "ALTER TABLE " . tn('monitor_logs') . " ADD COLUMN `ping_latency` INT DEFAULT NULL;",
    ];

    $sql = [];
    foreach (get_missing_monitor_schema_columns() as [$table, $column]) {
        $key = $table . '.' . $column;
        if (isset($definitions[$key])) {
            $sql[] = $definitions[$key];
        }
    }
    return implode("\n", $sql);
}

function ensure_group_icon_column(): void {
    ensure_monitor_schema_columns();
}

/**
 * 规范化首页自定义内容中的内部链接
 */
function normalize_home_content_links(string $content): string {
    $adminUrl = site_url('admin/index.php');
    $homeUrl = site_url('index.php');

    $replacements = [
        'href="admin"' => 'href="' . h($adminUrl) . '"',
        "href='admin'" => "href='" . h($adminUrl) . "'",
        'href="admin/"' => 'href="' . h($adminUrl) . '"',
        "href='admin/'" => "href='" . h($adminUrl) . "'",
        'href="/admin"' => 'href="' . h($adminUrl) . '"',
        "href='/admin'" => "href='" . h($adminUrl) . "'",
        'href="/admin/"' => 'href="' . h($adminUrl) . '"',
        "href='/admin/'" => "href='" . h($adminUrl) . "'",
        'href="admin/index.php"' => 'href="' . h($adminUrl) . '"',
        "href='admin/index.php'" => "href='" . h($adminUrl) . "'",
        'href="/admin/index.php"' => 'href="' . h($adminUrl) . '"',
        "href='/admin/index.php'" => "href='" . h($adminUrl) . "'",
        'href="index.php"' => 'href="' . h($homeUrl) . '"',
        "href='index.php'" => "href='" . h($homeUrl) . "'",
    ];

    return strtr($content, $replacements);
}

/**
 * 获取首页自定义内容
 */
function get_home_content(): string {
    try {
        $row = db_get('home_content', 'content_type = ?', ['html']);
        return $row ? normalize_home_content_links((string)$row['content']) : '';
    } catch (Exception $e) {
        return '';
    }
}

/**
 * 获取分组列表（含渠道数量）
 */
function get_groups_with_counts(): array {
    $groups = db_all('groups', 'is_active = 1', [], 'display_order ASC, id ASC');
    foreach ($groups as &$group) {
        $counts = db()->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active FROM " . tn('channels') . " WHERE group_id = ?");
        $counts->execute([$group['id']]);
        $group['channel_counts'] = $counts->fetch();
    }
    return $groups;
}

/**
 * 获取分组下的渠道列表
 */
function get_channels_by_group(int $groupId): array {
    return db_all('channels', 'group_id = ?', [$groupId], 'id ASC');
}

/**
 * 清理旧监控日志（每个渠道只保留最近100条）
 */
function clean_old_logs(): void {
    $channels = db_all('channels', '1=1', [], 'id ASC');
    foreach ($channels as $ch) {
        db()->exec(
            "DELETE FROM " . tn('monitor_logs') . " 
             WHERE channel_id = {$ch['id']} 
             AND id NOT IN (
                 SELECT id FROM (
                     SELECT id FROM " . tn('monitor_logs') . " 
                     WHERE channel_id = {$ch['id']} 
                     ORDER BY checked_at DESC LIMIT 100
                 ) tmp
             )"
        );
    }
}