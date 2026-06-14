<?php

declare(strict_types=1);

ensurePasswordResetTable();

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'GET' && isset($_GET['restart'])) {
    clearPasswordResetSession();
    header('Location: ' . appUrl('/forgot-password'));
    exit;
}

if ($method === 'POST') {
    handleForgotPasswordPost();
}

renderForgotPassword();

function ensurePasswordResetTable(): void
{
    $conn = getConnection();
    $conn->query(
        "CREATE TABLE IF NOT EXISTS password_reset_otps (
            reset_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            email VARCHAR(255) NOT NULL,
            otp_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            resend_available_at DATETIME NOT NULL,
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 5,
            consumed_at DATETIME NULL,
            request_ip_hash CHAR(64) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_reset_email_created (email, created_at),
            INDEX idx_reset_user_active (user_id, consumed_at, expires_at),
            CONSTRAINT fk_password_reset_user
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $conn->close();
}

function handleForgotPasswordPost(): never
{
    if (!verifyCsrfToken($_POST['_csrf'] ?? null)) {
        renderForgotPassword(['errors' => ['เซสชันหมดอายุ กรุณาลองใหม่อีกครั้ง']], 419);
    }

    $action = (string)($_POST['action'] ?? '');

    if ($action === 'request' || $action === 'resend') {
        requestPasswordOtp();
    }

    if ($action === 'verify') {
        verifyPasswordOtp();
    }

    if ($action === 'reset') {
        resetPassword();
    }

    renderForgotPassword(['errors' => ['คำขอไม่ถูกต้อง']], 400);
}

function requestPasswordOtp(): never
{
    $email = strtolower(trim((string)($_POST['email'] ?? $_SESSION['password_reset_email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        renderForgotPassword([
            'stage' => 'request',
            'email' => $email,
            'errors' => ['กรุณากรอกอีเมลให้ถูกต้อง'],
        ], 422);
    }

    $conn = getConnection();
    $stmt = $conn->prepare('SELECT user_id, name, email FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $_SESSION['password_reset_email'] = $email;
    unset($_SESSION['password_reset_verified_id'], $_SESSION['password_reset_otp_id']);

    if (!$user) {
        $conn->close();
        usleep(random_int(180000, 320000));
        $_SESSION['password_reset_user_id'] = 0;
        renderForgotPassword([
            'stage' => 'verify',
            'email' => $email,
            'messages' => ['หากอีเมลนี้มีบัญชีอยู่ ระบบได้ส่งรหัส OTP ให้แล้ว'],
        ]);
    }

    $userId = (int)$user['user_id'];
    $latest = $conn->prepare(
        'SELECT resend_available_at FROM password_reset_otps
         WHERE user_id = ? AND consumed_at IS NULL
         ORDER BY reset_id DESC LIMIT 1'
    );
    $latest->bind_param('i', $userId);
    $latest->execute();
    $latestRow = $latest->get_result()->fetch_assoc();
    $latest->close();

    if ($latestRow && strtotime((string)$latestRow['resend_available_at']) > time()) {
        $wait = max(1, strtotime((string)$latestRow['resend_available_at']) - time());
        $conn->close();
        renderForgotPassword([
            'stage' => 'verify',
            'email' => $email,
            'errors' => ["กรุณารอ {$wait} วินาทีก่อนขอ OTP ใหม่"],
        ], 429);
    }

    $ipHash = requestIpHash();
    $rate = $conn->prepare(
        'SELECT COUNT(*) AS total FROM password_reset_otps
         WHERE (email = ? OR request_ip_hash = ?)
           AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)'
    );
    $rate->bind_param('ss', $email, $ipHash);
    $rate->execute();
    $requestCount = (int)($rate->get_result()->fetch_assoc()['total'] ?? 0);
    $rate->close();

    if ($requestCount >= 8) {
        $conn->close();
        renderForgotPassword([
            'stage' => 'request',
            'email' => $email,
            'errors' => ['มีการขอ OTP มากเกินไป กรุณาลองใหม่ภายหลัง'],
        ], 429);
    }

    $otp = (string)random_int(100000, 999999);
    $mail = sendPasswordResetOtp(
        (string)$user['email'],
        (string)$user['name'],
        $otp
    );

    if (!$mail['ok']) {
        $conn->close();
        renderForgotPassword([
            'stage' => 'request',
            'email' => $email,
            'errors' => ['ระบบส่งอีเมลยังไม่พร้อม กรุณาตรวจสอบการตั้งค่า Gmail API หรือลองใหม่ภายหลัง'],
        ], 503);
    }

    $conn->begin_transaction();
    try {
        $consume = $conn->prepare(
            'UPDATE password_reset_otps SET consumed_at = NOW()
             WHERE user_id = ? AND consumed_at IS NULL'
        );
        $consume->bind_param('i', $userId);
        $consume->execute();
        $consume->close();

        $otpHash = password_hash($otp, PASSWORD_DEFAULT);
        $insert = $conn->prepare(
            'INSERT INTO password_reset_otps
             (user_id, email, otp_hash, expires_at, resend_available_at, request_ip_hash)
             VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), DATE_ADD(NOW(), INTERVAL 60 SECOND), ?)'
        );
        $insert->bind_param('isss', $userId, $email, $otpHash, $ipHash);
        $insert->execute();
        $insert->close();
        $conn->commit();
    } catch (Throwable $error) {
        $conn->rollback();
        $conn->close();
        renderForgotPassword([
            'stage' => 'request',
            'email' => $email,
            'errors' => ['ไม่สามารถสร้างคำขอกู้คืนรหัสผ่านได้ กรุณาลองใหม่'],
        ], 500);
    }

    $conn->close();
    $_SESSION['password_reset_user_id'] = $userId;

    renderForgotPassword([
        'stage' => 'verify',
        'email' => $email,
        'messages' => ['ส่ง OTP 6 หลักไปยังอีเมลของคุณแล้ว รหัสมีอายุ 10 นาที'],
    ]);
}

