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
    /*
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . appUrl('/login'));
        exit;
    }
    */

    $eventId = getEventIdFromRequest();
    if ($eventId === null) {
        notFound();
    }

    $conn = getConnection();
    $userId = (int)$_SESSION['user_id'];
    $event = findOwnedEvent($conn, $eventId, $userId);

    if (!$event) {
        $conn->close();
        notFound();
    }

    $images = getEventImages($conn, $eventId);
    $supportsCommerce = editingActivityColumnExists($conn, 'events', 'price')
        && editingActivityColumnExists($conn, 'events', 'compare_at_price');
    $supportsTags = editingActivityTableExists($conn, 'tags')
        && editingActivityTableExists($conn, 'event_tags')
        && editingActivityColumnExists($conn, 'tags', 'slug')
        && editingActivityColumnExists($conn, 'tags', 'name_th')
        && editingActivityColumnExists($conn, 'tags', 'name_en');
    $old = eventToOldInput($event);

    if ($supportsTags) {
        $old['tags'] = getEventTagsText($conn, $eventId);
    }

    $conn->close();

    renderEditingActivity([
        'title' => 'Edit activity',
        'event_id' => $eventId,
        'old' => $old,
        'existing_images' => $images,
        'supports_commerce' => $supportsCommerce,
        'supports_tags' => $supportsTags,
        'errors' => [],
        'success' => isset($_GET['saved']) && $_GET['saved'] === '1',
    ]);
}

