<?php
/** ajax/create_booking.php — Create new hotel booking with availability check */
require_once __DIR__ . '/helpers.php';
hl_require_admin_or_manager();
$pdo = hl_pdo();
$d   = hl_body();

$hotel_id    = i($d['hotel_id']        ?? 0);
$room_cat_id = i($d['room_category_id'] ?? 0);
$guest_name  = s($d['guest_name']      ?? '');
$guest_phone = s($d['guest_phone']     ?? '');
$guest_email = s($d['guest_email']     ?? '');
$checkin     = s($d['checkin_date']    ?? $d['check_in']    ?? '');
$checkout    = s($d['checkout_date']   ?? $d['check_out']   ?? '');
$adults      = max(1, i($d['adults']   ?? 1));
$children    = max(0, i($d['children'] ?? 0));
$meal_code   = s($d['meal_plan']       ?? 'EP');
$rooms_count = max(1, i($d['rooms_count'] ?? 1));
$extra_beds  = max(0, i($d['extra_beds']  ?? 0));
$special_req = s($d['special_requests'] ?? '');
$source      = s($d['source']           ?? 'direct');
$payment_st  = in_array($d['payment_status'] ?? '', ['pending','partial','paid']) ? $d['payment_status'] : 'pending';

if ($hotel_id <= 0)    hl_err('hotel_id required.');
if ($room_cat_id <= 0) hl_err('room_category_id required.');
if ($guest_name === '') hl_err('Guest name required.');
if (!$checkin || !$checkout) hl_err('Check-in and check-out dates required.');
if ($checkin >= $checkout)   hl_err('Check-out must be after check-in.');
if ($guest_email !== '' && !filter_var($guest_email, FILTER_VALIDATE_EMAIL)) hl_err('Invalid guest email.');
if (!in_array($meal_code, MEAL_CODES)) $meal_code = 'EP';

$dtIn    = new DateTime($checkin);
$dtOut   = new DateTime($checkout);
$nights  = (int)$dtIn->diff($dtOut)->days;
if ($nights < 1) hl_err('Minimum 1 night required.');

// Get room info
$rChk = $pdo->prepare("SELECT * FROM hotel_room_categories WHERE id=? AND status='active'");
$rChk->execute([$room_cat_id]);
$roomInfo = $rChk->fetch();
if (!$roomInfo) hl_err('Room category not found.', 404);

// Get meal plan id
$plan_id = get_plan_id($pdo, $meal_code);
if (!$plan_id) hl_err('Meal plan not found.');

// Build dates array
$dates = [];
$cur   = clone $dtIn;
while ($cur < $dtOut) { $dates[] = $cur->format('Y-m-d'); $cur->modify('+1 day'); }

// Availability check
if (!empty($dates)) {
    $in    = implode(',', array_fill(0, count($dates), '?'));
    $avStmt = $pdo->prepare("SELECT availability_date, available_rooms FROM room_availability WHERE room_category_id=? AND availability_date IN ($in)");
    $avStmt->execute(array_merge([$room_cat_id], $dates));
    $avMap   = [];
    foreach ($avStmt->fetchAll() as $av) $avMap[$av['availability_date']] = (int)$av['available_rooms'];
    $defaultAvail = (int)$roomInfo['available_rooms'];
    foreach ($dates as $dt) {
        $avail = $avMap[$dt] ?? $defaultAvail;
        if ($avail < $rooms_count) hl_err("Not enough rooms on $dt. Available: $avail, Requested: $rooms_count.");
    }
}

// Get price: first check date-specific rates (average), fallback to base price
$rateQ = $pdo->prepare("SELECT AVG(COALESCE(date_wise_price, base_price)) AS avg_price FROM room_prices WHERE room_category_id=? AND meal_plan_id=? AND rate_date BETWEEN ? AND ?");
$rateQ->execute([$room_cat_id, $plan_id, $checkin, $checkout]);
$rateRow = $rateQ->fetch();
if ($rateRow && $rateRow['avg_price'] !== null) {
    $price_per_night = (float)$rateRow['avg_price'];
} else {
    $bpQ = $pdo->prepare("SELECT base_price FROM room_prices WHERE room_category_id=? AND meal_plan_id=? AND rate_date IS NULL");
    $bpQ->execute([$room_cat_id, $plan_id]);
    $bpRow = $bpQ->fetch();
    $price_per_night = $bpRow ? (float)$bpRow['base_price'] : 0.0;
}

if ($price_per_night <= 0) {
    hl_err('Rate calendar/base rate is not configured for this room and meal plan. Please configure pricing first.');
}

$eb_price     = (float)$roomInfo['extra_bed_price'];
$total_amount = ($price_per_night * $rooms_count + $eb_price * $extra_beds) * $nights;
$booking_num  = gen_booking_num($pdo);

try {
    $pdo->beginTransaction();

    $pdo->prepare("INSERT INTO hotel_bookings (booking_number,hotel_id,guest_name,guest_phone,guest_email,checkin_date,checkout_date,total_nights,total_amount,meal_plan_id,special_requests,source,booking_status,payment_status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'confirmed',?)")
        ->execute([$booking_num,$hotel_id,$guest_name,$guest_phone,$guest_email,$checkin,$checkout,$nights,$total_amount,$plan_id,$special_req,$source,$payment_st]);
    $booking_id = (int)$pdo->lastInsertId();

    $room_total = $price_per_night * $rooms_count * $nights + $eb_price * $extra_beds * $nights;
    $pdo->prepare("INSERT INTO booking_rooms (booking_id,room_category_id,meal_plan_id,rooms_count,adults,children,extra_beds,price_per_night,total_price) VALUES (?,?,?,?,?,?,?,?,?)")
        ->execute([$booking_id,$room_cat_id,$plan_id,$rooms_count,$adults,$children,$extra_beds,$price_per_night,$room_total]);

    // Update availability per date
    $upsertAv = $pdo->prepare("INSERT INTO room_availability (hotel_id,room_category_id,availability_date,total_rooms,available_rooms,booked_rooms,blocked_rooms) VALUES (?,?,?,?,GREATEST(0,?-?),?,0) ON DUPLICATE KEY UPDATE available_rooms=GREATEST(0,available_rooms-?),booked_rooms=booked_rooms+?");
    $def = (int)$roomInfo['available_rooms'];
    foreach ($dates as $dt) {
        $curAv = isset($avMap[$dt]) ? $avMap[$dt] : $def;
        $upsertAv->execute([$hotel_id,$room_cat_id,$dt,(int)$roomInfo['total_rooms'],$curAv,$rooms_count,$rooms_count,$rooms_count,$rooms_count]);
    }

    // Update room_categories summary
    $pdo->prepare("UPDATE hotel_room_categories SET available_rooms=GREATEST(0,available_rooms-?),booked_rooms=booked_rooms+?,updated_at=NOW() WHERE id=?")
        ->execute([$rooms_count,$rooms_count,$room_cat_id]);

    $pdo->commit();
    hl_ok(['booking_id'=>$booking_id,'booking_number'=>$booking_num,'total_amount'=>$total_amount,'nights'=>$nights], "Booking $booking_num confirmed. Total: ₹" . number_format($total_amount, 2));
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    hl_err('Database error occurred. Please try again.', 500);
}
