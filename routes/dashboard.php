<?php
declare(strict_types=1);

if (!defined('BADOMEN_DASHBOARD_LIB')) {
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
    return;
}

function dashAddFlashMessage(string $key, string $message): void
{
    if (function_exists('addFlashMessage')) {
        addFlashMessage($key, $message);
        return;
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }

    if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }
    $_SESSION[$key][] = $message;
}

function dashGetFlashMessages(string $key): array
{
    if (function_exists('getFlashMessages')) {
        $messages = getFlashMessages($key);
        return is_array($messages) ? $messages : [];
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }

    $messages = $_SESSION[$key] ?? [];
    unset($_SESSION[$key]);

    if (!is_array($messages)) {
        return [];
    }

    return array_values(array_filter($messages, static fn($message): bool => is_scalar($message) && trim((string)$message) !== ''));
}

function get(): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . appUrl('/login'));
        exit;
    }

    $conn = getConnection();
    $conn->query("SET time_zone = '+07:00'");

    $userId = (int)$_SESSION['user_id'];
    $vipProfile = badomenFetchVipProfile($conn, $userId);
    $events = fetchCreatorEventStats($conn, $userId);

    foreach ($events as &$e) {
        $e['deep'] = getEmptyEventInsight();
    }
    unset($e);

    $summary = [
        'total_events' => count($events),
        'total_registered' => 0,
        'total_pending' => 0,
        'total_approved' => 0,
        'total_rejected' => 0,
        'total_checked_in' => 0,
        'total_capacity' => 0,
        'total_upcoming' => 0,
        'total_finished' => 0,
        'total_revenue' => 0.0,
        'total_staff' => 0,
        'total_sponsor' => 0,
    ];

    $now = time();
    foreach ($events as $e) {
        $summary['total_registered'] += (int)($e['total_registered'] ?? 0);
        $summary['total_pending'] += (int)($e['pending_count'] ?? 0);
        $summary['total_approved'] += (int)($e['approved_count'] ?? 0);
        $summary['total_rejected'] += (int)($e['rejected_count'] ?? 0);
        $summary['total_checked_in'] += (int)($e['checkedin_count'] ?? 0);
        $summary['total_capacity'] += (int)($e['max_participant'] ?? 0);
        $summary['total_revenue'] += (float)($e['revenue_total'] ?? 0);
        $summary['total_staff'] += (int)($e['staff_count'] ?? 0);
        $summary['total_sponsor'] += (int)($e['sponsor_count'] ?? 0);

        $endTs = strtotime((string)($e['event_end'] ?? ''));
        if ($endTs !== false && $endTs < $now) {
            $summary['total_finished']++;
        } else {
            $summary['total_upcoming']++;
        }
    }

    $conn->close();

    renderView('dashboard', [
        'title' => 'Dashboard',
        'events' => $events,
        'summary' => $summary,
        'errors' => dashGetFlashMessages('dashboard_errors'),
        'successes' => dashGetFlashMessages('dashboard_successes'),
        'access_grants_ready' => dashAccessGrantsAvailable() || true,
        'viewerIsVip' => (bool)$vipProfile['is_vip'],
    ]);
}

