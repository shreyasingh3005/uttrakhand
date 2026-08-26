# High-Level Technical Design

## 1. Architecture

The application is a server-rendered PHP application with a shared PDO data layer and progressively enhanced browser JavaScript. It is not currently a framework-based MVC application and it does not have a verified REST router in the active code path.

**Request flow:**

```text
Browser
  -> PHP page or /ajax/*.php endpoint
  -> auth/session and input/security helpers
  -> PDO prepared statement(s)
  -> MySQL database
  -> HTML or JSON response
  -> browser JavaScript updates tables, modals, metrics, clipboard, or WhatsApp links
```

Page controllers and views are combined in the same PHP files. `includes/db_connect.php` creates the shared PDO connection and performs compatibility table/column checks. `ajax/helpers.php` provides a second PDO factory and standardized JSON helpers for the canonical hotel-manager endpoints.

There are two data-model generations:

1. **Legacy CRM model:** `users`, `dashboard_details`, `agents_details`, `employees_details`, `hotel_listings_details`, `hotel_listing_room_categories`, `bookings_details`, `accounts_details`, and related legacy query tables.
2. **Canonical hotel operations model:** `hotels`, `meal_plans`, `hotel_room_categories`, `room_prices`, `hotel_bookings`, `booking_rooms`, `room_availability`, and query/activity tables.

The current listing and room-manager pages primarily use the canonical model. Several dashboard and legacy workflows still use the legacy model or bridge both models.

## 2. Folder & File Structure

```text
abhi/
├── index.php                         # Login UI
├── process_login.php                 # Login POST handler
├── logout.php                        # Session logout and tracking
├── dashboard.php                     # Admin dashboard and admin booking actions
├── employee-dashboard.php            # Employee dashboard, booking/query AJAX actions
├── bookingquery.php                  # Admin property search/query generation
├── query-history.php                 # Admin query history and lock controls
├── booking-details.php               # Booking operations, filters, payment actions
├── agents-details.php                # Admin agent management and directory UI
├── employees-detail.php              # Employee directory and metrics
├── accounts-detail.php                # Account entries and summaries
├── listing.php                       # Canonical hotel/room listing UI
├── hotel-manager.php                  # Room/rate/availability manager UI
├── employee-listings.php              # Employee-facing listing/booking view
├── about.php, contact.php             # Informational pages
├── export-*-excel.php                 # Agent, employee, booking exports
├── seed_data.php                      # Demo data generator
├── test_*.php                         # Local query/flow diagnostic scripts
├── ajax/
│   ├── helpers.php                    # PDO, auth, validation, JSON helpers
│   ├── get_hotels.php                 # Hotel search/list data
│   ├── get_listing_data.php            # Availability/rates/bookings multiplexer
│   ├── get_availability.php            # Availability ranges
│   ├── get_rates.php                  # Room rates/calendar data
│   ├── get_booking.php                 # Booking retrieval
│   ├── create_booking.php              # Canonical booking creation
│   ├── update_booking.php              # Booking/status/payment updates
│   ├── cancel_booking.php              # Cancellation and room restoration
│   ├── save_hotel.php, update_hotel.php, delete_hotel.php
│   ├── save_room.php, update_room.php, delete_room.php
│   ├── save_room_category.php, update_room_category.php, delete_room_category.php
│   ├── save_room_price.php, save_rates.php, bulk_rate_update.php
│   └── save_availability.php            # Canonical writes
├── includes/
│   ├── config.php                      # .env.php loader, URLs, redirects, rewrite buffer
│   ├── db_connect.php                  # PDO and compatibility schema checks
│   ├── auth_session.php                # Session and role guards
│   ├── security.php                    # CSRF, validation, rate limiting, safe responses
│   ├── header.php, footer.php          # Shared shell pieces
│   └── left-sidebar.php, right-sidebar.php
├── assets/
│   ├── css/style.css, admin.css, sidebar.css
│   ├── css/ui-modern.css, ui-consistency.css
│   ├── js/app.js                       # Older unused router-style client helper
│   ├── js/ui-common.js                 # Sidebar/profile normalization
│   └── js/quotation-template.js         # Shared quotation formatter
├── database/all.sql                    # Schema, seed data, clean-start SQL
├── scripts/                            # Import, vhost, seed-user, setup documentation
└── .htaccess                           # Rewrite, blocking, headers, caching
```

## 3. Tech Stack

- **Backend:** PHP, procedural/page-oriented application code, PDO.
- **Database:** MySQL/MariaDB-compatible SQL using InnoDB and `utf8mb4`.
- **Frontend:** HTML, CSS, vanilla JavaScript.
- **UI libraries:** Bootstrap 5 CSS/JS from jsDelivr on most pages; Bootstrap Icons from jsDelivr.
- **Charts:** Chart.js loaded from jsDelivr on dashboard/reporting pages.
- **Typography:** Inter loaded from Google Fonts.
- **Authentication:** PHP sessions, `users.role` values `admin` and `employee`, password hashes, CSRF token, rate limiting, session timeout, role guards.
- **Runtime/deployment:** Apache + PHP + MySQL, primarily XAMPP on Windows; local URL `http://localhost/abhi` or configured virtual host `abhi.local`.
- **Exports:** PHP-generated Excel-compatible downloads in `export-*.php`.
- **External integrations:** WhatsApp `wa.me` / `api.whatsapp.com` links and browser Clipboard API. No package manifest or server-side dependency manager is present in the repository.
