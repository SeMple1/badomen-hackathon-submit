<?php
declare(strict_types=1);

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'method_not_allowed'], 405);
}
if (!isset($_SESSION['user_id'])) {
    jsonResponse(['ok' => false, 'error' => 'authentication_required'], 401);
}
if (!verifyCsrfToken($_POST['_csrf'] ?? null)) {
    jsonResponse(['ok' => false, 'error' => 'invalid_csrf'], 419);
}

$now = time();
$requests = array_values(array_filter(
    (array)($_SESSION['event_insight_requests'] ?? []),
    static fn($timestamp): bool => is_int($timestamp) && $timestamp > $now - 60
));
if (count($requests) >= 5) {
    jsonResponse(['ok' => false, 'error' => 'กรุณารอสักครู่ก่อนขอสรุปอีกครั้ง'], 429);
}
$requests[] = $now;
$_SESSION['event_insight_requests'] = $requests;

$eventId = (int)($_POST['event_id'] ?? 0);
if ($eventId <= 0) {
    jsonResponse(['ok' => false, 'error' => 'invalid_event'], 422);
}

$conn = getConnection();
$stmt = $conn->prepare(
    "SELECT e.event_id, e.title, e.description, e.location, e.event_start, e.event_end,
            e.reg_start, e.reg_end, e.max_participant, e.price, e.currency,
            (SELECT COUNT(*) FROM registrations r
             WHERE r.event_id = e.event_id AND r.status IN ('approved','checked_in')) AS registered_count
     FROM events e WHERE e.event_id = ? LIMIT 1"
);
$stmt->bind_param('i', $eventId);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$event) {
    jsonResponse(['ok' => false, 'error' => 'event_not_found'], 404);
}

$result = generateEventInsight($event);
jsonResponse($result, $result['ok'] ? 200 : 503);
