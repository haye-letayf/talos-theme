<?php
/**
 * Generar Mes desde el front (Ingresos y Gastos).
 *
 * Solo dispara el motor de talos-core (talos_generar_periodo), el mismo de la
 * pantalla de wp-admin, que se queda como respaldo. Es seguro correrlo varias
 * veces: el motor no duplica. Solo Director (manage_options).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function talos_ajax_generar_mes() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'talos_generar_mes_front' ) ) {
        wp_send_json_error( array( 'mensaje' => 'La sesión expiró. Recarga la página.' ) );
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'mensaje' => 'No tienes permiso para generar el mes.' ) );
    }
    if ( ! function_exists( 'talos_generar_periodo' ) || ! defined( 'TALOS_PRIMER_MES_MOTOR' ) ) {
        wp_send_json_error( array( 'mensaje' => 'Falta talos-core: no se generó nada.' ) );
    }

    $mes = DateTime::createFromFormat( '!Y-m-d', ( isset( $_POST['mes'] ) ? sanitize_text_field( wp_unslash( $_POST['mes'] ) ) : '' ) . '-01' );
    if ( ! $mes ) {
        wp_send_json_error( array( 'mensaje' => 'Elige el mes a generar.' ) );
    }
    if ( $mes->format( 'Y-m' ) < TALOS_PRIMER_MES_MOTOR ) {
        wp_send_json_error( array( 'mensaje' => 'Solo se genera de ' . TALOS_PRIMER_MES_MOTOR . ' en adelante; lo anterior es histórico.' ) );
    }

    $r = talos_generar_periodo( $mes );

    wp_send_json_success( array(
        'mes'       => $mes->format( 'Y-m' ),
        'ingresos'  => (int) ( $r['income_creados'] ?? 0 ),
        'servicios' => (int) ( $r['servicios_desactivados'] ?? 0 ),
        'gastos'    => (int) ( $r['gastos_creados'] ?? 0 ),
        'nominas'   => (int) ( $r['nominas_creadas'] ?? 0 ),
        'plantillas'=> (int) ( $r['plantillas_desactivadas'] ?? 0 ),
    ) );
}
add_action( 'wp_ajax_talos_generar_mes_front', 'talos_ajax_generar_mes' );

/** Botón para el encabezado de la página. */
function talos_generar_mes_boton() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    echo '<button type="button" class="btn-secondary" id="btnGenerarMes"><svg viewBox="0 0 24 24"><use href="#i-clock"/></svg>Generar Mes</button>';
}

/** Modal + script. Va una vez por página, después del contenido. */
function talos_generar_mes_modal( DateTime $mes_viendo ) {
    if ( ! current_user_can( 'manage_options' ) || ! defined( 'TALOS_PRIMER_MES_MOTOR' ) ) {
        return;
    }
    ?>
<div class="modal-overlay" id="modalGenerarMes">
  <div class="modal-box">
    <div class="modal-icon"><svg viewBox="0 0 24 24"><use href="#i-clock"/></svg></div>
    <h4>Generar Mes</h4>
    <p class="modal-sub">Crea los Ingresos de los servicios activos, los Gastos de las plantillas recurrentes y las dos quincenas de nómina. Es seguro correrlo varias veces: no duplica lo que ya existe.</p>
    <div id="gmFormulario">
      <div class="form-field">
        <label for="gmMes">Mes a generar</label>
        <input type="month" id="gmMes" value="<?php echo esc_attr( $mes_viendo->format( 'Y-m' ) ); ?>" min="<?php echo esc_attr( TALOS_PRIMER_MES_MOTOR ); ?>">
      </div>
      <div class="modal-actions">
        <button class="btn-ghost" data-close-modal>Cancelar</button>
        <button class="btn-confirm" id="btnConfirmarGenerarMes">Generar</button>
      </div>
    </div>
    <div id="gmResultado" hidden>
      <ul id="gmLista" style="margin:0 0 18px;padding-left:20px;font-size:13px;line-height:1.7;"></ul>
      <div class="modal-actions">
        <button class="btn-confirm" id="btnCerrarGenerarMes">Listo</button>
      </div>
    </div>
  </div>
</div>
<script>
(function(){
  var modal = document.getElementById('modalGenerarMes');
  var btnAbrir = document.getElementById('btnGenerarMes');
  if (!modal || !btnAbrir) return;
  var mesGenerado = '';
  btnAbrir.addEventListener('click', function(){
    document.getElementById('gmFormulario').hidden = false;
    document.getElementById('gmResultado').hidden = true;
    modal.classList.add('show');
  });
  modal.querySelectorAll('[data-close-modal]').forEach(function(b){ b.addEventListener('click', function(){ modal.classList.remove('show'); }); });
  document.getElementById('btnCerrarGenerarMes').addEventListener('click', function(){
    window.location.href = window.location.pathname + '?mes=' + mesGenerado;
  });
  document.getElementById('btnConfirmarGenerarMes').addEventListener('click', function(){
    var btn = this;
    var lista = document.getElementById('gmLista');
    var form = new FormData();
    form.append('action', 'talos_generar_mes_front');
    form.append('nonce', '<?php echo esc_js( wp_create_nonce( 'talos_generar_mes_front' ) ); ?>');
    form.append('mes', document.getElementById('gmMes').value);
    btn.disabled = true;
    btn.textContent = 'Generando…';
    fetch('<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', { method: 'POST', body: form, credentials: 'same-origin' }).then(function(r){ return r.json(); }).then(function(res){
      btn.disabled = false;
      btn.textContent = 'Generar';
      lista.innerHTML = '';
      if (!res.success){
        var li = document.createElement('li');
        li.textContent = res.data && res.data.mensaje ? res.data.mensaje : 'No se pudo generar el mes.';
        lista.appendChild(li);
        mesGenerado = document.getElementById('gmMes').value;
      } else {
        var d = res.data;
        mesGenerado = d.mes;
        [
          d.ingresos + ' ingreso(s) nuevo(s)',
          d.gastos + ' gasto(s) nuevo(s)',
          d.nominas + ' quincena(s) de nómina',
          d.servicios + ' servicio(s) desactivado(s) por vencimiento',
          d.plantillas + ' plantilla(s) de gasto desactivada(s) por vencimiento'
        ].forEach(function(t){ var li = document.createElement('li'); li.textContent = t; lista.appendChild(li); });
      }
      document.getElementById('gmFormulario').hidden = true;
      document.getElementById('gmResultado').hidden = false;
    }).catch(function(){
      btn.disabled = false;
      btn.textContent = 'Generar';
      mesGenerado = document.getElementById('gmMes').value;
      lista.innerHTML = '<li>No se pudo conectar. Puedes volver a intentarlo: no duplica.</li>';
      document.getElementById('gmFormulario').hidden = true;
      document.getElementById('gmResultado').hidden = false;
    });
  });
})();
</script>
    <?php
}
