<?php
/**
 * Cerebro de la Piel: Talos 2.0 Theme
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Seguridad

function talos_enqueue_assets() {
    $uri = get_template_directory_uri();
    $ver = wp_get_theme()->get( 'Version' );

    // 1. CSS
    wp_enqueue_style( 'talos-bootstrap', $uri . '/assets/css/bootstrap.min.css', array(), $ver );
    wp_enqueue_style( 'talos-icons', $uri . '/assets/css/materialdesignicons.min.css', array(), $ver );
    wp_enqueue_style( 'talos-style', $uri . '/assets/css/style.css', array('talos-bootstrap'), $ver );

    // 2. JS
    wp_enqueue_script( 'jquery' );
    wp_enqueue_script( 'talos-bootstrap-js', $uri . '/assets/js/bootstrap.min.js', array('jquery'), $ver, true );
    wp_enqueue_script( 'talos-app', $uri . '/assets/js/app.js', array('jquery', 'talos-bootstrap-js'), $ver, true );
}
add_action( 'wp_enqueue_scripts', 'talos_enqueue_assets' );

/**
 * =========================================================================
 * SCRIPT NINJA: IMPORTACIÓN MASIVA DE SERVICIOS
 * =========================================================================
 */
add_action( 'admin_init', 'talos_inyectar_servicios_masivos' );
function talos_inyectar_servicios_masivos() {
    
    // Candado: Si ya se ejecutó una vez, nos salimos para no duplicar.
    if ( get_option( 'talos_servicios_importados_v1' ) ) {
        return;
    }

    // Tu lista exacta de 28 servicios
    $servicios = [
        'Admin. Campaña META', 'Admin. Campaña GADS', 'Admin. Campaña LinkedIn', 
        'Admin. Redes Sociales', 'Campaign GADS', 'Campaign INDEED', 
        'Campaign LinkedIn', 'Campaign META', 'Casillas de Correo', 
        'Conferencia Presencial', 'Consultoría', 'Desarrollo Personalizado', 
        'Diseño Especial', 'Dominio', 'GTM+GADS Setup', 'Hosting', 
        'Hosting+Webmaster', 'Landing Page', 'Licencia Plugin', 'META Setup', 
        'OpenREAL Licence', 'OpenREAL Setup', 'OpenREAL Website', 'Página Web', 
        'Plan Esencial', 'Tienda en Línea', 'Webinar', 'Webmaster'
    ];

    foreach ( $servicios as $titulo ) {
        $nuevo_servicio = array(
            'post_title'  => $titulo,
            'post_type'   => 'talos_service_cat',
            'post_status' => 'publish' // Publicado directamente
        );
        // Insertamos silenciosamente en la base de datos
        wp_insert_post( $nuevo_servicio );
    }

    // Cerramos el candado para siempre
    update_option( 'talos_servicios_importados_v1', true );
}