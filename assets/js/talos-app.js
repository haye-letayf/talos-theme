// Talos 2.0 — comportamiento compartido del shell (sidebar + topbar) en todas las páginas.
document.addEventListener('DOMContentLoaded', function () {
  try {
    var menuBtn = document.getElementById('menuBtn');
    if (menuBtn) {
      menuBtn.addEventListener('click', function () {
        document.body.classList.toggle('sidebar-open');
      });
    }

    var themeBtn = document.getElementById('themeBtn');
    if (themeBtn) {
      themeBtn.addEventListener('click', function () {
        var root = document.documentElement;
        var current = root.getAttribute('data-theme');
        var isDark = current === 'dark' || (!current && window.matchMedia('(prefers-color-scheme: dark)').matches);
        root.setAttribute('data-theme', isDark ? 'light' : 'dark');
      });
    }
  } catch (e) {}
});
