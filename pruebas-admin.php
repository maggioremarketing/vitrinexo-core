<?php
/**
 * Que wp-admin hable como habla la marca.
 *
 * Es la ultima superficie de texto que no medía nadie. No la ven los miembros,
 * la vemos nosotros, y por eso mismo es donde una palabra prohibida se queda
 * años: nadie la lee con ojos de marca. El dia que se escribio esta prueba,
 * wp-admin estaba limpio. La prueba no esta para arreglar algo roto, esta para
 * que siga limpio sin que nadie tenga que acordarse.
 *
 * ── Esta prueba lee el archivo, y las otras no ───────────────────────────────
 *
 * `pruebas-comun-voz.php` dice que se mide sobre lo renderizado y nunca sobre
 * el archivo, y tiene razon: `match ( true )` es una expresion de PHP,
 * `badge-vx` es una clase de CSS y `offers_matches` es una clave de datos.
 * Prohibir esas palabras en el codigo solo enseñaria a ignorar la prueba.
 *
 * Una pantalla de wp-admin no se puede renderizar sin WordPress entero: no es
 * una funcion que devuelve HTML como los estados vacios, ni una plantilla como
 * los correos. Son callbacks colgados de hooks que leen la base de datos y
 * escriben con echo. Medirlas de verdad pedia levantar WordPress y entrar como
 * administrador, que es mucho mas caro que lo que protege.
 *
 * Asi que esta lee el archivo, pero no lo lee como texto: lo tokeniza. PHP sabe
 * cual de sus pedazos es codigo y cual es texto, y se le pregunta a el. Se
 * miden solo los literales entrecomillados y el HTML que esta fuera de <?php,
 * que es exactamente lo que termina impreso. Lo demas ni se mira: `preg_match`
 * y `offers_matches` son identificadores (T_STRING), `match ( true )` es una
 * palabra reservada (T_MATCH), y los comentarios son comentarios. Las tres
 * trampas que hicieron escribir aquella regla las descarta el tokenizador solo,
 * sin una lista de excepciones que alguien tendria que mantener.
 *
 * Lo que cuesta: un texto que se arma concatenando en tiempo de ejecucion se
 * mide en pedazos, y un texto que viene de la base de datos no se mide. Es
 * menos de lo que parece, porque el copy de wp-admin esta escrito a mano en el
 * archivo, pero es real y por eso queda dicho aca.
 *
 * Correr:  php pruebas-admin.php
 */

define( 'ABSPATH', __DIR__ . '/' );

require_once __DIR__ . '/pruebas-comun-voz.php';

/**
 * Carpetas que son pantalla de administracion enteras.
 *
 * Van por carpeta y no por marca de hook porque
 * `modules/admin/class-vx-admin-membership.php` no llama a ninguna de las
 * marcas de abajo (se cuelga de una pagina que abre otro archivo) y aun asi
 * escribe copy que lee un administrador. Si esta en `modules/admin`, es de
 * wp-admin: no hace falta que lo demuestre.
 */
const VX_ADMIN_CARPETAS = [ 'modules/admin', 'cpts' ];

/** Lo que delata una pantalla de admin escondida en cualquier otra carpeta. */
const VX_ADMIN_MARCAS = [
    'add_menu_page',
    'add_submenu_page',
    'manage_users_columns',
    'manage_users_custom_column',
    'admin_notices',
    'register_post_type',
    'add_meta_box',
];

/**
 * El piso de archivos. Es un testigo, no un limite: si el descubrimiento se
 * rompe y de pronto encuentra tres archivos, la prueba pasaria en verde
 * midiendo casi nada. El dia que se escribio encontraba trece.
 */
const VX_ADMIN_PISO = 10;

