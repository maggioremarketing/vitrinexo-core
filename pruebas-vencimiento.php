<?php
/**
 * Que el vencimiento del plan sea uno solo: el que está guardado.
 *
 * El meta vx_plan_vencimiento es un timestamp y 0 significa sin vencimiento.
 * Lo escribe la aprobación, tres meses desde ese día, y lo puede corregir un
 * administrador. Todo lo demás lo lee.
 *
 * Había dos formas de romper eso y las dos estaban pasando. La aprobación
 * guardaba 'Y-m-d' mientras cada lector hacía (int), así que '2026-02-07'
 * quedaba en 2026, una fecha de 1970: el panel mostraba un guión y el cron de
 * vencimientos daba el plan por vencido. Y la columna del panel, cuando no
 * encontraba fecha, calculaba la suya desde el registro y la guardaba mientras
 * se dibujaba, así que la tabla inventaba un vencimiento que nadie había
 * decidido y lo dejaba escrito. Para quien se aprobó semanas después de
 * registrarse, esa fecha caía antes de la real.
 *
 * Se prueba corriendo el código de verdad contra un banco de user meta en
 * memoria, porque las dos fallas estaban en qué se guarda y en qué se lee, no
 * en cómo está escrito el archivo.
 *
 * No hay suite todavía en este repositorio, así que corre sola y sin
 * dependencias:  php pruebas-vencimiento.php
 */

date_default_timezone_set( 'America/Santiago' );

define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );

// ── Banco de user meta en memoria ───────────────────────────────────────────
// $escrituras anota cada update_user_meta: es la única forma de comprobar que
// dibujar una celda no guarda nada.
$GLOBALS['meta']       = [];
$GLOBALS['usuarios']   = [];
$GLOBALS['escrituras'] = [];

function get_user_meta( $uid, $key, $single = false ) {
    $v = $GLOBALS['meta'][ $uid ][ $key ] ?? '';
    return $single ? $v : [ $v ];
}
function update_user_meta( $uid, $key, $value ) {
    $GLOBALS['meta'][ $uid ][ $key ] = $value;
    $GLOBALS['escrituras'][] = [ $uid, $key, $value ];
    return true;
}
function delete_user_meta( $uid, $key ) { unset( $GLOBALS['meta'][ $uid ][ $key ] ); return true; }
function metadata_exists( $tipo, $uid, $key ) { return array_key_exists( $key, $GLOBALS['meta'][ $uid ] ?? [] ); }
function get_userdata( $uid ) { return $GLOBALS['usuarios'][ $uid ] ?? false; }
function get_users( $args = [] ) { return []; }

