<?php

declare(strict_types=1);

const BADOMEN_VIP_DISCOUNT_PER_TICKET = 59.0;

function badomenVipDiscountPerTicket(): float
{
    return BADOMEN_VIP_DISCOUNT_PER_TICKET;
}

function badomenIsGoldVipRecord(array $user): bool
{
    $rank = strtolower(trim((string)($user['member_rank'] ?? '')));
    if ($rank !== 'gold') {
        return false;
    }

    $expiresAt = trim((string)($user['vip_expires_at'] ?? ''));
    return $expiresAt === '' || strtotime($expiresAt) >= time();
}

function badomenFetchVipProfile(mysqli $conn, int $userId): array
{
    if ($userId <= 0 || !databaseTableExists($conn, 'users')) {
        return ['is_vip' => false, 'avatar_path' => '', 'member_rank' => 'member', 'vip_expires_at' => ''];
    }

    $columns = [];
    if (databaseColumnExists($conn, 'users', 'member_rank')) {
        $columns[] = 'member_rank';
    }
    if (databaseColumnExists($conn, 'users', 'vip_expires_at')) {
        $columns[] = 'vip_expires_at';
    }
    if (databaseColumnExists($conn, 'users', 'avatar_path')) {
        $columns[] = 'avatar_path';
    }
    if (empty($columns)) {
        return ['is_vip' => false, 'avatar_path' => '', 'member_rank' => 'member', 'vip_expires_at' => ''];
    }

    $stmt = $conn->prepare('SELECT ' . implode(', ', $columns) . ' FROM users WHERE user_id = ? LIMIT 1');
    if ($stmt === false) {
        return ['is_vip' => false, 'avatar_path' => '', 'member_rank' => 'member', 'vip_expires_at' => ''];
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    return [
        'is_vip' => badomenIsGoldVipRecord($user),
        'avatar_path' => (string)($user['avatar_path'] ?? ''),
        'member_rank' => (string)($user['member_rank'] ?? 'member'),
        'vip_expires_at' => (string)($user['vip_expires_at'] ?? ''),
    ];
}

function badomenIsVipUser(mysqli $conn, int $userId): bool
{
    return (bool)badomenFetchVipProfile($conn, $userId)['is_vip'];
}

function badomenVipDiscountAmount(float $grossAmount, int $quantity, bool $isVip): float
{
    if (!$isVip || $grossAmount <= 0 || $quantity <= 0) {
        return 0.0;
    }

    return min($grossAmount, badomenVipDiscountPerTicket() * $quantity);
}

function badomenApplyVipDiscount(float $grossAmount, int $quantity, bool $isVip): array
{
    $discount = badomenVipDiscountAmount($grossAmount, $quantity, $isVip);
    $finalAmount = max(0.0, $grossAmount - $discount);

    return [
        'gross_amount' => $grossAmount,
        'discount_amount' => $discount,
        'final_amount' => $finalAmount,
        'unit_amount' => $quantity > 0 ? $finalAmount / $quantity : 0.0,
    ];
}
