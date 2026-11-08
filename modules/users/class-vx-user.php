<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Modelo de usuario de Vitrinexo.
 * Abstracción sobre WP_User — centraliza todos los getters/setters de user meta.
 * Nunca llamar get_user_meta() directamente fuera de esta clase.
 */
class VX_User
{
    private WP_User $wp_user;

    private function __construct( WP_User $wp_user )
    {
        $this->wp_user = $wp_user;
    }

    /**
     * Factory. Devuelve una instancia o null si el usuario no existe.
     *
     * @param int $user_id
     * @return VX_User|null
     */
    public static function get( int $user_id ): ?self
    {
        $wp_user = get_userdata( $user_id );
        if ( ! $wp_user ) {
            return null;
        }
        return new self( $wp_user );
    }

    // ── Identidad ────────────────────────────────────────────────

    public function get_id(): int
    {
        return $this->wp_user->ID;
    }

    public function get_email(): string
    {
        return $this->wp_user->user_email;
    }

    public function get_nombre(): string
    {
        return (string) get_user_meta( $this->wp_user->ID, VX_User_Meta::NOMBRE, true );
    }

    public function get_apellido(): string
    {
        return (string) get_user_meta( $this->wp_user->ID, VX_User_Meta::APELLIDO, true );
    }

    public function get_nombre_completo(): string
    {
        $nombre   = $this->get_nombre();
        $apellido = $this->get_apellido();
        return trim( $nombre . ' ' . $apellido );
    }

    public function get_slug(): string
    {
        return (string) get_user_meta( $this->wp_user->ID, VX_User_Meta::PERFIL_SLUG, true );
    }

    public function get_foto(): int
    {
        return (int) get_user_meta( $this->wp_user->ID, VX_User_Meta::FOTO, true );
    }

    public function get_foto_url( string $size = 'vx-card' ): string
    {
        $id = $this->get_foto();
        if ( $id ) {
            $url = wp_get_attachment_image_url( $id, $size );
            if ( $url ) return $url;
        }
        // Sin foto: avatar generado (círculo Vitrinexo + iniciales), nunca un enlace roto.
        if ( function_exists( 'vx_avatar_placeholder_url' ) ) {
            return vx_avatar_placeholder_url( $this->get_iniciales() );
        }
        return get_template_directory_uri() . '/assets/img/placeholder.webp';
    }

    /** Iniciales del usuario (nombre + apellido) para el avatar placeholder. */
    public function get_iniciales(): string {
        if ( function_exists( 'vx_iniciales_de' ) ) {
            return vx_iniciales_de( $this->get_nombre(), $this->get_apellido() );
        }
        return mb_strtoupper( mb_substr( trim( $this->get_nombre() ), 0, 1 ) . mb_substr( trim( $this->get_apellido() ), 0, 1 ) ) ?: 'V';
    }

    public function get_bio(): string
    {
        return (string) get_user_meta( $this->wp_user->ID, VX_User_Meta::BIO, true );
    }

    public function get_ciudad(): string
    {
        return (string) get_user_meta( $this->wp_user->ID, VX_User_Meta::CIUDAD, true );
    }

    public function get_pais(): string
    {
        return (string) get_user_meta( $this->wp_user->ID, VX_User_Meta::PAIS, true );
    }

    public function get_pais_codigo(): string
    {
        $paises = [
            'Argentina' => 'ARG', 'Bolivia' => 'BOL', 'Chile' => 'CHL',
            'Colombia' => 'COL', 'Costa Rica' => 'CRI', 'Cuba' => 'CUB',
            'Ecuador' => 'ECU', 'El Salvador' => 'SLV', 'España' => 'ESP',
            'Guatemala' => 'GTM', 'Honduras' => 'HND', 'México' => 'MEX',
            'Nicaragua' => 'NIC', 'Panamá' => 'PAN', 'Paraguay' => 'PRY',
            'Perú' => 'PER', 'Puerto Rico' => 'PRI',
            'República Dominicana' => 'DOM', 'Uruguay' => 'URY', 'Venezuela' => 'VEN',
        ];
        $pais = $this->get_pais();
        return $paises[ $pais ] ?? strtoupper( substr( $pais, 0, 3 ) );
    }

    public function get_contacto_preferido(): string
    {
        return (string) get_user_meta( $this->wp_user->ID, VX_User_Meta::CONTACTO_PREFERIDO, true );
    }

    public function get_telefono(): string
    {
        return (string) get_user_meta( $this->wp_user->ID, VX_User_Meta::TELEFONO, true );
    }

    public function get_linkedin(): string
    {
        return (string) get_user_meta( $this->wp_user->ID, VX_User_Meta::LINKEDIN, true );
    }

