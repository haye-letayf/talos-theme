<?php
/**
 * Servicios — catálogo de referencia de precios y frecuencia (talos_service_cat).
 * ?ficha=ID muestra la ficha completa (Ver/Editar) en vez de la grilla de
 * tarjetas — mismo patrón ya establecido en Empresas/Contactos. Campos ACF
 * planos, sin Group. El mockup variaba el ícono por tipo de servicio, pero
 * no existe ningún campo ACF que decida eso — se usa un ícono genérico
 * (i-tag) en toda la grilla.
 */

$talos_frecuencia_labels = array(
    'pago_unico'=>'Pago Único','por_hora'=>'Por Hora','semanal'=>'Semanal','quincenal'=>'Quincenal',
    'mensual'=>'Mensual','trimestral'=>'Trimestral','semestral'=>'Semestral','anual'=>'Anual',
);

$talos_ficha_id = isset( $_GET['ficha'] ) ? (int) $_GET['ficha'] : 0;
if ( $talos_ficha_id && 'talos_service_cat' !== get_post_type( $talos_ficha_id ) ) {
    $talos_ficha_id = 0;
}
$talos_ficha_editando = $talos_ficha_id && isset( $_GET['editar'] );

$talos_servicios_ids = get_posts( array(
    'post_type'      => 'talos_service_cat',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'orderby'        => 'title',
    'order'          => 'ASC',
) );

$talos_total_catalogo = count( $talos_servicios_ids );
$talos_recurrentes = 0;
$talos_pago_unico = 0;
$talos_suma_precios = 0.0;
$talos_conteo_precios = 0;
foreach ( $talos_servicios_ids as $id ) {
    $frecuencia = get_field( 'service_ref_frequency', $id );
    if ( 'pago_unico' === $frecuencia ) {
        $talos_pago_unico++;
    } elseif ( $frecuencia ) {
        $talos_recurrentes++;
    }
    $precio_mxn = (float) get_field( 'service_ref_price_mxn', $id );
    if ( $precio_mxn > 0 ) {
        $talos_suma_precios += $precio_mxn;
        $talos_conteo_precios++;
    }
}
$talos_precio_promedio = $talos_conteo_precios > 0 ? $talos_suma_precios / $talos_conteo_precios : 0;

if ( $talos_ficha_id ) {
    $f = array(
        'nombre'                 => get_the_title( $talos_ficha_id ),
        'service_description'   => get_field( 'service_description', $talos_ficha_id ),
        'service_ref_frequency' => get_field( 'service_ref_frequency', $talos_ficha_id ),
        'service_ref_price_mxn' => get_field( 'service_ref_price_mxn', $talos_ficha_id ),
        'service_ref_price_usd' => get_field( 'service_ref_price_usd', $talos_ficha_id ),
    );

    // No hay relación directa a Empresa: company_services es un repeater dentro
    // de cada Empresa (sub-campo service_item post_object) — se cuenta a mano.
    $talos_ficha_empresas_n = 0;
    $talos_todas_empresas_ids = get_posts( array(
        'post_type' => 'talos_company', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids',
    ) );
    foreach ( $talos_todas_empresas_ids as $empresa_id ) {
        $filas = get_field( 'company_services', $empresa_id );
        if ( ! $filas ) continue;
        foreach ( $filas as $fila ) {
            $item = $fila['service_item'] ?? null;
            $item_id = ( $item instanceof WP_Post ) ? $item->ID : (int) $item;
            if ( $item_id === $talos_ficha_id ) {
                $talos_ficha_empresas_n++;
                break;
            }
        }
    }
}

get_header();
?>

