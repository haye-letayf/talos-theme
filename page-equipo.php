<?php
/**
 * Equipo — colaboradores de Once24 (talos_team).
 * team_status usa valores en español ("activo"/"inactivo", a diferencia de
 * company_status/contact_status que usan "active"/"inactive") — se traduce a
 * la clase CSS existente (.pill.status-active/.status-inactive) en PHP para
 * no tener que duplicar esas reglas. "Sueldo Actual" no es un campo plano:
 * se calcula como la fila más reciente de team_salary_history (repeater).
 */

$talos_equipo_rol_labels = array(
    'account_manager'=>'Account Manager','graphic_designer'=>'Graphic Designer',
    'programmer'=>'Programmer','manager'=>'Manager',
);

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

get_header();
?>

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
                <button class="action-btn view" data-action="ver" data-miembro="<?php echo esc_attr( $nombre ); ?>" title="Ver datos"><svg viewBox="0 0 24 24"><use href="#i-eye"/></svg></button>
                <button class="action-btn edit" data-action="editar" data-miembro="<?php echo esc_attr( $nombre ); ?>" title="Editar miembro"><svg viewBox="0 0 24 24"><use href="#i-edit"/></svg></button>
              </td>
            </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="toast" id="toastEquipo"><svg viewBox="0 0 24 24"><use href="#i-check"/></svg><span id="toastEquipoTexto"></span></div>

<script>
try{
  function mostrarToastEquipo(texto){
    var toast = document.getElementById('toastEquipo');
    document.getElementById('toastEquipoTexto').textContent = texto;
    toast.classList.add('show');
    clearTimeout(toast._t);
    toast._t = setTimeout(function(){ toast.classList.remove('show'); }, 2600);
  }

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

  document.querySelectorAll('[data-action="ver"]').forEach(function(btn){
    btn.addEventListener('click', function(){
      mostrarToastEquipo('Ver datos de "' + btn.getAttribute('data-miembro') + '" — pantalla por diseñar en la siguiente fase');
    });
  });
  document.querySelectorAll('[data-action="editar"]').forEach(function(btn){
    btn.addEventListener('click', function(){
      mostrarToastEquipo('Editar "' + btn.getAttribute('data-miembro') + '" — pantalla por diseñar en la siguiente fase');
    });
  });
  document.getElementById('btnNuevoMiembro').addEventListener('click', function(){
    mostrarToastEquipo('Crear Nuevo Miembro — pantalla por diseñar en la siguiente fase');
  });
}catch(e){ console.error(e); }
</script>

<?php get_footer(); ?>
