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

    $query = trim((string)($_GET['search'] ?? ''));
    $startAtRaw = trim((string)($_GET['start_at'] ?? ''));
    $endAtRaw = trim((string)($_GET['end_at'] ?? ''));
    $favoritesOnly = trim((string)($_GET['favorites'] ?? '')) === '1';
    $showAllEvents = trim((string)($_GET['show_all'] ?? '')) === '1';
    $liveSnapshot = trim((string)($_GET['live'] ?? '')) === '1';
    $startAt = parseDateTimeLocal($startAtRaw);
    $endAt = parseDateTimeLocal($endAtRaw);
    $userId = (int)$_SESSION['user_id'];

    $conn = getConnection();
    $conn->query("SET time_zone = '+07:00'");
    expireExpiredPaymentReservations($conn);
    $vipProfile = badomenFetchVipProfile($conn, $userId);
    $events = fetchOtherUserEvents($conn, $userId, $query, $startAt, $endAt, $favoritesOnly);
    $events = enrichEventDiscoveryData($conn, $events, $userId);
    $conn->close();

    renderView('home_in', [
        'title' => 'Home',
        'query' => $query,
        'startAt' => $startAtRaw,
        'endAt' => $endAtRaw,
        'favoritesOnly' => $favoritesOnly,
        'showAllEvents' => $showAllEvents,
        'events' => $events,
        'viewerIsVip' => (bool)$vipProfile['is_vip'],
        'vipDiscountPerTicket' => badomenVipDiscountPerTicket(),
        'clearGuestFavorites' => !empty($_SESSION['_clear_guest_favorites']),
        'errors' => $liveSnapshot ? [] : getFlashMessages('home_in_errors'),
        'successes' => $liveSnapshot ? [] : getFlashMessages('home_in_successes'),
    ]);
    unset($_SESSION['_clear_guest_favorites']);
}

function post(): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . appUrl('/login'));
        exit;
    }

    $postedAction = trim((string)($_POST['action'] ?? ''));
    $postedTicketAction = trim((string)($_POST['ticket_action'] ?? ''));
    $wantsJson = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
        || str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
        || $postedAction === 'toggle_favorite'
        || in_array($postedTicketAction, ['reserve_ticket', 'mock_pay'], true);

    if (!verifyCsrfToken($_POST['_csrf'] ?? null)) {
        if ($wantsJson) {
            homeInJsonResponse(['ok' => false, 'error' => 'csrf_expired'], 419);
        }
        addFlashMessage('home_in_errors', 'คำขอหมดอายุ กรุณาลองใหม่อีกครั้ง');
        header('Location: ' . appUrl('/home_in'));
        exit;
    }

    $userId = (int)$_SESSION['user_id'];

    if ($postedAction === 'toggle_favorite') {
        handleFavoriteToggle($userId);
    }

    if ($postedTicketAction === 'mock_pay') {
        handleMockPaymentComplete($userId, $wantsJson);
    }

    $eventId = (int)($_POST['event_id'] ?? 0);
    $redirectUrl = buildHomeInReturnUrl($_POST);

    if ($eventId <= 0) {
        if ($wantsJson) {
            homeInJsonResponse(['ok' => false, 'error' => 'invalid_event', 'message' => 'ไม่พบกิจกรรมที่ต้องการเข้าร่วม'], 422);
        }
        addFlashMessage('home_in_errors', 'ไม่พบกิจกรรมที่ต้องการเข้าร่วม');
        header('Location: ' . appUrl($redirectUrl));
        exit;
    }

    $conn = getConnection();
    $conn->query("SET time_zone = '+07:00'");
    expireExpiredPaymentReservations($conn);

    $event = getEventForJoinRequest($conn, $eventId, $userId);
    if (!$event) {
        $conn->close();
        if ($wantsJson) {
            homeInJsonResponse(['ok' => false, 'error' => 'event_not_found', 'message' => 'ไม่พบกิจกรรมที่ต้องการเข้าร่วม'], 404);
        }
        addFlashMessage('home_in_errors', 'ไม่พบกิจกรรมที่ต้องการเข้าร่วม');
        header('Location: ' . appUrl($redirectUrl));
        exit;
    }

    $validationError = validateJoinRequest($event, $userId);
    if ($validationError !== null) {
        $conn->close();
        if ($wantsJson) {
            homeInJsonResponse(['ok' => false, 'error' => 'join_validation_failed', 'message' => $validationError], 422);
        }
        addFlashMessage('home_in_errors', $validationError);
        header('Location: ' . appUrl($redirectUrl));
        exit;
    }

    $result = reserveTicketOrJoin($conn, $event, $userId, $_POST);
    $conn->close();

    if ($wantsJson && $postedTicketAction === 'reserve_ticket') {
        homeInJsonResponse($result, !empty($result['ok']) ? 200 : 422);
    }

    if ($result['ok']) {
        addFlashMessage('home_in_successes', (string)$result['message']);
    } else {
        addFlashMessage('home_in_errors', (string)$result['message']);
    }

    header('Location: ' . appUrl($redirectUrl));
    exit;
}

function buildHomeInReturnUrl(array $post): string
{
    $query = trim((string)($post['return_query'] ?? ''));
    $returnStartAt = trim((string)($post['return_start_at'] ?? ''));
    $returnEndAt = trim((string)($post['return_end_at'] ?? ''));
    $returnShowAll = trim((string)($post['return_show_all'] ?? '')) === '1';
    $returnFavoritesOnly = trim((string)($post['return_favorites_only'] ?? '')) === '1';

    $queryParams = [];
    if ($query !== '') $queryParams['search'] = $query;
    if ($returnStartAt !== '') $queryParams['start_at'] = $returnStartAt;
    if ($returnEndAt !== '') $queryParams['end_at'] = $returnEndAt;
    if ($returnShowAll) $queryParams['show_all'] = '1';
    if ($returnFavoritesOnly) $queryParams['favorites'] = '1';

    return '/home_in' . (!empty($queryParams) ? '?' . http_build_query($queryParams) : '');
}


function handleFavoriteToggle(int $userId): void
{
    $eventId = (int)($_POST['event_id'] ?? 0);
    if ($eventId <= 0) {
        homeInJsonResponse(['ok' => false, 'error' => 'invalid_event'], 422);
    }

    $conn = getConnection();
    $conn->query("SET time_zone = '+07:00'");

    try {
        ensureEventFavoritesTable($conn);

        $stmt = $conn->prepare('SELECT event_id, creator_id FROM events WHERE event_id = ? LIMIT 1');
        if ($stmt === false) {
            throw new RuntimeException('event_query_failed');
        }
        $stmt->bind_param('i', $eventId);
        $stmt->execute();
        $event = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$event || (int)$event['creator_id'] === $userId) {
            $conn->close();
            homeInJsonResponse(['ok' => false, 'error' => 'event_not_available'], 404);
        }

        $exists = isEventFavorite($conn, $eventId, $userId);
        if ($exists) {
            $stmt = $conn->prepare('DELETE FROM event_favorites WHERE event_id = ? AND user_id = ?');
            if ($stmt === false) {
                throw new RuntimeException('favorite_delete_prepare_failed');
            }
            $stmt->bind_param('ii', $eventId, $userId);
            $stmt->execute();
            $stmt->close();
            $saved = false;
        } else {
            $stmt = $conn->prepare('INSERT IGNORE INTO event_favorites (event_id, user_id) VALUES (?, ?)');
            if ($stmt === false) {
                throw new RuntimeException('favorite_insert_prepare_failed');
            }
            $stmt->bind_param('ii', $eventId, $userId);
            $stmt->execute();
            $stmt->close();
            $saved = true;
        }

        $favoriteCount = countEventFavorites($conn, $eventId);
        $conn->close();
        homeInJsonResponse([
            'ok' => true,
            'saved' => $saved,
            'favorite_count' => $favoriteCount,
        ]);
    } catch (Throwable $exception) {
        $conn->close();
        homeInJsonResponse(['ok' => false, 'error' => 'favorite_failed'], 500);
    }
}

function ensureEventFavoritesTable(mysqli $conn): void
{
    $sql = "CREATE TABLE IF NOT EXISTS event_favorites (
        favorite_id INT AUTO_INCREMENT PRIMARY KEY,
        event_id INT NOT NULL,
        user_id INT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_event_favorite_user_event (user_id, event_id),
        KEY idx_event_favorites_event_id (event_id),
        CONSTRAINT fk_event_favorites_event FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
        CONSTRAINT fk_event_favorites_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if (!$conn->query($sql)) {
        throw new RuntimeException('favorite_table_failed');
    }
}

