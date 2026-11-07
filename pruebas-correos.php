<?php
/**
 * Que los correos hablen como habla la marca.
 *
 * La prueba de vocabulario del tema mide las palabras prohibidas y la regla de
 * la marca madre sobre la pagina renderizada. Los correos no son una pagina:
 * no los sirve el sitio, no los abre Playwright y hasta aca no los medía
 * nadie. Son, ademas, el texto que mas lejos llega, porque le llega a cada
 * miembro a su bandeja sin que entre al sitio.
 *
 * Mientras no se midieron, el correo de bienvenida le decia a cada miembro
 * nuevo que los tags son «la clave del sistema de matches», seis commits
 * despues de que «match» saliera del area de miembros.
 *
 * Se mide sobre el HTML renderizado y no sobre el archivo, por lo mismo que en
 * el tema: `match ( true )` es una expresion de PHP y `offers_matches` es una
 * clave de datos. Prohibirlos en el codigo solo enseñaria a ignorar la prueba.
 *
 * Correr:  php pruebas-correos.php
 */

date_default_timezone_set( 'America/Santiago' );

define( 'ABSPATH', __DIR__ . '/' );
define( 'VX_PLUGIN_URL', 'https://vitrinexo.com/wp-content/plugins/vitrinexo-core/' );
define( 'VX_REST_NAMESPACE', 'vitrinexo/v1' );

// ── Lo minimo de WordPress que las plantillas tocan ──────────────────────────
function esc_html( $t )     { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $t )     { return esc_html( $t ); }
function esc_url( $t )      { return (string) $t; }
function esc_js( $t )       { return esc_html( $t ); }
function wp_kses_post( $t ) { return (string) $t; }
function home_url( $p = '' )  { return 'https://vitrinexo.com' . $p; }
function admin_url( $p = '' ) { return 'https://vitrinexo.com/wp-admin/' . $p; }
function rest_url( $p = '' )  { return 'https://vitrinexo.com/wp-json/' . $p; }
function wp_date( $f, $ts = null )   { return date( $f, $ts ?? time() ); }
function date_i18n( $f, $ts = null ) { return date( $f, $ts ?? time() ); }
// WordPress la llama de dos formas: (clave, valor, url) y (array, url).
function add_query_arg( ...$a ) {
    $url  = array_pop( $a );
    $pares = is_array( $a[0] ) ? $a[0] : [ $a[0] => $a[1] ];
    $q = [];
    foreach ( $pares as $k => $v ) $q[] = $k . '=' . rawurlencode( (string) $v );
    return $url . '?' . implode( '&', $q );
}
function get_user_meta( $u, $k, $s = false ) { return ''; }
function get_empresa_activa( $u ) { return null; }

require_once __DIR__ . '/modules/users/class-vx-user-meta.php';
require_once __DIR__ . '/helpers/helper-profile-fields.php';
require_once __DIR__ . '/modules/email/class-vx-email-templates.php';

// Las reglas viven en pruebas-comun-voz.php, que las comparte con las otras
// pruebas de copy del plugin.
require_once __DIR__ . '/pruebas-comun-voz.php';

/**
 * Datos de relleno para todas las plantillas a la vez.
 *
 * Una sola bolsa y no una por plantilla: lo que se mide es el texto fijo, y
 * una bolsa por plantilla seria una lista que hay que acordarse de ampliar.
 * Los valores llevan nombres que no son copy, para que un fallo nunca salga
 * del relleno.
 */