function post(): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . appUrl('/login'));
        exit;
    }

    $conn = getConnection();
    $conn->query("SET time_zone = '+07:00'");

    $userId = (int)$_SESSION['user_id'];
    $eventId = (int)($_POST['event_id'] ?? 0);
    $formAction = trim((string)($_POST['form_action'] ?? ''));
    $redirectUrl = '/dashboard' . ($eventId > 0 ? '#event-' . $eventId : '');

    if ($eventId <= 0 || !dashUserOwnsEvent($conn, $eventId, $userId)) {
        $conn->close();
        dashAddFlashMessage('dashboard_errors', 'ไม่พบกิจกรรม หรือคุณไม่มีสิทธิ์จัดการกิจกรรมนี้');
        header('Location: ' . appUrl('/dashboard'));
        exit;
    }

    if (!dashTableExists($conn, 'event_access_grants')) {
        $conn->close();
        dashAddFlashMessage('dashboard_errors', 'ยังไม่มีตาราง event_access_grants กรุณารัน migration 20260613_event_access_grants.sql ก่อน');
        header('Location: ' . appUrl($redirectUrl));
        exit;
    }

    if ($formAction === 'grant_access') {
        $email = trim((string)($_POST['access_email'] ?? ''));
        $role = strtolower(trim((string)($_POST['access_role'] ?? 'staff')));
        $note = trim((string)($_POST['access_note'] ?? ''));
        $freeLimit = max(0, min(10, (int)($_POST['free_ticket_limit'] ?? 1)));
        $canVerify = isset($_POST['can_verify_otp']) ? 1 : 0;

        if (!in_array($role, ['staff', 'sponsor'], true)) {
            $role = 'staff';
        }
        if ($role === 'staff') {
            $canVerify = 1;
        }
        if ($role === 'sponsor' && $freeLimit < 1) {
            $freeLimit = 1;
        }

        $targetUser = dashFindUserByEmail($conn, $email);
        if (!$targetUser) {
            $conn->close();
            dashAddFlashMessage('dashboard_errors', 'ไม่พบผู้ใช้จากอีเมลนี้');
            header('Location: ' . appUrl($redirectUrl));
            exit;
        }

        $targetUserId = (int)$targetUser['user_id'];
        if ($targetUserId === $userId) {
            $conn->close();
            dashAddFlashMessage('dashboard_errors', 'ไม่จำเป็นต้องมอบสิทธิ์ให้เจ้าของกิจกรรม');
            header('Location: ' . appUrl($redirectUrl));
            exit;
        }

        $grantError = dashUpsertAccessGrant($conn, $eventId, $targetUserId, $role, $canVerify, $freeLimit, $note, $userId);
        if ($grantError !== null) {
            $conn->close();
            dashAddFlashMessage('dashboard_errors', $grantError);
            header('Location: ' . appUrl($redirectUrl));
            exit;
        }
        $conn->close();
        dashAddFlashMessage('dashboard_successes', 'มอบสิทธิ์เรียบร้อยแล้ว');
        header('Location: ' . appUrl($redirectUrl));
        exit;
    }

    if ($formAction === 'revoke_access') {
        $grantId = (int)($_POST['grant_id'] ?? 0);
        if ($grantId <= 0) {
            $conn->close();
            dashAddFlashMessage('dashboard_errors', 'ไม่พบสิทธิ์ที่ต้องการยกเลิก');
            header('Location: ' . appUrl($redirectUrl));
            exit;
        }

        $revokeError = dashRevokeAccessGrant($conn, $grantId, $eventId);
        if ($revokeError !== null) {
            $conn->close();
            dashAddFlashMessage('dashboard_errors', $revokeError);
            header('Location: ' . appUrl($redirectUrl));
            exit;
        }
        $conn->close();
        dashAddFlashMessage('dashboard_successes', 'ยกเลิกสิทธิ์เรียบร้อยแล้ว');
        header('Location: ' . appUrl($redirectUrl));
        exit;
    }

    $conn->close();
    dashAddFlashMessage('dashboard_errors', 'คำสั่งไม่ถูกต้อง');
    header('Location: ' . appUrl($redirectUrl));
    exit;
}

function dashAccessGrantsAvailable(): bool
{
    return true;
}

