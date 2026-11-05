<?php
/**
 * Ficha de Oportunidad (page-oportunidades.php?ficha=ID, &editar para abrir
 * editando). Peloteo 2026-11-04 (etapa-2-pantallas-front.md):
 * - Mismo patrón Ver/Editar que las demás fichas; guarda con
 *   talos_guardar_oportunidad (includes/ajax-oportunidades.php), que dispara
 *   acf/save_post: referencia COT, vigencia, fecha de envío y conversión a
 *   Cliente al quedar en "Ganada".
 * - Conceptos (repeater quote_items): tabla editable con autollenado del
 *   catálogo y total en vivo (misma cuenta que talos_oportunidad_valor_total).
 * - Ya convertida a Cliente: conceptos de solo lectura; los precios se
 *   ajustan en los contratos de la Empresa.
 * - Sin log de cambios (decisión de Jorge).
 * Recibe $talos_ficha_id y $talos_opp_etapas desde page-oportunidades.php.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$id          = $talos_ficha_id;
$editando    = isset( $_GET['editar'] );
$meses_c     = talos_meses_cortos();
$empresa_id  = (int) get_field( 'opportunity_company', $id, false );
$nombre      = (string) get_field( 'opportunity_name', $id ) ?: get_the_title( $id );
$etapa       = (string) get_field( 'opportunity_stage', $id ) ?: 'prospeccion';
$motivo      = (string) get_field( 'opportunity_lost_reason', $id );
$contacto_id = (int) get_field( 'quote_contact', $id, false );
$referencia  = (string) get_field( 'quote_reference', $id );
$creada_raw  = preg_replace( '/\D/', '', (string) get_field( 'quote_created_date', $id, false ) );
$vigencia_raw= preg_replace( '/\D/', '', (string) get_field( 'quote_valid_until', $id, false ) );
$enviada     = (bool) get_field( 'quote_sent', $id );
$envio_raw   = preg_replace( '/\D/', '', (string) get_field( 'quote_sent_date', $id, false ) );
$notas       = (string) get_field( 'quote_notes', $id );
$terminos    = (string) get_field( 'quote_terms', $id );
$desc_gral   = (bool) get_field( 'quote_has_general_discount', $id );
$desc_tipo   = $desc_gral ? (string) get_field( 'quote_general_discount_type', $id ) : '';
$desc_valor  = $desc_gral ? (float) get_field( 'quote_general_discount_value', $id ) : 0;
$convertida  = (bool) get_post_meta( $id, '_talos_opportunity_converted', true );
$empresa_nom = $empresa_id ? get_the_title( $empresa_id ) : '';

$fmt_fecha = function( $d ) use ( $meses_c ) {
    if ( strlen( $d ) < 8 ) return '—';
    return (int) substr( $d, 6, 2 ) . ' ' . $meses_c[ (int) substr( $d, 4, 2 ) - 1 ] . ' ' . substr( $d, 0, 4 );
};
$iso = function( $d ) {
    return strlen( $d ) >= 8 ? substr( $d, 0, 4 ) . '-' . substr( $d, 4, 2 ) . '-' . substr( $d, 6, 2 ) : '';
};
$dinero = function( $n ) { return '$' . number_format( (float) $n, 2, '.', ',' ); };

// Si nunca se capturó la fecha de la cotización, el formulario propone la de alta.
$creada_input = $iso( $creada_raw ) ?: get_the_date( 'Y-m-d', $id );

$frecuencias = array(
    'pago_unico' => 'Pago Único', 'por_hora' => 'Por Hora', 'semanal' => 'Semanal', 'quincenal' => 'Quincenal',
    'mensual' => 'Mensual', 'trimestral' => 'Trimestral', 'semestral' => 'Semestral', 'anual' => 'Anual',
);

// Conceptos, ya normalizados para la tabla y para el editor (JS).
$conceptos = array();
foreach ( (array) get_field( 'quote_items', $id ) as $item ) {
    if ( ! is_array( $item ) ) continue;
    $svc = $item['service_item'] ?? null;
    $con_desc = ! empty( $item['item_has_discount'] );
    $conceptos[] = array(
        'servicio'       => $svc instanceof WP_Post ? $svc->ID : (int) $svc,
        'servicio_nom'   => $svc instanceof WP_Post ? $svc->post_title : '',
        'descripcion'    => (string) ( $item['service_invoice_description'] ?? '' ),
        'frecuencia'     => (string) ( $item['service_frequency'] ?? '' ),
        'cantidad'       => (float) ( $item['service_quantity'] ?? 1 ) ?: 1,
        'precio'         => (float) ( $item['service_price'] ?? 0 ),
        'costo'          => (float) ( $item['service_cost'] ?? 0 ),
        'iva'            => ! empty( $item['service_applies_iva'] ),
        'tipo_descuento' => $con_desc ? (string) ( $item['item_discount_type'] ?? '' ) : '',
        'descuento'      => $con_desc ? (float) ( $item['item_discount_value'] ?? 0 ) : 0,
        'final'          => function_exists( 'talos_precio_final_concepto' ) ? talos_precio_final_concepto( $item ) : (float) ( $item['service_price'] ?? 0 ),
    );
}
$subtotal = 0.0;
foreach ( $conceptos as $c ) $subtotal += $c['cantidad'] * $c['final'];
$total = talos_oportunidad_valor_total( $id );

// Contratos que creó la conversión a Cliente (su título lo dice).
$contratos_creados = 0;
if ( $convertida ) {
    global $wpdb;
    $contratos_creados = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'talos_contract' AND post_status = 'publish' AND post_title LIKE %s",
        '%' . $wpdb->esc_like( '(desde Oportunidad #' . $id . ')' )
    ) );
}

$empresas  = get_posts( array( 'post_type' => 'talos_company', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
$contactos = array();
foreach ( get_posts( array( 'post_type' => 'talos_contact', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC', 'fields' => 'ids' ) ) as $cid ) {
    $contactos[] = array( 'id' => $cid, 'nombre' => html_entity_decode( get_the_title( $cid ), ENT_QUOTES, 'UTF-8' ), 'empresa' => (int) get_post_meta( $cid, 'contact_company', true ) );
}
$catalogo = array();
$frec_viejas = array( 'único' => 'pago_unico', 'unico' => 'pago_unico', 'hora' => 'por_hora' );
foreach ( get_posts( array( 'post_type' => 'talos_service_cat', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ) as $svc ) {
    $frec = mb_strtolower( trim( (string) get_field( 'service_ref_frequency', $svc->ID ) ) );
    $frec = $frec_viejas[ $frec ] ?? $frec;
    $catalogo[] = array(
        'id'          => $svc->ID,
        'nombre'      => html_entity_decode( $svc->post_title, ENT_QUOTES, 'UTF-8' ),
        'descripcion' => (string) get_field( 'service_description', $svc->ID ),
        'precio'      => (float) get_field( 'service_ref_price_mxn', $svc->ID ),
        'frecuencia'  => isset( $frecuencias[ $frec ] ) ? $frec : '',
    );
}

$etapa_info = $talos_opp_etapas[ $etapa ] ?? array( 'label' => $etapa, 'color' => 'var(--border)' );
$dias_vig = null;
if ( strlen( $vigencia_raw ) >= 8 ) {
    $dias_vig = (int) ( new DateTime( 'today' ) )->diff( DateTime::createFromFormat( '!Ymd', $vigencia_raw ) )->format( '%r%a' );
}
$abierta = in_array( $etapa, array( 'prospeccion', 'propuesta_enviada', 'en_negociacion' ), true );
$url_volver = add_query_arg( 'op', $id, home_url( '/oportunidades/' ) );

get_header();
talos_ficha_estilos();
?>
<style>
  .pill.etapa{background:var(--surface-2);color:var(--text);}
  .conceptos-tabla{width:100%;border-collapse:collapse;font-size:13px;}
  .conceptos-tabla th{text-align:left;font-size:10.5px;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);font-weight:700;padding:0 8px 8px 0;border-bottom:1px solid var(--border);white-space:nowrap;}
  .conceptos-tabla td{padding:9px 8px 9px 0;border-bottom:1px solid var(--border);vertical-align:top;white-space:normal;}
  .conceptos-tabla .num{text-align:right;white-space:nowrap;}
  .conceptos-tabla .sub{display:block;font-size:11.5px;color:var(--text-muted);margin-top:2px;}
  .conceptos-tabla input,.conceptos-tabla select,.conceptos-tabla textarea{width:100%;border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:7px;padding:6px 8px;font:inherit;font-size:12.5px;}
  /* El shell pone nowrap a todo td: el servicio y su descripción van apilados. */
  .conceptos-tabla td > select,.conceptos-tabla td > textarea{display:block;}
  .conceptos-tabla textarea{resize:vertical;min-height:34px;}
  .conceptos-tabla .w-num{width:84px;}
  .conceptos-tabla .w-cant{width:62px;}
  .conceptos-tabla .desc-par{display:flex;gap:4px;min-width:130px;}
  .conceptos-tabla .desc-par select{width:62px;flex:none;padding:6px 4px;}
  .conceptos-tabla .chk{text-align:center;}
  .conceptos-tabla .chk input{width:auto;}
  .totales{display:flex;flex-direction:column;align-items:flex-end;gap:6px;margin-top:14px;font-size:13px;}
  .totales .fila{display:flex;gap:18px;align-items:center;}
  .totales .fila span:first-child{color:var(--text-muted);}
  .totales .fila.total{font-size:16px;font-weight:800;}
  .totales .fila.total span:first-child{color:var(--text);}
  .totales .desc-gral{display:none;gap:6px;align-items:center;}
  .totales .desc-gral select,.totales .desc-gral input{border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:7px;padding:5px 8px;font:inherit;font-size:12.5px;}
  .totales .desc-gral input{width:90px;}
  body.editando .totales .desc-gral{display:flex;}
  body.editando .totales .desc-gral-vista{display:none;}
  .editor-conceptos{display:none;}
  body.editando .editor-conceptos{display:block;}
  body.editando .vista-conceptos{display:none;}
  .aviso-convertida{background:var(--success-soft);color:var(--text);border-radius:10px;padding:12px 14px;font-size:13px;margin-bottom:14px;}
  .data-field textarea{min-height:70px;}
  .data-field .view-value.multilinea{white-space:pre-line;display:block;font-weight:500;}
