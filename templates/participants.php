<?php
$participants = $participants ?? [];
$errors = $errors ?? [];
$successes = $successes ?? [];
$statusColumnAvailable = (bool)($status_column_available ?? false);
$selectedEventTitle = trim((string)($selected_event_title ?? ''));

$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$careerLabels = [
    'student' => 'นักเรียน/นักศึกษา',
    'employee' => 'พนักงานบริษัท',
    'business_owner' => 'เจ้าของธุรกิจ',
    'freelancer' => 'ฟรีแลนซ์',
    'government_officer' => 'ข้าราชการ',
    'other' => 'อื่น ๆ',
];
$statusBadge = static function (string $status): array {
    $normalized = strtolower(trim($status));
    if (in_array($normalized, ['approved', 'approve', 'accepted', 'confirmed'], true)) {
        return ['อนุมัติแล้ว', 'status-badge--approved'];
    }
    if (in_array($normalized, ['rejected', 'reject', 'declined', 'denied'], true)) {
        return ['ปฏิเสธแล้ว', 'status-badge--rejected'];
    }
    return ['รออนุมัติ', 'status-badge--pending'];
};

//เพิ่มโค๊ดนี้ เพื่อจับการเปลี่ยนแปลง คล้ายโค๊ดด้านบน 
$statusNormalize = static function (string $status): string {
    $s = strtolower(trim($status));
    if (in_array($s, ['approved','approve','accepted','confirmed'], true)) return 'approved';
    if (in_array($s, ['rejected','reject','declined','denied'], true)) return 'rejected';
    return 'pending';
};
//สิ้นสด

?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/style/app.css">
    <link rel="stylesheet" href="/style/participants.css">
    <title>รายชื่อผู้เข้าร่วม</title>
</head>

<body class="native-participants-001">

    <?php require __DIR__ . '/header.php'; ?>

    <main class="site-content-shell native-participants-002">
        <div class="native-participants-003">
            <div>
                <h1 class="native-participants-004">จัดการผู้เข้าร่วม</h1>
                <p class="native-participants-005">อนุมัติหรือปฏิเสธคำขอเข้าร่วมกิจกรรมที่คุณสร้าง</p>
                <?php if ($selectedEventTitle !== ''): ?>
                    <p class="native-participants-006">กิจกรรม: <?= $escape($selectedEventTitle) ?></p>
                <?php endif; ?>
            </div>
            <a href="/dashboard" class="native-participants-007">
                < กลับ
                    </a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="native-participants-008">
                <ul class="native-participants-009">
                    <?php foreach ($errors as $error): ?>
                        <li><?= $escape((string)$error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($successes)): ?>
            <div class="native-participants-010">
                <ul class="native-participants-009">
                    <?php foreach ($successes as $success): ?>
                        <li><?= $escape((string)$success) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!$statusColumnAvailable): ?>
            <div class="native-participants-011">
                ยังไม่พบคอลัมน์สถานะในตาราง <code>registrations</code> จึงยังอนุมัติ/ปฏิเสธไม่ได้
            </div>
        <?php endif; ?>

        <?php if (empty($participants)): ?>
            <div class="native-participants-012">
                ยังไม่มีผู้ขอเข้าร่วมในกิจกรรมนี้
            </div>
        <?php else: ?>
            <div class="native-participants-013">
                <table class="divide-y divide-slate-200 native-participants-014">
                    <thead class="native-participants-015">
                        <tr>
                            <th class="native-participants-016">ผู้เข้าร่วม</th>
                            <th class="native-participants-016">อายุ</th>
                            <th class="native-participants-016">อาชีพ</th>
                            <th class="native-participants-016">สถานะ</th>
                            <th class="native-participants-016">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <?php foreach ($participants as $row): ?>
                            <?php
                            $careerCode = (string)($row['participant_career'] ?? '');
                            $careerText = $careerLabels[$careerCode] ?? $careerCode;
                            /*
                            ===== แก้ไขส่วนนี้เพื่อให้แสดงผล เมื่อผ่าน otp แล้วได้
                            */
                            $statusRaw = (string)($row['registration_status'] ?? 'pending');
                            $statusNorm = $statusNormalize($statusRaw);

                            [$statusText, $statusClass] = $statusBadge($statusRaw);

                            $isApproved = ($statusNorm === 'approved');
                            $isRejected = ($statusNorm === 'rejected');
                            $isPending  = ($statusNorm === 'pending');

                            /* แก้ไขสิ้่นสุด */
                            ?>
                            <tr class="native-participants-017">
                                <td class="native-participants-018">
                                    <?= $escape((string)($row['participant_name'] ?? '-')) ?>
                                </td>

                                <td class="native-participants-019">
                                    <?= (int)($row['participant_age'] ?? 0) ?>
                                </td>

                                <td class="native-participants-019">
                                    <?= $escape($careerText !== '' ? $careerText : '-') ?>
                                </td>

                                <td class="native-participants-020">
                                    <span class="<?= $statusClass ?> native-participants-021">
                                        <?= $statusText ?>
                                    </span>
                                </td>
                                <!--   
                                    == แก้ไขส่วนนี้ เพื่อทำให้ปุ่ม ยืนยัน ปฏิเสธ แสดงผลถูกต้อง
                                -->
                                <td class="native-participants-020">
                                    <?php if ($statusColumnAvailable && $isPending): ?>
                                        <div class="native-participants-022">
                                            <form method="POST" onsubmit="return confirmAcppect()">
                                                <input type="hidden" name="event_id" value="<?= (int)($row['event_id'] ?? 0) ?>">
                                                <input type="hidden" name="participant_user_id" value="<?= (int)($row['participant_user_id'] ?? 0) ?>">
                                                <input type="hidden" name="decision" value="approve">
                                                <button
                                                    type="submit"
                                                    class="native-participants-023">
                                                    อนุมัติ
                                                </button>
                                            </form>

                                            <form method="POST" onsubmit="return confirmReject()">
                                                <input type="hidden" name="event_id" value="<?= (int)($row['event_id'] ?? 0) ?>">
                                                <input type="hidden" name="participant_user_id" value="<?= (int)($row['participant_user_id'] ?? 0) ?>">
                                                <input type="hidden" name="decision" value="reject">
                                                <button
                                                    type="submit"
                                                    class="native-participants-024">
                                                    ปฏิเสธ
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span class="native-participants-025">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <!-- แก้ไขสิ้นสุด -->
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>

    <script>
        function confirmAcppect() {
            return confirm("ต้องการอนุมัติจริงหรือไม่ ?");
        }
    </script>


    <script>
        function confirmReject() {
            return confirm("ต้องการปฏิเสธจริงหรือไม่ ?");
        }
    </script>

</body>

</html>
