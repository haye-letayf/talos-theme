<?php
/**
 * Oportunidades — pipeline de cotizaciones (talos_opportunity), vista Kanban.
 * Campos ACF ya son Tab (no Group) desde que se construyó este módulo — ver
 * motor-oportunidades.php. El valor de cada tarjeta no es un campo plano: se
 * calcula sumando quote_items (cantidad × precio final con descuento de
 * concepto, vía talos_precio_final_concepto() de talos-core) y aplicando
 * encima el descuento general de la cotización si aplica.
 *
 * Mover de etapa y crear oportunidad SÍ quedan wireados a AJAX real (a
 * diferencia del resto de "Nuevo X" de otros módulos, que quedan en toast) —
 * es la interacción diaria de un Kanban y el alcance es acotado. "Ver/Editar"
 * siguen en toast, sin ficha de detalle diseñada todavía.
 */

function talos_oportunidad_valor_total( $id ) {
    $items = get_field( 'quote_items', $id );
    $subtotal = 0.0;
    if ( is_array( $items ) && function_exists( 'talos_precio_final_concepto' ) ) {
        foreach ( $items as $item ) {
            $cantidad = (float) ( $item['service_quantity'] ?? 1 );
            $subtotal += $cantidad * talos_precio_final_concepto( $item );
        }
    }
    if ( get_field( 'quote_has_general_discount', $id ) ) {
        $valor_desc = (float) get_field( 'quote_general_discount_value', $id );
        if ( 'porcentaje' === get_field( 'quote_general_discount_type', $id ) ) {
            $subtotal -= $subtotal * ( $valor_desc / 100 );
        } else {
            $subtotal -= $valor_desc;
        }
    }
    return max( 0, round( $subtotal, 2 ) );
}

function talos_opp_dias_restantes( $fecha_ymd ) {
    if ( ! $fecha_ymd ) return null;
    $venc = DateTime::createFromFormat( 'Y-m-d', $fecha_ymd );
    if ( ! $venc ) return null;
    return (int) ( new DateTime( 'today' ) )->diff( $venc )->format( '%r%a' );
}

$talos_opp_etapas = array(
    'prospeccion'       => array( 'label' => 'Prospección',      'color' => '#94a3b8', 'permite_crear' => true,  'siguiente' => 'propuesta_enviada' ),
    'propuesta_enviada' => array( 'label' => 'Propuesta Enviada', 'color' => 'var(--accent)', 'permite_crear' => true,  'siguiente' => 'en_negociacion' ),
    'en_negociacion'    => array( 'label' => 'En Negociación',    'color' => 'var(--gold)', 'permite_crear' => true,  'siguiente' => null ),
    'ganada'            => array( 'label' => 'Ganada',            'color' => 'var(--success)', 'permite_crear' => false, 'siguiente' => null ),
    'perdida'           => array( 'label' => 'Perdida',           'color' => 'var(--danger)', 'permite_crear' => false, 'siguiente' => null ),
);

$talos_opp_ids = get_posts( array(
    'post_type'      => 'talos_opportunity',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'orderby'        => 'date',
    'order'          => 'DESC',
) );

$talos_opp_por_etapa = array_fill_keys( array_keys( $talos_opp_etapas ), array() );
$talos_opp_datos = array();
foreach ( $talos_opp_ids as $id ) {
    $etapa = get_field( 'opportunity_stage', $id );
    if ( ! isset( $talos_opp_por_etapa[ $etapa ] ) ) continue;
    $empresa = get_field( 'opportunity_company', $id );
    $talos_opp_datos[ $id ] = array(
        'nombre'         => get_field( 'opportunity_name', $id ) ?: get_the_title( $id ),
        'empresa'        => ( $empresa instanceof WP_Post ) ? $empresa->post_title : '—',
        'valor'          => talos_oportunidad_valor_total( $id ),
        'referencia'     => get_field( 'quote_reference', $id ),
        'valido_hasta'   => get_field( 'quote_valid_until', $id ),
        'lost_reason'    => get_field( 'opportunity_lost_reason', $id ),
        'convertida'     => (bool) get_post_meta( $id, '_talos_opportunity_converted', true ),
        'modificado_mes' => get_post_modified_time( 'Y-m', false, $id ),
    );
    $talos_opp_por_etapa[ $etapa ][] = $id;
}

