# Rules, Standards & AI Boundaries

## 1. What to Use

- Use PHP, MySQL SQL, HTML, CSS, and vanilla JavaScript already used by the project.
- Use PDO prepared statements with `PDO::ATTR_EMULATE_PREPARES = false`.
- Reuse `includes/config.php`, `includes/db_connect.php`, `includes/auth_session.php`, `includes/security.php`, and `ajax/helpers.php` before adding helpers.
- Keep role checks at page and endpoint boundaries. Admin-only hotel structure/rate/availability operations must remain protected.
- Use `camelCase` for JavaScript functions/variables, `snake_case` for database columns and PHP variables where the surrounding file uses it, and descriptive names rather than one-letter variables.
- Preserve existing page-oriented architecture unless a migration is explicitly approved.
- Prefer shared quotation/UI helpers over duplicated formatting or markup.
- Keep JSON responses predictable. Canonical AJAX success responses use `{ "status": "success", "message": "...", "data": ... }`; errors use `{ "status": "error", "message": "..." }`. Older page actions may use `{ "success": true/false, ... }` and must not be changed casually.

## 2. What to Avoid

- Never commit `.env.php`, passwords, database credentials, session secrets, or production tokens.
- Do not hardcode production URLs, credentials, or secrets into PHP/JavaScript.
- Do not use string-concatenated SQL for user-controlled values. Whitelist dynamic sort columns and enum-like values.
- Do not use destructive migrations or run the clean-start/truncate section of `database/all.sql` against production data.
- Do not rewrite working modules or merge the two data models without a migration plan.
- Do not add a large framework, package, router, or build system without approval; no package manifest currently exists.
- Do not weaken authorization because the UI hides a control.
- Do not expose exception messages, SQL, passwords, or stack traces to users.
- Do not duplicate quotation blocks or maintain separate customer-facing quotation formats.

## 3. Libraries & Dependencies

- PHP PDO: database access.
- Bootstrap 5.3.x CDN: grid, controls, modal, dropdown, toast, and utility styles.
- Bootstrap Icons 1.10/1.11 CDN: interface icons.
- Chart.js CDN: dashboard charts.
- Google Fonts Inter: typography.
- Browser Clipboard API and WhatsApp URL schemes: quotation sharing.
- Apache `mod_rewrite`, `mod_headers`, and `mod_expires`: URL/security headers/caching.

There is no `composer.json`, `package.json`, or lockfile in the repository. Do not document or introduce packages that are not installed.

## 4. Error Handling

- Wrap database operations that may fail in `try/catch (PDOException)` or `try/catch (Throwable)` as appropriate.
- Log technical details server-side with `error_log`; return a safe user message.
- Use `http_response_code(401)` for unauthenticated API calls, `403` for forbidden actions, `404` for missing records, `409` for conflicts, `422` for invalid values, and `500` for unexpected database/server failures.
- Canonical AJAX endpoints should call `hl_ok()` and `hl_err()` from `ajax/helpers.php`.
- Page AJAX actions should preserve their existing JSON contract and always set a JSON content type before returning JSON.
- Validate required fields, dates, numeric ranges, email addresses, enum values, and ownership/role before database writes.

## 5. Boundaries of AI

- Inspect the current file and nearby call sites before editing.
- State the local hypothesis and the smallest validation check before the first edit.
- Make the smallest focused change and preserve unrelated user changes.
- Explain behavior changes briefly and run a narrow executable validation after edits.
- Do not replace the legacy/canonical models, authentication, or booking logic without explicit approval.
- Do not “fix” unrelated bugs during a feature change.
- Treat `.env.php`, database data, and user edits as sensitive and preserve them.
- Update documentation when routes, schema, permissions, or shared behavior changes.

## 6. General Rules

- Use feature-oriented, non-interactive commits with clear messages such as `fix: normalize quotation history output`.
- Sanitize input with existing helpers and escape output with context-appropriate HTML/URL encoding.
- Use CSRF protection for state-changing web forms and confirm whether AJAX endpoints need token support before adding writes.
- Keep sessions secure: regenerate after login, enforce timeout, clear on logout, and maintain role checks.
- Keep database writes transactional when multiple tables must remain consistent.
- Preserve foreign keys and availability invariants; never delete linked hotels/bookings casually.
- Use indexes for common hotel, room, date, and status searches.
- Keep third-party CDN dependencies documented and review CSP changes when adding one.
- Test admin and employee permissions, empty states, invalid inputs, date boundaries, mobile layouts, clipboard fallback, and WhatsApp URL encoding.
