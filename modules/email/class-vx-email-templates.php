<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Templates de email con CSS inline.
 * Cada template recibe datos y devuelve HTML completo para enviar por correo.
 * Los CSS DEBEN ser inline — requerimiento de compatibilidad con Gmail/Outlook.
 */
class VX_Email_Templates
{
    /**
     * Dispatcher — llama al método del template correcto.
     *
     * @param string $template
     * @param array  $data
     * @return string  HTML del email o '' si no existe.
     */
    public static function render_plain( string $html_body ): string
    {
        return self::wrapper(
            '<p style="margin:0 0 20px;font-size:16px;line-height:1.7;color:#14161F;">' . $html_body . '</p>'
        );
    }

    /**
     * Renderiza texto plano a HTML, pero convierte cualquier línea que sea
     * solo una URL en un botón (ocultando el enlace largo). El resto del
     * texto se escapa y se separa en párrafos por líneas en blanco.
     *
     * @param string $text          Texto plano con {{variables}} ya reemplazadas.
     * @param string $button_label  Etiqueta del botón para las URLs detectadas.
     */
    public static function render_plain_linked( string $text, string $button_label = 'Continuar' ): string
    {
        $lines  = preg_split( '/\r\n|\r|\n/', $text );
        $blocks = '';
        $buffer = [];

        $flush = static function () use ( &$buffer, &$blocks ) {
            if ( empty( $buffer ) ) {
                return;
            }
            $blocks .= '<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#14161F;">'
                . implode( '<br>', $buffer ) . '</p>';
            $buffer = [];
        };

        foreach ( $lines as $line ) {
            $trimmed = trim( $line );
            if ( '' === $trimmed ) {
                $flush();
                continue;
            }
            // Línea que es únicamente una URL → botón (enlace oculto).
            if ( preg_match( '#^https?://\S+$#', $trimmed ) ) {
                $flush();
                $blocks .= '<div style="text-align:center;margin:28px 0;">'
                    . self::btn( $trimmed, $button_label )
                    . '</div>';
            } else {
                $buffer[] = esc_html( $line );
            }
        }
        $flush();

        return self::wrapper( $blocks );
    }

    public static function render( string $template, array $data ): string
    {
        $method = 'tpl_' . str_replace( '-', '_', $template );
        if ( method_exists( self::class, $method ) ) {
            return self::$method( $data );
        }
        return '';
    }

    // ── Estructura base ──────────────────────────────────────────

    private static function wrapper( string $content ): string
    {
        return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Vitrinexo</title></head>
<body style="margin:0;padding:0;background:#DDE8F6;font-family:\'Switzer\',-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#DDE8F6;">
<tr><td align="center" style="padding:32px 16px;">
<table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;">
<tr><td style="background:#ffffff;padding:24px 32px;border-bottom:3px solid #FF7A4D;">
<img src="' . esc_url( VX_PLUGIN_URL . 'assets/img/vitrinexo-email.png' ) . '"
     alt="Vitrinexo" width="144" height="52" style="display:block;">
</td></tr>
<tr><td style="padding:32px;">' . $content . '</td></tr>
<tr><td style="background:#F4F1EC;padding:20px 32px;border-top:1px solid #E6E2DB;">
<p style="margin:0;font-size:12px;color:#5C5F6B;text-align:center;">
© ' . wp_date('Y') . ' Vitrinexo SpA &nbsp;·&nbsp;
<a href="' . home_url('/privacidad/') . '" style="color:#5C5F6B;text-decoration:none;">Privacidad</a> &nbsp;·&nbsp;
<a href="' . home_url('/terminos/') . '" style="color:#5C5F6B;text-decoration:none;">Términos</a>
</p></td></tr>
</table></td></tr></table></body></html>';
    }

    private static function btn( string $url, string $label, string $color = '#FF7A4D' ): string
    {
        return '<a href="' . esc_url( $url ) . '" style="display:inline-block;background:' . $color . ';color:#ffffff;text-decoration:none;padding:13px 28px;border-radius:999px;font-size:14px;font-weight:600;">' . esc_html( $label ) . '</a>';
    }

    private static function h1( string $text ): string
    {
        return '<h1 style="margin:0 0 8px;font-size:22px;font-weight:700;color:#14161F;">' . esc_html( $text ) . '</h1>';
    }

    private static function p( string $text ): string
    {
        return '<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#14161F;">' . wp_kses_post( $text ) . '</p>';
    }

    // ── Templates ────────────────────────────────────────────────

    /**
     * Confirmación de email (email institucional).
     * $data: nombre, link
     */
    private static function tpl_confirmacion( array $d ): string
    {
        $nombre = esc_html( $d['nombre'] ?? 'hola' );
        $link   = esc_url( $d['link'] ?? '' );

        $content = self::h1( "Hola $nombre, confirma tu email" )
            . self::p( "Estás a un clic de activar tu cuenta en Vitrinexo. Haz clic en el botón para confirmar tu email y acceder al directorio B2B hispanohablante." )
            . '<div style="text-align:center;margin:28px 0;">'
            . self::btn( $link, 'Confirmar mi email' )
            . '</div>'
            . self::p( '<small style="color:#5C5F6B;">Este enlace expira en 24 horas. Si no creaste esta cuenta, ignora este mensaje.</small>' );

        return self::wrapper( $content );
    }

    /**
     * Aprobación de cuenta manual (email genérico, aprobado por admin).
     * $data: nombre, link
     */
    private static function tpl_aprobacion( array $d ): string
    {
        $nombre = esc_html( $d['nombre'] ?? 'hola' );
        $link   = esc_url( $d['link'] ?? '' );

        $content = self::h1( "¡Buenas noticias, $nombre!" )
            . self::p( "Tu cuenta en Vitrinexo ha sido revisada y aprobada por nuestro equipo. Ya está activa: ingresa y completa tu perfil." )
            . '<div style="text-align:center;margin:28px 0;">'
            . self::btn( $link, 'Ingresar a Vitrinexo' )
            . '</div>';

        return self::wrapper( $content );
    }

