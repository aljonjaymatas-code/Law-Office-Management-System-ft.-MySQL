ALTER TABLE documents
    ADD COLUMN review_status VARCHAR(30) NOT NULL DEFAULT 'Pending Review',
    ADD COLUMN review_comment TEXT NULL,
    ADD COLUMN reviewed_by INT NULL,
    ADD COLUMN reviewed_at DATETIME NULL,
    ADD INDEX idx_documents_review_status (review_status);
