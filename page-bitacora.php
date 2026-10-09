<?php
/**
 * Bitácora de Peticiones (talos_request) — vista Kanban, mismo patrón visual
 * que Oportunidades (board-scroll/board/board-col/deal-card) pero sin
 * drag&drop: mover de etapa es un botón fijo por tarjeta ("Siguiente etapa"),
 * según decisión explícita de Jorge. Reemplaza al formulario de Fluent Forms
 * "Bitácora Once24" (que mandaba a Trello + Google Sheets) — ver conversación
 * de referencia: mismos campos (Empresa/Tipo/Descripción/Prioridad/Fecha de
 * Entrega), con dos cambios: "Cuenta" ahora es relación real a talos_company
 * en vez de una lista de texto fija, y "Elige tu Nombre" se elimina — el
 * autor se captura solo del usuario de WordPress con sesión (post_author).
 *
 * Crear/editar/mover NO requieren manage_options (ver ajax-bitacora.php) para
 * que el rol Consulta pueda registrar y avanzar sus propias peticiones sin
 * acceso de Director — ese es el objetivo del módulo. Eliminar sí se limita
 * a quien la creó o a un Director, y solo entonces se muestra el botón.
 *
 * Una sola modal sirve para Nueva Petición y Editar Petición (el formulario
 * es corto, no justifica una ficha de detalle aparte como Empresas/Contactos).
 */

$talos_bit_etapas = array(
    'pendiente'  => array( 'label' => 'Pendiente',   'color' => '#94a3b8',     'siguiente' => 'en_proceso' ),
    'en_proceso' => array( 'label' => 'En Proceso',  'color' => 'var(--accent)', 'siguiente' => 'completada' ),
    'completada' => array( 'label' => 'Completada',  'color' => 'var(--success)', 'siguiente' => null ),
);
$talos_bit_tipos = talos_bit_tipo_labels();
$talos_bit_prioridades = talos_bit_prioridad_labels();

$talos_bit_ids = get_posts( array(
    'post_type'      => 'talos_request',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'orderby'        => 'date',
    'order'          => 'DESC',
) );

$talos_bit_hoy = new DateTime( 'today' );
$talos_bit_por_etapa = array_fill_keys( array_keys( $talos_bit_etapas ), array() );
$talos_bit_datos = array();
$talos_bit_uid_actual = get_current_user_id();

foreach ( $talos_bit_ids as $id ) {
    $etapa = get_field( 'request_status', $id );
    if ( ! isset( $talos_bit_por_etapa[ $etapa ] ) ) continue;

    $empresa = get_field( 'request_company', $id );
    $tipo = get_field( 'request_type', $id );
    $tipo_otro = get_field( 'request_type_other', $id );
    $prioridad = get_field( 'request_priority', $id );
    $prioridad_otro = get_field( 'request_priority_other', $id );
    $fecha_entrega = get_field( 'request_due_date', $id );
    $autor_id = (int) get_post_field( 'post_author', $id );

    $dias = null;
    if ( $fecha_entrega ) {
        $venc = DateTime::createFromFormat( 'Y-m-d', $fecha_entrega );
        if ( $venc ) $dias = (int) $talos_bit_hoy->diff( $venc )->format( '%r%a' );
    }

    $talos_bit_datos[ $id ] = array(
        'empresa_id'      => ( $empresa instanceof WP_Post ) ? $empresa->ID : 0,
        'empresa'         => ( $empresa instanceof WP_Post ) ? $empresa->post_title : '—',
        'tipo'            => $tipo,
        'tipo_label'      => ( 'otro' === $tipo && $tipo_otro ) ? $tipo_otro : ( $talos_bit_tipos[ $tipo ] ?? $tipo ),
        'tipo_otro'       => $tipo_otro,
        'descripcion'     => get_field( 'request_description', $id ),
        'prioridad'       => $prioridad,
        'prioridad_label' => ( 'otra' === $prioridad && $prioridad_otro ) ? $prioridad_otro : ( $talos_bit_prioridades[ $prioridad ] ?? $prioridad ),
        'prioridad_otro'  => $prioridad_otro,
        'fecha_entrega'   => $fecha_entrega,
        'dias'            => $dias,
        'autor_id'        => $autor_id,
        'autor_nombre'    => $autor_id ? get_the_author_meta( 'display_name', $autor_id ) : '—',
        'modificado_mes'  => get_post_modified_time( 'Y-m', false, $id ),
        'puede_eliminar'  => current_user_can( 'manage_options' ) || $autor_id === $talos_bit_uid_actual,
    );
    $talos_bit_por_etapa[ $etapa ][] = $id;
}

