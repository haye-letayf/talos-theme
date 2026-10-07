<?php
/**
 * AJAX de Gastos: marcar pagado. A diferencia de Ingresos, no hay envío de
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
