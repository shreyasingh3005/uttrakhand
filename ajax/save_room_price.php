<?php
/** ajax/save_room_price.php — Save date-specific rate calendar prices */
require_once __DIR__ . '/helpers.php';
hl_require_admin_or_manager();
$pdo  = hl_pdo();
$d    = hl_body();
$rates = $d['rates'] ?? [];

if (!is_array($rates) || empty($rates)) hl_err('rates array required.');
if (count($rates) > 2000) hl_err('Max 2000 records per batch.');

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO room_prices (hotel_id,room_category_id,meal_plan_id,base_price,rate_date,date_wise_price) VALUES (?,?,?,0,?,?) ON DUPLICATE KEY UPDATE date_wise_price=VALUES(date_wise_price),updated_at=NOW()");
    $cnt = 0;
    foreach ($rates as $r) {
        $room_id   = i($r['room_id']   ?? 0);
        $meal_code = s($r['meal_plan'] ?? '');
        $date      = s($r['date']      ?? '');
        $price     = f($r['price']     ?? 0);
        if ($room_id <= 0 || !in_array($meal_code, MEAL_CODES) || !$date || $price < 0) continue;
        $plan_id  = get_plan_id($pdo, $meal_code);
        if (!$plan_id) continue;

        // Get hotel_id for this room
        $hId = $pdo->prepare("SELECT hotel_id FROM hotel_room_categories WHERE id=?");
        $hId->execute([$room_id]);
        $hRow = $hId->fetch();
        if (!$hRow) continue;

        $stmt->execute([$hRow['hotel_id'],$room_id,$plan_id,$date,$price]);
        $cnt++;
    }
    $pdo->commit();
    hl_ok(['count' => $cnt], "$cnt rate(s) saved successfully.");
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    hl_err('Database error occurred. Please try again.', 500);
}
