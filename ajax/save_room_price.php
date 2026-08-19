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
    $findRoom = $pdo->prepare("SELECT hotel_id FROM hotel_room_categories WHERE id=? AND status='active' LIMIT 1");
    $findPlan = $pdo->prepare("SELECT id FROM meal_plans WHERE code=? LIMIT 1");
    $findRate = $pdo->prepare("SELECT id FROM room_prices WHERE room_category_id=? AND meal_plan_id=? AND rate_date=? ORDER BY id LIMIT 1");
    $updateRate = $pdo->prepare("UPDATE room_prices SET hotel_id=?, base_price=0, date_wise_price=?, updated_at=NOW() WHERE id=?");
    $insertRate = $pdo->prepare("INSERT INTO room_prices (hotel_id,room_category_id,meal_plan_id,base_price,rate_date,date_wise_price) VALUES (?,?,?,0,?,?)");
    $cnt = 0;
    $invalid = [];
    foreach ($rates as $r) {
        $room_id   = i($r['room_id']   ?? 0);
        $meal_code = s($r['meal_plan'] ?? '');
        $date      = s($r['date']      ?? '');
        $price     = f($r['price']     ?? 0);
        $dateObject = DateTime::createFromFormat('!Y-m-d', $date);
        if ($room_id <= 0 || !in_array($meal_code, MEAL_CODES, true) || !$dateObject || $dateObject->format('Y-m-d') !== $date || $price < 0) {
            $invalid[] = ['room_id' => $room_id, 'meal_plan' => $meal_code, 'date' => $date];
            continue;
        }

        $findRoom->execute([$room_id]);
        $hRow = $findRoom->fetch(PDO::FETCH_ASSOC);
        $plan_id = get_plan_id($pdo, $meal_code);
        if (!$hRow || !$plan_id) {
            $invalid[] = ['room_id' => $room_id, 'meal_plan' => $meal_code, 'date' => $date];
            continue;
        }

        $findRate->execute([$room_id, $plan_id, $date]);
        $rateId = $findRate->fetchColumn();
        if ($rateId) {
            $updateRate->execute([(int)$hRow['hotel_id'], $price, (int)$rateId]);
        } else {
            $insertRate->execute([(int)$hRow['hotel_id'], $room_id, $plan_id, $date, $price]);
        }
        $cnt++;
    }
    if ($cnt === 0) {
        throw new InvalidArgumentException('No valid rate records were received.');
    }
    $pdo->commit();
    hl_ok(['count' => $cnt, 'skipped' => count($invalid)], "$cnt rate(s) saved successfully.");
} catch (InvalidArgumentException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    hl_err($e->getMessage(), 422);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('save_room_price.php failed: ' . $e->getMessage());
    hl_err('Database error occurred. Please try again.', 500);
}
