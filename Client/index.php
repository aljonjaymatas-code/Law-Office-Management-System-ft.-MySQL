<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../config/notifications.php';

$user = client_current_user($pdo);
if ($user === null) {
    unset($_SESSION['client_user_id']);
    header('Location: login.php');
    exit;
}

$attorneysStatement = $pdo->query(
    "SELECT user_id, username, email
     FROM users
     WHERE LOWER(TRIM(role)) IN ('lawyer', 'attorney')
     ORDER BY username ASC, user_id ASC"
);
$attorneys = $attorneysStatement->fetchAll();

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'request_document_access'
) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    $documentId = filter_var($_POST['document_id'] ?? null, FILTER_VALIDATE_INT);

    if (!client_csrf_is_valid($_POST['_csrf'] ?? null)) {
        http_response_code(403);
        echo json_encode(['error' => 'Your session expired. Refresh the page and try again.']);
        exit;
    }
    if ($documentId === false || $documentId === null || $documentId < 1) {
        http_response_code(400);
        echo json_encode(['error' => 'Select a valid case document.']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        $documentStatement = $pdo->prepare(
            'SELECT d.document_id, d.document_name, c.title AS case_title
             FROM documents d
             INNER JOIN cases c ON c.case_id = d.case_id
             WHERE d.document_id = :document_id AND c.client_id = :client_id
             FOR UPDATE'
        );
        $documentStatement->execute([
            'document_id' => $documentId,
            'client_id' => (int) $user['client_id'],
        ]);
        $document = $documentStatement->fetch();

        if (!is_array($document)) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['error' => 'The requested document was not found for your case.']);
            exit;
        }

        $pendingRequest = $pdo->prepare(
            "SELECT request_id
             FROM document_access_requests
             WHERE document_id = :document_id
               AND client_id = :client_id
               AND status = 'Pending'
             ORDER BY request_id DESC
             LIMIT 1"
        );
        $pendingRequest->execute([
            'document_id' => $documentId,
            'client_id' => (int) $user['client_id'],
        ]);

        if ($pendingRequest->fetchColumn() === false) {
            $requestInsert = $pdo->prepare(
                "INSERT INTO document_access_requests (document_id, client_id, status)
                 VALUES (:document_id, :client_id, 'Pending')"
            );
            $requestInsert->execute([
                'document_id' => $documentId,
                'client_id' => (int) $user['client_id'],
            ]);
            $requestId = (int) $pdo->lastInsertId();
            create_staff_notifications(
                $pdo,
                null,
                'document_access_requested',
                'Client requested document access',
                $user['fullname'] . ' requested access to "' . $document['document_name']
                    . '" for case ' . $document['case_title'] . '.',
                $requestId
            );
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'access_status' => 'Requested']);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
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
    $preferredAttorneySelection = is_string($preferredAttorneyInput) ? trim($preferredAttorneyInput) : null;
    $preferredAttorney = '';
    $selectedAttorneyIsValid = $preferredAttorneySelection === '';
    foreach ($attorneys as $attorney) {
        if (
            $preferredAttorneySelection !== null
            && $preferredAttorneySelection !== ''
            && (string) $attorney['user_id'] === $preferredAttorneySelection
        ) {
            $preferredAttorney = (string) $attorney['username'];
            $selectedAttorneyIsValid = true;
            break;
        }
    }
    $issueDescription = is_string($issueDescriptionInput) ? trim($issueDescriptionInput) : '';
    $allowedTypes = ['Family Law', 'Criminal Law', 'Real Estate Law', 'Business Law', 'Other'];
    $appointmentType = $matterType === 'Other' ? $otherMatter : $matterType;
    $appointmentFormValues = [
        'matter_type' => in_array($matterType, $allowedTypes, true) ? $matterType : 'Family Law',
        'other_matter' => $otherMatter,
        'appointment_date' => is_string($appointmentDateInput) ? $appointmentDateInput : '',
        'preferred_attorney' => $preferredAttorneySelection ?? '',
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
        || !$selectedAttorneyIsValid
        || strlen($issueDescription) > 2000
    ) {
        $appointmentNotice = !$selectedAttorneyIsValid
            ? 'Choose an Attorney from the available list, or leave the field blank.'
            : 'Confirm the terms, select a valid legal matter type, and keep the optional details within their length limits.';
        $appointmentNoticeType = 'error';
    } else {
        $pdo->beginTransaction();
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
        create_staff_notifications(
            $pdo,
            (int) $pdo->lastInsertId(),
            'appointment_requested',
            'New appointment request',
            $user['fullname'] . ' requested ' . $appointmentType . ' on ' . $appointmentDate->format('M j, Y g:i A') . '.'
        );
        $pdo->commit();
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
$notificationsStatement = $pdo->prepare(
    'SELECT notification_id, notification_type, title, message, created_at, read_at
     FROM notifications
     WHERE user_id = :user_id
     ORDER BY created_at DESC, notification_id DESC
     LIMIT 50'
);
$notificationsStatement->execute(['user_id' => (int) $user['user_id']]);
$portalNotifications = array_map(
    static function (array $notification): array {
        $notification['notification_id'] = (int) $notification['notification_id'];
        $notification['read'] = $notification['read_at'] !== null;
        return $notification;
    },
    $notificationsStatement->fetchAll()
);

$documentsStatement = $pdo->prepare(
    "SELECT d.document_id, d.document_name, d.upload_date, c.title AS case_title,
            COALESCE(
                (
                    SELECT dar.status
                    FROM document_access_requests dar
                    WHERE dar.document_id = d.document_id AND dar.client_id = :document_client_id
                    ORDER BY dar.request_id DESC
                    LIMIT 1
                ),
                'Locked'
            ) AS access_status
     FROM documents d
     INNER JOIN cases c ON c.case_id = d.case_id
     WHERE c.client_id = :case_client_id
     ORDER BY d.upload_date DESC, d.document_id DESC"
);
$documentsStatement->execute([
    'document_client_id' => (int) $user['client_id'],
    'case_client_id' => (int) $user['client_id'],
]);
$documents = array_map(
    static function (array $document): array {
        return [
            'document_id' => (int) $document['document_id'],
            'document_name' => $document['document_name'],
            'category' => 'Case File: ' . $document['case_title'],
            'upload_date' => $document['upload_date'],
            'access_status' => $document['access_status'],
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
    'attorneys' => array_map(
        static function (array $attorney): array {
            return [
                'user_id' => (int) $attorney['user_id'],
                'username' => $attorney['username'],
                'email' => $attorney['email'],
            ];
        },
        $attorneys
    ),
    'upcomingAppointments' => $upcomingAppointments,
    'appointmentNotice' => $appointmentNotice,
    'appointmentNoticeType' => $appointmentNoticeType,
    'appointmentFormValues' => $appointmentFormValues,
    'csrfToken' => client_csrf_token(),
    'notifications' => $portalNotifications,
    'documents' => $documents,
];

define('CLIENT_PORTAL_ACCESS', true);
require __DIR__ . '/portal-shell.php';