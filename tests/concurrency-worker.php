<?php
if (PHP_SAPI !== 'cli') exit;
ini_set('zend.exception_ignore_args', '1');
require __DIR__ . '/../includes/booking_service.php';
try {
    $pdo=new PDO('mysql:host=127.0.0.1;dbname=crm_validation_20261007;charset=utf8mb4','root','',[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]);
    $input=json_decode($argv[1],true,512,JSON_THROW_ON_ERROR);
    while(microtime(true)<$input['start']) usleep(1000);
    if($input['action']==='create') $result=booking_create($pdo,$input['data']);
    elseif($input['action']==='cancel') $result=booking_update($pdo,$input['id'],['status'=>'cancelled']);
    else {
        $stmt=$pdo->prepare('SELECT available_rooms,booked_rooms,total_rooms FROM room_availability WHERE room_category_id=? AND availability_date>=? AND availability_date<? ORDER BY availability_date');
        $stmt->execute([$input['data']['room_category_id'],$input['data']['checkin_date'],$input['data']['checkout_date']]);
        $result=$stmt->fetchAll();
    }
    echo json_encode(['ok'=>true,'data'=>$result]);
} catch(DomainException $e) {
    echo json_encode(['ok'=>false,'conflict'=>true]);
} catch(Throwable $e) {
    fwrite(STDERR,'Concurrency worker failed: '.$e->getMessage()); exit(1);
}
