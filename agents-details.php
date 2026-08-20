<?php
require_once __DIR__ . '/includes/auth_session.php';
require_once __DIR__ . '/includes/db_connect.php';
require_role('admin');

$flashMessage = null;
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_agent') {
	$agentId = (int) ($_POST['agent_id'] ?? 0);
	if ($agentId > 0) {
		try {
			$deleteStmt = $conn->prepare('DELETE FROM agents_details WHERE id = :id');
			$deleteStmt->execute([':id' => $agentId]);
			redirect('/agents-details.php?deleted=1');
		} catch (PDOException $e) {
			redirect('/agents-details.php?error=1');
		}
	}
	redirect('/agents-details.php?error=1');
}

if (isset($_GET['deleted'])) {
	$flashMessage = 'Agent deleted successfully.';
	$flashType = 'success';
} elseif (isset($_GET['error'])) {
	$flashMessage = 'Agent could not be deleted. It may be linked to bookings.';
	$flashType = 'danger';
}

$agentSearch = sanitize_input($_GET['q'] ?? '');
$agentStatusFilter = sanitize_input($_GET['status'] ?? '');
$agentFilterClauses = [];
$agentFilterParams = [];

if ($agentSearch !== '') {
	$agentFilterClauses[] = '(name LIKE :search OR phone LIKE :search OR email LIKE :search OR location LIKE :search OR created_by LIKE :search)';
	$agentFilterParams[':search'] = '%' . $agentSearch . '%';
}

if ($agentStatusFilter !== '' && in_array($agentStatusFilter, ['Active', 'On Leave', 'Inactive'], true)) {
	$agentFilterClauses[] = 'status = :status';
	$agentFilterParams[':status'] = $agentStatusFilter;
}

$agentWhereSql = count($agentFilterClauses) > 0 ? ' WHERE ' . implode(' AND ', $agentFilterClauses) : '';

$agentsStmt = $conn->prepare('SELECT * FROM agents_details' . $agentWhereSql . ' ORDER BY created_at DESC, id DESC');
$agentsStmt->execute($agentFilterParams);
$agents = $agentsStmt->fetchAll();

$summaryStmt = $conn->prepare(
	'SELECT
		COUNT(*) AS total_agents,
		SUM(CASE WHEN status = "Active" THEN 1 ELSE 0 END) AS active_agents,
		COALESCE(SUM(total_deals), 0) AS total_deals,
		COALESCE(SUM(total_revenue), 0) AS total_revenue
	 FROM agents_details' . $agentWhereSql
);
$summaryStmt->execute($agentFilterParams);
$summary = $summaryStmt->fetch();

$statusChartStmt = $conn->prepare('SELECT status, COUNT(*) AS total FROM agents_details' . $agentWhereSql . ' GROUP BY status');
$statusChartStmt->execute($agentFilterParams);
$statusLabels = [];
$statusValues = [];
foreach ($statusChartStmt->fetchAll() as $row) {
	$statusLabels[] = $row['status'];
	$statusValues[] = (int) $row['total'];
}

