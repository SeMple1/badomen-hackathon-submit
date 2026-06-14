<?php
$errors = $errors ?? [];
$info = $info ?? [];
$event = $event ?? null;
$event_id = (int)($event_id ?? 0);
$code = $code ?? null;
$record = $record ?? null;
$now = (int)($now ?? time());
$ttl = (int)($ttl_seconds ?? 1800);
$is_pending = (bool)($is_pending ?? false);
$is_rejected = (bool)($is_rejected ?? false);
$is_checked_in = (bool)($is_checked_in ?? false);
$is_outside_event_window = (bool)($is_outside_event_window ?? false);
$event_window_warning = (string)($event_window_warning ?? '');
$requires_outside_window_confirm = (bool)($requires_outside_window_confirm ?? false);
$cooldown = (int)($cooldown_seconds ?? 180);
$status = (string)($status ?? '');

$escape = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$title = $event && isset($event['title']) ? (string)$event['title'] : 'ขอ OTP';

$ttlRemaining = 0;
$cooldownRemaining = 0;
if (is_array($record)) {
    $requestedAt = (int)($record['requested_at'] ?? 0);
    $expiresAt = (int)($record['expires_at'] ?? 0);
    $ttlRemaining = max(0, $expiresAt - $now);
    $cooldownRemaining = max(0, $cooldown - ($now - $requestedAt));
}

