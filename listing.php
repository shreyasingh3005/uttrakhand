<?php
/* ═══════════════════════════════════════════════════════════════════════════
   listing.php — Hotel Room Manager (Clean Dynamic Edition)
   Uttarakhand Ventures CRM
   ═══════════════════════════════════════════════════════════════════════════ */
require_once __DIR__ . '/includes/auth_session.php';
require_once __DIR__ . '/includes/db_connect.php';
require_role('admin');
$isAdminUser = true; // This page is admin-only

/* ── PDO instance from existing connection ──────────────────────────────── */
$pdo = $conn; // $conn is the PDO from db_connect.php

/* ── Listing filters/sort/pagination ───────────────────────────────────── */
$qHotelName = trim((string)($_GET['hotel_name'] ?? ''));
$qHotelCode = trim((string)($_GET['hotel_code'] ?? ''));
$qCity = trim((string)($_GET['city'] ?? ''));
$qState = trim((string)($_GET['state'] ?? ''));
$qContact = trim((string)($_GET['contact_number'] ?? ''));
$qEmail = trim((string)($_GET['email'] ?? ''));
$qPage = max(1, (int)($_GET['page'] ?? 1));
$qPerPage = (int)($_GET['per_page'] ?? 20);
$qPerPage = max(10, min(100, $qPerPage));
$qOffset = ($qPage - 1) * $qPerPage;