function status_badge_class($status) {
	if ($status === 'Active') {
		return 'text-bg-success';
	}
	if ($status === 'On Leave') {
		return 'text-bg-warning';
	}
	return 'text-bg-secondary';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Agents — Uttarakhand Ventures CRM</title>
	<meta name="description" content="Manage your agent network, view deals and revenue.">
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
		.panel { background:var(--panel); border-radius:18px; border:1px solid var(--border); padding:14px; position:relative; overflow:hidden; }
		.summary-card { border:1px solid var(--border); border-radius:12px; background:#fbfcff; padding:10px; }
		.summary-label { color:var(--text-secondary); font-size:.84rem; }
		.summary-value { font-size:1rem; font-weight:700; }
		.agent-card { border:1px solid var(--border); border-radius:16px; background:#fff; transition:.2s ease; font-size:.92rem; }
		.agent-card:hover { transform:translateY(-4px); box-shadow:0 10px 26px rgba(20,30,70,.08); border-color:var(--primary-200); }
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
		}
		@media (max-width:576px){
			.panel { padding:10px; border-radius:12px; }
			.agent-card { font-size:.86rem; }
			.form-control,.form-select,.btn { font-size:.85rem; }
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
		<li class="nav-item"><a class="nav-link active" href="/agents-details.php"><i class="bi bi-person-badge"></i> Agents</a></li>
		<li class="nav-item"><a class="nav-link" href="/bookingquery.php"><i class="bi bi-chat-dots"></i> Booking Query</a></li>
		<li class="nav-item"><a class="nav-link" href="/query-history.php"><i class="bi bi-clock-history"></i> Query History</a></li>
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
		<div class="d-flex gap-2 flex-wrap align-items-center" style="width:min(760px,100%);">
			<input class="search-bar flex-grow-1" type="text" id="headerSearch" placeholder="Search agent, phone, email, location..." onkeyup="liveSearchAgents(this.value)" />
			<button class="btn btn-outline-secondary" onclick="document.getElementById('headerSearch').value=''; liveSearchAgents('');"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</button>
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
		<?php if ($flashMessage): ?>
			<div class="alert alert-<?php echo htmlspecialchars($flashType, ENT_QUOTES, 'UTF-8'); ?> shadow-sm mb-4"><?php echo htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8'); ?></div>
		<?php endif; ?>

		<div class="panel mb-4">
			<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
				<h4 class="fw-bold mb-0">Agents Command Center</h4>
				<span class="text-muted small">Live roster and revenue snapshot</span>
			</div>
			<div class="row g-3">
				<div class="col-md-3"><div class="summary-card"><div class="summary-label">Total Agents</div><div class="summary-value"><?php echo number_format((int) $summary['total_agents']); ?></div></div></div>
				<div class="col-md-3"><div class="summary-card"><div class="summary-label">Active Agents</div><div class="summary-value text-success"><?php echo number_format((int) $summary['active_agents']); ?></div></div></div>
				<div class="col-md-3"><div class="summary-card"><div class="summary-label">Total Deals</div><div class="summary-value"><?php echo number_format((int) $summary['total_deals']); ?></div></div></div>
				<div class="col-md-3"><div class="summary-card"><div class="summary-label">Total Revenue</div><div class="summary-value">₹<?php echo number_format((float) $summary['total_revenue'], 0); ?></div></div></div>
			</div>
		</div>

		<div class="panel mb-4">
			<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
				<h6 class="fw-bold mb-0">Search & Filters</h6>
				<span class="text-muted small">Find agents by name, mobile number or location</span>
			</div>
			<div class="row g-3 filter-grid">
				<div class="col-lg-7 col-md-6"><input class="form-control" id="agentFilterQ" placeholder="Agent name, phone, email, location" onkeyup="agentLiveFilter()"></div>
				<div class="col-lg-3 col-md-4"><select class="form-select" id="agentFilterStatus" onchange="agentLiveFilter()"><option value="">All Status</option><option value="Active">Active</option><option value="On Leave">On Leave</option><option value="Inactive">Inactive</option></select></div>
				<div class="col-lg-2 col-md-2 d-flex gap-2">
					<button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('agentFilterQ').value=''; document.getElementById('agentFilterStatus').value=''; agentLiveFilter();"><i class="bi bi-x-circle"></i> Reset</button>
				</div>
			</div>
		</div>

		<div class="row g-4">
			<div class="col-xl-9">
				<div class="row g-4">
					<?php if (count($agents) === 0): ?>
						<div class="col-12"><div class="panel text-muted">No agents found.</div></div>
					<?php else: ?>
						<?php foreach ($agents as $agent): ?>
							<div class="col-xl-4 col-md-6">
								<div class="agent-card p-4 h-100">
									<div class="text-center mb-2">
										<img src="/assets/images/agent-avatar.svg" class="rounded-circle" width="76" height="76" alt="Agent avatar">
									</div>
									<h6 class="fw-bold mb-1 text-center"><?php echo htmlspecialchars($agent['name'], ENT_QUOTES, 'UTF-8'); ?></h6>
									<p class="text-muted small mb-1 text-center"><i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($agent['location'], ENT_QUOTES, 'UTF-8'); ?></p>
									<p class="text-muted small mb-2 text-center"><i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($agent['phone'], ENT_QUOTES, 'UTF-8'); ?></p>
									<p class="text-muted small mb-2 text-center"><i class="bi bi-receipt me-1"></i>GST: <?php echo htmlspecialchars($agent['gst_number'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></p>
									<div class="mb-3 d-flex gap-2 justify-content-center flex-wrap">
										<span class="badge <?php echo status_badge_class($agent['status']); ?>"><?php echo htmlspecialchars($agent['status'], ENT_QUOTES, 'UTF-8'); ?></span>
										<span class="badge badge-created"><i class="bi bi-person-fill me-1"></i><?php echo htmlspecialchars($agent['created_by'], ENT_QUOTES, 'UTF-8'); ?></span>
									</div>
									<div class="row g-2 small text-center mb-2">
										<div class="col-6"><div class="text-muted">Deals</div><strong><?php echo number_format((int) $agent['total_deals']); ?></strong></div>
										<div class="col-6"><div class="text-muted">Revenue</div><strong>₹<?php echo number_format((float) $agent['total_revenue'], 0); ?></strong></div>
									</div>
									<p class="mb-0 text-muted small text-center"><?php echo htmlspecialchars($agent['email'], ENT_QUOTES, 'UTF-8'); ?></p>
									<div class="mt-3 text-center">
										<a class="btn btn-sm btn-outline-success rounded-pill px-3" href="/export-agent-excel.php?agent_id=<?php echo (int) $agent['id']; ?>">
											<i class="bi bi-file-earmark-spreadsheet me-1"></i> Download Full Data
										</a>
									</div>
									<form method="post" class="mt-3 text-center" onsubmit="return confirmDeleteAgent('<?php echo htmlspecialchars(addslashes($agent['name']), ENT_QUOTES, 'UTF-8'); ?>');">
										<input type="hidden" name="action" value="delete_agent">
										<input type="hidden" name="agent_id" value="<?php echo (int) $agent['id']; ?>">
										<button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3"><i class="bi bi-trash me-1"></i> Delete</button>
									</form>
								</div>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>

			<div class="col-xl-3">
				<div class="panel h-100">
					<h6 class="fw-bold mb-3">Agent Status Split</h6>
					<canvas id="agentStatusChart" height="220"></canvas>
				</div>
			</div>
		</div>
	</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="/assets/js/ui-common.js"></script>
<script>
function liveSearchAgents(q) {
	q = q.toLowerCase().trim();
	document.getElementById('agentFilterQ').value = q;
	agentLiveFilter();
}

function agentLiveFilter() {
	var q = (document.getElementById('agentFilterQ').value || '').toLowerCase().trim();
	var status = (document.getElementById('agentFilterStatus').value || '').trim();
	document.querySelectorAll('.agent-card').forEach(card => {
		var col = card.closest('.col-xl-4, .col-md-6, .col-12');
		if (!col) return;
		var text = card.textContent.toLowerCase();
		var cardStatus = (card.querySelector('.badge') || {}).textContent || '';
		var matchQ = !q || text.includes(q);
		var matchStatus = !status || cardStatus.includes(status);
		col.style.display = (matchQ && matchStatus) ? '' : 'none';
	});
}

function confirmDeleteAgent(agentName) {
	return confirm('Are you sure you want to delete agent "' + agentName + '"? This action cannot be undone.');
}

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

const statusLabels = <?php echo json_encode($statusLabels); ?>;
const statusValues = <?php echo json_encode($statusValues); ?>;
if (document.getElementById('agentStatusChart')) {
	new Chart(document.getElementById('agentStatusChart').getContext('2d'), {
		type: 'doughnut',
		data: {
			labels: statusLabels,
			datasets: [{
				data: statusValues,
				backgroundColor: ['#10b981', '#f59e0b', '#4f46e5', '#94a3b8'],
				borderWidth: 0
			}]
		},
		options: { plugins: { legend: { position: 'bottom' } }, cutout: '66%' }
	});
}
</script>
</body>
</html>
