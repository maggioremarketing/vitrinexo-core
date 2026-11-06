<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Meta boxes, columnas y acciones para el CPT vx_dinner en el admin.
 */
class VX_Admin_Dinner
{
    public static function init(): void
    {
        add_filter( 'manage_vx_dinner_posts_columns',       [ self::class, 'add_columns' ] );
        add_action( 'manage_vx_dinner_posts_custom_column', [ self::class, 'render_column' ], 10, 2 );

        add_action( 'add_meta_boxes',      [ self::class, 'add_meta_boxes' ] );
        add_action( 'save_post_vx_dinner', [ self::class, 'save_meta_box' ] );

        // Acciones de asignación e invitación
        add_action( 'admin_action_vx_dinner_asignar',        [ self::class, 'handle_asignar' ] );
        add_action( 'admin_action_vx_dinner_desasignar',     [ self::class, 'handle_desasignar' ] );
        add_action( 'admin_action_vx_dinner_confirmar',      [ self::class, 'handle_confirmar' ] );
        add_action( 'admin_action_vx_dinner_invitar',        [ self::class, 'handle_invitar' ] );
        add_action( 'admin_action_vx_dinner_asig_interes',   [ self::class, 'handle_asignar_interes' ] );
        add_action( 'admin_action_vx_dinner_rechaz_interes', [ self::class, 'handle_rechazar_interes' ] );
        add_action( 'admin_action_vx_dinner_export_csv',     [ self::class, 'handle_export_csv' ] );
        add_action( 'admin_action_vx_dinner_save_mesas',     [ self::class, 'handle_save_mesas' ] );
        add_action( 'admin_action_vx_dinner_notify_mesas',  [ self::class, 'handle_notify_mesas' ] );

        // AJAX: buscar miembros para invitar
        add_action( 'wp_ajax_vx_dinner_buscar_miembros',    [ self::class, 'ajax_buscar_miembros' ] );

        add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_scripts' ] );

        // Aviso: dinner duplicado en misma ciudad
        add_action( 'admin_notices', [ self::class, 'notice_city_conflict' ] );
    }

    public static function notice_city_conflict(): void
    {
        if ( empty( $_GET['vx_dinner_conflict'] ) ) return;
        if ( get_post_type() !== 'vx_dinner' ) return;
        echo '<div class="notice notice-warning is-dismissible"><p><strong>Aviso:</strong> Ya existe otro 4Dinner con estado <em>abierto</em> o <em>confirmado</em> en la misma ciudad. Revisa si deseas mantener ambos activos.</p></div>';
    }

