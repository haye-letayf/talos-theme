<?php
/**
 * Facturas (CFDI) por Empresa y mes: AJAX y modal compartido por Ingresos y la
 * ficha de Empresa. La lógica (lectura del XML, validaciones, carpeta
 * protegida) vive en talos-core, includes/facturas.php; aquí solo se recibe y
 * se pinta. Solo Director (manage_options).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function talos_fac_verificar() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'talos_facturas' ) ) {
        wp_send_json_error( array( 'mensaje' => 'La sesión expiró. Recarga la página.' ) );
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'mensaje' => 'No tienes permiso para manejar facturas.' ) );
    }
    if ( ! function_exists( 'talos_factura_guardar' ) ) {
        wp_send_json_error( array( 'mensaje' => 'Falta actualizar talos-core.' ) );
    }
}

function talos_fac_resumen_json( $empresa_id, $ym ) {
    $r = talos_facturas_resumen( $empresa_id, $ym );
    $r['empresa_id'] = (int) $empresa_id;
    $r['mes']        = substr( $ym, 0, 4 ) . '-' . substr( $ym, 4, 2 );
    $r['mes_texto']  = talos_factura_mes_texto( $ym );
    return $r;
}

function talos_fac_leer_peticion() {
    $empresa_id = isset( $_POST['empresa_id'] ) ? (int) $_POST['empresa_id'] : 0;
    $ym         = talos_factura_ym( isset( $_POST['mes'] ) ? sanitize_text_field( wp_unslash( $_POST['mes'] ) ) : '' );
    if ( ! $empresa_id || 'talos_company' !== get_post_type( $empresa_id ) || 6 !== strlen( $ym ) ) {
        wp_send_json_error( array( 'mensaje' => 'Empresa o mes no válidos.' ) );
    }
    return array( $empresa_id, $ym );
}

function talos_ajax_facturas_resumen() {
    talos_fac_verificar();
    list( $empresa_id, $ym ) = talos_fac_leer_peticion();
    wp_send_json_success( talos_fac_resumen_json( $empresa_id, $ym ) );
}
add_action( 'wp_ajax_talos_facturas_resumen', 'talos_ajax_facturas_resumen' );

function talos_ajax_factura_subir() {
    talos_fac_verificar();
    list( $empresa_id, $ym ) = talos_fac_leer_peticion();
    if ( empty( $_FILES['pdf'] ) || empty( $_FILES['xml'] ) ) {
        wp_send_json_error( array( 'mensaje' => 'Sube el PDF y el XML de la factura.' ) );
    }
    $actualizar_ficha = ! empty( $_POST['actualizar_ficha'] );
    $r = talos_factura_guardar( $empresa_id, $ym, $_FILES['pdf'], $_FILES['xml'], $actualizar_ficha ); // phpcs:ignore -- se valida en talos-core
    if ( is_wp_error( $r ) ) {
        $error = array( 'mensaje' => $r->get_error_message() );
        if ( 'rfc_distinto' === $r->get_error_code() ) {
            $error['conflicto'] = $r->get_error_data();
        }
        wp_send_json_error( $error );
    }
    $json = talos_fac_resumen_json( $empresa_id, $ym );
    $json['avisos'] = $r['avisos'];
    wp_send_json_success( $json );
}
add_action( 'wp_ajax_talos_factura_subir', 'talos_ajax_factura_subir' );

function talos_ajax_factura_eliminar() {
    talos_fac_verificar();
    $id = isset( $_POST['factura_id'] ) ? (int) $_POST['factura_id'] : 0;
    if ( 'talos_invoice' !== get_post_type( $id ) ) {
        wp_send_json_error( array( 'mensaje' => 'Factura no válida.' ) );
    }
    $empresa_id = (int) get_field( 'invoice_company', $id );
    $ym         = talos_factura_ym( get_field( 'invoice_month', $id ) );
    if ( ! talos_factura_eliminar( $id ) ) {
        wp_send_json_error( array( 'mensaje' => 'No se pudo eliminar la factura.' ) );
    }
    wp_send_json_success( talos_fac_resumen_json( $empresa_id, $ym ) );
}
add_action( 'wp_ajax_talos_factura_eliminar', 'talos_ajax_factura_eliminar' );

/**
 * Modal + script. Expone window.talosFacturas.abrir(empresaId, nombre, 'AAAA-MM', mesEditable)
 * y avisa los cambios con el evento 'talos:facturas' (detail = resumen).
 */
