<?php
/**
 * Equipo — colaboradores de Once24 (talos_team).
 * team_status usa valores en español ("activo"/"inactivo", a diferencia de
 * company_status/contact_status que usan "active"/"inactive") — se traduce a
 * la clase CSS existente (.pill.status-active/.status-inactive) en PHP para
 * no tener que duplicar esas reglas. "Sueldo Actual" no es un campo plano:
 * se calcula como la fila más reciente de team_salary_history (repeater) —
 * de solo lectura en la ficha, para cambiar el sueldo se agrega una fila
 * nueva en ese repeater desde wp-admin.
 *
 * ?ficha=ID muestra la ficha completa (5 secciones: Datos Generales, Datos
 * de Contacto, Finanzas y Nómina, Datos Bancarios, Salida) — mismo patrón
 * de Empresas/Contactos/Servicios. Jorge aplanó TODO el field group de
 * Group a Tab (incluido el Datos Bancarios que originalmente quedó
 * anidado como Group — ver commit anterior) — bank_name/bank_account/
 * bank_clabe/bank_card_number son campos planos normales, sin truco
 * especial de guardado.
 */

$talos_equipo_rol_labels = array(
    'account_manager'=>'Account Manager','graphic_designer'=>'Graphic Designer',
    'programmer'=>'Programmer','manager'=>'Manager',
);
$talos_equipo_estatus_labels = array( 'activo'=>'Activo', 'inactivo'=>'Inactivo' );

function talos_equipo_sueldo_actual( $id ) {
    $historial = get_field( 'team_salary_history', $id );
    if ( empty( $historial ) || ! is_array( $historial ) ) return 0.0;
    $mas_reciente = null;
    $sueldo = 0.0;
    foreach ( $historial as $fila ) {
        $fecha = DateTime::createFromFormat( 'd/m/Y', $fila['salary_date'] ?? '' );
        if ( ! $fecha ) continue;
        if ( null === $mas_reciente || $fecha > $mas_reciente ) {
            $mas_reciente = $fecha;
            $sueldo = (float) ( $fila['salary_amount'] ?? 0 );
        }
    }
    return $sueldo;
}

function talos_equipo_fecha_corta( $ymd, $cortos ) {
    $partes = explode( '-', (string) $ymd );
    if ( count( $partes ) < 2 ) return '—';
    return $cortos[ (int) $partes[1] - 1 ] . '/' . $partes[0];
}

$talos_meses_cortos_eq = talos_meses_cortos();

$talos_ficha_id = isset( $_GET['ficha'] ) ? (int) $_GET['ficha'] : 0;
if ( $talos_ficha_id && 'talos_team' !== get_post_type( $talos_ficha_id ) ) {
    $talos_ficha_id = 0;
}
$talos_ficha_editando = $talos_ficha_id && isset( $_GET['editar'] );

$talos_equipo_ids = get_posts( array(
    'post_type'      => 'talos_team',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'orderby'        => 'title',
    'order'          => 'ASC',
) );

$talos_total_equipo = count( $talos_equipo_ids );
$talos_equipo_activos = 0;
$talos_equipo_inactivos = 0;
$talos_nomina_mensual = 0.0;
foreach ( $talos_equipo_ids as $id ) {
    $estatus = get_field( 'team_status', $id );
    $sueldo  = talos_equipo_sueldo_actual( $id );
    if ( 'activo' === $estatus ) {
        $talos_equipo_activos++;
        $talos_nomina_mensual += $sueldo;
    } elseif ( 'inactivo' === $estatus ) {
        $talos_equipo_inactivos++;
    }
}

