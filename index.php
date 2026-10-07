<?php
// Catch-all: en condiciones normales nunca se ve, porque las 8 Páginas de módulo
// (dashboard, ingresos, gastos, empresas, contactos, servicios, equipo, oportunidades)
// cada una resuelve a su propia page-{slug}.php. Si alguien llega a la raíz del sitio,
// lo mandamos directo al Dashboard.
$talos_dashboard = get_page_by_path( 'dashboard' );
if ( $talos_dashboard ) {
    wp_safe_redirect( get_permalink( $talos_dashboard ) );
    exit;
}

get_header();
?>
<div class="page">
  <div class="card">
    <div class="card-head"><h3>Talos 2.0</h3></div>
    <p style="color:var(--text-muted);font-size:13px;line-height:1.6;">
        No existe todavía la Página "Dashboard" (slug <code>dashboard</code>). Crea las 8 páginas
        de módulo en wp-admin (Dashboard, Ingresos, Gastos, Empresas, Contactos, Servicios, Equipo,
        Oportunidades, con esos slugs exactos) para que cada una cargue su plantilla automáticamente.
    </p>
  </div>
</div>
<?php get_footer(); ?>
