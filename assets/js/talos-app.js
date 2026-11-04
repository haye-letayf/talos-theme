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

  // Selector de mes rápido (topbar): clic en el mes abre un popover con
  // Mes+Año para saltar directo, en vez de dar clic en < > uno por uno.
  try {
    var btnAbrirSelectorMes = document.getElementById('btnAbrirSelectorMes');
    var popoverSelectorMes = document.getElementById('popoverSelectorMes');
    if (btnAbrirSelectorMes && popoverSelectorMes) {
      btnAbrirSelectorMes.addEventListener('click', function (e) {
        e.stopPropagation();
        popoverSelectorMes.classList.toggle('show');
      });
      popoverSelectorMes.addEventListener('click', function (e) { e.stopPropagation(); });
      document.addEventListener('click', function () {
        popoverSelectorMes.classList.remove('show');
      });
      var btnIrAMes = document.getElementById('btnIrAMes');
      if (btnIrAMes) {
        btnIrAMes.addEventListener('click', function () {
          var mes = document.getElementById('selectorMesRapidoMes').value;
          var anio = document.getElementById('selectorMesRapidoAnio').value;
          if (!mes || !anio) return;
          var mesStr = mes.length < 2 ? '0' + mes : mes;
          var url = new URL(window.location.href);
          url.searchParams.set('mes', anio + '-' + mesStr);
          window.location.href = url.toString();
        });
      }
    }
  } catch (e) {}

  // Buscador global del header (includes/ajax-buscador.php). Sugerencias
  // desde 2 letras, agrupadas por tipo; flechas + Enter para abrir, Esc para
  // cerrar y "/" para saltar a la caja desde cualquier pantalla. En celular
  // la caja se abre con la lupa de la barra de arriba.
  try {
    var bInput = document.getElementById('talosBuscadorGlobal');
    var bLista = document.getElementById('talosBuscadorResultados');
    if (bInput && bLista && window.talosBuscador) {
      var bTimer = null, bSecuencia = 0, bActivo = -1;
      var bQuitarAcentos = function (t) { return t.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); };
      var bEscapar = function (t) {
        return String(t).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
      };
      // Resalta lo buscado sin importar acentos ni mayúsculas.
      var bResaltar = function (texto, q) {
        var normal = '', ok = true;
        for (var i = 0; i < texto.length; i++) {
          var n = bQuitarAcentos(texto[i]);
          if (n.length !== 1) { ok = false; break; }
          normal += n;
        }
        var pos = ok ? normal.indexOf(bQuitarAcentos(q)) : -1;
        if (pos < 0) return bEscapar(texto);
        return bEscapar(texto.slice(0, pos)) + '<mark>' + bEscapar(texto.slice(pos, pos + q.length)) + '</mark>' + bEscapar(texto.slice(pos + q.length));
      };
      var bItems = function () { return bLista.querySelectorAll('.search-item'); };
      var bMarcar = function (i) {
        var items = bItems();
        items.forEach(function (el, j) { el.classList.toggle('activo', j === i); el.setAttribute('aria-selected', j === i ? 'true' : 'false'); });
        bActivo = i;
        if (items[i]) items[i].scrollIntoView({ block: 'nearest' });
      };
      var bCerrarLista = function () {
        bLista.hidden = true;
        bInput.setAttribute('aria-expanded', 'false');
        bActivo = -1;
      };
      var bMostrar = function (html) {
        bLista.innerHTML = html;
        bLista.hidden = false;
        bInput.setAttribute('aria-expanded', 'true');
        bActivo = -1;
        if (bItems().length) bMarcar(0);
      };
      var bBuscar = function () {
        var q = bInput.value.trim();
        if (q.length < 2) { bSecuencia++; bCerrarLista(); return; }
        var mia = ++bSecuencia;
        var datos = new FormData();
        datos.append('action', 'talos_buscar_global');
        datos.append('nonce', talosBuscador.nonce);
        datos.append('q', q);
        fetch(talosBuscador.ajaxUrl, { method: 'POST', body: datos, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (resp) {
            if (mia !== bSecuencia) return; // llegó tarde: ya hay otra búsqueda
            if (!resp || !resp.success) {
              bMostrar('<div class="search-vacio">' + bEscapar((resp && resp.data && resp.data.mensaje) || 'No se pudo buscar.') + '</div>');
              return;
            }
            var grupos = resp.data.grupos || [];
            if (!grupos.length) { bMostrar('<div class="search-vacio">Sin resultados para “' + bEscapar(q) + '”.</div>'); return; }
            var html = '';
            grupos.forEach(function (g) {
              html += '<div class="search-grupo">' + bEscapar(g.grupo) + '</div>';
              g.items.forEach(function (it) {
                html += '<a class="search-item" role="option" href="' + bEscapar(it.url) + '">'
                  + '<svg viewBox="0 0 24 24"><use href="#' + bEscapar(g.icono) + '"/></svg>'
                  + '<span class="search-item-txt"><span class="search-item-titulo">' + bResaltar(it.titulo, q) + '</span>'
                  + (it.detalle ? '<span class="search-item-detalle">' + bResaltar(it.detalle, q) + '</span>' : '')
                  + '</span></a>';
              });
            });
            bMostrar(html);
          })
          .catch(function () { if (mia === bSecuencia) bMostrar('<div class="search-vacio">No se pudo buscar. Revisa tu conexión.</div>'); });
      };

      bInput.addEventListener('input', function () {
        clearTimeout(bTimer);
        bTimer = setTimeout(bBuscar, 200);
      });
      bInput.addEventListener('focus', function () {
        if (bLista.innerHTML && bInput.value.trim().length >= 2) { bLista.hidden = false; bInput.setAttribute('aria-expanded', 'true'); }
      });
      bInput.addEventListener('keydown', function (e) {
        var items = bItems();
        if (e.key === 'ArrowDown' && items.length) { e.preventDefault(); bMarcar((bActivo + 1) % items.length); }
        else if (e.key === 'ArrowUp' && items.length) { e.preventDefault(); bMarcar((bActivo - 1 + items.length) % items.length); }
        else if (e.key === 'Enter') {
          e.preventDefault();
          var elegido = items[bActivo] || items[0];
          if (elegido && !bLista.hidden) window.location.href = elegido.href;
        } else if (e.key === 'Escape') {
          if (!bLista.hidden) bCerrarLista();
          else { bInput.blur(); document.body.classList.remove('buscador-abierto'); }
        }
      });
      bLista.addEventListener('mousemove', function (e) {
        var item = e.target.closest('.search-item');
        if (!item) return;
        var i = Array.prototype.indexOf.call(bItems(), item);
        if (i !== bActivo) bMarcar(i);
      });
      document.addEventListener('click', function (e) {
        if (!e.target.closest('#talosBuscadorCaja') && !e.target.closest('#talosBuscadorAbrir')) {
          bCerrarLista();
          document.body.classList.remove('buscador-abierto');
        }
      });
      // "/" salta a la caja, salvo que ya se esté escribiendo en otro campo.
      document.addEventListener('keydown', function (e) {
        if (e.key !== '/' || e.metaKey || e.ctrlKey || e.altKey) return;
        var t = e.target;
        if (t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName))) return;
        e.preventDefault();
        document.body.classList.add('buscador-abierto');
        bInput.focus();
        bInput.select();
      });
      var bAbrir = document.getElementById('talosBuscadorAbrir');
      if (bAbrir) bAbrir.addEventListener('click', function () {
        document.body.classList.add('buscador-abierto');
        bInput.focus();
      });
      var bCerrar = document.getElementById('talosBuscadorCerrar');
      if (bCerrar) bCerrar.addEventListener('click', function () {
        bInput.value = '';
        bCerrarLista();
        document.body.classList.remove('buscador-abierto');
      });
    }
  } catch (e) {}

  // Oportunidad abierta desde el buscador: el Kanban llega con ?op=ID y se
  // resalta su tarjeta (mientras no exista la ficha de Oportunidad).
  try {
    var opId = new URLSearchParams(window.location.search).get('op');
    if (opId && /^\d+$/.test(opId)) {
      var tarjeta = document.querySelector('.deal-card[data-id="' + opId + '"]');
      if (tarjeta) {
        tarjeta.classList.add('resaltada');
        tarjeta.scrollIntoView({ block: 'center', inline: 'center' });
      }
    }
  } catch (e) {}
});
