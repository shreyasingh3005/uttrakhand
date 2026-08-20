<?php
require_once __DIR__ . '/includes/auth_session.php';
require_once __DIR__ . '/includes/db_connect.php';
require_role('admin');

$accountSearch = sanitize_input($_GET['q'] ?? '');
$accountTypeFilter = sanitize_input($_GET['entry_type'] ?? '');
$accountFromDate = sanitize_input($_GET['from_date'] ?? '');
$accountToDate = sanitize_input($_GET['to_date'] ?? '');
$accountFilterClauses = [];
$accountFilterParams = [];

if ($accountSearch !== '') {
	$accountFilterClauses[] = '(a.notes LIKE :search OR e.name LIKE :search OR a.entry_type LIKE :search OR a.amount LIKE :search)';
	$accountFilterParams[':search'] = '%' . $accountSearch . '%';
}

if ($accountTypeFilter !== '' && in_array($accountTypeFilter, ['commission', 'payout', 'receipt', 'expense'], true)) {
	$accountFilterClauses[] = 'a.entry_type = :entry_type';
	$accountFilterParams[':entry_type'] = $accountTypeFilter;
}

if ($accountFromDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $accountFromDate)) {
	$accountFilterClauses[] = 'a.entry_date >= :from_date';
	$accountFilterParams[':from_date'] = $accountFromDate;
}

if ($accountToDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $accountToDate)) {
	$accountFilterClauses[] = 'a.entry_date <= :to_date';
	$accountFilterParams[':to_date'] = $accountToDate;
}

$accountWhereSql = count($accountFilterClauses) > 0 ? ' WHERE ' . implode(' AND ', $accountFilterClauses) : '';

$summaryStmt = $conn->prepare(
	'SELECT
		COALESCE(SUM(CASE WHEN entry_type = "commission" THEN amount ELSE 0 END), 0) AS total_commission,
		COALESCE(SUM(CASE WHEN entry_type = "payout" THEN amount ELSE 0 END), 0) AS total_payout,
		COALESCE(SUM(CASE WHEN entry_type = "receipt" THEN amount ELSE 0 END), 0) AS total_receipt,
		COALESCE(SUM(CASE WHEN entry_type = "expense" THEN amount ELSE 0 END), 0) AS total_expense
	 FROM accounts_details a
	 JOIN employees_details e ON e.id = a.employee_id' . $accountWhereSql
);
$summaryStmt->execute($accountFilterParams);
$summary = $summaryStmt->fetch();

$accountsStmt = $conn->prepare(
	'SELECT a.entry_date, a.entry_type, a.amount, a.notes, e.name AS employee_name
	 FROM accounts_details a
	 JOIN employees_details e ON e.id = a.employee_id' . $accountWhereSql . '
	 ORDER BY a.entry_date DESC, a.id DESC'
);
$accountsStmt->execute($accountFilterParams);
$accountsRows = $accountsStmt->fetchAll();

$typeLabels = ['Commission', 'Payout', 'Receipt', 'Expense'];
$typeValues = [
	(float) $summary['total_commission'],
	(float) $summary['total_payout'],
	(float) $summary['total_receipt'],
	(float) $summary['total_expense'],
];

