<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Helper: renderiza una tarjeta de publicación ──────────────────────────────

function vx_render_pub_card( WP_Post $post, int $viewer_id, bool $compact = false ): string
{
    $autor_id  = (int) $post->post_author;
    $autor     = VX_User::get( $autor_id );
    if ( ! $autor ) return '';

    $tipo      = get_post_meta( $post->ID, 'vx_pub_tipo', true ) ?: 'ofrece';
    $es_propio = $viewer_id === $autor_id;
    $emp       = $autor->get_empresa_activa();
    $n_comments= (int) get_comments_number( $post->ID );
    $fecha     = human_time_diff( strtotime( $post->post_date ), current_time( 'timestamp' ) ) . ' atrás';

    $tipo_label = 'ofrece' === $tipo ? 'Ofrece' : 'Busca';
    $tipo_badge = 'ofrece' === $tipo ? 'badge-success' : 'badge-danger';

    $ep_delete  = rest_url( VX_REST_NAMESPACE . '/feed/' . $post->ID );
    $ep_comment = rest_url( VX_REST_NAMESPACE . '/feed/' . $post->ID . '/comentar' );
    $nonce      = wp_create_nonce( 'wp_rest' );

    // Comentarios — para la vista compacta no cargamos nada
    $comments       = [];
    $total_comments = 0;
    if ( ! $compact ) {
        $comments = get_comments( [
            'post_id' => $post->ID,
            'status'  => 'approve',
            'orderby' => 'comment_date',
            'order'   => 'ASC',
            'number'  => 3,
        ] );
        $total_comments = (int) get_comments_number( $post->ID );
    }

    ob_start();
    ?>
    <div class="card-vx mb-3" id="pub-<?php echo $post->ID; ?>">

      <!-- Cabecera del autor -->
      <div class="d-flex align-items-start gap-3 mb-3">
        <a href="<?php echo esc_url( home_url( '/perfil/' . $autor->get_slug() . '/' ) ); ?>" class="flex-shrink-0">
          <img src="<?php echo esc_url( $autor->get_foto_url( 'vx-avatar' ) ); ?>" alt="" class="pub-avatar">
        </a>
        <div class="flex-grow-1 min-w-0">
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="<?php echo esc_url( home_url( '/perfil/' . $autor->get_slug() . '/' ) ); ?>" class="pub-author-name">
              <?php echo esc_html( $autor->get_nombre_completo() ); ?>
            </a>
            <span class="badge-vx <?php echo $tipo_badge; ?>"><?php echo $tipo_label; ?></span>
          </div>
          <?php if ( $emp ) : ?>
          <div class="pub-author-meta">
            <?php echo esc_html( $emp->post_title ); ?><?php if ( $autor->get_ciudad() ) echo ' · ' . esc_html( $autor->get_ciudad() ); ?>
          </div>
          <?php endif; ?>
          <div class="pub-date"><?php echo esc_html( $fecha ); ?></div>
        </div>
        <?php if ( $es_propio ) : ?>
        <button class="btn-vx btn-ghost-vx btn-vx-sm ms-auto flex-shrink-0"
                onclick="vxEliminarPub(<?php echo $post->ID; ?>,'<?php echo esc_js( $ep_delete ); ?>','<?php echo esc_js( $nonce ); ?>')"
                title="Eliminar publicación">
          <i class="ti ti-trash"></i>
        </button>
        <?php endif; ?>
      </div>

      <!-- Contenido -->
      <p class="pub-body<?php echo $compact ? ' mb-0' : ''; ?>">
        <?php echo esc_html( $post->post_content ); ?>
      </p>

      <?php if ( ! $compact ) : ?>
      <!-- Footer acciones -->
      <div class="d-flex align-items-center gap-2 pt-3 pub-card-footer flex-wrap">
        <button class="btn-vx btn-ghost-vx btn-vx-sm"
                onclick="vxToggleComments(<?php echo $post->ID; ?>)">
          <i class="ti ti-message-circle"></i>
          <span id="pub-<?php echo $post->ID; ?>-count"><?php echo $n_comments; ?></span>
          <?php echo $n_comments === 1 ? 'comentario' : 'comentarios'; ?>
        </button>
        <?php if ( $viewer_id && ! $es_propio ) : ?>
        <button class="btn-vx btn-soft-secondary btn-vx-sm"
                data-bs-toggle="modal" data-bs-target="#modalConectar"
                data-receptor-id="<?php echo $autor_id; ?>"
                data-receptor-nombre="<?php echo esc_attr( $autor->get_nombre() ); ?>"
                data-receptor-empresa="<?php echo esc_attr( $emp ? $emp->post_title : '' ); ?>">
          <i class="ti ti-plug"></i> Conectar
        </button>
        <?php endif; ?>
      </div>

      <!-- Sección de comentarios (colapsable) -->
      <div id="pub-<?php echo $post->ID; ?>-comments" class="mt-3" style="display:none">

        <div id="pub-<?php echo $post->ID; ?>-list">
          <?php foreach ( $comments as $c ) :
              $c_user    = VX_User::get( (int) $c->user_id );
              $c_foto    = $c_user ? $c_user->get_foto_url( 'vx-avatar' ) : '';
              $c_url     = $c_user ? home_url( '/perfil/' . $c_user->get_slug() . '/' ) : '';
          ?>
          <div class="d-flex gap-2 mb-3">
            <?php if ( $c_foto ) : ?>
            <?php if ( $c_url ) : ?><a href="<?php echo esc_url( $c_url ); ?>" class="flex-shrink-0"><?php endif; ?>
            <img src="<?php echo esc_url( $c_foto ); ?>" alt="" class="pub-comment-avatar">
            <?php if ( $c_url ) : ?></a><?php endif; ?>
            <?php endif; ?>
            <div class="pub-comment-bubble flex-grow-1">
              <div class="d-flex align-items-center gap-1 mb-1">
                <?php if ( $c_url ) : ?>
                <a href="<?php echo esc_url( $c_url ); ?>" class="pub-comment-author"><?php echo esc_html( $c->comment_author ); ?></a>
                <?php else : ?>
                <span class="pub-comment-author"><?php echo esc_html( $c->comment_author ); ?></span>
                <?php endif; ?>
                <span class="pub-comment-date"><?php echo esc_html( date_i18n( 'j M Y', strtotime( $c->comment_date ) ) ); ?></span>
              </div>
              <p class="pub-comment-text"><?php echo nl2br( esc_html( $c->comment_content ) ); ?></p>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if ( $total_comments > 3 ) : ?>
          <button class="btn-vx btn-ghost-vx btn-vx-sm mb-3"
                  onclick="vxLoadAllComments(<?php echo $post->ID; ?>)">
            Ver los <?php echo $total_comments; ?> comentarios
          </button>
          <?php endif; ?>
        </div>

        <?php if ( $viewer_id ) :
          $me = VX_User::get( $viewer_id );
        ?>
        <div class="d-flex align-items-start gap-2 mt-2">
          <?php if ( $me ) : ?>
          <img src="<?php echo esc_url( $me->get_foto_url( 'vx-avatar' ) ); ?>" alt="" class="pub-comment-avatar mt-1">
          <?php endif; ?>
          <div class="search-bar-vx flex-grow-1" style="padding:6px 10px">
            <textarea id="pub-<?php echo $post->ID; ?>-input" rows="1"
                      placeholder="Escribe un comentario…"
                      onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();vxEnviarComentario(<?php echo $post->ID; ?>,'<?php echo esc_js( $ep_comment ); ?>','<?php echo esc_js( $nonce ); ?>');}"></textarea>
            <button class="btn-vx btn-primary-vx btn-vx-sm flex-shrink-0"
                    onclick="vxEnviarComentario(<?php echo $post->ID; ?>,'<?php echo esc_js( $ep_comment ); ?>','<?php echo esc_js( $nonce ); ?>')">
              <i class="ti ti-send"></i>
            </button>
          </div>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; // !compact ?>

    </div>
    <?php
    return ob_get_clean();
}

