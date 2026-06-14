<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/view.php';
require_once __DIR__ . '/../includes/gmail.php';
require_once __DIR__ . '/../includes/notifications.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . appUrl('/login'));
    exit;
}

$creatorId = (int)$_SESSION['user_id'];
$errors = [];
$success = null;

$eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : (int)($_POST['event_id'] ?? 0);
$entered = trim((string)($_POST['otp_code'] ?? ''));

$conn = getConnection();

/* ===============================
   เช็คว่า event นี้เป็นของ creator จริง
================================= */
$stmt = $conn->prepare('SELECT event_id, creator_id, title FROM events WHERE event_id = ? LIMIT 1');
$stmt->bind_param('i', $eventId);
$stmt->execute();
$result = $stmt->get_result();
$event = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$event || (int)$event['creator_id'] !== $creatorId) {
    $conn->close();
    header('Location: ' . appUrl('/my_activity'));
    exit;
}

/* ===============================
   เมื่อกดตรวจ OTP
================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!preg_match('/^\d{6}$/', $entered)) {
        $errors[] = 'OTP ต้องเป็นตัวเลข 6 หลัก';
    }

    if (empty($errors)) {

        // หาไฟล์ OTP ของ event นี้ทั้งหมด
        $otpBase = realpath(__DIR__ . '/../storage') ?: (__DIR__ . '/../storage');
        $otpDir = rtrim($otpBase, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'otp';

        if (is_dir($otpDir)) {

            $files = glob($otpDir . "/event{$eventId}_user*.json");

            $matchedUser = null;
            $matchedFile = null;
            $matchedUserId = 0;

            foreach ($files as $file) {

                $raw = file_get_contents($file);
                $record = json_decode((string)$raw, true);

                if (!is_array($record)) continue;

                $now = time();
                $expiresAt = (int)($record['expires_at'] ?? 0);
                $code = (string)($record['code'] ?? '');

                if ($expiresAt > 0 && $now > $expiresAt) {
                    continue; // ข้าม OTP ที่หมดอายุ
                }

                if (hash_equals($code, $entered)) {
                    $matchedUserId = (int)($record['user_id'] ?? 0);
                    $matchedFile = $file;

                    // ดึงชื่อผู้ใช้
                    $uStmt = $conn->prepare('SELECT name, email FROM users WHERE user_id = ? LIMIT 1');
                    $uStmt->bind_param('i', $matchedUserId);
                    $uStmt->execute();
                    $uRes = $uStmt->get_result();
                    $matchedUser = $uRes ? $uRes->fetch_assoc() : null;
                    $uStmt->close();

                    break;
                }
            }

            if ($matchedUser && $matchedUserId > 0) {

            // ✅ 1) ลบไฟล์ OTP ทันที กัน OTP ค้าง/ใช้ซ้ำ
            if ($matchedFile && is_file($matchedFile)) {
                @unlink($matchedFile);
            }

            // ✅ 2) update DB -> checked_in (ยืนยันสิทธิ์แล้ว)
            $checkedInUpdated = false;
            $up = $conn->prepare("
                UPDATE registrations
                SET status='checked_in', checked_in=NOW()
                WHERE event_id=? AND user_id=? LIMIT 1
            ");
            if ($up) {
                $up->bind_param('ii', $eventId, $matchedUserId);
                $checkedInUpdated = $up->execute();
                $up->close();
            }

            if ($checkedInUpdated) {
                queueCheckInWebNotification($conn, $matchedUserId, (int)$eventId, (string)($event['title'] ?? ''));
                sendCheckInThankYouEmail($matchedUser, $event);
            }

            $success = [
                'name' => (string)($matchedUser['name'] ?? '-'),
                'event' => (string)($event['title'] ?? ''),
            ];

        } else {
            $errors[] = 'OTP ไม่ถูกต้อง หรือหมดอายุแล้ว';
        }

        } else {
            $errors[] = 'ยังไม่มีผู้ขอ OTP';
        }
    }
}

$conn->close();

renderView('verifyotp', [
    'errors' => $errors,
    'success' => $success,
    'event' => $event,
    'event_id' => $eventId,
]);

function queueCheckInWebNotification(mysqli $conn, int $userId, int $eventId, string $eventTitle): void
{
    if ($userId <= 0 || !function_exists('databaseTableExists') || !databaseTableExists($conn, 'notifications')) {
        return;
    }

    $eventTitle = trim($eventTitle) !== '' ? trim($eventTitle) : 'กิจกรรม';
    $titleTh = 'ยืนยันสิทธิ์เข้าร่วมแล้ว';
    $titleEn = 'Checked in successfully';
    $bodyTh = 'คุณได้รับการยืนยันสิทธิ์เข้าร่วมกิจกรรม "' . $eventTitle . '" เรียบร้อยแล้ว ขอบคุณที่เข้าร่วมกิจกรรมกับเรา';
    $bodyEn = 'You have been checked in for "' . $eventTitle . '". Thank you for joining us.';
    $actionUrl = '/join_activity#event-' . $eventId;

    $stmt = $conn->prepare(
        'INSERT INTO notifications
         (user_id, event_id, type, title_th, title_en, body_th, body_en, action_url)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if ($stmt === false) {
        return;
    }

    $type = 'event_checked_in';
    $stmt->bind_param('iissssss', $userId, $eventId, $type, $titleTh, $titleEn, $bodyTh, $bodyEn, $actionUrl);
    if (!$stmt->execute()) {
        $stmt->close();
        return;
    }
    $notificationId = (int)$conn->insert_id;
    $stmt->close();

    if ($notificationId <= 0 || !databaseTableExists($conn, 'notification_deliveries')) {
        return;
    }

    $channel = 'web';
    $status = 'queued';
    $delivery = $conn->prepare('INSERT INTO notification_deliveries (notification_id, channel, status) VALUES (?, ?, ?)');
    if ($delivery !== false) {
        $delivery->bind_param('iss', $notificationId, $channel, $status);
        $delivery->execute();
        $delivery->close();
    }
}

function sendCheckInThankYouEmail(?array $user, array $event): void
{
    $email = trim((string)($user['email'] ?? ''));
    if ($email === '') {
        return;
    }

    $name = trim((string)($user['name'] ?? ''));
    $eventTitle = trim((string)($event['title'] ?? 'กิจกรรม'));
    $eventId = (int)($event['event_id'] ?? 0);
    $displayName = $name !== '' ? $name : 'ผู้เข้าร่วมกิจกรรม';
    $displayTitle = $eventTitle !== '' ? $eventTitle : 'กิจกรรม';
    $eventUrl = function_exists('appAbsoluteUrl')
        ? appAbsoluteUrl('/join_activity#event-' . $eventId)
        : appUrl('/join_activity#event-' . $eventId);
    $hackathonNote = 'หากท่านไม่ได้เข้าร่วมกิจกรรมแต่ได้รับจดหมายนี้ ทางทีมงานขออภัยอย่างสูง อีเมลฉบับนี้ถูกส่งโดยระบบต้นแบบสำหรับ Hackathon เพื่อทดสอบ flow การยืนยันสิทธิ์และการแจ้งเตือน กรุณาเพิกเฉยต่ออีเมลนี้ หรือแจ้งผู้จัดกิจกรรมให้ตรวจสอบข้อมูล';

    $safeName = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
    $safeTitle = htmlspecialchars($displayTitle, ENT_QUOTES, 'UTF-8');
    $safeUrl = htmlspecialchars($eventUrl, ENT_QUOTES, 'UTF-8');
    $safeNote = htmlspecialchars($hackathonNote, ENT_QUOTES, 'UTF-8');

    $subject = 'ขอบคุณที่เข้าร่วม: ' . $displayTitle;
    $text = "สวัสดี {$displayName}\n\n"
        . "ระบบยืนยันสิทธิ์เข้าร่วมกิจกรรม \"{$displayTitle}\" เรียบร้อยแล้ว ขอบคุณที่มาร่วมกิจกรรมกับเรา\n\n"
        . "ดูรายละเอียดกิจกรรม: {$eventUrl}\n\n"
        . $hackathonNote;

    $html = '<div style="margin:0;padding:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#0f172a">'
        . '<div style="max-width:640px;margin:0 auto;padding:28px 16px">'
        . '<div style="overflow:hidden;border-radius:24px;background:#ffffff;border:1px solid #e2e8f0;box-shadow:0 24px 70px rgba(15,23,42,.10)">'
        . '<div style="padding:28px;background:linear-gradient(135deg,#2563eb,#0f172a);color:#ffffff">'
        . '<div style="font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;opacity:.85">BADOMEN EVENT</div>'
        . '<h1 style="margin:10px 0 0;font-size:28px;line-height:1.25">ขอบคุณที่เข้าร่วมกิจกรรม</h1>'
        . '</div>'
        . '<div style="padding:28px">'
        . '<p style="margin:0 0 14px;font-size:16px;line-height:1.7">สวัสดี <strong>' . $safeName . '</strong></p>'
        . '<p style="margin:0 0 18px;font-size:16px;line-height:1.7">ระบบยืนยันสิทธิ์เข้าร่วมกิจกรรม <strong>' . $safeTitle . '</strong> เรียบร้อยแล้ว ขอบคุณที่ให้เวลากับกิจกรรมนี้</p>'
        . '<div style="margin:22px 0;padding:18px;border-radius:18px;background:#eff6ff;border:1px solid #bfdbfe">'
        . '<div style="font-size:12px;font-weight:800;color:#2563eb;letter-spacing:.08em;text-transform:uppercase">Checked in</div>'
        . '<div style="margin-top:6px;font-size:20px;font-weight:900;color:#0f172a">' . $safeTitle . '</div>'
        . '</div>'
        . '<a href="' . $safeUrl . '" style="display:inline-block;padding:13px 18px;border-radius:14px;background:#2563eb;color:#ffffff;text-decoration:none;font-weight:800">เปิดหน้ากิจกรรมของฉัน</a>'
        . '<p style="margin:24px 0 0;font-size:13px;line-height:1.7;color:#64748b">' . $safeNote . '</p>'
        . '</div>'
        . '</div>'
        . '</div>'
        . '</div>';

    sendGmailMessage($email, $subject, $text, $html);
}

/*                 
===== code เช็ค ว่าผู้ใช้ตรวจสอบ otp หรือยัง 
*/