function isEventFavorite(mysqli $conn, int $eventId, int $userId): bool
{
    $stmt = $conn->prepare('SELECT 1 FROM event_favorites WHERE event_id = ? AND user_id = ? LIMIT 1');
    if ($stmt === false) return false;
    $stmt->bind_param('ii', $eventId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmt->close();
    return $exists;
}

function countEventFavorites(mysqli $conn, int $eventId): int
{
    $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM event_favorites WHERE event_id = ?');
    if ($stmt === false) return 0;
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['total'] ?? 0);
}

function homeInJsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function validateJoinRequest(array $event, int $userId): ?string
{
    if ((int)$event['creator_id'] === $userId) {
        return 'ไม่สามารถขอเข้าร่วมกิจกรรมของตัวเองได้';
    }

    $ownStatus = normalizeHomeInRegistrationStatus((string)($event['own_registration_status'] ?? ''));
    $ownPaymentStatus = normalizeHomeInRegistrationStatus((string)($event['own_payment_status'] ?? ''));
    $canRejoin = in_array($ownStatus, ['rejected', 'cancelled', 'canceled', 'refunded', 'refund', 'expired'], true)
        || in_array($ownPaymentStatus, ['refunded', 'cancelled', 'canceled', 'expired'], true);

    if ((int)($event['already_requested'] ?? 0) > 0 && !$canRejoin) {
        return 'คุณได้ขอเข้าร่วมกิจกรรมนี้แล้ว';
    }

    $tz = new DateTimeZone('Asia/Bangkok');
    $now = new DateTimeImmutable('now', $tz);
    $regStartDT = parseDbDateTime((string)($event['reg_start'] ?? ''), $tz, false);
    $regEndDT = parseDbDateTime((string)($event['reg_end'] ?? ''), $tz, true);

    if ($regStartDT && $now < $regStartDT) {
        return 'กิจกรรมนี้ยังไม่เปิดรับสมัคร';
    }

    if ($regEndDT && $now > $regEndDT) {
        return 'กิจกรรมนี้ปิดรับสมัครแล้ว';
    }

    $maxParticipant = (int)($event['max_participant'] ?? 0);
    $registeredCount = (int)($event['registered_count'] ?? 0);

    if ($maxParticipant > 0 && $registeredCount >= $maxParticipant) {
        return 'กิจกรรมนี้เต็มแล้ว';
    }

    return null;
}

function reserveTicketOrJoin(mysqli $conn, array $event, int $userId, array $post): array
{
    $ticketMode = (string)($event['ticket_mode'] ?? 'general');
    $selectionMode = (string)($event['seat_selection_mode'] ?? 'manual');
    $hasSeatSystem = badomenTableExists($conn, 'event_ticket_zones')
        && badomenTableExists($conn, 'event_seats');

    if ($ticketMode === 'general' || !$hasSeatSystem) {
        if ($ticketMode !== 'general' && !$hasSeatSystem) {
            return ['ok' => false, 'message' => 'ยังไม่ได้รัน migration ระบบโซนและที่นั่ง'];
        }
        return insertSimpleJoinRequest($conn, $event, $userId);
    }

    $limit = max(1, min(2, (int)($event['max_tickets_per_user'] ?? 1)));
    $quantity = max(1, min($limit, (int)($post['quantity'] ?? 1)));
    $zoneId = (int)($post['zone_id'] ?? 0);

    if ($selectionMode === 'random') {
        if ($zoneId <= 0) {
            return ['ok' => false, 'message' => 'กรุณาเลือกโซนก่อนดำเนินการต่อ'];
        }
        return reserveRandomSeats($conn, $event, $userId, $zoneId, $quantity);
    }

    $selectedIds = parseSelectedSeatIds((string)($post['selected_seat_ids'] ?? ''));
    if (count($selectedIds) < 1) {
        return ['ok' => false, 'message' => 'กรุณาเลือกที่นั่งก่อนดำเนินการต่อ'];
    }
    if (count($selectedIds) > $limit) {
        return ['ok' => false, 'message' => 'เลือกได้ไม่เกิน ' . $limit . ' ที่นั่งต่อคน'];
    }

    return reserveSelectedSeats($conn, $event, $userId, $selectedIds);
}

function insertSimpleJoinRequest(mysqli $conn, array $event, int $userId): array
{
    try {
        $price = (float)($event['price'] ?? 0);
        $pricing = badomenApplyVipDiscount($price, 1, badomenIsVipUser($conn, $userId));
        $regId = createRegistrationRecord($conn, (int)$event['event_id'], $userId, 1, (float)$pricing['unit_amount'], (float)$pricing['final_amount'], (string)($event['currency'] ?? 'THB'));
        if ($regId <= 0) {
            return ['ok' => false, 'message' => 'ไม่สามารถขอเข้าร่วมกิจกรรมได้ในขณะนี้'];
        }
        if (function_exists('queueJoinRequestNotifications')) {
            queueJoinRequestNotifications($conn, $event, $userId);
        }
        return buildReservationPaymentPayload($conn, $regId, $price > 0 ? 'จองสิทธิ์เข้าร่วมแล้ว กรุณาชำระเงินภายใน 10 นาที' : 'สมัครเข้าร่วมกิจกรรมฟรีเรียบร้อยแล้ว');
    } catch (Throwable $exception) {
        return ['ok' => false, 'message' => duplicateMessageFromException($exception) ?: 'เกิดข้อผิดพลาดระหว่างขอเข้าร่วมกิจกรรม'];
    }
}

function reserveSelectedSeats(mysqli $conn, array $event, int $userId, array $seatIds): array
{
    try {
        $conn->begin_transaction();
        $seats = fetchSeatsForUpdate($conn, (int)$event['event_id'], $seatIds);

        if (count($seats) !== count($seatIds)) {
            throw new RuntimeException('seat_unavailable');
        }

        $total = 0.0;
        $currency = 'THB';
        foreach ($seats as $seat) {
            if ((string)$seat['status'] !== 'available') {
                throw new RuntimeException('seat_unavailable');
            }
            $total += (float)$seat['price'];
            $currency = (string)($seat['currency'] ?? 'THB');
        }

        $quantity = count($seats);
        $pricing = badomenApplyVipDiscount($total, $quantity, badomenIsVipUser($conn, $userId));
        $regId = createRegistrationRecord($conn, (int)$event['event_id'], $userId, $quantity, (float)$pricing['unit_amount'], (float)$pricing['final_amount'], $currency);
        bindSeatsToRegistration($conn, $regId, $seats);
        markSeatsReserved($conn, $regId, $userId, array_column($seats, 'seat_id'));
        $conn->commit();

        if (function_exists('queueJoinRequestNotifications')) {
            queueJoinRequestNotifications($conn, $event, $userId);
        }

        return buildReservationPaymentPayload($conn, $regId, 'จองที่นั่งเรียบร้อยแล้ว กรุณาชำระเงินภายใน 10 นาที');
    } catch (Throwable $exception) {
        $conn->rollback();
        $message = duplicateMessageFromException($exception);
        if ($message === null) {
            $message = $exception->getMessage() === 'seat_unavailable'
                ? 'มีที่นั่งบางรายการถูกจองไปแล้ว กรุณาเลือกใหม่'
                : 'ไม่สามารถจองที่นั่งได้ในขณะนี้';
        }
        return ['ok' => false, 'message' => $message];
    }
}

