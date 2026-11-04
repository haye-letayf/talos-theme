<?php
/**
 * AJAX de Ingresos: marcar pagado / enviar notas-facturas / editar celdas en línea.
 *
 * Correos: puerto directo de las plantillas legacy de Talos 1.0 (enviarNotaMasiva /
 * enviarComprobanteMasivo en talos-1.0-legacy-apps-script.gs) — mismo copy, mismos
 * datos bancarios, mismo WhatsApp. Dos cambios intencionales frente al original:
 * 1) el PDF/XML de la factura ya no se busca en una carpeta de Drive por nombre de
 *    cliente: es una Factura (talos_invoice, talos-core) de la Empresa en ese mes.
 *    Si un grupo es Factura y no tiene su CFDI, no se envía y se avisa.
 * 2) el "link de pago" (PayPal) del Sheet original no tiene campo ACF equivalente,
 *    así que siempre se muestra el bloque de datos bancarios.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Respaldo por si el tema llega antes que talos-core 2.19.2 (que define el logo).
if ( ! function_exists( 'talos_logo_correo_url' ) ) {
    function talos_logo_correo_url() {
        return 'https://talos.1124.com.mx/a/wp-content/uploads/2026/11/01-a-main-logo-colors-v3.png';
    }
}

function talos_ing_fmt( $n ) {
    return '$' . number_format( (float) $n, 0, '.', ',' ) . ' MXN';
}

function talos_ing_mes_str( $ymd ) {
    $meses = array( 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre' );
    $mes   = (int) substr( $ymd, 4, 2 );
    $anio  = substr( $ymd, 0, 4 );
    return array( $meses[ $mes - 1 ], $anio );
}

/**
 * Destinatarios de notas, facturas y comprobantes: los contactos con "Recibe
 * Facturas y Cotizaciones" palomeado (talos_destinatarios_facturacion de
 * talos-core, la misma regla de recordatorios y renovaciones).
 */
function talos_ing_destinatarios( $company_id ) {
    return function_exists( 'talos_destinatarios_facturacion' ) ? talos_destinatarios_facturacion( $company_id ) : null;
}