<style>
  .ficha-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px 22px;margin-bottom:4px;}
  .ficha-section-label{font-size:11px;text-transform:uppercase;letter-spacing:.08em;font-weight:800;color:var(--text-muted);margin:26px 0 10px;}
  .ficha-section-label:first-of-type{margin-top:0;}
  .ficha-modo-vista .solo-editando{display:none;}
  body.editando .ficha-modo-vista .solo-vista{display:none;}
  body.editando .ficha-modo-vista .solo-editando{display:inline-flex;}

  .service-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;}
  @media (max-width:1080px){.service-grid{grid-template-columns:repeat(2,minmax(0,1fr));}}
  @media (max-width:620px){.service-grid{grid-template-columns:1fr;}}
  .service-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px;display:flex;flex-direction:column;gap:12px;}
  .service-top{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;}
  .service-icon{width:38px;height:38px;border-radius:10px;background:var(--accent-soft);color:var(--accent);display:flex;align-items:center;justify-content:center;flex:none;}
  .service-icon svg{width:19px;height:19px;}
  .freq-pill{padding:3px 10px;border-radius:99px;font-size:11px;font-weight:700;white-space:nowrap;}
  .freq-pill.recurrente{background:var(--success-soft);color:var(--success);}
  .freq-pill.unico{background:var(--surface-2);color:var(--text-muted);border:1px solid var(--border);}
  .service-name{font-size:15.5px;font-weight:800;margin:0;}
  .service-desc{font-size:12.5px;color:var(--text-muted);margin:0;line-height:1.5;flex:1;}
  .service-prices{display:flex;align-items:baseline;justify-content:space-between;gap:10px;border-top:1px solid var(--border);padding-top:12px;}
  .price-mxn{font-size:19px;font-weight:800;}
  .price-mxn small{font-size:11px;color:var(--text-muted);font-weight:600;}
  .price-usd{font-size:12px;color:var(--text-muted);font-weight:600;}
  .service-actions{display:flex;align-items:center;gap:6px;border-top:1px solid var(--border);padding-top:12px;}
  .service-actions .action-btn{flex:1;height:32px;gap:6px;font-size:12px;font-weight:700;}
</style>

<?php if ( $talos_ficha_id ) : ?>

  <a class="back-link" href="<?php echo esc_url( remove_query_arg( array( 'ficha', 'editar' ) ) ); ?>"><svg viewBox="0 0 24 24"><use href="#i-chevron-left"/></svg>Volver al catálogo</a>

  <div class="page-head ficha-modo-vista">
    <div>
      <h1 class="page-title"><?php echo esc_html( $f['nombre'] ); ?></h1>
      <p class="page-sub">Ficha de Servicio</p>
    </div>
    <div class="head-controls">
      <button type="button" class="btn-primary solo-vista" id="btnEditarFicha"><svg viewBox="0 0 24 24"><use href="#i-edit"/></svg>Editar</button>
      <button type="button" class="btn-ghost solo-editando" id="btnCancelarFicha">Cancelar</button>
      <button type="button" class="btn-confirm solo-editando" id="btnGuardarFicha">Guardar Cambios</button>
    </div>
  </div>

  <div class="ficha-card">
    <div class="ficha-section-label">Datos del Servicio</div>
    <div class="data-grid">
      <div class="data-field">
        <label>Nombre</label>
        <div class="view-value"><?php echo esc_html( $f['nombre'] ); ?></div>
        <input type="text" name="nombre" value="<?php echo esc_attr( $f['nombre'] ); ?>">
      </div>
      <div class="data-field">
        <label>Frecuencia</label>
        <div class="view-value"><?php echo esc_html( $f['service_ref_frequency'] ? ( $talos_frecuencia_labels[ $f['service_ref_frequency'] ] ?? $f['service_ref_frequency'] ) : '—' ); ?></div>
        <select name="service_ref_frequency">
          <option value="">— Sin definir —</option>
          <?php foreach ( $talos_frecuencia_labels as $val => $label ) : ?>
            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $val, $f['service_ref_frequency'] ); ?>><?php echo esc_html( $label ); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="data-field">
        <label>Precio MXN</label>
        <div class="view-value"><?php echo esc_html( talos_fmt_mxn( $f['service_ref_price_mxn'] ) ); ?></div>
        <input type="number" name="service_ref_price_mxn" value="<?php echo esc_attr( $f['service_ref_price_mxn'] ); ?>" step="1" min="0">
      </div>
      <div class="data-field">
        <label>Precio USD</label>
        <div class="view-value"><?php echo esc_html( $f['service_ref_price_usd'] > 0 ? '$' . number_format( (float) $f['service_ref_price_usd'], 0 ) . ' USD' : '—' ); ?></div>
        <input type="number" name="service_ref_price_usd" value="<?php echo esc_attr( $f['service_ref_price_usd'] ); ?>" step="1" min="0">
      </div>
      <div class="data-field full">
        <label>Descripción</label>
        <div class="view-value"><?php echo esc_html( $f['service_description'] ?: '—' ); ?></div>
        <textarea name="service_description" rows="3"><?php echo esc_textarea( $f['service_description'] ); ?></textarea>
      </div>
    </div>
  </div>

  <div class="ficha-modo-vista" style="display:flex;justify-content:flex-end;margin-top:4px;">
    <button type="button" class="btn-danger solo-editando" id="btnEliminarServicio"><svg viewBox="0 0 24 24"><use href="#i-trash"/></svg>Eliminar Servicio</button>
  </div>

  <div class="modal-overlay" id="modalEliminarServicio">
    <div class="modal-box">
      <div class="modal-icon" style="background:var(--danger-soft);color:var(--danger);"><svg viewBox="0 0 24 24"><use href="#i-trash"/></svg></div>
      <h4>¿Eliminar "<?php echo esc_html( $f['nombre'] ); ?>"?</h4>
      <p>
        Se moverá a la papelera de WordPress (recuperable desde wp-admin, no es borrado permanente).
        <?php if ( $talos_ficha_empresas_n ) : ?>
          <br><br>Este servicio está contratado por <?php echo esc_html( $talos_ficha_empresas_n ); ?> empresa(s) — esas líneas de contrato seguirán existiendo, pero apuntando a un servicio eliminado.
        <?php endif; ?>
      </p>
      <div class="modal-actions">
        <button class="btn-ghost" data-close-modal>Cancelar</button>
        <button class="btn-danger" id="btnConfirmarEliminarServicio">Sí, eliminar</button>
      </div>
    </div>
  </div>

