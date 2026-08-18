(function () {
  'use strict';

  var navMap = {
    '/dashboard.php': { label: 'Dashboard', icon: 'bi-grid-1x2' },
    '/employee-dashboard.php': { label: 'Dashboard', icon: 'bi-grid-1x2' },
    '/agents-details.php': { label: 'Agents', icon: 'bi-person-badge' },
    '/booking-details.php': { label: 'Bookings', icon: 'bi-calendar-check' },
    '/bookingquery.php': { label: 'Booking Query', icon: 'bi-chat-dots' },
    '/employees-detail.php': { label: 'Employees', icon: 'bi-person-vcard' },
    '/accounts-detail.php': { label: 'Accounts', icon: 'bi-wallet2' },
    '/listing.php': { label: 'Hotel Listings', icon: 'bi-building' },
    '/employee-listings.php': { label: 'Hotel Listings', icon: 'bi-building' },
    '/hotel-manager.php': { label: 'Room Manager', icon: 'bi-building-gear' }
  };

  function toPath(href) {
    try {
      return new URL(href, window.location.origin).pathname.toLowerCase();
    } catch (e) {
      return '';
    }
  }

  function normalizeSidebarLabels() {
    var navEntries = Object.keys(navMap);
    var roots = document.querySelectorAll('#adminSidebar, #leftSidebar, .sidebar, .left-sidebar');
    roots.forEach(function (root) {
      var links = root.querySelectorAll('.nav-link[href]');
      links.forEach(function (link) {
        var path = toPath(link.getAttribute('href') || '');
        var matchedKey = navEntries.find(function (k) {
          return path === k || path.endsWith(k);
        });
        var map = matchedKey ? navMap[matchedKey] : null;
        if (!map) return;

        var iconEl = link.querySelector('i.bi');
        var badgeEl = link.querySelector('.nav-badge');

        if (iconEl) {
          iconEl.className = 'bi ' + map.icon + (iconEl.className.indexOf('nav-icon') >= 0 ? ' nav-icon' : '');
        }

        var iconHtml = iconEl ? iconEl.outerHTML : '<i class="bi ' + map.icon + '"></i>';
        var badgeHtml = badgeEl ? badgeEl.outerHTML : '';
        link.innerHTML = iconHtml + ' ' + map.label + (badgeHtml ? ' ' + badgeHtml : '');
      });
    });
  }

  function isEmployeeContext() {
    var p = window.location.pathname.toLowerCase();
    if (p.indexOf('/employee-') >= 0) return true;
    return !!document.querySelector('a.nav-link[href$="/employee-dashboard.php"]');
  }

  function ensureProfileMenuOption() {
    var profileHref = isEmployeeContext() ? '/employee-dashboard.php' : '/dashboard.php';

    document.querySelectorAll('.user-menu-corner .dropdown-menu').forEach(function (menu) {
      if (menu.querySelector('[data-uv-profile-link="1"]')) return;

      var li = document.createElement('li');
      li.innerHTML =
        '<a class="dropdown-item" data-uv-profile-link="1" href="' + profileHref + '">' +
        '<i class="bi bi-person-circle me-2"></i> Profile</a>';

      if (menu.firstChild) {
        menu.insertBefore(li, menu.firstChild);
      } else {
        menu.appendChild(li);
      }
    });

    var legacyMenu = document.getElementById('userDropdown');
    if (legacyMenu && !legacyMenu.querySelector('[data-uv-profile-link="1"]')) {
      var profileLink = document.createElement('a');
      profileLink.setAttribute('data-uv-profile-link', '1');
      profileLink.href = profileHref;
      profileLink.style.display = 'flex';
      profileLink.style.alignItems = 'center';
      profileLink.style.gap = '10px';
      profileLink.style.padding = '9px 14px';
      profileLink.style.fontSize = '.84rem';
      profileLink.style.borderRadius = '10px';
      profileLink.style.color = '#0f172a';
      profileLink.style.marginTop = '4px';
      profileLink.innerHTML = '<i class="bi bi-person-circle" style="color:#4f46e5;"></i> Profile';

      var firstAction = legacyMenu.querySelector('a');
      if (firstAction) {
        legacyMenu.insertBefore(profileLink, firstAction);
      } else {
        legacyMenu.appendChild(profileLink);
      }
    }
  }

  function run() {
    normalizeSidebarLabels();
    ensureProfileMenuOption();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }
})();
