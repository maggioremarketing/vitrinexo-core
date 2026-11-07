<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'rest_api_init', function () {

    // POST /comunidades/solicitar-senior — solicitar verificación Senior
    register_rest_route( VX_REST_NAMESPACE, '/comunidades/solicitar-senior', [
        'methods'             => 'POST',
        'callback'            => 'vx_rest_solicitar_senior',
        'permission_callback' => 'is_user_logged_in',
    ] );

    // POST /comunidades/{slug}/unirse — unirse a una comunidad (out2b, woman)
    register_rest_route( VX_REST_NAMESPACE, '/comunidades/(?P<slug>[a-z0-9]+)/unirse', [
        'methods'             => 'POST',
        'callback'            => 'vx_rest_comunidad_unirse',
        'permission_callback' => 'is_user_logged_in',
        'args' => [
            'slug' => [ 'required' => true, 'sanitize_callback' => 'sanitize_key' ],
        ],
    ] );

    // DELETE /comunidades/{slug}/salir — salir de una comunidad
    register_rest_route( VX_REST_NAMESPACE, '/comunidades/(?P<slug>[a-z0-9]+)/salir', [
        'methods'             => 'DELETE',
        'callback'            => 'vx_rest_comunidad_salir',
        'permission_callback' => 'is_user_logged_in',
        'args' => [
            'slug' => [ 'required' => true, 'sanitize_callback' => 'sanitize_key' ],
        ],
    ] );

} );

function vx_rest_solicitar_senior(): WP_REST_Response
{
    $user_id = get_current_user_id();
    $user    = VX_User::get( $user_id );

    if ( ! $user ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'usuario_no_encontrado' ], 404 );
    }

    if ( $user->is_in_community( 'senior' ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'ya_es_senior' ], 409 );
    }

    if ( get_user_meta( $user_id, VX_User_Meta::SENIOR_SOLICITADO, true ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'solicitud_ya_enviada' ], 409 );
    }

    VX_Senior_Verification::request( $user_id );

    return new WP_REST_Response( [ 'success' => true ], 200 );
}

function vx_rest_comunidad_unirse( WP_REST_Request $request ): WP_REST_Response
{
    $user_id   = get_current_user_id();
    $comunidad = $request->get_param( 'slug' );

    if ( ! in_array( $comunidad, VX_Community::COMMUNITIES, true ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'comunidad_invalida' ], 400 );
    }

    // Senior requiere verificación manual
    if ( 'senior' === $comunidad ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'senior_requiere_verificacion' ], 403 );
    }

    VX_Community::activate( $user_id, $comunidad );

    return new WP_REST_Response( [ 'success' => true ], 200 );
}

function vx_rest_comunidad_salir( WP_REST_Request $request ): WP_REST_Response
{
    $user_id   = get_current_user_id();
    $comunidad = $request->get_param( 'slug' );

    if ( ! in_array( $comunidad, VX_Community::COMMUNITIES, true ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'comunidad_invalida' ], 400 );
    }

    // La comunidad Woman es obligatoria para género femenino: no se puede abandonar.
    if ( 'woman' === $comunidad
        && 'femenino' === get_user_meta( $user_id, VX_User_Meta::GENERO, true ) ) {
        return new WP_REST_Response( [
            'success' => false,
            'error'   => 'woman_obligatoria',
            'message' => 'Vitrinexo Woman es parte de tu membresía por tu género y no puede desactivarse.',
        ], 403 );
    }

    // La comunidad Senior es automática (45+ años o 20+ de experiencia): no se puede abandonar.
    if ( 'senior' === $comunidad
        && class_exists( 'VX_Senior_Verification' )
        && VX_Senior_Verification::qualifies( $user_id ) ) {
        return new WP_REST_Response( [
            'success' => false,
            'error'   => 'senior_obligatoria',
            'message' => 'Vitrinexo Senior se asigna automáticamente por tu edad o experiencia y no puede desactivarse.',
        ], 403 );
    }

    VX_Community::deactivate( $user_id, $comunidad );

    return new WP_REST_Response( [ 'success' => true ], 200 );
}
