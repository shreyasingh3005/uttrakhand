<?php
require_once __DIR__ . '/includes/auth_session.php';
require_once __DIR__ . '/includes/db_connect.php';
require_role('admin');

$employeeId = (int) ($_GET['employee_id'] ?? 0);
$username = sanitize_input($_GET['username'] ?? '');
$email = sanitize_input($_GET['email'] ?? '');

$employee = null;

if ($employeeId > 0) {
    $empStmt = $conn->prepare(
        'SELECT e.*, u.username AS login_username, u.last_login_at, u.last_logout_at
         FROM employees_details e
         LEFT JOIN users u ON u.email = e.email AND u.role = "employee"
         WHERE e.id = :id
         LIMIT 1'
    );
    $empStmt->execute([':id' => $employeeId]);
    $employee = $empStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if (!$employee && $email !== '') {
    $empEmailStmt = $conn->prepare(
        'SELECT e.*, u.username AS login_username, u.last_login_at, u.last_logout_at
         FROM employees_details e
         LEFT JOIN users u ON u.email = e.email AND u.role = "employee"
         WHERE e.email = :email
         LIMIT 1'
    );
    $empEmailStmt->execute([':email' => $email]);
    $employee = $empEmailStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if (!$employee && $username !== '') {
    $empUserStmt = $conn->prepare(
        'SELECT e.*, u.username AS login_username, u.last_login_at, u.last_logout_at
         FROM users u
         LEFT JOIN employees_details e ON e.email = u.email
         WHERE u.username = :username AND u.role = "employee"
         LIMIT 1'
    );
    $empUserStmt->execute([':username' => $username]);
    $employee = $empUserStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$resolvedUsername = $username;
$resolvedEmail = $email;
$resolvedEmployeeId = 0;
$displayName = 'Archived Employee';

if ($employee) {
    $resolvedEmployeeId = (int) ($employee['id'] ?? 0);
    $resolvedUsername = (string) ($employee['login_username'] ?? $resolvedUsername);
    $resolvedEmail = (string) ($employee['email'] ?? $resolvedEmail);
    $displayName = (string) ($employee['name'] ?? $displayName);
}

if ($resolvedEmployeeId <= 0 && $resolvedUsername === '' && $resolvedEmail === '') {
    redirect('/employees-detail.php');
    exit;
}

$bookingClauses = [];
$bookingParams = [];

if ($resolvedEmployeeId > 0) {
    $bookingClauses[] = 'b.employee_id = :emp_id';
    $bookingParams[':emp_id'] = $resolvedEmployeeId;
}
if ($resolvedUsername !== '') {
    $bookingClauses[] = 'b.created_by = :created_by';
    $bookingParams[':created_by'] = $resolvedUsername;
}
if ($resolvedEmail !== '') {
    $bookingClauses[] = 'b.client_email = :client_email';
    $bookingParams[':client_email'] = $resolvedEmail;
}

if (count($bookingClauses) === 0) {
    $bookingClauses[] = '1 = 0';
}

$bookingWhereSql = implode(' OR ', $bookingClauses);

$bookingsStmt = $conn->prepare(
    'SELECT b.booking_code, b.client_name, b.client_phone, b.client_email, COALESCE(NULLIF(b.hotel_name_snapshot, ""), h.hotel_name) AS hotel_name, a.name AS agent_name,
            b.check_in, b.check_out, b.booking_date, b.amount, b.paid_amount, b.due_amount,
            b.payment_status, b.booking_status, b.status AS legacy_status, b.booking_source, b.guest_count, b.room_count,
            b.special_request, b.created_by, b.created_at
     FROM bookings_details b
     LEFT JOIN hotel_listings_details h ON h.id = b.hotel_listing_id
     LEFT JOIN agents_details a ON a.id = b.agent_id
     WHERE ' . $bookingWhereSql . '
     ORDER BY b.created_at DESC'
);
$bookingsStmt->execute($bookingParams);
$bookings = $bookingsStmt->fetchAll(PDO::FETCH_ASSOC);

$accounts = [];
if ($resolvedEmployeeId > 0) {
    $accountsStmt = $conn->prepare(
        'SELECT entry_date, entry_type, amount, notes, created_at
         FROM accounts_details
         WHERE employee_id = :emp_id
         ORDER BY entry_date DESC, id DESC'
    );
    $accountsStmt->execute([':emp_id' => $resolvedEmployeeId]);
    $accounts = $accountsStmt->fetchAll(PDO::FETCH_ASSOC);
}

$totalBookings = count($bookings);
$totalAmount = 0;
$totalPaid = 0;
$totalDue = 0;
foreach ($bookings as $row) {
    $totalAmount += (float) ($row['amount'] ?? 0);
    $totalPaid += (float) ($row['paid_amount'] ?? 0);
    $totalDue += (float) ($row['due_amount'] ?? 0);
}

$slugBase = preg_replace('/[^a-zA-Z0-9_-]+/', '-', strtolower($displayName));
if ($slugBase === '' || $slugBase === '-') {
    $slugBase = 'employee';
}
$fileName = $slugBase . '-full-data-' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $fileName . '"');

$output = fopen('php://output', 'w');
fwrite($output, "\xEF\xBB\xBF");

fputcsv($output, ['Employee Profile']);
fputcsv($output, ['Name', $displayName]);
fputcsv($output, ['Login ID', $resolvedUsername !== '' ? $resolvedUsername : 'N/A']);
fputcsv($output, ['Email', $resolvedEmail !== '' ? $resolvedEmail : 'N/A']);
if ($employee) {
    fputcsv($output, ['Phone', (string) ($employee['phone'] ?? 'N/A')]);
    fputcsv($output, ['Designation', (string) ($employee['designation'] ?? 'N/A')]);
    fputcsv($output, ['Department', (string) ($employee['department'] ?? 'N/A')]);
    fputcsv($output, ['HR Status', (string) ($employee['status'] ?? 'N/A')]);
    fputcsv($output, ['Monthly Salary', (float) ($employee['monthly_salary'] ?? 0)]);
    fputcsv($output, ['Last Login At', (string) ($employee['last_login_at'] ?? 'N/A')]);
    fputcsv($output, ['Last Logout At', (string) ($employee['last_logout_at'] ?? 'N/A')]);
} else {
    fputcsv($output, ['Profile Status', 'Archived / Deleted']);
}
fputcsv($output, []);

fputcsv($output, ['Summary']);
fputcsv($output, ['Total Bookings', $totalBookings]);
fputcsv($output, ['Total Amount', $totalAmount]);
fputcsv($output, ['Total Paid', $totalPaid]);
fputcsv($output, ['Total Due', $totalDue]);
fputcsv($output, []);

fputcsv($output, ['Bookings']);
fputcsv($output, [
    'Booking Code', 'Client Name', 'Client Phone', 'Client Email', 'Hotel', 'Agent',
    'Check In', 'Check Out', 'Booking Date', 'Amount', 'Paid', 'Due',
    'Payment Status', 'Status', 'Source', 'Guests', 'Rooms',
    'Special Request', 'Created By', 'Created At'
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
            (string) ($row['agent_name'] ?? ''),
            (string) ($row['check_in'] ?? ''),
            (string) ($row['check_out'] ?? ''),
            (string) ($row['booking_date'] ?? ''),
            (float) ($row['amount'] ?? 0),
            (float) ($row['paid_amount'] ?? 0),
            (float) ($row['due_amount'] ?? 0),
            (string) ($row['payment_status'] ?? ''),
            $statusLabel,
            (string) ($row['booking_source'] ?? ''),
            (int) ($row['guest_count'] ?? 0),
            (int) ($row['room_count'] ?? 0),
            (string) ($row['special_request'] ?? ''),
            (string) ($row['created_by'] ?? ''),
            (string) ($row['created_at'] ?? ''),
        ]);
    }
}

fputcsv($output, []);
fputcsv($output, ['Accounts Entries']);
fputcsv($output, ['Entry Date', 'Type', 'Amount', 'Notes', 'Created At']);
if (count($accounts) === 0) {
    fputcsv($output, ['No accounts records found']);
} else {
    foreach ($accounts as $row) {
        fputcsv($output, [
            (string) ($row['entry_date'] ?? ''),
            (string) ($row['entry_type'] ?? ''),
            (float) ($row['amount'] ?? 0),
            (string) ($row['notes'] ?? ''),
            (string) ($row['created_at'] ?? ''),
        ]);
    }
}

fclose($output);
exit;
