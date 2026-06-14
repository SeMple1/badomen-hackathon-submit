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

    renderCreateActivity([], [], false);
}

function post(): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . appUrl('/login'));
        exit;
    }

    $old = collectActivityOldInput($_POST);
    $creatorId = (int)$_SESSION['user_id'];

    $stageResult = stageEventImages($_FILES['images'] ?? null, $creatorId);
    $old['staged_images'] = mergeStagedImages($old['staged_images'], $stageResult['images']);

    $ticketMode = normalizeTicketMode($old['ticket_mode']);
    $old['ticket_mode'] = $ticketMode;
    $old['seat_selection_mode'] = deriveSeatSelectionMode($ticketMode);

    $errors = validateActivityInput($old);
    if (!empty($stageResult['errors'])) {
        $errors = array_merge($errors, $stageResult['errors']);
    }

    $ticketZones = parseTicketZonesJson($old['ticket_zones_json']);
    $zoneCapacityTotal = calculateZoneCapacity($ticketZones);

    if ($ticketMode !== 'general') {
        if (empty($ticketZones)) {
            $errors[] = 'กรุณาเพิ่มโซนอย่างน้อย 1 โซน หรือเปลี่ยนรูปแบบบัตรเป็นสมัครทั่วไป';
        }
        if ($zoneCapacityTotal <= 0) {
            $errors[] = 'จำนวนที่นั่งรวมของโซนต้องมากกว่า 0';
        }
        if ($zoneCapacityTotal > 0) {
            $old['max_participant'] = (string)$zoneCapacityTotal;
        }
    }

    $tz = new DateTimeZone('Asia/Bangkok');
    $dates = parseActivityDates($old, $tz, $errors);

    if (!empty($errors)) {
        renderCreateActivity($old, $errors, false);
        return;
    }

    $maxParticipant = (int)$old['max_participant'];
    $eventStart = $dates['event_start']->format('Y-m-d H:i:s');
    $eventEnd = $dates['event_end']->format('Y-m-d H:i:s');
    $regStart = $dates['reg_start']->setTime(0, 0, 0)->format('Y-m-d H:i:s');
    $regEnd = $dates['reg_end']->setTime(23, 59, 59)->format('Y-m-d H:i:s');

    $conn = getConnection();
    $conn->query("SET time_zone = '+07:00'");

    $schemaErrors = validateTicketingSchemaForCreate($conn, $ticketMode);
    if (!empty($schemaErrors)) {
        $conn->close();
        renderCreateActivity($old, $schemaErrors, false);
        return;
    }

    $eventId = 0;
    $uploadedPaths = [];

    try {
        if (!$conn->begin_transaction()) {
            throw new RuntimeException('ไม่สามารถเริ่มบันทึกข้อมูลกิจกรรมได้');
        }

        $eventId = insertEventRecord($conn, [
            'creator_id' => $creatorId,
            'title' => $old['title'],
            'description' => $old['description'],
            'location' => $old['location'],
            'latitude' => $old['latitude'],
            'longitude' => $old['longitude'],
            'event_start' => $eventStart,
            'event_end' => $eventEnd,
            'reg_start' => $regStart,
            'reg_end' => $regEnd,
            'max_participant' => $maxParticipant,
            'price' => max(0, (float)$old['price']),
            'compare_at_price' => $old['compare_at_price'] !== '' ? max(0, (float)$old['compare_at_price']) : null,
            'currency' => 'THB',
            'ticket_mode' => $ticketMode,
            'seat_selection_mode' => deriveSeatSelectionMode($ticketMode),
            'max_tickets_per_user' => max(1, min(2, (int)$old['max_tickets_per_user'])),
            'hold_minutes' => 15,
        ]);

        if (function_exists('replaceEventTags')) {
            replaceEventTags($conn, $eventId, $old['tags']);
        }

        if ($ticketMode !== 'general') {
            saveTicketZonesAndSeats($conn, $eventId, $ticketZones);
        }

        $imageResult = commitStagedImages($conn, $eventId, $old['staged_images']);
        $uploadedPaths = $imageResult['paths'];
        if (!empty($imageResult['errors'])) {
            throw new RuntimeException(implode(' / ', $imageResult['errors']));
        }

        if (!$conn->commit()) {
            throw new RuntimeException('ไม่สามารถยืนยันการบันทึกกิจกรรมได้');
        }
    } catch (Throwable $exception) {
        if ($conn->errno === 0) {
            @$conn->rollback();
        } else {
            @$conn->rollback();
        }

        foreach ($uploadedPaths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        $conn->close();
        renderCreateActivity($old, ['เกิดข้อผิดพลาดระหว่างบันทึกกิจกรรม: ' . $exception->getMessage()], false);
        return;
    }

    $conn->close();
    renderCreateActivity([], [], true);
}

