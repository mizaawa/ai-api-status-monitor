<?php
/**
 * 数据库连接
 */
require_once __DIR__ . '/../config.php';

function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

function db(): PDO {
    return get_db();
}

/**
 * 获取表全名（带前缀）
 */
function tn(string $table): string {
    return DB_PREFIX . $table;
}

/**
 * 快速查询 - 获取一行
 */
function db_get(string $table, string $where, array $params = []): ?array {
    $row = db()->prepare("SELECT * FROM " . tn($table) . " WHERE {$where} LIMIT 1");
    $row->execute($params);
    return $row->fetch() ?: null;
}

/**
 * 快速查询 - 获取多行
 */
function db_all(string $table, string $where = '1=1', array $params = [], string $order = ''): array {
    $sql = "SELECT * FROM " . tn($table) . " WHERE {$where}";
    if ($order) $sql .= " ORDER BY {$order}";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * 快速插入
 */
function db_insert(string $table, array $data): int {
    $columns = implode(',', array_keys($data));
    $placeholders = implode(',', array_fill(0, count($data), '?'));
    $stmt = db()->prepare("INSERT INTO " . tn($table) . " ({$columns}) VALUES ({$placeholders})");
    $stmt->execute(array_values($data));
    return (int) db()->lastInsertId();
}

/**
 * 快速更新
 */
function db_update(string $table, array $data, string $where, array $whereParams = []): int {
    $sets = implode('=?,', array_keys($data)) . '=?';
    $stmt = db()->prepare("UPDATE " . tn($table) . " SET {$sets} WHERE {$where}");
    $stmt->execute(array_merge(array_values($data), $whereParams));
    return $stmt->rowCount();
}

/**
 * 快速删除
 */
function db_delete(string $table, string $where, array $params = []): int {
    $stmt = db()->prepare("DELETE FROM " . tn($table) . " WHERE {$where}");
    $stmt->execute($params);
    return $stmt->rowCount();
}