function post(): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . appUrl('/login'));
        exit;
    }

    $eventId = getEventIdFromRequest();
    if ($eventId === null) {
        notFound();
    }

    $conn = getConnection();
    $userId = (int)$_SESSION['user_id'];
    $event = findOwnedEvent($conn, $eventId, $userId);
    if (!$event) {
        $conn->close();
        notFound();
    }

    $supportsCommerce = editingActivityColumnExists($conn, 'events', 'price')
        && editingActivityColumnExists($conn, 'events', 'compare_at_price');
    $supportsTags = editingActivityTableExists($conn, 'tags')
        && editingActivityTableExists($conn, 'event_tags')
        && editingActivityColumnExists($conn, 'tags', 'slug')
        && editingActivityColumnExists($conn, 'tags', 'name_th')
        && editingActivityColumnExists($conn, 'tags', 'name_en');

    $formAction = trim((string)($_POST['form_action'] ?? 'update'));
    if ($formAction === 'delete') {
        $deleteErrors = deleteOwnedEvent($conn, $eventId, $userId);
        if (empty($deleteErrors)) {
            $conn->close();
            header('Location: ' . appUrl('/dashboard?deleted=1'));
            exit;
        }

        renderEditingActivity([
            'title' => 'Edit activity',
            'event_id' => $eventId,
            'old' => eventToOldInput($event),
            'existing_images' => getEventImages($conn, $eventId),
            'supports_commerce' => $supportsCommerce ?? false,
            'supports_tags' => $supportsTags ?? false,
            'errors' => $deleteErrors,
            'success' => false,
        ]);
        $conn->close();
        return;
    }

    $stageResult = stageEditingEventImages($_FILES['images'] ?? null, $userId);

    $old = [
        'title' => trim((string)($_POST['title'] ?? '')),
        'description' => trim((string)($_POST['description'] ?? '')),
        'location' => trim((string)($_POST['location'] ?? '')),
        'latitude' => trim((string)($_POST['latitude'] ?? '')),
        'longitude' => trim((string)($_POST['longitude'] ?? '')),
        'event_start' => trim((string)($_POST['event_start'] ?? '')),
        'event_end' => trim((string)($_POST['event_end'] ?? '')),
        'reg_start' => trim((string)($_POST['reg_start'] ?? '')),
        'reg_end' => trim((string)($_POST['reg_end'] ?? '')),
        'max_participant' => trim((string)($_POST['max_participant'] ?? '')),
        'price' => $supportsCommerce ? trim((string)($_POST['price'] ?? '0')) : (string)($event['price'] ?? '0'),
        'compare_at_price' => $supportsCommerce ? trim((string)($_POST['compare_at_price'] ?? '')) : (string)($event['compare_at_price'] ?? ''),
        'tags' => $supportsTags ? trim((string)($_POST['tags'] ?? '')) : '',
        'staged_images' => mergeEditingStagedImages(normalizeEditingStagedImagesInput($_POST['staged_images'] ?? []), $stageResult['images']),
    ];

    $errors = [];
    if (!empty($stageResult['errors'])) {
        $errors = array_merge($errors, $stageResult['errors']);
    }

    if ($old['title'] === '') {
        $errors[] = 'กรุณากรอกชื่อกิจกรรม';
    }
    if ($old['description'] === '') {
        $errors[] = 'กรุณากรอกรายละเอียดกิจกรรม';
    }
    if ($old['location'] === '') {
        $errors[] = 'กรุณากรอกสถานที่';
    }
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

    if ($supportsCommerce) {
        if ($old['price'] === '' || !is_numeric($old['price']) || (float)$old['price'] < 0) {
            $errors[] = 'ราคาต้องเป็นตัวเลขตั้งแต่ 0 ขึ้นไป';
        }

        if ($old['compare_at_price'] !== '' && (!is_numeric($old['compare_at_price']) || (float)$old['compare_at_price'] <= (float)$old['price'])) {
            $errors[] = 'ราคาก่อนลดต้องมากกว่าราคาขาย';
        }
    }

    $tz = appDateLockTimezone();
    $serverToday = new DateTimeImmutable('today', $tz);

    $eventStartDate = parseDateTimeLocalImmutable($old['event_start'], $tz);
    $eventEndDate = parseDateTimeLocalImmutable($old['event_end'], $tz);
    $regStartDate = parseDateImmutable($old['reg_start'], $tz);
    $regEndDate = parseDateImmutable($old['reg_end'], $tz);

    if (!$eventStartDate) {
        $errors[] = 'กรุณาระบุวันเวลาเริ่มกิจกรรมให้ถูกต้อง';
    }
    if (!$eventEndDate) {
        $errors[] = 'กรุณาระบุวันเวลาสิ้นสุดกิจกรรมให้ถูกต้อง';
    }
    if (!$regStartDate) {
        $errors[] = 'กรุณาระบุวันเปิดรับสมัครให้ถูกต้อง';
    }
    if (!$regEndDate) {
        $errors[] = 'กรุณาระบุวันปิดรับสมัครให้ถูกต้อง';
    }

    if ($eventStartDate && $eventEndDate && $eventEndDate <= $eventStartDate) {
        $errors[] = 'วันเวลาสิ้นสุดกิจกรรมต้องมากกว่าวันเวลาเริ่มกิจกรรม';
    }

    if ($regStartDate && $regEndDate && $regEndDate < $regStartDate) {
        $errors[] = 'วันปิดรับสมัครต้องไม่ก่อนวันเปิดรับสมัคร';
    }

    if ($regStartDate && $regStartDate < $serverToday) {
        $errors[] = 'วันเปิดรับสมัครต้องไม่ก่อนวันปัจจุบันของเซิร์ฟเวอร์';
    }

    if ($regEndDate && $regEndDate < $serverToday) {
        $errors[] = 'วันปิดรับสมัครต้องไม่ก่อนวันปัจจุบันของเซิร์ฟเวอร์';
    }

    if ($eventStartDate) {
        $eventStartDay = $eventStartDate->setTime(0, 0, 0);
        $registrationMaxDay = $eventStartDay->modify('-1 day');

        if ($regStartDate && $regStartDate > $registrationMaxDay) {
            $errors[] = 'วันเปิดรับสมัครต้องก่อนวันเริ่มกิจกรรมอย่างน้อย 1 วัน';
        }
        if ($regEndDate && $regEndDate > $registrationMaxDay) {
            $errors[] = 'วันปิดรับสมัครต้องก่อนวันเริ่มกิจกรรมอย่างน้อย 1 วัน';
        }

        if ($registrationMaxDay < $serverToday) {
            $errors[] = 'วันเริ่มกิจกรรมต้องห่างจากวันปัจจุบันอย่างน้อย 1 วัน เพื่อให้มีช่วงเปิดรับสมัคร';
        }
    }

    if (!empty($errors)) {
        renderEditingActivity([
            'title' => 'Edit activity',
            'event_id' => $eventId,
            'old' => $old,
            'existing_images' => getEventImages($conn, $eventId),
            'supports_commerce' => $supportsCommerce ?? false,
            'supports_tags' => $supportsTags ?? false,
            'errors' => array_values(array_unique($errors)),
            'success' => false,
        ]);
        $conn->close();
        return;
    }

    $eventStart = $eventStartDate->format('Y-m-d H:i:s');
    $eventEnd = $eventEndDate->format('Y-m-d H:i:s');
    $regStart = $regStartDate->format('Y-m-d 00:00:00');

    $regEnd = $regEndDate->format('Y-m-d 23:59:59');

    $maxParticipant = (int)$old['max_participant'];
    $latitude = $old['latitude'] === '' ? 0.0 : (float)$old['latitude'];
    $longitude = $old['longitude'] === '' ? 0.0 : (float)$old['longitude'];

    if ($supportsCommerce) {
        $updateStmt = $conn->prepare(
            'UPDATE events
             SET title = ?, description = ?, location = ?, event_start = ?, event_end = ?, reg_start = ?, reg_end = ?,
                 max_participant = ?, price = ?, compare_at_price = NULLIF(?, 0), currency = ?,
                 latitude = NULLIF(?, 0), longitude = NULLIF(?, 0)
             WHERE event_id = ? AND creator_id = ?'
        );
    } else {
        $updateStmt = $conn->prepare(
            'UPDATE events
             SET title = ?, description = ?, location = ?, event_start = ?, event_end = ?, reg_start = ?, reg_end = ?,
                 max_participant = ?, latitude = NULLIF(?, 0), longitude = NULLIF(?, 0)
             WHERE event_id = ? AND creator_id = ?'
        );
    }

    if ($updateStmt === false) {
        renderEditingActivity([
            'title' => 'Edit activity',
            'event_id' => $eventId,
            'old' => $old,
            'existing_images' => getEventImages($conn, $eventId),
            'supports_commerce' => $supportsCommerce,
            'supports_tags' => $supportsTags,
            'errors' => ['ไม่สามารถบันทึกการแก้ไขได้ในขณะนี้'],
            'success' => false,
        ]);
        $conn->close();
        return;
    }

    if ($supportsCommerce) {
        $price = max(0, (float)$old['price']);
        $compareAtPrice = max(0, (float)($old['compare_at_price'] ?: 0));
        $currency = 'THB';
        $updateStmt->bind_param(
            'sssssssiddsddii',
            $old['title'],
            $old['description'],
            $old['location'],
            $eventStart,
            $eventEnd,
            $regStart,
            $regEnd,
            $maxParticipant,
            $price,
            $compareAtPrice,
            $currency,
            $latitude,
            $longitude,
            $eventId,
            $userId
        );
    } else {
        $updateStmt->bind_param(
            'sssssssiddii',
            $old['title'],
            $old['description'],
            $old['location'],
            $eventStart,
            $eventEnd,
            $regStart,
            $regEnd,
            $maxParticipant,
            $latitude,
            $longitude,
            $eventId,
            $userId
        );
    }

    if (!$updateStmt->execute()) {
        $updateStmt->close();
        renderEditingActivity([
            'title' => 'Edit activity',
            'event_id' => $eventId,
            'old' => $old,
            'existing_images' => getEventImages($conn, $eventId),
            'supports_commerce' => $supportsCommerce ?? false,
            'supports_tags' => $supportsTags ?? false,
            'errors' => ['เกิดข้อผิดพลาดระหว่างบันทึกการแก้ไขกิจกรรม'],
            'success' => false,
        ]);
        $conn->close();
        return;
    }
    $updateStmt->close();

    if ($supportsTags) {
        syncEventTags($conn, $eventId, $old['tags']);
    }

    $imageErrors = [];
    $removeImageIds = parseImageIds($_POST['remove_image_ids'] ?? []);
    if (!empty($removeImageIds)) {
        $imageErrors = array_merge($imageErrors, deleteEventImages($conn, $eventId, $removeImageIds));
    }
    $imageErrors = array_merge($imageErrors, commitEditingStagedImages($conn, $eventId, $old['staged_images']));
    $imageErrors = array_values(array_unique($imageErrors));

    if (!empty($imageErrors)) {
        renderEditingActivity([
            'title' => 'Edit activity',
            'event_id' => $eventId,
            'old' => $old,
            'existing_images' => getEventImages($conn, $eventId),
            'supports_commerce' => $supportsCommerce ?? false,
            'supports_tags' => $supportsTags ?? false,
            'errors' => $imageErrors,
            'success' => true,
        ]);
        $conn->close();
        return;
    }

    $conn->close();
    header('Location: ' . appUrl('/editing_activity?event_id=' . $eventId . '&saved=1'));
    exit;
}