$talos_abiertas_keys = array( 'prospeccion', 'propuesta_enviada', 'en_negociacion' );
$talos_kpi_abiertas = 0;
$talos_kpi_pipeline = 0.0;
foreach ( $talos_abiertas_keys as $k ) {
    foreach ( $talos_opp_por_etapa[ $k ] as $id ) {
        $talos_kpi_abiertas++;
        $talos_kpi_pipeline += $talos_opp_datos[ $id ]['valor'];
    }
}
$talos_mes_actual_ymd = current_time( 'Y-m' );
$talos_kpi_ganadas_n = 0;
$talos_kpi_ganadas_valor = 0.0;
foreach ( $talos_opp_por_etapa['ganada'] as $id ) {
    if ( $talos_opp_datos[ $id ]['modificado_mes'] === $talos_mes_actual_ymd ) {
        $talos_kpi_ganadas_n++;
        $talos_kpi_ganadas_valor += $talos_opp_datos[ $id ]['valor'];
    }
}
$talos_total_cerradas = count( $talos_opp_por_etapa['ganada'] ) + count( $talos_opp_por_etapa['perdida'] );
$talos_kpi_tasa_cierre = $talos_total_cerradas > 0 ? round( count( $talos_opp_por_etapa['ganada'] ) / $talos_total_cerradas * 100 ) : 0;

$talos_opp_empresas_ids = get_posts( array(
    'post_type'      => 'talos_company',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'orderby'        => 'title',
    'order'          => 'ASC',
) );

get_header();
?>

