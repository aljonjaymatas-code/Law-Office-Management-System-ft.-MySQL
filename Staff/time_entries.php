<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_layout.php';

$user = staff_require_auth($pdo);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $caseId = filter_var($_POST['case_id'] ?? null, FILTER_VALIDATE_INT);
    $hoursInput = $_POST['hours'] ?? '';
    $hours = filter_var($hoursInput, FILTER_VALIDATE_FLOAT);
    $description = trim((string) ($_POST['description'] ?? ''));
    $dateLogged = trim((string) ($_POST['date_logged'] ?? ''));
    $today = date('Y-m-d');

    if (!staff_csrf_is_valid($_POST['_csrf'] ?? null)) {
        $error = 'Your session has expired. Refresh the page and try again.';
    } elseif ($caseId === false || $caseId === null || $caseId < 1) {
        $error = 'Select a valid case for this time entry.';
    } elseif ($hours === false || $hours <= 0 || $hours > 24) {
        $error = 'Enter a number of hours greater than 0 and no more than 24.';
    } elseif (strlen($description) > 5000) {
        $error = 'The work description must be 5,000 characters or fewer.';
    } elseif (!DateTime::createFromFormat('!Y-m-d', $dateLogged) || $dateLogged > $today) {
        $error = 'Enter a valid date that is not in the future.';
    } else {
        $caseCheck = $pdo->prepare('SELECT case_id FROM cases WHERE case_id = :case_id');
        $caseCheck->execute(['case_id' => $caseId]);
        if ($caseCheck->fetchColumn() === false) {
            $error = 'The selected case could not be found.';
        } else {
            $insert = $pdo->prepare(
                'INSERT INTO time_entries (case_id, user_id, hours, description, date_logged)
                 VALUES (:case_id, :user_id, :hours, :description, :date_logged)'
            );
            $insert->execute([
                'case_id' => $caseId,
                'user_id' => (int) $user['user_id'],
                'hours' => $hours,
                'description' => $description !== '' ? $description : null,
                'date_logged' => $dateLogged,
            ]);
            staff_redirect_with_flash('Time entry saved successfully.');
        }
    }
}

$cases = $pdo->query('SELECT case_id, case_number, title FROM cases ORDER BY case_number ASC')->fetchAll();
$entriesStatement = $pdo->prepare(
    'SELECT t.time_entry_id, t.hours, t.description, t.date_logged, c.case_number, c.title AS case_title
     FROM time_entries t
     INNER JOIN cases c ON c.case_id = t.case_id
     WHERE t.user_id = :user_id
     ORDER BY t.date_logged DESC, t.time_entry_id DESC'
);
$entriesStatement->execute(['user_id' => (int) $user['user_id']]);
$entries = $entriesStatement->fetchAll();
$totalHours = array_sum(array_map(static fn(array $entry): float => (float) $entry['hours'], $entries));

