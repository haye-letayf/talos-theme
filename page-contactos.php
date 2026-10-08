<?php
/**
 * Contactos — personas de contacto en cada Empresa (talos_contact).
 * A diferencia de Empresas, estos campos ACF están planos desde el inicio
 * (sin Group), así que get_field() normal no tiene el riesgo ya visto ahí.
 * Columna "Recibe Facturas" (contact_billing_recipient) deliberadamente fuera
 * de la tabla — Jorge pidió quitarla en la fase de diseño, no es útil aquí.
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

get_header();
?>

<style>
  .count-strip{grid-template-columns:repeat(3,minmax(0,1fr));}
</style>

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
          <th>Contacto</th><th>Empresa</th><th>Correo</th><th>Teléfono</th><th>WhatsApp</th><th>Clase</th><th>Estatus</th><th>Acciones</th>
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
            ?>
            <tr data-estatus="<?php echo esc_attr( $estatus ); ?>" data-clase="<?php echo esc_attr( $clase ); ?>" data-nombre="<?php echo esc_attr( $nombre_filtro ); ?>">
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
              <td><?php if ( $clase ) : ?><span class="pill class-<?php echo esc_attr( $clase ); ?>"><?php echo esc_html( $talos_contacto_clase_labels[ $clase ] ?? $clase ); ?></span><?php else : ?>—<?php endif; ?></td>
              <td><?php if ( $estatus ) : ?><span class="pill status-<?php echo esc_attr( $estatus ); ?>"><span class="pill-dot"></span><?php echo esc_html( $talos_contacto_estatus_labels[ $estatus ] ?? $estatus ); ?></span><?php else : ?>—<?php endif; ?></td>
              <td class="row-actions">
                <button class="action-btn view" data-action="ver" data-contacto="<?php echo esc_attr( $nombre ); ?>" title="Ver datos"><svg viewBox="0 0 24 24"><use href="#i-eye"/></svg></button>
                <button class="action-btn edit" data-action="editar" data-contacto="<?php echo esc_attr( $nombre ); ?>" title="Editar contacto"><svg viewBox="0 0 24 24"><use href="#i-edit"/></svg></button>
              </td>
            </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="toast" id="toastContactos"><svg viewBox="0 0 24 24"><use href="#i-check"/></svg><span id="toastContactosTexto"></span></div>

<script>
try{
  function mostrarToastContactos(texto){
    var toast = document.getElementById('toastContactos');
    document.getElementById('toastContactosTexto').textContent = texto;
    toast.classList.add('show');
    clearTimeout(toast._t);
    toast._t = setTimeout(function(){ toast.classList.remove('show'); }, 2600);
  }

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

  document.querySelectorAll('[data-action="ver"]').forEach(function(btn){
    btn.addEventListener('click', function(){
      mostrarToastContactos('Ver datos de "' + btn.getAttribute('data-contacto') + '" — pantalla por diseñar en la siguiente fase');
    });
  });
  document.querySelectorAll('[data-action="editar"]').forEach(function(btn){
    btn.addEventListener('click', function(){
      mostrarToastContactos('Editar "' + btn.getAttribute('data-contacto') + '" — pantalla por diseñar en la siguiente fase');
    });
  });
  document.getElementById('btnNuevoContacto').addEventListener('click', function(){
    mostrarToastContactos('Crear Nuevo Contacto — pantalla por diseñar en la siguiente fase');
  });
}catch(e){ console.error(e); }
</script>

<?php get_footer(); ?>
