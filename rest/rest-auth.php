<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'rest_api_init', function () {

    // GET /stats/inscritos — contador público de inscritos
    register_rest_route( VX_REST_NAMESPACE, '/stats/inscritos', [
        'methods'             => 'GET',
        'callback'            => function() {
            // Contaba count_users() menos dos administradores escritos a mano,
            // asi que la cifra publica incluia perfiles pendientes y
            // rechazados, y se descuadraba el dia que hubiera un tercer
            // administrador. Ahora cuenta Pioneros aprobados, que es lo que la
            // frase de al lado promete.
            $total    = vx_pioneros_aprobados();
            $cupo     = vx_pioneros_cupo();
            $restante = max( 0, $cupo - $total );
            return new WP_REST_Response( [
                'inscritos' => $total,
                'cupo'      => $cupo,
                'restante'  => $restante,
                'porcentaje'=> min( 100, round( $total / $cupo * 100 ) ),
            ] );
        },
        'permission_callback' => '__return_true',
    ] );

    // GET /aprobar-usuario — aprueba usuario vía token de email
    register_rest_route( VX_REST_NAMESPACE, '/aprobar-usuario', [
        'methods'             => 'GET',
        'callback'            => 'vx_rest_aprobar_usuario',
        'permission_callback' => '__return_true',
        'args' => [
            'uid'   => [ 'required' => true, 'sanitize_callback' => 'absint' ],
            'token' => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
        ],
    ] );

    // GET /rechazar-usuario — rechaza usuario vía token de email
    register_rest_route( VX_REST_NAMESPACE, '/rechazar-usuario', [
        'methods'             => 'GET',
        'callback'            => 'vx_rest_rechazar_usuario',
        'permission_callback' => '__return_true',
        'args' => [
            'uid'   => [ 'required' => true, 'sanitize_callback' => 'absint' ],
            'token' => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
        ],
    ] );

    // GET /activar — valida token, activa cuenta, redirige
    register_rest_route( VX_REST_NAMESPACE, '/activar', [
        'methods'             => 'GET',
        'callback'            => 'vx_rest_activar_cuenta',
        'permission_callback' => '__return_true',
        'args' => [
            'uid'   => [ 'required' => true, 'sanitize_callback' => 'absint' ],
            'token' => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
        ],
    ] );

    // POST /reenviar-token — reenvía el email de confirmación
    register_rest_route( VX_REST_NAMESPACE, '/reenviar-token', [
        'methods'             => 'POST',
        'callback'            => 'vx_rest_reenviar_token',
        'permission_callback' => 'is_user_logged_in',
    ] );

    // POST /registrar — crea un usuario nuevo
    register_rest_route( VX_REST_NAMESPACE, '/registrar', [
        'methods'             => 'POST',
        'callback'            => 'vx_rest_registrar',
        'permission_callback' => '__return_true',
        'args' => [
            'nombre'   => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
            'apellido' => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
            'email'    => [ 'required' => true, 'sanitize_callback' => 'sanitize_email' ],
            'password' => [ 'required' => true ],
            'pais'     => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
            'empresa'  => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
            'telefono' => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
            'cargo'    => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
            'linkedin' => [ 'required' => false, 'sanitize_callback' => 'sanitize_url' ],
        ],
    ] );

} );

function vx_rest_activar_cuenta( WP_REST_Request $request ): void
{
    $user_id = $request->get_param( 'uid' );
    $token   = $request->get_param( 'token' );

    $user = VX_User::get( $user_id );

    if ( ! $user ) {
        wp_safe_redirect( home_url( '/login/?error=usuario_no_encontrado' ) );
        exit;
    }

    if ( 'activo' === $user->get_estado() ) {
        wp_safe_redirect( $user->is_onboarding_completo()
            ? home_url( '/dashboard/' )
            : home_url( '/onboarding/' ) );
        exit;
    }

    // Fix: cuenta rechazada no puede reactivarse con token — invalidar token e informar
    if ( 'rechazado' === $user->get_estado() ) {
        delete_user_meta( $user_id, VX_User_Meta::TOKEN_CONFIRMACION );
        delete_user_meta( $user_id, VX_User_Meta::TOKEN_EXPIRA );
        wp_safe_redirect( home_url( '/login/?error=cuenta_rechazada' ) );
        exit;
    }

    if ( ! VX_Verification::validate_token( $user_id, $token ) ) {
        wp_safe_redirect( home_url( '/confirmar-correo/?error=token_invalido' ) );
        exit;
    }

    VX_Verification::activate_account( $user_id );

    // Iniciar sesión automáticamente
    wp_set_auth_cookie( $user_id, false );
    wp_set_current_user( $user_id );

    wp_safe_redirect( home_url( '/onboarding/' ) );
    exit;
}

