<?php
require_once __DIR__ . '/includes/auth_session.php';
require_once __DIR__ . '/includes/db_connect.php';
require_role('admin');

$flashMessage = '';
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'admin_update_payment') {
	$bookingId = (int) ($_POST['booking_id'] ?? 0);
	$paidAmount = (float) ($_POST['paid_amount'] ?? 0);
	$bookingStatus = sanitize_input($_POST['booking_status'] ?? 'Pending');
	$paymentNote = sanitize_input($_POST['payment_note'] ?? '');
	$returnQuery = sanitize_input($_POST['return_query'] ?? '');
	$redirectUrl = '/booking-details.php' . ($returnQuery !== '' ? '?' . $returnQuery : '');
	$allowedBookingStatuses = ['Pending', 'Completed', 'Cancelled'];
	if (!in_array($bookingStatus, $allowedBookingStatuses, true)) {
		$bookingStatus = 'Pending';
	}

	if ($bookingId > 0 && $paidAmount >= 0) {
		try {
			$findStmt = $conn->prepare('SELECT amount FROM bookings_details WHERE id = :id LIMIT 1');
			$findStmt->execute([':id' => $bookingId]);
			$bookingBase = $findStmt->fetch();

			if ($bookingBase) {
				$totalAmount = (float) $bookingBase['amount'];
				$dueAmount = max($totalAmount - $paidAmount, 0);
				$paymentStatus = $paidAmount <= 0 ? 'Pending' : (($paidAmount >= $totalAmount) ? 'Paid' : 'Partial');
				if ($bookingStatus === 'Cancelled') {
					$paymentStatus = 'Cancelled';
					$dueAmount = 0;
				}

				$updateStmt = $conn->prepare(
					'UPDATE bookings_details
					 SET paid_amount = :paid_amount,
						 due_amount = :due_amount,
						 payment_status = :payment_status,
						 booking_status = :booking_status,
						 status = :legacy_status,
						 payment_note = :payment_note,
						 payment_updated_by = :updated_by,
						 payment_updated_at = NOW()
					 WHERE id = :id'
				);
				$updateStmt->execute([
					':paid_amount' => $paidAmount,
					':due_amount' => $dueAmount,
					':payment_status' => $paymentStatus,
					':booking_status' => $bookingStatus,
					':legacy_status' => $bookingStatus === 'Completed' ? 'Confirmed' : ($bookingStatus === 'Cancelled' ? 'Cancelled' : 'Pending Payment'),
					':payment_note' => $paymentNote,
					':updated_by' => $_SESSION['username'],
					':id' => $bookingId,
				]);

				header('Location: ' . $redirectUrl . (str_contains($redirectUrl, '?') ? '&' : '?') . 'updated=1');
				exit;
			}
		} catch (PDOException $e) {
			header('Location: ' . $redirectUrl . (str_contains($redirectUrl, '?') ? '&' : '?') . 'error=1');
			exit;
		}
	}

	header('Location: ' . $redirectUrl . (str_contains($redirectUrl, '?') ? '&' : '?') . 'error=1');
	exit;
}

if (isset($_GET['updated'])) {
	$flashMessage = 'Payment status updated successfully.';
	$flashType = 'success';
} elseif (isset($_GET['error'])) {
	$flashMessage = 'Payment update failed. Please try again.';
	$flashType = 'danger';
}

$bookingSearch = sanitize_input($_GET['q'] ?? '');
$bookingCodeFilter = sanitize_input($_GET['booking_code'] ?? '');
$bookingStatusFilter = sanitize_input($_GET['booking_status'] ?? '');
$paymentStatusFilter = sanitize_input($_GET['payment_status'] ?? '');
$agentPhoneFilter = sanitize_input($_GET['agent_phone'] ?? '');
$fromDateFilter = sanitize_input($_GET['from_date'] ?? '');
$toDateFilter = sanitize_input($_GET['to_date'] ?? '');
$filterClauses = [];
$filterParams = [];

if ($bookingSearch !== '') {
	$filterClauses[] = 'CONCAT_WS(" ", b.booking_code, b.client_name, h.hotel_name, a.name, a.phone, e.name, b.created_by, b.booking_status, b.payment_status) LIKE :search';
	$filterParams[':search'] = '%' . $bookingSearch . '%';
}

