<?php
/**
 * Contactos — personas de contacto en cada Empresa (talos_contact).
 * ?ficha=ID muestra la ficha completa (Ver/Editar) en vez del listado —
 * mismo patrón ya establecido en Empresas (page-empresas.php).
 * A diferencia de Empresas, estos campos ACF están planos desde el inicio
 * (sin Group), así que get_field() normal no tiene el riesgo ya visto ahí.
 * Columna "Recibe Facturas" (contact_billing_recipient) deliberadamente fuera
 * de la TABLA — Jorge pidió quitarla en la fase de diseño — pero sí vive en
 * la ficha, ahí sí es información útil de editar.
 */

$talos_contacto_clase_labels = array( 'client'=>'Client', 'opportunity'=>'Open Opportunity', 'lead'=>'Lead', 'partner'=>'Partner', 'nfs'=>'NFS' );
$talos_contacto_estatus_labels = array( 'active'=>'Active', 'inactive'=>'Inactive' );
$talos_contacto_cargo_labels = array(
    'ceo_fundador'=>'CEO / Fundador','director_general'=>'Director General','director_marketing'=>'Director de Marketing (CMO)',
    'director_ventas'=>'Director de Ventas (CSO)','gerente_marketing'=>'Gerente de Marketing','gerente_ventas'=>'Gerente de Ventas',
    'gerente_sistemas'=>'Gerente de TI / Sistemas','ejecutivo_cuenta'=>'Ejecutivo de Cuenta','asistente_direccion'=>'Asistente de Dirección',
    'recursos_humanos'=>'Recursos Humanos','compras_facturacion'=>'Compras / Facturación','broker_owner'=>'Broker Owner',
    'realtor'=>'Realtor','owners_assistant'=>"Owner's Assistant",'manager'=>'Manager','marketing'=>'Marketing','it'=>'IT / Sistemas',
    'accounting'=>'Contabilidad','sales'=>'Ventas','admin'=>'Administración','otro'=>'Otro',
);

$talos_ficha_id = isset( $_GET['ficha'] ) ? (int) $_GET['ficha'] : 0;
if ( $talos_ficha_id && 'talos_contact' !== get_post_type( $talos_ficha_id ) ) {
    $talos_ficha_id = 0;
}
$talos_ficha_editando = $talos_ficha_id && isset( $_GET['editar'] );

$talos_contactos_ids = get_posts( array(
    'post_type'      => 'talos_contact',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'orderby'        => 'title',
    'order'          => 'ASC',
) );

$talos_total_contactos = count( $talos_contactos_ids );
$talos_contactos_activos = 0;
$talos_contactos_inactivos = 0;
foreach ( $talos_contactos_ids as $id ) {
    $estatus = get_field( 'contact_status', $id );
    if ( 'active' === $estatus ) $talos_contactos_activos++;
    if ( 'inactive' === $estatus ) $talos_contactos_inactivos++;
}

$talos_empresas_para_select = get_posts( array(
    'post_type' => 'talos_company', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC',
) );

if ( $talos_ficha_id ) {
    $f_empresa = get_field( 'contact_company', $talos_ficha_id );
    $f = array(
        'nombre'                   => get_the_title( $talos_ficha_id ),
        'empresa_id'               => ( $f_empresa instanceof WP_Post ) ? $f_empresa->ID : 0,
        'contact_status'           => get_field( 'contact_status', $talos_ficha_id ),
        'contact_class'            => get_field( 'contact_class', $talos_ficha_id ),
        'contact_firstname'        => get_field( 'contact_firstname', $talos_ficha_id ),
        'contact_lastname'         => get_field( 'contact_lastname', $talos_ficha_id ),
        'contact_role'             => get_field( 'contact_role', $talos_ficha_id ),
        'contact_company_email'    => get_field( 'contact_company_email', $talos_ficha_id ),
        'contact_personal_email'   => get_field( 'contact_personal_email', $talos_ficha_id ),
        'contact_phone_office'     => get_field( 'contact_phone_office', $talos_ficha_id ),
        'contact_phone_mobile'     => get_field( 'contact_phone_mobile', $talos_ficha_id ),
        'contact_billing_recipient'=> get_field( 'contact_billing_recipient', $talos_ficha_id ),
    );

    $talos_ficha_oportunidades_n = count( get_posts( array(
        'post_type' => 'talos_opportunity', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids',
        'meta_query' => array( array( 'key' => 'quote_contact', 'value' => $talos_ficha_id ) ),
    ) ) );
}

