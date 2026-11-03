<?php
/**
 * Ficha de Ingreso (page-ingresos.php?ficha=ID, &editar para abrir editando).
 * Mismo patrón de Ver/Editar que Contactos y Empresas.
 * - Pagado y Enviado no se editan como palomita: "Registrar pago" usa el mismo
 *   endpoint de la tabla (recibo por correo); quitarlos no manda nada.
 * - Si ya se envió y cambian monto, documento, fecha o empresa, se avisa antes
 *   de guardar (no se reenvía solo).
 * - Fecha de cobro completa (no solo mes): Generar Mes evita duplicados por
 *   contrato + fecha exacta.
 * Recibe $talos_ficha_id desde page-ingresos.php.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$id         = $talos_ficha_id;
$editando   = isset( $_GET['editar'] );
$meses_c    = talos_meses_cortos();
$empresa    = get_field( 'income_company', $id );
$servicio   = get_field( 'income_service', $id );
$doc        = get_field( 'income_doc_type', $id );
$es_factura = ( 'factura' === $doc );
$enviado    = (bool) get_field( 'income_sent', $id );
$pagado     = (bool) get_field( 'income_paid', $id );
$fecha_raw  = preg_replace( '/\D/', '', (string) get_field( 'income_month', $id, false ) );
$pago_raw   = preg_replace( '/\D/', '', (string) get_field( 'income_payment_date', $id, false ) );
$precio     = (float) get_field( 'income_unit_price', $id );
$total      = (float) get_field( 'income_total', $id );
$recordar   = (bool) get_field( 'income_reminder_allowed', $id );
$contrato   = (int) get_post_meta( $id, '_talos_contract_id', true );
$empresa_id = $empresa instanceof WP_Post ? $empresa->ID : 0;

$fmt_fecha = function( $d ) use ( $meses_c ) {
    if ( strlen( $d ) < 8 ) return '—';
    return (int) substr( $d, 6, 2 ) . '/' . $meses_c[ (int) substr( $d, 4, 2 ) - 1 ] . '/' . substr( $d, 0, 4 );
};
$iso = function( $d ) {
    return strlen( $d ) >= 8 ? substr( $d, 0, 4 ) . '-' . substr( $d, 4, 2 ) . '-' . substr( $d, 6, 2 ) : '';
};
$mes_titulo = strlen( $fecha_raw ) >= 6 ? talos_meses_es()[ (int) substr( $fecha_raw, 4, 2 ) ] . ' ' . substr( $fecha_raw, 0, 4 ) : '';
$atrasado   = ! $pagado && strlen( $fecha_raw ) >= 6 && substr( $fecha_raw, 0, 6 ) < current_time( 'Ym' );

$servicios_cat = get_posts( array( 'post_type' => 'talos_service_cat', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
$empresas      = get_posts( array( 'post_type' => 'talos_company', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );

$n_facturas = ( $empresa_id && function_exists( 'talos_facturas_de' ) ) ? count( talos_facturas_de( $empresa_id, substr( $fecha_raw, 0, 6 ) ) ) : 0;
$contrato_titulo = ( $contrato && get_post( $contrato ) ) ? get_the_title( $contrato ) : '';

get_header();
talos_ficha_estilos();
?>

<a class="back-link" href="<?php echo esc_url( talos_ficha_url_volver() ); ?>"><svg viewBox="0 0 24 24"><use href="#i-chevron-left"/></svg>Volver a Ingresos</a>

<div class="page-head ficha-modo-vista">
  <div>
    <h1 class="page-title"><?php echo esc_html( ( $empresa_id ? $empresa->post_title : 'Sin empresa' ) . ( $mes_titulo ? ' · ' . $mes_titulo : '' ) ); ?></h1>
    <p class="page-sub">Ficha de Ingreso</p>
    <div class="ficha-pills">
      <span class="pill <?php echo $enviado ? 'ok' : 'pend'; ?>"><span class="pill-dot"></span><?php echo $enviado ? 'Enviado' : 'Sin enviar'; ?></span>
      <span class="pill <?php echo $pagado ? 'ok' : ( $atrasado ? 'mal' : 'pend' ); ?>"><span class="pill-dot"></span><?php echo $pagado ? 'Pagado ' . esc_html( $fmt_fecha( $pago_raw ) ) : ( $atrasado ? 'Atrasado' : 'Pendiente de pago' ); ?></span>
      <span class="pill class-nfs"><?php echo $es_factura ? 'Factura' : 'Nota de Venta'; ?></span>
    </div>
  </div>
  <div class="head-controls">
    <button type="button" class="btn-primary solo-vista" id="btnEditarFicha"><svg viewBox="0 0 24 24"><use href="#i-edit"/></svg>Editar</button>
    <button type="button" class="btn-ghost solo-editando" id="btnCancelarFicha">Cancelar</button>
    <button type="button" class="btn-confirm solo-editando" id="btnGuardarFicha">Guardar Cambios</button>
  </div>
</div>

<div class="ficha-card" id="fichaForm">
  <div class="ficha-section-label">Datos del cobro</div>
  <div class="data-grid">
    <div class="data-field">
      <label>Empresa</label>
      <div class="view-value"><?php if ( $empresa_id ) : ?><a class="email-link" href="<?php echo esc_url( add_query_arg( 'ficha', $empresa_id, home_url( '/empresas/' ) ) ); ?>"><?php echo esc_html( $empresa->post_title ); ?></a><?php else : ?>—<?php endif; ?></div>
      <select name="income_company" data-vigilar>
        <option value="">— Sin empresa —</option>
        <?php foreach ( $empresas as $e ) : ?>
          <option value="<?php echo esc_attr( $e->ID ); ?>" <?php selected( $empresa_id, $e->ID ); ?>><?php echo esc_html( $e->post_title ); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="data-field">
      <label>Servicio</label>
      <div class="view-value"><?php echo esc_html( $servicio instanceof WP_Post ? $servicio->post_title : '—' ); ?></div>
      <select name="income_service">
        <option value="">— Sin servicio —</option>
        <?php foreach ( $servicios_cat as $s ) : ?>
          <option value="<?php echo esc_attr( $s->ID ); ?>" <?php selected( $servicio instanceof WP_Post ? $servicio->ID : 0, $s->ID ); ?>><?php echo esc_html( $s->post_title ); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="data-field full">
      <label>Descripción (la que ve el cliente en su nota o factura)</label>
      <div class="view-value"><?php echo esc_html( get_field( 'income_description', $id ) ?: '—' ); ?></div>
      <input type="text" name="income_description" value="<?php echo esc_attr( get_field( 'income_description', $id ) ); ?>">
    </div>
    <div class="data-field">
      <label>Fecha de cobro</label>
      <div class="view-value"><?php echo esc_html( $fmt_fecha( $fecha_raw ) ); ?></div>
      <input type="date" name="income_month" value="<?php echo esc_attr( $iso( $fecha_raw ) ); ?>" data-vigilar data-fecha-cobro>
      <?php if ( $contrato ) : ?><span class="field-hint">Lo creó Generar Mes. Si cambias la fecha, Generar Mes podría volver a crear este cobro en la fecha original.</span><?php endif; ?>
    </div>
    <div class="data-field">
      <label>Documento</label>
      <div class="view-value"><?php echo $es_factura ? 'Factura (+16% IVA)' : 'Nota de Venta (sin IVA)'; ?></div>
      <select name="income_doc_type" data-vigilar id="fiDoc">
        <option value="nota_venta" <?php selected( ! $es_factura ); ?>>Nota de Venta (sin IVA)</option>
        <option value="factura" <?php selected( $es_factura ); ?>>Factura (+16% IVA)</option>
      </select>
    </div>
    <div class="data-field">
      <label>Monto sin IVA</label>
      <div class="view-value tabular"><?php echo esc_html( '$' . number_format( $precio, 2, '.', ',' ) ); ?></div>
      <input type="number" name="income_unit_price" value="<?php echo esc_attr( $precio ); ?>" step="any" data-vigilar id="fiMonto">
    </div>
    <div class="data-field locked">
      <label>Total</label>
      <div class="view-value tabular" id="fiTotal"><?php echo esc_html( '$' . number_format( $total, 2, '.', ',' ) ); ?></div>
    </div>
    <div class="data-field checkbox">
      <label>Recordatorios de cobranza</label>
      <div class="view-value"><?php echo $recordar ? 'Sí' : 'No'; ?></div>
      <input type="checkbox" name="income_reminder_allowed" value="1" <?php checked( $recordar ); ?>>
    </div>
    <div class="data-field locked">
      <label>Origen</label>
      <div class="view-value"><?php echo esc_html( $contrato ? 'Generar Mes · ' . ( $contrato_titulo ?: 'contrato #' . $contrato ) : 'Captura manual' ); ?></div>
    </div>
  </div>
</div>

<div class="ficha-card">
  <div class="ficha-section-label">Estado</div>
  <div class="estado-row">
    <div>
      <div class="estado-label">Nota / Factura</div>
      <div class="estado-valor"><?php echo $enviado ? 'Enviada al cliente' : 'Sin enviar'; ?></div>
    </div>
    <div class="estado-acciones">
      <?php if ( $enviado ) : ?>
        <button type="button" class="link-quieto" data-quitar="enviado">Marcar como no enviada</button>
      <?php else : ?>
        <span class="field-hint" style="display:block;">Se envía desde la tabla de Ingresos (agrupa por empresa y mes).</span>
      <?php endif; ?>
    </div>
  </div>
  <div class="estado-row">
    <div>
      <div class="estado-label">Pago</div>
      <div class="estado-valor"><?php echo $pagado ? 'Pagado el ' . esc_html( $fmt_fecha( $pago_raw ) ) : 'Pendiente'; ?></div>
    </div>
    <div class="estado-acciones">
      <?php if ( $pagado ) : ?>
        <span class="solo-editando-campo" hidden><input type="date" name="income_payment_date" value="<?php echo esc_attr( $iso( $pago_raw ) ); ?>" title="Corregir la fecha de pago"></span>
        <button type="button" class="link-quieto" data-quitar="pagado">Quitar pago</button>
      <?php else : ?>
        <button type="button" class="btn-pay" id="btnRegistrarPago">Registrar pago</button>
      <?php endif; ?>
    </div>
  </div>
  <?php if ( $es_factura && $empresa_id ) : ?>
  <div class="estado-row">
    <div>
      <div class="estado-label">Factura CFDI de <?php echo esc_html( $empresa->post_title . ' · ' . $mes_titulo ); ?></div>
      <div class="estado-valor" id="fichaFacturasEstado"><?php echo $n_facturas ? esc_html( $n_facturas . ' factura' . ( 1 === $n_facturas ? '' : 's' ) ) : 'Falta subirla'; ?></div>
    </div>
    <div class="estado-acciones">
      <button type="button" class="attach-btn" id="btnFacturasFicha" style="display:flex;align-items:center;gap:5px;border:1px solid var(--border);background:var(--surface);color:var(--text-muted);border-radius:7px;padding:5px 10px;font-size:12px;font-weight:700;cursor:pointer;"><svg viewBox="0 0 24 24" style="width:13px;height:13px;"><use href="#i-paperclip"/></svg><span id="fichaFacturasBtn"><?php echo $n_facturas ? 'Ver facturas' : 'Subir'; ?></span></button>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php talos_ficha_historial( $id ); ?>

<div class="ficha-modo-vista ficha-acciones-pie">
  <button type="button" class="btn-danger solo-editando" id="btnEliminarFicha"><svg viewBox="0 0 24 24"><use href="#i-trash"/></svg>Eliminar Ingreso</button>
</div>

<div class="modal-overlay" id="modalAviso">
  <div class="modal-box">
    <div class="modal-icon" style="background:#fdf3d8;color:#8a6200;"><svg viewBox="0 0 24 24"><use href="#i-alert-clock"/></svg></div>
    <h4 id="modalAvisoTitulo">¿Guardar los cambios?</h4>
    <p id="modalAvisoTexto"></p>
    <div class="modal-actions">
      <button class="btn-ghost" data-close-modal>Cancelar</button>
      <button class="btn-confirm" id="btnConfirmarAviso">Sí, continuar</button>
    </div>
  </div>
</div>

<div class="toast" id="toastFicha"><svg viewBox="0 0 24 24"><use href="#i-check"/></svg><span id="toastFichaTexto"></span></div>

<script>
try{
  var talosNonce = '<?php echo esc_js( wp_create_nonce( 'talos_ingresos' ) ); ?>';
  var talosAjaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
  var fichaId = '<?php echo (int) $id; ?>';
  // Se conserva ?mes= para que "Volver" regrese al mismo listado.
  var fichaUrl = (function(){ var u = new URL(window.location.href); u.searchParams.delete('editar'); return u.pathname + u.search; })();
  var yaEnviado = <?php echo $enviado ? 'true' : 'false'; ?>;
  var esGenerado = <?php echo $contrato ? 'true' : 'false'; ?>;
  var empresaNombre = <?php echo wp_json_encode( $empresa_id ? $empresa->post_title : 'este cliente' ); ?>;

  function post(accion, datos){
    var form = new FormData();
    form.append('action', accion);
    form.append('nonce', talosNonce);
    Object.keys(datos).forEach(function(k){
      var v = datos[k];
      if (Array.isArray(v)) v.forEach(function(item){ form.append(k + '[]', item); });
      else form.append(k, v);
    });
    return fetch(talosAjaxUrl, { method: 'POST', body: form, credentials: 'same-origin' }).then(function(r){ return r.json(); });
  }
  function toast(texto){
    var t = document.getElementById('toastFicha');
    document.getElementById('toastFichaTexto').textContent = texto;
    t.classList.add('show');
    clearTimeout(t._t);
    t._t = setTimeout(function(){ t.classList.remove('show'); }, Math.max(3000, texto.length * 60));
  }
  function error(res, defecto){ toast(res && res.data && res.data.mensaje ? res.data.mensaje : defecto); }

  // Modal de aviso reutilizable: confirmar(titulo, texto, botón, color) -> Promise
  var modalAviso = document.getElementById('modalAviso');
  var alConfirmar = null;
  function confirmar(titulo, texto, boton, color){
    document.getElementById('modalAvisoTitulo').textContent = titulo;
    document.getElementById('modalAvisoTexto').textContent = texto;
    var b = document.getElementById('btnConfirmarAviso');
    b.textContent = boton;
    b.style.background = color || '';
    b.disabled = false;
    modalAviso.classList.add('show');
    return new Promise(function(resolve){ alConfirmar = resolve; });
  }
  modalAviso.querySelectorAll('[data-close-modal]').forEach(function(b){ b.addEventListener('click', function(){ modalAviso.classList.remove('show'); alConfirmar = null; }); });
  document.getElementById('btnConfirmarAviso').addEventListener('click', function(){
    this.disabled = true;
    modalAviso.classList.remove('show');
    if (alConfirmar){ var f = alConfirmar; alConfirmar = null; f(); }
  });

  // ===== Ver / Editar =====
  var campoFechaPago = document.querySelector('.solo-editando-campo');
  function entrarEdicion(){
    document.body.classList.add('editando');
    if (campoFechaPago) campoFechaPago.hidden = false;
  }
  <?php if ( $editando ) : ?>entrarEdicion();<?php endif; ?>
  document.getElementById('btnEditarFicha').addEventListener('click', entrarEdicion);
  document.getElementById('btnCancelarFicha').addEventListener('click', function(){ window.location.href = fichaUrl; });

  // Total en vivo (talos-core lo recalcula igual al guardar)
  var fiMonto = document.getElementById('fiMonto'), fiDoc = document.getElementById('fiDoc'), fiTotal = document.getElementById('fiTotal');
  function recalcTotal(){
    var m = parseFloat(fiMonto.value) || 0;
    var t = fiDoc.value === 'factura' ? Math.round(m * 116) / 100 : m;
    fiTotal.textContent = '$' + t.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  fiMonto.addEventListener('input', recalcTotal);
  fiDoc.addEventListener('change', recalcTotal);

  var iniciales = {};
  document.querySelectorAll('[data-vigilar]').forEach(function(c){ iniciales[c.name] = c.value; });

  function datosFicha(){
    var datos = { income_id: fichaId };
    document.querySelectorAll('#fichaForm [name], [name="income_payment_date"]').forEach(function(c){
      if (c.type === 'checkbox'){ if (c.checked) datos[c.name] = '1'; return; }
      datos[c.name] = c.value;
    });
    return datos;
  }
  function guardar(){
    var btn = document.getElementById('btnGuardarFicha');
    btn.disabled = true;
    post('talos_guardar_income', datosFicha()).then(function(res){
      if (!res.success){ btn.disabled = false; error(res, 'No se pudo guardar.'); return; }
      window.location.href = fichaUrl;
    }).catch(function(){ btn.disabled = false; toast('No se pudo guardar.'); });
  }
  document.getElementById('btnGuardarFicha').addEventListener('click', function(){
    var cambiaron = Object.keys(iniciales).filter(function(n){ return document.querySelector('[name="' + n + '"]').value !== iniciales[n]; });
    var avisos = [];
    if (yaEnviado && cambiaron.length){
      avisos.push(empresaNombre + ' ya recibió la nota o factura con los datos anteriores. Se guarda, pero no se reenvía nada: si hace falta, reenvíala tú desde la tabla.');
    }
    if (esGenerado && cambiaron.indexOf('income_month') !== -1){
      avisos.push('Este cobro lo creó Generar Mes. Si le cambias la fecha, Generar Mes podría volver a crearlo en la fecha original.');
    }
    if (!avisos.length){ guardar(); return; }
    confirmar('¿Guardar los cambios?', avisos.join(' '), 'Sí, guardar').then(guardar);
  });

  // ===== Registrar pago (mismo endpoint y correo que la tabla) =====
  var btnPago = document.getElementById('btnRegistrarPago');
  if (btnPago) btnPago.addEventListener('click', function(){
    confirmar('¿Registrar pago?', 'Se marcará como Pagado con la fecha de hoy y se enviará el recibo de pago a ' + empresaNombre + '.', 'Sí, registrar y enviar', 'var(--success)').then(function(){
      post('talos_marcar_pagado', { ids: [fichaId] }).then(function(res){
        if (!res.success){ error(res, 'No se pudo registrar el pago.'); return; }
        window.location.href = fichaUrl;
      });
    });
  });

  // ===== Quitar Enviado / Pagado (sin correo) =====
  document.querySelectorAll('[data-quitar]').forEach(function(b){
    b.addEventListener('click', function(){
      var campo = b.getAttribute('data-quitar');
      var texto = campo === 'pagado'
        ? 'Quedará como pendiente de pago y se borra la fecha de pago. No se manda ningún correo y el recibo que ya recibió el cliente no se retira.'
        : 'Quedará como no enviada. No se manda ningún correo; podrás enviarla otra vez desde la tabla.';
      confirmar(campo === 'pagado' ? '¿Quitar el pago?' : '¿Marcar como no enviada?', texto, 'Sí, quitar', 'var(--danger)').then(function(){
        post('talos_estado_income', { income_id: fichaId, campo: campo }).then(function(res){
          if (!res.success){ error(res, 'No se pudo cambiar.'); return; }
          window.location.href = fichaUrl;
        });
      });
    });
  });

  // ===== Facturas (modal compartido, includes/ajax-facturas.php) =====
  var btnFac = document.getElementById('btnFacturasFicha');
  if (btnFac) btnFac.addEventListener('click', function(){
    if (window.talosFacturas) window.talosFacturas.abrir('<?php echo (int) $empresa_id; ?>', empresaNombre, '<?php echo esc_js( substr( $fecha_raw, 0, 4 ) . '-' . substr( $fecha_raw, 4, 2 ) ); ?>', false);
  });
  document.addEventListener('talos:facturas', function(e){
    var n = e.detail.facturas.length;
    document.getElementById('fichaFacturasEstado').textContent = n ? n + ' factura' + (n === 1 ? '' : 's') : 'Falta subirla';
    document.getElementById('fichaFacturasBtn').textContent = n ? 'Ver facturas' : 'Subir';
  });

  // ===== Eliminar (papelera) =====
  document.getElementById('btnEliminarFicha').addEventListener('click', function(){
    confirmar('¿Eliminar este ingreso?', 'Se moverá a la papelera de WordPress (de ' + empresaNombre + ') — podrás restaurarlo desde ahí si fue un error.', 'Sí, eliminar', 'var(--danger)').then(function(){
      post('talos_eliminar_income', { ids: [fichaId] }).then(function(res){
        if (!res.success){ error(res, 'No se pudo eliminar.'); return; }
        window.location.href = '<?php echo esc_js( talos_ficha_url_volver() ); ?>';
      });
    });
  });
}catch(e){ console.error(e); }
</script>

<?php if ( function_exists( 'talos_facturas_modal' ) ) talos_facturas_modal(); ?>
<?php get_footer(); ?>