function collectActivityOldInput(array $post): array
{
    return [
        'title' => trim((string)($post['title'] ?? '')),
        'description' => trim((string)($post['description'] ?? '')),
        'location' => trim((string)($post['location'] ?? '')),
        'latitude' => trim((string)($post['latitude'] ?? '')),
        'longitude' => trim((string)($post['longitude'] ?? '')),
        'event_start' => trim((string)($post['event_start'] ?? '')),
        'event_end' => trim((string)($post['event_end'] ?? '')),
        'reg_start' => trim((string)($post['reg_start'] ?? '')),
        'reg_end' => trim((string)($post['reg_end'] ?? '')),
        'max_participant' => trim((string)($post['max_participant'] ?? '')),
        'price' => trim((string)($post['price'] ?? '0')),
        'compare_at_price' => trim((string)($post['compare_at_price'] ?? '')),
        'tags' => trim((string)($post['tags'] ?? '')),
        'ticket_mode' => trim((string)($post['ticket_mode'] ?? 'general')),
        'seat_selection_mode' => trim((string)($post['seat_selection_mode'] ?? 'manual')),
        'max_tickets_per_user' => trim((string)($post['max_tickets_per_user'] ?? '2')),
        'ticket_zones_json' => trim((string)($post['ticket_zones_json'] ?? '')),
        'staged_images' => normalizeStagedImagesInput($post['staged_images'] ?? []),
    ];
}

function validateActivityInput(array $old): array
{
    $errors = [];

    if ($old['title'] === '') $errors[] = 'กรุณากรอกชื่อกิจกรรม';
    if ($old['description'] === '') $errors[] = 'กรุณากรอกรายละเอียดกิจกรรม';
    if ($old['location'] === '') $errors[] = 'กรุณากรอกสถานที่';
    if (($old['latitude'] === '') !== ($old['longitude'] === '')) {
        $errors[] = 'กรุณาปักพิกัดให้ครบทั้งละติจูดและลองจิจูด';
    }
    if ($old['latitude'] !== '' && (
        !is_numeric($old['latitude']) || !is_numeric($old['longitude'])
        || (float)$old['latitude'] < -90 || (float)$old['latitude'] > 90
        || (float)$old['longitude'] < -180 || (float)$old['longitude'] > 180
    )) {
        $errors[] = 'พิกัดสถานที่ไม่ถูกต้อง';
    }

    if (!ctype_digit($old['max_participant']) || (int)$old['max_participant'] <= 0) {
        $errors[] = 'จำนวนผู้เข้าร่วมสูงสุดต้องเป็นตัวเลขมากกว่า 0';
    }

    if ($old['price'] === '' || !is_numeric($old['price']) || (float)$old['price'] < 0) {
        $errors[] = 'ราคาต้องเป็นตัวเลขตั้งแต่ 0 ขึ้นไป';
    }

    if ($old['compare_at_price'] !== '' && (!is_numeric($old['compare_at_price']) || (float)$old['compare_at_price'] <= (float)$old['price'])) {
        $errors[] = 'ราคาก่อนลดต้องมากกว่าราคาขาย';
    }

    if (!in_array(normalizeTicketMode($old['ticket_mode']), ['general', 'zone', 'seating'], true)) {
        $errors[] = 'รูปแบบบัตรไม่ถูกต้อง';
    }

    if (!ctype_digit($old['max_tickets_per_user']) || (int)$old['max_tickets_per_user'] < 1 || (int)$old['max_tickets_per_user'] > 2) {
        $errors[] = 'จำนวนบัตรต่อคนต้องอยู่ระหว่าง 1 ถึง 2';
    }

    return $errors;
}

