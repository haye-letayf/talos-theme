<?php
/**
 * AJAX de Bitácora de Peticiones: crear, guardar ficha completa, mover de
 * etapa y eliminar. A diferencia de Empresas/Contactos, crear/editar/mover
 * NO exige manage_options — cualquier usuario de Talos con sesión (incluido
 * el rol Consulta) puede registrar y mover sus propias peticiones, ya que
 * ese es el objetivo del módulo. Eliminar sí queda restringido a quien la
 * creó o a un Director (manage_options).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function talos_bit_tipo_labels() {
    return array(
        'calendario'          => 'Cambios a Calendario',
        'campana_patrocinada' => 'Cambio en Campaña Patrocinada',
        'diseno_redes'        => 'Diseño Especial para Redes',
        'diseno_impresion'    => 'Diseño Especial para Impresión',
        'sitio_web'           => 'Cambio o Ajuste a Sitio Web',
        'otro'                => 'Otro',
    );
}

function talos_bit_prioridad_labels() {
    return array(
        'urgente' => 'Urgente',
        'media'   => 'Media',
        'baja'    => 'Baja',
        'otra'    => 'Otra',
    );
}

/**
 * Días de margen implícitos en cada prioridad (ya anunciados en su propia
 * etiqueta: "Urgente (menos de 24 hrs)", etc.) — se usan para calcular
 * "vencida" a partir de Fecha de Solicitud, sin necesitar una fecha de
 * entrega manual. "otra" no tiene ventana definida, se deja sin SLA.
 */
function talos_bit_sla_dias() {
    return array(
        'urgente' => 1,
        'media'   => 3,
        'baja'    => 6,
    );
}

/**
 * Conteo de Pendientes/En Proceso/Vencidas para un autor (post_author) en
 * particular, o para todos si se omite — reutilizado por el Dashboard
 * personalizado de Consulta (solo sus propias peticiones) y por la Cartera
 * por Asesor del Dashboard de Director (una llamada por cada miembro de
 * equipo). "Vencida" usa el mismo cálculo que el Kanban de Bitácora
 * (días transcurridos desde Fecha de Solicitud contra el margen de la
 * Prioridad) para no duplicar la lógica en dos lugares.
 */
function talos_bitacora_resumen_por_autor( $autor_id = 0 ) {
    $args = array(
        'post_type'      => 'talos_request',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    );
    if ( $autor_id ) {
        $args['author'] = $autor_id;
    }
    $ids = get_posts( $args );

    $sla = talos_bit_sla_dias();
    $hoy = new DateTime( 'today' );
    $resumen = array( 'pendientes' => 0, 'en_proceso' => 0, 'vencidas' => 0, 'total' => count( $ids ) );

    foreach ( $ids as $id ) {
        $estatus = get_field( 'request_status', $id );
        if ( 'pendiente' === $estatus ) $resumen['pendientes']++;
        elseif ( 'en_proceso' === $estatus ) $resumen['en_proceso']++;

        if ( 'completada' === $estatus ) continue;
        $fecha_solicitud = get_field( 'request_date', $id );
        if ( ! $fecha_solicitud ) continue;
        $sol = DateTime::createFromFormat( 'Y-m-d', $fecha_solicitud );
        if ( ! $sol ) continue;
        $prioridad = get_field( 'request_priority', $id );
        if ( ! isset( $sla[ $prioridad ] ) ) continue;
        $dias_transcurridos = (int) $sol->diff( $hoy )->format( '%a' );
        if ( ( $dias_transcurridos - $sla[ $prioridad ] ) > 0 ) $resumen['vencidas']++;
    }

    return $resumen;
}

/**
 * Valida que una fecha venga en Y-m-d y no sea futura (hoy sí se permite) —
 * usado para Fecha de Solicitud: se registra cuándo pidió el cliente, nunca
 * una fecha que todavía no ha llegado.
 */
function talos_bit_validar_fecha_no_futura( $fecha ) {
    $dt = DateTime::createFromFormat( 'Y-m-d', $fecha );
    if ( ! $dt ) return false;
    return $dt <= new DateTime( 'today' );
}

function talos_bit_verificar_nonce() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'talos_bitacora' ) ) {
        wp_send_json_error( array( 'mensaje' => 'Sesión inválida, recarga la página.' ), 403 );
    }
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'mensaje' => 'Debes iniciar sesión.' ), 403 );
    }
}

/**
 * Construye el título del post a partir de Tipo + Empresa — mismo criterio
 * que usaba el formulario de Fluent Forms original.
 */
function talos_bit_titulo( $tipo, $tipo_otro, $empresa_id ) {
    $labels = talos_bit_tipo_labels();
    $tipo_label = ( 'otro' === $tipo && $tipo_otro ) ? $tipo_otro : ( $labels[ $tipo ] ?? $tipo );
    return $tipo_label . ' — ' . get_the_title( $empresa_id );
}

