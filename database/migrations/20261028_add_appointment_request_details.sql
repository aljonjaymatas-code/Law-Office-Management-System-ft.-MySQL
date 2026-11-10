ALTER TABLE appointments
    ADD COLUMN IF NOT EXISTS preferred_attorney VARCHAR(150) NULL AFTER status,
    ADD COLUMN IF NOT EXISTS issue_description TEXT NULL AFTER preferred_attorney;
