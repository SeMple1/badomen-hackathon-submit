<?php
$user = $user ?? null;
$old = $old ?? [];
$errors = $errors ?? [];
$successes = $successes ?? [];

if (!$user) {
    header('Location: ' . appUrl('/home_in'));
    exit;
}

$escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');

$valueOf = static function (string $key) use ($old, $user): string {
    return (string)($old[$key] ?? $user[$key] ?? '');
};

$displayName = $valueOf('name');
$displayEmail = (string)($user['email'] ?? '');
$displayAge = $valueOf('age');
$selectedGender = $valueOf('gender');
$selectedCareer = $valueOf('career');
$displayPhone = $valueOf('phone');
$displayBio = $valueOf('bio');
$avatarPath = trim((string)($user['avatar_path'] ?? ''));
$avatarUrl = $avatarPath !== '' ? '/' . ltrim(str_replace('\\', '/', $avatarPath), '/') : '';
$memberRank = (string)($user['member_rank'] ?? 'member');
$isGold = $memberRank === 'gold' && (empty($user['vip_expires_at']) || strtotime((string)$user['vip_expires_at']) >= time());
$rankLabel = $isGold ? 'Gold VIP' : 'Member';
$notificationEmail = $valueOf('notification_email') !== '0';
$notificationWeb = $valueOf('notification_web') !== '0';
$createdAt = (string)($user['created_at'] ?? '');

$genderOptions = [
    'male' => 'ชาย',
    'female' => 'หญิง',
    'other' => 'อื่น ๆ',
];

$careerOptions = [
    'student' => 'นักเรียน/นักศึกษา',
    'employee' => 'พนักงานบริษัท',
    'business_owner' => 'เจ้าของกิจการ',
    'freelancer' => 'ฟรีแลนซ์',
    'government_officer' => 'ข้าราชการ/รัฐวิสาหกิจ',
    'other' => 'อื่น ๆ',
];

$memberSince = '-';
if ($createdAt !== '') {
    $timestamp = strtotime($createdAt);
    if ($timestamp !== false) {
        $memberSince = date('d/m/Y', $timestamp);
    }
}

$avatarBase = trim($displayName) !== '' ? trim($displayName) : 'U';
$avatarLetter = function_exists('mb_substr')
    ? mb_substr($avatarBase, 0, 1, 'UTF-8')
    : substr($avatarBase, 0, 1);

$displayGender = $genderOptions[$selectedGender] ?? '-';
$displayCareer = $careerOptions[$selectedCareer] ?? '-';
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $escape($title ?? 'แก้ไขโปรไฟล์ | Badomen') ?></title>

    <link rel="stylesheet" href="/style/app.css">
    <link rel="stylesheet" href="/style/edit_profile.css?v=3">

    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet"></noscript>
</head>

