<?php
/**
 * Como habla la marca, en un solo lugar.
 *
 * Esto no es una prueba: es la lista que comparten las pruebas del plugin que
 * miden copy. Vive aparte porque cuando estaba dentro de pruebas-correos.php,
 * la segunda prueba que la necesitó tuvo que copiarla, y dos copias de una
 * lista de palabras prohibidas es una que se queda atras.
 *
 * Sigue estando repetida respecto del tema, que tiene la suya en
 * `pruebas/pantalla/vocabulario.mjs`, y eso si es a proposito: el plugin se
 * despliega solo y no puede depender de que el tema este al lado para saber
 * como se habla. Una palabra que se agrega alla se agrega aca. Dos copias en
 * dos repositorios que se despliegan por separado es el precio; tres copias en
 * el mismo repositorio no tenia excusa.
 *
 * Se mide siempre sobre lo renderizado y nunca sobre el archivo, igual que en
 * el tema: `match ( true )` es una expresion de PHP, `badge-vx` es una clase de
 * CSS y `offers_matches` es una clave de datos. Prohibirlos en el codigo solo
 * enseñaria a ignorar la prueba.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** Las palabras que no se dicen, cada una con por que no se dice. */
const VX_VOZ_PROHIBIDAS = [
    [ '/\becosistemas?\b/iu', 'no significa nada' ],
    [ '/\bsinergias?\b/iu',   'no significa nada' ],
    [ '/\bsoluci[oó]n(es)?\b/iu', 'no significa nada' ],
    [ '/\bpartners?\b/iu',    'esta en ingles y ya hay palabra' ],
    [ '/\bmatch(es)?\b/iu',   'es de las aplicaciones de citas' ],
    [ '/\bearly adopters?\b/iu', 'se dice Miembro Pionero' ],
    [ '/\bbadges?\b/iu',      'se dice distintivo' ],
    [ '/VITRINEXO/u',         'el nombre en caja alta grita' ],
    [ '/\b(ten[ée]s|pod[ée]s|quer[ée]s|sab[ée]s)\b/iu', 'se trata de tu: «tienes» y «puedes», nunca «tenes» ni «podes»' ],
    [ '/\bsos\s+(un|una|el|la|miembro)\b/iu', 'se trata de tu: «eres», nunca «sos»' ],
    [ '/\bplataforma\s+(latinoamericana|latina)\b/iu', 'no se dice que la marca sea de una region: se dice donde hay comunidad hoy' ],
    [ '/\b(red|directorio|comunidad)\s+latinoamericana\b/iu', 'no se dice que la marca sea de una region: se dice donde hay comunidad hoy' ],

    // «continental» va sola, sin sustantivo al lado, al reves que las dos de
    // arriba. Se escribio porque la tarjeta de la landing del evento se
    // titulaba «Continental y local», y no aparece en ninguna otra parte de
    // los dos repos: no hay uso legitimo que proteger.
    [ '/\bcontinental(es)?\b/iu', 'no se dice que la marca sea de una region: se dice en que ciudades hay mesa hoy' ],

    // «Hispanoamerica» si lleva sustantivo, y el contraejemplo lo dio el
    // propio manual. Nacio sola, como «continental», y al medirla salio roja
    // en cinco frases del manual que dicen justo lo que el criterio permite:
    // «el lanzamiento es en Hispanoamerica y en español», «el español es el de
    // la region», «en Hispanoamerica ese signo nombra siete monedas
    // distintas». Ninguna declara de que region es la marca: dicen donde hay
    // comunidad hoy, que es la mitad permitida del criterio. Prohibir el
    // nombre entero obligaba a reescribir cinco frases correctas para esquivar
    // una palabra, y eso es lo que enseña a ignorar la prueba.
    //
    // Hace falta decir lo que esta version NO caza, porque es facil suponer lo
    // contrario leyendo de donde salio. Ninguna de las tres frases que la
    // hicieron nacer: «una ciudad de Hispanoamerica» en la landing del evento,
    // «servicios B2B en Hispanoamerica» en el blog y «directivas
    // hispanoamericanas» en Vitrinexo Woman pasan las tres en verde con esta
    // linea puesta. Y pasan bien: medidas contra el criterio, ninguna declara
    // que Vitrinexo sea de una region. Se cambiaron por como se leen, que es
    // una decision de redaccion, y las decisiones de redaccion no las toma una
    // prueba. Lo unico que esta linea impide es la forma que si lo declara,
    // «red hispanoamericana», que hasta hoy no se escribio nunca.
    //
    // Lo que si incumplia era el blog, y por otra palabra: decia «ecosistema»,
    // prohibida desde el primer dia. Nadie la vio durante meses porque /blog/
    // no estaba en la lista de rutas que las pruebas abren.
    [ '/\b(plataforma|red|directorio|comunidad)\s+hispanoamericanas?\b/iu', 'no se dice que la marca sea de una region: se dice donde hay comunidad hoy' ],
];

/** Las piezas de la linea, que nunca se nombran sin la marca madre adelante. */
const VX_VOZ_PIEZAS = [ '4Dinner', 'Woman', 'Senior', 'LGBTQ\+' ];

/**
 * «senior» y «LGBTQ+» tambien son español corriente, y ahi nombran a la gente
 * y no a la pieza. La caja distingue al adjetivo del nombre propio, y a la
 * sigla se le tacha el uso donde modifica a un sustantivo de personas:
 * «profesionales LGBTQ+» son las personas, «Vitrinexo LGBTQ+» es la pieza.
 */
const VX_VOZ_GENTE = '/\b(persona|personas|profesionales?|ejecutivos?|empresarios?|socios?|miembros?)\s+LGBTQ\+/u';

/**
 * Pasa un trozo de HTML por las dos reglas y devuelve los fallos en palabras.
 *
 * @param string $donde  Como se nombra en el informe lo que se esta midiendo.
 * @param string $html   Lo renderizado, no el archivo.
 * @return string[]      Vacio si habla bien.
 */
function vx_voz_revisar( string $donde, string $html ): array
{
    $fallos = [];

    $texto = html_entity_decode( strip_tags( $html ), ENT_QUOTES, 'UTF-8' );
    $texto = trim( preg_replace( '/\s+/u', ' ', $texto ) );

    foreach ( VX_VOZ_PROHIBIDAS as [ $patron, $porque ] ) {
        if ( preg_match( $patron, $texto, $m ) ) {
            $fallos[] = "$donde dice «{$m[0]}»: $porque";
        }
    }

    $plano = preg_replace( VX_VOZ_GENTE, '', $texto );
    foreach ( VX_VOZ_PIEZAS as $pieza ) {
        if ( ! preg_match( '/(?<!Vitrinexo )\b' . $pieza . '/u', $plano, $m, PREG_OFFSET_CAPTURE ) ) continue;
        $desde = max( 0, $m[0][1] - 32 );
        $largo = ( $m[0][1] - $desde ) + strlen( $m[0][0] ) + 24;
        $cerca = ( $desde ? '…' : '' ) . trim( substr( $plano, $desde, $largo ) ) . '…';
        $fallos[] = "$donde dice «{$cerca}»: las piezas llevan la marca madre adelante, se escribe «Vitrinexo {$m[0][0]}»";
    }

    return $fallos;
}
