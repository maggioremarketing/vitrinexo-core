<?php
/**
 * La pregunta del distintivo deja de explicar una regla interna.
 *
 * La respuesta terminaba diciendo «Siempre se llama "distintivo", nunca
 * "badge"». Es verdad y es la regla del proyecto, pero esa frase está escrita
 * para el equipo y no para quien lee las preguntas frecuentes: a un miembro no
 * le aporta saber cómo NO le decimos a una cosa. Es la misma corrección que ya
 * está anotada para los correos —no explicarle al cliente la logística
 * interna— aplicada al sitio.
 *
 * Se podría haber marcado la palabra con data-cita, que la comprobación
 * respeta para los valores que el manual cita en vez de usar. Pero esa salida
 * sirve cuando el texto tiene que decir la palabra prohibida para explicarla,
 * y acá no tiene que decirla: la frase que importa es cómo se llama, y esa se
 * queda. Tapar la medición es peor que arreglar el texto.
 *
 * Las comillas de la pregunta también se van: el distintivo se llama así, no
 * «se llama así» entre comillas.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'VX_MIG_FAQ_BADGE', 'vx_migracion_faq_badge' );

function vx_faq_badge_entrada() {
    return [
        'titulos' => [
            '¿Qué es el ‘distintivo’ de Miembro Pionero?',
            "¿Qué es el 'distintivo' de Miembro Pionero?",
        ],
        'cuerpo'  => 'Es un identificador visible en tu perfil que te reconoce como parte del grupo fundador de Vitrinexo. Los primeros 100 miembros aprobados lo reciben automáticamente. Siempre se llama \'distintivo\', nunca \'badge\'.',
        'titulo_nuevo' => '¿Qué es el distintivo de Miembro Pionero?',
        'cuerpo_nuevo' => 'Es un identificador visible en tu perfil que te reconoce como parte del grupo fundador de Vitrinexo. Los primeros cien miembros aprobados lo reciben automáticamente.',
    ];
}

add_action( 'init', function () {
    if ( get_option( VX_MIG_FAQ_BADGE ) ) return;
    if ( ! post_type_exists( 'vx_faq' ) ) return;

    $pedido   = vx_faq_badge_entrada();
    $respaldo = [];
    $intacta  = '';

    $encontrada = null;
    foreach ( $pedido['titulos'] as $titulo ) {
        $hallazgo = get_posts( [ 'post_type' => 'vx_faq', 'title' => $titulo, 'posts_per_page' => 1, 'post_status' => 'any' ] );
        if ( $hallazgo ) { $encontrada = $hallazgo[0]; break; }
    }

    if ( ! $encontrada ) {
        $intacta = 'no está la pregunta del distintivo';
    } elseif ( trim( wp_strip_all_tags( $encontrada->post_content ) ) !== $pedido['cuerpo'] ) {
        $intacta = $encontrada->post_title . ' (el cuerpo no es el que esperaba)';
    } else {
        $respaldo[ $encontrada->ID ] = [
            'post_title'   => $encontrada->post_title,
            'post_content' => $encontrada->post_content,
        ];
        wp_update_post( [
            'ID'           => $encontrada->ID,
            'post_title'   => $pedido['titulo_nuevo'],
            'post_content' => $pedido['cuerpo_nuevo'],
        ] );
    }

    update_option( VX_MIG_FAQ_BADGE, [
        'fecha'    => current_time( 'mysql' ),
        'tocadas'  => array_keys( $respaldo ),
        'intacta'  => $intacta,
        'respaldo' => $respaldo,
    ], false );
}, 23 );