function vx_rest_reenviar_token(): WP_REST_Response
{
    $user_id = get_current_user_id();
    $result  = VX_Verification::resend_token( $user_id );

    return new WP_REST_Response( [ 'success' => $result ], $result ? 200 : 400 );
}

function vx_rest_registrar( WP_REST_Request $request ): WP_REST_Response
{
    $nombre   = $request->get_param( 'nombre' );
    $apellido = $request->get_param( 'apellido' );
    $email    = $request->get_param( 'email' );
    $password = $request->get_param( 'password' );
    $pais     = $request->get_param( 'pais' )     ?? '';
    $empresa  = $request->get_param( 'empresa' )  ?? '';
    $telefono = $request->get_param( 'telefono' ) ?? '';
    $cargo    = $request->get_param( 'cargo' )    ?? '';
    $linkedin = $request->get_param( 'linkedin' ) ?? '';
    $rubro    = $request->get_param( 'rubro' )    ?? '';

    if ( empty( trim( $pais ) ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'pais_requerido', 'message' => 'El país es obligatorio.' ], 400 );
    }

    if ( empty( trim( $empresa ) ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'empresa_requerida', 'message' => 'El nombre de empresa es obligatorio.' ], 400 );
    }

    // El celular es obligatorio y debe incluir el prefijo de país con "+" (para WhatsApp).
    if ( ! preg_match( '/^\+\d[\d\s]{5,}$/', trim( $telefono ) ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'telefono_invalido', 'message' => 'Incluye el prefijo de país con "+" en tu celular (ej: +56 9 1234 5678).' ], 400 );
    }

    // Consentimiento explícito para ser contactado: obligatorio.
    if ( empty( $request->get_param( 'consentimiento' ) ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'consentimiento_requerido', 'message' => 'Debes autorizar que otros miembros te contacten para registrarte.' ], 400 );
    }

    if ( ! is_email( $email ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'email_invalido' ], 400 );
    }

    if ( email_exists( $email ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'email_en_uso' ], 409 );
    }

    if ( strlen( $password ) < 8 ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'password_muy_corta', 'message' => 'La contraseña debe tener al menos 8 caracteres.' ], 400 );
    }

    $user_id = wp_create_user( $email, $password, $email );

    if ( is_wp_error( $user_id ) ) {
        $code = $user_id->get_error_code();
        if ( $code === 'existing_user_login' || $code === 'existing_user_email' ) {
            return new WP_REST_Response( [ 'success' => false, 'error' => 'email_existente', 'message' => 'Ese email ya está registrado.' ], 409 );
        }
        return new WP_REST_Response( [ 'success' => false, 'error' => $code ], 500 );
    }

    // Actualizar nombre en la tabla wp_users para que aparezca en el admin
    wp_update_user( [
        'ID'           => $user_id,
        'first_name'   => $nombre,
        'last_name'    => $apellido,
        'display_name' => $nombre . ' ' . $apellido,
    ] );

    if ( is_wp_error( $user_id ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => $user_id->get_error_code() ], 500 );
    }

    // Establecer rol subscriber
    $user = new WP_User( $user_id );
    $user->set_role( 'subscriber' );

    // Guardar meta iniciales
    update_user_meta( $user_id, VX_User_Meta::NOMBRE,              $nombre );
    update_user_meta( $user_id, VX_User_Meta::APELLIDO,            $apellido );
    update_user_meta( $user_id, VX_User_Meta::PAIS,                $pais );
    update_user_meta( $user_id, VX_User_Meta::ESTADO,              'pendiente' );
    update_user_meta( $user_id, VX_User_Meta::ONBOARDING_COMPLETO, false );
    update_user_meta( $user_id, VX_User_Meta::ONBOARDING_PASO,     1 );
    update_user_meta( $user_id, VX_User_Meta::PLAN,                'gratuito' );
    update_user_meta( $user_id, VX_User_Meta::PLAN_ESTADO,         'activo' );

    // Guardar teléfono obligatorio
    update_user_meta( $user_id, VX_User_Meta::TELEFONO, sanitize_text_field( $telefono ) );
    update_user_meta( $user_id, 'vx_consentimiento_contacto', '1' );

    // Fix Gap 3: generar slug desde el registro para que el perfil no quede huérfano
    // si el usuario abandona el onboarding antes del paso 2.
    if ( $nombre && $apellido && class_exists( 'VX_Slug_Helper' ) ) {
        $slug = VX_Slug_Helper::generate( $nombre, $apellido, $user_id );
        update_user_meta( $user_id, VX_User_Meta::PERFIL_SLUG, $slug );
    }

    // Guardar empresa inicial en meta temporal (para el onboarding paso 3)
    if ( $empresa ) {
        update_user_meta( $user_id, 'vx_empresa_inicial', $empresa );

        // Crear entrada en el CPT vx_empresa para que aparezca en el listado
        $empresa_id = wp_insert_post( [
            'post_type'   => 'vx_empresa',
            'post_title'  => sanitize_text_field( $empresa ),
            'post_status' => 'publish',
            'post_author' => $user_id,
        ] );
        if ( $empresa_id && ! is_wp_error( $empresa_id ) ) {
            update_post_meta( $empresa_id, 'vx_user_id',        $user_id );
            update_post_meta( $empresa_id, 'vx_empresa_activa', '1' );
            update_post_meta( $empresa_id, 'vx_industria',      sanitize_text_field( $rubro ) );
        }
    }

    // Guardar cargo y LinkedIn
    if ( $cargo ) {
        update_user_meta( $user_id, 'vx_cargo_inicial', sanitize_text_field( $cargo ) );
    }
    if ( $linkedin ) {
        update_user_meta( $user_id, VX_User_Meta::LINKEDIN, esc_url_raw( $linkedin ) );
    }

    // Iniciar flujo de verificación
    VX_Verification::start( $user_id, $email );

    // Notificar a joao y marcia de cada nuevo registro
    $token_aprobar  = bin2hex( random_bytes( 32 ) );
    $token_rechazar = bin2hex( random_bytes( 32 ) );
    update_user_meta( $user_id, VX_User_Meta::TOKEN_APROBAR,  $token_aprobar );
    update_user_meta( $user_id, VX_User_Meta::TOKEN_RECHAZAR, $token_rechazar );

    $url_aprobar  = rest_url( VX_REST_NAMESPACE . '/aprobar-usuario' ) . '?uid=' . $user_id . '&token=' . $token_aprobar;
    $url_rechazar = rest_url( VX_REST_NAMESPACE . '/rechazar-usuario' ) . '?uid=' . $user_id . '&token=' . $token_rechazar;

    // Obtener asunto e intro desde plantilla editable (Vitrinexo → Emails)
    $tpl_data    = [
        'nombre'       => $nombre,
        'apellido'     => $apellido,
        'email_usuario'=> $email,
        'empresa'      => $empresa,
        'cargo'        => $cargo,
        'pais'         => $pais,
        'telefono'     => $telefono ?? '',
        'linkedin'     => $linkedin ?? '',
        'url_aprobar'  => $url_aprobar,
        'url_rechazar' => $url_rechazar,
    ];

    // Leer asunto editable; reemplazar variables
    $tpl_defaults = [
        'subject' => '[Vitrinexo] Nuevo registro: ' . $nombre . ' ' . $apellido,
        'intro'   => 'Alguien completó el formulario de inscripción. Revisa los datos y aprueba o rechaza la solicitud.',
    ];
    if ( class_exists( 'VX_Admin_Emails' ) ) {
        $editable = VX_Admin_Emails::get( 'notificacion_admin', $tpl_data );
        if ( ! empty( $editable['subject'] ) )    $tpl_defaults['subject'] = $editable['subject'];
        if ( ! empty( $editable['body_text'] ) )  $tpl_defaults['intro']   = $editable['body_text'];
    }
    $tpl_data['intro'] = $tpl_defaults['intro'];

    $asunto  = $tpl_defaults['subject'];
    $cuerpo  = VX_Email_Templates::render( 'notificacion_admin', $tpl_data );
    $headers = [ 'Content-Type: text/html; charset=UTF-8', 'From: Vitrinexo <hola@vitrinexo.com>' ];

    foreach ( [ 'joao@vitrinexo.com', 'marcia@vitrinexo.com' ] as $admin ) {
        wp_mail( $admin, $asunto, $cuerpo, $headers );
    }

    // Política: todas las cuentas quedan pendientes de aprobación manual de un
    // administrador, sin importar el dominio del email.
    $tipo_verificacion = 'manual';

    return new WP_REST_Response( [
        'success'            => true,
        'user_id'            => $user_id,
        'tipo_verificacion'  => $tipo_verificacion,
        'redirect'           => home_url( '/verificacion-pendiente/' ),
    ], 201 );
}

