<?php

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        get();
        break;
}

function get(): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . appUrl('/login'));
        exit;
    }

    $conn = getConnection();
    $stmt = $conn->prepare(
        'SELECT user_id, name, email, phone, age, gender, career, avatar_path, bio,
                member_rank, vip_started_at, vip_expires_at,
                locale, timezone, notification_email, notification_web, created_at
         FROM users WHERE user_id = ? LIMIT 1'
    );

    if ($stmt === false) {
        $conn->close();
        header('Location: ' . appUrl('/home_in'));
        exit;
    }

    $userId = (int)$_SESSION['user_id'];
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;

    $stmt->close();

    $stats = ['favorites' => 0, 'joined' => 0, 'created' => 0, 'reviews' => 0];
    $statsStmt = $conn->prepare(
        "SELECT
            (SELECT COUNT(*) FROM event_favorites WHERE user_id = ?) AS favorites,
            (SELECT COUNT(*) FROM registrations WHERE user_id = ? AND status IN ('pending','approved','checked_in')) AS joined,
            (SELECT COUNT(*) FROM events WHERE creator_id = ?) AS created,
            (SELECT COUNT(*) FROM event_reviews WHERE user_id = ? AND status = 'published') AS reviews"
    );
    if ($statsStmt !== false) {
        $statsStmt->bind_param('iiii', $userId, $userId, $userId, $userId);
        $statsStmt->execute();
        $stats = $statsStmt->get_result()->fetch_assoc() ?: $stats;
        $statsStmt->close();
    }

    $reviews = [];
    $reviewStmt = $conn->prepare(
        "SELECT er.review_id, er.rating, er.comment, er.created_at, er.updated_at,
                e.event_id, e.title, e.location, e.event_start
         FROM event_reviews er
         INNER JOIN events e ON e.event_id = er.event_id
         WHERE er.user_id = ? AND er.status = 'published'
         ORDER BY er.updated_at DESC, er.review_id DESC"
    );
    if ($reviewStmt !== false) {
        $reviewStmt->bind_param('i', $userId);
        $reviewStmt->execute();
        $reviewResult = $reviewStmt->get_result();
        $reviews = $reviewResult ? $reviewResult->fetch_all(MYSQLI_ASSOC) : [];
        $reviewStmt->close();
    }
    $conn->close();

    if (!$user) {
        header('Location: ' . appUrl('/home_in'));
        exit;
    }

    renderView('profile', [
        'title' => 'Profile',
        'user' => $user,
        'stats' => $stats,
        'reviews' => $reviews,
        'successes' => function_exists('getFlashMessages') ? getFlashMessages('profile_successes') : [],
    ]);
}