function reserveRandomSeats(mysqli $conn, array $event, int $userId, int $zoneId, int $quantity): array
{
    try {
        $conn->begin_transaction();
        $seats = fetchRandomSeatsForUpdate($conn, (int)$event['event_id'], $zoneId, $quantity);

        if (count($seats) !== $quantity) {
            throw new RuntimeException('seat_unavailable');
        }

        $total = 0.0;
        $currency = 'THB';
        foreach ($seats as $seat) {
            $total += (float)$seat['price'];
            $currency = (string)($seat['currency'] ?? 'THB');
        }

        $pricing = badomenApplyVipDiscount($total, $quantity, badomenIsVipUser($conn, $userId));
        $regId = createRegistrationRecord($conn, (int)$event['event_id'], $userId, $quantity, (float)$pricing['unit_amount'], (float)$pricing['final_amount'], $currency);
        bindSeatsToRegistration($conn, $regId, $seats);
        markSeatsReserved($conn, $regId, $userId, array_column($seats, 'seat_id'));
        $conn->commit();

        if (function_exists('queueJoinRequestNotifications')) {
            queueJoinRequestNotifications($conn, $event, $userId);
        }

        return buildReservationPaymentPayload($conn, $regId, 'ระบบสุ่มที่นั่งในโซนที่เลือกเรียบร้อยแล้ว กรุณาชำระเงินภายใน 10 นาที');
    } catch (Throwable $exception) {
        $conn->rollback();
        $message = duplicateMessageFromException($exception);
        if ($message === null) {
            $message = $exception->getMessage() === 'seat_unavailable'
                ? 'ที่นั่งว่างในโซนนี้ไม่พอ กรุณาเลือกโซนหรือจำนวนใหม่'
                : 'ไม่สามารถสุ่มที่นั่งได้ในขณะนี้';
        }
        return ['ok' => false, 'message' => $message];
    }
}

function fetchSeatsForUpdate(mysqli $conn, int $eventId, array $seatIds): array
{
    $placeholders = implode(',', array_fill(0, count($seatIds), '?'));
    $types = 'i' . str_repeat('i', count($seatIds));
    $params = array_merge([$eventId], $seatIds);

    $stmt = $conn->prepare(
        "SELECT s.seat_id, s.event_id, s.zone_id, s.row_label, s.seat_number, s.seat_code, s.status,
                z.price, z.currency
         FROM event_seats s
         JOIN event_ticket_zones z ON z.zone_id = s.zone_id
         WHERE s.event_id = ? AND s.seat_id IN ($placeholders)
         FOR UPDATE"
    );
    if ($stmt === false) {
        throw new RuntimeException('seat_query_failed');
    }
    bindStmt($stmt, $types, $params);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    return $rows;
}

function fetchRandomSeatsForUpdate(mysqli $conn, int $eventId, int $zoneId, int $quantity): array
{
    $stmt = $conn->prepare(
        "SELECT s.seat_id, s.event_id, s.zone_id, s.row_label, s.seat_number, s.seat_code, s.status,
                z.price, z.currency
         FROM event_seats s
         JOIN event_ticket_zones z ON z.zone_id = s.zone_id
         WHERE s.event_id = ? AND s.zone_id = ? AND s.status = 'available'
         ORDER BY s.row_sort ASC, s.seat_number ASC
         LIMIT ?
         FOR UPDATE"
    );
    if ($stmt === false) {
        throw new RuntimeException('seat_query_failed');
    }
    $stmt->bind_param('iii', $eventId, $zoneId, $quantity);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    return $rows;
}

function createRegistrationRecord(mysqli $conn, int $eventId, int $userId, int $quantity, float $unitPrice, float $totalAmount, string $currency): int
{
    $columns = ['event_id', 'user_id'];
    $types = 'ii';
    $params = [$eventId, $userId];

    if (detectRegistrationStatusColumn($conn) !== null) {
        $columns[] = detectRegistrationStatusColumn($conn);
        $types .= 's';
        $params[] = $totalAmount > 0 ? 'pending' : 'approved';
    }
    if (badomenColumnExists($conn, 'registrations', 'ticket_code')) {
        $columns[] = 'ticket_code';
        $types .= 's';
        $params[] = createUuidV4();
    }
    if (badomenColumnExists($conn, 'registrations', 'quantity')) {
        $columns[] = 'quantity';
        $types .= 'i';
        $params[] = $quantity;
    }
    if (badomenColumnExists($conn, 'registrations', 'unit_price')) {
        $columns[] = 'unit_price';
        $types .= 'd';
        $params[] = $unitPrice;
    }
    if (badomenColumnExists($conn, 'registrations', 'total_amount')) {
        $columns[] = 'total_amount';
        $types .= 'd';
        $params[] = $totalAmount;
    }
    if (badomenColumnExists($conn, 'registrations', 'currency')) {
        $columns[] = 'currency';
        $types .= 's';
        $params[] = $currency;
    }

    $paymentRequired = $totalAmount > 0;
    $paymentStatus = $paymentRequired ? 'pending' : 'paid';
    $paymentExpiresAt = (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->modify('+10 minutes')->format('Y-m-d H:i:s');
    if (badomenColumnExists($conn, 'registrations', 'payment_status')) {
        $columns[] = 'payment_status';
        $types .= 's';
        $params[] = $paymentStatus;
    }
    if ($paymentRequired && badomenColumnExists($conn, 'registrations', 'payment_expires_at')) {
        $columns[] = 'payment_expires_at';
        $types .= 's';
        $params[] = $paymentExpiresAt;
    }
    if (!$paymentRequired && badomenColumnExists($conn, 'registrations', 'paid_at')) {
        $columns[] = 'paid_at';
        $types .= 's';
        $params[] = (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d H:i:s');
    }

    $quoted = array_map(static fn(string $col): string => '`' . str_replace('`', '', $col) . '`', $columns);
    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $sql = 'INSERT INTO registrations (' . implode(',', $quoted) . ') VALUES (' . $placeholders . ')';
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('registration_prepare_failed');
    }
    bindStmt($stmt, $types, $params);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $errno = $stmt->errno;
        $stmt->close();

        if ($errno === 1062) {
            $conn->begin_transaction();
            try {
                $reusedRegId = reactivateRejoinableRegistration($conn, $eventId, $userId, $quantity, $unitPrice, $totalAmount, $currency);
                if ($reusedRegId > 0) {
                    $conn->commit();
                    return $reusedRegId;
                }
                $conn->rollback();
            } catch (Throwable) {
                $conn->rollback();
            }
        }

        throw new RuntimeException($errno === 1062 ? 'duplicate_registration' : ($error ?: 'registration_insert_failed'), $errno);
    }
    $regId = (int)$conn->insert_id;
    $stmt->close();

    return $regId;
}


function normalizeHomeInRegistrationStatus(string $status): string
{
    return str_replace([' ', '-'], '_', strtolower(trim($status)));
}

function isHomeInRejoinableRegistration(array $registration): bool
{
    $status = normalizeHomeInRegistrationStatus((string)($registration['registration_status'] ?? ''));
    $paymentStatus = normalizeHomeInRegistrationStatus((string)($registration['payment_status'] ?? ''));

    return in_array($status, ['rejected', 'cancelled', 'canceled', 'refunded', 'refund', 'expired'], true)
        || in_array($paymentStatus, ['refunded', 'cancelled', 'canceled', 'expired'], true);
}

