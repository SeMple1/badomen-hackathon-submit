<?php

declare(strict_types=1);

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        get();
        break;
    case 'POST':
        post();
        break;
    default:
        http_response_code(405);
        echo 'Method Not Allowed';
        break;
}

function get(): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . appUrl('/login'));
        exit;
    }

    $userId = (int)$_SESSION['user_id'];
    $action = trim((string)($_GET['action'] ?? ''));
    $eventId = (int)($_GET['event_id'] ?? 0);

    $conn = getConnection();
    $conn->query("SET time_zone = '+07:00'");
    $statusColumn = detectRegistrationStatusColumn($conn);
    ensureEventReviewsTable($conn);

    if ($action === 'ics' && $eventId > 0) {
        outputJoinedEventIcs($conn, $userId, $eventId, $statusColumn);
        $conn->close();
        return;
    }

    $activities = fetchJoinedActivities($conn, $userId, $statusColumn);
    $userEmail = fetchUserEmail($conn, $userId);
    $conn->close();

    renderView('join_activity', [
        'title' => 'Join activity',
        'activities' => $activities,
        'userEmail' => $userEmail,
        'errors' => getJoinFlashMessages('join_activity_errors'),
        'successes' => getJoinFlashMessages('join_activity_successes'),
    ]);
}

function post(): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . appUrl('/login'));
        exit;
    }

    if (function_exists('verifyCsrfToken') && !verifyCsrfToken($_POST['_csrf'] ?? null)) {
        addJoinFlashMessage('join_activity_errors', 'คำขอหมดอายุ กรุณาลองใหม่อีกครั้ง');
        header('Location: ' . appUrl('/join_activity'));
        exit;
    }

    $action = trim((string)($_POST['action'] ?? ''));
    $eventId = (int)($_POST['event_id'] ?? 0);
    $userId = (int)$_SESSION['user_id'];

    if (!in_array($action, ['toggle_email_reminder', 'request_refund', 'submit_review'], true) || $eventId <= 0) {
        addJoinFlashMessage('join_activity_errors', 'ไม่พบคำสั่งที่ต้องการทำรายการ');
        header('Location: ' . appUrl('/join_activity'));
        exit;
    }

    $conn = getConnection();
    $statusColumn = detectRegistrationStatusColumn($conn);
    ensureEventReviewsTable($conn);
    $event = findJoinedEvent($conn, $userId, $eventId, $statusColumn);

    if (!$event) {
        $conn->close();
        addJoinFlashMessage('join_activity_errors', 'ไม่พบกิจกรรมนี้ในรายการของคุณ');
        header('Location: ' . appUrl('/join_activity'));
        exit;
    }

    if ($action === 'submit_review') {
        $reviewResult = submitJoinedActivityReview($conn, $userId, $event, $_POST);
        $conn->close();
        addJoinFlashMessage(
            $reviewResult['ok'] ? 'join_activity_successes' : 'join_activity_errors',
            (string)$reviewResult['message']
        );
        header('Location: ' . appUrl('/join_activity#event-' . $eventId));
        exit;
    }

    if ($action === 'request_refund') {
        $reason = trim((string)($_POST['reason'] ?? ''));
        $refundResult = requestJoinedActivityRefund($conn, $userId, $event, $reason);
        $conn->close();
        addJoinFlashMessage(
            $refundResult['ok'] ? 'join_activity_successes' : 'join_activity_errors',
            (string)$refundResult['message']
        );
        header('Location: ' . appUrl('/join_activity#event-' . $eventId));
        exit;
    }

    $toggleResult = toggleEmailReminder($conn, $userId, $event);
    $conn->close();

    addJoinFlashMessage(
        $toggleResult['ok'] ? 'join_activity_successes' : 'join_activity_errors',
        (string)$toggleResult['message']
    );

    header('Location: ' . appUrl('/join_activity#event-' . $eventId));
    exit;
}

function detectRegistrationStatusColumn(mysqli $conn): ?string
{
    $candidates = ['status', 'registration_status', 'approve_status'];
    $sql = 'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1';

    foreach ($candidates as $column) {
        $stmt = $conn->prepare($sql);
        if ($stmt === false) return null;

        $tableName = 'registrations';
        $stmt->bind_param('ss', $tableName, $column);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result && $result->num_rows > 0;
        $stmt->close();

        if ($exists) return $column;
    }

    return null;
}


function joinActivityColumnExists(mysqli $conn, string $tableName, string $columnName): bool
{
    if (function_exists('databaseColumnExists')) {
        return databaseColumnExists($conn, $tableName, $columnName);
    }

    $stmt = $conn->prepare(
        'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
    );
    if ($stmt === false) return false;
    $stmt->bind_param('ss', $tableName, $columnName);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmt->close();
    return $exists;
}

function joinActivityEventColumnSelect(mysqli $conn, string $columnName, string $alias): string
{
    return joinActivityColumnExists($conn, 'events', $columnName)
        ? "e.`$columnName` AS `$alias`"
        : "NULL AS `$alias`";
}


function joinActivityTableExists(mysqli $conn, string $tableName): bool
{
    $stmt = $conn->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
    if ($stmt === false) return false;
    $stmt->bind_param('s', $tableName);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmt->close();
    return $exists;
}