if ($bookingCodeFilter !== '') {
	$filterClauses[] = 'b.booking_code LIKE :booking_code';
	$filterParams[':booking_code'] = '%' . $bookingCodeFilter . '%';
}

if ($bookingStatusFilter !== '' && in_array($bookingStatusFilter, ['Pending', 'Completed', 'Cancelled'], true)) {
	$filterClauses[] = 'b.booking_status = :booking_status';
	$filterParams[':booking_status'] = $bookingStatusFilter;
}

if ($paymentStatusFilter !== '' && in_array($paymentStatusFilter, ['Pending', 'Partial', 'Paid', 'Cancelled'], true)) {
	$filterClauses[] = 'b.payment_status = :payment_status';
	$filterParams[':payment_status'] = $paymentStatusFilter;
}

if ($agentPhoneFilter !== '') {
	$filterClauses[] = 'a.phone LIKE :agent_phone';
	$filterParams[':agent_phone'] = '%' . $agentPhoneFilter . '%';
}

if ($fromDateFilter !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDateFilter)) {
	$filterClauses[] = 'b.booking_date >= :from_date';
	$filterParams[':from_date'] = $fromDateFilter;
}

if ($toDateFilter !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDateFilter)) {
	$filterClauses[] = 'b.booking_date <= :to_date';
	$filterParams[':to_date'] = $toDateFilter;
}

$bookingWhereSql = count($filterClauses) > 0 ? ' WHERE ' . implode(' AND ', $filterClauses) : '';

$bookingsStmt = $conn->prepare(
	'SELECT b.id, b.booking_code, b.client_name, b.amount, b.paid_amount, b.due_amount, b.payment_status, b.payment_note, b.payment_updated_by, b.booking_status, b.status, b.booking_date, b.check_in, b.check_out, b.created_by, b.booking_source, b.guest_count, b.room_count, b.special_request,
			COALESCE(NULLIF(b.hotel_name_snapshot, ""), h.hotel_name) AS hotel_name, a.name AS agent_name, a.phone AS agent_phone, a.location AS agent_location, e.name AS employee_name
	 FROM bookings_details b
	 JOIN hotel_listings_details h ON h.id = b.hotel_listing_id
	 JOIN agents_details a ON a.id = b.agent_id
	 LEFT JOIN employees_details e ON e.id = b.employee_id' . $bookingWhereSql . '
	 ORDER BY b.booking_date DESC, b.id DESC'
);
$bookingsStmt->execute($filterParams);
$bookings = $bookingsStmt->fetchAll();

$summaryStmt = $conn->prepare(
	'SELECT
		COUNT(*) AS total_bookings,
		COALESCE(SUM(amount),0) AS total_amount,
		COALESCE(SUM(paid_amount),0) AS total_paid,
		COALESCE(SUM(due_amount),0) AS total_due,
		SUM(CASE WHEN payment_status = "Pending" THEN 1 ELSE 0 END) AS payment_pending_count,
		SUM(CASE WHEN booking_status = "Completed" THEN 1 ELSE 0 END) AS completed_count,
		SUM(CASE WHEN booking_status = "Pending" THEN 1 ELSE 0 END) AS pending_count,
		SUM(CASE WHEN booking_status = "Cancelled" THEN 1 ELSE 0 END) AS cancelled_count
	 FROM bookings_details b
	 JOIN hotel_listings_details h ON h.id = b.hotel_listing_id
	 JOIN agents_details a ON a.id = b.agent_id
	 LEFT JOIN employees_details e ON e.id = b.employee_id' . $bookingWhereSql
);
$summaryStmt->execute($filterParams);
$summary = $summaryStmt->fetch();

$statusChartStmt = $conn->prepare(
	'SELECT booking_status, COUNT(*) AS total
	 FROM bookings_details b
	 JOIN hotel_listings_details h ON h.id = b.hotel_listing_id
	 JOIN agents_details a ON a.id = b.agent_id
	 LEFT JOIN employees_details e ON e.id = b.employee_id' . $bookingWhereSql . '
	 GROUP BY booking_status'
);
$statusChartStmt->execute($filterParams);
$statusChartRows = $statusChartStmt->fetchAll();
$statusCountsMap = ['Pending' => 0, 'Completed' => 0, 'Cancelled' => 0];
foreach ($statusChartRows as $row) {
	if (isset($statusCountsMap[$row['booking_status']])) {
		$statusCountsMap[$row['booking_status']] = (int) $row['total'];
	}
}

