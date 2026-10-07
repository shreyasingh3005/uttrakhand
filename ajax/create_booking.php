<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../includes/booking_service.php';
hl_require_admin_or_manager();
$d = hl_body();
try {
    $result = booking_create(hl_pdo(), $d);
    hl_ok($result, 'Booking confirmed.');
} catch (InvalidArgumentException $e) {
    hl_err($e->getMessage(), 422);
} catch (DomainException $e) {
    hl_err($e->getMessage(), 409);
} catch (Throwable $e) {
    error_log('Booking creation: ' . $e->getMessage());
    hl_err('Unable to create booking. Please try again.', 500);
}
