<?php
/**
 * about.php — Example "About" page using the common layout
 */
require_once __DIR__ . '/includes/auth_session.php';
require_once __DIR__ . '/includes/db_connect.php';
require_role('admin');

$activePage      = '';              // No sidebar link is active
$pageTitle       = 'About';
$pageDescription = 'About Uttarakhand Ventures CRM system.';
$hideRightSidebar = true;          // Hide right sidebar on this page

require_once __DIR__ . '/includes/left-sidebar.php';
require_once __DIR__ . '/includes/header.php';
?>

    <main class="main-content">

      <div class="panel mb-4">
        <div class="panel-title">
          <i class="bi bi-info-circle" style="color:#4f46e5;"></i>
          About This System
        </div>
        <p style="font-size:.88rem;color:#64748b;line-height:1.8;max-width:680px;">
          <strong>Uttarakhand Ventures CRM</strong> is a comprehensive hotel and travel management platform
          built for managing bookings, agents, employees, accounts, and hotel listings from a single admin panel.
        </p>
        <div class="grid grid-3 mt-4">
          <div class="summary-card" style="text-align:center;">
            <i class="bi bi-calendar-check" style="font-size:28px;color:#4f46e5;display:block;margin-bottom:8px;"></i>
            <div class="summary-value">Bookings</div>
            <div class="summary-label mt-1">Full lifecycle tracking</div>
          </div>
          <div class="summary-card" style="text-align:center;">
            <i class="bi bi-person-badge" style="font-size:28px;color:#10b981;display:block;margin-bottom:8px;"></i>
            <div class="summary-value">Agents</div>
            <div class="summary-label mt-1">Multi-level agent network</div>
          </div>
          <div class="summary-card" style="text-align:center;">
            <i class="bi bi-building" style="font-size:28px;color:#0d9488;display:block;margin-bottom:8px;"></i>
            <div class="summary-value">Listings</div>
            <div class="summary-label mt-1">Room-wise rate management</div>
          </div>
        </div>
      </div>

    </main>

<?php
require_once __DIR__ . '/includes/right-sidebar.php';   // returns early because $hideRightSidebar = true
require_once __DIR__ . '/includes/footer.php';
?>
