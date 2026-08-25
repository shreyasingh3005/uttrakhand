# High-Level Technical Design

## 1. Architecture

The application is a server-rendered PHP monolith with embedded page controllers, HTML views, and page-specific JavaScript. It is not a Laravel application and does not currently implement a formal MVC framework, service layer, or versioned REST API.

The practical pattern is:

- **Page controller/view:** top-level `.php` pages authenticate, query data, process some POST actions, and render HTML.
- **Shared infrastructure:** `includes/config.php`, `includes/db_connect.php`, `includes/auth_session.php`, and `includes/security.php` provide configuration, PDO, sessions, authorization, and security helpers.
- **AJAX handlers:** `ajax/*.php` expose JSON operations for the hotel room manager. `employee-dashboard.php`, `bookingquery.php`, and `dashboard.php` also contain action-based POST endpoints.
- **Client layer:** browser JavaScript handles filtering, modals, AJAX requests, result rendering, quotation formatting, clipboard, and WhatsApp links.
- **Persistence:** MySQL accessed through PDO prepared statements. Some schema compatibility checks create or alter tables during application bootstrap.

### Request/data flow

1. Browser requests a PHP page.
2. The page loads configuration and opens a PDO connection.
3. Session/authentication and role checks run.
4. PHP queries MySQL and renders HTML plus initial data.
5. Browser JavaScript submits form data or JSON to a page action or `ajax/*.php` endpoint.
6. The endpoint validates/authenticates, performs prepared SQL, and returns either JSON or a redirect.
7. The UI updates tables/cards/modals and can call `AirwaysQuotation` for copy/share output.

## 2. Folder & File Structure

```text
/
├── index.php                         Login UI
├── process_login.php                 Login processing
├── dashboard.php                     Admin dashboard and admin actions
├── employee-dashboard.php            Employee SPA-like workspace and query actions
├── bookingquery.php                  Admin booking-query workspace
├── query-history.php                 Admin query-history page
├── agents-details.php                Admin agent directory
├── employees-detail.php              Admin employee directory
├── listing.php                       Canonical hotel room manager
├── hotel-manager.php                 Older/compatibility hotel manager UI
├── employee-listings.php             Employee listing view
├── booking-details.php               Booking operations/history
├── accounts-detail.php               Accounts ledger
├── about.php                          Admin about page
├── contact.php                        Contact page
├── export-*-excel.php                CSV exports
├── logout.php                         Logout handler
├── ajax/                              JSON hotel/room CRUD endpoints
│   ├── helpers.php                    PDO, auth, response, validation/schema helpers
│   ├── get_hotels.php, get_listing_data.php
│   ├── save_hotel.php, update_hotel.php, delete_hotel.php
│   ├── save_room.php, update_room.php, delete_room.php
│   ├── save_room_category.php, update_room_category.php, delete_room_category.php
│   ├── save_room_price.php, save_rates.php, get_rates.php, bulk_rate_update.php
│   ├── save_availability.php, get_availability.php
│   ├── create_booking.php, update_booking.php, cancel_booking.php, get_booking.php
├── includes/
│   ├── config.php                     `.env.php` loader, URL helpers, URL rewriting
│   ├── db_connect.php                 PDO and compatibility schema setup
│   ├── auth_session.php               Session/login/role guards
│   ├── security.php                   Rate limit, CSRF, validation, headers
│   ├── header.php, footer.php         Shared layout pieces
│   ├── left-sidebar.php, right-sidebar.php
├── assets/
│   ├── css/                           admin, shared, sidebar, consistency, modern CSS
│   ├── js/app.js                      Legacy generic API/UI functions
│   ├── js/admin-sidebar.js             Sidebar behavior
│   ├── js/quotation-template.js        Shared quotation formatter
│   └── js/ui-common.js                 Shared UI normalization
├── database/all.sql                   Full MySQL schema/import script
├── scripts/                           Import, seed-user, diagnostics, vhost setup
├── seed_data.php                      Demo hotels, rooms, prices, availability/bookings
├── test_*.php                         Narrow database/query smoke tests
├── .env.php                           Local configuration; sensitive and blocked by Apache
├── .htaccess                          Apache security headers and file rules
└── docs/                              Project context and documentation
```

## 3. Tech Stack

- **Backend:** PHP 8-compatible procedural/server-rendered PHP.
- **Database:** MySQL/MariaDB through PDO, database name currently `employee_management` in `.env.php`.
- **Web server/runtime:** Apache via XAMPP on Windows; local URL is `http://localhost/abhi` or optional `abhi.local` virtual host.
- **Frontend:** HTML5, CSS3, vanilla JavaScript, server-rendered PHP templates.
- **UI libraries:** Bootstrap 5.3 CSS/JS CDN and Bootstrap Icons CDN. Google Fonts uses Inter.
- **HTTP/data formats:** form-encoded POST, JSON request support in AJAX helpers, JSON responses, CSV downloads, WhatsApp URL links.
- **Authentication:** PHP sessions, role values `admin` and `employee`, session regeneration, timeout, password hashing, CSRF helpers, rate limiting.
- **Database model:** legacy CRM tables plus canonical hotel room-manager tables. Key canonical entities are `hotels`, `meal_plans`, `hotel_room_categories`, `room_prices`, `room_availability`, `hotel_bookings`, and `booking_rooms`.
- **Dependencies/package management:** no `composer.json`, `package.json`, or checked-in vendor directory is present. The checked-in GitHub workflow references Laravel/Composer/Artisan and is therefore not aligned with the current application.
- **Third-party services:** CDN-hosted Bootstrap, Bootstrap Icons, and Google Fonts; WhatsApp share endpoints. No external application API client is configured.