function talos_facturas_modal() {
    if ( ! current_user_can( 'manage_options' ) || ! function_exists( 'talos_factura_guardar' ) ) {
        return;
    }
    ?>
<style>
  .fc-lista{margin:0 0 14px;display:flex;flex-direction:column;gap:8px;}
  .fc-item{display:flex;align-items:center;gap:10px;border:1px solid var(--border);border-radius:9px;padding:9px 12px;font-size:13px;}
  .fc-item .fc-folio{font-weight:700;}
  .fc-item .fc-meta{color:var(--text-muted);font-size:12px;flex:1;}
  .fc-item a{font-size:12px;font-weight:700;color:var(--accent);text-decoration:none;}
  .fc-item .fc-borrar{background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:12px;padding:2px 4px;}
  .fc-item .fc-borrar.confirmar{color:var(--danger);font-weight:700;}
  .fc-resumen{font-size:12.5px;border-radius:9px;padding:9px 12px;margin:0 0 14px;background:var(--surface-2);}
  .fc-resumen.ok{background:var(--success-soft, var(--surface-2));color:var(--success);}
  .fc-resumen.alerta{background:var(--danger-soft);color:var(--danger);}
  .fc-avisos{font-size:12.5px;color:var(--danger);margin:0 0 12px;}
  .fc-avisos:empty{display:none;}
  .fc-conflicto{border:1px solid var(--danger);border-radius:9px;padding:12px;margin:0 0 14px;font-size:12.5px;}
  .fc-conflicto[hidden]{display:none!important;}
  .fc-conflicto table{width:100%;border-collapse:collapse;margin:8px 0 12px;}
  .fc-conflicto th,.fc-conflicto td{text-align:left;padding:5px 6px;border-bottom:1px solid var(--border);vertical-align:top;}
  .fc-conflicto .fc-conflicto-acciones{display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;}
</style>
<div class="modal-overlay" id="modalFacturas">
  <div class="modal-box" style="max-width:560px;">
    <div class="modal-icon"><svg viewBox="0 0 24 24"><use href="#i-paperclip"/></svg></div>
    <h4>Facturas · <span id="fcEmpresa"></span></h4>
    <p class="modal-sub">Una factura (PDF + XML) puede cubrir varios conceptos del mes. El correo de la empresa adjunta todas las de ese mes.</p>
    <div class="form-field">
      <label for="fcMes">Mes que cubre</label>
      <input type="month" id="fcMes">
    </div>
    <div class="fc-lista" id="fcLista"></div>
    <div class="fc-resumen" id="fcResumen"></div>
    <div class="form-row">
      <div class="form-field">
        <label for="fcPdf">PDF</label>
        <input type="file" id="fcPdf" accept=".pdf,application/pdf">
      </div>
      <div class="form-field">
        <label for="fcXml">XML</label>
        <input type="file" id="fcXml" accept=".xml,text/xml,application/xml">
      </div>
    </div>
    <div class="fc-avisos" id="fcAvisos"></div>
    <div class="fc-conflicto" id="fcConflicto" hidden>
      <strong style="color:var(--danger);">El RFC de la factura no coincide con la ficha.</strong>
      <div>Casi siempre es porque se eligió otra empresa. Si de verdad es esta, la ficha se actualiza con los datos del XML.</div>
      <table>
        <tr><th></th><th>Ficha</th><th>XML</th></tr>
        <tr><th>RFC</th><td id="fcFichaRfc"></td><td id="fcXmlRfc"></td></tr>
        <tr><th>Razón social</th><td id="fcFichaRazon"></td><td id="fcXmlRazon"></td></tr>
      </table>
      <div class="fc-conflicto-acciones">
        <button class="btn-confirm" type="button" id="btnConflictoCancelar">Cancelar</button>
        <button class="btn-ghost" type="button" id="btnConflictoActualizar">Es la empresa correcta: actualizar la ficha</button>
      </div>
    </div>
    <div class="modal-actions">
      <button class="btn-ghost" data-close-modal>Cerrar</button>
      <button class="btn-confirm" id="btnSubirFactura">Subir factura</button>
    </div>
  </div>
</div>
<script>
(function(){
  var modal = document.getElementById('modalFacturas');
  var ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
  var nonce = '<?php echo esc_js( wp_create_nonce( 'talos_facturas' ) ); ?>';
  var empresaId = 0;
  var fmt = function(n){ return '$' + Number(n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
  var $ = function(id){ return document.getElementById(id); };

  function post(accion, datos){
    var form = new FormData();
    form.append('action', accion);
    form.append('nonce', nonce);
    Object.keys(datos).forEach(function(k){ form.append(k, datos[k]); });
    return fetch(ajaxUrl, { method: 'POST', body: form, credentials: 'same-origin' }).then(function(r){ return r.json(); });
  }

  function pintar(r){
    var lista = $('fcLista');
    lista.innerHTML = '';
    r.facturas.forEach(function(f){
      var item = document.createElement('div');
      item.className = 'fc-item';
      var folio = document.createElement('span'); folio.className = 'fc-folio'; folio.textContent = f.folio || 'Sin folio';
      var meta = document.createElement('span'); meta.className = 'fc-meta'; meta.textContent = (f.fecha || '') + ' · ' + fmt(f.total);
      item.appendChild(folio); item.appendChild(meta);
      ['pdf', 'xml'].forEach(function(t){
        if (!f[t]) return;
        var a = document.createElement('a'); a.href = f[t]; a.target = '_blank'; a.rel = 'noopener'; a.textContent = t.toUpperCase();
        item.appendChild(a);
      });
      var borrar = document.createElement('button');
      borrar.type = 'button'; borrar.className = 'fc-borrar'; borrar.textContent = 'Eliminar';
      borrar.addEventListener('click', function(){
        if (!borrar.classList.contains('confirmar')){ borrar.classList.add('confirmar'); borrar.textContent = '¿Seguro?'; return; }
        borrar.disabled = true;
        post('talos_factura_eliminar', { factura_id: f.id }).then(function(res){
          if (!res.success){ $('fcAvisos').textContent = res.data && res.data.mensaje ? res.data.mensaje : 'No se pudo eliminar.'; borrar.disabled = false; return; }
          pintar(res.data); avisar(res.data);
        });
      });
      item.appendChild(borrar);
      lista.appendChild(item);
    });

    var resumen = $('fcResumen');
    var conceptos = r.n_conceptos + ' concepto' + (r.n_conceptos === 1 ? '' : 's') + ' con factura en ' + r.mes_texto + ' por ' + fmt(r.total_conceptos);
    resumen.className = 'fc-resumen';
    if (r.estado === 'completa'){
      resumen.classList.add('ok'); resumen.textContent = '✓ ' + conceptos + '. Facturado: ' + fmt(r.facturado) + '.';
    } else if (r.estado === 'no_cuadra'){
      resumen.classList.add('alerta'); resumen.textContent = 'No cuadra: ' + conceptos + ', pero lo facturado suma ' + fmt(r.facturado) + '. Revisa antes de enviar.';
    } else if (r.estado === 'falta'){
      resumen.classList.add('alerta'); resumen.textContent = 'Falta la factura: ' + conceptos + '.';
    } else {
      resumen.textContent = 'Sin conceptos con factura en ' + r.mes_texto + '.';
    }
  }

  function avisar(r){ document.dispatchEvent(new CustomEvent('talos:facturas', { detail: r })); }

  function cargar(){
    $('fcLista').innerHTML = '';
    $('fcResumen').className = 'fc-resumen';
    $('fcResumen').textContent = 'Cargando…';
    post('talos_facturas_resumen', { empresa_id: empresaId, mes: $('fcMes').value }).then(function(res){
      if (!res.success){ $('fcResumen').textContent = res.data && res.data.mensaje ? res.data.mensaje : 'No se pudo cargar.'; return; }
      pintar(res.data);
    });
  }

  window.talosFacturas = {
    abrir: function(id, nombre, mes, mesEditable){
      empresaId = id;
      $('fcEmpresa').textContent = nombre;
      $('fcMes').value = mes;
      $('fcMes').disabled = !mesEditable;
      $('fcPdf').value = ''; $('fcXml').value = '';
      $('fcAvisos').textContent = '';
      $('fcConflicto').hidden = true;
      modal.classList.add('show');
      cargar();
    }
  };
  $('fcMes').addEventListener('change', cargar);
  modal.querySelectorAll('[data-close-modal]').forEach(function(b){ b.addEventListener('click', function(){ modal.classList.remove('show'); }); });

  function subir(actualizarFicha){
    var btn = $('btnSubirFactura');
    var pdf = $('fcPdf').files[0], xml = $('fcXml').files[0];
    $('fcAvisos').textContent = '';
    $('fcConflicto').hidden = true;
    if (!pdf || !xml){ $('fcAvisos').textContent = 'Elige el PDF y el XML de la factura.'; return; }
    var datos = { empresa_id: empresaId, mes: $('fcMes').value, pdf: pdf, xml: xml };
    if (actualizarFicha) datos.actualizar_ficha = '1';
    btn.disabled = true; btn.textContent = 'Subiendo…';
    post('talos_factura_subir', datos).then(function(res){
      btn.disabled = false; btn.textContent = 'Subir factura';
      if (!res.success){
        var c = res.data && res.data.conflicto;
        if (c){
          $('fcFichaRfc').textContent = c.ficha_rfc || '—';
          $('fcXmlRfc').textContent = c.xml_rfc || '—';
          $('fcFichaRazon').textContent = c.ficha_razon || '—';
          $('fcXmlRazon').textContent = c.xml_razon || '—';
          $('fcConflicto').hidden = false;
          return;
        }
        $('fcAvisos').textContent = res.data && res.data.mensaje ? res.data.mensaje : 'No se pudo subir la factura.';
        return;
      }
      $('fcPdf').value = ''; $('fcXml').value = '';
      $('fcAvisos').textContent = (res.data.avisos || []).join(' ');
      pintar(res.data); avisar(res.data);
    }).catch(function(){ btn.disabled = false; btn.textContent = 'Subir factura'; $('fcAvisos').textContent = 'No se pudo conectar. Intenta de nuevo.'; });
  }
  $('btnSubirFactura').addEventListener('click', function(){ subir(false); });
  $('btnConflictoActualizar').addEventListener('click', function(){ subir(true); });
  $('btnConflictoCancelar').addEventListener('click', function(){
    $('fcConflicto').hidden = true;
    $('fcPdf').value = ''; $('fcXml').value = '';
  });
})();
</script>
    <?php
}
