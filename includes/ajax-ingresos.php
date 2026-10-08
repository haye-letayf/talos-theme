<?php
/**
 * AJAX de Ingresos: marcar pagado / enviar notas-facturas / editar celdas en línea.
 *
 * Correos: puerto directo de las plantillas legacy de Talos 1.0 (enviarNotaMasiva /
 * enviarComprobanteMasivo en talos-1.0-legacy-apps-script.gs) — mismo copy, mismos
 * datos bancarios, mismo WhatsApp. Dos cambios intencionales frente al original:
 * 1) el PDF/XML de la factura ya no se busca en una carpeta de Drive por nombre de
 *    cliente — se sube directo a cada Ingreso (income_invoice_pdf/income_invoice_xml)
 *    y se adjunta de verdad solo si ya están ambos; si falta alguno, el disclaimer
 *    avisa que se compartirán después en vez de prometer un adjunto que no va.
 * 2) el "link de pago" (PayPal) del Sheet original no tiene campo ACF equivalente,
 *    así que siempre se muestra el bloque de datos bancarios.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

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
 * Primer contacto ligado a una Empresa (contact_company == $company_id).
 * Devuelve null si la empresa no tiene ningún contacto registrado.
 */
function talos_ing_contacto_de_empresa( $company_id ) {
    $contactos = get_posts( array(
        'post_type'      => 'talos_contact',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'meta_query'     => array( array( 'key' => 'contact_company', 'value' => $company_id ) ),
    ) );
    return $contactos ? $contactos[0] : null;
}

function talos_ing_destinatarios( $company_id ) {
    $contacto = talos_ing_contacto_de_empresa( $company_id );
    if ( ! $contacto ) {
        return null;
    }
    $correos = array();
    $principal  = get_field( 'contact_company_email', $contacto->ID );
    $secundario = get_field( 'contact_personal_email', $contacto->ID );
    if ( $principal ) $correos[] = $principal;
    if ( $secundario ) $correos[] = $secundario;
    if ( empty( $correos ) ) {
        return null;
    }
    $apellido = get_field( 'contact_lastname', $contacto->ID );
    return array(
        'correos' => $correos,
        'nombre'  => trim( get_the_title( $contacto->ID ) . ' ' . $apellido ),
    );
}

/**
 * Convierte una URL dentro de /wp-content/uploads/ a su ruta local en disco,
 * para poder pasarla a wp_mail()'s attachments (que pide rutas, no URLs).
 */
function talos_ing_url_a_path( $url ) {
    if ( empty( $url ) ) return null;
    $uploads = wp_upload_dir();
    if ( 0 === strpos( $url, $uploads['baseurl'] ) ) {
        $path = $uploads['basedir'] . substr( $url, strlen( $uploads['baseurl'] ) );
        return file_exists( $path ) ? $path : null;
    }
    return null;
}

