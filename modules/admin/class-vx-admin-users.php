<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── AJAX: buscar usuarios para el autocomplete del admin de 4Dinner ──────────
// AJAX handler para búsqueda de miembros en el admin de 4Dinner
add_action( 'wp_ajax_vx_dinner_buscar_miembros', [ 'VX_Admin_Dinner', 'ajax_buscar_miembros' ] );

add_action( 'wp_ajax_vx_user_search_ajax', function () {
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Sin permiso', 403 );
    check_ajax_referer( 'vx_user_search', 'nonce' );

    $q = sanitize_text_field( wp_unslash( $_POST['q'] ?? '' ) );
    if ( strlen( $q ) < 2 ) wp_send_json_success( [] );

    $users = get_users( [
        'search'         => '*' . $q . '*',
        'search_columns' => [ 'user_login', 'user_email', 'display_name' ],
        'role'           => 'subscriber',
        'number'         => 10,
        'meta_query'     => [
            [ 'key' => VX_User_Meta::ESTADO, 'value' => 'activo' ],
        ],
    ] );

    $results = [];
    foreach ( $users as $wp_user ) {
        $vx = VX_User::get( $wp_user->ID );
        if ( ! $vx ) continue;
        $results[] = [
            'value' => $wp_user->ID,
            'label' => $vx->get_nombre_completo() . ' (' . $wp_user->user_email . ')',
        ];
    }

    wp_send_json_success( $results );
} );

/**
 * Columnas personalizadas en la lista de usuarios del admin.
 */
class VX_Admin_Users
{
    public static function init(): void
    {
        add_filter( 'manage_users_columns',       [ self::class, 'add_columns' ] );
        add_filter( 'manage_users_custom_column', [ self::class, 'render_column' ], 10, 3 );
        add_filter( 'manage_users_sortable_columns', [ self::class, 'sortable_columns' ] );

        // Acción de aprobar verificación manual
        add_action( 'admin_action_vx_aprobar_verificacion', [ self::class, 'handle_aprobar_verificacion' ] );
        add_action( 'admin_action_vx_verificar_senior',     [ self::class, 'handle_verificar_senior' ] );
        add_action( 'admin_action_vx_activar_directo',      [ self::class, 'handle_activar_directo' ] );

        // Gestión de plan
        add_action( 'admin_action_vx_set_plan',             [ self::class, 'handle_set_plan' ] );
        add_action( 'admin_action_vx_set_vencimiento',      [ self::class, 'handle_set_vencimiento' ] );
        add_action( 'admin_action_vx_cambiar_plan',         [ self::class, 'handle_cambiar_plan' ] );
        add_action( 'admin_action_vx_dar_pionero',          [ self::class, 'handle_dar_pionero' ] );
        add_action( 'admin_action_vx_quitar_pionero',       [ self::class, 'handle_quitar_pionero' ] );
        add_action( 'admin_head-users.php',                 [ self::class, 'users_table_css' ] );
        add_action( 'admin_action_vx_normalize_ciudades',   [ self::class, 'handle_normalize_ciudades' ] );

        // Filtro por estado en la lista
        add_action( 'restrict_manage_users', [ self::class, 'add_estado_filter' ] );
        add_filter( 'pre_get_users',         [ self::class, 'filter_by_estado' ] );

        // Export CSV de todos los miembros
        add_action( 'admin_action_vx_export_members_csv', [ self::class, 'handle_export_members_csv' ] );

        // Avisos admin
        add_action( 'admin_notices', [ self::class, 'notice_normalize_ciudades' ] );
        add_action( 'admin_notices', [ self::class, 'notice_stats_backfill' ] );
        add_action( 'admin_notices', [ self::class, 'notice_export_csv' ] );
        add_action( 'admin_action_vx_stats_backfill', [ self::class, 'handle_stats_backfill' ] );
    }

    public static function add_columns( array $columns ): array
    {
        unset( $columns['posts'] );

        $new_columns = [];
        foreach ( $columns as $key => $label ) {
            $new_columns[ $key ] = $label;
            if ( 'name' === $key ) {
                $new_columns['vx_empresa']     = 'Empresa';
                $new_columns['vx_cargo']       = 'Cargo';
                $new_columns['vx_telefono']    = 'Teléfono';
            }
            if ( 'email' === $key ) {
                $new_columns['vx_estado']      = 'Estado';
                $new_columns['vx_registro']    = 'Registro';
                $new_columns['vx_plan']        = 'Plan';
                $new_columns['vx_vencimiento'] = 'Vencimiento';
                $new_columns['vx_pionero']     = 'Pionero';
            }
        }
        return $new_columns;
    }

