<?php
/**
 * Empresas — directorio de clientes/leads/aliados (talos_company).
 * ?ficha=ID muestra la ficha completa (Ver/Editar) en vez del listado.
 * company_socials (redes sociales) se queda fuera de la ficha por ahora —
 * sigue editándose desde wp-admin, es un repeater y no justifica el esfuerzo
 * de un editor en línea todavía. Servicios contratados (talos_contract,
 * antes un repeater, migrado a CPT propio) se muestra, y el Director los da
 * de alta / edita / cancela desde la ficha (modal, ajax-empresas.php). Precio se
 * oculta para quien no es Director (manage_options): el rol Consulta debe
 * ver QUÉ está contratado, no CUÁNTO se cobra — pedido explícito de Jorge
 * desde que se diseñó el control de acceso, aplicado aquí desde el día 1
 * en vez de retrofit después.
 *
 * company_state/company_class/company_status/etc. se guardan como slug
 * (return_format "value" en ACF), por eso necesitan mapas de etiquetas.
 */

$talos_estados_mx = array(
    'aguascalientes'=>'Aguascalientes','baja_california'=>'Baja California','baja_california_sur'=>'Baja California Sur',
    'campeche'=>'Campeche','chiapas'=>'Chiapas','chihuahua'=>'Chihuahua','ciudad_de_mexico'=>'Ciudad de México',
    'coahuila'=>'Coahuila','colima'=>'Colima','durango'=>'Durango','estado_de_mexico'=>'Estado de México',
    'guanajuato'=>'Guanajuato','guerrero'=>'Guerrero','hidalgo'=>'Hidalgo','jalisco'=>'Jalisco','michoacan'=>'Michoacán',
    'morelos'=>'Morelos','nayarit'=>'Nayarit','nuevo_leon'=>'Nuevo León','oaxaca'=>'Oaxaca','puebla'=>'Puebla',
    'queretaro'=>'Querétaro','quintana_roo'=>'Quintana Roo','san_luis_potosi'=>'San Luis Potosí','sinaloa'=>'Sinaloa',
    'sonora'=>'Sonora','tabasco'=>'Tabasco','tamaulipas'=>'Tamaulipas','tlaxcala'=>'Tlaxcala','veracruz'=>'Veracruz',
    'yucatan'=>'Yucatán','zacatecas'=>'Zacatecas',
);
$talos_clase_labels = array( 'client'=>'Client', 'opportunity'=>'Open Opportunity', 'lead'=>'Lead', 'partner'=>'Partner', 'nfs'=>'NFS' );
$talos_estatus_labels = array( 'active'=>'Active', 'inactive'=>'Inactive' );
$talos_sector_labels = array(
    'articulos_de_lujo_y_joyas'=>'Artículos de lujo y joyas','asesor_inmobiliario'=>'Asesor Inmobiliario','bienes_de_consumo'=>'Bienes de consumo',
    'bienes_raices'=>'Bienes Raíces','construccion'=>'Construcción','consultoria'=>'Consultoría','deportes'=>'Deportes',
    'e_learning'=>'E-Learning','educacion'=>'Educación','empresa_padre'=>'Empresa Padre','entretenimiento'=>'Entretenimiento',
    'formacion_profesional_y_coaching'=>'Formación profesional y coaching','ingenieria_industrial_o_mecanica'=>'Ingeniería industrial o mecánica',
    'marketing_y_publicidad'=>'Marketing y publicidad','material_y_productos_quimicos'=>'Material y Productos Químicos',
    'materiales_de_construccion'=>'Materiales de construcción','ocio_viajes_y_turismo'=>'Ocio, viajes y turismo','ong'=>'ONG',
    'produccion_de_alimentos'=>'Producción de alimentos','produccion_de_medios'=>'Producción de medios','productos_agricolas'=>'Productos Agrícolas',
    'productos_quimicos'=>'Productos Químicos','recursos_humanos'=>'Recursos humanos','sector_hotelero'=>'Sector hotelero',
    'seguridad_publica'=>'Seguridad pública','servicios_financieros'=>'Servicios financieros','servicios_juridicos'=>'Servicios jurídicos','veterinaria'=>'Veterinaria',
);
$talos_regimen_fiscal_labels = array(
    '601'=>'601 - General de Ley Personas Morales','603'=>'603 - Personas Morales con Fines no Lucrativos',
    '605'=>'605 - Sueldos y Salarios e Ingresos Asimilados a Salarios','606'=>'606 - Arrendamiento',
    '607'=>'607 - Régimen de Enajenación o Adquisición de Bienes','608'=>'608 - Demás ingresos',
    '611'=>'611 - Ingresos por Dividendos (socios y accionistas)','612'=>'612 - Personas Físicas con Actividades Empresariales y Profesionales',
    '614'=>'614 - Ingresos por intereses','615'=>'615 - Régimen de los ingresos por obtención de premios','616'=>'616 - Sin obligaciones fiscales',
    '620'=>'620 - Sociedades Cooperativas de Producción que optan por diferir sus ingresos','621'=>'621 - Incorporación Fiscal',
    '622'=>'622 - Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras','623'=>'623 - Opcional para Grupos de Sociedades',
    '624'=>'624 - Coordinados','625'=>'625 - Régimen de las Actividades Empresariales con ingresos a través de Plataformas Tecnológicas',
    '626'=>'626 - Régimen Simplificado de Confianza (RESICO)',
);
$talos_cfdi_labels = array(
    'G01'=>'G01 - Adquisición de mercancías','G02'=>'G02 - Devoluciones, descuentos o bonificaciones','G03'=>'G03 - Gastos en general',
    'I01'=>'I01 - Construcciones','I02'=>'I02 - Mobiliario y equipo de oficina por inversiones','I03'=>'I03 - Equipo de transporte',
    'I04'=>'I04 - Equipo de computo y accesorios','I05'=>'I05 - Dados, troqueles, moldes, matrices y herramental',
    'I06'=>'I06 - Comunicaciones telefónicas','I07'=>'I07 - Comunicaciones satelitales','I08'=>'I08 - Otra maquinaria y equipo',
    'D01'=>'D01 - Honorarios médicos, dentales y gastos hospitalarios','D02'=>'D02 - Gastos médicos por incapacidad o discapacidad',
    'D03'=>'D03 - Gastos funerales','D04'=>'D04 - Donativos','D05'=>'D05 - Intereses reales efectivamente pagados por créditos hipotecarios',
    'D06'=>'D06 - Aportaciones voluntarias al SAR','D07'=>'D07 - Primas por seguros de gastos médicos',
    'D08'=>'D08 - Gastos de transportación escolar obligatoria','D09'=>'D09 - Depósitos en cuentas para el ahorro, planes de pensiones',
    'D10'=>'D10 - Pagos por servicios educativos (colegiaturas)','S01'=>'S01 - Sin efectos fiscales','CP01'=>'CP01 - Pagos','CN01'=>'CN01 - Nómina',
);
$talos_pais_labels = array(
    'mexico'=>'México','estados_unidos'=>'Estados Unidos','espana'=>'España','argentina'=>'Argentina','colombia'=>'Colombia',
    'chile'=>'Chile','peru'=>'Perú','ecuador'=>'Ecuador','guatemala'=>'Guatemala','costa_rica'=>'Costa Rica','canada'=>'Canadá','otro'=>'Otro',
);
// Mismas choices que service_ref_frequency en Servicios — aquí describe la
// frecuencia con la que se factura cada fila de company_services.
/** Fecha ACF cruda (Ymd) -> valor de <input type="date"> (Y-m-d). */
function talos_emp_fecha_input( $ymd ) {
    $d = preg_replace( '/\D/', '', (string) $ymd );
    return 8 === strlen( $d ) ? substr( $d, 0, 4 ) . '-' . substr( $d, 4, 2 ) . '-' . substr( $d, 6, 2 ) : '';
}

