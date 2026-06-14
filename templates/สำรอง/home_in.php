<?php
$events = $events ?? [];
$errors = $errors ?? [];
$successes = $successes ?? [];
$query = trim((string)($query ?? ''));
$startAt = trim((string)($startAt ?? ''));
$endAt = trim((string)($endAt ?? ''));

$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$placeholderSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="750" viewBox="0 0 1200 750"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#111827"/><stop offset=".55" stop-color="#7c2d12"/><stop offset="1" stop-color="#ea580c"/></linearGradient></defs><rect width="1200" height="750" fill="url(#g)"/><circle cx="940" cy="110" r="240" fill="#fb923c" opacity=".22"/><circle cx="120" cy="680" r="210" fill="#fed7aa" opacity=".20"/><path d="M210 445h780" stroke="#fff" stroke-opacity=".28" stroke-width="8" stroke-linecap="round" stroke-dasharray="24 28"/><rect x="250" y="210" width="700" height="330" rx="54" fill="#fff" fill-opacity=".12" stroke="#fff" stroke-opacity=".28" stroke-width="4"/><text x="600" y="345" text-anchor="middle" fill="#fff" font-family="Arial, sans-serif" font-size="54" font-weight="800">BADOMEN</text><text x="600" y="410" text-anchor="middle" fill="#fed7aa" font-family="Arial, sans-serif" font-size="30" font-weight="700">EVENT TICKET</text></svg>';
$placeholderImage = 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($placeholderSvg);

$normalizeImagePath = static function (?string $path) use ($placeholderImage): string {
    $path = trim((string)$path);

    if ($path === '') {
        return $placeholderImage;
    }

    if (preg_match('#^(https?://|data:image/)#i', $path)) {
        return $path;
    }

    $path = str_replace('\\', '/', $path);
    return '/' . ltrim($path, '/');
};

$formatDate = static function (string $datetime): string {
    $ts = strtotime($datetime);
    if ($ts === false) return '-';
    return date('d/m/Y H:i', $ts);
};

$formatDateShort = static function (string $datetime): string {
    $ts = strtotime($datetime);
    if ($ts === false) return '-';
    return date('d M Y • H:i', $ts);
};

$formatRegDate = static function (string $date, bool $isEnd = false): string {
    $date = trim($date);
    if ($date === '') return '-';

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return date('d/m/Y', strtotime($date)) . ($isEnd ? ' 23:59' : ' 00:00');
    }

    $ts = strtotime($date);
    if ($ts === false) return '-';

    if (date('H:i:s', $ts) === '00:00:00') {
        return date('d/m/Y', $ts) . ($isEnd ? ' 23:59' : ' 00:00');
    }

    return date('d/m/Y H:i', $ts);
};

$tz = new DateTimeZone('Asia/Bangkok');
$now = new DateTimeImmutable('now', $tz);

$parseDbDateTime = static function (string $value, DateTimeZone $tz, bool $isEnd = false): ?DateTimeImmutable {
    $value = trim($value);
    if ($value === '') return null;

    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, $tz);
    if ($dt instanceof DateTimeImmutable) {
        return $dt->format('H:i:s') === '00:00:00'
            ? ($isEnd ? $dt->setTime(23, 59, 59) : $dt->setTime(0, 0, 0))
            : $dt;
    }

    $d = DateTimeImmutable::createFromFormat('Y-m-d', $value, $tz);
    if ($d instanceof DateTimeImmutable) {
        return $isEnd ? $d->setTime(23, 59, 59) : $d->setTime(0, 0, 0);
    }

    $fallback = date_create_immutable($value, $tz);
    if ($fallback instanceof DateTimeImmutable) {
        return $fallback->format('H:i:s') === '00:00:00'
            ? ($isEnd ? $fallback->setTime(23, 59, 59) : $fallback->setTime(0, 0, 0))
            : $fallback;
    }

    return null;
};

$totalEvents = count($events);
$openEvents = 0;
$fullEvents = 0;
$eventModalPayloads = [];
$filtered = $query !== '' || $startAt !== '' || $endAt !== '';

foreach ($events as $eventStat) {
    $registered = (int)($eventStat['registered_count'] ?? 0);
    $max = (int)($eventStat['max_participant'] ?? 0);
    $isFullStat = $max > 0 && $registered >= $max;

    $regStart = $parseDbDateTime((string)($eventStat['reg_start'] ?? ''), $tz, false);
    $regEnd = $parseDbDateTime((string)($eventStat['reg_end'] ?? ''), $tz, true);
    $isNotOpenStat = $regStart !== null && $now < $regStart;
    $isClosedStat = $regEnd !== null && $now > $regEnd;
    $already = (int)($eventStat['already_requested'] ?? 0) > 0;

    if ($isFullStat) {
        $fullEvents++;
    }

    if (!$already && !$isNotOpenStat && !$isClosedStat && !$isFullStat) {
        $openEvents++;
    }
}
$showAllEvents = (bool)($showAllEvents ?? false);
$showAllUrlParams = [];
if ($query !== '') {
    $showAllUrlParams['search'] = $query;
}
if ($startAt !== '') {
    $showAllUrlParams['start_at'] = $startAt;
}
if ($endAt !== '') {
    $showAllUrlParams['end_at'] = $endAt;
}
$showAllUrlParams['show_all'] = '1';
$showAllUrl = '/home_in?' . http_build_query($showAllUrlParams) . '#all-events';
$showPreviewOnly = !$showAllEvents && !$filtered;

$toTimestamp = static function (string $value): int {
    $ts = strtotime($value);
    return $ts === false ? 0 : $ts;
};

