<?php

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        get();
        break;
    case 'POST':
        post();
        break;
}

function get(): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . appUrl('/login'));
        exit;
    }

    $creatorId = (int)$_SESSION['user_id'];
    $eventId = (int)($_GET['event_id'] ?? 0);
    $errors = getFlashMessages('participants_errors');

    if ($eventId <= 0) {
        $errors[] = 'ไม่พบกิจกรรมที่ต้องการดูรายชื่อผู้เข้าร่วม';
        renderView('participants', [
            'title' => 'Participants',
            'participants' => [],
            'errors' => $errors,
            'successes' => getFlashMessages('participants_successes'),
            'status_column_available' => true,
            'selected_event_id' => 0,
            'selected_event_title' => '',
        ]);
        return;
    }

    $conn = getConnection();
    $statusColumn = detectRegistrationStatusColumn($conn);
    $eventTitle = fetchCreatorEventTitle($conn, $creatorId, $eventId);
    if ($eventTitle === null) {
        $conn->close();
        $errors[] = 'ไม่พบกิจกรรมนี้ หรือคุณไม่มีสิทธิ์เข้าถึง';
        renderView('participants', [
            'title' => 'Participants',
            'participants' => [],
            'errors' => $errors,
            'successes' => getFlashMessages('participants_successes'),
            'status_column_available' => $statusColumn !== null,
            'selected_event_id' => $eventId,
            'selected_event_title' => '',
        ]);
        return;
    }

    $participants = fetchParticipantsForEvent($conn, $creatorId, $eventId, $statusColumn);
    $conn->close();

    renderView('participants', [
        'title' => 'Participants',
        'participants' => $participants,
        'errors' => $errors,
        'successes' => getFlashMessages('participants_successes'),
        'status_column_available' => $statusColumn !== null,
        'selected_event_id' => $eventId,
        'selected_event_title' => $eventTitle,
    ]);
}

function post(): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . appUrl('/login'));
        exit;
    }

    $creatorId = (int)$_SESSION['user_id'];
    $eventId = (int)($_POST['event_id'] ?? 0);
    $participantUserId = (int)($_POST['participant_user_id'] ?? 0);
    $decision = trim((string)($_POST['decision'] ?? ''));

    if ($eventId <= 0 || $participantUserId <= 0) {
        addFlashMessage('participants_errors', 'ไม่พบข้อมูลคำขอที่ต้องการอัปเดต');
        header('Location: ' . appUrl(participantsUrl($eventId)));
        exit;
    }

    $nextStatus = null;
    if ($decision === 'approve') {
        $nextStatus = 'approved';
    } elseif ($decision === 'reject') {
        $nextStatus = 'rejected';
    }

    if ($nextStatus === null) {
        addFlashMessage('participants_errors', 'รูปแบบการจัดการคำขอไม่ถูกต้อง');
        header('Location: ' . appUrl(participantsUrl($eventId)));
        exit;
    }

    $conn = getConnection();
    $statusColumn = detectRegistrationStatusColumn($conn);
    if ($statusColumn === null) {
        $conn->close();
        addFlashMessage('participants_errors', 'ยังไม่พบคอลัมน์สถานะในตาราง registrations');
        header('Location: ' . appUrl(participantsUrl($eventId)));
        exit;
    }

    if (!hasRegistrationColumn($conn, 'checked_in')) {
        $conn->close();
        addFlashMessage('participants_errors', 'ไม่พบคอลัมน์ checked_in ในตาราง registrations');
        header('Location: ' . appUrl(participantsUrl($eventId)));
        exit;
    }

    $ownedStmt = $conn->prepare(
        'SELECT 1
         FROM registrations r
         INNER JOIN events e ON e.event_id = r.event_id
         WHERE r.event_id = ? AND r.user_id = ? AND e.creator_id = ?
         LIMIT 1'
    );

    if ($ownedStmt === false) {
        $conn->close();
        addFlashMessage('participants_errors', 'ไม่สามารถตรวจสอบคำขอได้ในขณะนี้');
        header('Location: ' . appUrl(participantsUrl($eventId)));
        exit;
    }

    $ownedStmt->bind_param('iii', $eventId, $participantUserId, $creatorId);
    $ownedStmt->execute();
    $ownedResult = $ownedStmt->get_result();
    $isOwnedRequest = $ownedResult && $ownedResult->num_rows > 0;
    $ownedStmt->close();

    if (!$isOwnedRequest) {
        $conn->close();
        addFlashMessage('participants_errors', 'ไม่พบคำขอเข้าร่วมสำหรับกิจกรรมของคุณ');
        header('Location: ' . appUrl(participantsUrl($eventId)));
        exit;
    }

    $currentStmt = $conn->prepare(
        "SELECT r.`$statusColumn`
     FROM registrations r
     INNER JOIN events e ON e.event_id = r.event_id
     WHERE r.event_id = ? AND r.user_id = ? AND e.creator_id = ?
     LIMIT 1"
    );

    if ($currentStmt === false) {
        $conn->close();
        addFlashMessage('participants_errors', 'ไม่สามารถตรวจสอบสถานะปัจจุบันได้');
        header('Location: ' . appUrl(participantsUrl($eventId)));
        exit;
    }

    $currentStmt->bind_param('iii', $eventId, $participantUserId, $creatorId);
    $currentStmt->execute();
    $currentResult = $currentStmt->get_result();
    $currentRow = $currentResult ? $currentResult->fetch_assoc() : null;
    $currentStmt->close();

    $currentStatus = trim((string)($currentRow[$statusColumn] ?? 'pending'));


    if ($currentStatus !== 'pending') {
        $conn->close();
        addFlashMessage('participants_errors', 'ไม่สามารถเปลี่ยนสถานะได้ เนื่องจากมีการตัดสินไปแล้ว');
        header('Location: ' . appUrl(participantsUrl($eventId)));
        exit;
    }

    $checkedIn = ($nextStatus === 'approved') ? date('Y-m-d H:i:s') : null;

    $updateStmt = $conn->prepare(
        "UPDATE registrations r
        INNER JOIN events e ON e.event_id = r.event_id
        SET r.`$statusColumn` = ?,
            r.`checked_in` = ?
        WHERE r.event_id = ? AND r.user_id = ? AND e.creator_id = ?"
    );

    if ($updateStmt === false) {
        $conn->close();
        addFlashMessage('participants_errors', 'ไม่สามารถอัปเดตสถานะได้ในขณะนี้');
        header('Location: ' . appUrl(participantsUrl($eventId)));
        exit;
    }

    $updateStmt->bind_param('ssiii', $nextStatus, $checkedIn, $eventId, $participantUserId, $creatorId);

    if (!$updateStmt->execute()) {
        $updateStmt->close();
        $conn->close();
        addFlashMessage('participants_errors', 'เกิดข้อผิดพลาดระหว่างอัปเดตสถานะ');
        header('Location: ' . appUrl(participantsUrl($eventId)));
        exit;
    }

    $updateStmt->close();

    if ($nextStatus === 'approved') {
        $stmt = $conn->prepare("
            UPDATE registrations r
            JOIN events e ON e.event_id = r.event_id
            SET r.`$statusColumn` = 'rejected',
                r.`checked_in` = NULL
            WHERE r.event_id = ?
              AND e.creator_id = ?
              AND r.`$statusColumn` = 'pending'
              AND (
                    SELECT COUNT(*)
                    FROM registrations r2
                    WHERE r2.event_id = r.event_id
                      AND (r2.`$statusColumn` = 'approved' OR r2.checked_in IS NOT NULL)
                  ) >= e.max_participant
        ");

        if ($stmt !== false) {
            $stmt->bind_param('ii', $eventId, $creatorId);
            $stmt->execute();
            $stmt->close();
        }

        addFlashMessage('participants_successes', 'อนุมัติผู้เข้าร่วมเรียบร้อยแล้ว');
    } else {
        addFlashMessage('participants_successes', 'ปฏิเสธผู้เข้าร่วมเรียบร้อยแล้ว');
    }

    $conn->close();
    header('Location: ' . appUrl(participantsUrl($eventId)));
    exit;
}

