<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/config.php';
require_once $root . '/includes/database.php';

$backupDir = $root . '/database/backups';
if (!is_dir($backupDir) && !mkdir($backupDir, 0770, true) && !is_dir($backupDir)) {
    throw new RuntimeException('Unable to create database backup directory.');
}

$timestamp = date('Ymd_His');
$target = $argv[1] ?? ($backupDir . '/badomen_before_rebuild_' . $timestamp . '.sql');
$conn = getConnection();

function sqlValue(mysqli $conn, mixed $value): string
{
    if ($value === null) {
        return 'NULL';
    }

    return "'" . $conn->real_escape_string((string)$value) . "'";
}

$handle = fopen($target, 'wb');
if ($handle === false) {
    throw new RuntimeException('Unable to open backup file.');
}

fwrite($handle, "-- Badomen database backup\n");
fwrite($handle, "-- Created: " . date(DATE_ATOM) . "\n\n");
fwrite($handle, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

$objects = $conn->query(
    "SELECT TABLE_NAME, TABLE_TYPE
     FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE()
     ORDER BY CASE WHEN TABLE_TYPE = 'VIEW' THEN 1 ELSE 0 END, TABLE_NAME"
)->fetch_all(MYSQLI_ASSOC);

foreach ($objects as $object) {
    $name = (string)$object['TABLE_NAME'];
    $quotedName = '`' . str_replace('`', '``', $name) . '`';

    if ($object['TABLE_TYPE'] === 'VIEW') {
        $createRow = $conn->query("SHOW CREATE VIEW {$quotedName}")->fetch_assoc();
        $createSql = (string)($createRow['Create View'] ?? '');
        fwrite($handle, "DROP VIEW IF EXISTS {$quotedName};\n{$createSql};\n\n");
        continue;
    }

    $createRow = $conn->query("SHOW CREATE TABLE {$quotedName}")->fetch_assoc();
    $createSql = (string)($createRow['Create Table'] ?? '');
    fwrite($handle, "DROP TABLE IF EXISTS {$quotedName};\n{$createSql};\n\n");

    $result = $conn->query("SELECT * FROM {$quotedName}");
    $fields = array_map(
        static fn(array $field): string => '`' . str_replace('`', '``', (string)$field['Field']) . '`',
        $conn->query("SHOW COLUMNS FROM {$quotedName}")->fetch_all(MYSQLI_ASSOC)
    );

    $batch = [];
    while ($row = $result->fetch_assoc()) {
        $batch[] = '(' . implode(', ', array_map(
            static fn(mixed $value): string => sqlValue($conn, $value),
            array_values($row)
        )) . ')';

        if (count($batch) >= 250) {
            fwrite(
                $handle,
                "INSERT INTO {$quotedName} (" . implode(', ', $fields) . ") VALUES\n"
                . implode(",\n", $batch) . ";\n"
            );
            $batch = [];
        }
    }

    if ($batch !== []) {
        fwrite(
            $handle,
            "INSERT INTO {$quotedName} (" . implode(', ', $fields) . ") VALUES\n"
            . implode(",\n", $batch) . ";\n"
        );
    }

    fwrite($handle, "\n");
}

fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
fclose($handle);
$conn->close();

echo $target, PHP_EOL;
