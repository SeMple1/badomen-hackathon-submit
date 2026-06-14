<?php

declare(strict_types=1);

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'method_not_allowed'], 405);
}
if (!isset($_SESSION['user_id'])) {
    jsonResponse(['ok' => false, 'error' => 'authentication_required'], 401);
}
$vipConn = getConnection();
$dashboardAiIsVip = badomenIsVipUser($vipConn, (int)$_SESSION['user_id']);
$vipConn->close();
if (!$dashboardAiIsVip) {
    jsonResponse(['ok' => false, 'error' => 'vip_required'], 403);
}
if (!verifyCsrfToken($_POST['_csrf'] ?? null)) {
    jsonResponse(['ok' => false, 'error' => 'invalid_csrf'], 419);
}

$now = time();
$requests = array_values(array_filter(
    (array)($_SESSION['dashboard_ai_requests'] ?? []),
    static fn($timestamp): bool => is_int($timestamp) && $timestamp > $now - 60
));
if (count($requests) >= 6) {
    jsonResponse(['ok' => false, 'error' => 'กรุณารอสักครู่ก่อนถาม AI อีกครั้ง'], 429);
}
$requests[] = $now;
$_SESSION['dashboard_ai_requests'] = $requests;

$userId = (int)$_SESSION['user_id'];
$eventId = max(0, (int)($_POST['event_id'] ?? 0));
$message = trim((string)($_POST['message'] ?? ''));
$reset = (string)($_POST['reset'] ?? '') === '1';
if (mb_strlen($message) > 500) {
    jsonResponse(['ok' => false, 'error' => 'คำถามยาวเกิน 500 ตัวอักษร'], 422);
}
if ($message === '') {
    $message = $eventId > 0
        ? 'สรุปผลกิจกรรมนี้ พร้อมจุดแข็ง ความเสี่ยง และสิ่งที่ควรทำต่อ'
        : 'สรุปภาพรวมกิจกรรมทั้งหมด พร้อมแนวโน้ม จุดที่ควรระวัง และสิ่งที่ควรทำต่อ';
}

$conversationKey = $eventId > 0 ? 'event:' . $eventId : 'all';
$historyStore = (array)($_SESSION['dashboard_ai_history'] ?? []);
if ($reset) {
    unset($historyStore[$conversationKey]);
}
$history = array_slice((array)($historyStore[$conversationKey] ?? []), -6);

$conn = getConnection();
$sql = "
    SELECT e.event_id, e.title, e.description, e.location, e.event_start, e.event_end,
           e.reg_start, e.reg_end, e.max_participant, e.price, e.currency, e.status,
           COALESCE(SUM(r.status = 'pending'), 0) AS pending_count,
           COALESCE(SUM(r.status = 'approved'), 0) AS approved_count,
           COALESCE(SUM(r.status = 'rejected'), 0) AS rejected_count,
           COALESCE(SUM(r.status = 'checked_in'), 0) AS checked_in_count,
           COUNT(r.reg_id) AS registration_count
    FROM events e
    LEFT JOIN registrations r ON r.event_id = e.event_id
    WHERE e.creator_id = ?
";
if ($eventId > 0) {
    $sql .= ' AND e.event_id = ?';
}
$sql .= ' GROUP BY e.event_id ORDER BY e.event_start DESC LIMIT 30';

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    $conn->close();
    jsonResponse(['ok' => false, 'error' => 'ไม่สามารถอ่านข้อมูล dashboard ได้'], 500);
}
if ($eventId > 0) {
    $stmt->bind_param('ii', $userId, $eventId);
} else {
    $stmt->bind_param('i', $userId);
}
$stmt->execute();
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

if ($eventId > 0 && empty($events)) {
    jsonResponse(['ok' => false, 'error' => 'ไม่พบกิจกรรมหรือคุณไม่มีสิทธิ์เข้าถึง'], 404);
}
if (empty($events)) {
    jsonResponse(['ok' => false, 'error' => 'ยังไม่มีกิจกรรมจริงให้ AI วิเคราะห์'], 422);
}

$dataLines = [];
foreach ($events as $event) {
    $dataLines[] = json_encode([
        'event_id' => (int)$event['event_id'],
        'title' => (string)$event['title'],
        'description' => mb_substr(strip_tags((string)$event['description']), 0, 700),
        'location' => (string)$event['location'],
        'event_start' => (string)$event['event_start'],
        'event_end' => (string)$event['event_end'],
        'registration_close' => (string)$event['reg_end'],
        'capacity' => (int)$event['max_participant'],
        'price' => (float)$event['price'],
        'currency' => (string)$event['currency'],
        'status' => (string)$event['status'],
        'registrations' => (int)$event['registration_count'],
        'pending' => (int)$event['pending_count'],
        'approved' => (int)$event['approved_count'],
        'rejected' => (int)$event['rejected_count'],
        'checked_in' => (int)$event['checked_in_count'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

$historyLines = [];
foreach ($history as $turn) {
    $role = ($turn['role'] ?? '') === 'assistant' ? 'AI' : 'ผู้จัดงาน';
    $historyLines[] = $role . ': ' . mb_substr((string)($turn['text'] ?? ''), 0, 800);
}

$prompt = implode("\n", [
    'คุณคือผู้ช่วยวิเคราะห์ dashboard ของเว็บจองบัตร Badomen',
    'ตอบภาษาไทยแบบกระชับ ชัดเจน และอ้างอิงเฉพาะข้อมูลจริงด้านล่าง',
    'ห้ามแต่งยอดขาย จำนวนคน รีวิว หรือข้อเท็จจริงที่ไม่มีในข้อมูล',
    'เมื่อข้อมูลไม่พอให้บอกตรงๆ และเสนอวิธีตรวจสอบจากข้อมูลที่มี',
    'ใช้หัวข้อสั้นและ bullet ที่อ่านง่าย ไม่ต้องใช้ตาราง Markdown',
    'ข้อมูลกิจกรรม:',
    implode("\n", $dataLines),
    $historyLines ? "บทสนทนาก่อนหน้า:\n" . implode("\n", $historyLines) : '',
    'คำถามล่าสุดของผู้จัดงาน: ' . $message,
]);

$result = generateGeminiText($prompt, 1100);
if (!$result['ok']) {
    jsonResponse($result, 503);
}

$history[] = ['role' => 'user', 'text' => $message];
$history[] = ['role' => 'assistant', 'text' => (string)$result['text']];
$historyStore[$conversationKey] = array_slice($history, -8);
$_SESSION['dashboard_ai_history'] = $historyStore;

jsonResponse([
    'ok' => true,
    'text' => (string)$result['text'],
    'scope' => $conversationKey,
]);