    /**
     * Bienvenida post-activación.
     * $data: nombre
     */
    private static function tpl_bienvenida( array $d ): string
    {
        $nombre = esc_html( $d['nombre'] ?? 'hola' );

        $steps = [
            [ '1', 'Completa tu perfil', 'Agrega tu foto, bio y empresa para causar buena impresión.' ],
            [ '2', 'Define tus tags', 'Qué ofreces y qué buscas: son la clave de tus nexos.' ],
            [ '3', 'Explora el directorio', 'Mira quién ya está adentro y a quién te conviene escribirle.' ],
        ];

        $steps_html = '<table width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0;">';
        foreach ( $steps as [$icon, $title, $desc] ) {
            $steps_html .= '<tr><td style="padding:10px 0;vertical-align:top;width:44px;font-size:22px;">' . $icon . '</td>
            <td style="padding:10px 0 10px 8px;">
            <strong style="font-size:14px;color:#14161F;display:block;">' . $title . '</strong>
            <span style="font-size:13px;color:#5C5F6B;">' . $desc . '</span></td></tr>';
        }
        $steps_html .= '</table>';

        $content = self::h1( "¡Bienvenido a Vitrinexo, $nombre!" )
            . self::p( "Tu cuenta está activa. Empieza a construir tu vitrina y encuentra tus nexos B2B." )
            . $steps_html
            . '<div style="text-align:center;margin:28px 0;">'
            . self::btn( home_url( '/onboarding/' ), 'Completar mi perfil' )
            . '</div>';

        return self::wrapper( $content );
    }

    /**
     * Notificación de solicitud de conexión recibida.
     * $data: receptor_nombre, emisor_nombre, emisor_empresa, pitch, token_aceptar, token_rechazar
     */
    private static function tpl_conexion_recibida( array $d ): string
    {
        $receptor     = esc_html( $d['receptor_nombre'] ?? '' );
        $emisor       = esc_html( $d['emisor_nombre'] ?? '' );
        $empresa      = esc_html( $d['emisor_empresa'] ?? '' );
        $pitch        = esc_html( $d['pitch'] ?? '' );
        $perfil_url   = esc_url( $d['emisor_perfil_url'] ?? '' );
        $url_aceptar  = esc_url( add_query_arg( 'token', $d['token_aceptar']  ?? '', rest_url( VX_REST_NAMESPACE . '/conexiones/aceptar' ) ) );
        $url_rechazar = esc_url( add_query_arg( 'token', $d['token_rechazar'] ?? '', rest_url( VX_REST_NAMESPACE . '/conexiones/rechazar' ) ) );

        $ver_perfil = $perfil_url
            ? '<p style="margin:16px 0 0;font-size:14px;"><a href="' . $perfil_url . '" style="color:#AD4319;text-decoration:none;font-weight:600;">Ver el perfil de ' . $emisor . '</a></p>'
            : '';

        $content = self::h1( "$receptor, tienes una solicitud de conexión" )
            . self::p( "<strong>$emisor</strong>" . ( $empresa ? " de <strong>$empresa</strong>" : '' ) . " quiere conectar contigo." )
            . '<div style="background:#F4F1EC;border-left:4px solid #FF7A4D;padding:16px 20px;border-radius:0 8px 8px 0;margin:20px 0;">'
            . '<p style="margin:0;font-size:14px;color:#14161F;font-style:italic;">"' . $pitch . '"</p></div>'
            . '<p style="font-size:13px;color:#5C5F6B;margin:0 0 24px;">Sus datos de contacto se revelarán <strong>solo si aceptas</strong>. Puedes revisar su perfil público antes de decidir.</p>'
            . '<table cellpadding="0" cellspacing="0"><tr>'
            . '<td style="padding-right:12px;">' . self::btn( $url_aceptar, 'Aceptar', '#7DBF9E' ) . '</td>'
            . '<td><a href="' . $url_rechazar . '" style="display:inline-block;color:#5C5F6B;text-decoration:none;padding:13px 24px;border:1.5px solid #E6E2DB;border-radius:999px;font-size:14px;">Rechazar</a></td>'
            . '</tr></table>'
            . $ver_perfil;

        return self::wrapper( $content );
    }

    /**
     * Un admin propone actualizar campos del perfil (cualquiera de
     * VX_Profile_Fields::definitions(), no solo tags).
     * $data: nombre, user_id, token, campos ([post_key => valor])
     * El link solo enruta a la página de confirmación logueada — no aplica nada por sí solo.
     */
    private static function tpl_perfil_propuesta( array $d ): string
    {
        $nombre  = esc_html( $d['nombre'] ?? '' );
        $campos  = (array) ( $d['campos'] ?? [] );
        $url_ver = esc_url( add_query_arg(
            [ 'uid' => (int) ( $d['user_id'] ?? 0 ), 'token' => $d['token'] ?? '' ],
            rest_url( VX_REST_NAMESPACE . '/perfil/propuesta/ver' )
        ) );

        $campos_html = '';
        if ( class_exists( 'VX_Profile_Fields' ) ) {
            foreach ( VX_Profile_Fields::definitions() as $def ) {
                if ( ! array_key_exists( $def['post_key'], $campos ) ) continue;
                $valor = $campos[ $def['post_key'] ];
                $texto = is_array( $valor ) ? implode( ', ', $valor ) : (string) $valor;
                if ( '' === $texto ) continue;
                $campos_html .= '<p style="margin:0 0 8px;font-size:14px;color:#14161F;"><strong>' . esc_html( $def['label'] ) . ':</strong> ' . esc_html( $texto ) . '</p>';
            }
        }

        $content = self::h1( "$nombre, un admin sugiere actualizar tu perfil" )
            . self::p( "Nuestro equipo revisó tu perfil y propone estos cambios para mejorar tu presencia en el directorio. Tú decides si los aceptas." )
            . '<div style="background:#F4F1EC;border-left:4px solid #FF7A4D;padding:16px 20px;border-radius:0 8px 8px 0;margin:20px 0;">'
            . $campos_html
            . '</div>'
            . '<div style="text-align:center;margin:28px 0;">'
            . self::btn( $url_ver, 'Ver propuesta y responder' )
            . '</div>'
            . self::p( '<small style="color:#5C5F6B;">No se aplicará ningún cambio hasta que tú lo apruebes desde tu cuenta.</small>' );

        return self::wrapper( $content );
    }

