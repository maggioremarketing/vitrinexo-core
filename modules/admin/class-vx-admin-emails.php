<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Panel de edición de plantillas de email en el admin de WordPress.
 * Las plantillas se guardan como opciones WP (vx_email_tpl_{slug})
 * y sobreescriben las plantillas PHP cuando existen.
 */
class VX_Admin_Emails
{
    /** Definición de todas las plantillas editables */
    private static function templates(): array
    {
        return [
            'confirmacion' => [
                'label'   => 'Confirmación de email',
                'desc'    => 'Se envía al usuario cuando se registra con email corporativo.',
                'vars'    => '{{nombre}}, {{link}}',
                'default_subject' => 'Confirma tu email en Vitrinexo',
                'default_body'    => "Hola {{nombre}},\n\nEstás a un clic de activar tu cuenta en Vitrinexo. Confirma tu email con el botón de abajo:\n\n{{link}}\n\nSi no solicitaste este registro, ignora este mensaje.",
            ],
            'aprobacion' => [
                'label'   => 'Cuenta aprobada',
                'desc'    => 'Se envía al usuario cuando el administrador aprueba su solicitud.',
                'vars'    => '{{nombre}}, {{link}}',
                'default_subject' => '¡Tu cuenta en Vitrinexo fue aprobada!',
                'default_body'    => "Hola {{nombre}},\n\nTu solicitud para unirte a Vitrinexo fue aprobada y tu cuenta ya está activa. Ingresa con el botón de abajo:\n\n{{link}}",
            ],
            'blog_pendiente_admin' => [
                'label'   => 'Blog: nuevo artículo (admin)',
                'desc'    => 'Se envía a joao@ y marcia@ cuando un miembro envía un artículo para revisión.',
                'vars'    => '{{nombre}}, {{titulo}}, {{link}}',
                'default_subject' => '[Vitrinexo] Nuevo artículo pendiente de aprobación',
                'default_body'    => "{{nombre}} envió un artículo para revisión: \"{{titulo}}\".\n\nRevísalo y apruébalo o recházalo desde el panel de moderación:\n\n{{link}}",
            ],
            'blog_aprobado' => [
                'label'   => 'Blog: artículo aprobado',
                'desc'    => 'Se envía al autor cuando un admin aprueba y publica su artículo.',
                'vars'    => '{{nombre}}, {{titulo}}, {{link}}',
                'default_subject' => '¡Tu artículo fue publicado en Vitrinexo!',
                'default_body'    => "Hola {{nombre}},\n\n¡Buenas noticias! Tu artículo \"{{titulo}}\" fue aprobado y ya está publicado en el blog de Vitrinexo.\n\nMíralo aquí:\n\n{{link}}",
            ],
            'blog_rechazado' => [
                'label'   => 'Blog: artículo rechazado',
                'desc'    => 'Se envía al autor cuando un admin no aprueba su artículo.',
                'vars'    => '{{nombre}}, {{titulo}}, {{link}}',
                'default_subject' => 'Sobre tu artículo en Vitrinexo',
                'default_body'    => "Hola {{nombre}},\n\nRevisamos tu artículo \"{{titulo}}\" y por ahora no fue aprobado para publicación. Puedes ajustarlo y volver a enviarlo.\n\n{{link}}\n\nSi tienes dudas, escríbenos a hola@vitrinexo.com.",
            ],
            'foto_recordatorio' => [
                'label'   => 'Recordatorio: completa tu perfil',
                'desc'    => 'Se envía cada semana a los miembros activos con el perfil incompleto (sin terminar el onboarding o sin foto).',
                'vars'    => '{{nombre}}, {{link}}',
                'default_subject' => '{{nombre}}, completa tu perfil en Vitrinexo',
                'default_body'    => "Hola {{nombre}},\n\nTu perfil en Vitrinexo aún está incompleto. Completarlo —foto, lo que ofreces y lo que buscas— te ayuda a recibir más y mejores conexiones.\n\nComplétalo en un par de minutos con el botón de abajo:\n\n{{link}}\n\nSi ya lo completaste, ignora este mensaje.",
            ],
            'rechazo' => [
                'label'   => 'Solicitud rechazada',
                'desc'    => 'Se envía al usuario cuando el administrador rechaza su solicitud.',
                'vars'    => '{{nombre}}',
                'default_subject' => 'Tu solicitud en Vitrinexo',
                'default_body'    => "Hola {{nombre}},\n\nLamentamos informarte que tu solicitud para unirte a Vitrinexo no pudo ser aprobada en este momento.\n\nSi crees que hay un error, escríbenos a hola@vitrinexo.com.",
            ],
            'verificacion_pendiente' => [
                'label'   => 'Solicitud pendiente de revisión',
                'desc'    => 'Se envía al usuario con email genérico (@gmail, @hotmail, etc.) mientras su perfil es revisado.',
                'vars'    => '{{nombre}}',
                'default_subject' => 'Tu perfil está siendo validado',
                'default_body'    => "Hola {{nombre}},\n\nRecibimos tu solicitud para unirte a Vitrinexo. Nuestro equipo la está revisando y recibirás una respuesta pronto.\n\nGracias por tu paciencia.",
            ],
            'notificacion_admin' => [
                'label'   => 'Notificación de nuevo registro (admin)',
                'desc'    => 'Se envía a joao@vitrinexo.com y marcia@vitrinexo.com cada vez que alguien se registra. La tabla de datos y los botones Aprobar/Rechazar siempre se incluyen automáticamente.',
                'vars'    => '{{nombre}}, {{apellido}}, {{empresa}}, {{cargo}}, {{pais}}, {{email_usuario}}',
                'default_subject' => '[Vitrinexo] Nuevo registro: {{nombre}} {{apellido}}',
                'default_body'    => "Alguien completó el formulario de inscripción.\n\nRevisa los datos a continuación y aprueba o rechaza la solicitud.",
            ],
        ];
    }

