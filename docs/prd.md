# Uttarakhand Ventures CRM

## 1. What to Build

**Project name:** Uttarakhand Ventures CRM.

**Purpose:** A PHP/MySQL travel and hotel operations CRM for managing agents, employees, hotel properties, rooms, rates, availability, bookings, accounts, and customer-facing booking quotations.

**Current state:** Approximately 80% complete. The main operational workflows are implemented, including authentication, role-based dashboards, hotel/room/rate management, booking operations, agent query locks, quotation generation, query history, reporting views, and Excel exports. Remaining work is primarily hardening, regression testing, consolidation of legacy and canonical data models, and deployment verification.

**Key objectives:**
- Provide secure admin and employee access.
- Maintain hotel, room-category, meal-plan, rate, and availability data.
- Let staff find matching properties for a booking request.
- Generate consistent WhatsApp-ready quotations.
- Track agents, employees, bookings, payments, query history, and account entries.
- Provide searchable dashboards and operational exports.

## 2. Targeted User

### Admin

Admin users authenticate with the `admin` role and can access the administrative dashboard and management modules. Their workflows include:
- Review dashboard KPIs, booking/payment summaries, and live metrics.
- Create and manage agents and employee records.
- Create, edit, filter, and manage hotel listings and room categories.
- Maintain meal-plan prices, date-wise rates, room availability, and bookings.
- Generate booking queries, override agent locks, and inspect all query history.
- Review accounts and download agent, employee, and booking Excel reports.

### Employee

Employee users authenticate with the `employee` role and use `employee-dashboard.php`. Their workflows include:
- Review personal booking and payment metrics.
- Create and search agents where the employee workflow allows it.
- Search hotels by location, category, dates, occupancy, rooms, and budget.
- Select one or more room options and generate a quotation.
- Copy or send quotations through WhatsApp.
- Lock an agent query for the configured lock period and review personal query history.
- Create and manage bookings within the exposed employee workflow.

### Customer / Agent recipient

Customers and external agents are quotation recipients rather than authenticated application users. They receive plain WhatsApp-ready text containing hotel, date, occupancy, room, meal-plan, price, cancellation, and contact information.

## 3. Features

### Implemented

- Login form and POST login handler in `index.php` and `process_login.php`.
- Password hashing/verification, CSRF verification, session regeneration, login rate limiting, session timeout, and role checks.
- Admin dashboard with live KPI/status data and dashboard summaries.
- Employee dashboard with live personal booking, payment, source, hotel, and status metrics.
- Agent CRUD/search/status summaries and agent detail views.
- Employee directory, filters, login status, payroll summary, booking contribution data, and exports.
- Hotel listing management with hotel codes, names, location, category/star rating, contact fields, status, filters, pagination, room categories, and images/details fields.
- Canonical room manager with active room categories, bed types, room counts, extra-bed settings, rate calendar, meal plans, and availability grid.
- Meal plans currently modeled as EP, CP, MAP, AP; some seed/UI paths also recognize AI.
- Base room prices and date-wise price overrides.
- Booking creation, retrieval, update, cancellation, room availability checks, payment/status tracking, and booking history.
- Agent query locks and activity logging.
- Booking-query property matching using location/category/date/occupancy/room/budget filters.
- Quotation generation with `UV-0001` style query numbers, all available meal-plan prices, no GST/tax sentence, cancellation policy, contact block, and duplicate-block prevention for multiple room selections.
- Copy and WhatsApp actions on admin and employee query-generation/history surfaces.
- Query history filters, search, view, copy, lock/unlock, and generated-query storage.
- Excel exports for agents, employees, and bookings.
- Shared UI layers in `assets/css/ui-consistency.css`, `assets/css/ui-modern.css`, and `assets/js/ui-common.js`.
- Apache rewrite/security configuration and XAMPP setup/import helper scripts.

### Pending / incomplete

- Consolidate the legacy CRM tables (`*_details`) and canonical hotel-manager tables into one supported model.
- Remove or formally deprecate stale API code in `assets/js/app.js`, which references an absent `router.php/api` endpoint.
- Add automated browser/API tests for role permissions, booking transitions, availability, quotation output, and responsive layouts.
- Review all direct AJAX POST actions for consistent CSRF enforcement and authorization.
- Replace demo/seed credentials and rotate any credentials that may have existed in environment files.
- Finish production deployment validation, HTTPS, backups, monitoring, and migration discipline.
- Resolve remaining legacy UI duplication and naming differences between pages.
