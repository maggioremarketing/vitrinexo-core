<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Guard de acceso y autenticación de Vitrinexo.
 * Hook template_redirect evalúa el estado del usuario y redirige según corresponda.
 * Este es el ÚNICO lugar donde vive la lógica de control de acceso.
 */
class VX_Auth
{
    /**
     * Registra los hooks necesarios.
     * Llamado en el hook 'init'.
     */
    public static function init(): void
    {
        add_action( 'template_redirect', [ self::class, 'check_access' ] );
        add_action( 'admin_init',        [ self::class, 'block_admin_for_non_admins' ] );
        add_filter( 'show_admin_bar',    [ self::class, 'hide_admin_bar' ] );
        add_action( 'init',              [ self::class, 'register_rewrite_rules' ] );
        add_filter( 'query_vars',        [ self::class, 'add_query_vars' ] );
    }

    /**
     * Guard principal — evalúa estado y redirige.
     * Orden de evaluación crítico: si se cambia, cambiar el comentario en arquitectura-wordpress.md.
     */
    public static function check_access(): void
    {
        // 1. Admins: acceso total
        if ( current_user_can( 'manage_options' ) ) {
            return;
        }

        $pagename = get_query_var( 'pagename' );
        $is_auth_page = self::is_authenticated_page( $pagename );
        $is_flow_page = self::is_flow_page( $pagename );
        $is_public_page = ! $is_auth_page && ! $is_flow_page;

        // Páginas públicas: acceso libre sin verificación
        if ( $is_public_page ) {
            return;
        }

        // 2. No logueado + página autenticada → /login
        if ( ! is_user_logged_in() && $is_auth_page ) {
            wp_safe_redirect( home_url( '/login/?redirect_to=' . urlencode( $_SERVER['REQUEST_URI'] ) ) );
            exit;
        }

        if ( ! is_user_logged_in() ) {
            return;
        }

        $user_id = get_current_user_id();
        $user    = VX_User::get( $user_id );

        if ( ! $user ) {
            return;
        }

        $estado = $user->get_estado();

        // 3. Cuenta pendiente → página de espera
        if ( 'pendiente' === $estado ) {
            $target        = self::get_redirect_for_pending( $user_id );
            // Fix: strip trailing slash de ambos lados para evitar bucle infinito
            $target_path   = rtrim( ltrim( parse_url( $target, PHP_URL_PATH ), '/' ), '/' );
            if ( $pagename !== $target_path ) {
                wp_safe_redirect( $target );
                exit;
            }
            return;
        }

        // 4. Rechazado → solo página de espera (sin flujo)
        if ( 'rechazado' === $estado ) {
            if ( $pagename !== 'verificacion-pendiente' ) {
                wp_safe_redirect( home_url( '/verificacion-pendiente/' ) );
                exit;
            }
            return;
        }

        // 5. Activo pero onboarding incompleto → /onboarding
        if ( 'activo' === $estado && ! $user->is_onboarding_completo() ) {
            if ( $pagename !== 'onboarding' ) {
                wp_safe_redirect( home_url( '/onboarding/' ) );
                exit;
            }
            return;
        }

        // 5b. Activo con onboarding completo + flow page → redirigir a dashboard
        // Evita que usuarios completos accedan a /onboarding/, /confirmar-correo/, etc.
        if ( $is_flow_page && 'activo' === $estado && $user->is_onboarding_completo() ) {
            // Permitir conexion-aceptada y conexion-rechazada (páginas de confirmación)
            if ( ! in_array( $pagename, [ 'conexion-aceptada', 'conexion-rechazada' ], true ) ) {
                wp_safe_redirect( home_url( '/dashboard/' ) );
                exit;
            }
        }

        // 6. Plan vencido → solo configuración accesible
        $membresia = VX_Membership::get( $user_id );
        if ( 'vencido' === $membresia->get_plan_estado() && $is_auth_page ) {
            if ( 'configuracion' !== $pagename ) {
                wp_safe_redirect( home_url( '/configuracion/?plan=vencido' ) );
                exit;
            }
            return;
        }

        // 7. Programa lleno: gratuito + no Pionero → solo puede ir a /configuracion/
        //
        // Esto dependía de un interruptor del panel, «auto_fundador», que
        // alguien tenía que acordarse de apagar. O sea que el programa podía
        // estar lleno, el miembro 101 no recibir distintivo, y entrar igual con
        // acceso completo y gratis: el cupo cerraba la puerta del distintivo y
        // dejaba la del producto abierta.
        //
        // Ahora lo manda el cupo. Mientras el programa esté abierto esto no le
        // toca a nadie, porque quien entra recibe el distintivo; se activa solo
        // al llenarse, que es cuando empieza a haber algo que cobrar. Se usa el
        // número cacheado a propósito: corre en cada carga de página, y para
        // decidir un redirect cinco minutos de desfase no hacen daño. Para dar
        // el distintivo sí se cuenta fresco, y eso vive en la puerta.
        if ( vx_pioneros_programa_lleno_cacheado()
             && $membresia->is_gratuito()
             && ! $user->is_founder()
             && ( $is_auth_page || ( $is_flow_page && 'onboarding' === $pagename ) )
             && 'configuracion' !== $pagename
        ) {
            wp_safe_redirect( home_url( '/configuracion/?tab=plan&motivo=acceso' ) );
            exit;
        }

        // 9. Páginas de comunidad — solo accesibles para miembros de esa comunidad
        $community_pages = [
            'comunidad-out2b'    => 'out2b',
            'comunidad-woman'    => 'woman',
            'comunidad-senior'   => 'senior',
        ];

        if ( isset( $community_pages[ $pagename ] ) ) {
            $required_community = $community_pages[ $pagename ];
            if ( ! $user->is_in_community( $required_community ) ) {
                wp_safe_redirect( home_url( '/dashboard/?community_required=' . $required_community ) );
                exit;
            }
        }

        // 8. Activo y onboarding completo → acceso normal
    }