$bookingReturnQuery = http_build_query(array_filter([
	'q' => $bookingSearch,
	'booking_code' => $bookingCodeFilter,
	'booking_status' => $bookingStatusFilter,
	'payment_status' => $paymentStatusFilter,
	'agent_phone' => $agentPhoneFilter,
	'from_date' => $fromDateFilter,
	'to_date' => $toDateFilter,
], static fn($value) => $value !== '' && $value !== null));

$statusLabels = ['Pending', 'Completed', 'Cancelled'];
$statusValues = [
	(int) $statusCountsMap['Pending'],
	(int) $statusCountsMap['Completed'],
	(int) $statusCountsMap['Cancelled'],
];

function booking_status_badge($status) {
	if ($status === 'Pending') {
		return 'text-bg-warning';
	}
	if ($status === 'Completed') {
		return 'text-bg-success';
	}
	if ($status === 'Cancelled') {
		return 'text-bg-danger';
	}
	return 'text-bg-secondary';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Booking Details — Uttarakhand Ventures CRM</title>
	<meta name="description" content="Manage and track all hotel booking details, payments and statuses.">
	<link rel="icon" type="image/svg+xml" href="/assets/images/favicon.svg">
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
	<link rel="stylesheet" href="/assets/css/sidebar.css">
	<link rel="stylesheet" href="/assets/css/ui-modern.css">
	<style>
		:root { --bg:#f8fafc; --panel:#fff; --nav:#0f172a; --muted:#94a3b8; --brand:#4f46e5; --accent:#06b6d4; --success:#10b981; --warning:#f59e0b; --danger:#ef4444; --text:#0f172a; --text-secondary:#475569; --border:#e2e8f0; --primary-50:#eef2ff; --primary-200:#c7d2fe; }
		body { font-family:'Inter','Segoe UI',system-ui,sans-serif; background:var(--bg); color:var(--text); font-size:13px; }
		.btn, .form-control, .form-select, .dropdown-menu, .table { font-size:.82rem; }
		.btn { padding:.34rem .68rem; }
		.btn-light.dropdown-toggle { background:#0d9488; border-color:#0d9488; color:#fff; }
		.btn-light.dropdown-toggle:hover,.btn-light.dropdown-toggle:focus,.btn-light.dropdown-toggle.show { background:#0f766e; border-color:#0f766e; color:#fff; }
		.main-wrapper { margin-left:232px; min-height:100vh; }
		.top-header { background:rgba(255,255,255,.95); padding:10px 14px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border); position:sticky; top:0; z-index:20; backdrop-filter:blur(10px); }
		.search-bar { border:none; border-radius:30px; padding:8px 12px; font-size:.88rem; background:#f1f5f9; width:min(420px,100%); outline:none; transition:all .2s; }
		.search-bar:focus { background:#fff; border-color:var(--brand); box-shadow:0 0 0 3px var(--primary-50); }
		.panel { background:#fff; border:1px solid var(--border); border-radius:18px; padding:14px; position:relative; overflow:hidden; }
		.summary-card { border:1px solid var(--border); border-radius:12px; background:#fbfcff; padding:10px; }
		.summary-label { color:var(--text-secondary); font-size:.84rem; }
		.summary-value { font-size:1rem; font-weight:700; }
		.table { font-size:.82rem; }
		.meta-inline { font-size:.76rem; color:var(--text-secondary); display:block; margin-top:2px; }
		.request-note { font-size:.74rem; color:var(--text-secondary); display:block; margin-top:2px; max-width:220px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
		.badge-created { background:var(--primary-50); color:var(--brand); font-weight:600; }
		.filter-grid .form-control,.filter-grid .form-select { border-radius:12px; border-color:var(--border); }
		.filter-grid .form-control:focus,.filter-grid .form-select:focus { border-color:var(--brand); box-shadow:0 0 0 3px var(--primary-50); }
		.user-menu-corner { position:static; }
		.mobile-menu-btn { display:none; }
		.btn-brand { background:var(--brand); border-color:var(--brand); color:#fff; box-shadow:0 1px 3px rgba(79,70,229,.3); }
		.btn-brand:hover { background:var(--primary-dark,#4338ca); border-color:var(--primary-dark,#4338ca); color:#fff; box-shadow:0 4px 12px rgba(79,70,229,.35); }
		@media (max-width:992px){
			.mobile-menu-btn { display:inline-flex; align-items:center; justify-content:center; }
			.main-wrapper { margin-left:0; }
			.top-header { flex-wrap:wrap; gap:10px; padding:10px; }
			.top-header form { width:100%!important; }
			.user-menu-corner { position:fixed; top:10px; right:12px; z-index:1102; }
			.container-fluid { padding:12px!important; }
			.table { font-size:.76rem; }
			.request-note { max-width:150px; }
		}
		@media (max-width:576px){
			.panel { padding:10px; border-radius:12px; }
			.form-control,.form-select,.btn { font-size:.84rem; }
		}
	</style>
</head>
<body>
<div class="sidebar" id="adminSidebar">
	<div class="sidebar-brand">
		<span class="d-flex align-items-center gap-2">
			<span class="brand-icon"><i class="bi bi-buildings"></i></span>
			Uttarakhand Ventures
		</span>
		<button type="button" class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close menu"><i class="bi bi-x-lg"></i></button>
	</div>
	<ul class="nav flex-column">
		<li class="nav-item"><a class="nav-link" href="/dashboard.php"><i class="bi bi-grid-1x2"></i> Dashboard</a></li>
		<li class="nav-item"><a class="nav-link" href="/agents-details.php"><i class="bi bi-person-badge"></i> Agents</a></li>
		<li class="nav-item"><a class="nav-link" href="/bookingquery.php"><i class="bi bi-chat-dots"></i> Booking Query</a></li>
		<li class="nav-item"><a class="nav-link" href="/query-history.php"><i class="bi bi-clock-history"></i> Query History</a></li>
		<li class="nav-item"><a class="nav-link" href="/listing.php"><i class="bi bi-building"></i> Hotel Listings</a></li>
		<li class="nav-item"><a class="nav-link" href="/employees-detail.php"><i class="bi bi-person-vcard"></i> Employees</a></li>
		<li class="nav-item"><a class="nav-link" href="/accounts-detail.php"><i class="bi bi-wallet2"></i> Accounts</a></li>
		<li class="nav-item"><a class="nav-link active" href="/booking-details.php"><i class="bi bi-calendar-check"></i> Bookings</a></li>
	</ul>
</div>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<div class="main-wrapper">
	<header class="top-header">
		<button class="btn btn-light mobile-menu-btn" type="button" id="mobileMenuBtn" aria-label="Open menu"><i class="bi bi-list fs-4"></i></button>
		<div class="d-flex gap-2 flex-wrap align-items-center" style="width:min(760px,100%);">
			<input class="search-bar flex-grow-1" type="text" id="headerSearch" placeholder="Search booking, client, hotel, agent..." onkeyup="liveSearchBookings(this.value)" />
			<button class="btn btn-outline-secondary" onclick="document.getElementById('headerSearch').value=''; liveSearchBookings('');"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</button>
		</div>
		<div class="dropdown user-menu-corner">
			<button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
				<i class="bi bi-person-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8'); ?>
			</button>
			<ul class="dropdown-menu dropdown-menu-end">
				<li><a class="dropdown-item" href="/booking-details.php"><i class="bi bi-clock-history me-2"></i> Booking History</a></li>
				<li><a class="dropdown-item" href="/export-bookings-excel.php"><i class="bi bi-file-earmark-spreadsheet me-2 text-success"></i> Download Excel</a></li>
				<li><hr class="dropdown-divider"></li>
				<li><a class="dropdown-item text-danger" href="/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
			</ul>
		</div>
	</header>

	<div class="container-fluid p-4">
		<?php if ($flashMessage !== ''): ?>
			<div class="alert alert-<?php echo htmlspecialchars($flashType, ENT_QUOTES, 'UTF-8'); ?> shadow-sm mb-4"><?php echo htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8'); ?></div>
		<?php endif; ?>

		<div class="panel mb-4">
			<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
				<h4 class="fw-bold mb-0">Bookings Operations Board</h4>
				<span class="text-muted small">Live booking and payment status overview</span>
			</div>
			<div class="row g-3">
				<div class="col-md-3"><div class="summary-card"><div class="summary-label">Total Bookings</div><div class="summary-value"><?php echo number_format((int) $summary['total_bookings']); ?></div></div></div>
				<div class="col-md-3"><div class="summary-card"><div class="summary-label">Total Amount</div><div class="summary-value">₹<?php echo number_format((float) $summary['total_amount'], 0); ?></div></div></div>
				<div class="col-md-3"><div class="summary-card"><div class="summary-label">Total Received</div><div class="summary-value text-success">₹<?php echo number_format((float) $summary['total_paid'], 0); ?></div></div></div>
				<div class="col-md-3"><div class="summary-card"><div class="summary-label">Total Due</div><div class="summary-value text-danger">₹<?php echo number_format((float) $summary['total_due'], 0); ?></div></div></div>
				<div class="col-md-3"><div class="summary-card"><div class="summary-label">Payment Pending Bookings</div><div class="summary-value" style="color:#d99100;"><?php echo number_format((int) $summary['payment_pending_count']); ?></div></div></div>
				<div class="col-md-3"><div class="summary-card"><div class="summary-label">Completed Bookings</div><div class="summary-value text-success"><?php echo number_format((int) $summary['completed_count']); ?></div></div></div>
			</div>
		</div>

		<div class="panel mb-4">
			<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
				<h6 class="fw-bold mb-0">Search & Filters</h6>
				<span class="text-muted small">Find bookings by name, booking code, agent number, date or status</span>
			</div>
			<div class="row g-3 filter-grid">
				<div class="col-lg-3 col-md-6"><input class="form-control" id="bkFilterQ" placeholder="Booking, client, hotel, agent" onkeyup="bkLiveFilter()"></div>
				<div class="col-lg-2 col-md-6"><input class="form-control" id="bkFilterCode" placeholder="Booking code" onkeyup="bkLiveFilter()"></div>
				<div class="col-lg-2 col-md-6"><input class="form-control" id="bkFilterAgent" placeholder="Agent number" onkeyup="bkLiveFilter()"></div>
				<div class="col-lg-1 col-md-6"><select class="form-select" id="bkFilterStatus" onchange="bkLiveFilter()"><option value="">Status</option><option value="Pending">Pending</option><option value="Completed">Completed</option><option value="Cancelled">Cancelled</option></select></div>
				<div class="col-lg-2 col-md-6"><select class="form-select" id="bkFilterPayment" onchange="bkLiveFilter()"><option value="">Payment</option><option value="Pending">Pending</option><option value="Partial">Partial</option><option value="Paid">Paid</option><option value="Cancelled">Cancelled</option></select></div>
				<div class="col-lg-2 col-md-6 d-flex gap-2">
					<button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('bkFilterQ').value=''; document.getElementById('bkFilterCode').value=''; document.getElementById('bkFilterAgent').value=''; document.getElementById('bkFilterStatus').value=''; document.getElementById('bkFilterPayment').value=''; bkLiveFilter();"><i class="bi bi-x-circle"></i> Reset</button>
				</div>
			</div>
		</div>

		<div class="row g-4">
			<div class="col-xl-12">
				<div class="panel">
					<div class="table-responsive">
						<table class="table align-middle">
							<thead><tr><th>Booking ID</th><th>Client</th><th>Hotel</th><th>Dates</th><th>Total</th><th>Paid</th><th>Due</th><th>Payment</th><th>Agent</th><th>Employee</th><th>Created By</th><th>Status</th><th>Copy Data</th></tr></thead>
							<tbody>
							<?php if (count($bookings) === 0): ?>
								<tr><td colspan="13" class="text-muted">No bookings found.</td></tr>
							<?php else: ?>
								<?php foreach ($bookings as $booking): ?>
									<tr>
										<td>#<?php echo htmlspecialchars($booking['booking_code'], ENT_QUOTES, 'UTF-8'); ?></td>
										<td>
											<?php echo htmlspecialchars($booking['client_name'], ENT_QUOTES, 'UTF-8'); ?>
											<span class="meta-inline">Source: <?php echo htmlspecialchars((string)($booking['booking_source'] ?? 'Direct'), ENT_QUOTES, 'UTF-8'); ?></span>
											<span class="meta-inline">Guests: <?php echo (int)($booking['guest_count'] ?? 1); ?> | Rooms: <?php echo (int)($booking['room_count'] ?? 1); ?></span>
											<?php if (!empty($booking['special_request'])): ?>
												<span class="request-note" title="<?php echo htmlspecialchars((string)$booking['special_request'], ENT_QUOTES, 'UTF-8'); ?>">Note: <?php echo htmlspecialchars((string)$booking['special_request'], ENT_QUOTES, 'UTF-8'); ?></span>
											<?php endif; ?>
										</td>
										<td><?php echo htmlspecialchars($booking['hotel_name'], ENT_QUOTES, 'UTF-8'); ?></td>
										<td><?php echo htmlspecialchars($booking['check_in'], ENT_QUOTES, 'UTF-8'); ?> to <?php echo htmlspecialchars($booking['check_out'], ENT_QUOTES, 'UTF-8'); ?></td>
										<td><?php echo '₹' . number_format((float) $booking['amount'], 0); ?></td>
										<td class="text-success fw-semibold"><?php echo '₹' . number_format((float) ($booking['paid_amount'] ?? 0), 0); ?></td>
										<td class="text-danger fw-semibold"><?php echo '₹' . number_format((float) ($booking['due_amount'] ?? 0), 0); ?></td>
										<td>
											<span class="badge <?php echo (($booking['payment_status'] ?? 'Pending') === 'Paid') ? 'bg-success' : ((($booking['payment_status'] ?? 'Pending') === 'Partial') ? 'bg-warning text-dark' : 'bg-secondary'); ?>">
												<?php echo htmlspecialchars($booking['payment_status'] ?? 'Pending', ENT_QUOTES, 'UTF-8'); ?>
											</span>
											<button
												type="button"
												class="btn btn-sm btn-light border rounded-circle ms-1"
												title="Edit payment"
												onclick="openAdminPaymentEditor(<?php echo (int)$booking['id']; ?>, <?php echo (float)$booking['amount']; ?>, <?php echo (float)($booking['paid_amount'] ?? 0); ?>, '<?php echo htmlspecialchars((string)($booking['payment_note'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars((string)($booking['booking_status'] ?? 'Pending'), ENT_QUOTES, 'UTF-8'); ?>')">
												<i class="bi bi-pencil"></i>
											</button>
											<?php if (($booking['booking_status'] ?? 'Pending') !== 'Cancelled'): ?>
												<button type="button" class="btn btn-sm btn-outline-danger ms-1" title="Cancel booking" onclick="cancelBooking(<?php echo (int)$booking['id']; ?>)">
													<i class="bi bi-x-circle"></i>
												</button>
											<?php endif; ?>
										</td>
										<td><?php echo htmlspecialchars($booking['agent_name'], ENT_QUOTES, 'UTF-8'); ?></td>
										<td><?php echo htmlspecialchars((string) ($booking['employee_name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?></td>
										<td><span class="badge badge-created"><i class="bi bi-person-fill me-1"></i><?php echo htmlspecialchars($booking['created_by'], ENT_QUOTES, 'UTF-8'); ?></span></td>
										<td><span class="badge <?php echo booking_status_badge($booking['booking_status'] ?? 'Pending'); ?>"><?php echo htmlspecialchars($booking['booking_status'] ?? 'Pending', ENT_QUOTES, 'UTF-8'); ?></span></td>
										<td>
											<button
												type="button"
												class="btn btn-sm btn-outline-primary"
												onclick="copyAdminBookingData(this)"
												data-booking='<?php echo htmlspecialchars(json_encode([
													'booking_code' => (string)($booking['booking_code'] ?? ''),
													'client_name' => (string)($booking['client_name'] ?? ''),
													'hotel_name' => (string)($booking['hotel_name'] ?? ''),
													'agent_name' => (string)($booking['agent_name'] ?? ''),
													'agent_phone' => (string)($booking['agent_phone'] ?? ''),
													'agent_location' => (string)($booking['agent_location'] ?? ''),
													'employee_name' => (string)($booking['employee_name'] ?? 'N/A'),
													'created_by' => (string)($booking['created_by'] ?? ''),
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
													'payment_note' => (string)($booking['payment_note'] ?? ''),
												]), ENT_QUOTES, 'UTF-8'); ?>'>
													<i class="bi bi-clipboard me-1"></i>Copy
											</button>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="adminPaymentModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<form method="POST" action="/booking-details.php">
				<div class="modal-header">
					<h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Update Payment</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
				</div>
				<div class="modal-body">
					<input type="hidden" name="action" value="admin_update_payment">
					<input type="hidden" name="booking_id" id="adminEditBookingId">
					<input type="hidden" name="return_query" value="<?php echo htmlspecialchars($bookingReturnQuery, ENT_QUOTES, 'UTF-8'); ?>">
					<div class="mb-2 text-muted small">Total Amount: <strong id="adminEditTotalAmount">₹0</strong></div>
					<div class="mb-3">
						<label class="form-label">Booking Status</label>
						<select class="form-select" name="booking_status" id="adminEditBookingStatus" required>
							<option value="Pending">Pending</option>
							<option value="Completed">Completed</option>
							<option value="Cancelled">Cancelled</option>
						</select>
					</div>
					<div class="mb-3">
						<label class="form-label">Paid Amount</label>
						<input type="number" class="form-control" name="paid_amount" id="adminEditPaidAmount" min="0" step="100" required>
					</div>
					<div class="mb-1">
						<label class="form-label">Payment Note / Reference</label>
						<textarea class="form-control" name="payment_note" id="adminEditPaymentNote" rows="3"></textarea>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary">Save Changes</button>
				</div>
			</form>
		</div>
	</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function liveSearchBookings(q) {
	q = q.toLowerCase().trim();
	document.getElementById('bkFilterQ').value = q;
	bkLiveFilter();
}

function bkLiveFilter() {
	var q = (document.getElementById('bkFilterQ').value || '').toLowerCase().trim();
	var code = (document.getElementById('bkFilterCode').value || '').toLowerCase().trim();
	var agent = (document.getElementById('bkFilterAgent').value || '').toLowerCase().trim();
	var status = (document.getElementById('bkFilterStatus').value || '').trim();
	var payment = (document.getElementById('bkFilterPayment').value || '').trim();
	document.querySelectorAll('.table tbody tr').forEach(row => {
		var text = row.textContent.toLowerCase();
		var matchQ = !q || text.includes(q);
		var matchCode = !code || text.includes(code);
		var matchAgent = !agent || text.includes(agent);
		var matchStatus = !status || text.includes(status.toLowerCase());
		var matchPayment = !payment || text.includes(payment.toLowerCase());
		row.style.display = (matchQ && matchCode && matchAgent && matchStatus && matchPayment) ? '' : 'none';
	});
}

let adminPaymentModal = null;

function toggleSidebarMenu(open) {
	const sidebar = document.querySelector('.sidebar');
	const backdrop = document.getElementById('sidebarBackdrop');
	if (!sidebar || !backdrop) return;
	sidebar.classList.toggle('open', !!open);
	backdrop.classList.toggle('show', !!open);
}

(() => {
	const btn = document.getElementById('mobileMenuBtn');
	const closeBtn = document.getElementById('sidebarCloseBtn');
	const backdrop = document.getElementById('sidebarBackdrop');
	if (btn) btn.addEventListener('click', () => toggleSidebarMenu(true));
	if (closeBtn) closeBtn.addEventListener('click', () => toggleSidebarMenu(false));
	if (backdrop) backdrop.addEventListener('click', () => toggleSidebarMenu(false));
	document.querySelectorAll('.sidebar .nav-link').forEach((link) => link.addEventListener('click', () => toggleSidebarMenu(false)));
})();

	function openAdminPaymentEditor(bookingId, totalAmount, paidAmount, note, bookingStatus) {
	document.getElementById('adminEditBookingId').value = bookingId;
	document.getElementById('adminEditTotalAmount').textContent = '₹' + Number(totalAmount).toLocaleString('en-IN');
	document.getElementById('adminEditPaidAmount').value = paidAmount;
	document.getElementById('adminEditPaymentNote').value = note || '';
		document.getElementById('adminEditBookingStatus').value = bookingStatus || 'Pending';
	if (!adminPaymentModal) {
		adminPaymentModal = new bootstrap.Modal(document.getElementById('adminPaymentModal'));
	}
	adminPaymentModal.show();
}

	function cancelBooking(bookingId) {
	if (!confirm('Are you sure you want to cancel this booking?')) {
			return;
		}

		const form = document.createElement('form');
		form.method = 'POST';
		form.action = '/booking-details.php';
		
		const returnQueryInput = document.createElement('input');
		returnQueryInput.type = 'hidden';
		returnQueryInput.name = 'return_query';
		returnQueryInput.value = <?php echo json_encode($bookingReturnQuery); ?>;
		form.appendChild(returnQueryInput);

		const actionInput = document.createElement('input');
		actionInput.type = 'hidden';
		actionInput.name = 'action';
		actionInput.value = 'admin_update_payment';
		form.appendChild(actionInput);

		const bookingInput = document.createElement('input');
		bookingInput.type = 'hidden';
		bookingInput.name = 'booking_id';
		bookingInput.value = bookingId;
		form.appendChild(bookingInput);

		const paidInput = document.createElement('input');
		paidInput.type = 'hidden';
		paidInput.name = 'paid_amount';
		paidInput.value = 0;
		form.appendChild(paidInput);

		const statusInput = document.createElement('input');
		statusInput.type = 'hidden';
		statusInput.name = 'booking_status';
		statusInput.value = 'Cancelled';
		form.appendChild(statusInput);

		const noteInput = document.createElement('input');
		noteInput.type = 'hidden';
		noteInput.name = 'payment_note';
		noteInput.value = 'Cancelled by admin';
		form.appendChild(noteInput);

		document.body.appendChild(form);
		form.submit();
	}

	function formatAdminShareDate(value) {
		if (!value) return 'N/A';
		const d = new Date(value);
		if (Number.isNaN(d.getTime())) return value;
		return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
	}

	function buildAdminBookingShareText(booking) {
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
			`📍 Location: ${booking.agent_location || 'N/A'}`,
			`📞 Contact: ${booking.agent_phone || 'N/A'}`,
			'',
			'🏨 Hotel Stay',
			'────────────',
			`${booking.hotel_name || 'N/A'}`,
			`Check-in: ${formatAdminShareDate(booking.check_in)}`,
			`Check-out: ${formatAdminShareDate(booking.check_out)}`,
			`Guests: ${booking.guest_count || 1} | Rooms: ${booking.room_count || 1}`,
			'',
			'💰 Price (INR):',
			`Total Amount: ₹${Number(booking.amount || 0).toLocaleString('en-IN')}`,
			`Advance Paid: ₹${Number(booking.paid_amount || 0).toLocaleString('en-IN')}`,
			`Due Amount: ₹${Number(booking.due_amount || 0).toLocaleString('en-IN')}`,
			'',
			'📌 Other Details',
			`Source: ${booking.booking_source || 'Direct'}`,
			`Booking Date: ${formatAdminShareDate(booking.booking_date)}`,
			`Booking Status: ${booking.booking_status || 'Pending'}`,
			`Payment Status: ${booking.payment_status || 'Pending'}`,
			`Created By: ${booking.created_by || 'N/A'}`,
			`Employee: ${booking.employee_name || 'N/A'}`,
			'',
			`Special Request: ${booking.special_request || 'N/A'}`,
			`Payment Note: ${booking.payment_note || 'N/A'}`,
			'',
			'━━━━━━━━━━━━',
			'Thank you for choosing us!'
		].join('\n');
	}

	async function copyAdminBookingData(buttonEl) {
		try {
			const raw = buttonEl.getAttribute('data-booking');
			if (!raw) return;
			const booking = JSON.parse(raw);
			const text = buildAdminBookingShareText(booking);

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

			const oldHtml = buttonEl.innerHTML;
			buttonEl.innerHTML = '<i class="bi bi-check2 me-1"></i>Copied';
			buttonEl.classList.remove('btn-outline-primary');
			buttonEl.classList.add('btn-success');
			setTimeout(() => {
				buttonEl.innerHTML = oldHtml;
				buttonEl.classList.add('btn-outline-primary');
				buttonEl.classList.remove('btn-success');
			}, 1200);
		} catch (error) {
			console.error('Copy failed:', error);
			alert('Copy failed. Please try again.');
		}
	}

</script>
<script>
(() => {
	const sidebar = document.getElementById('adminSidebar');
	const backdrop = document.getElementById('sidebarBackdrop');
	const openBtn = document.getElementById('mobileMenuBtn');
	const closeBtn = document.getElementById('sidebarCloseBtn');
	if (!sidebar || !backdrop) return;
	const open = () => { sidebar.classList.add('open'); backdrop.classList.add('show'); document.body.style.overflow='hidden'; };
	const close = () => { sidebar.classList.remove('open'); backdrop.classList.remove('show'); document.body.style.overflow=''; };
	if (openBtn) openBtn.addEventListener('click', open);
	if (closeBtn) closeBtn.addEventListener('click', close);
	backdrop.addEventListener('click', close);
	document.addEventListener('keydown', e => { if (e.key==='Escape') close(); });
})();
</script>
<script src="/assets/js/ui-common.js"></script>
</body>
</html>
