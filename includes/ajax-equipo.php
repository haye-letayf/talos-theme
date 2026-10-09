<?php
/**
 * AJAX de Equipo: creación rápida, guardado de la ficha completa y eliminar.
 * Mismo patrón que ajax-empresas.php / ajax-contactos.php. Todo el field
 * group "Perfil de Equipo" está en Tab (Jorge aplanó también Datos
 * Bancarios, que originalmente quedó como Group anidado) — campos planos
 * normales, sin ningún truco especial de guardado.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function talos_eq_verificar_nonce() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'talos_equipo' ) ) {
        wp_send_json_error( array( 'mensaje' => 'Sesión inválida, recarga la página.' ), 403 );
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'mensaje' => 'No tienes permiso para esta acción.' ), 403 );
    }
}

/**
 * Creación mínima (Nombre + Puesto + Estatus) — el resto se completa justo
 * después, en modo edición.
 */
function talos_ajax_crear_miembro() {
    talos_eq_verificar_nonce();

    $nombre = isset( $_POST['nombre'] ) ? sanitize_text_field( wp_unslash( $_POST['nombre'] ) ) : '';
    if ( '' === $nombre ) {
        wp_send_json_error( array( 'mensaje' => 'Captura el nombre del colaborador.' ) );
    }

    $nuevo_id = wp_insert_post( array(
        'post_type'   => 'talos_team',
        'post_title'  => $nombre,
        'post_status' => 'publish',
    ), true );

    if ( is_wp_error( $nuevo_id ) || ! $nuevo_id ) {
        wp_send_json_error( array( 'mensaje' => 'No se pudo crear el miembro de equipo.' ) );
    }

    $puesto  = isset( $_POST['puesto'] ) ? sanitize_text_field( wp_unslash( $_POST['puesto'] ) ) : '';
    $estatus = isset( $_POST['estatus'] ) ? sanitize_text_field( wp_unslash( $_POST['estatus'] ) ) : 'activo';
    if ( $puesto ) {
        update_field( 'team_role', $puesto, $nuevo_id );
    }
    update_field( 'team_status', $estatus, $nuevo_id );

    wp_send_json_success( array( 'id' => $nuevo_id ) );
}
add_action( 'wp_ajax_talos_crear_miembro', 'talos_ajax_crear_miembro' );

function talos_ajax_guardar_miembro() {
    talos_eq_verificar_nonce();

    $id = isset( $_POST['miembro_id'] ) ? (int) $_POST['miembro_id'] : 0;
    if ( ! $id || 'talos_team' !== get_post_type( $id ) ) {
        wp_send_json_error( array( 'mensaje' => 'Miembro de equipo no válido.' ) );
    }

    $nombre = isset( $_POST['nombre'] ) ? sanitize_text_field( wp_unslash( $_POST['nombre'] ) ) : '';
    if ( '' !== $nombre ) {
        wp_update_post( array( 'ID' => $id, 'post_title' => $nombre ) );
    }

    $campos_texto = array( 'team_id_card', 'team_id_curp', 'team_phone', 'bank_name', 'bank_account', 'bank_clabe', 'bank_card_number' );
    foreach ( $campos_texto as $campo ) {
        if ( isset( $_POST[ $campo ] ) ) {
            update_field( $campo, sanitize_text_field( wp_unslash( $_POST[ $campo ] ) ), $id );
        }
    }

    if ( isset( $_POST['team_address'] ) ) {
        update_field( 'team_address', sanitize_textarea_field( wp_unslash( $_POST['team_address'] ) ), $id );
    }
    if ( isset( $_POST['team_exit_terms'] ) ) {
        update_field( 'team_exit_terms', sanitize_textarea_field( wp_unslash( $_POST['team_exit_terms'] ) ), $id );
    }

    foreach ( array( 'team_email_corp', 'team_email_personal' ) as $campo_correo ) {
        if ( isset( $_POST[ $campo_correo ] ) ) {
            update_field( $campo_correo, sanitize_email( wp_unslash( $_POST[ $campo_correo ] ) ), $id );
        }
    }
    foreach ( array( 'team_social_fb', 'team_social_ig' ) as $campo_url ) {
        if ( isset( $_POST[ $campo_url ] ) ) {
            update_field( $campo_url, sanitize_url( wp_unslash( $_POST[ $campo_url ] ) ), $id );
        }
    }

    foreach ( array( 'team_status', 'team_role' ) as $campo_select ) {
        if ( isset( $_POST[ $campo_select ] ) ) {
            update_field( $campo_select, sanitize_text_field( wp_unslash( $_POST[ $campo_select ] ) ), $id );
        }
    }

    foreach ( array( 'team_birthdate', 'team_start_date', 'team_end_date' ) as $campo_fecha ) {
        if ( isset( $_POST[ $campo_fecha ] ) ) {
            $valor = sanitize_text_field( wp_unslash( $_POST[ $campo_fecha ] ) );
            update_field( $campo_fecha, $valor ? str_replace( '-', '', $valor ) : '', $id );
        }
    }

    wp_send_json_success();
}
add_action( 'wp_ajax_talos_guardar_miembro', 'talos_ajax_guardar_miembro' );

