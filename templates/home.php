<?php
// สมมติว่ามีฟังก์ชัน getConnection() อยู่แล้ว
$homeSearchQuery = trim((string)($_GET['q'] ?? ''));
$headerAssetsLoaded = true;
$savedEventIds = [];
$slides = [];
$popularTags = [];
$homeDatabaseUnavailable = false;

try {
    $conn = getConnection();

if (isset($_SESSION['user_id'])) {
    $favoriteStmt = $conn->prepare('SELECT event_id FROM event_favorites WHERE user_id = ?');
    if ($favoriteStmt) {
        $favoriteUserId = (int)$_SESSION['user_id'];
        $favoriteStmt->bind_param('i', $favoriteUserId);
        $favoriteStmt->execute();
        $favoriteResult = $favoriteStmt->get_result();
        while ($favoriteRow = $favoriteResult->fetch_assoc()) {
            $savedEventIds[] = (int)$favoriteRow['event_id'];
        }
        $favoriteStmt->close();
    }
}

/* =========================
   ดึงกิจกรรมมาแรงสำหรับหน้า Home
   - ยังไม่ปิดรับสมัคร
   - อิงจากจำนวนคนเข้าร่วม
   - จำกัด 20 รายการสำหรับการ์ดแนะนำ/ค้นหา
========================= */
$sqlSlides = "
SELECT 
    e.event_id,
    e.title,
    e.description,
    e.location,
    e.event_start,
    e.event_end,
    e.reg_end,
    e.max_participant,
    (
        SELECT COUNT(*) 
        FROM registrations r
        WHERE r.event_id = e.event_id
        AND r.status IN ('approved', 'checked_in')
    ) AS registered_count,
    (
        SELECT image_path 
        FROM event_images i
        WHERE i.event_id = e.event_id
        ORDER BY i.image_id ASC
        LIMIT 1
    ) AS cover_image,
    (
        SELECT COUNT(*)
        FROM event_favorites f
        WHERE f.event_id = e.event_id
    ) AS favorite_count,
    (
        SELECT GROUP_CONCAT(t.name_th ORDER BY t.usage_count DESC SEPARATOR '|||')
        FROM event_tags et
        INNER JOIN tags t ON t.tag_id = et.tag_id
        WHERE et.event_id = e.event_id
    ) AS tag_names,
    (
        SELECT GROUP_CONCAT(t.slug ORDER BY t.usage_count DESC SEPARATOR '|||')
        FROM event_tags et
        INNER JOIN tags t ON t.tag_id = et.tag_id
        WHERE et.event_id = e.event_id
    ) AS tag_slugs
FROM events e
WHERE 
    e.status = 'published'
    AND e.visibility = 'public'
    AND (e.reg_end IS NULL OR e.reg_end >= NOW())
    AND (e.event_end IS NULL OR e.event_end >= NOW())
ORDER BY registered_count DESC, favorite_count DESC, e.event_start ASC
LIMIT 8
";

$resultSlides = $conn->query($sqlSlides);
if ($resultSlides) {
    $slides = $resultSlides->fetch_all(MYSQLI_ASSOC);
}

$tagResult = $conn->query(
    "SELECT t.slug, t.name_th, t.name_en, COUNT(DISTINCT et.event_id) AS actual_usage
     FROM tags t
     INNER JOIN event_tags et ON et.tag_id = t.tag_id
     INNER JOIN events e ON e.event_id = et.event_id
     WHERE e.status = 'published' AND e.visibility = 'public'
       AND (e.event_end IS NULL OR e.event_end >= NOW())
       AND (e.reg_end IS NULL OR e.reg_end >= NOW())
     GROUP BY t.tag_id, t.slug, t.name_th, t.name_en
     ORDER BY actual_usage DESC, t.usage_count DESC, t.name_th ASC
     LIMIT 6"
);
if ($tagResult) {
    $popularTags = $tagResult->fetch_all(MYSQLI_ASSOC);
}
$conn->close();
} catch (DatabaseConnectionException $error) {
    $homeDatabaseUnavailable = true;
}

function eventImageUrl($path) {
    $path = trim((string)$path);

    if ($path === '') {
        return '/assets/No_image.webp';
    }

    if (preg_match('/^https?:\/\//', $path)) {
        return $path;
    }

    $relativePath = ltrim(str_replace('\\', '/', $path), '/');
    $localPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    return is_file($localPath) ? '/' . $relativePath : '/assets/No_image.webp';
}

function eventDateLabel($date) {
    if (empty($date)) return '-';
    return date('d/m/Y H:i', strtotime($date));
}

$eventPayload = [];

