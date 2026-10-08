<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?php wp_title( '|', true, 'right' ); bloginfo( 'name' ); ?></title>
    <meta content="Talos 2.0 ERP" name="description" />
    <meta content="Once24" name="author" />
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>

<?php
// Cada módulo es una Página de WordPress con slug fijo (dashboard, ingresos, gastos, empresas,
// contactos, servicios, equipo, oportunidades) resuelta automáticamente por WP vía page-{slug}.php,
// sin necesidad de asignar plantilla a mano en el editor.
$talos_nav = array(
    array( 'slug' => 'dashboard',     'label' => 'Dashboard',      'icon' => 'i-grid',       'group' => 'Principal' ),
    array( 'slug' => 'oportunidades', 'label' => 'Oportunidades',  'icon' => 'i-funnel',     'group' => 'Principal' ),
    array( 'slug' => 'empresas',      'label' => 'Empresas',       'icon' => 'i-building',  'group' => 'Relaciones' ),
    array( 'slug' => 'contactos',     'label' => 'Contactos',      'icon' => 'i-users',      'group' => 'Relaciones' ),
    array( 'slug' => 'servicios',     'label' => 'Servicios',      'icon' => 'i-tag',        'group' => 'Relaciones' ),
    array( 'slug' => 'ingresos',      'label' => 'Ingresos',       'icon' => 'i-trend-up',   'group' => 'Finanzas' ),
    array( 'slug' => 'gastos',        'label' => 'Gastos',         'icon' => 'i-trend-down', 'group' => 'Finanzas' ),
    array( 'slug' => 'equipo',        'label' => 'Equipo',         'icon' => 'i-badge',      'group' => 'Equipo' ),
);
if ( ! current_user_can( 'manage_options' ) ) {
    $talos_nav = array_values( array_filter( $talos_nav, function ( $item ) {
        return in_array( $item['slug'], talos_secciones_consulta(), true );
    } ) );
}
$talos_current_slug = get_queried_object() && isset( get_queried_object()->post_name ) ? get_queried_object()->post_name : '';
$talos_grupo_actual = null;

// Selector de mes universal: solo aplica a las 3 secciones con datos por periodo.
$talos_secciones_con_mes = array( 'dashboard', 'ingresos', 'gastos' );
$talos_mes_activo = talos_mes_activo();
talos_guardar_mes_activo( $talos_mes_activo );
$talos_mes_activo_str = $talos_mes_activo->format( 'Y-m' );
$talos_mes_activo_label = talos_meses_es()[ (int) $talos_mes_activo->format( 'n' ) ] . ' ' . $talos_mes_activo->format( 'Y' );

$talos_es_seccion_con_mes = in_array( $talos_current_slug, $talos_secciones_con_mes, true );
if ( $talos_es_seccion_con_mes ) {
    $talos_url_pagina_actual = home_url( '/' . $talos_current_slug . '/' );
    $talos_topbar_mes_prev = add_query_arg( 'mes', ( clone $talos_mes_activo )->modify( '-1 month' )->format( 'Y-m' ), $talos_url_pagina_actual );
    $talos_topbar_mes_next = add_query_arg( 'mes', ( clone $talos_mes_activo )->modify( '+1 month' )->format( 'Y-m' ), $talos_url_pagina_actual );
}
?>

