<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

function eventChatbotSpecificReply(string $question): ?string
{
    $text = mb_strtolower($question);
    $compact = preg_replace('/\s+/u', '', $text) ?: $text;

    $eventHints = [
        'กิจกรรม', 'งาน', 'อีเวนต์', 'event', 'concert', 'คอนเสิร์ต', 'เวิร์กช็อป',
        'สมัคร', 'เข้าร่วม', 'บัตร', 'ตั๋ว', 'ราคา', 'ฟรี', 'จัดที่', 'เมื่อไหร่',
        'วันนี้', 'พรุ่งนี้', 'สัปดาห์', 'เดือน', 'แนะนำ', 'ค้นหา'
    ];
    $hasEventHint = false;
    foreach ($eventHints as $hint) {
        if (str_contains($compact, $hint)) {
            $hasEventHint = true;
            break;
        }
    }

    $groups = [
        [
            'patterns' => ['กินข้าว', 'ทานข้าว', 'หิว', 'ข้าวยัง', 'กินไร'],
            'replies' => [
                'ยังไม่ได้กินเลย แต่พร้อมช่วยหากิจกรรมให้ก่อนนะ ถ้าคุณหิว ลองถามว่า "มีกิจกรรมใกล้ร้านอาหารไหม" ได้เลย',
                'ขอบคุณที่ถามนะ ตอนนี้ผมโฟกัสช่วยหากิจกรรมให้คุณอยู่ ถ้าอยากได้งานชิล ๆ หลังมื้ออาหาร พิมพ์แนวที่สนใจมาได้เลย',
                'ถ้าเป็นผมคงเลือกกินก่อนแล้วค่อยไปงานสนุก ๆ ต่อ บอกประเภทกิจกรรมที่อยากไปได้เลย เดี๋ยวช่วยคัดให้'
            ],
        ],
        [
            'patterns' => ['ห้องน้ำ', 'สุขา', 'toilet', 'restroom', 'wc'],
            'replies' => [
                'เรื่องห้องน้ำขึ้นอยู่กับสถานที่จัดงานนะครับ แนะนำเปิดรายละเอียดกิจกรรมที่สนใจ แล้วเช็กข้อมูลสถานที่หรือสอบถามผู้จัดอีกที',
                'โดยทั่วไปสถานที่จัดงานมักมีห้องน้ำ แต่แต่ละกิจกรรมอาจไม่เหมือนกัน ถ้าบอกชื่องานหรือแนวงานที่สนใจ ผมช่วยเปิดรายละเอียดให้ดูได้',
                'คำถามนี้ตอบแบบฟันธงไม่ได้จากข้อมูลกิจกรรมทั้งหมดครับ ลองเลือกกิจกรรมก่อน แล้วดู location ใน popup เพื่อเช็กกับสถานที่จัดงาน'
            ],
        ],
        [
            'patterns' => ['สวัสดี', 'หวัดดี', 'hello', 'hi', 'ดีจ้า'],
            'replies' => [
                'สวัสดีครับ อยากไปกิจกรรมแนวไหน วันนี้ผมช่วยคัดให้ได้',
                'หวัดดีครับ พิมพ์แนวงานที่ชอบ เช่น ฟรี ดนตรี กีฬา หรือเวิร์กช็อป แล้วผมจะแนะนำให้',
                'มาเลยครับ อยากหากิจกรรมแบบไหน เดี๋ยวช่วยไล่ตัวเลือกที่เหมาะให้'
            ],
        ],
        [
            'patterns' => ['ขอบคุณ', 'แต้ง', 'thanks', 'thankyou'],
            'replies' => [
                'ยินดีครับ ถ้าอยากดูงานเพิ่ม พิมพ์แนวกิจกรรมที่สนใจมาได้เลย',
                'ด้วยความยินดีครับ อยากให้ช่วยคัดกิจกรรมฟรีหรือกิจกรรมใกล้วันไหนต่อไหม',
                'ได้เลยครับ พร้อมช่วยหากิจกรรมต่อเสมอ'
            ],
        ],
    ];

    foreach ($groups as $group) {
        foreach ($group['patterns'] as $pattern) {
            if (str_contains($compact, $pattern)) {
                return eventChatbotPickReply($group['replies']);
            }
        }
    }

    if (!$hasEventHint && mb_strlen($compact) >= 2) {
        return eventChatbotPickReply([
            'ผมตอบเรื่องทั่วไปได้นิดหน่อย แต่หน้าที่หลักคือช่วยหากิจกรรม ลองถามว่า "มีกิจกรรมฟรีไหม" หรือ "แนะนำคอนเสิร์ตหน่อย" ได้เลย',
            'คำถามนี้อาจอยู่นอกเรื่องกิจกรรมครับ ถ้าอยากค้นงาน ลองบอกประเภท สถานที่ หรือช่วงเวลาที่อยากไป',
            'ตอนนี้ผมช่วยเก่งสุดเรื่องค้นหาและแนะนำกิจกรรมครับ พิมพ์แนวงานที่สนใจมาได้เลย'
        ]);
    }

    return null;
}