$eventCards = [];
foreach ($events as $eventIndex => $event) {
    $imagePath = (string)($event['cover_image'] ?? '');
    $registeredCount = (int)($event['registered_count'] ?? 0);
    $maxParticipant = (int)($event['max_participant'] ?? 0);
    $isFull = $maxParticipant > 0 && $registeredCount >= $maxParticipant;
    $alreadyRequested = (int)($event['already_requested'] ?? 0) > 0;

    $regStartRaw = (string)($event['reg_start'] ?? '');
    $regEndRaw = (string)($event['reg_end'] ?? '');

    $regStartDT = $parseDbDateTime($regStartRaw, $tz, false);
    $regEndDT = $parseDbDateTime($regEndRaw, $tz, true);

    $isNotOpen = $regStartDT !== null && $now < $regStartDT;
    $isClosed = $regEndDT !== null && $now > $regEndDT;

    $regStartText = $regStartRaw !== '' ? $formatRegDate($regStartRaw, false) : '-';
    $regEndText = $regEndRaw !== '' ? $formatRegDate($regEndRaw, true) : '-';

    $galleryImages = [];
    if (!empty($event['images']) && is_array($event['images'])) {
        foreach ($event['images'] as $img) {
            $normalized = $normalizeImagePath((string)$img);
            if ($normalized !== '') {
                $galleryImages[] = $normalized;
            }
        }
    }

    if ($imagePath !== '') {
        $normalizedCover = $normalizeImagePath($imagePath);
        if ($normalizedCover !== '' && !in_array($normalizedCover, $galleryImages, true)) {
            array_unshift($galleryImages, $normalizedCover);
        }
    }

    if (empty($galleryImages)) {
        $galleryImages[] = $placeholderImage;
    }

    $statusText = 'เปิดรับสมัคร';
    $statusClass = 'status-badge--open';
    if ($alreadyRequested) {
        $statusText = 'ขอเข้าร่วมแล้ว';
        $statusClass = 'status-badge--pending';
    } elseif ($isNotOpen) {
        $statusText = 'ยังไม่เปิดรับสมัคร';
        $statusClass = 'status-badge--waiting';
    } elseif ($isClosed) {
        $statusText = 'ปิดรับสมัครแล้ว';
        $statusClass = 'status-badge--closed';
    } elseif ($isFull) {
        $statusText = 'เต็มแล้ว';
        $statusClass = 'status-badge--full';
    }

    $capacityPercent = $maxParticipant > 0
        ? max(0, min(100, (int)round(($registeredCount / $maxParticipant) * 100)))
        : 0;
    $price = isset($event['price']) ? (float)$event['price'] : null;
    $compareAtPrice = isset($event['compare_at_price']) ? (float)$event['compare_at_price'] : null;
    $saleStartTs = !empty($event['sale_start']) ? strtotime((string)$event['sale_start']) : null;
    $saleEndTs = !empty($event['sale_end']) ? strtotime((string)$event['sale_end']) : null;
    $saleActive = $price !== null
        && $compareAtPrice !== null
        && $compareAtPrice > $price
        && ($saleStartTs === null || $saleStartTs <= time())
        && ($saleEndTs === null || $saleEndTs >= time());
    $tags = is_array($event['tags'] ?? null) ? $event['tags'] : [];
    $eventId = (int)($event['event_id'] ?? 0);

    $modalPayload = [
        'event_id' => $eventId,
        'title' => (string)($event['title'] ?? ''),
        'description' => (string)($event['description'] ?? ''),
        'creator_name' => (string)($event['creator_name'] ?? '-'),
        'location' => (string)($event['location'] ?? ''),
        'event_start' => $formatDate((string)($event['event_start'] ?? '')),
        'event_end' => $formatDate((string)($event['event_end'] ?? '')),
        'reg_start' => $regStartText,
        'reg_end' => $regEndText,
        'registered_count' => $registeredCount,
        'max_participant' => $maxParticipant,
        'pending_count' => (int)($event['pending_count'] ?? 0),
        'approved_count' => (int)($event['approved_count'] ?? $registeredCount),
        'checked_in_count' => (int)($event['checked_in_count'] ?? 0),
        'rejected_count' => (int)($event['rejected_count'] ?? 0),
        'own_registration_status' => (string)($event['own_registration_status'] ?? ''),
        'own_registered_at' => !empty($event['own_registered_at']) ? $formatDate((string)$event['own_registered_at']) : '-',
        'own_checked_in' => !empty($event['own_checked_in']) ? $formatDate((string)$event['own_checked_in']) : '-',
        'status_text' => $statusText,
        'status_class' => $statusClass,
        'already_requested' => $alreadyRequested,
        'is_not_open' => $isNotOpen,
        'is_closed' => $isClosed,
        'is_full' => $isFull,
        'images' => $galleryImages,
        'return_query' => $query,
        'return_start_at' => $startAt,
        'return_end_at' => $endAt,
        'return_show_all' => $showAllEvents ? '1' : '',
        'price' => $price,
        'compare_at_price' => $compareAtPrice,
        'currency' => (string)($event['currency'] ?? 'THB'),
        'sale_active' => $saleActive,
        'tags' => $tags,
        'is_favorite' => !empty($event['is_favorite']),
        'favorite_count' => (int)($event['favorite_count'] ?? 0),
        'rank_score' => (float)($event['rank_score'] ?? 0),
        'created_at' => (string)($event['created_at'] ?? ''),
    ];
    $eventModalPayloads[(string)$eventId] = $modalPayload;

    $eventCards[] = [
        'id' => $eventId,
        'event' => $event,
        'image' => $galleryImages[0],
        'date_short' => $formatDateShort((string)($event['event_start'] ?? '')),
        'status_text' => $statusText,
        'status_class' => $statusClass,
        'registered_count' => $registeredCount,
        'max_participant' => $maxParticipant,
        'capacity_percent' => $capacityPercent,
        'reg_start_text' => $regStartText,
        'reg_end_text' => $regEndText,
        'price' => $price,
        'compare_at_price' => $compareAtPrice,
        'currency' => (string)($event['currency'] ?? 'THB'),
        'sale_active' => $saleActive,
        'tags' => $tags,
        'favorite_count' => (int)($event['favorite_count'] ?? 0),
        'rank_score' => (float)($event['rank_score'] ?? 0),
        'pending_count' => (int)($event['pending_count'] ?? 0),
        'created_at' => (string)($event['created_at'] ?? ''),
        'event_start' => (string)($event['event_start'] ?? ''),
        'event_index' => $eventIndex,
    ];
}

$allEventCards = $eventCards;
usort($allEventCards, static function (array $left, array $right) use ($toTimestamp): int {
    $startCompare = $toTimestamp((string)$left['event_start']) <=> $toTimestamp((string)$right['event_start']);
    return $startCompare !== 0 ? $startCompare : ((int)$right['id'] <=> (int)$left['id']);
});

$trendingEventCards = $eventCards;
usort($trendingEventCards, static function (array $left, array $right) use ($toTimestamp): int {
    $scoreCompare = (float)$right['rank_score'] <=> (float)$left['rank_score'];
    if ($scoreCompare !== 0) return $scoreCompare;

    $favoriteCompare = (int)$right['favorite_count'] <=> (int)$left['favorite_count'];
    if ($favoriteCompare !== 0) return $favoriteCompare;

    $registeredCompare = (int)$right['registered_count'] <=> (int)$left['registered_count'];
    if ($registeredCompare !== 0) return $registeredCompare;

    return $toTimestamp((string)$left['event_start']) <=> $toTimestamp((string)$right['event_start']);
});
$trendingEventCards = array_slice($trendingEventCards, 0, 5);
$trendingIds = [];
foreach ($trendingEventCards as $card) {
    $trendingIds[(int)$card['id']] = true;
}

