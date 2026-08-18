<?php
/**
 * includes/left-sidebar.php
 * ─────────────────────────────────────────────────────────
 * Reusable left navigation sidebar with modern design.
 */

$activePage  = $activePage  ?? '';
$currentUser = $_SESSION['username'] ?? 'Admin';
$userInitial = strtoupper(substr($currentUser, 0, 1));
$currentRole = $_SESSION['role'] ?? 'Administrator';

$navItems = [
    'dashboard'     => ['label' => 'Dashboard',     'icon' => 'bi-grid-1x2',      'href' => '/dashboard.php'],
    'agents'        => ['label' => 'Agents',         'icon' => 'bi-person-badge',  'href' => '/agents-details.php'],
    'bookings'      => ['label' => 'Bookings',       'icon' => 'bi-calendar-check','href' => '/booking-details.php'],
    'query'         => ['label' => 'Booking Query',  'icon' => 'bi-chat-dots',     'href' => '/bookingquery.php'],
    'employees'     => ['label' => 'Employees',      'icon' => 'bi-person-vcard',  'href' => '/employees-detail.php'],
    'accounts'      => ['label' => 'Accounts',       'icon' => 'bi-wallet2',       'href' => '/accounts-detail.php'],
    'listing'       => ['label' => 'Hotel Listings', 'icon' => 'bi-building',      'href' => '/listing.php'],
    'hotel-manager' => ['label' => 'Room Manager',   'icon' => 'bi-building-gear', 'href' => '/hotel-manager.php'],
];

$navRole = $_SESSION['role'] ?? 'employee';
$adminOnlyKeys = ['hotel-manager'];
if ($navRole !== 'admin') {
    foreach ($adminOnlyKeys as $k) { unset($navItems[$k]); }
}
if ($navRole === 'employee') {
    $navItems['dashboard']['href'] = '/employee-dashboard.php';
}
?>

<aside class="left-sidebar" id="leftSidebar" role="navigation" aria-label="Main Navigation">

  <div class="sidebar-brand">
    <span class="sidebar-brand-text">
      <span class="brand-icon"><i class="bi bi-buildings"></i></span>
      <span>Uttarakhand<br>Ventures</span>
    </span>
    <button class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close menu">
      <i class="bi bi-x-lg"></i>
    </button>
  </div>

  <nav class="sidebar-nav" aria-label="CRM Navigation">
    <span class="nav-section-label">Main Menu</span>

    <?php foreach ($navItems as $key => $item):
      $isActive = ($activePage === $key) ? 'active' : '';
      $ariaCurrent = $isActive ? 'aria-current="page"' : '';
    ?>
      <?php
        $href = $item['href'];
        if (function_exists('site_url') && strpos($href, '/') === 0) {
          $href = site_url(ltrim($href, '/'));
        }
      ?>
      <a class="nav-link <?php echo $isActive; ?>"
         href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>"
         <?php echo $ariaCurrent; ?>>
        <i class="bi <?php echo $item['icon']; ?> nav-icon"></i>
        <?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>
        <?php if (!empty($item['badge'])): ?>
          <span class="nav-badge"><?php echo (int) $item['badge']; ?></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>

  </nav>

  <div class="sidebar-footer">
    <?php
      $logoutHref = '/logout.php';
      if (function_exists('site_url')) { $logoutHref = site_url('logout.php'); }
    ?>
    <a class="sidebar-user" href="<?php echo htmlspecialchars($logoutHref, ENT_QUOTES, 'UTF-8'); ?>" title="Logout">
      <div class="sidebar-avatar"><?php echo $userInitial; ?></div>
      <div class="sidebar-user-info">
        <div class="sidebar-user-name"><?php echo htmlspecialchars($currentUser, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="sidebar-user-role"><?php echo htmlspecialchars($currentRole, ENT_QUOTES, 'UTF-8'); ?></div>
      </div>
      <i class="bi bi-box-arrow-right" style="color:var(--sidebar-text);font-size:15px;margin-left:auto;opacity:.6;transition:opacity .2s;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='.6'"></i>
    </a>
  </div>

</aside>
