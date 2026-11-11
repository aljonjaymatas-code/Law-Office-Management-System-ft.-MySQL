<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/_auth.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$userRole = $_SESSION['admin_portal_role'] ?? '';
if (!in_array($userRole, ['admin', 'lawyer'], true) || empty($_SESSION['admin_portal_user_id'])) {
    http_response_code(403);
    exit('Access denied.');
}

$userStatement = $pdo->prepare('SELECT user_id, username, role FROM users WHERE user_id = :user_id');
$userStatement->execute(['user_id' => (int) $_SESSION['admin_portal_user_id']]);
$user = $userStatement->fetch();
if (!is_array($user) || admin_portal_role_for_account_role((string) $user['role']) !== $userRole) {
    http_response_code(403);
    exit('Access denied.');
}

$documentId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($documentId === false || $documentId === null || $documentId < 1) {
    http_response_code(400);
    exit('Invalid document ID.');
}

$documentQuery =
    'SELECT d.document_name, d.file_path
     FROM documents d
     INNER JOIN cases c ON c.case_id = d.case_id
     WHERE d.document_id = :document_id';
$documentParams = ['document_id' => $documentId];
if ($userRole === 'lawyer') {
    $documentQuery .=
        ' AND (
            c.assigned_lawyer_id = :user_id
            OR (
                c.assigned_lawyer_id IS NULL
                AND LOWER(TRIM(c.assigned_lawyer_name)) = LOWER(TRIM(:username))
            )
        )';
    $documentParams['user_id'] = (int) $user['user_id'];
    $documentParams['username'] = (string) $user['username'];
}
$documentStatement = $pdo->prepare($documentQuery);
$documentStatement->execute($documentParams);
$document = $documentStatement->fetch();
$storedPath = is_array($document) ? str_replace('\\', '/', (string) $document['file_path']) : '';
if (preg_match('~\Auploads/[a-f0-9]{32}\.(?:pdf|doc|docx|jpg|jpeg|png|txt)\z~i', $storedPath) !== 1) {
    http_response_code(404);
    exit('Document not found.');
}

$uploadRoot = realpath(__DIR__ . '/../Staff/uploads');
$filePath = $uploadRoot !== false
    ? realpath($uploadRoot . DIRECTORY_SEPARATOR . basename($storedPath))
    : false;
if (
    $uploadRoot === false
    || $filePath === false
    || dirname($filePath) !== $uploadRoot
    || !is_file($filePath)
) {
    http_response_code(404);
    exit('Document not found.');
}

$mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($filePath) ?: 'application/octet-stream';
$inlineTypes = ['application/pdf', 'image/jpeg', 'image/png', 'text/plain'];
$isInline = isset($_GET['preview']) && $_GET['preview'] === '1' && in_array($mimeType, $inlineTypes, true);
$downloadName = preg_replace('/[^A-Za-z0-9._ -]/', '_', basename((string) $document['document_name'])) ?: 'document';
$fileExtension = strtolower(pathinfo($storedPath, PATHINFO_EXTENSION));
if (strtolower(pathinfo($downloadName, PATHINFO_EXTENSION)) !== $fileExtension) {
    $downloadName .= '.' . $fileExtension;
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . (string) filesize($filePath));
header('Content-Disposition: ' . ($isInline ? 'inline' : 'attachment') . '; filename="' . $downloadName . '"; filename*=UTF-8\'\'' . rawurlencode((string) $document['document_name'] . '.' . $fileExtension));
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
readfile($filePath);
