<?php
/**
 * Los primeros 100 son gratis para siempre, y el sitio lo tiene que decir.
 *
 * La regla cambió el 8 de noviembre de 2026. Antes el período gratuito
 * terminaba al llegar a 100 miembros y ahí empezaba a cobrarse un precio
 * preferencial de 19. Ahora no termina: ser Miembro Pionero significa no pagar
 * nunca, y el precio preferencial desaparece porque no tiene a quién
 * aplicarse.
 *
 * Nadie queda peor que como estaba: a los 44 Pioneros se les prometió «gratis
 * mientras el programa esté abierto y después precio preferencial», y lo que
 * reciben es más que eso. No hay que explicarle nada incómodo a nadie.
 *
 * Seis respuestas prometían un cobro que ya no va a ocurrir. Tres más hablan
 * del programa y siguen siendo verdad tal cual, así que no se tocan: cuántos
 * Pioneros habrá, qué es el distintivo y que no se pierde.
 *
 * Cada entrada se reconoce por título y cuerpo exactos. La que no coincida se
 * anota en «intactas» y se queda como está: esto corre una sola vez contra una
 * base de datos que no se puede leer desde el repositorio.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'VX_MIG_FAQ_GRATIS_SIEMPRE', 'vx_migracion_faq_gratis_siempre' );

function vx_faq_gratis_siempre_reglas(): array {
    return [
        [
            'titulo' => '¿Qué es el programa Miembro Pionero?',
            'cuerpo' => 'Es el programa de lanzamiento de Vitrinexo. Los primeros 100 miembros aprobados reciben el distintivo de Miembro Pionero y acceden a la plataforma de forma completamente gratuita mientras el programa esté abierto. Cuando lleguemos a 100 miembros te avisamos con tiempo antes de empezar a cobrar.',
            'nuevo'  => 'Es el programa de lanzamiento de Vitrinexo. Los primeros 100 miembros aprobados reciben el distintivo de Miembro Pionero y acceso gratuito a la plataforma, para siempre. Ser Pionero significa que no pagas nunca.',
        ],
        [
            'titulo' => '¿Cuánto cuesta ser Miembro Pionero?',
            'cuerpo' => 'Nada mientras el programa esté abierto. Cuando lleguemos a 100 miembros te avisamos con tiempo, y como Pionero te quedas con el precio preferencial para siempre.',
            'nuevo'  => 'Nada, y nunca. El acceso de los Miembros Pioneros es gratuito de forma permanente, sin fecha de término y sin cobro posterior.',
        ],
        [
            'titulo' => '¿Qué beneficios tiene el programa Miembro Pionero?',
            'cuerpo' => 'Acceso gratuito mientras el programa esté abierto, el precio preferencial para siempre cuando empecemos a cobrar, distintivo exclusivo visible en el perfil, acceso anticipado a todas las funcionalidades y participación en la comunidad desde el primer día.',
            'nuevo'  => 'Acceso gratuito permanente, sin fecha de término ni cobro posterior, distintivo exclusivo visible en el perfil, acceso anticipado a todas las funcionalidades y participación en la comunidad desde el primer día.',
        ],
        [
            'titulo' => '¿Qué pasa cuando el programa cierre?',
            'cuerpo' => 'Cuando lleguemos a 100 miembros te avisamos y te damos plazo antes de cualquier cobro. Como Miembro Pionero pagas 19 USD al mes en vez de 29, mientras sigas siendo miembro.',
            'nuevo'  => 'El programa cierra para quien llegue después: al completarse los 100, el distintivo deja de entregarse. Para ti no cambia nada. Tu acceso sigue siendo gratuito, igual que el primer día.',
        ],
        [
            'titulo' => '¿Tengo que renovar manualmente mi inscripción?',
            'cuerpo' => 'No. Cuando lleguemos a 100 miembros te escribimos con el proceso para continuar, y tienes plazo para decidir antes de que haya cualquier cobro.',
            'nuevo'  => 'No. Tu acceso como Miembro Pionero no vence, así que no hay nada que renovar.',
        ],
        [
            'titulo' => '¿Las comunidades tienen costo adicional?',
            'cuerpo' => 'Durante el período de Miembro Pionero, el acceso a las comunidades está incluido sin costo adicional.',
            'nuevo'  => 'No. El acceso a las comunidades está incluido para los Miembros Pioneros, sin costo adicional y sin fecha de término.',
        ],
    ];
}

add_action( 'init', function () {
    if ( get_option( VX_MIG_FAQ_GRATIS_SIEMPRE ) ) return;
    if ( ! post_type_exists( 'vx_faq' ) ) return;   // sin el CPT no se mira nada, y no se marca como hecha

    $respaldo = [];
    $intactas = [];

    foreach ( vx_faq_gratis_siempre_reglas() as $regla ) {
        $encontradas = get_posts( [
            'post_type'      => 'vx_faq',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'title'          => $regla['titulo'],
        ] );

        if ( ! $encontradas ) {
            $intactas[ $regla['titulo'] ] = 'no existe esa pregunta';
            continue;
        }

        foreach ( $encontradas as $p ) {
            $actual = trim( wp_strip_all_tags( $p->post_content ) );
            if ( $actual !== $regla['cuerpo'] ) {
                $intactas[ $regla['titulo'] ] = 'dice otra cosa: «' . mb_substr( $actual, 0, 80 ) . '…»';
                continue;
            }

            $respaldo[ $p->ID ] = [ 'titulo' => $p->post_title, 'cuerpo' => $p->post_content ];
            wp_update_post( [ 'ID' => $p->ID, 'post_content' => $regla['nuevo'] ] );
        }
    }

    update_option( VX_MIG_FAQ_GRATIS_SIEMPRE, [
        'fecha'    => current_time( 'mysql' ),
        'cuantas'  => count( $respaldo ),
        'respaldo' => $respaldo,
        'intactas' => $intactas,
    ], false );
}, 28 );