$newEventCards = array_values(array_filter(
    $eventCards,
    static fn(array $card): bool => !isset($trendingIds[(int)$card['id']])
));
if (empty($newEventCards)) {
    $newEventCards = $eventCards;
}
usort($newEventCards, static function (array $left, array $right) use ($toTimestamp): int {
    $leftCreated = $toTimestamp((string)$left['created_at']);
    $rightCreated = $toTimestamp((string)$right['created_at']);
    $createdCompare = $rightCreated <=> $leftCreated;
    return $createdCompare !== 0 ? $createdCompare : ((int)$right['id'] <=> (int)$left['id']);
});
$newEventCards = array_slice($newEventCards, 0, 6);

$renderEventCard = static function (array $card, string $modifier = '', bool $eager = false) use ($escape): void {
    $event = $card['event'] ?? [];
    $tags = is_array($card['tags'] ?? null) ? $card['tags'] : [];
    $price = $card['price'];
    $compareAtPrice = $card['compare_at_price'];
    $className = trim('event-card ' . $modifier);
    ?>
    <button
        type="button"
        class="<?= $escape($className) ?>"
        data-event-id="<?= (int)$card['id'] ?>">
        <div class="card-media">
            <img
                src="<?= $escape((string)$card['image']) ?>"
                alt="ภาพกิจกรรม <?= $escape((string)($event['title'] ?? '')) ?>"
                loading="<?= $eager ? 'eager' : 'lazy' ?>"
                decoding="async"
                fetchpriority="<?= $eager ? 'high' : 'low' ?>">
            <span class="date-chip"><i class="bx bx-calendar-event"></i> <?= $escape((string)$card['date_short']) ?></span>
        </div>

        <?php if (!empty($card['sale_active'])): ?>
            <span class="sale-chip">SALE</span>
        <?php endif; ?>

        <div class="card-body">
            <div class="card-head">
                <h3 class="card-title line-clamp-2"><?= $escape((string)($event['title'] ?? '')) ?></h3>
                <span class="status-badge <?= $escape((string)$card['status_class']) ?>"><?= $escape((string)$card['status_text']) ?></span>
            </div>

            <p class="card-desc line-clamp-3"><?= $escape((string)($event['description'] ?? '')) ?></p>

            <?php if (!empty($tags)): ?>
                <div class="event-tag-list" aria-label="Tags">
                    <?php foreach (array_slice($tags, 0, 4) as $tag): ?>
                        <span>#<?= $escape((string)($tag['name'] ?? '')) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($price !== null): ?>
                <div class="event-price">
                    <strong><?= $price > 0 ? number_format((float)$price, 2) . ' ' . $escape((string)$card['currency']) : 'FREE' ?></strong>
                    <?php if (!empty($card['sale_active'])): ?>
                        <del><?= number_format((float)$compareAtPrice, 2) ?></del>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="card-info">
                <div class="info-line"><i class="bx bx-user"></i><span>ผู้จัด: <?= $escape((string)($event['creator_name'] ?? '-')) ?></span></div>
                <div class="info-line"><i class="bx bx-map"></i><span class="line-clamp-2"><?= $escape((string)($event['location'] ?? '')) ?></span></div>
                <div class="info-line"><i class="bx bx-door-open"></i><span>รับสมัคร: <?= $escape((string)$card['reg_start_text']) ?> - <?= $escape((string)$card['reg_end_text']) ?></span></div>
            </div>

            <div class="capacity-box" style="--capacity: <?= (int)$card['capacity_percent'] ?>%;">
                <div class="capacity-top">
                    <span>จำนวนผู้เข้าร่วม</span>
                    <span class="capacity-number"><?= number_format((int)$card['registered_count']) ?>/<?= number_format((int)$card['max_participant']) ?> คน</span>
                </div>
                <div class="capacity-track" aria-hidden="true"><div class="capacity-bar"></div></div>
            </div>

            <div class="card-footer">
                <span><i class="bx bx-show"></i> ดูรายละเอียด</span>
                <span><i class="bx bx-chevron-right"></i></span>
            </div>
        </div>
    </button>
    <?php
};

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบขายบัตรกิจกรรม | Badomen</title>

    <link rel="stylesheet" href="/style/app.css">
    <link rel="stylesheet" href="/style/home_in.css?v=13">
    <link rel="stylesheet" href="/style/footer.css?v=2">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet"></noscript>
</head>

