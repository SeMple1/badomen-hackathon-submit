<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/config.php';
require_once $root . '/includes/database.php';

$conn = getConnection();
$database = (string)(getenv('DB_NAME') ?: '');

$tableResult = $conn->query(
    "SELECT TABLE_NAME, TABLE_TYPE, ENGINE, TABLE_ROWS
     FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE()
     ORDER BY TABLE_TYPE, TABLE_NAME"
);

$tables = [];
while ($row = $tableResult->fetch_assoc()) {
    $name = (string)$row['TABLE_NAME'];
    $count = null;
    if ($row['TABLE_TYPE'] === 'BASE TABLE') {
        $quoted = '`' . str_replace('`', '``', $name) . '`';
        $countResult = $conn->query("SELECT COUNT(*) AS total FROM {$quoted}");
        $count = (int)($countResult->fetch_assoc()['total'] ?? 0);
    }

    $tables[] = [
        'name' => $name,
        'type' => (string)$row['TABLE_TYPE'],
        'engine' => $row['ENGINE'],
        'rows' => $count,
    ];
}

$columnsResult = $conn->query(
    "SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, EXTRA
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
     ORDER BY TABLE_NAME, ORDINAL_POSITION"
);

$columns = [];
while ($row = $columnsResult->fetch_assoc()) {
    $columns[(string)$row['TABLE_NAME']][] = [
        'name' => (string)$row['COLUMN_NAME'],
        'type' => (string)$row['COLUMN_TYPE'],
        'nullable' => $row['IS_NULLABLE'] === 'YES',
        'key' => (string)$row['COLUMN_KEY'],
        'extra' => (string)$row['EXTRA'],
    ];
}

$integrityQueries = [
    'orphan_event_creators' =>
        'SELECT COUNT(*) AS total FROM events e LEFT JOIN users u ON u.user_id = e.creator_id WHERE u.user_id IS NULL',
    'orphan_registration_users' =>
        'SELECT COUNT(*) AS total FROM registrations r LEFT JOIN users u ON u.user_id = r.user_id WHERE u.user_id IS NULL',
    'orphan_registration_events' =>
        'SELECT COUNT(*) AS total FROM registrations r LEFT JOIN events e ON e.event_id = r.event_id WHERE e.event_id IS NULL',
    'orphan_event_images' =>
        'SELECT COUNT(*) AS total FROM event_images i LEFT JOIN events e ON e.event_id = i.event_id WHERE e.event_id IS NULL',
    'duplicate_user_event_registrations' =>
        'SELECT COUNT(*) AS total FROM (
            SELECT user_id, event_id FROM registrations
            GROUP BY user_id, event_id HAVING COUNT(*) > 1
        ) duplicate_pairs',
];

$integrity = [];
foreach ($integrityQueries as $name => $sql) {
    if (!isset($columns['events'], $columns['users'], $columns['registrations'], $columns['event_images'])) {
        break;
    }
    $integrity[$name] = (int)($conn->query($sql)->fetch_assoc()['total'] ?? 0);
}

echo json_encode([
    'database' => $database,
    'server_version' => $conn->server_info,
    'tables' => $tables,
    'columns' => $columns,
    'integrity' => $integrity,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;

$conn->close();
