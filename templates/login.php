<?php
$old = $old ?? [];
$errors = $errors ?? [];
$success = $success ?? false;
$rememberChecked = (bool)($rememberChecked ?? false);

$oldValue = static fn(string $key): string => htmlspecialchars((string)($old[$key] ?? ''), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ | Badomen</title>

    <link rel="stylesheet" href="/style/app.css">
    <link rel="stylesheet" href="/style/login.css?v=4">

    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet"></noscript>
</head>

<body>
    <?php require __DIR__ . '/header.php'; ?>

    <header class="legacy-header" hidden>
        <div class="header-inner">
            <div class="logo">
                <a href="/">
                    <img src="/mylogo1.png" alt="Badomen Logo">
                </a>
            </div>

            <nav class="nav" aria-label="Main navigation">
                <a class="login" href="/home">หน้าหลัก</a>
                <a class="register" href="/register">ลงทะเบียนฟรี</a>
            </nav>
        </div>
    </header>

    <main class="login-page">
        <section class="login-shell" aria-label="Login section">

            <div class="login-art">
                <div class="login-art-content">
                    <h1>
                        เจอกิจกรรมที่ใช่<br>
                        <span>ในแบบของคุณ.</span>
                    </h1>

                    <p>
                        เข้าสู่ระบบเพื่อค้นหากิจกรรมที่ตรงกับความสนใจ
                        เก็บรายการโปรดไว้ดูภายหลัง และติดตามทุกโอกาสใหม่ ๆ ได้ง่ายขึ้นบน Badomen
                    </p>
                </div>

                <span class="login-spark s1">✦</span>
                <span class="login-spark s2">✦</span>
                <span class="login-spark s3">✦</span>

                <div class="login-mascot-wrap">
                    <img class="login-mascot" src="/assets/login.webp" alt="Event mascot" width="1024" height="1024" decoding="async" fetchpriority="high">
                </div>
            </div>

            <div class="login-form-panel">
                <div class="login-form-inner">

                    <div class="login-heading">
                        <div class="login-icon">
                            <i class='bx bx-lock-alt'></i>
                        </div>

                        <h2 class="login-title">Login</h2>
                    </div>

                    <p class="login-desc">
                        กลับเข้าสู่พื้นที่ของคุณเพื่อสำรวจกิจกรรมใหม่ ๆ<br>
                        จัดการรายการโปรด และไม่พลาดโอกาสดี ๆ ที่เหมาะกับไลฟ์สไตล์ของคุณ
                    </p>

                    <?php if (!empty($errors)): ?>
                        <div class="login-alert" role="alert">
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="login-form" autocomplete="on" data-login-form>
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="guest_favorite_ids" data-guest-favorites>

                        <div class="form-group">
                            <label for="email" class="form-label">อีเมล</label>
                            <div class="input-wrap">
                                <i class='bx bx-envelope'></i>
                                <input
                                    id="email"
                                    name="email"
                                    type="email"
                                    value="<?= $oldValue('email') ?>"
                                    placeholder="กรอกอีเมลของคุณ"
                                    autocomplete="username email"
                                    inputmode="email"
                                    required>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="form-row-between">
                                <label for="password" class="form-label">รหัสผ่าน</label>
                            </div>

                            <div class="input-wrap">
                                <i class='bx bx-lock-alt'></i>
                                <input
                                    id="password"
                                    name="password"
                                    type="password"
                                    placeholder="กรอกรหัสผ่านของคุณ"
                                    autocomplete="current-password"
                                    required>

                                <button class="password-toggle" type="button" aria-label="แสดงหรือซ่อนรหัสผ่าน">
                                    <i class='bx bx-hide'></i>
                                </button>
                            </div>
                        </div>

                        <div class="login-options">
                            <label class="remember">
                                <input type="checkbox" name="remember" value="1"<?= $rememberChecked ? ' checked' : '' ?>>
                                <span>จดจำการเข้าสู่ระบบ</span>
                            </label>

                            <a class="forgot-link" href="/forgot-password">ลืมรหัสผ่าน?</a>
                        </div>

                        <p class="remember-hint">
                            ระบบจะให้ browser จำรหัสผ่านได้ตามปกติ และถ้าเลือกจดจำการเข้าสู่ระบบ ระบบจะใช้ token cookie แทนการเก็บรหัสผ่านจริง
                        </p>

                        <button type="submit" class="submit-btn">
                            <span>เข้าสู่ระบบ</span>
                            <i class='bx bx-right-arrow-alt'></i>
                        </button>

                        <div class="login-divider">
                            <span>หรือ</span>
                        </div>

                        <div class="register-cta">
                            ยังไม่มีบัญชี?
                            <a href="/register">สมัครสมาชิก</a>
                        </div>

                    </form>
                </div>
            </div>

        </section>
    </main>

    <script>
        const guestFavoritesInput = document.querySelector('[data-guest-favorites]');
        if (guestFavoritesInput) {
            guestFavoritesInput.value = localStorage.getItem('badomen_saved_events') || '[]';
        }

        const passwordInput = document.getElementById('password');
        const toggleBtn = document.querySelector('.password-toggle');
        const toggleIcon = toggleBtn?.querySelector('i');

        toggleBtn?.addEventListener('click', () => {
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';

            toggleIcon.className = isPassword ? 'bx bx-show' : 'bx bx-hide';
        });
    </script>

</body>

</html>
