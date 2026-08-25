# Project Breakdown & Milestones

The project is approximately 80% complete. The checkboxes describe behavior found in the current codebase; unchecked items are remaining validation, hardening, or deployment work.

## Phase 1: Login & Authentication

- [x] Login page with Admin/Employee role selection.
- [x] Password verification using PHP password hashing.
- [x] Session creation and session ID regeneration after login.
- [x] Role-based redirects to admin or employee workspace.
- [x] Login rate limiting by IP and account with backoff.
- [x] CSRF token generation and verification helpers.
- [x] Session timeout enforcement.
- [x] Logout flow and login-state tracking fields.
- [ ] Verify CSRF coverage on every state-changing AJAX endpoint.
- [ ] Add automated authentication/authorization tests.

## Phase 2: Dashboard & Core Layout

- [x] Admin dashboard with booking and agent KPI cards.
- [x] Live metrics endpoint and weekly booking/status data.
- [x] Employee dashboard with navigation sections and workspace cards.
- [x] Shared admin sidebar, employee sidebar, header/footer fragments, and responsive menu behavior.
- [x] Shared UI consistency and modern styling layers.
- [x] Search/filter controls across admin directories, bookings, accounts, and histories.
- [x] Responsive CSS breakpoints for desktop, tablet, and mobile layouts.
- [ ] Complete browser-based responsive and accessibility audit.
- [ ] Remove or reconcile duplicated legacy layout implementations.

## Phase 3: CRUD Operations

- [x] Admin agent directory, status/filtering, delete operation, and export.
- [x] Employee directory, status/filtering, login metadata, and export.
- [x] Legacy hotel listing CRUD.
- [x] Canonical hotel master CRUD through `ajax/save_hotel.php`, `update_hotel.php`, and `delete_hotel.php`.
- [x] Room category create/update/delete operations.
- [x] Room create/update/delete operations.
- [x] Meal-plan price and rate operations.
- [x] Availability save/read and bulk rate updates.
- [x] Booking create/update/cancel/read operations.
- [x] Accounts ledger display and financial summaries.
- [ ] Consolidate legacy and canonical data models.
- [ ] Add migration/version tracking for schema evolution.

## Phase 4: Additional Features

- [x] Hotel filtering by location, category, dates, occupancy, rooms, and nightly budget.
- [x] Room-level matching results with meal-plan pricing and availability.
- [x] Agent lock ownership and admin override behavior.
- [x] Employee and admin query history views with date/search filters.
- [x] CSV exports for bookings, agents, and employees.
- [x] Shared Airways Travels quotation formatter.
- [x] UV-#### query-number generation, mandatory tax/cancellation/footer content, and de-duplicated multi-hotel output.
- [x] Clipboard and WhatsApp sharing for generated quotations.
- [x] Persistence of formatted quotation text in generated and legacy history paths.
- [x] Seed data, database import, diagnostics, and optional Apache virtual-host scripts.
- [ ] Finish cleanup of orphaned/dead JavaScript and old compatibility flows.
- [ ] Add a user-facing recovery path for failed clipboard, WhatsApp, and history persistence operations.
- [ ] Verify quotation output against all meal-plan and missing-data combinations.

## Phase 5: Testing & QA

- [x] Narrow PHP smoke scripts exist for PDO parameter behavior and booking-query filtering.
- [x] PHP syntax and JavaScript syntax checks have been used for recent query-format changes.
- [ ] Add PHPUnit or another PHP test runner suited to this non-Laravel application.
- [ ] Test role boundaries and unauthorized AJAX access.
- [ ] Test malformed JSON, missing fields, invalid dates, duplicate records, and stale locks.
- [ ] Test booking/payment status transitions and cancellation behavior.
- [ ] Test concurrent agent locking and transaction rollback behavior.
- [ ] Test desktop/mobile layouts and keyboard/accessibility behavior.
- [ ] Run a security review covering CSRF, CSP, session cookies, SQL, file exposure, and production error handling.
- [ ] Add CI that actually boots/tests this PHP/MySQL application.

## Phase 6: Deployment & Maintenance

- [x] Local XAMPP import and virtual-host instructions exist.
- [x] `.htaccess` blocks sensitive directories/files and sets baseline headers.
- [x] Environment values are separated into `.env.php`.
- [ ] Set production `APP_ENV`, `APP_DEBUG=false`, HTTPS, and non-root database credentials.
- [ ] Replace or remove the mismatched Laravel GitHub Actions workflow.
- [ ] Document Apache/PHP/MySQL version requirements and extensions.
- [ ] Establish scheduled database backups and tested restore procedures.
- [ ] Establish log rotation, monitoring, alerting, and incident procedures.
- [ ] Establish a schema migration/release process.
- [ ] Document deployment rollback and seed-data safety rules.
