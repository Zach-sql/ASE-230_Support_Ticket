<?php
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!validCsrfToken()) {
    http_response_code(403);
    exit('The form expired. Return to the ticket detail page and try again.');
}
$id = validTicketId($_POST['id'] ?? null);
$status = requestString($_POST, 'status');
if ($id === null || !in_array($status, allowedStatuses(), true)) {
    http_response_code(400);
    exit('A positive ticket ID and a listed status are required.');
}

try {
    $found = withTicketLock(function () use ($id, $status): bool {
        $tickets = loadTickets();
        if (!updateTicketStatus($tickets, $id, $status)) {
            return false;
        }
        if (!saveTickets($tickets)) {
            throw new RuntimeException('The status could not be saved. Check the data folder permissions and try again.');
        }
        return true;
    });
    if (!$found) {
        http_response_code(404);
        exit('Ticket not found.');
    }
    header('Location: detail.php?id=' . urlencode((string)$id), true, 303);
    exit;
} catch (RuntimeException $exception) {
    http_response_code(500);
    echo e($exception->getMessage());
}
