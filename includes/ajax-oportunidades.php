<?php
/**
 * AJAX de Oportunidades: mover de etapa (Kanban) y creación rápida.
 * update_field() por sí solo NO dispara acf/save_post (motor-oportunidades.php
 * depende de ese hook para generar referencia/vigencia/conversión a cliente),
 * así que después de escribir el campo se dispara manualmente — igual que si
 * se hubiera guardado el formulario normal de wp-admin.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function talos_opp_verificar_nonce() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'talos_oportunidades' ) ) {
        wp_send_json_error( array( 'mensaje' => 'Sesión inválida, recarga la página.' ), 403 );
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'mensaje' => 'No tienes permiso para esta acción.' ), 403 );
    }
}

function talos_ajax_mover_etapa_oportunidad() {
    talos_opp_verificar_nonce();

    $id = isset( $_POST['oportunidad_id'] ) ? (int) $_POST['oportunidad_id'] : 0;
    $etapa = isset( $_POST['etapa'] ) ? sanitize_text_field( wp_unslash( $_POST['etapa'] ) ) : '';
    $etapas_validas = array( 'prospeccion', 'propuesta_enviada', 'en_negociacion', 'ganada', 'perdida' );

    if ( ! $id || 'talos_opportunity' !== get_post_type( $id ) ) {
        wp_send_json_error( array( 'mensaje' => 'Oportunidad no válida.' ) );
    }
    if ( ! in_array( $etapa, $etapas_validas, true ) ) {
        wp_send_json_error( array( 'mensaje' => 'Etapa no válida.' ) );
    }

    update_field( 'opportunity_stage', $etapa, $id );
    do_action( 'acf/save_post', $id ); // dispara las automatizaciones de motor-oportunidades.php

    wp_send_json_success( array( 'etapa' => $etapa ) );
}
add_action( 'wp_ajax_talos_mover_etapa_oportunidad', 'talos_ajax_mover_etapa_oportunidad' );

function talos_ajax_crear_oportunidad() {
    talos_opp_verificar_nonce();

    $empresa_id     = isset( $_POST['empresa_id'] ) ? (int) $_POST['empresa_id'] : 0;
    $nombre         = isset( $_POST['nombre'] ) ? sanitize_text_field( wp_unslash( $_POST['nombre'] ) ) : '';
    $etapa          = isset( $_POST['etapa'] ) ? sanitize_text_field( wp_unslash( $_POST['etapa'] ) ) : 'prospeccion';
    $valor_estimado = isset( $_POST['valor_estimado'] ) ? (float) $_POST['valor_estimado'] : 0;
    $etapas_validas_creacion = array( 'prospeccion', 'propuesta_enviada', 'en_negociacion' );

    if ( ! $empresa_id || 'talos_company' !== get_post_type( $empresa_id ) ) {
        wp_send_json_error( array( 'mensaje' => 'Selecciona una empresa ya registrada.' ) );
    }
    if ( '' === $nombre ) {
        wp_send_json_error( array( 'mensaje' => 'Captura el nombre de la oportunidad.' ) );
    }
    if ( ! in_array( $etapa, $etapas_validas_creacion, true ) ) {
        $etapa = 'prospeccion';
    }

    $nuevo_id = wp_insert_post( array(
        'post_type'   => 'talos_opportunity',
        'post_title'  => $nombre,
        'post_status' => 'publish',
    ), true );

    if ( is_wp_error( $nuevo_id ) ) {
        wp_send_json_error( array( 'mensaje' => 'No se pudo crear la oportunidad.' ) );
    }

    update_field( 'opportunity_company', $empresa_id, $nuevo_id );
    update_field( 'opportunity_name', $nombre, $nuevo_id );
    update_field( 'opportunity_stage', $etapa, $nuevo_id );

    // No hay campo plano de "valor estimado" en Oportunidades — el total de la
    // tarjeta siempre sale de sumar quote_items. Para que el número capturado
    // aquí se refleje de inmediato, se crea un primer concepto genérico (sin
    // servicio de catálogo asociado) con ese valor; Jorge lo detalla/reemplaza
    // después en wp-admin cuando tenga los conceptos reales de la cotización.
    if ( $valor_estimado > 0 ) {
        update_field( 'quote_items', array( array(
            'service_item'                => '',
            'service_invoice_description' => 'Estimado inicial — pendiente de detallar',
            'service_frequency'           => '',
            'service_quantity'            => 1,
            'service_price'               => $valor_estimado,
            'service_cost'                => 0,
            'service_applies_iva'         => 0,
            'item_has_discount'           => 0,
            'item_discount_type'          => '',
            'item_discount_value'         => 0,
        ) ), $nuevo_id );
    }

    do_action( 'acf/save_post', $nuevo_id ); // genera quote_reference + vigencia por defecto

    $empresa_post = get_post( $empresa_id );
    wp_send_json_success( array(
        'id'        => $nuevo_id,
        'etapa'     => $etapa,
        'empresa'   => $empresa_post ? $empresa_post->post_title : '',
        'nombre'    => $nombre,
        'referencia'=> get_field( 'quote_reference', $nuevo_id ),
    ) );
}
add_action( 'wp_ajax_talos_crear_oportunidad', 'talos_ajax_crear_oportunidad' );

/** Fecha de <input type="date"> (Y-m-d) -> Ymd para un date_picker de ACF ('' si no es válida). */
function talos_opp_fecha_ymd( $clave ) {
    $valor = isset( $_POST[ $clave ] ) ? sanitize_text_field( wp_unslash( $_POST[ $clave ] ) ) : '';
    $fecha = DateTime::createFromFormat( '!Y-m-d', $valor );
    return $fecha ? $fecha->format( 'Ymd' ) : '';
}