/** Los archivos de admin, descubiertos y no escritos a mano. */
function vx_admin_archivos(): array
{
    $raiz  = __DIR__;
    $todos = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $raiz ) );
    $encontrados = [];

    foreach ( $todos as $archivo ) {
        if ( ! $archivo->isFile() || 'php' !== $archivo->getExtension() ) continue;

        $ruta = str_replace( $raiz . '/', '', $archivo->getPathname() );

        // La prueba no se mide a si misma, ni a sus hermanas: estan llenas de
        // palabras prohibidas a proposito, que es de lo que hablan.
        if ( str_starts_with( $ruta, 'pruebas-' ) ) continue;
        if ( str_starts_with( $ruta, 'vendor/' ) || str_starts_with( $ruta, 'node_modules/' ) ) continue;

        $es_admin = false;
        foreach ( VX_ADMIN_CARPETAS as $carpeta ) {
            if ( str_starts_with( $ruta, $carpeta . '/' ) ) $es_admin = true;
        }

        if ( ! $es_admin ) {
            $fuente = file_get_contents( $archivo->getPathname() );
            foreach ( VX_ADMIN_MARCAS as $marca ) {
                if ( str_contains( $fuente, $marca ) ) { $es_admin = true; break; }
            }
        }

        if ( $es_admin ) $encontrados[] = $ruta;
    }

    sort( $encontrados );
    return $encontrados;
}

/**
 * Le quita las comillas a un literal y deshace lo escapado.
 *
 * Los saltos de linea escritos como \n se vuelven un espacio: pegados a la
 * palabra de al lado la convertirian en un token con barras, y un token con
 * barras no parece prosa y no se mediria.
 */
function vx_admin_desencomillar( string $token ): string
{
    $texto = substr( $token, 1, -1 );
    $texto = str_replace( [ '\\"', "\\'", '\\n', '\\t', '\\r', '\\\\' ], [ '"', "'", ' ', ' ', ' ', '\\' ], $texto );
    return $texto;
}

/**
 * Si un literal es una frase o es codigo.
 *
 * El criterio es cuantas palabras de puras letras tiene, despues de pelarles
 * la puntuacion de los bordes. `badge-vx badge-vx--senior` tiene cero, porque
 * el guion esta adentro; `America/Santiago` tiene cero; `color:#5C5F6B` tiene
 * cero. «Sin vencimiento» tiene dos. Dos palabras limpias es prosa.
 *
 * Una palabra sola pasa solo si empieza en mayuscula y no esta entera en caja
 * alta: «Distintivo» es un rotulo, 'match' es el nombre de un indice y
 * 'ABSPATH' es una constante. Sin la segunda mitad de esa regla, 'ABSPATH'
 * contaba como una frase y casi todos los archivos entregaban al menos una,
 * que es justo lo que el testigo de «este archivo no se midio» tenia que ver.
 *
 * Lo que cuesta: un literal que sea solo 'VITRINEXO', a secas, no se mide. La
 * regla del nombre en caja alta igual lo caza en cualquier frase de dos
 * palabras y en todo el HTML en linea, que es donde el nombre se escribe.
 */
function vx_admin_parece_prosa( string $texto ): bool
{
    $limpias = [];

    foreach ( preg_split( '/\s+/u', $texto, -1, PREG_SPLIT_NO_EMPTY ) as $token ) {
        $token = preg_replace( '/^[^\p{L}]+|[^\p{L}]+$/u', '', $token );
        if ( '' === $token ) continue;
        if ( preg_match( '/^[\p{L}\p{M}]+$/u', $token ) ) $limpias[] = $token;
    }

    if ( count( $limpias ) >= 2 ) return true;
    if ( 1 !== count( $limpias ) ) return false;

    return (bool) preg_match( '/^\p{Lu}/u', $limpias[0] )
        && ! preg_match( '/^[\p{Lu}\p{M}]+$/u', $limpias[0] );
}

/**
 * Saca de un bloque de HTML lo que hay dentro de <style> y <script>.
 *
 * strip_tags borra la etiqueta pero deja su contenido, y el CSS de las
 * pantallas de admin esta lleno de nombres de clase: `.vx-val-badge { … }` se
 * leia como que la pantalla dice «badge». Una hoja de estilo no la lee nadie.
 *
 * El estado viaja entre bloques porque una etiqueta <style> puede abrirse en
 * un bloque de HTML y cerrarse en otro, con PHP en el medio; si cada bloque
 * empezara de cero, la mitad de una hoja de estilo contaria como texto.
 */
