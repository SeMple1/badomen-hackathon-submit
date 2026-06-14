<?php
$user = $user ?? null;
$stats = is_array($stats ?? null) ? $stats : [];
$reviews = is_array($reviews ?? null) ? $reviews : [];
$successes = is_array($successes ?? null) ? $successes : [];

if (!$user) {
    header('Location: ' . appUrl('/home_in'));
    exit;
}

$escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');

$genderMap = [
    'male' => 'ชาย',
    'female' => 'หญิง',
    'other' => 'อื่น ๆ',
];

$careerMap = [
    'student' => 'นักเรียน/นักศึกษา',
    'employee' => 'พนักงานบริษัท',
    'business_owner' => 'เจ้าของกิจการ',
    'freelancer' => 'ฟรีแลนซ์',
    'government_officer' => 'ข้าราชการ/รัฐวิสาหกิจ',
    'other' => 'อื่น ๆ',
];

$displayName = (string)($user['name'] ?? '-');
$displayEmail = (string)($user['email'] ?? '-');
$displayAge = (string)($user['age'] ?? '-');
$displayGender = $genderMap[(string)($user['gender'] ?? '')] ?? '-';
$displayCareer = $careerMap[(string)($user['career'] ?? '')] ?? '-';
$displayPhone = trim((string)($user['phone'] ?? ''));
$displayBio = trim((string)($user['bio'] ?? ''));
$avatarPath = trim((string)($user['avatar_path'] ?? ''));
$memberRank = (string)($user['member_rank'] ?? 'member');
$isGold = $memberRank === 'gold' && (empty($user['vip_expires_at']) || strtotime((string)$user['vip_expires_at']) >= time());
$rankLabel = $isGold ? 'Gold VIP' : 'Member';
$vipExpiresAt = trim((string)($user['vip_expires_at'] ?? ''));
$vipExpiresDisplay = '-';
if ($vipExpiresAt !== '') {
    $vipTimestamp = strtotime($vipExpiresAt);
    if ($vipTimestamp !== false) {
        $vipExpiresDisplay = date('d/m/Y H:i', $vipTimestamp);
    }
}
$createdAt = (string)($user['created_at'] ?? '');

$memberSince = '-';
if ($createdAt !== '') {
    $timestamp = strtotime($createdAt);
    if ($timestamp !== false) {
        $memberSince = date('d/m/Y', $timestamp);
    }
}

$profileInitial = $displayName !== '' ? mb_strtoupper(mb_substr($displayName, 0, 1, 'UTF-8'), 'UTF-8') : 'B';
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>โปรไฟล์ของฉัน | Badomen</title>

    <link rel="stylesheet" href="/style/app.css">
    <link rel="stylesheet" href="/style/profile.css?v=2">

    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet"></noscript>
</head>

