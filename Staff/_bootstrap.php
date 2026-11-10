<?php
require_once __DIR__ . '/../config/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function staff_is_authorized_role(string $role): bool
{
    return in_array(strtolower(trim($role)), [
        'staff',
        'legal assistant',
        'legal_assistant',
        'paralegal',
        'receptionist',
    ], true);
}

function staff_require_auth(PDO $pdo): array
{
    $userId = filter_var($_SESSION['staff_user_id'] ?? null, FILTER_VALIDATE_INT);
    if ($userId === false || $userId === null || $userId < 1) {
        header('Location: login.php');
        exit;
    }

    $statement = $pdo->prepare('SELECT user_id, username, email, role FROM users WHERE user_id = :user_id');
    $statement->execute(['user_id' => $userId]);
    $user = $statement->fetch();

    if (!is_array($user) || !staff_is_authorized_role((string) $user['role'])) {
        unset($_SESSION['staff_user_id']);
        header('Location: login.php');
        exit;
    }

    return $user;
}

function staff_h(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function staff_csrf_token(): string
{
    if (!isset($_SESSION['staff_csrf_token'])) {
        $_SESSION['staff_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['staff_csrf_token'];
}

function staff_csrf_field(): void
{
    echo '<input type="hidden" name="_csrf" value="' . staff_h(staff_csrf_token()) . '">';
}

function staff_csrf_is_valid(mixed $submittedToken): bool
{
    return is_string($submittedToken)
        && isset($_SESSION['staff_csrf_token'])
        && hash_equals($_SESSION['staff_csrf_token'], $submittedToken);
}

function staff_redirect_with_flash(string $message): never
{
    $_SESSION['staff_flash'] = $message;
    header('Location: ' . basename($_SERVER['SCRIPT_NAME']));
    exit;
}

function staff_take_flash(): string
{
    $message = (string) ($_SESSION['staff_flash'] ?? '');
    unset($_SESSION['staff_flash']);
    return $message;
}