function vx_rest_aprobar_usuario( WP_REST_Request $request ): void
{
    $user_id = $request->get_param( 'uid' );
    $token   = $request->get_param( 'token' );
    $stored  = get_user_meta( $user_id, VX_User_Meta::TOKEN_APROBAR, true );

    if ( ! $stored || ! hash_equals( $stored, $token ) ) {
        wp_die( '<h2>Enlace inválido o ya utilizado.</h2>' );
    }

    delete_user_meta( $user_id, VX_User_Meta::TOKEN_APROBAR );
    delete_user_meta( $user_id, VX_User_Meta::TOKEN_RECHAZAR );

    $user = VX_User::get( $user_id );
    if ( ! $user ) wp_die( '<h2>Usuario no encontrado.</h2>' );

    if ( 'activo' === $user->get_estado() ) {
        wp_die( '<h2 style="font-family:sans-serif;color:#AD4319">Este usuario ya estaba aprobado.</h2>' );
    }

    // Activar directamente sin requerir confirmación de email
    VX_Verification::activate_account( $user_id );

    wp_die( '<h2 style="font-family:sans-serif;color:#AD4319">Usuario aprobado y activado</h2><p style="font-family:sans-serif">' . esc_html( $user->get_nombre_completo() ) . ' (' . esc_html( $user->get_email() ) . ') ha sido aprobado y ya puede acceder a Vitrinexo.</p>' );
}

