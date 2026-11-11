# Database Folder Debugging Guide

This folder currently contains schema-change scripts under `migrations/`; the application's live data is in MySQL/MariaDB configured via `../config/db.php`.

## Migration files

| File | Database / purpose | Debugging impact if missing or misapplied |
|---|---|---|
| [`migrations/20261028_add_appointment_request_details.sql`](migrations/20261028_add_appointment_request_details.sql) | MySQL/MariaDB: adds `preferred_attorney` and `issue_description` to `appointments`, used by appointment intake/review. | Appointment pages that select or save these fields can fail with unknown-column SQL errors. |
| [`migrations/20261028_add_assigned_lawyer_name_to_cases.sql`](migrations/20261028_add_assigned_lawyer_name_to_cases.sql) | MySQL/MariaDB: adds `assigned_lawyer_name` to `cases`, used to associate/display a lawyer by name. | Case intake and dashboards can fail when reading or writing that column. |
| [`migrations/20261109_add_sql_server_portal_columns.sql`](migrations/20261109_add_sql_server_portal_columns.sql) | SQL Server only: adds appointment request detail and assigned-lawyer columns to SQL Server tables. | Running SQL Server syntax on MySQL/MariaDB will fail. If the SQL Server schema is not updated, code that expects these columns there can fail. |
| [`migrations/20261110_add_user_contact_columns.sql`](migrations/20261110_add_user_contact_columns.sql) | SQL Server only: adds `phone` and `address` to `dbo.users` and copies suitable contact values from client records. | SQL Server pages that expect the user contact columns can fail. This uses SQL Server syntax and is not for MariaDB. |
| [`migrations/20261110_align_mysql_datetime_columns.sql`](migrations/20261110_align_mysql_datetime_columns.sql) | MySQL/MariaDB: aligns `users.created_at` and `documents.upload_date` to nullable `DATETIME(3)` columns. | Code/data imports expecting millisecond date values or nullable columns may fail or behave inconsistently if the schema differs. |
| [`migrations/20261111_add_document_review_fields.sql`](migrations/20261111_add_document_review_fields.sql) | MySQL/MariaDB: adds persisted document review status, comment, reviewer, timestamp, and a status index to `documents`. | Attorney review and Staff status views require these columns; apply this migration before using the database-backed review workflow. |

## Migration and debugging notes

- The MySQL/MariaDB migrations for this application are the two `20261028_...` files, `20261110_align_mysql_datetime_columns.sql`, and `20261111_add_document_review_fields.sql`.
- Do **not** run the SQL Server migrations (`20261109_add_sql_server_portal_columns.sql` and `20261110_add_user_contact_columns.sql`) against MariaDB. They target a separate SQL Server schema.
- Before diagnosing an unknown-column error, confirm which database server/schema the application is connected to and inspect the table definition. An unapplied migration and a misspelled query can produce similar symptoms.
- Back up the target database before running schema changes. `ADD COLUMN IF NOT EXISTS` support can vary with MariaDB/MySQL versions; inspect the server version and migration error rather than repeatedly rerunning a partially applied script.
- These scripts do not create the complete database or seed data; they only alter selected columns.
