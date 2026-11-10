<?php
require_once __DIR__ . '/includes/functions.php';

$values = [
    'name' => '',
    'email' => '',
    'category' => '',
    'description' => '',
    'status' => 'Open'
];

$errors = [];
$token = csrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $field => $default) {
        $values[$field] = requestString($_POST, $field, $default);
    }
    if (!validCsrfToken()) {
        http_response_code(403);
        $errors[] = 'This form expired or could not be read. Reload the page and try again. Large uploads may exceed the server limit.';
    }
    if ($values['name'] === '') {
        $errors[] = 'Enter the customer name.';
    }
    if (filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false) {
        $errors[] = 'Enter a valid email address.';
    }
    if (!in_array($values['category'], allowedCategories(), true)) {
        $errors[] = 'Choose one of the listed categories.';
    }
    if ($values['description'] === '') {
        $errors[] = 'Enter a description of the issue.';
    }
    if (!in_array($values['status'], allowedStatuses(), true)) {
        $errors[] = 'Choose one of the listed statuses.';
    }
    $attachment = null;
    if (empty($errors)) {
        $attachment = saveUploadedImage($_FILES['attachment'] ?? null, $errors);
    }
    if (empty($errors)) {
        try {
            withTicketLock(function () use ($values, $attachment): void {
                $tickets = loadTickets();
                $newTicket = [
                    'id' => nextTicketId($tickets),
                    'name' => $values['name'],
                    'email' => $values['email'],
                    'category' => $values['category'],
                    'description' => $values['description'],
                    'status' => $values['status'],
                    'attachment' => $attachment
                ];
                $tickets[] = $newTicket;
                if (!saveTickets($tickets)) {
                    throw new RuntimeException('The ticket could not be saved. Check the data folder permissions and try again.');
                }
            });
            // A redirect prevents a refresh from creating another ticket.
            header('Location: index.php', true, 303);
            exit;
        } catch (RuntimeException $exception) {
            http_response_code(500);
            $errors[] = $exception->getMessage();
            if ($attachment !== null) {
                @unlink(UPLOAD_DIR . $attachment);
            }
        }
    }
    if (!empty($errors) && http_response_code() < 400) {
        http_response_code(422);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Support Ticket</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
<main class="container">
    <p><a href="index.php">&larr; Back to tickets</a></p>

    <section class="panel">
        <h1>Create Support Ticket</h1>
        <p>Describe your issue and optionally attach an image.</p>

        <?php if (!empty($errors)): ?>
            <div class="error-box" role="alert">
                <strong>Please correct the following:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="create.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
            <label for="name">Customer Name</label>
            <input
                type="text"
                id="name"
                name="name"
                value="<?= e($values['name']) ?>"
                required
            >

            <label for="email">Email Address</label>
            <input
                type="email"
                id="email"
                name="email"
                value="<?= e($values['email']) ?>"
                required
            >

            <label for="category">Category</label>
            <select id="category" name="category" required>
                <option value="">Choose a category</option>
                <?php foreach (allowedCategories() as $category): ?>
                    <option
                        value="<?= e($category) ?>"
                        <?= $values['category'] === $category ? 'selected' : '' ?>
                    >
                        <?= e(ucfirst($category)) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="description">Description</label>
            <textarea id="description" name="description" rows="7" required><?= e($values['description']) ?></textarea>

            <label for="status">Status</label>
            <select id="status" name="status" required>
                <?php foreach (allowedStatuses() as $status): ?>
                    <option value="<?= e($status) ?>" <?= $values['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="attachment">Optional Image</label>
            <input type="hidden" name="MAX_FILE_SIZE" value="<?= e((string)MAX_IMAGE_BYTES) ?>">
            <input
                type="file"
                id="attachment"
                name="attachment"
                accept="image/jpeg,image/png,image/webp"
            >
            <p>JPEG, PNG, or WebP; maximum 2 MB. If the form has an error, select the image again.</p>

            <button type="submit">Submit Ticket</button>
        </form>
    </section>
</main>
</body>
</html>