$DATOS = [
    'nombre' => 'Ana', 'apellido' => 'Reyes', 'nombre_completo' => 'Ana Reyes',
    'usuario_nombre' => 'Ana Reyes', 'receptor_nombre' => 'Ana Reyes',
    'emisor_nombre' => 'Bruno Diaz', 'emisor_empresa' => 'Acme',
    'emisor_perfil_url' => 'https://vitrinexo.com/perfil/bruno-diaz/',
    'email' => 'ana@ejemplo.cl', 'email_usuario' => 'ana@ejemplo.cl',
    'email_actual' => 'ana@ejemplo.cl', 'email_nuevo' => 'ana2@ejemplo.cl',
    'empresa' => 'Acme', 'cargo' => 'Gerenta', 'pais' => 'Chile',
    'telefono' => '+56 9 0000 0000', 'linkedin' => 'https://linkedin.com/in/x',
    'pitch' => 'Texto de relleno.', 'mensaje' => 'Texto de relleno.',
    'intro' => 'Texto de relleno.', 'contacto' => 'Texto de relleno.',
    'link' => 'https://vitrinexo.com/x/', 'reset_link' => 'https://vitrinexo.com/x/',
    'token' => 'aaa', 'token_aceptar' => 'aaa', 'token_rechazar' => 'bbb',
    'url_aceptar' => 'https://vitrinexo.com/x/', 'url_rechazar' => 'https://vitrinexo.com/x/',
    'url_aprobar' => 'https://vitrinexo.com/x/', 'url_actualizar' => 'https://vitrinexo.com/x/',
    'url_renovar' => 'https://vitrinexo.com/x/', 'approve_url' => 'https://vitrinexo.com/x/',
    'validaciones_url' => 'https://vitrinexo.com/x/',
    'plan' => 'Miembro', 'days_left' => 5, 'fecha_vencimiento' => '31/12/2026',
    'fecha_intento' => '01/12/2026', 'es_fundador' => true, 'user_id' => 1,
    'n_cuentas' => 2, 'n_senior' => 1,
    'lista_cuentas' => [ [ 'nombre' => 'Ana Reyes', 'email' => 'ana@ejemplo.cl', 'fecha' => '01/12/2026' ] ],
    'lista_senior'  => [ [ 'nombre' => 'Ana Reyes', 'email' => 'ana@ejemplo.cl', 'industria' => 'Banca' ] ],
    'campos' => [ 'cargo' => 'Gerenta', 'empresa' => 'Acme' ],
    'mesa_nombre' => 'Mesa 1',
    'comensales' => [ [ 'nombre' => 'Bruno Diaz', 'empresa' => 'Acme', 'cargo' => 'Gerente' ] ],
    // El resumen semanal devuelve '' cuando no hay a quien mostrar, y una
    // plantilla vacia no se mide. Con gente adentro, se mide.
    'offers_matches' => [ [ 'nombre' => 'Bruno Diaz', 'empresa' => 'Acme' ] ],
    'seeks_matches'  => [ [ 'nombre' => 'Caro Lira',  'empresa' => 'Acme' ] ],
    'dinner' => [
        'ciudad' => 'Santiago', 'fecha' => 1800000000,
        'restaurante' => 'Casa Lastarria', 'direccion' => 'Lastarria 1',
    ],
];

// ── El examen ────────────────────────────────────────────────────────────────
$clase = new ReflectionClass( 'VX_Email_Templates' );
$plantillas = [];
foreach ( $clase->getMethods() as $m ) {
    if ( 0 === strpos( $m->getName(), 'tpl_' ) ) $plantillas[] = substr( $m->getName(), 4 );
}
sort( $plantillas );

$fallos = [];

if ( count( $plantillas ) < 20 ) {
    $fallos[] = 'solo se encontraron ' . count( $plantillas ) . ' plantillas: la reflexion no esta viendo la clase';
}

foreach ( $plantillas as $nombre ) {
    $html = VX_Email_Templates::render( $nombre, $DATOS );

    // Una plantilla que devuelve vacio no se midio, y dar por buena una
    // plantilla que no se abrio es la peor forma de pasar en verde.
    if ( '' === trim( $html ) ) {
        $fallos[] = "$nombre no devolvio nada: no se midio";
        continue;
    }

    $fallos = array_merge( $fallos, vx_voz_revisar( $nombre, $html ) );
}

// ── Informe ──────────────────────────────────────────────────────────────────
if ( $fallos ) {
    echo "  x correos (" . count( $plantillas ) . " plantillas)\n";
    foreach ( $fallos as $f ) echo "      $f\n";
    exit( 1 );
}

echo "  ok correos (" . count( $plantillas ) . " plantillas medidas)\n";
