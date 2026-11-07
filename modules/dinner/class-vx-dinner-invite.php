<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Gestión de invitaciones e intereses para eventos 4Dinner.
 *
 * Cada registro es un CPT vx_dinner_invite con:
 *   - vx_invite_dinner_id : int
 *   - vx_invite_user_id   : int
 *   - vx_invite_tipo      : 'interes' | 'invitacion'
 *   - vx_invite_estado    : 'pendiente' | 'aceptado' | 'rechazado'
 *   - vx_invite_token     : string  (one-time token para emails)
 *   - vx_invite_mensaje   : string  (mensaje opcional del usuario)
 */
class VX_Dinner_Invite
{
    // Meta keys
    const DINNER_ID = 'vx_invite_dinner_id';
    const USER_ID   = 'vx_invite_user_id';
    const TIPO      = 'vx_invite_tipo';
    const ESTADO    = 'vx_invite_estado';
    const TOKEN     = 'vx_invite_token';
    const MENSAJE   = 'vx_invite_mensaje';

    // ── Registro del CPT ─────────────────────────────────────────────────────

    public static function register_cpt(): void
    {
        register_post_type( 'vx_dinner_invite', [
            'labels'             => [ 'name' => 'Invitaciones a Vitrinexo 4Dinner', 'singular_name' => 'Invitación a Vitrinexo 4Dinner' ],
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => false,
            'show_in_rest'       => false,
            'supports'           => [ 'title' ],
            'rewrite'            => false,
        ] );
    }

    // ── Factory ──────────────────────────────────────────────────────────────

    public static function get( int $post_id ): ?array
    {
        $post = get_post( $post_id );
        if ( ! $post || 'vx_dinner_invite' !== $post->post_type ) return null;
        return self::format( $post );
    }

    private static function format( WP_Post $post ): array
    {
        return [
            'id'        => $post->ID,
            'dinner_id' => (int) get_post_meta( $post->ID, self::DINNER_ID, true ),
            'user_id'   => (int) get_post_meta( $post->ID, self::USER_ID,   true ),
            'tipo'      => (string) get_post_meta( $post->ID, self::TIPO,   true ),
            'estado'    => (string) get_post_meta( $post->ID, self::ESTADO, true ),
            'token'     => (string) get_post_meta( $post->ID, self::TOKEN,  true ),
            'mensaje'   => (string) get_post_meta( $post->ID, self::MENSAJE,true ),
            'fecha'     => (int) strtotime( $post->post_date ),
        ];
    }

    // ── Consultas ────────────────────────────────────────────────────────────

    /**
     * Devuelve todos los registros de un dinner.
     *
     * @param int    $dinner_id
     * @param string $estado  '' = todos
     * @return array[]
     */
    public static function get_for_dinner( int $dinner_id, string $estado = '' ): array
    {
        $meta_query = [ [ 'key' => self::DINNER_ID, 'value' => $dinner_id ] ];
        if ( $estado ) {
            $meta_query[] = [ 'key' => self::ESTADO, 'value' => $estado ];
        }

        $posts = get_posts( [
            'post_type'      => 'vx_dinner_invite',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => $meta_query,
        ] );

        return array_map( [ self::class, 'format' ], $posts );
    }

    /**
     * Devuelve el registro pendiente de un usuario para un dinner, si existe.
     */
    public static function get_pending( int $dinner_id, int $user_id ): ?array
    {
        $posts = get_posts( [
            'post_type'      => 'vx_dinner_invite',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_query'     => [
                [ 'key' => self::DINNER_ID, 'value' => $dinner_id ],
                [ 'key' => self::USER_ID,   'value' => $user_id ],
                [ 'key' => self::ESTADO,    'value' => 'pendiente' ],
            ],
        ] );

