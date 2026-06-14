<?php

declare(strict_types=1);

if (!isset($_SESSION['user_id'])) {
    redirectTo('/login');
}

$conn = getConnection();
$available = databaseTableExists($conn, 'notifications');
$userId = (int)$_SESSION['user_id'];
$wantsJson = strtolower((string)($_GET['format'] ?? '')) === 'json'
    || str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['_csrf'] ?? null)) {
        $conn->close();
        http_response_code(419);
        exit('Invalid CSRF token');
    }
    if ($available) {
        $stmt = $conn->prepare('UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL');
        if ($stmt !== false) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();
        }
    }
    $conn->close();
    redirectTo('/notifications');
}

$notifications = [];
if ($available) {
    $stmt = $conn->prepare(
        'SELECT notification_id, type, title_th, title_en, body_th, body_en,
                action_url, read_at, created_at
         FROM notifications
         WHERE user_id = ? AND (expires_at IS NULL OR expires_at > NOW())
         ORDER BY created_at DESC LIMIT 100'
    );
    if ($stmt !== false) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}
$conn->close();

if ($wantsJson) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'ok' => true,
        'available' => $available,
        'notifications' => array_map(static function (array $notification): array {
            return [
                'id' => (int)$notification['notification_id'],
                'type' => (string)$notification['type'],
                'title' => currentLocale() === 'en' ? (string)$notification['title_en'] : (string)$notification['title_th'],
                'body' => currentLocale() === 'en' ? (string)$notification['body_en'] : (string)$notification['body_th'],
                'action_url' => (string)($notification['action_url'] ?: '/notifications'),
                'read' => !empty($notification['read_at']),
                'created_at' => (string)$notification['created_at'],
            ];
        }, $notifications),
        'csrf' => csrfToken(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

renderView('notifications', [
    'title' => 'Notifications',
    'notifications' => $notifications,
    'available' => $available,
]);
