<?php
/**
 * API: 检测指定渠道
 * 支持单渠道检测或批量检测所有渠道
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
if (!is_installed()) { echo json_encode(['error' => 'not_installed']); exit; }

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

ensure_monitor_schema_columns();

$channelId = isset($_GET['channel_id']) ? (int)$_GET['channel_id'] : 0;

if ($channelId > 0) {
    $result = check_single_channel($channelId);
    echo json_encode($result);
} else {
    // 检测所有活跃渠道
    $stmt = db()->query(
        "SELECT c.*, g.provider_name 
         FROM " . tn('channels') . " c
         JOIN " . tn('groups') . " g ON c.group_id = g.id
         WHERE c.is_active = 1 AND g.is_active = 1
         ORDER BY g.display_order ASC, g.id ASC, c.id ASC"
    );
    $channels = $stmt->fetchAll();
    $results = [];
    $channelList = [];
    foreach ($channels as $ch) {
        $r = check_single_channel((int)$ch['id']);
        $results[] = $r;

        // 取最近20条日志
        $stmt = db()->prepare(
            "SELECT latency, ping_latency, status_code, error_message, checked_at 
             FROM " . tn('monitor_logs') . " 
             WHERE channel_id = ? ORDER BY checked_at DESC LIMIT 100"
        );
        $stmt->execute([$ch['id']]);
        $logs = $stmt->fetchAll();
        $logs = array_reverse($logs);

        $channelList[] = [
            'id' => (int)$ch['id'],
            'group_id' => (int)$ch['group_id'],
            'name' => $ch['name'],
            'model' => $ch['model'],
            'provider_name' => $ch['provider_name'] ?? '',
            'last_latency' => $r['latency'] !== null ? (int)$r['latency'] : null,
            'last_ping_latency' => $r['ping_latency'] !== null ? (int)$r['ping_latency'] : null,
            'last_status' => $r['status_code'] !== null ? (int)$r['status_code'] : null,
            'last_error' => $r['error'],
            'last_check_at' => date('Y-m-d H:i:s'),
            'logs' => $logs,
        ];
    }
    echo json_encode(['channels' => $channelList, 'results' => $results, 'count' => count($results), 'ts' => time()]);
}

function check_single_channel(int $id): array {
    $channel = db_get('channels', 'id = ?', [$id]);
    if (!$channel) {
        return ['error' => '渠道不存在', 'channel_id' => $id];
    }

    $result = check_ai_api($channel['api_key'], $channel['api_url'], $channel['model']);

    // 更新渠道状态
    db_update('channels', [
        'last_latency' => $result['latency'],
        'last_ping_latency' => $result['ping_latency'],
        'last_status' => $result['status_code'],
        'last_error' => $result['error'],
        'last_check_at' => date('Y-m-d H:i:s'),
    ], 'id = ?', [$id]);

    // 写入监控日志
    db_insert('monitor_logs', [
        'channel_id' => $id,
        'latency' => $result['latency'],
        'ping_latency' => $result['ping_latency'],
        'status_code' => $result['status_code'],
        'error_message' => $result['error'],
    ]);

    // 清理旧日志
    clean_old_logs();

    return [
        'channel_id' => $id,
        'name' => $channel['name'],
        'latency' => $result['latency'],
        'ping_latency' => $result['ping_latency'],
        'status_code' => $result['status_code'],
        'success' => $result['success'],
        'error' => $result['error'],
    ];
}