    /**
     * Confirmación de conexión aceptada — enviada al emisor con datos del receptor.
     * $data: emisor_nombre, receptor_nombre, contacto (array con email, telefono, linkedin, preferido)
     */
    private static function tpl_conexion_aceptada( array $d ): string
    {
        $emisor   = esc_html( $d['emisor_nombre'] ?? '' );
        $receptor = esc_html( $d['receptor_nombre'] ?? '' );
        $contacto = $d['contacto'] ?? [];
        $mensaje  = trim( (string) ( $d['mensaje'] ?? '' ) );

        $mensaje_html = $mensaje
            ? '<div style="background:#F4F1EC;border-left:4px solid #FF7A4D;padding:14px 18px;border-radius:0 8px 8px 0;margin:0 0 16px;">'
              . '<p style="margin:0 0 4px;font-size:11px;font-weight:700;color:#AD4319;text-transform:uppercase;letter-spacing:.5px;">Mensaje de ' . $receptor . '</p>'
              . '<p style="margin:0;font-size:14px;color:#14161F;font-style:italic;">"' . esc_html( $mensaje ) . '"</p>'
              . '</div>'
            : '';

        $links = '';
        if ( ! empty( $contacto['email'] ) ) {
            $links .= '<a href="mailto:' . esc_attr( $contacto['email'] ) . '" style="display:inline-block;margin:4px;padding:8px 16px;background:#CBE5D8;border:1.5px solid #7DBF9E;border-radius:999px;font-size:13px;color:#14161F;text-decoration:none;">' . esc_html( $contacto['email'] ) . '</a>';
        }
        if ( ! empty( $contacto['linkedin'] ) ) {
            $links .= '<a href="' . esc_url( $contacto['linkedin'] ) . '" style="display:inline-block;margin:4px;padding:8px 16px;background:#EAE7FF;border:1.5px solid #A6A0D2;border-radius:999px;font-size:13px;color:#A6A0D2;text-decoration:none;">in LinkedIn</a>';
        }
        if ( ! empty( $contacto['telefono'] ) ) {
            $links .= '<a href="tel:' . esc_attr( str_replace( ' ', '', $contacto['telefono'] ) ) . '" style="display:inline-block;margin:4px;padding:8px 16px;background:#F4F1EC;border:1.5px solid #E6E2DB;border-radius:999px;font-size:13px;color:#14161F;text-decoration:none;">' . esc_html( $contacto['telefono'] ) . '</a>';
        }

        $content = self::h1( "¡$receptor aceptó tu conexión!" )
            . $mensaje_html
            . self::p( "Ya tienen contacto mutuo. Estos son sus datos:" )
            . '<div style="background:#CBE5D8;border-radius:12px;padding:20px;margin:20px 0;">'
            . '<p style="margin:0 0 12px;font-size:16px;font-weight:600;color:#14161F;">' . $receptor . '</p>'
            . $links
            . '</div>'
            . '<div style="text-align:center;margin:24px 0;">'
            . self::btn( home_url( '/conexiones/' ), 'Ver mis conexiones' )
            . '</div>';

        return self::wrapper( $content );
    }

    /**
     * Recordatorio de conexión pendiente (72h sin respuesta).
     * $data: receptor_nombre, emisor_nombre, pitch, token_aceptar, token_rechazar
     */
    private static function tpl_recordatorio_conexion( array $d ): string
    {
        $receptor     = esc_html( $d['receptor_nombre'] ?? '' );
        $emisor       = esc_html( $d['emisor_nombre'] ?? '' );
        $pitch        = esc_html( $d['pitch'] ?? '' );
        $url_aceptar  = esc_url( add_query_arg( 'token', $d['token_aceptar']  ?? '', rest_url( VX_REST_NAMESPACE . '/conexiones/aceptar' ) ) );
        $url_rechazar = esc_url( add_query_arg( 'token', $d['token_rechazar'] ?? '', rest_url( VX_REST_NAMESPACE . '/conexiones/rechazar' ) ) );

        $content = self::h1( "Recordatorio: $emisor espera tu respuesta" )
            . self::p( "Hace 3 días te llegó una solicitud de conexión de <strong>$emisor</strong> y aún no ha sido respondida." )
            . '<div style="background:#F4F1EC;border-left:4px solid #FF7A4D;padding:16px 20px;border-radius:0 8px 8px 0;margin:20px 0;">'
            . '<p style="margin:0;font-size:14px;color:#14161F;font-style:italic;">"' . $pitch . '"</p></div>'
            . '<table cellpadding="0" cellspacing="0"><tr>'
            . '<td style="padding-right:12px;">' . self::btn( $url_aceptar, 'Aceptar', '#7DBF9E' ) . '</td>'
            . '<td><a href="' . $url_rechazar . '" style="display:inline-block;color:#5C5F6B;text-decoration:none;padding:13px 24px;border:1.5px solid #E6E2DB;border-radius:999px;font-size:14px;">Rechazar</a></td>'
            . '</tr></table>';

        return self::wrapper( $content );
    }

    /**
     * Resumen semanal de matches.
     * $data: nombre, seeks_matches (array de usuarios), offers_matches (array de usuarios)
     */
    private static function tpl_match_semanal( array $d ): string
    {
        $nombre        = esc_html( $d['nombre'] ?? '' );
        $seeks_matches = $d['seeks_matches'] ?? [];
        $offers_matches = $d['offers_matches'] ?? [];
        $total         = count( $seeks_matches ) + count( $offers_matches );

        if ( $total === 0 ) {
            return '';
        }

        $content = self::h1( "Tus nuevos nexos de la semana, $nombre" )
            . self::p( "Esta semana encontramos <strong>$total nuevo" . ( $total > 1 ? 's nexos' : ' nexo' ) . "</strong> para ti." );

        if ( ! empty( $seeks_matches ) ) {
            $content .= '<p style="font-size:13px;font-weight:700;color:#14161F;margin:16px 0 8px;text-transform:uppercase;letter-spacing:.5px;">Ofrecen lo que buscas</p>';
            foreach ( array_slice( $seeks_matches, 0, 3 ) as $user ) {
                $content .= '<div style="display:flex;align-items:center;padding:10px 0;border-bottom:1px solid #E6E2DB;">'
                    . '<div style="width:40px;height:40px;border-radius:50%;background:#E6E2DB;flex-shrink:0;overflow:hidden;">'
                    . '<img src="' . esc_url( $user['foto_url'] ?? '' ) . '" width="40" height="40" style="border-radius:50%;object-fit:cover;"></div>'
                    . '<div style="margin-left:12px;flex:1;">'
                    . '<strong style="font-size:14px;color:#14161F;">' . esc_html( $user['nombre'] ?? '' ) . '</strong>'
                    . '<div style="font-size:12px;color:#5C5F6B;">' . esc_html( $user['empresa'] ?? '' ) . '</div></div></div>';
            }
        }

        if ( ! empty( $offers_matches ) ) {
            $content .= '<p style="font-size:13px;font-weight:700;color:#14161F;margin:16px 0 8px;text-transform:uppercase;letter-spacing:.5px;">Buscan lo que ofreces</p>';
            foreach ( array_slice( $offers_matches, 0, 3 ) as $user ) {
                $content .= '<div style="display:flex;align-items:center;padding:10px 0;border-bottom:1px solid #E6E2DB;">'
                    . '<div style="width:40px;height:40px;border-radius:50%;background:#E6E2DB;flex-shrink:0;overflow:hidden;">'
                    . '<img src="' . esc_url( $user['foto_url'] ?? '' ) . '" width="40" height="40" style="border-radius:50%;object-fit:cover;"></div>'
                    . '<div style="margin-left:12px;flex:1;">'
                    . '<strong style="font-size:14px;color:#14161F;">' . esc_html( $user['nombre'] ?? '' ) . '</strong>'
                    . '<div style="font-size:12px;color:#5C5F6B;">' . esc_html( $user['empresa'] ?? '' ) . '</div></div></div>';
            }
        }

        $content .= '<div style="text-align:center;margin:28px 0;">'
            . self::btn( home_url( '/nexos/' ), 'Ver todos mis nexos' )
            . '</div>';

        return self::wrapper( $content );
    }

