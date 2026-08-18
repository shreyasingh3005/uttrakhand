<?php
/** ajax/get_rates.php — Get room rates for calendar view */
require_once __DIR__ . '/helpers.php';
hl_auth();
$pdo = hl_pdo();

$room_id   = i($_GET['room_id']   ?? 0);
$meal_plan = s($_GET['meal_plan'] ?? '');
$year      = i($_GET['year']      ?? date('Y'));
$month     = i($_GET['month']     ?? date('m'));

if ($room_id <= 0) hl_err('room_id is required.');
if ($meal_plan !== '' && !in_array($meal_plan, MEAL_CODES)) hl_err('Invalid meal_plan.');
if ($year < 2000 || $year > 2100) hl_err('Invalid year.');
if ($month < 1   || $month > 12)  hl_err('Invalid month.');

$chk = $pdo->prepare('SELECT id,name FROM hotel_room_categories WHERE id=?');
$chk->execute([$room_id]);
$room = $chk->fetch();
if (!$room) hl_err('Room not found.', 404);

try {
    $from   = sprintf('%04d-%02d-01', $year, $month);
    $to     = (new DateTime($from))->modify('last day of this month')->format('Y-m-d');

    // Get date-specific rates from room_prices
    $params = [$room_id, $from, $to];
    $mpSql  = '';
    if ($meal_plan !== '') {
        $plan_id = get_plan_id($pdo, $meal_plan);
        if ($plan_id) { $mpSql = 'AND meal_plan_id=?'; $params[] = $plan_id; }
    }

    $stmt = $pdo->prepare("SELECT rp.rate_date AS date, mp.code AS meal_plan_code, COALESCE(rp.date_wise_price, rp.base_price) AS price
        FROM room_prices rp
        JOIN meal_plans mp ON mp.id=rp.meal_plan_id
        WHERE rp.room_category_id=? AND rp.rate_date IS NOT NULL AND rp.rate_date BETWEEN ? AND ? $mpSql ORDER BY rp.rate_date,mp.code");
    $stmt->execute($params);
    $rates = $stmt->fetchAll();
    foreach ($rates as &$r) $r['price'] = (float)$r['price'];
    unset($r);

    // Base prices
    $bStmt = $pdo->prepare("SELECT mp.code, rp.base_price FROM room_prices rp JOIN meal_plans mp ON mp.id=rp.meal_plan_id WHERE rp.room_category_id=? AND rp.rate_date IS NULL");
    $bStmt->execute([$room_id]);
    $basePrices = [];
    foreach ($bStmt->fetchAll() as $b) $basePrices[$b['code']] = (float)$b['base_price'];

    hl_ok(['data' => $rates, 'count' => count($rates), 'room' => $room, 'base_prices' => $basePrices, 'year' => $year, 'month' => $month]);
} catch (PDOException $e) {
    hl_err('Database error occurred. Please try again.', 500);
}
