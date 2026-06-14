<?php
$activities = is_array($activities ?? null) ? $activities : [];
$errors = is_array($errors ?? null) ? $errors : [];
$successes = is_array($successes ?? null) ? $successes : [];
$userEmail = (string)($userEmail ?? '');

$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$csrf = function_exists('csrfToken') ? csrfToken() : '';
$serverToday = date('Y-m-d');

$placeholderSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="760" viewBox="0 0 1200 760"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#0f172a"/><stop offset=".52" stop-color="#7c2d12"/><stop offset="1" stop-color="#ea580c"/></linearGradient><linearGradient id="p" x1="0" y1="0" x2="1" y2="0"><stop stop-color="#fed7aa"/><stop offset="1" stop-color="#fff7ed"/></linearGradient></defs><rect width="1200" height="760" fill="url(#g)"/><circle cx="950" cy="92" r="260" fill="#fb923c" opacity=".25"/><circle cx="130" cy="680" r="220" fill="#fed7aa" opacity=".16"/><rect x="230" y="180" width="740" height="360" rx="56" fill="#fff" opacity=".12" stroke="#fff" stroke-opacity=".24" stroke-width="5"/><path d="M310 445h580" stroke="url(#p)" stroke-width="10" stroke-linecap="round" stroke-dasharray="22 26" opacity=".72"/><text x="600" y="338" text-anchor="middle" fill="#fff" font-family="Arial, sans-serif" font-size="54" font-weight="900">BADOMEN</text><text x="600" y="404" text-anchor="middle" fill="#fed7aa" font-family="Arial, sans-serif" font-size="30" font-weight="800">MY EVENTS</text></svg>';
$placeholderImage = 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($placeholderSvg);

$normalizeImagePath = static function (?string $path) use ($placeholderImage): string {
    $path = trim((string)$path);
    if ($path === '') return $placeholderImage;
    if (preg_match('#^(https?://|data:image/)#i', $path)) return $path;
    return '/' . ltrim(str_replace('\\', '/', $path), '/');
};

$formatDate = static function (string $datetime): string {
    $ts = strtotime($datetime);
    return $ts === false ? '-' : date('d/m/Y H:i', $ts);
};

$formatDateShort = static function (string $datetime): string {
    $ts = strtotime($datetime);
    return $ts === false ? '-' : date('d M Y • H:i', $ts);
};

$formatDay = static function (string $datetime): string {
    $ts = strtotime($datetime);
    return $ts === false ? '' : date('Y-m-d', $ts);
};

$statusBadge = static function (string $status): array {
    $normalized = strtolower(trim($status));
    if ($normalized === 'checked_in') return ['ยืนยันสิทธิ์แล้ว', 'status-badge--checked-in', 'checked_in'];
    if ($normalized === 'refunded') return ['คืนเงินแล้ว', 'status-badge--refunded', 'refunded'];
    if ($normalized === 'cancelled') return ['ยกเลิกแล้ว', 'status-badge--rejected', 'cancelled'];
    if (in_array($normalized, ['approved', 'approve', 'accepted', 'confirmed'], true)) return ['อนุมัติแล้ว', 'status-badge--approved', 'approved'];
    if (in_array($normalized, ['rejected', 'reject', 'declined', 'denied'], true)) return ['ยกเลิก', 'status-badge--rejected', 'rejected'];
    return ['รออนุมัติ', 'status-badge--pending', 'pending'];
};

$nowTs = time();
$totalActivities = count($activities);
$upcomingCount = 0;
$approvedCount = 0;
$reminderCount = 0;
$nextActivity = null;
$calendarEvents = [];
$joinedEventDetails = [];

foreach ($activities as $activity) {
    $startTs = strtotime((string)($activity['event_start'] ?? ''));
    $isUpcoming = $startTs !== false && $startTs >= $nowTs;
    $status = (string)($activity['registration_status'] ?? 'pending');
    [, , $statusKey] = $statusBadge($status);

    if ($isUpcoming) {
        $upcomingCount++;
        if ($nextActivity === null || $startTs < strtotime((string)($nextActivity['event_start'] ?? '9999-12-31'))) {
            $nextActivity = $activity;
        }
    }
    if (in_array($statusKey, ['approved', 'checked_in'], true)) $approvedCount++;
    if ((int)($activity['email_reminder_enabled'] ?? 0) === 1) $reminderCount++;

    $day = $formatDay((string)($activity['event_start'] ?? ''));
    if ($day !== '') {
        $calendarEvents[] = [
            'event_id' => (int)($activity['event_id'] ?? 0),
            'title' => (string)($activity['title'] ?? ''),
            'day' => $day,
            'start_text' => $formatDateShort((string)($activity['event_start'] ?? '')),
            'end_text' => $formatDateShort((string)($activity['event_end'] ?? '')),
            'location' => (string)($activity['location'] ?? ''),
            'status_key' => $statusKey,
            'reminder_enabled' => (int)($activity['email_reminder_enabled'] ?? 0) === 1,
        ];
    }

    $joinedEventDetails[(string)(int)($activity['event_id'] ?? 0)] = [
        'event_id' => (int)($activity['event_id'] ?? 0),
        'title' => (string)($activity['title'] ?? ''),
        'description' => (string)($activity['description'] ?? ''),
        'creator_name' => (string)($activity['creator_name'] ?? ''),
        'location' => (string)($activity['location'] ?? ''),
        'latitude' => isset($activity['latitude']) ? (float)$activity['latitude'] : null,
        'longitude' => isset($activity['longitude']) ? (float)$activity['longitude'] : null,
        'event_start' => $formatDate((string)($activity['event_start'] ?? '')),
        'event_end' => $formatDate((string)($activity['event_end'] ?? '')),
        'registration_status' => $statusBadge((string)($activity['registration_status'] ?? 'pending'))[0],
        'payment_status' => (string)($activity['order_payment_status'] ?? $activity['payment_status'] ?? ''),
        'refund_status' => (string)($activity['refund_status'] ?? ''),
        'ticket_code' => (string)($activity['ticket_code'] ?? ''),
        'amount' => (float)($activity['total_amount'] ?? 0),
        'currency' => (string)($activity['currency'] ?? 'THB'),
        'review_average' => (float)($activity['review_average'] ?? 0),
        'review_count' => (int)($activity['review_count'] ?? 0),
        'my_review_rating' => (int)($activity['my_review_rating'] ?? 0),
        'my_review_comment' => (string)($activity['my_review_comment'] ?? ''),
    ];
}

