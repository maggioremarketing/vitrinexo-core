<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Preguntas frecuentes (FAQ) administrables desde WordPress.
 *
 * - CPT  vx_faq      → cada entrada es una pregunta (título) + respuesta (contenido).
 * - Tax. vx_faq_cat  → categorías temáticas, ordenables con la meta 'vx_orden'.
 * - Shortcode [vx_faq] → acordeón agrupado por categoría con buscador en vivo.
 *
 * El administrador gestiona todo desde WordPress → "Preguntas frecuentes".
 */

// ── Registro del CPT y la taxonomía ─────────────────────────────────────────
add_action( 'init', function () {

    register_post_type( 'vx_faq', [
        'labels' => [
            'name'               => 'Preguntas frecuentes',
            'singular_name'      => 'Pregunta frecuente',
            'menu_name'          => 'Preguntas frecuentes',
            'add_new'            => 'Añadir pregunta',
            'add_new_item'       => 'Añadir pregunta',
            'edit_item'          => 'Editar pregunta',
            'new_item'           => 'Nueva pregunta',
            'view_item'          => 'Ver pregunta',
            'search_items'       => 'Buscar preguntas',
            'all_items'          => 'Todas las preguntas',
            'not_found'          => 'No hay preguntas todavía.',
            'not_found_in_trash' => 'No hay preguntas en la papelera.',
        ],
        'public'        => false,
        'show_ui'       => true,
        'show_in_menu'  => true,
        'show_in_rest'  => true,
        'menu_icon'     => 'dashicons-editor-help',
        'menu_position' => 26,
        'supports'      => [ 'title', 'editor', 'page-attributes' ],
        'has_archive'   => false,
        'rewrite'       => false,
        'hierarchical'  => false,
    ] );

    register_taxonomy( 'vx_faq_cat', 'vx_faq', [
        'labels' => [
            'name'              => 'Categorías',
            'singular_name'     => 'Categoría',
            'menu_name'         => 'Categorías',
            'add_new_item'      => 'Añadir categoría',
            'edit_item'         => 'Editar categoría',
            'search_items'      => 'Buscar categorías',
            'all_items'         => 'Todas las categorías',
        ],
        'public'            => false,
        'show_ui'           => true,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'hierarchical'      => true,
        'rewrite'           => false,
    ] );

    // Meta de orden de las categorías (editable vía REST; menor = primero).
    register_term_meta( 'vx_faq_cat', 'vx_orden', [
        'type'              => 'integer',
        'single'            => true,
        'default'           => 0,
        'show_in_rest'      => true,
        'sanitize_callback' => 'absint',
        'auth_callback'     => function () { return current_user_can( 'manage_categories' ); },
    ] );
} );

// ── Helpers ──────────────────────────────────────────────────────────────────

/** Categorías FAQ ordenadas por la meta 'vx_orden' (asc) y luego por nombre. */
function vx_faq_categorias_ordenadas(): array {
    $terms = get_terms( [ 'taxonomy' => 'vx_faq_cat', 'hide_empty' => true ] );
    if ( is_wp_error( $terms ) || ! $terms ) return [];
    usort( $terms, function ( $a, $b ) {
        $oa = (int) get_term_meta( $a->term_id, 'vx_orden', true );
        $ob = (int) get_term_meta( $b->term_id, 'vx_orden', true );
        return $oa === $ob ? strcmp( $a->name, $b->name ) : ( $oa <=> $ob );
    } );
    return $terms;
}

