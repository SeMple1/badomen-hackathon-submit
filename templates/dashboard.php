<?php
$events = $events ?? [];
$summary = $summary ?? [
    'total_events' => 0,
    'total_registered' => 0,
    'total_pending' => 0,
    'total_approved' => 0,
    'total_rejected' => 0,
    'total_checked_in' => 0,
    'total_capacity' => 0,
    'total_upcoming' => 0,
    'total_finished' => 0,
    'total_revenue' => 0,
    'total_staff' => 0,
    'total_sponsor' => 0,
];
$errors = $errors ?? [];
$successes = $successes ?? [];
$viewerIsVip = (bool)($viewerIsVip ?? false);

$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$placeholderSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="900" height="620" viewBox="0 0 900 620"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#111827"/><stop offset=".52" stop-color="#7c2d12"/><stop offset="1" stop-color="#ea580c"/></linearGradient></defs><rect width="900" height="620" fill="url(#g)"/><circle cx="730" cy="90" r="190" fill="#fb923c" opacity=".22"/><circle cx="120" cy="560" r="170" fill="#fed7aa" opacity=".16"/><rect x="155" y="175" width="590" height="270" rx="42" fill="#fff" fill-opacity=".10" stroke="#fff" stroke-opacity=".26" stroke-width="3"/><text x="450" y="295" text-anchor="middle" fill="#fff" font-family="Arial,sans-serif" font-size="44" font-weight="800">BADOMEN</text><text x="450" y="350" text-anchor="middle" fill="#fed7aa" font-family="Arial,sans-serif" font-size="24" font-weight="700">CREATOR DASHBOARD</text></svg>';
$placeholderImage = 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($placeholderSvg);

$normalizeImagePath = static function (?string $path) use ($placeholderImage): string {
    $path = trim((string)$path);
    if ($path === '') {
        return $placeholderImage;
    }
    if (preg_match('#^(https?://|data:image/)#i', $path)) {
        return $path;
    }
    $relativePath = ltrim(str_replace('\\', '/', $path), '/');
    $localPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    return is_file($localPath) ? '/' . $relativePath : $placeholderImage;
};

$formatDateTime = static function (?string $datetime): string {
    if (!$datetime) return '-';
    $ts = strtotime($datetime);
    return $ts ? date('d/m/Y H:i', $ts) : '-';
};

$formatDate = static function (?string $date): string {
    if (!$date) return '-';
    $ts = strtotime($date);
    return $ts ? date('d/m/Y', $ts) : '-';
};

$formatNumber = static fn(int|float $value): string => number_format((float)$value, 0);
$formatMoney = static fn(int|float $value): string => number_format((float)$value, 0) . ' บาท';

$now = time();

$capacity = (int)($summary['total_capacity'] ?? 0);
$registered = (int)($summary['total_registered'] ?? 0);
$overallFill = $capacity > 0 ? min(100, (int)round(($registered / $capacity) * 100)) : 0;

$overallApproved = (int)($summary['total_approved'] ?? 0);
$overallChecked = (int)($summary['total_checked_in'] ?? 0);
$overallApprovedPlusChecked = $overallApproved + $overallChecked;
$overallApprovalRate = $registered > 0 ? min(100, (int)round(($overallApprovedPlusChecked / $registered) * 100)) : 0;

$totalUpcoming = (int)($summary['total_upcoming'] ?? 0);
$totalFinished = (int)($summary['total_finished'] ?? 0);
if ($totalUpcoming === 0 && $totalFinished === 0 && !empty($events)) {
    foreach ($events as $eventForCount) {
        $endTs = strtotime((string)($eventForCount['event_end'] ?? ''));
        if ($endTs !== false && $endTs < $now) {
            $totalFinished++;
        } else {
            $totalUpcoming++;
        }
    }
}

$statusTotal = max(1, (int)$summary['total_registered']);
$pendingPct = (int)round(((int)$summary['total_pending'] / $statusTotal) * 100);
$approvedPct = (int)round(((int)$summary['total_approved'] / $statusTotal) * 100);
$checkedPct = (int)round(((int)$summary['total_checked_in'] / $statusTotal) * 100);
$rejectedPct = max(0, 100 - $pendingPct - $approvedPct - $checkedPct);

$eventInsightPayloads = [];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard | Badomen</title>

    <link rel="stylesheet" href="/style/app.css">
    <link rel="preload" as="image" href="/assets/dashborad.png?v=1" fetchpriority="high">
    <link rel="stylesheet" href="/style/dashboard.css?v=9">
    <link rel="stylesheet" href="/style/footer.css?v=2">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet"></noscript>
</head>

<body class="dashboard-page">
<?php require __DIR__ . '/header.php'; ?>

