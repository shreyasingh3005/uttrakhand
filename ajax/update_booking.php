<?php
/** ajax/update_booking.php — Update booking status */
require_once __DIR__ . '/helpers.php';
hl_require_admin_or_manager();
$pdo = hl_pdo();
$d   = hl_body();

$booking_id  = i($d['booking_id']     ?? 0);
$status      = s($d['status']         ?? '');
$payment_st  = s($d['payment_status'] ?? '');
$special_req = s($d['special_requests'] ?? '');

if ($booking_id <= 0) hl_err('booking_id required.');

$valid_statuses = ['confirmed','pending','checked_in','checked_out','cancelled'];
$valid_payments = ['pending','partial','paid'];
if ($status !== '' && !in_array($status, $valid_statuses)) hl_err('Invalid booking_status.');
if ($payment_st !== '' && !in_array($payment_st, $valid_payments)) hl_err('Invalid payment_status.');

$bChk = $pdo->prepare('SELECT id,booking_status,booking_number,guest_name FROM hotel_bookings WHERE id=?');
$bChk->execute([$booking_id]);
$booking = $bChk->fetch();
if (!$booking) hl_err('Booking not found.', 404);

$fields = [];
$params = [];
if ($status !== '')      { $fields[] = 'booking_status=?'; $params[] = $status; }
if ($payment_st !== '')  { $fields[] = 'payment_status=?'; $params[] = $payment_st; }
if ($special_req !== '') { $fields[] = 'special_requests=?'; $params[] = $special_req; }
$fields[] = 'updated_at=NOW()';
if (count($fields) <= 1) hl_err('Nothing to update.');

$params[] = $booking_id;
try {
    $pdo->prepare("UPDATE hotel_bookings SET " . implode(',', $fields) . " WHERE id=?")->execute($params);
    hl_ok(['booking_id'=>$booking_id,'booking_number'=>$booking['booking_number'],'status'=>$status], 'Booking updated successfully.');
} catch (PDOException $e) {
    hl_err('Database error occurred. Please try again.', 500);
}