    public static function render_column( string $output, string $column, int $user_id ): string
    {
        switch ( $column ) {
            case 'vx_empresa':
                return esc_html( get_user_meta( $user_id, 'vx_empresa_inicial', true ) ?: '-' );
            case 'vx_cargo':
                return esc_html( get_user_meta( $user_id, VX_User_Meta::CARGO, true ) ?: '-' );
            case 'vx_telefono':
                return esc_html( get_user_meta( $user_id, VX_User_Meta::TELEFONO, true ) ?: '-' );
            case 'vx_estado':
                $estado = get_user_meta( $user_id, VX_User_Meta::ESTADO, true );
                $labels = [
                    'activo'    => '<span class="dashicons dashicons-yes" style="color:green"></span> Activo',
                    'pendiente' => '<span class="dashicons dashicons-clock" style="color:orange"></span> Pendiente',
                    'rechazado' => '<span class="dashicons dashicons-no" style="color:red"></span> Rechazado',
                ];

                $html = $labels[ $estado ] ?? '<span class="dashicons dashicons-minus" style="color:#5C5F6B"></span> Sin estado';

                if ( 'pendiente' === $estado ) {
                    $url   = wp_nonce_url(
                        admin_url( 'users.php?action=vx_aprobar_verificacion&user_id=' . $user_id ),
                        'vx_aprobar_' . $user_id
                    );
                    $html .= ' <a href="' . esc_url( $url ) . '" class="button button-small">Aprobar (email)</a>';
                }

                if ( 'activo' !== $estado ) {
                    $url   = wp_nonce_url(
                        admin_url( 'users.php?action=vx_activar_directo&user_id=' . $user_id ),
                        'vx_activar_' . $user_id
                    );
                    $html .= ' <a href="' . esc_url( $url ) . '" class="button button-small button-primary">Activar</a>';
                }

                return $html;

            // ── Fecha de registro (solo lectura) ──────────────────────────────
            case 'vx_registro':
                $user = get_userdata( $user_id );
                return $user
                    ? '<span style="font-size:12px;color:#14161F">' . date_i18n( 'd/m/Y', strtotime( $user->user_registered ) ) . '</span>'
                    : '-';

            // ── Plan (dropdown configurable) ───────────────────────────────────
            case 'vx_plan':
                $user = get_userdata( $user_id );
                if ( $user && in_array( 'administrator', (array) $user->roles, true ) ) return '<span style="color:#5C5F6B;font-size:12px">-</span>';
                $plan_actual = get_user_meta( $user_id, VX_User_Meta::PLAN, true ) ?: 'Gratuito';
                $planes      = vx_get_planes();
                $html  = '<span style="font-size:12px;font-weight:600;color:#14161F">' . esc_html( $plan_actual ) . '</span>';
                $html .= '<div class="vx-edit-control-block">';
                $html .= '<select onchange="if(this.value){window.location=this.dataset.base+\'&plan=\'+encodeURIComponent(this.value);}" data-base="' . esc_attr( wp_nonce_url( admin_url( 'users.php?action=vx_cambiar_plan&user_id=' . $user_id ), 'vx_cambiar_plan_' . $user_id ) ) . '" style="font-size:11px;padding:2px 4px;border:1px solid #E6E2DB;border-radius:4px;color:#14161F;max-width:110px">';
                $html .= '<option value="">Cambiar...</option>';
                foreach ( $planes as $p ) {
                    if ( $p !== $plan_actual ) $html .= '<option value="' . esc_attr( $p ) . '">' . esc_html( $p ) . '</option>';
                }
                $html .= '</select></div>';
                return $html;

            // ── Fecha de vencimiento (editable) ───────────────────────────────
            case 'vx_vencimiento':
                $user = get_userdata( $user_id );
                if ( $user && in_array( 'administrator', (array) $user->roles, true ) ) return '<span style="color:#5C5F6B;font-size:12px">-</span>';

                // La fecha que vale es la guardada. Hoy la aprobación escribe
                // 0, que es sin vencimiento, porque el plan gratuito no termina
                // en una fecha por miembro sino cuando el programa llega a 100;
                // la puede corregir un administrador con el input de abajo. Acá
                // solo se lee: esta celda calculaba la suya desde el registro y
                // la guardaba mientras se dibujaba, así que la tabla mostraba un
                // vencimiento que nadie había decidido y además lo dejaba
                // escrito. Para quien se aprobó semanas después de registrarse,
                // esa fecha caía antes de la real.
                $meta_existe = metadata_exists( 'user', $user_id, VX_User_Meta::PLAN_VENCIMIENTO );
                $expiry_ts   = $meta_existe
                    ? VX_Membership::parse_expiry( get_user_meta( $user_id, VX_User_Meta::PLAN_VENCIMIENTO, true ) )
                    : 0;

                // Sin fecha guardada queda la cuenta por aprobar. Antes la celda
                // estimaba tres meses desde el registro; esa estimación ya no
                // existe porque la regla ya no es una fecha, y estimar una fecha
                // que el sistema no va a escribir es inventar.
                $por_aprobar = ! $meta_existe;

                $expiry_date = ( ! $por_aprobar && $expiry_ts > 0 ) ? date( 'Y-m-d', $expiry_ts ) : '';
                $diff        = $expiry_ts - time();

                if ( $por_aprobar ) {
                    $label = 'Por aprobar';
                    $color = '#5C5F6B';
                    $title = 'Esta cuenta todavía no tiene vencimiento guardado. Se escribe al aprobarla, y hoy se escribe sin vencimiento: el plan gratuito de los Pioneros termina cuando el programa llega a 100 miembros, no en una fecha por miembro.';
                } elseif ( $expiry_ts <= 0 ) {
                    $label = 'Sin vencimiento';
                    $color = '#5C5F6B';
                    $title = 'Este plan no vence. Al llegar a 100 miembros se avisa y se fija el plazo desde acá.';
                } elseif ( $diff < 0 ) {
                    $label = date_i18n( 'd/m/Y', $expiry_ts );
                    $color = '#AD4319';
                    $title = 'Venció el ' . date_i18n( 'd/m/Y', $expiry_ts ) . '.';
                } elseif ( $diff < 7 * DAY_IN_SECONDS ) {
                    $label = date_i18n( 'd/m/Y', $expiry_ts );
                    $color = '#C6C167';
                    $title = 'Vence esta semana.';
                } else {
                    $label = date_i18n( 'd/m/Y', $expiry_ts );
                    $color = '#689D85';
                    $title = 'Vence el ' . date_i18n( 'd/m/Y', $expiry_ts ) . '.';
                }

                $html  = '<span title="' . esc_attr( $title ) . '" style="font-size:12px;color:' . $color . ';font-weight:500;white-space:nowrap">' . esc_html( $label ) . '</span>';
                $html .= '<div class="vx-edit-control" style="gap:3px;align-items:center">';
                $nonce_url = wp_nonce_url( admin_url( 'users.php?action=vx_set_vencimiento&user_id=' . $user_id ), 'vx_set_vencimiento_' . $user_id );
                $html .= '<input type="date" value="' . esc_attr( $expiry_date ) . '" onchange="window.location=\'' . esc_js( $nonce_url ) . '&vencimiento=\'+this.value" style="font-size:11px;padding:2px 4px;border:1px solid #E6E2DB;border-radius:4px;color:#14161F">';
                $html .= '</div>';
                return $html;

            // ── Distintivo Pionero ─────────────────────────────────────────────
            case 'vx_pionero':
                $user = get_userdata( $user_id );
                if ( $user && in_array( 'administrator', (array) $user->roles, true ) ) return '<span style="color:#5C5F6B;font-size:12px">-</span>';
                $es_fundador = (bool) get_user_meta( $user_id, VX_User_Meta::ES_FUNDADOR, true );
                if ( $es_fundador ) {
                    $rm_url = wp_nonce_url( admin_url( 'users.php?action=vx_quitar_pionero&user_id=' . $user_id ), 'vx_quitar_pionero_' . $user_id );
                    return '<span style="background:#FAF7C9;color:#14161F;border-radius:4px;padding:2px 8px;font-size:11px;font-weight:700;white-space:nowrap">Pionero</span><br>'
                         . '<a href="' . esc_url( $rm_url ) . '" style="font-size:10px;color:#AD4319;margin-top:3px;display:block" onclick="return confirm(\'¿Quitar distintivo Pionero?\')">Quitar</a>';
                }
                $add_url = wp_nonce_url( admin_url( 'users.php?action=vx_dar_pionero&user_id=' . $user_id ), 'vx_dar_pionero_' . $user_id );
                return '<span style="color:#5C5F6B;font-size:12px">-</span><br>'
                     . '<a href="' . esc_url( $add_url ) . '" style="font-size:10px;color:#C6C167;margin-top:3px;display:block">Dar</a>';

            // Acá vivía un segundo case 'vx_plan', copia vieja de la columna de
            // más arriba. PHP se queda con el primero, así que no se dibujaba
            // nunca: leía el vencimiento por su cuenta y lo editaba con un
            // <form> anidado, que es justo lo que rompe el borrado masivo de
            // WordPress. Se saca para que una sola celda lea la fecha.

            case 'vx_comunidades':
                $coms = [];
                if ( get_user_meta( $user_id, VX_User_Meta::COMUNIDAD_OUT2B, true ) ) $coms[] = 'Vitrinexo LGBTQ+';
                if ( get_user_meta( $user_id, VX_User_Meta::COMUNIDAD_WOMAN,  true ) ) $coms[] = 'Vitrinexo Woman';
                if ( get_user_meta( $user_id, VX_User_Meta::COMUNIDAD_SENIOR, true ) ) $coms[] = 'Vitrinexo Senior';
                return $coms ? esc_html( implode( ', ', $coms ) ) : '-';

            case 'vx_stat_sol':
                if ( class_exists( 'VX_Stats' ) ) {
                    $n = VX_Stats::get_sol_recibidas( $user_id );
                    return $n > 0 ? '<strong>' . $n . '</strong>' : '<span style="color:#5C5F6B">0</span>';
                }
                return '-';

            case 'vx_stat_cnx':
                if ( class_exists( 'VX_Stats' ) ) {
                    $n = VX_Stats::get_conexiones( $user_id );
                    return $n > 0 ? '<strong style="color:#14161F">' . $n . '</strong>' : '<span style="color:#5C5F6B">0</span>';
                }
                return '-';
        }

        return $output;
    }