function renderEditingActivity(array $data): void
{
    $old = is_array($data['old'] ?? null) ? $data['old'] : [];
    $today = getServerTodayForHtml();
    $eventStartDay = getEventStartDateForHtml((string)($old['event_start'] ?? ''));
    $registrationMaxDay = $eventStartDay !== '' ? shiftHtmlDate($eventStartDay, -1) : '';

    $data['registration_min_date'] = $today;
    $data['registration_max_date'] = $registrationMaxDay;
    $data['server_date_text'] = formatThaiDateLabel($today);
    $data['registration_max_text'] = $registrationMaxDay !== '' ? formatThaiDateLabel($registrationMaxDay) : '';

    renderView('editing_activity', $data);
}

function eventToOldInput(array $event): array
{
    return [
        'title' => (string)$event['title'],
        'description' => (string)$event['description'],
        'location' => (string)$event['location'],
        'latitude' => isset($event['latitude']) && $event['latitude'] !== null ? (string)$event['latitude'] : '',
        'longitude' => isset($event['longitude']) && $event['longitude'] !== null ? (string)$event['longitude'] : '',
        'event_start' => formatForDateTimeLocal((string)$event['event_start']),
        'event_end' => formatForDateTimeLocal((string)$event['event_end']),
        'reg_start' => formatForDate((string)$event['reg_start']),
        'reg_end' => formatForDate((string)$event['reg_end']),
        'max_participant' => (string)$event['max_participant'],
        'price' => isset($event['price']) ? number_format((float)$event['price'], 2, '.', '') : '0',
        'compare_at_price' => isset($event['compare_at_price']) && $event['compare_at_price'] !== null ? number_format((float)$event['compare_at_price'], 2, '.', '') : '',
        'tags' => '',
        'staged_images' => [],
    ];
}

