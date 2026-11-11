CREATE TABLE document_access_requests (
    request_id INT NOT NULL AUTO_INCREMENT,
    document_id INT NOT NULL,
    client_id INT NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Pending',
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME NULL,
    PRIMARY KEY (request_id),
    KEY idx_document_access_requests_client_document (client_id, document_id, request_id),
    KEY idx_document_access_requests_status (status, requested_at)
);