function talos_ajax_crear_peticion() {
    talos_bit_verificar_nonce();

    $empresa_id = isset( $_POST['empresa_id'] ) ? (int) $_POST['empresa_id'] : 0;
    if ( ! $empresa_id || 'talos_company' !== get_post_type( $empresa_id ) ) {
        wp_send_json_error( array( 'mensaje' => 'Selecciona una empresa ya registrada.' ) );
    }

    $tipo = isset( $_POST['tipo'] ) ? sanitize_text_field( wp_unslash( $_POST['tipo'] ) ) : '';
    if ( ! array_key_exists( $tipo, talos_bit_tipo_labels() ) ) {
        wp_send_json_error( array( 'mensaje' => 'Selecciona el tipo de solicitud.' ) );
    }
    $tipo_otro = isset( $_POST['tipo_otro'] ) ? sanitize_text_field( wp_unslash( $_POST['tipo_otro'] ) ) : '';

    $descripcion = isset( $_POST['descripcion'] ) ? sanitize_textarea_field( wp_unslash( $_POST['descripcion'] ) ) : '';
    if ( '' === $descripcion ) {
        wp_send_json_error( array( 'mensaje' => 'Describe lo que solicita el cliente.' ) );
    }

    $prioridad = isset( $_POST['prioridad'] ) ? sanitize_text_field( wp_unslash( $_POST['prioridad'] ) ) : '';
    if ( ! in_array( $prioridad, array( 'urgente', 'media', 'baja', 'otra' ), true ) ) {
        wp_send_json_error( array( 'mensaje' => 'Selecciona la prioridad.' ) );
    }
    $prioridad_otro = isset( $_POST['prioridad_otro'] ) ? sanitize_text_field( wp_unslash( $_POST['prioridad_otro'] ) ) : '';

    $fecha_solicitud = isset( $_POST['fecha_solicitud'] ) ? sanitize_text_field( wp_unslash( $_POST['fecha_solicitud'] ) ) : '';
    if ( ! $fecha_solicitud || ! talos_bit_validar_fecha_no_futura( $fecha_solicitud ) ) {
        wp_send_json_error( array( 'mensaje' => 'Captura la fecha de solicitud (hoy o anterior, no puede ser futura).' ) );
    }

    $nuevo_id = wp_insert_post( array(
        'post_type'   => 'talos_request',
        'post_title'  => talos_bit_titulo( $tipo, $tipo_otro, $empresa_id ),
        'post_status' => 'publish',
    ), true );

    if ( is_wp_error( $nuevo_id ) || ! $nuevo_id ) {
        wp_send_json_error( array( 'mensaje' => 'No se pudo crear la petición.' ) );
    }

    update_field( 'request_company', $empresa_id, $nuevo_id );
    update_field( 'request_type', $tipo, $nuevo_id );
    update_field( 'request_type_other', $tipo_otro, $nuevo_id );
    update_field( 'request_description', $descripcion, $nuevo_id );
    update_field( 'request_priority', $prioridad, $nuevo_id );
    update_field( 'request_priority_other', $prioridad_otro, $nuevo_id );
    update_field( 'request_date', str_replace( '-', '', $fecha_solicitud ), $nuevo_id );
    update_field( 'request_status', 'pendiente', $nuevo_id );

    wp_send_json_success( array( 'id' => $nuevo_id ) );
}
add_action( 'wp_ajax_talos_crear_peticion', 'talos_ajax_crear_peticion' );

