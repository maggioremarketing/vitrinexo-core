<?php
/**
 * Tres páginas publicadas que nadie debería ver se van a la papelera.
 *
 * Las encontró una auditoría de rutas que comparó el sitemap de producción
 * contra las listas de las pruebas: 38 páginas publicadas, 26 medidas. Estas
 * tres estaban entre las que ninguna prueba abría nunca.
 *
 *   /inicio/         duplicado de la portada. Dos páginas distintas de
 *                    WordPress (id 5 y id 259) sirviendo la misma landing,
 *                    10.653 caracteres contra 10.644, y la segunda con
 *                    canonical apuntándose a sí misma. Nada del sitio la
 *                    enlaza: se llegaba por el sitemap o por Google.
 *   /privacy-policy/ la política de privacidad EN INGLÉS que crea el
 *                    instalador de WordPress. La de verdad es /privacidad/,
 *                    en español. Un producto que habla en chileno tenía 34 KB
 *                    de texto en inglés publicados e indexables.
 *   /__trashed/      titulada «Borrador automático». Un borrador que quedó
 *                    publicado, 27 KB.
 *
 * Papelera y no borrado definitivo: consigue lo mismo —dejan de servirse y
 * dejan de estar en el sitemap— y se puede deshacer desde el escritorio. El
 * borrado definitivo no, y acá no hace falta.
 *
 * /inicio/ además necesita la redirección 301 de VX_Redirecciones, que va en el
 * mismo despliegue: sin ella, quien la tenga guardada o la encuentre en un
 * resultado viejo de Google se come un 404.
 *
 * Cada página se mira antes de tocarla y se exige que sea la que creemos. La
 * que no coincida se anota en `intactas` y se queda como está: esto corre una
 * sola vez contra una base de datos que no podemos leer desde el repositorio,
 * así que equivocarse en silencio no es una opción.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'VX_MIG_PAGINAS_SOBRANTES', 'vx_migracion_paginas_sobrantes' );

add_action( 'init', function () {
    if ( get_option( VX_MIG_PAGINAS_SOBRANTES ) ) return;

    /* slug => el título exacto que tiene que tener para que la toquemos. */
    $sobrantes = [
        'inicio'         => 'Inicio',
        'privacy-policy' => 'Privacy Policy',
        '__trashed'      => 'Borrador automático',
    ];

    /* Las páginas que el sitio usa de verdad. Tocar cualquiera de estas sería
       dejar el sitio sin portada, y el error no se vería hasta que alguien
       entrara. Se leen de la configuración, no se adivinan. */
    $intocables = array_filter( [
        (int) get_option( 'page_on_front' ),
        (int) get_option( 'page_for_posts' ),
    ] );

    $respaldo = [];
    $intactas = [];

    foreach ( $sobrantes as $slug => $titulo_esperado ) {
        $pagina = get_page_by_path( $slug );

        if ( ! $pagina ) {
            $intactas[ $slug ] = 'no existe';
            continue;
        }

        if ( in_array( (int) $pagina->ID, $intocables, true ) ) {
            $intactas[ $slug ] = "es la portada o la página de entradas del sitio (id {$pagina->ID})";
            continue;
        }

        if ( 'publish' !== $pagina->post_status ) {
            $intactas[ $slug ] = "no está publicada, está en «{$pagina->post_status}»";
            continue;
        }

        if ( $pagina->post_title !== $titulo_esperado ) {
            $intactas[ $slug ] = "se llama «{$pagina->post_title}» y no «{$titulo_esperado}»";
            continue;
        }

        /* WordPress guarda en una opción cuál es su página de privacidad. Si
           apunta a la que nos llevamos, hay que moverla antes: si no, queda
           señalando a algo en la papelera y el escritorio lo muestra roto. */
        if ( (int) get_option( 'wp_page_for_privacy_policy' ) === (int) $pagina->ID ) {
            $buena = get_page_by_path( 'privacidad' );
            if ( $buena && 'publish' === $buena->post_status ) {
                update_option( 'wp_page_for_privacy_policy', $buena->ID );
                $respaldo['wp_page_for_privacy_policy'] = $pagina->ID;
            } else {
                $intactas[ $slug ] = 'es la página de privacidad de WordPress y no hay otra publicada que ponga en su lugar';
                continue;
            }
        }

        $respaldo[ $slug ] = [
            'id'     => $pagina->ID,
            'titulo' => $pagina->post_title,
            'estado' => $pagina->post_status,
        ];

        wp_trash_post( $pagina->ID );
    }

    update_option( VX_MIG_PAGINAS_SOBRANTES, [
        'fecha'    => current_time( 'mysql' ),
        'cuantas'  => count( array_filter( $respaldo, 'is_array' ) ),
        'respaldo' => $respaldo,
        'intactas' => $intactas,
    ], false );
}, 27 );
