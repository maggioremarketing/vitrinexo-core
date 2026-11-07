<?php
/**
 * Los Pioneros ya aprobados se quedan sin fecha de vencimiento.
 *
 * Hasta hoy la aprobación escribía tres meses desde ese día, y los miembros
 * aprobados tienen esa fecha guardada. La regla cambió: el período gratuito
 * termina cuando el programa llega a 100 miembros, con aviso y plazo. Una
 * fecha por miembro no puede expresar eso, y peor: esas fechas están vivas.
 * El cron de avisos las lee y manda «tu membresía está por vencer», y al
 * pasar la fecha el plan deja de estar vigente. O sea que sin esto, gente a
 * la que le dijimos que el acceso sigue se habría quedado afuera sola.
 *
 * Escribe 0, que en este meta significa sin vencimiento, y no borra el meta:
 * la columna del admin distingue «no hay nada guardado» de «no vence», y el
 * cron excluye el 0 explícitamente.
 *
 * Toca solo a los Pioneros activos. Un miembro que no es Pionero no entra en
 * esta decisión, y uno inactivo tampoco. Guarda el valor anterior de cada
 * uno: si un administrador le había puesto una fecha a mano, acá se pierde,
 * y el respaldo es la única forma de devolverla.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'VX_MIG_PIONEROS_SIN_VENCIMIENTO', 'vx_migracion_pioneros_sin_vencimiento' );

add_action( 'init', function () {
    if ( get_option( VX_MIG_PIONEROS_SIN_VENCIMIENTO ) ) return;
    if ( ! class_exists( 'VX_User_Meta' ) ) return;

    $pioneros = get_users( [
        'fields'     => 'ID',
        'number'     => -1,
        'meta_query' => [
            'relation' => 'AND',
            [ 'key' => VX_User_Meta::ES_FUNDADOR, 'value' => '1' ],
            [ 'key' => VX_User_Meta::ESTADO,      'value' => 'activo' ],
        ],
    ] );

    $respaldo = [];

    foreach ( $pioneros as $user_id ) {
        $guardado = get_user_meta( $user_id, VX_User_Meta::PLAN_VENCIMIENTO, true );
        if ( (int) $guardado === 0 ) continue;   // ya está sin vencimiento

        $respaldo[ $user_id ] = $guardado;
        update_user_meta( $user_id, VX_User_Meta::PLAN_VENCIMIENTO, 0 );
    }

    update_option( VX_MIG_PIONEROS_SIN_VENCIMIENTO, [
        'fecha'    => current_time( 'mysql' ),
        'cuantos'  => count( $respaldo ),
        'respaldo' => $respaldo,
    ], false );
}, 24 );
