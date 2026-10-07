<?php
require_once __DIR__ . '/booking_service.php';

function crm_booking_schema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS crm_booking_workflow (
        booking_id INT NOT NULL PRIMARY KEY,
        stage VARCHAR(20) NOT NULL DEFAULT 'Pending',
        assigned_user_id INT NULL,
        canonical_hotel_id INT UNSIGNED NULL,
        canonical_booking_id INT UNSIGNED NULL,
        query_id INT NULL,
        request_key VARCHAR(64) NULL UNIQUE,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_assignment (assigned_user_id, stage), INDEX idx_reservation (canonical_booking_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS crm_hotel_links (
        hotel_id INT UNSIGNED PRIMARY KEY, legacy_hotel_id INT NOT NULL UNIQUE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function crm_booking_log(PDO $pdo, int $id, string $action, array $details): void {
    $pdo->prepare('INSERT INTO activity_logs (booking_id,action,performed_by_user_id,performed_by_username,performed_by_role,details) VALUES (?,?,?,?,?,?)')
        ->execute([$id,$action,$_SESSION['user_id'],$_SESSION['username'],$_SESSION['role'],json_encode($details, JSON_UNESCAPED_UNICODE)]);
}

function crm_booking_access(array $booking): bool {
    return ($_SESSION['role'] ?? '') === 'admin' || $booking['created_by'] === ($_SESSION['username'] ?? '') || (int)($booking['assigned_user_id'] ?? 0) === (int)($_SESSION['user_id'] ?? -1);
}

function crm_booking_create(PDO $pdo, array $data): array {
    $name = trim((string)($data['clientName'] ?? ''));
    $phone = trim((string)($data['clientPhone'] ?? ''));
    $email = trim((string)($data['clientEmail'] ?? ''));
    if ($name === '' || mb_strlen($name)>120 || !preg_match('/^[+0-9 ()-]{7,20}$/', $phone) || ($email !== '' && !filter_var($email,FILTER_VALIDATE_EMAIL))) throw new InvalidArgumentException('Enter a customer name, valid phone and email.');
    $from = (string)($data['checkIn'] ?? ''); $to = (string)($data['checkOut'] ?? '');
    booking_dates($from,$to);
    $amount = filter_var($data['amount'] ?? 0, FILTER_VALIDATE_FLOAT);
    $paid = filter_var($data['paidAmount'] ?? 0, FILTER_VALIDATE_FLOAT);
    if ($amount === false || !is_finite($amount) || $amount<=0 || $amount>9999999999 || $paid === false || !is_finite($paid) || $paid<0 || (empty($data['room_category_id']) && $paid>$amount)) throw new InvalidArgumentException('Enter a positive total and a payment between zero and the total.');
    $rooms = booking_integer($data,'roomCount',1,1);
    $guests = booking_integer($data,'guestCount',1,1);
    $hotelId = booking_integer($data,'hotelId',0,1,PHP_INT_MAX);
    $agentId = booking_integer($data,'agentId',0,1,PHP_INT_MAX);
    $assigned = ($_SESSION['role'] ?? '') === 'admin' ? (int)($data['assigned_user_id'] ?? 0) : (int)$_SESSION['user_id'];
    $key = trim((string)($data['request_key'] ?? ''));
    if ($key !== '' && !preg_match('/^[a-zA-Z0-9-]{16,64}$/',$key)) throw new InvalidArgumentException('Invalid request identifier.');
    $pdo->beginTransaction();
    try {
        if ($key !== '') {
            $existing=$pdo->prepare('SELECT b.id,b.booking_code,b.created_by,w.assigned_user_id FROM crm_booking_workflow w JOIN bookings_details b ON b.id=w.booking_id WHERE w.request_key=?');
            $existing->execute([$key]);
            if ($row=$existing->fetch(PDO::FETCH_ASSOC)) {
                if (!crm_booking_access($row)) throw new DomainException('Request identifier already used.');
                $pdo->commit(); return ['booking_id'=>(int)$row['id'],'booking_code'=>$row['booking_code']];
            }
        }
        $stmt=$pdo->prepare("SELECT * FROM hotels WHERE id=? AND status='active' FOR UPDATE");
        $stmt->execute([$hotelId]); $hotel=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$hotel) throw new InvalidArgumentException('Selected hotel is unavailable.');
        $stmt=$pdo->prepare("SELECT id FROM agents_details WHERE id=? AND status='Active'");
        $stmt->execute([$agentId]); if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Select an active agent.');
        $employeeId=null;
        if ($assigned) {
            $stmt=$pdo->prepare("SELECT u.id,e.id AS employee_id FROM users u LEFT JOIN employees_details e ON e.email=u.email WHERE u.id=? AND u.role='employee'");
            $stmt->execute([$assigned]); $user=$stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) throw new InvalidArgumentException('Select a valid employee.');
            $employeeId=$user['employee_id'];
        }
        $queryId=(int)($data['query_id'] ?? 0);
        if ($queryId) {
            $stmt=$pdo->prepare('SELECT created_by_user_id,agent_id FROM booking_query_history WHERE id=?');
            $stmt->execute([$queryId]); $query=$stmt->fetch(PDO::FETCH_ASSOC);
            if (!$query || ((int)$query['agent_id'] !== $agentId) || (($_SESSION['role']??'')!=='admin' && (int)$query['created_by_user_id']!==(int)$_SESSION['user_id'])) throw new InvalidArgumentException('Enquiry is unavailable or belongs to another agent.');
        }
        // Keep the existing legacy FK valid through an explicit mapping, never by assuming equal IDs.
        $stmt=$pdo->prepare('SELECT legacy_hotel_id FROM crm_hotel_links WHERE hotel_id=?'); $stmt->execute([$hotelId]); $legacyId=(int)$stmt->fetchColumn();
        if (!$legacyId) {
            $pdo->prepare("INSERT INTO hotel_listings_details (hotel_name,category,location,room_type,status) VALUES (?,?,?,'Mixed','Active')")
                ->execute([$hotel['name'],$hotel['property_category'] ?? '',$hotel['city']]);
            $legacyId=(int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO crm_hotel_links (hotel_id,legacy_hotel_id) VALUES (?,?)')->execute([$hotelId,$legacyId]);
        }
        $reservation=null;
        if (!empty($data['room_category_id'])) {
            $reservation=booking_create($pdo,['hotel_id'=>$hotelId,'room_category_id'=>$data['room_category_id'],'guest_name'=>$name,'guest_phone'=>$phone,'guest_email'=>$email,'checkin_date'=>$from,'checkout_date'=>$to,'rooms_count'=>$rooms,'adults'=>$guests,'meal_plan'=>$data['meal_plan']??'EP','special_requests'=>$data['specialRequest']??'']);
            $amount=$reservation['total_amount'];
            if ($paid>$amount) throw new InvalidArgumentException('Payment exceeds the calculated room total.');
        }
        $code='CRM-'.date('ymd').'-'.strtoupper(bin2hex(random_bytes(5)));
        $payment=$paid<=0?'Pending':($paid>=$amount?'Paid':'Partial');
        $pdo->prepare("INSERT INTO bookings_details (booking_code,client_name,client_phone,client_email,hotel_listing_id,agent_id,employee_id,check_in,check_out,amount,paid_amount,due_amount,payment_status,booking_status,status,booking_date,created_by,guest_count,room_count,special_request,booking_source,hotel_name_snapshot,hotel_location_snapshot,room_type_snapshot) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'Pending','Pending Payment',?,?,?,?,?,?,?,?,?)")
            ->execute([$code,$name,$phone,$email,$legacyId,$agentId,$employeeId,$from,$to,$amount,$paid,$amount-$paid,$payment,date('Y-m-d'),$_SESSION['username'],$guests,$rooms,$data['specialRequest']??'',$data['bookingSource']??'Direct',$hotel['name'],$hotel['city'],$data['roomType']??'']);
        $id=(int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO crm_booking_workflow (booking_id,stage,assigned_user_id,canonical_hotel_id,canonical_booking_id,query_id,request_key) VALUES (?,\'Pending\',?,?,?,?,?)')
            ->execute([$id,$assigned?:null,$hotelId,$reservation['booking_id']??null,$queryId?:null,$key?:null]);
        crm_booking_log($pdo,$id,'Booking created',['total'=>$amount,'paid'=>$paid,'assigned_user_id'=>$assigned,'query_id'=>$queryId,'reserved_rooms'=>$reservation!==null]);
        $pdo->commit(); return ['booking_id'=>$id,'booking_code'=>$code,'total_amount'=>$amount];
    } catch(Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}

function crm_booking_update(PDO $pdo, int $id, array $data): void {
    $ownsTransaction=!$pdo->inTransaction();
    if($ownsTransaction)$pdo->beginTransaction();
    try {
        $stmt=$pdo->prepare('SELECT b.*,w.stage,w.assigned_user_id,w.canonical_booking_id FROM bookings_details b LEFT JOIN crm_booking_workflow w ON w.booking_id=b.id WHERE b.id=? FOR UPDATE');
        $stmt->execute([$id]); $booking=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$booking || !crm_booking_access($booking)) throw new DomainException('Booking not found or access denied.');
        $current=$booking['stage']?:$booking['booking_status'];
        $stage=$data['stage']??$current;
        $transitions=['Pending'=>['Assigned','Processing','Confirmed','Completed','Cancelled'],'Assigned'=>['Processing','Confirmed','Cancelled'],'Processing'=>['Confirmed','Cancelled'],'Confirmed'=>['Completed','Cancelled'],'Completed'=>[],'Cancelled'=>[]];
        if ($stage!==$current && !in_array($stage,$transitions[$current]??[],true)) throw new DomainException('Invalid booking status transition.');
        $assigned=(int)($booking['assigned_user_id']??0);
        if (array_key_exists('assigned_user_id',$data)) {
            if ($_SESSION['role']!=='admin') throw new DomainException('Only an administrator can assign bookings.');
            $assigned=(int)$data['assigned_user_id'];
            $stmt=$pdo->prepare("SELECT id FROM users WHERE id=? AND role='employee'"); $stmt->execute([$assigned]);
            if ($assigned && !$stmt->fetchColumn()) throw new InvalidArgumentException('Employee not found.');
        }
        if ($stage==='Assigned' && !$assigned) throw new InvalidArgumentException('Select an employee before assigning the booking.');
        $paid=(float)$booking['paid_amount'];
        if (isset($data['paid_amount'])) {
            if (!is_numeric($data['paid_amount'])) throw new InvalidArgumentException('Invalid payment amount.');
            $paid=(float)$data['paid_amount'] + (!empty($data['increment_payment']) ? $paid : 0);
        }
        if (!is_finite($paid) || $paid<0 || $paid>(float)$booking['amount']) throw new InvalidArgumentException('Payment must be between zero and the booking total.');
        if ($_SESSION['role'] !== 'admin' && $paid < (float)$booking['paid_amount']) throw new DomainException('Only an administrator can correct a recorded payment.');
        if ($current==='Cancelled' && $paid!=(float)$booking['paid_amount']) throw new DomainException('Cancelled booking payments cannot be changed here.');
        $legacy=in_array($stage,['Completed','Cancelled'],true)?$stage:'Pending';
        $payment=$paid<=0?'Pending':($paid>=(float)$booking['amount']?'Paid':'Partial');
        if ($stage==='Cancelled') $payment='Cancelled';
        $note=(string)($data['payment_note']??$booking['payment_note']??'');
        if (!empty($booking['canonical_booking_id'])) {
            $reserveData=['payment_status'=>strtolower($payment==='Cancelled'?'Pending':$payment)];
            if ($stage==='Cancelled') $reserveData['status']='cancelled';
            if ($stage==='Completed' && $current!=='Completed') {
                booking_update($pdo,(int)$booking['canonical_booking_id'],['status'=>'checked_in']);
                $reserveData['status']='checked_out';
            }
            booking_update($pdo,(int)$booking['canonical_booking_id'],$reserveData);
        }
        $pdo->prepare('UPDATE bookings_details SET paid_amount=?,due_amount=?,payment_status=?,booking_status=?,status=?,payment_note=?,payment_updated_by=?,payment_updated_at=NOW() WHERE id=?')
            ->execute([$paid,$stage==='Cancelled'?0:(float)$booking['amount']-$paid,$payment,$legacy,$stage==='Cancelled'?'Cancelled':($stage==='Completed'?'Confirmed':'Pending Payment'),$note,$_SESSION['username'],$id]);
        $pdo->prepare('INSERT INTO crm_booking_workflow (booking_id,stage,assigned_user_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE stage=VALUES(stage),assigned_user_id=VALUES(assigned_user_id)')->execute([$id,$stage,$assigned?:null]);
        crm_booking_log($pdo,$id,'Booking updated',['from'=>$current,'to'=>$stage,'previous_paid'=>(float)$booking['paid_amount'],'paid'=>$paid,'assigned_user_id'=>$assigned,'note'=>$note]);
        if($ownsTransaction)$pdo->commit();
    } catch(Throwable $e) { if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}

/** Keep Room Manager actions and CRM payment/status/history in one transaction. */
function crm_reservation_update(PDO $pdo, int $reservationId, array $data): array {
    crm_booking_schema($pdo);
    $lookup=$pdo->prepare('SELECT w.booking_id,b.amount,b.paid_amount FROM crm_booking_workflow w JOIN bookings_details b ON b.id=w.booking_id WHERE w.canonical_booking_id=?');
    $lookup->execute([$reservationId]);$link=$lookup->fetch(PDO::FETCH_ASSOC);
    if(!$link)return booking_update($pdo,$reservationId,$data);
    $pdo->beginTransaction();
    try {
        $update=[];
        $status=$data['status']??'';
        $map=['cancelled'=>'Cancelled','checked_out'=>'Completed','confirmed'=>'Confirmed','checked_in'=>'Confirmed'];
        if($status!=='' && !isset($map[$status]))throw new DomainException('Use the CRM workflow to manage linked bookings.');
        if($status!=='')$update['stage']=$map[$status];
        if(!empty($data['payment_status'])) {
            if($data['payment_status']==='paid')$update['paid_amount']=$link['amount'];
            elseif($data['payment_status']==='pending')$update['paid_amount']=0;
            elseif($data['payment_status']==='partial' && isset($data['paid_amount']))$update['paid_amount']=$data['paid_amount'];
            else throw new InvalidArgumentException('Record the actual payment amount in the Booking section.');
        }
        crm_booking_update($pdo,(int)$link['booking_id'],$update);
        $result=booking_update($pdo,$reservationId,$data);
        $pdo->commit();return $result;
    } catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack();throw $e; }
}