$talos_frecuencia_labels_empresa = array(
    'pago_unico'=>'Pago Único','por_hora'=>'Por Hora','semanal'=>'Semanal','quincenal'=>'Quincenal',
    'mensual'=>'Mensual','bimestral'=>'Bimestral','trimestral'=>'Trimestral','semestral'=>'Semestral','anual'=>'Anual',
);

function talos_empresas_dominio( $url ) {
    $url = trim( (string) $url );
    if ( '' === $url ) return '';
    $host = wp_parse_url( $url, PHP_URL_HOST );
    if ( ! $host ) $host = $url;
    return preg_replace( '/^www\./i', '', $host );
}

/** <input type="date"> espera Y-m-d. get_field() ya regresa Y-m-d (return_format del campo). */
function talos_empresas_fecha_input( $valor ) {
    return $valor ? substr( (string) $valor, 0, 10 ) : '';
}

$talos_ficha_id = isset( $_GET['ficha'] ) ? (int) $_GET['ficha'] : 0;
if ( $talos_ficha_id && 'talos_company' !== get_post_type( $talos_ficha_id ) ) {
    $talos_ficha_id = 0;
}
$talos_ficha_editando = $talos_ficha_id && isset( $_GET['editar'] );

$talos_empresas_todas = get_posts( array(
    'post_type'      => 'talos_company',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
) );

// talos_company es jerárquico (subcuentas = empresa hija con post_parent). Para que
// una subcuenta no aparezca suelta en medio del alfabeto, se agrupa justo debajo de
// su matriz en vez de ordenar todo por título de forma plana.
$talos_empresas_hijas_de = array();
$talos_empresas_matrices = array();
foreach ( $talos_empresas_todas as $id ) {
    $padre_id = wp_get_post_parent_id( $id );
    if ( $padre_id && in_array( $padre_id, $talos_empresas_todas, true ) ) {
        $talos_empresas_hijas_de[ $padre_id ][] = $id;
    } else {
        $talos_empresas_matrices[] = $id;
    }
}
$talos_ordenar_por_titulo = function ( $a, $b ) { return strcasecmp( get_the_title( $a ), get_the_title( $b ) ); };
usort( $talos_empresas_matrices, $talos_ordenar_por_titulo );
foreach ( $talos_empresas_hijas_de as &$talos_hijas ) {
    usort( $talos_hijas, $talos_ordenar_por_titulo );
}
unset( $talos_hijas );

$talos_empresas_ids = array();
foreach ( $talos_empresas_matrices as $matriz_id ) {
    $talos_empresas_ids[] = $matriz_id;
    foreach ( $talos_empresas_hijas_de[ $matriz_id ] ?? array() as $hija_id ) {
        $talos_empresas_ids[] = $hija_id;
    }
}

// Rol Consulta: solo ve las empresas donde es el Account Manager asignado —
// "Listado de empresas, solo pueden ver las que tienen asignadas" (diseño del
// Dashboard personalizado, aplica igual aquí en /empresas/).
if ( ! current_user_can( 'manage_options' ) ) {
    $talos_mi_id_empresas = talos_mi_team_post_id();
    $talos_empresas_ids = array_values( array_filter( $talos_empresas_ids, function ( $id ) use ( $talos_mi_id_empresas ) {
        $am = get_field( 'company_account_manager', $id );
        $am_id = ( $am instanceof WP_Post ) ? $am->ID : 0;
        return $am_id && $am_id === $talos_mi_id_empresas;
    } ) );
}

$talos_total_empresas = count( $talos_empresas_ids );
$talos_clientes_activos = 0;
$talos_leads_oportunidad = 0;
$talos_inactivas = 0;
foreach ( $talos_empresas_ids as $id ) {
    $clase   = get_field( 'company_class', $id );
    $estatus = get_field( 'company_status', $id );
    if ( 'client' === $clase && 'active' === $estatus ) $talos_clientes_activos++;
    if ( in_array( $clase, array( 'lead', 'opportunity' ), true ) ) $talos_leads_oportunidad++;
    if ( 'inactive' === $estatus ) $talos_inactivas++;
}

