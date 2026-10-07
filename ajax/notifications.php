<?php
require_once __DIR__ . '/../includes/auth_session.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/crm_booking.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success'=>false]); exit; }
require_role_any(['admin','employee']);
try {
    crm_booking_schema($conn);
    $where=$_SESSION['role']==='admin'?'1=1':'(b.created_by=? OR w.assigned_user_id=?)';
    $params=$_SESSION['role']==='admin'?[]:[$_SESSION['username'],(int)$_SESSION['user_id']];
    $stmt=$conn->prepare("SELECT a.action,a.action_at,b.booking_code,b.id AS booking_id,COALESCE(w.stage,b.booking_status) AS stage FROM activity_logs a JOIN bookings_details b ON b.id=a.booking_id LEFT JOIN crm_booking_workflow w ON w.booking_id=b.id WHERE $where ORDER BY a.id DESC LIMIT 15");
    $stmt->execute($params);
    echo json_encode(['success'=>true,'items'=>$stmt->fetchAll()]);
} catch(Throwable $e) { error_log('Notifications: '.$e->getMessage());http_response_code(500);echo json_encode(['success'=>false,'message'=>'Unable to load booking alerts.']); }
