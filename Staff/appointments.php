<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../config/notifications.php';

$user = staff_require_auth($pdo);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim((string) ($_POST['title'] ?? ''));
    $clientId = filter_var($_POST['client_id'] ?? null, FILTER_VALIDATE_INT);
    $caseInput = trim((string) ($_POST['case_id'] ?? ''));
    $caseId = $caseInput === '' ? null : filter_var($caseInput, FILTER_VALIDATE_INT);
    $type = trim((string) ($_POST['type'] ?? ''));
    $dateInput = trim((string) ($_POST['appointment_date'] ?? ''));
    $appointmentDate = DateTime::createFromFormat('!Y-m-d\TH:i', $dateInput);
    $dateErrors = DateTime::getLastErrors();

    if (!staff_csrf_is_valid($_POST['_csrf'] ?? null)) {
        $error = 'Your session has expired. Refresh the page and try again.';
    } elseif ($title === '' || strlen($title) > 255) {
        $error = 'Enter an appointment title (up to 255 characters).';
    } elseif ($clientId === false || $clientId === null || $clientId < 1) {
        $error = 'Select a valid client.';
    } elseif ($caseId === false || ($caseId !== null && $caseId < 1)) {
        $error = 'Select a valid case, or leave it blank.';
    } elseif ($type === '' || strlen($type) > 50) {
        $error = 'Enter an appointment type (up to 50 characters).';
    } elseif (
        !$appointmentDate
        || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
        || $appointmentDate <= new DateTime()
    ) {
        $error = 'Choose a valid future date and time.';
    } else {
        $clientCheck = $pdo->prepare('SELECT client_id FROM clients WHERE client_id = :client_id');
        $clientCheck->execute(['client_id' => $clientId]);
        if ($clientCheck->fetchColumn() === false) {
            $error = 'The selected client could not be found.';
        } elseif ($caseId !== null) {
            $caseCheck = $pdo->prepare('SELECT client_id FROM cases WHERE case_id = :case_id');
            $caseCheck->execute(['case_id' => $caseId]);
            $caseClientId = $caseCheck->fetchColumn();
            if ($caseClientId === false || (int) $caseClientId !== $clientId) {
                $error = 'Select a case that belongs to the chosen client.';
            }
        }

        if ($error === '') {
            $pdo->beginTransaction();
            $insert = $pdo->prepare(
                'INSERT INTO appointments (case_id, client_id, user_id, title, appointment_date, type, status)
                 VALUES (:case_id, :client_id, :user_id, :title, :appointment_date, :type, :status)'
            );
            $insert->execute([
                'case_id' => $caseId,
                'client_id' => $clientId,
                'user_id' => (int) $user['user_id'],
                'title' => $title,
                'appointment_date' => $appointmentDate->format('Y-m-d H:i:s'),
                'type' => $type,
                'status' => 'Pending',
            ]);
            $appointmentId = (int) $pdo->lastInsertId();
            $clientUserStatement = $pdo->prepare('SELECT user_id FROM clients WHERE client_id = :client_id');
            $clientUserStatement->execute(['client_id' => $clientId]);
            $clientUserId = $clientUserStatement->fetchColumn();
            if ($clientUserId !== false && $clientUserId !== null) {
                create_portal_notification(
                    $pdo,
                    (int) $clientUserId,
                    $appointmentId,
                    'appointment_requested',
                    'Appointment requested',
                    $title . ' is scheduled as a pending request for ' . $appointmentDate->format('M j, Y g:i A') . '.'
                );
            }
            $pdo->commit();
            staff_redirect_with_flash('Appointment saved as pending.');
        }
    }
}

