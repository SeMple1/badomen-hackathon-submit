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
    $action = (string)($_POST['vip_action'] ?? 'activate_now');

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

    if ($action === 'begin_payment') {
        beginVipPayment($userId, $plan, $allowedPlans[$plan]);
        return;
    }

    if ($action === 'complete_payment') {
        completeVipPayment($userId, $plan, $allowedPlans[$plan]);
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

function vipJsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function beginVipPayment(int $userId, string $plan, array $planConfig): void
{
    $conn = getConnection();
    $amount = (float)$planConfig['amount'];
    $paymentRef = 'MOCK-GOLD-' . strtoupper(bin2hex(random_bytes(4)));
    $startedAt = date('Y-m-d H:i:s');
    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . (int)$planConfig['days'] . ' days'));
    $status = 'pending';

    try {
        $stmt = $conn->prepare(
            'INSERT INTO vip_memberships (user_id, plan, amount, status, started_at, expires_at, payment_ref)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        if ($stmt === false) {
            throw new RuntimeException('prepare_vip_pending_failed');
        }
        $stmt->bind_param('isdssss', $userId, $plan, $amount, $status, $startedAt, $expiresAt, $paymentRef);
        $stmt->execute();
        $stmt->close();
        $conn->close();
        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok'));

        vipJsonResponse([
            'ok' => true,
            'message' => 'สร้างรายการชำระเงิน Gold VIP แล้ว',
            'payment_ref' => $paymentRef,
            'amount' => $amount,
            'currency' => 'THB',
            'payment_expires_at' => $now->modify('+10 minutes')->format(DateTimeInterface::ATOM),
            'server_now' => $now->format(DateTimeInterface::ATOM),
        ]);
    } catch (Throwable) {
        $conn->close();
        vipJsonResponse(['ok' => false, 'message' => 'ไม่สามารถสร้างรายการชำระเงิน VIP ได้'], 422);
    }
}

function completeVipPayment(int $userId, string $plan, array $planConfig): void
{
    $paymentRef = trim((string)($_POST['payment_ref'] ?? ''));
    $method = strtolower(trim((string)($_POST['payment_method'] ?? 'promptpay')));
    if (!in_array($method, ['promptpay', 'visa', 'mastercard', 'truemoney'], true)) {
        $method = 'promptpay';
    }
    if ($paymentRef === '') {
        vipJsonResponse(['ok' => false, 'message' => 'ไม่พบรายการชำระเงิน VIP'], 422);
    }

    $conn = getConnection();
    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare('SELECT user_id, member_rank, vip_expires_at FROM users WHERE user_id = ? LIMIT 1 FOR UPDATE');
        if ($stmt === false) {
            throw new RuntimeException('prepare_user_failed');
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$user) {
            throw new RuntimeException('user_not_found');
        }

        $orderStmt = $conn->prepare(
            "SELECT payment_ref, amount, status
             FROM vip_memberships
             WHERE user_id = ? AND payment_ref = ? AND plan = ?
             LIMIT 1 FOR UPDATE"
        );
        if ($orderStmt === false) {
            throw new RuntimeException('prepare_order_failed');
        }
        $orderStmt->bind_param('iss', $userId, $paymentRef, $plan);
        $orderStmt->execute();
        $order = $orderStmt->get_result()->fetch_assoc();
        $orderStmt->close();
        if (!$order || (string)($order['status'] ?? '') !== 'pending') {
            throw new RuntimeException('order_not_found');
        }

        $baseTimestamp = time();
        $currentExpires = strtotime((string)($user['vip_expires_at'] ?? ''));
        if ($currentExpires !== false && $currentExpires > $baseTimestamp) {
            $baseTimestamp = $currentExpires;
        }

        $startedAt = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . (int)$planConfig['days'] . ' days', $baseTimestamp));
        $status = 'paid';
        $paidRef = $paymentRef . '-' . strtoupper($method);

        $paidStmt = $conn->prepare(
            'UPDATE vip_memberships
             SET status = ?, started_at = ?, expires_at = ?, payment_ref = ?
             WHERE user_id = ? AND payment_ref = ? AND plan = ? AND status = \'pending\'
             LIMIT 1'
        );
        if ($paidStmt === false) {
            throw new RuntimeException('prepare_paid_failed');
        }
        $paidStmt->bind_param('ssssiss', $status, $startedAt, $expiresAt, $paidRef, $userId, $paymentRef, $plan);
        $paidStmt->execute();
        $paidStmt->close();

        $rank = 'gold';
        $updateStmt = $conn->prepare(
            'UPDATE users SET member_rank = ?, vip_started_at = COALESCE(vip_started_at, ?), vip_expires_at = ? WHERE user_id = ? LIMIT 1'
        );
        if ($updateStmt === false) {
            throw new RuntimeException('prepare_update_failed');
        }
        $updateStmt->bind_param('sssi', $rank, $startedAt, $expiresAt, $userId);
        $updateStmt->execute();
        $updateStmt->close();

        $conn->commit();
        $conn->close();

        $_SESSION['user_rank'] = 'gold';
        vipJsonResponse([
            'ok' => true,
            'message' => 'ชำระเงินสำเร็จ บัญชีของคุณเป็น Gold VIP แล้ว',
            'vip_expires_at' => $expiresAt,
            'payment_ref' => $paidRef,
        ]);
    } catch (Throwable) {
        $conn->rollback();
        $conn->close();
        vipJsonResponse(['ok' => false, 'message' => 'ไม่สามารถยืนยันการชำระเงิน VIP ได้ กรุณาลองใหม่'], 422);
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