function eventChatbotPickReply(array $replies): string
{
    if (!$replies) return '';
    try {
        return (string)$replies[random_int(0, count($replies) - 1)];
    } catch (Throwable) {
        return (string)$replies[array_rand($replies)];
    }
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'login_required']);
    exit;
}

$conn = getConnection();
if (!badomenIsVipUser($conn, (int)$_SESSION['user_id'])) {
    $conn->close();
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'vip_required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $conn->close();
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method Not Allowed']);
    exit;
}

$question = trim((string)($_POST['message'] ?? ''));
if ($question === '') {
    echo json_encode(['ok' => false, 'message' => 'กรุณาพิมพ์กิจกรรมที่สนใจ'], JSON_UNESCAPED_UNICODE);
    exit;
}

$search = preg_replace('/(ช่วย|หน่อย|อยาก|ร่วม|หา|ค้นหา|สรุป|กิจกรรม|งาน|มีอะไรบ้าง|ให้ฟัง)/u', ' ', mb_strtolower($question));
$specificReply = eventChatbotSpecificReply($question);
if ($specificReply !== null) {
    $conn->close();
    echo json_encode(['ok' => true, 'message' => $specificReply, 'events' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$tokens = array_values(array_filter(preg_split('/\s+/u', trim((string)$search)) ?: [], static fn($token) => mb_strlen($token) >= 2));
$freeOnly = str_contains($question, 'ฟรี');
$events = [];

if ($tokens) {
    $conditions = [];
    $params = [];
    $types = '';
    foreach (array_slice($tokens, 0, 4) as $token) {
        $conditions[] = '(title LIKE ? OR description LIKE ? OR location LIKE ?)';
        $like = '%' . $token . '%';
        array_push($params, $like, $like, $like);
        $types .= 'sss';
    }
    $priceFilter = $freeOnly ? ' AND price = 0' : '';
    $stmt = $conn->prepare(
        "SELECT event_id, title, description, location, event_start, price, currency
           FROM events
          WHERE status = 'published' AND visibility = 'public' AND event_end >= NOW()
            $priceFilter AND (" . implode(' OR ', $conditions) . ")
          ORDER BY event_start ASC LIMIT 5"
    );
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

if (!$events) {
    $priceFilter = $freeOnly ? ' AND price = 0' : '';
    $result = $conn->query(
        "SELECT event_id, title, description, location, event_start, price, currency
           FROM events
          WHERE status = 'published' AND visibility = 'public' AND event_end >= NOW() $priceFilter
          ORDER BY event_start ASC LIMIT 5"
    );
    $events = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}
$conn->close();

if (!$events) {
    echo json_encode(['ok' => true, 'message' => 'ยังไม่พบกิจกรรมที่เปิดให้เข้าร่วมในขณะนี้'], JSON_UNESCAPED_UNICODE);
    exit;
}

$items = [];
foreach ($events as $event) {
    $description = trim(strip_tags((string)$event['description']));
    if (mb_strlen($description) > 100) $description = mb_substr($description, 0, 100) . '...';
    $price = (float)$event['price'] > 0
        ? number_format((float)$event['price'], 0) . ' ' . (string)$event['currency']
        : 'ฟรี';
    $items[] = [
        'event_id' => (int)$event['event_id'],
        'title' => (string)$event['title'],
        'summary' => $description,
        'meta' => date('d/m/Y H:i', strtotime((string)$event['event_start'])) . ' · ' . (string)$event['location'] . ' · ' . $price,
        'url' => '/home_in?show_all=1#event-' . (int)$event['event_id'],
    ];
}

echo json_encode(['ok' => true, 'message' => 'กิจกรรมที่น่าจะตรงกับที่คุณหา', 'events' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