function appDateLockTimezone(): DateTimeZone
{
    return new DateTimeZone('Asia/Bangkok');
}

function getServerTodayForHtml(): string
{
    return (new DateTimeImmutable('today', appDateLockTimezone()))->format('Y-m-d');
}

function getEventStartDateForHtml(string $value): string
{
    $date = parseDateTimeLocalImmutable($value, appDateLockTimezone());
    return $date ? $date->format('Y-m-d') : '';
}

function parseDateTimeLocalImmutable(string $value, DateTimeZone $tz): ?DateTimeImmutable
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value, $tz);
    if ($date instanceof DateTimeImmutable) {
        return $date;
    }

    $fallback = date_create_immutable($value, $tz);
    return $fallback instanceof DateTimeImmutable ? $fallback : null;
}

function parseDateImmutable(string $value, DateTimeZone $tz): ?DateTimeImmutable
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $tz);
    if ($date instanceof DateTimeImmutable) {
        return $date;
    }

    $fallback = date_create_immutable($value, $tz);
    return $fallback instanceof DateTimeImmutable ? $fallback->setTime(0, 0, 0) : null;
}

function formatThaiDateLabel(string $ymd): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $ymd, appDateLockTimezone());
    if (!$date instanceof DateTimeImmutable) {
        return '-';
    }

    return $date->format('d/m/Y');
}

