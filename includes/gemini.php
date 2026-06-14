<?php
declare(strict_types=1);

function generateEventInsight(array $event): array
{
    $prompt = implode("\n", [
        'คุณคือผู้ช่วยเว็บไซต์ขายบัตรกิจกรรม ตอบภาษาไทย กระชับ และห้ามสร้างข้อมูลเพิ่ม',
        'ตอบเฉพาะผลลัพธ์สำหรับผู้ใช้ ห้ามอธิบายคำสั่ง ห้ามแสดง reasoning และห้ามตอบภาษาอังกฤษ',
        'ใช้ข้อความธรรมดา 3 ส่วน: ไฮไลต์กิจกรรม, เหมาะกับใคร 3 ข้อ, เช็กลิสต์ก่อนจอง 3 ข้อ',
        'ชื่อ: ' . (string)($event['title'] ?? ''),
        'รายละเอียด: ' . (string)($event['description'] ?? ''),
        'สถานที่: ' . (string)($event['location'] ?? ''),
        'เริ่ม: ' . (string)($event['event_start'] ?? ''),
        'สิ้นสุด: ' . (string)($event['event_end'] ?? ''),
        'เปิดจอง: ' . (string)($event['reg_start'] ?? ''),
        'ปิดจอง: ' . (string)($event['reg_end'] ?? ''),
        'ราคา: ' . number_format((float)($event['price'] ?? 0), 2) . ' ' . (string)($event['currency'] ?? 'THB'),
        'จำนวนที่รับ: ' . (string)($event['max_participant'] ?? 0),
        'จำนวนอนุมัติแล้ว: ' . (string)($event['registered_count'] ?? 0),
    ]);

    return generateGeminiText($prompt, 900);
}

function generateGeminiText(string $prompt, int $maxOutputTokens = 900): array
{
    $apiKey = trim((string)getenv('GEMINI_API_KEY'));
    $model = trim((string)(getenv('GEMINI_MODEL') ?: 'gemini-3.5-flash'));
    if ($apiKey === '') {
        return ['ok' => false, 'error' => 'ยังไม่ได้ตั้งค่า Gemini API'];
    }

    $prompt = trim($prompt);
    if ($prompt === '') {
        return ['ok' => false, 'error' => 'ไม่มีข้อมูลสำหรับส่งให้ AI'];
    }

    $payload = json_encode([
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => [
            'temperature' => 0.25,
            'maxOutputTokens' => max(200, min(1400, $maxOutputTokens)),
            'thinkingConfig' => ['thinkingLevel' => 'minimal'],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
        . rawurlencode($model) . ':generateContent';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $apiKey,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 18,
    ]);
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if (!is_string($raw) || $raw === '' || $status < 200 || $status >= 300) {
        error_log('Gemini request failed: HTTP ' . $status . ($curlError !== '' ? ' curl' : ''));
        return ['ok' => false, 'error' => 'ระบบสรุปกิจกรรมยังไม่พร้อม กรุณาลองใหม่'];
    }

    $decoded = json_decode($raw, true);
    $answerParts = [];
    foreach ((array)($decoded['candidates'][0]['content']['parts'] ?? []) as $part) {
        if (!empty($part['thought']) || !isset($part['text'])) {
            continue;
        }
        $answerParts[] = (string)$part['text'];
    }
    $text = trim(implode("\n", $answerParts));
    return $text !== ''
        ? ['ok' => true, 'text' => $text]
        : ['ok' => false, 'error' => 'ไม่สามารถสร้างสรุปได้ในขณะนี้'];
}