foreach ($slides as $slide) {
    $registered = (int)($slide['registered_count'] ?? 0);
    $max = max(1, (int)($slide['max_participant'] ?? 1));
    $percent = min(100, round(($registered / $max) * 100));
    $tags = array_values(array_filter(array_map(
        'trim',
        explode('|||', (string)($slide['tag_names'] ?? ''))
    )));
    $tagSlugs = array_values(array_filter(array_map(
        'trim',
        explode('|||', (string)($slide['tag_slugs'] ?? ''))
    )));

    $eventPayload[] = [
        'id' => (int)$slide['event_id'],
        'title' => $slide['title'],
        'description' => mb_substr(strip_tags($slide['description'] ?? ''), 0, 130),
        'location' => $slide['location'],
        'start' => eventDateLabel($slide['event_start']),
        'end' => eventDateLabel($slide['event_end']),
        'regEnd' => !empty($slide['reg_end']) ? date('d/m/Y', strtotime($slide['reg_end'])) : '-',
        'registered' => $registered,
        'max' => $max,
        'percent' => $percent,
        'favoriteCount' => (int)($slide['favorite_count'] ?? 0),
        'tags' => $tags,
        'tagSlugs' => $tagSlugs,
        'image' => eventImageUrl($slide['cover_image'] ?? ''),
        'trendScore' => $registered,
        'joinOpen' => true,
        'searchText' => trim(($slide['title'] ?? '') . ' ' . ($slide['location'] ?? '') . ' ' . strip_tags($slide['description'] ?? '') . ' ' . implode(' ', $tags)),
        'saved' => in_array((int)$slide['event_id'], $savedEventIds, true),
    ];
}

?>

<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/webp" href="/assets/badomen-logo.webp">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="stylesheet" href="/style/app.css">
    <link rel="stylesheet" href="/style/header.css?v=4">
    <link rel="stylesheet" href="/style/home.css">
    <link rel="stylesheet" href="/style/footer.css" media="print" onload="this.media='all'">
    <link rel="stylesheet" href="/style/trusted.css" media="print" onload="this.media='all'">
    <link rel="stylesheet" href="/style/consent.css" media="print" onload="this.media='all'">
    <noscript>
        <link rel="stylesheet" href="/style/footer.css">
        <link rel="stylesheet" href="/style/trusted.css">
        <link rel="stylesheet" href="/style/consent.css">
    </noscript>
    <link rel="preload" as="image" href="/assets/hero-home.webp" fetchpriority="high" media="(min-width: 769px)">
    <title>ค้นหาอีเว้นท์ | Event Registration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"></noscript>
</head>

<body>
    <?php if ($homeDatabaseUnavailable): ?>
        <div role="status" style="margin:16px auto;max-width:1360px;padding:12px 16px;border:1px solid #fed7aa;border-radius:14px;color:#9a3412;background:#fff7ed">
            ไม่สามารถโหลดกิจกรรมได้ในขณะนี้ กรุณาตรวจสอบการตั้งค่าฐานข้อมูลใน <code>.env.local</code>
        </div>
    <?php endif; ?>
    <?php require __DIR__ . '/header.php'; ?>

    <header class="legacy-header" hidden>
        <div class="header-inner">
            <div class="logo">
                <a href="/">
                    <img src="/assets/badomen-logo.webp" alt="Event Logo" width="44" height="44">
                </a>
            </div>
            <div class="nav">
                <a class="login" href="/login">เข้าสู่ระบบ</a>
                <a class="register" href="/register">ลงทะเบียนฟรี</a>
            </div>
        </div>
    </header>

    <div class="wrap">
        <section class="hero hero-bg">
            <div class="hero-content">
    <h1>คุณกำลังมองหา<br><span>อีเว้นท์อะไร ?</span></h1>
    <p>เว็บที่รวบรวมกิจกรรม ไว้ในที่เดียว</p>

    <form class="hero-search-form" id="heroSearchForm" action="#activityCards" method="get">
        <div class="hero-search-box">
            <i class="fa-solid fa-magnifying-glass hero-search-icon"></i>

            <input 
                class="hero-search-input" 
                type="search"
                name="q"
                placeholder="ค้นหากิจกรรม, สถานที่, หมวดหมู่..."
                autocomplete="off"
                value="<?= htmlspecialchars($homeSearchQuery) ?>"
            >

            <button class="hero-search-btn" type="submit">
                ค้นหา
            </button>
        </div>

        <div class="hero-popular">
            <span>ยอดนิยม:</span>
            <?php foreach ($popularTags as $tag): ?>
                <a
                    href="?q=<?= rawurlencode((string)$tag['name_th']) ?>#activityCards"
                    data-search-tag="<?= htmlspecialchars((string)$tag['name_th'], ENT_QUOTES, 'UTF-8') ?>">
                    #<?= htmlspecialchars((string)$tag['name_th'], ENT_QUOTES, 'UTF-8') ?>
                </a>
            <?php endforeach; ?>
        </div>
    </form>
