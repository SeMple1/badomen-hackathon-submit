<?php
$eventModalPayloads = $eventModalPayloads ?? [];
$placeholderImage = $placeholderImage ?? '';
$viewerIsVip = (bool)($viewerIsVip ?? false);
$vipDiscountPerTicket = (float)($vipDiscountPerTicket ?? (function_exists('badomenVipDiscountPerTicket') ? badomenVipDiscountPerTicket() : 59));
$escape = $escape ?? static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$modalConfig = [
    'csrf' => function_exists('csrfToken') ? csrfToken() : '',
    'favoriteEndpoint' => function_exists('appUrl') ? appUrl('/event-favorite') : '/event-favorite',
    'paymentEndpoint' => function_exists('appUrl') ? appUrl('/home_in') : '/home_in',
    'viewerIsVip' => $viewerIsVip,
    'vipDiscountPerTicket' => $vipDiscountPerTicket,
    'paymentAssets' => [
        'promptpay' => '/assets/promptpay.png',
        'visa' => '/assets/visa.png',
        'mastercard' => '/assets/mastercard.png',
        'truemoney' => '/assets/truemoney.png',
    ],
];
?>
<script id="eventModalData" type="application/json"><?= json_encode(
    $eventModalPayloads,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?></script>
<script id="eventModalConfig" type="application/json"><?= json_encode(
    $modalConfig,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<div id="eventModal" class="ticket-modal" aria-hidden="true">
    <div class="ticket-modal__backdrop" data-modal-close></div>

    <section class="ticket-modal__shell" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <header class="ticket-modal__topbar" aria-label="ขั้นตอนการจอง">
            <nav class="ticket-modal__steps">
                <span class="ticket-step ticket-step--status">
                    <b>สถานะ</b>
                    <strong id="modalStatus" class="modal-status status-badge--open">เปิดรับสมัคร</strong>
                </span>
                <span id="ticketStepSelect" class="ticket-step is-active"><i class="bx bx-chair"></i> เลือกบัตร</span>
                <span id="ticketStepReserve" class="ticket-step"><i class="bx bx-loader-alt"></i> จองที่นั่ง</span>
                <span id="ticketStepPayment" class="ticket-step"><i class="bx bx-wallet"></i> ชำระเงิน</span>
                <span id="ticketStepTicket" class="ticket-step"><i class="bx bx-badge-check"></i> รับบัตร</span>
            </nav>
        </header>

        <button type="button" class="ticket-modal__close" data-modal-close aria-label="ปิดหน้าต่าง">
            <i class="bx bx-x"></i>
        </button>
        <span id="modalEventCode" hidden></span>

        <div class="ticket-modal__body">
            <aside class="ticket-modal__event">
                <div class="ticket-cover">
                    <img id="modalMainImage" src="<?= $escape((string)$placeholderImage) ?>" alt="ภาพกิจกรรม" decoding="async" fetchpriority="low">
                    <span id="modalImageCounter">1/1</span>
                </div>
                <div id="modalThumbs" class="ticket-thumbs"></div>

                <div class="ticket-event-info">
                    <p id="modalCreator" class="ticket-organizer"></p>
                    <h2 id="modalTitle"></h2>
                    <div class="ticket-summary-grid">
                        <div><span>เริ่มกิจกรรม</span><strong id="modalEventStart">-</strong></div>
                        <div><span>สิ้นสุด</span><strong id="modalEventEnd">-</strong></div>
                        <div class="is-wide"><span>สถานที่</span><strong id="modalLocation">-</strong></div>
                        <div><span>เปิดรับ</span><strong id="modalRegStart">-</strong></div>
                        <div><span>ปิดรับ</span><strong id="modalRegEnd">-</strong></div>
                    </div>
                </div>

                <section class="ticket-panel ticket-panel--description">
                    <div class="ticket-panel__head">
                        <h3><i class="bx bx-detail"></i> รายละเอียดกิจกรรม</h3>
                    </div>
                    <div id="modalDescription" class="ticket-description"></div>
                </section>
                <section class="ticket-panel ticket-panel--map" id="modalMapPanel" hidden>
                    <div class="ticket-panel__head">
                        <h3><i class="bx bx-map-pin"></i> ตำแหน่งกิจกรรม</h3>
                    </div>
                    <div id="modalMiniMap" class="ticket-mini-map" aria-label="แผนที่ตำแหน่งกิจกรรม"></div>
                </section>

            </aside>

            <main class="ticket-modal__picker">
                <section class="ticket-panel ticket-panel--summary">
                    <div class="ticket-panel__head ticket-panel__head--large">
                        <div>
                            <h3><i class="bx bx-chair"></i> เลือกบัตร / ที่นั่ง</h3>
                            <p id="ticketModeText">รองรับสมัครทั่วไป เลือกโซน สุ่มที่นั่ง และเลือกที่นั่งเอง</p>
                        </div>
                        <button id="modalFavoriteBtn" type="button" class="ticket-save-button">
                            <i class="bx bx-heart"></i> บันทึก
                        </button>
                    </div>

                    <div class="ticket-capacity-card">
                        <div>
                            <span>อนุมัติแล้ว</span>
                            <strong id="modalCapacity">0/0 คน</strong>
                        </div>
                        <div class="ticket-capacity-track"><span id="modalCapacityBar"></span></div>
                        <div class="ticket-capacity-meta">
                            <span id="modalCapacityPercent">0%</span>
                            <span id="modalSeatLeft">-</span>
                        </div>
                    </div>

                    <div class="ticket-stats-row">
                        <div><strong id="modalApprovedCount">0</strong><span>อนุมัติ</span></div>
                        <div><strong id="modalPendingCount">0</strong><span>รอชำระ/อนุมัติ</span></div>
                        <div><strong id="modalCheckedInCount">0</strong><span>เช็กอิน</span></div>
                    </div>
                </section>

                <section class="ticket-panel" id="ticketZonePanel">
                    <div class="ticket-panel__head">
                        <h3><i class="bx bx-purchase-tag-alt"></i> โซนและราคา</h3>
                        <span>เลือกได้สูงสุด <b id="ticketLimitText">2</b> ที่นั่ง/คน</span>
                    </div>
                    <div id="ticketZoneList" class="ticket-zone-list"></div>
                </section>

                <section class="ticket-panel" id="ticketSeatPanel">
                    <div class="ticket-panel__head">
                        <h3><i class="bx bx-grid-alt"></i> ผังที่นั่ง</h3>
                        <span id="ticketSeatHint">สีฟ้าคือที่นั่งว่าง</span>
                    </div>
                    <div class="seat-legend" aria-label="คำอธิบายสีที่นั่ง">
                        <span><i class="seat-dot seat-dot--available"></i> ว่าง</span>
                        <span><i class="seat-dot seat-dot--selected"></i> เลือกแล้ว</span>
                        <span><i class="seat-dot seat-dot--reserved"></i> ถูกจอง/ชำระแล้ว</span>
                        <span><i class="seat-dot seat-dot--blocked"></i> ปิดใช้งาน</span>
                    </div>
                    <div class="seat-stage">SCREEN / STAGE</div>
                    <div id="ticketSeatMap" class="seat-map"></div>
                </section>

                <section class="ticket-panel" id="ticketRandomPanel" hidden>
                    <div class="ticket-panel__head">
                        <h3><i class="bx bx-shuffle"></i> สุ่มที่นั่งในโซน</h3>
                        <span>ระบบเลือกที่นั่งว่างให้หลังยืนยัน</span>
                    </div>
                    <div class="quantity-picker" id="quantityPicker"></div>
                    <p class="random-note">เหมาะกับงานที่ไม่ต้องให้ผู้ใช้เลือกตำแหน่งเอง แต่ยังคิดราคาตามโซนได้จริง</p>
                </section>

                <section class="ticket-panel ticket-checkout-card">
                    <div class="ticket-panel__head">
                        <h3><i class="bx bx-receipt"></i> สรุปการเลือก</h3>
                        <span>จองแล้วไปชำระเงินใน pop-up นี้</span>
                    </div>

                    <div id="modalMyRegistration" class="ticket-my-status"></div>
                    <div id="ticketSelectedList" class="ticket-selected-list"></div>

                    <div class="ticket-total-row">
                        <span>ยอดรวมโดยประมาณ</span>
                        <strong id="ticketTotalPrice">0 บาท</strong>
                    </div>
                    <div id="ticketVipSaving" class="ticket-vip-saving" hidden></div>

                    <form id="modalJoinForm" method="POST" action="/home_in" class="ticket-submit-form">
                        <input type="hidden" name="_csrf" value="<?= $escape((string)$modalConfig['csrf']) ?>">
                        <input type="hidden" name="ticket_action" value="reserve_ticket">
                        <input type="hidden" name="event_id" id="modalEventId" value="">
                        <input type="hidden" name="ticket_mode" id="ticketModeInput" value="general">
                        <input type="hidden" name="seat_selection_mode" id="ticketSelectionModeInput" value="manual">
                        <input type="hidden" name="zone_id" id="ticketZoneInput" value="">
                        <input type="hidden" name="quantity" id="ticketQuantityInput" value="1">
                        <input type="hidden" name="selected_seat_ids" id="ticketSeatIdsInput" value="">
                        <input type="hidden" name="return_query" id="modalReturnQuery" value="">
                        <input type="hidden" name="return_start_at" id="modalReturnStartAt" value="">
                        <input type="hidden" name="return_end_at" id="modalReturnEndAt" value="">
                        <input type="hidden" name="return_show_all" id="modalReturnShowAll" value="">
                        <button id="modalJoinBtn" type="submit" class="ticket-primary-button">
                            <i class="bx bx-lock-alt"></i> จองและชำระเงิน
                        </button>
                    </form>
                </section>

                <section id="ticketPaymentPanel" class="ticket-panel ticket-payment-panel" hidden>
                    <div class="ticket-panel__head ticket-panel__head--large">
                        <div>
                            <h3><i class="bx bx-wallet"></i> ชำระเงิน</h3>
                            <p>เลือกช่องทางชำระเงินและยืนยันรายการภายในเวลาที่กำหนด</p>
                        </div>
                        <span id="paymentCountdown" class="payment-countdown">10:00</span>
                    </div>

                    <div id="paymentReserveState" class="payment-reserve-state">
                        <i class="bx bx-loader-alt bx-spin"></i>
                        <div>
                            <strong>กำลังตรวจสอบและจองที่นั่ง</strong>
                            <span>ระบบกำลังอัปเดตฐานข้อมูลเพื่อกันที่นั่งให้บัญชีของคุณ</span>
                        </div>
                    </div>

                    <div class="payment-summary-box">
                        <div><span>เลขที่จอง</span><strong id="paymentRegistrationId">-</strong></div>
                        <div><span>ยอดชำระ</span><strong id="paymentAmount">0 บาท</strong></div>
                        <div><span>หมดเวลา</span><strong id="paymentExpiresAt">-</strong></div>
                    </div>

                    <div class="payment-method-grid" role="radiogroup" aria-label="ช่องทางชำระเงิน">
                        <button type="button" class="payment-method-card is-active" data-payment-method="promptpay">
                            <span class="payment-method-card__asset"><img src="<?= $escape((string)$modalConfig['paymentAssets']['promptpay']) ?>" alt="PromptPay" loading="lazy" decoding="async"><b>QR</b></span>
                            <strong>PromptPay</strong>
                            <span>สแกน QR PromptPay</span>
                        </button>
                        <button type="button" class="payment-method-card" data-payment-method="visa">
                            <span class="payment-method-card__asset"><img src="<?= $escape((string)$modalConfig['paymentAssets']['visa']) ?>" alt="Visa" loading="lazy" decoding="async"><b>VISA</b></span>
                            <strong>Visa Card</strong>
                            <span>บัตรเครดิตหรือเดบิต</span>
                        </button>
                        <button type="button" class="payment-method-card" data-payment-method="mastercard">
                            <span class="payment-method-card__asset"><img src="<?= $escape((string)$modalConfig['paymentAssets']['mastercard']) ?>" alt="Mastercard" loading="lazy" decoding="async"><b>MC</b></span>
                            <strong>Mastercard</strong>
                            <span>บัตรเครดิตหรือเดบิต</span>
                        </button>
                        <button type="button" class="payment-method-card" data-payment-method="truemoney">
                            <span class="payment-method-card__asset"><img src="<?= $escape((string)$modalConfig['paymentAssets']['truemoney']) ?>" alt="TrueMoney Wallet" loading="lazy" decoding="async"><b>TMN</b></span>
                            <strong>TrueMoney Wallet</strong>
                            <span>ชำระผ่านวอลเล็ต</span>
                        </button>
                    </div>

                    <div id="paymentMockDetail" class="payment-mock-detail">
                        <div class="payment-detail-asset">
                            <img src="<?= $escape((string)$modalConfig['paymentAssets']['promptpay']) ?>" alt="PromptPay QR">
                            <span><i class="bx bx-qr-scan"></i><b>QR</b></span>
                        </div>
                        <div>
                            <strong>PromptPay</strong>
                            <p>สแกน QR เพื่อชำระเงิน แล้วกดยืนยันเมื่อทำรายการเรียบร้อย</p>
                        </div>
                    </div>

                    <button id="mockPayButton" type="button" class="ticket-primary-button payment-confirm-button">
                        <i class="bx bx-check-shield"></i> ยืนยันการชำระเงิน
                    </button>
                    <p id="paymentMessage" class="payment-message" aria-live="polite"></p>
                </section>
            </main>
        </div>
    </section>
</div>
