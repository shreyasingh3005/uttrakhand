<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../includes/crm_booking.php';
hl_require_admin_or_manager();
$d = hl_body();
try {
    $result = crm_reservation_update(hl_pdo(), (int)($d['booking_id'] ?? 0), ['status'=>'cancelled']);
    hl_ok($result, 'Booking cancelled. Rooms restored.');
} catch (InvalidArgumentException $e) {
    hl_err($e->getMessage(), 422);
} catch (DomainException $e) {
    hl_err($e->getMessage(), 409);
} catch (Throwable $e) {
    error_log('Booking cancellation: ' . $e->getMessage());
    hl_err('Unable to cancel booking. Please try again.', 500);
}
