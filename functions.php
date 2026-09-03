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