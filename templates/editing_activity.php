<?php
$old = $old ?? [];
$errors = $errors ?? [];
$success = $success ?? false;
$eventId = (int)($event_id ?? 0);
$existingImages = is_array($existing_images ?? null) ? $existing_images : [];
$supportsCommerce = (bool)($supports_commerce ?? false);
$supportsTags = (bool)($supports_tags ?? false);
$registrationMinDate = (string)($registration_min_date ?? '');
$registrationMaxDate = (string)($registration_max_date ?? '');
$serverDateText = (string)($server_date_text ?? '-');
$registrationMaxText = (string)($registration_max_text ?? '');
$stagedImages = is_array($old['staged_images'] ?? null) ? array_values(array_filter($old['staged_images'], 'is_string')) : [];

$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$oldValue = static fn(string $key): string => htmlspecialchars((string)($old[$key] ?? ''), ENT_QUOTES, 'UTF-8');

$placeholderSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="760" viewBox="0 0 1200 760"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#0f172a"/><stop offset=".52" stop-color="#7c2d12"/><stop offset="1" stop-color="#ea580c"/></linearGradient><linearGradient id="p" x1="0" y1="0" x2="1" y2="0"><stop stop-color="#fed7aa"/><stop offset="1" stop-color="#fff7ed"/></linearGradient></defs><rect width="1200" height="760" fill="url(#g)"/><circle cx="950" cy="92" r="260" fill="#fb923c" opacity=".25"/><circle cx="130" cy="680" r="220" fill="#fed7aa" opacity=".16"/><rect x="230" y="180" width="740" height="360" rx="56" fill="#fff" opacity=".12" stroke="#fff" stroke-opacity=".24" stroke-width="5"/><path d="M310 445h580" stroke="url(#p)" stroke-width="10" stroke-linecap="round" stroke-dasharray="22 26" opacity=".72"/><text x="600" y="338" text-anchor="middle" fill="#fff" font-family="Arial, sans-serif" font-size="54" font-weight="900">BADOMEN</text><text x="600" y="404" text-anchor="middle" fill="#fed7aa" font-family="Arial, sans-serif" font-size="30" font-weight="800">EDIT EVENT</text></svg>';
$placeholderImage = 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($placeholderSvg);