function talos_ajax_guardar_peticion() {
    talos_bit_verificar_nonce();

    $id = isset( $_POST['peticion_id'] ) ? (int) $_POST['peticion_id'] : 0;
    if ( ! $id || 'talos_request' !== get_post_type( $id ) ) {
        wp_send_json_error( array( 'mensaje' => 'Petición no válida.' ) );
    }

    $empresa_id = isset( $_POST['empresa_id'] ) ? (int) $_POST['empresa_id'] : 0;
    if ( ! $empresa_id || 'talos_company' !== get_post_type( $empresa_id ) ) {
        wp_send_json_error( array( 'mensaje' => 'Selecciona una empresa ya registrada.' ) );
    }

    $tipo = isset( $_POST['tipo'] ) ? sanitize_text_field( wp_unslash( $_POST['tipo'] ) ) : '';
    if ( ! array_key_exists( $tipo, talos_bit_tipo_labels() ) ) {
        wp_send_json_error( array( 'mensaje' => 'Selecciona el tipo de solicitud.' ) );
    }
    $tipo_otro = isset( $_POST['tipo_otro'] ) ? sanitize_text_field( wp_unslash( $_POST['tipo_otro'] ) ) : '';

    $descripcion = isset( $_POST['descripcion'] ) ? sanitize_textarea_field( wp_unslash( $_POST['descripcion'] ) ) : '';
    if ( '' === $descripcion ) {
        wp_send_json_error( array( 'mensaje' => 'Describe lo que solicita el cliente.' ) );
    }

    $prioridad = isset( $_POST['prioridad'] ) ? sanitize_text_field( wp_unslash( $_POST['prioridad'] ) ) : '';
    if ( ! in_array( $prioridad, array( 'urgente', 'media', 'baja', 'otra' ), true ) ) {
        wp_send_json_error( array( 'mensaje' => 'Selecciona la prioridad.' ) );
    }
    $prioridad_otro = isset( $_POST['prioridad_otro'] ) ? sanitize_text_field( wp_unslash( $_POST['prioridad_otro'] ) ) : '';

    $fecha_solicitud = isset( $_POST['fecha_solicitud'] ) ? sanitize_text_field( wp_unslash( $_POST['fecha_solicitud'] ) ) : '';
    if ( ! $fecha_solicitud || ! talos_bit_validar_fecha_no_futura( $fecha_solicitud ) ) {
        wp_send_json_error( array( 'mensaje' => 'Captura la fecha de solicitud (hoy o anterior, no puede ser futura).' ) );
    }

    wp_update_post( array( 'ID' => $id, 'post_title' => talos_bit_titulo( $tipo, $tipo_otro, $empresa_id ) ) );

    update_field( 'request_company', $empresa_id, $id );
    update_field( 'request_type', $tipo, $id );
    update_field( 'request_type_other', $tipo_otro, $id );
    update_field( 'request_description', $descripcion, $id );
    update_field( 'request_priority', $prioridad, $id );
    update_field( 'request_priority_other', $prioridad_otro, $id );
    update_field( 'request_date', str_replace( '-', '', $fecha_solicitud ), $id );

    wp_send_json_success();
}
add_action( 'wp_ajax_talos_guardar_peticion', 'talos_ajax_guardar_peticion' );

/**
 * Mueve la petición a la siguiente etapa (Pendiente -> En Proceso -> Completada).
 * Sin drag&drop: el botón en la tarjeta ya trae la etapa destino fija. Al
 * llegar a Completada, Fecha de Entrega se registra sola con el día de hoy —
 * nunca se captura a mano, es la base para medir tiempos de respuesta real.
 */
function talos_ajax_mover_etapa_peticion() {
    talos_bit_verificar_nonce();

    $id = isset( $_POST['peticion_id'] ) ? (int) $_POST['peticion_id'] : 0;
    if ( ! $id || 'talos_request' !== get_post_type( $id ) ) {
        wp_send_json_error( array( 'mensaje' => 'Petición no válida.' ) );
    }

    $etapa = isset( $_POST['etapa'] ) ? sanitize_text_field( wp_unslash( $_POST['etapa'] ) ) : '';
    if ( ! in_array( $etapa, array( 'pendiente', 'en_proceso', 'completada' ), true ) ) {
        wp_send_json_error( array( 'mensaje' => 'Etapa no válida.' ) );
    }

    update_field( 'request_status', $etapa, $id );
    if ( 'completada' === $etapa ) {
        update_field( 'request_completed_date', current_time( 'Ymd' ), $id );
    }

    wp_send_json_success();
}
add_action( 'wp_ajax_talos_mover_etapa_peticion', 'talos_ajax_mover_etapa_peticion' );

/**
 * Elimina (papelera, recuperable desde wp-admin) una Petición — solo quien
 * la creó o un Director (manage_options), para que el rol Consulta no borre
 * peticiones de otros por error.
 */
function talos_ajax_eliminar_peticion() {
    talos_bit_verificar_nonce();

    $id = isset( $_POST['peticion_id'] ) ? (int) $_POST['peticion_id'] : 0;
    if ( ! $id || 'talos_request' !== get_post_type( $id ) ) {
        wp_send_json_error( array( 'mensaje' => 'Petición no válida.' ) );
    }

    $post = get_post( $id );
    $es_autor = $post && (int) $post->post_author === get_current_user_id();
    if ( ! $es_autor && ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'mensaje' => 'Solo quien la registró o un Director puede eliminarla.' ), 403 );
    }

    if ( ! wp_trash_post( $id ) ) {
        wp_send_json_error( array( 'mensaje' => 'No se pudo eliminar la petición.' ) );
    }

    wp_send_json_success();
}
add_action( 'wp_ajax_talos_eliminar_peticion', 'talos_ajax_eliminar_peticion' );
