const adminMenuButton = document.querySelector('[data-admin-menu-toggle]');
const adminMenu = document.querySelector('[data-admin-nav]');
if (adminMenuButton && adminMenu) {
  const closeAdminMenu = () => {
    adminMenuButton.setAttribute('aria-expanded', 'false');
    adminMenuButton.setAttribute('aria-label', 'Open navigation');
    adminMenu.classList.remove('is-open');
  };
  adminMenuButton.addEventListener('click', () => {
    const open = adminMenuButton.getAttribute('aria-expanded') === 'true';
    if (open) {
      closeAdminMenu();
      return;
    }
    adminMenuButton.setAttribute('aria-expanded', 'true');
    adminMenuButton.setAttribute('aria-label', 'Close navigation');
    adminMenu.classList.add('is-open');
  });
  adminMenu.addEventListener('click', event => {
    if (event.target.closest('a')) {
      closeAdminMenu();
    }
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && adminMenuButton.getAttribute('aria-expanded') === 'true') {
      closeAdminMenu();
      adminMenuButton.focus();
    }
  });
  document.addEventListener('click', event => {
    if (adminMenuButton.getAttribute('aria-expanded') === 'true' && !adminMenu.contains(event.target) && !adminMenuButton.contains(event.target)) {
      closeAdminMenu();
    }
  });
  window.addEventListener('resize', () => {
    if (window.innerWidth > 760) closeAdminMenu();
  }, { passive: true });
}

const manualForm = document.querySelector('.manual-form');
manualForm?.addEventListener('submit', event => {
  const local = manualForm.elements.local_start.value;
  if (local) manualForm.elements.start.value = local.length === 16 ? `${local.replace('T', ' ')}:00` : local.replace('T', ' ');
  if (!manualForm.elements.start.value) event.preventDefault();
});

document.addEventListener('submit', event => {
  const message = event.target.dataset.confirm;
  if (message && !window.confirm(message)) event.preventDefault();
});