    public static function enqueue_scripts( string $hook ): void
    {
        if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) return;
        if ( get_post_type() !== 'vx_dinner' ) return;
        wp_enqueue_style( 'wp-jquery-ui-dialog' );
    }

    // ── Columnas ─────────────────────────────────────────────────────────────

    public static function add_columns( array $columns ): array
    {
        unset( $columns['date'] );
        return array_merge( $columns, [
            'vx_ciudad'    => 'Ciudad',
            'vx_fecha'     => 'Fecha',
            'vx_deadline'  => 'Deadline',
            'vx_cupos'     => 'Asistentes',
            'vx_estado'    => 'Estado',
            'vx_acciones'  => 'Acciones',
        ] );
    }

    public static function render_column( string $column, int $post_id ): void
    {
        switch ( $column ) {
            case 'vx_ciudad':
                echo esc_html( get_post_meta( $post_id, VX_Dinner_Meta::CIUDAD, true ) );
                break;

            case 'vx_fecha':
                $ts = (int) get_post_meta( $post_id, VX_Dinner_Meta::FECHA, true );
                echo $ts ? esc_html( date_i18n( 'd/m/Y', $ts ) ) : '-';
                break;

            case 'vx_deadline':
                $dl = (int) get_post_meta( $post_id, VX_Dinner_Meta::DEADLINE, true );
                if ( $dl ) {
                    $color = $dl < time() ? '#AD4319' : '#7DBF9E';
                    echo '<span style="color:' . $color . ';font-size:12px">' . date_i18n( 'd/m/Y H:i', $dl ) . '</span>';
                } else {
                    echo '<span style="color:#5C5F6B;font-size:12px">Sin límite</span>';
                }
                break;

            case 'vx_cupos':
                $asignados = (array) get_post_meta( $post_id, VX_Dinner_Meta::ASIGNADOS, true );
                $n = count( $asignados );
                echo $n . ' asistente' . ( $n !== 1 ? 's' : '' ) . ( $n >= 4 ? ' (mesa completa)' : '' );
                break;

            case 'vx_estado':
                $estado = get_post_meta( $post_id, VX_Dinner_Meta::ESTADO, true );
                $colors = [
                    'abierto'    => '#7DBF9E',
                    'confirmado' => '#8BA3C0',
                    'completo'   => '#8BA3C0',
                    'realizado'  => '#5C5F6B',
                    'cancelado'  => '#C58D78',
                ];
                $color = $colors[ $estado ] ?? '#5C5F6B';
                echo '<span style="color:' . $color . ';font-weight:600">' . esc_html( ucfirst( $estado ) ) . '</span>';
                break;

            case 'vx_acciones':
                $csv_url = wp_nonce_url(
                    admin_url( 'admin.php?action=vx_dinner_export_csv&dinner_id=' . $post_id ),
                    'vx_dinner_export_csv_' . $post_id
                );
                echo '<a href="' . esc_url( $csv_url ) . '" class="button button-small">CSV</a>';
                break;
        }
    }

    // ── Meta Boxes ────────────────────────────────────────────────────────────

    public static function add_meta_boxes(): void
    {
        add_meta_box( 'vx_dinner_info',       'Información del evento',    [ self::class, 'render_info_meta_box' ],      'vx_dinner', 'normal',  'high' );
        add_meta_box( 'vx_dinner_mesas_box',  'Gestión de mesas',          [ self::class, 'render_mesas_meta_box' ],     'vx_dinner', 'normal',  'default' );
        add_meta_box( 'vx_dinner_comensales', 'Comensales y pendientes',   [ self::class, 'render_comensales_meta_box' ],'vx_dinner', 'normal',  'default' );
        add_meta_box( 'vx_dinner_busqueda',   'Buscar e invitar miembros', [ self::class, 'render_busqueda_meta_box' ],  'vx_dinner', 'side',    'default' );
    }

    // ── Meta Box: Información del evento ─────────────────────────────────────

    public static function render_info_meta_box( WP_Post $post ): void
    {
        wp_nonce_field( 'vx_dinner_save_' . $post->ID, 'vx_dinner_nonce' );

        $ts_fecha    = get_post_meta( $post->ID, VX_Dinner_Meta::FECHA,    true );
        $ts_deadline = get_post_meta( $post->ID, VX_Dinner_Meta::DEADLINE, true );

        $fecha_val    = $ts_fecha    ? date( 'Y-m-d', (int) $ts_fecha )           : '';
        $deadline_val = $ts_deadline ? date( 'Y-m-d\TH:i', (int) $ts_deadline )  : '';

        $estado = get_post_meta( $post->ID, VX_Dinner_Meta::ESTADO, true ) ?: 'abierto';

        $current_pais   = (string) get_post_meta( $post->ID, VX_Dinner_Meta::PAIS,   true );
        $current_ciudad = (string) get_post_meta( $post->ID, VX_Dinner_Meta::CIUDAD, true );
        $ciudades_pais  = function_exists( 'vx_get_ciudades_por_pais' ) ? ( vx_get_ciudades_por_pais()[ $current_pais ] ?? [] ) : [];
        $paises_latam   = function_exists( 'vx_get_paises_latam' ) ? vx_get_paises_latam() : [];

        echo '<table class="form-table"><tbody>';

        // País (select)
        echo '<tr><th><label for="vx_dinner_pais">País</label></th><td>';
        echo '<select name="' . esc_attr( VX_Dinner_Meta::PAIS ) . '" id="vx_dinner_pais" class="regular-text">';
        echo '<option value="">Selecciona el país</option>';
        foreach ( $paises_latam as $p ) {
            echo '<option value="' . esc_attr( $p ) . '"' . selected( $current_pais, $p, false ) . '>' . esc_html( $p ) . '</option>';
        }
        echo '</select>';
        echo '</td></tr>';

        // Ciudad (select, en cascada con JS)
        $ciudad_es_otra = $current_ciudad && ! in_array( $current_ciudad, $ciudades_pais, true ) && ! empty( $ciudades_pais );
        echo '<tr><th><label for="vx_dinner_ciudad">Ciudad</label></th><td>';
        echo '<select name="' . esc_attr( VX_Dinner_Meta::CIUDAD ) . '" id="vx_dinner_ciudad" class="regular-text">';
        if ( ! $current_pais ) {
            echo '<option value="">Selecciona primero el país</option>';
        } else {
            echo '<option value="">Selecciona la ciudad</option>';
            foreach ( $ciudades_pais as $c ) {
                echo '<option value="' . esc_attr( $c ) . '"' . selected( $ciudad_es_otra ? '' : $current_ciudad, $c, false ) . '>' . esc_html( $c ) . '</option>';
            }
            echo '<option value="__otra__"' . ( $ciudad_es_otra ? ' selected' : '' ) . '>Otra ciudad...</option>';
        }
        echo '</select>';
        echo '<input type="text" id="vx_dinner_ciudad_custom" name="' . esc_attr( VX_Dinner_Meta::CIUDAD ) . '_custom"'
           . ' value="' . esc_attr( $ciudad_es_otra ? $current_ciudad : '' ) . '"'
           . ' placeholder="Escribe la ciudad"'
           . ' style="margin-top:6px;width:100%;' . ( $ciudad_es_otra ? '' : 'display:none' ) . '">';
        echo '<p class="description">Ciudades principales del país. Si no está en la lista, elige "Otra ciudad...".</p>';
        echo '</td></tr>';

        // Restaurante y Dirección — siguen siendo texto libre
        foreach ( [
            [ 'Restaurante', VX_Dinner_Meta::RESTAURANTE, 'text' ],
            [ 'Dirección',   VX_Dinner_Meta::DIRECCION,   'text' ],
        ] as [ $label, $key, $type ] ) {
            $val = esc_attr( (string) get_post_meta( $post->ID, $key, true ) );
            echo "<tr><th><label>$label</label></th><td><input type='$type' name='" . esc_attr( $key ) . "' value='$val' class='regular-text'></td></tr>";
        }

        // JS cascade admin
        ?>
        <script>
        (function() {
            var ciudadesPorPais = <?php echo wp_json_encode( function_exists('vx_get_ciudades_por_pais') ? vx_get_ciudades_por_pais() : [] ); ?>;
            var paisSel     = document.getElementById('vx_dinner_pais');
            var ciudadSel   = document.getElementById('vx_dinner_ciudad');
            var customInput = document.getElementById('vx_dinner_ciudad_custom');
            if (!paisSel || !ciudadSel) return;

            function toggleCustom() {
                var isOtra = ciudadSel.value === '__otra__';
                if (customInput) {
                    customInput.style.display = isOtra ? '' : 'none';
                    if (isOtra) customInput.focus();
                    else customInput.value = '';
                }
            }

            paisSel.addEventListener('change', function() {
                var pais = this.value;
                var ciudades = ciudadesPorPais[pais] || [];
                ciudadSel.innerHTML = '';
                if (!ciudades.length) {
                    ciudadSel.innerHTML = '<option value="">Sin ciudades registradas</option>';
                    if (customInput) customInput.style.display = 'none';
                    return;
                }
                var ph = document.createElement('option'); ph.value=''; ph.textContent='Selecciona la ciudad'; ciudadSel.appendChild(ph);
                ciudades.forEach(function(c){ var o=document.createElement('option'); o.value=c; o.textContent=c; ciudadSel.appendChild(o); });
                var otra = document.createElement('option'); otra.value='__otra__'; otra.textContent='Otra ciudad...'; ciudadSel.appendChild(otra);
                toggleCustom();
            });

            ciudadSel.addEventListener('change', toggleCustom);
            toggleCustom(); // init
        })();
        </script>
        <?php

        // Fecha de la cena
        echo '<tr><th><label>Fecha de la cena</label></th><td>';
        echo '<input type="date" name="' . VX_Dinner_Meta::FECHA . '" value="' . esc_attr( $fecha_val ) . '" class="regular-text">';
        echo '<p class="description">La cena es a las 20:00 hora local.</p>';
        echo '</td></tr>';

        // Deadline
        echo '<tr><th><label>Deadline de inscripción</label></th><td>';
        echo '<input type="datetime-local" name="' . VX_Dinner_Meta::DEADLINE . '" value="' . esc_attr( $deadline_val ) . '" class="regular-text">';
        echo '<p class="description">Fecha y hora hasta la que los miembros pueden registrar interés. Dejar vacío = sin límite.</p>';
        echo '</td></tr>';

        // Estado
        echo '<tr><th><label>Estado</label></th><td><select name="' . VX_Dinner_Meta::ESTADO . '">';
        foreach ( [ 'abierto', 'confirmado', 'realizado', 'cancelado' ] as $opt ) {
            echo '<option value="' . esc_attr( $opt ) . '"' . selected( $estado, $opt, false ) . '>' . esc_html( ucfirst( $opt ) ) . '</option>';
        }
        echo '</select></td></tr>';

        echo '</tbody></table>';
    }

    // ── Meta Box: Gestión de mesas ────────────────────────────────────────────

    public static function render_mesas_meta_box( WP_Post $post ): void
    {
        $dinner    = VX_Dinner::get( $post->ID );
        $asignados = $dinner ? $dinner->get_asignados() : [];
        $mesas     = $dinner ? $dinner->get_mesas() : [];

        if ( empty( $asignados ) ) {
            echo '<p style="color:#5C5F6B;margin:0">Aún no hay asistentes confirmados para este evento.</p>';
            return;
        }

        // Datos de usuarios indexados por UID para pasar a JS
        $users_data = [];
        foreach ( $asignados as $uid ) {
            $u = VX_User::get( (int) $uid );
            if ( ! $u ) continue;
            $users_data[ (int) $uid ] = [
                'uid'    => (int) $uid,
                'nombre' => $u->get_nombre_completo(),
                'foto'   => $u->get_foto_url( 'vx-avatar' ),
            ];
        }

        // UIDs ya colocados en alguna mesa
        $en_mesa = [];
        foreach ( $mesas as $mesa ) {
            foreach ( (array) ( $mesa['asignados'] ?? [] ) as $uid ) {
                $en_mesa[] = (int) $uid;
            }
        }
        $sin_mesa_uids = array_values( array_diff( $asignados, $en_mesa ) );

        if ( ! empty( $_GET['vx_mesas_guardadas'] ) ) {
            echo '<div class="notice notice-success inline" style="margin:0 0 12px"><p>Mesas guardadas correctamente.</p></div>';
        }
        if ( isset( $_GET['vx_mesas_notificadas'] ) ) {
            $n = (int) $_GET['vx_mesas_notificadas'];
            if ( $n > 0 ) {
                echo '<div class="notice notice-success inline" style="margin:0 0 12px"><p>Notificaciones enviadas a ' . $n . ' asistente' . ( $n !== 1 ? 's' : '' ) . '.</p></div>';
            } else {
                echo '<div class="notice notice-warning inline" style="margin:0 0 12px"><p>No hay asistentes con mesa asignada para notificar.</p></div>';
            }
        }
        ?>

        <style>
        #vx-mesas-wrap { display:flex; flex-direction:column; gap:10px; }
        .vx-drop-zone  { min-height:52px; padding:8px; border-radius:6px; display:flex; flex-wrap:wrap; gap:6px; align-items:flex-start; }
        .vx-drop-zone.vx-over { background:#F4F1EC !important; outline:2px dashed #A9C6E8; outline-offset:-2px; }
        #vx-pool-box   { border:2px dashed #E6E2DB; border-radius:8px; background:#F4F1EC; }
        #vx-pool-label { font-size:11px; font-weight:700; color:#5C5F6B; text-transform:uppercase; letter-spacing:.05em; padding:8px 10px 4px; }
        .vx-mesa-block { border:1px solid #E6E2DB; border-radius:8px; overflow:hidden; }
        .vx-mesa-head  { background:#F4F1EC; padding:7px 12px; display:flex; align-items:center; gap:8px; border-bottom:1px solid #E6E2DB; }
        .vx-mesa-name  { border:none; background:transparent; font-weight:600; font-size:14px; flex:1; min-width:0; outline:none; }
        .vx-mesa-name:focus { background:#fff; border:1px solid #E6E2DB; border-radius:4px; padding:2px 6px; }
        .vx-seat-count { font-size:11px; color:#5C5F6B; white-space:nowrap; }
        .vx-chip { background:#fff; border:1px solid #E6E2DB; border-radius:99px; padding:4px 10px 4px 4px; font-size:12px; cursor:grab; display:inline-flex; align-items:center; gap:5px; user-select:none; }
        .vx-chip:active { cursor:grabbing; }
        .vx-chip.vx-in-mesa { background:#F4F1EC; border-color:#DDE8F6; }
        .vx-chip.vx-dragging { opacity:.35; }
        .vx-chip img { width:20px; height:20px; border-radius:50%; object-fit:cover; flex-shrink:0; pointer-events:none; }
        .vx-chip span { pointer-events:none; }
        .vx-empty-hint { color:#5C5F6B; font-size:12px; font-style:italic; pointer-events:none; align-self:center; padding:4px 2px; }
        </style>

        <p style="color:#5C5F6B;font-size:13px;margin:0 0 10px">
            Arrastra personas desde el pool hacia cualquier mesa. Usa <strong>+ Agregar mesa</strong> para crear tantas mesas como necesites.
        </p>

        <div id="vx-mesas-wrap">

          <!-- Pool sin mesa -->
          <div id="vx-pool-box">
            <div id="vx-pool-label">Sin mesa asignada (<?php echo count( $sin_mesa_uids ); ?>)</div>
            <div class="vx-drop-zone" id="vx-pool"
                 ondragover="vxOver(event)" ondragleave="vxLeave(event)" ondrop="vxDrop(event)">
              <?php foreach ( $sin_mesa_uids as $uid ) :
                  $u = $users_data[ (int) $uid ] ?? null;
                  if ( ! $u ) continue;
              ?>
              <span class="vx-chip" draggable="true" data-uid="<?php echo $uid; ?>"
                    ondragstart="vxStart(event)" ondragend="vxEnd(event)">
                <img src="<?php echo esc_url( $u['foto'] ); ?>" alt="">
                <span><?php echo esc_html( $u['nombre'] ); ?></span>
              </span>
              <?php endforeach; ?>
              <?php if ( empty( $sin_mesa_uids ) ) : ?>
              <span class="vx-empty-hint">Todos asignados a una mesa</span>
              <?php endif; ?>
            </div>
          </div>

          <!-- Lista de mesas -->
          <div id="vx-mesas-list">
            <?php foreach ( $mesas as $idx => $mesa ) :
                $m_uids = array_map( 'intval', (array) ( $mesa['asignados'] ?? [] ) );
                $n      = count( $m_uids );
            ?>
            <div class="vx-mesa-block" style="margin-bottom:8px">
              <div class="vx-mesa-head">
                <input type="text" class="vx-mesa-name" value="<?php echo esc_attr( $mesa['nombre'] ?? 'Mesa ' . ( $idx + 1 ) ); ?>">
                <span class="vx-seat-count"><?php echo $n; ?> persona<?php echo $n !== 1 ? 's' : ''; ?></span>
                <button type="button" onclick="vxBorrarMesa(this)"
                        style="margin-left:auto;border:none;background:none;cursor:pointer;color:#AD4319;font-size:12px;padding:2px 6px;line-height:1">
                  Eliminar
                </button>
              </div>
              <div class="vx-drop-zone" ondragover="vxOver(event)" ondragleave="vxLeave(event)" ondrop="vxDrop(event)">
                <?php foreach ( $m_uids as $uid ) :
                    $u = $users_data[ $uid ] ?? null;
                    if ( ! $u ) continue;
                ?>
                <span class="vx-chip vx-in-mesa" draggable="true" data-uid="<?php echo $uid; ?>"
                      ondragstart="vxStart(event)" ondragend="vxEnd(event)">
                  <img src="<?php echo esc_url( $u['foto'] ); ?>" alt="">
                  <span><?php echo esc_html( $u['nombre'] ); ?></span>
                </span>
                <?php endforeach; ?>
                <?php if ( empty( $m_uids ) ) : ?>
                <span class="vx-empty-hint">Arrastra personas aquí</span>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>

          <!-- Acciones — sin form anidado (el meta box ya está dentro del form del post) -->
          <div style="display:flex;gap:8px;align-items:center;padding-top:4px;flex-wrap:wrap">
            <button type="button" class="button" onclick="vxAgregarMesa()">+ Agregar mesa</button>
            <button type="button" class="button button-primary" onclick="vxGuardar()">Guardar mesas</button>
            <button type="button" class="button" onclick="vxNotificarMesas()"
                    style="border-color:#C6C167;color:#14161F;background:#FAF7C9"
                    title="Envía un email a cada asistente con el nombre de su mesa y los datos del evento">
              Notificar mesas
            </button>
          </div>

        </div><!-- #vx-mesas-wrap -->

        <script>
        (function () {
          var mesaSeq      = <?php echo count( $mesas ) ?: 0; ?>;
          var vxAction     = <?php echo wp_json_encode( admin_url( 'admin.php' ) ); ?>;
          var vxDinnerId   = <?php echo $post->ID; ?>;
          var vxNonce      = <?php echo wp_json_encode( wp_create_nonce( 'vx_dinner_save_mesas_' . $post->ID ) ); ?>;
          var vxNotifyNonce = <?php echo wp_json_encode( wp_create_nonce( 'vx_dinner_notify_mesas_' . $post->ID ) ); ?>;

          // ── Drag ──────────────────────────────────────────────────────────────

          window.vxStart = function (e) {
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', e.currentTarget.dataset.uid);
            setTimeout(function () { e.currentTarget.classList.add('vx-dragging'); }, 0);
          };

          window.vxEnd = function (e) {
            e.currentTarget.classList.remove('vx-dragging');
          };

          window.vxOver = function (e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            e.currentTarget.classList.add('vx-over');
          };

          window.vxLeave = function (e) {
            // Solo quitar el highlight si el cursor sale del drop zone (no de un hijo)
            if (!e.currentTarget.contains(e.relatedTarget)) {
              e.currentTarget.classList.remove('vx-over');
            }
          };

          window.vxDrop = function (e) {
            e.preventDefault();
            e.stopPropagation();
            var zone = e.currentTarget;
            zone.classList.remove('vx-over');
            var uid = e.dataTransfer.getData('text/plain');
            if (!uid) return;
            var chip = document.querySelector('.vx-chip[data-uid="' + uid + '"]');
            if (!chip || zone === chip.parentNode) return; // no-op si ya está ahí

            // Quitar hint vacío si existe
            var hint = zone.querySelector('.vx-empty-hint');
            if (hint) hint.remove();

            // Color del chip según destino
            if (zone.id === 'vx-pool') {
              chip.classList.remove('vx-in-mesa');
            } else {
              chip.classList.add('vx-in-mesa');
            }

            zone.appendChild(chip);
            refreshPool();
            refreshCounts();
          };

          // ── Mesas ─────────────────────────────────────────────────────────────

          window.vxAgregarMesa = function () {
            mesaSeq++;
            var block = document.createElement('div');
            block.className = 'vx-mesa-block';
            block.style.marginBottom = '8px';
            block.innerHTML =
              '<div class="vx-mesa-head">' +
                '<input type="text" class="vx-mesa-name" value="Mesa ' + mesaSeq + '">' +
                '<span class="vx-seat-count">0 personas</span>' +
                '<button type="button" onclick="vxBorrarMesa(this)" style="margin-left:auto;border:none;background:none;cursor:pointer;color:#AD4319;font-size:12px;padding:2px 6px;line-height:1">Eliminar</button>' +
              '</div>' +
              '<div class="vx-drop-zone" ondragover="vxOver(event)" ondragleave="vxLeave(event)" ondrop="vxDrop(event)">' +
                '<span class="vx-empty-hint">Arrastra personas aquí</span>' +
              '</div>';
            document.getElementById('vx-mesas-list').appendChild(block);
          };

          window.vxBorrarMesa = function (btn) {
            if (!confirm('¿Eliminar esta mesa? Las personas volverán al pool.')) return;
            var block = btn.closest('.vx-mesa-block');
            var pool  = document.getElementById('vx-pool');
            block.querySelectorAll('.vx-chip').forEach(function (c) {
              c.classList.remove('vx-in-mesa');
              pool.appendChild(c);
            });
            block.remove();
            refreshPool();
            refreshCounts();
          };

          // ── Guardar ───────────────────────────────────────────────────────────

          window.vxNotificarMesas = function () {
            if (!confirm('¿Enviar email a cada asistente con el nombre de su mesa?\n\nSolo se notifica a quienes tienen una mesa asignada.')) return;
            var f = document.createElement('form');
            f.method = 'post';
            f.action = vxAction;
            f.style.display = 'none';
            function addN(name, value) {
              var i = document.createElement('input');
              i.type = 'hidden'; i.name = name; i.value = value;
              f.appendChild(i);
            }
            addN('action',               'vx_dinner_notify_mesas');
            addN('dinner_id',            vxDinnerId);
            addN('_vx_notify_mesas_nonce', vxNotifyNonce);
            document.body.appendChild(f);
            f.submit();
          };

          window.vxGuardar = function () {
            var mesas = [];
            document.querySelectorAll('#vx-mesas-list .vx-mesa-block').forEach(function (block) {
              var nombre = (block.querySelector('.vx-mesa-name').value || 'Mesa').trim();
              var uids   = [];
              block.querySelectorAll('.vx-chip[data-uid]').forEach(function (c) {
                uids.push(parseInt(c.dataset.uid, 10));
              });
              mesas.push({ nombre: nombre, asignados: uids });
            });

            // Crear form fuera de cualquier form anidado y submitear desde document.body
            var f = document.createElement('form');
            f.method = 'post';
            f.action = vxAction;
            f.style.display = 'none';
            function addField(name, value) {
              var i = document.createElement('input');
              i.type = 'hidden'; i.name = name; i.value = value;
              f.appendChild(i);
            }
            addField('action',        'vx_dinner_save_mesas');
            addField('dinner_id',     vxDinnerId);
            addField('_vx_mesas_nonce', vxNonce);
            addField('mesas_json',    JSON.stringify(mesas));
            document.body.appendChild(f);
            f.submit();
          };

          // ── Helpers ───────────────────────────────────────────────────────────

          function refreshPool() {
            var pool  = document.getElementById('vx-pool');
            var label = document.getElementById('vx-pool-label');
            var n     = pool.querySelectorAll('.vx-chip').length;
            if (label) label.textContent = 'Sin mesa asignada (' + n + ')';
            var hint = pool.querySelector('.vx-empty-hint');
            if (n === 0 && !hint) {
              var s = document.createElement('span');
              s.className = 'vx-empty-hint';
              s.textContent = 'Todos asignados a una mesa';
              pool.appendChild(s);
            } else if (n > 0 && hint) {
              hint.remove();
            }
          }

          function refreshCounts() {
            document.querySelectorAll('#vx-mesas-list .vx-mesa-block').forEach(function (block) {
              var n   = block.querySelectorAll('.vx-chip').length;
              var lbl = block.querySelector('.vx-seat-count');
              if (lbl) lbl.textContent = n + ' persona' + (n !== 1 ? 's' : '');
              var zone = block.querySelector('.vx-drop-zone');
              var hint = zone ? zone.querySelector('.vx-empty-hint') : null;
              if (n === 0 && zone && !hint) {
                var s = document.createElement('span');
                s.className = 'vx-empty-hint';
                s.textContent = 'Arrastra personas aquí';
                zone.appendChild(s);
              } else if (n > 0 && hint) {
                hint.remove();
              }
            });
          }

        })();
        </script>
        <?php
    }

    // ── Meta Box: Comensales y pendientes ─────────────────────────────────────

    public static function render_comensales_meta_box( WP_Post $post ): void
    {
        $dinner    = VX_Dinner::get( $post->ID );
        $asignados = $dinner ? $dinner->get_asignados() : [];
        $pendientes = VX_Dinner_Invite::get_for_dinner( $post->ID, 'pendiente' );
        $aceptadas  = VX_Dinner_Invite::get_for_dinner( $post->ID, 'aceptado' );
        $rechazadas = VX_Dinner_Invite::get_for_dinner( $post->ID, 'rechazado' );
        $ep_responder = rest_url( VX_REST_NAMESPACE . '/conexiones/responder' );
        $api_nonce    = wp_create_nonce( 'wp_rest' );

        // ── Asignados ──
        $n_asig = count( $asignados );
        $status_label = $n_asig >= 4
            ? '' . $n_asig . ' asistente' . ( $n_asig !== 1 ? 's' : '' ) . ' · CENA CONFIRMADA'
            : '' . $n_asig . ' asistente' . ( $n_asig !== 1 ? 's' : '' ) . ' (mín. 4 para confirmar)';

        $csv_url = wp_nonce_url(
            admin_url( 'admin.php?action=vx_dinner_export_csv&dinner_id=' . $post->ID ),
            'vx_dinner_export_csv_' . $post->ID
        );

        echo '<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:12px">';
        echo '<h4 style="margin:0">' . esc_html( $status_label ) . '</h4>';
        if ( $asignados ) {
            echo '<a href="' . esc_url( $csv_url ) . '" class="button" style="display:inline-flex;align-items:center;gap:5px">'
               . 'Descargar CSV confirmados'
               . '</a>';
        }
        echo '</div>';

        if ( $asignados ) {
            echo '<table class="widefat striped" style="margin-bottom:16px;font-size:12px">';
            echo '<thead><tr><th>Nombre</th><th>Empresa</th><th>Email</th><th>Teléfono</th><th>Acción</th></tr></thead><tbody>';
            foreach ( $asignados as $uid ) {
                $u = VX_User::get( (int) $uid );
                if ( ! $u ) continue;
                $emp    = $u->get_empresa_activa();
                $rm_url = wp_nonce_url(
                    admin_url( 'admin.php?action=vx_dinner_desasignar&dinner_id=' . $post->ID . '&user_id=' . $uid ),
                    'vx_dinner_desasignar_' . $post->ID
                );
                $perfil_url = get_edit_user_link( $uid );
                echo '<tr>';
                echo '<td><a href="' . esc_url( $perfil_url ) . '" target="_blank"><strong>' . esc_html( $u->get_nombre_completo() ) . '</strong></a></td>';
                echo '<td>' . esc_html( $emp ? $emp->post_title : '-' ) . '</td>';
                echo '<td>' . ( $u->get_email() ? '<a href="mailto:' . esc_attr( $u->get_email() ) . '">' . esc_html( $u->get_email() ) . '</a>' : '-' ) . '</td>';
                echo '<td>' . ( $u->get_telefono() ? '<a href="tel:' . esc_attr( $u->get_telefono() ) . '">' . esc_html( $u->get_telefono() ) . '</a>' : '-' ) . '</td>';
                echo '<td><a href="' . esc_url( $rm_url ) . '" style="color:#AD4319" onclick="return confirm(\'¿Desasignar?\')">Quitar</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p style="color:#5C5F6B;margin:0 0 16px">Sin asistentes confirmados.</p>';
        }

        echo '<hr style="margin:12px 0">';

        // ── Pendientes ──
        echo '<h4 style="margin:0 0 8px">Pendientes (' . count( $pendientes ) . ')</h4>';
        if ( $pendientes ) {
            echo '<table class="widefat striped" style="margin-bottom:12px;font-size:12px">';
            echo '<thead><tr><th>Usuario</th><th>Tipo</th><th>Mensaje</th><th>Fecha</th><th>Acciones</th></tr></thead><tbody>';
            foreach ( $pendientes as $inv ) {
                $u  = VX_User::get( $inv['user_id'] );
                $nombre = $u ? esc_html( $u->get_nombre_completo() ) : '#' . $inv['user_id'];
                $tipo_label = 'invitacion' === $inv['tipo'] ? 'Invitación' : 'Interés';

                $asig_url = wp_nonce_url( admin_url( 'admin.php?action=vx_dinner_asig_interes&invite_id=' . $inv['id'] . '&dinner_id=' . $post->ID ), 'vx_dinner_asig_interes_' . $inv['id'] );
                $rech_url = wp_nonce_url( admin_url( 'admin.php?action=vx_dinner_rechaz_interes&invite_id=' . $inv['id'] . '&dinner_id=' . $post->ID ), 'vx_dinner_rechaz_interes_' . $inv['id'] );

                echo '<tr>';
                echo '<td><strong>' . $nombre . '</strong>' . ( $u ? '<br><small style="color:#5C5F6B">' . esc_html( $u->get_email() ) . '</small>' : '' ) . '</td>';
                echo '<td>' . $tipo_label . '</td>';
                echo '<td>' . ( $inv['mensaje'] ? esc_html( wp_trim_words( $inv['mensaje'], 10 ) ) : '-' ) . '</td>';
                echo '<td>' . date_i18n( 'd/m/Y', $inv['fecha'] ) . '</td>';
                echo '<td><a href="' . esc_url( $asig_url ) . '" class="button button-primary button-small" onclick="return confirm(\'¿Asignar?\')">Asignar</a> ';
                echo '<a href="' . esc_url( $rech_url ) . '" class="button button-small" style="color:#AD4319" onclick="return confirm(\'¿Rechazar?\')">Rechazar</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p style="color:#5C5F6B;margin:0 0 12px">Sin solicitudes pendientes.</p>';
        }

        // ── Historial ──
        $history = array_merge( $aceptadas, $rechazadas );
        if ( $history ) {
            echo '<details style="margin-top:12px"><summary style="cursor:pointer;font-size:12px;color:#5C5F6B">Historial aceptadas/rechazadas (' . count($history) . ')</summary>';
            echo '<ul style="margin:8px 0 0;font-size:12px">';
            foreach ( $history as $inv ) {
                $u   = VX_User::get( $inv['user_id'] );
                $nom = $u ? esc_html( $u->get_nombre_completo() ) : '#' . $inv['user_id'];
                $badge = 'aceptado' === $inv['estado']
                    ? '<span style="color:#14161F">Aceptado</span>'
                    : '<span style="color:#AD4319">Rechazado</span>';
                echo '<li>' . $nom . ' · ' . $badge . '</li>';
            }
            echo '</ul></details>';
        }
    }

    // ── Meta Box: Buscar e invitar ─────────────────────────────────────────────

    public static function render_busqueda_meta_box( WP_Post $post ): void
    {
        $dinner_id = $post->ID;
        $api_url   = rest_url( VX_REST_NAMESPACE . '/dinners/' . $dinner_id . '/invitar' );
        $api_nonce = wp_create_nonce( 'wp_rest' );
        $ajax_url  = admin_url( 'admin-ajax.php' );
        $search_nonce = wp_create_nonce( 'vx_dinner_buscar' );
        $paises    = vx_get_paises_latam();
        $industrias = vx_get_industrias();
        ?>
        <div style="font-size:13px">

          <p style="color:#5C5F6B;margin:0 0 12px">Busca miembros activos y envíales invitación.</p>

          <!-- Filtros -->
          <div style="margin-bottom:10px">
            <label style="display:block;font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">Filtrar por país</label>
            <select id="vx-busq-pais" style="width:100%;margin-bottom:8px">
              <option value="">Todos los países</option>
              <?php foreach ( $paises as $p ) : ?>
              <option value="<?php echo esc_attr($p); ?>"><?php echo esc_html($p); ?></option>
              <?php endforeach; ?>
            </select>

            <label style="display:block;font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">Filtrar por industria</label>
            <select id="vx-busq-industria" style="width:100%;margin-bottom:8px">
              <option value="">Todas las industrias</option>
              <?php foreach ( $industrias as $ind ) : ?>
              <option value="<?php echo esc_attr($ind); ?>"><?php echo esc_html($ind); ?></option>
              <?php endforeach; ?>
            </select>

            <input type="text" id="vx-busq-nombre" placeholder="Buscar por nombre o email..." style="width:100%;margin-bottom:8px">
            <button type="button" class="button" id="vx-busq-btn" onclick="vxBuscarMiembros()" style="width:100%">Buscar</button>
          </div>

          <!-- Resultados -->
          <div id="vx-busq-results" style="max-height:400px;overflow-y:auto;border:1px solid #E6E2DB;border-radius:6px;display:none">
            <div id="vx-busq-lista"></div>
          </div>
          <p id="vx-busq-msg" style="font-size:12px;color:#5C5F6B;margin:6px 0 0"></p>
        </div>

        <script>
        var vxBusqDinnerId = <?php echo $dinner_id; ?>;
        var vxBusqApiUrl   = <?php echo wp_json_encode( $api_url ); ?>;
        var vxBusqNonce    = <?php echo wp_json_encode( $api_nonce ); ?>;
        var vxBusqAjax     = <?php echo wp_json_encode( $ajax_url ); ?>;
        var vxBusqSNonce   = <?php echo wp_json_encode( $search_nonce ); ?>;

        function vxBuscarMiembros() {
          var pais      = document.getElementById('vx-busq-pais').value;
          var industria = document.getElementById('vx-busq-industria').value;
          var nombre    = document.getElementById('vx-busq-nombre').value;
          var msg       = document.getElementById('vx-busq-msg');
          var results   = document.getElementById('vx-busq-results');
          var lista     = document.getElementById('vx-busq-lista');

          msg.textContent = 'Buscando...';
          results.style.display = 'none';

          jQuery.post( vxBusqAjax, {
            action:    'vx_dinner_buscar_miembros',
            nonce:     vxBusqSNonce,
            dinner_id: vxBusqDinnerId,
            pais:      pais,
            industria: industria,
            nombre:    nombre,
          }, function(res) {
            if ( !res.success ) { msg.textContent = 'Error al buscar.'; return; }
            var members = res.data;
            if ( !members.length ) { msg.textContent = 'Sin resultados.'; return; }
            msg.textContent = members.length + ' miembro' + (members.length !== 1 ? 's' : '') + ' encontrado' + (members.length !== 1 ? 's' : '') + '.';
            lista.innerHTML = '';
            members.forEach(function(m) {
              var row = document.createElement('div');
              row.style.cssText = 'padding:10px 12px;border-bottom:1px solid #E6E2DB;display:flex;align-items:center;gap:10px';
              var alreadyAsigned = m.ya_asignado;
              row.innerHTML =
                '<img src="' + (m.foto_url||'') + '" style="width:32px;height:32px;border-radius:50%;object-fit:cover;flex-shrink:0">' +
                '<div style="flex:1;min-width:0">' +
                  '<div style="font-weight:600;font-size:12px">' + m.nombre + '</div>' +
                  '<div style="font-size:11px;color:#5C5F6B">' + (m.empresa||'') + (m.ciudad ? ' · ' + m.ciudad : '') + '</div>' +
                  (m.industria ? '<div style="font-size:11px;color:#8BA3C0">' + m.industria + '</div>' : '') +
                '</div>' +
                '<div style="display:flex;gap:4px;flex-shrink:0">' +
                  '<a href="' + m.perfil_admin_url + '" target="_blank" class="button button-small" title="Ver perfil">Ver</a>' +
                  (alreadyAsigned
                    ? '<span class="button button-small" style="opacity:.5" title="Ya asignado">Asignado</span>'
                    : '<button class="button button-primary button-small" onclick="vxInvitar(' + m.id + ', this)" title="Invitar">Invitar</button>'
                  ) +
                '</div>';
              lista.appendChild( row );
            });
            results.style.display = '';
          });
        }

        function vxInvitar(userId, btn) {
          btn.disabled = true;
          btn.textContent = '...';
          jQuery.ajax({
            url:         vxBusqApiUrl,
            method:      'POST',
            contentType: 'application/json',
            beforeSend:  function(xhr){ xhr.setRequestHeader('X-WP-Nonce', vxBusqNonce); },
            data:        JSON.stringify({ user_id: userId }),
            success: function(res) {
              if ( res.success ) { btn.textContent = 'Enviado'; btn.style.background='#7DBF9E'; }
              else { btn.textContent = res.message || 'Error'; btn.disabled = false; }
            },
            error: function(xhr) {
              var msg = xhr.responseJSON ? (xhr.responseJSON.message || 'Error') : 'Error';
              btn.textContent = msg; btn.disabled = false;
            }
          });
        }
        </script>
        <?php
    }

    // ── AJAX: buscar miembros ─────────────────────────────────────────────────

    public static function ajax_buscar_miembros(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Sin permiso', 403 );
        check_ajax_referer( 'vx_dinner_buscar', 'nonce' );

        $dinner_id = absint( $_POST['dinner_id'] ?? 0 );
        $pais      = sanitize_text_field( $_POST['pais']      ?? '' );
        $industria = sanitize_text_field( $_POST['industria'] ?? '' );
        $nombre    = sanitize_text_field( $_POST['nombre']    ?? '' );

        $dinner    = VX_Dinner::get( $dinner_id );
        $asignados = $dinner ? $dinner->get_asignados() : [];

        $meta_query = [
            'relation' => 'AND',
            [ 'key' => VX_User_Meta::ESTADO,              'value' => 'activo' ],
            [ 'key' => VX_User_Meta::ONBOARDING_COMPLETO, 'value' => '1' ],
        ];

        if ( $pais )      $meta_query[] = [ 'key' => VX_User_Meta::PAIS,      'value' => $pais ];
        if ( $industria ) $meta_query[] = [ 'key' => VX_User_Meta::INDUSTRIA, 'value' => $industria ];

        $args = [
            'role'       => 'subscriber',
            'number'     => 50,
            'fields'     => 'ids',
            'meta_query' => $meta_query,
        ];

        if ( $nombre ) {
            $args['search']         = '*' . $nombre . '*';
            $args['search_columns'] = [ 'user_login', 'user_email', 'display_name' ];
        }

        $ids  = get_users( $args );
        $data = [];

        foreach ( $ids as $uid ) {
            $u = VX_User::get( (int) $uid );
            if ( ! $u ) continue;

            $emp = $u->get_empresa_activa();
            $data[] = [
                'id'             => $uid,
                'nombre'         => $u->get_nombre_completo(),
                'email'          => $u->get_email(),
                'ciudad'         => $u->get_ciudad(),
                'pais'           => $u->get_pais(),
                'industria'      => $u->get_industria(),
                'empresa'        => $emp ? $emp->post_title : '',
                'foto_url'       => $u->get_foto_url( 'vx-avatar' ),
                'perfil_admin_url' => get_edit_user_link( $uid ),
                'ya_asignado'    => in_array( $uid, $asignados, true ),
            ];
        }

        wp_send_json_success( $data );
    }

    // ── Guardar meta box de info ──────────────────────────────────────────────

    public static function save_meta_box( int $post_id ): void
    {
        if ( ! isset( $_POST['vx_dinner_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['vx_dinner_nonce'], 'vx_dinner_save_' . $post_id ) ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        // Ciudad: si el select vale "__otra__", usar el campo custom
        $ciudad_raw = sanitize_text_field( wp_unslash( $_POST[ VX_Dinner_Meta::CIUDAD ] ?? '' ) );
        if ( '__otra__' === $ciudad_raw ) {
            $ciudad_raw = sanitize_text_field( wp_unslash( $_POST[ VX_Dinner_Meta::CIUDAD . '_custom' ] ?? '' ) );
        }
        if ( $ciudad_raw ) {
            update_post_meta( $post_id, VX_Dinner_Meta::CIUDAD, $ciudad_raw );
        }

        $string_fields = [
            VX_Dinner_Meta::PAIS,
            VX_Dinner_Meta::RESTAURANTE,
            VX_Dinner_Meta::DIRECCION,
            VX_Dinner_Meta::ESTADO,
        ];

        foreach ( $string_fields as $key ) {
            if ( isset( $_POST[ $key ] ) ) {
                update_post_meta( $post_id, $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
            }
        }

        // Fecha → timestamp
        if ( ! empty( $_POST[ VX_Dinner_Meta::FECHA ] ) ) {
            $timestamp = strtotime( sanitize_text_field( wp_unslash( $_POST[ VX_Dinner_Meta::FECHA ] ) ) . ' 20:00:00' );
            if ( $timestamp ) update_post_meta( $post_id, VX_Dinner_Meta::FECHA, $timestamp );
        }

        // Deadline → timestamp (datetime-local: "2026-06-20T18:00")
        if ( ! empty( $_POST[ VX_Dinner_Meta::DEADLINE ] ) ) {
            $deadline = strtotime( sanitize_text_field( wp_unslash( $_POST[ VX_Dinner_Meta::DEADLINE ] ) ) );
            if ( $deadline ) update_post_meta( $post_id, VX_Dinner_Meta::DEADLINE, $deadline );
        } else {
            update_post_meta( $post_id, VX_Dinner_Meta::DEADLINE, 0 );
        }

        // Advertencia: no debería haber más de un dinner abierto/confirmado en la misma ciudad
        $saved_ciudad = sanitize_text_field( wp_unslash( $_POST[ VX_Dinner_Meta::CIUDAD ] ?? '' ) );
        $saved_estado = sanitize_text_field( wp_unslash( $_POST[ VX_Dinner_Meta::ESTADO ] ?? '' ) );

        if ( $saved_ciudad && in_array( $saved_estado, [ 'abierto', 'confirmado' ], true ) ) {
            $conflict = get_posts( [
                'post_type'      => 'vx_dinner',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'post__not_in'   => [ $post_id ],
                'meta_query'     => [
                    'relation' => 'AND',
                    [ 'key' => VX_Dinner_Meta::CIUDAD, 'value' => $saved_ciudad, 'compare' => '=' ],
                    [ 'key' => VX_Dinner_Meta::ESTADO,  'value' => [ 'abierto', 'confirmado' ], 'compare' => 'IN' ],
                ],
            ] );

            if ( $conflict ) {
                add_filter( 'redirect_post_location', function( $location ) {
                    return add_query_arg( 'vx_dinner_conflict', '1', $location );
                } );
            }
        }
    }

    // ── Acciones de asignación ────────────────────────────────────────────────

    public static function handle_asignar(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permiso.' );
        $dinner_id = absint( $_GET['dinner_id'] ?? 0 );
        $user_id   = absint( $_GET['user_id']   ?? 0 );
        check_admin_referer( 'vx_dinner_asignar_' . $dinner_id );
        $result = VX_Dinner_Assignment::assign( $dinner_id, $user_id );
        $redir  = admin_url( 'post.php?post=' . $dinner_id . '&action=edit' );
        wp_safe_redirect( $redir . ( is_wp_error( $result ) ? '&vx_error=' . $result->get_error_code() : '&vx_asignado=1' ) );
        exit;
    }

    public static function handle_desasignar(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permiso.' );
        $dinner_id = absint( $_GET['dinner_id'] ?? 0 );
        $user_id   = absint( $_GET['user_id']   ?? 0 );
        check_admin_referer( 'vx_dinner_desasignar_' . $dinner_id );
        VX_Dinner_Assignment::unassign( $dinner_id, $user_id );
        wp_safe_redirect( admin_url( 'post.php?post=' . $dinner_id . '&action=edit' ) );
        exit;
    }

    public static function handle_confirmar(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permiso.' );
        $dinner_id = absint( $_GET['dinner_id'] ?? 0 );
        check_admin_referer( 'vx_dinner_confirmar_' . $dinner_id );
        $sent = VX_Dinner_Assignment::send_confirmations( $dinner_id );
        wp_safe_redirect( admin_url( 'edit.php?post_type=vx_dinner&vx_confirmaciones=' . $sent ) );
        exit;
    }

    public static function handle_invitar(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permiso.' );
        $dinner_id = absint( $_GET['dinner_id'] ?? 0 );
        $user_id   = absint( $_GET['user_id']   ?? 0 );
        check_admin_referer( 'vx_dinner_invitar_' . $dinner_id );
        VX_Dinner_Invite::create_invitation( $dinner_id, $user_id );
        wp_safe_redirect( admin_url( 'post.php?post=' . $dinner_id . '&action=edit&vx_invitado=1' ) );
        exit;
    }

    public static function handle_asignar_interes(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permiso.' );
        $invite_id = absint( $_GET['invite_id'] ?? 0 );
        $dinner_id = absint( $_GET['dinner_id'] ?? 0 );
        check_admin_referer( 'vx_dinner_asig_interes_' . $invite_id );
        VX_Dinner_Invite::accept( $invite_id );
        wp_safe_redirect( admin_url( 'post.php?post=' . $dinner_id . '&action=edit&vx_asignado=1' ) );
        exit;
    }

    public static function handle_rechazar_interes(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permiso.' );
        $invite_id = absint( $_GET['invite_id'] ?? 0 );
        $dinner_id = absint( $_GET['dinner_id'] ?? 0 );
        check_admin_referer( 'vx_dinner_rechaz_interes_' . $invite_id );
        VX_Dinner_Invite::reject( $invite_id );
        wp_safe_redirect( admin_url( 'post.php?post=' . $dinner_id . '&action=edit' ) );
        exit;
    }

    // ── Guardar mesas ─────────────────────────────────────────────────────────

    public static function handle_save_mesas(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permiso.' );
        $dinner_id  = absint( $_POST['dinner_id'] ?? 0 );
        $mesas_json = wp_unslash( $_POST['mesas_json'] ?? '' );
        check_admin_referer( 'vx_dinner_save_mesas_' . $dinner_id, '_vx_mesas_nonce' );

        $mesas = json_decode( $mesas_json, true );
        if ( is_array( $mesas ) ) {
            // Sanitizar
            $clean = [];
            foreach ( $mesas as $mesa ) {
                $clean[] = [
                    'nombre'    => sanitize_text_field( $mesa['nombre'] ?? 'Mesa' ),
                    'asignados' => array_map( 'absint', (array) ( $mesa['asignados'] ?? [] ) ),
                ];
            }
            $dinner = VX_Dinner::get( $dinner_id );
            if ( $dinner ) $dinner->set_mesas( $clean );
        }

        wp_safe_redirect( admin_url( 'post.php?post=' . $dinner_id . '&action=edit&vx_mesas_guardadas=1' ) );
        exit;
    }

    // ── Notificar mesas ───────────────────────────────────────────────────────

    public static function handle_notify_mesas(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permiso.' );
        $dinner_id = absint( $_POST['dinner_id'] ?? 0 );
        check_admin_referer( 'vx_dinner_notify_mesas_' . $dinner_id, '_vx_notify_mesas_nonce' );

        $dinner = VX_Dinner::get( $dinner_id );
        if ( ! $dinner ) wp_die( 'Evento no encontrado.' );

        $mesas     = $dinner->get_mesas();
        $asignados = $dinner->get_asignados();

        if ( empty( $mesas ) || empty( $asignados ) ) {
            wp_safe_redirect( admin_url( 'post.php?post=' . $dinner_id . '&action=edit&vx_mesas_notificadas=0' ) );
            exit;
        }

        $dinner_data = [
            'ciudad'      => $dinner->get_ciudad(),
            'pais'        => $dinner->get_pais(),
            'fecha'       => $dinner->get_fecha(),
            'restaurante' => $dinner->get_restaurante(),
            'direccion'   => $dinner->get_direccion(),
        ];

        $enviados = 0;
        foreach ( $mesas as $mesa ) {
            $nombre_mesa = $mesa['nombre'] ?? 'Mesa';
            foreach ( (array) ( $mesa['asignados'] ?? [] ) as $uid ) {
                $user = VX_User::get( (int) $uid );
                if ( ! $user ) continue;

                VX_Mailer::send(
                    $user->get_email(),
                    'Tu mesa para el 4Dinner de ' . $dinner->get_ciudad() . ' está lista',
                    'dinner_mesa_asignada',
                    [
                        'nombre'      => $user->get_nombre(),
                        'mesa_nombre' => $nombre_mesa,
                        'dinner'      => $dinner_data,
                    ]
                );
                $enviados++;
            }
        }

        wp_safe_redirect( admin_url( 'post.php?post=' . $dinner_id . '&action=edit&vx_mesas_notificadas=' . $enviados ) );
        exit;
    }

    // ── Exportar CSV ──────────────────────────────────────────────────────────

    public static function handle_export_csv(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permiso.' );
        $dinner_id = absint( $_GET['dinner_id'] ?? 0 );
        check_admin_referer( 'vx_dinner_export_csv_' . $dinner_id );

        $dinner = VX_Dinner::get( $dinner_id );
        if ( ! $dinner ) wp_die( 'Evento no encontrado.' );

        $asignados = $dinner->get_asignados();
        $mesas     = $dinner->get_mesas();

        // Mapa user_id → nombre de mesa
        $mesa_map = [];
        foreach ( $mesas as $idx => $mesa ) {
            foreach ( (array) ( $mesa['asignados'] ?? [] ) as $uid ) {
                $mesa_map[ $uid ] = $mesa['nombre'] ?? 'Mesa ' . ( $idx + 1 );
            }
        }

        // Headers del CSV
        $headers = [
            'Nombre', 'Apellido', 'Email', 'Teléfono', 'LinkedIn',
            'Ciudad', 'País', 'Industria',
            'Empresa', 'Cargo',
            'Ofrece (texto)', 'Busca (texto)',
            'Tags oferta', 'Tags búsqueda', 'Tags perfil',
            'Bio',
            'Mesa asignada',
        ];

        $filename = 'vitrinexo-4dinner-' . $dinner->get_ciudad() . '-' . date( 'Ymd', $dinner->get_fecha() ) . '.csv';

        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );

        $out = fopen( 'php://output', 'w' );
        fprintf( $out, chr(0xEF) . chr(0xBB) . chr(0xBF) ); // UTF-8 BOM para Excel
        fputcsv( $out, $headers );

        foreach ( $asignados as $uid ) {
            $u = VX_User::get( (int) $uid );
            if ( ! $u ) continue;

            $emp   = $u->get_empresa_activa();
            $cargo = $emp ? (string) get_post_meta( $emp->ID, 'vx_cargo', true ) : '';
            $mesa  = $mesa_map[ $uid ] ?? 'Sin mesa';

            fputcsv( $out, [
                $u->get_nombre(),
                $u->get_apellido(),
                $u->get_email(),
                $u->get_telefono(),
                $u->get_linkedin(),
                $u->get_ciudad(),
                $u->get_pais(),
                $u->get_industria(),
                $emp ? $emp->post_title : '',
                $cargo,
                $u->get_offer_texto(),
                $u->get_seek_texto(),
                implode( ', ', $u->get_offer_tags() ),
                implode( ', ', $u->get_seek_tags() ),
                implode( ', ', $u->get_profile_tags() ),
                $u->get_bio(),
                $mesa,
            ] );
        }

        fclose( $out );
        exit;
    }
}