$nextTitle = $nextActivity ? (string)($nextActivity['title'] ?? '-') : 'ยังไม่มีกิจกรรมถัดไป';
$calendarJson = json_encode($calendarEvents, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$joinedEventDetailsJson = json_encode($joinedEventDetails, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>กิจกรรมของฉัน | Badomen</title>
    <link rel="stylesheet" href="/style/app.css">
    <link rel="preload" as="image" href="/assets/my_activity.png?v=1" fetchpriority="high">
    <link rel="stylesheet" href="/style/join-activity.css?v=8">
    <link rel="stylesheet" href="/style/footer.css?v=2">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet"></noscript>
</head>

<body class="join-activity-page">
    <?php require __DIR__ . '/header.php'; ?>

    <main class="join-main">
        <section class="join-shell join-hero join-hero--with-bg" aria-labelledby="joinTitle" style="--ja-hero-bg-image: url('/assets/my_activity.png?v=1');">
            <div class="join-hero__copy">
                <span class="join-kicker"><i class="bx bx-calendar-star"></i> MY EVENT CONTROL</span>
                <h1 id="joinTitle">กิจกรรมของฉัน</h1>
                <p>รวมกิจกรรมที่คุณขอเข้าร่วม สถานะอนุมัติ วันเวลาที่ต้องไป ปฏิทินกิจกรรม และการแจ้งเตือนผ่าน email</p>

                <div class="join-hero__meta" aria-label="สรุปกิจกรรมของฉัน">
                    <span><i class="bx bx-layer"></i> ทั้งหมด <?= number_format($totalActivities) ?></span>
                    <button type="button" class="join-hero__meta-action" data-upcoming-filter aria-pressed="false"><i class="bx bx-time-five"></i> กิจกรรมใกล้เข้ามา <?= number_format($upcomingCount) ?></button>
                    <span><i class="bx bx-check-circle"></i> อนุมัติ <?= number_format($approvedCount) ?></span>
                    <span><i class="bx bx-envelope"></i> แจ้งเตือน <?= number_format($reminderCount) ?></span>
                </div>
            </div>

            <aside class="next-ticket" aria-label="กิจกรรมถัดไป">
                <div class="next-ticket__top">
                    <span>NEXT EVENT</span>
                    <strong><?= $nextActivity ? $escape($formatDateShort((string)$nextActivity['event_start'])) : '-' ?></strong>
                </div>
                <h2><?= $escape($nextTitle) ?></h2>
                <p><?= $nextActivity ? $escape((string)($nextActivity['location'] ?? '-')) : 'เมื่อมีกิจกรรมที่กำลังจะมาถึง ระบบจะแสดงรายการที่ใกล้ที่สุดตรงนี้' ?></p>
            </aside>
        </section>

        <section class="join-shell alert-stack" aria-live="polite">
            <?php foreach ($successes as $message): ?>
                <div class="join-alert join-alert--success"><i class="bx bx-check-circle"></i><span><?= $escape((string)$message) ?></span></div>
            <?php endforeach; ?>
            <?php foreach ($errors as $message): ?>
                <div class="join-alert join-alert--error"><i class="bx bx-error-circle"></i><span><?= $escape((string)$message) ?></span></div>
            <?php endforeach; ?>
        </section>

        <section class="join-shell join-workspace">
            <aside class="join-sidebar">
                <section class="mini-calendar-card" aria-labelledby="miniCalendarTitle">
                    <div class="mini-calendar__head">
                        <div>
                            <span class="sidebar-kicker"><i class="bx bx-calendar"></i> Calendar</span>
                            <h2 id="miniCalendarTitle">ปฏิทินกิจกรรม</h2>
                        </div>
                        <div class="calendar-nav">
                            <button id="calendarPrev" type="button" aria-label="เดือนก่อน"><i class="bx bx-chevron-left"></i></button>
                            <button id="calendarNext" type="button" aria-label="เดือนถัดไป"><i class="bx bx-chevron-right"></i></button>
                        </div>
                    </div>

                    <div id="calendarMonthLabel" class="calendar-month-label">-</div>
                    <div class="calendar-weekdays" aria-hidden="true">
                        <span>จ</span><span>อ</span><span>พ</span><span>พฤ</span><span>ศ</span><span>ส</span><span>อา</span>
                    </div>
                    <div id="miniCalendarGrid" class="mini-calendar-grid" aria-label="ปฏิทินกิจกรรมของฉัน"></div>

                    <div class="calendar-actions">
                        <button id="clearDayFilter" type="button" class="calendar-clear"><i class="bx bx-filter-alt"></i> แสดงทั้งหมด</button>
                    </div>

                    <div id="calendarDayEvents" class="calendar-day-events" aria-live="polite"></div>
                </section>

                <section class="sidebar-card">
                    <span class="sidebar-kicker"><i class="bx bx-bell"></i> Reminder</span>
                    <h2>แจ้งเตือนผ่าน email</h2>
                    <p>ระบบใช้ notification queue เดิมและ Gmail จริง โดยส่งอีเมลยืนยันทันทีเมื่อเปิดแจ้งเตือน</p>
                    <strong class="email-target"><i class="bx bx-envelope"></i> <?= $userEmail !== '' ? $escape($userEmail) : 'ยังไม่พบ email ผู้ใช้' ?></strong>
                </section>
            </aside>

            <section class="activity-panel" aria-labelledby="activityListTitle">
                <div class="activity-panel__head">
                    <div>
                        <span class="section-kicker"><i class="bx bx-list-ul"></i> Joined list</span>
                        <h2 id="activityListTitle">รายการกิจกรรมที่ต้องไป</h2>
                        <p id="activityFilterText">แสดงทุกกิจกรรมที่คุณขอเข้าร่วม</p>
                    </div>
                    <div class="activity-panel__tools" aria-label="เครื่องมือค้นหาและจัดเรียงกิจกรรม">
                        <label class="activity-search" for="activitySearch">
                            <i class="bx bx-search"></i>
                            <input id="activitySearch" type="search" placeholder="ค้นหาชื่อกิจกรรม สถานที่ หรือผู้สร้าง" autocomplete="off">
                        </label>

                        <label class="activity-sort" for="activitySort">
                            <i class="bx bx-sort-alt-2"></i>
                            <select id="activitySort">
                                <option value="upcoming">ใกล้ถึงก่อน</option>
                                <option value="newest">ใหม่ล่าสุด</option>
                                <option value="oldest">เก่าสุด</option>
                                <option value="title_asc">เรียงตามชื่อ</option>
                            </select>
                        </label>

                        <div class="status-filters" aria-label="ตัวกรองสถานะ">
                            <button type="button" class="status-filter is-active" data-status-filter="all">ทั้งหมด</button>
                            <button type="button" class="status-filter" data-status-filter="approved">อนุมัติ</button>
                            <button type="button" class="status-filter" data-status-filter="pending">รออนุมัติ</button>
                            <button type="button" class="status-filter" data-status-filter="checked_in">เช็กอิน</button>
                        </div>
                    </div>
                </div>

                <?php if (empty($activities)): ?>
                    <div class="empty-state">
                        <i class="bx bx-calendar-x"></i>
                        <h3>ยังไม่มีกิจกรรมที่คุณขอเข้าร่วม</h3>
                        <p>เมื่อคุณกดเข้าร่วมกิจกรรม ระบบจะแสดงรายการพร้อมปฏิทินและปุ่มแจ้งเตือนที่หน้านี้</p>
                        <a href="/home_in" class="join-button join-button--primary"><i class="bx bx-search"></i> ค้นหากิจกรรม</a>
                    </div>
                <?php else: ?>
                    <div id="activityList" class="activity-list">
                        <div id="filteredEmptyState" class="filtered-empty-state" hidden>
                            <i class="bx bx-search-alt"></i>
                            <strong>ไม่พบกิจกรรมที่ตรงกับเงื่อนไข</strong>
                            <span>ลองล้างคำค้นหา เปลี่ยนสถานะ หรือกด “แสดงทั้งหมด” ในปฏิทิน</span>
                        </div>
                        <?php foreach ($activities as $index => $activity): ?>
                            <?php
                            $activityId = (int)($activity['event_id'] ?? 0);
                            $imagePath = $normalizeImagePath((string)($activity['cover_image'] ?? ''));
                            $status = (string)($activity['registration_status'] ?? 'pending');
                            [$statusText, $statusClass, $statusKey] = $statusBadge($status);
                            $day = $formatDay((string)($activity['event_start'] ?? ''));
                            $reminderEnabled = (int)($activity['email_reminder_enabled'] ?? 0) === 1;
                            $paymentStatus = (string)($activity['order_payment_status'] ?? $activity['payment_status'] ?? '');
                            $refundStatus = (string)($activity['refund_status'] ?? '');
                            $amount = (float)($activity['total_amount'] ?? 0);
                            $ticketCode = trim((string)($activity['ticket_code'] ?? ''));
                            $hasTicket = $paymentStatus === 'paid' && $ticketCode !== '';
                            $isCheckedIn = $statusKey === 'checked_in';
                            $canReview = $isCheckedIn || strtotime((string)($activity['event_end'] ?? '')) < time();
                            $canRequestRefund = !$isCheckedIn
                                && $paymentStatus === 'paid'
                                && $amount > 0
                                && $refundStatus === ''
                                && strtotime((string)($activity['event_start'] ?? '')) > time();
                            $icsUrl = '/join_activity?' . http_build_query(['action' => 'ics', 'event_id' => $activityId]);
                            $copyText = trim((string)($activity['title'] ?? '') . "\n" . 'เริ่ม: ' . $formatDate((string)($activity['event_start'] ?? '')) . "\n" . 'สถานที่: ' . (string)($activity['location'] ?? ''));
                            $eventStartSort = strtotime((string)($activity['event_start'] ?? ''));
                            $eventStartSort = $eventStartSort === false ? 0 : $eventStartSort;
                            $searchText = trim(implode(' ', [
                                (string)($activity['title'] ?? ''),
                                (string)($activity['location'] ?? ''),
                                (string)($activity['creator_name'] ?? ''),
                                $statusText,
                            ]));
                            ?>
                            <article
                                id="event-<?= $activityId ?>"
                                class="activity-card"
                                data-event-card
                                data-event-id="<?= $activityId ?>"
                                data-event-day="<?= $escape($day) ?>"
                                data-event-status="<?= $escape($statusKey) ?>"
                                data-event-start="<?= $eventStartSort ?>"
                                data-event-upcoming="<?= $eventStartSort >= strtotime($serverToday . ' 00:00:00') ? '1' : '0' ?>"
                                data-event-order="<?= (int)$index ?>"
                                data-event-title="<?= $escape((string)($activity['title'] ?? '')) ?>"
                                data-event-search="<?= $escape($searchText) ?>">
                                <div class="activity-media">
                                    <img src="<?= $escape($imagePath) ?>" alt="ภาพกิจกรรม" loading="lazy" decoding="async">
                                    <span class="activity-date-chip"><i class="bx bx-calendar-event"></i> <?= $escape($formatDateShort((string)($activity['event_start'] ?? ''))) ?></span>
                                </div>

                                <div class="activity-content">
                                    <div class="activity-title-row">
                                        <div>
                                            <h3><?= $escape((string)($activity['title'] ?? '-')) ?></h3>
                                            <p>ผู้สร้าง: <?= $escape((string)($activity['creator_name'] ?? '-')) ?></p>
                                        </div>
                                        <span class="<?= $escape($statusClass) ?> activity-status"><?= $escape($statusText) ?></span>
                                    </div>

                                    <div class="activity-meta-grid">
                                        <span><i class="bx bx-map"></i><b><?= $escape((string)($activity['location'] ?? '-')) ?></b></span>
                                        <span><i class="bx bx-time-five"></i><b>เริ่ม: <?= $escape($formatDate((string)($activity['event_start'] ?? ''))) ?></b></span>
                                        <span><i class="bx bx-time"></i><b>สิ้นสุด: <?= $escape($formatDate((string)($activity['event_end'] ?? ''))) ?></b></span>
                                        <span><i class="bx bx-bell"></i><b><?= $reminderEnabled ? 'เปิดแจ้งเตือน email แล้ว' : 'ยังไม่เปิดแจ้งเตือน' ?></b></span>
                                        <span><i class="bx bx-wallet"></i><b>ชำระเงิน: <?= $escape($paymentStatus !== '' ? $paymentStatus : 'ไม่ระบุ') ?><?= $amount > 0 ? ' · ' . number_format($amount, 2) . ' ' . $escape((string)($activity['currency'] ?? 'THB')) : '' ?></b></span>
                                        <?php if ($refundStatus !== ''): ?>
                                            <span><i class="bx bx-revision"></i><b>คืนเงิน: <?= $escape($refundStatus) ?></b></span>
                                        <?php endif; ?>
                                        <?php if ((int)($activity['review_count'] ?? 0) > 0): ?>
                                            <span><i class="bx bx-star"></i><b>รีวิว <?= $escape((string)($activity['review_average'] ?? '0')) ?>/5 (<?= (int)$activity['review_count'] ?>)</b></span>
                                        <?php endif; ?>
                                        <?php if ((int)($activity['my_review_rating'] ?? 0) > 0): ?>
                                            <span><i class="bx bx-message-rounded-check"></i><b>รีวิวของคุณ <?= (int)$activity['my_review_rating'] ?> ดาว</b></span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($hasTicket): ?>
                                        <section class="issued-ticket" id="ticket-<?= (int)($activity['reg_id'] ?? 0) ?>" aria-label="บัตรเข้างาน">
                                            <div class="issued-ticket__mark">
                                                <i class="bx bx-qr-scan"></i>
                                                <span>ADMIT ONE</span>
                                            </div>
                                            <div class="issued-ticket__body">
                                                <span class="issued-ticket__eyebrow">YOUR TICKET</span>
                                                <strong><?= $escape((string)($activity['title'] ?? '-')) ?></strong>
                                                <p><?= $escape($formatDateShort((string)($activity['event_start'] ?? ''))) ?> · <?= $escape((string)($activity['location'] ?? '-')) ?></p>
                                                <code><?= $escape($ticketCode) ?></code>
                                            </div>
                                            <span class="issued-ticket__status"><i class="bx bx-check-circle"></i> ชำระแล้ว</span>
                                        </section>
                                    <?php endif; ?>

                                    <div class="activity-actions">
                                        <button type="button" class="join-button join-button--details" data-event-detail-open="<?= $activityId ?>">
                                            <i class="bx bx-map-alt"></i> ดูสถานที่และสถานะ
                                        </button>
                                        <form method="POST" action="/join_activity#event-<?= $activityId ?>" data-reminder-form>
                                            <?php if ($csrf !== ''): ?><input type="hidden" name="_csrf" value="<?= $escape($csrf) ?>"><?php endif; ?>
                                            <input type="hidden" name="action" value="toggle_email_reminder">
                                            <input type="hidden" name="event_id" value="<?= $activityId ?>">
                                            <button type="submit" class="join-button <?= $reminderEnabled ? 'join-button--muted' : 'join-button--primary' ?>" data-reminder-submit>
                                                <i class="bx <?= $reminderEnabled ? 'bx-bell-off' : 'bx-bell' ?>"></i>
                                                <?= $reminderEnabled ? 'ปิดแจ้งเตือน email' : 'แจ้งฉันด้วย email' ?>
                                            </button>
                                        </form>
                                        
                                        <form action="/userotp" method="post">
                                            <input type="hidden" name="event_id" value="<?= $activityId ?>">
                                            <button class="join-button join-button--dark"><i class="bx bx-key"></i> ขอรหัสเข้าร่วมงาน</button>
                                        </form>
                                        
                                        <?php if ($isCheckedIn): ?>
                                            <button
                                                type="button"
                                                class="join-button join-button--disabled"
                                                disabled
                                                aria-disabled="true"
                                                data-cancel-locked="1"
                                                title="ยืนยันสิทธิ์แล้ว ไม่สามารถยกเลิกกิจกรรมได้">
                                                <i class="bx bx-lock-alt"></i> ยืนยันสิทธิ์แล้ว ยกเลิกไม่ได้
                                            </button>
                                        <?php elseif ($canRequestRefund): ?>
                                            <button
                                                type="button"
                                                class="join-button join-button--refund"
                                                data-cancel-open
                                                data-cancel-event-id="<?= $activityId ?>"
                                                data-cancel-title="<?= $escape((string)($activity['title'] ?? '-')) ?>"
                                                data-cancel-action="/join_activity#event-<?= $activityId ?>">
                                                <i class="bx bx-x-circle"></i> ยกเลิกการจอง
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($canReview && $refundStatus !== 'refunded'): ?>
                                            <button
                                                type="button"
                                                class="join-button join-button--review"
                                                data-review-open
                                                data-review-registration="<?= (int)($activity['reg_id'] ?? 0) ?>"
                                                data-review-event="<?= $activityId ?>"
                                                data-review-rating="<?= (int)($activity['my_review_rating'] ?? 0) ?>"
                                                data-review-comment="<?= $escape((string)($activity['my_review_comment'] ?? '')) ?>"
                                                data-review-title="<?= $escape((string)($activity['title'] ?? '')) ?>">
                                                <i class="bx bx-star"></i>
                                                <?= !empty($activity['my_review_rating']) ? 'แก้ไขรีวิว ' . (int)$activity['my_review_rating'] . ' ดาว' : 'รีวิวกิจกรรม' ?>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </section>
    </main>

    <div class="cancel-modal" id="cancelBookingModal" aria-hidden="true">
        <div class="cancel-modal__backdrop" data-cancel-close></div>
        <section class="cancel-modal__panel" role="dialog" aria-modal="true" aria-labelledby="cancelModalTitle" tabindex="-1">
            <button type="button" class="cancel-modal__close" data-cancel-close aria-label="ปิดหน้าต่างยกเลิก">
                <i class="bx bx-x"></i>
            </button>

            <div class="cancel-modal__icon"><i class="bx bx-x-circle"></i></div>
            <span class="cancel-modal__kicker">Cancel booking</span>
            <h2 id="cancelModalTitle">เลือกเหตุผลการยกเลิก</h2>
            <p id="cancelModalEventTitle">-</p>

            <form method="POST" action="/join_activity" id="cancelBookingForm" class="cancel-modal__form">
                <?php if ($csrf !== ''): ?><input type="hidden" name="_csrf" value="<?= $escape($csrf) ?>"><?php endif; ?>
                <input type="hidden" name="action" value="request_refund">
                <input type="hidden" name="event_id" id="cancelEventId" value="">
                <input type="hidden" name="reason" id="cancelReasonValue" value="">

                <div class="cancel-reasons" role="radiogroup" aria-label="เหตุผลการยกเลิก">
                    <label><input type="radio" name="cancel_reason_choice" value="ติดธุระหรือไปไม่ได้ในวันจัดกิจกรรม" required><span>ติดธุระ / ไปไม่ได้ในวันจัดกิจกรรม</span></label>
                    <label><input type="radio" name="cancel_reason_choice" value="กรอกข้อมูลผิดหรือต้องการจองใหม่" required><span>กรอกข้อมูลผิด / ต้องการจองใหม่</span></label>
                    <label><input type="radio" name="cancel_reason_choice" value="เปลี่ยนใจหรือไม่สะดวกเข้าร่วมแล้ว" required><span>เปลี่ยนใจ / ไม่สะดวกเข้าร่วมแล้ว</span></label>
                    <label><input type="radio" name="cancel_reason_choice" value="เหตุผลด้านการชำระเงินหรือราคา" required><span>ปัญหาการชำระเงิน / ราคา</span></label>
                    <label><input type="radio" name="cancel_reason_choice" value="อื่น ๆ" required><span>อื่น ๆ</span></label>
                </div>

                <label class="cancel-note" for="cancelReasonNote">
                    <span>รายละเอียดเพิ่มเติม</span>
                    <textarea id="cancelReasonNote" name="cancel_reason_note" maxlength="500" placeholder="เพิ่มรายละเอียดเพิ่มเติม เช่น ต้องการเปลี่ยนรอบ หรือเหตุผลอื่น ๆ"></textarea>
                </label>

                <div class="cancel-modal__error" id="cancelReasonError" hidden>กรุณาเลือกเหตุผลการยกเลิกก่อนยืนยัน</div>

                <div class="cancel-modal__actions">
                    <button type="button" class="join-button join-button--ghost" data-cancel-close>กลับ</button>
                    <button type="submit" class="join-button join-button--refund"><i class="bx bx-revision"></i> ยืนยันยกเลิกและขอคืนเงิน</button>
                </div>
            </form>
        </section>
    </div>

    <div class="event-detail-modal" id="joinedEventDetailModal" aria-hidden="true">
        <div class="event-detail-modal__backdrop" data-event-detail-close></div>
        <section class="event-detail-modal__panel" role="dialog" aria-modal="true" aria-labelledby="joinedEventDetailTitle" aria-describedby="joinedEventDescription joinedEventMapHint" tabindex="-1">
            <button type="button" class="event-detail-modal__close" data-event-detail-close aria-label="ปิดรายละเอียดกิจกรรม"><i class="bx bx-x"></i></button>
            <span class="event-detail-modal__kicker">EVENT PLACE & STATUS</span>
            <h2 id="joinedEventDetailTitle">รายละเอียดกิจกรรม</h2>
            <p id="joinedEventDetailCreator"></p>
            <div id="joinedEventStatusGrid" class="event-detail-status-grid"></div>
            <p id="joinedEventDescription" class="event-detail-description"></p>
            <div class="event-detail-location"><i class="bx bx-map-pin"></i><strong id="joinedEventLocation">-</strong></div>
            <div class="event-detail-map-toolbar">
                <span id="joinedEventMapHint" class="event-detail-map-hint" hidden>ลากแผนที่เพื่อเลื่อนตำแหน่ง ใช้ scroll wheel / pinch เพื่อซูมเข้าออก</span>
                <a id="joinedEventMapLink" class="event-detail-map-link" href="#" target="_blank" rel="noopener" hidden>เปิดใน Google Maps</a>
            </div>
            <div id="joinedEventMap" class="event-detail-map" aria-label="แผนที่กิจกรรมแบบลากและซูมเข้าออกได้" hidden></div>
            <div id="joinedEventMapEmpty" class="event-detail-map-empty"><i class="bx bx-map"></i><span>ผู้จัดยังไม่ได้ปักหมุดกิจกรรมนี้</span></div>
        </section>
    </div>

    <div class="review-modal" id="joinedReviewModal" aria-hidden="true">
        <div class="review-modal__backdrop" data-review-close></div>
        <section class="review-modal__panel" role="dialog" aria-modal="true" aria-labelledby="joinedReviewTitle" tabindex="-1">
            <button type="button" class="review-modal__close" data-review-close aria-label="ปิดหน้าต่างรีวิว"><i class="bx bx-x"></i></button>
            <span class="review-modal__kicker">FEEDBACK</span>
            <h2 id="joinedReviewTitle">รีวิวกิจกรรม</h2>
            <p id="joinedReviewEventTitle">-</p>

            <form method="POST" action="/join_activity" id="joinedReviewForm" class="review-modal__form">
                <?php if ($csrf !== ''): ?><input type="hidden" name="_csrf" value="<?= $escape($csrf) ?>"><?php endif; ?>
                <input type="hidden" name="action" value="submit_review">
                <input type="hidden" name="event_id" id="reviewEventId" value="">

                <fieldset class="review-rating" aria-label="ให้คะแนนกิจกรรม">
                    <legend>ให้คะแนนกิจกรรม</legend>
                    <div class="review-stars">
                        <?php for ($star = 5; $star >= 1; $star--): ?>
                            <input id="reviewStar<?= $star ?>" type="radio" name="rating" value="<?= $star ?>" required>
                            <label for="reviewStar<?= $star ?>" title="<?= $star ?> ดาว"><i class="bx bxs-star"></i><span><?= $star ?> ดาว</span></label>
                        <?php endfor; ?>
                    </div>
                </fieldset>

                <label class="review-note" for="reviewComment">
                    <span>ความคิดเห็นเพิ่มเติม</span>
                    <textarea id="reviewComment" name="comment" maxlength="1000" rows="4" placeholder="เล่าว่ากิจกรรมนี้ใช้งานง่ายไหม สถานที่/ระบบเช็กอิน/ตั๋วเป็นอย่างไร"></textarea>
                </label>

                <div class="review-modal__error" id="reviewError" hidden>กรุณาเลือกคะแนน 1-5 ดาวก่อนบันทึก</div>

                <div class="review-modal__actions">
                    <button type="button" class="join-button join-button--ghost" data-review-close>กลับ</button>
                    <button type="submit" class="join-button join-button--review"><i class="bx bx-send"></i> บันทึกรีวิว</button>
                </div>
            </form>
        </section>
    </div>

    <script id="joinedCalendarData" type="application/json"><?= $calendarJson ?></script>
    <script id="joinedEventDetailsData" type="application/json"><?= $joinedEventDetailsJson ?></script>
    <script>
(() => {
    'use strict';

    const dataEl = document.getElementById('joinedCalendarData');
    const events = parseEvents(dataEl?.textContent || '[]');
    const grid = document.getElementById('miniCalendarGrid');
    const monthLabel = document.getElementById('calendarMonthLabel');
    const dayEvents = document.getElementById('calendarDayEvents');
    const filterText = document.getElementById('activityFilterText');
    const clearButton = document.getElementById('clearDayFilter');
    const prevButton = document.getElementById('calendarPrev');
    const nextButton = document.getElementById('calendarNext');
    const searchInput = document.getElementById('activitySearch');
    const sortSelect = document.getElementById('activitySort');
    const emptyFilteredState = document.getElementById('filteredEmptyState');
    const statusButtons = Array.from(document.querySelectorAll('[data-status-filter]'));
    const cards = Array.from(document.querySelectorAll('[data-event-card]'));
    const activityList = document.getElementById('activityList');
    const detailDataEl = document.getElementById('joinedEventDetailsData');
    const eventDetails = parseObject(detailDataEl?.textContent || '{}');
    const prefersReducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches || false;
    const LEAFLET_CSS_URL = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
    const LEAFLET_JS_URL = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
    let leafletAssetsPromise = null;
    let filterFrame = 0;
    const serverToday = <?= json_encode($serverToday, JSON_UNESCAPED_SLASHES) ?>;
    const now = parseYmd(serverToday) || new Date();
    const initialDate = pickInitialDate(events) || now;
    let visibleYear = initialDate.getFullYear();
    let visibleMonth = initialDate.getMonth();
    let selectedDay = '';
    let selectedStatus = 'all';
    let searchTerm = '';
    let selectedSort = 'upcoming';
    let onlyUpcoming = false;

    renderCalendar();
    sortCards();
    applyFilters();
    bindCopyButtons();
    bindCancelModal();
    bindEventDetailModal();
    bindReviewModal();
    restoreHeaderVisibility();
    revealHashTarget();

    prevButton?.addEventListener('click', () => {
        const date = new Date(visibleYear, visibleMonth - 1, 1);
        visibleYear = date.getFullYear();
        visibleMonth = date.getMonth();
        renderCalendar();
    });

    nextButton?.addEventListener('click', () => {
        const date = new Date(visibleYear, visibleMonth + 1, 1);
        visibleYear = date.getFullYear();
        visibleMonth = date.getMonth();
        renderCalendar();
    });

    clearButton?.addEventListener('click', () => {
        selectedDay = '';
        setUpcomingMode(false);
        renderCalendar();
        applyFilters();
    });

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-upcoming-filter]');
        if (!trigger) return;
        setUpcomingMode(!onlyUpcoming);
        selectedDay = '';
        if (onlyUpcoming) {
            selectedSort = 'upcoming';
            if (sortSelect) sortSelect.value = 'upcoming';
            sortCards();
        }
        renderCalendar();
        applyFilters();
        restoreHeaderVisibility();
        if (onlyUpcoming) animateUpcomingCards();
        smoothScrollTo(document.querySelector('.activity-panel'), 'start');
    });

    grid?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-day]');
        if (!button) return;
        selectedDay = button.dataset.day || '';
        renderCalendar();
        applyFilters();
        const firstCard = cards.find((card) => !card.classList.contains('is-hidden'));
        if (firstCard?.dataset.eventUpcoming === '1') animateUpcomingCards();
        smoothScrollTo(firstCard, 'nearest');
    });

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href^="#event-"]');
        if (!link) return;
        const target = document.querySelector(link.getAttribute('href'));
        if (!target) return;
        event.preventDefault();
        history.replaceState(null, '', link.getAttribute('href'));
        smoothScrollTo(target, 'nearest');
        markTargetCard(target);
    });

    statusButtons.forEach((button) => {
        button.addEventListener('click', () => {
            selectedStatus = button.dataset.statusFilter || 'all';
            statusButtons.forEach((btn) => btn.classList.toggle('is-active', btn === button));
            applyFilters();
        });
    });

    searchInput?.addEventListener('input', () => {
        searchTerm = normalizeText(searchInput.value);
        scheduleApplyFilters();
    });

    sortSelect?.addEventListener('change', () => {
        selectedSort = sortSelect.value || 'upcoming';
        sortCards();
        applyFilters();
    });

    window.addEventListener('pageshow', restoreHeaderVisibility);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) restoreHeaderVisibility();
    });

    function parseEvents(raw) {
        try {
            const value = JSON.parse(raw);
            return Array.isArray(value) ? value : [];
        } catch (_) {
            return [];
        }
    }

    function parseObject(raw) {
        try {
            const value = JSON.parse(raw);
            return value && typeof value === 'object' && !Array.isArray(value) ? value : {};
        } catch (_) {
            return {};
        }
    }

    function pickInitialDate(list) {
        const sorted = list
            .map((event) => event.day)
            .filter(Boolean)
            .sort();
        const today = serverToday;
        const upcoming = sorted.find((day) => day >= today);
        const target = upcoming || sorted[0];
        return target ? parseYmd(target) : null;
    }

    function toYmd(date) {
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    }

    function parseYmd(ymd) {
        const [y, m, d] = String(ymd).split('-').map(Number);
        if (!y || !m || !d) return null;
        return new Date(y, m - 1, d);
    }

    function getEventsByDay(day) {
        return events.filter((event) => event.day === day);
    }

    function renderCalendar() {
        if (!grid || !monthLabel) return;

        const formatter = new Intl.DateTimeFormat('th-TH', { month: 'long', year: 'numeric' });
        monthLabel.textContent = formatter.format(new Date(visibleYear, visibleMonth, 1));
        grid.textContent = '';

        const fragment = document.createDocumentFragment();
        const first = new Date(visibleYear, visibleMonth, 1);
        const startOffset = (first.getDay() + 6) % 7;
        const start = new Date(visibleYear, visibleMonth, 1 - startOffset);
        const today = serverToday;
        const eventDays = new Set(events.map((event) => event.day));

        for (let index = 0; index < 42; index += 1) {
            const date = new Date(start);
            date.setDate(start.getDate() + index);
            const day = toYmd(date);
            const inMonth = date.getMonth() === visibleMonth;
            const hasEvent = eventDays.has(day);
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'calendar-day';
            button.dataset.day = day;
            button.textContent = String(date.getDate());
            button.classList.toggle('is-muted', !inMonth);
            button.classList.toggle('has-event', hasEvent);
            button.classList.toggle('is-today', day === today);
            button.classList.toggle('is-selected', day === selectedDay);
            button.title = hasEvent ? `${getEventsByDay(day).length} กิจกรรม` : '';
            if (hasEvent) {
                const dot = document.createElement('i');
                dot.className = 'calendar-day__dot';
                button.appendChild(dot);
            }
            fragment.appendChild(button);
        }

        grid.appendChild(fragment);
        renderSelectedDayEvents();
    }

    function renderSelectedDayEvents() {
        if (!dayEvents) return;
        dayEvents.textContent = '';

        const targetDay = selectedDay || toYmd(new Date(visibleYear, visibleMonth, 1));
        const list = selectedDay ? getEventsByDay(targetDay) : getEventsByDay(targetDay).slice(0, 3);

        if (!selectedDay) {
            const next = events
                .filter((event) => parseYmd(event.day) >= now)
                .slice(0, 3);
            renderDayEventList(next.length ? next : events.slice(0, 3), 'กิจกรรมใกล้เข้ามา');
            return;
        }

        renderDayEventList(list, `กิจกรรมวันที่ ${formatDayLabel(selectedDay)}`);
    }

    function renderDayEventList(list, title) {
        if (!dayEvents) return;
        const isUpcomingTitle = title === 'กิจกรรมใกล้เข้ามา';
        const head = document.createElement(isUpcomingTitle ? 'button' : 'strong');
        head.className = isUpcomingTitle
            ? `calendar-day-event-title calendar-day-event-title--button${onlyUpcoming ? ' is-active' : ''}`
            : 'calendar-day-event-title';
        head.textContent = title;
        if (isUpcomingTitle) {
            head.type = 'button';
            head.setAttribute('data-upcoming-filter', '');
            head.setAttribute('aria-pressed', onlyUpcoming ? 'true' : 'false');
        }
        dayEvents.appendChild(head);

        if (!list.length) {
            const empty = document.createElement('span');
            empty.className = 'calendar-day-event';
            empty.textContent = 'ไม่มีรายการในวันนี้';
            dayEvents.appendChild(empty);
            return;
        }

        const fragment = document.createDocumentFragment();
        list.forEach((item) => {
            const link = document.createElement('a');
            link.className = 'calendar-day-event';
            link.href = `#event-${item.event_id}`;
            link.innerHTML = `<strong>${escapeHtml(item.title || '-')}</strong><span>${escapeHtml(item.start_text || '')}</span>`;
            fragment.appendChild(link);
        });
        dayEvents.appendChild(fragment);
    }

    function scheduleApplyFilters() {
        if (!window.requestAnimationFrame) {
            applyFilters();
            return;
        }
        window.cancelAnimationFrame?.(filterFrame);
        filterFrame = window.requestAnimationFrame(applyFilters);
    }

    function applyFilters() {
        let visible = 0;
        cards.forEach((card) => {
            const matchDay = !selectedDay || card.dataset.eventDay === selectedDay;
            const status = card.dataset.eventStatus || 'pending';
            const matchStatus = selectedStatus === 'all' || status === selectedStatus;
            const matchUpcoming = !onlyUpcoming || card.dataset.eventUpcoming === '1';
            const source = normalizeText(card.dataset.eventSearch || card.textContent || '');
            const matchSearch = !searchTerm || source.includes(searchTerm);
            const show = matchDay && matchStatus && matchUpcoming && matchSearch;
            card.classList.toggle('is-hidden', !show);
            if (show) visible += 1;
        });

        emptyFilteredState?.toggleAttribute('hidden', visible > 0 || cards.length === 0);

        if (filterText) {
            const parts = [];
            if (searchTerm) parts.push(`คำค้นหา “${searchInput?.value?.trim() || ''}”`);
            if (onlyUpcoming) parts.push('กิจกรรมใกล้เข้ามา');
            if (selectedDay) parts.push(`วันที่ ${formatDayLabel(selectedDay)}`);
            if (selectedStatus !== 'all') parts.push(`สถานะ ${statusText(selectedStatus)}`);
            filterText.textContent = parts.length
                ? `กำลังกรอง ${parts.join(' / ')} — พบ ${visible} รายการ`
                : 'แสดงทุกกิจกรรมที่คุณขอเข้าร่วม';
        }
    }

    function setUpcomingMode(enabled) {
        onlyUpcoming = Boolean(enabled);
        document.body.classList.toggle('is-upcoming-mode', onlyUpcoming);
        document.querySelectorAll('[data-upcoming-filter]').forEach((button) => {
            button.classList.toggle('is-active', onlyUpcoming);
            button.setAttribute('aria-pressed', onlyUpcoming ? 'true' : 'false');
        });
    }

    function animateUpcomingCards() {
        const upcomingCards = cards.filter((card) => card.dataset.eventUpcoming === '1' && !card.classList.contains('is-hidden'));
        upcomingCards.forEach((card, index) => {
            card.classList.remove('is-upcoming-highlight');
            window.setTimeout(() => card.classList.add('is-upcoming-highlight'), Math.min(index * 55, 440));
        });
        window.setTimeout(() => {
            upcomingCards.forEach((card) => card.classList.remove('is-upcoming-highlight'));
        }, 3400);
    }

    function sortCards() {
        if (!activityList) return;
        const sorted = [...cards].sort((a, b) => {
            const aStart = Number(a.dataset.eventStart || 0);
            const bStart = Number(b.dataset.eventStart || 0);
            const aOrder = Number(a.dataset.eventOrder || 0);
            const bOrder = Number(b.dataset.eventOrder || 0);
            const aTitle = normalizeText(a.dataset.eventTitle || '');
            const bTitle = normalizeText(b.dataset.eventTitle || '');

            if (selectedSort === 'newest') return (bStart - aStart) || (aOrder - bOrder);
            if (selectedSort === 'oldest') return (aStart - bStart) || (aOrder - bOrder);
            if (selectedSort === 'title_asc') return aTitle.localeCompare(bTitle, 'th') || (aStart - bStart);

            const todayDate = parseYmd(serverToday) || new Date();
            todayDate.setHours(0, 0, 0, 0);
            const todayStart = Math.floor(todayDate.getTime() / 1000);
            const aUpcoming = aStart >= todayStart;
            const bUpcoming = bStart >= todayStart;
            if (aUpcoming !== bUpcoming) return aUpcoming ? -1 : 1;
            if (aUpcoming && bUpcoming) return (aStart - bStart) || (aOrder - bOrder);
            return (bStart - aStart) || (aOrder - bOrder);
        });

        sorted.forEach((card) => activityList.appendChild(card));
    }

    function bindCopyButtons() {
        document.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-copy-event]');
            if (!button) return;
            const text = button.dataset.copyEvent || '';
            try {
                await navigator.clipboard.writeText(text);
                const old = button.innerHTML;
                button.innerHTML = '<i class="bx bx-check"></i> คัดลอกแล้ว';
                window.setTimeout(() => { button.innerHTML = old; }, 1200);
            } catch (_) {
                alert(text);
            }
        });
    }

    function bindEventDetailModal() {
        const modal = document.getElementById('joinedEventDetailModal');
        const panel = modal?.querySelector('.event-detail-modal__panel');
        const title = document.getElementById('joinedEventDetailTitle');
        const creator = document.getElementById('joinedEventDetailCreator');
        const statusGrid = document.getElementById('joinedEventStatusGrid');
        const description = document.getElementById('joinedEventDescription');
        const location = document.getElementById('joinedEventLocation');
        const mapElement = document.getElementById('joinedEventMap');
        const mapEmpty = document.getElementById('joinedEventMapEmpty');
        const mapHint = document.getElementById('joinedEventMapHint');
        const mapLink = document.getElementById('joinedEventMapLink');
        let map = null;
        let marker = null;
        let lastTrigger = null;

        if (!modal || !panel) return;

        const close = () => {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('is-event-detail-open');
            setPageInert(false);
            lastTrigger?.focus?.({ preventScroll: true });
        };

        const open = (eventId, trigger) => {
            const item = eventDetails[String(eventId)];
            if (!item) return;
            lastTrigger = trigger || document.activeElement;
            title.textContent = item.title || 'รายละเอียดกิจกรรม';
            creator.textContent = `ผู้จัด: ${item.creator_name || '-'}`;
            description.textContent = item.description || 'กิจกรรมนี้ยังไม่มีรายละเอียดเพิ่มเติม';
            location.textContent = item.location || 'ไม่ระบุสถานที่';
            statusGrid.innerHTML = [
                ['สถานะเข้าร่วม', item.registration_status || '-'],
                ['ชำระเงิน', item.payment_status || 'ไม่ระบุ'],
                ['คืนเงิน', item.refund_status || 'ไม่มี'],
                ['วันเริ่ม', item.event_start || '-'],
                ['วันสิ้นสุด', item.event_end || '-'],
                ['เลขตั๋ว', item.ticket_code || 'ยังไม่มี'],
                ['ยอดชำระ', `${Number(item.amount || 0).toLocaleString('th-TH', { minimumFractionDigits: 2 })} ${item.currency || 'THB'}`],
                ['คะแนนกิจกรรม', item.review_count > 0 ? `${item.review_average}/5 (${item.review_count} รีวิว)` : 'ยังไม่มีรีวิว']
            ].map(([label, value]) => `<div><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></div>`).join('');

            if (mapEmpty) {
                mapEmpty.innerHTML = '<i class="bx bx-map"></i><span>ผู้จัดยังไม่ได้ปักหมุดกิจกรรมนี้</span>';
            }
            const lat = Number(item.latitude);
            const lng = Number(item.longitude);
            const hasCoordinates = Number.isFinite(lat) && Number.isFinite(lng) && !(lat === 0 && lng === 0);
            mapElement.hidden = !hasCoordinates;
            mapEmpty.hidden = hasCoordinates;
            if (mapHint) mapHint.hidden = !hasCoordinates;
            if (mapLink) {
                mapLink.hidden = !hasCoordinates;
                mapLink.href = hasCoordinates ? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(`${lat},${lng}`)}` : '#';
            }

            lastTrigger?.blur?.();
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('is-event-detail-open');
            setPageInert(true);
            window.requestAnimationFrame?.(() => panel.focus({ preventScroll: true }));

            if (hasCoordinates) {
                renderLeafletMap(lat, lng, item);
            }
        };

        const renderLeafletMap = (lat, lng, item) => {
            mapElement.classList.add('is-loading');
            loadLeafletAssets()
                .then(() => {
                    mapElement.classList.remove('is-loading');
                    if (!window.L) throw new Error('Leaflet unavailable');
                    if (!map) {
                        map = L.map(mapElement, {
                            center: [lat, lng],
                            zoom: 15,
                            minZoom: 3,
                            maxZoom: 19,
                            zoomControl: true,
                            dragging: true,
                            scrollWheelZoom: true,
                            touchZoom: true,
                            doubleClickZoom: true,
                            boxZoom: true,
                            keyboard: true,
                            tap: true
                        });
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            maxZoom: 19,
                            attribution: '&copy; OpenStreetMap contributors'
                        }).addTo(map);
                        marker = L.marker([lat, lng], { keyboard: true }).addTo(map);
                    } else {
                        map.setView([lat, lng], 15, { animate: !prefersReducedMotion });
                        marker.setLatLng([lat, lng]);
                    }
                    marker.bindPopup(escapeHtml(item.location || item.title || 'สถานที่จัดกิจกรรม')).openPopup();
                    window.setTimeout(() => map.invalidateSize({ animate: false }), 80);
                })
                .catch(() => {
                    mapElement.hidden = true;
                    mapEmpty.hidden = false;
                    if (mapHint) mapHint.hidden = true;
                    mapEmpty.innerHTML = '<i class="bx bx-map"></i><span>โหลดแผนที่ไม่สำเร็จ แต่ยังเปิดตำแหน่งผ่าน Google Maps ได้</span>';
                });
        };

        document.addEventListener('click', (event) => {
            const explicit = event.target.closest('[data-event-detail-open]');
            if (explicit) {
                event.preventDefault();
                event.stopPropagation();
                open(explicit.dataset.eventDetailOpen, explicit);
                return;
            }

            const card = event.target.closest('[data-event-card]');
            if (!card || event.target.closest('button, a, form, input, textarea, select, label')) return;
            open(card.dataset.eventId, card);
        });

        document.querySelectorAll('[data-event-card]').forEach((card) => {
            card.tabIndex = 0;
            card.setAttribute('role', 'button');
            card.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    open(card.dataset.eventId, card);
                }
            });
        });

        modal.querySelectorAll('[data-event-detail-close]').forEach((button) => button.addEventListener('click', close));
        modal.addEventListener('keydown', (event) => trapModalFocus(event, modal));
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && modal.classList.contains('is-open')) close();
        });
    }

    function bindCancelModal() {
        const modal = document.getElementById('cancelBookingModal');
        const panel = modal?.querySelector('.cancel-modal__panel');
        const form = document.getElementById('cancelBookingForm');
        const eventIdInput = document.getElementById('cancelEventId');
        const titleEl = document.getElementById('cancelModalEventTitle');
        const reasonValue = document.getElementById('cancelReasonValue');
        const note = document.getElementById('cancelReasonNote');
        const error = document.getElementById('cancelReasonError');
        const closeButtons = Array.from(document.querySelectorAll('[data-cancel-close]'));
        let lastTrigger = null;

        if (!modal || !panel || !form || !eventIdInput || !reasonValue) return;

        document.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-cancel-open]');
            if (!trigger) return;
            if (trigger.disabled || trigger.getAttribute('aria-disabled') === 'true' || trigger.dataset.cancelLocked === '1') return;
            lastTrigger = trigger;
            eventIdInput.value = trigger.dataset.cancelEventId || '';
            form.action = trigger.dataset.cancelAction || '/join_activity';
            if (titleEl) titleEl.textContent = trigger.dataset.cancelTitle || '-';
            form.reset();
            reasonValue.value = '';
            error?.setAttribute('hidden', '');
            lastTrigger?.blur?.();
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('is-cancel-modal-open');
            setPageInert(true);
            window.setTimeout(() => {
                panel.focus({ preventScroll: true });
                form.querySelector('input[name="cancel_reason_choice"]')?.focus({ preventScroll: true });
            }, 30);
        });

        closeButtons.forEach((button) => button.addEventListener('click', closeModal));
        modal.addEventListener('keydown', (event) => trapModalFocus(event, modal));

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
        });

        form.addEventListener('submit', (event) => {
            const selected = form.querySelector('input[name="cancel_reason_choice"]:checked');
            const selectedText = selected?.value?.trim() || '';
            const detail = note?.value?.trim() || '';
            if (!selectedText) {
                event.preventDefault();
                error?.removeAttribute('hidden');
                form.querySelector('input[name="cancel_reason_choice"]')?.focus();
                return;
            }
            reasonValue.value = detail ? `${selectedText} — ${detail}` : selectedText;
        });

        function closeModal() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('is-cancel-modal-open');
            setPageInert(false);
            lastTrigger?.focus?.({ preventScroll: true });
        }
    }


    function bindReviewModal() {
        const modal = document.getElementById('joinedReviewModal');
        const panel = modal?.querySelector('.review-modal__panel');
        const form = document.getElementById('joinedReviewForm');
        const eventIdInput = document.getElementById('reviewEventId');
        const titleEl = document.getElementById('joinedReviewEventTitle');
        const commentEl = document.getElementById('reviewComment');
        const errorEl = document.getElementById('reviewError');
        let lastTrigger = null;

        if (!modal || !panel || !form || !eventIdInput) return;

        document.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-review-open]');
            if (!trigger) return;
            event.preventDefault();
            lastTrigger = trigger;
            const eventId = trigger.dataset.reviewEvent || '';
            const rating = Number(trigger.dataset.reviewRating || 0);
            eventIdInput.value = eventId;
            form.action = `/join_activity#event-${encodeURIComponent(eventId)}`;
            if (titleEl) titleEl.textContent = trigger.dataset.reviewTitle || '-';
            if (commentEl) commentEl.value = trigger.dataset.reviewComment || '';
            form.querySelectorAll('input[name="rating"]').forEach((input) => {
                input.checked = Number(input.value) === rating;
            });
            errorEl?.setAttribute('hidden', '');
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('is-review-modal-open');
            setPageInert(true);
            window.setTimeout(() => {
                panel.focus({ preventScroll: true });
                const checked = form.querySelector('input[name="rating"]:checked');
                (checked || form.querySelector('input[name="rating"]') || commentEl)?.focus?.({ preventScroll: true });
            }, 30);
        });

        modal.querySelectorAll('[data-review-close]').forEach((button) => button.addEventListener('click', close));
        modal.addEventListener('keydown', (event) => trapModalFocus(event, modal));
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && modal.classList.contains('is-open')) close();
        });

        form.addEventListener('submit', (event) => {
            const selected = form.querySelector('input[name="rating"]:checked');
            if (!selected) {
                event.preventDefault();
                errorEl?.removeAttribute('hidden');
                form.querySelector('input[name="rating"]')?.focus({ preventScroll: true });
                return;
            }
            if (commentEl && commentEl.value.length > 1000) {
                event.preventDefault();
                if (errorEl) {
                    errorEl.textContent = 'ข้อความรีวิวยาวเกิน 1000 ตัวอักษร';
                    errorEl.removeAttribute('hidden');
                }
                commentEl.focus({ preventScroll: true });
            }
        });

        function close() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('is-review-modal-open');
            setPageInert(false);
            lastTrigger?.focus?.({ preventScroll: true });
        }
    }

    function loadLeafletAssets() {
        if (window.L) return Promise.resolve();
        if (leafletAssetsPromise) return leafletAssetsPromise;
        leafletAssetsPromise = Promise.all([
            loadStylesheetOnce(LEAFLET_CSS_URL, 'badomen-leaflet-css'),
            loadScriptOnce(LEAFLET_JS_URL, 'badomen-leaflet-js')
        ]).then(() => undefined);
        return leafletAssetsPromise;
    }

    function loadStylesheetOnce(href, id) {
        if (document.getElementById(id)) return Promise.resolve();
        return new Promise((resolve, reject) => {
            const link = document.createElement('link');
            link.id = id;
            link.rel = 'stylesheet';
            link.href = href;
            link.onload = () => resolve();
            link.onerror = reject;
            document.head.appendChild(link);
        });
    }

    function loadScriptOnce(src, id) {
        if (document.getElementById(id)) return Promise.resolve();
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.id = id;
            script.src = src;
            script.defer = true;
            script.onload = () => resolve();
            script.onerror = reject;
            document.body.appendChild(script);
        });
    }

    function setPageInert(enabled) {
        const anyModalOpen = enabled || document.body.classList.contains('is-cancel-modal-open') || document.body.classList.contains('is-event-detail-open') || document.body.classList.contains('is-review-modal-open');
        document.querySelectorAll('body > main, body > header, body > footer').forEach((node) => {
            if (anyModalOpen) {
                node.setAttribute('inert', '');
                node.setAttribute('aria-hidden', 'true');
            } else {
                node.removeAttribute('inert');
                node.removeAttribute('aria-hidden');
            }
        });
        if (!anyModalOpen) restoreHeaderVisibility();
    }

    function restoreHeaderVisibility() {
        document.querySelectorAll('#siteHeader, body > header, .site-header').forEach((header) => {
            header.classList.remove('is-hidden');
            header.removeAttribute('inert');
            header.removeAttribute('aria-hidden');
        });
    }

    function trapModalFocus(event, modal) {
        if (event.key !== 'Tab' || !modal.classList.contains('is-open')) return;
        const focusable = Array.from(modal.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'))
            .filter((node) => node.offsetParent !== null);
        if (!focusable.length) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    function scrollOffset() {
        const header = document.querySelector('#siteHeader, header');
        const headerHeight = header ? Math.min(92, Math.max(0, header.getBoundingClientRect().height || 0)) : 0;
        return headerHeight + 28;
    }

    function smoothScrollTo(target, block = 'start') {
        if (!target) return;
        const rect = target.getBoundingClientRect();
        const offset = scrollOffset();
        const bottomSafe = 28;
        if (block === 'nearest' && rect.top >= offset && rect.bottom <= window.innerHeight - bottomSafe) return;

        let top = window.scrollY + rect.top - offset;
        if (block === 'center') {
            top = window.scrollY + rect.top - Math.max(offset, (window.innerHeight - rect.height) / 2);
        }
        window.scrollTo({
            top: Math.max(0, Math.round(top)),
            behavior: prefersReducedMotion ? 'auto' : 'smooth'
        });
    }

    function markTargetCard(card) {
        if (!card) return;
        cards.forEach((item) => item.classList.remove('is-scroll-target'));
        card.classList.add('is-scroll-target');
        window.setTimeout(() => card.classList.remove('is-scroll-target'), 2600);
    }

    function attachHashFeedback(card) {
        if (!card) return;
        const alert = document.querySelector('.join-alert');
        if (!alert) return;
        card.querySelector('.activity-inline-notice')?.remove();
        const notice = document.createElement('div');
        notice.className = 'activity-inline-notice ' + (alert.classList.contains('join-alert--error') ? 'is-error' : 'is-success');
        notice.innerHTML = alert.innerHTML;
        card.querySelector('.activity-content')?.prepend(notice);
    }

    function revealHashTarget() {
        const match = window.location.hash.match(/^#event-(\d+)$/);
        if (!match) return;
        const target = document.getElementById(`event-${match[1]}`);
        if (!target) return;
        window.setTimeout(() => {
            smoothScrollTo(target, 'nearest');
            markTargetCard(target);
            attachHashFeedback(target);
        }, 90);
    }

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('[data-reminder-form]');
        if (!form) return;

        const button = form.querySelector('[data-reminder-submit]');
        if (!button || button.classList.contains('is-loading')) return;

        const isDisabling = button.classList.contains('join-button--muted');
        button.dataset.originalLabel = button.innerHTML;
        button.classList.add('is-loading');
        button.setAttribute('aria-busy', 'true');
        button.disabled = true;
        button.innerHTML = `<i class="bx bx-loader-alt"></i>${isDisabling ? 'กำลังปิดแจ้งเตือน...' : 'กำลังตั้งแจ้งเตือน...'}`;
    }, true);

    function formatDayLabel(ymd) {
        const date = parseYmd(ymd);
        if (!date) return ymd;
        return new Intl.DateTimeFormat('th-TH', { day: '2-digit', month: 'short', year: 'numeric' }).format(date);
    }

    function statusText(status) {
        const map = { approved: 'อนุมัติ', pending: 'รออนุมัติ', checked_in: 'เช็กอิน', rejected: 'ยกเลิก' };
        return map[status] || status;
    }

    function normalizeText(value) {
        return String(value ?? '').toLocaleLowerCase('th-TH').replace(/\s+/g, ' ').trim();
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        }[char]));
    }
})();

</script>
    <?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