// ── [vx_mis_publicaciones] ────────────────────────────────────────────────────

add_shortcode( 'vx_mis_publicaciones', function (): string {
    $user_id = get_current_user_id();
    if ( ! $user_id ) return '';

    $loop = new WP_Query( [
        'post_type'      => 'vx_publicacion',
        'post_status'    => 'publish',
        'author'         => $user_id,
        'posts_per_page' => 50,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ] );

    ob_start();
    ?>
    <div class="page-header-vx">
      <div class="container">
        <div class="page-header-vx__inner">
          <div>
            <h1 class="page-header-vx__title">Mis publicaciones</h1>
            <p class="page-header-vx__lead">Lo que has publicado en Oportunidades y los comentarios recibidos.</p>
          </div>
          <div>
            <a href="<?php echo esc_url( home_url( '/oportunidades/' ) ); ?>" class="btn-vx btn-ghost-vx btn-vx-sm">
              <i class="ti ti-arrow-left me-1"></i>Ir a Oportunidades
            </a>
          </div>
        </div>
      </div>
    </div>
    <main>
    <div class="container py-4">
    <div class="row justify-content-center">
    <div class="col-12 col-lg-8">

      <?php if ( $loop->have_posts() ) : ?>
        <?php while ( $loop->have_posts() ) : $loop->the_post(); ?>
          <?php echo vx_render_pub_card( get_post(), $user_id ); ?>
        <?php endwhile; wp_reset_postdata(); ?>
      <?php else : ?>
      <div class="empty-state-vx py-5 text-center">
        <div class="empty-state-vx__icon"><i class="ti ti-file-text"></i></div>
        <p class="empty-state-vx__title">Aún no has publicado nada</p>
        <p class="empty-state-vx__desc">Ve a Oportunidades y comparte lo que ofreces o buscas.</p>
        <a href="<?php echo esc_url( home_url( '/oportunidades/' ) ); ?>" class="btn-vx btn-primary-vx btn-vx-sm mt-3">Ir a Oportunidades</a>
      </div>
      <?php endif; ?>

    </div>
    </div>
    </div>
    </main>
    <?php echo vx_modal_conectar_html(); ?>
    <?php
    return ob_get_clean();
} );