    /**
     * Notificación al admin de solicitud Senior.
     * $data: nombre_completo, email, approve_url
     */
    private static function tpl_admin_senior_request( array $d ): string
    {
        $nombre      = esc_html( $d['nombre_completo'] ?? '' );
        $email       = esc_html( $d['email'] ?? '' );
        $approve_url = esc_url( $d['approve_url'] ?? '' );

        $content = self::h1( 'Solicitud de verificación de Vitrinexo Senior' )
            . self::p( "<strong>$nombre</strong> ($email) solicita el distintivo de Vitrinexo Senior." )
            . '<div style="text-align:center;margin:28px 0;">'
            . self::btn( $approve_url, 'Aprobar la verificación de Vitrinexo Senior' )
            . '</div>';

        return self::wrapper( $content );
    }

    /**
     * Recordatorio diario de validaciones pendientes (9 AM → admin).
     * $data: n_cuentas, n_senior, lista_cuentas (array), lista_senior (array), validaciones_url
     */
    private static function tpl_admin_validaciones_reminder( array $d ): string
    {
        $n_cuentas       = (int) ( $d['n_cuentas'] ?? 0 );
        $n_senior        = (int) ( $d['n_senior']  ?? 0 );
        $lista_cuentas   = $d['lista_cuentas'] ?? [];
        $lista_senior    = $d['lista_senior']  ?? [];
        $url             = esc_url( $d['validaciones_url'] ?? admin_url( 'admin.php?page=vx-validaciones' ) );
        $total           = $n_cuentas + $n_senior;

        $content = self::h1( '' . $total . ' validación' . ( $total !== 1 ? 'es' : '' ) . ' pendiente' . ( $total !== 1 ? 's' : '' ) );
        $content .= self::p( 'Hay usuarios esperando revisión en Vitrinexo. Puedes gestionarlos desde el panel de Validaciones.' );

        // ── Sección cuentas pendientes ─────────────────────────────────────────
        if ( $n_cuentas > 0 ) {
            $content .= '<p style="font-size:13px;font-weight:700;color:#C6C167;margin:20px 0 8px;text-transform:uppercase;letter-spacing:.5px;">'
                . 'Cuentas por aprobar (' . $n_cuentas . ')</p>';

            $content .= '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-bottom:16px;">';
            $content .= '<tr style="background:#F4F1EC;">'
                . '<th style="padding:8px 12px;text-align:left;font-size:12px;color:#5C5F6B;border-bottom:1px solid #E6E2DB">Nombre</th>'
                . '<th style="padding:8px 12px;text-align:left;font-size:12px;color:#5C5F6B;border-bottom:1px solid #E6E2DB">Email</th>'
                . '<th style="padding:8px 12px;text-align:left;font-size:12px;color:#5C5F6B;border-bottom:1px solid #E6E2DB">Registro</th>'
                . '</tr>';

            foreach ( $lista_cuentas as $u ) {
                $content .= '<tr style="border-bottom:1px solid #E6E2DB;">'
                    . '<td style="padding:8px 12px;font-size:13px;color:#14161F;">' . esc_html( $u['nombre'] ?: '(sin nombre)' ) . '</td>'
                    . '<td style="padding:8px 12px;font-size:13px;color:#5C5F6B;">' . esc_html( $u['email'] ) . '</td>'
                    . '<td style="padding:8px 12px;font-size:12px;color:#5C5F6B;">' . esc_html( $u['fecha'] ) . '</td>'
                    . '</tr>';
            }
            $content .= '</table>';
        }

        // ── Sección solicitudes Senior ─────────────────────────────────────────
        if ( $n_senior > 0 ) {
            $content .= '<p style="font-size:13px;font-weight:700;color:#14161F;margin:20px 0 8px;text-transform:uppercase;letter-spacing:.5px;">'
                . 'Solicitudes de Vitrinexo Senior (' . $n_senior . ')</p>';

            $content .= '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-bottom:16px;">';
            $content .= '<tr style="background:#F4F1EC;">'
                . '<th style="padding:8px 12px;text-align:left;font-size:12px;color:#5C5F6B;border-bottom:1px solid #E6E2DB">Nombre</th>'
                . '<th style="padding:8px 12px;text-align:left;font-size:12px;color:#5C5F6B;border-bottom:1px solid #E6E2DB">Email</th>'
                . '<th style="padding:8px 12px;text-align:left;font-size:12px;color:#5C5F6B;border-bottom:1px solid #E6E2DB">Industria</th>'
                . '</tr>';

            foreach ( $lista_senior as $u ) {
                $content .= '<tr style="border-bottom:1px solid #E6E2DB;">'
                    . '<td style="padding:8px 12px;font-size:13px;color:#14161F;">' . esc_html( $u['nombre'] ?: '(sin nombre)' ) . '</td>'
                    . '<td style="padding:8px 12px;font-size:13px;color:#5C5F6B;">' . esc_html( $u['email'] ) . '</td>'
                    . '<td style="padding:8px 12px;font-size:12px;color:#5C5F6B;">' . esc_html( $u['industria'] ?: '—' ) . '</td>'
                    . '</tr>';
            }
            $content .= '</table>';
        }

        $content .= '<div style="text-align:center;margin:28px 0;">'
            . self::btn( $url, 'Revisar validaciones pendientes' )
            . '</div>'
            . self::p( '<small style="color:#5C5F6B;">Este recordatorio se envía cada mañana a las 09:00 solo si hay validaciones pendientes. No recibirás este email cuando todo esté al día.</small>' );

        return self::wrapper( $content );
    }