function reactivateRejoinableRegistration(mysqli $conn, int $eventId, int $userId, int $quantity, float $unitPrice, float $totalAmount, string $currency): int
{
    $statusColumn = detectRegistrationStatusColumn($conn);
    $selectColumns = ['reg_id'];
    $selectColumns[] = $statusColumn !== null ? '`' . str_replace('`', '', $statusColumn) . '` AS registration_status' : "'pending' AS registration_status";
    $selectColumns[] = badomenColumnExists($conn, 'registrations', 'payment_status') ? 'payment_status' : "'' AS payment_status";

    $stmt = $conn->prepare(
        'SELECT ' . implode(', ', $selectColumns) . ' FROM registrations WHERE event_id = ? AND user_id = ? ORDER BY reg_id DESC LIMIT 1 FOR UPDATE'
    );
    if ($stmt === false) {
        return 0;
    }
    $stmt->bind_param('ii', $eventId, $userId);
    $stmt->execute();
    $registration = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$registration || !isHomeInRejoinableRegistration($registration)) {
        return 0;
    }

    $regId = (int)($registration['reg_id'] ?? 0);
    if ($regId <= 0) {
        return 0;
    }

    cleanupRegistrationForRejoin($conn, $regId);

    $paymentRequired = $totalAmount > 0;
    $paymentStatus = $paymentRequired ? 'pending' : 'paid';
    $nextStatus = $paymentRequired ? 'pending' : 'approved';
    $paymentExpiresAt = (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->modify('+10 minutes')->format('Y-m-d H:i:s');

    $updates = [];
    $types = '';
    $params = [];

    if ($statusColumn !== null) {
        $updates[] = '`' . str_replace('`', '', $statusColumn) . '` = ?';
        $types .= 's';
        $params[] = $nextStatus;
    }
    if (badomenColumnExists($conn, 'registrations', 'ticket_code')) {
        $updates[] = 'ticket_code = ?';
        $types .= 's';
        $params[] = createUuidV4();
    }
    if (badomenColumnExists($conn, 'registrations', 'quantity')) {
        $updates[] = 'quantity = ?';
        $types .= 'i';
        $params[] = $quantity;
    }
    if (badomenColumnExists($conn, 'registrations', 'unit_price')) {
        $updates[] = 'unit_price = ?';
        $types .= 'd';
        $params[] = $unitPrice;
    }
    if (badomenColumnExists($conn, 'registrations', 'total_amount')) {
        $updates[] = 'total_amount = ?';
        $types .= 'd';
        $params[] = $totalAmount;
    }
    if (badomenColumnExists($conn, 'registrations', 'currency')) {
        $updates[] = 'currency = ?';
        $types .= 's';
        $params[] = $currency;
    }
    if (badomenColumnExists($conn, 'registrations', 'payment_status')) {
        $updates[] = 'payment_status = ?';
        $types .= 's';
        $params[] = $paymentStatus;
    }
    if (badomenColumnExists($conn, 'registrations', 'payment_expires_at')) {
        if ($paymentRequired) {
            $updates[] = 'payment_expires_at = ?';
            $types .= 's';
            $params[] = $paymentExpiresAt;
        } else {
            $updates[] = 'payment_expires_at = NULL';
        }
    }
    if (badomenColumnExists($conn, 'registrations', 'payment_method')) {
        $updates[] = 'payment_method = NULL';
    }
    if (badomenColumnExists($conn, 'registrations', 'payment_reference')) {
        $updates[] = 'payment_reference = NULL';
    }
    if (badomenColumnExists($conn, 'registrations', 'paid_at')) {
        $updates[] = $paymentRequired ? 'paid_at = NULL' : 'paid_at = NOW()';
    }
    if (badomenColumnExists($conn, 'registrations', 'checked_in')) {
        $updates[] = 'checked_in = NULL';
    }
    if (badomenColumnExists($conn, 'registrations', 'registered_at')) {
        $updates[] = 'registered_at = NOW()';
    }
    if (badomenColumnExists($conn, 'registrations', 'updated_at')) {
        $updates[] = 'updated_at = NOW()';
    }
    if (badomenColumnExists($conn, 'registrations', 'payment_updated_at')) {
        $updates[] = 'payment_updated_at = NOW()';
    }

    if (empty($updates)) {
        return 0;
    }

    $types .= 'i';
    $params[] = $regId;
    $stmt = $conn->prepare('UPDATE registrations SET ' . implode(', ', $updates) . ' WHERE reg_id = ?');
    if ($stmt === false) {
        return 0;
    }
    bindStmt($stmt, $types, $params);
    $stmt->execute();
    $ok = $stmt->affected_rows >= 0;
    $stmt->close();

    return $ok ? $regId : 0;
}

function cleanupRegistrationForRejoin(mysqli $conn, int $regId): void
{
    if ($regId <= 0) return;

    if (badomenTableExists($conn, 'event_seats')) {
        $stmt = $conn->prepare("UPDATE event_seats SET status = 'available', reg_id = NULL, locked_by_user_id = NULL, lock_expires_at = NULL, updated_at = CURRENT_TIMESTAMP WHERE reg_id = ? AND status IN ('reserved', 'paid', 'locked')");
        if ($stmt !== false) {
            $stmt->bind_param('i', $regId);
            $stmt->execute();
            $stmt->close();
        }
    }

    if (badomenTableExists($conn, 'registration_seats')) {
        $stmt = $conn->prepare('DELETE FROM registration_seats WHERE reg_id = ?');
        if ($stmt !== false) {
            $stmt->bind_param('i', $regId);
            $stmt->execute();
            $stmt->close();
        }
    }

    if (!badomenTableExists($conn, 'event_orders')) {
        return;
    }

    $stmt = $conn->prepare('SELECT order_id FROM event_orders WHERE reg_id = ?');
    if ($stmt === false) {
        return;
    }
    $stmt->bind_param('i', $regId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as $row) {
        $orderId = (int)($row['order_id'] ?? 0);
        if ($orderId <= 0) continue;

        if (badomenTableExists($conn, 'event_refund_requests')) {
            $deleteRefund = $conn->prepare('DELETE FROM event_refund_requests WHERE order_id = ?');
            if ($deleteRefund !== false) {
                $deleteRefund->bind_param('i', $orderId);
                $deleteRefund->execute();
                $deleteRefund->close();
            }
        }

        if (badomenTableExists($conn, 'event_payments')) {
            $deletePayment = $conn->prepare('DELETE FROM event_payments WHERE order_id = ?');
            if ($deletePayment !== false) {
                $deletePayment->bind_param('i', $orderId);
                $deletePayment->execute();
                $deletePayment->close();
            }
        }
    }

    $updates = ['payment_status = ?'];
    $types = 's';
    $params = ['pending'];
    if (badomenColumnExists($conn, 'event_orders', 'paid_at')) {
        $updates[] = 'paid_at = NULL';
    }
    if (badomenColumnExists($conn, 'event_orders', 'updated_at')) {
        $updates[] = 'updated_at = NOW()';
    }
    $types .= 'i';
    $params[] = $regId;

    $stmt = $conn->prepare('UPDATE event_orders SET ' . implode(', ', $updates) . ' WHERE reg_id = ?');
    if ($stmt !== false) {
        bindStmt($stmt, $types, $params);
        $stmt->execute();
        $stmt->close();
    }
}

function bindSeatsToRegistration(mysqli $conn, int $regId, array $seats): void
{
    if (!badomenTableExists($conn, 'registration_seats')) {
        return;
    }

    $stmt = $conn->prepare(
        'INSERT INTO registration_seats (reg_id, seat_id, zone_id, price, currency) VALUES (?, ?, ?, ?, ?)'
    );
    if ($stmt === false) {
        throw new RuntimeException('registration_seats_prepare_failed');
    }

    foreach ($seats as $seat) {
        $seatId = (int)$seat['seat_id'];
        $zoneId = (int)$seat['zone_id'];
        $price = (float)$seat['price'];
        $currency = (string)($seat['currency'] ?? 'THB');
        $stmt->bind_param('iiids', $regId, $seatId, $zoneId, $price, $currency);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('registration_seats_insert_failed');
        }
    }

    $stmt->close();
}

function markSeatsReserved(mysqli $conn, int $regId, int $userId, array $seatIds): void
{
    if (empty($seatIds)) return;

    $placeholders = implode(',', array_fill(0, count($seatIds), '?'));
    $types = 'ii' . str_repeat('i', count($seatIds));
    $params = array_merge([$regId, $userId], array_map('intval', $seatIds));

    $stmt = $conn->prepare(
        "UPDATE event_seats
         SET status = 'reserved', reg_id = ?, locked_by_user_id = ?, lock_expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE), updated_at = CURRENT_TIMESTAMP
         WHERE seat_id IN ($placeholders) AND status = 'available'"
    );
    if ($stmt === false) {
        throw new RuntimeException('seat_update_prepare_failed');
    }
    bindStmt($stmt, $types, $params);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected !== count($seatIds)) {
        throw new RuntimeException('seat_unavailable');
    }
}

function parseSelectedSeatIds(string $raw): array
{
    $ids = [];
    foreach (explode(',', $raw) as $part) {
        $id = (int)trim($part);
        if ($id > 0) $ids[$id] = $id;
    }
    return array_values($ids);
}


function buildReservationPaymentPayload(mysqli $conn, int $regId, string $message): array
{
    $payment = fetchRegistrationPaymentSummary($conn, $regId);
    if (!$payment) {
        return ['ok' => false, 'message' => 'บันทึกการจองแล้ว แต่ไม่สามารถอ่านข้อมูลชำระเงินได้'];
    }

    $amount = (float)($payment['total_amount'] ?? 0);
    $paymentStatus = (string)($payment['payment_status'] ?? ($amount > 0 ? 'pending' : 'paid'));
    syncMockPaymentLedger($conn, $payment, $paymentStatus, null);
    return [
        'ok' => true,
        'message' => $message,
        'registration_id' => (int)$regId,
        'event_id' => (int)($payment['event_id'] ?? 0),
        'amount' => $amount,
        'currency' => (string)($payment['currency'] ?? 'THB'),
        'payment_required' => $amount > 0 && $paymentStatus !== 'paid',
        'payment_status' => $paymentStatus,
        'payment_expires_at' => (string)($payment['payment_expires_at'] ?? ''),
        'server_now' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d H:i:s'),
        'ticket_code' => (string)($payment['ticket_code'] ?? ''),
    ];
}

