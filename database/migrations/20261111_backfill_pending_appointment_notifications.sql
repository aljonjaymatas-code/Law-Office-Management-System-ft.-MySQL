INSERT INTO notifications (user_id, appointment_id, notification_type, title, message)
SELECT staff_user.user_id,
       appointment.appointment_id,
       'appointment_requested',
       'Pending appointment request',
       CONCAT(
           COALESCE(client.fullname, 'A client'),
           ' requested ',
           appointment.type,
           ' for ',
           DATE_FORMAT(appointment.appointment_date, '%b %e, %Y %l:%i %p'),
           '.'
       )
FROM appointments appointment
LEFT JOIN clients client ON client.client_id = appointment.client_id
JOIN users staff_user
  ON LOWER(TRIM(staff_user.role)) IN ('staff', 'legal assistant', 'legal_assistant', 'paralegal', 'receptionist')
WHERE appointment.status = 'Pending'
  AND appointment.user_id IS NULL
  AND NOT EXISTS (
      SELECT 1
      FROM notifications existing
      WHERE existing.user_id = staff_user.user_id
        AND existing.appointment_id = appointment.appointment_id
        AND existing.notification_type = 'appointment_requested'
  );
