<?php
require_once __DIR__ . '/includes/auth_session.php';
require_once __DIR__ . '/includes/db_connect.php';
require_role('admin');

$employeeSearch = sanitize_input($_GET['q'] ?? '');
$employeeDepartmentFilter = sanitize_input($_GET['department'] ?? '');
$employeeStatusFilter = sanitize_input($_GET['status'] ?? '');
$employeeFilterClauses = [];
$employeeFilterParams = [];

if ($employeeSearch !== '') {
	$employeeFilterClauses[] = '(e.name LIKE :search OR e.phone LIKE :search OR e.email LIKE :search OR e.designation LIKE :search OR e.department LIKE :search)';
	$employeeFilterParams[':search'] = '%' . $employeeSearch . '%';
}

if ($employeeDepartmentFilter !== '') {
	$employeeFilterClauses[] = 'e.department = :department';
	$employeeFilterParams[':department'] = $employeeDepartmentFilter;
}

if ($employeeStatusFilter !== '' && in_array($employeeStatusFilter, ['Active', 'On Leave', 'Inactive'], true)) {
	$employeeFilterClauses[] = 'e.status = :status';
	$employeeFilterParams[':status'] = $employeeStatusFilter;
}

$employeeWhereSql = count($employeeFilterClauses) > 0 ? ' WHERE ' . implode(' AND ', $employeeFilterClauses) : '';

$employeesStmt = $conn->prepare(
	'SELECT e.*, u.username AS login_username, u.is_logged_in, u.last_login_at, u.last_logout_at, COUNT(b.id) AS booking_count, COALESCE(SUM(b.amount), 0) AS booking_amount
	 FROM employees_details e
	 LEFT JOIN users u ON u.email = e.email AND u.role = "employee"
	 LEFT JOIN bookings_details b ON b.employee_id = e.id' . $employeeWhereSql . '
	 GROUP BY e.id
	 ORDER BY e.created_at DESC, e.id DESC'
);
$employeesStmt->execute($employeeFilterParams);
$employees = $employeesStmt->fetchAll();

$archivedUsersStmt = $conn->query(
	'SELECT b.created_by AS username,
			COALESCE(u.email, "") AS email,
			COUNT(b.id) AS booking_count,
			COALESCE(SUM(b.amount), 0) AS booking_amount,
			MAX(b.created_at) AS last_activity
	 FROM bookings_details b
	 LEFT JOIN users u ON u.username = b.created_by AND u.role = "employee"
	 LEFT JOIN employees_details e ON e.email = u.email
	 WHERE b.created_by IS NOT NULL
	   AND b.created_by <> ""
	   AND e.id IS NULL
	 GROUP BY b.created_by, u.email
	 ORDER BY last_activity DESC'
);
$archivedEmployeeRecords = $archivedUsersStmt->fetchAll();

$summaryStmt = $conn->prepare(
	'SELECT
		COUNT(*) AS total_employees,
		SUM(CASE WHEN status = "Active" THEN 1 ELSE 0 END) AS active_employees,
		COALESCE(SUM(monthly_salary), 0) AS total_salary,
		COALESCE(AVG(monthly_salary), 0) AS avg_salary
	 FROM employees_details e' . $employeeWhereSql
);
$summaryStmt->execute($employeeFilterParams);
$summary = $summaryStmt->fetch();

$deptStmt = $conn->prepare(
	'SELECT department, COUNT(*) AS total
	 FROM employees_details e' . $employeeWhereSql . '
	 GROUP BY department
	 ORDER BY total DESC'
);
$deptStmt->execute($employeeFilterParams);
$deptLabels = [];
$deptValues = [];
foreach ($deptStmt->fetchAll() as $row) {
	$deptLabels[] = $row['department'];
	$deptValues[] = (int) $row['total'];
}

function employee_status_badge($status) {
	if ($status === 'Active') {
		return 'text-bg-success';
	}
	if ($status === 'On Leave') {
		return 'text-bg-warning';
	}
	return 'text-bg-secondary';
}

function login_status_badge_class($isLoggedIn) {
	return (int) $isLoggedIn === 1 ? 'text-bg-success' : 'text-bg-secondary';
}