function vx_rest_rechazar_usuario( WP_REST_Request $request ): void
{
    $user_id = $request->get_param( 'uid' );
    $token   = $request->get_param( 'token' );
    $stored  = get_user_meta( $user_id, VX_User_Meta::TOKEN_RECHAZAR, true );

    if ( ! $stored || ! hash_equals( $stored, $token ) ) {
        wp_die( '<h2>Enlace inválido o ya utilizado.</h2>' );
    }

    delete_user_meta( $user_id, VX_User_Meta::TOKEN_APROBAR );
    delete_user_meta( $user_id, VX_User_Meta::TOKEN_RECHAZAR );

    $user = VX_User::get( $user_id );
    if ( ! $user ) wp_die( '<h2>Usuario no encontrado.</h2>' );

    if ( 'rechazado' === $user->get_estado() ) {
        wp_die( '<h2>Este usuario ya estaba rechazado.</h2>' );
    }

    update_user_meta( $user_id, VX_User_Meta::ESTADO, 'rechazado' );

    // Notificar al usuario
    VX_Mailer::send(
        $user->get_email(),
        'Tu solicitud en Vitrinexo',
        'rechazo',
        [ 'nombre' => $user->get_nombre() ]
    );

    wp_die( '<h2 style="font-family:sans-serif;color:#14161F">Usuario rechazado</h2><p style="font-family:sans-serif">' . esc_html( $user->get_nombre_completo() ) . ' (' . esc_html( $user->get_email() ) . ') ha sido rechazado.</p>' );
}
