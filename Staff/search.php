<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_layout.php';

$user = staff_require_auth($pdo);
$rawQuery = $_GET['q'] ?? '';
$query = is_string($rawQuery) ? trim($rawQuery) : '';
$error = is_string($rawQuery) ? '' : 'Enter a valid search term.';
$results = [
    'clients' => [],
    'cases' => [],
    'appointments' => [],
    'documents' => [],
];

if (mb_strlen($query, 'UTF-8') > 100) {
    $error = 'Search terms must be 100 characters or fewer.';
} elseif ($query !== '') {
    $term = '%' . strtr($query, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';

    $searches = [
        'clients' => [
            'sql' => "SELECT client_id, fullname, email, phone
                      FROM clients
                      WHERE fullname LIKE ? ESCAPE '!'
                         OR email LIKE ? ESCAPE '!'
                         OR phone LIKE ? ESCAPE '!'
                      ORDER BY fullname ASC
                      LIMIT 25",
            'parameters' => [$term, $term, $term],
        ],
        'cases' => [
            'sql' => "SELECT c.case_id, c.case_number, c.title, c.status, c.filed_date,
                             cl.fullname AS client_name
                      FROM cases c
                      LEFT JOIN clients cl ON cl.client_id = c.client_id
                      WHERE c.case_number LIKE ? ESCAPE '!'
                         OR c.title LIKE ? ESCAPE '!'
                         OR c.description LIKE ? ESCAPE '!'
                         OR c.status LIKE ? ESCAPE '!'
                         OR cl.fullname LIKE ? ESCAPE '!'
                      ORDER BY c.filed_date DESC, c.case_id DESC
                      LIMIT 25",
            'parameters' => [$term, $term, $term, $term, $term],
        ],
        'appointments' => [
            'sql' => "SELECT a.appointment_id, a.title, a.appointment_date, a.type, a.status,
                             cl.fullname AS client_name, c.case_number
                      FROM appointments a
                      LEFT JOIN clients cl ON cl.client_id = a.client_id
                      LEFT JOIN cases c ON c.case_id = a.case_id
                      WHERE a.title LIKE ? ESCAPE '!'
                         OR a.type LIKE ? ESCAPE '!'
                         OR a.status LIKE ? ESCAPE '!'
                         OR cl.fullname LIKE ? ESCAPE '!'
                         OR c.case_number LIKE ? ESCAPE '!'
                      ORDER BY a.appointment_date DESC, a.appointment_id DESC
                      LIMIT 25",
            'parameters' => [$term, $term, $term, $term, $term],
        ],
        'documents' => [
            'sql' => "SELECT d.document_id, d.document_name, d.upload_date,
                             c.case_number, c.title AS case_title, cl.fullname AS client_name
                      FROM documents d
                      INNER JOIN cases c ON c.case_id = d.case_id
                      INNER JOIN clients cl ON cl.client_id = c.client_id
                      WHERE d.document_name LIKE ? ESCAPE '!'
                         OR c.case_number LIKE ? ESCAPE '!'
                         OR c.title LIKE ? ESCAPE '!'
                         OR cl.fullname LIKE ? ESCAPE '!'
                      ORDER BY d.upload_date DESC, d.document_id DESC
                      LIMIT 25",
            'parameters' => [$term, $term, $term, $term],
        ],
    ];

    foreach ($searches as $category => $search) {
        $statement = $pdo->prepare($search['sql']);
        $statement->execute($search['parameters']);
        $results[$category] = $statement->fetchAll();
    }
}

$isJsonRequest = is_string($_GET['format'] ?? null) && $_GET['format'] === 'json';
if ($isJsonRequest) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    if ($error !== '') {
        http_response_code(400);
        echo json_encode(['error' => $error, 'results' => []], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }

    $suggestions = [];
    foreach (['clients', 'cases', 'appointments', 'documents'] as $category) {
        foreach (array_slice($results[$category], 0, 3) as $result) {
            switch ($category) {
                case 'clients':
                    $id = (int) $result['client_id'];
                    $title = (string) $result['fullname'];
                    $details = trim((string) ($result['email'] ?? '') . ' · ' . (string) ($result['phone'] ?? ''), ' ·');
                    break;
                case 'cases':
                    $id = (int) $result['case_id'];
                    $title = (string) $result['case_number'] . ' — ' . (string) $result['title'];
                    $details = (string) ($result['client_name'] ?: 'No client') . ' · ' . (string) $result['status'];
                    break;
                case 'appointments':
                    $id = (int) $result['appointment_id'];
                    $title = (string) $result['title'];
                    $details = (string) ($result['client_name'] ?: 'No client')
                        . ' · ' . date('M j, Y g:i A', strtotime((string) $result['appointment_date']));
                    break;
                default:
                    $id = (int) $result['document_id'];
                    $title = (string) $result['document_name'];
                    $details = (string) $result['case_number'] . ' — ' . (string) $result['case_title'];
                    break;
            }

            $suggestions[] = [
                'category' => rtrim(ucfirst($category), 's'),
                'title' => $title,
                'details' => $details,
                'href' => 'search.php?' . http_build_query(['q' => $query]) . '#' . rtrim($category, 's') . '-' . $id,
            ];
        }
    }

    echo json_encode(
        ['error' => null, 'results' => $suggestions],
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
    exit;
}

$totalResults = array_sum(array_map('count', $results));
staff_layout_start('Search Results', $user, 'search.php', '', $error);
?>
<?php if ($query === '' && $error === ''): ?>
    <section class="<?php echo staff_panel_class(); ?>">
        <p class="p-5 text-sm text-slate-300">Enter a search term above to find clients, cases, appointments, and documents.</p>
    </section>
<?php elseif ($error === ''): ?>
    <p class="text-sm text-slate-400">
        <?php echo staff_h(number_format($totalResults)); ?> result<?php echo $totalResults === 1 ? '' : 's'; ?>
        for <span class="font-semibold text-white">&ldquo;<?php echo staff_h($query); ?>&rdquo;</span>
        <span>(showing up to 25 matches per category)</span>
    </p>

    <?php
    $sections = [
        'clients' => ['Clients', 'No matching clients.'],
        'cases' => ['Cases', 'No matching cases.'],
        'appointments' => ['Appointments', 'No matching appointments.'],
        'documents' => ['Documents', 'No matching documents.'],
    ];
    foreach ($sections as $category => [$heading, $emptyMessage]):
    ?>
        <section class="<?php echo staff_panel_class(); ?>">
            <div class="flex items-center justify-between gap-4 border-b border-slate-800 p-5">
                <h2 class="font-bold tracking-wide text-white"><?php echo staff_h($heading); ?></h2>
                <span class="text-sm text-slate-400"><?php echo staff_h(count($results[$category])); ?> found</span>
            </div>
            <?php if ($results[$category] === []): ?>
                <p class="p-5 text-sm text-slate-400"><?php echo staff_h($emptyMessage); ?></p>
            <?php else: ?>
                <ul class="divide-y divide-slate-800/60">
                    <?php foreach ($results[$category] as $result): ?>
                        <?php if ($category === 'clients'): ?>
                            <li id="client-<?php echo (int) $result['client_id']; ?>" class="scroll-mt-24 p-5 transition hover:bg-slate-800/40">
                                <a href="search.php?q=<?php echo rawurlencode($query); ?>#client-<?php echo (int) $result['client_id']; ?>" class="font-semibold text-amber-300 hover:underline"><?php echo staff_h($result['fullname']); ?></a>
                                <p class="mt-1 text-sm text-slate-400"><?php echo staff_h($result['email'] ?: 'No email'); ?> · <?php echo staff_h($result['phone'] ?: 'No phone'); ?></p>
                            </li>
                        <?php elseif ($category === 'cases'): ?>
                            <li id="case-<?php echo (int) $result['case_id']; ?>" class="scroll-mt-24 p-5 transition hover:bg-slate-800/40">
                                <a href="search.php?q=<?php echo rawurlencode($query); ?>#case-<?php echo (int) $result['case_id']; ?>" class="font-semibold text-amber-300 hover:underline"><?php echo staff_h($result['case_number'] . ' — ' . $result['title']); ?></a>
                                <p class="mt-1 text-sm text-slate-400"><?php echo staff_h($result['client_name'] ?: 'No client'); ?> · <?php echo staff_h($result['status']); ?> · Filed <?php echo staff_h($result['filed_date']); ?></p>
                            </li>
                        <?php elseif ($category === 'appointments'): ?>
                            <li id="appointment-<?php echo (int) $result['appointment_id']; ?>" class="scroll-mt-24 p-5 transition hover:bg-slate-800/40">
                                <a href="search.php?q=<?php echo rawurlencode($query); ?>#appointment-<?php echo (int) $result['appointment_id']; ?>" class="font-semibold text-amber-300 hover:underline"><?php echo staff_h($result['title']); ?></a>
                                <p class="mt-1 text-sm text-slate-400"><?php echo staff_h($result['client_name'] ?: 'No client'); ?> · <?php echo staff_h($result['case_number'] ?: 'No case'); ?> · <?php echo staff_h(date('M j, Y g:i A', strtotime($result['appointment_date']))); ?> · <?php echo staff_h($result['type']); ?> · <?php echo staff_h($result['status']); ?></p>
                            </li>
                        <?php else: ?>
                            <li id="document-<?php echo (int) $result['document_id']; ?>" class="scroll-mt-24 p-5 transition hover:bg-slate-800/40">
                                <a href="search.php?q=<?php echo rawurlencode($query); ?>#document-<?php echo (int) $result['document_id']; ?>" class="font-semibold text-amber-300 hover:underline"><?php echo staff_h($result['document_name']); ?></a>
                                <p class="mt-1 text-sm text-slate-400"><?php echo staff_h($result['case_number'] . ' — ' . $result['case_title']); ?> · <?php echo staff_h($result['client_name']); ?> · Uploaded <?php echo staff_h(date('M j, Y', strtotime($result['upload_date']))); ?></p>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
<?php endif; ?>
<?php staff_layout_end(); ?>