function talos_ing_email_nota_factura( $empresa_nombre, $contacto_nombre, $mes_str, $anio_str, $es_factura, $items, $subtotal, $iva, $total, $tiene_adjuntos = false ) {
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
        . '<div style="text-align:center;margin-bottom:30px;"><img src="https://1124.com.mx/main/wp-content/uploads/2025/08/once24-main-logo-horizontal-bicolor.png" alt="Once24" style="max-width:250px;"></div>'
        . '<p style="font-size:16px;">¡Hola, <strong>' . esc_html( $contacto_nombre ) . '</strong>!</p>'
        . '<p style="font-size:16px;">A continuación te compartimos el desglose correspondiente a los Servicios Digitales de <strong>' . esc_html( $mes_str ) . '</strong> del <strong>' . esc_html( $anio_str ) . '</strong>.</p>'
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
        . '<div style="text-align:center;margin-bottom:25px;"><img src="https://1124.com.mx/main/wp-content/uploads/2025/08/once24-main-logo-horizontal-bicolor.png" alt="Once24" style="max-width:200px;"></div>'
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
 * por grupo, igual que el Sheet original agrupaba por cliente+mes(+docType).
 */
function talos_ing_agrupar( $income_ids, $incluir_doc_type ) {
    $grupos = array();
    foreach ( $income_ids as $income_id ) {
        $empresa = get_field( 'income_company', $income_id );
        if ( ! $empresa instanceof WP_Post ) continue;
        $mes = get_field( 'income_month', $income_id );
        $doc = get_field( 'income_doc_type', $income_id );
        $llave = $empresa->ID . '_' . $mes . ( $incluir_doc_type ? '_' . $doc : '' );
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
                'adjuntos'   => array(),
            );
        }
        $grupos[ $llave ]['ids'][]   = $income_id;
        $grupos[ $llave ]['items'][] = array(
            'desc'  => (string) get_field( 'income_description', $income_id ),
            'monto' => (float) get_field( 'income_subtotal', $income_id ),
        );
        $grupos[ $llave ]['subtotal'] += (float) get_field( 'income_subtotal', $income_id );
        $grupos[ $llave ]['total']    += (float) get_field( 'income_total', $income_id );
        $pdf_path = talos_ing_url_a_path( get_field( 'income_invoice_pdf', $income_id ) );
        $xml_path = talos_ing_url_a_path( get_field( 'income_invoice_xml', $income_id ) );
        if ( $pdf_path ) $grupos[ $llave ]['adjuntos'][] = $pdf_path;
        if ( $xml_path ) $grupos[ $llave ]['adjuntos'][] = $xml_path;
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

    $grupos = talos_ing_agrupar( $ids, false );
    $correos_enviados = 0;
    $actualizados = array();

    foreach ( $grupos as $g ) {
        foreach ( $g['ids'] as $income_id ) {
            update_field( 'income_paid', 1, $income_id );
            update_field( 'income_payment_date', $fecha_ymd, $income_id );
            $actualizados[] = $income_id;
        }

        $dest = talos_ing_destinatarios( $g['empresa_id'] );
        if ( ! $dest ) continue;
        list( $mes_str, $anio_str ) = talos_ing_mes_str( $g['mes'] );
        $iva = $g['total'] - $g['subtotal'];
        $html = talos_ing_email_comprobante( $dest['nombre'], $mes_str, $anio_str, $g['items'], $g['subtotal'], $iva, $g['total'], $fecha_pago_str );
        $headers = array( 'Content-Type: text/html; charset=UTF-8', 'Bcc: facturasonce24@gmail.com' );
        $ok = wp_mail( $dest['correos'], 'Comprobante de Pago Once24 | ' . $g['empresa'], $html, $headers );
        if ( $ok ) $correos_enviados++;
    }

    wp_send_json_success( array(
        'ids'            => $actualizados,
        'fecha_chip'     => $fecha_chip,
        'correos_enviados' => $correos_enviados,
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

    foreach ( $grupos as $g ) {
        $dest = talos_ing_destinatarios( $g['empresa_id'] );
        if ( ! $dest ) {
            $sin_contacto[] = $g['empresa'];
            continue;
        }
        list( $mes_str, $anio_str ) = talos_ing_mes_str( $g['mes'] );
        $es_factura = ( 'factura' === $g['doc'] );
        $iva = $g['total'] - $g['subtotal'];
        // Solo se promete el adjunto si de verdad hay PDF + XML listos para los ingresos de este grupo.
        $tiene_adjuntos_completos = $es_factura && count( $g['adjuntos'] ) >= 2 * count( $g['ids'] );
        $html = talos_ing_email_nota_factura( $g['empresa'], $dest['nombre'], $mes_str, $anio_str, $es_factura, $g['items'], $g['subtotal'], $iva, $g['total'], $tiene_adjuntos_completos );
        $subject_label = $es_factura ? 'Factura Oficial' : 'Nota de Venta';
        $headers = array( 'Content-Type: text/html; charset=UTF-8', 'Bcc: facturasonce24@gmail.com' );
        $adjuntos = $tiene_adjuntos_completos ? $g['adjuntos'] : array();
        $ok = wp_mail( $dest['correos'], $subject_label . ' Once24 | ' . $g['empresa'], $html, $headers, $adjuntos );
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
    ) );
}
add_action( 'wp_ajax_talos_enviar_notas', 'talos_ajax_enviar_notas' );

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

    // income_quantity ya no se usa en ningún CPT/Field Group del sistema — se quitó de la UI y de aquí.
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

    // Recalcular subtotal/total si cambió algo que los afecta (sin cantidad: subtotal == precio unitario).
    $precio      = (float) get_field( 'income_unit_price', $income_id );
    $aplica_iva  = ( 'factura' === get_field( 'income_doc_type', $income_id ) );
    $subtotal    = $precio;
    $total       = $aplica_iva ? $subtotal * 1.16 : $subtotal;
    update_field( 'income_subtotal', $subtotal, $income_id );
    update_field( 'income_total', $total, $income_id );
    update_field( 'income_applies_iva', $aplica_iva ? 1 : 0, $income_id );

    wp_send_json_success( array(
        'subtotal' => talos_fmt_mxn( $subtotal ),
        'total'    => talos_fmt_mxn( $total ),
    ) );
}
add_action( 'wp_ajax_talos_actualizar_campo_income', 'talos_ajax_actualizar_campo_income' );

