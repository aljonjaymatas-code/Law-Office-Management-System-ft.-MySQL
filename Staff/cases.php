<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_layout.php';

$user = staff_require_auth($pdo);

$registeredClients = $pdo->query(
    "SELECT c.client_id, c.fullname, c.email, c.phone, c.address
     FROM clients c
     INNER JOIN users u ON u.user_id = c.user_id
     WHERE LOWER(TRIM(u.role)) = 'client'
     ORDER BY c.fullname ASC, c.client_id ASC"
)->fetchAll();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $caseNumber = trim((string) ($_POST['case_number'] ?? ''));
    $title = trim((string) ($_POST['title'] ?? ''));
    $clientName = trim((string) ($_POST['client_name'] ?? ''));
    $registeredClientIdInput = is_string($_POST['registered_client_id'] ?? null)
        ? trim($_POST['registered_client_id'])
        : '';
    $registeredClientId = $registeredClientIdInput === ''
        ? null
        : filter_var($registeredClientIdInput, FILTER_VALIDATE_INT);
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
    } elseif ($registeredClientId === false || ($registeredClientId !== null && $registeredClientId < 1)) {
        $error = 'Select a valid registered Client account.';
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
                if ($registeredClientId !== null) {
                    $clientLookup = $pdo->prepare(
                        "SELECT c.client_id, c.fullname
                         FROM clients c
                         INNER JOIN users u ON u.user_id = c.user_id
                         WHERE c.client_id = :client_id
                           AND LOWER(TRIM(u.role)) = 'client'
                           AND LOWER(TRIM(c.fullname)) = LOWER(TRIM(:fullname))
                         FOR UPDATE"
                    );
                    $clientLookup->execute([
                        'client_id' => $registeredClientId,
                        'fullname' => $clientName,
                    ]);
                    $registeredClient = $clientLookup->fetch();

                    if (!is_array($registeredClient)) {
                        $pdo->rollBack();
                        $error = 'Select a registered Client from the suggestions so the case is linked to the correct account.';
                    } else {
                        $clientId = (int) $registeredClient['client_id'];
                    }
                } else {
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
                        $error = 'More than one Client account has that name. Select the correct account from the suggestions.';
                    } elseif (count($matchingClientIds) === 1) {
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
                }

                if ($error === '') {
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
                    staff_redirect_with_flash('Case record saved successfully and submitted to the assigned Attorney for review.');
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
            c.case_review_status, c.case_review_comment,
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
        <div class="relative">
            <label for="client_name" class="mb-1 block text-sm font-medium text-slate-200">Client Name <span class="text-amber-400">*</span></label>
            <input id="registered_client_id" name="registered_client_id" type="hidden" value="<?php echo staff_h($_POST['registered_client_id'] ?? ''); ?>">
            <input
                id="client_name"
                name="client_name"
                type="text"
                required
                maxlength="150"
                value="<?php echo staff_h($_POST['client_name'] ?? ''); ?>"
                class="<?php echo staff_input_class(); ?>"
                autocomplete="off"
                role="combobox"
                aria-autocomplete="list"
                aria-expanded="false"
                aria-controls="registered-client-suggestions"
                aria-activedescendant="">
            <div id="registered-client-dropdown" class="absolute left-0 right-0 top-full z-50 mt-2 hidden overflow-hidden rounded-xl border border-slate-700 bg-slate-900 shadow-2xl">
                <ul id="registered-client-suggestions" class="max-h-60 overflow-y-auto py-1" role="listbox" aria-label="Registered Client accounts"></ul>
                <p id="registered-client-status" class="hidden px-4 py-3 text-sm text-slate-400" role="status"></p>
            </div>
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
    <div class="mb-5 flex flex-col gap-3 rounded-xl border border-amber-500/20 bg-amber-500/5 p-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="font-semibold text-white">Attach case documents</h2>
            <p class="mt-1 text-sm text-slate-400">After creating a case, upload its documents for Attorney review.</p>
        </div>
        <a href="documents.php" class="inline-flex shrink-0 items-center justify-center rounded-lg border border-amber-500/40 px-4 py-2 text-sm font-semibold text-amber-300 transition hover:bg-amber-500/10">Upload Document</a>
    </div>
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
                    <th class="p-4">Attorney Review</th>
                    <th class="p-4">Filed Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                <?php if ($cases === []): ?>
                    <?php staff_table_empty_row(7, 'No cases have been recorded.'); ?>
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
                        <td class="p-4">
                            <span class="rounded-full px-2.5 py-1 text-xs <?php echo $case['case_review_status'] === 'Approved' ? 'bg-emerald-500/10 text-emerald-300' : ($case['case_review_status'] === 'Declined' ? 'bg-red-500/10 text-red-300' : 'bg-amber-500/10 text-amber-300'); ?>">
                                <?php echo staff_h($case['case_review_status'] ?: 'Pending Review'); ?>
                            </span>
                            <?php if ($case['case_review_comment'] !== null && $case['case_review_comment'] !== ''): ?>
                                <p class="mt-2 max-w-xs whitespace-pre-wrap text-xs text-slate-400"><?php echo staff_h($case['case_review_comment']); ?></p>
                            <?php endif; ?>
                        </td>
                        <td class="p-4"><?php echo staff_h($case['filed_date']); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
<script>
    (() => {
        const clients = <?php echo json_encode($registeredClients, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const input = document.getElementById('client_name');
        const selectedClientId = document.getElementById('registered_client_id');
        const dropdown = document.getElementById('registered-client-dropdown');
        const list = document.getElementById('registered-client-suggestions');
        const status = document.getElementById('registered-client-status');
        const emailInput = document.getElementById('client_email');
        const phoneInput = document.getElementById('client_phone');
        const addressInput = document.getElementById('client_address');
        let activeIndex = -1;
        let matches = [];

        const closeSuggestions = () => {
            dropdown.classList.add('hidden');
            list.replaceChildren();
            input.setAttribute('aria-expanded', 'false');
            input.setAttribute('aria-activedescendant', '');
            activeIndex = -1;
            matches = [];
        };

        const setActiveOption = (index) => {
            const options = Array.from(list.querySelectorAll('[role="option"]'));
            if (options.length === 0) return;
            activeIndex = (index + options.length) % options.length;
            options.forEach((option, optionIndex) => {
                const isActive = optionIndex === activeIndex;
                option.setAttribute('aria-selected', String(isActive));
                option.classList.toggle('bg-slate-800', isActive);
                if (isActive) {
                    input.setAttribute('aria-activedescendant', option.id);
                    option.scrollIntoView({ block: 'nearest' });
                }
            });
        };

        const selectClient = (client) => {
            input.value = client.fullname;
            selectedClientId.value = String(client.client_id);
            emailInput.value = client.email || '';
            phoneInput.value = client.phone || '';
            addressInput.value = client.address || '';
            closeSuggestions();
        };

        const renderSuggestions = () => {
            const query = input.value.trim().toLocaleLowerCase();
            list.replaceChildren();
            status.classList.add('hidden');
            matches = query.length > 0
                ? clients.filter((client) => (
                    String(client.fullname).toLocaleLowerCase().includes(query)
                    || String(client.email || '').toLocaleLowerCase().includes(query)
                )).slice(0, 8)
                : [];

            if (query.length === 0) {
                closeSuggestions();
                return;
            }

            dropdown.classList.remove('hidden');
            input.setAttribute('aria-expanded', 'true');
            if (matches.length === 0) {
                status.textContent = 'No registered Client accounts match. You can continue to create a separate intake record.';
                status.classList.remove('hidden');
                return;
            }

            matches.forEach((client, index) => {
                const option = document.createElement('li');
                option.id = `registered-client-option-${client.client_id}`;
                option.setAttribute('role', 'option');
                option.setAttribute('aria-selected', 'false');
                option.className = 'cursor-pointer px-4 py-3 text-sm text-slate-200 transition hover:bg-slate-800 focus:bg-slate-800';

                const name = document.createElement('span');
                name.className = 'block font-medium text-white';
                name.textContent = client.fullname;
                option.append(name);

                if (client.email) {
                    const email = document.createElement('span');
                    email.className = 'mt-1 block text-xs text-slate-400';
                    email.textContent = client.email;
                    option.append(email);
                }

                option.addEventListener('pointerdown', (event) => event.preventDefault());
                option.addEventListener('click', () => selectClient(client));
                list.append(option);
                if (index === 0) setActiveOption(0);
            });
        };

        input.addEventListener('input', () => {
            if (selectedClientId.value !== '') {
                emailInput.value = '';
                phoneInput.value = '';
                addressInput.value = '';
            }
            selectedClientId.value = '';
            renderSuggestions();
        });
        input.addEventListener('focus', renderSuggestions);
        input.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown' && matches.length > 0) {
                event.preventDefault();
                setActiveOption(activeIndex + 1);
            } else if (event.key === 'ArrowUp' && matches.length > 0) {
                event.preventDefault();
                setActiveOption(activeIndex < 0 ? matches.length - 1 : activeIndex - 1);
            } else if (event.key === 'Enter' && activeIndex >= 0 && matches[activeIndex]) {
                event.preventDefault();
                selectClient(matches[activeIndex]);
            } else if (event.key === 'Escape') {
                closeSuggestions();
            }
        });
        document.addEventListener('pointerdown', (event) => {
            if (!dropdown.contains(event.target) && event.target !== input) closeSuggestions();
        });

        const initialClient = clients.find((client) => String(client.client_id) === selectedClientId.value);
        if (initialClient && initialClient.fullname === input.value) {
            emailInput.value = initialClient.email || '';
            phoneInput.value = initialClient.phone || '';
            addressInput.value = initialClient.address || '';
        } else {
            selectedClientId.value = '';
        }
    })();
</script>
<?php staff_layout_end(); ?>
