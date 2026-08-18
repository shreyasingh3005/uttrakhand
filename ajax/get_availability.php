<?php
/** ajax/get_availability.php — Get room availability for date range */
require_once __DIR__ . '/helpers.php';
hl_auth();
$pdo = hl_pdo();

$hotel_id  = i($_GET['hotel_id']  ?? 0);
$from_date = s($_GET['from_date'] ?? date('Y-m-d'));
$to_date   = s($_GET['to_date']   ?? date('Y-m-d', strtotime('+13 days')));

if ($hotel_id <= 0) hl_err('hotel_id is required.');

$dtFrom = DateTime::createFromFormat('Y-m-d', $from_date);
$dtTo   = DateTime::createFromFormat('Y-m-d', $to_date);
if (!$dtFrom || !$dtTo || $from_date > $to_date) hl_err('Invalid date range.');

try {
    // Get rooms
    $rStmt = $pdo->prepare("SELECT id,name,bed_type,total_rooms,available_rooms,booked_rooms,blocked_rooms FROM hotel_room_categories WHERE hotel_id=? AND status='active' ORDER BY id");
    $rStmt->execute([$hotel_id]);
    $rooms = $rStmt->fetchAll();
    if (empty($rooms)) { hl_ok(['data' => [], 'rooms' => [], 'count' => 0]); }

    $roomIds = array_column($rooms, 'id');
    $in      = implode(',', array_fill(0, count($roomIds), '?'));
    $params  = array_merge($roomIds, [$from_date, $to_date]);

    $aStmt = $pdo->prepare("SELECT room_category_id,availability_date,available_rooms,booked_rooms,blocked_rooms
        FROM room_availability WHERE room_category_id IN ($in) AND availability_date>=? AND availability_date<=? ORDER BY availability_date");
    $aStmt->execute($params);
    $records = $aStmt->fetchAll();

    $dataMap = [];
    foreach ($records as $rec) {
        $dataMap[(int)$rec['room_category_id']][$rec['availability_date']] = $rec;
    }
    $roomDefaults = [];
    foreach ($rooms as $r) $roomDefaults[(int)$r['id']] = $r;

    $result = [];
    $cur    = new DateTime($from_date);
    $end    = new DateTime($to_date);
    while ($cur <= $end) {
        $ds = $cur->format('Y-m-d');
        foreach ($roomIds as $rid) {
            $def      = $roomDefaults[$rid];
            $result[] = $dataMap[$rid][$ds] ?? [
                'room_category_id' => $rid,
                'availability_date'=> $ds,
                'total_rooms'      => (int)$def['total_rooms'],
                'available_rooms'  => (int)$def['available_rooms'],
                'booked_rooms'     => (int)$def['booked_rooms'],
                'blocked_rooms'    => (int)$def['blocked_rooms'],
            ];
        }
        $cur->modify('+1 day');
    }
    hl_ok(['data' => $result, 'rooms' => $rooms, 'count' => count($result)]);
} catch (PDOException $e) {
    hl_err('Database error occurred. Please try again.', 500);
}