function verifyPasswordOtp(): never
{
    $email = (string)($_SESSION['password_reset_email'] ?? '');
    $userId = (int)($_SESSION['password_reset_user_id'] ?? 0);
    $otp = preg_replace('/\D+/', '', (string)($_POST['otp'] ?? ''));

    if (!preg_match('/^\d{6}$/', $otp)) {
        renderForgotPassword([
            'stage' => 'verify',
            'email' => $email,
            'errors' => ['OTP ต้องเป็นตัวเลข 6 หลัก'],
        ], 422);
    }

    if ($userId <= 0 || $email === '') {
        usleep(random_int(180000, 320000));
        renderForgotPassword([
            'stage' => 'verify',
            'email' => $email,
            'errors' => ['OTP ไม่ถูกต้องหรือหมดอายุแล้ว'],
        ], 422);
    }

    $conn = getConnection();
    $stmt = $conn->prepare(
        'SELECT reset_id, otp_hash, attempts, max_attempts
         FROM password_reset_otps
         WHERE user_id = ? AND email = ? AND consumed_at IS NULL AND expires_at >= NOW()
         ORDER BY reset_id DESC LIMIT 1'
    );
    $stmt->bind_param('is', $userId, $email);
    $stmt->execute();
    $reset = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$reset || (int)$reset['attempts'] >= (int)$reset['max_attempts']) {
        $conn->close();
        renderForgotPassword([
            'stage' => 'verify',
            'email' => $email,
            'errors' => ['OTP ไม่ถูกต้องหรือหมดอายุแล้ว'],
        ], 422);
    }

    $resetId = (int)$reset['reset_id'];
    if (!password_verify($otp, (string)$reset['otp_hash'])) {
        $update = $conn->prepare('UPDATE password_reset_otps SET attempts = attempts + 1 WHERE reset_id = ?');
        $update->bind_param('i', $resetId);
        $update->execute();
        $update->close();
        $conn->close();

        renderForgotPassword([
            'stage' => 'verify',
            'email' => $email,
            'errors' => ['OTP ไม่ถูกต้องหรือหมดอายุแล้ว'],
        ], 422);
    }

    $conn->close();
    session_regenerate_id(true);
    $_SESSION['password_reset_verified_id'] = $userId;
    $_SESSION['password_reset_otp_id'] = $resetId;

    renderForgotPassword([
        'stage' => 'reset',
        'email' => $email,
        'messages' => ['ยืนยัน OTP สำเร็จ กรุณาตั้งรหัสผ่านใหม่'],
    ]);
}

