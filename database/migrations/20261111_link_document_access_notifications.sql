ALTER TABLE notifications
    ADD COLUMN document_access_request_id INT NULL AFTER appointment_id,
    ADD KEY idx_notifications_document_access_request (document_access_request_id);
