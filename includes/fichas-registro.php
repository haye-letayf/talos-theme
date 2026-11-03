<?php
/**
 * Piezas compartidas de las fichas de Ingreso y de Gasto (fichas/*.php):
 * estilos, historial de cambios (talos_cambios_de() de talos-core) y la liga
 * de Volver, que regresa al listado con el mismo mes (?mes= o la cookie del
 * mes activo). El filtro rápido y el scroll los recuerda el listado en
 * sessionStorage.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function talos_ficha_url_volver() {
    return remove_query_arg( array( 'ficha', 'editar' ) );
}

function talos_ficha_url( $id, $editar = false ) {
    $args = array( 'ficha' => (int) $id );
    if ( $editar ) $args['editar'] = 1;
    return add_query_arg( $args );
}

function talos_ficha_estilos() {
    ?>
    <style>
      .ficha-section-label{font-size:11px;text-transform:uppercase;letter-spacing:.08em;font-weight:800;color:var(--text-muted);margin:0 0 12px;}
      .ficha-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px 22px;margin-bottom:16px;}
      .ficha-modo-vista .solo-editando{display:none;}
      body.editando .ficha-modo-vista .solo-vista{display:none;}
      body.editando .ficha-modo-vista .solo-editando{display:inline-flex;}
      .data-field.checkbox{display:flex;flex-direction:row;align-items:center;gap:8px;}
      .data-field.checkbox label{margin-bottom:0;}
      .data-field.checkbox input[type="checkbox"]{display:none;width:auto;flex:none;}
      body.editando .data-field.checkbox input[type="checkbox"]{display:inline-block;}
      body.editando .data-field.checkbox .view-value{display:none;}
      .data-field .field-hint{display:none;margin-top:4px;}
      body.editando .data-field .field-hint{display:block;}
      .ficha-pills{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px;}
      .pill.ok{background:var(--success-soft);color:var(--success);}
      .pill.ok .pill-dot{background:var(--success);}
      .pill.pend{background:#fdf3d8;color:#8a6200;}
      .pill.pend .pill-dot{background:#c99500;}
      .pill.mal{background:var(--danger-soft);color:var(--danger);}
      .pill.mal .pill-dot{background:var(--danger);}
      .estado-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid var(--border);}
      .estado-row:last-child{border-bottom:none;padding-bottom:0;}
      .estado-row:first-of-type{padding-top:0;}
      .estado-row .estado-label{font-size:10.5px;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);font-weight:700;}
      .estado-row .estado-valor{font-size:13.5px;font-weight:700;margin-top:2px;}
      .estado-row .estado-acciones{display:flex;gap:8px;align-items:center;}
      .estado-row input[type="date"]{border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:6px 9px;font:inherit;font-size:13px;}
      .link-quieto{border:none;background:none;color:var(--text-muted);font:inherit;font-size:12px;font-weight:700;cursor:pointer;text-decoration:underline;padding:0;}
      .link-quieto:hover{color:var(--danger);}
      table.historial{width:100%;border-collapse:collapse;font-size:12.5px;}
      table.historial th{text-align:left;font-size:10.5px;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);font-weight:700;padding:0 10px 8px 0;border-bottom:1px solid var(--border);}
      table.historial td{padding:8px 10px 8px 0;border-bottom:1px solid var(--border);vertical-align:top;}
      table.historial tr:last-child td{border-bottom:none;}
      table.historial .antes{color:var(--text-muted);text-decoration:line-through;}
      table.historial .despues{font-weight:700;}
      table.historial .quien{color:var(--text-muted);white-space:nowrap;}
      .historial-vacio{color:var(--text-muted);font-size:13px;}
      .ficha-acciones-pie{display:flex;justify-content:flex-end;margin-bottom:16px;}
    </style>
    <?php
}

/** Tarjeta "Historial de cambios" de un Ingreso o Gasto. */
function talos_ficha_historial( $post_id ) {
    $filas = function_exists( 'talos_cambios_de' ) ? talos_cambios_de( $post_id ) : array();
    $meses = talos_meses_cortos();
    ?>
    <div class="ficha-card">
      <div class="ficha-section-label">Historial de cambios</div>
      <?php if ( empty( $filas ) ) : ?>
        <div class="historial-vacio">Sin cambios registrados todavía. El historial empezó a guardarse el 3 de noviembre de 2026.</div>
      <?php else : ?>
        <div class="table-scroll">
          <table class="historial">
            <thead><tr><th>Cuándo</th><th>Qué cambió</th><th>Antes → Después</th><th>Quién / desde</th></tr></thead>
            <tbody>
              <?php foreach ( $filas as $f ) :
                  $cuando = (int) date( 'j', $f['fecha'] ) . '/' . $meses[ (int) date( 'n', $f['fecha'] ) - 1 ] . '/' . date( 'Y', $f['fecha'] ) . ' ' . date( 'H:i', $f['fecha'] );
                  ?>
                <tr>
                  <td class="quien"><?php echo esc_html( $cuando ); ?></td>
                  <td><?php echo esc_html( $f['campo'] ); ?></td>
                  <td>
                    <?php if ( '' !== $f['antes'] || '' !== $f['despues'] ) : ?>
                      <span class="antes"><?php echo esc_html( $f['antes'] ); ?></span> → <span class="despues"><?php echo esc_html( $f['despues'] ); ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="quien"><?php echo esc_html( $f['usuario'] . ' · ' . $f['origen'] ); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
    <?php
}

/**
 * JS del listado (Ingresos y Gastos): recuerda el filtro rápido y el scroll
 * para que "Volver" de una ficha deje la tabla como estaba.
 */
function talos_listado_recordar_estado( $clave ) {
    ?>
    <script>
    (function(){
      var clave = 'talosListado_<?php echo esc_js( $clave ); ?>';
      var guardado = null;
      try { guardado = JSON.parse(sessionStorage.getItem(clave) || 'null'); } catch(e){}
      if (guardado && guardado.url === location.pathname + location.search){
        if (guardado.filtro){
          var btn = document.querySelector('[data-group-filter] button[data-filter="' + guardado.filtro + '"]');
          if (btn) btn.click();
        }
        if (guardado.scroll) window.scrollTo(0, guardado.scroll);
      }
      document.addEventListener('click', function(e){
        var liga = e.target.closest('a[data-ficha]');
        if (!liga) return;
        var activo = document.querySelector('[data-group-filter] button.active');
        try {
          sessionStorage.setItem(clave, JSON.stringify({
            url: location.pathname + location.search,
            filtro: activo ? activo.getAttribute('data-filter') : 'todos',
            scroll: window.scrollY
          }));
        } catch(e){}
      });
    })();
    </script>
    <?php
}
