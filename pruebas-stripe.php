<?php
/**
 * Que nadie pueda decir que pago sin haber pagado.
 *
 * El webhook de Stripe es una URL publica por necesidad: Stripe tiene que
 * poder llamarla sin sesion. Lo unico que separa un pago real de alguien que
 * supo la direccion y mando un JSON es la firma de la cabecera. Si esa
 * comprobacion esta mal, cualquiera se activa el plan gratis.
 *
 * Lo que se mide:
 *   - la firma buena pasa;
 *   - el secreto equivocado no pasa;
 *   - el cuerpo alterado no pasa, aunque la firma sea la del cuerpo original;
 *   - una peticion vieja no pasa, que es el ataque de repeticion: capturar una
 *     llamada legitima y reenviarla manana;
 *   - una peticion del futuro tampoco;
 *   - sin cabecera, sin secreto, sin marca de tiempo o sin firma, no pasa;
 *   - con varias firmas, basta que una sirva, porque asi es como Stripe rota
 *     el secreto sin cortar el servicio;
 *   - y un evento repetido se reconoce, porque procesar dos veces una
 *     renovacion corre el vencimiento dos meses.
 *
 * Correr:  php pruebas-stripe.php
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opciones']   = [];
$GLOBALS['transients'] = [];

function get_option( $k, $d = false ) { return $GLOBALS['opciones'][ $k ] ?? $d; }
function get_transient( $k ) { return $GLOBALS['transients'][ $k ] ?? false; }
function set_transient( $k, $v, $t = 0 ) { $GLOBALS['transients'][ $k ] = $v; return true; }
function wp_remote_request( ...$a ) { return []; }
function wp_remote_retrieve_response_code( $r ) { return 200; }
function wp_remote_retrieve_body( $r ) { return '{}'; }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
class WP_Error { public function __construct( ...$a ) {} }

require_once __DIR__ . '/modules/pagos/class-vx-stripe.php';

$fallos = [];
function comprobar( string $nombre, callable $f ): void {
    global $fallos;
    $GLOBALS['transients'] = [];
    $r = $f();
    if ( null !== $r ) $fallos[] = "$nombre: $r";
}

/** Arma la cabecera tal como la manda Stripe. */
function cabecera( string $cuerpo, string $secreto, int $marca ): string {
    return 't=' . $marca . ',v1=' . hash_hmac( 'sha256', $marca . '.' . $cuerpo, $secreto );
}

const SECRETO = 'whsec_pruebaNoEsUnSecretoReal';
const CUERPO  = '{"id":"evt_1","type":"checkout.session.completed"}';

comprobar( 'la firma buena pasa', function () {
    $t = time();
    return VX_Stripe::firma_valida( CUERPO, cabecera( CUERPO, SECRETO, $t ), SECRETO, $t )
        ? null : 'rechazo una firma correcta';
} );

comprobar( 'el secreto equivocado no pasa', function () {
    $t = time();
    return VX_Stripe::firma_valida( CUERPO, cabecera( CUERPO, 'whsec_otro', $t ), SECRETO, $t )
        ? 'acepto una firma hecha con otro secreto' : null;
} );

comprobar( 'el cuerpo alterado no pasa', function () {
    $t    = time();
    $firma = cabecera( CUERPO, SECRETO, $t );            // firma del cuerpo original
    $otro  = '{"id":"evt_1","type":"checkout.session.completed","trampa":true}';
    return VX_Stripe::firma_valida( $otro, $firma, SECRETO, $t )
        ? 'acepto un cuerpo distinto del que estaba firmado' : null;
} );

comprobar( 'una peticion vieja no pasa', function () {
    $t = time() - 3600;                                   // de hace una hora
    return VX_Stripe::firma_valida( CUERPO, cabecera( CUERPO, SECRETO, $t ), SECRETO, time() )
        ? 'acepto una peticion de hace una hora: se puede repetir una llamada capturada' : null;
} );

comprobar( 'una peticion del futuro no pasa', function () {
    $t = time() + 3600;
    return VX_Stripe::firma_valida( CUERPO, cabecera( CUERPO, SECRETO, $t ), SECRETO, time() )
        ? 'acepto una marca de tiempo adelantada una hora' : null;
} );

comprobar( 'sin cabecera no pasa', function () {
    return VX_Stripe::firma_valida( CUERPO, '', SECRETO ) ? 'acepto sin cabecera' : null;
} );

comprobar( 'sin secreto configurado no pasa, ni firmando con el vacio', function () {
    $t = time();

    // Firmada con el secreto bueno: la rechaza cualquier implementacion, porque
    // el hmac no cuadra. Esto solo no prueba nada.
    if ( VX_Stripe::firma_valida( CUERPO, cabecera( CUERPO, SECRETO, $t ), '', $t ) ) {
        return 'acepto una firma ajena con el secreto vacio';
    }

    // Lo que de verdad hay que impedir: si el sitio no tiene secreto, cualquiera
    // puede firmar CON EL VACIO y el hmac cuadra perfecto. Sin la guarda, un
    // Vitrinexo recien instalado le activa el plan a quien se lo pida.
    if ( VX_Stripe::firma_valida( CUERPO, cabecera( CUERPO, '', $t ), '', $t ) ) {
        return 'acepto una firma hecha con el secreto vacio: un sitio sin configurar regala los planes';
    }
    return null;
} );

comprobar( 'sin marca de tiempo no pasa', function () {
    $solo = 'v1=' . hash_hmac( 'sha256', time() . '.' . CUERPO, SECRETO );
    return VX_Stripe::firma_valida( CUERPO, $solo, SECRETO ) ? 'acepto sin t=' : null;
} );

comprobar( 'sin firma no pasa', function () {
    return VX_Stripe::firma_valida( CUERPO, 't=' . time(), SECRETO ) ? 'acepto sin v1=' : null;
} );

comprobar( 'con el secreto rotando, basta una firma buena', function () {
    $t = time();
    $mala  = hash_hmac( 'sha256', $t . '.' . CUERPO, 'whsec_viejo' );
    $buena = hash_hmac( 'sha256', $t . '.' . CUERPO, SECRETO );
    $cab   = 't=' . $t . ',v1=' . $mala . ',v1=' . $buena;
    return VX_Stripe::firma_valida( CUERPO, $cab, SECRETO, $t )
        ? null : 'rechazo una cabecera con dos firmas donde la segunda era buena';
} );

comprobar( 'un evento repetido se reconoce', function () {
    if ( VX_Stripe::evento_repetido( 'evt_123' ) ) return 'dijo repetido la primera vez';
    if ( ! VX_Stripe::evento_repetido( 'evt_123' ) ) return 'no reconocio el mismo evento la segunda vez';
    if ( VX_Stripe::evento_repetido( 'evt_otro' ) ) return 'confundio dos eventos distintos';
    return null;
} );

comprobar( 'sin claves, no se habla con Stripe', function () {
    $GLOBALS['opciones'] = [];
    if ( VX_Stripe::configurado() ) return 'se dio por configurado sin clave';
    $r = VX_Stripe::llamar( 'customers', [] );
    return is_wp_error( $r ) ? null : 'intento llamar a Stripe sin clave secreta';
} );

// ── Informe ──────────────────────────────────────────────────────────────────
if ( $fallos ) {
    echo "  x stripe\n";
    foreach ( $fallos as $f ) echo "      $f\n";
    exit( 1 );
}
echo "  ok stripe (12 reglas de la firma del webhook)\n";
