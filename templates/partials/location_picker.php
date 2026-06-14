<?php
$pickerOld = is_array($old ?? null) ? $old : [];
$pickerLat = trim((string)($pickerOld['latitude'] ?? ''));
$pickerLng = trim((string)($pickerOld['longitude'] ?? ''));
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="/style/location-picker.css?v=4">
<div class="location-picker">
    <div class="location-picker__head">
        <div>
            <strong><i class="bx bx-map-pin"></i> ปักตำแหน่งกิจกรรมบน OpenStreetMap</strong>
            <span>ค้นหาสถานที่ คลิกแผนที่เพื่อปักหมุด หรือกรอกละติจูด/ลองจิจูดเองได้โดยไม่จำกัดจำนวนทศนิยม</span>
        </div>
        <button type="button" id="locationMapReset"><i class="bx bx-target-lock"></i> กลับประเทศไทย</button>
    </div>
    <div class="location-picker__tools">
        <div class="location-picker__search" id="locationSearchForm" role="search">
            <label class="location-picker__search-field">
                <span>ค้นหาตำแหน่ง</span>
                <input
                    id="locationSearchInput"
                    type="search"
                    autocomplete="off"
                    placeholder="เช่น มหาวิทยาลัยมหาสารคาม, Siam Paragon, Khon Kaen">
            </label>
            <button type="button" id="locationSearchButton"><i class="bx bx-search"></i> ค้นหา</button>
        </div>
        <div class="location-picker__coords">
            <label>
                <span>ละติจูด</span>
                <input
                    id="latitudeInput"
                    name="latitude"
                    type="text"
                    inputmode="decimal"
                    autocomplete="off"
                    value="<?= htmlspecialchars($pickerLat, ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="13.7563309">
            </label>
            <label>
                <span>ลองจิจูด</span>
                <input
                    id="longitudeInput"
                    name="longitude"
                    type="text"
                    inputmode="decimal"
                    autocomplete="off"
                    value="<?= htmlspecialchars($pickerLng, ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="100.5017651">
            </label>
            <button type="button" id="locationApplyCoords"><i class="bx bx-current-location"></i> ใช้พิกัดนี้</button>
        </div>
    </div>
    <div id="locationPickerMap" class="location-picker__map" aria-label="แผนที่เลือกตำแหน่งกิจกรรม"></div>
    <div class="location-picker__status" id="locationPickerStatus">ยังไม่ได้ปักหมุด</div>
