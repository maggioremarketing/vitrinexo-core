<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ─── AJAX Login (wp_ajax_nopriv) ─────────────────────────────────────────────

add_action( 'wp_ajax_nopriv_vx_ajax_login', 'vx_ajax_login_handler' );
add_action( 'wp_ajax_vx_ajax_login',        'vx_ajax_login_handler' );

function vx_ajax_login_handler(): void
{
    check_ajax_referer( 'vx_ajax_login', 'nonce' );

    $email    = sanitize_email( wp_unslash( $_POST['email']    ?? '' ) );
    $password = wp_unslash( $_POST['password'] ?? '' );
    $remember = ! empty( $_POST['remember'] );

    if ( ! $email || ! $password ) {
        wp_send_json_error( [ 'message' => 'Campos requeridos.' ], 400 );
    }

    // ── Rate limiting: máx 5 intentos fallidos, luego 15 min de espera ──────
    // Fix: leer IP real detrás de Cloudflare/proxies (no usar REMOTE_ADDR solo)
    $ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? 'unknown' );
    if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
        $ip = sanitize_text_field( $_SERVER['HTTP_CF_CONNECTING_IP'] );
    } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
        $ip = sanitize_text_field( trim( explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] )[0] ) );
    }
    if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) $ip = 'unknown';

    $lock_key     = 'vx_login_locked_' . md5( $ip . '_' . $email ); // incluir email para evitar DoS global
    $attempts_key = 'vx_login_attempts_' . md5( $ip . '_' . $email );
    $max_attempts = 5;
    $lockout_secs = 15 * MINUTE_IN_SECONDS;

    if ( get_transient( $lock_key ) ) {
        // Fix: almacenar timestamp de expiración en lugar del valor constante
        $expires_at = (int) get_transient( $lock_key . '_expires' );
        $remaining  = max( 0, $expires_at - time() );
        wp_send_json_error( [
            'message' => 'Demasiados intentos fallidos. Espera ' . ceil( $remaining / 60 ) . ' minuto(s) antes de intentar de nuevo.',
            'locked'  => true,
        ], 429 );
    }

    $user = get_user_by( 'email', $email );

    if ( ! $user || ! wp_check_password( $password, $user->user_pass, $user->ID ) ) {
        // Incrementar contador de intentos
        $attempts = (int) get_transient( $attempts_key ) + 1;
        set_transient( $attempts_key, $attempts, $lockout_secs );

        if ( $attempts >= $max_attempts ) {
            set_transient( $lock_key, 1, $lockout_secs );
            set_transient( $lock_key . '_expires', time() + $lockout_secs, $lockout_secs ); // Fix: timestamp real
            delete_transient( $attempts_key );
            wp_send_json_error( [
                'message'  => 'Has superado el límite de intentos. Tu acceso está bloqueado por 15 minutos.',
                'locked'   => true,
                'attempts' => $attempts,
            ], 429 );
        }

        $remaining_attempts = $max_attempts - $attempts;
        wp_send_json_error( [
            'message'  => 'Credenciales incorrectas. Te quedan ' . $remaining_attempts . ' intento' . ( 1 === $remaining_attempts ? '' : 's' ) . '.',
            'attempts' => $attempts,
        ], 401 );
    }

    // Login exitoso → limpiar contadores
    delete_transient( $attempts_key );
    delete_transient( $lock_key );
    delete_transient( $lock_key . '_time' );

    $vx_user = VX_User::get( $user->ID );

    if ( ! $vx_user ) {
        wp_send_json_error( [ 'message' => 'Usuario no encontrado.' ], 404 );
    }

    if ( 'pendiente' === $vx_user->get_estado() ) {
        $tipo = $vx_user->get_tipo_verificacion();
        wp_send_json_error( [
            'message'  => 'Cuenta pendiente de verificación.',
            'redirect' => 'automatica' === $tipo
                ? home_url( '/confirmar-correo/' )
                : home_url( '/verificacion-pendiente/' ),
        ], 403 );
    }

    if ( 'rechazado' === $vx_user->get_estado() ) {
        wp_send_json_error( [ 'message' => 'Tu cuenta no fue aprobada.' ], 403 );
    }

    // $remember = true → cookie de 14 días; false → cookie de sesión (se borra al cerrar navegador)
    wp_set_auth_cookie( $user->ID, $remember );
    wp_set_current_user( $user->ID );

    $redirect = $vx_user->is_onboarding_completo()
        ? home_url( '/dashboard/' )
        : home_url( '/onboarding/' );

    wp_send_json_success( [ 'redirect' => $redirect ] );
}