</style>

<a class="back-link" href="<?php echo esc_url( $url_volver ); ?>"><svg viewBox="0 0 24 24"><use href="#i-chevron-left"/></svg>Volver a Oportunidades</a>

<div class="page-head ficha-modo-vista">
  <div>
    <h1 class="page-title"><?php echo esc_html( $nombre ); ?></h1>
    <p class="page-sub">Ficha de Oportunidad<?php echo $referencia ? ' · ' . esc_html( $referencia ) : ''; ?></p>
    <div class="ficha-pills">
      <span class="pill etapa"><span class="pill-dot" style="background:<?php echo esc_attr( $etapa_info['color'] ); ?>"></span><?php echo esc_html( $etapa_info['label'] ); ?></span>
      <?php if ( $convertida ) : ?><span class="pill ok"><span class="pill-dot"></span>Convertida a Cliente</span><?php endif; ?>
      <?php if ( $abierta ) : ?>
        <span class="pill <?php echo $enviada ? 'ok' : 'pend'; ?>"><span class="pill-dot"></span><?php echo $enviada ? 'Enviada ' . esc_html( $fmt_fecha( $envio_raw ) ) : 'Sin enviar'; ?></span>
        <?php if ( null !== $dias_vig ) : ?>
          <span class="pill <?php echo $dias_vig < 0 ? 'mal' : ( $dias_vig <= 7 ? 'pend' : 'ok' ); ?>"><span class="pill-dot"></span><?php echo esc_html( $dias_vig < 0 ? 'Venció hace ' . abs( $dias_vig ) . ' días' : ( 0 === $dias_vig ? 'Vence hoy' : 'Vence en ' . $dias_vig . ' días' ) ); ?></span>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
  <div class="head-controls">
    <button type="button" class="btn-primary solo-vista" id="btnEditarFicha"><svg viewBox="0 0 24 24"><use href="#i-edit"/></svg>Editar</button>
    <button type="button" class="btn-ghost solo-editando" id="btnCancelarFicha">Cancelar</button>
    <button type="button" class="btn-confirm solo-editando" id="btnGuardarFicha">Guardar Cambios</button>
  </div>
