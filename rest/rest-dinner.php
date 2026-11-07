<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'rest_api_init', function () {

    // GET /dinners — lista de próximos 4Dinners
    register_rest_route( VX_REST_NAMESPACE, '/dinners', [
        'methods'             => 'GET',
        'callback'            => 'vx_rest_dinners_listar',
        'permission_callback' => 'is_user_logged_in',
    ] );

    // POST /dinners/{id}/interes — registrar interés
    register_rest_route( VX_REST_NAMESPACE, '/dinners/(?P<id>\d+)/interes', [
        'methods'             => 'POST',
        'callback'            => 'vx_rest_dinner_interes_agregar',
        'permission_callback' => 'is_user_logged_in',
        'args'                => [ 'id' => [ 'required' => true, 'sanitize_callback' => 'absint' ] ],
    ] );

    // DELETE /dinners/{id}/interes — retirar interés
    register_rest_route( VX_REST_NAMESPACE, '/dinners/(?P<id>\d+)/interes', [
        'methods'             => 'DELETE',
        'callback'            => 'vx_rest_dinner_interes_retirar',
        'permission_callback' => 'is_user_logged_in',
        'args'                => [ 'id' => [ 'required' => true, 'sanitize_callback' => 'absint' ] ],
    ] );

    // POST /dinners/{id}/invitar — admin invita a un usuario (solo manage_options)
    register_rest_route( VX_REST_NAMESPACE, '/dinners/(?P<id>\d+)/invitar', [
        'methods'             => 'POST',
        'callback'            => 'vx_rest_dinner_invitar',
        'permission_callback' => function() { return current_user_can( 'manage_options' ); },
        'args'                => [
            'id'      => [ 'required' => true, 'sanitize_callback' => 'absint' ],
            'user_id' => [ 'required' => true, 'sanitize_callback' => 'absint' ],
        ],
    ] );

    // GET /dinners/invites/{token}/aceptar — aceptar invitación por token
    register_rest_route( VX_REST_NAMESPACE, '/dinners/invites/(?P<token>[a-f0-9]+)/aceptar', [
        'methods'             => 'GET',
        'callback'            => 'vx_rest_dinner_invite_aceptar',
        'permission_callback' => '__return_true',
        'args'                => [ 'token' => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ] ],
    ] );

    // GET /dinners/invites/{token}/rechazar — rechazar invitación por token
    register_rest_route( VX_REST_NAMESPACE, '/dinners/invites/(?P<token>[a-f0-9]+)/rechazar', [
        'methods'             => 'GET',
        'callback'            => 'vx_rest_dinner_invite_rechazar',
        'permission_callback' => '__return_true',
        'args'                => [ 'token' => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ] ],
    ] );

    // POST /dinners/admin/{id}/asignar-interes — admin asigna a alguien que expresó interés
    register_rest_route( VX_REST_NAMESPACE, '/dinners/admin/(?P<invite_id>\d+)/asignar', [
        'methods'             => 'POST',
        'callback'            => 'vx_rest_dinner_admin_asignar_interes',
        'permission_callback' => function() { return current_user_can( 'manage_options' ); },
        'args'                => [ 'invite_id' => [ 'required' => true, 'sanitize_callback' => 'absint' ] ],
    ] );

} );

// ── Listar dinners ────────────────────────────────────────────────────────────

function vx_rest_dinners_listar(): WP_REST_Response
{
    $user_id = get_current_user_id();
    $dinners = VX_Dinner::get_upcoming();
    $result  = [];

    foreach ( $dinners as $dinner ) {
        $asignados   = $dinner->get_asignados();
        $invite      = VX_Dinner_Invite::get_pending( $dinner->get_id(), $user_id );

        $result[] = [
            'id'               => $dinner->get_id(),
            'ciudad'           => $dinner->get_ciudad(),
            'pais'             => $dinner->get_pais(),
            'fecha'            => $dinner->get_fecha(),
            'restaurante'      => $dinner->get_restaurante(),
            'cupos_disponibles'=> max( 0, 4 - count( $asignados ) ),
            'estado'           => $dinner->get_estado(),
            'user_asignado'    => $dinner->is_user_assigned( $user_id ),
            'user_interesado'  => ! is_null( $invite ),
        ];
    }

    return new WP_REST_Response( [ 'success' => true, 'data' => $result ], 200 );
}

// ── Interés ───────────────────────────────────────────────────────────────────

