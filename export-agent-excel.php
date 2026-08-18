<?php
require_once __DIR__ . '/includes/auth_session.php';
require_once __DIR__ . '/includes/db_connect.php';
require_role('admin');

$agentId = (int) ($_GET['agent_id'] ?? 0);
if ($agentId <= 0) {
    redirect('/agents-details.php');
}

$agentStmt = $conn->prepare('SELECT * FROM agents_details WHERE id = :id LIMIT 1');
$agentStmt->execute([':id' => $agentId]);
$agent = $agentStmt->fetch(PDO::FETCH_ASSOC);

if (!$agent) {
    redirect('/agents-details.php?error=1');
}

$bookingsStmt = $conn->prepare(
    'SELECT b.booking_code, b.client_name, b.client_phone, b.client_email,
            COALESCE(NULLIF(b.hotel_name_snapshot, ""), h.hotel_name) AS hotel_name, b.check_in, b.check_out, b.booking_date,
            b.amount, b.paid_amount, b.due_amount,
            b.payment_status, b.booking_status, b.status AS legacy_status,
            b.booking_source, b.guest_count, b.room_count, b.special_request,
            b.created_by, b.created_at
     FROM bookings_details b
     LEFT JOIN hotel_listings_details h ON h.id = b.hotel_listing_id
     WHERE b.agent_id = :agent_id
     ORDER BY b.created_at DESC, b.id DESC'
);
$bookingsStmt->execute([':agent_id' => $agentId]);
$bookings = $bookingsStmt->fetchAll(PDO::FETCH_ASSOC);

$totalBookings = count($bookings);
$totalAmount = 0;
$totalPaid = 0;
$totalDue = 0;
foreach ($bookings as $row) {
    $totalAmount += (float) ($row['amount'] ?? 0);
    $totalPaid += (float) ($row['paid_amount'] ?? 0);
    $totalDue += (float) ($row['due_amount'] ?? 0);
}

$slugBase = preg_replace('/[^a-zA-Z0-9_-]+/', '-', strtolower((string) ($agent['name'] ?? 'agent')));
if ($slugBase === '' || $slugBase === '-') {
    $slugBase = 'agent';
}
$fileName = $slugBase . '-full-data-' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $fileName . '"');

$output = fopen('php://output', 'w');
fwrite($output, "\xEF\xBB\xBF");

fputcsv($output, ['Agent Profile']);
fputcsv($output, ['Name', (string) ($agent['name'] ?? '')]);
fputcsv($output, ['Email', (string) ($agent['email'] ?? '')]);
fputcsv($output, ['Phone', (string) ($agent['phone'] ?? '')]);
fputcsv($output, ['Location', (string) ($agent['location'] ?? '')]);
fputcsv($output, ['Status', (string) ($agent['status'] ?? '')]);
fputcsv($output, ['Created By', (string) ($agent['created_by'] ?? '')]);
fputcsv($output, ['Created At', (string) ($agent['created_at'] ?? '')]);
fputcsv($output, ['Total Deals (Profile)', (int) ($agent['total_deals'] ?? 0)]);
fputcsv($output, ['Total Revenue (Profile)', (float) ($agent['total_revenue'] ?? 0)]);
fputcsv($output, []);

fputcsv($output, ['Bookings Summary']);
fputcsv($output, ['Total Bookings', $totalBookings]);
fputcsv($output, ['Total Amount', $totalAmount]);
fputcsv($output, ['Total Paid', $totalPaid]);
fputcsv($output, ['Total Due', $totalDue]);
fputcsv($output, []);

fputcsv($output, ['Bookings']);
fputcsv($output, [
    'Booking Code', 'Client Name', 'Client Phone', 'Client Email', 'Hotel',
    'Check In', 'Check Out', 'Booking Date',
    'Amount', 'Paid', 'Due', 'Payment Status', 'Status',
    'Source', 'Guests', 'Rooms', 'Special Request', 'Created By', 'Created At'
]);

if ($totalBookings === 0) {
    fputcsv($output, ['No booking records found']);
} else {
    foreach ($bookings as $row) {
        $statusLabel = (string) ($row['booking_status'] ?? '');
        if ($statusLabel === '') {
            $legacy = (string) ($row['legacy_status'] ?? '');
            if ($legacy === 'Cancelled') {
                $statusLabel = 'Cancelled';
            } elseif ($legacy === 'Confirmed') {
                $statusLabel = 'Completed';
            } elseif ($legacy === 'Pending Payment') {
                $statusLabel = 'Pending';
            } else {
                $statusLabel = 'Pending';
            }
        }

        fputcsv($output, [
            (string) ($row['booking_code'] ?? ''),
            (string) ($row['client_name'] ?? ''),
            (string) ($row['client_phone'] ?? ''),
            (string) ($row['client_email'] ?? ''),
            (string) ($row['hotel_name'] ?? ''),
            (string) ($row['check_in'] ?? ''),
            (string) ($row['check_out'] ?? ''),
            (string) ($row['booking_date'] ?? ''),
            (float) ($row['amount'] ?? 0),
            (float) ($row['paid_amount'] ?? 0),
            (float) ($row['due_amount'] ?? 0),
            (string) ($row['payment_status'] ?? 'Pending'),
            $statusLabel,
            (string) ($row['booking_source'] ?? ''),
            (int) ($row['guest_count'] ?? 1),
            (int) ($row['room_count'] ?? 1),
            (string) ($row['special_request'] ?? ''),
            (string) ($row['created_by'] ?? ''),
            (string) ($row['created_at'] ?? ''),
        ]);
    }
}

fclose($output);
exit;
