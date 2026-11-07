<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Modelo de notificación del CPT vx_notification.
 */
class VX_Notification
{
    /**
     * Crea una nueva notificación.
     *
     * @param int    $user_id   Destinatario.
     * @param string $tipo      Tipo de notificación.
     * @param string $link      URL destino al hacer clic.
     * @param int    $actor_id  Usuario que generó la notificación (0 = sistema).
     * @param array  $data      Datos adicionales según el tipo.
     * @return int|false  Post ID de la notificación o false en error.
     */
    public static function create(
        int $user_id,
        string $tipo,
        string $link = '',
        int $actor_id = 0,
        array $data = []
    ): int|false {
        $user = VX_User::get( $user_id );
        if ( ! $user ) {
            return false;
        }

        $tipos_validos = [
            'conexion_nueva', 'conexion_aceptada', 'match_nuevo',
            'dinner_disponible', 'dinner_asignado', 'dinner_invitacion',
            'visita_perfil', 'favorito', 'comentario_pub', 'perfil_propuesta',
        ];

        if ( ! in_array( $tipo, $tipos_validos, true ) ) {
            return false;
        }

        $labels = [
            'conexion_nueva'    => 'Solicitud de conexión recibida',
            'conexion_aceptada' => 'Conexión aceptada',
            'match_nuevo'       => 'Nuevo nexo encontrado',
            'dinner_disponible' => 'Vitrinexo 4Dinner disponible cerca de ti',
            'dinner_asignado'   => 'Estás confirmado en un Vitrinexo 4Dinner',
            'dinner_invitacion' => 'Invitación a un Vitrinexo 4Dinner',
            'visita_perfil'     => 'Alguien visitó tu perfil',
            'favorito'          => 'Alguien te guardó en favoritos',
            'comentario_pub'    => 'Alguien comentó en tu publicación',
            'perfil_propuesta'  => 'Un admin propone actualizar tu perfil',
        ];

        $post_id = wp_insert_post( [
            'post_type'   => 'vx_notification',
            'post_title'  => $labels[ $tipo ] ?? $tipo,
            'post_status' => 'publish',
        ] );

        if ( is_wp_error( $post_id ) ) {
            error_log( '[VX_Notification::create] ' . $post_id->get_error_message() );
            return false;
        }

        update_post_meta( $post_id, 'vx_notif_user_id', $user_id );
        update_post_meta( $post_id, 'vx_notif_tipo',    $tipo );
        update_post_meta( $post_id, 'vx_notif_leida',   '0' ); // Fix: '0' string, no false, para que mark_all_read() lo encuentre
        update_post_meta( $post_id, 'vx_notif_fecha',   time() );
        update_post_meta( $post_id, 'vx_notif_link',    $link );
        update_post_meta( $post_id, 'vx_notif_actor_id', $actor_id );
        update_post_meta( $post_id, 'vx_notif_data',    wp_json_encode( $data ) );

        return $post_id;
    }

    /**
     * Devuelve notificaciones de un usuario (más recientes primero).
     *
     * @param int $user_id
     * @param int $limit   0 = sin límite
     * @return array[]  Cada elemento es un array con los datos de la notificación.
     */
    /**
     * @param int $page     Página (1-indexed)
     * @param int $per_page Notificaciones por página
     * @return array  ['items' => array[], 'pagination' => array]
     */
    public static function get_for_user( int $user_id, int $page = 1, int $per_page = 20 ): array
    {
        $all_posts = get_posts( [
            'post_type'      => 'vx_notification',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'ids',
            'meta_query'     => [
                [ 'key' => 'vx_notif_user_id', 'value' => $user_id ],
            ],
        ] );

        $total     = count( $all_posts );
        $offset    = ( max( 1, $page ) - 1 ) * $per_page;
        $page_ids  = array_slice( $all_posts, $offset, $per_page );
        $page_posts = array_filter( array_map( 'get_post', $page_ids ) );

        return [
            'items'      => array_map( [ self::class, 'format_post' ], array_values( $page_posts ) ),
            'pagination' => VX_Pagination::build( $total, $per_page, $page ),
        ];
    }

    /**
     * Marca una notificación como leída.
     *
     * @param int $notif_id
     */
    public static function mark_read( int $notif_id ): void
    {
        update_post_meta( $notif_id, 'vx_notif_leida', true );
    }

    /**
     * Marca todas las notificaciones de un usuario como leídas.
     *
     * @param int $user_id
     */
    public static function mark_all_read( int $user_id ): void
    {
        $posts = get_posts( [
            'post_type'      => 'vx_notification',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                [ 'key' => 'vx_notif_user_id', 'value' => $user_id ],
                [ 'key' => 'vx_notif_leida',   'value' => '0' ],
            ],
        ] );

        foreach ( $posts as $post_id ) {
            update_post_meta( $post_id, 'vx_notif_leida', true );
        }
    }

    /**
     * Devuelve el número de notificaciones no leídas de un usuario.
     *
     * @param int $user_id
     * @return int
     */
    public static function count_unread( int $user_id ): int
    {
        $query = new WP_Query( [
            'post_type'      => 'vx_notification',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                [ 'key' => 'vx_notif_user_id', 'value' => $user_id ],
                [
                    'relation' => 'OR',
                    [ 'key' => 'vx_notif_leida', 'value' => '0' ],
                    [ 'key' => 'vx_notif_leida', 'value' => '', 'compare' => 'NOT EXISTS' ],
                ],
            ],
        ] );

        return $query->found_posts;
    }

    /**
     * Formatea un WP_Post de notificación como array para la respuesta REST.
     *
     * @param WP_Post $post
     * @return array
     */
    private static function format_post( WP_Post $post ): array
    {
        $data_json = get_post_meta( $post->ID, 'vx_notif_data', true );
        $data      = $data_json ? json_decode( $data_json, true ) : [];

        $actor_id  = (int) get_post_meta( $post->ID, 'vx_notif_actor_id', true );
        $actor     = $actor_id ? VX_User::get( $actor_id ) : null;

        return [
            'id'         => $post->ID,
            'tipo'       => get_post_meta( $post->ID, 'vx_notif_tipo',    true ),
            'leida'      => (bool) get_post_meta( $post->ID, 'vx_notif_leida', true ),
            'fecha'      => (int) get_post_meta( $post->ID, 'vx_notif_fecha', true ),
            'fecha_iso'  => gmdate( 'c', (int) get_post_meta( $post->ID, 'vx_notif_fecha', true ) ),
            'link'       => get_post_meta( $post->ID, 'vx_notif_link', true ),
            'titulo'     => $post->post_title,
            'actor'      => $actor ? [
                'nombre'     => $actor->get_nombre_completo(),
                'foto_url'   => $actor->get_foto_url( 'thumbnail' ),
                'perfil_url' => home_url( '/perfil/' . $actor->get_slug() . '/' ),
            ] : null,
            'data'       => $data,
        ];
    }
}