get_header();
?>

<style>
  .count-strip{grid-template-columns:repeat(3,minmax(0,1fr));}
  .ficha-section-label{font-size:11px;text-transform:uppercase;letter-spacing:.08em;font-weight:800;color:var(--text-muted);margin:26px 0 10px;}
  .ficha-section-label:first-of-type{margin-top:0;}
  .ficha-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px 22px;margin-bottom:4px;}
  .ficha-modo-vista .solo-editando{display:none;}
  body.editando .ficha-modo-vista .solo-vista{display:none;}
  body.editando .ficha-modo-vista .solo-editando{display:inline-flex;}
  .data-field.checkbox{display:flex;flex-direction:row;align-items:center;gap:8px;}
  .data-field.checkbox label{margin-bottom:0;}
  .data-field.checkbox input[type="checkbox"]{display:none;width:auto;flex:none;}
  body.editando .data-field.checkbox input[type="checkbox"]{display:inline-block;}
  body.editando .data-field.checkbox .view-value{display:none;}
</style>

<?php if ( $talos_ficha_id ) : ?>

  <a class="back-link" href="<?php echo esc_url( remove_query_arg( array( 'ficha', 'editar' ) ) ); ?>"><svg viewBox="0 0 24 24"><use href="#i-chevron-left"/></svg>Volver al listado</a>

  <div class="page-head ficha-modo-vista">
    <div>
      <h1 class="page-title"><?php echo esc_html( $f['nombre'] ); ?></h1>
      <p class="page-sub">Ficha de Contacto</p>
    </div>
    <div class="head-controls">
      <button type="button" class="btn-primary solo-vista" id="btnEditarFicha"><svg viewBox="0 0 24 24"><use href="#i-edit"/></svg>Editar</button>
      <button type="button" class="btn-ghost solo-editando" id="btnCancelarFicha">Cancelar</button>
      <button type="button" class="btn-confirm solo-editando" id="btnGuardarFicha">Guardar Cambios</button>
    </div>
  </div>

  <div class="ficha-card">
    <div class="ficha-section-label">Datos Generales</div>
    <div class="data-grid">
      <div class="data-field">
        <label>Nombre</label>
        <div class="view-value"><?php echo esc_html( $f['nombre'] ); ?></div>
        <input type="text" name="nombre" value="<?php echo esc_attr( $f['nombre'] ); ?>">
      </div>
      <div class="data-field">
        <label>Apellidos</label>
        <div class="view-value"><?php echo esc_html( $f['contact_lastname'] ?: '—' ); ?></div>
        <input type="text" name="contact_lastname" value="<?php echo esc_attr( $f['contact_lastname'] ); ?>">
      </div>
      <div class="data-field">
        <label>Nombre (s) — ACF</label>
        <div class="view-value"><?php echo esc_html( $f['contact_firstname'] ?: '—' ); ?></div>
        <input type="text" name="contact_firstname" value="<?php echo esc_attr( $f['contact_firstname'] ); ?>">
      </div>
      <div class="data-field">
        <label>Empresa</label>
        <div class="view-value"><?php echo esc_html( $f['empresa_id'] ? get_the_title( $f['empresa_id'] ) : '—' ); ?></div>
        <select name="contact_company">
          <option value="">— Sin asignar —</option>
          <?php foreach ( $talos_empresas_para_select as $empresa ) : ?>
            <option value="<?php echo esc_attr( $empresa->ID ); ?>" <?php selected( $empresa->ID, $f['empresa_id'] ); ?>><?php echo esc_html( $empresa->post_title ); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="data-field">
        <label>Cargo</label>
        <div class="view-value"><?php echo esc_html( $f['contact_role'] ? ( $talos_contacto_cargo_labels[ $f['contact_role'] ] ?? $f['contact_role'] ) : '—' ); ?></div>
        <select name="contact_role">
          <option value="">— Sin definir —</option>
          <?php foreach ( $talos_contacto_cargo_labels as $val => $label ) : ?>
            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $val, $f['contact_role'] ); ?>><?php echo esc_html( $label ); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="data-field">
        <label>Estatus</label>
        <div class="view-value"><?php echo esc_html( $f['contact_status'] ? ( $talos_contacto_estatus_labels[ $f['contact_status'] ] ?? $f['contact_status'] ) : '—' ); ?></div>
        <select name="contact_status">
          <?php foreach ( $talos_contacto_estatus_labels as $val => $label ) : ?>
            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $val, $f['contact_status'] ); ?>><?php echo esc_html( $label ); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="data-field">
        <label>Clase</label>
        <div class="view-value"><?php echo esc_html( $f['contact_class'] ? ( $talos_contacto_clase_labels[ $f['contact_class'] ] ?? $f['contact_class'] ) : '—' ); ?></div>
        <select name="contact_class">
          <?php foreach ( $talos_contacto_clase_labels as $val => $label ) : ?>
            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $val, $f['contact_class'] ); ?>><?php echo esc_html( $label ); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>

  <div class="ficha-card">
    <div class="ficha-section-label">Contacto</div>
    <div class="data-grid">
      <div class="data-field">
        <label>Correo Empresa</label>
        <div class="view-value"><?php echo esc_html( $f['contact_company_email'] ?: '—' ); ?></div>
        <input type="email" name="contact_company_email" value="<?php echo esc_attr( $f['contact_company_email'] ); ?>">
      </div>
      <div class="data-field">
        <label>Correo Personal</label>
        <div class="view-value"><?php echo esc_html( $f['contact_personal_email'] ?: '—' ); ?></div>
        <input type="email" name="contact_personal_email" value="<?php echo esc_attr( $f['contact_personal_email'] ); ?>">
      </div>
      <div class="data-field">
        <label>Teléfono Oficina</label>
        <div class="view-value"><?php echo esc_html( $f['contact_phone_office'] ?: '—' ); ?></div>
        <input type="text" name="contact_phone_office" value="<?php echo esc_attr( $f['contact_phone_office'] ); ?>">
      </div>
      <div class="data-field">
        <label>Teléfono Celular</label>
        <div class="view-value"><?php echo esc_html( $f['contact_phone_mobile'] ?: '—' ); ?></div>
        <input type="text" name="contact_phone_mobile" value="<?php echo esc_attr( $f['contact_phone_mobile'] ); ?>">
      </div>
      <div class="data-field checkbox full">
        <label style="margin-bottom:0;">Recibe Facturas y Cotizaciones</label>
        <div class="view-value"><?php echo $f['contact_billing_recipient'] ? 'Sí' : 'No'; ?></div>
        <input type="checkbox" name="contact_billing_recipient" value="1" <?php checked( (bool) $f['contact_billing_recipient'] ); ?>>
      </div>
    </div>
  </div>

  <div class="ficha-modo-vista" style="display:flex;justify-content:flex-end;margin-top:4px;">
    <button type="button" class="btn-danger solo-editando" id="btnEliminarContacto"><svg viewBox="0 0 24 24"><use href="#i-trash"/></svg>Eliminar Contacto</button>
  </div>

  <div class="modal-overlay" id="modalEliminarContacto">
    <div class="modal-box">
      <div class="modal-icon" style="background:var(--danger-soft);color:var(--danger);"><svg viewBox="0 0 24 24"><use href="#i-trash"/></svg></div>
      <h4>¿Eliminar "<?php echo esc_html( $f['nombre'] ); ?>"?</h4>
      <p>
        Se moverá a la papelera de WordPress (recuperable desde wp-admin, no es borrado permanente).
        <?php if ( $talos_ficha_oportunidades_n ) : ?>
          <br><br>Este contacto está asignado como contacto de cotización en <?php echo esc_html( $talos_ficha_oportunidades_n ); ?> oportunidad(es), que se quedarán sin contacto asignado.
        <?php endif; ?>
      </p>
      <div class="modal-actions">
        <button class="btn-ghost" data-close-modal>Cancelar</button>
        <button class="btn-danger" id="btnConfirmarEliminarContacto">Sí, eliminar</button>
      </div>
    </div>
  </div>

