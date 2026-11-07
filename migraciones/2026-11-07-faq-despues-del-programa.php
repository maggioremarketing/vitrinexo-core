<?php
/**
 * La quinta respuesta que prometía tres meses.
 *
 * Las otras cuatro ya se corrigieron. Esta se escapó porque la promesa está
 * en la pregunta y no en la respuesta —«¿Qué pasa después de los 3 meses
 * gratuitos?»— y la búsqueda miraba el cuerpo. El despliegue la encontró
 * igual, midiendo producción, que es para lo que está.
 *
 * Aprovecha que el precio ya está decidido. «Condiciones preferenciales» era
 * lo único que se podía decir mientras no hubiera número: eran tres promesas
 * distintas —más barato para siempre, precio congelado, un año gratis— y el
 * lector no tenía forma de saber cuál. Ahora es una sola y se escribe: 19 en
 * vez de 29, mientras siga siendo miembro.
 *
 * El título también cambia, y por eso esta migración escribe los dos: una
 * pregunta que empieza nombrando un plazo que ya no existe no se arregla
 * cambiándole la respuesta.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'VX_MIG_FAQ_DESPUES_DEL_PROGRAMA', 'vx_migracion_faq_despues_del_programa' );

add_action( 'init', function () {
    if ( get_option( VX_MIG_FAQ_DESPUES_DEL_PROGRAMA ) ) return;
    if ( ! post_type_exists( 'vx_faq' ) ) return;

    $titulo = '¿Qué pasa después de los 3 meses gratuitos?';
    $cuerpo = 'Al término del período gratuito se comunicará el modelo de suscripción definitivo. Los Miembros Pioneros tendrán condiciones preferenciales respecto a los nuevos miembros.';

    $moneda = get_option( 'vx_moneda', 'USD' );
    $mensual      = (int) get_option( 'vx_precio_mensual', 29 );
    $preferencial = (int) get_option( 'vx_precio_preferencial', 19 );

    $titulo_nuevo = '¿Qué pasa cuando el programa cierre?';
    $cuerpo_nuevo = sprintf(
        'Cuando lleguemos a 100 miembros te avisamos y te damos plazo antes de cualquier cobro. Como Miembro Pionero pagas %d %s al mes en vez de %d, mientras sigas siendo miembro.',
        $preferencial, $moneda, $mensual
    );

    $respaldo = [];
    $intacta  = '';

    $hallazgo = get_posts( [ 'post_type' => 'vx_faq', 'title' => $titulo, 'posts_per_page' => 1, 'post_status' => 'any' ] );

    if ( ! $hallazgo ) {
        $intacta = 'no está la pregunta de los tres meses gratuitos';
    } elseif ( trim( wp_strip_all_tags( $hallazgo[0]->post_content ) ) !== $cuerpo ) {
        $intacta = $hallazgo[0]->post_title . ' (el cuerpo no es el que esperaba)';
    } else {
        $respaldo[ $hallazgo[0]->ID ] = [
            'post_title'   => $hallazgo[0]->post_title,
            'post_content' => $hallazgo[0]->post_content,
        ];
        wp_update_post( [
            'ID'           => $hallazgo[0]->ID,
            'post_title'   => $titulo_nuevo,
            'post_content' => $cuerpo_nuevo,
        ] );
    }

    update_option( VX_MIG_FAQ_DESPUES_DEL_PROGRAMA, [
        'fecha'    => current_time( 'mysql' ),
        'tocadas'  => array_keys( $respaldo ),
        'intacta'  => $intacta,
        'respaldo' => $respaldo,
    ], false );
}, 26 );
