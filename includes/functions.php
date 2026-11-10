<?php

const DATA_FILE = __DIR__ . '/../data/tickets.json';
const UPLOAD_DIR = __DIR__ . '/../uploads/';
const MAX_IMAGE_BYTES = 2 * 1024 * 1024;

// Treat missing values and array-shaped request parameters as empty strings.
function requestString(array $source, string $key, string $default = ''): string
{
    return isset($source[$key]) && is_string($source[$key])
        ? trim($source[$key])
        : (array_key_exists($key, $source) ? '' : $default);
}

function validTicketId($value): ?int
{
    if (!is_string($value)) {
        return null;
    }
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $id === false ? null : $id;
}

function csrfToken(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validCsrfToken(): bool
{
    return hash_equals(csrfToken(), requestString($_POST, 'csrf_token'));
}

function allowedCategories(): array
{
    return ['technical', 'billing', 'account', 'other'];
}

function allowedStatuses(): array
{
    return ['Open', 'In Progress', 'Resolved'];
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function loadTickets(): array
{
    if (!file_exists(DATA_FILE)) {
        return [];
    }

    $json = @file_get_contents(DATA_FILE);
    if ($json === false) {
        throw new RuntimeException('Ticket data could not be read. Check the data folder permissions.');
    }
    $tickets = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($tickets) || array_values($tickets) !== $tickets) {
        throw new RuntimeException('Ticket data is not a valid JSON list. Restore data/tickets.json before saving.');
    }
    foreach ($tickets as $ticket) {
        if (!is_array($ticket) || !isset($ticket['id']) || !is_int($ticket['id']) || $ticket['id'] < 1) {
            throw new RuntimeException('A saved ticket has an invalid ID. Check data/tickets.json.');
        }
        foreach (['name', 'email', 'category', 'description', 'status'] as $field) {
            if (!isset($ticket[$field]) || !is_string($ticket[$field])) {
                throw new RuntimeException('A saved ticket has invalid fields. Check data/tickets.json.');
            }
        }
    }
    return $tickets;
}

function saveTickets(array $tickets): bool
{
    $json = json_encode(array_values($tickets), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }

    // Replace the complete file so readers never receive half-written JSON.
    $temporary = @tempnam(dirname(DATA_FILE), 'tickets-');
    if ($temporary === false) {
        return false;
    }
    $contents = $json . PHP_EOL;
    $saved = @file_put_contents($temporary, $contents, LOCK_EX) === strlen($contents)
        && @rename($temporary, DATA_FILE);
    if (is_file($temporary)) {
        @unlink($temporary);
    }
    return $saved;
}

// Keep load -> change -> save together to avoid duplicate IDs or lost updates.
function withTicketLock(callable $action)
{
    $lock = @fopen(dirname(DATA_FILE) . '/.tickets.lock', 'c');
    if ($lock === false) {
        throw new RuntimeException('Ticket data is not writable. Check the data folder permissions.');
    }
    if (!flock($lock, LOCK_EX)) {
        fclose($lock);
        throw new RuntimeException('Ticket data is busy. Please try again.');
    }
    try {
        return $action();
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function findTicketById(array $tickets, int $id): ?array
{
    foreach ($tickets as $ticket) {
        if ((int)$ticket['id'] === $id) {
            return $ticket;
        }
    }

    return null;
}

function nextTicketId(array $tickets): int
{
    $maxId = 0;
    foreach ($tickets as $ticket) {
        $maxId = max($maxId, (int)$ticket['id']);
    }
    return $maxId + 1;
}

function filterTickets(
    array $tickets,
    string $query = '',
    string $status = '',
    string $category = ''
): array {
    return array_values(array_filter($tickets, function (array $ticket) use ($query, $status, $category): bool {
        if ($status !== '' && $ticket['status'] !== $status) {
            return false;
        }
        if ($category !== '' && $ticket['category'] !== $category) {
            return false;
        }
        $searchText = $ticket['name'] . ' ' . $ticket['email'] . ' ' . $ticket['description'];
        return $query === '' || stripos($searchText, $query) !== false;
    }));
}

function updateTicketStatus(array &$tickets, int $id, string $newStatus): bool
{
    if (!in_array($newStatus, allowedStatuses(), true)) {
        return false;
    }
    foreach ($tickets as $index => $ticket) {
        if ((int)$ticket['id'] === $id) {
            $tickets[$index]['status'] = $newStatus;
            return true;
        }
    }
    return false;
}

function saveUploadedImage(?array $file, array &$errors): ?string
{
    if ($file === null) {
        return null;
    }
    $error = $file['error'] ?? null;
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK) {
        $errors[] = in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            ? 'The image must be 2 MB or smaller.'
            : 'The image upload failed. Please select the file again and retry.';
        return null;
    }
    $temporary = $file['tmp_name'] ?? '';
    if (!is_string($temporary) || !is_uploaded_file($temporary)) {
        $errors[] = 'The attachment is not a valid uploaded file.';
        return null;
    }
    // Inspect actual bytes, not the browser MIME type or original extension.
    $size = filesize($temporary);
    if ($size === false || $size < 1 || $size > MAX_IMAGE_BYTES) {
        $errors[] = 'The image must be nonempty and 2 MB or smaller.';
        return null;
    }
    $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporary);
    $image = @getimagesize($temporary);
    if (!isset($allowedTypes[$mime]) || $image === false || ($image['mime'] ?? '') !== $mime) {
        $errors[] = 'Attach a real JPEG, PNG, or WebP image.';
        return null;
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mime];
    if (!is_dir(UPLOAD_DIR) || !is_writable(UPLOAD_DIR)
        || !move_uploaded_file($temporary, UPLOAD_DIR . $filename)) {
        $errors[] = 'The image could not be saved. Check the uploads folder permissions.';
        return null;
    }
    return $filename;
}

function attachmentUrl($filename): ?string
{
    if (!is_string($filename) || !preg_match('/\A[a-f0-9]{32}\.(jpg|png|webp)\z/', $filename)) {
        return null;
    }
    return 'uploads/' . rawurlencode($filename);
}