// ─── Helper: validar URL de LinkedIn ─────────────────────────────────────────

/**
 * Comprueba que una URL pertenece al dominio linkedin.com.
 * Acepta formatos con o sin https://, con o sin www.
 *
 * @param string $url
 * @return bool  true si es una URL de LinkedIn válida (o cadena vacía)
 */
function vx_is_linkedin_url( string $url ): bool {
    $url = trim( $url );
    if ( '' === $url ) return true; // vacío = válido (campo opcional)

    // Añadir scheme si falta para poder parsear con parse_url
    if ( ! preg_match( '#^https?://#i', $url ) ) {
        $url = 'https://' . $url;
    }

    $host = strtolower( (string) parse_url( $url, PHP_URL_HOST ) );
    $host = preg_replace( '#^www\.#', '', $host );

    return $host === 'linkedin.com' || str_ends_with( $host, '.linkedin.com' );
}

// ─── REST: Guardar perfil propio ──────────────────────────────────────────────

add_action( 'rest_api_init', function () {

    register_rest_route( VX_REST_NAMESPACE, '/perfil/guardar', [
        'methods'             => 'POST',
        'callback'            => 'vx_rest_perfil_guardar',
        'permission_callback' => 'is_user_logged_in',
    ] );

    register_rest_route( VX_REST_NAMESPACE, '/empresa/crear', [
        'methods'             => 'POST',
        'callback'            => 'vx_rest_empresa_crear',
        'permission_callback' => 'is_user_logged_in',
    ] );

} );

function vx_rest_empresa_crear( WP_REST_Request $request ): WP_REST_Response
{
    $user_id = get_current_user_id();
    $data    = $request->get_json_params() ?: [];

    $nombre = sanitize_text_field( $data['nombre'] ?? '' );
    if ( ! $nombre ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'El nombre de la empresa es requerido.' ], 400 );
    }

    $post_id = wp_insert_post( [
        'post_title'  => $nombre,
        'post_type'   => 'vx_empresa',
        'post_status' => 'publish',
        'post_author' => $user_id,
    ] );

    if ( is_wp_error( $post_id ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'Error al crear la empresa.' ], 500 );
    }

    // Validar LinkedIn antes de guardar
    $linkedin_raw = trim( $data['linkedin'] ?? '' );
    if ( $linkedin_raw !== '' && ! vx_is_linkedin_url( $linkedin_raw ) ) {
        wp_delete_post( $post_id, true ); // limpiar el post creado
        return new WP_REST_Response( [ 'success' => false, 'error' => 'linkedin_invalido', 'message' => 'El LinkedIn de la empresa debe ser una URL de linkedin.com.' ], 400 );
    }

    update_post_meta( $post_id, 'vx_user_id',              $user_id );
    update_post_meta( $post_id, 'vx_cargo',               sanitize_text_field( $data['cargo']                   ?? '' ) );
    update_post_meta( $post_id, 'vx_descripcion',         sanitize_textarea_field( $data['descripcion']         ?? '' ) );
    update_post_meta( $post_id, 'vx_descripcion_cliente', sanitize_textarea_field( $data['descripcion_cliente'] ?? '' ) );
    update_post_meta( $post_id, 'vx_sector',              sanitize_text_field( $data['sector']                  ?? '' ) );
    update_post_meta( $post_id, 'vx_web',                 esc_url_raw( $data['web']                             ?? '' ) );
    update_post_meta( $post_id, 'vx_linkedin',            esc_url_raw( $linkedin_raw ) );

    // Industria (añadido — nueva empresa ahora incluye industria principal)
    if ( ! empty( $data['industria'] ) ) {
        $industria = sanitize_text_field( $data['industria'] );
        update_post_meta( $post_id, 'vx_industria', $industria );
        // Si es la primera empresa (sin empresas previas), sincronizar al user meta
        $previas = get_posts( [
            'post_type'      => 'vx_empresa',
            'post_status'    => 'publish',
            'posts_per_page' => 2,
            'fields'         => 'ids',
            'meta_query'     => [ [ 'key' => 'vx_user_id', 'value' => $user_id ] ],
        ] );
        if ( count( $previas ) <= 1 ) {
            update_user_meta( $user_id, 'vx_industria', $industria );
        }
    }

    return new WP_REST_Response( [ 'success' => true, 'empresa_id' => $post_id ], 200 );
}

