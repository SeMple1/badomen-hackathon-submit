<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/config.php';
require_once $root . '/includes/database.php';

$tables = [
    'schema_migrations',
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

$conn = getConnection();
echo "SET NAMES utf8mb4;\n";
echo "SET time_zone = '+07:00';\n\n";

foreach ($tables as $table) {
    $quoted = '`' . str_replace('`', '``', $table) . '`';
    $row = $conn->query("SHOW CREATE TABLE {$quoted}")->fetch_assoc();
    $sql = (string)($row['Create Table'] ?? '');
    if ($sql === '') {
        throw new RuntimeException("Missing table: {$table}");
    }
    $sql = preg_replace('/ AUTO_INCREMENT=\d+/', '', $sql) ?? $sql;
    echo $sql, ";\n\n";
}

echo "INSERT INTO schema_migrations (migration_id, checksum_sha256)\n";
echo "VALUES ('20260613_ticketing_core', NULL);\n";
$conn->close();
