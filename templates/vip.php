<?php
$user = $user ?? [];
$errors = is_array($errors ?? null) ? $errors : [];
$successes = is_array($successes ?? null) ? $successes : [];
$escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$rank = (string)($user['member_rank'] ?? 'member');
$isGold = $rank === 'gold' && (empty($user['vip_expires_at']) || strtotime((string)$user['vip_expires_at']) >= time());
$expiresAt = trim((string)($user['vip_expires_at'] ?? ''));
$expiresDisplay = '-';
if ($expiresAt !== '') {
    $ts = strtotime($expiresAt);
    if ($ts !== false) {
        $expiresDisplay = date('d/m/Y H:i', $ts);
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gold VIP | Badomen</title>
    <link rel="stylesheet" href="/style/app.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet"></noscript>
    <style>
        body{margin:0;min-height:100vh;background:radial-gradient(circle at 12% 16%,rgba(245,158,11,.12),transparent 28%),linear-gradient(180deg,#fff,#fff8f1);font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,"Noto Sans Thai",sans-serif;color:#111827}.vip-page{width:min(980px,calc(100% - 40px));margin:0 auto;padding:54px 0 70px}.vip-card{overflow:hidden;border-radius:34px;background:#fff;border:1px solid rgba(245,158,11,.32);box-shadow:0 28px 76px rgba(146,64,14,.16)}.vip-hero{padding:42px;background:linear-gradient(135deg,#1a0c02,#432006 50%,#f59e0b 150%);color:#fff}.vip-badge{width:fit-content;display:inline-flex;align-items:center;gap:8px;padding:9px 14px;border-radius:999px;background:rgba(245,158,11,.18);border:1px solid rgba(251,191,36,.42);font-size:13px;font-weight:950}.vip-badge i{color:#fbbf24}.vip-hero h1{margin:22px 0 10px;font-size:clamp(34px,5vw,58px);line-height:1.05;letter-spacing:-.055em}.vip-hero p{max-width:670px;margin:0;color:rgba(255,255,255,.76);line-height:1.75;font-weight:700}.vip-body{padding:32px}.vip-status{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:22px}.vip-status div{padding:18px;border-radius:22px;background:#fff7ed;border:1px solid rgba(245,158,11,.18)}.vip-status span{display:block;color:#6b7280;font-size:12px;font-weight:850}.vip-status strong{display:block;margin-top:6px;font-size:18px;font-weight:950}.vip-benefits{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.vip-benefit{padding:18px;border-radius:22px;border:1px solid rgba(17,24,39,.10);background:#fff}.vip-benefit i{width:42px;height:42px;display:inline-flex;align-items:center;justify-content:center;border-radius:15px;color:#92400e;background:rgba(245,158,11,.15);font-size:22px}.vip-benefit strong{display:block;margin-top:12px;font-size:16px}.vip-benefit span{display:block;margin-top:6px;color:#6b7280;line-height:1.6;font-size:13px}.vip-actions{display:flex;gap:12px;margin-top:26px;flex-wrap:wrap}.vip-btn{height:50px;padding:0 20px;border:0;border-radius:16px;display:inline-flex;align-items:center;justify-content:center;gap:9px;text-decoration:none;font:inherit;font-weight:950;cursor:pointer}.vip-btn-primary{color:#1a0c02;background:linear-gradient(90deg,#fbbf24,#f59e0b);box-shadow:0 14px 30px rgba(245,158,11,.28)}.vip-btn-dark{color:#fff;background:#111827}.vip-alert{margin-bottom:16px;padding:14px 16px;border-radius:18px;font-weight:800}.vip-alert-error{color:#b42318;background:#fff1f1;border:1px solid rgba(239,68,68,.18)}.vip-alert-success{color:#067647;background:#ecfdf3;border:1px solid #b7f0cf}@media(max-width:720px){.vip-status,.vip-benefits{grid-template-columns:1fr}.vip-hero,.vip-body{padding:24px}.vip-btn{width:100%}}
    </style>
</head>
<body>
<?php require __DIR__ . '/header.php'; ?>
<main class="vip-page">
    <section class="vip-card">
        <div class="vip-hero">
            <div class="vip-badge"><i class='bx bxs-crown'></i><span>Gold VIP Membership</span></div>
            <h1>อัปเกรดบัญชีเป็น Gold VIP</h1>
            <p>ระบบนี้เป็น mock-up สำหรับงาน hackathon: กดสมัครแล้วระบบจะบันทึก order, อัปเดตยศเป็น gold และขยายอายุสมาชิก 30 วันทันที</p>
        </div>
        <div class="vip-body">
            <?php if (!empty($errors)): ?>
                <div class="vip-alert vip-alert-error"><?= $escape(implode(' ', $errors)) ?></div>
            <?php endif; ?>
            <?php if (!empty($successes)): ?>
                <div class="vip-alert vip-alert-success"><?= $escape(implode(' ', $successes)) ?></div>
            <?php endif; ?>

            <div class="vip-status">
                <div><span>ผู้ใช้</span><strong><?= $escape((string)($user['name'] ?? '-')) ?></strong></div>
                <div><span>ยศปัจจุบัน</span><strong><?= $isGold ? 'Gold VIP' : 'Member' ?></strong></div>
                <div><span>หมดอายุ</span><strong><?= $escape($isGold ? $expiresDisplay : '-') ?></strong></div>
            </div>

            <div class="vip-benefits">
                <div class="vip-benefit"><i class='bx bxs-crown'></i><strong>Gold border บนโปรไฟล์</strong><span>หน้าโปรไฟล์จะแสดงกรอบและ badge สีทองสำหรับผู้ใช้ Gold</span></div>
                <div class="vip-benefit"><i class='bx bx-image'></i><strong>โปรไฟล์ดูเด่นขึ้น</strong><span>รองรับรูป avatar จริงและแสดงผลร่วมกับยศ Gold</span></div>
                <div class="vip-benefit"><i class='bx bx-star'></i><strong>เตรียมต่อยอดสิทธิพิเศษ</strong><span>สามารถนำ member_rank ไปเช็คสิทธิพิเศษในหน้าอื่นต่อได้</span></div>
                <div class="vip-benefit"><i class='bx bx-receipt'></i><strong>มีประวัติ order</strong><span>ทุกครั้งที่สมัครจะถูกเก็บในตาราง vip_memberships</span></div>
            </div>

            <div class="vip-actions">
                <form method="POST" action="/vip">
                    <input type="hidden" name="_csrf" value="<?= $escape(csrfToken()) ?>">
                    <input type="hidden" name="plan" value="gold_monthly">
                    <button class="vip-btn vip-btn-primary" type="submit"><i class='bx bxs-crown'></i><span><?= $isGold ? 'ต่ออายุ Gold VIP 99 บาท' : 'สมัคร Gold VIP 99 บาท / 30 วัน' ?></span></button>
                </form>
                <a class="vip-btn vip-btn-dark" href="/profile"><i class='bx bx-left-arrow-alt'></i><span>กลับโปรไฟล์</span></a>
            </div>
        </div>
    </section>
</main>
</body>
</html>
