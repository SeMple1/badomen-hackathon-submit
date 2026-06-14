<?php
$old = $old ?? [];
$errors = $errors ?? [];
$success = $success ?? false;

$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$oldValue = static fn(string $key): string => htmlspecialchars((string)($old[$key] ?? ''), ENT_QUOTES, 'UTF-8');

$placeholderSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="760" viewBox="0 0 1200 760"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#0f172a"/><stop offset=".52" stop-color="#7c2d12"/><stop offset="1" stop-color="#ea580c"/></linearGradient><linearGradient id="p" x1="0" y1="0" x2="1" y2="0"><stop stop-color="#fed7aa"/><stop offset="1" stop-color="#fff7ed"/></linearGradient></defs><rect width="1200" height="760" rx="0" fill="url(#g)"/><circle cx="950" cy="92" r="260" fill="#fb923c" opacity=".25"/><circle cx="130" cy="680" r="220" fill="#fed7aa" opacity=".16"/><rect x="230" y="180" width="740" height="360" rx="56" fill="#fff" opacity=".12" stroke="#fff" stroke-opacity=".24" stroke-width="5"/><path d="M310 445h580" stroke="url(#p)" stroke-width="10" stroke-linecap="round" stroke-dasharray="22 26" opacity=".72"/><text x="600" y="338" text-anchor="middle" fill="#fff" font-family="Arial, sans-serif" font-size="54" font-weight="900">BADOMEN</text><text x="600" y="404" text-anchor="middle" fill="#fed7aa" font-family="Arial, sans-serif" font-size="30" font-weight="800">CREATE EVENT</text></svg>';
$placeholderImage = 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($placeholderSvg);

