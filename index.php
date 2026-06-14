<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Bangkok');

const INCLUDES_DIR = __DIR__ . '/includes';
const ROUTE_DIR = __DIR__ . '/routes';
const TEMPLATES_DIR = __DIR__ . '/templates';
const DATABASES_DIR = __DIR__ . '/databases';

require_once INCLUDES_DIR . '/config.php';
require_once INCLUDES_DIR . '/url.php';

// The PHP development server needs help serving assets when APP_BASE_PATH is set.
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (PHP_SAPI === 'cli-server' && is_string($requestPath)) {
    $staticPath = stripAppBasePath($requestPath);
    $publicFile = realpath(__DIR__ . $staticPath);
    $projectRoot = realpath(__DIR__);
    $extension = strtolower(pathinfo($publicFile ?: '', PATHINFO_EXTENSION));
    $staticExtensions = [
        'css', 'js', 'map', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg',
        'ico', 'woff', 'woff2', 'ttf', 'html',
    ];

    if (
        $publicFile !== false
        && $projectRoot !== false
        && str_starts_with($publicFile, $projectRoot . DIRECTORY_SEPARATOR)
        && is_file($publicFile)
        && in_array($extension, $staticExtensions, true)
    ) {
        if (appBasePath() === '') {
            return false;
        }

        $mimeTypes = [
            'css' => 'text/css; charset=UTF-8',
            'js' => 'text/javascript; charset=UTF-8',
            'map' => 'application/json',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'html' => 'text/html; charset=UTF-8',
        ];

        header('Content-Type: ' . ($mimeTypes[$extension] ?? 'application/octet-stream'));
        header('Content-Length: ' . (string)filesize($publicFile));
        readfile($publicFile);
        exit;
    }
}

session_start();

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once INCLUDES_DIR . '/router.php';
require_once INCLUDES_DIR . '/view.php';
require_once INCLUDES_DIR . '/database.php';
require_once INCLUDES_DIR . '/schema.php';
require_once INCLUDES_DIR . '/security.php';
require_once INCLUDES_DIR . '/i18n.php';
require_once INCLUDES_DIR . '/gmail.php';
require_once INCLUDES_DIR . '/gemini.php';
require_once INCLUDES_DIR . '/notifications.php';
require_once INCLUDES_DIR . '/events.php';
require_once INCLUDES_DIR . '/vip.php';
require_once INCLUDES_DIR . '/auth.php';

set_exception_handler(static function (Throwable $error): void {
    if (!$error instanceof DatabaseConnectionException) {
        throw $error;
    }

    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    header('Retry-After: 3');
    echo '<!doctype html><html lang="th"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>Database unavailable</title><body style="margin:0;font-family:Segoe UI,Arial,sans-serif;background:#fff7ed;color:#431407">';
    echo '<main style="max-width:680px;margin:12vh auto;padding:32px;border:1px solid #fed7aa;border-radius:24px;background:#fff;box-shadow:0 20px 50px rgba(67,20,7,.12)">';
    echo '<h1 style="margin-top:0">เชื่อมต่อฐานข้อมูลไม่ได้</h1>';
    echo '<p>กรุณาตรวจสอบค่า <code>DB_HOST</code>, <code>DB_NAME</code>, <code>DB_USER</code> และ <code>DB_PASSWORD</code> ในไฟล์ <code>.env.local</code> แล้วลองใหม่</p>';
    echo '<p style="color:#9a3412">ระบบซ่อนรายละเอียดการเชื่อมต่อเพื่อความปลอดภัยแล้ว</p>';
    echo '<div style="display:flex;align-items:center;gap:12px;margin-top:22px">';
    echo '<button type="button" onclick="location.reload()" style="border:0;border-radius:14px;padding:11px 16px;background:#ea580c;color:#fff;font-weight:800;cursor:pointer">ลองเชื่อมต่ออีกครั้ง</button>';
    echo '<span>รีเฟรชอัตโนมัติใน <strong id="dbRetryCountdown">3</strong> วินาที</span></div>';
    echo '<script>let s=3;const e=document.getElementById("dbRetryCountdown");const t=setInterval(()=>{s-=1;if(e)e.textContent=String(Math.max(0,s));if(s<=0){clearInterval(t);location.reload();}},1000);</script>';
    echo '</main></body></html>';
    exit;
});

attemptRememberLogin();
dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
