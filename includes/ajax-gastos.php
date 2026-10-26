<?php
/**
 * AJAX de Gastos: marcar pagado, editar monto. A diferencia de Ingresos, no hay envío de
 * correo aquí (un gasto no le genera ningún comunicado a un tercero).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function talos_ajax_marcar_pagado_gasto() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'talos_gastos' ) ) {
        wp_send_json_error( array( 'mensaje' => 'Sesión inválida, recarga la página.' ), 403 );
    }
    $expense_id = isset( $_POST['expense_id'] ) ? (int) $_POST['expense_id'] : 0;
    if ( ! $expense_id || 'talos_expense' !== get_post_type( $expense_id ) ) {
        wp_send_json_error( array( 'mensaje' => 'Gasto no válido.' ) );
    }

    $hoy = current_time( 'timestamp' );
    $fecha_ymd  = date( 'Y-m-d', $hoy );
    $fecha_chip = date( 'j', $hoy ) . '/' . array( 'ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic' )[ (int) date( 'n', $hoy ) - 1 ] . '/' . date( 'Y', $hoy );

    update_field( 'expense_paid', 1, $expense_id );
    update_field( 'expense_payment_date', $fecha_ymd, $expense_id );

    wp_send_json_success( array( 'fecha_chip' => $fecha_chip ) );
}
add_action( 'wp_ajax_talos_marcar_pagado_gasto', 'talos_ajax_marcar_pagado_gasto' );

/**
 * Registrar Pago Masivo (varios gastos seleccionados a la vez). Misma acción
 * que el botón individual, solo que para varios IDs — un gasto no le genera
 * ningún correo a nadie, así que no hay nada que agrupar/enviar como en Ingresos.
 */
function talos_ajax_marcar_pagado_gasto_masivo() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'talos_gastos' ) ) {
        wp_send_json_error( array( 'mensaje' => 'Sesión inválida, recarga la página.' ), 403 );
    }
    $ids = array_map( 'intval', (array) ( $_POST['ids'] ?? array() ) );
    $ids = array_filter( $ids );
    if ( empty( $ids ) ) {
        wp_send_json_error( array( 'mensaje' => 'No se recibieron gastos a marcar.' ) );
    }

    $hoy = current_time( 'timestamp' );
    $fecha_ymd  = date( 'Y-m-d', $hoy );
    $fecha_chip = date( 'j', $hoy ) . '/' . array( 'ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic' )[ (int) date( 'n', $hoy ) - 1 ] . '/' . date( 'Y', $hoy );

    $actualizados = array();
    foreach ( $ids as $id ) {
        if ( 'talos_expense' !== get_post_type( $id ) ) continue;
        update_field( 'expense_paid', 1, $id );
        update_field( 'expense_payment_date', $fecha_ymd, $id );
        $actualizados[] = $id;
    }

    wp_send_json_success( array( 'ids' => $actualizados, 'fecha_chip' => $fecha_chip ) );
}
add_action( 'wp_ajax_talos_marcar_pagado_gasto_masivo', 'talos_ajax_marcar_pagado_gasto_masivo' );

/**
 * Editar el Monto de un gasto desde la tabla (p. ej. el pago a AMEX Plat, que
 * la plantilla genera en $14,000 pero varía cada mes). Si el gasto traía
 * Precio unitario se vacía: el total capturado a mano manda, y si no, el
 * cálculo Cantidad × Precio de talos-core lo pisaría en el siguiente guardado.
 */