function talos_ing_email_nota_factura( $empresa_nombre, $contacto_nombre, $mes_str, $anio_str, $es_factura, $items, $subtotal, $iva, $total, $tiene_adjuntos = false, $facturado_a = '' ) {
    $subject_label = $es_factura ? 'Factura Oficial' : 'Nota de Venta';
    $disclaimer = $es_factura
        ? ( $tiene_adjuntos
            ? 'Esta es tu Factura Oficial (CFDI). Los archivos correspondientes (PDF y XML) se encuentran adjuntos a este correo.'
            : 'Esta es tu Factura Oficial (CFDI). En los próximos días te compartiremos el PDF y XML correspondientes por este mismo medio.' )
        : 'Este documento es una NOTA DE VENTA de uso interno y no constituye un comprobante fiscal.';

    $tabla = '<table style="width:100%;border-collapse:collapse;font-size:14px;"><thead><tr><th style="text-align:left;padding:10px;border-bottom:2px solid #022873;color:#022873;">Concepto</th><th style="text-align:right;padding:10px;border-bottom:2px solid #022873;color:#022873;">Monto</th></tr></thead><tbody>';
    foreach ( $items as $item ) {
        $tabla .= '<tr><td style="padding:10px;border-bottom:1px solid #e1e1e1;">' . esc_html( $item['desc'] ) . '</td><td style="padding:10px;border-bottom:1px solid #e1e1e1;text-align:right;">' . talos_ing_fmt( $item['monto'] ) . '</td></tr>';
    }
    $tabla .= '</tbody></table>';

    $datos_banco = $es_factura
        ? '<p style="margin:5px 0;"><strong>Banco:</strong> BANAMEX</p><p style="margin:5px 0;"><strong>Nombre:</strong> Jorge Halim Letayf Abraham</p><p style="margin:5px 0;"><strong>CLABE:</strong> 002180702066624743</p><p style="margin:5px 0;"><strong>Cuenta:</strong> 6662474</p><p style="margin:5px 0;"><strong>Sucursal:</strong> 7020</p><p style="margin:5px 0;"><strong>Tarjeta:</strong> 5206949671642097</p>'
        : '<p style="margin:5px 0;"><strong>Banco:</strong> HSBC</p><p style="margin:5px 0;"><strong>Nombre:</strong> Jorge Halim Letayf Abraham</p><p style="margin:5px 0;"><strong>CLABE:</strong> 021180065876724739</p><p style="margin:5px 0;"><strong>Cuenta:</strong> 6587672473</p><p style="margin:5px 0;"><strong>Tarjeta:</strong> 4213166182742696</p>';

    $bloque_pago = '<div style="background-color:#f4f6f9;border-left:5px solid #022873;padding:15px 20px;margin:30px 0;"><p style="margin-top:0;color:#022873;"><strong>Para Depósitos o Transferencia:</strong></p>' . $datos_banco . '<p style="margin:5px 0;background-color:#39BF81;color:white;display:inline-block;padding:2px 8px;border-radius:4px;"><strong>Concepto:</strong> ' . esc_html( $empresa_nombre ) . '</p></div>';

    return '<div style="font-family:Arial, sans-serif;color:#2c3e50;max-width:600px;margin:0 auto;padding:20px;border:1px solid #e1e1e1;border-radius:8px;">'
        . '<div style="text-align:center;margin-bottom:30px;"><img src="' . esc_url( talos_logo_correo_url() ) . '" alt="Once24" style="max-width:250px;"></div>'
        . '<p style="font-size:16px;">¡Hola, <strong>' . esc_html( $contacto_nombre ) . '</strong>!</p>'
        . '<p style="font-size:16px;">A continuación te compartimos el desglose correspondiente a los Servicios Digitales de <strong>' . esc_html( $mes_str ) . '</strong> del <strong>' . esc_html( $anio_str ) . '</strong>.</p>'
        . ( $es_factura && $facturado_a ? '<p style="font-size:14px;color:#7f8c8d;">Facturado a: <strong style="color:#2c3e50;">' . esc_html( $facturado_a ) . '</strong></p>' : '' )
        . $tabla
        . '<div style="text-align:right;margin-top:15px;">Subtotal: ' . talos_ing_fmt( $subtotal ) . '<br>IVA: ' . talos_ing_fmt( $iva ) . '<br><span style="font-size:32px;font-weight:bold;color:#39BF81;">' . talos_ing_fmt( $total ) . '</span></div>'
        . $bloque_pago
        . '<p style="font-size:11px;color:#7f8c8d;text-align:center;margin-top:25px;padding:15px;background:#f4f6f9;border-radius:4px;">⚠️ ' . $disclaimer . '</p>'
        . '<div style="border-top:1px solid #39BF81;padding-top:20px;margin-top:25px;"><h2 style="margin:0px;font-size:16px;color:#022873;">Jorge Letayf</h2><p style="margin:0px;color:#022873;font-size:13px;">Once24 Soluciones Digitales</p>'
        . '<div style="margin-top:20px;"><p style="margin-bottom:8px;font-size:14px;color:#7f8c8d;">¿Necesitas ayuda?</p><a href="https://wa.me/525620187875" style="background-color:#022873;color:white;padding:10px 20px;text-decoration:none;border-radius:4px;display:inline-block;">Escríbenos</a></div></div></div>';
}

