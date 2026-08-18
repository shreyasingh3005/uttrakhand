<?php
/** ajax/cancel_booking.php — Cancel booking and restore availability */
require_once __DIR__ . '/helpers.php';
hl_require_admin_or_manager();
$pdo = hl_pdo();
$d   = hl_body();

$booking_id = i($d['booking_id'] ?? 0);
if ($booking_id <= 0) hl_err('booking_id required.');

$bChk = $pdo->prepare('SELECT * FROM hotel_bookings WHERE id=?');
$bChk->execute([$booking_id]);
$booking = $bChk->fetch();
if (!$booking) hl_err('Booking not found.', 404);
if ($booking['booking_status'] === 'cancelled') hl_err('Booking is already cancelled.');

// Get booking rooms
$brStmt = $pdo->prepare('SELECT room_category_id, rooms_count FROM booking_rooms WHERE booking_id=?');
$brStmt->execute([$booking_id]);
$bRooms = $brStmt->fetchAll();

$checkin  = $booking['checkin_date'];
$checkout = $booking['checkout_date'];
$dtIn     = new DateTime($checkin);
$dtOut    = new DateTime($checkout);
$dates    = [];
$cur      = clone $dtIn;
while ($cur < $dtOut) { $dates[] = $cur->format('Y-m-d'); $cur->modify('+1 day'); }

try {
    $pdo->beginTransaction();

    foreach ($bRooms as $br) {
        $rid    = (int)$br['room_category_id'];
        $rcount = (int)$br['rooms_count'];

        if (!empty($dates)) {
            $in = implode(',', array_fill(0, count($dates), '?'));
            $pdo->prepare("UPDATE room_availability SET available_rooms=available_rooms+?,booked_rooms=GREATEST(0,booked_rooms-?) WHERE room_category_id=? AND availability_date IN ($in)")
                ->execute(array_merge([$rcount,$rcount,$rid], $dates));
        }
        $pdo->prepare("UPDATE hotel_room_categories SET available_rooms=available_rooms+?,booked_rooms=GREATEST(0,booked_rooms-?),updated_at=NOW() WHERE id=?")
            ->execute([$rcount,$rcount,$rid]);
    }

    $pdo->prepare("UPDATE hotel_bookings SET booking_status='cancelled',updated_at=NOW() WHERE id=?")->execute([$booking_id]);
    $pdo->commit();
    hl_ok(['booking_id'=>$booking_id,'booking_number'=>$booking['booking_number']], "Booking {$booking['booking_number']} cancelled. Rooms restored.");
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    hl_err('Database error occurred. Please try again.', 500);
}
