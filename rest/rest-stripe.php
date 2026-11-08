<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ════════════════════════════════════════════════════════════════════════════
 *  REST API — Stripe
 *
 *  Los cuatro endpoints están implementados y hablan con Stripe por HTTP, sin
 *  SDK: acá no hay composer ni vendor, y el despliegue es un rsync del
 *  repositorio, así que una carpeta de dependencias costaría más que las
 *  llamadas que de verdad hacen falta. El cliente vive en
 *  modules/pagos/class-vx-stripe.php.
 *
 *  Lo único que falta para cobrar son las claves, y no se escriben acá: van en
 *  wp-admin → Vitrinexo → Ajustes, y el manual de activación está en
 *  wp-admin → Vitrinexo → Manual de Stripe. Sin claves, todo esto responde
 *  «no configurado» en vez de fallar a medias.
 *
 *  La firma del webhook es lo único que separa un pago real de alguien que
 *  supo la URL: tiene su propia prueba en pruebas-stripe.php, verificada
 *  rompiéndola.
 * ════════════════════════════════════════════════════════════════════════════
 */

add_action( 'rest_api_init', function () {

    // GET /stripe/planes — precios disponibles según el usuario
    register_rest_route( VX_REST_NAMESPACE, '/stripe/planes', [
        'methods'             => 'GET',
        'callback'            => 'vx_rest_stripe_planes',
        'permission_callback' => 'is_user_logged_in',
    ] );

    // POST /stripe/checkout — crea sesión de pago
    register_rest_route( VX_REST_NAMESPACE, '/stripe/checkout', [
        'methods'             => 'POST',
        'callback'            => 'vx_rest_stripe_checkout',
        'permission_callback' => 'is_user_logged_in',
        'args' => [
            'plan' => [
                'required'          => true,
                'sanitize_callback' => 'sanitize_key',
                'validate_callback' => fn( $v ) => in_array( $v, [ 'mensual', 'anual' ], true ),
            ],
        ],
    ] );

    // POST /stripe/webhook — eventos de Stripe (no requiere auth — usa firma)
    register_rest_route( VX_REST_NAMESPACE, '/stripe/webhook', [
        'methods'             => 'POST',
        'callback'            => 'vx_rest_stripe_webhook',
        'permission_callback' => '__return_true',
    ] );

    // POST /stripe/portal — portal de facturación para gestionar suscripción
    register_rest_route( VX_REST_NAMESPACE, '/stripe/portal', [
        'methods'             => 'POST',
        'callback'            => 'vx_rest_stripe_portal',
        'permission_callback' => 'is_user_logged_in',
    ] );

} );

// ── Helpers de precios ────────────────────────────────────────────────────────

/**
 * Devuelve la configuración de precios desde las opciones de WordPress.
 * El admin las configura en Vitrinexo → Ajustes.
 *
 * @return array
 */
function vx_get_planes_config(): array {
    $moneda = strtoupper( get_option( 'vx_moneda', 'USD' ) );
    return [
        'mensual' => [
            'id'          => 'mensual',
            'nombre'      => 'Plan Mensual',
            'precio'      => (float) get_option( 'vx_precio_mensual', 29 ),
            'moneda'      => $moneda,
            'intervalo'   => 'mes',
            'stripe_price_id' => get_option( 'vx_stripe_price_mensual', '' ),  // Stripe Price ID
            'descripcion' => 'Acceso completo al directorio, nexos, conexiones y Vitrinexo 4Dinner.',
        ],
        'anual' => [
            'id'          => 'anual',
            'nombre'      => 'Plan Anual',
            'precio'      => (float) get_option( 'vx_precio_anual', 249 ),
            'moneda'      => $moneda,
            'intervalo'   => 'año',
            'stripe_price_id' => get_option( 'vx_stripe_price_anual', '' ),
            'descripcion' => 'Todo el plan mensual + 2 meses gratis.',
            'ahorro'      => round( ( get_option( 'vx_precio_mensual', 29 ) * 12 ) - get_option( 'vx_precio_anual', 249 ) ),
        ],
    ];
}

// ── Endpoints ─────────────────────────────────────────────────────────────────

/**
 * GET /stripe/planes
 * Devuelve los planes disponibles: mensual y anual, los mismos para todos.
 *
 * Hubo un tercero, 'preferencial', que solo se le mostraba a los Pioneros. Ya
 * no existe: los primeros 100 son gratis para siempre y no tienen nada que
 * pagar, asi que un precio exclusivo para ellos no tenia a quien aplicarse.
 */
function vx_rest_stripe_planes(): WP_REST_Response
{
    $user_id   = get_current_user_id();
    $user      = VX_User::get( $user_id );
    $membresia = VX_Membership::get( $user_id );
    $planes    = vx_get_planes_config();

    return new WP_REST_Response( [
        'success'       => true,
        'planes'        => array_values( $planes ),
        'plan_actual'   => $membresia->get_plan(),
        'plan_estado'   => $membresia->get_plan_estado(),
        'vencimiento'   => $membresia->get_expiry(),
        'es_fundador'   => $user ? $user->is_founder() : false,
    ], 200 );
}

