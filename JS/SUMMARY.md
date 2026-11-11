# JS Folder Debugging Guide

This folder contains small browser-side helpers used by PHP pages. It is separate from the React applications under `Admin/js/` and `Client/js/`.

## Files

| File | Purpose | Debugging impact if broken |
|---|---|---|
| [`phone-input.js`](phone-input.js) | Finds inputs marked `data-phone-input` and strips non-digits / limits their displayed value to 11 digits. Loaded by the staff shared layout and client sign-up page. Server-side validation remains authoritative. | Phone fields may stop being automatically cleaned while typing. The PHP pages should still validate submitted values; inspect the browser console and script URL if it does not run. |
| [`staff.js`](staff.js) | Defines `openIntakeModal()` and `closeIntakeModal()` for an element with ID `intakeModal`. It is not currently referenced by the active Staff pages, which use native `<dialog>` elements and JavaScript in `Staff/_layout.php`. | Current Staff pages should not be affected while they do not load this file. If legacy markup calls these functions, modal controls may fail. Check script references before debugging it as the cause of an active page issue. |

## Debugging notes

- Check the browser Network panel for a 404 and the Console for JavaScript errors.
- Confirm the input has `data-phone-input` before investigating `phone-input.js`.
- The helpers only affect browser behavior; they do not store data or replace server-side validation.