<body class="home-in-page">
    <?php require __DIR__ . '/header.php'; ?>

    <main class="home-in-main">
        <section class="home-in-shell hero-panel" aria-labelledby="homeInTitle">
            <div class="hero-copy">
                <div class="hero-eyebrow"><i class="bx bx-calendar-star"></i> EVENT TICKETING PLATFORM</div>
                <h1 id="homeInTitle" class="hero-title">ค้นหาอีเวนต์ <span>และจองสิทธิ์เข้าร่วม</span></h1>
                <p class="hero-subtitle">
                    รวมกิจกรรมจากผู้จัดหลายคนในหน้าเดียว พร้อมสถานะสมัคร จำนวนที่นั่ง รูปภาพกิจกรรม และรายละเอียดก่อนตัดสินใจเข้าร่วม
                </p>

                <div class="hero-meta" aria-label="จุดเด่นของระบบ">
                    <span class="meta-pill"><i class="bx bx-check-shield"></i> ตรวจสอบสิทธิ์ก่อนสมัคร</span>
                    <span class="meta-pill"><i class="bx bx-images"></i> รองรับหลายภาพต่อกิจกรรม</span>
                    <span class="meta-pill"><i class="bx bx-slider-alt"></i> ค้นหาและกรองด้วยช่วงเวลา</span>
                </div>
            </div>

            <aside class="hero-aside" aria-label="สรุปกิจกรรม">
                <div class="ticket-preview">
                    <div class="ticket-topline">
                        <span class="ticket-label">Available events</span>
                        <span class="ticket-code">LIVE</span>
                    </div>
                    <div class="ticket-number"><?= number_format($totalEvents) ?></div>
                    <p class="ticket-caption">รายการกิจกรรมที่สามารถแสดงบนหน้าสำหรับผู้ใช้ทั่วไป</p>

                    <div class="stat-row">
                        <div class="stat-box">
                            <strong><?= number_format($openEvents) ?></strong>
                            <span>เปิดรับ</span>
                        </div>
                        <div class="stat-box">
                            <strong><?= number_format($fullEvents) ?></strong>
                            <span>เต็มแล้ว</span>
                        </div>
                        <div class="stat-box">
                            <strong><?= $filtered ? 'ON' : 'OFF' ?></strong>
                            <span>ตัวกรอง</span>
                        </div>
                    </div>
                </div>
            </aside>
        </section>

        <section class="home-in-shell search-panel" aria-label="ค้นหากิจกรรม">
            <form method="GET" action="/home_in" class="search-form">
                <label class="field-block">
                    <span class="field-label"><i class="bx bx-search"></i> ค้นหากิจกรรม</span>
                    <span class="input-shell">
                        <i class="bx bx-search-alt-2"></i>
                        <input
                            type="text"
                            name="search"
                            value="<?= $escape($query) ?>"
                            placeholder="ชื่อกิจกรรม, สถานที่, รายละเอียด..."
                            class="event-input">
                    </span>
                </label>

                <label class="field-block">
                    <span class="field-label"><i class="bx bx-time-five"></i> เริ่มตั้งแต่</span>
                    <span class="input-shell">
                        <i class="bx bx-calendar"></i>
                        <input
                            type="datetime-local"
                            name="start_at"
                            value="<?= $escape($startAt) ?>"
                            class="event-input">
                    </span>
                </label>

                <label class="field-block">
                    <span class="field-label"><i class="bx bx-calendar-x"></i> สิ้นสุดไม่เกิน</span>
                    <span class="input-shell">
                        <i class="bx bx-calendar-check"></i>
                        <input
                            type="datetime-local"
                            name="end_at"
                            value="<?= $escape($endAt) ?>"
                            class="event-input">
                    </span>
                </label>

                <div class="search-actions">
                    <button type="submit" class="search-button"><i class="bx bx-filter-alt"></i> ค้นหา</button>
                    <a href="/home_in" class="reset-button" aria-label="ล้างตัวกรอง"><i class="bx bx-refresh"></i></a>
                </div>
            </form>
        </section>

        <?php if (!empty($errors) || !empty($successes)): ?>
            <section class="home-in-shell alert-stack" aria-live="polite">
                <?php if (!empty($errors)): ?>
                    <div class="alert-card alert-card--error">
                        <i class="bx bx-error-circle"></i>
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= $escape((string)$error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($successes)): ?>
                    <div class="alert-card alert-card--success">
                        <i class="bx bx-check-circle"></i>
                        <ul>
                            <?php foreach ($successes as $success): ?>
                                <li><?= $escape((string)$success) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if (empty($eventCards)): ?>
            <section class="home-in-shell" aria-labelledby="eventListTitle">
                <div class="section-head">
                    <div>
                        <span class="section-kicker"><i class="bx bx-calendar-x"></i> NO EVENTS</span>
                        <h2 id="eventListTitle" class="section-title">ไม่พบกิจกรรม</h2>
                        <p class="section-desc">
                            <?= $filtered
                                ? 'ไม่มีรายการที่ตรงกับคำค้นหาหรือช่วงเวลาที่เลือก ลองล้างตัวกรองหรือค้นหาด้วยคำที่กว้างขึ้น'
                                : 'ยังไม่มีกิจกรรมจากผู้ใช้อื่นในระบบตอนนี้' ?>
                        </p>
                    </div>
                    <span class="result-count"><i class="bx bx-list-check"></i> 0 รายการ</span>
                </div>

                <div class="empty-state">
                    <i class="bx bx-calendar-x"></i>
                    <h3>ไม่มีรายการให้แสดง</h3>
                    <p>เมื่อมีผู้จัดสร้างกิจกรรม ระบบจะแสดงกิจกรรมมาแรง กิจกรรมใหม่ และรายการทั้งหมดในหน้านี้อัตโนมัติ</p>
                </div>
            </section>
        <?php else: ?>
            <?php if (!$filtered): ?>
                <section class="home-in-shell discovery-section trending-section" aria-labelledby="trendingEventsTitle">
                    <div class="section-head section-head--with-action">
                        <div>
                            <span class="section-kicker"><i class="bx bx-trending-up"></i> TRENDING NOW</span>
                            <h2 id="trendingEventsTitle" class="section-title">กิจกรรมมาแรง</h2>
                            <p class="section-desc">จัดอันดับจากคะแนนความสนใจ จำนวนบันทึกกิจกรรม จำนวนผู้ได้รับอนุมัติ และการเช็กอิน เพื่อดันรายการที่มีสัญญาณใช้งานจริงขึ้นมาก่อน</p>
                        </div>
                        <div class="section-actions">
                            <span class="result-count"><i class="bx bx-hot"></i> <?= number_format(count($trendingEventCards)) ?> รายการเด่น</span>
                            <a href="#all-events" class="section-link"><i class="bx bx-layer"></i> ดูโซนทั้งหมด</a>
                        </div>
                    </div>

                    <div class="featured-grid">
                        <?php foreach ($trendingEventCards as $cardIndex => $card): ?>
                            <?php $renderEventCard($card, $cardIndex === 0 ? 'event-card--spotlight' : 'event-card--featured', $cardIndex === 0); ?>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="home-in-shell discovery-section new-section" aria-labelledby="newEventsTitle">
                    <div class="section-head section-head--with-action">
                        <div>
                            <span class="section-kicker"><i class="bx bx-sparkles"></i> NEW ARRIVALS</span>
                            <h2 id="newEventsTitle" class="section-title">กิจกรรมใหม่</h2>
                            <p class="section-desc">รายการที่เพิ่งถูกสร้างหรืออัปเดตล่าสุด เหมาะสำหรับให้ผู้ใช้เจอกิจกรรมใหม่โดยไม่ต้องไล่ดูทั้งหมดตั้งแต่ต้น</p>
                        </div>
                        <div class="section-actions">
                            <span class="result-count"><i class="bx bx-time-five"></i> ล่าสุด <?= number_format(count($newEventCards)) ?> รายการ</span>
                        </div>
                    </div>

                    <div class="new-events-rail">
                        <?php foreach ($newEventCards as $card): ?>
                            <?php $renderEventCard($card, 'event-card--compact'); ?>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <section id="all-events" class="home-in-shell all-events-section" aria-labelledby="eventListTitle">
                <div class="section-head section-head--with-action">
                    <div>
                        <span class="section-kicker"><i class="bx bx-list-ul"></i> <?= $filtered ? 'SEARCH RESULT' : 'ALL EVENTS' ?></span>
                        <h2 id="eventListTitle" class="section-title"><?= $filtered ? 'ผลการค้นหา' : 'กิจกรรมทั้งหมด' ?></h2>
                        <p class="section-desc">
                            <?= $filtered
                                ? 'แสดงรายการทั้งหมดที่ตรงกับคำค้นหาหรือช่วงเวลาที่เลือก พร้อมเปิดรายละเอียดและส่งคำขอเข้าร่วมได้เหมือนเดิม'
                                : 'ย้ายรายการทั้งหมดมาไว้ด้านล่าง และไม่โหลดออกมาเต็มหน้าตั้งแต่เริ่มต้น ผู้ใช้กดแสดงทั้งหมดเมื่ออยากไล่ดูครบทุกกิจกรรม' ?>
                        </p>
                    </div>
                    <div class="section-actions">
                        <span class="result-count"><i class="bx bx-list-check"></i> <?= number_format($totalEvents) ?> รายการ</span>
                        <?php if ($showPreviewOnly): ?>
                            <a href="<?= $escape($showAllUrl) ?>" class="section-link section-link--strong"><i class="bx bx-show"></i> แสดงทั้งหมด</a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($showAllEvents || $filtered): ?>
                    <div class="event-grid event-grid--all">
                        <?php foreach ($allEventCards as $card): ?>
                            <?php $renderEventCard($card); ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="show-all-panel">
                        <div class="show-all-copy">
                            <span class="show-all-icon"><i class="bx bx-layer-plus"></i></span>
                            <div>
                                <h3>ซ่อนรายการทั้งหมดไว้ก่อน เพื่อให้หน้าแรกเบาและอ่านง่ายขึ้น</h3>
                                <p>ตอนนี้หน้าแรกจะแสดงเฉพาะกิจกรรมมาแรงและกิจกรรมใหม่ก่อน ถ้าต้องการดูครบทุกกิจกรรมให้กดปุ่มแสดงทั้งหมด</p>
                            </div>
                        </div>
                        <a href="<?= $escape($showAllUrl) ?>" class="show-all-button"><i class="bx bx-grid-alt"></i> แสดงทั้งหมด <?= number_format($totalEvents) ?> รายการ</a>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>

    <script id="eventModalData" type="application/json"><?= json_encode(
        $eventModalPayloads,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?></script>

    <div id="eventModal" class="event-modal event-modal--long" aria-hidden="true">
        <div class="modal-backdrop" onclick="closeEventModal()"></div>

        <div class="modal-stage" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
            <article class="modal-card modal-card--long">
                <header class="modal-topbar">
                    <div class="modal-topbar-left">
                        <span id="modalStatus" class="modal-status status-badge--open"></span>
                        <span class="modal-topbar-note"><i class="bx bx-data"></i> ใช้ข้อมูลจาก users, events, registrations, event_images</span>
                    </div>
                    <button type="button" class="modal-close" onclick="closeEventModal()" aria-label="ปิดหน้าต่าง"><i class="bx bx-x"></i></button>
                </header>

                <div class="modal-scroll-area">
                    <section class="modal-hero-detail">
                        <div class="modal-title-block">
                            <p id="modalCreator" class="modal-creator"></p>
                            <h2 id="modalTitle" class="modal-title"></h2>
                            <p class="modal-subtitle">ตรวจสอบรายละเอียดกิจกรรม เงื่อนไขการสมัคร จำนวนที่นั่ง และรูปภาพทั้งหมดก่อนส่งคำขอเข้าร่วม</p>
                        </div>

                        <div class="modal-hero-actions">
                            <button type="button" class="btn-soft event-insight-trigger" onclick="generateEventInsight()"><i class="bx bx-bulb"></i> AI สรุปก่อนจอง</button>
                        </div>
                    </section>

                    <div class="modal-layout-long">
                        <div class="modal-content-col">
                            <section class="modal-panel modal-panel--media" aria-label="รูปภาพกิจกรรม">
                                <div class="main-image-wrap main-image-wrap--long">
                                    <img id="modalMainImage" src="<?= $escape($placeholderImage) ?>" alt="ภาพกิจกรรม" decoding="async">
                                    <span id="modalImageCounter" class="image-counter">1/1</span>
                                </div>
                                <div id="modalThumbs" class="modal-thumbs modal-thumbs--long"></div>
                            </section>

                            <section class="modal-panel">
                                <div class="modal-section-head">
                                    <h3><i class="bx bx-detail"></i> รายละเอียดกิจกรรม</h3>
                                    <span class="section-source">events.description</span>
                                </div>
                                <div id="modalDescription" class="modal-description modal-description--long"></div>
                            </section>

                            <section class="modal-panel">
                                <div class="modal-section-head">
                                    <h3><i class="bx bx-list-check"></i> ข้อมูลที่ระบบมีแล้ว</h3>
                                    <span class="section-source">ใช้ได้ทันที</span>
                                </div>
                                <div class="ready-grid">
                                    <div class="ready-card"><i class="bx bx-calendar-event"></i><strong>วันเวลาอีเวนต์</strong><span>ดึงจาก event_start / event_end</span></div>
                                    <div class="ready-card"><i class="bx bx-map"></i><strong>สถานที่</strong><span>ดึงจาก location</span></div>
                                    <div class="ready-card"><i class="bx bx-group"></i><strong>จำนวนที่นั่ง</strong><span>คำนวณจาก registrations</span></div>
                                    <div class="ready-card"><i class="bx bx-images"></i><strong>แกลเลอรี</strong><span>ดึงจาก event_images</span></div>
                                </div>
                            </section>

                            <section class="modal-panel">
                                <div class="modal-section-head">
                                    <h3><i class="bx bx-time"></i> ลำดับเวลา</h3>
                                    <span class="section-source">registration + event dates</span>
                                </div>
                                <div class="timeline-list">
                                    <div class="timeline-item"><span></span><div><strong>เปิดรับสมัคร</strong><p id="timelineRegStart"></p></div></div>
                                    <div class="timeline-item"><span></span><div><strong>ปิดรับสมัคร</strong><p id="timelineRegEnd"></p></div></div>
                                    <div class="timeline-item"><span></span><div><strong>เริ่มกิจกรรม</strong><p id="timelineEventStart"></p></div></div>
                                    <div class="timeline-item"><span></span><div><strong>สิ้นสุดกิจกรรม</strong><p id="timelineEventEnd"></p></div></div>
                                </div>
                            </section>

                            <section class="modal-panel">
                                <div class="modal-section-head">
                                    <h3><i class="bx bx-construction"></i> ระบบที่วางตำแหน่งไว้ก่อน</h3>
                                    <span class="section-source">ยังไม่ผูกฐานข้อมูล</span>
                                </div>
                                <div class="pending-feature-grid">
                                    <button type="button" class="pending-feature" disabled><i class="bx bx-qr"></i><strong>QR Ticket</strong><span>รอระบบออกบัตรหลังอนุมัติ</span></button>
                                    <button type="button" class="pending-feature" disabled><i class="bx bx-credit-card"></i><strong>Payment</strong><span>รอตาราง payments / orders</span></button>
                                    <button type="button" class="pending-feature" disabled><i class="bx bx-map-alt"></i><strong>Map route</strong><span>ตอนนี้มี location เป็นข้อความ</span></button>
                                    <button type="button" class="pending-feature" disabled><i class="bx bx-star"></i><strong>Reviews</strong><span>รอตาราง rating / comments</span></button>
                                    <button type="button" class="pending-feature" disabled><i class="bx bx-envelope"></i><strong>Email ticket</strong><span>รอระบบส่งอีเมล</span></button>
                                    <button type="button" class="pending-feature" disabled><i class="bx bx-id-card"></i><strong>Check-in page</strong><span>มี checked_in แล้ว แต่ยังไม่มีหน้าใช้งาน</span></button>
                                </div>
                            </section>
                        </div>

                        <aside class="modal-side-col">
                            <section class="modal-panel side-card side-card--sticky">
                                <div class="side-card-head">
                                    <h3>ข้อมูลสรุป</h3>
                                    <span id="modalEventCode" class="event-code"></span>
                                </div>

                                <div class="summary-grid">
                                    <div class="summary-item"><span><i class="bx bx-calendar-event"></i> วันเริ่มกิจกรรม</span><strong id="modalEventStart"></strong></div>
                                    <div class="summary-item"><span><i class="bx bx-calendar-x"></i> วันสิ้นสุดกิจกรรม</span><strong id="modalEventEnd"></strong></div>
                                    <div class="summary-item summary-item--wide"><span><i class="bx bx-map"></i> สถานที่</span><strong id="modalLocation"></strong></div>
                                    <div class="summary-item"><span><i class="bx bx-door-open"></i> เปิดรับสมัคร</span><strong id="modalRegStart"></strong></div>
                                    <div class="summary-item"><span><i class="bx bx-lock-alt"></i> ปิดรับสมัคร</span><strong id="modalRegEnd"></strong></div>
                                </div>

                                <div class="capacity-panel" id="modalCapacityBox">
                                    <div class="capacity-top">
                                        <span>จำนวนผู้เข้าร่วมที่อนุมัติแล้ว</span>
                                        <strong id="modalCapacity"></strong>
                                    </div>
                                    <div class="capacity-track" aria-hidden="true"><div id="modalCapacityBar" class="capacity-bar"></div></div>
                                    <div class="capacity-meta-row">
                                        <span id="modalCapacityPercent"></span>
                                        <span id="modalSeatLeft"></span>
                                    </div>
                                </div>

                                <div class="status-breakdown">
                                    <div><strong id="modalApprovedCount">0</strong><span>อนุมัติ</span></div>
                                    <div><strong id="modalPendingCount">0</strong><span>รออนุมัติ</span></div>
                                    <div><strong id="modalCheckedInCount">0</strong><span>เช็กอิน</span></div>
                                </div>

                                <div id="modalMyRegistration" class="my-registration-card"></div>

                                <form id="modalJoinForm" method="POST" action="/home_in" class="modal-join-form" onsubmit="return confirmSubmission()">
                                    <input type="hidden" name="_csrf" value="<?= $escape(csrfToken()) ?>">
                                    <input type="hidden" name="event_id" id="modalEventId" value="">
                                    <input type="hidden" name="return_query" id="modalReturnQuery" value="">
                                    <input type="hidden" name="return_start_at" id="modalReturnStartAt" value="">
                                    <input type="hidden" name="return_end_at" id="modalReturnEndAt" value="">
                                    <input type="hidden" name="return_show_all" id="modalReturnShowAll" value="">
                                    <button id="modalJoinBtn" type="submit" class="btn-primary"><i class="bx bx-send"></i> ขอเข้าร่วมกิจกรรม</button>
                                </form>
                            </section>

                            <section id="eventInsightCard" class="modal-panel event-insight-card">
                                <div class="event-insight-content">
                                    <div class="event-insight-head">
                                        <span class="event-insight-icon"><i class="bx bx-bulb"></i></span>
                                        <div>
                                            <span class="event-insight-kicker">BADOMEN EVENT INSIGHT</span>
                                            <h3>สรุปให้ก่อนตัดสินใจจอง</h3>
                                        </div>
                                    </div>
                                    <p class="event-insight-note">วิเคราะห์จากข้อมูลกิจกรรมจริงในระบบ โดยไม่สร้างรายละเอียดเพิ่มเติม</p>
                                    <button id="eventInsightButton" type="button" class="event-insight-button" onclick="generateEventInsight()">
                                        <i class="bx bx-bolt-circle"></i>
                                        <span>สร้าง Event Insight</span>
                                    </button>
                                    <div id="eventInsightResult" class="event-insight-result" aria-live="polite">
                                        กดปุ่มเพื่อดูไฮไลต์ กลุ่มคนที่เหมาะ และเช็กลิสต์ก่อนจอง
                                    </div>
                                </div>
                            </section>
                        </aside>
                    </div>
                </div>

                <footer class="modal-footer modal-footer--long">
                    <button type="button" onclick="closeEventModal()" class="btn-ghost"><i class="bx bx-left-arrow-alt"></i> ปิด</button>
                    <button id="modalFavoriteBtn" type="button" class="btn-ghost" onclick="toggleActiveFavorite()"><i class="bx bx-heart"></i> บันทึกกิจกรรม</button>
                    <button type="button" class="btn-ghost btn-ghost--disabled" disabled><i class="bx bx-share-alt"></i> แชร์กิจกรรม</button>
                </footer>
            </article>
        </div>
    </div>

    <?php require __DIR__ . '/footer.php'; ?>

    <script>
        const modalEl = document.getElementById('eventModal');
        const modalMainImage = document.getElementById('modalMainImage');
        const modalThumbs = document.getElementById('modalThumbs');
        const modalTitle = document.getElementById('modalTitle');
        const modalCreator = document.getElementById('modalCreator');
        const modalEventStart = document.getElementById('modalEventStart');
        const modalEventEnd = document.getElementById('modalEventEnd');
        const modalLocation = document.getElementById('modalLocation');
        const modalRegStart = document.getElementById('modalRegStart');
        const modalRegEnd = document.getElementById('modalRegEnd');
        const modalCapacity = document.getElementById('modalCapacity');
        const modalDescription = document.getElementById('modalDescription');
        const modalStatus = document.getElementById('modalStatus');
        const modalJoinBtn = document.getElementById('modalJoinBtn');
        const modalEventId = document.getElementById('modalEventId');
        const modalReturnQuery = document.getElementById('modalReturnQuery');
        const modalReturnStartAt = document.getElementById('modalReturnStartAt');
        const modalReturnEndAt = document.getElementById('modalReturnEndAt');
        const modalReturnShowAll = document.getElementById('modalReturnShowAll');
        const modalCapacityBar = document.getElementById('modalCapacityBar');
        const modalCapacityPercent = document.getElementById('modalCapacityPercent');
        const modalSeatLeft = document.getElementById('modalSeatLeft');
        const modalApprovedCount = document.getElementById('modalApprovedCount');
        const modalPendingCount = document.getElementById('modalPendingCount');
        const modalCheckedInCount = document.getElementById('modalCheckedInCount');
        const modalMyRegistration = document.getElementById('modalMyRegistration');
        const modalEventCode = document.getElementById('modalEventCode');
        const modalImageCounter = document.getElementById('modalImageCounter');
        const modalFavoriteBtn = document.getElementById('modalFavoriteBtn');
        const eventInsightButton = document.getElementById('eventInsightButton');
        const eventInsightResult = document.getElementById('eventInsightResult');
        const eventInsightCard = document.getElementById('eventInsightCard');
        const timelineRegStart = document.getElementById('timelineRegStart');
        const timelineRegEnd = document.getElementById('timelineRegEnd');
        const timelineEventStart = document.getElementById('timelineEventStart');
        const timelineEventEnd = document.getElementById('timelineEventEnd');
        const eventActionCsrf = <?= json_encode(csrfToken()) ?>;
        const eventFavoriteEndpoint = <?= json_encode(appUrl('/event-favorite'), JSON_UNESCAPED_SLASHES) ?>;
        const eventInsightEndpoint = <?= json_encode(appUrl('/event-insight'), JSON_UNESCAPED_SLASHES) ?>;
        const eventModalData = JSON.parse(document.getElementById('eventModalData')?.textContent || '{}');

        let activeEventData = null;
        let activeImages = [];
        let modalRenderToken = 0;

        function escapeHtml(value) {
            return String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        function plainText(value) {
            return String(value ?? '').replace(/\s+/g, ' ').trim();
        }

        function setMainImage(src, clickedThumb = null, index = 0) {
            modalMainImage.src = src;
            modalImageCounter.textContent = `${index + 1}/${Math.max(activeImages.length, 1)}`;

            const allThumbs = modalThumbs.querySelectorAll('[data-thumb]');
            allThumbs.forEach(el => el.classList.remove('thumb-active'));

            if (clickedThumb) {
                clickedThumb.classList.add('thumb-active');
            } else if (allThumbs[index]) {
                allThumbs[index].classList.add('thumb-active');
            }
        }

        function buildThumbs(images) {
            modalThumbs.replaceChildren();
            activeImages = images;
            const fragment = document.createDocumentFragment();

            images.forEach((img, index) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.setAttribute('data-thumb', '1');
                btn.className = 'modal-thumb';
                const image = document.createElement('img');
                image.src = img;
                image.alt = `ภาพกิจกรรม ${index + 1}`;
                image.loading = 'lazy';
                image.decoding = 'async';
                btn.appendChild(image);
                btn.addEventListener('click', () => setMainImage(img, btn, index));
                fragment.appendChild(btn);
            });

            modalThumbs.appendChild(fragment);
        }

        function registrationStatusLabel(status) {
            const map = {
                pending: 'รออนุมัติ',
                approved: 'อนุมัติแล้ว',
                rejected: 'ถูกปฏิเสธ',
                checked_in: 'เช็กอินแล้ว'
            };
            return map[status] || '';
        }

        function renderMyRegistration(eventData) {
            const status = eventData.own_registration_status || '';
            if (!status) {
                modalMyRegistration.className = 'my-registration-card my-registration-card--empty';
                modalMyRegistration.innerHTML = '<i class="bx bx-info-circle"></i><div><strong>ยังไม่มีคำขอของคุณ</strong><span>ส่งคำขอเข้าร่วมได้จากปุ่มด้านล่าง เมื่อกิจกรรมยังเปิดรับและที่นั่งยังไม่เต็ม</span></div>';
                return;
            }

            const label = registrationStatusLabel(status);
            modalMyRegistration.className = `my-registration-card my-registration-card--${escapeHtml(status)}`;
            modalMyRegistration.innerHTML = `
                <i class="bx bx-badge-check"></i>
                <div>
                    <strong>สถานะของคุณ: ${escapeHtml(label)}</strong>
                    <span>ส่งคำขอเมื่อ: ${escapeHtml(eventData.own_registered_at || '-')}</span>
                    ${eventData.own_checked_in && eventData.own_checked_in !== '-' ? `<span>เช็กอินเมื่อ: ${escapeHtml(eventData.own_checked_in)}</span>` : ''}
                </div>
            `;
        }

        function setJoinButton(eventData) {
            modalJoinBtn.disabled = false;
            modalJoinBtn.className = 'btn-primary';
            modalJoinBtn.innerHTML = '<i class="bx bx-send"></i> ขอเข้าร่วมกิจกรรม';

            if (eventData.already_requested) {
                modalJoinBtn.disabled = true;
                modalJoinBtn.className = 'btn-primary btn-disabled';
                modalJoinBtn.innerHTML = '<i class="bx bx-check"></i> มีคำขอในระบบแล้ว';
            } else if (eventData.is_not_open) {
                modalJoinBtn.disabled = true;
                modalJoinBtn.className = 'btn-primary btn-waiting';
                modalJoinBtn.innerHTML = '<i class="bx bx-time-five"></i> ยังไม่เปิดรับสมัคร';
            } else if (eventData.is_closed) {
                modalJoinBtn.disabled = true;
                modalJoinBtn.className = 'btn-primary btn-disabled';
                modalJoinBtn.innerHTML = '<i class="bx bx-lock-alt"></i> ปิดรับสมัครแล้ว';
            } else if (eventData.is_full) {
                modalJoinBtn.disabled = true;
                modalJoinBtn.className = 'btn-primary btn-full';
                modalJoinBtn.innerHTML = '<i class="bx bx-error"></i> จำนวนผู้ลงทะเบียนครบแล้ว';
            }
        }

        function renderFavoriteButton() {
            if (!activeEventData) return;
            const saved = Boolean(activeEventData.is_favorite);
            modalFavoriteBtn.classList.toggle('is-favorite', saved);
            modalFavoriteBtn.innerHTML = saved
                ? '<i class="bx bxs-heart"></i> บันทึกแล้ว'
                : '<i class="bx bx-heart"></i> บันทึกกิจกรรม';
        }

        async function toggleActiveFavorite() {
            if (!activeEventData?.event_id || modalFavoriteBtn.disabled) return;
            modalFavoriteBtn.disabled = true;
            try {
                const body = new URLSearchParams({
                    _csrf: eventActionCsrf,
                    event_id: String(activeEventData.event_id)
                });
                const response = await fetch(eventFavoriteEndpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                    body,
                    credentials: 'same-origin'
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok || !data.ok) throw new Error(data.error || 'favorite_failed');
                activeEventData.is_favorite = Boolean(data.saved);
                renderFavoriteButton();
            } catch (error) {
                modalFavoriteBtn.textContent = 'ไม่สามารถบันทึกได้';
            } finally {
                modalFavoriteBtn.disabled = false;
            }
        }

        function resetEventInsight() {
            eventInsightButton.disabled = false;
            eventInsightButton.classList.remove('is-loading');
            eventInsightButton.querySelector('span').textContent = 'สร้าง Event Insight';
            eventInsightResult.className = 'event-insight-result';
            eventInsightResult.textContent = 'กดปุ่มเพื่อดูไฮไลต์ กลุ่มคนที่เหมาะ และเช็กลิสต์ก่อนจอง';
        }

        async function generateEventInsight() {
            if (!activeEventData?.event_id || eventInsightButton.disabled) return;

            eventInsightButton.disabled = true;
            eventInsightButton.classList.add('is-loading');
            eventInsightButton.querySelector('span').textContent = 'กำลังวิเคราะห์...';
            eventInsightResult.className = 'event-insight-result is-loading';
            eventInsightResult.textContent = 'AI กำลังอ่านรายละเอียดกิจกรรม';
            eventInsightCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

            try {
                const body = new URLSearchParams({
                    _csrf: eventActionCsrf,
                    event_id: String(activeEventData.event_id)
                });
                const response = await fetch(eventInsightEndpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                    body,
                    credentials: 'same-origin'
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok || !data.ok) {
                    throw new Error(data.error || 'ไม่สามารถสร้างสรุปได้');
                }
                eventInsightResult.className = 'event-insight-result is-ready';
                eventInsightResult.textContent = data.text;
            } catch (error) {
                eventInsightResult.className = 'event-insight-result is-error';
                eventInsightResult.textContent = error.message || 'ระบบสรุปกิจกรรมยังไม่พร้อม กรุณาลองใหม่';
            } finally {
                eventInsightButton.disabled = false;
                eventInsightButton.classList.remove('is-loading');
                eventInsightButton.querySelector('span').textContent = 'วิเคราะห์อีกครั้ง';
            }
        }

        function openEventModal(eventData) {
            const renderToken = ++modalRenderToken;
            activeEventData = eventData;
            activeImages = Array.isArray(eventData.images) ? eventData.images : [];

            modalTitle.textContent = eventData.title || '-';
            modalStatus.className = `modal-status ${eventData.status_class || 'status-badge--open'}`;
            modalStatus.textContent = eventData.status_text || '-';
            modalEl.classList.add('is-loading', 'is-open');
            modalEl.setAttribute('aria-hidden', 'false');
            document.body.classList.add('modal-open');
            resetEventInsight();

            window.setTimeout(() => {
                if (renderToken !== modalRenderToken) return;
                hydrateEventModal(eventData, renderToken);
            }, 0);
        }

        function hydrateEventModal(eventData, renderToken) {
            const images = Array.isArray(eventData.images) && eventData.images.length ? eventData.images : [];
            const registered = Number(eventData.registered_count ?? 0);
            const max = Number(eventData.max_participant ?? 0);
            const percent = max > 0 ? Math.max(0, Math.min(100, Math.round((registered / max) * 100))) : 0;
            const seatLeft = Math.max(0, max - registered);

            modalTitle.textContent = eventData.title || '-';
            modalCreator.innerHTML = `<i class="bx bx-user"></i> ผู้จัด: ${escapeHtml(eventData.creator_name || '-')}`;
            modalEventStart.textContent = eventData.event_start || '-';
            modalEventEnd.textContent = eventData.event_end || '-';
            modalLocation.textContent = eventData.location || '-';
            modalRegStart.textContent = eventData.reg_start || '-';
            modalRegEnd.textContent = eventData.reg_end || '-';
            modalCapacity.textContent = `${registered}/${max} คน`;
            modalDescription.textContent = eventData.description || '-';
            modalEventCode.textContent = `EVT-${String(eventData.event_id || 0).padStart(5, '0')}`;

            modalCapacityBar.style.width = `${percent}%`;
            modalCapacityPercent.textContent = `${percent}%`;
            modalSeatLeft.textContent = max > 0 ? `เหลือ ${seatLeft} ที่` : 'ไม่จำกัดจำนวน';
            modalApprovedCount.textContent = eventData.approved_count ?? registered;
            modalPendingCount.textContent = eventData.pending_count ?? 0;
            modalCheckedInCount.textContent = eventData.checked_in_count ?? 0;

            modalEventId.value = eventData.event_id || '';
            modalReturnQuery.value = eventData.return_query || '';
            modalReturnStartAt.value = eventData.return_start_at || '';
            modalReturnEndAt.value = eventData.return_end_at || '';
            modalReturnShowAll.value = eventData.return_show_all || '';

            timelineRegStart.textContent = eventData.reg_start || '-';
            timelineRegEnd.textContent = eventData.reg_end || '-';
            timelineEventStart.textContent = eventData.event_start || '-';
            timelineEventEnd.textContent = eventData.event_end || '-';

            if (images[0]) {
                modalMainImage.src = images[0];
                modalImageCounter.textContent = `1/${images.length}`;
            }

            renderMyRegistration(eventData);
            setJoinButton(eventData);
            renderFavoriteButton();
            modalEl.classList.remove('is-loading');

            const renderThumbs = () => {
                if (renderToken === modalRenderToken) buildThumbs(images);
            };
            if ('requestIdleCallback' in window) {
                window.requestIdleCallback(renderThumbs, { timeout: 500 });
            } else {
                window.setTimeout(renderThumbs, 60);
            }
        }

        function closeEventModal() {
            modalRenderToken++;
            modalEl.classList.remove('is-open');
            modalEl.classList.remove('is-loading');
            modalEl.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
        }


        function confirmSubmission() {
            return confirm('ต้องการส่งคำขอเข้าร่วมกิจกรรมนี้จริงหรือไม่ ?');
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modalEl.classList.contains('is-open')) {
                closeEventModal();
            }
        });

        document.addEventListener('click', (event) => {
            const card = event.target.closest('[data-event-id]');
            if (!card) return;
            const eventData = eventModalData[card.dataset.eventId];
            if (eventData) openEventModal(eventData);
        });
    </script>
</body>
</html>
