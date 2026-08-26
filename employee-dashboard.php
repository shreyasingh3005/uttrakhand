<?php
session_start();
require_once 'includes/auth_session.php';
require_once 'includes/db_connect.php';

require_login();

$username = $_SESSION['username'];
$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'] ?? null;
$user_initial = strtoupper(substr($username, 0, 1));

function get_employee_live_metrics(PDO $conn, string $username): array {
    $summaryStmt = $conn->prepare(
        'SELECT
            COUNT(*) AS total_bookings,
            SUM(CASE WHEN booking_date = CURDATE() THEN 1 ELSE 0 END) AS today_bookings,
            COALESCE(SUM(amount), 0) AS total_amount,
            COALESCE(SUM(paid_amount), 0) AS total_paid,
            COALESCE(SUM(due_amount), 0) AS total_due,
            SUM(CASE WHEN payment_status = "pending" THEN 1 ELSE 0 END) AS pending_payment_count,
            SUM(CASE WHEN payment_status = "partial" THEN 1 ELSE 0 END) AS partial_payment_count,
            SUM(CASE WHEN payment_status = "paid" THEN 1 ELSE 0 END) AS paid_payment_count
         FROM bookings_details
         WHERE created_by = :username'
    );
    $summaryStmt->execute([':username' => $username]);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $statusMap = ['pending' => 0, 'completed' => 0, 'cancelled' => 0];
    $statusStmt = $conn->prepare('SELECT booking_status, COUNT(*) AS total FROM bookings_details WHERE created_by = :username GROUP BY booking_status');
    $statusStmt->execute([':username' => $username]);
    foreach ($statusStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $statusKey = strtolower((string) $row['booking_status']);
        if (isset($statusMap[$statusKey])) {
            $statusMap[$statusKey] = (int) $row['total'];
        }
    }

    $paymentMap = ['pending' => 0, 'partial' => 0, 'paid' => 0, 'cancelled' => 0];
    $paymentStmt = $conn->prepare('SELECT payment_status, COUNT(*) AS total FROM bookings_details WHERE created_by = :username GROUP BY payment_status');
    $paymentStmt->execute([':username' => $username]);
    foreach ($paymentStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $paymentKey = strtolower((string) $row['payment_status']);
        if (isset($paymentMap[$paymentKey])) {
            $paymentMap[$paymentKey] = (int) $row['total'];
        }
    }

    $weeklyLabels = [];
    $weeklyCounts = [];
    $weeklyMap = [];
    $weeklyStmt = $conn->prepare(
        'SELECT booking_date, COUNT(*) AS total
         FROM bookings_details
         WHERE created_by = :username AND booking_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
         GROUP BY booking_date'
    );
    $weeklyStmt->execute([':username' => $username]);
    foreach ($weeklyStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $weeklyMap[$row['booking_date']] = (int) $row['total'];
    }
    for ($i = 6; $i >= 0; $i--) {
        $dateKey = date('Y-m-d', strtotime('-' . $i . ' day'));
        $weeklyLabels[] = date('d M', strtotime($dateKey));
        $weeklyCounts[] = $weeklyMap[$dateKey] ?? 0;
    }

    $monthlyLabels = [];
    $monthlyCounts = [];
    $monthlyStmt = $conn->prepare(
        'SELECT DATE_FORMAT(booking_date, "%b %Y") AS month_label, COUNT(*) AS total
         FROM bookings_details
         WHERE created_by = :username AND booking_date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), "%Y-%m-01")
         GROUP BY YEAR(booking_date), MONTH(booking_date)
         ORDER BY YEAR(booking_date), MONTH(booking_date)'
    );
    $monthlyStmt->execute([':username' => $username]);
    foreach ($monthlyStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $monthlyLabels[] = $row['month_label'];
        $monthlyCounts[] = (int) $row['total'];
    }

    $sourceLabels = [];
    $sourceCounts = [];
    $sourceStmt = $conn->prepare(
        'SELECT COALESCE(NULLIF(booking_source, ""), "Direct") AS src, COUNT(*) AS total
         FROM bookings_details
         WHERE created_by = :username
         GROUP BY src
         ORDER BY total DESC
         LIMIT 6'
    );
    $sourceStmt->execute([':username' => $username]);
    foreach ($sourceStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sourceLabels[] = $row['src'];
        $sourceCounts[] = (int) $row['total'];
    }

    $hotelLabels = [];
    $hotelCounts = [];
    $hotelStmt = $conn->prepare(
           'SELECT COALESCE(h.name, "Unknown") AS hotel_name, COUNT(*) AS total
         FROM bookings_details b
            LEFT JOIN hotels h ON h.id = b.hotel_listing_id
         WHERE b.created_by = :username
            GROUP BY h.name
         ORDER BY total DESC
         LIMIT 6'
    );
    $hotelStmt->execute([':username' => $username]);
    foreach ($hotelStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $hotelLabels[] = $row['hotel_name'];
        $hotelCounts[] = (int) $row['total'];
    }

    return [
        'kpi' => [
            'totalBookings' => (int) ($summary['total_bookings'] ?? 0),
            'todayBookings' => (int) ($summary['today_bookings'] ?? 0),
            'totalAmount' => (float) ($summary['total_amount'] ?? 0),
            'received' => (float) ($summary['total_paid'] ?? 0),
            'due' => (float) ($summary['total_due'] ?? 0),
            'pendingPayment' => (int) (($summary['pending_payment_count'] ?? 0) + ($summary['partial_payment_count'] ?? 0)),
        ],
        'weekly' => ['labels' => $weeklyLabels, 'counts' => $weeklyCounts],
        'status' => ['labels' => ['Pending', 'Completed', 'Cancelled'], 'counts' => [$statusMap['pending'], $statusMap['completed'], $statusMap['cancelled']]],
        'payment' => ['labels' => ['Pending', 'Partial', 'Paid', 'Cancelled'], 'counts' => [$paymentMap['pending'], $paymentMap['partial'], $paymentMap['paid'], $paymentMap['cancelled']]],
        'monthly' => ['labels' => $monthlyLabels, 'counts' => $monthlyCounts],
        'source' => ['labels' => $sourceLabels, 'counts' => $sourceCounts],
        'hotels' => ['labels' => $hotelLabels, 'counts' => $hotelCounts],
        'updatedAt' => date('H:i:s'),
    ];
}

function record_activity_log(PDO $conn, string $action, ?int $queryLockId, ?int $bookingId, string $performedByUsername, ?int $performedByUserId, string $role, ?string $details = null) {
    try {
        $stmt = $conn->prepare("INSERT INTO activity_logs (
            query_lock_id, booking_id, action, performed_by_user_id, performed_by_username, performed_by_role, details, ip_address, user_agent
        ) VALUES (
            :query_lock_id, :booking_id, :action, :performed_by_user_id, :performed_by_username, :performed_by_role, :details, :ip_address, :user_agent
        )");
        $stmt->execute([
            ':query_lock_id' => $queryLockId,
            ':booking_id' => $bookingId,
            ':action' => $action,
            ':performed_by_user_id' => $performedByUserId,
            ':performed_by_username' => $performedByUsername,
            ':performed_by_role' => $role,
            ':details' => $details,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    } catch (PDOException $e) {
        // logging should not break the main flow
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'live_metrics') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'metrics' => get_employee_live_metrics($conn, $username)]);
    exit;
}

