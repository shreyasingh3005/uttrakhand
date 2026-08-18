<?php
/** ajax/delete_room.php — Delete room category and related data */
require_once __DIR__ . '/helpers.php';
hl_require_admin();
$pdo = hl_pdo();
$d   = hl_body();

$room_id = i($d['room_id'] ?? 0);
if ($room_id <= 0) hl_err('Valid room_id is required.');

$chk = $pdo->prepare('SELECT id,name FROM hotel_room_categories WHERE id=?');
$chk->execute([$room_id]);
$room = $chk->fetch();
if (!$room) hl_err('Room not found.', 404);

try {
    $pdo->beginTransaction();
    // Delete related data
    $pdo->prepare("DELETE FROM room_prices WHERE room_category_id=?")->execute([$room_id]);
    $pdo->prepare("DELETE FROM room_availability WHERE room_category_id=?")->execute([$room_id]);
    $pdo->prepare("DELETE FROM booking_rooms WHERE room_category_id=?")->execute([$room_id]);
    $pdo->prepare("DELETE FROM hotel_room_categories WHERE id=?")->execute([$room_id]);
    $pdo->commit();
    hl_ok(['room_id' => $room_id], 'Room category deleted successfully.');
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    hl_err('Database error occurred. Please try again.', 500);
}
