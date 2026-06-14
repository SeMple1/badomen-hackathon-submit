<?php

$notifications = $notifications ?? [];
$available = $available ?? false;
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$isEnglish = currentLocale() === 'en';
$totalNotifications = count($notifications);
$unreadCount = 0;
foreach ($notifications as $notification) {
    if (empty($notification['read_at'])) {
        $unreadCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/style/app.css">
    <link rel="stylesheet" href="/style/notifications.css">
    <link rel="stylesheet" href="/style/footer.css?v=2">
    <title><?= $isEnglish ? 'Notifications' : 'การแจ้งเตือน' ?></title>
</head>
<body class="notifications-page">
    <?php require __DIR__ . '/header.php'; ?>
    <main class="site-content-shell notifications-shell">
        <section class="notifications-hero" aria-labelledby="notificationsTitle">
            <div class="notifications-hero__copy">
                <span class="notifications-kicker"><?= $isEnglish ? 'NOTIFICATION CENTER' : 'ศูนย์การแจ้งเตือน' ?></span>
                <h1 id="notificationsTitle"><?= $isEnglish ? 'Notifications' : 'การแจ้งเตือน' ?></h1>
                <p><?= $isEnglish ? 'Fast updates from your events, tickets, payments, and registrations in one focused inbox.' : 'รวมข่าวสารจากกิจกรรม ตั๋ว การชำระเงิน และสถานะการสมัครของคุณไว้ในที่เดียว' ?></p>
            </div>
            <div class="notifications-hero__stats" aria-label="<?= $isEnglish ? 'Notification summary' : 'สรุปการแจ้งเตือน' ?>">
                <div class="notification-stat">
                    <strong><?= number_format($totalNotifications) ?></strong>
                    <span><?= $isEnglish ? 'Total' : 'ทั้งหมด' ?></span>
                </div>
                <div class="notification-stat notification-stat--hot">
                    <strong><?= number_format($unreadCount) ?></strong>
                    <span><?= $isEnglish ? 'Unread' : 'ยังไม่อ่าน' ?></span>
                </div>
            </div>
            <?php if ($available && !empty($notifications)): ?>
                <form method="post" class="notifications-mark-form">
                    <input type="hidden" name="_csrf" value="<?= $escape(csrfToken()) ?>">
                    <button class="notifications-mark-button">
                        <?= $isEnglish ? 'Mark all read' : 'ทำเครื่องหมายว่าอ่านแล้วทั้งหมด' ?>
                    </button>
                </form>
            <?php endif; ?>
        </section>

        <?php if (!$available): ?>
            <div class="notifications-state notifications-state--warning">
                <?= $isEnglish ? 'Apply the platform migration to enable notifications.' : 'กรุณา apply platform migration เพื่อเปิดใช้ระบบแจ้งเตือน' ?>
            </div>
        <?php elseif (empty($notifications)): ?>
            <div class="notifications-state">
                <strong><?= $isEnglish ? 'No notifications yet.' : 'ยังไม่มีการแจ้งเตือน' ?></strong>
                <span><?= $isEnglish ? 'When something changes, it will appear here.' : 'เมื่อมีความเคลื่อนไหวใหม่ ระบบจะแสดงรายการให้ทันที' ?></span>
            </div>
        <?php else: ?>
            <div class="notifications-list" aria-label="<?= $isEnglish ? 'Notification list' : 'รายการแจ้งเตือน' ?>">
                <?php foreach ($notifications as $notification): ?>
                    <?php
                    $title = $isEnglish ? (string)$notification['title_en'] : (string)$notification['title_th'];
                    $body = $isEnglish ? (string)$notification['body_en'] : (string)$notification['body_th'];
                    $unread = empty($notification['read_at']);
                    $type = trim((string)($notification['type'] ?? 'update'));
                    $typeLabel = $type !== '' ? strtoupper(str_replace('_', ' ', $type)) : 'UPDATE';
                    ?>
                    <a href="<?= $escape((string)($notification['action_url'] ?: '/notifications')) ?>"
                       class="notification-card <?= $unread ? 'notification-card--unread' : 'notification-card--read' ?>">
                        <span class="notification-card__rail" aria-hidden="true"></span>
                        <div class="notification-card__main">
                            <div class="notification-card__topline">
                                <span class="notification-card__type"><?= $escape($typeLabel) ?></span>
                                <?php if ($unread): ?><span class="notification-card__unread"><?= $isEnglish ? 'New' : 'ใหม่' ?></span><?php endif; ?>
                            </div>
                            <strong><?= $escape($title) ?></strong>
                            <p><?= $escape($body) ?></p>
                            <time><?= $escape((string)$notification['created_at']) ?></time>
                        </div>
                        <span class="notification-card__arrow" aria-hidden="true">›</span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
    <?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
