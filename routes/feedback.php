<?php

declare(strict_types=1);

if (!isset($_SESSION['user_id'])) {
    feedbackJson(['ok' => false, 'message' => 'กรุณาเข้าสู่ระบบ'], 401);
}

$userId = (int)$_SESSION['user_id'];
$conn = getConnection();
$conn->query("SET time_zone = '+07:00'");

if (!databaseTableExists($conn, 'event_feedbacks') || !databaseTableExists($conn, 'event_reviews')) {
    $conn->close();
    feedbackJson(['ok' => false, 'message' => 'ระบบ feedback ยังไม่พร้อม'], 503);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $items = findPendingFeedbackItems($conn, $userId);
    $conn->close();
    feedbackJson(['ok' => true, 'items' => $items]);
}

if (!verifyCsrfToken($_POST['_csrf'] ?? null)) {
    $conn->close();
    feedbackJson(['ok' => false, 'message' => 'คำขอหมดอายุ กรุณาลองใหม่'], 419);
}

$type = strtolower(trim((string)($_POST['feedback_type'] ?? '')));
$rating = (int)($_POST['rating'] ?? 0);
$comment = trim((string)($_POST['comment'] ?? ''));
$registrationId = (int)($_POST['registration_id'] ?? 0);
$refundId = (int)($_POST['refund_id'] ?? 0);

if (!in_array($type, ['payment', 'refund', 'attendance', 'app'], true) || $rating < 1 || $rating > 5) {
    $conn->close();
    feedbackJson(['ok' => false, 'message' => 'กรุณาเลือกคะแนน 1-5 ดาว'], 422);
}
if (mb_strlen($comment) > 1200) {
    $conn->close();
    feedbackJson(['ok' => false, 'message' => 'ความคิดเห็นยาวเกิน 1,200 ตัวอักษร'], 422);
}

$context = resolveFeedbackContext($conn, $userId, $type, $registrationId, $refundId);
if (!$context) {
    $conn->close();
    feedbackJson(['ok' => false, 'message' => 'ไม่พบรายการที่มีสิทธิ์ให้ feedback'], 403);
}

$metadata = json_encode([
    'source' => 'common_reviews_notifications_locations',
    'user_agent' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$stmt = $conn->prepare(
    'INSERT INTO event_feedbacks
     (user_id, event_id, registration_id, order_id, refund_id, feedback_type, rating, comment, metadata_json)
     VALUES (?, NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, 0), ?, ?, NULLIF(?, ""), ?)
     ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment),
       metadata_json = VALUES(metadata_json), updated_at = CURRENT_TIMESTAMP'
);
if ($stmt === false) {
    $conn->close();
    feedbackJson(['ok' => false, 'message' => 'บันทึก feedback ไม่สำเร็จ'], 500);
}
$eventId = (int)$context['event_id'];
$orderId = (int)$context['order_id'];
$contextRegistrationId = (int)$context['registration_id'];
$contextRefundId = (int)$context['refund_id'];
$stmt->bind_param(
    'iiiiisiss',
    $userId,
    $eventId,
    $contextRegistrationId,
    $orderId,
    $contextRefundId,
    $type,
    $rating,
    $comment,
    $metadata
);
$ok = $stmt->execute();
$stmt->close();

if ($ok && $type === 'attendance') {
    $review = $conn->prepare(
        'INSERT INTO event_reviews (user_id, event_id, registration_id, rating, comment)
         VALUES (?, ?, ?, ?, NULLIF(?, ""))
         ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment),
           status = "published", updated_at = CURRENT_TIMESTAMP'
    );
    if ($review !== false) {
        $review->bind_param('iiiis', $userId, $eventId, $contextRegistrationId, $rating, $comment);
        $ok = $review->execute() && $ok;
        $review->close();
    }
}

$conn->close();
feedbackJson([
    'ok' => $ok,
    'message' => $type === 'attendance' ? 'ขอบคุณสำหรับรีวิวกิจกรรม' : 'ขอบคุณสำหรับ feedback',
], $ok ? 200 : 500);

