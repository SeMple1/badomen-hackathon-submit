<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/config.php';
require_once $root . '/includes/database.php';

$conn = getConnection();
$checks = [
    'production_users' => 'SELECT COUNT(*) FROM users',
    'production_events' => 'SELECT COUNT(*) FROM events',
    'production_registrations' => 'SELECT COUNT(*) FROM registrations',
    'event_sessions' => 'SELECT COUNT(*) FROM event_sessions',
    'coupons' => 'SELECT COUNT(*) FROM coupons',
    'event_orders' => 'SELECT COUNT(*) FROM event_orders',
    'event_payments' => 'SELECT COUNT(*) FROM event_payments',
    'notifications' => 'SELECT COUNT(*) FROM notifications',
    'foreign_keys' =>
        'SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE()',
];

$results = [];
foreach ($checks as $name => $sql) {
    $result = $conn->query($sql);
    $results[$name] = (int)($result->fetch_row()[0] ?? 0);
}

$results['migration'] = (string)(
    $conn->query('SELECT migration_id FROM schema_migrations ORDER BY applied_at DESC LIMIT 1')
        ->fetch_row()[0] ?? ''
);

$users = $conn->query('SELECT user_id FROM users ORDER BY user_id LIMIT 2')->fetch_all(MYSQLI_ASSOC);
$results['write_transaction_rolled_back'] = false;
if (count($users) >= 2) {
    $creatorId = (int)$users[0]['user_id'];
    $attendeeId = (int)$users[1]['user_id'];
    $conn->begin_transaction();

    try {
        $event = $conn->prepare(
            'INSERT INTO events
             (creator_id, title, description, location, event_start, event_end,
              reg_start, reg_end, max_participant, price, compare_at_price, currency)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $title = '__database_smoke_test__';
        $description = 'Rolled back automatically.';
        $location = 'Transaction';
        $eventStart = '2030-01-10 09:00:00';
        $eventEnd = '2030-01-10 17:00:00';
        $regStart = '2030-01-01 00:00:00';
        $regEnd = '2030-01-09 23:59:59';
        $capacity = 1;
        $price = 100.00;
        $compareAtPrice = 120.00;
        $currency = 'THB';
        $event->bind_param(
            'isssssssidds',
            $creatorId,
            $title,
            $description,
            $location,
            $eventStart,
            $eventEnd,
            $regStart,
            $regEnd,
            $capacity,
            $price,
            $compareAtPrice,
            $currency
        );
        $event->execute();
        $eventId = (int)$conn->insert_id;
        $event->close();

        $registration = $conn->prepare(
            "INSERT INTO registrations (user_id, event_id, status) VALUES (?, ?, 'approved')"
        );
        $registration->bind_param('ii', $attendeeId, $eventId);
        $registration->execute();
        $registrationId = (int)$conn->insert_id;
        $registration->close();

        $favorite = $conn->prepare(
            'INSERT INTO event_favorites (user_id, event_id) VALUES (?, ?)'
        );
        $favorite->bind_param('ii', $attendeeId, $eventId);
        $favorite->execute();
        $favorite->close();

        $notification = $conn->prepare(
            "INSERT INTO notifications
             (user_id, event_id, type, title_th, title_en, body_th, body_en)
             VALUES (?, ?, 'smoke_test', 'ทดสอบ', 'Test', 'ทดสอบ', 'Test')"
        );
        $notification->bind_param('ii', $attendeeId, $eventId);
        $notification->execute();
        $notification->close();

        $token = $conn->prepare(
            'INSERT INTO check_in_tokens
             (registration_id, event_id, user_id, code_hash, expires_at)
             VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE))'
        );
        $hash = hash('sha256', 'smoke-token');
        $token->bind_param('iiis', $registrationId, $eventId, $attendeeId, $hash);
        $token->execute();
        $token->close();

        $conn->rollback();
        $results['write_transaction_rolled_back'] = true;
    } catch (Throwable $error) {
        $conn->rollback();
        $results['write_transaction_error'] = $error->getMessage();
    }
}

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
$conn->close();