function deleteOwnedEvent(mysqli $conn, int $eventId, int $userId): array
{
    $images = getEventImages($conn, $eventId);
    $imagePaths = [];
    foreach ($images as $image) {
        $dbPath = (string)($image['image_path'] ?? '');
        if ($dbPath !== '') {
            $imagePaths[] = $dbPath;
        }
    }

    if (!$conn->begin_transaction()) {
        return ['ไม่สามารถเริ่มลบกิจกรรมได้ในขณะนี้'];
    }

    $deleteRegistrationsStmt = $conn->prepare('DELETE FROM registrations WHERE event_id = ?');
    $deleteImagesStmt = $conn->prepare('DELETE FROM event_images WHERE event_id = ?');
    $deleteEventStmt = $conn->prepare('DELETE FROM events WHERE event_id = ? AND creator_id = ? LIMIT 1');

    if ($deleteRegistrationsStmt === false || $deleteImagesStmt === false || $deleteEventStmt === false) {
        if ($deleteRegistrationsStmt) {
            $deleteRegistrationsStmt->close();
        }
        if ($deleteImagesStmt) {
            $deleteImagesStmt->close();
        }
        if ($deleteEventStmt) {
            $deleteEventStmt->close();
        }
        $conn->rollback();
        return ['ไม่สามารถลบกิจกรรมได้ในขณะนี้'];
    }

    $errors = [];

    $deleteRegistrationsStmt->bind_param('i', $eventId);
    if (!$deleteRegistrationsStmt->execute()) {
        $errors[] = 'ลบข้อมูลการลงทะเบียนไม่สำเร็จ';
    }
    $deleteRegistrationsStmt->close();

    $deleteImagesStmt->bind_param('i', $eventId);
    if (!$deleteImagesStmt->execute()) {
        $errors[] = 'ลบรายการรูปกิจกรรมไม่สำเร็จ';
    }
    $deleteImagesStmt->close();

    $deleteEventStmt->bind_param('ii', $eventId, $userId);
    if (!$deleteEventStmt->execute() || (int)$deleteEventStmt->affected_rows !== 1) {
        $errors[] = 'ลบกิจกรรมไม่สำเร็จ';
    }
    $deleteEventStmt->close();

    if (!empty($errors)) {
        $conn->rollback();
        return array_values(array_unique($errors));
    }

    if (!$conn->commit()) {
        $conn->rollback();
        return ['ไม่สามารถยืนยันการลบกิจกรรมได้'];
    }

    foreach ($imagePaths as $dbPath) {
        $absolutePath = dirname(__DIR__) . '/' . ltrim(str_replace('\\', '/', $dbPath), '/');
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    return [];
}

function findOwnedEvent(mysqli $conn, int $eventId, int $userId): ?array
{
    $stmt = $conn->prepare(
        'SELECT *
         FROM events
         WHERE event_id = ? AND creator_id = ?
         LIMIT 1'
    );
    if ($stmt === false) {
        return null;
    }

    $stmt->bind_param('ii', $eventId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $event = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return $event ?: null;
}

function getEventImages(mysqli $conn, int $eventId): array
{
    $stmt = $conn->prepare(
        'SELECT image_id, image_path
         FROM event_images
         WHERE event_id = ?
         ORDER BY image_id ASC'
    );
    if ($stmt === false) {
        return [];
    }

    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    $images = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $images;
}

function parseImageIds($raw): array
{
    if (!is_array($raw)) {
        return [];
    }

    $ids = [];
    foreach ($raw as $value) {
        if (!is_scalar($value)) {
            continue;
        }
        $id = (int)$value;
        if ($id > 0) {
            $ids[$id] = true;
        }
    }

    return array_map('intval', array_keys($ids));
}

function deleteEventImages(mysqli $conn, int $eventId, array $imageIds): array
{
    if (empty($imageIds)) {
        return [];
    }

    $errors = [];
    $selectStmt = $conn->prepare('SELECT image_path FROM event_images WHERE image_id = ? AND event_id = ? LIMIT 1');
    $deleteStmt = $conn->prepare('DELETE FROM event_images WHERE image_id = ? AND event_id = ? LIMIT 1');

    if ($selectStmt === false || $deleteStmt === false) {
        if ($selectStmt) {
            $selectStmt->close();
        }
        if ($deleteStmt) {
            $deleteStmt->close();
        }
        return ['ไม่สามารถลบรูปกิจกรรมได้ในขณะนี้'];
    }

    foreach ($imageIds as $imageId) {
        $selectStmt->bind_param('ii', $imageId, $eventId);
        $selectStmt->execute();
        $result = $selectStmt->get_result();
        $image = $result ? $result->fetch_assoc() : null;

        if (!$image) {
            continue;
        }

        $deleteStmt->bind_param('ii', $imageId, $eventId);
        if (!$deleteStmt->execute()) {
            $errors[] = 'ลบรูปบางรายการไม่สำเร็จ';
            continue;
        }

        $dbPath = (string)$image['image_path'];
        if ($dbPath !== '') {
            $absolutePath = dirname(__DIR__) . '/' . ltrim(str_replace('\\', '/', $dbPath), '/');
            if (is_file($absolutePath)) {
                @unlink($absolutePath);
            }
        }
    }

    $selectStmt->close();
    $deleteStmt->close();
    return array_values(array_unique($errors));
}


function shiftHtmlDate(string $ymd, int $days): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $ymd, appDateLockTimezone());
    if (!$date instanceof DateTimeImmutable) {
        return '';
    }

    return $date->modify(($days >= 0 ? '+' : '') . $days . ' day')->format('Y-m-d');
}