function syncMockPaymentLedger(mysqli $conn, array $registration, string $status, ?string $method): void
{
    if (!badomenTableExists($conn, 'event_orders')) return;

    $regId = (int)($registration['reg_id'] ?? 0);
    $eventId = (int)($registration['event_id'] ?? 0);
    if ($regId <= 0 || $eventId <= 0) return;

    $userStmt = $conn->prepare('SELECT user_id FROM registrations WHERE reg_id = ? LIMIT 1');
    if ($userStmt === false) return;
    $userStmt->bind_param('i', $regId);
    $userStmt->execute();
    $userId = (int)($userStmt->get_result()->fetch_assoc()['user_id'] ?? 0);
    $userStmt->close();
    if ($userId <= 0) return;

    $amount = (float)($registration['total_amount'] ?? 0);
    $currency = (string)($registration['currency'] ?? 'THB');
    $orderStatus = $status === 'paid' ? 'paid' : ($status === 'refunded' ? 'refunded' : 'pending');
    $orderNumber = 'MOCK-' . date('YmdHis') . '-' . $regId;

    $stmt = $conn->prepare(
        "INSERT INTO event_orders
         (order_number, reg_id, user_id, event_id, base_price, final_amount, currency, payment_status, paid_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, IF(? = 'paid', NOW(), NULL))
         ON DUPLICATE KEY UPDATE
            base_price = VALUES(base_price),
            final_amount = VALUES(final_amount),
            currency = VALUES(currency),
            payment_status = VALUES(payment_status),
            paid_at = IF(VALUES(payment_status) = 'paid', COALESCE(paid_at, NOW()), NULL)"
    );
    if ($stmt === false) return;
    $stmt->bind_param('siiiddsss', $orderNumber, $regId, $userId, $eventId, $amount, $amount, $currency, $orderStatus, $orderStatus);
    $stmt->execute();
    $stmt->close();

    if ($orderStatus !== 'paid' || !badomenTableExists($conn, 'event_payments')) return;

    $stmt = $conn->prepare('SELECT order_id FROM event_orders WHERE reg_id = ? LIMIT 1');
    if ($stmt === false) return;
    $stmt->bind_param('i', $regId);
    $stmt->execute();
    $orderId = (int)($stmt->get_result()->fetch_assoc()['order_id'] ?? 0);
    $stmt->close();
    if ($orderId <= 0) return;

    $methodMap = [
        'promptpay' => 'promptpay',
        'visa' => 'credit_card',
        'mastercard' => 'credit_card',
        'truemoney' => 'wallet',
        'free' => 'other',
    ];
    $paymentMethod = $methodMap[$method ?? 'free'] ?? 'other';
    $reference = (string)($registration['payment_reference'] ?? ('MOCK-REG-' . $regId));
    $payload = json_encode(['mock' => true, 'source' => 'home_in'], JSON_UNESCAPED_SLASHES);

    $stmt = $conn->prepare(
        "INSERT INTO event_payments
         (order_id, user_id, amount, currency, method, transaction_ref, provider, provider_payload_json, status, paid_at)
         VALUES (?, ?, ?, ?, ?, ?, 'badomen_mock', ?, 'paid', NOW())
         ON DUPLICATE KEY UPDATE status = 'paid', paid_at = COALESCE(paid_at, NOW()), method = VALUES(method)"
    );
    if ($stmt === false) return;
    $stmt->bind_param('iidssss', $orderId, $userId, $amount, $currency, $paymentMethod, $reference, $payload);
    $stmt->execute();
    $stmt->close();
}