    /**
     * Notificación al admin de cuenta pendiente de aprobación.
     * $data: nombre_completo, email, approve_url
     */
    private static function tpl_admin_cuenta_pendiente( array $d ): string
    {
        $nombre      = esc_html( $d['nombre_completo'] ?? '' );
        $email       = esc_html( $d['email'] ?? '' );
        $approve_url = esc_url( $d['approve_url'] ?? '' );

        $content = self::h1( 'Nueva cuenta pendiente de aprobación' )
            . self::p( "El usuario <strong>$nombre</strong> ($email) se registró con un email personal y requiere aprobación manual." )
            . '<div style="text-align:center;margin:28px 0;">'
            . self::btn( $approve_url, 'Aprobar cuenta' )
            . '</div>';

        return self::wrapper( $content );
    }

    /**
     * Confirmación de cambio de email.
     * $data: nombre, email_nuevo, email_actual, link
     */
    private static function tpl_cambio_email( array $d ): string
    {
        $nombre       = esc_html( $d['nombre']      ?? '' );
        $email_nuevo  = esc_html( $d['email_nuevo'] ?? '' );
        $email_actual = esc_html( $d['email_actual'] ?? '' );
        $link         = esc_url( $d['link']         ?? '#' );

        $content = self::h1( "Confirma tu nuevo email, $nombre" )
            . self::p( "Recibimos una solicitud para cambiar el email de tu cuenta Vitrinexo de <strong>$email_actual</strong> a <strong>$email_nuevo</strong>." )
            . self::p( 'Si fuiste tú, haz clic en el botón para confirmar el cambio. Si no reconoces esta acción, ignora este correo, tu email actual no cambiará.' )
            . '<div style="text-align:center;margin:28px 0;">'
            . self::btn( $link, 'Confirmar nuevo email' )
            . '</div>'
            . self::p( '<small>Este enlace expira en 1 hora.</small>' );

        return self::wrapper( $content );
    }

    /**
     * Aviso de vencimiento de plan.
     * $data: nombre, days_left, fecha_vencimiento, plan_label, es_fundador, url_renovar
     */
    private static function tpl_plan_aviso_vencimiento( array $d ): string
    {
        $nombre    = esc_html( $d['nombre'] ?? '' );
        $days      = (int) ( $d['days_left'] ?? 0 );
        $fecha     = esc_html( $d['fecha_vencimiento'] ?? '' );
        $url       = esc_url( $d['url_renovar'] ?? home_url( '/configuracion/?tab=plan' ) );
        $fundador  = ! empty( $d['es_fundador'] );

        $urgencia  = match ( true ) {
            $days <= 1  => '¡Último día!',
            $days <= 7  => 'Esta semana',
            default     => 'En ' . $days . ' días',
        };

        /* Este correo le ofrecia al Pionero «activar su precio preferencial»
           antes de perder el acceso. Ya no: los primeros 100 son gratis para
           siempre y su plan no vence, asi que nunca reciben este aviso. Y si
           lo recibieran por tener una fecha puesta a mano, ofrecerles un
           precio que ya no existe seria peor que no decir nada. */
        $cta_text  = 'Renovar mi plan';
        $msg_extra = '';

        $content = self::h1( "$urgencia Tu plan vence el $fecha" )
            . self::p( "Hola $nombre, tu plan en Vitrinexo vencerá el <strong>$fecha</strong> ($days día" . ( 1 === $days ? '' : 's' ) . ' restante' . ( 1 === $days ? '' : 's' ) . ').' )
            . $msg_extra
            . '<div style="text-align:center;margin:28px 0;">'
            . self::btn( $url, $cta_text )
            . '</div>'
            . self::p( 'Si no renuevas, perderás acceso al directorio, tus conexiones y las próximas cenas de Vitrinexo 4Dinner.' );

        return self::wrapper( $content );
    }

    /**
     * Confirmación de activación de plan (post-pago).
     * $data: nombre, plan, fecha_vencimiento, es_fundador
     */
    private static function tpl_plan_activado( array $d ): string
    {
        $nombre    = esc_html( $d['nombre'] ?? '' );
        $plan      = esc_html( ucfirst( $d['plan'] ?? '' ) );
        $fecha     = esc_html( $d['fecha_vencimiento'] ?? '' );
        $fundador  = ! empty( $d['es_fundador'] );

        $badge     = $fundador ? ' <span style="background:#FAF7C9;color:#14161F;border-radius:4px;padding:2px 8px;font-size:12px;font-weight:700;">Pionero</span>' : '';

        $content = self::h1( "¡Tu plan $plan está activo, $nombre!" )
            . self::p( 'Bienvenido de vuelta. Tienes acceso completo a Vitrinexo' . ( $fecha ? ' hasta el <strong>' . $fecha . '</strong>' : ' sin fecha de vencimiento' ) . '.' . $badge )
            . '<div style="text-align:center;margin:28px 0;">'
            . self::btn( home_url( '/dashboard/' ), 'Ir a mi dashboard' )
            . '</div>'
            . self::p( 'Gracias por ser parte de la red Vitrinexo. Si tienes dudas escríbenos a hola@vitrinexo.com.' );

        return self::wrapper( $content );
    }

    /**
     * Invitación a 4Dinner (enviada por el admin).
     * $data: usuario_nombre, dinner (ciudad/pais/fecha/restaurante/direccion), url_aceptar, url_rechazar
     */
    private static function tpl_dinner_invitacion( array $d ): string
    {
        $nombre    = esc_html( $d['usuario_nombre'] ?? '' );
        $dinner    = $d['dinner'] ?? [];
        $fecha_str = ! empty( $dinner['fecha'] ) ? date_i18n( 'l j \d\e F \d\e Y', (int) $dinner['fecha'] ) : '';

        $content = self::h1( "¡$nombre, tienes una invitación a un Vitrinexo 4Dinner!" )
            . self::p( 'El equipo de Vitrinexo te ha reservado un cupo en la próxima cena. Aquí los detalles:' )
            . '<div style="background:#14161F;border-radius:12px;padding:20px;margin:20px 0;color:#fff;">'
            . '<p style="margin:0 0 4px;font-size:12px;color:#5C5F6B;text-transform:uppercase;letter-spacing:.5px;">Tu invitación</p>'
            . '<p style="margin:0 0 16px;font-size:20px;font-weight:700;">Vitrinexo 4Dinner ' . esc_html( $dinner['ciudad'] ?? '' ) . '</p>'
            . '<p style="margin:0 0 6px;font-size:14px;color:#E6E2DB;">Fecha: ' . $fecha_str . ' · 20:00</p>'
            . '<p style="margin:0;font-size:14px;color:#E6E2DB;">Lugar: ' . esc_html( ( $dinner['restaurante'] ?? '' ) . ( ! empty( $dinner['direccion'] ) ? ' · ' . $dinner['direccion'] : '' ) ) . '</p>'
            . '</div>'
            . self::p( '4 personas, 1 mesa, sin agenda formal. Solo una conversación real entre miembros verificados de Vitrinexo.' )
            . '<div style="text-align:center;margin:28px 0;display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">'
            . self::btn( $d['url_aceptar'] ?? '#', 'Acepto la invitación', '#FF7A4D' )
            . '&nbsp;'
            . '<a href="' . esc_url( $d['url_rechazar'] ?? '#' ) . '" style="display:inline-block;background:#E6E2DB;color:#5C5F6B;text-decoration:none;padding:13px 28px;border-radius:999px;font-size:14px;font-weight:600;">No puedo asistir</a>'
            . '</div>'
            . self::p( '<small>Los links son de un solo uso. Si tienes dudas escríbenos a hola@vitrinexo.com</small>' );

        return self::wrapper( $content );
    }