    /**
     * Devuelve la URL de redirección para usuarios en estado pendiente.
     *
     * @param int $user_id
     * @return string
     */
    public static function get_redirect_for_pending( int $user_id ): string
    {
        $tipo = (string) get_user_meta( $user_id, VX_User_Meta::TIPO_VERIFICACION, true );
        return 'manual' === $tipo
            ? home_url( '/verificacion-pendiente/' )
            : home_url( '/confirmar-correo/' );
    }

    /**
     * Bloquea acceso a wp-admin para usuarios no administradores.
     */
    public static function block_admin_for_non_admins(): void
    {
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_safe_redirect( home_url( '/dashboard/' ) );
            exit;
        }
    }

    /**
     * Oculta la barra de administración para no-admins.
     *
     * @param bool $show
     * @return bool
     */
    public static function hide_admin_bar( bool $show ): bool
    {
        return current_user_can( 'manage_options' ) ? $show : false;
    }

    /**
     * Registra el rewrite rule para /perfil/{slug}.
     */
    public static function register_rewrite_rules(): void
    {
        add_rewrite_rule(
            '^perfil/([^/]+)/?$',
            'index.php?pagename=perfil&vx_perfil_slug=$matches[1]',
            'top'
        );
    }

    /**
     * Agrega query vars de Vitrinexo.
     *
     * @param array $vars
     * @return array
     */
    public static function add_query_vars( array $vars ): array
    {
        $vars[] = 'vx_perfil_slug';
        $vars[] = 'vx_perfil_user_id';
        return $vars;
    }

    /**
     * Páginas que requieren autenticación completa (activo + onboarding completo).
     *
     * @param string $pagename
     * @return bool
     */
    private static function is_authenticated_page( string $pagename ): bool
    {
        $auth_pages = [
            'dashboard', 'directorio', 'matches', 'match-seeks', 'match-offers', 'perfil', 'editar-perfil',
            'favoritos', 'conexiones', 'notificaciones', 'configuracion',
            '4dinner', 'mis-eventos', 'comunidad-out2b', 'comunidad-woman', 'comunidad-senior',
        ];

        foreach ( $auth_pages as $page ) {
            if ( $pagename === $page || str_starts_with( $pagename, $page . '/' ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Páginas del flujo (confirmar-correo, verificacion-pendiente, onboarding).
     * Accesibles solo en su estado específico.
     *
     * @param string $pagename
     * @return bool
     */
    private static function is_flow_page( string $pagename ): bool
    {
        $flow_pages = [
            'confirmar-correo', 'verificacion-pendiente', 'onboarding',
            'conexion-aceptada', 'conexion-rechazada',
        ];

        return in_array( $pagename, $flow_pages, true );
    }
}
