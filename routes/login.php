<?php

declare(strict_types=1);

if (!defined('BADOMEN_REMEMBER_COOKIE')) {
    define('BADOMEN_REMEMBER_COOKIE', 'badomen_remember');
}

if (!defined('BADOMEN_LOGIN_EMAIL_COOKIE')) {
    define('BADOMEN_LOGIN_EMAIL_COOKIE', 'badomen_login_email');
}

if (!defined('BADOMEN_REMEMBER_DAYS')) {
    define('BADOMEN_REMEMBER_DAYS', 30);
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        get();
        break;
    case 'POST':
        post();
        break;
    default:
        http_response_code(405);
        echo 'Method Not Allowed';
        break;
}

function get(): void
{
    if (isset($_SESSION['user_id'])) {
        header('Location: ' . appUrl('/home_in'));
        exit;
    }

    $conn = getConnection();
    $rememberedUser = attemptRememberLoginFromCookie($conn);
    if ($rememberedUser !== null) {
        fillLoginSession($rememberedUser);
        $conn->close();
        header('Location: ' . appUrl('/home_in'));
        exit;
    }
    $conn->close();

    $rememberedEmail = trim((string)($_COOKIE[BADOMEN_LOGIN_EMAIL_COOKIE] ?? ''));

    renderView('login', [
        'title' => 'Login',
        'old' => ['email' => $rememberedEmail],
        'rememberChecked' => $rememberedEmail !== '',
    ]);
}