if ( ! function_exists( 'vx_url_with_scheme' ) ) {
    /** Antepone https:// a una URL si no trae esquema (http/https). '' queda ''. */
    function vx_url_with_scheme( string $url ): string {
        $url = trim( $url );
        if ( $url === '' ) return '';
        if ( ! preg_match( '#^https?://#i', $url ) ) {
            $url = 'https://' . ltrim( $url, '/' );
        }
        return $url;
    }
}

function vx_rest_perfil_guardar( WP_REST_Request $request ): WP_REST_Response
{
    $user_id = get_current_user_id();
    $data    = $request->get_json_params() ?: [];

    // ── Nombre y apellido — con regeneración de slug ──────────────────────────
    $nombre_nuevo   = isset( $data['nombre'] )   ? sanitize_text_field( $data['nombre'] )   : null;
    $apellido_nuevo = isset( $data['apellido'] ) ? sanitize_text_field( $data['apellido'] ) : null;

    if ( null !== $nombre_nuevo && null !== $apellido_nuevo ) {
        $nombre_nuevo   = trim( $nombre_nuevo );
        $apellido_nuevo = trim( $apellido_nuevo );

        if ( $nombre_nuevo === '' ) {
            return new WP_REST_Response( [ 'success' => false, 'error' => 'nombre_requerido', 'message' => 'El nombre no puede estar vacío.' ], 400 );
        }
        if ( $apellido_nuevo === '' ) {
            return new WP_REST_Response( [ 'success' => false, 'error' => 'apellido_requerido', 'message' => 'El apellido no puede estar vacío.' ], 400 );
        }

        $nombre_viejo   = (string) get_user_meta( $user_id, VX_User_Meta::NOMBRE,   true );
        $apellido_viejo = (string) get_user_meta( $user_id, VX_User_Meta::APELLIDO, true );

        update_user_meta( $user_id, VX_User_Meta::NOMBRE,   $nombre_nuevo );
        update_user_meta( $user_id, VX_User_Meta::APELLIDO, $apellido_nuevo );

        // Actualizar display_name de WordPress
        wp_update_user( [ 'ID' => $user_id, 'display_name' => $nombre_nuevo . ' ' . $apellido_nuevo ] );

        // Regenerar slug solo si el nombre cambió
        if ( $nombre_nuevo !== $nombre_viejo || $apellido_nuevo !== $apellido_viejo ) {
            if ( class_exists( 'VX_Slug_Helper' ) ) {
                $nuevo_slug = VX_Slug_Helper::generate( $nombre_nuevo, $apellido_nuevo, $user_id );
                update_user_meta( $user_id, VX_User_Meta::PERFIL_SLUG, $nuevo_slug );
            }
        }
    }

    $personal = [
        'bio'                => [ VX_User_Meta::BIO,                'sanitize_textarea_field' ],
        'ciudad'             => [ VX_User_Meta::CIUDAD,             'sanitize_text_field' ],
        'pais'               => [ VX_User_Meta::PAIS,               'sanitize_text_field' ],
        'contacto_preferido' => [ VX_User_Meta::CONTACTO_PREFERIDO, 'sanitize_text_field' ],
        'offer_texto'        => [ VX_User_Meta::OFFER_TEXTO,        'sanitize_textarea_field' ],
        'seek_texto'         => [ VX_User_Meta::SEEK_TEXTO,         'sanitize_textarea_field' ],
        'telefono'           => [ VX_User_Meta::TELEFONO,           'sanitize_text_field' ],
        'linkedin'           => [ VX_User_Meta::LINKEDIN,           'esc_url_raw' ],
    ];

    // Validar LinkedIn del usuario si viene en el payload
    if ( array_key_exists( 'linkedin', $data ) && $data['linkedin'] !== '' ) {
        if ( ! vx_is_linkedin_url( $data['linkedin'] ) ) {
            return new WP_REST_Response( [ 'success' => false, 'error' => 'linkedin_invalido', 'message' => 'El enlace de LinkedIn debe ser una URL de linkedin.com.' ], 400 );
        }
    }

    foreach ( $personal as $key => [ $meta_key, $sanitizer ] ) {
        if ( ! array_key_exists( $key, $data ) ) continue;

        $valor_sanitizado = $sanitizer( $data[ $key ] );

        // Protección anti-borrado accidental: si el valor sanitizado llega vacío
        // pero el usuario ya tenía un valor guardado, preservar el valor existente.
        // El usuario puede limpiar intencionalmente un campo enviando explícitamente
        // la clave con valor '' — ese caso también queda cubierto porque la validación
        // previa habría rechazado URLs inválidas antes de llegar aquí.
        // Excepción: bio, textos y preferencias SÍ pueden guardarse vacíos (son strings libres).
        $campos_protegidos = [ 'linkedin', 'telefono' ];
        if ( in_array( $key, $campos_protegidos, true ) && '' === $valor_sanitizado ) {
            $existente = (string) get_user_meta( $user_id, $meta_key, true );
            if ( '' !== $existente ) {
                continue; // preservar el valor que ya tenía
            }
        }

        update_user_meta( $user_id, $meta_key, $valor_sanitizado );
    }

    $tags_changed = false;
    if ( array_key_exists( 'offer_tags', $data ) && is_array( $data['offer_tags'] ) ) {
        update_user_meta( $user_id, VX_User_Meta::OFFER_TAGS, array_map( 'sanitize_text_field', $data['offer_tags'] ) );
        $tags_changed = true;
    }
    if ( array_key_exists( 'seek_tags', $data ) && is_array( $data['seek_tags'] ) ) {
        update_user_meta( $user_id, VX_User_Meta::SEEK_TAGS, array_map( 'sanitize_text_field', $data['seek_tags'] ) );
        $tags_changed = true;
    }

    // Notificación in-app match_nuevo: si los tags cambiaron, avisar a usuarios que ahora hacen match
    if ( $tags_changed && class_exists( 'VX_Matches' ) && class_exists( 'VX_Notification' ) ) {
        // Programar en shutdown para no bloquear la respuesta
        add_action( 'shutdown', function() use ( $user_id ) {
            vx_dispatch_match_notifications( $user_id );
        } );
    }
    if ( array_key_exists( 'profile_tags', $data ) && is_array( $data['profile_tags'] ) ) {
        update_user_meta( $user_id, VX_User_Meta::PROFILE_TAGS, array_map( 'sanitize_text_field', $data['profile_tags'] ) );
    }
    if ( array_key_exists( 'industria', $data ) ) {
        update_user_meta( $user_id, VX_User_Meta::INDUSTRIA, sanitize_text_field( $data['industria'] ) );
    }
    if ( array_key_exists( 'comunidad_out2b', $data ) ) {
        if ( $data['comunidad_out2b'] ) {
            VX_Community::activate( $user_id, 'out2b' );
        } else {
            VX_Community::deactivate( $user_id, 'out2b' );
        }
    }
    // Senior es AUTOMÁTICO (edad 45+ o experiencia 20+): se ignora cualquier
    // intento manual de marcarla/desmarcarla y se reevalúa la calificación.
    if ( class_exists( 'VX_Senior_Verification' ) ) {
        VX_Senior_Verification::apply_auto( $user_id );
    }

    if ( ! empty( $data['empresas'] ) && is_array( $data['empresas'] ) ) {
        foreach ( $data['empresas'] as $emp ) {
            $emp_id = absint( $emp['id'] ?? 0 );
            if ( ! $emp_id ) continue;

            $post = get_post( $emp_id );
            if ( ! $post || 'vx_empresa' !== $post->post_type ) continue;
            // Propiedad: el vínculo canónico es la meta vx_user_id (así se listan las
            // empresas del miembro). Antes solo se validaba post_author, por lo que las
            // empresas creadas por un admin o por importación no se guardaban ("no persiste").
            $owner_ok = ( (int) get_post_meta( $emp_id, 'vx_user_id', true ) === $user_id )
                || ( (int) $post->post_author === $user_id );
            if ( ! $owner_ok ) continue;

            if ( isset( $emp['nombre'] ) && $emp['nombre'] !== '' ) {
                wp_update_post( [ 'ID' => $emp_id, 'post_title' => sanitize_text_field( $emp['nombre'] ) ] );
            }
            if ( isset( $emp['cargo'] ) )               update_post_meta( $emp_id, 'vx_cargo',               sanitize_text_field( $emp['cargo'] ) );
            if ( isset( $emp['descripcion'] ) )         update_post_meta( $emp_id, 'vx_descripcion',         sanitize_textarea_field( $emp['descripcion'] ) );
            if ( isset( $emp['descripcion_cliente'] ) ) update_post_meta( $emp_id, 'vx_descripcion_cliente', sanitize_textarea_field( $emp['descripcion_cliente'] ) );
            if ( isset( $emp['sector'] ) )              update_post_meta( $emp_id, 'vx_sector',              sanitize_text_field( $emp['sector'] ) );
            if ( isset( $emp['web'] ) )                 update_post_meta( $emp_id, 'vx_web',                 esc_url_raw( vx_url_with_scheme( $emp['web'] ) ) );
            if ( isset( $emp['linkedin'] ) ) {
                $lk = $emp['linkedin'] !== '' ? vx_url_with_scheme( $emp['linkedin'] ) : '';
                if ( $lk !== '' && ! vx_is_linkedin_url( $lk ) ) {
                    return new WP_REST_Response( [ 'success' => false, 'error' => 'linkedin_invalido', 'message' => 'El LinkedIn de la empresa debe ser una URL de linkedin.com.' ], 400 );
                }
                update_post_meta( $emp_id, 'vx_linkedin', esc_url_raw( $lk ) );
            }
            if ( isset( $emp['industria'] ) ) {
                $ind = sanitize_text_field( $emp['industria'] );
                update_post_meta( $emp_id, 'vx_industria', $ind );
                // Si es la empresa activa, sincronizar al user meta para filtros del directorio
                if ( get_post_meta( $emp_id, 'vx_empresa_activa', true ) ) {
                    update_user_meta( $user_id, VX_User_Meta::INDUSTRIA, $ind );
                }
            }
        }
    }

    return new WP_REST_Response( [ 'success' => true ], 200 );
}