if ( $talos_ficha_id ) {
    $f = array(
        'nombre'              => get_the_title( $talos_ficha_id ),
        'team_status'         => get_field( 'team_status', $talos_ficha_id ),
        'team_role'           => get_field( 'team_role', $talos_ficha_id ),
        'team_birthdate'      => get_field( 'team_birthdate', $talos_ficha_id ),
        'team_id_card'        => get_field( 'team_id_card', $talos_ficha_id ),
        'team_id_curp'        => get_field( 'team_id_curp', $talos_ficha_id ),
        'team_address'        => get_field( 'team_address', $talos_ficha_id ),
        'team_phone'          => get_field( 'team_phone', $talos_ficha_id ),
        'team_email_corp'     => get_field( 'team_email_corp', $talos_ficha_id ),
        'team_email_personal' => get_field( 'team_email_personal', $talos_ficha_id ),
        'team_social_fb'      => get_field( 'team_social_fb', $talos_ficha_id ),
        'team_social_ig'      => get_field( 'team_social_ig', $talos_ficha_id ),
        'team_start_date'     => get_field( 'team_start_date', $talos_ficha_id ),
        'team_end_date'       => get_field( 'team_end_date', $talos_ficha_id ),
        'team_exit_terms'     => get_field( 'team_exit_terms', $talos_ficha_id ),
        'bank_name'           => get_field( 'bank_name', $talos_ficha_id ),
        'bank_account'        => get_field( 'bank_account', $talos_ficha_id ),
        'bank_clabe'          => get_field( 'bank_clabe', $talos_ficha_id ),
        'bank_card_number'    => get_field( 'bank_card_number', $talos_ficha_id ),
        'sueldo_actual'       => talos_equipo_sueldo_actual( $talos_ficha_id ),
    );

    // Dependientes para el aviso de Eliminar: Empresas donde es el Account
    // Manager + Gastos de nómina donde es el beneficiario.
    $talos_ficha_empresas_n = count( get_posts( array(
        'post_type' => 'talos_company', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids',
        'meta_query' => array( array( 'key' => 'company_account_manager', 'value' => $talos_ficha_id ) ),
    ) ) );
    $talos_ficha_gastos_n = count( get_posts( array(
        'post_type' => 'talos_expense', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids',
        'meta_query' => array( array( 'key' => 'expense_team_member', 'value' => $talos_ficha_id ) ),
    ) ) );
}

get_header();
?>

<style>
  .ficha-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px 22px;margin-bottom:4px;}
  .ficha-section-label{font-size:11px;text-transform:uppercase;letter-spacing:.08em;font-weight:800;color:var(--text-muted);margin:26px 0 10px;}
  .ficha-section-label:first-of-type{margin-top:0;}
  .ficha-modo-vista .solo-editando{display:none;}
  body.editando .ficha-modo-vista .solo-vista{display:none;}
  body.editando .ficha-modo-vista .solo-editando{display:inline-flex;}
</style>

