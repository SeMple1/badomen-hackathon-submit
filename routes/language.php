<?php

declare(strict_types=1);

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!verifyCsrfToken($_POST['_csrf'] ?? null)) {
    http_response_code(419);
    exit('Invalid CSRF token');
}

$locale = strtolower(trim((string)($_POST['locale'] ?? 'th')));
setCurrentLocale($locale);

if (isset($_SESSION['user_id'])) {
    $conn = getConnection();
    if (databaseColumnExists($conn, 'users', 'locale')) {
        $stmt = $conn->prepare('UPDATE users SET locale = ? WHERE user_id = ?');
        if ($stmt !== false) {
            $userId = (int)$_SESSION['user_id'];
            $stmt->bind_param('si', $locale, $userId);
            $stmt->execute();
            $stmt->close();
        }
    }
    $conn->close();
}

$returnTo = (string)($_POST['return_to'] ?? '/');
$returnPath = parse_url($returnTo, PHP_URL_PATH);
if (!is_string($returnPath) || !str_starts_with($returnPath, '/')) {
    $returnTo = '/';
}

redirectTo($returnTo);
