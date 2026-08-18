<?php
/**
 * includes/right-sidebar.php
 * ─────────────────────────────────────────────────────────
 * Reusable right sidebar — modern design with quick stats.
 */

if (!empty($hideRightSidebar)) return;

$rightSidebarTitle = $rightSidebarTitle ?? 'Quick Overview';

$rsStats = $rsStats ?? [
    ['label' => 'Total Bookings',  'value' => '—', 'color' => '#4f46e5'],
    ['label' => 'Active Agents',   'value' => '—', 'color' => '#10b981'],
    ['label' => 'Pending Queries', 'value' => '—', 'color' => '#f59e0b'],
    ['label' => 'Hotel Listings',  'value' => '—', 'color' => '#06b6d4'],
];

$rsActivity = $rsActivity ?? [];
?>

<aside class="right-sidebar" id="rightSidebar" aria-label="Quick Overview Panel">

  <div class="right-sidebar-header">
    <span><?php echo htmlspecialchars($rightSidebarTitle, ENT_QUOTES, 'UTF-8'); ?></span>
    <i class="bi bi-grid-3x3-gap" style="color:var(--text-muted);font-size:16px;"></i>
  </div>

  <div class="right-sidebar-section">
    <div class="rs-section-title">At a Glance</div>
    <?php foreach ($rsStats as $stat): ?>
      <div class="rs-stat">
        <span class="rs-stat-label"><?php echo htmlspecialchars($stat['label'], ENT_QUOTES, 'UTF-8'); ?></span>
        <span class="rs-stat-value" style="color:<?php echo htmlspecialchars($stat['color'] ?? 'var(--text)', ENT_QUOTES, 'UTF-8'); ?>;">
          <?php echo htmlspecialchars((string) $stat['value'], ENT_QUOTES, 'UTF-8'); ?>
        </span>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if (!empty($rsActivity)): ?>
  <div class="right-sidebar-section">
    <div class="rs-section-title">Recent Activity</div>
    <?php foreach ($rsActivity as $act): ?>
      <div class="activity-item">
        <span class="activity-dot" style="background:<?php echo htmlspecialchars($act['color'] ?? '#4f46e5', ENT_QUOTES, 'UTF-8'); ?>;"></span>
        <div>
          <div class="activity-text"><?php echo htmlspecialchars($act['text'], ENT_QUOTES, 'UTF-8'); ?></div>
          <?php if (!empty($act['time'])): ?>
            <div class="activity-time"><?php echo htmlspecialchars($act['time'], ENT_QUOTES, 'UTF-8'); ?></div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="right-sidebar-section">
    <div class="rs-section-title">Quick Links</div>
    <div style="display:flex;flex-direction:column;gap:4px;">
      <a href="<?php echo htmlspecialchars(function_exists('site_url') ? site_url('booking-details.php') : '/booking-details.php', ENT_QUOTES, 'UTF-8'); ?>" style="display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:10px;font-size:.82rem;color:var(--text);transition:all .15s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='none'">
        <i class="bi bi-calendar-check" style="color:#4f46e5;font-size:16px;width:20px;text-align:center;"></i>
        View All Bookings
      </a>
      <a href="<?php echo htmlspecialchars(function_exists('site_url') ? site_url('agents-details.php') : '/agents-details.php', ENT_QUOTES, 'UTF-8'); ?>" style="display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:10px;font-size:.82rem;color:var(--text);transition:all .15s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='none'">
        <i class="bi bi-person-badge" style="color:#10b981;font-size:16px;width:20px;text-align:center;"></i>
        Agent Directory
      </a>
      <a href="<?php echo htmlspecialchars(function_exists('site_url') ? site_url('listing.php') : '/listing.php', ENT_QUOTES, 'UTF-8'); ?>" style="display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:10px;font-size:.82rem;color:var(--text);transition:all .15s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='none'">
        <i class="bi bi-building" style="color:#06b6d4;font-size:16px;width:20px;text-align:center;"></i>
        Hotel Listings
      </a>
      <a href="<?php echo htmlspecialchars(function_exists('site_url') ? site_url('accounts-detail.php') : '/accounts-detail.php', ENT_QUOTES, 'UTF-8'); ?>" style="display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:10px;font-size:.82rem;color:var(--text);transition:all .15s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='none'">
        <i class="bi bi-wallet2" style="color:#f59e0b;font-size:16px;width:20px;text-align:center;"></i>
        Accounts Ledger
      </a>
      <a href="<?php echo htmlspecialchars(function_exists('site_url') ? site_url('export-bookings-excel.php') : '/export-bookings-excel.php', ENT_QUOTES, 'UTF-8'); ?>" style="display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:10px;font-size:.82rem;color:var(--text);transition:all .15s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='none'">
        <i class="bi bi-file-earmark-spreadsheet" style="color:#059669;font-size:16px;width:20px;text-align:center;"></i>
        Export to Excel
      </a>
    </div>
  </div>

  <div class="right-sidebar-section">
    <div class="rs-section-title">System</div>
    <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:6px;">
      <i class="bi bi-calendar3 me-1"></i>
      <?php echo date('D, d M Y'); ?>
    </div>
    <div style="font-size:.8rem;color:var(--text-muted);">
      <i class="bi bi-clock me-1"></i>
      <span id="rsLiveClock"><?php echo date('h:i A'); ?></span>
    </div>
  </div>

</aside>

<script>
(function() {
  function updateClock() {
    const el = document.getElementById('rsLiveClock');
    if (!el) return;
    const now = new Date();
    let h = now.getHours(), m = now.getMinutes(), s = now.getSeconds();
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    el.textContent = String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0') + ' ' + ampm;
  }
  updateClock();
  setInterval(updateClock, 1000);
})();
</script>
