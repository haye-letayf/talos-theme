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
        // Texto, no número: (float) le quitaba el cero inicial ("06500" ->
        // 6500) y guardaba 0 cuando el campo iba vacío. Un CP de 1 a 4
        // dígitos se rellena a 5 (México); otros formatos pasan igual.
        $cp = sanitize_text_field( wp_unslash( $_POST['company_zip'] ) );
        if ( '' === ltrim( $cp, '0' ) ) $cp = '';
        if ( preg_match( '/^\d{1,4}$/', $cp ) ) $cp = str_pad( $cp, 5, '0', STR_PAD_LEFT );
        update_field( 'company_zip', $cp, $id );
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

/**
 * Alta / edición de un Servicio Contratado (talos_contract) desde la ficha de
 * la Empresa. La Utilidad (precio − costo) la calcula talos-core en
 * acf/save_post; aquí solo se capturan los campos.
 */
function talos_ajax_guardar_contrato() {
    talos_emp_verificar_nonce();

    $contrato_id = isset( $_POST['contrato_id'] ) ? (int) $_POST['contrato_id'] : 0;
    $empresa_id  = isset( $_POST['empresa_id'] ) ? (int) $_POST['empresa_id'] : 0;
    $servicio_id = isset( $_POST['servicio_id'] ) ? (int) $_POST['servicio_id'] : 0;
    $frecuencias = array( 'pago_unico', 'por_hora', 'semanal', 'quincenal', 'mensual', 'bimestral', 'trimestral', 'semestral', 'anual' );
    $frecuencia  = isset( $_POST['frecuencia'] ) ? sanitize_key( $_POST['frecuencia'] ) : '';
    $inicio      = DateTime::createFromFormat( '!Y-m-d', isset( $_POST['inicio'] ) ? sanitize_text_field( wp_unslash( $_POST['inicio'] ) ) : '' );
    $fin_texto   = isset( $_POST['fin'] ) ? sanitize_text_field( wp_unslash( $_POST['fin'] ) ) : '';
    $fin         = '' === $fin_texto ? null : DateTime::createFromFormat( '!Y-m-d', $fin_texto );
    $diferido    = ! empty( $_POST['diferido'] );
    $meses       = isset( $_POST['meses'] ) ? (int) $_POST['meses'] : 0;

    if ( ! $empresa_id || 'talos_company' !== get_post_type( $empresa_id ) ) {
        wp_send_json_error( array( 'mensaje' => 'Empresa no válida.' ) );
    }
    if ( $contrato_id && ( 'talos_contract' !== get_post_type( $contrato_id ) || (int) get_field( 'service_company', $contrato_id, false ) !== $empresa_id ) ) {
        wp_send_json_error( array( 'mensaje' => 'Ese servicio no es de esta empresa.' ) );
    }
    if ( ! $servicio_id || 'talos_service_cat' !== get_post_type( $servicio_id ) ) {
        wp_send_json_error( array( 'mensaje' => 'Elige el servicio del catálogo.' ) );
    }
    if ( ! in_array( $frecuencia, $frecuencias, true ) ) {
        wp_send_json_error( array( 'mensaje' => 'Elige la frecuencia.' ) );
    }
    if ( ! $inicio ) {
        wp_send_json_error( array( 'mensaje' => 'Elige la fecha de inicio.' ) );
    }
    if ( '' !== $fin_texto && ( ! $fin || $fin < $inicio ) ) {
        wp_send_json_error( array( 'mensaje' => 'La fecha de fin debe ser posterior al inicio.' ) );
    }
    if ( $diferido && $meses < 1 ) {
        wp_send_json_error( array( 'mensaje' => 'Escribe los meses del diferido.' ) );
    }

    $titulo = get_the_title( $empresa_id ) . ' – ' . get_the_title( $servicio_id );
    if ( $contrato_id ) {
        wp_update_post( array( 'ID' => $contrato_id, 'post_title' => $titulo ) );
    } else {
        $contrato_id = wp_insert_post( array(
            'post_type'   => 'talos_contract',
            'post_title'  => $titulo,
            'post_status' => 'publish',
        ), true );
        if ( is_wp_error( $contrato_id ) ) {
            wp_send_json_error( array( 'mensaje' => 'No se pudo crear el servicio.' ) );
        }
    }

    update_field( 'service_company', $empresa_id, $contrato_id );
    update_field( 'service_item', $servicio_id, $contrato_id );
    update_field( 'service_invoice_description', sanitize_text_field( wp_unslash( $_POST['descripcion'] ?? '' ) ), $contrato_id );
    update_field( 'service_frequency', $frecuencia, $contrato_id );
    update_field( 'service_price', round( (float) ( $_POST['precio'] ?? 0 ), 2 ), $contrato_id );
    update_field( 'service_cost', round( (float) ( $_POST['costo'] ?? 0 ), 2 ), $contrato_id );
    update_field( 'service_applies_iva', ! empty( $_POST['iva'] ) ? 1 : 0, $contrato_id );
    update_field( 'service_deferred', $diferido ? 1 : 0, $contrato_id );
    update_field( 'service_deferred_months', $diferido ? $meses : '', $contrato_id );
    update_field( 'service_start_date', $inicio->format( 'Ymd' ), $contrato_id );
    update_field( 'service_end_date', $fin ? $fin->format( 'Ymd' ) : '', $contrato_id );
    update_field( 'service_status', ! empty( $_POST['activo'] ) ? 1 : 0, $contrato_id );
    update_field( 'service_reminder_allowed', ! empty( $_POST['recordatorio'] ) ? 1 : 0, $contrato_id );
    update_field( 'service_suspension_reason', sanitize_textarea_field( wp_unslash( $_POST['motivo'] ?? '' ) ), $contrato_id );
    do_action( 'acf/save_post', $contrato_id );

    wp_send_json_success( array( 'id' => $contrato_id ) );
}
add_action( 'wp_ajax_talos_guardar_contrato', 'talos_ajax_guardar_contrato' );