// ── [vx_feed] ─────────────────────────────────────────────────────────────────

add_shortcode( 'vx_feed', function (): string {
    $viewer_id = get_current_user_id();
    $pagina    = max( 1, (int) ( $_GET['pagina'] ?? 1 ) );
    $q         = sanitize_text_field( $_GET['q'] ?? '' );
    $tipo_fil  = sanitize_text_field( $_GET['tipo'] ?? '' );
    $per_page  = 15;
    $base_url  = strtok( $_SERVER['REQUEST_URI'] ?? home_url( '/oportunidades/' ), '?' );

    $args = [
        'post_type'      => 'vx_publicacion',
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $pagina,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ];

    if ( $q ) {
        $args['s'] = $q;
    }

    if ( $tipo_fil && in_array( $tipo_fil, [ 'ofrece', 'busca' ], true ) ) {
        $args['meta_query'] = [ [ 'key' => 'vx_pub_tipo', 'value' => $tipo_fil ] ];
    }

    $loop   = new WP_Query( $args );
    $total  = $loop->found_posts;
    $pages  = (int) ceil( $total / $per_page );

    $ep_crear = rest_url( VX_REST_NAMESPACE . '/feed' );
    $nonce    = wp_create_nonce( 'wp_rest' );

    ob_start();
    ?>
    <div class="page-header-vx">
      <div class="container">
        <div class="page-header-vx__inner">
          <div>
            <div class="d-flex align-items-center gap-2 mb-1">
              <span class="section-landing-label" style="margin:0">Comunidad</span>
            </div>
            <h1 class="page-header-vx__title">Oportunidades</h1>
            <p class="page-header-vx__lead">Lo que los miembros ofrecen y buscan en este momento.</p>
          </div>
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge-vx badge-neutral">
              <i class="ti ti-file-text me-1"></i><?php echo $total; ?> publicaci<?php echo $total === 1 ? 'ón' : 'ones'; ?>
            </span>
            <?php if ( $viewer_id ) : ?>
            <button class="btn-vx btn-primary-vx btn-vx-sm" id="btn-nueva-pub"
                    onclick="document.getElementById('panel-nueva-pub').style.display='';this.style.display='none'">
              <i class="ti ti-pencil-plus me-1"></i>Publicar
            </button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <main>
    <div class="container py-4">
    <div class="row justify-content-center">
    <div class="col-12 col-lg-8">

      <?php if ( $viewer_id ) : ?>
      <!-- ── Nueva publicación ─────────────────────────────────────────── -->
      <div id="panel-nueva-pub" style="display:none" class="card-vx mb-4">
        <div class="d-flex gap-2 mb-3">
          <button type="button" class="btn-vx btn-ghost-vx flex-fill vx-tipo-sel vx-tipo-sel--active"
                  data-tipo="ofrece" onclick="vxSelTipo('ofrece',this)">
            <i class="ti ti-arrow-up-circle me-1"></i>Ofrece
          </button>
          <button type="button" class="btn-vx btn-ghost-vx flex-fill vx-tipo-sel"
                  data-tipo="busca" onclick="vxSelTipo('busca',this)">
            <i class="ti ti-arrow-down-circle me-1"></i>Busca
          </button>
        </div>
        <textarea id="vx-nueva-pub-texto" rows="4" maxlength="1500"
                  class="form-control-vx w-100 mb-2"
                  placeholder="¿Qué ofreces o qué estás buscando? Sé específico para conectar mejor…"
                  style="resize:vertical"></textarea>
        <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
          <span id="vx-pub-chars" style="font-size:11px;color:var(--linea)">0 / 1500</span>
          <div class="d-flex gap-2">
            <button type="button" class="btn-vx btn-ghost-vx btn-vx-sm"
                    onclick="document.getElementById('panel-nueva-pub').style.display='none';document.getElementById('btn-nueva-pub').style.display=''">
              Cancelar
            </button>
            <button type="button" class="btn-vx btn-primary-vx btn-vx-sm" id="vx-pub-submit"
                    onclick="vxPublicar('<?php echo esc_js( $ep_crear ); ?>','<?php echo esc_js( $nonce ); ?>')">
              <i class="ti ti-send me-1"></i>Publicar
            </button>
          </div>
        </div>
        <div id="vx-pub-msg" class="mt-2 small" style="display:none"></div>
      </div>
      <?php endif; ?>

      <!-- ── Barra de búsqueda y filtros ──────────────────────────────── -->
      <form method="get" action="" class="mb-3">
        <?php if ( $tipo_fil ) : ?><input type="hidden" name="tipo" value="<?php echo esc_attr( $tipo_fil ); ?>"><?php endif; ?>
        <div class="search-bar-vx mb-2">
          <i class="ti ti-search" style="color:var(--color-text-secondary);font-size:16px"></i>
          <input type="text" name="q" value="<?php echo esc_attr( $q ); ?>" placeholder="Buscar oportunidades…">
          <button type="submit" class="btn-vx btn-primary-vx btn-vx-sm">
            <i class="ti ti-search"></i> Buscar
          </button>
        </div>
        <div class="d-flex gap-2 flex-wrap align-items-center">
          <a href="<?php echo esc_url( add_query_arg( array_filter( [ 'q' => $q ] ), $base_url ) ); ?>"
             class="btn-vx <?php echo ! $tipo_fil ? 'btn-soft-secondary' : 'btn-ghost-vx'; ?> btn-vx-sm">
            Todos
          </a>
          <a href="<?php echo esc_url( add_query_arg( array_filter( [ 'tipo' => 'ofrece', 'q' => $q ] ), $base_url ) ); ?>"
             class="btn-vx <?php echo 'ofrece' === $tipo_fil ? 'btn-soft-secondary' : 'btn-ghost-vx'; ?> btn-vx-sm">
            <i class="ti ti-arrow-up-circle"></i> Ofrece
          </a>
          <a href="<?php echo esc_url( add_query_arg( array_filter( [ 'tipo' => 'busca', 'q' => $q ] ), $base_url ) ); ?>"
             class="btn-vx <?php echo 'busca' === $tipo_fil ? 'btn-soft-secondary' : 'btn-ghost-vx'; ?> btn-vx-sm">
            <i class="ti ti-arrow-down-circle"></i> Busca
          </a>
          <?php if ( $q || $tipo_fil ) : ?>
          <a href="<?php echo esc_url( $base_url ); ?>" class="btn-vx btn-ghost-vx btn-vx-sm ms-auto">
            <i class="ti ti-x"></i> Limpiar
          </a>
          <?php endif; ?>
        </div>
      </form>

      <!-- ── Publicaciones ─────────────────────────────────────────────── -->
      <?php if ( $loop->have_posts() ) : ?>
        <?php while ( $loop->have_posts() ) : $loop->the_post(); ?>
          <?php echo vx_render_pub_card( get_post(), $viewer_id ); ?>
        <?php endwhile; wp_reset_postdata(); ?>
      <?php else : ?>
      <div class="empty-state-vx py-5 text-center">
        <div class="empty-state-vx__icon"><i class="ti ti-layout-board"></i></div>
        <p class="empty-state-vx__title">
          <?php echo $q ? 'Sin resultados para "' . esc_html( $q ) . '"' : 'Aún no hay oportunidades publicadas'; ?>
        </p>
        <p class="empty-state-vx__desc">
          <?php echo $q ? 'Prueba con otras palabras.' : 'Sé el primero en publicar lo que ofreces o buscas.'; ?>
        </p>
        <?php if ( $q ) : ?>
        <a href="<?php echo esc_url( $base_url ); ?>" class="btn-vx btn-ghost-vx btn-vx-sm mt-3">Ver todas las oportunidades</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- ── Paginación ──────────────────────────────────────────────────── -->
      <?php if ( $pages > 1 ) : ?>
      <nav class="d-flex justify-content-center gap-2 mt-4">
        <?php for ( $i = 1; $i <= $pages; $i++ ) : ?>
        <a href="<?php echo esc_url( add_query_arg( array_filter( [ 'pagina' => $i, 'q' => $q, 'tipo' => $tipo_fil ] ), $base_url ) ); ?>"
           class="btn-vx <?php echo $i === $pagina ? 'btn-primary-vx' : 'btn-ghost-vx'; ?> btn-vx-sm">
          <?php echo $i; ?>
        </a>
        <?php endfor; ?>
      </nav>
      <?php endif; ?>

    </div><!-- /.col -->
    </div><!-- /.row -->
    </div><!-- /.container -->
    </main>

    <?php if ( $viewer_id ) : ?>
    <?php echo vx_modal_conectar_html(); ?>
    <?php endif; ?>

    <script>
    (function () {
      var selectedTipo = 'ofrece';

      // ── Selector de tipo ─────────────────────────────────────────────────────
      window.vxSelTipo = function (tipo, btn) {
        selectedTipo = tipo;
        document.querySelectorAll('.vx-tipo-sel').forEach(function (b) {
          var active = b.dataset.tipo === tipo;
          b.classList.toggle('btn-primary-vx', active);
          b.classList.toggle('btn-ghost-vx', !active);
          b.classList.toggle('vx-tipo-sel--active', active);
        });
      };

      // ── Contador de caracteres ───────────────────────────────────────────────
      var ta = document.getElementById('vx-nueva-pub-texto');
      if (ta) {
        ta.addEventListener('input', function () {
          var el = document.getElementById('vx-pub-chars');
          if (el) el.textContent = this.value.length + ' / 1500';
        });
      }

      // ── Publicar ─────────────────────────────────────────────────────────────
      window.vxPublicar = function (ep, nonce) {
        var texto = (document.getElementById('vx-nueva-pub-texto').value || '').trim();
        var msg   = document.getElementById('vx-pub-msg');
        var btn   = document.getElementById('vx-pub-submit');
        msg.style.display = 'none';

        if (!texto) {
          msg.textContent = 'Escribe algo antes de publicar.';
          msg.className = 'mt-2 small text-danger';
          msg.style.display = '';
          return;
        }

        btn.disabled = true;
        btn.innerHTML = '<i class="ti ti-loader-2 me-1"></i>Publicando…';

        fetch(ep, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
          body: JSON.stringify({ tipo: selectedTipo, contenido: texto }),
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (d.success) { location.reload(); return; }
          msg.textContent = 'Error al publicar. Inténtalo de nuevo.';
          msg.className = 'mt-2 small text-danger';
          msg.style.display = '';
          btn.disabled = false;
          btn.innerHTML = '<i class="ti ti-send me-1"></i>Publicar';
        })
        .catch(function () {
          msg.textContent = 'Error de red.';
          msg.className = 'mt-2 small text-danger';
          msg.style.display = '';
          btn.disabled = false;
          btn.innerHTML = '<i class="ti ti-send me-1"></i>Publicar';
        });
      };

      // ── Eliminar publicación ─────────────────────────────────────────────────
      window.vxEliminarPub = function (id, ep, nonce) {
        if (!confirm('¿Eliminar esta publicación?')) return;
        fetch(ep, { method: 'DELETE', headers: { 'X-WP-Nonce': nonce } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (d.success) {
            var card = document.getElementById('pub-' + id);
            if (card) { card.style.opacity = '0'; card.style.transition = 'opacity .2s'; setTimeout(function(){ card.remove(); }, 200); }
          }
        });
      };

      // ── Toggle comentarios ───────────────────────────────────────────────────
      window.vxToggleComments = function (id) {
        var sec = document.getElementById('pub-' + id + '-comments');
        if (!sec) return;
        var isHidden = sec.style.display === 'none' || sec.style.display === '';
        sec.style.display = isHidden ? '' : 'none';
        if (isHidden) {
          var input = document.getElementById('pub-' + id + '-input');
          if (input) setTimeout(function () { input.focus(); }, 50);
        }
      };

      // ── Cargar todos los comentarios ─────────────────────────────────────────
      window.vxLoadAllComments = function (id) {
        var loadBtn = document.querySelector('#pub-' + id + '-list .btn-vx');
        if (loadBtn) { loadBtn.textContent = 'Cargando…'; loadBtn.disabled = true; }

        fetch('<?php echo esc_js( rest_url( VX_REST_NAMESPACE . '/feed/' ) ); ?>' + id + '/comentarios', {
          headers: { 'X-WP-Nonce': '<?php echo esc_js( $nonce ); ?>' },
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (!d.comments) return;
          var list = document.getElementById('pub-' + id + '-list');
          if (!list) return;
          list.innerHTML = '';
          d.comments.forEach(function (c) { list.innerHTML += vxCommentHtml(c); });
        });
      };

      // ── Enviar comentario ────────────────────────────────────────────────────
      window.vxEnviarComentario = function (id, ep, nonce) {
        var input = document.getElementById('pub-' + id + '-input');
        if (!input) return;
        var texto = input.value.trim();
        if (!texto) return;
        input.disabled = true;

        fetch(ep, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
          body: JSON.stringify({ texto: texto }),
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (d.success) {
            var list = document.getElementById('pub-' + id + '-list');
            if (list) list.insertAdjacentHTML('beforeend', vxCommentHtml(d));
            var counter = document.getElementById('pub-' + id + '-count');
            if (counter) counter.textContent = parseInt(counter.textContent || '0') + 1;
            input.value = '';
          }
          input.disabled = false;
          input.focus();
        })
        .catch(function () { input.disabled = false; });
      };

      // ── Helpers ──────────────────────────────────────────────────────────────
      function vxCommentHtml(c) {
        var url  = c.perfil_url ? e(c.perfil_url) : '';
        var foto = c.foto
          ? (url ? '<a href="' + url + '" class="flex-shrink-0"><img src="' + e(c.foto) + '" alt="" class="pub-comment-avatar"></a>'
                 : '<img src="' + e(c.foto) + '" alt="" class="pub-comment-avatar">')
          : '';
        var autor = url
          ? '<a href="' + url + '" class="pub-comment-author">' + e(c.autor) + '</a>'
          : '<span class="pub-comment-author">' + e(c.autor) + '</span>';
        return '<div class="d-flex gap-2 mb-3">' + foto +
          '<div class="pub-comment-bubble flex-grow-1">' +
          '<div class="d-flex align-items-center gap-1 mb-1">' +
          autor + '<span class="pub-comment-date">' + e(c.fecha) + '</span>' +
          '</div><p class="pub-comment-text">' +
          e(c.texto).replace(/\n/g,'<br>') + '</p></div></div>';
      }

      function e(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
      }

    })();
    </script>
    <?php
    return ob_get_clean();
} );
