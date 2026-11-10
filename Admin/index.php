<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/_auth.php';
session_start();

$userRole = $_SESSION['admin_portal_role'] ?? '';
if (!in_array($userRole, ['admin', 'lawyer'], true) || empty($_SESSION['admin_portal_user_id'])) {
    unset($_SESSION['admin_portal_role'], $_SESSION['admin_portal_user_id']);
    header('Location: login.php');
    exit;
}

$statement = $pdo->prepare('SELECT username, email, role FROM users WHERE user_id = :user_id');
$statement->execute(['user_id' => (int) $_SESSION['admin_portal_user_id']]);
$account = $statement->fetch();

if (!is_array($account) || admin_portal_role_for_account_role((string) $account['role']) !== $userRole) {
    unset($_SESSION['admin_portal_role'], $_SESSION['admin_portal_user_id']);
    header('Location: login.php');
    exit;
}

$appointmentActionNotice = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm_appointment') {
    $appointmentId = filter_var($_POST['appointment_id'] ?? null, FILTER_VALIDATE_INT);
    $appointmentDateInput = $_POST['appointment_date'] ?? null;
    $appointmentDate = is_string($appointmentDateInput)
        ? DateTime::createFromFormat('!Y-m-d\TH:i', $appointmentDateInput)
        : false;
    $dateErrors = DateTime::getLastErrors();

    if (
        !is_string($_POST['_csrf'] ?? null)
        || !isset($_SESSION['admin_appointment_csrf_token'])
        || !hash_equals($_SESSION['admin_appointment_csrf_token'], $_POST['_csrf'])
    ) {
        $appointmentActionNotice = ['type' => 'error', 'message' => 'Your session has expired. Refresh the page and try again.'];
    } elseif ($appointmentId === false || $appointmentId === null || $appointmentId < 1) {
        $appointmentActionNotice = ['type' => 'error', 'message' => 'Select a valid appointment request.'];
    } elseif (
        !$appointmentDate
        || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
        || $appointmentDate <= new DateTime()
    ) {
        $appointmentActionNotice = ['type' => 'error', 'message' => 'Choose a valid future date and time before confirming.'];
    } else {
        $pdo->beginTransaction();
        $pendingCheck = $pdo->prepare(
            "SELECT appointment_id
             FROM appointments
             WHERE appointment_id = :appointment_id AND status = 'Pending'
             FOR UPDATE"
        );
        $pendingCheck->execute(['appointment_id' => $appointmentId]);

        if ($pendingCheck->fetchColumn() === false) {
            $pdo->rollBack();
            $appointmentActionNotice = ['type' => 'error', 'message' => 'This appointment is no longer pending. Refresh the dashboard and try again.'];
        } else {
            $confirmStatement = $pdo->prepare(
                "UPDATE appointments
                 SET appointment_date = :appointment_date, status = 'Confirmed', user_id = :user_id
                 WHERE appointment_id = :appointment_id AND status = 'Pending'"
            );
            $confirmStatement->execute([
                'appointment_date' => $appointmentDate->format('Y-m-d H:i:s'),
                'user_id' => (int) $_SESSION['admin_portal_user_id'],
                'appointment_id' => $appointmentId,
            ]);
            $pdo->commit();
            $appointmentActionNotice = ['type' => 'success', 'message' => 'Appointment confirmed and added to the client’s Upcoming Appointments.'];
        }
    }

    $_SESSION['admin_appointment_notice'] = $appointmentActionNotice;
    header('Location: index.php?tab=schedule');
    exit;
}