<svg style="display:none">
  <defs>
    <g id="i-grid" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></g>
    <g id="i-funnel" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4h18l-7 8v6l-4 2v-8z"/></g>
    <g id="i-building" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="11" height="18"/><path d="M15 8h5v13h-5"/><path d="M8 7h0M11 7h0M8 11h0M11 11h0M8 15h0M11 15h0"/></g>
    <g id="i-users" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.5 2.7-6 6-6s6 2.5 6 6"/><path d="M16 8.2a3 3 0 1 1 3.6 2.95"/><path d="M15 14.3c2.6.4 4.5 2.6 5 5.7"/></g>
    <g id="i-tag" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12.3 3H5a2 2 0 0 0-2 2v7.3a2 2 0 0 0 .6 1.4l9.7 9.7a2 2 0 0 0 2.8 0l6-6a2 2 0 0 0 0-2.8l-9.8-9.6a2 2 0 0 0-1-.0z"/><circle cx="8" cy="8" r="1.5" fill="currentColor" stroke="none"/></g>
    <g id="i-trend-up" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 17l6-6 4 4 8-9"/><path d="M15 6h6v6"/></g>
    <g id="i-trend-down" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7l6 6 4-4 8 9"/><path d="M15 18h6v-6"/></g>
    <g id="i-badge" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4.2"/><path d="M8.5 11.8 7 21l5-2.6L17 21l-1.5-9.2"/></g>
    <g id="i-search" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></g>
    <g id="i-bell" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9a6 6 0 1 1 12 0c0 5 2 6 2 6H4s2-1 2-6"/><path d="M10 20a2 2 0 0 0 4 0"/></g>
    <g id="i-moon" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 14.7A8 8 0 1 1 9.3 4a6.4 6.4 0 0 0 10.7 10.7z"/></g>
    <g id="i-menu" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M3 12h18M3 18h18"/></g>
    <g id="i-chevron" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></g>
    <g id="i-clock" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></g>
    <g id="i-wallet" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7a2 2 0 0 1 2-2h13a1 1 0 0 1 1 1v3"/><path d="M3 7v11a2 2 0 0 0 2 2h14a1 1 0 0 0 1-1v-9a1 1 0 0 0-1-1H5a2 2 0 0 1-2-2z"/><circle cx="16.5" cy="13.5" r="1.4" fill="currentColor" stroke="none"/></g>
    <g id="i-receipt" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2h12v19l-3-2-3 2-3-2-3 2z"/><path d="M9 7h6M9 11h6M9 15h3"/></g>
    <g id="i-alert-clock" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 1.5"/><path d="M9 2h6"/></g>
    <g id="i-check" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l4 4 10-10"/></g>
    <g id="i-x" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6l12 12M18 6L6 18"/></g>
    <g id="i-plus" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></g>
    <g id="i-filter" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h16l-6 7v6l-4 2v-8z"/></g>
    <g id="i-send" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 3 11 13M21 3l-7 18-4-8-8-4 19-6z"/></g>
    <g id="i-paperclip" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 12.5l6.5-6.5a3 3 0 0 1 4.2 4.2L10.5 18.4a5 5 0 0 1-7-7L12 3"/></g>
    <g id="i-chevron-left" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6 9 12l6 6"/></g>
    <g id="i-chevron-right" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></g>
    <g id="i-trash" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M10 7V5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v2M6 7l1 13a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1l1-13"/></g>
    <g id="i-upload" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/></g>
    <g id="i-eye" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></g>
    <g id="i-edit" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></g>
    <g id="i-copy" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"/></g>
    <g id="i-trophy" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 4h8v5a4 4 0 0 1-8 0z"/><path d="M8 5H5a3 3 0 0 0 3 4M16 5h3a3 3 0 0 1-3 4"/><path d="M12 13v4M9 20h6M10 17h4v3h-4z"/></g>
    <g id="i-x-circle" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/></g>
    <g id="i-arrow-right" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></g>
  </defs>
</svg>

