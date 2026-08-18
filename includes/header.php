<?php
/**
 * includes/header.php
 * ─────────────────────────────────────────────────────────
 * Reusable page header — outputs DOCTYPE, <head>, opens <body>,
 * and renders the sticky top navigation bar.
 */
$pageTitle       = $pageTitle       ?? 'Uttarakhand Ventures CRM';
$pageDescription = $pageDescription ?? 'Admin panel for Uttarakhand Ventures CRM.';
$activePage      = $activePage      ?? '';
$extraCss        = $extraCss        ?? '';

$currentUser     = $_SESSION['username'] ?? 'Admin';
$currentRole     = $_SESSION['role']     ?? 'Administrator';
$userInitial     = strtoupper(substr($currentUser, 0, 1));

$fullTitle = $pageTitle . ' — Uttarakhand Ventures CRM';
?>
<?php
// Load config helpers. URL rewrite is initialized centrally in config.php.
require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($fullTitle, ENT_QUOTES, 'UTF-8'); ?></title>
  <meta name="description" content="<?php echo htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8'); ?>">
  <link rel="icon" type="image/svg+xml" href="/assets/images/favicon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="/assets/css/style.css" rel="stylesheet">
  <link href="/assets/css/ui-consistency.css" rel="stylesheet">
  <?php echo $extraCss; ?>
</head>
<body>

<div class="sidebar-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>

<div class="layout">
  <div class="layout-body" id="layoutBody">

    <header class="top-header" role="banner">
      <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Open menu" aria-expanded="false" aria-controls="leftSidebar">
        <i class="bi bi-list"></i>
      </button>

      <div class="page-title-bar">
        <h1><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
      </div>

      <div class="header-actions">
        <div class="header-search d-none d-md-block">
          <input type="text" placeholder="Search anything..." aria-label="Global search">
        </div>

        <button class="header-icon-btn" aria-label="Notifications">
          <i class="bi bi-bell"></i>
          <span class="notif-dot" aria-hidden="true"></span>
        </button>

        <button class="header-icon-btn" title="Booking History" onclick="location.href='/booking-details.php'" aria-label="Booking History">
          <i class="bi bi-clock-history"></i>
        </button>

        <div style="position:relative;" id="userMenuWrapper">
          <div class="header-avatar" id="userAvatarBtn" role="button" tabindex="0"
               aria-haspopup="true" aria-expanded="false"
               title="<?php echo htmlspecialchars($currentUser, ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo $userInitial; ?>
          </div>
          <div id="userDropdown" style="display:none;position:absolute;top:46px;right:0;background:#fff;border:1px solid #e2e8f0;border-radius:14px;box-shadow:0 10px 25px rgba(15,23,42,.12);padding:8px;min-width:200px;z-index:300;animation:fadeIn .2s ease;">
            <div style="padding:10px 14px 12px;border-bottom:1px solid #f1f5f9;">
              <div style="font-weight:700;font-size:.86rem;color:#0f172a;"><?php echo htmlspecialchars($currentUser, ENT_QUOTES, 'UTF-8'); ?></div>
              <div style="font-size:.74rem;color:#94a3b8;text-transform:capitalize;"><?php echo htmlspecialchars($currentRole, ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <a href="/dashboard.php" style="display:flex;align-items:center;gap:10px;padding:9px 14px;font-size:.84rem;border-radius:10px;color:#0f172a;margin-top:4px;transition:all .15s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='none'">
              <i class="bi bi-speedometer2" style="color:#4f46e5;"></i> Dashboard
            </a>
            <a href="/booking-details.php" style="display:flex;align-items:center;gap:10px;padding:9px 14px;font-size:.84rem;border-radius:10px;color:#0f172a;margin-top:2px;transition:all .15s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='none'">
              <i class="bi bi-clock-history" style="color:#06b6d4;"></i> Booking History
            </a>
            <a href="/logout.php" style="display:flex;align-items:center;gap:10px;padding:9px 14px;font-size:.84rem;border-radius:10px;color:#ef4444;margin-top:2px;transition:all .15s;" onmouseover="this.style.background='#fef2f2'" onmouseout="this.style.background='none'">
              <i class="bi bi-box-arrow-right"></i> Logout
            </a>
          </div>
        </div>
      </div>
    </header>
