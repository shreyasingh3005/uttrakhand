<?php
require_once __DIR__ . '/../includes/auth_session.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/crm_booking.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Please log in.']); exit; }
require_role_any(['admin','employee']);
try {
    crm_booking_schema($conn);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action=$_POST['action']??'';
        if ($action==='create') {
            $result=crm_booking_create($conn,$_POST);
            echo json_encode(['success'=>true,'message'=>'Booking created: '.$result['booking_code'],'data'=>$result]);
        } elseif ($action==='update') {
            crm_booking_update($conn,(int)($_POST['booking_id']??0),$_POST);
            echo json_encode(['success'=>true,'message'=>'Booking updated.']);
        } else throw new InvalidArgumentException('Unknown booking action.');
    } else {
        $id=(int)($_GET['booking_id']??0);
        $stmt=$conn->prepare('SELECT b.*,w.stage,w.assigned_user_id,w.query_id,w.canonical_booking_id FROM bookings_details b LEFT JOIN crm_booking_workflow w ON w.booking_id=b.id WHERE b.id=?');
        $stmt->execute([$id]); $booking=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$booking || !crm_booking_access($booking)) { http_response_code(404); echo json_encode(['success'=>false,'message'=>'Booking not found.']); exit; }
        $history=$conn->prepare('SELECT action,performed_by_username,action_at,details FROM activity_logs WHERE booking_id=? ORDER BY id DESC LIMIT 100');
        $history->execute([$id]);
        echo json_encode(['success'=>true,'data'=>['booking'=>$booking,'history'=>$history->fetchAll()]]);
    }
} catch (InvalidArgumentException | DomainException $e) {
    http_response_code(422); echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
} catch (Throwable $e) {
    error_log('CRM booking API: '.$e->getMessage());
    http_response_code(500); echo json_encode(['success'=>false,'message'=>'Unable to process this booking. Please try again.']);
}