function parseActivityDates(array $old, DateTimeZone $tz, array &$errors): array
{
    $eventStartDate = DateTime::createFromFormat('!Y-m-d\TH:i', $old['event_start'], $tz);
    $eventEndDate = DateTime::createFromFormat('!Y-m-d\TH:i', $old['event_end'], $tz);
    $regStartDate = DateTime::createFromFormat('!Y-m-d', $old['reg_start'], $tz);
    $regEndDate = DateTime::createFromFormat('!Y-m-d', $old['reg_end'], $tz);

    if (!$eventStartDate) $errors[] = 'กรุณาระบุวันเวลาเริ่มกิจกรรมให้ถูกต้อง';
    if (!$eventEndDate) $errors[] = 'กรุณาระบุวันเวลาสิ้นสุดกิจกรรมให้ถูกต้อง';
    if (!$regStartDate) $errors[] = 'กรุณาระบุวันเปิดรับสมัครให้ถูกต้อง';
    if (!$regEndDate) $errors[] = 'กรุณาระบุวันปิดรับสมัครให้ถูกต้อง';

    $today = new DateTime('today', $tz);
    $eventStartDay = null;
    $registrationMaxDay = null;

    if ($eventStartDate && $eventEndDate && $eventEndDate <= $eventStartDate) {
        $errors[] = 'วันเวลาสิ้นสุดกิจกรรมต้องมากกว่าวันเวลาเริ่มกิจกรรม';
    }

    if ($eventStartDate) {
        $eventStartDay = (clone $eventStartDate)->setTime(0, 0, 0);
        $registrationMaxDay = (clone $eventStartDay)->modify('-1 day');
        if ($eventStartDay < $today) {
            $errors[] = 'วันเริ่มกิจกรรมต้องไม่ก่อนวันปัจจุบัน';
        }
    }

    if ($regStartDate && $regStartDate < $today) {
        $errors[] = 'วันเปิดรับสมัครต้องไม่น้อยกว่าวันปัจจุบันจาก server';
    }
    if ($regEndDate && $regEndDate < $today) {
        $errors[] = 'วันปิดรับสมัครต้องไม่น้อยกว่าวันปัจจุบันจาก server';
    }
    if ($regStartDate && $regEndDate && $regEndDate < $regStartDate) {
        $errors[] = 'วันปิดรับสมัครต้องไม่ก่อนวันเปิดรับสมัคร';
    }
    if ($registrationMaxDay && $regStartDate && $regStartDate > $registrationMaxDay) {
        $errors[] = 'วันเปิดรับสมัครต้องก่อนวันเริ่มกิจกรรมอย่างน้อย 1 วัน';
    }
    if ($registrationMaxDay && $regEndDate && $regEndDate > $registrationMaxDay) {
        $errors[] = 'วันปิดรับสมัครต้องก่อนวันเริ่มกิจกรรมอย่างน้อย 1 วัน';
    }
    if ($registrationMaxDay && $registrationMaxDay < $today) {
        $errors[] = 'วันเริ่มกิจกรรมต้องห่างจากวันปัจจุบันอย่างน้อย 1 วัน เพื่อให้มีช่วงเปิดรับสมัคร';
    }

    return [
        'event_start' => $eventStartDate,
        'event_end' => $eventEndDate,
        'reg_start' => $regStartDate,
        'reg_end' => $regEndDate,
    ];
}

function validateTicketingSchemaForCreate(mysqli $conn, string $ticketMode): array
{
    if ($ticketMode === 'general') {
        return [];
    }

    $errors = [];
    foreach (['ticket_mode', 'seat_selection_mode', 'max_tickets_per_user', 'hold_minutes'] as $column) {
        if (!badomenColumnExists($conn, 'events', $column)) {
            $errors[] = 'ตาราง events ยังไม่มีคอลัมน์ระบบบัตร กรุณารัน database/migrations/20260613_ticketing_platform.sql';
            break;
        }
    }

    foreach (['event_ticket_zones', 'event_seats', 'registration_seats'] as $table) {
        if (!badomenTableExists($conn, $table)) {
            $errors[] = 'ยังไม่มีตาราง ' . $table . ' กรุณารัน database/migrations/20260613_ticketing_platform.sql';
        }
    }

    return array_values(array_unique($errors));
}

