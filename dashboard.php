<?php
require_once __DIR__ . '/includes/auth_session.php';
require_once __DIR__ . '/includes/db_connect.php';
require_role('admin');

$selectedDate = sanitize_input($_GET['date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = date('Y-m-d');
}

if (isset($_GET['action']) && $_GET['action'] === 'live_metrics') {
    header('Content-Type: application/json; charset=utf-8');

    $liveTotalBookings = (int) $conn->query('SELECT COUNT(*) FROM bookings_details')->fetchColumn();
    $liveTotalAgents = (int) $conn->query('SELECT COUNT(*) FROM agents_details')->fetchColumn();

    $liveStatusCounts = ['pending' => 0, 'completed' => 0, 'cancelled' => 0];

    $liveTodayBookingsStmt = $conn->prepare('SELECT COUNT(*) FROM bookings_details WHERE booking_date = :selected_date');
    $liveTodayBookingsStmt->execute([':selected_date' => $selectedDate]);
    $liveTodayBookings = (int) $liveTodayBookingsStmt->fetchColumn();

    $liveTodayAgentsStmt = $conn->prepare('SELECT COUNT(*) FROM agents_details WHERE DATE(created_at) = :selected_date');
    $liveTodayAgentsStmt->execute([':selected_date' => $selectedDate]);
    $liveTodayAgents = (int) $liveTodayAgentsStmt->fetchColumn();

    $liveStatusStmt = $conn->query('SELECT booking_status, COUNT(*) AS total FROM bookings_details GROUP BY booking_status');
    foreach ($liveStatusStmt->fetchAll() as $row) {
        $statusKey = strtolower((string) $row['booking_status']);
        if (isset($liveStatusCounts[$statusKey])) {
            $liveStatusCounts[$statusKey] = (int) $row['total'];
        }
    }

    $liveWeeklyLabels = [];
    $liveWeeklyCounts = [];
    $liveWeeklyStmt = $conn->query(
        'SELECT DATE_FORMAT(booking_date, "%d %b") AS day_label, COUNT(*) AS total
         FROM bookings_details
         WHERE booking_date >= DATE_SUB(CURDATE(), INTERVAL 27 DAY)
         GROUP BY booking_date
         ORDER BY booking_date ASC'
    );
    foreach ($liveWeeklyStmt->fetchAll() as $row) {
        $liveWeeklyLabels[] = $row['day_label'];
        $liveWeeklyCounts[] = (int) $row['total'];
    }
    if (count($liveWeeklyLabels) > 7) {
        $liveWeeklyLabels = array_slice($liveWeeklyLabels, -7);
        $liveWeeklyCounts = array_slice($liveWeeklyCounts, -7);
    }

    echo json_encode([
        'success' => true,
        'cards' => [
            'totalBookings' => $liveTotalBookings,
            'totalAgents' => $liveTotalAgents,
            'todayBookings' => $liveTodayBookings,
            'todayNewAgents' => $liveTodayAgents,
        ],
        'weekly' => [
            'labels' => $liveWeeklyLabels,
            'counts' => $liveWeeklyCounts,
        ],
        'statusCounts' => [
              $liveStatusCounts['pending'],
              $liveStatusCounts['completed'],
              $liveStatusCounts['cancelled'],
        ],
        'updatedAt' => date('H:i:s'),
    ]);
    exit;
}

// Handle Admin Booking Query AJAX Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'search_agent_by_mobile') {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        $mobileNumber = sanitize_input($_POST['mobileNumber'] ?? '');
        
        if (!$mobileNumber) {
            echo json_encode(['success' => false, 'found' => false, 'message' => 'Mobile number required']);
            exit;
        }
        
        try {
            $agentStmt = $conn->prepare("SELECT id, name, email, phone, location, gst_number FROM agents_details WHERE phone = :phone");
            $agentStmt->execute([':phone' => $mobileNumber]);
            $agent = $agentStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$agent) {
                echo json_encode(['success' => false, 'found' => false]);
                exit;
            }
            
            // Check if agent is locked (admin can override, so just show the lock info)
            $lockStmt = $conn->prepare("SELECT * FROM agent_query_locks WHERE agent_id = :agent_id AND lock_until > NOW() ORDER BY lock_until DESC LIMIT 1");
            $lockStmt->execute([':agent_id' => $agent['id']]);
            $lock = $lockStmt->fetch(PDO::FETCH_ASSOC);
            
            $response = [
                'success' => true,
                'found' => true,
                'agent' => $agent,
                'lock_info' => $lock
            ];
            
            echo json_encode($response);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'found' => false, 'message' => 'Database error']);
        }
        exit;
    }
    
    if ($action === 'create_booking') {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        
        $clientName = sanitize_input($_POST['clientName'] ?? '');
        $clientPhone = sanitize_input($_POST['clientPhone'] ?? '');
        $clientEmail = sanitize_input($_POST['clientEmail'] ?? '');
        $hotelId = intval($_POST['hotelId'] ?? 0);
        $agentId = intval($_POST['agentId'] ?? 0);
        $checkIn = sanitize_input($_POST['checkIn'] ?? '');
        $checkOut = sanitize_input($_POST['checkOut'] ?? '');
        $bookingDate = sanitize_input($_POST['bookingDate'] ?? date('Y-m-d'));
        $amount = floatval($_POST['amount'] ?? 0);
        $paidAmount = floatval($_POST['paidAmount'] ?? 0);
        $guestCount = intval($_POST['guestCount'] ?? 1);
        $roomCount = intval($_POST['roomCount'] ?? 1);
        $specialRequest = sanitize_input($_POST['specialRequest'] ?? '');
        $bookingSource = sanitize_input($_POST['bookingSource'] ?? 'Admin Query');
        $roomType = sanitize_input($_POST['roomType'] ?? '');
        $hotelSnapshot = sanitize_input($_POST['hotelNameSnapshot'] ?? '');
        
        if (!$clientName || !$clientPhone || !$hotelId || !$agentId || !$checkIn || !$checkOut || !$amount) {
            echo json_encode(['success' => false, 'message' => 'Required fields missing']);
            exit;
        }
        
        try {
            $bookingCode = 'BK-' . date('YmdHis') . '-' . rand(1000, 9999);
            $insertStmt = $conn->prepare(
                'INSERT INTO bookings_details (booking_code, client_name, client_phone, client_email, hotel_listing_id, agent_id, 
                 check_in, check_out, booking_date, amount, advance_payment, guest_count, room_count, special_request, 
                 booking_source, hotel_name_snapshot, room_type_snapshot, created_by, booking_status, created_at)
                 VALUES (:booking_code, :client_name, :client_phone, :client_email, :hotel_id, :agent_id,
                 :check_in, :check_out, :booking_date, :amount, :advance_payment, :guest_count, :room_count, :special_request,
                 :booking_source, :hotel_snapshot, :room_type, :created_by, "Pending", NOW())'
            );
            
            $insertStmt->execute([
                ':booking_code' => $bookingCode,
                ':client_name' => $clientName,
                ':client_phone' => $clientPhone,
                ':client_email' => $clientEmail,
                ':hotel_id' => $hotelId,
                ':agent_id' => $agentId,
                ':check_in' => $checkIn,
                ':check_out' => $checkOut,
                ':booking_date' => $bookingDate,
                ':amount' => $amount,
                ':advance_payment' => $paidAmount,
                ':guest_count' => $guestCount,
                ':room_count' => $roomCount,
                ':special_request' => $specialRequest,
                ':booking_source' => $bookingSource,
                ':hotel_snapshot' => $hotelSnapshot,
                ':room_type' => $roomType,
                ':created_by' => $_SESSION['username']
            ]);
            
            echo json_encode(['success' => true, 'message' => $bookingCode]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Booking creation failed. Please try again.']);
        }
        exit;
    }
}

$flashSuccess = $_SESSION['dashboard_success'] ?? '';
$flashError = $_SESSION['dashboard_error'] ?? '';
$credentialNote = $_SESSION['dashboard_credential_note'] ?? '';
unset($_SESSION['dashboard_success'], $_SESSION['dashboard_error'], $_SESSION['dashboard_credential_note']);

