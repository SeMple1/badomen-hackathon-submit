<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/database.php';
require_once dirname(__DIR__) . '/includes/schema.php';
require_once dirname(__DIR__) . '/includes/gmail.php';
require_once dirname(__DIR__) . '/includes/url.php';

$conn = getConnection();
if (!databaseTableExists($conn, 'notification_deliveries')) {
    fwrite(STDOUT, "Notification migration has not been applied.\n");
    $conn->close();
    exit(0);
}

$result = $conn->query(
    "SELECT d.delivery_id, d.recipient, n.title_th, n.title_en, n.body_th, n.body_en, n.action_url, u.locale
     FROM notification_deliveries d
     JOIN notifications n ON n.notification_id = d.notification_id
     JOIN users u ON u.user_id = n.user_id
     WHERE d.channel = 'email'
       AND d.status IN ('queued','failed')
       AND (d.next_attempt_at IS NULL OR d.next_attempt_at <= NOW())
       AND d.attempt_count < 5
     ORDER BY d.created_at ASC
     LIMIT 25"
);
$jobs = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

$update = $conn->prepare(
    "UPDATE notification_deliveries
     SET status = ?, attempt_count = attempt_count + 1, provider_message_id = ?,
         sent_at = ?, last_error_code = ?, next_attempt_at = ?
     WHERE delivery_id = ?"
);

$sent = 0;
$failed = 0;
foreach ($jobs as $job) {
    $locale = (string)($job['locale'] ?? 'th');
    $subject = $locale === 'en' ? (string)$job['title_en'] : (string)$job['title_th'];
    $body = $locale === 'en' ? (string)$job['body_en'] : (string)$job['body_th'];
    $actionUrl = trim((string)($job['action_url'] ?? ''));
    $absoluteActionUrl = $actionUrl !== '' ? appAbsoluteUrl($actionUrl) : '';
    $textBody = $body . ($absoluteActionUrl !== '' ? "\n\n" . $absoluteActionUrl : '');
    $htmlBody = '<div style="font-family:Arial,sans-serif;line-height:1.7">'
        . nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));
    if ($absoluteActionUrl !== '') {
        $safeActionUrl = htmlspecialchars($absoluteActionUrl, ENT_QUOTES, 'UTF-8');
        $htmlBody .= '<p style="margin:18px 0 0"><a href="' . $safeActionUrl . '" style="display:inline-block;padding:12px 16px;border-radius:12px;background:#2563eb;color:#ffffff;text-decoration:none;font-weight:800">Open event</a></p>'
            . '<p style="margin:10px 0 0;color:#64748b;font-size:12px">' . $safeActionUrl . '</p>';
    }
    $htmlBody .= '</div>';
    $mail = sendGmailMessage(
        (string)$job['recipient'],
        $subject,
        $textBody,
        $htmlBody
    );

    $status = $mail['ok'] ? 'sent' : 'failed';
    $messageId = $mail['ok'] ? (string)($mail['message_id'] ?? '') : null;
    $sentAt = $mail['ok'] ? date('Y-m-d H:i:s') : null;
    $errorCode = $mail['ok'] ? null : (string)($mail['error'] ?? 'gmail_send_failed');
    $nextAttempt = $mail['ok'] ? null : date('Y-m-d H:i:s', time() + 300);
    $deliveryId = (int)$job['delivery_id'];
    $update->bind_param('sssssi', $status, $messageId, $sentAt, $errorCode, $nextAttempt, $deliveryId);
    $update->execute();

    $mail['ok'] ? $sent++ : $failed++;
}

$update->close();
$conn->close();
fwrite(STDOUT, "Processed " . count($jobs) . " email notifications: {$sent} sent, {$failed} failed.\n");
