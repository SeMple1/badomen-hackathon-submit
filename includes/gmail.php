<?php

declare(strict_types=1);

function sendGmailMessage(string $to, string $subject, string $textBody, string $htmlBody): array
{
    if (gmailSmtpConfigured()) {
        return sendGmailSmtpMessage($to, $subject, $textBody, $htmlBody);
    }

    return sendGmailApiMessage($to, $subject, $textBody, $htmlBody);
}

function gmailSmtpConfigured(): bool
{
    return trim((string)getenv('GMAIL_SENDER_EMAIL')) !== ''
        && preg_replace('/\s+/', '', (string)getenv('GMAIL_APP_PASSWORD')) !== '';
}

function sendGmailSmtpMessage(string $to, string $subject, string $textBody, string $htmlBody): array
{
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        return ['ok' => false, 'error' => 'mailer_dependency_missing'];
    }

    require_once $autoload;

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        configureGmailSmtpMailer($mail);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody;
        $mail->send();

        return ['ok' => true, 'message_id' => $mail->getLastMessageID()];
    } catch (Throwable $error) {
        error_log('Gmail SMTP send failed: ' . get_class($error));
        return ['ok' => false, 'error' => 'gmail_send_failed'];
    }
}

function verifyGmailSmtpConnection(): array
{
    if (!gmailSmtpConfigured()) {
        return ['ok' => false, 'error' => 'gmail_not_configured'];
    }

    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        return ['ok' => false, 'error' => 'mailer_dependency_missing'];
    }

    require_once $autoload;
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        configureGmailSmtpMailer($mail);
        $connected = $mail->smtpConnect();
        $mail->smtpClose();

        return $connected
            ? ['ok' => true]
            : ['ok' => false, 'error' => 'gmail_auth_failed'];
    } catch (Throwable $error) {
        return ['ok' => false, 'error' => 'gmail_auth_failed'];
    }
}

function configureGmailSmtpMailer(PHPMailer\PHPMailer\PHPMailer $mail): void
{
    $senderEmail = trim((string)getenv('GMAIL_SENDER_EMAIL'));
    $senderName = trim((string)(getenv('GMAIL_SENDER_NAME') ?: 'Badomen'));
    $appPassword = preg_replace('/\s+/', '', (string)getenv('GMAIL_APP_PASSWORD'));

    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->Port = 587;
    $mail->SMTPAuth = true;
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Username = $senderEmail;
    $mail->Password = $appPassword;
    $mail->CharSet = PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
    $mail->Timeout = 15;
    $mail->SMTPKeepAlive = false;
    $mail->setFrom($senderEmail, $senderName);
}

function sendGmailApiMessage(string $to, string $subject, string $textBody, string $htmlBody): array
{
    $clientId = trim((string)getenv('GMAIL_CLIENT_ID'));
    $clientSecret = trim((string)getenv('GMAIL_CLIENT_SECRET'));
    $refreshToken = trim((string)getenv('GMAIL_REFRESH_TOKEN'));
    $senderEmail = trim((string)getenv('GMAIL_SENDER_EMAIL'));
    $senderName = trim((string)(getenv('GMAIL_SENDER_NAME') ?: 'Badomen'));

    if ($clientId === '' || $clientSecret === '' || $refreshToken === '' || $senderEmail === '') {
        return ['ok' => false, 'error' => 'gmail_not_configured'];
    }

    $tokenResponse = gmailHttpRequest(
        'https://oauth2.googleapis.com/token',
        [
            'Content-Type: application/x-www-form-urlencoded',
        ],
        http_build_query([
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ])
    );

    $accessToken = (string)($tokenResponse['json']['access_token'] ?? '');
    if (!$tokenResponse['ok'] || $accessToken === '') {
        return ['ok' => false, 'error' => 'gmail_token_failed'];
    }

    $boundary = 'badomen_' . bin2hex(random_bytes(12));
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $encodedSenderName = '=?UTF-8?B?' . base64_encode($senderName) . '?=';

    $mime = implode("\r\n", [
        'From: ' . $encodedSenderName . ' <' . $senderEmail . '>',
        'To: ' . $to,
        'Subject: ' . $encodedSubject,
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        '',
        '--' . $boundary,
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
        '',
        chunk_split(base64_encode($textBody), 76, "\r\n"),
        '--' . $boundary,
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
        '',
        chunk_split(base64_encode($htmlBody), 76, "\r\n"),
        '--' . $boundary . '--',
    ]);

    $raw = rtrim(strtr(base64_encode($mime), '+/', '-_'), '=');
    $sendResponse = gmailHttpRequest(
        'https://gmail.googleapis.com/gmail/v1/users/me/messages/send',
        [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ],
        json_encode(['raw' => $raw], JSON_UNESCAPED_SLASHES)
    );

    if (!$sendResponse['ok'] || empty($sendResponse['json']['id'])) {
        return ['ok' => false, 'error' => 'gmail_send_failed'];
    }

    return ['ok' => true, 'message_id' => (string)$sendResponse['json']['id']];
}

function gmailHttpRequest(string $url, array $headers, string $body): array
{
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 15,
    ]);

    $raw = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    $json = is_string($raw) ? json_decode($raw, true) : null;

    return [
        'ok' => $curlError === '' && $status >= 200 && $status < 300,
        'status' => $status,
        'json' => is_array($json) ? $json : [],
    ];
}
