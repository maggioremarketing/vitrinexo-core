<?php
/**
 * Las respuestas de las cenas, escritas en la voz del manual.
 *
 * Son cuatro cosas, siempre las mismas y siempre en estas cinco entradas:
 *
 *   · Frases donde nadie hace nada. «La inscripción se gestiona» no tiene
 *     sujeto, y además pone un sustantivo a hacer el trabajo de un verbo.
 *     Quien se inscribe es el lector: «Te inscribes».
 *   · El sujeto escondido al final. «La composición de las mesas la define el
 *     equipo de Vitrinexo» tarda nueve palabras en decir quién compone.
 *   · Relleno. «a través de la plataforma» es «por la plataforma»; «de los
 *     participantes» no agrega nada; «disponibles» tampoco.
 *   · Números en cifra dentro de la prosa, cuando la portada escribe «Cuatro
 *     personas, una mesa».
 *
 * Esto no arregla ninguna regla rota: el sitio pasaba en verde con el texto
 * anterior y seguiría pasando. Es redacción, y por eso va aparte de las dos
 * migraciones que sí corrigen una regla —la marca madre y el precio—, para
 * que se pueda deshacer esta sin tocar aquellas.
 *
 * Reconoce el título con el nombre ya corregido y con el nombre desnudo,
 * según si la migración de la marca madre alcanzó a correr. Exige además que
 * el cuerpo sea exactamente el que había: cualquier otra cosa es una edición
 * de alguien, y esa no se pisa ni se discute, se anota y se deja.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'VX_MIG_FAQ_VOZ', 'vx_migracion_faq_voz' );

/**
 * Cada entrada: los títulos con que puede estar guardada, el cuerpo que tiene
 * que tener para que la toquemos, y el título y el cuerpo nuevos.
 */
function vx_faq_voz_entradas() {
    return [
        [
            'titulos' => [
                '¿Por qué las cenas de Vitrinexo 4Dinner son solo de 4 personas?',
                '¿Por qué las cenas 4Dinner son solo de 4 personas?',
            ],
            'cuerpo'  => 'Porque el formato íntimo facilita conversaciones reales. En una mesa de 4 todos participan, todos escuchan y todos generan valor. Es lo opuesto a los eventos masivos donde terminas hablando con las mismas 2 personas de siempre.',
            'titulo_nuevo' => '¿Por qué las cenas de Vitrinexo 4Dinner son solo de cuatro personas?',
            'cuerpo_nuevo' => 'Porque el formato íntimo facilita conversaciones reales. En una mesa de cuatro todos participan, todos escuchan y todos generan valor. Es lo opuesto a los eventos masivos, donde terminas hablando con las mismas dos personas de siempre.',
        ],
        [
            'titulos' => [
                '¿En qué ciudades se realizan las cenas de Vitrinexo 4Dinner?',
                '¿En qué ciudades se realizan las cenas 4Dinner?',
            ],
            'cuerpo'  => 'Las cenas se realizan simultáneamente en múltiples ciudades. El detalle de ciudades activas se comunica a través de la plataforma según la demanda de cada mercado.',
            'titulo_nuevo' => '¿En qué ciudades hay cenas de Vitrinexo 4Dinner?',
            'cuerpo_nuevo' => 'Las cenas ocurren a la misma hora en varias ciudades. Vitrinexo avisa por la plataforma cuáles están activas, según la demanda de cada mercado.',
        ],
        [
            'titulos' => [
                '¿Cómo me inscribo en una cena de Vitrinexo 4Dinner?',
                '¿Cómo me inscribo en una cena 4Dinner?',
            ],
            'cuerpo'  => 'La inscripción a las cenas de Vitrinexo 4Dinner se gestiona desde la plataforma. Los miembros activos reciben información sobre las próximas cenas disponibles en su ciudad.',
            'titulo_nuevo' => '¿Cómo me inscribo en una cena de Vitrinexo 4Dinner?',
            'cuerpo_nuevo' => 'Te inscribes desde la plataforma. Los miembros activos reciben aviso de las próximas cenas en su ciudad.',
        ],
        [
            'titulos' => [
                '¿Quién define con quiénes ceno en Vitrinexo 4Dinner?',
                '¿Quién define con quiénes ceno en un 4Dinner?',
            ],
            'cuerpo'  => 'Vitrinexo compone las mesas considerando afinidad de rubros, intereses y objetivos de negocio de los participantes. La idea es que cada cena sea una conversación con sentido, no una mezcla aleatoria.',
            'titulo_nuevo' => '¿Quién define con quiénes ceno en Vitrinexo 4Dinner?',
            'cuerpo_nuevo' => 'Vitrinexo compone las mesas según afinidad de rubros, intereses y objetivos de negocio. La idea es que cada cena sea una conversación con sentido y no una mezcla al azar.',
        ],
        [
            'titulos' => [
                '¿Puedo pedir cenar con alguien específico en Vitrinexo 4Dinner?',
                '¿Puedo pedir cenar con alguien específico en un 4Dinner?',
            ],
            'cuerpo'  => 'El formato está pensado para generar conexiones nuevas, no para reunir a personas que ya se conocen. La composición de las mesas la define el equipo de Vitrinexo.',
            'titulo_nuevo' => '¿Puedo pedir cenar con alguien específico en Vitrinexo 4Dinner?',
            'cuerpo_nuevo' => 'El formato está pensado para generar conexiones nuevas, no para reunir a quienes ya se conocen. Las mesas las compone Vitrinexo.',
        ],
    ];
}

add_action( 'init', function () {
    if ( get_option( VX_MIG_FAQ_VOZ ) ) return;
    if ( ! post_type_exists( 'vx_faq' ) ) return;

    $respaldo = [];
    $intactas = [];

    foreach ( vx_faq_voz_entradas() as $pedido ) {
        $encontrada = null;
        foreach ( $pedido['titulos'] as $titulo ) {
            $hallazgo = get_posts( [
                'post_type'      => 'vx_faq',
                'title'          => $titulo,
                'posts_per_page' => 1,
                'post_status'    => 'any',
            ] );
            if ( $hallazgo ) { $encontrada = $hallazgo[0]; break; }
        }

        if ( ! $encontrada ) { $intactas[] = $pedido['titulos'][0] . ' (no está)'; continue; }

        if ( trim( wp_strip_all_tags( $encontrada->post_content ) ) !== $pedido['cuerpo'] ) {
            $intactas[] = $encontrada->post_title . ' (el cuerpo no es el que esperaba)';
            continue;
        }

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

    update_option( VX_MIG_FAQ_VOZ, [
        'fecha'    => current_time( 'mysql' ),
        'tocadas'  => array_keys( $respaldo ),
        'intactas' => $intactas,
        'respaldo' => $respaldo,
    ], false );
}, 22 );
