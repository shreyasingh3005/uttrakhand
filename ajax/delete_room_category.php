<?php
/** ajax/delete_room_category.php — Delete room category and all related data */
require_once __DIR__ . '/helpers.php';
hl_require_admin();
$pdo = hl_pdo();
$d   = hl_body();

$room_id = i($d['room_id'] ?? 0);
if ($room_id <= 0) hl_err('room_id required.');

$rChk = $pdo->prepare('SELECT id,name FROM hotel_room_categories WHERE id=?');
$rChk->execute([$room_id]);
$room = $rChk->fetch();
if (!$room) hl_err('Room not found.', 404);

// Check active bookings for this room
$bChk = $pdo->prepare("SELECT COUNT(*) FROM booking_rooms br JOIN hotel_bookings hb ON hb.id=br.booking_id WHERE br.room_category_id=? AND hb.booking_status NOT IN ('cancelled','checked_out')");
$bChk->execute([$room_id]);
if ((int)$bChk->fetchColumn() > 0) hl_err('Cannot delete room with active bookings. Cancel bookings first.');

try {
    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM room_prices WHERE room_category_id=?")->execute([$room_id]);
    $pdo->prepare("DELETE FROM room_availability WHERE room_category_id=?")->execute([$room_id]);
    $pdo->prepare("DELETE FROM booking_rooms WHERE room_category_id=?")->execute([$room_id]);
    $pdo->prepare("UPDATE hotel_room_categories SET status='inactive',updated_at=NOW() WHERE id=?")->execute([$room_id]);
    $pdo->commit();
    hl_ok(['room_id' => $room_id], "Room \"{$room['name']}\" deleted successfully.");
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    hl_err('Database error occurred. Please try again.', 500);
}
