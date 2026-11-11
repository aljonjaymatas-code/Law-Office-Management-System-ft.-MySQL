<?php
require_once __DIR__ . '/_bootstrap.php';

$user = client_current_user($pdo);
if ($user === null) {
    unset($_SESSION['client_user_id']);
    header('Location: login.php');
    exit;
}

$appointmentNotice = '';
$appointmentNoticeType = 'success';
$appointmentFormValues = [
    'matter_type' => 'Family Law',
    'other_matter' => '',
    'appointment_date' => '',
    'preferred_attorney' => '',
    'issue_description' => '',
    'confirmed' => false,
];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointmentDateInput = $_POST['appointment_date'] ?? null;
    $matterTypeInput = $_POST['matter_type'] ?? null;
    $otherMatterInput = $_POST['other_matter'] ?? null;
    $preferredAttorneyInput = $_POST['preferred_attorney'] ?? null;
    $issueDescriptionInput = $_POST['issue_description'] ?? null;
    $appointmentDate = is_string($appointmentDateInput)
        ? DateTime::createFromFormat('!Y-m-d\TH:i', $appointmentDateInput)
        : false;
    $dateErrors = DateTime::getLastErrors();
    $matterType = is_string($matterTypeInput) ? trim($matterTypeInput) : '';
    $otherMatter = is_string($otherMatterInput) ? trim($otherMatterInput) : '';
    $preferredAttorney = is_string($preferredAttorneyInput) ? trim($preferredAttorneyInput) : '';
    $issueDescription = is_string($issueDescriptionInput) ? trim($issueDescriptionInput) : '';
    $allowedTypes = ['Family Law', 'Criminal Law', 'Real Estate Law', 'Business Law', 'Other'];
    $appointmentType = $matterType === 'Other' ? $otherMatter : $matterType;
    $appointmentFormValues = [
        'matter_type' => in_array($matterType, $allowedTypes, true) ? $matterType : 'Family Law',
        'other_matter' => $otherMatter,
        'appointment_date' => is_string($appointmentDateInput) ? $appointmentDateInput : '',
        'preferred_attorney' => $preferredAttorney,
        'issue_description' => $issueDescription,
        'confirmed' => ($_POST['confirmed'] ?? null) === '1',
    ];

    if (!client_csrf_is_valid($_POST['_csrf'] ?? null)) {
        $appointmentNotice = 'Your session has expired. Refresh the page and try again.';
        $appointmentNoticeType = 'error';
    } elseif (
        !$appointmentDate
        || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
        || $appointmentDate <= new DateTime()
    ) {
        $appointmentNotice = 'Choose a valid future date and time for your appointment request.';
        $appointmentNoticeType = 'error';
    } elseif (
        ($_POST['confirmed'] ?? null) !== '1'
        || !in_array($matterType, $allowedTypes, true)
        || $appointmentType === ''
        || strlen($appointmentType) > 50
        || strlen($preferredAttorney) > 150
        || strlen($issueDescription) > 2000
    ) {
        $appointmentNotice = 'Confirm the terms, select a valid legal matter type, and keep the optional details within their length limits.';
        $appointmentNoticeType = 'error';
    } else {
        $insert = $pdo->prepare(
            'INSERT INTO appointments (
                case_id, client_id, user_id, title, appointment_date, type, status,
                preferred_attorney, issue_description
             )
             VALUES (NULL, :client_id, NULL, :title, :appointment_date, :type, :status,
                :preferred_attorney, :issue_description)'
        );
        $insert->execute([
            'client_id' => (int) $user['client_id'],
            'title' => $appointmentType . ' Consultation',
            'appointment_date' => $appointmentDate->format('Y-m-d H:i:s'),
            'type' => $appointmentType,
            'status' => 'Pending',
            'preferred_attorney' => $preferredAttorney !== '' ? $preferredAttorney : null,
            'issue_description' => $issueDescription !== '' ? $issueDescription : null,
        ]);
        $_SESSION['client_flash'] = 'Appointment request sent to the staff. It is pending confirmation.';
        header('Location: index.php');
        exit;
    }
}

$appointmentNotice = $appointmentNotice !== '' ? $appointmentNotice : client_take_flash();

$casesStatement = $pdo->prepare(
    'SELECT case_id, case_number, title, status, filed_date
     FROM cases
     WHERE client_id = :client_id
     ORDER BY filed_date DESC, case_id DESC'
);
$casesStatement->execute(['client_id' => (int) $user['client_id']]);
$activeCases = $casesStatement->fetchAll();

$appointmentsStatement = $pdo->prepare(
    'SELECT appointment_id, title, appointment_date, type, status
     FROM appointments
     WHERE client_id = :client_id
       AND appointment_date >= CURRENT_TIMESTAMP
       AND status IN (\'Pending\', \'Confirmed\', \'Scheduled\')
     ORDER BY appointment_date ASC, appointment_id ASC'
);
$appointmentsStatement->execute(['client_id' => (int) $user['client_id']]);
$upcomingAppointments = $appointmentsStatement->fetchAll();

$documentsStatement = $pdo->prepare(
    'SELECT d.document_id, d.document_name, d.upload_date, c.title AS case_title
     FROM documents d
     INNER JOIN cases c ON c.case_id = d.case_id
     WHERE c.client_id = :client_id
     ORDER BY d.upload_date DESC, d.document_id DESC'
);
$documentsStatement->execute(['client_id' => (int) $user['client_id']]);
$documents = array_map(
    static function (array $document): array {
        return [
            'document_id' => (int) $document['document_id'],
            'document_name' => $document['document_name'],
            'category' => 'Case File: ' . $document['case_title'],
            'upload_date' => $document['upload_date'],
            'access_status' => 'Locked',
            'preview' => [
                'Confidential case document',
                $document['case_title'],
                'Contact your attorney or office staff to access the original file.',
            ],
        ];
    },
    $documentsStatement->fetchAll()
);

$clientPortalData = [
    'profile' => [
        'fullname' => $user['fullname'],
        'username' => $user['username'],
        'email' => $user['email'],
        'phone' => $user['phone'] ?? '',
        'address' => $user['address'] ?? '',
    ],
    'activeCases' => $activeCases,
    'upcomingAppointments' => $upcomingAppointments,
    'appointmentNotice' => $appointmentNotice,
    'appointmentNoticeType' => $appointmentNoticeType,
    'appointmentFormValues' => $appointmentFormValues,
    'csrfToken' => client_csrf_token(),
    'documents' => $documents,
];

define('CLIENT_PORTAL_ACCESS', true);
require __DIR__ . '/portal-shell.php';