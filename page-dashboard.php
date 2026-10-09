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

/**
 * Clientes activos agrupados por Account Manager (talos_team) — a Jorge
 * deliberadamente no se le muestra en esta cartera, se excluye comparando
 * contra el usuario de WP que está viendo la página, no un nombre fijo.
 */
function talos_dashboard_cartera_por_asesor() {
    $usuario_actual = wp_get_current_user();

    $miembros = get_posts( array(
        'post_type'      => 'talos_team',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'orderby'        => 'title',
        'order'          => 'ASC',
    ) );

    $cartera = array();
    foreach ( $miembros as $miembro_id ) {
        $nombre = get_the_title( $miembro_id );
        if ( $usuario_actual && 0 === strcasecmp( $nombre, $usuario_actual->display_name ) ) continue;
        $wp_user_id = (int) get_field( 'team_wp_user_id', $miembro_id );
        $cartera[ $miembro_id ] = array(
            'nombre'   => $nombre,
            'clientes' => array(),
            // Solo si el miembro ya tiene su usuario de WP vinculado (campo
            // team_wp_user_id) — si no, se omite en vez de mostrar un 0 falso.
            'bitacora' => $wp_user_id ? talos_bitacora_resumen_por_autor( $wp_user_id ) : null,
        );
    }

    $empresas = get_posts( array(
        'post_type'      => 'talos_company',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ) );
    foreach ( $empresas as $empresa_id ) {
        if ( 'client' !== get_field( 'company_class', $empresa_id ) ) continue;
        if ( 'active' !== get_field( 'company_status', $empresa_id ) ) continue;
        $am = get_field( 'company_account_manager', $empresa_id );
        $am_id = ( $am instanceof WP_Post ) ? $am->ID : 0;
        if ( $am_id && isset( $cartera[ $am_id ] ) ) {
            $cartera[ $am_id ]['clientes'][] = get_the_title( $empresa_id );
        }
    }

    foreach ( $cartera as &$asesor ) {
        sort( $asesor['clientes'], SORT_STRING | SORT_FLAG_CASE );
    }
    unset( $asesor );

    $cartera = array_values( $cartera );
    usort( $cartera, function ( $a, $b ) { return count( $b['clientes'] ) - count( $a['clientes'] ); } );
    return $cartera;
}

$talos_es_director = current_user_can( 'manage_options' );