/**
 * POST /stripe/checkout
 * Crea una sesión de pago en Stripe y devuelve la URL de checkout.
 *
 * Retorna: { success: true, checkout_url: "https://checkout.stripe.com/..." }
 */
function vx_rest_stripe_checkout( WP_REST_Request $request ): WP_REST_Response
{
    $user_id = get_current_user_id();
    $user    = VX_User::get( $user_id );
    $plan_id = $request->get_param( 'plan' );
    $planes  = vx_get_planes_config();

    if ( ! isset( $planes[ $plan_id ] ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'plan_invalido' ], 400 );
    }

    $plan = $planes[ $plan_id ];

    if ( ! VX_Stripe::configurado() ) {
        return new WP_REST_Response( [
            'success' => false,
            'error'   => 'stripe_no_configurado',
            'message' => 'El sistema de pagos aún no está activado. Escríbenos a hola@vitrinexo.com y lo coordinamos.',
            'plan'    => $plan['nombre'],
            'precio'  => $plan['precio'] . ' ' . $plan['moneda'] . '/' . $plan['intervalo'],
        ], 503 );
    }

    if ( ! $user ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'sin_sesion' ], 401 );
    }

    /* Sin price id no hay nada que cobrar. Pasa cuando alguien puso las claves
       pero todavia no creo los productos en Stripe, y es mejor decirlo que
       mandar a la gente a una pantalla de pago rota. */
    if ( empty( $plan['stripe_price_id'] ) ) {
        return new WP_REST_Response( [
            'success' => false,
            'error'   => 'plan_sin_precio_en_stripe',
            'message' => 'Ese plan todavía no tiene precio configurado en Stripe.',
        ], 503 );
    }

    $membresia = VX_Membership::get( $user_id );
    $cliente   = $membresia->get_gateway_customer_id();

    if ( ! $cliente ) {
        $r = VX_Stripe::llamar( 'customers', [
            'email'                   => $user->get_email(),
            'name'                    => $user->get_nombre_completo(),
            'metadata[vx_user_id]'    => $user_id,
        ] );
        if ( is_wp_error( $r ) || empty( $r['id'] ) ) {
            return new WP_REST_Response( [ 'success' => false, 'error' => 'stripe_error' ], 502 );
        }
        $cliente = $r['id'];
        $membresia->set_gateway_customer_id( $cliente );
    }

    $sesion = VX_Stripe::llamar( 'checkout/sessions', [
        'customer'                  => $cliente,
        'mode'                      => 'subscription',
        'line_items[0][price]'      => $plan['stripe_price_id'],
        'line_items[0][quantity]'   => 1,
        'success_url'               => home_url( '/configuracion/?tab=plan&stripe=ok&session_id={CHECKOUT_SESSION_ID}' ),
        'cancel_url'                => home_url( '/configuracion/?tab=plan&stripe=cancelado' ),
        'metadata[vx_user_id]'      => $user_id,
        'metadata[vx_plan]'         => $plan_id,
        /* El plan tambien va en la suscripcion: el webhook de renovacion llega
           con la suscripcion y no con la sesion, y asi no hay que adivinarlo. */
        'subscription_data[metadata][vx_user_id]' => $user_id,
        'subscription_data[metadata][vx_plan]'    => $plan_id,
        'allow_promotion_codes'     => 'true',
        'client_reference_id'       => (string) $user_id,
    ] );

    if ( is_wp_error( $sesion ) || empty( $sesion['url'] ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'stripe_error' ], 502 );
    }

    return new WP_REST_Response( [ 'success' => true, 'checkout_url' => $sesion['url'] ], 200 );
}

/**
 * POST /stripe/webhook
 * Recibe y procesa eventos de Stripe (firma verificada).
 *
 * Eventos relevantes:
 *   checkout.session.completed → activa el plan
 *   customer.subscription.deleted → cancela el plan
 *   invoice.payment_failed → notifica al usuario
 *   invoice.payment_succeeded → renueva la fecha de vencimiento
 */