</div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(() => {
    const mapEl = document.getElementById('locationPickerMap');
    const latInput = document.getElementById('latitudeInput');
    const lngInput = document.getElementById('longitudeInput');
    const applyCoordsButton = document.getElementById('locationApplyCoords');
    const searchForm = document.getElementById('locationSearchForm');
    const searchButton = document.getElementById('locationSearchButton');
    const searchInput = document.getElementById('locationSearchInput');
    const status = document.getElementById('locationPickerStatus');
    document.body.classList.add('location-actions-gated');

    if (mapEl && 'IntersectionObserver' in window) {
        const actionObserver = new IntersectionObserver((entries) => {
            if (!entries.some((entry) => entry.isIntersecting)) return;
            document.body.classList.add('location-picker-reached');
            actionObserver.disconnect();
        }, {
            root: null,
            rootMargin: '0px 0px -35% 0px',
            threshold: 0.1
        });
        actionObserver.observe(mapEl);
    } else {
        document.body.classList.add('location-picker-reached');
    }

    if (!mapEl || !latInput || !lngInput || !window.L) return;

    const coordinatePattern = /^[-+]?(?:\d+(?:\.\d*)?|\.\d+)$/;
    const initial = parseCoordinatePair();
    const hasInitial = initial !== null;
    const map = L.map(mapEl, { zoomControl: true }).setView(
        hasInitial ? [initial.lat, initial.lng] : [13.7563, 100.5018],
        hasInitial ? 15 : 6
    );
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);
    let marker = hasInitial ? L.marker([initial.lat, initial.lng]).addTo(map) : null;

    function normalizeCoordinateText(value) {
        return String(value || '').trim().replace(',', '.');
    }

    function parseCoordinate(value) {
        const normalized = normalizeCoordinateText(value);
        if (!coordinatePattern.test(normalized)) return null;
        const number = Number(normalized);
        return Number.isFinite(number) ? number : null;
    }

    function parseCoordinatePair() {
        if (latInput.value.trim() === '' && lngInput.value.trim() === '') return null;
        const lat = parseCoordinate(latInput.value);
        const lng = parseCoordinate(lngInput.value);
        if (lat === null || lng === null || lat < -90 || lat > 90 || lng < -180 || lng > 180) return null;
        return {
            lat,
            lng,
            latText: normalizeCoordinateText(latInput.value),
            lngText: normalizeCoordinateText(lngInput.value)
        };
    }

    function setStatus(text, state) {
        status.textContent = text;
        status.classList.remove('is-set', 'is-error', 'is-loading');
        if (state) status.classList.add(state);
    }

    function update(lat, lng, options = {}) {
        const latText = options.latText || String(lat);
        const lngText = options.lngText || String(lng);
        latInput.value = latText.trim();
        lngInput.value = lngText.trim();
        if (!marker) marker = L.marker([lat, lng]).addTo(map);
        else marker.setLatLng([lat, lng]);
        if (options.pan !== false) map.setView([lat, lng], Math.max(map.getZoom(), options.zoom || 15));
        setStatus(`พิกัด ${latInput.value}, ${lngInput.value}`, 'is-set');
    }

    function clear() {
        if (marker) map.removeLayer(marker);
        marker = null;
        latInput.value = '';
        lngInput.value = '';
        setStatus('เอาหมุดออกแล้ว คลิกซ้ายบนแผนที่เพื่อปักใหม่', '');
    }

    function applyManualCoordinates() {
        const pair = parseCoordinatePair();
        latInput.setCustomValidity('');
        lngInput.setCustomValidity('');

        if (!pair) {
            const message = 'กรุณากรอกละติจูด -90 ถึง 90 และลองจิจูด -180 ถึง 180 ให้ถูกต้อง';
            latInput.setCustomValidity(message);
            latInput.reportValidity();
            setStatus(message, 'is-error');
            return;
        }

        update(pair.lat, pair.lng, {
            latText: pair.latText,
            lngText: pair.lngText,
            zoom: 16
        });
    }

    async function searchLocation(query) {
        const trimmed = query.trim();
        if (trimmed === '') {
            searchInput?.focus();
            return;
        }

        setStatus('กำลังค้นหาตำแหน่ง...', 'is-loading');

        try {
            const url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q=' + encodeURIComponent(trimmed);
            const response = await fetch(url, {
                headers: { Accept: 'application/json' }
            });
            if (!response.ok) throw new Error('search_failed');
            const results = await response.json();
            const first = Array.isArray(results) ? results[0] : null;
            if (!first || first.lat === undefined || first.lon === undefined) {
                setStatus('ไม่พบตำแหน่งนี้ ลองค้นหาด้วยชื่อสถานที่หรือจังหวัดให้ชัดขึ้น', 'is-error');
                return;
            }

            update(Number(first.lat), Number(first.lon), {
                latText: String(first.lat),
                lngText: String(first.lon),
                zoom: 16
            });
            setStatus(`พบตำแหน่ง: ${first.display_name || trimmed}`, 'is-set');
        } catch (error) {
            setStatus('ค้นหาตำแหน่งไม่สำเร็จ กรุณาลองใหม่หรือกรอกพิกัดเอง', 'is-error');
        }
    }

    if (hasInitial) update(initial.lat, initial.lng, {
        latText: latInput.value,
        lngText: lngInput.value,
        pan: false
    });

    map.on('click', (event) => update(event.latlng.lat, event.latlng.lng));
    map.on('contextmenu', (event) => {
        event.originalEvent?.preventDefault();
        clear();
    });
    document.getElementById('locationMapReset')?.addEventListener('click', () => map.setView([13.7563, 100.5018], 6));
    applyCoordsButton?.addEventListener('click', applyManualCoordinates);
    [latInput, lngInput].forEach((input) => {
        input.addEventListener('change', applyManualCoordinates);
        input.addEventListener('input', () => {
            input.setCustomValidity('');
            status.classList.remove('is-error');
        });
    });
    searchButton?.addEventListener('click', () => {
        searchLocation(searchInput?.value || '');
    });
    searchInput?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') return;
        event.preventDefault();
        searchLocation(searchInput.value || '');
    });
    window.setTimeout(() => map.invalidateSize(), 100);
})();
</script>