<body>
    <?php require __DIR__ . '/header.php'; ?>

    <main class="profile-page">
        <section class="profile-shell<?= $isGold ? ' is-gold' : '' ?>" aria-label="Profile section">

            <div class="profile-hero">
                <div class="profile-hero-bg"></div>

                <div class="profile-badge<?= $isGold ? ' profile-badge--gold' : '' ?>">
                    <i class='bx <?= $isGold ? 'bxs-crown' : 'bx-user-check' ?>'></i>
                    <span><?= $escape($rankLabel) ?> Profile</span>
                </div>

                <div class="profile-avatar-wrap">
                    <?php if ($avatarPath !== ''): ?>
                        <img class="profile-avatar profile-avatar-image" src="<?= $escape('/' . ltrim(str_replace('\\', '/', $avatarPath), '/')) ?>" alt="รูปโปรไฟล์">
                    <?php else: ?>
                        <span class="profile-avatar" aria-label="อักษรย่อโปรไฟล์"><?= $escape($profileInitial) ?></span>
                    <?php endif; ?>
                </div>

                <h1><?= $escape($displayName) ?></h1>

                <p class="profile-email">
                    <i class='bx bx-envelope'></i>
                    <?= $escape($displayEmail) ?>
                </p>

                <div class="profile-member">
                    <span>สมาชิกตั้งแต่</span>
                    <strong><?= $escape($memberSince) ?></strong>
                </div>

                <div class="profile-hero-note">

                    <p>
                        จัดการข้อมูลส่วนตัวของคุณ เพื่อให้ Badomen แนะนำกิจกรรม
                        และประสบการณ์ที่เหมาะกับคุณได้ดียิ่งขึ้น
                    </p>
                </div>
            </div>

            <div class="profile-content">

                <div class="profile-head">
                    <div>
                        <p class="profile-kicker">Account overview</p>
                        <h2>ข้อมูลโปรไฟล์</h2>
                    </div>
                    <div class="profile-head-actions">
                        <a href="/edit_profile" class="edit-btn profile-edit-top">
                            <i class='bx bx-edit'></i>
                            <span>แก้ไขโปรไฟล์</span>
                        </a>
                        <button type="button" class="edit-btn profile-review-open" id="profileReviewsOpen">
                            <i class='bx bx-star'></i>
                            <span>รีวิวของฉัน (<?= number_format(count($reviews)) ?>)</span>
                        </button>
                    </div>
                </div>

                <?php if (!empty($successes)): ?>
                    <div class="profile-alert profile-alert-success" role="status">
                        <?php foreach ($successes as $success): ?>
                            <p><?= $escape((string)$success) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="profile-summary">
                    <a class="summary-card summary-card-link summary-card-rank<?= $isGold ? ' is-gold' : '' ?>" href="/vip">
                        <div class="summary-icon">
                            <i class='bx <?= $isGold ? 'bxs-crown' : 'bx-calendar-star' ?>'></i>
                        </div>
                        <div>
                            <span>ยศผู้ใช้</span>
                            <strong><?= $escape($rankLabel) ?></strong>
                        </div>
                    </a>

                    <div class="summary-card">
                        <div class="summary-icon">
                            <i class='bx bx-time-five'></i>
                        </div>
                        <div>
                            <span>เริ่มใช้งาน</span>
                            <strong><?= $escape($memberSince) ?></strong>
                        </div>
                    </div>

                    <a class="summary-card summary-card-link" href="/home_in?favorites=1">
                        <div class="summary-icon"><i class='bx bxs-heart'></i></div>
                        <div>
                            <span>รายการโปรด</span>
                            <strong><?= number_format((int)($stats['favorites'] ?? 0)) ?> กิจกรรม</strong>
                        </div>
                    </a>
                </div>

                <div class="profile-stat-strip">
                    <div><strong><?= number_format((int)($stats['joined'] ?? 0)) ?></strong><span>กิจกรรมที่เข้าร่วม</span></div>
                    <div><strong><?= number_format((int)($stats['created'] ?? 0)) ?></strong><span>กิจกรรมที่สร้าง</span></div>
                    <div><strong><?= $escape($rankLabel) ?></strong><span>ยศสมาชิก</span></div>
                </div>

                <div class="profile-info-list">

                    <article class="profile-info-card wide">
                        <div class="info-icon">
                            <i class='bx bx-id-card'></i>
                        </div>
                        <div class="info-text">
                            <span>ชื่อ - นามสกุล</span>
                            <strong><?= $escape($displayName) ?></strong>
                        </div>
                    </article>

                    <article class="profile-info-card wide">
                        <div class="info-icon"><i class='bx bx-phone'></i></div>
                        <div class="info-text">
                            <span>เบอร์โทรศัพท์</span>
                            <strong><?= $escape($displayPhone !== '' ? $displayPhone : 'ยังไม่ได้ระบุ') ?></strong>
                        </div>
                    </article>

                    <article class="profile-info-card wide profile-bio-card">
                        <div class="info-icon"><i class='bx bx-message-square-detail'></i></div>
                        <div class="info-text">
                            <span>แนะนำตัว</span>
                            <strong><?= $escape($displayBio !== '' ? $displayBio : 'ยังไม่ได้เขียนแนะนำตัว') ?></strong>
                        </div>
                    </article>

                    <article class="profile-info-card wide profile-rank-card<?= $isGold ? ' is-gold' : '' ?>">
                        <div class="info-icon"><i class='bx <?= $isGold ? 'bxs-crown' : 'bx-id-card' ?>'></i></div>
                        <div class="info-text">
                            <span>Membership</span>
                            <strong><?= $escape($rankLabel) ?><?= $isGold && $vipExpiresDisplay !== '-' ? ' · หมดอายุ ' . $escape($vipExpiresDisplay) : '' ?></strong>
                        </div>
                    </article>

                    <article class="profile-info-card wide">
                        <div class="info-icon">
                            <i class='bx bx-envelope-open'></i>
                        </div>
                        <div class="info-text">
                            <span>อีเมล</span>
                            <strong><?= $escape($displayEmail) ?></strong>
                        </div>
                    </article>

                    <div class="profile-info-row">
                        <article class="profile-info-card">
                            <div class="info-icon">
                                <i class='bx bx-cake'></i>
                            </div>
                            <div class="info-text">
                                <span>อายุ</span>
                                <strong><?= $escape($displayAge) ?> ปี</strong>
                            </div>
                        </article>

                        <article class="profile-info-card">
                            <div class="info-icon">
                                <i class='bx bx-user'></i>
                            </div>
                            <div class="info-text">
                                <span>เพศ</span>
                                <strong><?= $escape($displayGender) ?></strong>
                            </div>
                        </article>
                    </div>

                    <article class="profile-info-card wide">
                        <div class="info-icon">
                            <i class='bx bx-briefcase-alt-2'></i>
                        </div>
                        <div class="info-text">
                            <span>อาชีพ</span>
                            <strong><?= $escape($displayCareer) ?></strong>
                        </div>
                    </article>

                </div>

                <div class="profile-actions">
                    <a href="/home_in" class="secondary-btn">
                        <i class='bx bx-left-arrow-alt'></i>
                        <span>กลับหน้าแรก</span>
                    </a>

                    <a href="/vip" class="secondary-btn">
                        <i class='bx bxs-crown'></i>
                        <span><?= $isGold ? 'จัดการ VIP' : 'สมัคร Gold VIP' ?></span>
                    </a>

                    <a href="/edit_profile" class="primary-btn">
                        <span>แก้ไขข้อมูลส่วนตัว</span>
                        <i class='bx bx-right-arrow-alt'></i>
                    </a>
                </div>

            </div>

        </section>
    </main>

    <div class="profile-review-modal" id="profileReviewModal" aria-hidden="true">
        <div class="profile-review-modal__backdrop" data-profile-review-close></div>
        <section class="profile-review-modal__panel" role="dialog" aria-modal="true" aria-labelledby="profileReviewTitle">
            <button type="button" class="profile-review-modal__close" data-profile-review-close aria-label="ปิดรีวิวของฉัน"><i class="bx bx-x"></i></button>
            <span class="profile-kicker">MY EVENT REVIEWS</span>
            <h2 id="profileReviewTitle">รีวิวกิจกรรมที่ฉันเคยเขียน</h2>
            <p>รวมคะแนนและความคิดเห็นที่คุณส่งให้แต่ละกิจกรรม</p>
            <div class="profile-review-list">
                <?php if (empty($reviews)): ?>
                    <div class="profile-review-empty"><i class="bx bx-message-square-x"></i><strong>ยังไม่มีรีวิว</strong><span>หลังเข้าร่วมกิจกรรมแล้ว รีวิวของคุณจะแสดงที่นี่</span></div>
                <?php else: ?>
                    <?php foreach ($reviews as $review): ?>
                        <article class="profile-review-card">
                            <div class="profile-review-card__head">
                                <div><strong><?= $escape((string)($review['title'] ?? '-')) ?></strong><span><?= $escape((string)($review['location'] ?? '-')) ?></span></div>
                                <span class="profile-review-stars"><?= str_repeat('★', max(0, min(5, (int)($review['rating'] ?? 0)))) ?><?= str_repeat('☆', max(0, 5 - (int)($review['rating'] ?? 0))) ?></span>
                            </div>
                            <p><?= $escape(trim((string)($review['comment'] ?? '')) !== '' ? (string)$review['comment'] : 'ไม่ได้ระบุความคิดเห็นเพิ่มเติม') ?></p>
                            <footer>
                                <span>กิจกรรม: <?= $escape(date('d/m/Y H:i', strtotime((string)($review['event_start'] ?? 'now')))) ?></span>
                                <time>รีวิวเมื่อ <?= $escape(date('d/m/Y H:i', strtotime((string)($review['updated_at'] ?? 'now')))) ?></time>
                            </footer>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>
    <script>
    (() => {
        const modal = document.getElementById('profileReviewModal');
        const openButton = document.getElementById('profileReviewsOpen');
        if (!modal || !openButton) return;
        const close = () => {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('profile-review-open');
            openButton.focus({ preventScroll: true });
        };
        openButton.addEventListener('click', () => {
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('profile-review-open');
            modal.querySelector('[data-profile-review-close]')?.focus();
        });
        modal.querySelectorAll('[data-profile-review-close]').forEach((button) => button.addEventListener('click', close));
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && modal.classList.contains('is-open')) close();
        });
    })();
    </script>
</body>

</html>