function normalizeEditingStagedImagesInput($raw): array
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

function mergeEditingStagedImages(array $current, array $incoming): array
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

function stageEditingEventImages($images, int $userId): array
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

function commitEditingStagedImages(mysqli $conn, int $eventId, array $stagedImages): array
{
    if (empty($stagedImages)) {
        return [];
    }

    $errors = [];
    $uploadRoot = dirname(__DIR__) . '/uploads/events';
    $dbBasePath = '/uploads/events';

    if (!is_dir($uploadRoot) && !mkdir($uploadRoot, 0777, true) && !is_dir($uploadRoot)) {
        return ['ไม่สามารถสร้างโฟลเดอร์สำหรับอัปโหลดรูปได้'];
    }

    $insertImageStmt = $conn->prepare('INSERT INTO event_images (event_id, image_path) VALUES (?, ?)');
    if ($insertImageStmt === false) {
        return ['ไม่สามารถบันทึกรูปกิจกรรมลงฐานข้อมูลได้'];
    }

    foreach (normalizeEditingStagedImagesInput($stagedImages) as $draftPath) {
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

        $insertImageStmt->bind_param('is', $eventId, $dbPath);
        if (!$insertImageStmt->execute()) {
            @unlink($targetPath);
            $errors[] = 'ไม่สามารถบันทึกพาธรูปลงฐานข้อมูลได้';
        }
    }

    $insertImageStmt->close();
    return array_values(array_unique($errors));
}

function saveEventImagesForEvent(mysqli $conn, int $eventId, $images): array
{
    if (!is_array($images) || !isset($images['name']) || !is_array($images['name'])) {
        return [];
    }

    $errors = [];
    $uploadRoot = __DIR__ . '/../uploads/events';
    $dbBasePath = '/uploads/events';

    if (!is_dir($uploadRoot) && !mkdir($uploadRoot, 0777, true) && !is_dir($uploadRoot)) {
        return ['ไม่สามารถสร้างโฟลเดอร์สำหรับอัปโหลดรูปได้'];
    }

    $insertImageStmt = $conn->prepare('INSERT INTO event_images (event_id, image_path) VALUES (?, ?)');
    if ($insertImageStmt === false) {
        return ['ไม่สามารถบันทึกรูปกิจกรรมลงฐานข้อมูลได้'];
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
            $randomPart = bin2hex(random_bytes(8));
        } catch (Exception $exception) {
            $randomPart = (string)mt_rand(10000000, 99999999);
        }

        $fileName = sprintf('%d_%s.%s', $eventId, $randomPart, $extension);
        $absolutePath = $uploadRoot . '/' . $fileName;
        $dbPath = $dbBasePath . '/' . $fileName;

        if (!move_uploaded_file($tmpName, $absolutePath)) {
            $errors[] = 'ไม่สามารถบันทึกไฟล์รูปลงเซิร์ฟเวอร์ได้';
            continue;
        }

        $insertImageStmt->bind_param('is', $eventId, $dbPath);
        if (!$insertImageStmt->execute()) {
            @unlink($absolutePath);
            $errors[] = 'ไม่สามารถบันทึกพาธรูปลงฐานข้อมูลได้';
            continue;
        }
    }

    $insertImageStmt->close();
    return array_values(array_unique($errors));
}


function editingActivityTableExists(mysqli $conn, string $table): bool
{
    $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    if ($stmt === false) {
        return false;
    }

    $stmt->bind_param('s', $table);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return (int)($row['total'] ?? 0) > 0;
}

function editingActivityColumnExists(mysqli $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    if ($stmt === false) {
        return false;
    }

    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return (int)($row['total'] ?? 0) > 0;
}