function post(): void
{
    if (!verifyCsrfToken($_POST['_csrf'] ?? null)) {
        renderView('login', [
            'title' => 'Login',
            'errors' => ['เซสชันหมดอายุ กรุณาลองใหม่อีกครั้ง'],
            'rememberChecked' => isset($_POST['remember']) && $_POST['remember'] === '1',
        ]);
        return;
    }

    $old = [
        'email' => trim((string)($_POST['email'] ?? '')),
    ];
    $password = (string)($_POST['password'] ?? '');
    $remember = isset($_POST['remember']) && $_POST['remember'] === '1';
    $errors = [];
    $attempts = $_SESSION['_login_attempts'] ?? ['count' => 0, 'started_at' => time()];
    if (!is_array($attempts) || time() - (int)($attempts['started_at'] ?? 0) > 900) {
        $attempts = ['count' => 0, 'started_at' => time()];
    }

    if ((int)($attempts['count'] ?? 0) >= 8) {
        renderView('login', [
            'title' => 'Login',
            'old' => $old,
            'errors' => ['มีการเข้าสู่ระบบผิดหลายครั้ง กรุณารอ 15 นาทีแล้วลองใหม่'],
            'rememberChecked' => $remember,
        ]);
        return;
    }

    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'รูปแบบอีเมลไม่ถูกต้อง';
    }

    if ($password === '') {
        $errors[] = 'กรุณากรอกรหัสผ่าน';
    }

    if (!empty($errors)) {
        renderView('login', [
            'title' => 'Login',
            'old' => $old,
            'errors' => $errors,
            'rememberChecked' => $remember,
        ]);
        return;
    }

    $conn = getConnection();
    $preferenceColumns = databaseColumnExists($conn, 'users', 'locale') ? ', locale' : '';
    $stmt = $conn->prepare('SELECT user_id, name, email, password' . $preferenceColumns . ' FROM users WHERE email = ? LIMIT 1');

    if ($stmt === false) {
        $conn->close();
        renderView('login', [
            'title' => 'Login',
            'old' => $old,
            'errors' => ['ไม่สามารถเข้าสู่ระบบได้ในขณะนี้'],
            'rememberChecked' => $remember,
        ]);
        return;
    }

    $stmt->bind_param('s', $old['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;

    $stmt->close();

    if (!$user || !password_verify($password, (string)$user['password'])) {
        $conn->close();
        $attempts['count'] = (int)($attempts['count'] ?? 0) + 1;
        $_SESSION['_login_attempts'] = $attempts;
        usleep(min(800000, 120000 * $attempts['count']));
        renderView('login', [
            'title' => 'Login',
            'old' => $old,
            'errors' => ['อีเมลหรือรหัสผ่านไม่ถูกต้อง'],
            'rememberChecked' => $remember,
        ]);
        return;
    }

    session_regenerate_id(true);

    fillLoginSession($user);
    unset($_SESSION['_login_attempts']);

    $guestFavorites = (string)($_POST['guest_favorite_ids'] ?? '');
    importGuestFavorites($conn, (int)$user['user_id'], $guestFavorites);
    if (normalizeGuestFavoriteIds($guestFavorites) !== []) {
        $_SESSION['_clear_guest_favorites'] = true;
    }
    $conn->close();

    if ($remember) {
        issueRememberToken((int)$user['user_id']);
        setLoginEmailCookie((string)$user['email']);
    } else {
        revokeRememberToken();
        clearLoginEmailCookie();
    }

    header('Location: ' . appUrl('/home_in'));
    exit;
}

function fillLoginSession(array $user): void
{
    $_SESSION['user_id'] = (int)$user['user_id'];
    $_SESSION['user_name'] = (string)$user['name'];
    $_SESSION['user_email'] = (string)$user['email'];

    if (
        isset($user['locale'])
        && defined('SUPPORTED_LOCALES')
        && in_array((string)$user['locale'], SUPPORTED_LOCALES, true)
    ) {
        $_SESSION['locale'] = (string)$user['locale'];
    }
}

function attemptRememberLoginFromCookie(mysqli $conn): ?array
{
    $cookieValue = (string)($_COOKIE[BADOMEN_REMEMBER_COOKIE] ?? '');
    if ($cookieValue === '' || !str_contains($cookieValue, ':')) {
        return null;
    }

    [$selector, $validator] = array_pad(explode(':', $cookieValue, 2), 2, '');
    $selector = trim($selector);
    $validator = trim($validator);

    if ($selector === '' || $validator === '') {
        revokeRememberToken();
        return null;
    }

    ensureRememberTokensTable($conn);
    cleanupExpiredRememberTokens($conn);

    $stmt = $conn->prepare(
        'SELECT rt.token_hash, rt.expires_at, u.user_id, u.name, u.email'
        . (databaseColumnExists($conn, 'users', 'locale') ? ', u.locale' : '')
        . ' FROM remember_tokens rt INNER JOIN users u ON u.user_id = rt.user_id WHERE rt.selector = ? LIMIT 1'
    );
    if ($stmt === false) {
        return null;
    }

    $stmt->bind_param('s', $selector);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        revokeRememberToken();
        return null;
    }

    $expiresAt = strtotime((string)$row['expires_at']);
    $tokenHash = hash('sha256', $validator);

    if ($expiresAt === false || $expiresAt < time() || !hash_equals((string)$row['token_hash'], $tokenHash)) {
        revokeRememberToken();
        deleteRememberSelector($conn, $selector);
        return null;
    }

    $lastUsedAt = date('Y-m-d H:i:s');
    $uaHash = hash('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $update = $conn->prepare('UPDATE remember_tokens SET last_used_at = ?, user_agent_hash = ? WHERE selector = ?');
    if ($update !== false) {
        $update->bind_param('sss', $lastUsedAt, $uaHash, $selector);
        $update->execute();
        $update->close();
    }

    return $row;
}

if (!function_exists('issueRememberToken')) {
    function issueRememberToken(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $conn = getConnection();
        ensureRememberTokensTable($conn);
        cleanupExpiredRememberTokens($conn);

        $selector = base64UrlRandom(18);
        $validator = base64UrlRandom(32);
        $tokenHash = hash('sha256', $validator);
        $expiresAt = date('Y-m-d H:i:s', time() + BADOMEN_REMEMBER_DAYS * 86400);
        $uaHash = hash('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));

        $stmt = $conn->prepare(
            'INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at, user_agent_hash) VALUES (?, ?, ?, ?, ?)'
        );
        if ($stmt !== false) {
            $stmt->bind_param('issss', $userId, $selector, $tokenHash, $expiresAt, $uaHash);
            $stmt->execute();
            $stmt->close();
        }
        $conn->close();

        setSecureCookie(BADOMEN_REMEMBER_COOKIE, $selector . ':' . $validator, time() + BADOMEN_REMEMBER_DAYS * 86400, true);
    }
}

if (!function_exists('revokeRememberToken')) {
    function revokeRememberToken(): void
    {
        $cookieValue = (string)($_COOKIE[BADOMEN_REMEMBER_COOKIE] ?? '');
        if ($cookieValue !== '' && str_contains($cookieValue, ':')) {
            [$selector] = explode(':', $cookieValue, 2);
            $selector = trim((string)$selector);
            if ($selector !== '') {
                $conn = getConnection();
                ensureRememberTokensTable($conn);
                deleteRememberSelector($conn, $selector);
                $conn->close();
            }
        }

        setSecureCookie(BADOMEN_REMEMBER_COOKIE, '', time() - 3600, true);
        unset($_COOKIE[BADOMEN_REMEMBER_COOKIE]);
    }
}

function ensureRememberTokensTable(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS remember_tokens (
            token_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            selector CHAR(24) NOT NULL UNIQUE,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_used_at DATETIME NULL,
            user_agent_hash CHAR(64) NULL,
            INDEX idx_remember_user (user_id),
            INDEX idx_remember_expiry (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function cleanupExpiredRememberTokens(mysqli $conn): void
{
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare('DELETE FROM remember_tokens WHERE expires_at < ?');
    if ($stmt === false) {
        return;
    }
    $stmt->bind_param('s', $now);
    $stmt->execute();
    $stmt->close();
}

function deleteRememberSelector(mysqli $conn, string $selector): void
{
    $stmt = $conn->prepare('DELETE FROM remember_tokens WHERE selector = ?');
    if ($stmt === false) {
        return;
    }
    $stmt->bind_param('s', $selector);
    $stmt->execute();
    $stmt->close();
}

function setLoginEmailCookie(string $email): void
{
    setSecureCookie(BADOMEN_LOGIN_EMAIL_COOKIE, $email, time() + BADOMEN_REMEMBER_DAYS * 86400, true);
}

function clearLoginEmailCookie(): void
{
    setSecureCookie(BADOMEN_LOGIN_EMAIL_COOKIE, '', time() - 3600, true);
    unset($_COOKIE[BADOMEN_LOGIN_EMAIL_COOKIE]);
}

function setSecureCookie(string $name, string $value, int $expires, bool $httpOnly): void
{
    setcookie($name, $value, [
        'expires' => $expires,
        'path' => '/',
        'secure' => isHttpsRequest(),
        'httponly' => $httpOnly,
        'samesite' => 'Lax',
    ]);
}

function isHttpsRequest(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (string)($_SERVER['SERVER_PORT'] ?? '') === '443'
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function base64UrlRandom(int $bytes): string
{
    return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
}
