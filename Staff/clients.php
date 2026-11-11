<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_layout.php';

$user = staff_require_auth($pdo);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim((string) ($_POST['fullname'] ?? ''));
    $emailInput = trim((string) ($_POST['email'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));

    if (!staff_csrf_is_valid($_POST['_csrf'] ?? null)) {
        $error = 'Your session has expired. Refresh the page and try again.';
    } elseif (strlen($fullname) < 2 || strlen($fullname) > 150) {
        $error = 'Enter a client name between 2 and 150 characters.';
    } elseif ($emailInput !== '' && filter_var($emailInput, FILTER_VALIDATE_EMAIL) === false) {
        $error = 'Enter a valid email address or leave the email field blank.';
    } elseif ($phone !== '' && preg_match('/^[0-9]{1,11}$/D', $phone) !== 1) {
        $error = 'The phone number must contain digits only and be no more than 11 digits.';
    } else {
        $statement = $pdo->prepare(
            'INSERT INTO clients (fullname, phone, email, address) VALUES (:fullname, :phone, :email, :address)'
        );
        $statement->execute([
            'fullname' => $fullname,
            'phone' => $phone !== '' ? $phone : null,
            'email' => $emailInput !== '' ? $emailInput : null,
            'address' => $address !== '' ? $address : null,
        ]);
        staff_redirect_with_flash('Client record added successfully.');
    }
}

$clientsStatement = $pdo->query(
    "SELECT c.client_id, c.fullname, c.email, c.phone, c.address
     FROM clients c
     INNER JOIN users u ON u.user_id = c.user_id
     WHERE LOWER(TRIM(u.role)) = 'client'
     ORDER BY c.fullname ASC, c.client_id ASC"
);
$clients = $clientsStatement->fetchAll();
$intakeRecordsStatement = $pdo->query(
    "SELECT c.client_id, c.fullname, c.email, c.phone, c.address
     FROM clients c
     LEFT JOIN users u
       ON u.user_id = c.user_id AND LOWER(TRIM(u.role)) = 'client'
     WHERE u.user_id IS NULL
     ORDER BY c.fullname ASC, c.client_id ASC"
);
$intakeRecords = $intakeRecordsStatement->fetchAll();

staff_layout_start('Client Registration & Intake Management', $user, 'clients.php', staff_take_flash(), $error, 'New Client', 'client-intake-form');
?>
<dialog id="client-intake-form" data-floating-form data-open-on-load="<?php echo $error !== '' ? 'true' : 'false'; ?>" aria-labelledby="client-intake-title" class="m-auto w-[calc(100%-2rem)] max-w-2xl rounded-2xl border border-amber-500/30 bg-slate-900 p-0 text-slate-100 shadow-2xl backdrop:bg-slate-950/75 backdrop:backdrop-blur-sm">
    <section class="<?php echo staff_panel_class(); ?>">
        <div class="flex items-start justify-between gap-4 border-b border-slate-800 p-5">
            <div>
                <h2 id="client-intake-title" class="text-xl font-bold text-white">New Client Intake</h2>
                <p class="mt-1 text-sm text-slate-400">Add a new client or intake record.</p>
            </div>
            <button type="button" data-dialog-close aria-label="Close new client form" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-800 hover:text-white">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>
        <form method="post" action="clients.php" class="grid grid-cols-1 gap-4 p-5 md:grid-cols-2">
        <?php staff_csrf_field(); ?>
        <div>
            <label for="fullname" class="mb-1 block text-sm font-medium text-slate-200">Full Name / Company <span class="text-amber-400">*</span></label>
            <input id="fullname" name="fullname" type="text" required minlength="2" maxlength="150" value="<?php echo staff_h($_POST['fullname'] ?? ''); ?>" class="<?php echo staff_input_class(); ?>">
        </div>
        <div>
            <label for="email" class="mb-1 block text-sm font-medium text-slate-200">Email Address</label>
            <input id="email" name="email" type="email" maxlength="150" value="<?php echo staff_h($_POST['email'] ?? ''); ?>" class="<?php echo staff_input_class(); ?>">
        </div>
        <div>
            <label for="phone" class="mb-1 block text-sm font-medium text-slate-200">Phone Number</label>
            <input id="phone" name="phone" type="text" inputmode="numeric" pattern="[0-9]{0,11}" maxlength="11" autocomplete="tel-national" data-phone-input title="Enter up to 11 digits only." value="<?php echo staff_h($_POST['phone'] ?? ''); ?>" class="<?php echo staff_input_class(); ?>">
            <p class="mt-1 text-xs text-slate-400">Enter up to 11 digits only (example: 09123456789).</p>
        </div>
        <div>
            <label for="address" class="mb-1 block text-sm font-medium text-slate-200">Physical Address</label>
            <input id="address" name="address" type="text" value="<?php echo staff_h($_POST['address'] ?? ''); ?>" class="<?php echo staff_input_class(); ?>">
        </div>
        <div class="md:col-span-2">
            <button type="submit" class="rounded-lg bg-gradient-to-r from-amber-600 to-amber-500 px-5 py-2.5 text-sm font-semibold text-slate-950 transition hover:from-amber-500 hover:to-amber-400">Save Client</button>
        </div>
        </form>
    </section>