function talos_ajax_editar_monto_gasto() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'mensaje' => 'No tienes permiso para editar gastos.' ), 403 );
    }
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'talos_gastos' ) ) {
        wp_send_json_error( array( 'mensaje' => 'Sesión inválida, recarga la página.' ), 403 );
    }
    $expense_id = isset( $_POST['expense_id'] ) ? (int) $_POST['expense_id'] : 0;
    if ( ! $expense_id || 'talos_expense' !== get_post_type( $expense_id ) ) {
        wp_send_json_error( array( 'mensaje' => 'Gasto no válido.' ) );
    }
    $texto = str_replace( array( '$', ',', ' ' ), '', wp_unslash( (string) ( $_POST['monto'] ?? '' ) ) );
    if ( '' === $texto || ! is_numeric( $texto ) ) {
        wp_send_json_error( array( 'mensaje' => 'Escribe un monto válido (solo números).' ) );
    }
    $monto = round( (float) $texto, 2 );

    $anterior = (float) get_field( 'expense_amount', $expense_id );
    $precio   = get_field( 'expense_unit_price', $expense_id, false );
    if ( '' !== (string) $precio && null !== $precio ) {
        update_field( 'expense_unit_price', '', $expense_id );
    }
    update_field( 'expense_amount', $monto, $expense_id );
    do_action( 'acf/save_post', $expense_id );

    $guardado = (float) get_field( 'expense_amount', $expense_id );
    wp_send_json_success( array(
        'monto'    => $guardado,
        'anterior' => $anterior,
        'texto'    => talos_fmt_mxn( $guardado ),
    ) );
}
add_action( 'wp_ajax_talos_editar_monto_gasto', 'talos_ajax_editar_monto_gasto' );

/**
 * Elimina (mueve a la papelera de WordPress, NO borrado permanente) uno o
 * varios Gastos. Igual criterio que Ingresos: recuperable desde wp-admin.
 */
function talos_ajax_eliminar_gasto() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'talos_gastos' ) ) {
        wp_send_json_error( array( 'mensaje' => 'Sesión inválida, recarga la página.' ), 403 );
    }
    $ids = array_map( 'intval', (array) ( $_POST['ids'] ?? array() ) );
    $ids = array_filter( $ids );
    if ( empty( $ids ) ) {
        wp_send_json_error( array( 'mensaje' => 'No se recibieron gastos a eliminar.' ) );
    }

    $eliminados = array();
    foreach ( $ids as $id ) {
        if ( 'talos_expense' !== get_post_type( $id ) ) continue;
        if ( wp_trash_post( $id ) ) {
            $eliminados[] = $id;
        }
    }

    wp_send_json_success( array( 'ids' => $eliminados ) );
}
add_action( 'wp_ajax_talos_eliminar_gasto', 'talos_ajax_eliminar_gasto' );

/**
 * Importar AMEX desde el modal de Gastos — reutiliza talos_procesar_csv_amex()
 * de talos-core (el mismo motor que ya usa la página de wp-admin), solo cambia
 * cómo llega el archivo y cómo se reporta el resultado (JSON en vez de HTML).
 * Idempotente: re-subir el mismo CSV solo cuenta duplicados, no crea registros de más.
 */
function talos_ajax_importar_amex() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'mensaje' => 'No tienes permiso para importar AMEX.' ), 403 );
    }
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'talos_importar_amex_modal' ) ) {
        wp_send_json_error( array( 'mensaje' => 'Sesión inválida, recarga la página.' ), 403 );
    }
    if ( empty( $_FILES['archivo']['tmp_name'] ) ) {
        wp_send_json_error( array( 'mensaje' => 'No se recibió ningún archivo.' ) );
    }
    if ( 'csv' !== strtolower( pathinfo( $_FILES['archivo']['name'], PATHINFO_EXTENSION ) ) ) {
        wp_send_json_error( array( 'mensaje' => 'El archivo debe ser .csv' ) );
    }
    if ( ! function_exists( 'talos_procesar_csv_amex' ) ) {
        wp_send_json_error( array( 'mensaje' => 'El plugin talos-core no está activo (falta el motor de importación AMEX).' ) );
    }

    $resultado = talos_procesar_csv_amex( $_FILES['archivo']['tmp_name'] );
    if ( isset( $resultado['error'] ) ) {
        wp_send_json_error( array( 'mensaje' => $resultado['error'] ) );
    }
    wp_send_json_success( $resultado );
}
add_action( 'wp_ajax_talos_importar_amex', 'talos_ajax_importar_amex' );
