<?php
/**
 * Empresas — directorio de clientes/leads/aliados (talos_company).
 * company_state/company_class/company_status se guardan como slug (return_format
 * "value" en ACF), por eso necesitan un mapa de etiquetas igual que expense_subcategory
 * en Gastos.
 *
 * 2026-10-08: estos campos vivían anidados dentro de campos ACF tipo "Group"
 * (Clasificación/Datos Fiscales/Contacto y Ubicación) y get_field() devolvía
 * valores cruzados entre empresas distintas (mismo bug ya visto en Oportunidades).
 * Jorge reestructuró "Perfil de Empresa" convirtiendo esos Group a Tab (puramente
 * visual, mismos field names, sin pérdida de datos) — con los campos ya planos,
 * se usa get_field() normal de nuevo.
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

function talos_empresas_dominio( $url ) {
    $url = trim( (string) $url );
    if ( '' === $url ) return '';
    $host = wp_parse_url( $url, PHP_URL_HOST );
    if ( ! $host ) $host = $url;
    return preg_replace( '/^www\./i', '', $host );
}

$talos_empresas_ids = get_posts( array(
    'post_type'      => 'talos_company',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'orderby'        => 'title',
    'order'          => 'ASC',
) );

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

get_header();
?>

<div class="page-head">
  <div>
    <h1 class="page-title">Empresas</h1>
    <p class="page-sub">Directorio de clientes, leads y aliados</p>
  </div>
  <button class="btn-primary" id="btnNuevaEmpresa"><svg viewBox="0 0 24 24"><use href="#i-plus"/></svg>Nueva Empresa</button>
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
          <th>Empresa</th><th>Clase</th><th>Estatus</th><th>Account Manager</th><th>RFC</th><th>Estado</th><th>Acciones</th>
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
            ?>
            <tr data-estatus="<?php echo esc_attr( $estatus ); ?>" data-clase="<?php echo esc_attr( $clase ); ?>" data-nombre="<?php echo esc_attr( $nombre ); ?>">
              <td>
                <div class="company-name">
                  <div class="company-logo"><?php echo esc_html( talos_iniciales( $nombre ) ); ?></div>
                  <div><?php echo esc_html( $nombre ); ?><?php if ( $dominio ) : ?><span class="company-sub"><?php echo esc_html( $dominio ); ?></span><?php endif; ?></div>
                </div>
              </td>
              <td><?php if ( $clase ) : ?><span class="pill class-<?php echo esc_attr( $clase ); ?>"><?php echo esc_html( $talos_clase_labels[ $clase ] ?? $clase ); ?></span><?php else : ?>—<?php endif; ?></td>
              <td><?php if ( $estatus ) : ?><span class="pill status-<?php echo esc_attr( $estatus ); ?>"><span class="pill-dot"></span><?php echo esc_html( $talos_estatus_labels[ $estatus ] ?? $estatus ); ?></span><?php else : ?>—<?php endif; ?></td>
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
              <td><?php echo esc_html( $estado ? ( $talos_estados_mx[ $estado ] ?? $estado ) : '—' ); ?></td>
              <td class="row-actions">
                <button class="action-btn view" data-action="ver" data-empresa="<?php echo esc_attr( $nombre ); ?>" title="Ver ficha técnica"><svg viewBox="0 0 24 24"><use href="#i-eye"/></svg></button>
                <button class="action-btn edit" data-action="editar" data-empresa="<?php echo esc_attr( $nombre ); ?>" title="Editar empresa"><svg viewBox="0 0 24 24"><use href="#i-edit"/></svg></button>
              </td>
            </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="toast" id="toastEmpresas"><svg viewBox="0 0 24 24"><use href="#i-check"/></svg><span id="toastEmpresasTexto"></span></div>

<script>
try{
  function mostrarToastEmpresas(texto){
    var toast = document.getElementById('toastEmpresas');
    document.getElementById('toastEmpresasTexto').textContent = texto;
    toast.classList.add('show');
    clearTimeout(toast._t);
    toast._t = setTimeout(function(){ toast.classList.remove('show'); }, 2600);
  }

  // Filtros rápidos (Estatus + Clase) + buscador, combinados con AND sobre la tabla
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

  // Copiado rápido de RFC
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

  // Ver ficha técnica / Editar empresa / Nueva Empresa -> pantallas por diseñar en la siguiente fase
  document.querySelectorAll('[data-action="ver"]').forEach(function(btn){
    btn.addEventListener('click', function(){
      mostrarToastEmpresas('Ficha técnica de "' + btn.getAttribute('data-empresa') + '" — pantalla por diseñar en la siguiente fase');
    });
  });
  document.querySelectorAll('[data-action="editar"]').forEach(function(btn){
    btn.addEventListener('click', function(){
      mostrarToastEmpresas('Editar "' + btn.getAttribute('data-empresa') + '" — pantalla por diseñar en la siguiente fase');
    });
  });
  document.getElementById('btnNuevaEmpresa').addEventListener('click', function(){
    mostrarToastEmpresas('Crear Nueva Empresa — pantalla por diseñar en la siguiente fase');
  });
}catch(e){ console.error(e); }
</script>

<?php get_footer(); ?>