</div>

<div class="ficha-card" id="fichaForm">
  <div class="ficha-section-label">Seguimiento</div>
  <div class="data-grid">
    <div class="data-field">
      <label>Empresa</label>
      <div class="view-value"><?php if ( $empresa_id ) : ?><a class="link-ficha" href="<?php echo esc_url( add_query_arg( 'ficha', $empresa_id, home_url( '/empresas/' ) ) ); ?>"><?php echo esc_html( $empresa_nom ); ?></a><?php else : ?>—<?php endif; ?></div>
      <select name="opportunity_company" id="foEmpresa">
        <option value="">Selecciona…</option>
        <?php foreach ( $empresas as $e ) : ?>
          <option value="<?php echo esc_attr( $e->ID ); ?>" <?php selected( $empresa_id, $e->ID ); ?>><?php echo esc_html( $e->post_title ); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="data-field">
      <label>Nombre de la oportunidad</label>
      <div class="view-value"><?php echo esc_html( $nombre ); ?></div>
      <input type="text" name="opportunity_name" value="<?php echo esc_attr( $nombre ); ?>">
    </div>
    <div class="data-field">
      <label>Etapa</label>
      <div class="view-value"><?php echo esc_html( $etapa_info['label'] ); ?></div>
      <select name="opportunity_stage" id="foEtapa">
        <?php foreach ( $talos_opp_etapas as $k => $info ) : ?>
          <option value="<?php echo esc_attr( $k ); ?>" <?php selected( $etapa, $k ); ?>><?php echo esc_html( $info['label'] ); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="data-field">
      <label>Contacto de la cotización</label>
      <div class="view-value"><?php if ( $contacto_id && get_post( $contacto_id ) ) : ?><a class="link-ficha" href="<?php echo esc_url( add_query_arg( 'ficha', $contacto_id, home_url( '/contactos/' ) ) ); ?>"><?php echo esc_html( get_the_title( $contacto_id ) ); ?></a><?php else : ?>—<?php endif; ?></div>
      <select name="quote_contact" id="foContacto"></select>
    </div>
    <div class="data-field full" id="foMotivoWrap" <?php echo 'perdida' === $etapa ? '' : 'hidden'; ?>>
      <label>Motivo de pérdida</label>
      <div class="view-value multilinea"><?php echo esc_html( $motivo ?: '—' ); ?></div>
      <textarea name="opportunity_lost_reason" rows="2"><?php echo esc_textarea( $motivo ); ?></textarea>
    </div>
  </div>
