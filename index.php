<?php
require_once __DIR__ . '/includes/functions.php';

$query = requestString($_GET, 'q');
$status = requestString($_GET, 'status');
$category = requestString($_GET, 'category');
$error = null;
$tickets = [];
if (($status !== '' && !in_array($status, allowedStatuses(), true))
    || ($category !== '' && !in_array($category, allowedCategories(), true))) {
    http_response_code(400);
    $error = 'Choose a listed status and category, or clear the filters.';
} else {
    try {
        $tickets = filterTickets(loadTickets(), $query, $status, $category);
    } catch (RuntimeException $exception) {
        http_response_code(500);
        $error = $exception->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Tickets</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
<main class="container">
    <header class="page-header">
        <div>
            <h1>Support Tickets</h1>
            <p>Search, inspect, create, and update support requests.</p>
        </div>
        <a class="button" href="create.php">Create Ticket</a>
    </header>

    <section class="panel">
        <h2>Search and Filter</h2>

        <form method="GET" action="index.php" class="filter-grid">
            <div>
                <label for="q">Keyword</label>
                <input
                    type="search"
                    id="q"
                    name="q"
                    value="<?= e($query) ?>"
                    placeholder="name, email, description"
                >
            </div>

            <div>
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All statuses</option>
                    <?php foreach (allowedStatuses() as $option): ?>
                        <option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>>
                            <?= e($option) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="category">Category</label>
                <select id="category" name="category">
                    <option value="">All categories</option>
                    <?php foreach (allowedCategories() as $option): ?>
                        <option value="<?= e($option) ?>" <?= $category === $option ? 'selected' : '' ?>>
                            <?= e(ucfirst($option)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit">Apply Filters</button>
                <a href="index.php">Clear</a>
            </div>
        </form>
    </section>

    <section>
        <h2>Tickets</h2>

        <?php if ($error !== null): ?>
            <p class="error-box" role="alert"><?= e($error) ?></p>
        <?php elseif (count($tickets) === 0): ?>
            <p>No tickets matched your request.</p>
        <?php else: ?>
            <div class="ticket-list">
                <?php foreach ($tickets as $ticket): ?>
                    <article class="ticket-card">
                        <div class="ticket-meta">
                            <span>#<?= e((string)$ticket['id']) ?></span>
                            <span><?= e($ticket['status']) ?></span>
                            <span><?= e(ucfirst($ticket['category'])) ?></span>
                        </div>

                        <h3><?= e($ticket['name']) ?></h3>
                        <p><?= e($ticket['description']) ?></p>

                        <a href="detail.php?id=<?= urlencode((string)$ticket['id']) ?>">
                            View Ticket
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
