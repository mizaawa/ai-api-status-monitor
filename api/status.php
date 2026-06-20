<?php
/**
 * API: 获取所有渠道的实时状态
 * 返回各渠道最新状态和最近100条延迟记录
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config.php';
if (!is_installed()) { echo json_encode(['error' => 'not_installed']); exit; }

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

ensure_monitor_schema_columns();

$groups = db_all('groups', 'is_active = 1', [], 'display_order ASC, id ASC');
$channels = [];

foreach ($groups as $group) {
    $gChannels = get_channels_by_group((int)$group['id']);
    foreach ($gChannels as $ch) {
        // 取最近20条日志
        $stmt = db()->prepare(
            "SELECT latency, ping_latency, status_code, error_message, checked_at 
             FROM " . tn('monitor_logs') . " 
             WHERE channel_id = ? 
             ORDER BY checked_at DESC LIMIT 100"
        );
        $stmt->execute([$ch['id']]);
        $logs = $stmt->fetchAll();
        $logs = array_reverse($logs); // 时间正序

        $channels[] = [
            'id' => (int)$ch['id'],
            'group_id' => (int)$ch['group_id'],
            'name' => $ch['name'],
            'model' => $ch['model'],
            'provider_name' => $group['provider_name'] ?? '',
            'last_latency' => $ch['last_latency'] !== null ? (int)$ch['last_latency'] : null,
            'last_ping_latency' => $ch['last_ping_latency'] !== null ? (int)$ch['last_ping_latency'] : null,
            'last_status' => $ch['last_status'] !== null ? (int)$ch['last_status'] : null,
            'last_error' => $ch['last_error'],
            'last_check_at' => $ch['last_check_at'],
            'logs' => $logs,
        ];
    }
}

echo json_encode(['channels' => $channels, 'ts' => time()]);