<?php
require_once __DIR__ . '/../config/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function client_h(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function client_csrf_token(): string
{
    if (!isset($_SESSION['client_csrf_token'])) {
        $_SESSION['client_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['client_csrf_token'];
}

function client_csrf_field(): void
{
    echo '<input type="hidden" name="_csrf" value="' . client_h(client_csrf_token()) . '">';
}

function client_csrf_is_valid(mixed $submittedToken): bool
{
    return is_string($submittedToken)
        && isset($_SESSION['client_csrf_token'])
        && hash_equals($_SESSION['client_csrf_token'], $submittedToken);
}

function client_current_user(PDO $pdo): ?array
{
    $userId = filter_var($_SESSION['client_user_id'] ?? null, FILTER_VALIDATE_INT);
    if ($userId === false || $userId === null || $userId < 1) {
        return null;
    }

    $statement = $pdo->prepare(
        "SELECT u.user_id, u.username, u.email, u.role, c.client_id, c.fullname,
                COALESCE(NULLIF(u.phone, ''), c.phone) AS phone,
                COALESCE(NULLIF(u.address, ''), c.address) AS address
         FROM users u
         INNER JOIN clients c ON c.user_id = u.user_id
         WHERE u.user_id = :user_id AND LOWER(TRIM(u.role)) = 'client'
         "
    );
    $statement->execute(['user_id' => $userId]);
    $user = $statement->fetch();

    return is_array($user) ? $user : null;
}

function client_take_flash(): string
{
    $message = (string) ($_SESSION['client_flash'] ?? '');
    unset($_SESSION['client_flash']);

    return $message;
}