if ( $talos_ficha_id ) {
    $talos_equipo_para_am = get_posts( array(
        'post_type' => 'talos_team', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC',
    ) );
    $f_am = get_field( 'company_account_manager', $talos_ficha_id );
    $f = array(
        'nombre'          => get_the_title( $talos_ficha_id ),
        'company_status'  => get_field( 'company_status', $talos_ficha_id ),
        'company_class'   => get_field( 'company_class', $talos_ficha_id ),
        'am_id'           => ( $f_am instanceof WP_Post ) ? $f_am->ID : 0,
        'sector'          => get_field( 'sector', $talos_ficha_id ),
        'company_start_op'=> get_field( 'company_start_op', $talos_ficha_id ),
        'company_end_op'  => get_field( 'company_end_op', $talos_ficha_id ),
        'company_legal_name' => get_field( 'company_legal_name', $talos_ficha_id ),
        'company_rfc'     => get_field( 'company_rfc', $talos_ficha_id ),
        'company_zip'     => get_field( 'company_zip', $talos_ficha_id ),
        'company_state'   => get_field( 'company_state', $talos_ficha_id ),
        'company_tax_regime' => get_field( 'company_tax_regime', $talos_ficha_id ),
        'company_cfdi'    => get_field( 'company_cfdi', $talos_ficha_id ),
        'company_country' => get_field( 'company_country', $talos_ficha_id ),
        'company_address' => get_field( 'company_address', $talos_ficha_id ),
        'company_website' => get_field( 'company_website', $talos_ficha_id ),
        'company_phone'   => get_field( 'company_phone', $talos_ficha_id ),
        'company_whatsapp'=> get_field( 'company_whatsapp', $talos_ficha_id ),
    );

    // Conteo de dependientes, solo para avisar antes de eliminar — no bloquea nada.
    $talos_ficha_contactos_n = count( get_posts( array(
        'post_type' => 'talos_contact', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids',
        'meta_query' => array( array( 'key' => 'contact_company', 'value' => $talos_ficha_id ) ),
    ) ) );
    $talos_ficha_oportunidades_n = count( get_posts( array(
        'post_type' => 'talos_opportunity', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids',
        'meta_query' => array( array( 'key' => 'opportunity_company', 'value' => $talos_ficha_id ) ),
    ) ) );
    $talos_ficha_ingresos_n = count( get_posts( array(
        'post_type' => 'talos_income', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids',
        'meta_query' => array( array( 'key' => 'income_company', 'value' => $talos_ficha_id ) ),
    ) ) );

    // Servicios contratados (talos_contract, antes repeater, migrado a CPT
    // propio). Precio/costo solo se muestran a Director (manage_options) —
    // el rol Consulta debe ver QUÉ está contratado, no CUÁNTO se cobra. Los
    // valores crudos ('datos') alimentan el modal de edición.
    $talos_ficha_servicios = array();
    $talos_posts_servicios = get_posts( array(
        'post_type' => 'talos_contract', 'post_status' => 'publish', 'posts_per_page' => -1,
        'meta_query' => array( array( 'key' => 'service_company', 'value' => $talos_ficha_id ) ),
    ) );
    foreach ( $talos_posts_servicios as $talos_servicio_post ) {
        $item = get_field( 'service_item', $talos_servicio_post->ID );
        $talos_ficha_servicios[] = array(
            'nombre'      => ( $item instanceof WP_Post ) ? $item->post_title : ( get_field( 'service_invoice_description', $talos_servicio_post->ID ) ?: 'Servicio' ),
            'frecuencia'  => get_field( 'service_frequency', $talos_servicio_post->ID ) ?: '',
            'precio'      => (float) get_field( 'service_price', $talos_servicio_post->ID ),
            'estatus'     => ! empty( get_field( 'service_status', $talos_servicio_post->ID ) ),
            'inicio'      => get_field( 'service_start_date', $talos_servicio_post->ID ) ?: '',
            'fin'         => get_field( 'service_end_date', $talos_servicio_post->ID ) ?: '',
            'datos'       => array(
                'id'           => $talos_servicio_post->ID,
                'servicio'     => (int) get_field( 'service_item', $talos_servicio_post->ID, false ),
                'descripcion'  => (string) get_field( 'service_invoice_description', $talos_servicio_post->ID ),
                'frecuencia'   => (string) get_field( 'service_frequency', $talos_servicio_post->ID ),
                'precio'       => (string) get_field( 'service_price', $talos_servicio_post->ID, false ),
                'costo'        => (string) get_field( 'service_cost', $talos_servicio_post->ID, false ),
                'iva'          => (bool) get_field( 'service_applies_iva', $talos_servicio_post->ID ),
                'diferido'     => (bool) get_field( 'service_deferred', $talos_servicio_post->ID ),
                'meses'        => (string) get_field( 'service_deferred_months', $talos_servicio_post->ID, false ),
                'inicio'       => talos_emp_fecha_input( get_field( 'service_start_date', $talos_servicio_post->ID, false ) ),
                'fin'          => talos_emp_fecha_input( get_field( 'service_end_date', $talos_servicio_post->ID, false ) ),
                'activo'       => (bool) get_field( 'service_status', $talos_servicio_post->ID ),
                'recordatorio' => (bool) get_field( 'service_reminder_allowed', $talos_servicio_post->ID ),
            ),
        );
    }
    // Activos primero
    usort( $talos_ficha_servicios, function ( $a, $b ) { return (int) $b['estatus'] - (int) $a['estatus']; } );
    $talos_catalogo_servicios = get_posts( array( 'post_type' => 'talos_service_cat', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
}

get_header();
?>

<style>
  .company-name.is-subcuenta{margin-left:22px;}
  .ficha-section-label{font-size:11px;text-transform:uppercase;letter-spacing:.08em;font-weight:800;color:var(--text-muted);margin:26px 0 10px;}
  .ficha-section-label:first-of-type{margin-top:0;}
  .ficha-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px 22px;margin-bottom:4px;}
  .ficha-modo-vista .solo-editando{display:none;}
  body.editando .ficha-modo-vista .solo-vista{display:none;}
  body.editando .ficha-modo-vista .solo-editando{display:inline-flex;}
</style>

<?php if ( $talos_ficha_id ) : ?>

  <a class="back-link" href="<?php echo esc_url( remove_query_arg( array( 'ficha', 'editar' ) ) ); ?>"><svg viewBox="0 0 24 24"><use href="#i-chevron-left"/></svg>Volver al listado</a>

  <div class="page-head ficha-modo-vista">
    <div>
      <h1 class="page-title"><?php echo esc_html( $f['nombre'] ); ?></h1>
      <p class="page-sub">Ficha de Empresa</p>
    </div>
    <?php if ( current_user_can( 'manage_options' ) ) : ?>
    <div class="head-controls">
      <button type="button" class="btn-primary solo-vista" id="btnEditarFicha"><svg viewBox="0 0 24 24"><use href="#i-edit"/></svg>Editar</button>
      <button type="button" class="btn-ghost solo-editando" id="btnCancelarFicha">Cancelar</button>
      <button type="button" class="btn-confirm solo-editando" id="btnGuardarFicha">Guardar Cambios</button>
    </div>
    <?php endif; ?>
  </div>

  <div class="ficha-card">
    <div class="ficha-section-label">Clasificación</div>
    <div class="data-grid">
      <div class="data-field">
        <label>Nombre</label>
        <div class="view-value"><?php echo esc_html( $f['nombre'] ); ?></div>
        <input type="text" name="nombre" value="<?php echo esc_attr( $f['nombre'] ); ?>">
      </div>
      <div class="data-field">
        <label>Estatus</label>
        <div class="view-value"><?php echo esc_html( $f['company_status'] ? ( $talos_estatus_labels[ $f['company_status'] ] ?? $f['company_status'] ) : '—' ); ?></div>
        <select name="company_status">
          <?php foreach ( $talos_estatus_labels as $val => $label ) : ?>
            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $val, $f['company_status'] ); ?>><?php echo esc_html( $label ); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="data-field">
        <label>Clase</label>
        <div class="view-value"><?php echo esc_html( $f['company_class'] ? ( $talos_clase_labels[ $f['company_class'] ] ?? $f['company_class'] ) : '—' ); ?></div>
        <select name="company_class">
          <?php foreach ( $talos_clase_labels as $val => $label ) : ?>
            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $val, $f['company_class'] ); ?>><?php echo esc_html( $label ); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="data-field">
        <label>Account Manager</label>
        <div class="view-value"><?php echo esc_html( $f['am_id'] ? get_the_title( $f['am_id'] ) : '—' ); ?></div>
        <select name="company_account_manager">
          <option value="">— Sin asignar —</option>
          <?php foreach ( $talos_equipo_para_am as $miembro ) : ?>
            <option value="<?php echo esc_attr( $miembro->ID ); ?>" <?php selected( $miembro->ID, $f['am_id'] ); ?>><?php echo esc_html( $miembro->post_title ); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="data-field">
        <label>Sector</label>
        <div class="view-value"><?php echo esc_html( $f['sector'] ? ( $talos_sector_labels[ $f['sector'] ] ?? $f['sector'] ) : '—' ); ?></div>
        <select name="sector">
          <option value="">— Sin definir —</option>
          <?php foreach ( $talos_sector_labels as $val => $label ) : ?>
            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $val, $f['sector'] ); ?>><?php echo esc_html( $label ); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="data-field">
        <label>Fecha de Alta</label>
        <div class="view-value"><?php echo esc_html( $f['company_start_op'] ?: '—' ); ?></div>
        <input type="date" name="company_start_op" value="<?php echo esc_attr( talos_empresas_fecha_input( $f['company_start_op'] ) ); ?>">
      </div>
      <div class="data-field">
        <label>Fecha de Suspensión</label>
        <div class="view-value"><?php echo esc_html( $f['company_end_op'] ?: '—' ); ?></div>
        <input type="date" name="company_end_op" value="<?php echo esc_attr( talos_empresas_fecha_input( $f['company_end_op'] ) ); ?>">
      </div>
    </div>
  </div>

  <div class="ficha-card">
    <div class="ficha-section-label">Datos Fiscales</div>
    <div class="data-grid">
      <div class="data-field full">
        <label>Razón Social</label>
        <div class="view-value"><?php echo esc_html( $f['company_legal_name'] ?: '—' ); ?></div>
        <input type="text" name="company_legal_name" value="<?php echo esc_attr( $f['company_legal_name'] ); ?>">
      </div>
      <div class="data-field">
        <label>RFC</label>
        <div class="view-value"><?php echo esc_html( $f['company_rfc'] ?: '—' ); ?></div>
        <input type="text" name="company_rfc" value="<?php echo esc_attr( $f['company_rfc'] ); ?>">
      </div>
      <div class="data-field">
        <label>Código Postal Fiscal</label>
        <div class="view-value"><?php echo esc_html( $f['company_zip'] ?: '—' ); ?></div>
        <input type="number" name="company_zip" value="<?php echo esc_attr( $f['company_zip'] ); ?>">
      </div>
      <div class="data-field">
        <label>Entidad Federativa</label>
        <div class="view-value"><?php echo esc_html( $f['company_state'] ? ( $talos_estados_mx[ $f['company_state'] ] ?? $f['company_state'] ) : '—' ); ?></div>
        <select name="company_state">
          <option value="">— Sin definir —</option>
          <?php foreach ( $talos_estados_mx as $val => $label ) : ?>
            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $val, $f['company_state'] ); ?>><?php echo esc_html( $label ); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="data-field">
        <label>Régimen Fiscal</label>
        <div class="view-value"><?php echo esc_html( $f['company_tax_regime'] ? ( $talos_regimen_fiscal_labels[ $f['company_tax_regime'] ] ?? $f['company_tax_regime'] ) : '—' ); ?></div>
        <select name="company_tax_regime">
          <option value="">— Sin definir —</option>
          <?php foreach ( $talos_regimen_fiscal_labels as $val => $label ) : ?>
            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $val, $f['company_tax_regime'] ); ?>><?php echo esc_html( $label ); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="data-field">
        <label>Uso de CFDI</label>
        <div class="view-value"><?php echo esc_html( $f['company_cfdi'] ? ( $talos_cfdi_labels[ $f['company_cfdi'] ] ?? $f['company_cfdi'] ) : '—' ); ?></div>
        <select name="company_cfdi">
          <option value="">— Sin definir —</option>
          <?php foreach ( $talos_cfdi_labels as $val => $label ) : ?>
            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $val, $f['company_cfdi'] ); ?>><?php echo esc_html( $label ); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>

  <div class="ficha-card">
    <div class="ficha-section-label">Contacto y Ubicación</div>
    <div class="data-grid">
      <div class="data-field">
        <label>País</label>
        <div class="view-value"><?php echo esc_html( $f['company_country'] ? ( $talos_pais_labels[ $f['company_country'] ] ?? $f['company_country'] ) : '—' ); ?></div>
        <select name="company_country">
          <?php foreach ( $talos_pais_labels as $val => $label ) : ?>
            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $val, $f['company_country'] ); ?>><?php echo esc_html( $label ); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="data-field full">
        <label>Dirección Física</label>
        <div class="view-value"><?php echo esc_html( $f['company_address'] ?: '—' ); ?></div>
        <input type="text" name="company_address" value="<?php echo esc_attr( $f['company_address'] ); ?>">
      </div>
      <div class="data-field">
        <label>Sitio Web</label>
        <div class="view-value"><?php echo esc_html( $f['company_website'] ?: '—' ); ?></div>
        <input type="url" name="company_website" value="<?php echo esc_attr( $f['company_website'] ); ?>" placeholder="https://">
      </div>
      <div class="data-field">
        <label>Teléfono Oficina</label>
        <div class="view-value"><?php echo esc_html( $f['company_phone'] ?: '—' ); ?></div>
        <input type="text" name="company_phone" value="<?php echo esc_attr( $f['company_phone'] ); ?>">
      </div>
      <div class="data-field">
        <label>WhatsApp Oficina</label>
        <div class="view-value"><?php echo esc_html( $f['company_whatsapp'] ?: '—' ); ?></div>
        <input type="text" name="company_whatsapp" value="<?php echo esc_attr( $f['company_whatsapp'] ); ?>">
      </div>
    </div>
    <p class="field-hint" style="margin-top:16px;">Los perfiles de redes sociales todavía se editan desde wp-admin.</p>
  </div>

  <div class="ficha-card">
    <div class="ficha-section-label">Servicios Contratados</div>
    <?php if ( empty( $talos_ficha_servicios ) ) : ?>
      <p class="field-hint">Sin servicios contratados registrados.</p>
    <?php else : ?>
      <div class="table-card" style="box-shadow:none;">
        <div class="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Servicio</th>
                <th>Frecuencia</th>
                <th>Vigencia</th>
                <?php if ( current_user_can( 'manage_options' ) ) : ?><th class="num">Precio</th><?php endif; ?>
                <th>Estatus</th>
                <?php if ( current_user_can( 'manage_options' ) ) : ?><th></th><?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ( $talos_ficha_servicios as $s ) : ?>
                <tr>
                  <td><?php echo esc_html( $s['nombre'] ); ?></td>
                  <td><?php echo esc_html( $talos_frecuencia_labels_empresa[ $s['frecuencia'] ] ?? ( $s['frecuencia'] ?: '—' ) ); ?></td>
                  <td><?php echo esc_html( ( $s['inicio'] ?: '—' ) . ( $s['fin'] ? ' – ' . $s['fin'] : '' ) ); ?></td>
                  <?php if ( current_user_can( 'manage_options' ) ) : ?><td class="num tabular"><?php echo esc_html( talos_fmt_mxn( $s['precio'] ) ); ?></td><?php endif; ?>
                  <td><span class="pill status-<?php echo $s['estatus'] ? 'active' : 'inactive'; ?>"><span class="pill-dot"></span><?php echo $s['estatus'] ? 'Activo' : 'Inactivo'; ?></span></td>
                  <?php if ( current_user_can( 'manage_options' ) ) : ?><td><button type="button" class="action-btn edit" data-role="editar-contrato" data-contrato="<?php echo esc_attr( wp_json_encode( $s['datos'] ) ); ?>" title="Editar servicio"><svg viewBox="0 0 24 24"><use href="#i-edit"/></svg></button></td><?php endif; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
    <?php if ( current_user_can( 'manage_options' ) ) : ?>
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:16px;flex-wrap:wrap;">
        <p class="field-hint" style="margin:0;">Para cancelar un servicio, edítalo y quita "Activo" o ponle fecha de fin. Generar Mes cobra solo los activos.</p>
        <button type="button" class="btn-secondary" id="btnAgregarContrato"><svg viewBox="0 0 24 24"><use href="#i-plus"/></svg>Agregar servicio</button>
      </div>
    <?php endif; ?>
  </div>

  <?php if ( current_user_can( 'manage_options' ) ) : ?>
  <div class="modal-overlay" id="modalContrato">
    <div class="modal-box" style="max-width:560px;">
      <h4 id="modalContratoTitulo">Agregar servicio</h4>
      <p class="modal-sub">Lo que Generar Mes le cobra a <?php echo esc_html( $f['nombre'] ); ?> cada periodo.</p>
      <div class="form-field">
        <label for="ctServicio">Servicio del catálogo</label>
        <select id="ctServicio">
          <option value="">Selecciona…</option>
          <?php foreach ( $talos_catalogo_servicios as $svc ) : ?>
            <option value="<?php echo esc_attr( $svc->ID ); ?>"><?php echo esc_html( $svc->post_title ); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-field">
        <label for="ctDescripcion">Descripción en nota / factura</label>
        <input type="text" id="ctDescripcion" placeholder="Ej. Administración de campaña META">
      </div>
      <div class="form-row">
        <div class="form-field">
          <label for="ctFrecuencia">Frecuencia</label>
          <select id="ctFrecuencia">
            <?php foreach ( $talos_frecuencia_labels_empresa as $slug => $label ) : ?>
              <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $slug, 'mensual' ); ?>><?php echo esc_html( $label ); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-field">
          <label for="ctPrecio">Precio sin IVA (MXN)</label>
          <input type="number" id="ctPrecio" step="any" min="0" placeholder="0">
        </div>
        <div class="form-field">
          <label for="ctCosto">Costo (MXN)</label>
          <input type="number" id="ctCosto" step="any" min="0" placeholder="0">
        </div>
      </div>
      <div class="form-row">
        <div class="form-field">
          <label for="ctInicio">Fecha de inicio</label>
          <input type="date" id="ctInicio">
        </div>
        <div class="form-field">
          <label for="ctFin">Fecha de fin (opcional)</label>
          <input type="date" id="ctFin">
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px 16px;font-size:13px;margin:-2px 0 14px;">
        <label style="display:flex;gap:8px;align-items:center;cursor:pointer;"><input type="checkbox" id="ctIva"> Factura (+16% IVA)</label>
        <label style="display:flex;gap:8px;align-items:center;cursor:pointer;"><input type="checkbox" id="ctActivo" checked> Activo</label>
        <label style="display:flex;gap:8px;align-items:center;cursor:pointer;"><input type="checkbox" id="ctRecordatorio" checked> Recordatorios de cobranza</label>
        <label style="display:flex;gap:8px;align-items:center;cursor:pointer;"><input type="checkbox" id="ctDiferido"> Pago diferido</label>
      </div>
      <div class="form-field" id="ctMesesWrap" hidden>
        <label for="ctMeses">Meses del diferido</label>
        <input type="number" id="ctMeses" min="1" step="1" placeholder="Ej. 12">
        <span class="field-hint">El precio es el total del proyecto; cada mes se cobra precio ÷ meses, con la leyenda "Pago X de Y".</span>
      </div>
      <div class="modal-actions">
        <button class="btn-ghost" data-close-modal>Cancelar</button>
        <button class="btn-confirm" id="btnGuardarContrato">Guardar servicio</button>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="ficha-modo-vista" style="display:flex;justify-content:flex-end;margin-top:4px;">
    <button type="button" class="btn-danger solo-editando" id="btnEliminarEmpresa"><svg viewBox="0 0 24 24"><use href="#i-trash"/></svg>Eliminar Empresa</button>
  </div>

  <div class="modal-overlay" id="modalEliminarEmpresa">
    <div class="modal-box">
      <div class="modal-icon" style="background:var(--danger-soft);color:var(--danger);"><svg viewBox="0 0 24 24"><use href="#i-trash"/></svg></div>
      <h4>¿Eliminar "<?php echo esc_html( $f['nombre'] ); ?>"?</h4>
      <p>
        Se moverá a la papelera de WordPress (recuperable desde wp-admin, no es borrado permanente).
        <?php if ( $talos_ficha_contactos_n || $talos_ficha_oportunidades_n || $talos_ficha_ingresos_n ) : ?>
          <br><br>Esta empresa tiene registros relacionados que se quedarán sin empresa asignada:
          <br>
          <?php if ( $talos_ficha_contactos_n ) : ?>• <?php echo esc_html( $talos_ficha_contactos_n ); ?> contacto(s)<br><?php endif; ?>
          <?php if ( $talos_ficha_oportunidades_n ) : ?>• <?php echo esc_html( $talos_ficha_oportunidades_n ); ?> oportunidad(es)<br><?php endif; ?>
          <?php if ( $talos_ficha_ingresos_n ) : ?>• <?php echo esc_html( $talos_ficha_ingresos_n ); ?> ingreso(s)<br><?php endif; ?>
        <?php endif; ?>
      </p>
      <div class="modal-actions">
        <button class="btn-ghost" data-close-modal>Cancelar</button>
        <button class="btn-danger" id="btnConfirmarEliminarEmpresa">Sí, eliminar</button>
      </div>
    </div>
  </div>

