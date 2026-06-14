<?php
declare(strict_types=1);

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        get();
        break;
    case 'POST':
        post();
        break;
    case 'HEAD':
        head();
        break;
    default:
        http_response_code(405);
        break;
}

function get(): void
{
    header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . appUrl('/login'));
        exit;
    }
    $eventId = (int)($_GET['event_id'] ?? 0);

    if ($eventId <= 0) {
        header('Location: ' . appUrl('/join_activity'));
        exit;
    }
    renderUserOtpPage($eventId, false);
}


function head(): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        header('X-Checked-In: 0');
        return;
    }

    $eventId = (int)($_GET['event_id'] ?? 0);
    if ($eventId <= 0) {
        http_response_code(400);
        header('X-Checked-In: 0');
        return;
    }

    $userId = (int)$_SESSION['user_id'];
    $conn = getConnection();
    $checkedIn = false;

    $stmt = $conn->prepare("SELECT 1 FROM registrations WHERE event_id = ? AND user_id = ? AND status = 'checked_in' LIMIT 1");
    if ($stmt !== false) {
        $stmt->bind_param('ii', $eventId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $checkedIn = (bool)($result && $result->fetch_assoc());
        $stmt->close();
    }

    $conn->close();
    header('X-Checked-In: ' . ($checkedIn ? '1' : '0'));
    header('Content-Length: 0');
    http_response_code(204);
}

function post(): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . appUrl('/login'));
        exit;
    }

    // โหมดหลัก: รับ event_id จากปุ่ม POST
    $eventId = (int)($_POST['event_id'] ?? 0);

    if ($eventId <= 0) {
        // ไม่ต้องโชว์ error ให้ user งง -> ส่งกลับ
        header('Location: ' . appUrl('/join_activity'));
        exit;
    }

    renderUserOtpPage($eventId, true);
}

/**
 * แสดงหน้า userotp และจัดการสร้าง OTP
 * - OTP อายุ 30 นาที
 * - กดขอซ้ำได้หลัง 3 นาที
 * - ถ้า OTP ยังไม่หมดอายุ จะใช้รหัสเดิม
 * - เก็บ OTP เป็นไฟล์ JSON ใน storage/otp/event{eventId}_user{userId}.json
 * - เช็คสิทธิ์จาก registrations ว่าผู้ใช้เคย request/join event นี้จริง
 */
