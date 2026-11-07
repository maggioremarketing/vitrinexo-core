<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Tareas programadas via WP Cron.
 */
class VX_Cron
{
    const HOOK_HOURLY  = 'vx_cron_hourly';
    const HOOK_DAILY   = 'vx_cron_daily';
    const HOOK_WEEKLY  = 'vx_cron_weekly';
    const HOOK_MORNING = 'vx_cron_morning'; // 9 AM diario — recordatorio de validaciones

    /**
     * Registra las tareas cron. Llamado en register_activation_hook.
     */
    public static function schedule(): void
    {
        if ( ! wp_next_scheduled( self::HOOK_HOURLY ) ) {
            wp_schedule_event( time(), 'hourly', self::HOOK_HOURLY );
        }
        if ( ! wp_next_scheduled( self::HOOK_DAILY ) ) {
            wp_schedule_event( time(), 'daily', self::HOOK_DAILY );
        }
        if ( ! wp_next_scheduled( self::HOOK_WEEKLY ) ) {
            $next_monday = strtotime( 'next monday 8:00 AM' );
            wp_schedule_event( $next_monday, 'weekly', self::HOOK_WEEKLY );
        }
        if ( ! wp_next_scheduled( self::HOOK_MORNING ) ) {
            // Calcular el próximo 9:00 AM en la zona horaria del sitio
            $tz        = wp_timezone();
            $now_local = new DateTimeImmutable( 'now', $tz );
            $today9am  = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $now_local->format( 'Y-m-d' ) . ' 09:00:00', $tz );
            // Si ya pasaron las 9 AM de hoy → programar para mañana
            if ( $now_local >= $today9am ) {
                $today9am = $today9am->modify( '+1 day' );
            }
            wp_schedule_event( $today9am->getTimestamp(), 'daily', self::HOOK_MORNING );
        }

