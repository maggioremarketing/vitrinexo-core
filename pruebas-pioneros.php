<?php
/**
 * Que el programa Pionero no reparta mas distintivos de los que promete.
 *
 * La portada promete «100» y lleva un contador. El distintivo ahora significa
 * gratis para siempre, asi que cada uno de mas es una cuenta que no paga nunca
 * y una promesa publica que deja de cuadrar.
 *
 * Habia dos puertas al programa y solo una tenia llave: la aprobacion contaba
 * los activos contra un 100 escrito a mano, y el final del onboarding escribia
 * el distintivo sin mirar nada. El miembro 130 salia Pionero igual.
 *
 * Lo que se mide:
 *   - con cupo libre, se da;
 *   - lleno, no se da;
 *   - quien ya lo tiene no lo pierde aunque este cerrado, porque el distintivo
 *     es permanente y esa promesa es anterior al cupo;
 *   - la decision no se toma con el numero cacheado;
 *   - y lo que mas importa: NADIE escribe el distintivo fuera de la puerta.
 *     Eso es lo que caza la tercera puerta el dia que alguien la abra.
 *
 * Correr:  php pruebas-pioneros.php
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );

// ── Lo minimo de WordPress ───────────────────────────────────────────────────
$GLOBALS['meta']       = [];
$GLOBALS['pioneros']   = 0;   // cuantos devuelve la consulta
$GLOBALS['transients'] = [];
$GLOBALS['consultas']  = 0;   // cuantas veces se pregunto de verdad

function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['meta'][ $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['meta'][ $id ][ $k ] = $v; return true; }
function get_transient( $k )  { return $GLOBALS['transients'][ $k ] ?? false; }
function set_transient( $k, $v, $t = 0 ) { $GLOBALS['transients'][ $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['transients'][ $k ] ); return true; }
function apply_filters( $hook, $valor ) { return $valor; }

class WP_User_Query {
    public function __construct( $args ) { $GLOBALS['consultas']++; }
    public function get_total() { return $GLOBALS['pioneros']; }
}

class VX_User_Meta { const ES_FUNDADOR = 'vx_es_fundador'; const ESTADO = 'vx_estado'; }

require_once __DIR__ . '/helpers/helper-pioneros.php';

$fallos = [];

function comprobar( string $nombre, callable $f ): void {
    global $fallos;
    $GLOBALS['meta'] = [];
    $GLOBALS['transients'] = [];
    $GLOBALS['consultas'] = 0;
    $r = $f();
    if ( null !== $r ) $fallos[] = "$nombre: $r";
}

comprobar( 'con cupo libre se da el distintivo', function () {
    $GLOBALS['pioneros'] = 44;
    if ( ! vx_pioneros_dar_distintivo( 7 ) ) return 'dijo que no con 44 de 100';
    return ! empty( $GLOBALS['meta'][7][ VX_User_Meta::ES_FUNDADOR ] ) ? null : 'dijo que si pero no lo escribio';
} );

comprobar( 'lleno no se da', function () {
    $GLOBALS['pioneros'] = 100;
    if ( vx_pioneros_dar_distintivo( 7 ) ) return 'lo dio con el programa lleno';
    return empty( $GLOBALS['meta'][7][ VX_User_Meta::ES_FUNDADOR ] ) ? null : 'lo escribio igual';
} );

comprobar( 'pasado de cupo tampoco', function () {
    $GLOBALS['pioneros'] = 130;
    return vx_pioneros_dar_distintivo( 7 ) ? 'lo dio con 130 Pioneros' : null;
} );

comprobar( 'quien ya lo tiene no lo pierde', function () {
    $GLOBALS['pioneros'] = 100;
    $GLOBALS['meta'][7][ VX_User_Meta::ES_FUNDADOR ] = 1;
    if ( ! vx_pioneros_dar_distintivo( 7 ) ) return 'se lo quito a quien ya era Pionero';
    return ! empty( $GLOBALS['meta'][7][ VX_User_Meta::ES_FUNDADOR ] ) ? null : 'borro el distintivo';
} );

comprobar( 'la decision no se toma con el numero cacheado', function () {
    // El cache se llena por la portada, que lo pide en cada visita, y puede
    // quedar viejo por cosas que no pasan por la puerta: un administrador que
    // da un distintivo a mano, una cuenta que se desactiva. Asi que se ensucia
    // el cache a proposito, sin tocar la puerta, y se pregunta igual.
    //
    // La primera version de esta prueba daba el distintivo para llenar el
    // cache, pero la puerta lo borra al dar, asi que no llegaba a leer un
    // numero viejo nunca: medía la invalidacion y no la frescura, y pasaba en
    // verde con la puerta leyendo del cache.
    set_transient( 'vx_pioneros_aprobados', 44, 300 );   // lo que dice el cache
    $GLOBALS['pioneros'] = 100;                          // lo que es verdad
    return vx_pioneros_dar_distintivo( 8 ) ? 'uso el numero viejo y dio uno de mas' : null;
} );

comprobar( 'contar de nuevo despues de dar, para que el siguiente vea la verdad', function () {
    $GLOBALS['pioneros'] = 44;
    vx_pioneros_dar_distintivo( 7 );
    $antes = $GLOBALS['consultas'];
    vx_pioneros_dar_distintivo( 8 );
    return $GLOBALS['consultas'] > $antes ? null : 'no volvio a contar: se quedo con el cache';
} );

// ── La tercera puerta ────────────────────────────────────────────────────────
//
// El distintivo solo se escribe en un sitio. Si manana alguien agrega otro
// update_user_meta( ..., ES_FUNDADOR, ... ) fuera del helper, el cupo deja de
// valer y nadie se entera hasta que el contador de la portada pase de 100.
$puerta  = realpath( __DIR__ . '/helpers/helper-pioneros.php' );
$fuera   = [];

/* Las excepciones van escritas, con su motivo, y hay que agregarlas a mano.
   Esa friccion es el punto: una puerta nueva se declara o falla.

   El panel de usuarios puede dar y quitar el distintivo de a una persona, con
   nonce y permiso de administrador. Eso es una decision humana caso por caso,
   la misma escala que ponerle fecha a una cuenta concreta. Lo que no existe, y
   no debe volver a existir, es algo que lo haga a todos de golpe. */