function talos_ing_email_comprobante( $contacto_nombre, $mes_str, $anio_str, $items, $subtotal, $iva, $total, $fecha_pago_str ) {
    $tiene_iva = $iva > 0.1;

    $tabla = '<table style="width:100%;border-collapse:collapse;margin-bottom:5px;font-size:14px;"><thead><tr><th style="text-align:left;padding:10px;border-bottom:2px solid #022873;color:#022873;">Concepto Cubierto</th><th style="text-align:right;padding:10px;border-bottom:2px solid #022873;color:#022873;">Monto</th></tr></thead><tbody>';
    foreach ( $items as $item ) {
        $tabla .= '<tr><td style="padding:10px;border-bottom:1px solid #e1e1e1;color:#2c3e50;">' . esc_html( $item['desc'] ) . '</td><td style="padding:10px;border-bottom:1px solid #e1e1e1;text-align:right;color:#2c3e50;">' . talos_ing_fmt( $item['monto'] ) . '</td></tr>';
    }
    $tabla .= '</tbody></table>';

    $desglose = '<div style="text-align:right;margin-top:15px;font-size:14px;color:#2c3e50;">'
        . '<span style="display:inline-block;width:120px;color:#7f8c8d;">Subtotal:</span> <strong>' . talos_ing_fmt( $subtotal ) . '</strong><br>'
        . '<span style="display:inline-block;width:120px;color:#7f8c8d;">IVA (' . ( $tiene_iva ? '16' : '0' ) . '%):</span> <strong>' . ( $tiene_iva ? talos_ing_fmt( $iva ) : '$0 MXN' ) . '</strong></div>';

    return '<div style="font-family:Arial, sans-serif;color:#2c3e50;max-width:600px;margin:0 auto;padding:20px;border:1px solid #e1e1e1;border-radius:8px;">'
        . '<div style="text-align:center;margin-bottom:25px;"><img src="' . esc_url( talos_logo_correo_url() ) . '" alt="Once24" style="max-width:200px;"></div>'
        . '<div style="background-color:#eafaf1;border-left:5px solid #39BF81;padding:15px;margin-bottom:25px;"><h3 style="margin:0;color:#39BF81;text-align:center;">¡Pago Recibido Exitosamente! ✅</h3></div>'
        . '<p style="font-size:16px;">Hola <strong>' . esc_html( $contacto_nombre ) . '</strong>,</p>'
        . '<p style="font-size:16px;">Confirmamos de recibido tu pago correspondiente a los Servicios Digitales de <strong>' . esc_html( $mes_str ) . '</strong> del <strong>' . esc_html( $anio_str ) . '</strong>.</p>'
        . $tabla . $desglose
        . '<div style="text-align:right;margin:15px 0 25px 0;"><span style="font-size:14px;color:#022873;text-transform:uppercase;">Total Pagado</span><br><span style="font-size:32px;font-weight:bold;color:#39BF81;">' . talos_ing_fmt( $total ) . '</span></div>'
        . '<p style="font-size:15px;text-align:center;background-color:#f4f6f9;padding:10px;border-radius:4px;"><strong>Fecha de Pago:</strong> ' . esc_html( $fecha_pago_str ) . '</p>'
        . '<div style="border-top:1px solid #e1e1e1;margin-top:30px;padding-top:20px;text-align:center;"><p style="margin:0px;font-weight:600;color:#022873;font-size:16px;">Once24 | Soluciones Digitales</p></div></div>';
}

/**
 * Agrupa IDs de talos_income por llave (empresa+mes[+doc]) para enviar un solo correo
 * por grupo, igual que el Sheet original agrupaba por cliente+mes(+docType). El mes
 * es AAAAMM: dos cobros del mismo cliente en días distintos del mes van juntos.
 */
function talos_ing_agrupar( $income_ids, $incluir_doc_type ) {
    $grupos = array();
    foreach ( $income_ids as $income_id ) {
        $empresa = get_field( 'income_company', $income_id );
        if ( ! $empresa instanceof WP_Post ) continue;
        $mes = get_field( 'income_month', $income_id );
        $doc = get_field( 'income_doc_type', $income_id );
        $llave = $empresa->ID . '_' . substr( preg_replace( '/\D/', '', (string) $mes ), 0, 6 ) . ( $incluir_doc_type ? '_' . $doc : '' );
        if ( ! isset( $grupos[ $llave ] ) ) {
            $grupos[ $llave ] = array(
                'empresa_id' => $empresa->ID,
                'empresa'    => $empresa->post_title,
                'mes'        => $mes,
                'doc'        => $doc,
                'ids'        => array(),
                'items'      => array(),
                'subtotal'   => 0.0,
                'total'      => 0.0,
            );
        }
        $grupos[ $llave ]['ids'][]   = $income_id;
        $grupos[ $llave ]['items'][] = array(
            'desc'  => (string) get_field( 'income_description', $income_id ),
            'monto' => (float) get_field( 'income_subtotal', $income_id ),
        );
        $grupos[ $llave ]['subtotal'] += (float) get_field( 'income_subtotal', $income_id );
        $grupos[ $llave ]['total']    += (float) get_field( 'income_total', $income_id );
    }
    return $grupos;
}

