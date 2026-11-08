<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Flujo de verificación de cuentas nuevas.
 * Detecta tipo de email, genera tokens, activa cuentas.
 */
class VX_Verification
{
    const TOKEN_EXPIRY_INSTITUTIONAL = 86400;    // 24h
    const TOKEN_EXPIRY_MANUAL        = 259200;   // 72h

    /**
     * Inicia el flujo de verificación para un usuario recién registrado.
     * Llamado desde rest-auth.php al registrar.
     *
     * @param int    $user_id
     * @param string $email
     */
    public static function start( int $user_id, string $email ): void
    {
        // Política: TODAS las cuentas requieren aprobación manual de un
        // administrador antes de tener acceso, sin importar el dominio del email
        // (institucional o genérico). No se envía confirmación de auto-activación;
        // la cuenta queda pendiente hasta que un administrador la apruebe.
        update_user_meta( $user_id, VX_User_Meta::TIPO_VERIFICACION, 'manual' );
        self::notify_admin_pending( $user_id );
    }

    /**
     * Verifica si el dominio del email es institucional.
     *
     * @param string $email
     * @return bool
     */
    public static function is_institutional( string $email ): bool
    {
        return VX_Domain_Helper::is_institutional( $email );
    }

    /**
     * Genera un token de activación y lo guarda en user meta.
     *
     * @param int $user_id
     * @param int $expiry_hours  Horas de validez del token.
     * @return string  El token generado.
     */
    public static function generate_token( int $user_id, int $expiry_hours = 24 ): string
    {
        $token  = VX_Token_Helper::generate();
        $expiry = time() + ( $expiry_hours * 3600 );

        update_user_meta( $user_id, VX_User_Meta::TOKEN_CONFIRMACION, $token );
        update_user_meta( $user_id, VX_User_Meta::TOKEN_EXPIRA,       $expiry );

        return $token;
    }

    /**
     * Valida el token de activación de un usuario.
     * Si es válido, lo consume (elimina) y devuelve true.
     *
     * @param int    $user_id
     * @param string $token
     * @return bool
     */
    public static function validate_token( int $user_id, string $token ): bool
    {
        $stored_token = get_user_meta( $user_id, VX_User_Meta::TOKEN_CONFIRMACION, true );
        $expiry       = (int) get_user_meta( $user_id, VX_User_Meta::TOKEN_EXPIRA, true );

        if ( empty( $stored_token ) || $stored_token !== $token ) {
            return false;
        }

        if ( $expiry > 0 && $expiry < time() ) {
            return false; // expirado
        }

        // Consumir el token
        delete_user_meta( $user_id, VX_User_Meta::TOKEN_CONFIRMACION );
        delete_user_meta( $user_id, VX_User_Meta::TOKEN_EXPIRA );

        return true;
    }

    /**
     * Activa una cuenta: cambia vx_estado a 'activo', asigna distintivo Pionero
     * si es uno de los primeros 100, y establece vencimiento 3 meses.
     */
    public static function activate_account( int $user_id ): void
    {
        update_user_meta( $user_id, VX_User_Meta::ESTADO, 'activo' );

        // ── Distintivo Pionero, por la única puerta ────────────────────────
        //
        // Esto contaba a mano los usuarios activos contra un 100 escrito acá,
        // y de paso hacía una consulta entera que no usaba. Peor: era una de
        // dos puertas. La otra, el final del onboarding, repartía el
        // distintivo sin mirar el cupo, así que el miembro 130 salía Pionero
        // igual. Ahora las dos preguntan lo mismo, y el cupo vive en
        // vx_pioneros_cupo() y no en un número suelto.
        vx_pioneros_dar_distintivo( $user_id );

        // ── El plan gratuito no vence en una fecha, vence en un número ────
        //
        // Antes esto escribía tres meses desde la aprobación. Esa ya no es la
        // regla: el período gratuito de los Pioneros termina cuando el
        // programa llega a sus 100 miembros, y ahí se les avisa y se les da un
        // plazo antes de cobrar. Una fecha por miembro no puede expresar eso,
        // porque el momento no depende de cuándo entró cada uno sino de
        // cuántos entraron en total.
        //
        // Así que se escribe 0, que en este meta significa sin vencimiento, y
        // no se deja sin escribir: el meta tiene que existir. La columna del
        // admin distingue «no hay fecha guardada» de «no vence», y el cron de
        // avisos excluye el 0, así que un miembro sin vencimiento no recibe
        // correos diciéndole que su membresía está por terminar.
        //
        // La condición mira si el meta existe, no si está vacío, porque 0 es
        // un valor legítimo y con empty() la aprobación lo pisaba.
        if ( ! metadata_exists( 'user', $user_id, VX_User_Meta::PLAN_VENCIMIENTO ) ) {
            update_user_meta( $user_id, VX_User_Meta::PLAN_VENCIMIENTO, 0 );
        }

        $user = VX_User::get( $user_id );
        if ( ! $user ) return;

        VX_Mailer::send(
            $user->get_email(),
            '¡Bienvenido a Vitrinexo, ' . $user->get_nombre() . '!',
            'aprobacion',
            [ 'nombre' => $user->get_nombre(), 'link' => home_url( '/login/' ) ]
        );

        do_action( 'vx_account_activated', $user_id );
    }