<?php if ( $talos_ficha_id ) : ?>

  <a class="back-link" href="<?php echo esc_url( remove_query_arg( array( 'ficha', 'editar' ) ) ); ?>"><svg viewBox="0 0 24 24"><use href="#i-chevron-left"/></svg>Volver al listado</a>

  <div class="page-head ficha-modo-vista">
    <div>
      <h1 class="page-title"><?php echo esc_html( $f['nombre'] ); ?></h1>
      <p class="page-sub">Ficha de Equipo</p>
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
        <label>Estatus</label>
        <div class="view-value"><?php echo esc_html( $f['team_status'] ? ( $talos_equipo_estatus_labels[ $f['team_status'] ] ?? $f['team_status'] ) : '—' ); ?></div>
        <select name="team_status">
          <?php foreach ( $talos_equipo_estatus_labels as $val => $label ) : ?>
            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $val, $f['team_status'] ); ?>><?php echo esc_html( $label ); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="data-field">
        <label>Puesto</label>
        <div class="view-value"><?php echo esc_html( $f['team_role'] ? ( $talos_equipo_rol_labels[ $f['team_role'] ] ?? $f['team_role'] ) : '—' ); ?></div>
        <select name="team_role">
          <option value="">— Sin definir —</option>
          <?php foreach ( $talos_equipo_rol_labels as $val => $label ) : ?>
            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $val, $f['team_role'] ); ?>><?php echo esc_html( $label ); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="data-field">
        <label>Fecha de Nacimiento</label>
        <div class="view-value"><?php echo esc_html( $f['team_birthdate'] ?: '—' ); ?></div>
        <input type="date" name="team_birthdate" value="<?php echo esc_attr( $f['team_birthdate'] ); ?>">
      </div>
      <div class="data-field">
        <label>Clave de Elector</label>
        <div class="view-value"><?php echo esc_html( $f['team_id_card'] ?: '—' ); ?></div>
        <input type="text" name="team_id_card" value="<?php echo esc_attr( $f['team_id_card'] ); ?>">
      </div>
      <div class="data-field">
        <label>CURP</label>
        <div class="view-value"><?php echo esc_html( $f['team_id_curp'] ?: '—' ); ?></div>
        <input type="text" name="team_id_curp" value="<?php echo esc_attr( $f['team_id_curp'] ); ?>">
      </div>
      <div class="data-field full">
        <label>Dirección</label>
        <div class="view-value"><?php echo esc_html( $f['team_address'] ?: '—' ); ?></div>
        <textarea name="team_address" rows="2"><?php echo esc_textarea( $f['team_address'] ); ?></textarea>
      </div>
    </div>
  </div>

  <div class="ficha-card">
    <div class="ficha-section-label">Datos de Contacto</div>
    <div class="data-grid">
      <div class="data-field">
        <label>Teléfono</label>
        <div class="view-value"><?php echo esc_html( $f['team_phone'] ?: '—' ); ?></div>
        <input type="text" name="team_phone" value="<?php echo esc_attr( $f['team_phone'] ); ?>">
      </div>
      <div class="data-field">
        <label>Correo Once24</label>
        <div class="view-value"><?php echo esc_html( $f['team_email_corp'] ?: '—' ); ?></div>
        <input type="email" name="team_email_corp" value="<?php echo esc_attr( $f['team_email_corp'] ); ?>">
      </div>
      <div class="data-field">
        <label>Correo Personal</label>
        <div class="view-value"><?php echo esc_html( $f['team_email_personal'] ?: '—' ); ?></div>
        <input type="email" name="team_email_personal" value="<?php echo esc_attr( $f['team_email_personal'] ); ?>">
      </div>
      <div class="data-field">
        <label>Facebook Personal</label>
        <div class="view-value"><?php echo esc_html( $f['team_social_fb'] ?: '—' ); ?></div>
        <input type="url" name="team_social_fb" value="<?php echo esc_attr( $f['team_social_fb'] ); ?>">
      </div>
      <div class="data-field">
        <label>Instagram Personal</label>
        <div class="view-value"><?php echo esc_html( $f['team_social_ig'] ?: '—' ); ?></div>
        <input type="url" name="team_social_ig" value="<?php echo esc_attr( $f['team_social_ig'] ); ?>">
      </div>
    </div>
  </div>

  <div class="ficha-card">
    <div class="ficha-section-label">Finanzas y Nómina</div>
    <div class="data-grid">
      <div class="data-field">
        <label>Fecha de Inicio</label>
        <div class="view-value"><?php echo esc_html( $f['team_start_date'] ?: '—' ); ?></div>
        <input type="date" name="team_start_date" value="<?php echo esc_attr( $f['team_start_date'] ); ?>">
      </div>
      <div class="data-field">
        <label>Sueldo Actual</label>
        <div class="view-value"><?php echo esc_html( $f['sueldo_actual'] > 0 ? talos_fmt_mxn( $f['sueldo_actual'] ) : '—' ); ?></div>
        <div class="view-value">Se agrega una fila nueva en el repeater "Historial de Sueldos" desde wp-admin — no editable aquí.</div>
      </div>
    </div>
  </div>

  <div class="ficha-card">
    <div class="ficha-section-label">Datos Bancarios</div>
    <div class="data-grid">
      <div class="data-field">
        <label>Banco</label>
        <div class="view-value"><?php echo esc_html( $f['bank_name'] ?: '—' ); ?></div>
        <input type="text" name="bank_name" value="<?php echo esc_attr( $f['bank_name'] ); ?>">
      </div>
      <div class="data-field">
        <label>Cuenta</label>
        <div class="view-value"><?php echo esc_html( $f['bank_account'] ?: '—' ); ?></div>
        <input type="text" name="bank_account" value="<?php echo esc_attr( $f['bank_account'] ); ?>">
      </div>
      <div class="data-field">
        <label>CLABE</label>
        <div class="view-value"><?php echo esc_html( $f['bank_clabe'] ?: '—' ); ?></div>
        <input type="text" name="bank_clabe" value="<?php echo esc_attr( $f['bank_clabe'] ); ?>">
      </div>
      <div class="data-field">
        <label>Tarjeta</label>
        <div class="view-value"><?php echo esc_html( $f['bank_card_number'] ?: '—' ); ?></div>
        <input type="text" name="bank_card_number" value="<?php echo esc_attr( $f['bank_card_number'] ); ?>">
      </div>
    </div>
  </div>

  <div class="ficha-card">
    <div class="ficha-section-label">Salida</div>
    <div class="data-grid">
      <div class="data-field">
        <label>Fecha de Salida</label>
        <div class="view-value"><?php echo esc_html( $f['team_end_date'] ?: '—' ); ?></div>
        <input type="date" name="team_end_date" value="<?php echo esc_attr( $f['team_end_date'] ); ?>">
      </div>
      <div class="data-field full">
        <label>Motivos de Salida</label>
        <div class="view-value"><?php echo esc_html( $f['team_exit_terms'] ?: '—' ); ?></div>
        <textarea name="team_exit_terms" rows="2"><?php echo esc_textarea( $f['team_exit_terms'] ); ?></textarea>
      </div>
    </div>
  </div>

  <div class="ficha-modo-vista" style="display:flex;justify-content:flex-end;margin-top:4px;">
    <button type="button" class="btn-danger solo-editando" id="btnEliminarMiembro"><svg viewBox="0 0 24 24"><use href="#i-trash"/></svg>Eliminar Miembro</button>
  </div>

  <div class="modal-overlay" id="modalEliminarMiembro">
    <div class="modal-box">
      <div class="modal-icon" style="background:var(--danger-soft);color:var(--danger);"><svg viewBox="0 0 24 24"><use href="#i-trash"/></svg></div>
      <h4>¿Eliminar a "<?php echo esc_html( $f['nombre'] ); ?>"?</h4>
      <p>
        Se moverá a la papelera de WordPress (recuperable desde wp-admin, no es borrado permanente).
        <?php if ( $talos_ficha_empresas_n || $talos_ficha_gastos_n ) : ?>
          <br><br>
          <?php if ( $talos_ficha_empresas_n ) : ?>Es Account Manager de <?php echo esc_html( $talos_ficha_empresas_n ); ?> empresa(s). <?php endif; ?>
          <?php if ( $talos_ficha_gastos_n ) : ?>Tiene <?php echo esc_html( $talos_ficha_gastos_n ); ?> gasto(s) de nómina asociado(s). <?php endif; ?>
          Esas relaciones se quedarán apuntando a un miembro eliminado.
        <?php endif; ?>
      </p>
      <div class="modal-actions">
        <button class="btn-ghost" data-close-modal>Cancelar</button>
        <button class="btn-danger" id="btnConfirmarEliminarMiembro">Sí, eliminar</button>
      </div>
    </div>
  </div>