</div>

<div class="ficha-card">
  <div class="ficha-section-label">Cotización</div>
  <div class="data-grid">
    <div class="data-field locked">
      <label>Referencia</label>
      <div class="view-value"><?php echo esc_html( $referencia ?: 'Se genera al guardar' ); ?></div>
    </div>
    <div class="data-field">
      <label>Fecha de la cotización</label>
      <div class="view-value"><?php echo esc_html( $fmt_fecha( $creada_raw ) ); ?></div>
      <input type="date" name="quote_created_date" value="<?php echo esc_attr( $creada_input ); ?>">
    </div>
    <div class="data-field">
      <label>Vigente hasta</label>
      <div class="view-value"><?php echo esc_html( $fmt_fecha( $vigencia_raw ) ); ?></div>
      <input type="date" name="quote_valid_until" value="<?php echo esc_attr( $iso( $vigencia_raw ) ); ?>">
      <span class="field-hint">Si la dejas vacía, queda a 30 días de la fecha de la cotización.</span>
    </div>
    <div class="data-field">
      <label>Fecha de envío</label>
      <div class="view-value"><?php echo $enviada ? esc_html( $fmt_fecha( $envio_raw ) ) : 'Sin enviar'; ?></div>
      <input type="date" name="quote_sent_date" id="foFechaEnvio" value="<?php echo esc_attr( $iso( $envio_raw ) ); ?>">
      <span class="field-hint">Al marcarla enviada sin fecha, se pone la de hoy.</span>
    </div>
    <div class="data-field checkbox">
      <label>Enviada al cliente</label>
      <div class="view-value"><?php echo $enviada ? 'Sí' : 'No'; ?></div>
      <input type="checkbox" name="quote_sent" value="1" id="foEnviada" <?php checked( $enviada ); ?>>
    </div>
  </div>
</div>

