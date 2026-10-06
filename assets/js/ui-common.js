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
    '/hotel-manager.php': { label: 'Room Manager', icon: 'bi-building-gear' },
    '/hotel_calculator.php': { label: 'Hotel Rates', icon: 'bi-currency-rupee' },
    'https://buyusnumber.com/uk1.php': { label: 'Hotel Rates', icon: 'bi-currency-rupee' }
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
        var rawHref = (link.getAttribute('href') || '').trim();
        var path = toPath(rawHref);
        var matchedKey = navEntries.find(function (k) {
          return rawHref === k || path === k || (k.indexOf('/') === 0 && path.endsWith(k));
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

  function ensureCalculatorLink() {
    document.querySelectorAll('#adminSidebar').forEach(function (sidebar) {
      if (sidebar.querySelector('a[data-uv-calculator-link="1"]')) return;
      var list = sidebar.querySelector('ul.nav, .sidebar-nav');
      if (!list) return;
      var item = document.createElement('li');
      item.className = 'nav-item';
      item.innerHTML = '<a class="nav-link" data-uv-calculator-link="1" href="https://buyusnumber.com/uk1.php" target="_blank" rel="noopener noreferrer"><i class="bi bi-currency-rupee"></i> Hotel Rates</a>';
      list.appendChild(item);
    });
  }

  function isEmployeeContext() {
    var p = window.location.pathname.toLowerCase();
    if (p.indexOf('/employee-') >= 0) return true;
    return !!document.querySelector('a.nav-link[href$="/employee-dashboard.php"]');
  }

  function ensureProfileMenuOption() {
    document.querySelectorAll('.dropdown-menu, #userDropdown').forEach(function (menu) {
      var profileLinks = Array.prototype.filter.call(menu.querySelectorAll('a'), function (link) {
        var text = (link.textContent || '').trim().toLowerCase();
        return text.indexOf('profile') !== -1;
      });
      if (profileLinks.length > 1) {
        for (var i = 1; i < profileLinks.length; i++) {
          var item = profileLinks[i].closest('li') || profileLinks[i];
          if (item && item.parentNode) {
            item.parentNode.removeChild(item);
          }
        }
      }
    });
  }

  function ensureThemeControls() {
    var headers = document.querySelectorAll('.top-header');
    if (!headers.length) return;

    headers.forEach(function (header) {
      var actions = header.querySelector('.header-actions, .user-menu-corner, .dropdown');
      var notification = header.querySelector('[aria-label="Notifications"]');
      var toggle = header.querySelector('.theme-toggle');

      function makeButton(className, label, iconClass) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'header-action-icon ' + className;
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);
        button.innerHTML = '<i class="bi ' + iconClass + '"></i>';
        return button;
      }

      if (!notification) {
        notification = makeButton('notification-toggle', 'Notifications', 'bi-bell');
        if (actions && actions.parentNode === header) header.insertBefore(notification, actions);
        else header.appendChild(notification);
      }
      if (!toggle) {
        toggle = makeButton('theme-toggle', 'Toggle dark mode', 'bi-lightbulb');
        if (actions && actions.parentNode === header) header.insertBefore(toggle, actions);
        else header.appendChild(toggle);
      }

      var icon = toggle.querySelector('i');
      var dark = document.documentElement.getAttribute('data-theme') === 'dark';
      icon.className = dark ? 'bi bi-sun' : 'bi bi-lightbulb';
      toggle.setAttribute('aria-pressed', dark ? 'true' : 'false');
    });
  }

  function bindThemeToggle() {
    if (document.documentElement.dataset.themeClickBound === '1') return;
    document.documentElement.dataset.themeClickBound = '1';
    window.toggleCrmTheme = function () {
      var isDark = document.documentElement.getAttribute('data-theme') !== 'dark';
      document.documentElement.toggleAttribute('data-theme', isDark);
      document.documentElement.classList.toggle('dark-theme', isDark);
      document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
      if (isDark) localStorage.setItem('crm-theme', 'dark');
      else localStorage.removeItem('crm-theme');
      document.querySelectorAll('.theme-toggle').forEach(function (button) {
        var icon = button.querySelector('i');
        if (icon) icon.className = isDark ? 'bi bi-sun' : 'bi bi-lightbulb';
        button.setAttribute('aria-pressed', isDark ? 'true' : 'false');
      });
    };
    document.addEventListener('click', function (event) {
      var toggle = event.target.closest ? event.target.closest('.theme-toggle') : null;
      if (!toggle) return;
      event.preventDefault();
      window.toggleCrmTheme();
    });
  }

  function run() {
    var savedTheme = localStorage.getItem('crm-theme') === 'dark';
    if (savedTheme) {
      document.documentElement.setAttribute('data-theme', 'dark');
      document.documentElement.classList.add('dark-theme');
      document.documentElement.style.colorScheme = 'dark';
    }
    normalizeSidebarLabels();
    ensureCalculatorLink();
    ensureProfileMenuOption();
    ensureThemeControls();
    bindThemeToggle();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }
})();
