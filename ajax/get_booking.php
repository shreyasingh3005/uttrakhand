<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../includes/crm_booking.php';
hl_auth(); $pdo=hl_pdo(); crm_booking_schema($pdo);
$hotel=i($_GET['hotel_id']??0);$id=i($_GET['booking_id']??0);
if(!$hotel&&!$id)hl_err('Hotel or booking ID required.',422);
$params=[$id?:$hotel];$where=$id?'hb.id=?':'hb.hotel_id=?';
if(($_SESSION['role']??'')!=='admin') {
 $where.=' AND EXISTS (SELECT 1 FROM crm_booking_workflow w JOIN bookings_details b ON b.id=w.booking_id WHERE w.canonical_booking_id=hb.id AND (b.created_by=? OR w.assigned_user_id=?))';
 $params[]=$_SESSION['username'];$params[]=(int)$_SESSION['user_id'];
}
try {
 $stmt=$pdo->prepare("SELECT hb.*,h.name AS hotel_name,mp.code AS meal_plan_code FROM hotel_bookings hb JOIN hotels h ON h.id=hb.hotel_id LEFT JOIN meal_plans mp ON mp.id=hb.meal_plan_id WHERE $where ORDER BY hb.created_at DESC LIMIT 200");
 $stmt->execute($params);$bookings=$stmt->fetchAll();
 if($id&&!$bookings)hl_err('Booking not found.',404);
 $roomsByBooking=[];
 if($bookings) {
  $ids=array_column($bookings,'id');$marks=implode(',',array_fill(0,count($ids),'?'));
  $rooms=$pdo->prepare("SELECT br.*,r.name AS room_name,r.bed_type FROM booking_rooms br LEFT JOIN hotel_room_categories r ON r.id=br.room_category_id WHERE br.booking_id IN ($marks) ORDER BY br.id");$rooms->execute($ids);
  foreach($rooms->fetchAll() as $room)$roomsByBooking[$room['booking_id']][]=$room;
 }
 foreach($bookings as &$booking) {
  $booking['rooms']=$roomsByBooking[$booking['id']]??[];
  $first=$booking['rooms'][0]??[];
  foreach(['room_name','room_category_id','bed_type','rooms_count','adults','children','extra_beds','price_per_night'] as $field)$booking[$field]=$first[$field]??null;
  $booking['total_amount']=(float)$booking['total_amount'];
 }
 unset($booking);
 hl_ok($id?['booking'=>$bookings[0]]:['bookings'=>$bookings,'count'=>count($bookings)]);
} catch(Throwable $e) {error_log('Booking lookup: '.$e->getMessage());hl_err('Unable to load bookings.',500);}
