# Client Folder Debugging Guide

This folder implements the client-facing portal: client sign-up and sign-in, a protected dashboard, appointment requests, case and appointment summaries, and document-access requests.

## Request and data flow

1. `login.php` and `signup.php` authenticate or create a client using the shared MySQL connection in `../config/db.php`.
2. Both pages use `_bootstrap.php` for sessions, client lookup, output escaping, CSRF, and flash messages.
3. `index.php` requires a valid client session, reads only that client's records, handles appointment-request POSTs, then passes page data to `portal-shell.php`.
4. `portal-shell.php` loads the React/Babel/Tailwind/Lucide browser dependencies and `js/client-app.js`.
5. `logout.php` clears the client session. `styles.css` supplies local visual styling.

## Files

| File | Purpose | Debugging impact if broken |
|---|---|---|
| [`index.php`](index.php) | Main protected client dashboard. Validates the session, handles appointment and document-access requests, fetches the signed-in client's cases, upcoming appointments, database notifications, and case documents, and builds the data sent to the browser, including database-backed Attorney choices and profile details for read-only Account Settings. Attorney selections are validated against current Attorney accounts; appointment requests remain Pending until an Attorney confirms the final date and time in the Admin/Attorney portal. Document-access requests are saved to MySQL and notify Staff. | A PHP/database error can prevent the dashboard from loading or show missing data. Incorrect ownership filters could expose another client's records. Request validation, ownership checks, and CSRF failures prevent scheduling or document access requests. |
| [`login.php`](login.php) | Client sign-in form and password verification. Restricts login to accounts linked to a client record and starts the client session. | Clients may be unable to sign in or be redirected incorrectly. Check the `users`/`clients` relationship, role value, password hash, and session configuration. |
| [`signup.php`](signup.php) | Validates and creates a client user and matching `clients` record in one database transaction. Collects contact information and redirects to sign-in. | New accounts may fail or leave inconsistent user/client records if transaction handling is damaged. Check CSRF validation, field limits, duplicate constraints, schema columns, and PDO errors. |
| [`logout.php`](logout.php) | Clears the client user ID, CSRF token, and flash message, regenerates the session ID, and returns to login. | Sign-out may leave the portal session active or fail to redirect. |
| [`_bootstrap.php`](_bootstrap.php) | Shared client initialization and helpers: database connection, session startup, HTML escaping, CSRF token creation/validation, database-backed current-client lookup, and one-time flash messages. | Most client pages depend on it. A syntax/include error can break the portal; incorrect user lookup or escaping can cause authentication, data-privacy, or output-safety problems. |
| [`portal-shell.php`](portal-shell.php) | Shared React dashboard document shell. Exposes `CLIENT_PORTAL_DATA` as JSON, loads the saved per-client accent theme before rendering, and includes the UI libraries, `styles.css`, and client app. Requires `CLIENT_PORTAL_ACCESS` to be defined before inclusion. | The PHP dashboard can return data but render blank if the root, scripts, external libraries, or JSON wiring are broken. The access guard prevents direct use as a standalone page. |
| [`index.html`](index.html) | Legacy entry point that redirects to `login.php`. | Old links to the folder may stop redirecting; the PHP login page remains the actual entry point. |
| [`appointments.php`](appointments.php) | Empty placeholder; appointment requests and lists are currently implemented in `index.php` and `js/client-app.js`. | No current functionality should depend on this file. Opening it directly may show a blank page. |
| [`my_cases.php`](my_cases.php) | Empty placeholder; the case list is currently fetched by `index.php` and rendered by `js/client-app.js`. | No current functionality should depend on this file. Opening it directly may show a blank page. |
| [`styles.css`](styles.css) | Client portal body, Amber/Ocean/Emerald accent palette overrides, gold glow, and scrollbar styling. | The portal can still function but may lose its local styling or have inconsistent appearance. Most layout/utilities come from Tailwind classes in the page and app. |
| [`js/client-app.js`](js/client-app.js) | React client interface: dashboard and its database-backed notification/profile popovers, read-only client Account Settings, theme selection, case and appointment panels, appointment-request modal, document previews/access-request UI, and rendering of PHP-provided data. | A JavaScript/Babel syntax or runtime error can leave the root blank or disable interactions. For data problems, first check `CLIENT_PORTAL_DATA` in `portal-shell.php` and its source queries in `index.php`. |

## Debugging notes

- A blank dashboard with a successful PHP response often points to a browser console error, a missing external React/Babel script, a bad `CLIENT_PORTAL_DATA` value, or a JSX/JavaScript error.
- If login succeeds but the dashboard redirects back, inspect the session cookie and `client_current_user()` lookup in `_bootstrap.php`; the account must have a matching `clients.user_id` and client role.
- Client document-access requests are stored in MySQL and create persistent notifications for Staff accounts. Staff can approve or decline each request from the portal notification bell; the Client's displayed request status reflects the saved decision.
- The profile popover saves the selected dark-mode accent in browser `localStorage` under `client-portal-theme:<lowercase-email>`. If the selection does not persist, check browser storage permissions and the early theme bootstrap in `portal-shell.php`. Appointment notifications are stored in MySQL and can be marked read from the Client bell menu.
- Do not use browser storage or client-side visibility as an authorization boundary for client records.