    /**
     * Recuperación de contraseña (branded, reemplaza el email genérico de WP).
     * $data: nombre, reset_link
     */
    private static function tpl_reset_password( array $d ): string
    {
        $nombre = esc_html( $d['nombre'] ?? 'hola' );
        $link   = esc_url( $d['reset_link'] ?? '#' );

        $content = self::h1( "Recupera tu acceso, $nombre" )
            . self::p( 'Recibimos una solicitud para restablecer la contraseña de tu cuenta en Vitrinexo. Si fuiste tú, haz clic en el botón a continuación.' )
            . '<div style="text-align:center;margin:28px 0;">'
            . self::btn( $link, 'Crear nueva contraseña' )
            . '</div>'
            . self::p( '<small style="color:#5C5F6B;">Este enlace es válido por 24 horas y solo puede usarse una vez. Si no solicitaste el cambio, ignora este mensaje, tu contraseña actual no cambiará.</small>' );

        return self::wrapper( $content );
    }

    /**
     * Confirmación de interés en un 4Dinner ("Quiero ir").
     * $data: nombre, dinner (ciudad, fecha_ts, restaurante)
     */
    private static function tpl_dinner_interes( array $d ): string
    {
        $nombre    = esc_html( $d['nombre'] ?? '' );
        $dinner    = $d['dinner'] ?? [];
        $ciudad    = esc_html( $dinner['ciudad'] ?? '' );
        $fecha_str = ! empty( $dinner['fecha'] ) ? date_i18n( 'l j \d\e F \d\e Y', (int) $dinner['fecha'] ) : '';

        $content = self::h1( "¡$nombre, recibimos tu interés!" )
            . self::p( "Tu interés en el <strong>Vitrinexo 4Dinner $ciudad</strong>" . ( $fecha_str ? " del $fecha_str" : '' ) . " quedó registrado." )
            . '<div style="background:#FAF7C9;border:1px solid #F2EC79;border-radius:12px;padding:20px;margin:20px 0;">'
            . '<p style="margin:0 0 6px;font-size:13px;font-weight:700;color:#14161F;text-transform:uppercase;letter-spacing:.5px;">¿Qué sigue?</p>'
            . '<ul style="margin:0;padding-left:18px;color:#14161F;font-size:14px;line-height:1.8;">'
            . '<li>El equipo de Vitrinexo revisa los interesados y arma las mesas.</li>'
            . '<li>Si quedas seleccionado, recibirás una <strong>invitación oficial</strong> con todos los detalles.</li>'
            . '<li>La cena es el <strong>miércoles a las 20:00</strong>, hora local.</li>'
            . '</ul>'
            . '</div>'
            . '<div style="text-align:center;margin:28px 0;">'
            . self::btn( home_url( '/4dinner/' ), 'Ver próximas cenas' )
            . '</div>'
            . self::p( '<small style="color:#5C5F6B;">¿No puedes asistir? Puedes retirar tu interés desde la página del evento.</small>' );

        return self::wrapper( $content );
    }

    /**
     * Recordatorio 24h antes del 4Dinner (para asignados confirmados).
     * $data: nombre, dinner (ciudad, fecha, restaurante, direccion), comensales (array)
     */
    private static function tpl_dinner_recordatorio( array $d ): string
    {
        $nombre      = esc_html( $d['nombre'] ?? '' );
        $dinner      = $d['dinner'] ?? [];
        $mesa_nombre = esc_html( $d['mesa_nombre'] ?? '' );
        $fecha_str   = ! empty( $dinner['fecha'] ) ? date_i18n( 'l j \d\e F', (int) $dinner['fecha'] ) : 'mañana';

        $mesa_html = $mesa_nombre
            ? '<div style="background:#F4F1EC;border-radius:8px;padding:14px 16px;margin:20px 0;border-left:4px solid #A9C6E8;">'
              . '<p style="margin:0 0 2px;font-size:11px;font-weight:700;color:#8BA3C0;text-transform:uppercase;letter-spacing:.5px;">Tu mesa asignada</p>'
              . '<p style="margin:0;font-size:18px;font-weight:700;color:#14161F;">' . $mesa_nombre . '</p>'
              . '</div>'
            : '';

        $content = self::h1( "¡$nombre, tu Vitrinexo 4Dinner es $fecha_str!" )
            . self::p( 'Solo quedan <strong>24 horas</strong>. Aquí un recordatorio de los detalles:' )
            . '<div style="background:#14161F;border-radius:12px;padding:20px;margin:20px 0;color:#fff;">'
            . '<p style="margin:0 0 4px;font-size:12px;color:#5C5F6B;text-transform:uppercase;letter-spacing:.5px;">Tu cena</p>'
            . '<p style="margin:0 0 14px;font-size:20px;font-weight:700;">Vitrinexo 4Dinner ' . esc_html( $dinner['ciudad'] ?? '' ) . '</p>'
            . '<p style="margin:0 0 6px;font-size:14px;color:#E6E2DB;">Fecha: ' . $fecha_str . ' · 20:00</p>'
            . '<p style="margin:0;font-size:14px;color:#E6E2DB;">Lugar: ' . esc_html( ( $dinner['restaurante'] ?? '' ) . ( ! empty( $dinner['direccion'] ) ? ' · ' . $dinner['direccion'] : '' ) ) . '</p>'
            . '</div>'
            . $mesa_html
            . '<div style="background:#CBE5D8;border-radius:8px;padding:14px 16px;margin:20px 0;">'
            . '<p style="margin:0;font-size:13px;color:#14161F;"><strong>Recuerda:</strong> cada quien paga su consumo. Sin agenda formal, la conversación fluye sola.</p>'
            . '</div>'
            . '<div style="text-align:center;margin:24px 0;">'
            . self::btn( home_url( '/4dinner/' ), 'Ver detalles del evento' )
            . '</div>';

        return self::wrapper( $content );
    }