</div>
        </section>

        <section class="event-discovery<?= empty($eventPayload) ? ' event-discovery--empty' : '' ?>" id="eventDiscovery">
    <div class="event-stack-area<?= empty($eventPayload) ? ' event-stack-area--empty' : '' ?>">
        <?php if (!empty($eventPayload)): ?>
            <div class="event-stack" id="eventStack">
                <?php foreach ($eventPayload as $index => $event): ?>
                    <article 
                        class="event-stack-card <?= $index === 0 ? 'active' : '' ?><?= $index > 2 ? ' is-hidden' : '' ?>"
                        data-stack-index="<?= $index ?>"
                        aria-hidden="<?= $index > 2 ? 'true' : 'false' ?>"
                        style="--i: <?= $index ?>;"
                    >
                        <img 
                            src="<?= htmlspecialchars($event['image']) ?>" 
                            alt="<?= htmlspecialchars($event['title']) ?>"
                            width="840"
                            height="1020"
                            loading="<?= $index === 0 ? 'eager' : 'lazy' ?>"
                            fetchpriority="<?= $index === 0 ? 'high' : 'low' ?>"
                            decoding="async"
                            onerror="this.onerror=null; this.src='/assets/hero.webp';"
                        >

                        <div class="event-card-shade"></div>

                        <button type="button" class="event-heart<?= $event['saved'] ? ' is-saved' : '' ?>" aria-label="เก็บกิจกรรมนี้ไว้">
                            <i class="<?= $event['saved'] ? 'fa-solid' : 'fa-regular' ?> fa-heart"></i>
                        </button>

                        <span class="event-badge">
                            แนะนำสำหรับคุณ
                        </span>

                        <div class="event-stack-content">
                            <span class="event-location">
                                <i class="fa-solid fa-location-dot"></i>
                                <?= htmlspecialchars($event['location']) ?>
                            </span>

                            <h3><?= htmlspecialchars($event['title']) ?></h3>

                            <p><?= htmlspecialchars($event['description'] ?: 'กิจกรรมน่าสนใจที่คุณไม่ควรพลาด') ?></p>

                            <div class="event-mini-meta">
                                <span>
                                    <i class="fa-regular fa-calendar"></i>
                                    <?= htmlspecialchars($event['start']) ?>
                                </span>
                                <span>
                                    <i class="fa-solid fa-users"></i>
                                    <?= $event['registered'] ?>/<?= $event['max'] ?>
                                </span>
                                <span>
                                    <i class="fa-solid fa-chart-simple"></i>
                                    <?= $event['percent'] ?>%
                                </span>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="event-actions">
                <button type="button" class="event-action-btn ghost" id="eventPrevBtn" aria-label="ไม่สนใจและเลื่อนไปท้ายรายการ">
                    <i class="fa-solid fa-xmark"></i>
                </button>

                <button type="button" class="event-action-btn save" id="eventSaveBtn" aria-label="เก็บไว้ก่อน">
                    <i class="fa-regular fa-bookmark"></i>
                </button>

                <button type="button" class="event-action-btn next" id="eventNextBtn" aria-label="กิจกรรมถัดไป">
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        <?php else: ?>
            <div class="event-empty event-empty--deck" role="status" aria-live="polite">
                <div class="event-empty__visual" aria-hidden="true">
                    <span class="event-empty__halo"></span>
                    <span class="event-empty__icon"><i class="fa-regular fa-calendar-plus"></i></span>
                    <span class="event-empty__bubble event-empty__bubble--search"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <span class="event-empty__bubble event-empty__bubble--star"><i class="fa-solid fa-star"></i></span>
                </div>
                <span class="event-empty__kicker">NO EVENTS YET</span>
                <h3>ยังไม่มีกิจกรรมให้แสดง</h3>
                <p>ตอนนี้ยังไม่มีอีเวนต์ที่เปิดรับสมัคร ระบบจะแสดงการ์ดแนะนำพร้อมรูปภาพ สถานที่ และจำนวนผู้เข้าร่วมทันทีเมื่อมีผู้จัดสร้างกิจกรรมใหม่</p>
                <div class="event-empty__actions">
                    <a href="/login" class="event-empty__btn event-empty__btn--primary"><i class="fa-solid fa-right-to-bracket"></i> เข้าสู่ระบบ</a>
                    <a href="/register" class="event-empty__btn event-empty__btn--soft"><i class="fa-regular fa-user"></i> สมัครใช้งาน</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($eventPayload)): ?>
    <aside class="event-detail-panel" id="eventDetailPanel">
        <?php $firstEvent = $eventPayload[0]; ?>

            <span class="detail-place">
                <i class="fa-solid fa-location-dot"></i>
                <?= htmlspecialchars($firstEvent['location']) ?>
            </span>

            <h2 id="detailTitle"><?= htmlspecialchars($firstEvent['title']) ?></h2>

            <p id="detailDesc">
                <?= htmlspecialchars($firstEvent['description'] ?: 'กิจกรรมน่าสนใจที่คุณไม่ควรพลาด') ?>
            </p>

            <div class="detail-score" aria-label="คะแนนความนิยมจากข้อมูลจริง">
                <i class="fa-solid fa-chart-line"></i>
                <strong id="detailScore"><?= (int)$firstEvent['percent'] ?>%</strong>
                <span id="detailScoreMeta"><?= (int)$firstEvent['favoriteCount'] ?> รายการโปรด</span>
            </div>

            <div class="detail-list">
                <div>
                    <i class="fa-regular fa-calendar"></i>
                    <span>เริ่มกิจกรรม</span>
                    <strong id="detailStart"><?= htmlspecialchars($firstEvent['start']) ?></strong>
                </div>

                <div>
                    <i class="fa-solid fa-hourglass-half"></i>
                    <span>ปิดรับสมัคร</span>
                    <strong id="detailRegEnd"><?= htmlspecialchars($firstEvent['regEnd']) ?></strong>
                </div>

                <div>
                    <i class="fa-solid fa-users"></i>
                    <span>ผู้สมัคร</span>
                    <strong id="detailSlots"><?= $firstEvent['registered'] ?>/<?= $firstEvent['max'] ?> คน</strong>
                </div>

                <div>
                    <i class="fa-solid fa-signal"></i>
                    <span>ความนิยม</span>
                    <strong id="detailPercent"><?= $firstEvent['percent'] ?>%</strong>
                </div>
            </div>

            <div class="detail-progress">
                <span id="detailProgress" style="width: <?= $firstEvent['percent'] ?>%;"></span>
            </div>

            <button 
                type="button" 
                class="detail-main-btn" 
                id="detailOpenBtn"
            >
                ดูรายละเอียด
                <i class="fa-solid fa-arrow-right"></i>
            </button>
    </aside>
    <?php endif; ?>
