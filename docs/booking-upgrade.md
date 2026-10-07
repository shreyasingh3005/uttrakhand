# Booking upgrade and validation

The existing Booking page and employee dashboard now share `includes/crm_booking.php`.
Room reservations use `includes/booking_service.php`. No existing tables are dropped.

## Behavior

- New room bookings reserve each occupied night under a transaction and calculate each night's configured price, excluding checkout.
- The Booking page supports assignment, enquiry ID linkage, status changes, payment updates, recent-activity notifications, history, search, date filters and 50-row pagination.
- Employees can read and change bookings they created or are assigned. Other employees cannot retrieve their guest details through reservation APIs.
- Cancellation restores inventory once and preserves money already collected. Completed and cancelled reservations cannot be reopened without a new booking.
- Existing direct/dashboard bookings remain supported as manual CRM bookings. They do **not** reserve a room unless a room category is supplied. Use **New room booking** for inventory-backed reservations.
- `crm_booking_workflow` links CRM records to reservations and assignments. `crm_hotel_links` explicitly bridges canonical hotels to legacy hotel records, preserving existing foreign keys.
- `hotel-manager.php` redirects to the existing persisted room/rate/availability interface in `listing.php`; the former screen's Save buttons only displayed success messages without saving.
- Room and hotel removal now archives records and preserves booking/rate history. Active reservations prevent removal.
- Writes require CSRF tokens. Login account deletion, role changes and password changes invalidate authenticated sessions on guarded data pages/APIs.

## Deployment

The user's `.env.php` was left unchanged. Its configured database login was rejected during initial testing; deployment against that database is not verified.

After restoring database access and taking the normal database backup, run:

```powershell
C:\xampp\php\php.exe scripts\migrate_bookings.php
```

This additive, repeatable migration creates the workflow/link tables and repairs cancelled-booking balances previously overwritten by the old connection-time backfill. It preserves collected payment amounts.

`CRM_DB_HOST`, `CRM_DB_NAME`, `CRM_DB_USER`, `CRM_DB_PASS` and `CRM_APP_URL` environment variables can override configuration for isolated environments. Production should not point to the QA database. `.env.php` is ignored for future additions, but an already tracked file still requires separate Git-history/credential remediation before publishing.

## Validation completed

- 74 HTTP regression checks: both logins, primary page rendering, inline JavaScript parsing, search, authorization, CSRF rejection, booking creation, deduplication, assignment, payments, overbooking, invalid dates, cancellation, inventory restoration, completion and logout.
- PHP lint: 67 files at the validation checkpoint; JavaScript syntax checks passed.
- Browser: actual booking creation with payment and assignment; booking modal visibility and scrolling; layout widths 390, 768 and 1440 pixels without document-level horizontal overflow on the Booking page.
- Tests used only the separate `crm_validation_20261007` database. They did not validate the intended deployment database.

## Remaining verification

This is not a certification that the entire CRM is production-ready. Remaining work includes full CRUD coverage for every older module, sustained-load tests, every-page responsive testing, SMTP/password-reset delivery, legacy enquiry/lock behavior, large-dataset performance, and deployment HTTPS/backups. Historical CRM bookings are not automatically mapped to canonical reservations because matching IDs across the two schemas is unsafe.

## Resumed validation

- Four simultaneous reservation requests: one succeeds, three receive availability conflicts.
- Four simultaneous cancellations: inventory is restored exactly once.
- Mixed base/nightly prices, checkout exclusion, deposits, repeated room-price saves, linked CRM cancellation, calendar reservation protection, and scoped notifications passed.
- Fixed bulk-rate updates accepting rooms from another hotel, invalid calendar months, and lowercase hotel codes losing letters.
