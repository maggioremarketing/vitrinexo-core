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
        // Con revisiones: esto es contenido que se escribe a mano y hasta hoy
        // no guardaba historial, así que una edición mala no se podía deshacer.
        'supports'      => [ 'title', 'editor', 'page-attributes', 'revisions' ],
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

    <?php /* Los estilos viven en assets/css/style.css del tema: la portada
       muestra las mismas preguntas y necesitaba los mismos estilos, y dos
       copias de una hoja se separan igual que dos copias de un texto. */ ?>

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

/* ════════════════════════════════════════════════════════════════════════
   Preguntas destacadas en la portada
   ════════════════════════════════════════════════════════════════════════ */

/**
 * La portada muestra unas pocas preguntas y la página completa las muestra
 * todas. Salen de la misma fuente a propósito: la propuesta traía diez
 * preguntas escritas aparte, y dos copias de la misma respuesta se separan
 * sin que nadie lo note, que es exactamente el problema que las páginas
 * legales tuvieron durante meses.
 *
 * Se eligen marcando «Destacada en la portada» en la pregunta. Mientras no
 * haya ninguna marcada, la portada muestra las primeras publicadas, así que
 * la sección nunca sale vacía.
 */
add_action( 'add_meta_boxes', function () {
    add_meta_box(
        'vx_faq_destacada',
        'Portada',
        function ( $post ) {
            $on = get_post_meta( $post->ID, '_vx_faq_destacada', true );
            wp_nonce_field( 'vx_faq_destacada', 'vx_faq_destacada_nonce' );
            echo '<label><input type="checkbox" name="vx_faq_destacada" value="1" ' . checked( $on, '1', false ) . '> Destacada en la portada</label>';
            echo '<p class="description">La portada muestra las destacadas. Si no hay ninguna, muestra las primeras publicadas.</p>';
        },
        'vx_faq',
        'side'
    );
} );

add_action( 'save_post_vx_faq', function ( $post_id ) {
    if ( ! isset( $_POST['vx_faq_destacada_nonce'] ) ) return;
    if ( ! wp_verify_nonce( sanitize_key( $_POST['vx_faq_destacada_nonce'] ), 'vx_faq_destacada' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    if ( empty( $_POST['vx_faq_destacada'] ) ) delete_post_meta( $post_id, '_vx_faq_destacada' );
    else update_post_meta( $post_id, '_vx_faq_destacada', '1' );
} );

/**
 * Las preguntas de la portada.
 *
 * @return WP_Post[]
 */
function vx_faq_destacadas( int $cuantas = 8 ): array {
    $marcadas = get_posts( [
        'post_type'      => 'vx_faq',
        'posts_per_page' => $cuantas,
        'orderby'        => 'menu_order date',
        'order'          => 'ASC',
        'meta_key'       => '_vx_faq_destacada',
        'meta_value'     => '1',
    ] );
    if ( $marcadas ) return $marcadas;

    return get_posts( [
        'post_type'      => 'vx_faq',
        'posts_per_page' => $cuantas,
        'orderby'        => 'menu_order date',
        'order'          => 'ASC',
    ] );
}