    /**
     * Notificación manual de mesa asignada.
     * $data: nombre, mesa_nombre, dinner { ciudad, fecha, restaurante, direccion }
     */
    private static function tpl_dinner_mesa_asignada( array $d ): string
    {
        $nombre      = esc_html( $d['nombre'] ?? '' );
        $dinner      = $d['dinner'] ?? [];
        $mesa_nombre = esc_html( $d['mesa_nombre'] ?? '' );
        $fecha_str   = ! empty( $dinner['fecha'] ) ? date_i18n( 'l j \d\e F', (int) $dinner['fecha'] ) : '';

        $content = self::h1( "¡$nombre, ya tienes mesa asignada!" )
            . self::p( 'El equipo ya armó las mesas para el Vitrinexo 4Dinner de <strong>' . esc_html( $dinner['ciudad'] ?? '' ) . '</strong>. Esta es tu asignación:' )
            . '<div style="background:#F4F1EC;border-radius:12px;padding:20px;margin:20px 0;border-left:4px solid #A9C6E8;text-align:center;">'
            . '<p style="margin:0 0 4px;font-size:12px;font-weight:700;color:#8BA3C0;text-transform:uppercase;letter-spacing:.5px;">Tu mesa</p>'
            . '<p style="margin:0;font-size:28px;font-weight:700;color:#14161F;">' . $mesa_nombre . '</p>'
            . '</div>'
            . '<div style="background:#14161F;border-radius:12px;padding:20px;margin:20px 0;color:#fff;">'
            . '<p style="margin:0 0 4px;font-size:12px;color:#5C5F6B;text-transform:uppercase;letter-spacing:.5px;">Detalles del evento</p>'
            . ( $fecha_str ? '<p style="margin:0 0 6px;font-size:14px;color:#E6E2DB;">Fecha: ' . $fecha_str . ' · 20:00</p>' : '' )
            . '<p style="margin:0;font-size:14px;color:#E6E2DB;">Lugar: ' . esc_html( ( $dinner['restaurante'] ?? '' ) . ( ! empty( $dinner['direccion'] ) ? ' · ' . $dinner['direccion'] : '' ) ) . '</p>'
            . '</div>'
            . '<div style="background:#CBE5D8;border-radius:8px;padding:14px 16px;margin:20px 0;">'
            . '<p style="margin:0;font-size:13px;color:#14161F;"><strong>Recuerda:</strong> cada quien paga su consumo. Sin agenda formal, la conversación fluye sola.</p>'
            . '</div>'
            . '<div style="text-align:center;margin:24px 0;">'
            . self::btn( home_url( '/4dinner/' ), 'Ver detalles del evento' )
            . '</div>';

        return self::wrapper( $content );
    }

    /**
     * Aprobación del distintivo de Vitrinexo Senior.
     * $data: nombre
     */
    private static function tpl_senior_aprobado( array $d ): string
    {
        $nombre = esc_html( $d['nombre'] ?? '' );

        $content = self::h1( "¡$nombre, eres parte de Vitrinexo Senior!" )
            . self::p( 'Tu solicitud para unirte a la comunidad <strong>Vitrinexo Senior</strong> fue aprobada. Ahora tienes acceso al directorio vertical de ejecutivos con trayectoria consolidada.' )
            . '<div style="background:#FAF7C9;border:1px solid #F2EC79;border-radius:12px;padding:20px;margin:20px 0;text-align:center;">'
            . '<p style="margin:0;font-size:18px;font-weight:700;color:#14161F;">Distintivo de Vitrinexo Senior activado</p>'
            . '<p style="margin:6px 0 0;font-size:13px;color:#14161F;">Tu distintivo es visible en tu ficha de Vitrinexo.</p>'
            . '</div>'
            . self::p( 'Explora los perfiles de tu comunidad, conecta con otros ejecutivos senior y participa en las próximas cenas de Vitrinexo 4Dinner.' )
            . '<div style="text-align:center;margin:28px 0;">'
            . self::btn( home_url( '/comunidad-senior/' ), 'Ir a Vitrinexo Senior' )
            . '</div>';

        return self::wrapper( $content );
    }

    /**
     * Despedida al eliminar cuenta.
     * $data: nombre, email
     */
    private static function tpl_eliminacion_cuenta( array $d ): string
    {
        $nombre = esc_html( $d['nombre'] ?? '' );
        $email  = esc_html( $d['email']  ?? '' );

        $content = self::h1( "Lamentamos verte partir, $nombre" )
            . self::p( "Tu cuenta de Vitrinexo asociada a <strong>$email</strong> ha sido eliminada de forma permanente junto con todos tus datos, empresas y conexiones." )
            . '<div style="background:#F4F1EC;border-radius:8px;padding:14px 16px;margin:20px 0;">'
            . '<p style="margin:0;font-size:13px;color:#5C5F6B;">Si esto fue un error o quieres volver en el futuro, puedes registrarte nuevamente en vitrinexo.com. Tu distintivo Pionero no puede ser recuperado.</p>'
            . '</div>'
            . self::p( 'Gracias por haber sido parte de la red. Si tienes comentarios sobre por qué decidiste irte, escríbenos a <a href="mailto:hola@vitrinexo.com" style="color:#AD4319;">hola@vitrinexo.com</a>, nos ayuda a mejorar.' );

        return self::wrapper( $content );
    }

    /**
     * Pago fallido (para webhook de Stripe).
     * $data: nombre, plan, fecha_intento, url_actualizar
     */
    private static function tpl_plan_pago_fallido( array $d ): string
    {
        $nombre       = esc_html( $d['nombre'] ?? '' );
        $plan         = esc_html( ucfirst( $d['plan'] ?? '' ) );
        $fecha        = esc_html( $d['fecha_intento'] ?? '' );
        $url          = esc_url( $d['url_actualizar'] ?? home_url( '/configuracion/?tab=plan' ) );

        $content = self::h1( "No pudimos procesar tu pago, $nombre" )
            . self::p( "El cobro de tu plan <strong>$plan</strong>" . ( $fecha ? " del $fecha" : '' ) . " no pudo completarse. Tu acceso a Vitrinexo podría suspenderse pronto si no se regulariza." )
            . '<div style="background:#E8D1C9;border:1px solid #E8D1C9;border-radius:12px;padding:18px 20px;margin:20px 0;">'
            . '<p style="margin:0;font-size:14px;color:#AD4319;font-weight:600;">Posibles causas:</p>'
            . '<ul style="margin:8px 0 0;padding-left:18px;color:#14161F;font-size:13px;line-height:1.8;">'
            . '<li>Fondos insuficientes en la tarjeta.</li>'
            . '<li>Tarjeta vencida o bloqueada.</li>'
            . '<li>Límite de crédito alcanzado.</li>'
            . '</ul>'
            . '</div>'
            . '<div style="text-align:center;margin:28px 0;">'
            . self::btn( $url, 'Actualizar método de pago', '#AD4319' )
            . '</div>'
            . self::p( 'Si el problema persiste, escríbenos a <a href="mailto:hola@vitrinexo.com" style="color:#AD4319;">hola@vitrinexo.com</a>.' );

        return self::wrapper( $content );
    }