    // ── Estado de cuenta ────────────────────────────────────────

    public function get_estado(): string
    {
        return (string) get_user_meta( $this->wp_user->ID, VX_User_Meta::ESTADO, true );
    }

    public function get_tipo_verificacion(): string
    {
        return (string) get_user_meta( $this->wp_user->ID, VX_User_Meta::TIPO_VERIFICACION, true );
    }

    public function is_onboarding_completo(): bool
    {
        return (bool) get_user_meta( $this->wp_user->ID, VX_User_Meta::ONBOARDING_COMPLETO, true );
    }

    public function get_onboarding_paso(): int
    {
        return (int) get_user_meta( $this->wp_user->ID, VX_User_Meta::ONBOARDING_PASO, true );
    }

    public function is_active(): bool
    {
        return 'activo' === $this->get_estado() && $this->is_onboarding_completo();
    }

    public function is_pending(): bool
    {
        return 'pendiente' === $this->get_estado();
    }

    // ── Membresía ────────────────────────────────────────────────

    public function get_plan(): string
    {
        return (string) get_user_meta( $this->wp_user->ID, VX_User_Meta::PLAN, true );
    }

    public function get_plan_estado(): string
    {
        return (string) get_user_meta( $this->wp_user->ID, VX_User_Meta::PLAN_ESTADO, true );
    }

    /**
     * Badge de Fundador — PERMANENTE.
     * Independiente del plan de facturación actual.
     * Un fundador puede estar en plan 'mensual' o 'preferencial' y seguir teniendo el badge.
     */
    public function is_founder(): bool
    {
        return (bool) get_user_meta( $this->wp_user->ID, VX_User_Meta::ES_FUNDADOR, true );
    }

    public function has_precio_preferente(): bool
    {
        return (bool) get_user_meta( $this->wp_user->ID, VX_User_Meta::PRECIO_PREFERENTE, true );
    }

    // ── Comunidades ─────────────────────────────────────────────

    public function is_in_community( string $community ): bool
    {
        $key_map = [
            'out2b'  => VX_User_Meta::COMUNIDAD_OUT2B,
            'woman'  => VX_User_Meta::COMUNIDAD_WOMAN,
            'senior' => VX_User_Meta::COMUNIDAD_SENIOR,
        ];

        if ( ! isset( $key_map[ $community ] ) ) {
            return false;
        }

        return (bool) get_user_meta( $this->wp_user->ID, $key_map[ $community ], true );
    }

    public function is_senior_verified(): bool
    {
        return (bool) get_user_meta( $this->wp_user->ID, VX_User_Meta::SENIOR_VERIFICADO, true );
    }

    // ── Tags ─────────────────────────────────────────────────────

    public function get_offer_tags(): array
    {
        $tags = get_user_meta( $this->wp_user->ID, VX_User_Meta::OFFER_TAGS, true );
        return is_array( $tags ) ? $tags : [];
    }

    public function get_seek_tags(): array
    {
        $tags = get_user_meta( $this->wp_user->ID, VX_User_Meta::SEEK_TAGS, true );
        return is_array( $tags ) ? $tags : [];
    }

    public function get_offer_texto(): string
    {
        return (string) get_user_meta( $this->wp_user->ID, VX_User_Meta::OFFER_TEXTO, true );
    }

    public function get_seek_texto(): string
    {
        return (string) get_user_meta( $this->wp_user->ID, VX_User_Meta::SEEK_TEXTO, true );
    }

    public function get_genero(): string
    {
        return (string) get_user_meta( $this->wp_user->ID, VX_User_Meta::GENERO, true );
    }

    public function get_profile_tags(): array
    {
        $tags = get_user_meta( $this->wp_user->ID, VX_User_Meta::PROFILE_TAGS, true );
        return is_array( $tags ) ? $tags : [];
    }

    public function get_industria(): string
    {
        return (string) get_user_meta( $this->wp_user->ID, VX_User_Meta::INDUSTRIA, true );
    }

    // ── Empresas ─────────────────────────────────────────────────

