INSERT INTO document_access_requests (document_id, client_id, status, requested_at)
SELECT d.document_id, client.client_id, 'Pending', MIN(n.created_at)
FROM notifications n
INNER JOIN users staff_user
    ON staff_user.user_id = n.user_id
   AND LOWER(TRIM(staff_user.role)) IN ('staff', 'legal assistant', 'legal_assistant', 'paralegal', 'receptionist')
INNER JOIN users client_user
    ON LOWER(TRIM(client_user.role)) = 'client'
INNER JOIN clients client ON client.user_id = client_user.user_id
INNER JOIN cases c ON c.client_id = client.client_id
INNER JOIN documents d ON d.case_id = c.case_id
WHERE n.notification_type = 'document_access_requested'
  AND n.document_access_request_id IS NULL
  AND n.message = CONCAT(
      client_user.username,
      ' requested access to "',
      d.document_name,
      '" for case ',
      c.title,
      '.'
  )
  AND NOT EXISTS (
      SELECT 1
      FROM document_access_requests existing
      WHERE existing.document_id = d.document_id
        AND existing.client_id = client.client_id
        AND existing.status = 'Pending'
  )
GROUP BY d.document_id, client.client_id;

UPDATE notifications n
INNER JOIN users client_user
    ON LOWER(TRIM(client_user.role)) = 'client'
INNER JOIN clients client ON client.user_id = client_user.user_id
INNER JOIN cases c ON c.client_id = client.client_id
INNER JOIN documents d ON d.case_id = c.case_id
INNER JOIN document_access_requests request
    ON request.document_id = d.document_id
   AND request.client_id = client.client_id
   AND request.status = 'Pending'
SET n.document_access_request_id = request.request_id
WHERE n.notification_type = 'document_access_requested'
  AND n.document_access_request_id IS NULL
  AND n.message = CONCAT(
      client_user.username,
      ' requested access to "',
      d.document_name,
      '" for case ',
      c.title,
      '.'
  );