// ─── REST: Cambiar contraseña + Cambiar email + Eliminar cuenta ──────────────

add_action( 'rest_api_init', function () {

    register_rest_route( VX_REST_NAMESPACE, '/cuenta/cambiar-password', [
        'methods'             => 'POST',
        'callback'            => 'vx_rest_cambiar_password',
        'permission_callback' => 'is_user_logged_in',
        'args' => [
            'password_actual' => [ 'required' => true ],
            'password_nuevo'  => [ 'required' => true ],
        ],
    ] );

    register_rest_route( VX_REST_NAMESPACE, '/cuenta/reset-password', [
        'methods'             => 'POST',
        'callback'            => 'vx_rest_reset_password',
        'permission_callback' => '__return_true',
        'args' => [
            'key'      => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
            'login'    => [ 'required' => true, 'sanitize_callback' => 'sanitize_user' ],
            'password' => [ 'required' => true ],
        ],
    ] );

    // POST /cuenta/cambiar-email — solicita cambio enviando confirmación al nuevo email
    register_rest_route( VX_REST_NAMESPACE, '/cuenta/cambiar-email', [
        'methods'             => 'POST',
        'callback'            => 'vx_rest_cambiar_email',
        'permission_callback' => 'is_user_logged_in',
        'args' => [
            'email_nuevo'     => [ 'required' => true, 'sanitize_callback' => 'sanitize_email' ],
            'password_actual' => [ 'required' => true ],
        ],
    ] );

    // POST /cuenta/eliminar — elimina la cuenta del usuario actual
    register_rest_route( VX_REST_NAMESPACE, '/cuenta/eliminar', [
        'methods'             => 'POST',
        'callback'            => 'vx_rest_eliminar_cuenta',
        'permission_callback' => 'is_user_logged_in',
        'args' => [
            'password_actual' => [ 'required' => true ],
            'confirmacion'    => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
        ],
    ] );

    // GET /cuenta/confirmar-email — aplica el cambio de email tras confirmar token
    register_rest_route( VX_REST_NAMESPACE, '/cuenta/confirmar-email', [
        'methods'             => 'GET',
        'callback'            => 'vx_rest_confirmar_email',
        'permission_callback' => '__return_true',
        'args' => [
            'uid'   => [ 'required' => true, 'sanitize_callback' => 'absint' ],
            'token' => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
            'email' => [ 'required' => true, 'sanitize_callback' => 'sanitize_email' ],
        ],
    ] );

} );

