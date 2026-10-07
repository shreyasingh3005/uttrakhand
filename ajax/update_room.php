<?php
/** ajax/update_room.php — Update room category */
require_once __DIR__ . '/helpers.php';
hl_require_admin();
$pdo = hl_pdo();
$d   = hl_body();

$room_id  = i($d['room_id'] ?? 0);
$name     = s($d['name']      ?? '');
if ($room_id <= 0) hl_err('Valid room_id is required.');
if ($name === '')  hl_err('Room name is required.');

$chk = $pdo->prepare('SELECT id,hotel_id FROM hotel_room_categories WHERE id=?');
$chk->execute([$room_id]);
$roomRow = $chk->fetch();
if (!$roomRow) hl_err('Room not found.', 404);
$hotel_id = (int)$roomRow['hotel_id'];

$bed      = in_array($d['bed_type'] ?? '', BED_TYPES) ? $d['bed_type'] : 'Double';
$size     = s($d['room_size']     ?? '');
$total    = max(1, i($d['total_rooms']     ?? 1));
$avail    = min($total, max(0, i($d['available_rooms'] ?? $total)));
$eb_on    = !empty($d['extra_bed_allowed']) ? 1 : 0;
$eb_price = f($d['extra_bed_price'] ?? 0);
$eb_max   = max(0, i($d['extra_bed_max'] ?? $d['max_extra_beds'] ?? 0));
$prices   = $d['prices'] ?? [];
if (is_string($prices)) $prices = json_decode($prices, true) ?? [];

try {
    $pdo->beginTransaction();
    $lock = $pdo->prepare('SELECT id FROM hotel_room_categories WHERE id=? FOR UPDATE');
    $lock->execute([$room_id]);
    $capacity = $pdo->prepare('SELECT MAX(booked_rooms+blocked_rooms) FROM room_availability WHERE room_category_id=? AND availability_date>=CURDATE()');
    $capacity->execute([$room_id]);
    if ((int)$capacity->fetchColumn() > $total) {
        $pdo->rollBack();
        hl_err('Room count cannot be lower than reserved and blocked rooms.', 409);
    }

    $pdo->prepare("UPDATE hotel_room_categories SET name=?,bed_type=?,room_size=?,total_rooms=?,available_rooms=?,extra_bed_allowed=?,extra_bed_price=?,max_extra_beds=?,updated_at=NOW() WHERE id=?")
        ->execute([$name, $bed, $size, $total, $avail, $eb_on, $eb_price, $eb_max, $room_id]);

    // Upsert all provided meal plan prices
    $findBase = $pdo->prepare('SELECT id FROM room_prices WHERE room_category_id=? AND meal_plan_id=? AND rate_date IS NULL ORDER BY id DESC LIMIT 1');
    $updateBase = $pdo->prepare('UPDATE room_prices SET base_price=?,updated_at=NOW() WHERE room_category_id=? AND meal_plan_id=? AND rate_date IS NULL');
    $mp = $pdo->prepare("INSERT INTO room_prices (hotel_id,room_category_id,meal_plan_id,base_price,rate_date,date_wise_price) VALUES (?,?,?,?,NULL,NULL)");
    foreach (MEAL_CODES as $code) {
        $price = f($prices[$code] ?? 0);
        if ($price > 0) {
            $plan_id = get_plan_id($pdo, $code);
            if ($plan_id) {
                $findBase->execute([$room_id,$plan_id]);
                if ($findBase->fetchColumn()) $updateBase->execute([$price,$room_id,$plan_id]);
                else $mp->execute([$hotel_id, $room_id, $plan_id, $price]);
            }
        }
    }

    $pdo->commit();
    $room = load_room($pdo, $room_id);
    hl_ok(['room' => $room], 'Room category updated successfully.');
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    hl_err('Database error occurred. Please try again.', 500);
}
