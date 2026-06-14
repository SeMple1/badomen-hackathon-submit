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

$eventId = (int)($_POST['event_id'] ?? 0);
if ($eventId <= 0) {
    jsonResponse(['ok' => false, 'error' => 'invalid_event'], 422);
}

$conn = getConnection();

$eventStmt = $conn->prepare('SELECT event_id FROM events WHERE event_id = ? LIMIT 1');
$eventStmt->bind_param('i', $eventId);
$eventStmt->execute();
$eventExists = $eventStmt->get_result()->fetch_assoc();
$eventStmt->close();

if (!$eventExists) {
    $conn->close();
    jsonResponse(['ok' => false, 'error' => 'event_not_found'], 404);
}

$userId = (int)$_SESSION['user_id'];
$check = $conn->prepare('SELECT favorite_id FROM event_favorites WHERE user_id = ? AND event_id = ? LIMIT 1');
$check->bind_param('ii', $userId, $eventId);
$check->execute();
$favorite = $check->get_result()->fetch_assoc();
$check->close();

if ($favorite) {
    $delete = $conn->prepare('DELETE FROM event_favorites WHERE user_id = ? AND event_id = ?');
    $delete->bind_param('ii', $userId, $eventId);
    $delete->execute();
    $delete->close();
    $saved = false;
} else {
    $insert = $conn->prepare('INSERT INTO event_favorites (user_id, event_id) VALUES (?, ?)');
    $insert->bind_param('ii', $userId, $eventId);
    $insert->execute();
    $insert->close();
    $saved = true;
}

$conn->close();
jsonResponse(['ok' => true, 'event_id' => $eventId, 'saved' => $saved]);
