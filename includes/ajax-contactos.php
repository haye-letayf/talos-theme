<?php
/**
 * AJAX de Contactos: creación rápida, guardado de la ficha completa y eliminar.
 * Mismo patrón ya establecido en Empresas (includes/ajax-empresas.php).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function talos_con_verificar_nonce() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'talos_contactos' ) ) {
        wp_send_json_error( array( 'mensaje' => 'Sesión inválida, recarga la página.' ), 403 );
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'mensaje' => 'No tienes permiso para esta acción.' ), 403 );
    }
}

/**
 * Creación mínima (Nombre + Empresa + Estatus) — el resto se completa justo
 * después, en modo edición.
 */
function talos_ajax_crear_contacto() {
    talos_con_verificar_nonce();

    $nombre = isset( $_POST['nombre'] ) ? sanitize_text_field( wp_unslash( $_POST['nombre'] ) ) : '';
    if ( '' === $nombre ) {
        wp_send_json_error( array( 'mensaje' => 'Captura el nombre del contacto.' ) );
    }
    $empresa_id = isset( $_POST['empresa_id'] ) ? (int) $_POST['empresa_id'] : 0;
    if ( ! $empresa_id || 'talos_company' !== get_post_type( $empresa_id ) ) {
        wp_send_json_error( array( 'mensaje' => 'Selecciona una empresa ya registrada.' ) );
    }

    $nuevo_id = wp_insert_post( array(
        'post_type'   => 'talos_contact',
        'post_title'  => $nombre,
        'post_status' => 'publish',
    ), true );

    if ( is_wp_error( $nuevo_id ) || ! $nuevo_id ) {
        wp_send_json_error( array( 'mensaje' => 'No se pudo crear el contacto.' ) );
    }

    update_field( 'contact_company', $empresa_id, $nuevo_id );
    $estatus = isset( $_POST['estatus'] ) ? sanitize_text_field( wp_unslash( $_POST['estatus'] ) ) : 'active';
    update_field( 'contact_status', $estatus, $nuevo_id );

    wp_send_json_success( array( 'id' => $nuevo_id ) );
}
add_action( 'wp_ajax_talos_crear_contacto', 'talos_ajax_crear_contacto' );

function talos_ajax_guardar_contacto() {
    talos_con_verificar_nonce();

    $id = isset( $_POST['contacto_id'] ) ? (int) $_POST['contacto_id'] : 0;
    if ( ! $id || 'talos_contact' !== get_post_type( $id ) ) {
        wp_send_json_error( array( 'mensaje' => 'Contacto no válido.' ) );
    }

    $nombre = isset( $_POST['nombre'] ) ? sanitize_text_field( wp_unslash( $_POST['nombre'] ) ) : '';
    if ( '' !== $nombre ) {
        wp_update_post( array( 'ID' => $id, 'post_title' => $nombre ) );
    }

    $campos_texto = array( 'contact_firstname', 'contact_lastname', 'contact_phone_office', 'contact_phone_mobile' );
    foreach ( $campos_texto as $campo ) {
        if ( isset( $_POST[ $campo ] ) ) {
            update_field( $campo, sanitize_text_field( wp_unslash( $_POST[ $campo ] ) ), $id );
        }
    }

    foreach ( array( 'contact_company_email', 'contact_personal_email' ) as $campo_correo ) {
        if ( isset( $_POST[ $campo_correo ] ) ) {
            update_field( $campo_correo, sanitize_email( wp_unslash( $_POST[ $campo_correo ] ) ), $id );
        }
    }

    foreach ( array( 'contact_status', 'contact_class', 'contact_role' ) as $campo_select ) {
        if ( isset( $_POST[ $campo_select ] ) ) {
            update_field( $campo_select, sanitize_text_field( wp_unslash( $_POST[ $campo_select ] ) ), $id );
        }
    }

    if ( isset( $_POST['contact_company'] ) ) {
        $empresa_id = (int) $_POST['contact_company'];
        if ( $empresa_id && 'talos_company' === get_post_type( $empresa_id ) ) {
            update_field( 'contact_company', $empresa_id, $id );
        }
    }

    update_field( 'contact_billing_recipient', ! empty( $_POST['contact_billing_recipient'] ), $id );

    wp_send_json_success();
}
add_action( 'wp_ajax_talos_guardar_contacto', 'talos_ajax_guardar_contacto' );

/**
 * Elimina (papelera, recuperable desde wp-admin) un Contacto.
 */
function talos_ajax_eliminar_contacto() {
    talos_con_verificar_nonce();

    $id = isset( $_POST['contacto_id'] ) ? (int) $_POST['contacto_id'] : 0;
    if ( ! $id || 'talos_contact' !== get_post_type( $id ) ) {
        wp_send_json_error( array( 'mensaje' => 'Contacto no válido.' ) );
    }

    if ( ! wp_trash_post( $id ) ) {
        wp_send_json_error( array( 'mensaje' => 'No se pudo eliminar el contacto.' ) );
    }

    wp_send_json_success();
}
add_action( 'wp_ajax_talos_eliminar_contacto', 'talos_ajax_eliminar_contacto' );
