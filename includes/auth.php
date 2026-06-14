<?php

declare(strict_types=1);

const REMEMBER_COOKIE = 'badomen_remember';

function attemptRememberLogin(): void
{
    if (isset($_SESSION['user_id']) || empty($_COOKIE[REMEMBER_COOKIE])) {
        return;
    }

    $parts = explode(':', (string)$_COOKIE[REMEMBER_COOKIE], 2);
    if (count($parts) !== 2 || !ctype_xdigit($parts[0]) || !ctype_xdigit($parts[1])) {
        clearRememberCookie();
        return;
    }

    [$selector, $validator] = $parts;
    $conn = getConnection();
    ensureRememberTokenTable($conn);

    $stmt = $conn->prepare(
        'SELECT t.token_id, t.user_id, t.validator_hash, u.name, u.email
         FROM auth_remember_tokens t
         JOIN users u ON u.user_id = t.user_id
         WHERE t.selector = ? AND t.expires_at >= NOW()
         LIMIT 1'
    );
    $stmt->bind_param('s', $selector);
    $stmt->execute();
    $token = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$token || !hash_equals((string)$token['validator_hash'], hash('sha256', $validator))) {
        $conn->close();
        clearRememberCookie();
        return;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$token['user_id'];
    $_SESSION['user_name'] = (string)$token['name'];
    $_SESSION['user_email'] = (string)$token['email'];

    $delete = $conn->prepare('DELETE FROM auth_remember_tokens WHERE token_id = ?');
    $tokenId = (int)$token['token_id'];
    $delete->bind_param('i', $tokenId);
    $delete->execute();
    $delete->close();
    issueRememberToken((int)$token['user_id'], $conn);
    $conn->close();
}

function issueRememberToken(int $userId, ?mysqli $connection = null): void
{
    $conn = $connection ?? getConnection();
    ensureRememberTokenTable($conn);

    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $validatorHash = hash('sha256', $validator);

    $stmt = $conn->prepare(
        'INSERT INTO auth_remember_tokens (user_id, selector, validator_hash, expires_at)
         VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))'
    );
    $stmt->bind_param('iss', $userId, $selector, $validatorHash);
    $stmt->execute();
    $stmt->close();

    setcookie(REMEMBER_COOKIE, $selector . ':' . $validator, [
        'expires' => time() + (30 * 86400),
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    if ($connection === null) {
        $conn->close();
    }
}

function revokeRememberToken(): void
{
    $cookie = (string)($_COOKIE[REMEMBER_COOKIE] ?? '');
    $parts = explode(':', $cookie, 2);

    if (count($parts) === 2 && ctype_xdigit($parts[0])) {
        $conn = getConnection();
        ensureRememberTokenTable($conn);
        $stmt = $conn->prepare('DELETE FROM auth_remember_tokens WHERE selector = ?');
        $stmt->bind_param('s', $parts[0]);
        $stmt->execute();
        $stmt->close();
        $conn->close();
    }

    clearRememberCookie();
}

function clearRememberCookie(): void
{
    setcookie(REMEMBER_COOKIE, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE[REMEMBER_COOKIE]);
}

function ensureRememberTokenTable(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS auth_remember_tokens (
            token_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            selector CHAR(24) NOT NULL UNIQUE,
            validator_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_remember_user (user_id),
            INDEX idx_remember_expiry (expires_at),
            CONSTRAINT fk_remember_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}