<?php else : ?>

<div class="page-head">
  <div>
    <h1 class="page-title">Servicios</h1>
    <p class="page-sub">Catálogo de referencia de precios y frecuencia</p>
  </div>
  <button class="btn-primary" id="btnNuevoServicio"><svg viewBox="0 0 24 24"><use href="#i-plus"/></svg>Nuevo Servicio</button>
</div>

<div class="count-strip">
  <div class="count-box accent"><span class="label">Total en Catálogo</span><span class="value tabular"><?php echo esc_html( $talos_total_catalogo ); ?></span></div>
  <div class="count-box success"><span class="label">Recurrentes</span><span class="value tabular"><?php echo esc_html( $talos_recurrentes ); ?></span></div>
  <div class="count-box"><span class="label">Pago Único</span><span class="value tabular"><?php echo esc_html( $talos_pago_unico ); ?></span></div>
  <div class="count-box gold"><span class="label">Precio Promedio</span><span class="value tabular"><?php echo esc_html( talos_fmt_mxn( $talos_precio_promedio ) ); ?></span></div>
</div>

<div class="filter-bar">
  <div class="filter-search"><svg viewBox="0 0 24 24"><use href="#i-search"/></svg><input type="text" id="buscarServicio" placeholder="Buscar servicio…"></div>
  <div class="seg" data-filter-group="frecuencia">
    <button class="active" data-filter="todos">Todos</button>
    <button data-filter="mensual">Mensual</button>
    <button data-filter="anual">Anual</button>
    <button data-filter="pago_unico">Pago Único</button>
  </div>
  <div class="filter-spacer"></div>
</div>

