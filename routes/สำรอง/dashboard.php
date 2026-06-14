<?php
declare(strict_types=1);

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        get();
        break;
    default:
        http_response_code(405);
        echo 'Method Not Allowed';
        break;
}

function get(): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . appUrl('/login'));
        exit;
    }

    $conn = getConnection();
    $userId = (int)$_SESSION['user_id'];

    $events = fetchCreatorEventStats($conn, $userId);

    $eventIds = array_map(static fn($e) => (int)($e['event_id'] ?? 0), $events);
    $demo = fetchEventDemographics($conn, $eventIds); 

    foreach ($events as &$e) {
        $id = (int)($e['event_id'] ?? 0);
        $e['age_groups'] = $demo['age'][$id] ?? [];
        $e['career_groups'] = $demo['career'][$id] ?? [];
        $e['gender_groups'] = $demo['gender'][$id] ?? [];
    }
    unset($e);

    // summary รวม
    $summary = [
        'total_events' => 0,
        'total_registered' => 0,
        'total_pending' => 0,
        'total_approved' => 0,
        'total_rejected' => 0,
        'total_checked_in' => 0,
        'total_capacity' => 0,
    ];

    $summary['total_events'] = count($events);
    foreach ($events as $e) {
        $summary['total_registered'] += (int)($e['total_registered'] ?? 0);
        $summary['total_pending']    += (int)($e['pending_count'] ?? 0);
        $summary['total_approved']   += (int)($e['approved_count'] ?? 0);
        $summary['total_rejected']   += (int)($e['rejected_count'] ?? 0);
        $summary['total_checked_in'] += (int)($e['checkedin_count'] ?? 0);
        $summary['total_capacity']   += (int)($e['max_participant'] ?? 0);
    }

    $conn->close();

    renderView('dashboard', [
        'title' => 'Dashboard',
        'events' => $events,
        'summary' => $summary,
        'errors' => [],
    ]);
}

/**
 * ดึงสถิติรายกิจกรรมของผู้สร้าง (creator_id)
 * - total_registered
 * - pending/approved/rejected/checked_in
 * - cover image (รูปแรก)
 */
function fetchCreatorEventStats(mysqli $conn, int $creatorId): array
{
    $sql = "
        SELECT
            e.event_id,
            e.title,
            e.location,
            e.event_start,
            e.event_end,
            e.reg_start,
            e.reg_end,
            e.max_participant,
            (
                SELECT i.image_path
                FROM event_images i
                WHERE i.event_id = e.event_id
                ORDER BY i.image_id ASC
                LIMIT 1
            ) AS cover_image,

            COALESCE(SUM(r.status = 'pending'), 0)    AS pending_count,
            COALESCE(SUM(r.status = 'approved'), 0)   AS approved_count,
            COALESCE(SUM(r.status = 'rejected'), 0)   AS rejected_count,
            COALESCE(SUM(r.status = 'checked_in'), 0) AS checkedin_count,
            COALESCE(COUNT(r.reg_id), 0)              AS total_registered
        FROM events e
        LEFT JOIN registrations r ON r.event_id = e.event_id
        WHERE e.creator_id = ?
        GROUP BY e.event_id
        ORDER BY e.event_start DESC
    ";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        return [];
    }

    $stmt->bind_param('i', $creatorId);
    $stmt->execute();

    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    $stmt->close();

    // แคสต์เป็น int ให้เรียบร้อย (กัน template พัง)
    foreach ($rows as &$row) {
        $row['event_id']         = (int)($row['event_id'] ?? 0);
        $row['max_participant']  = (int)($row['max_participant'] ?? 0);
        $row['pending_count']    = (int)($row['pending_count'] ?? 0);
        $row['approved_count']   = (int)($row['approved_count'] ?? 0);
        $row['rejected_count']   = (int)($row['rejected_count'] ?? 0);
        $row['checkedin_count']  = (int)($row['checkedin_count'] ?? 0);
        $row['total_registered'] = (int)($row['total_registered'] ?? 0);
    }
    unset($row);

    return $rows;
}

