<?php
/**
 * AJAX de Empresas: creación rápida (mínima) y guardado de la ficha completa.
 * company_socials (repeater de redes sociales) queda fuera de la ficha por
 * ahora — se sigue editando desde wp-admin.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function talos_emp_verificar_nonce() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'talos_empresas' ) ) {
        wp_send_json_error( array( 'mensaje' => 'Sesión inválida, recarga la página.' ), 403 );
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'mensaje' => 'No tienes permiso para esta acción.' ), 403 );
    }
}

/**
 * Creación mínima (Nombre + Clase + Estatus) — el resto de la ficha se llena
 * justo después, en modo edición, mismo criterio ya usado en Oportunidades.
 */
function talos_ajax_crear_empresa() {
    talos_emp_verificar_nonce();

    $nombre = isset( $_POST['nombre'] ) ? sanitize_text_field( wp_unslash( $_POST['nombre'] ) ) : '';
    if ( '' === $nombre ) {
        wp_send_json_error( array( 'mensaje' => 'Captura el nombre de la empresa.' ) );
    }

    $nuevo_id = wp_insert_post( array(
        'post_type'   => 'talos_company',
        'post_title'  => $nombre,
        'post_status' => 'publish',
    ), true );

    if ( is_wp_error( $nuevo_id ) || ! $nuevo_id ) {
        wp_send_json_error( array( 'mensaje' => 'No se pudo crear la empresa.' ) );
    }

    $clase   = isset( $_POST['clase'] ) ? sanitize_text_field( wp_unslash( $_POST['clase'] ) ) : '';
    $estatus = isset( $_POST['estatus'] ) ? sanitize_text_field( wp_unslash( $_POST['estatus'] ) ) : 'active';
    if ( $clase ) {
        update_field( 'company_class', $clase, $nuevo_id );
    }
    update_field( 'company_status', $estatus, $nuevo_id );

    wp_send_json_success( array( 'id' => $nuevo_id ) );
}
add_action( 'wp_ajax_talos_crear_empresa', 'talos_ajax_crear_empresa' );

/**
 * Guarda la ficha completa (Clasificación / Datos Fiscales / Contacto y
 * Ubicación). Las fechas llegan como Y-m-d (nativo de <input type="date">) y
 * se convierten a Ymd antes de guardar — así es como ACF espera escribir un
 * date_picker, sin importar su return_format de lectura.
 */
function talos_ajax_guardar_empresa() {
    talos_emp_verificar_nonce();

    $id = isset( $_POST['empresa_id'] ) ? (int) $_POST['empresa_id'] : 0;
    if ( ! $id || 'talos_company' !== get_post_type( $id ) ) {
        wp_send_json_error( array( 'mensaje' => 'Empresa no válida.' ) );
    }

    $nombre = isset( $_POST['nombre'] ) ? sanitize_text_field( wp_unslash( $_POST['nombre'] ) ) : '';
    if ( '' !== $nombre ) {
        wp_update_post( array( 'ID' => $id, 'post_title' => $nombre ) );
    }

    $campos_texto = array( 'company_legal_name', 'company_rfc', 'company_address', 'company_phone', 'company_whatsapp' );
    foreach ( $campos_texto as $campo ) {
        if ( isset( $_POST[ $campo ] ) ) {
            update_field( $campo, sanitize_text_field( wp_unslash( $_POST[ $campo ] ) ), $id );
        }
    }

    if ( isset( $_POST['company_website'] ) ) {
        update_field( 'company_website', sanitize_url( wp_unslash( $_POST['company_website'] ) ), $id );
    }
    if ( isset( $_POST['company_zip'] ) ) {
        update_field( 'company_zip', (float) $_POST['company_zip'], $id );
    }

    $campos_select = array( 'company_status', 'company_class', 'sector', 'company_state', 'company_tax_regime', 'company_cfdi', 'company_country' );
    foreach ( $campos_select as $campo ) {
        if ( isset( $_POST[ $campo ] ) ) {
            update_field( $campo, sanitize_text_field( wp_unslash( $_POST[ $campo ] ) ), $id );
        }
    }

    if ( isset( $_POST['company_account_manager'] ) ) {
        $am_id = (int) $_POST['company_account_manager'];
        update_field( 'company_account_manager', $am_id ?: null, $id );
    }

    foreach ( array( 'company_start_op', 'company_end_op' ) as $campo_fecha ) {
        if ( isset( $_POST[ $campo_fecha ] ) ) {
            $valor = sanitize_text_field( wp_unslash( $_POST[ $campo_fecha ] ) );
            update_field( $campo_fecha, $valor ? str_replace( '-', '', $valor ) : '', $id );
        }
    }

    wp_send_json_success();
}
add_action( 'wp_ajax_talos_guardar_empresa', 'talos_ajax_guardar_empresa' );

/**
 * Elimina (mueve a la papelera de WordPress, NO borrado permanente) una
 * Empresa — mismo criterio que Ingresos/Gastos: recuperable desde wp-admin.
 * El front ya avisó antes de confirmar si tenía Contactos/Oportunidades/
 * Ingresos relacionados; aquí no se bloquea nada, Jorge decide.
 */
function talos_ajax_eliminar_empresa() {
    talos_emp_verificar_nonce();

    $id = isset( $_POST['empresa_id'] ) ? (int) $_POST['empresa_id'] : 0;
    if ( ! $id || 'talos_company' !== get_post_type( $id ) ) {
        wp_send_json_error( array( 'mensaje' => 'Empresa no válida.' ) );
    }

    if ( ! wp_trash_post( $id ) ) {
        wp_send_json_error( array( 'mensaje' => 'No se pudo eliminar la empresa.' ) );
    }

    wp_send_json_success();
}
add_action( 'wp_ajax_talos_eliminar_empresa', 'talos_ajax_eliminar_empresa' );
