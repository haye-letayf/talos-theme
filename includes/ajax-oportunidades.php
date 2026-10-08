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

    $empresa_id = isset( $_POST['empresa_id'] ) ? (int) $_POST['empresa_id'] : 0;
    $nombre     = isset( $_POST['nombre'] ) ? sanitize_text_field( wp_unslash( $_POST['nombre'] ) ) : '';
    $etapa      = isset( $_POST['etapa'] ) ? sanitize_text_field( wp_unslash( $_POST['etapa'] ) ) : 'prospeccion';
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
