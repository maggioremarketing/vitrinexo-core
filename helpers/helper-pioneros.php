<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Cuántos Miembros Pioneros hay aprobados, de verdad.
 *
 * La propuesta de la portada trae un contador «40 / 100». Ese número es del
 * boceto, y publicar una cifra de comunidad que no se puede sostener es lo
 * único que el criterio de Tono prohíbe explícitamente. Así que se cuenta.
 *
 * Se cachea cinco minutos: la portada es la página más visitada y esto es una
 * consulta a meta de usuario, que no es barata.
 */
function vx_pioneros_aprobados(): int {
    $cache = get_transient( 'vx_pioneros_aprobados' );
    if ( false !== $cache ) return (int) $cache;

    $q = new WP_User_Query( [
        'role'        => 'subscriber',
        'number'      => 200,
        'fields'      => 'ID',
        'count_total' => true,
        'meta_query'  => [
            'relation' => 'AND',
            [ 'key' => VX_User_Meta::ES_FUNDADOR, 'value' => '1' ],
            [ 'key' => VX_User_Meta::ESTADO,      'value' => 'activo' ],
        ],
    ] );

    $n = (int) $q->get_total();
    set_transient( 'vx_pioneros_aprobados', $n, 5 * MINUTE_IN_SECONDS );
    return $n;
}

/** El cupo del programa. Vive acá y no escrito en la portada. */
function vx_pioneros_cupo(): int {
    return (int) apply_filters( 'vx_pioneros_cupo', 100 );
}