    /**
     * Confirmación de 4Dinner.
     * $data: usuario_nombre, dinner (array con ciudad, fecha, restaurante, direccion), comensales (array)
     */
    private static function tpl_dinner_confirmacion( array $d ): string
    {
        $nombre     = esc_html( $d['usuario_nombre'] ?? '' );
        $dinner     = $d['dinner'] ?? [];
        $comensales = $d['comensales'] ?? [];

        $comensales_html = '';
        foreach ( $comensales as $c ) {
            $comensales_html .= '<div style="display:flex;align-items:center;padding:8px 0;">'
                . '<img src="' . esc_url( $c['foto_url'] ?? '' ) . '" width="36" height="36" style="border-radius:50%;object-fit:cover;flex-shrink:0;">'
                . '<div style="margin-left:10px;">'
                . '<strong style="font-size:14px;color:#14161F;">' . esc_html( $c['nombre'] ?? '' ) . '</strong>'
                . '<div style="font-size:12px;color:#5C5F6B;">' . esc_html( $c['empresa'] ?? '' ) . '</div></div></div>';
        }

        $fecha_str = ! empty( $dinner['fecha'] ) ? date_i18n( 'l j \d\e F \d\e Y', $dinner['fecha'] ) : '';

        $content = self::h1( "¡Tu mesa para el Vitrinexo 4Dinner está confirmada, $nombre!" )
            . '<div style="background:#14161F;border-radius:12px;padding:20px;margin:20px 0;color:#fff;">'
            . '<p style="margin:0 0 4px;font-size:12px;color:#5C5F6B;text-transform:uppercase;letter-spacing:.5px;">Tu mesa</p>'
            . '<p style="margin:0 0 16px;font-size:20px;font-weight:700;">Vitrinexo 4Dinner ' . esc_html( $dinner['ciudad'] ?? '' ) . '</p>'
            . '<p style="margin:0 0 6px;font-size:14px;color:#E6E2DB;">Fecha: ' . $fecha_str . ' · 20:00</p>'
            . '<p style="margin:0;font-size:14px;color:#E6E2DB;">Lugar: ' . esc_html( $dinner['restaurante'] ?? '' ) . ' · ' . esc_html( $dinner['direccion'] ?? '' ) . '</p>'
            . '</div>'
            . '<p style="font-size:14px;font-weight:600;color:#14161F;margin:20px 0 8px;">Tus comensales:</p>'
            . $comensales_html
            . '<div style="text-align:center;margin:28px 0;">'
            . self::btn( home_url( '/mis-eventos/' ), 'Ver mis detalles en Mis cenas' )
            . '</div>'
            . self::p( 'No se requiere agenda. Es una cena informal. ¡Nos vemos ahí!' );

        return self::wrapper( $content );
    }

    // ── Notificación a administradores: nuevo registro ───────────

    private static function tpl_notificacion_admin( array $d ): string
    {
        $intro        = nl2br( esc_html( $d['intro'] ?? 'Alguien completó el formulario de inscripción.' ) );
        $nombre       = esc_html( $d['nombre'] ?? '' );
        $apellido     = esc_html( $d['apellido'] ?? '' );
        $email_u      = esc_html( $d['email_usuario'] ?? '' );
        $empresa      = esc_html( $d['empresa'] ?? '' );
        $cargo        = esc_html( $d['cargo'] ?? '' );
        $pais         = esc_html( $d['pais'] ?? '' );
        $telefono     = esc_html( $d['telefono'] ?? '' );
        $linkedin     = esc_url( $d['linkedin'] ?? '' );
        $url_aprobar  = esc_url( $d['url_aprobar'] ?? '' );
        $url_rechazar = esc_url( $d['url_rechazar'] ?? '' );

        $row = function( string $label, string $value, bool $first = false ) {
            $top = $first ? 'border-radius:6px 6px 0 0;' : 'border-top:1px solid #E6E2DB;';
            return '<tr><td style="padding:10px 12px;' . $top . 'background:#DDE8F6;font-weight:600;color:#14161F;width:130px">' . $label . '</td>'
                 . '<td style="padding:10px 12px;' . $top . 'background:#DDE8F6;color:#14161F">' . $value . '</td></tr>';
        };

        $rows  = $row( 'Nombre',   $nombre . ' ' . $apellido, true );
        $rows .= $row( 'Email',    '<a href="mailto:' . $email_u . '" style="color:#AD4319">' . $email_u . '</a>' );
        $rows .= $row( 'Empresa',  $empresa );
        $rows .= $row( 'Cargo',    $cargo );
        $rows .= $row( 'País',     $pais );
        if ( $telefono ) $rows .= $row( 'Teléfono', $telefono );
        if ( $linkedin ) $rows .= $row( 'LinkedIn', '<a href="' . $linkedin . '" style="color:#AD4319">' . $linkedin . '</a>' );

        $content = '<p style="color:#5C5F6B;margin:0 0 24px">' . $intro . '</p>'
            . '<table style="width:100%;border-collapse:collapse;margin:0 0 32px">' . $rows . '</table>'
            . '<div style="margin:0 0 24px">'
            . '<a href="' . $url_aprobar . '" style="display:inline-block;background:#FF7A4D;color:#fff;padding:14px 28px;border-radius:999px;text-decoration:none;font-weight:600;font-size:15px;margin-right:12px">Aprobar</a>'
            . '<a href="' . $url_rechazar . '" style="display:inline-block;background:#F4C6DC;color:#fff;padding:14px 28px;border-radius:999px;text-decoration:none;font-weight:600;font-size:15px">Rechazar</a>'
            . '</div>'
            . '<p style="color:#5C5F6B;font-size:12px;margin:0">Estos botones son de un solo uso. Una vez utilizados no podrán volver a usarse.</p>';

        return self::wrapper( $content );
    }
}
