<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Asignación de usuarios a eventos 4Dinner.
 */
class VX_Dinner_Assignment
{
    /**
     * Asigna un usuario a la mesa de un 4Dinner.
     * Si completa los 4 cupos, envía las confirmaciones automáticamente.
     *
     * @param int $dinner_id
     * @param int $user_id
     * @return bool|WP_Error
     */
    public static function assign( int $dinner_id, int $user_id ): bool|WP_Error
    {
        $dinner = VX_Dinner::get( $dinner_id );
        if ( ! $dinner ) {
            return new WP_Error( 'dinner_no_encontrado', 'Evento no encontrado.', [ 'status' => 404 ] );
        }

        // has_space() siempre es true ahora — 4 es mínimo, no máximo

        if ( $dinner->is_user_assigned( $user_id ) ) {
            return new WP_Error( 'ya_asignado', 'El usuario ya está asignado a esta mesa.', [ 'status' => 409 ] );
        }

        $asignados   = $dinner->get_asignados();
        $asignados[] = $user_id;
        update_post_meta( $dinner_id, VX_Dinner_Meta::ASIGNADOS, $asignados );

        // Actualizar user meta
        $user_dinners   = (array) get_user_meta( $user_id, VX_User_Meta::DINNERS_ASIGNADO, true );
        $user_dinners[] = $dinner_id;
        update_user_meta( $user_id, VX_User_Meta::DINNERS_ASIGNADO, array_unique( $user_dinners ) );

        // Disparar hook → VX_Notification_Triggers::on_dinner_assigned() crea la notificación
        do_action( 'vx_dinner_assigned', $user_id, $dinner_id );

        // Email de confirmación
        $user = VX_User::get( $user_id );
        if ( $user ) {
            VX_Mailer::send(
                $user->get_email(),
                '¡Estás confirmado en el Vitrinexo 4Dinner de ' . $dinner->get_ciudad() . '!',
                'dinner_confirmacion',
                array_merge(
                    VX_Dinner_Assignment::build_confirmation_data( $dinner_id ),
                    [ 'usuario_nombre' => $user->get_nombre() ]
                )
            );
        }

        // Al llegar a 4 asistentes → la cena queda CONFIRMADA (se realizará).
        // Más personas pueden seguir uniéndose después del mínimo de 4.
        if ( 4 === count( $asignados ) ) {
            $dinner->set_estado( 'confirmado' );
        }

        return true;
    }

    /**
     * Desasigna un usuario de la mesa.
     *
     * @param int $dinner_id
     * @param int $user_id
     */
    public static function unassign( int $dinner_id, int $user_id ): void
    {
        $dinner = VX_Dinner::get( $dinner_id );
        if ( ! $dinner ) return;

        $asignados = array_diff( $dinner->get_asignados(), [ $user_id ] );
        update_post_meta( $dinner_id, VX_Dinner_Meta::ASIGNADOS, array_values( $asignados ) );

        $dinner->set_estado( count( $asignados ) >= 4 ? 'confirmado' : 'abierto' );

        $user_dinners = array_diff( (array) get_user_meta( $user_id, VX_User_Meta::DINNERS_ASIGNADO, true ), [ $dinner_id ] );
        update_user_meta( $user_id, VX_User_Meta::DINNERS_ASIGNADO, array_values( $user_dinners ) );
    }

    /**
     * Envía emails de confirmación a los 4 comensales de la mesa.
     *
     * @param int $dinner_id
     * @return int  Número de emails enviados.
     */
    public static function send_confirmations( int $dinner_id ): int
    {
        $dinner = VX_Dinner::get( $dinner_id );
        if ( ! $dinner ) return 0;

        $asignados = $dinner->get_asignados();
        if ( empty( $asignados ) ) return 0;

        $confirmation_data = self::build_confirmation_data( $dinner_id );
        $recipients        = [];

        foreach ( $asignados as $uid ) {
            $user = VX_User::get( $uid );
            if ( ! $user ) continue;

            $recipients[] = [
                'to'      => $user->get_email(),
                'subject' => '¡Tu mesa para el Vitrinexo 4Dinner en ' . $dinner->get_ciudad() . ' está confirmada!',
                'data'    => array_merge( $confirmation_data, [ 'usuario_nombre' => $user->get_nombre() ] ),
            ];
        }

        return VX_Mailer::send_bulk( $recipients, 'dinner_confirmacion' );
    }

    /**
     * Construye el array de datos para el email de confirmación.
     *
     * @param int $dinner_id
     * @return array
     */
    public static function build_confirmation_data( int $dinner_id ): array
    {
        $dinner = VX_Dinner::get( $dinner_id );
        if ( ! $dinner ) return [];

        $comensales = [];
        foreach ( $dinner->get_asignados() as $uid ) {
            $user = VX_User::get( $uid );
            if ( ! $user ) continue;

            $empresa = $user->get_empresa_activa();
            $comensales[] = [
                'nombre'   => $user->get_nombre_completo(),
                'empresa'  => $empresa ? $empresa->post_title : '',
                'foto_url' => $user->get_foto_url( 'thumbnail' ),
            ];
        }

        return [
            'dinner' => [
                'ciudad'      => $dinner->get_ciudad(),
                'pais'        => $dinner->get_pais(),
                'fecha'       => $dinner->get_fecha(),
                'restaurante' => $dinner->get_restaurante(),
                'direccion'   => $dinner->get_direccion(),
            ],
            'comensales' => $comensales,
        ];
    }
}
