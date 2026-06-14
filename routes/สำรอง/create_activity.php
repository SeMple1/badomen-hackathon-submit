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

    renderView('create_activity', [
        'title' => 'Create activity',
        'old' => [],
        'errors' => [],
        'success' => false,
    ]);
}

function post(): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . appUrl('/login'));
        exit;
    }

    $old = [
        'title' => trim((string)($_POST['title'] ?? '')),
        'description' => trim((string)($_POST['description'] ?? '')),
        'location' => trim((string)($_POST['location'] ?? '')),
        'event_start' => trim((string)($_POST['event_start'] ?? '')),
        'event_end' => trim((string)($_POST['event_end'] ?? '')),
        'reg_start' => trim((string)($_POST['reg_start'] ?? '')),
        'reg_end' => trim((string)($_POST['reg_end'] ?? '')),
        'max_participant' => trim((string)($_POST['max_participant'] ?? '')),
        'price' => trim((string)($_POST['price'] ?? '0')),
        'compare_at_price' => trim((string)($_POST['compare_at_price'] ?? '')),
        'tags' => trim((string)($_POST['tags'] ?? '')),
    ];

    $errors = [];

    if ($old['title'] === '') {
        $errors[] = 'กรุณากรอกชื่อกิจกรรม';
    }

    if ($old['description'] === '') {
        $errors[] = 'กรุณากรอกรายละเอียดกิจกรรม';
    }

    if ($old['location'] === '') {
        $errors[] = 'กรุณากรอกสถานที่';
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

    $tz = new DateTimeZone('Asia/Bangkok');

    $eventStartDate = DateTime::createFromFormat('Y-m-d\TH:i', $old['event_start'], $tz);
    $eventEndDate   = DateTime::createFromFormat('Y-m-d\TH:i', $old['event_end'], $tz);
    $regStartDate   = DateTime::createFromFormat('Y-m-d', $old['reg_start'], $tz);
    $regEndDate     = DateTime::createFromFormat('Y-m-d', $old['reg_end'], $tz);

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

    if ($eventStartDate && $regEndDate && $eventStartDate->format('Y-m-d') <= $regEndDate->format('Y-m-d')) {
        $errors[] = 'วันเริ่มกิจกรรมต้องอยู่หลังวันปิดรับสมัคร';
    }

    if (!empty($errors)) {
        renderView('create_activity', [
            'title' => 'Create activity',
            'old' => $old,
            'errors' => $errors,
            'success' => false,
        ]);
        return;
    }

    $creatorId = (int)$_SESSION['user_id'];
    $maxParticipant = (int)$old['max_participant'];

    $eventStart = $eventStartDate->format('Y-m-d H:i:s');
    $eventEnd   = $eventEndDate->format('Y-m-d H:i:s');

    $regStart = $regStartDate->setTime(0, 0, 0)->format('Y-m-d H:i:s');
    $regEnd   = $regEndDate->setTime(23, 59, 59)->format('Y-m-d H:i:s');

    $conn = getConnection();

    $hasCommerceFields = databaseColumnExists($conn, 'events', 'price');
    if (($old['price'] !== '0' || $old['compare_at_price'] !== '' || $old['tags'] !== '') && !$hasCommerceFields) {
        $conn->close();
        renderView('create_activity', [
            'title' => 'Create activity',
            'old' => $old,
            'errors' => ['กรุณารัน database/migrations/20260613_platform_scale.sql ก่อนใช้ราคา ส่วนลด หรือแท็ก'],
            'success' => false,
        ]);
        return;
    }

    if ($hasCommerceFields) {
        $stmt = $conn->prepare(
            'INSERT INTO events
             (creator_id, title, description, location, event_start, event_end, reg_start, reg_end,
              max_participant, price, compare_at_price, currency)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, 0), ?)'
        );
    } else {
        $stmt = $conn->prepare(
            'INSERT INTO events (creator_id, title, description, location, event_start, event_end, reg_start, reg_end, max_participant)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
    }

    if ($stmt === false) {
        $conn->close();
        renderView('create_activity', [
            'title' => 'Create activity',
            'old' => $old,
            'errors' => ['ไม่สามารถบันทึกกิจกรรมได้ในขณะนี้'],
            'success' => false,
        ]);
        return;
    }

    if ($hasCommerceFields) {
        $price = max(0, (float)$old['price']);
        $compareAtPrice = max(0, (float)($old['compare_at_price'] ?: 0));
        $currency = 'THB';
        $stmt->bind_param(
            'isssssssidds',
            $creatorId,
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
            $currency
        );
    } else {
        $stmt->bind_param(
            'isssssssi',
            $creatorId,
            $old['title'],
            $old['description'],
            $old['location'],
            $eventStart,
            $eventEnd,
            $regStart,
            $regEnd,
            $maxParticipant
        );
    }

    if (!$stmt->execute()) {
        $stmt->close();
        $conn->close();
        renderView('create_activity', [
            'title' => 'Create activity',
            'old' => $old,
            'errors' => ['เกิดข้อผิดพลาดระหว่างบันทึกกิจกรรม'],
            'success' => false,
        ]);
        return;
    }

    $stmt->close();
    $eventId = (int)$conn->insert_id;

    replaceEventTags($conn, $eventId, $old['tags']);

    $imageErrors = saveEventImages($conn, $eventId, $_FILES['images'] ?? null);

    if (!empty($imageErrors)) {
        $deleteStmt = $conn->prepare('DELETE FROM events WHERE event_id = ? LIMIT 1');
        if ($deleteStmt) {
            $deleteStmt->bind_param('i', $eventId);
            $deleteStmt->execute();
            $deleteStmt->close();
        }

        $conn->close();

        renderView('create_activity', [
            'title' => 'Create activity',
            'old' => $old,
            'errors' => $imageErrors,
            'success' => false,
        ]);
        return;
    }

    $conn->close();

    renderView('create_activity', [
        'title' => 'Create activity',
        'old' => [],
        'errors' => [],
        'success' => true,
    ]);
}

function saveEventImages(mysqli $conn, int $eventId, $images): array
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