function vx_rest_cambiar_password( WP_REST_Request $request ): WP_REST_Response
{
    $user_id  = get_current_user_id();
    $actual   = $request->get_param( 'password_actual' );
    $nuevo    = $request->get_param( 'password_nuevo' );

    $user = get_user_by( 'id', $user_id );
    if ( ! $user ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'usuario_no_encontrado' ], 404 );
    }

    if ( ! wp_check_password( $actual, $user->user_pass, $user_id ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'password_actual_incorrecta' ], 400 );
    }

    if ( strlen( $nuevo ) < 8 ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'password_muy_corta' ], 400 );
    }

    wp_set_password( $nuevo, $user_id );
    wp_set_auth_cookie( $user_id, false );

    return new WP_REST_Response( [ 'success' => true ], 200 );
}

function vx_rest_reset_password( WP_REST_Request $request ): WP_REST_Response
{
    $key      = $request->get_param( 'key' );
    $login    = $request->get_param( 'login' );
    $password = $request->get_param( 'password' );

    $user = check_password_reset_key( $key, $login );
    if ( is_wp_error( $user ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'token_invalido' ], 400 );
    }

    if ( strlen( $password ) < 8 ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'password_muy_corta' ], 400 );
    }

    reset_password( $user, $password );

    return new WP_REST_Response( [
        'success'  => true,
        'redirect' => home_url( '/login/?reset=ok' ),
    ], 200 );
}