<?php else : ?>

<div class="page-head">
  <div>
    <h1 class="page-title">Contactos</h1>
    <p class="page-sub">Personas de contacto en cada Empresa</p>
  </div>
  <button class="btn-primary" id="btnNuevoContacto"><svg viewBox="0 0 24 24"><use href="#i-plus"/></svg>Nuevo Contacto</button>
</div>

<div class="count-strip">
  <div class="count-box accent"><span class="label">Total Contactos</span><span class="value tabular"><?php echo esc_html( $talos_total_contactos ); ?></span></div>
  <div class="count-box success"><span class="label">Activos</span><span class="value tabular"><?php echo esc_html( $talos_contactos_activos ); ?></span></div>
  <div class="count-box"><span class="label">Inactivos</span><span class="value tabular"><?php echo esc_html( $talos_contactos_inactivos ); ?></span></div>
</div>

<div class="filter-bar">
  <div class="filter-search"><svg viewBox="0 0 24 24"><use href="#i-search"/></svg><input type="text" id="buscarContacto" placeholder="Buscar por nombre o empresa…"></div>
  <div class="seg" data-filter-group="estatus">
    <button class="active" data-filter="todos">Todos</button>
    <button data-filter="active">Activos</button>
    <button data-filter="inactive">Inactivos</button>
  </div>
  <div class="seg" data-filter-group="clase">
    <button class="active" data-filter="todos">Cualquier Clase</button>
    <button data-filter="client">Client</button>
    <button data-filter="lead">Lead</button>
  </div>
  <div class="filter-spacer"></div>
