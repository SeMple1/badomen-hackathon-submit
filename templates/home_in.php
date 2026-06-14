<?php
$events = $events ?? [];
$errors = $errors ?? [];
$successes = $successes ?? [];
$query = trim((string)($query ?? ''));
$startAt = trim((string)($startAt ?? ''));
$endAt = trim((string)($endAt ?? ''));
$favoritesOnly = (bool)($favoritesOnly ?? false);
$clearGuestFavorites = (bool)($clearGuestFavorites ?? false);
$viewerIsVip = (bool)($viewerIsVip ?? false);
$vipDiscountPerTicket = (float)($vipDiscountPerTicket ?? 59);

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

    $relativePath = ltrim(str_replace('\\', '/', $path), '/');
    $localPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    return is_file($localPath) ? '/' . $relativePath : $placeholderImage;
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
$hasSearchFilter = $query !== '' || $startAt !== '' || $endAt !== '';
$isFavoriteView = $favoritesOnly;
$filtered = $hasSearchFilter || $isFavoriteView;
$showDiscoveryZones = !$hasSearchFilter && !$isFavoriteView;

foreach ($events as $eventStat) {
    $registered = (int)($eventStat['registered_count'] ?? 0);
    $max = (int)($eventStat['max_participant'] ?? 0);
    $isFullStat = $max > 0 && $registered >= $max;

    $regStart = $parseDbDateTime((string)($eventStat['reg_start'] ?? ''), $tz, false);
    $regEnd = $parseDbDateTime((string)($eventStat['reg_end'] ?? ''), $tz, true);
    $isNotOpenStat = $regStart !== null && $now < $regStart;
    $isClosedStat = $regEnd !== null && $now > $regEnd;
    $ownStatusStat = str_replace([' ', '-'], '_', strtolower(trim((string)($eventStat['own_registration_status'] ?? ''))));
    $ownPaymentStat = str_replace([' ', '-'], '_', strtolower(trim((string)($eventStat['own_payment_status'] ?? ''))));
    $wasRejoinableStat = in_array($ownStatusStat, ['rejected', 'cancelled', 'canceled', 'refunded', 'expired'], true)
        || in_array($ownPaymentStat, ['refunded', 'cancelled', 'canceled', 'expired'], true);
    $already = (int)($eventStat['already_requested'] ?? 0) > 0 && !$wasRejoinableStat;

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
if ($favoritesOnly) {
    $showAllUrlParams['favorites'] = '1';
}
$showAllUrlParams['show_all'] = '1';
$showAllUrl = '/home_in?' . http_build_query($showAllUrlParams) . '#all-events';
$forceAllEvents = $showAllEvents || $filtered;
$showPreviewOnly = !$forceAllEvents;

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
    $ownStatus = str_replace([' ', '-'], '_', strtolower(trim((string)($event['own_registration_status'] ?? ''))));
    $ownPaymentStatus = str_replace([' ', '-'], '_', strtolower(trim((string)($event['own_payment_status'] ?? ''))));
    $wasRejoinable = in_array($ownStatus, ['rejected', 'cancelled', 'canceled', 'refunded', 'expired'], true)
        || in_array($ownPaymentStatus, ['refunded', 'cancelled', 'canceled', 'expired'], true);
    $alreadyRequested = (int)($event['already_requested'] ?? 0) > 0 && !$wasRejoinable;

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
    } elseif ($wasRejoinable) {
        $statusText = 'เคยยกเลิก สมัครใหม่ได้';
        $statusClass = 'status-badge--rejoin';
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
    $createdAtDT = $parseDbDateTime((string)($event['created_at'] ?? ''), $tz, false);
    $isNewEvent = $createdAtDT instanceof DateTimeImmutable && $createdAtDT >= $now->modify('-1 day');

    $modalPayload = [
        'event_id' => $eventId,
        'title' => (string)($event['title'] ?? ''),
        'description' => (string)($event['description'] ?? ''),
        'creator_name' => (string)($event['creator_name'] ?? '-'),
        'location' => (string)($event['location'] ?? ''),
        'latitude' => isset($event['latitude']) ? (float)$event['latitude'] : null,
        'longitude' => isset($event['longitude']) ? (float)$event['longitude'] : null,
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
        'own_registration_id' => (int)($event['own_registration_id'] ?? 0),
        'own_payment_status' => (string)($event['own_payment_status'] ?? ''),
        'own_payment_expires_at' => (string)($event['own_payment_expires_at'] ?? ''),
        'own_payment_method' => (string)($event['own_payment_method'] ?? ''),
        'own_total_amount' => isset($event['own_total_amount']) ? (float)$event['own_total_amount'] : null,
        'server_now' => $now->format('Y-m-d H:i:s'),
        'status_text' => $statusText,
        'status_class' => $statusClass,
        'already_requested' => $alreadyRequested,
        'can_rejoin' => $wasRejoinable && !$alreadyRequested,
        'previous_registration_status' => $ownStatus,
        'is_not_open' => $isNotOpen,
        'is_closed' => $isClosed,
        'is_full' => $isFull,
        'images' => $galleryImages,
        'return_query' => $query,
        'return_start_at' => $startAt,
        'return_end_at' => $endAt,
        'return_show_all' => $showAllEvents ? '1' : '',
        'return_favorites_only' => $favoritesOnly ? '1' : '',
        'price' => $price,
        'compare_at_price' => $compareAtPrice,
        'currency' => (string)($event['currency'] ?? 'THB'),
        'sale_active' => $saleActive,
        'tags' => $tags,
        'is_favorite' => !empty($event['is_favorite']),
        'favorite_count' => (int)($event['favorite_count'] ?? 0),
        'rank_score' => (float)($event['rank_score'] ?? 0),
        'ticket_mode' => (string)($event['ticket_mode'] ?? 'general'),
        'seat_selection_mode' => (string)($event['seat_selection_mode'] ?? 'manual'),
        'max_tickets_per_user' => max(1, min(2, (int)($event['max_tickets_per_user'] ?? 1))),
        'hold_minutes' => (int)($event['hold_minutes'] ?? 15),
        'ticket_zones' => is_array($event['ticket_zones'] ?? null) ? $event['ticket_zones'] : [],
        'seat_map' => is_array($event['seat_map'] ?? null) ? $event['seat_map'] : [],
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
        'is_new' => $isNewEvent,
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
$trendingEventCards = array_slice($trendingEventCards, 0, 6);
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

$priorityCard = null;
if (!empty($allEventCards) && $forceAllEvents) {
    $priorityCard = $allEventCards[0];
} elseif (!empty($trendingEventCards)) {
    $priorityCard = $trendingEventCards[0];
} elseif (!empty($eventCards)) {
    $priorityCard = $eventCards[0];
}
$priorityImage = is_array($priorityCard ?? null) ? (string)($priorityCard['image'] ?? '') : '';
$shouldPreloadPriorityImage = $priorityImage !== '' && strpos($priorityImage, 'data:image/') !== 0;

$listKicker = $isFavoriteView ? 'SAVED EVENTS' : ($filtered ? 'SEARCH RESULT' : 'ALL EVENTS');
$listTitle = $isFavoriteView ? 'รายการโปรดของฉัน' : ($filtered ? 'ผลการค้นหา' : 'กิจกรรมทั้งหมด');
$listDesc = $isFavoriteView
    ? 'แสดงเฉพาะกิจกรรมที่คุณบันทึกไว้ โดยซ่อนกิจกรรมมาแรงและกิจกรรมใหม่เพื่อให้เลือกดูรายการโปรดได้ตรงจุด'
    : ($filtered
        ? 'แสดงรายการทั้งหมดที่ตรงกับคำค้นหาหรือช่วงเวลาที่เลือก พร้อมเปิดรายละเอียดและส่งคำขอเข้าร่วมได้เหมือนเดิม'
        : 'ย้ายรายการทั้งหมดมาไว้ด้านล่าง และไม่โหลดออกมาเต็มหน้าตั้งแต่เริ่มต้น ผู้ใช้กดแสดงทั้งหมดเมื่ออยากไล่ดูครบทุกกิจกรรม');

$renderEventCard = static function (array $card, string $modifier = '', bool $eager = false) use ($escape): void {
    $event = $card['event'] ?? [];
    $tags = is_array($card['tags'] ?? null) ? $card['tags'] : [];
    $price = $card['price'];
    $compareAtPrice = $card['compare_at_price'];
    $isFavorite = !empty($event['is_favorite']);
    $favoriteCount = (int)($card['favorite_count'] ?? 0);
    $className = trim('event-card ' . $modifier);
    if (!empty($card['is_new'])) {
        $className .= ' event-card--new';
    }
    ?>
    <button
        type="button"
        class="<?= $escape($className) ?>"
        data-event-id="<?= (int)$card['id'] ?>"
        data-created-at="<?= $escape((string)($card['created_at'] ?? '')) ?>">
        <div class="card-media">
            <img
                src="<?= $escape((string)$card['image']) ?>"
                alt="ภาพกิจกรรม <?= $escape((string)($event['title'] ?? '')) ?>"
                loading="<?= $eager ? 'eager' : 'lazy' ?>"
                decoding="async"
                fetchpriority="<?= $eager ? 'high' : 'low' ?>">
            <span class="date-chip"><i class="bx bx-calendar-event"></i> <?= $escape((string)$card['date_short']) ?></span>
            <?php if (!empty($card['is_new'])): ?>
                <span class="new-event-chip"><i class="bx bx-sparkles"></i> ใหม่</span>
            <?php endif; ?>
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
                <span class="card-favorite-state <?= $isFavorite ? 'is-favorite' : '' ?>" data-card-favorite-state>
                    <i class="bx <?= $isFavorite ? 'bxs-heart' : 'bx-heart' ?>"></i>
                    <span data-card-favorite-count><?= number_format($favoriteCount) ?></span>
                </span>
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
    <?php if ($shouldPreloadPriorityImage): ?>
        <link rel="preload" as="image" href="<?= $escape($priorityImage) ?>" fetchpriority="high">
    <?php endif; ?>
    <link rel="preload" as="image" href="/assets/event.png?v=1" fetchpriority="high">
    <link rel="stylesheet" href="/style/home_in.css?v=20">
    <link rel="stylesheet" href="/style/event-ticket-modal.css?v=1" media="print" onload="this.media='all'">
    <link rel="stylesheet" href="/style/footer.css?v=2" media="print" onload="this.media='all'">
    <link rel="preconnect" href="https://unpkg.com" crossorigin>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript>
        <link rel="stylesheet" href="/style/event-ticket-modal.css?v=1">
        <link rel="stylesheet" href="/style/footer.css?v=2">
        <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    </noscript>
</head>

<body class="home-in-page">
    <?php require __DIR__ . '/header.php'; ?>

    <main class="home-in-main">
        <section
            class="home-in-shell hero-panel hero-panel--with-bg"
            aria-labelledby="homeInTitle"
            style="--home-in-hero-bg-image: url('/assets/event.png?v=1');">
            <div class="hero-copy">
                <h1 id="homeInTitle" class="hero-title">ค้นหาอีเวนต์ <span>และจองสิทธิ์เข้าร่วม</span></h1>
                <p class="hero-subtitle">
                    รวมกิจกรรมจากผู้จัดหลายคนในหน้าเดียว พร้อมสถานะสมัคร จำนวนที่นั่ง รูปภาพกิจกรรม และรายละเอียดก่อนตัดสินใจเข้าร่วม
                </p>

                <div class="hero-action-row" aria-label="ทางลัดในหน้าแรก">
                    <a href="#trending-events" class="hero-cta hero-cta--primary">
                        <i class="bx bx-trending-up"></i>
                        ดูกิจกรรมมาแรง
                    </a>
                    <a href="#homeInSearchForm" class="hero-cta hero-cta--ghost">
                        <i class="bx bx-search"></i>
                        ค้นหากิจกรรม
                    </a>
                    <span class="hero-live-pill">
                        <i class="bx bx-radio-circle-marked"></i>
                        อัปเดตสดทุก 12 วินาที
                    </span>
                </div>

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

                    <?php if (!empty($trendingEventCards)): ?>
                        <?php $heroTrending = $trendingEventCards[0]; ?>
                        <div class="hero-trending-card">
                            <span class="hero-trending-card__label"><i class="bx bx-hot"></i> กำลังมาแรง</span>
                            <strong><?= $escape((string)($heroTrending['event']['title'] ?? 'กิจกรรมเด่น')) ?></strong>
                            <small>
                                <?= number_format((int)($heroTrending['favorite_count'] ?? 0)) ?> บันทึก
                                · <?= number_format((int)($heroTrending['registered_count'] ?? 0)) ?> ผู้สมัคร
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
            </aside>
        </section>

        <section class="home-in-shell search-panel" aria-label="ค้นหากิจกรรม">
            <form method="GET" action="/home_in" class="search-form" id="homeInSearchForm">
                <label class="field-block field-block--keyword">
                    <span class="field-label"><i class="bx bx-search"></i> ค้นหากิจกรรม</span>
                    <span class="keyword-row">
                        <span class="input-shell">
                            <i class="bx bx-search-alt-2"></i>
                            <input
                                type="text"
                                name="search"
                                value="<?= $escape($query) ?>"
                                placeholder="ชื่อกิจกรรม, สถานที่, รายละเอียด..."
                                class="event-input">
                        </span>
                        <button type="submit" class="search-button"><i class="bx bx-filter-alt"></i> ค้นหา</button>
                        <a href="/home_in" class="reset-button" aria-label="ล้างตัวกรอง"><i class="bx bx-refresh"></i></a>
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
                            data-server-today="<?= $escape($now->format('Y-m-d')) ?>"
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
                            data-server-today="<?= $escape($now->format('Y-m-d')) ?>"
                            class="event-input">
                    </span>
                </label>

                <label
                    class="favorite-filter <?= $favoritesOnly ? 'is-active' : '' ?>"
                    id="favoriteFilterToggle"
                    aria-pressed="<?= $favoritesOnly ? 'true' : 'false' ?>">
                    <input
                        type="checkbox"
                        name="favorites"
                        value="1"
                        <?= $favoritesOnly ? 'checked' : '' ?>
                        autocomplete="off">
                    <i class="bx <?= $favoritesOnly ? 'bxs-heart' : 'bx-heart' ?>"></i>
                    <span>
                        <strong>รายการโปรดเท่านั้น</strong>
                        <small><?= $favoritesOnly ? 'กำลังแสดงเฉพาะรายการโปรด' : 'คลิกเพื่อแสดงรายการที่บันทึกไว้' ?></small>
                    </span>
                </label>

                <div class="view-switcher" aria-label="สลับรูปแบบการแสดงการ์ด">
                    <span class="view-switcher-label">มุมมอง</span>
                    <button type="button" class="view-button" data-card-view="grid" aria-pressed="true" title="Grid View"><i class="bx bx-grid-alt"></i><span>Grid</span></button>
                    <button type="button" class="view-button" data-card-view="dense" aria-pressed="false" title="Dense Grid"><i class="bx bx-grid-small"></i><span>Dense</span></button>
                    <button type="button" class="view-button" data-card-view="list" aria-pressed="false" title="List View"><i class="bx bx-list-ul"></i><span>List</span></button>
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
            <?php if ($showDiscoveryZones): ?>
                <section id="trending-events" class="home-in-shell discovery-section trending-section" aria-labelledby="trendingEventsTitle">
                    <div class="section-head section-head--with-action">
                        <div>
                            <span class="section-kicker"><i class="bx bx-trending-up"></i> TRENDING NOW</span>
                            <h2 id="trendingEventsTitle" class="section-title">กิจกรรมมาแรง</h2>
                            <p class="section-desc">จัดอันดับจากคะแนนความสนใจ จำนวนบันทึกกิจกรรม จำนวนผู้ได้รับอนุมัติ และการเช็กอิน เพื่อดันรายการที่มีสัญญาณใช้งานจริงขึ้นมาก่อน</p>
                        </div>
                        <div class="section-actions">
                            <span class="result-count"><i class="bx bx-hot"></i> <?= number_format(count($trendingEventCards)) ?> รายการเด่น</span>
                            <a href="#all-events" class="section-link"><i class="bx bx-layer"></i> ดูทั้งหมด</a>
                        </div>
                    </div>

                    <div class="featured-grid" data-card-list="trending">
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

                    <div class="new-events-rail" data-card-list="new">
                        <?php foreach ($newEventCards as $card): ?>
                            <?php $renderEventCard($card, 'event-card--compact'); ?>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <section id="all-events" class="home-in-shell all-events-section" aria-labelledby="eventListTitle">
                <div class="section-head section-head--with-action">
                    <div>
                        <span class="section-kicker"><i class="bx bx-list-ul"></i> <?= $escape($listKicker) ?></span>
                        <h2 id="eventListTitle" class="section-title"><?= $escape($listTitle) ?></h2>
                        <p class="section-desc"><?= $escape($listDesc) ?></p>
                    </div>
                    <div class="section-actions">
                        <span class="live-sync-badge" data-live-sync-status><i class="bx bx-radio-circle-marked"></i> Live</span>
                        <span class="result-count"><i class="bx bx-list-check"></i> <?= number_format($totalEvents) ?> รายการ</span>
                        <?php if ($showPreviewOnly): ?>
                            <a href="<?= $escape($showAllUrl) ?>" class="section-link section-link--strong"><i class="bx bx-show"></i> แสดงทั้งหมด</a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($forceAllEvents): ?>
                    <div class="event-grid event-grid--all" data-card-list="all">
                        <?php foreach ($allEventCards as $cardIndex => $card): ?>
                            <?php $renderEventCard($card, '', $cardIndex === 0); ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="show-all-panel">

                        <a href="<?= $escape($showAllUrl) ?>" class="show-all-button"><i class="bx bx-grid-alt"></i> แสดงทั้งหมด <?= number_format($totalEvents) ?> รายการ</a>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>

    <?php require __DIR__ . '/partials/event_ticket_modal.php'; ?>

    <div class="home-event-chat" data-event-chatbot data-is-vip="<?= $viewerIsVip ? '1' : '0' ?>">
        <button type="button" class="home-event-chat__toggle" aria-label="เปิดผู้ช่วยค้นหากิจกรรม">
            <i class="bx bx-message-rounded-dots"></i><span>ช่วยหากิจกรรม</span>
        </button>
        <section class="home-event-chat__panel" hidden>
            <header>
                <div><strong>ผู้ช่วยกิจกรรม</strong><small>ค้นหาและสรุปกิจกรรมในระบบ</small></div>
                <button type="button" data-chat-close aria-label="ปิด"><i class="bx bx-x"></i></button>
            </header>
            <div class="home-event-chat__messages">
                <p>ลองพิมพ์ “หากิจกรรมดนตรี” หรือ “สรุปกิจกรรม Miku Festival”</p>
            </div>
            <div class="home-event-chat__suggestions">
                <button type="button" data-chat-prompt="มีกิจกรรมอะไรบ้าง">กิจกรรมทั้งหมด</button>
                <button type="button" data-chat-prompt="หากิจกรรมฟรี">กิจกรรมฟรี</button>
                <button type="button" data-chat-prompt="แนะนำกิจกรรมใกล้เข้ามา">ใกล้เข้ามา</button>
                <button type="button" data-chat-prompt="สรุปกิจกรรมที่น่าไปที่สุด">สรุปให้หน่อย</button>
                <button type="button" data-chat-prompt="มีงานดนตรีหรือคอนเสิร์ตไหม">ดนตรี</button>
                <button type="button" data-chat-prompt="มีเวิร์กช็อปไหม">เวิร์กช็อป</button>
            </div>
            <form>
                <input type="text" name="message" maxlength="200" placeholder="ถามเกี่ยวกับกิจกรรม...">
                <button type="submit" aria-label="ส่ง"><i class="bx bx-send"></i></button>
            </form>
        </section>
    </div>

    <?php require __DIR__ . '/footer.php'; ?>

    <script>
    <?php if ($clearGuestFavorites): ?>
    localStorage.removeItem('badomen_saved_events');
    <?php endif; ?>
(() => {
    const modalEl = document.getElementById('eventModal');
    if (!modalEl) return;

    const parseJson = (id, fallback) => {
        try {
            return JSON.parse(document.getElementById(id)?.textContent || fallback);
        } catch (_) {
            return JSON.parse(fallback);
        }
    };

    const eventModalData = parseJson('eventModalData', '{}');
    const config = parseJson('eventModalConfig', '{}');
    config.favoriteEndpoint = '/home_in';
    config.paymentEndpoint = config.paymentEndpoint || '/home_in';
    const $ = (id) => document.getElementById(id);

    const refs = {
        shell: modalEl.querySelector('.ticket-modal__shell'),
        mainImage: $('modalMainImage'),
        thumbs: $('modalThumbs'),
        title: $('modalTitle'),
        creator: $('modalCreator'),
        eventStart: $('modalEventStart'),
        eventEnd: $('modalEventEnd'),
        location: $('modalLocation'),
        regStart: $('modalRegStart'),
        regEnd: $('modalRegEnd'),
        capacity: $('modalCapacity'),
        description: $('modalDescription'),
        status: $('modalStatus'),
        eventCode: $('modalEventCode'),
        joinBtn: $('modalJoinBtn'),
        eventId: $('modalEventId'),
        returnQuery: $('modalReturnQuery'),
        returnStartAt: $('modalReturnStartAt'),
        returnEndAt: $('modalReturnEndAt'),
        returnShowAll: $('modalReturnShowAll'),
        capacityBar: $('modalCapacityBar'),
        capacityPercent: $('modalCapacityPercent'),
        seatLeft: $('modalSeatLeft'),
        approvedCount: $('modalApprovedCount'),
        pendingCount: $('modalPendingCount'),
        checkedInCount: $('modalCheckedInCount'),
        myRegistration: $('modalMyRegistration'),
        imageCounter: $('modalImageCounter'),
        favoriteBtn: $('modalFavoriteBtn'),
        ticketModeText: $('ticketModeText'),
        ticketLimitText: $('ticketLimitText'),
        zonePanel: $('ticketZonePanel'),
        zoneList: $('ticketZoneList'),
        seatPanel: $('ticketSeatPanel'),
        seatMap: $('ticketSeatMap'),
        randomPanel: $('ticketRandomPanel'),
        quantityPicker: $('quantityPicker'),
        selectedList: $('ticketSelectedList'),
        totalPrice: $('ticketTotalPrice'),
        vipSaving: $('ticketVipSaving'),
        ticketModeInput: $('ticketModeInput'),
        selectionModeInput: $('ticketSelectionModeInput'),
        zoneInput: $('ticketZoneInput'),
        quantityInput: $('ticketQuantityInput'),
        seatIdsInput: $('ticketSeatIdsInput'),
        stepSelect: $('ticketStepSelect'),
        stepReserve: $('ticketStepReserve'),
        stepPayment: $('ticketStepPayment'),
        stepTicket: $('ticketStepTicket'),
        paymentPanel: $('ticketPaymentPanel'),
        paymentReserveState: $('paymentReserveState'),
        paymentRegistrationId: $('paymentRegistrationId'),
        paymentAmount: $('paymentAmount'),
        paymentExpiresAt: $('paymentExpiresAt'),
        paymentCountdown: $('paymentCountdown'),
        paymentMethods: document.querySelectorAll('[data-payment-method]'),
        paymentMockDetail: $('paymentMockDetail'),
        mockPayButton: $('mockPayButton'),
        paymentMessage: $('paymentMessage')
    };
    refs.miniMap = $('modalMiniMap');
    refs.mapPanel = $('modalMapPanel');
    let modalMap = null;
    let modalMapMarker = null;

    const moneyFormatters = new Map();
    const idle = window.requestIdleCallback
        ? (fn) => window.requestIdleCallback(fn, { timeout: 220 })
        : (fn) => window.setTimeout(fn, 16);

    let activeEventData = null;
    let activeImages = [];
    let selectedSeatIds = new Set();
    let selectedZoneId = null;
    let selectedQuantity = 1;
    let renderToken = 0;
    let lastSeatRenderKey = '';
    let activeReservation = null;
    let activePaymentMethod = 'promptpay';
    let paymentTimer = null;
    const wait = (milliseconds) => new Promise((resolve) => window.setTimeout(resolve, milliseconds));

    let zoneMap = new Map();
    let seatMap = new Map();
    let seatsByZone = new Map();

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function safeColor(value) {
        const color = String(value || '').trim();
        return /^#[0-9a-f]{3}([0-9a-f]{3})?$/i.test(color) ? color : '#38bdf8';
    }

    function money(value, currency = 'THB') {
        const n = Number(value || 0);
        if (n <= 0) return 'ฟรี';
        const code = currency || 'THB';
        if (!moneyFormatters.has(code)) {
            moneyFormatters.set(code, new Intl.NumberFormat('th-TH', {
                style: 'currency',
                currency: code,
                maximumFractionDigits: 0
            }));
        }
        return moneyFormatters.get(code).format(n).replace('THB', 'บาท');
    }

    function normalizeLimit(value) {
        const n = Number(value || 1);
        return Math.max(1, Math.min(2, Number.isFinite(n) ? n : 1));
    }

    const validCardViews = new Set(['grid', 'dense', 'list']);
    const cardViewKey = 'badomen.home_in.card_view';

    function currentCardView() {
        const saved = localStorage.getItem(cardViewKey) || 'grid';
        return validCardViews.has(saved) ? saved : 'grid';
    }

    function applyCardView(view) {
        const selected = validCardViews.has(view) ? view : 'grid';
        localStorage.setItem(cardViewKey, selected);
        document.body.classList.remove('view-grid', 'view-dense', 'view-list');
        document.body.classList.add(`view-${selected}`);
        document.querySelectorAll('[data-card-view]').forEach((button) => {
            const active = button.dataset.cardView === selected;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    function hydrateCardImageOrientation(root = document) {
        root.querySelectorAll('.card-media img:not([data-orientation-ready])').forEach((img) => {
            const mark = () => {
                if (!img.naturalWidth || !img.naturalHeight) return;
                img.dataset.orientationReady = '1';
                img.classList.toggle('is-landscape', img.naturalWidth >= img.naturalHeight);
                img.classList.toggle('is-portrait', img.naturalWidth < img.naturalHeight);
            };
            if (img.complete) mark();
            else img.addEventListener('load', mark, { once: true });
        });
    }

    function setText(ref, value) {
        if (ref) ref.textContent = value ?? '';
    }

    function registrationStatusLabel(status) {
        const map = {
            pending: 'รอชำระ/รออนุมัติ',
            approved: 'อนุมัติแล้ว',
            rejected: 'ยกเลิกแล้ว',
            checked_in: 'เช็กอินแล้ว',
            cancelled: 'ยกเลิกแล้ว',
            refunded: 'คืนเงินแล้ว'
        };
        return map[status] || '';
    }

    function isRejoinableStatus(status, paymentStatus = '') {
        const normalizedStatus = String(status || '').trim().toLowerCase().replace(/[\s-]+/g, '_');
        const normalizedPayment = String(paymentStatus || '').trim().toLowerCase().replace(/[\s-]+/g, '_');
        return ['rejected', 'cancelled', 'canceled', 'refunded', 'expired'].includes(normalizedStatus)
            || ['refunded', 'cancelled', 'canceled', 'expired'].includes(normalizedPayment);
    }

    function parseServerTime(value) {
        const raw = String(value || '').trim();
        if (!raw) return null;
        const normalized = raw.includes('T') ? raw : raw.replace(' ', 'T');
        const date = new Date(normalized + (/[zZ]|[+-]\d{2}:?\d{2}$/.test(normalized) ? '' : '+07:00'));
        return Number.isNaN(date.getTime()) ? null : date;
    }

    function updateTicketSteps(state) {
        const map = {
            select: refs.stepSelect,
            reserve: refs.stepReserve,
            payment: refs.stepPayment,
            ticket: refs.stepTicket
        };
        Object.values(map).forEach((el) => el?.classList.remove('is-active', 'is-done', 'is-loading'));
        if (state === 'select') {
            map.select?.classList.add('is-active');
            return;
        }
        if (state === 'reserve') {
            map.select?.classList.add('is-done');
            map.reserve?.classList.add('is-active', 'is-loading');
            return;
        }
        if (state === 'payment') {
            map.select?.classList.add('is-done');
            map.reserve?.classList.add('is-done');
            map.payment?.classList.add('is-active');
            return;
        }
        if (state === 'ticket') {
            map.select?.classList.add('is-done');
            map.reserve?.classList.add('is-done');
            map.payment?.classList.add('is-done');
            map.ticket?.classList.add('is-active');
        }
    }

    function stopPaymentTimer() {
        if (paymentTimer) window.clearInterval(paymentTimer);
        paymentTimer = null;
    }

    function startPaymentTimer(expiresAt, serverNow) {
        stopPaymentTimer();
        const expireDate = parseServerTime(expiresAt);
        const serverDate = parseServerTime(serverNow) || new Date();
        if (!expireDate) {
            setText(refs.paymentCountdown, '--:--');
            return;
        }
        const clientStarted = Date.now();
        const serverStarted = serverDate.getTime();
        const render = () => {
            const estimatedServerNow = serverStarted + (Date.now() - clientStarted);
            const remainMs = expireDate.getTime() - estimatedServerNow;
            if (remainMs <= 0) {
                setText(refs.paymentCountdown, 'หมดเวลา');
                if (refs.mockPayButton) refs.mockPayButton.disabled = true;
                if (refs.paymentMessage) refs.paymentMessage.textContent = 'หมดเวลาชำระเงินแล้ว กรุณาปิดหน้าต่างแล้วจองใหม่';
                stopPaymentTimer();
                return;
            }
            const totalSeconds = Math.ceil(remainMs / 1000);
            const minutes = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
            const seconds = String(totalSeconds % 60).padStart(2, '0');
            setText(refs.paymentCountdown, `${minutes}:${seconds}`);
        };
        render();
        paymentTimer = window.setInterval(render, 1000);
    }

    function renderPaymentMethodDetail(method) {
        if (!refs.paymentMockDetail) return;
        const data = {
            promptpay: ['PromptPay', 'สแกน QR เพื่อชำระเงิน แล้วกดยืนยันเมื่อทำรายการเรียบร้อย', 'QR', 'bx-qr-scan'],
            visa: ['Visa Card', 'ตรวจสอบยอดและยืนยันการชำระด้วยบัตร Visa', 'VISA', 'bx-credit-card'],
            mastercard: ['Mastercard', 'ตรวจสอบยอดและยืนยันการชำระด้วย Mastercard', 'MC', 'bx-credit-card'],
            truemoney: ['TrueMoney Wallet', 'เปิดแอปวอลเล็ตเพื่อชำระยอด แล้วกลับมายืนยันรายการ', 'TMN', 'bx-wallet']
        }[method] || ['ชำระเงิน', 'เลือกช่องทางชำระเงิน', 'PAY', 'bx-wallet'];
        const imagePath = config.paymentAssets?.[method] || '';
        refs.paymentMockDetail.innerHTML = `
            <div class="payment-detail-asset">
                <img src="${escapeHtml(imagePath)}" alt="${escapeHtml(data[0])}">
                <span><i class="bx ${data[3]}"></i><b>${data[2]}</b></span>
            </div>
            <div>
                <strong>${escapeHtml(data[0])}</strong>
                <p>${escapeHtml(data[1])}</p>
            </div>
        `;
        const detailImage = refs.paymentMockDetail.querySelector('img');
        detailImage?.addEventListener('error', () => detailImage.closest('.payment-detail-asset')?.classList.add('is-missing-image'), { once: true });
    }

    function selectPaymentMethod(method) {
        activePaymentMethod = method || 'promptpay';
        refs.paymentMethods?.forEach((btn) => btn.classList.toggle('is-active', btn.dataset.paymentMethod === activePaymentMethod));
        renderPaymentMethodDetail(activePaymentMethod);
    }

    function showPaymentPanel(payload) {
        activeReservation = payload || {};
        if (!refs.paymentPanel) return;
        refs.shell?.classList.add('is-payment-view');
        refs.paymentPanel.hidden = false;
        updateTicketSteps(activeReservation.payment_required === false ? 'ticket' : 'payment');
        setText(refs.paymentRegistrationId, activeReservation.registration_id ? `#${activeReservation.registration_id}` : '-');
        setText(refs.paymentAmount, money(activeReservation.amount || 0, activeReservation.currency || activeEventData?.currency || 'THB'));
        setText(refs.paymentExpiresAt, activeReservation.payment_expires_at || '-');
        if (refs.paymentReserveState) {
            refs.paymentReserveState.classList.add('is-ready');
            refs.paymentReserveState.innerHTML = `<i class="bx bx-check-circle"></i><div><strong>จองสำเร็จแล้ว</strong><span>${escapeHtml(activeReservation.message || 'ระบบกันที่นั่งให้คุณแล้ว')}</span></div>`;
        }
        if (refs.paymentMessage) refs.paymentMessage.textContent = activeReservation.payment_required === false ? 'ออกบัตรเรียบร้อย กำลังพาไปยังบัตรของคุณ' : 'เลือกช่องทางแล้วกดยืนยันการชำระเงิน';
        if (refs.mockPayButton) {
            refs.mockPayButton.disabled = activeReservation.payment_required !== true;
            refs.mockPayButton.hidden = activeReservation.payment_required === false;
        }
        selectPaymentMethod(activePaymentMethod);
        if (activeReservation.payment_required !== false) {
            startPaymentTimer(activeReservation.payment_expires_at, activeReservation.server_now);
        } else {
            stopPaymentTimer();
            setText(refs.paymentCountdown, 'สำเร็จ');
            window.setTimeout(() => {
                window.location.assign(`/join_activity#event-${activeEventData?.event_id || ''}`);
            }, 900);
        }
        refs.paymentPanel.scrollIntoView({ behavior: 'auto', block: 'start' });
    }

    async function reserveActiveTicket(form) {
        if (!form || !activeEventData) return;
        renderSelectedSummary();
        validateSelection();
        if (refs.joinBtn?.disabled) return;

        updateTicketSteps('reserve');
        refs.shell?.classList.add('is-payment-view');
        if (refs.paymentPanel) refs.paymentPanel.hidden = false;
        if (refs.mockPayButton) {
            refs.mockPayButton.disabled = true;
            refs.mockPayButton.hidden = false;
        }
        if (refs.paymentReserveState) {
            refs.paymentReserveState.classList.remove('is-ready');
            refs.paymentReserveState.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i><div><strong>กำลังตรวจสอบและจองที่นั่ง</strong><span>ระบบกำลังอัปเดตฐานข้อมูลเพื่อกันที่นั่งให้บัญชีของคุณ</span></div>';
        }
        if (refs.joinBtn) {
            refs.joinBtn.disabled = true;
            refs.joinBtn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> กำลังจองที่นั่ง';
        }
        if (refs.paymentMessage) refs.paymentMessage.textContent = '';

        try {
            const body = new URLSearchParams(new FormData(form));
            body.set('ticket_action', 'reserve_ticket');
            const request = fetch(config.paymentEndpoint || '/home_in', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                credentials: 'same-origin',
                body
            });
            const [response] = await Promise.all([request, wait(600)]);
            const raw = await response.text();
            let data = {};
            try {
                data = raw ? JSON.parse(raw) : {};
            } catch (_) {
                throw new Error('ระบบไม่ได้ส่งข้อมูล JSON กลับมา กรุณาตรวจสอบ route /home_in ว่าเป็นไฟล์ controller ไม่ใช่ template');
            }
            if (!response.ok || !data.ok) throw new Error(data.message || 'ไม่สามารถจองที่นั่งได้');
            activeEventData.already_requested = true;
            activeEventData.own_registration_status = data.payment_required === false ? 'approved' : 'pending';
            activeEventData.own_registration_id = data.registration_id || 0;
            activeEventData.own_payment_status = data.payment_status || 'pending';
            activeEventData.own_payment_expires_at = data.payment_expires_at || '';
            showPaymentPanel(data);
            renderMyRegistration(activeEventData);
        } catch (error) {
            updateTicketSteps('select');
            refs.shell?.classList.remove('is-payment-view');
            if (refs.paymentReserveState) {
                refs.paymentReserveState.innerHTML = `<i class="bx bx-error-circle"></i><div><strong>จองไม่สำเร็จ</strong><span>${escapeHtml(error.message || 'กรุณาลองใหม่')}</span></div>`;
            }
            if (refs.paymentMessage) refs.paymentMessage.textContent = error.message || 'กรุณาลองใหม่';
            validateSelection();
        }
    }

    async function completeMockPayment() {
        if (!activeReservation?.registration_id || !refs.mockPayButton) return;
        refs.mockPayButton.disabled = true;
        refs.mockPayButton.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> กำลังอัปเดตการชำระเงิน';
        refs.paymentPanel?.classList.add('is-processing');
        if (refs.paymentMessage) refs.paymentMessage.textContent = 'กำลังตรวจสอบการชำระเงิน กรุณารอสักครู่';
        try {
            const body = new URLSearchParams({
                _csrf: config.csrf || '',
                ticket_action: 'mock_pay',
                registration_id: String(activeReservation.registration_id),
                payment_method: activePaymentMethod
            });
            const request = fetch(config.paymentEndpoint || '/home_in', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                credentials: 'same-origin',
                body
            });
            const [response] = await Promise.all([request, wait(2200)]);
            const data = await response.json().catch(() => ({}));
            if (!response.ok || !data.ok) throw new Error(data.message || 'ชำระเงินไม่สำเร็จ');
            stopPaymentTimer();
            updateTicketSteps('ticket');
            if (refs.paymentCountdown) refs.paymentCountdown.textContent = 'สำเร็จ';
            if (refs.paymentMessage) refs.paymentMessage.textContent = data.message || 'ชำระเงินสำเร็จ';
            refs.mockPayButton.innerHTML = '<i class="bx bx-badge-check"></i> ชำระเงินแล้ว';
            activeEventData.own_registration_status = 'approved';
            activeEventData.own_payment_status = 'paid';
            activeEventData.own_payment_method = activePaymentMethod;
            renderMyRegistration(activeEventData);
            sessionStorage.setItem('badomen_payment_feedback', JSON.stringify({
                feedback_type: 'payment',
                registration_id: activeReservation.registration_id,
                event_id: activeEventData.event_id,
                event_title: activeEventData.title || ''
            }));
            window.setTimeout(() => {
                window.location.assign(`/join_activity?payment=success#event-${activeEventData.event_id}`);
            }, 900);
        } catch (error) {
            refs.paymentPanel?.classList.remove('is-processing');
            refs.mockPayButton.disabled = false;
            refs.mockPayButton.innerHTML = '<i class="bx bx-check-shield"></i> ยืนยันการชำระเงิน';
            if (refs.paymentMessage) refs.paymentMessage.textContent = error.message || 'กรุณาลองใหม่';
        }
    }

    function shouldDisableJoin(eventData) {
        if (eventData.already_requested && !isRejoinableStatus(eventData.own_registration_status, eventData.own_payment_status)) return 'มีคำขอในระบบแล้ว';
        if (eventData.is_not_open) return 'ยังไม่เปิดรับสมัคร';
        if (eventData.is_closed) return 'ปิดรับสมัครแล้ว';
        if (eventData.is_full) return 'จำนวนผู้ลงทะเบียนครบแล้ว';
        return '';
    }

    function indexTicketData(eventData) {
        zoneMap = new Map();
        seatMap = new Map();
        seatsByZone = new Map();

        const zones = Array.isArray(eventData.ticket_zones) ? eventData.ticket_zones : [];
        const seats = Array.isArray(eventData.seat_map) ? eventData.seat_map : [];

        zones.forEach((zone) => zoneMap.set(String(zone.zone_id), zone));
        seats.forEach((seat) => {
            const seatId = String(seat.seat_id);
            const zoneId = String(seat.zone_id || '0');
            seatMap.set(seatId, seat);
            if (!seatsByZone.has(zoneId)) seatsByZone.set(zoneId, []);
            seatsByZone.get(zoneId).push(seat);
        });
    }

    function zoneById(zoneId) {
        return zoneMap.get(String(zoneId)) || null;
    }

    function seatById(seatId) {
        return seatMap.get(String(seatId)) || null;
    }

    function setMainImage(src, index = 0) {
        if (!refs.mainImage) return;
        refs.mainImage.src = src;
        setText(refs.imageCounter, `${index + 1}/${Math.max(activeImages.length, 1)}`);
        refs.thumbs?.querySelectorAll('.ticket-thumb').forEach((thumb, idx) => {
            thumb.classList.toggle('is-active', idx === index);
        });
    }

    function buildThumbs(images) {
        if (!refs.thumbs) return;
        refs.thumbs.replaceChildren();
        activeImages = images;
        const fragment = document.createDocumentFragment();

        images.slice(0, 8).forEach((img, index) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `ticket-thumb${index === 0 ? ' is-active' : ''}`;
            btn.dataset.imageIndex = String(index);
            const image = document.createElement('img');
            image.src = img;
            image.alt = `ภาพกิจกรรม ${index + 1}`;
            image.loading = 'lazy';
            image.decoding = 'async';
            btn.appendChild(image);
            fragment.appendChild(btn);
        });

        refs.thumbs.appendChild(fragment);
    }

    function renderMyRegistration(eventData) {
        if (!refs.myRegistration) return;
        const status = eventData.own_registration_status || '';
        const paymentStatus = eventData.own_payment_status || '';
        if (!status) {
            refs.myRegistration.innerHTML = '<i class="bx bx-info-circle"></i><div><strong>ยังไม่มีคำขอของคุณ</strong><span>เลือกบัตรแล้วกดจองและชำระเงิน เพื่อกันที่นั่งไว้ 10 นาที</span></div>';
            return;
        }

        if (!eventData.already_requested && isRejoinableStatus(status, paymentStatus)) {
            refs.myRegistration.innerHTML = `
                <i class="bx bx-refresh"></i>
                <div>
                    <strong>คำขอเดิมถูกยกเลิก/คืนเงินแล้ว</strong>
                    <span>คุณสามารถขอเข้าร่วมกิจกรรมนี้ใหม่ได้ ระบบจะสร้างคำขอและรอบชำระเงินใหม่</span>
                    <span>สถานะเดิม: ${escapeHtml(registrationStatusLabel(status) || status)}</span>
                </div>
            `;
            return;
        }

        const label = registrationStatusLabel(status);
        const paymentLabel = paymentStatus === 'paid'
            ? 'ชำระเงินแล้ว'
            : (paymentStatus === 'pending' ? 'รอชำระเงิน' : 'ยังไม่ระบุ');
        refs.myRegistration.innerHTML = `
            <i class="bx bx-badge-check"></i>
            <div>
                <strong>สถานะของคุณ: ${escapeHtml(label)}</strong>
                <span>การชำระเงิน: ${escapeHtml(paymentLabel)}</span>
                <span>ส่งคำขอเมื่อ: ${escapeHtml(eventData.own_registered_at || '-')}</span>
                ${eventData.own_payment_expires_at ? `<span>หมดเวลาชำระ: ${escapeHtml(eventData.own_payment_expires_at)}</span>` : ''}
                ${eventData.own_checked_in && eventData.own_checked_in !== '-' ? `<span>เช็กอินเมื่อ: ${escapeHtml(eventData.own_checked_in)}</span>` : ''}
            </div>
        `;
    }

    function renderFavoriteButton() {
        if (!activeEventData || !refs.favoriteBtn) return;
        const saved = Boolean(activeEventData.is_favorite);
        refs.favoriteBtn.classList.toggle('is-favorite', saved);
        refs.favoriteBtn.innerHTML = saved
            ? '<i class="bx bxs-heart"></i> บันทึกแล้ว'
            : '<i class="bx bx-heart"></i> บันทึก';
    }

    async function toggleActiveFavorite() {
        if (!activeEventData?.event_id || !refs.favoriteBtn || refs.favoriteBtn.disabled) return;
        refs.favoriteBtn.disabled = true;
        try {
            const body = new URLSearchParams({
                _csrf: config.csrf || '',
                action: 'toggle_favorite',
                event_id: String(activeEventData.event_id)
            });
            const response = await fetch(config.favoriteEndpoint || '/home_in', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                body,
                credentials: 'same-origin'
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || !data.ok) throw new Error(data.error || 'favorite_failed');
            activeEventData.is_favorite = Boolean(data.saved);
            if (typeof data.favorite_count !== 'undefined') activeEventData.favorite_count = Number(data.favorite_count || 0);
            renderFavoriteButton();
            syncFavoriteState(activeEventData.event_id, activeEventData.is_favorite, activeEventData.favorite_count);
        } catch (_) {
            refs.favoriteBtn.textContent = 'ไม่สามารถบันทึกได้';
        } finally {
            refs.favoriteBtn.disabled = false;
        }
    }

    function syncFavoriteState(eventId, saved, favoriteCount) {
        const id = String(eventId || '');
        const count = Math.max(0, Number(favoriteCount || 0));
        document.querySelectorAll(`[data-event-id="${CSS.escape(id)}"]`).forEach((card) => {
            const state = card.querySelector('[data-card-favorite-state]');
            if (state) {
                state.classList.toggle('is-favorite', Boolean(saved));
                const icon = state.querySelector('i');
                if (icon) icon.className = `bx ${saved ? 'bxs-heart' : 'bx-heart'}`;
                const countEl = state.querySelector('[data-card-favorite-count]');
                if (countEl) countEl.textContent = new Intl.NumberFormat('th-TH').format(count);
            }
            if (!saved && document.querySelector('.favorite-filter input')?.checked) {
                card.remove();
            }
        });
    }

    function updateModalDataFromDocument(nextDoc) {
        const node = nextDoc.getElementById('eventModalData');
        if (!node) return;
        let nextData = {};
        try { nextData = JSON.parse(node.textContent || '{}'); } catch (_) { return; }
        Object.assign(eventModalData, nextData);
    }

    const homeImageCache = new Map();

    function warmHomeImage(src) {
        const url = String(src || '').trim();
        if (!url || url.startsWith('data:image/')) return Promise.resolve(false);
        const cached = homeImageCache.get(url);
        if (cached) return cached;

        const task = new Promise((resolve) => {
            const image = new Image();
            let done = false;
            const finish = (ok) => {
                if (done) return;
                done = true;
                resolve(ok);
            };
            image.decoding = 'async';
            image.onload = () => finish(true);
            image.onerror = () => finish(false);
            image.src = url;
            if (image.complete) finish(true);
            window.setTimeout(() => finish(false), 1400);
        });
        homeImageCache.set(url, task);
        return task;
    }

    function cardId(card) {
        return String(card?.dataset?.eventId || '').trim();
    }

    async function warmNewSnapshotImages(nextDoc) {
        const urls = [];
        nextDoc.querySelectorAll('.event-card[data-event-id]').forEach((nextCard) => {
            const id = cardId(nextCard);
            if (!id || document.querySelector(`.event-card[data-event-id="${CSS.escape(id)}"]`)) return;
            const img = nextCard.querySelector('.card-media img');
            const src = img?.getAttribute('src') || '';
            if (src) urls.push(src);
        });
        if (!urls.length) return;
        await Promise.allSettled([...new Set(urls)].map(warmHomeImage));
    }

    function mergeCardListFromSnapshot(nextDoc, selector) {
        const current = document.querySelector(selector);
        const next = nextDoc.querySelector(selector);
        if (!current || !next) return false;

        const currentCards = new Map();
        current.querySelectorAll(':scope > .event-card[data-event-id]').forEach((card) => {
            const id = cardId(card);
            if (id) currentCards.set(id, card);
        });

        let changed = false;
        const nextCards = Array.from(next.querySelectorAll(':scope > .event-card[data-event-id]'));
        const nextIds = nextCards.map(cardId);

        nextCards.forEach((nextCard, index) => {
            const id = nextIds[index];
            if (!id || currentCards.has(id)) return;

            const imported = document.importNode(nextCard, true);
            const before = nextIds.slice(index + 1)
                .map((nextId) => currentCards.get(nextId))
                .find(Boolean) || null;
            current.insertBefore(imported, before);
            currentCards.set(id, imported);
            changed = true;
            requestAnimationFrame(() => imported.classList.add('is-live-new'));
            window.setTimeout(() => imported.classList.remove('is-live-new'), 3600);
        });

        if (changed) hydrateCardImageOrientation(current);
        return changed;
    }

    function replaceFromSnapshot(nextDoc, selector) {
        const current = document.querySelector(selector);
        const next = nextDoc.querySelector(selector);
        if (!current || !next || current.innerHTML === next.innerHTML) return false;
        current.replaceChildren(...Array.from(next.children).map((child) => document.importNode(child, true)));
        hydrateCardImageOrientation(current);
        return true;
    }

    function setLiveStatus(text, state = '') {
        document.querySelectorAll('[data-live-sync-status]').forEach((el) => {
            el.dataset.state = state;
            const icon = state === 'error' ? 'bx-error-circle' : (state === 'sync' ? 'bx-loader-alt bx-spin' : 'bx-radio-circle-marked');
            el.innerHTML = `<i class="bx ${icon}"></i> ${escapeHtml(text)}`;
        });
    }

    function bindFavoriteFilter(root = document) {
        const favoriteFilter = root.querySelector?.('#favoriteFilterToggle');
        const favoriteCheckbox = favoriteFilter?.querySelector('input[name="favorites"]');
        if (!favoriteFilter || !favoriteCheckbox || favoriteCheckbox.dataset.bound === '1') return;
        favoriteCheckbox.dataset.bound = '1';
        favoriteCheckbox.addEventListener('change', () => {
            const active = favoriteCheckbox.checked;
            const icon = favoriteFilter.querySelector('i');
            const small = favoriteFilter.querySelector('small');
            favoriteFilter.classList.toggle('is-active', active);
            favoriteFilter.setAttribute('aria-pressed', active ? 'true' : 'false');
            if (icon) icon.className = active ? 'bx bxs-heart' : 'bx bx-heart';
            if (small) {
                small.textContent = active
                    ? 'กำลังแสดงเฉพาะรายการโปรด'
                    : 'คลิกเพื่อแสดงรายการที่บันทึกไว้';
            }
            favoriteCheckbox.form?.requestSubmit();
        });
    }

    function highlightServerTodayInputs(root = document) {
        root.querySelectorAll?.('input[type="datetime-local"][data-server-today]').forEach((input) => {
            const refresh = () => input.classList.toggle(
                'is-today',
                String(input.value || '').slice(0, 10) === input.dataset.serverToday
            );
            if (input.dataset.todayBound !== '1') {
                input.dataset.todayBound = '1';
                input.addEventListener('change', refresh);
                input.addEventListener('input', refresh);
            }
            refresh();
        });
    }

    bindFavoriteFilter();
    highlightServerTodayInputs();

    const HOME_SNAPSHOT_CACHE_PREFIX = 'badomen.home_in.snapshot.';
    const HOME_SNAPSHOT_CACHE_MAX_AGE = 10 * 60 * 1000;
    let homeSnapshotMemoryCache = null;

    function homeSnapshotCacheKey() {
        const url = new URL(window.location.href);
        url.searchParams.delete('live');
        url.searchParams.delete('_');
        return `${HOME_SNAPSHOT_CACHE_PREFIX}${url.pathname}?${url.searchParams.toString()}`;
    }

    function clearHomeSnapshotCache() {
        homeSnapshotMemoryCache = null;
        homeImageCache.clear();
        try {
            for (let i = sessionStorage.length - 1; i >= 0; i -= 1) {
                const key = sessionStorage.key(i);
                if (key?.startsWith(HOME_SNAPSHOT_CACHE_PREFIX)) sessionStorage.removeItem(key);
            }
        } catch (_) {}
    }

    function readCachedHomeSnapshot() {
        if (homeSnapshotMemoryCache?.html && Date.now() - homeSnapshotMemoryCache.savedAt <= HOME_SNAPSHOT_CACHE_MAX_AGE) {
            return homeSnapshotMemoryCache.html;
        }
        try {
            const cached = JSON.parse(sessionStorage.getItem(homeSnapshotCacheKey()) || 'null');
            if (!cached?.html || Date.now() - Number(cached.savedAt || 0) > HOME_SNAPSHOT_CACHE_MAX_AGE) return '';
            return cached.html;
        } catch (_) {
            return '';
        }
    }

    function writeCachedHomeSnapshot(html) {
        if (!html) return;
        homeSnapshotMemoryCache = { html, savedAt: Date.now() };
        try {
            sessionStorage.setItem(homeSnapshotCacheKey(), JSON.stringify({
                html,
                savedAt: homeSnapshotMemoryCache.savedAt
            }));
        } catch (_) {}
    }

    function bindHomeSnapshotCacheCleanup() {
        document.addEventListener('click', (event) => {
            const link = event.target.closest('a[href]');
            if (!link || link.target || link.hasAttribute('download')) return;
            const url = new URL(link.href, window.location.href);
            if (url.origin === window.location.origin && url.pathname.replace(/\/+$/, '') !== '/home_in') {
                clearHomeSnapshotCache();
            }
        });

        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) return;
            const url = new URL(form.action || window.location.href, window.location.href);
            if (url.origin === window.location.origin && url.pathname.replace(/\/+$/, '') !== '/home_in') {
                clearHomeSnapshotCache();
            }
        });
    }

    function liveSnapshotUrl() {
        const url = new URL(window.location.href);
        url.searchParams.set('live', '1');
        url.searchParams.set('_', String(Date.now()));
        return url.toString();
    }

    function applyHomeSnapshot(snapshot) {
        const nextDoc = typeof snapshot === 'string'
            ? new DOMParser().parseFromString(snapshot, 'text/html')
            : snapshot;
        if (!nextDoc) return false;
        updateModalDataFromDocument(nextDoc);

        const main = document.querySelector('.home-in-main');
        const nextMain = nextDoc.querySelector('.home-in-main');
        const currentEmpty = document.querySelector('.empty-state');
        const nextEmpty = nextDoc.querySelector('.empty-state');
        if (main && nextMain && Boolean(currentEmpty) !== Boolean(nextEmpty)) {
            main.replaceChildren(...Array.from(nextMain.children).map((child) => document.importNode(child, true)));
            bindFavoriteFilter(main);
            highlightServerTodayInputs(main);
            applyCardView(currentCardView());
            hydrateCardImageOrientation(main);
            return true;
        }

        let changed = false;
        [
            '.ticket-preview',
            '.trending-section .section-actions',
            '.new-section .section-actions',
            '.all-events-section .section-actions',
        ].forEach((selector) => {
            changed = replaceFromSnapshot(nextDoc, selector) || changed;
        });
        [
            '.trending-section [data-card-list="trending"]',
            '.new-section [data-card-list="new"]',
            '.all-events-section [data-card-list="all"]'
        ].forEach((selector) => {
            changed = mergeCardListFromSnapshot(nextDoc, selector) || changed;
        });
        applyCardView(currentCardView());
        return changed;
    }

    async function refreshHomeSnapshot() {
        if (document.hidden || modalEl.classList.contains('is-open')) return;
        if (document.activeElement && document.activeElement.closest('.search-panel')) return;
        setLiveStatus('กำลังซิงก์', 'sync');
        try {
            const response = await fetch(liveSnapshotUrl(), {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!response.ok) throw new Error('snapshot_failed');
            const html = await response.text();
            const nextDoc = new DOMParser().parseFromString(html, 'text/html');
            await warmNewSnapshotImages(nextDoc);
            writeCachedHomeSnapshot(html);
            applyHomeSnapshot(nextDoc);
            setLiveStatus('Live', 'ok');
        } catch (_) {
            const cached = readCachedHomeSnapshot();
            if (cached) {
                applyHomeSnapshot(cached);
                setLiveStatus('Cached', 'ok');
            } else {
                setLiveStatus('Sync error', 'error');
            }
        }
    }

    function renderZones() {
        if (!refs.zoneList) return;
        const zones = Array.isArray(activeEventData?.ticket_zones) ? activeEventData.ticket_zones : [];
        refs.zoneList.replaceChildren();

        if (!zones.length) {
            if (refs.zonePanel) refs.zonePanel.hidden = true;
            return;
        }

        if (refs.zonePanel) refs.zonePanel.hidden = false;
        const fragment = document.createDocumentFragment();
        zones.forEach((zone) => {
            const available = Number(zone.available_count ?? zone.capacity ?? 0);
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ticket-zone-card';
            btn.dataset.zoneId = String(zone.zone_id);
            btn.style.setProperty('--zone-color', safeColor(zone.color_hex));
            btn.classList.toggle('is-active', String(selectedZoneId) === String(zone.zone_id));
            btn.classList.toggle('is-sold-out', available <= 0);
            btn.disabled = available <= 0;
            btn.innerHTML = `
                <strong>${escapeHtml(zone.zone_name || zone.zone_code || 'Zone')}</strong>
                <div class="ticket-zone-price">${money(zone.price, zone.currency || activeEventData.currency || 'THB')}</div>
                <span><b>${escapeHtml(zone.zone_code || '-')}</b><em>${available}/${Number(zone.capacity || 0)} ว่าง</em></span>
            `;
            fragment.appendChild(btn);
        });
        refs.zoneList.appendChild(fragment);
    }

    function updateZoneActiveState() {
        refs.zoneList?.querySelectorAll('.ticket-zone-card').forEach((btn) => {
            btn.classList.toggle('is-active', String(btn.dataset.zoneId) === String(selectedZoneId));
        });
    }

    function groupSeatsByRow(seats) {
        const rows = new Map();
        seats.forEach((seat) => {
            const row = String(seat.row_label || '-');
            if (!rows.has(row)) rows.set(row, []);
            rows.get(row).push(seat);
        });
        return Array.from(rows.entries()).sort((a, b) => {
            const la = Number(a[1][0]?.row_sort ?? 0);
            const lb = Number(b[1][0]?.row_sort ?? 0);
            return la - lb;
        });
    }

    function renderSeatMap() {
        if (!refs.seatMap) return;
        const zoneId = selectedZoneId ? String(selectedZoneId) : '';
        const selectionMode = activeEventData?.seat_selection_mode || 'manual';
        const ticketMode = activeEventData?.ticket_mode || 'general';
        const key = `${activeEventData?.event_id || 0}:${zoneId}:${selectionMode}:${ticketMode}`;
        if (key === lastSeatRenderKey) return;
        lastSeatRenderKey = key;

        refs.seatMap.replaceChildren();
        if (ticketMode === 'general' || selectionMode === 'random') return;

        const seats = zoneId ? (seatsByZone.get(zoneId) || []) : Array.from(seatMap.values());
        if (!seats.length) {
            refs.seatMap.innerHTML = '<div class="ticket-my-status"><i class="bx bx-info-circle"></i><div><strong>ยังไม่มีผังที่นั่งในโซนนี้</strong><span>ตรวจสอบการสร้างโซนจากหน้า Create event</span></div></div>';
            return;
        }

        refs.seatMap.classList.add('is-rendering');
        refs.seatMap.innerHTML = '<div class="ticket-my-status"><i class="bx bx-loader-alt"></i><div><strong>กำลังเตรียมผังที่นั่ง</strong><span>แสดงเฉพาะโซนที่เลือกเพื่อลดการกระตุก</span></div></div>';

        const currentToken = renderToken;
        idle(() => {
            if (currentToken !== renderToken || !activeEventData) return;

            const fragment = document.createDocumentFragment();
            const zone = zoneById(zoneId) || {};
            const group = document.createElement('div');
            group.className = 'seat-zone-group';
            group.innerHTML = `<div class="seat-zone-title" style="border-left:6px solid ${safeColor(zone.color_hex)}">
                <span>${escapeHtml(zone.zone_name || zone.zone_code || 'Zone')}</span>
                <span>${money(zone.price || 0, zone.currency || activeEventData.currency || 'THB')}</span>
            </div>`;

            groupSeatsByRow(seats).forEach(([rowLabel, rowSeats]) => {
                rowSeats.sort((a, b) => Number(a.seat_number || 0) - Number(b.seat_number || 0));
                const row = document.createElement('div');
                row.className = 'seat-row';

                const label = document.createElement('span');
                label.className = 'seat-row-label';
                label.textContent = rowLabel;
                row.appendChild(label);

                const seatWrap = document.createElement('div');
                seatWrap.className = 'seat-row-seats';

                rowSeats.forEach((seat) => {
                    const status = String(seat.status || 'available');
                    const seatId = String(seat.seat_id);
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = `seat-button is-${status}${selectedSeatIds.has(seatId) ? ' is-selected' : ''}`;
                    btn.textContent = String(seat.seat_number || '');
                    btn.title = seat.seat_code || '';
                    btn.disabled = status !== 'available';
                    btn.dataset.seatId = seatId;
                    seatWrap.appendChild(btn);
                });

                row.appendChild(seatWrap);
                group.appendChild(row);
            });

            fragment.appendChild(group);
            refs.seatMap.replaceChildren(fragment);
            refs.seatMap.classList.remove('is-rendering');
        });
    }

    function syncSeatButtons() {
        if (!refs.seatMap) return;
        refs.seatMap.querySelectorAll('.seat-button[data-seat-id]').forEach((btn) => {
            btn.classList.toggle('is-selected', selectedSeatIds.has(String(btn.dataset.seatId)));
        });
    }

    function toggleSeatById(seatId) {
        const seat = seatById(seatId);
        if (!seat || String(seat.status || 'available') !== 'available') return;

        const limit = normalizeLimit(activeEventData?.max_tickets_per_user);
        const id = String(seat.seat_id);

        if (selectedSeatIds.has(id)) {
            selectedSeatIds.delete(id);
        } else {
            if (selectedSeatIds.size >= limit) return;
            selectedSeatIds.add(id);
            selectedZoneId = seat.zone_id;
        }

        selectedQuantity = Math.max(1, selectedSeatIds.size);
        syncSeatButtons();
        renderSelectedSummary();
        validateSelection();
    }

    function renderQuantityPicker() {
        if (!refs.quantityPicker) return;
        const limit = normalizeLimit(activeEventData?.max_tickets_per_user);
        refs.quantityPicker.replaceChildren();
        const fragment = document.createDocumentFragment();
        for (let i = 1; i <= limit; i += 1) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `quantity-button${selectedQuantity === i ? ' is-active' : ''}`;
            btn.textContent = `${i} ใบ`;
            btn.dataset.quantity = String(i);
            fragment.appendChild(btn);
        }
        refs.quantityPicker.appendChild(fragment);
    }

    function renderSelectedSummary() {
        if (!refs.selectedList) return;
        refs.selectedList.replaceChildren();
        const mode = activeEventData?.seat_selection_mode || 'manual';
        const ticketMode = activeEventData?.ticket_mode || 'general';
        const currency = activeEventData?.currency || 'THB';
        let total = 0;

        if (ticketMode === 'general') {
            total = Number(activeEventData?.price || 0);
            refs.selectedList.innerHTML = '<span class="ticket-selected-pill"><i class="bx bx-user-check"></i> สมัครทั่วไป 1 ใบ</span>';
        } else if (mode === 'random') {
            const zone = zoneById(selectedZoneId) || {};
            total = Number(zone.price || 0) * selectedQuantity;
            refs.selectedList.innerHTML = `<span class="ticket-selected-pill"><i class="bx bx-shuffle"></i> ${escapeHtml(zone.zone_name || 'เลือกโซน')} × ${selectedQuantity}</span>`;
        } else {
            const fragment = document.createDocumentFragment();
            selectedSeatIds.forEach((seatId) => {
                const seat = seatById(seatId);
                if (!seat) return;
                const zone = zoneById(seat.zone_id) || {};
                total += Number(zone.price || seat.price || 0);
                const pill = document.createElement('span');
                pill.className = 'ticket-selected-pill';
                pill.innerHTML = `<i class="bx bx-chair"></i> ${escapeHtml(seat.seat_code || seatId)}`;
                fragment.appendChild(pill);
            });
            if (fragment.childNodes.length) {
                refs.selectedList.appendChild(fragment);
            } else {
                refs.selectedList.innerHTML = '<span class="ticket-selected-pill"><i class="bx bx-chair"></i> ยังไม่ได้เลือกที่นั่ง</span>';
            }
        }

        const discountQuantity = ticketMode === 'general'
            ? 1
            : (mode === 'manual' ? Math.max(1, selectedSeatIds.size) : Math.max(1, selectedQuantity));
        const vipDiscount = config.viewerIsVip
            ? Math.min(total, Number(config.vipDiscountPerTicket || 0) * discountQuantity)
            : 0;
        const finalTotal = Math.max(0, total - vipDiscount);
        setText(refs.totalPrice, money(finalTotal, currency));
        if (refs.vipSaving) {
            refs.vipSaving.hidden = vipDiscount <= 0;
            refs.vipSaving.innerHTML = vipDiscount > 0
                ? `<span>Gold VIP</span><strong>ประหยัด ${money(vipDiscount, currency)}</strong><small>ราคาเต็ม ${money(total, currency)}</small>`
                : '';
        }
        if (refs.quantityInput) refs.quantityInput.value = String(ticketMode === 'general' ? 1 : Math.max(1, selectedQuantity));
        if (refs.zoneInput) refs.zoneInput.value = selectedZoneId ? String(selectedZoneId) : '';
        if (refs.seatIdsInput) refs.seatIdsInput.value = Array.from(selectedSeatIds).join(',');
    }

    function validateSelection() {
        const reason = shouldDisableJoin(activeEventData || {});
        const ticketMode = activeEventData?.ticket_mode || 'general';
        const selectionMode = activeEventData?.seat_selection_mode || 'manual';
        let disabledReason = reason;

        if (!disabledReason && ticketMode !== 'general') {
            if (!selectedZoneId) disabledReason = 'กรุณาเลือกโซน';
            if (selectionMode === 'manual' && selectedSeatIds.size < 1) disabledReason = 'กรุณาเลือกที่นั่ง';
        }

        if (!refs.joinBtn) return;
        refs.joinBtn.disabled = Boolean(disabledReason);
        refs.joinBtn.innerHTML = disabledReason
            ? `<i class="bx bx-info-circle"></i> ${escapeHtml(disabledReason)}`
            : '<i class="bx bx-lock-alt"></i> จองและชำระเงิน';
    }

    function renderTicketPicker() {
        const zones = Array.isArray(activeEventData?.ticket_zones) ? activeEventData.ticket_zones : [];
        const limit = normalizeLimit(activeEventData?.max_tickets_per_user);
        const ticketMode = activeEventData?.ticket_mode || (zones.length ? 'seating' : 'general');
        const selectionMode = activeEventData?.seat_selection_mode || 'manual';

        if (refs.ticketModeInput) refs.ticketModeInput.value = ticketMode;
        if (refs.selectionModeInput) refs.selectionModeInput.value = selectionMode;
        setText(refs.ticketLimitText, String(limit));
        setText(refs.ticketModeText, ticketMode === 'general'
            ? 'กิจกรรมนี้ใช้ระบบสมัครทั่วไป ยังไม่แยกโซนที่นั่ง'
            : (selectionMode === 'random'
                ? 'เลือกโซนและจำนวนใบ จากนั้นระบบสุ่มที่นั่งว่างให้'
                : 'เลือกโซนและตำแหน่งที่นั่งเองได้ เหมือนระบบบัตรคอนเสิร์ต'));

        if (ticketMode !== 'general' && zones.length && !selectedZoneId) {
            const firstAvailable = zones.find((zone) => Number(zone.available_count ?? zone.capacity ?? 0) > 0) || zones[0];
            selectedZoneId = firstAvailable?.zone_id || null;
        }

        if (refs.zonePanel) refs.zonePanel.hidden = ticketMode === 'general';
        if (refs.seatPanel) refs.seatPanel.hidden = ticketMode === 'general' || selectionMode === 'random';
        if (refs.randomPanel) refs.randomPanel.hidden = ticketMode === 'general' || selectionMode !== 'random';

        renderZones();
        if (ticketMode !== 'general' && selectionMode === 'manual') renderSeatMap();
        if (ticketMode !== 'general' && selectionMode === 'random') renderQuantityPicker();
        renderSelectedSummary();
        validateSelection();
    }

    function openEventModal(eventData) {
        renderToken += 1;
        activeEventData = eventData;
        selectedSeatIds = new Set();
        selectedZoneId = null;
        selectedQuantity = 1;
        lastSeatRenderKey = '';
        activeReservation = null;
        stopPaymentTimer();
        refs.shell?.classList.remove('is-payment-view');
        refs.paymentPanel?.classList.remove('is-processing');
        if (refs.paymentPanel) refs.paymentPanel.hidden = true;
        if (refs.paymentMessage) refs.paymentMessage.textContent = '';
        if (refs.mockPayButton) {
            refs.mockPayButton.disabled = false;
            refs.mockPayButton.hidden = false;
            refs.mockPayButton.innerHTML = '<i class="bx bx-check-shield"></i> ยืนยันการชำระเงิน';
        }
        updateTicketSteps('select');
        activeImages = Array.isArray(eventData.images) ? eventData.images : [];
        indexTicketData(eventData);

        setText(refs.title, eventData.title || '-');
        const mapLat = Number(eventData.latitude);
        const mapLng = Number(eventData.longitude);
        const hasMap = Number.isFinite(mapLat) && Number.isFinite(mapLng)
            && eventData.latitude !== null && eventData.longitude !== null;
        if (refs.mapPanel) refs.mapPanel.hidden = !hasMap;
        if (hasMap && refs.miniMap && window.L) {
            if (!modalMap) {
                modalMap = L.map(refs.miniMap, { zoomControl: true, attributionControl: true });
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(modalMap);
            }
            modalMap.setView([mapLat, mapLng], 15);
            if (!modalMapMarker) modalMapMarker = L.marker([mapLat, mapLng]).addTo(modalMap);
            else modalMapMarker.setLatLng([mapLat, mapLng]);
            window.setTimeout(() => modalMap.invalidateSize(), 120);
        }
        if (refs.status) {
            refs.status.className = `modal-status ${eventData.status_class || 'status-badge--open'}`;
            refs.status.textContent = eventData.status_text || '-';
        }

        modalEl.classList.add('is-open');
        modalEl.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');

        const currentToken = renderToken;
        requestAnimationFrame(() => {
            if (currentToken !== renderToken) return;
            hydrateEventModal(eventData, currentToken);
        });
    }

    function hydrateEventModal(eventData, currentToken) {
        const images = Array.isArray(eventData.images) && eventData.images.length ? eventData.images : [];
        const registered = Number(eventData.registered_count ?? 0);
        const max = Number(eventData.max_participant ?? 0);
        const percent = max > 0 ? Math.max(0, Math.min(100, Math.round((registered / max) * 100))) : 0;
        const seatLeft = Math.max(0, max - registered);

        setText(refs.title, eventData.title || '-');
        if (refs.creator) refs.creator.innerHTML = `<i class="bx bx-user"></i> ผู้จัด: ${escapeHtml(eventData.creator_name || '-')}`;
        setText(refs.eventStart, eventData.event_start || '-');
        setText(refs.eventEnd, eventData.event_end || '-');
        setText(refs.location, eventData.location || '-');
        setText(refs.regStart, eventData.reg_start || '-');
        setText(refs.regEnd, eventData.reg_end || '-');
        setText(refs.capacity, `${registered}/${max} คน`);
        setText(refs.description, eventData.description || '-');
        setText(refs.eventCode, `EVT-${String(eventData.event_id || 0).padStart(5, '0')}`);
        if (refs.capacityBar) refs.capacityBar.style.width = `${percent}%`;
        setText(refs.capacityPercent, `${percent}%`);
        setText(refs.seatLeft, max > 0 ? `เหลือ ${seatLeft} ที่` : 'ไม่จำกัดจำนวน');
        setText(refs.approvedCount, eventData.approved_count ?? registered);
        setText(refs.pendingCount, eventData.pending_count ?? 0);
        setText(refs.checkedInCount, eventData.checked_in_count ?? 0);
        if (refs.eventId) refs.eventId.value = eventData.event_id || '';
        if (refs.returnQuery) refs.returnQuery.value = eventData.return_query || '';
        if (refs.returnStartAt) refs.returnStartAt.value = eventData.return_start_at || '';
        if (refs.returnEndAt) refs.returnEndAt.value = eventData.return_end_at || '';
        if (refs.returnShowAll) refs.returnShowAll.value = eventData.return_show_all || '';

        if (images[0]) {
            setMainImage(images[0], 0);
        }

        renderMyRegistration(eventData);
        renderFavoriteButton();
        renderTicketPicker();
        if (eventData.own_registration_id && eventData.own_payment_status === 'pending' && !isRejoinableStatus(eventData.own_registration_status, eventData.own_payment_status)) {
            showPaymentPanel({
                ok: true,
                message: 'คุณมีรายการจองที่รอชำระเงินอยู่',
                registration_id: eventData.own_registration_id,
                amount: eventData.own_total_amount ?? eventData.price ?? 0,
                currency: eventData.currency || 'THB',
                payment_required: true,
                payment_status: 'pending',
                payment_expires_at: eventData.own_payment_expires_at || '',
                server_now: eventData.server_now || ''
            });
        }

        idle(() => {
            if (currentToken === renderToken) buildThumbs(images);
        });
    }

    function closeEventModal() {
        renderToken += 1;
        modalEl.classList.remove('is-open');
        modalEl.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        refs.shell?.classList.remove('is-payment-view');
        refs.paymentPanel?.classList.remove('is-processing');
        stopPaymentTimer();
    }

    applyCardView(currentCardView());
    hydrateCardImageOrientation(document);
    bindHomeSnapshotCacheCleanup();
    writeCachedHomeSnapshot(document.documentElement.outerHTML);
    document.addEventListener('click', (event) => {
        const viewButton = event.target.closest('[data-card-view]');
        if (!viewButton) return;
        applyCardView(viewButton.dataset.cardView || 'grid');
    });
    window.setInterval(refreshHomeSnapshot, 12000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refreshHomeSnapshot();
    });

    refs.favoriteBtn?.addEventListener('click', toggleActiveFavorite);
    refs.thumbs?.addEventListener('click', (event) => {
        const btn = event.target.closest('.ticket-thumb[data-image-index]');
        if (!btn) return;
        const index = Number(btn.dataset.imageIndex || 0);
        if (activeImages[index]) setMainImage(activeImages[index], index);
    });
    refs.zoneList?.addEventListener('click', (event) => {
        const btn = event.target.closest('.ticket-zone-card[data-zone-id]');
        if (!btn || btn.disabled) return;
        const zoneId = btn.dataset.zoneId;
        if (String(selectedZoneId) === String(zoneId)) return;
        selectedZoneId = zoneId;
        selectedSeatIds.clear();
        selectedQuantity = 1;
        lastSeatRenderKey = '';
        updateZoneActiveState();
        renderSeatMap();
        renderQuantityPicker();
        renderSelectedSummary();
        validateSelection();
    });
    refs.seatMap?.addEventListener('click', (event) => {
        const btn = event.target.closest('.seat-button[data-seat-id]');
        if (!btn || btn.disabled) return;
        toggleSeatById(btn.dataset.seatId);
    });
    refs.quantityPicker?.addEventListener('click', (event) => {
        const btn = event.target.closest('.quantity-button[data-quantity]');
        if (!btn) return;
        selectedQuantity = Number(btn.dataset.quantity || 1);
        renderQuantityPicker();
        renderSelectedSummary();
        validateSelection();
    });

    refs.paymentMethods?.forEach((btn) => {
        btn.addEventListener('click', () => selectPaymentMethod(btn.dataset.paymentMethod || 'promptpay'));
        btn.querySelector('img')?.addEventListener('error', () => btn.querySelector('.payment-method-card__asset')?.classList.add('is-missing-image'), { once: true });
    });
    refs.mockPayButton?.addEventListener('click', completeMockPayment);

    document.querySelectorAll('[data-modal-close]').forEach((el) => el.addEventListener('click', closeEventModal));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modalEl.classList.contains('is-open')) closeEventModal();
    });
    document.addEventListener('click', (event) => {
        const card = event.target.closest('[data-event-id]');
        if (!card || modalEl.contains(card)) return;
        const eventData = eventModalData[card.dataset.eventId];
        if (eventData) openEventModal(eventData);
    });

    function openEventFromHash() {
        const match = window.location.hash.match(/^#event-(\d+)$/);
        if (!match) return;
        const eventData = eventModalData[match[1]];
        if (eventData) {
            window.setTimeout(() => openEventModal(eventData), 80);
        }
    }

    async function openEventById(eventId, fallbackUrl = '') {
        const id = String(eventId || '');
        if (!id) return false;

        let eventData = eventModalData[id];
        if (!eventData) {
            try {
                const url = new URL(fallbackUrl || `/home_in?show_all=1#event-${encodeURIComponent(id)}`, window.location.href);
                url.hash = '';
                url.searchParams.set('show_all', '1');
                url.searchParams.set('live', '1');
                url.searchParams.set('_', String(Date.now()));
                const response = await fetch(url.toString(), {
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!response.ok) throw new Error('event_snapshot_failed');
                const html = await response.text();
                writeCachedHomeSnapshot(html);
                applyHomeSnapshot(html);
                eventData = eventModalData[id];
            } catch (_) {
                eventData = null;
            }
        }

        if (!eventData) return false;
        history.replaceState(null, '', `#event-${id}`);
        document.querySelectorAll('.event-card.is-chat-selected').forEach((card) => card.classList.remove('is-chat-selected'));
        const card = document.querySelector(`[data-event-id="${CSS.escape(id)}"]`);
        card?.classList.add('is-chat-selected');
        window.setTimeout(() => card?.classList.remove('is-chat-selected'), 2400);
        openEventModal(eventData);
        return true;
    }

    refs.joinBtn?.closest('form')?.addEventListener('submit', (event) => {
        event.preventDefault();
        reserveActiveTicket(event.currentTarget);
    });

    window.closeEventModal = closeEventModal;
    window.openEventModal = openEventModal;
    window.BadomenEventModalData = eventModalData;
    window.BadomenOpenEventById = openEventById;
    openEventFromHash();
})();

