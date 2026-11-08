<?php
/**
 * Que una ruta vieja lleve a donde dice, y que ninguna otra se mueva.
 *
 * Un 301 es permanente y el navegador lo guarda con ganas: una entrada mal
 * puesta en el mapa es dificil de deshacer en el navegador de quien ya la
 * visito. Por eso se mide el mapa entero, no un caso.
 *
 * Lo que se mide:
 *   - cada ruta del mapa redirige, con 301 y al destino que dice;
 *   - ninguna redirige a si misma, que es un bucle infinito en el navegador;
 *   - el destino no esta a su vez en el mapa, que es la otra forma del bucle;
 *   - la portada nunca se mueve;
 *   - una ruta que no esta en el mapa no se toca;
 *   - la forma sin barra final y la que trae parametros tambien entran, porque
 *     un enlace viejo puede venir de cualquiera de las dos.
 *
 * Correr:  php pruebas-redirecciones.php
 */

define( 'ABSPATH', __DIR__ . '/' );

// ── Lo minimo de WordPress que el modulo toca ────────────────────────────────
function add_action( ...$a ) {}
function is_admin(): bool { return false; }
function home_url( $p = '' ) { return 'https://vitrinexo.com' . $p; }

/** El redirect no se ejecuta: se levanta, para poder mirarlo y seguir. */
final class SeFue extends Exception {
    public function __construct( public string $destino, public int $codigo ) { parent::__construct( 'redirigido' ); }
}
function wp_safe_redirect( $destino, $codigo = 302 ) { throw new SeFue( (string) $destino, (int) $codigo ); }

require_once __DIR__ . '/modules/seo/class-vx-redirecciones.php';

$fallos = [];

/** Pide una ruta y devuelve [destino, codigo], o null si no hubo redireccion. */
function pedir( string $uri ): ?array {
    $_SERVER['REQUEST_URI'] = $uri;
    try {
        VX_Redirecciones::redirigir();
    } catch ( SeFue $e ) {
        return [ $e->destino, $e->codigo ];
    }
    return null;
}

/* El mapa se lee de la clase, no se copia: una lista copiada se queda corta el
   dia que alguien agrega una entrada, y ese dia la prueba seguiria en verde. */
$r    = new ReflectionClass( 'VX_Redirecciones' );
$mapa = $r->getConstant( 'MAPA' );

if ( ! $mapa ) {
    echo "  x redirecciones\n      el mapa vino vacio: no se midio nada\n";
    exit( 1 );
}

foreach ( $mapa as $desde => $hacia ) {
    if ( $desde === $hacia ) {
        $fallos[] = "$desde se redirige a si misma: eso es un bucle en el navegador";
        continue;
    }
    if ( isset( $mapa[ $hacia ] ) ) {
        $fallos[] = "$desde lleva a $hacia, que tambien esta en el mapa: dos saltos, y el segundo puede volver";
        continue;
    }

    $esperado = home_url( $hacia );

    foreach ( [ $desde, rtrim( $desde, '/' ), $desde . '?utm_source=x' ] as $forma ) {
        $ido = pedir( $forma );
        if ( null === $ido ) {
            $fallos[] = "«{$forma}» no redirigio a ninguna parte";
            continue;
        }
        [ $destino, $codigo ] = $ido;
        if ( 301 !== $codigo )      $fallos[] = "«{$forma}» redirigio con $codigo y no con 301";
        if ( $destino !== $esperado ) $fallos[] = "«{$forma}» fue a parar a $destino en vez de $esperado";
    }
}

/* La portada nunca se mueve. Si esto fallara, el sitio entero seria un bucle. */
if ( null !== pedir( '/' ) )  $fallos[] = 'la portada se redirige: el sitio seria un bucle';
if ( null !== pedir( '' ) )   $fallos[] = 'la peticion vacia se redirige';

/* Y lo que no esta en el mapa se queda quieto. */
foreach ( [ '/login/', '/preguntas-frecuentes/', '/blog/', '/inicio-de-sesion/', '/privacidad/' ] as $quieta ) {
    if ( isset( $mapa[ $quieta ] ) ) continue;
    if ( null !== pedir( $quieta ) ) $fallos[] = "$quieta se redirige y no esta en el mapa";
}

// ── Informe ──────────────────────────────────────────────────────────────────
if ( $fallos ) {
    echo '  x redirecciones (' . count( $mapa ) . " en el mapa)\n";
    foreach ( $fallos as $f ) echo "      $f\n";
    exit( 1 );
}

echo '  ok redirecciones (' . count( $mapa ) . " medidas)\n";