// ── Lo mínimo de WordPress para que los archivos carguen y dibujen ──────────
function date_i18n( $formato, $ts = null ) { return date( $formato, $ts ?? time() ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return (string) $s; }
function esc_js( $s ) { return (string) $s; }
function admin_url( $ruta = '' ) { return 'https://vitrinexo.com/wp-admin/' . $ruta; }
function home_url( $ruta = '' ) { return 'https://vitrinexo.com' . $ruta; }
function wp_nonce_url( $url, $accion ) { return $url . '&_wpnonce=prueba'; }
function wp_create_nonce( $accion ) { return 'prueba'; }
function wp_nonce_field( $a = -1, $n = '_wpnonce', $r = true, $e = true ) { return ''; }
function add_action( ...$a ) {}
function add_filter( ...$a ) {}
function do_action( ...$a ) {}
function current_user_can( ...$a ) { return true; }
function check_ajax_referer( ...$a ) { return true; }
function sanitize_text_field( $s ) { return $s; }
function wp_unslash( $s ) { return $s; }
function wp_send_json_error( ...$a ) {}
function wp_send_json_success( ...$a ) {}

class VX_User  { public static function get( $uid ) { return null; } }
class VX_Stats { public static function get_sol_recibidas( $uid ) { return 0; }
                 public static function get_conexiones( $uid ) { return 0; } }

require_once __DIR__ . '/modules/users/class-vx-user-meta.php';
require_once __DIR__ . '/modules/membership/class-vx-membership-meta.php';
require_once __DIR__ . '/modules/membership/class-vx-membership.php';
require_once __DIR__ . '/modules/users/class-vx-verification.php';
require_once __DIR__ . '/modules/admin/class-vx-admin-users.php';

const VENC = VX_User_Meta::PLAN_VENCIMIENTO;

$fallos = [];
function comprobar( string $nombre, callable $caso ): void {
    $GLOBALS['meta'] = $GLOBALS['usuarios'] = $GLOBALS['escrituras'] = [];
    $problema = $caso();
    if ( $problema ) $GLOBALS['fallos'][] = "$nombre: $problema";
}

/** Un miembro registrado hace $dias días, sin vencimiento guardado. */
function miembro( int $uid, int $dias ): void {
    $GLOBALS['usuarios'][ $uid ] = (object) [
        'ID'              => $uid,
        'roles'           => [ 'subscriber' ],
        'user_registered' => date( 'Y-m-d H:i:s', time() - $dias * DAY_IN_SECONDS ),
    ];
}
function celda( int $uid ): string {
    return VX_Admin_Users::render_column( '', 'vx_vencimiento', $uid );
}
function dia( int $ts ): string { return date( 'Y-m-d', $ts ); }
function en_tres_meses( int $desde ): string {
    return ( new DateTime( '@' . $desde ) )->setTimezone( new DateTimeZone( 'America/Santiago' ) )
        ->modify( '+3 months' )->format( 'Y-m-d' );
}

// ── La aprobación escribe un timestamp, no una fecha escrita ────────────────

comprobar( 'la aprobación guarda un timestamp', function () {
    miembro( 1, 0 );
    VX_Verification::activate_account( 1 );
    $v = $GLOBALS['meta'][1][ VENC ] ?? null;
    if ( ! is_int( $v ) ) return 'guardó ' . var_export( $v, true ) . ', que no es un timestamp';
    if ( (int) $v !== (int) (string) $v ) return "guardó $v, que no sobrevive un (int)";
    return null;
} );

comprobar( 'la aprobación vence a los tres meses', function () {
    miembro( 1, 0 );
    VX_Verification::activate_account( 1 );
    $esperado = en_tres_meses( time() );
    $visto    = dia( (int) $GLOBALS['meta'][1][ VENC ] );
    return $visto === $esperado ? null : "venció el $visto y no el $esperado";
} );

comprobar( 'la aprobación respeta una fecha ya guardada', function () {
    miembro( 1, 0 );
    $GLOBALS['meta'][1][ VENC ] = 1893456000;
    VX_Verification::activate_account( 1 );
    return 1893456000 === $GLOBALS['meta'][1][ VENC ] ? null : 'pisó la fecha que ya estaba';
} );

comprobar( 'la aprobación respeta el sin vencimiento', function () {
    miembro( 1, 0 );
    $GLOBALS['meta'][1][ VENC ] = 0;   // sin vencimiento, decidido a mano
    VX_Verification::activate_account( 1 );
    return 0 === $GLOBALS['meta'][1][ VENC ] ? null : 'le puso fecha a un plan sin vencimiento';
} );

// ── La columna del panel lee, y solo lee ────────────────────────────────────

comprobar( 'el panel muestra la fecha guardada', function () {
    miembro( 1, 0 );
    VX_Verification::activate_account( 1 );
    $guardada = date( 'd/m/Y', (int) $GLOBALS['meta'][1][ VENC ] );
    return str_contains( celda( 1 ), $guardada ) ? null : "no muestra $guardada";
} );

comprobar( 'el panel no cuenta desde el registro', function () {
    // El caso que se veía mal: registrado hace 60 días, aprobado hoy. Contando
    // desde el registro la fecha cae antes de la real, nunca después.
    miembro( 1, 60 );
    VX_Verification::activate_account( 1 );
    $desde_el_registro = date( 'd/m/Y', strtotime( $GLOBALS['usuarios'][1]->user_registered ) + 90 * DAY_IN_SECONDS );
    $real              = date( 'd/m/Y', (int) $GLOBALS['meta'][1][ VENC ] );
    $celda             = celda( 1 );
    if ( str_contains( $celda, $desde_el_registro ) ) return "muestra $desde_el_registro, contada desde el registro";
    return str_contains( $celda, $real ) ? null : "no muestra $real";
} );

comprobar( 'dibujar la celda no guarda nada', function () {
    miembro( 1, 60 );   // nunca aprobado: es cuando la celda calculaba y guardaba
    celda( 1 );
    if ( $GLOBALS['escrituras'] ) return 'escribió ' . count( $GLOBALS['escrituras'] ) . ' meta al dibujarse';
    return metadata_exists( 'user', 1, VENC ) ? 'dejó el vencimiento escrito' : null;
} );

comprobar( 'sin fecha guardada la celda lo dice', function () {
    miembro( 1, 60 );
    $celda = celda( 1 );
    return str_contains( $celda, 'estimado' ) ? null : 'no avisa que la fecha es estimada';
} );

comprobar( 'un plan sin vencimiento no sale estimado', function () {
    miembro( 1, 60 );
    $GLOBALS['meta'][1][ VENC ] = 0;
    $celda = celda( 1 );
    if ( str_contains( $celda, 'estimado' ) ) return 'trata el 0 como si no hubiera fecha';
    return str_contains( $celda, 'Sin vencimiento' ) ? null : 'no dice que el plan no vence';
} );

// ── Lo que quedó guardado como fecha escrita se sigue leyendo ───────────────

comprobar( 'una fecha escrita de antes se lee como ese día', function () {
    $fecha = date( 'Y-m-d', time() + 40 * DAY_IN_SECONDS );
    $ts    = VX_Membership::parse_expiry( $fecha );
    if ( dia( $ts ) !== $fecha ) return "leyó $fecha como " . dia( $ts );
    return $ts > time() ? null : "leyó $fecha como una fecha ya vencida";
} );

comprobar( 'el panel muestra una fecha escrita de antes', function () {
    miembro( 1, 60 );
    $GLOBALS['meta'][1][ VENC ] = date( 'Y-m-d', time() + 40 * DAY_IN_SECONDS );
    $esperada = date( 'd/m/Y', time() + 40 * DAY_IN_SECONDS );
    $celda    = celda( 1 );
    if ( str_contains( $celda, 'estimado' ) ) return 'la dio por ausente';
    return str_contains( $celda, $esperada ) ? null : "no muestra $esperada";
} );

comprobar( 'la membresía no da por vencida una fecha escrita', function () {
    $GLOBALS['meta'][1][ VX_Membership_Meta::PLAN_ESTADO ] = 'activo';
    $GLOBALS['meta'][1][ VENC ] = date( 'Y-m-d', time() + 40 * DAY_IN_SECONDS );
    $m = VX_Membership::get( 1 );
    if ( $m->is_expired() ) return 'la leyó como vencida';
    return $m->is_active() ? null : 'dejó la membresía inactiva';
} );

comprobar( 'el 0 sigue siendo sin vencimiento', function () {
    $GLOBALS['meta'][1][ VENC ] = 0;
    if ( 0 !== VX_Membership::parse_expiry( 0 ) )   return 'el 0 dejó de ser 0';
    if ( 0 !== VX_Membership::parse_expiry( '' ) )  return 'una cadena vacía no es sin vencimiento';
    return null === VX_Membership::get( 1 )->days_until_expiry() ? null : 'le contó días a un plan sin vencimiento';
} );

if ( $fallos ) {
    echo "  x vencimiento\n";
    foreach ( $fallos as $f ) echo "      $f\n";
    exit( 1 );
}
echo "  ok vencimiento\n";
