# Admin Folder File Guide

This guide covers every file under `Admin/`, including its `js/` subfolder. The Admin area provides sign-in and attorney registration, then serves an administrator or attorney dashboard using PHP, MySQL, React, and browser storage.

## How the files work together

1. `login.php` authenticates an existing account, and `signup.php` creates an attorney account.
2. Both pages use `_auth.php` to interpret account roles. Successful authentication redirects to `index.php`.
3. `index.php` validates the session, reads dashboard data from the database, and passes it to the browser.
4. `js/admin-app.jsx` renders the dashboard and implements its interactive views.
5. `js/admin-storage.js` supplies browser-storage helpers for notifications, appointment workflow records, and document submissions.
6. `logout.php` ends the portal session. `styles.css` adds local styles to the PHP pages.

The dashboard also depends on the shared database connection in `../config/db.php`, the database schema, and front-end libraries loaded by `index.php` and the authentication pages.

## Files

| File | Purpose in the system | What can happen if it is missing or broken |
|---|---|---|
| [`index.php`](index.php) | Main authenticated dashboard entry point. Checks the signed-in user and role against the database; handles appointment confirmation and billable-time submissions; loads cases, appointments, users, clients, and hours; supplies page data and CSRF tokens to the browser; loads the dashboard scripts and styles. | The dashboard may fail to load, reject valid sessions, show incomplete or incorrect data, or fail to save appointments and time entries. Errors in its access checks or CSRF handling can also weaken security, so changes here need particular care. |
| [`login.php`](login.php) | Sign-in form and server-side authentication. Validates the email and password, verifies the stored password hash, refreshes the session ID, and directs the user to the dashboard. Includes password visibility behavior. | Users may be unable to sign in, valid accounts may be rejected, or a broken authentication/session flow could expose protected pages. |
| [`signup.php`](signup.php) | Attorney registration page and server-side account creation. Checks the CSRF token, validates the name, Gmail address, and password requirements, hashes the password, creates a `lawyer` account, and signs the new account in. | Attorney account creation can fail. Damaging its validation, password hashing, or CSRF handling can cause invalid accounts or weaken account security. |
| [`logout.php`](logout.php) | Clears the portal role and user ID from the session, regenerates the session ID, and redirects to sign-in. | Sign-out may leave the current session active or fail to redirect, which can create an account-privacy and security risk on shared computers. |
| [`_auth.php`](_auth.php) | Shared role-normalization helper. Maps recognized administrator and attorney account-role names to the portal's `admin` and `lawyer` roles; rejects unrecognized roles. | Login and registration pages that include it may fail with a PHP error, or role checks and role-specific access may behave incorrectly. |
| [`index.html`](index.html) | Compatibility landing page that immediately redirects to `index.php` for older links or direct visits. | Visits to the HTML entry point may stop redirecting; the PHP dashboard itself remains available at `index.php`. |
| [`styles.css`](styles.css) | Local styles for accent palettes, themed amber text (including subdued table headings), buttons and gradients, gold glow effects, and the dashboard fade-in animation. Honors reduced-motion settings for that animation. | The pages still render, but selected theme accents, custom glow, and fade-in effects may be lost or look incorrect. |
| [`cases.php`](cases.php) | Empty PHP file; it currently contains no implementation and is not the dashboard's case-data source. | It currently has no behavior to break. Opening it directly may produce a blank response; case data is loaded by `index.php`. |
| [`users.php`](users.php) | Empty PHP file; it currently contains no implementation and is not the dashboard's user-management view. | It currently has no behavior to break. Opening it directly may produce a blank response; the user-management view is rendered by `js/admin-app.jsx` using data supplied by `index.php`. |
| [`js/admin-app.jsx`](js/admin-app.jsx) | Main browser application. Renders the administrator and attorney dashboards, header, search and notifications, the administrator navigation drawer, and the attorney profile menu with attorney navigation, Account Settings, Customization, and logout; also renders case/client tables, user and transaction views, document review and preview, appointment review, calendar, and billable-hours workflows. It connects UI actions to server submissions and browser storage. Attorney profile settings are read-only; the selected accent theme is stored per account in browser localStorage. | A syntax or runtime error can prevent the dashboard from rendering. Errors in an individual view or action can break that workflow; damaged role checks or client-side access controls can expose or misroute UI. Server-side authorization in `index.php` remains essential and must not be replaced by client-side checks. |
| [`js/admin-storage.js`](js/admin-storage.js) | Defines `window.AdminPortalStorage`: storage keys and helpers for reading notifications, appointment workflow records, document submissions, per-account activity history, and local dates. The dashboard imports these helpers. Some workflow state is kept in this browser's `localStorage`, not the database. | The dashboard can fail when it tries to initialize or update browser-stored workflows. Stored browser data may be unavailable or unreadable; this storage is not secure or shared across browsers and devices. Do not use it as protected storage for confidential legal documents. |

## Important notes

- The dashboard is not a standalone folder: it needs the shared configuration and database outside `Admin/`.
- `index.php` is the server-side access boundary. Hiding a view in React does not authorize or protect database operations.
- `cases.php` and `users.php` are empty placeholders, not active implementations of those dashboard views.
- `js/admin-storage.js` stores some workflow state locally in the browser. Clearing browser data or switching browsers may remove or hide that state.
- The Attorney profile menu links to `Transaction History & Tracking` and `Review File`. Meaningful document-review actions are stored per account in localStorage; billable-hour entries are loaded from the database for the signed-in attorney. Ordinary page views are not transactions. Local activity is browser-specific, not a server audit log.
