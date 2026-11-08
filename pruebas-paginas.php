<?php
/**
 * Que la lista de paginas del plugin describa el sitio que existe.
 *
 * vx_create_pages() es la lista autoritativa: lo que diga ahi es lo que crea
 * una instalacion nueva, y lo que reescribe cualquier instalacion cuando sube
 * VX_VERSION. Si la lista se queda atras, nadie se entera, porque en el sitio
 * que ya funciona las paginas viejas siguen ahi.
 *
 * Se quedo atras. Creaba las tres de nexos con sus nombres viejos —matches,
 * match-seeks, match-offers, palabra prohibida por el manual desde que el area
 * de miembros dejo de decir «match»— y no mencionaba /oportunidades/,
 * /mis-publicaciones/ ni /preguntas-frecuentes/, que existen en produccion
 * desde hace tiempo. O sea que una instalacion nueva nacia con tres paginas
 * muertas y sin tres pantallas vivas.
 *
 * Lo que se mide:
 *   - los slugs no dicen ninguna palabra prohibida: un slug es una URL, se
 *     queda para siempre y sale en Google;
 *   - los titulos hablan como habla la marca, con la regla entera, porque son
 *     el texto de la pestaña y del resultado de busqueda;
 *   - cada shortcode que la lista nombra existe de verdad en el plugin, o la
 *     pagina nace en blanco;
 *   - ningun slug repetido, ningun campo vacio.
 *
 * La lista se lee del archivo, no se copia: una copia se desincroniza y
 * entonces esta prueba miente igual que aquello que comprueba.
 *
 * Correr:  php pruebas-paginas.php
 */

define( 'ABSPATH', __DIR__ . '/' );

require_once __DIR__ . '/pruebas-comun-voz.php';

/** La lista, leida del cuerpo de vx_create_pages() en vitrinexo-core.php. */
function vx_leer_lista_paginas(): array
{
    $fuente = file_get_contents( __DIR__ . '/vitrinexo-core.php' );

    if ( ! preg_match( '/function vx_create_pages.*?\$pages = \[(.*?)\n    \];/s', $fuente, $m ) ) {
        return [];
    }

    preg_match_all(
        "/'([a-z0-9-]+)'\s*=>\s*\[\s*'title'\s*=>\s*'([^']*)'\s*,\s*'shortcode'\s*=>\s*'([^']*)'/u",
        $m[1],
        $filas,
        PREG_SET_ORDER
    );

    $lista = [];
    foreach ( $filas as $f ) {
        $lista[] = [ 'slug' => $f[1], 'titulo' => $f[2], 'shortcode' => $f[3] ];
    }
    return $lista;
}

/** Los shortcodes que el plugin registra de verdad, leidos de sus archivos. */
function vx_leer_shortcodes_registrados(): array
{
    $nombres = [];
    $it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( __DIR__ ) );
    foreach ( $it as $archivo ) {
        if ( $archivo->getExtension() !== 'php' ) continue;
        if ( str_contains( $archivo->getPathname(), '/banco/' ) ) continue;
        preg_match_all( "/add_shortcode\(\s*'([a-z0-9_]+)'/i", file_get_contents( $archivo->getPathname() ), $m );
        foreach ( $m[1] as $n ) $nombres[ $n ] = true;
    }
    return array_keys( $nombres );
}

$lista = vx_leer_lista_paginas();

if ( count( $lista ) < 20 ) {
    echo "  x paginas\n      se leyeron " . count( $lista ) . " paginas de vx_create_pages(), que son muy pocas: no se midio nada\n";
    exit( 1 );
}

$registrados = vx_leer_shortcodes_registrados();
$fallos      = [];
$vistos      = [];

foreach ( $lista as $p ) {
    [ 'slug' => $slug, 'titulo' => $titulo, 'shortcode' => $sc ] = $p;

    if ( isset( $vistos[ $slug ] ) ) $fallos[] = "el slug «{$slug}» esta dos veces";
    $vistos[ $slug ] = true;

    if ( '' === trim( $titulo ) ) $fallos[] = "«{$slug}» no tiene titulo";
    if ( '' === trim( $sc ) )     $fallos[] = "«{$slug}» no tiene shortcode";

    // El slug es una URL: se queda para siempre y sale en Google. Se le pide
    // solo la lista de prohibidas, no la regla entera: «las piezas llevan la
    // marca madre adelante» es una regla de prosa y una URL no es prosa.
    foreach ( VX_VOZ_PROHIBIDAS as [ $patron, $porque ] ) {
        if ( preg_match( $patron, str_replace( '-', ' ', $slug ), $m ) ) {
            $fallos[] = "el slug «{$slug}» dice «{$m[0]}»: $porque";
        }
    }

    // El titulo sale en la pestaña y en el resultado de busqueda, asi que se
    // le pide la regla entera.
    foreach ( vx_voz_revisar( "el titulo de «{$slug}»", $titulo ) as $f ) $fallos[] = $f;

    // Un shortcode que nadie registra deja la pagina en blanco el dia que
    // alguien instala el plugin de cero.
    if ( preg_match( '/^\[([a-z0-9_]+)/i', $sc, $m ) ) {
        if ( ! in_array( $m[1], $registrados, true ) ) {
            $fallos[] = "«{$slug}» usa [{$m[1]}], que no lo registra nadie en el plugin";
        }
    } else {
        $fallos[] = "«{$slug}» tiene un shortcode que no se entiende: «{$sc}»";
    }
}

// ── Informe ──────────────────────────────────────────────────────────────────
if ( $fallos ) {
    echo '  x paginas (' . count( $lista ) . " leidas)\n";
    foreach ( $fallos as $f ) echo "      $f\n";
    exit( 1 );
}

echo '  ok paginas (' . count( $lista ) . ' medidas, ' . count( $registrados ) . " shortcodes registrados)\n";
