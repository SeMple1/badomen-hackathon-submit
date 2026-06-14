<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/config.php';
require_once $root . '/includes/database.php';

if (!in_array('--execute', $argv, true)) {
    fwrite(STDERR, "Refusing destructive rebuild without --execute.\n");
    exit(2);
}

$backupFiles = glob($root . '/database/backups/badomen_before_rebuild_*.sql') ?: [];
rsort($backupFiles, SORT_STRING);
$backupPath = $backupFiles[0] ?? '';
if ($backupPath === '' || !is_readable($backupPath)) {
    fwrite(STDERR, "A readable pre-rebuild SQL backup is required.\n");
    exit(2);
}

$productionSchema = $root . '/database/schema/ticketing.sql';
foreach ([$productionSchema] as $schemaPath) {
    if (!is_readable($schemaPath)) {
        fwrite(STDERR, "Missing schema file: {$schemaPath}\n");
        exit(2);
    }
}

function fetchTableRows(mysqli $conn, string $table): array
{
    $quoted = '`' . str_replace('`', '``', $table) . '`';
    return $conn->query("SELECT * FROM {$quoted}")->fetch_all(MYSQLI_ASSOC);
}

function currentColumns(mysqli $conn, string $table): array
{
    $stmt = $conn->prepare(
        'SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
         ORDER BY ORDINAL_POSITION'
    );
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $columns = array_map(
        static fn(array $row): string => (string)$row['COLUMN_NAME'],
        $stmt->get_result()->fetch_all(MYSQLI_ASSOC)
    );
    $stmt->close();
    return $columns;
}

function tableExists(mysqli $conn, string $table): bool
{
    $stmt = $conn->prepare(
        'SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND TABLE_TYPE = "BASE TABLE"'
    );
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    return $exists;
}

function dropAllObjects(mysqli $conn): void
{
    $conn->query('SET FOREIGN_KEY_CHECKS=0');

    $views = $conn->query(
        "SELECT TABLE_NAME FROM information_schema.VIEWS
         WHERE TABLE_SCHEMA = DATABASE()"
    )->fetch_all(MYSQLI_ASSOC);
    foreach ($views as $view) {
        $name = '`' . str_replace('`', '``', (string)$view['TABLE_NAME']) . '`';
        $conn->query("DROP VIEW IF EXISTS {$name}");
    }

    $tables = $conn->query(
        "SELECT TABLE_NAME FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'"
    )->fetch_all(MYSQLI_ASSOC);
    foreach ($tables as $table) {
        $name = '`' . str_replace('`', '``', (string)$table['TABLE_NAME']) . '`';
        $conn->query("DROP TABLE IF EXISTS {$name}");
    }
}

function runSqlFile(mysqli $conn, string $path): void
{
    $sql = (string)file_get_contents($path);
    if (!$conn->multi_query($sql)) {
        throw new RuntimeException("SQL failed in {$path}: {$conn->error}");
    }

    do {
        $result = $conn->store_result();
        if ($result instanceof mysqli_result) {
            $result->free();
        }
        if (!$conn->more_results()) {
            break;
        }
    } while ($conn->next_result());

    if ($conn->errno !== 0) {
        throw new RuntimeException("SQL failed in {$path}: {$conn->error}");
    }
}

function insertRows(mysqli $conn, string $table, array $rows): int
{
    if ($rows === []) {
        return 0;
    }

    $targetColumns = currentColumns($conn, $table);
    $sourceColumns = array_keys($rows[0]);
    $columns = array_values(array_intersect($targetColumns, $sourceColumns));
    if ($columns === []) {
        return 0;
    }

    $quotedTable = '`' . str_replace('`', '``', $table) . '`';
    $quotedColumns = array_map(
        static fn(string $column): string => '`' . str_replace('`', '``', $column) . '`',
        $columns
    );
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $stmt = $conn->prepare(
        "INSERT INTO {$quotedTable} (" . implode(', ', $quotedColumns) . ")
         VALUES ({$placeholders})"
    );

    $inserted = 0;
    foreach ($rows as $row) {
        $values = array_map(
            static fn(string $column): mixed => $row[$column] ?? null,
            $columns
        );
        $types = '';
        foreach ($values as $value) {
            $types .= is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
        }
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        $inserted++;
    }
    $stmt->close();
    return $inserted;
}

$conn = getConnection();
$restoreOrder = [
    'users',
    'events',
    'event_sessions',
    'event_images',
    'tags',
    'event_tags',
    'coupons',
    'registrations',
    'event_favorites',
    'event_orders',
    'coupon_redemptions',
    'event_payments',
    'check_in_tokens',
    'notifications',
    'notification_deliveries',
    'auth_remember_tokens',
    'password_reset_otps',
];
$snapshot = [];

foreach ($restoreOrder as $table) {
    $snapshot[$table] = tableExists($conn, $table) ? fetchTableRows($conn, $table) : [];
}

echo "Snapshot captured:\n";
foreach ($snapshot as $table => $rows) {
    echo "- {$table}: " . count($rows) . "\n";
}

try {
    dropAllObjects($conn);
    runSqlFile($conn, $productionSchema);
    $conn->query('SET FOREIGN_KEY_CHECKS=1');
    foreach ($restoreOrder as $table) {
        $inserted = insertRows($conn, $table, $snapshot[$table]);
        echo "Restored {$table}: {$inserted}\n";
    }

    $conn->query(
        "UPDATE users u
         INNER JOIN (SELECT DISTINCT creator_id FROM events) creators
            ON creators.creator_id = u.user_id
         SET u.role = IF(u.role = 'admin', 'admin', 'organizer')"
    );
    $conn->query(
        "UPDATE events
         SET published_at = COALESCE(published_at, created_at),
             status = COALESCE(status, 'published'),
             visibility = COALESCE(visibility, 'public')"
    );

    echo "Production rebuild completed.\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Rebuild failed: {$error->getMessage()}\n");
    fwrite(STDERR, "Attempting automatic restore from {$backupPath}\n");

    try {
        dropAllObjects($conn);
        runSqlFile($conn, $backupPath);
        $conn->query('SET FOREIGN_KEY_CHECKS=1');
        fwrite(STDERR, "Automatic restore completed.\n");
    } catch (Throwable $restoreError) {
        fwrite(STDERR, "Automatic restore failed: {$restoreError->getMessage()}\n");
    }

    $conn->close();
    exit(1);
}

$conn->close();
