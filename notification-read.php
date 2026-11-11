<?php
require_once __DIR__ . '/config/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$portal = '';
$userId = null;
$csrfSessionKey = '';
$requestedPortal = $_SERVER['REQUEST_METHOD'] === 'GET'
    ? ($_GET['portal'] ?? '')
    : ($_POST['portal'] ?? '');
if ($requestedPortal === 'client' && isset($_SESSION['client_user_id'])) {
    $portal = 'client';
    $userId = filter_var($_SESSION['client_user_id'], FILTER_VALIDATE_INT);
    $csrfSessionKey = 'client_csrf_token';
} elseif ($requestedPortal === 'staff' && isset($_SESSION['staff_user_id'])) {
    $portal = 'staff';
    $userId = filter_var($_SESSION['staff_user_id'], FILTER_VALIDATE_INT);
    $csrfSessionKey = 'staff_csrf_token';
} elseif (
    $requestedPortal === 'admin'
    && isset($_SESSION['admin_portal_user_id'], $_SESSION['admin_portal_role'])
) {
    $portal = 'admin';
    $userId = filter_var($_SESSION['admin_portal_user_id'], FILTER_VALIDATE_INT);
    $csrfSessionKey = 'admin_appointment_csrf_token';
}

if (
    $portal === ''
    || $userId === false
    || $userId === null
    || $userId < 1
) {
    http_response_code(403);
    echo json_encode(['error' => 'Your session expired. Refresh the page and try again.']);
    exit;
}

$userStatement = $pdo->prepare('SELECT role FROM users WHERE user_id = :user_id');
$userStatement->execute(['user_id' => $userId]);
$role = strtolower(trim((string) $userStatement->fetchColumn()));
$adminPortalRole = $_SESSION['admin_portal_role'] ?? '';
$authorized = match ($portal) {
    'client' => $role === 'client',
    'staff' => in_array($role, ['staff', 'legal assistant', 'legal_assistant', 'paralegal', 'receptionist'], true),
    'admin' => $adminPortalRole === 'admin'
        ? in_array($role, ['admin', 'administrator', 'managing partner'], true)
        : ($adminPortalRole === 'lawyer' && in_array($role, ['lawyer', 'attorney'], true)),
    default => false,
};

if (!$authorized) {
    http_response_code(403);
    echo json_encode(['error' => 'You are not authorized to update these notifications.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $notificationsStatement = $pdo->prepare(
        'SELECT n.notification_id, n.notification_type, n.title, n.message, n.created_at, n.read_at,
                n.document_access_request_id, dar.status AS document_access_request_status
         FROM notifications n
         LEFT JOIN document_access_requests dar
           ON dar.request_id = n.document_access_request_id
         WHERE n.user_id = :user_id
         ORDER BY n.created_at DESC, n.notification_id DESC
         LIMIT 50'
    );
    $notificationsStatement->execute(['user_id' => $userId]);
    $notifications = array_map(
        static function (array $notification): array {
            $notification['notification_id'] = (int) $notification['notification_id'];
            $notification['read'] = $notification['read_at'] !== null;
            return $notification;
        },
        $notificationsStatement->fetchAll()
    );
    echo json_encode(['notifications' => $notifications]);
    exit;
}

$submittedToken = $_POST['_csrf'] ?? null;
if (
    !is_string($submittedToken)
    || !isset($_SESSION[$csrfSessionKey])
    || !hash_equals($_SESSION[$csrfSessionKey], $submittedToken)
) {
    http_response_code(403);
    echo json_encode(['error' => 'Your session expired. Refresh the page and try again.']);
    exit;
}

$action = $_POST['action'] ?? '';
if ($action === 'mark_read') {
    $notificationId = filter_var($_POST['notification_id'] ?? null, FILTER_VALIDATE_INT);
    if ($notificationId === false || $notificationId === null || $notificationId < 1) {
        http_response_code(400);
        echo json_encode(['error' => 'Select a valid notification.']);
        exit;
    }

    $update = $pdo->prepare(
        'UPDATE notifications
         SET read_at = CURRENT_TIMESTAMP
         WHERE notification_id = :notification_id AND user_id = :user_id AND read_at IS NULL'
    );
    $update->execute(['notification_id' => $notificationId, 'user_id' => $userId]);
} elseif ($action === 'mark_all_read') {
    $update = $pdo->prepare(
        'UPDATE notifications
         SET read_at = CURRENT_TIMESTAMP
         WHERE user_id = :user_id AND read_at IS NULL'
    );
    $update->execute(['user_id' => $userId]);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid notification action.']);
    exit;
}

echo json_encode(['success' => true]);