function fetchEventDemographics(mysqli $conn, array $eventIds): array
{
    // sanitize ids
    $ids = [];
    foreach ($eventIds as $id) {
        $id = (int)$id;
        if ($id > 0) $ids[$id] = true;
    }
    $ids = array_keys($ids);

    if (empty($ids)) {
        return ['age' => [], 'career' => []];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));

    // นับเฉพาะ "ผู้ร่วม" แนะนำ approved + checked_in
    $statusSql = "('approved','checked_in')";

    // 1) Age groups
    $sqlAge = "
        SELECT
            r.event_id,
            CASE
                WHEN u.age < 18 THEN 'ต่ำกว่า 18'
                WHEN u.age BETWEEN 18 AND 24 THEN '18-24'
                WHEN u.age BETWEEN 25 AND 34 THEN '25-34'
                WHEN u.age BETWEEN 35 AND 44 THEN '35-44'
                ELSE '45+'
            END AS age_group,
            COUNT(*) AS cnt
        FROM registrations r
        INNER JOIN users u ON u.user_id = r.user_id
        WHERE r.event_id IN ($placeholders)
          AND r.status IN $statusSql
        GROUP BY r.event_id, age_group
        ORDER BY r.event_id ASC
    ";

    $ageByEvent = [];
    $stmt = $conn->prepare($sqlAge);
    if ($stmt !== false) {
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        foreach ($rows as $row) {
            $eid = (int)($row['event_id'] ?? 0);
            $label = (string)($row['age_group'] ?? '');
            $cnt = (int)($row['cnt'] ?? 0);
            if ($eid > 0 && $label !== '') {
                $ageByEvent[$eid][] = ['label' => $label, 'count' => $cnt];
            }
        }
    }

    // 2) Career groups
    $sqlCareer = "
        SELECT
            r.event_id,
            COALESCE(NULLIF(TRIM(u.career), ''), 'ไม่ระบุ') AS career,
            COUNT(*) AS cnt
        FROM registrations r
        INNER JOIN users u ON u.user_id = r.user_id
        WHERE r.event_id IN ($placeholders)
          AND r.status IN $statusSql
        GROUP BY r.event_id, career
        ORDER BY r.event_id ASC, cnt DESC
    ";

    $careerRawByEvent = [];
    $stmt2 = $conn->prepare($sqlCareer);
    if ($stmt2 !== false) {
        $stmt2->bind_param($types, ...$ids);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        $rows2 = $res2 ? $res2->fetch_all(MYSQLI_ASSOC) : [];
        $stmt2->close();

        foreach ($rows2 as $row) {
            $eid = (int)($row['event_id'] ?? 0);
            $label = (string)($row['career'] ?? 'ไม่ระบุ');
            $cnt = (int)($row['cnt'] ?? 0);
            if ($eid > 0) {
                $careerRawByEvent[$eid][] = ['label' => $label, 'count' => $cnt];
            }
        }
    }

    // แปลง career ให้เป็น Top 5 + อื่นๆ (อ่านง่าย)
    $careerByEvent = [];
    foreach ($careerRawByEvent as $eid => $items) {
        $top = array_slice($items, 0, 5);
        $rest = array_slice($items, 5);
        $otherSum = 0;
        foreach ($rest as $r) $otherSum += (int)($r['count'] ?? 0);

        if ($otherSum > 0) {
            $top[] = ['label' => 'อื่นๆ', 'count' => $otherSum];
        }
        $careerByEvent[(int)$eid] = $top;
    }

        // 3) Gender groups
    $sqlGender = "
        SELECT
            r.event_id,
            CASE
                WHEN TRIM(COALESCE(u.gender, '')) = '' THEN 'ไม่ระบุ'
                ELSE TRIM(u.gender)
            END AS gender_label,
            COUNT(*) AS cnt
        FROM registrations r
        INNER JOIN users u ON u.user_id = r.user_id
        WHERE r.event_id IN ($placeholders)
          AND r.status IN $statusSql
        GROUP BY r.event_id, gender_label
        ORDER BY r.event_id ASC, cnt DESC
    ";

    $genderByEvent = [];
    $stmt3 = $conn->prepare($sqlGender);
    if ($stmt3 !== false) {
        $stmt3->bind_param($types, ...$ids);
        $stmt3->execute();
        $res3 = $stmt3->get_result();
        $rows3 = $res3 ? $res3->fetch_all(MYSQLI_ASSOC) : [];
        $stmt3->close();

        foreach ($rows3 as $row) {
            $eid = (int)($row['event_id'] ?? 0);
            $label = (string)($row['gender_label'] ?? 'ไม่ระบุ');
            $cnt = (int)($row['cnt'] ?? 0);

            if ($eid > 0) {
                $genderByEvent[$eid][] = [
                    'label' => $label,
                    'count' => $cnt,
                ];
            }
        }
    }

   return [
        'age' => $ageByEvent,
        'career' => $careerByEvent,
        'gender' => $genderByEvent,
    ];
    
}
