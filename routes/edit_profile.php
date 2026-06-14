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

    $userId = (int)$_SESSION['user_id'];
    $conn = getConnection();

    $stmt = $conn->prepare(
        'SELECT user_id, name, email, phone, age, gender, career, avatar_path, bio,
                member_rank, vip_started_at, vip_expires_at,
                locale, timezone, notification_email, notification_web, created_at
         FROM users WHERE user_id = ? LIMIT 1'
    );

    if ($stmt === false) {
        $conn->close();
        header('Location: ' . appUrl('/home_in'));
        exit;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    $conn->close();

    if (!$user) {
        header('Location: ' . appUrl('/home_in'));
        exit;
    }

    renderView('edit_profile', [
        'title' => 'Edit Profile',
        'user' => $user,
        'errors' => [],
        'successes' => [],
    ]);
}

function post(): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . appUrl('/login'));
        exit;
    }

    if (!verifyCsrfToken($_POST['_csrf'] ?? null)) {
        header('Location: ' . appUrl('/edit_profile'));
        exit;
    }

    $userId = (int)$_SESSION['user_id'];

    $old = [
        'name' => trim((string)($_POST['name'] ?? '')),
        'age' => trim((string)($_POST['age'] ?? '')),
        'gender' => trim((string)($_POST['gender'] ?? '')),
        'career' => trim((string)($_POST['career'] ?? '')),
        'phone' => trim((string)($_POST['phone'] ?? '')),
        'bio' => trim((string)($_POST['bio'] ?? '')),
        'notification_email' => isset($_POST['notification_email']) ? '1' : '0',
        'notification_web' => isset($_POST['notification_web']) ? '1' : '0',
    ];

    $errors = [];
    $allowedGender = ['male', 'female', 'other'];
    $allowedCareer = ['student', 'employee', 'business_owner', 'freelancer', 'government_officer', 'other'];

    if ($old['name'] === '') {
        $errors[] = 'กรุณากรอกชื่อ';
    } elseif (!preg_match('/^[a-zA-Zก-๙\s]+$/u', $old['name'])) {
        $errors[] = 'ชื่อสามารถใช้ได้เฉพาะตัวอักษรเท่านั้น';
    }

    if (!ctype_digit($old['age']) || (int)$old['age'] <= 0 || (int)$old['age'] > 120) {
        $errors[] = 'อายุต้องเป็นตัวเลขมากกว่า 0 และไม่เกิน 120 ปี';
    }

    if (!in_array($old['gender'], $allowedGender, true)) {
        $errors[] = 'กรุณาเลือกเพศ';
    }

    if (!in_array($old['career'], $allowedCareer, true)) {
        $errors[] = 'กรุณาเลือกอาชีพ';
    }
    if ($old['phone'] !== '' && !preg_match('/^[0-9+()\-\s]{7,32}$/', $old['phone'])) {
        $errors[] = 'รูปแบบเบอร์โทรศัพท์ไม่ถูกต้อง';
    }
    if (mb_strlen($old['bio'], 'UTF-8') > 500) {
        $errors[] = 'แนะนำตัวได้ไม่เกิน 500 ตัวอักษร';
    }

    $conn = getConnection();

    $userStmt = $conn->prepare(
        'SELECT user_id, name, email, phone, age, gender, career, avatar_path, bio,
                member_rank, vip_started_at, vip_expires_at,
                locale, timezone, notification_email, notification_web, created_at
         FROM users WHERE user_id = ? LIMIT 1'
    );

    if ($userStmt === false) {
        $conn->close();
        header('Location: ' . appUrl('/home_in'));
        exit;
    }

    $userStmt->bind_param('i', $userId);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    $user = $userResult ? $userResult->fetch_assoc() : null;
    $userStmt->close();

    if (!$user) {
        $conn->close();
        header('Location: ' . appUrl('/home_in'));
        exit;
    }

    if (!empty($errors)) {
        $user['name'] = $old['name'];
        $user['age'] = $old['age'];
        $user['gender'] = $old['gender'];
        $user['career'] = $old['career'];
        $user['phone'] = $old['phone'];
        $user['bio'] = $old['bio'];
        $user['notification_email'] = $old['notification_email'];
        $user['notification_web'] = $old['notification_web'];

        $conn->close();

        renderView('edit_profile', [
            'title' => 'Edit Profile',
            'user' => $user,
            'errors' => $errors,
            'successes' => [],
        ]);
        return;
    }

    $avatarPath = handleProfileAvatarUpload($userId, (string)($user['avatar_path'] ?? ''), $errors);
    if (!empty($errors)) {
        $user['name'] = $old['name'];
        $user['age'] = $old['age'];
        $user['gender'] = $old['gender'];
        $user['career'] = $old['career'];
        $user['phone'] = $old['phone'];
        $user['bio'] = $old['bio'];
        $user['notification_email'] = $old['notification_email'];
        $user['notification_web'] = $old['notification_web'];

        $conn->close();

        renderView('edit_profile', [
            'title' => 'Edit Profile',
            'user' => $user,
            'errors' => $errors,
            'successes' => [],
        ]);
        return;
    }

    $age = (int)$old['age'];
    $notificationEmail = (int)$old['notification_email'];
    $notificationWeb = (int)$old['notification_web'];

    $stmt = $conn->prepare(
        "UPDATE users
         SET name = ?, phone = NULLIF(?, ''), age = ?, gender = ?, career = ?,
             avatar_path = NULLIF(?, ''), bio = NULLIF(?, ''), notification_email = ?, notification_web = ?
         WHERE user_id = ? LIMIT 1"
    );

    if ($stmt === false) {
        $conn->close();
        renderView('edit_profile', [
            'title' => 'Edit Profile',
            'user' => $user,
            'errors' => ['ไม่สามารถเตรียมคำสั่ง SQL ได้'],
            'successes' => [],
        ]);
        return;
    }

    $stmt->bind_param(
        'ssissssiii',
        $old['name'],
        $old['phone'],
        $age,
        $old['gender'],
        $old['career'],
        $avatarPath,
        $old['bio'],
        $notificationEmail,
        $notificationWeb,
        $userId
    );

    try {
        $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        $stmt->close();
        $conn->close();

        $user['name'] = $old['name'];
        $user['age'] = $old['age'];
        $user['gender'] = $old['gender'];
        $user['career'] = $old['career'];
        $user['phone'] = $old['phone'];
        $user['bio'] = $old['bio'];
        $user['notification_email'] = $notificationEmail;
        $user['notification_web'] = $notificationWeb;

        renderView('edit_profile', [
            'title' => 'Edit Profile',
            'user' => $user,
            'errors' => ['เกิดข้อผิดพลาดระหว่างบันทึกข้อมูล'],
            'successes' => [],
        ]);
        return;
    }

    $stmt->close();

    $refreshStmt = $conn->prepare(
        'SELECT user_id, name, email, phone, age, gender, career, avatar_path, bio,
                member_rank, vip_started_at, vip_expires_at,
                locale, timezone, notification_email, notification_web, created_at
         FROM users WHERE user_id = ? LIMIT 1'
    );

    if ($refreshStmt === false) {
        $conn->close();
        header('Location: ' . appUrl('/profile'));
        exit;
    }

    $refreshStmt->bind_param('i', $userId);
    $refreshStmt->execute();
    $refreshResult = $refreshStmt->get_result();
    $updatedUser = $refreshResult ? $refreshResult->fetch_assoc() : $user;
    $refreshStmt->close();
    $conn->close();

    $_SESSION['user_name'] = (string)($updatedUser['name'] ?? $old['name']);

    if (function_exists('addFlashMessage')) {
        addFlashMessage('profile_successes', 'บันทึกข้อมูลเรียบร้อยแล้ว');
    }

    header('Location: ' . appUrl('/profile'));
    exit;
}