function ensureEventReviewsTable(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS event_reviews (
            review_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            registration_id INT NOT NULL,
            event_id INT NOT NULL,
            user_id INT NOT NULL,
            rating TINYINT UNSIGNED NOT NULL,
            comment TEXT NULL,
            feedback_type VARCHAR(40) NOT NULL DEFAULT 'attendance',
            status VARCHAR(20) NOT NULL DEFAULT 'published',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            UNIQUE KEY uniq_event_review_registration (registration_id),
            INDEX idx_event_reviews_event_status (event_id, status),
            INDEX idx_event_reviews_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $columns = [
        'registration_id' => 'INT NOT NULL DEFAULT 0',
        'event_id' => 'INT NOT NULL DEFAULT 0',
        'user_id' => 'INT NOT NULL DEFAULT 0',
        'rating' => 'TINYINT UNSIGNED NOT NULL DEFAULT 0',
        'comment' => 'TEXT NULL',
        'feedback_type' => "VARCHAR(40) NOT NULL DEFAULT 'attendance'",
        'status' => "VARCHAR(20) NOT NULL DEFAULT 'published'",
        'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'DATETIME NULL',
    ];

    foreach ($columns as $column => $definition) {
        if (!joinActivityColumnExists($conn, 'event_reviews', $column)) {
            $conn->query("ALTER TABLE event_reviews ADD COLUMN `$column` $definition");
        }
    }

    if (!joinActivityColumnExists($conn, 'event_reviews', 'review_id')) {
        // Existing table without review_id can still work through registration_id/user_id+event_id WHERE clauses.
        return;
    }
}

function joinActivityReviewSelects(mysqli $conn): array
{
    if (!joinActivityTableExists($conn, 'event_reviews')) {
        return [
            '0 AS review_average',
            '0 AS review_count',
            '0 AS my_review_rating',
            "'' AS my_review_comment",
        ];
    }

    $hasRating = joinActivityColumnExists($conn, 'event_reviews', 'rating');
    $hasEvent = joinActivityColumnExists($conn, 'event_reviews', 'event_id');
    $hasStatus = joinActivityColumnExists($conn, 'event_reviews', 'status');
    $hasReg = joinActivityColumnExists($conn, 'event_reviews', 'registration_id');
    $hasComment = joinActivityColumnExists($conn, 'event_reviews', 'comment');
    $published = $hasStatus ? " AND er.status = 'published'" : '';

    $average = ($hasRating && $hasEvent)
        ? "(SELECT ROUND(AVG(er.rating), 1) FROM event_reviews er WHERE er.event_id = e.event_id$published) AS review_average"
        : '0 AS review_average';
    $count = $hasEvent
        ? "(SELECT COUNT(*) FROM event_reviews er WHERE er.event_id = e.event_id$published) AS review_count"
        : '0 AS review_count';
    $myRating = ($hasRating && $hasReg)
        ? '(SELECT er.rating FROM event_reviews er WHERE er.registration_id = r.reg_id LIMIT 1) AS my_review_rating'
        : '0 AS my_review_rating';
    $myComment = ($hasComment && $hasReg)
        ? "(SELECT COALESCE(er.comment, '') FROM event_reviews er WHERE er.registration_id = r.reg_id LIMIT 1) AS my_review_comment"
        : "'' AS my_review_comment";

    return [$average, $count, $myRating, $myComment];
}

function fetchJoinedActivities(mysqli $conn, int $userId, ?string $statusColumn): array
{
    $statusSelect = $statusColumn !== null ? "r.`$statusColumn` AS registration_status," : "'pending' AS registration_status,";
    $latitudeSelect = joinActivityEventColumnSelect($conn, 'latitude', 'latitude');
    $longitudeSelect = joinActivityEventColumnSelect($conn, 'longitude', 'longitude');
    [$reviewAverageSelect, $reviewCountSelect, $myReviewRatingSelect, $myReviewCommentSelect] = joinActivityReviewSelects($conn);

    $activeStatusColumn = $statusColumn !== null ? "r.`$statusColumn`" : 'r.status';
    $activeFilter = "AND NOT (
              COALESCE(r.payment_status, '') = 'refunded'
              OR COALESCE($activeStatusColumn, '') IN ('cancelled', 'canceled', 'refunded')
          )";

    $sql = "
        SELECT
            e.event_id,
            e.title,
            e.description,
            e.location,
            $latitudeSelect,
            $longitudeSelect,
            e.event_start,
            e.event_end,
            r.reg_id,
            r.ticket_code,
            r.total_amount,
            r.currency,
            r.payment_status,
            r.payment_method,
            r.paid_at,
            u.name AS creator_name,
            $statusSelect
            (SELECT eo.order_id FROM event_orders eo WHERE eo.reg_id = r.reg_id LIMIT 1) AS order_id,
            (SELECT eo.payment_status FROM event_orders eo WHERE eo.reg_id = r.reg_id LIMIT 1) AS order_payment_status,
            (SELECT rr.status
               FROM event_refund_requests rr
              WHERE rr.order_id = (SELECT eo2.order_id FROM event_orders eo2 WHERE eo2.reg_id = r.reg_id LIMIT 1)
              ORDER BY rr.refund_id DESC LIMIT 1) AS refund_status,
            $reviewAverageSelect,
            $reviewCountSelect,
            $myReviewRatingSelect,
            $myReviewCommentSelect,
            EXISTS(
                SELECT 1
                FROM notifications n
                INNER JOIN notification_deliveries d ON d.notification_id = n.notification_id
                WHERE n.user_id = r.user_id
                  AND n.event_id = e.event_id
                  AND n.type = 'event_email_reminder'
                  AND d.channel = 'email'
                  AND d.status = 'queued'
            ) AS email_reminder_enabled,
            (
                SELECT MIN(d.next_attempt_at)
                FROM notifications n
                INNER JOIN notification_deliveries d ON d.notification_id = n.notification_id
                WHERE n.user_id = r.user_id
                  AND n.event_id = e.event_id
                  AND n.type = 'event_email_reminder'
                  AND d.channel = 'email'
                  AND d.status = 'queued'
            ) AS remind_at,
            (
                SELECT MAX(d.sent_at)
                FROM notifications n
                INNER JOIN notification_deliveries d ON d.notification_id = n.notification_id
                WHERE n.user_id = r.user_id
                  AND n.event_id = e.event_id
                  AND n.type = 'event_email_reminder'
                  AND d.channel = 'email'
                  AND d.status = 'sent'
            ) AS sent_at,
            (
                SELECT image_path
                FROM event_images i
                WHERE i.event_id = e.event_id
                ORDER BY i.image_id ASC
                LIMIT 1
            ) AS cover_image
        FROM registrations r
        INNER JOIN events e ON e.event_id = r.event_id
        LEFT JOIN users u ON u.user_id = e.creator_id
        WHERE r.user_id = ?
        $activeFilter
        ORDER BY e.event_start ASC
    ";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) return [];

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $activities = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    return $activities;
}