<style>
  .board-scroll{overflow-x:auto;padding-bottom:6px;}
  .board{display:flex;gap:16px;align-items:flex-start;min-width:1180px;}
  .board-col{width:232px;flex:none;display:flex;flex-direction:column;gap:12px;}
  .col-head{display:flex;align-items:center;gap:8px;padding:2px 4px;}
  .col-dot{width:9px;height:9px;border-radius:50%;flex:none;}
  .col-title{font-size:13px;font-weight:700;flex:1;}
  .col-count{font-size:11px;font-weight:700;color:var(--text-muted);background:var(--surface);border:1px solid var(--border);padding:1px 8px;border-radius:99px;}
  .col-total{font-size:11.5px;color:var(--text-muted);font-weight:600;padding:0 4px;margin-top:-6px;}
  .col-body{display:flex;flex-direction:column;gap:10px;min-height:60px;}
  .col-add{display:flex;align-items:center;justify-content:center;gap:6px;border:1.5px dashed var(--border);border-radius:10px;padding:9px;color:var(--text-muted);font-size:12px;font-weight:600;cursor:pointer;background:transparent;width:100%;}
  .col-add svg{width:14px;height:14px;}
  .col-add:hover{border-color:var(--accent);color:var(--accent);}

  .deal-card{background:var(--surface);border:1px solid var(--border);border-top:3px solid var(--border);border-radius:11px;padding:13px 14px;display:flex;flex-direction:column;gap:9px;}
  .deal-card.c-prospeccion{border-top-color:#94a3b8;}
  .deal-card.c-propuesta_enviada{border-top-color:var(--accent);}
  .deal-card.c-en_negociacion{border-top-color:var(--gold);}
  .deal-card.c-ganada{border-top-color:var(--success);}
  .deal-card.c-perdida{border-top-color:var(--danger);}
  .deal-company{font-size:11.5px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.03em;}
  .deal-name{font-size:13.5px;font-weight:700;line-height:1.3;}
  .deal-value{font-size:16px;font-weight:800;}
  .deal-foot{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:2px;}
  .deal-ref{font-size:10.5px;color:var(--text-muted);font-family:monospace;}
  .deal-ava{width:24px;height:24px;border-radius:50%;background:var(--accent-soft);color:var(--accent);display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;flex:none;}
  .deal-badge{display:inline-flex;align-items:center;gap:4px;font-size:10.5px;font-weight:700;padding:2px 7px;border-radius:99px;width:fit-content;}
  .deal-badge.warn{background:#fdf3d8;color:#8a6200;}
  .deal-badge.lost{background:var(--danger-soft);color:var(--danger);}
  .deal-badge.won{background:var(--success-soft);color:var(--success);}
  .deal-badge svg{width:10px;height:10px;}

  .deal-actions{display:flex;align-items:center;gap:6px;border-top:1px solid var(--border);padding-top:9px;margin-top:2px;}
  .deal-actions .action-btn{width:26px;height:26px;}
  .deal-actions .action-btn svg{width:13px;height:13px;}
  .btn-move{display:flex;align-items:center;justify-content:center;gap:5px;border:1px dashed var(--border);background:transparent;color:var(--text-muted);border-radius:7px;padding:6px 8px;font-size:11px;font-weight:700;cursor:pointer;white-space:nowrap;flex:1;}
  .btn-move:hover{border-color:var(--accent);color:var(--accent);}
  .btn-move svg{width:11px;height:11px;}
  .stage-decision{display:flex;gap:6px;}
  .btn-move.win{border-color:var(--success);color:var(--success);}
  .btn-move.win:hover{background:var(--success-soft);}
  .btn-move.lose{border-color:var(--danger);color:var(--danger);}
  .btn-move.lose:hover{background:var(--danger-soft);}

  @media (max-width:860px){.board-col{width:210px;}}
</style>

<div class="page-head">
  <div>
    <h1 class="page-title">Oportunidades</h1>
    <p class="page-sub">Pipeline de cotizaciones en curso</p>
  </div>
  <button class="btn-primary" id="btnNuevaOportunidad"><svg viewBox="0 0 24 24"><use href="#i-plus"/></svg>Nueva Oportunidad</button>
</div>

<div class="kpi-strip">
  <div class="metric-box stripe-accent"><span class="label">Oportunidades Abiertas</span><span class="value tabular"><?php echo esc_html( $talos_kpi_abiertas ); ?></span></div>
  <div class="metric-box stripe-gold"><span class="label">Valor en Pipeline</span><span class="value tabular"><?php echo esc_html( talos_fmt_mxn( $talos_kpi_pipeline ) ); ?></span></div>
  <div class="metric-box stripe-success"><span class="label">Ganadas este mes</span><span class="value tabular"><?php echo esc_html( $talos_kpi_ganadas_n . ' · ' . talos_fmt_mxn( $talos_kpi_ganadas_valor ) ); ?></span></div>
  <div class="metric-box"><span class="label">Tasa de Cierre</span><span class="value tabular"><?php echo esc_html( $talos_kpi_tasa_cierre ); ?>%</span></div>
</div>

<div class="board-scroll">
  <div class="board">
    <?php foreach ( $talos_opp_etapas as $etapa_key => $etapa ) :
        $ids_etapa = $talos_opp_por_etapa[ $etapa_key ];
        $total_etapa = 0.0;
        foreach ( $ids_etapa as $id ) { $total_etapa += $talos_opp_datos[ $id ]['valor']; }
        ?>
      <div class="board-col" data-stage="<?php echo esc_attr( $etapa_key ); ?>">
        <div class="col-head">
          <span class="col-dot" style="background:<?php echo esc_attr( $etapa['color'] ); ?>"></span>
          <span class="col-title"><?php echo esc_html( $etapa['label'] ); ?></span>
          <span class="col-count"><?php echo esc_html( count( $ids_etapa ) ); ?></span>
        </div>
        <div class="col-total">
          <?php if ( 'perdida' === $etapa_key ) : ?>
            <?php echo esc_html( count( $ids_etapa ) ); ?> oportunidad<?php echo 1 === count( $ids_etapa ) ? '' : 'es'; ?>
          <?php elseif ( 'ganada' === $etapa_key ) : ?>
            <?php echo esc_html( talos_fmt_mxn( $total_etapa ) ); ?> cerrado
          <?php else : ?>
            <?php echo esc_html( talos_fmt_mxn( $total_etapa ) ); ?> en pipeline
          <?php endif; ?>
        </div>
        <div class="col-body">
          <?php foreach ( $ids_etapa as $id ) :
              $d = $talos_opp_datos[ $id ];
              $dias = talos_opp_dias_restantes( $d['valido_hasta'] );
              ?>
            <div class="deal-card c-<?php echo esc_attr( $etapa_key ); ?>" data-id="<?php echo esc_attr( $id ); ?>">
              <div class="deal-company"><?php echo esc_html( $d['empresa'] ); ?></div>
              <div class="deal-name"><?php echo esc_html( $d['nombre'] ); ?></div>
              <div class="deal-value tabular"><?php echo esc_html( talos_fmt_mxn( $d['valor'] ) ); ?></div>
              <?php if ( 'ganada' === $etapa_key && $d['convertida'] ) : ?>
                <div class="deal-badge won"><svg viewBox="0 0 24 24"><use href="#i-trophy"/></svg>Convertida a Cliente</div>
              <?php elseif ( 'perdida' === $etapa_key && $d['lost_reason'] ) : ?>
                <div class="deal-badge lost"><svg viewBox="0 0 24 24"><use href="#i-x-circle"/></svg><?php echo esc_html( wp_trim_words( $d['lost_reason'], 4, '…' ) ); ?></div>
              <?php elseif ( in_array( $etapa_key, array( 'propuesta_enviada', 'en_negociacion' ), true ) && null !== $dias && $dias <= 7 ) : ?>
                <div class="deal-badge warn"><svg viewBox="0 0 24 24"><use href="#i-clock"/></svg><?php echo esc_html( $dias < 0 ? 'Venció hace ' . abs( $dias ) . ' días' : ( 0 === $dias ? 'Vence hoy' : 'Vence en ' . $dias . ' días' ) ); ?></div>
              <?php endif; ?>
              <div class="deal-foot">
                <span class="deal-ref"><?php echo esc_html( $d['referencia'] ?: '—' ); ?></span>
                <div class="deal-ava"><?php echo esc_html( talos_iniciales( $d['empresa'] ) ); ?></div>
              </div>
              <div class="deal-actions">
                <button class="action-btn view" data-action="ver" data-oportunidad="<?php echo esc_attr( $d['nombre'] ); ?>" title="Ver datos"><svg viewBox="0 0 24 24"><use href="#i-eye"/></svg></button>
                <button class="action-btn edit" data-action="editar" data-oportunidad="<?php echo esc_attr( $d['nombre'] ); ?>" title="Editar"><svg viewBox="0 0 24 24"><use href="#i-edit"/></svg></button>
                <?php if ( 'prospeccion' === $etapa_key || 'propuesta_enviada' === $etapa_key ) : ?>
                  <button class="btn-move" data-move="<?php echo esc_attr( $etapa['siguiente'] ); ?>">Siguiente etapa<svg viewBox="0 0 24 24"><use href="#i-arrow-right"/></svg></button>
                <?php endif; ?>
              </div>
              <?php if ( 'en_negociacion' === $etapa_key ) : ?>
                <div class="stage-decision">
                  <button class="btn-move win" data-move="ganada">Ganada<svg viewBox="0 0 24 24"><use href="#i-check"/></svg></button>
                  <button class="btn-move lose" data-move="perdida">Perdida<svg viewBox="0 0 24 24"><use href="#i-x-circle"/></svg></button>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <?php if ( $etapa['permite_crear'] ) : ?>
          <button class="col-add btn-agregar-etapa" data-etapa="<?php echo esc_attr( $etapa_key ); ?>"><svg viewBox="0 0 24 24"><use href="#i-plus"/></svg>Agregar</button>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="modal-overlay" id="modalNuevaOportunidad">
  <div class="modal-box">
    <h4>Nueva Oportunidad</h4>
    <p class="modal-sub">Captura los datos iniciales. Podrás agregar los conceptos de la cotización después, desde wp-admin.</p>
    <div class="form-field">
      <label for="campoEmpresa">Empresa</label>
      <select id="campoEmpresa">
        <option value="" disabled selected>Selecciona una empresa…</option>
        <?php foreach ( $talos_opp_empresas_ids as $empresa_id ) : ?>
          <option value="<?php echo esc_attr( $empresa_id ); ?>"><?php echo esc_html( get_the_title( $empresa_id ) ); ?></option>
        <?php endforeach; ?>
      </select>
      <span class="field-hint">¿No está en la lista? Primero créala en Empresas.</span>
    </div>
    <div class="form-field">
      <label for="campoNombre">Nombre de la oportunidad</label>
      <input type="text" id="campoNombre" placeholder="Ej. Página Web + Hosting">
    </div>
    <div class="form-row">
      <div class="form-field">
        <label for="campoValor">Valor estimado (MXN)</label>
        <input type="number" id="campoValor" placeholder="0" step="100" min="0">
      </div>
      <div class="form-field">
        <label for="campoEtapa">Etapa inicial</label>
        <select id="campoEtapa">
          <option value="prospeccion">Prospección</option>
          <option value="propuesta_enviada">Propuesta Enviada</option>
          <option value="en_negociacion">En Negociación</option>
        </select>
      </div>
    </div>
    <span class="field-hint" style="display:block;margin:-8px 0 14px;">El valor captura un concepto genérico en la cotización — ábrela después en wp-admin para detallar los conceptos reales.</span>
    <div class="modal-actions">
      <button class="btn-ghost" data-close-modal>Cancelar</button>
      <button class="btn-confirm" id="btnConfirmarNuevaOportunidad">Crear Oportunidad</button>
    </div>
  </div>
</div>

<div class="toast" id="toastOportunidades"><svg viewBox="0 0 24 24"><use href="#i-check"/></svg><span id="toastOportunidadesTexto"></span></div>

<script>
try{
  var talosNonceOpp = '<?php echo esc_js( wp_create_nonce( 'talos_oportunidades' ) ); ?>';
  var talosAjaxUrlOpp = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

  function mostrarToastOpp(texto){
    var toast = document.getElementById('toastOportunidades');
    document.getElementById('toastOportunidadesTexto').textContent = texto;
    toast.classList.add('show');
    clearTimeout(toast._t);
    toast._t = setTimeout(function(){ toast.classList.remove('show'); }, 2600);
  }

  document.querySelectorAll('[data-action="ver"]').forEach(function(btn){
    btn.addEventListener('click', function(){
      mostrarToastOpp('Ver datos de "' + btn.getAttribute('data-oportunidad') + '" — pantalla por diseñar en la siguiente fase');
    });
  });
  document.querySelectorAll('[data-action="editar"]').forEach(function(btn){
    btn.addEventListener('click', function(){
      mostrarToastOpp('Editar "' + btn.getAttribute('data-oportunidad') + '" — pantalla por diseñar en la siguiente fase');
    });
  });

  // Mover de etapa: siguiente etapa / Ganada / Perdida — AJAX real, recarga al terminar
  document.querySelectorAll('[data-move]').forEach(function(btn){
    btn.addEventListener('click', function(){
      var card = btn.closest('.deal-card');
      var id = card.getAttribute('data-id');
      var etapa = btn.getAttribute('data-move');
      btn.disabled = true;
      var form = new FormData();
      form.append('action', 'talos_mover_etapa_oportunidad');
      form.append('nonce', talosNonceOpp);
      form.append('oportunidad_id', id);
      form.append('etapa', etapa);
      fetch(talosAjaxUrlOpp, { method: 'POST', body: form, credentials: 'same-origin' }).then(function(r){ return r.json(); }).then(function(res){
        if (!res.success){
          mostrarToastOpp(res.data && res.data.mensaje ? res.data.mensaje : 'No se pudo mover la oportunidad.');
          btn.disabled = false;
          return;
        }
        window.location.reload();
      });
    });
  });

  // Panel Nueva Oportunidad
  var modalNueva = document.getElementById('modalNuevaOportunidad');
  var campoEmpresa = document.getElementById('campoEmpresa');
  var campoNombre = document.getElementById('campoNombre');
  var campoValor = document.getElementById('campoValor');
  var campoEtapa = document.getElementById('campoEtapa');

  function abrirModalNuevaOportunidad(etapaDefault){
    campoEmpresa.value = '';
    campoNombre.value = '';
    campoValor.value = '';
    campoEtapa.value = etapaDefault || 'prospeccion';
    modalNueva.classList.add('show');
  }
  document.getElementById('btnNuevaOportunidad').addEventListener('click', function(){ abrirModalNuevaOportunidad('prospeccion'); });
  document.querySelectorAll('.btn-agregar-etapa').forEach(function(btn){
    btn.addEventListener('click', function(){ abrirModalNuevaOportunidad(btn.getAttribute('data-etapa')); });
  });
  modalNueva.querySelectorAll('[data-close-modal]').forEach(function(b){ b.addEventListener('click', function(){ modalNueva.classList.remove('show'); }); });
  modalNueva.addEventListener('click', function(e){ if (e.target === modalNueva) modalNueva.classList.remove('show'); });

  document.getElementById('btnConfirmarNuevaOportunidad').addEventListener('click', function(){
    var empresaId = campoEmpresa.value;
    var nombre = campoNombre.value.trim();
    if (!empresaId || !nombre){
      mostrarToastOpp('Selecciona una empresa y captura el nombre de la oportunidad');
      return;
    }
    var btn = this;
    btn.disabled = true;
    var form = new FormData();
    form.append('action', 'talos_crear_oportunidad');
    form.append('nonce', talosNonceOpp);
    form.append('empresa_id', empresaId);
    form.append('nombre', nombre);
    form.append('valor_estimado', campoValor.value || 0);
    form.append('etapa', campoEtapa.value);
    fetch(talosAjaxUrlOpp, { method: 'POST', body: form, credentials: 'same-origin' }).then(function(r){ return r.json(); }).then(function(res){
      if (!res.success){
        mostrarToastOpp(res.data && res.data.mensaje ? res.data.mensaje : 'No se pudo crear la oportunidad.');
        btn.disabled = false;
        return;
      }
      window.location.reload();
    });
  });
}catch(e){ console.error(e); }
</script>

<?php get_footer(); ?>
