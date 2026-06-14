<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/view.php';

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
                    $uStmt = $conn->prepare('SELECT name FROM users WHERE user_id = ? LIMIT 1');
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
            $up = $conn->prepare("
                UPDATE registrations
                SET status='checked_in', checked_in=NOW()
                WHERE event_id=? AND user_id=? LIMIT 1
            ");
            if ($up) {
                $up->bind_param('ii', $eventId, $matchedUserId);
                $up->execute();
                $up->close();
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

/*                 
===== code เช็ค ว่าผู้ใช้ตรวจสอบ otp หรือยัง 
*/