function talos_ing_verificar_nonce() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'talos_ingresos' ) ) {
        wp_send_json_error( array( 'mensaje' => 'Sesión inválida, recarga la página.' ), 403 );
    }
}

/**
 * Marcar Pagado (individual o masivo) -> sella income_paid/income_payment_date y
 * envía el Comprobante de Pago agrupado por empresa+mes.
 */
function talos_ajax_marcar_pagado() {
    talos_ing_verificar_nonce();
    $ids = array_map( 'intval', (array) ( $_POST['ids'] ?? array() ) );
    $ids = array_filter( $ids );
    if ( empty( $ids ) ) {
        wp_send_json_error( array( 'mensaje' => 'No se recibieron ingresos a marcar.' ) );
    }

    $hoy = current_time( 'timestamp' );
    $fecha_ymd      = date( 'Ymd', $hoy );
    $fecha_pago_str = date( 'j', $hoy ) . ' de ' . array( 'Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre' )[ (int) date( 'n', $hoy ) - 1 ] . ' del ' . date( 'Y', $hoy );
    $fecha_chip     = date( 'j', $hoy ) . '/' . array( 'ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic' )[ (int) date( 'n', $hoy ) - 1 ] . '/' . date( 'Y', $hoy );

    // Se marcan todos; el comprobante sale por empresa, así que un Ingreso sin
    // Empresa (p. ej. el reembolso de la tarjeta adicional) queda pagado sin
    // correo. Antes talos_ing_agrupar() lo descartaba y no se marcaba nada.
    $actualizados = array();
    $sin_empresa  = array();
    foreach ( $ids as $income_id ) {
        if ( 'talos_income' !== get_post_type( $income_id ) ) continue;
        update_field( 'income_paid', 1, $income_id );
        update_field( 'income_payment_date', $fecha_ymd, $income_id );
        $actualizados[] = $income_id;
        if ( ! get_field( 'income_company', $income_id ) instanceof WP_Post ) {
            $sin_empresa[] = $income_id;
        }
    }

    $grupos = talos_ing_agrupar( $actualizados, false );
    $correos_enviados = 0;

    foreach ( $grupos as $g ) {
        $dest = talos_ing_destinatarios( $g['empresa_id'] );
        if ( ! $dest ) continue;
        list( $mes_str, $anio_str ) = talos_ing_mes_str( $g['mes'] );
        $iva = $g['total'] - $g['subtotal'];
        $html = talos_ing_email_comprobante( $dest['nombre'], $mes_str, $anio_str, $g['items'], $g['subtotal'], $iva, $g['total'], $fecha_pago_str );
        $headers = array( 'Content-Type: text/html; charset=UTF-8', 'Bcc: facturasonce24@gmail.com' );
        $ok = wp_mail( $dest['correos'], 'Comprobante de Pago Once24', $html, $headers );
        if ( $ok ) $correos_enviados++;
    }

    wp_send_json_success( array(
        'ids'            => $actualizados,
        'fecha_chip'     => $fecha_chip,
        'correos_enviados' => $correos_enviados,
        'sin_empresa'    => $sin_empresa,
    ) );
}
add_action( 'wp_ajax_talos_marcar_pagado', 'talos_ajax_marcar_pagado' );

/**
 * Enviar Notas/Facturas (individual o masivo) -> sella income_sent y envía la
 * Nota de Venta o Factura agrupada por empresa+mes+tipo de documento.
 */