$timeEntryActionNotice = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_time_entry') {
    $caseId = filter_var($_POST['case_id'] ?? null, FILTER_VALIDATE_INT);
    $hours = filter_var($_POST['hours'] ?? null, FILTER_VALIDATE_FLOAT);
    $description = trim((string) ($_POST['description'] ?? ''));
    $dateLoggedInput = $_POST['date_logged'] ?? null;
    $dateLogged = is_string($dateLoggedInput)
        ? DateTime::createFromFormat('!Y-m-d', $dateLoggedInput)
        : false;
    $dateErrors = DateTime::getLastErrors();

    if (
        !is_string($_POST['_csrf'] ?? null)
        || !isset($_SESSION['admin_time_entry_csrf_token'])
        || !hash_equals($_SESSION['admin_time_entry_csrf_token'], $_POST['_csrf'])
    ) {
        $timeEntryActionNotice = ['type' => 'error', 'message' => 'Your session has expired. Refresh the page and try again.'];
    } elseif ($caseId === false || $caseId === null || $caseId < 1) {
        $timeEntryActionNotice = ['type' => 'error', 'message' => 'Select a valid case for this time entry.'];
    } elseif ($hours === false || $hours <= 0 || $hours > 24) {
        $timeEntryActionNotice = ['type' => 'error', 'message' => 'Enter a number of hours greater than 0 and no more than 24.'];
    } elseif ($description === '' || strlen($description) > 1000) {
        $timeEntryActionNotice = ['type' => 'error', 'message' => 'Enter a work description up to 1,000 characters.'];
    } elseif (
        !$dateLogged
        || $dateLogged->format('Y-m-d') !== $dateLoggedInput
        || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
        || $dateLoggedInput > date('Y-m-d')
    ) {
        $timeEntryActionNotice = ['type' => 'error', 'message' => 'Enter a valid date that is not in the future.'];
    } else {
        $caseCheck = $pdo->prepare('SELECT case_id FROM cases WHERE case_id = :case_id');
        $caseCheck->execute(['case_id' => $caseId]);

        if ($caseCheck->fetchColumn() === false) {
            $timeEntryActionNotice = ['type' => 'error', 'message' => 'The selected case could not be found.'];
        } else {
            $insertTimeEntry = $pdo->prepare(
                'INSERT INTO time_entries (case_id, user_id, hours, description, date_logged)
                 VALUES (:case_id, :user_id, :hours, :description, :date_logged)'
            );
            $insertTimeEntry->execute([
                'case_id' => $caseId,
                'user_id' => (int) $_SESSION['admin_portal_user_id'],
                'hours' => $hours,
                'description' => $description,
                'date_logged' => $dateLogged->format('Y-m-d'),
            ]);
            $timeEntryActionNotice = ['type' => 'success', 'message' => 'Billable hours saved to the database.'];
        }
    }

    $_SESSION['admin_time_entry_notice'] = $timeEntryActionNotice;
    header('Location: index.php?tab=hours');
    exit;
}

$appointmentActionNotice = $_SESSION['admin_appointment_notice'] ?? null;
unset($_SESSION['admin_appointment_notice']);
$timeEntryActionNotice = $_SESSION['admin_time_entry_notice'] ?? null;
unset($_SESSION['admin_time_entry_notice']);
if (!isset($_SESSION['admin_appointment_csrf_token'])) {
    $_SESSION['admin_appointment_csrf_token'] = bin2hex(random_bytes(32));
}
if (!isset($_SESSION['admin_time_entry_csrf_token'])) {
    $_SESSION['admin_time_entry_csrf_token'] = bin2hex(random_bytes(32));
}

$accountRole = strtolower(trim($account['role']));
$accountLabel = in_array($accountRole, ['admin', 'administrator', 'managing partner'], true)
    ? 'Partner / Managing Attorney'
    : 'Lawyer / Attorney';
$accountDetails = [
    'name' => $account['username'],
    'email' => $account['email'],
    'roleLabel' => $accountLabel,
];

