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

function eventChatbotPersonalityIntro(string $question, array $events, bool $freeOnly): string
{
    $count = count($events);
    $text = mb_strtolower($question);
    $hasSummaryIntent = str_contains($text, 'สรุป') || str_contains($text, 'summary') || str_contains($text, 'แนะนำ');
    $hasSoonIntent = str_contains($text, 'ใกล้') || str_contains($text, 'วันนี้') || str_contains($text, 'พรุ่งนี้') || str_contains($text, 'soon');

    if ($freeOnly) {
        return eventChatbotPickReply([
            "จัดให้แบบประหยัดแต่ยังดูดีครับ ผมเจอกิจกรรมฟรีที่เข้าทาง {$count} รายการ ลองกดการ์ดเพื่อเปิดรายละเอียดได้เลย",
            "สายฟรีไม่ใช่สายรองครับ ผมคัดกิจกรรมที่ไม่ต้องจ่ายก่อนมาให้ {$count} รายการ",
            "งบเป็นศูนย์ก็ยังออกไปสนุกได้ ผมเจอ {$count} ตัวเลือกที่ราคาเป็นฟรีให้แล้วครับ",
        ]);
    }

    if ($hasSummaryIntent) {
        return eventChatbotPickReply([
            "ผมสรุปให้แบบคนกำลังเลือกจริง ๆ นะครับ มี {$count} กิจกรรมที่น่าดู กดชื่อกิจกรรมเพื่อเปิด pop-up ได้ทันที",
            "นี่คือ shortlist ที่ระบบมองว่าน่าตรงกับคำถามที่สุด {$count} รายการ ผมเรียงจากเวลาที่กำลังจะมาถึงให้แล้ว",
            "คัดมาให้แบบไม่โยนลิสต์ยาว ๆ ครับ มี {$count} ตัวเลือกที่น่าลองดูรายละเอียดต่อ",
        ]);
    }

    if ($hasSoonIntent) {
        return eventChatbotPickReply([
            "ผมดึงกิจกรรมที่ยังไม่จบและใกล้เข้ามาให้ก่อนครับ เจอ {$count} รายการ",
            "ถ้าอยากหาอะไรไปเร็ว ๆ นี้ ชุดนี้น่าดูครับ มี {$count} กิจกรรมที่ยังเปิดอยู่",
            "นี่คือรายการที่ยังทันและควรดูต่อ {$count} กิจกรรมครับ",
        ]);
    }

    return eventChatbotPickReply([
        "ผมลองไล่ข้อมูลกิจกรรมจริงในระบบให้แล้ว เจอ {$count} รายการที่น่าจะตรงทางคุณ",
        "ได้ครับ ผมคัดจากกิจกรรมที่เผยแพร่และยังไม่หมดเวลาให้แล้ว {$count} รายการ",
        "เจอของน่าสนใจแล้วครับ {$count} กิจกรรมนี้น่าจะเป็นจุดเริ่มที่ดี",
    ]);
}

function eventChatbotFollowUpPrompts(bool $freeOnly): array
{
    $base = $freeOnly
        ? ['มีกิจกรรมฟรีใกล้ที่สุดไหม', 'สรุปกิจกรรมฟรีให้เลือก 3 อัน', 'มีกิจกรรมฟรีที่ไปคนเดียวได้ไหม']
        : ['แนะนำกิจกรรมใกล้เข้ามา', 'สรุปกิจกรรมที่น่าไปที่สุด', 'มีกิจกรรมที่เหมาะไปกับเพื่อนไหม'];

    $extra = ['มีเวิร์กช็อปไหม', 'ขอกิจกรรมแนวชิล ๆ', 'มีงานดนตรีหรือคอนเสิร์ตไหม'];
    return array_slice(array_values(array_unique(array_merge($base, $extra))), 0, 5);
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
    echo json_encode([
        'ok' => true,
        'message' => $specificReply,
        'events' => [],
        'suggestions' => eventChatbotFollowUpPrompts(false),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
    echo json_encode([
        'ok' => true,
        'message' => 'ยังไม่พบกิจกรรมที่เปิดให้เข้าร่วมตอนนี้ ลองถามให้กว้างขึ้น เช่น กิจกรรมฟรี กิจกรรมใกล้เข้ามา หรือเวิร์กช็อป',
        'events' => [],
        'suggestions' => eventChatbotFollowUpPrompts($freeOnly),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

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

echo json_encode([
    'ok' => true,
    'message' => eventChatbotPersonalityIntro($question, $events, $freeOnly),
    'events' => $items,
    'suggestions' => eventChatbotFollowUpPrompts($freeOnly),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