function getEventTagsText(mysqli $conn, int $eventId): string
{
    $stmt = $conn->prepare(
        'SELECT COALESCE(NULLIF(t.name_th, ""), NULLIF(t.name_en, ""), t.slug) AS tag_name
         FROM event_tags et
         INNER JOIN tags t ON t.tag_id = et.tag_id
         WHERE et.event_id = ?
         ORDER BY t.usage_count DESC, t.name_th ASC'
    );
    if ($stmt === false) {
        return '';
    }

    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    $tags = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $name = trim((string)($row['tag_name'] ?? ''));
            if ($name !== '') {
                $tags[] = $name;
            }
        }
    }
    $stmt->close();

    return implode(', ', $tags);
}

function syncEventTags(mysqli $conn, int $eventId, string $rawTags): void
{
    if (!editingActivityTableExists($conn, 'tags') || !editingActivityTableExists($conn, 'event_tags')) {
        return;
    }

    $tags = parseTagNames($rawTags);

    $deleteStmt = $conn->prepare('DELETE FROM event_tags WHERE event_id = ?');
    if ($deleteStmt) {
        $deleteStmt->bind_param('i', $eventId);
        $deleteStmt->execute();
        $deleteStmt->close();
    }

    if (empty($tags)) {
        return;
    }

    $selectStmt = $conn->prepare('SELECT tag_id FROM tags WHERE slug = ? LIMIT 1');
    $insertTagStmt = $conn->prepare('INSERT INTO tags (slug, name_th, name_en) VALUES (?, ?, ?)');
    $insertEventTagStmt = $conn->prepare('INSERT IGNORE INTO event_tags (event_id, tag_id) VALUES (?, ?)');
    $usageStmt = $conn->prepare('UPDATE tags SET usage_count = usage_count + 1 WHERE tag_id = ?');

    if ($selectStmt === false || $insertTagStmt === false || $insertEventTagStmt === false) {
        if ($selectStmt) $selectStmt->close();
        if ($insertTagStmt) $insertTagStmt->close();
        if ($insertEventTagStmt) $insertEventTagStmt->close();
        if ($usageStmt) $usageStmt->close();
        return;
    }

    foreach ($tags as $tagName) {
        $slug = makeTagSlug($tagName);
        if ($slug === '') {
            continue;
        }

        $tagId = 0;
        $selectStmt->bind_param('s', $slug);
        $selectStmt->execute();
        $result = $selectStmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        if ($row) {
            $tagId = (int)$row['tag_id'];
        } else {
            $insertTagStmt->bind_param('sss', $slug, $tagName, $tagName);
            if ($insertTagStmt->execute()) {
                $tagId = (int)$conn->insert_id;
            }
        }

        if ($tagId <= 0) {
            continue;
        }

        $insertEventTagStmt->bind_param('ii', $eventId, $tagId);
        $insertEventTagStmt->execute();

        if ($usageStmt) {
            $usageStmt->bind_param('i', $tagId);
            $usageStmt->execute();
        }
    }

    $selectStmt->close();
    $insertTagStmt->close();
    $insertEventTagStmt->close();
    if ($usageStmt) $usageStmt->close();
}

function parseTagNames(string $rawTags): array
{
    $parts = preg_split('/[,#]+/u', $rawTags) ?: [];
    $tags = [];
    foreach ($parts as $part) {
        $tag = trim((string)$part);
        if ($tag === '') {
            continue;
        }
        $tag = mb_substr($tag, 0, 60, 'UTF-8');
        $tags[mb_strtolower($tag, 'UTF-8')] = $tag;
        if (count($tags) >= 12) {
            break;
        }
    }
    return array_values($tags);
}

function makeTagSlug(string $tag): string
{
    $slug = mb_strtolower(trim($tag), 'UTF-8');
    $slug = preg_replace('/\s+/u', '-', $slug) ?: '';
    $slug = preg_replace('/[^\p{L}\p{N}\-_]+/u', '', $slug) ?: '';
    return trim($slug, '-_');
}

function getEventIdFromRequest(): ?int
{
    $raw = $_POST['event_id'] ?? $_GET['event_id'] ?? null;
    if (!is_scalar($raw)) {
        return null;
    }

    $eventId = (int)$raw;
    return $eventId > 0 ? $eventId : null;
}

function formatForDateTimeLocal(string $value): string
{
    $date = date_create($value);
    if (!$date) {
        return '';
    }

    return $date->format('Y-m-d\TH:i');
}

function formatForDate(string $value): string
{
    $date = date_create($value);
    if (!$date) {
        return '';
    }

    return $date->format('Y-m-d');
}
