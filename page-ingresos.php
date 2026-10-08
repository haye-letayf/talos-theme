<?php
/**
 * Ingresos — facturación y notas de venta por empresa, con atrasados siempre
 * primero (independientemente del mes que se esté viendo) y envío real de
 * correos (Nota/Factura, Comprobante de Pago) vía includes/ajax-ingresos.php.
 */

// ===== Mes que se está viendo: selector universal (topbar), ver talos_mes_activo() =====
$talos_mes_viendo   = talos_mes_activo();
$talos_mes_hoy      = new DateTime( 'first day of this month' );
$talos_meses_cortos = talos_meses_cortos();
$talos_mes_label    = talos_meses_es()[ (int) $talos_mes_viendo->format( 'n' ) ] . ' ' . $talos_mes_viendo->format( 'Y' );

function talos_ing_mes_corto( $ymd, $cortos ) {
    $m = (int) substr( $ymd, 4, 2 );
    return $cortos[ $m - 1 ] . '/' . substr( $ymd, 0, 4 );
}

// ===== Atrasados: no pagados, de ANTES del mes real en curso (independiente del mes que se vea) =====
$talos_atrasados_ids = get_posts( array(
    'post_type'      => 'talos_income',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'orderby'        => 'meta_value_num',
    'meta_key'       => 'income_month',
    'order'          => 'ASC',
    'meta_query'     => array( array( 'key' => 'income_month', 'value' => $talos_mes_hoy->format( 'Ymd' ), 'compare' => '<', 'type' => 'NUMERIC' ) ),
) );
$talos_atrasados_ids = array_values( array_filter( $talos_atrasados_ids, function( $id ) { return ! get_field( 'income_paid', $id ); } ) );

// ===== Filas normales del mes que se está viendo =====
$talos_inicio_mes = $talos_mes_viendo->format( 'Ymd' );
$talos_fin_mes    = ( clone $talos_mes_viendo )->modify( 'last day of this month' )->format( 'Ymd' );
$talos_normales_ids = get_posts( array(
    'post_type'      => 'talos_income',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'orderby'        => 'title',
    'order'          => 'ASC',
    'meta_query'     => array( array( 'key' => 'income_month', 'value' => array( $talos_inicio_mes, $talos_fin_mes ), 'compare' => 'BETWEEN', 'type' => 'NUMERIC' ) ),
) );

// ===== Métricas de la tira (igual fórmula que Dashboard) =====
$talos_m_registrado = 0.0; $talos_m_pendiente = 0.0; $talos_m_atrasado = 0.0;
foreach ( $talos_normales_ids as $id ) {
    $t = (float) get_field( 'income_total', $id );
    if ( get_field( 'income_paid', $id ) ) { $talos_m_registrado += $t; } else { $talos_m_pendiente += $t; }
}
foreach ( $talos_atrasados_ids as $id ) {
    $talos_m_atrasado += (float) get_field( 'income_total', $id );
}
$talos_m_esperado = $talos_m_registrado + $talos_m_pendiente + $talos_m_atrasado;
$talos_income_goal = $talos_m_esperado > 0 ? round( ( $talos_m_registrado / $talos_m_esperado ) * 100 ) : 0;
$talos_goal_clase   = $talos_income_goal >= 86 ? 'goal-green' : ( $talos_income_goal >= 70 ? 'goal-amber' : 'goal-red' );

