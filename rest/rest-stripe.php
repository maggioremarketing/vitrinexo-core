<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ════════════════════════════════════════════════════════════════════════════
 *  REST API — Stripe Integration (STUBS)
 *  Todos los endpoints están definidos y documentados.
 *  Para activar la integración real:
 *    1. composer require stripe/stripe-php
 *    2. Agregar claves en wp-admin → Vitrinexo → Ajustes
 *    3. Reemplazar los bloques marcados con "STRIPE TODO"
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

    // ── STRIPE TODO ────────────────────────────────────────────────────────
    // Cuando tengas las claves de Stripe:
    //
    // require_once __DIR__ . '/../../vendor/autoload.php';
    // \Stripe\Stripe::setApiKey( get_option('vx_stripe_secret_key') );
    //
    // $membresia = VX_Membership::get($user_id);
    // $customer_id = $membresia->get_gateway_customer_id();
    //
    // if (!$customer_id) {
    //     $customer = \Stripe\Customer::create([
    //         'email' => $user->get_email(),
    //         'name'  => $user->get_nombre_completo(),
    //         'metadata' => ['vx_user_id' => $user_id],
    //     ]);
    //     $customer_id = $customer->id;
    //     $membresia->set_gateway_customer_id($customer_id);
    // }
    //
    // $session = \Stripe\Checkout\Session::create([
    //     'customer'            => $customer_id,
    //     'mode'                => 'subscription',
    //     'line_items'          => [[ 'price' => $plan['stripe_price_id'], 'quantity' => 1 ]],
    //     'success_url'         => home_url('/configuracion/?tab=plan&stripe=success&session_id={CHECKOUT_SESSION_ID}'),
    //     'cancel_url'          => home_url('/configuracion/?tab=plan&stripe=cancelled'),
    //     'metadata'            => ['vx_user_id' => $user_id, 'vx_plan' => $plan_id],
    //     'allow_promotion_codes' => true,
    // ]);
    //
    // return new WP_REST_Response(['success' => true, 'checkout_url' => $session->url], 200);
    // ── FIN STRIPE TODO ────────────────────────────────────────────────────

    // STUB: mientras no hay Stripe conectado, devolver URL de configuración
    return new WP_REST_Response( [
        'success'      => false,
        'error'        => 'stripe_no_configurado',
        'message'      => 'El sistema de pagos aún no está activado. Contacta a hola@vitrinexo.com para coordinar tu suscripción.',
        'plan'         => $plan['nombre'],
        'precio'       => $plan['precio'] . ' ' . $plan['moneda'] . '/' . $plan['intervalo'],
    ], 503 );
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

    // ── STRIPE TODO ────────────────────────────────────────────────────────
    // require_once __DIR__ . '/../../vendor/autoload.php';
    // \Stripe\Stripe::setApiKey( get_option('vx_stripe_secret_key') );
    // $webhook_secret = get_option('vx_stripe_webhook_secret');
    //
    // try {
    //     $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $webhook_secret);
    // } catch (\Exception $e) {
    //     return new WP_REST_Response(['error' => 'firma_invalida'], 400);
    // }
    //
    // switch ($event->type) {
    //
    //     case 'checkout.session.completed':
    //         $session    = $event->data->object;
    //         $user_id    = (int) $session->metadata->vx_user_id;
    //         $plan_id    = $session->metadata->vx_plan;
    //         $sub_id     = $session->subscription;
    //         // Calcular vencimiento (1 mes o 1 año desde ahora)
    //         $planes     = vx_get_planes_config();
    //         $intervalo  = $planes[$plan_id]['intervalo'] ?? 'mes';
    //         $expiry     = 'año' === $intervalo ? strtotime('+1 year') : strtotime('+1 month');
    //         $membresia  = VX_Membership::get($user_id);
    //         $membresia->activate($plan_id, $expiry);
    //         $membresia->set_gateway_subscription_id($sub_id);
    //         // Enviar email de confirmación
    //         $user = VX_User::get($user_id);
    //         if ($user) {
    //             VX_Mailer::send($user->get_email(), '¡Tu plan está activo!', 'plan_activado', [
    //                 'nombre' => $user->get_nombre(),
    //                 'plan' => $plan_id,
    //                 'fecha_vencimiento' => date_i18n('j \d\e F Y', $expiry),
    //                 'es_fundador' => $user->is_founder(),
    //             ]);
    //         }
    //         break;
    //
    //     case 'invoice.payment_succeeded':
    //         $invoice   = $event->data->object;
    //         $sub       = $invoice->subscription;
    //         // Buscar usuario por subscription_id y renovar vencimiento
    //         $users = get_users(['meta_key' => VX_Membership_Meta::GATEWAY_SUBSCRIPTION, 'meta_value' => $sub, 'fields' => 'ids']);
    //         foreach ($users as $uid) {
    //             $plan = VX_Membership::get($uid)->get_plan();
    //             $planes = vx_get_planes_config();
    //             $intervalo = $planes[$plan]['intervalo'] ?? 'mes';
    //             $expiry = 'año' === $intervalo ? strtotime('+1 year') : strtotime('+1 month');
    //             VX_Membership::get($uid)->activate($plan, $expiry);
    //         }
    //         break;
    //
    //     case 'customer.subscription.deleted':
    //         $sub   = $event->data->object;
    //         $users = get_users(['meta_key' => VX_Membership_Meta::GATEWAY_SUBSCRIPTION, 'meta_value' => $sub->id, 'fields' => 'ids']);
    //         foreach ($users as $uid) { VX_Membership::get($uid)->cancel(); }
    //         break;
    //
    //     case 'invoice.payment_failed':
    //         $invoice = $event->data->object;
    //         $users = get_users(['meta_key' => VX_Membership_Meta::GATEWAY_CUSTOMER_ID, 'meta_value' => $invoice->customer, 'fields' => 'ids']);
    //         foreach ($users as $uid) {
    //             $user = VX_User::get($uid);
    //             if ($user) {
    //                 VX_Mailer::send($user->get_email(), 'Problema con tu pago en Vitrinexo', 'plan_pago_fallido', [
    //                     'nombre' => $user->get_nombre(),
    //                     'url_actualizar' => home_url('/configuracion/?tab=plan'),
    //                 ]);
    //             }
    //         }
    //         break;
    // }
    // ── FIN STRIPE TODO ────────────────────────────────────────────────────

    // STUB: responder 200 para que Stripe no reintente
    return new WP_REST_Response( [ 'received' => true ], 200 );
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

    // ── STRIPE TODO ────────────────────────────────────────────────────────
    // \Stripe\Stripe::setApiKey( get_option('vx_stripe_secret_key') );
    // $session = \Stripe\BillingPortal\Session::create([
    //     'customer'   => $customer,
    //     'return_url' => home_url('/configuracion/?tab=plan'),
    // ]);
    // return new WP_REST_Response(['success' => true, 'portal_url' => $session->url], 200);
    // ── FIN STRIPE TODO ────────────────────────────────────────────────────

    return new WP_REST_Response( [
        'success' => false,
        'error'   => 'stripe_no_configurado',
        'message' => 'Portal no disponible aún.',
    ], 503 );
}
