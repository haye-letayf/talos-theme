<?php
/**
 * Dashboard — resumen de Ingresos/Gastos del mes en curso.
 * Resuelve automáticamente sobre la Página con slug "dashboard".
 */

function talos_dashboard_totales_income( DateTime $mes ) {
    $inicio = $mes->format( 'Ymd' );
    $fin    = ( clone $mes )->modify( 'last day of this month' )->format( 'Ymd' );

    $posts = get_posts( array(
        'post_type'      => 'talos_income',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => array(
            array(
                'key'     => 'income_month',
                'value'   => array( $inicio, $fin ),
                'compare' => 'BETWEEN',
                'type'    => 'NUMERIC',
            ),
        ),
    ) );

    $pagado = 0.0;
    $pendiente = 0.0;
    foreach ( $posts as $post_id ) {
        $total = (float) get_field( 'income_total', $post_id );
        if ( get_field( 'income_paid', $post_id ) ) {
            $pagado += $total;
        } else {
            $pendiente += $total;
        }
    }
    return array( 'pagado' => $pagado, 'pendiente' => $pendiente, 'esperado' => $pagado + $pendiente );
}

function talos_dashboard_totales_expense( DateTime $mes ) {
    $inicio = $mes->format( 'Y-m-01' );
    $fin    = ( clone $mes )->modify( 'last day of this month' )->format( 'Y-m-d' );

    $posts = get_posts( array(
        'post_type'      => 'talos_expense',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => array(
            'relation' => 'AND',
            array(
                'key'   => 'expense_type',
                'value' => 'transaccion',
            ),
            array(
                'key'     => 'expense_period',
                'value'   => array( $inicio, $fin ),
                'compare' => 'BETWEEN',
                'type'    => 'DATE',
            ),
        ),
    ) );

    $pagado = 0.0;
    $pendiente = 0.0;
    foreach ( $posts as $post_id ) {
        $monto = (float) get_field( 'expense_amount', $post_id );
        if ( get_field( 'expense_paid', $post_id ) ) {
            $pagado += $monto;
        } else {
            $pendiente += $monto;
        }
    }
    return array( 'pagado' => $pagado, 'pendiente' => $pendiente, 'total' => $pagado + $pendiente );
}

function talos_dashboard_totales_empresas() {
    $ids = get_posts( array(
        'post_type'      => 'talos_company',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ) );

    $clientes_activos  = 0;
    $leads_oportunidad = 0;
    foreach ( $ids as $id ) {
        $clase   = get_field( 'company_class', $id );
        $estatus = get_field( 'company_status', $id );
        if ( 'client' === $clase && 'active' === $estatus ) $clientes_activos++;
        if ( in_array( $clase, array( 'lead', 'opportunity' ), true ) ) $leads_oportunidad++;
    }
    return array( 'clientes_activos' => $clientes_activos, 'leads_oportunidad' => $leads_oportunidad );
}

$talos_mes_actual   = talos_mes_activo();
$talos_mes_anterior = ( clone $talos_mes_actual )->modify( '-1 month' );

$talos_empresas_totales = talos_dashboard_totales_empresas();
$talos_income_actual   = talos_dashboard_totales_income( $talos_mes_actual );
$talos_income_anterior = talos_dashboard_totales_income( $talos_mes_anterior );
$talos_expense_actual  = talos_dashboard_totales_expense( $talos_mes_actual );

// Income Goal: Income (registrado/pagado) ÷ Total Esperado × 100 — 0-69 rojo, 70-85 ámbar, 86-100 verde.
$talos_income_goal = $talos_income_actual['esperado'] > 0
    ? round( ( $talos_income_actual['pagado'] / $talos_income_actual['esperado'] ) * 100 )
    : 0;
$talos_goal_clase = $talos_income_goal >= 86 ? 'goal-green' : ( $talos_income_goal >= 70 ? 'goal-amber' : 'goal-red' );

$talos_net_profit = $talos_income_actual['pagado'] - $talos_expense_actual['pagado'];

$talos_income_trend = null;
if ( $talos_income_anterior['pagado'] > 0 ) {
    $talos_income_trend = round( ( ( $talos_income_actual['pagado'] - $talos_income_anterior['pagado'] ) / $talos_income_anterior['pagado'] ) * 100, 1 );
}

get_header();
?>

<style>
  .kpi-section-label{font-size:11px;text-transform:uppercase;letter-spacing:.08em;font-weight:800;color:var(--text-muted);margin:26px 0 10px;}
  .kpi-section-label:first-of-type{margin-top:0;}
  .kpi-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;}
  .kpi-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px 18px 16px;display:flex;flex-direction:column;gap:12px;}
  .kpi-top{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;}
  .kpi-icon{width:38px;height:38px;border-radius:10px;flex:none;display:flex;align-items:center;justify-content:center;}
  .kpi-icon svg{width:19px;height:19px;}
  .kpi-icon.success{background:var(--success-soft);color:var(--success);}
  .kpi-icon.accent{background:var(--accent-soft);color:var(--accent);}
  .kpi-icon.danger{background:var(--danger-soft);color:var(--danger);}
  .kpi-trend{font-size:11.5px;font-weight:700;display:flex;align-items:center;gap:3px;}
  .kpi-trend svg{width:13px;height:13px;}
  .kpi-trend.up{color:var(--success);}
  .kpi-trend.down{color:var(--danger);}
  .kpi-label{font-size:12.5px;color:var(--text-muted);font-weight:600;margin:0;}
  .kpi-value{font-size:24px;font-weight:800;margin:0;letter-spacing:-.01em;}
  @media (max-width:1024px){.kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr));}}
  @media (max-width:560px){.kpi-grid{grid-template-columns:1fr;}.page-head{align-items:flex-start;}}
  .kpi-grid.cols-2{grid-template-columns:repeat(2,minmax(0,1fr));}
  @media (max-width:560px){.kpi-grid.cols-2{grid-template-columns:1fr;}}