// ── Shortcode [vx_faq] ─────────────────────────────────────────────────────
add_shortcode( 'vx_faq', function (): string {
    $cats = vx_faq_categorias_ordenadas();

    ob_start(); ?>
    <div class="vx-faq">
      <div class="vx-faq__search">
        <i class="ti ti-search" aria-hidden="true"></i>
        <input type="search" id="vxFaqSearch" placeholder="Buscar en las preguntas frecuentes…" autocomplete="off" aria-label="Buscar en las preguntas frecuentes">
      </div>
      <p class="vx-faq__empty" id="vxFaqEmpty" hidden>No encontramos preguntas para tu búsqueda.</p>

      <?php if ( empty( $cats ) ) : ?>
        <p class="vx-faq__none">Todavía no hay preguntas publicadas.</p>
      <?php else : foreach ( $cats as $cat ) :
          $q = new WP_Query( [
              'post_type'      => 'vx_faq',
              'posts_per_page' => -1,
              'orderby'        => [ 'menu_order' => 'ASC', 'title' => 'ASC' ],
              'tax_query'      => [ [ 'taxonomy' => 'vx_faq_cat', 'field' => 'term_id', 'terms' => $cat->term_id ] ],
              'no_found_rows'  => true,
          ] );
          if ( ! $q->have_posts() ) { wp_reset_postdata(); continue; } ?>
        <section class="vx-faq__cat" data-faq-cat>
          <h2 class="vx-faq__cat-title"><?php echo esc_html( $cat->name ); ?></h2>
          <?php while ( $q->have_posts() ) : $q->the_post(); ?>
            <details class="vx-faq__item" data-faq-item>
              <summary class="vx-faq__q"><span><?php echo esc_html( get_the_title() ); ?></span><i class="ti ti-chevron-down vx-faq__chev" aria-hidden="true"></i></summary>
              <div class="vx-faq__a"><?php echo wp_kses_post( wpautop( get_the_content() ) ); ?></div>
            </details>
          <?php endwhile; wp_reset_postdata(); ?>
        </section>
      <?php endforeach; endif; ?>
    </div>

    <style>
      .vx-faq{max-width:820px;margin:0 auto}
      .vx-faq__search{position:relative;margin-bottom:1.75rem}
      .vx-faq__search .ti{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--color-text-secondary,var(--tinta-2));font-size:18px}
      .vx-faq__search input{width:100%;padding:13px 16px 13px 42px;border:1px solid var(--color-border,var(--linea));border-radius:12px;font-size:15px;background:var(--blanco);transition:border-color .15s,box-shadow .15s}
      .vx-faq__search input:focus{outline:none;border-color:var(--color-primary,var(--coral));box-shadow:0 0 0 3px rgba(44,206,214,.15)}
      .vx-faq__cat{margin-bottom:2rem}
      .vx-faq__cat-title{font-size:1.05rem;font-weight:700;color:var(--color-primary,var(--salvia-presion));margin:0 0 .5rem;padding-bottom:.4rem;border-bottom:2px solid var(--color-primary,var(--coral))}
      .vx-faq__item{border:1px solid var(--color-border,var(--linea));border-radius:12px;margin-bottom:.6rem;background:var(--blanco);overflow:hidden}
      .vx-faq__item[open]{border-color:var(--color-primary,var(--coral))}
      .vx-faq__q{list-style:none;cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:15px 18px;font-weight:600;color:var(--color-text-primary,var(--tinta));user-select:none}
      .vx-faq__q::-webkit-details-marker{display:none}
      .vx-faq__q:hover{background:rgba(44,206,214,.05)}
      .vx-faq__chev{transition:transform .2s;color:var(--color-primary,var(--coral));flex-shrink:0}
      .vx-faq__item[open] .vx-faq__chev{transform:rotate(180deg)}
      .vx-faq__a{padding:0 18px 16px;color:var(--color-text-secondary,var(--tinta-2));line-height:1.6}
      .vx-faq__a p{margin:0 0 .6rem}
      .vx-faq__a p:last-child{margin-bottom:0}
      .vx-faq__empty,.vx-faq__none{color:var(--color-text-secondary,var(--tinta-2));text-align:center;padding:1rem 0}
    </style>

    <script>
    (function(){
      var input = document.getElementById('vxFaqSearch');
      if(!input) return;
      var items = Array.prototype.slice.call(document.querySelectorAll('[data-faq-item]'));
      var cats  = Array.prototype.slice.call(document.querySelectorAll('[data-faq-cat]'));
      var empty = document.getElementById('vxFaqEmpty');
      function norm(s){return (s||'').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'');}
      input.addEventListener('input', function(){
        var q = norm(input.value.trim());
        var anyVisible = false;
        items.forEach(function(it){
          var txt = norm(it.textContent);
          var hit = q === '' || txt.indexOf(q) !== -1;
          it.style.display = hit ? '' : 'none';
          if(hit) anyVisible = true;
          if(q !== '' && hit){ it.setAttribute('open',''); } else if(q === ''){ it.removeAttribute('open'); }
        });
        cats.forEach(function(c){
          var vis = Array.prototype.slice.call(c.querySelectorAll('[data-faq-item]')).some(function(it){return it.style.display !== 'none';});
          c.style.display = vis ? '' : 'none';
        });
        if(empty) empty.hidden = anyVisible;
      });
    })();
    </script>
    <?php
    return ob_get_clean();
} );