</div>

<div class="table-card">
  <div class="table-scroll">
    <table id="contactsTable">
      <thead>
        <tr>
          <th data-sort-key="contacto"><span class="th-flex">Contacto<button class="sort-btn" data-sort-key="contacto"><svg viewBox="0 0 24 24"><use href="#i-chevron"/></svg></button></span></th>
          <th data-sort-key="empresa"><span class="th-flex">Empresa<button class="sort-btn" data-sort-key="empresa"><svg viewBox="0 0 24 24"><use href="#i-chevron"/></svg></button></span></th>
          <th data-sort-key="correo"><span class="th-flex">Correo<button class="sort-btn" data-sort-key="correo"><svg viewBox="0 0 24 24"><use href="#i-chevron"/></svg></button></span></th>
          <th data-sort-key="telefono"><span class="th-flex">Teléfono<button class="sort-btn" data-sort-key="telefono"><svg viewBox="0 0 24 24"><use href="#i-chevron"/></svg></button></span></th>
          <th data-sort-key="whatsapp"><span class="th-flex">WhatsApp<button class="sort-btn" data-sort-key="whatsapp"><svg viewBox="0 0 24 24"><use href="#i-chevron"/></svg></button></span></th>
          <th data-sort-key="clase"><span class="th-flex">Clase<button class="sort-btn" data-sort-key="clase"><svg viewBox="0 0 24 24"><use href="#i-chevron"/></svg></button></span></th>
          <th data-sort-key="estatus"><span class="th-flex">Estatus<button class="sort-btn" data-sort-key="estatus"><svg viewBox="0 0 24 24"><use href="#i-chevron"/></svg></button></span></th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody id="contactsBody">
        <?php if ( empty( $talos_contactos_ids ) ) : ?>
          <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:30px;">Sin contactos registrados.</td></tr>
        <?php endif; ?>
        <?php foreach ( $talos_contactos_ids as $id ) :
            $nombre    = get_the_title( $id );
            $clase     = get_field( 'contact_class', $id );
            $estatus   = get_field( 'contact_status', $id );
            $cargo     = get_field( 'contact_role', $id );
            $correo    = get_field( 'contact_company_email', $id ) ?: get_field( 'contact_personal_email', $id );
            $tel_oficina = get_field( 'contact_phone_office', $id );
            $tel_celular = get_field( 'contact_phone_mobile', $id );
            $empresa   = get_field( 'contact_company', $id );
            $empresa_post = ( $empresa instanceof WP_Post ) ? $empresa : null;
            $nombre_filtro = $nombre . ' ' . ( $empresa_post ? $empresa_post->post_title : '' );
            $clase_label   = $clase ? ( $talos_contacto_clase_labels[ $clase ] ?? $clase ) : '';
            $estatus_label = $estatus ? ( $talos_contacto_estatus_labels[ $estatus ] ?? $estatus ) : '';
            ?>
            <tr data-estatus="<?php echo esc_attr( $estatus ); ?>" data-clase="<?php echo esc_attr( $clase ); ?>" data-nombre="<?php echo esc_attr( $nombre_filtro ); ?>"
                data-sort-contacto="<?php echo esc_attr( $nombre ); ?>"
                data-sort-empresa="<?php echo esc_attr( $empresa_post ? $empresa_post->post_title : '' ); ?>"
                data-sort-correo="<?php echo esc_attr( $correo ); ?>"
                data-sort-telefono="<?php echo esc_attr( $tel_oficina ); ?>"
                data-sort-whatsapp="<?php echo esc_attr( $tel_celular ); ?>"
                data-sort-clase="<?php echo esc_attr( $clase_label ); ?>"
                data-sort-estatus="<?php echo esc_attr( $estatus_label ); ?>">
              <td>
                <div class="person">
                  <div class="person-ava"><?php echo esc_html( talos_iniciales( $nombre ) ); ?></div>
                  <div>
                    <div class="person-name"><?php echo esc_html( $nombre ); ?></div>
                    <?php if ( $cargo ) : ?><span class="person-sub"><?php echo esc_html( $talos_contacto_cargo_labels[ $cargo ] ?? $cargo ); ?></span><?php endif; ?>
                  </div>
                </div>
              </td>
              <td><?php echo esc_html( $empresa_post ? $empresa_post->post_title : '—' ); ?></td>
              <td><?php if ( $correo ) : ?><a class="email-link" href="mailto:<?php echo esc_attr( $correo ); ?>"><?php echo esc_html( $correo ); ?></a><?php else : ?>—<?php endif; ?></td>
              <td>
                <?php if ( $tel_oficina ) : ?>
                  <div class="rfc-cell"><span><?php echo esc_html( $tel_oficina ); ?></span><button class="copy-btn" data-copy="<?php echo esc_attr( $tel_oficina ); ?>" title="Copiar teléfono"><svg viewBox="0 0 24 24"><use href="#i-copy"/></svg></button></div>
                <?php else : ?>—<?php endif; ?>
              </td>
              <td>
                <?php if ( $tel_celular ) : ?>
                  <div class="rfc-cell"><span><?php echo esc_html( $tel_celular ); ?></span><button class="copy-btn" data-copy="<?php echo esc_attr( $tel_celular ); ?>" title="Copiar WhatsApp"><svg viewBox="0 0 24 24"><use href="#i-copy"/></svg></button></div>
                <?php else : ?>—<?php endif; ?>
              </td>
              <td><?php if ( $clase ) : ?><span class="pill class-<?php echo esc_attr( $clase ); ?>"><?php echo esc_html( $clase_label ); ?></span><?php else : ?>—<?php endif; ?></td>
              <td><?php if ( $estatus ) : ?><span class="pill status-<?php echo esc_attr( $estatus ); ?>"><span class="pill-dot"></span><?php echo esc_html( $estatus_label ); ?></span><?php else : ?>—<?php endif; ?></td>
              <td class="row-actions">
                <a class="action-btn view" href="<?php echo esc_url( add_query_arg( 'ficha', $id ) ); ?>" title="Ver datos"><svg viewBox="0 0 24 24"><use href="#i-eye"/></svg></a>
                <a class="action-btn edit" href="<?php echo esc_url( add_query_arg( array( 'ficha' => $id, 'editar' => 1 ) ) ); ?>" title="Editar contacto"><svg viewBox="0 0 24 24"><use href="#i-edit"/></svg></a>
              </td>
            </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="modalNuevoContacto">
  <div class="modal-box">
    <h4>Nuevo Contacto</h4>
    <p class="modal-sub">Captura lo esencial — el resto lo completas justo después, en la ficha.</p>
    <div class="form-field">
      <label for="campoNombreContacto">Nombre</label>
      <input type="text" id="campoNombreContacto" placeholder="Ej. Fernanda Ríos">
    </div>
    <div class="form-field">
      <label for="campoEmpresaContacto">Empresa</label>
      <select id="campoEmpresaContacto">
        <option value="" disabled selected>Selecciona una empresa…</option>
        <?php foreach ( $talos_empresas_para_select as $empresa ) : ?>
          <option value="<?php echo esc_attr( $empresa->ID ); ?>"><?php echo esc_html( $empresa->post_title ); ?></option>
        <?php endforeach; ?>
      </select>
      <span class="field-hint">¿No está en la lista? Primero créala en Empresas.</span>
    </div>
    <div class="form-field">
      <label for="campoEstatusContacto">Estatus</label>
      <select id="campoEstatusContacto">
        <?php foreach ( $talos_contacto_estatus_labels as $val => $label ) : ?>
          <option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="modal-actions">
      <button class="btn-ghost" data-close-modal>Cancelar</button>
      <button class="btn-confirm" id="btnConfirmarNuevoContacto">Crear y Continuar</button>
    </div>
  </div>
