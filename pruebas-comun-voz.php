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

    // Las dos de arriba piden un sustantivo al lado y estas no, a proposito.
    // «latinoamericano» es español corriente y describe a una persona o a una
    // empresa sin decir nada de la marca, asi que prohibirlo entero marcaria
    // en rojo la ficha de un miembro. «Hispanoamerica» y «continental» no:
    // en este producto no aparecen nunca para otra cosa que para decir de
    // que region es Vitrinexo, que es justo lo que no se dice.
    //
    // Las tres veces que se escribieron lo confirman. La landing del evento
    // decia que la cena es «en una ciudad de Hispanoamerica», su tarjeta se
    // titulaba «Continental y local», y el blog hablaba del «ecosistema de
    // servicios B2B en Hispanoamerica». Ninguna prueba vio una sola: las dos
    // del blog ademas llevaban «ecosistema», que si estaba prohibida desde el
    // principio, y siguieron en produccion porque /blog/ no era una ruta que
    // alguien abriera.
    [ '/\bhispanoam[eé]rica(nas?|nos?)?\b/iu', 'no se dice que la marca sea de una region: se dice donde hay comunidad hoy' ],
    [ '/\bcontinental(es)?\b/iu', 'no se dice que la marca sea de una region: se dice en que ciudades hay mesa hoy' ],
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
