<?php
/** Non-destructive booking upgrade. Run using the intended deployment configuration. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
ini_set('zend.exception_ignore_args', '1');
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/crm_booking.php';
try {
    crm_booking_schema($conn);
    $conn->exec("CREATE TABLE IF NOT EXISTS crm_schema_migrations (version VARCHAR(100) PRIMARY KEY, applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
    $version='20261007_booking_payment_integrity';
    $conn->beginTransaction();
    $stmt=$conn->prepare('SELECT version FROM crm_schema_migrations WHERE version=? FOR UPDATE');
    $stmt->execute([$version]);
    if (!$stmt->fetchColumn()) {
        // Repair cancellation balances overwritten by the old connection-time backfill.
        // Collected payments and payment notes are deliberately preserved.
        $conn->exec("UPDATE bookings_details SET due_amount=0,payment_status='Cancelled' WHERE booking_status='Cancelled'");
        $conn->prepare('INSERT INTO crm_schema_migrations(version) VALUES (?)')->execute([$version]);
    }
    $conn->commit();
    echo "Booking migration applied. Existing bookings and payments preserved.\n";
} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    error_log('Booking migration: '.$e->getMessage());
    fwrite(STDERR,"Booking migration failed. Review the server error log.\n");
    exit(1);
}
