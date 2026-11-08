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

/**
 * Cuántos Pioneros hay ahora mismo, sin pasar por el caché.
 *
 * vx_pioneros_aprobados() cachea cinco minutos porque la portada la pide en
 * cada visita. Para decidir si alguien entra al programa eso no sirve: entre
 * dos aprobaciones seguidas el número viejo diría que todavía hay cupo, y el
 * programa se pasaría de 100 sin que nadie lo note.
 */
function vx_pioneros_aprobados_fresco(): int {
    delete_transient( 'vx_pioneros_aprobados' );
    return vx_pioneros_aprobados();
}

/**
 * Si el programa todavía admite Pioneros.
 *
 * Esta es la única puerta. Había dos y solo una tenía llave: la aprobación
 * contaba los activos contra 100, y el final del onboarding repartía el
 * distintivo sin mirar nada, con lo que el miembro 130 salía Pionero igual.
 * Con el programa gratis para siempre eso dejó de ser cosmético: cada
 * distintivo de más es una cuenta que no paga nunca.
 *
 * Se pregunta por los Pioneros aprobados, no por los activos en general: son
 * los que el contador de la portada promete y los que el cupo limita.
 */
function vx_pioneros_programa_abierto(): bool {
    return vx_pioneros_aprobados_fresco() < vx_pioneros_cupo();
}

/**
 * Le da el distintivo a quien corresponda, y a nadie más.
 *
 * Devuelve true si la persona queda con el distintivo, lo acabe de recibir o
 * ya lo tuviera. Quien ya lo tiene no lo pierde aunque el programa esté
 * cerrado: el distintivo es permanente, y esa promesa es anterior al cupo.
 */
function vx_pioneros_dar_distintivo( int $user_id ): bool {
    if ( get_user_meta( $user_id, VX_User_Meta::ES_FUNDADOR, true ) ) return true;
    if ( ! vx_pioneros_programa_abierto() ) return false;

    update_user_meta( $user_id, VX_User_Meta::ES_FUNDADOR, 1 );
    delete_transient( 'vx_pioneros_aprobados' );
    return true;
}