function insertEventRecord(mysqli $conn, array $data): int
{
    $columns = ['creator_id', 'title', 'description', 'location', 'event_start', 'event_end', 'reg_start', 'reg_end', 'max_participant'];
    $types = 'isssssssi';
    $params = [
        (int)$data['creator_id'],
        (string)$data['title'],
        (string)$data['description'],
        (string)$data['location'],
        (string)$data['event_start'],
        (string)$data['event_end'],
        (string)$data['reg_start'],
        (string)$data['reg_end'],
        (int)$data['max_participant'],
    ];

    if (badomenColumnExists($conn, 'events', 'price')) {
        $columns[] = 'price';
        $types .= 'd';
        $params[] = (float)$data['price'];
    }
    if (badomenColumnExists($conn, 'events', 'compare_at_price')) {
        $columns[] = 'compare_at_price';
        $types .= 'd';
        $params[] = $data['compare_at_price'] === null ? 0.0 : (float)$data['compare_at_price'];
    }
    if (badomenColumnExists($conn, 'events', 'currency')) {
        $columns[] = 'currency';
        $types .= 's';
        $params[] = (string)$data['currency'];
    }
    if ($data['latitude'] !== '' && $data['longitude'] !== '' && badomenColumnExists($conn, 'events', 'latitude')) {
        $columns[] = 'latitude';
        $types .= 'd';
        $params[] = (float)$data['latitude'];
    }
    if ($data['latitude'] !== '' && $data['longitude'] !== '' && badomenColumnExists($conn, 'events', 'longitude')) {
        $columns[] = 'longitude';
        $types .= 'd';
        $params[] = (float)$data['longitude'];
    }

    foreach (['ticket_mode' => 's', 'seat_selection_mode' => 's', 'max_tickets_per_user' => 'i', 'hold_minutes' => 'i'] as $column => $type) {
        if (badomenColumnExists($conn, 'events', $column)) {
            $columns[] = $column;
            $types .= $type;
            $params[] = $type === 'i' ? (int)$data[$column] : (string)$data[$column];
        }
    }

    $quoted = array_map(static fn(string $col): string => '`' . str_replace('`', '', $col) . '`', $columns);
    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $stmt = $conn->prepare('INSERT INTO events (' . implode(',', $quoted) . ') VALUES (' . $placeholders . ')');
    if ($stmt === false) {
        throw new RuntimeException('ไม่สามารถเตรียมคำสั่งบันทึกกิจกรรมได้');
    }

    bindStmt($stmt, $types, $params);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException($error ?: 'ไม่สามารถบันทึกกิจกรรมได้');
    }

    $eventId = (int)$conn->insert_id;
    $stmt->close();
    return $eventId;
}

function parseTicketZonesJson(string $raw): array
{
    if ($raw === '') return [];
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) return [];

    $zones = [];
    $seen = [];
    foreach ($decoded as $index => $zone) {
        if (!is_array($zone)) continue;

        $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9_-]/', '', (string)($zone['zone_code'] ?? '')), 0, 12));
        $name = trim(substr((string)($zone['zone_name'] ?? $code), 0, 80));
        $price = max(0, (float)($zone['price'] ?? 0));
        $color = (string)($zone['color_hex'] ?? '#38bdf8');
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#38bdf8';
        $rows = max(1, min(26, (int)($zone['row_count'] ?? 1)));
        $seats = max(1, min(60, (int)($zone['seats_per_row'] ?? 1)));

        if ($code === '' || $name === '' || isset($seen[$code])) continue;
        $seen[$code] = true;
        $zones[] = [
            'zone_code' => $code,
            'zone_name' => $name,
            'price' => $price,
            'color_hex' => $color,
            'row_count' => $rows,
            'seats_per_row' => $seats,
            'capacity' => $rows * $seats,
            'sort_order' => $index + 1,
        ];
    }

    return $zones;
}

