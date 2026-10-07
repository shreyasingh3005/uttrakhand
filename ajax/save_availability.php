<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../includes/booking_service.php';
hl_require_admin_or_manager();
$d=hl_body(); $pdo=hl_pdo(); $updates=$d['updates']??[];
if (!is_array($updates) || !$updates || count($updates)>2000) hl_err('Provide 1–2000 availability updates.',422);
usort($updates,static fn($a,$b)=>[(int)($a['room_id']??0),$a['date']??$a['availability_date']??''] <=> [(int)($b['room_id']??0),$b['date']??$b['availability_date']??'']);
try {
    $pdo->beginTransaction();
    $roomStmt=$pdo->prepare("SELECT * FROM hotel_room_categories WHERE id=? AND status='active' FOR UPDATE");
    $calendar=$pdo->prepare('SELECT * FROM room_availability WHERE room_category_id=? AND availability_date=? FOR UPDATE');
    $save=$pdo->prepare('INSERT INTO room_availability (hotel_id,room_category_id,availability_date,total_rooms,available_rooms,booked_rooms,blocked_rooms) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE total_rooms=VALUES(total_rooms),available_rooms=VALUES(available_rooms),blocked_rooms=VALUES(blocked_rooms),updated_at=NOW()');
    foreach($updates as $update) {
        $rid=booking_integer($update,'room_id',0,1,PHP_INT_MAX);
        $date=(string)($update['date']??$update['availability_date']??'');
        $dt=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if(!$dt || $dt->format('Y-m-d')!==$date) throw new InvalidArgumentException('Invalid availability date.');
        $roomStmt->execute([$rid]);$room=$roomStmt->fetch();
        if(!$room || (!empty($update['hotel_id']) && (int)$update['hotel_id']!==(int)$room['hotel_id'])) throw new InvalidArgumentException('Room does not belong to the selected hotel.');
        $calendar->execute([$rid,$date]);$existing=$calendar->fetch();
        $booked=(int)($existing['booked_rooms']??0);
        $total=(int)$room['total_rooms'];
        $avail=booking_integer($update,'available_rooms',0,0,$total);
        // The UI edits available inventory; the remaining unbooked capacity is blocked.
        if($avail+$booked>$total) throw new DomainException('Availability cannot overwrite rooms already reserved. Refresh the calendar.');
        $blocked=$total-$avail-$booked;
        $save->execute([$room['hotel_id'],$rid,$date,$total,$avail,$booked,$blocked]);
    }
    $pdo->commit(); hl_ok(['count'=>count($updates)],'Availability saved.');
} catch(InvalidArgumentException|DomainException $e) {
    if($pdo->inTransaction())$pdo->rollBack(); hl_err($e->getMessage(),422);
} catch(Throwable $e) {
    if($pdo->inTransaction())$pdo->rollBack(); error_log('Availability: '.$e->getMessage()); hl_err('Unable to save availability.',500);
}
