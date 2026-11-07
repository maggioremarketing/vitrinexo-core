<?php
/**
 * Las preguntas frecuentes dejan de prometer tres meses.
 *
 * Cuatro respuestas decían que el acceso es gratuito «durante 3 meses». Esa
 * era la regla y ya no lo es: el período gratuito termina cuando el programa
 * llega a 100 miembros, con aviso y plazo antes de cobrar, y los Pioneros se
 * quedan con el precio preferencial para siempre.
 *
 * El cambio no le quita nada a nadie, y eso importa porque son 44 personas
 * que ya se inscribieron leyendo la promesa anterior. Con 44 de 100, llegar
 * al cupo va a tomar más que tres meses, así que el período se alarga en vez
 * de acortarse. Lo único que desaparece es la fecha fija, y en su lugar queda
 * dicho lo que sí se va a cumplir: que se avisa antes de cobrar.
 *
 * Por eso el texto nuevo no promete una duración. Prometer «hasta que
 * lleguemos a 100» tampoco sirve, porque nadie sabe cuándo es, y una promesa
 * que no se puede fechar se cumple avisando, no calculando.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'VX_MIG_FAQ_PERIODO_GRATUITO', 'vx_migracion_faq_periodo_gratuito' );

function vx_faq_periodo_gratuito_entradas() {
    return [
        [
            'titulo' => '¿Qué es el programa Miembro Pionero?',
            'cuerpo' => 'Es el programa de lanzamiento de Vitrinexo. Los primeros 100 miembros aprobados reciben el distintivo de Miembro Pionero y acceden a la plataforma de forma completamente gratuita durante 3 meses.',
            'cuerpo_nuevo' => 'Es el programa de lanzamiento de Vitrinexo. Los primeros 100 miembros aprobados reciben el distintivo de Miembro Pionero y acceden a la plataforma de forma completamente gratuita mientras el programa esté abierto. Cuando lleguemos a 100 miembros te avisamos con tiempo antes de empezar a cobrar.',
        ],
        [
            'titulo' => '¿Cuánto cuesta ser Miembro Pionero?',
            'cuerpo' => 'Nada. El acceso es completamente gratuito durante los primeros 3 meses.',
            'cuerpo_nuevo' => 'Nada mientras el programa esté abierto. Cuando lleguemos a 100 miembros te avisamos con tiempo, y como Pionero te quedas con el precio preferencial para siempre.',
        ],
        [
            'titulo' => '¿Qué beneficios tiene el programa Miembro Pionero?',
            'cuerpo' => 'Acceso gratuito por 3 meses, distintivo exclusivo visible en el perfil, acceso anticipado a todas las funcionalidades de la plataforma y participación activa en la comunidad desde el primer día.',
            'cuerpo_nuevo' => 'Acceso gratuito mientras el programa esté abierto, el precio preferencial para siempre cuando empecemos a cobrar, distintivo exclusivo visible en el perfil, acceso anticipado a todas las funcionalidades y participación en la comunidad desde el primer día.',
        ],
        [
            'titulo' => '¿Tengo que renovar manualmente mi inscripción?',
            'cuerpo' => 'No durante el período gratuito. Antes del término de los 3 meses se te informará el proceso para continuar en la plataforma.',
            'cuerpo_nuevo' => 'No. Cuando lleguemos a 100 miembros te escribimos con el proceso para continuar, y tienes plazo para decidir antes de que haya cualquier cobro.',
        ],
    ];
}

add_action( 'init', function () {
    if ( get_option( VX_MIG_FAQ_PERIODO_GRATUITO ) ) return;
    if ( ! post_type_exists( 'vx_faq' ) ) return;

    $respaldo = [];
    $intactas = [];

    foreach ( vx_faq_periodo_gratuito_entradas() as $pedido ) {
        $hallazgo = get_posts( [
            'post_type'      => 'vx_faq',
            'title'          => $pedido['titulo'],
            'posts_per_page' => 1,
            'post_status'    => 'any',
        ] );

        if ( ! $hallazgo ) { $intactas[] = $pedido['titulo'] . ' (no está)'; continue; }

        $entrada = $hallazgo[0];
        if ( trim( wp_strip_all_tags( $entrada->post_content ) ) !== $pedido['cuerpo'] ) {
            $intactas[] = $pedido['titulo'] . ' (el cuerpo no es el que esperaba)';
            continue;
        }

        $respaldo[ $entrada->ID ] = [ 'post_content' => $entrada->post_content ];
        wp_update_post( [ 'ID' => $entrada->ID, 'post_content' => $pedido['cuerpo_nuevo'] ] );
    }

    update_option( VX_MIG_FAQ_PERIODO_GRATUITO, [
        'fecha'    => current_time( 'mysql' ),
        'tocadas'  => array_keys( $respaldo ),
        'intactas' => $intactas,
        'respaldo' => $respaldo,
    ], false );
}, 25 );
