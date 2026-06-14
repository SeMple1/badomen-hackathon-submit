<?php

declare(strict_types=1);

function normalizeEventTags(string $raw): array
{
    $parts = preg_split('/[,#\r\n]+/u', $raw) ?: [];
    $tags = [];
    foreach ($parts as $part) {
        $name = trim(preg_replace('/\s+/u', ' ', $part) ?? '');
        if ($name === '') {
            continue;
        }
        $name = mb_substr($name, 0, 80);
        $slug = strtolower(trim(preg_replace('/[^\pL\pN]+/u', '-', $name) ?? '', '-'));
        if ($slug === '') {
            continue;
        }
        $tags[$slug] = $name;
        if (count($tags) >= 12) {
            break;
        }
    }
    return $tags;
}

function replaceEventTags(mysqli $conn, int $eventId, string $rawTags): void
{
    if (!databaseTableExists($conn, 'tags') || !databaseTableExists($conn, 'event_tags')) {
        return;
    }

    $tags = normalizeEventTags($rawTags);
    $delete = $conn->prepare('DELETE FROM event_tags WHERE event_id = ?');
    if ($delete !== false) {
        $delete->bind_param('i', $eventId);
        $delete->execute();
        $delete->close();
    }
    if (empty($tags)) {
        return;
    }

    $insertTag = $conn->prepare(
        'INSERT INTO tags (slug, name_th, name_en, usage_count)
         VALUES (?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE usage_count = usage_count + 1'
    );
    $selectTag = $conn->prepare('SELECT tag_id FROM tags WHERE slug = ? LIMIT 1');
    $linkTag = $conn->prepare('INSERT IGNORE INTO event_tags (event_id, tag_id) VALUES (?, ?)');
    if ($insertTag === false || $selectTag === false || $linkTag === false) {
        return;
    }

    foreach ($tags as $slug => $name) {
        $insertTag->bind_param('sss', $slug, $name, $name);
        $insertTag->execute();
        $selectTag->bind_param('s', $slug);
        $selectTag->execute();
        $tagId = (int)($selectTag->get_result()->fetch_assoc()['tag_id'] ?? 0);
        if ($tagId > 0) {
            $linkTag->bind_param('ii', $eventId, $tagId);
            $linkTag->execute();
        }
    }

    $insertTag->close();
    $selectTag->close();
    $linkTag->close();
}

function normalizeGuestFavoriteIds(string $raw): array
{
    if ($raw === '' || strlen($raw) > 4096) {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }

    $ids = [];
    foreach ($decoded as $value) {
        $eventId = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($eventId === false) {
            continue;
        }
        $ids[(int)$eventId] = (int)$eventId;
        if (count($ids) >= 50) {
            break;
        }
    }

    return array_values($ids);
}

function importGuestFavorites(mysqli $conn, int $userId, string $raw): int
{
    $eventIds = normalizeGuestFavoriteIds($raw);
    if ($userId <= 0 || empty($eventIds) || !databaseTableExists($conn, 'event_favorites')) {
        return 0;
    }

    $check = $conn->prepare('SELECT event_id FROM events WHERE event_id = ? LIMIT 1');
    $insert = $conn->prepare(
        'INSERT IGNORE INTO event_favorites (user_id, event_id) VALUES (?, ?)'
    );
    if ($check === false || $insert === false) {
        return 0;
    }

    $imported = 0;
    foreach ($eventIds as $eventId) {
        $check->bind_param('i', $eventId);
        $check->execute();
        if (!$check->get_result()->fetch_assoc()) {
            continue;
        }

        $insert->bind_param('ii', $userId, $eventId);
        $insert->execute();
        $imported += max(0, $insert->affected_rows);
    }

    $check->close();
    $insert->close();
    return $imported;
}
