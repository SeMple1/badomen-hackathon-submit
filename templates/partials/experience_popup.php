<?php
$experienceCsrf = function_exists('csrfToken') ? csrfToken() : '';
?>
<link rel="stylesheet" href="/style/experience-popup.css?v=3">
<div class="experience-popup" id="experiencePopup" aria-hidden="true" hidden>
    <div class="experience-popup__backdrop" data-experience-close></div>
    <section class="experience-popup__panel" role="dialog" aria-modal="true" aria-labelledby="experienceTitle">
        <button type="button" class="experience-popup__close" data-experience-close aria-label="ปิด">×</button>
        <span class="experience-popup__eyebrow" id="experienceEyebrow">YOUR EXPERIENCE</span>
        <h2 id="experienceTitle">ประสบการณ์ของคุณเป็นอย่างไร?</h2>
        <p id="experienceDescription">ให้คะแนนเพื่อช่วยเราพัฒนาระบบให้ดีขึ้น</p>
        <div class="experience-stars" role="radiogroup" aria-label="คะแนน 1 ถึง 5 ดาว">
            <?php for ($star = 1; $star <= 5; $star++): ?>
                <button type="button" data-rating="<?= $star ?>" aria-label="<?= $star ?> ดาว">★</button>
            <?php endfor; ?>
        </div>
        <textarea id="experienceComment" maxlength="1200" placeholder="เล่ารายละเอียดเพิ่มเติม (ไม่บังคับ)"></textarea>
        <p class="experience-popup__message" id="experienceMessage" aria-live="polite"></p>
        <div class="experience-popup__actions">
            <button type="button" class="experience-button experience-button--ghost" data-experience-close>ไว้ทีหลัง</button>
            <button type="button" class="experience-button experience-button--primary" id="experienceSubmit" disabled>ส่งความคิดเห็น</button>
        </div>
    </section>
