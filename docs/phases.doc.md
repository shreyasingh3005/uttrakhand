# Project Breakdown & Milestones

## Phase 1: Login & Authentication

- [x] Login page in `index.php`.
- [x] Admin/employee login-type validation.
- [x] Password hashing and verification.
- [x] PHP session creation and session-ID regeneration.
- [x] Role-based page guards for admin and employee areas.
- [x] Session timeout and logout cleanup.
- [x] CSRF token generation and verification for the main login flow.
- [x] File-based login rate limiting with backoff.
- [ ] Review CSRF coverage for every state-changing AJAX action.
- [ ] Rotate/remove any credentials used by demo seed scripts before production.

## Phase 2: Dashboard & Core Layout

- [x] Admin dashboard with booking, agent, payment, and status summaries.
- [x] Employee dashboard with employee-scoped live metrics.
- [x] Responsive sidebar and top-header navigation.
- [x] Shared sidebar/profile normalization through `ui-common.js`.
- [x] Bootstrap Icons and Chart.js dashboard integrations.
- [x] Mobile sidebar open/close behavior on the main dashboard pages.
- [x] Shared UI consistency and modernization CSS layers.
- [ ] Consolidate duplicated legacy page shells and navigation markup.
- [ ] Verify all dashboard metrics against the final production schema.

## Phase 3: CRUD Operations

- [x] Agent create, search, status filtering, detail display, and delete protection.
- [x] Employee directory filters, status/login visibility, payroll summary, and exports.
- [x] Admin hotel listing create/update/delete flows.
- [x] Room category create/update/delete flows.
- [x] Meal-plan base pricing and date-wise rate editing.
- [x] Availability retrieval and saving.
- [x] Canonical hotel, room, rate, availability, and booking AJAX endpoints.
- [x] Booking create, retrieve, update, cancel, payment/status tracking, and room restoration.
- [x] Accounts summary and account-entry workflows.
- [x] Agent, employee, and booking Excel exports.
- [ ] Complete migration/consolidation between legacy `*_details` and canonical tables.
- [ ] Add automated CRUD authorization and foreign-key regression tests.

## Phase 4: Additional Features

- [x] Booking Query property matching by location/category/dates/occupancy/rooms/budget.
- [x] Agent query locks and admin lock override.
- [x] Query history for generated requests.
- [x] Shared quotation formatter with `UV-####` numbering.
- [x] All available meal-plan display in quotations.
- [x] Removal of the tax sentence from customer quotations.
- [x] Copy and WhatsApp sharing on admin and employee query surfaces.
- [x] Single shared message for multiple selected room options, without repeated full quotations.
- [x] Search/date filters on query history.
- [ ] Finish production-ready quotation persistence/reconstruction for every legacy record.
- [ ] Remove or document stale `assets/js/app.js` router code.
- [ ] Add a supported migration path for query history and old lock records.

## Phase 5: Testing & QA

- [ ] Test admin versus employee access for every page and AJAX endpoint.
- [ ] Test invalid, missing, duplicate, and boundary input values.
- [ ] Test date ranges, nights, availability, overbooking prevention, and cancellation restoration.
- [ ] Test quotation output for one room, multiple rooms, missing prices, duplicate selections, and special characters.
- [ ] Test clipboard fallback and WhatsApp URL generation.
- [ ] Test empty database states and partially migrated schemas.
- [ ] Test desktop, tablet, and mobile responsiveness.
- [ ] Add PHP lint, JavaScript syntax, and browser smoke tests to a repeatable QA checklist.
- [ ] Perform security review of secrets, CSRF, CSP, headers, session cookies, and authorization.

## Phase 6: Deployment & Maintenance

- [x] XAMPP import helper and Apache virtual-host setup scripts exist.
- [x] `.htaccess` blocks sensitive files/directories and adds baseline headers/caching.
- [ ] Set production `.env.php` outside version control with rotated credentials.
- [ ] Configure HTTPS and uncomment/verify the HTTPS redirect only after SSL is active.
- [ ] Verify Apache/PHP/MySQL versions and required PHP extensions.
- [ ] Establish automated database backups and restore drills.
- [ ] Establish application/error-log rotation and monitoring.
- [ ] Replace demo seed credentials and restrict seed scripts in production.
- [ ] Create non-destructive versioned migrations instead of relying on the full SQL clean-start script.