function findJoinedEvent(mysqli $conn, int $userId, int $eventId, ?string $statusColumn): ?array
{
    $statusSelect = $statusColumn !== null ? "r.`$statusColumn` AS registration_status," : "'pending' AS registration_status,";
    $latitudeSelect = joinActivityEventColumnSelect($conn, 'latitude', 'latitude');
    $longitudeSelect = joinActivityEventColumnSelect($conn, 'longitude', 'longitude');
    [$reviewAverageSelect, $reviewCountSelect, $myReviewRatingSelect, $myReviewCommentSelect] = joinActivityReviewSelects($conn);
    $sql = "
        SELECT
            e.event_id,
            e.title,
            e.description,
            e.location,
            $latitudeSelect,
            $longitudeSelect,
            e.event_start,
            e.event_end,
            r.reg_id,
            r.ticket_code,
            r.total_amount,
            r.currency,
            r.payment_status,
            r.payment_method,
            r.paid_at,
            u.name AS creator_name,
            $statusSelect
            (SELECT eo.order_id FROM event_orders eo WHERE eo.reg_id = r.reg_id LIMIT 1) AS order_id,
            (SELECT eo.payment_status FROM event_orders eo WHERE eo.reg_id = r.reg_id LIMIT 1) AS order_payment_status,
            (SELECT rr.status
               FROM event_refund_requests rr
              WHERE rr.order_id = (SELECT eo2.order_id FROM event_orders eo2 WHERE eo2.reg_id = r.reg_id LIMIT 1)
              ORDER BY rr.refund_id DESC LIMIT 1) AS refund_status,
            $reviewAverageSelect,
            $reviewCountSelect,
            $myReviewRatingSelect,
            $myReviewCommentSelect,
            (
                SELECT image_path
                FROM event_images i
                WHERE i.event_id = e.event_id
                ORDER BY i.image_id ASC
                LIMIT 1
            ) AS cover_image
        FROM registrations r
        INNER JOIN events e ON e.event_id = r.event_id
        LEFT JOIN users u ON u.user_id = e.creator_id
        WHERE r.user_id = ? AND e.event_id = ?
        ORDER BY r.reg_id DESC
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) return null;

    $stmt->bind_param('ii', $userId, $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    $event = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $event ?: null;
}


function normalizeJoinedRegistrationStatus(string $status): string
{
    return str_replace([' ', '-'], '_', strtolower(trim($status)));
}

function isJoinedActivityCheckedIn(array $event): bool
{
    $status = normalizeJoinedRegistrationStatus((string)($event['registration_status'] ?? ''));
    return in_array($status, ['checked_in', 'checkedin', 'check_in', 'attended', 'used'], true);
}


function canSubmitJoinedActivityReview(array $event): bool
{
    if (isJoinedActivityCheckedIn($event)) return true;

    $eventEnd = parseJoinDateTime((string)($event['event_end'] ?? ''));
    if (!$eventEnd) return false;

    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok'));
    return $eventEnd <= $now;
}