if ( $talos_es_director ) {

    $talos_mes_actual   = talos_mes_activo();
    $talos_mes_anterior = ( clone $talos_mes_actual )->modify( '-1 month' );

    $talos_empresas_totales = talos_dashboard_totales_empresas();
    $talos_cartera_asesores = talos_dashboard_cartera_por_asesor();
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

} else {

    // Dashboard personalizado del rol Consulta (Fer/Dany) — ver page-dashboard.php
    // más abajo. Nada de cifras de la agencia completa, solo lo propio.
    $talos_mi_id = talos_mi_team_post_id();

    if ( $talos_mi_id ) {
        $talos_mi_nombre = get_the_title( $talos_mi_id );
        $talos_mi_perfil = array(
            'team_phone'          => get_field( 'team_phone', $talos_mi_id ),
            'team_email_personal' => get_field( 'team_email_personal', $talos_mi_id ),
            'team_social_fb'      => get_field( 'team_social_fb', $talos_mi_id ),
            'team_social_ig'      => get_field( 'team_social_ig', $talos_mi_id ),
            'team_address'        => get_field( 'team_address', $talos_mi_id ),
        );

        $talos_mis_empresas_ids = get_posts( array(
            'post_type'      => 'talos_company',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'orderby'        => 'title',
            'order'          => 'ASC',
            'meta_query'     => array( array( 'key' => 'company_account_manager', 'value' => $talos_mi_id ) ),
        ) );

        // Una fila por servicio contratado (no por empresa) — sin precio/costo,
        // el rol Consulta ve QUÉ está contratado, no CUÁNTO se cobra.
        $talos_mis_servicios = array();
        foreach ( $talos_mis_empresas_ids as $empresa_id ) {
            $filas = get_field( 'company_services', $empresa_id );
            if ( ! is_array( $filas ) ) continue;
            foreach ( $filas as $fila ) {
                if ( empty( $fila['service_status'] ) ) continue; // solo servicios activos
                $item = $fila['service_item'] ?? null;
                $talos_mis_servicios[] = array(
                    'empresa'     => get_the_title( $empresa_id ),
                    'servicio'    => ( $item instanceof WP_Post ) ? $item->post_title : 'Servicio',
                    'descripcion' => $fila['service_invoice_description'] ?? '',
                );
            }
        }

        $talos_mi_resumen_bitacora = talos_bitacora_resumen_por_autor( get_current_user_id() );
    }
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

  .asesor-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;}
  @media (max-width:1024px){.asesor-grid{grid-template-columns:repeat(2,minmax(0,1fr));}}
  @media (max-width:560px){.asesor-grid{grid-template-columns:1fr;}}
  .asesor-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:16px 18px;}
  .asesor-top{display:flex;align-items:center;gap:10px;margin-bottom:12px;}
  .asesor-count{font-size:26px;font-weight:800;margin:0 0 2px;letter-spacing:-.01em;}
  .asesor-count-label{font-size:11.5px;color:var(--text-muted);font-weight:600;margin:0 0 12px;}
  .asesor-lista{max-height:132px;overflow-y:auto;display:flex;flex-direction:column;gap:4px;}
  .asesor-cliente{font-size:12.5px;color:var(--text);padding:5px 9px;background:var(--surface-2);border-radius:6px;}
  .asesor-vacio{font-size:12.5px;color:var(--text-muted);font-style:italic;}
  .asesor-bitacora{display:flex;gap:6px;margin:-4px 0 12px;flex-wrap:wrap;}
  .asesor-bitacora .req-badge{font-size:10px;}

  .ficha-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px 22px;margin-bottom:4px;}
  .ficha-section-label{font-size:11px;text-transform:uppercase;letter-spacing:.08em;font-weight:800;color:var(--text-muted);margin:26px 0 10px;}
  .ficha-section-label:first-of-type{margin-top:0;}
  .ficha-modo-vista .solo-editando{display:none;}
  body.editando .ficha-modo-vista .solo-vista{display:none;}
  body.editando .ficha-modo-vista .solo-editando{display:inline-flex;}
</style>

<?php if ( $talos_es_director ) : ?>

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

<div class="kpi-section-label">Cartera por Asesor</div>
<div class="asesor-grid">
  <?php foreach ( $talos_cartera_asesores as $asesor ) : ?>
    <div class="asesor-card">
      <div class="asesor-top">
        <div class="person-ava"><?php echo esc_html( talos_iniciales( $asesor['nombre'] ) ); ?></div>
        <div class="person-name"><?php echo esc_html( $asesor['nombre'] ); ?></div>
      </div>
      <?php if ( null !== $asesor['bitacora'] ) : ?>
        <div class="asesor-bitacora">
          <span class="req-badge media"><?php echo esc_html( $asesor['bitacora']['pendientes'] + $asesor['bitacora']['en_proceso'] ); ?> peticiones abiertas</span>
          <?php if ( $asesor['bitacora']['vencidas'] ) : ?>
            <span class="req-badge vencida"><?php echo esc_html( $asesor['bitacora']['vencidas'] ); ?> vencida<?php echo 1 === $asesor['bitacora']['vencidas'] ? '' : 's'; ?></span>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <p class="asesor-count tabular"><?php echo esc_html( count( $asesor['clientes'] ) ); ?></p>
      <p class="asesor-count-label">cliente<?php echo 1 === count( $asesor['clientes'] ) ? '' : 's'; ?> activo<?php echo 1 === count( $asesor['clientes'] ) ? '' : 's'; ?></p>
      <?php if ( empty( $asesor['clientes'] ) ) : ?>
        <p class="asesor-vacio">Sin clientes asignados.</p>
      <?php else : ?>
        <div class="asesor-lista">
          <?php foreach ( $asesor['clientes'] as $cliente_nombre ) : ?>
            <div class="asesor-cliente"><?php echo esc_html( $cliente_nombre ); ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if ( empty( $talos_cartera_asesores ) ) : ?>
    <p style="color:var(--text-muted);">Sin miembros de equipo registrados.</p>
  <?php endif; ?>
