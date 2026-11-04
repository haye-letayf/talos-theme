<?php
/**
 * Buscador global del header (#talosBuscadorGlobal). Peloteo 2026-11-04:
 * sugerencias mientras se escribe (desde 2 letras, hasta 5 por tipo).
 *
 * Busca en Empresas, Contactos, Oportunidades, Servicios (catálogo) y
 * Equipo. Ingresos, Gastos y Contratos quedan fuera a propósito: son miles
 * y se llega a ellos por la Empresa o por los filtros de su pantalla.
 *
 * Permisos: cada tipo pertenece a una sección; si el usuario no puede ver
 * esa sección (talos_secciones_consulta()), tampoco ve sus resultados.
 * La BD compara con collation *_ci, así que "lopez" encuentra "López".
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Tipos que busca el buscador global. 'meta' son los campos que se revisan
 * además del título; 'empresa_meta' agrega el nombre de la empresa ligada.
 */
function talos_buscador_tipos() {
    return array(
        'talos_company' => array(
            'grupo'   => 'Empresas',
            'seccion' => 'empresas',
            'icono'   => 'i-building',
            'meta'    => array( 'company_legal_name', 'company_rfc' ),
        ),
        'talos_contact' => array(
            'grupo'   => 'Contactos',
            'seccion' => 'contactos',
            'icono'   => 'i-users',
            'meta'    => array( 'contact_firstname', 'contact_lastname', 'contact_company_email', 'contact_personal_email', 'contact_phone_office', 'contact_phone_mobile' ),
        ),
        'talos_opportunity' => array(
            'grupo'        => 'Oportunidades',
            'seccion'      => 'oportunidades',
            'icono'        => 'i-funnel',
            'meta'         => array( 'opportunity_name', 'quote_reference' ),
            'empresa_meta' => 'opportunity_company',
        ),
        'talos_service_cat' => array(
            'grupo'   => 'Servicios',
            'seccion' => 'servicios',
            'icono'   => 'i-tag',
            'meta'    => array(),
        ),
        'talos_team' => array(
            'grupo'   => 'Equipo',
            'seccion' => 'equipo',
            'icono'   => 'i-badge',
            'meta'    => array(),
        ),
    );
}

/** IDs que coinciden con $termino en un tipo, los que empiezan igual primero. */
function talos_buscador_ids( $post_type, $conf, $termino, $limite ) {
    global $wpdb;
    $like    = '%' . $wpdb->esc_like( $termino ) . '%';
    $inicio  = $wpdb->esc_like( $termino ) . '%';
    $wheres  = array( $wpdb->prepare( 'p.post_title LIKE %s', $like ) );

    if ( $conf['meta'] ) {
        $claves   = implode( ',', array_fill( 0, count( $conf['meta'] ), '%s' ) );
        $wheres[] = $wpdb->prepare(
            "EXISTS ( SELECT 1 FROM {$wpdb->postmeta} m WHERE m.post_id = p.ID AND m.meta_key IN ( $claves ) AND m.meta_value LIKE %s )",
            array_merge( $conf['meta'], array( $like ) )
        );
    }
    if ( ! empty( $conf['empresa_meta'] ) ) {
        $wheres[] = $wpdb->prepare(
            "EXISTS ( SELECT 1 FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} c ON c.ID = m.meta_value
                      WHERE m.post_id = p.ID AND m.meta_key = %s AND c.post_type = 'talos_company' AND c.post_title LIKE %s )",
            $conf['empresa_meta'], $like
        );
    }

    // Consulta solo ve las Empresas donde es Account Manager (igual que el
    // listado de /empresas/).
    $solo_mias = '';
    if ( 'talos_company' === $post_type && ! current_user_can( 'manage_options' ) ) {
        $solo_mias = ' AND ' . $wpdb->prepare(
            "EXISTS ( SELECT 1 FROM {$wpdb->postmeta} am WHERE am.post_id = p.ID AND am.meta_key = 'company_account_manager' AND am.meta_value = %s )",
            (string) talos_mi_team_post_id()
        );
    }

    // Cada pedazo se prepara por separado (sin anidar prepare()).
    $sql = "SELECT p.ID FROM {$wpdb->posts} p WHERE "
        . $wpdb->prepare( "p.post_type = %s AND p.post_status = 'publish'", $post_type )
        . $solo_mias
        . ' AND ( ' . implode( ' OR ', $wheres ) . ' ) '
        . $wpdb->prepare( 'ORDER BY ( p.post_title LIKE %s ) DESC, p.post_title ASC LIMIT %d', $inicio, $limite );
    return array_map( 'intval', $wpdb->get_col( $sql ) );
}