<?php else : ?>

<div class="page-head">
  <div>
    <h1 class="page-title">Empresas</h1>
    <p class="page-sub"><?php echo current_user_can( 'manage_options' ) ? 'Directorio de clientes, leads y aliados' : 'Tus cuentas asignadas'; ?></p>
  </div>
  <?php if ( current_user_can( 'manage_options' ) ) : ?>
  <button class="btn-primary" id="btnNuevaEmpresa"><svg viewBox="0 0 24 24"><use href="#i-plus"/></svg>Nueva Empresa</button>
  <?php endif; ?>
</div>

<div class="count-strip">
  <div class="count-box accent"><span class="label">Total Empresas</span><span class="value tabular"><?php echo esc_html( $talos_total_empresas ); ?></span></div>
  <div class="count-box success"><span class="label">Clientes Activos</span><span class="value tabular"><?php echo esc_html( $talos_clientes_activos ); ?></span></div>
  <div class="count-box gold"><span class="label">Leads / Oportunidad</span><span class="value tabular"><?php echo esc_html( $talos_leads_oportunidad ); ?></span></div>
  <div class="count-box"><span class="label">Inactivas</span><span class="value tabular"><?php echo esc_html( $talos_inactivas ); ?></span></div>
</div>

<div class="filter-bar">
  <div class="filter-search"><svg viewBox="0 0 24 24"><use href="#i-search"/></svg><input type="text" id="buscarEmpresa" placeholder="Buscar por nombre…"></div>
  <div class="seg" data-filter-group="estatus">
    <button class="active" data-filter="todas">Todas</button>
    <button data-filter="active">Activas</button>
    <button data-filter="inactive">Inactivas</button>
  </div>
  <div class="seg" data-filter-group="clase">
    <button class="active" data-filter="todas">Cualquier Clase</button>
    <button data-filter="client">Client</button>
    <button data-filter="lead">Lead</button>
    <button data-filter="opportunity">Opportunity</button>
  </div>
  <div class="filter-spacer"></div>