$statusText = 'พร้อมใช้งาน';
$statusClass = 'userotp-status--ready';
if ($is_pending) {
    $statusText = 'รออนุมัติ';
    $statusClass = 'userotp-status--pending';
} elseif ($is_rejected) {
    $statusText = 'ยกเลิก';
    $statusClass = 'userotp-status--rejected';
} elseif ($is_checked_in) {
    $statusText = 'ยืนยันสิทธิ์แล้ว';
    $statusClass = 'userotp-status--checked';
} elseif (!$code) {
    $statusText = 'ยังไม่มี OTP';
    $statusClass = 'userotp-status--idle';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="theme-color" content="#080f18" />
    <link rel="stylesheet" href="/style/app.css">
    <link rel="stylesheet" href="/style/userotp.css?v=5">
    <title>userOTP | Badomen</title>
</head>

<body class="userotp-page<?= $is_checked_in ? ' has-pass-modal' : '' ?>">
    <?php require __DIR__ . '/header.php'; ?>

    <?php if ($is_pending): ?>
        <div class="userotp-modal userotp-modal--pending" role="dialog" aria-modal="true" aria-labelledby="pendingTitle">
            <div class="userotp-modal__backdrop"></div>
            <section class="userotp-modal__card userotp-modal__card--warning">
                <span class="userotp-modal__eyebrow">WAITING APPROVAL</span>
                <h2 id="pendingTitle">คำขอเข้าร่วมยังรอการอนุมัติ</h2>
                <p>ยังไม่สามารถใช้งาน OTP ได้ จนกว่าผู้จัดกิจกรรมจะอนุมัติสิทธิ์ของคุณ</p>
                <a href="/join_activity" class="userotp-modal__button">กลับไปหน้ากิจกรรมของฉัน</a>
            </section>
        </div>
    <?php endif; ?>

    <?php if ($is_rejected): ?>
        <div class="userotp-modal userotp-modal--rejected" role="dialog" aria-modal="true" aria-labelledby="rejectedTitle">
            <div class="userotp-modal__backdrop"></div>
            <section class="userotp-modal__card userotp-modal__card--danger">
                <span class="userotp-modal__eyebrow">BOOKING CANCELLED</span>
                <h2 id="rejectedTitle">คำขอเข้าร่วมถูกยกเลิก</h2>
                <p>คุณยังไม่สามารถใช้งาน OTP สำหรับกิจกรรมนี้ได้</p>
                <a href="/join_activity" class="userotp-modal__button">กลับไปหน้ากิจกรรมของฉัน</a>
            </section>
        </div>
    <?php endif; ?>

    <?php if ($is_checked_in): ?>
        <div class="userotp-modal userotp-modal--pass" role="dialog" aria-modal="true" aria-labelledby="passTitle" data-pass-modal>
            <div class="userotp-modal__backdrop userotp-modal__backdrop--pass"></div>
            <section class="userotp-pass-card">
                <div class="userotp-fire-ring" aria-hidden="true">
                    <span></span><span></span><span></span><span></span>
                </div>
                <div class="userotp-pass-badge">PASS</div>
                <span class="userotp-modal__eyebrow">ACCESS CONFIRMED</span>
                <h2 id="passTitle">มีสิทธิเข้าร่วมแล้ว</h2>
                <p>ระบบยืนยันสิทธิ์สำหรับกิจกรรม <strong><?= $escape($title) ?></strong> เรียบร้อยแล้ว</p>
                <a href="/join_activity" class="userotp-pass-button">
                    <span>กลับไปหน้ากิจกรรมของฉัน</span>
                    <i aria-hidden="true"></i>
                </a>
            </section>
        </div>
    <?php endif; ?>

    <main class="userotp-main">
        <section class="userotp-shell" aria-labelledby="userOtpTitle">
            <div class="userotp-hero-card">
                <div class="userotp-hero-card__glow" aria-hidden="true"></div>

                <div class="userotp-info-panel">
                    <div class="userotp-kicker"><span></span> Participant OTP</div>
                    <h1 id="userOtpTitle">รหัสยืนยันสำหรับผู้เข้าร่วม</h1>
                    <p>ขอ OTP แล้วนำรหัส 6 หลักไปแจ้งผู้จัดกิจกรรม ระบบจะอัปเดตสถานะอัตโนมัติเมื่อฝั่งผู้จัดยืนยันแล้ว</p>

                    <div class="userotp-event-box">
                        <span>กิจกรรม</span>
                        <strong><?= $escape($title) ?></strong>
                        <small>event_id: <?= (int)$event_id ?></small>
                    </div>

                    <div class="userotp-guide">
                        <span>วิธีใช้งาน</span>
                        <ul class="instruction-list">
                            <li><i></i>กด “ขอรหัส OTP ใหม่อีกครั้ง” แล้วแจ้งรหัสให้ผู้จัด</li>
                            <li><i></i>รหัสใช้ได้ 30 นาที และขอซ้ำได้ตาม cooldown เดิม 3 นาที</li>
                            <li><i></i>เมื่อผู้จัด verify สำเร็จ หน้า user จะเด้ง popup ยืนยันสิทธิ์</li>
                        </ul>
                    </div>
                </div>

                <div class="userotp-console">
                    <div class="userotp-console__top">
                        <div>
                            <span>Your OTP</span>
                            <p>สถานะปัจจุบันของสิทธิ์เข้าร่วม</p>
                        </div>
                        <strong class="userotp-status <?= $escape($statusClass) ?>"><?= $escape($statusText) ?></strong>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="userotp-alert userotp-alert--error">
                            <ul class="compact-list">
                                <?php foreach ($errors as $e): ?>
                                    <li><?= $escape($e) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($is_outside_event_window && $event_window_warning !== ''): ?>
                        <div class="userotp-alert userotp-alert--warning">
                            <strong>อยู่นอกช่วงกิจกรรม</strong>
                            <p><?= $escape($event_window_warning) ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($info)): ?>
                        <div class="userotp-alert userotp-alert--info">
                            <ul class="compact-list">
                                <?php foreach ($info as $m): ?>
                                    <li><?= $escape($m) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <section class="userotp-code-card" aria-label="OTP 6 หลัก">
                        <div class="userotp-code-card__label">OTP CODE</div>
                        <?php if ($code): ?>
                            <div class="otp-digits userotp-code"><?= $escape($code) ?></div>
                            <div class="userotp-count-row">
                                <span>หมดอายุใน <b id="ttlText"><?= (int)$ttlRemaining ?></b> วินาที</span>
                                <span>ขอใหม่ได้ใน <b id="cooldownText"><?= (int)$cooldownRemaining ?></b> วินาที</span>
                            </div>
                            <div class="userotp-progress" aria-hidden="true">
                                <div id="ttlBar"></div>
                            </div>
                        <?php else: ?>
                            <div class="userotp-empty-code">ยังไม่มี OTP หรือ OTP หมดอายุ</div>
                            <p class="userotp-console-note">กดปุ่มด้านล่างเพื่อสร้างรหัสใหม่ตามกฎเดิมของระบบ</p>
                        <?php endif; ?>
                    </section>

                    <?php if (!$is_checked_in && !$is_pending): ?>
                        <form method="post" class="userotp-actions" data-outside-window="<?= $is_outside_event_window ? '1' : '0' ?>" data-outside-window-message="<?= $escape($event_window_warning) ?>">
                            <input type="hidden" name="event_id" value="<?= (int)$event_id ?>">
                            <input type="hidden" name="confirm_outside_window" value="<?= $requires_outside_window_confirm ? '1' : '0' ?>" data-outside-window-confirm>
                            <button id="otpBtn" type="submit" class="userotp-primary-btn" <?= ($cooldownRemaining > 0) ? 'disabled' : '' ?>>
                                ขอรหัส OTP ใหม่อีกครั้ง
                            </button>
                            <a href="/join_activity" class="userotp-secondary-btn">กลับ</a>
                        </form>
                    <?php endif; ?>

                    <div class="userotp-footnote">ระบบจะลบ/ปิด OTP ตามเงื่อนไขเดิม และตรวจสถานะยืนยันสิทธิ์แบบเบาๆ ทุก 3 วินาที</div>
                </div>
            </div>
        </section>
    </main>

    <div class="userotp-checking" aria-hidden="true">
        <span></span>
        กำลังอัปเดตสิทธิ์เข้าร่วม
    </div>

    <script>
        (() => {
            'use strict';

            let ttl = <?= (int)$ttlRemaining ?>;
            let cooldown = <?= (int)$cooldownRemaining ?>;
            const ttlTotal = <?= (int)$ttl ?>;
            const hasCode = <?= $code ? 'true' : 'false' ?>;
            const alreadyCheckedIn = <?= !empty($is_checked_in) ? 'true' : 'false' ?>;
            const eventId = <?= (int)$event_id ?>;

            const ttlText = document.getElementById('ttlText');
            const cooldownText = document.getElementById('cooldownText');
            const ttlBar = document.getElementById('ttlBar');
            const otpBtn = document.getElementById('otpBtn');
            const otpForm = otpBtn?.closest('form');
            const outsideConfirmInput = otpForm?.querySelector('[data-outside-window-confirm]');

            let lastTick = Date.now();
            let tickTimer = 0;
            let pollTimer = 0;
            const pollDeadline = Date.now() + (30 * 60 * 1000);

            function render() {
                const safeTtl = Math.max(0, ttl);
                const safeCooldown = Math.max(0, cooldown);

                if (ttlText) ttlText.textContent = safeTtl;
                if (cooldownText) cooldownText.textContent = safeCooldown;

                if (ttlBar && hasCode) {
                    const pct = ttlTotal > 0 ? Math.max(0, Math.min(1, safeTtl / ttlTotal)) : 0;
                    ttlBar.style.transform = `scaleX(${pct})`;
                    ttlBar.style.transformOrigin = 'left center';
                    ttlBar.style.width = '100%';
                }

                if (otpBtn) otpBtn.disabled = safeCooldown > 0;
                if (hasCode && safeTtl <= 0) {
                    window.location.reload();
                }
            }

            function startCountdown() {
                if (!hasCode && cooldown <= 0) {
                    render();
                    return;
                }

                window.clearTimeout(tickTimer);
                lastTick = Date.now();

                const tick = () => {
                    const nowMs = Date.now();
                    const step = Math.max(1, Math.round((nowMs - lastTick) / 1000));
                    lastTick = nowMs;

                    if (hasCode && ttl > 0) ttl -= step;
                    if (cooldown > 0) cooldown -= step;

                    render();

                    if ((hasCode && ttl > 0) || cooldown > 0) {
                        tickTimer = window.setTimeout(tick, 1000);
                    }
                };

                render();
                tickTimer = window.setTimeout(tick, 1000);
            }

            function getPollDelay() {
                const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
                if (document.hidden) return 15000;
                if (connection && connection.saveData) return 12000;
                return 3000;
            }

            async function checkCheckedIn() {
                if (alreadyCheckedIn || Date.now() > pollDeadline) return;

                if (document.hidden) {
                    schedulePoll();
                    return;
                }

                const controller = new AbortController();
                const abortTimer = window.setTimeout(() => controller.abort(), 2500);

                try {
                    const res = await fetch(`/userotp?event_id=${eventId}`, {
                        method: 'HEAD',
                        cache: 'no-store',
                        credentials: 'same-origin',
                        signal: controller.signal
                    });

                    if (res.ok && res.headers.get('X-Checked-In') === '1') {
                        document.body.classList.add('is-checking-in');
                        window.location.reload();
                        return;
                    }
                } catch (_) {
                    // เงียบไว้เพื่อไม่รบกวนผู้ใช้บน network ช้า
                } finally {
                    window.clearTimeout(abortTimer);
                }

                schedulePoll();
            }

            function schedulePoll(delay = getPollDelay()) {
                if (alreadyCheckedIn || Date.now() > pollDeadline) return;
                window.clearTimeout(pollTimer);
                pollTimer = window.setTimeout(checkCheckedIn, delay);
            }

            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    window.clearTimeout(tickTimer);
                    window.clearTimeout(pollTimer);
                    return;
                }

                startCountdown();
                schedulePoll(600);
            }, { passive: true });

            window.addEventListener('pagehide', () => {
                window.clearTimeout(tickTimer);
                window.clearTimeout(pollTimer);
            }, { passive: true });

            otpForm?.addEventListener('submit', (event) => {
                if (otpForm.dataset.outsideWindow !== '1' || outsideConfirmInput?.value === '1') return;
                const message = otpForm.dataset.outsideWindowMessage || 'ตอนนี้ไม่ได้อยู่ในช่วงวัน/เวลาของกิจกรรม ต้องการขอหรือกรอก OTP แน่นอนใช่ไหม?';
                if (!window.confirm(message)) {
                    event.preventDefault();
                    return;
                }
                if (outsideConfirmInput) outsideConfirmInput.value = '1';
            });

            startCountdown();
            if (!alreadyCheckedIn) schedulePoll(3000);
        })();
    </script>
</body>
</html>