function renderUserOtpPage(int $eventId, bool $isRequestingNewOtp = false): void
{
    header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    $userId = (int)$_SESSION['user_id'];
    $errors = [];
    $info = [];
    $code = null;
    $record = null;
    $status = '';
    $isPending = false;
    $isCheckedIn = false;
    $isRejected = false;

    $ttlSeconds = 30 * 60;     // 30 นาที
    $cooldownSeconds = 3 * 60; // 3 นาที
    $now = time();

    $conn = getConnection();

    // 1) เช็ค event มีจริง
    $eventStmt = $conn->prepare('SELECT event_id, creator_id, title FROM events WHERE event_id = ? LIMIT 1');
    if ($eventStmt === false) {
        $conn->close();
        renderView('userotp', [
            'errors' => ['ไม่สามารถโหลดกิจกรรมได้'],
            'info' => [],
            'event' => null,
            'event_id' => $eventId,
            'code' => null,
            'record' => null,
            'now' => $now,
            'ttl_seconds' => $ttlSeconds,
            'cooldown_seconds' => $cooldownSeconds,
        ]);
        return;
    }
    $eventStmt->bind_param('i', $eventId);
    $eventStmt->execute();
    $eventRes = $eventStmt->get_result();
    $event = $eventRes ? $eventRes->fetch_assoc() : null;
    $eventStmt->close();

    if (!$event) {
        $conn->close();
        header('Location: ' . appUrl('/join_activity'));
        exit;
    }

    // 2) เช็คว่า user นี้เคย request/join event นี้จริงหรือไม่ (ถ้าไม่เคย -> ไม่ต้องโชว์ error ให้ user งง -> ส่งกลับ)
    $regStmt = $conn->prepare('SELECT status FROM registrations WHERE event_id = ? AND user_id = ? LIMIT 1');
    if ($regStmt === false) {
        $conn->close();
        $errors[] = 'ไม่สามารถตรวจสอบการเข้าร่วมได้';
    } else {
        $regStmt->bind_param('ii', $eventId, $userId);
        $regStmt->execute();
        $regRes = $regStmt->get_result();
        $regRow = $regRes ? $regRes->fetch_assoc() : null;

        if (!$regRow) {
            $regStmt->close();
            $conn->close();
            header('Location: ' . appUrl('/join_activity'));
            exit;
        }

        $status = strtolower(trim((string)($regRow['status'] ?? '')));

        $isPending = ($status === 'pending');
        $isRejected = ($status === 'rejected');
        $isCheckedIn = ($status === 'checked_in');
        $regStmt->close();
    }

    // 3) file storage
    $otpBase = realpath(__DIR__ . '/../storage') ?: (__DIR__ . '/../storage');
    $otpDir = rtrim($otpBase, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'otp';
    if (!is_dir($otpDir)) {
        @mkdir($otpDir, 0775, true);
    }
    $otpFile = $otpDir . DIRECTORY_SEPARATOR . "event{$eventId}_user{$userId}.json";

    if (is_file($otpFile)) {
        $raw = file_get_contents($otpFile);
        $decoded = json_decode((string)$raw, true);
        if (is_array($decoded)) {
            $record = $decoded;
        }
    }

    // 4) ถ้ากดขอ OTP (POST) และไม่มี error -> สร้างตามกฎ cooldown
    if ($isRequestingNewOtp && empty($errors) && !$isCheckedIn && !$isPending && !$isRejected) {
        $shouldGenerate = false;

        if (!$record) {
            $shouldGenerate = true;
        } else {
            $expiresAt = (int)($record['expires_at'] ?? 0);

            // ถ้ายังไม่หมดอายุ ให้ใช้ OTP เดิมต่อ ไม่ต้องสร้างใหม่
            if ($expiresAt > 0 && $now <= $expiresAt) {
                $info[] = 'OTP เดิมยังใช้งานได้อยู่ ระบบจะแสดงรหัสเดิมให้';
                $shouldGenerate = false;
            } else {
                // หมดอายุแล้ว ค่อยสร้างใหม่
                $shouldGenerate = true;
            }
        }

        if ($shouldGenerate) {
            $newCode = (string) random_int(100000, 999999);
            $newRecord = [
                'event_id' => $eventId,
                'user_id' => $userId,
                'code' => $newCode,
                'requested_at' => $now,
                'expires_at' => $now + $ttlSeconds,
            ];

            file_put_contents(
                $otpFile,
                json_encode($newRecord, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                LOCK_EX
            );

            $record = $newRecord;
            $info[] = 'สร้าง OTP ใหม่เรียบร้อย (ใช้ได้ 30 นาที)';
        }
    }

    // 5) แสดง OTP ถ้ายังไม่หมดอายุ
    if (is_array($record)) {
        $expiresAt = (int)($record['expires_at'] ?? 0);
        if ($expiresAt > 0 && $now <= $expiresAt) {
            $code = (string)($record['code'] ?? '');
        } else {
            $info[] = 'OTP หมดอายุแล้ว ให้กดขอใหม่';
            $code = null;
        }
    }

    renderView('userotp', [
        'errors' => $errors,
        'info' => $info,
        'event' => $event,
        'event_id' => $eventId,
        'code' => $code,
        'record' => $record,
        'now' => $now,
        'ttl_seconds' => $ttlSeconds,
        'status' => $status,
        'is_pending' => !empty($isPending),
        'is_rejected' => !empty($isRejected),
        'is_checked_in' => !empty($isCheckedIn),
        'cooldown_seconds' => $cooldownSeconds,
    ]);
}
