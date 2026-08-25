# Uttarakhand Ventures CRM

## 1. What to Build

**Product:** Uttarakhand Ventures CRM and Hotel Room Manager.

**Purpose:** Provide a single internal workspace for a travel/hotel business to manage agents, employees, hotel inventory, room categories, prices, availability, booking queries, bookings, accounts, and operational exports.

**Current state:** Approximately 80% complete. The core admin and employee workflows are implemented and connected to MySQL. The project is functional for local XAMPP use, while production hardening, test coverage, schema consolidation, and deployment automation remain.

**Key objectives:**

- Authenticate administrators and employees with role-based access.
- Maintain a reliable agent and employee directory.
- Maintain hotel, room, meal-plan, pricing, and availability data.
- Let staff find matching properties and produce customer-ready quotations.
- Preserve query history and agent lock ownership.
- Track bookings, payments, accounts, operational metrics, and exports.
- Keep customer-facing quotation output consistent across admin, employee, copy, WhatsApp, and history flows.

## 2. Targeted User

### Admin

Admins have access to the administrative pages and can manage the business data model:

- Dashboard metrics and booking/agent summaries.
- Agent directory, status, revenue, deletion, and exports.
- Employee directory, payroll information, login status, and exports.
- Hotel listings, room categories, rates, availability, and room operations.
- Booking queries, query history, locks, and all employee-generated query records.
- Booking records, accounts ledger, and CSV exports.

### Employee

Employees use the operational workspace to:

- Search agents and view agent information.
- Create booking queries from customer requirements.
- Filter hotels by location, category, dates, room count, and budget.
- Select room options and share quotations through clipboard or WhatsApp.
- View their own generated query history and legacy agent-query history.
- Create and review their own bookings within the employee dashboard.

### Primary workflows

1. Sign in as Admin or Employee.
2. Find or register an agent.
3. Enter stay requirements and search available hotel room options.
4. Select one or more room options.
5. Generate the standardized Airways Travels quotation.
6. Copy/share it to the customer and persist it in query history.
7. Lock the agent for the configured period where applicable.
8. Convert a confirmed request into a booking and track payment/status data.
9. Review dashboards, history, accounts, or CSV reports.

## 3. Features

### Implemented

- Login form with Admin/Employee login type selection.
- Password hashing and verification using PHP `password_hash`/`password_verify`.
- Session regeneration after login and eight-hour session timeout enforcement.
- CSRF token helpers and login rate limiting.
- Role guards for admin, employee, and authenticated access.
- Admin dashboard with live booking/agent metrics, status counts, and weekly chart data.
- Agent CRUD/search/filter views, status display, revenue/deal summaries, and CSV export.
- Employee directory with status, salary, login tracking, booking contribution, filters, and full-data export.
- Hotel master data with category/star rating, contact details, images, and status.
- Room category CRUD with room totals, booked/available/blocked counts, bed type, room size, and extra-bed settings.
- Meal plans and room-level base/date-wise prices. Canonical meal codes include EP, CP, MAP, and AP; some legacy manager code also supports AI.
- Availability calendar and rate calendar operations through AJAX endpoints.
- Booking creation, update, cancellation, payment fields, booking status, and booking history.
- Accounts ledger for commission, payout, receipt, and expense entries with summaries and filters.
- Booking query filtering by city/location, property category, dates, nights, adults, children, rooms, and nightly budget.
- Room-level result rows with meal-plan prices, availability, location, dates, and estimated stay totals.
- Query history for generated records and legacy agent query locks, with filters/search and admin visibility.
- Agent locking for employee query ownership, with admin override behavior.
- Shared `AirwaysQuotation` formatter for customer-ready text, UV-#### query numbers, all available meal plans, mandatory tax/cancellation/footer content, clipboard, and WhatsApp sharing.
- CSV exports for bookings, agents, and employee full data.
- Local database import, seed data, diagnostic, and virtual-host setup scripts.
- Apache hardening rules for sensitive files, directory listing, headers, PHP error exposure, caching, and selected bot blocking.

### Pending or incomplete

- Replace the stale Laravel GitHub Actions workflow with PHP/MySQL-appropriate CI checks.
- Add a repeatable automated test suite covering authenticated pages, AJAX responses, permissions, query generation, and booking transitions.
- Consolidate legacy CRM tables (`hotel_listings_details`, `bookings_details`) and canonical room-manager tables (`hotels`, `hotel_room_categories`, `hotel_bookings`, `room_prices`) or document a permanent integration boundary.
- Add a controlled migration/versioning process instead of relying primarily on runtime schema repair functions.
- Complete production environment configuration, HTTPS enforcement, backup/restore routines, monitoring, and log rotation.
- Perform a full responsive-browser and accessibility review across all dashboards and tables.
- Review CSRF enforcement consistently across every state-changing AJAX endpoint.
- Remove or reconcile dead/orphaned UI code and old compatibility paths after behavior is confirmed.
