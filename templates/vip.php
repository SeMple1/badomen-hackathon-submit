<?php
$user = $user ?? [];
$errors = is_array($errors ?? null) ? $errors : [];
$successes = is_array($successes ?? null) ? $successes : [];
$escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$rank = (string)($user['member_rank'] ?? 'member');
$isGold = $rank === 'gold' && (empty($user['vip_expires_at']) || strtotime((string)$user['vip_expires_at']) >= time());
$expiresAt = trim((string)($user['vip_expires_at'] ?? ''));
$expiresDisplay = '-';
if ($expiresAt !== '') {
    $ts = strtotime($expiresAt);
    if ($ts !== false) {
        $expiresDisplay = date('d/m/Y H:i', $ts);
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gold VIP | Badomen</title>
    <link rel="stylesheet" href="/style/app.css">
    <link rel="stylesheet" href="/style/event-ticket-modal.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet"></noscript>
    <style>
        body{margin:0;min-height:100vh;background:radial-gradient(circle at 12% 16%,rgba(245,158,11,.12),transparent 28%),linear-gradient(180deg,#fff,#fff8f1);font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,"Noto Sans Thai",sans-serif;color:#111827}.vip-page{width:min(980px,calc(100% - 40px));margin:0 auto;padding:54px 0 70px}.vip-card{overflow:hidden;border-radius:34px;background:#fff;border:1px solid rgba(245,158,11,.32);box-shadow:0 28px 76px rgba(146,64,14,.16)}.vip-hero{padding:42px;background:linear-gradient(135deg,#1a0c02,#432006 50%,#f59e0b 150%);color:#fff}.vip-badge{width:fit-content;display:inline-flex;align-items:center;gap:8px;padding:9px 14px;border-radius:999px;background:rgba(245,158,11,.18);border:1px solid rgba(251,191,36,.42);font-size:13px;font-weight:950}.vip-badge i{color:#fbbf24}.vip-hero h1{margin:22px 0 10px;font-size:clamp(34px,5vw,58px);line-height:1.05;letter-spacing:-.055em}.vip-hero p{max-width:670px;margin:0;color:rgba(255,255,255,.76);line-height:1.75;font-weight:700}.vip-body{padding:32px}.vip-status{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:22px}.vip-status div{padding:18px;border-radius:22px;background:#fff7ed;border:1px solid rgba(245,158,11,.18)}.vip-status span{display:block;color:#6b7280;font-size:12px;font-weight:850}.vip-status strong{display:block;margin-top:6px;font-size:18px;font-weight:950}.vip-benefits{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.vip-benefit{padding:18px;border-radius:22px;border:1px solid rgba(17,24,39,.10);background:#fff}.vip-benefit i{width:42px;height:42px;display:inline-flex;align-items:center;justify-content:center;border-radius:15px;color:#92400e;background:rgba(245,158,11,.15);font-size:22px}.vip-benefit strong{display:block;margin-top:12px;font-size:16px}.vip-benefit span{display:block;margin-top:6px;color:#6b7280;line-height:1.6;font-size:13px}.vip-actions{display:flex;gap:12px;margin-top:26px;flex-wrap:wrap}.vip-btn{height:50px;padding:0 20px;border:0;border-radius:16px;display:inline-flex;align-items:center;justify-content:center;gap:9px;text-decoration:none;font:inherit;font-weight:950;cursor:pointer}.vip-btn-primary{color:#1a0c02;background:linear-gradient(90deg,#fbbf24,#f59e0b);box-shadow:0 14px 30px rgba(245,158,11,.28)}.vip-btn-dark{color:#fff;background:#111827}.vip-alert{margin-bottom:16px;padding:14px 16px;border-radius:18px;font-weight:800}.vip-alert-error{color:#b42318;background:#fff1f1;border:1px solid rgba(239,68,68,.18)}.vip-alert-success{color:#067647;background:#ecfdf3;border:1px solid #b7f0cf}.vip-payment-modal{--tm-line:rgba(148,163,184,.28);--tm-ink:#0f172a;position:fixed;inset:0;z-index:12000;display:grid;place-items:center;padding:18px;background:rgba(15,23,42,.62);backdrop-filter:blur(10px);font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,"Noto Sans Thai",sans-serif}.vip-payment-modal[hidden]{display:none!important}.vip-payment-modal *{box-sizing:border-box}.vip-payment-shell{position:relative;width:min(760px,100%);max-height:calc(100vh - 36px);overflow:auto;border-radius:28px;background:#fff;box-shadow:0 30px 90px rgba(15,23,42,.30)}.vip-payment-shell .ticket-payment-panel{display:block;position:relative;margin:0;padding:24px;border:0;border-radius:0;background:radial-gradient(circle at 0 0,rgba(251,191,36,.20),transparent 18rem),#fff;box-shadow:none}.vip-payment-close{position:absolute;top:12px;right:12px;z-index:2;width:42px;height:42px;border:0;border-radius:999px;background:#111827;color:#fff;font-size:24px;cursor:pointer}.vip-payment-modal .ticket-payment-header{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:18px;padding-right:46px}.vip-payment-modal .payment-kicker{margin:0 0 6px;color:#92400e;font-size:12px;font-weight:950;text-transform:uppercase;letter-spacing:.08em}.vip-payment-modal .ticket-payment-header h2{margin:0;color:#111827;font-size:clamp(24px,4vw,34px);line-height:1.12;font-weight:950}.vip-payment-modal .ticket-payment-header span{display:block;margin-top:8px;color:#64748b;font-size:13px;font-weight:800;line-height:1.6}.vip-payment-modal .payment-summary-box{grid-template-columns:repeat(3,minmax(0,1fr))}.vip-payment-modal .payment-summary-box small{display:block;color:#64748b;font-size:11px;font-weight:900}.vip-payment-modal .payment-method-card img{display:block;width:64px;height:42px;object-fit:contain;padding:6px;border-radius:10px;background:#fff;box-shadow:inset 0 0 0 1px rgba(148,163,184,.22)}.vip-payment-modal .payment-mock-detail span{display:block;margin-top:4px;color:#64748b;line-height:1.6;font-size:13px;font-weight:800}.vip-payment-modal .payment-actions-row{display:grid;gap:10px;margin-top:14px}.vip-payment-modal .payment-confirm-button{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:50px;border:0;border-radius:16px;color:#fff;background:linear-gradient(135deg,#f59e0b,#92400e);font:inherit;font-weight:950;cursor:pointer;box-shadow:0 16px 30px rgba(146,64,14,.24)}.vip-payment-modal .payment-confirm-button:disabled{cursor:not-allowed;opacity:.72}.vip-payment-modal .payment-expire-note{color:#64748b;font-size:12px;font-weight:850;text-align:center}.vip-payment-modal .payment-expire-note strong{color:#111827}@media(max-width:720px){.vip-status,.vip-benefits{grid-template-columns:1fr}.vip-hero,.vip-body{padding:24px}.vip-btn{width:100%}}@media(max-width:680px){.vip-payment-modal .ticket-payment-header{display:grid;padding-right:42px}.vip-payment-modal .payment-summary-box,.vip-payment-modal .payment-method-grid{grid-template-columns:1fr}.vip-payment-shell{border-radius:22px}.vip-payment-shell .ticket-payment-panel{padding:20px}}
    </style>
</head>
<body>
<?php require __DIR__ . '/header.php'; ?>
<main class="vip-page">
    <section class="vip-card">
        <div class="vip-hero">
            <div class="vip-badge"><i class='bx bxs-crown'></i><span>Gold VIP Membership</span></div>
            <h1>อัปเกรดบัญชีเป็น Gold VIP</h1>
            <p>ระบบนี้เป็น mock-up สำหรับงาน hackathon: กดสมัครแล้วระบบจะบันทึก order, อัปเดตยศเป็น gold และขยายอายุสมาชิก 30 วันทันที</p>
        </div>
        <div class="vip-body">
            <?php if (!empty($errors)): ?>
                <div class="vip-alert vip-alert-error"><?= $escape(implode(' ', $errors)) ?></div>
            <?php endif; ?>
            <?php if (!empty($successes)): ?>
                <div class="vip-alert vip-alert-success"><?= $escape(implode(' ', $successes)) ?></div>
            <?php endif; ?>

            <div class="vip-status">
                <div><span>ผู้ใช้</span><strong><?= $escape((string)($user['name'] ?? '-')) ?></strong></div>
                <div><span>ยศปัจจุบัน</span><strong><?= $isGold ? 'Gold VIP' : 'Member' ?></strong></div>
                <div><span>หมดอายุ</span><strong><?= $escape($isGold ? $expiresDisplay : '-') ?></strong></div>
            </div>

            <div class="vip-benefits">
                <div class="vip-benefit"><i class='bx bxs-crown'></i><strong>Gold border บนโปรไฟล์</strong><span>หน้าโปรไฟล์จะแสดงกรอบและ badge สีทองสำหรับผู้ใช้ Gold</span></div>
                <div class="vip-benefit"><i class='bx bx-image'></i><strong>โปรไฟล์ดูเด่นขึ้น</strong><span>รองรับรูป avatar จริงและแสดงผลร่วมกับยศ Gold</span></div>
                <div class="vip-benefit"><i class='bx bx-star'></i><strong>เตรียมต่อยอดสิทธิพิเศษ</strong><span>สามารถนำ member_rank ไปเช็คสิทธิพิเศษในหน้าอื่นต่อได้</span></div>
                <div class="vip-benefit"><i class='bx bx-receipt'></i><strong>มีประวัติ order</strong><span>ทุกครั้งที่สมัครจะถูกเก็บในตาราง vip_memberships</span></div>
            </div>

            <div class="vip-actions">
                <form method="POST" action="/vip" data-vip-payment-form>
                    <input type="hidden" name="_csrf" value="<?= $escape(csrfToken()) ?>">
                    <input type="hidden" name="plan" value="gold_monthly">
                    <button class="vip-btn vip-btn-primary" type="submit"><i class='bx bxs-crown'></i><span><?= $isGold ? 'ต่ออายุ Gold VIP 99 บาท' : 'สมัคร Gold VIP 99 บาท / 30 วัน' ?></span></button>
                </form>
                <a class="vip-btn vip-btn-dark" href="/profile"><i class='bx bx-left-arrow-alt'></i><span>กลับโปรไฟล์</span></a>
            </div>
        </div>
    </section>
</main>
<div class="vip-payment-modal" id="vipPaymentModal" hidden>
    <div class="vip-payment-shell" role="dialog" aria-modal="true" aria-labelledby="vipPaymentTitle">
        <button class="vip-payment-close" type="button" data-vip-payment-close aria-label="ปิดหน้าชำระเงิน">&times;</button>
        <section class="ticket-payment-panel" id="vipPaymentPanel">
            <div class="ticket-payment-header">
                <div>
                    <p class="payment-kicker">Gold VIP Payment</p>
                    <h2 id="vipPaymentTitle">ชำระเงินสมาชิก Gold VIP</h2>
                    <span>เลือกช่องทางชำระเงินเหมือนระบบบัตรกิจกรรม ระบบนี้เป็น mock-up สำหรับ Hackathon</span>
                </div>
                <div class="payment-countdown" id="vipPaymentCountdown">10:00</div>
            </div>
            <div class="payment-summary-box">
                <div>
                    <small>แพ็กเกจ</small>
                    <strong>Gold VIP 30 วัน</strong>
                </div>
                <div>
                    <small>ยอดชำระ</small>
                    <strong id="vipPaymentAmount">99 บาท</strong>
                </div>
                <div>
                    <small>เลขอ้างอิง</small>
                    <strong id="vipPaymentRef">-</strong>
                </div>
            </div>
            <div class="payment-method-grid">
                <button class="payment-method-card is-active" type="button" data-vip-payment-method="promptpay">
                    <img src="/assets/promptpay.png" alt="PromptPay">
                    <span>PromptPay</span>
                </button>
                <button class="payment-method-card" type="button" data-vip-payment-method="visa">
                    <img src="/assets/visa.png" alt="Visa">
                    <span>Visa</span>
                </button>
                <button class="payment-method-card" type="button" data-vip-payment-method="mastercard">
                    <img src="/assets/mastercard.png" alt="Mastercard">
                    <span>Mastercard</span>
                </button>
                <button class="payment-method-card" type="button" data-vip-payment-method="truemoney">
                    <img src="/assets/truemoney.png" alt="TrueMoney Wallet">
                    <span>TrueMoney</span>
                </button>
            </div>
            <div class="payment-mock-detail" id="vipPaymentMockDetail">
                <img class="payment-detail-asset" src="/assets/promptpay.png" alt="">
                <div>
                    <strong>PromptPay mock payment</strong>
                    <span>กดปุ่มยืนยันเพื่อจำลองการชำระเงินและเปิดสิทธิ์ Gold VIP ทันที</span>
                </div>
            </div>
            <div class="payment-actions-row">
                <button class="payment-confirm-button" type="button" id="vipMockPayButton">
                    <i class='bx bx-check-circle'></i>
                    <span>ยืนยันการชำระเงิน</span>
                </button>
                <span class="payment-expire-note">หมดเวลาชำระ <strong id="vipPaymentExpiresAt">-</strong></span>
            </div>
        </section>
    </div>
</div>
<script>
(() => {
    const form = document.querySelector('[data-vip-payment-form]');
    const modal = document.getElementById('vipPaymentModal');
    if (!form || !modal) {
        return;
    }

    const closeButtons = modal.querySelectorAll('[data-vip-payment-close]');
    const methodButtons = modal.querySelectorAll('[data-vip-payment-method]');
    const countdownEl = document.getElementById('vipPaymentCountdown');
    const refEl = document.getElementById('vipPaymentRef');
    const amountEl = document.getElementById('vipPaymentAmount');
    const expiresEl = document.getElementById('vipPaymentExpiresAt');
    const detailEl = document.getElementById('vipPaymentMockDetail');
    const payButton = document.getElementById('vipMockPayButton');
    const submitButton = form.querySelector('button[type="submit"]');
    const originalSubmitHtml = submitButton ? submitButton.innerHTML : '';
    const originalPayHtml = payButton ? payButton.innerHTML : '';
    const paymentAssets = {
        promptpay: '/assets/promptpay.png',
        visa: '/assets/visa.png',
        mastercard: '/assets/mastercard.png',
        truemoney: '/assets/truemoney.png',
    };
    const methodLabels = {
        promptpay: 'PromptPay mock payment',
        visa: 'Visa mock card',
        mastercard: 'Mastercard mock card',
        truemoney: 'TrueMoney Wallet mock payment',
    };
    let activeMethod = 'promptpay';
    let activePaymentRef = '';
    let countdownTimer = null;
    let paymentExpiresAt = null;

    const postVipPayment = async (action, extra = {}) => {
        const payload = new URLSearchParams(new FormData(form));
        payload.set('vip_action', action);
        Object.entries(extra).forEach(([key, value]) => payload.set(key, value));
        const response = await fetch('/vip', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: payload.toString(),
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.ok) {
            throw new Error(data.message || 'ไม่สามารถทำรายการได้ในตอนนี้');
        }
        return data;
    };

    const formatAmount = (amount, currency) => {
        const number = Number(amount || 0);
        return `${number.toLocaleString('th-TH')} ${currency === 'THB' ? 'บาท' : currency}`;
    };

    const formatExpire = (value) => {
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return '-';
        }
        return date.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });
    };

    const startCountdown = () => {
        window.clearInterval(countdownTimer);
        const tick = () => {
            if (!paymentExpiresAt || !countdownEl) {
                return;
            }
            const remaining = Math.max(0, paymentExpiresAt.getTime() - Date.now());
            const totalSeconds = Math.floor(remaining / 1000);
            const minutes = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
            const seconds = String(totalSeconds % 60).padStart(2, '0');
            countdownEl.textContent = `${minutes}:${seconds}`;
            if (remaining <= 0) {
                window.clearInterval(countdownTimer);
                if (payButton) {
                    payButton.disabled = true;
                    payButton.querySelector('span').textContent = 'หมดเวลาชำระเงิน';
                }
            }
        };
        tick();
        countdownTimer = window.setInterval(tick, 1000);
    };

    const openModal = () => {
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
    };

    const closeModal = () => {
        modal.hidden = true;
        document.body.style.overflow = '';
        window.clearInterval(countdownTimer);
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.innerHTML = originalSubmitHtml;
        }
        if (payButton) {
            payButton.disabled = false;
            payButton.innerHTML = originalPayHtml;
        }
    };

    const selectMethod = (method) => {
        activeMethod = method;
        methodButtons.forEach((button) => {
            button.classList.toggle('is-active', button.dataset.vipPaymentMethod === method);
        });
        if (detailEl) {
            const image = detailEl.querySelector('img');
            const title = detailEl.querySelector('strong');
            const text = detailEl.querySelector('span');
            if (image) {
                image.src = paymentAssets[method] || paymentAssets.promptpay;
            }
            if (title) {
                title.textContent = methodLabels[method] || methodLabels.promptpay;
            }
            if (text) {
                text.textContent = method === 'promptpay'
                    ? 'สแกนหรือกดปุ่มยืนยันเพื่อจำลองการชำระเงินและเปิดสิทธิ์ Gold VIP'
                    : 'ระบบจำลองการจ่ายด้วยช่องทางนี้ ใช้ flow เดียวกับตอนซื้อบัตรกิจกรรม';
            }
        }
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = "<i class='bx bx-loader-alt bx-spin'></i><span>กำลังเตรียมชำระเงิน...</span>";
        }
        try {
            const data = await postVipPayment('begin_payment');
            activePaymentRef = data.payment_ref || '';
            paymentExpiresAt = new Date(data.payment_expires_at);
            if (refEl) {
                refEl.textContent = activePaymentRef || '-';
            }
            if (amountEl) {
                amountEl.textContent = formatAmount(data.amount, data.currency);
            }
            if (expiresEl) {
                expiresEl.textContent = formatExpire(data.payment_expires_at);
            }
            if (payButton) {
                payButton.disabled = false;
                payButton.innerHTML = originalPayHtml;
            }
            selectMethod(activeMethod);
            openModal();
            startCountdown();
        } catch (error) {
            form.submit();
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.innerHTML = originalSubmitHtml;
            }
        }
    });

    methodButtons.forEach((button) => {
        button.addEventListener('click', () => selectMethod(button.dataset.vipPaymentMethod || 'promptpay'));
    });

    if (payButton) {
        payButton.addEventListener('click', async () => {
            if (!activePaymentRef) {
                return;
            }
            payButton.disabled = true;
            payButton.innerHTML = "<i class='bx bx-loader-alt bx-spin'></i><span>กำลังยืนยัน...</span>";
            try {
                await postVipPayment('complete_payment', {
                    payment_ref: activePaymentRef,
                    payment_method: activeMethod,
                });
                window.clearInterval(countdownTimer);
                if (countdownEl) {
                    countdownEl.textContent = 'สำเร็จ';
                }
                payButton.innerHTML = "<i class='bx bx-check-circle'></i><span>เปิดสิทธิ์ Gold VIP แล้ว</span>";
                window.setTimeout(() => window.location.assign('/profile'), 900);
            } catch (error) {
                payButton.disabled = false;
                payButton.innerHTML = originalPayHtml;
                alert(error.message || 'ชำระเงินไม่สำเร็จ กรุณาลองใหม่');
            }
        });
    }

    closeButtons.forEach((button) => button.addEventListener('click', closeModal));
    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });
})();
</script>
</body>
</html>