</div>

<div class="table-card">
  <div class="table-scroll">
    <table id="companiesTable">
      <thead>
        <tr>
          <th>Empresa</th>
          <th data-sort-key="clase"><span class="th-flex">Clase<button class="sort-btn" data-sort-key="clase"><svg viewBox="0 0 24 24"><use href="#i-chevron"/></svg></button></span></th>
          <th data-sort-key="estatus"><span class="th-flex">Estatus<button class="sort-btn" data-sort-key="estatus"><svg viewBox="0 0 24 24"><use href="#i-chevron"/></svg></button></span></th>
          <th data-sort-key="am"><span class="th-flex">Account Manager<button class="sort-btn" data-sort-key="am"><svg viewBox="0 0 24 24"><use href="#i-chevron"/></svg></button></span></th>
          <th data-sort-key="rfc"><span class="th-flex">RFC<button class="sort-btn" data-sort-key="rfc"><svg viewBox="0 0 24 24"><use href="#i-chevron"/></svg></button></span></th>
          <th data-sort-key="estado"><span class="th-flex">Estado<button class="sort-btn" data-sort-key="estado"><svg viewBox="0 0 24 24"><use href="#i-chevron"/></svg></button></span></th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody id="companiesBody">
        <?php if ( empty( $talos_empresas_ids ) ) : ?>
          <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:30px;">Sin empresas registradas.</td></tr>
        <?php endif; ?>
        <?php foreach ( $talos_empresas_ids as $id ) :
            $nombre   = get_the_title( $id );
            $clase    = get_field( 'company_class', $id );
            $estatus  = get_field( 'company_status', $id );
            $rfc      = get_field( 'company_rfc', $id );
            $estado   = get_field( 'company_state', $id );
            $dominio  = talos_empresas_dominio( get_field( 'company_website', $id ) );
            $am       = get_field( 'company_account_manager', $id );
            $am_post  = ( $am instanceof WP_Post ) ? $am : null;
            $padre_id = wp_get_post_parent_id( $id );
            $es_subcuenta = $padre_id && in_array( $padre_id, $talos_empresas_todas, true );
            $clase_label   = $clase ? ( $talos_clase_labels[ $clase ] ?? $clase ) : '';
            $estatus_label = $estatus ? ( $talos_estatus_labels[ $estatus ] ?? $estatus ) : '';
            $estado_label  = $estado ? ( $talos_estados_mx[ $estado ] ?? $estado ) : '';
            ?>
            <tr data-estatus="<?php echo esc_attr( $estatus ); ?>" data-clase="<?php echo esc_attr( $clase ); ?>" data-nombre="<?php echo esc_attr( $nombre ); ?>"
                data-sort-clase="<?php echo esc_attr( $clase_label ); ?>"
                data-sort-estatus="<?php echo esc_attr( $estatus_label ); ?>"
                data-sort-am="<?php echo esc_attr( $am_post ? $am_post->post_title : '' ); ?>"
                data-sort-rfc="<?php echo esc_attr( $rfc ); ?>"
                data-sort-estado="<?php echo esc_attr( $estado_label ); ?>">
              <td>
                <div class="company-name<?php echo $es_subcuenta ? ' is-subcuenta' : ''; ?>">
                  <div class="company-logo"><?php echo esc_html( talos_iniciales( $nombre ) ); ?></div>
                  <div>
                    <?php echo esc_html( $nombre ); ?>
                    <?php if ( $es_subcuenta ) : ?><span class="company-sub">↳ Subcuenta de <?php echo esc_html( get_the_title( $padre_id ) ); ?></span><?php endif; ?>
                    <?php if ( $dominio ) : ?><span class="company-sub"><?php echo esc_html( $dominio ); ?></span><?php endif; ?>
                  </div>
                </div>
              </td>
              <td><?php if ( $clase ) : ?><span class="pill class-<?php echo esc_attr( $clase ); ?>"><?php echo esc_html( $clase_label ); ?></span><?php else : ?>—<?php endif; ?></td>
              <td><?php if ( $estatus ) : ?><span class="pill status-<?php echo esc_attr( $estatus ); ?>"><span class="pill-dot"></span><?php echo esc_html( $estatus_label ); ?></span><?php else : ?>—<?php endif; ?></td>
              <td>
                <?php if ( $am_post ) : ?>
                  <div class="am-cell"><div class="avatar-sm"><?php echo esc_html( talos_iniciales( $am_post->post_title ) ); ?></div><?php echo esc_html( $am_post->post_title ); ?></div>
                <?php else : ?>—<?php endif; ?>
              </td>
              <td>
                <?php if ( $rfc ) : ?>
                  <div class="rfc-cell"><span><?php echo esc_html( $rfc ); ?></span><button class="copy-btn" data-copy="<?php echo esc_attr( $rfc ); ?>" title="Copiar RFC"><svg viewBox="0 0 24 24"><use href="#i-copy"/></svg></button></div>
                <?php else : ?>—<?php endif; ?>
              </td>
              <td><?php echo esc_html( $estado_label ?: '—' ); ?></td>
              <td class="row-actions">
                <a class="action-btn view" href="<?php echo esc_url( add_query_arg( 'ficha', $id ) ); ?>" title="Ver ficha técnica"><svg viewBox="0 0 24 24"><use href="#i-eye"/></svg></a>
                <?php if ( current_user_can( 'manage_options' ) ) : ?>
                <a class="action-btn edit" href="<?php echo esc_url( add_query_arg( array( 'ficha' => $id, 'editar' => 1 ) ) ); ?>" title="Editar empresa"><svg viewBox="0 0 24 24"><use href="#i-edit"/></svg></a>
                <?php endif; ?>
              </td>
            </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ( current_user_can( 'manage_options' ) ) : ?>
