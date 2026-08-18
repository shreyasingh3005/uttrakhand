<?php
/**
 * test_query_flow.php - Quick test of booking query availability
 */
session_start();
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

// Check if hotels exist
$hotelCount = $conn->query("SELECT COUNT(*) FROM hotels WHERE status='active'")->fetchColumn();
echo "Hotels: " . $hotelCount . "\n";

// Check if rooms exist
$roomCount = $conn->query("SELECT COUNT(*) FROM hotel_room_categories WHERE status='active'")->fetchColumn();
echo "Room Categories: " . $roomCount . "\n";

// Check if prices exist
$priceCount = $conn->query("SELECT COUNT(*) FROM room_prices WHERE base_price > 0")->fetchColumn();
echo "Prices with values: " . $priceCount . "\n";

// Check availability on 2026-08-20
$checkIn = '2026-08-20';
$stmt = $conn->prepare("
    SELECT COUNT(*) FROM room_availability
    WHERE availability_date = ? AND available_rooms > 0
");
$stmt->execute([$checkIn]);
$availCount = $stmt->fetchColumn();
echo "Rooms with availability on $checkIn: " . $availCount . "\n";

// Test the exact filter_hotels_for_query logic
echo "\n=== Testing filter logic ===\n";
$filterLocation = 'Mussoorie';
$filterCategory = '';
$filterCheckIn = '2026-08-20';
$filterCheckOut = '2026-08-23';
$filterRooms = 1;

$where = ["LOWER(TRIM(h.status)) = 'active'"];
$params = [];

if ($filterLocation) {
    $locationTokens = array_filter(preg_split('/\s+/', trim($filterLocation)));
    if ($locationTokens) {
        $tokenClauses = [];
        foreach (array_values($locationTokens) as $i => $token) {
            $key = ':loc' . $i;
            $tokenClauses[] = "(LOWER(TRIM(h.city)) LIKE $key OR LOWER(TRIM(h.state)) LIKE $key)";
            $params[$key] = '%' . strtolower($token) . '%';
        }
        $where[] = '(' . implode(' AND ', $tokenClauses) . ')';
    }
}

if ($filterCategory && strtolower($filterCategory) !== 'all categories') {
    $where[] = "LOWER(TRIM(COALESCE(NULLIF(h.property_category, ''), CONCAT(h.star_rating, ' Star')))) = LOWER(TRIM(:category))";
    $params[':category'] = $filterCategory;
}

$sql = 'SELECT h.id, h.name FROM hotels h WHERE ' . implode(' AND ', $where) . ' ORDER BY h.name ASC';
echo "SQL: " . $sql . "\n";
echo "Params: " . json_encode($params) . "\n";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $hotels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Hotels matching filter: " . count($hotels) . "\n";
    foreach ($hotels as $h) {
        echo "  - " . $h['name'] . " (ID: " . $h['id'] . ")\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    $hotels = [];
}

// Check room prices for first hotel
if ($hotels) {
    $hotelId = $hotels[0]['id'];
    echo "\nRoom prices for {$hotels[0]['name']}:\n";
    $stmt = $conn->prepare("
        SELECT DISTINCT rp.base_price, mp.code 
        FROM room_prices rp
        JOIN meal_plans mp ON mp.id = rp.meal_plan_id
        WHERE rp.hotel_id = ? AND rp.base_price > 0
        LIMIT 5
    ");
    $stmt->execute([$hotelId]);
    $prices = $stmt->fetchAll();
    echo "Price count: " . count($prices) . "\n";
    foreach ($prices as $p) {
        echo "  - {$p['code']}: {$p['base_price']}\n";
    }
}

echo "\n=== Done ===\n";
