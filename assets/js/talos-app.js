// Talos 2.0 — comportamiento compartido del shell (sidebar + topbar) en todas las páginas.
document.addEventListener('DOMContentLoaded', function () {
  try {
    var menuBtn = document.getElementById('menuBtn');
    if (menuBtn) {
      menuBtn.addEventListener('click', function () {
        document.body.classList.toggle('sidebar-open');
      });
    }
  } catch (e) {}
});
