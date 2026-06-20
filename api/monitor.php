<?php
/**
 * API: 监控端点 - 供 Cron 定时调用
 * 检测所有活跃渠道并记录结果
 * 调用方式：curl https://your-domain.com/api/monitor
 */
require_once __DIR__ . '/../config.php';
if (!is_installed()) { header('Content-Type: application/json'); echo json_encode(['error' => 'not_installed']); exit; }

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

ensure_monitor_schema_columns();

$channels = db_all('channels', 'is_active = 1');
$results = [];
$successCount = 0;
$failCount = 0;

foreach ($channels as $ch) {
    $result = check_ai_api($ch['api_key'], $ch['api_url'], $ch['model']);

    db_update('channels', [
        'last_latency' => $result['latency'],
        'last_ping_latency' => $result['ping_latency'],
        'last_status' => $result['status_code'],
        'last_error' => $result['error'],
        'last_check_at' => date('Y-m-d H:i:s'),
    ], 'id = ?', [(int)$ch['id']]);

    db_insert('monitor_logs', [
        'channel_id' => (int)$ch['id'],
        'latency' => $result['latency'],
        'ping_latency' => $result['ping_latency'],
        'status_code' => $result['status_code'],
        'error_message' => $result['error'],
    ]);

    if ($result['success']) {
        $successCount++;
    } else {
        $failCount++;
    }

    $results[] = [
        'channel_id' => (int)$ch['id'],
        'name' => $ch['name'],
        'latency' => $result['latency'],
        'ping_latency' => $result['ping_latency'],
        'success' => $result['success'],
    ];
}

// 清理旧日志
clean_old_logs();

header('Content-Type: application/json');
echo json_encode([
    'checked' => count($results),
    'success' => $successCount,
    'fail' => $failCount,
    'results' => $results,
    'ts' => date('Y-m-d H:i:s'),
]);