/**
 * Sube el PDF o el XML de una Factura y lo guarda en income_invoice_pdf/income_invoice_xml.
 */
function talos_ajax_subir_factura_archivo() {
    talos_ing_verificar_nonce();
    $income_id = isset( $_POST['income_id'] ) ? (int) $_POST['income_id'] : 0;
    $tipo      = isset( $_POST['tipo'] ) ? sanitize_key( $_POST['tipo'] ) : '';

    if ( ! $income_id || 'talos_income' !== get_post_type( $income_id ) ) {
        wp_send_json_error( array( 'mensaje' => 'Ingreso no válido.' ) );
    }
    if ( ! in_array( $tipo, array( 'pdf', 'xml' ), true ) || empty( $_FILES['archivo'] ) ) {
        wp_send_json_error( array( 'mensaje' => 'Archivo no válido.' ) );
    }

    $extensiones_por_tipo = array( 'pdf' => 'pdf', 'xml' => 'xml' );
    $ext = strtolower( pathinfo( $_FILES['archivo']['name'], PATHINFO_EXTENSION ) );
    if ( $ext !== $extensiones_por_tipo[ $tipo ] ) {
        wp_send_json_error( array( 'mensaje' => 'El archivo debe tener extensión .' . $extensiones_por_tipo[ $tipo ] ) );
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    $subido = wp_handle_upload( $_FILES['archivo'], array( 'test_form' => false ) );
    if ( isset( $subido['error'] ) ) {
        wp_send_json_error( array( 'mensaje' => $subido['error'] ) );
    }

    $campo = ( 'pdf' === $tipo ) ? 'income_invoice_pdf' : 'income_invoice_xml';
    update_field( $campo, $subido['url'], $income_id );

    $tiene_pdf = (bool) get_field( 'income_invoice_pdf', $income_id );
    $tiene_xml = (bool) get_field( 'income_invoice_xml', $income_id );

    wp_send_json_success( array(
        'nombre'  => basename( $subido['file'] ),
        'listos'  => ( $tiene_pdf ? 1 : 0 ) + ( $tiene_xml ? 1 : 0 ),
        'tiene_pdf' => $tiene_pdf,
        'tiene_xml' => $tiene_xml,
    ) );
}
add_action( 'wp_ajax_talos_subir_factura_archivo', 'talos_ajax_subir_factura_archivo' );

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
