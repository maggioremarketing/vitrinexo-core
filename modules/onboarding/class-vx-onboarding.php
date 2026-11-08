<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Gestión del wizard de onboarding en 6 pasos.
 */
class VX_Onboarding
{
    const PASOS        = [ 1, 2, 3, 4, 5, 6 ]; // Pasos reales del wizard
    const PASO_COMPLETO = 7;                  // Estado virtual de "onboarding completado"

    /**
     * Devuelve el estado actual del onboarding del usuario.
     *
     * @param int $user_id
     * @return array
     */
    public static function get_state( int $user_id ): array
    {
        $paso_actual = (int) get_user_meta( $user_id, VX_User_Meta::ONBOARDING_PASO, true );
        $datos       = [];

        foreach ( self::PASOS as $paso ) {
            $raw = get_user_meta( $user_id, 'vx_onboarding_datos_' . $paso, true );
            if ( $raw ) {
                $decoded = json_decode( $raw, true );
                if ( is_array( $decoded ) ) {
                    $datos[ $paso ] = $decoded;
                }
            }
        }

        return [
            'paso_actual' => max( 1, $paso_actual ),
            'datos'       => $datos,
            'completo'    => (bool) get_user_meta( $user_id, VX_User_Meta::ONBOARDING_COMPLETO, true ),
        ];
    }

    /**
     * Guarda los datos de un paso del onboarding.
     * Si partial=true, no valida campos obligatorios (para retroceso).
     *
     * @param int   $user_id
     * @param int   $paso
     * @param array $datos
     * @param bool  $partial
     * @return array  ['success' => bool, 'errors' => array]
     */
    public static function save_step( int $user_id, int $paso, array $datos, bool $partial = false ): array
    {
        if ( ! in_array( $paso, self::PASOS, true ) ) {
            return [ 'success' => false, 'errors' => [ 'paso_invalido' ] ];
        }

        if ( ! $partial ) {
            $validation = self::validate_step( $paso, $datos );
            if ( ! $validation['valid'] ) {
                return [ 'success' => false, 'errors' => $validation['errors'] ];
            }
        }

        // Guardar datos del paso
        update_user_meta( $user_id, 'vx_onboarding_datos_' . $paso, wp_json_encode( $datos ) );

        // Actualizar paso actual
        $current_paso = (int) get_user_meta( $user_id, VX_User_Meta::ONBOARDING_PASO, true );
        if ( $paso >= $current_paso ) {
            update_user_meta( $user_id, VX_User_Meta::ONBOARDING_PASO, $paso );
        }

        // Persistir datos en user meta si no es partial
        if ( ! $partial ) {
            self::persist_step_data( $user_id, $paso, $datos );
        }

        return [ 'success' => true, 'errors' => [] ];
    }

    /**
     * Completa el onboarding.
     * Marca como completo, dispara email de bienvenida.
     *
     * @param int $user_id
     */
    public static function complete( int $user_id ): void
    {
        update_user_meta( $user_id, VX_User_Meta::ONBOARDING_COMPLETO, true );
        update_user_meta( $user_id, VX_User_Meta::ONBOARDING_PASO, self::PASO_COMPLETO );

        // Paso 5 = comunidades (paso 4 = tags)
        $state = self::get_state( $user_id );
        $coms  = $state['datos'][5] ?? [];
        if ( ! empty( $coms['out2b'] ) )  VX_Community::activate( $user_id, 'out2b' );
        if ( ! empty( $coms['woman'] ) )  VX_Community::activate( $user_id, 'woman' );

        // La comunidad Woman es obligatoria para género femenino: no es opcional
        // y no puede desmarcarse. Se fuerza aquí aunque no venga el flag.
        if ( 'femenino' === get_user_meta( $user_id, VX_User_Meta::GENERO, true ) ) {
            VX_Community::activate( $user_id, 'woman' );
        }

        // Senior es automático: 45+ años y/o 20+ de experiencia. Sin verificación manual.
        VX_Senior_Verification::apply_auto( $user_id );

        // Auto-fundador: desactivable desde wp-admin → Vitrinexo → Ajustes.
        //
        // Esto escribía el distintivo directo, sin mirar el cupo, así que era
        // la puerta por la que el programa se podía pasar de 100 sin que nadie
        // lo notara. Ahora pregunta lo mismo que la aprobación.
        //
        // El plan gratuito va con vencimiento 0, que acá significa que no
        // vence: los primeros 100 son gratis para siempre, no gratis hasta que
        // alguien fije una fecha. Antes esa fecha la ponía un ajuste del panel
        // que ya no existe, porque contradecía la regla del programa.
        if ( get_option( 'vx_auto_fundador', '1' ) === '1' && vx_pioneros_dar_distintivo( $user_id ) ) {
            $membresia = VX_Membership::get( $user_id );
            if ( empty( $membresia->get_plan() ) || 'gratuito' === $membresia->get_plan() ) {
                $membresia->activate( 'gratuito', 0 );
            }
        }

        // Limpiar meta temporal del registro
        delete_user_meta( $user_id, 'vx_empresa_inicial' );

        do_action( 'vx_onboarding_completed', $user_id );
    }