<body>
    <?php require __DIR__ . '/header.php'; ?>

    <main class="ep-page">
        <section class="ep-shell" aria-label="แก้ไขโปรไฟล์">

            <aside class="ep-hero">
                <div class="ep-hero-pattern"></div>

                <div class="ep-badge<?= $isGold ? ' ep-badge--gold' : '' ?>">
                    <i class='bx <?= $isGold ? 'bxs-crown' : 'bx-user-pin' ?>'></i>
                    <span><?= $escape($rankLabel) ?> · Edit Profile</span>
                </div>

                <div class="ep-avatar<?= $isGold ? ' is-gold' : '' ?>">
                    <?php if ($avatarUrl !== ''): ?>
                        <img src="<?= $escape($avatarUrl) ?>" alt="รูปโปรไฟล์" class="ep-avatar-image">
                    <?php else: ?>
                        <span><?= $escape($avatarLetter) ?></span>
                    <?php endif; ?>
                </div>

                <h1><?= $escape($displayName !== '' ? $displayName : 'ผู้ใช้') ?></h1>

                <p class="ep-email">
                    <i class='bx bx-envelope'></i>
                    <?= $escape($displayEmail !== '' ? $displayEmail : '-') ?>
                </p>

                <div class="ep-member-card">
                    <span>สมาชิกตั้งแต่</span>
                    <strong><?= $escape($memberSince) ?></strong>
                </div>

                <div class="ep-preview-list">
                    <div class="ep-preview-item">
                        <i class='bx bx-cake'></i>
                        <div>
                            <span>อายุ</span>
                            <strong><?= $escape($displayAge !== '' ? $displayAge . ' ปี' : '-') ?></strong>
                        </div>
                    </div>

                    <div class="ep-preview-item">
                        <i class='bx bx-user'></i>
                        <div>
                            <span>เพศ</span>
                            <strong><?= $escape($displayGender) ?></strong>
                        </div>
                    </div>

                    <div class="ep-preview-item">
                        <i class='bx bx-briefcase-alt-2'></i>
                        <div>
                            <span>อาชีพ</span>
                            <strong><?= $escape($displayCareer) ?></strong>
                        </div>
                    </div>
                </div>
            </aside>

            <section class="ep-content">
                <div class="ep-head">
                    <div>
                        <p class="ep-kicker">Account Settings</p>
                        <h2>แก้ไขข้อมูลส่วนตัว</h2>
                        <p class="ep-desc">
                            ปรับข้อมูลโปรไฟล์ของคุณให้ครบถ้วน เพื่อใช้กับระบบสมาชิกและการแนะนำกิจกรรม
                        </p>
                    </div>

                    <a href="/profile" class="ep-top-link">
                        <i class='bx bx-id-card'></i>
                        <span>ดูโปรไฟล์</span>
                    </a>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="ep-alert ep-alert-error" role="alert">
                        <div class="ep-alert-icon">
                            <i class='bx bx-error-circle'></i>
                        </div>
                        <div>
                            <strong>ไม่สามารถบันทึกข้อมูลได้</strong>
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?= $escape($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($successes)): ?>
                    <div class="ep-alert ep-alert-success" role="status">
                        <div class="ep-alert-icon">
                            <i class='bx bx-check-circle'></i>
                        </div>
                        <div>
                            <strong>สำเร็จ</strong>
                            <ul>
                                <?php foreach ($successes as $success): ?>
                                    <li><?= $escape($success) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="/edit_profile" class="ep-form" enctype="multipart/form-data">
                    <input type="hidden" name="_csrf" value="<?= $escape(csrfToken()) ?>">

                    <div class="ep-avatar-field">
                        <div class="ep-avatar-preview<?= $isGold ? ' is-gold' : '' ?>" data-avatar-preview>
                            <?php if ($avatarUrl !== ''): ?>
                                <img src="<?= $escape($avatarUrl) ?>" alt="ตัวอย่างรูปโปรไฟล์">
                            <?php else: ?>
                                <span><?= $escape($avatarLetter) ?></span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label for="avatar" class="ep-avatar-upload-btn">
                                <i class='bx bx-image-add'></i>
                                <span>อัปโหลดรูปโปรไฟล์</span>
                            </label>
                            <input id="avatar" type="file" name="avatar" accept="image/jpeg,image/png,image/webp" data-avatar-input>
                            <small>รองรับ JPG, PNG, WEBP ขนาดไม่เกิน 2MB รูปจะแสดงแทนตัวอักษรโปรไฟล์ทันทีหลังบันทึก</small>
                        </div>
                    </div>

                    <div class="ep-section-title">
                        <i class='bx bx-edit-alt'></i>
                        <div>
                            <strong>ข้อมูลบัญชี</strong>
                            <span>ข้อมูลที่แสดงบนหน้าโปรไฟล์ของคุณ</span>
                        </div>
                    </div>

                    <div class="ep-field">
                        <label for="name">
                            <i class='bx bx-id-card'></i>
                            ชื่อ - นามสกุล
                        </label>
                        <input
                            type="text"
                            id="name"
                            name="name"
                            value="<?= $escape($displayName) ?>"
                            placeholder="กรอกชื่อ - นามสกุล"
                            autocomplete="name"
                            required>
                    </div>

                    <div class="ep-field">
                        <label for="email">
                            <i class='bx bx-envelope-open'></i>
                            อีเมล
                        </label>
                        <input
                            type="email"
                            id="email"
                            value="<?= $escape($displayEmail) ?>"
                            readonly
                            class="is-readonly">
                        <small>อีเมลใช้สำหรับระบุตัวตนบัญชี จึงไม่เปิดให้แก้ไขจากหน้านี้</small>
                    </div>

                    <div class="ep-field">
                        <label for="phone"><i class='bx bx-phone'></i> เบอร์โทรศัพท์</label>
                        <input type="tel" id="phone" name="phone" maxlength="32"
                            value="<?= $escape($displayPhone) ?>" placeholder="เช่น 0812345678" autocomplete="tel">
                    </div>

                    <div class="ep-row">
                        <div class="ep-field">
                            <label for="age">
                                <i class='bx bx-cake'></i>
                                อายุ
                            </label>
                            <input
                                type="number"
                                id="age"
                                name="age"
                                min="1"
                                max="120"
                                value="<?= $escape($displayAge) ?>"
                                placeholder="เช่น 20"
                                required>
                        </div>

                        <div class="ep-field">
                            <label for="gender">
                                <i class='bx bx-user'></i>
                                เพศ
                            </label>
                            <select id="gender" name="gender" required>
                                <option value="">เลือกเพศ</option>
                                <?php foreach ($genderOptions as $value => $label): ?>
                                    <option value="<?= $escape($value) ?>" <?= $selectedGender === $value ? 'selected' : '' ?>>
                                        <?= $escape($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="ep-field">
                        <label for="career">
                            <i class='bx bx-briefcase-alt-2'></i>
                            อาชีพ
                        </label>
                        <select id="career" name="career" required>
                            <option value="">เลือกอาชีพ</option>
                            <?php foreach ($careerOptions as $value => $label): ?>
                                <option value="<?= $escape($value) ?>" <?= $selectedCareer === $value ? 'selected' : '' ?>>
                                    <?= $escape($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ep-field">
                        <label for="bio"><i class='bx bx-message-square-detail'></i> แนะนำตัว</label>
                        <textarea id="bio" name="bio" maxlength="500" rows="4" placeholder="เล่าเกี่ยวกับตัวคุณแบบสั้น ๆ"><?= $escape($displayBio) ?></textarea>
                        <small>สูงสุด 500 ตัวอักษร</small>
                    </div>

                    <div class="ep-notification-grid">
                        <label class="ep-toggle-card">
                            <input type="checkbox" name="notification_email" value="1" <?= $notificationEmail ? 'checked' : '' ?>>
                            <span class="ep-toggle-icon"><i class='bx bx-envelope'></i></span>
                            <span><strong>แจ้งเตือนผ่าน Gmail</strong><small>รับข่าวการสมัครและแจ้งเตือนกิจกรรม</small></span>
                        </label>
                        <label class="ep-toggle-card">
                            <input type="checkbox" name="notification_web" value="1" <?= $notificationWeb ? 'checked' : '' ?>>
                            <span class="ep-toggle-icon"><i class='bx bx-bell'></i></span>
                            <span><strong>แจ้งเตือนบนเว็บไซต์</strong><small>แสดงรายการในศูนย์แจ้งเตือน</small></span>
                        </label>
                    </div>

                    <div class="ep-actions">
                        <a href="/home_in" class="ep-btn ep-btn-light">
                            <i class='bx bx-left-arrow-alt'></i>
                            <span>กลับหน้าแรก</span>
                        </a>

                        <a href="/profile" class="ep-btn ep-btn-ghost">
                            <i class='bx bx-x'></i>
                            <span>ยกเลิก</span>
                        </a>

                        <button type="submit" class="ep-btn ep-btn-primary">
                            <span>บันทึกการแก้ไข</span>
                            <i class='bx bx-save'></i>
                        </button>
                    </div>
                </form>
            </section>

        </section>
    </main>
    <script>
        (() => {
            const input = document.querySelector('[data-avatar-input]');
            const preview = document.querySelector('[data-avatar-preview]');
            if (!input || !preview) return;

            input.addEventListener('change', () => {
                const file = input.files && input.files[0];
                if (!file || !file.type.startsWith('image/')) return;

                const url = URL.createObjectURL(file);
                preview.innerHTML = `<img src="${url}" alt="ตัวอย่างรูปโปรไฟล์">`;
            });
        })();
    </script>
</body>

</html>
