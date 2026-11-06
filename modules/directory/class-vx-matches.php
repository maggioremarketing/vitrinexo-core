<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Algoritmo de matches de Vitrinexo.
 * Match "seeks": usuarios cuyos offer_tags intersectan con mis seek_tags.
 * Match "offers": usuarios cuyos seek_tags intersectan con mis offer_tags.
 */
class VX_Matches
{
    const PER_PAGE = 20;

    /**
     * Devuelve usuarios que "ofrecen lo que busco" (seeks matches).
     *
     * @param int   $user_id
     * @param array $args    page, per_page
     * @return array  ['users' => VX_User[], 'total' => int, 'pages' => int]
     */
    public static function get_seeks_matches( int $user_id, array $args = [] ): array
    {
        $user = VX_User::get( $user_id );
        if ( ! $user ) {
            return self::empty_result();
        }

        $my_seek_tags = $user->get_seek_tags();
        if ( empty( $my_seek_tags ) ) {
            return self::empty_result();
        }

        return self::find_matches( $user_id, $my_seek_tags, 'offer', $args );
    }

    /**
     * Devuelve usuarios que "buscan lo que ofrezco" (offers matches).
     *
     * @param int   $user_id
     * @param array $args    page, per_page
     * @return array
     */
    public static function get_offers_matches( int $user_id, array $args = [] ): array
    {
        $user = VX_User::get( $user_id );
        if ( ! $user ) {
            return self::empty_result();
        }

        $my_offer_tags = $user->get_offer_tags();
        if ( empty( $my_offer_tags ) ) {
            return self::empty_result();
        }

        return self::find_matches( $user_id, $my_offer_tags, 'seek', $args );
    }

    /**
     * Calcula el score de coincidencia entre dos arrays de tags.
     *
     * @param array $tags_a
     * @param array $tags_b
     * @return float  Entre 0 y 1.
     */
    public static function calculate_score( array $tags_a, array $tags_b ): float
    {
        return VX_Tag_Helper::score( $tags_a, $tags_b );
    }

    /**
     * Devuelve nexos nuevos del usuario desde una fecha determinada.
     * Usado para el resumen semanal.
     *
     * @param int $user_id
     * @param int $since   Timestamp desde cuándo son "nuevos"
     * @return array  ['seeks' => VX_User[], 'offers' => VX_User[]]
     */
    public static function get_new_since( int $user_id, int $since ): array
    {
        // Para simplificar, devolvemos todos los matches actuales.
        // En una versión futura se puede cachear y comparar con los anteriores.
        $seeks  = self::get_seeks_matches( $user_id, [ 'per_page' => 5 ] );
        $offers = self::get_offers_matches( $user_id, [ 'per_page' => 5 ] );

        return [
            'seeks'  => array_map( fn( $u ) => $u->to_card_array(), $seeks['users'] ),
            'offers' => array_map( fn( $u ) => $u->to_card_array(), $offers['users'] ),
        ];
    }

    // ── Privados ─────────────────────────────────────────────────

    private static function find_matches(
        int $user_id,
        array $my_tags,
        string $match_meta_key_type, // 'offer' o 'seek'
        array $args
    ): array {
        $page     = max( 1, absint( $args['page'] ?? 1 ) );
        $per_page = absint( $args['per_page'] ?? self::PER_PAGE );

        $meta_key = 'offer' === $match_meta_key_type ? VX_User_Meta::OFFER_TAGS : VX_User_Meta::SEEK_TAGS;

        // Obtener todos los candidatos activos (excepto el usuario actual y los bloqueados en cualquier dirección)
        $excluir = array_merge( [ $user_id ], function_exists( 'vx_hidden_user_ids' ) ? vx_hidden_user_ids( $user_id ) : [] );
        $all_ids = get_users( [
            'role'       => 'subscriber',
            'fields'     => 'ids',
            'number'     => -1,
            'exclude'    => $excluir,
            'meta_query' => [
                'relation' => 'AND',
                [ 'key' => VX_User_Meta::ESTADO,              'value' => 'activo' ],
                [ 'key' => VX_User_Meta::ONBOARDING_COMPLETO, 'value' => '1' ],
                [ 'key' => $meta_key,                         'compare' => 'EXISTS' ],
            ],
        ] );

        $scored = [];

        foreach ( $all_ids as $uid ) {
            $candidate = VX_User::get( $uid );
            if ( ! $candidate ) continue;

            $candidate_tags = 'offer' === $match_meta_key_type
                ? $candidate->get_offer_tags()
                : $candidate->get_seek_tags();

            if ( empty( $candidate_tags ) ) continue;

            $score = self::calculate_score( $my_tags, $candidate_tags );
            if ( $score > 0 ) {
                $scored[ $uid ] = $score;
            }
        }

        arsort( $scored );
        $matching_ids = array_keys( $scored );
        $total        = count( $matching_ids );

        $offset   = ( $page - 1 ) * $per_page;
        $page_ids = array_slice( $matching_ids, $offset, $per_page );

        $users = array_filter( array_map( [ 'VX_User', 'get' ], $page_ids ) );

        return [
            'users'      => array_values( $users ),
            'total'      => $total,
            'pages'      => (int) ceil( $total / max( 1, $per_page ) ),
            'pagination' => VX_Pagination::build( $total, $per_page, $page ),
        ];
    }

    private static function empty_result(): array
    {
        return [
            'users'      => [],
            'total'      => 0,
            'pages'      => 0,
            'pagination' => VX_Pagination::build( 0, self::PER_PAGE, 1 ),
        ];
    }
}
