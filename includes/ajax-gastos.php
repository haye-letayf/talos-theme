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
 * que el botón individual, solo que para varios IDs. Excepción: las quincenas
 * de nómina se pagan con talos-core (talos_pagar_quincena_nomina), que suma el
 * IVA si se pidió y le manda su recibo al colaborador.
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

    $con_iva = ! empty( $_POST['nomina_iva'] );
    $actualizados = array();
    $montos = array();
    $recibos = 0;
    $sin_correo = array();
    foreach ( $ids as $id ) {
        if ( 'talos_expense' !== get_post_type( $id ) ) continue;
        if ( function_exists( 'talos_nomina_es_quincena' ) && talos_nomina_es_quincena( $id ) && ! get_field( 'expense_paid', $id ) ) {
            $pago = talos_pagar_quincena_nomina( $id, $con_iva );
            $montos[ $id ] = talos_fmt_mxn( $pago['monto'] );
            if ( true === $pago['recibo'] ) {
                $recibos++;
            } else {
                $sin_correo[] = get_field( 'expense_supplier', $id );
            }
            $actualizados[] = $id;
            continue;
        }
        update_field( 'expense_paid', 1, $id );
        update_field( 'expense_payment_date', $fecha_ymd, $id );
        $actualizados[] = $id;
    }

    wp_send_json_success( array(
        'ids'        => $actualizados,
        'fecha_chip' => $fecha_chip,
        'montos'     => $montos,
        'recibos'    => $recibos,
        'sin_correo' => array_values( array_unique( $sin_correo ) ),
    ) );
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
 * Nuevo Gasto eventual (los fijos salen de Plantillas y los de tarjeta del
 * importador AMEX). Si ya está pagado, la fecha de pago es la del gasto.
 */
function talos_ajax_crear_gasto() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'mensaje' => 'No tienes permiso para crear gastos.' ), 403 );
    }
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'talos_gastos' ) ) {
        wp_send_json_error( array( 'mensaje' => 'Sesión inválida, recarga la página.' ), 403 );
    }
    $categoria    = ( isset( $_POST['categoria'] ) && 'personal' === $_POST['categoria'] ) ? 'personal' : 'operacion';
    $subcategoria = isset( $_POST['subcategoria'] ) ? sanitize_key( $_POST['subcategoria'] ) : '';
    $proveedor    = isset( $_POST['proveedor'] ) ? sanitize_text_field( wp_unslash( $_POST['proveedor'] ) ) : '';
    $descripcion  = isset( $_POST['descripcion'] ) ? sanitize_text_field( wp_unslash( $_POST['descripcion'] ) ) : '';
    $fecha        = DateTime::createFromFormat( '!Y-m-d', isset( $_POST['fecha'] ) ? sanitize_text_field( wp_unslash( $_POST['fecha'] ) ) : '' );
    $monto        = isset( $_POST['monto'] ) ? round( (float) $_POST['monto'], 2 ) : 0;
    $metodo       = isset( $_POST['metodo'] ) ? sanitize_key( $_POST['metodo'] ) : '';
    $pagado       = ! empty( $_POST['pagado'] );

    $campo_subcat = function_exists( 'acf_get_field' ) ? acf_get_field( 'expense_subcategory' ) : null;
    if ( '' === $subcategoria || ( ! empty( $campo_subcat['choices'] ) && ! isset( $campo_subcat['choices'][ $subcategoria ] ) ) ) {
        wp_send_json_error( array( 'mensaje' => 'Elige una subcategoría válida.' ) );
    }
    if ( '' === $proveedor ) {
        wp_send_json_error( array( 'mensaje' => 'Escribe el proveedor.' ) );
    }
    if ( ! $fecha ) {
        wp_send_json_error( array( 'mensaje' => 'Elige la fecha.' ) );
    }
    if ( 0.0 === $monto ) {
        wp_send_json_error( array( 'mensaje' => 'Escribe el monto.' ) );
    }

    $nuevo_id = wp_insert_post( array(
        'post_title'  => $proveedor . ' - ' . $fecha->format( 'Y-m-d' ),
        'post_type'   => 'talos_expense',
        'post_status' => 'publish',
    ), true );
    if ( is_wp_error( $nuevo_id ) ) {
        wp_send_json_error( array( 'mensaje' => 'No se pudo crear el gasto.' ) );
    }

    update_field( 'expense_type', 'transaccion', $nuevo_id );
    update_field( 'expense_category', $categoria, $nuevo_id );
    update_field( 'expense_subcategory', $subcategoria, $nuevo_id );
    update_field( 'expense_supplier', $proveedor, $nuevo_id );
    update_field( 'expense_description', $descripcion, $nuevo_id );
    update_field( 'expense_amount', $monto, $nuevo_id );
    update_field( 'expense_frequency', 'pago_unico', $nuevo_id );
    update_field( 'expense_payment_method', $metodo, $nuevo_id );
    update_field( 'expense_period', $fecha->format( 'Ymd' ), $nuevo_id );
    update_field( 'expense_status', true, $nuevo_id );
    update_field( 'expense_paid', $pagado ? 1 : 0, $nuevo_id );
    if ( $pagado ) {
        update_field( 'expense_payment_date', $fecha->format( 'Ymd' ), $nuevo_id );
    }
    do_action( 'acf/save_post', $nuevo_id );

    wp_send_json_success( array( 'id' => $nuevo_id, 'mes' => $fecha->format( 'Y-m' ) ) );
}
add_action( 'wp_ajax_talos_crear_gasto', 'talos_ajax_crear_gasto' );

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

