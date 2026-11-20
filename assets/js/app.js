(() => {
  const body = document.body;
  const toggle = document.getElementById('sidebarToggle');
  const backdrop = document.getElementById('sidebarBackdrop');

  const isMobile = () => window.matchMedia('(max-width: 991.98px)').matches;

  const closeMobileSidebar = () => {
    body.classList.remove('sidebar-open');
  };

  if (toggle) {
    toggle.addEventListener('click', () => {
      if (isMobile()) {
        body.classList.toggle('sidebar-open');
      } else {
        body.classList.toggle('sidebar-collapsed');
      }
    });
  }

  if (backdrop) {
    backdrop.addEventListener('click', () => {
      body.classList.remove('sidebar-open');
      body.classList.remove('sidebar-collapsed');
    });
  }

  document.querySelectorAll('.nav-group-toggle').forEach((btn) => {
    btn.addEventListener('click', () => {
      const group = btn.closest('.nav-group');
      if (!group) return;
      group.classList.toggle('open');
    });
  });

  const modulesToggle = document.getElementById('modulesMenuToggle');
  const modulesDropdown = modulesToggle?.closest('.top-module-dropdown');

  if (modulesToggle && modulesDropdown) {
    modulesToggle.addEventListener('click', (event) => {
      event.stopPropagation();
      const open = modulesDropdown.classList.toggle('open');
      modulesToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    document.addEventListener('click', (event) => {
      if (!modulesDropdown.contains(event.target)) {
        modulesDropdown.classList.remove('open');
        modulesToggle.setAttribute('aria-expanded', 'false');
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        modulesDropdown.classList.remove('open');
        modulesToggle.setAttribute('aria-expanded', 'false');
      }
    });
  }

  document.querySelectorAll('.sidebar-nav a.nav-link').forEach((link) => {
    link.addEventListener('click', () => {
      if (isMobile()) closeMobileSidebar();
    });
  });

  window.addEventListener('resize', () => {
    if (!isMobile()) {
      body.classList.remove('sidebar-open');
    }
  });
})();
