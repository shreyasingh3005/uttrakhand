# Project Memory, Context & Current Work

## 1. Memory

Uttarakhand Ventures CRM is a PHP/MySQL hotel and travel operations system hosted under XAMPP. The active authentication path is PHP session-based: `index.php` posts to `process_login.php`, which validates credentials from `users`, regenerates the session ID, stores the user role, and redirects admins to `dashboard.php` and employees to `employee-dashboard.php`.

The repository contains a legacy CRM schema and a newer canonical hotel-room-manager schema. The canonical property source is the `hotels` table; active room categories come from `hotel_room_categories`; base query-time prices come from `room_prices` joined to `meal_plans`; availability comes from `room_availability`. Existing pages bridge these models, so schema changes require a migration plan.

The shared customer quotation source of truth is `assets/js/quotation-template.js`. It generates `UV-####` query numbers, formats dates, lists available meal-plan prices, omits the old tax sentence, adds cancellation/contact text, and groups multiple room selections into one quotation without repeating the full message.

## 2. What Happened

- Added/maintained session, role, CSRF, input-validation, safe-error, and login-rate-limit helpers.
- Added compatibility schema checks in `includes/db_connect.php` and canonical AJAX helpers in `ajax/helpers.php`.
- Added admin and employee dashboard metrics, filters, CRUD surfaces, booking operations, exports, and responsive navigation.
- Added canonical hotel/room/rate/availability AJAX endpoints.
- Added booking-query matching, agent locking, generated query history, date/search filters, and admin history controls.
- Standardized quotation copy/WhatsApp paths across admin and employee flows.
- Changed quotation numbering from legacy `L...`/`N/A` fallbacks to `UV-0001` style.
- Removed the `Copyinclusive taxes` sentence from customer quotations.
- Added all available meal-plan pricing to quotations.
- Fixed multi-room selection output so a hotel with several room categories produces one combined message rather than repeated complete quotation blocks.
- Added shared UI consistency CSS and sidebar/profile normalization.
- Repaired the local `employee_management.agents_details` InnoDB table after MySQL reported it as present but missing from the storage engine; the corrupted table's old rows were not readable and the recreated table is currently empty.

## 3. Currently Working

The current documented state is approximately 80% complete. The most recent active behavior is the quotation and query-history workflow:

- `assets/js/quotation-template.js`: canonical quotation output and multi-room grouping.
- `employee-dashboard.php`: employee query generation, query-history rendering, copy, view, and WhatsApp actions.
- `bookingquery.php`: admin property matching, multi-selection quotation sharing, and generated-query history.
- `query-history.php`: admin history display, copy, view, and lock/unlock controls.
- `dashboard.php`: legacy/admin direct query-generation flow.

Next work should prioritize:

1. Add browser smoke tests for one room and multiple room categories on both roles.
2. Verify every history record can reconstruct the same quotation from stored structured fields.
3. Apply and test CSRF/authorization consistently across all state-changing AJAX endpoints.
4. Consolidate legacy and canonical schemas or document a supported bridge/migration.
5. Rotate credentials, lock down seed scripts, configure HTTPS, backups, and deployment monitoring.
6. Remove or explicitly deprecate the stale router-style code in `assets/js/app.js`.

The local dashboard was verified after repair with an authenticated admin smoke test and returned HTTP 200 with rendered dashboard HTML.

## 4. Updates

When changing the project, update this file when any of the following changes:

- Authentication, roles, permissions, or session behavior.
- Database tables, columns, foreign keys, indexes, or source-of-truth decisions.
- Routes, AJAX actions, JSON contracts, or external integrations.
- Customer-facing quotation format or sharing behavior.
- Major UI navigation, persistence, or responsive behavior.
- Completed milestones, known bugs, or current work ownership.

Record changes as short dated bullets when a decision materially affects future implementation. Never place secrets, customer data, passwords, or API tokens in this file.

## 5. Purpose

This file prevents future developer and AI sessions from guessing about the project. It records the verified architecture, active ownership boundaries, recent fixes, known dual-model constraints, and next work. Future changes should read this file and the relevant source files first, preserve user edits, make narrow changes, and validate the affected behavior before expanding scope.
