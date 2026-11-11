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

if (isset($_GET['download']) || isset($_GET['inline']) || isset($_GET['print']) || isset($_GET['preview'])) {
    $isPrintView = isset($_GET['print']);
    $isPreview = isset($_GET['preview']);
    $isInline = isset($_GET['inline']);
    $documentId = filter_var($_GET['print'] ?? $_GET['preview'] ?? $_GET['inline'] ?? $_GET['download'], FILTER_VALIDATE_INT);
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

    if ($isPrintView || $isPreview) {
        $title = staff_h((string) $document['document_name']);
        $printSource = 'documents.php?inline=' . $documentId;
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store, private');
        ?>
        <!doctype html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo $isPreview ? 'Preview ' : 'Print '; ?><?php echo $title; ?></title>
            <style>
                body { margin: 0; padding: <?php echo $isPreview ? '1rem' : '1.5rem'; ?>; color: #172033; font: 16px Arial, sans-serif; }
                header { display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 1rem; }
                button { border: 0; border-radius: .5rem; background: #d97706; color: #fff; cursor: pointer; font: inherit; font-weight: 700; padding: .7rem 1rem; }
                iframe { border: 1px solid #cbd5e1; height: <?php echo $isPreview ? 'calc(100vh - 2rem)' : 'calc(100vh - 7rem)'; ?>; width: 100%; }
                img { display: block; height: auto; margin: 0 auto; max-height: <?php echo $isPreview ? 'calc(100vh - 2rem)' : 'none'; ?>; max-width: 100%; }
                pre { white-space: pre-wrap; overflow-wrap: anywhere; }
                #docx-print-area { display: block; }
                #print-status { color: #475569; }
                @page { margin: 18mm; }
                @media print {
                    body { padding: 0; }
                    header, #print-status { display: none; }
                    iframe { border: 0; height: 100vh; }
                }
            </style>
        </head>
        <body>
            <?php if ($isPrintView): ?>
                <header>
                    <strong><?php echo $title; ?></strong>
                    <button type="button" onclick="window.print()">Print</button>
                </header>
            <?php endif; ?>
            <?php if ($mimeType === 'text/plain'): ?>
                <pre><?php echo staff_h((string) file_get_contents($filePath)); ?></pre>
                <?php if ($isPrintView): ?><script>window.addEventListener('load', () => window.print());</script><?php endif; ?>
            <?php elseif (str_starts_with($mimeType, 'image/')): ?>
                <img src="<?php echo staff_h($printSource); ?>" alt="<?php echo $title; ?>" <?php echo $isPrintView ? 'onload="window.print()"' : ''; ?>>
            <?php elseif (str_contains($mimeType, 'wordprocessingml') || $mimeType === 'application/zip'): ?>
                <p id="print-status" role="status">Preparing document preview…</p>
                <div id="docx-print-area"></div>
                <script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js" referrerpolicy="no-referrer"></script>
                <script src="https://cdn.jsdelivr.net/npm/docx-preview@0.3.6/dist/docx-preview.min.js" referrerpolicy="no-referrer"></script>
                <script>
                    (async () => {
                        const status = document.getElementById('print-status');
                        const container = document.getElementById('docx-print-area');
                        try {
                            if (!window.JSZip || !window.docx || typeof window.docx.renderAsync !== 'function') {
                                throw new Error('The local document renderer did not load.');
                            }
                            const response = await fetch(<?php echo json_encode($printSource); ?>, { credentials: 'same-origin' });
                            if (!response.ok) {
                                throw new Error('The document could not be opened for printing.');
                            }
                            await window.docx.renderAsync(await response.blob(), container, null, {
                                inWrapper: false,
                                breakPages: true,
                                ignoreLastRenderedPageBreak: false
                            });
                            status.remove();
                            await document.fonts.ready;
                            <?php if ($isPrintView): ?>window.print();<?php endif; ?>
                        } catch (error) {
                            console.error('Unable to prepare document for printing.', error);
                            status.textContent = 'Unable to prepare this document for printing. Check your connection and try again.';
                        }
                    })();
                </script>
            <?php else: ?>
                <iframe title="Printable document preview" src="<?php echo staff_h($printSource); ?>" <?php echo $isPrintView ? 'onload="window.print()"' : ''; ?>></iframe>
            <?php endif; ?>
        </body>
        </html>
        <?php
        exit;
    }

    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . (string) filesize($filePath));
    header('Content-Disposition: ' . ($isInline ? 'inline' : 'attachment') . '; filename="' . rawurlencode(basename((string) $document['document_name'])) . '"');
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
        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        $uploadedFileHash = hash_file('sha256', (string) $file['tmp_name']);

        if (!isset($allowedFiles[$extension]) || !in_array($mimeType, $allowedFiles[$extension], true)) {
            $error = 'Choose a PDF, Word document, JPG, PNG, or TXT file.';
        } elseif (!is_uploaded_file((string) $file['tmp_name'])) {
            $error = 'The uploaded file could not be verified.';
        } elseif ($uploadedFileHash === false) {
            $error = 'The uploaded file could not be checked for duplicates.';
        } else {
            $pdo->beginTransaction();
            $destination = null;
            try {
                $caseCheck = $pdo->prepare('SELECT case_id FROM cases WHERE case_id = :case_id FOR UPDATE');
                $caseCheck->execute(['case_id' => $caseId]);
                if ($caseCheck->fetchColumn() === false) {
                    $error = 'The selected case could not be found.';
                } else {
                    $documentsForCase = $pdo->prepare(
                        'SELECT document_name, file_path FROM documents WHERE case_id = :case_id FOR UPDATE'
                    );
                    $documentsForCase->execute(['case_id' => $caseId]);
                    $uploadRoot = realpath($uploadDirectory);
                    $duplicateFound = false;

                    foreach ($documentsForCase->fetchAll() as $existingDocument) {
                        if (strcasecmp(trim((string) $existingDocument['document_name']), $documentTitle) === 0) {
                            $duplicateFound = true;
                            break;
                        }

                        $storedName = basename(str_replace('\\', '/', (string) $existingDocument['file_path']));
                        $existingFilePath = $uploadRoot !== false && $storedName !== ''
                            ? realpath($uploadRoot . DIRECTORY_SEPARATOR . $storedName)
                            : false;
                        if (
                            $existingFilePath !== false
                            && dirname($existingFilePath) === $uploadRoot
                            && is_file($existingFilePath)
                        ) {
                            $existingFileHash = hash_file('sha256', $existingFilePath);
                            if ($existingFileHash !== false && hash_equals($existingFileHash, $uploadedFileHash)) {
                                $duplicateFound = true;
                                break;
                            }
                        }
                    }

                    if ($duplicateFound) {
                        $error = 'This document has already been uploaded for the selected case.';
                    } elseif (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0750, true) && !is_dir($uploadDirectory)) {
                        $error = 'The server could not prepare secure document storage.';
                    } else {
                        $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
                        $destination = $uploadDirectory . DIRECTORY_SEPARATOR . $storedName;
                        if (!move_uploaded_file((string) $file['tmp_name'], $destination)) {
                            $error = 'The server could not save the uploaded file.';
                        } else {
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
                            $pdo->commit();
                            staff_redirect_with_flash('Document uploaded successfully and submitted to the Attorney Review File queue.');
                        }
                    }
                }
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($destination !== null && is_file($destination) && !unlink($destination)) {
                    error_log('Failed to remove an uploaded file after its document record could not be saved.');
                }
                throw $exception;
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
            d.review_status, d.reviewed_at,
            c.case_number, c.title AS case_title, cl.fullname AS client_name,
            u.username AS uploaded_by_name, reviewer.username AS reviewed_by_name
     FROM documents d
     INNER JOIN cases c ON c.case_id = d.case_id
     INNER JOIN clients cl ON cl.client_id = c.client_id
     LEFT JOIN users u ON u.user_id = d.uploaded_by
     LEFT JOIN users reviewer ON reviewer.user_id = d.reviewed_by
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
        <table class="w-full min-w-[1050px] text-left text-sm text-slate-300">
            <thead class="bg-slate-950/60 text-xs uppercase tracking-wider text-amber-400/80">
                <tr><th class="p-4">Document</th><th class="p-4">Case</th><th class="p-4">Client</th><th class="p-4">Uploaded By</th><th class="p-4">Upload Date</th><th class="p-4">Review Status</th><th class="p-4">File</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                <?php if ($documents === []): ?>
                    <?php staff_table_empty_row(7, 'No documents have been uploaded.'); ?>
                <?php else: foreach ($documents as $document): ?>
                    <tr class="hover:bg-slate-800/40">
                        <td class="p-4 font-semibold text-white"><?php echo staff_h($document['document_name']); ?></td>
                        <td class="p-4"><?php echo staff_h($document['case_number'] . ' — ' . $document['case_title']); ?></td>
                        <td class="p-4"><?php echo staff_h($document['client_name']); ?></td>
                        <td class="p-4"><?php echo staff_h($document['uploaded_by_name'] ?: '—'); ?></td>
                        <td class="p-4"><?php echo staff_h($document['upload_date'] ? date('M j, Y', strtotime($document['upload_date'])) : 'Date not recorded'); ?></td>
                        <td class="p-4">
                            <?php $reviewStatus = $document['review_status'] ?: 'Pending Review'; ?>
                            <span class="rounded-full border px-3 py-1 text-xs font-medium <?php echo $reviewStatus === 'Approved' ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300' : ($reviewStatus === 'Declined' ? 'border-red-500/30 bg-red-500/10 text-red-300' : 'border-amber-500/30 bg-amber-500/10 text-amber-300'); ?>">
                                <?php echo staff_h($reviewStatus); ?>
                            </span>
                            <?php if ($document['reviewed_at']): ?>
                                <span class="mt-2 block text-xs text-slate-500">By <?php echo staff_h($document['reviewed_by_name'] ?: 'Attorney'); ?> · <?php echo staff_h(date('M j, Y', strtotime($document['reviewed_at']))); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="p-4"><button type="button" data-document-preview="<?php echo (int) $document['document_id']; ?>" data-document-title="<?php echo staff_h($document['document_name']); ?>" class="font-medium text-amber-300 hover:underline">Print</button></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
<dialog id="document-preview-dialog" aria-labelledby="document-preview-title" class="m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-5xl overflow-hidden rounded-2xl border border-amber-500/30 bg-slate-900 p-0 text-slate-100 shadow-2xl backdrop:bg-slate-950/75 backdrop:backdrop-blur-sm">
    <section class="flex max-h-[calc(100dvh-2rem)] flex-col">
        <div class="flex items-center justify-between gap-4 border-b border-slate-800 p-5">
            <div class="min-w-0">
                <h2 id="document-preview-title" class="truncate text-lg font-bold text-white">Document Preview</h2>
                <p class="mt-1 text-sm text-slate-400">Review the file before printing.</p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <button id="document-preview-print" type="button" class="rounded-lg bg-gradient-to-r from-amber-600 to-amber-500 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:from-amber-500 hover:to-amber-400">Print</button>
                <button id="document-preview-close" type="button" aria-label="Close document preview" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-800 hover:text-white">&times;</button>
            </div>
        </div>
        <iframe id="document-preview-frame" title="Document preview" class="min-h-[65vh] w-full flex-1 border-0 bg-white"></iframe>
    </section>
</dialog>
<script>
    (() => {
        const dialog = document.getElementById('document-preview-dialog');
        const frame = document.getElementById('document-preview-frame');
        const title = document.getElementById('document-preview-title');
        const printButton = document.getElementById('document-preview-print');
        const closeButton = document.getElementById('document-preview-close');
        let currentDocumentId = null;

        document.querySelectorAll('[data-document-preview]').forEach((button) => {
            button.addEventListener('click', () => {
                currentDocumentId = button.dataset.documentPreview;
                title.textContent = button.dataset.documentTitle || 'Document Preview';
                frame.src = `documents.php?preview=${encodeURIComponent(currentDocumentId)}`;
                dialog.showModal();
            });
        });

        const closePreview = () => {
            if (dialog.open) dialog.close();
            frame.src = 'about:blank';
            currentDocumentId = null;
        };
        closeButton.addEventListener('click', closePreview);
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) closePreview();
        });
        dialog.addEventListener('close', () => {
            frame.src = 'about:blank';
            currentDocumentId = null;
        });
        printButton.addEventListener('click', () => {
            if (currentDocumentId === null) return;
            window.open(`documents.php?print=${encodeURIComponent(currentDocumentId)}`, '_blank', 'noopener');
        });
    })();
</script>
<?php staff_layout_end(); ?>