<?php else : ?>

<div class="page-head">
  <div>
    <h1 class="page-title">Equipo</h1>
    <p class="page-sub">Colaboradores de Once24</p>
  </div>
  <button class="btn-primary" id="btnNuevoMiembro"><svg viewBox="0 0 24 24"><use href="#i-plus"/></svg>Nuevo Miembro</button>
</div>

<div class="count-strip">
  <div class="count-box accent"><span class="label">Total Equipo</span><span class="value tabular"><?php echo esc_html( $talos_total_equipo ); ?></span></div>
  <div class="count-box success"><span class="label">Activos</span><span class="value tabular"><?php echo esc_html( $talos_equipo_activos ); ?></span></div>
  <div class="count-box gold"><span class="label">Nómina Mensual</span><span class="value tabular"><?php echo esc_html( talos_fmt_mxn( $talos_nomina_mensual ) ); ?></span></div>
  <div class="count-box"><span class="label">Inactivos</span><span class="value tabular"><?php echo esc_html( $talos_equipo_inactivos ); ?></span></div>
</div>

<div class="filter-bar">
  <div class="filter-search"><svg viewBox="0 0 24 24"><use href="#i-search"/></svg><input type="text" id="buscarMiembro" placeholder="Buscar por nombre o puesto…"></div>
  <div class="seg" data-filter-group="estatus">
    <button class="active" data-filter="todos">Todos</button>
    <button data-filter="active">Activos</button>
    <button data-filter="inactive">Inactivos</button>
  </div>
  <div class="filter-spacer"></div>
