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
    renderView('register', ['title' => 'Register']);
}


function post(): void
{
    $old = [
        'name' => trim((string)($_POST['name'] ?? '')),
        'age' => trim((string)($_POST['age'] ?? '')),
        'gender' => trim((string)($_POST['gender'] ?? '')),
        'career' => trim((string)($_POST['career'] ?? '')),
        'email' => trim((string)($_POST['email'] ?? '')),
    ];


    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');
    $errors = [];
    $allowedGender = ['male', 'female', 'other'];
    $allowedCareer = ['student', 'employee', 'business_owner', 'freelancer', 'government_officer', 'other'];

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


    if ($password !== $confirmPassword) {
        $errors[] = 'รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน';
    }


    if (strlen($password) < 8) {
        $errors[] = 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร';
    }


    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'รูปแบบอีเมลไม่ถูกต้อง';
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
    $age = (int)$old['age'];
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $check = $conn->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
    $check->bind_param('s', $old['email']);
    $check->execute();
    $check->store_result();
    $check->close();

    $stmt = $conn->prepare(
        'INSERT INTO users (name, email, password, age, gender, career) VALUES (?, ?, ?, ?, ?, ?)'
    );

    if ($stmt === false) {
        $errors[] = 'ไม่สามารถเตรียมคำสั่ง SQL ได้';
    } else {
        $stmt->bind_param(
            'sssiss',
            $old['name'],
            $old['email'],
            $hashedPassword,
            $age,
            $old['gender'],
            $old['career']
        );

        try {
            $stmt->execute();
        } catch (mysqli_sql_exception $e) {
            if ((int)$e->getCode() === 1062) {
                $errors[] = 'อีเมลนี้ถูกใช้งานแล้ว';
            } else {
                $errors[] = 'เกิดข้อผิดพลาดระหว่างบันทึกข้อมูล';
            }
        }

        $stmt->close();
    }

    $conn->close();

    if (!empty($errors)) {
        renderView('register', [
            'title' => 'Register',
            'old' => $old,
            'errors' => $errors,
            'success' => false,
        ]);
        return;
    }

    renderView('register', [
        'title' => 'Register',
        'old' => [],
        'errors' => [],
        'success' => true,
    ]);
}
