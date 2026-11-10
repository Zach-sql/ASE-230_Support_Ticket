<?php
require_once __DIR__ . '/includes/functions.php';

$id = validTicketId($_GET['id'] ?? null);
$ticket = null;
$message = null;
$token = csrfToken();
if ($id === null) {
    http_response_code(400);
    $message = 'Enter a valid positive ticket ID.';
} else {
    try {
        $ticket = findTicketById(loadTickets(), $id);
        if ($ticket === null) {
            http_response_code(404);
            $message = 'Ticket not found.';
        }
    } catch (RuntimeException $exception) {
        http_response_code(500);
        $message = $exception->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Detail</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
<main class="container">
    <p><a href="index.php">&larr; Back to tickets</a></p>

    <?php if ($message): ?>
        <section class="panel">
            <h1><?= e($message) ?></h1>
            <p>Check the ticket ID and try again.</p>
        </section>
    <?php else: ?>
        <section class="panel">
            <h1>Ticket #<?= e((string)$ticket['id']) ?></h1>

            <dl class="ticket-detail">
                <dt>Name</dt>
                <dd><?= e($ticket['name']) ?></dd>

                <dt>Email</dt>
                <dd><?= e($ticket['email']) ?></dd>

                <dt>Category</dt>
                <dd><?= e(ucfirst($ticket['category'])) ?></dd>

                <dt>Status</dt>
                <dd><?= e($ticket['status']) ?></dd>

                <dt>Description</dt>
                <dd><?= nl2br(e($ticket['description'])) ?></dd>

                <dt>Attachment</dt>
                <dd>
                    <?php $imageUrl = attachmentUrl($ticket['attachment'] ?? null); ?>
                    <?php if ($imageUrl !== null): ?>
                        <a href="<?= e($imageUrl) ?>">View attached image</a>
                        <img class="attachment-preview" src="<?= e($imageUrl) ?>" alt="Image attached to this support ticket">
                    <?php else: ?>
                        None
                    <?php endif; ?>
                </dd>
            </dl>
        </section>

        <section class="panel">
            <h2>Update Status</h2>

            <form method="POST" action="update-status.php">
                <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                <input type="hidden" name="id" value="<?= e((string)$ticket['id']) ?>">

                <label for="status">New Status</label>
                <select name="status" id="status">
                    <?php foreach (allowedStatuses() as $status): ?>
                        <option value="<?= e($status) ?>" <?= $ticket['status'] === $status ? 'selected' : '' ?>>
                            <?= e($status) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit">Update Ticket</button>
            </form>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
