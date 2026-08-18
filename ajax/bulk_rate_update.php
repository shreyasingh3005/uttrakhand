<?php
/** ajax/bulk_rate_update.php — Bulk rate update across date range */
require_once __DIR__ . '/helpers.php';
hl_require_admin_or_manager();
$pdo = hl_pdo();
$d   = hl_body();

$hotel_id    = i($d['hotel_id']    ?? 0);
$room_id_raw = $d['room_id']       ?? 'all';
$meal_code   = s($d['meal_plan']   ?? '');
$from_date   = s($d['from_date']   ?? '');
$to_date     = s($d['to_date']     ?? '');
$price       = (float)($d['price'] ?? -1);
$raw_days    = is_array($d['days_of_week'] ?? null) ? $d['days_of_week'] : [];

if ($hotel_id <= 0)                      hl_err('hotel_id required.');
if (!in_array($meal_code, MEAL_CODES))   hl_err('Invalid meal_plan code.');
if (!$from_date || !$to_date)            hl_err('from_date and to_date required.');
if ($from_date > $to_date)               hl_err('from_date must be <= to_date.');
if ($price < 0)                          hl_err('price must be >= 0.');

$plan_id = get_plan_id($pdo, $meal_code);
if (!$plan_id) hl_err('Meal plan not found.');

// Normalise days_of_week
$days = [];
foreach ($raw_days as $dv) {
    if (is_numeric($dv) && (int)$dv >= 0 && (int)$dv <= 6) { $days[] = (int)$dv; continue; }
    if (is_string($dv) && isset(DOW[$dv]))                  { $days[] = DOW[$dv]; continue; }
}
$days = array_values(array_unique($days));

// Get room IDs
if ($room_id_raw === 'all') {
    $rs = $pdo->prepare("SELECT id FROM hotel_room_categories WHERE hotel_id=? AND status='active'");
    $rs->execute([$hotel_id]);
    $roomIds = array_column($rs->fetchAll(), 'id');
    if (empty($roomIds)) hl_err('No active rooms for this hotel.');
} else {
    $rid = i($room_id_raw);
    if ($rid <= 0) hl_err('Invalid room_id.');
    $roomIds = [$rid];
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO room_prices (hotel_id,room_category_id,meal_plan_id,base_price,rate_date,date_wise_price) VALUES (?,?,?,0,?,?) ON DUPLICATE KEY UPDATE date_wise_price=VALUES(date_wise_price),updated_at=NOW()");
    $cnt = 0;
    $cur = new DateTime($from_date);
    $end = new DateTime($to_date);
    while ($cur <= $end) {
        $dow = (int)$cur->format('w'); // 0=Sun,6=Sat
        if (empty($days) || in_array($dow, $days, true)) {
            $ds = $cur->format('Y-m-d');
            foreach ($roomIds as $rid) {
                $stmt->execute([$hotel_id, $rid, $plan_id, $ds, $price]);
                $cnt++;
            }
        }
        $cur->modify('+1 day');
    }
    $pdo->commit();
    hl_ok(['count' => $cnt], "Bulk rates applied: $cnt record(s) updated.");
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    hl_err('Database error occurred. Please try again.', 500);
}