function vx_admin_sin_estilos( string $html, bool &$dentro ): string
{
    $fuera = '';

    while ( '' !== $html ) {
        if ( $dentro ) {
            if ( ! preg_match( '#</(style|script)\s*>#i', $html, $m, PREG_OFFSET_CAPTURE ) ) return $fuera;
            $html   = substr( $html, $m[0][1] + strlen( $m[0][0] ) );
            $dentro = false;
            continue;
        }

        if ( ! preg_match( '#<(style|script)\b#i', $html, $m, PREG_OFFSET_CAPTURE ) ) {
            return $fuera . $html;
        }

        $fuera  .= substr( $html, 0, $m[0][1] ) . ' ';
        $html    = substr( $html, $m[0][1] + strlen( $m[0][0] ) );
        $dentro  = true;
    }

    return $fuera;
}

/**
 * El texto que un archivo le muestra a una persona, sin el codigo que lo rodea.
 *
 * @return array{texto: string, piezas: int}
 */
function vx_admin_copy( string $ruta ): array
{
    $tokens = token_get_all( file_get_contents( __DIR__ . '/' . $ruta ) );
    $piezas = [];
    $dentro = false;

    foreach ( $tokens as $token ) {
        // Un token suelto es un signo de puntuacion del lenguaje. Nunca texto.
        if ( ! is_array( $token ) ) continue;

        [ $id, $crudo ] = $token;

        if ( T_INLINE_HTML === $id ) {
            // Lo que esta fuera de <?php se imprime tal cual: es copy entero y
            // no se le pregunta si parece prosa.
            $pieza = strip_tags( vx_admin_sin_estilos( $crudo, $dentro ) );
        } elseif ( T_CONSTANT_ENCAPSED_STRING === $id ) {
            $pieza = vx_admin_desencomillar( $crudo );
            if ( ! vx_admin_parece_prosa( $pieza ) ) continue;
            $pieza = strip_tags( $pieza );
        } elseif ( T_ENCAPSED_AND_WHITESPACE === $id ) {
            // Los pedazos literales de una cadena con variables adentro.
            if ( ! vx_admin_parece_prosa( $crudo ) ) continue;
            $pieza = strip_tags( $crudo );
        } else {
            continue;
        }

        // Cada pieza se despoja de etiquetas por separado a proposito. Una
        // etiqueta que el PHP parte al medio, `<div class="<?php …`, deja el
        // bloque con un `<` sin cerrar, y strip_tags se come todo lo que viene
        // despues. Si se pegaran todos los bloques antes de limpiar, ese `<`
        // se tragaria el copy de los bloques siguientes y la prueba pasaria en
        // verde por no haber mirado.
        if ( '' !== trim( $pieza ) ) $piezas[] = trim( $pieza );
    }

    return [ 'texto' => implode( ' · ', $piezas ), 'piezas' => count( $piezas ) ];
}

// ── El examen ────────────────────────────────────────────────────────────────
$archivos = vx_admin_archivos();
$fallos   = [];
$medidos  = 0;

// El testigo primero. Si el descubrimiento se rompio, lo que venga despues
// mide media casa y decir cuanto midio seria mentir.
if ( count( $archivos ) < VX_ADMIN_PISO ) {
    echo "  x admin\n";
    echo '      solo se encontraron ' . count( $archivos ) . ' archivos de admin y el piso es ' . VX_ADMIN_PISO . ":\n";
    echo "      el descubrimiento de archivos dejo de funcionar\n";
    exit( 1 );
}

foreach ( $archivos as $ruta ) {
    $copy = vx_admin_copy( $ruta );

    // Un archivo de admin sin una sola frase es un archivo que no se midio.
    // Puede ser que de verdad no tenga copy, pero es mas probable que el
    // tokenizador se haya tropezado, y dar por bueno lo que no se abrio es la
    // peor forma de pasar en verde.
    if ( 0 === $copy['piezas'] ) {
        $fallos[] = "$ruta no entrego ni una frase: no se midio";
        continue;
    }

    $medidos += $copy['piezas'];
    $fallos   = array_merge( $fallos, vx_voz_revisar( $ruta, $copy['texto'] ) );
}

// ── Informe ──────────────────────────────────────────────────────────────────
if ( $fallos ) {
    echo '  x admin (' . count( $archivos ) . " archivos)\n";
    foreach ( $fallos as $f ) echo "      $f\n";
    exit( 1 );
}

echo '  ok admin (' . count( $archivos ) . ' archivos, ' . $medidos . " textos medidos)\n";