</div>

<?php else : ?>

  <?php if ( ! $talos_mi_id ) : ?>

    <div class="page-head">
      <div>
        <h1 class="page-title">Dashboard</h1>
        <p class="page-sub">Tu usuario todavía no está vinculado a un perfil de Equipo</p>
      </div>
    </div>
    <p style="color:var(--text-muted);">Pide a un Director que te vincule desde Equipo → tu ficha → campo "Usuario de WordPress".</p>

  <?php else : ?>

    <div class="page-head">
      <div>
        <h1 class="page-title">Hola, <?php echo esc_html( $talos_mi_nombre ); ?></h1>
        <p class="page-sub">Tu resumen personal</p>
      </div>
    </div>

    <div class="ficha-section-label">Mi Perfil</div>
    <div class="ficha-card">
      <div class="page-head ficha-modo-vista" style="margin-bottom:0;">
        <div></div>
        <div class="head-controls">
          <button type="button" class="btn-primary solo-vista" id="btnEditarPerfil"><svg viewBox="0 0 24 24"><use href="#i-edit"/></svg>Editar</button>
          <button type="button" class="btn-ghost solo-editando" id="btnCancelarPerfil">Cancelar</button>
          <button type="button" class="btn-confirm solo-editando" id="btnGuardarPerfil">Guardar Cambios</button>
        </div>
      </div>
      <div class="data-grid">
        <div class="data-field">
          <label>Teléfono</label>
          <div class="view-value"><?php echo esc_html( $talos_mi_perfil['team_phone'] ?: '—' ); ?></div>
          <input type="text" name="team_phone" value="<?php echo esc_attr( $talos_mi_perfil['team_phone'] ); ?>">
        </div>
        <div class="data-field">
          <label>Correo Personal</label>
          <div class="view-value"><?php echo esc_html( $talos_mi_perfil['team_email_personal'] ?: '—' ); ?></div>
          <input type="email" name="team_email_personal" value="<?php echo esc_attr( $talos_mi_perfil['team_email_personal'] ); ?>">
        </div>
        <div class="data-field">
          <label>Facebook Personal</label>
          <div class="view-value"><?php echo esc_html( $talos_mi_perfil['team_social_fb'] ?: '—' ); ?></div>
          <input type="url" name="team_social_fb" value="<?php echo esc_attr( $talos_mi_perfil['team_social_fb'] ); ?>">
        </div>
        <div class="data-field">
          <label>Instagram Personal</label>
          <div class="view-value"><?php echo esc_html( $talos_mi_perfil['team_social_ig'] ?: '—' ); ?></div>
          <input type="url" name="team_social_ig" value="<?php echo esc_attr( $talos_mi_perfil['team_social_ig'] ); ?>">
        </div>
        <div class="data-field full">
          <label>Dirección</label>
          <div class="view-value"><?php echo esc_html( $talos_mi_perfil['team_address'] ?: '—' ); ?></div>
          <textarea name="team_address" rows="2"><?php echo esc_textarea( $talos_mi_perfil['team_address'] ); ?></textarea>
        </div>
      </div>
    </div>

    <div class="kpi-section-label">Mis Cuentas Asignadas</div>
    <div class="count-strip" style="margin-bottom:16px;">
      <div class="count-box accent"><span class="label">Empresas</span><span class="value tabular"><?php echo esc_html( count( $talos_mis_empresas_ids ) ); ?></span></div>
      <div class="count-box"><span class="label">Servicios Activos</span><span class="value tabular"><?php echo esc_html( count( $talos_mis_servicios ) ); ?></span></div>
    </div>
    <div class="table-card">
      <div class="table-scroll">
        <table>
          <thead><tr><th>Empresa</th><th>Servicio</th><th>Descripción</th></tr></thead>
          <tbody>
            <?php if ( empty( $talos_mis_servicios ) ) : ?>
              <tr><td colspan="3" style="text-align:center;color:var(--text-muted);padding:30px;">Sin cuentas asignadas todavía.</td></tr>
            <?php endif; ?>
            <?php foreach ( $talos_mis_servicios as $s ) : ?>
              <tr>
                <td><?php echo esc_html( $s['empresa'] ); ?></td>
                <td><?php echo esc_html( $s['servicio'] ); ?></td>
                <td><?php echo esc_html( $s['descripcion'] ?: '—' ); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="kpi-section-label">Mis Peticiones</div>
    <div class="count-strip" style="margin-bottom:16px;">
      <div class="count-box accent"><span class="label">Pendientes</span><span class="value tabular"><?php echo esc_html( $talos_mi_resumen_bitacora['pendientes'] ); ?></span></div>
      <div class="count-box"><span class="label">En Proceso</span><span class="value tabular"><?php echo esc_html( $talos_mi_resumen_bitacora['en_proceso'] ); ?></span></div>
      <div class="count-box danger"><span class="label">Vencidas</span><span class="value tabular"><?php echo esc_html( $talos_mi_resumen_bitacora['vencidas'] ); ?></span></div>
    </div>

    <?php
    $talos_mis_peticiones_etapas = array(
        'pendiente'  => array( 'label' => 'Pendiente',  'color' => '#94a3b8',       'siguiente' => 'en_proceso' ),
        'en_proceso' => array( 'label' => 'En Proceso', 'color' => 'var(--accent)', 'siguiente' => 'completada' ),
        'completada' => array( 'label' => 'Completada', 'color' => 'var(--success)', 'siguiente' => null ),
    );
    $talos_mis_peticiones_tipos = talos_bit_tipo_labels();
    $talos_mis_peticiones_prioridades = talos_bit_prioridad_labels();
    $talos_mis_peticiones_sla = talos_bit_sla_dias();
    $talos_mis_peticiones_hoy = new DateTime( 'today' );

    $talos_mis_peticiones_ids = get_posts( array(
        'post_type'      => 'talos_request',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'orderby'        => 'date',
        'order'          => 'DESC',
        'author'         => get_current_user_id(),
    ) );
    $talos_mis_peticiones_por_etapa = array_fill_keys( array_keys( $talos_mis_peticiones_etapas ), array() );
    $talos_mis_peticiones_datos = array();
    foreach ( $talos_mis_peticiones_ids as $id ) {
        $etapa = get_field( 'request_status', $id );
        if ( ! isset( $talos_mis_peticiones_por_etapa[ $etapa ] ) ) continue;
        $empresa = get_field( 'request_company', $id );
        $tipo = get_field( 'request_type', $id );
        $tipo_otro = get_field( 'request_type_other', $id );
        $prioridad = get_field( 'request_priority', $id );
        $prioridad_otro = get_field( 'request_priority_other', $id );
        $fecha_solicitud = get_field( 'request_date', $id );
        $dias_fuera_sla = null;
        if ( $fecha_solicitud ) {
            $sol = DateTime::createFromFormat( 'Y-m-d', $fecha_solicitud );
            if ( $sol && isset( $talos_mis_peticiones_sla[ $prioridad ] ) ) {
                $dias_fuera_sla = (int) $sol->diff( $talos_mis_peticiones_hoy )->format( '%a' ) - $talos_mis_peticiones_sla[ $prioridad ];
            }
        }
        $talos_mis_peticiones_datos[ $id ] = array(
            'empresa_id'      => ( $empresa instanceof WP_Post ) ? $empresa->ID : 0,
            'empresa'         => ( $empresa instanceof WP_Post ) ? $empresa->post_title : '—',
            'tipo'            => $tipo,
            'tipo_label'      => ( 'otro' === $tipo && $tipo_otro ) ? $tipo_otro : ( $talos_mis_peticiones_tipos[ $tipo ] ?? $tipo ),
            'tipo_otro'       => $tipo_otro,
            'descripcion'     => get_field( 'request_description', $id ),
            'prioridad'       => $prioridad,
            'prioridad_label' => ( 'otra' === $prioridad && $prioridad_otro ) ? $prioridad_otro : ( $talos_mis_peticiones_prioridades[ $prioridad ] ?? $prioridad ),
            'prioridad_otro'  => $prioridad_otro,
            'fecha_solicitud' => $fecha_solicitud,
            'vencida'         => ( null !== $dias_fuera_sla && $dias_fuera_sla > 0 && 'completada' !== $etapa ),
            'dias_fuera_sla'  => $dias_fuera_sla,
        );
        $talos_mis_peticiones_por_etapa[ $etapa ][] = $id;
    }
    $talos_mis_empresas_para_select = $talos_mis_empresas_ids;
    ?>

    <div class="page-head" style="margin-bottom:16px;">
      <div></div>
      <button class="btn-primary" id="btnNuevaPeticionDash"><svg viewBox="0 0 24 24"><use href="#i-plus"/></svg>Nueva Petición</button>
    </div>

    <div class="board-scroll">
      <div class="board">
        <?php foreach ( $talos_mis_peticiones_etapas as $etapa_key => $etapa ) :
            $ids_etapa = $talos_mis_peticiones_por_etapa[ $etapa_key ];
            ?>
          <div class="board-col">
            <div class="col-head">
              <span class="col-dot" style="background:<?php echo esc_attr( $etapa['color'] ); ?>"></span>
              <span class="col-title"><?php echo esc_html( $etapa['label'] ); ?></span>
              <span class="col-count"><?php echo esc_html( count( $ids_etapa ) ); ?></span>
            </div>
            <div class="col-body">
              <?php foreach ( $ids_etapa as $id ) :
                  $d = $talos_mis_peticiones_datos[ $id ];
                  ?>
                <div class="req-card c-<?php echo esc_attr( $etapa_key ); ?><?php echo $d['vencida'] ? ' is-vencida' : ''; ?>"
                     data-id="<?php echo esc_attr( $id ); ?>"
                     data-empresa-id="<?php echo esc_attr( $d['empresa_id'] ); ?>"
                     data-tipo="<?php echo esc_attr( $d['tipo'] ); ?>"
                     data-tipo-otro="<?php echo esc_attr( $d['tipo_otro'] ); ?>"
                     data-descripcion="<?php echo esc_attr( $d['descripcion'] ); ?>"
                     data-prioridad="<?php echo esc_attr( $d['prioridad'] ); ?>"
                     data-prioridad-otro="<?php echo esc_attr( $d['prioridad_otro'] ); ?>"
                     data-fecha-solicitud="<?php echo esc_attr( $d['fecha_solicitud'] ); ?>">
                  <div class="req-company"><?php echo esc_html( $d['empresa'] ); ?></div>
                  <div class="req-tipo"><?php echo esc_html( $d['tipo_label'] ); ?></div>
                  <div class="req-desc"><?php echo esc_html( wp_trim_words( $d['descripcion'], 14, '…' ) ); ?></div>
                  <div class="req-badges">
                    <span class="req-badge <?php echo esc_attr( $d['prioridad'] ); ?>"><?php echo esc_html( $d['prioridad_label'] ); ?></span>
                    <?php if ( $d['vencida'] ) : ?>
                      <span class="req-badge vencida"><svg viewBox="0 0 24 24"><use href="#i-alert-clock"/></svg><?php echo esc_html( $d['dias_fuera_sla'] ); ?> día<?php echo 1 === $d['dias_fuera_sla'] ? '' : 's'; ?> fuera de plazo</span>
                    <?php endif; ?>
                  </div>
                  <div class="req-foot">
                    <span class="req-fecha">Pedida <?php echo esc_html( talos_fmt_fecha_corta( $d['fecha_solicitud'] ) ); ?></span>
                  </div>
                  <div class="req-actions">
                    <button class="action-btn edit btn-editar-peticion-dash" title="Editar"><svg viewBox="0 0 24 24"><use href="#i-edit"/></svg></button>
                    <button class="action-btn btn-eliminar-peticion-dash" title="Eliminar"><svg viewBox="0 0 24 24"><use href="#i-trash"/></svg></button>
                    <?php if ( $etapa['siguiente'] ) : ?>
                      <button class="btn-move" data-move="<?php echo esc_attr( $etapa['siguiente'] ); ?>"><?php echo esc_html( $talos_mis_peticiones_etapas[ $etapa['siguiente'] ]['label'] ); ?><svg viewBox="0 0 24 24"><use href="#i-arrow-right"/></svg></button>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="modal-overlay" id="modalPeticionDash">
      <div class="modal-box">
        <h4 id="modalPeticionDashTitulo">Nueva Petición</h4>
        <p class="modal-sub">Captura exactamente lo que solicita el cliente.</p>
        <div class="form-field">
          <label for="campoDashEmpresa">Empresa</label>
          <select id="campoDashEmpresa">
            <option value="" disabled selected>Selecciona una empresa…</option>
            <?php foreach ( $talos_mis_empresas_para_select as $empresa_id ) : ?>
              <option value="<?php echo esc_attr( $empresa_id ); ?>"><?php echo esc_html( get_the_title( $empresa_id ) ); ?></option>
            <?php endforeach; ?>
          </select>
          <span class="field-hint">Solo aparecen las empresas que tienes asignadas.</span>
        </div>
        <div class="form-field">
          <label for="campoDashTipo">Tipo de Solicitud</label>
          <select id="campoDashTipo">
            <?php foreach ( $talos_mis_peticiones_tipos as $key => $label ) : ?>
              <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-field" id="wrapDashTipoOtro" hidden>
          <label for="campoDashTipoOtro">Especifica el tipo de solicitud</label>
          <input type="text" id="campoDashTipoOtro">
        </div>
        <div class="form-field">
          <label for="campoDashDescripcion">Descripción de lo solicitado</label>
          <textarea id="campoDashDescripcion" rows="4"></textarea>
        </div>
        <div class="form-row">
          <div class="form-field">
            <label for="campoDashPrioridad">Prioridad</label>
            <select id="campoDashPrioridad">
              <?php foreach ( $talos_mis_peticiones_prioridades as $key => $label ) : ?>
                <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-field">
            <label for="campoDashFecha">Fecha de Solicitud</label>
            <input type="date" id="campoDashFecha" max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>">
          </div>
        </div>
        <div class="form-field" id="wrapDashPrioridadOtro" hidden>
          <label for="campoDashPrioridadOtro">Especifica la prioridad</label>
          <input type="text" id="campoDashPrioridadOtro">
        </div>
        <div class="modal-actions">
          <button class="btn-ghost" data-close-modal>Cancelar</button>
          <button class="btn-confirm" id="btnConfirmarPeticionDash">Guardar</button>
        </div>
      </div>
    </div>

  <?php endif; ?>