function fetchParticipantsForEvent(mysqli $conn, int $creatorId, int $eventId, ?string $statusColumn): array
{
    //จุดที่มีการแก้ไข อันนี้ทำให้ OTP ที่ไป set checked_in แล้ว จะกลายเป็น approved ในหน้ารายชื่อทันที
    $statusSelect = $statusColumn !== null
    ? "CASE 
          WHEN r.checked_in IS NOT NULL THEN 'approved'
          ELSE COALESCE(NULLIF(TRIM(r.`$statusColumn`), ''), 'pending') 
       END AS registration_status"
    : "CASE
          WHEN r.checked_in IS NOT NULL THEN 'approved'
          ELSE 'pending'
       END AS registration_status";
    //สิ้นสุด

    $sql = "
        SELECT
            e.event_id,
            e.title AS event_title,
            u.user_id AS participant_user_id,
            u.name AS participant_name,
            u.age AS participant_age,
            u.career AS participant_career,
            $statusSelect
        FROM events e
        INNER JOIN registrations r ON r.event_id = e.event_id
        INNER JOIN users u ON u.user_id = r.user_id
        WHERE e.creator_id = ? AND e.event_id = ?
        ORDER BY u.name ASC
    ";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        return [];
    }

    $stmt->bind_param('ii', $creatorId, $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    return $rows;
}

function fetchCreatorEventTitle(mysqli $conn, int $creatorId, int $eventId): ?string
{
    $stmt = $conn->prepare('SELECT title FROM events WHERE event_id = ? AND creator_id = ? LIMIT 1');
    if ($stmt === false) {
        return null;
    }

    $stmt->bind_param('ii', $eventId, $creatorId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row !== null ? (string)($row['title'] ?? '') : null;
}

function participantsUrl(int $eventId): string
{
    if ($eventId > 0) {
        return '/participants?event_id=' . $eventId;
    }

    return '/participants';
}

function detectRegistrationStatusColumn(mysqli $conn): ?string
{
    $candidates = ['status', 'registration_status', 'approve_status'];

    foreach ($candidates as $column) {
        if (hasRegistrationColumn($conn, $column)) {
            return $column;
        }
    }

    return null;
}

function hasRegistrationColumn(mysqli $conn, string $column): bool
{
    $sql = 'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1';
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        return false;
    }

    $tableName = 'registrations';
    $stmt->bind_param('ss', $tableName, $column);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmt->close();

    return $exists;
}

function addFlashMessage(string $key, string $message): void
{
    if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }

    $_SESSION[$key][] = $message;
}

function getFlashMessages(string $key): array
{
    $messages = $_SESSION[$key] ?? [];
    unset($_SESSION[$key]);

    if (!is_array($messages)) {
        return [];
    }

    return array_map('strval', $messages);
}