// Handle Agent Creation Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_agent') {
        $agent_name = sanitize_input($_POST['agentName'] ?? '');
        $company_name = sanitize_input($_POST['companyName'] ?? '');
        $gst_number = strtoupper(sanitize_input($_POST['gstNumber'] ?? ''));
        $email = sanitize_input($_POST['email'] ?? '');
        $phone = sanitize_input($_POST['contact'] ?? '');
        $location = sanitize_input($_POST['location'] ?? ($_POST['address'] ?? ''));

        if ($gst_number !== '' && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $gst_number)) {
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Invalid GSTIN. Please enter a valid 15-character GSTIN.']);
            exit;
        }

        if ($agent_name && $company_name && $email && $phone && $location) {
            try {
                $existingAgentStmt = $conn->prepare('SELECT id FROM agents_details WHERE phone = :phone LIMIT 1');
                $existingAgentStmt->execute([':phone' => $phone]);
                if ($existingAgentStmt->fetch()) {
                    http_response_code(200);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'message' => 'This mobile number is already registered. Please use a different mobile number.']);
                    exit;
                }

                $query = "INSERT INTO agents_details (name, company_name, gst_number, email, phone, location, status, created_by)
                         VALUES (:name, :company_name, :gst_number, :email, :phone, :location, 'Active', :created_by)";
                $stmt = $conn->prepare($query);
                $stmt->execute([
                    ':name' => $agent_name,
                    ':company_name' => $company_name,
                    ':gst_number' => $gst_number !== '' ? $gst_number : null,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':location' => $location,
                    ':created_by' => $username
                ]);
                http_response_code(200);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true, 'message' => "Agent created successfully!"]);
                exit;
            } catch (PDOException $e) {
                http_response_code(200);
                header('Content-Type: application/json; charset=utf-8');
                $duplicateMobileStmt = $conn->prepare('SELECT id FROM agents_details WHERE phone = :phone LIMIT 1');
                $duplicateMobileStmt->execute([':phone' => $phone]);
                echo json_encode([
                    'success' => false,
                    'message' => $duplicateMobileStmt->fetch()
                        ? 'This mobile number is already registered. Please use a different mobile number.'
                        : 'Agent registration failed. Please verify the details and try again.'
                ]);
                exit;
            }
        }
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => "Please fill all required fields"]);
        exit;
    }
    
    if ($_POST['action'] === 'create_booking') {
        $client_name = sanitize_input($_POST['clientName'] ?? '');
        $client_phone = sanitize_input($_POST['clientPhone'] ?? '');
        $client_email = sanitize_input($_POST['clientEmail'] ?? '');
        $hotel_id = intval($_POST['hotelId'] ?? 0);
        $agent_id = intval($_POST['agentId'] ?? 0);
        $check_in = sanitize_input($_POST['checkIn'] ?? '');
        $check_out = sanitize_input($_POST['checkOut'] ?? '');
        $booking_date = sanitize_input($_POST['bookingDate'] ?? date('Y-m-d'));
        $amount = floatval($_POST['amount'] ?? 0);
        $status = sanitize_input($_POST['status'] ?? 'Confirmed');
        $booking_source = sanitize_input($_POST['bookingSource'] ?? 'Direct');
        $guest_count = intval($_POST['guestCount'] ?? 1);
        $room_count = intval($_POST['roomCount'] ?? 1);
        $special_request = sanitize_input($_POST['specialRequest'] ?? '');
        $payment_note = sanitize_input($_POST['paymentNote'] ?? '');
        $paid_amount = floatval($_POST['paidAmount'] ?? 0);
        if ($paid_amount < 0) {
            $paid_amount = 0;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $booking_date)) {
            $booking_date = date('Y-m-d');
        }
        if ($guest_count < 1) {
            $guest_count = 1;
        }
        if ($room_count < 1) {
            $room_count = 1;
        }

        $allowedStatuses = ['Pending', 'Completed', 'Cancelled'];
        $booking_status = in_array($status, $allowedStatuses, true) ? $status : 'Pending';
        $legacy_status = $booking_status === 'Completed' ? 'Confirmed' : ($booking_status === 'Cancelled' ? 'Cancelled' : 'Pending Payment');

        if ($client_name && $client_phone && $hotel_id && $agent_id && $check_in && $check_out && $amount > 0) {
            try {
                $hotelSnapshotStmt = $conn->prepare('SELECT h.name AS hotel_name, CONCAT_WS(", ", NULLIF(h.city, ""), NULLIF(h.state, "")) AS location, "" AS room_type, "" AS category FROM hotels h WHERE h.id = :id LIMIT 1');
                $hotelSnapshotStmt->execute([':id' => $hotel_id]);
                $hotelSnapshot = $hotelSnapshotStmt->fetch(PDO::FETCH_ASSOC);
                if (!$hotelSnapshot) {
                    http_response_code(200);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'message' => "Selected hotel not found"]);
                    exit;
                }

                $booking_code = 'BK-' . date('YmdHis') . '-' . random_int(1000, 9999);
                
                $query = "INSERT INTO bookings_details 
                         (booking_code, client_name, client_phone, client_email, hotel_listing_id, agent_id, 
                          check_in, check_out, amount, booking_source, guest_count, room_count, special_request, paid_amount, due_amount, payment_status, booking_status, status, booking_date, payment_note, hotel_name_snapshot, hotel_location_snapshot, room_type_snapshot, hotel_category_snapshot, created_by, payment_updated_by, payment_updated_at) 
                         VALUES (:code, :client_name, :client_phone, :client_email, :hotel_id, :agent_id, 
                              :check_in, :check_out, :amount, :booking_source, :guest_count, :room_count, :special_request, :paid_amount, :due_amount, :payment_status, :booking_status, :status, :booking_date, :payment_note, :hotel_name_snapshot, :hotel_location_snapshot, :room_type_snapshot, :hotel_category_snapshot, :created_by, :payment_updated_by, NOW())";
                $stmt = $conn->prepare($query);
                $due_amount = max($amount - $paid_amount, 0);
                $payment_status = $paid_amount <= 0 ? 'Pending' : (($paid_amount >= $amount) ? 'Paid' : 'Partial');
                $stmt->execute([
                    ':code' => $booking_code,
                    ':client_name' => $client_name,
                    ':client_phone' => $client_phone,
                    ':client_email' => $client_email,
                    ':hotel_id' => $hotel_id,
                    ':agent_id' => $agent_id,
                    ':check_in' => $check_in,
                    ':check_out' => $check_out,
                    ':amount' => $amount,
                    ':booking_source' => $booking_source,
                    ':guest_count' => $guest_count,
                    ':room_count' => $room_count,
                    ':special_request' => $special_request,
                    ':paid_amount' => $paid_amount,
                    ':due_amount' => $due_amount,
                    ':payment_status' => $payment_status,
                    ':booking_status' => $booking_status,
                    ':status' => $legacy_status,
                    ':booking_date' => $booking_date,
                    ':payment_note' => $payment_note,
                    ':hotel_name_snapshot' => (string) ($hotelSnapshot['hotel_name'] ?? ''),
                    ':hotel_location_snapshot' => (string) ($hotelSnapshot['location'] ?? ''),
                    ':room_type_snapshot' => (string) ($hotelSnapshot['room_type'] ?? ''),
                    ':hotel_category_snapshot' => (string) ($hotelSnapshot['category'] ?? ''),
                    ':created_by' => $username,
                    ':payment_updated_by' => $username
                ]);
                http_response_code(200);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true, 'message' => "Booking created successfully! Code: $booking_code"]);
                exit;
            } catch (PDOException $e) {
                http_response_code(200);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => "Booking already exists or database error"]);
                exit;
            }
        }
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => "Please fill all required fields"]);
        exit;
    }

    if ($_POST['action'] === 'update_payment_status') {
        $booking_id = intval($_POST['bookingId'] ?? 0);
        $payment_amount = floatval($_POST['paidAmount'] ?? 0);
        $payment_note = sanitize_input($_POST['paymentNote'] ?? '');

        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');

        if ($booking_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid booking selected']);
            exit;
        }

        if ($payment_amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Enter a valid payment amount greater than 0']);
            exit;
        }

        try {
            $findStmt = $conn->prepare('SELECT id, amount, paid_amount FROM bookings_details WHERE id = :id AND created_by = :username LIMIT 1');
            $findStmt->execute([':id' => $booking_id, ':username' => $username]);
            $booking = $findStmt->fetch(PDO::FETCH_ASSOC);

            if (!$booking) {
                echo json_encode(['success' => false, 'message' => 'Booking not found']);
                exit;
            }

            $amount = (float) $booking['amount'];
            $existing_paid = (float) $booking['paid_amount'];
            $pending_due = max($amount - $existing_paid, 0);

            if ($payment_amount > $pending_due) {
                $maxAllowed = number_format($pending_due, 0, '.', ',');
                echo json_encode(['success' => false, 'message' => "Entered amount exceeds pending due amount. Maximum allowed is ₹{$maxAllowed}"]);
                exit;
            }

            $new_paid_amount = $existing_paid + $payment_amount;
            $new_due_amount = max($amount - $new_paid_amount, 0);
            $payment_status = $new_paid_amount <= 0 ? 'Pending' : (($new_paid_amount >= $amount) ? 'Paid' : 'Partial');

            $updateStmt = $conn->prepare(
                'UPDATE bookings_details
                 SET paid_amount = :paid_amount,
                     due_amount = :due_amount,
                     payment_status = :payment_status,
                     payment_note = :payment_note,
                     payment_updated_by = :updated_by,
                     payment_updated_at = NOW()
                 WHERE id = :id AND created_by = :username'
            );
            $updateStmt->execute([
                ':paid_amount' => $new_paid_amount,
                ':due_amount' => $new_due_amount,
                ':payment_status' => $payment_status,
                ':payment_note' => $payment_note,
                ':updated_by' => $username,
                ':id' => $booking_id,
                ':username' => $username,
            ]);

            echo json_encode(['success' => true, 'message' => 'Payment status updated successfully']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Unable to update payment status']);
        }
        exit;
    }
    
    // Search Agent by Mobile Number
    if ($_POST['action'] === 'search_agent_by_mobile') {
        $mobile = sanitize_input($_POST['mobileNumber'] ?? '');
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        
        if (!$mobile) {
            echo json_encode(['success' => false, 'message' => "Please enter a mobile number"]);
            exit;
        }
        
        try {
            $stmt = $conn->prepare("SELECT id, name, company_name, gst_number, email, phone, location, status, created_by, created_at FROM agents_details WHERE phone = :phone LIMIT 1");
            $stmt->execute([':phone' => $mobile]);
            $agent = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($agent) {
                $lockStmt = $conn->prepare('SELECT employee_id, employee_username, lock_until FROM agent_query_locks WHERE agent_id = :agent_id AND lock_until > NOW() AND status = "Locked" ORDER BY lock_until DESC LIMIT 1');
                $lockStmt->execute([':agent_id' => (int)$agent['id']]);
                $activeLock = $lockStmt->fetch(PDO::FETCH_ASSOC);
                $ownsActiveLock = $activeLock && (((int)($activeLock['employee_id'] ?? 0) === (int)$user_id) || ($activeLock['employee_username'] ?? '') === $username);
                if ($activeLock && $user_role !== 'admin' && !$ownsActiveLock) {
                    $lockTime = date('d M Y, h:i A', strtotime((string)$activeLock['lock_until']));
                    echo json_encode([
                        'success' => false,
                        'found' => false,
                        'locked' => true,
                        'lock_until' => $activeLock['lock_until'],
                        'message' => 'This agent already has a query generated by ' . (($activeLock['employee_username'] ?? '') ?: 'another employee') . '. The agent is locked until ' . $lockTime . '. For more information, please contact Admin.'
                    ]);
                    exit;
                }

                // Get agent's bookings
                $bookingsStmt = $conn->prepare("SELECT bd.*, COALESCE(NULLIF(bd.hotel_name_snapshot, ''), h.name) AS hotel_name 
                                               FROM bookings_details bd
                                               LEFT JOIN hotels h ON bd.hotel_listing_id = h.id
                                               WHERE bd.agent_id = :agent_id
                                               ORDER BY bd.created_at DESC
                                               LIMIT 20");
                $bookingsStmt->execute([':agent_id' => $agent['id']]);
                $agent_bookings = $bookingsStmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true, 
                    'found' => true,
                    'agent' => $agent,
                    'bookings' => $agent_bookings,
                    'booking_count' => count($agent_bookings)
                ]);
            } else {
                echo json_encode(['success' => true, 'found' => false, 'message' => "Agent not found. Please register first."]);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error occurred. Please try again.']);
        }
        exit;
    }

    if ($_POST['action'] === 'search_hotels') {
        $keyword = sanitize_input($_POST['keyword'] ?? '');
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');

        try {
            $keywordLength = function_exists('mb_strlen') ? mb_strlen($keyword, 'UTF-8') : strlen($keyword);
            if ($keywordLength < 1) {
                echo json_encode(['success' => true, 'hotels' => []]);
                exit;
            }

            $keywordNormalized = function_exists('mb_strtolower') ? mb_strtolower($keyword, 'UTF-8') : strtolower($keyword);
            $tokens = preg_split('/\s+/', $keywordNormalized) ?: [];
            $tokens = array_values(array_filter(array_map('trim', $tokens), static function ($value) {
                return $value !== '';
            }));

            $whereParts = [];
            $params = [];
            foreach ($tokens as $index => $token) {
                $param = ':k' . $index;
                $whereParts[] = '(LOWER(h.name) LIKE ' . $param . ' OR LOWER(h.city) LIKE ' . $param . ' OR LOWER(h.state) LIKE ' . $param . ' OR LOWER(h.hotel_code) LIKE ' . $param . ')';
                $params[$param] = '%' . $token . '%';
            }

            if (!$whereParts) {
                echo json_encode(['success' => true, 'hotels' => []]);
                exit;
            }

            $stmt = $conn->prepare(
                'SELECT h.id, h.name AS hotel_name, h.city, h.state, h.hotel_code, h.star_rating
                 FROM hotels h
                 WHERE h.status = "active" AND ' . implode(' AND ', $whereParts) . '
                 ORDER BY h.name ASC, h.city ASC
                 LIMIT 20'
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $hotels = [];
            foreach ($rows as $row) {
                $name = trim((string)($row['hotel_name'] ?? ''));
                $location = trim((string)($row['city'] ?? ''));
                $state = trim((string)($row['state'] ?? ''));
                $locationText = trim($location . ($state !== '' ? ', ' . $state : ''));
                $code = trim((string)($row['hotel_code'] ?? ''));
                $label = $locationText !== '' ? ($name . ', ' . $locationText) : $name;
                $hotels[] = [
                    'id' => (int)($row['id'] ?? 0),
                    'label' => $label,
                    'name' => $name,
                    'location' => $locationText,
                    'category' => ((int)($row['star_rating'] ?? 0)) > 0 ? (((int)$row['star_rating']) . ' Star') : '',
                    'room_type' => '',
                    'hotel_code' => $code,
                ];
            }

            echo json_encode(['success' => true, 'hotels' => $hotels]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Unable to search hotels']);
        }
        exit;
    }

    if ($_POST['action'] === 'get_hotel_room_categories') {
        $hotelId = (int)($_POST['hotelId'] ?? 0);
        $roomTypeRaw = sanitize_input($_POST['roomType'] ?? '');
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');

        if ($hotelId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid hotel selected']);
            exit;
        }

        try {
            $stmt = $conn->prepare(
                'SELECT name AS category_name
                 FROM hotel_room_categories
                 WHERE hotel_id = :hotel_id AND status = "active"
                 ORDER BY id ASC'
            );
            $stmt->execute([':hotel_id' => $hotelId]);
            $categories = array_values(array_filter(array_map(static function ($row) {
                return trim((string)($row['category_name'] ?? ''));
            }, $stmt->fetchAll(PDO::FETCH_ASSOC)), static function ($value) {
                return $value !== '';
            }));

            echo json_encode(['success' => true, 'categories' => $categories]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Unable to fetch room categories']);
        }
        exit;
    }

    if ($_POST['action'] === 'get_room_category_pricing') {
        $hotelId = (int)($_POST['hotelId'] ?? 0);
        $categoryName = sanitize_input($_POST['categoryName'] ?? '');
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');

        if ($hotelId <= 0 || !$categoryName) {
            echo json_encode(['success' => false, 'message' => 'Invalid hotel or category']);
            exit;
        }

        try {
            $stmt = $conn->prepare(
                'SELECT
                    COALESCE(MAX(CASE WHEN mp.code = "EP"  THEN rp.base_price END), 0) AS weekday_price,
                    COALESCE(MAX(CASE WHEN mp.code = "EP"  THEN rp.base_price END), 0) AS weekend_price,
                    COALESCE(MAX(CASE WHEN mp.code = "CP"  THEN rp.base_price END), 0) AS cpai_price,
                    COALESCE(MAX(CASE WHEN mp.code = "MAP" THEN rp.base_price END), 0) AS mapai_price,
                    COALESCE(MAX(CASE WHEN mp.code = "AP"  THEN rp.base_price END), 0) AS apai_price,
                    0 AS child_no_bed_cpai,
                    0 AS child_with_bed_cpai,
                    0 AS adult_with_bed_cpai,
                    MAX(hrc.extra_bed_price) AS extra_person_with_bed,
                    0 AS extra_person_without_bed
                 FROM hotel_room_categories hrc
                 LEFT JOIN room_prices rp ON rp.room_category_id = hrc.id AND rp.rate_date IS NULL
                 LEFT JOIN meal_plans mp ON mp.id = rp.meal_plan_id
                 WHERE hrc.hotel_id = :hotel_id AND hrc.name = :category_name AND hrc.status = "active"
                 GROUP BY hrc.id'
            );
            $stmt->execute([':hotel_id' => $hotelId, ':category_name' => $categoryName]);
            $pricing = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pricing) {
                echo json_encode(['success' => false, 'message' => 'Pricing not found']);
                exit;
            }

            echo json_encode(['success' => true, 'pricing' => $pricing]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Unable to fetch pricing']);
        }
        exit;
    }

    if ($_POST['action'] === 'filter_hotels_for_query') {
        $filterLocation = sanitize_input($_POST['location'] ?? '');
        $filterCategory = sanitize_input($_POST['category'] ?? '');
        $filterBudget = (float)($_POST['budget'] ?? 0);
        $filterCheckIn = sanitize_input($_POST['check_in'] ?? '');
        $filterCheckOut = sanitize_input($_POST['check_out'] ?? '');
        $filterAdults = max(1, (int)($_POST['adults'] ?? 1));
        $filterChildren = max(0, (int)($_POST['children'] ?? 0));
        $filterRooms = max(1, (int)($_POST['rooms'] ?? 1));

        // Nights: recompute from dates when both are valid, else fall back to the posted value.
        $filterNights = max(0, (int)($_POST['nights'] ?? 0));
        if ($filterCheckIn !== '' && $filterCheckOut !== '') {
            try {
                $diff = (new DateTime($filterCheckIn))->diff(new DateTime($filterCheckOut))->days;
                if ($diff > 0) {
                    $filterNights = $diff;
                }
            } catch (Exception $e) {
                // Keep the posted nights value if dates are unparseable.
            }
        }
        $filterNights = max(1, $filterNights);

        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');

        try {
            $where = ["LOWER(TRIM(h.status)) = 'active'"];
            $params = [];
            // Fuzzy/partial location search stays inside the hotel listing city.
            $locationTokens = array_filter(preg_split('/\s+/', trim($filterLocation)));
            if ($locationTokens) {
                $tokenClauses = [];
                $paramIndex = 0;
                foreach (array_values($locationTokens) as $i => $token) {
                    $keyCity = ':locc' . $paramIndex;
                    $keyState = ':locs' . $paramIndex;
                    $tokenClauses[] = "(LOWER(TRIM(h.city)) LIKE $keyCity OR LOWER(TRIM(h.state)) LIKE $keyState)";
                    $params[$keyCity] = '%' . strtolower($token) . '%';
                    $params[$keyState] = '%' . strtolower($token) . '%';
                    $paramIndex++;
                }
                $where[] = '(' . implode(' AND ', $tokenClauses) . ')';
            }
            if ($filterCategory !== '' && strtolower(trim($filterCategory)) !== 'all categories') {
                $where[] = "LOWER(TRIM(COALESCE(NULLIF(h.property_category, ''), CONCAT(h.star_rating, ' Star')))) = LOWER(TRIM(:category))";
                $params[':category'] = $filterCategory;
            }

            $stmt = $conn->prepare(
                'SELECT h.id, h.name, h.city, h.state, h.hotel_code, h.star_rating, h.property_category,
                        h.address, h.description, h.phone, h.email
                 FROM hotels h
                 WHERE ' . implode(' AND ', $where) . '
                 ORDER BY h.name ASC'
            );
            $stmt->execute($params);
            $hotelRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $results = [];
            if ($hotelRows) {
                $hotelIds = array_map(static function ($r) { return (int)$r['id']; }, $hotelRows);
                $placeholders = implode(',', array_fill(0, count($hotelIds), '?'));

                $roomStmt = $conn->prepare(
                    "SELECT hrc.id, hrc.hotel_id, hrc.name, hrc.bed_type, hrc.room_size,
                            hrc.total_rooms, hrc.available_rooms, hrc.extra_bed_allowed, hrc.extra_bed_price,
                            mp.code AS meal_code, rp.rate_date, COALESCE(NULLIF(rp.base_price, 0), rp.date_wise_price, 0) AS base_price
                     FROM hotel_room_categories hrc
                     LEFT JOIN room_prices rp ON rp.room_category_id = hrc.id
                     LEFT JOIN meal_plans mp ON mp.id = rp.meal_plan_id
                     WHERE hrc.hotel_id IN ($placeholders) AND LOWER(TRIM(hrc.status)) = 'active'
                     ORDER BY hrc.id ASC"
                );
                $roomStmt->execute($hotelIds);

                $roomsByHotel = [];
                foreach ($roomStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $hid = (int)$row['hotel_id'];
                    $rid = (int)$row['id'];
                    if (!isset($roomsByHotel[$hid][$rid])) {
                        $roomsByHotel[$hid][$rid] = [
                            'id' => $rid,
                            'name' => (string)$row['name'],
                            'bed_type' => (string)$row['bed_type'],
                            'room_size' => (string)$row['room_size'],
                            'total_rooms' => (int)$row['total_rooms'],
                            'available_rooms' => (int)$row['available_rooms'],
                            'extra_bed_allowed' => (bool)$row['extra_bed_allowed'],
                            'extra_bed_price' => (float)$row['extra_bed_price'],
                            'prices' => [],
                        ];
                    }
                    if (!empty($row['meal_code'])) {
                        $rateDate = (string)($row['rate_date'] ?? '');
                        $code = (string)$row['meal_code'];
                        $price = (float)$row['base_price'];
                        $hasSelectedDateRate = $filterCheckIn !== '' && $rateDate === $filterCheckIn;
                        $isBaseRate = $rateDate === '';
                        if ($hasSelectedDateRate || ($isBaseRate && !array_key_exists($code, $roomsByHotel[$hid][$rid]['prices']))) {
                            $roomsByHotel[$hid][$rid]['prices'][$code] = $price;
                        }
                    }
                }

                // Budget is the customer's acceptable per-night EP price range.
                $budgetLow = $filterBudget > 0 ? $filterBudget * 0.5 : null;
                $budgetHigh = $filterBudget > 0 ? $filterBudget * 1.5 : null;

                foreach ($hotelRows as $hotel) {
                    $hid = (int)$hotel['id'];
                    $allRooms = array_values($roomsByHotel[$hid] ?? []);
                    // Only rooms that can actually cover the requested room count are eligible.
                    $eligibleRooms = array_values(array_filter($allRooms, static function ($room) use ($filterRooms) {
                        return (int)$room['available_rooms'] >= $filterRooms;
                    }));

                    $availableRoomsTotal = 0;
                    foreach ($allRooms as $room) {
                        $availableRoomsTotal += (int)$room['available_rooms'];
                    }
                    $matchingRooms = array_values(array_filter($eligibleRooms, static function ($room) use ($budgetLow, $budgetHigh) {
                        $price = (float)($room['prices']['EP'] ?? 0);
                        if ($price <= 0) {
                            $availablePrices = array_values(array_filter(array_map('floatval', (array)($room['prices'] ?? []))));
                            $price = $availablePrices ? min($availablePrices) : 0;
                        }
                        return $price > 0 && ($budgetLow === null || ($price >= $budgetLow && $price <= $budgetHigh));
                    }));
                    if (empty($matchingRooms)) {
                        continue;
                    }

                    $epPrices = array_map(static function ($room) {
                        $epPrice = (float)($room['prices']['EP'] ?? 0);
                        if ($epPrice <= 0) {
                            $availablePrices = array_values(array_filter(array_map('floatval', (array)($room['prices'] ?? []))));
                            $epPrice = $availablePrices ? min($availablePrices) : 0;
                        }
                        return $epPrice;
                    }, $matchingRooms);
                    $minPrice = min($epPrices);
                    $maxPrice = max($epPrices);
                    $totalMin = $minPrice * $filterNights * $filterRooms;
                    $totalMax = $maxPrice * $filterNights * $filterRooms;

                    $estNightly = $minPrice;
                    if ($filterBudget > 0) {
                        $estNightly = $epPrices[0];
                        $closestDiff = abs($epPrices[0] - $filterBudget);
                        foreach ($epPrices as $price) {
                            $diff = abs($price - $filterBudget);
                            if ($diff < $closestDiff) {
                                $estNightly = $price;
                                $closestDiff = $diff;
                            }
                        }
                    }
                    $estTotal = $estNightly * $filterNights * $filterRooms;

                    $location = trim((string)$hotel['city'] . (($hotel['state'] ?? '') !== '' ? ', ' . $hotel['state'] : ''));

                    $results[] = [
                        'id' => $hid,
                        'name' => (string)$hotel['name'],
                        'hotel_code' => (string)$hotel['hotel_code'],
                        'location' => $location,
                        'city' => (string)$hotel['city'],
                        'category' => (string)($hotel['property_category'] ?: (((int)$hotel['star_rating']) . ' Star')),
                        'address' => (string)$hotel['address'],
                        'description' => (string)$hotel['description'],
                        'phone' => (string)$hotel['phone'],
                        'email' => (string)$hotel['email'],
                        'available_rooms' => $availableRoomsTotal,
                        'min_price' => $minPrice,
                        'max_price' => $maxPrice,
                        'nights' => $filterNights,
                        'rooms_requested' => $filterRooms,
                        'adults' => $filterAdults,
                        'children' => $filterChildren,
                        'total_min' => round($totalMin),
                        'total_max' => round($totalMax),
                        'budget_min' => $budgetLow !== null ? round($budgetLow) : null,
                        'budget_max' => $budgetHigh !== null ? round($budgetHigh) : null,
                        'est_budget' => round($estNightly),
                        'est_total' => round($estTotal),
                        'rooms' => $matchingRooms,
                    ];
                }
            }

            echo json_encode(['success' => true, 'results' => $results, 'count' => count($results), 'nights' => $filterNights, 'rooms' => $filterRooms]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Unable to load matching properties']);
        }
        exit;
    }

    if ($_POST['action'] === 'acquire_booking_query_agent_lock') {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        $agentPhone = sanitize_input($_POST['agent_phone'] ?? '');
        if ($agentPhone === '') {
            echo json_encode(['success' => false, 'message' => 'Agent mobile number is required']);
            exit;
        }
        try {
            $agentStmt = $conn->prepare('SELECT id, name, phone FROM agents_details WHERE phone = :phone AND status = "Active" LIMIT 1');
            $agentStmt->execute([':phone' => $agentPhone]);
            $agent = $agentStmt->fetch(PDO::FETCH_ASSOC);
            if (!$agent) {
                echo json_encode(['success' => false, 'message' => 'Agent mobile number is not registered.']);
                exit;
            }

            $conn->beginTransaction();
            $lockStmt = $conn->prepare('SELECT id, employee_id, employee_username, lock_until FROM agent_query_locks WHERE agent_id = :agent_id AND lock_until > NOW() AND status = "Locked" ORDER BY lock_until DESC LIMIT 1 FOR UPDATE');
            $lockStmt->execute([':agent_id' => (int)$agent['id']]);
            $activeLock = $lockStmt->fetch(PDO::FETCH_ASSOC);
            if ($activeLock && $user_role !== 'admin' && ((int)$activeLock['employee_id'] !== (int)$user_id) && $activeLock['employee_username'] !== $username) {
                $conn->rollBack();
                echo json_encode(['success' => false, 'message' => 'This agent is currently locked with another employee and cannot be booked by you until the lock expires.', 'lock_until' => $activeLock['lock_until']]);
                exit;
            }

            if ($activeLock) {
                $lockUntil = $activeLock['lock_until'];
            } else {
                $lockUntil = $user_role === 'admin' ? date('Y-m-d H:i:s') : date('Y-m-d H:i:s', strtotime('+6 hours'));
                $newLockStmt = $conn->prepare('INSERT INTO agent_query_locks (agent_id, employee_id, employee_username, created_by_user_id, created_by_role, generated_at, locked_at, lock_until, query_text, status, booking_status, ip_address, user_agent) VALUES (:agent_id, :employee_id, :employee_username, :created_by_user_id, :created_by_role, NOW(), NOW(), :lock_until, :query_text, :status, "Unbooked", :ip_address, :user_agent)');
                $newLockStmt->execute([
                    ':agent_id' => (int)$agent['id'], ':employee_id' => $user_id, ':employee_username' => $username,
                    ':created_by_user_id' => $user_id, ':created_by_role' => $user_role, ':lock_until' => $lockUntil,
                    ':query_text' => 'Booking Query generated for agent ' . $agent['name'] . ' (' . $agent['phone'] . ')',
                    ':status' => $user_role === 'admin' ? 'Open' : 'Locked',
                    ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null, ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                ]);
            }
            $conn->commit();
            echo json_encode(['success' => true, 'agent' => $agent, 'lock_until' => $lockUntil]);
        } catch (PDOException $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'Unable to lock this agent. Please try again.']);
        }
        exit;
    }

    if ($_POST['action'] === 'save_booking_query_history') {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');

        $location = sanitize_input($_POST['location'] ?? '');
        $category = sanitize_input($_POST['category'] ?? 'All Categories');
        $checkIn = sanitize_input($_POST['check_in'] ?? '');
        $checkOut = sanitize_input($_POST['check_out'] ?? '');
        $nights = max(0, (int)($_POST['nights'] ?? 0));
        $adults = max(1, (int)($_POST['adults'] ?? 1));
        $children = max(0, (int)($_POST['children'] ?? 0));
        $rooms = max(1, (int)($_POST['rooms'] ?? 1));
        $budget = max(0, (float)($_POST['budget'] ?? 0));
        $queryType = strtolower(trim((string)($_POST['query_type'] ?? 'admin')));
        $queryType = $queryType === 'agent' ? 'agent' : 'admin';
        $agentPhone = sanitize_input($_POST['agent_phone'] ?? '');
        $hotelName = sanitize_input($_POST['hotel_name'] ?? '');
        $roomCategory = sanitize_input($_POST['room_category'] ?? '');
        $matchedHotels = json_decode($_POST['matched_hotels'] ?? '[]', true);
        $matchedHotels = is_array($matchedHotels) ? $matchedHotels : [];
        $queryNumber = strtoupper(trim((string)($_POST['query_number'] ?? '')));
        if (!preg_match('/^UV-\d{3,5}$/', $queryNumber)) {
            $queryNumber = 'UV-' . random_int(100, 99999);
        }

        if (empty($matchedHotels)) {
            echo json_encode(['success' => false, 'message' => 'Select at least one matching hotel before sending quotes']);
            exit;
        }

        $agent = null;
        $lockUntil = null;
        if ($queryType === 'agent') {
            if ($agentPhone === '') {
                echo json_encode(['success' => false, 'message' => 'Agent mobile number is required']);
                exit;
            }
            $agentStmt = $conn->prepare('SELECT id, name, phone, location, company_name, gst_number, email FROM agents_details WHERE phone = :phone AND status = "Active" LIMIT 1');
            $agentStmt->execute([':phone' => $agentPhone]);
            $agent = $agentStmt->fetch(PDO::FETCH_ASSOC);
            if (!$agent) {
                echo json_encode(['success' => false, 'message' => 'Agent mobile number is not registered.']);
                exit;
            }
        }

        if ($hotelName === '' && !empty($matchedHotels)) {
            $hotelName = sanitize_input((string)($matchedHotels[0]['name'] ?? ''));
        }
        if ($roomCategory === '' && !empty($matchedHotels)) {
            $roomCategory = sanitize_input((string)($matchedHotels[0]['room_name'] ?? ''));
        }

        $lines = [
            'Booking Query',
                $queryType === 'agent' ? 'Agent: ' . (($agent['name'] ?? '') . ' (' . ($agent['phone'] ?? '') . ')') : '',
            'Location: ' . ($location ?: 'Any'),
            'Hotel Category: ' . ($category ?: 'All Categories'),
            'Check-In: ' . ($checkIn ?: 'N/A'),
            'Check-Out: ' . ($checkOut ?: 'N/A'),
            'Nights: ' . $nights,
            'Adults: ' . $adults,
            'Children: ' . $children,
            'Rooms: ' . $rooms,
            'Budget per Night: ₹' . number_format($budget, 0),
            '',
        ];
        foreach ($matchedHotels as $index => $hotel) {
            $lines[] = ($index + 1) . '. ' . (string)($hotel['name'] ?? 'Hotel');
            $lines[] = 'Room Category: ' . (string)($hotel['room_name'] ?? 'N/A');
            $lines[] = 'Meal Plans: ' . implode(', ', array_keys((array)($hotel['prices'] ?? [])));
            $lines[] = 'Location: ' . (string)($hotel['location'] ?? $location);
            $lines[] = 'Price per Night: ₹' . number_format((float)($hotel['selected_price'] ?? $hotel['min_price'] ?? 0), 0);
            $lines[] = '';
        }

        try {
            $conn->beginTransaction();
            if ($agent) {
                $lockStmt = $conn->prepare('SELECT id, employee_id, employee_username, lock_until FROM agent_query_locks WHERE agent_id = :agent_id AND lock_until > NOW() AND status = "Locked" ORDER BY lock_until DESC LIMIT 1 FOR UPDATE');
                $lockStmt->execute([':agent_id' => (int)$agent['id']]);
                $activeLock = $lockStmt->fetch(PDO::FETCH_ASSOC);
                $ownsActiveLock = $activeLock && (((int)($activeLock['employee_id'] ?? 0) === (int)$user_id) || $activeLock['employee_username'] === $username);
                if ($activeLock && $user_role !== 'admin' && !$ownsActiveLock) {
                    $conn->rollBack();
                    echo json_encode(['success' => false, 'message' => 'This agent is currently locked with another employee and cannot be booked by you until the lock expires.', 'lock_until' => $activeLock['lock_until']]);
                    exit;
                }
                $lockUntil = $activeLock['lock_until'] ?? ($user_role === 'admin' ? date('Y-m-d H:i:s') : date('Y-m-d H:i:s', strtotime('+6 hours')));
                if (!$activeLock || !$ownsActiveLock) {
                    $newLockStmt = $conn->prepare('INSERT INTO agent_query_locks (agent_id, employee_id, employee_username, created_by_user_id, created_by_role, generated_at, locked_at, lock_until, query_text, hotel_name, room_category, check_in, check_out, adults, children, rooms, status, booking_status, ip_address, user_agent) VALUES (:agent_id, :employee_id, :employee_username, :created_by_user_id, :created_by_role, NOW(), NOW(), :lock_until, :query_text, :hotel_name, :room_category, :check_in, :check_out, :adults, :children, :rooms, :status, "Unbooked", :ip_address, :user_agent)');
                    $newLockStmt->execute([
                        ':agent_id' => (int)$agent['id'], ':employee_id' => $user_id, ':employee_username' => $username,
                        ':created_by_user_id' => $user_id, ':created_by_role' => $user_role, ':lock_until' => $lockUntil,
                        ':query_text' => implode("\n", $lines), ':hotel_name' => $hotelName ?: null, ':room_category' => $roomCategory ?: null,
                        ':check_in' => $checkIn ?: null, ':check_out' => $checkOut ?: null, ':adults' => $adults, ':children' => $children,
                        ':rooms' => $rooms, ':status' => $user_role === 'admin' ? 'Open' : 'Locked',
                        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null, ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                    ]);
                }
            }
            $saveStmt = $conn->prepare('INSERT INTO booking_query_history (
                created_by_user_id, created_by_username, created_by_role, query_type, agent_id, agent_name, agent_phone, lock_until, location, hotel_category,
                hotel_name, room_category, check_in, check_out, nights, adults, children, rooms, budget,
                query_text, matched_hotels_json, query_number
            ) VALUES (
                :user_id, :username, :role, :query_type, :agent_id, :agent_name, :agent_phone, :lock_until, :location, :category, :hotel_name, :room_category,
                :check_in, :check_out, :nights, :adults, :children, :rooms, :budget,
                :query_text, :matched_hotels_json, :query_number
            )');
            $saveStmt->execute([
                ':user_id' => $user_id,
                ':username' => $username,
                ':role' => $user_role === 'admin' ? 'admin' : 'employee',
                ':query_type' => $queryType,
                ':agent_id' => $agent['id'] ?? null,
                ':agent_name' => $agent['name'] ?? null,
                ':agent_phone' => $agent['phone'] ?? null,
                ':lock_until' => $lockUntil,
                ':location' => $location ?: null,
                ':category' => $category ?: 'All Categories',
                ':hotel_name' => $hotelName ?: null,
                ':room_category' => $roomCategory ?: null,
                ':check_in' => $checkIn ?: null,
                ':check_out' => $checkOut ?: null,
                ':nights' => $nights,
                ':adults' => $adults,
                ':children' => $children,
                ':rooms' => $rooms,
                ':budget' => $budget,
                ':query_text' => implode("\n", $lines),
                ':matched_hotels_json' => json_encode($matchedHotels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':query_number' => $queryNumber,
            ]);
            $conn->commit();
            echo json_encode(['success' => true, 'id' => (int)$conn->lastInsertId(), 'query_number' => $queryNumber]);
        } catch (Throwable $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            error_log('save_booking_query_history failed: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Unable to save query history']);
        }
        exit;
    }

    if ($_POST['action'] === 'get_booking_query_history') {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        try {
            $historySql = 'SELECT * FROM booking_query_history';
            $historyParams = [];
            if ($user_role !== 'admin') {
                $historySql .= ' WHERE created_by_user_id = :user_id OR created_by_username = :username';
                $historyParams = [':user_id' => $user_id, ':username' => $username];
            }
            $historySql .= ' ORDER BY generated_at DESC LIMIT 100';
            $historyStmt = $conn->prepare($historySql);
            $historyStmt->execute($historyParams);
            $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($history as &$historyRow) {
                $historyRow['matched_hotels'] = json_decode($historyRow['matched_hotels_json'] ?? '[]', true) ?: [];
            }
            unset($historyRow);
            echo json_encode(['success' => true, 'history' => $history]);
        } catch (PDOException $e) {
            echo json_encode(['success' => true, 'history' => []]);
        }
        exit;
    }

    if ($_POST['action'] === 'generate_query') {
        $agent_phone = sanitize_input($_POST['agentPhone'] ?? '');
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');

        if (!$agent_phone) {
            echo json_encode(['success' => false, 'message' => 'Please enter agent contact number']);
            exit;
        }

        try {
            // Check if agent exists
            $agentStmt = $conn->prepare("SELECT id, name FROM agents_details WHERE phone = :phone LIMIT 1");
            $agentStmt->execute([':phone' => $agent_phone]);
            $agent = $agentStmt->fetch(PDO::FETCH_ASSOC);

            if (!$agent) {
                // Agent not found
                echo json_encode(['success' => false, 'message' => 'Agent not found']);
                exit;
            }

            $agent_id = $agent['id'];

            // Check if agent is locked
            $lock = null;
            try {
                $lockStmt = $conn->prepare("SELECT employee_username, lock_until FROM agent_query_locks 
                                           WHERE agent_id = :agent_id AND lock_until > NOW() ORDER BY lock_until DESC LIMIT 1");
                $lockStmt->execute([':agent_id' => $agent_id]);
                $lock = $lockStmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                // Table may not exist, ignore
            }

            if ($lock) {
                $locked_by = $lock['employee_username'];
                $lock_until = $lock['lock_until'];
                if ($user_role !== 'admin' && $locked_by !== $username) {
                    echo json_encode(['success' => false, 'message' => 'This agent is currently locked with another employee and cannot be booked by you until the lock expires.', 'lock_until' => $lock_until]);
                    exit;
                }
            }

            // Generate query text
            $hotelsStmt = $conn->prepare("SELECT name AS hotel_name, CONCAT_WS(', ', NULLIF(city,''), NULLIF(state,'')) AS location, '' AS category, '' AS room_type, 0 AS weekday_price, 0 AS weekend_price, 0 AS gst FROM hotels WHERE status = 'active' ORDER BY name");
            $hotelsStmt->execute();
            $hotels = $hotelsStmt->fetchAll(PDO::FETCH_ASSOC);

            $query_text = "Hotel Listings Query for Agent: " . $agent['name'] . " (Phone: $agent_phone)\n\n";
            foreach ($hotels as $hotel) {
                $query_text .= "Hotel: " . $hotel['hotel_name'] . "\n";
                $query_text .= "Location: " . $hotel['location'] . "\n";
                $query_text .= "Category: " . $hotel['category'] . "\n";
                $query_text .= "Room Type: " . $hotel['room_type'] . "\n";
                $query_text .= "Weekday Price: ₹" . number_format($hotel['weekday_price'], 2) . "\n";
                $query_text .= "Weekend Price: ₹" . number_format($hotel['weekend_price'], 2) . "\n";
                $query_text .= "GST: " . $hotel['gst'] . "%\n\n";
            }

            // Save query and lock agent for 6 hours for non-admins; admin gets no active lock
            $lock_until = ($user_role === 'admin') ? date('Y-m-d H:i:s') : date('Y-m-d H:i:s', strtotime('+6 hours'));
            try {
                $insertStmt = $conn->prepare("INSERT INTO agent_query_locks (
                                               agent_id, employee_id, employee_username, created_by_user_id, created_by_role,
                                               lock_until, query_text, status, booking_status, ip_address, user_agent
                                             ) VALUES (
                                               :agent_id, :employee_id, :employee_username, :created_by_user_id, :created_by_role,
                                               :lock_until, :query_text, :status, :booking_status, :ip_address, :user_agent
                                             )");
                $insertStmt->execute([
                    ':agent_id' => $agent_id,
                    ':employee_id' => $user_id,
                    ':employee_username' => $username,
                    ':created_by_user_id' => $user_id,
                    ':created_by_role' => $user_role,
                    ':lock_until' => $lock_until,
                    ':query_text' => $query_text,
                    ':status' => $user_role === 'admin' ? 'Open' : 'Locked',
                    ':booking_status' => 'Unbooked',
                    ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                    ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                ]);
                $lastQueryId = (int) $conn->lastInsertId();
                record_activity_log($conn, 'query_generated', $lastQueryId, null, $username, $user_id, $user_role, 'Query generated and lock recorded');
            } catch (PDOException $e) {
                // Ignore if table not created
            }

            echo json_encode(['success' => true, 'query' => $query_text, 'agent_name' => $agent['name']]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error occurred. Please try again.']);
        }
        exit;
    }

    if ($_POST['action'] === 'get_query_history') {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');

        try {
            $historyStmt = $conn->prepare("SELECT aql.*, ad.name as agent_name, ad.phone as agent_phone 
                                          FROM agent_query_locks aql 
                                          JOIN agents_details ad ON aql.agent_id = ad.id 
                                          WHERE (aql.created_by_user_id = :user_id OR aql.employee_username = :username) 
                                          ORDER BY aql.generated_at DESC LIMIT 20");
            $historyStmt->execute([':user_id' => $user_id, ':username' => $username]);
            $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
            // Ensure hotel_name and room_category are populated when empty by parsing query_text
            foreach ($history as &$hrow) {
                if (empty($hrow['hotel_name']) && !empty($hrow['query_text'])) {
                    if (preg_match('/^Hotel:\s*(.+)$/mi', $hrow['query_text'], $m)) {
                        $hrow['hotel_name'] = trim($m[1]);
                    }
                }
                if (empty($hrow['room_category']) && !empty($hrow['query_text'])) {
                    // look for Room Category, Room Type or Category labels inside query_text
                    if (preg_match('/^(?:Room Category|Room Type|Category):\s*(.+)$/mi', $hrow['query_text'], $m2)) {
                        $hrow['room_category'] = trim($m2[1]);
                    }
                }
            }
            unset($hrow);
            echo json_encode(['success' => true, 'history' => $history]);
        } catch (PDOException $e) {
            echo json_encode(['success' => true, 'history' => []]);
        }
        exit;
    }

    if ($_POST['action'] === 'lock_agent_and_save_query') {
        $agent_phone = sanitize_input($_POST['agentPhone'] ?? '');
        $query_text = sanitize_input($_POST['queryText'] ?? '');
        $hotel_name = sanitize_input($_POST['hotelName'] ?? '');
        $room_category = sanitize_input($_POST['roomCategory'] ?? '');
        $check_in = sanitize_input($_POST['checkIn'] ?? '');
        $check_out = sanitize_input($_POST['checkOut'] ?? '');
        $adults = intval($_POST['adults'] ?? 1);
        $children = intval($_POST['children'] ?? 0);
        $rooms = intval($_POST['rooms'] ?? 1);
        $extra_bed = sanitize_input($_POST['extraBed'] ?? '');
        $meal_plan = sanitize_input($_POST['mealPlan'] ?? '');
        $total_amount = floatval($_POST['totalAmount'] ?? 0);
        $paid_amount = floatval($_POST['paidAmount'] ?? 0);
        $client_name = sanitize_input($_POST['clientName'] ?? '');
        $client_mobile = sanitize_input($_POST['clientMobile'] ?? '');
        $client_email = sanitize_input($_POST['clientEmail'] ?? '');
        $special_request = sanitize_input($_POST['specialRequest'] ?? '');

        if (!$agent_phone || !$query_text) {
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Agent phone and query text are required']);
            exit;
        }

        try {
            // Find agent by phone
            $agentStmt = $conn->prepare("SELECT id FROM agents_details WHERE phone = :phone AND status = 'Active'");
            $agentStmt->execute([':phone' => $agent_phone]);
            $agent = $agentStmt->fetch(PDO::FETCH_ASSOC);

            if (!$agent) {
                http_response_code(200);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Agent not found']);
                exit;
            }

            $agent_id = $agent['id'];
            // For admin, don't apply a 6-hour lock (set lock_until = now so it's not considered locked)
            $lock_until = ($user_role === 'admin') ? date('Y-m-d H:i:s') : date('Y-m-d H:i:s', strtotime('+6 hours'));

            // Append a new query history row and lock record
            $lockStmt = $conn->prepare("INSERT INTO agent_query_locks (
                                       agent_id, employee_id, employee_username, created_by_user_id, created_by_role,
                                       generated_at, lock_until, query_text,
                                       hotel_name, room_category, check_in, check_out, adults, children, rooms, extra_bed,
                                       meal_plan, total_amount, paid_amount, client_name, client_mobile, client_email, special_request,
                                       status, booking_status, ip_address, user_agent)
                                       VALUES (
                                       :agent_id, :employee_id, :employee_username, :created_by_user_id, :created_by_role,
                                       NOW(), :lock_until, :query_text,
                                       :hotel_name, :room_category, :check_in, :check_out, :adults, :children, :rooms, :extra_bed,
                                       :meal_plan, :total_amount, :paid_amount, :client_name, :client_mobile, :client_email, :special_request,
                                       :status, :booking_status, :ip_address, :user_agent)");

            $lockStmt->execute([
                ':agent_id' => $agent_id,
                ':employee_id' => $user_id,
                ':employee_username' => $username,
                ':created_by_user_id' => $user_id,
                ':created_by_role' => $user_role,
                ':lock_until' => $lock_until,
                ':query_text' => $query_text,
                ':hotel_name' => $hotel_name,
                ':room_category' => $room_category,
                ':check_in' => $check_in ?: null,
                ':check_out' => $check_out ?: null,
                ':adults' => $adults,
                ':children' => $children,
                ':rooms' => $rooms,
                ':extra_bed' => $extra_bed,
                ':meal_plan' => $meal_plan,
                ':total_amount' => $total_amount,
                ':paid_amount' => $paid_amount,
                ':client_name' => $client_name,
                ':client_mobile' => $client_mobile,
                ':client_email' => $client_email,
                ':special_request' => $special_request,
                ':status' => $user_role === 'admin' ? 'Open' : 'Locked',
                ':booking_status' => 'Unbooked',
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);
            $newQueryId = (int) $conn->lastInsertId();
            record_activity_log($conn, 'query_saved', $newQueryId, null, $username, $user_id, $user_role, 'Query saved and lock recorded');

            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'message' => 'Agent locked and query saved successfully', 'query_id' => $newQueryId]);
        } catch (PDOException $e) {
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Database error occurred. Please try again.']);
        }
        exit;
    }

    if ($_POST['action'] === 'get_query_by_id') {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');

        $query_id = intval($_POST['queryId'] ?? 0);
        if ($query_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid query ID']);
            exit;
        }

        try {
            $sql = "SELECT aql.*, ad.name AS agent_name, ad.phone AS agent_phone 
                    FROM agent_query_locks aql 
                    JOIN agents_details ad ON aql.agent_id = ad.id 
                    WHERE aql.id = :id";
            if ($user_role !== 'admin') {
                $sql .= " AND (aql.created_by_user_id = :user_id OR aql.employee_username = :username)";
            }

            $queryStmt = $conn->prepare($sql);
            $params = [':id' => $query_id];
            if ($user_role !== 'admin') {
                $params[':user_id'] = $user_id;
                $params[':username'] = $username;
            }
            $queryStmt->execute($params);
            $queryRow = $queryStmt->fetch(PDO::FETCH_ASSOC);

            if (!$queryRow) {
                echo json_encode(['success' => false, 'message' => 'Query not found']);
                exit;
            }

            $hotelName = trim((string)($queryRow['hotel_name'] ?? ''));
            $roomCategory = trim((string)($queryRow['room_category'] ?? ''));

            $matchedHotels = [];
            if ($hotelName !== '' && $roomCategory !== '') {
                $stmtHotels = $conn->prepare(
                    'SELECT h.name AS hotel_name, CONCAT_WS(", ", NULLIF(h.city, ""), NULLIF(h.state, "")) AS location,
                            "" AS category, hrc.name AS room_type,
                            COALESCE(MAX(CASE WHEN mp.code = "EP"  THEN rp.base_price END), 0) AS weekday_price,
                            COALESCE(MAX(CASE WHEN mp.code = "EP"  THEN rp.base_price END), 0) AS weekend_price,
                            0 AS gst
                     FROM hotels h
                     LEFT JOIN hotel_room_categories hrc ON hrc.hotel_id = h.id AND hrc.status = "active"
                     LEFT JOIN room_prices rp ON rp.room_category_id = hrc.id AND rp.rate_date IS NULL
                     LEFT JOIN meal_plans mp ON mp.id = rp.meal_plan_id
                     WHERE h.name = :hotel_name AND hrc.name = :room_category AND h.status = "active"
                     GROUP BY h.id, hrc.id'
                );
                $stmtHotels->execute([
                    ':hotel_name' => $hotelName,
                    ':room_category' => $roomCategory,
                ]);
                $matchedHotels = $stmtHotels->fetchAll(PDO::FETCH_ASSOC);
            }

            $checkIn = $queryRow['check_in'] ?? null;
            $checkOut = $queryRow['check_out'] ?? null;
            $nights = '';
            if (!empty($checkIn) && !empty($checkOut)) {
                $d1 = new DateTime($checkIn);
                $d2 = new DateTime($checkOut);
                $interval = $d1->diff($d2);
                $nights = (int)$interval->format('%r%a');
                if ($nights < 0) {
                    $nights = 0;
                }
            }

            echo json_encode([
                'success' => true,
                'query' => $queryRow['query_text'] ?? '',
                'agent_name' => $queryRow['agent_name'] ?? '',
                'agent_phone' => $queryRow['agent_phone'] ?? '',
                'generated_at' => $queryRow['generated_at'] ?? '',
                'lock_until' => $queryRow['lock_until'] ?? '',
                'hotel_name' => $hotelName,
                'room_category' => $roomCategory,
                'check_in' => $checkIn ?: '',
                'check_out' => $checkOut ?: '',
                'nights' => $nights,
                'adults' => $queryRow['adults'] ?? 1,
                'children' => $queryRow['children'] ?? 0,
                'rooms' => $queryRow['rooms'] ?? 1,
                'extra_bed' => $queryRow['extra_bed'] ?? '',
                'meal_plan' => $queryRow['meal_plan'] ?? '',
                'total_amount' => $queryRow['total_amount'] ?? 0,
                'paid_amount' => $queryRow['paid_amount'] ?? 0,
                'client_name' => $queryRow['client_name'] ?? '',
                'client_mobile' => $queryRow['client_mobile'] ?? '',
                'client_email' => $queryRow['client_email'] ?? '',
                'special_request' => $queryRow['special_request'] ?? '',
                'hotels' => $matchedHotels
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error occurred. Please try again.']);
        }
        exit;
    }
}

// Fetch data for dropdowns
$hotels = $conn->query("SELECT id, hotel_code, name AS hotel_name, city, state, status, star_rating, property_category FROM hotels WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$agents = $conn->query("SELECT id, name, email FROM agents_details WHERE status = 'Active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$roomTypeMap = [];
if ($hotels) {
    $hotelIds = array_map(static function ($row) {
        return (int)($row['id'] ?? 0);
    }, $hotels);
    $hotelIds = array_values(array_filter($hotelIds, static function ($id) {
        return $id > 0;
    }));

    if ($hotelIds) {
        $placeholders = implode(',', array_fill(0, count($hotelIds), '?'));
        $roomStmt = $conn->prepare('SELECT hotel_id, name FROM hotel_room_categories WHERE hotel_id IN (' . $placeholders . ') AND status = "active" ORDER BY id ASC');
        $roomStmt->execute($hotelIds);
        foreach ($roomStmt->fetchAll(PDO::FETCH_ASSOC) as $roomRow) {
            $hid = (int)($roomRow['hotel_id'] ?? 0);
            $name = trim((string)($roomRow['name'] ?? ''));
            if ($hid <= 0 || $name === '') {
                continue;
            }
            if (!isset($roomTypeMap[$hid])) {
                $roomTypeMap[$hid] = [];
            }
            $roomTypeMap[$hid][] = $name;
        }
    }
}

$hotel_catalog = [];
foreach ($hotels as $hotel) {
    $hotelName = trim((string)($hotel['hotel_name'] ?? ''));
    $hotelCity = trim((string)($hotel['city'] ?? ''));
    $hotelState = trim((string)($hotel['state'] ?? ''));
    $hotelLocation = trim($hotelCity . ($hotelState !== '' ? ', ' . $hotelState : ''));
    $label = $hotelLocation !== '' ? ($hotelName . ', ' . $hotelLocation) : $hotelName;
    $roomTypes = $roomTypeMap[(int)($hotel['id'] ?? 0)] ?? [];

    $hotel_catalog[$label] = [
        'id' => (int)($hotel['id'] ?? 0),
        'name' => $hotelName,
        'location' => $hotelLocation,
        'category' => (string)($hotel['property_category'] ?: (((int)($hotel['star_rating'] ?? 0)) > 0 ? (((int)$hotel['star_rating']) . ' Star') : '')),
        'hotel_code' => (string)($hotel['hotel_code'] ?? ''),
        'roomTypes' => $roomTypes,
    ];
}

// Location + category options for the Employee Booking Query filter form (admin hotel data is the source of truth).
$hotel_locations = array_values(array_unique(array_filter(array_map(static function ($hotel) {
    return trim((string)($hotel['city'] ?? ''));
}, $hotels))));
sort($hotel_locations);
$hotel_category_options = array_values(array_unique(array_merge(
    hotel_category_options(),
    array_filter(array_map(static function ($hotel) {
        return trim((string)($hotel['property_category'] ?? ''));
    }, $hotels))
)));

// Fetch employee's own bookings
 $my_bookings_query = "SELECT bd.*, COALESCE(NULLIF(bd.hotel_name_snapshot, ''), h.name) AS hotel_name, a.name as agent_name, COALESCE(a.company_name, '') as agent_company, a.location as agent_location, a.phone as agent_phone 
                      FROM bookings_details bd
                      LEFT JOIN hotels h ON bd.hotel_listing_id = h.id
                      LEFT JOIN agents_details a ON bd.agent_id = a.id
                      WHERE bd.created_by = :username
                      ORDER BY bd.created_at DESC
                      LIMIT 20";
$stmt = $conn->prepare($my_bookings_query);
$stmt->execute([':username' => $username]);
$my_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$employeeMetrics = get_employee_live_metrics($conn, $username);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard | Uttarakhand Ventures</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/ui-consistency.css" rel="stylesheet">
    <link href="/assets/css/ui-modern.css" rel="stylesheet">

    <style>
    :root {
        --primary-bg: #f8fafc;
        --sidebar-bg: #0f172a;
        --theme-color: #4f46e5;
        --panel: #ffffff;
        --muted: #94a3b8;
        --accent: #06b6d4;
        --success: #10b981;
        --warning: #f59e0b;
        --danger: #ef4444;
        --text: #0f172a;
        --text-secondary: #475569;
        --border: #e2e8f0;
        --primary-50: #eef2ff;
        --primary-100: #e0e7ff;
        --primary-200: #c7d2fe;
    }

    body {
        font-family: 'Inter','Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: var(--primary-bg);
        color: var(--text);
        margin: 0;
    }

    .btn,
    .form-control,
    .form-select,
    .dropdown-menu,
    .table {
        font-size: .84rem;
    }

    .btn {
        padding: .4rem .72rem;
    }

    .sidebar {
        width: 248px;
        min-height: 100vh;
        background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
        position: fixed;
        left: 0;
        top: 0;
        border-right: 1px solid rgba(255, 255, 255, 0.07);
        z-index: 1000;
    }

    .sidebar-brand {
        color: #fff;
        font-size: 1.45rem;
        font-weight: 700;
        padding: 24px 22px 18px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.07);
    }

    .sidebar-brand .brand-icon {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, var(--theme-color), var(--accent));
        font-size: 16px;
        color: #fff;
    }

    .sidebar .nav {
        padding: 16px 14px;
        gap: 8px;
    }

    .sidebar .nav-link {
        color: var(--muted);
        border-radius: 12px;
        padding: 12px 14px;
        font-weight: 500;
        font-size: .95rem;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: .25s ease;
    }

    .sidebar .nav-link:hover,
    .sidebar .nav-link.active {
        color: #fff;
        background: rgba(255, 255, 255, .08);
    }

    .main-wrapper {
        margin-left: 248px;
        min-height: 100vh;
    }

    .top-header {
        background: var(--panel);
        padding: 12px 18px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid var(--border);
        position: sticky;
        top: 0;
        z-index: 20;
        backdrop-filter: blur(10px);
        background: rgba(255, 255, 255, 0.95);
    }

    .section-wrap {
        padding: 18px;
    }

    .profile-btn {
        background: #0d9488;
        border: 1px solid #0d9488;
        padding: 6px 16px 6px 6px;
        border-radius: 50px;
        transition: all 0.2s ease;
        color: #ffffff;
    }

    .profile-btn:hover,
    .profile-btn[aria-expanded="true"] {
        background: #0f766e;
        border-color: #0f766e;
        color: #ffffff;
    }

    .profile-img {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #4f46e5;
        color: #ffffff;
        font-weight: 700;
        font-size: 0.88rem;
    }

    .dropdown-menu-custom {
        border: none;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        border-radius: 12px;
        padding: 10px;
        min-width: 220px;
        margin-top: 15px !important;
    }

    .dropdown-menu-custom .dropdown-item {
        border-radius: 8px;
        padding: 10px 15px;
        font-weight: 500;
        color: #4a5568;
        transition: all 0.2s;
    }

    .dropdown-menu-custom .dropdown-item:hover {
        background-color: var(--primary-50);
        color: var(--theme-color);
    }

    .welcome-section {
        background: var(--panel);
        border-radius: 18px;
        border: 1px solid var(--border);
        padding: 18px;
        position: relative;
        overflow: hidden;
        margin-bottom: 18px;
    }

    .welcome-title {
        font-weight: 700;
        color: var(--text);
        font-size: 1.6rem;
        letter-spacing: -0.5px;
    }

    .welcome-vector {
        position: absolute;
        right: 18px;
        bottom: -10px;
        width: 170px;
        opacity: .38;
        pointer-events: none;
    }

    .action-card {
        background: #ffffff;
        border: 1px solid var(--border);
        border-radius: 20px;
        padding: 24px 18px;
        height: 100%;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
        position: relative;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
        text-decoration: none;
        display: block;
        color: inherit;
    }

    .action-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
        border-color: var(--primary-200);
    }

    .icon-wrapper {
        width: 58px;
        height: 58px;
        border-radius: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.45rem;
        margin-bottom: 18px;
        transition: transform 0.3s ease;
    }

    .action-card:hover .icon-wrapper {
        transform: scale(1.1);
    }

    .card-agents .icon-wrapper {
        background: var(--primary-50);
        color: var(--theme-color);
    }

    .card-booking .icon-wrapper {
        background: #d1fae5;
        color: var(--success);
    }

    .card-history .icon-wrapper {
        background: #cffafe;
        color: var(--accent);
    }

    .card-title {
        font-weight: 700;
        font-size: 1.02rem;
        color: var(--text);
        margin-bottom: 12px;
    }

    .card-text {
        color: var(--text-secondary);
        font-size: 0.88rem;
        line-height: 1.5;
        margin-bottom: 0;
    }

    .arrow-icon {
        position: absolute;
        bottom: 35px;
        right: 25px;
        font-size: 1.5rem;
        color: var(--text-secondary);
        transition: all 0.3s ease;
    }

    .action-card:hover .arrow-icon {
        color: var(--theme-color);
        transform: translateX(5px);
    }

    .form-control:focus,
    .form-select:focus {
        border-color: var(--theme-color);
        box-shadow: 0 0 0 0.25rem rgba(79, 70, 229, 0.15);
    }

    .view-section {
        display: none;
        opacity: 0;
        transition: opacity 0.4s ease-in-out;
    }

    .view-section.active {
        display: block;
        opacity: 1;
    }

    .form-card {
        background: #ffffff;
        border: 1px solid rgba(0, 0, 0, 0.04);
        border-radius: 20px;
        padding: 24px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.05);
        margin: 0 auto;
        width: 100%;
    }

    .form-hero {
        border: 1px solid var(--border);
        border-radius: 16px;
        background: linear-gradient(135deg, var(--primary-50) 0%, #ecfeff 100%);
        padding: 14px;
        margin-bottom: 16px;
        position: relative;
        overflow: hidden;
    }

    .form-hero svg {
        position: absolute;
        right: -8px;
        bottom: -8px;
        width: 110px;
        opacity: .5;
    }

    .field-block {
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 12px;
        background: #fff;
        margin-bottom: 12px;
    }

    .form-card.compact {
        max-width: none;
    }

    .table-custom th {
        text-transform: uppercase;
        font-size: 0.68rem;
        letter-spacing: 0.5px;
        color: var(--text-secondary);
        font-weight: 600;
        border-bottom: 2px solid var(--border);
        padding: 15px;
    }

    .table-custom td {
        vertical-align: middle;
        padding: 11px;
        border-bottom: 1px solid var(--border);
    }

    .badge-created {
        background: var(--primary-50);
        color: var(--theme-color);
        font-weight: 600;
    }

    .analytics-card {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 11px;
        height: 100%;
    }

    .analytics-kpi-label {
        color: var(--text-secondary);
        font-size: .75rem;
        margin-bottom: 2px;
    }

    .chart-tile {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 11px;
        height: 100%;
    }

    .chart-title {
        font-size: .9rem;
        font-weight: 700;
        margin-bottom: 6px;
    }

    .process-hero {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        padding: 16px 18px;
        border-radius: 16px;
        background: linear-gradient(135deg, var(--primary-50) 0%, #ecfeff 100%);
        border: 1px solid var(--border);
        margin-bottom: 16px;
        position: relative;
        overflow: hidden;
    }

    .process-hero svg {
        width: 110px;
        max-width: 28vw;
        height: auto;
        opacity: .5;
        flex: 0 0 auto;
    }

    .agent-lookup {
        background: #f8fafc;
        border: 1px solid #e4e8f4;
        border-radius: 18px;
        padding: 18px;
        margin-bottom: 22px;
    }

    .agent-summary {
        border-left: 4px solid #38a169;
        background: #f4fbf7;
    }

    .hotel-search-wrap {
        position: relative;
    }

    .hotel-suggestion-menu {
        position: absolute;
        left: 0;
        right: 0;
        top: calc(100% + 6px);
        z-index: 50;
        background: #ffffff;
        border: 1px solid var(--border);
        border-radius: 12px;
        box-shadow: 0 16px 34px rgba(14, 24, 54, 0.12);
        max-height: 240px;
        overflow-y: auto;
        display: none;
    }

    .hotel-suggestion-item {
        padding: 10px 12px;
        border-bottom: 1px solid var(--border);
        cursor: pointer;
        transition: background-color 0.15s ease;
    }

    .hotel-suggestion-item:last-child {
        border-bottom: none;
    }

    .hotel-suggestion-item:hover,
    .hotel-suggestion-item.active {
        background: var(--primary-50);
    }

    .hotel-suggestion-title {
        font-weight: 600;
        color: var(--text);
        font-size: 0.92rem;
    }

    .hotel-suggestion-sub {
        font-size: 0.78rem;
        color: var(--text-secondary);
        margin-top: 2px;
    }

    .property-finder-card {
        background: linear-gradient(180deg, #fbfcff 0%, #f5f7ff 100%);
        border: 1px solid var(--border);
        border-radius: 18px;
        padding: 22px;
    }

    .pf-step-tag {
        display: inline-block;
        background: #4f46e5;
        color: #fff;
        font-size: 0.62rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        padding: 2px 8px;
        border-radius: 20px;
        margin-right: 6px;
        vertical-align: middle;
    }

    .pf-step-divider {
        font-size: 0.8rem;
        color: var(--text-secondary);
        background: #f8fafc;
        border: 1px dashed var(--border);
        border-radius: 12px;
        padding: 10px 14px;
        margin-bottom: 16px;
    }

    .pf-share-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
    }

    .pf-selected-badge {
        display: inline-flex;
        align-items: center;
        background: #eef2ff;
        color: #4338ca;
        font-weight: 700;
        font-size: 0.86rem;
        padding: 5px 12px;
        border-radius: 20px;
    }

    .pf-best-match-badge {
        position: absolute;
        top: -9px;
        right: 14px;
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: #fff;
        font-size: 0.66rem;
        font-weight: 800;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        padding: 3px 10px;
        border-radius: 20px;
        box-shadow: 0 4px 10px rgba(217, 119, 6, 0.35);
    }

    .pf-sort-btn.active {
        background: #4f46e5;
        border-color: #4f46e5;
        color: #fff;
    }

    .pf-results-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 16px;
    }

    .pf-property-card {
        position: relative;
        background: #fff;
        border: 1.5px solid var(--border);
        border-radius: 16px;
        padding: 16px;
        transition: all 0.18s ease;
        cursor: pointer;
    }

    .pf-property-card:hover {
        border-color: #c7d2fe;
        box-shadow: 0 8px 24px rgba(79, 70, 229, 0.1);
        transform: translateY(-1px);
    }

    .pf-property-card.selected {
        border-color: #4f46e5;
        background: linear-gradient(180deg, #f5f6ff 0%, #ffffff 100%);
        box-shadow: 0 8px 24px rgba(79, 70, 229, 0.14);
    }

    .pf-property-card-hdr {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 10px;
    }

    .pf-property-name {
        font-weight: 700;
        color: var(--text);
        font-size: 0.98rem;
        line-height: 1.25;
    }

    .pf-property-sub {
        font-size: 0.78rem;
        color: var(--text-secondary);
        margin-top: 2px;
    }

    .pf-property-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin: 10px 0;
        font-size: 0.76rem;
    }

    .pf-meta-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: #f1f5f9;
        color: var(--text-secondary);
        padding: 4px 10px;
        border-radius: 20px;
        font-weight: 600;
    }

    .pf-meta-chip.pf-budget-chip {
        background: #dcfce7;
        color: #14532d;
    }

    .pf-room-list {
        border-top: 1px dashed var(--border);
        padding-top: 10px;
        margin-top: 4px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        max-height: 150px;
        overflow-y: auto;
    }

    .pf-room-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 6px;
        font-size: 0.76rem;
        background: #fafbff;
        border: 1px solid #eef1f8;
        border-radius: 10px;
        padding: 7px 10px;
    }

    .pf-room-row .pf-room-name {
        font-weight: 600;
        color: var(--text);
    }

    .pf-room-row .pf-room-tags {
        color: var(--text-secondary);
        font-size: 0.72rem;
    }

    .pf-room-row .pf-room-price {
        font-weight: 700;
        color: #4f46e5;
        white-space: nowrap;
    }

    .pf-select-checkbox {
        width: 20px;
        height: 20px;
        cursor: pointer;
        flex-shrink: 0;
    }

    .summary-chip {
        background: linear-gradient(180deg, #fff, #f8faff);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 14px 16px;
    }

    .summary-chip .label {
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--text-secondary);
        font-weight: 700;
    }

    .summary-chip .value {
        font-size: 1.25rem;
        font-weight: 800;
        color: var(--text);
    }

    .booking-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 18px;
    }

    .booking-search {
        max-width: 360px;
        width: 100%;
    }

    .bookings-table tbody tr {
        transition: background 0.2s ease;
    }

    .bookings-table tbody tr:hover {
        background: var(--primary-50);
    }

    .copy-trigger {
        white-space: nowrap;
    }

    .mobile-menu-btn {
        display: none;
    }

    .sidebar-close-btn {
        display: none;
    }

    .sidebar-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(7, 13, 35, 0.45);
        backdrop-filter: blur(2px);
        z-index: 999;
        display: none;
    }

    @media (max-width: 992px) {
        .mobile-menu-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: min(82vw, 280px);
            height: 100vh;
            min-height: 100vh;
            transform: translateX(-100%);
            transition: transform .25s ease;
            z-index: 1000;
        }

        .sidebar.open {
            transform: translateX(0);
        }

        .sidebar .nav {
            flex-direction: column;
            overflow-y: auto;
            gap: 8px;
            padding: 10px;
        }

        .sidebar .nav-link {
            white-space: normal;
            padding: 10px 12px;
            font-size: .86rem;
        }

        .sidebar-close-btn {
            display: inline-flex;
            width: 32px;
            height: 32px;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, .2);
            color: #fff;
            background: rgba(255, 255, 255, .08);
        }

        .sidebar-backdrop.show {
            display: block;
        }

        .main-wrapper {
            margin-left: 0;
        }
    }
    </style>