function vx_rest_dinner_interes_agregar( WP_REST_Request $request ): WP_REST_Response
{
    $user_id   = get_current_user_id();
    $dinner_id = (int) $request->get_param( 'id' );
    $mensaje   = sanitize_textarea_field( $request->get_param( 'mensaje' ) ?? '' );

    $dinner = VX_Dinner::get( $dinner_id );
    if ( ! $dinner ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'dinner_no_encontrado' ], 404 );
    }

    // Bloquear si las inscripciones están cerradas (deadline pasado o estado incorrecto)
    if ( ! $dinner->is_open_for_registration() ) {
        return new WP_REST_Response( [
            'success' => false,
            'error'   => 'inscripciones_cerradas',
            'message' => 'Las inscripciones para este evento están cerradas.',
        ], 422 );
    }

    // Bug 4 fix: un solo punto de escritura — create_interest() maneja todo
    $invite_id = VX_Dinner_Invite::create_interest( $dinner_id, $user_id, $mensaje );

    if ( null === $invite_id ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'ya_registrado', 'message' => 'Ya tienes un interés registrado para este evento.' ], 409 );
    }

    // Email de confirmación de interés al usuario
    $user_obj = VX_User::get( $user_id );
    if ( $user_obj ) {
        VX_Mailer::send(
            $user_obj->get_email(),
            'Tu interés en el Vitrinexo 4Dinner de ' . $dinner->get_ciudad() . ' fue registrado',
            'dinner_interes',
            [
                'nombre' => $user_obj->get_nombre(),
                'dinner' => [
                    'ciudad' => $dinner->get_ciudad(),
                    'fecha'  => $dinner->get_fecha(),
                    'restaurante' => $dinner->get_restaurante(),
                ],
            ]
        );
    }

    return new WP_REST_Response( [ 'success' => true, 'invite_id' => $invite_id ], 200 );
}

function vx_rest_dinner_interes_retirar( WP_REST_Request $request ): WP_REST_Response
{
    $user_id   = get_current_user_id();
    $dinner_id = (int) $request->get_param( 'id' );

    $invite = VX_Dinner_Invite::get_pending( $dinner_id, $user_id );
    if ( $invite ) {
        VX_Dinner_Invite::reject( $invite['id'] );
    }

    return new WP_REST_Response( [ 'success' => true ], 200 );
}

// ── Invitar (admin) ───────────────────────────────────────────────────────────

function vx_rest_dinner_invitar( WP_REST_Request $request ): WP_REST_Response
{
    $dinner_id = (int) $request->get_param( 'id' );
    $user_id   = (int) $request->get_param( 'user_id' );

    $result = VX_Dinner_Invite::create_invitation( $dinner_id, $user_id );

    if ( is_wp_error( $result ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => $result->get_error_code(), 'message' => $result->get_error_message() ], 400 );
    }

    return new WP_REST_Response( [ 'success' => true, 'invite_id' => $result ], 200 );
}

// ── Aceptar / rechazar por token ──────────────────────────────────────────────

function vx_rest_dinner_invite_aceptar( WP_REST_Request $request ): void
{
    $token  = $request->get_param( 'token' );
    $result = VX_Dinner_Invite::accept_by_token( $token );

    if ( is_wp_error( $result ) ) {
        wp_safe_redirect( home_url( '/4dinner/?dinner_error=' . $result->get_error_code() ) );
    } else {
        wp_safe_redirect( home_url( '/4dinner/?dinner_confirmado=1' ) );
    }
    exit;
}

function vx_rest_dinner_invite_rechazar( WP_REST_Request $request ): void
{
    $token  = $request->get_param( 'token' );
    $result = VX_Dinner_Invite::reject_by_token( $token );

    if ( is_wp_error( $result ) ) {
        wp_safe_redirect( home_url( '/4dinner/?dinner_error=' . $result->get_error_code() ) );
    } else {
        wp_safe_redirect( home_url( '/4dinner/?dinner_rechazado=1' ) );
    }
    exit;
}

// ── Admin: asignar interesado ─────────────────────────────────────────────────

function vx_rest_dinner_admin_asignar_interes( WP_REST_Request $request ): WP_REST_Response
{
    $invite_id = (int) $request->get_param( 'invite_id' );
    $ok = VX_Dinner_Invite::accept( $invite_id );

    if ( ! $ok ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'no_se_pudo_asignar' ], 400 );
    }

    return new WP_REST_Response( [ 'success' => true ], 200 );
}