function findPendingFeedbackItems(mysqli $conn, int $userId): array
{
    $items = [];
    $sql = "
        SELECT r.reg_id, r.event_id, e.title, e.event_end, r.payment_status,
               eo.order_id, eo.payment_status AS order_payment_status,
               rr.refund_id, rr.status AS refund_status,
               r.status AS registration_status,
               EXISTS(
                   SELECT 1 FROM event_feedbacks f
                   WHERE f.user_id = r.user_id AND f.registration_id = r.reg_id
                     AND f.feedback_type = 'payment'
               ) AS payment_done,
               EXISTS(
                   SELECT 1 FROM event_feedbacks f
                   WHERE f.user_id = r.user_id AND f.registration_id = r.reg_id
                     AND f.feedback_type = 'attendance'
               ) AS attendance_done,
               EXISTS(
                   SELECT 1 FROM event_feedbacks f
                   WHERE f.user_id = r.user_id AND f.refund_id = rr.refund_id
                     AND f.feedback_type = 'refund'
               ) AS refund_done
        FROM registrations r
        INNER JOIN events e ON e.event_id = r.event_id
        LEFT JOIN event_orders eo ON eo.reg_id = r.reg_id
        LEFT JOIN event_refund_requests rr ON rr.refund_id = (
            SELECT rr2.refund_id FROM event_refund_requests rr2
            WHERE rr2.order_id = eo.order_id ORDER BY rr2.refund_id DESC LIMIT 1
        )
        WHERE r.user_id = ?
        ORDER BY COALESCE(r.paid_at, r.registered_at) DESC
        LIMIT 30
    ";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) return [];
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as $row) {
        $base = [
            'registration_id' => (int)$row['reg_id'],
            'event_id' => (int)$row['event_id'],
            'refund_id' => (int)($row['refund_id'] ?? 0),
            'event_title' => (string)$row['title'],
        ];
        if (
            (string)($row['order_payment_status'] ?? $row['payment_status']) === 'paid'
            && (int)$row['payment_done'] === 0
        ) {
            $items[] = $base + ['feedback_type' => 'payment'];
        }
        if ((string)($row['refund_status'] ?? '') === 'refunded' && (int)$row['refund_done'] === 0) {
            $items[] = $base + ['feedback_type' => 'refund'];
        }
        $attended = in_array((string)$row['registration_status'], ['checked_in'], true)
            || strtotime((string)$row['event_end']) < time();
        if ($attended && (int)$row['attendance_done'] === 0 && (string)($row['refund_status'] ?? '') !== 'refunded') {
            $items[] = $base + ['feedback_type' => 'attendance'];
        }
    }
    return array_slice($items, 0, 5);
}

function resolveFeedbackContext(
    mysqli $conn,
    int $userId,
    string $type,
    int $registrationId,
    int $refundId
): ?array {
    if ($type === 'app') {
        return ['event_id' => 0, 'registration_id' => 0, 'order_id' => 0, 'refund_id' => 0];
    }

    $stmt = $conn->prepare(
        'SELECT r.reg_id AS registration_id, r.event_id, r.status AS registration_status,
                r.payment_status, e.event_end, eo.order_id, eo.payment_status AS order_payment_status,
                rr.refund_id, rr.status AS refund_status
         FROM registrations r
         INNER JOIN events e ON e.event_id = r.event_id
         LEFT JOIN event_orders eo ON eo.reg_id = r.reg_id
         LEFT JOIN event_refund_requests rr ON rr.refund_id = (
             SELECT rr2.refund_id FROM event_refund_requests rr2
             WHERE rr2.order_id = eo.order_id ORDER BY rr2.refund_id DESC LIMIT 1
         )
         WHERE r.user_id = ? AND r.reg_id = ?
         LIMIT 1'
    );
    if ($stmt === false) return null;
    $stmt->bind_param('ii', $userId, $registrationId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return null;

    $allowed = false;
    if ($type === 'payment') {
        $allowed = (string)($row['order_payment_status'] ?? $row['payment_status']) === 'paid';
    } elseif ($type === 'refund') {
        $allowed = (int)($row['refund_id'] ?? 0) === $refundId && (string)($row['refund_status'] ?? '') === 'refunded';
    } elseif ($type === 'attendance') {
        $allowed = (string)$row['registration_status'] === 'checked_in'
            || strtotime((string)$row['event_end']) < time();
    }
    return $allowed ? $row : null;
}

function feedbackJson(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
