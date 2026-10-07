<?php
/**
 * Gastos — operación/personales del mes, atrasados siempre primero.
 * Dos ajustes frente al mockup, porque así es el esquema real de ACF:
 * 1) No existe categoría "Publicidad" en expense_category (solo operacion/personal);
 *    la pauta se registra como subcategoría "Publicidad" dentro de Operación.
 * 2) No existe un campo de Cantidad para gastos (solo un monto), así que la tabla
 *    muestra una sola columna "Monto" en vez de Cant./P. Unit./Total por separado.
 */

$talos_mes_param = isset( $_GET['mes'] ) ? sanitize_text_field( wp_unslash( $_GET['mes'] ) ) : '';
$talos_mes_viendo = DateTime::createFromFormat( 'Y-m', $talos_mes_param );
$talos_mes_viendo = $talos_mes_viendo ? $talos_mes_viendo->modify( 'first day of this month' ) : new DateTime( 'first day of this month' );
$talos_mes_hoy    = new DateTime( 'first day of this month' );

$talos_meses_es     = array( 1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre' );
$talos_meses_cortos = array( 'ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic' );
$talos_mes_label    = $talos_meses_es[ (int) $talos_mes_viendo->format( 'n' ) ] . ' ' . $talos_mes_viendo->format( 'Y' );
$talos_url_prev     = add_query_arg( 'mes', ( clone $talos_mes_viendo )->modify( '-1 month' )->format( 'Y-m' ), get_permalink() );
$talos_url_next     = add_query_arg( 'mes', ( clone $talos_mes_viendo )->modify( '+1 month' )->format( 'Y-m' ), get_permalink() );

$talos_subcat_labels = array(
    'alimentos_bebidas'=>'Alimentos y Bebidas','auto_transporte'=>'Auto y Transporte','casetas'=>'Casetas',
    'comisiones_bancarias'=>'Comisiones Bancarias','consultas_medicas'=>'Consultas Médicas','cuidado_personal'=>'Cuidado Personal',
    'deporte'=>'Deporte','dominios_web'=>'Dominios Web','entretenimiento'=>'Entretenimiento','estacionamientos'=>'Estacionamientos',
    'gasolina'=>'Gasolina','gastos_familiares'=>'Gastos Familiares','internet'=>'Internet','licencias_software'=>'Licencias de Software',
    'medicamentos'=>'Medicamentos','nomina'=>'Nómina','publicidad'=>'Publicidad','ropa_calzado'=>'Ropa y Calzado','seguros'=>'Seguros',
    'servicios_hogar'=>'Servicios del Hogar','servidores_hosting'=>'Servidores y Hosting','software_plugins'=>'Software y Plugins',
    'super'=>'Súper','suscripciones'=>'Suscripciones','telefonia'=>'Telefonía','viajes_hospedaje'=>'Viajes y Hospedaje',
    'gastos_generales'=>'Gastos Generales','educacion'=>'Educación','pendiente'=>'⚠️ Pendiente de Clasificar',
);
function talos_gastos_label_subcat( $slug, $mapa ) { return $mapa[ $slug ] ?? $slug; }
function talos_gastos_mes_corto( $ymd_guion, $cortos ) {
    // expense_period / expense_payment_date vienen en formato Y-m-d
    $partes = explode( '-', (string) $ymd_guion );
    if ( count( $partes ) < 2 ) return '—';
    return $cortos[ (int) $partes[1] - 1 ] . '/' . $partes[0];
}

// ===== Atrasados: transacciones no pagadas de ANTES del mes real en curso =====
$talos_atrasados_ids = get_posts( array(
    'post_type'      => 'talos_expense',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'orderby'        => 'meta_value',
    'meta_key'       => 'expense_period',
    'order'          => 'ASC',
    'meta_query'     => array(
        'relation' => 'AND',
        array( 'key' => 'expense_type', 'value' => 'transaccion' ),
        array( 'key' => 'expense_period', 'value' => $talos_mes_hoy->format( 'Y-m-01' ), 'compare' => '<', 'type' => 'DATE' ),
    ),
) );
$talos_atrasados_ids = array_values( array_filter( $talos_atrasados_ids, function( $id ) { return ! get_field( 'expense_paid', $id ); } ) );

// ===== Transacciones del mes que se está viendo =====
$talos_inicio_mes = $talos_mes_viendo->format( 'Y-m-01' );
$talos_fin_mes    = ( clone $talos_mes_viendo )->modify( 'last day of this month' )->format( 'Y-m-d' );
$talos_normales_ids = get_posts( array(
    'post_type'      => 'talos_expense',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'orderby'        => 'title',
    'order'          => 'ASC',
    'meta_query'     => array(
        'relation' => 'AND',
        array( 'key' => 'expense_type', 'value' => 'transaccion' ),
        array( 'key' => 'expense_period', 'value' => array( $talos_inicio_mes, $talos_fin_mes ), 'compare' => 'BETWEEN', 'type' => 'DATE' ),
    ),
) );

// ===== Métricas: KPI strip + desglose por categoría =====
$talos_g_realizado = 0.0; $talos_g_pendiente = 0.0; $talos_g_atrasado = 0.0;
$talos_cat_realizado = array( 'operacion' => 0.0, 'personal' => 0.0 );
$talos_cat_pendiente = array( 'operacion' => 0.0, 'personal' => 0.0 );
foreach ( $talos_normales_ids as $id ) {
    $monto = (float) get_field( 'expense_amount', $id );
    $cat   = get_field( 'expense_category', $id );
    if ( get_field( 'expense_paid', $id ) ) {
        $talos_g_realizado += $monto;
        if ( isset( $talos_cat_realizado[ $cat ] ) ) $talos_cat_realizado[ $cat ] += $monto;
    } else {
        $talos_g_pendiente += $monto;
        if ( isset( $talos_cat_pendiente[ $cat ] ) ) $talos_cat_pendiente[ $cat ] += $monto;
    }
}
foreach ( $talos_atrasados_ids as $id ) {
    $talos_g_atrasado += (float) get_field( 'expense_amount', $id );
}
$talos_g_total_mes = $talos_g_realizado + $talos_g_pendiente;

$talos_filas = array();
foreach ( $talos_atrasados_ids as $id ) { $talos_filas[] = array( 'id' => $id, 'grupo' => 'atrasado' ); }
foreach ( $talos_normales_ids as $id )  { $talos_filas[] = array( 'id' => $id, 'grupo' => 'actual' ); }

get_header();
?>

<style>
  .kpi-strip{grid-template-columns:repeat(3,minmax(0,1fr));}
  .breakdown-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:4px 0;margin-bottom:22px;}
  .breakdown-row{display:grid;grid-template-columns:1.4fr 1fr 1fr;gap:12px;align-items:center;padding:12px 20px;border-bottom:1px solid var(--border);}
  .breakdown-row:last-child{border-bottom:none;}
  .breakdown-row.head{color:var(--text-muted);font-size:10.5px;text-transform:uppercase;letter-spacing:.06em;font-weight:700;padding-bottom:10px;}
  .breakdown-cat{display:flex;align-items:center;gap:10px;font-weight:700;font-size:13.5px;}
  .cat-dot{width:9px;height:9px;border-radius:50%;flex:none;}
  .breakdown-row .amt{font-size:14px;font-weight:700;}
  .breakdown-row .amt.muted{color:var(--text-muted);font-weight:500;}
  .breakdown-row .amt.realizado{color:var(--success);}
  .breakdown-row .amt.pendiente{color:var(--danger);}
  @media (max-width:860px){.breakdown-row{grid-template-columns:1.2fr 1fr 1fr;padding:12px 14px;}}

  table.expenses{min-width:900px;}
  table.expenses td.concept .sup{font-weight:700;}
  table.expenses td.concept .desc{color:var(--text-muted);font-size:12px;max-width:260px;white-space:normal;margin-top:1px;}
  table.expenses td.final{font-weight:800;}
  .cat-pill{display:inline-flex;align-items:center;gap:6px;padding:3px 10px 3px 7px;border-radius:99px;font-size:11px;font-weight:700;}
  .cat-pill .cat-dot{width:7px;height:7px;}
  .cat-pill.operacion{background:var(--accent-soft);color:var(--accent);}
  .cat-pill.operacion .cat-dot{background:var(--accent);}
  .cat-pill.personal{background:#fdf3d8;color:#8a6200;}
  .cat-pill.personal .cat-dot{background:#c99500;}
  table.expenses thead th .th-flex{display:flex;align-items:center;gap:5px;}
  table.expenses thead th.num .th-flex{justify-content:flex-end;}
  .sort-btn{border:none;background:transparent;color:var(--text-muted);cursor:pointer;display:inline-flex;align-items:center;justify-content:center;width:16px;height:16px;padding:0;border-radius:4px;flex:none;}
  .sort-btn svg{width:11px;height:11px;transition:transform .15s ease;}
  .sort-btn:hover{color:var(--text);background:var(--border);}
  .sort-btn[data-dir="asc"] svg{transform:rotate(180deg);}
  .sort-btn.active{color:var(--accent);}
  table.expenses tbody tr.atrasado{background:var(--danger-soft);}
  table.expenses tbody tr.atrasado:hover{background:var(--danger-soft);filter:brightness(0.97);}
  .late-badge{display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:800;color:var(--danger);text-transform:uppercase;letter-spacing:.04em;margin-top:2px;}
</style>

<div class="page-head">
  <div>
    <h1 class="page-title">Gastos</h1>
    <p class="page-sub">Gastos de operación y personales</p>
  </div>
  <div class="head-controls">
    <a href="<?php echo esc_url( $talos_url_prev ); ?>" class="icon-btn" aria-label="Mes anterior"><svg viewBox="0 0 24 24"><use href="#i-chevron-left"/></svg></a>
    <button class="month-picker" type="button"><?php echo esc_html( $talos_mes_label ); ?></button>
    <a href="<?php echo esc_url( $talos_url_next ); ?>" class="icon-btn" aria-label="Mes siguiente"><svg viewBox="0 0 24 24"><use href="#i-chevron-right"/></svg></a>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=talos-importar-amex' ) ); ?>" class="btn-secondary"><svg viewBox="0 0 24 24"><use href="#i-trend-down"/></svg>Importar AMEX</a>
    <button class="btn-primary" id="btnNuevoGasto"><svg viewBox="0 0 24 24"><use href="#i-plus"/></svg>Nuevo Gasto</button>
  </div>
</div>

<div class="kpi-strip">
  <div class="metric-box stripe-success"><span class="label">Total Realizados</span><span class="value tabular"><?php echo esc_html( talos_fmt_mxn( $talos_g_realizado ) ); ?></span></div>
  <div class="metric-box stripe-danger"><span class="label">Total Pendientes</span><span class="value tabular"><?php echo esc_html( talos_fmt_mxn( $talos_g_pendiente ) ); ?></span></div>
  <div class="metric-box hero"><span class="label">Gastos del Mes</span><span class="value tabular"><?php echo esc_html( talos_fmt_mxn( $talos_g_total_mes ) ); ?></span></div>
</div>

<div class="breakdown-card">
  <div class="breakdown-row head"><div>Categoría</div><div>Realizados</div><div>Pendientes</div></div>
  <div class="breakdown-row">
    <div class="breakdown-cat"><span class="cat-dot" style="background:var(--accent)"></span>Gastos de Operación</div>
    <div class="amt realizado tabular"><?php echo esc_html( talos_fmt_mxn( $talos_cat_realizado['operacion'] ) ); ?></div>
    <div class="amt pendiente tabular"><?php echo esc_html( talos_fmt_mxn( $talos_cat_pendiente['operacion'] ) ); ?></div>
  </div>
  <div class="breakdown-row">
    <div class="breakdown-cat"><span class="cat-dot" style="background:#c99500"></span>Gastos Personales</div>
    <div class="amt realizado tabular"><?php echo esc_html( talos_fmt_mxn( $talos_cat_realizado['personal'] ) ); ?></div>
    <div class="amt pendiente tabular"><?php echo esc_html( talos_fmt_mxn( $talos_cat_pendiente['personal'] ) ); ?></div>
  </div>
  <div class="breakdown-row">
    <div class="breakdown-cat"><span class="cat-dot" style="background:var(--danger)"></span>Gastos Atrasados</div>
    <div class="amt muted">—</div>
    <div class="amt pendiente tabular"><?php echo esc_html( talos_fmt_mxn( $talos_g_atrasado ) ); ?></div>
  </div>
</div>

<div class="filter-bar">
  <div class="seg" data-group-filter>
    <button class="active" data-filter="todos">Todos</button>
    <button data-filter="actual">Actuales</button>
    <button data-filter="atrasado">Atrasados</button>
  </div>
  <div class="seg-spacer"></div>
</div>

<div class="table-card">
  <div class="table-card-head">
    <h3>Transacciones de <?php echo esc_html( $talos_mes_label ); ?></h3>
  </div>
  <div class="table-scroll">
    <table class="expenses" id="expensesTable">
      <thead>
        <tr>
          <th data-sort-key="mes"><span class="th-flex">Mes<button class="sort-btn" data-sort-key="mes"><svg viewBox="0 0 24 24"><use href="#i-chevron"/></svg></button></span></th>
          <th data-sort-key="categoria"><span class="th-flex">Categoría<button class="sort-btn" data-sort-key="categoria"><svg viewBox="0 0 24 24"><use href="#i-chevron"/></svg></button></span></th>
          <th data-sort-key="subcategoria"><span class="th-flex">Subcategoría<button class="sort-btn" data-sort-key="subcategoria"><svg viewBox="0 0 24 24"><use href="#i-chevron"/></svg></button></span></th>
          <th data-sort-key="proveedor"><span class="th-flex">Proveedor / Descripción<button class="sort-btn" data-sort-key="proveedor"><svg viewBox="0 0 24 24"><use href="#i-chevron"/></svg></button></span></th>
          <th class="num" data-sort-key="monto"><span class="th-flex">Monto<button class="sort-btn" data-sort-key="monto"><svg viewBox="0 0 24 24"><use href="#i-chevron"/></svg></button></span></th>
          <th data-sort-key="pagado"><span class="th-flex">Pagado<button class="sort-btn" data-sort-key="pagado"><svg viewBox="0 0 24 24"><use href="#i-chevron"/></svg></button></span></th>
        </tr>
      </thead>
      <tbody id="expenseBody">
        <?php if ( empty( $talos_filas ) ) : ?>
          <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:30px;">Sin gastos registrados para este mes.</td></tr>
        <?php endif; ?>
        <?php foreach ( $talos_filas as $fila ) :
            $id = $fila['id'];
            $categoria = get_field( 'expense_category', $id );
            $subcategoria = get_field( 'expense_subcategory', $id );
            $monto = (float) get_field( 'expense_amount', $id );
            $pagado = get_field( 'expense_paid', $id );
            $fecha_pago = get_field( 'expense_payment_date', $id );
            ?>
            <tr class="<?php echo 'atrasado' === $fila['grupo'] ? 'atrasado' : ''; ?>" data-group="<?php echo esc_attr( $fila['grupo'] ); ?>" data-expense-id="<?php echo esc_attr( $id ); ?>"
                data-sort-mes="<?php echo esc_attr( str_replace( '-', '', substr( (string) get_field( 'expense_period', $id ), 0, 7 ) ) ); ?>"
                data-sort-categoria="<?php echo esc_attr( $categoria ); ?>"
                data-sort-subcategoria="<?php echo esc_attr( talos_gastos_label_subcat( $subcategoria, $talos_subcat_labels ) ); ?>"
                data-sort-proveedor="<?php echo esc_attr( get_field( 'expense_supplier', $id ) ); ?>"
                data-sort-monto="<?php echo esc_attr( $monto ); ?>"
                data-sort-pagado="<?php echo $pagado ? 1 : 0; ?>">
              <td>
                <?php echo esc_html( talos_gastos_mes_corto( get_field( 'expense_period', $id ), $talos_meses_cortos ) ); ?>
                <?php if ( 'atrasado' === $fila['grupo'] ) : ?><div class="late-badge">⚠ Atrasado</div><?php endif; ?>
              </td>
              <td><span class="cat-pill <?php echo esc_attr( $categoria ); ?>"><span class="cat-dot"></span><?php echo esc_html( 'operacion' === $categoria ? 'Operación' : 'Personales' ); ?></span></td>
              <td><?php echo esc_html( talos_gastos_label_subcat( $subcategoria, $talos_subcat_labels ) ); ?></td>
              <td class="concept"><div class="sup"><?php echo esc_html( get_field( 'expense_supplier', $id ) ); ?></div><div class="desc"><?php echo esc_html( get_field( 'expense_description', $id ) ); ?></div></td>
              <td class="num final tabular"><?php echo esc_html( talos_fmt_mxn( $monto ) ); ?></td>
              <td>
                <?php if ( $pagado ) : ?>
                  <span class="paid-chip"><svg viewBox="0 0 24 24"><use href="#i-check"/></svg><?php echo esc_html( $fecha_pago ? talos_gastos_mes_corto( $fecha_pago, $talos_meses_cortos ) : '' ); ?></span>
                <?php else : ?>
                  <button class="btn-pay" data-role="pay">Marcar Pagado</button>
                <?php endif; ?>
              </td>
            </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="modalPagoGasto">
  <div class="modal-box">
    <div class="modal-icon" style="background:var(--success-soft);color:var(--success);"><svg viewBox="0 0 24 24"><use href="#i-check"/></svg></div>
    <h4>¿Marcar como pagado?</h4>
    <p>Se marcará este gasto como Pagado con la fecha de hoy.</p>
    <div class="modal-actions">
      <button class="btn-ghost" data-close-modal>Cancelar</button>
      <button class="btn-confirm" id="btnConfirmarPagoGasto" style="background:var(--success);">Sí, marcar pagado</button>
    </div>
  </div>
</div>

<div class="toast" id="toastGastos"><svg viewBox="0 0 24 24"><use href="#i-check"/></svg><span id="toastGastosTexto"></span></div>

<script>
try{
  var talosNonceGastos = '<?php echo esc_js( wp_create_nonce( 'talos_gastos' ) ); ?>';
  var talosAjaxUrlGastos = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

  function mostrarToastGastos(texto){
    var toast = document.getElementById('toastGastos');
    document.getElementById('toastGastosTexto').textContent = texto;
    toast.classList.add('show');
    clearTimeout(toast._t);
    toast._t = setTimeout(function(){ toast.classList.remove('show'); }, 3000);
  }

  // Filtros rápidos: Todos / Actuales / Atrasados
  document.querySelectorAll('[data-group-filter] button').forEach(function(btn){
    btn.addEventListener('click', function(){
      document.querySelectorAll('[data-group-filter] button').forEach(function(b){ b.classList.remove('active'); });
      btn.classList.add('active');
      var filtro = btn.getAttribute('data-filter');
      document.querySelectorAll('#expenseBody tr').forEach(function(row){
        row.hidden = (filtro !== 'todos' && row.getAttribute('data-group') !== filtro);
      });
    });
  });

  // Encabezados ordenables
  var NUMERIC_SORT_KEYS = ['monto', 'pagado'];
  var sortActivo = { key: null, dir: 1 };
  document.querySelectorAll('.sort-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
      var key = btn.getAttribute('data-sort-key');
      var dir = (sortActivo.key === key) ? -sortActivo.dir : 1;
      sortActivo = { key: key, dir: dir };
      document.querySelectorAll('.sort-btn').forEach(function(b){ b.classList.remove('active'); b.removeAttribute('data-dir'); });
      btn.classList.add('active');
      btn.setAttribute('data-dir', dir === 1 ? 'asc' : 'desc');
      var tbody = document.getElementById('expenseBody');
      var rows = Array.from(tbody.querySelectorAll('tr[data-expense-id]'));
      var esNumerico = NUMERIC_SORT_KEYS.indexOf(key) !== -1 || key === 'mes';
      rows.sort(function(a, b){
        var va = a.getAttribute('data-sort-' + key) || '';
        var vb = b.getAttribute('data-sort-' + key) || '';
        if (esNumerico){ va = parseFloat(va) || 0; vb = parseFloat(vb) || 0; return (va - vb) * dir; }
        return va.localeCompare(vb) * dir;
      });
      rows.forEach(function(row){ tbody.appendChild(row); });
    });
  });

  // Marcar Pagado
  var modalPagoGasto = document.getElementById('modalPagoGasto');
  var filaPendientePago = null;
  document.querySelectorAll('[data-role="pay"]').forEach(function(btn){
    btn.addEventListener('click', function(){ filaPendientePago = btn.closest('tr'); modalPagoGasto.classList.add('show'); });
  });
  modalPagoGasto.querySelectorAll('[data-close-modal]').forEach(function(b){ b.addEventListener('click', function(){ filaPendientePago = null; modalPagoGasto.classList.remove('show'); }); });
  document.getElementById('btnConfirmarPagoGasto').addEventListener('click', function(){
    if (!filaPendientePago) return;
    var form = new FormData();
    form.append('action', 'talos_marcar_pagado_gasto');
    form.append('nonce', talosNonceGastos);
    form.append('expense_id', filaPendientePago.getAttribute('data-expense-id'));
    fetch(talosAjaxUrlGastos, { method: 'POST', body: form, credentials: 'same-origin' }).then(function(r){ return r.json(); }).then(function(res){
      modalPagoGasto.classList.remove('show');
      if (!res.success){ mostrarToastGastos(res.data && res.data.mensaje ? res.data.mensaje : 'No se pudo registrar el pago.'); return; }
      var btn = filaPendientePago.querySelector('[data-role="pay"]');
      if (btn){
        var chip = document.createElement('span');
        chip.className = 'paid-chip';
        chip.innerHTML = '<svg viewBox="0 0 24 24"><use href="#i-check"/></svg>' + res.data.fecha_chip;
        btn.replaceWith(chip);
      }
      filaPendientePago.setAttribute('data-sort-pagado', '1');
      filaPendientePago = null;
    });
  });

  // Nuevo Gasto e Importar AMEX: el botón de AMEX ya enlaza al importador real de wp-admin.
  document.getElementById('btnNuevoGasto').addEventListener('click', function(){
    mostrarToastGastos('Registrar un gasto eventual desde aquí — próximamente en esta misma pantalla.');
  });
}catch(e){ console.error(e); }
</script>

<?php get_footer(); ?>