function fetchRegistrationPaymentSummary(mysqli $conn, int $regId): ?array
{
    $columns = ['reg_id', 'event_id'];
    foreach (['total_amount', 'currency', 'ticket_code', 'payment_status', 'payment_expires_at', 'payment_method', 'payment_reference', 'paid_at'] as $column) {
        if (badomenColumnExists($conn, 'registrations', $column)) {
            $columns[] = $column;
        }
    }
    $sql = 'SELECT ' . implode(', ', array_map(static fn($c) => '`' . $c . '`', $columns)) . ' FROM registrations WHERE reg_id = ? LIMIT 1';
    $stmt = $conn->prepare($sql);
    if ($stmt === false) return null;
    $stmt->bind_param('i', $regId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function handleMockPaymentComplete(int $userId, bool $wantsJson): void
{
    $regId = (int)($_POST['registration_id'] ?? 0);
    $method = strtolower(trim((string)($_POST['payment_method'] ?? 'promptpay')));
    $allowed = ['promptpay', 'visa', 'mastercard', 'truemoney'];
    if (!in_array($method, $allowed, true)) {
        $method = 'promptpay';
    }
    if ($regId <= 0) {
        homeInJsonResponse(['ok' => false, 'message' => 'ไม่พบรายการจอง'], 422);
    }

    $conn = getConnection();
    $conn->query("SET time_zone = '+07:00'");
    expireExpiredPaymentReservations($conn);

    try {
        $conn->begin_transaction();

        $statusColumn = detectRegistrationStatusColumn($conn);
        $paymentStatusExpr = badomenColumnExists($conn, 'registrations', 'payment_status') ? 'payment_status' : "'pending'";
        $paymentExpiresExpr = badomenColumnExists($conn, 'registrations', 'payment_expires_at') ? 'payment_expires_at' : 'NULL';
        $amountExpr = badomenColumnExists($conn, 'registrations', 'total_amount') ? 'total_amount' : '0';
        $currencyExpr = badomenColumnExists($conn, 'registrations', 'currency') ? 'currency' : "'THB'";
        $ticketCodeExpr = badomenColumnExists($conn, 'registrations', 'ticket_code') ? 'ticket_code' : "''";
        $statusExpr = $statusColumn ? '`' . $statusColumn . '`' : "'pending'";

        $stmt = $conn->prepare(
            "SELECT reg_id, event_id, user_id, $statusExpr AS registration_status, $paymentStatusExpr AS payment_status,
                    $paymentExpiresExpr AS payment_expires_at, $amountExpr AS total_amount, $currencyExpr AS currency,
                    $ticketCodeExpr AS ticket_code
             FROM registrations
             WHERE reg_id = ? AND user_id = ?
             LIMIT 1
             FOR UPDATE"
        );
        if ($stmt === false) {
            throw new RuntimeException('payment_lookup_failed');
        }
        $stmt->bind_param('ii', $regId, $userId);
        $stmt->execute();
        $registration = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$registration) {
            throw new RuntimeException('payment_not_found');
        }

        $expiresRaw = trim((string)($registration['payment_expires_at'] ?? ''));
        if ($expiresRaw !== '') {
            $expires = date_create_immutable($expiresRaw, new DateTimeZone('Asia/Bangkok'));
            $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok'));
            if ($expires instanceof DateTimeImmutable && $expires < $now) {
                throw new RuntimeException('payment_expired');
            }
        }

        $updates = [];
        $types = '';
        $params = [];
        if ($statusColumn) {
            $updates[] = '`' . $statusColumn . '` = ?';
            $types .= 's';
            $params[] = 'approved';
        }
        if (badomenColumnExists($conn, 'registrations', 'payment_status')) {
            $updates[] = 'payment_status = ?';
            $types .= 's';
            $params[] = 'paid';
        }
        if (badomenColumnExists($conn, 'registrations', 'payment_method')) {
            $updates[] = 'payment_method = ?';
            $types .= 's';
            $params[] = $method;
        }
        if (badomenColumnExists($conn, 'registrations', 'payment_reference')) {
            $updates[] = 'payment_reference = ?';
            $types .= 's';
            $params[] = 'MOCK-' . strtoupper(bin2hex(random_bytes(4)));
        }
        if (badomenColumnExists($conn, 'registrations', 'paid_at')) {
            $updates[] = 'paid_at = NOW()';
        }
        if (badomenColumnExists($conn, 'registrations', 'payment_updated_at')) {
            $updates[] = 'payment_updated_at = NOW()';
        }
        if (badomenColumnExists($conn, 'registrations', 'payment_expires_at')) {
            $updates[] = 'payment_expires_at = NULL';
        }
        if (!empty($updates)) {
            $types .= 'i';
            $params[] = $regId;
            $stmt = $conn->prepare('UPDATE registrations SET ' . implode(', ', $updates) . ' WHERE reg_id = ?');
            if ($stmt === false) {
                throw new RuntimeException('payment_update_failed');
            }
            bindStmt($stmt, $types, $params);
            $stmt->execute();
            $stmt->close();
        }

        if (badomenTableExists($conn, 'event_seats')) {
            $stmt = $conn->prepare("UPDATE event_seats SET status = 'paid', lock_expires_at = NULL, updated_at = CURRENT_TIMESTAMP WHERE reg_id = ? AND status IN ('reserved','available')");
            if ($stmt !== false) {
                $stmt->bind_param('i', $regId);
                $stmt->execute();
                $stmt->close();
            }
        }

        $conn->commit();
        $summary = fetchRegistrationPaymentSummary($conn, $regId) ?: [];
        syncMockPaymentLedger($conn, $summary + ['reg_id' => $regId], 'paid', $method);
        $conn->close();
        homeInJsonResponse([
            'ok' => true,
            'message' => 'ชำระเงินสำเร็จ ระบบออกบัตรให้แล้ว',
            'registration_id' => $regId,
            'payment_status' => 'paid',
            'method' => $method,
            'ticket_code' => (string)($summary['ticket_code'] ?? $registration['ticket_code'] ?? ''),
            'paid_at' => (string)($summary['paid_at'] ?? ''),
        ]);
    } catch (Throwable $exception) {
        @$conn->rollback();
        $conn->close();
        if ($exception->getMessage() === 'payment_not_found') {
            $message = 'ไม่พบรายการจองนี้ในบัญชีของคุณ';
        } elseif ($exception->getMessage() === 'payment_expired') {
            $message = 'หมดเวลาชำระเงินแล้ว กรุณาจองใหม่';
        } else {
            $message = 'ไม่สามารถยืนยันการชำระเงินได้ในขณะนี้';
        }
        homeInJsonResponse(['ok' => false, 'message' => $message], 422);
    }
}

function expireExpiredPaymentReservations(mysqli $conn): void
{
    if (!badomenColumnExists($conn, 'registrations', 'payment_status') || !badomenColumnExists($conn, 'registrations', 'payment_expires_at')) {
        return;
    }

    $stmt = $conn->prepare("SELECT reg_id FROM registrations WHERE payment_status = 'pending' AND payment_expires_at IS NOT NULL AND payment_expires_at < NOW() LIMIT 200");
    if ($stmt === false) return;
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    if (empty($rows)) return;

    $regIds = array_map(static fn($row) => (int)$row['reg_id'], $rows);
    $placeholders = implode(',', array_fill(0, count($regIds), '?'));
    $types = str_repeat('i', count($regIds));

    if (badomenTableExists($conn, 'event_orders')) {
        $stmt = $conn->prepare("UPDATE event_orders SET payment_status = 'cancelled' WHERE reg_id IN ($placeholders) AND payment_status = 'pending'");
        if ($stmt !== false) {
            bindStmt($stmt, $types, $regIds);
            $stmt->execute();
            $stmt->close();
        }
    }

    if (badomenTableExists($conn, 'event_seats')) {
        $sql = "UPDATE event_seats SET status = 'available', reg_id = NULL, locked_by_user_id = NULL, lock_expires_at = NULL, updated_at = CURRENT_TIMESTAMP WHERE reg_id IN ($placeholders) AND status = 'reserved'";
        $stmt = $conn->prepare($sql);
        if ($stmt !== false) {
            bindStmt($stmt, $types, $regIds);
            $stmt->execute();
            $stmt->close();
        }
    }

    if (badomenTableExists($conn, 'registration_seats')) {
        $stmt = $conn->prepare("DELETE FROM registration_seats WHERE reg_id IN ($placeholders)");
        if ($stmt !== false) {
            bindStmt($stmt, $types, $regIds);
            $stmt->execute();
            $stmt->close();
        }
    }

    $stmt = $conn->prepare("DELETE FROM registrations WHERE reg_id IN ($placeholders) AND payment_status = 'pending'");
    if ($stmt !== false) {
        bindStmt($stmt, $types, $regIds);
        $stmt->execute();
        $stmt->close();
    }
}

function duplicateMessageFromException(Throwable $exception): ?string
{
    return ((int)$exception->getCode() === 1062 || $exception->getMessage() === 'duplicate_registration')
        ? 'คุณมีคำขอเข้าร่วมที่ยังใช้งานอยู่แล้ว'
        : null;
}

function fetchOtherUserEvents(
    mysqli $conn,
    int $userId,
    string $query,
    string $startAt,
    string $endAt,
    bool $favoritesOnly = false
): array
{
    $hasPaymentStatusColumn = badomenColumnExists($conn, 'registrations', 'payment_status');
    $hasPaymentExpiresColumn = badomenColumnExists($conn, 'registrations', 'payment_expires_at');
    $hasPaymentMethodColumn = badomenColumnExists($conn, 'registrations', 'payment_method');
    $hasTotalAmountColumn = badomenColumnExists($conn, 'registrations', 'total_amount');
    $ownRegistrationIdSelect = "(SELECT r6.reg_id FROM registrations r6 WHERE r6.event_id = e.event_id AND r6.user_id = ? ORDER BY r6.reg_id DESC LIMIT 1)";
    $ownPaymentStatusSelect = $hasPaymentStatusColumn
        ? "(SELECT r7.payment_status FROM registrations r7 WHERE r7.event_id = e.event_id AND r7.user_id = ? ORDER BY r7.reg_id DESC LIMIT 1)"
        : "NULL";
    $ownPaymentExpiresSelect = $hasPaymentExpiresColumn
        ? "(SELECT r8.payment_expires_at FROM registrations r8 WHERE r8.event_id = e.event_id AND r8.user_id = ? ORDER BY r8.reg_id DESC LIMIT 1)"
        : "NULL";
    $ownPaymentMethodSelect = $hasPaymentMethodColumn
        ? "(SELECT r9.payment_method FROM registrations r9 WHERE r9.event_id = e.event_id AND r9.user_id = ? ORDER BY r9.reg_id DESC LIMIT 1)"
        : "NULL";
    $ownTotalAmountSelect = $hasTotalAmountColumn
        ? "(SELECT r10.total_amount FROM registrations r10 WHERE r10.event_id = e.event_id AND r10.user_id = ? ORDER BY r10.reg_id DESC LIMIT 1)"
        : "NULL";

    $baseSql = "
        SELECT
            e.event_id,
            e.title,
            e.description,
            e.location,
            e.event_start,
            e.event_end,
            e.reg_start,
            e.reg_end,
            e.max_participant,
            e.created_at,
            COALESCE(u.name, 'ไม่ทราบชื่อผู้สร้าง') AS creator_name,
            (
                SELECT COUNT(*)
                FROM registrations r
                WHERE r.event_id = e.event_id
                    AND r.status IN ('approved', 'checked_in')
            ) AS registered_count,
            (
                SELECT COUNT(*)
                FROM registrations rp
                WHERE rp.event_id = e.event_id
                    AND rp.status = 'pending'
            ) AS pending_count,
            (
                SELECT COUNT(*)
                FROM registrations ra
                WHERE ra.event_id = e.event_id
                    AND ra.status = 'approved'
            ) AS approved_count,
            (
                SELECT COUNT(*)
                FROM registrations rc
                WHERE rc.event_id = e.event_id
                    AND rc.status = 'checked_in'
            ) AS checked_in_count,
            (
                SELECT COUNT(*)
                FROM registrations rr
                WHERE rr.event_id = e.event_id
                    AND rr.status IN ('rejected', 'cancelled', 'canceled', 'refunded')
            ) AS rejected_count,
            (
                SELECT r3.status
                FROM registrations r3
                WHERE r3.event_id = e.event_id
                    AND r3.user_id = ?
                ORDER BY r3.reg_id DESC
                LIMIT 1
            ) AS own_registration_status,
            (
                SELECT r4.registered_at
                FROM registrations r4
                WHERE r4.event_id = e.event_id
                    AND r4.user_id = ?
                ORDER BY r4.reg_id DESC
                LIMIT 1
            ) AS own_registered_at,
            (
                SELECT r5.checked_in
                FROM registrations r5
                WHERE r5.event_id = e.event_id
                    AND r5.user_id = ?
                ORDER BY r5.reg_id DESC
                LIMIT 1
            ) AS own_checked_in,
            $ownRegistrationIdSelect AS own_registration_id,
            $ownPaymentStatusSelect AS own_payment_status,
            $ownPaymentExpiresSelect AS own_payment_expires_at,
            $ownPaymentMethodSelect AS own_payment_method,
            $ownTotalAmountSelect AS own_total_amount,
            (
                SELECT COUNT(*)
                FROM registrations r2
                WHERE r2.event_id = e.event_id
                  AND r2.user_id = ?
                  AND r2.status IN ('pending', 'approved', 'checked_in')
            ) AS already_requested,
            (
                SELECT image_path
                FROM event_images i
                WHERE i.event_id = e.event_id
                ORDER BY i.image_id ASC
                LIMIT 1
            ) AS cover_image,
            (
                SELECT GROUP_CONCAT(i2.image_path ORDER BY i2.image_id ASC SEPARATOR '|||')
                FROM event_images i2
                WHERE i2.event_id = e.event_id
            ) AS all_images
        FROM events e
        LEFT JOIN users u ON u.user_id = e.creator_id
        WHERE e.creator_id <> ?
    ";

    $hasFavoritesTable = badomenTableExists($conn, 'event_favorites');
    if ($favoritesOnly && !$hasFavoritesTable) {
        return [];
    }

    $sql = $baseSql;
    $types = 'iiii';
    $params = [$userId, $userId, $userId, $userId];
    if ($hasPaymentStatusColumn) {
        $types .= 'i';
        $params[] = $userId;
    }
    if ($hasPaymentExpiresColumn) {
        $types .= 'i';
        $params[] = $userId;
    }
    if ($hasPaymentMethodColumn) {
        $types .= 'i';
        $params[] = $userId;
    }
    if ($hasTotalAmountColumn) {
        $types .= 'i';
        $params[] = $userId;
    }
    $types .= 'ii';
    $params[] = $userId;
    $params[] = $userId;

    if ($query !== '') {
        $sql .= ' AND (e.title LIKE ? OR e.location LIKE ? OR e.description LIKE ?)';
        $like = '%' . $query . '%';
        $types .= 'sss';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    if ($startAt !== '') {
        $sql .= ' AND e.event_start >= ?';
        $types .= 's';
        $params[] = $startAt;
    }

    if ($endAt !== '') {
        $sql .= ' AND e.event_end <= ?';
        $types .= 's';
        $params[] = $endAt;
    }

    if ($favoritesOnly && $hasFavoritesTable) {
        $sql .= ' AND EXISTS (
            SELECT 1 FROM event_favorites ff
            WHERE ff.event_id = e.event_id AND ff.user_id = ?
        )';
        $types .= 'i';
        $params[] = $userId;
    }

    $sql .= ' ORDER BY e.event_start ASC';

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        return [];
    }

    bindStmt($stmt, $types, $params);
    $stmt->execute();

    $result = $stmt->get_result();
    $events = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    foreach ($events as &$event) {
        $event['images'] = [];
        $rawImages = (string)($event['all_images'] ?? '');
        if ($rawImages !== '') {
            foreach (explode('|||', $rawImages) as $img) {
                $img = trim((string)$img);
                if ($img !== '') $event['images'][] = $img;
            }
        }
        if (empty($event['images']) && !empty($event['cover_image'])) {
            $event['images'][] = (string)$event['cover_image'];
        }
        unset($event['all_images']);
    }
    unset($event);

    return $events;
}