<?php endif; ?>

<div class="toast" id="toastDash"><svg viewBox="0 0 24 24"><use href="#i-check"/></svg><span id="toastDashTexto"></span></div>

<?php if ( ! $talos_es_director && $talos_mi_id ) : ?>
<script>
try{
  var talosNonceMiPerfil = '<?php echo esc_js( wp_create_nonce( 'talos_mi_perfil' ) ); ?>';
  var talosNonceDashBit = '<?php echo esc_js( wp_create_nonce( 'talos_bitacora' ) ); ?>';
  var talosAjaxUrlDash = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
  var dashPeticionIdActual = null;

  function mostrarToastDash(texto){
    var toast = document.getElementById('toastDash');
    document.getElementById('toastDashTexto').textContent = texto;
    toast.classList.add('show');
    clearTimeout(toast._t);
    toast._t = setTimeout(function(){ toast.classList.remove('show'); }, 2600);
  }

  // ===== Mi Perfil =====
  document.getElementById('btnEditarPerfil').addEventListener('click', function(){ document.body.classList.add('editando'); });
  document.getElementById('btnCancelarPerfil').addEventListener('click', function(){ window.location.reload(); });
  document.getElementById('btnGuardarPerfil').addEventListener('click', function(){
    var btn = this;
    btn.disabled = true;
    var form = new FormData();
    form.append('action', 'talos_guardar_mi_perfil');
    form.append('nonce', talosNonceMiPerfil);
    document.querySelectorAll('.data-field input, .data-field textarea').forEach(function(campo){
      if (!campo.name) return;
      form.append(campo.name, campo.value);
    });
    fetch(talosAjaxUrlDash, { method: 'POST', body: form, credentials: 'same-origin' }).then(function(r){ return r.json(); }).then(function(res){
      if (!res.success){
        mostrarToastDash(res.data && res.data.mensaje ? res.data.mensaje : 'No se pudo guardar.');
        btn.disabled = false;
        return;
      }
      window.location.reload();
    });
  });

  // ===== Mis Peticiones (mismos endpoints que /bitacora/) =====
  var modalDash = document.getElementById('modalPeticionDash');
  var campoDashEmpresa = document.getElementById('campoDashEmpresa');
  var campoDashTipo = document.getElementById('campoDashTipo');
  var campoDashTipoOtro = document.getElementById('campoDashTipoOtro');
  var wrapDashTipoOtro = document.getElementById('wrapDashTipoOtro');
  var campoDashDescripcion = document.getElementById('campoDashDescripcion');
  var campoDashPrioridad = document.getElementById('campoDashPrioridad');
  var campoDashPrioridadOtro = document.getElementById('campoDashPrioridadOtro');
  var wrapDashPrioridadOtro = document.getElementById('wrapDashPrioridadOtro');
  var campoDashFecha = document.getElementById('campoDashFecha');
  var talosHoyYmdDash = '<?php echo esc_js( current_time( 'Y-m-d' ) ); ?>';

  function actualizarCamposOtroDash(){
    wrapDashTipoOtro.hidden = (campoDashTipo.value !== 'otro');
    wrapDashPrioridadOtro.hidden = (campoDashPrioridad.value !== 'otra');
  }
  campoDashTipo.addEventListener('change', actualizarCamposOtroDash);
  campoDashPrioridad.addEventListener('change', actualizarCamposOtroDash);

  function abrirModalNuevaDash(){
    dashPeticionIdActual = null;
    document.getElementById('modalPeticionDashTitulo').textContent = 'Nueva Petición';
    campoDashEmpresa.value = '';
    campoDashTipo.value = 'calendario';
    campoDashTipoOtro.value = '';
    campoDashDescripcion.value = '';
    campoDashPrioridad.value = 'media';
    campoDashPrioridadOtro.value = '';
    campoDashFecha.value = talosHoyYmdDash;
    actualizarCamposOtroDash();
    modalDash.classList.add('show');
  }
  document.getElementById('btnNuevaPeticionDash').addEventListener('click', abrirModalNuevaDash);

  document.querySelectorAll('.btn-editar-peticion-dash').forEach(function(btn){
    btn.addEventListener('click', function(){
      var card = btn.closest('.req-card');
      dashPeticionIdActual = card.getAttribute('data-id');
      document.getElementById('modalPeticionDashTitulo').textContent = 'Editar Petición';
      campoDashEmpresa.value = card.getAttribute('data-empresa-id');
      campoDashTipo.value = card.getAttribute('data-tipo');
      campoDashTipoOtro.value = card.getAttribute('data-tipo-otro');
      campoDashDescripcion.value = card.getAttribute('data-descripcion');
      campoDashPrioridad.value = card.getAttribute('data-prioridad');
      campoDashPrioridadOtro.value = card.getAttribute('data-prioridad-otro');
      campoDashFecha.value = card.getAttribute('data-fecha-solicitud');
      actualizarCamposOtroDash();
      modalDash.classList.add('show');
    });
  });
  modalDash.querySelectorAll('[data-close-modal]').forEach(function(b){ b.addEventListener('click', function(){ modalDash.classList.remove('show'); }); });
  modalDash.addEventListener('click', function(e){ if (e.target === modalDash) modalDash.classList.remove('show'); });

  document.getElementById('btnConfirmarPeticionDash').addEventListener('click', function(){
    var empresaId = campoDashEmpresa.value;
    var descripcion = campoDashDescripcion.value.trim();
    var fecha = campoDashFecha.value;
    if (!empresaId || !descripcion || !fecha){
      mostrarToastDash('Selecciona la empresa, describe la solicitud y captura la fecha de solicitud');
      return;
    }
    if (fecha > talosHoyYmdDash){
      mostrarToastDash('La fecha de solicitud no puede ser futura');
      return;
    }
    var btn = this;
    btn.disabled = true;
    var form = new FormData();
    form.append('action', dashPeticionIdActual ? 'talos_guardar_peticion' : 'talos_crear_peticion');
    form.append('nonce', talosNonceDashBit);
    if (dashPeticionIdActual) form.append('peticion_id', dashPeticionIdActual);
    form.append('empresa_id', empresaId);
    form.append('tipo', campoDashTipo.value);
    form.append('tipo_otro', campoDashTipoOtro.value);
    form.append('descripcion', descripcion);
    form.append('prioridad', campoDashPrioridad.value);
    form.append('prioridad_otro', campoDashPrioridadOtro.value);
    form.append('fecha_solicitud', fecha);
    fetch(talosAjaxUrlDash, { method: 'POST', body: form, credentials: 'same-origin' }).then(function(r){ return r.json(); }).then(function(res){
      if (!res.success){
        mostrarToastDash(res.data && res.data.mensaje ? res.data.mensaje : 'No se pudo guardar la petición.');
        btn.disabled = false;
        return;
      }
      window.location.reload();
    });
  });

  document.querySelectorAll('[data-move]').forEach(function(btn){
    btn.addEventListener('click', function(){
      var card = btn.closest('.req-card');
      var id = card.getAttribute('data-id');
      var etapa = btn.getAttribute('data-move');
      btn.disabled = true;
      var form = new FormData();
      form.append('action', 'talos_mover_etapa_peticion');
      form.append('nonce', talosNonceDashBit);
      form.append('peticion_id', id);
      form.append('etapa', etapa);
      fetch(talosAjaxUrlDash, { method: 'POST', body: form, credentials: 'same-origin' }).then(function(r){ return r.json(); }).then(function(res){
        if (!res.success){
          mostrarToastDash(res.data && res.data.mensaje ? res.data.mensaje : 'No se pudo mover la petición.');
          btn.disabled = false;
          return;
        }
        window.location.reload();
      });
    });
  });

  document.querySelectorAll('.btn-eliminar-peticion-dash').forEach(function(btn){
    btn.addEventListener('click', function(){
      if (!confirm('¿Eliminar esta petición? Se mueve a la papelera, se puede recuperar desde wp-admin.')) return;
      var card = btn.closest('.req-card');
      var id = card.getAttribute('data-id');
      btn.disabled = true;
      var form = new FormData();
      form.append('action', 'talos_eliminar_peticion');
      form.append('nonce', talosNonceDashBit);
      form.append('peticion_id', id);
      fetch(talosAjaxUrlDash, { method: 'POST', body: form, credentials: 'same-origin' }).then(function(r){ return r.json(); }).then(function(res){
        if (!res.success){
          mostrarToastDash(res.data && res.data.mensaje ? res.data.mensaje : 'No se pudo eliminar la petición.');
          btn.disabled = false;
          return;
        }
        window.location.reload();
      });
    });
  });
}catch(e){ console.error(e); }
</script>
<?php endif; ?>

<?php get_footer(); ?>
