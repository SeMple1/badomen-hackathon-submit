<?php

declare(strict_types=1);

function hackathonDataRoot(): string
{
    return dirname(__DIR__) . '/Hackathon File/data';
}

function readHackathonJson(string $relativePath): array
{
    static $cache = [];

    $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    if (isset($cache[$normalized])) {
        return $cache[$normalized];
    }

    $root = realpath(hackathonDataRoot());
    $path = realpath(hackathonDataRoot() . DIRECTORY_SEPARATOR . $normalized);

    if (
        $root === false
        || $path === false
        || !str_starts_with($path, $root . DIRECTORY_SEPARATOR)
        || !is_file($path)
        || !is_readable($path)
    ) {
        return $cache[$normalized] = [];
    }

    $json = file_get_contents($path);
    if (!is_string($json)) {
        return $cache[$normalized] = [];
    }

    try {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return $cache[$normalized] = [];
    }

    return $cache[$normalized] = is_array($decoded) ? $decoded : [];
}

function loadHackathonEventData(): array
{
    $eventDirectory = '8. ระบบอีเวนต์และตั๋ว (Event Ticketing)';

    return [
        'events' => readHackathonJson($eventDirectory . '/events.json'),
        'tickets' => readHackathonJson($eventDirectory . '/event_tickets.json'),
        'users' => readHackathonJson('users.json'),
        'locations' => readHackathonJson('10. common/locations.json'),
    ];
}

function buildHackathonEventIntelligence(array $data): array
{
    $events = array_values(array_filter(
        $data['events'] ?? [],
        static fn($row): bool => is_array($row) && isset($row['event_id'], $row['title'], $row['date'])
    ));
    $tickets = array_values(array_filter(
        $data['tickets'] ?? [],
        static fn($row): bool => is_array($row) && isset($row['ticket_id'], $row['event_id'])
    ));
    $users = array_values(array_filter(
        $data['users'] ?? [],
        static fn($row): bool => is_array($row) && isset($row['user_id'])
    ));
    $locations = array_values(array_filter(
        $data['locations'] ?? [],
        static fn($row): bool => is_array($row) && isset($row['name'])
    ));

    $usersById = [];
    foreach ($users as $user) {
        $usersById[(string)$user['user_id']] = $user;
    }

    $locationsByName = [];
    foreach ($locations as $location) {
        if (($location['type'] ?? '') !== 'EVENT_VENUE') {
            continue;
        }
        $locationsByName[(string)$location['name']] ??= $location;
    }

    $ticketsByEvent = [];
    $ticketStatuses = [];
    $seatZones = [];
    foreach ($tickets as $ticket) {
        $eventId = (string)$ticket['event_id'];
        $status = strtoupper((string)($ticket['status'] ?? 'UNKNOWN'));
        $zone = (string)($ticket['seat_zone'] ?? 'Unknown');
        $ticket['user'] = $usersById[(string)($ticket['user_id'] ?? '')] ?? null;
        $ticketsByEvent[$eventId][] = $ticket;
        $ticketStatuses[$status] = ($ticketStatuses[$status] ?? 0) + 1;
        $seatZones[$zone] = ($seatZones[$zone] ?? 0) + 1;
    }

    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok'));
    $monthly = [];
    $venueCounts = [];
    $eventRows = [];
    $grossPotential = 0.0;
    $recognizedRevenue = 0.0;
    $freeEvents = 0;
    $upcomingEvents = 0;

    foreach ($events as $event) {
        $eventId = (string)$event['event_id'];
        $date = date_create_immutable((string)$event['date']);
        $date = $date instanceof DateTimeImmutable
            ? $date->setTimezone(new DateTimeZone('Asia/Bangkok'))
            : null;
        $eventTickets = $ticketsByEvent[$eventId] ?? [];
        $price = max(0, (float)($event['ticket_price'] ?? 0));
        $recognizedCount = 0;

        foreach ($eventTickets as $ticket) {
            if (in_array(strtoupper((string)($ticket['status'] ?? '')), ['PAID', 'USED'], true)) {
                $recognizedCount++;
            }
        }

        $ticketCount = count($eventTickets);
        $grossPotential += $ticketCount * $price;
        $recognizedRevenue += $recognizedCount * $price;
        if ($price <= 0) {
            $freeEvents++;
        }

        $isUpcoming = $date !== null && $date >= $now;
        if ($isUpcoming) {
            $upcomingEvents++;
        }

        $monthKey = $date?->format('Y-m') ?? 'unknown';
        $monthly[$monthKey] = ($monthly[$monthKey] ?? 0) + 1;

        $venue = trim((string)($event['location'] ?? 'Unknown'));
        $venueCounts[$venue] = ($venueCounts[$venue] ?? 0) + 1;

        $eventRows[] = array_merge($event, [
            'date_object' => $date,
            'is_upcoming' => $isUpcoming,
            'tickets' => $eventTickets,
            'ticket_count' => $ticketCount,
            'recognized_ticket_count' => $recognizedCount,
            'recognized_revenue' => $recognizedCount * $price,
            'gross_potential' => $ticketCount * $price,
            'venue_detail' => $locationsByName[$venue] ?? null,
        ]);
    }

    ksort($monthly);
    arsort($venueCounts);
    arsort($ticketStatuses);
    arsort($seatZones);
    usort($eventRows, static function (array $a, array $b): int {
        $aDate = $a['date_object'] instanceof DateTimeImmutable ? $a['date_object']->getTimestamp() : PHP_INT_MAX;
        $bDate = $b['date_object'] instanceof DateTimeImmutable ? $b['date_object']->getTimestamp() : PHP_INT_MAX;
        return $aDate <=> $bDate;
    });

    $topEventRows = $eventRows;
    usort($topEventRows, static fn(array $a, array $b): int => $b['ticket_count'] <=> $a['ticket_count']);

    return [
        'events' => $eventRows,
        'events_by_id' => array_column($eventRows, null, 'event_id'),
        'summary' => [
            'total_events' => count($eventRows),
            'upcoming_events' => $upcomingEvents,
            'past_events' => count($eventRows) - $upcomingEvents,
            'free_events' => $freeEvents,
            'paid_events' => count($eventRows) - $freeEvents,
            'total_tickets' => count($tickets),
            'recognized_revenue' => $recognizedRevenue,
            'gross_potential' => $grossPotential,
            'unique_users' => count($usersById),
        ],
        'monthly' => $monthly,
        'venues' => $venueCounts,
        'ticket_statuses' => $ticketStatuses,
        'seat_zones' => $seatZones,
        'top_events' => array_slice($topEventRows, 0, 5),
    ];
}