</div>

<div class="table-card">
  <div class="table-scroll">
    <table id="teamTable">
      <thead>
        <tr>
          <th data-sort-key="miembro"><span class="th-flex">Miembro<button class="sort-btn" data-sort-key="miembro"><svg viewBox="0 0 24 24"><use href="#i-chevron"/></svg></button></span></th>
          <th data-sort-key="correo"><span class="th-flex">Correo<button class="sort-btn" data-sort-key="correo"><svg viewBox="0 0 24 24"><use href="#i-chevron"/></svg></button></span></th>
          <th>WhatsApp</th>
          <th data-sort-key="inicio"><span class="th-flex">Fecha de Inicio<button class="sort-btn" data-sort-key="inicio" data-sort-numeric><svg viewBox="0 0 24 24"><use href="#i-chevron"/></svg></button></span></th>
          <th class="num" data-sort-key="sueldo"><span class="th-flex">Sueldo Actual<button class="sort-btn" data-sort-key="sueldo" data-sort-numeric><svg viewBox="0 0 24 24"><use href="#i-chevron"/></svg></button></span></th>
          <th data-sort-key="estatus"><span class="th-flex">Estatus<button class="sort-btn" data-sort-key="estatus"><svg viewBox="0 0 24 24"><use href="#i-chevron"/></svg></button></span></th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody id="teamBody">
        <?php if ( empty( $talos_equipo_ids ) ) : ?>
          <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:30px;">Sin miembros de equipo registrados.</td></tr>
        <?php endif; ?>
        <?php foreach ( $talos_equipo_ids as $id ) :
            $nombre   = get_the_title( $id );
            $estatus  = get_field( 'team_status', $id );
            $estatus_clase = 'activo' === $estatus ? 'active' : ( 'inactivo' === $estatus ? 'inactive' : '' );
            $puesto   = get_field( 'team_role', $id );
            $correo   = get_field( 'team_email_corp', $id ) ?: get_field( 'team_email_personal', $id );
            $whatsapp = get_field( 'team_phone', $id );
            $inicio   = get_field( 'team_start_date', $id );
            $sueldo   = talos_equipo_sueldo_actual( $id );
            ?>
            <tr data-estatus="<?php echo esc_attr( $estatus_clase ); ?>" data-nombre="<?php echo esc_attr( $nombre . ' ' . $puesto ); ?>"
                data-sort-miembro="<?php echo esc_attr( $nombre ); ?>"
                data-sort-correo="<?php echo esc_attr( $correo ); ?>"
                data-sort-inicio="<?php echo esc_attr( str_replace( '-', '', (string) $inicio ) ); ?>"
                data-sort-sueldo="<?php echo esc_attr( $sueldo ); ?>"
                data-sort-estatus="<?php echo esc_attr( $estatus_clase ); ?>">
              <td>
                <div class="person">
                  <div class="person-ava"><?php echo esc_html( talos_iniciales( $nombre ) ); ?></div>
                  <div>
                    <div class="person-name"><?php echo esc_html( $nombre ); ?></div>
                    <?php if ( $puesto ) : ?><span class="person-sub"><?php echo esc_html( $talos_equipo_rol_labels[ $puesto ] ?? $puesto ); ?></span><?php endif; ?>
                  </div>
                </div>
              </td>
              <td><?php if ( $correo ) : ?><a class="email-link" href="mailto:<?php echo esc_attr( $correo ); ?>"><?php echo esc_html( $correo ); ?></a><?php else : ?>—<?php endif; ?></td>
              <td>
                <?php if ( $whatsapp ) : ?>
                  <div class="rfc-cell"><span><?php echo esc_html( $whatsapp ); ?></span><button class="copy-btn" data-copy="<?php echo esc_attr( $whatsapp ); ?>" title="Copiar WhatsApp"><svg viewBox="0 0 24 24"><use href="#i-copy"/></svg></button></div>
                <?php else : ?>—<?php endif; ?>
              </td>
              <td><?php echo esc_html( $inicio ? talos_equipo_fecha_corta( $inicio, $talos_meses_cortos_eq ) : '—' ); ?></td>
              <td class="num tabular"><?php echo esc_html( $sueldo > 0 ? talos_fmt_mxn( $sueldo ) : '—' ); ?></td>
              <td><?php if ( $estatus_clase ) : ?><span class="pill status-<?php echo esc_attr( $estatus_clase ); ?>"><span class="pill-dot"></span><?php echo esc_html( 'active' === $estatus_clase ? 'Active' : 'Inactive' ); ?></span><?php else : ?>—<?php endif; ?></td>
              <td class="row-actions">
                <a class="action-btn view" href="<?php echo esc_url( add_query_arg( 'ficha', $id ) ); ?>" title="Ver datos"><svg viewBox="0 0 24 24"><use href="#i-eye"/></svg></a>
                <a class="action-btn edit" href="<?php echo esc_url( add_query_arg( array( 'ficha' => $id, 'editar' => 1 ) ) ); ?>" title="Editar miembro"><svg viewBox="0 0 24 24"><use href="#i-edit"/></svg></a>
              </td>
            </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="modalNuevoMiembro">
  <div class="modal-box">
    <h4>Nuevo Miembro</h4>
    <p class="modal-sub">Captura lo esencial — el resto lo completas justo después, en la ficha.</p>
    <div class="form-field">
      <label for="campoNombreMiembro">Nombre</label>
      <input type="text" id="campoNombreMiembro" placeholder="Ej. Fernanda Letayf">
    </div>
    <div class="form-field">
      <label for="campoPuestoMiembro">Puesto</label>
      <select id="campoPuestoMiembro">
        <option value="">— Sin definir —</option>
        <?php foreach ( $talos_equipo_rol_labels as $val => $label ) : ?>
          <option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-field">
      <label for="campoEstatusMiembro">Estatus</label>
      <select id="campoEstatusMiembro">
        <?php foreach ( $talos_equipo_estatus_labels as $val => $label ) : ?>
          <option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="modal-actions">
      <button class="btn-ghost" data-close-modal>Cancelar</button>
      <button class="btn-confirm" id="btnConfirmarNuevoMiembro">Crear y Continuar</button>
    </div>
  </div>
