<?php
/** ajax/save_room_category.php — Add new room category to a hotel */
require_once __DIR__ . '/helpers.php';
hl_require_admin();
$pdo = hl_pdo();
$d   = hl_body();

$hotel_id = i($d['hotel_id'] ?? 0);
$name     = s($d['name']     ?? '');
if ($hotel_id <= 0) hl_err('hotel_id required.');
if ($name === '')   hl_err('Room name required.');

// Verify hotel
$hChk = $pdo->prepare("SELECT id FROM hotels WHERE id=? AND status='active'");
$hChk->execute([$hotel_id]);
if (!$hChk->fetch()) hl_err('Hotel not found.', 404);

$bed      = in_array($d['bed_type'] ?? '', BED_TYPES) ? $d['bed_type'] : 'Double';
$size     = s($d['room_size']     ?? '');
$total    = max(0, i($d['total_rooms']     ?? 0));
$avail    = min($total, max(0, i($d['available_rooms'] ?? $total)));
$eb_on    = !empty($d['extra_bed_allowed']) ? 1 : 0;
$eb_pr    = f($d['extra_bed_price'] ?? 0);
$eb_mx    = max(0, i($d['max_extra_beds'] ?? 0));
$prices   = $d['prices'] ?? [];
if (is_string($prices)) $prices = json_decode($prices, true) ?? [];

try {
    $pdo->beginTransaction();
    $pdo->prepare("INSERT INTO hotel_room_categories (hotel_id,name,bed_type,room_size,total_rooms,available_rooms,booked_rooms,blocked_rooms,extra_bed_allowed,extra_bed_price,max_extra_beds,status) VALUES (?,?,?,?,?,?,0,0,?,?,?,'active')")
        ->execute([$hotel_id,$name,$bed,$size,$total,$avail,$eb_on,$eb_pr,$eb_mx]);
    $room_id = (int)$pdo->lastInsertId();

    $prStmt = $pdo->prepare("INSERT INTO room_prices (hotel_id,room_category_id,meal_plan_id,base_price,rate_date,date_wise_price) VALUES (?,?,?,?,NULL,NULL) ON DUPLICATE KEY UPDATE base_price=VALUES(base_price)");
    foreach ($prices as $code => $price) {
        if (!in_array($code, MEAL_CODES) || (float)$price <= 0) continue;
        $plan_id = get_plan_id($pdo, $code);
        if ($plan_id > 0) $prStmt->execute([$hotel_id, $room_id, $plan_id, (float)$price]);
    }

    $pdo->commit();
    $room = load_room($pdo, $room_id);
    hl_ok(['room_id' => $room_id, 'room' => $room], 'Room category added successfully.');
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    hl_err('Database error occurred. Please try again.', 500);
}
