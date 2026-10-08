<?php
/**
 * Servicios — catálogo de referencia de precios y frecuencia (talos_service_cat).
 * Campos ACF planos ("Catálogo de Servicios", sin Group), sin riesgo del bug de
 * Empresas. El mockup variaba el ícono por tipo de servicio (megáfono/sitio web/
 * servidor/dominio), pero no existe ningún campo ACF que decida eso — se usa un
 * ícono genérico (i-tag, el mismo del sidebar) para todas las tarjetas.
 */

$talos_frecuencia_labels = array(
    'pago_unico'=>'Pago Único','por_hora'=>'Por Hora','semanal'=>'Semanal','quincenal'=>'Quincenal',
    'mensual'=>'Mensual','trimestral'=>'Trimestral','semestral'=>'Semestral','anual'=>'Anual',
);

$talos_servicios_ids = get_posts( array(
    'post_type'      => 'talos_service_cat',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'orderby'        => 'title',
    'order'          => 'ASC',
) );

$talos_total_catalogo = count( $talos_servicios_ids );
$talos_recurrentes = 0;
$talos_pago_unico = 0;
$talos_suma_precios = 0.0;
$talos_conteo_precios = 0;
foreach ( $talos_servicios_ids as $id ) {
    $frecuencia = get_field( 'service_ref_frequency', $id );
    if ( 'pago_unico' === $frecuencia ) {
        $talos_pago_unico++;
    } elseif ( $frecuencia ) {
        $talos_recurrentes++;
    }
    $precio_mxn = (float) get_field( 'service_ref_price_mxn', $id );
    if ( $precio_mxn > 0 ) {
        $talos_suma_precios += $precio_mxn;
        $talos_conteo_precios++;
    }
}
$talos_precio_promedio = $talos_conteo_precios > 0 ? $talos_suma_precios / $talos_conteo_precios : 0;

get_header();
?>

<style>
  .service-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;}
  @media (max-width:1080px){.service-grid{grid-template-columns:repeat(2,minmax(0,1fr));}}
  @media (max-width:620px){.service-grid{grid-template-columns:1fr;}}
  .service-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px;display:flex;flex-direction:column;gap:12px;}
  .service-top{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;}
  .service-icon{width:38px;height:38px;border-radius:10px;background:var(--accent-soft);color:var(--accent);display:flex;align-items:center;justify-content:center;flex:none;}
  .service-icon svg{width:19px;height:19px;}
  .freq-pill{padding:3px 10px;border-radius:99px;font-size:11px;font-weight:700;white-space:nowrap;}
  .freq-pill.recurrente{background:var(--success-soft);color:var(--success);}
  .freq-pill.unico{background:var(--surface-2);color:var(--text-muted);border:1px solid var(--border);}
  .service-name{font-size:15.5px;font-weight:800;margin:0;}
  .service-desc{font-size:12.5px;color:var(--text-muted);margin:0;line-height:1.5;flex:1;}
  .service-prices{display:flex;align-items:baseline;justify-content:space-between;gap:10px;border-top:1px solid var(--border);padding-top:12px;}
  .price-mxn{font-size:19px;font-weight:800;}
  .price-mxn small{font-size:11px;color:var(--text-muted);font-weight:600;}
  .price-usd{font-size:12px;color:var(--text-muted);font-weight:600;}
  .service-actions{display:flex;align-items:center;gap:6px;border-top:1px solid var(--border);padding-top:12px;}
  .service-actions .action-btn{flex:1;height:32px;gap:6px;font-size:12px;font-weight:700;}
</style>

<div class="page-head">
  <div>
    <h1 class="page-title">Servicios</h1>
    <p class="page-sub">Catálogo de referencia de precios y frecuencia</p>
  </div>
  <button class="btn-primary" id="btnNuevoServicio"><svg viewBox="0 0 24 24"><use href="#i-plus"/></svg>Nuevo Servicio</button>
</div>

<div class="count-strip">
  <div class="count-box accent"><span class="label">Total en Catálogo</span><span class="value tabular"><?php echo esc_html( $talos_total_catalogo ); ?></span></div>
  <div class="count-box success"><span class="label">Recurrentes</span><span class="value tabular"><?php echo esc_html( $talos_recurrentes ); ?></span></div>
  <div class="count-box"><span class="label">Pago Único</span><span class="value tabular"><?php echo esc_html( $talos_pago_unico ); ?></span></div>
  <div class="count-box gold"><span class="label">Precio Promedio</span><span class="value tabular"><?php echo esc_html( talos_fmt_mxn( $talos_precio_promedio ) ); ?></span></div>
</div>

<div class="filter-bar">
  <div class="filter-search"><svg viewBox="0 0 24 24"><use href="#i-search"/></svg><input type="text" id="buscarServicio" placeholder="Buscar servicio…"></div>
  <div class="seg" data-filter-group="frecuencia">
    <button class="active" data-filter="todos">Todos</button>
    <button data-filter="mensual">Mensual</button>
    <button data-filter="anual">Anual</button>
    <button data-filter="pago_unico">Pago Único</button>
  </div>
  <div class="filter-spacer"></div>
