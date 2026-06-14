<?php
$stage = (string)($stage ?? 'request');
$email = (string)($email ?? '');
$errors = is_array($errors ?? null) ? $errors : [];
$messages = is_array($messages ?? null) ? $messages : [];
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>กู้คืนรหัสผ่าน | Badomen</title>
    <link rel="stylesheet" href="/style/app.css">
    <link rel="stylesheet" href="/style/login.css?v=5">
    <link rel="stylesheet" href="/style/forgot-password.css?v=1">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet"></noscript>
</head>
<body>
    <?php require __DIR__ . '/header.php'; ?>
    <header class="legacy-header" hidden>
        <div class="header-inner">
            <div class="logo"><a href="/"><img src="/mylogo1.png" alt="Badomen Logo"></a></div>
            <nav class="nav" aria-label="Main navigation">
                <a class="login" href="/">หน้าหลัก</a>
                <a class="register" href="/login">เข้าสู่ระบบ</a>
            </nav>
        </div>
    </header>

    <main class="reset-page">
        <section class="reset-card" aria-labelledby="resetTitle">
            <div class="reset-icon"><i class="bx bx-shield-quarter"></i></div>

            <?php if ($stage === 'complete'): ?>
                <h1 id="resetTitle">ตั้งรหัสผ่านใหม่แล้ว</h1>
                <p>บัญชีของคุณพร้อมใช้งานด้วยรหัสผ่านใหม่</p>
            <?php elseif ($stage === 'verify'): ?>
                <h1 id="resetTitle">ตรวจสอบ OTP</h1>
                <p>กรอกรหัส 6 หลักที่ส่งไปยัง <strong><?= $escape($email) ?></strong></p>
            <?php elseif ($stage === 'reset'): ?>
                <h1 id="resetTitle">ตั้งรหัสผ่านใหม่</h1>
                <p>ใช้รหัสผ่านอย่างน้อย 8 ตัวอักษรและไม่ควรซ้ำกับบริการอื่น</p>
            <?php else: ?>
                <h1 id="resetTitle">ลืมรหัสผ่าน?</h1>
                <p>กรอกอีเมลที่ใช้สมัคร ระบบจะส่ง OTP สำหรับยืนยันตัวตน</p>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="reset-alert error" role="alert">
                    <?php foreach ($errors as $error): ?><p><?= $escape((string)$error) ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($messages): ?>
                <div class="reset-alert success" role="status">
                    <?php foreach ($messages as $message): ?><p><?= $escape((string)$message) ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($stage === 'verify'): ?>
                <form method="post" class="reset-form">
                    <input type="hidden" name="_csrf" value="<?= $escape(csrfToken()) ?>">
                    <input type="hidden" name="action" value="verify">
                    <label for="otp">รหัส OTP</label>
                    <input id="otp" class="otp-input" name="otp" type="text" inputmode="numeric"
                        pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus>
                    <button type="submit">ยืนยัน OTP</button>
                </form>
                <form method="post" class="reset-secondary-form">
                    <input type="hidden" name="_csrf" value="<?= $escape(csrfToken()) ?>">
                    <input type="hidden" name="action" value="resend">
                    <input type="hidden" name="email" value="<?= $escape($email) ?>">
                    <button type="submit">ส่ง OTP อีกครั้ง</button>
                </form>
            <?php elseif ($stage === 'reset'): ?>
                <form method="post" class="reset-form">
                    <input type="hidden" name="_csrf" value="<?= $escape(csrfToken()) ?>">
                    <input type="hidden" name="action" value="reset">
                    <label for="password">รหัสผ่านใหม่</label>
                    <input id="password" name="password" type="password" minlength="8"
                        autocomplete="new-password" required autofocus>
                    <label for="confirmPassword">ยืนยันรหัสผ่านใหม่</label>
                    <input id="confirmPassword" name="confirm_password" type="password" minlength="8"
                        autocomplete="new-password" required>
                    <button type="submit">บันทึกรหัสผ่านใหม่</button>
                </form>
            <?php elseif ($stage === 'complete'): ?>
                <a class="reset-login-link" href="/login">กลับไปเข้าสู่ระบบ</a>
            <?php else: ?>
                <form method="post" class="reset-form">
                    <input type="hidden" name="_csrf" value="<?= $escape(csrfToken()) ?>">
                    <input type="hidden" name="action" value="request">
                    <label for="email">อีเมลที่ใช้สมัคร</label>
                    <input id="email" name="email" type="email" value="<?= $escape($email) ?>"
                        autocomplete="email" required autofocus>
                    <button type="submit">ส่งรหัส OTP</button>
                </form>
            <?php endif; ?>

            <?php if ($stage !== 'complete'): ?>
                <a class="reset-back-link" href="/login"><i class="bx bx-left-arrow-alt"></i> กลับหน้าเข้าสู่ระบบ</a>
                <?php if ($stage !== 'request'): ?>
                    <a class="reset-back-link" href="/forgot-password?restart=1">ใช้อีเมลอื่น</a>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