function fetchCreatorEventStats(mysqli $conn, int $creatorId): array
{
    $hasPrice = dashColumnExists($conn, 'events', 'price');
    $hasCurrency = dashColumnExists($conn, 'events', 'currency');

    $priceSelect = $hasPrice ? 'e.price' : '0 AS price';
    $currencySelect = $hasCurrency ? "e.currency" : "'THB' AS currency";

    $hasOrders = dashTableExists($conn, 'event_orders');
    $hasGrants = dashTableExists($conn, 'event_access_grants');
    $grantRoleColumn = $hasGrants ? dashAccessGrantRoleColumn($conn) : null;
    $grantStatusWhere = ($hasGrants && dashColumnExists($conn, 'event_access_grants', 'status'))
        ? " AND g.status = 'active'"
        : '';

    $revenueSelect = $hasOrders
        ? "(
                SELECT COALESCE(SUM(eo.final_amount), 0)
                FROM event_orders eo
                WHERE eo.event_id = e.event_id
                  AND eo.payment_status IN ('paid', 'partial_refunded')
            ) AS revenue_total"
        : '0 AS revenue_total';
    $staffSelect = ($hasGrants && $grantRoleColumn !== null)
        ? "(
                SELECT COUNT(*)
                FROM event_access_grants g
                WHERE g.event_id = e.event_id{$grantStatusWhere}
                  AND g.`{$grantRoleColumn}` = 'staff'
            ) AS staff_count"
        : '0 AS staff_count';
    $sponsorSelect = ($hasGrants && $grantRoleColumn !== null)
        ? "(
                SELECT COUNT(*)
                FROM event_access_grants g
                WHERE g.event_id = e.event_id{$grantStatusWhere}
                  AND g.`{$grantRoleColumn}` = 'sponsor'
            ) AS sponsor_count"
        : '0 AS sponsor_count';

    $sql = "
        SELECT
            e.event_id,
            e.title,
            e.location,
            e.event_start,
            e.event_end,
            e.reg_start,
            e.reg_end,
            e.max_participant,
            $priceSelect,
            $currencySelect,
            (
                SELECT i.image_path
                FROM event_images i
                WHERE i.event_id = e.event_id
                ORDER BY i.image_id ASC
                LIMIT 1
            ) AS cover_image,
            COALESCE(SUM(r.status = 'pending'), 0) AS pending_count,
            COALESCE(SUM(r.status = 'approved'), 0) AS approved_count,
            COALESCE(SUM(r.status = 'rejected'), 0) AS rejected_count,
            COALESCE(SUM(r.status = 'checked_in'), 0) AS checkedin_count,
            (
                SELECT COUNT(*)
                FROM event_refund_requests rr
                WHERE rr.event_id = e.event_id
                  AND rr.status IN ('pending','approved','processing')
            ) AS refund_request_count,
            COALESCE(SUM(r.status IN ('pending','approved','checked_in')), 0) AS total_registered,
            $revenueSelect,
            $staffSelect,
            $sponsorSelect
        FROM events e
        LEFT JOIN registrations r ON r.event_id = e.event_id
        WHERE e.creator_id = ?
        GROUP BY e.event_id
        ORDER BY e.event_start DESC
    ";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        return [];
    }

    $stmt->bind_param('i', $creatorId);
    $stmt->execute();

    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    foreach ($rows as &$row) {
        $row['event_id'] = (int)($row['event_id'] ?? 0);
        $row['max_participant'] = (int)($row['max_participant'] ?? 0);
        $row['pending_count'] = (int)($row['pending_count'] ?? 0);
        $row['approved_count'] = (int)($row['approved_count'] ?? 0);
        $row['rejected_count'] = (int)($row['rejected_count'] ?? 0);
        $row['checkedin_count'] = (int)($row['checkedin_count'] ?? 0);
        $row['refund_request_count'] = (int)($row['refund_request_count'] ?? 0);
        $row['total_registered'] = (int)($row['total_registered'] ?? 0);
        $row['revenue_total'] = (float)($row['revenue_total'] ?? 0);
        $row['staff_count'] = (int)($row['staff_count'] ?? 0);
        $row['sponsor_count'] = (int)($row['sponsor_count'] ?? 0);
        $row['price'] = (float)($row['price'] ?? 0);
        $row['currency'] = (string)($row['currency'] ?? 'THB');
    }
    unset($row);

    return $rows;
}

function fetchDashboardEventInsights(mysqli $conn, array $eventIds, int $ownerId): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $eventIds), static fn($id) => $id > 0)));
    $insights = [];
    foreach ($ids as $id) {
        $insights[$id] = getEmptyEventInsight();
    }
    if (empty($ids)) {
        return $insights;
    }

    foreach ($ids as $eventId) {
        $insights[$eventId]['registration_trend'] = fetchRegistrationTrend($conn, $eventId);
        $insights[$eventId]['checkin_trend'] = fetchCheckinTrend($conn, $eventId);
        $insights[$eventId]['ticket_status'] = fetchTicketStatusBreakdown($conn, $eventId);
        $insights[$eventId]['revenue_trend'] = fetchRevenueTrend($conn, $eventId);
        $insights[$eventId]['zones'] = fetchZoneUsage($conn, $eventId);
        $insights[$eventId]['grants'] = fetchAccessGrants($conn, $eventId);
        $insights[$eventId]['reviews'] = fetchOrganizerEventReviews($conn, $eventId, $ownerId);
        $insights[$eventId]['review_count'] = count($insights[$eventId]['reviews']);
        if ($insights[$eventId]['review_count'] > 0) {
            $insights[$eventId]['review_average'] = round(array_sum(array_column($insights[$eventId]['reviews'], 'rating')) / $insights[$eventId]['review_count'], 1);
        }

        foreach ($insights[$eventId]['grants'] as $grant) {
            if (($grant['access_role'] ?? '') === 'staff') {
                $insights[$eventId]['staff_count']++;
            } elseif (($grant['access_role'] ?? '') === 'sponsor') {
                $insights[$eventId]['sponsor_count']++;
            }
        }

        $insights[$eventId]['free_grants_count'] = (int)array_sum(array_map(
            static fn($grant) => (int)($grant['free_ticket_limit'] ?? 0),
            $insights[$eventId]['grants']
        ));
        $insights[$eventId]['revenue_total'] = (float)array_sum(array_map(
            static fn($row) => (float)($row['amount'] ?? 0),
            $insights[$eventId]['revenue_trend']
        ));
    }

    return $insights;
}

