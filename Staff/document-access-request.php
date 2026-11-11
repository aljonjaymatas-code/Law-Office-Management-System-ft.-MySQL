<?php
require_once __DIR__ . '/_bootstrap.php';

$user = staff_require_auth($pdo);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

if (!staff_csrf_is_valid($_POST['_csrf'] ?? null)) {
    http_response_code(403);
    echo json_encode(['error' => 'Your session expired. Refresh the page and try again.']);
    exit;
}

$requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT);
$decision = $_POST['decision'] ?? null;
if ($requestId === false || $requestId === null || $requestId < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'Select a valid document access request.']);
    exit;
}
if (!in_array($decision, ['Approved', 'Declined'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Choose whether to approve or decline this request.']);
    exit;
}

$pdo->beginTransaction();
try {
    $requestLookup = $pdo->prepare(
        'SELECT request_id
         FROM document_access_requests
         WHERE request_id = :request_id AND status = \'Pending\'
         FOR UPDATE'
    );
    $requestLookup->execute(['request_id' => $requestId]);
    if ($requestLookup->fetchColumn() === false) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['error' => 'This access request has already been handled. Refresh notifications and try again.']);
        exit;
    }

    $requestUpdate = $pdo->prepare(
        'UPDATE document_access_requests
         SET status = :status, reviewed_at = CURRENT_TIMESTAMP
         WHERE request_id = :request_id AND status = \'Pending\''
    );
    $requestUpdate->execute(['status' => $decision, 'request_id' => $requestId]);
    $notificationUpdate = $pdo->prepare(
        'UPDATE notifications
         SET read_at = COALESCE(read_at, CURRENT_TIMESTAMP)
         WHERE document_access_request_id = :request_id'
    );
    $notificationUpdate->execute(['request_id' => $requestId]);

    $pdo->commit();
    echo json_encode(['success' => true, 'status' => $decision]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
}