$oldJson = json_encode(
    $old,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
$ticketModeOld = (string)($old['ticket_mode'] ?? 'general');
$seatSelectionOld = (string)($old['seat_selection_mode'] ?? 'manual');
$ticketLimitOld = (string)($old['max_tickets_per_user'] ?? '2');
$ticketZonesJsonOld = (string)($old['ticket_zones_json'] ?? '');
$stagedImages = is_array($old['staged_images'] ?? null) ? array_values(array_filter($old['staged_images'], 'is_string')) : [];
$previewImage = $stagedImages[0] ?? $placeholderImage;
$createTz = new DateTimeZone('Asia/Bangkok');
$serverToday = (new DateTimeImmutable('today', $createTz))->format('Y-m-d');
$eventStartDateOld = '';
if (!empty($old['event_start'])) {
    $eventStartRaw = substr((string)$old['event_start'], 0, 10);
    $eventStartDate = DateTimeImmutable::createFromFormat('!Y-m-d', $eventStartRaw, $createTz);
    if ($eventStartDate instanceof DateTimeImmutable) {
        $eventStartDateOld = $eventStartDate->modify('-1 day')->format('Y-m-d');
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สร้างกิจกรรม | Badomen</title>

    <link rel="stylesheet" href="/style/app.css">
    <link rel="stylesheet" href="/style/create-activity.css?v=8">
    <link rel="stylesheet" href="/style/footer.css?v=2">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet"></noscript>
</head>

<body class="create-activity-page">
    <?php require __DIR__ . '/header.php'; ?>

    <main class="create-main">
        <section class="create-shell create-hero" aria-labelledby="createTitle">
            <div class="hero-copy">
                <div class="hero-kicker">
                    <i class="bx bx-calendar-plus"></i>
                    CREATOR CONSOLE
                </div>

                <h1 id="createTitle">สร้างกิจกรรมใหม่</h1>
                <p>
                    กรอกข้อมูลกิจกรรมให้ครบก่อนเผยแพร่ ระบบจะนำข้อมูลนี้ไปใช้กับหน้าแรก การ์ดกิจกรรม รายละเอียดกิจกรรม การสมัคร และแดชบอร์ดผู้จัด
                </p>

                <div class="hero-pills" aria-label="ข้อมูลที่รองรับ">
                    <span><i class="bx bx-images"></i> หลายรูปภาพ</span>
                    <span><i class="bx bx-purchase-tag-alt"></i> ราคาและส่วนลด</span>
                    <span><i class="bx bx-hash"></i> แท็กกิจกรรม</span>
                    <span><i class="bx bx-group"></i> จำกัดจำนวนผู้ร่วม</span>
                </div>

                <div class="hero-actions">
                    <a href="/dashboard" class="btn btn-ghost"><i class="bx bx-arrow-back"></i> กลับ Dashboard</a>
                    <a href="/home_in" class="btn btn-soft"><i class="bx bx-show"></i> ดูหน้าผู้ใช้</a>
                </div>
            </div>

            <aside class="preview-card" aria-label="ตัวอย่างการ์ดกิจกรรม">
                <div class="preview-topline">
                    <span>Live Preview</span>
                    <strong id="previewStatus">Draft</strong>
                </div>

                <div class="preview-media">
                    <img id="previewImage" src="<?= $escape($previewImage) ?>" data-placeholder-src="<?= $escape($placeholderImage) ?>" alt="ตัวอย่างรูปกิจกรรม" decoding="async">
                    <span class="preview-date" id="previewDate"><i class="bx bx-calendar-event"></i> ยังไม่ระบุเวลา</span>
                </div>

                <div class="preview-body">
                    <h2 id="previewTitle">ชื่อกิจกรรมจะแสดงตรงนี้</h2>
                    <p id="previewDescription">รายละเอียดสั้น ๆ จากช่องรายละเอียดจะถูกนำมาแสดงเป็นตัวอย่างบนการ์ดกิจกรรม</p>

                    <div id="previewTags" class="preview-tags">
                        <span>#event</span>
                        <span>#badomen</span>
                    </div>

                    <div class="preview-meta">
                        <span><i class="bx bx-map"></i><b id="previewLocation">ยังไม่ระบุสถานที่</b></span>
                        <span><i class="bx bx-group"></i><b id="previewCapacity">0 คน</b></span>
                    </div>

                    <div class="preview-price">
                        <strong id="previewPrice">FREE</strong>
                        <del id="previewCompare" hidden></del>
                    </div>
                </div>
            </aside>
        </section>

        <section class="create-shell alert-zone" aria-live="polite">
            <?php if ($success): ?>
                <div class="alert-card alert-success">
                    <i class="bx bx-check-circle"></i>
                    <div>
                        <strong>บันทึกกิจกรรมสำเร็จแล้ว</strong>
                        <p>กิจกรรมถูกสร้างแล้ว สามารถตรวจสอบสถิติและรายชื่อผู้เข้าร่วมได้จาก Dashboard</p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert-card alert-error">
                    <i class="bx bx-error-circle"></i>
                    <div>
                        <strong>ยังบันทึกกิจกรรมไม่ได้</strong>
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= $escape((string)$error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <section class="create-shell create-workspace">
            <form
                id="activityForm"
                method="POST"
                enctype="multipart/form-data"
                class="activity-form"
                data-server-today="<?= $escape($serverToday) ?>"
                onsubmit="return confirmSubmission()">

                <section class="form-panel" aria-labelledby="basicInfoTitle">
                    <div class="panel-head">
                        <span class="step-badge">01</span>
                        <div>
                            <h2 id="basicInfoTitle">ข้อมูลหลักของกิจกรรม</h2>
                            <p>ข้อมูลส่วนนี้ใช้แสดงบนการ์ดกิจกรรมและหน้ารายละเอียด</p>
                        </div>
                    </div>

                    <div class="field-stack">
                        <label class="field-block">
                            <span class="field-label"><i class="bx bx-edit-alt"></i> ชื่อกิจกรรม</span>
                            <input
                                id="titleInput"
                                type="text"
                                name="title"
                                value="<?= $oldValue('title') ?>"
                                required
                                maxlength="160"
                                placeholder="เช่น MSU Hackathon 2026"
                                class="form-control"
                                data-preview="title">
                        </label>

                        <label class="field-block">
                            <span class="field-label"><i class="bx bx-detail"></i> รายละเอียดกิจกรรม</span>
                            <textarea
                                id="descriptionInput"
                                name="description"
                                rows="7"
                                required
                                maxlength="2800"
                                placeholder="สรุปเป้าหมายกิจกรรม สิ่งที่จะได้ทำ เงื่อนไขสำคัญ และรายละเอียดที่ผู้เข้าร่วมควรรู้"
                                class="form-control form-textarea"
                                data-preview="description"><?= $oldValue('description') ?></textarea>
                            <span class="field-hint"><b id="descriptionCount">0</b>/2800 ตัวอักษร</span>
                        </label>

                        <label class="field-block">
                            <span class="field-label"><i class="bx bx-map"></i> สถานที่</span>
                            <input
                                id="locationInput"
                                type="text"
                                name="location"
                                value="<?= $oldValue('location') ?>"
                                required
                                maxlength="255"
                                placeholder="เช่น คณะวิทยาการสารสนเทศ มหาวิทยาลัยมหาสารคาม"
                                class="form-control"
                                data-preview="location">
                        </label>
                        <?php require __DIR__ . '/partials/location_picker.php'; ?>
                    </div>
                </section>

                <section class="form-panel" aria-labelledby="mediaTitle">
                    <div class="panel-head">
                        <span class="step-badge">02</span>
                        <div>
                            <h2 id="mediaTitle">รูปภาพกิจกรรม</h2>
                            <p>รูปแรกจะถูกใช้เป็นภาพปกบนหน้าค้นหากิจกรรม</p>
                        </div>
                    </div>

                    <label id="uploadDrop" class="upload-zone">
                        <input
                            id="imagesInput"
                            type="file"
                            name="images[]"
                            multiple
                            accept=".jpg,.jpeg,.png,.webp,image/*">
                        <span class="upload-icon"><i class="bx bx-cloud-upload"></i></span>
                        <strong>ลากรูปมาวาง หรือกดเพื่อเลือกรูป</strong>
                        <em>รองรับ JPG, PNG, WEBP ขนาดไม่เกิน 5MB ต่อไฟล์</em>
                    </label>

                    <div id="stagedImageList" class="file-preview-list staged-preview-list" aria-live="polite">
                        <?php foreach ($stagedImages as $stagedImage): ?>
                            <div class="file-preview-item staged-file-item" data-staged-image="<?= $escape($stagedImage) ?>">
                                <img src="<?= $escape($stagedImage) ?>" alt="รูปที่พักไว้" loading="lazy" decoding="async">
                                <div>
                                    <strong><?= $escape(basename($stagedImage)) ?></strong>
                                    <span>รูปที่เลือกไว้จากรอบก่อน</span>
                                </div>
                                <input type="hidden" name="staged_images[]" value="<?= $escape($stagedImage) ?>">
                                <button type="button" class="file-preview-remove" data-remove-staged-image aria-label="ลบรูปที่พักไว้"><i class="bx bx-x"></i></button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="filePreviewList" class="file-preview-list" aria-live="polite"></div>
                </section>

                <section class="form-panel" aria-labelledby="scheduleTitle">
                    <div class="panel-head">
                        <span class="step-badge">03</span>
                        <div>
                            <h2 id="scheduleTitle">เวลาและรอบรับสมัคร</h2>
                            <p>ระบบจะตรวจสอบให้วันเริ่มกิจกรรมอยู่หลังวันปิดรับสมัคร</p>
                        </div>
                    </div>

                    <div class="field-grid">
                        <label class="field-block">
                            <span class="field-label"><i class="bx bx-calendar-event"></i> วันเวลาเริ่มกิจกรรม</span>
                            <input
                                id="eventStartInput"
                                type="datetime-local"
                                name="event_start"
                                value="<?= $oldValue('event_start') ?>"
                                required
                                class="form-control"
                                data-preview="event_start">
                        </label>

                        <label class="field-block">
                            <span class="field-label"><i class="bx bx-calendar-x"></i> วันเวลาสิ้นสุดกิจกรรม</span>
                            <input
                                id="eventEndInput"
                                type="datetime-local"
                                name="event_end"
                                value="<?= $oldValue('event_end') ?>"
                                required
                                class="form-control">
                        </label>

                        <label class="field-block">
                            <span class="field-label"><i class="bx bx-door-open"></i> วันเปิดรับสมัคร</span>
                            <input
                                id="regStartInput"
                                type="date"
                                name="reg_start"
                                value="<?= $oldValue('reg_start') ?>"
                                min="<?= $escape($serverToday) ?>"
                                <?= $eventStartDateOld !== '' ? 'max="' . $escape($eventStartDateOld) . '"' : '' ?>
                                required
                                class="form-control">
                        </label>

                        <label class="field-block">
                            <span class="field-label"><i class="bx bx-lock-alt"></i> วันปิดรับสมัคร</span>
                            <input
                                id="regEndInput"
                                type="date"
                                name="reg_end"
                                value="<?= $oldValue('reg_end') ?>"
                                min="<?= $escape($serverToday) ?>"
                                <?= $eventStartDateOld !== '' ? 'max="' . $escape($eventStartDateOld) . '"' : '' ?>
                                required
                                class="form-control">
                        </label>
                    </div>

                    <div class="timeline-preview" aria-label="ลำดับเวลาที่กรอก">
                        <div>
                            <span></span>
                            <strong>เปิดรับสมัคร</strong>
                            <p id="timelineRegStart">ยังไม่ระบุ</p>
                        </div>
                        <div>
                            <span></span>
                            <strong>ปิดรับสมัคร</strong>
                            <p id="timelineRegEnd">ยังไม่ระบุ</p>
                        </div>
                        <div>
                            <span></span>
                            <strong>เริ่มกิจกรรม</strong>
                            <p id="timelineEventStart">ยังไม่ระบุ</p>
                        </div>
                    </div>
                </section>

                <section class="form-panel" aria-labelledby="commerceTitle">
                    <div class="panel-head">
                        <span class="step-badge">04</span>
                        <div>
                            <h2 id="commerceTitle">จำนวนผู้ร่วม ราคา และแท็ก</h2>
                            <p>ราคา 0 จะถูกแสดงเป็น FREE และแท็กใช้ช่วยจัดกลุ่มกิจกรรม</p>
                        </div>
                    </div>

                    <div class="field-grid">
                        <label class="field-block">
                            <span class="field-label"><i class="bx bx-group"></i> จำนวนผู้เข้าร่วมสูงสุด</span>
                            <input
                                id="capacityInput"
                                type="number"
                                name="max_participant"
                                value="<?= $oldValue('max_participant') ?>"
                                min="1"
                                required
                                placeholder="เช่น 80"
                                class="form-control"
                                data-preview="capacity">
                        </label>

                        <label class="field-block">
                            <span class="field-label"><i class="bx bx-wallet"></i> ราคา (THB)</span>
                            <input
                                id="priceInput"
                                type="number"
                                name="price"
                                value="<?= $oldValue('price') ?: '0' ?>"
                                min="0"
                                step="0.01"
                                class="form-control"
                                data-preview="price">
                        </label>

                        <label class="field-block">
                            <span class="field-label"><i class="bx bx-purchase-tag"></i> ราคาก่อนลด</span>
                            <input
                                id="compareInput"
                                type="number"
                                name="compare_at_price"
                                value="<?= $oldValue('compare_at_price') ?>"
                                min="0"
                                step="0.01"
                                placeholder="กรอกเฉพาะเมื่อมีส่วนลด"
                                class="form-control"
                                data-preview="compare">
                            <span class="field-hint">ต้องมากกว่าราคาขาย หากกรอกช่องนี้</span>
                        </label>

                        <label class="field-block">
                            <span class="field-label"><i class="bx bx-hash"></i> แท็กกิจกรรม</span>
                            <input
                                id="tagsInput"
                                type="text"
                                name="tags"
                                value="<?= $oldValue('tags') ?>"
                                placeholder="เช่น workshop, music, networking"
                                class="form-control"
                                data-preview="tags">
                            <span class="field-hint">คั่นแต่ละแท็กด้วยเครื่องหมายจุลภาค สูงสุด 12 แท็ก</span>
                        </label>
                    </div>
                </section>

                <section class="form-panel ticket-builder-panel" aria-labelledby="ticketBuilderTitle">
                    <div class="panel-head">
                        <span class="step-badge">05</span>
                        <div>
                            <h2 id="ticketBuilderTitle">ระบบบัตร โซนราคา และผังที่นั่ง</h2>
                            <p>เลือกให้ชัดเจนเพียงรูปแบบเดียว: สมัครทั่วไปไม่มีโซน, เลือกโซนให้ระบบจัดที่นั่ง, หรือเลือกที่นั่งเองจากผัง</p>
                        </div>
                    </div>

                    <div class="ticket-mode-grid" role="radiogroup" aria-label="รูปแบบบัตร">
                        <label class="ticket-mode-card">
                            <input type="radio" name="ticket_mode" value="general" <?= $ticketModeOld === 'general' ? 'checked' : '' ?>>
                            <span><i class="bx bx-user-check"></i></span>
                            <strong>สมัครทั่วไป</strong>
                            <small>ไม่มีโซน ไม่มีผังที่นั่ง เหมาะกับกิจกรรมทั่วไป</small>
                        </label>

                        <label class="ticket-mode-card">
                            <input type="radio" name="ticket_mode" value="zone" <?= $ticketModeOld === 'zone' ? 'checked' : '' ?>>
                            <span><i class="bx bx-purchase-tag-alt"></i></span>
                            <strong>เลือกโซน</strong>
                            <small>ผู้ใช้เลือกโซนและจำนวนใบ ระบบจัดที่นั่งให้</small>
                        </label>

                        <label class="ticket-mode-card">
                            <input type="radio" name="ticket_mode" value="seating" <?= $ticketModeOld === 'seating' ? 'checked' : '' ?>>
                            <span><i class="bx bx-chair"></i></span>
                            <strong>เลือกที่นั่งเอง</strong>
                            <small>แสดงผังที่นั่งแบบคอนเสิร์ตใน pop-up</small>
                        </label>
                    </div>

                    <input id="seatSelectionMode" type="hidden" name="seat_selection_mode" value="<?= $escape($seatSelectionOld) ?>">

                    <div class="field-grid ticket-config-grid ticket-config-grid--simple">
                        <label class="field-block">
                            <span class="field-label"><i class="bx bx-user-pin"></i> จำกัดจำนวนต่อคน</span>
                            <input
                                id="maxTicketsPerUser"
                                type="number"
                                name="max_tickets_per_user"
                                value="<?= $escape($ticketLimitOld) ?>"
                                min="1"
                                max="2"
                                class="form-control">
                            <span class="field-hint">ตั้งไว้ไม่เกิน 2 ตามเงื่อนไขที่ต้องการ</span>
                        </label>

                        <div class="ticket-logic-note" id="ticketLogicNote" aria-live="polite">
                            <i class="bx bx-info-circle"></i>
                            <div>
                                <strong>สมัครทั่วไป</strong>
                                <span>ระบบจะซ่อนตัวแก้ไขโซนทั้งหมด และใช้จำนวนผู้เข้าร่วมสูงสุดจากช่องด้านบน</span>
                            </div>
                        </div>
                    </div>

                    <input id="ticketZonesJson" type="hidden" name="ticket_zones_json" value="<?= $escape($ticketZonesJsonOld) ?>">

                    <div id="zoneBuilderPanel" class="zone-builder" <?= $ticketModeOld === 'general' ? 'hidden' : '' ?>>
                        <div class="zone-builder-form" aria-label="เพิ่มโซนที่นั่ง">
                            <label>
                                <span>รหัสโซน</span>
                                <input id="zoneCodeInput" type="text" maxlength="12" placeholder="A, VIP, B1">
                            </label>
                            <label>
                                <span>ชื่อโซน</span>
                                <input id="zoneNameInput" type="text" maxlength="80" placeholder="VIP / Standard">
                            </label>
                            <label>
                                <span>ราคา</span>
                                <input id="zonePriceInput" type="number" min="0" step="0.01" placeholder="650">
                            </label>
                            <label>
                                <span>สีโซน</span>
                                <input id="zoneColorInput" type="color" value="#38bdf8">
                            </label>
                            <label>
                                <span>จำนวนแถว</span>
                                <input id="zoneRowsInput" type="number" min="1" max="26" value="5">
                            </label>
                            <label>
                                <span>ที่นั่ง/แถว</span>
                                <input id="zoneSeatsInput" type="number" min="1" max="60" value="12">
                            </label>
                            <button id="addZoneButton" type="button" class="btn btn-soft"><i class="bx bx-plus"></i> เพิ่มโซน</button>
                        </div>

                        <div class="zone-builder-head">
                            <div>
                                <strong>โซนที่สร้างไว้</strong>
                                <span id="zoneBuilderSummary">ยังไม่มีโซน</span>
                            </div>
                            <button id="loadSampleZonesButton" type="button" class="btn btn-ghost"><i class="bx bx-magic-wand"></i> ตัวอย่าง 7 โซน</button>
                        </div>

                        <div id="zoneBuilderList" class="zone-builder-list"></div>
                    </div>
                </section>

                <div class="form-actions">
                    <a href="/dashboard" class="btn btn-ghost"><i class="bx bx-x"></i> ยกเลิก</a>
                    <button type="submit" class="btn btn-primary"><i class="bx bx-save"></i> บันทึกกิจกรรม</button>
                </div>
            </form>

            <aside class="creator-sidebar">
                <div class="sidebar-card sticky-card">
                    <div class="sidebar-head">
                        <span><i class="bx bx-list-check"></i></span>
                        <div>
                            <h2>ความครบถ้วน</h2>
                            <p>เช็คข้อมูลก่อนบันทึก</p>
                        </div>
                    </div>

                    <div class="completion-meter" style="--complete: 0%;">
                        <div class="meter-top">
                            <strong id="completionText">0%</strong>
                            <span id="completionCount">0/8 รายการ</span>
                        </div>
                        <div class="meter-track"><div id="completionBar"></div></div>
                    </div>

                    <ul class="checklist" id="completionList">
                        <li data-check="title"><i class="bx bx-circle"></i> ชื่อกิจกรรม</li>
                        <li data-check="description"><i class="bx bx-circle"></i> รายละเอียด</li>
                        <li data-check="location"><i class="bx bx-circle"></i> สถานที่</li>
                        <li data-check="schedule"><i class="bx bx-circle"></i> วันเวลาอีเวนต์</li>
                        <li data-check="registration"><i class="bx bx-circle"></i> รอบรับสมัคร</li>
                        <li data-check="capacity"><i class="bx bx-circle"></i> จำนวนผู้ร่วม</li>
                        <li data-check="price"><i class="bx bx-circle"></i> ราคา</li>
                        <li data-check="image"><i class="bx bx-circle"></i> รูปภาพ</li>
                    </ul>
                </div>

                <div class="sidebar-card note-card">
                    <h2><i class="bx bx-info-circle"></i> คำแนะนำ</h2>
                    <p>ใส่รูปแนวนอนอย่างน้อย 1 รูปจะทำให้การ์ดกิจกรรมบนหน้าแรกดูดีกว่า และควรเขียนรายละเอียดให้บอกสิ่งที่ผู้ร่วมต้องเตรียมมาด้วย</p>
                </div>
            </aside>
        </section>
    </main>

    <?php require __DIR__ . '/footer.php'; ?>

    <script id="createActivityOld" type="application/json"><?= $oldJson ?: '{}' ?></script>
    <script>
    (function () {
        const form = document.getElementById('activityForm');
        const fields = {
            title: document.getElementById('titleInput'),
            description: document.getElementById('descriptionInput'),
            location: document.getElementById('locationInput'),
            eventStart: document.getElementById('eventStartInput'),
            eventEnd: document.getElementById('eventEndInput'),
            regStart: document.getElementById('regStartInput'),
            regEnd: document.getElementById('regEndInput'),
            capacity: document.getElementById('capacityInput'),
            price: document.getElementById('priceInput'),
            compare: document.getElementById('compareInput'),
            tags: document.getElementById('tagsInput'),
            images: document.getElementById('imagesInput')
        };

        const preview = {
            image: document.getElementById('previewImage'),
            date: document.getElementById('previewDate'),
            title: document.getElementById('previewTitle'),
            description: document.getElementById('previewDescription'),
            location: document.getElementById('previewLocation'),
            capacity: document.getElementById('previewCapacity'),
            price: document.getElementById('previewPrice'),
            compare: document.getElementById('previewCompare'),
            tags: document.getElementById('previewTags'),
            status: document.getElementById('previewStatus')
        };

        const timeline = {
            regStart: document.getElementById('timelineRegStart'),
            regEnd: document.getElementById('timelineRegEnd'),
            eventStart: document.getElementById('timelineEventStart')
        };

        const descriptionCount = document.getElementById('descriptionCount');
        const completionText = document.getElementById('completionText');
        const completionCount = document.getElementById('completionCount');
        const completionBar = document.getElementById('completionBar');
        const filePreviewList = document.getElementById('filePreviewList');
        const uploadDrop = document.getElementById('uploadDrop');

        const placeholder = preview.image ? (preview.image.dataset.placeholderSrc || preview.image.src) : '';
        const serverToday = form?.dataset.serverToday || '';

        function getEventStartDateValue() {
            if (!fields.eventStart || !fields.eventStart.value) return '';
            const eventStartDate = new Date(fields.eventStart.value.slice(0, 10) + 'T00:00:00');
            if (Number.isNaN(eventStartDate.getTime())) return '';
            eventStartDate.setDate(eventStartDate.getDate() - 1);
            const year = eventStartDate.getFullYear();
            const month = String(eventStartDate.getMonth() + 1).padStart(2, '0');
            const day = String(eventStartDate.getDate()).padStart(2, '0');
            return year + '-' + month + '-' + day;
        }

        function clampDateInput(input) {
            if (!input || !input.value) return;
            if (input.min && input.value < input.min) input.value = input.min;
            if (input.max && input.value > input.max) input.value = input.max;
        }

        function syncRegistrationDateLocks() {
            const eventStartDate = getEventStartDateValue();
            [fields.regStart, fields.regEnd].forEach((input) => {
                if (!input) return;
                input.min = serverToday;
                if (eventStartDate) {
                    input.max = eventStartDate;
                } else {
                    input.removeAttribute('max');
                }
                clampDateInput(input);
            });

            if (fields.regStart && fields.regEnd && fields.regStart.value && fields.regEnd.value && fields.regEnd.value < fields.regStart.value) {
                fields.regEnd.value = fields.regStart.value;
            }
        }

        function hasValue(el) {
            return !!el && String(el.value || '').trim() !== '';
        }

        function formatDateTime(value) {
            if (!value) return 'ยังไม่ระบุ';
            const date = new Date(value);
            if (Number.isNaN(date.getTime())) return 'ยังไม่ระบุ';
            return date.toLocaleString('th-TH', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function formatDate(value) {
            if (!value) return 'ยังไม่ระบุ';
            const date = new Date(value + 'T00:00:00');
            if (Number.isNaN(date.getTime())) return 'ยังไม่ระบุ';
            return date.toLocaleDateString('th-TH', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            });
        }

        function money(value) {
            const amount = Number(value || 0);
            if (!Number.isFinite(amount) || amount <= 0) return 'FREE';
            return amount.toLocaleString('th-TH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }) + ' THB';
        }

        function updatePreview() {
            syncRegistrationDateLocks();
            const title = fields.title.value.trim();
            const description = fields.description.value.trim();
            const location = fields.location.value.trim();
            const capacity = fields.capacity.value.trim();
            const price = Number(fields.price.value || 0);
            const compare = Number(fields.compare.value || 0);
            const tags = fields.tags.value
                .split(',')
                .map(tag => tag.trim().replace(/^#/, ''))
                .filter(Boolean)
                .slice(0, 4);

            preview.title.textContent = title || 'ชื่อกิจกรรมจะแสดงตรงนี้';
            preview.description.textContent = description || 'รายละเอียดสั้น ๆ จากช่องรายละเอียดจะถูกนำมาแสดงเป็นตัวอย่างบนการ์ดกิจกรรม';
            preview.location.textContent = location || 'ยังไม่ระบุสถานที่';
            preview.capacity.textContent = capacity ? capacity + ' คน' : '0 คน';
            preview.date.innerHTML = '<i class="bx bx-calendar-event"></i> ' + formatDateTime(fields.eventStart.value);
            preview.price.textContent = money(price);

            if (compare > price && price >= 0) {
                preview.compare.hidden = false;
                preview.compare.textContent = money(compare);
            } else {
                preview.compare.hidden = true;
                preview.compare.textContent = '';
            }

            preview.tags.innerHTML = '';
            if (tags.length === 0) {
                ['event', 'badomen'].forEach(tag => {
                    const span = document.createElement('span');
                    span.textContent = '#' + tag;
                    preview.tags.appendChild(span);
                });
            } else {
                tags.forEach(tag => {
                    const span = document.createElement('span');
                    span.textContent = '#' + tag;
                    preview.tags.appendChild(span);
                });
            }

            timeline.regStart.textContent = formatDate(fields.regStart.value);
            timeline.regEnd.textContent = formatDate(fields.regEnd.value);
            timeline.eventStart.textContent = formatDateTime(fields.eventStart.value);

            descriptionCount.textContent = String(fields.description.value.length);
            preview.status.textContent = form.checkValidity() ? 'Ready' : 'Draft';
            updateCompletion();
        }

        function updateCompletion() {
            const checks = {
                title: hasValue(fields.title),
                description: hasValue(fields.description),
                location: hasValue(fields.location),
                schedule: hasValue(fields.eventStart) && hasValue(fields.eventEnd),
                registration: hasValue(fields.regStart) && hasValue(fields.regEnd),
                capacity: hasValue(fields.capacity) && Number(fields.capacity.value) > 0,
                price: fields.price.value !== '' && Number(fields.price.value) >= 0,
                image: getStagedImageCount() > 0 || (fields.images.files && fields.images.files.length > 0)
            };

            const keys = Object.keys(checks);
            const done = keys.filter(key => checks[key]).length;
            const pct = Math.round((done / keys.length) * 100);

            completionText.textContent = pct + '%';
            completionCount.textContent = done + '/' + keys.length + ' รายการ';
            completionBar.style.width = pct + '%';

            keys.forEach(key => {
                const item = document.querySelector('[data-check="' + key + '"]');
                if (!item) return;
                item.classList.toggle('is-done', checks[key]);
                const icon = item.querySelector('i');
                if (icon) icon.className = checks[key] ? 'bx bx-check-circle' : 'bx bx-circle';
            });
        }

        const stagedImageList = document.getElementById('stagedImageList');
        let selectedImageFiles = [];

        function getStagedImageCount() {
            return stagedImageList ? stagedImageList.querySelectorAll('[data-staged-image]').length : 0;
        }

        function syncSelectedFilesToInput() {
            if (!fields.images) return;
            if (typeof DataTransfer === 'undefined') return;

            const transfer = new DataTransfer();
            selectedImageFiles.forEach((file) => transfer.items.add(file));
            fields.images.files = transfer.files;
        }

        function addSelectedFiles(fileList) {
            Array.from(fileList || []).forEach((file) => {
                if (!file || !file.type || !file.type.startsWith('image/')) return;
                const key = [file.name, file.size, file.lastModified].join(':');
                const exists = selectedImageFiles.some((item) => [item.name, item.size, item.lastModified].join(':') === key);
                if (!exists) selectedImageFiles.push(file);
            });

            selectedImageFiles = selectedImageFiles.slice(0, 12);
            syncSelectedFilesToInput();
            renderFilePreview();
        }

        function renderFilePreview() {
            filePreviewList.innerHTML = '';

            const allPreviewCount = getStagedImageCount() + selectedImageFiles.length;
            if (allPreviewCount === 0) {
                preview.image.src = placeholder;
                updateCompletion();
                return;
            }

            if (selectedImageFiles.length > 0) {
                const firstUrl = URL.createObjectURL(selectedImageFiles[0]);
                preview.image.src = firstUrl;
                preview.image.onload = function () {
                    URL.revokeObjectURL(firstUrl);
                    preview.image.onload = null;
                };
            }

            selectedImageFiles.slice(0, 8).forEach((file, index) => {
                const item = document.createElement('div');
                item.className = 'file-preview-item staged-file-item';

                const thumb = document.createElement('img');
                thumb.alt = file.name;

                const meta = document.createElement('div');
                meta.innerHTML = '<strong></strong><span></span>';
                meta.querySelector('strong').textContent = file.name;
                meta.querySelector('span').textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB';

                const removeButton = document.createElement('button');
                removeButton.type = 'button';
                removeButton.className = 'file-preview-remove';
                removeButton.setAttribute('aria-label', 'ลบรูปนี้');
                removeButton.innerHTML = '<i class="bx bx-x"></i>';
                removeButton.addEventListener('click', function () {
                    selectedImageFiles.splice(index, 1);
                    syncSelectedFilesToInput();
                    renderFilePreview();
                });

                item.appendChild(thumb);
                item.appendChild(meta);
                item.appendChild(removeButton);
                filePreviewList.appendChild(item);

                const reader = new FileReader();
                reader.onload = function (event) {
                    thumb.src = event.target.result;
                };
                reader.readAsDataURL(file);
            });

            if (selectedImageFiles.length > 8) {
                const more = document.createElement('div');
                more.className = 'file-preview-more';
                more.textContent = '+' + (selectedImageFiles.length - 8) + ' รูป';
                filePreviewList.appendChild(more);
            }

            updateCompletion();
        }

        if (stagedImageList) {
            stagedImageList.addEventListener('click', function (event) {
                const button = event.target.closest('[data-remove-staged-image]');
                if (!button) return;
                const item = button.closest('[data-staged-image]');
                if (item) item.remove();
                updateCompletion();
                if (selectedImageFiles.length === 0) {
                    const firstStaged = stagedImageList.querySelector('[data-staged-image] img');
                    preview.image.src = firstStaged ? firstStaged.src : placeholder;
                }
            });
        }

        Object.values(fields).forEach((field) => {
            if (!field) return;
            if (field === fields.images) return;
            field.addEventListener('input', updatePreview);
            field.addEventListener('change', updatePreview);
        });

        fields.images.addEventListener('change', function () {
            addSelectedFiles(fields.images.files);
        });

        ['dragenter', 'dragover'].forEach((eventName) => {
            uploadDrop.addEventListener(eventName, function (event) {
                event.preventDefault();
                uploadDrop.classList.add('is-dragging');
            });
        });

        ['dragleave', 'drop'].forEach((eventName) => {
            uploadDrop.addEventListener(eventName, function (event) {
                event.preventDefault();
                uploadDrop.classList.remove('is-dragging');
            });
        });

        uploadDrop.addEventListener('drop', function (event) {
            const files = event.dataTransfer && event.dataTransfer.files;
            if (!files || files.length === 0) return;
            addSelectedFiles(files);
        });

        window.confirmSubmission = function () {
            syncRegistrationDateLocks();
            if (!form.checkValidity()) {
                form.reportValidity();
                return false;
            }

            const title = fields.title.value.trim() || 'กิจกรรมนี้';
            const start = formatDateTime(fields.eventStart.value);
            return confirm('ยืนยันสร้าง "' + title + '"\nเริ่มกิจกรรม: ' + start + '\n\nต้องการบันทึกกิจกรรมนี้จริงหรือไม่?');
        };

        updatePreview();
    })();
    </script>
    <script>
(() => {
    const form = document.getElementById('activityForm');
    const hidden = document.getElementById('ticketZonesJson');
    const list = document.getElementById('zoneBuilderList');
    const summary = document.getElementById('zoneBuilderSummary');
    const addButton = document.getElementById('addZoneButton');
    const sampleButton = document.getElementById('loadSampleZonesButton');
    const modeRadios = Array.from(document.querySelectorAll('input[name="ticket_mode"]'));
    const selectionMode = document.getElementById('seatSelectionMode');
    const zoneBuilderPanel = document.getElementById('zoneBuilderPanel');
    const ticketLogicNote = document.getElementById('ticketLogicNote');
    const limitInput = document.getElementById('maxTicketsPerUser');
    const capacityInput = document.getElementById('capacityInput');
    const priceInput = document.getElementById('priceInput');

    if (!form || !hidden || !list) return;

    const inputs = {
        code: document.getElementById('zoneCodeInput'),
        name: document.getElementById('zoneNameInput'),
        price: document.getElementById('zonePriceInput'),
        color: document.getElementById('zoneColorInput'),
        rows: document.getElementById('zoneRowsInput'),
        seats: document.getElementById('zoneSeatsInput')
    };

    let zones = currentMode() === 'general' ? [] : parseZones(hidden.value);

    function parseZones(raw) {
        try {
            const value = JSON.parse(raw || '[]');
            return Array.isArray(value) ? value.map(normalizeZone).filter(Boolean) : [];
        } catch (error) {
            return [];
        }
    }

    function normalizeZone(zone) {
        const code = String(zone.zone_code || zone.code || '').trim().toUpperCase().slice(0, 12);
        const name = String(zone.zone_name || zone.name || code || '').trim().slice(0, 80);
        const price = Math.max(0, Number(zone.price || 0));
        const color = /^#[0-9a-f]{6}$/i.test(String(zone.color_hex || zone.color || '')) ? String(zone.color_hex || zone.color) : '#38bdf8';
        const rows = Math.max(1, Math.min(26, Number.parseInt(zone.row_count || zone.rows || 1, 10)));
        const seats = Math.max(1, Math.min(60, Number.parseInt(zone.seats_per_row || zone.seats || 1, 10)));
        if (!code || !name) return null;
        return {
            zone_code: code,
            zone_name: name,
            price,
            color_hex: color,
            row_count: rows,
            seats_per_row: seats
        };
    }

    function currentMode() {
        return modeRadios.find((radio) => radio.checked)?.value || 'general';
    }

    function formatMoney(value) {
        const n = Number(value || 0);
        if (n <= 0) return 'ฟรี';
        return new Intl.NumberFormat('th-TH', { maximumFractionDigits: n % 1 === 0 ? 0 : 2 }).format(n) + ' บาท';
    }

    function totalCapacity() {
        return zones.reduce((sum, zone) => sum + Number(zone.row_count || 0) * Number(zone.seats_per_row || 0), 0);
    }

    function syncHidden() {
        const mode = currentMode();
        hidden.value = mode === 'general' ? '[]' : JSON.stringify(zones);
        if (selectionMode) selectionMode.value = mode === 'zone' ? 'random' : 'manual';
        const total = totalCapacity();
        if (mode !== 'general' && total > 0 && capacityInput) {
            capacityInput.value = String(total);
            capacityInput.dispatchEvent(new Event('input', { bubbles: true }));
        }
        if (limitInput) {
            const n = Math.max(1, Math.min(2, Number.parseInt(limitInput.value || '2', 10)));
            limitInput.value = String(n);
        }
    }

    function render() {
        const mode = currentMode();
        const isGeneral = mode === 'general';
        if (isGeneral) zones = [];
        if (zoneBuilderPanel) {
            zoneBuilderPanel.hidden = isGeneral;
        }
        if (selectionMode) {
            selectionMode.value = mode === 'zone' ? 'random' : 'manual';
        }
        if (ticketLogicNote) {
            const title = ticketLogicNote.querySelector('strong');
            const text = ticketLogicNote.querySelector('span');
            if (title && text) {
                if (mode === 'general') {
                    title.textContent = 'สมัครทั่วไป';
                    text.textContent = 'ระบบซ่อนตัวแก้ไขโซน และใช้จำนวนผู้เข้าร่วมสูงสุดจากช่องด้านบน';
                } else if (mode === 'zone') {
                    title.textContent = 'เลือกโซน';
                    text.textContent = 'ผู้ใช้เลือกโซนและจำนวนใบ ระบบจัดที่นั่งให้เอง ไม่ต้องเลือกเก้าอี้ทีละตัว';
                } else {
                    title.textContent = 'เลือกที่นั่งเอง';
                    text.textContent = 'ผู้ใช้เลือกเก้าอี้จากผังที่นั่งใน pop-up เหมือนระบบกดบัตรคอนเสิร์ต';
                }
            }
        }

        list.replaceChildren();
        if (!zones.length) {
            list.innerHTML = '<div class="zone-empty"><i class="bx bx-info-circle"></i><strong>ยังไม่มีโซน</strong><span>ใช้เฉพาะโหมดเลือกโซนหรือเลือกที่นั่งเองเท่านั้น</span></div>';
        } else {
            const fragment = document.createDocumentFragment();
            zones.forEach((zone, index) => {
                const capacity = Number(zone.row_count) * Number(zone.seats_per_row);
                const card = document.createElement('article');
                card.className = 'zone-preview-card';
                card.style.setProperty('--zone-color', zone.color_hex);
                card.innerHTML = `
                    <div class="zone-preview-top">
                        <span>${escapeHtml(zone.zone_code)}</span>
                        <button type="button" data-remove-zone="${index}" aria-label="ลบโซน"><i class="bx bx-trash"></i></button>
                    </div>
                    <strong>${escapeHtml(zone.zone_name)}</strong>
                    <div class="zone-preview-meta">
                        <span>${formatMoney(zone.price)}</span>
                        <span>${zone.row_count} แถว × ${zone.seats_per_row} ที่</span>
                        <span>${capacity} ที่นั่ง</span>
                    </div>
                `;
                fragment.appendChild(card);
            });
            list.appendChild(fragment);
        }

        const total = totalCapacity();
        summary.textContent = zones.length
            ? `${zones.length} โซน / รวม ${total} ที่นั่ง`
            : 'ยังไม่มีโซน';

        syncHidden();
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function clearInputs() {
        inputs.code.value = '';
        inputs.name.value = '';
        inputs.price.value = '';
        inputs.color.value = '#38bdf8';
        inputs.rows.value = '5';
        inputs.seats.value = '12';
        inputs.code.focus();
    }

    function addZoneFromInputs() {
        const zone = normalizeZone({
            zone_code: inputs.code.value,
            zone_name: inputs.name.value,
            price: inputs.price.value,
            color_hex: inputs.color.value,
            row_count: inputs.rows.value,
            seats_per_row: inputs.seats.value
        });

        if (!zone) {
            alert('กรุณากรอกรหัสโซนและชื่อโซน');
            return;
        }

        const duplicate = zones.some((item) => item.zone_code === zone.zone_code);
        if (duplicate) {
            alert('รหัสโซนนี้มีอยู่แล้ว');
            return;
        }

        zones.push(zone);
        clearInputs();
        render();
    }

    function loadSampleZones() {
        zones = [
            { zone_code: 'A', zone_name: 'A', price: 590, color_hex: '#38bdf8', row_count: 6, seats_per_row: 14 },
            { zone_code: 'B', zone_name: 'B', price: 590, color_hex: '#22c55e', row_count: 6, seats_per_row: 14 },
            { zone_code: 'C', zone_name: 'C', price: 490, color_hex: '#a3e635', row_count: 6, seats_per_row: 16 },
            { zone_code: 'D', zone_name: 'D', price: 490, color_hex: '#facc15', row_count: 6, seats_per_row: 16 },
            { zone_code: 'STANDING', zone_name: 'STANDING', price: 390, color_hex: '#f97316', row_count: 1, seats_per_row: 80 },
            { zone_code: 'VIP', zone_name: 'VIP', price: 890, color_hex: '#e11d48', row_count: 3, seats_per_row: 10 },
            { zone_code: 'REGULAR', zone_name: 'REGULAR', price: 390, color_hex: '#8b5cf6', row_count: 8, seats_per_row: 18 }
        ];
        render();
    }

    addButton?.addEventListener('click', addZoneFromInputs);
    sampleButton?.addEventListener('click', loadSampleZones);
    list.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-remove-zone]');
        if (!removeButton) return;
        const index = Number(removeButton.dataset.removeZone);
        zones.splice(index, 1);
        render();
    });

    modeRadios.forEach((radio) => radio.addEventListener('change', render));
    limitInput?.addEventListener('input', syncHidden);

    form.addEventListener('submit', (event) => {
        const mode = currentMode();
        syncHidden();
        if (mode !== 'general' && zones.length < 1) {
            event.preventDefault();
            alert('กรุณาเพิ่มโซนอย่างน้อย 1 โซน หรือเปลี่ยนเป็นสมัครทั่วไป');
            return;
        }
    });

    render();
})();

</script>
</body>
</html>