<div class="ficha-card">
  <div class="ficha-section-label">Conceptos</div>

  <?php if ( $convertida ) : ?>
    <div class="aviso-convertida editor-aviso">
      Esta oportunidad ya se convirtió a Cliente: creó <?php echo esc_html( $contratos_creados ); ?> servicio<?php echo 1 === $contratos_creados ? '' : 's'; ?> en
      <?php if ( $empresa_id ) : ?><a class="link-ficha" href="<?php echo esc_url( add_query_arg( 'ficha', $empresa_id, home_url( '/empresas/' ) ) ); ?>"><?php echo esc_html( $empresa_nom ); ?></a><?php else : ?>la Empresa<?php endif; ?>.
      Los conceptos ya no se editan aquí; los precios se ajustan en los servicios contratados de la Empresa.
    </div>
  <?php endif; ?>

  <div class="vista-conceptos"<?php echo $convertida ? ' style="display:block"' : ''; ?>>
    <?php if ( ! $conceptos ) : ?>
      <p class="field-hint">Sin conceptos todavía. Dale Editar para agregarlos.</p>
    <?php else : ?>
      <div class="table-scroll">
        <table class="conceptos-tabla">
          <thead><tr><th>Concepto</th><th>Frecuencia</th><th class="num">Cant.</th><th class="num">Precio</th><th class="num">Descuento</th><th class="num">Importe</th><th class="chk">IVA</th></tr></thead>
          <tbody>
            <?php foreach ( $conceptos as $c ) : ?>
              <tr>
                <td><?php echo esc_html( $c['descripcion'] ?: $c['servicio_nom'] ?: '—' ); ?><?php if ( $c['servicio_nom'] && $c['descripcion'] ) : ?><span class="sub"><?php echo esc_html( $c['servicio_nom'] ); ?></span><?php endif; ?></td>
                <td><?php echo esc_html( $frecuencias[ $c['frecuencia'] ] ?? '—' ); ?></td>
                <td class="num tabular"><?php echo esc_html( rtrim( rtrim( number_format( $c['cantidad'], 2, '.', '' ), '0' ), '.' ) ); ?></td>
                <td class="num tabular"><?php echo esc_html( $dinero( $c['precio'] ) ); ?></td>
                <td class="num tabular"><?php echo $c['tipo_descuento'] ? esc_html( 'porcentaje' === $c['tipo_descuento'] ? rtrim( rtrim( number_format( $c['descuento'], 2, '.', '' ), '0' ), '.' ) . '%' : $dinero( $c['descuento'] ) ) : '—'; ?></td>
                <td class="num tabular"><?php echo esc_html( $dinero( $c['cantidad'] * $c['final'] ) ); ?></td>
                <td class="chk"><?php echo $c['iva'] ? 'Sí' : 'No'; ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <?php if ( ! $convertida ) : ?>
  <div class="editor-conceptos">
    <div class="table-scroll">
      <table class="conceptos-tabla">
        <thead><tr><th>Servicio / descripción</th><th>Frecuencia</th><th>Cant.</th><th>Precio</th><th>Descuento</th><th>Costo</th><th class="chk">IVA</th><th class="num">Importe</th><th></th></tr></thead>
        <tbody id="foConceptos"></tbody>
      </table>
    </div>
    <button type="button" class="btn-secondary" id="btnAgregarConcepto" style="margin-top:12px;"><svg viewBox="0 0 24 24"><use href="#i-plus"/></svg>Agregar concepto</button>
  </div>
  <?php endif; ?>

  <div class="totales">
    <div class="fila"><span>Subtotal</span><span class="tabular" id="foSubtotal"><?php echo esc_html( $dinero( $subtotal ) ); ?></span></div>
    <div class="fila">
      <span>Descuento general</span>
      <span class="desc-gral-vista tabular"><?php echo $desc_tipo ? esc_html( 'porcentaje' === $desc_tipo ? rtrim( rtrim( number_format( $desc_valor, 2, '.', '' ), '0' ), '.' ) . '%' : $dinero( $desc_valor ) ) : '—'; ?></span>
      <span class="desc-gral">
        <select name="quote_general_discount_type" id="foDescTipo">
          <option value="" <?php selected( $desc_tipo, '' ); ?>>Sin descuento</option>
          <option value="porcentaje" <?php selected( $desc_tipo, 'porcentaje' ); ?>>%</option>
          <option value="monto_fijo" <?php selected( $desc_tipo, 'monto_fijo' ); ?>>$ fijo</option>
        </select>
        <input type="number" name="quote_general_discount_value" id="foDescValor" step="any" min="0" value="<?php echo $desc_tipo ? esc_attr( $desc_valor ) : ''; ?>">
      </span>
    </div>
    <div class="fila total"><span>Total sin IVA</span><span class="tabular" id="foTotal"><?php echo esc_html( $dinero( $total ) ); ?></span></div>
  </div>
</div>

<div class="ficha-card">
  <div class="ficha-section-label">Generales</div>
  <div class="data-grid">
    <div class="data-field full">
      <label>Notas</label>
      <div class="view-value multilinea"><?php echo esc_html( $notas ?: '—' ); ?></div>
      <textarea name="quote_notes" rows="3"><?php echo esc_textarea( $notas ); ?></textarea>
    </div>
    <div class="data-field full">
      <label>Términos y condiciones</label>
      <div class="view-value multilinea"><?php echo esc_html( $terminos ?: '—' ); ?></div>
      <textarea name="quote_terms" rows="3"><?php echo esc_textarea( $terminos ); ?></textarea>
    </div>
  </div>
</div>

<div class="ficha-modo-vista ficha-acciones-pie">
  <button type="button" class="btn-danger solo-editando" id="btnEliminarFicha"><svg viewBox="0 0 24 24"><use href="#i-trash"/></svg>Eliminar Oportunidad</button>
</div>

<div class="modal-overlay" id="modalAviso">
  <div class="modal-box">
    <div class="modal-icon" style="background:#fdf3d8;color:#8a6200;"><svg viewBox="0 0 24 24"><use href="#i-alert-clock"/></svg></div>
    <h4 id="modalAvisoTitulo">¿Guardar los cambios?</h4>
    <p id="modalAvisoTexto"></p>
    <div class="modal-actions">
      <button class="btn-ghost" data-close-modal>Cancelar</button>
      <button class="btn-confirm" id="btnConfirmarAviso">Sí, continuar</button>
    </div>
  </div>