function submitJoinedActivityReview(mysqli $conn, int $userId, array $event, array $payload): array
{
    $regId = (int)($event['reg_id'] ?? 0);
    $eventId = (int)($event['event_id'] ?? 0);
    $refundStatus = strtolower(trim((string)($event['refund_status'] ?? '')));
    $rating = (int)($payload['rating'] ?? 0);
    $comment = trim((string)($payload['comment'] ?? ''));

    if ($regId <= 0 || $eventId <= 0) {
        return ['ok' => false, 'message' => 'ไม่พบข้อมูลการเข้าร่วมกิจกรรมสำหรับรีวิว'];
    }
    if ($refundStatus === 'refunded') {
        return ['ok' => false, 'message' => 'รายการที่คืนเงินแล้วไม่สามารถรีวิวกิจกรรมได้'];
    }
    if (!canSubmitJoinedActivityReview($event)) {
        return ['ok' => false, 'message' => 'รีวิวได้หลังยืนยันสิทธิ์เข้าร่วม หรือหลังจบกิจกรรมแล้วเท่านั้น'];
    }
    if ($rating < 1 || $rating > 5) {
        return ['ok' => false, 'message' => 'กรุณาเลือกคะแนนรีวิว 1-5 ดาว'];
    }
    if (mb_strlen($comment) > 1000) {
        return ['ok' => false, 'message' => 'ข้อความรีวิวยาวเกิน 1000 ตัวอักษร'];
    }

    ensureEventReviewsTable($conn);

    if (!joinActivityColumnExists($conn, 'event_reviews', 'rating')) {
        return ['ok' => false, 'message' => 'ตาราง event_reviews ยังไม่มีคอลัมน์ rating'];
    }

    $setParts = ['rating = ?'];
    $types = 'i';
    $values = [$rating];

    if (joinActivityColumnExists($conn, 'event_reviews', 'comment')) {
        $setParts[] = 'comment = ?';
        $types .= 's';
        $values[] = $comment;
    }
    if (joinActivityColumnExists($conn, 'event_reviews', 'feedback_type')) {
        $setParts[] = "feedback_type = 'attendance'";
    }
    if (joinActivityColumnExists($conn, 'event_reviews', 'status')) {
        $setParts[] = "status = 'published'";
    }
    if (joinActivityColumnExists($conn, 'event_reviews', 'updated_at')) {
        $setParts[] = 'updated_at = NOW()';
    }

    if (joinActivityColumnExists($conn, 'event_reviews', 'registration_id')) {
        $where = 'registration_id = ?';
        $types .= 'i';
        $values[] = $regId;
    } else {
        $where = 'user_id = ? AND event_id = ?';
        $types .= 'ii';
        $values[] = $userId;
        $values[] = $eventId;
    }

    $sql = 'UPDATE event_reviews SET ' . implode(', ', $setParts) . ' WHERE ' . $where . ' LIMIT 1';
    $stmt = $conn->prepare($sql);
    if ($stmt !== false) {
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        if ($affected > 0) {
            return ['ok' => true, 'message' => 'อัปเดตรีวิวกิจกรรมเรียบร้อยแล้ว'];
        }
    }

    $columns = [];
    $placeholders = [];
    $insertTypes = '';
    $insertValues = [];

    $addValue = static function (string $column, string $placeholder, string $type, $value) use (&$columns, &$placeholders, &$insertTypes, &$insertValues): void {
        $columns[] = "`$column`";
        $placeholders[] = $placeholder;
        if ($type !== '') {
            $insertTypes .= $type;
            $insertValues[] = $value;
        }
    };

    if (joinActivityColumnExists($conn, 'event_reviews', 'registration_id')) $addValue('registration_id', '?', 'i', $regId);
    if (joinActivityColumnExists($conn, 'event_reviews', 'event_id')) $addValue('event_id', '?', 'i', $eventId);
    if (joinActivityColumnExists($conn, 'event_reviews', 'user_id')) $addValue('user_id', '?', 'i', $userId);
    $addValue('rating', '?', 'i', $rating);
    if (joinActivityColumnExists($conn, 'event_reviews', 'comment')) $addValue('comment', '?', 's', $comment);
    if (joinActivityColumnExists($conn, 'event_reviews', 'feedback_type')) $addValue('feedback_type', "'attendance'", '', null);
    if (joinActivityColumnExists($conn, 'event_reviews', 'status')) $addValue('status', "'published'", '', null);
    if (joinActivityColumnExists($conn, 'event_reviews', 'created_at')) $addValue('created_at', 'NOW()', '', null);
    if (joinActivityColumnExists($conn, 'event_reviews', 'updated_at')) $addValue('updated_at', 'NOW()', '', null);

    $sql = 'INSERT INTO event_reviews (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        return ['ok' => false, 'message' => 'บันทึกรีวิวไม่สำเร็จ'];
    }
    if ($insertTypes !== '') {
        $stmt->bind_param($insertTypes, ...$insertValues);
    }
    $ok = $stmt->execute();
    $stmt->close();

    return [
        'ok' => $ok,
        'message' => $ok ? 'บันทึกรีวิวกิจกรรมเรียบร้อยแล้ว' : 'บันทึกรีวิวไม่สำเร็จ',
    ];
}

