# CSS Folder Debugging Guide

These stylesheets provide shared theme rules plus staff-specific and legacy admin styles. Most page layout and component styling is written as Tailwind classes in the PHP/JSX files.

## Files

| File | Purpose | Debugging impact if broken |
|---|---|---|
| [`style.css`](style.css) | Shared base theme, body defaults, gold glow shadows, and custom scrollbars. Currently linked by `Admin/index.php`. | The admin page may lose its base appearance, glows, or scrollbar styling. Tailwind utility classes and dashboard logic are separate. |
| [`staff-styles.css`](staff-styles.css) | Shared Staff portal accent palettes (Classic Amber, Ocean Blue, Emerald), base theme, scrollbar styling, and legacy modal styles. Used by `Staff/_layout.php`, `Staff/login.php`, and `Staff/signup.php`. | Staff pages can render without the expected palette accents, base colors, typography, and scrollbar appearance. It does not handle PHP authentication or database behavior. |
| [`admin-styles.css`](admin-styles.css) | Legacy/custom admin modal and form classes (for example `.modal-overlay`, `.modal-card`, and `.form-control`). No current `Admin/` page includes this stylesheet. | Existing pages should not change unless a page is updated to load it; if future legacy markup relies on these classes, the modal/form appearance or visibility can break. Do not assume it is active in the current React dashboard. |

## Debugging notes

- If a page is unstyled, inspect its `<link rel="stylesheet">` URL in the browser Network panel first; a correct file at the wrong relative URL still returns 404.
- Staff accent text, including subdued table headings (`text-amber-400/80`), is remapped by `staff-styles.css` to the selected Amber, Ocean, or Emerald palette. If headings stay gold after switching themes, verify the `data-staff-theme` attribute on `<html>` and refresh the stylesheet cache-buster in `Staff/_layout.php`.
- Tailwind classes are loaded separately by CDN scripts in the current portals; editing these files will not fix a missing Tailwind CDN connection.
- Search references before changing a stylesheet: `admin-styles.css` is currently unreferenced by the active application.
