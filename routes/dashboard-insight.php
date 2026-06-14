<?php

declare(strict_types=1);

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['ok' => false, 'error' => 'authentication_required'], 401);
}

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
    jsonResponse(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$eventId = (int)($_GET['event_id'] ?? 0);
$userId = (int)$_SESSION['user_id'];

if ($eventId <= 0) {
    jsonResponse(['ok' => false, 'error' => 'invalid_event'], 422);
}

define('BADOMEN_DASHBOARD_LIB', true);
require_once dirname(__DIR__) . '/routes/dashboard.php';

$conn = getConnection();
$conn->query("SET time_zone = '+07:00'");

if (!dashUserOwnsEvent($conn, $eventId, $userId)) {
    $conn->close();
    jsonResponse(['ok' => false, 'error' => 'forbidden'], 403);
}

$events = fetchCreatorEventStats($conn, $userId);
$eventRow = null;
foreach ($events as $row) {
    if ((int)($row['event_id'] ?? 0) === $eventId) {
        $eventRow = $row;
        break;
    }
}

if ($eventRow === null) {
    $conn->close();
    jsonResponse(['ok' => false, 'error' => 'event_not_found'], 404);
}

$demo = fetchEventDemographics($conn, [$eventId]);
$deepMap = fetchDashboardEventInsights($conn, [$eventId], $userId);
$deep = $deepMap[$eventId] ?? getEmptyEventInsight();

$max = (int)($eventRow['max_participant'] ?? 0);
$total = (int)($eventRow['total_registered'] ?? 0);
$approved = (int)($eventRow['approved_count'] ?? 0);
$checked = (int)($eventRow['checkedin_count'] ?? 0);
$pending = (int)($eventRow['pending_count'] ?? 0);
$rejected = (int)($eventRow['rejected_count'] ?? 0);
$revenueTotal = (float)($deep['revenue_total'] ?? (float)($eventRow['revenue_total'] ?? 0));
$fill = $max > 0 ? min(100, (int)round(($total / $max) * 100)) : 0;
$approvalRate = $total > 0 ? min(100, (int)round((($approved + $checked) / $total) * 100)) : 0;

$conn->close();

jsonResponse([
    'ok' => true,
    'insight' => [
        'event_id' => $eventId,
        'title' => (string)($eventRow['title'] ?? ''),
        'location' => (string)($eventRow['location'] ?? ''),
        'event_start' => (string)($eventRow['event_start'] ?? ''),
        'event_end' => (string)($eventRow['event_end'] ?? ''),
        'reg_start' => (string)($eventRow['reg_start'] ?? ''),
        'reg_end' => (string)($eventRow['reg_end'] ?? ''),
        'capacity' => $max,
        'total_registered' => $total,
        'pending' => $pending,
        'approved' => $approved,
        'rejected' => $rejected,
        'checked' => $checked,
        'approval_rate' => $approvalRate,
        'fill' => $fill,
        'revenue_total' => $revenueTotal,
        'revenue_text' => number_format($revenueTotal, 0) . ' บาท',
        'staff_count' => (int)($deep['staff_count'] ?? 0),
        'sponsor_count' => (int)($deep['sponsor_count'] ?? 0),
        'free_grants_count' => (int)($deep['free_grants_count'] ?? 0),
        'age_groups' => $demo['age'][$eventId] ?? [],
        'career_groups' => $demo['career'][$eventId] ?? [],
        'gender_groups' => $demo['gender'][$eventId] ?? [],
        'registration_trend' => $deep['registration_trend'] ?? [],
        'checkin_trend' => $deep['checkin_trend'] ?? [],
        'revenue_trend' => $deep['revenue_trend'] ?? [],
        'ticket_status' => $deep['ticket_status'] ?? [],
        'zones' => $deep['zones'] ?? [],
        'grants' => $deep['grants'] ?? [],
        'reviews' => $deep['reviews'] ?? [],
        'review_count' => (int)($deep['review_count'] ?? 0),
        'review_average' => (float)($deep['review_average'] ?? 0),
        'participants_url' => '/participants?event_id=' . $eventId,
        'otp_url' => '/verifyotp?event_id=' . $eventId,
    ],
]);