$talos_bit_kpi_vencidas = 0;
foreach ( array( 'pendiente', 'en_proceso' ) as $k ) {
    foreach ( $talos_bit_por_etapa[ $k ] as $id ) {
        if ( null !== $talos_bit_datos[ $id ]['dias'] && $talos_bit_datos[ $id ]['dias'] < 0 ) $talos_bit_kpi_vencidas++;
    }
}
$talos_mes_actual_ymd_bit = current_time( 'Y-m' );
$talos_bit_kpi_completadas_mes = 0;
foreach ( $talos_bit_por_etapa['completada'] as $id ) {
    if ( $talos_bit_datos[ $id ]['modificado_mes'] === $talos_mes_actual_ymd_bit ) $talos_bit_kpi_completadas_mes++;
}

$talos_bit_empresas_ids = get_posts( array(
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
  .board{display:flex;gap:16px;align-items:flex-start;min-width:1020px;}
  .board-col{width:272px;flex:none;display:flex;flex-direction:column;gap:12px;}
  .col-head{display:flex;align-items:center;gap:8px;padding:2px 4px;}
  .col-dot{width:9px;height:9px;border-radius:50%;flex:none;}
  .col-title{font-size:13px;font-weight:700;flex:1;}
  .col-count{font-size:11px;font-weight:700;color:var(--text-muted);background:var(--surface);border:1px solid var(--border);padding:1px 8px;border-radius:99px;}
  .col-body{display:flex;flex-direction:column;gap:10px;min-height:60px;}
  .col-add{display:flex;align-items:center;justify-content:center;gap:6px;border:1.5px dashed var(--border);border-radius:10px;padding:9px;color:var(--text-muted);font-size:12px;font-weight:600;cursor:pointer;background:transparent;width:100%;}
  .col-add svg{width:14px;height:14px;}
  .col-add:hover{border-color:var(--accent);color:var(--accent);}

  .req-card{background:var(--surface);border:1px solid var(--border);border-top:3px solid var(--border);border-radius:11px;padding:13px 14px;display:flex;flex-direction:column;gap:9px;}
  .req-card.c-pendiente{border-top-color:#94a3b8;}
  .req-card.c-en_proceso{border-top-color:var(--accent);}
  .req-card.c-completada{border-top-color:var(--success);}
  .req-company{font-size:11.5px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.03em;}
  .req-tipo{font-size:13.5px;font-weight:700;line-height:1.3;}
  .req-desc{font-size:12px;color:var(--text-muted);line-height:1.4;}
  .req-foot{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:2px;}
  .req-fecha{font-size:10.5px;color:var(--text-muted);}
  .req-ava{width:24px;height:24px;border-radius:50%;background:var(--accent-soft);color:var(--accent);display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;flex:none;}
  .req-badges{display:flex;gap:6px;flex-wrap:wrap;}
  .req-badge{display:inline-flex;align-items:center;gap:4px;font-size:10.5px;font-weight:700;padding:2px 7px;border-radius:99px;width:fit-content;}
  .req-badge.urgente{background:var(--danger-soft);color:var(--danger);}
  .req-badge.media{background:#fdf3d8;color:#8a6200;}
  .req-badge.baja{background:var(--surface);color:var(--text-muted);border:1px solid var(--border);}
  .req-badge.otra{background:var(--surface);color:var(--text-muted);border:1px solid var(--border);}
  .req-badge.vencida{background:var(--danger-soft);color:var(--danger);}
  .req-badge svg{width:10px;height:10px;}

  .req-actions{display:flex;align-items:center;gap:6px;border-top:1px solid var(--border);padding-top:9px;margin-top:2px;}
  .req-actions .action-btn{width:26px;height:26px;}
  .req-actions .action-btn svg{width:13px;height:13px;}
  .btn-move{display:flex;align-items:center;justify-content:center;gap:5px;border:1px dashed var(--border);background:transparent;color:var(--text-muted);border-radius:7px;padding:6px 8px;font-size:11px;font-weight:700;cursor:pointer;white-space:nowrap;flex:1;}
  .btn-move:hover{border-color:var(--accent);color:var(--accent);}
  .btn-move svg{width:11px;height:11px;}

  @media (max-width:860px){.board-col{width:240px;}}
</style>

<div class="page-head">
  <div>
    <h1 class="page-title">Bitácora de Peticiones</h1>
    <p class="page-sub">Solicitudes de cuentas en curso</p>
  </div>
  <button class="btn-primary" id="btnNuevaPeticion"><svg viewBox="0 0 24 24"><use href="#i-plus"/></svg>Nueva Petición</button>
</div>

<div class="kpi-strip">
  <div class="metric-box stripe-accent"><span class="label">Pendientes</span><span class="value tabular"><?php echo esc_html( count( $talos_bit_por_etapa['pendiente'] ) ); ?></span></div>
  <div class="metric-box"><span class="label">En Proceso</span><span class="value tabular"><?php echo esc_html( count( $talos_bit_por_etapa['en_proceso'] ) ); ?></span></div>
  <div class="metric-box stripe-danger"><span class="label">Vencidas</span><span class="value tabular"><?php echo esc_html( $talos_bit_kpi_vencidas ); ?></span></div>
  <div class="metric-box stripe-success"><span class="label">Completadas este mes</span><span class="value tabular"><?php echo esc_html( $talos_bit_kpi_completadas_mes ); ?></span></div>
</div>

<div class="board-scroll">
  <div class="board">
    <?php foreach ( $talos_bit_etapas as $etapa_key => $etapa ) :
        $ids_etapa = $talos_bit_por_etapa[ $etapa_key ];
        ?>
      <div class="board-col" data-stage="<?php echo esc_attr( $etapa_key ); ?>">
        <div class="col-head">
          <span class="col-dot" style="background:<?php echo esc_attr( $etapa['color'] ); ?>"></span>
          <span class="col-title"><?php echo esc_html( $etapa['label'] ); ?></span>
          <span class="col-count"><?php echo esc_html( count( $ids_etapa ) ); ?></span>
        </div>
        <div class="col-body">
          <?php foreach ( $ids_etapa as $id ) :
              $d = $talos_bit_datos[ $id ];
              ?>
            <div class="req-card c-<?php echo esc_attr( $etapa_key ); ?>"
                 data-id="<?php echo esc_attr( $id ); ?>"
                 data-empresa-id="<?php echo esc_attr( $d['empresa_id'] ); ?>"
                 data-tipo="<?php echo esc_attr( $d['tipo'] ); ?>"
                 data-tipo-otro="<?php echo esc_attr( $d['tipo_otro'] ); ?>"
                 data-descripcion="<?php echo esc_attr( $d['descripcion'] ); ?>"
                 data-prioridad="<?php echo esc_attr( $d['prioridad'] ); ?>"
                 data-prioridad-otro="<?php echo esc_attr( $d['prioridad_otro'] ); ?>"
                 data-fecha="<?php echo esc_attr( $d['fecha_entrega'] ); ?>">
              <div class="req-company"><?php echo esc_html( $d['empresa'] ); ?></div>
              <div class="req-tipo"><?php echo esc_html( $d['tipo_label'] ); ?></div>
              <div class="req-desc"><?php echo esc_html( wp_trim_words( $d['descripcion'], 14, '…' ) ); ?></div>
              <div class="req-badges">
                <span class="req-badge <?php echo esc_attr( $d['prioridad'] ); ?>"><?php echo esc_html( $d['prioridad_label'] ); ?></span>
                <?php if ( 'completada' !== $etapa_key && null !== $d['dias'] && $d['dias'] < 0 ) : ?>
                  <span class="req-badge vencida"><svg viewBox="0 0 24 24"><use href="#i-alert-clock"/></svg>Venció hace <?php echo esc_html( abs( $d['dias'] ) ); ?> días</span>
                <?php endif; ?>
              </div>
              <div class="req-foot">
                <span class="req-fecha"><?php echo esc_html( $d['fecha_entrega'] ? date_i18n( 'd/m', strtotime( $d['fecha_entrega'] ) ) : '—' ); ?></span>
                <div class="req-ava" title="<?php echo esc_attr( $d['autor_nombre'] ); ?>"><?php echo esc_html( talos_iniciales( $d['autor_nombre'] ) ); ?></div>
              </div>
              <div class="req-actions">
                <button class="action-btn edit btn-editar-peticion" title="Editar"><svg viewBox="0 0 24 24"><use href="#i-edit"/></svg></button>
                <?php if ( $d['puede_eliminar'] ) : ?>
                  <button class="action-btn btn-eliminar-peticion" title="Eliminar"><svg viewBox="0 0 24 24"><use href="#i-trash"/></svg></button>
                <?php endif; ?>
                <?php if ( $etapa['siguiente'] ) : ?>
                  <button class="btn-move" data-move="<?php echo esc_attr( $etapa['siguiente'] ); ?>"><?php echo esc_html( $talos_bit_etapas[ $etapa['siguiente'] ]['label'] ); ?><svg viewBox="0 0 24 24"><use href="#i-arrow-right"/></svg></button>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <?php if ( 'pendiente' === $etapa_key ) : ?>
          <button class="col-add" id="btnAgregarPendiente"><svg viewBox="0 0 24 24"><use href="#i-plus"/></svg>Agregar</button>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="modal-overlay" id="modalPeticion">
  <div class="modal-box">
    <h4 id="modalPeticionTitulo">Nueva Petición</h4>
    <p class="modal-sub">Captura exactamente lo que solicita el cliente.</p>
    <div class="form-field">
      <label for="campoBitEmpresa">Empresa</label>
      <select id="campoBitEmpresa">
        <option value="" disabled selected>Selecciona una empresa…</option>
        <?php foreach ( $talos_bit_empresas_ids as $empresa_id ) : ?>
          <option value="<?php echo esc_attr( $empresa_id ); ?>"><?php echo esc_html( get_the_title( $empresa_id ) ); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-field">
      <label for="campoBitTipo">Tipo de Solicitud</label>
      <select id="campoBitTipo">
        <?php foreach ( $talos_bit_tipos as $key => $label ) : ?>
          <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-field" id="wrapBitTipoOtro" hidden>
      <label for="campoBitTipoOtro">Especifica el tipo de solicitud</label>
      <input type="text" id="campoBitTipoOtro">
    </div>
    <div class="form-field">
      <label for="campoBitDescripcion">Descripción de lo solicitado</label>
      <textarea id="campoBitDescripcion" rows="4"></textarea>
    </div>
    <div class="form-row">
      <div class="form-field">
        <label for="campoBitPrioridad">Prioridad</label>
        <select id="campoBitPrioridad">
          <?php foreach ( $talos_bit_prioridades as $key => $label ) : ?>
            <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-field">
        <label for="campoBitFecha">Fecha de Entrega</label>
        <input type="date" id="campoBitFecha">
      </div>
    </div>
    <div class="form-field" id="wrapBitPrioridadOtro" hidden>
      <label for="campoBitPrioridadOtro">Especifica la prioridad</label>
      <input type="text" id="campoBitPrioridadOtro">
    </div>
    <span class="field-hint" style="display:block;margin:-8px 0 14px;">Las capturas de pantalla se adjuntan desde wp-admin por ahora.</span>
    <div class="modal-actions">
      <button class="btn-ghost" data-close-modal>Cancelar</button>
      <button class="btn-confirm" id="btnConfirmarPeticion">Guardar</button>
    </div>
  </div>
</div>

<div class="toast" id="toastBitacora"><svg viewBox="0 0 24 24"><use href="#i-check"/></svg><span id="toastBitacoraTexto"></span></div>

<script>
try{
  var talosNonceBit = '<?php echo esc_js( wp_create_nonce( 'talos_bitacora' ) ); ?>';
  var talosAjaxUrlBit = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
  var bitPeticionIdActual = null;

  function mostrarToastBit(texto){
    var toast = document.getElementById('toastBitacora');
    document.getElementById('toastBitacoraTexto').textContent = texto;
    toast.classList.add('show');
    clearTimeout(toast._t);
    toast._t = setTimeout(function(){ toast.classList.remove('show'); }, 2600);
  }

  var modalBit = document.getElementById('modalPeticion');
  var campoBitEmpresa = document.getElementById('campoBitEmpresa');
  var campoBitTipo = document.getElementById('campoBitTipo');
  var campoBitTipoOtro = document.getElementById('campoBitTipoOtro');
  var wrapBitTipoOtro = document.getElementById('wrapBitTipoOtro');
  var campoBitDescripcion = document.getElementById('campoBitDescripcion');
  var campoBitPrioridad = document.getElementById('campoBitPrioridad');
  var campoBitPrioridadOtro = document.getElementById('campoBitPrioridadOtro');
  var wrapBitPrioridadOtro = document.getElementById('wrapBitPrioridadOtro');
  var campoBitFecha = document.getElementById('campoBitFecha');

  function actualizarCamposOtro(){
    wrapBitTipoOtro.hidden = (campoBitTipo.value !== 'otro');
    wrapBitPrioridadOtro.hidden = (campoBitPrioridad.value !== 'otra');
  }
  campoBitTipo.addEventListener('change', actualizarCamposOtro);
  campoBitPrioridad.addEventListener('change', actualizarCamposOtro);

  function abrirModalNueva(){
    bitPeticionIdActual = null;
    document.getElementById('modalPeticionTitulo').textContent = 'Nueva Petición';
    campoBitEmpresa.value = '';
    campoBitTipo.value = 'calendario';
    campoBitTipoOtro.value = '';
    campoBitDescripcion.value = '';
    campoBitPrioridad.value = 'media';
    campoBitPrioridadOtro.value = '';
    campoBitFecha.value = '';
    actualizarCamposOtro();
    modalBit.classList.add('show');
  }

  function abrirModalEditar(card){
    bitPeticionIdActual = card.getAttribute('data-id');
    document.getElementById('modalPeticionTitulo').textContent = 'Editar Petición';
    campoBitEmpresa.value = card.getAttribute('data-empresa-id');
    campoBitTipo.value = card.getAttribute('data-tipo');
    campoBitTipoOtro.value = card.getAttribute('data-tipo-otro');
    campoBitDescripcion.value = card.getAttribute('data-descripcion');
    campoBitPrioridad.value = card.getAttribute('data-prioridad');
    campoBitPrioridadOtro.value = card.getAttribute('data-prioridad-otro');
    campoBitFecha.value = card.getAttribute('data-fecha');
    actualizarCamposOtro();
    modalBit.classList.add('show');
  }

  document.getElementById('btnNuevaPeticion').addEventListener('click', abrirModalNueva);
  document.getElementById('btnAgregarPendiente').addEventListener('click', abrirModalNueva);
  document.querySelectorAll('.btn-editar-peticion').forEach(function(btn){
    btn.addEventListener('click', function(){ abrirModalEditar(btn.closest('.req-card')); });
  });
  modalBit.querySelectorAll('[data-close-modal]').forEach(function(b){ b.addEventListener('click', function(){ modalBit.classList.remove('show'); }); });
  modalBit.addEventListener('click', function(e){ if (e.target === modalBit) modalBit.classList.remove('show'); });

  document.getElementById('btnConfirmarPeticion').addEventListener('click', function(){
    var empresaId = campoBitEmpresa.value;
    var descripcion = campoBitDescripcion.value.trim();
    var fecha = campoBitFecha.value;
    if (!empresaId || !descripcion || !fecha){
      mostrarToastBit('Selecciona la empresa, describe la solicitud y captura la fecha de entrega');
      return;
    }
    var btn = this;
    btn.disabled = true;
    var form = new FormData();
    form.append('action', bitPeticionIdActual ? 'talos_guardar_peticion' : 'talos_crear_peticion');
    form.append('nonce', talosNonceBit);
    if (bitPeticionIdActual) form.append('peticion_id', bitPeticionIdActual);
    form.append('empresa_id', empresaId);
    form.append('tipo', campoBitTipo.value);
    form.append('tipo_otro', campoBitTipoOtro.value);
    form.append('descripcion', descripcion);
    form.append('prioridad', campoBitPrioridad.value);
    form.append('prioridad_otro', campoBitPrioridadOtro.value);
    form.append('fecha_entrega', fecha);
    fetch(talosAjaxUrlBit, { method: 'POST', body: form, credentials: 'same-origin' }).then(function(r){ return r.json(); }).then(function(res){
      if (!res.success){
        mostrarToastBit(res.data && res.data.mensaje ? res.data.mensaje : 'No se pudo guardar la petición.');
        btn.disabled = false;
        return;
      }
      window.location.reload();
    });
  });

  // Mover de etapa: botón fijo por tarjeta, sin drag&drop.
  document.querySelectorAll('[data-move]').forEach(function(btn){
    btn.addEventListener('click', function(){
      var card = btn.closest('.req-card');
      var id = card.getAttribute('data-id');
      var etapa = btn.getAttribute('data-move');
      btn.disabled = true;
      var form = new FormData();
      form.append('action', 'talos_mover_etapa_peticion');
      form.append('nonce', talosNonceBit);
      form.append('peticion_id', id);
      form.append('etapa', etapa);
      fetch(talosAjaxUrlBit, { method: 'POST', body: form, credentials: 'same-origin' }).then(function(r){ return r.json(); }).then(function(res){
        if (!res.success){
          mostrarToastBit(res.data && res.data.mensaje ? res.data.mensaje : 'No se pudo mover la petición.');
          btn.disabled = false;
          return;
        }
        window.location.reload();
      });
    });
  });

  document.querySelectorAll('.btn-eliminar-peticion').forEach(function(btn){
    btn.addEventListener('click', function(){
      if (!confirm('¿Eliminar esta petición? Se mueve a la papelera, se puede recuperar desde wp-admin.')) return;
      var card = btn.closest('.req-card');
      var id = card.getAttribute('data-id');
      btn.disabled = true;
      var form = new FormData();
      form.append('action', 'talos_eliminar_peticion');
      form.append('nonce', talosNonceBit);
      form.append('peticion_id', id);
      fetch(talosAjaxUrlBit, { method: 'POST', body: form, credentials: 'same-origin' }).then(function(r){ return r.json(); }).then(function(res){
        if (!res.success){
          mostrarToastBit(res.data && res.data.mensaje ? res.data.mensaje : 'No se pudo eliminar la petición.');
          btn.disabled = false;
          return;
        }
        window.location.reload();
      });
    });
  });
}catch(e){ console.error(e); }
</script>

<?php get_footer(); ?>
