<?php
require_once __DIR__ . '/includes/auth_session.php';
require_once __DIR__ . '/includes/db_connect.php';
require_role('admin');

try {
    $historyStmt = $conn->prepare("SELECT bqh.id, bqh.created_by_user_id, bqh.created_by_username, bqh.created_by_role, bqh.generated_at, bqh.query_text,
                                          bqh.query_type, bqh.agent_id, bqh.agent_name, bqh.agent_phone, bqh.lock_until,
                                          bqh.location, bqh.hotel_category, bqh.check_in, bqh.check_out,
                                          bqh.nights, bqh.adults, bqh.children, bqh.rooms, bqh.budget, bqh.matched_hotels_json,
                                          COALESCE(NULLIF(bqh.created_by_username, ''), 'Unknown') AS employee_name,
                                          '' AS agent_name, '' AS agent_phone
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
    <title>Query History — Uttarakhand Ventures CRM</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/sidebar.css">
    <style>
        :root { --bg:#f8fafc; --panel:#fff; --nav:#0f172a; --muted:#94a3b8; --brand:#4f46e5; --accent:#06b6d4; --text:#0f172a; --text-secondary:#475569; --border:#e2e8f0; }
        body { background:var(--bg); color:var(--text); font-family:'Inter','Segoe UI',system-ui,sans-serif; font-size:13px; }
        .main-wrapper { margin-left: 232px; padding: 24px; min-height: 100vh; }
        .top-header {
            background: rgba(255,255,255,.95); padding: 12px 20px; display:flex; align-items:center; gap:14px;
            border-bottom:1px solid var(--border); margin:-24px -24px 24px -24px; position:sticky; top:0; z-index:20; backdrop-filter: blur(10px);
        }
        .panel { background:#fff; border:1px solid var(--border); border-radius:18px; padding:24px; }
        @media (max-width: 992px) { .main-wrapper { margin-left:0; } }
    </style>
</head>
<body>
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
        <li class="nav-item"><a class="nav-link" href="/agents-details.php"><i class="bi bi-person-badge"></i> Agents Details</a></li>
        <li class="nav-item"><a class="nav-link" href="/booking-details.php"><i class="bi bi-calendar-check"></i> Bookings Details</a></li>
        <li class="nav-item"><a class="nav-link" href="/bookingquery.php"><i class="bi bi-chat-dots"></i> Booking Query</a></li>
        <li class="nav-item"><a class="nav-link active" href="/query-history.php"><i class="bi bi-clock-history"></i> Query History</a></li>
        <li class="nav-item"><a class="nav-link" href="/employees-detail.php"><i class="bi bi-person-vcard"></i> Employees Details</a></li>
        <li class="nav-item"><a class="nav-link" href="/accounts-detail.php"><i class="bi bi-wallet2"></i> Accounts Details</a></li>
        <li class="nav-item"><a class="nav-link" href="/listing.php"><i class="bi bi-building"></i> Hotel Listings</a></li>
    </ul>
</div>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<div class="main-wrapper">
    <header class="top-header">
        <button class="btn btn-light mobile-menu-btn" type="button" id="mobileMenuBtn" aria-label="Open menu">
            <i class="bi bi-list fs-4"></i>
        </button>
        <div>
            <h5 class="mb-0 fw-bold">Query History</h5>
            <p class="text-muted mb-0 small">All booking query history generated by employees and admin.</p>
        </div>
        <div class="dropdown user-menu-corner ms-auto">
            <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8'); ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="/dashboard.php"><i class="bi bi-person-circle me-2"></i> Profile</a></li>
                <li><a class="dropdown-item" href="/bookingquery.php"><i class="bi bi-chat-dots me-2"></i> Booking Query</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
            </ul>
        </div>
    </header>

    <div class="panel">
        <div class="d-flex flex-wrap gap-2 mb-3">
            <a href="/bookingquery.php" class="btn btn-outline-primary fw-semibold px-3 py-2">
                <i class="bi bi-chat-dots me-2"></i>Booking Query
            </a>
            <a href="/query-history.php" class="btn btn-primary fw-semibold px-3 py-2">
                <i class="bi bi-clock-history me-2"></i>Query History
            </a>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Employee</label>
                <input type="text" class="form-control form-control-sm" id="adminHistoryEmployeeFilter" placeholder="Employee name">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Hotel</label>
                <input type="text" class="form-control form-control-sm" id="adminHistoryHotelFilter" placeholder="Hotel name">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Location</label>
                <input type="text" class="form-control form-control-sm" id="adminHistoryLocationFilter" placeholder="Location">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Category</label>
                <input type="text" class="form-control form-control-sm" id="adminHistoryCategoryFilter" placeholder="Hotel category">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Room</label>
                <input type="text" class="form-control form-control-sm" id="adminHistoryRoomFilter" placeholder="Room category">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Budget</label>
                <input type="number" class="form-control form-control-sm" id="adminHistoryBudgetFilter" placeholder="Min budget">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Check-in</label>
                <input type="date" class="form-control form-control-sm" id="adminHistoryCheckInFilter">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Check-out</label>
                <input type="date" class="form-control form-control-sm" id="adminHistoryCheckOutFilter">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Date From</label>
                <input type="date" class="form-control form-control-sm" id="adminHistoryFromFilter">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Date To</label>
                <input type="date" class="form-control form-control-sm" id="adminHistoryToFilter">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="button" class="btn btn-sm btn-outline-secondary w-100" id="adminHistoryResetFilters">Reset</button>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
            <button type="button" class="btn btn-sm btn-primary admin-query-history-filter active" data-history-filter="all">All</button>
            <button type="button" class="btn btn-sm btn-outline-primary admin-query-history-filter" data-history-filter="today">Today</button>
            <button type="button" class="btn btn-sm btn-outline-primary admin-query-history-filter" data-history-filter="week">This Week</button>
            <button type="button" class="btn btn-sm btn-outline-primary admin-query-history-filter" data-history-filter="month">This Month</button>
            <input type="search" class="form-control form-control-sm ms-auto" style="max-width:240px" id="adminQueryHistorySearch" placeholder="Quick search...">
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead class="table-light"><tr><th>Employee</th><th>Agent</th><th>Phone</th><th>Hotel</th><th>Room Category</th><th>Dates</th><th>Pax</th><th>Amount</th><th>Location</th><th>Generated At</th><th>Lock Until</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($admin_history as $item): ?>
                    <tr class="admin-history-row" data-query-id="<?php echo (int)($item['id'] ?? 0); ?>"
                        data-employee="<?php echo htmlspecialchars(strtolower((string)($item['created_by_username'] ?? $item['employee_name'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"
                        data-hotel="<?php echo htmlspecialchars(strtolower((string)($item['hotel_name'] ?? ($item['hotel_category'] ?? ''))), ENT_QUOTES, 'UTF-8'); ?>"
                        data-location="<?php echo htmlspecialchars(strtolower((string)($item['location'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"
                        data-category="<?php echo htmlspecialchars(strtolower((string)($item['hotel_category'] ?? ($item['room_category'] ?? ''))), ENT_QUOTES, 'UTF-8'); ?>"
                        data-room="<?php echo htmlspecialchars(strtolower((string)($item['room_category'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"
                        data-budget="<?php echo htmlspecialchars((string)((float)($item['total_amount'] ?? $item['budget'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?>"
                        data-checkin="<?php echo htmlspecialchars((string)($item['check_in'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        data-checkout="<?php echo htmlspecialchars((string)($item['check_out'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        data-nights="<?php echo htmlspecialchars((string)($item['nights'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>"
                        data-adults="<?php echo htmlspecialchars((string)($item['adults'] ?? 1), ENT_QUOTES, 'UTF-8'); ?>"
                        data-children="<?php echo htmlspecialchars((string)($item['children'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>"
                        data-rooms="<?php echo htmlspecialchars((string)($item['rooms'] ?? 1), ENT_QUOTES, 'UTF-8'); ?>"
                        data-hotels="<?php echo htmlspecialchars($item['matched_hotels_json'] ?? '[]', ENT_QUOTES, 'UTF-8'); ?>"
                        data-history-date="<?php echo htmlspecialchars($item['generated_at'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                        data-history-text="<?php echo htmlspecialchars(strtolower((string)($item['query_text'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>">>
                        <td><?php echo htmlspecialchars($item['created_by_username'] ?? $item['employee_name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($item['agent_name'] ?? ($item['employee_name'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars($item['agent_phone'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($item['hotel_name'] ?? ($item['hotel_category'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars($item['room_category'] ?? ($item['hotel_category'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars(($item['check_in'] ?? '') . (isset($item['check_out']) && $item['check_out'] ? ' - ' . $item['check_out'] : '')); ?></td>
                        <td><?php echo 'A:' . ((int)($item['adults'] ?? 1)) . ' C:' . ((int)($item['children'] ?? 0)) . ' R:' . ((int)($item['rooms'] ?? 1)); ?></td>
                        <td>₹<?php echo number_format((float)($item['total_amount'] ?? $item['budget'] ?? 0),0); ?></td>
                        <td><?php echo htmlspecialchars($item['location'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($item['generated_at']); ?></td>
                        <td><?php echo htmlspecialchars($item['lock_until'] ?? ''); ?></td>
                        <td>
                            <?php if (!empty($item['agent_phone'])): ?>
                                <button class="btn btn-sm btn-outline-primary" onclick="viewAdminQuery(<?php echo (int)$item['id']; ?>)">View</button>
                                <button class="btn btn-sm btn-outline-secondary" onclick="copyQueryText('', this)">Copy</button>
                                <a class="btn btn-sm btn-success" target="_blank" href="https://wa.me/<?php echo preg_replace('/\D/','',($item['agent_phone'] ?? '')); ?>?text=<?php echo urlencode($item['query_text'] ?? ''); ?>">WA</a>
                                <form method="post" style="display:inline-block; margin:0;">
                                    <input type="hidden" name="action" value="unlock_query">
                                    <input type="hidden" name="query_id" value="<?php echo (int) $item['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Unlock</button>
                                </form>
                            <?php else: ?>
                                <button class="btn btn-sm btn-outline-secondary" onclick="copyQueryText('', this)">Copy</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/quotation-template.js?v=20260820-2"></script>
<script src="/assets/js/ui-common.js"></script>
<script>
function getAdminHistoryControls() {
    return {
        employee: document.getElementById('adminHistoryEmployeeFilter')?.value.trim().toLowerCase() || '',
        hotel: document.getElementById('adminHistoryHotelFilter')?.value.trim().toLowerCase() || '',
        location: document.getElementById('adminHistoryLocationFilter')?.value.trim().toLowerCase() || '',
        category: document.getElementById('adminHistoryCategoryFilter')?.value.trim().toLowerCase() || '',
        room: document.getElementById('adminHistoryRoomFilter')?.value.trim().toLowerCase() || '',
        budget: Number(document.getElementById('adminHistoryBudgetFilter')?.value || 0),
        checkIn: document.getElementById('adminHistoryCheckInFilter')?.value || '',
        checkOut: document.getElementById('adminHistoryCheckOutFilter')?.value || '',
        from: document.getElementById('adminHistoryFromFilter')?.value || '',
        to: document.getElementById('adminHistoryToFilter')?.value || ''
    };
}

function applyAdminQueryHistoryFilter(filter) {
    const now = new Date();
    const dayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const weekStart = new Date(dayStart);
    weekStart.setDate(dayStart.getDate() - dayStart.getDay());
    const monthStart = new Date(now.getFullYear(), now.getMonth(), 1);
    const quickSearch = (document.getElementById('adminQueryHistorySearch')?.value || '').toLowerCase().trim();
    const controls = getAdminHistoryControls();

    document.querySelectorAll('.admin-history-row').forEach((row) => {
        const date = new Date(row.dataset.historyDate);
        const dateMatch = filter === 'today' ? date >= dayStart : filter === 'week' ? date >= weekStart : filter === 'month' ? date >= monthStart : true;
        const employeeMatch = !controls.employee || (row.dataset.employee || '').includes(controls.employee);
        const hotelMatch = !controls.hotel || (row.dataset.hotel || '').includes(controls.hotel);
        const locationMatch = !controls.location || (row.dataset.location || '').includes(controls.location);
        const categoryMatch = !controls.category || (row.dataset.category || '').includes(controls.category);
        const roomMatch = !controls.room || (row.dataset.room || '').includes(controls.room);
        const budgetMatch = !controls.budget || Number(row.dataset.budget || 0) >= controls.budget;
        const checkInMatch = !controls.checkIn || (row.dataset.checkin || '') === controls.checkIn;
        const checkOutMatch = !controls.checkOut || (row.dataset.checkout || '') === controls.checkOut;
        const fromMatch = !controls.from || (row.dataset.historyDate || '').slice(0, 10) >= controls.from;
        const toMatch = !controls.to || (row.dataset.historyDate || '').slice(0, 10) <= controls.to;
        const quickMatch = !quickSearch || row.dataset.historyText.includes(quickSearch) || row.textContent.toLowerCase().includes(quickSearch);

        row.style.display = dateMatch && employeeMatch && hotelMatch && locationMatch && categoryMatch && roomMatch && budgetMatch && checkInMatch && checkOutMatch && fromMatch && toMatch && quickMatch ? '' : 'none';
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
["adminHistoryEmployeeFilter","adminHistoryHotelFilter","adminHistoryLocationFilter","adminHistoryCategoryFilter","adminHistoryRoomFilter","adminHistoryBudgetFilter","adminHistoryCheckInFilter","adminHistoryCheckOutFilter","adminHistoryFromFilter","adminHistoryToFilter"].forEach((id) => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', () => applyAdminQueryHistoryFilter('all'));
    if (el) el.addEventListener('change', () => applyAdminQueryHistoryFilter('all'));
});
document.getElementById('adminQueryHistorySearch')?.addEventListener('input', () => applyAdminQueryHistoryFilter('all'));
document.getElementById('adminHistoryResetFilters')?.addEventListener('click', () => {
    ["adminHistoryEmployeeFilter","adminHistoryHotelFilter","adminHistoryLocationFilter","adminHistoryCategoryFilter","adminHistoryRoomFilter","adminHistoryBudgetFilter","adminHistoryCheckInFilter","adminHistoryCheckOutFilter","adminHistoryFromFilter","adminHistoryToFilter"].forEach((id) => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    document.getElementById('adminQueryHistorySearch').value = '';
    applyAdminQueryHistoryFilter('all');
});

function copyQueryText(queryText, buttonElement) {
    // Find the row containing this button
    const row = buttonElement ? buttonElement.closest('.admin-history-row') : (event && event.target ? event.target.closest('.admin-history-row') : null);
    
    if (!row) {
        // Fallback: just copy the query text if provided
        queryText = AirwaysQuotation.plainText(queryText || '');
        if (queryText) {
            navigator.clipboard.writeText(queryText).then(() => {
                alert('Query copied to clipboard!');
            }).catch(() => {
                alert('Failed to copy: ' + queryText.substring(0, 100));
            });
        }
        return;
    }

    let matchedHotels = [];
    try { matchedHotels = JSON.parse(row.dataset.hotels || '[]'); } catch (e) { matchedHotels = []; }
    const firstHotel = matchedHotels[0] || {};
    const firstRoom = firstHotel.rooms?.[0] || firstHotel;
    const quotationText = AirwaysQuotation.format({
        id: row.dataset.queryId,
        hotelName: firstHotel.name || row.dataset.hotel,
        hotelLocation: firstHotel.location || firstHotel.city || row.dataset.location,
        roomCategory: firstRoom.room_name || firstRoom.category || row.dataset.room,
        mealPlan: firstRoom.prices ? Object.keys(firstRoom.prices)[0] : '',
        checkIn: row.dataset.checkin, checkOut: row.dataset.checkout,
        adults: row.dataset.adults, children: row.dataset.children, rooms: row.dataset.rooms,
        roomPrice: firstRoom.selected_price || firstRoom.basePrice || row.dataset.budget,
        matchedHotels
    });
    navigator.clipboard.writeText(quotationText).then(() => alert('Query copied to clipboard!')).catch(() => {
        const textarea = document.createElement('textarea');
        textarea.value = quotationText;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        alert('Query copied to clipboard!');
    });
}

function toggleSidebarMenu(open) {
    const sidebar = document.getElementById('adminSidebar');
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
})();
</script>
</body>
</html>