function talos_gas_verificar_ficha() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'mensaje' => 'No tienes permiso para editar gastos.' ), 403 );
    }
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'talos_gastos' ) ) {
        wp_send_json_error( array( 'mensaje' => 'Sesión inválida, recarga la página.' ), 403 );
    }
    $id = isset( $_POST['expense_id'] ) ? (int) $_POST['expense_id'] : 0;
    if ( ! $id || 'talos_expense' !== get_post_type( $id ) ) {
        wp_send_json_error( array( 'mensaje' => 'Gasto no válido.' ) );
    }
    return $id;
}

/**
 * Ficha de Gasto: guarda el registro completo. Con Precio unitario, el Monto
 * lo calcula talos-core (Cantidad × Precio); sin él, manda el Monto capturado.
 * Pagado no va aquí: "Registrar pago" usa el endpoint de la tabla (nómina
 * incluida, con su recibo) y talos_estado_gasto lo quita. La fecha de pago sí
 * se corrige aquí, si ya está pagado.
 */
function talos_ajax_guardar_gasto() {
    $id = talos_gas_verificar_ficha();

    $campo_cat    = function_exists( 'acf_get_field' ) ? acf_get_field( 'expense_category' ) : null;
    $campo_subcat = function_exists( 'acf_get_field' ) ? acf_get_field( 'expense_subcategory' ) : null;
    $campo_metodo = function_exists( 'acf_get_field' ) ? acf_get_field( 'expense_payment_method' ) : null;

    $categoria    = isset( $_POST['expense_category'] ) ? sanitize_key( $_POST['expense_category'] ) : '';
    $subcategoria = isset( $_POST['expense_subcategory'] ) ? sanitize_key( $_POST['expense_subcategory'] ) : '';
    $metodo       = isset( $_POST['expense_payment_method'] ) ? sanitize_key( $_POST['expense_payment_method'] ) : '';
    $proveedor    = isset( $_POST['expense_supplier'] ) ? sanitize_text_field( wp_unslash( $_POST['expense_supplier'] ) ) : '';
    $descripcion  = isset( $_POST['expense_description'] ) ? sanitize_text_field( wp_unslash( $_POST['expense_description'] ) ) : '';
    $miembro      = isset( $_POST['expense_team_member'] ) ? (int) $_POST['expense_team_member'] : 0;
    $fecha        = DateTime::createFromFormat( '!Y-m-d', isset( $_POST['expense_period'] ) ? sanitize_text_field( wp_unslash( $_POST['expense_period'] ) ) : '' );
    $limpiar      = function( $k ) { return isset( $_POST[ $k ] ) ? str_replace( array( '$', ',', ' ' ), '', wp_unslash( (string) $_POST[ $k ] ) ) : ''; };
    $cantidad     = $limpiar( 'expense_quantity' );
    $precio       = $limpiar( 'expense_unit_price' );
    $monto        = $limpiar( 'expense_amount' );

    if ( ! empty( $campo_cat['choices'] ) && ! isset( $campo_cat['choices'][ $categoria ] ) ) {
        wp_send_json_error( array( 'mensaje' => 'Elige una categoría válida.' ) );
    }
    if ( '' === $subcategoria || ( ! empty( $campo_subcat['choices'] ) && ! isset( $campo_subcat['choices'][ $subcategoria ] ) ) ) {
        wp_send_json_error( array( 'mensaje' => 'Elige una subcategoría válida.' ) );
    }
    if ( '' !== $metodo && ! empty( $campo_metodo['choices'] ) && ! isset( $campo_metodo['choices'][ $metodo ] ) ) {
        wp_send_json_error( array( 'mensaje' => 'Elige un método de pago válido.' ) );
    }
    if ( '' === $proveedor ) {
        wp_send_json_error( array( 'mensaje' => 'Escribe el proveedor.' ) );
    }
    if ( ! $fecha ) {
        wp_send_json_error( array( 'mensaje' => 'Elige la fecha.' ) );
    }
    if ( $miembro && 'talos_team' !== get_post_type( $miembro ) ) {
        $miembro = 0;
    }
    if ( '' !== $precio ) {
        if ( ! is_numeric( $precio ) || ( '' !== $cantidad && ! is_numeric( $cantidad ) ) ) {
            wp_send_json_error( array( 'mensaje' => 'Cantidad y precio unitario deben ser números.' ) );
        }
    } elseif ( '' === $monto || ! is_numeric( $monto ) ) {
        wp_send_json_error( array( 'mensaje' => 'Escribe el monto (o un precio unitario).' ) );
    }

    update_field( 'expense_category', $categoria, $id );
    update_field( 'expense_subcategory', $subcategoria, $id );
    update_field( 'expense_supplier', $proveedor, $id );
    update_field( 'expense_description', $descripcion, $id );
    update_field( 'expense_team_member', $miembro ? $miembro : '', $id );
    update_field( 'expense_period', $fecha->format( 'Ymd' ), $id );
    update_field( 'expense_payment_method', $metodo, $id );
    if ( '' !== $precio ) {
        update_field( 'expense_quantity', '' === $cantidad ? 1 : round( (float) $cantidad, 4 ), $id );
        update_field( 'expense_unit_price', round( (float) $precio, 2 ), $id );
    } else {
        // Monto directo: se vacía el precio para que talos-core no lo recalcule.
        update_field( 'expense_unit_price', '', $id );
        update_field( 'expense_amount', round( (float) $monto, 2 ), $id );
    }

    if ( get_field( 'expense_paid', $id ) && ! empty( $_POST['expense_payment_date'] ) ) {
        $pago = DateTime::createFromFormat( '!Y-m-d', sanitize_text_field( wp_unslash( $_POST['expense_payment_date'] ) ) );
        if ( $pago ) {
            update_field( 'expense_payment_date', $pago->format( 'Ymd' ), $id );
        }
    }

    do_action( 'acf/save_post', $id );
    wp_send_json_success();
}
add_action( 'wp_ajax_talos_guardar_gasto', 'talos_ajax_guardar_gasto' );

/**
 * Ficha de Gasto: quitar Pagado (corregir un renglón equivocado). Borra la
 * fecha de pago. En nómina, el IVA ya sumado y el recibo enviado se quedan.
 */
function talos_ajax_estado_gasto() {
    $id = talos_gas_verificar_ficha();
    update_field( 'expense_paid', 0, $id );
    update_field( 'expense_payment_date', '', $id );
    wp_send_json_success();
}
add_action( 'wp_ajax_talos_estado_gasto', 'talos_ajax_estado_gasto' );