<div class="modal-overlay" id="modalNuevaEmpresa">
  <div class="modal-box">
    <h4>Nueva Empresa</h4>
    <p class="modal-sub">Captura lo esencial — el resto lo completas justo después, en la ficha.</p>
    <div class="form-field">
      <label for="campoNombreEmpresa">Nombre</label>
      <input type="text" id="campoNombreEmpresa" placeholder="Ej. Inmobiliaria Amanecer">
    </div>
    <div class="form-row">
      <div class="form-field">
        <label for="campoClaseEmpresa">Clase</label>
        <select id="campoClaseEmpresa">
          <?php foreach ( $talos_clase_labels as $val => $label ) : ?>
            <option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-field">
        <label for="campoEstatusEmpresa">Estatus</label>
        <select id="campoEstatusEmpresa">
          <?php foreach ( $talos_estatus_labels as $val => $label ) : ?>
            <option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="modal-actions">
      <button class="btn-ghost" data-close-modal>Cancelar</button>
      <button class="btn-confirm" id="btnConfirmarNuevaEmpresa">Crear y Continuar</button>
    </div>
  </div>
</div>
<?php endif; ?>

<?php endif; ?>

<div class="toast" id="toastEmpresas"><svg viewBox="0 0 24 24"><use href="#i-check"/></svg><span id="toastEmpresasTexto"></span></div>