(() => {
    const root = document.querySelector('[data-event-chatbot]');
    if (!root) return;
    const isVip = root.dataset.isVip === '1';
    const panel = root.querySelector('.home-event-chat__panel');
    const input = root.querySelector('input');
    const form = root.querySelector('form');
    const messages = root.querySelector('.home-event-chat__messages');
    const escapeHtml = value => String(value).replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    const setOpen = open => { panel.hidden = !open; if (open) input.focus(); };
    const send = async message => {
        if (!message) return;
        setOpen(true);
        if (!isVip) {
            messages.insertAdjacentHTML('beforeend', '<p>Gold VIP required. สมัคร VIP เพื่อใช้งานผู้ช่วยค้นหากิจกรรมและ AI</p>');
            messages.scrollTop = messages.scrollHeight;
            return;
        }
        messages.insertAdjacentHTML('beforeend', `<p class="is-user">${escapeHtml(message)}</p><p class="is-loading">กำลังค้นหา...</p>`);
        try {
            const response = await fetch('/event-chatbot', {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
                body: new URLSearchParams({message})
            });
            const data = await response.json();
            messages.querySelector('.is-loading')?.remove();
            const cards = (data.events || []).map(item => `<a href="${escapeHtml(item.url)}" data-chat-event-id="${escapeHtml(item.event_id || '')}"><strong>${escapeHtml(item.title)}</strong><span>${escapeHtml(item.summary)}</span><small>${escapeHtml(item.meta)}</small></a>`).join('');
            const followups = (data.suggestions || []).map(prompt => `<button type="button" data-chat-inline-prompt="${escapeHtml(prompt)}">${escapeHtml(prompt)}</button>`).join('');
            messages.insertAdjacentHTML('beforeend', `<p>${escapeHtml(data.message || 'ไม่พบกิจกรรม')}</p>${cards}${followups ? `<div class="home-event-chat__followups">${followups}</div>` : ''}`);
        } catch (_) {
            messages.querySelector('.is-loading')?.remove();
            messages.insertAdjacentHTML('beforeend', '<p>เชื่อมต่อผู้ช่วยไม่สำเร็จ</p>');
        }
        messages.scrollTop = messages.scrollHeight;
    };
    root.querySelector('.home-event-chat__toggle').addEventListener('click', () => setOpen(panel.hidden));
    root.querySelector('[data-chat-close]').addEventListener('click', () => setOpen(false));
    document.querySelector('[data-open-event-chat]')?.addEventListener('click', () => setOpen(true));
    root.querySelectorAll('[data-chat-prompt]').forEach(button => button.addEventListener('click', () => send(button.dataset.chatPrompt)));
    messages.addEventListener('click', async event => {
        const link = event.target.closest('[data-chat-event-id]');
        const promptButton = event.target.closest('[data-chat-inline-prompt]');
        if (promptButton) {
            send(promptButton.dataset.chatInlinePrompt || '');
            return;
        }
        if (!link) return;
        event.preventDefault();
        const eventId = String(link.dataset.chatEventId || '');
        if (!eventId) return;
        link.classList.add('is-loading');
        setOpen(false);
        const opened = typeof window.BadomenOpenEventById === 'function'
            ? await window.BadomenOpenEventById(eventId, link.href)
            : false;
        link.classList.remove('is-loading');
        if (!opened) window.location.assign(link.href);
    });
    form.addEventListener('submit', event => {
        event.preventDefault();
        const message = input.value.trim();
        input.value = '';
        send(message);
    });
})();

</script>
</body>
</html>
