<?php
require_once __DIR__ . '/helpers.php';
hl_require_admin(); $d=hl_body(); $pdo=hl_pdo(); $room_id=i($d['room_id']??0);
if($room_id<=0)hl_err('Room ID required.',422);
try {
 $pdo->beginTransaction();
 $stmt=$pdo->prepare('SELECT id FROM hotel_room_categories WHERE id=? FOR UPDATE');$stmt->execute([$room_id]);
 if(!$stmt->fetchColumn())throw new InvalidArgumentException('Room not found.');
 $stmt=$pdo->prepare("SELECT COUNT(*) FROM booking_rooms br JOIN hotel_bookings hb ON hb.id=br.booking_id WHERE br.room_category_id=? AND hb.booking_status NOT IN ('cancelled','checked_out')");$stmt->execute([$room_id]);
 if($stmt->fetchColumn()>0)throw new DomainException('Cannot remove a room with active bookings.');
 $pdo->prepare("UPDATE hotel_room_categories SET status='inactive' WHERE id=?")->execute([$room_id]);
 $pdo->commit();hl_ok(['room_id'=>$room_id],'Room category archived. Booking history preserved.');
} catch(InvalidArgumentException|DomainException $e) {if($pdo->inTransaction())$pdo->rollBack();hl_err($e->getMessage(),409);}
catch(Throwable $e) {if($pdo->inTransaction())$pdo->rollBack();error_log('Archive room: '.$e->getMessage());hl_err('Unable to archive room.',500);}