</div>
<script>
(() => {
    const popup = document.getElementById('experiencePopup');
    if (!popup) return;
    const title = document.getElementById('experienceTitle');
    const eyebrow = document.getElementById('experienceEyebrow');
    const description = document.getElementById('experienceDescription');
    const comment = document.getElementById('experienceComment');
    const message = document.getElementById('experienceMessage');
    const submit = document.getElementById('experienceSubmit');
    const stars = Array.from(popup.querySelectorAll('[data-rating]'));
    const DISMISSED_KEY = 'badomen_feedback_dismissed_v1';
    const AUTO_SUPPRESS_UNTIL_KEY = 'badomen_feedback_auto_suppress_until_v1';
    const DISMISS_LIMIT = 80;
    const AUTO_SUPPRESS_MS = 24 * 60 * 60 * 1000;
    const dismissedMemory = new Set();
    let current = null;
    let rating = 0;
    let openTimer = null;

    const copy = {
        payment: ['PAYMENT COMPLETE', 'การชำระเงินเป็นอย่างไรบ้าง?', 'ให้คะแนนประสบการณ์ซื้อตั๋วครั้งนี้'],
        refund: ['REFUND COMPLETE', 'ประสบการณ์คืนเงินเป็นอย่างไร?', 'ความคิดเห็นของคุณช่วยให้เราปรับปรุงขั้นตอน refund'],
        attendance: ['EVENT REVIEW', 'กิจกรรมนี้เป็นอย่างไรบ้าง?', 'รีวิวนี้จะแสดงเพื่อช่วยผู้เข้าร่วมคนอื่นตัดสินใจ'],
        app: ['APP FEEDBACK', 'ประสบการณ์ใช้งานแอปเป็นอย่างไร?', 'ให้คะแนนเพื่อช่วยเราพัฒนา Badomen']
    };

    function paintStars() {
        stars.forEach((star) => star.classList.toggle('is-active', Number(star.dataset.rating) <= rating));
        submit.disabled = rating < 1;
    }

    function feedbackKey(item) {
        if (!item) return 'app:0:0:0';
        return [
            item.feedback_type || 'app',
            item.registration_id || 0,
            item.refund_id || 0,
            item.event_id || 0
        ].join(':');
    }

    function readDismissed() {
        const rawValues = [];
        try { rawValues.push(localStorage.getItem(DISMISSED_KEY)); } catch (_) {}
        try { rawValues.push(sessionStorage.getItem(DISMISSED_KEY)); } catch (_) {}
        const list = Array.from(dismissedMemory);
        rawValues.filter(Boolean).forEach((raw) => {
            try {
                const parsed = JSON.parse(raw);
                if (Array.isArray(parsed)) list.push(...parsed.filter(Boolean));
            } catch (_) {}
        });
        return Array.from(new Set(list));
    }

    function isAutoSuppressed() {
        try {
            return Number(sessionStorage.getItem(AUTO_SUPPRESS_UNTIL_KEY) || 0) > Date.now();
        } catch (_) {
            return false;
        }
    }

    function hasDismissed(item) {
        const key = feedbackKey(item);
        return dismissedMemory.has(key) || readDismissed().includes(key);
    }

    function rememberDismissed(item = current) {
        const key = feedbackKey(item);
        dismissedMemory.add(key);
        const list = readDismissed().filter((entry) => entry !== key);
        list.unshift(key);
        const next = JSON.stringify(list.slice(0, DISMISS_LIMIT));
        try {
            localStorage.setItem(DISMISSED_KEY, next);
        } catch (_) {
            try { sessionStorage.setItem(DISMISSED_KEY, next); } catch (_) {}
        }
    }

    function suppressAutoOpen() {
        try {
            sessionStorage.setItem(AUTO_SUPPRESS_UNTIL_KEY, String(Date.now() + AUTO_SUPPRESS_MS));
        } catch (_) {}
    }

    function queueOpen(item, delay = 450) {
        if (!item || hasDismissed(item) || isAutoSuppressed()) return;
        if (openTimer) window.clearTimeout(openTimer);
        openTimer = window.setTimeout(() => {
            openTimer = null;
            if (!hasDismissed(item) && !isAutoSuppressed()) open(item);
        }, delay);
    }

    function open(item) {
        current = item || { feedback_type: 'app', registration_id: 0, refund_id: 0 };
        if (!current.force_open && (hasDismissed(current) || isAutoSuppressed())) return;
        rating = 0;
        comment.value = '';
        message.textContent = '';
        const text = copy[current.feedback_type] || copy.app;
        eyebrow.textContent = text[0];
        title.textContent = current.event_title ? `${text[1]} · ${current.event_title}` : text[1];
        description.textContent = text[2];
        paintStars();
        popup.hidden = false;
        popup.classList.add('is-open');
        popup.setAttribute('aria-hidden', 'false');
        document.body.classList.add('experience-open');
    }

    function close() {
        if (openTimer) {
            window.clearTimeout(openTimer);
            openTimer = null;
        }
        if (current && !current.force_open) {
            rememberDismissed(current);
            suppressAutoOpen();
        }
        popup.classList.remove('is-open');
        popup.setAttribute('aria-hidden', 'true');
        popup.hidden = true;
        document.body.classList.remove('experience-open');
    }

    async function loadPending() {
        try {
            const response = await fetch('/feedback?action=pending', {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin'
            });
            const data = await response.json();
            if (data.ok && data.items?.length) {
                const nextItem = data.items.find((item) => !hasDismissed(item));
                queueOpen(nextItem, 450);
            }
        } catch (_) {}
    }

    stars.forEach((star) => star.addEventListener('click', () => {
        rating = Number(star.dataset.rating);
        paintStars();
    }));
    popup.querySelectorAll('[data-experience-close]').forEach((button) => button.addEventListener('click', close));
    submit.addEventListener('click', async () => {
        if (!current || rating < 1) return;
        submit.disabled = true;
        message.textContent = 'กำลังบันทึก...';
        const body = new URLSearchParams({
            _csrf: <?= json_encode($experienceCsrf) ?>,
            feedback_type: current.feedback_type || 'app',
            registration_id: String(current.registration_id || 0),
            refund_id: String(current.refund_id || 0),
            rating: String(rating),
            comment: comment.value.trim()
        });
        try {
            const response = await fetch('/feedback', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body
            });
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.message || 'บันทึกไม่สำเร็จ');
            rememberDismissed(current);
            message.textContent = data.message;
            window.setTimeout(close, 700);
        } catch (error) {
            message.textContent = error.message || 'บันทึกไม่สำเร็จ';
            submit.disabled = false;
        }
    });

    window.BadomenExperience = {
        open: (item) => open({ ...(item || {}), force_open: true }),
        close
    };
    const currentPath = window.location.pathname.replace(/\/+$/, '') || '/';
    const skipFeedbackAutoLoad = ['/dashboard', '/participants', '/verifyotp', '/event-intelligence'].some(
        (path) => currentPath === path || currentPath.startsWith(path + '/')
    );
    const redirectedPayment = sessionStorage.getItem('badomen_payment_feedback');
    if (redirectedPayment && window.location.pathname.replace(/\/+$/, '') === '/join_activity') {
        sessionStorage.removeItem('badomen_payment_feedback');
        try {
            const paymentFeedback = JSON.parse(redirectedPayment);
            queueOpen(paymentFeedback, 1800);
        } catch (_) {}
    } else if (!skipFeedbackAutoLoad && !sessionStorage.getItem('badomen_feedback_checked')) {
        sessionStorage.setItem('badomen_feedback_checked', '1');
        const idle = window.requestIdleCallback || ((callback) => window.setTimeout(callback, 900));
        idle(loadPending, { timeout: 2200 });
    }
})();
</script>