</style>

<div class="page-head">
  <div>
    <h1 class="page-title">Dashboard</h1>
    <p class="page-sub">Resumen general de la operación de Once24</p>
  </div>
  <div class="head-controls">
    <div class="metric-pill <?php echo esc_attr( $talos_goal_clase ); ?>">
      <div><span class="label">Meta de Ingresos</span><span class="value tabular"><?php echo esc_html( $talos_income_goal ); ?>%</span></div>
    </div>
    <div class="metric-pill <?php echo $talos_net_profit >= 0 ? 'good' : 'bad'; ?>">
      <div><span class="label">Utilidad Neta</span><span class="value tabular"><?php echo esc_html( talos_fmt_mxn( $talos_net_profit ) ); ?></span></div>
    </div>
  </div>
</div>

<div class="kpi-section-label">Ingresos</div>
<div class="kpi-grid">
  <div class="kpi-card">
    <div class="kpi-top">
      <div class="kpi-icon success"><svg viewBox="0 0 24 24"><use href="#i-trend-up"/></svg></div>
      <?php if ( null !== $talos_income_trend ) : ?>
        <span class="kpi-trend <?php echo $talos_income_trend >= 0 ? 'up' : 'down'; ?>">
          <svg viewBox="0 0 24 24"><use href="#<?php echo $talos_income_trend >= 0 ? 'i-trend-up' : 'i-trend-down'; ?>"/></svg><?php echo esc_html( abs( $talos_income_trend ) ); ?>%
        </span>
      <?php endif; ?>
    </div>
    <p class="kpi-label">Ingresos (pagados)</p>
    <p class="kpi-value tabular"><?php echo esc_html( talos_fmt_mxn( $talos_income_actual['pagado'] ) ); ?></p>
  </div>
  <div class="kpi-card">
    <div class="kpi-top"><div class="kpi-icon accent"><svg viewBox="0 0 24 24"><use href="#i-clock"/></svg></div></div>
    <p class="kpi-label">Ingresos Pendientes</p>
    <p class="kpi-value tabular"><?php echo esc_html( talos_fmt_mxn( $talos_income_actual['pendiente'] ) ); ?></p>
  </div>
  <div class="kpi-card">
    <div class="kpi-top"><div class="kpi-icon accent"><svg viewBox="0 0 24 24"><use href="#i-trend-up"/></svg></div></div>
    <p class="kpi-label">Ingresos Esperados</p>
    <p class="kpi-value tabular"><?php echo esc_html( talos_fmt_mxn( $talos_income_actual['esperado'] ) ); ?></p>
  </div>
</div>

<div class="kpi-section-label">Gastos</div>
<div class="kpi-grid">
  <div class="kpi-card">
    <div class="kpi-top"><div class="kpi-icon success"><svg viewBox="0 0 24 24"><use href="#i-wallet"/></svg></div></div>
    <p class="kpi-label">Gastos Pagados</p>
    <p class="kpi-value tabular"><?php echo esc_html( talos_fmt_mxn( $talos_expense_actual['pagado'] ) ); ?></p>
  </div>
  <div class="kpi-card">
    <div class="kpi-top"><div class="kpi-icon danger"><svg viewBox="0 0 24 24"><use href="#i-alert-clock"/></svg></div></div>
    <p class="kpi-label">Gastos Pendientes</p>
    <p class="kpi-value tabular"><?php echo esc_html( talos_fmt_mxn( $talos_expense_actual['pendiente'] ) ); ?></p>
  </div>
  <div class="kpi-card">
    <div class="kpi-top"><div class="kpi-icon accent"><svg viewBox="0 0 24 24"><use href="#i-receipt"/></svg></div></div>
    <p class="kpi-label">Gastos Totales</p>
    <p class="kpi-value tabular"><?php echo esc_html( talos_fmt_mxn( $talos_expense_actual['total'] ) ); ?></p>
  </div>
</div>

<div class="kpi-section-label">Empresas</div>
<div class="kpi-grid cols-2">
  <div class="kpi-card">
    <div class="kpi-top"><div class="kpi-icon success"><svg viewBox="0 0 24 24"><use href="#i-building"/></svg></div></div>
    <p class="kpi-label">Clientes Activos</p>
    <p class="kpi-value tabular"><?php echo esc_html( $talos_empresas_totales['clientes_activos'] ); ?></p>
  </div>
  <div class="kpi-card">
    <div class="kpi-top"><div class="kpi-icon accent"><svg viewBox="0 0 24 24"><use href="#i-funnel"/></svg></div></div>
    <p class="kpi-label">Leads / Oportunidad</p>
    <p class="kpi-value tabular"><?php echo esc_html( $talos_empresas_totales['leads_oportunidad'] ); ?></p>
  </div>
</div>

<?php get_footer(); ?>