$searchedAgent = null;
$searchedAgentBookings = [];
$selectedEmployeeUsername = sanitize_input($_GET['emp_user'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize_input($_POST['action'] ?? '');
    $redirectDate = sanitize_input($_POST['selected_date'] ?? $selectedDate);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $redirectDate)) {
        $redirectDate = date('Y-m-d');
    }

    if ($action === 'add_hotel_listing') {
        $hotelName = sanitize_input($_POST['hotel_name'] ?? '');
        $category = sanitize_input($_POST['category'] ?? '');
        $location = sanitize_input($_POST['location'] ?? '');
        $roomType = sanitize_input($_POST['room_type'] ?? '');
        $weekdayPrice = (float) ($_POST['weekday_price'] ?? 0);
        $weekendPrice = (float) ($_POST['weekend_price'] ?? 0);
        $gst = (float) ($_POST['gst'] ?? 0);

        if ($hotelName !== '' && $category !== '' && $location !== '' && $roomType !== '') {
            try {
                $insertStmt = $conn->prepare(
                    'INSERT INTO hotel_listings_details (hotel_name, category, location, room_type, weekday_price, weekend_price, gst, status)
                     VALUES (:hotel_name, :category, :location, :room_type, :weekday_price, :weekend_price, :gst, "Active")'
                );
                $insertStmt->execute([
                    ':hotel_name' => $hotelName,
                    ':category' => $category,
                    ':location' => $location,
                    ':room_type' => $roomType,
                    ':weekday_price' => $weekdayPrice,
                    ':weekend_price' => $weekendPrice,
                    ':gst' => $gst,
                ]);
                $_SESSION['dashboard_success'] = 'Hotel listing published successfully.';
            } catch (PDOException $e) {
                $_SESSION['dashboard_error'] = 'Unable to publish listing. Please try again.';
            }
        } else {
            $_SESSION['dashboard_error'] = 'Please fill in all required fields for the hotel listing.';
        }

        redirect('/dashboard.php?date=' . urlencode($redirectDate));
    }

    if ($action === 'register_agent') {
        $name = sanitize_input($_POST['agent_name'] ?? '');
        $companyName = sanitize_input($_POST['agent_company'] ?? '');
        $gstNumber = sanitize_input($_POST['agent_gst_number'] ?? '');
        $email = sanitize_input($_POST['agent_email'] ?? '');
        $phone = sanitize_input($_POST['agent_phone'] ?? '');
        $location = sanitize_input($_POST['agent_location'] ?? '');

        if ($name !== '' && $companyName !== '' && $email !== '' && $phone !== '' && $location !== '') {
            $existingAgentStmt = $conn->prepare('SELECT name, company_name, email FROM agents_details WHERE phone = :phone LIMIT 1');
            $existingAgentStmt->execute([':phone' => $phone]);
            $existingAgent = $existingAgentStmt->fetch();

            if ($existingAgent) {
                $_SESSION['dashboard_error'] = 'This mobile number is already registered. Please use a different mobile number.';
            } else {
                try {
                    $agentStmt = $conn->prepare(
                        'INSERT INTO agents_details (name, company_name, gst_number, email, phone, location, status, created_by)
                         VALUES (:name, :company_name, :gst_number, :email, :phone, :location, "Active", :created_by)'
                    );
                    $agentStmt->execute([
                        ':name' => $name,
                        ':company_name' => $companyName,
                        ':gst_number' => $gstNumber !== '' ? $gstNumber : null,
                        ':email' => $email,
                        ':phone' => $phone,
                        ':location' => $location,
                        ':created_by' => $_SESSION['username'],
                    ]);
                    $_SESSION['dashboard_success'] = 'Agent registered successfully.';
                } catch (PDOException $e) {
                    $duplicateMobileStmt = $conn->prepare('SELECT id FROM agents_details WHERE phone = :phone LIMIT 1');
                    $duplicateMobileStmt->execute([':phone' => $phone]);
                    $_SESSION['dashboard_error'] = $duplicateMobileStmt->fetch()
                        ? 'This mobile number is already registered. Please use a different mobile number.'
                        : 'Agent registration failed. Please verify the details and try again.';
                }
            }
        } else {
            $_SESSION['dashboard_error'] = 'Please fill in all required fields for agent registration.';
        }

        redirect('/dashboard.php?date=' . urlencode($redirectDate));
    }

    if ($action === 'register_employee') {
        $name = sanitize_input($_POST['emp_name'] ?? '');
        $email = sanitize_input($_POST['emp_email'] ?? '');
        $phone = sanitize_input($_POST['emp_phone'] ?? '');
        $designation = sanitize_input($_POST['emp_designation'] ?? '');
        $department = sanitize_input($_POST['emp_department'] ?? '');
        $salary = (float) ($_POST['emp_salary'] ?? 0);
        $username = sanitize_input($_POST['emp_username'] ?? '');
        $password = $_POST['emp_password'] ?? '';

        if ($name !== '' && $email !== '' && $phone !== '' && $designation !== '' && $department !== '' && $username !== '' && $password !== '') {
            try {
                $existingEmployeeStmt = $conn->prepare('SELECT id FROM employees_details WHERE phone = :phone LIMIT 1');
                $existingEmployeeStmt->execute([':phone' => $phone]);
                if ($existingEmployeeStmt->fetch()) {
                    $_SESSION['dashboard_error'] = 'This mobile number is already registered. Please use a different mobile number.';
                    redirect('/dashboard.php?date=' . urlencode($redirectDate));
                }

                $conn->beginTransaction();

                $employeeStmt = $conn->prepare(
                    'INSERT INTO employees_details (name, email, phone, designation, department, status, monthly_salary)
                     VALUES (:name, :email, :phone, :designation, :department, "Active", :salary)'
                );
                $employeeStmt->execute([
                    ':name' => $name,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':designation' => $designation,
                    ':department' => $department,
                    ':salary' => $salary,
                ]);

                $userStmt = $conn->prepare(
                    'INSERT INTO users (username, email, password, role)
                     VALUES (:username, :email, :password, "employee")'
                );
                $userStmt->execute([
                    ':username' => $username,
                    ':email' => $email,
                    ':password' => hash_password($password),
                ]);

                $conn->commit();
                $_SESSION['dashboard_success'] = 'Employee registered successfully.';
                $_SESSION['dashboard_credential_note'] = 'New employee account created. Username: ' . $username . '. Password has been securely set.';
            } catch (PDOException $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                $duplicateMobileStmt = $conn->prepare('SELECT id FROM employees_details WHERE phone = :phone LIMIT 1');
                $duplicateMobileStmt->execute([':phone' => $phone]);
                $_SESSION['dashboard_error'] = $duplicateMobileStmt->fetch()
                    ? 'This mobile number is already registered. Please use a different mobile number.'
                    : 'Employee registration failed. Please verify the details and try again.';
            }
        } else {
            $_SESSION['dashboard_error'] = 'Please complete all required fields for employee registration.';
        }

        redirect('/dashboard.php?date=' . urlencode($redirectDate));
    }

    if ($action === 'remove_employee_login') {
        $removeUsername = sanitize_input($_POST['remove_username'] ?? '');

        if ($removeUsername === '' || $removeUsername === 'admin') {
            $_SESSION['dashboard_error'] = 'Please select a valid employee login.';
            redirect('/dashboard.php?date=' . urlencode($redirectDate));
        }

        try {
            $conn->beginTransaction();

            $userStmt = $conn->prepare('SELECT id, email, role FROM users WHERE username = :username LIMIT 1');
            $userStmt->execute([':username' => $removeUsername]);
            $user = $userStmt->fetch();

            if (!$user || $user['role'] !== 'employee') {
                throw new RuntimeException('Employee login not found.');
            }

            $deleteStmt = $conn->prepare('DELETE FROM users WHERE id = :id AND role = "employee"');
            $deleteStmt->execute([':id' => $user['id']]);

            $deactivateStmt = $conn->prepare('UPDATE employees_details SET status = "Inactive" WHERE email = :email');
            $deactivateStmt->execute([':email' => $user['email']]);

            $conn->commit();
            $_SESSION['dashboard_success'] = 'Employee login removed and employee marked inactive.';
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $_SESSION['dashboard_error'] = 'Employee login could not be removed. Please try again.';
        }

        redirect('/dashboard.php?date=' . urlencode($redirectDate));
    }

    if ($action === 'search_agent_mobile') {
        $mobile = sanitize_input($_POST['search_mobile'] ?? '');
        if ($mobile !== '') {
            $agentSearchStmt = $conn->prepare(
                'SELECT * FROM agents_details WHERE phone = :phone ORDER BY id DESC LIMIT 1'
            );
            $agentSearchStmt->execute([':phone' => $mobile]);
            $searchedAgent = $agentSearchStmt->fetch();

            if ($searchedAgent) {
                $bookingsSearchStmt = $conn->prepare(
                    'SELECT b.booking_code, b.client_name, b.check_in, b.check_out, b.amount, b.status, h.hotel_name
                     FROM bookings_details b
                     LEFT JOIN hotels h ON h.id = b.hotel_listing_id
                     WHERE b.agent_id = :agent_id
                     ORDER BY b.created_at DESC
                     LIMIT 10'
                );
                $bookingsSearchStmt->execute([':agent_id' => $searchedAgent['id']]);
                $searchedAgentBookings = $bookingsSearchStmt->fetchAll();
            } else {
                $flashError = 'Agent not found with this mobile number. Please register this agent.';
            }
        } else {
            $flashError = 'Please enter mobile number for search.';
        }
    }
}

$totalBookings = (int) $conn->query('SELECT COUNT(*) FROM bookings_details')->fetchColumn();
$totalAgents = (int) $conn->query('SELECT COUNT(*) FROM agents_details')->fetchColumn();
$totalEmployees = (int) $conn->query('SELECT COUNT(*) FROM employees_details')->fetchColumn();
$totalListings = (int) $conn->query('SELECT COUNT(*) FROM hotels WHERE status = "active"')->fetchColumn();
$totalAccountsEntries = (int) $conn->query('SELECT COUNT(*) FROM accounts_details')->fetchColumn();
$totalRevenue = (float) $conn->query('SELECT COALESCE(SUM(amount), 0) FROM bookings_details WHERE booking_status <> "Cancelled"')->fetchColumn();
$paymentSummaryStmt = $conn->query(
    'SELECT
        COALESCE(SUM(paid_amount), 0) AS total_paid,
        COALESCE(SUM(due_amount), 0) AS total_due,
        SUM(CASE WHEN payment_status = "Pending" THEN 1 ELSE 0 END) AS pending_payment_count,
        SUM(CASE WHEN payment_status = "Partial" THEN 1 ELSE 0 END) AS partial_payment_count,
        SUM(CASE WHEN payment_status = "Paid" THEN 1 ELSE 0 END) AS paid_booking_count
     FROM bookings_details'
);
$paymentSummary = $paymentSummaryStmt->fetch();
$todayBookingsStmt = $conn->query('SELECT COUNT(*) FROM bookings_details WHERE booking_date = CURDATE()');
$todayBookings = (int) $todayBookingsStmt->fetchColumn();
$todayNewAgentsStmt = $conn->query("SELECT COUNT(*) FROM agents_details WHERE DATE(created_at) = CURDATE()");
$todayNewAgents = (int) $todayNewAgentsStmt->fetchColumn();

$selectedBookingsStmt = $conn->prepare('SELECT COUNT(*) FROM bookings_details WHERE booking_date = :selected_date');
$selectedBookingsStmt->execute([':selected_date' => $selectedDate]);
$todayBookings = (int) $selectedBookingsStmt->fetchColumn();

$selectedAgentsStmt = $conn->prepare('SELECT COUNT(*) FROM agents_details WHERE DATE(created_at) = :selected_date');
$selectedAgentsStmt->execute([':selected_date' => $selectedDate]);
$todayNewAgents = (int) $selectedAgentsStmt->fetchColumn();

$topDepartmentsStmt = $conn->query(
    'SELECT department AS name, COUNT(*) AS total
     FROM employees_details
     GROUP BY department
    ORDER BY total DESC, department ASC'
);
$topDepartments = $topDepartmentsStmt->fetchAll();