function enrichEventDiscoveryData(mysqli $conn, array $events, int $userId): array
{
    if (empty($events)) return $events;

    $indexes = [];
    $eventIds = [];
    foreach ($events as $index => $event) {
        $eventId = (int)($event['event_id'] ?? 0);
        if ($eventId <= 0) continue;
        $indexes[$eventId] = $index;
        $eventIds[] = $eventId;
        $events[$index] += [
            'tags' => [],
            'is_favorite' => false,
            'favorite_count' => 0,
            'rank_score' => 0.0,
            'price' => null,
            'compare_at_price' => null,
            'currency' => 'THB',
            'sale_start' => null,
            'sale_end' => null,
            'ticket_mode' => 'general',
            'seat_selection_mode' => 'manual',
            'max_tickets_per_user' => 1,
            'hold_minutes' => 15,
            'ticket_zones' => [],
            'seat_map' => [],
        ];
    }
    if (empty($eventIds)) return $events;

    $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
    $idTypes = str_repeat('i', count($eventIds));

    $eventColumns = ['event_id'];
    foreach (['price', 'compare_at_price', 'currency', 'sale_start', 'sale_end', 'ticket_mode', 'seat_selection_mode', 'max_tickets_per_user', 'hold_minutes'] as $column) {
        if (badomenColumnExists($conn, 'events', $column)) {
            $eventColumns[] = $column;
        }
    }
    if (count($eventColumns) > 1) {
        $stmt = $conn->prepare('SELECT ' . implode(', ', array_map(static fn($c) => '`' . $c . '`', $eventColumns)) . " FROM events WHERE event_id IN ($placeholders)");
        if ($stmt !== false) {
            bindStmt($stmt, $idTypes, $eventIds);
            $stmt->execute();
            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                $index = $indexes[(int)$row['event_id']] ?? null;
                if ($index !== null) $events[$index] = array_replace($events[$index], $row);
            }
            $stmt->close();
        }
    }

    if (badomenTableExists($conn, 'event_favorites')) {
        $types = 'i' . $idTypes;
        $params = array_merge([$userId], $eventIds);
        $stmt = $conn->prepare(
            "SELECT e.event_id, COUNT(f.favorite_id) AS favorite_count,
                    MAX(CASE WHEN f.user_id = ? THEN 1 ELSE 0 END) AS is_favorite
             FROM events e
             LEFT JOIN event_favorites f ON f.event_id = e.event_id
             WHERE e.event_id IN ($placeholders)
             GROUP BY e.event_id"
        );
        if ($stmt !== false) {
            bindStmt($stmt, $types, $params);
            $stmt->execute();
            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                $index = $indexes[(int)$row['event_id']] ?? null;
                if ($index !== null) {
                    $events[$index]['favorite_count'] = (int)$row['favorite_count'];
                    $events[$index]['is_favorite'] = (int)$row['is_favorite'] === 1;
                }
            }
            $stmt->close();
        }
    }

    if (badomenTableExists($conn, 'event_tags') && badomenTableExists($conn, 'tags')) {
        $stmt = $conn->prepare(
            "SELECT et.event_id, t.slug, t.name_th, t.name_en
             FROM event_tags et JOIN tags t ON t.tag_id = et.tag_id
             WHERE et.event_id IN ($placeholders)
             ORDER BY t.usage_count DESC, t.name_th ASC"
        );
        if ($stmt !== false) {
            bindStmt($stmt, $idTypes, $eventIds);
            $stmt->execute();
            $locale = function_exists('currentLocale') ? currentLocale() : 'th';
            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                $index = $indexes[(int)$row['event_id']] ?? null;
                if ($index !== null && count($events[$index]['tags']) < 8) {
                    $events[$index]['tags'][] = [
                        'slug' => (string)$row['slug'],
                        'name' => $locale === 'en' ? (string)$row['name_en'] : (string)$row['name_th'],
                    ];
                }
            }
            $stmt->close();
        }
    }

    enrichTicketZonesAndSeats($conn, $events, $indexes, $eventIds, $placeholders, $idTypes);

    foreach ($events as &$event) {
        $event['max_tickets_per_user'] = max(1, min(2, (int)($event['max_tickets_per_user'] ?? 1)));
        $event['rank_score'] =
            ((int)($event['favorite_count'] ?? 0) * 3)
            + ((int)($event['approved_count'] ?? 0) * 2)
            + ((int)($event['checked_in_count'] ?? 0) * 4);
    }
    unset($event);

    usort($events, static function (array $left, array $right): int {
        $scoreCompare = (float)$right['rank_score'] <=> (float)$left['rank_score'];
        return $scoreCompare !== 0
            ? $scoreCompare
            : strcmp((string)$left['event_start'], (string)$right['event_start']);
    });

    return $events;
}

