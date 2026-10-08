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

  // Encabezados ordenables, compartido por cualquier tabla (Empresas/Contactos/
  // Servicios/Ingresos/Gastos/Equipo): basta con un <button class="sort-btn"
  // data-sort-key="x"> en el <th> y data-sort-x="valor" en cada <tr> del <tbody>.
  // data-sort-numeric en el botón activa comparación numérica en vez de texto.
  try {
    document.querySelectorAll('table').forEach(function (tabla) {
      var botones = tabla.querySelectorAll('.sort-btn');
      var tbody = tabla.querySelector('tbody');
      if (!botones.length || !tbody) return;
      var activo = { key: null, dir: 1 };
      botones.forEach(function (btn) {
        btn.addEventListener('click', function () {
          var key = btn.getAttribute('data-sort-key');
          var dir = (activo.key === key) ? -activo.dir : 1;
          activo = { key: key, dir: dir };
          botones.forEach(function (b) { b.classList.remove('active'); b.removeAttribute('data-dir'); });
          btn.classList.add('active');
          btn.setAttribute('data-dir', dir === 1 ? 'asc' : 'desc');
          var esNumerico = btn.hasAttribute('data-sort-numeric');
          var filas = Array.from(tbody.querySelectorAll('tr[data-sort-' + key + ']'));
          filas.sort(function (a, b) {
            var va = a.getAttribute('data-sort-' + key) || '';
            var vb = b.getAttribute('data-sort-' + key) || '';
            if (esNumerico) return ((parseFloat(va) || 0) - (parseFloat(vb) || 0)) * dir;
            return va.localeCompare(vb) * dir;
          });
          filas.forEach(function (fila) { tbody.appendChild(fila); });
        });
      });
    });
  } catch (e) {}
});