    /**
     * Envía el email de confirmación de cuenta (email institucional).
     *
     * @param int $user_id
     */
    public static function send_confirmation_email( int $user_id ): void
    {
        $user  = VX_User::get( $user_id );
        if ( ! $user ) return;

        $token = self::generate_token( $user_id, 24 );
        $link  = add_query_arg( [
            'uid'    => $user_id,
            'token'  => $token,
            'accion' => 'confirmar',
        ], rest_url( VX_REST_NAMESPACE . '/activar' ) );

        VX_Mailer::send(
            $user->get_email(),
            'Confirma tu email en Vitrinexo',
            'confirmacion',
            [
                'nombre' => $user->get_nombre(),
                'link'   => $link,
            ]
        );
    }

    /**
     * Notifica al admin que hay una cuenta manual pendiente de revisión.
     *
     * @param int $user_id
     */
    public static function notify_admin_pending( int $user_id ): void
    {
        // Desactivado: este aviso enviaba "[Vitrinexo] Nueva cuenta pendiente de aprobación"
        // con un enlace de wp-admin (nonce) que caducaba y nunca funcionaba. El aviso al
        // administrador ahora lo cubre el correo "[Vitrinexo] Nuevo registro: …" con botones
        // de aprobar/rechazar de un solo uso (ver rest/rest-auth.php).
        return;

        $user        = VX_User::get( $user_id );
        $admin_email = get_option( 'admin_email' );

        if ( ! $user ) return;

        $approve_url = wp_nonce_url(
            admin_url( 'users.php?action=vx_aprobar_verificacion&user_id=' . $user_id ),
            'vx_aprobar_' . $user_id
        );

        VX_Mailer::send(
            $admin_email,
            '[Vitrinexo] Nueva cuenta pendiente de aprobación',
            'admin_cuenta_pendiente',
            [
                'nombre_completo' => $user->get_nombre_completo(),
                'email'           => $user->get_email(),
                'approve_url'     => $approve_url,
            ]
        );
    }

    /**
     * Aprueba manualmente una cuenta (admin action).
     * Genera token y envía email al usuario.
     *
     * @param int $user_id
     */
    public static function approve_manual( int $user_id ): void
    {
        $user = VX_User::get( $user_id );
        if ( ! $user ) return;

        // La aprobación del admin ES la activación: no hay segundo paso.
        // Evita doble activación/email si ya está activo (p. ej. doble clic).
        if ( 'activo' === $user->get_estado() ) return;

        // Activa la cuenta y envía UN solo email de bienvenida con botón "Ingresar".
        self::activate_account( $user_id );
    }

    /**
     * Reenvía el email de confirmación (solicitud del usuario).
     * Genera un nuevo token invalidando el anterior.
     *
     * @param int $user_id
     * @return bool
     */
    public static function resend_token( int $user_id ): bool
    {
        $user = VX_User::get( $user_id );
        if ( ! $user ) return false;

        $tipo = $user->get_tipo_verificacion();

        if ( 'automatica' === $tipo ) {
            self::send_confirmation_email( $user_id );
            return true;
        }

        // Fix: si es manual y el token expiró, notificar al admin para que vuelva a aprobar
        $token   = get_user_meta( $user_id, VX_User_Meta::TOKEN_CONFIRMACION, true );
        $expiry  = (int) get_user_meta( $user_id, VX_User_Meta::TOKEN_EXPIRA, true );
        $expired = $expiry > 0 && $expiry < time();
        if ( ! $token || $expired ) {
            self::notify_admin_pending( $user_id ); // pedir al admin que apruebe de nuevo
        }
        return false;
    }
}