$clients = $pdo->query('SELECT client_id, fullname FROM clients ORDER BY fullname ASC')->fetchAll();
$cases = $pdo->query('SELECT case_id, client_id, case_number, title FROM cases ORDER BY case_number ASC')->fetchAll();
$appointmentsStatement = $pdo->query(
    'SELECT a.appointment_id, a.title, a.appointment_date, a.type, a.status,
            cl.fullname AS client_name, c.case_number, c.title AS case_title,
            COALESCE(u.username, CASE WHEN a.user_id IS NULL THEN \'Client request\' END) AS staff_name
     FROM appointments a
     LEFT JOIN clients cl ON cl.client_id = a.client_id
     LEFT JOIN cases c ON c.case_id = a.case_id
     LEFT JOIN users u ON u.user_id = a.user_id
     ORDER BY a.appointment_date DESC, a.appointment_id DESC'
);
$appointments = $appointmentsStatement->fetchAll();

staff_layout_start('Appointment & Hearing Scheduling', $user, 'appointments.php', staff_take_flash(), $error, 'Schedule Appointment', 'appointment-intake-form');
?>
<dialog id="appointment-intake-form" data-floating-form data-open-on-load="<?php echo $error !== '' ? 'true' : 'false'; ?>" aria-labelledby="appointment-intake-title" class="m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-2xl overflow-y-auto rounded-2xl border border-amber-500/30 bg-slate-900 p-0 text-slate-100 shadow-2xl backdrop:bg-slate-950/75 backdrop:backdrop-blur-sm">
    <section class="<?php echo staff_panel_class(); ?>">
        <div class="flex items-start justify-between gap-4 border-b border-slate-800 p-5">
            <div>
                <h2 id="appointment-intake-title" class="text-xl font-bold text-white">Schedule an Appointment</h2>
                <p class="mt-1 text-sm text-slate-400">Schedule a client meeting or hearing.</p>
            </div>
            <button type="button" data-dialog-close aria-label="Close appointment form" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-800 hover:text-white">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>
        <?php if ($clients === []): ?>
            <div class="p-5">
                <p class="text-sm text-slate-300">Add a client before scheduling an appointment.</p>
                <a href="clients.php" class="mt-3 inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-amber-600 to-amber-500 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:from-amber-500 hover:to-amber-400">
                    <i data-lucide="user-plus" class="h-4 w-4"></i><span>Add Client</span>
                </a>
            </div>
        <?php else: ?>
            <form method="post" action="appointments.php" class="grid grid-cols-1 gap-4 p-5 md:grid-cols-2">
            <?php staff_csrf_field(); ?>
            <div>
                <label for="title" class="mb-1 block text-sm font-medium text-slate-200">Meeting Title <span class="text-amber-400">*</span></label>
                <input id="title" name="title" type="text" required maxlength="255" value="<?php echo staff_h($_POST['title'] ?? ''); ?>" class="<?php echo staff_input_class(); ?>" placeholder="e.g. Case consultation">
            </div>
            <div>
                <label for="client_id" class="mb-1 block text-sm font-medium text-slate-200">Client <span class="text-amber-400">*</span></label>
                <select id="client_id" name="client_id" required class="<?php echo staff_input_class(); ?>">
                    <option value="">Select a client</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?php echo (int) $client['client_id']; ?>" <?php echo (string) ($_POST['client_id'] ?? '') === (string) $client['client_id'] ? 'selected' : ''; ?>>
                            <?php echo staff_h($client['fullname']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="case_id" class="mb-1 block text-sm font-medium text-slate-200">Related Case</label>
                <select id="case_id" name="case_id" class="<?php echo staff_input_class(); ?>">
                    <option value="">No case selected</option>
                    <?php foreach ($cases as $case): ?>
                        <option value="<?php echo (int) $case['case_id']; ?>" <?php echo (string) ($_POST['case_id'] ?? '') === (string) $case['case_id'] ? 'selected' : ''; ?>>
                            <?php echo staff_h($case['case_number'] . ' — ' . $case['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="mt-1 text-xs text-slate-500">If selected, the case must belong to the selected client.</p>
            </div>
            <div>
                <label for="type" class="mb-1 block text-sm font-medium text-slate-200">Appointment Type <span class="text-amber-400">*</span></label>
                <select id="type" name="type" required class="<?php echo staff_input_class(); ?>">
                    <option value="">Select a type</option>
                    <?php foreach (['Consultation', 'Hearing', 'Deposition', 'Client Meeting', 'Other'] as $appointmentType): ?>
                        <option value="<?php echo staff_h($appointmentType); ?>" <?php echo ($_POST['type'] ?? '') === $appointmentType ? 'selected' : ''; ?>><?php echo staff_h($appointmentType); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="appointment_date" class="mb-1 block text-sm font-medium text-slate-200">Date &amp; Time <span class="text-amber-400">*</span></label>
                <input id="appointment_date" name="appointment_date" type="datetime-local" required min="<?php echo staff_h((new DateTime())->modify('+1 minute')->format('Y-m-d\TH:i')); ?>" value="<?php echo staff_h($_POST['appointment_date'] ?? ''); ?>" class="<?php echo staff_input_class(); ?>">
            </div>
            <div class="flex items-end">
                <button type="submit" class="rounded-lg bg-gradient-to-r from-amber-600 to-amber-500 px-5 py-2.5 text-sm font-semibold text-slate-950 transition hover:from-amber-500 hover:to-amber-400">Save Pending Appointment</button>
            </div>
            </form>
        <?php endif; ?>
    </section>
</dialog>

<section class="<?php echo staff_panel_class(); ?>">
    <?php staff_panel_heading('Appointment Requests & Schedule'); ?>
    <p class="mb-4 text-sm text-slate-400">Appointments created by Staff or requested by Clients start as Pending. An Attorney confirms the final date and time; confirmed appointments will appear here and in the Client portal.</p>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[900px] text-left text-sm text-slate-300">
            <thead class="bg-slate-950/60 text-xs uppercase tracking-wider text-amber-400/80">
                <tr><th class="p-4">Meeting Title</th><th class="p-4">Client</th><th class="p-4">Case</th><th class="p-4">Date &amp; Time</th><th class="p-4">Type</th><th class="p-4">Status</th><th class="p-4">Request Source</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                <?php if ($appointments === []): ?>
                    <?php staff_table_empty_row(7, 'No appointments have been recorded.'); ?>
                <?php else: foreach ($appointments as $appointment): ?>
                    <tr class="hover:bg-slate-800/40">
                        <td class="p-4 font-semibold text-white"><?php echo staff_h($appointment['title']); ?></td>
                        <td class="p-4"><?php echo staff_h($appointment['client_name'] ?: '—'); ?></td>
                        <td class="p-4"><?php echo staff_h($appointment['case_number'] ? $appointment['case_number'] . ' — ' . $appointment['case_title'] : '—'); ?></td>
                        <td class="p-4"><?php echo staff_h(date('M j, Y g:i A', strtotime($appointment['appointment_date']))); ?></td>
                        <td class="p-4"><?php echo staff_h($appointment['type'] ?: '—'); ?></td>
                        <td class="p-4"><?php echo staff_h($appointment['status']); ?></td>
                        <td class="p-4"><?php echo staff_h($appointment['staff_name'] ?: '—'); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php staff_layout_end(); ?>
