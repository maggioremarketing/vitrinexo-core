<?php
/**
 * Las preguntas frecuentes también llevan la marca madre adelante.
 *
 * La regla ya se mide desde la prueba de vocabulario, y el primer despliegue
 * que la midió contra producción la encontró rota en seis entradas de
 * /preguntas-frecuentes/: «las cenas 4Dinner», «una cena 4Dinner», «en un
 * 4Dinner». Esas entradas no viven en el repositorio: son contenido del CPT
 * vx_faq, escrito en WordPress, así que ningún empuje las arregla y el
 * despliegue se queda en rojo para siempre. Esto las arregla donde están.
 *
 * Reemplaza fragmentos y no la entrada entera, a propósito. Lo que hay
 * guardado puede traer marcado de bloques o no traerlo, y reescribir un cuerpo
 * completo contra un original que no conozco byte a byte es la forma de
 * pisar una edición ajena sin enterarse. Un fragmento acotado no puede hacer
 * eso: o está tal cual y se corrige, o no está y la entrada queda intacta.
 *
 * strtr con un arreglo, y no str_replace encadenado, porque strtr no vuelve a
 * mirar lo que ya reemplazó. Encadenando, «las cenas de Vitrinexo 4Dinner»
 * volvería a entrar por la regla de «4Dinner» y quedaría la marca dos veces.
 *
 * Lo que NO toca, y conviene que se sepa: la respuesta de precios dice que el
 * detalle se comunicará más adelante, y la portada dice que sentarse a la mesa
 * no cuesta nada. Las dos están vivas en el mismo sitio. Eso es una decisión
 * de negocio y no una regla de marca, así que acá solo se le saca el nombre
 * desnudo y la contradicción queda en pie, a la vista, para que la resuelva
 * quien corresponde. Una migración no es lugar para decidir un precio.
 *
 * Corre una sola vez y guarda el original de todo lo que toca, porque el CPT
 * no tiene revisiones: sin respaldo esto no se podría deshacer.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'VX_MIG_FAQ_MARCA_MADRE', 'vx_migracion_faq_marca_madre' );

/**
 * Las cuatro formas en que la pieza aparecía desnuda, con su reemplazo.
 *
 * Son las que encontró la prueba, una por una, y ninguna más: no hay aquí un
 * patrón que adivine. Si mañana aparece una quinta forma, la caza el
 * despliegue y se agrega acá con su nombre, que es como se vio esta vez.
 *
 * Dentro de la respuesta el nombre completo queda pesado si se repite, pero la
 * regla del manual no admite el nombre a secas y la alternativa —cambiar la
 * frase entera— es justo lo que esta migración no debe hacer. Afinar la
 * redacción es trabajo de quien escribe, en el editor.
 */
function vx_faq_marca_madre_reglas() {
    return [
        'Las cenas 4Dinner' => 'Las cenas de Vitrinexo 4Dinner',
        'las cenas 4Dinner' => 'las cenas de Vitrinexo 4Dinner',
        'una cena 4Dinner'  => 'una cena de Vitrinexo 4Dinner',
        'en un 4Dinner'     => 'en Vitrinexo 4Dinner',
    ];
}

/**
 * Corrige título y cuerpo de cada entrada y devuelve lo que había antes.
 *
 * Solo escribe donde algo cambió. Una entrada que ya estaba bien no se toca,
 * así que no se le mueve la fecha de modificación ni se le pisa nada.
 */
function vx_faq_marca_madre_corregir() {
    $reglas   = vx_faq_marca_madre_reglas();
    $respaldo = [];

    $entradas = get_posts( [
        'post_type'   => 'vx_faq',
        'numberposts' => -1,
        'post_status' => 'any',
    ] );

    foreach ( $entradas as $entrada ) {
        $titulo = strtr( $entrada->post_title,   $reglas );
        $cuerpo = strtr( $entrada->post_content, $reglas );

        if ( $titulo === $entrada->post_title && $cuerpo === $entrada->post_content ) continue;

        $respaldo[ $entrada->ID ] = [
            'post_title'   => $entrada->post_title,
            'post_content' => $entrada->post_content,
        ];

        wp_update_post( [
            'ID'           => $entrada->ID,
            'post_title'   => $titulo,
            'post_content' => $cuerpo,
        ] );
    }

    return $respaldo;
}

/**
 * Lo que sigue roto después de corregir.
 *
 * Los fragmentos son exactos a propósito, y eso tiene un costo: si en la base
 * hay un espacio duro o una comilla distinta a la que yo vi en la página, la
 * regla no engancha y la entrada se queda como estaba. La migración corre una
 * sola vez, así que sin esto se daría por hecha dejando el problema intacto y
 * nadie se enteraría hasta el siguiente despliegue rojo.
 *
 * No reintenta ni adivina: deja los títulos anotados en la misma opción, para
 * que al abrirla se vea qué quedó pendiente y se arregle en el editor.
 */
function vx_faq_marca_madre_pendientes() {
    $pendientes = [];
    foreach ( get_posts( [ 'post_type' => 'vx_faq', 'numberposts' => -1, 'post_status' => 'any' ] ) as $entrada ) {
        $texto = $entrada->post_title . ' ' . $entrada->post_content;
        if ( preg_match( '/(?<!Vitrinexo )\b4Dinner/', $texto ) ) $pendientes[] = $entrada->post_title;
    }
    return $pendientes;
}

/**
 * Prioridad 20 porque el CPT se registra en init con la de por defecto. Antes
 * de que exista, get_posts devuelve vacío sin quejarse: la migración se daría
 * por hecha sin haber mirado una sola entrada, que es la misma forma de pasar
 * en verde sin medir nada que este proyecto ya se encontró tres veces.
 * Por eso además se comprueba que el tipo exista antes de marcarla corrida.
 */
add_action( 'init', function () {
    if ( get_option( VX_MIG_FAQ_MARCA_MADRE ) ) return;
    if ( ! post_type_exists( 'vx_faq' ) ) return;

    $respaldo = vx_faq_marca_madre_corregir();

    update_option( VX_MIG_FAQ_MARCA_MADRE, [
        'fecha'      => current_time( 'mysql' ),
        'tocadas'    => array_keys( $respaldo ),
        'pendientes' => vx_faq_marca_madre_pendientes(),
        'respaldo'   => $respaldo,
    ], false );
}, 20 );
