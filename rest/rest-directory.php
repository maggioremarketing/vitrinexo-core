<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'rest_api_init', function () {

    // GET /directorio — lista de miembros con filtros y paginación
    register_rest_route( VX_REST_NAMESPACE, '/directorio', [
        'methods'             => 'GET',
        'callback'            => 'vx_rest_directorio',
        'permission_callback' => 'is_user_logged_in',
        'args' => [
            'pais'      => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
            'comunidad' => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
            'fundador'  => [ 'required' => false ],
            'pagina'    => [ 'required' => false, 'default' => 1, 'sanitize_callback' => 'absint' ],
        ],
    ] );

    // GET /directorio/buscar — búsqueda con relevancia
    register_rest_route( VX_REST_NAMESPACE, '/directorio/buscar', [
        'methods'             => 'GET',
        'callback'            => 'vx_rest_directorio_buscar',
        'permission_callback' => 'is_user_logged_in',
        'args' => [
            'q'         => [ 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ],
            'pais'      => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
            'comunidad' => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
        ],
    ] );

    // GET /perfil/{slug} — datos públicos de un perfil
    register_rest_route( VX_REST_NAMESPACE, '/perfil/(?P<slug>[a-z0-9\-]+)', [
        'methods'             => 'GET',
        'callback'            => 'vx_rest_perfil',
        'permission_callback' => 'is_user_logged_in',
        'args' => [
            'slug' => [ 'required' => true, 'sanitize_callback' => 'sanitize_title' ],
        ],
    ] );

    // GET /nexos/busca — mis seek_tags ∩ offer_tags de otros
    register_rest_route( VX_REST_NAMESPACE, '/nexos/busca', [
        'methods'             => 'GET',
        'callback'            => 'vx_rest_matches_seeks',
        'permission_callback' => 'is_user_logged_in',
        'args' => [
            'pagina' => [ 'required' => false, 'default' => 1, 'sanitize_callback' => 'absint' ],
        ],
    ] );

    // GET /nexos/ofrece — mis offer_tags ∩ seek_tags de otros
    register_rest_route( VX_REST_NAMESPACE, '/nexos/ofrece', [
        'methods'             => 'GET',
        'callback'            => 'vx_rest_matches_offers',
        'permission_callback' => 'is_user_logged_in',
        'args' => [
            'pagina' => [ 'required' => false, 'default' => 1, 'sanitize_callback' => 'absint' ],
        ],
    ] );

} );

function vx_rest_directorio( WP_REST_Request $request ): WP_REST_Response
{
    $args = [
        'pais'      => $request->get_param( 'pais' )      ?? '',
        'comunidad' => $request->get_param( 'comunidad' ) ?? '',
        'fundador'  => (bool) $request->get_param( 'fundador' ),
        'pagina'    => max( 1, (int) $request->get_param( 'pagina' ) ),
    ];

    $result = VX_Directory::get_members( $args );

    return new WP_REST_Response( [ 'success' => true, 'data' => $result ], 200 );
}

function vx_rest_directorio_buscar( WP_REST_Request $request ): WP_REST_Response
{
    $query   = $request->get_param( 'q' );
    $filters = [
        'pais'      => $request->get_param( 'pais' )      ?? '',
        'comunidad' => $request->get_param( 'comunidad' ) ?? '',
    ];

    if ( strlen( $query ) < 2 ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'query_muy_corta' ], 400 );
    }

    $result = VX_Search::search( $query, $filters );

    return new WP_REST_Response( [ 'success' => true, 'data' => $result ], 200 );
}

function vx_rest_perfil( WP_REST_Request $request ): WP_REST_Response
{
    $slug    = $request->get_param( 'slug' );
    $viewer  = get_current_user_id();

    $users = get_users( [
        'meta_key'   => VX_User_Meta::PERFIL_SLUG,
        'meta_value' => $slug,
        'number'     => 1,
        'fields'     => 'ids',
    ] );

    if ( empty( $users ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'perfil_no_encontrado' ], 404 );
    }

    $user_id = (int) $users[0];
    $user    = VX_User::get( $user_id );

    if ( ! $user || ! $user->is_active() ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'perfil_no_encontrado' ], 404 );
    }

    // Disparar evento de visita (solo si no es el propio perfil)
    if ( $viewer !== $user_id ) {
        do_action( 'vx_profile_visited', $user_id, $viewer );
    }

    $card    = $user->to_card_array();
    $empresa = $user->get_empresa_activa();

    $data = array_merge( $card, [
        'bio'          => $user->get_bio(),
        'telefono'     => null, // ocultado hasta aceptar conexión
        'linkedin'     => null,
        'offer_tags'   => $user->get_offer_tags(),
        'seek_tags'    => $user->get_seek_tags(),
        'offer_texto'  => get_user_meta( $user_id, VX_User_Meta::OFFER_TEXTO, true ),
        'seek_texto'   => get_user_meta( $user_id, VX_User_Meta::SEEK_TEXTO, true ),
        'comunidades'  => $user->get_comunidades_activas(),
        'empresa'      => $empresa ? [
            'nombre'    => $empresa->post_title,
            'logo_url'  => wp_get_attachment_image_url( get_post_meta( $empresa->ID, 'vx_logo', true ), 'vx-logo' ) ?: '',
            'banner_url'=> wp_get_attachment_image_url( get_post_meta( $empresa->ID, 'vx_banner', true ), 'vx-banner' ) ?: '',
            'sector'    => get_post_meta( $empresa->ID, 'vx_sector', true ),
            'pais'      => get_post_meta( $empresa->ID, 'vx_pais', true ),
            'web'       => get_post_meta( $empresa->ID, 'vx_web', true ),
            'descripcion' => get_post_meta( $empresa->ID, 'vx_descripcion', true ),
        ] : null,
    ] );

    // Si tienen conexión aceptada, revelar datos de contacto
    $conexion = VX_Connection::get_between( $viewer, $user_id );
    if ( $conexion && 'aceptado' === $conexion->get_estado() ) {
        $data['telefono'] = $user->get_telefono();
        $data['linkedin'] = $user->get_linkedin();
    }

    return new WP_REST_Response( [ 'success' => true, 'data' => $data ], 200 );
}

function vx_rest_matches_seeks( WP_REST_Request $request ): WP_REST_Response
{
    $user_id = get_current_user_id();
    $pagina  = max( 1, (int) $request->get_param( 'pagina' ) );

    $result = VX_Matches::get_seeks_matches( $user_id, [ 'pagina' => $pagina ] );

    return new WP_REST_Response( [ 'success' => true, 'data' => $result ], 200 );
}

function vx_rest_matches_offers( WP_REST_Request $request ): WP_REST_Response
{
    $user_id = get_current_user_id();
    $pagina  = max( 1, (int) $request->get_param( 'pagina' ) );

    $result = VX_Matches::get_offers_matches( $user_id, [ 'pagina' => $pagina ] );

    return new WP_REST_Response( [ 'success' => true, 'data' => $result ], 200 );
}
