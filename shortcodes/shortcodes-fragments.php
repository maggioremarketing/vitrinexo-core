<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// [vx_empty_state tipo="favoritos"] — estado vacío reutilizable
add_shortcode( 'vx_empty_state', function ( $atts ): string {
    $atts = shortcode_atts( [ 'tipo' => 'generico' ], $atts );
    return vx_render_empty_state( $atts['tipo'] );
} );

/**
 * Renderiza un estado vacío según tipo.
 * Usado tanto por el shortcode como por get_template_part().
 */
function vx_render_empty_state( string $tipo ): string
{
    $configs = [
        'favoritos' => [
            'icon'    => 'ti-heart',
            'title'   => 'Sin favoritos todavía',
            'desc'    => 'Guarda miembros como favoritos desde el directorio o sus perfiles.',
            'cta'     => [ 'Ir al directorio', '/directorio/' ],
        ],
        'conexiones' => [
            'icon'    => 'ti-network',
            'title'   => 'Sin conexiones pendientes',
            'desc'    => 'Cuando envíes o recibas solicitudes de conexión, aparecerán aquí.',
            'cta'     => [ 'Ver directorio', '/directorio/' ],
        ],
        'conexiones-concretadas' => [
            'icon'    => 'ti-users',
            'title'   => 'Aún no tienes conexiones confirmadas',
            'desc'    => 'Las conexiones aceptadas aparecerán aquí con los datos de contacto de cada miembro.',
            'cta'     => [ 'Buscar miembros', '/directorio/' ],
        ],
        'matches' => [
            'icon'    => 'ti-sparkles',
            'title'   => 'Sin nexos por ahora',
            'desc'    => 'Completa lo que ofreces y lo que buscas para que Vitrinexo encuentre tus nexos.',
            'cta'     => [ 'Editar mi perfil', '/editar-perfil/' ],
        ],
        'notificaciones' => [
            'icon'    => 'ti-bell',
            'title'   => 'Sin notificaciones',
            'desc'    => 'Aquí verás las actividades recientes: conexiones, visitas y nexos.',
            'cta'     => null,
        ],
        'directorio' => [
            'icon'    => 'ti-search',
            'title'   => 'Sin resultados',
            'desc'    => 'Prueba ajustando los filtros de búsqueda.',
            'cta'     => null,
        ],
        'dinners' => [
            'icon'    => 'ti-restaurant',
            'title'   => 'No hay eventos próximos',
            'desc'    => 'Cuando se creen nuevas cenas de Vitrinexo 4Dinner recibirás una notificación.',
            'cta'     => null,
        ],
        'comunidad' => [
            'icon'    => 'ti-users',
            'title'   => 'Sin miembros activos',
            'desc'    => 'Esta comunidad aún no tiene miembros. ¡Sé el primero!',
            'cta'     => null,
        ],
        'generico' => [
            'icon'    => 'ti-mood-empty',
            'title'   => 'No hay contenido',
            'desc'    => '',
            'cta'     => null,
        ],
    ];

    $config = $configs[ $tipo ] ?? $configs['generico'];

    ob_start();
    ?>
    <div class="vx-empty-state text-center py-5">
        <div class="vx-empty-state__icon mb-3">
            <i class="ti <?php echo esc_attr( $config['icon'] ); ?>"></i>
        </div>
        <h3 class="vx-empty-state__title"><?php echo esc_html( $config['title'] ); ?></h3>
        <?php if ( $config['desc'] ) : ?>
            <p class="vx-empty-state__desc text-muted"><?php echo esc_html( $config['desc'] ); ?></p>
        <?php endif; ?>
        <?php if ( $config['cta'] ) : ?>
            <a href="<?php echo esc_url( home_url( $config['cta'][1] ) ); ?>" class="btn-vx btn-vx--ghost mt-3">
                <?php echo esc_html( $config['cta'][0] ); ?>
            </a>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
