<?php
require_once __DIR__ . '/helpers.php';
hl_require_admin();$d=hl_body();$pdo=hl_pdo();$id=i($d['hotel_id']??0);
if($id<=0)hl_err('Hotel ID required.',422);
try {
 $pdo->beginTransaction();
 $stmt=$pdo->prepare('SELECT id FROM hotels WHERE id=? FOR UPDATE');$stmt->execute([$id]);
 if(!$stmt->fetchColumn())throw new InvalidArgumentException('Hotel not found.');
 $stmt=$pdo->prepare("SELECT COUNT(*) FROM hotel_bookings WHERE hotel_id=? AND booking_status NOT IN ('cancelled','checked_out')");$stmt->execute([$id]);
 if($stmt->fetchColumn()>0)throw new DomainException('Cannot archive a hotel with active reservations.');
 $pdo->prepare("UPDATE hotels SET status='inactive' WHERE id=?")->execute([$id]);
 $pdo->commit();hl_ok(['hotel_id'=>$id],'Hotel archived. Booking and rate history preserved.');
} catch(InvalidArgumentException|DomainException $e) {if($pdo->inTransaction())$pdo->rollBack();hl_err($e->getMessage(),409);}
catch(Throwable $e) {if($pdo->inTransaction())$pdo->rollBack();error_log('Archive hotel: '.$e->getMessage());hl_err('Unable to archive hotel.',500);}
