<?php
require_once __DIR__ . '/includes/auth_session.php';
require_once __DIR__ . '/includes/db_connect.php';
require_role('admin');

$flashMessage = '';
$flashType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'unlock_query') {
    $queryId = (int) ($_POST['query_id'] ?? 0);
    if ($queryId > 0) {
        try {
            $historyStmt = $conn->prepare('SELECT agent_id FROM booking_query_history WHERE id = :id LIMIT 1');
            $historyStmt->execute([':id' => $queryId]);
            $historyAgentId = (int) ($historyStmt->fetchColumn() ?: 0);
            if ($historyAgentId > 0) {
                $unlockStmt = $conn->prepare('UPDATE agent_query_locks SET lock_until = NOW(), status = "Open" WHERE agent_id = :agent_id AND status = "Locked"');
                $unlockStmt->execute([':agent_id' => $historyAgentId]);
                $historyUnlockStmt = $conn->prepare('UPDATE booking_query_history SET lock_until = NOW() WHERE id = :id');
                $historyUnlockStmt->execute([':id' => $queryId]);
            } else {
                $unlockStmt = $conn->prepare('UPDATE agent_query_locks SET lock_until = NOW(), status = "Open" WHERE id = :id');
                $unlockStmt->execute([':id' => $queryId]);
            }
            $flashMessage = 'Query lock unlocked successfully.';
            $flashType = 'success';
        } catch (PDOException $e) {
            $flashMessage = 'Unable to unlock the query. Please try again.';
            $flashType = 'danger';
        }
    } else {
        $flashMessage = 'Invalid query selected for unlock.';
        $flashType = 'danger';
    }
}

if (isset($_GET['unlocked'])) {
    $flashMessage = 'Query unlocked successfully.';
    $flashType = 'success';
}

$listingsStmt = $conn->prepare("SELECT id, hotel_code, name AS hotel_name, city AS location, state, status, star_rating, property_category FROM hotels WHERE status = 'active' ORDER BY name ASC, created_at DESC");
$listingsStmt->execute();
$listings = $listingsStmt->fetchAll(PDO::FETCH_ASSOC);

// Full category list (not just categories already in use) + distinct active hotel cities for the location suggestion box.
$hotel_category_options = array_values(array_unique(array_merge(
    hotel_category_options(),
    array_filter(array_map(static function ($row) {
        return trim((string)($row['property_category'] ?? ''));
    }, $listings))
)));
$hotel_locations = array_values(array_unique(array_filter(array_map(static function ($row) {
    return trim((string)($row['location'] ?? ''));
}, $listings))));
sort($hotel_locations);

$listingRoomMap = [];
if (count($listings) > 0) {
    $listingIds = array_map(static fn($row) => (int) $row['id'], $listings);
    $placeholders = implode(',', array_fill(0, count($listingIds), '?'));
    $roomStmt = $conn->prepare(
        'SELECT hrc.id, hrc.hotel_id, hrc.name AS category_name,
                COALESCE(MAX(CASE WHEN mp.code = "EP"  THEN rp.base_price END), 0) AS weekday_price,
                COALESCE(MAX(CASE WHEN mp.code = "CP"  THEN rp.base_price END), 0) AS cpai_price,
                COALESCE(MAX(CASE WHEN mp.code = "MAP" THEN rp.base_price END), 0) AS mapai_price,
                COALESCE(MAX(CASE WHEN mp.code = "AP"  THEN rp.base_price END), 0) AS weekday_apai
         FROM hotel_room_categories hrc
         LEFT JOIN room_prices rp ON rp.room_category_id = hrc.id AND rp.rate_date IS NULL
         LEFT JOIN meal_plans mp ON mp.id = rp.meal_plan_id
         WHERE hrc.hotel_id IN (' . $placeholders . ') AND hrc.status = "active"
         GROUP BY hrc.id
         ORDER BY hrc.id ASC'
    );
    $roomStmt->execute($listingIds);
    foreach ($roomStmt->fetchAll() as $room) {
        $listingId = (int) $room['hotel_id'];
        if (!isset($listingRoomMap[$listingId])) {
            $listingRoomMap[$listingId] = [];
        }
        $listingRoomMap[$listingId][] = $room;
    }
}

$listingDataForJs = [];
foreach ($listings as $listing) {
    $id = (int) $listing['id'];
    $dbRooms = $listingRoomMap[$id] ?? [];
    $rooms = array_map(static function ($room) {
        return [
            'category_name' => (string) ($room['category_name'] ?? ''),
            'weekday_price' => (float) ($room['weekday_price'] ?? 0),
            'weekend_price' => (float) ($room['weekday_price'] ?? 0),
            'weekday_cpai' => (float) ($room['cpai_price'] ?? 0),
            'weekday_mapai' => (float) ($room['mapai_price'] ?? 0),
            'weekday_apai' => (float) ($room['weekday_apai'] ?? 0),
            'cpai_price' => (float) ($room['cpai_price'] ?? 0),
            'mapai_price' => (float) ($room['mapai_price'] ?? 0),
            'gst' => 0,
            'child_no_bed_cp' => 0,
            'child_with_bed_cp' => 0,
            'extra_person_with_bed' => 0,
            'extra_person_without_bed' => 0,
        ];
    }, $dbRooms);

    $listingDataForJs[$id] = [
        'id' => $id,
        'hotel_code' => (string) ($listing['hotel_code'] ?? ''),
        'hotel_name' => (string) ($listing['hotel_name'] ?? ''),
        'category' => ((int)($listing['star_rating'] ?? 0)) > 0 ? (((int)$listing['star_rating']) . ' Star') : '',
        'location' => trim(((string)($listing['location'] ?? '')) . (((string)($listing['state'] ?? '')) !== '' ? (', ' . (string)$listing['state']) : '')),
        'status' => (string) ($listing['status'] ?? 'active'),
        'room_type' => '',
        'rooms' => $rooms,
    ];
}

