<?php

declare(strict_types=1);

function queueNotification(
    mysqli $conn,
    int $userId,
    ?int $eventId,
    string $type,
    string $titleTh,
    string $titleEn,
    string $bodyTh,
    string $bodyEn,
    ?string $actionUrl = null
): void {
    if (!databaseTableExists($conn, 'notifications')) {
        return;
    }

    $stmt = $conn->prepare(
        'INSERT INTO notifications
         (user_id, event_id, type, title_th, title_en, body_th, body_en, action_url)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if ($stmt === false) {
        return;
    }
    $stmt->bind_param('iissssss', $userId, $eventId, $type, $titleTh, $titleEn, $bodyTh, $bodyEn, $actionUrl);
    if (!$stmt->execute()) {
        $stmt->close();
        return;
    }
    $notificationId = (int)$conn->insert_id;
    $stmt->close();

    if (!databaseTableExists($conn, 'notification_deliveries')) {
        return;
    }

    $channel = 'web';
    $status = 'queued';
    $delivery = $conn->prepare(
        'INSERT INTO notification_deliveries (notification_id, channel, status) VALUES (?, ?, ?)'
    );
    if ($delivery !== false) {
        $delivery->bind_param('iss', $notificationId, $channel, $status);
        $delivery->execute();
        $delivery->close();
    }

    $emailStmt = $conn->prepare('SELECT email FROM users WHERE user_id = ? LIMIT 1');
    if ($emailStmt === false) {
        return;
    }
    $emailStmt->bind_param('i', $userId);
    $emailStmt->execute();
    $recipient = (string)($emailStmt->get_result()->fetch_assoc()['email'] ?? '');
    $emailStmt->close();
    if ($recipient === '') {
        return;
    }

    $channel = 'email';
    $emailDelivery = $conn->prepare(
        'INSERT INTO notification_deliveries (notification_id, channel, status, recipient)
         VALUES (?, ?, ?, ?)'
    );
    if ($emailDelivery !== false) {
        $emailDelivery->bind_param('isss', $notificationId, $channel, $status, $recipient);
        $emailDelivery->execute();
        $emailDelivery->close();
    }
}

function queueJoinRequestNotifications(mysqli $conn, array $event, int $participantUserId): void
{
    $eventId = (int)($event['event_id'] ?? 0);
    $creatorId = (int)($event['creator_id'] ?? 0);
    $eventTitle = trim((string)($event['title'] ?? ''));
    if ($eventId <= 0 || $creatorId <= 0 || $eventTitle === '') {
        return;
    }

    queueNotification(
        $conn, $participantUserId, $eventId, 'registration_requested',
        'ส่งคำขอเข้าร่วมแล้ว', 'Join request sent',
        'คำขอเข้าร่วมกิจกรรม "' . $eventTitle . '" ถูกส่งให้ผู้จัดแล้ว',
        'Your request to join "' . $eventTitle . '" was sent to the organizer.',
        '/join_activity'
    );
    queueNotification(
        $conn, $creatorId, $eventId, 'organizer_new_request',
        'มีคำขอเข้าร่วมใหม่', 'New join request',
        'กิจกรรม "' . $eventTitle . '" มีคำขอเข้าร่วมใหม่',
        'A new attendee requested to join "' . $eventTitle . '".',
        '/participants?event_id=' . $eventId
    );
}
