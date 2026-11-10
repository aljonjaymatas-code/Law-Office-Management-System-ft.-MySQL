<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_layout.php';

$user = staff_require_auth($pdo);
$uploadDirectory = __DIR__ . '/uploads';
$maxFileSize = 10 * 1024 * 1024;
$allowedFiles = [
    'pdf' => ['application/pdf'],
    'doc' => ['application/msword', 'application/vnd.ms-word'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
    'jpg' => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png' => ['image/png'],
    'txt' => ['text/plain'],
];

if (isset($_GET['download'])) {
    $documentId = filter_var($_GET['download'], FILTER_VALIDATE_INT);
    if ($documentId === false || $documentId === null || $documentId < 1) {
        http_response_code(400);
        exit('Invalid document ID.');
    }

    $downloadStatement = $pdo->prepare('SELECT document_name, file_path FROM documents WHERE document_id = :document_id');
    $downloadStatement->execute(['document_id' => $documentId]);
    $document = $downloadStatement->fetch();
    $uploadRoot = realpath($uploadDirectory);
    $storedName = is_array($document) ? basename(str_replace('\\', '/', (string) $document['file_path'])) : '';
    $filePath = $uploadRoot !== false && $storedName !== ''
        ? realpath($uploadRoot . DIRECTORY_SEPARATOR . $storedName)
        : false;

    if (!is_array($document) || $uploadRoot === false || $filePath === false || dirname($filePath) !== $uploadRoot || !is_file($filePath)) {
        http_response_code(404);
        exit('Document not found.');
    }

    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $fileInfo->file($filePath) ?: 'application/octet-stream';
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . (string) filesize($filePath));
    header('Content-Disposition: attachment; filename="' . rawurlencode(basename((string) $document['document_name'])) . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($filePath);
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $caseId = filter_var($_POST['case_id'] ?? null, FILTER_VALIDATE_INT);
    $documentTitle = trim((string) ($_POST['document_name'] ?? ''));
    $file = $_FILES['document_file'] ?? null;

    if (!staff_csrf_is_valid($_POST['_csrf'] ?? null)) {
        $error = 'Your session has expired. Refresh the page and try again.';
    } elseif ($caseId === false || $caseId === null || $caseId < 1) {
        $error = 'Select a valid case for this document.';
    } elseif ($documentTitle === '' || strlen($documentTitle) > 255) {
        $error = 'Enter a document title up to 255 characters.';
    } elseif (!is_array($file) || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $uploadError = is_array($file) ? (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
        $error = match ($uploadError) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the server upload size limit.',
            UPLOAD_ERR_PARTIAL => 'The file upload was incomplete. Please try again.',
            UPLOAD_ERR_NO_FILE => 'Choose a file to upload.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'The server could not receive the uploaded file.',
            default => 'The file could not be uploaded. Please try again.',
        };
    } elseif ((int) $file['size'] > $maxFileSize) {
        $error = 'The file must be 10 MB or smaller.';
    } else {
        $caseCheck = $pdo->prepare('SELECT case_id FROM cases WHERE case_id = :case_id');
        $caseCheck->execute(['case_id' => $caseId]);
        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);

        if ($caseCheck->fetchColumn() === false) {
            $error = 'The selected case could not be found.';
        } elseif (!isset($allowedFiles[$extension]) || !in_array($mimeType, $allowedFiles[$extension], true)) {
            $error = 'Choose a PDF, Word document, JPG, PNG, or TXT file.';
        } elseif (!is_uploaded_file((string) $file['tmp_name'])) {
            $error = 'The uploaded file could not be verified.';
        } else {
            if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0750, true) && !is_dir($uploadDirectory)) {
                $error = 'The server could not prepare secure document storage.';
            } else {
                $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
                $destination = $uploadDirectory . DIRECTORY_SEPARATOR . $storedName;
                if (!move_uploaded_file((string) $file['tmp_name'], $destination)) {
                    $error = 'The server could not save the uploaded file.';
                } else {
                    try {
                        $insert = $pdo->prepare(
                            'INSERT INTO documents (case_id, uploaded_by, document_name, file_path)
                             VALUES (:case_id, :uploaded_by, :document_name, :file_path)'
                        );
                        $insert->execute([
                            'case_id' => $caseId,
                            'uploaded_by' => (int) $user['user_id'],
                            'document_name' => $documentTitle,
                            'file_path' => 'uploads/' . $storedName,
                        ]);
                        staff_redirect_with_flash('Document uploaded successfully.');
                    } catch (Throwable $exception) {
                        if (is_file($destination) && !unlink($destination)) {
                            error_log('Failed to remove an uploaded file after its document record could not be saved.');
                        }
                        throw $exception;
                    }
                }
            }
        }
    }
}

$cases = $pdo->query(
    'SELECT c.case_id, c.case_number, c.title, cl.fullname AS client_name
     FROM cases c
     INNER JOIN clients cl ON cl.client_id = c.client_id
     ORDER BY c.case_number ASC'
)->fetchAll();
$documentsStatement = $pdo->query(
    'SELECT d.document_id, d.document_name, d.file_path, d.upload_date,
            c.case_number, c.title AS case_title, cl.fullname AS client_name, u.username AS uploaded_by_name
     FROM documents d
     INNER JOIN cases c ON c.case_id = d.case_id
     INNER JOIN clients cl ON cl.client_id = c.client_id
     LEFT JOIN users u ON u.user_id = d.uploaded_by
     ORDER BY d.upload_date DESC, d.document_id DESC'
);
$documents = $documentsStatement->fetchAll();