function talos_ajax_enviar_notas() {
    talos_ing_verificar_nonce();
    $ids = array_map( 'intval', (array) ( $_POST['ids'] ?? array() ) );
    $ids = array_filter( $ids );
    if ( empty( $ids ) ) {
        wp_send_json_error( array( 'mensaje' => 'No se recibieron ingresos a enviar.' ) );
    }

    $grupos = talos_ing_agrupar( $ids, true );
    $correos_enviados = 0;
    $actualizados = array();
    $sin_contacto = array();
    $sin_factura  = array();

    foreach ( $grupos as $g ) {
        $es_factura = ( 'factura' === $g['doc'] );
        // Una Factura sin su CFDI (PDF + XML) no sale: se avisa y queda sin enviar.
        $adjuntos = array();
        if ( $es_factura ) {
            $adjuntos = function_exists( 'talos_facturas_adjuntos' ) ? talos_facturas_adjuntos( talos_facturas_de( $g['empresa_id'], $g['mes'] ) ) : array();
            if ( count( $adjuntos ) < 2 ) {
                $sin_factura[] = $g['empresa'];
                continue;
            }
        }
        $dest = talos_ing_destinatarios( $g['empresa_id'] );
        if ( ! $dest ) {
            $sin_contacto[] = $g['empresa'];
            continue;
        }
        list( $mes_str, $anio_str ) = talos_ing_mes_str( $g['mes'] );
        $iva = $g['total'] - $g['subtotal'];
        // El asunto ya no lleva el nombre comercial (las franquicias REMAX van en Talos
        // solo con su apellido); la razón social y el RFC van en el cuerpo de la factura.
        $facturado_a = trim( (string) get_field( 'company_legal_name', $g['empresa_id'] ) );
        $rfc_emp     = trim( (string) get_field( 'company_rfc', $g['empresa_id'] ) );
        if ( $facturado_a && $rfc_emp ) $facturado_a .= ' · RFC ' . $rfc_emp;
        $html = talos_ing_email_nota_factura( $g['empresa'], $dest['nombre'], $mes_str, $anio_str, $es_factura, $g['items'], $g['subtotal'], $iva, $g['total'], $es_factura, $facturado_a );
        $subject_label = $es_factura ? 'Factura Oficial' : 'Nota de Venta';
        $headers = array( 'Content-Type: text/html; charset=UTF-8', 'Bcc: facturasonce24@gmail.com' );
        $ok = wp_mail( $dest['correos'], $subject_label . ' Once24', $html, $headers, $adjuntos );
        if ( $ok ) {
            $correos_enviados++;
            foreach ( $g['ids'] as $income_id ) {
                update_field( 'income_sent', 1, $income_id );
                $actualizados[] = $income_id;
            }
        }
    }

    wp_send_json_success( array(
        'ids'              => $actualizados,
        'correos_enviados' => $correos_enviados,
        'sin_contacto'     => $sin_contacto,
        'sin_factura'      => $sin_factura,
    ) );
}
add_action( 'wp_ajax_talos_enviar_notas', 'talos_ajax_enviar_notas' );

/**
 * Nuevo Ingreso: cobros de una sola vez (desarrollos, setups, extras). Si es
 * el cobro de un Servicio Contratado de pago único, llega contrato_id. Subtotal/Total/IVA los calcula talos-core
 * (talos_normalizar_income_al_guardar) con el mismo acf/save_post de siempre.
 */
