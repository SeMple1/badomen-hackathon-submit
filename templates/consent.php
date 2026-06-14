<section class="consent-popup consent-compact" id="consentPopup" aria-live="polite" hidden>
    <div class="consent-card">
        <div class="consent-compact-row">
            <div class="consent-compact-text">
                <div class="consent-mini-icon">
                    <i class="fa-solid fa-cookie-bite"></i>
                </div>

                <div>
                    <h2>เราใช้คุกกี้และข้อมูลเซสชัน</h2>
                    <p>
                        เพื่อให้ระบบเข้าสู่ระบบ สมัครกิจกรรม ตั๋ว/OTP และการตั้งค่าทำงานได้ถูกต้อง
                    </p>
                </div>
            </div>

            <div class="consent-compact-actions">
                <button class="consent-btn consent-btn-ghost" type="button" id="consentReject">
                    ปฏิเสธ
                </button>
                
                <button class="consent-btn consent-btn-primary" type="button" id="consentAccept">
                    ยอมรับทั้งหมด
                </button>
                
                <button 
                    class="consent-btn consent-btn-soft consent-icon-btn" 
                    type="button" 
                    id="consentCustomize"
                    aria-label="ตั้งค่าเอง"
                    title="ตั้งค่าเอง"
                >
                    <i class="fa-solid fa-gear"></i>
                </button>
            </div>
        </div>

        <div class="consent-expanded" id="consentExpanded" hidden>
            <div class="consent-expanded-head">
                <div>
                    <h3>ตั้งค่าความเป็นส่วนตัว</h3>
                    <p>
                        เลือกได้ว่าจะอนุญาตให้ Badomen ใช้ข้อมูลประเภทใดบ้าง
                        โดยข้อมูลที่จำเป็นต่อระบบจะเปิดใช้งานเสมอ
                    </p>
                </div>

                <button class="consent-close" type="button" id="consentCollapse" aria-label="ย่อหน้าต่างตั้งค่า">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <button class="consent-policy-toggle" type="button" id="consentPolicyToggle">
                อ่านนโยบายฉบับย่อ
                <i class="fa-solid fa-chevron-down"></i>
            </button>

            <div class="consent-policy" id="consentPolicy" hidden>
                <h3>นโยบายการใช้งานข้อมูลฉบับย่อ</h3>

                <p>
                    Badomen อาจจัดเก็บคุกกี้ ข้อมูลเซสชัน และข้อมูลการใช้งานบางส่วน เช่น
                    สถานะการเข้าสู่ระบบ การตั้งค่าภาษา การค้นหา การเปิดดูหน้าเว็บ
                    และการโต้ตอบกับกิจกรรม เพื่อให้บริการทำงานได้ต่อเนื่อง ปลอดภัย
                    และปรับปรุงคุณภาพของระบบ
                </p>

                <p>
                    ข้อมูลที่จำเป็นต่อระบบจะใช้เพื่อการเข้าสู่ระบบ การยืนยันตัวตน
                    การสมัครกิจกรรม การจัดการตั๋ว/OTP และการป้องกันการใช้งานที่ผิดปกติ
                </p>

                <p>
                    คุณสามารถเปิดหรือปิดหมวดที่ไม่จำเป็น เช่น การวิเคราะห์การใช้งาน
                    และการตลาดได้ตลอดเวลา การปิดหมวดเหล่านี้ไม่กระทบต่อการใช้งานหลักของเว็บไซต์
                </p>

                <p>
                    Badomen จะไม่ขายข้อมูลส่วนบุคคลของผู้ใช้ให้บุคคลภายนอก
                    และจะใช้ข้อมูลตามวัตถุประสงค์ที่แจ้งไว้เท่านั้น
                </p>
            </div>

            <div class="consent-settings">
                <div class="consent-option">
                    <div>
                        <strong>จำเป็นต่อระบบ</strong>
                        <p>ใช้สำหรับ session, login, security, CSRF, OTP และการสมัครกิจกรรม</p>
                    </div>

                    <label class="consent-switch is-disabled">
                        <input type="checkbox" checked disabled>
                        <span></span>
                    </label>
                </div>

                <div class="consent-option">
                    <div>
                        <strong>ตั้งค่าประสบการณ์ใช้งาน</strong>
                        <p>จดจำภาษา ธีม การตั้งค่า UI และตัวเลือกที่คุณเคยเลือก</p>
                    </div>

                    <label class="consent-switch">
                        <input type="checkbox" id="consentPreferences">
                        <span></span>
                    </label>
                </div>

                <div class="consent-option">
                    <div>
                        <strong>วิเคราะห์การใช้งาน</strong>
                        <p>ช่วยดูภาพรวมการใช้งาน เช่น หน้าไหนถูกใช้บ่อย หรือ flow ไหนติดปัญหา</p>
                    </div>

                    <label class="consent-switch">
                        <input type="checkbox" id="consentAnalytics">
                        <span></span>
                    </label>
                </div>

                <div class="consent-option">
                    <div>
                        <strong>การตลาดและการแนะนำกิจกรรม</strong>
                        <p>ใช้เพื่อแนะนำกิจกรรม โปรโมชัน หรือเนื้อหาที่เกี่ยวข้องกับความสนใจของคุณ</p>
                    </div>

                    <label class="consent-switch">
                        <input type="checkbox" id="consentMarketing">
                        <span></span>
                    </label>
                </div>
            </div>

            <div class="consent-expanded-actions">
                <button class="consent-btn consent-btn-ghost" type="button" id="consentRejectExpanded">
                    ปฏิเสธที่ไม่จำเป็น
                </button>

                <button class="consent-btn consent-btn-primary" type="button" id="consentSave">
                    บันทึกการตั้งค่า
                </button>
            </div>
        </div>
    </div>
