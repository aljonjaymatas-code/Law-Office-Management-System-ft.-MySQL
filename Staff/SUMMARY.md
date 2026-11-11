# Staff Folder Debugging Guide

The Staff portal supports staff account access and operational work: clients, cases, appointments, time entries, document uploads/downloads, and search. Most protected pages include `_bootstrap.php` for authentication and `_layout.php` for shared navigation and browser behavior.

## Request and data flow

1. `login.php` authenticates a staff-role account; `signup.php` creates one.
2. Protected pages call `staff_require_auth()` from `_bootstrap.php`, which rechecks the role against the database on each request.
3. Pages query or update MySQL through `../config/db.php`, then use `staff_layout_start()` / `staff_layout_end()` from `_layout.php`.
4. Document bytes are stored in `uploads/`, while their titles and paths are stored in the `documents` table. `documents.php` serves downloads after validating the database path.
5. `logout.php` clears the staff session. `index.html` redirects legacy links to the dashboard.

## Files

| File | Purpose | Debugging impact if broken |
|---|---|---|
| [`index.php`](index.php) | Protected staff dashboard and summary counts for clients, cases, appointments, and the signed-in staff member's time entries. | Dashboard queries or authentication can fail; inspect table availability, DB connection, and the session role. |
| [`clients.php`](clients.php) | Creates and lists client/intake records, with server-side validation and CSRF protection. | Client creation or listing can fail. Check `clients` schema, constraints, form field names, and SQL/PDO errors. |
| [`cases.php`](cases.php) | Creates case records, links or creates the matching client record, validates status/date/fields, and lists cases. | Case intake/listing can fail or associate records incorrectly. Check client matching, transaction rollback, uniqueness constraints, and schema columns such as `assigned_lawyer_name`. |
| [`appointments.php`](appointments.php) | Creates pending appointments after validating client/case ownership and future date/time, then lists appointments. | Scheduling or listing may fail. Check the appointment schema/migrations, client/case IDs, CSRF token, and date validation. |
| [`time_entries.php`](time_entries.php) | Creates billable-time entries for valid cases and lists totals/entries for the signed-in staff account. | Time recording or totals may fail. Check `time_entries` schema, case IDs, user ID, and the selected date/hours. |
| [`documents.php`](documents.php) | Uploads allowed document types (max 10 MB), stores the file in `uploads/`, inserts its database record, lists documents, and serves validated downloads. | Upload/download can fail due to PHP upload limits, directory permissions, MIME/extension validation, missing files, or DB records. It contains sensitive-file handling; preserve the path checks and direct-access protections. |
| [`search.php`](search.php) | Searches clients, cases, appointments, and documents using prepared queries. Serves both the HTML result page and JSON autocomplete suggestions (`?format=json`). | Search results or live suggestions may fail. Check query parameter `q`, JSON response/content type, PDO query errors, and the matching DOM IDs/ARIA wiring in `_layout.php`. |
| [`login.php`](login.php) | Staff login form. Verifies a password hash and allowed staff role, rotates the session ID, and redirects to the dashboard. | Staff may be rejected or authentication may fail. Check role spelling, password hashes, session cookie, CSRF field, and user lookup. |
| [`signup.php`](signup.php) | Creates an account with the `staff` role after validating name, Gmail address, password, and password confirmation; signs the account in. | Staff onboarding may fail. Check validation, `users` constraints, role value, and password hashing. Account registration is security-sensitive. |
| [`logout.php`](logout.php) | Clears the staff session and CSRF/flash values, regenerates the session ID, and redirects to login. | Sign-out may fail or leave a session active. |
| [`_bootstrap.php`](_bootstrap.php) | Shared database include, session setup, staff-role allowlist and per-request authorization, HTML escaping, CSRF helpers, and flash-message helpers. | Nearly every protected staff page depends on it. Errors can disable the portal; damaged role checks, CSRF, or escaping can create access or security problems. |
| [`_layout.php`](_layout.php) | Shared page chrome, navigation, global search form/dropdown, notification and profile menus, account/customization links, per-account accent-theme loading, status/error messages, modal triggers, native `<dialog>` behavior, Lucide initialization, and script loading. | All pages using it can lose navigation/layout or dialog/search behavior. If a header menu is broken, check its `data-header-popover` attributes, `aria-controls` IDs, and inline handler here. Theme loading and persistence use the `staff-portal-theme:<email>` localStorage key; action-button gradients follow the selected palette through `../CSS/staff-styles.css`. Notifications currently show an empty state because the portal has no notification feed. |
| [`settings.php`](settings.php) | Protected Account Settings and Customization screens. Account details are read-only; the customization screen offers Classic Amber, Ocean Blue, and Emerald accent palettes and saves the selection in browser localStorage. | Account information or palette controls may fail to display. Check the `view` query parameter, `_layout.php` theme-storage key, and palette overrides in `../CSS/staff-styles.css`. |
| [`index.html`](index.html) | Legacy entry point that redirects to `index.php`. | Old links may fail to reach the dashboard; the PHP route remains the actual page. |
| [`uploads/.htaccess`](uploads/.htaccess) | Denies direct HTTP access to files in the upload directory on Apache. Downloads are intended to go through the checked PHP handler in `documents.php`. | If removed or ignored by the web server, uploaded files might become directly accessible. Check that Apache allows `.htaccess` overrides; keep PHP path/authorization checks regardless. |
| `uploads/*.pdf` (current uploaded files) | Runtime document files referenced by rows in the `documents` table. They are data, not application source; the set changes as staff uploads/removes files. | Deleting or renaming a file leaves its database row but makes downloads fail. Preserve access controls and back up these files securely; do not edit or commit confidential document contents as part of routine code changes. |

## Debugging checklist

- **Redirect to login:** inspect the `staff_user_id` session and the user's role in `users`; `_bootstrap.php` accepts staff, legal assistant, paralegal, receptionist, and related configured role spellings.
- **Database/unknown-column error:** verify XAMPP MySQL is running, `.env` points to the intended DB, and relevant MySQL migrations are applied. Do not apply SQL Server migrations to MariaDB.
- **POST says session expired:** check that the form rendered `staff_csrf_field()` and that the browser sends the same session cookie and `_csrf` field.
- **Modal or live search issue:** inspect the browser Console and Network panel; then compare the DOM IDs/data attributes in the individual page with the handlers in `_layout.php`.
- **Upload succeeds but download fails:** verify the database `file_path`, that the corresponding file exists under `uploads/`, directory permissions, and Apache's `.htaccess` behavior. Do not bypass the download route's path validation.
- This folder contains no `js/` subfolder; shared phone-input behavior comes from `../JS/phone-input.js`.