</div>

<div class="toast" id="toastFicha"><svg viewBox="0 0 24 24"><use href="#i-check"/></svg><span id="toastFichaTexto"></span></div>

<script>
try{
  var talosNonce = '<?php echo esc_js( wp_create_nonce( 'talos_oportunidades' ) ); ?>';
  var talosAjaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
  var fichaId = <?php echo (int) $id; ?>;
  var fichaUrl = '<?php echo esc_js( add_query_arg( 'ficha', $id, home_url( '/oportunidades/' ) ) ); ?>';
  var convertida = <?php echo $convertida ? 'true' : 'false'; ?>;
  var etapaInicial = <?php echo wp_json_encode( $etapa ); ?>;
  var contactos = <?php echo wp_json_encode( $contactos ); ?>;
  var contactoActual = <?php echo (int) $contacto_id; ?>;
  var catalogo = <?php echo wp_json_encode( $catalogo ); ?>;
  var frecuencias = <?php echo wp_json_encode( $frecuencias ); ?>;
  var conceptos = <?php echo wp_json_encode( array_map( function( $c ) { unset( $c['servicio_nom'], $c['final'] ); return $c; }, $conceptos ) ); ?>;

  function post(accion, datos){
    var form = new FormData();
    form.append('action', accion);
    form.append('nonce', talosNonce);
    Object.keys(datos).forEach(function(k){ form.append(k, datos[k]); });
    return fetch(talosAjaxUrl, { method: 'POST', body: form, credentials: 'same-origin' }).then(function(r){ return r.json(); });
  }
  function toast(texto){
    var t = document.getElementById('toastFicha');
    document.getElementById('toastFichaTexto').textContent = texto;
    t.classList.add('show');
    clearTimeout(t._t);
    t._t = setTimeout(function(){ t.classList.remove('show'); }, Math.max(3000, texto.length * 60));
  }
  function esc(t){ return String(t == null ? '' : t).replace(/[&<>"']/g, function(c){ return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function dinero(n){ return '$' + (Math.round(n * 100) / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

  // Modal de aviso reutilizable: confirmar(titulo, texto, botón, color) -> Promise
  var modalAviso = document.getElementById('modalAviso');
  var alConfirmar = null;
  function confirmar(titulo, texto, boton, color){
    document.getElementById('modalAvisoTitulo').textContent = titulo;
    document.getElementById('modalAvisoTexto').textContent = texto;
    var b = document.getElementById('btnConfirmarAviso');
    b.textContent = boton;
    b.style.background = color || '';
    b.disabled = false;
    modalAviso.classList.add('show');
    return new Promise(function(resolve){ alConfirmar = resolve; });
  }
  modalAviso.querySelectorAll('[data-close-modal]').forEach(function(b){ b.addEventListener('click', function(){ modalAviso.classList.remove('show'); alConfirmar = null; }); });
  document.getElementById('btnConfirmarAviso').addEventListener('click', function(){
    this.disabled = true;
    modalAviso.classList.remove('show');
    if (alConfirmar){ var f = alConfirmar; alConfirmar = null; f(); }
  });

  // ===== Ver / Editar =====
  function entrarEdicion(){ document.body.classList.add('editando'); }
  <?php if ( $editando ) : ?>entrarEdicion();<?php endif; ?>
  document.getElementById('btnEditarFicha').addEventListener('click', entrarEdicion);
  document.getElementById('btnCancelarFicha').addEventListener('click', function(){ window.location.href = fichaUrl; });

  // ===== Contacto: solo los de la empresa elegida =====
  var foEmpresa = document.getElementById('foEmpresa'), foContacto = document.getElementById('foContacto');
  function llenarContactos(){
    var emp = parseInt(foEmpresa.value, 10) || 0;
    var propios = contactos.filter(function(c){ return c.empresa === emp; });
    var html = '<option value="">' + (propios.length ? '— Sin contacto —' : 'La empresa no tiene contactos') + '</option>';
    propios.forEach(function(c){ html += '<option value="' + c.id + '"' + (c.id === contactoActual ? ' selected' : '') + '>' + esc(c.nombre) + '</option>'; });
    foContacto.innerHTML = html;
  }
  llenarContactos();
  foEmpresa.addEventListener('change', llenarContactos);

  // ===== Etapa: motivo de pérdida solo en "Perdida" =====
  var foEtapa = document.getElementById('foEtapa');
  foEtapa.addEventListener('change', function(){ document.getElementById('foMotivoWrap').hidden = this.value !== 'perdida'; });

  // ===== Enviada: propone la fecha de hoy =====
  document.getElementById('foEnviada').addEventListener('change', function(){
    var f = document.getElementById('foFechaEnvio');
    if (this.checked && !f.value) f.value = '<?php echo esc_js( current_time( 'Y-m-d' ) ); ?>';
  });

  // ===== Conceptos (misma cuenta que talos_precio_final_concepto + descuento general) =====
  function precioFinal(c){
    var p = parseFloat(c.precio) || 0, v = parseFloat(c.descuento) || 0;
    if (c.tipo_descuento === 'porcentaje') p -= p * v / 100;
    else if (c.tipo_descuento === 'monto_fijo') p -= v;
    return Math.max(0, Math.round(p * 100) / 100);
  }
  function recalcular(){
    var lista = convertida ? null : conceptos;
    var sub = 0;
    if (lista){
      lista.forEach(function(c, i){
        var imp = (parseFloat(c.cantidad) || 0) * precioFinal(c);
        sub += imp;
        var celda = document.querySelector('[data-importe="' + i + '"]');
        if (celda) celda.textContent = dinero(imp);
      });
      document.getElementById('foSubtotal').textContent = dinero(sub);
    } else {
      sub = <?php echo wp_json_encode( round( $subtotal, 2 ) ); ?>;
    }
    var tipo = document.getElementById('foDescTipo').value, val = parseFloat(document.getElementById('foDescValor').value) || 0;
    var total = sub;
    if (tipo === 'porcentaje') total -= total * val / 100;
    else if (tipo === 'monto_fijo') total -= val;
    document.getElementById('foTotal').textContent = dinero(Math.max(0, total));
  }
  document.getElementById('foDescTipo').addEventListener('change', recalcular);
  document.getElementById('foDescValor').addEventListener('input', recalcular);

  var tbody = document.getElementById('foConceptos');
  function opciones(lista, actual, vacio){
    var html = '<option value="">' + vacio + '</option>';
    lista.forEach(function(o){ html += '<option value="' + esc(o[0]) + '"' + (String(o[0]) === String(actual) ? ' selected' : '') + '>' + esc(o[1]) + '</option>'; });
    return html;
  }
  function pintarConceptos(){
    if (!tbody) return;
    var html = '';
    conceptos.forEach(function(c, i){
      html += '<tr data-i="' + i + '">'
        + '<td style="min-width:220px;"><select data-k="servicio">' + opciones(catalogo.map(function(s){ return [s.id, s.nombre]; }), c.servicio, '— Servicio del catálogo —') + '</select>'
        + '<textarea data-k="descripcion" rows="1" placeholder="Descripción para el cliente" style="margin-top:4px;">' + esc(c.descripcion) + '</textarea></td>'
        + '<td><select data-k="frecuencia" style="min-width:110px;">' + opciones(Object.keys(frecuencias).map(function(k){ return [k, frecuencias[k]]; }), c.frecuencia, '—') + '</select></td>'
        + '<td><input type="number" data-k="cantidad" class="w-cant" step="any" min="0" value="' + esc(c.cantidad) + '"></td>'
        + '<td><input type="number" data-k="precio" class="w-num" step="any" min="0" value="' + esc(c.precio) + '"></td>'
        + '<td><div class="desc-par"><select data-k="tipo_descuento">' + opciones([['porcentaje', '%'], ['monto_fijo', '$']], c.tipo_descuento, '—') + '</select>'
        + '<input type="number" data-k="descuento" step="any" min="0" value="' + (c.tipo_descuento ? esc(c.descuento) : '') + '"></div></td>'
        + '<td><input type="number" data-k="costo" class="w-num" step="any" min="0" value="' + esc(c.costo) + '"></td>'
        + '<td class="chk"><input type="checkbox" data-k="iva"' + (c.iva ? ' checked' : '') + '></td>'
        + '<td class="num tabular" data-importe="' + i + '"></td>'
        + '<td><button type="button" class="action-btn" data-quitar-concepto="' + i + '" title="Quitar concepto"><svg viewBox="0 0 24 24"><use href="#i-trash"/></svg></button></td>'
        + '</tr>';
    });
    if (!conceptos.length) html = '<tr><td colspan="9" class="field-hint" style="padding:14px 0;">Sin conceptos. Agrega el primero.</td></tr>';
    tbody.innerHTML = html;
    recalcular();
  }
  if (tbody){
    tbody.addEventListener('input', function(e){
      var k = e.target.getAttribute('data-k'), tr = e.target.closest('tr[data-i]');
      if (!k || !tr) return;
      var c = conceptos[parseInt(tr.getAttribute('data-i'), 10)];
      c[k] = e.target.type === 'checkbox' ? e.target.checked : e.target.value;
      // Autollenado como en Servicios Contratados: descripción del catálogo
      // siempre; precio y frecuencia solo si están vacíos.
      if (k === 'servicio'){
        var ref = catalogo.filter(function(s){ return String(s.id) === String(c.servicio); })[0];
        if (ref){
          if (ref.descripcion) c.descripcion = ref.descripcion;
          if (!(parseFloat(c.precio) > 0) && ref.precio) c.precio = ref.precio;
          if (!c.frecuencia && ref.frecuencia) c.frecuencia = ref.frecuencia;
          pintarConceptos();
          return;
        }
      }
      recalcular();
    });
    tbody.addEventListener('click', function(e){
      var b = e.target.closest('[data-quitar-concepto]');
      if (!b) return;
      conceptos.splice(parseInt(b.getAttribute('data-quitar-concepto'), 10), 1);
      pintarConceptos();
    });
    document.getElementById('btnAgregarConcepto').addEventListener('click', function(){
      conceptos.push({ servicio: '', descripcion: '', frecuencia: '', cantidad: 1, precio: '', costo: '', iva: true, tipo_descuento: '', descuento: '' });
      pintarConceptos();
      var filas = tbody.querySelectorAll('tr[data-i]');
      if (filas.length) filas[filas.length - 1].querySelector('select').focus();
    });
    pintarConceptos();
  }

  // ===== Guardar =====
  function datosFicha(){
    var datos = { oportunidad_id: fichaId };
    document.querySelectorAll('.ficha-card [name]').forEach(function(c){
      if (c.type === 'checkbox'){ if (c.checked) datos[c.name] = '1'; return; }
      datos[c.name] = c.value;
    });
    if (!convertida) datos.conceptos = JSON.stringify(conceptos);
    return datos;
  }
  function guardar(){
    var btn = document.getElementById('btnGuardarFicha');
    btn.disabled = true;
    post('talos_guardar_oportunidad', datosFicha()).then(function(res){
      if (!res.success){ btn.disabled = false; toast(res.data && res.data.mensaje ? res.data.mensaje : 'No se pudo guardar.'); return; }
      window.location.href = fichaUrl;
    }).catch(function(){ btn.disabled = false; toast('No se pudo guardar.'); });
  }
  document.getElementById('btnGuardarFicha').addEventListener('click', function(){
    if (!foEmpresa.value){ toast('Elige la empresa.'); return; }
    if (!document.querySelector('[name="opportunity_name"]').value.trim()){ toast('Escribe el nombre de la oportunidad.'); return; }
    if (!convertida){
      for (var i = 0; i < conceptos.length; i++){
        if (!conceptos[i].servicio && !String(conceptos[i].descripcion).trim()){ toast('El concepto ' + (i + 1) + ' no tiene servicio ni descripción.'); return; }
      }
    }
    if (foEtapa.value === 'ganada' && etapaInicial !== 'ganada' && !convertida){
      var empresa = foEmpresa.options[foEmpresa.selectedIndex].text;
      var sinServicio = conceptos.filter(function(c){ return !c.servicio; }).length;
      var texto = 'Al guardarla como Ganada se crea' + (conceptos.length === 1 ? '' : 'n') + ' ' + conceptos.length + ' servicio' + (conceptos.length === 1 ? '' : 's') + ' contratado' + (conceptos.length === 1 ? '' : 's') + ' en ' + empresa
        + ' (activos, con inicio hoy) y la empresa pasa a Client. Después, los conceptos ya no se editan aquí.';
      if (sinServicio) texto += ' Ojo: ' + sinServicio + ' concepto' + (sinServicio === 1 ? ' no tiene' : 's no tienen') + ' servicio del catálogo.';
      confirmar('¿Marcar como Ganada?', texto, 'Sí, ganada', 'var(--success)').then(guardar);
      return;
    }
    guardar();
  });

  // ===== Eliminar (papelera) =====
  document.getElementById('btnEliminarFicha').addEventListener('click', function(){
    var texto = 'Se moverá a la papelera de WordPress; podrás restaurarla desde ahí si fue un error.';
    if (convertida) texto += ' Los servicios que ya creó en la Empresa se quedan.';
    confirmar('¿Eliminar esta oportunidad?', texto, 'Sí, eliminar', 'var(--danger)').then(function(){
      post('talos_eliminar_oportunidad', { oportunidad_id: fichaId }).then(function(res){
        if (!res.success){ toast(res.data && res.data.mensaje ? res.data.mensaje : 'No se pudo eliminar.'); return; }
        window.location.href = '<?php echo esc_js( home_url( '/oportunidades/' ) ); ?>';
      });
    });
  });
}catch(e){ console.error(e); }
</script>

<?php get_footer(); ?>