    public static function sortable_columns( array $columns ): array
    {
        $columns['vx_estado'] = 'vx_estado';
        $columns['vx_plan']   = 'vx_plan';
        return $columns;
    }

    public static function add_estado_filter(): void
    {
        $estado = isset( $_GET['vx_estado_filter'] ) ? sanitize_key( $_GET['vx_estado_filter'] ) : '';
        ?>
        <select name="vx_estado_filter">
            <option value="">Todos los estados</option>
            <option value="activo"    <?php selected( $estado, 'activo' ); ?>>Activo</option>
            <option value="pendiente" <?php selected( $estado, 'pendiente' ); ?>>Pendiente</option>
            <option value="rechazado" <?php selected( $estado, 'rechazado' ); ?>>Rechazado</option>
        </select>
        <?php
    }

    public static function filter_by_estado( WP_User_Query $query ): void
    {
        if ( ! is_admin() ) return;

        $estado = isset( $_GET['vx_estado_filter'] ) ? sanitize_key( $_GET['vx_estado_filter'] ) : '';
        if ( ! $estado ) return;

        $meta_query   = (array) $query->get( 'meta_query' );
        $meta_query[] = [
            'key'   => VX_User_Meta::ESTADO,
            'value' => $estado,
        ];
        $query->set( 'meta_query', $meta_query );
    }

    public static function handle_aprobar_verificacion(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Sin permiso.' );
        }

        $user_id = absint( $_GET['user_id'] ?? 0 );
        check_admin_referer( 'vx_aprobar_' . $user_id );

        VX_Verification::approve_manual( $user_id );