<div class="service-grid" id="serviceGrid">
  <?php if ( empty( $talos_servicios_ids ) ) : ?>
    <p style="color:var(--text-muted);">Sin servicios en el catálogo.</p>
  <?php endif; ?>
  <?php foreach ( $talos_servicios_ids as $id ) :
      $nombre      = get_the_title( $id );
      $frecuencia  = get_field( 'service_ref_frequency', $id );
      $descripcion = get_field( 'service_description', $id );
      $precio_mxn  = (float) get_field( 'service_ref_price_mxn', $id );
      $precio_usd  = (float) get_field( 'service_ref_price_usd', $id );
      $es_unico    = 'pago_unico' === $frecuencia;
      ?>
      <div class="service-card" data-freq="<?php echo esc_attr( $frecuencia ); ?>" data-nombre="<?php echo esc_attr( $nombre ); ?>">
        <div class="service-top">
          <div class="service-icon"><svg viewBox="0 0 24 24"><use href="#i-tag"/></svg></div>
          <?php if ( $frecuencia ) : ?>
            <span class="freq-pill <?php echo $es_unico ? 'unico' : 'recurrente'; ?>"><?php echo esc_html( $talos_frecuencia_labels[ $frecuencia ] ?? $frecuencia ); ?></span>
          <?php endif; ?>
        </div>
        <h3 class="service-name"><?php echo esc_html( $nombre ); ?></h3>
        <p class="service-desc"><?php echo esc_html( $descripcion ?: 'Sin descripción.' ); ?></p>
        <div class="service-prices">
          <span class="price-mxn tabular"><?php echo esc_html( talos_fmt_mxn( $precio_mxn ) ); ?><small> MXN</small></span>
          <span class="price-usd tabular"><?php echo $precio_usd > 0 ? esc_html( '$' . number_format( $precio_usd, 0 ) . ' USD' ) : '—'; ?></span>
        </div>
        <div class="service-actions">
          <a class="action-btn view" href="<?php echo esc_url( add_query_arg( 'ficha', $id ) ); ?>"><svg viewBox="0 0 24 24"><use href="#i-eye"/></svg>Ver</a>
          <a class="action-btn edit" href="<?php echo esc_url( add_query_arg( array( 'ficha' => $id, 'editar' => 1 ) ) ); ?>"><svg viewBox="0 0 24 24"><use href="#i-edit"/></svg>Editar</a>
        </div>
      </div>
  <?php endforeach; ?>
</div>

<div class="modal-overlay" id="modalNuevoServicio">
  <div class="modal-box">
    <h4>Nuevo Servicio</h4>
    <p class="modal-sub">Captura lo esencial — precio y descripción se completan justo después, en la ficha.</p>
    <div class="form-field">
      <label for="campoNombreServicio">Nombre</label>
      <input type="text" id="campoNombreServicio" placeholder="Ej. Gestión de Redes Sociales">
    </div>
    <div class="form-field">
      <label for="campoFrecuenciaServicio">Frecuencia</label>
      <select id="campoFrecuenciaServicio">
        <?php foreach ( $talos_frecuencia_labels as $val => $label ) : ?>
          <option value="<?php echo esc_attr( $val ); ?>" <?php selected( 'mensual', $val ); ?>><?php echo esc_html( $label ); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="modal-actions">
      <button class="btn-ghost" data-close-modal>Cancelar</button>
      <button class="btn-confirm" id="btnConfirmarNuevoServicio">Crear y Continuar</button>
    </div>
  </div>
</div>

<?php endif; ?>

<div class="toast" id="toastServicios"><svg viewBox="0 0 24 24"><use href="#i-check"/></svg><span id="toastServiciosTexto"></span></div>