</div>

<?php endif; ?>

<div class="toast" id="toastEquipo"><svg viewBox="0 0 24 24"><use href="#i-check"/></svg><span id="toastEquipoTexto"></span></div>

<script>
try{
  var talosNonceEq = '<?php echo esc_js( wp_create_nonce( 'talos_equipo' ) ); ?>';
  var talosAjaxUrlEq = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

  function mostrarToastEquipo(texto){
    var toast = document.getElementById('toastEquipo');
    document.getElementById('toastEquipoTexto').textContent = texto;
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
    form.append('action', 'talos_guardar_miembro');
    form.append('nonce', talosNonceEq);
    form.append('miembro_id', '<?php echo (int) $talos_ficha_id; ?>');
    document.querySelectorAll('.data-field input, .data-field select, .data-field textarea').forEach(function(campo){
      if (!campo.name) return;
      form.append(campo.name, campo.value);
    });
    fetch(talosAjaxUrlEq, { method: 'POST', body: form, credentials: 'same-origin' }).then(function(r){ return r.json(); }).then(function(res){
      if (!res.success){
        mostrarToastEquipo(res.data && res.data.mensaje ? res.data.mensaje : 'No se pudo guardar.');
        btn.disabled = false;
        return;
      }
      window.location.href = window.location.pathname + '?ficha=<?php echo (int) $talos_ficha_id; ?>';
    });
  });

  var modalEliminarMiembro = document.getElementById('modalEliminarMiembro');
  document.getElementById('btnEliminarMiembro').addEventListener('click', function(){
    modalEliminarMiembro.classList.add('show');
  });
  modalEliminarMiembro.querySelectorAll('[data-close-modal]').forEach(function(b){ b.addEventListener('click', function(){ modalEliminarMiembro.classList.remove('show'); }); });
  modalEliminarMiembro.addEventListener('click', function(e){ if (e.target === modalEliminarMiembro) modalEliminarMiembro.classList.remove('show'); });
  document.getElementById('btnConfirmarEliminarMiembro').addEventListener('click', function(){
    var btn = this;
    btn.disabled = true;
    var form = new FormData();
    form.append('action', 'talos_eliminar_miembro');
    form.append('nonce', talosNonceEq);
    form.append('miembro_id', '<?php echo (int) $talos_ficha_id; ?>');
    fetch(talosAjaxUrlEq, { method: 'POST', body: form, credentials: 'same-origin' }).then(function(r){ return r.json(); }).then(function(res){
      if (!res.success){
        mostrarToastEquipo(res.data && res.data.mensaje ? res.data.mensaje : 'No se pudo eliminar.');
        btn.disabled = false;
        return;
      }
      window.location.href = window.location.pathname;
    });
  });

  <?php else : ?>
  // ===== Listado: filtros + buscador + copiado + Nuevo Miembro =====
  function aplicarFiltrosEquipo(){
    var estatus = document.querySelector('[data-filter-group="estatus"] button.active').getAttribute('data-filter');
    var texto = (document.getElementById('buscarMiembro').value || '').trim().toLowerCase();
    document.querySelectorAll('#teamBody tr').forEach(function(row){
      if (!row.hasAttribute('data-nombre')) return;
      var okEstatus = (estatus === 'todos') || (row.getAttribute('data-estatus') === estatus);
      var okTexto = !texto || (row.getAttribute('data-nombre') || '').toLowerCase().indexOf(texto) !== -1;
      row.hidden = !(okEstatus && okTexto);
    });
  }
  document.querySelectorAll('.seg').forEach(function(seg){
    seg.querySelectorAll('button').forEach(function(btn){
      btn.addEventListener('click', function(){
        seg.querySelectorAll('button').forEach(function(b){b.classList.remove('active');});
        btn.classList.add('active');
        aplicarFiltrosEquipo();
      });
    });
  });
  document.getElementById('buscarMiembro').addEventListener('input', aplicarFiltrosEquipo);

  document.querySelectorAll('.copy-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
      var valor = btn.getAttribute('data-copy');
      var hacerFeedback = function(){
        btn.classList.add('copied');
        mostrarToastEquipo('Número "' + valor + '" copiado al portapapeles');
        setTimeout(function(){ btn.classList.remove('copied'); }, 1500);
      };
      if (navigator.clipboard && navigator.clipboard.writeText){
        navigator.clipboard.writeText(valor).then(hacerFeedback).catch(hacerFeedback);
      } else {
        hacerFeedback();
      }
    });
  });

  var modalNuevoMiembro = document.getElementById('modalNuevoMiembro');
  document.getElementById('btnNuevoMiembro').addEventListener('click', function(){
    document.getElementById('campoNombreMiembro').value = '';
    modalNuevoMiembro.classList.add('show');
  });
  modalNuevoMiembro.querySelectorAll('[data-close-modal]').forEach(function(b){ b.addEventListener('click', function(){ modalNuevoMiembro.classList.remove('show'); }); });
  modalNuevoMiembro.addEventListener('click', function(e){ if (e.target === modalNuevoMiembro) modalNuevoMiembro.classList.remove('show'); });

  document.getElementById('btnConfirmarNuevoMiembro').addEventListener('click', function(){
    var nombre = document.getElementById('campoNombreMiembro').value.trim();
    if (!nombre){ mostrarToastEquipo('Captura el nombre del colaborador'); return; }
    var btn = this;
    btn.disabled = true;
    var form = new FormData();
    form.append('action', 'talos_crear_miembro');
    form.append('nonce', talosNonceEq);
    form.append('nombre', nombre);
    form.append('puesto', document.getElementById('campoPuestoMiembro').value);
    form.append('estatus', document.getElementById('campoEstatusMiembro').value);
    fetch(talosAjaxUrlEq, { method: 'POST', body: form, credentials: 'same-origin' }).then(function(r){ return r.json(); }).then(function(res){
      if (!res.success){
        mostrarToastEquipo(res.data && res.data.mensaje ? res.data.mensaje : 'No se pudo crear el miembro.');
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
