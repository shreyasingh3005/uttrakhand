# Rules, Standards & AI Boundaries

## 1. What to Use

- Use PHP 8-compatible code, MySQL-compatible SQL, PDO, HTML, CSS, and vanilla JavaScript consistent with the existing project.
- Use prepared statements for all values. Keep dynamic SQL identifiers allow-listed.
- Reuse `config()`, `site_url()`, `redirect()`, auth guards, `sanitize_input()`, `validate_required()`, `hl_ok()`, and `hl_err()` where applicable.
- Keep page-specific behavior near its owning page; use shared helpers only for genuinely shared behavior.
- Use `camelCase` for JavaScript variables/functions, `snake_case` for database fields and PHP request keys, and descriptive PHP function names. Existing database/table names are the compatibility contract.
- Escape HTML output with `htmlspecialchars`. Treat JSON payloads and database content as untrusted at output boundaries.
- Use strict, explicit role checks: `admin` for administrative mutations and `employee` for employee workspace operations.
- Prefer small, focused edits and preserve existing public IDs, POST action names, database columns, and response shapes unless a migration is included.
- Keep customer quotation formatting centralized in `assets/js/quotation-template.js`.

## 2. What to Avoid

- Do not commit `.env.php`, credentials, database dumps containing secrets, or generated logs.
- Do not use raw SQL string interpolation for user values, shell commands with untrusted input, or unescaped HTML interpolation.
- Do not add Laravel, Node, React, or a large library without an explicit migration decision; the current runtime is plain PHP/vanilla JS.
- Do not perform destructive migrations, `DROP TABLE`, data truncation, or schema rewrites in a normal request path.
- Do not break legacy endpoint names or table compatibility without checking every caller.
- Do not silently change booking, lock, pricing, payment, or quotation semantics.
- Do not duplicate quotation headers, cancellation policy, availability wording, contact footer, or query-number logic in page files.
- Do not expose PDO exception messages, credentials, stack traces, or internal SQL to users.
- Do not treat the stale Laravel CI file as proof that Laravel or Composer is installed.

## 3. Libraries & Dependencies

- Bootstrap 5.3.0 CDN: layout, forms, tables, responsive utilities, modals, and dropdowns.
- Bootstrap Icons 1.10.5/1.11.3 CDN: interface icons.
- Google Fonts Inter: current typography.
- Browser Clipboard API with textarea fallback: quotation and booking copying.
- Browser `fetch`: page/AJAX communication.
- PHP PDO: database access.
- PHP sessions, password hashing, JSON, CSV functions, and DateTime: core runtime facilities.

No Composer or npm dependency manifest is currently present.

## 4. Error Handling

- Wrap database operations that may fail in `try/catch (PDOException $e)` or `Throwable` where appropriate.
- Log technical details server-side with `error_log`; return a generic user-facing message.
- JSON endpoints must set `Content-Type: application/json; charset=utf-8`, use a suitable HTTP status, and return a consistent object. Hotel AJAX helpers use `{status: "success", message, data}` or `{status: "error", message}`. Existing page actions also use `{success: true/false, ...}`; preserve that contract per endpoint.
- Validate required fields before queries or mutations and return a clear field/business error.
- Use redirects for normal HTML form flows and JSON for AJAX flows; do not mix response types.
- Never continue after emitting a terminal error response. Existing helpers use `exit`/`never` for this reason.

## 5. Boundaries of AI

- Inspect the owning file, caller, schema, and nearby test before editing.
- State one concrete hypothesis and one focused validation check before the first substantive edit.
- Explain the behavioral scope of changes and keep edits minimal.
- Preserve working modules, IDs, request keys, table names, and response formats unless the task requires a deliberate migration.
- Do not refactor unrelated code, normalize all formatting, or remove compatibility paths without evidence.
- After editing, run the narrowest relevant executable check first, then broaden only when needed.
- Do not invent routes, tables, dependencies, or completed features. Mark uncertain items as pending or verify them in code.
- For schema changes, provide a forward-compatible migration and a rollback/data-impact note.
- For UI changes, check both admin and employee paths when behavior is shared.

## 6. General Rules

- Use conventional commits when commits are requested: `feat:`, `fix:`, `refactor:`, `test:`, `docs:`, or `chore:`.
- Validate and sanitize input, but keep raw values separate from escaped display values where business logic needs the original value.
- Enforce authentication and authorization server-side; client-side visibility is not security.
- Keep CSRF protection on state-changing web requests and review AJAX callers for token forwarding.
- Use HTTPS in production, secure session cookie settings, least-privilege database credentials, backups, and restricted filesystem permissions.
- Keep indexes aligned with search columns and avoid unbounded result sets; current pages generally use limits/pagination.
- Do not run seed/truncate scripts against production data.
- Maintain `docs/memory.md` after architectural decisions, bug fixes, route/schema changes, or completed milestones.