/**
 * Elimina (papelera, recuperable desde wp-admin) un miembro de Equipo.
 */
function talos_ajax_eliminar_miembro() {
    talos_eq_verificar_nonce();

    $id = isset( $_POST['miembro_id'] ) ? (int) $_POST['miembro_id'] : 0;
    if ( ! $id || 'talos_team' !== get_post_type( $id ) ) {
        wp_send_json_error( array( 'mensaje' => 'Miembro de equipo no válido.' ) );
    }

    if ( ! wp_trash_post( $id ) ) {
        wp_send_json_error( array( 'mensaje' => 'No se pudo eliminar al miembro de equipo.' ) );
    }

    wp_send_json_success();
}
add_action( 'wp_ajax_talos_eliminar_miembro', 'talos_ajax_eliminar_miembro' );

/**
 * Autoedición de perfil (Dashboard de Consulta): cualquier usuario con
 * sesión puede editar SU PROPIO registro de Equipo (resuelto vía
 * talos_mi_team_post_id(), campo team_wp_user_id) — pero solo la lista
 * acotada de campos no-sensibles ($campos_permitidos). Estatus/Puesto/
 * fechas/CURP/datos bancarios/sueldo siguen siendo exclusivos de
 * talos_ajax_guardar_miembro() (manage_options).
 */
function talos_ajax_guardar_mi_perfil() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'talos_mi_perfil' ) ) {
        wp_send_json_error( array( 'mensaje' => 'Sesión inválida, recarga la página.' ), 403 );
    }
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'mensaje' => 'Debes iniciar sesión.' ), 403 );
    }

    $id = talos_mi_team_post_id();
    if ( ! $id ) {
        wp_send_json_error( array( 'mensaje' => 'Tu usuario no está vinculado a un perfil de Equipo — pide a un Director que lo vincule.' ) );
    }

    if ( isset( $_POST['team_phone'] ) ) {
        update_field( 'team_phone', sanitize_text_field( wp_unslash( $_POST['team_phone'] ) ), $id );
    }
    if ( isset( $_POST['team_email_personal'] ) ) {
        update_field( 'team_email_personal', sanitize_email( wp_unslash( $_POST['team_email_personal'] ) ), $id );
    }
    foreach ( array( 'team_social_fb', 'team_social_ig' ) as $campo_url ) {
        if ( isset( $_POST[ $campo_url ] ) ) {
            update_field( $campo_url, sanitize_url( wp_unslash( $_POST[ $campo_url ] ) ), $id );
        }
    }
    if ( isset( $_POST['team_address'] ) ) {
        update_field( 'team_address', sanitize_textarea_field( wp_unslash( $_POST['team_address'] ) ), $id );
    }

    wp_send_json_success();
}
add_action( 'wp_ajax_talos_guardar_mi_perfil', 'talos_ajax_guardar_mi_perfil' );