// --- Admin Query History (show all employees + generated query records) ---
try {
    $historyStmt = $conn->prepare("SELECT bqh.id, bqh.created_by_user_id, bqh.created_by_username, bqh.created_by_role, bqh.generated_at, bqh.query_text,
                                          bqh.query_type, bqh.agent_id, bqh.agent_name, bqh.agent_phone, bqh.lock_until,
                                          bqh.location, bqh.hotel_category, bqh.check_in, bqh.check_out,
                                          bqh.nights, bqh.adults, bqh.children, bqh.rooms, bqh.budget, bqh.matched_hotels_json,
                                          COALESCE(NULLIF(bqh.created_by_username, ''), 'Unknown') AS employee_name
                                          FROM booking_query_history bqh
                                          ORDER BY bqh.generated_at DESC LIMIT 200");
    $historyStmt->execute();
    $admin_history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$admin_history) {
        $legacyStmt = $conn->prepare("SELECT aql.id, aql.agent_id, aql.employee_username, aql.created_by_user_id, aql.created_by_role, COALESCE(u.username, aql.employee_username) AS created_by_username, aql.generated_at, aql.lock_until, aql.query_text,
                                              aql.hotel_name, aql.room_category, aql.check_in, aql.check_out, aql.adults, aql.children, aql.rooms, aql.total_amount, aql.status, aql.booking_status,
                                              ad.name AS agent_name, ad.phone AS agent_phone FROM agent_query_locks aql
                                              JOIN agents_details ad ON aql.agent_id = ad.id
                                              LEFT JOIN users u ON u.id = aql.created_by_user_id
                                              ORDER BY aql.generated_at DESC LIMIT 200");
        $legacyStmt->execute();
        $admin_history = $legacyStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $admin_history = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Query — Uttarakhand Ventures CRM</title>
    <meta name="description" content="Generate and manage hotel booking queries for agents.">
    <link rel="icon" type="image/svg+xml" href="/assets/images/favicon.svg">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/sidebar.css">
    <link rel="stylesheet" href="/assets/css/ui-modern.css">
    <style>
        :root { --bg:#f8fafc; --panel:#fff; --nav:#0f172a; --muted:#94a3b8; --brand:#4f46e5; --accent:#06b6d4; --text:#0f172a; --text-secondary:#475569; --border:#e2e8f0; --primary-50:#eef2ff; --primary-200:#c7d2fe; }
        body { background:var(--bg); color:var(--text); font-family:'Inter','Segoe UI',system-ui,sans-serif; font-size:13px; }

        /* ── Main wrapper ── */
        .main-wrapper { margin-left: 232px; padding: 24px; min-height: 100vh; }

        /* ── Top header ── */
        .top-header {
            background: rgba(255,255,255,.95);
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            border-bottom: 1px solid var(--border);
            margin: -24px -24px 24px -24px;
            position: sticky;
            top: 0;
            z-index: 20;
            backdrop-filter: blur(10px);
        }
        .mobile-menu-btn { display: none; }

        /* ── Panel ── */
        .panel {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 24px;
        }

        /* ── Responsive ── */
        @media (max-width: 992px) {
            .main-wrapper { margin-left: 0; }
            .mobile-menu-btn { display: inline-flex; }
        }

        @media (max-width: 576px) {
            .panel { padding: 14px; border-radius: 12px; }
            .top-header { padding: 10px 14px; }
        }
    </style>
</head>
<body>

<!-- ══════════ SIDEBAR ══════════ -->
<div class="sidebar" id="adminSidebar">
    <div class="sidebar-brand">
        <span class="d-flex align-items-center gap-2">
            <span class="brand-icon"><i class="bi bi-buildings"></i></span>
            Uttarakhand Ventures
        </span>
        <button type="button" class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close menu">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <ul class="nav flex-column">
        <li class="nav-item"><a class="nav-link" href="/dashboard.php"><i class="bi bi-grid-1x2"></i> Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="/agents-details.php"><i class="bi bi-person-badge"></i> Agents</a></li>
        <li class="nav-item"><a class="nav-link active" href="/bookingquery.php"><i class="bi bi-chat-dots"></i> Booking Query</a></li>
        <li class="nav-item"><a class="nav-link" href="/query-history.php"><i class="bi bi-clock-history"></i> Query History</a></li>
        <li class="nav-item"><a class="nav-link" href="/listing.php"><i class="bi bi-building"></i> Hotel Listings</a></li>
        <li class="nav-item"><a class="nav-link" href="/employees-detail.php"><i class="bi bi-person-vcard"></i> Employees</a></li>
        <li class="nav-item"><a class="nav-link" href="/accounts-detail.php"><i class="bi bi-wallet2"></i> Accounts</a></li>
        <li class="nav-item"><a class="nav-link" href="/booking-details.php"><i class="bi bi-calendar-check"></i> Bookings</a></li>
    </ul>
</div>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<!-- ══════════ MAIN ══════════ -->
<div class="main-wrapper">

    <header class="top-header">
        <button class="btn btn-light mobile-menu-btn" type="button" id="mobileMenuBtn" aria-label="Open menu">
            <i class="bi bi-list fs-4"></i>
        </button>
        <div>
            <h5 class="mb-0 fw-bold">Admin Booking Query</h5>
            <p class="text-muted mb-0 small">Use hotel listings and room categories to generate booking queries for agents.</p>
        </div>
        <div class="dropdown user-menu-corner ms-auto">
            <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8'); ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="/dashboard.php"><i class="bi bi-person-circle me-2"></i> Profile</a></li>
                <li><a class="dropdown-item" href="/booking-details.php"><i class="bi bi-clock-history me-2"></i> Booking History</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
            </ul>
        </div>
    </header>

    <?php if ($flashMessage !== ''): ?>
        <div class="alert alert-<?php echo $flashType === 'danger' ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="/bookingquery.php" class="btn btn-primary fw-semibold px-3 py-2">
            <i class="bi bi-chat-dots me-2"></i>Booking Query
        </a>
        <a href="/query-history.php" class="btn btn-outline-primary fw-semibold px-3 py-2">
            <i class="bi bi-clock-history me-2"></i>Query History
        </a>
    </div>

    <div class="panel">
        <div class="mb-4">
            <h4 class="fw-bold text-dark mb-1">Booking Query Details</h4>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold text-secondary d-block">Query Type</label>
            <div class="d-flex gap-4">
                <label class="form-check"><input class="form-check-input" type="radio" name="adminBookingQueryType" value="admin" checked onchange="setAdminBookingQueryType(this.value)"> Admin</label>
                <label class="form-check"><input class="form-check-input" type="radio" name="adminBookingQueryType" value="agent" onchange="setAdminBookingQueryType(this.value)"> Agent</label>
            </div>
        </div>
        <div id="adminBookingQueryAgentBox" class="border rounded p-3 mb-3" style="display:none;">
            <label for="adminBookingQueryAgentPhone" class="form-label small fw-semibold text-secondary">Agent Mobile Number</label>
            <div class="input-group">
                <input type="tel" class="form-control" id="adminBookingQueryAgentPhone" maxlength="20" placeholder="Enter registered agent mobile number" oninput="lookupAdminBookingQueryAgent()">
                <button type="button" class="btn btn-outline-primary" onclick="lookupAdminBookingQueryAgent()">Fetch Agent</button>
            </div>
            <div id="adminBookingQueryAgentStatus" class="small text-muted mt-2">Enter agent mobile number.</div>
        </div>

        <fieldset id="adminBookingQueryDetailsFields" disabled>
        <div class="row g-3">
            <div class="col-md-6">
                <label for="adminQueryLocation" class="form-label small fw-semibold text-secondary">Location</label>
                <input type="text" class="form-control form-control-sm query-required-field" id="adminQueryLocation" list="hotelCityList" placeholder="Type a city, e.g. gur..." autocomplete="off" required>
                <datalist id="hotelCityList">
                    <?php foreach ($hotel_locations as $cityOption): ?>
                        <option value="<?php echo htmlspecialchars($cityOption, ENT_QUOTES, 'UTF-8'); ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="col-md-6">
                <label for="adminQueryCategory" class="form-label small fw-semibold text-secondary">Hotel Category</label>
                <select class="form-select form-select-sm query-required-field" id="adminQueryCategory" required>
                    <option value="all categories" selected>All Categories</option>
                    <?php foreach ($hotel_category_options as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label for="adminQueryCheckIn" class="form-label small fw-semibold text-secondary">Check-In</label>
                <input type="date" class="form-control form-control-sm query-required-field" id="adminQueryCheckIn" required>
            </div>
            <div class="col-md-4">
                <label for="adminQueryCheckOut" class="form-label small fw-semibold text-secondary">Check-out</label>
                <input type="date" class="form-control form-control-sm query-required-field" id="adminQueryCheckOut" required>
            </div>
            <div class="col-md-4">
                <label for="adminQueryNights" class="form-label small fw-semibold text-secondary">Nights</label>
                <input type="number" class="form-control form-control-sm" id="adminQueryNights" min="0" value="0" readonly>
            </div>

            <div class="col-md-3">
                <label for="adminQueryAdults" class="form-label small fw-semibold text-secondary">Adults</label>
                <input type="number" class="form-control form-control-sm query-required-field" id="adminQueryAdults" min="1" value="2" required>
            </div>
            <div class="col-md-3">
                <label for="adminQueryChildren" class="form-label small fw-semibold text-secondary">Children</label>
                <input type="number" class="form-control form-control-sm query-required-field" id="adminQueryChildren" min="0" value="0" required>
            </div>
            <div class="col-md-3">
                <label for="adminQueryRooms" class="form-label small fw-semibold text-secondary">Rooms</label>
                <input type="number" class="form-control form-control-sm query-required-field" id="adminQueryRooms" min="1" value="1" required>
            </div>
            <div class="col-md-3">
                <label for="adminQueryBudget" class="form-label small fw-semibold text-secondary">Budget</label>
                <input type="number" class="form-control form-control-sm" id="adminQueryBudget" min="0" step="100" placeholder="e.g. 5000">
            </div>
        </div>

        <div class="mt-4">
            <button type="button" class="btn btn-primary w-100 py-2 fw-semibold" onclick="generateAdminBookingQueryResults()">
                <i class="bi bi-magic me-2"></i>Generate Query
            </button>
        </div>
        </fieldset>
    </div>

    <div id="adminQueryResultsWrap" class="panel mt-4" style="display: none;">
        <div class="d-flex flex-wrap gap-2 mb-3 align-items-center justify-content-between">
            <h5 class="mb-0 fw-bold">Generated Results</h5>
            <input type="search" class="form-control form-control-sm" id="adminQueryHotelSearch" placeholder="Search hotel name..." oninput="filterAdminQueryResults()" style="max-width:240px;">
            <div>
                <button type="button" class="btn btn-sm btn-outline-secondary me-2" onclick="selectAdminQueryRows(5)">Top 5</button>
                <button type="button" class="btn btn-sm btn-outline-secondary me-2" onclick="selectAdminQueryRows(10)">Top 10</button>
                <button type="button" class="btn btn-sm btn-outline-primary me-2" onclick="selectAdminQueryRows('all')">Select All</button>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearAdminQueryRows()">Clear Selection</button>
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
                <tbody id="adminQueryResultsBody"></tbody>
            </table>
        </div>

        <div class="mt-3">
            <button type="button" class="btn btn-success" onclick="sendSelectedAdminQueryQuotes()">Copy</button>
        </div>
    </div>

</div><!-- /.main-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/quotation-template.js?v=20260826-1"></script>
<script src="/assets/js/ui-common.js"></script>
<script>
const listingPayload = <?php echo json_encode($listingDataForJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
let listingSearchDebounceTimer = null;
let adminBookingQueryType = 'admin';
let adminBookingQueryAgent = null;

function setAdminBookingQueryType(type) {
    adminBookingQueryType = type === 'agent' ? 'agent' : 'admin';
    const agentBox = document.getElementById('adminBookingQueryAgentBox');
    const form = document.getElementById('adminBookingQueryDetailsFields');
    if (agentBox) agentBox.style.display = adminBookingQueryType === 'agent' ? 'block' : 'none';
    if (form) form.disabled = adminBookingQueryType === 'agent' && !adminBookingQueryAgent;
    if (adminBookingQueryType === 'admin') {
        adminBookingQueryAgent = null;
        const status = document.getElementById('adminBookingQueryAgentStatus');
        if (status) status.textContent = 'Select Agent type to search an agent.';
    }
}

function lookupAdminBookingQueryAgent() {
    const phone = document.getElementById('adminBookingQueryAgentPhone')?.value.trim() || '';
    const status = document.getElementById('adminBookingQueryAgentStatus');
    if (adminBookingQueryAgent && adminBookingQueryAgent.phone !== phone) adminBookingQueryAgent = null;
    const form = document.getElementById('adminBookingQueryDetailsFields');
    if (!adminBookingQueryAgent && form) form.disabled = true;
    if (!phone) {
        adminBookingQueryAgent = null;
        if (status) status.textContent = 'Enter agent mobile number.';
        return;
    }
    if (status) status.textContent = 'Searching agent...';
    fetch('employee-dashboard.php', { method: 'POST', body: new URLSearchParams({ action: 'search_agent_by_mobile', mobileNumber: phone }) })
        .then((response) => response.json())
        .then((data) => {
            if (!data.success || !data.found) {
                adminBookingQueryAgent = null;
                if (form) form.disabled = true;
                if (status) status.textContent = data.message || 'Agent mobile number is not registered.';
                if (status) status.className = data.locked ? 'small text-danger mt-2' : 'small text-muted mt-2';
                return;
            }
            adminBookingQueryAgent = data.agent;
            if (form) form.disabled = false;
            if (status) status.className = 'small text-success mt-2';
            if (status) status.textContent = `${data.agent.name} | ${data.agent.phone} | GSTIN: ${data.agent.gst_number || 'N/A'} | ${data.agent.location || 'Location unavailable'} | ${data.agent.company_name || ''} | ${data.agent.email || ''}`;
        })
        .catch(() => {
            adminBookingQueryAgent = null;
            if (status) status.textContent = 'Unable to fetch agent details.';
        });
}

setAdminBookingQueryType('admin');

function escapeAdminHistoryHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
}

function formatAdminBookingMealPlans(prices) {
    const labels = { EP: 'EP - Room Only', CP: 'CP - Breakfast Included', MAP: 'MAP - Breakfast + Dinner', AP: 'AP - All Meals' };
    return Object.entries(prices || {}).map(([code, price]) => `${labels[code] || code} (₹${Number(price || 0).toLocaleString('en-IN')}/night)`).join(', ') || 'EP - Room Only';
}

function formatAdminHistoryDate(value) {
    if (!value) return 'N/A';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? 'N/A' : date.toLocaleString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true });
}

function loadAdminGeneratedQueryHistory() {
    fetch('employee-dashboard.php', {
        method: 'POST',
        body: new URLSearchParams({ action: 'get_booking_query_history' })
    })
    .then((response) => response.json())
    .then((data) => {
        const records = Array.isArray(data?.history) ? data.history : [];
        const container = document.getElementById('adminGeneratedQueryHistory');
        if (!container) return;
        container.innerHTML = records.length ? `<div class="table-responsive"><table class="table table-sm table-hover align-middle admin-generated-history-table">
            <thead class="table-light"><tr><th>Created By</th><th>Agent</th><th>Agent Phone</th><th>Location</th><th>Category</th><th>Hotel / Room</th><th>Meal</th><th>Dates</th><th>Budget</th><th>Lock Status</th><th>Lock Until</th><th>Generated At</th><th>Action</th></tr></thead><tbody>
            ${records.map((item) => {
                const hotels = Array.isArray(item.matched_hotels) ? item.matched_hotels : [];
                const hotelNames = hotels.map((hotel) => `${escapeAdminHistoryHtml(hotel.name)} / ${escapeAdminHistoryHtml(hotel.room_name)}`).join('<br>') || 'No matches';
                const meals = hotels.map((hotel) => formatAdminBookingMealPlans(hotel.prices)).join('<br>') || 'N/A';
                return `<tr class="admin-history-row" data-history-date="${escapeAdminHistoryHtml(item.generated_at)}" data-history-text="${escapeAdminHistoryHtml((item.query_text || '').toLowerCase())}">
                    <td>${escapeAdminHistoryHtml(item.created_by_username)}</td><td>${escapeAdminHistoryHtml(item.agent_name || 'Admin')}</td><td>${escapeAdminHistoryHtml(item.agent_phone || '')}</td><td>${escapeAdminHistoryHtml(item.location || 'Any')}</td>
                    <td>${escapeAdminHistoryHtml(item.hotel_category || 'All Categories')}</td><td>${hotelNames}</td><td>${meals}</td>
                    <td>${escapeAdminHistoryHtml(item.check_in || 'N/A')} - ${escapeAdminHistoryHtml(item.check_out || 'N/A')}</td>
                    <td>₹${Number(item.budget || 0).toLocaleString('en-IN')}/night</td><td>${item.lock_until && new Date(item.lock_until).getTime() > Date.now() ? '<span class="badge bg-danger">Agent Locked</span>' : '<span class="badge bg-success">Unlocked</span>'}</td><td>${escapeAdminHistoryHtml(item.lock_until && new Date(item.lock_until).getTime() > Date.now() ? formatAdminHistoryDate(item.lock_until) : 'Unlocked')}</td><td>${escapeAdminHistoryHtml(formatAdminHistoryDate(item.generated_at))}</td>
                    <td><button type="button" class="btn btn-sm btn-outline-secondary" data-query-text="${escapeAdminHistoryHtml(item.query_text)}" data-quotation="${escapeAdminHistoryHtml(JSON.stringify({ queryNumber: item.query_number, queryText: item.query_text, hotelName: item.hotel_name, hotelLocation: item.location, roomCategory: item.room_category, checkIn: item.check_in, checkOut: item.check_out, adults: item.adults, children: item.children, rooms: item.rooms, roomPrice: null, agentName: item.agent_name, agentPhone: item.agent_phone, matchedHotels: hotels }))}" onclick="copyAdminHistoryQuotation(this)">Copy</button></td>
                </tr>`;
            }).join('')}</tbody></table></div>` : '<div class="text-center py-3 text-muted">No generated Booking Query history found.</div>';
        applyAdminQueryHistoryFilter('all');
    })
    .catch(() => {});
}

function copyAdminHistoryQuotation(button) {
    const quotation = button?.dataset.quotation ? JSON.parse(button.dataset.quotation) : null;
    copyQueryText(quotation ? AirwaysQuotation.format(quotation) : AirwaysQuotation.plainText(button?.dataset.queryText || ''));
}

function applyAdminQueryHistoryFilter(filter) {
    const now = new Date();
    const dayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const weekStart = new Date(dayStart);
    weekStart.setDate(dayStart.getDate() - dayStart.getDay());
    const monthStart = new Date(now.getFullYear(), now.getMonth(), 1);
    const search = (document.getElementById('adminQueryHistorySearch')?.value || '').toLowerCase().trim();
    document.querySelectorAll('.admin-history-row').forEach((row) => {
        const date = new Date(row.dataset.historyDate);
        const dateMatch = filter === 'today' ? date >= dayStart : filter === 'week' ? date >= weekStart : filter === 'month' ? date >= monthStart : true;
        row.style.display = dateMatch && (!search || row.dataset.historyText.includes(search) || row.textContent.toLowerCase().includes(search)) ? '' : 'none';
    });
    document.querySelectorAll('.admin-query-history-filter').forEach((button) => {
        const active = button.dataset.historyFilter === filter;
        button.classList.toggle('active', active);
        button.classList.toggle('btn-primary', active);
        button.classList.toggle('btn-outline-primary', !active);
    });
}

document.addEventListener('click', (event) => {
    const button = event.target.closest('.admin-query-history-filter');
    if (button) applyAdminQueryHistoryFilter(button.dataset.historyFilter || 'all');
});
document.getElementById('adminQueryHistorySearch')?.addEventListener('input', () => applyAdminQueryHistoryFilter('all'));
loadAdminGeneratedQueryHistory();

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

const resultsDataStore = {};

document.querySelectorAll('.query-required-field').forEach((field) => {
    const clearInvalid = () => field.classList.toggle('is-invalid', String(field.value).trim() === '');
    field.addEventListener('input', clearInvalid);
    field.addEventListener('change', clearInvalid);
});

function generateBookingResultsFromInputs(locationId, categoryId, checkInId, checkOutId, nightsId, adultsId, childrenId, roomsId, budgetId, resultWrapId, resultBodyId) {
    const requiredFields = [
        [locationId, 'Location'], [categoryId, 'Hotel Category'], [checkInId, 'Check-In'],
        [checkOutId, 'Check-out'], [adultsId, 'Adults'], [childrenId, 'Children'], [roomsId, 'Rooms']
    ];
    for (const [id, label] of requiredFields) {
        const field = document.getElementById(id);
        field?.classList.toggle('is-invalid', !field || String(field.value).trim() === '');
        if (!field || String(field.value).trim() === '') {
            alert(`${label} is required.`);
            field?.focus();
            return;
        }
    }
    if (resultBodyId === 'adminQueryResultsBody' && adminBookingQueryType === 'agent' && !adminBookingQueryAgent) {
        alert('Please enter and verify a registered agent mobile number first');
        return;
    }
    const location = document.getElementById(locationId)?.value.trim() || '';
    const category = document.getElementById(categoryId)?.value || '';
    const checkIn = document.getElementById(checkInId)?.value || '';
    const checkOut = document.getElementById(checkOutId)?.value || '';
    const nights = Number(document.getElementById(nightsId)?.value || 0);
    const adults = Number(document.getElementById(adultsId)?.value || 1);
    const children = Number(document.getElementById(childrenId)?.value || 0);
    const rooms = Number(document.getElementById(roomsId)?.value || 1);
    const budget = Number(document.getElementById(budgetId)?.value || 0);
    const resultWrap = document.getElementById(resultWrapId);
    const resultBody = document.getElementById(resultBodyId);
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

    fetch('employee-dashboard.php', {
        method: 'POST',
        body: formData
    })
    .then((response) => response.json())
    .then((data) => {
        const results = Array.isArray(data?.results) ? data.results : [];
        resultsDataStore[resultBodyId] = results;

        if (budget > 0) {
            results.sort((a, b) => Math.abs((a.est_budget || a.min_price || 0) - budget) - Math.abs((b.est_budget || b.min_price || 0) - budget));
        } else {
            results.sort((a, b) => (a.total_min || 0) - (b.total_min || 0));
        }

        resultBody.innerHTML = results.length
            ? results.flatMap((hotel) => (hotel.rooms || []).map((room) => {
                const mealPlans = formatAdminBookingMealPlans(room.prices);
                const nightlyPrice = Number(room.prices?.EP || hotel.est_budget || hotel.min_price || 0);
                const roomIndex = hotel.rooms.indexOf(room);
                const selectionKey = `${hotel.id}::${roomIndex}`;
                return `
                <tr data-hotel-name="${String(hotel.name || '').toLowerCase()}">
                    <td><input class="form-check-input hotel-checkbox" type="checkbox" value="${selectionKey}" id="${resultBodyId}_${hotel.id}_${roomIndex}"></td>
                    <td><label for="${resultBodyId}_${hotel.id}_${roomIndex}">${hotel.name}</label></td>
                    <td>${room.name || 'N/A'}</td>
                    <td>${mealPlans}</td>
                    <td>${hotel.location || hotel.city || 'N/A'}</td>
                    <td>₹${nightlyPrice.toLocaleString('en-IN')}</td>
                    <td>${checkIn || 'N/A'}</td>
                    <td>${checkOut || 'N/A'}</td>
                </tr>`;
            })).join('')
            : '<tr><td colspan="9" class="text-center text-muted py-4">No active hotels match this location/category/budget.</td></tr>';

        if (adminBookingQueryType === 'agent' && results.length) {
            fetch('employee-dashboard.php', {
                method: 'POST',
                body: new URLSearchParams({ action: 'acquire_booking_query_agent_lock', agent_phone: adminBookingQueryAgent.phone })
            }).then((lockResponse) => lockResponse.json()).then((lockData) => {
                if (!lockData.success) {
                    resultsDataStore[resultBodyId] = [];
                    resultBody.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-4">${lockData.message || 'Agent is currently unavailable.'}</td></tr>`;
                    alert(lockData.message || 'Agent is currently unavailable.');
                }
            }).catch(() => alert('Unable to verify the agent lock. Please try again.'));
        }

    })
    .catch((error) => {
        console.error('Hotel filter error:', error);
        resultsDataStore[resultBodyId] = [];
        resultBody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">Unable to load hotels from database.</td></tr>';
    });
}

function filterAdminQueryResults() {
    const query = (document.getElementById('adminQueryHotelSearch')?.value || '').trim().toLowerCase();
    document.querySelectorAll('#adminQueryResultsBody tr[data-hotel-name]').forEach((row) => {
        row.style.display = !query || row.dataset.hotelName.includes(query) ? '' : 'none';
    });
}

function selectBookingQueryRows(limit) {
    const boxes = [...document.querySelectorAll('.hotel-checkbox')].filter((box) => box.closest('tr')?.style.display !== 'none');
    boxes.forEach((box) => box.checked = false);
    if (limit === 'all') {
        boxes.forEach((box) => box.checked = true);
        return;
    }
    [...boxes].slice(0, Number(limit) || 0).forEach((box) => box.checked = true);
}

function clearBookingQueryRows() {
    document.querySelectorAll('.hotel-checkbox').forEach((box) => box.checked = false);
}

function buildHotelShareText(prefixIds, resultBodyId) {
    const location = document.getElementById(prefixIds.location)?.value.trim() || 'Any';
    const category = document.getElementById(prefixIds.category)?.value || 'Any';
    const checkIn = document.getElementById(prefixIds.checkIn)?.value || 'N/A';
    const checkOut = document.getElementById(prefixIds.checkOut)?.value || 'N/A';
    const nights = document.getElementById(prefixIds.nights)?.value || '0';
    const adults = document.getElementById(prefixIds.adults)?.value || '1';
    const children = document.getElementById(prefixIds.children)?.value || '0';
    const rooms = document.getElementById(prefixIds.rooms)?.value || '1';
    const budget = Number(document.getElementById(prefixIds.budget)?.value || 0);

    const selectedIds = [...document.querySelectorAll(`#${resultBodyId} .hotel-checkbox:checked`)].map((box) => box.value);
    const results = resultsDataStore[resultBodyId] || [];
    const selectedRows = selectedIds.map((key) => {
        const [hotelId, roomIndex] = String(key).split('::');
        const hotel = results.find((item) => String(item.id) === hotelId);
        const room = hotel?.rooms?.[Number(roomIndex)];
        return hotel && room ? { hotel, room } : null;
    }).filter(Boolean);
    return {
        text: AirwaysQuotation.formatMany(selectedRows.map(({ hotel, room }) => ({
            queryNumber: window.adminBookingQueryNumber,
            hotelName: hotel.name, hotelLocation: hotel.location || hotel.city || location,
            roomCategory: room.name || room.category, mealPlan: room.meal_plan || room.mealPlan || Object.keys(room.prices || {})[0],
            checkIn, checkOut, adults, children, rooms,
            roomPrice: room.prices?.EP || hotel.est_budget || hotel.min_price || budget,
            matchedHotels: [{ ...hotel, rooms: [room] }]
        }))),
        count: selectedIds.length
    };
}

function copyAndShareHotelQuotes(prefixIds, resultBodyId) {
    const { text, count } = buildHotelShareText(prefixIds, resultBodyId);
    if (!count) {
        alert('Please select at least one hotel.');
        return;
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => {
            alert(`${count} hotel(s) quotation copied successfully. WhatsApp will not open automatically.`);
        }).catch(() => {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            alert(`${count} hotel(s) quotation copied successfully. WhatsApp will not open automatically.`);
        });
    } else {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        alert(`${count} hotel(s) quotation copied successfully. WhatsApp will not open automatically.`);
    }
}

function sendSelectedBookingQueryQuotes() {
    copyAndShareHotelQuotes({
        location: 'bookingQueryLocation', category: 'bookingQueryHotelCategory',
        checkIn: 'bookingQueryCheckIn', checkOut: 'bookingQueryCheckOut', nights: 'bookingQueryNights',
        adults: 'bookingQueryAdults', children: 'bookingQueryChildren', rooms: 'bookingQueryRooms', budget: 'bookingQueryBudget'
    }, 'bookingQueryResultsBody');
}

function generateAdminBookingQueryResults() {
    generateBookingResultsFromInputs('adminQueryLocation', 'adminQueryCategory', 'adminQueryCheckIn', 'adminQueryCheckOut', 'adminQueryNights', 'adminQueryAdults', 'adminQueryChildren', 'adminQueryRooms', 'adminQueryBudget', 'adminQueryResultsWrap', 'adminQueryResultsBody');
    selectAdminQueryRows(5);
}

function selectAdminQueryRows(limit) {
    const boxes = [...document.querySelectorAll('#adminQueryResultsBody .hotel-checkbox')].filter((box) => box.closest('tr')?.style.display !== 'none');
    boxes.forEach((box) => box.checked = false);
    if (limit === 'all') {
        boxes.forEach((box) => box.checked = true);
        return;
    }
    [...boxes].slice(0, Number(limit) || 0).forEach((box) => box.checked = true);
}

function clearAdminQueryRows() {
    document.querySelectorAll('#adminQueryResultsBody .hotel-checkbox').forEach((box) => box.checked = false);
}

function sendSelectedAdminQueryQuotes() {
    const resultBodyId = 'adminQueryResultsBody';
    const selectedIds = [...document.querySelectorAll(`#${resultBodyId} .hotel-checkbox:checked`)].map((box) => box.value);
    if (!selectedIds.length) {
        alert('Please select at least one hotel.');
        return;
    }

    const prefixIds = {
        location: 'adminQueryLocation', category: 'adminQueryCategory',
        checkIn: 'adminQueryCheckIn', checkOut: 'adminQueryCheckOut', nights: 'adminQueryNights',
        adults: 'adminQueryAdults', children: 'adminQueryChildren', rooms: 'adminQueryRooms', budget: 'adminQueryBudget'
    };
    const results = resultsDataStore[resultBodyId] || [];
    const selectedRows = selectedIds.map((key) => {
        const [hotelId, roomIndex] = String(key).split('::');
        const hotel = results.find((item) => String(item.id) === hotelId);
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
    const params = new URLSearchParams({
        action: 'save_booking_query_history',
        location: document.getElementById(prefixIds.location)?.value.trim() || '',
        category: document.getElementById(prefixIds.category)?.value || '',
        check_in: document.getElementById(prefixIds.checkIn)?.value || '',
        check_out: document.getElementById(prefixIds.checkOut)?.value || '',
        nights: document.getElementById(prefixIds.nights)?.value || '0',
        adults: document.getElementById(prefixIds.adults)?.value || '1',
        children: document.getElementById(prefixIds.children)?.value || '0',
        rooms: document.getElementById(prefixIds.rooms)?.value || '1',
        budget: document.getElementById(prefixIds.budget)?.value || '0',
        query_number: window.adminBookingQueryNumber || AirwaysQuotation.generateQueryNumber(),
        query_type: adminBookingQueryType,
        agent_phone: adminBookingQueryAgent?.phone || '',
        matched_hotels: JSON.stringify(matchedHotels)
    });
    fetch('employee-dashboard.php', { method: 'POST', body: params })
        .then((response) => response.json())
        .then((data) => {
            if (!data.success) throw new Error(data.message || 'Unable to save query history');
            window.adminBookingQueryId = data.id;
            window.adminBookingQueryNumber = data.query_number || window.adminBookingQueryNumber;
            copyAndShareHotelQuotes(prefixIds, resultBodyId);
        })
        .catch((error) => {
            console.error('Query history save error:', error);
            alert('Unable to save query history. Quotes were not sent.');
        });
}

function generateBookingQueryResults() {
    generateBookingResultsFromInputs('bookingQueryLocation', 'bookingQueryHotelCategory', 'bookingQueryCheckIn', 'bookingQueryCheckOut', 'bookingQueryNights', 'bookingQueryAdults', 'bookingQueryChildren', 'bookingQueryRooms', 'bookingQueryBudget', 'bookingQueryResultsWrap', 'bookingQueryResultsBody');
    selectBookingQueryRows(5);
}

const adminCheckIn = document.getElementById('adminQueryCheckIn');
if (adminCheckIn) {
    adminCheckIn.addEventListener('change', () => calculateBookingNights('adminQueryCheckIn', 'adminQueryCheckOut', 'adminQueryNights'));
}
const adminCheckOut = document.getElementById('adminQueryCheckOut');
if (adminCheckOut) {
    adminCheckOut.addEventListener('change', () => calculateBookingNights('adminQueryCheckIn', 'adminQueryCheckOut', 'adminQueryNights'));
}

const employeeCheckIn = document.getElementById('bookingQueryCheckIn');
if (employeeCheckIn) {
    employeeCheckIn.addEventListener('change', () => calculateBookingNights('bookingQueryCheckIn', 'bookingQueryCheckOut', 'bookingQueryNights'));
}
const employeeCheckOut = document.getElementById('bookingQueryCheckOut');
if (employeeCheckOut) {
    employeeCheckOut.addEventListener('change', () => calculateBookingNights('bookingQueryCheckIn', 'bookingQueryCheckOut', 'bookingQueryNights'));
}

function tokenizeSearch(value) {
    return (value || '')
        .toLowerCase()
        .split(/\s+/)
        .map(v => v.trim())
        .filter(Boolean);
}

function scrollToQueryHistory(event) {
    if (event) event.preventDefault();
    const section = document.getElementById('queryHistorySection');
    if (section) {
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    toggleSidebarMenu(false);
}

/* ── Sidebar toggle ── */
function toggleSidebarMenu(open) {
    const sidebar  = document.getElementById('adminSidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    if (!sidebar || !backdrop) return;
    sidebar.classList.toggle('open', !!open);
    backdrop.classList.toggle('show', !!open);
}

(() => {
    const btn      = document.getElementById('mobileMenuBtn');
    const closeBtn = document.getElementById('sidebarCloseBtn');
    const backdrop = document.getElementById('sidebarBackdrop');
    if (btn)      btn.addEventListener('click',      () => toggleSidebarMenu(true));
    if (closeBtn) closeBtn.addEventListener('click', () => toggleSidebarMenu(false));
    if (backdrop) backdrop.addEventListener('click', () => toggleSidebarMenu(false));
    document.querySelectorAll('.sidebar .nav-link').forEach(link =>
        link.addEventListener('click', () => toggleSidebarMenu(false))
    );
})();

/* ── Listing select ── */
function populateAdminListingSelect() {
    populateAdminListingSelectFiltered('');
}

function populateAdminListingSelectFiltered(keyword) {
    const select = document.getElementById('adminQueryListingId');
    if (!select) return;

    const selectedBefore = select.value;
    select.innerHTML = '<option value="">Select property...</option>';
    const tokens = tokenizeSearch(keyword);

    Object.values(listingPayload).filter(listing => {
        if (tokens.length === 0) return true;
        const haystack = getListingSearchText(listing);
        return tokens.every(token => haystack.includes(token));
    }).forEach(listing => {
        const option = document.createElement('option');
        option.value = String(listing.id);
        const loc = listing.location ? `, ${listing.location}` : '';
        const cat = listing.category ? ` [${listing.category}]` : '';
        const code = listing.hotel_code ? ` (${listing.hotel_code})` : '';
        option.textContent = (listing.hotel_name + loc + cat + code) || `Property ${listing.id}`;
        select.appendChild(option);
    });

    if (selectedBefore && Array.from(select.options).some(o => o.value === selectedBefore)) {
        select.value = selectedBefore;
    }
}

function handleAdminListingSearch(value) {
    if (listingSearchDebounceTimer) {
        clearTimeout(listingSearchDebounceTimer);
    }
    listingSearchDebounceTimer = setTimeout(() => {
        populateAdminListingSelectFiltered(value);
    }, 300);
}

/* ── Room categories ── */
function populateAdminRoomCategories(listingId) {
    const categorySelect = document.getElementById('adminQueryRoomCategory');
    const listing = listingPayload[String(listingId)] || listingPayload[listingId];
    if (!categorySelect) return;
    categorySelect.innerHTML = '<option value="">Select category...</option>';
    if (!listing || !Array.isArray(listing.rooms)) return;
    listing.rooms.forEach((room, index) => {
        const option = document.createElement('option');
        option.value = index;
        option.textContent = room.category_name || room.category || `Category ${index + 1}`;
        categorySelect.appendChild(option);
    });
    calculateAdminQueryTotalAmount();
}

/* ── Get selected room ── */
function getSelectedAdminRoom() {
    const categorySelect = document.getElementById('adminQueryRoomCategory');
    const listingId = document.getElementById('adminQueryListingId')?.value;
    const listing = listingPayload[String(listingId)] || listingPayload[listingId];
    if (!listing || !Array.isArray(listing.rooms) || !categorySelect || categorySelect.value === '') return null;
    const selectedIndex = Number(categorySelect.value);
    return Number.isInteger(selectedIndex) ? listing.rooms[selectedIndex] : null;
}

/* ── Nights calculation ── */
function calculateAdminQueryNights() {
    const checkIn  = document.getElementById('adminQueryCheckIn')?.value;
    const checkOut = document.getElementById('adminQueryCheckOut')?.value;
    const nightsInput = document.getElementById('adminQueryNights');
    let nights = 0;
    if (checkIn && checkOut) {
        const diff = Math.round((new Date(checkOut) - new Date(checkIn)) / (1000 * 60 * 60 * 24));
        nights = diff > 0 ? diff : 0;
    }
    if (nightsInput) nightsInput.value = String(nights);
    calculateAdminQueryTotalAmount();
}

/* ── Total amount ── */
function calculateAdminQueryTotalAmount() {
    const room     = getSelectedAdminRoom();
    const nights   = Number(document.getElementById('adminQueryNights')?.value || 0);
    const rooms    = Number(document.getElementById('adminQueryRooms')?.value || 1);
    const mealPlan = document.getElementById('adminQueryMealPlan')?.value || '';
    const totalInput = document.getElementById('adminQueryTotalAmount');
    if (!room || nights <= 0 || rooms <= 0) {
        if (totalInput) totalInput.value = '0';
        return;
    }
    let unitPrice = 0;
    switch (mealPlan) {
        case 'CP (Breakfast)':
            unitPrice = Number(room.weekday_cpai || room.cpai_price || room.weekday_price || 0); break;
        case 'MAP (Breakfast + Dinner)':
            unitPrice = Number(room.weekday_mapai || room.mapai_price || room.weekday_price || 0); break;
        case 'AP (All Meals)':
            unitPrice = Number(room.weekday_apai || room.weekday_price || 0); break;
        default:
            unitPrice = Number(room.weekday_price || 0);
    }
    const total = unitPrice * nights * rooms;
    if (totalInput) totalInput.value = String(total ? Math.round(total) : 0);
}

function loadAdminBudgetBasedHotels() {
    const location = document.getElementById('adminQueryLocation')?.value.trim() || '';
    const category = document.getElementById('adminQueryCategory')?.value.trim() || '';
    const budget = Number(document.getElementById('adminQueryBudget')?.value || 0);

    if (!location && !category && budget <= 0) {
        alert('Please fill at least the location, category, or budget to find matching hotels.');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'filter_hotels_for_query');
    formData.append('location', location);
    formData.append('category', category);
    formData.append('budget', String(budget || 0));

    fetch('employee-dashboard.php', {
        method: 'POST',
        body: formData
    })
    .then((response) => response.json())
    .then((data) => {
        if (!data.success || !Array.isArray(data.results)) {
            throw new Error(data.message || 'Unable to load matching properties');
        }

        const select = document.getElementById('adminQueryListingId');
        const searchInput = document.getElementById('adminQueryListingSearch');
        if (!select) return;

        select.innerHTML = '<option value="">Select property...</option>';
        const results = data.results;

        results.forEach((hotel) => {
            const option = document.createElement('option');
            option.value = String(hotel.id);
            option.textContent = `${hotel.name} ${hotel.location ? ' - ' + hotel.location : ''} ${hotel.category ? ' [' + hotel.category + ']' : ''}`;
            select.appendChild(option);
        });

        if (searchInput) {
            searchInput.value = '';
        }

        if (results.length > 0) {
            select.value = String(results[0].id);
            populateAdminRoomCategories(results[0].id);
            alert(`${results.length} matching hotel${results.length > 1 ? 's were' : ' was'} found for this budget.`);
        } else {
            alert('No properties match this Location + Category + Budget combination.');
        }
    })
    .catch((error) => {
        console.error(error);
        alert(error.message || 'Unable to find matching hotels.');
    });
}

/* ── Generate query ── */
function generateAdminQueryFromForm() {
    const agentPhone = document.getElementById('adminQueryAgentPhone')?.value.trim();
    if (!agentPhone) { alert('Please enter the agent mobile number.'); return; }
    const listingId = document.getElementById('adminQueryListingId')?.value;
    const room = getSelectedAdminRoom();
    if (!listingId || !room) { alert('Please select a property and room category.'); return; }
    const listing = listingPayload[String(listingId)] || listingPayload[listingId];
    const values = {
        agentName:      document.getElementById('adminQueryAgentName')?.value.trim(),
        agentPhone,
        clientName:     document.getElementById('adminQueryClientName')?.value.trim(),
        clientMobile:   document.getElementById('adminQueryClientMobile')?.value.trim(),
        hotelName:      listing.hotel_name || '',
        location:       listing.location || '',
        roomCategory:   room.category_name || room.category || '',
        mealPlan:       document.getElementById('adminQueryMealPlan')?.value || '',
        budget:         document.getElementById('adminQueryBudget')?.value || '0',
        checkIn:        document.getElementById('adminQueryCheckIn')?.value || '',
        checkOut:       document.getElementById('adminQueryCheckOut')?.value || '',
        nights:         document.getElementById('adminQueryNights')?.value || '0',
        rooms:          document.getElementById('adminQueryRooms')?.value || '1',
        adults:         document.getElementById('adminQueryAdults')?.value || '1',
        children:       document.getElementById('adminQueryChildren')?.value || '0',
        totalAmount:    document.getElementById('adminQueryTotalAmount')?.value || '0',
        extraBed:       document.getElementById('adminQueryExtraBed')?.value || 'No',
        specialRequest: document.getElementById('adminQuerySpecialRequest')?.value.trim(),
    };

    const queryNumber = AirwaysQuotation.generateQueryNumber();
    let queryText = AirwaysQuotation.format({
        hotelName: values.hotelName, hotelLocation: values.location, roomCategory: values.roomCategory,
        mealPlan: values.mealPlan, checkIn: values.checkIn, checkOut: values.checkOut,
        adults: values.adults, children: values.children, rooms: values.rooms,
        roomPrice: values.totalAmount, agentName: values.agentName, agentPhone: values.agentPhone, queryNumber
    });
/*
        `Booking Query:`,
        `Agent Name: ${values.agentName || 'N/A'}`,
        `Agent Phone: ${values.agentPhone}`,
        `Client Name: ${values.clientName || 'N/A'}`,
        `Client Mobile: ${values.clientMobile || 'N/A'}`,
        `Property: ${values.hotelName}`,
        `Location: ${values.location}`,
        `Room Category: ${values.roomCategory}`,
        `Meal Plan: ${values.mealPlan}`,
        `Budget: ₹${values.budget}`,
        `Check-in: ${values.checkIn}`,
        `Check-out: ${values.checkOut}`,
        `Nights: ${values.nights}`,
        `Rooms: ${values.rooms}`,
        `Adults: ${values.adults}`,
        `Children: ${values.children}`,
        `Total Amount: ₹${values.totalAmount}`,
        `Extra Bed: ${values.extraBed}`,
        `Special Request: ${values.specialRequest || 'None'}`,
    ].join('\n'); */

    document.getElementById('adminGeneratedQueryText').value = queryText;
    document.getElementById('adminGeneratedQueryWhatsappLink').href =
        `https://wa.me/${agentPhone.replace(/[^0-9]/g, '')}?text=${encodeURIComponent(queryText)}`;
    document.getElementById('adminGeneratedQueryDisplay').style.display = 'block';
    navigator.clipboard.writeText(queryText).catch(() => {});
    alert('Admin booking query generated and copied to clipboard.');

    fetch('employee-dashboard.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'lock_agent_and_save_query',
            agentPhone: agentPhone,
            queryText: queryText,
            hotelName: listing.hotel_name || '',
            roomCategory: values.roomCategory,
            checkIn: values.checkIn,
            checkOut: values.checkOut,
            adults: values.adults,
            children: values.children,
            rooms: values.rooms,
            mealPlan: values.mealPlan,
            totalAmount: values.totalAmount,
            clientName: values.clientName,
            clientMobile: values.clientMobile,
            specialRequest: values.specialRequest
        })
    }).then(r => r.json()).then(data => {
        if (data.success) {
            queryText = AirwaysQuotation.format({
                hotelName: values.hotelName, hotelLocation: values.location, roomCategory: values.roomCategory,
                mealPlan: values.mealPlan, checkIn: values.checkIn, checkOut: values.checkOut,
                adults: values.adults, children: values.children, rooms: values.rooms,
                roomPrice: values.totalAmount, agentName: values.agentName, agentPhone: values.agentPhone, queryNumber
            });
            document.getElementById('adminGeneratedQueryText').value = queryText;
            document.getElementById('adminGeneratedQueryWhatsappLink').href =
                `https://wa.me/${agentPhone.replace(/[^0-9]/g, '')}?text=${encodeURIComponent(queryText)}`;
            navigator.clipboard?.writeText(queryText).catch(() => {});
            setTimeout(() => { location.reload(); }, 600);
        } else {
            console.warn('Unable to save query:', data.message);
        }
    }).catch(err => console.error('Save error', err));
}

/* ── Copy query ── */
function copyAdminGeneratedQuery() {
    const text = document.getElementById('adminGeneratedQueryText')?.value;
    if (!text) return;
    navigator.clipboard.writeText(text).then(() => alert('Query copied to clipboard.'));
}

function copyQueryText(text) {
    text = AirwaysQuotation.plainText(text || '');
    if (!text) return alert('No query text');
    try { navigator.clipboard.writeText(text); alert('Copied to clipboard'); }
    catch (e) { const ta=document.createElement('textarea'); ta.value=text; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta); alert('Copied to clipboard'); }
}

function viewAdminQuery(id) {
    if (!id) return;
    fetch('employee-dashboard.php', {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'get_query_by_id', queryId: id })
    }).then(r => r.json()).then(data => {
        if (!data.success) return alert(data.message || 'Unable to load query');
        let parts = [];
        if (data.agent_name) parts.push('Agent: ' + data.agent_name + (data.agent_phone ? ' ('+data.agent_phone+')' : ''));
        if (data.hotel_name) parts.push('Hotel: ' + data.hotel_name);
        if (data.room_category) parts.push('Room Category: ' + data.room_category);
        if (data.check_in || data.check_out) parts.push('Dates: ' + (data.check_in || '') + (data.check_out ? ' - ' + data.check_out : ''));
        parts.push('Pax: A:' + (data.adults||1) + ' C:' + (data.children||0) + ' R:' + (data.rooms||1));
        if (data.total_amount) parts.push('Amount: ₹' + Number(data.total_amount||0).toFixed(2));
        if (data.client_name) parts.push('Client: ' + data.client_name + (data.client_mobile ? ' ('+data.client_mobile+')' : ''));
        if (data.special_request) parts.push('Special Request: ' + data.special_request);
        parts.push('\n--- Query Text ---\n');
        parts.push(data.query || 'No query text');
        alert(parts.join('\n'));
    }).catch(err => { console.error(err); alert('Error loading query'); });
}

/* ── Init ── */
populateAdminListingSelect();
</script>
</body>
</html>