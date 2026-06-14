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
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สร้างกิจกรรม | Badomen</title>

    <link rel="stylesheet" href="/style/app.css">
    <link rel="stylesheet" href="/style/create-activity.css?v=5">
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
                    <img id="previewImage" src="<?= $escape($placeholderImage) ?>" alt="ตัวอย่างรูปกิจกรรม" decoding="async">
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

        const placeholder = preview.image ? preview.image.src : '';

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
                image: fields.images.files && fields.images.files.length > 0
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

        function renderFilePreview(files) {
            filePreviewList.innerHTML = '';

            if (!files || files.length === 0) {
                preview.image.src = placeholder;
                updateCompletion();
                return;
            }

            Array.from(files).slice(0, 6).forEach((file, index) => {
                const item = document.createElement('div');
                item.className = 'file-preview-item';

                const thumb = document.createElement('img');
                thumb.alt = file.name;

                const meta = document.createElement('div');
                meta.innerHTML = '<strong></strong><span></span>';
                meta.querySelector('strong').textContent = file.name;
                meta.querySelector('span').textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB';

                item.appendChild(thumb);
                item.appendChild(meta);
                filePreviewList.appendChild(item);

                const reader = new FileReader();
                reader.onload = function (event) {
                    thumb.src = event.target.result;
                    if (index === 0) {
                        preview.image.src = event.target.result;
                    }
                };
                reader.readAsDataURL(file);
            });

            if (files.length > 6) {
                const more = document.createElement('div');
                more.className = 'file-preview-more';
                more.textContent = '+' + (files.length - 6) + ' รูป';
                filePreviewList.appendChild(more);
            }

            updateCompletion();
        }

        Object.values(fields).forEach((field) => {
            if (!field) return;
            field.addEventListener('input', updatePreview);
            field.addEventListener('change', updatePreview);
        });

        fields.images.addEventListener('change', function () {
            renderFilePreview(fields.images.files);
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

            try {
                fields.images.files = files;
            } catch (error) {
                // Some browsers do not allow setting FileList programmatically.
            }
            renderFilePreview(files);
        });

        window.confirmSubmission = function () {
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
</body>
</html>
