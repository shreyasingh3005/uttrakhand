<?php
/**
 * includes/footer.php
 * ─────────────────────────────────────────────────────────
 * Reusable page footer.
 */
$currentYear = date('Y');
?>

    </main>



  </div>
</div>

<script src="/assets/js/ui-common.js"></script>
<script>
(function () {
  const sidebar   = document.getElementById('leftSidebar');
  const backdrop  = document.getElementById('sidebarBackdrop');
  const openBtn   = document.getElementById('mobileMenuBtn');
  const closeBtn  = document.getElementById('sidebarCloseBtn');

  if (!sidebar || !backdrop) return;

  function openSidebar() {
    sidebar.classList.add('open');
    backdrop.classList.add('show');
    document.body.style.overflow = 'hidden';
    if (openBtn) openBtn.setAttribute('aria-expanded', 'true');
  }

  function closeSidebar() {
    sidebar.classList.remove('open');
    backdrop.classList.remove('show');
    document.body.style.overflow = '';
    if (openBtn) openBtn.setAttribute('aria-expanded', 'false');
  }

  if (openBtn)   openBtn.addEventListener('click', openSidebar);
  if (closeBtn)  closeBtn.addEventListener('click', closeSidebar);
  backdrop.addEventListener('click', closeSidebar);

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeSidebar();
  });

  sidebar.querySelectorAll('.nav-link').forEach(function (link) {
    link.addEventListener('click', function () {
      if (window.innerWidth < 992) closeSidebar();
    });
  });
})();

(function () {
  const avatarBtn  = document.getElementById('userAvatarBtn');
  const dropdown   = document.getElementById('userDropdown');
  if (!avatarBtn || !dropdown) return;

  avatarBtn.addEventListener('click', function (e) {
    e.stopPropagation();
    const isOpen = dropdown.style.display === 'block';
    dropdown.style.display = isOpen ? 'none' : 'block';
    avatarBtn.setAttribute('aria-expanded', !isOpen);
  });

  document.addEventListener('click', function () {
    dropdown.style.display = 'none';
    avatarBtn.setAttribute('aria-expanded', 'false');
  });

  dropdown.addEventListener('click', function (e) { e.stopPropagation(); });
})();
</script>

<?php if (!empty($extraJs)) echo $extraJs; ?>

</body>
</html>