function handleProfileAvatarUpload(int $userId, string $currentAvatarPath, array &$errors): string
{
    if (!isset($_FILES['avatar']) || !is_array($_FILES['avatar'])) {
        return $currentAvatarPath;
    }

    $file = $_FILES['avatar'];
    $errorCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        return $currentAvatarPath;
    }

    if ($errorCode !== UPLOAD_ERR_OK) {
        $errors[] = 'อัปโหลดรูปโปรไฟล์ไม่สำเร็จ';
        return $currentAvatarPath;
    }

    $tmpName = (string)($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        $errors[] = 'ไม่พบไฟล์รูปโปรไฟล์ที่อัปโหลด';
        return $currentAvatarPath;
    }

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > 2 * 1024 * 1024) {
        $errors[] = 'รูปโปรไฟล์ต้องมีขนาดไม่เกิน 2MB';
        return $currentAvatarPath;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($tmpName);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowed[$mime])) {
        $errors[] = 'รองรับเฉพาะไฟล์ JPG, PNG หรือ WEBP เท่านั้น';
        return $currentAvatarPath;
    }

    $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'avatars';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        $errors[] = 'ไม่สามารถสร้างโฟลเดอร์สำหรับเก็บรูปโปรไฟล์ได้';
        return $currentAvatarPath;
    }

    $filename = 'user_' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmpName, $targetPath)) {
        $errors[] = 'ไม่สามารถบันทึกรูปโปรไฟล์ได้';
        return $currentAvatarPath;
    }

    $newPath = 'uploads/avatars/' . $filename;

    $oldPath = str_replace('\\', '/', trim($currentAvatarPath));
    if ($oldPath !== '' && strpos($oldPath, 'uploads/avatars/') === 0) {
        $oldFullPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $oldPath);
        if (is_file($oldFullPath)) {
            @unlink($oldFullPath);
        }
    }

    return $newPath;
}