$usersList = [];
$totalCases = (int) $pdo->query('SELECT COUNT(*) FROM cases')->fetchColumn();
$pendingAppointmentsCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM appointments WHERE status = 'Pending'"
)->fetchColumn();
$pendingAppointmentsStatement = $pdo->query(
    "SELECT a.appointment_id, a.title, a.appointment_date, a.type,
            a.preferred_attorney, a.issue_description,
            c.fullname AS client_name, cs.case_number,
            COALESCE(u.username, 'Client request') AS requested_by
     FROM appointments a
     LEFT JOIN clients c ON c.client_id = a.client_id
     LEFT JOIN cases cs ON cs.case_id = a.case_id
     LEFT JOIN users u ON u.user_id = a.user_id
     WHERE a.status = 'Pending'
     ORDER BY a.appointment_date ASC, a.appointment_id ASC"
);
$pendingAppointments = $pendingAppointmentsStatement->fetchAll();
$calendarAppointmentsStatement = $pdo->query(
    "SELECT a.appointment_id, a.title, a.appointment_date, a.type, a.status,
            c.fullname AS client_name, cs.case_number, cs.title AS case_title
     FROM appointments a
     LEFT JOIN clients c ON c.client_id = a.client_id
     LEFT JOIN cases cs ON cs.case_id = a.case_id
     WHERE a.status IN ('Pending', 'Confirmed')
     ORDER BY a.appointment_date ASC, a.appointment_id ASC"
);
$calendarAppointments = $calendarAppointmentsStatement->fetchAll();
$caseRecordsStatement = $pdo->query(
    "SELECT c.case_id, c.case_number, c.title, c.status, c.filed_date,
            cl.fullname AS client_name,
            COALESCE(NULLIF(c.assigned_lawyer_name, ''), u.username) AS lawyer_name
     FROM cases c
     LEFT JOIN clients cl ON cl.client_id = c.client_id
     LEFT JOIN users u ON u.user_id = c.assigned_lawyer_id
     ORDER BY c.filed_date DESC, c.case_id DESC"
);
$caseRecords = $caseRecordsStatement->fetchAll();
$timeEntriesStatement = $pdo->query(
    'SELECT t.time_entry_id, t.hours, t.description, t.date_logged,
            c.case_number, c.title AS case_title, u.username AS logged_by
     FROM time_entries t
     INNER JOIN cases c ON c.case_id = t.case_id
     LEFT JOIN users u ON u.user_id = t.user_id
     ORDER BY t.date_logged DESC, t.time_entry_id DESC'
);
$timeEntries = $timeEntriesStatement->fetchAll();
$totalBillableHoursYtd = (float) $pdo->query(
    'SELECT COALESCE(SUM(hours), 0) FROM time_entries WHERE YEAR(date_logged) = YEAR(CURRENT_DATE())'
)->fetchColumn();
$totalClients = (int) $pdo->query(
    'SELECT COUNT(*) FROM clients'
)->fetchColumn();
$clientRecordsStatement = $pdo->query(
    "SELECT c.client_id, c.fullname, c.email,
            COALESCE(NULLIF(u.phone, ''), c.phone) AS phone,
            COALESCE(NULLIF(u.address, ''), c.address) AS address
     FROM clients c
     INNER JOIN users u ON u.user_id = c.user_id
     WHERE LOWER(TRIM(u.role)) = 'client'
     ORDER BY c.fullname ASC, c.client_id ASC"
);
$clientRecords = $clientRecordsStatement->fetchAll();
if ($userRole === 'admin') {
    $usersStatement = $pdo->query(
        'SELECT user_id, username, email, role, CAST(created_at AS date) AS created_at FROM users ORDER BY user_id ASC'
    );
    $usersList = $usersStatement->fetchAll();
}
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Law Office Management System - Admin & Attorney Portal</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://unpkg.com/react@18/umd/react.development.js" crossorigin></script>
    <script src="https://unpkg.com/react-dom@18/umd/react-dom.development.js" crossorigin></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>

    <link rel="stylesheet" href="../CSS/styles.css">
</head>
<body class="bg-[#020617] text-slate-100 antialiased selection:bg-amber-500 selection:text-slate-950">
    <div id="root"></div>

    <script>
        window.ADMIN_USER = <?php echo json_encode($accountDetails, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        window.ADMIN_USER_ROLE = <?php echo json_encode($userRole, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        window.ADMIN_USERS = <?php echo json_encode($usersList, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        window.ADMIN_TOTAL_CASES = <?php echo json_encode($totalCases); ?>;
        window.ADMIN_PENDING_APPOINTMENTS_COUNT = <?php echo json_encode($pendingAppointmentsCount); ?>;
        window.ADMIN_PENDING_APPOINTMENTS = <?php echo json_encode($pendingAppointments, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        window.ADMIN_CALENDAR_APPOINTMENTS = <?php echo json_encode($calendarAppointments, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        window.ADMIN_APPOINTMENT_CSRF_TOKEN = <?php echo json_encode($_SESSION['admin_appointment_csrf_token']); ?>;
        window.ADMIN_APPOINTMENT_NOTICE = <?php echo json_encode($appointmentActionNotice, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        window.ADMIN_TIME_ENTRY_CSRF_TOKEN = <?php echo json_encode($_SESSION['admin_time_entry_csrf_token']); ?>;
        window.ADMIN_TIME_ENTRY_NOTICE = <?php echo json_encode($timeEntryActionNotice, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        window.ADMIN_TIME_ENTRIES = <?php echo json_encode($timeEntries, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        window.ADMIN_TOTAL_BILLABLE_HOURS_YTD = <?php echo json_encode($totalBillableHoursYtd); ?>;
        window.ADMIN_CASE_RECORDS = <?php echo json_encode($caseRecords, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        window.ADMIN_TOTAL_CLIENTS = <?php echo json_encode($totalClients); ?>;
        window.ADMIN_CLIENT_RECORDS = <?php echo json_encode($clientRecords, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    </script>
    <script src="js/admin-storage.js"></script>
    <script type="text/babel" src="js/admin-app.jsx?v=18"></script>
</body>
</html>