    /** Administradores que reciben las pruebas de email. */
    private const TEST_RECIPIENTS = [ 'joao@vitrinexo.com', 'marcia@vitrinexo.com' ];

    public static function init(): void
    {
        add_action( 'admin_menu', [ self::class, 'register_page' ], 20 );
        add_action( 'admin_init', [ self::class, 'handle_save' ] );
        add_action( 'admin_init', [ self::class, 'handle_test_send' ] );
    }

    public static function register_page(): void
    {
        add_submenu_page(
            'vitrinexo-core',
            'Plantillas de Email',
            'Emails',
            'manage_options',
            'vx-email-templates',
            [ self::class, 'render_page' ]
        );
    }

    public static function handle_save(): void
    {
        if ( ! isset( $_POST['vx_email_save'] ) ) return;
        if ( ! check_admin_referer( 'vx_email_templates' ) ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;

        $slug = sanitize_key( $_POST['vx_tpl_slug'] ?? '' );
        $tpls = self::templates();
        if ( ! isset( $tpls[ $slug ] ) ) return;

        update_option( 'vx_email_tpl_subject_' . $slug, sanitize_text_field( wp_unslash( $_POST['vx_subject'] ?? '' ) ) );
        update_option( 'vx_email_tpl_body_'    . $slug, wp_kses_post( wp_unslash( $_POST['vx_body'] ?? '' ) ) );

        wp_redirect( add_query_arg( [ 'page' => 'vx-email-templates', 'saved' => 1, 'tpl' => $slug ], admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Envía una prueba de la plantilla actual (tal como está guardada) a los
     * administradores, usando el mismo pipeline que los emails reales.
     */
    public static function handle_test_send(): void
    {
        if ( ! isset( $_POST['vx_email_test'] ) ) return;
        if ( ! check_admin_referer( 'vx_email_templates' ) ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;

        $slug = sanitize_key( $_POST['vx_tpl_slug'] ?? '' );
        $tpls = self::templates();
        if ( ! isset( $tpls[ $slug ] ) ) return;

        // Datos de ejemplo para reemplazar las variables de la plantilla.
        $sample_link = add_query_arg( [
            'uid'    => 0,
            'token'  => 'token-de-ejemplo',
            'accion' => 'confirmar',
        ], rest_url( VX_REST_NAMESPACE . '/activar' ) );

        $data = [
            'nombre'        => 'Marcia',
            'apellido'      => 'Maggi',
            'link'          => $sample_link,
            'empresa'       => 'Vitrinexo',
            'cargo'         => 'Administradora',
            'pais'          => 'Chile',
            'email_usuario' => 'ejemplo@empresa.com',
        ];

        $sent = 0;
        foreach ( self::TEST_RECIPIENTS as $to ) {
            if ( VX_Mailer::send( $to, '', $slug, $data ) ) {
                $sent++;
            }
        }

        wp_redirect( add_query_arg( [ 'page' => 'vx-email-templates', 'tested' => $sent, 'tpl' => $slug ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function render_page(): void
    {
        $tpls    = self::templates();
        $active  = sanitize_key( $_GET['tpl'] ?? array_key_first( $tpls ) );
        if ( ! isset( $tpls[ $active ] ) ) $active = array_key_first( $tpls );
        $saved   = ! empty( $_GET['saved'] );
        $tested  = isset( $_GET['tested'] ) ? (int) $_GET['tested'] : null;
        $current = $tpls[ $active ];
        $subject = get_option( 'vx_email_tpl_subject_' . $active, $current['default_subject'] );
        $body    = get_option( 'vx_email_tpl_body_'    . $active, $current['default_body'] );
        ?>
        <div class="wrap">
            <h1>Plantillas de Email</h1>
            <?php if ( $saved ) : ?><div class="notice notice-success is-dismissible"><p>Plantilla guardada.</p></div><?php endif; ?>
            <?php if ( null !== $tested ) : ?><div class="notice notice-<?php echo $tested > 0 ? 'success' : 'error'; ?> is-dismissible"><p><?php echo $tested > 0 ? 'Prueba enviada a joao@vitrinexo.com y marcia@vitrinexo.com.' : 'No se pudo enviar la prueba. Revisa la configuración SMTP.'; ?></p></div><?php endif; ?>

            <div style="display:flex;gap:24px;margin-top:20px;align-items:flex-start">
                <div style="width:220px;flex-shrink:0">
                    <ul style="margin:0;padding:0;list-style:none;background:#fff;border:1px solid #E6E2DB;border-radius:4px;overflow:hidden">
                    <?php foreach ( $tpls as $slug => $tpl ) : ?>
                        <li style="border-bottom:1px solid #F4F1EC">
                            <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'vx-email-templates', 'tpl' => $slug ], admin_url( 'admin.php' ) ) ); ?>"
                               style="display:block;padding:10px 14px;text-decoration:none;font-weight:<?php echo $slug === $active ? '600' : '400'; ?>;background:<?php echo $slug === $active ? '#DDE8F6' : 'transparent'; ?>;color:#14161F">
                               <?php echo esc_html( $tpl['label'] ); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                </div>

                <div style="flex:1">
                    <p style="color:#5C5F6B;margin-top:0"><?php echo esc_html( $current['desc'] ); ?> <br>
                    <strong>Variables disponibles:</strong> <code><?php echo esc_html( $current['vars'] ); ?></code></p>

                    <form method="post">
                        <?php wp_nonce_field( 'vx_email_templates' ); ?>
                        <input type="hidden" name="vx_email_save" value="1">
                        <input type="hidden" name="vx_tpl_slug"   value="<?php echo esc_attr( $active ); ?>">

                        <table class="form-table">
                            <tr>
                                <th><label for="vx_subject">Asunto</label></th>
                                <td><input type="text" id="vx_subject" name="vx_subject" value="<?php echo esc_attr( $subject ); ?>" class="large-text"></td>
                            </tr>
                            <tr>
                                <th><label for="vx_body">Cuerpo del mensaje</label></th>
                                <td>
                                    <textarea id="vx_body" name="vx_body" rows="14" class="large-text" style="font-family:monospace"><?php echo esc_textarea( $body ); ?></textarea>
                                    <p class="description">Texto plano. Usa {{variable}} para insertar datos dinámicos.</p>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <button type="submit" class="button button-primary">Guardar plantilla</button>
                            <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'vx-email-templates', 'tpl' => $active, 'reset' => 1 ], admin_url( 'admin.php' ) ) ); ?>" class="button" style="margin-left:8px">Restaurar por defecto</a>
                        </p>
                    </form>

                    <div style="margin-top:16px;padding-top:16px;border-top:1px solid #E6E2DB">
                        <form method="post" onsubmit="return confirm('Se enviará una prueba de esta plantilla a joao@vitrinexo.com y marcia@vitrinexo.com. ¿Continuar?');">
                            <?php wp_nonce_field( 'vx_email_templates' ); ?>
                            <input type="hidden" name="vx_email_test" value="1">
                            <input type="hidden" name="vx_tpl_slug"  value="<?php echo esc_attr( $active ); ?>">
                            <button type="submit" class="button">Enviar prueba a administradores</button>
                            <span class="description" style="margin-left:8px">Envía la versión guardada de esta plantilla a joao@ y marcia@vitrinexo.com.</span>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Lee una plantilla: primero busca en opciones WP, luego usa el PHP por defecto.
     * Reemplaza {{variable}} con los valores de $data.
     *
     * @return array [ 'subject' => string, 'body_text' => string ]
     */
    public static function get( string $slug, array $data ): array
    {
        $tpls = self::templates();
        $tpl  = $tpls[ $slug ] ?? null;

        $subject = get_option( 'vx_email_tpl_subject_' . $slug, $tpl['default_subject'] ?? $slug );
        $body    = get_option( 'vx_email_tpl_body_'    . $slug, $tpl['default_body']    ?? '' );

        // Reemplazar variables {{nombre}}, {{link}}, etc.
        // Solo valores escalares: si llega un array/objeto se ignora (evita fatal en str_replace).
        foreach ( $data as $k => $v ) {
            if ( ! is_scalar( $v ) ) {
                continue;
            }
            $v       = (string) $v;
            $body    = str_replace( '{{' . $k . '}}', $v, $body );
            $subject = str_replace( '{{' . $k . '}}', $v, $subject );
        }

        return [ 'subject' => $subject, 'body_text' => $body ];
    }
}
