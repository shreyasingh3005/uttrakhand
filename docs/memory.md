# Project Memory, Context & Current Work

## 1. Memory

Uttarakhand Ventures CRM is a plain PHP/MySQL application running under XAMPP at `http://localhost/abhi`. It contains two related operational surfaces:

- A legacy CRM surface using tables such as `agents_details`, `employees_details`, `hotel_listings_details`, `bookings_details`, `accounts_details`, and `agent_query_locks`.
- A newer canonical hotel room-manager surface using `hotels`, `meal_plans`, `hotel_room_categories`, `room_prices`, `room_availability`, `hotel_bookings`, and `booking_rooms`.

`includes/db_connect.php` performs compatibility checks and runtime schema creation/alteration. `ajax/helpers.php` owns shared hotel-manager JSON/auth/input helpers. Admin-only pages use `require_role('admin')`; employee operations are primarily in `employee-dashboard.php`.

The most important current source-of-truth decisions are:

- Hotel master names and locations come from `hotels` for the live property-query flow.
- Active room categories come from `hotel_room_categories`.
- Base query-time prices come from `room_prices` joined to `meal_plans`; supported core codes are EP, CP, MAP, and AP.
- Property categories are stored in `hotels.property_category`, with legacy `star_rating` fallback/derivation.
- Customer quotation output is centralized in `assets/js/quotation-template.js`.
- Query numbers use `UV-####`; database-backed records use a padded ID where available and the formatter has a random four-digit fallback before persistence.

## 2. What Happened

- Added and aligned the shared Airways Travels quotation format for employee and admin flows.
- Removed the old 18% tax wording and replaced it with the mandatory `Above rates are inclusive of taxes.` line.
- Added the mandatory cancellation policy, availability statement, support contact, and powered-by footer.
- Added all available meal-plan codes to quotation output.
- Prevented repeated header/footer content when multiple hotel options are shared.
- Added formatted query-text persistence so generated history and legacy lock history can retain the customer-facing quotation.
- Added copy behavior that uses stored history text instead of rebuilding an incomplete first-hotel quotation.
- Validated recent changes with PHP lint, JavaScript syntax checks, formatter assertions, and multi-quote assertions.
- Existing project work also includes dynamic hotel filtering, location autocomplete, room availability/pricing, agent locks, query history filters, exports, runtime schema compatibility, security headers, and local setup scripts.

## 3. Currently Working

The latest active feature area is quotation generation and query history consistency across:

- `assets/js/quotation-template.js`
- `employee-dashboard.php`
- `bookingquery.php`
- `query-history.php`

Expected next work:

1. Verify the complete admin and employee browser flows against live seeded data.
2. Confirm new records persist the same text that is copied/shared and that old legacy records have an acceptable display fallback.
3. Add focused automated tests for quotation formatting, query-number guarantees, meal-plan rendering, history persistence, permissions, and lock ownership.
4. Address project-level remaining work: schema consolidation, CSRF review for AJAX mutations, responsive/accessibility QA, CI replacement, production configuration, backups, and monitoring.

## 4. Updates

Update this file whenever any of the following changes:

- A route/action, table, column, role rule, or source-of-truth decision changes.
- A major bug is fixed or a compatibility path is removed.
- A milestone moves from pending to complete.
- A new dependency, deployment requirement, or security control is introduced.
- The currently active files or next validation step changes.

Keep entries factual, dated when useful, and concise. Record what is implemented separately from what is proposed. Link deeper implementation details from `prd.md`, `architecture.md`, `rules.md`, `phases.doc.md`, or `design.md` instead of copying large code sections here.

## 5. Purpose

This file prevents future development sessions from inventing architecture, routes, schema, dependencies, or completed work. Before making a change, read this file and the relevant architecture/rules section, then verify the claim in the owning source file. When code and memory disagree, the current code plus a focused executable check is authoritative; update this memory after resolving the discrepancy.
