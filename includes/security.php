<?php

declare(strict_types=1);

function csrfToken(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string)$_SESSION['_csrf_token'];
}

function verifyCsrfToken(?string $token): bool
{
    return is_string($token)
        && $token !== ''
        && hash_equals(csrfToken(), $token);
}

function requestIpHash(): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $secret = getenv('APP_KEY') ?: 'badomen-local-development-key';

    return hash_hmac('sha256', $ip, $secret);
}

function jsonResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