</head>

<body>

    <div class="toast-container position-fixed top-0 end-0 p-4" style="z-index: 1060;">
        <div id="successToast" class="toast align-items-center text-bg-success border-0 shadow-lg" role="alert"
            aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body fw-medium px-3 py-3" style="font-size: 1.05rem;" id="toastMessage">
                    <i class="bi bi-check-circle-fill me-2"></i> Success!
                </div>
                <button type="button" class="btn-close btn-close-white me-3 m-auto" data-bs-dismiss="toast"
                    aria-label="Close"></button>
            </div>
        </div>

        <div id="errorToast" class="toast align-items-center text-bg-danger border-0 shadow-lg mt-2" role="alert"
            aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body fw-medium px-3 py-3" style="font-size: 1.05rem;" id="errorToastMessage">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> Error!
                </div>
                <button type="button" class="btn-close btn-close-white me-3 m-auto" data-bs-dismiss="toast"
                    aria-label="Close"></button>
            </div>
        </div>
    </div>

    <div class="sidebar">
        <div class="sidebar-brand d-flex align-items-center justify-content-between">
            <span class="d-flex align-items-center gap-2">
                <span class="brand-icon"><i class="bi bi-buildings"></i></span>
                Uttarakhand Ventures
            </span>
            <button type="button" class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close menu">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link active" data-target="dashboard-view" href="#" onclick="showSection('dashboard-view'); return false;"><i class="bi bi-grid-1x2"></i> Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" data-target="search-agent-view" href="#" onclick="showSection('search-agent-view'); return false;"><i class="bi bi-person-badge"></i>Search Agents</a></li>
            <li class="nav-item"><a class="nav-link" data-target="add-agent-view" href="#" onclick="showSection('add-agent-view'); return false;"><i class="bi bi-person-plus"></i> Add Agent</a></li>
            <li class="nav-item"><a class="nav-link" data-target="booking-query-view" href="#" onclick="showSection('booking-query-view'); return false;"><i class="bi bi-chat-dots"></i> Booking Query</a></li>
            <li class="nav-item"><a class="nav-link" data-target="query-history-view" href="#" onclick="showSection('query-history-view'); return false;"><i class="bi bi-clock-history"></i> Query History</a></li>
            <li class="nav-item"><a class="nav-link" data-target="#" href="#" onclick="showSection('my-bookings-view'); return false;"><i class="bi bi-calendar-check"></i> Bookings(soon)</a></li>
            <li class="nav-item"><a class="nav-link" data-target="#" href="#" onclick="showSection('create-booking-view'); return false;"><i class="bi bi-calendar-plus"></i> Create Booking(soon)</a></li>
            
        </ul>
    </div>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <div class="main-wrapper">
        <header class="top-header">
            <button class="btn btn-light mobile-menu-btn" type="button" id="mobileMenuBtn" aria-label="Open menu"><i
                    class="bi bi-list fs-4"></i></button>
            <div class="text-muted fw-semibold"><i class="bi bi-person-badge me-2"></i>Employee Workspace</div>
            <div class="d-flex align-items-center">
                <div class="dropdown">
                    <a class="d-flex align-items-center text-decoration-none dropdown-toggle profile-btn" href="#"
                        role="button" data-bs-toggle="dropdown" aria-expanded="false" style="color: inherit;">
                        <span class="profile-img me-2" aria-hidden="true"><?php echo htmlspecialchars($user_initial, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span
                            class="fw-semibold me-1 d-none d-sm-block"><?php echo htmlspecialchars($username); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-custom">
                        <li class="px-3 py-2 mb-2">
                            <span class="d-block fw-bold text-dark"><?php echo htmlspecialchars($username); ?></span>
                            <span class="d-block text-muted" style="font-size: 0.8rem;">Employee</span>
                        </li>
                        <li>
                            <hr class="dropdown-divider mb-2">
                        </li>
                        <li>
                            <a class="dropdown-item" href="#" onclick="showSection('dashboard-view'); return false;">
                                <i class="bi bi-person-circle me-2"></i> Profile
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="#" onclick="showSection('my-bookings-view'); return false;">
                                <i class="bi bi-clock-history me-2"></i> Booking History
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="/export-bookings-excel.php">
                                <i class="bi bi-file-earmark-spreadsheet me-2 text-success"></i> Download Excel
                            </a>
                        </li>
                        <li>
                            <hr class="dropdown-divider mb-2">
                        </li>
                        <li>
                            <a class="dropdown-item text-danger fw-bold" href="logout.php">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="section-wrap">

            <div id="welcomeBanner" class="welcome-section">
                <h1 class="welcome-title mb-2">Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
                <p class="text-muted mb-2">Manage bookings and agents with a unified admin-style workspace.</p>
                <div class="d-flex gap-3 flex-wrap text-muted small">
                    <span><i class="bi bi-building me-1"></i> Active Hotels: <?php echo count($hotels); ?></span>
                    <span><i class="bi bi-people me-1"></i> Active Agents: <?php echo count($agents); ?></span>
                    <span><i class="bi bi-journal-check me-1"></i> My Bookings:
                        <?php echo count($my_bookings); ?></span>
                </div>
                <svg class="welcome-vector" viewBox="0 0 220 160" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <rect x="22" y="60" width="170" height="80" rx="14" fill="#4f46e5" opacity=".18" />
                    <rect x="42" y="36" width="38" height="94" rx="8" fill="#4f46e5" opacity=".62" />
                    <rect x="92" y="48" width="38" height="82" rx="8" fill="#10b981" opacity=".58" />
                    <rect x="142" y="24" width="38" height="106" rx="8" fill="#06b6d4" opacity=".58" />
                </svg>
            </div>

            <!-- Dashboard View -->
            <div id="dashboard-view" class="view-section active">
                <div class="row g-4 justify-content-center">

                    <div class="col-lg-4 col-md-6">
                        <a href="#" class="action-card card-agents"
                            onclick="showSection('add-agent-view'); return false;">
                            <div class="icon-wrapper">
                                <i class="bi bi-person-plus-fill"></i>
                            </div>
                            <h3 class="card-title">Add New Agent</h3>
                            <p class="card-text">Create and register new agents in the system.</p>
                            <i class="bi bi-arrow-right arrow-icon"></i>
                        </a>
                    </div>

                    <div class="col-lg-4 col-md-6">
                        <a href="#" class="action-card card-booking"
                            onclick="showSection('create-booking-view'); return false;">
                            <div class="icon-wrapper">
                                <i class="bi bi-calendar-check-fill"></i>
                            </div>
                            <h3 class="card-title">Create Booking</h3>
                            <p class="card-text">Process new client reservations and bookings.</p>
                            <i class="bi bi-arrow-right arrow-icon"></i>
                        </a>
                    </div>

                    <div class="col-lg-4 col-md-6">
                        <a href="#" class="action-card card-history"
                            onclick="showSection('search-agent-view'); return false;">
                            <div class="icon-wrapper">
                                <i class="bi bi-search"></i>
                            </div>
                            <h3 class="card-title">Search by Mobile</h3>
                            <p class="card-text">Find agents by mobile number and view their bookings.</p>
                            <i class="bi bi-arrow-right arrow-icon"></i>
                        </a>
                    </div>

                    <div class="col-lg-4 col-md-6">
                        <a href="#" class="action-card card-history"
                            onclick="showSection('my-bookings-view'); return false;">
                            <div class="icon-wrapper">
                                <i class="bi bi-clock-history"></i>
                            </div>
                            <h3 class="card-title">My Bookings</h3>
                            <p class="card-text">View all your created bookings and details.</p>
                            <i class="bi bi-arrow-right arrow-icon"></i>
                        </a>
                    </div>

                    <div class="col-lg-4 col-md-6">
                        <a href="#" class="action-card card-history"
                            onclick="showSection('booking-query-view'); return false;">
                            <div class="icon-wrapper">
                                <i class="bi bi-chat-quote"></i>
                            </div>
                            <h3 class="card-title">Booking Query</h3>
                            <p class="card-text">Generate hotel queries for agents and send via WhatsApp.</p>
                            <i class="bi bi-arrow-right arrow-icon"></i>
                        </a>
                    </div>

                </div>

                <div class="row g-2 mt-2">
                    <div class="col-xl-2 col-md-4 col-6">
                        <div class="analytics-card">
                            <div class="analytics-kpi-label">Total Bookings</div>
                            <div class="analytics-kpi-value" id="empKpiTotalBookings">
                                <?php echo number_format((int)($employeeMetrics['kpi']['totalBookings'] ?? 0)); ?></div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6">
                        <div class="analytics-card">
                            <div class="analytics-kpi-label">Today</div>
                            <div class="analytics-kpi-value" id="empKpiTodayBookings">
                                <?php echo number_format((int)($employeeMetrics['kpi']['todayBookings'] ?? 0)); ?></div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6">
                        <div class="analytics-card">
                            <div class="analytics-kpi-label">Total Amount</div>
                            <div class="analytics-kpi-value" id="empKpiTotalAmount">
                                ₹<?php echo number_format((float)($employeeMetrics['kpi']['totalAmount'] ?? 0), 0); ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6">
                        <div class="analytics-card">
                            <div class="analytics-kpi-label">Received</div>
                            <div class="analytics-kpi-value text-success" id="empKpiReceived">
                                ₹<?php echo number_format((float)($employeeMetrics['kpi']['received'] ?? 0), 0); ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6">
                        <div class="analytics-card">
                            <div class="analytics-kpi-label">Due</div>
                            <div class="analytics-kpi-value text-danger" id="empKpiDue">
                                ₹<?php echo number_format((float)($employeeMetrics['kpi']['due'] ?? 0), 0); ?></div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6">
                        <div class="analytics-card">
                            <div class="analytics-kpi-label">Pending/Partial</div>
                            <div class="analytics-kpi-value" id="empKpiPendingPayment">
                                <?php echo number_format((int)($employeeMetrics['kpi']['pendingPayment'] ?? 0)); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-2 mt-1">
                    <div class="col-xl-4">
                        <div class="chart-tile">
                            <div class="chart-title">Weekly Bookings Trend</div><canvas id="empWeeklyChart"
                                class="chart-canvas" height="150"></canvas>
                        </div>
                    </div>
                    <div class="col-xl-4">
                        <div class="chart-tile">
                            <div class="chart-title">Booking Status Split</div><canvas id="empStatusChart"
                                class="chart-canvas" height="150"></canvas>
                        </div>
                    </div>
                    <div class="col-xl-4">
                        <div class="chart-tile">
                            <div class="chart-title">Payment Status Split</div><canvas id="empPaymentChart"
                                class="chart-canvas" height="150"></canvas>
                        </div>
                    </div>
                    <div class="col-xl-6">
                        <div class="chart-tile">
                            <div class="chart-title">Monthly Booking Trend</div><canvas id="empMonthlyChart"
                                class="chart-canvas short" height="130"></canvas>
                        </div>
                    </div>
                    <div class="col-xl-6">
                        <div class="chart-tile">
                            <div class="chart-title">Booking Source Mix</div><canvas id="empSourceChart"
                                class="chart-canvas short" height="130"></canvas>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="chart-tile">
                            <div class="chart-title">Top Hotels (My Bookings)</div><canvas id="empHotelChart"
                                class="chart-canvas mini" height="112"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add Agent View -->
            <div id="add-agent-view" class="view-section">
                <div class="form-card compact">
                    <div class="text-center mb-4 pb-3 border-bottom">
                        <div class="icon-wrapper mx-auto"
                            style="background: #ebf8ff; color: #3182ce; width: 60px; height: 60px; font-size: 1.5rem; margin-bottom: 15px;">
                            <i class="bi bi-person-plus-fill"></i>
                        </div>
                        <h3 class="fw-bold text-dark mb-1">Create New Agent</h3>
                        <p class="text-muted fs-7 mb-0">Register a new agent into the system</p>
                    </div>

                    <form id="createAgentForm">
                        <div class="mb-3">
                            <label for="agentName" class="form-label text-muted fw-medium fs-7">Agent Name</label>
                            <input type="text" class="form-control py-2" id="agentName" placeholder="Enter full name"
                                required>
                        </div>
                        <div class="mb-3">
                            <label for="agentCompany" class="form-label text-muted fw-medium fs-7">Company Name</label>
                            <input type="text" class="form-control py-2" id="agentCompany"
                                placeholder="Enter company name" required>
                        </div>
                        <div class="mb-3">
                            <label for="agentGstNumber" class="form-label text-muted fw-medium fs-7">GST Number</label>
                            <input type="text" class="form-control py-2" id="agentGstNumber" placeholder="Enter GST number (optional)">
                        </div>
                        <div class="mb-3">
                            <label for="agentEmail" class="form-label text-muted fw-medium fs-7">Email Address</label>
                            <input type="email" class="form-control py-2" id="agentEmail" placeholder="Enter email"
                                required>
                        </div>
                        <div class="mb-3">
                            <label for="agentPhone" class="form-label text-muted fw-medium fs-7">Contact Number</label>
                            <input type="tel" class="form-control py-2" id="agentPhone"
                                placeholder="Enter 10-digit phone number" pattern="[0-9]{10}" maxlength="10" required>
                        </div>
                        <div class="mb-4">
                            <label for="agentLocation" class="form-label text-muted fw-medium fs-7">Location /
                                Area</label>
                            <input type="text" class="form-control py-2" id="agentLocation" placeholder="Enter location"
                                required>
                        </div>
                        <button type="submit" class="btn w-100 py-3 rounded-3 fw-bold shadow-sm"
                            style="background-color: var(--theme-color); color: white; border: none; font-size: 1.1rem;">
                            Create Agent Profile
                        </button>
                    </form>
                </div>
            </div>

            <!-- Create Booking View -->
            <div id="create-booking-view" class="view-section">
                <div class="form-card mb-5">
                    <div class="process-hero">
                        <div class="d-flex align-items-center gap-3">
                            <div class="icon-wrapper mb-0"
                                style="background: #f0fff4; color: #38a169; width: 52px; height: 52px; font-size: 1.25rem;">
                                <i class="bi bi-calendar-check-fill"></i>
                            </div>
                            <div>
                                <h3 class="fw-bold text-dark mb-1" style="font-size:1.1rem;">Process Booking</h3>
                                <p class="text-muted mb-0">Capture complete details and sync instantly with admin panel.
                                </p>
                            </div>
                        </div>
                        <svg viewBox="0 0 120 90" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <rect x="10" y="40" width="88" height="36" rx="8" fill="#4f46e5" opacity=".18" />
                            <rect x="20" y="24" width="18" height="46" rx="4" fill="#4f46e5" opacity=".55" />
                            <rect x="44" y="30" width="18" height="40" rx="4" fill="#10b981" opacity=".55" />
                            <rect x="68" y="18" width="18" height="52" rx="4" fill="#06b6d4" opacity=".55" />
                        </svg>
                    </div>

                    <div class="agent-lookup">
                        <div class="row g-3 align-items-end">
                            <div class="col-lg-8">
                                <label class="form-label text-muted fw-medium fs-7">Search Agent Number</label>
                                <div class="input-group input-group-lg shadow-sm">
                                    <span class="input-group-text bg-light border-end-0"><i
                                            class="bi bi-search text-muted"></i></span>
                                    <input type="tel" class="form-control border-start-0" id="bookingAgentPhone"
                                        placeholder="Enter 10 Digit Agent Number" maxlength="10" inputmode="numeric"
                                        oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10); lookupBookingAgent(this.value);">
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="small text-muted fw-semibold mb-1">Selected Agent</div>
                                <div class="fw-bold text-dark" id="bookingAgentStatus">Search an agent to continue.
                                </div>
                            </div>
                        </div>

                        <input type="hidden" id="bookingAgentId">

                        <div id="bookingAgentBox" class="bg-white p-4 rounded-4 mt-4 border agent-summary"
                            style="display:none;">
                            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-person-badge me-2 text-success"></i>Agent
                                Information</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <small class="text-muted d-block fs-8 text-uppercase fw-semibold">Agent Name</small>
                                    <strong class="text-dark fs-6" id="bookingAgentName">-</strong>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block fs-8 text-uppercase fw-semibold">Company
                                        Name</small>
                                    <strong class="text-dark fs-6" id="bookingAgentCompany">-</strong>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block fs-8 text-uppercase fw-semibold">GST Number</small>
                                    <strong class="text-dark fs-6" id="bookingAgentGstNumber">-</strong>
                                </div>
                                <div class="col-md-12">
                                    <small class="text-muted d-block fs-8 text-uppercase fw-semibold">Address /
                                        Location</small>
                                    <strong class="text-dark fs-6" id="bookingAgentLocation">-</strong>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block fs-8 text-uppercase fw-semibold">Contact
                                        Number</small>
                                    <strong class="text-dark fs-6" id="bookingAgentContact">-</strong>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block fs-8 text-uppercase fw-semibold">Email
                                        Address</small>
                                    <strong class="text-dark fs-6" id="bookingAgentEmail">-</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <form id="createBookingForm">
                        <div class="field-block">
                            <h6 class="fw-bold text-dark mb-3 border-bottom pb-2"><i
                                    class="bi bi-person-fill me-2"></i>Client Details</h6>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label text-muted fw-medium fs-7">Client Name</label>
                                    <input type="text" class="form-control py-2" id="clientName" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted fw-medium fs-7">Phone Number</label>
                                    <input type="tel" class="form-control py-2" id="clientPhone" required>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label text-muted fw-medium fs-7">Email (Optional)</label>
                                    <input type="email" class="form-control py-2" id="clientEmail">
                                </div>
                            </div>
                        </div>

                        <div class="field-block">
                            <h6 class="fw-bold text-dark mb-3 border-bottom pb-2"><i
                                    class="bi bi-card-text me-2 text-success"></i>Reservation Details</h6>
                            <div class="row g-3 mb-3">
                                <div class="col-md-12">
                                    <label class="form-label text-muted fw-medium fs-7">Hotel Name</label>
                                    <div class="hotel-search-wrap">
                                        <input type="text" class="form-control py-2" id="bookingHotelName"
                                            list="bookingHotelOptions" placeholder="Type or select hotel..."
                                            autocomplete="off" required>
                                        <div id="hotelSuggestionMenu" class="hotel-suggestion-menu"></div>
                                    </div>
                                    <input type="hidden" id="bookingHotelId">
                                    <input type="hidden" id="bookingHotelRoomType">
                                    <datalist id="bookingHotelOptions">
                                    </datalist>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label text-muted fw-medium fs-7">Room Category</label>
                                    <select class="form-select py-2" id="bookRoomCategory" required>
                                        <option value="" selected disabled>Select room category...</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted fw-medium fs-7">Number of Rooms</label>
                                    <input type="number" class="form-control py-2" id="bookRoomsCount"
                                        placeholder="Rooms" min="1" value="1" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted fw-medium fs-7">Number of Persons</label>
                                    <input type="number" class="form-control py-2" id="bookPersons" placeholder="Adults"
                                        min="1" value="1" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted fw-medium fs-7">Check-in Date</label>
                                    <input type="date" class="form-control py-2" id="bookCheckInDate"
                                        min="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted fw-medium fs-7">Check-out Date</label>
                                    <input type="date" class="form-control py-2" id="bookCheckOutDate" required
                                        disabled>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted fw-medium fs-7">Child (Optional)</label>
                                    <input type="number" class="form-control py-2" id="bookChild" placeholder="Children"
                                        min="0" value="0">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted fw-medium fs-7">Extra Person (Optional)</label>
                                    <input type="number" class="form-control py-2" id="bookExtraPerson"
                                        placeholder="Extra Person" min="0" value="0">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label text-muted fw-medium fs-7">Meal Plan</label>
                                    <select class="form-select py-2" id="bookMealPlan" required>
                                        <option value="" selected disabled>Select...</option>
                                        <option value="EP (Room Only)">EP (Room Only)</option>
                                        <option value="CP (Breakfast)">CP (Breakfast)</option>
                                        <option value="MAP (Breakfast + Dinner)">MAP (Breakfast + Dinner)</option>
                                        <option value="AP (All Meals)">AP (All Meals)</option>
                                    </select>
                                </div>

                                <div class="col-12 mt-2">
                                    <div class="p-3 bg-light rounded-3 border"
                                        style="border-left: 4px solid #805ad5 !important;">
                                        <small class="text-muted d-block fs-8 text-uppercase fw-semibold mb-1">Booking
                                            Overview</small>
                                        <strong class="text-dark fs-6" id="dispBookingOverview">Please fill out details
                                            to see overview.</strong>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label text-muted fw-medium fs-7">Total Booking Amount (₹)</label>
                                    <input type="number" class="form-control py-2 fw-bold text-success"
                                        id="bookTotalAmount" min="0" placeholder="0.00" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted fw-medium fs-7">Advance Paid (₹)</label>
                                    <input type="number" class="form-control py-2" id="bookAdvancePaid" min="0"
                                        value="0" placeholder="0.00" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted fw-medium fs-7">Booking Date</label>
                                    <input type="date" class="form-control py-2" id="bookingDate"
                                        value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted fw-medium fs-7">Booking Status</label>
                                    <select class="form-select py-2" id="bookingStatus">
                                        <option value="Pending">Pending</option>
                                        <option value="Completed">Completed</option>
                                        <option value="Cancelled">Cancelled</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted fw-medium fs-7">Booking Source</label>
                                    <select class="form-select py-2" id="bookingSource">
                                        <option value="Direct">Direct</option>
                                        <option value="Walk-in">Walk-in</option>
                                        <option value="Phone Call">Phone Call</option>
                                        <option value="Website Lead">Website Lead</option>
                                        <option value="Referral">Referral</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted fw-medium fs-7">Payment Note</label>
                                    <input type="text" class="form-control py-2" id="paymentNote"
                                        placeholder="UPI ref / cash note">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label text-muted fw-medium fs-7">Special Request</label>
                                    <textarea class="form-control py-2" id="specialRequest" rows="2"
                                        placeholder="Late check-in, high floor, meal preference etc."></textarea>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn w-100 py-3 rounded-3 fw-bold shadow-sm mt-4"
                            style="background-color: #38a169; color: white; border: none; font-size: 1.1rem;">
                            <i class="bi bi-check-circle me-2"></i> Create Booking
                        </button>
                    </form>
                </div>
            </div>

            <!-- Search Agent by Mobile View -->
            <div id="search-agent-view" class="view-section">
                <div class="form-card compact">
                    <div class="text-center mb-4 pb-3 border-bottom">
                        <div class="icon-wrapper mx-auto"
                            style="background: #faf5ff; color: #805ad5; width: 60px; height: 60px; font-size: 1.5rem; margin-bottom: 15px;">
                            <i class="bi bi-search"></i>
                        </div>
                        <h3 class="fw-bold text-dark mb-1">Search Agent by Mobile</h3>
                        <p class="text-muted fs-7 mb-0">Enter mobile number to find agent and view their bookings</p>
                    </div>

                    <form id="searchAgentForm" onsubmit="searchAgentByMobile(event); return false;">
                        <div class="mb-4">
                            <label for="searchMobile" class="form-label text-muted fw-medium fs-7">
                                Mobile Number
                            </label>

                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="bi bi-telephone-fill text-muted"></i>
                                </span>

                                <input type="tel" class="form-control border-start-0" id="searchMobile"
                                    placeholder="Enter 10-digit mobile number" maxlength="10" pattern="[0-9]{10}"
                                    oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0,10)" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-3 rounded-3 fw-bold shadow-sm"
                            style="background-color: #805ad5; border-color: #805ad5; font-size: 1.1rem;">
                            <i class="bi bi-search me-2"></i> Search Agent
                        </button>
                    </form>

                    <!-- Search Results -->
                    <div id="searchResults"
                        style="display: none; margin-top: 30px; padding-top: 30px; border-top: 2px solid #edf2f7;">

                        <!-- Agent Found Section -->
                        <div id="agentFoundSection" style="display: none;">
                            <div class="alert alert-success border-0 rounded-3 mb-4" role="alert">
                                <i class="bi bi-check-circle-fill me-2"></i> <strong>Agent Found!</strong>
                            </div>

                            <div class="card border-0 rounded-3 mb-4 shadow-sm" id="agentCard">
                                <div class="card-body p-4">
                                    <div class="row">
                                        <div class="col-md-6 mb-3 mb-md-0">
                                            <img src="https://ui-avatars.com/api/?name=Agent&background=dbeafe&color=1d4ed8&size=96" id="agentAvatarDisplay" class="rounded-circle mb-3" width="64" height="64" alt="Agent avatar">
                                            <h6 class="text-muted fw-bold small text-uppercase mb-2">Agent Information
                                            </h6>
                                            <h5 class="fw-bold mb-3" id="agentNameDisplay"></h5>
                                            <p class="mb-2">
                                                <i class="bi bi-telephone me-2 text-muted"></i>
                                                <span id="agentPhoneDisplay" class="fw-medium"></span>
                                            </p>
                                            <p class="mb-2">
                                                <i class="bi bi-envelope me-2 text-muted"></i>
                                                <span id="agentEmailDisplay" class="fw-medium"></span>
                                            </p>
                                            <p class="mb-2">
                                                <i class="bi bi-receipt me-2 text-muted"></i>
                                                <span id="agentGstDisplay" class="fw-medium"></span>
                                            </p>
                                            <p class="mb-2">
                                                <i class="bi bi-building me-2 text-muted"></i>
                                                <span id="agentCompanyDisplay" class="fw-medium"></span>
                                            </p>
                                            <p class="mb-2">
                                                <i class="bi bi-geo-alt me-2 text-muted"></i>
                                                <span id="agentLocationDisplay" class="fw-medium"></span>
                                            </p>
                                        </div>
                                        <div class="col-md-6">
                                            <h6 class="text-muted fw-bold small text-uppercase mb-2">Status Summary</h6>
                                            <div class="stat-badge mb-3">
                                                <div class="d-flex align-items-baseline justify-content-between">
                                                    <span class="text-muted">Total Bookings</span>
                                                    <strong id="totalBookingsCount"
                                                        style="font-size: 1.8rem; color: #805ad5;">0</strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Bookings Table -->
                            <div class="card border-0 rounded-3 shadow-sm">
                                <div class="p-3">
                                    <div class="row g-2">
                                        <div class="col-md-5">
                                            <input type="text" id="agentBookingsSearch" class="form-control" placeholder="Search booking, client, hotel..." oninput="filterAgentBookings()">
                                        </div>
                                        <div class="col-md-3">
                                            <input type="text" id="agentBookingCodeFilter" class="form-control" placeholder="Booking code" oninput="filterAgentBookings()">
                                        </div>
                                        <div class="col-md-2">
                                            <select id="agentBookingStatusFilter" class="form-select" onchange="filterAgentBookings()">
                                                <option value="">All Status</option>
                                                <option value="Pending">Pending</option>
                                                <option value="Completed">Completed</option>
                                                <option value="Cancelled">Cancelled</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2 text-end">
                                            <button class="btn btn-sm btn-outline-secondary" onclick="clearAgentBookingFilters()">Reset</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-header bg-white border-0 p-4 rounded-top-3">
                                    <h6 class="fw-bold text-dark mb-0">
                                        <i class="bi bi-calendar-check me-2"></i> Agent's Bookings
                                    </h6>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-custom table-hover mb-0">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th>Booking Code</th>
                                                    <th>Client Name</th>
                                                    <th>Hotel</th>
                                                    <th>Check-in</th>
                                                    <th>Check-out</th>
                                                    <th>Amount</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody id="bookingsTableBody">
                                            </tbody>
                                        </table>
                                        <div id="noBookingsMsg" class="text-center py-4 text-muted">
                                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                            <p>No bookings found for this agent yet.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Agent Not Found Section -->
                        <div id="agentNotFoundSection" style="display: none;">
                            <div class="alert alert-warning border-0 rounded-3 mb-4" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i> <strong>Agent Not
                                    Registered</strong>
                            </div>

                            <div class="card border-0 rounded-3 shadow-sm">
                                <div class="card-body p-5 text-center">
                                    <div class="mb-4">
                                        <i class="bi bi-person-plus-circle"
                                            style="font-size: 3rem; color: #e2a76f;"></i>
                                    </div>
                                    <h5 class="fw-bold text-dark mb-2">No Agent Found with This Mobile</h5>
                                    <p class="text-muted mb-4">Would you like to register this agent now? Fill in the
                                        details below:</p>
                                </div>
                            </div>

                            <form id="registerAgentForm" class="mt-4" onsubmit="registerNewAgent(event); return false;">
                                <div class="form-card compact">
                                    <div class="mb-3">
                                        <label for="regAgentName" class="form-label text-muted fw-medium fs-7">Agent
                                            Name</label>
                                        <input type="text" class="form-control py-2" id="regAgentName"
                                            placeholder="Enter full name" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="regAgentPhone" class="form-label text-muted fw-medium fs-7">Mobile
                                            Number</label>
                                        <input type="tel" class="form-control py-2" id="regAgentPhone" readonly>
                                    </div>
                                    <div class="mb-3">
                                        <label for="regAgentEmail" class="form-label text-muted fw-medium fs-7">Email
                                            Address</label>
                                        <input type="email" class="form-control py-2" id="regAgentEmail"
                                            placeholder="Enter email" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="regAgentCompany"
                                            class="form-label text-muted fw-medium fs-7">Company Name</label>
                                        <input type="text" class="form-control py-2" id="regAgentCompany"
                                            placeholder="Enter company name" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="regAgentGstNumber" class="form-label text-muted fw-medium fs-7">GST Number</label>
                                        <input type="text" class="form-control py-2" id="regAgentGstNumber" placeholder="Enter GST number (optional)">
                                    </div>
                                    <div class="mb-4">
                                        <label for="regAgentLocation"
                                            class="form-label text-muted fw-medium fs-7">Location / Area</label>
                                        <input type="text" class="form-control py-2" id="regAgentLocation"
                                            placeholder="Enter location" required>
                                    </div>
                                    <button type="submit" class="btn btn-success w-100 py-3 rounded-3 fw-bold shadow-sm"
                                        style="font-size: 1.1rem;">
                                        <i class="bi bi-person-plus-fill me-2"></i> Register Agent
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- My Bookings View -->
            <div id="my-bookings-view" class="view-section">
                <div class="form-card mb-5">
                    <div class="d-flex align-items-center mb-4 pb-3 border-bottom">
                        <div class="icon-wrapper me-3 mb-0"
                            style="background: #faf5ff; color: #805ad5; width: 50px; height: 50px; font-size: 1.25rem;">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <div>
                            <h4 class="fw-bold text-dark mb-1">My Bookings</h4>
                            <p class="text-muted fs-7 mb-0">All bookings you created</p>
                        </div>
                    </div>

                    <div class="history-summary">
                        <div class="summary-chip">
                            <div class="label">Bookings</div>
                            <div class="value"><?php echo number_format(count($my_bookings)); ?></div>
                        </div>
                        <div class="summary-chip">
                            <div class="label">Total Amount</div>
                            <div class="value">
                                ₹<?php echo number_format((float)array_sum(array_map(static fn($row) => (float)($row['amount'] ?? 0), $my_bookings)), 0); ?>
                            </div>
                        </div>
                        <div class="summary-chip">
                            <div class="label">Received</div>
                            <div class="value text-success">
                                ₹<?php echo number_format((float)array_sum(array_map(static fn($row) => (float)($row['paid_amount'] ?? 0), $my_bookings)), 0); ?>
                            </div>
                        </div>
                        <div class="summary-chip">
                            <div class="label">Due</div>
                            <div class="value text-danger">
                                ₹<?php echo number_format((float)array_sum(array_map(static fn($row) => (float)($row['due_amount'] ?? 0), $my_bookings)), 0); ?>
                            </div>
                        </div>
                    </div>

                    <div class="booking-toolbar">
                        <div class="row g-2 align-items-center">
                            <div class="col-md-4">
                                <div class="input-group shadow-sm">
                                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                                    <input type="text" class="form-control border-start-0 ps-0" id="myBookingsSearch" placeholder="Search booking, client, hotel, agent..." onkeyup="filterMyBookings()">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <input type="text" id="myBookingCodeFilter" class="form-control" placeholder="Booking code" oninput="filterMyBookings()">
                            </div>
                            <div class="col-md-2">
                                <select id="myBookingStatusFilter" class="form-select" onchange="filterMyBookings()">
                                    <option value="">All Status</option>
                                    <option value="Pending">Pending</option>
                                    <option value="Completed">Completed</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select id="myPaymentStatusFilter" class="form-select" onchange="filterMyBookings()">
                                    <option value="">All Payment</option>
                                    <option value="Pending">Pending</option>
                                    <option value="Partial">Partial</option>
                                    <option value="Paid">Paid</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-2 text-end">
                                <div class="text-muted small fw-medium">
                                    <i class="bi bi-info-circle me-1"></i> Copy button is ready for WhatsApp sharing
                                </div>
                            </div>
                            <div class="col-12 mt-2">
                                <div class="row g-2">
                                    <div class="col-md-3">
                                        <input type="text" id="myAgentPhoneFilter" class="form-control" placeholder="Agent number" oninput="filterMyBookings()">
                                    </div>
                                    <div class="col-md-3">
                                        <input type="date" id="myFromDateFilter" class="form-control" onchange="filterMyBookings()">
                                    </div>
                                    <div class="col-md-3">
                                        <input type="date" id="myToDateFilter" class="form-control" onchange="filterMyBookings()">
                                    </div>
                                    <div class="col-md-3 text-end">
                                        <button class="btn btn-sm btn-outline-secondary" onclick="clearMyBookingFilters()">Reset Filters</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (count($my_bookings) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-custom table-hover align-middle bookings-table" id="myBookingsTable">
                            <thead class="bg-light">
                                <tr>
                                    <th>Code</th>
                                    <th>Client</th>
                                    <th>Hotel / Agent</th>
                                    <th>Dates</th>
                                    <th>Total</th>
                                    <th>Paid</th>
                                    <th>Due</th>
                                    <th>Payment</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Copy Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($my_bookings as $booking): ?>
                                    <tr data-booking-code="<?php echo htmlspecialchars($booking['booking_code'] ?? ''); ?>" data-agent-phone="<?php echo htmlspecialchars($booking['agent_phone'] ?? ''); ?>" data-booking-date="<?php echo htmlspecialchars($booking['booking_date'] ?? ''); ?>" data-client="<?php echo htmlspecialchars($booking['client_name'] ?? ''); ?>" data-hotel="<?php echo htmlspecialchars($booking['hotel_name'] ?? ''); ?>" data-payment-status="<?php echo htmlspecialchars($booking['payment_status'] ?? ''); ?>" data-booking-status="<?php echo htmlspecialchars($booking['booking_status'] ?? ''); ?>">
                                    <td><span
                                            class="fw-bold text-dark"><?php echo htmlspecialchars($booking['booking_code']); ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-medium text-dark">
                                            <?php echo htmlspecialchars($booking['client_name']); ?></div>
                                        <div class="fs-8 text-muted">
                                            <?php echo htmlspecialchars($booking['client_phone']); ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-medium text-dark">
                                            <?php echo htmlspecialchars($booking['hotel_name']); ?></div>
                                        <div class="fs-8 text-muted">
                                            <?php echo htmlspecialchars((string)($booking['hotel_location_snapshot'] ?? '')); ?>
                                            <?php if (!empty($booking['room_type_snapshot'])): ?>
                                            <span
                                                class="badge bg-light text-dark border ms-1"><?php echo htmlspecialchars((string)$booking['room_type_snapshot']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="fs-8 text-muted mt-1">
                                            <i class="bi bi-person-badge me-1"></i>
                                            Agent:
                                            <?php echo htmlspecialchars((string)($booking['agent_name'] ?? 'N/A')); ?>
                                            <?php if (!empty($booking['agent_company'])): ?>
                                            <span
                                                class="ms-1">(<?php echo htmlspecialchars((string)$booking['agent_company']); ?>)</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="fs-8">
                                        <?php echo date('d M', strtotime($booking['check_in'])); ?> -
                                        <?php echo date('d M', strtotime($booking['check_out'])); ?>
                                    </td>
                                    <td><span
                                            class="fw-bold text-dark">₹<?php echo number_format($booking['amount'], 0); ?></span>
                                    </td>
                                    <td><span
                                            class="fw-bold text-success">₹<?php echo number_format((float)($booking['paid_amount'] ?? 0), 0); ?></span>
                                    </td>
                                    <td><span
                                            class="fw-bold text-danger">₹<?php echo number_format((float)($booking['due_amount'] ?? 0), 0); ?></span>
                                    </td>
                                    <td>
                                        <span
                                            class="badge <?php echo (($booking['payment_status'] ?? 'Pending') === 'Paid') ? 'bg-success' : ((($booking['payment_status'] ?? 'Pending') === 'Partial') ? 'bg-warning text-dark' : 'bg-secondary'); ?>">
                                            <?php echo htmlspecialchars($booking['payment_status'] ?? 'Pending'); ?>
                                        </span>
                                        <button type="button" class="btn btn-sm btn-light border rounded-circle ms-1"
                                            title="Edit payment"
                                            onclick="openPaymentEditor(<?php echo (int)$booking['id']; ?>, <?php echo (float)$booking['amount']; ?>, <?php echo (float)($booking['paid_amount'] ?? 0); ?>, '<?php echo htmlspecialchars((string)($booking['payment_note'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>')">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    </td>
                                    <td>
                                        <span
                                            class="badge 
                                                <?php echo (($booking['booking_status'] ?? 'Pending') === 'Completed' ? 'bg-success' : 
                                                       (($booking['booking_status'] ?? 'Pending') === 'Pending' ? 'bg-warning text-dark' : 'bg-danger')); ?>">
                                            <?php echo htmlspecialchars($booking['booking_status'] ?? 'Pending'); ?>
                                        </span>
                                    </td>
                                    <td class="fs-8 text-muted">
                                        <?php echo date('d M Y', strtotime($booking['created_at'])); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary copy-trigger"
                                            onclick="copyBookingData(this)" data-booking='<?php echo htmlspecialchars(json_encode([
                                                    'booking_code' => (string)($booking['booking_code'] ?? ''),
                                                    'client_name' => (string)($booking['client_name'] ?? ''),
                                                    'client_phone' => (string)($booking['client_phone'] ?? ''),
                                                    'client_email' => (string)($booking['client_email'] ?? ''),
                                                    'hotel_name' => (string)($booking['hotel_name'] ?? ''),
                                                    'hotel_location' => (string)($booking['hotel_location_snapshot'] ?? ''),
                                                    'room_type' => (string)($booking['room_type_snapshot'] ?? ''),
                                                    'agent_name' => (string)($booking['agent_name'] ?? ''),
                                                    'agent_company' => (string)($booking['agent_company'] ?? ''),
                                                    'agent_location' => (string)($booking['agent_location'] ?? ''),
                                                    'check_in' => (string)($booking['check_in'] ?? ''),
                                                    'check_out' => (string)($booking['check_out'] ?? ''),
                                                    'booking_date' => (string)($booking['booking_date'] ?? ''),
                                                    'amount' => (float)($booking['amount'] ?? 0),
                                                    'paid_amount' => (float)($booking['paid_amount'] ?? 0),
                                                    'due_amount' => (float)($booking['due_amount'] ?? 0),
                                                    'payment_status' => (string)($booking['payment_status'] ?? 'Pending'),
                                                    'booking_status' => (string)($booking['booking_status'] ?? 'Pending'),
                                                    'booking_source' => (string)($booking['booking_source'] ?? 'Direct'),
                                                    'guest_count' => (int)($booking['guest_count'] ?? 1),
                                                    'room_count' => (int)($booking['room_count'] ?? 1),
                                                    'special_request' => (string)($booking['special_request'] ?? ''),
                                                    'created_at' => (string)($booking['created_at'] ?? ''),
                                                ]), ENT_QUOTES, 'UTF-8'); ?>'>
                                            <i class="bi bi-clipboard me-1"></i>Copy
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div id="myBookingsFilterEmpty" class="text-center py-4 text-muted" style="display:none;">
                        <i class="bi bi-search fs-1 d-block mb-2"></i>
                        No bookings match your search.
                    </div>
                    <?php else: ?>
                    <div class="text-center py-5 text-muted" id="myBookingsEmptyState">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        <p>No bookings created yet. <a href="#"
                                onclick="showSection('create-booking-view'); return false;">Create your first
                                booking</a></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Booking Query View -->
            <div id="booking-query-view" class="view-section">
                <div class="card p-4 shadow-sm border-0" style="border-radius: 8px; background: #fff;">
                    <h4 class="mb-4 fw-bold text-dark">Booking Query Details</h4>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary d-block">Query Type</label>
                        <div class="d-flex gap-4">
                            <label class="form-check"><input class="form-check-input" type="radio" name="bookingQueryType" value="agent" checked onchange="setBookingQueryType(this.value)"> Agent</label>
                        </div>
                    </div>
                    <div id="bookingQueryAgentBox" class="border rounded p-3 mb-3" style="display:none;">
                        <label for="bookingQueryAgentPhone" class="form-label small fw-semibold text-secondary">Agent Mobile Number</label>
                        <div class="input-group">
                            <input type="tel" class="form-control" id="bookingQueryAgentPhone" maxlength="20" placeholder="Enter registered agent mobile number" oninput="lookupBookingQueryAgent()">
                            <button type="button" class="btn btn-outline-primary" onclick="lookupBookingQueryAgent()">Fetch Agent</button>
                        </div>
                        <div id="bookingQueryAgentStatus" class="small text-muted mt-2">Enter agent mobile number.</div>
                    </div>

                    <fieldset id="bookingQueryDetailsFields">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="bookingQueryLocation" class="form-label small fw-semibold text-secondary">Location</label>
                            <input type="text" class="form-control query-required-field" id="bookingQueryLocation" list="hotelCityList" placeholder="Type a city, e.g. gur..." value="" autocomplete="off" required>
                            <datalist id="hotelCityList">
                                <?php foreach ($hotel_locations as $cityOption): ?>
                                    <option value="<?php echo htmlspecialchars($cityOption, ENT_QUOTES, 'UTF-8'); ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-md-6">
                            <label for="bookingQueryHotelCategory" class="form-label small fw-semibold text-secondary">Hotel Category</label>
                            <select class="form-select query-required-field" id="bookingQueryHotelCategory" required>
                                <option value="all categories" selected>All Categories</option>
                                <?php foreach ($hotel_category_options as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="bookingQueryCheckIn" class="form-label small fw-semibold text-secondary">Check-In</label>
                            <input type="date" class="form-control query-required-field" id="bookingQueryCheckIn" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="bookingQueryCheckOut" class="form-label small fw-semibold text-secondary">Check-out</label>
                            <input type="date" class="form-control query-required-field" id="bookingQueryCheckOut" required>
                        </div>
                        <div class="col-md-4">
                            <label for="bookingQueryNights" class="form-label small fw-semibold text-secondary">Nights</label>
                            <input type="number" class="form-control" id="bookingQueryNights" min="0" value="0" readonly>
                        </div>

                        <div class="col-md-3">
                            <label for="bookingQueryAdults" class="form-label small fw-semibold text-secondary">Adults</label>
                            <input type="number" class="form-control query-required-field" id="bookingQueryAdults" min="1" value="2" required>
                        </div>
                        <div class="col-md-3">
                            <label for="bookingQueryChildren" class="form-label small fw-semibold text-secondary">Children</label>
                            <input type="number" class="form-control query-required-field" id="bookingQueryChildren" min="0" value="0" required>
                        </div>
                        <div class="col-md-3">
                            <label for="bookingQueryRooms" class="form-label small fw-semibold text-secondary">Rooms</label>
                            <input type="number" class="form-control query-required-field" id="bookingQueryRooms" min="1" value="1" required>
                        </div>
                        <div class="col-md-3">
                            <label for="bookingQueryBudget" class="form-label small fw-semibold text-secondary">Budget</label>
                            <input type="number" class="form-control" id="bookingQueryBudget" placeholder="e.g. 5000" min="0" step="100">
                        </div>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="button" class="btn btn-primary py-2" onclick="generateBookingQueryResults()">
                            <i class="bi bi-magic me-2"></i>Generate Query
                        </button>
                    </div>
                    </fieldset>
                </div>

                <div id="bookingQueryResultsWrap" class="card p-4 mt-4 shadow-sm border-0" style="border-radius: 8px; display: none;">
                    <div class="d-flex flex-wrap gap-2 mb-3 align-items-center justify-content-between">
                        <h5 class="mb-0 fw-bold text-dark">Generated Results</h5>
                        <input type="search" class="form-control form-control-sm" id="bookingQueryHotelSearch" placeholder="Search hotel name..." oninput="filterBookingQueryResults()" style="max-width:240px;">
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-secondary me-2" onclick="selectBookingQueryRows(5)">Top 5</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary me-2" onclick="selectBookingQueryRows(10)">Top 10</button>
                            <button type="button" class="btn btn-sm btn-outline-primary me-2" onclick="selectBookingQueryRows('all')">Select All</button>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearBookingQueryRows()">Clear Selection</button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 55px;">Select</th>
                                    <th>Property Name</th>
                                    <th>Room Category</th>
                                    <th>Meal Plan</th>
                                    <th>Location</th>
                                    <th>Price / Night</th>
                                    <th>Check-In</th>
                                    <th>Check-Out</th>
                                </tr>
                            </thead>
                            <tbody id="bookingQueryResultsBody"></tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        <button class="btn btn-success" type="button" onclick="sendSelectedBookingQueryQuotes()">
                            Copy
                        </button>
                    </div>
                </div>
            </div>

            <!-- Query History View -->
            <div id="query-history-view" class="view-section">
            <div class="form-card">
                        <div class="d-flex align-items-center mb-4 pb-3 border-bottom">
                            <div class="icon-wrapper me-3 mb-0"
                                style="background: #fef3c7; color: #d97706; width: 50px; height: 50px; font-size: 1.25rem;">
                                <i class="bi bi-clock-history"></i>
                            </div>
                            <div>
                                <h4 class="fw-bold text-dark mb-1">Query History</h4>
                                <p class="text-muted fs-7 mb-0">Your recent query generations</p>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
                            <button type="button" class="btn btn-sm btn-primary query-history-filter active" data-history-filter="all">All</button>
                            <button type="button" class="btn btn-sm btn-outline-primary query-history-filter" data-history-filter="today">Today</button>
                            <button type="button" class="btn btn-sm btn-outline-primary query-history-filter" data-history-filter="week">This Week</button>
                            <button type="button" class="btn btn-sm btn-outline-primary query-history-filter" data-history-filter="month">This Month</button>
                            <input type="search" class="form-control form-control-sm ms-auto" style="max-width:240px" id="queryHistorySearch" placeholder="Filter history...">
                        </div>

                        <div id="queryHistory">
                            <div class="text-center py-4">
                                <div class="spinner-border spinner-border-sm text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <div class="mt-2 text-muted">Loading history...</div>
                            </div>
                        </div>

                        <div class="modal fade" id="queryHistoryModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="queryHistoryModalTitle">Query Details</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                                            aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <pre id="queryHistoryModalBody" class="p-3"
                                            style="white-space: pre-wrap; background: #f8f9fa; border-radius: .75rem; min-height: 220px;"></pre>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary"
                                            data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
            </div>
                </div>

        </main>
    </div>

    </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <div class="modal fade" id="paymentEditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Update Payment Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="editBookingId">
                    <div class="mb-2 text-muted small">Total Amount: <strong id="editTotalAmount">₹0</strong></div>
                    <div class="mb-2 text-muted small">Already Paid: <strong id="editAlreadyPaid">₹0</strong></div>
                    <div class="mb-2 text-muted small">Due Amount: <strong id="editDueAmount">₹0</strong></div>
                    <div class="mb-3">
                        <label class="form-label">Payment Amount</label>
                        <input type="number" class="form-control" id="editPaymentAmount" min="0" step="100" placeholder="Enter amount to pay">
                        <div class="form-text">Enter only the additional payment you want to apply. Maximum allowed is the due amount above.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Note</label>
                        <textarea class="form-control" id="editPaymentNote" rows="3" placeholder="Payment note / reference"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary" onclick="savePaymentUpdate()">Save</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    (() => {
        const sidebar = document.querySelector('.sidebar');
        const btn = document.getElementById('mobileMenuBtn');
        const closeBtn = document.getElementById('sidebarCloseBtn');
        const backdrop = document.getElementById('sidebarBackdrop');
        if (!sidebar || !btn || !backdrop) return;
        const closeMenu = () => {
            sidebar.classList.remove('open');
            backdrop.classList.remove('show');
        };
        const openMenu = () => {
            sidebar.classList.add('open');
            backdrop.classList.add('show');
        };
        btn.addEventListener('click', openMenu);
        if (closeBtn) closeBtn.addEventListener('click', closeMenu);
        backdrop.addEventListener('click', closeMenu);
        document.querySelectorAll('.sidebar .nav-link').forEach((link) => link.addEventListener('click',
            closeMenu));
    })();

    const empInitialMetrics = <?php echo json_encode($employeeMetrics); ?>;
    const bookingHotelCatalog =
        <?php echo json_encode($hotel_catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    let bookingHotelLookupTimer = null;
    let queryHotelLookupTimer = null;
    let bookingHotelLookupMap = {};
    let queryHotelLookupMap = {};
    let queryHistoryData = [];
    let empWeeklyChart = null;
    let empStatusChart = null;
    let empPaymentChart = null;
    let empMonthlyChart = null;
    let empSourceChart = null;
    let empHotelChart = null;

    function formatInr(num) {
        return new Intl.NumberFormat('en-IN').format(Number(num || 0));
    }

    function setEmployeeKpis(kpi) {
        const setText = (id, value) => {
            const el = document.getElementById(id);
            if (el) el.textContent = value;
        };
        setText('empKpiTotalBookings', formatInr(kpi.totalBookings));
        setText('empKpiTodayBookings', formatInr(kpi.todayBookings));
        setText('empKpiTotalAmount', '₹' + formatInr(kpi.totalAmount));
        setText('empKpiReceived', '₹' + formatInr(kpi.received));
        setText('empKpiDue', '₹' + formatInr(kpi.due));
        setText('empKpiPendingPayment', formatInr(kpi.pendingPayment));
    }

    function initializeEmployeeCharts(metrics) {
        if (document.getElementById('empWeeklyChart')) {
            empWeeklyChart = new Chart(document.getElementById('empWeeklyChart').getContext('2d'), {
                type: 'line',
                data: {
                    labels: metrics.weekly.labels,
                    datasets: [{
                        data: metrics.weekly.counts,
                        borderColor: '#4f46e5',
                        backgroundColor: 'rgba(123, 76, 247, 0.12)',
                        fill: true,
                        tension: .35
                    }]
                },
                options: {
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        }

        if (document.getElementById('empStatusChart')) {
            empStatusChart = new Chart(document.getElementById('empStatusChart').getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: metrics.status.labels,
                    datasets: [{
                        data: metrics.status.counts,
                        backgroundColor: ['#f59e0b', '#10b981', '#ef4444'],
                        borderWidth: 0
                    }]
                },
                options: {
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    },
                    cutout: '65%'
                }
            });
        }

        if (document.getElementById('empPaymentChart')) {
            empPaymentChart = new Chart(document.getElementById('empPaymentChart').getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: metrics.payment.labels,
                    datasets: [{
                        data: metrics.payment.counts,
                        backgroundColor: ['#94a3b8', '#f59e0b', '#10b981', '#ef4444'],
                        borderWidth: 0
                    }]
                },
                options: {
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    },
                    cutout: '65%'
                }
            });
        }

        if (document.getElementById('empMonthlyChart')) {
            empMonthlyChart = new Chart(document.getElementById('empMonthlyChart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: metrics.monthly.labels,
                    datasets: [{
                        data: metrics.monthly.counts,
                        backgroundColor: '#4f46e5',
                        borderRadius: 8
                    }]
                },
                options: {
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        }

        if (document.getElementById('empSourceChart')) {
            empSourceChart = new Chart(document.getElementById('empSourceChart').getContext('2d'), {
                type: 'polarArea',
                data: {
                    labels: metrics.source.labels,
                    datasets: [{
                        data: metrics.source.counts,
                        backgroundColor: ['#4f46e5', '#10b981', '#06b6d4', '#f59e0b', 
'#ef4444',
                            '#9aa0b5'
                        ]
                    }]
                },
                options: {
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        if (document.getElementById('empHotelChart')) {
            empHotelChart = new Chart(document.getElementById('empHotelChart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: metrics.hotels.labels,
                    datasets: [{
                        data: metrics.hotels.counts,
                        backgroundColor: '#10b981',
                        borderRadius: 8
                    }]
                },
                options: {
                    indexAxis: 'y',
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        }
    }

    function refreshEmployeeLiveMetrics() {
        fetch('employee-dashboard.php?action=live_metrics')
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.metrics) return;
                const m = data.metrics;
                setEmployeeKpis(m.kpi);

                if (empWeeklyChart) {
                    empWeeklyChart.data.labels = m.weekly.labels;
                    empWeeklyChart.data.datasets[0].data = m.weekly.counts;
                    empWeeklyChart.update('none');
                }
                if (empStatusChart) {
                    empStatusChart.data.labels = m.status.labels;
                    empStatusChart.data.datasets[0].data = m.status.counts;
                    empStatusChart.update('none');
                }
                if (empPaymentChart) {
                    empPaymentChart.data.labels = m.payment.labels;
                    empPaymentChart.data.datasets[0].data = m.payment.counts;
                    empPaymentChart.update('none');
                }
                if (empMonthlyChart) {
                    empMonthlyChart.data.labels = m.monthly.labels;
                    empMonthlyChart.data.datasets[0].data = m.monthly.counts;
                    empMonthlyChart.update('none');
                }
                if (empSourceChart) {
                    empSourceChart.data.labels = m.source.labels;
                    empSourceChart.data.datasets[0].data = m.source.counts;
                    empSourceChart.update('none');
                }
                if (empHotelChart) {
                    empHotelChart.data.labels = m.hotels.labels;
                    empHotelChart.data.datasets[0].data = m.hotels.counts;
                    empHotelChart.update('none');
                }
            })
            .catch(() => {});
    }

    setEmployeeKpis(empInitialMetrics.kpi);
    initializeEmployeeCharts(empInitialMetrics);
    setInterval(refreshEmployeeLiveMetrics, 30000);

    let paymentModalInstance = null;

    function openPaymentEditor(bookingId, totalAmount, paidAmount, note) {
        const total = Number(totalAmount) || 0;
        const paid = Number(paidAmount) || 0;
        const due = Math.max(total - paid, 0);

        document.getElementById('editBookingId').value = bookingId;
        document.getElementById('editTotalAmount').textContent = '₹' + total.toLocaleString('en-IN');
        document.getElementById('editAlreadyPaid').textContent = '₹' + paid.toLocaleString('en-IN');
        document.getElementById('editDueAmount').textContent = '₹' + due.toLocaleString('en-IN');
        document.getElementById('editPaymentAmount').value = '';
        document.getElementById('editPaymentAmount').max = due;
        document.getElementById('editPaymentAmount').disabled = due <= 0;
        document.getElementById('editPaymentNote').value = note || '';

        const modalEl = document.getElementById('paymentEditModal');
        paymentModalInstance = paymentModalInstance || new bootstrap.Modal(modalEl);
        paymentModalInstance.show();
    }

    function savePaymentUpdate() {
        const bookingId = document.getElementById('editBookingId').value;
        const paymentAmountRaw = document.getElementById('editPaymentAmount').value;
        const totalAmount = Number(document.getElementById('editTotalAmount').textContent.replace(/[^0-9.-]+/g, '')) || 0;
        const alreadyPaid = Number(document.getElementById('editAlreadyPaid').textContent.replace(/[^0-9.-]+/g, '')) || 0;
        const dueAmount = Math.max(totalAmount - alreadyPaid, 0);
        const paymentNote = document.getElementById('editPaymentNote').value;

        const paymentAmount = Number(paymentAmountRaw);
        if (!bookingId) {
            return showErrorToast('Invalid booking selected');
        }
        if (Number.isNaN(paymentAmount) || paymentAmount <= 0) {
            return showErrorToast('Enter a valid payment amount greater than 0');
        }
        if (paymentAmount > dueAmount) {
            return showErrorToast(`Entered amount exceeds pending due amount. Maximum allowed is ₹${dueAmount.toLocaleString('en-IN')}`);
        }

        const formData = new FormData();
        formData.append('action', 'update_payment_status');
        formData.append('bookingId', bookingId);
        formData.append('paidAmount', paymentAmount);
        formData.append('paymentNote', paymentNote);

        fetch('employee-dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToastMsg(data.message);
                    if (paymentModalInstance) paymentModalInstance.hide();
                    setTimeout(() => location.reload(), 400);
                } else {
                    showErrorToast(data.message || 'Payment update failed');
                }
            })
            .catch(() => showErrorToast('Payment update failed'));
    }

    function showSection(sectionId) {
        document.querySelectorAll('.sidebar .nav-link').forEach(link => {
            link.classList.toggle('active', link.dataset.target === sectionId);
        });

        const welcomeBanner = document.getElementById('welcomeBanner');
        if (welcomeBanner) {
            welcomeBanner.style.display = sectionId === 'dashboard-view' ? 'block' : 'none';
        }

        document.querySelectorAll('.view-section').forEach(el => {
            el.classList.remove('active');
            setTimeout(() => el.style.display = 'none', 50);
        });

        setTimeout(() => {
            const target = document.getElementById(sectionId);
            target.style.display = 'block';
            void target.offsetWidth;
            target.classList.add('active');
        }, 50);

        if (sectionId === 'create-booking-view') {
            const bookingAgentStatus = document.getElementById('bookingAgentStatus');
            const bookingAgentBox = document.getElementById('bookingAgentBox');
            const bookingAgentId = document.getElementById('bookingAgentId');
            const bookingAgentPhone = document.getElementById('bookingAgentPhone');
            if (bookingAgentStatus) bookingAgentStatus.textContent = 'Search an agent to continue.';
            if (bookingAgentBox) bookingAgentBox.style.display = 'none';
            if (bookingAgentId) bookingAgentId.value = '';
            if (bookingAgentPhone && !bookingAgentPhone.value) bookingAgentPhone.focus();
        }

        if (sectionId === 'my-bookings-view') {
            filterMyBookings();
        }
    }

    function showToastMsg(message) {
        const toastEl = document.getElementById('successToast');
        document.getElementById('toastMessage').innerHTML = `<i class="bi bi-check-circle-fill me-2"></i> ${message}`;
        const toast = new bootstrap.Toast(toastEl, {
            delay: 3000
        });
        toast.show();
    }

    function showErrorToast(message) {
        const toastEl = document.getElementById('errorToast');
        document.getElementById('errorToastMessage').innerHTML =
            `<i class="bi bi-exclamation-triangle-fill me-2"></i> ${message}`;
        const toast = new bootstrap.Toast(toastEl, {
            delay: 4000
        });
        toast.show();
    }

    function formatShareDate(value) {
        if (!value) return 'N/A';
        const d = new Date(value);
        if (Number.isNaN(d.getTime())) return value;
        return d.toLocaleDateString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
    }

    function buildBookingShareText(booking) {
        return [
            `Hi ${booking.client_name || 'Guest'},`,
            '',
            `Greetings from ${booking.agent_location || 'our team'}.`,
            '',
            'Thank you for your query with us. As per your requirements, following are the booking details.',
            '',
            `Trip ID ${booking.booking_code || 'N/A'}`,
            '────────────',
            `👤 Agent: ${booking.agent_name || 'N/A'}`,
            `🏢 Company: ${booking.agent_company || 'N/A'}`,
            `📍 Location: ${booking.agent_location || 'N/A'}`,
            '',
            '🏨 Hotel Stay',
            '────────────',
            `${booking.hotel_name || 'N/A'}`,
            booking.hotel_location ? `Location: ${booking.hotel_location}` : null,
            booking.room_type ? `Room Type: ${booking.room_type}` : null,
            `Check-in: ${formatShareDate(booking.check_in)}`,
            `Check-out: ${formatShareDate(booking.check_out)}`,
            `Guests: ${booking.guest_count || 1} | Rooms: ${booking.room_count || 1}`,
            '',
            '💰 Price (INR):',
            `Total Amount: ₹${Number(booking.amount || 0).toLocaleString('en-IN')}`,
            `Advance Paid: ₹${Number(booking.paid_amount || 0).toLocaleString('en-IN')}`,
            `Due Amount: ₹${Number(booking.due_amount || 0).toLocaleString('en-IN')}`,
            '',
            '📌 Other Details',
            `Source: ${booking.booking_source || 'Direct'}`,
            `Booking Date: ${formatShareDate(booking.booking_date)}`,
            `Booking Status: ${booking.booking_status || 'Pending'}`,
            `Payment Status: ${booking.payment_status || 'Pending'}`,
            '',
            `Special Request: ${booking.special_request || 'N/A'}`,
            '',
            '━━━━━━━━━━━━',
            'Thank you for choosing us!'
        ].filter(Boolean).join('\n');
    }

    function hideHotelSuggestionMenu() {
        const menu = document.getElementById('hotelSuggestionMenu');
        if (menu) {
            menu.style.display = 'none';
        }
    }

    function showHotelSuggestionMenu() {
        const menu = document.getElementById('hotelSuggestionMenu');
        if (menu && menu.children.length > 0) {
            menu.style.display = 'block';
        }
    }

    function selectHotelSuggestion(hotel) {
        if (!hotel || !hotel.label) {
            return;
        }

        const hotelInput = document.getElementById('bookingHotelName');
        if (hotelInput) {
            hotelInput.value = hotel.label;
        }

        bookingHotelLookupMap[hotel.label] = hotel;
        setSelectedHotelMeta(hotel);
        hideHotelSuggestionMenu();
        loadHotelRoomCategories(hotel.id, hotel.room_type || '');
        updateBookingOverview();
    }

    function renderHotelSuggestionMenu(hotels) {
        const menu = document.getElementById('hotelSuggestionMenu');
        if (!menu) {
            return;
        }

        menu.innerHTML = '';
        if (!Array.isArray(hotels) || hotels.length === 0) {
            hideHotelSuggestionMenu();
            return;
        }

        hotels.forEach((hotel) => {
            const item = document.createElement('div');
            item.className = 'hotel-suggestion-item';
            item.innerHTML =
                `<div class="hotel-suggestion-title">${hotel.label}</div><div class="hotel-suggestion-sub">${hotel.category || 'Property'} • ${hotel.location || 'Location N/A'}</div>`;
            item.addEventListener('mousedown', (event) => {
                event.preventDefault();
                selectHotelSuggestion(hotel);
            });
            menu.appendChild(item);
        });

        showHotelSuggestionMenu();
    }

    function populateHotelSuggestions(hotels) {
        const datalist = document.getElementById('bookingHotelOptions');
        if (!datalist) {
            return;
        }

        datalist.innerHTML = '';
        bookingHotelLookupMap = {};

        hotels.forEach((hotel) => {
            if (!hotel || !hotel.label) {
                return;
            }
            const option = document.createElement('option');
            option.value = hotel.label;
            datalist.appendChild(option);
            bookingHotelLookupMap[hotel.label] = hotel;
        });

        renderHotelSuggestionMenu(hotels);
    }

    function resetRoomCategoryDropdown() {
        const roomSelect = document.getElementById('bookRoomCategory');
        if (!roomSelect) {
            return;
        }
        roomSelect.innerHTML = '<option value="" selected disabled>Select room category...</option>';
    }

    function setSelectedHotelMeta(hotelMeta) {
        const hotelIdInput = document.getElementById('bookingHotelId');
        const roomTypeInput = document.getElementById('bookingHotelRoomType');
        if (hotelIdInput) {
            hotelIdInput.value = hotelMeta && hotelMeta.id ? String(hotelMeta.id) : '';
        }
        if (roomTypeInput) {
            roomTypeInput.value = hotelMeta && hotelMeta.room_type ? String(hotelMeta.room_type) : '';
        }
    }

    function loadHotelRoomCategories(hotelId, roomTypeRaw = '') {
        if (!hotelId) {
            resetRoomCategoryDropdown();
            return;
        }

        const formData = new FormData();
        formData.append('action', 'get_hotel_room_categories');
        formData.append('hotelId', String(hotelId));
        formData.append('roomType', roomTypeRaw || '');

        fetch('employee-dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then((response) => response.json())
            .then((data) => {
                if (!data.success || !Array.isArray(data.categories)) {
                    throw new Error('Unable to load categories');
                }

                const roomSelect = document.getElementById('bookRoomCategory');
                if (!roomSelect) {
                    return;
                }

                roomSelect.innerHTML = '';
                data.categories.forEach((category, idx) => {
                    const option = document.createElement('option');
                    option.value = category;
                    option.textContent = category;
                    if (idx === 0) {
                        option.selected = true;
                    }
                    roomSelect.appendChild(option);
                });
                updateBookingOverview();
            })
            .catch(() => {
                resetRoomCategoryDropdown();
                showErrorToast('Room categories load nahi ho paayi.');
            });
    }

    function resolveSelectedHotelAndLoadCategories() {
        const hotelLabel = (document.getElementById('bookingHotelName')?.value || '').trim();
        const hotelMeta = bookingHotelLookupMap[hotelLabel] || bookingHotelCatalog[hotelLabel] || null;
        if (!hotelMeta || !hotelMeta.id) {
            setSelectedHotelMeta(null);
            resetRoomCategoryDropdown();
            return null;
        }

        setSelectedHotelMeta(hotelMeta);
        loadHotelRoomCategories(hotelMeta.id, hotelMeta.room_type || '');
        return hotelMeta;
    }

    function searchHotelsLive(keyword) {
        const query = (keyword || '').trim();
        if (query.length < 1) {
            populateHotelSuggestions([]);
            setSelectedHotelMeta(null);
            resetRoomCategoryDropdown();
            hideHotelSuggestionMenu();
            return;
        }

        clearTimeout(bookingHotelLookupTimer);
        bookingHotelLookupTimer = setTimeout(() => {
            const formData = new FormData();
            formData.append('action', 'search_hotels');
            formData.append('keyword', query);

            fetch('employee-dashboard.php', {
                    method: 'POST',
                    body: formData
                })
                .then((response) => response.json())
                .then((data) => {
                    if (!data.success || !Array.isArray(data.hotels)) {
                        throw new Error('Hotel search failed');
                    }
                    populateHotelSuggestions(data.hotels);
                })
                .catch(() => {
                    populateHotelSuggestions([]);
                    hideHotelSuggestionMenu();
                });
        }, 300);
    }

    function updateBookingOverview() {
        const getNumber = (id) => {
            const val = Number(document.getElementById(id)?.value || 0);
            return Number.isFinite(val) ? val : 0;
        };
        const getText = (id, fallback = '') => {
            const val = (document.getElementById(id)?.value || '').trim();
            return val || fallback;
        };

        const hotelLabel = getText('bookingHotelName', '[Hotel]');
        const roomCategory = getText('bookRoomCategory', '[Room]');
        const roomCount = Math.max(getNumber('bookRoomsCount'), 0);
        const adults = Math.max(getNumber('bookPersons'), 0);
        const child = Math.max(getNumber('bookChild'), 0);
        const extra = Math.max(getNumber('bookExtraPerson'), 0);
        const mealPlan = getText('bookMealPlan', '[Meal Plan]');

        const checkIn = document.getElementById('bookCheckInDate')?.value || '';
        const checkOut = document.getElementById('bookCheckOutDate')?.value || '';
        let nights = 0;

        if (checkIn && checkOut) {
            const start = new Date(checkIn);
            const end = new Date(checkOut);
            const diffMs = end.getTime() - start.getTime();
            nights = Math.max(Math.floor(diffMs / (1000 * 60 * 60 * 24)), 0);
        }

        const baseRate = 4500;
        const mealRateMap = {
            'EP (Room Only)': 0,
            'CP (Breakfast)': 500,
            'MAP (Breakfast + Dinner)': 1000,
            'AP (All Meals)': 1500
        };
        const mealRate = mealRateMap[mealPlan] || 0;

        let calculated = 0;
        if (roomCount > 0 && nights > 0) {
            calculated = ((baseRate + mealRate) * roomCount * nights) + (((child * 1000) + (extra * 1500)) * nights);
        }

        const overview =
            `${hotelLabel} | ${roomCount}x ${roomCategory} | ${nights} Night(s) | ${adults} Adult(s), ${child} Child(ren), ${extra} Extra | ${mealPlan}`;
        const overviewEl = document.getElementById('dispBookingOverview');
        if (overviewEl) {
            if (hotelLabel !== '[Hotel]' && nights > 0) {
                overviewEl.innerHTML =
                    `${overview}<br><span class="text-primary fw-bold d-block mt-2 fs-6"><i class="bi bi-calculator me-1"></i>Calculated Estimate: ₹${calculated.toLocaleString('en-IN')}</span>`;
            } else {
                overviewEl.textContent = 'Please fill out details to see overview.';
            }
        }

        const totalAmountInput = document.getElementById('bookTotalAmount');
        if (totalAmountInput && !totalAmountInput.dataset.userEdited) {
            totalAmountInput.value = calculated > 0 ? String(calculated) : '';
        }
    }

    let bookingAgentLookupTimer = null;

    function lookupBookingAgent(mobile) {
        const statusEl = document.getElementById('bookingAgentStatus');
        const boxEl = document.getElementById('bookingAgentBox');
        const agentIdEl = document.getElementById('bookingAgentId');

        if (!mobile || mobile.length < 10) {
            if (statusEl) statusEl.textContent = 'Search an agent to continue.';
            if (boxEl) boxEl.style.display = 'none';
            if (agentIdEl) agentIdEl.value = '';
            return;
        }

        clearTimeout(bookingAgentLookupTimer);
        bookingAgentLookupTimer = setTimeout(() => {
            const formData = new FormData();
            formData.append('action', 'search_agent_by_mobile');
            formData.append('mobileNumber', mobile);

            fetch('employee-dashboard.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.message || 'Search failed');
                    }

                    if (data.found && data.agent) {
                        const agent = data.agent;
                        if (statusEl) statusEl.textContent = 'Agent found';
                        if (boxEl) boxEl.style.display = 'block';
                        if (agentIdEl) agentIdEl.value = agent.id || '';
                        const setText = (id, value) => {
                            const el = document.getElementById(id);
                            if (el) el.textContent = value || '-';
                        };
                        setText('bookingAgentName', agent.name);
                        setText('bookingAgentCompany', agent.company_name || 'N/A');
                        setText('bookingAgentGstNumber', agent.gst_number || 'N/A');
                        setText('bookingAgentLocation', agent.location || 'N/A');
                        setText('bookingAgentContact', agent.phone || mobile);
                        setText('bookingAgentEmail', agent.email || 'N/A');
                    } else {
                        if (statusEl) statusEl.textContent = 'Agent not found';
                        if (boxEl) boxEl.style.display = 'none';
                        if (agentIdEl) agentIdEl.value = '';
                    }
                })
                .catch(() => {
                    if (statusEl) statusEl.textContent = 'Search failed';
                    if (boxEl) boxEl.style.display = 'none';
                    if (agentIdEl) agentIdEl.value = '';
                });
        }, 250);
    }

    function filterMyBookings() {
        const searchTerm = (document.getElementById('myBookingsSearch')?.value || '').trim().toLowerCase();
        const bookingCode = (document.getElementById('myBookingCodeFilter')?.value || '').trim().toLowerCase();
        const bookingStatus = (document.getElementById('myBookingStatusFilter')?.value || '').trim();
        const paymentStatus = (document.getElementById('myPaymentStatusFilter')?.value || '').trim();
        const agentPhone = (document.getElementById('myAgentPhoneFilter')?.value || '').trim().toLowerCase();
        const fromDate = document.getElementById('myFromDateFilter')?.value || '';
        const toDate = document.getElementById('myToDateFilter')?.value || '';

        const table = document.getElementById('myBookingsTable');
        if (!table) return;
        const rows = table.querySelectorAll('tbody tr');
        let visibleCount = 0;

        rows.forEach(row => {
            const code = (row.dataset.bookingCode || '').toLowerCase();
            const agent = (row.dataset.agentPhone || '').toLowerCase();
            const bDate = row.dataset.bookingDate || '';
            const client = (row.dataset.client || '').toLowerCase();
            const hotel = (row.dataset.hotel || '').toLowerCase();
            const pStatus = (row.dataset.paymentStatus || '').trim();
            const bStatus = (row.dataset.bookingStatus || '').trim();

            let visible = true;

            if (searchTerm) {
                const hay = (code + ' ' + client + ' ' + hotel + ' ' + agent + ' ' + row.textContent).toLowerCase();
                if (!hay.includes(searchTerm)) visible = false;
            }

            if (bookingCode && !code.includes(bookingCode)) visible = false;
            if (bookingStatus && bStatus !== bookingStatus) visible = false;
            if (paymentStatus && pStatus !== paymentStatus) visible = false;
            if (agentPhone && !agent.includes(agentPhone)) visible = false;

            if ((fromDate || toDate) && bDate) {
                const dt = new Date(bDate);
                if (fromDate) {
                    const fd = new Date(fromDate + 'T00:00:00');
                    if (dt < fd) visible = false;
                }
                if (toDate) {
                    const td = new Date(toDate + 'T23:59:59');
                    if (dt > td) visible = false;
                }
            }

            row.style.display = visible ? '' : 'none';
            if (visible) visibleCount += 1;
        });

        const filterEmptyState = document.getElementById('myBookingsFilterEmpty');
        if (filterEmptyState) filterEmptyState.style.display = visibleCount === 0 ? 'block' : 'none';
    }

    function clearMyBookingFilters() {
        ['myBookingsSearch','myBookingCodeFilter','myBookingStatusFilter','myPaymentStatusFilter','myAgentPhoneFilter','myFromDateFilter','myToDateFilter'].forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            if (el.tagName === 'SELECT' || el.type === 'date') el.value = '';
            else el.value = '';
        });
        filterMyBookings();
    }

    function filterAgentBookings() {
        const searchTerm = (document.getElementById('agentBookingsSearch')?.value || '').trim().toLowerCase();
        const bookingCode = (document.getElementById('agentBookingCodeFilter')?.value || '').trim().toLowerCase();
        const bookingStatus = (document.getElementById('agentBookingStatusFilter')?.value || '').trim();

        const table = document.getElementById('bookingsTableBody');
        if (!table) return;
        const rows = table.querySelectorAll('tr');
        let visibleCount = 0;

        rows.forEach(row => {
            const code = (row.dataset.bookingCode || '').toLowerCase();
            const client = (row.dataset.client || '').toLowerCase();
            const hotel = (row.dataset.hotel || '').toLowerCase();
            const bStatus = (row.dataset.bookingStatus || '').trim();

            let visible = true;
            if (searchTerm) {
                const hay = (code + ' ' + client + ' ' + hotel + ' ' + row.textContent).toLowerCase();
                if (!hay.includes(searchTerm)) visible = false;
            }
            if (bookingCode && !code.includes(bookingCode)) visible = false;
            if (bookingStatus && bStatus !== bookingStatus) visible = false;

            row.style.display = visible ? '' : 'none';
            if (visible) visibleCount += 1;
        });

        const noBookingsMsg = document.getElementById('noBookingsMsg');
        if (noBookingsMsg) noBookingsMsg.style.display = visibleCount === 0 ? 'block' : 'none';
    }

    function clearAgentBookingFilters() {
        ['agentBookingsSearch','agentBookingCodeFilter','agentBookingStatusFilter'].forEach(id => {
            const el = document.getElementById(id); if (el) el.value = ''; });
        filterAgentBookings();
    }

    async function copyBookingData(buttonEl) {
        try {
            const raw = buttonEl.getAttribute('data-booking');
            if (!raw) {
                showErrorToast('Booking data not available');
                return;
            }

            const booking = JSON.parse(raw);
            const text = buildBookingShareText(booking);

            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(text);
            } else {
                const textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'fixed';
                textArea.style.left = '-9999px';
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
            }

            showToastMsg('Booking data copied. WhatsApp me paste karke share karein.');
        } catch (error) {
            console.error('Copy failed:', error);
            showErrorToast('Copy failed. Please try again.');
        }
    }

    // Create Agent Form
    document.getElementById('createAgentForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData();
        formData.append('action', 'create_agent');
        formData.append('agentName', document.getElementById('agentName').value);
        formData.append('companyName', document.getElementById('agentCompany').value);
        formData.append('gstNumber', document.getElementById('agentGstNumber').value);
        formData.append('email', document.getElementById('agentEmail').value);
        formData.append('contact', document.getElementById('agentPhone').value);
        formData.append('location', document.getElementById('agentLocation').value);

        fetch('employee-dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToastMsg(data.message);
                    this.reset();
                    setTimeout(() => {
                        showSection('dashboard-view');
                    }, 500);
                } else {
                    showErrorToast(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorToast('An error occurred while creating the agent');
            });
    });

    // Create Booking Form
    document.getElementById('createBookingForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const checkInValue = document.getElementById('bookCheckInDate').value;
        const checkOutValue = document.getElementById('bookCheckOutDate').value;
        const hotelMeta = resolveSelectedHotelAndLoadCategories() || null;
        const selectedHotelId = document.getElementById('bookingHotelId').value;

        const checkIn = new Date(checkInValue);
        const checkOut = new Date(checkOutValue);

        if (checkOut <= checkIn) {
            showErrorToast('Check-out date must be after check-in date');
            return;
        }

        if (!hotelMeta || !selectedHotelId) {
            showErrorToast('Please select a valid hotel from the list');
            return;
        }

        if (!document.getElementById('bookingAgentId').value) {
            showErrorToast('Please search and select a valid agent number first');
            return;
        }

        const roomCategory = document.getElementById('bookRoomCategory').value.trim();
        const mealPlan = document.getElementById('bookMealPlan').value;
        const childCount = Number(document.getElementById('bookChild').value || 0);
        const extraPersonCount = Number(document.getElementById('bookExtraPerson').value || 0);
        const adultCount = Number(document.getElementById('bookPersons').value || 1);
        const totalGuestCount = Math.max(adultCount + childCount + extraPersonCount, 1);

        const specialRequestRaw = document.getElementById('specialRequest').value.trim();
        const reservationMeta =
            `Room Category: ${roomCategory} | Meal Plan: ${mealPlan} | Child: ${childCount} | Extra Person: ${extraPersonCount}`;
        const finalSpecialRequest = specialRequestRaw ? `${specialRequestRaw} | ${reservationMeta}` :
            reservationMeta;

        const formData = new FormData();
        formData.append('action', 'create_booking');
        formData.append('clientName', document.getElementById('clientName').value);
        formData.append('clientPhone', document.getElementById('clientPhone').value);
        formData.append('clientEmail', document.getElementById('clientEmail').value);
        formData.append('hotelId', String(selectedHotelId));
        formData.append('agentId', document.getElementById('bookingAgentId').value);
        formData.append('checkIn', checkInValue);
        formData.append('checkOut', checkOutValue);
        formData.append('bookingDate', document.getElementById('bookingDate').value);
        formData.append('bookingSource', document.getElementById('bookingSource').value);
        formData.append('amount', document.getElementById('bookTotalAmount').value);
        formData.append('paidAmount', document.getElementById('bookAdvancePaid').value || 0);
        formData.append('guestCount', String(totalGuestCount));
        formData.append('roomCount', document.getElementById('bookRoomsCount').value || 1);
        formData.append('specialRequest', finalSpecialRequest);
        formData.append('paymentNote', document.getElementById('paymentNote').value || '');
        formData.append('status', document.getElementById('bookingStatus').value);

        fetch('employee-dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToastMsg(data.message);
                    this.reset();
                    const checkoutInput = document.getElementById('bookCheckOutDate');
                    if (checkoutInput) {
                        checkoutInput.disabled = true;
                        checkoutInput.value = '';
                    }
                    const overviewEl = document.getElementById('dispBookingOverview');
                    if (overviewEl) {
                        overviewEl.textContent = 'Please fill out details to see overview.';
                    }
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showErrorToast(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorToast('An error occurred while creating the booking');
            });
    });

    // Reservation details interactions
    document.getElementById('bookingHotelName').addEventListener('input', function() {
        searchHotelsLive(this.value);
        setSelectedHotelMeta(null);
        resetRoomCategoryDropdown();
        updateBookingOverview();
    });

    document.getElementById('bookingHotelName').addEventListener('focus', function() {
        if (this.value.trim().length > 0) {
            searchHotelsLive(this.value);
        }
    });

    document.getElementById('bookingHotelName').addEventListener('change', function() {
        resolveSelectedHotelAndLoadCategories();
        updateBookingOverview();
    });

    document.getElementById('bookingHotelName').addEventListener('blur', function() {
        setTimeout(() => {
            const hotelLabel = (this.value || '').trim();
            const hotelMeta = bookingHotelLookupMap[hotelLabel] || null;
            if (hotelMeta && hotelMeta.id) {
                setSelectedHotelMeta(hotelMeta);
                loadHotelRoomCategories(hotelMeta.id, hotelMeta.room_type || '');
            }
            hideHotelSuggestionMenu();
        }, 120);
    });

    document.addEventListener('click', function(event) {
        const wrap = document.querySelector('.hotel-search-wrap');
        if (!wrap) {
            return;
        }
        if (!wrap.contains(event.target)) {
            hideHotelSuggestionMenu();
        }
    });

    // Hide query hotel suggestions when clicking outside
    document.addEventListener('click', function(event) {
        const queryHotelInput = document.getElementById('queryHotelName');
        const queryMenu = document.getElementById('queryHotelSuggestionMenu');
        if (queryHotelInput && queryMenu && !queryHotelInput.contains(event.target) && !queryMenu.contains(event
                .target)) {
            hideQueryHotelSuggestionMenu();
        }
    });

    document.getElementById('bookRoomCategory').addEventListener('input', updateBookingOverview);
    document.getElementById('bookRoomsCount').addEventListener('input', updateBookingOverview);
    document.getElementById('bookPersons').addEventListener('input', updateBookingOverview);
    document.getElementById('bookChild').addEventListener('input', updateBookingOverview);
    document.getElementById('bookExtraPerson').addEventListener('input', updateBookingOverview);
    document.getElementById('bookMealPlan').addEventListener('change', updateBookingOverview);

    document.getElementById('bookTotalAmount').addEventListener('input', function() {
        this.dataset.userEdited = this.value.trim() !== '' ? '1' : '';
    });

    document.getElementById('bookCheckInDate').addEventListener('change', function() {
        const checkOut = document.getElementById('bookCheckOutDate');
        if (this.value) {
            const nextDay = new Date(this.value);
            nextDay.setDate(nextDay.getDate() + 1);
            checkOut.min = nextDay.toISOString().split('T')[0];
            checkOut.value = nextDay.toISOString().split('T')[0];
            checkOut.disabled = false;
        } else {
            checkOut.value = '';
            checkOut.disabled = true;
        }
        updateBookingOverview();
    });

    document.getElementById('bookCheckOutDate').addEventListener('change', function() {
        const checkIn = document.getElementById('bookCheckInDate').value;
        if (checkIn && this.value && this.value <= checkIn) {
            showErrorToast('Check-out date must be strictly after check-in date');
            const nextDay = new Date(checkIn);
            nextDay.setDate(nextDay.getDate() + 1);
            this.value = nextDay.toISOString().split('T')[0];
        }
        updateBookingOverview();
    });

    // Search Agent by Mobile Number
    function searchAgentByMobile(event) {
        event.preventDefault();
        const mobile = document.getElementById('searchMobile').value.trim();

        if (!mobile) {
            showErrorToast('Please enter a mobile number');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'search_agent_by_mobile');
        formData.append('mobileNumber', mobile);

        fetch('employee-dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.found) {
                    // Agent found
                    displayAgentDetails(data.agent, data.bookings, data.booking_count);
                } else if (data.success && !data.found) {
                    // Agent not found - show registration form
                    displayAgentNotFound(mobile);
                } else {
                    showErrorToast(data.message || 'Error searching for agent');
                }
                document.getElementById('searchResults').style.display = 'block';
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorToast('An error occurred while searching');
            });
    }

    function displayAgentDetails(agent, bookings, bookingCount) {
        // Hide not found section
        document.getElementById('agentNotFoundSection').style.display = 'none';
        document.getElementById('agentFoundSection').style.display = 'block';

        // Populate agent details
        document.getElementById('agentNameDisplay').textContent = agent.name;
        const avatar = document.getElementById('agentAvatarDisplay');
        if (avatar) {
            avatar.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(agent.name || 'Agent')}&background=dbeafe&color=1d4ed8&size=96`;
        }
        document.getElementById('agentPhoneDisplay').textContent = agent.phone;
        document.getElementById('agentEmailDisplay').textContent = agent.email;
        document.getElementById('agentGstDisplay').textContent = agent.gst_number || 'N/A';
        document.getElementById('agentCompanyDisplay').textContent = agent.company_name || 'N/A';
        document.getElementById('agentLocationDisplay').textContent = agent.location;
        document.getElementById('totalBookingsCount').textContent = bookingCount;

        // Populate bookings table
        const tbody = document.getElementById('bookingsTableBody');
        const noMsg = document.getElementById('noBookingsMsg');

        if (bookings.length === 0) {
            tbody.innerHTML = '';
            noMsg.style.display = 'block';
        } else {
            noMsg.style.display = 'none';
            tbody.innerHTML = bookings.map(booking => `
                    <tr data-booking-code="${booking.booking_code || ''}" data-agent-phone="${agent.phone || ''}" data-booking-date="${booking.booking_date || ''}" data-client="${booking.client_name || ''}" data-hotel="${booking.hotel_name || ''}" data-payment-status="${booking.payment_status || ''}" data-booking-status="${booking.status || ''}">
                        <td><span class="fw-bold">${booking.booking_code}</span></td>
                        <td>${booking.client_name}</td>
                        <td>${booking.hotel_name || 'N/A'}</td>
                        <td>${booking.check_in}</td>
                        <td>${booking.check_out}</td>
                        <td><span class="text-success fw-bold">₹${parseInt(booking.amount).toLocaleString()}</span></td>
                        <td><span class="badge bg-success">${booking.status}</span></td>
                    </tr>
                `).join('');
        }
    }

    function displayAgentNotFound(mobile) {
        // Hide found section
        document.getElementById('agentFoundSection').style.display = 'none';
        document.getElementById('agentNotFoundSection').style.display = 'block';

        // Pre-fill mobile number in registration form
        document.getElementById('regAgentPhone').value = mobile;
    }

    // Register new agent from search form
    function registerNewAgent(event) {
        event.preventDefault();

        const formData = new FormData();
        formData.append('action', 'create_agent');
        formData.append('agentName', document.getElementById('regAgentName').value);
        formData.append('companyName', document.getElementById('regAgentCompany').value);
        formData.append('gstNumber', document.getElementById('regAgentGstNumber').value);
        formData.append('email', document.getElementById('regAgentEmail').value);
        formData.append('contact', document.getElementById('regAgentPhone').value);
        formData.append('location', document.getElementById('regAgentLocation').value);

        fetch('employee-dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToastMsg('Agent registered successfully! Now searching...');
                    document.getElementById('registerAgentForm').reset();
                    setTimeout(() => {
                        searchAgentByMobile(new Event('submit', {
                            bubbles: true,
                            cancelable: true
                        }));
                    }, 800);
                } else {
                    showErrorToast(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorToast('An error occurred while registering the agent');
            });
    }

    // Booking Query Functions
    let querySearchTimer = null;
    let currentQueryAgent = null;

    function autoSearchAgent(phoneNumber) {
        clearTimeout(querySearchTimer);
        const statusDiv = document.getElementById('agentSearchStatus');

        phoneNumber = phoneNumber.trim();

        if (!phoneNumber) {
            statusDiv.style.display = 'none';
            document.getElementById('queryResult').style.display = 'none';
            return;
        }

        // Show loading status
        statusDiv.style.display = 'block';
        document.getElementById('agentSearchLoading').style.display = 'block';
        document.getElementById('agentSearchFound').style.display = 'none';
        document.getElementById('agentSearchNotFound').style.display = 'none';

        querySearchTimer = setTimeout(() => {
            fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        action: 'generate_query',
                        agentPhone: phoneNumber
                    })
                })
                .then(response => response.json())
                .then(data => {
                    document.getElementById('agentSearchLoading').style.display = 'none';

                    if (data.success) {
                        // Agent found - show booking query form
                        document.getElementById('agentSearchFound').style.display = 'block';
                        document.getElementById('foundAgentName').textContent = data.agent_name;
                        document.getElementById('queryAgentName').textContent = data.agent_name;
                        currentQueryAgent = data.agent_name;

                        // Show the booking query form
                        document.getElementById('queryResult').style.display = 'block';
                        document.getElementById('generatedQueryDisplay').style.display = 'none';

                        loadQueryHistory();
                    } else {
                        // Agent not found
                        document.getElementById('agentSearchNotFound').style.display = 'block';
                        document.getElementById('queryResult').style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('agentSearchLoading').style.display = 'none';
                    document.getElementById('agentSearchNotFound').style.display = 'block';
                });
        }, 600);
    }

    function populateQueryHotels(hotels) {
        // No longer needed since we use suggestion menu
    }

    function handleQueryHotelInput(value) {
        const query = (value || '').trim();

        if (!query) {
            queryHotelLookupMap = {};
            hideQueryHotelSuggestionMenu();
            return;
        }

        clearTimeout(queryHotelLookupTimer);
        queryHotelLookupTimer = setTimeout(() => {
            const formData = new FormData();
            formData.append('action', 'search_hotels');
            formData.append('keyword', query);

            fetch('employee-dashboard.php', {
                    method: 'POST',
                    body: formData
                })
                .then((response) => response.json())
                .then((data) => {
                    if (!data.success || !Array.isArray(data.hotels)) {
                        throw new Error('Hotel search failed');
                    }
                    queryHotelLookupMap = {};
                    data.hotels.forEach((hotel) => {
                        if (hotel && hotel.label) {
                            queryHotelLookupMap[hotel.label] = hotel;
                        }
                    });
                    renderQueryHotelSuggestionMenu(data.hotels);
                })
                .catch(() => {
                    const tokens = query.toLowerCase().split(/\s+/).map((v) => v.trim()).filter(Boolean);
                    const fallbackHotels = Object.entries(bookingHotelCatalog)
                        .filter(([label, hotel]) => {
                            const haystack = [
                                label || '',
                                hotel?.name || '',
                                hotel?.location || '',
                                hotel?.category || '',
                                hotel?.hotel_code || ''
                            ].join(' ').toLowerCase();
                            return tokens.every((token) => haystack.includes(token));
                        })
                        .map(([label, hotel]) => ({
                            ...hotel,
                            label
                        }));

                    queryHotelLookupMap = {};
                    fallbackHotels.forEach((hotel) => {
                        if (hotel && hotel.label) {
                            queryHotelLookupMap[hotel.label] = hotel;
                        }
                    });

                    if (fallbackHotels.length > 0) {
                        renderQueryHotelSuggestionMenu(fallbackHotels);
                    } else {
                        hideQueryHotelSuggestionMenu();
                    }
                });
        }, 300);
    }

    function hideQueryHotelSuggestionMenu() {
        const menu = document.getElementById('queryHotelSuggestionMenu');
        if (menu) {
            menu.style.display = 'none';
        }
    }

    function showQueryHotelSuggestionMenu() {
        const menu = document.getElementById('queryHotelSuggestionMenu');
        if (menu && menu.children.length > 0) {
            menu.style.display = 'block';
        }
    }

    function renderQueryHotelSuggestionMenu(hotels) {
        const menu = document.getElementById('queryHotelSuggestionMenu');
        if (!menu) {
            return;
        }

        menu.innerHTML = '';
        if (!Array.isArray(hotels) || hotels.length === 0) {
            hideQueryHotelSuggestionMenu();
            return;
        }

        hotels.forEach((hotel) => {
            const item = document.createElement('div');
            item.className = 'hotel-suggestion-item';
            item.innerHTML =
                `<div class="hotel-suggestion-title">${hotel.name}</div><div class="hotel-suggestion-sub">${hotel.category || 'Property'} • ${hotel.location || 'Location N/A'}</div>`;
            item.addEventListener('mousedown', (event) => {
                event.preventDefault();
                selectQueryHotelSuggestion(hotel);
            });
            menu.appendChild(item);
        });

        showQueryHotelSuggestionMenu();
    }

    function selectQueryHotelSuggestion(hotel) {
        if (!hotel || !hotel.label) {
            return;
        }

        const input = document.getElementById('queryHotelName');
        if (input) {
            input.value = hotel.label;
        }

        document.getElementById('queryHotelId').value = hotel.id;
        queryHotelLookupMap[hotel.label] = hotel;
        loadQueryRoomCategories(hotel.id);
        hideQueryHotelSuggestionMenu();
    }

    function loadQueryRoomCategories(hotelId = null) {
        const targetHotelId = Number(hotelId || document.getElementById('queryHotelId').value || 0);
        const roomSelect = document.getElementById('queryRoomCategory');
        roomSelect.innerHTML = '<option value="" selected disabled>Select room category...</option>';

        if (!targetHotelId) {
            document.getElementById('queryTotalAmount').value = '0';
            return;
        }

        const formData = new FormData();
        formData.append('action', 'get_hotel_room_categories');
        formData.append('hotelId', String(targetHotelId));

        fetch('employee-dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then((response) => response.json())
            .then((data) => {
                if (!data.success || !Array.isArray(data.categories)) {
                    throw new Error('Unable to load categories');
                }

                roomSelect.innerHTML = '<option value="" selected disabled>Select room category...</option>';
                data.categories.forEach((roomType) => {
                    const option = document.createElement('option');
                    option.value = roomType;
                    option.textContent = roomType;
                    roomSelect.appendChild(option);
                });

                if (roomSelect.options.length > 1) {
                    roomSelect.selectedIndex = 1;
                }
                calculateQueryTotalAmount();
            })
            .catch(() => {
                roomSelect.innerHTML = '<option value="" selected disabled>Select room category...</option>';
                document.getElementById('queryTotalAmount').value = '0';
            });

        // Reset total when hotel changes
        document.getElementById('queryTotalAmount').value = '0';

        // Add event listener to room category dropdown for calculation
        roomSelect.removeEventListener('change', calculateQueryTotalAmount);
        roomSelect.addEventListener('change', calculateQueryTotalAmount);
    }

    // ── Booking Query Details: Location + Category + Dates + Budget → DB-backed shortlist (admin uses the same backend) ──
    let bookingQueryLastResults = [];

    function formatBookingMealPlans(prices) {
        const labels = { EP: 'EP - Room Only', CP: 'CP - Breakfast Included', MAP: 'MAP - Breakfast + Dinner', AP: 'AP - All Meals' };
        return Object.entries(prices || {}).map(([code, price]) => `${labels[code] || code} (₹${Number(price || 0).toLocaleString('en-IN')}/night)`).join(', ') || 'EP - Room Only';
    }

    function calculateBookingNights(checkInId, checkOutId, nightsId) {
        const checkIn = document.getElementById(checkInId);
        const checkOut = document.getElementById(checkOutId);
        const nights = document.getElementById(nightsId);
        if (!checkIn || !checkOut || !nights) return;

        if (!checkIn.value) {
            checkOut.min = '';
            nights.value = 0;
            return;
        }

        const [year, month, day] = checkIn.value.split('-').map(Number);
        const nextDay = new Date(Date.UTC(year, month - 1, day + 1)).toISOString().slice(0, 10);
        checkOut.min = nextDay;
        if (!checkOut.value || checkOut.value < nextDay) checkOut.value = nextDay;
        checkIn.blur();

        if (!checkOut.value) {
            nights.value = 0;
            return;
        }

        const [outYear, outMonth, outDay] = checkOut.value.split('-').map(Number);
        const diffDays = Math.round((Date.UTC(outYear, outMonth - 1, outDay) - Date.UTC(year, month - 1, day)) / 86400000);
        nights.value = diffDays > 0 ? diffDays : 0;
    }

    let bookingQueryAgent = null;
    let bookingQueryType = 'agent';

    function setBookingQueryDetailsDisabled(disabled) {
        const detailsFields = document.getElementById('bookingQueryDetailsFields');
        if (detailsFields) detailsFields.disabled = disabled;
    }

    document.querySelectorAll('.query-required-field').forEach((field) => {
        const clearInvalid = () => field.classList.toggle('is-invalid', String(field.value).trim() === '');
        field.addEventListener('input', clearInvalid);
        field.addEventListener('change', clearInvalid);
    });

    function setBookingQueryType(type) {
        bookingQueryType = 'agent';
        const agentBox = document.getElementById('bookingQueryAgentBox');
        if (agentBox) agentBox.style.display = 'block';
        setBookingQueryDetailsDisabled(!bookingQueryAgent);
    }

    function lookupBookingQueryAgent() {
        const phone = document.getElementById('bookingQueryAgentPhone')?.value.trim() || '';
        const status = document.getElementById('bookingQueryAgentStatus');
        if (bookingQueryAgent && bookingQueryAgent.phone !== phone) bookingQueryAgent = null;
        setBookingQueryDetailsDisabled(true);
        if (!phone) {
            bookingQueryAgent = null;
            if (status) status.textContent = 'Please enter the Agent Mobile Number before generating the query.';
            return;
        }
        if (status) status.textContent = 'Searching agent...';
        fetch('employee-dashboard.php', { method: 'POST', body: new URLSearchParams({ action: 'search_agent_by_mobile', mobileNumber: phone }) })
            .then((response) => response.json())
            .then((data) => {
                if (!data.success || !data.found) {
                    bookingQueryAgent = null;
                    setBookingQueryDetailsDisabled(true);
                    if (status) {
                        status.textContent = data.message || 'Agent not found. Please enter a registered Agent Mobile Number.';
                        status.className = 'small text-danger mt-2';
                    }
                    return;
                }
                bookingQueryAgent = data.agent;
                setBookingQueryDetailsDisabled(false);
                if (status) status.className = 'small text-success mt-2';
                                if (status) status.innerHTML = `<strong>${escapeQueryHistoryHtml(data.agent.name)}</strong> | ${escapeQueryHistoryHtml(data.agent.phone)} | GST: ${escapeQueryHistoryHtml(data.agent.gst_number || 'N/A')} | ${escapeQueryHistoryHtml(data.agent.location || 'Location unavailable')} | ${escapeQueryHistoryHtml(data.agent.company_name || '')} | ${escapeQueryHistoryHtml(data.agent.email || '')}`;
            })
            .catch(() => {
                bookingQueryAgent = null;
                setBookingQueryDetailsDisabled(true);
                if (status) status.textContent = 'Unable to fetch agent details.';
            });
    }

            setBookingQueryType('agent');

    function generateBookingQueryResults() {
        const requiredFields = [
            ['bookingQueryLocation', 'Location'], ['bookingQueryHotelCategory', 'Hotel Category'],
            ['bookingQueryCheckIn', 'Check-In'], ['bookingQueryCheckOut', 'Check-out'],
            ['bookingQueryAdults', 'Adults'], ['bookingQueryChildren', 'Children'], ['bookingQueryRooms', 'Rooms']
        ];
        for (const [id, label] of requiredFields) {
            const field = document.getElementById(id);
            field?.classList.toggle('is-invalid', !field || String(field.value).trim() === '');
            if (!field || String(field.value).trim() === '') {
                showErrorToast(`${label} is required.`);
                field?.focus();
                return;
            }
        }
        if (!document.getElementById('bookingQueryAgentPhone')?.value.trim()) {
            showErrorToast('Please enter the Agent Mobile Number before generating the query.');
            return;
        }
        if (!bookingQueryAgent) {
            showErrorToast('Agent not found. Please enter a registered Agent Mobile Number.');
            return;
        }
        const location = document.getElementById('bookingQueryLocation')?.value.trim() || '';
        const category = document.getElementById('bookingQueryHotelCategory')?.value || '';
        const checkIn = document.getElementById('bookingQueryCheckIn')?.value || '';
        const checkOut = document.getElementById('bookingQueryCheckOut')?.value || '';
        const nights = Number(document.getElementById('bookingQueryNights')?.value || 0);
        const adults = Number(document.getElementById('bookingQueryAdults')?.value || 1);
        const children = Number(document.getElementById('bookingQueryChildren')?.value || 0);
        const rooms = Number(document.getElementById('bookingQueryRooms')?.value || 1);
        const budget = Number(document.getElementById('bookingQueryBudget')?.value || 0);

        const resultWrap = document.getElementById('bookingQueryResultsWrap');
        const resultBody = document.getElementById('bookingQueryResultsBody');
        if (!resultWrap || !resultBody) return;

        const formData = new FormData();
        formData.append('action', 'filter_hotels_for_query');
        formData.append('location', location);
        formData.append('category', category);
        formData.append('check_in', checkIn);
        formData.append('check_out', checkOut);
        formData.append('nights', String(nights));
        formData.append('adults', String(adults));
        formData.append('children', String(children));
        formData.append('rooms', String(rooms));
        formData.append('budget', String(budget));

        resultWrap.style.display = 'block';
        resultBody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">Searching hotels...</td></tr>';

        fetch('employee-dashboard.php', { method: 'POST', body: formData })
            .then((response) => response.json())
            .then((data) => {
                if (!data.success) {
                    throw new Error(data.message || 'Unable to load matching properties');
                }
                const results = Array.isArray(data.results) ? data.results : [];
                bookingQueryLastResults = results;

                if (budget > 0) {
                    results.sort((a, b) => Math.abs((a.est_budget || a.min_price || 0) - budget) - Math.abs((b.est_budget || b.min_price || 0) - budget));
                } else {
                    results.sort((a, b) => (a.total_min || 0) - (b.total_min || 0));
                }

                resultBody.innerHTML = results.length
                    ? results.flatMap((hotel) => (hotel.rooms || []).map((room) => {
                        const mealPlans = formatBookingMealPlans(room.prices);
                        const nightlyPrice = Number(room.prices?.EP || hotel.est_budget || hotel.min_price || 0);
                        const roomIndex = hotel.rooms.indexOf(room);
                        const selectionKey = `${hotel.id}::${roomIndex}`;
                        return `
                        <tr data-hotel-name="${String(hotel.name || '').toLowerCase()}">
                            <td><input class="form-check-input hotel-checkbox" type="checkbox" value="${selectionKey}" id="hotel_${selectionKey}"></td>
                            <td><label for="hotel_${selectionKey}">${hotel.name}</label></td>
                            <td>${room.name || 'N/A'}</td>
                            <td>${mealPlans}</td>
                            <td>${hotel.location || hotel.city || 'N/A'}</td>
                            <td>₹${nightlyPrice.toLocaleString('en-IN')}</td>
                            <td>${checkIn || 'N/A'}</td>
                            <td>${checkOut || 'N/A'}</td>
                        </tr>
                    `;
                    })).join('')
                    : '<tr><td colspan="9" class="text-center text-muted py-4">No active hotels match this location/category/budget.</td></tr>';

                if (bookingQueryType === 'agent' && results.length) {
                    fetch('employee-dashboard.php', {
                        method: 'POST',
                        body: new URLSearchParams({ action: 'acquire_booking_query_agent_lock', agent_phone: bookingQueryAgent.phone })
                    }).then((lockResponse) => lockResponse.json()).then((lockData) => {
                        if (!lockData.success) {
                            bookingQueryLastResults = [];
                            resultBody.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-4">${lockData.message || 'Agent is currently unavailable.'}</td></tr>`;
                            showErrorToast(lockData.message || 'Agent is currently unavailable.');
                        }
                    }).catch(() => showErrorToast('Unable to verify the agent lock. Please try again.'));
                }

            })
            .catch((error) => {
                console.error('Hotel filter error:', error);
                bookingQueryLastResults = [];
                resultBody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">Unable to load hotels from database.</td></tr>';
            });
    }

    function filterBookingQueryResults() {
        const query = (document.getElementById('bookingQueryHotelSearch')?.value || '').trim().toLowerCase();
        document.querySelectorAll('#bookingQueryResultsBody tr[data-hotel-name]').forEach((row) => {
            row.style.display = !query || row.dataset.hotelName.includes(query) ? '' : 'none';
        });
    }

    function selectBookingQueryRows(limit) {
        const boxes = [...document.querySelectorAll('#bookingQueryResultsBody .hotel-checkbox')].filter((box) => box.closest('tr')?.style.display !== 'none');
        boxes.forEach((box) => box.checked = false);
        if (limit === 'all') {
            boxes.forEach((box) => box.checked = true);
            return;
        }
        [...boxes].slice(0, Number(limit) || 0).forEach((box) => box.checked = true);
    }

    function clearBookingQueryRows() {
        document.querySelectorAll('#bookingQueryResultsBody .hotel-checkbox').forEach((box) => box.checked = false);
    }

    function buildBookingQueryShareText(selectedIds) {
        const location = document.getElementById('bookingQueryLocation')?.value.trim() || 'Any';
        const category = document.getElementById('bookingQueryHotelCategory')?.value || 'Any';
        const checkIn = document.getElementById('bookingQueryCheckIn')?.value || 'N/A';
        const checkOut = document.getElementById('bookingQueryCheckOut')?.value || 'N/A';
        const nights = document.getElementById('bookingQueryNights')?.value || '0';
        const adults = document.getElementById('bookingQueryAdults')?.value || '1';
        const children = document.getElementById('bookingQueryChildren')?.value || '0';
        const rooms = document.getElementById('bookingQueryRooms')?.value || '1';
        const budget = Number(document.getElementById('bookingQueryBudget')?.value || 0);

        const selectedRows = selectedIds.map((key) => {
            const [hotelId, roomIndex] = String(key).split('::');
            const hotel = bookingQueryLastResults.find((item) => String(item.id) === hotelId);
            const room = hotel?.rooms?.[Number(roomIndex)];
            return hotel && room ? { hotel, room } : null;
        }).filter(Boolean);
        return AirwaysQuotation.formatMany(selectedRows.map(({ hotel, room }) => ({
            queryNumber: window.employeeBookingQueryNumber,
            hotelName: hotel.name, hotelLocation: hotel.location || hotel.city || location,
            roomCategory: room.name || room.category, mealPlan: room.meal_plan || room.mealPlan || Object.keys(room.prices || {})[0],
            checkIn, checkOut, adults, children, rooms,
            roomPrice: room.prices?.EP || hotel.est_budget || hotel.min_price || budget,
            matchedHotels: [{ ...hotel, rooms: [room] }]
        })));
    }

    function saveSelectedBookingQueryHistory(selectedIds) {
        const location = document.getElementById('bookingQueryLocation')?.value.trim() || '';
        const category = document.getElementById('bookingQueryHotelCategory')?.value || '';
        const checkIn = document.getElementById('bookingQueryCheckIn')?.value || '';
        const checkOut = document.getElementById('bookingQueryCheckOut')?.value || '';
        const nights = Number(document.getElementById('bookingQueryNights')?.value || 0);
        const adults = Number(document.getElementById('bookingQueryAdults')?.value || 1);
        const children = Number(document.getElementById('bookingQueryChildren')?.value || 0);
        const rooms = Number(document.getElementById('bookingQueryRooms')?.value || 1);
        const budget = Number(document.getElementById('bookingQueryBudget')?.value || 0);
        const selectedRows = selectedIds.map((key) => {
            const [hotelId, roomIndex] = String(key).split('::');
            const hotel = bookingQueryLastResults.find((item) => String(item.id) === hotelId);
            const room = hotel?.rooms?.[Number(roomIndex)];
            return hotel && room ? { hotel, room } : null;
        }).filter(Boolean);
        const matchedHotels = selectedRows.map(({ hotel, room }) => ({
            name: hotel.name,
            hotel_code: hotel.hotel_code || '',
            category: hotel.category || '',
            room_name: room.name,
            bed_type: room.bed_type || '',
            room_size: room.room_size || '',
            prices: room.prices || {},
            location: hotel.location || hotel.city || '',
            address: hotel.address || '',
            phone: hotel.phone || '',
            email: hotel.email || '',
            available_rooms: Number(room.available_rooms || 0),
            selected_price: Number(room.prices?.EP || hotel.est_budget || hotel.min_price || 0),
        }));

        return fetch('employee-dashboard.php', {
            method: 'POST',
            body: new URLSearchParams({
                action: 'save_booking_query_history', location, category, check_in: checkIn,
                check_out: checkOut, nights: String(nights), adults: String(adults),
                children: String(children), rooms: String(rooms), budget: String(budget),
                query_number: window.employeeBookingQueryNumber || AirwaysQuotation.generateQueryNumber(),
                query_type: bookingQueryType,
                agent_phone: bookingQueryAgent?.phone || '',
                matched_hotels: JSON.stringify(matchedHotels)
            })
        }).then((response) => response.json()).then((data) => {
            if (!data.success) throw new Error(data.message || 'Unable to save query history');
            return data;
        });
    }

    function sendSelectedBookingQueryQuotes() {
        const selected = [...document.querySelectorAll('#bookingQueryResultsBody .hotel-checkbox:checked')].map((box) => box.value);
        if (!selected.length) {
            alert('Please select at least one hotel.');
            return;
        }

        saveSelectedBookingQueryHistory(selected).then((data) => {
            window.employeeBookingQueryId = data.id;
            window.employeeBookingQueryNumber = data.query_number || window.employeeBookingQueryNumber;
            const message = buildBookingQueryShareText(selected);

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(message).then(() => {
                    alert(`${selected.length} hotel(s) quotation copied successfully. WhatsApp will not open automatically.`);
                }).catch(() => {
                    const textarea = document.createElement('textarea');
                    textarea.value = message;
                    document.body.appendChild(textarea);
                    textarea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textarea);
                    alert(`${selected.length} hotel(s) quotation copied successfully. WhatsApp will not open automatically.`);
                });
            } else {
                const textarea = document.createElement('textarea');
                textarea.value = message;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                alert(`${selected.length} hotel(s) quotation copied successfully. WhatsApp will not open automatically.`);
            }
        }).catch((error) => {
            console.error('Query history save error:', error);
            showErrorToast('Unable to save query history. Quotes were not sent.');
        });
    }

    const bqCheckInEl = document.getElementById('bookingQueryCheckIn');
    if (bqCheckInEl) {
        bqCheckInEl.addEventListener('change', () => calculateBookingNights('bookingQueryCheckIn', 'bookingQueryCheckOut', 'bookingQueryNights'));
    }
    const bqCheckOutEl = document.getElementById('bookingQueryCheckOut');
    if (bqCheckOutEl) {
        bqCheckOutEl.addEventListener('change', () => calculateBookingNights('bookingQueryCheckIn', 'bookingQueryCheckOut', 'bookingQueryNights'));
    }

    // ── Property Finder: Location + Category + Budget results, sorting, selection, WhatsApp share ──
    let pfResultsData = [];
    let pfSortMode = 'recommended';
    let pfSelectedMap = {};
    let pfLastBudget = 0;

    function generatePropertyResults() {
        const location = document.getElementById('pfLocation').value;
        const category = document.getElementById('pfCategory').value;
        const budget = document.getElementById('pfBudget').value;
        pfLastBudget = Number(budget) || 0;

        document.getElementById('pfResultsWrap').style.display = 'none';
        document.getElementById('pfLoading').style.display = 'block';

        const formData = new FormData();
        formData.append('action', 'filter_hotels_for_query');
        formData.append('location', location);
        formData.append('category', category);
        formData.append('budget', budget || '0');

        fetch('employee-dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then((response) => response.json())
            .then((data) => {
                document.getElementById('pfLoading').style.display = 'none';
                if (!data.success || !Array.isArray(data.results)) {
                    throw new Error(data.message || 'Unable to load properties');
                }
                pfResultsData = data.results;
                pfSortMode = 'recommended';
                document.querySelectorAll('.pf-sort-btn').forEach((btn) => {
                    btn.classList.toggle('active', btn.dataset.sort === 'recommended');
                });

                const subtitleParts = [];
                if (location) subtitleParts.push(location);
                if (category) subtitleParts.push(category);
                const subtitle = subtitleParts.length ? subtitleParts.join(' • ') : 'All Properties';
                document.getElementById('pfResultsTitle').textContent = `Hotel Options for ${subtitle}`;
                const budgetText = pfLastBudget > 0 ? ` around the customer's ₹${formatInr(pfLastBudget)} budget` : '';
                document.getElementById('pfResultsSubtitle').textContent =
                    `${pfResultsData.length} propert${pfResultsData.length === 1 ? 'y' : 'ies'} found${budgetText}`;

                document.getElementById('pfResultsWrap').style.display = 'block';
                renderPfResults();
            })
            .catch((error) => {
                document.getElementById('pfLoading').style.display = 'none';
                document.getElementById('pfResultsWrap').style.display = 'block';
                pfResultsData = [];
                renderPfResults();
                showErrorToast(error.message || 'Unable to load properties');
            });
    }

    function getSortedPfResults() {
        const list = pfResultsData.slice();
        if (pfSortMode === 'asc') {
            list.sort((a, b) => (a.min_price || 0) - (b.min_price || 0));
        } else if (pfSortMode === 'desc') {
            list.sort((a, b) => (b.min_price || 0) - (a.min_price || 0));
        } else {
            // Recommended: closest to the customer's budget first, cheapest first when no budget entered.
            list.sort((a, b) => {
                if (pfLastBudget > 0) {
                    const diffA = Math.abs((a.est_budget || a.min_price || 0) - pfLastBudget);
                    const diffB = Math.abs((b.est_budget || b.min_price || 0) - pfLastBudget);
                    if (diffA !== diffB) return diffA - diffB;
                }
                return (a.min_price || 0) - (b.min_price || 0);
            });
        }
        return list;
    }

    function setPriceSort(mode) {
        pfSortMode = mode;
        document.querySelectorAll('.pf-sort-btn').forEach((btn) => {
            btn.classList.toggle('active', btn.dataset.sort === mode);
        });
        renderPfResults();
    }

    // Room whose EP price is closest to the customer's budget (or cheapest room if no budget entered).
    function getPrimaryRoom(property) {
        const rooms = property.rooms || [];
        if (!rooms.length) return null;
        const withPrice = rooms.filter((r) => Number(r.prices?.EP) > 0);
        const pool = withPrice.length ? withPrice : rooms;
        if (pfLastBudget > 0) {
            return pool.reduce((best, room) => {
                const price = Number(room.prices?.EP) || Infinity;
                const bestPrice = Number(best.prices?.EP) || Infinity;
                return Math.abs(price - pfLastBudget) < Math.abs(bestPrice - pfLastBudget) ? room : best;
            }, pool[0]);
        }
        return pool.reduce((best, room) => {
            const price = Number(room.prices?.EP) || Infinity;
            const bestPrice = Number(best.prices?.EP) || Infinity;
            return price < bestPrice ? room : best;
        }, pool[0]);
    }

    function renderPfResults() {
        const grid = document.getElementById('pfResultsBody');
        const emptyState = document.getElementById('pfEmptyState');
        const sorted = getSortedPfResults();

        grid.innerHTML = '';
        emptyState.style.display = sorted.length === 0 ? 'block' : 'none';

        sorted.forEach((property, index) => {
            const isSelected = !!pfSelectedMap[property.id];
            const card = document.createElement('div');
            card.className = 'pf-property-card' + (isSelected ? ' selected' : '');
            card.dataset.id = property.id;

            const showBestMatch = pfSortMode === 'recommended' && pfLastBudget > 0 && index < 3;

            const roomsHtml = (property.rooms || []).slice(0, 6).map((room) => {
                const priceEntries = Object.entries(room.prices || {}).filter(([, price]) => Number(price) > 0);
                const priceText = priceEntries.length
                    ? priceEntries.map(([code, price]) => `${code} ₹${formatInr(price)}`).join(' • ')
                    : 'Price on request';
                const tags = [room.bed_type, room.room_size].filter(Boolean).join(' • ');
                return `
                    <div class="pf-room-row">
                        <div>
                            <div class="pf-room-name">${room.name}</div>
                            <div class="pf-room-tags">${tags || 'N/A'} • ${room.available_rooms ?? 0} available</div>
                        </div>
                        <div class="pf-room-price">${priceText}</div>
                    </div>
                `;
            }).join('');

            card.innerHTML = `
                ${showBestMatch ? '<span class="pf-best-match-badge"><i class="bi bi-star-fill me-1"></i>Best Match</span>' : ''}
                <div class="pf-property-card-hdr">
                    <div>
                        <div class="pf-property-name">${property.name}</div>
                        <div class="pf-property-sub"><i class="bi bi-geo-alt me-1"></i>${property.location || 'N/A'}</div>
                    </div>
                    <input type="checkbox" class="pf-select-checkbox pf-row-checkbox" data-id="${property.id}" ${isSelected ? 'checked' : ''}
                        onclick="event.stopPropagation();" onchange="togglePropertySelection(${property.id}, this.checked)">
                </div>
                <div class="pf-property-meta">
                    <span class="pf-meta-chip"><i class="bi bi-award"></i> ${property.category || 'N/A'}</span>
                    <span class="pf-meta-chip"><i class="bi bi-door-open"></i> ${property.available_rooms ?? 0} rooms available</span>
                    <span class="pf-meta-chip pf-budget-chip"><i class="bi bi-cash-coin"></i> From ₹${formatInr(property.min_price || 0)}</span>
                    ${pfLastBudget > 0 ? `<span class="pf-meta-chip pf-budget-chip"><i class="bi bi-bullseye"></i> Closest: ₹${formatInr(property.est_budget || 0)}</span>` : ''}
                </div>
                <div class="pf-room-list">${roomsHtml || '<div class="pf-property-sub">No active room categories found.</div>'}</div>
                <button type="button" class="btn btn-sm btn-outline-primary w-100 mt-2 pf-use-btn"
                    onclick="event.stopPropagation(); useMatchedPropertyForQuery(${property.id});">
                    <i class="bi bi-arrow-down-circle me-1"></i>Use This Property in Booking Query
                </button>
            `;

            card.addEventListener('click', (event) => {
                if (event.target.closest('.pf-select-checkbox') || event.target.closest('.pf-use-btn')) return;
                const checkbox = card.querySelector('.pf-select-checkbox');
                checkbox.checked = !checkbox.checked;
                togglePropertySelection(property.id, checkbox.checked);
            });

            grid.appendChild(card);
        });

        const selectAll = document.getElementById('pfSelectAll');
        if (selectAll) {
            selectAll.checked = sorted.length > 0 && sorted.every((property) => !!pfSelectedMap[property.id]);
        }
    }

    // Pulls a matched result straight into the existing Hotel/Property + Room Category fields below (same form, no separate flow).
    function useMatchedPropertyForQuery(id) {
        const property = pfResultsData.find((item) => item.id === id);
        if (!property) {
            showErrorToast('Property not found');
            return;
        }

        const label = property.location ? `${property.name}, ${property.location}` : property.name;
        selectQueryHotelSuggestion({ id: property.id, label });

        const hotelField = document.getElementById('queryHotelName');
        if (hotelField) {
            hotelField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        showToastMsg(`${property.name} selected. Continue filling dates & guest details below.`);
    }

    function togglePropertySelection(id, checked) {
        if (checked) {
            const property = pfResultsData.find((item) => item.id === id);
            if (property) pfSelectedMap[id] = property;
        } else {
            delete pfSelectedMap[id];
        }
        updatePfSelectionUi();
    }

    function toggleSelectAllProperties(checked) {
        getSortedPfResults().forEach((property) => {
            if (checked) {
                pfSelectedMap[property.id] = property;
            } else {
                delete pfSelectedMap[property.id];
            }
        });
        renderPfResults();
        updatePfSelectionUi();
    }

    function updatePfSelectionUi() {
        const count = Object.keys(pfSelectedMap).length;
        document.getElementById('pfSelectedCount').textContent = String(count);
        const pluralEl = document.getElementById('pfSelectedPlural');
        if (pluralEl) pluralEl.textContent = count === 1 ? 'y' : 'ies';
        document.getElementById('pfWhatsappBtn').disabled = count === 0;
        document.querySelectorAll('.pf-row-checkbox').forEach((cb) => {
            const card = cb.closest('.pf-property-card');
            const id = Number(cb.dataset.id);
            const selected = !!pfSelectedMap[id];
            cb.checked = selected;
            if (card) card.classList.toggle('selected', selected);
        });
    }

    function mealPlanLabel(code) {
        const map = {
            EP: 'EP (Room Only)',
            CP: 'CP (Breakfast Included)',
            MAP: 'MAP (Breakfast + Dinner)',
            AP: 'AP (All Meals)',
        };
        return map[code] || code;
    }

    function buildPropertyShareBlock(property, index) {
        const lines = [];
        lines.push(`*${index}. ${property.name}*`);
        lines.push(`Location: ${property.location || 'N/A'}`);
        lines.push(`Category: ${property.category || 'N/A'}`);

        const room = getPrimaryRoom(property);
        if (room) {
            lines.push(`Room: ${room.name}`);
            lines.push(`Bed: ${room.bed_type || 'N/A'}${room.room_size ? ' (' + room.room_size + ')' : ''}`);
            lines.push(`Available Rooms: ${room.available_rooms ?? property.available_rooms ?? 0}`);
            const priceEntries = Object.entries(room.prices || {}).filter(([, price]) => Number(price) > 0);
            priceEntries.forEach(([code, price]) => {
                lines.push(`${code}: ₹${formatInr(price)}`);
            });
            if (room.extra_bed_allowed) {
                lines.push(`Extra Bed: ₹${formatInr(room.extra_bed_price || 0)}`);
            }
            if ((property.rooms || []).length > 1) {
                lines.push(`(+${property.rooms.length - 1} more room option${property.rooms.length > 2 ? 's' : ''} available)`);
            }
        } else {
            lines.push(`Available Rooms: ${property.available_rooms ?? 0}`);
            lines.push(`Starting Price: ₹${formatInr(property.min_price || 0)}`);
        }

        if (property.description) {
            lines.push(`Details: ${property.description}`);
        }

        return lines.join('\n');
    }

    function shareSelectedPropertiesOnWhatsapp() {
        const selected = Object.values(pfSelectedMap);
        if (selected.length === 0) {
            showErrorToast('Please select at least one property to share');
            return;
        }

        const blocks = selected.map((property, idx) => buildPropertyShareBlock(property, idx + 1));
        const message = [
            '*Hotel Options for Your Stay*',
            '',
            blocks.join('\n\n'),
            '',
            'Please let us know which hotel you would like to book. 😊'
        ].join('\n');

        const agentPhoneRaw = (document.getElementById('queryAgentPhone')?.value || '').replace(/\D/g, '');
        const whatsappUrl = `https://wa.me/${agentPhoneRaw}?text=${encodeURIComponent(message)}`;
        window.open(whatsappUrl, '_blank');
    }

    function updateCheckOut() {
        const checkInDate = document.getElementById('queryCheckIn')?.value;
        if (!checkInDate) {
            return;
        }

        const checkOutInput = document.getElementById('queryCheckOut');
        if (!checkOutInput) {
            return;
        }

        // Check-in date se ek din aage ki date calculate karo.
        const date = new Date(checkInDate);
        date.setDate(date.getDate() + 1);
        const nextDay = date.toISOString().split('T')[0];

        // Check-out ka minimum date next day hona chahiye.
        checkOutInput.min = nextDay;

        // Existing check-out invalid ho to usko next day par set karo.
        if (!checkOutInput.value || checkOutInput.value < nextDay) {
            checkOutInput.value = nextDay;
        }
    }

    function calculateQueryNights() {
        const checkIn = new Date(document.getElementById('queryCheckIn').value);
        const checkOut = new Date(document.getElementById('queryCheckOut').value);
        if (checkIn && checkOut && checkOut > checkIn) {
            const nights = Math.floor((checkOut - checkIn) / (1000 * 60 * 60 * 24));
            document.getElementById('queryNights').value = nights > 0 ? nights : 0;
            calculateQueryTotalAmount();
        }
    }

    function calculateQueryTotalAmount() {
        const hotelId = document.getElementById('queryHotelId').value;
        const categoryName = document.getElementById('queryRoomCategory').value;
        const checkIn = document.getElementById('queryCheckIn').value;
        const checkOut = document.getElementById('queryCheckOut').value;
        const adults = parseInt(document.getElementById('queryAdults').value) || 1;
        const children = parseInt(document.getElementById('queryChildren').value) || 0;
        const rooms = parseInt(document.getElementById('queryRooms').value) || 1;
        const extraBed = document.getElementById('queryExtraBed').value;
        const mealPlan = document.getElementById('queryMealPlan').value;

        if (!hotelId || !categoryName || !checkIn || !checkOut) {
            document.getElementById('queryTotalAmount').value = '0';
            return;
        }

        // Calculate nights
        const checkInDate = new Date(checkIn);
        const checkOutDate = new Date(checkOut);
        if (checkOutDate <= checkInDate) {
            document.getElementById('queryTotalAmount').value = '0';
            return;
        }

        const nights = Math.floor((checkOutDate - checkInDate) / (1000 * 60 * 60 * 24));

        // Fetch pricing for the selected category
        const formData = new FormData();
        formData.append('action', 'get_room_category_pricing');
        formData.append('hotelId', hotelId);
        formData.append('categoryName', categoryName);

        fetch('employee-dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success || !data.pricing) {
                    document.getElementById('queryTotalAmount').value = '0';
                    return;
                }

                const pricing = data.pricing;
                let totalPrice = 0;

                // Calculate room price based on weekday/weekend
                const roomPrice = calculateRoomPricing(checkInDate, checkOutDate, pricing.weekday_price || 0,
                    pricing.weekend_price || 0);

                // Base cost: room price per room per night × nights × rooms
                totalPrice = roomPrice * nights * rooms;

                // Calculate meal plan price
                let mealPrice = 0;
                if (mealPlan === 'CP (Breakfast)') {
                    mealPrice = (pricing.cpai_price || 0) * adults * nights;
                } else if (mealPlan === 'MAP (Breakfast + Dinner)') {
                    mealPrice = (pricing.mapai_price || 0) * adults * nights;
                } else if (mealPlan === 'AP (All Meals)') {
                    mealPrice = (pricing.apai_price || 0) * adults * nights;
                }

                // Add children charges
                let childPrice = 0;
                if (children > 0) {
                    childPrice = (pricing.child_no_bed_cpai || 0) * children * nights;
                }

                // Add extra bed charges (rate fixed by admin per room category)
                let extraBedPrice = 0;
                if (extraBed === 'Yes') {
                    extraBedPrice = (pricing.extra_person_with_bed || 0) * rooms * nights;
                }

                // Total calculation
                totalPrice = totalPrice + mealPrice + childPrice + extraBedPrice;

                document.getElementById('queryTotalAmount').value = Math.round(totalPrice);
            })
            .catch(error => {
                console.error('Error fetching pricing:', error);
                document.getElementById('queryTotalAmount').value = '0';
            });
    }

    function calculateRoomPricing(checkInDate, checkOutDate, weekdayPrice, weekendPrice) {
        // Simple calculation: use average or weighted price
        // For simplicity, we'll use weekday price
        // In a real scenario, you'd want to calculate each day
        const allDaysPrice = weekdayPrice + weekendPrice;
        return allDaysPrice > 0 ? Math.round(allDaysPrice / 2) : 0;
    }

    function generateQueryFromForm() {
        const hotelName = document.getElementById('queryHotelName').value.trim();
        const checkIn = document.getElementById('queryCheckIn').value;
        const checkOut = document.getElementById('queryCheckOut').value;
        const adults = document.getElementById('queryAdults').value;
        const children = document.getElementById('queryChildren').value;
        const rooms = document.getElementById('queryRooms').value;
        const roomCategory = document.getElementById('queryRoomCategory').value;
        const mealPlan = document.getElementById('queryMealPlan').value;
        const totalAmount = document.getElementById('queryTotalAmount').value;
        const clientName = document.getElementById('queryClientName').value.trim();
        const clientMobile = document.getElementById('queryClientMobile').value.trim();
        const specialRequest = document.getElementById('querySpecialRequest').value.trim();
        const agentPhone = document.getElementById('queryAgentPhone').value;

        if (!hotelName || !checkIn || !checkOut || !roomCategory || !clientName || !clientMobile || !totalAmount) {
            showErrorToast('Please fill in all required fields');
            return;
        }

        // Validate dates - prevent back dates
        const today = new Date().toISOString().split('T')[0];
        if (checkIn < today) {
            showErrorToast('Check-in date cannot be in the past');
            return;
        }
        if (checkOut <= checkIn) {
            showErrorToast('Check-out date must be after check-in date');
            return;
        }

        const queryNumber = AirwaysQuotation.generateQueryNumber();
        let queryText = AirwaysQuotation.format({
            hotelName, roomCategory, mealPlan, checkIn, checkOut, adults, children, rooms,
            roomPrice: totalAmount, agentName: currentQueryAgent, agentPhone, queryNumber
        });

        // Lock agent and save query history
        fetch('employee-dashboard.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    action: 'lock_agent_and_save_query',
                    agentPhone: agentPhone,
                    queryText: queryText,
                    hotelName: hotelName,
                    roomCategory: roomCategory,
                    checkIn: checkIn,
                    checkOut: checkOut,
                    adults: adults,
                    children: children,
                    rooms: rooms,
                    mealPlan: mealPlan,
                    totalAmount: totalAmount,
                    clientName: clientName,
                    clientMobile: clientMobile,
                    specialRequest: specialRequest
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    queryText = AirwaysQuotation.format({
                        hotelName, roomCategory, mealPlan, checkIn, checkOut, adults, children, rooms,
                        roomPrice: totalAmount, agentName: currentQueryAgent, agentPhone, queryNumber
                    });
                    navigator.clipboard?.writeText(queryText).catch(() => {});
                    showToastMsg('Query generated and agent locked for 5 hours');
                    document.getElementById('generatedQueryText').value = queryText;
                    document.getElementById('generatedQueryDisplay').style.display = 'block';

                    const whatsappUrl =
                        `https://wa.me/${agentPhone.replace(/\D/g, '')}?text=${encodeURIComponent(queryText)}`;
                    document.getElementById('generatedQueryWhatsappLink').href = whatsappUrl;

                    // Refresh query history
                    loadQueryHistory();
                } else {
                    showErrorToast(data.message || 'Error saving query');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorToast('Error processing query');
            });
    }

    function copyGeneratedQuery() {
        const queryText = document.getElementById('generatedQueryText');
        queryText.select();
        document.execCommand('copy');
        showToastMsg('Query copied to clipboard');
    }

    function copyQuery() {
        copyGeneratedQuery();
    }

    function createBookingFromQuery() {
        const hotelName = document.getElementById('queryHotelName').value.trim();
        const hotelId = document.getElementById('queryHotelId').value;
        const roomCategory = document.getElementById('queryRoomCategory').value;
        const checkIn = document.getElementById('queryCheckIn').value;
        const checkOut = document.getElementById('queryCheckOut').value;
        const adults = document.getElementById('queryAdults').value;
        const children = document.getElementById('queryChildren').value;
        const rooms = document.getElementById('queryRooms').value;
        const totalAmount = document.getElementById('queryTotalAmount').value;
        const advancePaid = document.getElementById('queryAdvancePaid').value || '0';
        const clientName = document.getElementById('queryClientName').value.trim();
        const clientMobile = document.getElementById('queryClientMobile').value.trim();
        const clientEmail = document.getElementById('queryClientEmail').value.trim();
        const specialRequest = document.getElementById('querySpecialRequest').value.trim();
        const agentPhone = document.getElementById('queryAgentPhone').value;

        if (!hotelName || !checkIn || !checkOut || !roomCategory || !clientName || !clientMobile || !totalAmount) {
            showErrorToast('Please fill in all required fields');
            return;
        }

        // Validate dates - prevent back dates
        const today = new Date().toISOString().split('T')[0];
        if (checkIn < today) {
            showErrorToast('Check-in date cannot be in the past');
            return;
        }
        if (checkOut <= checkIn) {
            showErrorToast('Check-out date must be after check-in date');
            return;
        }

        // Search for agent to get agent ID
        fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    action: 'search_agent_by_mobile',
                    mobileNumber: agentPhone
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.found) {
                    const agentId = data.agent.id;

                    // Create booking
                    fetch('', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: new URLSearchParams({
                                action: 'create_booking',
                                clientName: clientName,
                                clientPhone: clientMobile,
                                clientEmail: clientEmail,
                                hotelId: hotelId,
                                agentId: agentId,
                                checkIn: checkIn,
                                checkOut: checkOut,
                                bookingDate: new Date().toISOString().split('T')[0],
                                amount: totalAmount,
                                paidAmount: advancePaid,
                                guestCount: adults,
                                roomCount: rooms,
                                specialRequest: specialRequest,
                                bookingSource: 'Query',
                                roomType: roomCategory,
                                hotelNameSnapshot: hotelName
                            })
                        })
                        .then(response => response.json())
                        .then(bookingData => {
                            if (bookingData.success) {
                                showToastMsg('Booking created successfully: ' + bookingData.message);
                                setTimeout(() => {
                                    // Reset form
                                    document.getElementById('queryHotelName').value = '';
                                    document.getElementById('queryHotelId').value = '';
                                    document.getElementById('queryRoomCategory').innerHTML =
                                        '<option value="" selected disabled>Select room category...</option>';
                                    document.getElementById('queryCheckIn').value = '';
                                    document.getElementById('queryCheckOut').value = '';
                                    document.getElementById('queryAdults').value = '1';
                                    document.getElementById('queryChildren').value = '0';
                                    document.getElementById('queryRooms').value = '1';
                                    document.getElementById('queryMealPlan').value = 'EP (Room Only)';
                                    document.getElementById('queryTotalAmount').value = '0';
                                    document.getElementById('queryClientName').value = '';
                                    document.getElementById('queryClientMobile').value = '';
                                    document.getElementById('queryClientEmail').value = '';
                                    document.getElementById('querySpecialRequest').value = '';
                                    document.getElementById('queryAdvancePaid').value = '';

                                    // Hide generated query display
                                    document.getElementById('generatedQueryDisplay').style.display =
                                        'none';

                                    // Refresh dashboard data
                                    loadDashboardData();
                                }, 1500);
                            } else {
                                showErrorToast(bookingData.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showErrorToast('Error creating booking');
                        });
                } else {
                    showErrorToast('Agent not found');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorToast('Error finding agent');
            });
    }

    function goToQueryHistory() {
        showSection('query-history-view');
    }

    function loadQueryHistory() {
        const legacyHistoryRequest = fetch('employee-dashboard.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    action: 'get_query_history'
                })
            });
        const generatedHistoryRequest = fetch('employee-dashboard.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'get_booking_query_history' })
        });

        Promise.all([legacyHistoryRequest, generatedHistoryRequest])
            .then(async ([legacyResponse, generatedResponse]) => {
                const legacyData = await legacyResponse.json();
                const generatedData = await generatedResponse.json();
                renderQueryHistory(legacyData.success ? legacyData.history : [], generatedData.success ? generatedData.history : []);
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('queryHistory').innerHTML =
                    '<div class="text-center py-4 text-danger">Error loading history</div>';
            });
    }

    function escapeQueryHistoryHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
    }

    function formatQueryHistoryDate(value) {
        if (!value) return 'N/A';
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? 'N/A' : date.toLocaleString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true });
    }

    function renderQueryHistory(history, generatedHistory = []) {
        queryHistoryData = Array.isArray(history) ? history : [];
        const generated = Array.isArray(generatedHistory) ? generatedHistory : [];
        if ((!history || history.length === 0) && generated.length === 0) {
            document.getElementById('queryHistory').innerHTML =
                '<div class="text-center py-4 text-muted">No query history found</div>';
            return;
        }

        let html = '<div class="table-responsive"><table class="table table-custom table-hover align-middle"><thead class="bg-light"><tr><th>Type</th><th>Agent Name</th><th>Agent Number</th><th>Location</th><th>Category</th><th>Hotel / Room</th><th>Meal</th><th>Dates</th><th>Pax</th><th>Budget</th><th>Lock Status</th><th>Lock Until</th><th>Generated At</th><th>Actions</th></tr></thead><tbody>';
        generated.forEach(item => {
            const generatedAt = formatQueryHistoryDate(item.generated_at);
            const isLocked = item.lock_until && new Date(item.lock_until).getTime() > Date.now();
            const lockStatus = isLocked ? '<span class="badge bg-danger">Agent Locked</span>' : '<span class="badge bg-success">Unlocked</span>';
            const lockUntil = isLocked ? formatQueryHistoryDate(item.lock_until) : 'Unlocked';
            const dates = `${item.check_in || 'N/A'} - ${item.check_out || 'N/A'}`;
            const hotels = Array.isArray(item.matched_hotels) ? item.matched_hotels : [];
            const hotelSummary = hotels.map(h => `${h.name || 'Hotel'} / ${h.room_name || 'Room'}`).join('<br>') || 'No matches';
            const mealSummary = hotels.map(h => formatBookingMealPlans(h.prices)).join('<br>') || 'N/A';
            const text = item.query_text || '';
            html += `<tr class="query-history-row" data-history-date="${item.generated_at}" data-history-text="${(item.query_text || '').toLowerCase()}">
                <td>Booking Query</td><td>${escapeQueryHistoryHtml(item.agent_name || 'N/A')}</td><td>${escapeQueryHistoryHtml(item.agent_phone || 'N/A')}</td><td>${escapeQueryHistoryHtml(item.location || 'Any')}</td><td>${escapeQueryHistoryHtml(item.hotel_category || 'All Categories')}</td>
                <td>${hotelSummary}</td><td>${mealSummary}</td><td>${dates}</td>
                <td>A:${item.adults || 1} C:${item.children || 0} R:${item.rooms || 1}</td>
                <td>₹${Number(item.budget || 0).toLocaleString('en-IN')}/night</td><td>${lockStatus}</td><td>${lockUntil}</td><td>${generatedAt}</td>
                <td><button class="btn btn-sm btn-outline-primary me-1" data-query-text="${escapeQueryHistoryHtml(text)}" data-quotation="${escapeQueryHistoryHtml(JSON.stringify({ queryNumber: item.query_number, queryText: text, hotelName: item.hotel_name, hotelLocation: item.location, roomCategory: item.room_category, mealPlan: item.meal_plan, checkIn: item.check_in, checkOut: item.check_out, adults: item.adults, children: item.children, rooms: item.rooms, roomPrice: item.total_amount, agentName: item.agent_name, agentPhone: item.agent_phone, matchedHotels: hotels }))}" onclick="viewGeneratedQuery(this)">View</button><button class="btn btn-sm btn-outline-secondary" data-query-text="${escapeQueryHistoryHtml(text)}" data-quotation="${escapeQueryHistoryHtml(JSON.stringify({ queryNumber: item.query_number, queryText: text, hotelName: item.hotel_name, hotelLocation: item.location, roomCategory: item.room_category, mealPlan: item.meal_plan, checkIn: item.check_in, checkOut: item.check_out, adults: item.adults, children: item.children, rooms: item.rooms, roomPrice: item.total_amount, agentName: item.agent_name, agentPhone: item.agent_phone, matchedHotels: hotels }))}" onclick="copyQueryText(this.dataset.queryText, this)">Copy</button></td>
            </tr>`;
        });
        history.forEach(item => {
            const generatedAt = formatQueryHistoryDate(item.generated_at);
            const isLocked = item.lock_until && new Date(item.lock_until).getTime() > Date.now();
            const lockStatus = isLocked ? '<span class="badge bg-danger">Agent Locked</span>' : '<span class="badge bg-success">Unlocked</span>';
            const lockUntil = isLocked ? formatQueryHistoryDate(item.lock_until) : 'Unlocked';
            const escapedQuery = (item.query_text || '').replace(/'/g, "\\'");
            const dates = (item.check_in ? item.check_in : '') + (item.check_out ? (' - ' + item.check_out) : '');
            const pax = `A:${item.adults||1} C:${item.children||0} R:${item.rooms||1}`;
            html += `<tr class="query-history-row" data-history-date="${item.generated_at}" data-history-text="${(item.query_text || '').toLowerCase()}">
                <td>Agent Query</td><td>${escapeQueryHistoryHtml(item.agent_name || 'N/A')}</td><td>${escapeQueryHistoryHtml(item.agent_phone || 'N/A')}</td><td>${escapeQueryHistoryHtml(item.location || 'Any')}</td><td>${escapeQueryHistoryHtml(item.hotel_name || '')}</td>
                <td>${item.room_category || ''}</td><td>${item.meal_plan || ''}</td><td>${dates}</td><td>${pax}</td>
                <td>₹${Number(item.total_amount||0).toLocaleString('en-IN')}</td><td>${lockStatus}</td><td>${lockUntil}</td><td>${generatedAt}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="viewQuery(${item.id})">View</button>
                    <button class="btn btn-sm btn-outline-secondary me-1" onclick="copyQueryDetails(${item.id})">Copy</button>
                </td>
            </tr>`;
        });
        html += '</tbody></table></div>';
        document.getElementById('queryHistory').innerHTML = html;
        applyQueryHistoryFilter('all');
    }

    function applyQueryHistoryFilter(filter) {
        const now = new Date();
        const rows = document.querySelectorAll('.query-history-row');
        const search = (document.getElementById('queryHistorySearch')?.value || '').toLowerCase().trim();
        rows.forEach(row => {
            const date = new Date(row.dataset.historyDate);
            const dayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            const weekStart = new Date(dayStart);
            weekStart.setDate(dayStart.getDate() - dayStart.getDay());
            const monthStart = new Date(now.getFullYear(), now.getMonth(), 1);
            const dateMatch = filter === 'today' ? date >= dayStart : filter === 'week' ? date >= weekStart : filter === 'month' ? date >= monthStart : true;
            row.style.display = dateMatch && (!search || row.dataset.historyText.includes(search) || row.textContent.toLowerCase().includes(search)) ? '' : 'none';
        });
        document.querySelectorAll('.query-history-filter').forEach(button => {
            const active = button.dataset.historyFilter === filter;
            button.classList.toggle('active', active);
            button.classList.toggle('btn-primary', active);
            button.classList.toggle('btn-outline-primary', !active);
        });
    }

    document.addEventListener('click', event => {
        const button = event.target.closest('.query-history-filter');
        if (button) applyQueryHistoryFilter(button.dataset.historyFilter || 'all');
    });
    document.getElementById('queryHistorySearch')?.addEventListener('input', () => applyQueryHistoryFilter('all'));

    async function copyQueryDetails(queryId) {
        if (!queryId) return showErrorToast('Invalid query selected');

        try {
            const response = await fetch('employee-dashboard.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    action: 'get_query_by_id',
                    queryId: queryId
                })
            });
            const data = await response.json();
            if (!data.success) {
                return showErrorToast(data.message || 'Unable to load query details');
            }

            const text = AirwaysQuotation.format({ ...data, id: queryId, hotelName: data.hotel_name, roomCategory: data.room_category, matchedHotels: data.hotels });
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(text);
            } else {
                const ta = document.createElement('textarea');
                ta.value = text;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
            }
            showToastMsg('Full query details copied to clipboard');
        } catch (error) {
            console.error('Copy query details failed:', error);
            showErrorToast('Unable to copy query details');
        }
    }

    async function copyQueryText(text, button) {
        const row = button?.closest('.query-history-row');
        if (row?.dataset.quotation) text = AirwaysQuotation.format(JSON.parse(row.dataset.quotation));
        else text = AirwaysQuotation.plainText(text || '');
        if (!text) return showErrorToast('No query text available to copy');
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(text);
            } else {
                throw new Error('Clipboard API unavailable');
            }
            showToastMsg('Query copied to clipboard');
        } catch (e) {
            const ta = document.createElement('textarea');
            ta.value = text; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
            showToastMsg('Query copied to clipboard');
        }
    }

    function viewQuery(queryId) {
        fetch('employee-dashboard.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    action: 'get_query_by_id',
                    queryId: queryId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    showErrorToast(data.message || 'Unable to load query');
                    return;
                }

                const modal = document.getElementById('queryHistoryModal');
                if (!modal) {
                    showToastMsg('Query modal not available');
                    return;
                }

                const title = data.agent_name ? `Query for ${data.agent_name}` : 'Query Details';
                document.getElementById('queryHistoryModalTitle').textContent = title;
                const formatRupees = amount => new Intl.NumberFormat('en-IN', { maximumFractionDigits: 0 }).format(amount || 0);
                const cleanValue = value => value !== undefined && value !== null && value !== '' ? value : 'N/A';
                const nights = data.nights !== '' ? data.nights : 'N/A';
                const extraBed = data.extra_bed ? data.extra_bed : 'No';
                const paidAmount = data.paid_amount ? formatRupees(data.paid_amount) : '0';

                let html = `
                    <div class="card mb-3 border-0 shadow-sm">
                        <div class="card-body" style="padding:1rem 1.25rem;">
                            <div class="text-secondary small mb-2">🧾 Booking Details</div>
                            <div class="row gy-2">
                                <div class="col-12 col-md-6"><span class="text-secondary">Agent Name:</span> ${cleanValue(data.agent_name)}</div>
                                <div class="col-12 col-md-6"><span class="text-secondary">Phone:</span> ${cleanValue(data.agent_phone)}</div>
                                <div class="col-12 col-md-6"><span class="text-secondary">Hotel / Property:</span> ${cleanValue(data.hotel_name)}</div>
                                <div class="col-12 col-md-6"><span class="text-secondary">Room Category:</span> ${cleanValue(data.room_category)}</div>
                                <div class="col-12 col-md-6"><span class="text-secondary">Check-in Date:</span> ${cleanValue(data.check_in)}</div>
                                <div class="col-12 col-md-6"><span class="text-secondary">Check-out Date:</span> ${cleanValue(data.check_out)}</div>
                                <div class="col-12 col-md-6"><span class="text-secondary">Nights:</span> ${cleanValue(nights)}</div>
                                <div class="col-12 col-md-6"><span class="text-secondary">Adults / Children / Rooms:</span> A:${data.adults||1} / C:${data.children||0} / R:${data.rooms||1}</div>
                                <div class="col-12 col-md-6"><span class="text-secondary">Extra Bed:</span> ${extraBed}</div>
                                <div class="col-12 col-md-6"><span class="text-secondary">Meal Plan:</span> ${cleanValue(data.meal_plan)}</div>
                                <div class="col-12 col-md-6"><span class="text-secondary">Total Amount:</span> ₹${formatRupees(data.total_amount)}</div>
                                <div class="col-12 col-md-6"><span class="text-secondary">Advance Paid:</span> ₹${paidAmount}</div>
                                <div class="col-12 col-md-6"><span class="text-secondary">Client Name:</span> ${cleanValue(data.client_name)}</div>
                                <div class="col-12 col-md-6"><span class="text-secondary">Client Mobile:</span> ${cleanValue(data.client_mobile)}</div>
                                <div class="col-12 col-md-6"><span class="text-secondary">Client Email:</span> ${cleanValue(data.client_email)}</div>
                                <div class="col-12"><span class="text-secondary">Special Request / Notes:</span> ${cleanValue(data.special_request)}</div>
                            </div>
                        </div>
                    </div>`;

                html += '<div class="text-secondary small mb-2">🏨 Matched Hotel Details</div>';

                if (Array.isArray(data.hotels) && data.hotels.length > 0) {
                    data.hotels.forEach(h => {
                        html += `
                            <div class="card mb-3 border-0 shadow-sm">
                                <div class="card-body" style="padding:1rem 1.25rem;">
                                    <div class="fw-semibold mb-2">🏨 ${cleanValue(h.hotel_name)}</div>
                                    <div class="row gy-2">
                                        <div class="col-12 col-md-6"><span class="text-secondary">📍 Location:</span> ${cleanValue(h.location)}</div>
                                        <div class="col-12 col-md-6"><span class="text-secondary">🏷 Category:</span> ${cleanValue(h.category)}</div>
                                        <div class="col-12"><span class="text-secondary">🛏 Room Type:</span> ${cleanValue(h.room_type)}</div>
                                        <div class="col-12 col-md-4"><span class="text-secondary">💰 Weekday Price:</span> ₹${formatRupees(h.weekday_price)}</div>
                                        <div class="col-12 col-md-4"><span class="text-secondary">💰 Weekend Price:</span> ₹${formatRupees(h.weekend_price)}</div>
                                        <div class="col-12 col-md-4"><span class="text-secondary">💰 GST:</span> ${cleanValue(h.gst)}%</div>
                                    </div>
                                </div>
                            </div>`;
                    });
                } else {
                    html += '<div class="text-muted small">No matched hotel details available for this query.</div>';
                }

                document.getElementById('queryHistoryModalBody').innerHTML = html;
                const modalInstance = new bootstrap.Modal(modal);
                modalInstance.show();
            })
            .catch(error => {
                console.error('Error fetching query details:', error);
                showErrorToast('Unable to load query details');
            });
    }

    function viewGeneratedQuery(button) {
        const modal = document.getElementById('queryHistoryModal');
        const text = button?.dataset.queryText || '';
        if (!modal) return showErrorToast('Query modal not available');
        document.getElementById('queryHistoryModalTitle').textContent = 'Query Details';
        const quotation = button?.dataset.quotation ? JSON.parse(button.dataset.quotation) : null;
        document.getElementById('queryHistoryModalBody').textContent = quotation
            ? AirwaysQuotation.format(quotation)
            : AirwaysQuotation.plainText(text);
        bootstrap.Modal.getOrCreateInstance(modal).show();
    }

    // Load query history when section is shown
    // Override showSection to load history
    const originalShowSection = showSection;
    showSection = function(sectionId) {
        originalShowSection(sectionId);
        if (sectionId === 'booking-query-view') {
            updateCheckOut();
        }
        if (sectionId === 'query-history-view') {
            loadQueryHistory();
        }
    };
    </script>
    <script src="/assets/js/quotation-template.js?v=20260826-1"></script>
<script src="/assets/js/ui-common.js"></script>
</body>

</html>