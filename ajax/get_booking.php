<?php
/** ajax/get_booking.php — Get booking details */
require_once __DIR__ . '/helpers.php';
hl_auth();
$pdo = hl_pdo();

$hotel_id   = i($_GET['hotel_id']   ?? 0);
$booking_id = i($_GET['booking_id'] ?? 0);

try {
    if ($booking_id > 0) {
        $stmt = $pdo->prepare("
            SELECT hb.*, h.name AS hotel_name,
                   hrc.name AS room_name, hrc.bed_type,
                   br.rooms_count, br.price_per_night, br.extra_beds, br.adults, br.children
            FROM hotel_bookings hb
            JOIN hotels h ON h.id=hb.hotel_id
            LEFT JOIN booking_rooms br ON br.booking_id=hb.id
            LEFT JOIN hotel_room_categories hrc ON hrc.id=br.room_category_id
            WHERE hb.id=?
        ");
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch();
        if (!$booking) hl_err('Booking not found.', 404);
        $booking['total_amount'] = (float)$booking['total_amount'];
        hl_ok(['booking' => $booking]);
    } elseif ($hotel_id > 0) {
        $stmt = $pdo->prepare("
            SELECT hb.id, hb.booking_number, hb.guest_name, hb.guest_email, hb.guest_phone,
                   hb.checkin_date, hb.checkout_date, hb.total_nights, hb.total_amount,
                   hb.booking_status, hb.payment_status, hb.source, hb.special_requests, hb.created_at,
                   mp.code AS meal_plan_code,
                   hrc.name AS room_name, hrc.id AS room_category_id,
                   br.rooms_count, br.price_per_night, br.extra_beds, br.adults, br.children
            FROM hotel_bookings hb
            LEFT JOIN meal_plans mp ON mp.id=hb.meal_plan_id
            LEFT JOIN booking_rooms br ON br.booking_id=hb.id
            LEFT JOIN hotel_room_categories hrc ON hrc.id=br.room_category_id
            WHERE hb.hotel_id=?
            ORDER BY hb.created_at DESC
            LIMIT 200
        ");
        $stmt->execute([$hotel_id]);
        $bookings = $stmt->fetchAll();
        foreach ($bookings as &$bk) {
            $bk['total_amount'] = (float)$bk['total_amount'];
            $bk['total_nights'] = (int)$bk['total_nights'];
        }
        unset($bk);
        hl_ok(['bookings' => $bookings, 'count' => count($bookings)]);
    } else {
        hl_err('hotel_id or booking_id is required.');
    }
} catch (PDOException $e) {
    hl_err('Database error occurred. Please try again.', 500);
}
