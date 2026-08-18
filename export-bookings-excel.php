<?php
require_once __DIR__ . '/includes/auth_session.php';
require_once __DIR__ . '/includes/db_connect.php';

require_login();

$username = $_SESSION['username'] ?? 'user';
$role = $_SESSION['role'] ?? 'employee';

$query = 'SELECT b.booking_code,
                 b.client_name,
                 b.client_phone,
                 b.client_email,
                 h.hotel_name,
                 a.name AS agent_name,
                 b.check_in,
                 b.check_out,
                 b.amount,
                 b.booking_source,
                 b.guest_count,
                 b.room_count,
                 b.special_request,
                 b.booking_status,
                 b.payment_status,
                 b.paid_amount,
                 b.due_amount,
                 b.booking_date,
                 b.created_by,
                 b.created_at
          FROM bookings_details b
          LEFT JOIN hotel_listings_details h ON h.id = b.hotel_listing_id
          LEFT JOIN agents_details a ON a.id = b.agent_id';

$params = [];
if ($role !== 'admin') {
    $query .= ' WHERE b.created_by = :username';
    $params[':username'] = $username;
}

$query .= ' ORDER BY b.created_at DESC, b.id DESC';

$stmt = $conn->prepare($query);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$fileName = 'bookings_report_' . $role . '_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $fileName);
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

// UTF-8 BOM helps Excel render text correctly.
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, ['Report Generated For', $username]);
fputcsv($out, ['User Role', $role]);
fputcsv($out, ['Total Bookings', (string) count($rows)]);
fputcsv($out, ['Generated At', date('Y-m-d H:i:s')]);
fputcsv($out, []);

fputcsv($out, [
    'Booking Code',
    'Client Name',
    'Client Phone',
    'Client Email',
    'Hotel Name',
    'Agent Name',
    'Check In',
    'Check Out',
    'Amount',
    'Booking Source',
    'Guests',
    'Rooms',
    'Special Request',
    'Booking Status',
    'Payment Status',
    'Paid Amount',
    'Due Amount',
    'Booking Date',
    'Created By',
    'Created At'
]);

foreach ($rows as $row) {
    fputcsv($out, [
        $row['booking_code'] ?? '',
        $row['client_name'] ?? '',
        $row['client_phone'] ?? '',
        $row['client_email'] ?? '',
        $row['hotel_name'] ?? '',
        $row['agent_name'] ?? '',
        $row['check_in'] ?? '',
        $row['check_out'] ?? '',
        (string) ($row['amount'] ?? ''),
        $row['booking_source'] ?? '',
        (string) ($row['guest_count'] ?? ''),
        (string) ($row['room_count'] ?? ''),
        $row['special_request'] ?? '',
        $row['booking_status'] ?? '',
        $row['payment_status'] ?? '',
        (string) ($row['paid_amount'] ?? ''),
        (string) ($row['due_amount'] ?? ''),
        $row['booking_date'] ?? '',
        $row['created_by'] ?? '',
        $row['created_at'] ?? ''
    ]);
}

fclose($out);
exit;