        return ! empty( $posts ) ? self::format( $posts[0] ) : null;
    }

    /**
     * Busca un registro por token.
     */
    public static function get_by_token( string $token ): ?array
    {
        $posts = get_posts( [
            'post_type'      => 'vx_dinner_invite',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_query'     => [ [ 'key' => self::TOKEN, 'value' => $token ] ],
        ] );

        return ! empty( $posts ) ? self::format( $posts[0] ) : null;
    }

    // ── Creación ─────────────────────────────────────────────────────────────

    /**
     * Registra interés de un usuario en un dinner.
     * Devuelve el ID del post creado, o null si ya existe uno pendiente.
     *
     * @param int    $dinner_id
     * @param int    $user_id
     * @param string $mensaje   Texto opcional del usuario
     * @return int|null
     */
    public static function create_interest( int $dinner_id, int $user_id, string $mensaje = '' ): ?int
    {
        // No duplicar si ya hay un registro pendiente
        if ( self::get_pending( $dinner_id, $user_id ) ) return null;

        $dinner = VX_Dinner::get( $dinner_id );
        $user   = VX_User::get( $user_id );
        if ( ! $dinner || ! $user ) return null;

        $post_id = wp_insert_post( [
            'post_type'   => 'vx_dinner_invite',
            'post_status' => 'publish',
            'post_title'  => 'Interés: ' . $user->get_nombre_completo() . ' · Vitrinexo 4Dinner ' . $dinner->get_ciudad(),
        ] );

        if ( is_wp_error( $post_id ) ) return null;

        update_post_meta( $post_id, self::DINNER_ID, $dinner_id );
        update_post_meta( $post_id, self::USER_ID,   $user_id );
        update_post_meta( $post_id, self::TIPO,      'interes' );
        update_post_meta( $post_id, self::ESTADO,    'pendiente' );
        update_post_meta( $post_id, self::TOKEN,     VX_Token_Helper::generate() );
        update_post_meta( $post_id, self::MENSAJE,   sanitize_textarea_field( $mensaje ) );

        return $post_id;
    }

    /**
     * El admin invita a un usuario a un dinner.
     * Envía email con token de aceptar/rechazar + notificación interna.
     *
     * @param int $dinner_id
     * @param int $user_id
     * @return int|WP_Error
     */
    public static function create_invitation( int $dinner_id, int $user_id ): int|WP_Error
    {
        $dinner = VX_Dinner::get( $dinner_id );
        $user   = VX_User::get( $user_id );

        if ( ! $dinner ) return new WP_Error( 'dinner_no_encontrado', 'Evento no encontrado.' );
        if ( ! $user )   return new WP_Error( 'usuario_no_encontrado', 'Usuario no encontrado.' );

        if ( ! in_array( $dinner->get_estado(), [ 'abierto', 'confirmado' ], true ) ) {
            return new WP_Error( 'dinner_no_disponible', 'El evento no está abierto para invitaciones.' );
        }

        if ( ! $dinner->has_space() ) {
            return new WP_Error( 'mesa_completa', 'La mesa ya tiene 4 personas asignadas.' );
        }

        if ( $dinner->is_user_assigned( $user_id ) ) {
            return new WP_Error( 'ya_asignado', 'El usuario ya está asignado.' );
        }

        // Evitar duplicar invitaciones pendientes
        if ( self::get_pending( $dinner_id, $user_id ) ) {
            return new WP_Error( 'ya_invitado', 'Ya existe una invitación pendiente para este usuario.' );
        }

        $token = VX_Token_Helper::generate();

        $post_id = wp_insert_post( [
            'post_type'   => 'vx_dinner_invite',
            'post_status' => 'publish',
            'post_title'  => 'Invitación: ' . $user->get_nombre_completo() . ' · Vitrinexo 4Dinner ' . $dinner->get_ciudad(),
        ] );

        if ( is_wp_error( $post_id ) ) return $post_id;

        update_post_meta( $post_id, self::DINNER_ID, $dinner_id );
        update_post_meta( $post_id, self::USER_ID,   $user_id );
        update_post_meta( $post_id, self::TIPO,      'invitacion' );
        update_post_meta( $post_id, self::ESTADO,    'pendiente' );
        update_post_meta( $post_id, self::TOKEN,     $token );

        // Email con links de aceptar / rechazar
        $ep_aceptar  = rest_url( VX_REST_NAMESPACE . '/dinners/invites/' . $token . '/aceptar' );
        $ep_rechazar = rest_url( VX_REST_NAMESPACE . '/dinners/invites/' . $token . '/rechazar' );

        VX_Mailer::send(
            $user->get_email(),
            '¡Te invitamos a un Vitrinexo 4Dinner en ' . $dinner->get_ciudad() . '!',
            'dinner_invitacion',
            [
                'usuario_nombre' => $user->get_nombre(),
                'dinner'         => [
                    'ciudad'      => $dinner->get_ciudad(),
                    'pais'        => $dinner->get_pais(),
                    'fecha'       => $dinner->get_fecha(),
                    'restaurante' => $dinner->get_restaurante(),
                    'direccion'   => $dinner->get_direccion(),
                ],
                'url_aceptar'  => $ep_aceptar,
                'url_rechazar' => $ep_rechazar,
            ]
        );

        // Notificación interna
        VX_Notification::create(
            $user_id,
            'dinner_invitacion',
            home_url( '/4dinner/' ),
            0,
            [ 'dinner_id' => $dinner_id, 'ciudad' => $dinner->get_ciudad() ]
        );

        return $post_id;
    }

    // ── Cambios de estado ────────────────────────────────────────────────────

    public static function accept( int $invite_id ): bool
    {
        $inv = self::get( $invite_id );
        if ( ! $inv || 'pendiente' !== $inv['estado'] ) return false;

        // Fix: primero verificar que assign() funciona, LUEGO marcar como aceptado
        // Evita dejar el invite en 'aceptado' si la mesa ya estaba llena (condición de carrera)
        $result = VX_Dinner_Assignment::assign( $inv['dinner_id'], $inv['user_id'] );
        if ( is_wp_error( $result ) ) return false;

        update_post_meta( $invite_id, self::ESTADO, 'aceptado' );
        delete_post_meta( $invite_id, self::TOKEN );

        return true;
    }

    public static function reject( int $invite_id ): void
    {
        update_post_meta( $invite_id, self::ESTADO, 'rechazado' );
        delete_post_meta( $invite_id, self::TOKEN );
    }

    public static function accept_by_token( string $token ): bool|WP_Error
    {
        $inv = self::get_by_token( $token );
        if ( ! $inv ) return new WP_Error( 'token_invalido', 'El enlace no es válido o ya fue usado.' );
        if ( 'pendiente' !== $inv['estado'] ) return new WP_Error( 'ya_procesado', 'Esta invitación ya fue procesada.' );

        $ok = self::accept( $inv['id'] );
        return $ok ? true : new WP_Error( 'error_asignacion', 'No se pudo asignar a la mesa.' );
    }

    public static function reject_by_token( string $token ): bool|WP_Error
    {
        $inv = self::get_by_token( $token );
        if ( ! $inv ) return new WP_Error( 'token_invalido', 'El enlace no es válido o ya fue usado.' );
        if ( 'pendiente' !== $inv['estado'] ) return new WP_Error( 'ya_procesado', 'Esta invitación ya fue procesada.' );

        self::reject( $inv['id'] );
        return true;
    }
}