/** Texto gris bajo el título de cada resultado. */
function talos_buscador_detalle( $post_type, $id ) {
    $partes = array();
    switch ( $post_type ) {
        case 'talos_company':
            $partes[] = (string) get_post_meta( $id, 'company_legal_name', true );
            $partes[] = (string) get_post_meta( $id, 'company_rfc', true );
            if ( 'inactive' === get_post_meta( $id, 'company_status', true ) ) $partes[] = 'Inactiva';
            break;
        case 'talos_contact':
            $empresa  = (int) get_post_meta( $id, 'contact_company', true );
            $partes[] = $empresa ? get_the_title( $empresa ) : '';
            $partes[] = (string) ( get_post_meta( $id, 'contact_company_email', true ) ?: get_post_meta( $id, 'contact_personal_email', true ) );
            break;
        case 'talos_opportunity':
            $empresa  = (int) get_post_meta( $id, 'opportunity_company', true );
            $partes[] = $empresa ? get_the_title( $empresa ) : '';
            $partes[] = (string) get_post_meta( $id, 'quote_reference', true );
            break;
        case 'talos_team':
            $partes[] = (string) get_post_meta( $id, 'team_role', true );
            if ( 'inactivo' === get_post_meta( $id, 'team_status', true ) ) $partes[] = 'Inactivo';
            break;
    }
    return implode( ' · ', array_filter( array_map( 'trim', $partes ) ) );
}

/** A dónde lleva cada resultado. Oportunidad no tiene ficha: va al Kanban. */
function talos_buscador_url( $post_type, $seccion, $id ) {
    if ( 'talos_opportunity' === $post_type ) {
        return add_query_arg( 'op', $id, home_url( '/oportunidades/' ) );
    }
    return add_query_arg( 'ficha', $id, home_url( '/' . $seccion . '/' ) );
}

function talos_ajax_buscar_global() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'talos_buscador' ) ) {
        wp_send_json_error( array( 'mensaje' => 'Sesión inválida, recarga la página.' ), 403 );
    }
    $termino = isset( $_POST['q'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['q'] ) ) ) : '';
    if ( mb_strlen( $termino ) < 2 ) {
        wp_send_json_success( array( 'grupos' => array() ) );
    }
    $termino = mb_substr( $termino, 0, 80 );

    $director = current_user_can( 'manage_options' );
    $grupos   = array();
    foreach ( talos_buscador_tipos() as $post_type => $conf ) {
        if ( ! $director && ! in_array( $conf['seccion'], talos_secciones_consulta(), true ) ) continue;
        $items = array();
        foreach ( talos_buscador_ids( $post_type, $conf, $termino, 5 ) as $id ) {
            $titulo = get_the_title( $id );
            if ( 'talos_opportunity' === $post_type ) {
                $titulo = (string) get_post_meta( $id, 'opportunity_name', true ) ?: $titulo;
            }
            $items[] = array(
                'titulo'  => html_entity_decode( $titulo, ENT_QUOTES, 'UTF-8' ),
                'detalle' => html_entity_decode( talos_buscador_detalle( $post_type, $id ), ENT_QUOTES, 'UTF-8' ),
                'url'     => talos_buscador_url( $post_type, $conf['seccion'], $id ),
            );
        }
        if ( $items ) {
            $grupos[] = array( 'grupo' => $conf['grupo'], 'icono' => $conf['icono'], 'items' => $items );
        }
    }
    wp_send_json_success( array( 'grupos' => $grupos ) );
}
add_action( 'wp_ajax_talos_buscar_global', 'talos_ajax_buscar_global' );
