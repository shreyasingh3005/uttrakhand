<?php
/** ajax/save_rates.php — Batch upsert room_prices (date-wise rates) */
require_once __DIR__ . '/helpers.php';
hl_require_admin();
$pdo   = hl_pdo();
$d     = hl_body();
$rates = $d['rates'] ?? [];

if (!is_array($rates) || empty($rates)) hl_err('rates array is required.');
if (count($rates) > 2000) hl_err('Max 2000 records per batch.');

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO room_prices (hotel_id,room_category_id,meal_plan_id,base_price,rate_date,date_wise_price) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE date_wise_price=VALUES(date_wise_price),updated_at=NOW()");
    $cnt = 0;
    foreach ($rates as $r) {
        $rid      = i($r['room_id']   ?? 0);
        $hotel_id = i($r['hotel_id']  ?? 0);
        $mp       = s($r['meal_plan'] ?? '');
        $dt       = s($r['date']      ?? '');
        $pr       = f($r['price']     ?? 0);
        if ($rid <= 0 || !in_array($mp, MEAL_CODES) || !$dt || $pr < 0) continue;
        $plan_id = get_plan_id($pdo, $mp);
        if (!$plan_id) continue;
        if ($hotel_id <= 0) {
            $hq = $pdo->prepare("SELECT hotel_id FROM hotel_room_categories WHERE id=?");
            $hq->execute([$rid]);
            $hr = $hq->fetch();
            if (!$hr) continue;
            $hotel_id = (int)$hr['hotel_id'];
        }
        $stmt->execute([$hotel_id, $rid, $plan_id, $pr, $dt, $pr]);
        $cnt++;
    }
    $pdo->commit();
    hl_ok(['count' => $cnt], "$cnt rates saved successfully.");
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    hl_err('Database error occurred. Please try again.', 500);
}
