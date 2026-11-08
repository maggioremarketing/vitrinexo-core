<?php
/**
 * Rutas viejas que tienen que seguir llevando a alguna parte.
 *
 * Una URL que alguien pudo guardar, enlazar o encontrar en Google no se borra:
 * se redirige. Si se borra y ya está, quien la tenía se come un 404 y Google
 * tarda meses en olvidarla.
 *
 * El hook va en `template_redirect` y no en una regla de WordPress a propósito:
 * así funciona igual exista la página o no. /inicio/ se manda a la papelera en
 * la migración de la misma fecha, y sin esto empezaría a dar 404 el mismo día.
 *
 * El 301 es permanente y los navegadores lo guardan con ganas: una entrada mal
 * puesta acá es difícil de deshacer en el navegador de quien ya la visitó. Por
 * eso el mapa es corto, explícito, y no acepta patrones.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

final class VX_Redirecciones {

    /**
     * De dónde a dónde. Las claves son rutas exactas, con las dos barras.
     *
     * /inicio/ era un duplicado de la portada: dos páginas distintas de
     * WordPress (id 5 y id 259) sirviendo la misma landing, y la segunda con
     * canonical apuntándose a sí misma, que es lo peor de los dos mundos. Nada
     * del sitio la enlazaba; se llegaba solo por el sitemap o por Google.
     */
    private const MAPA = [
        '/inicio/' => '/',
    ];

    public static function init(): void {
        add_action( 'template_redirect', [ self::class, 'redirigir' ], 1 );
    }

    public static function redirigir(): void {
        if ( is_admin() ) return;

        $pedido = strtok( $_SERVER['REQUEST_URI'] ?? '', '?' );
        if ( ! is_string( $pedido ) || '' === $pedido ) return;

        // Se compara con la barra final puesta, que es como WordPress sirve las
        // páginas, pero se acepta la forma sin barra para no depender de eso.
        $ruta = '/' . trim( $pedido, '/' );
        if ( '/' !== $ruta ) $ruta .= '/';

        if ( ! isset( self::MAPA[ $ruta ] ) ) return;

        wp_safe_redirect( home_url( self::MAPA[ $ruta ] ), 301 );
        exit;
    }
}

VX_Redirecciones::init();
