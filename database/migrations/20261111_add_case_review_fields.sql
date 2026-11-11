ALTER TABLE cases
    ADD COLUMN case_review_status VARCHAR(30) NOT NULL DEFAULT 'Pending Review',
    ADD COLUMN case_review_comment TEXT NULL,
    ADD COLUMN case_reviewed_by INT NULL,
    ADD COLUMN case_reviewed_at DATETIME NULL,
    ADD INDEX idx_cases_review_status (case_review_status);
