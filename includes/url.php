<?php

declare(strict_types=1);

function appBasePath(): string
{
    static $basePath;

    if (is_string($basePath)) {
        return $basePath;
    }

    $configured = trim((string)(getenv('APP_BASE_PATH') ?: ''));
    if ($configured !== '') {
        $basePath = '/' . trim(str_replace('\\', '/', $configured), '/');
        return $basePath === '/' ? '' : $basePath;
    }

    if (PHP_SAPI === 'cli-server') {
        $basePath = '';
        return $basePath;
    }

    $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $directory = str_replace('\\', '/', dirname($scriptName));
    $basePath = $directory === '/' || $directory === '.' ? '' : '/' . trim($directory, '/');

    return $basePath;
}

function appUrl(string $path = '/'): string
{
    $path = trim($path);

    if (
        $path === ''
        || str_starts_with($path, '#')
        || str_starts_with($path, '?')
        || preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//#i', $path)
        || preg_match('#^(?:data|mailto|tel):#i', $path)
    ) {
        return $path;
    }

    $basePath = appBasePath();
    $normalized = '/' . ltrim(preg_replace('#^/(?:\.\./)+#', '/', $path), '/');

    if ($basePath !== '' && ($normalized === $basePath || str_starts_with($normalized, $basePath . '/'))) {
        return $normalized;
    }

    return $basePath . ($normalized === '/' ? '/' : $normalized);
}

function appAbsoluteUrl(string $path = '/'): string
{
    $path = trim($path);
    if ($path !== '' && preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//#i', $path)) {
        return $path;
    }

    $configured = trim((string)(getenv('APP_URL') ?: ''));
    if ($configured !== '') {
        $origin = rtrim($configured, '/');
    } else {
        $host = trim((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
        if ($host !== '' && !str_contains($host, '/') && !str_starts_with($host, '#')) {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
            $origin = ($https ? 'https' : 'http') . '://' . $host;
        } else {
            $origin = 'https://badomen.gonggang.net';
        }
    }

    return $origin . appUrl($path);
}

function stripAppBasePath(string $path): string
{
    $basePath = appBasePath();
    if ($basePath !== '' && ($path === $basePath || str_starts_with($path, $basePath . '/'))) {
        $path = substr($path, strlen($basePath));
    }

    return $path === '' ? '/' : $path;
}

function redirectTo(string $path, int $status = 302): never
{
    header('Location: ' . appUrl($path), true, $status);
    exit;
}

function rewriteAppUrls(string $html): string
{
    $basePath = appBasePath();

    return (string)preg_replace_callback(
        '#([\'"`])/(?!/)([^\'"`\s<>]*)#',
        static function (array $matches) use ($basePath): string {
            $path = '/' . $matches[2];

            if (
                $basePath !== ''
                && ($path === $basePath || str_starts_with($path, $basePath . '/'))
            ) {
                return $matches[0];
            }

            return $matches[1] . appUrl($path);
        },
        $html
    );
}