function account_type_badge($type) {
	if ($type === 'commission') {
		return 'text-bg-primary';
	}
	if ($type === 'payout') {
		return 'text-bg-warning';
	}
	if ($type === 'receipt') {
		return 'text-bg-success';
	}
	if ($type === 'expense') {
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
	<title>Accounts — Uttarakhand Ventures CRM</title>
	<meta name="description" content="Financial accounts ledger showing commissions, payouts, receipts and expenses.">
	<link rel="icon" type="image/svg+xml" href="/assets/images/favicon.svg">
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
	<link rel="stylesheet" href="/assets/css/sidebar.css">
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
			.table { font-size:.78rem; }
		}
		@media (max-width:576px){
			.panel { padding:10px; border-radius:12px; }
			.summary-value { font-size:.95rem; }
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
		<li class="nav-item"><a class="nav-link" href="/listing.php"><i class="bi bi-building"></i> Hotel Listings</a></li>
		<li class="nav-item"><a class="nav-link" href="/employees-detail.php"><i class="bi bi-person-vcard"></i> Employees</a></li>
		<li class="nav-item"><a class="nav-link active" href="/accounts-detail.php"><i class="bi bi-wallet2"></i> Accounts</a></li>
		<li class="nav-item"><a class="nav-link" href="/booking-details.php"><i class="bi bi-calendar-check"></i> Bookings</a></li>
	</ul>
</div>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<div class="main-wrapper">
	<header class="top-header">
		<button class="btn btn-light mobile-menu-btn" type="button" id="mobileMenuBtn" aria-label="Open menu"><i class="bi bi-list fs-4"></i></button>
		<div class="d-flex gap-2 flex-wrap align-items-center" style="width:min(760px,100%);">
			<input class="search-bar flex-grow-1" type="text" id="headerSearch" placeholder="Search transaction, employee, note..." onkeyup="liveSearchAccounts(this.value)" />
			<button class="btn btn-outline-secondary" onclick="document.getElementById('headerSearch').value=''; liveSearchAccounts('');"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</button>
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
				<h4 class="fw-bold mb-0">Financial Accounts & Records</h4>
				<span class="text-muted small">Complete commission, payout, receipt and expense control</span>
			</div>
			<div class="row g-3">
				<div class="col-md-3"><div class="summary-card"><div class="summary-label">Total Commission</div><div class="summary-value">₹<?php echo number_format((float) $summary['total_commission'], 0); ?></div></div></div>
				<div class="col-md-3"><div class="summary-card"><div class="summary-label">Total Payout</div><div class="summary-value">₹<?php echo number_format((float) $summary['total_payout'], 0); ?></div></div></div>
				<div class="col-md-3"><div class="summary-card"><div class="summary-label">Total Receipt</div><div class="summary-value text-success">₹<?php echo number_format((float) $summary['total_receipt'], 0); ?></div></div></div>
				<div class="col-md-3"><div class="summary-card"><div class="summary-label">Total Expense</div><div class="summary-value text-danger">₹<?php echo number_format((float) $summary['total_expense'], 0); ?></div></div></div>
			</div>
		</div>

		<div class="panel mb-4">
			<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
				<h6 class="fw-bold mb-0">Search & Filters</h6>
				<span class="text-muted small">Find ledger rows by employee, note, entry type or date</span>
			</div>
			<div class="row g-3 filter-grid">
				<div class="col-lg-5 col-md-6"><input class="form-control" id="accFilterQ" placeholder="Employee, notes, amount" onkeyup="accLiveFilter()"></div>
				<div class="col-lg-2 col-md-3"><select class="form-select" id="accFilterType" onchange="accLiveFilter()"><option value="">All Types</option><option value="Commission">Commission</option><option value="Payout">Payout</option><option value="Receipt">Receipt</option><option value="Expense">Expense</option></select></div>
				<div class="col-lg-1 col-md-12 d-flex gap-2">
					<button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('accFilterQ').value=''; document.getElementById('accFilterType').value=''; accLiveFilter();"><i class="bi bi-x-circle"></i> Reset</button>
				</div>
			</div>
		</div>

		<div class="row g-4">
			<div class="col-xl-9">
				<div class="panel">
					<h6 class="fw-bold mb-3">Employee Accounts Ledger</h6>
					<div class="table-responsive">
						<table class="table align-middle">
							<thead><tr><th>Date</th><th>Employee</th><th>Type</th><th>Amount</th><th>Notes</th></tr></thead>
							<tbody>
							<?php if (count($accountsRows) === 0): ?>
								<tr><td colspan="5" class="text-muted">No account entries found.</td></tr>
							<?php else: ?>
								<?php foreach ($accountsRows as $row): ?>
									<tr>
										<td><?php echo htmlspecialchars($row['entry_date'], ENT_QUOTES, 'UTF-8'); ?></td>
										<td><?php echo htmlspecialchars($row['employee_name'], ENT_QUOTES, 'UTF-8'); ?></td>
										<td><span class="badge <?php echo account_type_badge($row['entry_type']); ?>"><?php echo htmlspecialchars(ucfirst($row['entry_type']), ENT_QUOTES, 'UTF-8'); ?></span></td>
										<td><?php echo '₹' . number_format((float) $row['amount'], 0); ?></td>
										<td><?php echo htmlspecialchars((string) $row['notes'], ENT_QUOTES, 'UTF-8'); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>

			<div class="col-xl-3">
				<div class="panel h-100">
					<h6 class="fw-bold mb-3">Entry Type Distribution</h6>
					<canvas id="accountsTypeChart" height="220"></canvas>
				</div>
			</div>
		</div>
	</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/admin-sidebar.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="/assets/js/ui-common.js"></script>
<script>
const typeLabels = <?php echo json_encode($typeLabels); ?>;
const typeValues = <?php echo json_encode($typeValues); ?>;

function liveSearchAccounts(q) {
	q = q.toLowerCase().trim();
	document.getElementById('accFilterQ').value = q;
	accLiveFilter();
}

function accLiveFilter() {
	var q = (document.getElementById('accFilterQ').value || '').toLowerCase().trim();
	var type = (document.getElementById('accFilterType').value || '').trim();
	document.querySelectorAll('.table tbody tr').forEach(row => {
		var text = row.textContent.toLowerCase();
		var rowType = (row.querySelector('td:nth-child(2)') || {}).textContent || '';
		var matchQ = !q || text.includes(q);
		var matchType = !type || rowType.includes(type);
		row.style.display = (matchQ && matchType) ? '' : 'none';
	});
}

if (document.getElementById('accountsTypeChart')) {
	new Chart(document.getElementById('accountsTypeChart').getContext('2d'), {
		type: 'polarArea',
		data: {
			labels: typeLabels,
			datasets: [{
				data: typeValues,
				backgroundColor: ['#4f46e5','#f59e0b','#10b981','#ef4444'],
				borderWidth: 0
			}]
		},
		options: { plugins: { legend: { position: 'bottom' } } }
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