        wp_safe_redirect( admin_url( 'users.php?vx_aprobado=1' ) );
        exit;
    }

    public static function handle_verificar_senior(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Sin permiso.' );
        }

        $user_id = absint( $_GET['user_id'] ?? 0 );
        check_admin_referer( 'vx_verificar_senior_' . $user_id );
        VX_Senior_Verification::approve( $user_id );

        wp_safe_redirect( admin_url( 'users.php?vx_senior_aprobado=1' ) );
        exit;
    }

    /**
     * Activa un usuario directamente sin email de confirmación.
     * Útil para cuentas de admin, pruebas y usuarios migrados.
     */
    public static function handle_activar_directo(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Sin permiso.' );
        }

        $user_id = absint( $_GET['user_id'] ?? 0 );
        check_admin_referer( 'vx_activar_' . $user_id );

        if ( ! $user_id ) {
            wp_die( 'Usuario inválido.' );
        }

        $wp_user = get_userdata( $user_id );
        if ( ! $wp_user ) {
            wp_die( 'Usuario no encontrado.' );
        }

        // Marcar como activo
        update_user_meta( $user_id, VX_User_Meta::ESTADO,             'activo' );
        update_user_meta( $user_id, VX_User_Meta::ONBOARDING_COMPLETO, '1' );

        // Generar slug si no tiene
        $slug = (string) get_user_meta( $user_id, VX_User_Meta::PERFIL_SLUG, true );
        if ( ! $slug && class_exists( 'VX_Slug_Helper' ) ) {
            $nombre   = (string) get_user_meta( $user_id, VX_User_Meta::NOMBRE,   true ) ?: $wp_user->first_name;
            $apellido = (string) get_user_meta( $user_id, VX_User_Meta::APELLIDO,  true ) ?: $wp_user->last_name;
            if ( ! $nombre ) {
                // Fallback: usar display_name
                $parts    = explode( ' ', $wp_user->display_name, 2 );
                $nombre   = $parts[0];
                $apellido = $parts[1] ?? '';
            }
            $slug = VX_Slug_Helper::generate( $nombre, $apellido, $user_id );
            update_user_meta( $user_id, VX_User_Meta::PERFIL_SLUG, $slug );
        }

        wp_safe_redirect( admin_url( 'users.php?vx_activado=1' ) );
        exit;
    }

    /**
     * Cambia el plan de un usuario desde la lista de usuarios.
     * Acción: vx_set_plan (POST desde formulario en columna vx_plan).
     */
    public static function handle_set_plan(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Sin permiso.' );
        }

        // Soporta tanto GET (links badge) como POST (formulario de plan)
        $params  = array_merge( (array) $_GET, (array) $_POST );
        $user_id = absint( $params['user_id'] ?? 0 );

        check_admin_referer( 'vx_set_plan_' . $user_id );

        if ( ! $user_id ) wp_die( 'Usuario inválido.' );

        // ── Cambio de distintivo Pionero (via GET link) ──
        if ( ! empty( $params['quitar_fundador'] ) ) {
            update_user_meta( $user_id, VX_User_Meta::ES_FUNDADOR, false );
            update_user_meta( $user_id, VX_User_Meta::PRECIO_PREFERENTE, false );
            wp_safe_redirect( admin_url( 'users.php?vx_plan_cambiado=1' ) );
            exit;
        }

        if ( ! empty( $params['dar_fundador'] ) ) {
            update_user_meta( $user_id, VX_User_Meta::ES_FUNDADOR, true );
            update_user_meta( $user_id, VX_User_Meta::PRECIO_PREFERENTE, true );
            wp_safe_redirect( admin_url( 'users.php?vx_plan_cambiado=1' ) );
            exit;
        }

        // ── Cambio de plan de facturación (via POST form) ──
        $plan  = sanitize_key( $params['plan'] ?? '' );
        $dias  = max( 0, absint( $params['dias'] ?? 0 ) );

        $planes_validos = [ 'gratuito', 'mensual', 'anual', 'preferencial' ];
        if ( ! in_array( $plan, $planes_validos, true ) ) {
            wp_die( 'Plan no válido.' );
        }

        $membresia = VX_Membership::get( $user_id );
        $expiry    = ( 'gratuito' === $plan || 0 === $dias )
            ? 0
            : (int) strtotime( '+' . $dias . ' days' );
        $membresia->activate( $plan, $expiry );

        wp_safe_redirect( admin_url( 'users.php?vx_plan_cambiado=1' ) );
        exit;
    }

    /** CSS + JS: tabla con ancho automático, scroll horizontal y columnas redimensionables */
    public static function users_table_css(): void
    {
        ?>
<style>
/* Wrapper scroll horizontal */
.wrap table.wp-list-table.widefat {
    display: block;
    overflow-x: auto;
    width: 100%;
    table-layout: auto !important;
}
.wrap table.wp-list-table {
    width: max-content !important;
    min-width: 100%;
    table-layout: auto !important;
}
/* Celdas compactas */
#the-list tr { height: auto !important; }
#the-list td,
.wp-list-table thead th,
.wp-list-table tfoot th {
    vertical-align: middle !important;
    padding: 6px 10px !important;
    white-space: nowrap;
    position: relative;
}
/* Columnas de texto largo: permitir wrap */
.column-name, .column-email, .column-vx_empresa, .column-vx_cargo {
    white-space: normal;
    min-width: 100px;
}
/* Controles inline: visibles solo en hover */
.vx-edit-control       { display: none; margin-top: 3px; }
.vx-edit-control-block { display: none; margin-top: 3px; }
td:hover .vx-edit-control       { display: flex; gap: 3px; align-items: center; }
td:hover .vx-edit-control-block { display: block; }
/* Handles de resize */
.vx-rh {
    position: absolute; right: 0; top: 0;
    width: 5px; height: 100%;
    cursor: col-resize;
    user-select: none; z-index: 10;
}
.vx-rh:hover, .vx-rh.active { background:#FF7A4D; opacity: .5; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var tbl = document.querySelector('table.wp-list-table');
    if (!tbl) return;
    tbl.querySelectorAll('thead th').forEach(function(th) {
        var h = document.createElement('div');
        h.className = 'vx-rh';
        th.appendChild(h);
        var x0, w0;
        h.addEventListener('mousedown', function(e) {
            e.preventDefault();
            x0 = e.clientX; w0 = th.offsetWidth;
            h.classList.add('active');
            function mv(e) {
                var w = Math.max(50, w0 + e.clientX - x0);
                th.style.width = th.style.minWidth = w + 'px';
            }
            function up() {
                h.classList.remove('active');
                document.removeEventListener('mousemove', mv);
                document.removeEventListener('mouseup', up);
            }
            document.addEventListener('mousemove', mv);
            document.addEventListener('mouseup', up);
        });
    });
});
</script>
        <?php
    }


    /** Cambia el plan de un usuario desde el dropdown inline. */
    public static function handle_cambiar_plan(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permiso.' );
        $user_id = absint( $_GET['user_id'] ?? 0 );
        check_admin_referer( 'vx_cambiar_plan_' . $user_id );
        if ( ! $user_id ) wp_die( 'Usuario inválido.' );
        $plan = sanitize_text_field( $_GET['plan'] ?? '' );
        if ( $plan ) update_user_meta( $user_id, VX_User_Meta::PLAN, $plan );
        wp_safe_redirect( admin_url( 'users.php?vx_plan_cambiado=1' ) );
        exit;
    }

    /** Actualiza la fecha de vencimiento del plan desde el input date inline. */
    public static function handle_set_vencimiento(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permiso.' );
        $user_id = absint( $_GET['user_id'] ?? 0 );
        check_admin_referer( 'vx_set_vencimiento_' . $user_id );
        if ( ! $user_id ) wp_die( 'Usuario inválido.' );

        $fecha = sanitize_text_field( $_GET['vencimiento'] ?? '' );
        $ts    = $fecha ? (int) strtotime( $fecha . ' 23:59:59' ) : 0;
        update_user_meta( $user_id, VX_User_Meta::PLAN_VENCIMIENTO, $ts );
        wp_safe_redirect( admin_url( 'users.php?vx_plan_cambiado=1' ) );
        exit;
    }

    /** Asigna el distintivo Pionero. */
    public static function handle_dar_pionero(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permiso.' );
        $user_id = absint( $_GET['user_id'] ?? 0 );
        check_admin_referer( 'vx_dar_pionero_' . $user_id );
        update_user_meta( $user_id, VX_User_Meta::ES_FUNDADOR, true );
        update_user_meta( $user_id, VX_User_Meta::PRECIO_PREFERENTE, true );
        wp_safe_redirect( admin_url( 'users.php?vx_plan_cambiado=1' ) );
        exit;
    }

    /** Quita el distintivo Pionero. */
    public static function handle_quitar_pionero(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permiso.' );
        $user_id = absint( $_GET['user_id'] ?? 0 );
        check_admin_referer( 'vx_quitar_pionero_' . $user_id );
        update_user_meta( $user_id, VX_User_Meta::ES_FUNDADOR, false );
        update_user_meta( $user_id, VX_User_Meta::PRECIO_PREFERENTE, false );
        wp_safe_redirect( admin_url( 'users.php?vx_plan_cambiado=1' ) );
        exit;
    }

    /**
     * Muestra el aviso con el botón de normalizar ciudades en el listado de usuarios.
     */
    public static function notice_normalize_ciudades(): void
    {
        $screen = get_current_screen();
        if ( ! $screen || $screen->id !== 'users' ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;

        if ( ! empty( $_GET['vx_normalize_done'] ) ) {
            $n = (int) $_GET['vx_normalize_done'];
            echo '<div class="notice notice-success is-dismissible"><p>Ciudades normalizadas: <strong>' . $n . '</strong> usuario' . ( $n !== 1 ? 's' : '' ) . ' actualizados.</p></div>';
            return;
        }

        $url = wp_nonce_url(
            admin_url( 'users.php?action=vx_normalize_ciudades' ),
            'vx_normalize_ciudades'
        );
        echo '<div class="notice notice-info"><p>'
           . '<strong>Vitrinexo:</strong> Estandarizar ciudades de usuarios al listado canónico. '
           . '<a href="' . esc_url( $url ) . '" class="button button-small" onclick="return confirm(\'¿Normalizar ciudades de todos los miembros? Se intentará mapear valores libres (ej: Providencia · Santiago). Los que no tengan correspondencia quedarán sin cambios.\')">Normalizar ciudades ahora</a>'
           . '</p></div>';
    }

    /**
     * Aviso con botón para migrar estadísticas históricas desde CPTs existentes.
     */
    public static function notice_stats_backfill(): void
    {
        $screen = get_current_screen();
        if ( ! $screen || $screen->id !== 'users' ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;

        if ( ! empty( $_GET['vx_stats_done'] ) ) {
            $r = json_decode( base64_decode( sanitize_text_field( $_GET['vx_stats_done'] ) ), true );
            if ( $r ) {
                echo '<div class="notice notice-success is-dismissible"><p>Stats migradas: <strong>'
                   . (int)($r['global']) . '</strong> conexiones efectivas · <strong>'
                   . (int)($r['sol_recibidas']) . '</strong> solicitudes totales. Total global actual: <strong>'
                   . ( class_exists('VX_Stats') ? VX_Stats::get_total_conexiones() : '?' )
                   . '</strong> conexiones.</p></div>';
            }
            return;
        }

        // Mostrar total actual
        $total = class_exists( 'VX_Stats' ) ? VX_Stats::get_total_conexiones() : 0;
        $url   = wp_nonce_url(
            admin_url( 'users.php?action=vx_stats_backfill' ),
            'vx_stats_backfill'
        );
        echo '<div class="notice notice-info"><p>'
           . '<strong>Vitrinexo Stats:</strong> '
           . 'Conexiones totales registradas: <strong>' . $total . '</strong>. '
           . 'Si acabas de activar el sistema de stats, migra los datos históricos: '
           . '<a href="' . esc_url( $url ) . '" class="button button-small" onclick="return confirm(\'¿Migrar estadísticas históricas desde las conexiones existentes?\')">Migrar stats históricas</a>'
           . '</p></div>';
    }

    public static function handle_stats_backfill(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permiso.' );
        check_admin_referer( 'vx_stats_backfill' );

        if ( ! class_exists( 'VX_Stats' ) ) wp_die( 'VX_Stats no disponible.' );

        $result = VX_Stats::backfill_from_existing_connections();
        $encoded = base64_encode( wp_json_encode( $result ) );

        wp_safe_redirect( admin_url( 'users.php?vx_stats_done=' . urlencode( $encoded ) ) );
        exit;
    }

    /**
     * Procesa la normalización: lee ciudad+país de cada usuario activo,
     * intenta mapearla al valor canónico y guarda si hay cambio.
     */
    public static function handle_normalize_ciudades(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permiso.' );
        check_admin_referer( 'vx_normalize_ciudades' );

        if ( ! function_exists( 'vx_normalizar_ciudad' ) ) {
            wp_die( 'Función vx_normalizar_ciudad() no disponible.' );
        }

        $users = get_users( [
            'role'       => 'subscriber',
            'fields'     => 'ids',
            'number'     => -1,
            'meta_query' => [
                [ 'key' => VX_User_Meta::ESTADO, 'value' => 'activo' ],
            ],
        ] );

        $cambiados = 0;

        foreach ( $users as $uid ) {
            $ciudad = (string) get_user_meta( $uid, VX_User_Meta::CIUDAD, true );
            $pais   = (string) get_user_meta( $uid, VX_User_Meta::PAIS,   true );

            if ( ! $ciudad || ! $pais ) continue;

            $normalizada = vx_normalizar_ciudad( $ciudad, $pais );

            if ( $normalizada !== $ciudad ) {
                update_user_meta( $uid, VX_User_Meta::CIUDAD, $normalizada );
                $cambiados++;
            }
        }

        wp_safe_redirect( admin_url( 'users.php?vx_normalize_done=' . $cambiados ) );
        exit;
    }

    // ── Export CSV de todos los miembros ──────────────────────────────────────

    /**
     * Aviso con botón de descarga en la pantalla de usuarios.
     */
    public static function notice_export_csv(): void
    {
        $screen = get_current_screen();
        if ( ! $screen || $screen->id !== 'users' ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;

        $url = wp_nonce_url(
            admin_url( 'users.php?action=vx_export_members_csv' ),
            'vx_export_members_csv'
        );

        echo '<div class="notice notice-info"><p>'
           . '<strong>Vitrinexo:</strong> '
           . '<a href="' . esc_url( $url ) . '" class="button button-primary button-small">Exportar todos los miembros (CSV)</a> '
           . '<span style="color:#5C5F6B;font-size:12px;margin-left:8px">Incluye tags, industria, ciudad, empresa, stats de conexiones y más.</span>'
           . '</p></div>';
    }

    /**
     * Genera y descarga el CSV completo de todos los miembros activos.
     * Incluye todos los datos relevantes para análisis.
     */
    public static function handle_export_members_csv(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permiso.' );
        check_admin_referer( 'vx_export_members_csv' );

        $users = get_users( [
            'role'       => 'subscriber',
            'fields'     => 'ids',
            'number'     => -1,
            'orderby'    => 'registered',
            'order'      => 'ASC',
        ] );

        $filename = 'vitrinexo-miembros-' . date( 'Ymd-His' ) . '.csv';

        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $out = fopen( 'php://output', 'w' );
        // BOM para Excel
        fprintf( $out, chr(0xEF) . chr(0xBB) . chr(0xBF) );

        // ── Cabecera ──────────────────────────────────────────────────────────
        fputcsv( $out, [
            // Identidad
            'ID', 'Nombre', 'Apellido', 'Email', 'Slug',
            // Datos personales
            'Ciudad', 'País', 'Género', 'Teléfono', 'LinkedIn personal',
            'Contacto preferido', 'Bio',
            // Plan y estado
            'Estado', 'Plan', 'Fundador', 'Fecha registro',
            // Empresa principal
            'Empresa', 'Cargo', 'Industria', 'Sector (tags)', 'Web empresa', 'LinkedIn empresa',
            // Tags de matching (clave para análisis)
            'Ofrece (texto)', 'Busca (texto)',
            'Offer tags', 'Seek tags', 'Profile tags',
            // Comunidades
            'Vitrinexo LGBTQ+', 'Vitrinexo Woman', 'Vitrinexo Senior',
            // Estadísticas de conexiones
            'Solicitudes recibidas (histórico)', 'Conexiones efectivas (histórico)',
            // 4Dinner
            'Dinners asistidos',
        ] );

        // ── Filas ─────────────────────────────────────────────────────────────
        foreach ( $users as $uid ) {
            $u = VX_User::get( (int) $uid );
            if ( ! $u ) continue;

            $wp_user  = get_userdata( $uid );
            $empresa  = $u->get_empresa_activa();
            $emp_id   = $empresa ? $empresa->ID : 0;

            // Calcular dinners a los que asistió (estado = realizado y uid en asignados)
            $dinners_asistidos = 0;
            if ( class_exists( 'VX_Dinner' ) ) {
                $dinner_posts = get_posts( [
                    'post_type'      => 'vx_dinner',
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'fields'         => 'ids',
                    'meta_query'     => [
                        [ 'key' => VX_Dinner_Meta::ESTADO, 'value' => 'realizado' ],
                    ],
                ] );
                foreach ( $dinner_posts as $dp_id ) {
                    $asignados = (array) get_post_meta( $dp_id, VX_Dinner_Meta::ASIGNADOS, true );
                    if ( in_array( (int) $uid, array_map( 'intval', $asignados ), true ) ) {
                        $dinners_asistidos++;
                    }
                }
            }

            fputcsv( $out, [
                // Identidad
                $uid,
                $u->get_nombre(),
                $u->get_apellido(),
                $u->get_email(),
                $u->get_slug(),
                // Datos personales
                $u->get_ciudad(),
                $u->get_pais(),
                (string) get_user_meta( $uid, VX_User_Meta::GENERO,            true ),
                $u->get_telefono(),
                $u->get_linkedin(),
                $u->get_contacto_preferido(),
                $u->get_bio(),
                // Plan y estado
                (string) get_user_meta( $uid, VX_User_Meta::ESTADO,            true ),
                (string) get_user_meta( $uid, VX_User_Meta::PLAN,              true ),
                get_user_meta( $uid, VX_User_Meta::ES_FUNDADOR, true ) ? 'Sí' : 'No',
                $wp_user ? date( 'Y-m-d', strtotime( $wp_user->user_registered ) ) : '',
                // Empresa
                $empresa ? $empresa->post_title                                   : '',
                $emp_id  ? (string) get_post_meta( $emp_id, 'vx_cargo',     true ) : '',
                $u->get_industria(),
                $emp_id  ? (string) get_post_meta( $emp_id, 'vx_sector',    true ) : '',
                $emp_id  ? (string) get_post_meta( $emp_id, 'vx_web',       true ) : '',
                $emp_id  ? (string) get_post_meta( $emp_id, 'vx_linkedin',  true ) : '',
                // Tags
                (string) get_user_meta( $uid, VX_User_Meta::OFFER_TEXTO,       true ),
                (string) get_user_meta( $uid, VX_User_Meta::SEEK_TEXTO,        true ),
                implode( ' | ', (array) get_user_meta( $uid, VX_User_Meta::OFFER_TAGS,    true ) ),
                implode( ' | ', (array) get_user_meta( $uid, VX_User_Meta::SEEK_TAGS,     true ) ),
                implode( ' | ', (array) get_user_meta( $uid, VX_User_Meta::PROFILE_TAGS,  true ) ),
                // Comunidades
                get_user_meta( $uid, VX_User_Meta::COMUNIDAD_OUT2B,  true ) ? 'Sí' : 'No',
                get_user_meta( $uid, VX_User_Meta::COMUNIDAD_WOMAN,  true ) ? 'Sí' : 'No',
                get_user_meta( $uid, VX_User_Meta::COMUNIDAD_SENIOR, true ) ? 'Sí' : 'No',
                // Stats
                class_exists( 'VX_Stats' ) ? VX_Stats::get_sol_recibidas( $uid ) : 0,
                class_exists( 'VX_Stats' ) ? VX_Stats::get_conexiones( $uid )    : 0,
                // 4Dinner
                $dinners_asistidos,
            ] );
        }

        fclose( $out );
        exit;
    }

    // ── Campos Vitrinexo en pantalla de edición de usuario WP ───────────────

    public static function init_profile_fields(): void
    {
        add_action( 'show_user_profile',        [ self::class, 'render_profile_fields' ] );
        add_action( 'edit_user_profile',        [ self::class, 'render_profile_fields' ] );
        add_action( 'personal_options_update',  [ self::class, 'save_profile_fields' ] );
        add_action( 'edit_user_profile_update', [ self::class, 'save_profile_fields' ] );
        // Agregar campos también al formulario "Añadir usuario"
        add_action( 'user_new_form',            [ self::class, 'render_profile_fields_new' ] );
        add_action( 'user_register',            [ self::class, 'save_profile_fields' ] );
    }

    public static function render_profile_fields_new(): void
    {
        wp_nonce_field( 'vx_perfil_admin', 'vx_perfil_admin_nonce' );
        ?>
        <h2>Datos Vitrinexo</h2>
        <table class="form-table" role="presentation">
        <?php
        $fields = [
            [ 'key' => 'vx_nombre',           'label' => 'Nombre',    'type' => 'text' ],
            [ 'key' => 'vx_apellido',         'label' => 'Apellido',  'type' => 'text' ],
            [ 'key' => 'vx_empresa_inicial',  'label' => 'Empresa',   'type' => 'text' ],
            [ 'key' => VX_User_Meta::CARGO,    'label' => 'Cargo',     'type' => 'text' ],
            [ 'key' => VX_User_Meta::CIUDAD,   'label' => 'Ciudad',    'type' => 'text' ],
            [ 'key' => VX_User_Meta::PAIS,     'label' => 'País',      'type' => 'text' ],
            [ 'key' => VX_User_Meta::TELEFONO, 'label' => 'Teléfono',  'type' => 'tel'  ],
            [ 'key' => VX_User_Meta::LINKEDIN, 'label' => 'LinkedIn',  'type' => 'url'  ],
        ];
        foreach ( $fields as $f ) : ?>
        <tr>
            <th><label for="vx_new_<?php echo esc_attr( $f['key'] ); ?>"><?php echo esc_html( $f['label'] ); ?></label></th>
            <td><input type="<?php echo esc_attr( $f['type'] ); ?>" name="<?php echo esc_attr( $f['key'] ); ?>" id="vx_new_<?php echo esc_attr( $f['key'] ); ?>" value="" class="regular-text" /></td>
        </tr>
        <?php endforeach; ?>
        </table>
        <?php
    }

    /**
     * Sección editable "Perfil Vitrinexo" en la pantalla de usuario del admin.
     * Permite a un administrador mantener actualizado el perfil de cualquier miembro
     * (Bio, Ofrece, Busca, Ciudad, Industria, tags, etc.) sin que el miembro deba entrar.
     */
    public static function render_profile_fields( WP_User $user ): void
    {
        $uid       = $user->ID;
        $g         = fn( $k ) => (string) get_user_meta( $uid, $k, true );
        $industria = $g( VX_User_Meta::INDUSTRIA );

        // Empresa: preferir el título de la empresa activa (CPT); si no, la meta inicial.
        $empresa_val = $g( 'vx_empresa_inicial' );
        $vx_user     = class_exists( 'VX_User' ) ? VX_User::get( $uid ) : null;
        if ( $vx_user ) {
            $emp = $vx_user->get_empresa_activa();
            if ( $emp ) { $empresa_val = $emp->post_title; }
        }

        wp_nonce_field( 'vx_perfil_admin', 'vx_perfil_admin_nonce' );
        ?>
        <h2>Perfil Vitrinexo <span style="font-weight:400;font-size:13px;color:#5C5F6B">— editable por administradores</span></h2>
        <p class="description" style="margin-bottom:8px">Actualiza aquí el perfil del miembro. Los cambios se reflejan en el directorio y su perfil público.</p>
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="vx_nombre">Nombre</label></th>
                <td><input type="text" name="vx_nombre" id="vx_nombre" value="<?php echo esc_attr( $g( 'vx_nombre' ) ); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="vx_apellido">Apellido</label></th>
                <td><input type="text" name="vx_apellido" id="vx_apellido" value="<?php echo esc_attr( $g( 'vx_apellido' ) ); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="vx_empresa_inicial">Empresa</label></th>
                <td><input type="text" name="vx_empresa_inicial" id="vx_empresa_inicial" value="<?php echo esc_attr( $empresa_val ); ?>" class="regular-text" />
                <p class="description">Nombre de la empresa que se muestra en el perfil.</p></td>
            </tr>
            <tr>
                <th><label for="vx_cargo_inicial">Cargo</label></th>
                <td><input type="text" name="vx_cargo_inicial" id="vx_cargo_inicial" value="<?php echo esc_attr( $g( VX_User_Meta::CARGO ) ); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="vx_ciudad">Ciudad</label></th>
                <td><input type="text" name="vx_ciudad" id="vx_ciudad" value="<?php echo esc_attr( $g( VX_User_Meta::CIUDAD ) ); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="vx_pais">País</label></th>
                <td><input type="text" name="vx_pais" id="vx_pais" value="<?php echo esc_attr( $g( VX_User_Meta::PAIS ) ); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="vx_industria">Industria</label></th>
                <td>
                    <?php if ( function_exists( 'vx_get_industrias' ) ) :
                        $inds = vx_get_industrias(); ?>
                    <select name="vx_industria" id="vx_industria" class="regular-text">
                        <option value="">— Selecciona —</option>
                        <?php foreach ( $inds as $ind ) : ?>
                        <option value="<?php echo esc_attr( $ind ); ?>" <?php selected( $industria, $ind ); ?>><?php echo esc_html( $ind ); ?></option>
                        <?php endforeach; ?>
                        <?php if ( $industria && ! in_array( $industria, $inds, true ) ) : ?>
                        <option value="<?php echo esc_attr( $industria ); ?>" selected><?php echo esc_html( $industria ); ?></option>
                        <?php endif; ?>
                    </select>
                    <?php else : ?>
                    <input type="text" name="vx_industria" id="vx_industria" value="<?php echo esc_attr( $industria ); ?>" class="regular-text" />
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><label for="vx_telefono">Teléfono</label></th>
                <td><input type="tel" name="vx_telefono" id="vx_telefono" value="<?php echo esc_attr( $g( VX_User_Meta::TELEFONO ) ); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="vx_linkedin">LinkedIn</label></th>
                <td><input type="url" name="vx_linkedin" id="vx_linkedin" value="<?php echo esc_attr( $g( VX_User_Meta::LINKEDIN ) ); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="vx_bio">Bio profesional</label></th>
                <td><textarea name="vx_bio" id="vx_bio" rows="4" class="large-text"><?php echo esc_textarea( $g( VX_User_Meta::BIO ) ); ?></textarea></td>
            </tr>
            <tr>
                <th><label for="vx_offer_texto">Ofrece (texto)</label></th>
                <td><textarea name="vx_offer_texto" id="vx_offer_texto" rows="4" class="large-text"><?php echo esc_textarea( $g( VX_User_Meta::OFFER_TEXTO ) ); ?></textarea></td>
            </tr>
            <tr>
                <th><label for="vx_seek_texto">Busca (texto)</label></th>
                <td><textarea name="vx_seek_texto" id="vx_seek_texto" rows="4" class="large-text"><?php echo esc_textarea( $g( VX_User_Meta::SEEK_TEXTO ) ); ?></textarea></td>
            </tr>
            <?php
            $tags_preset_admin = function_exists( 'vx_get_tags_preset' ) ? vx_get_tags_preset() : [];
            // Comparación case-insensitive: los tags aplicados vía propuesta quedan en
            // minúsculas (VX_Tag_Helper::normalize), pero el preset se muestra con su
            // capitalización "bonita" — sin esto, un tag ya aceptado nunca calzaría con
            // su chip del preset y aparecería duplicado como "custom".
            $render_tag_picker = function ( string $meta_key, string $field_name, string $modifier ) use ( $uid, $tags_preset_admin ) {
                $actuales      = (array) get_user_meta( $uid, $meta_key, true );
                $actuales_low  = array_map( 'mb_strtolower', $actuales );
                $preset_low    = array_map( 'mb_strtolower', $tags_preset_admin );
                ?>
                <div class="vx-tag-selector vx-tag-selector--<?php echo esc_attr( $modifier ); ?>" data-hidden="<?php echo esc_attr( $field_name ); ?>_hidden">
                    <?php foreach ( $tags_preset_admin as $tag ) :
                        $sel = in_array( mb_strtolower( $tag ), $actuales_low, true );
                    ?>
                    <span class="vx-tag-chip <?php echo $sel ? 'vx-tag-chip--selected' : ''; ?>" onclick="vxAdminToggleTag(this)"><?php echo esc_html( $tag ); ?></span>
                    <?php endforeach; ?>
                    <?php foreach ( $actuales as $custom ) :
                        if ( in_array( mb_strtolower( $custom ), $preset_low, true ) ) continue; // ya está como chip del preset
                    ?>
                    <span class="vx-tag-chip vx-tag-chip--selected" onclick="vxAdminToggleTag(this)"><?php echo esc_html( $custom ); ?></span>
                    <?php endforeach; ?>
                </div>
                <input type="text" placeholder="Escribe otra etiqueta y presiona Enter" class="regular-text" style="margin-top:6px"
                       onkeydown="vxAdminAddCustomTag(event)">
                <input type="hidden" name="<?php echo esc_attr( $field_name ); ?>" id="<?php echo esc_attr( $field_name ); ?>_hidden"
                       value="<?php echo esc_attr( implode( ', ', $actuales ) ); ?>">
                <?php
            };
            ?>
            <tr>
                <th>Tags que ofrece</th>
                <td>
                    <?php $render_tag_picker( VX_User_Meta::OFFER_TAGS, 'vx_offer_tags', 'offer' ); ?>
                    <p class="description">Selecciona de la lista (son las mismas que ve el usuario) o agrega una nueva con Enter. Máximo 5 — si eliges más, solo se guardan los primeros 5.</p>
                </td>
            </tr>
            <tr>
                <th>Tags que busca</th>
                <td>
                    <?php $render_tag_picker( VX_User_Meta::SEEK_TAGS, 'vx_seek_tags', 'seek' ); ?>
                    <p class="description">Selecciona de la lista (son las mismas que ve el usuario) o agrega una nueva con Enter. Máximo 5 — si eliges más, solo se guardan los primeros 5.</p>
                </td>
            </tr>
            <tr>
                <th>Propuesta al usuario</th>
                <td>
                    <?php $propuesta = function_exists( 'vx_get_pending_profile_proposal' ) ? vx_get_pending_profile_proposal( $uid ) : null; ?>
                    <?php if ( $propuesta ) : ?>
                    <p class="description" style="color:#C6C167">
                        Ya hay una propuesta pendiente enviada el
                        <?php echo esc_html( date_i18n( 'd/m/Y H:i', (int) $propuesta['created_at'] ) ); ?>,
                        esperando respuesta del usuario. Al enviar otra, se reemplaza.
                    </p>
                    <?php endif; ?>
                    <input type="hidden" name="vx_perfil_admin_accion" id="vx_perfil_admin_accion" value="">
                    <button type="button" class="button" onclick="vxAdminEnviarPropuesta(this)">
                        Enviar propuesta de actualización de datos
                    </button>
                    <p class="description">
                        Envía como propuesta <strong>todos los campos de esta pantalla que hayas cambiado</strong> (nombre, empresa, cargo, ciudad, país, industria, teléfono, LinkedIn, bio, tags): el usuario debe aceptarla antes de que se apliquen a su perfil.
                        "Estado Vitrinexo" nunca pasa por aquí, siempre se guarda directo.
                    </p>
                </td>
            </tr>
            <tr>
                <th><label for="vx_estado">Estado Vitrinexo</label></th>
                <td>
                    <?php $estado = $g( VX_User_Meta::ESTADO ); ?>
                    <select name="vx_estado" id="vx_estado">
                        <?php foreach ( [ 'activo' => 'Activo', 'pendiente' => 'Pendiente', 'rechazado' => 'Rechazado' ] as $ev => $el ) : ?>
                        <option value="<?php echo esc_attr( $ev ); ?>" <?php selected( $estado, $ev ); ?>><?php echo esc_html( $el ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>
        <style>
        .vx-tag-chip {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 12px; margin: 0 6px 6px 0;
            border-radius: 4px; border: 1.5px solid #E6E2DB;
            background: #fff; font-size: 12px; font-weight: 500;
            color: #5C5F6B; cursor: pointer; user-select: none;
        }
        .vx-tag-chip:hover { border-color:#FF7A4D; color:#AD4319; }
        .vx-tag-selector--offer .vx-tag-chip--selected { border-color:#7DBF9E; background: #CBE5D8; color: #14161F; }
        .vx-tag-selector--seek  .vx-tag-chip--selected { border-color: #C7A3B6; background: #FBE8F1; color: #14161F; }
        </style>
        <script>
        function vxAdminToggleTag( el ) {
            el.classList.toggle( 'vx-tag-chip--selected' );
            vxAdminSyncHidden( el.closest( '.vx-tag-selector' ) );
        }
        function vxAdminAddCustomTag( e ) {
            if ( e.key !== 'Enter' ) return;
            e.preventDefault();
            var input     = e.target;
            var value     = input.value.trim();
            if ( ! value ) return;
            var container = input.previousElementSibling;
            var existe = Array.prototype.some.call( container.querySelectorAll( '.vx-tag-chip' ), function ( c ) {
                return c.textContent.trim().toLowerCase() === value.toLowerCase();
            } );
            if ( ! existe ) {
                var span = document.createElement( 'span' );
                span.className = 'vx-tag-chip vx-tag-chip--selected';
                span.textContent = value;
                span.onclick = function () { vxAdminToggleTag( span ); };
                container.appendChild( span );
            }
            input.value = '';
            vxAdminSyncHidden( container );
        }
        function vxAdminSyncHidden( container ) {
            var hidden = document.getElementById( container.dataset.hidden );
            if ( ! hidden ) return;
            var seleccionados = Array.prototype.map.call(
                container.querySelectorAll( '.vx-tag-chip--selected' ),
                function ( c ) { return c.textContent.trim(); }
            );
            hidden.value = seleccionados.join( ', ' );
        }
        // Botón type="button" a propósito: si fuera type="submit" sería el primer
        // submit del form nativo de WP y capturaría el Enter de cualquier campo
        // de texto anterior (nombre, ciudad, etc.), enviando una propuesta sin querer.
        function vxAdminEnviarPropuesta( btn ) {
            var form = btn.form || btn.closest( 'form' );
            var accion = document.getElementById( 'vx_perfil_admin_accion' );
            if ( accion ) accion.value = 'proponer';
            if ( form.requestSubmit ) form.requestSubmit(); else form.submit();
        }
        </script>
        <?php
    }

    public static function save_profile_fields( int $user_id ): void
    {
        if ( ! current_user_can( 'edit_user', $user_id ) ) return;
        // Solo procesa si nuestra sección fue enviada (nonce propio).
        if ( ! isset( $_POST['vx_perfil_admin_nonce'] )
            || ! wp_verify_nonce( sanitize_key( $_POST['vx_perfil_admin_nonce'] ), 'vx_perfil_admin' ) ) {
            return;
        }

        // Todos los campos "de contenido del perfil" (nombre, empresa, cargo,
        // ciudad, país, industria, teléfono, linkedin, bio, tags/textos) pasan
        // por VX_Profile_Fields — única función que sanitiza y única función
        // que aplica, reusada tanto por el guardado directo como por aceptar
        // una propuesta (evita que ambos caminos diverjan, como pasó con tags).
        $campos_posteados = VX_Profile_Fields::parse_posted( $_POST );
        $es_propuesta      = ( ( $_POST['vx_perfil_admin_accion'] ?? '' ) === 'proponer' );

        if ( $es_propuesta ) {
            // Solo lo que realmente cambió respecto a lo que el usuario tiene hoy —
            // si no, el aviso/email mostraría los 14 campos aunque el admin haya
            // tocado solo uno (el form siempre postea el valor actual de todos).
            $cambiados = VX_Profile_Fields::diff( $user_id, $campos_posteados );
            if ( $cambiados ) {
                self::create_profile_proposal( $user_id, $cambiados );
            }
        } else {
            VX_Profile_Fields::apply( $user_id, $campos_posteados );
            // Guardado directo: si había una propuesta pendiente sin resolver,
            // queda obsoleta — si no se invalida, el usuario podría aceptarla
            // más tarde y revertir silenciosamente este cambio directo más reciente.
            if ( vx_get_pending_profile_proposal( $user_id ) ) {
                delete_user_meta( $user_id, VX_User_Meta::PROFILE_PROPOSAL );
            }
        }

        // "Estado Vitrinexo" queda fuera de VX_Profile_Fields a propósito: es un
        // control de cuenta administrativo, no contenido del perfil del usuario,
        // así que siempre se guarda directo, incluso al "Enviar propuesta".
        if ( isset( $_POST[ VX_User_Meta::ESTADO ] ) ) {
            update_user_meta( $user_id, VX_User_Meta::ESTADO, sanitize_text_field( wp_unslash( $_POST[ VX_User_Meta::ESTADO ] ) ) );
        }
    }

    /**
     * Guarda los campos de perfil cambiados como una propuesta pendiente (en
     * vez de aplicarlos directo) y avisa al usuario por notificación interna +
     * email. El usuario debe aceptarla para que se apliquen a su perfil real.
     *
     * @param int   $user_id
     * @param array $campos    [post_key => valor], ya sanitizado y diffed — ver VX_Profile_Fields.
     */
    private static function create_profile_proposal( int $user_id, array $campos ): void
    {
        $proposal = [
            'admin_id'   => get_current_user_id(),
            'campos'     => $campos,
            'token'      => VX_Token_Helper::generate(),
            'created_at' => time(),
            'estado'     => 'pendiente',
        ];

        update_user_meta( $user_id, VX_User_Meta::PROFILE_PROPOSAL, $proposal );

        $vx_user = class_exists( 'VX_User' ) ? VX_User::get( $user_id ) : null;
        if ( $vx_user && $vx_user->get_email() ) {
            VX_Mailer::send(
                $vx_user->get_email(),
                'Un admin de Vitrinexo propone actualizar tu perfil',
                'perfil_propuesta',
                [
                    'nombre'   => $vx_user->get_nombre(),
                    'user_id'  => $user_id,
                    'token'    => $proposal['token'],
                    'campos'   => $campos,
                ]
            );
        }

        do_action( 'vx_profile_proposal_created', $user_id, $proposal );
    }
}