</div>

<div class="service-grid" id="serviceGrid">
  <?php if ( empty( $talos_servicios_ids ) ) : ?>
    <p style="color:var(--text-muted);">Sin servicios en el catálogo.</p>
  <?php endif; ?>
  <?php foreach ( $talos_servicios_ids as $id ) :
      $nombre      = get_the_title( $id );
      $frecuencia  = get_field( 'service_ref_frequency', $id );
      $descripcion = get_field( 'service_description', $id );
      $precio_mxn  = (float) get_field( 'service_ref_price_mxn', $id );
      $precio_usd  = (float) get_field( 'service_ref_price_usd', $id );
      $es_unico    = 'pago_unico' === $frecuencia;
      ?>
      <div class="service-card" data-freq="<?php echo esc_attr( $frecuencia ); ?>" data-nombre="<?php echo esc_attr( $nombre ); ?>">
        <div class="service-top">
          <div class="service-icon"><svg viewBox="0 0 24 24"><use href="#i-tag"/></svg></div>
          <?php if ( $frecuencia ) : ?>
            <span class="freq-pill <?php echo $es_unico ? 'unico' : 'recurrente'; ?>"><?php echo esc_html( $talos_frecuencia_labels[ $frecuencia ] ?? $frecuencia ); ?></span>
          <?php endif; ?>
        </div>
        <h3 class="service-name"><?php echo esc_html( $nombre ); ?></h3>
        <p class="service-desc"><?php echo esc_html( $descripcion ?: 'Sin descripción.' ); ?></p>
        <div class="service-prices">
          <span class="price-mxn tabular"><?php echo esc_html( talos_fmt_mxn( $precio_mxn ) ); ?><small> MXN</small></span>
          <span class="price-usd tabular"><?php echo $precio_usd > 0 ? esc_html( '$' . number_format( $precio_usd, 0 ) . ' USD' ) : '—'; ?></span>
        </div>
        <div class="service-actions">
          <button class="action-btn view" data-action="ver" data-servicio="<?php echo esc_attr( $nombre ); ?>"><svg viewBox="0 0 24 24"><use href="#i-eye"/></svg>Ver</button>
          <button class="action-btn edit" data-action="editar" data-servicio="<?php echo esc_attr( $nombre ); ?>"><svg viewBox="0 0 24 24"><use href="#i-edit"/></svg>Editar</button>
        </div>
      </div>
  <?php endforeach; ?>
</div>

<div class="toast" id="toastServicios"><svg viewBox="0 0 24 24"><use href="#i-check"/></svg><span id="toastServiciosTexto"></span></div>

<script>
try{
  function mostrarToastServicios(texto){
    var toast = document.getElementById('toastServicios');
    document.getElementById('toastServiciosTexto').textContent = texto;
    toast.classList.add('show');
    clearTimeout(toast._t);
    toast._t = setTimeout(function(){ toast.classList.remove('show'); }, 2600);
  }

  function aplicarFiltrosServicios(){
    var freq = document.querySelector('[data-filter-group="frecuencia"] button.active').getAttribute('data-filter');
    var texto = (document.getElementById('buscarServicio').value || '').trim().toLowerCase();
    document.querySelectorAll('#serviceGrid .service-card').forEach(function(card){
      var okFreq = (freq === 'todos') || (card.getAttribute('data-freq') === freq);
      var okTexto = !texto || (card.getAttribute('data-nombre') || '').toLowerCase().indexOf(texto) !== -1;
      card.hidden = !(okFreq && okTexto);
    });
  }
  document.querySelectorAll('.seg').forEach(function(seg){
    seg.querySelectorAll('button').forEach(function(btn){
      btn.addEventListener('click', function(){
        seg.querySelectorAll('button').forEach(function(b){b.classList.remove('active');});
        btn.classList.add('active');
        aplicarFiltrosServicios();
      });
    });
  });
  document.getElementById('buscarServicio').addEventListener('input', aplicarFiltrosServicios);

  document.querySelectorAll('[data-action="ver"]').forEach(function(btn){
    btn.addEventListener('click', function(){
      mostrarToastServicios('Ver datos de "' + btn.getAttribute('data-servicio') + '" — pantalla por diseñar en la siguiente fase');
    });
  });
  document.querySelectorAll('[data-action="editar"]').forEach(function(btn){
    btn.addEventListener('click', function(){
      mostrarToastServicios('Editar "' + btn.getAttribute('data-servicio') + '" — pantalla por diseñar en la siguiente fase');
    });
  });
  document.getElementById('btnNuevoServicio').addEventListener('click', function(){
    mostrarToastServicios('Crear Nuevo Servicio — pantalla por diseñar en la siguiente fase');
  });
}catch(e){ console.error(e); }
</script>

<?php get_footer(); ?>
