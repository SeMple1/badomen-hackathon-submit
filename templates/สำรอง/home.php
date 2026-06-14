<?php
// สมมติว่ามีฟังก์ชัน getConnection() อยู่แล้ว
$conn = getConnection();

/* =========================
   ดึง event สำหรับ slider
========================= */
$slides = [];
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
    ) AS cover_image
FROM events e
ORDER BY e.event_start ASC
LIMIT 8
";

$resultSlides = $conn->query($sqlSlides);
if ($resultSlides) {
    $slides = $resultSlides->fetch_all(MYSQLI_ASSOC);
}

function eventImageUrl($path) {
    $path = trim((string)$path);

    if ($path === '') {
        return '/assets/event-placeholder.png';
    }

    if (preg_match('/^https?:\/\//', $path)) {
        return $path;
    }

    return '/' . ltrim($path, '/');
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
        'image' => eventImageUrl($slide['cover_image'] ?? ''),
    ];
}

$conn->close();
?>

<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/style/app.css">
    <link rel="stylesheet" href="/style/home.css">
    <link rel="stylesheet" href="/style/footer.css">
    <link rel="stylesheet" href="/style/trusted.css">
    <link rel="stylesheet" href="/style/consent.css">
    <title>ค้นหาอีเว้นท์ | Event Registration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>

    <header>
        <div class="header-inner">
            <div class="logo">
                <a href="/">
                    <img src="/mylogo1.png" alt="Event Logo">
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

    <form class="hero-search-form" action="/search" method="get">
        <div class="hero-search-box">
            <i class="fa-solid fa-magnifying-glass hero-search-icon"></i>

            <input 
                class="hero-search-input" 
                type="search"
                name="q"
                placeholder="ค้นหากิจกรรม, สถานที่, หมวดหมู่..."
                autocomplete="off"
            >

            <button class="hero-search-btn" type="submit">
                ค้นหา
            </button>
        </div>

        <div class="hero-popular">
            <span>ยอดนิยม:</span>

            <a href="/search?q=คอนเสิร์ต">#คอนเสิร์ต</a>
            <a href="/search?q=กีฬา">#กีฬา</a>
            <a href="/search?q=อบรมสัมมนา">#อบรมสัมมนา</a>
            <a href="/search?q=งานแฟร์">#งานแฟร์</a>
            <a href="/search?q=เทคโนโลยี">#เทคโนโลยี</a>
        </div>
    </form>
</div>
        </section>

        <section class="event-discovery" id="eventDiscovery">
    <aside class="event-mood-panel">
        <span class="event-section-kicker">วันนี้</span>
        <h2>อยากออกไป<br>ทำอะไรดี?</h2>
        <p>เลือกความรู้สึก แล้วเราแนะนำกิจกรรมที่เหมาะกับคุณ</p>

        <div class="mood-list">
            <button type="button" class="mood-btn active" aria-pressed="true">
                <i class="fa-solid fa-sun"></i>
                ทั้งหมด
            </button>
            <button type="button" class="mood-btn" aria-pressed="false">
                <i class="fa-solid fa-mug-hot"></i>
                ชิลล์ ผ่อนคลาย
            </button>
            <button type="button" class="mood-btn" aria-pressed="false">
                <i class="fa-solid fa-mountain-sun"></i>
                ผจญภัย ตื่นเต้น
            </button>
            <button type="button" class="mood-btn" aria-pressed="false">
                <i class="fa-solid fa-utensils"></i>
                สายกิน
            </button>
            <button type="button" class="mood-btn" aria-pressed="false">
                <i class="fa-solid fa-palette"></i>
                สร้างสรรค์
            </button>
            <button type="button" class="mood-btn" aria-pressed="false">
                <i class="fa-solid fa-moon"></i>
                แฮงเอาท์กลางคืน
            </button>
        </div>

        <button type="button" class="mood-help">
            <span>
                <i class="fa-solid fa-wand-magic-sparkles"></i>
            </span>
            <strong>ไม่รู้จะเลือกอะไรดี?</strong>
            <small>ลองสุ่มกิจกรรมดูสิ</small>
        </button>
    </aside>

    <div class="event-stack-area">
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
                            onerror="this.onerror=null; this.src='/assets/hero.png';"
                        >

                        <div class="event-card-shade"></div>

                        <button type="button" class="event-heart" aria-label="เก็บกิจกรรมนี้ไว้">
                            <i class="fa-regular fa-heart"></i>
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
                <button type="button" class="event-action-btn ghost" id="eventPrevBtn" aria-label="ย้อนกลับ">
                    <i class="fa-solid fa-xmark"></i>
                </button>

                <button type="button" class="event-action-btn save" aria-label="เก็บไว้ก่อน">
                    <i class="fa-regular fa-bookmark"></i>
                </button>

                <button type="button" class="event-action-btn next" id="eventNextBtn" aria-label="กิจกรรมถัดไป">
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        <?php else: ?>
            <div class="event-empty">
                <i class="fa-regular fa-calendar-xmark"></i>
                <h3>ยังไม่มีกิจกรรมให้แสดง</h3>
                <p>เมื่อมีกิจกรรมใหม่ ระบบจะแสดงกิจกรรมแนะนำตรงนี้</p>
            </div>
        <?php endif; ?>
    </div>

    <aside class="event-detail-panel" id="eventDetailPanel">
        <?php if (!empty($eventPayload)): ?>
            <?php $firstEvent = $eventPayload[0]; ?>

            <span class="detail-place">
                <i class="fa-solid fa-location-dot"></i>
                <?= htmlspecialchars($firstEvent['location']) ?>
            </span>

            <h2 id="detailTitle"><?= htmlspecialchars($firstEvent['title']) ?></h2>

            <p id="detailDesc">
                <?= htmlspecialchars($firstEvent['description'] ?: 'กิจกรรมน่าสนใจที่คุณไม่ควรพลาด') ?>
            </p>

            <div class="detail-score">
                <i class="fa-solid fa-star"></i>
                <strong>4.8</strong>
                <span>(128 รีวิว)</span>
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
        <?php endif; ?>
    </aside>