function login_status_label($isLoggedIn) {
	return (int) $isLoggedIn === 1 ? 'Active' : 'Inactive';
}

function time_ago_label($dateTime) {
	if (!$dateTime) {
		return '';
	}

	$timestamp = strtotime((string) $dateTime);
	if ($timestamp === false) {
		return '';
	}

	$diff = time() - $timestamp;
	if ($diff < 60) {
		return 'just now';
	}
	if ($diff < 3600) {
		$minutes = (int) floor($diff / 60);
		return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
	}
	if ($diff < 86400) {
		$hours = (int) floor($diff / 3600);
		return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
	}
	$days = (int) floor($diff / 86400);
	return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Employees — Uttarakhand Ventures CRM</title>
	<meta name="description" content="Employee directory, payroll summary and booking contribution tracking.">
	<link rel="icon" type="image/svg+xml" href="/assets/images/favicon.svg">
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
	<link rel="stylesheet" href="/assets/css/sidebar.css">
	<style>
		:root { --bg:#f8fafc; --panel:#fff; --nav-start:#0f172a; --nav-end:#1e293b; --sidebar-text:#94a3b8; --primary:#4f46e5; --primary-dark:#4338ca; --primary-50:#eef2ff; --primary-200:#c7d2fe; --accent:#06b6d4; --success:#10b981; --warning:#f59e0b; --danger:#ef4444; --text:#0f172a; --text-secondary:#475569; --text-muted:#94a3b8; --border:#e2e8f0; }
		body { font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif; background:var(--bg); color:var(--text); font-size:14px; -webkit-font-smoothing:antialiased; }
		.btn, .form-control, .form-select, .dropdown-menu, .table { font-size:.84rem; font-family:inherit; }
		.btn { padding:.4rem .85rem; border-radius:10px; font-weight:600; transition:all .25s cubic-bezier(.16,1,.3,1); }
		.btn:hover { transform:translateY(-1px); }
		.main-wrapper { margin-left:232px; min-height:100vh; display:flex; flex-direction:column; }
		.top-header { background:rgba(255,255,255,.92); backdrop-filter:blur(12px); padding:0 24px; height:64px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border); position:sticky; top:0; z-index:20; box-shadow:0 1px 2px rgba(0,0,0,.04); }
		.search-bar { border:1px solid var(--border); border-radius:9999px; padding:9px 18px; font-size:.88rem; background:#f1f5f9; width:min(420px,100%); outline:none; transition:all .25s cubic-bezier(.16,1,.3,1); color:var(--text); }
		.search-bar::placeholder { color:var(--text-muted); }
		.search-bar:focus { background:#fff; border-color:var(--primary); box-shadow:0 0 0 3px var(--primary-50); }
		.panel { background:#fff; border:1px solid var(--border); border-radius:20px; padding:24px; position:relative; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.06); transition:all .25s cubic-bezier(.16,1,.3,1); }
		.panel:hover { box-shadow:0 4px 6px -1px rgba(0,0,0,.07); }
		.summary-card { border:1px solid var(--border); border-radius:14px; background:#fff; padding:18px 20px; transition:all .25s cubic-bezier(.16,1,.3,1); position:relative; overflow:hidden; }
		.summary-card::before { content:''; position:absolute; top:0; left:0; width:100%; height:3px; background:linear-gradient(90deg,var(--primary),var(--accent)); opacity:0; transition:opacity .25s; }
		.summary-card:hover { border-color:var(--primary-200); box-shadow:0 10px 15px -3px rgba(0,0,0,.08); transform:translateY(-2px); }
		.summary-card:hover::before { opacity:1; }
		.summary-label { color:var(--text-muted); font-size:.8rem; font-weight:500; margin-bottom:6px; }
		.summary-value { font-size:1.3rem; font-weight:800; letter-spacing:-.02em; }
		.employee-card { border:1px solid var(--border); border-radius:20px; background:#fff; font-size:.88rem; transition:all .25s cubic-bezier(.16,1,.3,1); box-shadow:0 1px 2px rgba(0,0,0,.04); }
		.employee-card:hover { transform:translateY(-4px); box-shadow:0 10px 15px -3px rgba(0,0,0,.08); border-color:var(--primary-200); }
		.login-meta { font-size:.78rem; color:#64748b; }
		.archived-card { border:1px dashed var(--border); border-radius:14px; background:#f8fafc; }
		.filter-grid .form-control,.filter-grid .form-select { border-radius:10px; border-color:var(--border); transition:all .25s; }
		.filter-grid .form-control:focus,.filter-grid .form-select:focus { border-color:var(--primary); box-shadow:0 0 0 3px var(--primary-50); }
		.btn-brand { background:var(--primary); border-color:var(--primary); color:#fff; box-shadow:0 1px 3px rgba(79,70,229,.3); }
		.btn-brand:hover { background:var(--primary-dark); border-color:var(--primary-dark); color:#fff; box-shadow:0 4px 12px rgba(79,70,229,.35); }
		.user-menu-corner { position:static; }
		.mobile-menu-btn { display:none; }
		@media (max-width:992px){
			.mobile-menu-btn { display:inline-flex; align-items:center; justify-content:center; width:38px; height:38px; border:1px solid var(--border); background:transparent; border-radius:10px; cursor:pointer; font-size:20px; color:var(--text); }
			.main-wrapper { margin-left:0; }
			.top-header { flex-wrap:wrap; gap:10px; padding:0 14px; height:56px; }
			.top-header form { width:100%!important; }
			.user-menu-corner { position:fixed; top:10px; right:12px; z-index:1102; }
			.container-fluid { padding:16px!important; }
		}
		@media (max-width:576px){
			.panel { padding:14px; border-radius:14px; }
			.employee-card { padding:12px!important; font-size:.84rem; }
			.archived-card { padding:10px!important; }
			.form-control,.form-select,.btn { font-size:.84rem; }
		}
	</style>
</head>
<body>
<div class="sidebar" id="adminSidebar">
	<div class="sidebar-brand">
		<span class="d-flex align-items-center gap-2"><span class="brand-icon"><i class="bi bi-buildings"></i></span> Uttarakhand Ventures</span>
		<button type="button" class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close menu"><i class="bi bi-x-lg"></i></button>
	</div>
	<ul class="nav flex-column">
		<li class="nav-item"><a class="nav-link" href="/dashboard.php"><i class="bi bi-grid-1x2"></i> Dashboard</a></li>
		<li class="nav-item"><a class="nav-link" href="/agents-details.php"><i class="bi bi-person-badge"></i> Agents</a></li>
		<li class="nav-item"><a class="nav-link" href="/bookingquery.php"><i class="bi bi-chat-dots"></i> Booking Query</a></li>
		<li class="nav-item"><a class="nav-link" href="/listing.php"><i class="bi bi-building"></i> Hotel Listings</a></li>
		<li class="nav-item"><a class="nav-link active" href="/employees-detail.php"><i class="bi bi-person-vcard"></i> Employees</a></li>
		<li class="nav-item"><a class="nav-link" href="/accounts-detail.php"><i class="bi bi-wallet2"></i> Accounts</a></li>
		<li class="nav-item"><a class="nav-link" href="/booking-details.php"><i class="bi bi-calendar-check"></i> Bookings</a></li>
	</ul>
</div>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<div class="main-wrapper">
	<header class="top-header">
		<button class="btn btn-light mobile-menu-btn" type="button" id="mobileMenuBtn" aria-label="Open menu"><i class="bi bi-list fs-4"></i></button>
		<div class="d-flex gap-2 flex-wrap align-items-center" style="width:min(760px,100%);">
			<input class="search-bar flex-grow-1" type="text" id="headerSearch" placeholder="Search employee, phone, department..." onkeyup="liveSearchEmployees(this.value)" />
			<button class="btn btn-outline-secondary" onclick="document.getElementById('headerSearch').value=''; liveSearchEmployees('');"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</button>
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
		<div class="panel mb-4">
			<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
				<h4 class="fw-bold mb-0">Employees Operations Board</h4>
				<span class="text-muted small">Directory, compensation and booking contribution</span>
			</div>
			<div class="row g-3">
				<div class="col-md-3"><div class="summary-card"><div class="summary-label">Total Employees</div><div class="summary-value"><?php echo number_format((int) $summary['total_employees']); ?></div></div></div>
				<div class="col-md-3"><div class="summary-card"><div class="summary-label">Active Employees</div><div class="summary-value text-success"><?php echo number_format((int) $summary['active_employees']); ?></div></div></div>
				<div class="col-md-3"><div class="summary-card"><div class="summary-label">Total Payroll</div><div class="summary-value">₹<?php echo number_format((float) $summary['total_salary'], 0); ?></div></div></div>
				<div class="col-md-3"><div class="summary-card"><div class="summary-label">Average Salary</div><div class="summary-value">₹<?php echo number_format((float) $summary['avg_salary'], 0); ?></div></div></div>
			</div>
		</div>

		<div class="panel mb-4">
			<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
				<h6 class="fw-bold mb-0">Search & Filters</h6>
				<span class="text-muted small">Find employees by name, department or phone number</span>
			</div>
			<div class="row g-3 filter-grid">
				<div class="col-lg-6 col-md-6"><input class="form-control" id="empFilterQ" placeholder="Employee name, phone, designation" onkeyup="empLiveFilter()"></div>
				<div class="col-lg-3 col-md-3"><input class="form-control" id="empFilterDept" placeholder="Department" onkeyup="empLiveFilter()"></div>
				<div class="col-lg-2 col-md-3"><select class="form-select" id="empFilterStatus" onchange="empLiveFilter()"><option value="">All Status</option><option value="Active">Active</option><option value="On Leave">On Leave</option><option value="Inactive">Inactive</option></select></div>
				<div class="col-lg-1 col-md-12 d-flex gap-2">
					<button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('empFilterQ').value=''; document.getElementById('empFilterDept').value=''; document.getElementById('empFilterStatus').value=''; empLiveFilter();"><i class="bi bi-x-circle"></i> Reset</button>
				</div>
			</div>
		</div>

		<div class="row g-4">
			<div class="col-xl-9">
				<div class="row g-4">
					<?php if (count($employees) === 0): ?>
						<div class="col-12"><div class="panel text-muted">No employees found.</div></div>
					<?php else: ?>
						<?php foreach ($employees as $employee): ?>
							<div class="col-xl-4 col-md-6">
								<div class="employee-card p-4 h-100">
									<div class="d-flex justify-content-between align-items-start mb-2">
										<h6 class="fw-bold mb-0"><?php echo htmlspecialchars($employee['name'], ENT_QUOTES, 'UTF-8'); ?></h6>
										<span class="badge <?php echo login_status_badge_class($employee['is_logged_in'] ?? 0); ?>"><?php echo htmlspecialchars(login_status_label($employee['is_logged_in'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></span>
									</div>
									<p class="text-muted small mb-2"><?php echo htmlspecialchars($employee['designation'], ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars($employee['department'], ENT_QUOTES, 'UTF-8'); ?></p>
									<?php if ((int)($employee['is_logged_in'] ?? 0) === 1): ?>
										<p class="login-meta mb-2"><i class="bi bi-circle-fill text-success me-1" style="font-size:.55rem;"></i>Currently logged in</p>
									<?php elseif (!empty($employee['last_logout_at'])): ?>
										<p class="login-meta mb-2"><i class="bi bi-clock-history me-1"></i>Last logout: <?php echo htmlspecialchars(time_ago_label($employee['last_logout_at']), ENT_QUOTES, 'UTF-8'); ?></p>
									<?php else: ?>
										<p class="login-meta mb-2"><i class="bi bi-dash-circle me-1"></i>No logout history</p>
									<?php endif; ?>
									<p class="mb-1 small"><strong>HR Status:</strong> <span class="badge <?php echo employee_status_badge($employee['status']); ?>"><?php echo htmlspecialchars($employee['status'], ENT_QUOTES, 'UTF-8'); ?></span></p>
									<p class="mb-1 small"><strong>Login ID:</strong> <?php echo htmlspecialchars((string)($employee['login_username'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?></p>
									<p class="mb-1 small"><strong>Phone:</strong> <?php echo htmlspecialchars($employee['phone'], ENT_QUOTES, 'UTF-8'); ?></p>
									<p class="mb-1 small"><strong>Email:</strong> <?php echo htmlspecialchars($employee['email'], ENT_QUOTES, 'UTF-8'); ?></p>
									<p class="mb-1 small"><strong>Salary:</strong> <?php echo '₹' . number_format((float) $employee['monthly_salary'], 0); ?></p>
									<p class="mb-0 small"><strong>Bookings:</strong> <?php echo (int) $employee['booking_count']; ?> | <strong>Amount:</strong> <?php echo '₹' . number_format((float) $employee['booking_amount'], 0); ?></p>
									<a class="btn btn-sm btn-outline-success mt-3" href="/export-employee-excel.php?employee_id=<?php echo (int) $employee['id']; ?>">
										<i class="bi bi-file-earmark-spreadsheet me-1"></i>Download Full Data
									</a>
								</div>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>

				<?php if (count($archivedEmployeeRecords) > 0): ?>
					<div class="panel mt-4">
						<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
							<h6 class="fw-bold mb-0">Archived / Deleted Employee Booking Records</h6>
							<span class="text-muted small">Booking history for removed employee accounts is still available for export</span>
						</div>
						<div class="row g-3">
							<?php foreach ($archivedEmployeeRecords as $archived): ?>
								<div class="col-lg-6">
									<div class="archived-card p-3 h-100">
										<div class="fw-semibold mb-1"><?php echo htmlspecialchars((string)$archived['username'], ENT_QUOTES, 'UTF-8'); ?></div>
										<div class="text-muted small mb-2"><?php echo htmlspecialchars((string)($archived['email'] ?: 'Email not available'), ENT_QUOTES, 'UTF-8'); ?></div>
										<div class="small mb-2"><strong>Bookings:</strong> <?php echo (int)$archived['booking_count']; ?> | <strong>Amount:</strong> ₹<?php echo number_format((float)$archived['booking_amount'], 0); ?></div>
										<a class="btn btn-sm btn-outline-success" href="/export-employee-excel.php?username=<?php echo urlencode((string)$archived['username']); ?>">
											<i class="bi bi-download me-1"></i>Download Archived Data
										</a>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>
			</div>
			<div class="col-xl-3">
				<div class="panel h-100">
					<h6 class="fw-bold mb-3">Department Spread</h6>
					<canvas id="departmentChart" height="220"></canvas>
				</div>
			</div>
		</div>
	</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="/assets/js/admin-sidebar.js"></script>
<script src="/assets/js/ui-common.js"></script>
<script>

function liveSearchEmployees(q) {
	q = q.toLowerCase().trim();
	document.getElementById('empFilterQ').value = q;
	empLiveFilter();
}

function empLiveFilter() {
	var q = (document.getElementById('empFilterQ').value || '').toLowerCase().trim();
	var dept = (document.getElementById('empFilterDept').value || '').toLowerCase().trim();
	var status = (document.getElementById('empFilterStatus').value || '').trim();
	document.querySelectorAll('.employee-card').forEach(card => {
		var col = card.closest('.col-xl-4, .col-md-6, .col-12');
		if (!col) return;
		var text = card.textContent.toLowerCase();
		var cardStatus = '';
		var badges = card.querySelectorAll('.badge');
		badges.forEach(b => { if (b.textContent.includes('Active') || b.textContent.includes('On Leave') || b.textContent.includes('Inactive')) cardStatus = b.textContent.trim(); });
		var matchQ = !q || text.includes(q);
		var matchDept = !dept || text.includes(dept);
		var matchStatus = !status || cardStatus.includes(status);
		col.style.display = (matchQ && matchDept && matchStatus) ? '' : 'none';
	});
}

const deptLabels = <?php echo json_encode($deptLabels); ?>;
const deptValues = <?php echo json_encode($deptValues); ?>;
if (document.getElementById('departmentChart')) {
	new Chart(document.getElementById('departmentChart').getContext('2d'), {
		type: 'bar',
		data: {
			labels: deptLabels,
			datasets: [{
				data: deptValues,
				backgroundColor: ['#4f46e5','#10b981','#06b6d4','#f59e0b','#ef4444','#94a3b8'],
				borderRadius: 8,
				borderSkipped: false
			}]
		},
		options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
	});
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
</body>
</html>