$qSortBy = (string)($_GET['sort_by'] ?? 'created_at');
$qSortDir = strtolower((string)($_GET['sort_dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
$sortCols = [
  'created_at' => 'h.created_at',
  'hotel_name' => 'h.name',
  'hotel_code' => 'h.hotel_code',
  'city' => 'h.city',
  'state' => 'h.state',
  'contact_number' => 'h.phone',
  'email' => 'h.email',
  'total_rooms' => 'total_rooms',
  'available' => 'avail_rooms',
  'booked' => 'booked_rooms',
  'blocked' => 'blocked_rooms',
  'occupancy' => 'occupancy_pct',
];
$sortExpr = $sortCols[$qSortBy] ?? 'h.created_at';

/* ── Fetch meal plans ───────────────────────────────────────────────────── */
try {
    $mpStmt  = $pdo->query("SELECT id, code, name AS label, sort_order FROM meal_plans WHERE status='active' ORDER BY sort_order");
    $mpRows  = $mpStmt ? $mpStmt->fetchAll() : [];
} catch (PDOException $e) {
    $mpRows = [];
}
$mealPlans    = array_column($mpRows, 'label', 'code');
$mealPlanIds  = array_column($mpRows, 'id', 'code');
if (empty($mealPlans)) {
    $mealPlans   = ['EP'=>'EP – Room Only','CP'=>'CP – Breakfast Included','MAP'=>'MAP – Breakfast + Dinner','AP'=>'AP – All Meals'];
    $mealPlanIds = ['EP'=>1,'CP'=>2,'MAP'=>3,'AP'=>4];
}

/* ── Fetch hotels with counts (filtered + paginated) ───────────────────── */
$hotels = [];
$totalHotelsFiltered = 0;
try {
  $where = ["h.status='active'"];
  $params = [];

  if ($qHotelName !== '') {
    $where[] = 'h.name LIKE :hotel_name';
    $params[':hotel_name'] = '%' . $qHotelName . '%';
  }
  if ($qHotelCode !== '') {
    $where[] = 'h.hotel_code LIKE :hotel_code';
    $params[':hotel_code'] = '%' . $qHotelCode . '%';
  }
  if ($qCity !== '') {
    $where[] = 'h.city LIKE :city';
    $params[':city'] = '%' . $qCity . '%';
  }
  if ($qState !== '') {
    $where[] = 'h.state LIKE :state';
    $params[':state'] = '%' . $qState . '%';
  }
  if ($qContact !== '') {
    $where[] = '(h.phone LIKE :contact OR COALESCE(h.contact_details,\'\') LIKE :contact)';
    $params[':contact'] = '%' . $qContact . '%';
  }
  if ($qEmail !== '') {
    $where[] = 'h.email LIKE :email';
    $params[':email'] = '%' . $qEmail . '%';
  }
  $whereSql = implode(' AND ', $where);

  $cntStmt = $pdo->prepare("SELECT COUNT(*) AS c FROM hotels h WHERE $whereSql");
  foreach ($params as $k => $v) {
    $cntStmt->bindValue($k, $v, PDO::PARAM_STR);
  }
  $cntStmt->execute();
  $totalHotelsFiltered = (int)($cntStmt->fetch()['c'] ?? 0);

  $sql = "
    SELECT h.*,
         COALESCE(agg.room_count,0) AS room_count,
         COALESCE(agg.total_rooms,0) AS total_rooms,
         COALESCE(agg.booked_rooms,0) AS booked_rooms,
         COALESCE(agg.avail_rooms,0) AS avail_rooms,
         COALESCE(agg.blocked_rooms,0) AS blocked_rooms,
         ROUND(COALESCE(agg.booked_rooms,0) / NULLIF(COALESCE(agg.total_rooms,0),0) * 100, 1) AS occupancy_pct
    FROM hotels h
    LEFT JOIN (
      SELECT hotel_id,
           COUNT(*) AS room_count,
           COALESCE(SUM(total_rooms),0) AS total_rooms,
           COALESCE(SUM(booked_rooms),0) AS booked_rooms,
           COALESCE(SUM(available_rooms),0) AS avail_rooms,
           COALESCE(SUM(blocked_rooms),0) AS blocked_rooms
      FROM hotel_room_categories
      WHERE status='active'
      GROUP BY hotel_id
    ) agg ON agg.hotel_id = h.id
    WHERE $whereSql
    ORDER BY $sortExpr $qSortDir, h.id DESC
    LIMIT :limit OFFSET :offset
  ";
  $stmt = $pdo->prepare($sql);
  foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, PDO::PARAM_STR);
  }
  $stmt->bindValue(':limit', $qPerPage, PDO::PARAM_INT);
  $stmt->bindValue(':offset', $qOffset, PDO::PARAM_INT);
  $stmt->execute();
  $hotels = $stmt->fetchAll() ?: [];
} catch (PDOException $e) {
  $hotels = [];
}

/* ── For each hotel fetch rooms + base prices ───────────────────────────── */
foreach ($hotels as &$hotel) {
    try {
        $rStmt = $pdo->prepare("SELECT * FROM hotel_room_categories WHERE hotel_id=? AND status='active' ORDER BY id");
        $rStmt->execute([$hotel['id']]);
        $rooms = $rStmt->fetchAll();
        foreach ($rooms as &$room) {
            $pStmt = $pdo->prepare("SELECT mp.code, rp.base_price FROM room_prices rp JOIN meal_plans mp ON mp.id=rp.meal_plan_id WHERE rp.room_category_id=? AND rp.rate_date IS NULL");
            $pStmt->execute([$room['id']]);
            $prices = [];
            foreach ($pStmt->fetchAll() as $p) $prices[$p['code']] = (float)$p['base_price'];
            $room['prices'] = $prices;
        }
        unset($room);
    } catch (PDOException $e) { $rooms = []; }
    $hotel['rooms'] = $rooms ?? [];
}
unset($hotel);

/* ── Calendar defaults ──────────────────────────────────────────────────── */
$calYear      = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
$calMonth     = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
if ($calMonth < 1)  { $calMonth = 12; $calYear--; }
if ($calMonth > 12) { $calMonth = 1;  $calYear++; }
$todayStr     = date('Y-m-d');
$calMonthStr  = date('F Y', mktime(0, 0, 0, $calMonth, 1, $calYear));

/* ── Availability grid dates (today + 13) ──────────────────────────────── */
$availDates = [];
for ($d = 0; $d < 14; $d++) $availDates[] = date('Y-m-d', strtotime("+{$d} days"));

/* ── Page stats ─────────────────────────────────────────────────────────── */
$totalHotels = $totalHotelsFiltered;
$totalRooms  = array_sum(array_column($hotels, 'total_rooms'));
$totalAvail  = array_sum(array_column($hotels, 'avail_rooms'));
$totalBooked = array_sum(array_column($hotels, 'booked_rooms'));
$globalOcc   = $totalRooms > 0 ? round($totalBooked / $totalRooms * 100) : 0;
$totalPages  = max(1, (int)ceil($totalHotels / $qPerPage));
$queryBase = [
  'hotel_name' => $qHotelName,
  'hotel_code' => $qHotelCode,
  'city' => $qCity,
  'state' => $qState,
  'contact_number' => $qContact,
  'email' => $qEmail,
  'sort_by' => $qSortBy,
  'sort_dir' => strtolower($qSortDir),
  'per_page' => $qPerPage,
];
$activePage      = 'listing';
$pageTitle       = 'Hotel Room Manager';
$pageDescription = 'Manage room categories, availability, rate calendars and bookings.';

/* ── CSS ────────────────────────────────────────────────────────────────── */
$extraCss = <<<'CSS'
<style>
:root { --bg:#f8fafc; --panel:#fff; --nav:#0f172a; --muted:#94a3b8; --brand:#4f46e5; --accent:#06b6d4; --success:#10b981; --warning:#f59e0b; --danger:#ef4444; --text:#0f172a; --text-secondary:#475569; --border:#e2e8f0; --primary-50:#eef2ff; --primary-200:#c7d2fe; }
body { font-family:'Inter','Segoe UI',system-ui,sans-serif; background:var(--bg); color:var(--text); font-size:13px; }
.btn, .form-control, .form-select, .dropdown-menu, .table { font-size:.82rem; }
.btn { padding:.34rem .68rem; }

.mobile-menu-btn { display:none; }

/* Main */
.main-wrapper { margin-left:232px; min-height:100vh; }
.top-header { background:rgba(255,255,255,.95); padding:10px 14px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border); position:sticky; top:0; z-index:20; backdrop-filter:blur(10px); }
.user-menu-corner { position:static; }

/* Page Content */
.hm-wrap { padding:24px; }
.hm-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:16px; margin-bottom:26px; }
.hm-stat { background:#fff; border-radius:14px; padding:18px 20px; box-shadow:0 2px 10px rgba(0,0,0,.06); border:1px solid var(--border); }
.hm-stat .lbl { font-size:.73rem; font-weight:600; letter-spacing:.5px; color:var(--muted); text-transform:uppercase; }
.hm-stat .val { font-size:2rem; font-weight:800; line-height:1.1; margin-top:4px; }
.hm-toolbar { display:flex; align-items:center; gap:12px; margin-bottom:22px; flex-wrap:wrap; }
.hm-toolbar h2 { flex:1; font-size:1.4rem; font-weight:800; color:var(--text); }

/* Buttons */
.hm-btn { display:inline-flex; align-items:center; gap:6px; padding:9px 18px; border-radius:10px; border:none; cursor:pointer; font-size:.83rem; font-weight:600; transition:all .18s; white-space:nowrap; text-decoration:none; }
.hm-btn-teal { background:var(--accent); color:#fff; } .hm-btn-teal:hover { background:#0891b2; }
.hm-btn-brand { background:var(--brand); color:#fff; } .hm-btn-brand:hover { background:#4338ca; }
.hm-btn-amber { background:var(--warning); color:#1e293b; } .hm-btn-amber:hover { background:#d97706; }
.hm-btn-coral { background:var(--danger); color:#fff; } .hm-btn-coral:hover { background:#dc2626; }
.hm-btn-ghost { background:#f1f5f9; color:var(--text); border:1px solid var(--border); } .hm-btn-ghost:hover { background:#e2e8f0; }
.hm-btn-white { background:#fff; color:var(--text); border:1px solid var(--border); } .hm-btn-white:hover { background:#f8fafc; }
.hm-btn-sm { padding:6px 14px; font-size:.79rem; border-radius:8px; }
.hm-btn-xs { padding:4px 10px; font-size:.75rem; border-radius:7px; }

/* Hotel Card */
.hm-hcard { background:#fff; border-radius:18px; box-shadow:0 4px 20px rgba(0,0,0,.06); border:1px solid var(--border); margin-bottom:28px; overflow:hidden; }
.hm-hcard-hdr { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; padding:20px 24px 0; flex-wrap:wrap; }
.hm-hotel-info h3 { font-size:1.2rem; font-weight:800; color:var(--text); margin:0 0 4px; }
.hm-hotel-info p { font-size:.78rem; color:var(--muted); margin:0; }
.hm-hotel-actions { display:flex; gap:8px; flex-wrap:wrap; padding-top:4px; }
.hm-hstats { display:flex; gap:14px; padding:14px 24px; flex-wrap:wrap; border-bottom:1px solid var(--border); }
.hm-hstat { text-align:center; }
.hm-hstat .n { font-size:1.3rem; font-weight:800; }
.hm-hstat .l { font-size:.67rem; color:var(--muted); font-weight:600; text-transform:uppercase; }

/* Tabs */
.hm-tabs { display:flex; gap:2px; padding:0 20px; background:#f8fafc; border-bottom:1px solid var(--border); overflow-x:auto; }
.hm-tab { padding:12px 16px; cursor:pointer; font-size:.8rem; font-weight:600; color:var(--muted); border-bottom:3px solid transparent; white-space:nowrap; transition:.15s; display:flex; align-items:center; gap:6px; }
.hm-tab:hover { color:var(--text); }
.hm-tab.active { color:var(--accent); border-bottom-color:var(--accent); }
.hm-tab-panel { display:none; padding:20px 24px; }
.hm-tab-panel.active { display:block; }

/* Table */
.hm-panel-top { display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; flex-wrap:wrap; gap:8px; }
.hm-panel-top h3 { font-size:.98rem; font-weight:700; color:var(--text); margin:0; display:flex; align-items:center; gap:6px; }
.hm-rtable { width:100%; border-collapse:collapse; font-size:.8rem; }
.hm-rtable th { background:var(--nav); color:#fff; font-weight:600; font-size:.72rem; letter-spacing:.5px; text-align:left; padding:10px 12px; }
.hm-rtable td { padding:10px 12px; border-bottom:1px solid var(--border); vertical-align:middle; }
.hm-rtable tr:hover td { background:#f8fafc; }

/* Badges */
.hm-badge { display:inline-flex; align-items:center; gap:3px; padding:2px 9px; border-radius:6px; font-size:.71rem; font-weight:700; }
.hm-b-green { background:#d1fae5; color:#065f46; }
.hm-b-red { background:#fee2e2; color:#991b1b; }
.hm-b-orange { background:#ffedd5; color:#9a3412; }
.hm-b-blue { background:#dbeafe; color:#1e40af; }
.hm-b-purple { background:#ede9fe; color:#4c1d95; }
.hm-b-gray { background:#f1f5f9; color:#475569; }

/* Price cell */
.hm-price-cell { font-size:.72rem; }
.hm-price-cell span { display:inline-block; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:5px; padding:1px 6px; margin:1px; color:#166534; font-weight:600; }

/* Availability Grid */
.hm-avail-scroll { overflow-x:auto; border-radius:10px; border:1px solid var(--border); margin-top:10px; }
.hm-agrid { border-collapse:collapse; font-size:.75rem; min-width:900px; }
.hm-agrid th,.hm-agrid td { border:1px solid #e5e7eb; padding:6px 8px; text-align:center; }
.hm-agrid th { background:var(--nav); color:#fff; font-size:.68rem; font-weight:600; }
.hm-agrid td.tname { text-align:left; font-weight:700; color:var(--text); font-size:.78rem; background:#f8fafc; padding:8px 12px; }
.hm-agrid td.ttype { text-align:left; font-size:.71rem; color:var(--muted); background:#fafafa; padding-left:10px; white-space:nowrap; }
.hm-agrid td.sold { background:#fee2e2; }
.hm-agrid td.partial { background:#fff7ed; }
.hm-agrid td.wkfri { background:#eef2ff; }
.hm-agrid td.wksat { background:#fef3c7; }
.hm-agrid td.tdy { outline:2px solid var(--accent); }
.hm-agrid .hm-occ-row td { background:#f0fdf4; font-weight:700; font-size:.72rem; color:#166534; }
input.avail-input, input.blocked-input { width:46px; text-align:center; border:1px solid #d1d5db; border-radius:6px; padding:3px 4px; font-size:.75rem; transition:all .2s; }
input.avail-input:focus, input.blocked-input:focus { outline:none; border-color:var(--accent); }
.hm-agrid td.cell-saving { background:#fef9c3 !important; transition:background .3s; }
.hm-agrid td.cell-saved { background:#d1fae5 !important; transition:background .3s; }
.hm-agrid td.cell-dirty input { border-color:var(--warning); }
.hm-avail-legend { display:flex; gap:12px; flex-wrap:wrap; font-size:.72rem; margin-bottom:6px; align-items:center; }
.hm-ldot { display:inline-block; width:10px; height:10px; border-radius:50%; margin-right:3px; }

/* Rate Calendar */
.hm-rcal { border-collapse:collapse; width:100%; }
.hm-rcal th { background:var(--nav); color:#fff; text-align:center; padding:8px; font-size:.78rem; width:14.28%; }
.hm-rcal td { border:1px solid #e5e7eb; padding:4px; height:80px; vertical-align:top; }
.hm-rcal td.other-month { background:#f9fafb; }
.hm-rcal td.today-cell { outline:2px solid var(--accent); }
.hm-rcal td.fri-cell { background:#eef2ff; }
.hm-rcal td.sat-cell { background:#fef3c7; }
.hm-rcal td.sun-cell { background:#fce7f3; }
.hm-cal-day-num { font-size:.72rem; font-weight:700; color:var(--muted); padding:2px 4px; }
.hm-cal-price { display:block; width:100%; text-align:center; border:1px solid #d1fae5; border-radius:5px; padding:3px 2px; font-size:.75rem; font-weight:700; color:#065f46; background:#f0fdf4; cursor:pointer; }
.hm-cal-price:hover { background:#d1fae5; }
.hm-cal-hdr { display:flex; align-items:center; gap:12px; margin:10px 0; flex-wrap:wrap; }
.hm-cal-hdr h3 { font-size:1rem; font-weight:700; color:var(--text); }
.hm-room-plan-bar { display:flex; align-items:center; gap:10px; margin-bottom:12px; flex-wrap:wrap; font-size:.8rem; }
.hm-room-plan-bar select { padding:6px 10px; border:1px solid var(--border); border-radius:8px; font-size:.8rem; font-family:inherit; }
.hm-day-rule { display:flex; align-items:center; gap:8px; flex-wrap:wrap; font-size:.78rem; margin-bottom:10px; background:var(--primary-50); padding:8px 14px; border-radius:10px; }

/* Modals */
.hm-overlay { position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9000; display:none; align-items:center; justify-content:center; padding:16px; }
.hm-overlay.open { display:flex; }
.hm-modal { background:#fff; border-radius:18px; width:100%; max-width:680px; max-height:90vh; overflow-y:auto; box-shadow:0 25px 60px rgba(0,0,0,.25); }
.hm-modal.wide { max-width:820px; }
.hm-modal.xl { max-width:960px; }
.hm-modal-hdr { background:linear-gradient(135deg,var(--nav),#1e293b); padding:22px 26px 18px; border-radius:18px 18px 0 0; }
.hm-modal-hdr h3 { color:#fff; font-size:1.1rem; font-weight:800; margin:0 0 4px; }
.hm-modal-hdr p { color:#94a3b8; font-size:.78rem; margin:0; }
.hm-modal-body { padding:22px 26px; }
.hm-mfooter { display:flex; justify-content:flex-end; gap:10px; padding:16px 26px; border-top:1px solid var(--border); flex-wrap:wrap; }
.hm-frow { display:flex; flex-direction:column; gap:5px; margin-bottom:14px; }
.hm-frow label { font-size:.76rem; font-weight:600; color:var(--text); }
.hm-frow input,.hm-frow select,.hm-frow textarea { padding:9px 12px; border:1px solid #d1d5db; border-radius:9px; font-size:.84rem; font-family:inherit; transition:border .15s; width:100%; box-sizing:border-box; }
.hm-frow input:focus,.hm-frow select:focus,.hm-frow textarea:focus { outline:none; border-color:var(--accent); }
.hm-fgrid2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.hm-fgrid3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; }
.hm-wizard-step { display:none; }
.hm-wizard-step.active { display:block; }
.hm-step-dots { display:flex; gap:8px; justify-content:center; padding:14px 0 0; }
.hm-dot { width:30px; height:8px; border-radius:4px; background:#e2e8f0; cursor:pointer; transition:.2s; }
.hm-dot.active { background:var(--accent); }
.hm-room-entry { background:#f8fafc; border-radius:12px; border:1px solid var(--border); padding:14px 16px; margin-bottom:12px; position:relative; }
.hm-mp-grid { display:grid; grid-template-columns:repeat(5,1fr); gap:8px; margin-top:8px; }

@media(max-width:640px){
    .hm-fgrid2,.hm-fgrid3 { grid-template-columns:1fr; }
    .hm-modal.xl,.hm-modal.wide,.hm-modal { max-width:100%; border-radius:14px; }
    .hm-modal-hdr { padding:16px 18px 14px; border-radius:14px 14px 0 0; }
    .hm-modal-hdr h3 { font-size:1rem; }
    .hm-modal-body { padding:16px 18px; }
    .hm-mfooter { padding:12px 18px; gap:8px; }
    .hm-mfooter .hm-btn { flex:1; justify-content:center; min-width:0; padding:10px 12px; }
    .hm-mp-grid { grid-template-columns:1fr 1fr; }
    .hm-room-entry { padding:12px; }
}
@media(max-width:380px){
    .hm-modal-hdr { padding:14px 14px 12px; }
    .hm-modal-body { padding:14px; }
    .hm-mfooter { padding:10px 14px; }
    .hm-mp-grid { grid-template-columns:1fr; }
}

/* Toast */
.hm-toast { position:fixed; bottom:24px; right:24px; background:#1e293b; color:#fff; padding:12px 20px; border-radius:12px; font-size:.82rem; font-weight:600; display:flex; align-items:center; gap:8px; z-index:9999; opacity:0; transform:translateY(20px); pointer-events:none; transition:all .3s; max-width:380px; }
.hm-toast.show { opacity:1; transform:translateY(0); pointer-events:auto; }
.hm-toast.ok { border-left:4px solid #10b981; }
.hm-toast.err { border-left:4px solid #ef4444; background:#7f1d1d; }

/* Empty */
.hm-empty { text-align:center; padding:40px 20px; color:var(--muted); font-size:.85rem; }
.hm-empty i { font-size:2.5rem; margin-bottom:10px; display:block; }

/* Responsive */
@media(max-width:992px){
    .mobile-menu-btn { display:inline-flex; align-items:center; justify-content:center; }
    .main-wrapper { margin-left:0; }
    .top-header { flex-wrap:wrap; gap:10px; padding:10px; }
    .user-menu-corner { position:fixed; top:10px; right:12px; z-index:1102; }
}
@media(max-width:576px){
    .hm-wrap { padding:14px; }
    .hm-stats { grid-template-columns:repeat(2,1fr); gap:10px; }
    .hm-stat { padding:12px 14px; }
    .hm-stat .val { font-size:1.5rem; }
    .hm-hcard-hdr { padding:14px 16px 0; flex-direction:column; gap:12px; }
    .hm-hotel-actions { width:100%; }
    .hm-hotel-actions .hm-btn { flex:1; justify-content:center; }
    .hm-hstats { padding:12px 16px; gap:10px; }
    .hm-tabs { padding:0 12px; }
    .hm-tab-panel { padding:14px 16px; }
    .hm-toolbar { gap:8px; }
    .hm-toolbar h2 { font-size:1.1rem; }
}
</style>
CSS;

/* ── No shared includes, render our own sidebar ──────────────────────────── */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel Listings — Uttarakhand Ventures CRM</title>
    <link rel="icon" type="image/svg+xml" href="/assets/images/favicon.svg">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/sidebar.css">
    <?php echo $extraCss; ?>
</head>
<body>

<!-- SIDEBAR -->
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
        <li class="nav-item"><a class="nav-link" href="/booking-details.php"><i class="bi bi-calendar-check"></i> Bookings</a></li>
        <li class="nav-item"><a class="nav-link" href="/bookingquery.php"><i class="bi bi-chat-dots"></i> Booking Query</a></li>
        <li class="nav-item"><a class="nav-link" href="/employees-detail.php"><i class="bi bi-person-vcard"></i> Employees</a></li>
        <li class="nav-item"><a class="nav-link" href="/accounts-detail.php"><i class="bi bi-wallet2"></i> Accounts</a></li>
        <li class="nav-item"><a class="nav-link active" href="/listing.php"><i class="bi bi-building"></i> Hotel Listings</a></li>
    </ul>
</div>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<!-- MAIN WRAPPER -->
<div class="main-wrapper">
    <header class="top-header">
        <button class="btn btn-light mobile-menu-btn" type="button" id="mobileMenuBtn" aria-label="Open menu"><i class="bi bi-list fs-4"></i></button>
        <div class="flex-grow-1">
            <h5 class="mb-0 fw-bold">Hotel Listings</h5>
            <p class="text-muted mb-0 small">Manage room categories, availability, rate calendars and bookings.</p>
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

    <div class="hm-wrap">
    <!-- Stats -->
    <div class="hm-stats">
        <div class="hm-stat"><div class="lbl">Total Hotels</div><div class="val" style="color:var(--brand)"><?= $totalHotels ?></div></div>
        <div class="hm-stat"><div class="lbl">Total Rooms</div><div class="val" style="color:var(--accent)"><?= $totalRooms ?></div></div>
        <div class="hm-stat"><div class="lbl">Available</div><div class="val" style="color:#059669"><?= $totalAvail ?></div></div>
        <div class="hm-stat"><div class="lbl">Booked</div><div class="val" style="color:var(--danger)"><?= $totalBooked ?></div></div>
        <div class="hm-stat"><div class="lbl">Occupancy</div><div class="val" style="color:var(--warning)"><?= $globalOcc ?>%</div></div>
    </div>

    <!-- Toolbar -->
    <div class="hm-toolbar">
        <h2><i class="bi bi-building"></i> Hotel Room Manager</h2>
        <button class="hm-btn hm-btn-teal" onclick="openHotelModal()">
            <i class="bi bi-plus-circle-fill"></i> Add Hotel
        </button>
    </div>

    <div class="hm-hcard" style="padding:16px 18px;margin-bottom:18px;">
      <form method="GET" id="listingFilterForm" style="display:grid;grid-template-columns:repeat(6,minmax(120px,1fr));gap:10px;">
        <input class="form-control" type="text" name="hotel_name" id="flt-hotel-name" placeholder="Hotel Name" value="<?= htmlspecialchars($qHotelName, ENT_QUOTES, 'UTF-8') ?>">
        <input class="form-control" type="text" name="hotel_code" id="flt-hotel-code" placeholder="Hotel Code" value="<?= htmlspecialchars($qHotelCode, ENT_QUOTES, 'UTF-8') ?>">
        <input class="form-control" type="text" name="city" id="flt-city" placeholder="City" value="<?= htmlspecialchars($qCity, ENT_QUOTES, 'UTF-8') ?>">
        <input class="form-control" type="text" name="state" id="flt-state" placeholder="State" value="<?= htmlspecialchars($qState, ENT_QUOTES, 'UTF-8') ?>">
        <input class="form-control" type="text" name="contact_number" id="flt-contact" placeholder="Contact Number" value="<?= htmlspecialchars($qContact, ENT_QUOTES, 'UTF-8') ?>">
        <input class="form-control" type="text" name="email" id="flt-email" placeholder="Email" value="<?= htmlspecialchars($qEmail, ENT_QUOTES, 'UTF-8') ?>">
        <select class="form-select" name="sort_by" id="flt-sort-by">
          <?php foreach (['created_at'=>'Newest','hotel_name'=>'Hotel Name','hotel_code'=>'Hotel Code','city'=>'City','state'=>'State','contact_number'=>'Contact','email'=>'Email','total_rooms'=>'Total Rooms','available'=>'Available','booked'=>'Booked','blocked'=>'Blocked','occupancy'=>'Occupancy'] as $k=>$lbl): ?>
          <option value="<?= $k ?>"<?= $qSortBy === $k ? ' selected' : '' ?>>Sort: <?= htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>
        <select class="form-select" name="sort_dir" id="flt-sort-dir">
          <option value="desc"<?= strtolower($qSortDir)==='desc'?' selected':'' ?>>Desc</option>
          <option value="asc"<?= strtolower($qSortDir)==='asc'?' selected':'' ?>>Asc</option>
        </select>
        <select class="form-select" name="per_page" id="flt-per-page">
          <?php foreach ([10,20,50,100] as $pp): ?>
          <option value="<?= $pp ?>"<?= $qPerPage === $pp ? ' selected' : '' ?>>Rows: <?= $pp ?></option>
          <?php endforeach; ?>
        </select>
        <input type="hidden" name="page" value="1" id="flt-page">
        <div style="display:flex;gap:8px;grid-column:span 3;justify-content:flex-end;">
          <a class="hm-btn hm-btn-ghost" href="listing.php"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
          <button class="hm-btn hm-btn-brand" type="submit"><i class="bi bi-search"></i> Apply Filters</button>
        </div>
      </form>
      <div style="margin-top:12px;font-size:.78rem;color:var(--muted);">
        Matching Hotels: <strong><?= (int)$totalHotels ?></strong> | Page <?= (int)$qPage ?> of <?= (int)$totalPages ?>
      </div>
      <div id="liveSearchSummary" style="margin-top:10px;font-size:.78rem;color:#334155;"></div>
    </div>

  <?php if (empty($hotels)): ?>
  <div class="hm-hcard">
    <div class="hm-empty">
      <i class="bi bi-building-slash"></i>
      No hotels found. Click <strong>Add Hotel</strong> to create your first property.
    </div>
  </div>
  <?php endif; ?>

  <?php foreach ($hotels as $hotel):
    $hTot = (int)$hotel['total_rooms'];
    $hBk  = (int)$hotel['booked_rooms'];
    $hAv  = (int)$hotel['avail_rooms'];
    $hBl  = (int)$hotel['blocked_rooms'];
    $hOcc = $hTot > 0 ? round($hBk / $hTot * 100) : 0;
  ?>
  <div class="hm-hcard" id="hcard-<?= $hotel['id'] ?>" data-hotel="<?= htmlspecialchars(json_encode($hotel, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?>">

    <!-- Card Header -->
    <div class="hm-hcard-hdr">
      <div class="hm-hotel-info">
        <h3>
          <?php for($s=1;$s<=$hotel['star_rating'];$s++) echo '<i class="bi bi-star-fill" style="color:#f59e0b;font-size:.8rem;"></i>'; ?>
          <?= htmlspecialchars($hotel['name']) ?>
          <span class="hm-badge hm-b-gray" style="font-size:.65rem;font-weight:600;margin-left:6px;"><?= htmlspecialchars($hotel['hotel_code']) ?></span>
          <?php if (!empty($hotel['property_category'])): ?>
          <span class="hm-badge hm-b-blue" style="font-size:.65rem;font-weight:600;margin-left:4px;"><?= htmlspecialchars($hotel['property_category']) ?></span>
          <?php endif; ?>
        </h3>
        <p><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($hotel['city']) ?><?= $hotel['state'] ? ', '.htmlspecialchars($hotel['state']) : '' ?>
          <?php if($hotel['phone']): ?> &nbsp;|&nbsp; <i class="bi bi-telephone"></i> <?= htmlspecialchars($hotel['phone']) ?><?php endif; ?>
          <?php if($hotel['email']): ?> &nbsp;|&nbsp; <i class="bi bi-envelope"></i> <?= htmlspecialchars($hotel['email']) ?><?php endif; ?>
        </p>
      </div>
      <div class="hm-hotel-actions">
        <button class="hm-btn hm-btn-white hm-btn-sm" onclick="openRoomModal(<?= $hotel['id'] ?>, null)">
          <i class="bi bi-plus-lg"></i> Add Room
        </button>
        <button class="hm-btn hm-btn-brand hm-btn-sm" onclick="openHotelModal(<?= $hotel['id'] ?>)">
          <i class="bi bi-pencil-square"></i> Edit Hotel
        </button>
        <button class="hm-btn hm-btn-amber hm-btn-sm" onclick="openBulk(<?= $hotel['id'] ?>)">
          <i class="bi bi-arrow-up-circle"></i> Bulk Rates
        </button>
        <button class="hm-btn hm-btn-teal hm-btn-sm" onclick="openCreateBooking(<?= $hotel['id'] ?>)">
          <i class="bi bi-calendar-plus"></i> New Booking
        </button>
        <?php if ($isAdminUser): ?>
        <button class="hm-btn hm-btn-coral hm-btn-sm" onclick="deleteHotel(<?= $hotel['id'] ?>)">
          <i class="bi bi-trash3"></i> Delete
        </button>
        <?php endif; ?>
      </div>
    </div>

    <!-- Hotel Stats Bar -->
    <div class="hm-hstats">
      <div class="hm-hstat"><div class="n"><?= (int)$hotel['room_count'] ?></div><div class="l">Categories</div></div>
      <div class="hm-hstat"><div class="n"><?= $hTot ?></div><div class="l">Total Rooms</div></div>
      <div class="hm-hstat"><div class="n" style="color:#059669"><?= $hAv ?></div><div class="l">Available</div></div>
      <div class="hm-hstat"><div class="n" style="color:var(--hm-coral)"><?= $hBk ?></div><div class="l">Booked</div></div>
      <div class="hm-hstat"><div class="n" style="color:#92400e"><?= $hBl ?></div><div class="l">Blocked</div></div>
      <div class="hm-hstat"><div class="n" style="color:var(--hm-brand)"><?= $hOcc ?>%</div><div class="l">Occupancy</div></div>
    </div>

    <!-- Tabs -->
    <div class="hm-tabs">
      <div class="hm-tab active" onclick="switchTab(this,'tp-rooms-<?= $hotel['id'] ?>')"><i class="bi bi-door-open"></i> Room Categories</div>
      <div class="hm-tab" onclick="switchTab(this,'tp-avail-<?= $hotel['id'] ?>');loadAvailability(<?= $hotel['id'] ?>);"><i class="bi bi-grid-3x3"></i> Availability</div>
      <div class="hm-tab" onclick="switchTab(this,'tp-rates-<?= $hotel['id'] ?>');initRateCal(<?= $hotel['id'] ?>);"><i class="bi bi-calendar2-week"></i> Rate Calendar</div>
      <div class="hm-tab" onclick="switchTab(this,'tp-bookings-<?= $hotel['id'] ?>');loadBookings(<?= $hotel['id'] ?>);">
        <i class="bi bi-ticket-detailed"></i> Bookings
        <span id="bk-badge-<?= $hotel['id'] ?>" class="hm-badge hm-b-blue" style="font-size:.62rem;padding:0 6px;display:none">0</span>
      </div>
    </div>

    <!-- TAB: Room Categories -->
    <div class="hm-tab-panel active" id="tp-rooms-<?= $hotel['id'] ?>">
      <div class="hm-panel-top">
        <h3><i class="bi bi-door-open" style="color:var(--hm-teal)"></i> Room Categories</h3>
        <button class="hm-btn hm-btn-teal hm-btn-sm" onclick="openRoomModal(<?= $hotel['id'] ?>, null)">
          <i class="bi bi-plus-lg"></i> Add Category
        </button>
      </div>
      <div style="overflow-x:auto;border-radius:10px;border:1px solid var(--hm-border);">
        <table class="hm-rtable" id="rtable-<?= $hotel['id'] ?>">
          <thead>
            <tr>
              <th>Room Name</th><th>Bed</th><th>Size</th>
              <th style="text-align:center">Total</th><th style="text-align:center">Available</th>
              <th style="text-align:center">Booked</th><th style="text-align:center">Blocked</th>
              <th>Prices (Meal Plan)</th><th>Extra Bed</th><th style="text-align:center">Actions</th>
            </tr>
          </thead>
          <tbody id="rbody-<?= $hotel['id'] ?>">
            <?php if(empty($hotel['rooms'])): ?>
            <tr><td colspan="10" class="hm-empty"><i class="bi bi-inbox"></i> No room categories. Click "Add Category".</td></tr>
            <?php endif; ?>
            <?php foreach($hotel['rooms'] as $room): ?>
            <tr id="rrow-<?= $room['id'] ?>"
                data-name="<?= htmlspecialchars($room['name'],ENT_QUOTES) ?>"
                data-total="<?= $room['total_rooms'] ?>"
                data-avail="<?= $room['available_rooms'] ?>"
                data-size="<?= htmlspecialchars($room['room_size'],ENT_QUOTES) ?>"
                data-bed="<?= $room['bed_type'] ?>"
                data-prices="<?= htmlspecialchars(json_encode($room['prices']),ENT_QUOTES) ?>"
                data-ebon="<?= $room['extra_bed_allowed'] ? '1' : '0' ?>"
                data-ebprice="<?= $room['extra_bed_price'] ?>"
                data-ebmax="<?= $room['max_extra_beds'] ?>">
              <td style="font-weight:700;max-width:200px"><?= htmlspecialchars($room['name']) ?></td>
              <td><span class="hm-badge hm-b-blue"><i class="bi bi-moon"></i> <?= htmlspecialchars($room['bed_type']) ?></span></td>
              <td style="color:var(--hm-muted);font-size:.77rem"><?= htmlspecialchars($room['room_size']) ?></td>
              <td style="text-align:center"><strong><?= $room['total_rooms'] ?></strong></td>
              <td style="text-align:center"><span class="hm-badge hm-b-green"><?= $room['available_rooms'] ?></span></td>
              <td style="text-align:center"><span class="hm-badge hm-b-red"><?= $room['booked_rooms'] ?></span></td>
              <td style="text-align:center"><span class="hm-badge hm-b-orange"><?= $room['blocked_rooms'] ?></span></td>
              <td class="hm-price-cell">
                <?php foreach($mealPlans as $code=>$label): if(isset($room['prices'][$code])): ?>
                <span title="<?= htmlspecialchars($label) ?>"><?= $code ?> ₹<?= number_format($room['prices'][$code]) ?></span>
                <?php endif; endforeach; ?>
              </td>
              <td style="font-size:.77rem">
                <?php if($room['extra_bed_allowed']): ?>
                  <span style="color:var(--hm-teal);font-weight:700"><i class="bi bi-check-circle-fill"></i></span>
                  ₹<?= number_format($room['extra_bed_price']) ?>/bed &nbsp;Max: <?= $room['max_extra_beds'] ?>
                <?php else: ?>
                  <span style="color:var(--hm-muted);font-size:.72rem"><i class="bi bi-x-circle"></i> Not allowed</span>
                <?php endif; ?>
              </td>
              <td style="text-align:center">
                <div style="display:flex;gap:5px;justify-content:center">
                  <button class="hm-btn hm-btn-teal hm-btn-xs" onclick="openRoomModal(<?= $hotel['id'] ?>,<?= $room['id'] ?>)"><i class="bi bi-pencil"></i> Edit</button>
                  <button class="hm-btn hm-btn-coral hm-btn-xs" onclick="removeRoom(<?= $room['id'] ?>,<?= $hotel['id'] ?>)"><i class="bi bi-trash3"></i></button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- TAB: Availability -->
    <div class="hm-tab-panel" id="tp-avail-<?= $hotel['id'] ?>">
      <div class="hm-avail-legend">
        <span><span class="hm-ldot" style="background:#e74c3c"></span>Sold Out</span>
        <span><span class="hm-ldot" style="background:#f39c12"></span>Partial (&lt;3)</span>
        <span><span class="hm-ldot" style="background:#95a5a6"></span>Blocked</span>
        <span><span class="hm-ldot" style="background:#2ecc71"></span>Available</span>
        <span style="margin-left:auto;font-size:.72rem;color:var(--hm-muted)"><i class="bi bi-pencil-square"></i> Click cells to edit</span>
      </div>
      <div class="hm-avail-scroll">
        <table class="hm-agrid" id="agrid-<?= $hotel['id'] ?>">
          <thead>
            <tr>
              <th style="min-width:160px">Room Category</th>
              <th style="min-width:80px">Type</th>
              <?php foreach($availDates as $dt):
                $dow  = date('D', strtotime($dt));
                $cls  = ($dow==='Fri')   ? 'wkfri' : (($dow==='Sat') ? 'wksat' : (($dt===$todayStr) ? 'tdy' : '')); ?>
              <th class="<?= $cls ?>"><?= strtoupper(substr($dow,0,3)) ?><br><?= date('j',strtotime($dt)) ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php if(empty($hotel['rooms'])): ?>
            <tr><td colspan="<?= 2+count($availDates) ?>" class="hm-empty">Add rooms first.</td></tr>
            <?php endif; ?>
            <?php foreach($hotel['rooms'] as $room): $tot=(int)$room['total_rooms']; ?>
            <tr data-room-id="<?= $room['id'] ?>">
              <td class="tname" rowspan="3">
                <?= htmlspecialchars($room['name']) ?>
                <br><small style="color:var(--hm-muted);font-weight:400;font-size:.68rem">(<?= $tot ?> total)</small>
              </td>
              <td class="ttype"><i class="bi bi-check2"></i> Avail</td>
              <?php foreach($availDates as $dt):
                $a    = (int)$room['available_rooms'];
                $cls2 = ($a===0)?'sold':(($a<3)?'partial':''); ?>
              <td class="<?= $cls2 ?>">
                <input type="number" class="avail-input" data-room-id="<?= $room['id'] ?>" data-date="<?= $dt ?>" value="<?= $a ?>" min="0" max="<?= $tot ?>" oninput="updateAvailCellClass(this)" onchange="autoSaveCell(<?= $hotel['id'] ?>,<?= $room['id'] ?>,'<?= $dt ?>')">
              </td>
              <?php endforeach; ?>
            </tr>
            <tr data-room-id="<?= $room['id'] ?>">
              <td class="ttype"><i class="bi bi-person-check"></i> Booked</td>
              <?php foreach($availDates as $dt): ?>
              <td><span class="booked-val" data-room-id="<?= $room['id'] ?>" data-date="<?= $dt ?>"><?= (int)$room['booked_rooms'] ?></span></td>
              <?php endforeach; ?>
            </tr>
            <tr data-room-id="<?= $room['id'] ?>">
              <td class="ttype"><i class="bi bi-lock"></i> Blocked</td>
              <?php foreach($availDates as $dt): ?>
              <td>
                <input type="number" class="blocked-input" data-room-id="<?= $room['id'] ?>" data-date="<?= $dt ?>" value="<?= (int)$room['blocked_rooms'] ?>" min="0" max="<?= $tot ?>" onchange="autoSaveCell(<?= $hotel['id'] ?>,<?= $room['id'] ?>,'<?= $dt ?>')">
              </td>
              <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
            <tr class="hm-occ-row" id="occ-row-<?= $hotel['id'] ?>">
              <td><i class="bi bi-graph-up-arrow"></i> Occupancy %</td><td></td>
              <?php foreach($availDates as $dt): ?><td><?= $hOcc ?>%</td><?php endforeach; ?>
            </tr>
          </tbody>
        </table>
      </div>
      <div style="margin-top:14px;text-align:right">
        <button class="hm-btn hm-btn-teal" onclick="saveAvailability(<?= $hotel['id'] ?>)">
          <i class="bi bi-floppy2"></i> Update Availability
        </button>
      </div>
    </div>

    <!-- TAB: Rate Calendar -->
    <div class="hm-tab-panel" id="tp-rates-<?= $hotel['id'] ?>">
      <div class="hm-room-plan-bar">
        <label><i class="bi bi-door-open"></i> Room:</label>
        <select id="cal-room-<?= $hotel['id'] ?>" onchange="loadRateCal(<?= $hotel['id'] ?>)">
          <?php foreach($hotel['rooms'] as $r): ?>
          <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <label><i class="bi bi-fork-knife"></i> Meal Plan:</label>
        <select id="cal-plan-<?= $hotel['id'] ?>" onchange="loadRateCal(<?= $hotel['id'] ?>)">
          <?php foreach($mealPlans as $code=>$label): ?>
          <option value="<?= $code ?>"><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="hm-day-rule">
        <strong style="font-size:.78rem;color:var(--hm-navy)"><i class="bi bi-calendar2-day"></i> Override by day:</strong>
        <label><input type="checkbox" id="dr-fri-<?= $hotel['id'] ?>" checked> Fri</label>
        <label><input type="checkbox" id="dr-sat-<?= $hotel['id'] ?>" checked> Sat</label>
        <label><input type="checkbox" id="dr-sun-<?= $hotel['id'] ?>"> Sun</label>
        <input type="number" id="dr-val-<?= $hotel['id'] ?>" value="700" placeholder="Rate ₹" style="width:90px;border:1px solid var(--hm-border);border-radius:7px;padding:6px 10px;font-size:.83rem;font-family:inherit">
        <button class="hm-btn hm-btn-amber hm-btn-sm" onclick="applyDayRule(<?= $hotel['id'] ?>)"><i class="bi bi-check2-all"></i> Apply</button>
      </div>
      <div class="hm-cal-hdr">
        <button class="hm-btn hm-btn-ghost hm-btn-sm" onclick="prevMonth(<?= $hotel['id'] ?>)"><i class="bi bi-chevron-left"></i> Prev</button>
        <h3 id="cal-title-<?= $hotel['id'] ?>"><?= $calMonthStr ?></h3>
        <button class="hm-btn hm-btn-ghost hm-btn-sm" onclick="nextMonth(<?= $hotel['id'] ?>)">Next <i class="bi bi-chevron-right"></i></button>
        <span style="font-size:.72rem;color:var(--hm-muted);margin-left:auto">
          <span style="background:var(--hm-fri);padding:2px 7px;border-radius:4px">Fri</span>
          <span style="background:var(--hm-sat);padding:2px 7px;border-radius:4px">Sat</span>
          <span style="background:var(--hm-sun);padding:2px 7px;border-radius:4px">Sun</span>
          <span style="outline:2px solid var(--hm-teal);padding:2px 7px;border-radius:4px">Today</span>
        </span>
      </div>
      <div style="overflow-x:auto">
        <table class="hm-rcal" id="rcal-<?= $hotel['id'] ?>">
          <thead><tr><?php foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dw): ?><th><?= $dw ?></th><?php endforeach; ?></tr></thead>
          <tbody id="rcal-body-<?= $hotel['id'] ?>"></tbody>
        </table>
      </div>
      <div style="margin-top:18px;display:flex;justify-content:flex-end;gap:10px">
        <button class="hm-btn hm-btn-ghost" onclick="copyRatesToAll(<?= $hotel['id'] ?>)"><i class="bi bi-clipboard2-check"></i> Copy to All Rooms</button>
        <button class="hm-btn hm-btn-teal" onclick="saveRates(<?= $hotel['id'] ?>)"><i class="bi bi-floppy2"></i> Save Rates</button>
      </div>
    </div>

    <!-- TAB: Bookings -->
    <div class="hm-tab-panel" id="tp-bookings-<?= $hotel['id'] ?>">
      <div class="hm-panel-top">
        <h3><i class="bi bi-ticket-detailed" style="color:var(--hm-brand)"></i> Bookings</h3>
        <button class="hm-btn hm-btn-brand hm-btn-sm" onclick="openCreateBooking(<?= $hotel['id'] ?>)"><i class="bi bi-plus-lg"></i> New Booking</button>
      </div>
      <div id="bk-list-<?= $hotel['id'] ?>" style="min-height:80px">
        <div style="text-align:center;padding:30px;color:var(--hm-muted);font-size:.84rem"><i class="bi bi-arrow-clockwise" style="font-size:1.2rem"></i><br>Click "Bookings" tab to load...</div>
      </div>
    </div>

    </div><!-- /hm-hcard -->
    <?php endforeach; ?>

    <?php if ($totalPages > 1): ?>
    <div class="hm-hcard" style="padding:12px 16px;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
      <div style="font-size:.8rem;color:var(--text-secondary);">
        Showing page <?= (int)$qPage ?> of <?= (int)$totalPages ?>
      </div>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <?php
          $prevQ = $queryBase;
          $prevQ['page'] = max(1, $qPage - 1);
          $nextQ = $queryBase;
          $nextQ['page'] = min($totalPages, $qPage + 1);
        ?>
        <a class="hm-btn hm-btn-ghost hm-btn-sm<?= $qPage <= 1 ? ' disabled' : '' ?>" href="<?= $qPage <= 1 ? '#' : ('listing.php?' . htmlspecialchars(http_build_query($prevQ), ENT_QUOTES, 'UTF-8')) ?>">
          <i class="bi bi-chevron-left"></i> Prev
        </a>
        <span style="font-size:.78rem;color:var(--muted);">Page <?= (int)$qPage ?></span>
        <a class="hm-btn hm-btn-ghost hm-btn-sm<?= $qPage >= $totalPages ? ' disabled' : '' ?>" href="<?= $qPage >= $totalPages ? '#' : ('listing.php?' . htmlspecialchars(http_build_query($nextQ), ENT_QUOTES, 'UTF-8')) ?>">
          Next <i class="bi bi-chevron-right"></i>
        </a>
      </div>
    </div>
    <?php endif; ?>

    </div><!-- /hm-wrap -->
</div><!-- /main-wrapper -->

<!-- Toast -->
<div class="hm-toast" id="toast"><i class="bi bi-check-circle-fill"></i><span id="toast-msg">Done</span></div>

<!-- MODAL: Add Hotel Wizard -->
<div class="hm-overlay" id="modal-hotel">
  <div class="hm-modal xl">
    <div class="hm-modal-hdr">
      <h3 id="hotel-modal-title"><i class="bi bi-building-add"></i> Add New Hotel</h3>
      <p id="hotel-modal-description">Hotel master details only. Rooms, rates, and availability are managed later.</p>
    </div>
    <div class="hm-modal-body">
      <input type="hidden" id="hotel-edit-id" value="">
      <div class="hm-wizard-step active" id="wiz-s1">
        <div class="hm-fgrid2">
          <div class="hm-frow"><label>Hotel Name *</label><input type="text" id="wiz-name" placeholder="e.g. The Grand Palace"></div>
          <div class="hm-frow"><label>Hotel Code</label><input type="text" id="wiz-code" placeholder="e.g. HTL-MSR-001"></div>
        </div>
        <div class="hm-fgrid2">
          <div class="hm-frow"><label>Hotel Category / Star Rating *</label>
            <select id="wiz-category">
              <?php foreach (hotel_category_options() as $catOpt): ?><option value="<?= htmlspecialchars($catOpt, ENT_QUOTES, 'UTF-8') ?>"<?= $catOpt === '3 Star' ? ' selected' : '' ?>><?= htmlspecialchars($catOpt, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
            </select>
            <div class="hm-fhint" style="font-size:.72rem;color:var(--muted);margin-top:4px;">Drives Location + Category filtering on the Employee Booking Query form.</div>
          </div>
          <div class="hm-frow"><label>Contact Details</label><input type="text" id="wiz-contact" placeholder="Primary contact person / desk info"></div>
        </div>
        <div class="hm-fgrid2">
          <div class="hm-frow"><label>City *</label><input type="text" id="wiz-city" placeholder="e.g. Mussoorie"></div>
          <div class="hm-frow"><label>State</label><input type="text" id="wiz-state" placeholder="e.g. Uttarakhand"></div>
        </div>
        <div class="hm-frow"><label>Address</label><input type="text" id="wiz-address" placeholder="Full address"></div>
        <div class="hm-fgrid3">
          <div class="hm-frow"><label>Pincode</label><input type="text" id="wiz-pincode" placeholder="248001"></div>
          <div class="hm-frow"><label>Phone</label><input type="tel" id="wiz-phone" placeholder="+91 9876543210"></div>
          <div class="hm-frow"><label>Email</label><input type="email" id="wiz-email" placeholder="hotel@example.com"></div>
        </div>
        <div class="hm-frow"><label>Website</label><input type="url" id="wiz-website" placeholder="https://"></div>
        <div class="hm-frow"><label>Description</label><textarea id="wiz-desc" rows="3" placeholder="Brief description..."></textarea></div>
        <div class="hm-frow"><label>Image URLs (comma/new line separated)</label><textarea id="wiz-image-urls" rows="3" placeholder="https://.../img1.jpg"></textarea></div>
      </div>

      <div class="hm-step-dots"><div class="hm-dot active" id="wd-0" onclick="goWizStep(1)"></div></div>
    </div>
    <div class="hm-mfooter">
      <button class="hm-btn hm-btn-ghost" onclick="closeHotelModal()">Cancel</button>
      <button class="hm-btn hm-btn-ghost" id="wiz-prev" onclick="goWizStep(1)" style="display:none"><i class="bi bi-chevron-left"></i> Back</button>
      <button class="hm-btn hm-btn-teal" id="wiz-next" onclick="goWizStep(1)" style="display:none">Next <i class="bi bi-chevron-right"></i></button>
      <button class="hm-btn hm-btn-brand" id="wiz-save" onclick="submitHotelWizard()"><i class="bi bi-check2-circle"></i> Create Hotel</button>
    </div>
  </div>
</div>

<!-- MODAL: Add/Edit Room Category -->
<div class="hm-overlay" id="modal-room">
  <div class="hm-modal wide">
    <div class="hm-modal-hdr">
      <h3 id="rm-title"><i class="bi bi-door-open"></i> Add Room Category</h3>
      <p>Configure room details, pricing and extra bed settings.</p>
    </div>
    <div class="hm-modal-body">
      <input type="hidden" id="rm-hotel-id">
      <input type="hidden" id="rm-room-id">
      <div class="hm-fgrid2">
        <div class="hm-frow"><label>Room Name *</label><input type="text" id="rm-name" placeholder="e.g. Deluxe Suite"></div>
        <div class="hm-frow"><label>Bed Type</label>
          <select id="rm-bed">
            <?php foreach(['Single','Double','Twin','King','Queen','Bunk'] as $bt): ?>
            <option value="<?= $bt ?>"><?= $bt ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="hm-fgrid3">
        <div class="hm-frow"><label>Room Size</label><input type="text" id="rm-size" placeholder="e.g. 280 sq ft"></div>
        <div class="hm-frow"><label>Total Rooms</label><input type="number" id="rm-total" value="1" min="1" max="500"></div>
        <div class="hm-frow"><label>Available Rooms</label><input type="number" id="rm-avail" value="1" min="0" max="500"></div>
      </div>
      <div style="margin-bottom:14px">
        <label style="font-size:.76rem;font-weight:600;color:var(--hm-navy)">Prices by Meal Plan (₹ per night)</label>
        <div class="hm-mp-grid">
          <?php foreach($mealPlans as $code=>$label): ?>
          <div class="hm-frow" style="margin-bottom:0">
            <label><?= $code ?></label>
            <input type="number" id="rmp-<?= $code ?>" min="0" step="50" placeholder="0">
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div style="background:#f0fdf4;border-radius:10px;padding:14px 16px">
        <label style="font-size:.76rem;font-weight:700;color:var(--hm-teal-d);display:flex;align-items:center;gap:8px;margin-bottom:10px">
          <input type="checkbox" id="rm-eb-on" onchange="toggleEbFields()"> Extra Bed Allowed
        </label>
        <div id="rm-eb-fields" style="display:none">
          <div class="hm-fgrid2">
            <div class="hm-frow"><label>Price per Extra Bed (₹)</label><input type="number" id="rm-eb-price" value="0" min="0"></div>
            <div class="hm-frow"><label>Max Extra Beds</label><input type="number" id="rm-eb-max" value="1" min="1" max="5"></div>
          </div>
        </div>
      </div>
    </div>
    <div class="hm-mfooter">
      <button class="hm-btn hm-btn-ghost" onclick="closeRoomModal()">Cancel</button>
      <button class="hm-btn hm-btn-teal" onclick="saveRoomModal()"><i class="bi bi-floppy2"></i> Save Room</button>
    </div>
  </div>
</div>

<!-- MODAL: Bulk Rate Update -->
<div class="hm-overlay" id="modal-bulk">
  <div class="hm-modal">
    <div class="hm-modal-hdr">
      <h3><i class="bi bi-arrow-up-circle"></i> Bulk Rate Update</h3>
      <p>Apply a rate to multiple dates at once.</p>
    </div>
    <div class="hm-modal-body">
      <input type="hidden" id="bulk-hotel-id">
      <div class="hm-fgrid2">
        <div class="hm-frow">
          <label>Room</label>
          <select id="bulk-room">
            <option value="all">All Rooms</option>
          </select>
        </div>
        <div class="hm-frow">
          <label>Meal Plan</label>
          <select id="bulk-plan">
            <?php foreach($mealPlans as $code=>$label): ?>
            <option value="<?= $code ?>"><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="hm-fgrid2">
        <div class="hm-frow"><label>From Date</label><input type="date" id="bulk-from" value="<?= date('Y-m-d') ?>"></div>
        <div class="hm-frow"><label>To Date</label><input type="date" id="bulk-to" value="<?= date('Y-m-d', strtotime('+30 days')) ?>"></div>
      </div>
      <div class="hm-frow"><label>Rate (₹ per night)</label><input type="number" id="bulk-price" value="0" min="0" step="50"></div>
      <div class="hm-frow">
        <label>Apply on Days</label>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:4px">
          <?php foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dw): ?>
          <label style="display:flex;align-items:center;gap:4px;font-size:.8rem;background:#f1f5f9;padding:5px 10px;border-radius:7px">
            <input type="checkbox" name="bulk-day" value="<?= $dw ?>" checked> <?= $dw ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <div class="hm-mfooter">
      <button class="hm-btn hm-btn-ghost" onclick="closeBulk()">Cancel</button>
      <button class="hm-btn hm-btn-amber" onclick="applyBulkRates()"><i class="bi bi-check2-all"></i> Apply Rates</button>
    </div>
  </div>
</div>

<!-- MODAL: Create Booking -->
<div class="hm-overlay" id="modal-booking">
  <div class="hm-modal wide">
    <div class="hm-modal-hdr">
      <h3><i class="bi bi-calendar-plus" style="color:var(--hm-brand)"></i> Create New Booking</h3>
      <p>Fill guest details and select room to confirm booking.</p>
    </div>
    <div class="hm-modal-body">
      <input type="hidden" id="bk-hotel-id">
      <div class="hm-fgrid2">
        <div class="hm-frow"><label>Guest Name *</label><input type="text" id="bk-guest-name" placeholder="e.g. Rahul Sharma"></div>
        <div class="hm-frow"><label>Phone</label><input type="tel" id="bk-guest-phone" placeholder="+91 9876543210"></div>
      </div>
      <div class="hm-frow"><label>Email</label><input type="email" id="bk-guest-email" placeholder="guest@example.com"></div>
      <div class="hm-fgrid2">
        <div class="hm-frow"><label>Check-In *</label><input type="date" id="bk-checkin" value="<?= date('Y-m-d') ?>"></div>
        <div class="hm-frow"><label>Check-Out *</label><input type="date" id="bk-checkout" value="<?= date('Y-m-d', strtotime('+1 day')) ?>"></div>
      </div>
      <div class="hm-fgrid3">
        <div class="hm-frow"><label>Adults</label><input type="number" id="bk-adults" value="2" min="1" max="10"></div>
        <div class="hm-frow"><label>Children</label><input type="number" id="bk-children" value="0" min="0" max="10"></div>
        <div class="hm-frow"><label>Rooms Count</label><input type="number" id="bk-rooms-count" value="1" min="1" max="20"></div>
      </div>
      <div class="hm-fgrid2">
        <div class="hm-frow">
          <label>Room Category *</label>
          <select id="bk-room-cat"><option value="">-- Select Room --</option></select>
        </div>
        <div class="hm-frow">
          <label>Meal Plan</label>
          <select id="bk-meal-plan">
            <?php foreach($mealPlans as $code=>$label): ?>
            <option value="<?= $code ?>"><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="hm-fgrid2">
        <div class="hm-frow"><label>Extra Beds</label><input type="number" id="bk-extra-beds" value="0" min="0" max="3"></div>
        <div class="hm-frow">
          <label>Source</label>
          <select id="bk-source">
            <option value="direct">Direct</option><option value="agent">Agent</option>
            <option value="online">Online</option><option value="phone">Phone</option><option value="walk-in">Walk-in</option>
          </select>
        </div>
      </div>
      <div class="hm-frow"><label>Special Requests</label><textarea id="bk-special" rows="2" placeholder="Any special requirements..."></textarea></div>
      <div id="bk-amount-preview" style="background:#f0f7f5;border-radius:10px;padding:12px 16px;margin-top:8px;font-size:.84rem;color:var(--hm-teal-d);display:none">
        <i class="bi bi-calculator"></i> <span id="bk-amount-text"></span>
      </div>
    </div>
    <div class="hm-mfooter">
      <button class="hm-btn hm-btn-ghost" onclick="closeBookingModal()">Cancel</button>
      <button class="hm-btn hm-btn-brand" onclick="submitBooking()"><i class="bi bi-check2-circle"></i> Confirm Booking</button>
    </div>
  </div>
</div>

<?php
/* ── JS: Inline data injection ──────────────────────────────────────────── */
$mealPlanKeysJson = json_encode(array_keys($mealPlans));
$availDatesJson   = json_encode($availDates);
$calYearStr       = (int)$calYear;
$calMonthStr2     = (int)$calMonth;
?>
<script>
const mealPlanKeys = <?= $mealPlanKeysJson ?>;
const availDates   = <?= $availDatesJson ?>;
const API_ROOT     = '<?= rtrim(dirname($_SERVER['SCRIPT_NAME']), "\\/") ?>/ajax';
let calYearMap  = {};
let calMonthMap = {};

/* ── API Helper ─────────────────────────────────────────────────────────── */
async function api(url, data = null) {
  const endpoint = url.startsWith('http') ? url : `${API_ROOT}/${url.replace(/^\/+/, '')}`;
  const opts = {
    method: data ? 'POST' : 'GET',
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'Accept': 'application/json'
    },
    credentials: 'same-origin'
  };
  if (data) {
    opts.headers['Content-Type'] = 'application/json';
    opts.body = JSON.stringify(data);
  }
  try {
    const res  = await fetch(endpoint, opts);
    const json = await res.json();
    const ok   = json.status === 'success' || json.success === true;
    if (!res.ok || !ok) throw new Error(json.message || 'Request failed');
    return json;
  } catch(e) {
    showToast(e.message || 'Request failed','err');
    throw e;
  }
}

/* ── Toast ──────────────────────────────────────────────────────────────── */
let toastTimer = null;
function showToast(msg, type='ok') {
  const t = document.getElementById('toast');
  const s = document.getElementById('toast-msg');
  s.textContent = msg;
  t.className = 'hm-toast show ' + type;
  if(toastTimer) clearTimeout(toastTimer);
  toastTimer = setTimeout(() => t.className = 'hm-toast', 3800);
}

/* ── Tabs ───────────────────────────────────────────────────────────────── */
function switchTab(el, panelId) {
  const card = el.closest('.hm-hcard');
  card.querySelectorAll('.hm-tab').forEach(t => t.classList.remove('active'));
  card.querySelectorAll('.hm-tab-panel').forEach(p => p.classList.remove('active'));
  el.classList.add('active');
  const panel = document.getElementById(panelId);
  if (panel) panel.classList.add('active');
}

/* ── Escape HTML ────────────────────────────────────────────────────────── */
function esc(v) { const d=document.createElement('div'); d.textContent=v??''; return d.innerHTML; }

/* ══════════════════════════════════════════════════════════════════════════
   HOTEL WIZARD
   ════════════════════════════════════════════════════════════════════════ */
function openHotelModal(hotelId = null) {
  const editId = document.getElementById('hotel-edit-id');
  const title = document.getElementById('hotel-modal-title');
  const description = document.getElementById('hotel-modal-description');
  const save = document.getElementById('wiz-save');
  editId.value = hotelId || '';
  ['wiz-name','wiz-code','wiz-contact','wiz-city','wiz-state','wiz-address','wiz-pincode','wiz-phone','wiz-email','wiz-website','wiz-desc','wiz-image-urls'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.value = '';
  });
  document.getElementById('wiz-category').value = '3 Star';
  if (hotelId) {
    const card = document.getElementById('hcard-' + hotelId);
    const hotel = card ? JSON.parse(card.dataset.hotel || '{}') : {};
    document.getElementById('wiz-name').value = hotel.name || '';
    document.getElementById('wiz-code').value = hotel.hotel_code || '';
    document.getElementById('wiz-contact').value = hotel.contact_details || '';
    document.getElementById('wiz-city').value = hotel.city || '';
    document.getElementById('wiz-state').value = hotel.state || '';
    document.getElementById('wiz-address').value = hotel.address || '';
    document.getElementById('wiz-pincode').value = hotel.pin_code || '';
    document.getElementById('wiz-phone').value = hotel.phone || '';
    document.getElementById('wiz-email').value = hotel.email || '';
    document.getElementById('wiz-website').value = hotel.website || '';
    document.getElementById('wiz-category').value = hotel.property_category || '3 Star';
    document.getElementById('wiz-desc').value = hotel.description || '';
    const imageUrls = hotel.image_urls ? (Array.isArray(hotel.image_urls) ? hotel.image_urls : JSON.parse(hotel.image_urls || '[]')) : [];
    document.getElementById('wiz-image-urls').value = imageUrls.join('\n');
    title.innerHTML = '<i class="bi bi-pencil-square"></i> Edit Hotel';
    description.textContent = 'Update hotel master details. Room categories, rates, and availability remain unchanged.';
    save.innerHTML = '<i class="bi bi-floppy2"></i> Save Changes';
  } else {
    title.innerHTML = '<i class="bi bi-building-add"></i> Add New Hotel';
    description.textContent = 'Hotel master details only. Rooms, rates, and availability are managed later.';
    save.innerHTML = '<i class="bi bi-check2-circle"></i> Create Hotel';
  }
  document.getElementById('modal-hotel').classList.add('open');
  goWizStep(1);
}
function closeHotelModal() { document.getElementById('modal-hotel').classList.remove('open'); }

function goWizStep(step) {
  document.querySelectorAll('.hm-wizard-step').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.hm-dot').forEach((d,i) => d.classList.toggle('active', i < step));
  const stepPanel = document.getElementById('wiz-s' + step);
  if (stepPanel) stepPanel.classList.add('active');
  const stepNum = document.getElementById('wiz-step-num');
  if (stepNum) stepNum.textContent = String(step);
  const prev = document.getElementById('wiz-prev');
  const next = document.getElementById('wiz-next');
  const save = document.getElementById('wiz-save');
  if (prev) prev.style.display = 'none';
  if (next) next.style.display = 'none';
  if (save) save.style.display = '';
}

async function submitHotelWizard() {
  const name  = document.getElementById('wiz-name').value.trim();
  const city  = document.getElementById('wiz-city').value.trim();
  if (!name) { showToast('Hotel name required','err'); goWizStep(1); document.getElementById('wiz-name').focus(); return; }
  if (!city) { showToast('City required','err'); goWizStep(1); document.getElementById('wiz-city').focus(); return; }
  const imageUrlsRaw = document.getElementById('wiz-image-urls').value.trim();
  const image_urls = imageUrlsRaw === ''
    ? []
    : imageUrlsRaw.split(/\r?\n|,/).map(v => v.trim()).filter(Boolean);

  const payload = {
    name,
    hotel_code: document.getElementById('wiz-code').value.trim(),
    city,
    state: document.getElementById('wiz-state').value.trim(),
    address: document.getElementById('wiz-address').value.trim(),
    pincode: document.getElementById('wiz-pincode').value.trim(),
    phone: document.getElementById('wiz-phone').value.trim(),
    contact_details: document.getElementById('wiz-contact').value.trim(),
    email: document.getElementById('wiz-email').value.trim(),
    website: document.getElementById('wiz-website').value.trim(),
    property_category: document.getElementById('wiz-category').value,
    description: document.getElementById('wiz-desc').value.trim(),
    image_urls
  };

  try {
    const editId = parseInt(document.getElementById('hotel-edit-id').value || '0', 10);
    const res = await api(editId ? 'update_hotel.php' : 'save_hotel.php', editId ? {...payload, hotel_id: editId} : payload);
    if (res.status === 'success' || res.success) {
      localStorage.setItem('hm_toast_msg', `Hotel "${name}" ${editId ? 'updated' : 'created'} ✓`);
      closeHotelModal();
      location.reload();
    }
  } catch(e) { /* handled by api() */ }
}

/* ══════════════════════════════════════════════════════════════════════════
   ROOM MODAL
   ════════════════════════════════════════════════════════════════════════ */
let ebOn = false;
function toggleEbFields() {
  ebOn = document.getElementById('rm-eb-on').checked;
  document.getElementById('rm-eb-fields').style.display = ebOn ? '' : 'none';
}
function openRoomModal(hotelId, roomId) {
  document.getElementById('rm-hotel-id').value = hotelId;
  document.getElementById('rm-room-id').value  = roomId || '';
  document.getElementById('rm-title').textContent = roomId ? '✏️ Edit Room Category' : '🚪 Add Room Category';
  mealPlanKeys.forEach(c => { const el = document.getElementById('rmp-'+c); if(el) el.value = ''; });
  ['rm-name','rm-size'].forEach(id => { const el=document.getElementById(id); if(el) el.value=''; });
  document.getElementById('rm-total').value = 1;
  document.getElementById('rm-avail').value = 1;
  document.getElementById('rm-bed').value   = 'Double';
  document.getElementById('rm-eb-on').checked  = false;
  document.getElementById('rm-eb-price').value = 0;
  document.getElementById('rm-eb-max').value   = 1;
  document.getElementById('rm-eb-fields').style.display = 'none';
  ebOn = false;

  if (roomId) {
    const row = document.getElementById('rrow-' + roomId);
    if (row) {
      document.getElementById('rm-name').value  = row.dataset.name  || '';
      document.getElementById('rm-total').value = row.dataset.total || 1;
      document.getElementById('rm-avail').value = row.dataset.avail || 0;
      document.getElementById('rm-size').value  = row.dataset.size  || '';
      document.getElementById('rm-bed').value   = row.dataset.bed   || 'Double';
      ebOn = row.dataset.ebon === '1';
      document.getElementById('rm-eb-on').checked    = ebOn;
      document.getElementById('rm-eb-price').value   = row.dataset.ebprice || 0;
      document.getElementById('rm-eb-max').value     = row.dataset.ebmax   || 0;
      document.getElementById('rm-eb-fields').style.display = ebOn ? '' : 'none';
      try {
        const prices = JSON.parse(row.dataset.prices || '{}');
        mealPlanKeys.forEach(c => { const el=document.getElementById('rmp-'+c); if(el) el.value = prices[c]||''; });
      } catch(e) {}
    }
  }
  document.getElementById('modal-room').classList.add('open');
  setTimeout(() => document.getElementById('rm-name').focus(), 150);
}
function closeRoomModal() { document.getElementById('modal-room').classList.remove('open'); }

async function saveRoomModal() {
  const hotelId = parseInt(document.getElementById('rm-hotel-id').value);
  const roomId  = document.getElementById('rm-room-id').value;
  const name    = document.getElementById('rm-name').value.trim();
  if (!name) { showToast('Room name required','err'); document.getElementById('rm-name').focus(); return; }

  const prices = {};
  mealPlanKeys.forEach(c => { const v = parseFloat(document.getElementById('rmp-'+c)?.value||0); if(v>0) prices[c]=v; });

  const payload = {
    name, bed_type: document.getElementById('rm-bed').value,
    room_size: document.getElementById('rm-size').value.trim(),
    total_rooms:    parseInt(document.getElementById('rm-total').value)||1,
    available_rooms:parseInt(document.getElementById('rm-avail').value)||0,
    extra_bed_allowed: ebOn ? 1 : 0,
    extra_bed_price:   parseFloat(document.getElementById('rm-eb-price').value||0),
    max_extra_beds:    parseInt(document.getElementById('rm-eb-max').value||0),
    prices
  };

  try {
    if (roomId) {
      payload.room_id = parseInt(roomId);
      await api('update_room_category.php', payload);
    } else {
      payload.hotel_id = hotelId;
      await api('save_room_category.php', payload);
    }
    showToast(roomId ? 'Room updated ✓' : 'Room added ✓', 'ok');
    closeRoomModal();
    location.reload();
  } catch(e) { /* handled */ }
}

async function removeRoom(roomId, hotelId) {
  if (!confirm('Delete this room category? All rates and availability data will be removed.')) return;
  try {
    await api('delete_room_category.php', { room_id: roomId });
    const row = document.getElementById('rrow-' + roomId);
    if (row) { row.style.opacity='0'; row.style.transition='opacity .3s'; setTimeout(()=>{ row.remove(); location.reload(); },300); }
    showToast('Room deleted ✓','ok');
  } catch(e) { /* handled */ }
}

async function deleteHotel(hotelId) {
  if (!confirm('Delete this hotel and all related data? This action cannot be undone.')) return;
  try {
    await api('delete_hotel.php', { hotel_id: hotelId });
    const card = document.getElementById('hcard-' + hotelId);
    if (card) { card.style.opacity='0'; card.style.transition='opacity .4s'; setTimeout(()=>{ card.remove(); location.reload(); },400); }
    showToast('Hotel deleted ✓','ok');
  } catch(e) { /* handled */ }
}

/* ══════════════════════════════════════════════════════════════════════════
   AVAILABILITY
   ════════════════════════════════════════════════════════════════════════ */
const loadedAvailHotels = new Set();
const pendingSaves = {};
function updateAvailCellClass(inp) {
  const v = parseInt(inp.value||0);
  const td = inp.closest('td');
  if (!td) return;
  td.className = v === 0 ? 'sold' : (v < 3 ? 'partial' : '');
  td.classList.add('cell-dirty');
}

async function loadAvailability(hotelId, force=false) {
  if (loadedAvailHotels.has(hotelId) && !force) return;
  const from = availDates[0], to = availDates[availDates.length-1];
  const grid = document.getElementById('agrid-'+hotelId);
  if (grid) grid.closest('.hm-tab-panel').style.opacity = '0.5';
  try {
    const res = await api(`get_listing_data.php?type=availability&hotel_id=${hotelId}&from_date=${from}&to_date=${to}`);
    const records = res.data?.data || res.data || [];
    records.forEach(rec => {
      const rid = rec.room_category_id;
      const dt  = rec.availability_date || rec.date;
      const ai  = document.querySelector(`.avail-input[data-room-id="${rid}"][data-date="${dt}"]`);
      const bi  = document.querySelector(`.blocked-input[data-room-id="${rid}"][data-date="${dt}"]`);
      const bv  = document.querySelector(`.booked-val[data-room-id="${rid}"][data-date="${dt}"]`);
      if (ai) { ai.value = rec.available_rooms; updateAvailCellClass(ai); }
      if (bi) bi.value = rec.blocked_rooms;
      if (bv) bv.textContent = rec.booked_rooms;
    });
    loadedAvailHotels.add(hotelId);
  } catch(e) { /* handled */ }
  if (grid) grid.closest('.hm-tab-panel').style.opacity = '1';
}

function autoSaveCell(hotelId, roomId, date) {
  const key = hotelId+'-'+roomId+'-'+date;
  if (pendingSaves[key]) clearTimeout(pendingSaves[key]);
  const td = document.querySelector(`.avail-input[data-room-id="${roomId}"][data-date="${date}"]`)?.closest('td');
  if (td) td.classList.add('cell-saving');
  pendingSaves[key] = setTimeout(async () => {
    const ai = document.querySelector(`.avail-input[data-room-id="${roomId}"][data-date="${date}"]`);
    const bi = document.querySelector(`.blocked-input[data-room-id="${roomId}"][data-date="${date}"]`);
    const bv = document.querySelector(`.booked-val[data-room-id="${roomId}"][data-date="${date}"]`);
    if (!ai) return;
    try {
      await api('save_availability.php', { updates: [{
        room_id: parseInt(roomId), date: date, availability_date: date,
        hotel_id: hotelId,
        available_rooms: parseInt(ai.value||0),
        blocked_rooms:   parseInt(bi?.value||0),
        booked_rooms:    parseInt(bv?.textContent||0)
      }]});
      if (td) { td.classList.remove('cell-saving','cell-dirty'); td.classList.add('cell-saved'); setTimeout(()=>td.classList.remove('cell-saved'),900); }
    } catch(e) {
      if (td) td.classList.remove('cell-saving');
    }
  }, 400);
}

async function saveAvailability(hotelId) {
  const updates = [];
  document.querySelectorAll(`#agrid-${hotelId} .avail-input`).forEach(inp => {
    const rid = inp.dataset.roomId;
    const dt  = inp.dataset.date;
    const bl  = document.querySelector(`.blocked-input[data-room-id="${rid}"][data-date="${dt}"]`);
    const bk  = document.querySelector(`.booked-val[data-room-id="${rid}"][data-date="${dt}"]`);
    updates.push({ room_id: parseInt(rid), date: dt, availability_date: dt,
      hotel_id: hotelId,
      available_rooms: parseInt(inp.value||0),
      blocked_rooms:   parseInt(bl?.value||0),
      booked_rooms:    parseInt(bk?.textContent||0) });
  });
  if (!updates.length) { showToast('No data to save','err'); return; }
  try {
    await api('save_availability.php', { updates });
    showToast('Availability saved ✓','ok');
    loadedAvailHotels.delete(hotelId);
    loadAvailability(hotelId, true);
  } catch(e) { /* handled */ }
}

/* ══════════════════════════════════════════════════════════════════════════
   RATE CALENDAR
   ════════════════════════════════════════════════════════════════════════ */
const loadedRateHotels = new Set();

function initRateCal(hotelId) {
  if (!calYearMap[hotelId])  calYearMap[hotelId]  = <?= $calYearStr ?>;
  if (!calMonthMap[hotelId]) calMonthMap[hotelId] = <?= $calMonthStr2 ?>;
  loadRateCal(hotelId);
}
function prevMonth(hotelId) {
  calMonthMap[hotelId]--;
  if (calMonthMap[hotelId] < 1) { calMonthMap[hotelId]=12; calYearMap[hotelId]--; }
  loadRateCal(hotelId);
}
function nextMonth(hotelId) {
  calMonthMap[hotelId]++;
  if (calMonthMap[hotelId] > 12) { calMonthMap[hotelId]=1; calYearMap[hotelId]++; }
  loadRateCal(hotelId);
}

async function loadRateCal(hotelId) {
  const roomSel = document.getElementById('cal-room-'+hotelId);
  const planSel = document.getElementById('cal-plan-'+hotelId);
  if (!roomSel || !planSel) return;
  const roomId = parseInt(roomSel.value || '0', 10);
  const plan   = planSel.value;
  const year   = calYearMap[hotelId]  || <?= $calYearStr ?>;
  const month  = calMonthMap[hotelId] || <?= $calMonthStr2 ?>;

  if (!roomId || Number.isNaN(roomId)) {
    const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    const titleEl = document.getElementById('cal-title-'+hotelId);
    if (titleEl) titleEl.textContent = monthNames[month-1] + ' ' + year;
    const tbody = document.getElementById('rcal-body-'+hotelId);
    if (tbody) {
      tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:22px;color:#64748b;">No room categories yet. Add a room category first to manage rate calendar.</td></tr>';
    }
    return;
  }

  try {
    const res        = await api(`get_listing_data.php?type=rates&room_id=${roomId}&meal_plan=${plan}&year=${year}&month=${month}`);
    const ratesData  = res.data?.data || res.data || [];
    const basePrice  = res.data?.base_price ?? 0;
    const ratesMap   = {};
    ratesData.forEach(r => { ratesMap[r.rate_date || r.date] = parseFloat(r.price ?? r.date_wise_price ?? 0) || basePrice; });

    const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    document.getElementById('cal-title-'+hotelId).textContent = monthNames[month-1] + ' ' + year;

    const daysInMonth = new Date(year, month, 0).getDate();
    const firstDow    = new Date(year, month-1, 1).getDay();
    const today       = new Date().toISOString().split('T')[0];
    const tbody       = document.getElementById('rcal-body-'+hotelId);
    let html = '<tr>';
    for (let b=0; b<firstDow; b++) html += '<td class="other-month"></td>';
    let col = firstDow;
    for (let day=1; day<=daysInMonth; day++) {
      const ds    = `${year}-${String(month).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
      const dow   = new Date(ds).getDay();
      const price = ratesMap[ds] !== undefined ? ratesMap[ds] : basePrice;
      const cls   = `${ds===today?'today-cell':''} ${dow===5?'fri-cell':''} ${dow===6?'sat-cell':''} ${dow===0?'sun-cell':''}`.trim();
      html += `<td class="${cls}">
        <div class="hm-cal-day-num">${day}</div>
        <input class="hm-cal-price" data-room-id="${roomId}" data-plan="${plan}" data-date="${ds}" value="${price > 0 ? price : ''}" type="number" min="0" step="50" placeholder="Base">
      </td>`;
      col++;
      if (col % 7 === 0 && day < daysInMonth) html += '</tr><tr>';
    }
    while (col % 7 !== 0) { html += '<td class="other-month"></td>'; col++; }
    html += '</tr>';
    tbody.innerHTML = html;
  } catch(e) { /* handled */ }
}

function applyDayRule(hotelId) {
  const rate = parseFloat(document.getElementById('dr-val-'+hotelId).value||0);
  const chkFri = document.getElementById('dr-fri-'+hotelId).checked;
  const chkSat = document.getElementById('dr-sat-'+hotelId).checked;
  const chkSun = document.getElementById('dr-sun-'+hotelId).checked;
  if (rate <= 0) { showToast('Enter a valid rate','err'); return; }
  document.querySelectorAll(`#rcal-body-${hotelId} .hm-cal-price`).forEach(inp => {
    const ds  = inp.dataset.date;
    const dow = new Date(ds).getDay();
    if ((chkFri && dow===5) || (chkSat && dow===6) || (chkSun && dow===0)) inp.value = rate;
  });
  showToast(`Day rule applied (₹${rate.toLocaleString()}) ✓`,'ok');
}

async function saveRates(hotelId) {
  const rates = [];
  document.querySelectorAll(`#rcal-body-${hotelId} .hm-cal-price`).forEach(inp => {
    const price = parseFloat(inp.value||0);
    if (price > 0) rates.push({ room_id: parseInt(inp.dataset.roomId), meal_plan: inp.dataset.plan, date: inp.dataset.date, price });
  });
  if (!rates.length) { showToast('No rates to save','err'); return; }
  try {
    const result = await api('save_room_price.php', { rates });
    const skipped = Number(result.data?.skipped || 0);
    showToast(skipped ? `Rates saved, ${skipped} invalid row(s) skipped` : 'Rates saved ✓','ok');
    loadRateCal(hotelId);
  } catch(e) { /* handled */ }
}

async function copyRatesToAll(hotelId) {
  if (!confirm('Copy these rates to all rooms in this hotel? Existing rates will be overwritten.')) return;
  const roomSel = document.getElementById('cal-room-'+hotelId);
  const planSel = document.getElementById('cal-plan-'+hotelId);
  const plan    = planSel.value;
  const rates   = [];
  document.querySelectorAll(`#rcal-body-${hotelId} .hm-cal-price`).forEach(inp => {
    const price = parseFloat(inp.value||0);
    if (price > 0) rates.push({ room_id: 0, meal_plan: plan, date: inp.dataset.date, price, forAll: true, hotel_id: hotelId });
  });
  if (!rates.length) { showToast('No rates to copy','err'); return; }
  // Get all room options
  const allRooms = Array.from(roomSel.options).map(o => parseInt(o.value, 10)).filter(v=>v>0);
  if (!allRooms.length) { showToast('No room categories available','err'); return; }
  const expanded = [];
  rates.forEach(r => allRooms.forEach(rid => expanded.push({...r, room_id: rid})));
  try {
    await api('save_room_price.php', { rates: expanded });
    showToast('Rates copied to all rooms ✓','ok');
  } catch(e) { /* handled */ }
}

/* ══════════════════════════════════════════════════════════════════════════
   BULK RATES
   ════════════════════════════════════════════════════════════════════════ */
function openBulk(hotelId) {
  document.getElementById('bulk-hotel-id').value = hotelId;
  const sel = document.getElementById('bulk-room');
  sel.innerHTML = '<option value="all">All Rooms</option>';
  document.querySelectorAll(`#hcard-${hotelId} [id^="rrow-"]`).forEach(row => {
    const opt = document.createElement('option');
    opt.value = row.id.replace('rrow-','');
    opt.textContent = row.querySelector('td:first-child')?.textContent?.trim()||'Room';
    sel.appendChild(opt);
  });
  document.getElementById('modal-bulk').classList.add('open');
}
function closeBulk() { document.getElementById('modal-bulk').classList.remove('open'); }

async function applyBulkRates() {
  const hotelId  = parseInt(document.getElementById('bulk-hotel-id').value);
  const roomId   = document.getElementById('bulk-room').value;
  const mealPlan = document.getElementById('bulk-plan').value;
  const fromDate = document.getElementById('bulk-from').value;
  const toDate   = document.getElementById('bulk-to').value;
  const price    = parseFloat(document.getElementById('bulk-price').value||0);
  const days     = Array.from(document.querySelectorAll('input[name="bulk-day"]:checked')).map(c=>c.value);
  if (!fromDate || !toDate || fromDate > toDate) { showToast('Invalid date range','err'); return; }
  if (price <= 0) { showToast('Enter a valid price','err'); return; }
  try {
    const res = await api('bulk_rate_update.php', {hotel_id:hotelId,room_id:roomId,meal_plan:mealPlan,from_date:fromDate,to_date:toDate,price,days_of_week:days});
    showToast(`Bulk rates applied: ${res.data?.count||0} records ✓`,'ok');
    closeBulk();
    const calRoomSel = document.getElementById('cal-room-'+hotelId);
    if (calRoomSel) loadRateCal(hotelId);
  } catch(e) { /* handled */ }
}

/* ══════════════════════════════════════════════════════════════════════════
   BOOKINGS
   ════════════════════════════════════════════════════════════════════════ */
const loadedBookingHotels = new Set();
const statusColor = {confirmed:'hm-b-green',pending:'hm-b-orange',cancelled:'hm-b-gray',checked_in:'hm-b-blue',checked_out:'hm-b-purple'};

async function loadBookings(hotelId, force=false) {
  if (loadedBookingHotels.has(hotelId) && !force) return;
  const container = document.getElementById('bk-list-'+hotelId);
  if (!container) return;
  container.innerHTML = '<div style="text-align:center;padding:24px;color:var(--hm-muted)"><i class="bi bi-hourglass-split"></i> Loading...</div>';
  try {
    const res      = await api(`get_listing_data.php?type=bookings&hotel_id=${hotelId}`);
    const bookings = res.data?.bookings || [];
    const badge    = document.getElementById('bk-badge-'+hotelId);
    const active   = bookings.filter(b=>b.booking_status!=='cancelled').length;
    if (badge) { badge.textContent = active; badge.style.display = active > 0 ? '' : 'none'; }
    if (!bookings.length) {
      container.innerHTML = '<div style="text-align:center;padding:30px;color:var(--hm-muted)"><i class="bi bi-inbox" style="font-size:2rem"></i><br><br>No bookings yet. Click "New Booking".</div>';
      return;
    }
    const rows = bookings.map(b=>`
      <tr id="bkrow-${b.id}">
        <td><strong style="color:var(--hm-brand)">${esc(b.booking_number)}</strong></td>
        <td><strong>${esc(b.guest_name)}</strong><br><small style="color:var(--hm-muted)">${esc(b.guest_phone||'')}</small></td>
        <td>${esc(b.room_name||'—')}</td>
        <td style="white-space:nowrap"><small>${esc(b.checkin_date)}</small><br><small>→ ${esc(b.checkout_date)}</small></td>
        <td style="text-align:center">${b.total_nights||'?'}N / ${b.rooms_count||1}R</td>
        <td style="text-align:center"><span class="hm-badge hm-b-blue">${esc(b.meal_plan_code||'EP')}</span></td>
        <td style="text-align:right"><strong>₹${Number(b.total_amount).toLocaleString('en-IN')}</strong></td>
        <td style="text-align:center">
          <span class="hm-badge ${statusColor[b.booking_status]||'hm-b-gray'}">${b.booking_status}</span>
        </td>
        <td style="text-align:center">
          ${b.booking_status!=='cancelled'?`
          <div style="display:flex;gap:4px;justify-content:center">
            <button class="hm-btn hm-btn-ghost hm-btn-xs" onclick="changeBookingStatus(${b.id},'checked_in',${hotelId})" title="Check In"><i class="bi bi-door-open"></i></button>
            <button class="hm-btn hm-btn-ghost hm-btn-xs" onclick="changeBookingStatus(${b.id},'checked_out',${hotelId})" title="Check Out"><i class="bi bi-box-arrow-right"></i></button>
            <button class="hm-btn hm-btn-coral hm-btn-xs" onclick="cancelBooking(${b.id},${hotelId})" title="Cancel"><i class="bi bi-x-circle"></i></button>
          </div>` : '<span style="color:var(--hm-muted);font-size:.72rem">Cancelled</span>'}
        </td>
      </tr>`).join('');
    container.innerHTML = `
      <div style="overflow-x:auto;border-radius:10px;border:1px solid var(--hm-border)">
        <table class="hm-rtable">
          <thead><tr>
            <th>Ref No.</th><th>Guest</th><th>Room</th><th>Dates</th>
            <th style="text-align:center">Nights/Rooms</th><th style="text-align:center">Plan</th>
            <th style="text-align:right">Amount</th><th style="text-align:center">Status</th><th style="text-align:center">Actions</th>
          </tr></thead>
          <tbody>${rows}</tbody>
        </table>
      </div>`;
    loadedBookingHotels.add(hotelId);
  } catch(e) {
    container.innerHTML = `<div style="text-align:center;padding:20px;color:var(--hm-coral)"><i class="bi bi-exclamation-triangle"></i> ${esc(e.message)}</div>`;
  }
}

async function changeBookingStatus(bookingId, status, hotelId) {
  const lbl = {checked_in:'Check In',checked_out:'Check Out'};
  if (!confirm(`Confirm: ${lbl[status]||status} this booking?`)) return;
  try {
    await api('update_booking.php', {booking_id:bookingId,status});
    showToast(`${lbl[status]||status} confirmed ✓`,'ok');
    loadedBookingHotels.delete(hotelId);
    loadBookings(hotelId, true);
  } catch(e) { /* handled */ }
}

async function cancelBooking(bookingId, hotelId) {
  if (!confirm('Cancel this booking? Room availability will be restored.')) return;
  try {
    await api('cancel_booking.php', {booking_id:bookingId});
    showToast('Booking cancelled. Rooms restored ✓','ok');
    loadedBookingHotels.delete(hotelId);
    loadBookings(hotelId, true);
    location.reload();
  } catch(e) { /* handled */ }
}

/* ── Create Booking Modal ───────────────────────────────────────────────── */
function openCreateBooking(hotelId) {
  document.getElementById('bk-hotel-id').value = hotelId;
  const sel = document.getElementById('bk-room-cat');
  sel.innerHTML = '<option value="">-- Select Room --</option>';
  document.querySelectorAll(`#hcard-${hotelId} [id^="rrow-"]`).forEach(row => {
    const opt  = document.createElement('option');
    opt.value  = row.id.replace('rrow-','');
    opt.textContent = row.querySelector('td:first-child')?.textContent?.trim()||'Room';
    sel.appendChild(opt);
  });
  document.getElementById('bk-amount-preview').style.display = 'none';
  ['bk-guest-name','bk-guest-phone','bk-guest-email','bk-special'].forEach(id => { const el=document.getElementById(id); if(el) el.value=''; });
  document.getElementById('bk-adults').value  = 2;
  document.getElementById('bk-children').value= 0;
  document.getElementById('bk-rooms-count').value = 1;
  document.getElementById('bk-extra-beds').value  = 0;
  document.getElementById('bk-checkin').value  = new Date().toISOString().split('T')[0];
  const tom = new Date(); tom.setDate(tom.getDate()+1);
  document.getElementById('bk-checkout').value = tom.toISOString().split('T')[0];
  document.getElementById('modal-booking').classList.add('open');
  setTimeout(()=>document.getElementById('bk-guest-name').focus(),200);
}
function closeBookingModal() { document.getElementById('modal-booking').classList.remove('open'); }

async function submitBooking() {
  const hotelId   = parseInt(document.getElementById('bk-hotel-id').value);
  const guestName = document.getElementById('bk-guest-name').value.trim();
  const roomCatId = parseInt(document.getElementById('bk-room-cat').value)||0;
  const checkin   = document.getElementById('bk-checkin').value;
  const checkout  = document.getElementById('bk-checkout').value;
  if (!guestName)          { showToast('Guest name required','err'); document.getElementById('bk-guest-name').focus(); return; }
  if (!roomCatId)          { showToast('Select a room category','err'); return; }
  if (!checkin||!checkout) { showToast('Check-in/out required','err'); return; }
  if (checkin >= checkout) { showToast('Check-out must be after check-in','err'); return; }
  try {
    const res = await api('create_booking.php', {
      hotel_id: hotelId, room_category_id: roomCatId,
      guest_name: guestName,
      guest_phone: document.getElementById('bk-guest-phone').value.trim(),
      guest_email: document.getElementById('bk-guest-email').value.trim(),
      checkin_date: checkin, checkout_date: checkout,
      adults:      parseInt(document.getElementById('bk-adults').value)||1,
      children:    parseInt(document.getElementById('bk-children').value)||0,
      rooms_count: parseInt(document.getElementById('bk-rooms-count').value)||1,
      extra_beds:  parseInt(document.getElementById('bk-extra-beds').value)||0,
      meal_plan:   document.getElementById('bk-meal-plan').value,
      source:      document.getElementById('bk-source').value,
      special_requests: document.getElementById('bk-special').value.trim()
    });
    const d = res.data || {};
    document.getElementById('bk-amount-preview').style.display = 'block';
    document.getElementById('bk-amount-text').textContent = `✓ Booking ${d.booking_number||d.booking_ref} confirmed! Total: ₹${Number(d.total_amount||0).toLocaleString('en-IN')} for ${d.nights||1} night(s)`;
    showToast(res.message||'Booking confirmed ✓','ok');
    loadedBookingHotels.delete(hotelId);
    setTimeout(()=>{ closeBookingModal(); location.reload(); }, 2200);
  } catch(e) { /* handled */ }
}

/* ── On load: show stored toast ─────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  const msg  = localStorage.getItem('hm_toast_msg');
  const type = localStorage.getItem('hm_toast_type') || 'ok';
  if (msg) { showToast(msg, type); localStorage.removeItem('hm_toast_msg'); localStorage.removeItem('hm_toast_type'); }

  // Debounced enterprise search preview (AJAX, 300ms).
  const form = document.getElementById('listingFilterForm');
  const summary = document.getElementById('liveSearchSummary');
  if (!form || !summary) return;

  const fields = ['flt-hotel-name','flt-hotel-code','flt-city','flt-state','flt-contact','flt-email'];
  let t = null;

  const runLiveSearch = async () => {
    const params = new URLSearchParams({
      hotel_name: document.getElementById('flt-hotel-name')?.value?.trim() || '',
      hotel_code: document.getElementById('flt-hotel-code')?.value?.trim() || '',
      city: document.getElementById('flt-city')?.value?.trim() || '',
      state: document.getElementById('flt-state')?.value?.trim() || '',
      contact_number: document.getElementById('flt-contact')?.value?.trim() || '',
      email: document.getElementById('flt-email')?.value?.trim() || '',
      sort_by: document.getElementById('flt-sort-by')?.value || 'created_at',
      sort_dir: document.getElementById('flt-sort-dir')?.value || 'desc',
      per_page: document.getElementById('flt-per-page')?.value || '20',
      page: '1'
    });

    try {
      const res = await api(`get_hotels.php?${params.toString()}`);
      const data = res.data || {};
      const total = data.pagination?.total ?? 0;
      const rows = data.hotels || [];
      const sample = rows.slice(0, 3).map(h => `${h.name} (${h.hotel_code})`).join(' | ');
      summary.textContent = total > 0
        ? `Live Search: ${total} match(es). ${sample !== '' ? 'Top: ' + sample : ''}`
        : 'Live Search: no matching hotels.';
    } catch (e) {
      summary.textContent = 'Live Search: unable to fetch results.';
    }
  };

  fields.forEach((id) => {
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('input', () => {
      if (t) clearTimeout(t);
      t = setTimeout(runLiveSearch, 300);
    });
  });

  ['flt-sort-by','flt-sort-dir','flt-per-page'].forEach((id) => {
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('change', () => {
      document.getElementById('flt-page').value = '1';
      runLiveSearch();
    });
  });
});
</script>

<script>
/* ── Sidebar Toggle ────────────────────────────────────────────────────── */
document.getElementById('mobileMenuBtn')?.addEventListener('click', () => {
    document.getElementById('adminSidebar').classList.add('open');
    document.getElementById('sidebarBackdrop').classList.add('show');
});
document.getElementById('sidebarCloseBtn')?.addEventListener('click', () => {
    document.getElementById('adminSidebar').classList.remove('open');
    document.getElementById('sidebarBackdrop').classList.remove('show');
});
document.getElementById('sidebarBackdrop')?.addEventListener('click', () => {
    document.getElementById('adminSidebar').classList.remove('open');
    document.getElementById('sidebarBackdrop').classList.remove('show');
});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