function enrichTicketZonesAndSeats(mysqli $conn, array &$events, array $indexes, array $eventIds, string $placeholders, string $idTypes): void
{
    if (!badomenTableExists($conn, 'event_ticket_zones')) return;

    $stmt = $conn->prepare(
        "SELECT z.event_id, z.zone_id, z.zone_code, z.zone_name, z.color_hex, z.price, z.currency,
                z.capacity, z.row_count, z.seats_per_row, z.sort_order,
                COALESCE(SUM(CASE WHEN s.status = 'available' THEN 1 ELSE 0 END), 0) AS available_count,
                COALESCE(SUM(CASE WHEN s.status IN ('reserved', 'paid') THEN 1 ELSE 0 END), 0) AS reserved_count,
                COALESCE(SUM(CASE WHEN s.status = 'blocked' THEN 1 ELSE 0 END), 0) AS blocked_count
         FROM event_ticket_zones z
         LEFT JOIN event_seats s ON s.zone_id = z.zone_id
         WHERE z.event_id IN ($placeholders) AND z.is_active = 1
         GROUP BY z.zone_id
         ORDER BY z.event_id ASC, z.sort_order ASC, z.zone_id ASC"
    );
    if ($stmt !== false) {
        bindStmt($stmt, $idTypes, $eventIds);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $index = $indexes[(int)$row['event_id']] ?? null;
            if ($index !== null) {
                $events[$index]['ticket_zones'][] = [
                    'zone_id' => (int)$row['zone_id'],
                    'zone_code' => (string)$row['zone_code'],
                    'zone_name' => (string)$row['zone_name'],
                    'color_hex' => (string)$row['color_hex'],
                    'price' => (float)$row['price'],
                    'currency' => (string)$row['currency'],
                    'capacity' => (int)$row['capacity'],
                    'row_count' => (int)$row['row_count'],
                    'seats_per_row' => (int)$row['seats_per_row'],
                    'available_count' => (int)$row['available_count'],
                    'reserved_count' => (int)$row['reserved_count'],
                    'blocked_count' => (int)$row['blocked_count'],
                    'sort_order' => (int)$row['sort_order'],
                ];
            }
        }
        $stmt->close();
    }

    if (!badomenTableExists($conn, 'event_seats')) return;

    $stmt = $conn->prepare(
        "SELECT s.event_id, s.seat_id, s.zone_id, s.row_label, s.row_sort, s.seat_number, s.seat_code, s.status
         FROM event_seats s
         JOIN event_ticket_zones z ON z.zone_id = s.zone_id
         WHERE s.event_id IN ($placeholders) AND z.is_active = 1
         ORDER BY z.sort_order ASC, s.row_sort ASC, s.seat_number ASC"
    );
    if ($stmt !== false) {
        bindStmt($stmt, $idTypes, $eventIds);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $index = $indexes[(int)$row['event_id']] ?? null;
            if ($index !== null) {
                $events[$index]['seat_map'][] = [
                    'seat_id' => (int)$row['seat_id'],
                    'zone_id' => (int)$row['zone_id'],
                    'row_label' => (string)$row['row_label'],
                    'row_sort' => (int)$row['row_sort'],
                    'seat_number' => (int)$row['seat_number'],
                    'seat_code' => (string)$row['seat_code'],
                    'status' => (string)$row['status'],
                ];
            }
        }
        $stmt->close();
    }
}

function parseDateTimeLocal(string $value): string
{
    if ($value === '') return '';
    $date = DateTime::createFromFormat('Y-m-d\TH:i', $value);
    return $date ? $date->format('Y-m-d H:i:s') : '';
}

function parseDbDateTime(string $value, DateTimeZone $tz, bool $isEnd = false): ?DateTimeImmutable
{
    $value = trim($value);
    if ($value === '') return null;

    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, $tz);
    if ($dt instanceof DateTimeImmutable) {
        return $dt->format('H:i:s') === '00:00:00'
            ? ($isEnd ? $dt->setTime(23, 59, 59) : $dt->setTime(0, 0, 0))
            : $dt;
    }

    $d = DateTimeImmutable::createFromFormat('Y-m-d', $value, $tz);
    if ($d instanceof DateTimeImmutable) {
        return $isEnd ? $d->setTime(23, 59, 59) : $d->setTime(0, 0, 0);
    }

    $fallback = date_create_immutable($value, $tz);
    return $fallback instanceof DateTimeImmutable ? $fallback : null;
}

function getEventForJoinRequest(mysqli $conn, int $eventId, int $userId): ?array
{
    $statusColumn = detectRegistrationStatusColumn($conn);
    $statusR = $statusColumn !== null ? 'r.`' . str_replace('`', '', $statusColumn) . '`' : "'pending'";
    $statusR2 = $statusColumn !== null ? 'r2.`' . str_replace('`', '', $statusColumn) . '`' : "'pending'";
    $statusR3 = $statusColumn !== null ? 'r3.`' . str_replace('`', '', $statusColumn) . '`' : "'pending'";
    $registeredFilter = $statusColumn !== null
        ? "$statusR IN ('approved', 'checked_in')"
        : '1 = 1';
    $activeRequestFilter = $statusColumn !== null
        ? "$statusR2 IN ('pending', 'approved', 'checked_in')"
        : '1 = 1';
    $paymentStatusSelect = badomenColumnExists($conn, 'registrations', 'payment_status')
        ? "(SELECT r4.payment_status FROM registrations r4 WHERE r4.event_id = e.event_id AND r4.user_id = ? ORDER BY r4.reg_id DESC LIMIT 1)"
        : "''";

    $stmt = $conn->prepare(
        "SELECT
            e.event_id,
            e.creator_id,
            e.title,
            e.reg_start,
            e.reg_end,
            e.max_participant,
            (
                SELECT COUNT(*)
                FROM registrations r
                WHERE r.event_id = e.event_id
                    AND $registeredFilter
            ) AS registered_count,
            (
                SELECT COUNT(*)
                FROM registrations r2
                WHERE r2.event_id = e.event_id
                    AND r2.user_id = ?
                    AND $activeRequestFilter
            ) AS already_requested,
            (
                SELECT $statusR3
                FROM registrations r3
                WHERE r3.event_id = e.event_id
                    AND r3.user_id = ?
                ORDER BY r3.reg_id DESC
                LIMIT 1
            ) AS own_registration_status,
            $paymentStatusSelect AS own_payment_status
        FROM events e
        WHERE e.event_id = ?
        LIMIT 1"
    );

    if ($stmt === false) return null;

    if (badomenColumnExists($conn, 'registrations', 'payment_status')) {
        $stmt->bind_param('iiii', $userId, $userId, $userId, $eventId);
    } else {
        $stmt->bind_param('iii', $userId, $userId, $eventId);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $event = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$event) return null;

    $event += [
        'price' => 0,
        'currency' => 'THB',
        'ticket_mode' => 'general',
        'seat_selection_mode' => 'manual',
        'max_tickets_per_user' => 1,
        'own_registration_status' => '',
        'own_payment_status' => '',
    ];

    $columns = ['event_id'];
    foreach (['price', 'currency', 'ticket_mode', 'seat_selection_mode', 'max_tickets_per_user'] as $column) {
        if (badomenColumnExists($conn, 'events', $column)) {
            $columns[] = $column;
        }
    }
    if (count($columns) > 1) {
        $stmt = $conn->prepare('SELECT ' . implode(', ', array_map(static fn($c) => '`' . $c . '`', $columns)) . ' FROM events WHERE event_id = ? LIMIT 1');
        if ($stmt !== false) {
            $stmt->bind_param('i', $eventId);
            $stmt->execute();
            $extra = $stmt->get_result()->fetch_assoc();
            if ($extra) $event = array_replace($event, $extra);
            $stmt->close();
        }
    }

    return $event;
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
    return is_array($messages) ? array_map('strval', $messages) : [];
}

function detectRegistrationStatusColumn(mysqli $conn): ?string
{
    foreach (['status', 'registration_status', 'approve_status'] as $column) {
        if (badomenColumnExists($conn, 'registrations', $column)) return $column;
    }
    return null;
}

function badomenTableExists(mysqli $conn, string $table): bool
{
    $stmt = $conn->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
    if ($stmt === false) return false;
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmt->close();
    return $exists;
}

function badomenColumnExists(mysqli $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1');
    if ($stmt === false) return false;
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmt->close();
    return $exists;
}

function bindStmt(mysqli_stmt $stmt, string $types, array $params): void
{
    $refs = [];
    foreach ($params as $key => $value) {
        $refs[$key] = $value;
    }
    $bindRefs = [];
    foreach ($refs as $key => &$value) {
        $bindRefs[$key] = &$value;
    }
    $stmt->bind_param($types, ...$bindRefs);
}

function createUuidV4(): string
{
    try {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    } catch (Throwable $exception) {
        return uniqid('ticket-', true);
    }
}