function talos_ajax_crear_income() {
    talos_ing_verificar_nonce();
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'mensaje' => 'No tienes permiso para crear ingresos.' ), 403 );
    }

    $empresa_id  = isset( $_POST['empresa_id'] ) ? (int) $_POST['empresa_id'] : 0;
    $servicio_id = isset( $_POST['servicio_id'] ) ? (int) $_POST['servicio_id'] : 0;
    $descripcion = isset( $_POST['descripcion'] ) ? sanitize_text_field( wp_unslash( $_POST['descripcion'] ) ) : '';
    $mes         = DateTime::createFromFormat( '!Y-m', isset( $_POST['mes'] ) ? sanitize_text_field( wp_unslash( $_POST['mes'] ) ) : '' );
    $monto       = isset( $_POST['monto'] ) ? round( (float) $_POST['monto'], 2 ) : 0;
    $doc         = ( isset( $_POST['doc'] ) && 'factura' === $_POST['doc'] ) ? 'factura' : 'nota_venta';

    if ( ! $empresa_id || 'talos_company' !== get_post_type( $empresa_id ) ) {
        wp_send_json_error( array( 'mensaje' => 'Selecciona una empresa ya registrada.' ) );
    }
    if ( $servicio_id && 'talos_service_cat' !== get_post_type( $servicio_id ) ) {
        $servicio_id = 0;
    }
    // Cobro de un servicio contratado de pago único de esta empresa: se liga
    // (_talos_contract_id) para que talos-core lo desactive al pagarse.
    $contrato_id = isset( $_POST['contrato_id'] ) ? (int) $_POST['contrato_id'] : 0;
    if ( $contrato_id && ( 'talos_contract' !== get_post_type( $contrato_id )
        || (int) get_post_meta( $contrato_id, 'service_company', true ) !== $empresa_id
        || ! function_exists( 'talos_contrato_es_pago_unico' ) || ! talos_contrato_es_pago_unico( $contrato_id ) ) ) {
        wp_send_json_error( array( 'mensaje' => 'Ese servicio no es de pago único de esta empresa.' ) );
    }
    if ( '' === $descripcion ) {
        wp_send_json_error( array( 'mensaje' => 'Escribe la descripción.' ) );
    }
    if ( ! $mes ) {
        wp_send_json_error( array( 'mensaje' => 'Elige el mes.' ) );
    }
    if ( $monto <= 0 ) {
        wp_send_json_error( array( 'mensaje' => 'Escribe un monto mayor a cero.' ) );
    }

    $nuevo_id = wp_insert_post( array(
        'post_title'  => sprintf( 'Income %s - Empresa #%d', $mes->format( 'Y-m-d' ), $empresa_id ),
        'post_type'   => 'talos_income',
        'post_status' => 'publish',
    ), true );
    if ( is_wp_error( $nuevo_id ) ) {
        wp_send_json_error( array( 'mensaje' => 'No se pudo crear el ingreso.' ) );
    }

    if ( $contrato_id ) {
        update_post_meta( $nuevo_id, '_talos_contract_id', $contrato_id );
    }
    update_field( 'income_company', $empresa_id, $nuevo_id );
    if ( $servicio_id ) {
        update_field( 'income_service', $servicio_id, $nuevo_id );
    }
    update_field( 'income_description', $descripcion, $nuevo_id );
    update_field( 'income_month', $mes->format( 'Ymd' ), $nuevo_id );
    update_field( 'income_quantity', 1, $nuevo_id );
    update_field( 'income_unit_price', $monto, $nuevo_id );
    update_field( 'income_doc_type', $doc, $nuevo_id );
    update_field( 'income_sent', false, $nuevo_id );
    update_field( 'income_paid', false, $nuevo_id );
    update_field( 'income_reminder_allowed', ! empty( $_POST['recordatorio'] ), $nuevo_id );
    do_action( 'acf/save_post', $nuevo_id );

    wp_send_json_success( array( 'id' => $nuevo_id, 'mes' => $mes->format( 'Y-m' ) ) );
}
add_action( 'wp_ajax_talos_crear_income', 'talos_ajax_crear_income' );

/**
 * Edición en línea de una celda de Ingresos (Servicio/Doc/Cant/P.Unit/Descripción).
 * Recalcula y guarda subtotal/total cuando cambia algo que los afecta.
 */
