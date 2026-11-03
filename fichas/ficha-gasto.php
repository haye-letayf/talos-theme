<?php
/**
 * Ficha de Gasto (page-gastos.php?ficha=ID, &editar para abrir editando).
 * Mismo patrón de Ver/Editar que la ficha de Ingreso.
 * - Pagado no es palomita: "Registrar pago" usa el endpoint de la tabla (en
 *   nómina, con IVA opcional y recibo al colaborador); quitarlo no manda nada.
 * - Con Precio unitario, Monto = Cantidad × Precio (talos-core); sin él, manda
 *   el Monto capturado.
 * - Plantilla de origen y tipo se ven, no se editan.
 * Recibe $talos_ficha_id desde page-gastos.php.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$id        = $talos_ficha_id;
$editando  = isset( $_GET['editar'] );
$meses_c   = talos_meses_cortos();
$acf_campo = function( $n ) { return function_exists( 'acf_get_field' ) ? acf_get_field( $n ) : null; };
$opciones  = function( $n, $respaldo ) use ( $acf_campo ) { $f = $acf_campo( $n ); return ! empty( $f['choices'] ) ? $f['choices'] : $respaldo; };

$cats     = $opciones( 'expense_category', array( 'operacion' => 'Operación', 'personal' => 'Personal' ) );
$subcats  = $opciones( 'expense_subcategory', array() );
$metodos  = $opciones( 'expense_payment_method', array( 'amex' => 'AMEX', 'debito' => 'Débito', 'efectivo' => 'Efectivo', 'transferencia' => 'Transferencia' ) );
$tipos    = $opciones( 'expense_type', array( 'transaccion' => 'Transacción', 'plantilla' => 'Plantilla' ) );
$frecs    = $opciones( 'expense_frequency', array() );

$tipo        = (string) get_field( 'expense_type', $id, false );
$categoria   = (string) get_field( 'expense_category', $id, false );
$subcat      = (string) get_field( 'expense_subcategory', $id, false );
$metodo      = (string) get_field( 'expense_payment_method', $id, false );
$frecuencia  = (string) get_field( 'expense_frequency', $id, false );
$proveedor   = (string) get_field( 'expense_supplier', $id );
$descripcion = (string) get_field( 'expense_description', $id );
$miembro_id  = (int) get_field( 'expense_team_member', $id, false );
$plantilla   = (int) get_field( 'expense_source_template', $id, false );
$fecha_raw   = preg_replace( '/\D/', '', (string) get_field( 'expense_period', $id, false ) );
$pago_raw    = preg_replace( '/\D/', '', (string) get_field( 'expense_payment_date', $id, false ) );
$pagado      = (bool) get_field( 'expense_paid', $id );
$monto       = (float) get_field( 'expense_amount', $id );
$precio_raw  = get_field( 'expense_unit_price', $id, false );
$con_precio  = '' !== (string) $precio_raw && null !== $precio_raw;
$cantidad    = get_field( 'expense_quantity', $id, false );
$cantidad    = ( '' === (string) $cantidad || null === $cantidad ) ? 1 : (float) $cantidad;
$es_nomina   = function_exists( 'talos_nomina_es_quincena' ) && talos_nomina_es_quincena( $id );
$amex_ref    = (string) get_post_meta( $id, '_talos_amex_referencia', true );

$fmt_fecha = function( $d ) use ( $meses_c ) {
    if ( strlen( $d ) < 8 ) return '—';
    return (int) substr( $d, 6, 2 ) . '/' . $meses_c[ (int) substr( $d, 4, 2 ) - 1 ] . '/' . substr( $d, 0, 4 );
};
$iso = function( $d ) {
    return strlen( $d ) >= 8 ? substr( $d, 0, 4 ) . '-' . substr( $d, 4, 2 ) . '-' . substr( $d, 6, 2 ) : '';
};
$dinero   = function( $n ) { return '$' . number_format( (float) $n, 2, '.', ',' ); };
$atrasado = ! $pagado && 'transaccion' === $tipo && strlen( $fecha_raw ) >= 6 && substr( $fecha_raw, 0, 6 ) < current_time( 'Ym' );

$equipo = get_posts( array( 'post_type' => 'talos_team', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
$subcat_mapa = function_exists( 'talos_subcategorias_por_categoria' ) ? talos_subcategorias_por_categoria() : null;

get_header();
talos_ficha_estilos();
?>

<a class="back-link" href="<?php echo esc_url( talos_ficha_url_volver() ); ?>"><svg viewBox="0 0 24 24"><use href="#i-chevron-left"/></svg>Volver a Gastos</a>

<div class="page-head ficha-modo-vista">
  <div>
    <h1 class="page-title"><?php echo esc_html( ( $proveedor ?: 'Gasto' ) . ' · ' . $dinero( $monto ) ); ?></h1>
    <p class="page-sub">Ficha de Gasto<?php echo 'plantilla' === $tipo ? ' (plantilla recurrente)' : ''; ?></p>
    <div class="ficha-pills">
      <?php if ( 'plantilla' !== $tipo ) : ?>
        <span class="pill <?php echo $pagado ? 'ok' : ( $atrasado ? 'mal' : 'pend' ); ?>"><span class="pill-dot"></span><?php echo $pagado ? 'Pagado ' . esc_html( $fmt_fecha( $pago_raw ) ) : ( $atrasado ? 'Atrasado' : 'Pendiente de pago' ); ?></span>
      <?php endif; ?>
      <span class="pill class-nfs"><?php echo esc_html( $cats[ $categoria ] ?? $categoria ); ?></span>
      <?php if ( $es_nomina ) : ?><span class="pill class-nfs">Nómina</span><?php endif; ?>
    </div>
  </div>
  <div class="head-controls">
    <button type="button" class="btn-primary solo-vista" id="btnEditarFicha"><svg viewBox="0 0 24 24"><use href="#i-edit"/></svg>Editar</button>
    <button type="button" class="btn-ghost solo-editando" id="btnCancelarFicha">Cancelar</button>
    <button type="button" class="btn-confirm solo-editando" id="btnGuardarFicha">Guardar Cambios</button>
  </div>
</div>

<div class="ficha-card" id="fichaForm">
  <div class="ficha-section-label">Datos del gasto</div>
  <div class="data-grid">
    <div class="data-field">
      <label>Categoría</label>
      <div class="view-value"><?php echo esc_html( $cats[ $categoria ] ?? ( $categoria ?: '—' ) ); ?></div>
      <select name="expense_category" id="fgCategoria">
        <?php foreach ( $cats as $v => $l ) : ?>
          <option value="<?php echo esc_attr( $v ); ?>" <?php selected( $categoria, $v ); ?>><?php echo esc_html( $l ); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="data-field">
      <label>Subcategoría</label>
      <div class="view-value"><?php echo esc_html( $subcats[ $subcat ] ?? ( $subcat ?: '—' ) ); ?></div>
      <select name="expense_subcategory" id="fgSubcategoria">
        <?php foreach ( $subcats as $v => $l ) : ?>
          <option value="<?php echo esc_attr( $v ); ?>" <?php selected( $subcat, $v ); ?>><?php echo esc_html( $l ); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="data-field">
      <label>Proveedor</label>
      <div class="view-value"><?php echo esc_html( $proveedor ?: '—' ); ?></div>
      <input type="text" name="expense_supplier" value="<?php echo esc_attr( $proveedor ); ?>">
    </div>
    <div class="data-field">
      <label>Fecha</label>
      <div class="view-value"><?php echo esc_html( $fmt_fecha( $fecha_raw ) ); ?></div>
      <input type="date" name="expense_period" value="<?php echo esc_attr( $iso( $fecha_raw ) ); ?>" data-fecha-gasto>
      <?php if ( $plantilla ) : ?><span class="field-hint">Viene de una plantilla. Si lo cambias de mes, Generar Mes podría volver a crearlo en el mes original.</span><?php endif; ?>
    </div>
    <div class="data-field full">
      <label>Descripción</label>
      <div class="view-value"><?php echo esc_html( $descripcion ?: '—' ); ?></div>
      <input type="text" name="expense_description" value="<?php echo esc_attr( $descripcion ); ?>">
    </div>
    <div class="data-field">
      <label>Cantidad</label>
      <div class="view-value tabular"><?php echo $con_precio ? esc_html( rtrim( rtrim( number_format( $cantidad, 4, '.', ',' ), '0' ), '.' ) ) : '—'; ?></div>
      <input type="number" name="expense_quantity" value="<?php echo esc_attr( $con_precio ? $cantidad : 1 ); ?>" step="any" min="0" id="fgCantidad">
    </div>
    <div class="data-field">
      <label>Precio unitario</label>
      <div class="view-value tabular"><?php echo $con_precio ? esc_html( $dinero( $precio_raw ) ) : '—'; ?></div>
      <input type="number" name="expense_unit_price" value="<?php echo esc_attr( $con_precio ? (float) $precio_raw : '' ); ?>" step="any" id="fgPrecio" placeholder="Vacío = capturar el monto directo">
    </div>
    <div class="data-field">
      <label>Monto</label>
      <div class="view-value tabular"><?php echo esc_html( $dinero( $monto ) ); ?></div>
      <input type="number" name="expense_amount" value="<?php echo esc_attr( $monto ); ?>" step="any" id="fgMonto">
      <span class="field-hint" id="fgMontoHint">Con precio unitario, el monto es Cantidad × Precio.</span>
    </div>
    <div class="data-field">
      <label>Método de pago</label>
      <div class="view-value"><?php echo esc_html( $metodos[ $metodo ] ?? ( $metodo ?: '—' ) ); ?></div>
      <select name="expense_payment_method">
        <option value="">— Sin definir —</option>
        <?php foreach ( $metodos as $v => $l ) : ?>
          <option value="<?php echo esc_attr( $v ); ?>" <?php selected( $metodo, $v ); ?>><?php echo esc_html( $l ); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="data-field">
      <label>Miembro del equipo</label>
      <div class="view-value"><?php echo esc_html( $miembro_id ? get_the_title( $miembro_id ) : '—' ); ?></div>
      <select name="expense_team_member">
        <option value="">— Ninguno —</option>
        <?php foreach ( $equipo as $m ) : ?>
          <option value="<?php echo esc_attr( $m->ID ); ?>" <?php selected( $miembro_id, $m->ID ); ?>><?php echo esc_html( $m->post_title ); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="data-field locked">
      <label>Origen</label>
      <div class="view-value"><?php
        if ( $es_nomina ) {
            echo 'Nómina (quincena ' . (int) talos_nomina_numero_quincena( $id ) . ')';
        } elseif ( $plantilla ) {
            echo 'Plantilla · ' . esc_html( get_the_title( $plantilla ) ?: '#' . $plantilla );
        } elseif ( $amex_ref ) {
            echo 'Importado de AMEX';
        } else {
            echo esc_html( $tipos[ $tipo ] ?? 'Captura manual' );
        }
        if ( 'plantilla' === $tipo && $frecuencia ) echo ' · ' . esc_html( $frecs[ $frecuencia ] ?? $frecuencia );
      ?></div>
    </div>
  </div>
</div>

<?php if ( 'plantilla' !== $tipo ) : ?>
<div class="ficha-card">
  <div class="ficha-section-label">Pago</div>
  <div class="estado-row">
    <div>
      <div class="estado-label">Estado</div>
      <div class="estado-valor"><?php echo $pagado ? 'Pagado el ' . esc_html( $fmt_fecha( $pago_raw ) ) : 'Pendiente'; ?></div>
    </div>
    <div class="estado-acciones">
      <?php if ( $pagado ) : ?>
        <span class="solo-editando-campo" hidden><input type="date" name="expense_payment_date" value="<?php echo esc_attr( $iso( $pago_raw ) ); ?>" title="Corregir la fecha de pago"></span>
        <button type="button" class="link-quieto" id="btnQuitarPago">Quitar pago</button>
      <?php else : ?>
        <button type="button" class="btn-pay" id="btnRegistrarPago">Registrar pago</button>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<?php talos_ficha_historial( $id ); ?>

<div class="ficha-modo-vista ficha-acciones-pie">
  <button type="button" class="btn-danger solo-editando" id="btnEliminarFicha"><svg viewBox="0 0 24 24"><use href="#i-trash"/></svg>Eliminar Gasto</button>
</div>

<div class="modal-overlay" id="modalAviso">
  <div class="modal-box">
    <div class="modal-icon" style="background:#fdf3d8;color:#8a6200;"><svg viewBox="0 0 24 24"><use href="#i-alert-clock"/></svg></div>
    <h4 id="modalAvisoTitulo">¿Guardar los cambios?</h4>
    <p id="modalAvisoTexto"></p>
    <div id="modalAvisoNomina" hidden style="text-align:left;background:var(--surface-2);border-radius:10px;padding:12px 14px;margin:0 0 16px;font-size:13px;">
      <label style="display:flex;gap:8px;align-items:center;font-weight:700;cursor:pointer;"><input type="checkbox" id="chkNominaIva"> Lleva IVA (+16% sobre el monto)</label>
      <div style="color:var(--text-muted);margin-top:6px;">Se le enviará su recibo de nómina a su correo corporativo, con copia oculta a facturas.</div>
    </div>
    <div class="modal-actions">
      <button class="btn-ghost" data-close-modal>Cancelar</button>
      <button class="btn-confirm" id="btnConfirmarAviso">Sí, continuar</button>
    </div>
  </div>
</div>

<div class="toast" id="toastFicha"><svg viewBox="0 0 24 24"><use href="#i-check"/></svg><span id="toastFichaTexto"></span></div>

<script>
try{
  var talosNonceGastos = '<?php echo esc_js( wp_create_nonce( 'talos_gastos' ) ); ?>';
  var talosAjaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
  var fichaId = '<?php echo (int) $id; ?>';
  // Se conserva ?mes= para que "Volver" regrese al mismo listado.
  var fichaUrl = (function(){ var u = new URL(window.location.href); u.searchParams.delete('editar'); return u.pathname + u.search; })();
  var esNomina = <?php echo $es_nomina ? 'true' : 'false'; ?>;
  var dePlantilla = <?php echo $plantilla ? 'true' : 'false'; ?>;

  function post(accion, datos){
    var form = new FormData();
    form.append('action', accion);
    form.append('nonce', talosNonceGastos);
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

  var modalAviso = document.getElementById('modalAviso');
  var alConfirmar = null;
  function confirmar(titulo, texto, boton, color, conNomina){
    document.getElementById('modalAvisoTitulo').textContent = titulo;
    document.getElementById('modalAvisoTexto').textContent = texto;
    document.getElementById('modalAvisoNomina').hidden = !conNomina;
    document.getElementById('chkNominaIva').checked = false;
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

  // Subcategorías según la categoría (talos_subcategorias_por_categoria de talos-core).
  // La subcategoría actual se conserva aunque no esté en la lista (gastos viejos).
  var subcatMapa = <?php echo wp_json_encode( $subcat_mapa ); ?>;
  var fgCat = document.getElementById('fgCategoria'), fgSub = document.getElementById('fgSubcategoria');
  var subActual = <?php echo wp_json_encode( $subcat ); ?>;
  var subTodas = Array.prototype.map.call(fgSub.options, function(o){ return { valor: o.value, texto: o.text }; });
  function filtrarSub(){
    if (!subcatMapa) return;
    var permitidas = subcatMapa[fgCat.value] || [];
    var elegida = fgSub.value;
    fgSub.innerHTML = '';
    subTodas.forEach(function(o){
      if (permitidas.indexOf(o.valor) !== -1 || o.valor === subActual) fgSub.add(new Option(o.texto, o.valor, false, o.valor === elegida));
    });
  }
  fgCat.addEventListener('change', filtrarSub);
  filtrarSub();

  // Monto: con precio unitario se calcula y no se escribe a mano
  var fgCant = document.getElementById('fgCantidad'), fgPrecio = document.getElementById('fgPrecio'), fgMonto = document.getElementById('fgMonto');
  function recalcMonto(){
    var conPrecio = fgPrecio.value.trim() !== '';
    fgMonto.readOnly = conPrecio;
    fgMonto.style.background = conPrecio ? 'var(--surface-2)' : '';
    fgCant.disabled = !conPrecio;
    document.getElementById('fgMontoHint').hidden = !conPrecio;
    if (conPrecio){
      var c = fgCant.value.trim() === '' ? 1 : (parseFloat(fgCant.value) || 0);
      fgMonto.value = Math.round(c * (parseFloat(fgPrecio.value) || 0) * 100) / 100;
    }
  }
  fgCant.addEventListener('input', recalcMonto);
  fgPrecio.addEventListener('input', recalcMonto);
  recalcMonto();

  var fechaInicial = document.querySelector('[data-fecha-gasto]').value;
  function datosFicha(){
    var datos = { expense_id: fichaId };
    document.querySelectorAll('#fichaForm [name], [name="expense_payment_date"]').forEach(function(c){
      if (c.disabled) return;
      datos[c.name] = c.value;
    });
    return datos;
  }
  function guardar(){
    var btn = document.getElementById('btnGuardarFicha');
    btn.disabled = true;
    post('talos_guardar_gasto', datosFicha()).then(function(res){
      if (!res.success){ btn.disabled = false; error(res, 'No se pudo guardar.'); return; }
      window.location.href = fichaUrl;
    }).catch(function(){ btn.disabled = false; toast('No se pudo guardar.'); });
  }
  document.getElementById('btnGuardarFicha').addEventListener('click', function(){
    var nuevaFecha = document.querySelector('[data-fecha-gasto]').value;
    if (dePlantilla && nuevaFecha.slice(0, 7) !== fechaInicial.slice(0, 7)){
      confirmar('¿Guardar los cambios?', 'Este gasto viene de una plantilla. Si lo cambias de mes, Generar Mes podría volver a crearlo en el mes original.', 'Sí, guardar').then(guardar);
      return;
    }
    guardar();
  });

  // ===== Registrar pago (mismo endpoint que la tabla; nómina con IVA y recibo) =====
  var btnPago = document.getElementById('btnRegistrarPago');
  if (btnPago) btnPago.addEventListener('click', function(){
    confirmar('¿Marcar como pagado?', 'Se marcará este gasto como Pagado con la fecha de hoy.', 'Sí, marcar pagado', 'var(--success)', esNomina).then(function(){
      var datos = { ids: [fichaId] };
      if (document.getElementById('chkNominaIva').checked) datos.nomina_iva = '1';
      post('talos_marcar_pagado_gasto_masivo', datos).then(function(res){
        if (!res.success){ error(res, 'No se pudo registrar el pago.'); return; }
        window.location.href = fichaUrl;
      });
    });
  });

  // ===== Quitar pago (sin correo) =====
  var btnQuitar = document.getElementById('btnQuitarPago');
  if (btnQuitar) btnQuitar.addEventListener('click', function(){
    var texto = 'Quedará como pendiente de pago y se borra la fecha de pago.'
      + (esNomina ? ' Ojo: si al pagarla se le sumó el IVA, el monto se queda con IVA, y el recibo que ya recibió no se retira.' : '');
    confirmar('¿Quitar el pago?', texto, 'Sí, quitar', 'var(--danger)').then(function(){
      post('talos_estado_gasto', { expense_id: fichaId }).then(function(res){
        if (!res.success){ error(res, 'No se pudo cambiar.'); return; }
        window.location.href = fichaUrl;
      });
    });
  });

  // ===== Eliminar (papelera) =====
  document.getElementById('btnEliminarFicha').addEventListener('click', function(){
    confirmar('¿Eliminar este gasto?', 'Se moverá a la papelera de WordPress — podrás restaurarlo desde ahí si fue un error.', 'Sí, eliminar', 'var(--danger)').then(function(){
      post('talos_eliminar_gasto', { ids: [fichaId] }).then(function(res){
        if (!res.success){ error(res, 'No se pudo eliminar.'); return; }
        window.location.href = '<?php echo esc_js( talos_ficha_url_volver() ); ?>';
      });
    });
  });
}catch(e){ console.error(e); }
</script>

<?php get_footer(); ?>