// ===== Catálogo de servicios (para el <select> de cada fila) =====
$talos_servicios_cat = get_posts( array( 'post_type' => 'talos_service_cat', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );

$talos_filas = array();
foreach ( $talos_atrasados_ids as $id ) { $talos_filas[] = array( 'id' => $id, 'grupo' => 'atrasado' ); }
foreach ( $talos_normales_ids as $id )  { $talos_filas[] = array( 'id' => $id, 'grupo' => 'actual' ); }

get_header();
?>

<style>
  .metric-strip{grid-template-columns:repeat(5,minmax(0,1fr));}
  .metric-box.goal-red .value{color:var(--danger);}
  .metric-box.goal-amber .value{color:#b4740e;}
  .metric-box.goal-green .value{color:var(--success);}

  .table-card-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 18px;border-bottom:1px solid var(--border);}
  .table-card-head h3{margin:0;font-size:15px;font-weight:800;}

  table.income{min-width:1040px;}
  table.income td.concept .desc{color:var(--text-muted);font-size:12px;max-width:260px;white-space:normal;margin-top:1px;outline:none;border-bottom:1px dashed transparent;border-radius:4px;padding:1px 2px;margin-left:-2px;}
  table.income td.concept .desc:hover{border-bottom-color:var(--border);}
  table.income td.concept .desc:focus{border-bottom-color:var(--accent);background:var(--surface-2);}
  table.income td.client{font-weight:700;}
  table.income td.client .client-sub{display:block;font-weight:600;color:var(--text-muted);font-size:11.5px;margin-top:2px;}
  table.income th:first-child, table.income td:first-child{position:sticky;left:0;z-index:6;width:42px;background:var(--surface-2);}
  table.income tbody td:first-child{background:var(--surface);}
  table.income th:nth-child(2), table.income td:nth-child(2){position:sticky;left:42px;z-index:6;box-shadow:2px 0 5px rgba(0,0,0,.05);}
  table.income th:nth-child(2){background:var(--surface-2);}
  table.income tbody td:nth-child(2){background:var(--surface);}
  table.income tbody tr.atrasado td:first-child, table.income tbody tr.atrasado td:nth-child(2){background:var(--danger-soft);}
  table.income tbody tr.atrasado{background:var(--danger-soft);}
  table.income tbody tr.atrasado:hover{background:var(--danger-soft);filter:brightness(0.97);}
  .late-badge{display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:800;color:var(--danger);text-transform:uppercase;letter-spacing:.04em;margin-top:2px;}
  .row-check{width:17px;height:17px;accent-color:var(--accent);cursor:pointer;}
  .cell-input{width:64px;border:1px solid transparent;background:transparent;border-radius:6px;padding:4px 6px;font:inherit;font-size:13px;text-align:right;color:var(--text);}
  .cell-input:hover{border-color:var(--border);}
  .cell-input:focus{outline:none;border-color:var(--accent);background:var(--surface);}
  select.cell-select{border:1px solid transparent;background:transparent;border-radius:6px;padding:4px 2px;font:inherit;font-size:13px;font-weight:700;color:var(--text);max-width:170px;}
  select.cell-select:hover{border-color:var(--border);}
  select.cell-select:focus{outline:none;border-color:var(--accent);background:var(--surface);}
  .doc-toggle{border:none;cursor:pointer;padding:3px 9px;border-radius:99px;font-size:11px;font-weight:700;}
  .doc-toggle.factura{background:var(--accent-soft);color:var(--accent);}
  .doc-toggle.nota{background:var(--surface-2);color:var(--text-muted);border:1px solid var(--border);}
  .attach-muted{color:var(--text-muted);font-size:11px;}
  .attach-btn{display:flex;align-items:center;gap:5px;border:1px solid var(--border);background:var(--surface);color:var(--text-muted);border-radius:7px;padding:4px 9px;font-size:11px;font-weight:700;cursor:pointer;}
  .attach-btn svg{width:13px;height:13px;}
  .attach-btn.complete{border-color:var(--success);color:var(--success);}
  .factura-row{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 0;}
  .factura-row:first-child{padding-top:0;}
  .factura-estado{font-size:12px;color:var(--text-muted);}
  .factura-estado.listo{color:var(--success);font-weight:700;}
  .selection-bar{display:none;align-items:center;gap:10px;background:var(--accent-soft);color:var(--accent);border-radius:9px;padding:7px 12px;font-size:12.5px;font-weight:700;}
  .selection-bar.show{display:flex;}
  .btn-send{display:flex;align-items:center;gap:7px;background:var(--accent);color:#fff;border:none;border-radius:8px;padding:7px 14px;font-size:12.5px;font-weight:700;cursor:pointer;}
  .btn-send svg{width:13px;height:13px;}
  .btn-send:disabled{opacity:.4;cursor:not-allowed;}
  .btn-send-pay{background:var(--success);}

</style>

<div class="page-head">
  <div>
    <h1 class="page-title">Ingresos</h1>
    <p class="page-sub">Facturación y notas de venta por empresa</p>
  </div>
  <div class="head-controls">
    <button class="btn-primary" id="btnNuevoIngreso"><svg viewBox="0 0 24 24"><use href="#i-plus"/></svg>Nuevo Ingreso</button>
  </div>
</div>

<div class="metric-strip">
  <div class="metric-box stripe-gold"><span class="label">Pendientes</span><span class="value tabular"><?php echo esc_html( talos_fmt_mxn( $talos_m_pendiente ) ); ?></span></div>
  <div class="metric-box stripe-success"><span class="label">Registrados</span><span class="value tabular"><?php echo esc_html( talos_fmt_mxn( $talos_m_registrado ) ); ?></span></div>
  <div class="metric-box stripe-danger"><span class="label">Atrasados</span><span class="value tabular"><?php echo esc_html( talos_fmt_mxn( $talos_m_atrasado ) ); ?></span></div>
  <div class="metric-box hero"><span class="label">Total Esperado</span><span class="value tabular"><?php echo esc_html( talos_fmt_mxn( $talos_m_esperado ) ); ?></span></div>
  <div class="metric-box <?php echo esc_attr( $talos_goal_clase ); ?>"><span class="label">Income Goal</span><span class="value tabular"><?php echo esc_html( $talos_income_goal ); ?>%</span></div>
</div>

<div class="filter-bar">
  <div class="seg" data-group-filter>
    <button class="active" data-filter="todos">Todos</button>
    <button data-filter="actual">Actuales</button>
    <button data-filter="atrasado">Atrasados</button>
  </div>
  <div class="seg-spacer"></div>
  <div class="selection-bar" id="selectionBar">
    <span id="selectionCount">0 seleccionados</span>
    <button class="btn-send" id="btnEnviar" disabled><svg viewBox="0 0 24 24"><use href="#i-send"/></svg>Enviar Notas/Facturas</button>
    <button class="btn-send btn-send-pay" id="btnPagoMasivo" disabled><svg viewBox="0 0 24 24"><use href="#i-check"/></svg>Registrar Pago Masivo</button>
    <button class="btn-danger" id="btnEliminarMasivo" disabled><svg viewBox="0 0 24 24"><use href="#i-trash"/></svg>Eliminar Seleccionados</button>
  </div>
</div>

<div class="table-card">
  <div class="table-card-head">
    <h3>Transacciones de <?php echo esc_html( $talos_mes_label ); ?></h3>
  </div>
  <div class="table-scroll">
    <table class="income" id="incomeTable">
      <thead>
        <tr>
          <th><input type="checkbox" class="row-check" id="checkAll"></th>
          <th data-sort-key="cliente"><span class="th-flex">Cliente<button class="sort-btn" data-sort-key="cliente"><svg viewBox="0 0 24 24"><use href="#i-chevron"/></svg></button></span></th>
          <th data-sort-key="servicio"><span class="th-flex">Servicio<button class="sort-btn" data-sort-key="servicio"><svg viewBox="0 0 24 24"><use href="#i-chevron"/></svg></button></span></th>
          <th data-sort-key="doc"><span class="th-flex">Doc.<button class="sort-btn" data-sort-key="doc"><svg viewBox="0 0 24 24"><use href="#i-chevron"/></svg></button></span></th>
          <th class="num" data-sort-key="monto"><span class="th-flex">Monto<button class="sort-btn" data-sort-key="monto" data-sort-numeric><svg viewBox="0 0 24 24"><use href="#i-chevron"/></svg></button></span></th>
          <th class="num" data-sort-key="total"><span class="th-flex">Total<button class="sort-btn" data-sort-key="total" data-sort-numeric><svg viewBox="0 0 24 24"><use href="#i-chevron"/></svg></button></span></th>
          <th data-sort-key="enviado"><span class="th-flex">Enviado<button class="sort-btn" data-sort-key="enviado" data-sort-numeric><svg viewBox="0 0 24 24"><use href="#i-chevron"/></svg></button></span></th>
          <th data-sort-key="pagado"><span class="th-flex">Pagado<button class="sort-btn" data-sort-key="pagado" data-sort-numeric><svg viewBox="0 0 24 24"><use href="#i-chevron"/></svg></button></span></th>
          <th>Factura</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="incomeBody">
        <?php if ( empty( $talos_filas ) ) : ?>
          <tr><td colspan="10" style="text-align:center;color:var(--text-muted);padding:30px;">Sin ingresos registrados para este mes.</td></tr>
        <?php endif; ?>
        <?php foreach ( $talos_filas as $fila ) :
            $id = $fila['id'];
            $empresa = get_field( 'income_company', $id );
            $servicio_actual = get_field( 'income_service', $id );
            $doc = get_field( 'income_doc_type', $id );
            $es_factura = ( 'factura' === $doc );
            $pagado = get_field( 'income_paid', $id );
            $fecha_pago = get_field( 'income_payment_date', $id );
            ?>
            <tr class="income-row<?php echo 'atrasado' === $fila['grupo'] ? ' atrasado' : ''; ?>" data-group="<?php echo esc_attr( $fila['grupo'] ); ?>" data-income-id="<?php echo esc_attr( $id ); ?>"
                data-sort-cliente="<?php echo esc_attr( $empresa instanceof WP_Post ? $empresa->post_title : '' ); ?>"
                data-sort-servicio="<?php echo esc_attr( $servicio_actual instanceof WP_Post ? $servicio_actual->post_title : '' ); ?>"
                data-sort-doc="<?php echo esc_attr( $doc ); ?>"
                data-sort-monto="<?php echo esc_attr( get_field( 'income_unit_price', $id ) ); ?>"
                data-sort-total="<?php echo esc_attr( get_field( 'income_total', $id ) ); ?>"
                data-sort-enviado="<?php echo get_field( 'income_sent', $id ) ? 1 : 0; ?>"
                data-sort-pagado="<?php echo $pagado ? 1 : 0; ?>">
              <td><input type="checkbox" class="row-check"></td>
              <td class="client">
                <span class="client-name"><?php echo esc_html( $empresa instanceof WP_Post ? $empresa->post_title : '—' ); ?></span>
                <span class="client-sub"><?php echo esc_html( talos_ing_mes_corto( get_field( 'income_month', $id ), $talos_meses_cortos ) ); ?></span>
                <?php if ( 'atrasado' === $fila['grupo'] ) : ?><div class="late-badge">⚠ Atrasado</div><?php endif; ?>
              </td>
              <td class="concept">
                <select class="cell-select" data-role="servicio">
                  <?php foreach ( $talos_servicios_cat as $svc ) : ?>
                    <option value="<?php echo esc_attr( $svc->ID ); ?>" <?php selected( $servicio_actual instanceof WP_Post ? $servicio_actual->ID : 0, $svc->ID ); ?>><?php echo esc_html( $svc->post_title ); ?></option>
                  <?php endforeach; ?>
                </select>
                <div class="desc" contenteditable="true" title="Editar descripción"><?php echo esc_html( get_field( 'income_description', $id ) ); ?></div>
              </td>
              <td><button class="doc-toggle <?php echo $es_factura ? 'factura' : 'nota'; ?>" data-role="doc"><?php echo $es_factura ? 'Factura' : 'Nota Venta'; ?></button></td>
              <td class="num"><input class="cell-input" data-role="monto" type="number" value="<?php echo esc_attr( get_field( 'income_unit_price', $id ) ); ?>" step="100"></td>
              <td class="num final tabular" data-role="total"><?php echo esc_html( talos_fmt_mxn( get_field( 'income_total', $id ) ) ); ?></td>
              <td><span class="status-dot <?php echo get_field( 'income_sent', $id ) ? 'yes' : 'no'; ?>" data-role="enviado-dot"><svg viewBox="0 0 24 24"><use href="#<?php echo get_field( 'income_sent', $id ) ? 'i-check' : 'i-x'; ?>"/></svg></span></td>
              <td>
                <?php if ( $pagado ) : ?>
                  <span class="paid-chip" data-role="paid-chip"><svg viewBox="0 0 24 24"><use href="#i-check"/></svg><?php echo esc_html( $fecha_pago ? talos_ing_mes_corto( $fecha_pago, $talos_meses_cortos ) : '' ); ?></span>
                <?php else : ?>
                  <button class="btn-pay" data-role="pay">Marcar Pagado</button>
                <?php endif; ?>
              </td>
              <td>
                <?php if ( $es_factura ) :
                    $tiene_pdf = (bool) get_field( 'income_invoice_pdf', $id );
                    $tiene_xml = (bool) get_field( 'income_invoice_xml', $id );
                    $listos = ( $tiene_pdf ? 1 : 0 ) + ( $tiene_xml ? 1 : 0 );
                    ?>
                  <button class="attach-btn<?php echo 2 === $listos ? ' complete' : ''; ?>" data-role="attach" data-pdf="<?php echo $tiene_pdf ? '1' : '0'; ?>" data-xml="<?php echo $tiene_xml ? '1' : '0'; ?>"><svg viewBox="0 0 24 24"><use href="#i-paperclip"/></svg><?php echo esc_html( $listos ); ?>/2</button>
                <?php else : ?>
                  <span class="attach-muted">—</span>
                <?php endif; ?>
              </td>
              <td><button class="action-btn delete" data-role="delete" title="Eliminar (a la papelera)"><svg viewBox="0 0 24 24"><use href="#i-trash"/></svg></button></td>
            </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ===== Modal: confirmar envío de Notas/Facturas ===== -->
<div class="modal-overlay" id="modalEnviar">
  <div class="modal-box">
    <div class="modal-icon"><svg viewBox="0 0 24 24"><use href="#i-send"/></svg></div>
    <h4>¿Enviar Notas/Facturas?</h4>
    <p id="modalEnviarTexto">Se enviará el correo correspondiente a los ingresos seleccionados y se marcarán como Enviados.</p>
    <div class="modal-actions">
      <button class="btn-ghost" data-close-modal>Cancelar</button>
      <button class="btn-confirm" id="btnConfirmarEnvio">Sí, enviar</button>
    </div>
  </div>
</div>

<!-- ===== Modal: confirmar registro de pago ===== -->
<div class="modal-overlay" id="modalPago">
  <div class="modal-box">
    <div class="modal-icon" style="background:var(--success-soft);color:var(--success);"><svg viewBox="0 0 24 24"><use href="#i-check"/></svg></div>
    <h4 id="modalPagoTitulo">¿Registrar pago?</h4>
    <p id="modalPagoTexto">Se marcará como Pagado con la fecha de hoy y se enviará el recibo de pago.</p>
    <div class="modal-actions">
      <button class="btn-ghost" data-close-modal>Cancelar</button>
      <button class="btn-confirm" id="btnConfirmarPago" style="background:var(--success);">Sí, registrar y enviar</button>
    </div>
  </div>
</div>

<!-- ===== Modal: confirmar eliminación (a la papelera) ===== -->
<div class="modal-overlay" id="modalEliminar">
  <div class="modal-box">
    <div class="modal-icon" style="background:var(--danger-soft);color:var(--danger);"><svg viewBox="0 0 24 24"><use href="#i-trash"/></svg></div>
    <h4 id="modalEliminarTitulo">¿Eliminar este ingreso?</h4>
    <p id="modalEliminarTexto">Se moverá a la papelera de WordPress — podrás restaurarlo desde ahí si fue un error.</p>
    <div class="modal-actions">
      <button class="btn-ghost" data-close-modal>Cancelar</button>
      <button class="btn-confirm" id="btnConfirmarEliminar" style="background:var(--danger);">Sí, eliminar</button>
    </div>
  </div>
</div>

<!-- ===== Modal: archivos de Factura (PDF + XML) ===== -->
<div class="modal-overlay" id="modalFactura">
  <div class="modal-box">
    <div class="modal-icon"><svg viewBox="0 0 24 24"><use href="#i-paperclip"/></svg></div>
    <h4>Archivos de Factura</h4>
    <p id="modalFacturaEmpresa"></p>
    <div class="factura-row">
      <span>PDF</span>
      <span class="factura-estado" id="facturaPdfEstado">Sin subir</span>
      <input type="file" id="facturaPdfInput" accept=".pdf" style="display:none">
      <button class="btn-ghost" id="btnSubirPdf" type="button">Subir PDF</button>
    </div>
    <div class="factura-row">
      <span>XML</span>
      <span class="factura-estado" id="facturaXmlEstado">Sin subir</span>
      <input type="file" id="facturaXmlInput" accept=".xml" style="display:none">
      <button class="btn-ghost" id="btnSubirXml" type="button">Subir XML</button>
    </div>
    <div class="modal-actions">
      <button class="btn-ghost" data-close-modal>Cerrar</button>
    </div>
  </div>
</div>

<div class="toast" id="toastIngresos"><svg viewBox="0 0 24 24"><use href="#i-check"/></svg><span id="toastIngresosTexto"></span></div>

<script>
try{
  var talosNonce = '<?php echo esc_js( wp_create_nonce( 'talos_ingresos' ) ); ?>';
  var talosAjaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

  function talosPost(accion, datos){
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

  function mostrarToast(texto){
    var toast = document.getElementById('toastIngresos');
    document.getElementById('toastIngresosTexto').textContent = texto;
    toast.classList.add('show');
    clearTimeout(toast._t);
    toast._t = setTimeout(function(){ toast.classList.remove('show'); }, 3000);
  }

  var fmt = function(n){ return '$' + Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ','); };

  function recalcRowPreview(row){
    var monto = parseFloat(row.querySelector('[data-role="monto"]').value) || 0;
    var esFactura = row.querySelector('[data-role="doc"]').classList.contains('factura');
    var total = esFactura ? monto * 1.16 : monto;
    row.querySelector('[data-role="total"]').textContent = fmt(total);
    row.setAttribute('data-sort-monto', monto);
    row.setAttribute('data-sort-total', total);
  }

  function guardarCampo(row, campo, valor){
    var incomeId = row.getAttribute('data-income-id');
    talosPost('talos_actualizar_campo_income', { income_id: incomeId, campo: campo, valor: valor }).then(function(res){
      if (res.success){
        row.querySelector('[data-role="total"]').textContent = res.data.total;
        row.setAttribute('data-sort-total', parseFloat(res.data.total.replace(/[^0-9.-]/g, '')) || 0);
      } else {
        mostrarToast(res.data && res.data.mensaje ? res.data.mensaje : 'No se pudo guardar el cambio.');
      }
    });
  }

  document.querySelectorAll('#incomeBody input[data-role="monto"]').forEach(function(inp){
    inp.addEventListener('input', function(){ recalcRowPreview(inp.closest('tr')); });
    inp.addEventListener('change', function(){ guardarCampo(inp.closest('tr'), 'income_unit_price', inp.value); });
  });

  document.querySelectorAll('[data-role="servicio"]').forEach(function(sel){
    sel.addEventListener('change', function(){
      guardarCampo(sel.closest('tr'), 'income_service', sel.value);
      sel.closest('tr').setAttribute('data-sort-servicio', sel.options[sel.selectedIndex].text);
    });
  });

  document.querySelectorAll('#incomeBody .desc').forEach(function(desc){
    desc.addEventListener('blur', function(){ guardarCampo(desc.closest('tr'), 'income_description', desc.textContent.trim()); });
  });

  document.querySelectorAll('[data-role="doc"]').forEach(function(btn){
    btn.addEventListener('click', function(){
      var esFactura = btn.classList.contains('factura');
      btn.classList.toggle('factura', !esFactura);
      btn.classList.toggle('nota', esFactura);
      btn.textContent = esFactura ? 'Nota Venta' : 'Factura';
      recalcRowPreview(btn.closest('tr'));
      guardarCampo(btn.closest('tr'), 'income_doc_type', esFactura ? 'nota_venta' : 'factura');
      btn.closest('tr').setAttribute('data-sort-doc', esFactura ? 'nota_venta' : 'factura');
    });
  });

  // Encabezados ordenables: lógica compartida en talos-app.js

  document.querySelectorAll('[data-group-filter] button').forEach(function(btn){
    btn.addEventListener('click', function(){
      document.querySelectorAll('[data-group-filter] button').forEach(function(b){ b.classList.remove('active'); });
      btn.classList.add('active');
      var filtro = btn.getAttribute('data-filter');
      document.querySelectorAll('#incomeBody tr.income-row').forEach(function(row){
        row.hidden = (filtro !== 'todos' && row.getAttribute('data-group') !== filtro);
      });
    });
  });

  function updateSelectionBar(){
    var checked = document.querySelectorAll('#incomeBody .row-check:checked');
    document.getElementById('selectionCount').textContent = checked.length + ' seleccionado' + (checked.length === 1 ? '' : 's');
    document.getElementById('selectionBar').classList.toggle('show', checked.length > 0);
    document.getElementById('btnEnviar').disabled = checked.length === 0;
    document.getElementById('btnPagoMasivo').disabled = checked.length === 0;
    document.getElementById('btnEliminarMasivo').disabled = checked.length === 0;
  }
  var checkAllEl = document.getElementById('checkAll');
  if (checkAllEl) checkAllEl.addEventListener('change', function(){
    document.querySelectorAll('#incomeBody .row-check').forEach(function(c){ c.checked = checkAllEl.checked; });
    updateSelectionBar();
  });
  document.querySelectorAll('#incomeBody .row-check').forEach(function(c){ c.addEventListener('change', updateSelectionBar); });

  function idsSeleccionados(){
    return Array.from(document.querySelectorAll('#incomeBody .row-check:checked')).map(function(c){ return c.closest('tr').getAttribute('data-income-id'); });
  }
  function nombresClientes(ids){
    var vistos = [];
    ids.forEach(function(id){
      var row = document.querySelector('[data-income-id="' + id + '"]');
      var nombre = row.querySelector('.client-name').textContent.trim();
      if (vistos.indexOf(nombre) === -1) vistos.push(nombre);
    });
    return vistos;
  }

  // ===== Enviar Notas/Facturas =====
  var modalEnviar = document.getElementById('modalEnviar');
  var idsParaEnviar = [];
  document.getElementById('btnEnviar').addEventListener('click', function(){
    idsParaEnviar = idsSeleccionados();
    var clientes = nombresClientes(idsParaEnviar);
    var n = idsParaEnviar.length;
    document.getElementById('modalEnviarTexto').textContent = clientes.length <= 1
      ? 'Se enviará el correo correspondiente a ' + n + ' ingreso' + (n === 1 ? '' : 's') + ' y se marcará(n) como Enviado(s).'
      : 'Se enviará un correo agrupado por empresa a cada uno de los ' + clientes.length + ' clientes seleccionados (' + n + ' ingresos en total).';
    modalEnviar.classList.add('show');
  });
  modalEnviar.querySelectorAll('[data-close-modal]').forEach(function(b){ b.addEventListener('click', function(){ modalEnviar.classList.remove('show'); }); });
  document.getElementById('btnConfirmarEnvio').addEventListener('click', function(){
    talosPost('talos_enviar_notas', { ids: idsParaEnviar }).then(function(res){
      modalEnviar.classList.remove('show');
      if (!res.success){ mostrarToast(res.data && res.data.mensaje ? res.data.mensaje : 'No se pudo enviar.'); return; }
      res.data.ids.forEach(function(id){
        var row = document.querySelector('[data-income-id="' + id + '"]');
        if (!row) return;
        var dot = row.querySelector('[data-role="enviado-dot"]');
        dot.className = 'status-dot yes';
        dot.innerHTML = '<svg viewBox="0 0 24 24"><use href="#i-check"/></svg>';
        row.setAttribute('data-sort-enviado', '1');
        row.querySelector('.row-check').checked = false;
      });
      updateSelectionBar();
      mostrarToast(res.data.correos_enviados + ' correo(s) enviado(s) correctamente.' + (res.data.sin_contacto.length ? ' Sin contacto registrado: ' + res.data.sin_contacto.join(', ') : ''));
    });
  });

  // ===== Registrar Pago (individual o masivo) =====
  var modalPago = document.getElementById('modalPago');
  var idsParaPago = [];
  function solicitarPago(ids){
    idsParaPago = ids;
    var clientes = nombresClientes(ids);
    var n = ids.length;
    document.getElementById('modalPagoTitulo').textContent = n === 1 ? '¿Registrar pago?' : '¿Registrar pago masivo?';
    document.getElementById('modalPagoTexto').textContent = clientes.length <= 1
      ? 'Se marcará como Pagado con la fecha de hoy y se enviará el recibo de pago a ' + (clientes[0] || 'este cliente') + '.'
      : 'Se marcarán como Pagados ' + n + ' ingresos de ' + clientes.length + ' clientes distintos, y se enviará un recibo agrupado a cada uno.';
    modalPago.classList.add('show');
  }
  document.querySelectorAll('[data-role="pay"]').forEach(function(btn){
    btn.addEventListener('click', function(){ solicitarPago([btn.closest('tr').getAttribute('data-income-id')]); });
  });
  document.getElementById('btnPagoMasivo').addEventListener('click', function(){ solicitarPago(idsSeleccionados()); });
  modalPago.querySelectorAll('[data-close-modal]').forEach(function(b){ b.addEventListener('click', function(){ modalPago.classList.remove('show'); }); });
  document.getElementById('btnConfirmarPago').addEventListener('click', function(){
    talosPost('talos_marcar_pagado', { ids: idsParaPago }).then(function(res){
      modalPago.classList.remove('show');
      if (!res.success){ mostrarToast(res.data && res.data.mensaje ? res.data.mensaje : 'No se pudo registrar el pago.'); return; }
      res.data.ids.forEach(function(id){
        var row = document.querySelector('[data-income-id="' + id + '"]');
        if (!row) return;
        var btn = row.querySelector('[data-role="pay"]');
        if (btn){
          var chip = document.createElement('span');
          chip.className = 'paid-chip';
          chip.setAttribute('data-role', 'paid-chip');
          chip.innerHTML = '<svg viewBox="0 0 24 24"><use href="#i-check"/></svg>' + res.data.fecha_chip;
          btn.replaceWith(chip);
        }
        row.setAttribute('data-sort-pagado', '1');
        var check = row.querySelector('.row-check');
        if (check) check.checked = false;
      });
      updateSelectionBar();
      mostrarToast(res.data.correos_enviados + ' recibo(s) de pago enviado(s) correctamente.');
    });
  });

  // "Nuevo Ingreso" ad-hoc todavía no está conectado a una creación real — próxima iteración.
  document.getElementById('btnNuevoIngreso').addEventListener('click', function(){
    mostrarToast('Registrar un ingreso ad-hoc desde aquí — próximamente en esta misma pantalla.');
  });

  // ===== Archivos de Factura (PDF + XML) =====
  var modalFactura = document.getElementById('modalFactura');
  var filaFacturaActual = null;

  function actualizarEstadoFactura(){
    var btn = filaFacturaActual.querySelector('[data-role="attach"]');
    var tienePdf = btn.getAttribute('data-pdf') === '1';
    var tieneXml = btn.getAttribute('data-xml') === '1';
    document.getElementById('facturaPdfEstado').textContent = tienePdf ? 'Listo ✓' : 'Sin subir';
    document.getElementById('facturaPdfEstado').classList.toggle('listo', tienePdf);
    document.getElementById('facturaXmlEstado').textContent = tieneXml ? 'Listo ✓' : 'Sin subir';
    document.getElementById('facturaXmlEstado').classList.toggle('listo', tieneXml);
    var listos = (tienePdf ? 1 : 0) + (tieneXml ? 1 : 0);
    btn.textContent = '';
    btn.innerHTML = '<svg viewBox="0 0 24 24"><use href="#i-paperclip"/></svg>' + listos + '/2';
    btn.classList.toggle('complete', listos === 2);
  }

  document.querySelectorAll('[data-role="attach"]').forEach(function(btn){
    btn.addEventListener('click', function(){
      filaFacturaActual = btn.closest('tr');
      document.getElementById('modalFacturaEmpresa').textContent = filaFacturaActual.querySelector('.client-name').textContent.trim();
      actualizarEstadoFactura();
      modalFactura.classList.add('show');
    });
  });
  modalFactura.querySelectorAll('[data-close-modal]').forEach(function(b){ b.addEventListener('click', function(){ modalFactura.classList.remove('show'); }); });

  function subirArchivoFactura(tipo, archivo){
    if (!filaFacturaActual || !archivo) return;
    var form = new FormData();
    form.append('action', 'talos_subir_factura_archivo');
    form.append('nonce', talosNonce);
    form.append('income_id', filaFacturaActual.getAttribute('data-income-id'));
    form.append('tipo', tipo);
    form.append('archivo', archivo);
    fetch(talosAjaxUrl, { method: 'POST', body: form, credentials: 'same-origin' }).then(function(r){ return r.json(); }).then(function(res){
      if (!res.success){ mostrarToast(res.data && res.data.mensaje ? res.data.mensaje : 'No se pudo subir el archivo.'); return; }
      var btn = filaFacturaActual.querySelector('[data-role="attach"]');
      btn.setAttribute('data-pdf', res.data.tiene_pdf ? '1' : '0');
      btn.setAttribute('data-xml', res.data.tiene_xml ? '1' : '0');
      actualizarEstadoFactura();
      mostrarToast('Archivo "' + res.data.nombre + '" subido correctamente.');
    });
  }

  document.getElementById('btnSubirPdf').addEventListener('click', function(){ document.getElementById('facturaPdfInput').click(); });
  document.getElementById('btnSubirXml').addEventListener('click', function(){ document.getElementById('facturaXmlInput').click(); });
  document.getElementById('facturaPdfInput').addEventListener('change', function(e){ subirArchivoFactura('pdf', e.target.files[0]); e.target.value = ''; });
  document.getElementById('facturaXmlInput').addEventListener('change', function(e){ subirArchivoFactura('xml', e.target.files[0]); e.target.value = ''; });

  // ===== Eliminar (individual o masivo) — mueve a la papelera de WordPress =====
  var modalEliminar = document.getElementById('modalEliminar');
  var idsParaEliminar = [];
  function solicitarEliminar(ids){
    idsParaEliminar = ids;
    var clientes = nombresClientes(ids);
    var n = ids.length;
    document.getElementById('modalEliminarTitulo').textContent = n === 1 ? '¿Eliminar este ingreso?' : '¿Eliminar ' + n + ' ingresos?';
    document.getElementById('modalEliminarTexto').textContent = (n === 1
      ? 'Se moverá a la papelera de WordPress (de ' + (clientes[0] || 'este cliente') + ')'
      : 'Se moverán ' + n + ' ingresos a la papelera de WordPress') + ' — podrás restaurarlos desde ahí si fue un error.';
    modalEliminar.classList.add('show');
  }
  document.querySelectorAll('[data-role="delete"]').forEach(function(btn){
    btn.addEventListener('click', function(){ solicitarEliminar([btn.closest('tr').getAttribute('data-income-id')]); });
  });
  document.getElementById('btnEliminarMasivo').addEventListener('click', function(){ solicitarEliminar(idsSeleccionados()); });
  modalEliminar.querySelectorAll('[data-close-modal]').forEach(function(b){ b.addEventListener('click', function(){ modalEliminar.classList.remove('show'); }); });
  document.getElementById('btnConfirmarEliminar').addEventListener('click', function(){
    talosPost('talos_eliminar_income', { ids: idsParaEliminar }).then(function(res){
      modalEliminar.classList.remove('show');
      if (!res.success){ mostrarToast(res.data && res.data.mensaje ? res.data.mensaje : 'No se pudo eliminar.'); return; }
      res.data.ids.forEach(function(id){
        var row = document.querySelector('[data-income-id="' + id + '"]');
        if (row) row.remove();
      });
      updateSelectionBar();
      mostrarToast(res.data.ids.length + ' ingreso(s) movido(s) a la papelera.');
    });
  });
}catch(e){ console.error(e); }
</script>

<?php get_footer(); ?>
