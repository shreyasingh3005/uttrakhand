<?php
/* ═══════════════════════════════════════════════════════════════════════════
   employee-listings.php — Hotel Listing View for Employees
   Shows all hotels, rooms, availability and bookings in read-only mode.
   Uttarakhand Ventures CRM
   ═══════════════════════════════════════════════════════════════════════════ */
require_once __DIR__ . '/includes/auth_session.php';
require_once __DIR__ . '/includes/db_connect.php';
// Allow both admin and employee
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php'); exit();
}
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$pdo     = $conn;

/* ── Data ────────────────────────────────────────────────────────────────── */
try {
    $hotels = $pdo->query("
        SELECT h.*,
               COUNT(DISTINCT hrc.id)               AS room_count,
               COALESCE(SUM(hrc.total_rooms),0)     AS total_rooms,
               COALESCE(SUM(hrc.booked_rooms),0)    AS booked_rooms,
               COALESCE(SUM(hrc.available_rooms),0) AS avail_rooms,
               COALESCE(SUM(hrc.blocked_rooms),0)   AS blocked_rooms
        FROM hotels h
        LEFT JOIN hotel_room_categories hrc ON hrc.hotel_id=h.id AND hrc.status='active'
        WHERE h.status='active'
        GROUP BY h.id ORDER BY h.id
    ")->fetchAll();
} catch (PDOException $e) { $hotels = []; }

try {
    $mpRows    = $pdo->query("SELECT id,code,name AS label FROM meal_plans WHERE status='active' ORDER BY sort_order")->fetchAll();
    $mealPlans = array_column($mpRows,'label','code');
} catch (PDOException $e) {
    $mealPlans = ['EP'=>'EP – Room Only','CP'=>'CP – Breakfast Included','MAP'=>'MAP – B+D','AP'=>'AP – All Meals','AI'=>'AI – All Inclusive'];
}

foreach ($hotels as &$hotel) {
    try {
        $rStmt = $pdo->prepare("SELECT hrc.*, GROUP_CONCAT(CONCAT(mp.code,':',rp.base_price) SEPARATOR ',') AS prices_str FROM hotel_room_categories hrc LEFT JOIN room_prices rp ON rp.room_category_id=hrc.id AND rp.rate_date IS NULL LEFT JOIN meal_plans mp ON mp.id=rp.meal_plan_id WHERE hrc.hotel_id=? AND hrc.status='active' GROUP BY hrc.id ORDER BY hrc.id");
        $rStmt->execute([$hotel['id']]);
        $rooms = $rStmt->fetchAll();
        foreach ($rooms as &$rm) {
            $prices = [];
            if ($rm['prices_str']) {
                foreach (explode(',', $rm['prices_str']) as $pair) {
                    [$code,$price] = array_pad(explode(':', $pair, 2), 2, 0);
                    if ($code) $prices[trim($code)] = (float)$price;
                }
            }
            $rm['prices'] = $prices;
        }
        unset($rm);
    } catch (PDOException $e) { $rooms = []; }

    // Today's bookings count
    try {
        $bCnt = $pdo->prepare("SELECT COUNT(*) FROM hotel_bookings WHERE hotel_id=? AND checkin_date<=CURDATE() AND checkout_date>CURDATE() AND booking_status NOT IN ('cancelled')");
        $bCnt->execute([$hotel['id']]);
        $hotel['todays_guests'] = (int)$bCnt->fetchColumn();
    } catch (PDOException $e) { $hotel['todays_guests'] = 0; }

    $hotel['rooms'] = $rooms ?? [];
}
unset($hotel);

$todayStr   = date('Y-m-d');
$totalHotels= count($hotels);
$totalRooms = array_sum(array_column($hotels,'total_rooms'));
$totalAvail = array_sum(array_column($hotels,'avail_rooms'));
$totalBooked= array_sum(array_column($hotels,'booked_rooms'));
$globalOcc  = $totalRooms > 0 ? round($totalBooked/$totalRooms*100) : 0;

$activePage      = 'employee-listings';
$pageTitle       = 'Hotel Listings';
$pageDescription = 'View hotel properties, room availability and bookings.';

$extraCss = <<<'CSS'
<style>
  .el-wrap {
    --el-teal:#2a9d8f; --el-navy:#1e3a5f; --el-coral:#e76f51; --el-amber:#e9c46a;
    --el-brand:#4f46e5; --el-border:#e2e8f0; --el-muted:#6b7280; --el-slate:#f8fafc;
    font-family:'Inter','Segoe UI',sans-serif; color:#1e293b; padding:24px;
  }
  .el-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:14px; margin-bottom:22px; }
  .el-stat  { background:#fff; border-radius:12px; padding:16px 18px; border:1px solid var(--el-border); box-shadow:0 2px 8px rgba(0,0,0,.05); }
  .el-stat .lbl { font-size:.7rem; font-weight:700; color:var(--el-muted); text-transform:uppercase; letter-spacing:.5px; }
  .el-stat .val { font-size:1.8rem; font-weight:800; margin-top:2px; }
  .el-toolbar { display:flex; align-items:center; gap:12px; margin-bottom:18px; flex-wrap:wrap; }
  .el-toolbar h2 { flex:1; font-size:1.3rem; font-weight:800; color:var(--el-navy); }
  .el-btn { display:inline-flex; align-items:center; gap:5px; padding:8px 16px; border-radius:9px; border:none; cursor:pointer; font-size:.8rem; font-weight:600; text-decoration:none; transition:.15s; }
  .el-btn-teal  { background:var(--el-teal); color:#fff; } .el-btn-teal:hover { background:#21867a; }
  .el-btn-ghost { background:#f1f5f9; color:var(--el-navy); border:1px solid var(--el-border); } .el-btn-ghost:hover { background:#e2e8f0; }
  .el-btn-sm { padding:6px 12px; font-size:.76rem; }
  .el-hcard { background:#fff; border-radius:16px; box-shadow:0 3px 15px rgba(0,0,0,.06); border:1px solid var(--el-border); margin-bottom:22px; }
  .el-hcard-hdr { display:flex; align-items:flex-start; justify-content:space-between; gap:14px; padding:18px 22px; border-bottom:1px solid var(--el-border); flex-wrap:wrap; }
  .el-hotel-name h3 { font-size:1.1rem; font-weight:800; color:var(--el-navy); margin:0 0 3px; }
  .el-hotel-name p  { font-size:.75rem; color:var(--el-muted); margin:0; }
  .el-hstats { display:flex; gap:16px; padding:12px 22px; border-bottom:1px solid var(--el-border); flex-wrap:wrap; }
  .el-hstat  { text-align:center; }
  .el-hstat .n { font-size:1.2rem; font-weight:800; }
  .el-hstat .l { font-size:.65rem; color:var(--el-muted); font-weight:600; text-transform:uppercase; }
  .el-badge { display:inline-flex; align-items:center; gap:2px; padding:2px 8px; border-radius:5px; font-size:.7rem; font-weight:700; }
  .el-b-green { background:#d1fae5; color:#065f46; }
  .el-b-red   { background:#fee2e2; color:#991b1b; }
  .el-b-orange{ background:#ffedd5; color:#9a3412; }
  .el-b-blue  { background:#dbeafe; color:#1e40af; }
  .el-b-gray  { background:#f1f5f9; color:#475569; }
  .el-b-purple{ background:#ede9fe; color:#4c1d95; }
  .el-rtable { width:100%; border-collapse:collapse; font-size:.79rem; }
  .el-rtable th { background:var(--el-navy); color:#fff; font-size:.71rem; font-weight:600; padding:9px 12px; text-align:left; }
  .el-rtable td { padding:9px 12px; border-bottom:1px solid var(--el-border); vertical-align:middle; }
  .el-rtable tr:hover td { background:#f8fafc; }
  .el-price-cell span { display:inline-block; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:4px; padding:1px 5px; margin:1px; font-size:.69rem; font-weight:700; color:#166534; }
  .el-tabs { display:flex; gap:2px; border-bottom:1px solid var(--el-border); padding:0 18px; }
  .el-tab  { padding:10px 14px; cursor:pointer; font-size:.78rem; font-weight:600; color:var(--el-muted); border-bottom:3px solid transparent; transition:.15s; display:flex; align-items:center; gap:5px; }
  .el-tab.active { color:var(--el-teal); border-bottom-color:var(--el-teal); }
  .el-tab-panel { display:none; padding:18px 22px; }
  .el-tab-panel.active { display:block; }
  .el-search { padding:8px 12px; border:1px solid var(--el-border); border-radius:9px; font-size:.82rem; font-family:inherit; min-width:200px; }
  .el-search:focus { outline:none; border-color:var(--el-teal); }
  .el-bk-table th { background:var(--el-navy); color:#fff; font-size:.71rem; font-weight:600; padding:9px 12px; text-align:left; }
  .el-bk-table td { padding:9px 12px; border-bottom:1px solid var(--el-border); font-size:.79rem; }
  .el-bk-table tr:hover td { background:#f8fafc; }
  .el-empty { text-align:center; padding:32px; color:var(--el-muted); font-size:.83rem; }
  .el-empty i { font-size:2rem; display:block; margin-bottom:8px; }
</style>
CSS;

require_once __DIR__ . '/includes/header.php';
?>
<div class="el-wrap">

  <!-- Stats -->
  <div class="el-stats">
    <div class="el-stat"><div class="lbl">Hotels</div><div class="val" style="color:var(--el-brand)"><?= $totalHotels ?></div></div>
    <div class="el-stat"><div class="lbl">Total Rooms</div><div class="val" style="color:var(--el-teal)"><?= $totalRooms ?></div></div>
    <div class="el-stat"><div class="lbl">Available</div><div class="val" style="color:#059669"><?= $totalAvail ?></div></div>
    <div class="el-stat"><div class="lbl">Booked Today</div><div class="val" style="color:var(--el-coral)"><?= $totalBooked ?></div></div>
    <div class="el-stat"><div class="lbl">Occupancy</div><div class="val" style="color:#d97706"><?= $globalOcc ?>%</div></div>
  </div>

  <!-- Toolbar -->
  <div class="el-toolbar">
    <h2><i class="bi bi-building"></i> Hotel Listings</h2>
    <input type="text" class="el-search" id="hotel-search" placeholder="🔍 Search hotels..." onkeyup="filterHotels(this.value)">
    <?php if($isAdmin): ?>
    <a href="listing.php" class="el-btn el-btn-teal"><i class="bi bi-gear"></i> Manage Hotels</a>
    <?php endif; ?>
  </div>

  <?php if(empty($hotels)): ?>
  <div class="el-hcard"><div class="el-empty"><i class="bi bi-building-slash"></i>No hotels found.</div></div>
  <?php endif; ?>

  <?php foreach($hotels as $hotel):
    $hTot = (int)$hotel['total_rooms'];
    $hBk  = (int)$hotel['booked_rooms'];
    $hAv  = (int)$hotel['avail_rooms'];
    $hBl  = (int)$hotel['blocked_rooms'];
    $hOcc = $hTot > 0 ? round($hBk/$hTot*100) : 0;
  ?>
  <div class="el-hcard" id="el-hcard-<?= $hotel['id'] ?>" data-hotel-name="<?= htmlspecialchars(strtolower($hotel['name'].' '.$hotel['city'])) ?>">

    <!-- Header -->
    <div class="el-hcard-hdr">
      <div class="el-hotel-name">
        <h3>
          <?php for($s=1;$s<=$hotel['star_rating'];$s++) echo '<i class="bi bi-star-fill" style="color:#f59e0b;font-size:.75rem"></i>'; ?>
          <?= htmlspecialchars($hotel['name']) ?>
          <span class="el-badge el-b-gray" style="font-size:.62rem;margin-left:6px"><?= htmlspecialchars($hotel['hotel_code']) ?></span>
        </h3>
        <p>
          <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($hotel['city']) ?><?= $hotel['state']?', '.htmlspecialchars($hotel['state']):'' ?>
          <?php if($hotel['phone']): ?> &nbsp;|&nbsp; <i class="bi bi-telephone"></i> <?= htmlspecialchars($hotel['phone']) ?><?php endif; ?>
          <?php if($hotel['email']): ?> &nbsp;|&nbsp; <i class="bi bi-envelope"></i> <?= htmlspecialchars($hotel['email']) ?><?php endif; ?>
          <?php if($hotel['address']): ?> &nbsp;|&nbsp; <i class="bi bi-house"></i> <?= htmlspecialchars($hotel['address']) ?><?php endif; ?>
        </p>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <?php if($hotel['todays_guests'] > 0): ?>
        <span class="el-badge el-b-blue"><i class="bi bi-people"></i> <?= $hotel['todays_guests'] ?> Today's Guests</span>
        <?php endif; ?>
        <button class="el-btn el-btn-ghost el-btn-sm" onclick="elLoadBookings(<?= $hotel['id'] ?>)">
          <i class="bi bi-ticket-detailed"></i> View Bookings
        </button>
        <?php if($isAdmin): ?>
        <a href="listing.php" class="el-btn el-btn-teal el-btn-sm"><i class="bi bi-pencil"></i> Manage</a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Stats Bar -->
    <div class="el-hstats">
      <div class="el-hstat"><div class="n"><?= count($hotel['rooms']) ?></div><div class="l">Catg.</div></div>
      <div class="el-hstat"><div class="n"><?= $hTot ?></div><div class="l">Total</div></div>
      <div class="el-hstat"><div class="n" style="color:#059669"><?= $hAv ?></div><div class="l">Avail</div></div>
      <div class="el-hstat"><div class="n" style="color:var(--el-coral)"><?= $hBk ?></div><div class="l">Booked</div></div>
      <div class="el-hstat"><div class="n" style="color:#92400e"><?= $hBl ?></div><div class="l">Blocked</div></div>
      <div class="el-hstat"><div class="n" style="color:var(--el-brand)"><?= $hOcc ?>%</div><div class="l">Occ.</div></div>
      <div class="el-hstat"><div class="n" style="color:#0369a1"><?= $hotel['todays_guests'] ?></div><div class="l">In-House</div></div>
    </div>

    <!-- Tabs -->
    <div class="el-tabs">
      <div class="el-tab active" onclick="elSwitchTab(this,'el-rooms-<?= $hotel['id'] ?>')"><i class="bi bi-door-open"></i> Rooms</div>
      <div class="el-tab" onclick="elSwitchTab(this,'el-bk-<?= $hotel['id'] ?>');elLoadBookings(<?= $hotel['id'] ?>)">
        <i class="bi bi-ticket-detailed"></i> Bookings
        <span id="el-bk-badge-<?= $hotel['id'] ?>" 
style="background:#4f46e5;color:#fff;border-radius:10px;padding:0 
6px;font-size:.62rem;margin-left:3px;display:none">0</span>
      </div>
    </div>

    <!-- Rooms Tab -->
    <div class="el-tab-panel active" id="el-rooms-<?= $hotel['id'] ?>">
      <?php if(empty($hotel['rooms'])): ?>
      <div class="el-empty"><i class="bi bi-inbox"></i>No room categories.</div>
      <?php else: ?>
      <div style="overflow-x:auto;border-radius:10px;border:1px solid var(--el-border)">
        <table class="el-rtable">
          <thead>
            <tr>
              <th>Room Name</th><th>Bed</th><th>Size</th>
              <th style="text-align:center">Total</th><th style="text-align:center">Available</th>
              <th style="text-align:center">Booked</th><th style="text-align:center">Blocked</th>
              <th>Pricing (Meal Plan)</th><th>Extra Bed</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($hotel['rooms'] as $rm): ?>
            <tr>
              <td style="font-weight:700"><?= htmlspecialchars($rm['name']) ?></td>
              <td><span class="el-badge el-b-blue"><?= htmlspecialchars($rm['bed_type']) ?></span></td>
              <td style="color:var(--el-muted);font-size:.75rem"><?= htmlspecialchars($rm['room_size']) ?></td>
              <td style="text-align:center"><strong><?= $rm['total_rooms'] ?></strong></td>
              <td style="text-align:center"><span class="el-badge el-b-green"><?= $rm['available_rooms'] ?></span></td>
              <td style="text-align:center"><span class="el-badge el-b-red"><?= $rm['booked_rooms'] ?></span></td>
              <td style="text-align:center"><span class="el-badge el-b-orange"><?= $rm['blocked_rooms'] ?></span></td>
              <td class="el-price-cell">
                <?php foreach($mealPlans as $code=>$label): if(isset($rm['prices'][$code]) && $rm['prices'][$code]>0): ?>
                <span title="<?= htmlspecialchars($label) ?>"><?= $code ?> ₹<?= number_format($rm['prices'][$code]) ?></span>
                <?php endif; endforeach; ?>
              </td>
              <td style="font-size:.74rem">
                <?php if($rm['extra_bed_allowed']): ?>
                  <span style="color:#2a9d8f;font-weight:700"><i class="bi bi-check-circle-fill"></i></span>
                  ₹<?= number_format($rm['extra_bed_price']) ?>/bed — Max <?= $rm['max_extra_beds'] ?>
                <?php else: ?>
                  <span style="color:var(--el-muted)"><i class="bi bi-x-circle"></i> Not allowed</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- Bookings Tab -->
    <div class="el-tab-panel" id="el-bk-<?= $hotel['id'] ?>">
      <div id="el-bk-content-<?= $hotel['id'] ?>" style="min-height:80px">
        <div class="el-empty"><i class="bi bi-arrow-clockwise"></i>Click "Bookings" tab to load...</div>
      </div>
    </div>

  </div><!-- /el-hcard -->
  <?php endforeach; ?>

</div><!-- /el-wrap -->

<script>
function elSwitchTab(el, panelId) {
  const card = el.closest('.el-hcard');
  card.querySelectorAll('.el-tab').forEach(t=>t.classList.remove('active'));
  card.querySelectorAll('.el-tab-panel').forEach(p=>p.classList.remove('active'));
  el.classList.add('active');
  const p = document.getElementById(panelId);
  if(p) p.classList.add('active');
}

function filterHotels(q) {
  q = q.toLowerCase().trim();
  document.querySelectorAll('.el-hcard').forEach(card => {
    const name = card.dataset.hotelName || '';
    card.style.display = (!q || name.includes(q)) ? '' : 'none';
  });
}

const elLoadedBk = new Set();
async function elLoadBookings(hotelId, force=false) {
  if (elLoadedBk.has(hotelId) && !force) return;
  const container = document.getElementById('el-bk-content-'+hotelId);
  if (!container) return;
  container.innerHTML = '<div class="el-empty"><i class="bi bi-hourglass-split"></i> Loading bookings...</div>';
  try {
    const res      = await fetch(`/ajax/get_listing_data.php?type=bookings&hotel_id=${hotelId}`, {headers:{'X-Requested-With':'XMLHttpRequest'}});
    const json     = await res.json();
    const bookings = json.data?.bookings || [];
    const badge    = document.getElementById('el-bk-badge-'+hotelId);
    const active   = bookings.filter(b=>b.booking_status!=='cancelled').length;
    if(badge){ badge.textContent=active; badge.style.display=active>0?'':'none'; }
    if(!bookings.length) {
      container.innerHTML = '<div class="el-empty"><i class="bi bi-inbox"></i>No bookings found for this hotel.</div>';
      return;
    }
    const statusColor = {confirmed:'el-b-green',pending:'el-b-orange',cancelled:'el-b-gray',checked_in:'el-b-blue',checked_out:'el-b-purple'};
    const esc = v => { const d=document.createElement('div'); d.textContent=v??''; return d.innerHTML; };
    const rows = bookings.map(b=>`
      <tr>
        <td><strong style="color:#4f46e5">${esc(b.booking_number)}</strong></td>
        <td><strong>${esc(b.guest_name)}</strong><br><small style="color:#6b7280">${esc(b.guest_phone||'')}</small></td>
        <td>${esc(b.room_name||'—')}</td>
        <td><small>${esc(b.checkin_date)}</small><br><small>→${esc(b.checkout_date)}</small></td>
        <td style="text-align:center">${b.total_nights||'?'}N</td>
        <td style="text-align:center"><span class="el-badge el-b-blue">${esc(b.meal_plan_code||'EP')}</span></td>
        <td style="text-align:right"><strong>₹${Number(b.total_amount).toLocaleString('en-IN')}</strong></td>
        <td style="text-align:center"><span class="el-badge ${statusColor[b.booking_status]||'el-b-gray'}">${b.booking_status}</span></td>
        <td style="text-align:center"><span class="el-badge ${b.payment_status==='paid'?'el-b-green':(b.payment_status==='partial'?'el-b-orange':'el-b-gray')}">${b.payment_status}</span></td>
      </tr>`).join('');
    container.innerHTML = `
      <div style="overflow-x:auto;border-radius:10px;border:1px solid #e2e8f0">
        <table class="el-rtable el-bk-table" style="font-size:.78rem">
          <thead><tr>
            <th>Booking Ref</th><th>Guest</th><th>Room</th><th>Dates</th>
            <th style="text-align:center">Nights</th><th style="text-align:center">Plan</th>
            <th style="text-align:right">Amount</th><th style="text-align:center">Status</th><th style="text-align:center">Payment</th>
          </tr></thead>
          <tbody>${rows}</tbody>
        </table>
      </div>`;
    elLoadedBk.add(hotelId);
  } catch(e) {
    container.innerHTML = `<div class="el-empty" style="color:#e76f51"><i class="bi bi-exclamation-triangle"></i> ${e.message}</div>`;
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