</section>

<section class="activity-radar" id="activityCards" aria-labelledby="activityRadarTitle">
    <div class="activity-radar-head">
        <div>
            <span class="activity-kicker">
                <i class="fa-solid fa-fire-flame-curved"></i>
                กิจกรรมมาแรง
            </span>
            <h2 id="activityRadarTitle">กิจกรรมทั้งหมดที่ยังเปิดรับสมัคร</h2>
            <p>ระบบเรียงจากจำนวนผู้เข้าร่วมก่อน แล้ว search จะดันรายการที่ตรงคำค้นขึ้นบนโดยไม่ซ่อนกิจกรรมอื่น</p>
        </div>

        <div class="activity-radar-tools">
            <span id="activitySearchSummary">แสดงกิจกรรมแนะนำสูงสุด 20 รายการ</span>
            <button type="button" id="clearActivitySearch" class="activity-clear-btn">
                ล้างคำค้น
            </button>
        </div>
    </div>

    <?php if (!empty($eventPayload)): ?>
        <div class="activity-grid" id="activityGrid" aria-live="polite"></div>
    <?php else: ?>
        <div class="event-empty event-empty--wide" role="status" aria-live="polite">
            <div class="event-empty__visual" aria-hidden="true">
                <span class="event-empty__halo"></span>
                <span class="event-empty__icon"><i class="fa-solid fa-fire-flame-curved"></i></span>
                <span class="event-empty__bubble event-empty__bubble--search"><i class="fa-solid fa-chart-line"></i></span>
                <span class="event-empty__bubble event-empty__bubble--star"><i class="fa-solid fa-ticket"></i></span>
            </div>
            <span class="event-empty__kicker">TRENDING EMPTY</span>
            <h3>ยังไม่มีกิจกรรมที่เปิดรับสมัคร</h3>
            <p>เมื่อมีผู้จัดเผยแพร่กิจกรรม ระบบจะดึงกิจกรรมมาแรงมาแสดงอัตโนมัติ พร้อมจัดอันดับจากยอดผู้เข้าร่วมและรายการโปรด</p>
        </div>
    <?php endif; ?>
</section>

