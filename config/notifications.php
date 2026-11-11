<?php

function create_portal_notification(
    PDO $pdo,
    int $userId,
    ?int $appointmentId,
    string $type,
    string $title,
    string $message,
    ?int $documentAccessRequestId = null
): void {
    $statement = $pdo->prepare(
        'INSERT INTO notifications (user_id, appointment_id, document_access_request_id, notification_type, title, message)
         VALUES (:user_id, :appointment_id, :document_access_request_id, :notification_type, :title, :message)'
    );
    $statement->execute([
        'user_id' => $userId,
        'appointment_id' => $appointmentId,
        'document_access_request_id' => $documentAccessRequestId,
        'notification_type' => $type,
        'title' => $title,
        'message' => $message,
    ]);
}

function create_staff_notifications(
    PDO $pdo,
    ?int $appointmentId,
    string $type,
    string $title,
    string $message,
    ?int $documentAccessRequestId = null
): void {
    $staffUsers = $pdo->query(
        "SELECT user_id
         FROM users
         WHERE LOWER(TRIM(role)) IN ('staff', 'legal assistant', 'legal_assistant', 'paralegal', 'receptionist')"
    )->fetchAll(PDO::FETCH_COLUMN);

    foreach ($staffUsers as $userId) {
        create_portal_notification($pdo, (int) $userId, $appointmentId, $type, $title, $message, $documentAccessRequestId);
    }
}