function vx_rest_stripe_webhook(): WP_REST_Response
{
    $payload = @file_get_contents( 'php://input' );
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

    /* Sin firma valida no se mira el cuerpo siquiera. Esta URL es publica por
       necesidad —Stripe tiene que poder llamarla sin sesion— asi que la firma
       es lo unico que separa un pago real de alguien que supo la direccion y
       dijo que pago. */
    if ( ! VX_Stripe::firma_valida( (string) $payload, (string) $sig_header, VX_Stripe::secreto_webhook() ) ) {
        return new WP_REST_Response( [ 'error' => 'firma_invalida' ], 400 );
    }

    $evento = json_decode( (string) $payload, true );
    if ( ! is_array( $evento ) || empty( $evento['type'] ) ) {
        return new WP_REST_Response( [ 'error' => 'cuerpo_invalido' ], 400 );
    }

    /* Stripe reintenta cuando no recibe un 200, y puede entregar el mismo
       evento mas de una vez aunque todo haya ido bien. Procesar dos veces una
       renovacion correria el vencimiento dos meses. */
    if ( VX_Stripe::evento_repetido( (string) ( $evento['id'] ?? '' ) ) ) {
        return new WP_REST_Response( [ 'received' => true, 'repetido' => true ], 200 );
    }

    $objeto = $evento['data']['object'] ?? [];

    switch ( $evento['type'] ) {

        case 'checkout.session.completed':
            $uid  = (int) ( $objeto['metadata']['vx_user_id'] ?? 0 );
            $plan = (string) ( $objeto['metadata']['vx_plan'] ?? '' );
            if ( ! $uid || ! $plan ) break;

            $membresia = VX_Membership::get( $uid );
            $membresia->activate( $plan, vx_stripe_vencimiento( $plan ) );
            if ( ! empty( $objeto['subscription'] ) ) {
                $membresia->set_gateway_subscription_id( (string) $objeto['subscription'] );
            }
            if ( ! empty( $objeto['customer'] ) ) {
                $membresia->set_gateway_customer_id( (string) $objeto['customer'] );
            }

            $user = VX_User::get( $uid );
            if ( $user ) {
                VX_Mailer::send( $user->get_email(), 'Tu plan en Vitrinexo está activo', 'plan_activado', [
                    'nombre'            => $user->get_nombre(),
                    'plan'              => $plan,
                    'fecha_vencimiento' => date_i18n( 'j \d\e F \d\e Y', vx_stripe_vencimiento( $plan ) ),
                    'es_fundador'       => $user->is_founder(),
                ] );
            }
            break;

        case 'invoice.payment_succeeded':
            /* La renovacion. Se busca por suscripcion, que es lo que no cambia
               entre un cobro y el siguiente. */
            $sub = (string) ( $objeto['subscription'] ?? '' );
            foreach ( vx_stripe_usuarios_por( VX_Membership_Meta::GATEWAY_SUBSCRIPTION, $sub ) as $uid ) {
                $membresia = VX_Membership::get( $uid );
                $plan      = $membresia->get_plan();
                if ( '' === $plan ) continue;
                $membresia->activate( $plan, vx_stripe_vencimiento( $plan ) );
            }
            break;

        case 'customer.subscription.deleted':
            $sub = (string) ( $objeto['id'] ?? '' );
            foreach ( vx_stripe_usuarios_por( VX_Membership_Meta::GATEWAY_SUBSCRIPTION, $sub ) as $uid ) {
                VX_Membership::get( $uid )->cancel();
            }
            break;

        case 'invoice.payment_failed':
            $cliente = (string) ( $objeto['customer'] ?? '' );
            foreach ( vx_stripe_usuarios_por( VX_Membership_Meta::GATEWAY_CUSTOMER_ID, $cliente ) as $uid ) {
                $user = VX_User::get( $uid );
                if ( ! $user ) continue;
                VX_Mailer::send( $user->get_email(), 'No pudimos procesar tu pago', 'plan_pago_fallido', [
                    'nombre'         => $user->get_nombre(),
                    'url_actualizar' => home_url( '/configuracion/?tab=plan' ),
                ] );
            }
            break;
    }

    return new WP_REST_Response( [ 'received' => true ], 200 );
}

/**
 * Hasta cuando vale un plan recien pagado.
 *
 * Se lee el intervalo de la configuracion en vez de escribir los dias acá: si
 * algun dia hay un plan trimestral, esto no hay que tocarlo.
 */
function vx_stripe_vencimiento( string $plan_id ): int {
    $planes = vx_get_planes_config();
    $inter  = $planes[ $plan_id ]['intervalo'] ?? 'mes';
    return 'año' === $inter ? strtotime( '+1 year' ) : strtotime( '+1 month' );
}

/** Los usuarios que tienen ese valor en ese meta. Vacio si el valor es vacio. */
function vx_stripe_usuarios_por( string $meta, string $valor ): array {
    if ( '' === $valor ) return [];
    return get_users( [ 'meta_key' => $meta, 'meta_value' => $valor, 'fields' => 'ids', 'number' => -1 ] );
}

/**
 * POST /stripe/portal
 * Redirige al usuario al portal de facturación de Stripe
 * (cambiar tarjeta, cancelar suscripción, ver facturas).
 */
function vx_rest_stripe_portal(): WP_REST_Response
{
    $user_id   = get_current_user_id();
    $membresia = VX_Membership::get( $user_id );
    $customer  = $membresia->get_gateway_customer_id();

    if ( ! $customer ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'sin_suscripcion' ], 404 );
    }

    if ( ! VX_Stripe::configurado() ) {
        return new WP_REST_Response( [
            'success' => false,
            'error'   => 'stripe_no_configurado',
            'message' => 'El portal de facturación aún no está disponible.',
        ], 503 );
    }

    $sesion = VX_Stripe::llamar( 'billing_portal/sessions', [
        'customer'   => $customer,
        'return_url' => home_url( '/configuracion/?tab=plan' ),
    ] );

    if ( is_wp_error( $sesion ) || empty( $sesion['url'] ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'stripe_error' ], 502 );
    }

    return new WP_REST_Response( [ 'success' => true, 'portal_url' => $sesion['url'] ], 200 );
}