$activeDealsStmt = $conn->query(
    'SELECT b.client_name, b.amount, a.name AS booked_by, b.status
     FROM bookings_details b
     JOIN agents_details a ON a.id = b.agent_id
    ORDER BY b.booking_date DESC, b.id DESC'
);
$activeDeals = $activeDealsStmt->fetchAll();

$topLocationsStmt = $conn->query(
    'SELECT h.city AS location, COUNT(*) AS total
     FROM bookings_details b
     LEFT JOIN hotels h ON h.id = b.hotel_listing_id
     GROUP BY h.city
     ORDER BY total DESC
     LIMIT 4'
);
$topLocations = $topLocationsStmt->fetchAll();

$popularPropertiesStmt = $conn->query(
    'SELECT h.name AS hotel_name, h.property_category AS category, COUNT(*) AS total
     FROM bookings_details b
     LEFT JOIN hotels h ON h.id = b.hotel_listing_id
     GROUP BY h.name, h.property_category
     ORDER BY total DESC
     LIMIT 4'
);
$popularProperties = $popularPropertiesStmt->fetchAll();

$hotelRows = $conn->query('SELECT id, hotel_code, name, city, state, status, star_rating FROM hotels WHERE status = "active" ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
$hotelRoomTypeMap = [];
if (!empty($hotelRows)) {
    $hotelIds = array_map(static function ($row) {
        return (int)($row['id'] ?? 0);
    }, $hotelRows);
    $hotelIds = array_values(array_filter($hotelIds, static function ($id) {
        return $id > 0;
    }));

    if (!empty($hotelIds)) {
        $placeholders = implode(',', array_fill(0, count($hotelIds), '?'));
        $roomStmt = $conn->prepare('SELECT hotel_id, name FROM hotel_room_categories WHERE status = "active" AND hotel_id IN (' . $placeholders . ') ORDER BY id ASC');
        $roomStmt->execute($hotelIds);
        foreach ($roomStmt->fetchAll(PDO::FETCH_ASSOC) as $roomRow) {
            $hid = (int)($roomRow['hotel_id'] ?? 0);
            $roomName = trim((string)($roomRow['name'] ?? ''));
            if ($hid <= 0 || $roomName === '') {
                continue;
            }
            if (!isset($hotelRoomTypeMap[$hid])) {
                $hotelRoomTypeMap[$hid] = [];
            }
            $hotelRoomTypeMap[$hid][] = $roomName;
        }
    }
}

$hotel_catalog = [];
foreach ($hotelRows as $hotelRow) {
    $hotelName = trim((string)($hotelRow['name'] ?? ''));
    if ($hotelName === '') {
        continue;
    }
    $city = trim((string)($hotelRow['city'] ?? ''));
    $state = trim((string)($hotelRow['state'] ?? ''));
    $location = trim($city . ($state !== '' ? ', ' . $state : ''));
    $label = $location !== '' ? ($hotelName . ', ' . $location) : $hotelName;
    $hotel_catalog[$label] = [
        'id' => (int)($hotelRow['id'] ?? 0),
        'name' => $hotelName,
        'location' => $location,
        'category' => ((int)($hotelRow['star_rating'] ?? 0)) > 0 ? (((int)$hotelRow['star_rating']) . ' Star') : '',
        'hotel_code' => (string)($hotelRow['hotel_code'] ?? ''),
        'roomTypes' => $hotelRoomTypeMap[(int)($hotelRow['id'] ?? 0)] ?? [],
    ];
}

$monthLabels = [];
$monthCounts = [];
$monthlyStmt = $conn->query(
    'SELECT DATE_FORMAT(booking_date, "%b %Y") AS month_label, COUNT(*) AS total
     FROM bookings_details
     GROUP BY YEAR(booking_date), MONTH(booking_date)
     ORDER BY YEAR(booking_date), MONTH(booking_date)'
);
foreach ($monthlyStmt->fetchAll() as $row) {
    $monthLabels[] = $row['month_label'];
    $monthCounts[] = (int) $row['total'];
}

$statusLabels = ['pending', 'completed', 'cancelled'];
$statusCounts = [0, 0, 0];
$statusStmt = $conn->query('SELECT booking_status, COUNT(*) AS total FROM bookings_details GROUP BY booking_status');
foreach ($statusStmt->fetchAll() as $row) {
    $idx = array_search($row['booking_status'], $statusLabels, true);
    if ($idx !== false) {
        $statusCounts[$idx] = (int) $row['total'];
    }
}

$metricsLabels = ['Hotels', 'Employees', 'Agents', 'Total Bookings', 'Today Bookings', 'Today New Agents'];
$metricsValues = [
    $totalListings,
    $totalEmployees,
    $totalAgents,
    $totalBookings,
    $todayBookings,
    $todayNewAgents
];

$weeklyLabels = [];
$weeklyCounts = [];
$weeklyStmt = $conn->query(
    'SELECT DATE_FORMAT(booking_date, "%d %b") AS day_label, COUNT(*) AS total
     FROM bookings_details
     WHERE booking_date >= DATE_SUB(CURDATE(), INTERVAL 27 DAY)
     GROUP BY booking_date
     ORDER BY booking_date ASC'
);
foreach ($weeklyStmt->fetchAll() as $row) {
    $weeklyLabels[] = $row['day_label'];
    $weeklyCounts[] = (int) $row['total'];
}

if (count($weeklyLabels) > 7) {
    $weeklyLabels = array_slice($weeklyLabels, -7);
    $weeklyCounts = array_slice($weeklyCounts, -7);
}

$employeeUsersStmt = $conn->query(
    'SELECT u.username, u.email, COALESCE(e.name, u.username) AS employee_name
     FROM users u
     LEFT JOIN employees_details e ON e.email = u.email
     WHERE u.role = "employee"
     ORDER BY employee_name ASC'
);
$employeeUsers = $employeeUsersStmt->fetchAll();

if ($selectedEmployeeUsername === '' && count($employeeUsers) > 0) {
    $selectedEmployeeUsername = $employeeUsers[0]['username'];
}

$selectedEmployeeMeta = null;
$empDailyBookings = 0;
$empWeeklyBookings = 0;
$empMonthlyBookings = 0;
$empDailyAgents = 0;
$empWeeklyAgents = 0;
$empMonthlyAgents = 0;
$empTrendLabels = [];
$empTrendBookingCounts = [];
$empTrendAgentCounts = [];

if ($selectedEmployeeUsername !== '') {
    foreach ($employeeUsers as $empUser) {
        if ($empUser['username'] === $selectedEmployeeUsername) {
            $selectedEmployeeMeta = $empUser;
            break;
        }
    }

    $empDailyBookingsStmt = $conn->prepare('SELECT COUNT(*) FROM bookings_details WHERE created_by = :username AND booking_date = CURDATE()');
    $empDailyBookingsStmt->execute([':username' => $selectedEmployeeUsername]);
    $empDailyBookings = (int) $empDailyBookingsStmt->fetchColumn();

    $empWeeklyBookingsStmt = $conn->prepare('SELECT COUNT(*) FROM bookings_details WHERE created_by = :username AND booking_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)');
    $empWeeklyBookingsStmt->execute([':username' => $selectedEmployeeUsername]);
    $empWeeklyBookings = (int) $empWeeklyBookingsStmt->fetchColumn();

    $empMonthlyBookingsStmt = $conn->prepare('SELECT COUNT(*) FROM bookings_details WHERE created_by = :username AND booking_date >= DATE_FORMAT(CURDATE(), "%Y-%m-01")');
    $empMonthlyBookingsStmt->execute([':username' => $selectedEmployeeUsername]);
    $empMonthlyBookings = (int) $empMonthlyBookingsStmt->fetchColumn();

    $empDailyAgentsStmt = $conn->prepare('SELECT COUNT(*) FROM agents_details WHERE created_by = :username AND DATE(created_at) = CURDATE()');
    $empDailyAgentsStmt->execute([':username' => $selectedEmployeeUsername]);
    $empDailyAgents = (int) $empDailyAgentsStmt->fetchColumn();

    $empWeeklyAgentsStmt = $conn->prepare('SELECT COUNT(*) FROM agents_details WHERE created_by = :username AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)');
    $empWeeklyAgentsStmt->execute([':username' => $selectedEmployeeUsername]);
    $empWeeklyAgents = (int) $empWeeklyAgentsStmt->fetchColumn();

    $empMonthlyAgentsStmt = $conn->prepare('SELECT COUNT(*) FROM agents_details WHERE created_by = :username AND DATE(created_at) >= DATE_FORMAT(CURDATE(), "%Y-%m-01")');
    $empMonthlyAgentsStmt->execute([':username' => $selectedEmployeeUsername]);
    $empMonthlyAgents = (int) $empMonthlyAgentsStmt->fetchColumn();

    $bookingsByDayStmt = $conn->prepare(
        'SELECT DATE(booking_date) AS metric_date, COUNT(*) AS total
         FROM bookings_details
         WHERE created_by = :username AND booking_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
         GROUP BY DATE(booking_date)'
    );
    $bookingsByDayStmt->execute([':username' => $selectedEmployeeUsername]);
    $bookingMap = [];
    foreach ($bookingsByDayStmt->fetchAll() as $row) {
        $bookingMap[$row['metric_date']] = (int) $row['total'];
    }

    $agentsByDayStmt = $conn->prepare(
        'SELECT DATE(created_at) AS metric_date, COUNT(*) AS total
         FROM agents_details
         WHERE created_by = :username AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
         GROUP BY DATE(created_at)'
    );
    $agentsByDayStmt->execute([':username' => $selectedEmployeeUsername]);
    $agentMap = [];
    foreach ($agentsByDayStmt->fetchAll() as $row) {
        $agentMap[$row['metric_date']] = (int) $row['total'];
    }

    for ($i = 6; $i >= 0; $i--) {
        $dateKey = date('Y-m-d', strtotime('-' . $i . ' day'));
        $empTrendLabels[] = date('d M', strtotime($dateKey));
        $empTrendBookingCounts[] = $bookingMap[$dateKey] ?? 0;
        $empTrendAgentCounts[] = $agentMap[$dateKey] ?? 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Uttarakhand Ventures CRM</title>
    <meta name="description" content="Admin overview dashboard for Uttarakhand Ventures CRM.">
    <link rel="icon" type="image/svg+xml" href="/assets/images/favicon.svg">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/sidebar.css">
    <style>
        :root {
            --bg: #f8fafc;
            --panel: #ffffff;
            --nav: #0f172a;
            --muted: #94a3b8;
            --brand: #4f46e5;
            --accent: #06b6d4;
            --green: #10b981;
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
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            font-size: 13px;
            background: var(--bg);
            color: var(--text);
            overflow-x: hidden;
        }
        .btn, .form-control, .form-select, .dropdown-menu, .table {
            font-size: .82rem;
        }
        .btn {
            padding: .34rem .68rem;
        }
        .btn-brand {
            background: var(--brand);
            border-color: var(--brand);
            color: #fff;
            box-shadow: 0 1px 3px rgba(79,70,229,.3);
        }
        .btn-brand:hover {
            background: var(--primary-dark, #4338ca);
            border-color: var(--primary-dark, #4338ca);
            color: #fff;
            box-shadow: 0 4px 12px rgba(79,70,229,.35);
        }
        .btn-light.dropdown-toggle {
            background: #0d9488;
            border-color: #0d9488;
            color: #ffffff;
        }
        .btn-light.dropdown-toggle:hover,
        .btn-light.dropdown-toggle:focus,
        .btn-light.dropdown-toggle.show {
            background: #0f766e;
            border-color: #0f766e;
            color: #ffffff;
        }
        .main-wrapper {
            margin-left: 232px;
            min-height: 100vh;
        }
        .top-header {
            background: var(--panel);
            padding: 10px 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e8eaf3;
            position: sticky;
            top: 0;
            z-index: 20;
            box-shadow: 0 1px 8px rgba(10,20,60,0.05);
        }
        .search-wrap {
            width: min(420px, 100%);
        }
        .search-bar {
            width: 100%;
            border: none;
            border-radius: 30px;
            padding: 8px 12px;
            font-size: .88rem;
            background: #f0f1f7;
            outline: none;
        }
        .top-actions {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .icon-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            border: 1px solid #e5e8f4;
            background: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .user-menu-corner { position: static; }
        .mobile-menu-btn { display: none; }
        .avatar-pill {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(160deg, #7a47f3, #a56dff);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            letter-spacing: .5px;
            cursor: pointer;
        }
        .section-wrap {
            padding: 14px;
        }
        .overview-title {
            font-size: 1.12rem;
            font-weight: 700;
            margin-bottom: 14px;
            color: #1b2233;
        }
        .summary-panel {
            background: var(--panel);
            border-radius: 18px;
            border: 1px solid #e8eaf3;
            padding: 14px;
            position: relative;
            overflow: hidden;
        }
        .panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
            gap: 16px;
        }
        .panel-title {
            font-weight: 700;
            font-size: .94rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .filter-form {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .date-input {
            border: 1px solid #d7dced;
            border-radius: 10px;
            padding: 7px 10px;
            font-size: .95rem;
        }
        .btn-apply {
            background: var(--brand);
            color: #fff;
            border: 0;
            border-radius: 10px;
            padding: 7px 14px;
            font-weight: 600;
            font-size: .95rem;
        }
        .mini-stat {
            border: 1px solid #dde2f0;
            border-radius: 10px;
            background: #fdfdff;
            padding: 10px;
            height: 100%;
        }
        .mini-stat h6 {
            color: #5b667f;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 500;
        }
        .mini-stat .value {
            font-size: 1.15rem;
            font-weight: 700;
            line-height: 1;
        }
        .mini-stat .icon {
            font-size: 18px;
            opacity: .7;
        }
        .mini-stat.soft-green { background: #eef8f3; }
        .mini-stat.soft-purple { background: #f5f1fd; }
        .data-card {
            background: var(--panel);
            border-radius: 18px;
            border: 1px solid #e8eaf3;
            padding: 14px;
            height: 100%;
        }
        .manage-card {
            background: var(--panel);
            border-radius: 18px;
            border: 1px solid #e8eaf3;
            padding: 14px;
            height: 100%;
        }
        .manage-title {
            font-size: .92rem;
            font-weight: 700;
            margin-bottom: 14px;
        }
        .manage-card .form-control,
        .manage-card .form-select {
            border-radius: 10px;
            border-color: #dce3f2;
            font-size: 0.92rem;
        }
        .manage-card .btn {
            border-radius: 10px;
            font-weight: 600;
        }
        .chart-card {
            background: var(--panel);
            border-radius: 18px;
            border: 1px solid #e8eaf3;
            padding: 14px;
        }
        .flash-note {
            border-radius: 12px;
            border: 1px solid transparent;
            padding: 12px 14px;
            margin-bottom: 14px;
            font-weight: 500;
        }
        .flash-note.success {
            background: #e9f7ef;
            border-color: #b7e6cb;
            color: #1f7a4f;
        }
        .flash-note.error {
            background: #fdeeee;
            border-color: #f4c3c3;
            color: #a83737;
        }
        .search-result-box {
            border: 1px solid #dce3f2;
            border-radius: 12px;
            padding: 14px;
            background: #fafbff;
        }
        .search-result-box p {
            margin-bottom: 6px;
            font-size: 0.92rem;
        }
        .kpi-chip {
            border: 1px solid #dce3f2;
            border-radius: 12px;
            padding: 12px;
            background: #fbfcff;
            height: 100%;
        }
        .kpi-chip .label {
            font-size: 0.82rem;
            color: #64708d;
            margin-bottom: 4px;
            display: block;
        }
        .kpi-chip .num {
            font-size: 1rem;
            font-weight: 700;
            line-height: 1.2;
        }
        .data-card h4 {
            font-size: 1.02rem;
            font-weight: 700;
            margin-bottom: 14px;
        }
        .progress-thin {
            height: 8px;
            background: #e9edf6;
            border-radius: 20px;
            overflow: hidden;
        }
        .progress-thin .bar {
            height: 100%;
            border-radius: 20px;
        }
        .property-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
        }
        .property-meta {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .vector-chip {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        @media (max-width: 992px) {
            .mobile-menu-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
            .main-wrapper { margin-left: 0; }
            .top-header {
                flex-wrap: wrap;
                gap: 10px;
                padding: 10px;
            }
            .user-menu-corner {
                position: fixed;
                top: 10px;
                right: 12px;
                z-index: 1102;
            }
            .search-wrap {
                width: 100%;
            }
            .overview-title { font-size: 1.2rem; }
            .panel-title { font-size: .92rem; }
            .data-card h4 { font-size: 1rem; }
            .section-wrap {
                padding: 10px;
            }
            .table-responsive {
                -webkit-overflow-scrolling: touch;
            }
            .table {
                font-size: .78rem;
            }
            .mini-stat .value {
                font-size: 1rem;
            }
        }

        @media (max-width: 576px) {
            .panel,
            .summary-panel,
            .data-card,
            .chart-card,
            .manage-card {
                border-radius: 12px;
                padding: 10px;
            }
            .date-input,
            .btn-apply {
                width: 100%;
            }
            .filter-form {
                width: 100%;
            }
            .top-actions {
                width: 100%;
                justify-content: flex-end;
            }
        }
    </style>
</head>
<body>
<div class="sidebar" id="adminSidebar">
    <div class="sidebar-brand d-flex align-items-center justify-content-between">
        <span class="d-flex align-items-center gap-2"><i class="bi bi-buildings"></i> Uttarakhand Ventures</span>
        <button type="button" class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close menu"><i class="bi bi-x-lg"></i></button>
    </div>
    <ul class="nav flex-column">
        <li class="nav-item"><a class="nav-link active" href="/dashboard.php"><i class="bi bi-grid-1x2"></i> Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="/agents-details.php"><i class="bi bi-person-badge"></i> Agents</a></li>
        <li class="nav-item"><a class="nav-link" href="/bookingquery.php"><i class="bi bi-chat-dots"></i> Booking Query</a></li>
        <li class="nav-item"><a class="nav-link" href="/listing.php"><i class="bi bi-building"></i> Hotel Listings</a></li>
        <li class="nav-item"><a class="nav-link" href="/employees-detail.php"><i class="bi bi-person-vcard"></i> Employees</a></li>
        <li class="nav-item"><a class="nav-link" href="/accounts-detail.php"><i class="bi bi-wallet2"></i> Accounts</a></li>
        <li class="nav-item"><a class="nav-link" href="/booking-details.php"><i class="bi bi-calendar-check"></i> Bookings</a></li>
    </ul>
</div>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
<div class="main-wrapper">
    <header class="top-header">
        <button class="btn btn-light mobile-menu-btn" type="button" id="mobileMenuBtn" aria-label="Open menu"><i class="bi bi-list fs-4"></i></button>
        <input type="text" class="search-bar" placeholder="Search properties, leads..." id="dashboardSearch" onkeydown="if(event.key==='Enter'){dashboardSearchNav(this.value);this.value='';}" />
        <div class="d-flex align-items-center gap-2">
            <div class="dropdown user-menu-corner">
                <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8'); ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="/booking-details.php"><i class="bi bi-clock-history me-2"></i> Booking History</a></li>
                    <li><a class="dropdown-item" href="/export-bookings-excel.php"><i class="bi bi-file-earmark-spreadsheet me-2 text-success"></i> Download Excel</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </header>
    <div class="section-wrap">
        <h2 class="overview-title">Overview</h2>

        <?php if ($flashSuccess !== ''): ?>
            <div class="flash-note success"><i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($flashError !== ''): ?>
            <div class="flash-note error"><i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($credentialNote !== ''): ?>
            <div class="flash-note success"><i class="bi bi-key me-2"></i><?php echo htmlspecialchars($credentialNote, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="summary-panel mb-4">
            <div class="panel-head">
                <h3 class="panel-title"><i class="bi bi-calendar2-week" style="color:#4f46e5;"></i> Daily Booking & Agent Summary</h3>
                <form class="filter-form" method="GET" action="/dashboard.php">
                    <label class="text-muted fw-semibold mb-0">Select Date:</label>
                    <input class="date-input" type="date" name="date" value="<?php echo htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8'); ?>">
                    <button class="btn-apply" type="submit">Apply</button>
                </form>
            </div>

            <div class="row g-3">
                <div class="col-lg-3 col-sm-6">
                    <div class="mini-stat">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6>Total Bookings</h6>
                                <div class="value" id="totalBookingsValue"><?php echo number_format($totalBookings); ?></div>
                            </div>
                            <i class="bi bi-journal-check icon"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="mini-stat">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6>Total Agents</h6>
                                <div class="value" id="totalAgentsValue"><?php echo number_format($totalAgents); ?></div>
                            </div>
                            <i class="bi bi-person-vcard icon"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="mini-stat soft-green">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6>Today's Bookings</h6>
                                <div class="value" id="todayBookingsValue" style="color:var(--green);"><?php echo number_format($todayBookings); ?></div>
                            </div>
                            <i class="bi bi-calendar-plus icon" style="color:var(--green);"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="mini-stat soft-purple">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6>Today's New Agents</h6>
                                <div class="value" id="todayAgentsValue" style="color:#8a61f8;"><?php echo number_format($todayNewAgents); ?></div>
                            </div>
                            <i class="bi bi-person-plus icon" style="color:#8a61f8;"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-lg-4 col-sm-6">
                    <div class="mini-stat">
                        <h6>Total Payment Received</h6>
                        <div class="value text-success">₹<?php echo number_format((float) ($paymentSummary['total_paid'] ?? 0), 0); ?></div>
                    </div>
                </div>
                <div class="col-lg-4 col-sm-6">
                    <div class="mini-stat">
                        <h6>Total Payment Due</h6>
                        <div class="value text-danger">₹<?php echo number_format((float) ($paymentSummary['total_due'] ?? 0), 0); ?></div>
                    </div>
                </div>
                <div class="col-lg-4 col-sm-12">
                    <div class="mini-stat">
                        <h6>Payment Pending / Partial</h6>
                        <div class="value" style="color:#d99100;"><?php echo number_format((int) (($paymentSummary['pending_payment_count'] ?? 0) + ($paymentSummary['partial_payment_count'] ?? 0))); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-xl-4 col-lg-6">
                <div class="data-card">
                    <h4>Top Locations</h4>
                    <?php if (count($topLocations) === 0): ?>
                        <p class="text-muted mb-0">No location booking data found.</p>
                    <?php else: ?>
                        <?php
                        $maxLocation = max(array_map(static function ($r) { return (int) $r['total']; }, $topLocations));
                        if ($maxLocation <= 0) {
                            $maxLocation = 1;
                        }
                        $locColors = ['#4f46e5', '#10b981', '#06b6d4', '#f59e0b'];
                        foreach ($topLocations as $index => $loc):
                            $width = (int) round(((int) $loc['total'] / $maxLocation) * 100);
                            $color = $locColors[$index % count($locColors)];
                        ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-semibold"><?php echo htmlspecialchars($loc['location'] ?: 'Unknown', ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="text-muted"><?php echo (int) $loc['total']; ?> Bookings</span>
                            </div>
                            <div class="progress-thin mb-4">
                                <div class="bar" style="width: <?php echo $width; ?>%; background: <?php echo $color; ?>;"></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-xl-4 col-lg-6">
                <div class="data-card">
                    <h4>Popular Properties</h4>
                    <?php if (count($popularProperties) === 0): ?>
                        <p class="text-muted mb-0">No property booking data found.</p>
                    <?php else: ?>
                        <?php
                        $chips = [
                            ['bg' => '#eef2ff', 'text' => '#4f46e5', 'icon' => 'bi-building'],
                            ['bg' => '#d1fae5', 'text' => '#10b981', 'icon' => 'bi-house-door'],
                            ['bg' => '#fff2e8', 'text' => '#f59b42', 'icon' => 'bi-shop'],
                            ['bg' => '#e8f6ff', 'text' => '#18a4e0', 'icon' => 'bi-house-heart']
                        ];
                        foreach ($popularProperties as $idx => $prop):
                            $chip = $chips[$idx % count($chips)];
                        ?>
                            <div class="property-item">
                                <div class="property-meta">
                                    <span class="vector-chip" style="background: <?php echo $chip['bg']; ?>; color: <?php echo $chip['text']; ?>;"><i class="bi <?php echo $chip['icon']; ?>"></i></span>
                                    <div>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($prop['hotel_name'] ?: 'Unknown Property', ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="text-muted"><?php echo htmlspecialchars($prop['category'] ?: 'Property', ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                </div>
                                <div class="fw-bold" style="font-size: 1.4rem;"><?php echo (int) $prop['total']; ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-xl-4 col-lg-12">
                <div class="data-card">
                    <h4>Employees Booking Status</h4>
                    <canvas id="employeeStatusChart" height="220"></canvas>
                    <div class="mt-3 text-muted small">Total Employees: <?php echo number_format($totalEmployees); ?> | Revenue: <?php echo '₹' . number_format($totalRevenue, 0); ?></div>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-1">
            <div class="col-xl-4 col-lg-6">
                <div class="manage-card">
                    <h5 class="manage-title"><i class="bi bi-building-add me-2"></i>Admin Hotel Listing</h5>
                    <form method="POST" action="/dashboard.php?date=<?php echo urlencode($selectedDate); ?>">
                        <input type="hidden" name="action" value="add_hotel_listing">
                        <input type="hidden" name="selected_date" value="<?php echo htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="mb-2"><input class="form-control" type="text" name="hotel_name" placeholder="Hotel Name" required></div>
                        <div class="mb-2"><input class="form-control" type="text" name="category" placeholder="Category" required></div>
                        <div class="mb-2"><input class="form-control" type="text" name="location" placeholder="Location" required></div>
                        <div class="mb-2"><input class="form-control" type="text" name="room_type" placeholder="Room Type" required></div>
                        <div class="row g-2 mb-2">
                            <div class="col-4"><input class="form-control" type="number" step="0.01" name="weekday_price" placeholder="Weekday"></div>
                            <div class="col-4"><input class="form-control" type="number" step="0.01" name="weekend_price" placeholder="Weekend"></div>
                            <div class="col-4"><input class="form-control" type="number" step="0.01" name="gst" placeholder="GST %"></div>
                        </div>
                        <button class="btn btn-primary w-100" type="submit">Publish Listing</button>
                    </form>
                </div>
            </div>

            <div class="col-xl-4 col-lg-6">
                <div class="manage-card">
                    <h5 class="manage-title"><i class="bi bi-person-plus me-2"></i>Admin Agent Registration</h5>
                    <form method="POST" action="/dashboard.php?date=<?php echo urlencode($selectedDate); ?>">
                        <input type="hidden" name="action" value="register_agent">
                        <input type="hidden" name="selected_date" value="<?php echo htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="mb-2"><input class="form-control" type="text" name="agent_name" placeholder="Agent Name" required></div>
                        <div class="mb-2"><input class="form-control" type="text" name="agent_company" placeholder="Company Name" required></div>
                        <div class="mb-2"><input class="form-control" type="text" name="agent_gst_number" placeholder="GST Number (optional)"></div>
                        <div class="mb-2"><input class="form-control" type="email" name="agent_email" placeholder="Agent Email" required></div>
                        <div class="mb-2"><input class="form-control" type="text" name="agent_phone" placeholder="Mobile Number" required></div>
                        <div class="mb-1 text-muted" style="font-size:.76rem;"></div>
                        <div class="mb-3"><input class="form-control" type="text" name="agent_location" placeholder="Enter location" required></div>
                        <button class="btn btn-success w-100" type="submit">Register Agent</button>
                    </form>
                </div>
            </div>

            <div class="col-xl-4 col-lg-12">
                <div class="manage-card">
                    <h5 class="manage-title"><i class="bi bi-search me-2"></i>Search Agent by Mobile Number</h5>
                    <form method="POST" action="/dashboard.php?date=<?php echo urlencode($selectedDate); ?>" class="mb-3">
                        <input type="hidden" name="action" value="search_agent_mobile">
                        <input type="hidden" name="selected_date" value="<?php echo htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                            <input class="form-control" type="text" name="search_mobile" placeholder="Enter agent mobile" required>
                            <button class="btn btn-outline-primary" type="submit">Search</button>
                        </div>
                    </form>

                    <?php if ($searchedAgent): ?>
                        <div class="search-result-box mb-3">
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($searchedAgent['name'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($searchedAgent['email'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($searchedAgent['phone'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p><strong>GST Number:</strong> <?php echo htmlspecialchars($searchedAgent['gst_number'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="mb-0"><strong>Location:</strong> <?php echo htmlspecialchars($searchedAgent['location'], ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead><tr><th>Booking</th><th>Hotel</th><th>Amount</th><th>Status</th></tr></thead>
                                <tbody>
                                <?php if (count($searchedAgentBookings) === 0): ?>
                                    <tr><td colspan="4" class="text-muted">No bookings found for this agent.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($searchedAgentBookings as $bk): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($bk['booking_code'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($bk['hotel_name'] ?: 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo '₹' . number_format((float) $bk['amount'], 0); ?></td>
                                            <td><?php echo htmlspecialchars($bk['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-1">
            <div class="col-xl-7">
                <div class="manage-card">
                    <h5 class="manage-title"><i class="bi bi-person-badge me-2"></i>Admin Employee Registration (Login ID + Password)</h5>
                    <form method="POST" action="/dashboard.php?date=<?php echo urlencode($selectedDate); ?>">
                        <input type="hidden" name="action" value="register_employee">
                        <input type="hidden" name="selected_date" value="<?php echo htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="row g-2">
                            <div class="col-md-6"><input class="form-control" type="text" name="emp_name" placeholder="Employee Name" required></div>
                            <div class="col-md-6"><input class="form-control" type="email" name="emp_email" placeholder="Employee Email" required></div>
                            <div class="col-md-6"><input class="form-control" type="text" name="emp_phone" placeholder="Phone Number" required></div>
                            <div class="col-md-6"><input class="form-control" type="text" name="emp_designation" placeholder="Designation" required></div>
                            <div class="col-md-6"><input class="form-control" type="text" name="emp_department" placeholder="Department" required></div>
                            <div class="col-md-6"><input class="form-control" type="number" step="0.01" name="emp_salary" placeholder="Monthly Salary"></div>
                            <div class="col-md-6"><input class="form-control" type="text" name="emp_username" placeholder="Login ID (username)" required></div>
                            <div class="col-md-6"><input class="form-control" type="text" name="emp_password" placeholder="Login Password" required></div>
                            <div class="col-12"><button class="btn btn-dark w-100" type="submit">Register Employee & Create Login</button></div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-xl-5">
                <div class="chart-card h-100">
                    <h5 class="manage-title"><i class="bi bi-pie-chart me-2"></i>Booking Status Distribution</h5>
                    <canvas id="bookingStatusChart" height="220"></canvas>
                    <div class="text-muted small mt-2">Graph auto-refreshes every 30 seconds.</div>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-1">
            <div class="col-xl-4">
                <div class="manage-card">
                    <h5 class="manage-title"><i class="bi bi-person-x me-2"></i>Remove Employee Login</h5>
                    <form method="POST" action="/dashboard.php?date=<?php echo urlencode($selectedDate); ?>" onsubmit="return confirm('Is employee login ko remove karna hai?');">
                        <input type="hidden" name="action" value="remove_employee_login">
                        <input type="hidden" name="selected_date" value="<?php echo htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="mb-3">
                            <label class="form-label small text-muted mb-1">Select Employee Login</label>
                            <select class="form-select" name="remove_username" required>
                                <option value="">Choose employee...</option>
                                <?php foreach ($employeeUsers as $empUser): ?>
                                    <option value="<?php echo htmlspecialchars($empUser['username'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($empUser['employee_name'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($empUser['username'], ENT_QUOTES, 'UTF-8'); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="btn btn-outline-danger w-100" type="submit">Remove Login Access</button>
                    </form>
                    <p class="small text-muted mt-2 mb-0">Login delete hone ke baad employee portal me access nahi milega, aur employee status Inactive mark hoga.</p>
                </div>
            </div>

            <div class="col-xl-8">
                <div class="chart-card h-100">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <h5 class="manage-title mb-0"><i class="bi bi-person-lines-fill me-2"></i>Employee-wise Performance (Daily / Week / Month)</h5>
                        <form method="GET" action="/dashboard.php" class="d-flex align-items-center gap-2">
                            <input type="hidden" name="date" value="<?php echo htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8'); ?>">
                            <select class="form-select form-select-sm" name="emp_user" onchange="this.form.submit()">
                                <?php if (count($employeeUsers) === 0): ?>
                                    <option value="">No employees found</option>
                                <?php else: ?>
                                    <?php foreach ($employeeUsers as $empUser): ?>
                                        <option value="<?php echo htmlspecialchars($empUser['username'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedEmployeeUsername === $empUser['username'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($empUser['employee_name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </form>
                    </div>

                    <?php if ($selectedEmployeeMeta): ?>
                        <div class="small text-muted mb-3">
                            Username: <strong><?php echo htmlspecialchars($selectedEmployeeMeta['username'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            | Email: <strong><?php echo htmlspecialchars($selectedEmployeeMeta['email'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        </div>
                    <?php endif; ?>

                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <div class="kpi-chip">
                                <span class="label">Daily Bookings</span>
                                <div class="num"><?php echo $empDailyBookings; ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="kpi-chip">
                                <span class="label">Weekly Bookings</span>
                                <div class="num"><?php echo $empWeeklyBookings; ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="kpi-chip">
                                <span class="label">Monthly Bookings</span>
                                <div class="num"><?php echo $empMonthlyBookings; ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <div class="kpi-chip">
                                <span class="label">Daily Agent Registration</span>
                                <div class="num"><?php echo $empDailyAgents; ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="kpi-chip">
                                <span class="label">Weekly Agent Registration</span>
                                <div class="num"><?php echo $empWeeklyAgents; ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="kpi-chip">
                                <span class="label">Monthly Agent Registration</span>
                                <div class="num"><?php echo $empMonthlyAgents; ?></div>
                            </div>
                        </div>
                    </div>

                    <canvas id="employeePerformanceChart" height="120"></canvas>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-1">
            <div class="col-12">
                <div class="chart-card">
                    <h5 class="manage-title"><i class="bi bi-graph-up me-2"></i>Monthly Booking Trend (Real-time refresh)</h5>
                    <canvas id="revenueTrendChart" height="90"></canvas>
                </div>
            </div>
        </div>

        <!-- Booking Query Module for Admin -->
        <div class="row g-4 mt-4">
            <div class="col-12">
                <div class="data-card">
                    <h4><i class="bi bi-chat-dots me-2"></i>Booking Query Management</h4>
                    <p class="text-muted mb-4">Generate booking queries and manage agent locks (Admin has override access)</p>
                    
                    <!-- Agent Search Section -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-8">
                            <label class="form-label fw-medium">Agent Mobile Number</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                <input type="text" class="form-control" id="adminAgentPhone" placeholder="Enter agent mobile number" maxlength="15">
                                <button class="btn btn-outline-primary" onclick="adminSearchAgent()">
                                    <i class="bi bi-search me-1"></i>Search Agent
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-medium">Agent Status</label>
                            <div id="adminAgentStatus" class="alert alert-light py-2 mb-0">
                                <small class="text-muted">Enter mobile number and click search</small>
                            </div>
                        </div>
                    </div>

                    <!-- Query Form (Hidden initially) -->
                    <div id="adminQueryResult" style="display: none;">
                        <div class="border rounded p-4 bg-light">
                            <h5 class="fw-bold text-dark mb-3">Booking Query Details</h5>
                            <p class="text-muted fs-7">Agent: <strong id="adminQueryAgentName"></strong></p>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label text-muted fw-medium fs-7">Hotel / Property</label>
                                    <div class="hotel-search-wrap">
                                        <input type="hidden" id="adminQueryHotelId">
                                        <input type="text" class="form-control py-2" id="adminQueryHotelName" placeholder="Select hotel" onkeyup="handleAdminQueryHotelInput(this.value)">
                                        <div id="adminQueryHotelSuggestionMenu" class="hotel-suggestion-menu"></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted fw-medium fs-7">Room Category</label>
                                    <select class="form-select py-2" id="adminQueryRoomCategory">
                                        <option value="" selected disabled>Select room category...</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label text-muted fw-medium fs-7">Check-in</label>
                                    <input type="date" class="form-control py-2" id="adminQueryCheckIn" min="<?php echo date('Y-m-d'); ?>" onchange="calculateAdminQueryNights()">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label text-muted fw-medium fs-7">Check-out</label>
                                    <input type="date" class="form-control py-2" id="adminQueryCheckOut" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" onchange="calculateAdminQueryNights()">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label text-muted fw-medium fs-7">Nights</label>
                                    <input type="text" class="form-control py-2" id="adminQueryNights" readonly value="0">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label text-muted fw-medium fs-7">Adults</label>
                                    <input type="number" class="form-control py-2" id="adminQueryAdults" value="1" min="1" max="10">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label text-muted fw-medium fs-7">Children</label>
                                    <input type="number" class="form-control py-2" id="adminQueryChildren" value="0" min="0" max="10">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label text-muted fw-medium fs-7">Rooms</label>
                                    <input type="number" class="form-control py-2" id="adminQueryRooms" value="1" min="1" max="10">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label text-muted fw-medium fs-7">Meal Plan</label>
                                    <select class="form-select py-2" id="adminQueryMealPlan">
                                        <option value="EP (Room Only)">EP (Room Only)</option>
                                        <option value="CP (Room + Breakfast)">CP (Room + Breakfast)</option>
                                        <option value="MAP (Room + Breakfast + Dinner)">MAP (Room + Breakfast + Dinner)</option>
                                        <option value="AP (Room + All Meals)">AP (Room + All Meals)</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label text-muted fw-medium fs-7">Total Amount (INR)</label>
                                    <input type="number" class="form-control py-2" id="adminQueryTotalAmount" placeholder="0" min="0">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted fw-medium fs-7">Extra Bed</label>
                                    <select class="form-select py-2" id="adminQueryExtraBed">
                                        <option value="No">No</option>
                                        <option value="Yes">Yes</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted fw-medium fs-7">Client Name</label>
                                    <input type="text" class="form-control py-2" id="adminQueryClientName" placeholder="Customer name for booking">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted fw-medium fs-7">Client Mobile</label>
                                    <input type="text" class="form-control py-2" id="adminQueryClientMobile" placeholder="Customer mobile number">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted fw-medium fs-7">Client Email (optional)</label>
                                    <input type="email" class="form-control py-2" id="adminQueryClientEmail" placeholder="Customer email">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label text-muted fw-medium fs-7">Special Request / Notes</label>
                                    <textarea class="form-control py-2" id="adminQuerySpecialRequest" rows="2" placeholder="Any notes for the hotel or agent"></textarea>
                                </div>
                            </div>

                            <div class="row g-3 mt-3">
                                <div class="col-md-6">
                                    <button class="btn btn-primary w-100" onclick="adminGenerateQueryFromForm()">
                                        <i class="bi bi-magic me-2"></i>Generate Query + Copy
                                    </button>
                                </div>
                                <div class="col-md-6">
                                    <button class="btn btn-outline-primary w-100" onclick="adminCreateBookingFromQuery()">
                                        <i class="bi bi-calendar-check me-2"></i>Create Booking
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Generated Query Display -->
                    <div id="adminGeneratedQueryDisplay" style="display: none;" class="mt-4">
                        <div class="border rounded p-3 bg-success bg-opacity-10">
                            <h6 class="fw-bold text-success mb-2">Query Generated Successfully!</h6>
                            <textarea class="form-control mb-2" id="adminGeneratedQueryText" rows="6" readonly></textarea>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-outline-success" onclick="copyAdminGeneratedQuery()">
                                    <i class="bi bi-clipboard me-1"></i>Copy Query
                                </button>
                                <a class="btn btn-sm btn-success" id="adminGeneratedQueryWhatsappLink" target="_blank">
                                    <i class="bi bi-whatsapp me-1"></i>Send via WhatsApp
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Agent Locks Management -->
        <div class="row g-4 mt-1">
            <div class="col-12">
                <div class="data-card">
                    <h4><i class="bi bi-lock me-2"></i>Agent Query Locks Management</h4>
                    <p class="text-muted mb-3">View and manage agent locks (Admin can override any lock)</p>
                    <div id="adminAgentLocksTable">
                        <!-- Agent locks will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(() => {
    const sidebar = document.getElementById('adminSidebar');
    const btn = document.getElementById('mobileMenuBtn');
    const closeBtn = document.getElementById('sidebarCloseBtn');
    const backdrop = document.getElementById('sidebarBackdrop');
    if (!sidebar || !btn || !backdrop) return;
    const close = () => { sidebar.classList.remove('open'); backdrop.classList.remove('show'); document.body.style.overflow=''; };
    const open = () => { sidebar.classList.add('open'); backdrop.classList.add('show'); document.body.style.overflow='hidden'; };
    btn.addEventListener('click', open);
    if (closeBtn) closeBtn.addEventListener('click', close);
    backdrop.addEventListener('click', close);
    document.querySelectorAll('.sidebar .nav-link').forEach(l => l.addEventListener('click', close));
    document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
})();


const weeklyLabels = <?php echo json_encode($weeklyLabels); ?>;
const weeklyCounts = <?php echo json_encode($weeklyCounts); ?>;
const monthLabels = <?php echo json_encode($monthLabels); ?>;
const monthCounts = <?php echo json_encode($monthCounts); ?>;
const statusLabels = <?php echo json_encode($statusLabels); ?>;
const statusCounts = <?php echo json_encode($statusCounts); ?>;
const employeeTrendLabels = <?php echo json_encode($empTrendLabels); ?>;
const employeeBookingTrend = <?php echo json_encode($empTrendBookingCounts); ?>;
const employeeAgentTrend = <?php echo json_encode($empTrendAgentCounts); ?>;

let employeeStatusChart = null;
let bookingStatusChart = null;
let revenueTrendChart = null;

// Admin Booking Query Functions
function adminSearchAgent() {
    const phone = document.getElementById('adminAgentPhone').value.trim();
    if (!phone) {
        showToastMsg('Please enter agent mobile number');
        return;
    }

    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'search_agent_by_mobile',
            mobileNumber: phone
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.found) {
            const agent = data.agent;
            let statusHtml = `<div class="d-flex justify-content-between align-items-center">
                <div>
                    <strong>${agent.name}</strong><br>
                    <small class="text-muted">${agent.email} • ${agent.location}</small>
                </div>
                <span class="badge bg-success">Active</span>
            </div>`;
            
            // Check if agent is locked
            if (data.lock_info) {
                const lockTime = new Date(data.lock_info.lock_until);
                const now = new Date();
                if (lockTime > now) {
                    statusHtml = `<div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${agent.name}</strong><br>
                            <small class="text-muted">${agent.email} • ${agent.location}</small><br>
                            <small class="text-warning">Locked until ${lockTime.toLocaleString()}</small>
                        </div>
                        <span class="badge bg-warning">Locked</span>
                    </div>`;
                }
            }
            
            document.getElementById('adminAgentStatus').innerHTML = statusHtml;
            document.getElementById('adminAgentStatus').className = 'alert alert-success py-2 mb-0';
            
            document.getElementById('adminQueryAgentName').textContent = agent.name;
            currentAdminQueryAgent = agent.name;
            
            // Populate hotel dropdown
            // Show the booking query form
            document.getElementById('adminQueryResult').style.display = 'block';
            document.getElementById('adminGeneratedQueryDisplay').style.display = 'none';
        } else {
            document.getElementById('adminAgentStatus').innerHTML = '<small class="text-danger">Agent not found</small>';
            document.getElementById('adminAgentStatus').className = 'alert alert-danger py-2 mb-0';
            document.getElementById('adminQueryResult').style.display = 'none';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('adminAgentStatus').innerHTML = '<small class="text-danger">Error searching agent</small>';
        document.getElementById('adminAgentStatus').className = 'alert alert-danger py-2 mb-0';
    });
}

function handleAdminQueryHotelInput(value) {
    const query = (value || '').trim().toLowerCase();
    if (adminHotelSearchTimer) {
        clearTimeout(adminHotelSearchTimer);
    }

    adminHotelSearchTimer = setTimeout(() => {
        if (!query) {
            const idInput = document.getElementById('adminQueryHotelId');
            if (idInput) idInput.value = '';
            hideAdminQueryHotelSuggestionMenu();
            return;
        }

        const tokens = query.split(/\s+/).map((v) => v.trim()).filter(Boolean);
        const filteredHotels = Object.values(adminHotelCatalog).filter((hotel) => {
            const haystack = [hotel.name || '', hotel.location || '', hotel.category || '', hotel.hotel_code || '']
                .join(' ')
                .toLowerCase();
            return tokens.every((token) => haystack.includes(token));
        });

        if (filteredHotels.length > 0) {
            renderAdminQueryHotelSuggestionMenu(filteredHotels);
        } else {
            hideAdminQueryHotelSuggestionMenu();
        }
    }, 300);
}

function hideAdminQueryHotelSuggestionMenu() {
    const menu = document.getElementById('adminQueryHotelSuggestionMenu');
    if (menu) {
        menu.style.display = 'none';
    }
}

function showAdminQueryHotelSuggestionMenu() {
    const menu = document.getElementById('adminQueryHotelSuggestionMenu');
    if (menu && menu.children.length > 0) {
        menu.style.display = 'block';
    }
}

function renderAdminQueryHotelSuggestionMenu(hotels) {
    const menu = document.getElementById('adminQueryHotelSuggestionMenu');
    if (!menu) {
        return;
    }

    menu.innerHTML = '';
    if (!Array.isArray(hotels) || hotels.length === 0) {
        hideAdminQueryHotelSuggestionMenu();
        return;
    }

    hotels.forEach((hotel) => {
        const item = document.createElement('div');
        item.className = 'hotel-suggestion-item';
        item.innerHTML =
            `<div class="hotel-suggestion-title">${hotel.name}</div><div class="hotel-suggestion-sub">${hotel.category || 'Property'} • ${hotel.location || 'Location N/A'}</div>`;
        item.addEventListener('mousedown', (event) => {
            event.preventDefault();
            selectAdminQueryHotelSuggestion(hotel);
        });
        menu.appendChild(item);
    });

    showAdminQueryHotelSuggestionMenu();
}

function selectAdminQueryHotelSuggestion(hotel) {
    if (!hotel || !hotel.name) {
        return;
    }

    const input = document.getElementById('adminQueryHotelName');
    if (input) {
        input.value = hotel.location ? `${hotel.name}, ${hotel.location}` : hotel.name;
    }

    document.getElementById('adminQueryHotelId').value = hotel.id;
    loadAdminQueryRoomCategories();
    hideAdminQueryHotelSuggestionMenu();
}

function loadAdminQueryRoomCategories() {
    const hotelId = String(document.getElementById('adminQueryHotelId').value || '');
    const hotel = Object.values(adminHotelCatalog).find((item) => String(item.id) === hotelId) || null;
    if (!hotel) {
        return;
    }
    
    const roomSelect = document.getElementById('adminQueryRoomCategory');
    roomSelect.innerHTML = '<option value="" selected disabled>Select room category...</option>';
    (hotel.roomTypes || []).forEach(roomType => {
        const option = document.createElement('option');
        option.value = roomType;
        option.textContent = roomType;
        roomSelect.appendChild(option);
    });

    if (roomSelect.options.length > 1) {
        roomSelect.selectedIndex = 1;
    }
}

function calculateAdminQueryNights() {
    const checkIn = document.getElementById('adminQueryCheckIn').value;
    const checkOut = document.getElementById('adminQueryCheckOut').value;
    const nightsField = document.getElementById('adminQueryNights');
    
    if (checkIn && checkOut) {
        const checkInDate = new Date(checkIn);
        const checkOutDate = new Date(checkOut);
        const diffTime = checkOutDate - checkInDate;
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        nightsField.value = diffDays > 0 ? diffDays : 0;
    } else {
        nightsField.value = 0;
    }
}

function adminGenerateQueryFromForm() {
    const hotelName = document.getElementById('adminQueryHotelName').value.trim();
    const checkIn = document.getElementById('adminQueryCheckIn').value;
    const checkOut = document.getElementById('adminQueryCheckOut').value;
    const adults = document.getElementById('adminQueryAdults').value;
    const children = document.getElementById('adminQueryChildren').value;
    const rooms = document.getElementById('adminQueryRooms').value;
    const roomCategory = document.getElementById('adminQueryRoomCategory').value;
    const mealPlan = document.getElementById('adminQueryMealPlan').value;
    const totalAmount = document.getElementById('adminQueryTotalAmount').value;
    const clientName = document.getElementById('adminQueryClientName').value.trim();
    const clientMobile = document.getElementById('adminQueryClientMobile').value.trim();
    const specialRequest = document.getElementById('adminQuerySpecialRequest').value.trim();
    const agentPhone = document.getElementById('adminAgentPhone').value;
    
    if (!hotelName || !checkIn || !checkOut || !roomCategory || !clientName || !clientMobile || !totalAmount) {
        showToastMsg('Please fill in all required fields');
        return;
    }

    // Validate dates - prevent back dates
    const today = new Date().toISOString().split('T')[0];
    if (checkIn < today) {
        showToastMsg('Check-in date cannot be in the past');
        return;
    }
    if (checkOut <= checkIn) {
        showToastMsg('Check-out date must be after check-in date');
        return;
    }

    const queryText = `Booking Query for Agent: ${currentAdminQueryAgent}\n\n` +
        `Hotel: ${hotelName}\n` +
        `Room Category: ${roomCategory}\n` +
        `Meal Plan: ${mealPlan}\n\n` +
        `Check-in: ${checkIn}\n` +
        `Check-out: ${checkOut}\n` +
        `Adults: ${adults}\n` +
        `Children: ${children}\n` +
        `Rooms: ${rooms}\n\n` +
        `Total Amount: ₹${totalAmount}\n` +
        `Client Name: ${clientName}\n` +
        `Client Mobile: ${clientMobile}\n` +
        (specialRequest ? `Special Request: ${specialRequest}\n` : '');

    // Copy to clipboard
    navigator.clipboard.writeText(queryText).then(() => {
        showToastMsg('Query copied to clipboard');
    }).catch(() => {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = queryText;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showToastMsg('Query copied to clipboard');
    });

    // For admin, we don't lock agents - admin has override access
    document.getElementById('adminGeneratedQueryText').value = queryText;
    document.getElementById('adminGeneratedQueryDisplay').style.display = 'block';
    
    const whatsappUrl = `https://wa.me/${agentPhone.replace(/\D/g, '')}?text=${encodeURIComponent(queryText)}`;
    document.getElementById('adminGeneratedQueryWhatsappLink').href = whatsappUrl;
    
    showToastMsg('Query generated successfully (Admin override - no lock applied)');
}

function copyAdminGeneratedQuery() {
    const queryText = document.getElementById('adminGeneratedQueryText');
    queryText.select();
    document.execCommand('copy');
    showToastMsg('Query copied to clipboard');
}

function adminCreateBookingFromQuery() {
    const hotelName = document.getElementById('adminQueryHotelName').value.trim();
    const hotelId = document.getElementById('adminQueryHotelId').value;
    const roomCategory = document.getElementById('adminQueryRoomCategory').value;
    const checkIn = document.getElementById('adminQueryCheckIn').value;
    const checkOut = document.getElementById('adminQueryCheckOut').value;
    const adults = document.getElementById('adminQueryAdults').value;
    const children = document.getElementById('adminQueryChildren').value;
    const rooms = document.getElementById('adminQueryRooms').value;
    const totalAmount = document.getElementById('adminQueryTotalAmount').value;
    const clientName = document.getElementById('adminQueryClientName').value.trim();
    const clientMobile = document.getElementById('adminQueryClientMobile').value.trim();
    const clientEmail = document.getElementById('adminQueryClientEmail').value.trim();
    const specialRequest = document.getElementById('adminQuerySpecialRequest').value.trim();
    const agentPhone = document.getElementById('adminAgentPhone').value;
    
    if (!hotelName || !checkIn || !checkOut || !roomCategory || !clientName || !clientMobile || !totalAmount) {
        showToastMsg('Please fill in all required fields');
        return;
    }

    // Validate dates - prevent back dates
    const today = new Date().toISOString().split('T')[0];
    if (checkIn < today) {
        showToastMsg('Check-in date cannot be in the past');
        return;
    }
    if (checkOut <= checkIn) {
        showToastMsg('Check-out date must be after check-in date');
        return;
    }

    // Search for agent to get agent ID
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
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
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
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
                    paidAmount: '0',
                    guestCount: adults,
                    roomCount: rooms,
                    specialRequest: specialRequest,
                    bookingSource: 'Admin Query',
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
                        document.getElementById('adminQueryHotelName').value = '';
                        document.getElementById('adminQueryHotelId').value = '';
                        document.getElementById('adminQueryRoomCategory').innerHTML = '<option value="" selected disabled>Select room category...</option>';
                        document.getElementById('adminQueryCheckIn').value = '';
                        document.getElementById('adminQueryCheckOut').value = '';
                        document.getElementById('adminQueryAdults').value = '1';
                        document.getElementById('adminQueryChildren').value = '0';
                        document.getElementById('adminQueryRooms').value = '1';
                        document.getElementById('adminQueryMealPlan').value = 'EP (Room Only)';
                        document.getElementById('adminQueryTotalAmount').value = '0';
                        document.getElementById('adminQueryClientName').value = '';
                        document.getElementById('adminQueryClientMobile').value = '';
                        document.getElementById('adminQueryClientEmail').value = '';
                        document.getElementById('adminQuerySpecialRequest').value = '';
                        
                        // Hide generated query display
                        document.getElementById('adminGeneratedQueryDisplay').style.display = 'none';
                        
                        // Refresh dashboard data
                        location.reload();
                    }, 1500);
                } else {
                    showToastMsg(bookingData.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToastMsg('Error creating booking');
            });
        } else {
            showToastMsg('Agent not found');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToastMsg('Error finding agent');
    });
}

// Hide admin query hotel suggestions when clicking outside
document.addEventListener('click', function(event) {
    const adminQueryHotelInput = document.getElementById('adminQueryHotelName');
    const adminQueryMenu = document.getElementById('adminQueryHotelSuggestionMenu');
    if (adminQueryHotelInput && adminQueryMenu && !adminQueryHotelInput.contains(event.target) && !adminQueryMenu.contains(event.target)) {
        hideAdminQueryHotelSuggestionMenu();
    }
});

// Admin Booking Query Module Data
const adminHotelCatalog =
    <?php echo json_encode($hotel_catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
let adminHotelSearchTimer = null;
let currentAdminQueryAgent = null;
let employeePerformanceChart = null;

function numberFormatIN(value) {
    return new Intl.NumberFormat('en-IN').format(value || 0);
}

if (document.getElementById('employeeStatusChart')) {
    employeeStatusChart = new Chart(document.getElementById('employeeStatusChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: weeklyLabels,
            datasets: [{
                label: 'Bookings',
                data: weeklyCounts,
                backgroundColor: '#4f46e5',
                borderRadius: 8,
                borderSkipped: false
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });
}

if (document.getElementById('bookingStatusChart')) {
    bookingStatusChart = new Chart(document.getElementById('bookingStatusChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusCounts,
                backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                borderWidth: 0
            }]
        },
        options: {
            plugins: {
                legend: {
                    position: 'bottom'
                }
            },
            cutout: '68%'
        }
    });
}

if (document.getElementById('revenueTrendChart')) {
    revenueTrendChart = new Chart(document.getElementById('revenueTrendChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: monthLabels,
            datasets: [{
                label: 'Bookings per Month',
                data: monthCounts,
                borderColor: '#4f46e5',
                backgroundColor: 'rgba(79, 70, 229, 0.12)',
                pointBackgroundColor: '#4f46e5',
                fill: true,
                tension: 0.35
            }]
        },
        options: {
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            }
        }
    });
}

if (document.getElementById('employeePerformanceChart')) {
    employeePerformanceChart = new Chart(document.getElementById('employeePerformanceChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: employeeTrendLabels,
            datasets: [
                {
                    label: 'Bookings',
                    data: employeeBookingTrend,
                    borderColor: '#4f46e5',
                    backgroundColor: 'rgba(79, 70, 229, 0.12)',
                    pointRadius: 3,
                    tension: 0.32,
                    fill: true,
                },
                {
                    label: 'Agent Registrations',
                    data: employeeAgentTrend,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.08)',
                    pointRadius: 3,
                    tension: 0.32,
                    fill: true,
                },
            ]
        },
        options: {
            plugins: {
                legend: { position: 'bottom' }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            }
        }
    });
}

function refreshLiveDashboard() {
    const selectedDate = document.querySelector('input[name="date"]')?.value || '<?php echo htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8'); ?>';
    fetch(`/dashboard.php?action=live_metrics&date=${encodeURIComponent(selectedDate)}`)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                return;
            }

            const totalBookingsEl = document.getElementById('totalBookingsValue');
            const totalAgentsEl = document.getElementById('totalAgentsValue');
            const todayBookingsEl = document.getElementById('todayBookingsValue');
            const todayAgentsEl = document.getElementById('todayAgentsValue');

            if (totalBookingsEl) totalBookingsEl.textContent = numberFormatIN(data.cards.totalBookings);
            if (totalAgentsEl) totalAgentsEl.textContent = numberFormatIN(data.cards.totalAgents);
            if (todayBookingsEl) todayBookingsEl.textContent = numberFormatIN(data.cards.todayBookings);
            if (todayAgentsEl) todayAgentsEl.textContent = numberFormatIN(data.cards.todayNewAgents);

            if (employeeStatusChart) {
                employeeStatusChart.data.labels = data.weekly.labels;
                employeeStatusChart.data.datasets[0].data = data.weekly.counts;
                employeeStatusChart.update('none');
            }

            if (bookingStatusChart) {
                bookingStatusChart.data.datasets[0].data = data.statusCounts;
                bookingStatusChart.update('none');
            }
        })
        .catch(() => {
            // Keep UI stable if refresh fails.
        });
}

setInterval(refreshLiveDashboard, 30000);

function dashboardSearchNav(q) {
	q = q.toLowerCase().trim();
	if (!q) return;
	const pages = [
		{ keywords: ['booking', 'book', 'reservation'], url: '/booking-details.php' },
		{ keywords: ['agent', 'agents'], url: '/agents-details.php' },
		{ keywords: ['employee', 'staff', 'workers'], url: '/employees-detail.php' },
		{ keywords: ['account', 'ledger', 'finance', 'commission', 'payout'], url: '/accounts-detail.php' },
		{ keywords: ['hotel', 'listing', 'room', 'property'], url: '/listing.php' },
		{ keywords: ['query', 'queries'], url: '/bookingquery.php' },
	];
	for (const p of pages) {
		if (p.keywords.some(k => q.includes(k))) {
			window.location.href = p.url;
			return;
		}
	}
	window.location.href = '/booking-details.php?q=' + encodeURIComponent(q);
}

function showToastMsg(message) {
	const existing = document.querySelector('.dashboard-toast');
	if (existing) existing.remove();
	const toast = document.createElement('div');
	toast.className = 'dashboard-toast';
	toast.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#1e293b;color:#fff;padding:12px 20px;border-radius:12px;font-size:.82rem;font-weight:600;z-index:9999;opacity:0;transform:translateY(20px);transition:all .3s;max-width:380px;border-left:4px solid #10b981;';
	toast.textContent = message;
	document.body.appendChild(toast);
	setTimeout(() => { toast.style.opacity='1'; toast.style.transform='translateY(0)'; }, 10);
	setTimeout(() => { toast.style.opacity='0'; toast.style.transform='translateY(20px)'; setTimeout(() => toast.remove(), 300); }, 3000);
}
</script>
<script src="/assets/js/ui-common.js"></script>
</body>
</html>