</section>

<button class="consent-manage-btn" id="consentManageBtn" type="button" hidden>
    <i class="fa-solid fa-cookie-bite"></i>
    ตั้งค่าคุกกี้
</button>

<script>
(() => {
    const STORAGE_KEY = 'badomen_consent_v1';

    const popup = document.querySelector('#consentPopup');
    const expanded = document.querySelector('#consentExpanded');
    const manageBtn = document.querySelector('#consentManageBtn');

    const acceptBtn = document.querySelector('#consentAccept');
    const rejectBtn = document.querySelector('#consentReject');
    const customizeBtn = document.querySelector('#consentCustomize');

    const rejectExpandedBtn = document.querySelector('#consentRejectExpanded');
    const saveBtn = document.querySelector('#consentSave');
    const collapseBtn = document.querySelector('#consentCollapse');

    const policyToggle = document.querySelector('#consentPolicyToggle');
    const policy = document.querySelector('#consentPolicy');

    const preferencesInput = document.querySelector('#consentPreferences');
    const analyticsInput = document.querySelector('#consentAnalytics');
    const marketingInput = document.querySelector('#consentMarketing');

    if (!popup) return;

    const defaultConsent = {
        necessary: true,
        preferences: false,
        analytics: false,
        marketing: false,
        savedAt: null,
        version: 1
    };

    function getConsent() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            return raw ? JSON.parse(raw) : null;
        } catch (error) {
            return null;
        }
    }

    function saveConsent(consent) {
        const nextConsent = {
            ...defaultConsent,
            ...consent,
            necessary: true,
            savedAt: new Date().toISOString()
        };

        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(nextConsent));
        } catch (_) {}
        window.BadomenConsent = nextConsent;

        applyConsent(nextConsent);
        hidePopup();
        showManageButton();
    }

    function applyConsent(consent) {
        if (consent.preferences) {
            document.dispatchEvent(new CustomEvent('badomen:preferences-consent', { detail: consent }));
        }

        if (consent.analytics) {
            document.dispatchEvent(new CustomEvent('badomen:analytics-consent', { detail: consent }));
        }

        if (consent.marketing) {
            document.dispatchEvent(new CustomEvent('badomen:marketing-consent', { detail: consent }));
        }
    }

    function showPopup() {
        popup.hidden = false;
        popup.classList.remove('is-expanded');
        expanded.hidden = true;
    }

    function hidePopup() {
        popup.hidden = true;
        popup.classList.remove('is-expanded');
        expanded.hidden = true;
    }

    function showManageButton() {
        if (manageBtn) manageBtn.hidden = false;
    }

    function hideManageButton() {
        if (manageBtn) manageBtn.hidden = true;
    }

    function openSettings() {
        const existing = getConsent();

        if (existing) {
            preferencesInput.checked = Boolean(existing.preferences);
            analyticsInput.checked = Boolean(existing.analytics);
            marketingInput.checked = Boolean(existing.marketing);
        }

        popup.hidden = false;
        popup.classList.add('is-expanded');
        expanded.hidden = false;
    }

    function collectSettings() {
        return {
            necessary: true,
            preferences: Boolean(preferencesInput.checked),
            analytics: Boolean(analyticsInput.checked),
            marketing: Boolean(marketingInput.checked)
        };
    }

    acceptBtn?.addEventListener('click', () => {
        saveConsent({
            necessary: true,
            preferences: true,
            analytics: true,
            marketing: true
        });
    });

    rejectBtn?.addEventListener('click', () => {
        saveConsent({
            necessary: true,
            preferences: false,
            analytics: false,
            marketing: false
        });
    });

    rejectExpandedBtn?.addEventListener('click', () => {
        saveConsent({
            necessary: true,
            preferences: false,
            analytics: false,
            marketing: false
        });
    });

    customizeBtn?.addEventListener('click', openSettings);

    saveBtn?.addEventListener('click', () => {
        saveConsent(collectSettings());
    });

    collapseBtn?.addEventListener('click', () => {
        popup.classList.remove('is-expanded');
        expanded.hidden = true;
    });

    policyToggle?.addEventListener('click', () => {
        const shouldOpen = policy.hidden;
        policy.hidden = !shouldOpen;
        policyToggle.classList.toggle('is-open', shouldOpen);
    });

    manageBtn?.addEventListener('click', () => {
        hideManageButton();
        openSettings();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !popup.hidden) {
            if (!expanded.hidden) {
                popup.classList.remove('is-expanded');
                expanded.hidden = true;
            } else {
                hidePopup();
                showManageButton();
            }
        }
    });

    const consent = getConsent();

    if (consent) {
        window.BadomenConsent = consent;
        applyConsent(consent);
        showManageButton();
    } else {
        showPopup();
    }
})();
</script>
