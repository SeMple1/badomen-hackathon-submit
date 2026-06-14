<?php
declare(strict_types=1);

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        get();
        break;
    case 'POST':
        post();
        break;
}

function get(): void {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . appUrl('/login'));
        exit;
    }

    $eventId = getEventIdFromRequest();
    if ($eventId === null) {
        notFound();
    }
}

function post(): void {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . appUrl('/login'));
        exit;
    }

    $eventId = getEventIdFromRequest();
    if ($eventId === null) {
        notFound();
    }

}