<script>
try{
  var talosNonceSrv = '<?php echo esc_js( wp_create_nonce( 'talos_servicios' ) ); ?>';
  var talosAjaxUrlSrv = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

  function mostrarToastServicios(texto){
    var toast = document.getElementById('toastServicios');
    document.getElementById('toastServiciosTexto').textContent = texto;
    toast.classList.add('show');
    clearTimeout(toast._t);
    toast._t = setTimeout(function(){ toast.classList.remove('show'); }, 2600);
  }

  <?php if ( $talos_ficha_id ) : ?>
  // ===== Ficha: alternar vista/edición + guardar + eliminar =====
  <?php if ( $talos_ficha_editando ) : ?>document.body.classList.add('editando');<?php endif; ?>

  document.getElementById('btnEditarFicha').addEventListener('click', function(){
    document.body.classList.add('editando');
  });
  document.getElementById('btnCancelarFicha').addEventListener('click', function(){
    window.location.href = window.location.pathname + '?ficha=<?php echo (int) $talos_ficha_id; ?>';
  });
  document.getElementById('btnGuardarFicha').addEventListener('click', function(){
    var btn = this;
    btn.disabled = true;
    var form = new FormData();
    form.append('action', 'talos_guardar_servicio');
    form.append('nonce', talosNonceSrv);
    form.append('servicio_id', '<?php echo (int) $talos_ficha_id; ?>');
    document.querySelectorAll('.data-field input, .data-field select, .data-field textarea').forEach(function(campo){
      if (!campo.name) return;
      form.append(campo.name, campo.value);
    });
    fetch(talosAjaxUrlSrv, { method: 'POST', body: form, credentials: 'same-origin' }).then(function(r){ return r.json(); }).then(function(res){
      if (!res.success){
        mostrarToastServicios(res.data && res.data.mensaje ? res.data.mensaje : 'No se pudo guardar.');
        btn.disabled = false;
        return;
      }
      window.location.href = window.location.pathname + '?ficha=<?php echo (int) $talos_ficha_id; ?>';
    });
  });

  var modalEliminarServicio = document.getElementById('modalEliminarServicio');
  document.getElementById('btnEliminarServicio').addEventListener('click', function(){
    modalEliminarServicio.classList.add('show');
  });
  modalEliminarServicio.querySelectorAll('[data-close-modal]').forEach(function(b){ b.addEventListener('click', function(){ modalEliminarServicio.classList.remove('show'); }); });
  modalEliminarServicio.addEventListener('click', function(e){ if (e.target === modalEliminarServicio) modalEliminarServicio.classList.remove('show'); });
  document.getElementById('btnConfirmarEliminarServicio').addEventListener('click', function(){
    var btn = this;
    btn.disabled = true;
    var form = new FormData();
    form.append('action', 'talos_eliminar_servicio');
    form.append('nonce', talosNonceSrv);
    form.append('servicio_id', '<?php echo (int) $talos_ficha_id; ?>');
    fetch(talosAjaxUrlSrv, { method: 'POST', body: form, credentials: 'same-origin' }).then(function(r){ return r.json(); }).then(function(res){
      if (!res.success){
        mostrarToastServicios(res.data && res.data.mensaje ? res.data.mensaje : 'No se pudo eliminar.');
        btn.disabled = false;
        return;
      }
      window.location.href = window.location.pathname;
    });
  });

  <?php else : ?>
  // ===== Listado: filtros + buscador + Nuevo Servicio =====
  function aplicarFiltrosServicios(){
    var freq = document.querySelector('[data-filter-group="frecuencia"] button.active').getAttribute('data-filter');
    var texto = (document.getElementById('buscarServicio').value || '').trim().toLowerCase();
    document.querySelectorAll('#serviceGrid .service-card').forEach(function(card){
      var okFreq = (freq === 'todos') || (card.getAttribute('data-freq') === freq);
      var okTexto = !texto || (card.getAttribute('data-nombre') || '').toLowerCase().indexOf(texto) !== -1;
      card.hidden = !(okFreq && okTexto);
    });
  }
  document.querySelectorAll('.seg').forEach(function(seg){
    seg.querySelectorAll('button').forEach(function(btn){
      btn.addEventListener('click', function(){
        seg.querySelectorAll('button').forEach(function(b){b.classList.remove('active');});
        btn.classList.add('active');
        aplicarFiltrosServicios();
      });
    });
  });
  document.getElementById('buscarServicio').addEventListener('input', aplicarFiltrosServicios);

  var modalNuevoServicio = document.getElementById('modalNuevoServicio');
  document.getElementById('btnNuevoServicio').addEventListener('click', function(){
    document.getElementById('campoNombreServicio').value = '';
    modalNuevoServicio.classList.add('show');
  });
  modalNuevoServicio.querySelectorAll('[data-close-modal]').forEach(function(b){ b.addEventListener('click', function(){ modalNuevoServicio.classList.remove('show'); }); });
  modalNuevoServicio.addEventListener('click', function(e){ if (e.target === modalNuevoServicio) modalNuevoServicio.classList.remove('show'); });

  document.getElementById('btnConfirmarNuevoServicio').addEventListener('click', function(){
    var nombre = document.getElementById('campoNombreServicio').value.trim();
    if (!nombre){ mostrarToastServicios('Captura el nombre del servicio'); return; }
    var btn = this;
    btn.disabled = true;
    var form = new FormData();
    form.append('action', 'talos_crear_servicio');
    form.append('nonce', talosNonceSrv);
    form.append('nombre', nombre);
    form.append('frecuencia', document.getElementById('campoFrecuenciaServicio').value);
    fetch(talosAjaxUrlSrv, { method: 'POST', body: form, credentials: 'same-origin' }).then(function(r){ return r.json(); }).then(function(res){
      if (!res.success){
        mostrarToastServicios(res.data && res.data.mensaje ? res.data.mensaje : 'No se pudo crear el servicio.');
        btn.disabled = false;
        return;
      }
      window.location.href = window.location.pathname + '?ficha=' + res.data.id + '&editar=1';
    });
  });
  <?php endif; ?>
}catch(e){ console.error(e); }
</script>

<?php get_footer(); ?>