</section>

<script>
    window.BadomenEvents = <?= json_encode($eventPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>

    </div>

    <?php $showTrusted = true; ?>
    <?php $enableConsent = true; ?>

    <?php if (!empty($showTrusted)): ?>
        <?php require __DIR__ . '/trusted.php'; ?>
    <?php endif; ?>

    <?php require __DIR__ . '/footer.php'; ?>


    <?php if (!empty($enableConsent)): ?>
        <?php require __DIR__ . '/consent.php'; ?>
    <?php endif; ?>

    <script>
        (() => {
            const events = window.BadomenEvents || [];
            const cards = Array.from(document.querySelectorAll('.event-stack-card'));
            const nextBtn = document.querySelector('#eventNextBtn');
            const prevBtn = document.querySelector('#eventPrevBtn');
            const detailTitle = document.querySelector('#detailTitle');
            const detailDesc = document.querySelector('#detailDesc');
            const detailStart = document.querySelector('#detailStart');
            const detailRegEnd = document.querySelector('#detailRegEnd');
            const detailSlots = document.querySelector('#detailSlots');
            const detailPercent = document.querySelector('#detailPercent');
            const detailProgress = document.querySelector('#detailProgress');
            const detailOpenBtn = document.querySelector('#detailOpenBtn');
            const moodList = document.querySelector('.mood-list');
            const moodHelp = document.querySelector('.mood-help');

            let activeIndex = 0;
            let activeMood = document.querySelector('.mood-btn.active');
            const visibleCardCount = 3;

            function updateCards() {
                cards.forEach((card, index) => {
                    const offset = (index - activeIndex + cards.length) % cards.length;
                    const isVisible = offset < visibleCardCount;
                    const isActive = offset === 0;

                    card.classList.toggle('is-hidden', !isVisible);
                    card.classList.toggle('active', isActive);
                    card.setAttribute('aria-hidden', isVisible ? 'false' : 'true');

                    if (isVisible) {
                        card.style.setProperty('--i', offset);
                    }
                });
            }

            function updateDetails() {
                const event = events[activeIndex];
                if (!event) return;

                if (detailTitle) detailTitle.textContent = event.title;
                if (detailDesc) detailDesc.textContent = event.description || 'กิจกรรมน่าสนใจที่คุณไม่ควรพลาด';
                if (detailStart) detailStart.textContent = event.start;
                if (detailRegEnd) detailRegEnd.textContent = event.regEnd;
                if (detailSlots) detailSlots.textContent = `${event.registered}/${event.max} คน`;
                if (detailPercent) detailPercent.textContent = `${event.percent}%`;
                if (detailProgress) detailProgress.style.transform = `scaleX(${event.percent / 100})`;
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
                showEvent(activeIndex - 1);
            }

            nextBtn?.addEventListener('click', goNext);
            prevBtn?.addEventListener('click', goPrev);
            detailOpenBtn?.addEventListener('click', () => {
                location.href = '/login';
            });

            moodList?.addEventListener('click', (event) => {
                const btn = event.target.closest('.mood-btn');
                if (!btn || btn === activeMood) return;

                activeMood?.classList.remove('active');
                activeMood?.setAttribute('aria-pressed', 'false');
                btn.classList.add('active');
                btn.setAttribute('aria-pressed', 'true');
                activeMood = btn;
                goNext();
            });

            moodHelp?.addEventListener('click', () => {
                if (events.length < 2) return;

                let randomIndex = activeIndex;
                while (randomIndex === activeIndex) {
                    randomIndex = Math.floor(Math.random() * events.length);
                }
                showEvent(randomIndex);
            });

            updateCards();
            updateDetails();
        })();
    </script>
</body>
</html>
