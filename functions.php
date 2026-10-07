<?php
/**
 * Cerebro de la Piel: Talos 2.0 Theme
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Seguridad

require_once get_template_directory() . '/includes/ajax-ingresos.php';
require_once get_template_directory() . '/includes/ajax-gastos.php';

function talos_enqueue_assets() {
    $uri = get_template_directory_uri();
    $ver = wp_get_theme()->get( 'Version' );

    // Tipografía de marca (Once24: Poppins)
    wp_enqueue_style( 'talos-fonts', 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap', array(), null );

    // Shell + componentes propios (sidebar, topbar, tarjetas, tablas, pills, botones) — ver CLAUDE.md:
    // se decidió portar el CSS ya aprobado en los mockups en vez de pelear con el Bootstrap
    // de la plantilla base (sus clases .sidenav/.navbar-custom/.bg-home no existen en su propio CSS).
    wp_enqueue_style( 'talos-shell', $uri . '/assets/css/talos-shell.css', array( 'talos-fonts' ), $ver );

    wp_enqueue_script( 'talos-app', $uri . '/assets/js/talos-app.js', array(), $ver, true );
}
add_action( 'wp_enqueue_scripts', 'talos_enqueue_assets' );

/**
 * Iniciales de un nombre completo, para avatares circulares ("Jorge Letayf" -> "JL").
 */
function talos_iniciales( $nombre ) {
    $partes = preg_split( '/\s+/', trim( (string) $nombre ) );
    $partes = array_filter( $partes );
    $iniciales = array_map( function ( $palabra ) {
        return mb_strtoupper( mb_substr( $palabra, 0, 1 ) );
    }, array_slice( $partes, 0, 2 ) );
    return implode( '', $iniciales );
}

/**
 * Formatea un número como moneda MXN ($12,345), sin decimales — mismo criterio que los mockups.
 */
function talos_fmt_mxn( $numero ) {
    return '$' . number_format( (float) $numero, 0, '.', ',' );
}
