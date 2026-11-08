<?php
/**
 * El trato con Stripe, sin SDK.
 *
 * El plan original decía «composer require stripe/stripe-php». Acá no hay
 * composer ni vendor, y el despliegue es un rsync del repositorio: meter un
 * directorio de dependencias significa commitearlo entero o agregar un paso de
 * build al CI, y las dos cosas son más caras que las cuatro llamadas HTTP que
 * de verdad hacen falta. La API de Stripe es HTTP con parámetros de formulario.
 *
 * Lo único que no se puede improvisar es la firma del webhook: sin ella,
 * cualquiera que sepa la URL puede decir que alguien pagó. Va implementada
 * según la especificación y con comparación en tiempo constante.
 *
 * Las claves no viven acá. Se escriben en wp-admin → Vitrinexo → Ajustes y se
 * leen de opciones. Si faltan, todo esto dice que no está configurado en vez de
 * fallar a medias.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

final class VX_Stripe {

    private const BASE      = 'https://api.stripe.com/v1/';
    private const VERSION   = '2024-06-20';
    /** Margen del reloj para la firma, en segundos. El que recomienda Stripe. */
    private const TOLERANCIA = 300;

    public static function clave_secreta(): string {
        return trim( (string) get_option( 'vx_stripe_secret_key', '' ) );
    }

    public static function secreto_webhook(): string {
        return trim( (string) get_option( 'vx_stripe_webhook_secret', '' ) );
    }

    /** Si hay con qué hablarle a Stripe. */
    public static function configurado(): bool {
        return '' !== self::clave_secreta();
    }

    /**
     * Una llamada a la API. Devuelve el cuerpo ya decodificado, o WP_Error.
     *
     * Stripe recibe parámetros de formulario, también los anidados, que van
     * con la notación corchete: metadata[vx_user_id]. http_build_query la
     * produce tal cual, así que no hace falta armarla a mano.
     */
    public static function llamar( string $ruta, array $params = [], string $metodo = 'POST' ) {
        $clave = self::clave_secreta();
        if ( '' === $clave ) {
            return new WP_Error( 'stripe_sin_configurar', 'Faltan las claves de Stripe en los ajustes.' );
        }

        $url  = self::BASE . ltrim( $ruta, '/' );
        $args = [
            'method'  => $metodo,
            'timeout' => 20,
            'headers' => [
                'Authorization'  => 'Bearer ' . $clave,
                'Stripe-Version' => self::VERSION,
                'Content-Type'   => 'application/x-www-form-urlencoded',
            ],
        ];

        if ( 'GET' === $metodo ) {
            $url .= $params ? '?' . http_build_query( $params ) : '';
        } else {
            $args['body'] = http_build_query( $params );
        }

        $r = wp_remote_request( $url, $args );
        if ( is_wp_error( $r ) ) return $r;

        $codigo = (int) wp_remote_retrieve_response_code( $r );
        $cuerpo = json_decode( (string) wp_remote_retrieve_body( $r ), true );

        if ( $codigo >= 200 && $codigo < 300 ) {
            return is_array( $cuerpo ) ? $cuerpo : [];
        }

        /* El mensaje de Stripe se guarda en el log pero no se le devuelve a
           quien llamó: puede traer detalles de la cuenta que no son suyos. */
        $detalle = $cuerpo['error']['message'] ?? 'sin detalle';
        error_log( "[vx_stripe] $metodo $ruta devolvio $codigo: $detalle" );
        return new WP_Error( 'stripe_error', 'Stripe respondió ' . $codigo, [ 'status' => 502 ] );
    }

    /**
     * Si la petición viene de verdad de Stripe.
     *
     * La cabecera trae «t=<marca>,v1=<firma>[,v1=<otra>]». Se firma la cadena
     * «<marca>.<cuerpo>» con el secreto y se compara con cada v1. Puede haber
     * más de una cuando el secreto está rotando, así que basta con que una
     * coincida.
     *
     * La marca se mira para que una petición capturada no se pueda repetir
     * mañana, y la comparación es hash_equals para no filtrar por el tiempo
     * que tarda en fallar.
     */
    public static function firma_valida( string $cuerpo, string $cabecera, string $secreto, ?int $ahora = null ): bool {
        if ( '' === $secreto || '' === $cabecera ) return false;

        $marca = null;
        $firmas = [];
        foreach ( explode( ',', $cabecera ) as $parte ) {
            $trozo = explode( '=', trim( $parte ), 2 );
            if ( 2 !== count( $trozo ) ) continue;
            if ( 't' === $trozo[0] )  $marca = (int) $trozo[1];
            if ( 'v1' === $trozo[0] ) $firmas[] = $trozo[1];
        }

        if ( null === $marca || ! $firmas ) return false;

        $ahora = $ahora ?? time();
        if ( abs( $ahora - $marca ) > self::TOLERANCIA ) return false;

        $esperada = hash_hmac( 'sha256', $marca . '.' . $cuerpo, $secreto );
        foreach ( $firmas as $f ) {
            if ( hash_equals( $esperada, $f ) ) return true;
        }
        return false;
    }

    /**
     * Si este evento ya se proceso.
     *
     * Stripe reintenta cuando no recibe un 200, y puede entregar el mismo
     * evento mas de una vez aunque todo haya ido bien. Procesar dos veces un
     * pago renovaria el vencimiento dos veces. Se recuerdan los identificadores
     * vistos durante tres dias, que es mas que la ventana de reintentos.
     */
    public static function evento_repetido( string $id ): bool {
        if ( '' === $id ) return false;
        $clave = 'vx_stripe_evt_' . md5( $id );
        if ( get_transient( $clave ) ) return true;
        set_transient( $clave, 1, 3 * DAY_IN_SECONDS );
        return false;
    }
}
