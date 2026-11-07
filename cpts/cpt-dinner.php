<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', function () {
    register_post_type( 'vx_dinner', [
        'labels' => [
            'name'               => 'Cenas Vitrinexo 4Dinner',
            'singular_name'      => 'Vitrinexo 4Dinner',
            'add_new'            => 'Crear evento',
            'add_new_item'       => 'Crear nuevo Vitrinexo 4Dinner',
            'edit_item'          => 'Editar Vitrinexo 4Dinner',
            'view_item'          => 'Ver Vitrinexo 4Dinner',
            'search_items'       => 'Buscar eventos',
            'not_found'          => 'No se encontraron eventos',
            'not_found_in_trash' => 'No hay eventos en la papelera',
        ],
        'public'              => false,
        'publicly_queryable'  => false,
        'show_ui'             => true,
        'show_in_menu'        => 'vitrinexo-core',
        'show_in_rest'        => false,
        'supports'            => [ 'title' ],
        'menu_icon'           => 'dashicons-food',
        'capability_type'     => 'post',
        'map_meta_cap'        => true,
        'rewrite'             => false,
    ] );
} );