// ─── AJAX: Reset password request (WP built-in) ──────────────────────────────

add_action( 'wp_ajax_nopriv_vx_reset_password', 'vx_ajax_reset_password_request' );

function vx_ajax_reset_password_request(): void
{
    $email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );

    if ( ! $email ) {
        wp_send_json_error( [ 'message' => 'Email requerido.' ] );
    }

    $user = get_user_by( 'email', $email );

    // Always respond positively to avoid email enumeration
    if ( $user ) {
        // Reemplazar el email genérico de WP por uno branded de Vitrinexo
        $nombre_usuario = $user->display_name ?: $user->user_login;
        add_filter( 'retrieve_password_title', fn() => 'Recupera tu acceso a Vitrinexo' );
        add_filter( 'retrieve_password_message', function( $message, $key, $login, $user_data ) use ( $nombre_usuario ) {
            $reset_link = network_site_url( 'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode( $login ), 'login' );

            // Redirigir al formulario de nueva contraseña de Vitrinexo
            $vx_reset_link = add_query_arg( [
                'key'   => $key,
                'login' => rawurlencode( $login ),
            ], home_url( '/nueva-contrasena/' ) );

            return VX_Email_Templates::render( 'reset_password', [
                'nombre'     => $nombre_usuario,
                'reset_link' => $vx_reset_link,
            ] );
        }, 10, 4 );

        // Necesario para que wp_mail envíe HTML
        add_filter( 'wp_mail_content_type', fn() => 'text/html' );
        add_action( 'wp_mail_failed', fn() => null ); // sin-op por si falla

        retrieve_password( $user->user_login );

        // Limpiar filtros para no afectar otros emails
        remove_all_filters( 'retrieve_password_title' );
        remove_all_filters( 'retrieve_password_message' );
        remove_all_filters( 'wp_mail_content_type' );
    }

    wp_send_json_success();
}

// ─── REST: Cambiar email ──────────────────────────────────────────────────────

function vx_rest_cambiar_email( WP_REST_Request $request ): WP_REST_Response
{
    $user_id    = get_current_user_id();
    $email_nuevo = sanitize_email( $request->get_param( 'email_nuevo' ) );
    $password   = $request->get_param( 'password_actual' );

    if ( ! is_email( $email_nuevo ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'email_invalido' ], 400 );
    }

    // Verificar que el nuevo email no esté en uso
    if ( email_exists( $email_nuevo ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'email_en_uso' ], 409 );
    }

    // Verificar contraseña actual
    $user = get_user_by( 'id', $user_id );
    if ( ! $user || ! wp_check_password( $password, $user->user_pass, $user_id ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'password_incorrecta' ], 400 );
    }

    // Generar token de un solo uso y guardarlo en user meta
    $token = bin2hex( random_bytes( 16 ) );
    update_user_meta( $user_id, 'vx_email_change_token', $token );
    update_user_meta( $user_id, 'vx_email_change_nuevo',  $email_nuevo );
    update_user_meta( $user_id, 'vx_email_change_expiry', time() + HOUR_IN_SECONDS );

    // Enlace de confirmación
    $confirm_url = add_query_arg( [
        'uid'   => $user_id,
        'token' => $token,
        'email' => urlencode( $email_nuevo ),
    ], rest_url( VX_REST_NAMESPACE . '/cuenta/confirmar-email' ) );

    $vx_user = VX_User::get( $user_id );
    VX_Mailer::send(
        $email_nuevo,
        'Confirma tu nuevo email en Vitrinexo',
        'cambio_email',
        [
            'nombre'       => $vx_user ? $vx_user->get_nombre() : '',
            'email_nuevo'  => $email_nuevo,
            'email_actual' => $user->user_email,
            'link'         => $confirm_url,
        ]
    );

    return new WP_REST_Response( [ 'success' => true ], 200 );
}

