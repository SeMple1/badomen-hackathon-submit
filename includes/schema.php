<?php

declare(strict_types=1);

function databaseTableExists(mysqli $conn, string $table): bool
{
    static $cache = [];
    $key = spl_object_id($conn) . ':' . $table;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $conn->prepare(
        'SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
    );
    if ($stmt === false) {
        return $cache[$key] = false;
    }

    $stmt->bind_param('s', $table);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();

    return $cache[$key] = $exists;
}

function databaseColumnExists(mysqli $conn, string $table, string $column): bool
{
    static $cache = [];
    $key = spl_object_id($conn) . ':' . $table . ':' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $conn->prepare(
        'SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
    );
    if ($stmt === false) {
        return $cache[$key] = false;
    }

    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();

    return $cache[$key] = $exists;
}