function getEmptyEventInsight(): array
{
    return [
        'registration_trend' => [],
        'checkin_trend' => [],
        'ticket_status' => [],
        'revenue_trend' => [],
        'zones' => [],
        'grants' => [],
        'reviews' => [],
        'review_count' => 0,
        'review_average' => 0.0,
        'staff_count' => 0,
        'sponsor_count' => 0,
        'free_grants_count' => 0,
        'revenue_total' => 0.0,
    ];
}

function fetchOrganizerEventReviews(mysqli $conn, int $eventId, int $ownerId): array
{
    if (!dashTableExists($conn, 'event_reviews')) {
        return [];
    }

    $stmt = $conn->prepare(
        "SELECT er.review_id, er.rating, er.comment, er.created_at, er.updated_at,
                u.user_id AS reviewer_id, u.name AS reviewer_name, u.email AS reviewer_email,
                r.status AS registration_status, r.checked_in
         FROM event_reviews er
         INNER JOIN events e ON e.event_id = er.event_id
         INNER JOIN users u ON u.user_id = er.user_id
         LEFT JOIN registrations r ON r.reg_id = er.registration_id
         WHERE er.event_id = ? AND e.creator_id = ? AND er.status = 'published'
         ORDER BY er.updated_at DESC, er.review_id DESC"
    );
    if ($stmt === false) return [];

    $stmt->bind_param('ii', $eventId, $ownerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    return array_map(static fn(array $row): array => [
        'review_id' => (int)($row['review_id'] ?? 0),
        'rating' => (int)($row['rating'] ?? 0),
        'comment' => (string)($row['comment'] ?? ''),
        'created_at' => (string)($row['created_at'] ?? ''),
        'updated_at' => (string)($row['updated_at'] ?? ''),
        'reviewer_id' => (int)($row['reviewer_id'] ?? 0),
        'reviewer_name' => (string)($row['reviewer_name'] ?? ''),
        'reviewer_email' => (string)($row['reviewer_email'] ?? ''),
        'registration_status' => (string)($row['registration_status'] ?? ''),
        'checked_in' => (string)($row['checked_in'] ?? ''),
    ], $rows);
}

function fetchRegistrationTrend(mysqli $conn, int $eventId): array
{
    if (!dashColumnExists($conn, 'registrations', 'registered_at')) {
        return [];
    }
    $stmt = $conn->prepare(
        "SELECT DATE(registered_at) AS label, COUNT(*) AS count
         FROM registrations
         WHERE event_id = ?
         GROUP BY DATE(registered_at)
         ORDER BY label ASC"
    );
    if ($stmt === false) return [];
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return array_map(static fn($row) => [
        'label' => (string)$row['label'],
        'count' => (int)$row['count'],
    ], $rows);
}

function fetchCheckinTrend(mysqli $conn, int $eventId): array
{
    if (!dashColumnExists($conn, 'registrations', 'checked_in')) {
        return [];
    }
    $stmt = $conn->prepare(
        "SELECT DATE_FORMAT(checked_in, '%H:00') AS label, COUNT(*) AS count
         FROM registrations
         WHERE event_id = ? AND checked_in IS NOT NULL
         GROUP BY DATE_FORMAT(checked_in, '%H:00')
         ORDER BY MIN(checked_in) ASC"
    );
    if ($stmt === false) return [];
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return array_map(static fn($row) => [
        'label' => (string)$row['label'],
        'count' => (int)$row['count'],
    ], $rows);
}

function fetchTicketStatusBreakdown(mysqli $conn, int $eventId): array
{
    $stmt = $conn->prepare(
        "SELECT status, COUNT(*) AS count
         FROM registrations
         WHERE event_id = ?
         GROUP BY status
         ORDER BY count DESC"
    );
    if ($stmt === false) return [];
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return array_map(static fn($row) => [
        'label' => (string)$row['status'],
        'count' => (int)$row['count'],
    ], $rows);
}

function fetchRevenueTrend(mysqli $conn, int $eventId): array
{
    if (dashTableExists($conn, 'event_orders')) {
        $stmt = $conn->prepare(
            "SELECT DATE(COALESCE(paid_at, created_at)) AS label, SUM(final_amount) AS amount, COUNT(*) AS count
             FROM event_orders
             WHERE event_id = ? AND payment_status IN ('paid','partial_refunded')
             GROUP BY DATE(COALESCE(paid_at, created_at))
             ORDER BY label ASC"
        );
        if ($stmt !== false) {
            $stmt->bind_param('i', $eventId);
            $stmt->execute();
            $res = $stmt->get_result();
            $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
            $stmt->close();
            return array_map(static fn($row) => [
                'label' => (string)$row['label'],
                'amount' => (float)$row['amount'],
                'count' => (int)$row['count'],
            ], $rows);
        }
    }

    if (!dashColumnExists($conn, 'registrations', 'total_amount')) {
        return [];
    }
    $stmt = $conn->prepare(
        "SELECT DATE(registered_at) AS label, SUM(total_amount) AS amount, COUNT(*) AS count
         FROM registrations
         WHERE event_id = ? AND status IN ('approved','checked_in')
         GROUP BY DATE(registered_at)
         ORDER BY label ASC"
    );
    if ($stmt === false) return [];
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return array_map(static fn($row) => [
        'label' => (string)$row['label'],
        'amount' => (float)$row['amount'],
        'count' => (int)$row['count'],
    ], $rows);
}

function fetchZoneUsage(mysqli $conn, int $eventId): array
{
    if (!dashTableExists($conn, 'event_ticket_zones')) {
        return [];
    }

    $hasSeats = dashTableExists($conn, 'event_seats');
    if ($hasSeats) {
        $stmt = $conn->prepare(
            "SELECT z.zone_id, z.zone_code, z.zone_name, z.color_hex, z.price, z.capacity,
                    SUM(s.status = 'available') AS available_count,
                    SUM(s.status = 'reserved') AS reserved_count,
                    SUM(s.status = 'paid') AS paid_count,
                    SUM(s.status = 'blocked') AS blocked_count,
                    COUNT(s.seat_id) AS seat_count
             FROM event_ticket_zones z
             LEFT JOIN event_seats s ON s.zone_id = z.zone_id
             WHERE z.event_id = ? AND z.is_active = 1
             GROUP BY z.zone_id
             ORDER BY z.sort_order ASC, z.zone_id ASC"
        );
    } else {
        $stmt = $conn->prepare(
            "SELECT zone_id, zone_code, zone_name, color_hex, price, capacity,
                    0 AS available_count, 0 AS reserved_count, 0 AS paid_count, 0 AS blocked_count, capacity AS seat_count
             FROM event_ticket_zones
             WHERE event_id = ? AND is_active = 1
             ORDER BY sort_order ASC, zone_id ASC"
        );
    }
    if ($stmt === false) return [];
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return array_map(static fn($row) => [
        'zone_id' => (int)$row['zone_id'],
        'zone_code' => (string)$row['zone_code'],
        'zone_name' => (string)$row['zone_name'],
        'color_hex' => (string)$row['color_hex'],
        'price' => (float)$row['price'],
        'capacity' => (int)$row['capacity'],
        'available_count' => (int)$row['available_count'],
        'reserved_count' => (int)$row['reserved_count'],
        'paid_count' => (int)$row['paid_count'],
        'blocked_count' => (int)$row['blocked_count'],
        'seat_count' => (int)$row['seat_count'],
    ], $rows);
}

function fetchAccessGrants(mysqli $conn, int $eventId): array
{
    if (!dashTableExists($conn, 'event_access_grants')) {
        return [];
    }

    $roleColumn = dashAccessGrantRoleColumn($conn);
    if ($roleColumn === null) {
        return [];
    }

    $statusSelect = dashColumnExists($conn, 'event_access_grants', 'status')
        ? 'g.status'
        : "'active' AS status";
    $statusWhere = dashColumnExists($conn, 'event_access_grants', 'status')
        ? " AND g.status = 'active'"
        : '';
    $dateSelect = dashColumnExists($conn, 'event_access_grants', 'granted_at')
        ? 'g.granted_at'
        : (dashColumnExists($conn, 'event_access_grants', 'created_at') ? 'g.created_at AS granted_at' : 'NULL AS granted_at');
    $noteSelect = dashColumnExists($conn, 'event_access_grants', 'note') ? 'g.note' : "'' AS note";
    $verifySelect = dashColumnExists($conn, 'event_access_grants', 'can_verify_otp') ? 'g.can_verify_otp' : '0 AS can_verify_otp';
    $freeLimitSelect = dashColumnExists($conn, 'event_access_grants', 'free_ticket_limit') ? 'g.free_ticket_limit' : '0 AS free_ticket_limit';
    $usedLimitSelect = dashColumnExists($conn, 'event_access_grants', 'used_free_ticket_count') ? 'g.used_free_ticket_count' : '0 AS used_free_ticket_count';

    $sql = "SELECT g.grant_id, g.user_id, g.`$roleColumn` AS access_role, $verifySelect, $freeLimitSelect,
                   $usedLimitSelect, $statusSelect, $noteSelect, $dateSelect, u.name, u.email
            FROM event_access_grants g
            INNER JOIN users u ON u.user_id = g.user_id
            WHERE g.event_id = ?$statusWhere
            ORDER BY FIELD(g.`$roleColumn`, 'staff', 'sponsor'), COALESCE(granted_at, '1970-01-01') DESC";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) return [];
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    return array_map(static fn($row) => [
        'grant_id' => (int)$row['grant_id'],
        'user_id' => (int)$row['user_id'],
        'access_role' => (string)$row['access_role'],
        'can_verify_otp' => (int)$row['can_verify_otp'],
        'free_ticket_limit' => (int)$row['free_ticket_limit'],
        'used_free_ticket_count' => (int)$row['used_free_ticket_count'],
        'status' => (string)$row['status'],
        'note' => (string)($row['note'] ?? ''),
        'granted_at' => (string)($row['granted_at'] ?? ''),
        'name' => (string)$row['name'],
        'email' => (string)$row['email'],
    ], $rows);
}

function fetchEventDemographics(mysqli $conn, array $eventIds): array
{
    $ids = [];
    foreach ($eventIds as $id) {
        $id = (int)$id;
        if ($id > 0) {
            $ids[$id] = true;
        }
    }
    $ids = array_keys($ids);

    if (empty($ids)) {
        return ['age' => [], 'career' => [], 'gender' => []];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $statusSql = "('approved','checked_in')";

    $ageByEvent = [];
    $sqlAge = "
        SELECT
            r.event_id,
            CASE
                WHEN u.age < 18 THEN 'ต่ำกว่า 18'
                WHEN u.age BETWEEN 18 AND 24 THEN '18-24'
                WHEN u.age BETWEEN 25 AND 34 THEN '25-34'
                WHEN u.age BETWEEN 35 AND 44 THEN '35-44'
                ELSE '45+'
            END AS age_group,
            COUNT(*) AS cnt
        FROM registrations r
        INNER JOIN users u ON u.user_id = r.user_id
        WHERE r.event_id IN ($placeholders)
          AND r.status IN $statusSql
        GROUP BY r.event_id, age_group
        ORDER BY r.event_id ASC
    ";

    $stmt = $conn->prepare($sqlAge);
    if ($stmt !== false) {
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        foreach ($rows as $row) {
            $eid = (int)($row['event_id'] ?? 0);
            $label = (string)($row['age_group'] ?? '');
            $cnt = (int)($row['cnt'] ?? 0);
            if ($eid > 0 && $label !== '') {
                $ageByEvent[$eid][] = ['label' => $label, 'count' => $cnt];
            }
        }
    }

    $careerRawByEvent = [];
    $sqlCareer = "
        SELECT
            r.event_id,
            COALESCE(NULLIF(TRIM(u.career), ''), 'ไม่ระบุ') AS career,
            COUNT(*) AS cnt
        FROM registrations r
        INNER JOIN users u ON u.user_id = r.user_id
        WHERE r.event_id IN ($placeholders)
          AND r.status IN $statusSql
        GROUP BY r.event_id, career
        ORDER BY r.event_id ASC, cnt DESC
    ";

    $stmt2 = $conn->prepare($sqlCareer);
    if ($stmt2 !== false) {
        $stmt2->bind_param($types, ...$ids);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        $rows2 = $res2 ? $res2->fetch_all(MYSQLI_ASSOC) : [];
        $stmt2->close();

        foreach ($rows2 as $row) {
            $eid = (int)($row['event_id'] ?? 0);
            $label = (string)($row['career'] ?? 'ไม่ระบุ');
            $cnt = (int)($row['cnt'] ?? 0);
            if ($eid > 0) {
                $careerRawByEvent[$eid][] = ['label' => $label, 'count' => $cnt];
            }
        }
    }

    $careerByEvent = [];
    foreach ($careerRawByEvent as $eid => $items) {
        $top = array_slice($items, 0, 5);
        $rest = array_slice($items, 5);
        $otherSum = 0;
        foreach ($rest as $r) {
            $otherSum += (int)($r['count'] ?? 0);
        }
        if ($otherSum > 0) {
            $top[] = ['label' => 'อื่นๆ', 'count' => $otherSum];
        }
        $careerByEvent[(int)$eid] = $top;
    }

    $genderByEvent = [];
    $sqlGender = "
        SELECT
            r.event_id,
            CASE
                WHEN TRIM(COALESCE(u.gender, '')) = '' THEN 'ไม่ระบุ'
                ELSE TRIM(u.gender)
            END AS gender_label,
            COUNT(*) AS cnt
        FROM registrations r
        INNER JOIN users u ON u.user_id = r.user_id
        WHERE r.event_id IN ($placeholders)
          AND r.status IN $statusSql
        GROUP BY r.event_id, gender_label
        ORDER BY r.event_id ASC, cnt DESC
    ";

    $stmt3 = $conn->prepare($sqlGender);
    if ($stmt3 !== false) {
        $stmt3->bind_param($types, ...$ids);
        $stmt3->execute();
        $res3 = $stmt3->get_result();
        $rows3 = $res3 ? $res3->fetch_all(MYSQLI_ASSOC) : [];
        $stmt3->close();

        foreach ($rows3 as $row) {
            $eid = (int)($row['event_id'] ?? 0);
            $label = (string)($row['gender_label'] ?? 'ไม่ระบุ');
            $cnt = (int)($row['cnt'] ?? 0);
            if ($eid > 0) {
                $genderByEvent[$eid][] = ['label' => $label, 'count' => $cnt];
            }
        }
    }

    return [
        'age' => $ageByEvent,
        'career' => $careerByEvent,
        'gender' => $genderByEvent,
    ];
}

function dashUserOwnsEvent(mysqli $conn, int $eventId, int $userId): bool
{
    $stmt = $conn->prepare('SELECT event_id FROM events WHERE event_id = ? AND creator_id = ? LIMIT 1');
    if ($stmt === false) return false;
    $stmt->bind_param('ii', $eventId, $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $found = $res && $res->num_rows > 0;
    $stmt->close();
    return $found;
}

function dashAccessGrantRoleColumn(mysqli $conn): ?string
{
    if (!dashTableExists($conn, 'event_access_grants')) {
        return null;
    }
    if (dashColumnExists($conn, 'event_access_grants', 'access_role')) {
        return 'access_role';
    }
    if (dashColumnExists($conn, 'event_access_grants', 'role')) {
        return 'role';
    }
    return null;
}

function dashUpsertAccessGrant(mysqli $conn, int $eventId, int $targetUserId, string $role, int $canVerify, int $freeLimit, string $note, int $grantedBy): ?string
{
    if (!dashTableExists($conn, 'event_access_grants')) {
        return 'ยังไม่มีตาราง event_access_grants กรุณารัน migration ระบบบัตรก่อน';
    }

    $roleColumn = dashAccessGrantRoleColumn($conn);
    if ($roleColumn === null) {
        return 'ตาราง event_access_grants ไม่มีคอลัมน์ role/access_role';
    }

    $columns = ['event_id', 'user_id', $roleColumn];
    $types = 'iis';
    $params = [$eventId, $targetUserId, $role];
    $updates = ["`$roleColumn` = VALUES(`$roleColumn`)"];

    if (dashColumnExists($conn, 'event_access_grants', 'can_verify_otp')) {
        $columns[] = 'can_verify_otp';
        $types .= 'i';
        $params[] = $canVerify;
        $updates[] = 'can_verify_otp = VALUES(can_verify_otp)';
    }
    if (dashColumnExists($conn, 'event_access_grants', 'free_ticket_limit')) {
        $columns[] = 'free_ticket_limit';
        $types .= 'i';
        $params[] = $freeLimit;
        $updates[] = 'free_ticket_limit = VALUES(free_ticket_limit)';
    }
    if (dashColumnExists($conn, 'event_access_grants', 'status')) {
        $columns[] = 'status';
        $types .= 's';
        $params[] = 'active';
        $updates[] = "status = 'active'";
    }
    if (dashColumnExists($conn, 'event_access_grants', 'note')) {
        $columns[] = 'note';
        $types .= 's';
        $params[] = $note;
        $updates[] = 'note = VALUES(note)';
    }
    if (dashColumnExists($conn, 'event_access_grants', 'granted_by')) {
        $columns[] = 'granted_by';
        $types .= 'i';
        $params[] = $grantedBy;
        $updates[] = 'granted_by = VALUES(granted_by)';
    }
    if (dashColumnExists($conn, 'event_access_grants', 'granted_at')) {
        $columns[] = 'granted_at';
        $types .= 's';
        $params[] = date('Y-m-d H:i:s');
        $updates[] = 'granted_at = VALUES(granted_at)';
    }
    if (dashColumnExists($conn, 'event_access_grants', 'revoked_at')) {
        $updates[] = 'revoked_at = NULL';
    }
    if (dashColumnExists($conn, 'event_access_grants', 'updated_at')) {
        $updates[] = 'updated_at = CURRENT_TIMESTAMP';
    }

    $quoted = array_map(static fn(string $column): string => '`' . str_replace('`', '', $column) . '`', $columns);
    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $sql = 'INSERT INTO event_access_grants (' . implode(',', $quoted) . ') VALUES (' . $placeholders . ')'
         . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        return 'ไม่สามารถมอบสิทธิ์ได้ในขณะนี้';
    }
    dashBindStmt($stmt, $types, $params);
    if (!$stmt->execute()) {
        $stmt->close();
        return 'มอบสิทธิ์ไม่สำเร็จ';
    }
    $stmt->close();
    return null;
}

function dashRevokeAccessGrant(mysqli $conn, int $grantId, int $eventId): ?string
{
    if (!dashTableExists($conn, 'event_access_grants')) {
        return 'ยังไม่มีตาราง event_access_grants';
    }

    if (dashColumnExists($conn, 'event_access_grants', 'status')) {
        $set = "status = 'revoked'";
        if (dashColumnExists($conn, 'event_access_grants', 'revoked_at')) {
            $set .= ', revoked_at = CURRENT_TIMESTAMP';
        }
        if (dashColumnExists($conn, 'event_access_grants', 'updated_at')) {
            $set .= ', updated_at = CURRENT_TIMESTAMP';
        }
        $stmt = $conn->prepare("UPDATE event_access_grants SET $set WHERE grant_id = ? AND event_id = ? LIMIT 1");
    } else {
        $stmt = $conn->prepare('DELETE FROM event_access_grants WHERE grant_id = ? AND event_id = ? LIMIT 1');
    }

    if ($stmt === false) {
        return 'ไม่สามารถยกเลิกสิทธิ์ได้ในขณะนี้';
    }
    $stmt->bind_param('ii', $grantId, $eventId);
    if (!$stmt->execute()) {
        $stmt->close();
        return 'ยกเลิกสิทธิ์ไม่สำเร็จ';
    }
    $stmt->close();
    return null;
}

function dashBindStmt(mysqli_stmt $stmt, string $types, array $params): void
{
    $refs = [];
    foreach ($params as $key => $value) {
        $refs[$key] = &$params[$key];
    }
    $stmt->bind_param($types, ...$refs);
}

function dashFindUserByEmail(mysqli $conn, string $email): ?array
{
    if ($email === '') return null;
    $stmt = $conn->prepare('SELECT user_id, name, email FROM users WHERE email = ? LIMIT 1');
    if ($stmt === false) return null;
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

function dashTableExists(mysqli $conn, string $table): bool
{
    $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    if ($stmt === false) return false;
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return (int)($row['c'] ?? 0) > 0;
}

function dashColumnExists(mysqli $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    if ($stmt === false) return false;
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return (int)($row['c'] ?? 0) > 0;
}