</dialog>

<section class="<?php echo staff_panel_class(); ?>">
    <?php staff_panel_heading('Registered Client Accounts'); ?>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[760px] text-left text-sm text-slate-300">
            <thead class="bg-slate-950/60 text-xs uppercase tracking-wider text-amber-400/80">
                <tr><th class="p-4">Client ID</th><th class="p-4">Full Name / Company</th><th class="p-4">Email Address</th><th class="p-4">Phone Number</th><th class="p-4">Physical Address</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                <?php if ($clients === []): ?>
                    <?php staff_table_empty_row(5, 'No registered Client accounts found.'); ?>
                <?php else: foreach ($clients as $client): ?>
                    <tr class="hover:bg-slate-800/40">
                        <td class="p-4 font-mono text-amber-300">#<?php echo (int) $client['client_id']; ?></td>
                        <td class="p-4 font-semibold text-white"><?php echo staff_h($client['fullname']); ?></td>
                        <td class="p-4"><?php echo staff_h($client['email'] ?: '—'); ?></td>
                        <td class="p-4"><?php echo staff_h($client['phone'] ?: '—'); ?></td>
                        <td class="p-4"><?php echo staff_h($client['address'] ?: '—'); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="<?php echo staff_panel_class(); ?>">
        <?php staff_panel_heading('Client Intake Records '); ?>
    <p class="px-5 pb-4 text-sm text-slate-400">These staff-entered records are not linked to a Client user account and are excluded from dashboard client totals.</p>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[760px] text-left text-sm text-slate-300">
            <thead class="bg-slate-950/60 text-xs uppercase tracking-wider text-amber-400/80">
                <tr><th class="p-4">Record ID</th><th class="p-4">Full Name / Company</th><th class="p-4">Email Address</th><th class="p-4">Phone Number</th><th class="p-4">Physical Address</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                <?php if ($intakeRecords === []): ?>
                    <?php staff_table_empty_row(5, 'No unlinked client intake records.'); ?>
                <?php else: foreach ($intakeRecords as $client): ?>
                    <tr class="hover:bg-slate-800/40">
                        <td class="p-4 font-mono text-amber-300">#<?php echo (int) $client['client_id']; ?></td>
                        <td class="p-4 font-semibold text-white"><?php echo staff_h($client['fullname']); ?></td>
                        <td class="p-4"><?php echo staff_h($client['email'] ?: '—'); ?></td>
                        <td class="p-4"><?php echo staff_h($client['phone'] ?: '—'); ?></td>
                        <td class="p-4"><?php echo staff_h($client['address'] ?: '—'); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php staff_layout_end(); ?>
