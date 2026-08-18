<?php
/** ajax/delete_hotel.php — Delete hotel and all related data */
require_once __DIR__ . '/helpers.php';
hl_require_admin();
$pdo = hl_pdo();
$d   = hl_body();

$hotel_id = i($d['hotel_id'] ?? 0);
if ($hotel_id <= 0) hl_err('Valid hotel_id required.');

$chk = $pdo->prepare('SELECT id,name FROM hotels WHERE id=?');
$chk->execute([$hotel_id]);
$hotel = $chk->fetch();
if (!$hotel) hl_err('Hotel not found.', 404);

// Check active bookings
$bChk = $pdo->prepare("SELECT COUNT(*) FROM hotel_bookings WHERE hotel_id=? AND booking_status NOT IN ('cancelled','checked_out')");
$bChk->execute([$hotel_id]);
if ((int)$bChk->fetchColumn() > 0) hl_err('Cannot delete hotel with active bookings. Cancel all bookings first.');

try {
    $pdo->beginTransaction();
    // Get room IDs for this hotel
    $rids = $pdo->prepare("SELECT id FROM hotel_room_categories WHERE hotel_id=?");
    $rids->execute([$hotel_id]);
    $roomIds = array_column($rids->fetchAll(), 'id');

    if (!empty($roomIds)) {
        $in = implode(',', array_fill(0, count($roomIds), '?'));
        // Delete booking_rooms referencing these rooms
        $pdo->prepare("DELETE FROM booking_rooms WHERE room_category_id IN ($in)")->execute($roomIds);
        // room_prices, room_availability cascade via FK ON DELETE CASCADE, but be explicit
        $pdo->prepare("DELETE FROM room_prices WHERE room_category_id IN ($in)")->execute($roomIds);
        $pdo->prepare("DELETE FROM room_availability WHERE room_category_id IN ($in)")->execute($roomIds);
    }
    // Delete hotel_bookings for this hotel
    $pdo->prepare("DELETE FROM hotel_bookings WHERE hotel_id=?")->execute([$hotel_id]);
    // Delete hotel (cascades to hotel_room_categories, room_prices, room_availability via FK)
    $pdo->prepare("UPDATE hotels SET status='inactive' WHERE id=?")->execute([$hotel_id]);
    $pdo->commit();
    hl_ok(['hotel_id' => $hotel_id], "Hotel \"{$hotel['name']}\" deleted successfully.");
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    hl_err('Database error occurred. Please try again.', 500);
}
