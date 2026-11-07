<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Comunidad Senior. Ahora es AUTOMÁTICA: se asigna sola a quien tenga
 * 45 años o más y/o 20 años o más de experiencia. Sin verificación manual.
 */
class VX_Senior_Verification
{
    /** Umbrales de calificación automática. */
    const EDAD_MIN = 45;
    const EXP_MIN  = 20;

    /** Calcula la edad (en años) a partir de la fecha de nacimiento YYYY-MM-DD. */
    public static function edad( int $user_id ): int
    {
        $fnac = (string) get_user_meta( $user_id, 'vx_fecha_nacimiento', true );
        if ( '' === $fnac || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $fnac ) ) {
            return 0;
        }
        try {
            $dob = new DateTime( $fnac );
            return (int) $dob->diff( new DateTime( 'now' ) )->y;
        } catch ( Exception $e ) {
            return 0;
        }
    }

    /** ¿Califica automáticamente como Senior? (edad >= 45 o experiencia >= 20). */
    public static function qualifies( int $user_id ): bool
    {
        $exp = (int) get_user_meta( $user_id, 'vx_anos_experiencia', true );
        return self::edad( $user_id ) >= self::EDAD_MIN || $exp >= self::EXP_MIN;
    }

    /**
     * Aplica la clasificación automática: si califica, activa Senior (bloqueada).
     * Idempotente.
     */
    public static function apply_auto( int $user_id ): void
    {
        if ( ! self::qualifies( $user_id ) ) {
            return;
        }
        if ( get_user_meta( $user_id, VX_User_Meta::SENIOR_VERIFICADO, true ) ) {
            return; // ya asignado
        }
        update_user_meta( $user_id, VX_User_Meta::SENIOR_VERIFICADO, true );
        VX_Community::activate( $user_id, 'senior' );

        $user = VX_User::get( $user_id );
        if ( $user ) {
            VX_Mailer::send(
                $user->get_email(),
                '¡Bienvenido a Vitrinexo Senior!',
                'senior_aprobado',
                [ 'nombre' => $user->get_nombre() ]
            );
        }
    }

    public static function request( int $user_id ): void
    {
        update_user_meta( $user_id, VX_User_Meta::SENIOR_SOLICITADO, true );

        $user        = VX_User::get( $user_id );
        $admin_email = get_option( 'admin_email' );

        if ( ! $user ) return;

        $approve_url = wp_nonce_url(
            admin_url( 'users.php?action=vx_verificar_senior&user_id=' . $user_id ),
            'vx_verificar_senior_' . $user_id
        );

        VX_Mailer::send(
            $admin_email,
            '[Vitrinexo] Solicitud de verificación para Vitrinexo Senior',
            'admin_senior_request',
            [
                'nombre_completo' => $user->get_nombre_completo(),
                'email'           => $user->get_email(),
                'approve_url'     => $approve_url,
            ]
        );
    }

    public static function approve( int $user_id ): void
    {
        update_user_meta( $user_id, VX_User_Meta::SENIOR_VERIFICADO, true );
        VX_Community::activate( $user_id, 'senior' );

        // Notificar al usuario que fue aprobado
        $user = VX_User::get( $user_id );
        if ( $user ) {
            VX_Mailer::send(
                $user->get_email(),
                '¡Bienvenido a Vitrinexo Senior!',
                'senior_aprobado',
                [ 'nombre' => $user->get_nombre() ]
            );
        }
    }

    public static function reject( int $user_id ): void
    {
        delete_user_meta( $user_id, VX_User_Meta::SENIOR_SOLICITADO );
        delete_user_meta( $user_id, VX_User_Meta::SENIOR_VERIFICADO );
    }
}