<aside class="sidebar" id="sidebar">
  <div class="brand">
    <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/once24-logo-white.png' ); ?>" alt="Once24">
    <span class="brand-sub">TALOS</span>
  </div>
  <nav class="nav-group">
    <?php foreach ( $talos_nav as $item ) :
        if ( $item['group'] !== $talos_grupo_actual ) :
            $talos_grupo_actual = $item['group'];
            ?><div class="nav-label"><?php echo esc_html( $talos_grupo_actual ); ?></div><?php
        endif;
        $talos_es_activo = ( $talos_current_slug === $item['slug'] );
        $talos_href = home_url( '/' . $item['slug'] . '/' );
        if ( in_array( $item['slug'], $talos_secciones_con_mes, true ) ) {
            $talos_href = add_query_arg( 'mes', $talos_mes_activo_str, $talos_href );
        }
        ?>
        <a class="nav-item<?php echo $talos_es_activo ? ' active' : ''; ?>" href="<?php echo esc_url( $talos_href ); ?>">
            <svg viewBox="0 0 24 24"><use href="#<?php echo esc_attr( $item['icon'] ); ?>"/></svg><?php echo esc_html( $item['label'] ); ?>
        </a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-foot">
    <div class="user-chip">
      <?php $talos_user = wp_get_current_user(); ?>
      <div class="avatar"><?php echo esc_html( talos_iniciales( $talos_user->display_name ) ); ?></div>
      <div>
        <div class="user-name"><?php echo esc_html( $talos_user->display_name ); ?></div>
        <div class="user-role"><?php echo esc_html( current_user_can( 'manage_options' ) ? 'Director' : 'Consulta' ); ?></div>
      </div>
    </div>
  </div>
</aside>

<div class="shell">
  <?php if ( function_exists( 'talos_modo_pruebas_activo' ) && talos_modo_pruebas_activo() ) : ?>
  <div class="banner-pruebas">⚠ MODO PRUEBAS ACTIVO — todos los correos se redirigen a <?php echo esc_html( get_option( 'talos_modo_pruebas_correo', 'jorgeletayf@gmail.com' ) ); ?>. Desactívalo en Ajustes → Talos: Modo Pruebas cuando termines.</div>
  <?php endif; ?>
  <header class="topbar">
    <button class="menu-btn" id="menuBtn" aria-label="Abrir menú"><svg viewBox="0 0 24 24"><use href="#i-menu"/></svg></button>
    <div class="search-box"><svg viewBox="0 0 24 24"><use href="#i-search"/></svg><input type="text" id="talosBuscadorGlobal" placeholder="Buscar empresa, contacto, oportunidad…" autocomplete="off"></div>
    <?php if ( $talos_es_seccion_con_mes ) : ?>
    <div class="topbar-mes">
      <a href="<?php echo esc_url( $talos_topbar_mes_prev ); ?>" class="icon-btn" aria-label="Mes anterior"><svg viewBox="0 0 24 24"><use href="#i-chevron-left"/></svg></a>
      <div class="month-picker-wrap">
        <button type="button" class="month-picker" id="btnAbrirSelectorMes" aria-live="polite"><?php echo esc_html( $talos_mes_activo_label ); ?></button>
        <div class="month-picker-pop" id="popoverSelectorMes">
          <div class="month-picker-pop-row">
            <select id="selectorMesRapidoMes">
              <?php foreach ( talos_meses_es() as $num => $nombre ) : ?>
                <option value="<?php echo esc_attr( $num ); ?>" <?php selected( $num, (int) $talos_mes_activo->format( 'n' ) ); ?>><?php echo esc_html( $nombre ); ?></option>
              <?php endforeach; ?>
            </select>
            <input type="number" id="selectorMesRapidoAnio" value="<?php echo esc_attr( $talos_mes_activo->format( 'Y' ) ); ?>" min="2015" max="2100">
          </div>
          <button type="button" class="btn-primary" id="btnIrAMes">Ir</button>
        </div>
      </div>
      <a href="<?php echo esc_url( $talos_topbar_mes_next ); ?>" class="icon-btn" aria-label="Mes siguiente"><svg viewBox="0 0 24 24"><use href="#i-chevron-right"/></svg></a>
    </div>
    <?php endif; ?>
    <div class="topbar-spacer"></div>
    <button class="icon-btn" aria-label="Notificaciones"><svg viewBox="0 0 24 24"><use href="#i-bell"/></svg><span class="dot"></span></button>
    <div class="topbar-avatar"><?php echo esc_html( talos_iniciales( $talos_user->display_name ) ); ?></div>
  </header>

  <main class="page">