<main class="dashboard-main site-content-shell">
    <section
        class="dashboard-hero dashboard-hero--with-bg"
        aria-labelledby="dashboardTitle"
        style="--dash-hero-bg-image: url('/assets/dashborad.png?v=1');">
        <div class="hero-copy">
            <span class="hero-kicker"><i class="bx bx-bar-chart-alt-2"></i> CREATOR COMMAND CENTER</span>
            <h1 id="dashboardTitle">กิจกรรมของฉัน</h1>
            <p>
                Dashboard สำหรับผู้สร้างกิจกรรม ดูยอดสมัคร รายได้ สถานะตั๋ว และโครงสร้างผู้ร่วมจากฐานข้อมูลจริง
            </p>

            <div class="hero-detail-grid" aria-label="รายละเอียดแดชบอร์ด">
                <article>
                    <i class="bx bx-calendar-star"></i>
                    <div>
                        <span>กิจกรรมทั้งหมด</span>
                        <strong><?= $formatNumber((int)$summary['total_events']) ?></strong>
                    </div>
                </article>

                <article>
                    <i class="bx bx-group"></i>
                    <div>
                        <span>ผู้สมัครรวม</span>
                        <strong><?= $formatNumber((int)$summary['total_registered']) ?></strong>
                    </div>
                </article>

                <article>
                    <i class="bx bx-check-shield"></i>
                    <div>
                        <span>อนุมัติแล้ว</span>
                        <strong><?= $formatNumber((int)$summary['total_approved']) ?></strong>
                    </div>
                </article>

                <article>
                    <i class="bx bx-qr-scan"></i>
                    <div>
                        <span>เช็คอินแล้ว</span>
                        <strong><?= $formatNumber((int)$summary['total_checked_in']) ?></strong>
                    </div>
                </article>
            </div>


            <div class="hero-actions">
                <a href="/create_activity" class="btn btn-primary"><i class="bx bx-plus"></i> สร้างกิจกรรม</a>
                <a href="/home_in" class="btn btn-ghost"><i class="bx bx-left-arrow-alt"></i> กลับหน้าแรก</a>
                <button
                    type="button"
                    class="btn btn-ai open-dashboard-ai"
                    data-ai-event-id="0"
                    <?= (empty($events) || !$viewerIsVip) ? 'disabled aria-disabled="true" title="' . ($viewerIsVip ? 'สร้างกิจกรรมก่อนใช้งาน AI' : 'Gold VIP required') . '"' : '' ?>>
                    <i class="bx bx-sparkles"></i> AI สรุปภาพรวม
                </button>
            </div>
        </div>

        <aside class="hero-panel" aria-label="ภาพรวมระบบ">
            <div class="hero-panel-top">
                <div>
                    <span class="mini-label">อัตราอนุมัติรวม</span>
                    <strong><?= $overallApprovalRate ?>%</strong>
                </div>
                <div class="radial-score" style="--score: <?= $overallApprovalRate ?>%;">
                    <span><?= $overallApprovalRate ?>%</span>
                </div>
            </div>

            <div class="hero-panel-grid">
                <div><strong><?= $formatNumber((int)$summary['total_events']) ?></strong><span>กิจกรรม</span></div>
                <div><strong><?= $formatNumber($totalUpcoming) ?></strong><span>ยังไม่จบ</span></div>
                <div><strong><?= $formatNumber($totalFinished) ?></strong><span>เสร็จสิ้น</span></div>
            </div>
        </aside>
    </section>

    <section class="alert-stack" aria-live="polite">
        <?php if (!empty($successes)): ?>
            <div class="alert-card alert-card-success">
                <i class="bx bx-check-circle"></i>
                <ul>
                    <?php foreach ($successes as $success): ?>
                        <li><?= $escape((string)$success) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert-card alert-card-error">
                <i class="bx bx-error-circle"></i>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= $escape((string)$error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </section>

    <section class="summary-grid" aria-label="สรุปรวม">
        <article class="summary-card summary-card-accent">
            <div class="summary-card__top"><span class="summary-icon"><i class="bx bx-calendar-star"></i></span><b>จำนวนกิจกรรม</b></div>
            <strong><?= $formatNumber((int)$summary['total_events']) ?></strong>
            <p>กิจกรรมที่คุณเป็นผู้สร้างทั้งหมด</p>
        </article>

        <article class="summary-card">
            <div class="summary-card__top"><span class="summary-icon"><i class="bx bx-group"></i></span><b>ผู้สมัครทั้งหมด</b></div>
            <strong><?= $formatNumber((int)$summary['total_registered']) ?></strong>
            <p>Approved <?= $formatNumber((int)$summary['total_approved']) ?> • Pending <?= $formatNumber((int)$summary['total_pending']) ?></p>
        </article>

        <article class="summary-card">
            <div class="summary-card__top"><span class="summary-icon"><i class="bx bx-qr-scan"></i></span><b>เช็คอินแล้ว</b></div>
            <strong><?= $formatNumber((int)$summary['total_checked_in']) ?></strong>
            <p>Approved <?= $formatNumber((int)$summary['total_approved']) ?> • Rejected <?= $formatNumber((int)$summary['total_rejected']) ?></p>
        </article>

        <article class="summary-card">
            <div class="summary-card__top"><span class="summary-icon"><i class="bx bx-wallet"></i></span><b>รายได้รวม</b></div>
            <strong><?= $escape($formatMoney((float)($summary['total_revenue'] ?? 0))) ?></strong>
            <p>คำนวณจากคำสั่งซื้อที่ชำระแล้ว • ความหนาแน่น <?= $overallFill ?>%</p>
        </article>
    </section>

    <section class="insight-strip" aria-label="สรุปสถานะผู้สมัคร">
        <div class="insight-copy">
            <span class="section-kicker"><i class="bx bx-pulse"></i> REGISTRATION FLOW</span>
            <h2>สถานะผู้สมัครรวม</h2>
        </div>
        <div class="status-meter" aria-label="กราฟสถานะผู้สมัคร">
            <div class="status-meter-bar">
                <span class="meter-pending" style="width: <?= $pendingPct ?>%"></span>
                <span class="meter-approved" style="width: <?= $approvedPct ?>%"></span>
                <span class="meter-checked" style="width: <?= $checkedPct ?>%"></span>
                <span class="meter-rejected" style="width: <?= $rejectedPct ?>%"></span>
            </div>
            <div class="status-legend">
                <span><b class="dot dot-pending"></b>Pending <?= (int)$summary['total_pending'] ?></span>
                <span><b class="dot dot-approved"></b>Approved <?= (int)$summary['total_approved'] ?></span>
                <span><b class="dot dot-checked"></b>Checked-in <?= (int)$summary['total_checked_in'] ?></span>
                <span><b class="dot dot-rejected"></b>Rejected <?= (int)$summary['total_rejected'] ?></span>
            </div>
        </div>
    </section>

    <section class="event-section" aria-labelledby="eventListTitle">
        <div class="section-head">
            <div>
                <span class="section-kicker"><i class="bx bx-list-ul"></i> EVENT PERFORMANCE</span>
                <h2 id="eventListTitle">รายการกิจกรรม</h2>
                <p>กด “รายละเอียดเชิงลึก” เพื่อเปิด modal วิเคราะห์ผู้สมัคร การเช็กอิน ตั๋ว และรายได้</p>
            </div>

            <div class="event-tools">
                <label class="event-search" aria-label="ค้นหากิจกรรมในหน้านี้">
                    <i class="bx bx-search"></i>
                    <input id="eventSearchInput" type="search" placeholder="ค้นหาชื่อหรือสถานที่...">
                </label>
                <div class="filter-tabs" role="tablist" aria-label="กรองสถานะกิจกรรม">
                    <button type="button" class="filter-tab is-active" data-filter="all">ทั้งหมด</button>
                    <button type="button" class="filter-tab" data-filter="upcoming">ยังไม่จบ</button>
                    <button type="button" class="filter-tab" data-filter="finished">เสร็จสิ้น</button>
                </div>
            </div>
        </div>

        <?php if (empty($events)): ?>
            <div class="empty-state">
                <i class="bx bx-calendar-x"></i>
                <h3>ยังไม่มีกิจกรรมที่คุณสร้าง</h3>
                <p>เริ่มต้นด้วยการสร้างกิจกรรมแรก แล้วระบบจะแสดงยอดสมัคร สถานะ และข้อมูลผู้ร่วมในหน้านี้</p>
                <a href="/create_activity" class="btn btn-primary"><i class="bx bx-plus"></i> สร้างกิจกรรม</a>
            </div>
        <?php else: ?>
            <div id="eventStack" class="event-stack">
                <?php foreach ($events as $eventIndex => $event): ?>
                    <?php
                    $eventId = (int)($event['event_id'] ?? 0);
                    $title = (string)($event['title'] ?? '');
                    $location = (string)($event['location'] ?? '');
                    $cover = $normalizeImagePath((string)($event['cover_image'] ?? ''));

                    $endTs = strtotime((string)($event['event_end'] ?? ''));
                    $isFinished = ($endTs !== false && $endTs < $now);
                    $statusKey = $isFinished ? 'finished' : 'upcoming';

                    $max = (int)($event['max_participant'] ?? 0);
                    $total = (int)($event['total_registered'] ?? 0);

                    $pending = (int)($event['pending_count'] ?? 0);
                    $approved = (int)($event['approved_count'] ?? 0);
                    $rejected = (int)($event['rejected_count'] ?? 0);
                    $checked = (int)($event['checkedin_count'] ?? 0);
                    $refundRequestCount = (int)($event['refund_request_count'] ?? 0);

                    $fill = $max > 0 ? min(100, (int)round(($total / $max) * 100)) : 0;
                    $displayApprovalRate = $total > 0 ? min(100, (int)round((($approved + $checked) / $total) * 100)) : 0;

                    $timeBadgeClass = $isFinished ? 'event-time-badge--finished' : 'event-time-badge--upcoming';
                    $timeBadgeText = $isFinished ? 'เสร็จสิ้นแล้ว' : 'กำลังจะมาถึง';
                    $staffCount = (int)($event['staff_count'] ?? 0);
                    $sponsorCount = (int)($event['sponsor_count'] ?? 0);
                    $revenueTotal = (float)($event['revenue_total'] ?? 0);

                    $eventInsightPayloads[(string)$eventId] = [
                        'event_id' => $eventId,
                        'title' => $title,
                        'location' => $location,
                        'event_start' => $formatDateTime((string)($event['event_start'] ?? '')),
                        'event_end' => $formatDateTime((string)($event['event_end'] ?? '')),
                        'reg_start' => $formatDate((string)($event['reg_start'] ?? '')),
                        'reg_end' => $formatDate((string)($event['reg_end'] ?? '')),
                        'capacity' => $max,
                        'total_registered' => $total,
                        'pending' => $pending,
                        'approved' => $approved,
                        'rejected' => $rejected,
                        'checked' => $checked,
                        'approval_rate' => $displayApprovalRate,
                        'fill' => $fill,
                        'revenue_total' => $revenueTotal,
                        'revenue_text' => $formatMoney($revenueTotal),
                        'staff_count' => $staffCount,
                        'sponsor_count' => $sponsorCount,
                        'participants_url' => '/participants?event_id=' . $eventId,
                        'otp_url' => '/verifyotp?event_id=' . $eventId,
                        '_loaded' => false,
                    ];
                    ?>
                    <article
                        id="event-<?= $eventId ?>"
                        class="event-card"
                        data-event-card
                        data-status="<?= $escape($statusKey) ?>"
                        data-title="<?= $escape(mb_strtolower($title . ' ' . $location, 'UTF-8')) ?>">
                        <div class="event-cover-wrap">
                            <img
                                src="<?= $escape($cover) ?>"
                                alt="ภาพปกกิจกรรม <?= $escape($title) ?>"
                                loading="<?= $eventIndex === 0 ? 'eager' : 'lazy' ?>"
                                decoding="async"
                                fetchpriority="<?= $eventIndex === 0 ? 'high' : 'low' ?>">
                            <span class="event-time-badge <?= $escape($timeBadgeClass) ?>"><?= $timeBadgeText ?></span>
                        </div>

                        <div class="event-content">
                            <div class="event-title-row">
                                <div>
                                    <h3><?= $escape($title) ?></h3>
                                    <p><i class="bx bx-map"></i><span><?= $escape($location) ?></span></p>
                                </div>
                                <span class="event-id-chip">#<?= $eventId ?></span>
                            </div>

                            <div class="event-meta-grid">
                                <div><span>เริ่ม</span><strong><?= $escape($formatDateTime((string)($event['event_start'] ?? ''))) ?></strong></div>
                                <div><span>จบ</span><strong><?= $escape($formatDateTime((string)($event['event_end'] ?? ''))) ?></strong></div>
                                <div><span>เปิดรับ</span><strong><?= $escape($formatDate((string)($event['reg_start'] ?? ''))) ?></strong></div>
                                <div><span>ปิดรับ</span><strong><?= $escape($formatDate((string)($event['reg_end'] ?? ''))) ?></strong></div>
                            </div>

                            <div class="fill-box">
                                <div class="fill-top">
                                    <span>ความหนาแน่นผู้สมัคร</span>
                                    <strong><?= $fill ?>% <small>(<?= $formatNumber($total) ?>/<?= $formatNumber($max) ?>)</small></strong>
                                </div>
                                <div class="progress-track"><span style="width: <?= $fill ?>%"></span></div>
                            </div>
                        </div>

                        <aside class="event-side">
                            <div class="action-row">
                                <a href="/participants?event_id=<?= $eventId ?>" class="btn btn-primary btn-sm"><i class="bx bx-user-check"></i> รายชื่อ</a>
                                <a href="/verifyotp?event_id=<?= $eventId ?>" class="btn btn-soft btn-sm"><i class="bx bx-qr-scan"></i> OTP</a>
                                <a href="/editing_activity?event_id=<?= $eventId ?>" class="btn btn-danger btn-sm"><i class="bx bx-edit"></i> แก้ไข</a>
                            </div>

                            <div class="stats-box">
                                <div class="stats-head">
                                    <div>
                                        <span>Approval</span>
                                        <strong><?= $displayApprovalRate ?>%</strong>
                                    </div>
                                    <i class="bx bx-line-chart"></i>
                                </div>

                                <div class="status-grid">
                                    <div class="status-cell pending"><span>Pending</span><strong><?= $formatNumber($pending) ?></strong></div>
                                    <div class="status-cell approved"><span>Approved</span><strong><?= $formatNumber($approved) ?></strong></div>
                                    <div class="status-cell rejected"><span>Rejected</span><strong><?= $formatNumber($rejected) ?></strong></div>
                                    <div class="status-cell checked"><span>Checked-in</span><strong><?= $formatNumber($checked) ?></strong></div>
                                </div>

                                <div class="deep-shortcuts">
                                    <span><b><?= $formatNumber($approved) ?></b> Approved</span>
                                    <span><b><?= $formatNumber($checked) ?></b> Checked-in</span>
                                    <span><b><?= $escape($formatMoney($revenueTotal)) ?></b></span>
                                    <span><b><?= $formatNumber($refundRequestCount) ?></b> Refund requests</span>
                                </div>

                                <div class="toggle-row">
                                    <button
                                        type="button"
                                        class="open-insight-modal"
                                        data-event-id="<?= $eventId ?>"
                                        aria-haspopup="dialog">
                                        <i class="bx bx-analyse"></i> รายละเอียดเชิงลึก
                                    </button>
                                    <button
                                        type="button"
                                        class="open-dashboard-ai"
                                        data-ai-event-id="<?= $eventId ?>"
                                        data-ai-event-title="<?= $escape($title) ?>">
                                        <i class="bx bx-sparkles"></i> AI สรุป
                                    </button>
                                </div>
                            </div>
                        </aside>
                    </article>
                <?php endforeach; ?>
            </div>

            <div id="dashboardNoMatch" class="empty-state empty-state-small is-hidden">
                <i class="bx bx-search-alt"></i>
                <h3>ไม่พบกิจกรรมที่ตรงกับตัวกรอง</h3>
                <p>ลองล้างคำค้นหาหรือเปลี่ยนตัวกรองสถานะ</p>
            </div>
        <?php endif; ?>
    </section>
</main>

<div id="eventInsightModal" class="event-insight-modal is-hidden" role="dialog" aria-modal="true" aria-labelledby="eventInsightTitle">
    <div class="event-insight-backdrop" data-close-insight></div>
    <section class="event-insight-card" tabindex="-1">
        <header class="event-insight-header">
            <div>
                <span class="insight-eyebrow">EVENT DEEP DETAIL</span>
                <h2 id="eventInsightTitle">รายละเอียดเชิงลึก</h2>
                <p id="eventInsightSubTitle">-</p>
            </div>
            <button type="button" class="insight-close" data-close-insight aria-label="ปิดรายละเอียด"><i class="bx bx-x"></i></button>
        </header>

        <div class="insight-tabbar" role="tablist" aria-label="หมวดข้อมูลเชิงลึก">
            <button type="button" class="insight-tab is-active" data-insight-tab="overview">ภาพรวม</button>
            <button type="button" class="insight-tab" data-insight-tab="audience">ผู้ร่วม</button>
            <button type="button" class="insight-tab" data-insight-tab="tickets">ตั๋ว/รายได้</button>
            <button type="button" class="insight-tab" data-insight-tab="reviews">รีวิว</button>
        </div>

        <div class="event-insight-body">
            <section class="insight-panel is-active" data-insight-panel="overview">
                <div id="insightKpis" class="insight-kpis"></div>
                <div class="insight-chart-grid">
                    <article class="insight-chart-card">
                        <div class="chart-head"><span>REGISTRATION TREND</span><strong>ยอดสมัครตามวัน</strong></div>
                        <div id="registrationTrendChart" class="line-chart"></div>
                    </article>
                    <article class="insight-chart-card">
                        <div class="chart-head"><span>CHECK-IN FLOW</span><strong>เช็คอินตามเวลา</strong></div>
                        <div id="checkinTrendChart" class="line-chart"></div>
                    </article>
                </div>
            </section>

            <section class="insight-panel" data-insight-panel="audience">
                <div class="insight-compare-grid">
                    <article class="insight-list-card"><div class="chart-head"><span>AGE</span><strong>ช่วงอายุ</strong></div><div id="ageInsightList"></div></article>
                    <article class="insight-list-card"><div class="chart-head"><span>CAREER</span><strong>กลุ่มอาชีพ</strong></div><div id="careerInsightList"></div></article>
                    <article class="insight-list-card"><div class="chart-head"><span>GENDER</span><strong>เพศ</strong></div><div id="genderInsightList"></div></article>
                </div>
            </section>

            <section class="insight-panel" data-insight-panel="tickets">
                <div class="insight-chart-grid">
                    <article class="insight-chart-card">
                        <div class="chart-head"><span>REVENUE</span><strong>รายได้ตามวัน</strong></div>
                        <div id="revenueTrendChart" class="line-chart"></div>
                    </article>
                    <article class="insight-list-card">
                        <div class="chart-head"><span>TICKET STATUS</span><strong>สถานะการใช้ตั๋ว</strong></div>
                        <div id="ticketStatusList"></div>
                    </article>
                </div>
                <article class="insight-list-card insight-zone-card">
                    <div class="chart-head"><span>ZONE HEALTH</span><strong>โซนที่นั่งและราคา</strong></div>
                    <div id="zoneUsageList" class="zone-usage-list"></div>
                </article>
            </section>

            <section class="insight-panel" data-insight-panel="reviews">
                <div class="review-insight-head">
                    <div><span>USER REVIEWS</span><strong id="reviewInsightSummary">ยังไม่มีรีวิว</strong></div>
                    <i class="bx bx-message-square-detail"></i>
                </div>
                <div id="reviewInsightList" class="review-insight-list"></div>
            </section>

        </div>
    </section>
</div>

<div id="dashboardAiModal" class="dashboard-ai-modal is-hidden" role="dialog" aria-modal="true" aria-labelledby="dashboardAiTitle">
    <div class="dashboard-ai-backdrop" data-close-dashboard-ai></div>
    <section class="dashboard-ai-card" tabindex="-1">
        <header class="dashboard-ai-header" data-ai-drag-handle>
            <div>
                <span>BADOMEN AI</span>
                <h2 id="dashboardAiTitle">ผู้ช่วยสรุปกิจกรรม</h2>
                <p id="dashboardAiScope">ภาพรวมกิจกรรมทั้งหมด</p>
            </div>
            <button type="button" class="dashboard-ai-close" data-close-dashboard-ai aria-label="ปิด AI"><i class="bx bx-x"></i></button>
        </header>
        <div class="dashboard-ai-suggestions" aria-label="คำถามแนะนำ">
            <button type="button" data-ai-prompt="สรุปจุดแข็งและสิ่งที่ควรปรับปรุง">จุดแข็ง/จุดปรับปรุง</button>
            <button type="button" data-ai-prompt="วิเคราะห์ยอดสมัครและความเสี่ยงที่ควรรีบจัดการ">ยอดสมัครและความเสี่ยง</button>
            <button type="button" data-ai-prompt="แนะนำสิ่งที่ผู้จัดงานควรทำต่อเป็นลำดับ">สิ่งที่ควรทำต่อ</button>
        </div>
        <div id="dashboardAiMessages" class="dashboard-ai-messages" aria-live="polite">
            <p class="dashboard-ai-empty">กดคำถามแนะนำหรือพิมพ์คำถามจากข้อมูลกิจกรรมจริงของคุณ</p>
        </div>
        <form id="dashboardAiForm" class="dashboard-ai-form">
            <input id="dashboardAiInput" type="text" maxlength="500" autocomplete="off" placeholder="ถามต่อเกี่ยวกับยอดสมัคร ผู้เข้าร่วม หรือกิจกรรม...">
            <button type="submit"><i class="bx bx-send"></i><span>ส่ง</span></button>
        </form>
    </section>
</div>

<?php require __DIR__ . '/footer.php'; ?>

<script id="dashboardEventInsights" type="application/json"><?=
    json_encode($eventInsightPayloads, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
?></script>
<script>
(function () {
    const searchInput = document.getElementById('eventSearchInput');
    const cards = Array.from(document.querySelectorAll('[data-event-card]'));
    const tabs = Array.from(document.querySelectorAll('.filter-tab'));
    const noMatch = document.getElementById('dashboardNoMatch');
    let activeFilter = 'all';

    function normalize(text) {
        return String(text || '').toLowerCase().trim();
    }

    function applyFilter() {
        const q = normalize(searchInput ? searchInput.value : '');
        let visible = 0;

        cards.forEach((card) => {
            const title = normalize(card.getAttribute('data-title'));
            const status = card.getAttribute('data-status') || 'all';
            const matchText = q === '' || title.includes(q);
            const matchStatus = activeFilter === 'all' || status === activeFilter;
            const show = matchText && matchStatus;
            card.classList.toggle('is-hidden', !show);
            if (show) visible++;
        });

        if (noMatch) noMatch.classList.toggle('is-hidden', visible !== 0);
    }

    if (searchInput) searchInput.addEventListener('input', applyFilter, { passive: true });

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            activeFilter = tab.getAttribute('data-filter') || 'all';
            tabs.forEach((item) => item.classList.toggle('is-active', item === tab));
            applyFilter();
        });
    });

    const payloadEl = document.getElementById('dashboardEventInsights');
    let insights = {};
    try {
        insights = JSON.parse(payloadEl ? payloadEl.textContent : '{}') || {};
    } catch (error) {
        insights = {};
    }

    const modal = document.getElementById('eventInsightModal');
    const modalCard = modal ? modal.querySelector('.event-insight-card') : null;
    const titleEl = document.getElementById('eventInsightTitle');
    const subTitleEl = document.getElementById('eventInsightSubTitle');
    const grantEventInput = document.getElementById('grantEventId');
    let activeEvent = null;
    let lastFocus = null;

    function money(value) {
        const amount = Number(value || 0);
        return amount.toLocaleString('th-TH', { maximumFractionDigits: 0 }) + ' บาท';
    }

    function number(value) {
        return Number(value || 0).toLocaleString('th-TH');
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function pct(part, total) {
        const base = Number(total || 0);
        if (base <= 0) return 0;
        return Math.max(0, Math.min(100, Math.round((Number(part || 0) / base) * 100)));
    }

    function compactDate(label) {
        const raw = String(label || '');
        const date = new Date(raw + 'T00:00:00');
        if (Number.isNaN(date.getTime())) return raw;
        return date.toLocaleDateString('th-TH', { day: '2-digit', month: 'short' });
    }

    function setPanel(tabName) {
        document.querySelectorAll('.insight-tab').forEach((tab) => {
            tab.classList.toggle('is-active', tab.getAttribute('data-insight-tab') === tabName);
        });
        document.querySelectorAll('.insight-panel').forEach((panel) => {
            panel.classList.toggle('is-active', panel.getAttribute('data-insight-panel') === tabName);
        });
    }

    document.querySelectorAll('.insight-tab').forEach((tab) => {
        tab.addEventListener('click', () => setPanel(tab.getAttribute('data-insight-tab') || 'overview'));
    });

    function renderKpis(event) {
        const kpis = [
            ['ผู้สมัคร', number(event.total_registered) + '/' + number(event.capacity), 'ความหนาแน่น ' + number(event.fill) + '%'],
            ['อนุมัติ', number(event.approved), 'Approval ' + number(event.approval_rate) + '%'],
            ['เช็คอิน', number(event.checked), 'Pending ' + number(event.pending)],
            ['รายได้รวม', event.revenue_text || money(event.revenue_total), 'จากคำสั่งซื้อที่ชำระแล้ว'],
            ['สิทธิ์พิเศษ', number((event.staff_count || 0) + (event.sponsor_count || 0)), 'Staff ' + number(event.staff_count) + ' • Sponsor ' + number(event.sponsor_count)],
        ];

        const el = document.getElementById('insightKpis');
        if (!el) return;
        el.innerHTML = kpis.map(([label, value, meta]) => `
            <article class="insight-kpi">
                <span>${escapeHtml(label)}</span>
                <strong>${escapeHtml(value)}</strong>
                <small>${escapeHtml(meta)}</small>
            </article>
        `).join('');
    }

    function renderLineChart(targetId, rows, metric, options) {
        const target = document.getElementById(targetId);
        if (!target) return;
        const items = Array.isArray(rows) ? rows.slice(0, 32) : [];
        if (items.length === 0) {
            target.innerHTML = '<div class="chart-empty">ยังไม่มีข้อมูลสำหรับกราฟนี้</div>';
            return;
        }

        const width = 680;
        const height = 238;
        const pad = { left: 42, right: 18, top: 18, bottom: 42 };
        const plotW = width - pad.left - pad.right;
        const plotH = height - pad.top - pad.bottom;
        const values = items.map((item) => Number(item[metric] || 0));
        const max = Math.max(1, ...values);
        const yTicks = [0, Math.ceil(max / 2), max];
        const denom = Math.max(1, items.length - 1);
        const points = items.map((item, index) => {
            const value = Number(item[metric] || 0);
            const x = pad.left + (plotW * index / denom);
            const y = pad.top + plotH - (plotH * value / max);
            return { x, y, value, label: String(item.label || '') };
        });
        const line = points.map((point) => `${point.x.toFixed(1)},${point.y.toFixed(1)}`).join(' ');
        const area = `${pad.left},${pad.top + plotH} ${line} ${pad.left + plotW},${pad.top + plotH}`;
        const color = options && options.color ? options.color : '#ea580c';
        const unit = options && options.unit ? options.unit : '';
        const axisTitle = options && options.axisTitle ? options.axisTitle : '';

        const xLabelStep = Math.max(1, Math.ceil(items.length / 6));
        const xLabels = points.map((point, index) => {
            if (index !== 0 && index !== points.length - 1 && index % xLabelStep !== 0) return '';
            const label = targetId === 'checkinTrendChart' ? point.label : compactDate(point.label);
            return `<text x="${point.x.toFixed(1)}" y="218" text-anchor="middle" font-size="10" fill="#64748b">${escapeHtml(label)}</text>`;
        }).join('');

        const yLabels = yTicks.map((tick) => {
            const y = pad.top + plotH - (plotH * tick / max);
            return `<g><line x1="${pad.left}" x2="${pad.left + plotW}" y1="${y.toFixed(1)}" y2="${y.toFixed(1)}" stroke="#e2e8f0" stroke-width="1"/><text x="32" y="${(y + 4).toFixed(1)}" text-anchor="end" font-size="10" fill="#64748b">${escapeHtml(number(tick))}</text></g>`;
        }).join('');

        const dots = points.map((point) => `
            <g>
                <circle cx="${point.x.toFixed(1)}" cy="${point.y.toFixed(1)}" r="4" fill="#fff" stroke="${color}" stroke-width="2" />
                <title>${escapeHtml(point.label)}: ${escapeHtml(number(point.value))}${escapeHtml(unit)}</title>
            </g>
        `).join('');

        target.innerHTML = `
            <svg viewBox="0 0 ${width} ${height}" role="img" aria-label="${escapeHtml(axisTitle)}">
                <defs>
                    <linearGradient id="chartFill${targetId}" x1="0" x2="0" y1="0" y2="1">
                        <stop offset="0" stop-color="${color}" stop-opacity=".22" />
                        <stop offset="1" stop-color="${color}" stop-opacity=".02" />
                    </linearGradient>
                </defs>
                ${yLabels}
                <line x1="${pad.left}" x2="${pad.left}" y1="${pad.top}" y2="${pad.top + plotH}" stroke="#cbd5e1" />
                <line x1="${pad.left}" x2="${pad.left + plotW}" y1="${pad.top + plotH}" y2="${pad.top + plotH}" stroke="#cbd5e1" />
                <polygon points="${area}" fill="url(#chartFill${targetId})" />
                <polyline points="${line}" fill="none" stroke="${color}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                ${dots}
                ${xLabels}
                <text x="${pad.left}" y="13" font-size="10" fill="#64748b">Y: ${escapeHtml(axisTitle || metric)}</text>
                <text x="${pad.left + plotW}" y="235" text-anchor="end" font-size="10" fill="#64748b">X: ช่วงเวลา</text>
            </svg>
        `;
    }

    function renderBarList(targetId, rows, total, emptyText) {
        const target = document.getElementById(targetId);
        if (!target) return;
        const items = Array.isArray(rows) ? rows : [];
        if (!items.length) {
            target.innerHTML = `<div class="list-empty">${escapeHtml(emptyText || 'ยังไม่มีข้อมูล')}</div>`;
            return;
        }
        const base = Math.max(1, Number(total || items.reduce((sum, item) => sum + Number(item.count || 0), 0)));
        target.innerHTML = `<div class="insight-bars">${items.map((item) => {
            const value = Number(item.count || 0);
            const percent = pct(value, base);
            return `
                <div class="insight-bar-row">
                    <div><span>${escapeHtml(item.label || '-')}</span><strong>${number(value)} (${percent}%)</strong></div>
                    <div class="progress-track progress-thin"><span style="width:${percent}%"></span></div>
                </div>
            `;
        }).join('')}</div>`;
    }

    function renderTicketStatus(event) {
        const target = document.getElementById('ticketStatusList');
        if (!target) return;
        const items = Array.isArray(event.ticket_status) ? event.ticket_status : [];
        if (!items.length) {
            target.innerHTML = '<div class="list-empty">ยังไม่มีข้อมูลสถานะตั๋ว</div>';
            return;
        }
        const total = Math.max(1, items.reduce((sum, item) => sum + Number(item.count || 0), 0));
        target.innerHTML = items.map((item) => {
            const percent = pct(item.count, total);
            return `
                <div class="status-row">
                    <div><span>${escapeHtml(item.label)}</span><strong>${number(item.count)} (${percent}%)</strong></div>
                    <div class="progress-track progress-thin"><span style="width:${percent}%"></span></div>
                </div>
            `;
        }).join('');
    }

    function renderZones(event) {
        const target = document.getElementById('zoneUsageList');
        if (!target) return;
        const zones = Array.isArray(event.zones) ? event.zones : [];
        if (!zones.length) {
            target.innerHTML = '<div class="list-empty">ยังไม่มีข้อมูลโซนที่นั่ง หรือยังไม่ได้รันระบบ ticket zones</div>';
            return;
        }
        target.innerHTML = zones.map((zone) => {
            const capacity = Number(zone.capacity || zone.seat_count || 0);
            const paid = Number(zone.paid_count || 0);
            const reserved = Number(zone.reserved_count || 0);
            const used = paid + reserved;
            const percent = pct(used, capacity);
            return `
                <div class="zone-row" style="--zone-color:${escapeHtml(zone.color_hex || '#ea580c')}">
                    <div>
                        <div class="zone-title"><span class="zone-dot"></span>${escapeHtml(zone.zone_name || zone.zone_code || '-')}</div>
                        <small>${escapeHtml(zone.zone_code || '')} • ราคา ${money(zone.price || 0)} • ใช้แล้ว ${number(used)}/${number(capacity)}</small>
                        <div class="progress-track progress-thin"><span style="width:${percent}%;background:var(--zone-color)"></span></div>
                    </div>
                    <strong>${percent}%</strong>
                </div>
            `;
        }).join('');
    }

    function renderGrants(event) {
        const target = document.getElementById('grantAccessList');
        if (!target) return;
        const grants = Array.isArray(event.grants) ? event.grants : [];
        if (!grants.length) {
            target.innerHTML = '<div class="list-empty">ยังไม่มี Staff หรือ Sponsor สำหรับกิจกรรมนี้</div>';
            return;
        }
        target.innerHTML = grants.map((grant) => {
            const canOtp = Number(grant.can_verify_otp || 0) === 1;
            const roleText = grant.access_role === 'sponsor' ? 'Sponsor' : 'Staff';
            return `
                <div class="grant-row">
                    <div>
                        <strong>${escapeHtml(grant.name || grant.email || '-')}</strong>
                        <small>${escapeHtml(grant.email || '')}</small>
                        <div class="grant-badges">
                            <span>${roleText}</span>
                            <span>ฟรี ${number(grant.free_ticket_limit || 0)} ใบ</span>
                            ${canOtp ? '<span>ตรวจ OTP ได้</span>' : ''}
                        </div>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="form_action" value="revoke_access">
                        <input type="hidden" name="event_id" value="${escapeHtml(event.event_id)}">
                        <input type="hidden" name="grant_id" value="${escapeHtml(grant.grant_id)}">
                        <button type="submit" class="revoke-btn">ยกเลิก</button>
                    </form>
                </div>
            `;
        }).join('');
    }

    function renderReviews(event) {
        const target = document.getElementById('reviewInsightList');
        const summary = document.getElementById('reviewInsightSummary');
        if (!target || !summary) return;
        const reviews = Array.isArray(event.reviews) ? event.reviews : [];
        summary.textContent = reviews.length
            ? `คะแนนเฉลี่ย ${Number(event.review_average || 0).toFixed(1)}/5 จาก ${number(reviews.length)} รีวิว`
            : 'ยังไม่มีรีวิวจากผู้เข้าร่วม';
        if (!reviews.length) {
            target.innerHTML = '<div class="list-empty">เมื่อผู้เข้าร่วมรีวิวกิจกรรม ชื่อ คะแนน และเหตุผลจะแสดงที่นี่</div>';
            return;
        }

        target.innerHTML = reviews.map((review) => {
            const rating = Math.max(0, Math.min(5, Number(review.rating || 0)));
            const stars = '★'.repeat(rating) + '☆'.repeat(5 - rating);
            const date = review.updated_at ? new Date(String(review.updated_at).replace(' ', 'T')).toLocaleString('th-TH') : '-';
            return `
                <article class="review-insight-card">
                    <div class="review-insight-card__head">
                        <div>
                            <strong>${escapeHtml(review.reviewer_name || 'ผู้ใช้ Badomen')}</strong>
                            <span>${escapeHtml(review.reviewer_email || '')}</span>
                        </div>
                        <div class="review-insight-stars" aria-label="${escapeHtml(rating)} ดาว">${stars}</div>
                    </div>
                    <p>${escapeHtml(review.comment || 'ผู้รีวิวไม่ได้ระบุเหตุผลเพิ่มเติม')}</p>
                    <footer><span>สถานะ: ${escapeHtml(review.checked_in ? 'เช็กอินแล้ว' : (review.registration_status || '-'))}</span><time>${escapeHtml(date)}</time></footer>
                </article>
            `;
        }).join('');
    }

    function renderEvent(event) {
        if (!event) return;
        if (titleEl) titleEl.textContent = event.title || 'รายละเอียดเชิงลึก';
        if (subTitleEl) subTitleEl.textContent = (event.location || '-') + ' • ' + (event.event_start || '-');
        if (grantEventInput) grantEventInput.value = event.event_id || '';
        renderKpis(event);
        renderLineChart('registrationTrendChart', event.registration_trend, 'count', { color: '#ea580c', axisTitle: 'จำนวนผู้สมัคร' });
        renderLineChart('checkinTrendChart', event.checkin_trend, 'count', { color: '#059669', axisTitle: 'จำนวนเช็คอิน' });
        renderLineChart('revenueTrendChart', event.revenue_trend, 'amount', { color: '#2563eb', axisTitle: 'รายได้', unit: ' บาท' });
        const participantBase = Math.max(1, Number(event.approved || 0) + Number(event.checked || 0));
        renderBarList('ageInsightList', event.age_groups, participantBase, 'ยังไม่มีข้อมูลช่วงอายุ');
        renderBarList('careerInsightList', event.career_groups, participantBase, 'ยังไม่มีข้อมูลอาชีพ');
        renderBarList('genderInsightList', event.gender_groups, participantBase, 'ยังไม่มีข้อมูลเพศ');
        renderTicketStatus(event);
        renderZones(event);
        renderGrants(event);
        renderReviews(event);
    }

    function openModal(eventId) {
        if (!modal) return;
        const key = String(eventId);
        const cached = insights[key] || null;
        if (!cached) return;

        lastFocus = document.activeElement;
        setPanel('overview');
        modal.classList.remove('is-hidden');
        document.body.classList.add('insight-modal-open');

        const renderOrLoad = (payload) => {
            activeEvent = payload;
            renderEvent(payload);
            window.requestAnimationFrame(() => {
                if (modalCard) modalCard.focus({ preventScroll: true });
            });
        };

        if (cached._loaded) {
            renderOrLoad(cached);
            return;
        }

        renderOrLoad(Object.assign({}, cached, {
            registration_trend: [],
            checkin_trend: [],
            revenue_trend: [],
            ticket_status: [],
            zones: [],
            grants: [],
            reviews: [],
            age_groups: [],
            career_groups: [],
            gender_groups: [],
            _loading: true,
        }));

        if (titleEl) titleEl.textContent = cached.title || 'กำลังโหลด...';
        if (subTitleEl) subTitleEl.textContent = 'กำลังดึงข้อมูลเชิงลึก...';

        fetch('/dashboard-insight?event_id=' + encodeURIComponent(key), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((response) => response.json())
            .then((data) => {
                if (!data.ok || !data.insight) {
                    throw new Error(data.error || 'load_failed');
                }
                insights[key] = Object.assign({}, cached, data.insight, { _loaded: true, _loading: false });
                if (!modal.classList.contains('is-hidden')) {
                    renderOrLoad(insights[key]);
                }
            })
            .catch(() => {
                if (subTitleEl) subTitleEl.textContent = 'โหลดข้อมูลไม่สำเร็จ กรุณาลองใหม่';
            });
    }

    function closeModal() {
        if (!modal) return;
        modal.classList.add('is-hidden');
        document.body.classList.remove('insight-modal-open');
        if (lastFocus && typeof lastFocus.focus === 'function') {
            lastFocus.focus({ preventScroll: true });
        }
    }

    document.addEventListener('click', (event) => {
        const openBtn = event.target.closest('.open-insight-modal');
        if (openBtn) {
            openModal(openBtn.getAttribute('data-event-id'));
            return;
        }
        if (event.target.closest('[data-close-insight]')) {
            closeModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal && !modal.classList.contains('is-hidden')) {
            closeModal();
        }
    });

    const aiModal = document.getElementById('dashboardAiModal');
    const dashboardAiVip = <?= json_encode($viewerIsVip) ?>;
    const aiCard = aiModal?.querySelector('.dashboard-ai-card');
    const aiDragHandle = aiModal?.querySelector('[data-ai-drag-handle]');
    const aiScope = document.getElementById('dashboardAiScope');
    const aiMessages = document.getElementById('dashboardAiMessages');
    const aiForm = document.getElementById('dashboardAiForm');
    const aiInput = document.getElementById('dashboardAiInput');
    let aiEventId = 0;
    let aiBusy = false;
    let aiHasConversation = false;
    let aiLastFocus = null;
    let dragState = null;

    function appendAiMessage(role, text) {
        if (!aiMessages) return;
        aiMessages.querySelector('.dashboard-ai-empty')?.remove();
        const message = document.createElement('div');
        message.className = `dashboard-ai-message is-${role}`;
        message.textContent = text;
        aiMessages.appendChild(message);
        aiMessages.scrollTop = aiMessages.scrollHeight;
        aiHasConversation = true;
    }

    async function askDashboardAi(message, reset = false) {
        if (aiBusy || !message.trim()) return;
        aiBusy = true;
        appendAiMessage('user', message.trim());
        const loading = document.createElement('div');
        loading.className = 'dashboard-ai-message is-assistant is-loading';
        loading.textContent = 'กำลังวิเคราะห์ข้อมูลจริง...';
        aiMessages?.appendChild(loading);
        aiInput?.setAttribute('disabled', 'disabled');

        try {
            const body = new URLSearchParams({
                _csrf: <?= json_encode(csrfToken(), JSON_UNESCAPED_SLASHES) ?>,
                event_id: String(aiEventId),
                message: message.trim(),
                reset: reset ? '1' : '0',
            });
            const response = await fetch('/dashboard-ai', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body,
            });
            const result = await response.json();
            loading.remove();
            appendAiMessage('assistant', response.ok && result.ok
                ? result.text
                : (result.error || 'AI ยังไม่พร้อมใช้งานในขณะนี้'));
        } catch (error) {
            loading.remove();
            appendAiMessage('assistant', 'เชื่อมต่อ AI ไม่สำเร็จ กรุณาลองใหม่อีกครั้ง');
        } finally {
            aiBusy = false;
            aiInput?.removeAttribute('disabled');
            aiInput?.focus({ preventScroll: true });
        }
    }

    function openDashboardAi(button) {
        if (!aiModal || !aiCard) return;
        if (!dashboardAiVip) {
            window.location.href = '/vip';
            return;
        }
        aiEventId = Number(button?.dataset.aiEventId || 0);
        const title = button?.dataset.aiEventTitle || '';
        if (aiScope) aiScope.textContent = aiEventId > 0 ? title : 'ภาพรวมกิจกรรมทั้งหมด';
        aiMessages?.replaceChildren();
        if (aiMessages) {
            const empty = document.createElement('p');
            empty.className = 'dashboard-ai-empty';
            empty.textContent = 'กดคำถามแนะนำหรือพิมพ์คำถามจากข้อมูลกิจกรรมจริงของคุณ';
            aiMessages.appendChild(empty);
        }
        aiHasConversation = false;
        aiLastFocus = document.activeElement;
        aiCard.style.transform = '';
        aiModal.classList.remove('is-hidden');
        document.body.classList.add('dashboard-ai-open');
        window.requestAnimationFrame(() => aiInput?.focus({ preventScroll: true }));
    }

    function closeDashboardAi() {
        if (!aiModal) return;
        if ((aiBusy || aiHasConversation) && !window.confirm('ปิดบทสนทนา AI นี้หรือไม่? คุณสามารถเปิดและถามใหม่ได้ทุกเมื่อ')) return;
        aiModal.classList.add('is-hidden');
        document.body.classList.remove('dashboard-ai-open');
        aiLastFocus?.focus?.({ preventScroll: true });
    }

    document.addEventListener('click', (event) => {
        const openButton = event.target.closest('.open-dashboard-ai');
        if (openButton) {
            openDashboardAi(openButton);
            return;
        }
        if (event.target.closest('[data-close-dashboard-ai]')) {
            closeDashboardAi();
            return;
        }
        const promptButton = event.target.closest('[data-ai-prompt]');
        if (promptButton && aiModal && !aiModal.classList.contains('is-hidden')) {
            askDashboardAi(promptButton.dataset.aiPrompt || '', !aiHasConversation);
        }
    });

    aiForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        const message = aiInput?.value || '';
        if (!message.trim()) return;
        if (aiInput) aiInput.value = '';
        askDashboardAi(message, !aiHasConversation);
    });

    aiDragHandle?.addEventListener('pointerdown', (event) => {
        if (event.target.closest('button') || !aiCard || window.innerWidth < 760) return;
        const rect = aiCard.getBoundingClientRect();
        dragState = {
            pointerId: event.pointerId,
            startX: event.clientX,
            startY: event.clientY,
            originX: rect.left + rect.width / 2 - window.innerWidth / 2,
            originY: rect.top + rect.height / 2 - window.innerHeight / 2,
        };
        aiDragHandle.setPointerCapture(event.pointerId);
        aiCard.classList.add('is-dragging');
    });
    aiDragHandle?.addEventListener('pointermove', (event) => {
        if (!dragState || !aiCard) return;
        const maxX = Math.max(0, (window.innerWidth - aiCard.offsetWidth) / 2 - 12);
        const maxY = Math.max(0, (window.innerHeight - aiCard.offsetHeight) / 2 - 12);
        const x = Math.max(-maxX, Math.min(maxX, dragState.originX + event.clientX - dragState.startX));
        const y = Math.max(-maxY, Math.min(maxY, dragState.originY + event.clientY - dragState.startY));
        aiCard.style.transform = `translate3d(${x}px,${y}px,0)`;
    });
    aiDragHandle?.addEventListener('pointerup', () => {
        dragState = null;
        aiCard?.classList.remove('is-dragging');
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && aiModal && !aiModal.classList.contains('is-hidden')) {
            closeDashboardAi();
        }
    });

    const roleSelect = document.getElementById('accessRoleSelect');
    if (roleSelect) {
        roleSelect.addEventListener('change', () => {
            const checkbox = document.querySelector('input[name="can_verify_otp"]');
            const freeInput = document.querySelector('input[name="free_ticket_limit"]');
            if (!checkbox || !freeInput) return;
            if (roleSelect.value === 'staff') {
                checkbox.checked = true;
                freeInput.value = freeInput.value || '1';
            } else if (roleSelect.value === 'sponsor') {
                checkbox.checked = false;
                if (Number(freeInput.value || 0) < 1) freeInput.value = '1';
            }
        });
    }
})();

</script>
</body>
</html>
