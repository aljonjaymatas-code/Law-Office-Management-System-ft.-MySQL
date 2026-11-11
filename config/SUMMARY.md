# Config Folder Debugging Guide

## Files

| File | Purpose | Debugging impact if broken |
|---|---|---|
| [`db.php`](db.php) | Loads repository-root `.env` values when present, validates MySQL connection settings, and creates the shared PDO connection `$pdo` using PDO_MYSQL and `utf8mb4`. Sets exception mode, associative fetches, and native prepared statements. Nearly every PHP portal depends on it. | A syntax/configuration/driver problem can make all database-backed pages fail with HTTP 500. Check that MySQL is running, the database exists, `.env` has the expected `DB_DRIVER=mysql`, host, port, database, username, and password, and PHP has `pdo_mysql` enabled. The connection error is written to the PHP error log; the browser receives a generic message. |

## Debugging notes

- Configuration is read from `.env` in the repository root, not from `config/`. Keep credentials private; do not copy them into source or diagnostic output.
- The application expects MySQL/MariaDB. SQL Server connection support is not configured by this file.
- For “table/column not found,” the connection may be fine: verify the active database and apply only the migrations intended for that database engine (see `../database/SUMMARY.md`).
- Pages that report database failure depend on this common connection, so test it before debugging an individual portal query.