function resetPassword(): never
{
    $userId = (int)($_SESSION['password_reset_verified_id'] ?? 0);
    $resetId = (int)($_SESSION['password_reset_otp_id'] ?? 0);
    $email = (string)($_SESSION['password_reset_email'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');
    $errors = [];

    if (strlen($password) < 8) {
        $errors[] = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'รหัสผ่านใหม่และการยืนยันไม่ตรงกัน';
    }
    if ($userId <= 0 || $resetId <= 0) {
        $errors[] = 'คำขอกู้คืนหมดอายุ กรุณาเริ่มใหม่';
    }

    if ($errors) {
        renderForgotPassword([
            'stage' => $userId > 0 ? 'reset' : 'request',
            'email' => $email,
            'errors' => $errors,
        ], 422);
    }

    $conn = getConnection();
    $check = $conn->prepare(
        'SELECT reset_id FROM password_reset_otps
         WHERE reset_id = ? AND user_id = ? AND consumed_at IS NULL
           AND expires_at >= NOW() AND attempts < max_attempts
         LIMIT 1'
    );
    $check->bind_param('ii', $resetId, $userId);
    $check->execute();
    $valid = $check->get_result()->fetch_assoc();
    $check->close();

    if (!$valid) {
        $conn->close();
        clearPasswordResetSession();
        renderForgotPassword([
            'stage' => 'request',
            'errors' => ['คำขอกู้คืนหมดอายุ กรุณาเริ่มใหม่'],
        ], 422);
    }

    $conn->begin_transaction();
    try {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $updateUser = $conn->prepare('UPDATE users SET password = ? WHERE user_id = ?');
        $updateUser->bind_param('si', $passwordHash, $userId);
        $updateUser->execute();
        $updateUser->close();

        $consume = $conn->prepare(
            'UPDATE password_reset_otps SET consumed_at = NOW()
             WHERE user_id = ? AND consumed_at IS NULL'
        );
        $consume->bind_param('i', $userId);
        $consume->execute();
        $consume->close();
        $conn->commit();
    } catch (Throwable $error) {
        $conn->rollback();
        $conn->close();
        renderForgotPassword([
            'stage' => 'reset',
            'email' => $email,
            'errors' => ['ไม่สามารถเปลี่ยนรหัสผ่านได้ กรุณาลองใหม่'],
        ], 500);
    }

    $conn->close();
    clearPasswordResetSession();
    renderForgotPassword([
        'stage' => 'complete',
        'messages' => ['เปลี่ยนรหัสผ่านเรียบร้อยแล้ว คุณสามารถเข้าสู่ระบบด้วยรหัสผ่านใหม่ได้'],
    ]);
}

function sendPasswordResetOtp(string $email, string $name, string $otp): array
{
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeOtp = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');
    $subject = 'รหัส OTP สำหรับกู้คืนรหัสผ่าน Badomen';
    $text = "สวัสดี {$name}\n\nรหัส OTP ของคุณคือ {$otp}\nรหัสนี้มีอายุ 10 นาที และใช้ได้ครั้งเดียว\n\nหากคุณไม่ได้ขอเปลี่ยนรหัสผ่าน สามารถละเว้นอีเมลนี้ได้";
    $html = "<div style=\"font-family:Arial,sans-serif;max-width:560px;margin:auto;padding:24px;color:#111827\">"
        . "<h2 style=\"color:#ff6a00\">กู้คืนรหัสผ่าน Badomen</h2>"
        . "<p>สวัสดี {$safeName}</p>"
        . "<p>ใช้รหัส OTP ด้านล่างเพื่อยืนยันการตั้งรหัสผ่านใหม่</p>"
        . "<div style=\"font-size:32px;font-weight:800;letter-spacing:8px;padding:18px;text-align:center;background:#fff3e8;border-radius:16px\">{$safeOtp}</div>"
        . "<p>รหัสนี้มีอายุ 10 นาทีและใช้ได้ครั้งเดียว</p>"
        . "<p style=\"color:#6b7280;font-size:13px\">หากคุณไม่ได้เป็นผู้ขอเปลี่ยนรหัสผ่าน สามารถละเว้นอีเมลนี้ได้</p>"
        . "</div>";

    return sendGmailMessage($email, $subject, $text, $html);
}

function clearPasswordResetSession(): void
{
    unset(
        $_SESSION['password_reset_email'],
        $_SESSION['password_reset_user_id'],
        $_SESSION['password_reset_verified_id'],
        $_SESSION['password_reset_otp_id']
    );
}

function renderForgotPassword(array $data = [], int $status = 200): never
{
    http_response_code($status);
    $defaults = [
        'stage' => isset($_SESSION['password_reset_verified_id']) ? 'reset' : 'request',
        'email' => (string)($_SESSION['password_reset_email'] ?? ''),
        'errors' => [],
        'messages' => [],
    ];

    renderView('forgot-password', array_merge($defaults, $data));
    exit;
}
