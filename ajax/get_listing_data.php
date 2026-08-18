<?php
/** ajax/get_listing_data.php — Unified GET endpoint for listing.php */
require_once __DIR__ . '/helpers.php';
hl_auth();
$pdo  = hl_pdo();
$type = s($_GET['type'] ?? '');

switch ($type) {

  /* ── GET Availability ─────────────────────────────────────────────────── */
  case 'availability':
    $hotel_id  = i($_GET['hotel_id']  ?? 0);
    $from_date = s($_GET['from_date'] ?? date('Y-m-d'));
    $to_date   = s($_GET['to_date']   ?? date('Y-m-d', strtotime('+13 days')));
    if ($hotel_id <= 0) hl_err('hotel_id required.');
    if ($from_date > $to_date) hl_err('Invalid date range.');

    $rooms = $pdo->prepare("SELECT id,name,bed_type,total_rooms,available_rooms,booked_rooms,blocked_rooms FROM hotel_room_categories WHERE hotel_id=? AND status='active' ORDER BY id");
    $rooms->execute([$hotel_id]);
    $roomList = $rooms->fetchAll();
    if (empty($roomList)) { hl_ok(['data'=>[],'rooms'=>[]]); }

    $roomIds = array_column($roomList, 'id');
    $in      = implode(',', array_fill(0, count($roomIds), '?'));
    $avStmt  = $pdo->prepare("SELECT room_category_id,availability_date,available_rooms,booked_rooms,blocked_rooms,total_rooms FROM room_availability WHERE room_category_id IN ($in) AND availability_date BETWEEN ? AND ? ORDER BY availability_date");
    $avStmt->execute(array_merge($roomIds, [$from_date, $to_date]));
    $avRows  = $avStmt->fetchAll();
    $avMap   = [];
    foreach ($avRows as $av) $avMap[(int)$av['room_category_id']][$av['availability_date']] = $av;

    $result = [];
    $cur    = new DateTime($from_date);
    $end    = new DateTime($to_date);
    while ($cur <= $end) {
        $ds = $cur->format('Y-m-d');
        foreach ($roomList as $rm) {
            $rid = (int)$rm['id'];
            $result[] = $avMap[$rid][$ds] ?? ['room_category_id'=>$rid,'availability_date'=>$ds,'total_rooms'=>(int)$rm['total_rooms'],'available_rooms'=>(int)$rm['available_rooms'],'booked_rooms'=>(int)$rm['booked_rooms'],'blocked_rooms'=>(int)$rm['blocked_rooms']];
        }
        $cur->modify('+1 day');
    }
    hl_ok(['data' => $result, 'rooms' => $roomList]);
    break;

  /* ── GET Rates Calendar ───────────────────────────────────────────────── */
  case 'rates':
    $room_id   = i($_GET['room_id']   ?? 0);
    $meal_code = s($_GET['meal_plan']  ?? 'EP');
    $year      = i($_GET['year']       ?? date('Y'));
    $month     = i($_GET['month']      ?? date('m'));
    if ($room_id <= 0) hl_err('room_id required.');
    if (!in_array($meal_code, MEAL_CODES)) hl_err('Invalid meal_plan.');

    $plan_id = get_plan_id($pdo, $meal_code);
    $from    = sprintf('%04d-%02d-01', $year, $month);
    $to      = (new DateTime($from))->modify('last day of this month')->format('Y-m-d');

    // Date-specific rates
    $rStmt = $pdo->prepare("SELECT rate_date, date_wise_price AS price FROM room_prices WHERE room_category_id=? AND meal_plan_id=? AND rate_date BETWEEN ? AND ? ORDER BY rate_date");
    $rStmt->execute([$room_id, $plan_id, $from, $to]);
    $rates = $rStmt->fetchAll();

    // Base price
    $bStmt = $pdo->prepare("SELECT base_price FROM room_prices WHERE room_category_id=? AND meal_plan_id=? AND rate_date IS NULL");
    $bStmt->execute([$room_id, $plan_id]);
    $baseRow    = $bStmt->fetch();
    $base_price = $baseRow ? (float)$baseRow['base_price'] : 0.0;

    // All base prices by code
    $apStmt = $pdo->prepare("SELECT mp.code, rp.base_price FROM room_prices rp JOIN meal_plans mp ON mp.id=rp.meal_plan_id WHERE rp.room_category_id=? AND rp.rate_date IS NULL");
    $apStmt->execute([$room_id]);
    $allPrices = [];
    foreach ($apStmt->fetchAll() as $ap) $allPrices[$ap['code']] = (float)$ap['base_price'];

    hl_ok(['data'=>$rates,'base_price'=>$base_price,'base_prices'=>$allPrices,'year'=>$year,'month'=>$month]);
    break;

  /* ── GET Bookings ─────────────────────────────────────────────────────── */
  case 'bookings':
    $hotel_id   = i($_GET['hotel_id']   ?? 0);
    $booking_id = i($_GET['booking_id'] ?? 0);
    if ($booking_id > 0) {
        $s = $pdo->prepare("SELECT hb.*,h.name AS hotel_name,mp.code AS meal_plan_code,hrc.name AS room_name,br.rooms_count,br.price_per_night,br.extra_beds,br.adults,br.children FROM hotel_bookings hb JOIN hotels h ON h.id=hb.hotel_id LEFT JOIN meal_plans mp ON mp.id=hb.meal_plan_id LEFT JOIN booking_rooms br ON br.booking_id=hb.id LEFT JOIN hotel_room_categories hrc ON hrc.id=br.room_category_id WHERE hb.id=?");
        $s->execute([$booking_id]);
        $b = $s->fetch();
        hl_ok(['booking' => $b]);
    } elseif ($hotel_id > 0) {
        $s = $pdo->prepare("SELECT hb.id,hb.booking_number,hb.guest_name,hb.guest_phone,hb.guest_email,hb.checkin_date,hb.checkout_date,hb.total_nights,hb.total_amount,hb.booking_status,hb.payment_status,hb.source,hb.created_at,mp.code AS meal_plan_code,hrc.name AS room_name,hrc.id AS room_category_id,br.rooms_count,br.price_per_night,br.adults,br.children,br.extra_beds FROM hotel_bookings hb LEFT JOIN meal_plans mp ON mp.id=hb.meal_plan_id LEFT JOIN booking_rooms br ON br.booking_id=hb.id LEFT JOIN hotel_room_categories hrc ON hrc.id=br.room_category_id WHERE hb.hotel_id=? ORDER BY hb.created_at DESC LIMIT 300");
        $s->execute([$hotel_id]);
        hl_ok(['bookings' => $s->fetchAll()]);
    } else { hl_err('hotel_id or booking_id required.'); }
    break;

  default:
    hl_err('Invalid type. Use: availability|rates|bookings');
}