function calculateZoneCapacity(array $zones): int
{
    $total = 0;
    foreach ($zones as $zone) {
        $total += (int)($zone['capacity'] ?? 0);
    }
    return $total;
}

function saveTicketZonesAndSeats(mysqli $conn, int $eventId, array $zones): void
{
    $zoneStmt = $conn->prepare(
        'INSERT INTO event_ticket_zones
         (event_id, zone_code, zone_name, color_hex, price, currency, capacity, row_count, seats_per_row, sort_order, is_active)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)'
    );
    if ($zoneStmt === false) {
        throw new RuntimeException('ไม่สามารถเตรียมคำสั่งบันทึกโซนได้');
    }

    $seatStmt = $conn->prepare(
        'INSERT INTO event_seats
         (event_id, zone_id, row_label, row_sort, seat_number, seat_code, status)
         VALUES (?, ?, ?, ?, ?, ?, \'available\')'
    );
    if ($seatStmt === false) {
        $zoneStmt->close();
        throw new RuntimeException('ไม่สามารถเตรียมคำสั่งสร้างที่นั่งได้');
    }

    foreach ($zones as $zone) {
        $code = (string)$zone['zone_code'];
        $name = (string)$zone['zone_name'];
        $color = (string)$zone['color_hex'];
        $price = (float)$zone['price'];
        $currency = 'THB';
        $capacity = (int)$zone['capacity'];
        $rows = (int)$zone['row_count'];
        $seatsPerRow = (int)$zone['seats_per_row'];
        $sortOrder = (int)$zone['sort_order'];

        $zoneStmt->bind_param('isssdsiiii', $eventId, $code, $name, $color, $price, $currency, $capacity, $rows, $seatsPerRow, $sortOrder);
        if (!$zoneStmt->execute()) {
            $seatStmt->close();
            $zoneStmt->close();
            throw new RuntimeException('ไม่สามารถบันทึกโซน ' . $code . ' ได้');
        }

        $zoneId = (int)$conn->insert_id;
        for ($row = 1; $row <= $rows; $row++) {
            $rowLabel = rowLabelFromNumber($row);
            for ($seatNo = 1; $seatNo <= $seatsPerRow; $seatNo++) {
                $seatCode = $code . '-' . $rowLabel . str_pad((string)$seatNo, 2, '0', STR_PAD_LEFT);
                $seatStmt->bind_param('iisiis', $eventId, $zoneId, $rowLabel, $row, $seatNo, $seatCode);
                if (!$seatStmt->execute()) {
                    $seatStmt->close();
                    $zoneStmt->close();
                    throw new RuntimeException('ไม่สามารถสร้างที่นั่ง ' . $seatCode . ' ได้');
                }
            }
        }
    }

    $seatStmt->close();
    $zoneStmt->close();
}

function rowLabelFromNumber(int $number): string
{
    $number = max(1, min(26, $number));
    return chr(64 + $number);
}

function normalizeTicketMode(string $mode): string
{
    return in_array($mode, ['general', 'zone', 'seating'], true) ? $mode : 'general';
}

function deriveSeatSelectionMode(string $ticketMode): string
{
    return $ticketMode === 'zone' ? 'random' : 'manual';
}

function renderCreateActivity(array $old, array $errors, bool $success): void
{
    renderView('create_activity', [
        'title' => 'Create activity',
        'old' => $old,
        'errors' => $errors,
        'success' => $success,
    ]);
}

