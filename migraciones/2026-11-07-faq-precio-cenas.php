<?php
/**
 * Las cenas no tienen costo, y las preguntas frecuentes ahora lo dicen.
 *
 * La respuesta decía que el detalle de precios se comunicaría más adelante,
 * mientras la portada decía que sentarse a la mesa no cuesta nada. Las dos
 * estaban vivas en el mismo sitio, y quien leyera las dos se iba sin saber.
 *
 * La migración anterior le sacó el nombre desnudo y dejó la frase en pie a
 * propósito: un precio es una decisión de negocio y no se decide desde una
 * migración. Ya está decidido, así que ahora sí se escribe, y se escribe con
 * las palabras de la portada para que las dos piezas digan lo mismo y no dos
 * versiones parecidas de lo mismo.
 *
 * Reconoce las dos formas que pudo haber quedado guardadas —con el nombre ya
 * corregido o todavía desnudo, según si la migración anterior llegó a correr—
 * y no toca nada si encuentra cualquier otra cosa, que es como se respeta una
 * edición hecha a mano mientras tanto.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'VX_MIG_FAQ_PRECIO_CENAS', 'vx_migracion_faq_precio_cenas' );

/** Lo que había antes, en sus dos formas posibles. */
function vx_faq_precio_cenas_originales() {
    return [
        'El detalle de precios de las cenas de Vitrinexo 4Dinner se comunicará oportunamente a los miembros de la plataforma.',
        'El detalle de precios de las cenas 4Dinner se comunicará oportunamente a los miembros de la plataforma.',
    ];
}

/** Lo que dice ahora. Son las palabras de la portada, no una variante. */
function vx_faq_precio_cenas_respuesta() {
    return 'Sentarse a la mesa no cuesta nada. Cada uno paga su consumo.';
}

add_action( 'init', function () {
    if ( get_option( VX_MIG_FAQ_PRECIO_CENAS ) ) return;
    if ( ! post_type_exists( 'vx_faq' ) ) return;

    $originales = vx_faq_precio_cenas_originales();
    $respaldo   = [];
    $intactas   = [];

    foreach ( get_posts( [ 'post_type' => 'vx_faq', 'numberposts' => -1, 'post_status' => 'any' ] ) as $entrada ) {
        if ( false === strpos( $entrada->post_title, 'tienen costo' ) ) continue;

        $actual = trim( wp_strip_all_tags( $entrada->post_content ) );

        // Cualquier otra cosa es una edición de alguien, y no se pisa.
        if ( ! in_array( $actual, $originales, true ) ) {
            if ( $actual !== vx_faq_precio_cenas_respuesta() ) $intactas[] = $entrada->post_title;
            continue;
        }

        $respaldo[ $entrada->ID ] = [ 'post_content' => $entrada->post_content ];
        wp_update_post( [ 'ID' => $entrada->ID, 'post_content' => vx_faq_precio_cenas_respuesta() ] );
    }

    update_option( VX_MIG_FAQ_PRECIO_CENAS, [
        'fecha'    => current_time( 'mysql' ),
        'tocadas'  => array_keys( $respaldo ),
        'intactas' => $intactas,
        'respaldo' => $respaldo,
    ], false );
}, 21 );