    /**
     * Devuelve todos los posts vx_empresa del usuario.
     *
     * @return WP_Post[]
     */
    public function get_empresas(): array
    {
        return get_posts( [
            'post_type'      => 'vx_empresa',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                [ 'key' => 'vx_user_id', 'value' => $this->wp_user->ID ],
            ],
        ] );
    }

    /**
     * Devuelve la empresa activa del usuario, o null si no tiene.
     *
     * @return WP_Post|null
     */
    public function get_empresa_activa(): ?WP_Post
    {
        $posts = get_posts( [
            'post_type'      => 'vx_empresa',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_query'     => [
                [ 'key' => 'vx_user_id',       'value' => $this->wp_user->ID ],
                [ 'key' => 'vx_empresa_activa', 'value' => '1' ],
            ],
        ] );

        if ( ! empty( $posts ) ) {
            return $posts[0];
        }

        // Fallback: si no hay empresa marcada como activa, devolver la primera
        $all = $this->get_empresas();
        return ! empty( $all ) ? $all[0] : null;
    }

    /**
     * Formatea los datos del usuario para renderizar una tarjeta de directorio.
     *
     * @return array
     */
    public function to_card_array(): array
    {
        $empresa = $this->get_empresa_activa();

        return [
            'id'            => $this->wp_user->ID,
            'nombre'        => $this->get_nombre_completo(),
            'slug'          => $this->get_slug(),
            'foto_url'      => $this->get_foto_url(),
            'ciudad'        => $this->get_ciudad(),
            'pais'          => $this->get_pais(),
            'pais_codigo'   => $this->get_pais_codigo(),
            'offer_tags'    => $this->get_offer_tags(),
            'seek_tags'     => $this->get_seek_tags(),
            'profile_tags'  => $this->get_profile_tags(),
            'industria'     => $this->get_industria(),
            'empresa'       => $empresa ? $empresa->post_title : '',
            'empresa_id'    => $empresa ? $empresa->ID : 0,
            'cargo'         => $empresa ? get_post_meta( $empresa->ID, 'vx_cargo', true ) : '',
            'is_founder'    => $this->is_founder(),
            'comunidades'   => $this->get_comunidades_activas(),
            'onboarding_completo' => $this->is_onboarding_completo(),
        ];
    }

    /**
     * Devuelve array de comunidades activas del usuario.
     *
     * @return string[]
     */
    public function get_comunidades_activas(): array
    {
        $comunidades = [];
        if ( $this->is_in_community( 'out2b' ) )  $comunidades[] = 'out2b';
        if ( $this->is_in_community( 'woman' ) )  $comunidades[] = 'woman';
        if ( $this->is_in_community( 'senior' ) && $this->is_senior_verified() ) {
            $comunidades[] = 'senior';
        }
        return $comunidades;
    }

    // ── Setters ──────────────────────────────────────────────────

    public function set_estado( string $estado ): void
    {
        update_user_meta( $this->wp_user->ID, VX_User_Meta::ESTADO, $estado );
    }

    public function set_onboarding_completo( bool $value ): void
    {
        update_user_meta( $this->wp_user->ID, VX_User_Meta::ONBOARDING_COMPLETO, $value );
    }

    public function set_onboarding_paso( int $paso ): void
    {
        update_user_meta( $this->wp_user->ID, VX_User_Meta::ONBOARDING_PASO, $paso );
    }

    public function increment_visitas(): void
    {
        $visitas = (int) get_user_meta( $this->wp_user->ID, VX_User_Meta::VISITAS_PERFIL, true );
        update_user_meta( $this->wp_user->ID, VX_User_Meta::VISITAS_PERFIL, $visitas + 1 );
    }

    public function get_visitas(): int
    {
        return (int) get_user_meta( $this->wp_user->ID, VX_User_Meta::VISITAS_PERFIL, true );
    }

    // ── Favoritos ────────────────────────────────────────────────

    public function get_favoritos(): array
    {
        $favs = get_user_meta( $this->wp_user->ID, VX_User_Meta::FAVORITOS, true );
        return is_array( $favs ) ? array_map( 'intval', $favs ) : [];
    }

    public function add_favorito( int $target_user_id ): void
    {
        $favs = $this->get_favoritos();
        if ( ! in_array( $target_user_id, $favs, true ) ) {
            $favs[] = $target_user_id;
            update_user_meta( $this->wp_user->ID, VX_User_Meta::FAVORITOS, $favs );
        }
    }

    public function remove_favorito( int $target_user_id ): void
    {
        $favs = array_diff( $this->get_favoritos(), [ $target_user_id ] );
        update_user_meta( $this->wp_user->ID, VX_User_Meta::FAVORITOS, array_values( $favs ) );
    }

    public function is_favorito( int $target_user_id ): bool
    {
        return in_array( $target_user_id, $this->get_favoritos(), true );
    }

    // ── 4Dinner ──────────────────────────────────────────────────

    public function get_dinners_asignado(): array
    {
        $arr = get_user_meta( $this->wp_user->ID, VX_User_Meta::DINNERS_ASIGNADO, true );
        return is_array( $arr ) ? array_map( 'intval', $arr ) : [];
    }

    public function get_dinners_interesado(): array
    {
        $arr = get_user_meta( $this->wp_user->ID, VX_User_Meta::DINNERS_INTERESADO, true );
        return is_array( $arr ) ? array_map( 'intval', $arr ) : [];
    }
}