$coverImage = $stagedImages[0] ?? $placeholderImage;
if (empty($stagedImages)) {
foreach ($existingImages as $image) {
    $path = trim((string)($image['image_path'] ?? ''));
    if ($path !== '') {
        $coverImage = $path;
        break;
    }
}
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขกิจกรรม | Badomen</title>

    <link rel="stylesheet" href="/style/app.css">
    <link rel="stylesheet" href="/style/editing-activity.css?v=4">
    <link rel="stylesheet" href="/style/footer.css?v=2">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet"></noscript>
</head>

<body class="edit-activity-page">
    <?php require __DIR__ . '/header.php'; ?>

    <main class="create-main">
        <section class="create-shell create-hero" aria-labelledby="editTitle">
            <div class="hero-copy">
                <div class="hero-kicker">
                    <i class="bx bx-edit-alt"></i>
                    CREATOR CONSOLE
                </div>

                <h1 id="editTitle">แก้ไขกิจกรรม</h1>
                <p>
                    ปรับข้อมูลกิจกรรม รูปภาพ เวลาเปิดรับสมัคร จำนวนผู้ร่วม และข้อมูลบัตร โดยหน้าแก้ไขใช้รูปแบบเดียวกับหน้า Create Activity เพื่อไม่ให้หน้าจอหลุดดีไซน์กัน
                </p>

                <div class="hero-pills" aria-label="ข้อมูลที่แก้ไขได้">
                    <span><i class="bx bx-images"></i> จัดการรูปภาพ</span>
                    <span><i class="bx bx-calendar-check"></i> ล็อควันรับสมัคร</span>
                    <span><i class="bx bx-group"></i> จำนวนผู้ร่วม</span>
                    <?php if ($supportsCommerce): ?><span><i class="bx bx-wallet"></i> ราคาและส่วนลด</span><?php endif; ?>
                </div>

                <div class="hero-actions">
                    <a href="/dashboard" class="btn btn-ghost"><i class="bx bx-arrow-back"></i> กลับ Dashboard</a>
                    <a href="/home_in" class="btn btn-soft"><i class="bx bx-show"></i> ดูหน้าผู้ใช้</a>
                </div>
            </div>

            <aside class="preview-card" aria-label="ตัวอย่างการ์ดกิจกรรมหลังแก้ไข">
                <div class="preview-topline">
                    <span>Live Preview</span>
                    <strong id="previewStatus">Editing</strong>
                </div>

                <div class="preview-media">
                    <img id="previewImage" src="<?= $escape($coverImage) ?>" alt="ตัวอย่างรูปกิจกรรม" decoding="async">
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
                        <strong>บันทึกการแก้ไขกิจกรรมสำเร็จแล้ว</strong>
                        <p>ข้อมูลกิจกรรมถูกอัปเดตแล้ว สามารถตรวจสอบหน้าผู้ใช้หรือกลับไป Dashboard ได้ทันที</p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert-card alert-error">
                    <i class="bx bx-error-circle"></i>
                    <div>
                        <strong>ยังบันทึกการแก้ไขไม่ได้</strong>
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
                data-registration-date-lock
                data-server-date="<?= $escape($registrationMinDate) ?>"
                data-server-date-text="<?= $escape($serverDateText) ?>"
                data-existing-cover="<?= $escape($coverImage) ?>"
                onsubmit="return confirmSubmission()">

                <input type="hidden" name="event_id" value="<?= $eventId ?>">

                <section class="form-panel" aria-labelledby="basicInfoTitle">
                    <div class="panel-head">
                        <span class="step-badge">01</span>
                        <div>
                            <h2 id="basicInfoTitle">ข้อมูลหลักของกิจกรรม</h2>
                            <p>ส่วนนี้ใช้แสดงบนการ์ดกิจกรรม รายละเอียด และหน้ากดบัตร</p>
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
                                placeholder="สรุปเป้าหมายกิจกรรม เงื่อนไข สิ่งที่ผู้เข้าร่วมต้องรู้ และสิ่งที่ต้องเตรียม"
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
                            <p>รูปแรกที่มีอยู่จะใช้เป็นภาพปก หากอัปโหลดรูปใหม่ ระบบจะพรีวิวรูปแรกที่เลือกทันที</p>
                        </div>
                    </div>

                    <?php if (empty($existingImages)): ?>
                        <p class="empty-media-note">ยังไม่มีรูปกิจกรรม</p>
                    <?php else: ?>
                        <div class="current-image-grid" aria-label="รูปภาพปัจจุบัน">
                            <?php foreach ($existingImages as $image): ?>
                                <label class="current-image-card">
                                    <img
                                        src="<?= $escape((string)$image['image_path']) ?>"
                                        alt="รูปกิจกรรมปัจจุบัน"
                                        loading="lazy"
                                        decoding="async">
                                    <input type="checkbox" name="remove_image_ids[]" value="<?= (int)$image['image_id'] ?>">
                                    <span class="current-image-action">
                                        <span>เลือกลบรูปนี้</span>
                                        <i class="bx bx-trash"></i>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <label id="uploadDrop" class="upload-zone">
                        <input
                            id="imagesInput"
                            type="file"
                            name="images[]"
                            multiple
                            accept=".jpg,.jpeg,.png,.webp,image/*">
                        <span class="upload-icon"><i class="bx bx-cloud-upload"></i></span>
                        <strong>ลากรูปมาวาง หรือกดเพื่อเลือกรูปใหม่</strong>
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
                            <h2 id="scheduleTitle">วันเวลาและรอบเปิดรับสมัคร</h2>
                            <p>ระบบล็อควันเปิดรับสมัครไม่ให้น้อยกว่าวันเซิร์ฟเวอร์ และไม่เกินวันเริ่มกิจกรรม</p>
                        </div>
                    </div>

                    <div class="field-grid">
                        <label class="field-block">
                            <span class="field-label"><i class="bx bx-time-five"></i> วันเวลาเริ่มกิจกรรม</span>
                            <input
                                id="eventStartInput"
                                type="datetime-local"
                                name="event_start"
                                value="<?= $oldValue('event_start') ?>"
                                required
                                class="form-control">
                            <span class="field-hint">ใช้ค่านี้เป็นวันสูงสุดของช่วงเปิดรับสมัคร</span>
                        </label>

                        <label class="field-block">
                            <span class="field-label"><i class="bx bx-time"></i> วันเวลาสิ้นสุดกิจกรรม</span>
                            <input
                                id="eventEndInput"
                                type="datetime-local"
                                name="event_end"
                                value="<?= $oldValue('event_end') ?>"
                                required
                                class="form-control">
                        </label>
                    </div>

                    <section class="date-lock-panel" aria-labelledby="dateLockTitle">
                        <div class="date-lock-head">
                            <div>
                                <h3 id="dateLockTitle">ช่วงวันที่เปิดรับได้</h3>
                                <p>วันที่แนะนำด้านล่างคือช่วงที่เลือกได้จริงตามเวลาเซิร์ฟเวอร์</p>
                            </div>
                            <span id="dateLockStatus" class="date-lock-status">รอข้อมูล</span>
                        </div>

                        <div class="date-lock-range" aria-label="ช่วงวันที่เปิดรับได้">
                            <div>
                                <span>วันต่ำสุด</span>
                                <strong id="dateLockMinText"><?= $escape($serverDateText) ?></strong>
                            </div>
                            <div>
                                <span>วันสูงสุด</span>
                                <strong id="dateLockMaxText"><?= $registrationMaxText !== '' ? $escape($registrationMaxText) : 'เลือกวันเริ่มกิจกรรมก่อน' ?></strong>
                            </div>
                        </div>

                        <div class="date-lock-rail" aria-hidden="true">
                            <span id="dateLockRailFill"></span>
                        </div>

                        <div id="dateLockChips" class="date-lock-chips" aria-label="วันที่เปิดรับสมัครที่แนะนำ"></div>
                        <p id="dateLockMessage" class="date-lock-message" aria-live="polite"></p>
                    </section>

                    <div class="field-grid">
                        <label class="field-block">
                            <span class="field-label"><i class="bx bx-door-open"></i> วันเปิดรับสมัคร</span>
                            <input
                                id="regStartInput"
                                type="date"
                                name="reg_start"
                                value="<?= $oldValue('reg_start') ?>"
                                min="<?= $escape($registrationMinDate) ?>"
                                max="<?= $escape($registrationMaxDate) ?>"
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
                                min="<?= $escape($registrationMinDate) ?>"
                                max="<?= $escape($registrationMaxDate) ?>"
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
                            <h2 id="commerceTitle">จำนวนผู้ร่วม<?= $supportsCommerce ? ' ราคา และแท็ก' : '' ?></h2>
                            <p><?= $supportsCommerce ? 'ราคา 0 จะแสดงเป็น FREE และแท็กใช้ช่วยจัดกลุ่มกิจกรรม' : 'กำหนดจำนวนสูงสุดของผู้เข้าร่วมกิจกรรม' ?></p>
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

                        <?php if ($supportsCommerce): ?>
                            <label class="field-block">
                                <span class="field-label"><i class="bx bx-wallet"></i> ราคา (THB)</span>
                                <input
                                    id="priceInput"
                                    type="number"
                                    name="price"
                                    value="<?= $oldValue('price') !== '' ? $oldValue('price') : '0' ?>"
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

                            <?php if ($supportsTags): ?>
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
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <div class="form-actions">
                    <a href="/dashboard" class="btn btn-ghost"><i class="bx bx-x"></i> ยกเลิก</a>
                    <button type="submit" class="btn btn-primary"><i class="bx bx-save"></i> บันทึกการแก้ไข</button>
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
                            <span id="completionCount">0/<?= $supportsCommerce && $supportsTags ? '8' : '6' ?> รายการ</span>
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
                        <?php if ($supportsCommerce): ?><li data-check="price"><i class="bx bx-circle"></i> ราคา</li><?php endif; ?>
                        <li data-check="image"><i class="bx bx-circle"></i> รูปภาพ</li>
                    </ul>
                </div>

                <div class="sidebar-card note-card">
                    <h2><i class="bx bx-info-circle"></i> คำแนะนำ</h2>
                    <p>ถ้าผู้จัดแก้วันเริ่มกิจกรรม ให้ตรวจช่วงเปิดรับสมัครทุกครั้ง เพราะระบบจะปรับ min/max ให้ตรงกับวันเซิร์ฟเวอร์และวันเริ่มกิจกรรมทันที</p>
                </div>

                <div class="sidebar-card sidebar-danger-note">
                    <h2><i class="bx bx-trash"></i> ลบกิจกรรม</h2>
                    <p>การลบกิจกรรมจะลบรายการลงทะเบียนและรูปภาพที่เกี่ยวข้องด้วย กู้คืนจากหน้านี้ไม่ได้</p>
                    <button type="button" class="btn btn-danger" onclick="submitDeleteForm()"><i class="bx bx-trash"></i> ลบกิจกรรมนี้</button>
                </div>
            </aside>
        </section>

        <form id="delete-activity-form" method="POST" class="is-hidden">
            <input type="hidden" name="event_id" value="<?= $eventId ?>">
            <input type="hidden" name="form_action" value="delete">
        </form>
    </main>

    <?php require __DIR__ . '/footer.php'; ?>

    <script>
(() => {
  'use strict';

  const form = document.getElementById('activityForm');
  if (!form) return;

  const $ = (id) => document.getElementById(id);

  const fields = {
    title: $('titleInput'),
    description: $('descriptionInput'),
    location: $('locationInput'),
    eventStart: $('eventStartInput'),
    eventEnd: $('eventEndInput'),
    regStart: $('regStartInput'),
    regEnd: $('regEndInput'),
    capacity: $('capacityInput'),
    price: $('priceInput'),
    compare: $('compareInput'),
    tags: $('tagsInput'),
    images: $('imagesInput')
  };

  const preview = {
    image: $('previewImage'),
    date: $('previewDate'),
    title: $('previewTitle'),
    description: $('previewDescription'),
    location: $('previewLocation'),
    capacity: $('previewCapacity'),
    price: $('previewPrice'),
    compare: $('previewCompare'),
    tags: $('previewTags'),
    status: $('previewStatus')
  };

  const timeline = {
    regStart: $('timelineRegStart'),
    regEnd: $('timelineRegEnd'),
    eventStart: $('timelineEventStart')
  };

  const dateLock = {
    minText: $('dateLockMinText'),
    maxText: $('dateLockMaxText'),
    status: $('dateLockStatus'),
    message: $('dateLockMessage'),
    railFill: $('dateLockRailFill'),
    chips: $('dateLockChips')
  };

  const descriptionCount = $('descriptionCount');
  const completionText = $('completionText');
  const completionCount = $('completionCount');
  const completionBar = $('completionBar');
  const completionList = $('completionList');
  const filePreviewList = $('filePreviewList');
  const stagedImageList = $('stagedImageList');
  const uploadDrop = $('uploadDrop');

  const existingCover = form.dataset.existingCover || (preview.image ? preview.image.src : '');
  let selectedImageFiles = [];
  const serverDate = form.dataset.serverDate || new Date().toISOString().slice(0, 10);
  const serverDateText = form.dataset.serverDateText || formatDateLabel(serverDate);

  let currentObjectUrl = null;

  window.confirmSubmission = function confirmSubmission() {
    syncAll();
    if (!validateRegistrationRange()) {
      const target = fields.regStart && fields.regStart.validationMessage ? fields.regStart : fields.regEnd;
      if (target && typeof target.reportValidity === 'function') target.reportValidity();
      return false;
    }
    return confirm('ต้องการบันทึกการแก้ไขกิจกรรมนี้ใช่หรือไม่?');
  };

  window.confirmDeleteActivity = function confirmDeleteActivity() {
    return confirm('การลบไม่สามารถกู้คืนได้ ต้องการลบกิจกรรมนี้ใช่หรือไม่?');
  };

  window.submitDeleteForm = function submitDeleteForm() {
    if (!window.confirmDeleteActivity()) return;
    const deleteForm = $('delete-activity-form');
    if (deleteForm) deleteForm.submit();
  };

  function hasValue(el) {
    return !!el && String(el.value || '').trim() !== '';
  }

  function compareDate(left, right) {
    if (!left || !right) return 0;
    if (left === right) return 0;
    return left > right ? 1 : -1;
  }

  function eventStartDateOnly() {
    const raw = fields.eventStart ? fields.eventStart.value || '' : '';
    return raw.includes('T') ? raw.slice(0, 10) : '';
  }

  function getValidMaxDate() {
    const eventStart = eventStartDateOnly();
    if (!eventStart) return '';
    const max = addDays(eventStart, -1);
    return compareDate(max, serverDate) >= 0 ? max : '';
  }

  function syncInputLimits() {
    if (!fields.regStart || !fields.regEnd) return;

    const max = getValidMaxDate();
    fields.regStart.min = serverDate;
    fields.regEnd.min = serverDate;

    if (max) {
      fields.regStart.max = max;
      fields.regEnd.max = max;
    } else {
      fields.regStart.removeAttribute('max');
      fields.regEnd.removeAttribute('max');
    }

    if (fields.regStart.value && compareDate(fields.regStart.value, serverDate) >= 0) {
      fields.regEnd.min = fields.regStart.value;
    }
  }

  function setStatus(text, state) {
    if (!dateLock.status) return;
    dateLock.status.textContent = text;
    dateLock.status.classList.remove('is-valid', 'is-warning', 'is-invalid');
    if (state) dateLock.status.classList.add(state);
  }

  function setMessage(text, state) {
    if (!dateLock.message) return;
    dateLock.message.textContent = text;
    dateLock.message.classList.remove('is-valid', 'is-invalid');
    if (state) dateLock.message.classList.add(state);
  }

  function setDateState(input, isValid) {
    if (!input) return;
    if (!input.value) {
      input.classList.remove('is-date-valid', 'is-date-invalid');
      return;
    }
    input.classList.toggle('is-date-valid', isValid);
    input.classList.toggle('is-date-invalid', !isValid);
  }

  function validateInput(input, label) {
    if (!input) return true;
    const max = getValidMaxDate();
    const value = input.value;
    input.setCustomValidity('');

    if (!value) {
      setDateState(input, true);
      return true;
    }

    if (compareDate(value, serverDate) < 0) {
      input.setCustomValidity(`${label}ต้องไม่ก่อนวันปัจจุบันของเซิร์ฟเวอร์`);
      setDateState(input, false);
      return false;
    }

    if (max && compareDate(value, max) > 0) {
      input.setCustomValidity(`${label}ต้องก่อนวันเริ่มกิจกรรมอย่างน้อย 1 วัน`);
      setDateState(input, false);
      return false;
    }

    setDateState(input, true);
    return true;
  }

  function validateRegistrationRange() {
    let isValid = true;
    isValid = validateInput(fields.regStart, 'วันเปิดรับสมัคร') && isValid;
    isValid = validateInput(fields.regEnd, 'วันปิดรับสมัคร') && isValid;

    if (fields.regStart && fields.regEnd && fields.regStart.value && fields.regEnd.value && compareDate(fields.regEnd.value, fields.regStart.value) < 0) {
      fields.regEnd.setCustomValidity('วันปิดรับสมัครต้องไม่ก่อนวันเปิดรับสมัคร');
      setDateState(fields.regEnd, false);
      isValid = false;
    }

    return isValid;
  }

  function renderDateChips() {
    if (!dateLock.chips) return;
    const max = getValidMaxDate();
    dateLock.chips.textContent = '';

    if (!max) {
      const chip = document.createElement('span');
      chip.className = 'date-lock-chip';
      chip.textContent = eventStartDateOnly() ? 'วันเริ่มกิจกรรมใกล้เกินไป ไม่มีวันรับสมัครที่ใช้ได้' : 'เลือกวันเริ่มกิจกรรมก่อน';
      dateLock.chips.appendChild(chip);
      return;
    }

    const selected = new Set([fields.regStart && fields.regStart.value, fields.regEnd && fields.regEnd.value].filter(Boolean));
    buildDateSamples(serverDate, max, 7).forEach((dateText) => {
      const chip = document.createElement('span');
      chip.className = 'date-lock-chip';
      if (selected.has(dateText)) chip.classList.add('is-selected');
      chip.textContent = formatDateLabel(dateText);
      dateLock.chips.appendChild(chip);
    });
  }

  function updateDateLockSummary() {
    if (!dateLock.minText || !dateLock.maxText) return;

    const eventStart = eventStartDateOnly();
    const max = getValidMaxDate();
    const validRange = max && compareDate(max, serverDate) >= 0;

    dateLock.minText.textContent = serverDateText;
    dateLock.maxText.textContent = max ? formatDateLabel(max) : (eventStart ? 'วันเริ่มกิจกรรมใกล้เกินไป' : 'เลือกวันเริ่มกิจกรรมก่อน');

    if (!eventStart) {
      setStatus('รอวันกิจกรรม', 'is-warning');
      setMessage('กรอกวันเวลาเริ่มกิจกรรมก่อน ระบบจึงจะล็อควันเปิดรับสมัครได้', '');
      if (dateLock.railFill) dateLock.railFill.style.setProperty('--date-lock-fill', '0%');
      renderDateChips();
      return;
    }

    if (!validRange) {
      setStatus('ไม่มีช่วงวันที่ใช้ได้', 'is-invalid');
      setMessage('วันเริ่มกิจกรรมต้องห่างจากวันปัจจุบันอย่างน้อย 1 วัน เพื่อให้มีช่วงเปิดรับสมัครที่ถูกต้อง', 'is-invalid');
      if (dateLock.railFill) dateLock.railFill.style.setProperty('--date-lock-fill', '0%');
      renderDateChips();
      return;
    }

    const selectedCount = [fields.regStart && fields.regStart.value, fields.regEnd && fields.regEnd.value].filter(Boolean).length;
    const validInputs = validateRegistrationRange();
    const fill = selectedCount === 0 ? '36%' : selectedCount === 1 ? '68%' : '100%';
    if (dateLock.railFill) dateLock.railFill.style.setProperty('--date-lock-fill', fill);

    if (validInputs && selectedCount === 2) {
      setStatus('วันที่ถูกต้อง', 'is-valid');
      setMessage(`ใช้ได้: เปิดรับสมัครตั้งแต่ ${formatDateLabel(fields.regStart.value)} ถึง ${formatDateLabel(fields.regEnd.value)}`, 'is-valid');
    } else if (validInputs) {
      setStatus('เลือกต่อได้', 'is-warning');
      setMessage(`ช่วงวันที่เปิดรับได้คือ ${serverDateText} ถึง ${formatDateLabel(max)}`, '');
    } else {
      setStatus('วันที่ผิดช่วง', 'is-invalid');
      setMessage(`เลือกได้เฉพาะช่วง ${serverDateText} ถึง ${formatDateLabel(max)} เท่านั้น`, 'is-invalid');
    }

    renderDateChips();
  }

  function formatDateTime(value) {
    if (!value) return 'ยังไม่ระบุ';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return 'ยังไม่ระบุ';
    return date.toLocaleString('th-TH', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  }

  function formatDate(value) {
    if (!value) return 'ยังไม่ระบุ';
    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) return 'ยังไม่ระบุ';
    return date.toLocaleDateString('th-TH', { day: '2-digit', month: 'short', year: 'numeric' });
  }

  function formatDateLabel(ymd) {
    if (!ymd) return '-';
    const parts = ymd.split('-').map(Number);
    if (parts.length !== 3 || parts.some(Number.isNaN)) return ymd;
    return new Intl.DateTimeFormat('th-TH', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(parts[0], parts[1] - 1, parts[2]));
  }

  function money(value) {
    const amount = Number(value || 0);
    if (!Number.isFinite(amount) || amount <= 0) return 'FREE';
    return amount.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' THB';
  }

  function updatePreview() {
    if (!preview.title) return;

    const title = fields.title ? fields.title.value.trim() : '';
    const description = fields.description ? fields.description.value.trim() : '';
    const location = fields.location ? fields.location.value.trim() : '';
    const capacity = fields.capacity ? fields.capacity.value.trim() : '';
    const price = fields.price ? Number(fields.price.value || 0) : 0;
    const compare = fields.compare ? Number(fields.compare.value || 0) : 0;
    const tags = fields.tags ? fields.tags.value.split(',').map(tag => tag.trim().replace(/^#/, '')).filter(Boolean).slice(0, 4) : [];

    preview.title.textContent = title || 'ชื่อกิจกรรมจะแสดงตรงนี้';
    preview.description.textContent = description || 'รายละเอียดสั้น ๆ จากช่องรายละเอียดจะถูกนำมาแสดงเป็นตัวอย่างบนการ์ดกิจกรรม';
    preview.location.textContent = location || 'ยังไม่ระบุสถานที่';
    preview.capacity.textContent = capacity ? capacity + ' คน' : '0 คน';
    preview.date.innerHTML = '<i class="bx bx-calendar-event"></i> ' + formatDateTime(fields.eventStart && fields.eventStart.value);
    preview.price.textContent = money(price);

    if (preview.compare) {
      if (compare > price && price >= 0) {
        preview.compare.hidden = false;
        preview.compare.textContent = money(compare);
      } else {
        preview.compare.hidden = true;
        preview.compare.textContent = '';
      }
    }

    if (preview.tags) {
      preview.tags.textContent = '';
      (tags.length ? tags : ['event', 'badomen']).forEach(tag => {
        const span = document.createElement('span');
        span.textContent = '#' + tag;
        preview.tags.appendChild(span);
      });
    }
  }

  function updateTimeline() {
    if (timeline.regStart) timeline.regStart.textContent = formatDate(fields.regStart && fields.regStart.value);
    if (timeline.regEnd) timeline.regEnd.textContent = formatDate(fields.regEnd && fields.regEnd.value);
    if (timeline.eventStart) timeline.eventStart.textContent = formatDateTime(fields.eventStart && fields.eventStart.value);
  }

  function updateDescriptionCount() {
    if (!descriptionCount || !fields.description) return;
    descriptionCount.textContent = String(fields.description.value.length);
  }

  function updateCompletion() {
    if (!completionText || !completionBar || !completionList) return;

    const checks = {
      title: hasValue(fields.title),
      description: hasValue(fields.description),
      location: hasValue(fields.location),
      schedule: hasValue(fields.eventStart) && hasValue(fields.eventEnd),
      registration: validateRegistrationRange() && hasValue(fields.regStart) && hasValue(fields.regEnd),
      capacity: hasValue(fields.capacity) && Number(fields.capacity.value) > 0,
      price: fields.price ? hasValue(fields.price) && Number(fields.price.value) >= 0 : true,
      image: Boolean(existingCover) || getStagedImageCount() > 0 || (fields.images && fields.images.files && fields.images.files.length > 0)
    };

    const items = Array.from(completionList.querySelectorAll('[data-check]'));
    const validCount = items.reduce((count, item) => {
      const key = item.dataset.check;
      const passed = Boolean(checks[key]);
      item.classList.toggle('is-complete', passed);
      const icon = item.querySelector('i');
      if (icon) icon.className = passed ? 'bx bx-check-circle' : 'bx bx-circle';
      return count + (passed ? 1 : 0);
    }, 0);

    const percent = items.length > 0 ? Math.round((validCount / items.length) * 100) : 0;
    completionText.textContent = percent + '%';
    if (completionCount) completionCount.textContent = `${validCount}/${items.length} รายการ`;
    completionBar.style.width = percent + '%';
  }

  function getStagedImageCount() {
    return stagedImageList ? stagedImageList.querySelectorAll('[data-staged-image]').length : 0;
  }

  function syncSelectedFilesToInput() {
    if (!fields.images || typeof DataTransfer === 'undefined') return;
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
    renderFilePreviews();
    syncAll();
  }

  function renderFilePreviews() {
    if (!filePreviewList || !fields.images) return;
    filePreviewList.textContent = '';

    if (currentObjectUrl) {
      URL.revokeObjectURL(currentObjectUrl);
      currentObjectUrl = null;
    }

    if (selectedImageFiles[0] && preview.image) {
      currentObjectUrl = URL.createObjectURL(selectedImageFiles[0]);
      preview.image.src = currentObjectUrl;
    } else if (preview.image && existingCover) {
      preview.image.src = existingCover;
    }

    selectedImageFiles.slice(0, 8).forEach((file, index) => {
      const item = document.createElement('div');
      item.className = 'file-preview-item staged-file-item';

      const thumb = document.createElement('img');
      thumb.alt = file.name;
      thumb.src = URL.createObjectURL(file);
      thumb.onload = function () {
        URL.revokeObjectURL(thumb.src);
      };

      const meta = document.createElement('div');
      meta.innerHTML = `<strong>${escapeHtml(file.name)}</strong><span>${formatFileSize(file.size)}</span>`;

      const removeButton = document.createElement('button');
      removeButton.type = 'button';
      removeButton.className = 'file-preview-remove';
      removeButton.setAttribute('aria-label', 'ลบรูปนี้');
      removeButton.innerHTML = '<i class="bx bx-x"></i>';
      removeButton.addEventListener('click', () => {
        selectedImageFiles.splice(index, 1);
        syncSelectedFilesToInput();
        renderFilePreviews();
        syncAll();
      });

      item.appendChild(thumb);
      item.appendChild(meta);
      item.appendChild(removeButton);
      filePreviewList.appendChild(item);
    });

    if (selectedImageFiles.length > 8) {
      const more = document.createElement('div');
      more.className = 'file-preview-more';
      more.textContent = '+' + (selectedImageFiles.length - 8) + ' รูป';
      filePreviewList.appendChild(more);
    }
  }

  Object.values(fields).forEach((field) => {
    if (!field || field === fields.images) return;
    field.addEventListener('input', syncAll);
    field.addEventListener('change', syncAll);
  });

  if (stagedImageList) {
    stagedImageList.addEventListener('click', (event) => {
      const button = event.target.closest('[data-remove-staged-image]');
      if (!button) return;
      const item = button.closest('[data-staged-image]');
      if (item) item.remove();
      if (selectedImageFiles.length === 0) {
        const firstStaged = stagedImageList.querySelector('[data-staged-image] img');
        if (firstStaged && preview.image) preview.image.src = firstStaged.src;
        else if (preview.image && existingCover) preview.image.src = existingCover;
      }
      syncAll();
    });
  }

  if (fields.images) {
    fields.images.addEventListener('change', () => addSelectedFiles(fields.images.files));
  }

  if (uploadDrop && fields.images) {
    ['dragenter', 'dragover'].forEach(eventName => {
      uploadDrop.addEventListener(eventName, (event) => {
        event.preventDefault();
        uploadDrop.classList.add('is-dragging');
      });
    });

    ['dragleave', 'drop'].forEach(eventName => {
      uploadDrop.addEventListener(eventName, (event) => {
        event.preventDefault();
        uploadDrop.classList.remove('is-dragging');
      });
    });

    uploadDrop.addEventListener('drop', (event) => {
      if (event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files.length) {
        addSelectedFiles(event.dataTransfer.files);
      }
    });
  }

  form.addEventListener('submit', (event) => {
    syncAll();
    if (!validateRegistrationRange()) {
      event.preventDefault();
      const target = fields.regStart && fields.regStart.validationMessage ? fields.regStart : fields.regEnd;
      if (target && typeof target.reportValidity === 'function') target.reportValidity();
    }
  });

  syncAll();

  function addDays(ymd, amount) {
    const parts = ymd.split('-').map(Number);
    const date = new Date(parts[0], parts[1] - 1, parts[2]);
    date.setDate(date.getDate() + amount);
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
  }

  function daysBetween(start, end) {
    const left = start.split('-').map(Number);
    const right = end.split('-').map(Number);
    return Math.max(0, Math.round((Date.UTC(right[0], right[1] - 1, right[2]) - Date.UTC(left[0], left[1] - 1, left[2])) / 86400000));
  }

  function buildDateSamples(start, end, limit) {
    const totalDays = daysBetween(start, end);
    if (totalDays <= limit - 1) {
      return Array.from({ length: totalDays + 1 }, (_, index) => addDays(start, index));
    }

    const result = new Set([start, end]);
    const step = Math.max(1, Math.floor(totalDays / (limit - 1)));
    for (let day = step; result.size < limit && day < totalDays; day += step) {
      result.add(addDays(start, day));
    }
    return Array.from(result).sort();
  }

  function formatFileSize(bytes) {
    if (!Number.isFinite(bytes) || bytes <= 0) return '0 KB';
    if (bytes < 1024 * 1024) return Math.ceil(bytes / 1024) + ' KB';
    return (bytes / 1024 / 1024).toFixed(1) + ' MB';
  }

  function escapeHtml(value) {
    return String(value).replace(/[&<>"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[char]));
  }
})();

</script>
</body>
</html>