        self::register_hooks();
    }

    /**
     * Engancha los callbacks a sus hooks.
     * Se llama tanto en schedule() como en init (WP Cron requiere el hook
     * registrado en cada carga de página para poder dispararlo).
     */
    public static function register_hooks(): void
    {
        add_action( self::HOOK_HOURLY,  [ self::class, 'check_pending_connections' ] );
        add_action( self::HOOK_DAILY,   [ self::class, 'check_expired_memberships' ] );
        add_action( self::HOOK_DAILY,   [ self::class, 'send_dinner_reminders' ] );
        add_action( self::HOOK_WEEKLY,  [ self::class, 'send_weekly_matches' ] );
        add_action( self::HOOK_MORNING, [ self::class, 'send_admin_validaciones_reminder' ] );
    }

    /**
     * Programa los hooks que aún no están en la cola de WP Cron.
     * A diferencia de schedule(), NO llama a register_hooks() (evita duplicados).
     * Se llama en cada init para auto-reparar hooks faltantes cuando el plugin ya
     * estaba activo al agregar un nuevo hook (e.g. HOOK_MORNING añadido en 1.0.3).
     */
    public static function schedule_missing(): void
    {
        if ( ! wp_next_scheduled( self::HOOK_HOURLY ) ) {
            wp_schedule_event( time(), 'hourly', self::HOOK_HOURLY );
        }
        if ( ! wp_next_scheduled( self::HOOK_DAILY ) ) {
            wp_schedule_event( time(), 'daily', self::HOOK_DAILY );
        }
        if ( ! wp_next_scheduled( self::HOOK_WEEKLY ) ) {
            $next_monday = strtotime( 'next monday 8:00 AM' );
            wp_schedule_event( $next_monday, 'weekly', self::HOOK_WEEKLY );
        }
        if ( ! wp_next_scheduled( self::HOOK_MORNING ) ) {
            $tz        = wp_timezone();
            $now_local = new DateTimeImmutable( 'now', $tz );
            $today9am  = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $now_local->format( 'Y-m-d' ) . ' 09:00:00', $tz );
            if ( $now_local >= $today9am ) {
                $today9am = $today9am->modify( '+1 day' );
            }
            wp_schedule_event( $today9am->getTimestamp(), 'daily', self::HOOK_MORNING );
        }
    }

    /**
     * Elimina las tareas cron. Llamado en register_deactivation_hook.
     */
    public static function unschedule(): void
    {
        foreach ( [ self::HOOK_HOURLY, self::HOOK_DAILY, self::HOOK_WEEKLY, self::HOOK_MORNING ] as $hook ) {
            $timestamp = wp_next_scheduled( $hook );
            if ( $timestamp ) {
                wp_unschedule_event( $timestamp, $hook );
            }
        }
    }

    /**
     * Verifica conexiones pendientes:
     * - A las 72h: envía recordatorio al receptor.
     * - A los 7 días: marca como sin_respuesta.
     */
    public static function check_pending_connections(): void
    {
        $posts = get_posts( [
            'post_type'      => 'vx_conexion',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                [ 'key' => VX_Connection_Meta::ESTADO, 'value' => 'pendiente' ],
            ],
        ] );

        $now          = time();
        $hours_72     = 72 * 3600;
        $days_7       = 7 * 24 * 3600;

        foreach ( $posts as $post_id ) {
            $fecha_envio  = (int) get_post_meta( $post_id, VX_Connection_Meta::FECHA_ENVIO,          true );
            $recordatorio = (bool) get_post_meta( $post_id, VX_Connection_Meta::RECORDATORIO_ENVIADO, true );
            $elapsed      = $now - $fecha_envio;

            if ( $elapsed >= $days_7 ) {
                VX_Connection_Flow::mark_no_response( $post_id );
                continue;
            }

            if ( $elapsed >= $hours_72 && ! $recordatorio ) {
                self::send_reminder( $post_id );
            }
        }
    }

    /**
     * Envía el email de recordatorio para una conexión pendiente.
     *
     * @param int $post_id
     */
    private static function send_reminder( int $post_id ): void
    {
        $receptor_id   = (int) get_post_meta( $post_id, VX_Connection_Meta::RECEPTOR_ID, true );
        $emisor_nombre = (string) get_post_meta( $post_id, VX_Connection_Meta::EMISOR_NOMBRE,  true );
        $pitch         = (string) get_post_meta( $post_id, VX_Connection_Meta::PITCH,          true );
        $token_aceptar = (string) get_post_meta( $post_id, VX_Connection_Meta::TOKEN_ACEPTAR,  true );
        $token_rechazar= (string) get_post_meta( $post_id, VX_Connection_Meta::TOKEN_RECHAZAR, true );

        $receptor = VX_User::get( $receptor_id );
        if ( ! $receptor ) return;

        VX_Mailer::send(
            $receptor->get_email(),
            'Recordatorio: ' . $emisor_nombre . ' espera tu respuesta',
            'recordatorio_conexion',
            [
                'receptor_nombre' => $receptor->get_nombre_completo(),
                'emisor_nombre'   => $emisor_nombre,
                'pitch'           => $pitch,
                'token_aceptar'   => $token_aceptar,
                'token_rechazar'  => $token_rechazar,
            ]
        );

        update_post_meta( $post_id, VX_Connection_Meta::RECORDATORIO_ENVIADO, true );
    }

    /**
     * Envía recordatorio 24h antes a los confirmados de un 4Dinner.
     * Se ejecuta diariamente. Usa meta 'vx_dinner_reminder_sent' para no enviar dos veces.
     */
    public static function send_dinner_reminders(): void
    {
        if ( ! class_exists( 'VX_Dinner' ) ) return;

        $now        = time();
        $in_24h     = $now + DAY_IN_SECONDS;
        $in_25h     = $now + DAY_IN_SECONDS + 3600; // margen de 1h

        // Dinners que ocurren en las próximas 24-25h (ventana de 1h para el cron)
        $posts = get_posts( [
            'post_type'      => 'vx_dinner',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'AND',
                [ 'key' => VX_Dinner_Meta::ESTADO, 'value' => [ 'abierto', 'confirmado' ], 'compare' => 'IN' ],
                [ 'key' => VX_Dinner_Meta::FECHA,  'value' => $in_24h, 'compare' => '>=', 'type' => 'NUMERIC' ],
                [ 'key' => VX_Dinner_Meta::FECHA,  'value' => $in_25h, 'compare' => '<=', 'type' => 'NUMERIC' ],
            ],
        ] );

        foreach ( $posts as $post_id ) {
            // Evitar doble envío
            if ( get_post_meta( $post_id, 'vx_dinner_reminder_sent', true ) ) continue;

            $dinner    = VX_Dinner::get( $post_id );
            if ( ! $dinner ) continue;

            $asignados = $dinner->get_asignados();
            if ( empty( $asignados ) ) continue;

            // Datos del dinner para el email
            $dinner_data = [
                'ciudad'      => $dinner->get_ciudad(),
                'pais'        => $dinner->get_pais(),
                'fecha'       => $dinner->get_fecha(),
                'restaurante' => $dinner->get_restaurante(),
                'direccion'   => $dinner->get_direccion(),
            ];

            // Mesas del dinner para asignar nombre por usuario
            $mesas = $dinner->get_mesas();

            // Enviar recordatorio a cada confirmado con el nombre de su mesa
            foreach ( $asignados as $uid ) {
                $user = VX_User::get( (int) $uid );
                if ( ! $user ) continue;

                $mesa_nombre = '';
                foreach ( $mesas as $mesa ) {
                    if ( in_array( (int) $uid, array_map( 'intval', (array) ( $mesa['asignados'] ?? [] ) ), true ) ) {
                        $mesa_nombre = $mesa['nombre'];
                        break;
                    }
                }

                VX_Mailer::send(
                    $user->get_email(),
                    'Mañana es tu Vitrinexo 4Dinner en ' . $dinner->get_ciudad() . ' · Recordatorio',
                    'dinner_recordatorio',
                    [
                        'nombre'      => $user->get_nombre(),
                        'dinner'      => $dinner_data,
                        'mesa_nombre' => $mesa_nombre,
                    ]
                );
            }

            // Marcar como enviado para no repetir
            update_post_meta( $post_id, 'vx_dinner_reminder_sent', '1' );
        }
    }

    /**
     * Recordatorio diario a los admins a las 9 AM.
     * Solo se envía si hay cuentas pendientes de aprobación o solicitudes Senior sin revisar.
     */
    public static function send_admin_validaciones_reminder(): void
    {
        // ── Cuentas pendientes (correo genérico) ──────────────────────────────
        $cuentas_pendientes = get_users( [
            'role'       => 'subscriber',
            'fields'     => 'ids',
            'number'     => -1,
            'meta_query' => [
                'relation' => 'AND',
                [ 'key' => VX_User_Meta::ESTADO,            'value' => 'pendiente' ],
                [ 'key' => VX_User_Meta::TIPO_VERIFICACION, 'value' => 'manual' ],
            ],
        ] );

        // ── Solicitudes Senior pendientes ─────────────────────────────────────
        $senior_pendientes = get_users( [
            'role'       => 'subscriber',
            'fields'     => 'ids',
            'number'     => -1,
            'meta_query' => [
                'relation' => 'AND',
                [ 'key' => VX_User_Meta::SENIOR_SOLICITADO, 'value' => '1' ],
                [ 'key' => VX_User_Meta::SENIOR_VERIFICADO, 'compare' => 'NOT EXISTS' ],
            ],
        ] );

        $n_cuentas = count( $cuentas_pendientes );
        $n_senior  = count( $senior_pendientes );

        // Si no hay nada pendiente, no enviar
        if ( $n_cuentas === 0 && $n_senior === 0 ) {
            return;
        }

        // Construir listas de usuarios para el email
        $lista_cuentas = [];
        foreach ( $cuentas_pendientes as $uid ) {
            $wp_user = get_userdata( $uid );
            if ( $wp_user ) {
                $vx = VX_User::get( $uid );
                $lista_cuentas[] = [
                    'nombre' => $vx ? $vx->get_nombre_completo() : $wp_user->display_name,
                    'email'  => $wp_user->user_email,
                    'fecha'  => date_i18n( 'd/m/Y H:i', strtotime( $wp_user->user_registered ) ),
                ];
            }
        }

        $lista_senior = [];
        foreach ( $senior_pendientes as $uid ) {
            $wp_user = get_userdata( $uid );
            if ( $wp_user ) {
                $vx = VX_User::get( $uid );
                $lista_senior[] = [
                    'nombre'    => $vx ? $vx->get_nombre_completo() : $wp_user->display_name,
                    'email'     => $wp_user->user_email,
                    'industria' => $vx ? $vx->get_industria() : '',
                ];
            }
        }

        $admin_email = get_option( 'admin_email' );
        $validaciones_url = admin_url( 'admin.php?page=vx-validaciones' );

        VX_Mailer::send(
            $admin_email,
            '[Vitrinexo] ' . ( $n_cuentas + $n_senior ) . ' validación' . ( ( $n_cuentas + $n_senior ) !== 1 ? 'es' : '' ) . ' pendiente' . ( ( $n_cuentas + $n_senior ) !== 1 ? 's' : '' ) . ' hoy',
            'admin_validaciones_reminder',
            [
                'n_cuentas'      => $n_cuentas,
                'n_senior'       => $n_senior,
                'lista_cuentas'  => $lista_cuentas,
                'lista_senior'   => $lista_senior,
                'validaciones_url' => $validaciones_url,
            ]
        );
    }

    /**
     * Envía el resumen semanal de matches (cada lunes).
     */
    public static function send_weekly_matches(): void
    {
        $active_users = get_users( [
            'role'       => 'subscriber',
            'fields'     => 'ids',
            'number'     => -1,
            'meta_query' => [
                'relation' => 'AND',
                [ 'key' => VX_User_Meta::ESTADO,              'value' => 'activo' ],
                [ 'key' => VX_User_Meta::ONBOARDING_COMPLETO, 'value' => '1' ],
            ],
        ] );

        $since = strtotime( '-7 days' );

        foreach ( $active_users as $uid ) {
            $user    = VX_User::get( $uid );
            if ( ! $user ) continue;

            $matches = VX_Matches::get_new_since( $uid, $since );

            if ( empty( $matches['seeks'] ) && empty( $matches['offers'] ) ) {
                continue;
            }

            VX_Mailer::send(
                $user->get_email(),
                'Tus nexos de la semana en Vitrinexo',
                'match_semanal',
                [
                    'nombre'         => $user->get_nombre(),
                    'seeks_matches'  => $matches['seeks'],
                    'offers_matches' => $matches['offers'],
                ]
            );
        }
    }

    /**
     * Verifica membresías vencidas y envía avisos previos (diario).
     *
     * Avisos: 30 días, 7 días y 1 día antes del vencimiento.
     * Al vencer → mark_expired() → el guard redirige a pagar.
     */
    public static function check_expired_memberships(): void
    {
        $users = get_users( [
            'role'       => 'subscriber',
            'fields'     => 'ids',
            'number'     => -1,
            'meta_query' => [
                [ 'key' => VX_User_Meta::PLAN_ESTADO,      'value' => 'activo' ],
                [ 'key' => VX_User_Meta::PLAN_VENCIMIENTO, 'value' => 0, 'compare' => '!=', 'type' => 'NUMERIC' ],
            ],
        ] );

        $now = time();

        foreach ( $users as $uid ) {
            // Por el parser y no con (int): si quedó guardada una fecha escrita,
            // un (int) la lee como el año y este cron da el plan por vencido.
            $vencimiento = VX_Membership::parse_expiry( get_user_meta( $uid, VX_User_Meta::PLAN_VENCIMIENTO, true ) );
            if ( $vencimiento <= 0 ) continue;

            // Ya venció → marcar
            if ( $vencimiento < $now ) {
                $membresia = VX_Membership::get( $uid );
                $membresia->mark_expired();
                // Limpiar tracking de avisos para el próximo ciclo
                delete_user_meta( $uid, VX_Membership_Meta::AVISOS_ENVIADOS );
                continue;
            }

            // Calcular días restantes y enviar avisos
            $days_left = (int) floor( ( $vencimiento - $now ) / DAY_IN_SECONDS );
            self::maybe_send_expiry_warning( $uid, $days_left, $vencimiento );
        }
    }

    /**
     * Envía aviso de vencimiento si corresponde y no fue enviado ya.
     *
     * @param int $user_id
     * @param int $days_left    Días hasta el vencimiento
     * @param int $vencimiento  Timestamp de vencimiento
     */
    private static function maybe_send_expiry_warning( int $user_id, int $days_left, int $vencimiento ): void
    {
        // Fix: iterar en orden ascendente para seleccionar el umbral más ajustado
        // Ej: days_left=5 → $found=7 (no 30), days_left=0 → $found=1
        $umbrales = [ 1, 7, 30 ];
        $found    = null;
        foreach ( $umbrales as $u ) {
            if ( $days_left <= $u ) { $found = $u; break; }
        }
        if ( null === $found ) return; // más de 30 días · no avisar aún

        // Leer avisos ya enviados
        $enviados = json_decode( (string) get_user_meta( $user_id, VX_Membership_Meta::AVISOS_ENVIADOS, true ) ?: '{}', true );
        $key      = $found . 'd';
        if ( ! empty( $enviados[ $key ] ) ) return; // ya enviado

        $user      = VX_User::get( $user_id );
        $membresia = VX_Membership::get( $user_id );
        if ( ! $user ) return;

        $es_fundador = $user->is_founder();
        $plan_label  = $es_fundador ? 'gratuito (precio preferencial disponible)' : ucfirst( $membresia->get_plan() );

        // Fix: solo marcar como enviado si el email salió correctamente
        $sent = VX_Mailer::send(
            $user->get_email(),
            'Tu plan en Vitrinexo vence en ' . $found . ' día' . ( 1 === $found ? '' : 's' ),
            'plan_aviso_vencimiento',
            [
                'nombre'         => $user->get_nombre(),
                'days_left'      => $found,
                'fecha_vencimiento' => date_i18n( 'j \d\e F \d\e Y', $vencimiento ),
                'plan_label'     => $plan_label,
                'es_fundador'    => $es_fundador,
                'url_renovar'    => home_url( '/configuracion/?tab=plan' ),
            ]
        );

        // Solo marcar como enviado si el email se envió correctamente
        if ( $sent ) {
            $enviados[ $key ] = true;
            update_user_meta( $user_id, VX_Membership_Meta::AVISOS_ENVIADOS, wp_json_encode( $enviados ) );
        }
    }
}

