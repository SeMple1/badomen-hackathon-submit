<?php

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        get();
        break;

    case 'POST':
        post();
        break;

    default:
        http_response_code(405);
        exit('Method Not Allowed');
}

function get(): void
{
    if (isset($_SESSION['user_id'])) {
        header('Location: ' . appUrl('/home_in'));
        exit;
    }

    renderView('register', ['title' => 'Register']);
}

function post(): void
{
    if (isset($_SESSION['user_id'])) {
        header('Location: ' . appUrl('/home_in'));
        exit;
    }

    if (!verifyCsrfToken($_POST['_csrf'] ?? null)) {
        renderView('register', [
            'title' => 'Register',
            'errors' => ['เซสชันหมดอายุ กรุณาลองใหม่อีกครั้ง'],
            'success' => false,
        ]);
        return;
    }

    $old = [
        'name' => trim((string)($_POST['name'] ?? '')),
        'age' => trim((string)($_POST['age'] ?? '')),
        'gender' => trim((string)($_POST['gender'] ?? '')),
        'career' => trim((string)($_POST['career'] ?? '')),
        'phone' => trim((string)($_POST['phone'] ?? '')),
        'email' => trim((string)($_POST['email'] ?? '')),
    ];

    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');
    $errors = [];

    $allowedGender = ['male', 'female', 'other'];

    $allowedCareer = [
        'student',
        'employee',
        'business_owner',
        'freelancer',
        'government_officer',
        'other',
    ];

    if ($old['name'] === '') {
        $errors[] = 'กรุณากรอกชื่อ';
    } elseif (!preg_match('/^[a-zA-Zก-๙\s]+$/u', $old['name'])) {
        $errors[] = 'ชื่อสามารถใช้ได้เฉพาะตัวอักษรเท่านั้น';
    }

    if (!ctype_digit($old['age']) || (int)$old['age'] <= 0 || (int)$old['age'] > 120) {
        $errors[] = 'อายุต้องเป็นตัวเลขมากกว่า 0 และน้อยกว่า 120 ปี';
    }

    if (!in_array($old['gender'], $allowedGender, true)) {
        $errors[] = 'กรุณาเลือกเพศ';
    }

    if (!in_array($old['career'], $allowedCareer, true)) {
        $errors[] = 'กรุณาเลือกอาชีพ';
    }

    if ($old['phone'] === '') {
        $errors[] = 'กรุณากรอกเบอร์โทรศัพท์';
    } elseif (!preg_match('/^[0-9+()\-\s]{7,32}$/', $old['phone'])) {
        $errors[] = 'รูปแบบเบอร์โทรศัพท์ไม่ถูกต้อง';
    }

    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'รูปแบบอีเมลไม่ถูกต้อง';
    }

    if (strlen($password) < 8) {
        $errors[] = 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน';
    }

    if (!empty($errors)) {
        renderView('register', [
            'title' => 'Register',
            'old' => $old,
            'errors' => $errors,
            'success' => false,
        ]);
        return;
    }

    $conn = getConnection();

    $check = $conn->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');

    if ($check === false) {
        $conn->close();

        renderView('register', [
            'title' => 'Register',
            'old' => $old,
            'errors' => ['ไม่สามารถตรวจสอบอีเมลได้'],
            'success' => false,
        ]);
        return;
    }

    $check->bind_param('s', $old['email']);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $check->close();
        $conn->close();

        renderView('register', [
            'title' => 'Register',
            'old' => $old,
            'errors' => ['อีเมลนี้ถูกใช้งานแล้ว'],
            'success' => false,
        ]);
        return;
    }

    $check->close();

    $age = (int)$old['age'];
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $memberRank = 'member';

    $stmt = $conn->prepare(
        'INSERT INTO users (name, email, phone, password, age, gender, career, member_rank) VALUES (?, ?, NULLIF(?, \'\'), ?, ?, ?, ?, ?)'
    );

    if ($stmt === false) {
        $conn->close();

        renderView('register', [
            'title' => 'Register',
            'old' => $old,
            'errors' => ['ไม่สามารถเตรียมคำสั่ง SQL ได้'],
            'success' => false,
        ]);
        return;
    }

    $stmt->bind_param(
        'ssssisss',
        $old['name'],
        $old['email'],
        $old['phone'],
        $hashedPassword,
        $age,
        $old['gender'],
        $old['career'],
        $memberRank
    );

    try {
        $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        $stmt->close();
        $conn->close();

        $message = ((int)$e->getCode() === 1062)
            ? 'อีเมลนี้ถูกใช้งานแล้ว'
            : 'เกิดข้อผิดพลาดระหว่างบันทึกข้อมูล';

        renderView('register', [
            'title' => 'Register',
            'old' => $old,
            'errors' => [$message],
            'success' => false,
        ]);
        return;
    }

    $userId = (int)$conn->insert_id;
    $stmt->close();

    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_name'] = $old['name'];
    $_SESSION['user_email'] = $old['email'];

    $guestFavorites = (string)($_POST['guest_favorite_ids'] ?? '');
    importGuestFavorites($conn, $userId, $guestFavorites);
    if (normalizeGuestFavoriteIds($guestFavorites) !== []) {
        $_SESSION['_clear_guest_favorites'] = true;
    }
    $conn->close();

    header('Location: ' . appUrl('/home_in'));
    exit;
}