$excepciones = [
    'modules/admin/class-vx-admin-users.php' => 'un administrador decide de a uno, con nonce y permiso',
];
$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( __DIR__ ) );
foreach ( $it as $archivo ) {
    if ( $archivo->getExtension() !== 'php' ) continue;
    $ruta = $archivo->getPathname();
    if ( str_contains( $ruta, '/banco/' ) || str_contains( $ruta, '/migraciones/' ) ) continue;
    if ( str_starts_with( basename( $ruta ), 'pruebas-' ) ) continue;
    if ( realpath( $ruta ) === $puerta ) continue;

    foreach ( file( $ruta ) as $n => $linea ) {
        if ( ! preg_match( '/update_user_meta\s*\([^;]*(ES_FUNDADOR|vx_es_fundador)/', $linea ) ) continue;
        $rel = str_replace( __DIR__ . '/', '', $ruta );
        if ( isset( $excepciones[ $rel ] ) ) continue;
        $fuera[] = $rel . ':' . ( $n + 1 );
    }
}
foreach ( $fuera as $d ) {
    $fallos[] = "el distintivo se escribe fuera de la puerta, en $d: el cupo deja de valer ahi";
}

// ── Informe ──────────────────────────────────────────────────────────────────
if ( $fallos ) {
    echo "  x pioneros\n";
    foreach ( $fallos as $f ) echo "      $f\n";
    exit( 1 );
}

echo "  ok pioneros (6 reglas, cupo " . vx_pioneros_cupo() . ", una puerta y "
   . count( $excepciones ) . " excepcion" . ( 1 === count( $excepciones ) ? '' : 'es' ) . " declarada"
   . ( 1 === count( $excepciones ) ? '' : 's' ) . ")\n";