<script>
    window.BadomenEvents = <?= json_encode($eventPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    window.BadomenInitialQuery = <?= json_encode($homeSearchQuery, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    window.BadomenCsrfToken = <?= json_encode(csrfToken(), JSON_UNESCAPED_SLASHES) ?>;
    window.BadomenIsAuthenticated = <?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>;
</script>

    </div>

    <?php $showTrusted = true; ?>
    <?php $enableConsent = true; ?>

    <?php if (!empty($showTrusted)): ?>
        <?php require __DIR__ . '/trusted.php'; ?>
    <?php endif; ?>

    <?php $hideFooterLanguage = true; ?>
    <?php require __DIR__ . '/footer.php'; ?>


    <?php if (!empty($enableConsent)): ?>
        <?php require __DIR__ . '/consent.php'; ?>
    <?php endif; ?>

    <script>
        (() => {
            const baseEvents = window.BadomenEvents || [];
            let events = [...baseEvents];
            let cards = [];
            const eventStack = document.querySelector('#eventStack');
            const nextBtn = document.querySelector('#eventNextBtn');
            const prevBtn = document.querySelector('#eventPrevBtn');
            const saveBtn = document.querySelector('#eventSaveBtn');
            const detailPlace = document.querySelector('.detail-place');
            const detailTitle = document.querySelector('#detailTitle');
            const detailDesc = document.querySelector('#detailDesc');
            const detailScore = document.querySelector('#detailScore');
            const detailScoreMeta = document.querySelector('#detailScoreMeta');
            const detailStart = document.querySelector('#detailStart');
            const detailRegEnd = document.querySelector('#detailRegEnd');
            const detailSlots = document.querySelector('#detailSlots');
            const detailPercent = document.querySelector('#detailPercent');
            const detailProgress = document.querySelector('#detailProgress');
            const detailOpenBtn = document.querySelector('#detailOpenBtn');
            const searchForm = document.querySelector('#heroSearchForm');
            const searchInput = document.querySelector('.hero-search-input');
            const popularLinks = Array.from(document.querySelectorAll('[data-search-tag]'));
            const activityGrid = document.querySelector('#activityGrid');
            const activitySummary = document.querySelector('#activitySearchSummary');
            const clearSearchBtn = document.querySelector('#clearActivitySearch');

            let activeIndex = 0;
            const visibleCardCount = 3;
            let searchTimer = 0;
            const guestSavedKey = 'badomen_saved_events';

            function safeText(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function normalizeText(value) {
                return String(value ?? '')
                    .toLowerCase()
                    .normalize('NFC')
                    .replace(/\s+/g, ' ')
                    .trim();
            }

            function eventJoinUrl(event) {
                return `/login?event_id=${encodeURIComponent(event.id ?? '')}`;
            }

            function getGuestSavedIds() {
                try {
                    const value = JSON.parse(localStorage.getItem(guestSavedKey) || '[]');
                    return Array.isArray(value) ? value.map(Number).filter(Number.isInteger) : [];
                } catch (error) {
                    return [];
                }
            }

            function isEventSaved(event) {
                if (window.BadomenIsAuthenticated) return Boolean(event?.saved);
                return getGuestSavedIds().includes(Number(event?.id));
            }

            function renderStackCards() {
                if (!eventStack) return;

                const visibleEvents = [];
                const count = Math.min(visibleCardCount, events.length);
                for (let offset = 0; offset < count; offset += 1) {
                    const index = (activeIndex + offset) % events.length;
                    visibleEvents.push({ event: events[index], offset });
                }

                eventStack.innerHTML = visibleEvents.map(({ event, offset }) => {
                    const saved = isEventSaved(event);
                    return `
                        <article
                            class="event-stack-card${offset === 0 ? ' active' : ''}"
                            data-event-id="${safeText(event.id)}"
                            aria-hidden="${offset === 0 ? 'false' : 'true'}"
                            style="--i:${offset};"
                        >
                            <img
                                src="${safeText(event.image || '/assets/No_image.webp')}"
                                alt="${safeText(event.title || '')}"
                                width="840"
                                height="1020"
                                loading="${offset === 0 ? 'eager' : 'lazy'}"
                                decoding="async"
                            >
                            <div class="event-card-shade"></div>
                            <button type="button"
                                class="event-heart${saved ? ' is-saved' : ''}"
                                aria-label="${saved ? 'นำกิจกรรมออกจากรายการที่บันทึก' : 'เก็บกิจกรรมนี้ไว้'}"
                                aria-pressed="${saved ? 'true' : 'false'}">
                                <i class="${saved ? 'fa-solid' : 'fa-regular'} fa-heart"></i>
                            </button>
                            <span class="event-badge">แนะนำสำหรับคุณ</span>
                            <div class="event-stack-content">
                                <span class="event-location">
                                    <i class="fa-solid fa-location-dot"></i>
                                    ${safeText(event.location || '-')}
                                </span>
                                <h3>${safeText(event.title || 'กิจกรรมไม่มีชื่อ')}</h3>
                                <p>${safeText(event.description || 'กิจกรรมน่าสนใจที่คุณไม่ควรพลาด')}</p>
                                <div class="event-mini-meta">
                                    <span><i class="fa-regular fa-calendar"></i>${safeText(event.start || '-')}</span>
                                    <span><i class="fa-solid fa-users"></i>${Number(event.registered || 0)}/${Number(event.max || 0)}</span>
                                    <span><i class="fa-solid fa-chart-simple"></i>${Number(event.percent || 0)}%</span>
                                </div>
                            </div>
                        </article>
                    `;
                }).join('');

                cards = Array.from(eventStack.querySelectorAll('.event-stack-card'));
                updateSaveControls();
            }

            function updateCards() {
                renderStackCards();
            }

            function updateDetails() {
                const event = events[activeIndex];
                if (!event) return;

                if (detailPlace) {
                    detailPlace.innerHTML = `<i class="fa-solid fa-location-dot"></i> ${safeText(event.location || '-')}`;
                }
                if (detailTitle) detailTitle.textContent = event.title;
                if (detailDesc) detailDesc.textContent = event.description || 'กิจกรรมน่าสนใจที่คุณไม่ควรพลาด';
                if (detailScore) detailScore.textContent = `${Number(event.percent || 0)}%`;
                if (detailScoreMeta) detailScoreMeta.textContent = `${Number(event.favoriteCount || 0)} รายการโปรด`;
                if (detailStart) detailStart.textContent = event.start;
                if (detailRegEnd) detailRegEnd.textContent = event.regEnd;
                if (detailSlots) detailSlots.textContent = `${event.registered}/${event.max} คน`;
                if (detailPercent) detailPercent.textContent = `${event.percent}%`;
                if (detailProgress) detailProgress.style.transform = `scaleX(${event.percent / 100})`;
                updateSaveControls();
            }

            function showEvent(index) {
                if (!events.length) return;
                activeIndex = (index + events.length) % events.length;
                updateCards();
                updateDetails();
            }

            function goNext() {
                showEvent(activeIndex + 1);
            }

            function goPrev() {
                if (events.length < 2) return;
                const [dismissed] = events.splice(activeIndex, 1);
                events.push(dismissed);
                activeIndex %= events.length;
                updateCards();
                updateDetails();
            }

            function updateSavedState(eventId, saved) {
                [...baseEvents, ...events].forEach((event) => {
                    if (Number(event.id) === Number(eventId)) event.saved = saved;
                });
            }

            function updateSaveControls() {
                const event = events[activeIndex];
                if (!event) return;
                const saved = isEventSaved(event);

                if (saveBtn) {
                    saveBtn.classList.toggle('is-saved', saved);
                    saveBtn.setAttribute('aria-pressed', String(saved));
                    saveBtn.setAttribute('aria-label', saved ? 'นำออกจากรายการที่บันทึก' : 'เก็บไว้ก่อน');
                    const icon = saveBtn.querySelector('i');
                    if (icon) icon.className = `${saved ? 'fa-solid' : 'fa-regular'} fa-bookmark`;
                }

                const activeHeart = eventStack?.querySelector('.event-stack-card.active .event-heart');
                if (activeHeart) {
                    activeHeart.classList.toggle('is-saved', saved);
                    activeHeart.setAttribute('aria-pressed', String(saved));
                    activeHeart.setAttribute('aria-label', saved ? 'นำกิจกรรมออกจากรายการที่บันทึก' : 'เก็บกิจกรรมนี้ไว้');
                    const icon = activeHeart.querySelector('i');
                    if (icon) icon.className = `${saved ? 'fa-solid' : 'fa-regular'} fa-heart`;
                }
            }

            async function toggleSavedEvent(event) {
                if (!event?.id) return;

                if (!window.BadomenIsAuthenticated) {
                    const savedIds = getGuestSavedIds();
                    const eventId = Number(event.id);
                    const saved = !savedIds.includes(eventId);
                    const nextIds = saved
                        ? [...savedIds, eventId]
                        : savedIds.filter((id) => id !== eventId);
                    localStorage.setItem(guestSavedKey, JSON.stringify(nextIds));
                    updateSavedState(eventId, saved);
                    updateSaveControls();
                    return;
                }

                saveBtn?.setAttribute('disabled', 'disabled');
                try {
                    const body = new URLSearchParams({
                        _csrf: window.BadomenCsrfToken || '',
                        event_id: String(event.id),
                    });
                    const response = await fetch('/event-favorite', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                        body,
                    });
                    const result = await response.json();
                    if (!response.ok || !result.ok) throw new Error(result.error || 'save_failed');
                    updateSavedState(event.id, Boolean(result.saved));
                    updateSaveControls();
                } catch (error) {
                    alert('ไม่สามารถบันทึกกิจกรรมได้ในขณะนี้ กรุณาลองใหม่');
                } finally {
                    saveBtn?.removeAttribute('disabled');
                }
            }

            function getSearchMeta(event, query) {
                const q = normalizeText(query);
                if (!q) {
                    return { score: 0, matched: false, exact: false, reason: 'กิจกรรมมาแรง' };
                }

                const title = normalizeText(event.title);
                const location = normalizeText(event.location);
                const description = normalizeText(event.description);
                const tags = normalizeText((event.tags || []).join(' '));
                const tagSlugs = normalizeText((event.tagSlugs || []).join(' '));
                const searchText = normalizeText(event.searchText);
                const start = normalizeText(event.start);
                const regEnd = normalizeText(event.regEnd);
                const haystack = `${title} ${location} ${description} ${tags} ${tagSlugs} ${searchText} ${start} ${regEnd}`;
                const tokens = q.split(' ').filter(Boolean);
                let score = 0;
                let exact = false;
                let reason = 'ใกล้เคียงกับคำค้น';

                if (location === q) {
                    score += 220;
                    exact = true;
                    reason = 'สถานที่ตรงกัน';
                } else if (location.includes(q)) {
                    score += 150;
                    reason = 'พบในสถานที่';
                }

                if (title === q) {
                    score += 200;
                    exact = true;
                    reason = 'ชื่อกิจกรรมตรงกัน';
                } else if (title.includes(q)) {
                    score += 130;
                    reason = reason === 'ใกล้เคียงกับคำค้น' ? 'พบในชื่อกิจกรรม' : reason;
                }

                if (description.includes(q)) {
                    score += 70;
                    reason = reason === 'ใกล้เคียงกับคำค้น' ? 'พบในรายละเอียด' : reason;
                }

                if (tags.includes(q) || tagSlugs.includes(q)) {
                    score += 120;
                    reason = 'ตรงกับหมวดหมู่กิจกรรม';
                }

                if (start.includes(q) || regEnd.includes(q)) {
                    score += 45;
                    reason = reason === 'ใกล้เคียงกับคำค้น' ? 'พบในวันที่' : reason;
                }

                tokens.forEach((token) => {
                    if (token.length > 1 && haystack.includes(token)) {
                        score += 18;
                    }
                });

                return {
                    score,
                    matched: score > 0,
                    exact,
                    reason: score > 0 ? reason : 'กิจกรรมอื่นที่ยังเปิดรับสมัคร'
                };
            }

            function getSortedActivities(query) {
                const hasQuery = normalizeText(query).length > 0;

                return events
                    .map((event, index) => ({
                        ...event,
                        _originIndex: index,
                        _searchMeta: getSearchMeta(event, query)
                    }))
                    .sort((a, b) => {
                        if (hasQuery && b._searchMeta.score !== a._searchMeta.score) {
                            return b._searchMeta.score - a._searchMeta.score;
                        }

                        const trendDiff = Number(b.registered || 0) - Number(a.registered || 0);
                        if (trendDiff !== 0) return trendDiff;

                        const percentDiff = Number(b.percent || 0) - Number(a.percent || 0);
                        if (percentDiff !== 0) return percentDiff;

                        return a._originIndex - b._originIndex;
                    });
            }

            function renderActivityGrid(query = '', shouldScroll = false) {
                if (!activityGrid) return;

                const q = normalizeText(query);
                const sortedEvents = getSortedActivities(q);
                const matchedCount = sortedEvents.filter((event) => event._searchMeta.matched).length;
                events = sortedEvents;
                activeIndex = 0;
                updateCards();
                updateDetails();

                if (activitySummary) {
                    activitySummary.textContent = q
                        ? `พบ ${matchedCount} รายการที่เกี่ยวข้องกับ “${query}” และยังแสดงกิจกรรมอื่นต่อ`
                        : `แสดงกิจกรรมมาแรง ${sortedEvents.length} รายการ จากจำนวนผู้เข้าร่วม`;
                }

                activityGrid.innerHTML = sortedEvents.map((event, index) => {
                    const meta = event._searchMeta;
                    const badgeText = q ? meta.reason : (index < 3 ? 'กำลังมาแรง' : 'ยังเปิดรับสมัคร');
                    const cardClass = [
                        'activity-card',
                        meta.matched ? 'is-search-match' : '',
                        meta.exact ? 'is-exact-match' : ''
                    ].filter(Boolean).join(' ');

                    return `
                        <article class="${cardClass}" data-event-id="${safeText(event.id)}">
                            <a class="activity-cover" href="${eventJoinUrl(event)}" aria-label="#${index + 1} ${safeText(badgeText)} ดูรายละเอียด ${safeText(event.title)}">
                                <img
                                    src="${safeText(event.image || '/assets/hero.webp')}"
                                    alt="${safeText(event.title)}"
                                    loading="lazy"
                                    decoding="async"
                                    onerror="this.onerror=null; this.src='/assets/hero.webp';"
                                >
                                <span class="activity-rank">#${index + 1}</span>
                                <span class="activity-match-badge">${safeText(badgeText)}</span>
                            </a>

                            <div class="activity-card-body">
                                <span class="activity-place">
                                    <i class="fa-solid fa-location-dot"></i>
                                    ${safeText(event.location || '-')}
                                </span>

                                <h3>${safeText(event.title || 'กิจกรรมไม่มีชื่อ')}</h3>
                                <p>${safeText(event.description || 'กิจกรรมน่าสนใจที่คุณไม่ควรพลาด')}</p>

                                <div class="activity-meta-row">
                                    <span>
                                        <i class="fa-regular fa-calendar"></i>
                                        ${safeText(event.start || '-')}
                                    </span>
                                    <span>
                                        <i class="fa-solid fa-hourglass-half"></i>
                                        ปิดรับ ${safeText(event.regEnd || '-')}
                                    </span>
                                </div>

                                <div class="activity-bottom">
                                    <div>
                                        <strong>${Number(event.registered || 0)}/${Number(event.max || 0)}</strong>
                                        <small>คนเข้าร่วม</small>
                                    </div>
                                    <div class="activity-progress" role="progressbar" aria-label="ความนิยม ${Number(event.percent || 0)}%" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${Math.max(0, Math.min(100, Number(event.percent || 0)))}">
                                        <span style="width: ${Math.max(0, Math.min(100, Number(event.percent || 0)))}%;"></span>
                                    </div>
                                    <a class="activity-card-cta" href="${eventJoinUrl(event)}">
                                        ดูรายละเอียด
                                    </a>
                                </div>
                            </div>
                        </article>
                    `;
                }).join('');

                if (shouldScroll) {
                    document.querySelector('#activityCards')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }

            nextBtn?.addEventListener('click', goNext);
            prevBtn?.addEventListener('click', goPrev);
            saveBtn?.addEventListener('click', () => toggleSavedEvent(events[activeIndex]));
            eventStack?.addEventListener('click', (event) => {
                const heart = event.target.closest('.event-heart');
                if (!heart) return;
                toggleSavedEvent(events[activeIndex]);
            });
            detailOpenBtn?.addEventListener('click', () => {
                const event = events[activeIndex];
                location.href = eventJoinUrl(event || {});
            });

            searchForm?.addEventListener('submit', (event) => {
                event.preventDefault();
                const query = searchInput?.value.trim() || '';
                const url = new URL(location.href);

                if (query) {
                    url.searchParams.set('q', query);
                    url.hash = 'activityCards';
                } else {
                    url.searchParams.delete('q');
                    url.hash = '';
                }

                history.replaceState(null, '', url.toString());
                renderActivityGrid(query, true);
            });

            searchInput?.addEventListener('input', () => {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => {
                    renderActivityGrid(searchInput.value, false);
                }, 120);
            });

            popularLinks.forEach((link) => {
                link.addEventListener('click', (event) => {
                    event.preventDefault();
                    const query = link.dataset.searchTag || link.textContent.replace('#', '').trim();
                    if (searchInput) searchInput.value = query;
                    renderActivityGrid(query, true);

                    const url = new URL(location.href);
                    url.searchParams.set('q', query);
                    url.hash = 'activityCards';
                    history.replaceState(null, '', url.toString());
                });
            });

            let swipeStartX = 0;
            let swipeDeltaX = 0;
            let swipingCard = null;

            eventStack?.addEventListener('pointerdown', (pointerEvent) => {
                if (pointerEvent.button !== 0 || pointerEvent.target.closest('button, a')) return;
                const card = pointerEvent.target.closest('.event-stack-card.active');
                if (!card) return;
                swipingCard = card;
                swipeStartX = pointerEvent.clientX;
                swipeDeltaX = 0;
                card.classList.add('is-swiping');
                card.setPointerCapture(pointerEvent.pointerId);
            });

            eventStack?.addEventListener('pointermove', (pointerEvent) => {
                if (!swipingCard) return;
                swipeDeltaX = Math.max(-150, Math.min(150, pointerEvent.clientX - swipeStartX));
                const rotation = swipeDeltaX / 24;
                swipingCard.style.transform = `translate3d(${swipeDeltaX}px,0,0) rotate(${rotation}deg)`;
            });

            function finishSwipe(pointerEvent) {
                if (!swipingCard) return;
                const card = swipingCard;
                swipingCard = null;
                card.classList.remove('is-swiping');

                if (Math.abs(swipeDeltaX) < 72) {
                    card.style.transform = '';
                    swipeDeltaX = 0;
                    return;
                }

                const direction = swipeDeltaX < 0 ? 'left' : 'right';
                card.style.transform = '';
                card.classList.add(`swipe-out-${direction}`);
                window.setTimeout(() => {
                    direction === 'left' ? goPrev() : goNext();
                }, 170);
                if (pointerEvent && card.hasPointerCapture(pointerEvent.pointerId)) {
                    card.releasePointerCapture(pointerEvent.pointerId);
                }
                swipeDeltaX = 0;
            }

            eventStack?.addEventListener('pointerup', finishSwipe);
            eventStack?.addEventListener('pointercancel', finishSwipe);

            clearSearchBtn?.addEventListener('click', () => {
                if (searchInput) searchInput.value = '';
                const url = new URL(location.href);
                url.searchParams.delete('q');
                url.hash = 'activityCards';
                history.replaceState(null, '', url.toString());
                renderActivityGrid('', false);
                searchInput?.focus();
            });

            const runInitialRender = () => {
                updateCards();
                updateDetails();
                renderActivityGrid(window.BadomenInitialQuery || new URLSearchParams(location.search).get('q') || '', Boolean(location.hash === '#activityCards'));
            };
            const idle = window.requestIdleCallback || ((callback) => window.setTimeout(callback, 450));
            idle(runInitialRender, { timeout: 1400 });
        })();
    </script>
</body>
</html>