function talos_ajax_actualizar_campo_income() {
    talos_ing_verificar_nonce();
    $income_id = isset( $_POST['income_id'] ) ? (int) $_POST['income_id'] : 0;
    $campo     = isset( $_POST['campo'] ) ? sanitize_key( $_POST['campo'] ) : '';
    $valor     = isset( $_POST['valor'] ) ? wp_unslash( $_POST['valor'] ) : '';

    if ( ! $income_id || 'talos_income' !== get_post_type( $income_id ) ) {
        wp_send_json_error( array( 'mensaje' => 'Ingreso no válido.' ) );
    }

    // income_quantity se quitó de esta UI y de este editor en línea (2026-10), pero OJO:
    // motor-recurrencia.php y el importador de AMEX en talos-core todavía la escriben al
    // crear un Ingreso — no está muerta en todo el sistema, solo aquí. Ver feedback_talos_platform_quirks en memoria.
    $permitidos = array( 'income_service', 'income_doc_type', 'income_unit_price', 'income_description' );
    if ( ! in_array( $campo, $permitidos, true ) ) {
        wp_send_json_error( array( 'mensaje' => 'Campo no permitido.' ) );
    }

    if ( 'income_service' === $campo ) {
        update_field( $campo, (int) $valor, $income_id );
    } elseif ( 'income_doc_type' === $campo ) {
        update_field( $campo, sanitize_text_field( $valor ), $income_id );
    } elseif ( 'income_description' === $campo ) {
        update_field( $campo, sanitize_textarea_field( $valor ), $income_id );
    } else {
        update_field( $campo, (float) $valor, $income_id );
    }

    // Recalcular subtotal/total (y default de income_month si estuviera vacío) vía el
    // mismo hook que corre para cualquier guardado de Ingreso — ver motor-recurrencia.php
    // en talos-core. update_field() no dispara acf/save_post por sí solo.
    do_action( 'acf/save_post', $income_id );
    $subtotal = (float) get_field( 'income_subtotal', $income_id );
    $total    = (float) get_field( 'income_total', $income_id );

    wp_send_json_success( array(
        'subtotal' => talos_fmt_mxn( $subtotal ),
        'total'    => talos_fmt_mxn( $total ),
    ) );
}
add_action( 'wp_ajax_talos_actualizar_campo_income', 'talos_ajax_actualizar_campo_income' );


/**
 * Elimina (mueve a la papelera de WordPress, NO borrado permanente) uno o
 * varios Ingresos. Trash en vez de delete definitivo a propósito: son
 * registros financieros, un error aquí debe ser recuperable desde wp-admin.
 */
function talos_ajax_eliminar_income() {
    talos_ing_verificar_nonce();
    $ids = array_map( 'intval', (array) ( $_POST['ids'] ?? array() ) );
    $ids = array_filter( $ids );
    if ( empty( $ids ) ) {
        wp_send_json_error( array( 'mensaje' => 'No se recibieron ingresos a eliminar.' ) );
    }

    $eliminados = array();
    foreach ( $ids as $id ) {
        if ( 'talos_income' !== get_post_type( $id ) ) continue;
        if ( wp_trash_post( $id ) ) {
            $eliminados[] = $id;
        }
    }

    wp_send_json_success( array( 'ids' => $eliminados ) );
}
add_action( 'wp_ajax_talos_eliminar_income', 'talos_ajax_eliminar_income' );

/**
 * Ficha de Ingreso: guarda el registro completo. Pagado y Enviado no van aquí:
 * se registran con sus botones (recibo y nota salen por correo) y se quitan con
 * talos_estado_income. La fecha de pago sí se corrige aquí, si ya está pagado.
 * Subtotal y Total los recalcula talos-core en acf/save_post.
 */