function saveEventImages(mysqli $conn, int $eventId, $images): array
{
    if (!is_array($images) || !isset($images['name']) || !is_array($images['name'])) {
        return ['errors' => [], 'paths' => []];
    }

    $errors = [];
    $paths = [];
    $uploadRoot = __DIR__ . '/../uploads/events';
    $dbBasePath = '/uploads/events';

    if (!is_dir($uploadRoot) && !mkdir($uploadRoot, 0777, true) && !is_dir($uploadRoot)) {
        return ['errors' => ['ไม่สามารถสร้างโฟลเดอร์สำหรับอัปโหลดรูปได้'], 'paths' => []];
    }

    $insertImageStmt = $conn->prepare('INSERT INTO event_images (event_id, image_path) VALUES (?, ?)');
    if ($insertImageStmt === false) {
        return ['errors' => ['ไม่สามารถบันทึกรูปกิจกรรมลงฐานข้อมูลได้'], 'paths' => []];
    }

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    $maxBytes = 5 * 1024 * 1024;

    foreach ($images['name'] as $index => $originalName) {
        $errorCode = (int)($images['error'][$index] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode === UPLOAD_ERR_NO_FILE) continue;

        if ($errorCode !== UPLOAD_ERR_OK) {
            $errors[] = 'มีไฟล์รูปบางรายการอัปโหลดไม่สำเร็จ';
            continue;
        }

        $tmpName = (string)($images['tmp_name'][$index] ?? '');
        $size = (int)($images['size'][$index] ?? 0);
        $extension = strtolower(pathinfo((string)$originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, $allowedExtensions, true)) {
            $errors[] = 'รองรับเฉพาะไฟล์รูปประเภท JPG, PNG, WEBP';
            continue;
        }
        if ($size <= 0 || $size > $maxBytes) {
            $errors[] = 'ไฟล์รูปต้องมีขนาดไม่เกิน 5MB';
            continue;
        }
        if (!is_uploaded_file($tmpName)) {
            $errors[] = 'พบไฟล์รูปไม่ถูกต้อง';
            continue;
        }

        try {
            $randomPart = bin2hex(random_bytes(8));
        } catch (Throwable $exception) {
            $randomPart = (string)mt_rand(10000000, 99999999);
        }

        $fileName = sprintf('%d_%s.%s', $eventId, $randomPart, $extension);
        $absolutePath = $uploadRoot . '/' . $fileName;
        $dbPath = $dbBasePath . '/' . $fileName;

        if (!move_uploaded_file($tmpName, $absolutePath)) {
            $errors[] = 'ไม่สามารถบันทึกไฟล์รูปลงเซิร์ฟเวอร์ได้';
            continue;
        }
        $paths[] = $absolutePath;

        $insertImageStmt->bind_param('is', $eventId, $dbPath);
        if (!$insertImageStmt->execute()) {
            $errors[] = 'ไม่สามารถบันทึกพาธรูปลงฐานข้อมูลได้';
        }
    }

    $insertImageStmt->close();
    return ['errors' => array_values(array_unique($errors)), 'paths' => $paths];
}


function normalizeStagedImagesInput($raw): array
{
    if (!is_array($raw)) {
        return [];
    }

    $images = [];
    foreach ($raw as $path) {
        if (!is_scalar($path)) {
            continue;
        }

        $path = '/' . ltrim(str_replace('\\', '/', trim((string)$path)), '/');
        if (!str_starts_with($path, '/uploads/events/_draft/')) {
            continue;
        }

        $basename = basename($path);
        if ($basename === '' || !preg_match('/^[a-zA-Z0-9._-]+\.(jpg|jpeg|png|webp)$/i', $basename)) {
            continue;
        }

        $absolutePath = dirname(__DIR__) . $path;
        if (is_file($absolutePath)) {
            $images[$path] = true;
        }
    }

    return array_keys($images);
}

function mergeStagedImages(array $current, array $incoming): array
{
    $merged = [];
    foreach (array_merge($current, $incoming) as $path) {
        if (!is_string($path) || $path === '') {
            continue;
        }
        $merged[$path] = true;
    }

    return array_slice(array_keys($merged), 0, 12);
}

