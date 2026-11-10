ALTER TABLE cases
    ADD COLUMN IF NOT EXISTS assigned_lawyer_name VARCHAR(150) NULL AFTER assigned_lawyer_id;