function requestJoinedActivityRefund(mysqli $conn, int $userId, array $event, string $reason): array
{
    $regId = (int)($event['reg_id'] ?? 0);
    $orderId = (int)($event['order_id'] ?? 0);
    $amount = (float)($event['total_amount'] ?? 0);
    $paymentStatus = (string)($event['order_payment_status'] ?? $event['payment_status'] ?? '');
    $eventStart = parseJoinDateTime((string)($event['event_start'] ?? ''));
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok'));

    if (isJoinedActivityCheckedIn($event)) {
        return ['ok' => false, 'message' => 'ยืนยันสิทธิ์แล้ว ไม่สามารถยกเลิกกิจกรรมหรือขอคืนเงินได้'];
    }

    if ($regId <= 0 || $orderId <= 0 || $amount <= 0 || $paymentStatus !== 'paid') {
        return ['ok' => false, 'message' => 'รายการนี้ยังไม่มีการชำระเงินที่สามารถขอคืนได้'];
    }
    if ($eventStart && $eventStart <= $now) {
        return ['ok' => false, 'message' => 'ไม่สามารถขอคืนเงินหลังจากกิจกรรมเริ่มแล้ว'];
    }
    if (mb_strlen($reason) < 5) {
        return ['ok' => false, 'message' => 'กรุณาระบุเหตุผลขอคืนเงินอย่างน้อย 5 ตัวอักษร'];
    }

    $existing = $conn->prepare(
        "SELECT refund_id FROM event_refund_requests
         WHERE order_id = ? AND user_id = ? AND status IN ('pending','approved','processing')
         LIMIT 1"
    );
    if ($existing === false) {
        return ['ok' => false, 'message' => 'ตรวจสอบคำขอคืนเงินไม่สำเร็จ'];
    }
    $existing->bind_param('ii', $orderId, $userId);
    $existing->execute();
    $hasExisting = (bool)$existing->get_result()->fetch_assoc();
    $existing->close();
    if ($hasExisting) {
        return ['ok' => false, 'message' => 'รายการนี้มีคำขอคืนเงินที่กำลังดำเนินการอยู่แล้ว'];
    }

    $paymentId = 0;
    $paymentStmt = $conn->prepare(
        "SELECT payment_id FROM event_payments
         WHERE order_id = ? AND status = 'paid'
         ORDER BY payment_id DESC LIMIT 1"
    );
    if ($paymentStmt !== false) {
        $paymentStmt->bind_param('i', $orderId);
        $paymentStmt->execute();
        $paymentId = (int)($paymentStmt->get_result()->fetch_assoc()['payment_id'] ?? 0);
        $paymentStmt->close();
    }

    $eventId = (int)$event['event_id'];
    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare(
            'INSERT INTO event_refund_requests
             (order_id, payment_id, user_id, event_id, requested_amount, reason, status, reviewed_at)
             VALUES (?, NULLIF(?, 0), ?, ?, ?, ?, \'refunded\', NOW())'
        );
        if ($stmt === false) {
            throw new RuntimeException('refund_insert_failed');
        }
        $stmt->bind_param('iiiids', $orderId, $paymentId, $userId, $eventId, $amount, $reason);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('refund_insert_failed');
        }
        $refundId = (int)$conn->insert_id;
        $stmt->close();

        $statusColumn = detectRegistrationStatusColumn($conn);
        if ($statusColumn !== null) {
            $statusStmt = $conn->prepare(
                "UPDATE registrations
                 SET `$statusColumn` = 'cancelled'
                 WHERE reg_id = ? AND user_id = ?"
            );
            if ($statusStmt !== false) {
                $statusStmt->bind_param('ii', $regId, $userId);
                $statusStmt->execute();
                $statusStmt->close();
            }
        }

        if (joinActivityColumnExists($conn, 'registrations', 'payment_status')) {
            $payStmt = $conn->prepare(
                "UPDATE registrations
                 SET payment_status = 'refunded', paid_at = NULL, checked_in = NULL
                 WHERE reg_id = ? AND user_id = ?"
            );
            if ($payStmt !== false) {
                $payStmt->bind_param('ii', $regId, $userId);
                $payStmt->execute();
                $payStmt->close();
            }
        }

        if (joinActivityTableExists($conn, 'event_orders')) {
            $orderStmt = $conn->prepare(
                "UPDATE event_orders
                 SET payment_status = 'refunded', paid_at = NULL, updated_at = NOW()
                 WHERE order_id = ? AND reg_id = ?"
            );
            if ($orderStmt !== false) {
                $orderStmt->bind_param('ii', $orderId, $regId);
                $orderStmt->execute();
                $orderStmt->close();
            }
        }

        if (joinActivityTableExists($conn, 'event_payments') && $paymentId > 0) {
            $paidStmt = $conn->prepare(
                "UPDATE event_payments SET status = 'refunded' WHERE payment_id = ? AND order_id = ?"
            );
            if ($paidStmt !== false) {
                $paidStmt->bind_param('ii', $paymentId, $orderId);
                $paidStmt->execute();
                $paidStmt->close();
            }
        }

        if (joinActivityTableExists($conn, 'event_seats')) {
            $seatStmt = $conn->prepare(
                "UPDATE event_seats
                 SET status = 'available', reg_id = NULL, locked_by_user_id = NULL, lock_expires_at = NULL, updated_at = CURRENT_TIMESTAMP
                 WHERE reg_id = ? AND status IN ('reserved', 'paid', 'locked')"
            );
            if ($seatStmt !== false) {
                $seatStmt->bind_param('i', $regId);
                $seatStmt->execute();
                $seatStmt->close();
            }
        }

        if (joinActivityTableExists($conn, 'registration_seats')) {
            $seatLinkStmt = $conn->prepare('DELETE FROM registration_seats WHERE reg_id = ?');
            if ($seatLinkStmt !== false) {
                $seatLinkStmt->bind_param('i', $regId);
                $seatLinkStmt->execute();
                $seatLinkStmt->close();
            }
        }

        $conn->commit();

        return [
            'ok' => true,
            'message' => 'ยกเลิกและคืนเงินเรียบร้อยแล้ว คุณสามารถสมัครเข้าร่วมกิจกรรมนี้ใหม่ได้',
            'refund_id' => $refundId,
        ];
    } catch (Throwable) {
        $conn->rollback();
        return ['ok' => false, 'message' => 'ส่งคำขอคืนเงินไม่สำเร็จ'];
    }
}