</div>

<?php endif; ?>

<div class="toast" id="toastContactos"><svg viewBox="0 0 24 24"><use href="#i-check"/></svg><span id="toastContactosTexto"></span></div>

<script>
try{
  var talosNonceCon = '<?php echo esc_js( wp_create_nonce( 'talos_contactos' ) ); ?>';
  var talosAjaxUrlCon = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

  function mostrarToastContactos(texto){
    var toast = document.getElementById('toastContactos');
    document.getElementById('toastContactosTexto').textContent = texto;
    toast.classList.add('show');
    clearTimeout(toast._t);
    toast._t = setTimeout(function(){ toast.classList.remove('show'); }, 2600);
  }

  <?php if ( $talos_ficha_id ) : ?>
  // ===== Ficha: alternar vista/edición + guardar + eliminar =====
  <?php if ( $talos_ficha_editando ) : ?>document.body.classList.add('editando');<?php endif; ?>

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
    form.append('action', 'talos_guardar_contacto');
    form.append('nonce', talosNonceCon);
    form.append('contacto_id', '<?php echo (int) $talos_ficha_id; ?>');
    document.querySelectorAll('.data-field input, .data-field select').forEach(function(campo){
      if (!campo.name) return;
      if (campo.type === 'checkbox'){ if (campo.checked) form.append(campo.name, '1'); return; }
      form.append(campo.name, campo.value);
    });
    fetch(talosAjaxUrlCon, { method: 'POST', body: form, credentials: 'same-origin' }).then(function(r){ return r.json(); }).then(function(res){
      if (!res.success){
        mostrarToastContactos(res.data && res.data.mensaje ? res.data.mensaje : 'No se pudo guardar.');
        btn.disabled = false;
        return;
      }
      window.location.href = window.location.pathname + '?ficha=<?php echo (int) $talos_ficha_id; ?>';
    });
  });

  var modalEliminarContacto = document.getElementById('modalEliminarContacto');
  document.getElementById('btnEliminarContacto').addEventListener('click', function(){
    modalEliminarContacto.classList.add('show');
  });
  modalEliminarContacto.querySelectorAll('[data-close-modal]').forEach(function(b){ b.addEventListener('click', function(){ modalEliminarContacto.classList.remove('show'); }); });
  modalEliminarContacto.addEventListener('click', function(e){ if (e.target === modalEliminarContacto) modalEliminarContacto.classList.remove('show'); });
  document.getElementById('btnConfirmarEliminarContacto').addEventListener('click', function(){
    var btn = this;
    btn.disabled = true;
    var form = new FormData();
    form.append('action', 'talos_eliminar_contacto');
    form.append('nonce', talosNonceCon);
    form.append('contacto_id', '<?php echo (int) $talos_ficha_id; ?>');
    fetch(talosAjaxUrlCon, { method: 'POST', body: form, credentials: 'same-origin' }).then(function(r){ return r.json(); }).then(function(res){
      if (!res.success){
        mostrarToastContactos(res.data && res.data.mensaje ? res.data.mensaje : 'No se pudo eliminar.');
        btn.disabled = false;
        return;
      }
      window.location.href = window.location.pathname;
    });
  });

  <?php else : ?>
  // ===== Listado: filtros + buscador + copiado + Nuevo Contacto =====
  function aplicarFiltrosContactos(){
    var estatus = document.querySelector('[data-filter-group="estatus"] button.active').getAttribute('data-filter');
    var clase = document.querySelector('[data-filter-group="clase"] button.active').getAttribute('data-filter');
    var texto = (document.getElementById('buscarContacto').value || '').trim().toLowerCase();
    document.querySelectorAll('#contactsBody tr').forEach(function(row){
      if (!row.hasAttribute('data-nombre')) return;
      var okEstatus = (estatus === 'todos') || (row.getAttribute('data-estatus') === estatus);
      var okClase = (clase === 'todos') || (row.getAttribute('data-clase') === clase);
      var okTexto = !texto || (row.getAttribute('data-nombre') || '').toLowerCase().indexOf(texto) !== -1;
      row.hidden = !(okEstatus && okClase && okTexto);
    });
  }
  document.querySelectorAll('.seg').forEach(function(seg){
    seg.querySelectorAll('button').forEach(function(btn){
      btn.addEventListener('click', function(){
        seg.querySelectorAll('button').forEach(function(b){b.classList.remove('active');});
        btn.classList.add('active');
        aplicarFiltrosContactos();
      });
    });
  });
  document.getElementById('buscarContacto').addEventListener('input', aplicarFiltrosContactos);

  document.querySelectorAll('.copy-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
      var valor = btn.getAttribute('data-copy');
      var hacerFeedback = function(){
        btn.classList.add('copied');
        mostrarToastContactos('Número "' + valor + '" copiado al portapapeles');
        setTimeout(function(){ btn.classList.remove('copied'); }, 1500);
      };
      if (navigator.clipboard && navigator.clipboard.writeText){
        navigator.clipboard.writeText(valor).then(hacerFeedback).catch(hacerFeedback);
      } else {
        hacerFeedback();
      }
    });
  });

  var modalNuevoContacto = document.getElementById('modalNuevoContacto');
  document.getElementById('btnNuevoContacto').addEventListener('click', function(){
    document.getElementById('campoNombreContacto').value = '';
    modalNuevoContacto.classList.add('show');
  });
  modalNuevoContacto.querySelectorAll('[data-close-modal]').forEach(function(b){ b.addEventListener('click', function(){ modalNuevoContacto.classList.remove('show'); }); });
  modalNuevoContacto.addEventListener('click', function(e){ if (e.target === modalNuevoContacto) modalNuevoContacto.classList.remove('show'); });

  document.getElementById('btnConfirmarNuevoContacto').addEventListener('click', function(){
    var nombre = document.getElementById('campoNombreContacto').value.trim();
    var empresaId = document.getElementById('campoEmpresaContacto').value;
    if (!nombre || !empresaId){ mostrarToastContactos('Captura el nombre y selecciona una empresa'); return; }
    var btn = this;
    btn.disabled = true;
    var form = new FormData();
    form.append('action', 'talos_crear_contacto');
    form.append('nonce', talosNonceCon);
    form.append('nombre', nombre);
    form.append('empresa_id', empresaId);
    form.append('estatus', document.getElementById('campoEstatusContacto').value);
    fetch(talosAjaxUrlCon, { method: 'POST', body: form, credentials: 'same-origin' }).then(function(r){ return r.json(); }).then(function(res){
      if (!res.success){
        mostrarToastContactos(res.data && res.data.mensaje ? res.data.mensaje : 'No se pudo crear el contacto.');
        btn.disabled = false;
        return;
      }
      window.location.href = window.location.pathname + '?ficha=' + res.data.id + '&editar=1';
    });
  });
  <?php endif; ?>
}catch(e){ console.error(e); }
</script>

<?php get_footer(); ?>
