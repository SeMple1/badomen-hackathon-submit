<?php
$errors = $errors ?? [];
$success = $success ?? null;
$event = $event ?? null;
$event_id = (int)($event_id ?? 0);

$escape = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$title = $event && isset($event['title']) ? (string)$event['title'] : 'ตรวจสอบ OTP';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="theme-color" content="#080f18" />
    <link rel="stylesheet" href="/style/app.css">
    <link rel="stylesheet" href="/style/verifyotp.css?v=5">
    <title>verifyOTP | Badomen</title>
</head>

<body class="verifyotp-page<?= $success ? ' verifyotp-page--success' : '' ?>">
    <?php require __DIR__ . '/header.php'; ?>

    <main class="verifyotp-main">
        <section class="verifyotp-shell" aria-labelledby="verifyOtpTitle">
            <div class="verifyotp-card">
                <div class="verifyotp-card__flare" aria-hidden="true"></div>

                <aside class="verifyotp-info">
                    <div class="verifyotp-kicker"><span></span> Creator verification</div>
                    <h1 id="verifyOtpTitle">ตรวจสอบรหัส OTP</h1>
                    <p>กรอกรหัส 6 หลักจากผู้เข้าร่วมเพื่อยืนยันสิทธิ์เข้ากิจกรรม ระบบจะอัปเดตสถานะเป็น checked_in ตาม flow เดิม</p>

                    <div class="verifyotp-event-box">
                        <span>กิจกรรมที่กำลังตรวจ</span>
                        <strong><?= $escape($title) ?></strong>
                        <small>event_id: <?= (int)$event_id ?></small>
                    </div>

                    <div class="verifyotp-guide">
                        <span>ขั้นตอน</span>
                        <ul class="instruction-list">
                            <li><i></i>ให้ผู้เข้าร่วมเปิดหน้า userOTP แล้วบอกรหัส 6 หลัก</li>
                            <li><i></i>กรอกรหัสให้ครบ ระบบจะรวมลง hidden input เดิม</li>
                            <li><i></i>เมื่อ OTP ถูกต้อง ฝั่ง user จะเห็น popup ยืนยันสิทธิ์หลัง polling</li>
                        </ul>
                    </div>

                    <a href="/dashboard" class="verifyotp-back-link">กลับหน้ากิจกรรมที่สร้าง</a>
                </aside>

                <section class="verifyotp-console" aria-label="ฟอร์มตรวจ OTP">
                    <div class="verifyotp-console__head">
                        <div>
                            <span>Verify OTP</span>
                            <p>วางรหัสหรือกรอกทีละช่องได้</p>
                        </div>
                        <b><?= $success ? 'VERIFIED' : 'WAITING' ?></b>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="verifyotp-alert verifyotp-alert--error">
                            <ul class="compact-list">
                                <?php foreach ($errors as $e): ?>
                                    <li><?= $escape((string)$e) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="verifyotp-success-card">
                            <div class="verifyotp-success-mark" aria-hidden="true">✓</div>
                            <div>
                                <strong>OTP ถูกต้อง</strong>
                                <p>ผู้เข้าร่วม: <span><?= $escape((string)($success['name'] ?? '-')) ?></span></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="post" class="verifyotp-form" autocomplete="off">
                        <input type="hidden" name="event_id" value="<?= (int)$event_id ?>">
                        <input type="hidden" name="otp_code" id="otp_code" value="">

                        <div class="verifyotp-boxes" aria-label="กรอกรหัส OTP 6 หลัก">
                            <?php for ($i = 0; $i < 6; $i++): ?>
                                <input
                                    inputmode="numeric"
                                    pattern="[0-9]*"
                                    maxlength="1"
                                    autocomplete="one-time-code"
                                    enterkeyhint="done"
                                    autocapitalize="off"
                                    aria-label="OTP digit <?= $i + 1 ?>"
                                    class="otp-box verifyotp-box"
                                />
                            <?php endfor; ?>
                        </div>

                        <div class="verifyotp-tip"><span></span> Tip: วางรหัส 6 หลักได้ทันที</div>

                        <button type="submit" class="verifyotp-submit">
                            ตรวจ OTP
                        </button>

                        <div class="verifyotp-footnote">ตรวจได้เฉพาะเจ้าของกิจกรรมเท่านั้น</div>
                    </form>
                </section>
            </div>
        </section>
    </main>

    <script>
        (() => {
            'use strict';

            const boxes = Array.from(document.querySelectorAll('.otp-box'));
            const hidden = document.getElementById('otp_code');
            const form = document.querySelector('.verifyotp-form');

            function syncHidden() {
                if (!hidden) return '';
                hidden.value = boxes.map((box) => (box.value || '')).join('').slice(0, 6);
                return hidden.value;
            }

            function focusIndex(index) {
                if (!boxes.length) return;
                const target = boxes[Math.max(0, Math.min(index, boxes.length - 1))];
                target.focus({ preventScroll: true });
                target.select();
            }

            if ('requestIdleCallback' in window) {
                requestIdleCallback(() => focusIndex(0), { timeout: 500 });
            } else {
                window.setTimeout(() => focusIndex(0), 120);
            }

            boxes.forEach((box, index) => {
                box.addEventListener('input', () => {
                    box.value = (box.value || '').replace(/\D/g, '').slice(0, 1);
                    syncHidden();
                    if (box.value && index < boxes.length - 1) focusIndex(index + 1);
                });

                box.addEventListener('keydown', (event) => {
                    const key = event.key;

                    if (key === 'Backspace') {
                        if (box.value) {
                            box.value = '';
                            syncHidden();
                            event.preventDefault();
                            return;
                        }

                        if (index > 0) {
                            focusIndex(index - 1);
                            boxes[index - 1].value = '';
                            syncHidden();
                            event.preventDefault();
                        }
                        return;
                    }

                    if (key === 'ArrowLeft' || key === 'ArrowRight') {
                        const nextIndex = key === 'ArrowLeft' ? index - 1 : index + 1;
                        if (nextIndex >= 0 && nextIndex < boxes.length) focusIndex(nextIndex);
                        event.preventDefault();
                        return;
                    }

                    if (key.length === 1 && !/^\d$/.test(key)) event.preventDefault();
                });

                box.addEventListener('paste', (event) => {
                    const text = (event.clipboardData || window.clipboardData)?.getData('text') || '';
                    const digits = text.replace(/\D/g, '').slice(0, 6);
                    if (!digits) return;

                    event.preventDefault();
                    boxes.forEach((target, targetIndex) => {
                        target.value = digits[targetIndex] || '';
                    });
                    syncHidden();
                    focusIndex(Math.max(0, Math.min(digits.length, boxes.length) - 1));
                });
            });

            form?.addEventListener('submit', (event) => {
                const value = syncHidden();
                if (!/^\d{6}$/.test(value)) {
                    event.preventDefault();
                    focusIndex(value.length);
                }
            });
        })();
    </script>
</body>
</html>