function toggleEmailReminder(mysqli $conn, int $userId, array $event): array
{
    $eventStart = parseJoinDateTime((string)($event['event_start'] ?? ''));
    if (!$eventStart) {
        return ['ok' => false, 'message' => 'กิจกรรมนี้ยังไม่มีวันเริ่มกิจกรรมที่ถูกต้อง'];
    }

    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok'));
    if ($eventStart <= $now) {
        return ['ok' => false, 'message' => 'กิจกรรมนี้เริ่มไปแล้ว ไม่สามารถตั้งแจ้งเตือนล่วงหน้าได้'];
    }

    $eventId = (int)$event['event_id'];
    $activeStmt = $conn->prepare(
        "SELECT d.delivery_id
         FROM notifications n
         INNER JOIN notification_deliveries d ON d.notification_id = n.notification_id
         WHERE n.user_id = ? AND n.event_id = ? AND n.type = 'event_email_reminder'
           AND d.channel = 'email' AND d.status = 'queued'
         ORDER BY d.delivery_id DESC
         LIMIT 1"
    );
    if ($activeStmt === false) {
        return ['ok' => false, 'message' => 'ตรวจสอบสถานะแจ้งเตือนไม่สำเร็จ'];
    }
    $activeStmt->bind_param('ii', $userId, $eventId);
    $activeStmt->execute();
    $active = $activeStmt->get_result()->fetch_assoc();
    $activeStmt->close();

    if ($active) {
        $deliveryId = (int)$active['delivery_id'];
        $stmt = $conn->prepare(
            "UPDATE notification_deliveries
             SET status = 'skipped', next_attempt_at = NULL, last_error_code = 'disabled_by_user'
             WHERE delivery_id = ? AND status = 'queued'"
        );
        if ($stmt === false) {
            return ['ok' => false, 'message' => 'ปิดแจ้งเตือนไม่สำเร็จ'];
        }
        $stmt->bind_param('i', $deliveryId);
        $ok = $stmt->execute();
        $stmt->close();
        return ['ok' => $ok, 'message' => $ok ? 'ปิดการแจ้งเตือนผ่าน Gmail แล้ว' : 'ปิดแจ้งเตือนไม่สำเร็จ'];
    }

    $remindAt = calculateReminderTime($eventStart)->format('Y-m-d H:i:s');
    $email = fetchUserEmail($conn, $userId);
    if ($email === '') {
        return ['ok' => false, 'message' => 'บัญชีนี้ยังไม่มีอีเมลสำหรับรับการแจ้งเตือน'];
    }

    $title = (string)($event['title'] ?? 'กิจกรรม');
    $startText = $eventStart->format('d/m/Y H:i');
    $location = trim((string)($event['location'] ?? ''));
    $titleTh = 'แจ้งเตือนกิจกรรม: ' . $title;
    $titleEn = 'Event reminder: ' . $title;
    $bodyTh = 'กิจกรรม "' . $title . '" จะเริ่มวันที่ ' . $startText
        . ($location !== '' ? ' ที่ ' . $location : '') . ' กรุณาเตรียมตัวล่วงหน้า';
    $bodyEn = 'Your event "' . $title . '" starts on ' . $startText
        . ($location !== '' ? ' at ' . $location : '') . '.';
    $actionUrl = '/join_activity#event-' . $eventId;
    $metadata = json_encode(['remind_at' => $remindAt], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $conn->begin_transaction();
    $stmt = $conn->prepare(
        "INSERT INTO notifications
         (user_id, event_id, type, title_th, title_en, body_th, body_en, action_url, metadata_json, expires_at)
         VALUES (?, ?, 'event_email_reminder', ?, ?, ?, ?, ?, ?, ?)"
    );
    if ($stmt === false) {
        $conn->rollback();
        return ['ok' => false, 'message' => 'ตั้งแจ้งเตือนไม่สำเร็จ'];
    }

    $expiresAt = $eventStart->format('Y-m-d H:i:s');
    $stmt->bind_param(
        'iisssssss',
        $userId,
        $eventId,
        $titleTh,
        $titleEn,
        $bodyTh,
        $bodyEn,
        $actionUrl,
        $metadata,
        $expiresAt
    );
    $ok = $stmt->execute();
    $notificationId = (int)$conn->insert_id;
    $stmt->close();
    if (!$ok || $notificationId <= 0) {
        $conn->rollback();
        return ['ok' => false, 'message' => 'ตั้งแจ้งเตือนไม่สำเร็จ'];
    }

    $status = 'queued';
    $delivery = $conn->prepare(
        "INSERT INTO notification_deliveries
         (notification_id, channel, status, recipient, next_attempt_at)
         VALUES (?, 'email', ?, ?, ?)"
    );
    if ($delivery === false) {
        $conn->rollback();
        return ['ok' => false, 'message' => 'สร้างคิว Gmail ไม่สำเร็จ'];
    }
    $delivery->bind_param('isss', $notificationId, $status, $email, $remindAt);
    $ok = $delivery->execute();
    $delivery->close();
    if (!$ok) {
        $conn->rollback();
        return ['ok' => false, 'message' => 'สร้างคิว Gmail ไม่สำเร็จ'];
    }
    $conn->commit();

    $confirmationTemplate = buildReminderConfirmationEmail($title, $startText, $location, $remindAt, $actionUrl);
    $confirmation = sendGmailMessageFromTemplate($confirmationTemplate,
        $email,
        'เปิดแจ้งเตือนแล้ว: ' . $title,
        'ระบบจะส่งอีเมลเตือนกิจกรรม "' . $title . '" ในวันที่ ' . $remindAt,
        '<div style="font-family:Arial,sans-serif;line-height:1.7">'
        . '<h2>เปิดแจ้งเตือนกิจกรรมแล้ว</h2>'
        . '<p>ระบบจะส่งอีเมลเตือน <strong>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</strong></p>'
        . '<p>กำหนดส่ง: ' . htmlspecialchars($remindAt, ENT_QUOTES, 'UTF-8') . '</p>'
        . '</div>'
    );

    return [
        'ok' => true,
        'message' => $confirmation['ok']
            ? 'ตั้งแจ้งเตือนผ่าน Gmail แล้ว และส่งอีเมลยืนยันเรียบร้อย'
            : 'ตั้งคิวแจ้งเตือนผ่าน Gmail แล้ว แต่อีเมลยืนยันยังส่งไม่สำเร็จ',
    ];
}

function calculateReminderTime(DateTimeImmutable $eventStart): DateTimeImmutable
{
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok'));
    $oneDayBefore = $eventStart->modify('-1 day');
    if ($oneDayBefore > $now) return $oneDayBefore;

    $threeHoursBefore = $eventStart->modify('-3 hours');
    if ($threeHoursBefore > $now) return $threeHoursBefore;

    $oneHourBefore = $eventStart->modify('-1 hour');
    return $oneHourBefore > $now ? $oneHourBefore : $now->modify('+5 minutes');
}

function buildReminderConfirmationEmail(string $title, string $startText, string $location, string $remindAt, string $actionUrl): array
{
    $displayTitle = trim($title) !== '' ? trim($title) : 'กิจกรรม';
    $displayLocation = trim($location);
    $safeTitle = htmlspecialchars($displayTitle, ENT_QUOTES, 'UTF-8');
    $safeStart = htmlspecialchars($startText, ENT_QUOTES, 'UTF-8');
    $safeRemindAt = htmlspecialchars($remindAt, ENT_QUOTES, 'UTF-8');
    $safeLocation = htmlspecialchars($displayLocation !== '' ? $displayLocation : 'รอประกาศสถานที่', ENT_QUOTES, 'UTF-8');
    $absoluteActionUrl = function_exists('appAbsoluteUrl') ? appAbsoluteUrl($actionUrl) : appUrl($actionUrl);
    $safeUrl = htmlspecialchars($absoluteActionUrl, ENT_QUOTES, 'UTF-8');
    $hackathonNote = 'หากท่านไม่ได้เข้าร่วมกิจกรรมแต่ได้รับจดหมายนี้ ทางทีมงานขออภัยอย่างสูง อีเมลฉบับนี้ถูกส่งโดยระบบต้นแบบสำหรับ Hackathon เพื่อทดสอบ flow การยืนยันสิทธิ์และการแจ้งเตือน กรุณาเพิกเฉยต่ออีเมลนี้ หรือแจ้งผู้จัดกิจกรรมให้ตรวจสอบข้อมูล';
    $safeNote = htmlspecialchars($hackathonNote, ENT_QUOTES, 'UTF-8');

    $text = "เปิดแจ้งเตือนกิจกรรมแล้ว\n\n"
        . "กิจกรรม: {$displayTitle}\n"
        . "วันเริ่มกิจกรรม: {$startText}\n"
        . "สถานที่: " . ($displayLocation !== '' ? $displayLocation : 'รอประกาศสถานที่') . "\n"
        . "ระบบจะส่งอีเมลเตือนอีกครั้งประมาณ: {$remindAt}\n\n"
        . "ดูรายละเอียด: " . $absoluteActionUrl . "\n\n"
        . $hackathonNote;

    $html = '<div style="margin:0;padding:0;background:#fff7ed;font-family:Arial,Helvetica,sans-serif;color:#1f2937">'
        . '<div style="max-width:640px;margin:0 auto;padding:28px 16px">'
        . '<div style="overflow:hidden;border-radius:24px;background:#ffffff;border:1px solid #fed7aa;box-shadow:0 24px 60px rgba(194,65,12,.14)">'
        . '<div style="padding:28px;background:linear-gradient(135deg,#ea580c,#c2410c);color:#ffffff">'
        . '<div style="font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;opacity:.88">GMAIL REMINDER</div>'
        . '<h1 style="margin:10px 0 0;font-size:28px;line-height:1.25">เปิดแจ้งเตือนกิจกรรมแล้ว</h1>'
        . '<p style="margin:12px 0 0;font-size:15px;line-height:1.65;opacity:.92">เราจะช่วยเตือนคุณก่อนถึงเวลากิจกรรม</p>'
        . '</div>'
        . '<div style="padding:28px">'
        . '<p style="margin:0 0 18px;font-size:16px;line-height:1.7">ระบบเปิดแจ้งเตือนผ่าน Gmail สำหรับ <strong>' . $safeTitle . '</strong> เรียบร้อยแล้ว</p>'
        . '<div style="display:block;margin:20px 0;padding:18px;border-radius:18px;background:#fff7ed;border:1px solid #fed7aa">'
        . '<div style="margin-bottom:10px;font-size:13px;line-height:1.6;color:#9a3412"><strong>เริ่มกิจกรรม:</strong> ' . $safeStart . '</div>'
        . '<div style="margin-bottom:10px;font-size:13px;line-height:1.6;color:#9a3412"><strong>สถานที่:</strong> ' . $safeLocation . '</div>'
        . '<div style="font-size:13px;line-height:1.6;color:#9a3412"><strong>เวลาที่จะเตือน:</strong> ' . $safeRemindAt . '</div>'
        . '</div>'
        . '<a href="' . $safeUrl . '" style="display:inline-block;padding:13px 18px;border-radius:14px;background:#ea580c;color:#ffffff;text-decoration:none;font-weight:800">เปิดกิจกรรมของฉัน</a>'
        . '<p style="margin:24px 0 0;font-size:13px;line-height:1.7;color:#64748b">' . $safeNote . '</p>'
        . '</div>'
        . '</div>'
        . '</div>'
        . '</div>';

    return [
        'subject' => 'เปิดแจ้งเตือนแล้ว: ' . $displayTitle,
        'text' => $text,
        'html' => $html,
    ];
}

function sendGmailMessageFromTemplate(array $template, string $email, ...$unused): array
{
    return sendGmailMessage(
        $email,
        (string)($template['subject'] ?? 'เปิดแจ้งเตือนกิจกรรมแล้ว'),
        (string)($template['text'] ?? ''),
        (string)($template['html'] ?? '')
    );
}

function outputJoinedEventIcs(mysqli $conn, int $userId, int $eventId, ?string $statusColumn): void
{
    $event = findJoinedEvent($conn, $userId, $eventId, $statusColumn);
    if (!$event) {
        http_response_code(404);
        echo 'Not Found';
        return;
    }

    $start = parseJoinDateTime((string)$event['event_start']);
    $end = parseJoinDateTime((string)$event['event_end']);
    if (!$start) {
        http_response_code(422);
        echo 'Invalid event date';
        return;
    }
    if (!$end || $end <= $start) {
        $end = $start->modify('+2 hours');
    }

    $uid = 'badomen-event-' . (int)$event['event_id'] . '-' . $userId . '@badomen.local';
    $filename = 'badomen-event-' . (int)$event['event_id'] . '.ics';
    $ics = implode("\r\n", [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Badomen//Joined Event Calendar//TH',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'BEGIN:VEVENT',
        'UID:' . $uid,
        'DTSTAMP:' . gmdate('Ymd\THis\Z'),
        'DTSTART:' . $start->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z'),
        'DTEND:' . $end->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z'),
        'SUMMARY:' . escapeIcsText((string)$event['title']),
        'LOCATION:' . escapeIcsText((string)$event['location']),
        'DESCRIPTION:' . escapeIcsText(strip_tags((string)($event['description'] ?? ''))),
        'END:VEVENT',
        'END:VCALENDAR',
        '',
    ]);

    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $ics;
}

function parseJoinDateTime(string $value): ?DateTimeImmutable
{
    $value = trim($value);
    if ($value === '') return null;

    $tz = new DateTimeZone(date_default_timezone_get());
    $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, $tz);
    if ($date instanceof DateTimeImmutable) return $date;

    $fallback = date_create_immutable($value, $tz);
    return $fallback instanceof DateTimeImmutable ? $fallback : null;
}

function fetchUserEmail(mysqli $conn, int $userId): string
{
    $stmt = $conn->prepare('SELECT email FROM users WHERE user_id = ? LIMIT 1');
    if ($stmt === false) return '';

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (string)($row['email'] ?? '');
}

function escapeIcsText(string $value): string
{
    $value = str_replace(["\\", ";", ",", "\r\n", "\n", "\r"], ["\\\\", "\\;", "\\,", "\\n", "\\n", "\\n"], $value);
    return trim($value);
}

function addJoinFlashMessage(string $key, string $message): void
{
    if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) $_SESSION[$key] = [];
    $_SESSION[$key][] = $message;
}

function getJoinFlashMessages(string $key): array
{
    $messages = $_SESSION[$key] ?? [];
    unset($_SESSION[$key]);
    return is_array($messages) ? array_map('strval', $messages) : [];
}
