<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/notifications.php';
require_once __DIR__ . '/_auth.php';
session_start();

$userRole = $_SESSION['admin_portal_role'] ?? '';
if (!in_array($userRole, ['admin', 'lawyer'], true) || empty($_SESSION['admin_portal_user_id'])) {
    unset($_SESSION['admin_portal_role'], $_SESSION['admin_portal_user_id']);
    header('Location: login.php');
    exit;
}

$statement = $pdo->prepare('SELECT user_id, username, email, role FROM users WHERE user_id = :user_id');
$statement->execute(['user_id' => (int) $_SESSION['admin_portal_user_id']]);
$account = $statement->fetch();

if (!is_array($account) || admin_portal_role_for_account_role((string) $account['role']) !== $userRole) {
    unset($_SESSION['admin_portal_role'], $_SESSION['admin_portal_user_id']);
    header('Location: login.php');
    exit;
}

if (!isset($_SESSION['admin_document_review_csrf_token'])) {
    $_SESSION['admin_document_review_csrf_token'] = bin2hex(random_bytes(32));
}
if (!isset($_SESSION['admin_case_review_csrf_token'])) {
    $_SESSION['admin_case_review_csrf_token'] = bin2hex(random_bytes(32));
}
if (!isset($_SESSION['admin_case_status_csrf_token'])) {
    $_SESSION['admin_case_status_csrf_token'] = bin2hex(random_bytes(32));
}
$documentReviewActionNotice = null;
$caseReviewActionNotice = null;
$caseStatusActionNotice = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_case_status') {
    $caseId = filter_var($_POST['case_id'] ?? null, FILTER_VALIDATE_INT);
    $status = $_POST['case_status'] ?? null;
    $allowedCaseStatuses = ['Active', 'Pending', 'Closed', 'On Hold'];

    if ($userRole !== 'lawyer') {
        $caseStatusActionNotice = ['type' => 'error', 'message' => 'Only an Attorney can update an assigned case status.'];
    } elseif (
        !is_string($_POST['_csrf'] ?? null)
        || !hash_equals($_SESSION['admin_case_status_csrf_token'], $_POST['_csrf'])
    ) {
        $caseStatusActionNotice = ['type' => 'error', 'message' => 'Your session has expired. Refresh the page and try again.'];
    } elseif ($caseId === false || $caseId === null || $caseId < 1) {
        $caseStatusActionNotice = ['type' => 'error', 'message' => 'Select a valid assigned case.'];
    } elseif (!is_string($status) || !in_array($status, $allowedCaseStatuses, true)) {
        $caseStatusActionNotice = ['type' => 'error', 'message' => 'Choose Active, Pending, Closed, or On Hold.'];
    } else {
        $pdo->beginTransaction();
        try {
            $caseCheck = $pdo->prepare(
                'SELECT case_id
                 FROM cases
                 WHERE case_id = :case_id
                   AND (
                       assigned_lawyer_id = :user_id
                       OR (
                           assigned_lawyer_id IS NULL
                           AND LOWER(TRIM(assigned_lawyer_name)) = LOWER(TRIM(:username))
                       )
                   )
                 FOR UPDATE'
            );
            $caseCheck->execute([
                'case_id' => $caseId,
                'user_id' => (int) $account['user_id'],
                'username' => (string) $account['username'],
            ]);

            if ($caseCheck->fetchColumn() === false) {
                $pdo->rollBack();
                $caseStatusActionNotice = ['type' => 'error', 'message' => 'That case is not assigned to your Attorney account.'];
            } else {
                $caseUpdate = $pdo->prepare('UPDATE cases SET status = :status WHERE case_id = :case_id');
                $caseUpdate->execute(['status' => $status, 'case_id' => $caseId]);
                $pdo->commit();
                $caseStatusActionNotice = ['type' => 'success', 'message' => 'Case status updated to ' . $status . '.'];
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    $_SESSION['admin_case_status_notice'] = $caseStatusActionNotice;
    header('Location: index.php?tab=cases');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'review_case') {
    $caseId = filter_var($_POST['case_id'] ?? null, FILTER_VALIDATE_INT);
    $decision = $_POST['case_review_status'] ?? null;
    $comment = is_string($_POST['case_review_comment'] ?? null) ? trim($_POST['case_review_comment']) : '';

    if ($userRole !== 'lawyer') {
        $caseReviewActionNotice = ['type' => 'error', 'message' => 'Only the assigned Attorney can review this case.'];
    } elseif (
        !is_string($_POST['_csrf'] ?? null)
        || !hash_equals($_SESSION['admin_case_review_csrf_token'], $_POST['_csrf'])
    ) {
        $caseReviewActionNotice = ['type' => 'error', 'message' => 'Your session has expired. Refresh the page and try again.'];
    } elseif ($caseId === false || $caseId === null || $caseId < 1) {
        $caseReviewActionNotice = ['type' => 'error', 'message' => 'Select a valid case to review.'];
    } elseif (!in_array($decision, ['Approved', 'Declined'], true)) {
        $caseReviewActionNotice = ['type' => 'error', 'message' => 'Choose whether to approve or decline the case.'];
    } elseif (strlen($comment) > 2000) {
        $caseReviewActionNotice = ['type' => 'error', 'message' => 'Keep the review comment within 2,000 characters.'];
    } elseif ($decision === 'Declined' && $comment === '') {
        $caseReviewActionNotice = ['type' => 'error', 'message' => 'Add a comment explaining why the case is declined.'];
    } else {
        $caseCheck = $pdo->prepare(
            "SELECT case_id
             FROM cases
             WHERE case_id = :case_id
               AND case_review_status = 'Pending Review'
               AND (
                   assigned_lawyer_id = :user_id
                   OR (
                       assigned_lawyer_id IS NULL
                       AND LOWER(TRIM(assigned_lawyer_name)) = LOWER(TRIM(:username))
                   )
               )"
        );
        $caseCheck->execute([
            'case_id' => $caseId,
            'user_id' => (int) $account['user_id'],
            'username' => (string) $account['username'],
        ]);

        if ($caseCheck->fetchColumn() === false) {
            $caseReviewActionNotice = ['type' => 'error', 'message' => 'The case is unavailable, not assigned to you, or already reviewed.'];
        } else {
            $caseUpdate = $pdo->prepare(
                "UPDATE cases
                 SET case_review_status = :review_status,
                     case_review_comment = :review_comment,
                     case_reviewed_by = :reviewed_by,
                     case_reviewed_at = CURRENT_TIMESTAMP
                 WHERE case_id = :case_id AND case_review_status = 'Pending Review'"
            );
            $caseUpdate->execute([
                'review_status' => $decision,
                'review_comment' => $comment !== '' ? $comment : null,
                'reviewed_by' => (int) $account['user_id'],
                'case_id' => $caseId,
            ]);
            $caseReviewActionNotice = $caseUpdate->rowCount() === 1
                ? ['type' => 'success', 'message' => 'Case ' . strtolower($decision) . ' successfully.']
                : ['type' => 'error', 'message' => 'This case has already been reviewed. Refresh the list and try again.'];
        }
    }

    $_SESSION['admin_case_review_notice'] = $caseReviewActionNotice;
    header('Location: index.php?tab=cases');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'review_document') {
    $documentId = filter_var($_POST['document_id'] ?? null, FILTER_VALIDATE_INT);
    $decision = $_POST['review_status'] ?? null;
    $comment = is_string($_POST['review_comment'] ?? null) ? trim($_POST['review_comment']) : '';

    if (
        !is_string($_POST['_csrf'] ?? null)
        || !hash_equals($_SESSION['admin_document_review_csrf_token'], $_POST['_csrf'])
    ) {
        $documentReviewActionNotice = ['type' => 'error', 'message' => 'Your session has expired. Refresh the page and try again.'];
    } elseif ($documentId === false || $documentId === null || $documentId < 1) {
        $documentReviewActionNotice = ['type' => 'error', 'message' => 'Select a valid document to review.'];
    } elseif (!in_array($decision, ['Approved', 'Declined'], true)) {
        $documentReviewActionNotice = ['type' => 'error', 'message' => 'Choose whether to approve or decline the document.'];
    } elseif (strlen($comment) > 2000) {
        $documentReviewActionNotice = ['type' => 'error', 'message' => 'Keep the review comment within 2,000 characters.'];
    } elseif ($decision === 'Declined' && $comment === '') {
        $documentReviewActionNotice = ['type' => 'error', 'message' => 'Add a comment explaining why the document is declined.'];
    } else {
        $documentCheckQuery =
            'SELECT d.document_id, d.review_status
             FROM documents d
             INNER JOIN cases c ON c.case_id = d.case_id
             WHERE d.document_id = :document_id';
        $documentCheckParams = ['document_id' => $documentId];
        if ($userRole === 'lawyer') {
            $documentCheckQuery .=
                ' AND (
                    c.assigned_lawyer_id = :user_id
                    OR (
                        c.assigned_lawyer_id IS NULL
                        AND LOWER(TRIM(c.assigned_lawyer_name)) = LOWER(TRIM(:username))
                    )
                )';
            $documentCheckParams['user_id'] = (int) $account['user_id'];
            $documentCheckParams['username'] = (string) $account['username'];
        }
        $documentCheck = $pdo->prepare($documentCheckQuery);
        $documentCheck->execute($documentCheckParams);
        $document = $documentCheck->fetch();

        if (!is_array($document)) {
            $documentReviewActionNotice = ['type' => 'error', 'message' => 'The selected document could not be found.'];
        } elseif ($document['review_status'] !== 'Pending Review') {
            $documentReviewActionNotice = ['type' => 'error', 'message' => 'This document has already been reviewed.'];
        } else {
            $reviewUpdate = $pdo->prepare(
                "UPDATE documents
                 SET review_status = :review_status,
                     review_comment = :review_comment,
                     reviewed_by = :reviewed_by,
                     reviewed_at = CURRENT_TIMESTAMP
                 WHERE document_id = :document_id AND review_status = 'Pending Review'"
            );
            $reviewUpdate->execute([
                'review_status' => $decision,
                'review_comment' => $comment !== '' ? $comment : null,
                'reviewed_by' => (int) $account['user_id'],
                'document_id' => $documentId,
            ]);

            $documentReviewActionNotice = $reviewUpdate->rowCount() === 1
                ? ['type' => 'success', 'message' => 'Document ' . strtolower($decision) . ' successfully.']
                : ['type' => 'error', 'message' => 'This document has already been reviewed. Refresh the list and try again.'];
        }
    }

    $_SESSION['admin_document_review_notice'] = $documentReviewActionNotice;
    header('Location: index.php?tab=document-review');
    exit;
}

$appointmentActionNotice = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm_appointment') {
    $appointmentId = filter_var($_POST['appointment_id'] ?? null, FILTER_VALIDATE_INT);
    $appointmentDateInput = is_string($_POST['appointment_date'] ?? null) ? trim($_POST['appointment_date']) : '';
    $appointmentHour = is_string($_POST['appointment_hour'] ?? null) ? $_POST['appointment_hour'] : '';
    $appointmentMinute = is_string($_POST['appointment_minute'] ?? null) ? $_POST['appointment_minute'] : '';
    $appointmentMeridiem = is_string($_POST['appointment_meridiem'] ?? null) ? $_POST['appointment_meridiem'] : '';
    $appointmentTimeIsValid = in_array($appointmentHour, ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'], true)
        && preg_match('/\A(?:0[0-9]|[1-5][0-9])\z/', $appointmentMinute) === 1
        && in_array($appointmentMeridiem, ['AM', 'PM'], true);
    $appointmentDateTimeInput = $appointmentDateInput . ' ' . $appointmentHour . ':' . $appointmentMinute . ' ' . $appointmentMeridiem;
    $appointmentDate = $appointmentTimeIsValid
        ? DateTime::createFromFormat('!Y-m-d g:i A', $appointmentDateTimeInput)
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
        || $appointmentDate->format('Y-m-d') !== $appointmentDateInput
        || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
        || $appointmentDate <= new DateTime()
    ) {
        $appointmentActionNotice = ['type' => 'error', 'message' => 'Choose a valid future date and time using the hour, minute, and AM/PM selectors.'];
    } else {
        $scheduleLock = $pdo->query("SELECT GET_LOCK('law_office_appointment_schedule', 10)");
        if ((int) $scheduleLock->fetchColumn() !== 1) {
            $appointmentActionNotice = ['type' => 'error', 'message' => 'The appointment schedule is busy. Please try confirming again.'];
        } else {
            try {
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
                    $conflictCheck = $pdo->prepare(
                        "SELECT a.title, a.appointment_date, c.fullname AS client_name
                         FROM appointments a
                         LEFT JOIN clients c ON c.client_id = a.client_id
                         WHERE a.status = 'Confirmed'
                           AND a.appointment_date > :window_start
                           AND a.appointment_date < :window_end
                         ORDER BY a.appointment_date ASC
                         LIMIT 1
                         FOR UPDATE"
                    );
                    $conflictCheck->execute([
                        'window_start' => (clone $appointmentDate)->modify('-60 minutes')->format('Y-m-d H:i:s'),
                        'window_end' => (clone $appointmentDate)->modify('+60 minutes')->format('Y-m-d H:i:s'),
                    ]);
                    $conflictingAppointment = $conflictCheck->fetch();

                    if (is_array($conflictingAppointment)) {
                        $pdo->rollBack();
                        $conflictingClient = trim((string) ($conflictingAppointment['client_name'] ?? ''));
                        $conflictingTitle = trim((string) ($conflictingAppointment['title'] ?? 'appointment'));
                        $conflictingDate = new DateTime((string) $conflictingAppointment['appointment_date']);
                        $conflictDescription = $conflictingClient !== ''
                            ? $conflictingTitle . ' for ' . $conflictingClient
                            : $conflictingTitle;
                        $appointmentActionNotice = [
                            'type' => 'error',
                            'message' => 'This time overlaps the confirmed ' . $conflictDescription . ' at ' . $conflictingDate->format('M j, Y g:i A') . '. Choose a time at least 60 minutes apart.',
                        ];
                        create_portal_notification(
                            $pdo,
                            (int) $account['user_id'],
                            $appointmentId,
                            'appointment_conflict',
                            'Appointment time conflict',
                            $appointmentActionNotice['message']
                        );
                    } else {
                        $confirmStatement = $pdo->prepare(
                            "UPDATE appointments
                             SET appointment_date = :appointment_date, status = 'Confirmed'
                             WHERE appointment_id = :appointment_id AND status = 'Pending'"
                        );
                        $confirmStatement->execute([
                            'appointment_date' => $appointmentDate->format('Y-m-d H:i:s'),
                            'appointment_id' => $appointmentId,
                        ]);
                        $appointmentDetailsStatement = $pdo->prepare(
                            'SELECT a.title, c.user_id AS client_user_id
                             FROM appointments a
                             LEFT JOIN clients c ON c.client_id = a.client_id
                             WHERE a.appointment_id = :appointment_id'
                        );
                        $appointmentDetailsStatement->execute(['appointment_id' => $appointmentId]);
                        $appointmentDetails = $appointmentDetailsStatement->fetch();
                        $appointmentTitle = is_array($appointmentDetails)
                            ? (string) $appointmentDetails['title']
                            : 'Appointment';
                        $confirmationMessage = $appointmentTitle . ' is confirmed for ' . $appointmentDate->format('M j, Y g:i A') . '.';
                        if (is_array($appointmentDetails) && $appointmentDetails['client_user_id'] !== null) {
                            create_portal_notification(
                                $pdo,
                                (int) $appointmentDetails['client_user_id'],
                                $appointmentId,
                                'appointment_confirmed',
                                'Appointment confirmed',
                                $confirmationMessage
                            );
                        }
                        create_staff_notifications(
                            $pdo,
                            $appointmentId,
                            'appointment_confirmed',
                            'Appointment confirmed',
                            $confirmationMessage
                        );
                        $pdo->commit();
                        $appointmentActionNotice = ['type' => 'success', 'message' => 'Appointment confirmed and added to the client’s Upcoming Appointments.'];
                    }
                }
            } finally {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $pdo->query("SELECT RELEASE_LOCK('law_office_appointment_schedule')")->fetchColumn();
            }
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
$caseStatusActionNotice = $_SESSION['admin_case_status_notice'] ?? null;
unset($_SESSION['admin_case_status_notice']);
$caseReviewActionNotice = $_SESSION['admin_case_review_notice'] ?? null;
unset($_SESSION['admin_case_review_notice']);
$documentReviewActionNotice = $_SESSION['admin_document_review_notice'] ?? null;
unset($_SESSION['admin_document_review_notice']);
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
    'userId' => (int) $account['user_id'],
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
$calendarAppointmentsStatement = $pdo->prepare(
    "SELECT a.appointment_id, a.title, a.appointment_date, a.type, a.status, a.user_id,
            c.fullname AS client_name, cs.case_number, cs.title AS case_title
     FROM appointments a
     LEFT JOIN clients c ON c.client_id = a.client_id
     LEFT JOIN cases cs ON cs.case_id = a.case_id
     WHERE a.status = 'Pending'
        OR (a.status = 'Confirmed' AND a.appointment_date >= :current_datetime)
     ORDER BY a.appointment_date ASC, a.appointment_id ASC"
);
$calendarAppointmentsStatement->execute(['current_datetime' => (new DateTime())->format('Y-m-d H:i:s')]);
$calendarAppointments = $calendarAppointmentsStatement->fetchAll();
$notificationsStatement = $pdo->prepare(
    'SELECT notification_id, notification_type, title, message, created_at, read_at
     FROM notifications
     WHERE user_id = :user_id
     ORDER BY created_at DESC, notification_id DESC
     LIMIT 50'
);
$notificationsStatement->execute(['user_id' => (int) $account['user_id']]);
$portalNotifications = array_map(
    static function (array $notification): array {
        $notification['notification_id'] = (int) $notification['notification_id'];
        $notification['read'] = $notification['read_at'] !== null;
        return $notification;
    },
    $notificationsStatement->fetchAll()
);
$caseRecordsQuery =
    "SELECT c.case_id, c.case_number, c.title, c.description, c.status, c.filed_date,
            c.case_review_status, c.case_reviewed_at,
            cl.fullname AS client_name,
            COALESCE(NULLIF(c.assigned_lawyer_name, ''), u.username) AS lawyer_name
     FROM cases c
     LEFT JOIN clients cl ON cl.client_id = c.client_id
     LEFT JOIN users u ON u.user_id = c.assigned_lawyer_id";
$caseRecordsParams = [];
if ($userRole === 'lawyer') {
    $caseRecordsQuery .=
        ' WHERE c.assigned_lawyer_id = :user_id
          OR (
              c.assigned_lawyer_id IS NULL
              AND LOWER(TRIM(c.assigned_lawyer_name)) = LOWER(TRIM(:username))
          )';
    $caseRecordsParams = [
        'user_id' => (int) $account['user_id'],
        'username' => (string) $account['username'],
    ];
}
$caseRecordsQuery .= ' ORDER BY c.filed_date DESC, c.case_id DESC';
$caseRecordsStatement = $pdo->prepare($caseRecordsQuery);
$caseRecordsStatement->execute($caseRecordsParams);
$caseRecords = $caseRecordsStatement->fetchAll();
$attorneyReporting = [
    'totalCases' => 0,
    'pendingAppointments' => 0,
    'totalBillableHoursYTD' => 0,
    'totalClients' => 0,
];
if ($userRole === 'lawyer') {
    $attorneyReporting['totalCases'] = count($caseRecords);

    $attorneyAppointmentCount = $pdo->prepare(
        "SELECT COUNT(*)
         FROM appointments a
         WHERE a.status = 'Pending'
           AND (
               LOWER(TRIM(COALESCE(a.preferred_attorney, ''))) IN (
                   LOWER(TRIM(:username)),
                   LOWER(TRIM(:email))
               )
               OR EXISTS (
                   SELECT 1
                   FROM cases assigned_case
                   WHERE assigned_case.case_id = a.case_id
                     AND (
                         assigned_case.assigned_lawyer_id = :user_id
                         OR (
                             assigned_case.assigned_lawyer_id IS NULL
                             AND LOWER(TRIM(assigned_case.assigned_lawyer_name)) = LOWER(TRIM(:legacy_username))
                         )
                     )
               )
           )"
    );
    $attorneyAppointmentCount->execute([
        'username' => (string) $account['username'],
        'email' => (string) $account['email'],
        'user_id' => (int) $account['user_id'],
        'legacy_username' => (string) $account['username'],
    ]);
    $attorneyReporting['pendingAppointments'] = (int) $attorneyAppointmentCount->fetchColumn();

    $attorneyHours = $pdo->prepare(
        'SELECT COALESCE(SUM(hours), 0)
         FROM time_entries
         WHERE user_id = :user_id
           AND YEAR(date_logged) = YEAR(CURRENT_DATE())'
    );
    $attorneyHours->execute(['user_id' => (int) $account['user_id']]);
    $attorneyReporting['totalBillableHoursYTD'] = (float) $attorneyHours->fetchColumn();

    $attorneyClientCount = $pdo->prepare(
        'SELECT COUNT(DISTINCT client_id)
         FROM cases
         WHERE assigned_lawyer_id = :user_id
            OR (
                assigned_lawyer_id IS NULL
                AND LOWER(TRIM(assigned_lawyer_name)) = LOWER(TRIM(:username))
            )'
    );
    $attorneyClientCount->execute([
        'user_id' => (int) $account['user_id'],
        'username' => (string) $account['username'],
    ]);
    $attorneyReporting['totalClients'] = (int) $attorneyClientCount->fetchColumn();
}
$reviewDocumentsQuery =
    "SELECT d.document_id, d.case_id, d.document_name, d.file_path, d.upload_date,
            d.review_status, d.review_comment, d.reviewed_at,
            c.case_number, c.title AS case_title,
            uploader.username AS uploaded_by,
            reviewer.username AS reviewed_by
     FROM documents d
     INNER JOIN cases c ON c.case_id = d.case_id
     LEFT JOIN users uploader ON uploader.user_id = d.uploaded_by
     LEFT JOIN users reviewer ON reviewer.user_id = d.reviewed_by";
$reviewDocumentsParams = [];
if ($userRole === 'lawyer') {
    $reviewDocumentsQuery .=
        ' WHERE c.assigned_lawyer_id = :user_id
          OR (
              c.assigned_lawyer_id IS NULL
              AND LOWER(TRIM(c.assigned_lawyer_name)) = LOWER(TRIM(:username))
          )';
    $reviewDocumentsParams = [
        'user_id' => (int) $account['user_id'],
        'username' => (string) $account['username'],
    ];
}
$reviewDocumentsQuery .= ' ORDER BY d.upload_date DESC, d.document_id DESC';
$reviewDocumentsStatement = $pdo->prepare($reviewDocumentsQuery);
$reviewDocumentsStatement->execute($reviewDocumentsParams);
$reviewDocuments = array_map(
    static function (array $document): array {
        return [
            'document_id' => (int) $document['document_id'],
            'case_id' => (int) $document['case_id'],
            'document_name' => $document['document_name'],
            'upload_date' => $document['upload_date'],
            'review_status' => $document['review_status'],
            'review_comment' => $document['review_comment'],
            'reviewed_at' => $document['reviewed_at'],
            'reviewed_by' => $document['reviewed_by'],
            'case_number' => $document['case_number'],
            'case_title' => $document['case_title'],
            'uploaded_by' => $document['uploaded_by'] ?: 'Staff',
            'previewable' => preg_match('/\.(?:pdf|jpg|jpeg|png|txt)$/i', (string) $document['file_path']) === 1,
            'file_url' => 'document-file.php?id=' . (int) $document['document_id'] . '&preview=1',
            'download_url' => 'document-file.php?id=' . (int) $document['document_id'],
        ];
    },
    $reviewDocumentsStatement->fetchAll()
);
$timeEntriesQuery =
    'SELECT t.time_entry_id, t.user_id, t.hours, t.description, t.date_logged,
            c.case_number, c.title AS case_title, u.username AS logged_by
     FROM time_entries t
     INNER JOIN cases c ON c.case_id = t.case_id
     LEFT JOIN users u ON u.user_id = t.user_id';
$timeEntriesParams = [];
if ($userRole === 'lawyer') {
    $timeEntriesQuery .= ' WHERE t.user_id = :user_id';
    $timeEntriesParams['user_id'] = (int) $_SESSION['admin_portal_user_id'];
}
$timeEntriesQuery .= ' ORDER BY t.date_logged DESC, t.time_entry_id DESC';
$timeEntriesStatement = $pdo->prepare($timeEntriesQuery);
$timeEntriesStatement->execute($timeEntriesParams);
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

    <link rel="stylesheet" href="../CSS/style.css">
    <link rel="stylesheet" href="styles.css?v=3">
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
        window.ADMIN_NOTIFICATIONS = <?php echo json_encode($portalNotifications, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        window.ADMIN_APPOINTMENT_NOTICE = <?php echo json_encode($appointmentActionNotice, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        window.ADMIN_TIME_ENTRY_CSRF_TOKEN = <?php echo json_encode($_SESSION['admin_time_entry_csrf_token']); ?>;
        window.ADMIN_TIME_ENTRY_NOTICE = <?php echo json_encode($timeEntryActionNotice, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        window.ADMIN_DOCUMENT_REVIEW_NOTICE = <?php echo json_encode($documentReviewActionNotice, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        window.ADMIN_DOCUMENT_REVIEW_CSRF_TOKEN = <?php echo json_encode($_SESSION['admin_document_review_csrf_token']); ?>;
        window.ADMIN_CASE_REVIEW_NOTICE = <?php echo json_encode($caseReviewActionNotice, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        window.ADMIN_CASE_REVIEW_CSRF_TOKEN = <?php echo json_encode($_SESSION['admin_case_review_csrf_token']); ?>;
        window.ADMIN_CASE_STATUS_NOTICE = <?php echo json_encode($caseStatusActionNotice, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        window.ADMIN_CASE_STATUS_CSRF_TOKEN = <?php echo json_encode($_SESSION['admin_case_status_csrf_token']); ?>;
        window.ADMIN_REVIEW_DOCUMENTS = <?php echo json_encode($reviewDocuments, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        window.ADMIN_TIME_ENTRIES = <?php echo json_encode($timeEntries, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        window.ADMIN_TOTAL_BILLABLE_HOURS_YTD = <?php echo json_encode($totalBillableHoursYtd); ?>;
        window.ADMIN_CASE_RECORDS = <?php echo json_encode($caseRecords, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        window.ADMIN_TOTAL_CLIENTS = <?php echo json_encode($totalClients); ?>;
        window.ADMIN_ATTORNEY_REPORTING = <?php echo json_encode($attorneyReporting); ?>;
        window.ADMIN_CLIENT_RECORDS = <?php echo json_encode($clientRecords, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    </script>
    <script src="js/admin-storage.js?v=4"></script>
    <script type="text/babel" src="js/admin-app.jsx?v=47"></script>
</body>
</html>