function talos_ajax_guardar_income() {
    talos_ing_verificar_nonce();
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'mensaje' => 'No tienes permiso para editar ingresos.' ), 403 );
    }
    $id = isset( $_POST['income_id'] ) ? (int) $_POST['income_id'] : 0;
    if ( ! $id || 'talos_income' !== get_post_type( $id ) ) {
        wp_send_json_error( array( 'mensaje' => 'Ingreso no válido.' ) );
    }

    $empresa_id  = isset( $_POST['income_company'] ) ? (int) $_POST['income_company'] : 0;
    $servicio_id = isset( $_POST['income_service'] ) ? (int) $_POST['income_service'] : 0;
    $descripcion = isset( $_POST['income_description'] ) ? sanitize_text_field( wp_unslash( $_POST['income_description'] ) ) : '';
    // Fecha completa, no solo el mes: Generar Mes evita duplicados por contrato + fecha exacta.
    $fecha       = DateTime::createFromFormat( '!Y-m-d', isset( $_POST['income_month'] ) ? sanitize_text_field( wp_unslash( $_POST['income_month'] ) ) : '' );
    $monto_txt   = isset( $_POST['income_unit_price'] ) ? str_replace( array( '$', ',', ' ' ), '', wp_unslash( (string) $_POST['income_unit_price'] ) ) : '';
    $doc         = ( isset( $_POST['income_doc_type'] ) && 'factura' === $_POST['income_doc_type'] ) ? 'factura' : 'nota_venta';

    if ( $empresa_id && 'talos_company' !== get_post_type( $empresa_id ) ) {
        wp_send_json_error( array( 'mensaje' => 'Selecciona una empresa ya registrada.' ) );
    }
    if ( $servicio_id && 'talos_service_cat' !== get_post_type( $servicio_id ) ) {
        $servicio_id = 0;
    }
    if ( '' === $descripcion ) {
        wp_send_json_error( array( 'mensaje' => 'Escribe la descripción.' ) );
    }
    if ( ! $fecha ) {
        wp_send_json_error( array( 'mensaje' => 'Elige la fecha de cobro.' ) );
    }
    if ( '' === $monto_txt || ! is_numeric( $monto_txt ) ) {
        wp_send_json_error( array( 'mensaje' => 'Escribe un monto válido (solo números).' ) );
    }

    update_field( 'income_company', $empresa_id ? $empresa_id : '', $id );
    update_field( 'income_service', $servicio_id ? $servicio_id : '', $id );
    update_field( 'income_description', $descripcion, $id );
    update_field( 'income_month', $fecha->format( 'Ymd' ), $id );
    update_field( 'income_unit_price', round( (float) $monto_txt, 2 ), $id );
    update_field( 'income_doc_type', $doc, $id );
    update_field( 'income_reminder_allowed', ! empty( $_POST['income_reminder_allowed'] ) ? 1 : 0, $id );

    if ( get_field( 'income_paid', $id ) && ! empty( $_POST['income_payment_date'] ) ) {
        $pago = DateTime::createFromFormat( '!Y-m-d', sanitize_text_field( wp_unslash( $_POST['income_payment_date'] ) ) );
        if ( $pago ) {
            update_field( 'income_payment_date', $pago->format( 'Ymd' ), $id );
        }
    }

    do_action( 'acf/save_post', $id );
    wp_send_json_success();
}
add_action( 'wp_ajax_talos_guardar_income', 'talos_ajax_guardar_income' );

/**
 * Ficha de Ingreso: quitar Enviado o Pagado (corregir un renglón equivocado).
 * No manda ningún correo. Quitar Pagado borra también la fecha de pago.
 */
function talos_ajax_estado_income() {
    talos_ing_verificar_nonce();
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'mensaje' => 'No tienes permiso para editar ingresos.' ), 403 );
    }
    $id    = isset( $_POST['income_id'] ) ? (int) $_POST['income_id'] : 0;
    $campo = isset( $_POST['campo'] ) ? sanitize_key( $_POST['campo'] ) : '';
    if ( ! $id || 'talos_income' !== get_post_type( $id ) ) {
        wp_send_json_error( array( 'mensaje' => 'Ingreso no válido.' ) );
    }
    if ( 'enviado' === $campo ) {
        update_field( 'income_sent', 0, $id );
    } elseif ( 'pagado' === $campo ) {
        update_field( 'income_paid', 0, $id );
        update_field( 'income_payment_date', '', $id );
    } else {
        wp_send_json_error( array( 'mensaje' => 'Campo no permitido.' ) );
    }
    wp_send_json_success();
}
add_action( 'wp_ajax_talos_estado_income', 'talos_ajax_estado_income' );
