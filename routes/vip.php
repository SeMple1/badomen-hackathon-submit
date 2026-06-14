<?php

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        get();
        break;
    case 'POST':
        post();
        break;
    default:
        http_response_code(405);
        exit('Method Not Allowed');
}

function get(): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . appUrl('/login'));
        exit;
    }

    $user = fetchVipUser((int)$_SESSION['user_id']);
    if (!$user) {
        header('Location: ' . appUrl('/home_in'));
        exit;
    }

    renderView('vip', [
        'title' => 'Gold VIP',
        'user' => $user,
        'errors' => [],
        'successes' => function_exists('getFlashMessages') ? getFlashMessages('vip_successes') : [],
    ]);
}

function post(): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . appUrl('/login'));
        exit;
    }

    if (!verifyCsrfToken($_POST['_csrf'] ?? null)) {
        header('Location: ' . appUrl('/vip'));
        exit;
    }

    $userId = (int)$_SESSION['user_id'];
    $plan = (string)($_POST['plan'] ?? 'gold_monthly');
    $allowedPlans = ['gold_monthly' => ['days' => 30, 'amount' => 99.00]];

    if (!isset($allowedPlans[$plan])) {
        $user = fetchVipUser($userId);
        renderView('vip', [
            'title' => 'Gold VIP',
            'user' => $user,
            'errors' => ['แพ็กเกจ VIP ไม่ถูกต้อง'],
            'successes' => [],
        ]);
        return;
    }

    $conn = getConnection();
    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare('SELECT user_id, name, email, member_rank, vip_expires_at FROM users WHERE user_id = ? LIMIT 1 FOR UPDATE');
        if ($stmt === false) {
            throw new RuntimeException('prepare user failed');
        }

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            throw new RuntimeException('user not found');
        }

        $baseTimestamp = time();
        $currentExpires = strtotime((string)($user['vip_expires_at'] ?? ''));
        if ($currentExpires !== false && $currentExpires > $baseTimestamp) {
            $baseTimestamp = $currentExpires;
        }

        $startedAt = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . (int)$allowedPlans[$plan]['days'] . ' days', $baseTimestamp));
        $amount = (float)$allowedPlans[$plan]['amount'];
        $paymentRef = 'MOCK-GOLD-' . strtoupper(bin2hex(random_bytes(4)));
        $status = 'paid';

        $orderStmt = $conn->prepare(
            'INSERT INTO vip_memberships (user_id, plan, amount, status, started_at, expires_at, payment_ref)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        if ($orderStmt === false) {
            throw new RuntimeException('prepare vip failed');
        }
        $orderStmt->bind_param('isdssss', $userId, $plan, $amount, $status, $startedAt, $expiresAt, $paymentRef);
        $orderStmt->execute();
        $orderStmt->close();

        $rank = 'gold';
        $updateStmt = $conn->prepare(
            'UPDATE users SET member_rank = ?, vip_started_at = COALESCE(vip_started_at, ?), vip_expires_at = ? WHERE user_id = ? LIMIT 1'
        );
        if ($updateStmt === false) {
            throw new RuntimeException('prepare update failed');
        }
        $updateStmt->bind_param('sssi', $rank, $startedAt, $expiresAt, $userId);
        $updateStmt->execute();
        $updateStmt->close();

        $conn->commit();
        $conn->close();

        $_SESSION['user_rank'] = 'gold';
        if (function_exists('addFlashMessage')) {
            addFlashMessage('vip_successes', 'สมัคร Gold VIP สำเร็จแล้ว');
            addFlashMessage('profile_successes', 'บัญชีของคุณอัปเกรดเป็น Gold VIP แล้ว');
        }

        header('Location: ' . appUrl('/profile'));
        exit;
    } catch (Throwable $e) {
        $conn->rollback();
        $conn->close();

        $user = fetchVipUser($userId);
        renderView('vip', [
            'title' => 'Gold VIP',
            'user' => $user,
            'errors' => ['ไม่สามารถสมัคร VIP ได้ กรุณาลองใหม่อีกครั้ง'],
            'successes' => [],
        ]);
    }
}

function fetchVipUser(int $userId): ?array
{
    $conn = getConnection();
    $stmt = $conn->prepare(
        'SELECT user_id, name, email, member_rank, vip_started_at, vip_expires_at, created_at
         FROM users WHERE user_id = ? LIMIT 1'
    );

    if ($stmt === false) {
        $conn->close();
        return null;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    $conn->close();

    return $user;
}