    /**
     * Valida los campos obligatorios de un paso.
     *
     * @param int   $paso
     * @param array $datos
     * @return array  ['valid' => bool, 'errors' => array]
     */
    public static function validate_step( int $paso, array $datos ): array
    {
        $errors = [];

        switch ( $paso ) {
            case 2:
                // nombre, apellido y país ya se validaron en el registro.
                if ( empty( trim( (string) ( $datos['fecha_nacimiento'] ?? '' ) ) ) ) $errors[] = 'fecha_nacimiento_requerida';
                if ( '' === trim( (string) ( $datos['anos_experiencia'] ?? '' ) ) )   $errors[] = 'anos_experiencia_requerido';
                break;

            case 3:
                if ( empty( trim( $datos['empresa_nombre'] ?? '' ) ) ) $errors[] = 'empresa_nombre_requerido';
                if ( empty( trim( $datos['empresa_cargo']  ?? '' ) ) ) $errors[] = 'empresa_cargo_requerido';
                break;

            case 4:
                // Tags offer/seek — opcionales, sin validación obligatoria
                break;

            case 5:
                // Comunidades — opcionales
                break;
        }

        return [ 'valid' => empty( $errors ), 'errors' => $errors ];
    }

    /**
     * Persiste los datos de un paso en los user meta definitivos.
     *
     * @param int   $user_id
     * @param int   $paso
     * @param array $datos
     */
    private static function persist_step_data( int $user_id, int $paso, array $datos ): void
    {
        switch ( $paso ) {
            case 2:
                // nombre, apellido y país ya se guardaron en el registro: no se sobreescriben.
                update_user_meta( $user_id, VX_User_Meta::BIO,                sanitize_textarea_field( $datos['bio']            ?? '' ) );
                if ( isset( $datos['ciudad'] ) ) {
                    update_user_meta( $user_id, VX_User_Meta::CIUDAD,         sanitize_text_field( $datos['ciudad'] ) );
                }
                $genero = sanitize_key( $datos['genero'] ?? '' );
                if ( in_array( $genero, [ 'masculino', 'femenino', 'otro', 'no_contesta' ], true ) ) {
                    update_user_meta( $user_id, VX_User_Meta::GENERO, $genero );
                }
                // Fecha de nacimiento (formato YYYY-MM-DD)
                $fnac = sanitize_text_field( $datos['fecha_nacimiento'] ?? '' );
                if ( '' !== $fnac && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $fnac ) ) {
                    update_user_meta( $user_id, 'vx_fecha_nacimiento', $fnac );
                }
                // Años de experiencia en el mercado (entero 0-80)
                if ( isset( $datos['anos_experiencia'] ) && '' !== trim( (string) $datos['anos_experiencia'] ) ) {
                    update_user_meta( $user_id, 'vx_anos_experiencia', min( 80, max( 0, (int) $datos['anos_experiencia'] ) ) );
                }
                // El consentimiento se pide y guarda en el registro, no en el onboarding.
                if ( ! empty( $datos['foto_id'] ) ) {
                    update_user_meta( $user_id, VX_User_Meta::FOTO, absint( $datos['foto_id'] ) );
                }
                // Slug del perfil: usar nombre/apellido ya guardados en el registro.
                $nombre   = (string) get_user_meta( $user_id, VX_User_Meta::NOMBRE,   true );
                $apellido = (string) get_user_meta( $user_id, VX_User_Meta::APELLIDO, true );
                if ( $nombre && $apellido && ! get_user_meta( $user_id, VX_User_Meta::PERFIL_SLUG, true ) ) {
                    update_user_meta( $user_id, VX_User_Meta::PERFIL_SLUG,
                        VX_Slug_Helper::generate( $nombre, $apellido, $user_id ) );
                }
                break;

            case 3:
                // Crear/actualizar empresa + sincronizar industria al user meta
                $empresa_id = self::get_or_create_empresa( $user_id, $datos );
                if ( $empresa_id ) {
                    update_post_meta( $empresa_id, 'vx_empresa_activa', '1' );
                    // Sincronizar industria al user meta para facilitar filtros de directorio
                    $industria = sanitize_text_field( $datos['empresa_industria'] ?? '' );
                    update_post_meta( $empresa_id, 'vx_industria', $industria );
                    update_user_meta( $user_id, VX_User_Meta::INDUSTRIA, $industria );
                }
                break;

            case 4:
                // Tags offer/seek — guardar inmediatamente en user meta
                $offer = array_map( 'sanitize_text_field', (array) ( $datos['offer_tags'] ?? [] ) );
                $seek  = array_map( 'sanitize_text_field', (array) ( $datos['seek_tags']  ?? [] ) );
                $offer_texto = sanitize_textarea_field( $datos['offer_texto'] ?? '' );
                $seek_texto  = sanitize_textarea_field( $datos['seek_texto']  ?? '' );
                update_user_meta( $user_id, VX_User_Meta::OFFER_TAGS,  array_values( array_filter( $offer ) ) );
                update_user_meta( $user_id, VX_User_Meta::SEEK_TAGS,   array_values( array_filter( $seek ) ) );
                if ( $offer_texto ) update_user_meta( $user_id, VX_User_Meta::OFFER_TEXTO, $offer_texto );
                if ( $seek_texto )  update_user_meta( $user_id, VX_User_Meta::SEEK_TEXTO,  $seek_texto );
                break;

            case 5:
                // Comunidades — se activan definitivamente en complete()
                break;
        }
    }

    /**
     * Crea o actualiza la empresa del usuario en el paso 3.
     *
     * @param int   $user_id
     * @param array $datos
     * @return int|null  Post ID de la empresa creada/actualizada.
     */
    private static function get_or_create_empresa( int $user_id, array $datos ): ?int
    {
        // Buscar empresa existente del usuario sin nombre de empresa aún asignado
        $existing = get_posts( [
            'post_type'      => 'vx_empresa',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'author'         => $user_id,
            'fields'         => 'ids',
            'meta_query'     => [
                [ 'key' => 'vx_user_id', 'value' => $user_id ],
            ],
        ] );

        $post_id = ! empty( $existing ) ? (int) $existing[0] : null;

        $empresa_nombre = sanitize_text_field( $datos['empresa_nombre'] ?? '' );
        if ( empty( $empresa_nombre ) ) return $post_id;

        $post_data = [
            'post_title'  => $empresa_nombre,
            'post_type'   => 'vx_empresa',
            'post_status' => 'publish',
            'post_author' => $user_id,
        ];

        if ( $post_id ) {
            $post_data['ID'] = $post_id;
            wp_update_post( $post_data );
        } else {
            $post_id = wp_insert_post( $post_data );
            if ( is_wp_error( $post_id ) ) return null;
        }

        update_post_meta( $post_id, 'vx_user_id',     $user_id );
        update_post_meta( $post_id, 'vx_cargo',     sanitize_text_field( $datos['empresa_cargo']    ?? '' ) );
        update_post_meta( $post_id, 'vx_web',       esc_url_raw( $datos['empresa_web']              ?? '' ) );
        update_post_meta( $post_id, 'vx_linkedin',  esc_url_raw( $datos['empresa_linkedin']         ?? '' ) );
        update_post_meta( $post_id, 'vx_descripcion', sanitize_textarea_field( $datos['empresa_desc'] ?? '' ) );

        if ( ! empty( $datos['empresa_logo_id'] ) ) {
            update_post_meta( $post_id, 'vx_logo', absint( $datos['empresa_logo_id'] ) );
        }

        return $post_id;
    }
}