function stageEventImages($images, int $userId): array
{
    if (!is_array($images) || !isset($images['name']) || !is_array($images['name'])) {
        return ['errors' => [], 'images' => []];
    }

    $errors = [];
    $paths = [];
    $uploadRoot = dirname(__DIR__) . '/uploads/events/_draft';
    $dbBasePath = '/uploads/events/_draft';

    if (!is_dir($uploadRoot) && !mkdir($uploadRoot, 0777, true) && !is_dir($uploadRoot)) {
        return ['errors' => ['ไม่สามารถสร้างโฟลเดอร์พักรูปภาพได้'], 'images' => []];
    }

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    $maxBytes = 5 * 1024 * 1024;

    foreach ($images['name'] as $index => $originalName) {
        $errorCode = (int)($images['error'][$index] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($errorCode !== UPLOAD_ERR_OK) {
            $errors[] = 'มีไฟล์รูปบางรายการอัปโหลดไม่สำเร็จ';
            continue;
        }

        $tmpName = (string)($images['tmp_name'][$index] ?? '');
        $size = (int)($images['size'][$index] ?? 0);
        $extension = strtolower(pathinfo((string)$originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, $allowedExtensions, true)) {
            $errors[] = 'รองรับเฉพาะไฟล์รูปประเภท JPG, PNG, WEBP';
            continue;
        }

        if ($size <= 0 || $size > $maxBytes) {
            $errors[] = 'ไฟล์รูปต้องมีขนาดไม่เกิน 5MB';
            continue;
        }

        if (!is_uploaded_file($tmpName)) {
            $errors[] = 'พบไฟล์รูปไม่ถูกต้อง';
            continue;
        }

        try {
            $randomPart = bin2hex(random_bytes(10));
        } catch (Throwable $exception) {
            $randomPart = (string)mt_rand(10000000, 99999999);
        }

        $fileName = sprintf('u%d_%s.%s', $userId, $randomPart, $extension);
        $absolutePath = $uploadRoot . '/' . $fileName;
        $dbPath = $dbBasePath . '/' . $fileName;

        if (!move_uploaded_file($tmpName, $absolutePath)) {
            $errors[] = 'ไม่สามารถพักไฟล์รูปลงเซิร์ฟเวอร์ได้';
            continue;
        }

        $paths[] = $dbPath;
    }

    return ['errors' => array_values(array_unique($errors)), 'images' => $paths];
}

function commitStagedImages(mysqli $conn, int $eventId, array $stagedImages): array
{
    if (empty($stagedImages)) {
        return ['errors' => [], 'paths' => []];
    }

    $errors = [];
    $paths = [];
    $uploadRoot = dirname(__DIR__) . '/uploads/events';
    $dbBasePath = '/uploads/events';

    if (!is_dir($uploadRoot) && !mkdir($uploadRoot, 0777, true) && !is_dir($uploadRoot)) {
        return ['errors' => ['ไม่สามารถสร้างโฟลเดอร์สำหรับอัปโหลดรูปได้'], 'paths' => []];
    }

    $insertImageStmt = $conn->prepare('INSERT INTO event_images (event_id, image_path) VALUES (?, ?)');
    if ($insertImageStmt === false) {
        return ['errors' => ['ไม่สามารถบันทึกรูปกิจกรรมลงฐานข้อมูลได้'], 'paths' => []];
    }

    foreach (normalizeStagedImagesInput($stagedImages) as $draftPath) {
        $sourcePath = dirname(__DIR__) . $draftPath;
        if (!is_file($sourcePath)) {
            continue;
        }

        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        try {
            $randomPart = bin2hex(random_bytes(8));
        } catch (Throwable $exception) {
            $randomPart = (string)mt_rand(10000000, 99999999);
        }

        $fileName = sprintf('%d_%s.%s', $eventId, $randomPart, $extension);
        $targetPath = $uploadRoot . '/' . $fileName;
        $dbPath = $dbBasePath . '/' . $fileName;

        if (!@rename($sourcePath, $targetPath)) {
            if (!@copy($sourcePath, $targetPath)) {
                $errors[] = 'ไม่สามารถย้ายรูปจากพื้นที่พักไปยังโฟลเดอร์กิจกรรมได้';
                continue;
            }
            @unlink($sourcePath);
        }

        $paths[] = $targetPath;
        $insertImageStmt->bind_param('is', $eventId, $dbPath);
        if (!$insertImageStmt->execute()) {
            @unlink($targetPath);
            $errors[] = 'ไม่สามารถบันทึกพาธรูปลงฐานข้อมูลได้';
        }
    }

    $insertImageStmt->close();
    return ['errors' => array_values(array_unique($errors)), 'paths' => $paths];
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
