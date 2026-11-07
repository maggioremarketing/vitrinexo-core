<?php
/**
 * Que los estados vacios hablen como habla la marca.
 *
 * Un estado vacio es la pantalla que ve quien todavia no tiene nada: ninguna
 * conexion, ningun favorito, ninguna notificacion. Es el primer texto que lee
 * un miembro nuevo, y es justo el que ninguna prueba de pantalla puede ver,
 * porque el banco local tiene seis miembros con datos y una pantalla con datos
 * nunca dibuja su estado vacio. Medirlos con el navegador pedria un banco
 * vacio al lado del lleno; se miden llamando a la funcion que los arma, que es
 * mas barato y no se olvida de ninguno.
 *
 * Mientras no se midieron, el estado vacio de notificaciones decia «conexiones,
 * visitas y matches», palabra prohibida, ocho commits despues de que «match»
 * saliera del area de miembros. Los otros ocho estados ya decian «nexos»: el
 * que no se ve es el que se queda atras.
 *
 * Los tipos no estan escritos a mano aca: se leen de la funcion. Una lista
 * copiada es una lista que se queda corta el dia que alguien agrega un estado,
 * y ese dia la prueba seguiria en verde.
 *
 * Correr:  php pruebas-estados-vacios.php
 */

define( 'ABSPATH', __DIR__ . '/' );

// ── Lo minimo de WordPress que el fragmento toca ─────────────────────────────
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $t ) { return esc_html( $t ); }
function esc_url( $t )  { return (string) $t; }
function home_url( $p = '' ) { return 'https://vitrinexo.com' . $p; }
function add_shortcode( $n, $f ) {}
function get_template_part( ...$a ) {}
function shortcode_atts( $pares, $atts ) { return array_merge( $pares, (array) $atts ); }

require_once __DIR__ . '/pruebas-comun-voz.php';
require_once __DIR__ . '/shortcodes/shortcodes-fragments.php';

/**
 * Los tipos de estado vacio, leidos del cuerpo de la funcion que los define.
 *
 * Se lee el archivo y no se adivina con un render por tipo: un tipo que no
 * existe cae en 'generico' y devolveria HTML valido, asi que preguntarle a la
 * funcion «¿tienes este?» no sirve para descubrir cuales tiene.
 */
function vx_leer_estados_vacios(): array
{
    $f      = new ReflectionFunction( 'vx_render_empty_state' );
    $lineas = array_slice(
        file( $f->getFileName() ),
        $f->getStartLine(),
        $f->getEndLine() - $f->getStartLine()
    );

    $tipos = [];
    $iconos = 0;
    foreach ( $lineas as $linea ) {
        // Cada estado trae exactamente un icono. Contarlos aparte es el unico
        // testigo de que la lectura de claves no se salto ninguno: una clave
        // que el patron no ve no falla por si sola, simplemente no se mide.
        if ( preg_match( "/^\s+'icon' *=>/", $linea ) ) $iconos++;
        // Solo las claves del primer nivel del arreglo, que son las que abren
        // un bloque: ocho espacios, la clave, la flecha y el corchete.
        if ( preg_match( "/^        '([a-z0-9-]+)' *=> *\[\s*$/", $linea, $m ) ) {
            $tipos[] = $m[1];
        }
    }

    return [ 'tipos' => $tipos, 'iconos' => $iconos ];
}

// ── El examen ────────────────────────────────────────────────────────────────
$leido  = vx_leer_estados_vacios();
$tipos  = $leido['tipos'];
$fallos = [];

// El testigo primero: si la lectura se salto un estado, lo que venga despues
// mide una lista incompleta y decir cuantos midio seria mentir.
if ( $leido['iconos'] !== count( $tipos ) ) {
    echo "  x estados vacios\n";
    echo "      se leyeron " . count( $tipos ) . " claves pero la funcion define {$leido['iconos']} estados:\n";
    echo "      hay uno que la prueba no esta midiendo\n";
    exit( 1 );
}

if ( count( $tipos ) < 5 ) {
    $fallos[] = 'solo se leyeron ' . count( $tipos ) . ' tipos: la lectura de la funcion dejo de funcionar';
}

if ( ! in_array( 'generico', $tipos, true ) ) {
    $fallos[] = 'no se leyo el tipo «generico», que es el respaldo de todos los demas';
}

$generico = vx_render_empty_state( 'generico' );

foreach ( $tipos as $tipo ) {
    $html = vx_render_empty_state( $tipo );

    if ( '' === trim( $html ) ) {
        $fallos[] = "$tipo no devolvio nada: no se midio";
        continue;
    }

    // Un tipo mal leido cae en 'generico' y pasaria en verde midiendo el
    // respaldo dos veces. Si el texto es identico al generico, no se midio
    // este estado: se midio aquel.
    if ( 'generico' !== $tipo && $html === $generico ) {
        $fallos[] = "$tipo devolvio el estado generico: el tipo no existe con ese nombre";
        continue;
    }

    $fallos = array_merge( $fallos, vx_voz_revisar( $tipo, $html ) );
}

// ── Informe ──────────────────────────────────────────────────────────────────
if ( $fallos ) {
    echo '  x estados vacios (' . count( $tipos ) . " leidos)\n";
    foreach ( $fallos as $f ) echo "      $f\n";
    exit( 1 );
}

echo '  ok estados vacios (' . count( $tipos ) . " medidos)\n";
