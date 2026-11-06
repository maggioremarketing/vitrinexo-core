<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Columnas y meta boxes para vx_conexion en el admin.
 */
class VX_Admin_Connections
{
    public static function init(): void
    {
        add_filter( 'manage_vx_conexion_posts_columns',       [ self::class, 'add_columns' ] );
        add_action( 'manage_vx_conexion_posts_custom_column', [ self::class, 'render_column' ], 10, 2 );
        add_filter( 'manage_edit-vx_conexion_sortable_columns', [ self::class, 'sortable_columns' ] );

        add_action( 'add_meta_boxes', [ self::class, 'add_meta_boxes' ] );
    }

    public static function add_columns( array $columns ): array
    {
        unset( $columns['date'] );

        return array_merge( $columns, [
            'vx_emisor'      => 'Emisor',
            'vx_receptor'    => 'Receptor',
            'vx_estado'      => 'Estado',
            'vx_fecha_envio' => 'Fecha envío',
        ] );
    }

    public static function render_column( string $column, int $post_id ): void
    {
        switch ( $column ) {
            case 'vx_emisor':
                $id     = (int) get_post_meta( $post_id, VX_Connection_Meta::EMISOR_ID, true );
                $nombre = get_post_meta( $post_id, VX_Connection_Meta::EMISOR_NOMBRE, true );
                echo esc_html( $nombre ) . ' <small>(#' . $id . ')</small>';
                break;

            case 'vx_receptor':
                $id   = (int) get_post_meta( $post_id, VX_Connection_Meta::RECEPTOR_ID, true );
                $user = get_user_by( 'id', $id );
                if ( $user ) {
                    $nombre = get_user_meta( $id, VX_User_Meta::NOMBRE, true ) . ' ' . get_user_meta( $id, VX_User_Meta::APELLIDO, true );
                    echo esc_html( trim( $nombre ) ) . ' <small>(#' . $id . ')</small>';
                } else {
                    echo '#' . $id;
                }
                break;

            case 'vx_estado':
                $estado = get_post_meta( $post_id, VX_Connection_Meta::ESTADO, true );
                $colors = [
                    'pendiente'    => '#C6C167',
                    'aceptado'     => '#7DBF9E',
                    'rechazado'    => '#C58D78',
                    'sin_respuesta'=> '#5C5F6B',
                ];
                $color = $colors[ $estado ] ?? '#5C5F6B';
                echo '<span style="color:' . $color . ';font-weight:600">' . esc_html( $estado ) . '</span>';
                break;

            case 'vx_fecha_envio':
                $ts = (int) get_post_meta( $post_id, VX_Connection_Meta::FECHA_ENVIO, true );
                echo $ts ? esc_html( date_i18n( 'd/m/Y H:i', $ts ) ) : '-';
                break;
        }
    }

    public static function sortable_columns( array $columns ): array
    {
        $columns['vx_estado']      = 'vx_estado';
        $columns['vx_fecha_envio'] = 'vx_fecha_envio';
        return $columns;
    }

    public static function add_meta_boxes(): void
    {
        add_meta_box(
            'vx_conexion_details',
            'Detalles de la Conexión',
            [ self::class, 'render_meta_box' ],
            'vx_conexion',
            'normal',
            'high'
        );
    }

    public static function render_meta_box( WP_Post $post ): void
    {
        $meta_keys = [
            'Emisor ID'           => VX_Connection_Meta::EMISOR_ID,
            'Emisor nombre'       => VX_Connection_Meta::EMISOR_NOMBRE,
            'Emisor email'        => VX_Connection_Meta::EMISOR_EMAIL,
            'Receptor ID'         => VX_Connection_Meta::RECEPTOR_ID,
            'Receptor nombre'     => VX_Connection_Meta::RECEPTOR_NOMBRE,
            'Pitch'               => VX_Connection_Meta::PITCH,
            'Estado'              => VX_Connection_Meta::ESTADO,
            'Recordatorio enviado'=> VX_Connection_Meta::RECORDATORIO_ENVIADO,
        ];

        echo '<table class="form-table">';
        foreach ( $meta_keys as $label => $key ) {
            $value = get_post_meta( $post->ID, $key, true );
            if ( is_array( $value ) ) $value = implode( ', ', $value );
            echo '<tr><th>' . esc_html( $label ) . '</th><td>' . esc_html( (string) $value ) . '</td></tr>';
        }
        echo '</table>';
    }
}
