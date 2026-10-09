<?php
/**
 * AJAX de Servicios (catálogo, talos_service_cat): creación rápida, guardado
 * de la ficha completa y eliminar. Mismo patrón que ajax-empresas.php /
 * ajax-contactos.php.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function talos_srv_verificar_nonce() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'talos_servicios' ) ) {
        wp_send_json_error( array( 'mensaje' => 'Sesión inválida, recarga la página.' ), 403 );
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'mensaje' => 'No tienes permiso para esta acción.' ), 403 );
    }
}

/**
 * Creación mínima (Nombre + Frecuencia) — precio y descripción se completan
 * justo después, en modo edición.
 */
function talos_ajax_crear_servicio() {
    talos_srv_verificar_nonce();

    $nombre = isset( $_POST['nombre'] ) ? sanitize_text_field( wp_unslash( $_POST['nombre'] ) ) : '';
    if ( '' === $nombre ) {
        wp_send_json_error( array( 'mensaje' => 'Captura el nombre del servicio.' ) );
    }

    $nuevo_id = wp_insert_post( array(
        'post_type'   => 'talos_service_cat',
        'post_title'  => $nombre,
        'post_status' => 'publish',
    ), true );

    if ( is_wp_error( $nuevo_id ) || ! $nuevo_id ) {
        wp_send_json_error( array( 'mensaje' => 'No se pudo crear el servicio.' ) );
    }

    $frecuencia = isset( $_POST['frecuencia'] ) ? sanitize_text_field( wp_unslash( $_POST['frecuencia'] ) ) : '';
    if ( $frecuencia ) {
        update_field( 'service_ref_frequency', $frecuencia, $nuevo_id );
    }

    wp_send_json_success( array( 'id' => $nuevo_id ) );
}
add_action( 'wp_ajax_talos_crear_servicio', 'talos_ajax_crear_servicio' );

function talos_ajax_guardar_servicio() {
    talos_srv_verificar_nonce();

    $id = isset( $_POST['servicio_id'] ) ? (int) $_POST['servicio_id'] : 0;
    if ( ! $id || 'talos_service_cat' !== get_post_type( $id ) ) {
        wp_send_json_error( array( 'mensaje' => 'Servicio no válido.' ) );
    }

    $nombre = isset( $_POST['nombre'] ) ? sanitize_text_field( wp_unslash( $_POST['nombre'] ) ) : '';
    if ( '' !== $nombre ) {
        wp_update_post( array( 'ID' => $id, 'post_title' => $nombre ) );
    }

    if ( isset( $_POST['service_description'] ) ) {
        update_field( 'service_description', sanitize_textarea_field( wp_unslash( $_POST['service_description'] ) ), $id );
    }
    if ( isset( $_POST['service_ref_frequency'] ) ) {
        update_field( 'service_ref_frequency', sanitize_text_field( wp_unslash( $_POST['service_ref_frequency'] ) ), $id );
    }
    if ( isset( $_POST['service_ref_price_mxn'] ) ) {
        update_field( 'service_ref_price_mxn', (float) $_POST['service_ref_price_mxn'], $id );
    }
    if ( isset( $_POST['service_ref_price_usd'] ) ) {
        update_field( 'service_ref_price_usd', (float) $_POST['service_ref_price_usd'], $id );
    }

    wp_send_json_success();
}
add_action( 'wp_ajax_talos_guardar_servicio', 'talos_ajax_guardar_servicio' );

/**
 * Elimina (papelera, recuperable desde wp-admin) un Servicio del catálogo.
 * El front ya avisó si está contratado en el repeater company_services de
 * alguna Empresa; aquí no se bloquea nada, Jorge decide.
 */
function talos_ajax_eliminar_servicio() {
    talos_srv_verificar_nonce();

    $id = isset( $_POST['servicio_id'] ) ? (int) $_POST['servicio_id'] : 0;
    if ( ! $id || 'talos_service_cat' !== get_post_type( $id ) ) {
        wp_send_json_error( array( 'mensaje' => 'Servicio no válido.' ) );
    }

    if ( ! wp_trash_post( $id ) ) {
        wp_send_json_error( array( 'mensaje' => 'No se pudo eliminar el servicio.' ) );
    }

    wp_send_json_success();
}
add_action( 'wp_ajax_talos_eliminar_servicio', 'talos_ajax_eliminar_servicio' );
