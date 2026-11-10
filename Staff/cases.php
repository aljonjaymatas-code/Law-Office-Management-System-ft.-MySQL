<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_layout.php';

$user = staff_require_auth($pdo);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $caseNumber = trim((string) ($_POST['case_number'] ?? ''));
    $title = trim((string) ($_POST['title'] ?? ''));
    $clientName = trim((string) ($_POST['client_name'] ?? ''));
    $clientEmail = trim((string) ($_POST['client_email'] ?? ''));
    $clientPhone = trim((string) ($_POST['client_phone'] ?? ''));
    $clientAddress = trim((string) ($_POST['client_address'] ?? ''));
    $lawyerName = trim((string) ($_POST['assigned_lawyer_name'] ?? ''));
    $status = trim((string) ($_POST['status'] ?? ''));
    $filedDateInput = trim((string) ($_POST['filed_date'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $filedDate = DateTime::createFromFormat('!Y-m-d', $filedDateInput);
    $dateErrors = DateTime::getLastErrors();
    $allowedStatuses = ['Pending', 'Active', 'On Hold', 'Closed'];

    if (!staff_csrf_is_valid($_POST['_csrf'] ?? null)) {
        $error = 'Your session has expired. Refresh the page and try again.';
    } elseif ($caseNumber === '' || strlen($caseNumber) > 100) {
        $error = 'Enter a case number (up to 100 characters).';
    } elseif ($title === '' || strlen($title) > 255) {
        $error = 'Enter a case title (up to 255 characters).';
    } elseif ($clientName === '' || strlen($clientName) > 150) {
        $error = 'Enter a client name (up to 150 characters).';
    } elseif ($clientEmail !== '' && (strlen($clientEmail) > 150 || filter_var($clientEmail, FILTER_VALIDATE_EMAIL) === false)) {
        $error = 'Enter a valid client email address or leave it blank.';
    } elseif ($clientPhone !== '' && preg_match('/^[0-9]{1,11}$/D', $clientPhone) !== 1) {
        $error = 'Client phone number must contain digits only and be no more than 11 digits.';
    } elseif (strlen($lawyerName) > 150) {
        $error = 'Assigned lawyer name must be 150 characters or fewer.';
    } elseif (!in_array($status, $allowedStatuses, true)) {
        $error = 'Select a valid case status.';
    } elseif (
        !$filedDate
        || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
    ) {
        $error = 'Enter a valid filed date.';
    } elseif ($description !== '' && strlen($description) > 65535) {
        $error = 'Case description is too long.';
    } else {
        $duplicateCheck = $pdo->prepare('SELECT case_id FROM cases WHERE case_number = :case_number');
        $duplicateCheck->execute(['case_number' => $caseNumber]);

        if ($duplicateCheck->fetchColumn() !== false) {
            $error = 'That case number is already in use.';
        } else {
            try {
                $pdo->beginTransaction();
                $clientLookup = $pdo->prepare(
                    "SELECT c.client_id
                     FROM clients c
                     INNER JOIN users u ON u.user_id = c.user_id
                     WHERE LOWER(TRIM(u.role)) = 'client'
                       AND LOWER(TRIM(c.fullname)) = LOWER(TRIM(:fullname))
                     FOR UPDATE"
                );
                $clientLookup->execute(['fullname' => $clientName]);
                $matchingClientIds = $clientLookup->fetchAll(PDO::FETCH_COLUMN);

                if (count($matchingClientIds) > 1) {
                    $pdo->rollBack();
                    $error = 'More than one Client account has that name. Use a unique client name so the case is linked to the correct account.';
                } else {
                    if (count($matchingClientIds) === 1) {
                        $clientId = (int) $matchingClientIds[0];
                    } else {
                        $clientInsert = $pdo->prepare(
                            'INSERT INTO clients (fullname, phone, email, address)
                             VALUES (:fullname, :phone, :email, :address)'
                        );
                        $clientInsert->execute([
                            'fullname' => $clientName,
                            'phone' => $clientPhone !== '' ? $clientPhone : null,
                            'email' => $clientEmail !== '' ? $clientEmail : null,
                            'address' => $clientAddress !== '' ? $clientAddress : null,
                        ]);
                        $clientId = (int) $pdo->lastInsertId();
                    }

                    $caseInsert = $pdo->prepare(
                        'INSERT INTO cases
                            (client_id, assigned_lawyer_name, case_number, title, description, status, filed_date)
                         VALUES
                            (:client_id, :assigned_lawyer_name, :case_number, :title, :description, :status, :filed_date)'
                    );
                    $caseInsert->execute([
                        'client_id' => $clientId,
                        'assigned_lawyer_name' => $lawyerName !== '' ? $lawyerName : null,
                        'case_number' => $caseNumber,
                        'title' => $title,
                        'description' => $description !== '' ? $description : null,
                        'status' => $status,
                        'filed_date' => $filedDate->format('Y-m-d'),
                    ]);
                    $pdo->commit();
                    staff_redirect_with_flash('Case record saved successfully.');
                }
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($exception->getCode() !== '23000') {
                    throw $exception;
                }
                $error = 'That case number is already in use. Review the form and try again.';
            }
        }
    }
}

$statement = $pdo->query(
    'SELECT c.case_id, c.case_number, c.title, c.description, c.status, c.filed_date,
            cl.fullname AS client_name,
            COALESCE(NULLIF(c.assigned_lawyer_name, \'\'), u.username) AS lawyer_name
     FROM cases c
     LEFT JOIN clients cl ON cl.client_id = c.client_id
     LEFT JOIN users u ON u.user_id = c.assigned_lawyer_id
     ORDER BY c.filed_date DESC, c.case_id DESC'
);
$cases = $statement->fetchAll();

staff_layout_start('Case Management', $user, 'cases.php', staff_take_flash(), $error, 'Add Case', 'case-intake-form');
?>
<dialog id="case-intake-form" data-floating-form data-open-on-load="<?php echo $error !== '' ? 'true' : 'false'; ?>" aria-labelledby="case-intake-title" class="m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-3xl overflow-y-auto rounded-2xl border border-amber-500/30 bg-slate-900 p-0 text-slate-100 shadow-2xl backdrop:bg-slate-950/75 backdrop:backdrop-blur-sm">
    <section class="<?php echo staff_panel_class(); ?>">
        <div class="flex items-start justify-between gap-4 border-b border-slate-800 p-5">
            <div>
                <h2 id="case-intake-title" class="text-xl font-bold text-white">Add a Case</h2>
                <p class="mt-1 text-sm text-slate-400">Enter a new case record.</p>
            </div>
            <button type="button" data-dialog-close aria-label="Close case form" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-800 hover:text-white">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>
        <form method="post" action="cases.php" class="grid grid-cols-1 gap-4 p-5 md:grid-cols-2">
        <?php staff_csrf_field(); ?>
        <div>
            <label for="case_number" class="mb-1 block text-sm font-medium text-slate-200">Case # <span class="text-amber-400">*</span></label>
            <input id="case_number" name="case_number" type="text" required maxlength="100" value="<?php echo staff_h($_POST['case_number'] ?? ''); ?>" class="<?php echo staff_input_class(); ?>">
        </div>
        <div>
            <label for="title" class="mb-1 block text-sm font-medium text-slate-200">Case Title <span class="text-amber-400">*</span></label>
            <input id="title" name="title" type="text" required maxlength="255" value="<?php echo staff_h($_POST['title'] ?? ''); ?>" class="<?php echo staff_input_class(); ?>">
        </div>
        <div>
            <label for="client_name" class="mb-1 block text-sm font-medium text-slate-200">Client Name <span class="text-amber-400">*</span></label>
            <input id="client_name" name="client_name" type="text" required maxlength="150" value="<?php echo staff_h($_POST['client_name'] ?? ''); ?>" class="<?php echo staff_input_class(); ?>">
            <p class="mt-1 text-xs text-slate-400">Use the registered Client account name to link this case to their Client Portal. If no account matches, a separate intake record is created.</p>
        </div>
        <div>
            <label for="assigned_lawyer_name" class="mb-1 block text-sm font-medium text-slate-200">Assigned Lawyer</label>
            <input id="assigned_lawyer_name" name="assigned_lawyer_name" type="text" maxlength="150" value="<?php echo staff_h($_POST['assigned_lawyer_name'] ?? ''); ?>" class="<?php echo staff_input_class(); ?>">
        </div>
        <div>
            <label for="client_email" class="mb-1 block text-sm font-medium text-slate-200">Client Email</label>
            <input id="client_email" name="client_email" type="email" maxlength="150" value="<?php echo staff_h($_POST['client_email'] ?? ''); ?>" class="<?php echo staff_input_class(); ?>">
        </div>
        <div>
            <label for="client_phone" class="mb-1 block text-sm font-medium text-slate-200">Client Phone</label>
            <input id="client_phone" name="client_phone" type="text" inputmode="numeric" pattern="[0-9]{0,11}" maxlength="11" autocomplete="tel-national" data-phone-input title="Enter up to 11 digits only." value="<?php echo staff_h($_POST['client_phone'] ?? ''); ?>" class="<?php echo staff_input_class(); ?>">
            <p class="mt-1 text-xs text-slate-400">Enter up to 11 digits only (example: 09123456789).</p>
        </div>
        <div>
            <label for="client_address" class="mb-1 block text-sm font-medium text-slate-200">Client Address</label>
            <input id="client_address" name="client_address" type="text" value="<?php echo staff_h($_POST['client_address'] ?? ''); ?>" class="<?php echo staff_input_class(); ?>">
        </div>
        <div>
            <label for="status" class="mb-1 block text-sm font-medium text-slate-200">Status <span class="text-amber-400">*</span></label>
            <select id="status" name="status" required class="<?php echo staff_input_class(); ?>">
                <?php foreach (['Pending', 'Active', 'On Hold', 'Closed'] as $caseStatus): ?>
                    <option value="<?php echo staff_h($caseStatus); ?>" <?php echo ($_POST['status'] ?? 'Pending') === $caseStatus ? 'selected' : ''; ?>><?php echo staff_h($caseStatus); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="filed_date" class="mb-1 block text-sm font-medium text-slate-200">Filed Date <span class="text-amber-400">*</span></label>
            <input id="filed_date" name="filed_date" type="date" required value="<?php echo staff_h($_POST['filed_date'] ?? date('Y-m-d')); ?>" class="<?php echo staff_input_class(); ?>">
        </div>
        <div class="md:col-span-2">
            <label for="description" class="mb-1 block text-sm font-medium text-slate-200">Case Description</label>
            <textarea id="description" name="description" rows="3" class="<?php echo staff_input_class(); ?>"><?php echo staff_h($_POST['description'] ?? ''); ?></textarea>
        </div>
        <div class="md:col-span-2">
            <button type="submit" class="rounded-lg bg-gradient-to-r from-amber-600 to-amber-500 px-5 py-2.5 text-sm font-semibold text-slate-950 transition hover:from-amber-500 hover:to-amber-400">Save Case</button>
        </div>
        </form>
    </section>
</dialog>

<section class="<?php echo staff_panel_class(); ?>">
    <?php staff_panel_heading('Recent Cases'); ?>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[900px] text-left text-sm text-slate-300">
            <thead class="bg-slate-950/60 text-xs uppercase tracking-wider text-amber-400/80">
                <tr>
                    <th class="p-4">Case #</th>
                    <th class="p-4">Case Title</th>
                    <th class="p-4">Client</th>
                    <th class="p-4">Assigned Lawyer</th>
                    <th class="p-4">Status</th>
                    <th class="p-4">Filed Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                <?php if ($cases === []): ?>
                    <?php staff_table_empty_row(6, 'No cases have been recorded.'); ?>
                <?php else: foreach ($cases as $case): ?>
                    <tr class="hover:bg-slate-800/40">
                        <td class="p-4 font-mono text-amber-300"><?php echo staff_h($case['case_number']); ?></td>
                        <td class="p-4">
                            <p class="font-semibold text-white"><?php echo staff_h($case['title']); ?></p>
                            <?php if ($case['description'] !== null && $case['description'] !== ''): ?>
                                <p class="mt-1 max-w-sm whitespace-normal text-xs text-slate-400"><?php echo staff_h($case['description']); ?></p>
                            <?php endif; ?>
                        </td>
                        <td class="p-4"><?php echo staff_h($case['client_name']); ?></td>
                        <td class="p-4"><?php echo staff_h($case['lawyer_name'] ?: 'Unassigned'); ?></td>
                        <td class="p-4"><?php echo staff_h($case['status']); ?></td>
                        <td class="p-4"><?php echo staff_h($case['filed_date']); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php staff_layout_end(); ?>