staff_layout_start('Case Document Uploading', $user, 'documents.php', staff_take_flash(), $error, 'Upload Document', 'document-upload-form');
?>
<dialog id="document-upload-form" data-floating-form data-open-on-load="<?php echo $error !== '' ? 'true' : 'false'; ?>" aria-labelledby="document-upload-title" class="m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-2xl overflow-y-auto rounded-2xl border border-amber-500/30 bg-slate-900 p-0 text-slate-100 shadow-2xl backdrop:bg-slate-950/75 backdrop:backdrop-blur-sm">
    <section class="<?php echo staff_panel_class(); ?>">
        <div class="flex items-start justify-between gap-4 border-b border-slate-800 p-5">
            <div>
                <h2 id="document-upload-title" class="text-xl font-bold text-white">Upload a Case Document</h2>
                <p class="mt-1 text-sm text-slate-400">Upload a file to a case.</p>
            </div>
            <button type="button" data-dialog-close aria-label="Close document upload form" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-800 hover:text-white">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>
        <?php if ($cases === []): ?>
            <div class="p-5">
                <p class="text-sm text-slate-300">A case must exist before a document can be uploaded.</p>
                <a href="cases.php" class="mt-3 inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-amber-600 to-amber-500 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:from-amber-500 hover:to-amber-400">
                    <i data-lucide="briefcase-business" class="h-4 w-4"></i><span>Create Case</span>
                </a>
            </div>
        <?php else: ?>
            <form method="post" action="documents.php" enctype="multipart/form-data" class="grid grid-cols-1 gap-4 p-5 md:grid-cols-2">
            <?php staff_csrf_field(); ?>
            <div>
                <label for="case_id" class="mb-1 block text-sm font-medium text-slate-200">Case <span class="text-amber-400">*</span></label>
                <select id="case_id" name="case_id" required class="<?php echo staff_input_class(); ?>">
                    <option value="">Select a case</option>
                    <?php foreach ($cases as $case): ?>
                        <option value="<?php echo (int) $case['case_id']; ?>" <?php echo (string) ($_POST['case_id'] ?? '') === (string) $case['case_id'] ? 'selected' : ''; ?>>
                            <?php echo staff_h($case['case_number'] . ' — ' . $case['title'] . ' (' . $case['client_name'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="document_name" class="mb-1 block text-sm font-medium text-slate-200">Document Title <span class="text-amber-400">*</span></label>
                <input id="document_name" name="document_name" type="text" required maxlength="255" value="<?php echo staff_h($_POST['document_name'] ?? ''); ?>" class="<?php echo staff_input_class(); ?>">
            </div>
            <div>
                <label for="document_file" class="mb-1 block text-sm font-medium text-slate-200">Choose File <span class="text-amber-400">*</span></label>
                <input id="document_file" name="document_file" type="file" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.txt" class="<?php echo staff_input_class(); ?>">
                <p class="mt-1 text-xs text-slate-500">PDF, Word, JPG, PNG, or TXT; maximum 10 MB.</p>
            </div>
            <div class="flex items-end">
                <button type="submit" class="rounded-lg bg-gradient-to-r from-amber-600 to-amber-500 px-5 py-2.5 text-sm font-semibold text-slate-950 transition hover:from-amber-500 hover:to-amber-400">Upload Document</button>
            </div>
            </form>
        <?php endif; ?>
    </section>
</dialog>

<section class="<?php echo staff_panel_class(); ?>">
    <?php staff_panel_heading('Documents from the LOMS database'); ?>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[850px] text-left text-sm text-slate-300">
            <thead class="bg-slate-950/60 text-xs uppercase tracking-wider text-amber-400/80">
                <tr><th class="p-4">Document</th><th class="p-4">Case</th><th class="p-4">Client</th><th class="p-4">Uploaded By</th><th class="p-4">Upload Date</th><th class="p-4">File</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                <?php if ($documents === []): ?>
                    <?php staff_table_empty_row(6, 'No documents have been uploaded.'); ?>
                <?php else: foreach ($documents as $document): ?>
                    <tr class="hover:bg-slate-800/40">
                        <td class="p-4 font-semibold text-white"><?php echo staff_h($document['document_name']); ?></td>
                        <td class="p-4"><?php echo staff_h($document['case_number'] . ' — ' . $document['case_title']); ?></td>
                        <td class="p-4"><?php echo staff_h($document['client_name']); ?></td>
                        <td class="p-4"><?php echo staff_h($document['uploaded_by_name'] ?: '—'); ?></td>
                        <td class="p-4"><?php echo staff_h(date('M j, Y', strtotime($document['upload_date']))); ?></td>
                        <td class="p-4"><a href="documents.php?download=<?php echo (int) $document['document_id']; ?>" class="font-medium text-amber-300 hover:underline">Download</a></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php staff_layout_end(); ?>