<script>
try{
  var talosNonceEmp = '<?php echo esc_js( wp_create_nonce( 'talos_empresas' ) ); ?>';
  var talosAjaxUrlEmp = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

  function mostrarToastEmpresas(texto){
    var toast = document.getElementById('toastEmpresas');
    document.getElementById('toastEmpresasTexto').textContent = texto;
    toast.classList.add('show');
    clearTimeout(toast._t);
    toast._t = setTimeout(function(){ toast.classList.remove('show'); }, 2600);
  }

  <?php if ( $talos_ficha_id ) : ?>
  // ===== Ficha: alternar vista/edición + guardar =====
  <?php if ( $talos_ficha_editando && current_user_can( 'manage_options' ) ) : ?>document.body.classList.add('editando');<?php endif; ?>

  <?php if ( current_user_can( 'manage_options' ) ) : ?>
  // ===== Servicios Contratados: agregar / editar (mismo modal) =====
  var modalContrato = document.getElementById('modalContrato');
  var contratoEditando = 0;
  function abrirModalContrato(d){
    contratoEditando = d ? d.id : 0;
    document.getElementById('modalContratoTitulo').textContent = d ? 'Editar servicio' : 'Agregar servicio';
    document.getElementById('ctServicio').value = d && d.servicio ? String(d.servicio) : '';
    document.getElementById('ctDescripcion').value = d ? d.descripcion : '';
    document.getElementById('ctFrecuencia').value = d && d.frecuencia ? d.frecuencia : 'mensual';
    document.getElementById('ctPrecio').value = d ? d.precio : '';
    document.getElementById('ctCosto').value = d ? d.costo : '';
    document.getElementById('ctInicio').value = d ? d.inicio : '';
    document.getElementById('ctFin').value = d ? d.fin : '';
    document.getElementById('ctIva').checked = d ? d.iva : false;
    document.getElementById('ctActivo').checked = d ? d.activo : true;
    document.getElementById('ctRecordatorio').checked = d ? d.recordatorio : true;
    document.getElementById('ctDiferido').checked = d ? d.diferido : false;
    document.getElementById('ctMeses').value = d ? d.meses : '';
    document.getElementById('ctMesesWrap').hidden = !document.getElementById('ctDiferido').checked;
    modalContrato.classList.add('show');
  }
  document.getElementById('btnAgregarContrato').addEventListener('click', function(){ abrirModalContrato(null); });
  document.querySelectorAll('[data-role="editar-contrato"]').forEach(function(btn){
    btn.addEventListener('click', function(){ abrirModalContrato(JSON.parse(btn.getAttribute('data-contrato'))); });
  });
  document.getElementById('ctDiferido').addEventListener('change', function(){ document.getElementById('ctMesesWrap').hidden = !this.checked; });
  modalContrato.querySelectorAll('[data-close-modal]').forEach(function(b){ b.addEventListener('click', function(){ modalContrato.classList.remove('show'); }); });
  document.getElementById('btnGuardarContrato').addEventListener('click', function(){
    var btn = this;
    var campos = {
      contrato_id: contratoEditando,
      empresa_id: <?php echo (int) $talos_ficha_id; ?>,
      servicio_id: document.getElementById('ctServicio').value,
      descripcion: document.getElementById('ctDescripcion').value.trim(),
      frecuencia: document.getElementById('ctFrecuencia').value,
      precio: document.getElementById('ctPrecio').value,
      costo: document.getElementById('ctCosto').value,
      inicio: document.getElementById('ctInicio').value,
      fin: document.getElementById('ctFin').value,
      iva: document.getElementById('ctIva').checked ? '1' : '',
      activo: document.getElementById('ctActivo').checked ? '1' : '',
      recordatorio: document.getElementById('ctRecordatorio').checked ? '1' : '',
      diferido: document.getElementById('ctDiferido').checked ? '1' : '',
      meses: document.getElementById('ctMeses').value
    };
    if (!campos.servicio_id){ mostrarToastEmpresas('Elige el servicio del catálogo.'); return; }
    if (campos.precio === '' || parseFloat(campos.precio) < 0){ mostrarToastEmpresas('Escribe el precio.'); return; }
    if (!campos.inicio){ mostrarToastEmpresas('Elige la fecha de inicio.'); return; }
    if (campos.diferido && !(parseInt(campos.meses, 10) > 0)){ mostrarToastEmpresas('Escribe los meses del diferido.'); return; }
    var form = new FormData();
    form.append('action', 'talos_guardar_contrato');
    form.append('nonce', talosNonceEmp);
    Object.keys(campos).forEach(function(k){ form.append(k, campos[k]); });
    btn.disabled = true;
    fetch(talosAjaxUrlEmp, { method: 'POST', body: form, credentials: 'same-origin' }).then(function(r){ return r.json(); }).then(function(res){
      btn.disabled = false;
      if (!res.success){ mostrarToastEmpresas(res.data && res.data.mensaje ? res.data.mensaje : 'No se pudo guardar el servicio.'); return; }
      window.location.reload();
    }).catch(function(){ btn.disabled = false; mostrarToastEmpresas('No se pudo guardar el servicio.'); });
  });

  document.getElementById('btnEditarFicha').addEventListener('click', function(){
    document.body.classList.add('editando');
  });
  document.getElementById('btnCancelarFicha').addEventListener('click', function(){
    window.location.href = window.location.pathname + '?ficha=<?php echo (int) $talos_ficha_id; ?>';
  });
  document.getElementById('btnGuardarFicha').addEventListener('click', function(){
    var btn = this;
    btn.disabled = true;
    var form = new FormData();
    form.append('action', 'talos_guardar_empresa');
    form.append('nonce', talosNonceEmp);
    form.append('empresa_id', '<?php echo (int) $talos_ficha_id; ?>');
    document.querySelectorAll('.data-field input, .data-field select').forEach(function(campo){
      if (campo.name) form.append(campo.name, campo.value);
    });
    fetch(talosAjaxUrlEmp, { method: 'POST', body: form, credentials: 'same-origin' }).then(function(r){ return r.json(); }).then(function(res){
      if (!res.success){
        mostrarToastEmpresas(res.data && res.data.mensaje ? res.data.mensaje : 'No se pudo guardar.');
        btn.disabled = false;
        return;
      }
      window.location.href = window.location.pathname + '?ficha=<?php echo (int) $talos_ficha_id; ?>';
    });
  });
  <?php endif; ?>

  // Eliminar (papelera, recuperable desde wp-admin) — el botón sigue oculto por
  // CSS para Consulta (nunca entra a editando), este listener no causa nada.
  var modalEliminarEmpresa = document.getElementById('modalEliminarEmpresa');
  document.getElementById('btnEliminarEmpresa').addEventListener('click', function(){
    modalEliminarEmpresa.classList.add('show');
  });
  modalEliminarEmpresa.querySelectorAll('[data-close-modal]').forEach(function(b){ b.addEventListener('click', function(){ modalEliminarEmpresa.classList.remove('show'); }); });
  modalEliminarEmpresa.addEventListener('click', function(e){ if (e.target === modalEliminarEmpresa) modalEliminarEmpresa.classList.remove('show'); });
  document.getElementById('btnConfirmarEliminarEmpresa').addEventListener('click', function(){
    var btn = this;
    btn.disabled = true;
    var form = new FormData();
    form.append('action', 'talos_eliminar_empresa');
    form.append('nonce', talosNonceEmp);
    form.append('empresa_id', '<?php echo (int) $talos_ficha_id; ?>');
    fetch(talosAjaxUrlEmp, { method: 'POST', body: form, credentials: 'same-origin' }).then(function(r){ return r.json(); }).then(function(res){
      if (!res.success){
        mostrarToastEmpresas(res.data && res.data.mensaje ? res.data.mensaje : 'No se pudo eliminar.');
        btn.disabled = false;
        return;
      }
      window.location.href = window.location.pathname;
    });
  });

  <?php else : ?>
  // ===== Listado: filtros + buscador + copiado de RFC + Nueva Empresa =====
  function aplicarFiltrosEmpresas(){
    var estatus = document.querySelector('[data-filter-group="estatus"] button.active').getAttribute('data-filter');
    var clase = document.querySelector('[data-filter-group="clase"] button.active').getAttribute('data-filter');
    var texto = (document.getElementById('buscarEmpresa').value || '').trim().toLowerCase();
    document.querySelectorAll('#companiesBody tr').forEach(function(row){
      if (!row.hasAttribute('data-nombre')) return;
      var okEstatus = (estatus === 'todas') || (row.getAttribute('data-estatus') === estatus);
      var okClase = (clase === 'todas') || (row.getAttribute('data-clase') === clase);
      var okTexto = !texto || (row.getAttribute('data-nombre') || '').toLowerCase().indexOf(texto) !== -1;
      row.hidden = !(okEstatus && okClase && okTexto);
    });
  }
  document.querySelectorAll('.seg').forEach(function(seg){
    seg.querySelectorAll('button').forEach(function(btn){
      btn.addEventListener('click', function(){
        seg.querySelectorAll('button').forEach(function(b){b.classList.remove('active');});
        btn.classList.add('active');
        aplicarFiltrosEmpresas();
      });
    });
  });
  document.getElementById('buscarEmpresa').addEventListener('input', aplicarFiltrosEmpresas);

  document.querySelectorAll('.copy-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
      var valor = btn.getAttribute('data-copy');
      var hacerFeedback = function(){
        btn.classList.add('copied');
        mostrarToastEmpresas('RFC "' + valor + '" copiado al portapapeles');
        setTimeout(function(){ btn.classList.remove('copied'); }, 1500);
      };
      if (navigator.clipboard && navigator.clipboard.writeText){
        navigator.clipboard.writeText(valor).then(hacerFeedback).catch(hacerFeedback);
      } else {
        hacerFeedback();
      }
    });
  });

  <?php if ( current_user_can( 'manage_options' ) ) : ?>
  var modalNuevaEmpresa = document.getElementById('modalNuevaEmpresa');
  document.getElementById('btnNuevaEmpresa').addEventListener('click', function(){
    document.getElementById('campoNombreEmpresa').value = '';
    modalNuevaEmpresa.classList.add('show');
  });
  modalNuevaEmpresa.querySelectorAll('[data-close-modal]').forEach(function(b){ b.addEventListener('click', function(){ modalNuevaEmpresa.classList.remove('show'); }); });
  modalNuevaEmpresa.addEventListener('click', function(e){ if (e.target === modalNuevaEmpresa) modalNuevaEmpresa.classList.remove('show'); });

  document.getElementById('btnConfirmarNuevaEmpresa').addEventListener('click', function(){
    var nombre = document.getElementById('campoNombreEmpresa').value.trim();
    if (!nombre){ mostrarToastEmpresas('Captura el nombre de la empresa'); return; }
    var btn = this;
    btn.disabled = true;
    var form = new FormData();
    form.append('action', 'talos_crear_empresa');
    form.append('nonce', talosNonceEmp);
    form.append('nombre', nombre);
    form.append('clase', document.getElementById('campoClaseEmpresa').value);
    form.append('estatus', document.getElementById('campoEstatusEmpresa').value);
    fetch(talosAjaxUrlEmp, { method: 'POST', body: form, credentials: 'same-origin' }).then(function(r){ return r.json(); }).then(function(res){
      if (!res.success){
        mostrarToastEmpresas(res.data && res.data.mensaje ? res.data.mensaje : 'No se pudo crear la empresa.');
        btn.disabled = false;
        return;
      }
      window.location.href = window.location.pathname + '?ficha=' + res.data.id + '&editar=1';
    });
  });
  <?php endif; ?>
  <?php endif; ?>
}catch(e){ console.error(e); }
</script>

<?php get_footer(); ?>