/**
 * Guarda la ficha completa de una Oportunidad (fichas/ficha-oportunidad.php).
 * Al final dispara acf/save_post, igual que wp-admin: referencia COT,
 * vigencia, fecha de envío y conversión a Cliente si queda en "Ganada".
 * Una Oportunidad ya convertida no cambia sus conceptos (decisión
 * 2026-11-04): los precios se ajustan en los contratos de la Empresa.
 */
function talos_ajax_guardar_oportunidad() {
    talos_opp_verificar_nonce();

    $id = isset( $_POST['oportunidad_id'] ) ? (int) $_POST['oportunidad_id'] : 0;
    if ( ! $id || 'talos_opportunity' !== get_post_type( $id ) ) {
        wp_send_json_error( array( 'mensaje' => 'Oportunidad no válida.' ) );
    }

    $nombre     = isset( $_POST['opportunity_name'] ) ? sanitize_text_field( wp_unslash( $_POST['opportunity_name'] ) ) : '';
    $empresa_id = isset( $_POST['opportunity_company'] ) ? (int) $_POST['opportunity_company'] : 0;
    $etapa      = isset( $_POST['opportunity_stage'] ) ? sanitize_text_field( wp_unslash( $_POST['opportunity_stage'] ) ) : '';
    $contacto   = isset( $_POST['quote_contact'] ) ? (int) $_POST['quote_contact'] : 0;
    $etapas     = array( 'prospeccion', 'propuesta_enviada', 'en_negociacion', 'ganada', 'perdida' );

    if ( '' === $nombre ) {
        wp_send_json_error( array( 'mensaje' => 'Escribe el nombre de la oportunidad.' ) );
    }
    if ( ! $empresa_id || 'talos_company' !== get_post_type( $empresa_id ) ) {
        wp_send_json_error( array( 'mensaje' => 'Elige la empresa.' ) );
    }
    if ( ! in_array( $etapa, $etapas, true ) ) {
        wp_send_json_error( array( 'mensaje' => 'Etapa no válida.' ) );
    }
    if ( $contacto && (int) get_post_meta( $contacto, 'contact_company', true ) !== $empresa_id ) {
        $contacto = 0; // el contacto era de otra empresa: se quita
    }

    $convertida = (bool) get_post_meta( $id, '_talos_opportunity_converted', true );
    $conceptos  = array();
    if ( ! $convertida ) {
        $crudos = isset( $_POST['conceptos'] ) ? json_decode( wp_unslash( $_POST['conceptos'] ), true ) : array();
        if ( ! is_array( $crudos ) ) {
            wp_send_json_error( array( 'mensaje' => 'Los conceptos llegaron mal; recarga la página.' ) );
        }
        $frecuencias = array( 'pago_unico', 'por_hora', 'semanal', 'quincenal', 'mensual', 'trimestral', 'semestral', 'anual' );
        foreach ( $crudos as $n => $c ) {
            $servicio    = (int) ( $c['servicio'] ?? 0 );
            $descripcion = sanitize_textarea_field( (string) ( $c['descripcion'] ?? '' ) );
            if ( $servicio && 'talos_service_cat' !== get_post_type( $servicio ) ) $servicio = 0;
            if ( ! $servicio && '' === trim( $descripcion ) ) {
                wp_send_json_error( array( 'mensaje' => 'El concepto ' . ( $n + 1 ) . ' no tiene servicio ni descripción.' ) );
            }
            $tipo_desc = (string) ( $c['tipo_descuento'] ?? '' );
            $con_desc  = in_array( $tipo_desc, array( 'porcentaje', 'monto_fijo' ), true ) && (float) ( $c['descuento'] ?? 0 ) > 0;
            $cantidad  = (float) ( $c['cantidad'] ?? 1 );
            $conceptos[] = array(
                'service_item'                => $servicio ?: '',
                'service_invoice_description' => $descripcion,
                'service_frequency'           => in_array( $c['frecuencia'] ?? '', $frecuencias, true ) ? $c['frecuencia'] : '',
                'service_quantity'            => $cantidad > 0 ? $cantidad : 1,
                'service_price'               => max( 0, (float) ( $c['precio'] ?? 0 ) ),
                'service_cost'                => max( 0, (float) ( $c['costo'] ?? 0 ) ),
                'service_applies_iva'         => ! empty( $c['iva'] ) ? 1 : 0,
                'item_has_discount'           => $con_desc ? 1 : 0,
                'item_discount_type'          => $con_desc ? $tipo_desc : '',
                'item_discount_value'         => $con_desc ? (float) $c['descuento'] : 0,
            );
        }
    }

    wp_update_post( array( 'ID' => $id, 'post_title' => $nombre ) );
    update_field( 'opportunity_name', $nombre, $id );
    update_field( 'opportunity_company', $empresa_id, $id );
    update_field( 'opportunity_stage', $etapa, $id );
    update_field( 'opportunity_lost_reason', isset( $_POST['opportunity_lost_reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['opportunity_lost_reason'] ) ) : '', $id );
    update_field( 'quote_contact', $contacto ?: '', $id );
    update_field( 'quote_created_date', talos_opp_fecha_ymd( 'quote_created_date' ), $id );
    update_field( 'quote_valid_until', talos_opp_fecha_ymd( 'quote_valid_until' ), $id );
    update_field( 'quote_sent', ! empty( $_POST['quote_sent'] ) ? 1 : 0, $id );
    update_field( 'quote_sent_date', ! empty( $_POST['quote_sent'] ) ? talos_opp_fecha_ymd( 'quote_sent_date' ) : '', $id );

    $tipo_general = isset( $_POST['quote_general_discount_type'] ) ? sanitize_text_field( wp_unslash( $_POST['quote_general_discount_type'] ) ) : '';
    $valor_general = isset( $_POST['quote_general_discount_value'] ) ? (float) $_POST['quote_general_discount_value'] : 0;
    $con_general = in_array( $tipo_general, array( 'porcentaje', 'monto_fijo' ), true ) && $valor_general > 0;
    update_field( 'quote_has_general_discount', $con_general ? 1 : 0, $id );
    update_field( 'quote_general_discount_type', $con_general ? $tipo_general : '', $id );
    update_field( 'quote_general_discount_value', $con_general ? $valor_general : '', $id );
    update_field( 'quote_notes', isset( $_POST['quote_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['quote_notes'] ) ) : '', $id );
    update_field( 'quote_terms', isset( $_POST['quote_terms'] ) ? sanitize_textarea_field( wp_unslash( $_POST['quote_terms'] ) ) : '', $id );

    if ( ! $convertida ) {
        update_field( 'quote_items', $conceptos, $id );
    }

    do_action( 'acf/save_post', $id ); // automatizaciones de motor-oportunidades.php

    wp_send_json_success( array(
        'convertida' => (bool) get_post_meta( $id, '_talos_opportunity_converted', true ),
    ) );
}
add_action( 'wp_ajax_talos_guardar_oportunidad', 'talos_ajax_guardar_oportunidad' );

/** Manda una Oportunidad a la papelera (recuperable desde wp-admin). */
function talos_ajax_eliminar_oportunidad() {
    talos_opp_verificar_nonce();

    $id = isset( $_POST['oportunidad_id'] ) ? (int) $_POST['oportunidad_id'] : 0;
    if ( ! $id || 'talos_opportunity' !== get_post_type( $id ) ) {
        wp_send_json_error( array( 'mensaje' => 'Oportunidad no válida.' ) );
    }
    if ( ! wp_trash_post( $id ) ) {
        wp_send_json_error( array( 'mensaje' => 'No se pudo eliminar la oportunidad.' ) );
    }
    wp_send_json_success();
}
add_action( 'wp_ajax_talos_eliminar_oportunidad', 'talos_ajax_eliminar_oportunidad' );