function vx_rest_confirmar_email( WP_REST_Request $request ): void
{
    $user_id    = absint( $request->get_param( 'uid' ) );
    $token      = $request->get_param( 'token' );
    $email_nuevo = sanitize_email( urldecode( $request->get_param( 'email' ) ) );

    $stored_token  = get_user_meta( $user_id, 'vx_email_change_token',  true );
    $stored_email  = get_user_meta( $user_id, 'vx_email_change_nuevo',  true );
    $stored_expiry = (int) get_user_meta( $user_id, 'vx_email_change_expiry', true );

    if ( ! $stored_token
        || ! hash_equals( (string) $stored_token, $token )
        || $stored_email !== $email_nuevo
        || $stored_expiry < time()
    ) {
        wp_safe_redirect( home_url( '/configuracion/?email_error=token_invalido' ) );
        exit;
    }

    // Aplicar el cambio
    wp_update_user( [ 'ID' => $user_id, 'user_email' => $email_nuevo, 'user_login' => $email_nuevo ] );

    // Limpiar tokens temporales
    delete_user_meta( $user_id, 'vx_email_change_token' );
    delete_user_meta( $user_id, 'vx_email_change_nuevo' );
    delete_user_meta( $user_id, 'vx_email_change_expiry' );

    // Renovar cookie de autenticación con el nuevo login
    wp_set_auth_cookie( $user_id, false );

    wp_safe_redirect( home_url( '/configuracion/?email_cambiado=1' ) );
    exit;
}

// ─── REST: Eliminar cuenta ────────────────────────────────────────────────────

function vx_rest_eliminar_cuenta( WP_REST_Request $request ): WP_REST_Response
{
    $user_id      = get_current_user_id();
    $password     = $request->get_param( 'password_actual' );
    $confirmacion = $request->get_param( 'confirmacion' );

    if ( 'ELIMINAR' !== $confirmacion ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'confirmacion_invalida' ], 400 );
    }

    $user = get_user_by( 'id', $user_id );
    if ( ! $user || ! wp_check_password( $password, $user->user_pass, $user_id ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'password_incorrecta' ], 400 );
    }

    // No permitir eliminar admins
    if ( user_can( $user_id, 'manage_options' ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'no_autorizado' ], 403 );
    }

    // ── Limpiar datos del usuario ─────────────────────────────────────────────

    // Empresas del usuario
    $empresas = get_posts( [
        'post_type'      => 'vx_empresa',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [ [ 'key' => 'vx_user_id', 'value' => $user_id ] ],
    ] );
    foreach ( $empresas as $emp_id ) {
        wp_delete_post( $emp_id, true );
    }

    // Notificaciones
    $notifs = get_posts( [
        'post_type'      => 'vx_notification',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [ [ 'key' => 'vx_notif_user_id', 'value' => $user_id ] ],
    ] );
    foreach ( $notifs as $nid ) {
        wp_delete_post( $nid, true );
    }

    // Invitaciones dinner
    $invites = get_posts( [
        'post_type'      => 'vx_dinner_invite',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [ [ 'key' => VX_Dinner_Invite::USER_ID, 'value' => $user_id ] ],
    ] );
    foreach ( $invites as $iid ) {
        wp_delete_post( $iid, true );
    }

    // Conexiones (como emisor o receptor)
    $conexiones = get_posts( [
        'post_type'      => 'vx_conexion',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [
            'relation' => 'OR',
            [ 'key' => VX_Connection_Meta::EMISOR_ID,   'value' => $user_id ],
            [ 'key' => VX_Connection_Meta::RECEPTOR_ID, 'value' => $user_id ],
        ],
    ] );
    foreach ( $conexiones as $cid ) {
        wp_delete_post( $cid, true );
    }

    // Quitar de favoritos de otros usuarios
    $que_guardan_al_usuario = get_users( [
        'meta_key'   => VX_User_Meta::FAVORITOS,
        'meta_value' => $user_id,
        'fields'     => 'ids',
        'number'     => -1,
    ] );
    foreach ( $que_guardan_al_usuario as $uid ) {
        $favs = array_diff( (array) get_user_meta( $uid, VX_User_Meta::FAVORITOS, true ), [ $user_id ] );
        update_user_meta( $uid, VX_User_Meta::FAVORITOS, array_values( $favs ) );
    }

    // Email de despedida (antes de eliminar — aún tenemos los datos)
    $nombre_usuario = $user->display_name;
    $email_usuario  = $user->user_email;
    VX_Mailer::send(
        $email_usuario,
        'Tu cuenta de Vitrinexo ha sido eliminada',
        'eliminacion_cuenta',
        [
            'nombre' => $nombre_usuario,
            'email'  => $email_usuario,
        ]
    );

    // Cerrar sesión y eliminar usuario
    wp_logout();
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user( $user_id );

    return new WP_REST_Response( [ 'success' => true ], 200 );
}

