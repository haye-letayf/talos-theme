<?php
/**
 * Cerebro de la Piel: Talos 2.0 Theme
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Seguridad

require_once get_template_directory() . '/includes/ajax-ingresos.php';
require_once get_template_directory() . '/includes/ajax-gastos.php';

function talos_enqueue_assets() {
    $uri = get_template_directory_uri();
    $dir = get_template_directory();

    // Tipografía de marca (Once24: Poppins)
    wp_enqueue_style( 'talos-fonts', 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap', array(), null );

    // Shell + componentes propios (sidebar, topbar, tarjetas, tablas, pills, botones) — ver CLAUDE.md:
    // se decidió portar el CSS ya aprobado en los mockups en vez de pelear con el Bootstrap
    // de la plantilla base (sus clases .sidenav/.navbar-custom/.bg-home no existen en su propio CSS).
    // Versión = filemtime() del archivo, no el Version del theme header: así cada cambio
    // revienta el caché del navegador/plugins solo, sin depender de subir un número a mano.
    $shell_css = $dir . '/assets/css/talos-shell.css';
    wp_enqueue_style( 'talos-shell', $uri . '/assets/css/talos-shell.css', array( 'talos-fonts' ), file_exists( $shell_css ) ? filemtime( $shell_css ) : false );

    $app_js = $dir . '/assets/js/talos-app.js';
    wp_enqueue_script( 'talos-app', $uri . '/assets/js/talos-app.js', array(), file_exists( $app_js ) ? filemtime( $app_js ) : false, true );
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

/**
 * Selector de mes universal (Dashboard/Ingresos/Gastos): el mes activo viene del
 * parámetro ?mes=YYYY-MM si está presente, si no de la cookie talos_mes_activo
 * (lo último que se vio), si no del mes real en curso. header.php vuelve a
 * guardar la cookie en cada carga para que, al navegar a otra sección desde el
 * sidebar, el mes seleccionado se mantenga sin tener que repetirlo en la URL.
 */
function talos_mes_activo() {
    $param = isset( $_GET['mes'] ) ? sanitize_text_field( wp_unslash( $_GET['mes'] ) ) : '';
    $mes = DateTime::createFromFormat( 'Y-m', $param );
    if ( ! $mes && isset( $_COOKIE['talos_mes_activo'] ) ) {
        $mes = DateTime::createFromFormat( 'Y-m', sanitize_text_field( wp_unslash( $_COOKIE['talos_mes_activo'] ) ) );
    }
    return $mes ? $mes->modify( 'first day of this month' ) : new DateTime( 'first day of this month' );
}

function talos_guardar_mes_activo( DateTime $mes ) {
    if ( ! headers_sent() ) {
        setcookie( 'talos_mes_activo', $mes->format( 'Y-m' ), time() + 60 * DAY_IN_SECONDS, '/' );
    }
}

function talos_meses_es() {
    return array( 1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre' );
}

function talos_meses_cortos() {
    return array( 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic' );
}