staff_layout_start('Billable Time Entries', $user, 'time_entries.php', staff_take_flash(), $error, 'Add Time Entry', 'time-entry-form');
?>
<dialog id="time-entry-form" data-floating-form data-open-on-load="<?php echo $error !== '' ? 'true' : 'false'; ?>" aria-labelledby="time-entry-title" class="m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-2xl overflow-y-auto rounded-2xl border border-amber-500/30 bg-slate-900 p-0 text-slate-100 shadow-2xl backdrop:bg-slate-950/75 backdrop:backdrop-blur-sm">
    <section class="<?php echo staff_panel_class(); ?>">
        <div class="flex items-start justify-between gap-4 border-b border-slate-800 p-5">
            <div>
                <h2 id="time-entry-title" class="text-xl font-bold text-white">Log Billable Time</h2>
                <p class="mt-1 text-sm text-slate-400">Record time worked on a case.</p>
            </div>
            <button type="button" data-dialog-close aria-label="Close time entry form" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-800 hover:text-white">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>
        <?php if ($cases === []): ?>
            <div class="p-5">
                <p class="text-sm text-slate-300">A case must exist before time can be recorded.</p>
                <a href="cases.php" class="mt-3 inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-amber-600 to-amber-500 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:from-amber-500 hover:to-amber-400">
                    <i data-lucide="briefcase-business" class="h-4 w-4"></i><span>Create Case</span>
                </a>
            </div>
        <?php else: ?>
            <form method="post" action="time_entries.php" class="grid grid-cols-1 gap-4 p-5 md:grid-cols-2">
            <?php staff_csrf_field(); ?>
            <div>
                <label for="case_id" class="mb-1 block text-sm font-medium text-slate-200">Case <span class="text-amber-400">*</span></label>
                <select id="case_id" name="case_id" required class="<?php echo staff_input_class(); ?>">
                    <option value="">Select a case</option>
                    <?php foreach ($cases as $case): ?>
                        <option value="<?php echo (int) $case['case_id']; ?>" <?php echo (string) ($_POST['case_id'] ?? '') === (string) $case['case_id'] ? 'selected' : ''; ?>>
                            <?php echo staff_h($case['case_number'] . ' — ' . $case['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="hours" class="mb-1 block text-sm font-medium text-slate-200">Hours <span class="text-amber-400">*</span></label>
                <input id="hours" name="hours" type="number" min="0.01" max="24" step="0.01" required value="<?php echo staff_h($_POST['hours'] ?? ''); ?>" class="<?php echo staff_input_class(); ?>">
            </div>
            <div>
                <label for="date_logged" class="mb-1 block text-sm font-medium text-slate-200">Date Worked <span class="text-amber-400">*</span></label>
                <input id="date_logged" name="date_logged" type="date" required max="<?php echo staff_h(date('Y-m-d')); ?>" value="<?php echo staff_h($_POST['date_logged'] ?? date('Y-m-d')); ?>" class="<?php echo staff_input_class(); ?>">
            </div>
            <div>
                <label for="description" class="mb-1 block text-sm font-medium text-slate-200">Work Description</label>
                <input id="description" name="description" type="text" maxlength="5000" value="<?php echo staff_h($_POST['description'] ?? ''); ?>" class="<?php echo staff_input_class(); ?>">
            </div>
            <div class="md:col-span-2">
                <button type="submit" class="rounded-lg bg-gradient-to-r from-amber-600 to-amber-500 px-5 py-2.5 text-sm font-semibold text-slate-950 transition hover:from-amber-500 hover:to-amber-400">Save Time Entry</button>
            </div>
            </form>
        <?php endif; ?>
    </section>
</dialog>

<section class="<?php echo staff_panel_class(); ?>">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-800 p-5">
        <h2 class="font-bold tracking-wide text-white">My Time Entries</h2>
        <p class="text-sm text-amber-300">Total: <?php echo staff_h(number_format($totalHours, 2)); ?> hrs</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[760px] text-left text-sm text-slate-300">
            <thead class="bg-slate-950/60 text-xs uppercase tracking-wider text-amber-400/80">
                <tr><th class="p-4">Date</th><th class="p-4">Case #</th><th class="p-4">Case</th><th class="p-4">Hours</th><th class="p-4">Description</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                <?php if ($entries === []): ?>
                    <?php staff_table_empty_row(5, 'No time entries have been recorded for your account.'); ?>
                <?php else: foreach ($entries as $entry): ?>
                    <tr class="hover:bg-slate-800/40">
                        <td class="p-4"><?php echo staff_h($entry['date_logged']); ?></td>
                        <td class="p-4 font-mono text-amber-300"><?php echo staff_h($entry['case_number']); ?></td>
                        <td class="p-4 font-semibold text-white"><?php echo staff_h($entry['case_title']); ?></td>
                        <td class="p-4"><?php echo staff_h(number_format((float) $entry['hours'], 2)); ?></td>
                        <td class="p-4"><?php echo staff_h($entry['description'] ?: '—'); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php staff_layout_end(); ?>