// ─── Helper: notificaciones in-app de match_nuevo ────────────────────────────

/**
 * Cuando un usuario actualiza sus tags de oferta/búsqueda, calcula qué otros
 * usuarios hacen match bidireccional con él/ella y les envía una notificación
 * in-app de tipo 'match_nuevo', si aún no la tienen sin leer.
 *
 * Se ejecuta en 'shutdown' para no bloquear la respuesta REST.
 *
 * @param int $user_id  ID del usuario que actualizó sus tags.
 */
function vx_dispatch_match_notifications( int $user_id ): void
{
    if ( ! class_exists( 'VX_User' ) || ! class_exists( 'VX_Notification' ) ) return;

    $user = VX_User::get( $user_id );
    if ( ! $user ) return;

    $offer_tags = $user->get_offer_tags(); // lo que este usuario ofrece
    $seek_tags  = $user->get_seek_tags();  // lo que este usuario busca

    if ( empty( $offer_tags ) && empty( $seek_tags ) ) return;

    // Buscar usuarios activos con onboarding completo (excluye al propio usuario)
    $candidates = get_users( [
        'role'       => 'subscriber',
        'exclude'    => [ $user_id ],
        'fields'     => 'ids',
        'number'     => -1,
        'meta_query' => [
            'relation' => 'AND',
            [ 'key' => VX_User_Meta::ESTADO,              'value' => 'activo' ],
            [ 'key' => VX_User_Meta::ONBOARDING_COMPLETO, 'value' => '1' ],
        ],
    ] );

    $notificados = 0;

    foreach ( $candidates as $cid ) {
        $c = VX_User::get( (int) $cid );
        if ( ! $c ) continue;

        $c_offer = $c->get_offer_tags();
        $c_seek  = $c->get_seek_tags();

        // Hay match si: el usuario actualizado ofrece algo que el candidato busca
        //               O el candidato ofrece algo que el usuario actualizado busca
        $match_fwd = ! empty( $offer_tags ) && ! empty( $c_seek  ) && array_intersect( $offer_tags, $c_seek  );
        $match_rev = ! empty( $seek_tags  ) && ! empty( $c_offer ) && array_intersect( $seek_tags,  $c_offer );

        if ( ! $match_fwd && ! $match_rev ) continue;

        // No crear duplicados: si ya existe una notif match_nuevo sin leer de este usuario → saltar
        $existing = get_posts( [
            'post_type'      => 'vx_notification',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'AND',
                [ 'key' => 'vx_notif_user_id', 'value' => (int) $cid ],
                [ 'key' => 'vx_notif_tipo',    'value' => 'match_nuevo' ],
                [ 'key' => 'vx_notif_leida',   'value' => '0' ],
                [ 'key' => 'vx_notif_actor_id','value' => $user_id ],
            ],
        ] );
        if ( $existing ) continue;

        VX_Notification::create(
            (int) $cid,
            'match_nuevo',
            home_url( '/nexos/' ),
            $user_id,
            [ 'actor_name' => $user->get_nombre_completo() ]
        );

        $notificados++;
        if ( $notificados >= 50 ) break; // límite de seguridad por llamada
    }
}
