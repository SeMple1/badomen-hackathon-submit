<?php
$old = $old ?? [];
$errors = $errors ?? [];
$success = $success ?? false;

$oldValue = static fn(string $key): string => htmlspecialchars((string)($old[$key] ?? ''), ENT_QUOTES, 'UTF-8');
$selected = static fn(string $key, string $value): string => (($old[$key] ?? '') === $value) ? 'selected' : '';
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก | Badomen</title>

    <link rel="stylesheet" href="/style/app.css">
    <link rel="stylesheet" href="/style/register.css?v=11">

    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet"></noscript>
</head>

<body class="register-page">
    <?php require __DIR__ . '/header.php'; ?>

    <header class="legacy-header" hidden>
        <div class="header-inner">
            <div class="logo">
                <a href="/" aria-label="กลับหน้าหลัก">
                    <img src="/mylogo1.png" alt="Badomen Logo">
                </a>
            </div>

            <nav class="nav" aria-label="Main navigation">
                <a class="login" href="/home">หน้าหลัก</a>
                <a class="register" href="/login">เข้าสู่ระบบ</a>
            </nav>
        </div>
    </header>

    <main class="register-main">
        <section class="register-shell" aria-label="ฟอร์มสมัครสมาชิก">

            <aside class="register-visual" aria-hidden="true">
                <div class="visual-copy">
                    <h1>
                        เริ่มต้นค้นหา<br>
                        <strong>กิจกรรมที่ใช่.</strong>
                    </h1>

                    <p>
                        สมัครบัญชีเพื่อค้นหากิจกรรม สมัครเข้าร่วม
                        บันทึกกิจกรรมที่สนใจ และติดตามสถานะของคุณ
                    </p>
                </div>
            </aside>

            <section class="register-panel">
                <div class="register-inner">

                    
                    <div class="register-head">
                        <div class="register-title-row">
                            <div class="register-icon">
                                <i class='bx bx-user-plus'></i>
                            </div>

                            <h2>สมัครสมาชิก</h2>
                        </div>

                        <p class="register-subtitle">
                            กรอกข้อมูลให้ครบถ้วนเพื่อสร้างบัญชีสำหรับเข้าร่วมกิจกรรม
                        </p>
                    </div>

                    <?php if ($success): ?>
                        <div class="alert alert-success" role="status">
                            สมัครสมาชิกสำเร็จแล้ว สามารถเข้าสู่ระบบได้ทันที
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-error" role="alert">
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="register-form" autocomplete="on" novalidate>
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="guest_favorite_ids" data-guest-favorites>

                        <div class="form-group span-full">
                            <label for="name">ชื่อ - นามสกุล</label>
                            <div class="input-wrap">
                                <i class='bx bx-user'></i>
                                <input
                                    id="name"
                                    type="text"
                                    name="name"
                                    value="<?= $oldValue('name') ?>"
                                    autocomplete="name"
                                    placeholder="กรอกชื่อของคุณ"
                                    required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="age">อายุ</label>
                                <div class="input-wrap">
                                    <i class='bx bx-calendar'></i>
                                    <input
                                        id="age"
                                        type="number"
                                        name="age"
                                        value="<?= $oldValue('age') ?>"
                                        min="1"
                                        max="120"
                                        inputmode="numeric"
                                        placeholder="เช่น 20"
                                        required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="gender">เพศ</label>
                                <div class="input-wrap select-wrap">
                                    <i class='bx bx-male-female'></i>
                                    <select id="gender" name="gender" required>
                                        <option value="">เลือกเพศ</option>
                                        <option value="male" <?= $selected('gender', 'male') ?>>ชาย</option>
                                        <option value="female" <?= $selected('gender', 'female') ?>>หญิง</option>
                                        <option value="other" <?= $selected('gender', 'other') ?>>อื่น ๆ</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group span-full">
                            <label for="career">อาชีพ</label>
                            <div class="input-wrap select-wrap">
                                <i class='bx bx-briefcase'></i>
                                <select id="career" name="career" required>
                                    <option value="">เลือกอาชีพ</option>
                                    <option value="student" <?= $selected('career', 'student') ?>>นักเรียน/นักศึกษา</option>
                                    <option value="employee" <?= $selected('career', 'employee') ?>>พนักงานบริษัท</option>
                                    <option value="business_owner" <?= $selected('career', 'business_owner') ?>>เจ้าของกิจการ</option>
                                    <option value="freelancer" <?= $selected('career', 'freelancer') ?>>ฟรีแลนซ์</option>
                                    <option value="government_officer" <?= $selected('career', 'government_officer') ?>>ข้าราชการ/รัฐวิสาหกิจ</option>
                                    <option value="other" <?= $selected('career', 'other') ?>>อื่น ๆ</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group span-full">
                            <label for="phone">เบอร์โทรศัพท์</label>
                            <div class="input-wrap">
                                <i class='bx bx-phone'></i>
                                <input
                                    id="phone"
                                    type="tel"
                                    name="phone"
                                    value="<?= $oldValue('phone') ?>"
                                    autocomplete="tel"
                                    inputmode="tel"
                                    maxlength="32"
                                    placeholder="เช่น 0812345678"
                                    required>
                            </div>
                        </div>

                        <div class="form-group span-full">
                            <label for="email">อีเมล</label>
                            <div class="input-wrap">
                                <i class='bx bx-envelope'></i>
                                <input
                                    id="email"
                                    type="email"
                                    name="email"
                                    value="<?= $oldValue('email') ?>"
                                    autocomplete="email"
                                    placeholder="กรอกอีเมลของคุณ"
                                    required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="password">รหัสผ่าน</label>
                                <div class="input-wrap">
                                    <i class='bx bx-lock-alt'></i>
                                    <input
                                        id="password"
                                        type="password"
                                        name="password"
                                        autocomplete="new-password"
                                        placeholder="อย่างน้อย 8 ตัวอักษร"
                                        required>

                                    <button
                                        class="password-toggle"
                                        type="button"
                                        data-target="password"
                                        aria-label="แสดงหรือซ่อนรหัสผ่าน">
                                        <i class='bx bx-hide'></i>
                                    </button>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password">ยืนยันรหัสผ่าน</label>
                                <div class="input-wrap">
                                    <i class='bx bx-lock-alt'></i>
                                    <input
                                        id="confirm_password"
                                        type="password"
                                        name="confirm_password"
                                        autocomplete="new-password"
                                        placeholder="กรอกรหัสผ่านอีกครั้ง"
                                        required>

                                    <button
                                        class="password-toggle"
                                        type="button"
                                        data-target="confirm_password"
                                        aria-label="แสดงหรือซ่อนยืนยันรหัสผ่าน">
                                        <i class='bx bx-hide'></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <button class="submit-btn" type="submit">
                            <span>สมัครสมาชิก</span>
                            <i class='bx bx-right-arrow-alt'></i>
                        </button>

                        <div class="form-divider">
                            <span>หรือ</span>
                        </div>

                        <div class="form-footer">
                            มีบัญชีอยู่แล้ว?
                            <a href="/login">เข้าสู่ระบบ</a>
                        </div>

                    </form>
                </div>
            </section>

        </section>
    </main>

    <script>
        const guestFavoritesInput = document.querySelector('[data-guest-favorites]');
        if (guestFavoritesInput) {
            guestFavoritesInput.value = localStorage.getItem('badomen_saved_events') || '[]';
        }

        document.querySelectorAll('.password-toggle').forEach((button) => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.target);
                const icon = button.querySelector('i');

                if (!input || !icon) return;

                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                icon.className = isPassword ? 'bx bx-show' : 'bx bx-hide';
            });
        });
    </script>

</body>

</html>
