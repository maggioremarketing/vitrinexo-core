<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// [vx_onboarding] — wizard de 5 pasos (estructura exacta del mockup onboarding.html)
add_shortcode( 'vx_onboarding', function (): string {
    $user_id = get_current_user_id();
    if ( ! $user_id ) return '';
    $state       = VX_Onboarding::get_state( $user_id );
    // Clamp al máximo de 6 (pasos reales del wizard)
    $paso_actual = min( 6, (int) $state['paso_actual'] );

    $user        = VX_User::get( $user_id );
    $nombre      = $user ? $user->get_nombre() : '';
    $apellido    = $user ? $user->get_apellido() : '';
    $bio         = $user ? $user->get_bio() : '';
    $ciudad      = $user ? $user->get_ciudad() : '';
    $pais        = $user ? $user->get_pais() : '';
    $contacto    = get_user_meta( $user_id, VX_User_Meta::CONTACTO_PREFERIDO, true ) ?: 'email';
    $telefono    = get_user_meta( $user_id, VX_User_Meta::TELEFONO, true );
    $linkedin    = get_user_meta( $user_id, VX_User_Meta::LINKEDIN, true );

    $foto_id     = (int) get_user_meta( $user_id, VX_User_Meta::FOTO, true );
    $foto_url    = $foto_id ? wp_get_attachment_image_url( $foto_id, 'vx-avatar' ) : '';
    $genero      = $user ? $user->get_genero() : '';
    $fecha_nacimiento = get_user_meta( $user_id, 'vx_fecha_nacimiento', true );
    $anos_experiencia = get_user_meta( $user_id, 'vx_anos_experiencia', true );
    $consentimiento   = get_user_meta( $user_id, 'vx_consentimiento_contacto', true );

    // Pre-rellenar paso 3: empresa activa → campo vx_empresa_inicial del registro
    $empresa_activa       = $user ? $user->get_empresa_activa() : null;
    $ob3_empresa_nombre   = '';
    $ob3_empresa_cargo    = '';
    $ob3_empresa_web      = '';
    $ob3_empresa_linkedin = '';
    $ob3_empresa_desc     = '';
    $ob3_empresa_industria= '';
    $ob3_logo_url         = '';
    $ob3_logo_id          = 0;
    if ( $empresa_activa ) {
        $ob3_empresa_nombre    = $empresa_activa->post_title;
        $ob3_empresa_cargo     = (string) get_post_meta( $empresa_activa->ID, 'vx_cargo',     true );
        if ( '' === $ob3_empresa_cargo ) {
            // El registro guarda el cargo como user meta; usarlo si la empresa aún no lo tiene.
            $ob3_empresa_cargo = (string) get_user_meta( $user_id, VX_User_Meta::CARGO, true );
        }
        $ob3_empresa_web       = (string) get_post_meta( $empresa_activa->ID, 'vx_web',       true );
        $ob3_empresa_linkedin  = (string) get_post_meta( $empresa_activa->ID, 'vx_linkedin',  true );
        $ob3_empresa_desc      = (string) get_post_meta( $empresa_activa->ID, 'vx_descripcion', true );
        $ob3_empresa_industria = (string) get_post_meta( $empresa_activa->ID, 'vx_industria', true );
        $ob3_logo_id           = (int)    get_post_meta( $empresa_activa->ID, 'vx_logo',      true );
        $ob3_logo_url          = $ob3_logo_id ? wp_get_attachment_image_url( $ob3_logo_id, 'vx-logo' ) : '';
    } elseif ( $empresa_inicial = get_user_meta( $user_id, 'vx_empresa_inicial', true ) ) {
        // Fallback: texto guardado al momento del registro
        $ob3_empresa_nombre = (string) $empresa_inicial;
        $ob3_empresa_cargo  = (string) get_user_meta( $user_id, VX_User_Meta::CARGO, true );
    }

    $industrias  = vx_get_industrias();
    $tags_preset = vx_get_tags_preset();
    $offer_tags  = $user ? $user->get_offer_tags() : [];
    $seek_tags   = $user ? $user->get_seek_tags()  : [];
    $offer_texto = (string) get_user_meta( $user_id, VX_User_Meta::OFFER_TEXTO, true );
    $seek_texto  = (string) get_user_meta( $user_id, VX_User_Meta::SEEK_TEXTO,  true );
    $api_url     = rest_url( VX_REST_NAMESPACE . '/' );
    $nonce       = wp_create_nonce( 'wp_rest' );

    ob_start();
    ?>
<!-- NAV CON PROGRESO — 5 pasos -->
<nav class="ob-nav">
  <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="d-flex align-items-center gap-2 text-decoration-none">
    <img src="<?php echo esc_url( function_exists( 'vx_logo_url' ) ? vx_logo_url() : get_theme_file_uri( 'assets/img/vitrinexo.svg' ) ); ?>" width="90" alt="Vitrinexo">
  </a>

  <div class="ob-steps d-none d-md-flex" id="ob-steps-indicator">
    <?php for ( $i = 1; $i <= 6; $i++ ) : ?>
    <div class="ob-step <?php echo $paso_actual > $i ? 'ob-step--done' : ( $paso_actual === $i ? 'ob-step--active' : '' ); ?>" id="dot-<?php echo $i; ?>" onclick="obStepClick(<?php echo $i; ?>)">
      <div class="ob-step-dot">
        <?php echo $paso_actual > $i ? '<i class="ti ti-check" style="font-size:11px"></i>' : $i; ?>
      </div>
    </div>
    <?php if ( $i < 6 ) : ?>
    <div class="ob-step-line <?php echo $paso_actual > $i ? 'ob-step-line--done' : ''; ?>" id="line-<?php echo $i; ?>"></div>
    <?php endif; ?>
    <?php endfor; ?>
  </div>

  <div class="ob-progress d-md-none">
    <div class="ob-progress-track">
      <div class="ob-progress-fill" id="ob-progress-fill" style="width:<?php echo round( ( $paso_actual - 1 ) / 5 * 100 ); ?>%"></div>
    </div>
    <div class="ob-progress-label" id="ob-progress-label">Paso <?php echo $paso_actual; ?> de 6</div>
  </div>

  <a href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>" class="dashboard-date text-decoration-none">Completar después</a>
</nav>

<!-- PANELES -->
<div class="flex-grow-1 py-4 px-3">

  <!-- ── PASO 1: Bienvenida ── -->
  <div class="ob-panel <?php echo 1 === $paso_actual ? 'ob-panel--active' : ''; ?>" id="panel-1">
    <div class="ob-card text-center">
      <div class="ob-step-eyebrow">Paso 1 de 6</div>
      <h1 class="ob-title">Hola<?php echo $nombre ? ', ' . esc_html( $nombre ) . '.' : '.'; ?><br>Construyamos tu vitrina.</h1>
      <p class="ob-lead">Te vamos a hacer unas preguntas para que tu perfil quede completo y la red pueda encontrarte. Son 6 pasos cortos, menos de 5 minutos.</p>
      <div class="ob-welcome-list">
        <div class="ob-welcome-item"><div class="ob-welcome-icon ob-welcome-icon--green"><i class="ti ti-user"></i></div>Datos básicos de tu perfil</div>
        <div class="ob-welcome-item"><div class="ob-welcome-icon ob-welcome-icon--cyan"><i class="ti ti-building"></i></div>Tu empresa y tu rol</div>
        <div class="ob-welcome-item"><div class="ob-welcome-icon ob-welcome-icon--pink"><i class="ti ti-arrows-exchange"></i></div>Qué ofreces y qué buscas: la clave de los nexos</div>
        <div class="ob-welcome-item"><div class="ob-welcome-icon ob-welcome-icon--purple"><i class="ti ti-users"></i></div>Comunidades a las que quieres pertenecer</div>
      </div>
      <div class="ob-footer ob-footer--center">
        <button class="btn-vx btn-primary-vx btn-vx-lg" onclick="obGoTo(2)">Empezar <i class="ti ti-arrow-right ms-1"></i></button>
      </div>
    </div>
  </div>

  <!-- ── PASO 2: Datos personales ── -->
  <div class="ob-panel <?php echo 2 === $paso_actual ? 'ob-panel--active' : ''; ?>" id="panel-2">
    <div class="ob-card">
      <div class="ob-step-eyebrow">Paso 2 de 6</div>
      <h2 class="ob-title">Cuéntanos sobre ti</h2>
      <p class="ob-lead">Esta información aparece en tu ficha pública.</p>

      <div class="ob-upload-row">
        <div class="logo-upload-zone" id="foto-zone" onclick="document.getElementById('foto-input').click()" style="cursor:pointer">
          <?php if ( $foto_url ) : ?>
          <img src="<?php echo esc_url( $foto_url ); ?>" alt="Tu foto" id="foto-preview" style="width:100%;height:100%;object-fit:cover;border-radius:inherit">
          <?php else : ?>
          <i class="ti ti-user ob-logo-placeholder" id="foto-icon"></i>
          <img alt="Tu foto" id="foto-preview" style="display:none;width:100%;height:100%;object-fit:cover;border-radius:inherit">
          <?php endif; ?>
          <div class="logo-upload-overlay"><i class="ti ti-camera"></i></div>
        </div>
        <input type="file" id="foto-input" accept="image/*" style="display:none">
        <input type="hidden" id="foto-id" value="<?php echo esc_attr( $foto_id ?: '' ); ?>">
        <div>
          <p class="ob-upload-meta-title">Tu foto de perfil</p>
          <p class="ob-upload-meta-hint">Recomendado: foto profesional, fondo neutro</p>
          <button class="btn-vx btn-ghost-vx btn-vx-sm" type="button" onclick="document.getElementById('foto-input').click()">
            <i class="ti ti-upload me-1"></i>Subir foto
          </button>
          <p style="font-size:11px;color:var(--color-text-secondary);margin:4px 0 0"><i class="ti ti-info-circle" style="font-size:10px"></i> Máx. 15 MB · JPG, PNG o WebP</p>
          <div class="vx-upload-progress d-none" id="ob-foto-progress" style="margin-top:6px;min-width:160px">
            <div style="height:3px;background:var(--color-border);border-radius:2px;overflow:hidden">
              <div class="vx-progress-fill" style="height:100%;width:0%;background:var(--color-primary);transition:width 0.15s ease;border-radius:2px"></div>
            </div>
            <span class="vx-progress-label" style="font-size:11px;color:var(--color-text-secondary);margin-top:2px;display:block">Subiendo...</span>
          </div>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label-vx">Bio profesional <span class="form-hint d-inline">(opcional · máx. 1500 caracteres)</span></label>
        <textarea id="ob2-bio" class="form-control-vx" rows="5" maxlength="1500" placeholder="Ej: Especialista en marketing B2B con 8 años de experiencia en mercados internacionales..."><?php echo esc_textarea( $bio ); ?></textarea>
      </div>

      <?php
      // La ciudad se pide aquí; el país ya viene del registro y define las opciones.
      $ciudades_pais  = vx_get_ciudades_por_pais()[ $pais ] ?? [];
      $ciudad_es_otra = $ciudad && ! in_array( $ciudad, $ciudades_pais, true );
      ?>
      <div class="mb-3">
        <label class="form-label-vx">Ciudad<?php echo $pais ? ' <span class="form-hint d-inline">(' . esc_html( $pais ) . ')</span>' : ''; ?></label>
        <select id="ob2-ciudad" class="form-control-vx" onchange="var c=document.getElementById('ob2-ciudad-custom'); if(this.value==='__otra__'){c.style.display='';c.focus();}else{c.style.display='none';c.value='';}">
          <option value="">Selecciona tu ciudad</option>
          <?php foreach ( $ciudades_pais as $c ) : ?>
          <option value="<?php echo esc_attr( $c ); ?>" <?php selected( $ciudad, $c ); ?>><?php echo esc_html( $c ); ?></option>
          <?php endforeach; ?>
          <option value="__otra__" <?php echo $ciudad_es_otra ? 'selected' : ''; ?>>Otra ciudad...</option>
        </select>
        <input type="text" id="ob2-ciudad-custom" class="form-control-vx mt-2"
               placeholder="Escribe tu ciudad"
               value="<?php echo $ciudad_es_otra ? esc_attr( $ciudad ) : ''; ?>"
               style="<?php echo $ciudad_es_otra ? '' : 'display:none'; ?>">
      </div>

      <div class="mb-3">
        <label class="form-label-vx">Género <span class="form-hint d-inline">(opcional)</span></label>
        <div class="d-flex flex-wrap gap-2" id="ob2-genero-group">
          <?php
          $genero_opts = [
              'masculino'   => 'Masculino',
              'femenino'    => 'Femenino',
              'otro'        => 'Otro',
              'no_contesta' => 'Prefiero no contestar',
          ];
          foreach ( $genero_opts as $val => $lbl ) :
          ?>
          <label class="d-flex align-items-center gap-2 p-2 border rounded-2" style="cursor:pointer;font-size:14px">
            <input type="radio" name="ob2-genero" value="<?php echo esc_attr( $val ); ?>" <?php checked( $genero, $val ); ?> style="accent-color:var(--color-primary-ink)">
            <?php echo esc_html( $lbl ); ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label class="form-label-vx">Fecha de nacimiento</label>
          <input type="date" id="ob2-fecha-nacimiento" class="form-control-vx" value="<?php echo esc_attr( $fecha_nacimiento ); ?>" max="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label-vx">Años de experiencia en el mercado</label>
          <input type="number" id="ob2-experiencia" class="form-control-vx" value="<?php echo esc_attr( $anos_experiencia ); ?>" min="0" max="80" step="1" placeholder="Ej: 12" required>
        </div>
      </div>

      <div id="ob2-error" class="alert-vx alert-error d-none mb-3"></div>
      <div class="ob-footer">
        <button class="btn-vx btn-ghost-vx btn-vx-md" onclick="obGoTo(1)"><i class="ti ti-arrow-left me-1"></i> Atrás</button>
        <button class="btn-vx btn-primary-vx btn-vx-md" onclick="obSave2()">Continuar <i class="ti ti-arrow-right ms-1"></i></button>
      </div>
    </div>
  </div>

  <!-- ── PASO 3: Empresa ── -->
  <div class="ob-panel <?php echo 3 === $paso_actual ? 'ob-panel--active' : ''; ?>" id="panel-3">
    <div class="ob-card">
      <div class="ob-step-eyebrow">Paso 3 de 6</div>
      <h2 class="ob-title">Tu empresa</h2>
      <p class="ob-lead">La empresa con la que apareces en el directorio. Puedes agregar más desde tu perfil.</p>

      <div class="ob-upload-row">
        <div class="logo-upload-zone" id="logo-zone" onclick="document.getElementById('logo-input').click()" style="cursor:pointer">
          <i class="ti ti-building ob-logo-placeholder" id="logo-empresa-icon"<?php echo $ob3_logo_url ? ' style="display:none"' : ''; ?>></i>
          <?php /* Sin logo va SIN atributo src, no con src vacío: un src=""
                   hace que el navegador vuelva a pedir la página entera como
                   si fuera la imagen. El hueco de la foto, unas líneas más
                   arriba, ya se escribía así. */ ?>
          <img <?php echo $ob3_logo_url ? 'src="' . esc_url( $ob3_logo_url ) . '" ' : ''; ?>alt="Logo empresa" id="logo-empresa-preview"
               style="<?php echo $ob3_logo_url ? 'width:100%;height:100%;object-fit:contain;border-radius:inherit' : 'display:none;width:100%;height:100%;object-fit:contain;border-radius:inherit'; ?>">
          <div class="logo-upload-overlay"><i class="ti ti-camera"></i></div>
        </div>
        <input type="file" id="logo-input" accept="image/*" style="display:none">
        <input type="hidden" id="logo-empresa-id" value="<?php echo esc_attr( $ob3_logo_id ?: '' ); ?>">
        <div>
          <p class="ob-upload-meta-title">Logo de la empresa</p>
          <p class="ob-upload-meta-hint">Circular · Usa una imagen cuadrada centrada · Fondo blanco recomendado</p>
          <button class="btn-vx btn-ghost-vx btn-vx-sm" type="button" onclick="document.getElementById('logo-input').click()">
            <i class="ti ti-upload me-1"></i>Subir logo
          </button>
          <p style="font-size:11px;color:var(--color-text-secondary);margin:4px 0 0"><i class="ti ti-info-circle" style="font-size:10px"></i> Máx. 15 MB · JPG, PNG o WebP</p>
          <div class="vx-upload-progress d-none" id="ob-logo-progress" style="margin-top:6px;min-width:160px">
            <div style="height:3px;background:var(--color-border);border-radius:2px;overflow:hidden">
              <div class="vx-progress-fill" style="height:100%;width:0%;background:var(--color-primary);transition:width 0.15s ease;border-radius:2px"></div>
            </div>
            <span class="vx-progress-label" style="font-size:11px;color:var(--color-text-secondary);margin-top:2px;display:block">Subiendo...</span>
          </div>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label-vx">Nombre de la empresa <span class="form-hint d-inline">(desde tu registro)</span></label>
        <input type="text" id="ob3-empresa-nombre" class="form-control-vx" readonly
               style="background:var(--color-surface-2, var(--hueso));cursor:not-allowed"
               value="<?php echo esc_attr( $ob3_empresa_nombre ); ?>">
      </div>
      <div class="mb-3">
        <label class="form-label-vx">Tu cargo / rol <span class="form-hint d-inline">(desde tu registro)</span></label>
        <input type="text" id="ob3-empresa-cargo" class="form-control-vx" readonly
               style="background:var(--color-surface-2, var(--hueso));cursor:not-allowed"
               value="<?php echo esc_attr( $ob3_empresa_cargo ); ?>">
      </div>
      <div class="mb-3">
        <label class="form-label-vx">Sitio web <span class="form-hint d-inline">(opcional)</span></label>
        <div class="input-group-vx">
          <span class="input-icon input-prefix">https://</span>
          <input type="text" id="ob3-empresa-web" placeholder="tuempresa.com"
                 value="<?php echo esc_attr( preg_replace( '#^https?://#', '', $ob3_empresa_web ) ); ?>">
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label-vx">LinkedIn empresa <span class="form-hint d-inline">(opcional)</span></label>
        <div class="input-group-vx">
          <span class="input-icon"><i class="ti ti-brand-linkedin"></i></span>
          <input type="text" id="ob3-empresa-linkedin" class="vx-linkedin-input" placeholder="linkedin.com/company/tuempresa"
                 value="<?php echo esc_attr( preg_replace( '#^https?://#', '', $ob3_empresa_linkedin ) ); ?>">
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label-vx">Descripción breve <span class="form-hint d-inline">(opcional)</span></label>
        <textarea id="ob3-empresa-desc" class="form-control-vx" rows="2" placeholder="En qué se especializa la empresa, a quién atiende..."><?php echo esc_textarea( $ob3_empresa_desc ); ?></textarea>
      </div>

      <div class="mb-3">
        <label class="form-label-vx">Industria <span class="form-hint d-inline">(opcional)</span></label>
        <select id="ob3-empresa-industria" class="form-control-vx">
          <option value="">Selecciona una industria...</option>
          <?php foreach ( $industrias as $ind ) : ?>
          <option value="<?php echo esc_attr( $ind ); ?>" <?php selected( $ob3_empresa_industria, $ind ); ?>><?php echo esc_html( $ind ); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div id="ob3-error" class="alert-vx alert-error d-none mb-3"></div>
      <div class="ob-footer">
        <button class="btn-vx btn-ghost-vx btn-vx-md" onclick="obGoTo(2)"><i class="ti ti-arrow-left me-1"></i> Atrás</button>
        <button class="btn-vx btn-primary-vx btn-vx-md" onclick="obSave3()">Continuar <i class="ti ti-arrow-right ms-1"></i></button>
      </div>
    </div>
  </div>

  <!-- ── PASO 4: Tags — Qué ofreces / qué buscas ── -->
  <div class="ob-panel <?php echo 4 === $paso_actual ? 'ob-panel--active' : ''; ?>" id="panel-4">
    <div class="ob-card">
      <div class="ob-step-eyebrow">Paso 4 de 6</div>
      <h2 class="ob-title">¿Qué ofreces y qué buscas?</h2>
      <p class="ob-lead">Estas etiquetas son el motor del sistema de nexos. Selecciona las que aplican o escribe las tuyas. Puedes editarlas cuando quieras.</p>

      <?php
      $tags_preset_ob = vx_get_tags_preset();
      ?>

      <!-- Qué ofreces -->
      <div class="mb-4">
        <div class="d-flex align-items-center gap-2 mb-2">
          <span class="badge-vx" style="background:var(--salvia-suave);color:var(--color-text-primary);font-size:11px;font-weight:700">OFRECES</span>
          <span style="font-size:12px;color:var(--color-text-secondary)">Elige los que apliquen.¿En qué puedes ayudar a otros?</span>
        </div>
        <div class="d-flex flex-wrap gap-2 mb-2" id="tags-offer">
          <?php foreach ( $tags_preset_ob as $tag ) :
            $selected = in_array( $tag, $offer_tags, true );
          ?>
          <span class="tag-option <?php echo $selected ? 'tag-option--selected-offer' : ''; ?>"
                data-type="offer"
                onclick="obToggleTag(this,'offer')"><?php echo esc_html( $tag ); ?></span>
          <?php endforeach; ?>
          <?php foreach ( array_diff( $offer_tags, $tags_preset_ob ) as $custom ) : ?>
          <span class="tag-option tag-option--selected-offer" data-type="offer" onclick="obToggleTag(this,'offer')"><?php echo esc_html( $custom ); ?></span>
          <?php endforeach; ?>
        </div>
        <input type="text" class="form-control-vx" placeholder="Escribe otra etiqueta y presiona Enter"
               onkeydown="obAddCustomTag(event,'offer')" style="font-size:14px">
      </div>

      <!-- Qué buscas -->
      <div class="mb-4">
        <div class="d-flex align-items-center gap-2 mb-2">
          <span class="badge-vx" style="background:var(--rosa-suave);color:var(--color-text-primary);font-size:11px;font-weight:700">BUSCAS</span>
          <span style="font-size:12px;color:var(--color-text-secondary)">Elige los que apliquen.¿Qué necesitas de la red?</span>
        </div>
        <div class="d-flex flex-wrap gap-2 mb-2" id="tags-seek">
          <?php foreach ( $tags_preset_ob as $tag ) :
            $selected = in_array( $tag, $seek_tags, true );
          ?>
          <span class="tag-option <?php echo $selected ? 'tag-option--selected-seek' : ''; ?>"
                data-type="seek"
                onclick="obToggleTag(this,'seek')"><?php echo esc_html( $tag ); ?></span>
          <?php endforeach; ?>
          <?php foreach ( array_diff( $seek_tags, $tags_preset_ob ) as $custom ) : ?>
          <span class="tag-option tag-option--selected-seek" data-type="seek" onclick="obToggleTag(this,'seek')"><?php echo esc_html( $custom ); ?></span>
          <?php endforeach; ?>
        </div>
        <input type="text" class="form-control-vx" placeholder="Escribe otra etiqueta y presiona Enter"
               onkeydown="obAddCustomTag(event,'seek')" style="font-size:14px">
      </div>

      <div id="ob4-error" class="alert-vx alert-error d-none mb-3"></div>
      <div class="ob-footer">
        <button class="btn-vx btn-ghost-vx btn-vx-md" onclick="obGoTo(3)"><i class="ti ti-arrow-left me-1"></i> Atrás</button>
        <button class="btn-vx btn-primary-vx btn-vx-md" onclick="obSave4()">Continuar <i class="ti ti-arrow-right ms-1"></i></button>
      </div>
    </div>
  </div>

  <!-- ── PASO 5: Comunidades ── -->
  <div class="ob-panel <?php echo 5 === $paso_actual ? 'ob-panel--active' : ''; ?>" id="panel-5">
    <div class="ob-card">
      <div class="ob-step-eyebrow">Paso 5 de 6</div>
      <h2 class="ob-title">¿A qué comunidades perteneces?</h2>
      <p class="ob-lead">Opcionales. Puedes activarlas o desactivarlas en cualquier momento desde tu perfil.</p>

      <div class="community-toggle" id="com-out2b" onclick="obToggleCom(this)">
        <div class="community-toggle__icon community-toggle__icon--out2b"><i class="ti ti-rainbow ob-community-icon-i"></i></div>
        <div><div class="community-toggle__title">Vitrinexo LGBTQ+</div><div class="community-toggle__desc">Para profesionales LGBTQ+ del mundo empresarial</div></div>
        <i class="ti ti-circle community-toggle__check"></i>
      </div>
      <?php $es_femenino = ( 'femenino' === $genero ); ?>
      <div class="community-toggle <?php echo $es_femenino ? 'community-toggle--selected community-toggle--forced' : 'community-toggle--locked'; ?>"
           id="com-woman" onclick="obToggleWoman(this)"
           style="cursor:not-allowed<?php echo $es_femenino ? '' : ';opacity:.5'; ?>">
        <div class="community-toggle__icon community-toggle__icon--woman"><i class="ti ti-gender-female ob-community-icon-i"></i></div>
        <div>
          <div class="community-toggle__title">Vitrinexo Woman</div>
          <div class="community-toggle__desc">
            Mujeres en posiciones de liderazgo empresarial
            <?php if ( $es_femenino ) : ?>
            <br><span style="color:var(--color-text-secondary);font-size:12px"><i class="ti ti-lock" style="font-size:11px"></i> Incluida automáticamente por tu género</span>
            <?php else : ?>
            <br><span style="color:var(--color-text-secondary);font-size:12px"><i class="ti ti-lock" style="font-size:11px"></i> Disponible solo para mujeres</span>
            <?php endif; ?>
          </div>
        </div>
        <i class="ti <?php echo $es_femenino ? 'ti-circle-check' : 'ti-circle'; ?> community-toggle__check"></i>
      </div>
      <div class="community-toggle community-toggle--locked" id="com-senior" onclick="obToggleSenior(this)" style="cursor:not-allowed">
        <div class="community-toggle__icon community-toggle__icon--senior"><i class="ti ti-award ob-community-icon-i"></i></div>
        <div>
          <div class="community-toggle__title">Vitrinexo Senior</div>
          <div class="community-toggle__desc">
            Ejecutivos con trayectoria consolidada
            <br><span style="color:var(--color-text-secondary);font-size:12px"><i class="ti ti-lock" style="font-size:11px"></i> Se asigna automáticamente con 45+ años o 20+ de experiencia</span>
          </div>
        </div>
        <i class="ti ti-circle community-toggle__check"></i>
      </div>

      <div class="ob-footer">
        <button class="btn-vx btn-ghost-vx btn-vx-md" onclick="obGoTo(4)"><i class="ti ti-arrow-left me-1"></i> Atrás</button>
        <button class="btn-vx btn-primary-vx btn-vx-md" onclick="obSave5()">Finalizar <i class="ti ti-check ms-1"></i></button>
      </div>
    </div>
  </div>

  <!-- ── PASO 6: Listo ── -->
  <div class="ob-panel <?php echo 6 === $paso_actual ? 'ob-panel--active' : ''; ?>" id="panel-6">
    <div class="ob-card text-center">
      <div class="ob-success-icon mx-auto mb-3"><i class="ti ti-check ob-success-check"></i></div>
      <div class="ob-step-eyebrow">¡Todo listo!</div>
      <h2 class="ob-title">Ya estás en la red.<br><strong class="text-primary-color">Encuentra tus próximos nexos.</strong></h2>
      <p class="ob-lead">Tu perfil aparece en el directorio y tus etiquetas ya están activas para los nexos.</p>
      <div class="ob-success-actions">
        <a href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>" class="btn-vx btn-primary-vx btn-vx-lg">
          Ir al dashboard <i class="ti ti-arrow-right ms-1"></i>
        </a>
        <a href="<?php echo esc_url( home_url( '/editar-perfil/' ) ); ?>" class="btn-vx btn-ghost-vx btn-vx-md">
          Completar mi perfil
        </a>
      </div>
    </div>
  </div>

</div><!-- /.flex-grow-1 -->

<script>
(function(){
  var API   = <?php echo wp_json_encode( $api_url ); ?>;
  var NONCE = <?php echo wp_json_encode( $nonce ); ?>;
  var TOTAL = 6;
  var current = <?php echo $paso_actual; ?>;
  var maxReached = current; // paso más avanzado alcanzado (para navegar con los indicadores)

  window.obGoTo = function(step) {
    var prev = document.getElementById('panel-' + current);
    if (prev) prev.classList.remove('ob-panel--active');
    current = step;
    if (current > maxReached) maxReached = current;
    var next = document.getElementById('panel-' + current);
    if (next) next.classList.add('ob-panel--active');
    obUpdateIndicators();
    // Al llegar al paso de comunidades, refrescar el estado de Woman según el género elegido.
    if (step === 5) {
      if (typeof window.vxSyncWoman === 'function') window.vxSyncWoman();
      if (typeof window.vxSyncSenior === 'function') window.vxSyncSenior();
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  // Navegación con los indicadores de paso: solo se puede ir a un paso ya alcanzado.
  window.obStepClick = function(i) {
    if (i !== current && i <= maxReached) obGoTo(i);
  };

  function obUpdateIndicators() {
    var pct = Math.round(((current - 1) / (TOTAL - 1)) * 100); // TOTAL-1 = 5 pasos entre 6 pantallas
    var fill = document.getElementById('ob-progress-fill');
    var lbl  = document.getElementById('ob-progress-label');
    if (fill) fill.style.width = pct + '%';
    if (lbl)  lbl.textContent = 'Paso ' + current + ' de ' + TOTAL;
    for (var i = 1; i <= TOTAL; i++) {
      var dot = document.getElementById('dot-' + i);
      if (!dot) continue;
      dot.classList.remove('ob-step--done', 'ob-step--active');
      if (i < current) dot.classList.add('ob-step--done');
      else if (i === current) dot.classList.add('ob-step--active');
      var dotEl = dot.querySelector('.ob-step-dot');
      if (dotEl) dotEl.innerHTML = i < current ? '<i class="ti ti-check" style="font-size:11px"></i>' : i;
      dot.style.cursor = (i <= maxReached && i !== current) ? 'pointer' : 'default';
      var line = document.getElementById('line-' + i);
      if (line) line.classList.toggle('ob-step-line--done', i < current);
    }
  }

  // Tags (kept for potential future use within onboarding)
  window.obToggleTag = function(el, type) {
    var cls = type === 'offer' ? 'tag-option--selected-offer' : 'tag-option--selected-seek';
    if (el.classList.contains(cls)) { el.classList.remove(cls); return; }
    // Sin límite de tags
    el.classList.add(cls);
  };

  window.obAddCustomTag = function(e, type) {
    if (e.key !== 'Enter' && e.key !== ',') return;
    e.preventDefault();
    var val = e.target.value.trim();
    if (!val) return;
    var container = document.getElementById('tags-' + type);
    var cls = type === 'offer' ? 'tag-option--selected-offer' : 'tag-option--selected-seek';
    if (!container) return; // sin límite de tags
    // Check not duplicate
    var existing = Array.from(container.querySelectorAll('.tag-option')).map(function(t){ return t.textContent.toLowerCase(); });
    if (existing.includes(val.toLowerCase())) { e.target.value = ''; return; }
    var span = document.createElement('span');
    span.className = 'tag-option ' + cls;
    span.dataset.type = type;
    span.setAttribute('onclick', "obToggleTag(this,'" + type + "')");
    span.textContent = val;
    container.appendChild(span);
    e.target.value = '';
  };

  function obGetSelectedTags(type) {
    var cls = type === 'offer' ? 'tag-option--selected-offer' : 'tag-option--selected-seek';
    var container = document.getElementById('tags-' + type);
    if (!container) return [];
    return Array.from(container.querySelectorAll('.' + cls)).map(function(el){ return el.textContent.trim(); });
  }

  // ── Comunidades ───────────────────────────────────────────────────────────────
  window.obToggleCom = function(el) {
    var on = el.classList.toggle('community-toggle--selected');
    var icon = el.querySelector('.community-toggle__check');
    if (icon) { icon.classList.toggle('ti-circle-check', on); icon.classList.toggle('ti-circle', !on); }
  };

  // Woman: obligatoria para género femenino, bloqueada para el resto.
  window._vxGenero = <?php echo wp_json_encode( $genero ); ?>;

  // Sincroniza el estado visual del toggle Woman con el género actual.
  // Para femenino: seleccionada y bloqueada (no se puede desmarcar).
  // Para el resto: deshabilitada (no se puede marcar).
  window.vxSyncWoman = function(){
    var womanEl = document.getElementById('com-woman');
    if (!womanEl) return;
    var icon = womanEl.querySelector('.community-toggle__check');
    if (window._vxGenero === 'femenino') {
      womanEl.classList.add('community-toggle--selected', 'community-toggle--forced');
      womanEl.classList.remove('community-toggle--locked');
      womanEl.style.opacity = '';
      womanEl.style.cursor  = 'not-allowed';
      if (icon) { icon.classList.add('ti-circle-check'); icon.classList.remove('ti-circle'); }
    } else {
      womanEl.classList.remove('community-toggle--selected', 'community-toggle--forced');
      womanEl.classList.add('community-toggle--locked');
      womanEl.style.opacity = '.5';
      womanEl.style.cursor  = 'not-allowed';
      if (icon) { icon.classList.add('ti-circle'); icon.classList.remove('ti-circle-check'); }
    }
  };
  vxSyncWoman();

  window.obToggleWoman = function(el) {
    var genero = window._vxGenero || (function(){
      var g = document.querySelector('#ob2-genero-group input[name="ob2-genero"]:checked');
      return g ? g.value : '';
    })();
    if (genero !== 'femenino') {
      alert('Vitrinexo Woman es exclusiva para mujeres. Indica tu género en el paso anterior para habilitarla.');
      return;
    }
    // Obligatoria para femenino: siempre queda seleccionada, no se puede desmarcar.
    vxSyncWoman();
  };

  // Senior: automática por edad (45+) o experiencia (20+). Bloqueada, no seleccionable a mano.
  window.vxSeniorCalifica = function(){
    var fnac = (document.getElementById('ob2-fecha-nacimiento') || {}).value || '';
    var exp  = parseInt( ( (document.getElementById('ob2-experiencia') || {}).value || '0' ), 10 ) || 0;
    var edad = 0;
    if ( /^\d{4}-\d{2}-\d{2}$/.test( fnac ) ) {
      var d = new Date( fnac ), n = new Date();
      edad = n.getFullYear() - d.getFullYear();
      var m = n.getMonth() - d.getMonth();
      if ( m < 0 || ( m === 0 && n.getDate() < d.getDate() ) ) edad--;
    }
    return edad >= 45 || exp >= 20;
  };
  window.vxSyncSenior = function(){
    var el = document.getElementById('com-senior');
    if ( !el ) return;
    var icon = el.querySelector('.community-toggle__check');
    if ( window.vxSeniorCalifica() ) {
      el.classList.add('community-toggle--selected');
      if ( icon ) { icon.classList.add('ti-circle-check'); icon.classList.remove('ti-circle'); }
    } else {
      el.classList.remove('community-toggle--selected');
      if ( icon ) { icon.classList.add('ti-circle'); icon.classList.remove('ti-circle-check'); }
    }
  };
  window.obToggleSenior = function(){
    // No es seleccionable a mano: se define por edad/experiencia.
    vxSyncSenior();
  };
  vxSyncSenior();

  // ── Upload foto ───────────────────────────────────────────────────────────────
  document.getElementById('foto-input').addEventListener('change', function() {
    var file = this.files[0];
    if (!file) return;
    // Preview optimista
    var reader = new FileReader();
    reader.onload = function(e) {
      var prev = document.getElementById('foto-preview');
      var icon = document.getElementById('foto-icon');
      if (prev) { prev.src = e.target.result; prev.style.display = ''; }
      if (icon) icon.style.display = 'none';
    };
    reader.readAsDataURL(file);
    // Upload con progreso
    var progressEl = document.getElementById('ob-foto-progress');
    window.vxUploadXHR(file, 'foto', null, progressEl,
      function(json) {
        var hid = document.getElementById('foto-id');
        if (hid && json.attachment_id) hid.value = json.attachment_id;
      },
      function(msg) { obMostrarError(msg); }
    );
  });

  document.getElementById('logo-input').addEventListener('change', function() {
    var file = this.files[0];
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function(e) {
      var prev = document.getElementById('logo-empresa-preview');
      var icon = document.getElementById('logo-empresa-icon');
      if (prev) { prev.src = e.target.result; prev.style.display = ''; }
      if (icon) icon.style.display = 'none';
    };
    reader.readAsDataURL(file);
    var progressEl = document.getElementById('ob-logo-progress');
    window.vxUploadXHR(file, 'logo', null, progressEl,
      function(json) {
        var hid = document.getElementById('logo-empresa-id');
        if (hid && json.attachment_id) hid.value = json.attachment_id;
      },
      function(msg) { obMostrarError(msg); }
    );
  });

  function obMostrarError(msg) {
    // Mostrar error genérico si los elementos de error de paso no están disponibles
    var errEl = document.getElementById('ob2-error') || document.getElementById('ob3-error');
    if (errEl) { errEl.textContent = msg; errEl.classList.remove('d-none'); }
    else { alert(msg); }
  }

  // ── API save ──────────────────────────────────────────────────────────────────
  function obSave(paso, datos, onSuccess) {
    fetch(API + 'onboarding/paso', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
      body: JSON.stringify({ paso: paso, datos: datos, partial: false }),
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (d.success) { onSuccess(); }
      else {
        var errEl = document.getElementById('ob' + paso + '-error');
        var msg = obErrorMsg(d.errors || []);
        if (errEl) { errEl.textContent = msg; errEl.classList.remove('d-none'); }
        else alert(msg);
      }
    })
    .catch(function(){ alert('Error de red. Intenta de nuevo.'); });
  }

  function obErrorMsg(errors) {
    var map = {
      nombre_requerido:          'El nombre es obligatorio.',
      apellido_requerido:        'El apellido es obligatorio.',
      pais_requerido:            'El país es obligatorio.',
      fecha_nacimiento_requerida:'La fecha de nacimiento es obligatoria.',
      anos_experiencia_requerido:'Los años de experiencia son obligatorios.',
      consentimiento_requerido:  'Debes autorizar que otros miembros te contacten para continuar.',
      empresa_nombre_requerido:  'El nombre de la empresa es obligatorio.',
      empresa_cargo_requerido:   'Tu cargo en la empresa es obligatorio.',
      paso_invalido:             'Paso no válido. Recarga la página.',
    };
    return errors.map(function(e){ return map[e] || e; }).join(' ') || 'Error al guardar.';
  }

  // ── Guardado por paso ─────────────────────────────────────────────────────────
  window.obSave2 = function() {
    var errEl = document.getElementById('ob2-error');
    if (errEl) errEl.classList.add('d-none');
    var generoEl = document.querySelector('#ob2-genero-group input[name="ob2-genero"]:checked');
    var generoVal = generoEl ? generoEl.value : '';
    window._vxGenero = generoVal; // persist across steps
    var fechaNac    = document.getElementById('ob2-fecha-nacimiento').value;
    var experiencia = document.getElementById('ob2-experiencia').value.trim();
    // Fecha de nacimiento y años de experiencia son obligatorios
    if (!fechaNac || experiencia === '') {
      if (errEl) { errEl.textContent = 'Indica tu fecha de nacimiento y tus años de experiencia en el mercado.'; errEl.classList.remove('d-none'); }
      return;
    }
    obSave(2, {
      // nombre, apellido, país, teléfono y LinkedIn ya vienen del registro: no se re-piden.
      bio:                document.getElementById('ob2-bio').value.trim(),
      ciudad:             (function(){ var s=document.getElementById('ob2-ciudad'); var c=document.getElementById('ob2-ciudad-custom'); return s && s.value==='__otra__' ? (c ? c.value.trim() : '') : (s ? s.value.trim() : ''); })(),
      foto_id:            document.getElementById('foto-id').value,
      genero:             generoVal,
      fecha_nacimiento:   fechaNac,
      anos_experiencia:   experiencia,
    }, function(){ obGoTo(3); });
  };

  // ── Cascade país → ciudad (onboarding) ───────────────────────────────────────
  (function() {
    var paisSel   = document.getElementById('ob2-pais');
    var ciudadSel = document.getElementById('ob2-ciudad');
    if ( !paisSel || !ciudadSel ) return;

    var customInput = document.getElementById('ob2-ciudad-custom');

    function vxToggleCiudadCustom() {
      var isOtra = ciudadSel.value === '__otra__';
      if ( customInput ) {
        customInput.style.display = isOtra ? '' : 'none';
        if ( isOtra ) customInput.focus();
        else customInput.value = '';
      }
    }

    function vxPopulateCiudades( pais, selected ) {
      var ciudades = (window.vxCiudadesPorPais || {})[ pais ] || [];
      ciudadSel.innerHTML = '';
      if ( !ciudades.length ) {
        ciudadSel.innerHTML = '<option value="">Sin opciones para este país</option>';
        if ( customInput ) customInput.style.display = 'none';
        return;
      }
      var placeholder = document.createElement('option');
      placeholder.value = ''; placeholder.textContent = 'Selecciona tu ciudad';
      ciudadSel.appendChild( placeholder );
      ciudades.forEach(function(c) {
        var opt = document.createElement('option');
        opt.value = c; opt.textContent = c;
        if ( c === selected ) opt.selected = true;
        ciudadSel.appendChild( opt );
      });
      // Añadir opción "Otra ciudad..."
      var otra = document.createElement('option');
      otra.value = '__otra__'; otra.textContent = 'Otra ciudad...';
      ciudadSel.appendChild( otra );
      vxToggleCiudadCustom();
    }

    // Inicializar con el valor actual del usuario (si ya tiene país)
    vxPopulateCiudades( paisSel.value, ciudadSel.dataset.selected || '' );

    paisSel.addEventListener('change', function() {
      vxPopulateCiudades( this.value, '' );
    });

    ciudadSel.addEventListener('change', vxToggleCiudadCustom);
  })();

  window.obSave3 = function() {
    var errEl = document.getElementById('ob3-error');
    if (errEl) errEl.classList.add('d-none');
    var web = document.getElementById('ob3-empresa-web').value.trim();
    if (web && !web.startsWith('http')) web = 'https://' + web;
    var lin = document.getElementById('ob3-empresa-linkedin').value.trim();
    if (lin && !lin.startsWith('http')) lin = 'https://' + lin;
    // Validar LinkedIn antes de enviar
    if (lin && !vxIsLinkedinUrl(lin)) {
      var linEl = document.getElementById('ob3-empresa-linkedin');
      linEl.style.borderColor = 'var(--rosa)';
      linEl.focus();
      var errEl = document.getElementById('ob3-error');
      if (errEl) { errEl.textContent = 'El enlace de LinkedIn debe ser de linkedin.com (ej: linkedin.com/company/tuempresa)'; errEl.classList.remove('d-none'); }
      return;
    }
    obSave(3, {
      empresa_nombre:     document.getElementById('ob3-empresa-nombre').value.trim(),
      empresa_cargo:      document.getElementById('ob3-empresa-cargo').value.trim(),
      empresa_web:        web,
      empresa_linkedin:   lin,
      empresa_desc:       document.getElementById('ob3-empresa-desc').value.trim(),
      empresa_logo_id:    document.getElementById('logo-empresa-id').value,
      empresa_industria:  document.getElementById('ob3-empresa-industria').value,
    }, function(){ obGoTo(4); });
  };

  // Paso 4 = tags (offer / seek)
  window.obSave4 = function() {
    obSave(4, {
      offer_tags: obGetSelectedTags('offer'),
      seek_tags:  obGetSelectedTags('seek'),
    }, function(){ obGoTo(5); });
  };

  // Paso 5 = comunidades → llama a completar y va al paso 6
  window.obSave5 = function() {
    obSave(5, {
      out2b:  document.getElementById('com-out2b').classList.contains('community-toggle--selected')  ? '1' : '',
      woman:  document.getElementById('com-woman').classList.contains('community-toggle--selected')  ? '1' : '',
      senior: document.getElementById('com-senior').classList.contains('community-toggle--selected') ? '1' : '',
    }, function(){
      fetch(API + 'onboarding/completar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
      }).then(function(){ obGoTo(6); });
    });
  };

  obUpdateIndicators();
})();
</script>
    <?php
    return ob_get_clean();
} );

// ─── HELPERS ──────────────────────────────────────────────────────────────────

/**
 * Lista de industrias para el directorio Vitrinexo.
 * Usada en onboarding, editor-perfil y filtros del directorio.
 *
 * @return string[]
 */
function vx_get_industrias(): array {
    return [
        'Agro & Alimentación',
        'Arquitectura & Diseño Interior',
        'Comercio & Retail',
        'Consultoría Empresarial',
        'Data & Analytics',
        'Deportes & Entretenimiento',
        'Diseño & Branding',
        'E-commerce',
        'Educación & Formación',
        'Energía & Medio Ambiente',
        'Finanzas & Inversión',
        'Industria & Manufactura',
        'Inmobiliario & Construcción',
        'Legal & Regulatorio',
        'Logística & Supply Chain',
        'Marketing & Publicidad',
        'Medios & Comunicación',
        'Recursos Humanos',
        'Salud & Bienestar',
        'Software & Tecnología',
        'Turismo & Hospitalidad',
        'Otro',
    ];
}

/**
 * Tags sugeridos para offer/seek/profile — pre-set compartido entre onboarding y editor.
 *
 * @return string[]
 */
function vx_get_tags_preset(): array {
    $default = [
        'Marketing digital','Diseño y branding','Desarrollo web','Consultoría',
        'Ventas B2B','CRM','Automatización','Legal','Finanzas','RRHH',
        'Data e inteligencia','Logística','Producción audiovisual','Real estate',
        'Alianzas tecnológicas','Alianzas comerciales','E-commerce','SaaS',
        'Transformación digital','Startups','Venture capital','Exportaciones',
    ];
    $saved = get_option( 'vx_tags_preset', null );
    return is_array( $saved ) && ! empty( $saved ) ? $saved : $default;
}

function vx_get_paises_latam(): array {
    return [
        'Argentina', 'Bolivia', 'Chile', 'Colombia', 'Costa Rica',
        'Cuba', 'Ecuador', 'El Salvador', 'España', 'Guatemala',
        'Honduras', 'México', 'Nicaragua', 'Panamá', 'Paraguay',
        'Perú', 'Puerto Rico', 'República Dominicana', 'Uruguay', 'Venezuela',
    ];
}

/**
 * Lista curada de ciudades principales por país.
 * Solo ciudades (no comunas, barrios ni municipios).
 * Criterio: hub de negocios, no subdivisiones internas.
 *
 * @return array<string, string[]>  país → [ciudad, ...]
 */
function vx_get_ciudades_por_pais(): array {
    return [
        'Argentina'           => [ 'Buenos Aires','Córdoba','Rosario','Mendoza','Tucumán','La Plata','Mar del Plata','Salta','Santa Fe','San Juan','Neuquén','Bahía Blanca','San Salvador de Jujuy','Resistencia','Posadas' ],
        'Bolivia'             => [ 'La Paz','Santa Cruz de la Sierra','Cochabamba','Sucre','Oruro','Potosí','Tarija','Trinidad','Cobija' ],
        'Chile'               => [ 'Santiago','Valparaíso','Concepción','La Serena','Antofagasta','Temuco','Iquique','Puerto Montt','Arica','Rancagua','Talca','Chillán','Punta Arenas','Osorno' ],
        'Colombia'            => [ 'Bogotá','Medellín','Cali','Barranquilla','Cartagena','Bucaramanga','Pereira','Santa Marta','Manizales','Cúcuta','Ibagué','Villavicencio','Pasto','Armenia' ],
        'Costa Rica'          => [ 'San José','Alajuela','Heredia','Cartago','Liberia','Puntarenas','Limón' ],
        'Cuba'                => [ 'La Habana','Santiago de Cuba','Camagüey','Holguín','Matanzas','Santa Clara','Cienfuegos' ],
        'Ecuador'             => [ 'Quito','Guayaquil','Cuenca','Manta','Santo Domingo','Ambato','Riobamba','Loja','Esmeraldas','Machala' ],
        'El Salvador'         => [ 'San Salvador','Santa Ana','San Miguel','Soyapango','Nueva San Salvador' ],
        'España'              => [ 'Madrid','Barcelona','Valencia','Sevilla','Bilbao','Zaragoza','Málaga','Alicante','Granada','Murcia','Palma','Las Palmas','Valladolid','San Sebastián','Santander' ],
        'Guatemala'           => [ 'Ciudad de Guatemala','Quetzaltenango','Escuintla','Cobán','Mazatenango','Retalhuleu' ],
        'Honduras'            => [ 'Tegucigalpa','San Pedro Sula','La Ceiba','Choloma','El Progreso' ],
        'México'              => [ 'Ciudad de México','Guadalajara','Monterrey','Puebla','Querétaro','Tijuana','León','Mérida','San Luis Potosí','Cancún','Hermosillo','Chihuahua','Aguascalientes','Culiacán','Morelia','Toluca','Ciudad Juárez','Veracruz','Saltillo' ],
        'Nicaragua'           => [ 'Managua','León','Masaya','Granada','Matagalpa','Chinandega' ],
        'Panamá'              => [ 'Ciudad de Panamá','San Miguelito','Colón','David','Arraiján','La Chorrera' ],
        'Paraguay'            => [ 'Asunción','Ciudad del Este','Luque','San Lorenzo','Lambaré','Fernando de la Mora','Capiatá' ],
        'Perú'                => [ 'Lima','Arequipa','Trujillo','Chiclayo','Iquitos','Piura','Cusco','Tacna','Chimbote','Huancayo','Pucallpa' ],
        'Puerto Rico'         => [ 'San Juan','Bayamón','Carolina','Ponce','Caguas','Guaynabo' ],
        'República Dominicana'=> [ 'Santo Domingo','Santiago de los Caballeros','La Romana','San Pedro de Macorís','La Vega','San Francisco de Macorís' ],
        'Uruguay'             => [ 'Montevideo','Salto','Paysandú','Maldonado','Canelones','Rivera','Tacuarembó' ],
        'Venezuela'           => [ 'Caracas','Maracaibo','Valencia','Barquisimeto','Maracay','Maturín','Ciudad Guayana','Barcelona','Mérida' ],
    ];
}

/**
 * Intenta normalizar un nombre de ciudad libre al valor canónico.
 * Útil para migrar datos existentes.
 *
 * @param string $ciudad_libre  Valor ingresado por el usuario.
 * @param string $pais          País del usuario.
 * @return string               Valor canónico si lo encuentra, o el original.
 */
function vx_normalizar_ciudad( string $ciudad_libre, string $pais ): string {
    $ciudades = vx_get_ciudades_por_pais()[ $pais ] ?? [];
    if ( empty( $ciudades ) || empty( $ciudad_libre ) ) return $ciudad_libre;

    $libre_norm = mb_strtolower( trim( $ciudad_libre ), 'UTF-8' );

    // 1. Match exacto (case-insensitive)
    foreach ( $ciudades as $c ) {
        if ( mb_strtolower( $c, 'UTF-8' ) === $libre_norm ) return $c;
    }

    // 2. La ciudad canónica está contenida en el valor libre (ej: "Santiago de Chile" → "Santiago")
    foreach ( $ciudades as $c ) {
        if ( str_contains( $libre_norm, mb_strtolower( $c, 'UTF-8' ) ) ) return $c;
    }

    // 3. El valor libre está contenido en la ciudad canónica (ej: "Stgo" → no match, pero "Buenos" → "Buenos Aires")
    foreach ( $ciudades as $c ) {
        if ( mb_strlen( $libre_norm, 'UTF-8' ) >= 4 && str_contains( mb_strtolower( $c, 'UTF-8' ), $libre_norm ) ) return $c;
    }

    return $ciudad_libre; // sin match · devolver original
}

/**
 * Renderiza el <datalist> de países para usar con <input list="vx-paises-list">.
 * Incluido una sola vez por página (evita IDs duplicados con un flag estático).
 */
function vx_paises_datalist(): string {
    static $rendered = false;
    if ( $rendered ) return '';
    $rendered = true;
    $html = '<datalist id="vx-paises-list">';
    foreach ( vx_get_paises_latam() as $p ) {
        $html .= '<option value="' . esc_attr( $p ) . '">';
    }
    $html .= '</datalist>';
    return $html;
}

/**
 * Renderiza una member card con la estructura exacta del mockup.
 *
 * @param array      $m                to_card_array() del usuario
 * @param int        $viewer_id        ID del usuario que navega (0 = anónimo)
 * @param array|null $only_offer_tags  null = todos los offer_tags; array = solo esos; [] = ninguno
 * @param array|null $only_seek_tags   null = todos los seek_tags; array = solo esos; [] = ninguno
 */
function vx_render_member_card( array $m, int $viewer_id = 0, ?array $only_offer_tags = null, ?array $only_seek_tags = null ): string {
    $uid     = (int) ( $m['id'] ?? 0 );
    $slug    = $m['slug'] ?? '';
    $nombre  = $m['nombre'] ?? '';
    $foto    = $m['foto_url'] ?? '';
    $empresa = $m['empresa'] ?? '';
    $ciudad  = $m['ciudad'] ?? '';
    $pais    = $m['pais_codigo'] ?? $m['pais'] ?? '';

    $perfil_url = home_url( '/perfil/' . $slug . '/' );

    // Badges
    $badges = '';
    if ( ! empty( $m['is_founder'] ) ) {
        $badges .= ' <span class="founder-tooltip" data-tooltip="Miembro Pionero" tabindex="0"><i class="founder-tag ti ti-star"></i></span>';
    }
    if ( ! empty( $m['comunidades'] ) && in_array( 'senior', (array) $m['comunidades'], true ) ) {
        $badges .= ' <span class="badge-vx badge-neutral" style="font-size:10px">Vitrinexo Senior</span>';
    }

    // Favorito
    $is_fav    = false;
    $fav_style = '';
    if ( $viewer_id && $viewer_id !== $uid ) {
        $vo = VX_User::get( $viewer_id );
        if ( $vo && in_array( $uid, (array) $vo->get_favoritos(), true ) ) {
            $is_fav    = true;
            $fav_style = ' style="color:var(--color-text-primary)"';
        }
    }
    $fav_icon = $is_fav ? 'ti-heart-filled' : 'ti-heart';

    // Tags — si se pasa un array específico se usa ese; null = usar todos del miembro
    $show_offers = $only_offer_tags !== null ? $only_offer_tags : (array) ( $m['offer_tags'] ?? [] );
    $show_seeks  = $only_seek_tags  !== null ? $only_seek_tags  : (array) ( $m['seek_tags']  ?? [] );

    $tags_html = '';
    $has_offer = ! empty( $show_offers );
    $has_seek  = ! empty( $show_seeks );
    if ( $has_offer || $has_seek ) {
        $tags_html .= '<div class="d-flex flex-wrap gap-1 mb-0 p-0">';
        if ( $has_offer ) $tags_html .= '<p class="p-offers">Ofrece</p>';
        if ( $has_seek )  $tags_html .= '<p class="p-seeks">Busca</p>';
        $tags_html .= '</div><div class="d-flex flex-wrap gap-1">';
        foreach ( $show_offers as $t ) $tags_html .= '<span class="tag-vx tag-offers">' . esc_html( $t ) . '</span>';
        foreach ( $show_seeks  as $t ) $tags_html .= '<span class="tag-vx tag-seeks">' . esc_html( $t ) . '</span>';
        $tags_html .= '</div>';
    }

    $loc = trim( ( $ciudad ? $ciudad : '' ) . ( $pais ? ' (' . $pais . ')' : '' ) );

    ob_start(); ?>
<div class="card">
  <div class="card-img-container">
    <div class="card-enlaces">
      <a href="<?php echo esc_url( $perfil_url ); ?>" class="btn-vx btn-ghost-vx btn-vx-sm btn-vx-icon-sm" aria-label="Ver perfil"><i class="ti ti-external-link"></i></a>
      <?php if ( $viewer_id && $viewer_id !== $uid ) : ?>
      <button class="btn-vx btn-soft-accent btn-vx-sm btn-vx-icon-sm vx-fav-btn" data-user-id="<?php echo $uid; ?>" data-activo="<?php echo $is_fav ? '1' : '0'; ?>"<?php echo $fav_style; ?> aria-label="Favorito"><i class="ti <?php echo $fav_icon; ?>"></i></button>
      <button class="btn-vx btn-soft-primary btn-vx-sm" data-bs-toggle="modal" data-bs-target="#modalConectar" data-receptor-id="<?php echo $uid; ?>"><i class="ti ti-send"></i> Conectar</button>
      <?php endif; ?>
    </div>
    <div class="card-blur-gradient"></div>
    <a href="<?php echo esc_url( $perfil_url ); ?>" tabindex="-1" aria-label="Ver perfil de <?php echo esc_attr( $nombre ); ?>">
      <img class="card-img-top" src="<?php echo esc_url( $foto ); ?>" alt="<?php echo esc_attr( $nombre ); ?>">
    </a>
  </div>
  <div class="card-body">
    <div class="info mb-2">
      <h5 class="h6 py-0 my-0">
        <a href="<?php echo esc_url( $perfil_url ); ?>" style="color:inherit;text-decoration:none" class="vx-card-name-link"><?php echo esc_html( $nombre ); ?></a><?php echo $badges; ?>
      </h5>
      <?php if ( $empresa ) : ?><p class="member-company"><?php echo esc_html( $empresa ); ?></p><?php endif; ?>
      <?php if ( $loc ) : ?><p class="member-company"><?php echo esc_html( $loc ); ?></p><?php endif; ?>
      <?php if ( empty( $m['onboarding_completo'] ) ) : ?><p class="mb-0 mt-1"><span class="badge-vx badge-neutral" style="font-size:10px"><i class="ti ti-progress me-1"></i>Perfil en progreso</span></p><?php endif; ?>
    </div>
    <?php echo $tags_html; ?>
  </div>
</div>
<?php
    return ob_get_clean();
}

/**
 * Renderiza el modal Bootstrap #modalConectar para enviar solicitudes.
 */
/**
 * Prefijos telefónicos de los países de Hispanoamérica + España.
 * Formato: ['codigo' => 'Nombre (código)']
 *
 * @return array
 */
function vx_get_prefijos_telefonicos(): array {
    return [
        '+54'  => 'Argentina (+54)',
        '+591' => 'Bolivia (+591)',
        '+56'  => 'Chile (+56)',
        '+57'  => 'Colombia (+57)',
        '+506' => 'Costa Rica (+506)',
        '+53'  => 'Cuba (+53)',
        '+593' => 'Ecuador (+593)',
        '+503' => 'El Salvador (+503)',
        '+34'  => 'España (+34)',
        '+502' => 'Guatemala (+502)',
        '+504' => 'Honduras (+504)',
        '+52'  => 'México (+52)',
        '+505' => 'Nicaragua (+505)',
        '+507' => 'Panamá (+507)',
        '+595' => 'Paraguay (+595)',
        '+51'  => 'Perú (+51)',
        '+1'   => 'Puerto Rico / RD (+1)',
        '+598' => 'Uruguay (+598)',
        '+58'  => 'Venezuela (+58)',
    ];
}

/**
 * Separa un número de teléfono almacenado en prefijo + número.
 * Ej: "+56 912345678" → ['+56', '912345678']
 *     "912345678"     → ['', '912345678']
 *
 * @param string $telefono
 * @return array  [prefijo, numero]
 */
function vx_parse_telefono( string $telefono ): array {
    $telefono = trim( $telefono );
    if ( '' === $telefono ) return [ '', '' ];

    // Si empieza con +, separar el prefijo del número
    if ( str_starts_with( $telefono, '+' ) ) {
        // Los prefijos de la lista tienen entre 2 y 4 dígitos tras el +
        foreach ( array_keys( vx_get_prefijos_telefonicos() ) as $prefijo ) {
            if ( str_starts_with( $telefono, $prefijo ) ) {
                $numero = trim( substr( $telefono, strlen( $prefijo ) ) );
                return [ $prefijo, $numero ];
            }
        }
    }

    return [ '', $telefono ];
}

/**
 * Renderiza el input de teléfono con selector de prefijo de país.
 *
 * @param string $id           ID del elemento HTML
 * @param string $name         Atributo name del input
 * @param string $value        Valor actual (ej: "+56 912345678")
 * @param string $placeholder  Placeholder del campo de número
 * @return string  HTML completo
 */
function vx_phone_input_html( string $id, string $name, string $value = '', string $placeholder = 'Ej: 9 1234 5678' ): string {
    [ $prefijo, $numero ] = vx_parse_telefono( $value );
    $select_id = $id . '-prefix';
    $number_id = $id . '-number';

    $options = '<option value="">Prefijo</option>';
    if ( function_exists( 'vx_get_dial_codes' ) ) {
        foreach ( vx_get_dial_codes() as $c ) {
            $sel      = selected( $prefijo, $c['dial'], false );
            $options .= '<option value="' . esc_attr( $c['dial'] ) . '" ' . $sel . ' title="' . esc_attr( $c['name'] . ' (' . $c['dial'] . ')' ) . '">' . esc_html( $c['flag'] . ' ' . $c['dial'] ) . '</option>';
        }
    } else {
        foreach ( vx_get_prefijos_telefonicos() as $code => $label ) {
            $sel      = selected( $prefijo, $code, false );
            $options .= '<option value="' . esc_attr( $code ) . '" ' . $sel . ' title="' . esc_attr( $label ) . '">' . esc_html( $code ) . '</option>';
        }
    }

    return '<div class="input-group-vx" id="' . esc_attr( $id ) . '-group">'
        . '<select id="' . esc_attr( $select_id ) . '" style="border:none;background:transparent;padding:9px 8px;min-width:104px;font-size:14px;color:var(--color-text-primary);cursor:pointer;flex-shrink:0;border-right:1px solid var(--color-border)" onchange="vxUpdatePhone(\'' . esc_js( $id ) . '\')">'
        . $options
        . '</select>'
        . '<input type="tel" id="' . esc_attr( $number_id ) . '" placeholder="' . esc_attr( $placeholder ) . '"'
        . ' value="' . esc_attr( $numero ) . '"'
        . ' style="flex:1" onchange="vxUpdatePhone(\'' . esc_js( $id ) . '\')">'
        . '<input type="hidden" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"'
        . ' value="' . esc_attr( $value ) . '">'
        . '</div>'
        . '<script>function vxUpdatePhone(id){'
        . 'var p=document.getElementById(id+"-prefix").value;'
        . 'var n=document.getElementById(id+"-number").value.trim();'
        . 'document.getElementById(id).value=(p&&n)?p+" "+n:n;'
        . '}</script>';
}

/**
 * Renderiza los links de contacto de una conexión aceptada.
 * Muestra TODOS los métodos disponibles; el preferido lleva el badge.
 *
 * @param VX_User $user
 * @param string  $contacto_preferido
 * @return string
 */
function vx_render_contact_links( VX_User $user, string $contacto_preferido = 'email' ): string {
    $html      = '<div class="contact-reveal-links">';
    $nombre_wa = rawurlencode( 'Hola ' . $user->get_nombre() . ' te escribo desde vitrinexo' );

    if ( $user->get_email() ) {
        $email = $user->get_email();
        $badge = 'email' === $contacto_preferido ? '<span class="contact-preferred-badge">Preferido</span>' : '';
        $html .= '<div class="contact-reveal-row">'
               . '<span class="contact-reveal-value"><i class="ti ti-mail"></i> ' . esc_html( $email ) . $badge . '</span>'
               . '<span class="contact-reveal-actions">'
               . '<button type="button" class="contact-action-btn" onclick="navigator.clipboard.writeText(' . esc_attr( wp_json_encode( $email ) ) . ');this.textContent=\'Copiado\';setTimeout(()=>this.textContent=\'Copiar\',1500)" title="Copiar correo">Copiar</button>'
               . '<a href="mailto:' . esc_attr( $email ) . '" class="contact-action-btn" title="Enviar email">Email</a>'
               . '</span>'
               . '</div>';
    }

    if ( $user->get_telefono() ) {
        $tel     = $user->get_telefono();
        $tel_wa  = preg_replace( '/[^\d+]/', '', $tel );
        $badge   = 'telefono' === $contacto_preferido ? '<span class="contact-preferred-badge">Preferido</span>' : '';
        $wa_url  = 'https://wa.me/' . ltrim( $tel_wa, '+' ) . '?text=' . $nombre_wa;
        $html   .= '<div class="contact-reveal-row">'
               . '<span class="contact-reveal-value"><i class="ti ti-phone"></i> ' . esc_html( $tel ) . $badge . '</span>'
               . '<span class="contact-reveal-actions">'
               . '<button type="button" class="contact-action-btn" onclick="navigator.clipboard.writeText(' . esc_attr( wp_json_encode( $tel ) ) . ');this.textContent=\'Copiado\';setTimeout(()=>this.textContent=\'Copiar\',1500)" title="Copiar número">Copiar</button>'
               . '<a href="' . esc_url( $wa_url ) . '" class="contact-action-btn contact-action-btn--wa" target="_blank" rel="noopener" title="Abrir WhatsApp"><i class="ti ti-brand-whatsapp"></i> WhatsApp</a>'
               . '</span>'
               . '</div>';
    }

    if ( $user->get_linkedin() ) {
        $badge = 'linkedin' === $contacto_preferido ? '<span class="contact-preferred-badge">Preferido</span>' : '';
        $html .= '<div class="contact-reveal-row">'
               . '<a href="' . esc_url( $user->get_linkedin() ) . '" class="contact-reveal-value" target="_blank" rel="noopener"><i class="ti ti-brand-linkedin"></i> LinkedIn' . $badge . '</a>'
               . '</div>';
    }

    $html .= '</div>';
    return $html;
}

function vx_modal_conectar_html(): string {
    $nonce    = wp_create_nonce( 'wp_rest' );
    $endpoint = rest_url( VX_REST_NAMESPACE . '/conexiones' );
    ob_start(); ?>
<div class="modal fade" id="modalConectar" tabindex="-1" aria-labelledby="modalConectarLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-vx">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-semibold" id="modalConectarLabel"><i class="ti ti-send me-2 ic-primary"></i>Solicitud de conexión</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <p class="cta-card__desc mb-3">Cuéntale brevemente por qué quieres conectar con esta persona (mín. 20 caracteres).</p>
        <textarea id="vx-modal-pitch" class="form-control-vx" rows="3" placeholder="Hola, me interesa conectar porque..." maxlength="500"></textarea>
        <div id="vx-modal-msg" class="mt-2 d-none small"></div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn-vx btn-ghost-vx btn-vx-sm" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn-vx btn-primary-vx btn-vx-sm" id="vx-modal-enviar"><i class="ti ti-send me-1"></i>Enviar solicitud</button>
      </div>
    </div>
  </div>
</div>
<script>
(function(){
  var ep    = <?php echo wp_json_encode( $endpoint ); ?>;
  var nonce = <?php echo wp_json_encode( $nonce ); ?>;
  var mel   = document.getElementById('modalConectar');
  if(!mel) return;
  mel.addEventListener('show.bs.modal',function(e){
    var t=e.relatedTarget;
    if(t){ mel.dataset.receptorId=t.dataset.receptorId||(t.closest('[data-receptor-id]')||{}).dataset?.receptorId||''; }
    document.getElementById('vx-modal-pitch').value='';
    var msg=document.getElementById('vx-modal-msg'); msg.textContent=''; msg.className='mt-2 d-none small';
    document.getElementById('vx-modal-enviar').disabled=false;
  });
  document.getElementById('vx-modal-enviar').addEventListener('click',function(){
    var pitch=document.getElementById('vx-modal-pitch').value.trim();
    var rid=parseInt(mel.dataset.receptorId||'0');
    var msgEl=document.getElementById('vx-modal-msg');
    msgEl.className='mt-2 small d-none';
    if(!pitch){msgEl.textContent='Escribe un mensaje para enviar la solicitud.';msgEl.className='mt-2 small text-danger';return;}
    this.disabled=true;
    fetch(ep,{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},body:JSON.stringify({receptor_id:rid,pitch:pitch})})
    .then(r=>r.json()).then(d=>{
      if(d.success){msgEl.textContent='¡Solicitud enviada!';msgEl.className='mt-2 small text-success';setTimeout(()=>{var m=bootstrap.Modal.getInstance(mel);if(m)m.hide();location.reload();},1200);}
      else{var e2=d.data?.message||d.error||'Error al enviar.';if(d.error==='conexion_pendiente')e2='Ya tienes una solicitud pendiente con esta persona.';msgEl.textContent=e2;msgEl.className='mt-2 small text-danger';this.disabled=false;}
    }).catch(()=>{msgEl.textContent='Error de red.';msgEl.className='mt-2 small text-danger';this.disabled=false;});
  });
})();
</script>
<?php
    return ob_get_clean();
}

// ─── SHORTCODES ───────────────────────────────────────────────────────────────

// [vx_dashboard] — panel principal del usuario
add_shortcode( 'vx_dashboard', function (): string {
    if ( ! is_user_logged_in() ) return '';
    $user_id = get_current_user_id();
    $user    = VX_User::get( $user_id );
    if ( ! $user ) return '';

    $seeks_raw      = VX_Matches::get_seeks_matches( $user_id, [ 'page' => 1, 'per_page' => 5 ] );
    $offers_raw     = VX_Matches::get_offers_matches( $user_id, [ 'page' => 1, 'per_page' => 5 ] );
    $seeks_matches  = array_map( fn( $u ) => $u->to_card_array(), $seeks_raw['users'] ?? [] );
    $offers_matches = array_map( fn( $u ) => $u->to_card_array(), $offers_raw['users'] ?? [] );
    $conexiones     = VX_Connection::get_accepted( $user_id );
    $pending_recv   = VX_Connection::get_received_by( $user_id, 'pendiente' );
    $favoritos_ids  = $user->get_favoritos();
    $offer_tags     = $user->get_offer_tags();
    $seek_tags      = $user->get_seek_tags();

    $dinners = class_exists( 'VX_Dinner' ) ? VX_Dinner::get_upcoming() : [];
    $dinner  = ! empty( $dinners ) ? $dinners[0] : null;

    $fecha_hoy = date_i18n( 'l, j \de F Y' );

    $nonce_rest = wp_create_nonce( 'wp_rest' );
    $ep_responder = rest_url( VX_REST_NAMESPACE . '/conexiones/responder' );

    ob_start();
    ?>
    <div class="page-header-vx">
      <div class="container">
        <div class="page-header-vx__inner">
          <div>
            <h1 class="page-header-vx__title">Bienvenido, <a href="<?php echo esc_url( home_url( '/perfil/' . $user->get_slug() . '/' ) ); ?>" style="color:inherit;text-decoration:none"><strong><?php echo esc_html( $user->get_nombre() ); ?></strong></a></h1>
            <p class="page-header-vx__lead">
              <?php
              $nm = count( $seeks_matches ) + count( $offers_matches );
              $np = count( $pending_recv );
              // «match» esta prohibido: es de las aplicaciones de citas.
              $lead = 'Tienes ' . $nm . ' nexo' . ( $nm !== 1 ? 's' : '' ) . ' nuevo' . ( $nm !== 1 ? 's' : '' );
              if ( $np > 0 ) $lead .= ' y ' . $np . ' solicitud' . ( $np !== 1 ? 'es' : '' ) . ' de conexión pendiente' . ( $np !== 1 ? 's' : '' );
              $lead .= '.';
              echo esc_html( $lead );
              ?>
            </p>
          </div>
          <div class="d-flex gap-2 align-items-center">
            <a href="<?php echo esc_url( home_url( '/editar-perfil/' ) ); ?>" class="btn-vx btn-soft-primary btn-vx-sm">
              <i class="ti ti-pencil"></i> Editar perfil
            </a>
          </div>
        </div>
      </div>
    </div>

    <main>
    <div class="container py-4">

      <!-- STATS RÁPIDAS -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
          <a href="<?php echo esc_url( home_url( '/conexiones/' ) ); ?>" class="text-decoration-none d-block h-100">
            <div class="stat-card-vx stat-card-vx--link h-100">
              <div class="stat-num-vx stat-num-vx--primary"><?php echo count( $conexiones ); ?></div>
              <div class="stat-label-vx">Conexiones activas <i class="ti ti-arrow-right stat-arrow"></i></div>
            </div>
          </a>
        </div>
        <div class="col-6 col-md-3">
          <a href="<?php echo esc_url( home_url( '/nexos/' ) ); ?>" class="text-decoration-none d-block h-100">
            <div class="stat-card-vx stat-card-vx--link h-100">
              <div class="stat-num-vx stat-num-vx--accent"><?php echo $nm; ?></div>
              <div class="stat-label-vx">Nexos nuevos <i class="ti ti-arrow-right stat-arrow"></i></div>
            </div>
          </a>
        </div>
        <div class="col-6 col-md-3">
          <a href="<?php echo esc_url( home_url( '/conexiones/' ) ); ?>" class="text-decoration-none d-block h-100">
            <div class="stat-card-vx stat-card-vx--link h-100">
              <div class="stat-num-vx stat-num-vx--secondary"><?php echo $np; ?></div>
              <div class="stat-label-vx">Solicitudes recibidas <i class="ti ti-arrow-right stat-arrow"></i></div>
            </div>
          </a>
        </div>
        <div class="col-6 col-md-3">
          <a href="<?php echo esc_url( home_url( '/favoritos/' ) ); ?>" class="text-decoration-none d-block h-100">
            <div class="stat-card-vx stat-card-vx--link h-100">
              <div class="stat-num-vx stat-num-vx--success"><?php echo count( $favoritos_ids ); ?></div>
              <div class="stat-label-vx">En favoritos <i class="ti ti-arrow-right stat-arrow"></i></div>
            </div>
          </a>
        </div>
      </div>

      <!-- BANNER 4DINNER -->
      <?php if ( $dinner ) : ?>
      <div class="banner-4dinner mb-5">
        <div class="banner-4dinner__deco banner-4dinner__deco--1"></div>
        <div class="banner-4dinner__deco banner-4dinner__deco--2"></div>
        <div class="banner-4dinner__body">
          <div class="banner-4dinner__icon text-white"><i class="ti ti-tools-kitchen-2" aria-hidden="true"></i></div>
          <div>
            <div class="banner-4dinner__eyebrow">Próximo evento</div>
            <div class="banner-4dinner__title">Habrá un <strong>Vitrinexo 4Dinner</strong> cerca tuyo</div>
            <div class="banner-4dinner__meta">
              <i class="ti ti-map-pin"></i><?php echo esc_html( $dinner->get_ciudad() ); ?> &nbsp;·&nbsp;
              <i class="ti ti-calendar"></i><?php echo esc_html( date_i18n( 'j \de F', strtotime( $dinner->get_fecha() ) ) ); ?>
            </div>
          </div>
        </div>
        <a href="<?php echo esc_url( home_url( '/4dinner/' ) ); ?>" class="btn-vx btn-vx-sm banner-4dinner__cta">
          Ver detalles <i class="ti ti-arrow-right ms-1"></i>
        </a>
      </div>
      <?php endif; ?>

      <!-- SOLICITUDES RECIBIDAS -->
      <?php if ( $pending_recv ) : ?>
      <div class="mb-5">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <div>
            <h2 class="section-title-sm">Solicitudes de conexión</h2>
            <p class="cta-card__desc">Personas que quieren conectar contigo</p>
          </div>
          <a href="<?php echo esc_url( home_url( '/conexiones/' ) ); ?>" class="btn-vx btn-ghost-vx btn-vx-sm link-primary-color">
            Ver todas <i class="ti ti-arrow-right ms-1"></i>
          </a>
        </div>
        <div class="d-flex flex-column gap-2">
          <?php foreach ( array_slice( $pending_recv, 0, 3 ) as $conn ) :
            $emisor = VX_User::get( $conn->get_emisor_id() );
            if ( ! $emisor ) continue;
            $emp_activa = $emisor->get_empresa_activa();
          ?>
          <div class="card-vx cta-card--seeks">
            <div class="conn-row">
              <img src="<?php echo esc_url( $emisor->get_foto_url( 'vx-avatar' ) ); ?>" alt="" class="conn-avatar">
              <div class="conn-info">
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                  <a href="<?php echo esc_url( home_url( '/perfil/' . $emisor->get_slug() . '/' ) ); ?>" class="conn-name"><?php echo esc_html( $emisor->get_nombre_completo() ); ?></a>
                  <span class="badge-vx badge-accent"><i class="ti ti-bell me-1"></i>Nueva</span>
                </div>
                <div class="conn-meta"><?php if ( $emp_activa ) echo esc_html( $emp_activa->post_title ) . ' · '; echo esc_html( $emisor->get_ciudad() ); if ( $emisor->get_pais_codigo() ) echo ' (' . esc_html( $emisor->get_pais_codigo() ) . ')'; ?></div>
                <?php if ( $conn->get_pitch() ) : ?>
                <div class="conn-pitch">"<?php echo esc_html( wp_trim_words( $conn->get_pitch(), 20 ) ); ?>"</div>
                <?php endif; ?>
              </div>
              <div class="d-flex gap-2 flex-shrink-0">
                <button class="btn-vx btn-success-vx btn-vx-sm vx-conn-btn" data-conn-id="<?php echo $conn->get_id(); ?>" data-accion="aceptado"><i class="ti ti-check me-1"></i>Aceptar</button>
                <button class="btn-vx btn-danger-vx btn-vx-sm vx-conn-btn" data-conn-id="<?php echo $conn->get_id(); ?>" data-accion="rechazado"><i class="ti ti-x me-1"></i>Rechazar</button>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- BUSCADOR — envía a /directorio/ con q, pais, industria, fundador -->
      <div class="card-vx mb-5">
        <div class="d-flex align-items-center gap-2 mb-3">
          <i class="ti ti-search ic-primary" style="font-size:18px"></i>
          <h2 class="section-title-sm" style="font-weight:500">Explorar directorio</h2>
        </div>
        <form method="get" action="<?php echo esc_url( home_url( '/directorio/' ) ); ?>">
          <div class="search-bar-vx mb-3">
            <i class="ti ti-search" style="color:var(--color-text-secondary);font-size:16px"></i>
            <input type="text" name="q" placeholder="Buscar nombre, empresa, industria, tags..." autocomplete="off">
            <button type="submit" class="btn-vx btn-primary-vx btn-vx-sm">
              <i class="ti ti-search"></i> Buscar
            </button>
          </div>
          <div class="d-flex gap-2 flex-wrap align-items-center">
            <!-- País -->
            <select name="pais" class="btn-vx btn-ghost-vx btn-vx-sm" style="border:1px solid var(--color-border);cursor:pointer">
              <option value=""><i class="ti ti-world"></i> País</option>
              <?php foreach ( vx_get_paises_latam() as $p ) : ?>
              <option value="<?php echo esc_attr( $p ); ?>"><?php echo esc_html( $p ); ?></option>
              <?php endforeach; ?>
            </select>
            <!-- Industria -->
            <select name="industria" class="btn-vx btn-ghost-vx btn-vx-sm" style="border:1px solid var(--color-border);cursor:pointer">
              <option value=""><i class="ti ti-briefcase"></i> Industria</option>
              <?php foreach ( vx_get_industrias() as $ind ) : ?>
              <option value="<?php echo esc_attr( $ind ); ?>"><?php echo esc_html( $ind ); ?></option>
              <?php endforeach; ?>
            </select>
            <!-- Fundadores -->
            <label class="btn-vx btn-ghost-vx btn-vx-sm" style="border:1px solid var(--color-border);cursor:pointer">
              <input type="checkbox" name="fundador" value="1" style="display:none">
              <i class="ti ti-star"></i> Miembros Pioneros
            </label>
            <a href="<?php echo esc_url( home_url( '/directorio/' ) ); ?>" class="btn-vx btn-ghost-vx btn-vx-sm ms-auto link-primary-color">
              Ver todo <i class="ti ti-arrow-right ms-1"></i>
            </a>
          </div>
        </form>
      </div>

      <!-- OFRECEN LO QUE BUSCAS -->
      <?php $seeks_members = $seeks_matches; if ( $seeks_members && $seek_tags ) : ?>
      <section class="mb-5">
        <div class="d-flex align-items-center justify-content-between mb-3 gap-2 flex-wrap">
          <h2 class="section-title-md">Ofrecen lo que buscas</h2>
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <div class="d-flex flex-wrap gap-1">
              <?php foreach ( array_slice( $seek_tags, 0, 3 ) as $tag ) : ?>
              <span class="tag-vx tag-seeks"><?php echo esc_html( $tag ); ?></span>
              <?php endforeach; ?>
            </div>
            <a href="<?php echo esc_url( home_url( '/nexos-que-busco/' ) ); ?>" class="btn-vx btn-ghost-vx btn-vx-sm">Ver todos <i class="ti ti-arrow-right ms-1"></i></a>
          </div>
        </div>
        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5 g-2">
          <?php foreach ( array_slice( $seeks_members, 0, 5 ) as $m ) :
            $matching_offers = array_values( array_intersect( (array) ( $m['offer_tags'] ?? [] ), $seek_tags ) );
          ?>
          <div class="col"><?php echo vx_render_member_card( $m, $user_id, $matching_offers, [] ); ?></div>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <!-- BUSCAN LO QUE OFRECES -->
      <?php $offers_members = $offers_matches; if ( $offers_members && $offer_tags ) : ?>
      <section class="mb-5">
        <div class="d-flex align-items-center justify-content-between mb-3 gap-2 flex-wrap">
          <h2 class="section-title-md">Buscan lo que ofreces</h2>
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <div class="d-flex flex-wrap gap-1">
              <?php foreach ( array_slice( $offer_tags, 0, 3 ) as $tag ) : ?>
              <span class="tag-vx tag-offers"><?php echo esc_html( $tag ); ?></span>
              <?php endforeach; ?>
            </div>
            <a href="<?php echo esc_url( home_url( '/nexos-que-ofrezco/' ) ); ?>" class="btn-vx btn-ghost-vx btn-vx-sm">Ver todos <i class="ti ti-arrow-right ms-1"></i></a>
          </div>
        </div>
        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5 g-2">
          <?php foreach ( array_slice( $offers_members, 0, 5 ) as $m ) :
            $matching_seeks = array_values( array_intersect( (array) ( $m['seek_tags'] ?? [] ), $offer_tags ) );
          ?>
          <div class="col"><?php echo vx_render_member_card( $m, $user_id, [], $matching_seeks ); ?></div>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <!-- CONEXIONES CONCRETADAS -->
      <?php if ( $conexiones ) : ?>
      <div class="mb-5">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <h2 class="section-title-sm">Conexiones concretadas</h2>
          <a href="<?php echo esc_url( home_url( '/conexiones/' ) ); ?>" class="btn-vx btn-ghost-vx btn-vx-sm link-primary-color">Ver todas <i class="ti ti-arrow-right ms-1"></i></a>
        </div>
        <div class="d-flex flex-column gap-2">
          <?php foreach ( array_slice( $conexiones, 0, 3 ) as $conn ) :
            $other_id   = $conn->get_other_user_id( $user_id );
            $other_user = VX_User::get( $other_id );
            if ( ! $other_user ) continue;
            $emp_activa = $other_user->get_empresa_activa();
            $role_str   = trim( implode( ' · ', array_filter( [
                $emp_activa ? $emp_activa->post_title : '',
                trim( ( $other_user->get_ciudad() ? $other_user->get_ciudad() : '' ) . ( $other_user->get_pais_codigo() ? ' (' . $other_user->get_pais_codigo() . ')' : '' ) ),
            ] ) ) );
            $contacto_pref = get_user_meta( $other_id, VX_User_Meta::CONTACTO_PREFERIDO, true ) ?: 'email';
          ?>
          <div class="contact-reveal">
            <img src="<?php echo esc_url( $other_user->get_foto_url( 'vx-avatar' ) ); ?>" alt="" class="contact-reveal-avatar">
            <div class="flex-grow-1">
              <a href="<?php echo esc_url( home_url( '/perfil/' . $other_user->get_slug() . '/' ) ); ?>" class="contact-reveal-name"><?php echo esc_html( $other_user->get_nombre_completo() ); ?></a>
              <?php if ( $role_str ) : ?><div class="contact-reveal-role"><?php echo esc_html( $role_str ); ?></div><?php endif; ?>
              <?php echo vx_render_contact_links( $other_user, $contacto_pref ); ?>
            </div>
            <a href="<?php echo esc_url( home_url( '/perfil/' . $other_user->get_slug() . '/' ) ); ?>" class="btn-vx btn-ghost-vx btn-vx-sm btn-vx-icon-sm flex-shrink-0"><i class="ti ti-external-link"></i></a>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- MIS COMUNIDADES -->
      <div class="mb-5">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <h2 class="section-title-sm">Mis comunidades</h2>
          <a href="<?php echo esc_url( home_url( '/configuracion/' ) ); ?>" class="btn-vx btn-ghost-vx btn-vx-sm"><i class="ti ti-settings"></i> Gestionar</a>
        </div>
        <div class="row g-2">
          <!-- Vitrinexo base — siempre visible -->
          <div class="col-12 col-sm-6 col-lg-3">
            <a href="<?php echo esc_url( home_url( '/directorio/' ) ); ?>" class="text-decoration-none d-block h-100">
              <div class="card-vx h-100 d-flex align-items-center gap-3 comunidad-card">
                <div class="comunidad-icon comunidad-icon--vx"><i class="ti ti-building"></i></div>
                <div><div class="comunidad-name">Vitrinexo</div><div class="comunidad-status">Directorio principal · Activa</div></div>
              </div>
            </a>
          </div>
          <?php
          // out2b es opt-in (se puede unir). woman/senior son automáticas
          // (intrínsecas al perfil): no llevan botón "Unirse", solo una nota.
          $coms_display = [
              'out2b'  => [ 'Vitrinexo LGBTQ+', 'Para profesionales LGBTQ+', 'comunidad-icon--out2b',  'ti-rainbow',       'comunidad-card--out2b',  '' ],
              'woman'  => [ 'Vitrinexo Woman',  'Empresarias líderes',   'comunidad-icon--woman',  'ti-gender-female', 'comunidad-card--woman',  'Automática · solo para mujeres' ],
              'senior' => [ 'Vitrinexo Senior', 'Trayectoria verificada', 'comunidad-icon--senior', 'ti-award',        'comunidad-card--senior', 'Automática · 45+ años o 20+ de experiencia' ],
          ];
          foreach ( $coms_display as $com_id => [ $com_name, $com_desc, $icon_cls, $icon, $card_cls, $auto_hint ] ) :
              $es_miembro    = $user->is_in_community( $com_id );
              $es_automatica = ( '' !== $auto_hint );
              // No mostramos comunidades a las que el miembro no puede unirse:
              // las automáticas (Woman/Senior) solo aparecen si ya pertenece.
              if ( $es_automatica && ! $es_miembro ) continue;
          ?>
          <div class="col-12 col-sm-6 col-lg-3">
            <?php if ( $es_miembro ) : ?>
            <a href="<?php echo esc_url( home_url( '/comunidad-' . $com_id . '/' ) ); ?>" class="text-decoration-none d-block h-100">
              <div class="card-vx h-100 d-flex align-items-center gap-3 comunidad-card <?php echo esc_attr($card_cls); ?>">
                <div class="comunidad-icon <?php echo esc_attr($icon_cls); ?>"><i class="ti <?php echo esc_attr($icon); ?>"></i></div>
                <div><div class="comunidad-name"><?php echo esc_html($com_name); ?></div><div class="comunidad-status"><?php echo esc_html($com_desc); ?> · Activa</div></div>
              </div>
            </a>
            <?php else : ?>
            <div class="card-vx h-100 d-flex align-items-center gap-3 comunidad-card comunidad-card--inactive">
              <div class="comunidad-icon comunidad-icon--inactive"><i class="ti <?php echo esc_attr($icon); ?>"></i></div>
              <div class="flex-grow-1"><div class="comunidad-name"><?php echo esc_html($com_name); ?></div><div class="comunidad-status">No activa</div></div>
              <a href="<?php echo esc_url( home_url( '/editar-perfil/' ) ); ?>" class="btn-vx btn-ghost-vx btn-vx-sm">Unirse</a>
            </div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- MIS EMPRESAS -->
      <?php $empresas_user = $user->get_empresas(); if ( $empresas_user ) : ?>
      <div class="mb-5">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <h2 class="section-title-sm">Mis empresas</h2>
          <a href="<?php echo esc_url( home_url( '/editar-perfil/' ) ); ?>" class="btn-vx btn-ghost-vx btn-vx-sm"><i class="ti ti-pencil"></i> Editar</a>
        </div>
        <div class="row g-3">
          <?php foreach ( $empresas_user as $emp ) :
            $logo_id  = (int) get_post_meta( $emp->ID, 'vx_logo', true );
            $logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'vx-logo' ) : '';
            $cargo    = (string) get_post_meta( $emp->ID, 'vx_cargo', true );
            $web      = (string) get_post_meta( $emp->ID, 'vx_web', true );
            $linkedin = (string) get_post_meta( $emp->ID, 'vx_linkedin', true );
            $initial  = strtoupper( mb_substr( $emp->post_title, 0, 1 ) );
          ?>
          <div class="col-12 col-md-6">
            <div class="card-vx d-flex align-items-center gap-3">
              <div class="empresa-logo-circle">
                <?php if ( $logo_url ) : ?>
                <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $emp->post_title ); ?>">
                <?php else : ?>
                <span style="font-weight:700;font-size:20px;color:var(--color-primary-ink)"><?php echo esc_html( $initial ); ?></span>
                <?php endif; ?>
              </div>
              <div class="flex-grow-1 empresa-meta-dashboard">
                <div class="comunidad-name"><?php echo esc_html( $emp->post_title ); ?></div>
                <?php if ( $cargo ) : ?><div class="comunidad-status profile-company-meta-title"><?php echo esc_html( $cargo ); ?></div><?php endif; ?>
                <div class="d-flex gap-3 mt-1">
                  <?php if ( $web ) : ?>
                  <a href="<?php echo esc_url( $web ); ?>" target="_blank" rel="noopener" class="profile-company-link"><i class="ti ti-world"></i> <?php echo esc_html( preg_replace('#^https?://#', '', rtrim($web, '/')) ); ?></a>
                  <?php endif; ?>
                  <?php if ( $linkedin ) : ?>
                  <a href="<?php echo esc_url( $linkedin ); ?>" target="_blank" rel="noopener" class="profile-company-link"><i class="ti ti-brand-linkedin"></i> LinkedIn</a>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- MI TARJETA EN EL DIRECTORIO -->
      <div class="mb-5">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <div>
            <span class="subsection-label">Tu tarjeta en el directorio</span>
            <h2 class="subsection-title">Así te ven otros</h2>
          </div>
          <a href="<?php echo esc_url( home_url( '/editar-perfil/' ) ); ?>" class="btn-vx btn-soft-primary btn-vx-sm"><i class="ti ti-pencil"></i> Editar</a>
        </div>
        <div style="max-width:240px">
          <?php
          // Render the own card — no connect/fav buttons (viewer === owner)
          $own_card = $user->to_card_array();
          echo vx_render_member_card( $own_card, 0 ); // viewer=0 hides action buttons
          ?>
        </div>
      </div>

    </div>
    </main>
    <script>
    (function(){
      var ep    = <?php echo wp_json_encode( $ep_responder ); ?>;
      var nonce = <?php echo wp_json_encode( $nonce_rest ); ?>;
      document.querySelectorAll('.vx-conn-btn').forEach(function(btn){
        btn.addEventListener('click',function(){
          var cid=parseInt(this.dataset.connId);
          var acc=this.dataset.accion;
          this.disabled=true;
          fetch(ep,{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},body:JSON.stringify({conexion_id:cid,accion:acc})})
          .then(r=>r.json()).then(d=>{ if(d.success) location.reload(); else{ alert(d.error||'Error'); this.disabled=false; } })
          .catch(()=>{ alert('Error de red'); this.disabled=false; });
        });
      });
    })();
    </script>
    <?php echo vx_modal_conectar_html(); ?>
    <?php
    return ob_get_clean();
} );

// [vx_directorio] — directorio de miembros
add_shortcode( 'vx_directorio', function (): string {
    $user_id   = get_current_user_id();
    $pais      = isset( $_GET['pais'] )      ? sanitize_text_field( wp_unslash( $_GET['pais'] ) )      : '';
    $industria = isset( $_GET['industria'] ) ? sanitize_text_field( wp_unslash( $_GET['industria'] ) ) : '';
    $busqueda  = isset( $_GET['q'] )         ? sanitize_text_field( wp_unslash( $_GET['q'] ) )         : '';
    $fundador  = ! empty( $_GET['fundador'] );
    $pagina    = max( 1, (int) ( $_GET['pagina'] ?? 1 ) );

    $result    = VX_Directory::get_members( [
        'pais'      => $pais,
        'industria' => $industria,
        'busqueda'  => $busqueda,
        'fundador'  => $fundador,
        'page'      => $pagina,
    ] );
    $filters    = VX_Directory::get_filters();
    $members    = array_map( fn( $u ) => $u->to_card_array(), $result['users'] ?? [] );
    $pagination = $result['pagination'] ?? [];

    ob_start();
    ?>
    <header class="pt-5">
      <div class="container py-5">
        <h1 class="mt-2 mb-2 main-phrase text-center">
          Encuentra tu siguiente <strong>nexo</strong>
        </h1>
        <p class="text-center mb-5" style="color:var(--color-text-secondary);font-weight:600">
          <?php
          $vx_total = (int) ( $result['total'] ?? 0 );
          if ( $pais || $industria || $fundador || $busqueda ) {
              echo esc_html( $vx_total . ( 1 === $vx_total ? ' miembro coincide' : ' miembros coinciden' ) . ' con tu búsqueda' );
          } else {
              echo esc_html( $vx_total . ( 1 === $vx_total ? ' miembro' : ' miembros' ) . ' en el directorio' );
          }
          ?>
        </p>

        <!-- Búsqueda + filtros -->
        <form method="get" action="<?php echo esc_url( home_url( '/directorio/' ) ); ?>">
          <div class="search-bar-vx mb-3">
            <i class="ti ti-search" style="color:var(--color-text-secondary);font-size:16px"></i>
            <input type="text" name="q" value="<?php echo esc_attr( $busqueda ); ?>" placeholder="Buscar nombre, empresa, industria, tags..." />
            <button type="submit" class="btn-vx btn-primary-vx btn-vx-sm"><i class="ti ti-search"></i> Buscar</button>
          </div>
          <div class="d-flex gap-2 flex-wrap align-items-center">
            <select name="pais" class="btn-vx btn-ghost-vx btn-vx-sm" onchange="this.form.submit()" style="border:1px solid var(--color-border)">
              <option value=""><i class="ti ti-world"></i> País</option>
              <?php foreach ( $filters['paises'] ?? [] as $p ) : ?>
              <option value="<?php echo esc_attr( $p ); ?>" <?php selected( $pais, $p ); ?>><?php echo esc_html( $p ); ?></option>
              <?php endforeach; ?>
            </select>
            <select name="industria" class="btn-vx btn-ghost-vx btn-vx-sm" onchange="this.form.submit()" style="border:1px solid var(--color-border)">
              <option value="">Industria</option>
              <?php foreach ( vx_get_industrias() as $ind ) : ?>
              <option value="<?php echo esc_attr( $ind ); ?>" <?php selected( $industria, $ind ); ?>><?php echo esc_html( $ind ); ?></option>
              <?php endforeach; ?>
            </select>
            <label class="btn-vx <?php echo $fundador ? 'btn-soft-secondary' : 'btn-ghost-vx'; ?> btn-vx-sm" style="cursor:pointer">
              <input type="checkbox" name="fundador" value="1" <?php checked( $fundador ); ?> onchange="this.form.submit()" style="display:none">
              <i class="ti ti-star"></i> Miembros Pioneros
            </label>
            <?php if ( $pais || $industria || $fundador || $busqueda ) : ?>
            <a href="<?php echo esc_url( home_url( '/directorio/' ) ); ?>" class="btn-vx btn-ghost-vx btn-vx-sm ms-auto">
              <i class="ti ti-x"></i> Limpiar filtros
            </a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </header>

    <section id="search-result" class="members-section pb-5">
      <div class="container">
        <?php if ( $members ) : ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 row-cols-xl-5 g-2 justify-content-center" id="members-root">
          <?php foreach ( $members as $m ) : ?>
          <div class="col masonry-item"><?php echo vx_render_member_card( $m, $user_id ); ?></div>
          <?php endforeach; ?>
        </div>

        <!-- Paginación -->
        <?php if ( ! empty( $pagination ) && ( $pagination['total_pages'] ?? 1 ) > 1 ) : ?>
        <nav class="d-flex justify-content-center gap-2 mt-5">
          <?php for ( $i = 1; $i <= $pagination['total_pages']; $i++ ) : ?>
          <a href="<?php echo esc_url( add_query_arg( 'pagina', $i ) ); ?>"
             class="btn-vx <?php echo $i === $pagina ? 'btn-primary-vx' : 'btn-ghost-vx'; ?> btn-vx-sm">
            <?php echo $i; ?>
          </a>
          <?php endfor; ?>
        </nav>
        <?php endif; ?>

        <?php else : ?>
        <div class="empty-state-vx py-5 text-center">
          <div class="empty-state-vx__icon"><i class="ti ti-users-group"></i></div>
          <p class="empty-state-vx__title">No se encontraron miembros</p>
          <p class="empty-state-vx__desc">Intenta con otros filtros o términos de búsqueda.</p>
          <a href="<?php echo esc_url( home_url( '/directorio/' ) ); ?>" class="btn-vx btn-primary-vx btn-vx-sm mt-3">Ver todos</a>
        </div>
        <?php endif; ?>
      </div>
    </section>
    <?php echo vx_modal_conectar_html(); ?>
    <?php
    return ob_get_clean();
} );

// ── Helper compartido para matches ─────────────────────────────────────────────
function vx_matches_empty_state( string $tipo ): string {
    $msgs = [
        'seeks'  => [ 'Nadie ofrece lo que buscas aún', 'Agrega tags de búsqueda en tu perfil para ver coincidencias.' ],
        'offers' => [ 'Nadie busca lo que ofreces aún', 'Agrega tags de oferta en tu perfil para ver coincidencias.' ],
    ];
    [ $title, $desc ] = $msgs[ $tipo ] ?? [ 'Sin nexos', '' ];
    return '<div class="empty-state-vx py-5 text-center">
      <div class="empty-state-vx__icon"><i class="ti ti-sparkles"></i></div>
      <p class="empty-state-vx__title">' . esc_html( $title ) . '</p>
      <p class="empty-state-vx__desc">' . esc_html( $desc ) . '</p>
      <a href="' . esc_url( home_url( '/editar-perfil/' ) ) . '" class="btn-vx btn-primary-vx btn-vx-sm mt-3">Editar mi perfil</a>
    </div>';
}

// [vx_matches] — overview: primeros 5 de cada tipo con enlaces a páginas dedicadas
add_shortcode( 'vx_matches', function (): string {
    $user_id = get_current_user_id();
    $user    = VX_User::get( $user_id );
    if ( ! $user ) return '';

    $PREVIEW = 5; // items to show in overview

    $seeks_result  = VX_Matches::get_seeks_matches( $user_id, [ 'page' => 1, 'per_page' => $PREVIEW ] );
    $offers_result = VX_Matches::get_offers_matches( $user_id, [ 'page' => 1, 'per_page' => $PREVIEW ] );

    $seeks_users   = $seeks_result['users']  ?? [];
    $offers_users  = $offers_result['users'] ?? [];
    $seeks_total   = $seeks_result['total']  ?? count( $seeks_users );
    $offers_total  = $offers_result['total'] ?? count( $offers_users );

    $offer_tags = $user->get_offer_tags();
    $seek_tags  = $user->get_seek_tags();

    ob_start();
    ?>
    <div class="page-header-vx">
      <div class="container">
        <div class="page-header-vx__inner">
          <div>
            <h1 class="page-header-vx__title">Mis nexos</h1>
            <p class="page-header-vx__lead">Perfiles que coinciden con lo que ofreces y con lo que buscas.</p>
          </div>
          <div class="d-flex gap-2 flex-wrap align-items-center">
            <div class="d-flex flex-wrap gap-1">
              <?php foreach ( array_slice( $offer_tags, 0, 2 ) as $t ) : ?><span class="tag-vx tag-offers"><?php echo esc_html( $t ); ?></span><?php endforeach; ?>
              <?php foreach ( array_slice( $seek_tags,  0, 2 ) as $t ) : ?><span class="tag-vx tag-seeks"><?php echo esc_html( $t ); ?></span><?php endforeach; ?>
            </div>
            <a href="<?php echo esc_url( home_url( '/editar-perfil/' ) ); ?>" class="btn-vx btn-ghost-vx btn-vx-sm"><i class="ti ti-pencil"></i> Editar tags</a>
          </div>
        </div>
      </div>
    </div>

    <main>
    <div class="container py-4">

      <!-- OFRECEN LO QUE BUSCAS -->
      <section class="mb-5">
        <div class="d-flex align-items-center justify-content-between mb-3 gap-2 flex-wrap">
          <div>
            <h2 class="section-title-md">Ofrecen lo que buscas</h2>
            <p class="cta-card__desc">Su oferta coincide con lo que declaraste buscar.</p>
          </div>
          <div class="d-flex align-items-center gap-2">
            <span class="badge-vx badge-neutral"><?php echo $seeks_total; ?> <?php echo 1 === $seeks_total ? 'nexo' : 'nexos'; ?></span>
            <a href="<?php echo esc_url( home_url( '/nexos-que-busco/' ) ); ?>" class="btn-vx btn-soft-accent btn-vx-sm">Ver todos <i class="ti ti-arrow-right ms-1"></i></a>
          </div>
        </div>
        <?php if ( $seeks_users ) : ?>
        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5 g-2">
          <?php foreach ( $seeks_users as $u_obj ) :
            $mc = $u_obj->to_card_array();
            $matching_offers = array_values( array_intersect( (array) $mc['offer_tags'], $seek_tags ) );
          ?>
          <div class="col"><?php echo vx_render_member_card( $mc, $user_id, $matching_offers, [] ); ?></div>
          <?php endforeach; ?>
        </div>
        <?php else : ?>
        <?php echo vx_matches_empty_state( 'seeks' ); ?>
        <?php endif; ?>
      </section>

      <!-- BUSCAN LO QUE OFRECES -->
      <section class="mb-5">
        <div class="d-flex align-items-center justify-content-between mb-3 gap-2 flex-wrap">
          <div>
            <h2 class="section-title-md">Buscan lo que ofreces</h2>
            <p class="cta-card__desc">Su búsqueda coincide con lo que declaraste ofrecer.</p>
          </div>
          <div class="d-flex align-items-center gap-2">
            <span class="badge-vx badge-neutral"><?php echo $offers_total; ?> <?php echo 1 === $offers_total ? 'nexo' : 'nexos'; ?></span>
            <a href="<?php echo esc_url( home_url( '/nexos-que-ofrezco/' ) ); ?>" class="btn-vx btn-soft-primary btn-vx-sm">Ver todos <i class="ti ti-arrow-right ms-1"></i></a>
          </div>
        </div>
        <?php if ( $offers_users ) : ?>
        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5 g-2">
          <?php foreach ( $offers_users as $u_obj ) :
            $mc = $u_obj->to_card_array();
            $matching_seeks = array_values( array_intersect( (array) $mc['seek_tags'], $offer_tags ) );
          ?>
          <div class="col"><?php echo vx_render_member_card( $mc, $user_id, [], $matching_seeks ); ?></div>
          <?php endforeach; ?>
        </div>
        <?php else : ?>
        <?php echo vx_matches_empty_state( 'offers' ); ?>
        <?php endif; ?>
      </section>

    </div>
    </main>
    <?php echo vx_modal_conectar_html(); ?>
    <?php
    return ob_get_clean();
} );

// [vx_match_seeks] — página dedicada: ofrecen lo que buscas (con paginación)
add_shortcode( 'vx_match_seeks', function (): string {
    $user_id = get_current_user_id();
    $user    = VX_User::get( $user_id );
    if ( ! $user ) return '';

    $pagina  = max( 1, (int) ( $_GET['pagina'] ?? 1 ) );
    $result  = VX_Matches::get_seeks_matches( $user_id, [ 'page' => $pagina, 'per_page' => 20 ] );
    $users   = $result['users'] ?? [];
    $total   = $result['total'] ?? 0;
    $pagination = $result['pagination'] ?? [];
    $seek_tags  = $user->get_seek_tags();

    ob_start();
    ?>
    <div class="page-header-vx">
      <div class="container">
        <div class="page-header-vx__inner">
          <div>
            <h1 class="page-header-vx__title">Ofrecen lo que <strong class="match-section-label--seeks">buscas</strong></h1>
            <p class="page-header-vx__lead">Empresas cuya oferta coincide con lo que declaraste buscar en tu perfil.</p>
          </div>
          <div class="d-flex align-items-center gap-3 flex-wrap">
            <?php if ( $seek_tags ) : ?>
            <div>
              <span class="match-profile-label">Tu perfil busca</span>
              <div class="d-flex flex-wrap gap-1 mt-1">
                <?php foreach ( $seek_tags as $t ) : ?><span class="tag-vx tag-seeks"><?php echo esc_html( $t ); ?></span><?php endforeach; ?>
              </div>
            </div>
            <?php endif; ?>
            <a href="<?php echo esc_url( home_url( '/editar-perfil/' ) ); ?>" class="btn-vx btn-ghost-vx btn-vx-sm"><i class="ti ti-pencil"></i> Editar lo que busco</a>
          </div>
        </div>
      </div>
    </div>

    <main>
    <div class="container py-4">
      <div class="d-flex align-items-center justify-content-between gap-3 mb-4 flex-wrap">
        <a href="<?php echo esc_url( home_url( '/nexos/' ) ); ?>" class="btn-vx btn-ghost-vx btn-vx-sm"><i class="ti ti-arrow-left me-1"></i> Volver a mis nexos</a>
        <span class="badge-vx badge-neutral"><i class="ti ti-users me-1"></i><?php echo $total; ?> nexos</span>
      </div>

      <?php if ( $users ) : ?>
      <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5 g-2 mb-4">
        <?php foreach ( $users as $u_obj ) :
          $mc = $u_obj->to_card_array();
          $matching_offers = array_values( array_intersect( (array) $mc['offer_tags'], $seek_tags ) );
        ?>
        <div class="col"><?php echo vx_render_member_card( $mc, $user_id, $matching_offers, [] ); ?></div>
        <?php endforeach; ?>
      </div>
      <?php if ( ! empty( $pagination ) && ( $pagination['total_pages'] ?? 1 ) > 1 ) : ?>
      <nav class="d-flex justify-content-center gap-2 mt-4">
        <?php for ( $i = 1; $i <= $pagination['total_pages']; $i++ ) : ?>
        <a href="<?php echo esc_url( add_query_arg( 'pagina', $i ) ); ?>"
           class="btn-vx <?php echo $i === $pagina ? 'btn-primary-vx' : 'btn-ghost-vx'; ?> btn-vx-sm"><?php echo $i; ?></a>
        <?php endfor; ?>
      </nav>
      <?php endif; ?>
      <?php else : ?>
      <?php echo vx_matches_empty_state( 'seeks' ); ?>
      <?php endif; ?>
    </div>
    </main>
    <?php echo vx_modal_conectar_html(); ?>
    <?php
    return ob_get_clean();
} );

// [vx_match_offers] — página dedicada: buscan lo que ofreces (con paginación)
add_shortcode( 'vx_match_offers', function (): string {
    $user_id = get_current_user_id();
    $user    = VX_User::get( $user_id );
    if ( ! $user ) return '';

    $pagina  = max( 1, (int) ( $_GET['pagina'] ?? 1 ) );
    $result  = VX_Matches::get_offers_matches( $user_id, [ 'page' => $pagina, 'per_page' => 20 ] );
    $users   = $result['users'] ?? [];
    $total   = $result['total'] ?? 0;
    $pagination  = $result['pagination'] ?? [];
    $offer_tags  = $user->get_offer_tags();

    ob_start();
    ?>
    <div class="page-header-vx match-header--offers">
      <div class="container">
        <div class="page-header-vx__inner">
          <div>
            <h1 class="page-header-vx__title">Buscan lo que <strong>ofreces</strong></h1>
            <p class="page-header-vx__lead">Empresas cuya búsqueda coincide con lo que declaraste ofrecer en tu perfil.</p>
          </div>
          <div class="d-flex align-items-center gap-3 flex-wrap">
            <?php if ( $offer_tags ) : ?>
            <div>
              <span class="match-profile-label">Tu perfil ofrece</span>
              <div class="d-flex flex-wrap gap-1 mt-1">
                <?php foreach ( $offer_tags as $t ) : ?><span class="tag-vx tag-offers"><?php echo esc_html( $t ); ?></span><?php endforeach; ?>
              </div>
            </div>
            <?php endif; ?>
            <a href="<?php echo esc_url( home_url( '/editar-perfil/' ) ); ?>" class="btn-vx btn-ghost-vx btn-vx-sm"><i class="ti ti-pencil"></i> Editar lo que ofrezco</a>
          </div>
        </div>
      </div>
    </div>

    <main>
    <div class="container py-4">
      <div class="d-flex align-items-center justify-content-between gap-3 mb-4 flex-wrap">
        <a href="<?php echo esc_url( home_url( '/nexos/' ) ); ?>" class="btn-vx btn-ghost-vx btn-vx-sm"><i class="ti ti-arrow-left me-1"></i> Volver a mis nexos</a>
        <span class="badge-vx badge-neutral"><i class="ti ti-users me-1"></i><?php echo $total; ?> nexos</span>
      </div>

      <?php if ( $users ) : ?>
      <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5 g-2 mb-4">
        <?php foreach ( $users as $u_obj ) :
          $mc = $u_obj->to_card_array();
          $matching_seeks = array_values( array_intersect( (array) $mc['seek_tags'], $offer_tags ) );
        ?>
        <div class="col"><?php echo vx_render_member_card( $mc, $user_id, [], $matching_seeks ); ?></div>
        <?php endforeach; ?>
      </div>
      <?php if ( ! empty( $pagination ) && ( $pagination['total_pages'] ?? 1 ) > 1 ) : ?>
      <nav class="d-flex justify-content-center gap-2 mt-4">
        <?php for ( $i = 1; $i <= $pagination['total_pages']; $i++ ) : ?>
        <a href="<?php echo esc_url( add_query_arg( 'pagina', $i ) ); ?>"
           class="btn-vx <?php echo $i === $pagina ? 'btn-primary-vx' : 'btn-ghost-vx'; ?> btn-vx-sm"><?php echo $i; ?></a>
        <?php endfor; ?>
      </nav>
      <?php endif; ?>
      <?php else : ?>
      <?php echo vx_matches_empty_state( 'offers' ); ?>
      <?php endif; ?>
    </div>
    </main>
    <?php echo vx_modal_conectar_html(); ?>
    <?php
    return ob_get_clean();
} );

// [vx_favoritos] — lista de favoritos
add_shortcode( 'vx_favoritos', function (): string {
    $user_id       = get_current_user_id();
    $viewer        = VX_User::get( $user_id );
    $favoritos_ids = $viewer ? (array) $viewer->get_favoritos() : [];

    $members = [];
    foreach ( $favoritos_ids as $uid ) {
        $u = VX_User::get( (int) $uid );
        if ( $u && $u->is_active() ) {
            $members[] = $u->to_card_array();
        }
    }

    $count = count( $members );

    ob_start();
    ?>
    <div class="page-header-vx">
      <div class="container">
        <div class="page-header-vx__inner">
          <div>
            <div class="d-flex align-items-center gap-2 mb-1">
              <span class="section-landing-label" style="margin:0">Mis favoritos</span>
            </div>
            <h1 class="page-header-vx__title">Perfiles guardados</h1>
            <p class="page-header-vx__lead">Empresas que marcaste para retomar cuando quieras.</p>
          </div>
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge-vx badge-neutral"><i class="ti ti-bookmark me-1"></i><?php echo $count; ?> guardado<?php echo $count !== 1 ? 's' : ''; ?></span>
            <a href="<?php echo esc_url( home_url( '/directorio/' ) ); ?>" class="btn-vx btn-ghost-vx btn-vx-sm"><i class="ti ti-layout-grid"></i> Ir al directorio</a>
          </div>
        </div>
      </div>
    </div>

    <main>
    <div class="container py-4">
      <?php if ( $members ) : ?>
      <div class="page-section-heading mb-3">
        <h2 class="page-section-heading__title">Todos los guardados</h2>
        <span class="badge-vx badge-neutral"><?php echo $count; ?> perfiles</span>
      </div>
      <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5 g-2 mb-5">
        <?php foreach ( $members as $m ) : ?>
        <div class="col masonry-item"><?php echo vx_render_member_card( $m, $user_id ); ?></div>
        <?php endforeach; ?>
      </div>
      <?php else : ?>
      <div class="empty-state-vx py-5 text-center">
        <div class="empty-state-vx__icon"><i class="ti ti-heart"></i></div>
        <p class="empty-state-vx__title">Aún no tienes favoritos</p>
        <p class="empty-state-vx__desc">Guarda perfiles que te interesen para encontrarlos fácilmente.</p>
        <a href="<?php echo esc_url( home_url( '/directorio/' ) ); ?>" class="btn-vx btn-primary-vx btn-vx-sm mt-3"><i class="ti ti-layout-grid me-1"></i>Ir al directorio</a>
      </div>
      <?php endif; ?>
    </div>
    </main>
    <?php echo vx_modal_conectar_html(); ?>
    <?php
    return ob_get_clean();
} );

// [vx_conexiones] — lista de conexiones
add_shortcode( 'vx_conexiones', function (): string {
    $user_id   = get_current_user_id();
    $recibidas = array_values( array_filter( VX_Connection::get_received_by( $user_id ), fn( $c ) => 'pendiente' === $c->get_estado() ) );
    $enviadas  = array_values( array_filter( VX_Connection::get_sent_by( $user_id ),     fn( $c ) => in_array( $c->get_estado(), [ 'pendiente', 'sin_respuesta' ], true ) ) );
    $aceptadas = VX_Connection::get_accepted( $user_id );

    $nonce_rest       = wp_create_nonce( 'wp_rest' );
    $ep_responder     = rest_url( VX_REST_NAMESPACE . '/conexiones/responder' );
    $ep_bloquear      = rest_url( VX_REST_NAMESPACE . '/conexiones/bloquear' );
    $bloqueados_ids   = (array) ( get_user_meta( $user_id, 'vx_bloqueados', true ) ?: [] );
    $bloqueados_users = array_values( array_filter( array_map( fn( $id ) => VX_User::get( (int) $id ), $bloqueados_ids ) ) );

    ob_start();
    ?>
    <div class="page-header-vx">
      <div class="container">
        <div class="page-header-vx__inner">
          <div>
            <div class="d-flex align-items-center gap-2 mb-1">
              <span class="section-landing-label" style="margin:0">Red</span>
            </div>
            <h1 class="page-header-vx__title">Mis conexiones</h1>
            <p class="page-header-vx__lead">Solicitudes enviadas, recibidas y conexiones concretadas.</p>
          </div>
          <div class="d-flex gap-2">
            <div class="stat-card-vx text-center stat-box-vx">
              <div class="stat-num-vx stat-num-vx--success stat-num-lg"><?php echo count( $aceptadas ); ?></div>
              <div class="stat-label-vx">Concretadas</div>
            </div>
            <div class="stat-card-vx text-center stat-box-vx">
              <div class="stat-num-vx stat-num-vx--accent stat-num-lg"><?php echo count( $recibidas ); ?></div>
              <div class="stat-label-vx">Pendientes</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <main>
    <div class="container py-4">

      <nav class="vx-tabs" id="conn-tabs">
        <button class="vx-tab vx-tab--active" id="tab-concretadas" onclick="vxConnTab('concretadas',this)">
          <i class="ti ti-circle-check"></i> Concretadas
          <span class="badge-vx badge-primary"><?php echo count( $aceptadas ); ?></span>
        </button>
        <button class="vx-tab" id="tab-enviadas" onclick="vxConnTab('enviadas',this)">
          <i class="ti ti-send"></i> Enviadas
          <span class="badge-vx badge-neutral"><?php echo count( $enviadas ); ?></span>
        </button>
        <button class="vx-tab" id="tab-recibidas" onclick="vxConnTab('recibidas',this)">
          <i class="ti ti-inbox"></i> Recibidas
          <span class="badge-vx badge-accent"><?php echo count( $recibidas ); ?></span>
        </button>
        <button class="vx-tab" id="tab-bloqueados" onclick="vxConnTab('bloqueados',this)">
          <i class="ti ti-ban"></i> Bloqueados
          <?php if ( $bloqueados_users ) : ?><span class="badge-vx badge-neutral"><?php echo count( $bloqueados_users ); ?></span><?php endif; ?>
        </button>
      </nav>

      <!-- ── CONCRETADAS ── -->
      <div id="panel-concretadas">
        <p class="cta-card__desc mb-4">Personas con las que la conexión fue aceptada. Sus datos de contacto están disponibles.</p>
        <?php if ( $aceptadas ) : ?>
        <div class="row g-3">
          <?php foreach ( $aceptadas as $conn ) :
            $other_id   = $conn->get_other_user_id( $user_id );
            $other_user = VX_User::get( $other_id );
            if ( ! $other_user ) continue;
            $yo_contacte = $conn->get_emisor_id() === $user_id;
            $emp_activa  = $other_user->get_empresa_activa();
            $cargo       = get_post_meta( $emp_activa ? $emp_activa->ID : 0, 'vx_cargo', true );
            $role_str    = trim( implode( ' · ', array_filter( [
                $cargo,
                $emp_activa ? $emp_activa->post_title : '',
                trim( ( $other_user->get_ciudad() ? $other_user->get_ciudad() : '' ) . ( $other_user->get_pais_codigo() ? ' (' . $other_user->get_pais_codigo() . ')' : '' ) )
            ] ) ) );
            $contacto_preferido = get_user_meta( $other_id, VX_User_Meta::CONTACTO_PREFERIDO, true ) ?: 'email';
          ?>
          <div class="col-12">
            <div class="contact-reveal">
              <a href="<?php echo esc_url( home_url( '/perfil/' . $other_user->get_slug() . '/' ) ); ?>" class="flex-shrink-0">
                <img src="<?php echo esc_url( $other_user->get_foto_url( 'vx-avatar' ) ); ?>" alt="" class="contact-reveal-avatar">
              </a>
              <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                  <a href="<?php echo esc_url( home_url( '/perfil/' . $other_user->get_slug() . '/' ) ); ?>" class="contact-reveal-name"><?php echo esc_html( $other_user->get_nombre_completo() ); ?></a>
                  <span class="badge-vx badge-neutral" style="font-size:10px">
                    <?php if ( $yo_contacte ) : ?><i class="ti ti-send me-1"></i>Yo contacté<?php else : ?><i class="ti ti-inbox me-1"></i>Me contactó<?php endif; ?>
                  </span>
                </div>
                <?php if ( $role_str ) : ?><div class="contact-reveal-role"><?php echo esc_html( $role_str ); ?></div><?php endif; ?>
                <?php echo vx_render_contact_links( $other_user, $contacto_preferido ); ?>
              </div>
              <div class="d-flex flex-column align-items-end gap-1 flex-shrink-0">
                <button class="btn-vx btn-soft-danger-vx btn-vx-sm vx-bloquear-btn" data-user-id="<?php echo esc_attr( $other_id ); ?>"><i class="ti ti-ban me-1"></i>Bloquear</button>
                <span class="text-xxs-muted"><?php echo esc_html( date_i18n( 'd M Y', $conn->get_fecha_envio() ) ); ?></span>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else : ?>
        <div class="empty-state-vx py-5 text-center">
          <div class="empty-state-vx__icon"><i class="ti ti-network"></i></div>
          <p class="empty-state-vx__title">Aún no tienes conexiones concretadas</p>
          <p class="empty-state-vx__desc">Cuando una solicitud sea aceptada, el perfil aparecerá aquí.</p>
        </div>
        <?php endif; ?>
      </div>

      <!-- ── ENVIADAS ── -->
      <div id="panel-enviadas" style="display:none">
        <?php if ( $enviadas ) : ?>
        <div class="d-flex flex-column gap-3">
          <?php foreach ( $enviadas as $conn ) :
            $other_id   = $conn->get_other_user_id( $user_id );
            $other_user = VX_User::get( $other_id );
            if ( ! $other_user ) continue;
            $emp_activa = $other_user->get_empresa_activa();
            $estado     = $conn->get_estado();
            $badge_label = 'pendiente' === $estado ? '<i class="ti ti-clock me-1"></i>Pendiente' : ucfirst( $estado );
          ?>
          <div class="card-vx">
            <div class="conn-row">
              <img src="<?php echo esc_url( $other_user->get_foto_url( 'vx-avatar' ) ); ?>" alt="" class="conn-avatar">
              <div class="conn-info">
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                  <a href="<?php echo esc_url( home_url( '/perfil/' . $other_user->get_slug() . '/' ) ); ?>" class="conn-name"><?php echo esc_html( $other_user->get_nombre_completo() ); ?></a>
                  <span class="badge-vx badge-neutral"><?php echo $badge_label; ?></span>
                </div>
                <div class="conn-meta"><?php if ( $emp_activa ) echo esc_html( $emp_activa->post_title ) . ' · '; echo esc_html( $other_user->get_ciudad() ); if ( $other_user->get_pais_codigo() ) echo ' (' . esc_html( $other_user->get_pais_codigo() ) . ')'; ?></div>
                <div class="conn-date"><i class="ti ti-calendar"></i> Enviada el <?php echo esc_html( date_i18n( 'j M Y', $conn->get_fecha_envio() ) ); ?></div>
                <?php if ( $conn->get_pitch() ) : ?>
                <div class="conn-pitch">"<?php echo esc_html( wp_trim_words( $conn->get_pitch(), 20 ) ); ?>"</div>
                <?php endif; ?>
              </div>
              <div class="d-flex flex-column align-items-end gap-1 flex-shrink-0">
                <a href="<?php echo esc_url( home_url( '/perfil/' . $other_user->get_slug() . '/' ) ); ?>" class="btn-vx btn-ghost-vx btn-vx-sm"><i class="ti ti-external-link"></i></a>
                <button class="btn-vx btn-soft-danger-vx btn-vx-sm vx-bloquear-btn" data-user-id="<?php echo esc_attr( $other_id ); ?>"><i class="ti ti-ban me-1"></i>Bloquear</button>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else : ?>
        <div class="empty-state-vx py-5 text-center">
          <div class="empty-state-vx__icon"><i class="ti ti-send"></i></div>
          <p class="empty-state-vx__title">No has enviado solicitudes</p>
          <p class="empty-state-vx__desc">Explora el directorio y conecta con otros miembros.</p>
          <a href="<?php echo esc_url( home_url( '/directorio/' ) ); ?>" class="btn-vx btn-primary-vx btn-vx-sm mt-3">Ir al directorio</a>
        </div>
        <?php endif; ?>
      </div>

      <!-- ── RECIBIDAS ── -->
      <div id="panel-recibidas" style="display:none">
        <?php if ( $recibidas ) : ?>
        <div class="d-flex flex-column gap-3">
          <?php foreach ( $recibidas as $conn ) :
            $emisor    = VX_User::get( $conn->get_emisor_id() );
            if ( ! $emisor ) continue;
            $emp_activa = $emisor->get_empresa_activa();
            $estado     = $conn->get_estado();
          ?>
          <div class="card-vx border-left-accent">
            <div class="conn-row">
              <img src="<?php echo esc_url( $emisor->get_foto_url( 'vx-avatar' ) ); ?>" alt="" class="conn-avatar">
              <div class="conn-info">
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                  <a href="<?php echo esc_url( home_url( '/perfil/' . $emisor->get_slug() . '/' ) ); ?>" class="conn-name"><?php echo esc_html( $emisor->get_nombre_completo() ); ?></a>
                  <?php if ( 'pendiente' === $estado ) : ?>
                  <span class="badge-vx badge-accent"><i class="ti ti-bell me-1"></i>Nueva</span>
                  <?php endif; ?>
                </div>
                <div class="conn-meta"><?php if ( $emp_activa ) echo esc_html( $emp_activa->post_title ) . ' · '; echo esc_html( $emisor->get_ciudad() ); if ( $emisor->get_pais_codigo() ) echo ' (' . esc_html( $emisor->get_pais_codigo() ) . ')'; ?></div>
                <div class="conn-date"><i class="ti ti-calendar"></i> Recibida el <?php echo esc_html( date_i18n( 'j M Y', $conn->get_fecha_envio() ) ); ?></div>
                <?php if ( $conn->get_pitch() ) : ?>
                <div class="conn-pitch">"<?php echo esc_html( wp_trim_words( $conn->get_pitch(), 25 ) ); ?>"</div>
                <?php endif; ?>
              </div>
              <?php if ( 'pendiente' === $estado ) : ?>
              <div class="d-flex gap-2 flex-shrink-0 flex-column">
                <button class="btn-vx btn-success-vx btn-vx-sm vx-conn-btn" data-conn-id="<?php echo $conn->get_id(); ?>" data-accion="aceptado"><i class="ti ti-check me-1"></i>Aceptar</button>
                <button class="btn-vx btn-danger-vx btn-vx-sm vx-conn-btn" data-conn-id="<?php echo $conn->get_id(); ?>" data-accion="rechazado"><i class="ti ti-x me-1"></i>Rechazar</button>
                <button class="btn-vx btn-soft-danger-vx btn-vx-sm vx-bloquear-btn" data-user-id="<?php echo esc_attr( $conn->get_emisor_id() ); ?>"><i class="ti ti-ban me-1"></i>Bloquear</button>
              </div>
              <?php endif; ?>
            </div>
            <?php if ( 'pendiente' === $estado ) : ?>
            <div style="padding-top:10px">
              <textarea class="vx-conn-mensaje form-control-vx" rows="2" placeholder="Escribe un mensaje de respuesta (opcional)..." style="font-size:13px;resize:none" data-conn-id="<?php echo $conn->get_id(); ?>"></textarea>
            </div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else : ?>
        <div class="empty-state-vx py-5 text-center">
          <div class="empty-state-vx__icon"><i class="ti ti-inbox"></i></div>
          <p class="empty-state-vx__title">No tienes solicitudes recibidas</p>
          <p class="empty-state-vx__desc">Cuando alguien quiera conectar contigo aparecerá aquí.</p>
        </div>
        <?php endif; ?>
      </div>


      <!-- ── BLOQUEADOS ── -->
      <div id="panel-bloqueados" style="display:none">
        <?php if ( $bloqueados_users ) : ?>
        <p class="cta-card__desc mb-4">Usuarios bloqueados no pueden enviarte solicitudes de conexión ni tú a ellos.</p>
        <div class="d-flex flex-column gap-3">
          <?php foreach ( $bloqueados_users as $bu ) :
            $emp_activa_bu = $bu->get_empresa_activa();
          ?>
          <div class="card-vx">
            <div class="conn-row">
              <img src="<?php echo esc_url( $bu->get_foto_url( 'vx-avatar' ) ); ?>" alt="" class="conn-avatar">
              <div class="conn-info">
                <a href="<?php echo esc_url( home_url( '/perfil/' . $bu->get_slug() . '/' ) ); ?>" class="conn-name"><?php echo esc_html( $bu->get_nombre_completo() ); ?></a>
                <?php if ( $emp_activa_bu ) : ?><div class="conn-meta"><?php echo esc_html( $emp_activa_bu->post_title ); ?></div><?php endif; ?>
              </div>
              <button class="btn-vx btn-ghost-vx btn-vx-sm vx-desbloquear-btn" data-user-id="<?php echo esc_attr( $bu->get_id() ); ?>"><i class="ti ti-ban-off me-1"></i>Desbloquear</button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else : ?>
        <div class="empty-state-vx py-5 text-center">
          <div class="empty-state-vx__icon"><i class="ti ti-ban"></i></div>
          <p class="empty-state-vx__title">No has bloqueado a ningún usuario</p>
          <p class="empty-state-vx__desc">Los usuarios bloqueados no pueden conectar contigo.</p>
        </div>
        <?php endif; ?>
      </div>

    </div>
    </main>
    <script>
    function vxConnTab(tab, el) {
      document.querySelectorAll('#conn-tabs .vx-tab').forEach(function(b){ b.classList.remove('vx-tab--active'); });
      el.classList.add('vx-tab--active');
      ['concretadas','enviadas','recibidas','bloqueados'].forEach(function(p){ document.getElementById('panel-'+p).style.display = p===tab ? '' : 'none'; });
    }
    (function(){
      var ep         = <?php echo wp_json_encode( $ep_responder ); ?>;
      var epBloquear = <?php echo wp_json_encode( $ep_bloquear ); ?>;
      var nonce      = <?php echo wp_json_encode( $nonce_rest ); ?>;

      document.querySelectorAll('.vx-conn-btn').forEach(function(btn){
        btn.addEventListener('click',function(){
          var cid=parseInt(this.dataset.connId), acc=this.dataset.accion;
          var mensajeEl=document.querySelector('.vx-conn-mensaje[data-conn-id="'+cid+'"]');
          var mensaje=mensajeEl?mensajeEl.value.trim():'';
          this.disabled=true;
          fetch(ep,{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},body:JSON.stringify({conexion_id:cid,accion:acc,mensaje:mensaje})})
          .then(r=>r.json()).then(d=>{ if(d.success) location.reload(); else{ alert(d.error||'Error'); this.disabled=false; } })
          .catch(()=>{ alert('Error de red'); this.disabled=false; });
        });
      });

      document.querySelectorAll('.vx-bloquear-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
          if (!confirm('¿Bloquear a este usuario? Esto cancelará cualquier conexión existente.')) return;
          var uid = parseInt(this.dataset.userId);
          var self = this;
          self.disabled = true;
          fetch(epBloquear, {method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},body:JSON.stringify({usuario_id:uid})})
          .then(r=>r.json()).then(function(d){
            if (d.success) { location.reload(); }
            else { alert(d.error||'Error al bloquear'); self.disabled = false; }
          }).catch(function(){ alert('Error de red'); self.disabled = false; });
        });
      });

      document.querySelectorAll('.vx-desbloquear-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
          var uid = parseInt(this.dataset.userId);
          var self = this;
          self.disabled = true;
          fetch(epBloquear + '/' + uid, {method:'DELETE',headers:{'X-WP-Nonce':nonce}})
          .then(r=>r.json()).then(function(d){
            if (d.success) { location.reload(); }
            else { alert(d.error||'Error al desbloquear'); self.disabled = false; }
          }).catch(function(){ alert('Error de red'); self.disabled = false; });
        });
      });
    })();
    </script>
    <?php
    return ob_get_clean();
} );

// [vx_notificaciones] — lista de notificaciones
add_shortcode( 'vx_notificaciones', function (): string {
    $user_id    = get_current_user_id();
    $pagina     = max( 1, (int) ( $_GET['pagina'] ?? 1 ) );
    $result     = VX_Notification::get_for_user( $user_id, $pagina );
    $notifs     = $result['items']      ?? [];
    $pagination = $result['pagination'] ?? [];

    // Mapa de tipo → clase de icono del mockup
    // Los tipos vienen de class-vx-notification-triggers.php
    $icon_map = [
        'conexion_nueva'     => [ 'class' => 'notif-icon--accent',  'icon' => 'ti-send',        'css' => 'notif-item--connection' ],
        'conexion_recibida'  => [ 'class' => 'notif-icon--accent',  'icon' => 'ti-send',        'css' => 'notif-item--connection' ],
        'conexion_aceptada'  => [ 'class' => 'notif-icon--success', 'icon' => 'ti-circle-check','css' => 'notif-item--success' ],
        'match_nuevo'        => [ 'class' => 'notif-icon--primary', 'icon' => 'ti-sparkles',    'css' => 'notif-item--match' ],
        'visita_perfil'      => [ 'class' => 'notif-icon--neutral', 'icon' => 'ti-eye',            'css' => '' ],
        'favorito'           => [ 'class' => 'notif-icon--neutral', 'icon' => 'ti-heart',          'css' => '' ],
        'comentario_pub'     => [ 'class' => 'notif-icon--accent',  'icon' => 'ti-message-circle', 'css' => '' ],
        'dinner_disponible'  => [ 'class' => 'notif-icon--dinner',  'icon' => '',               'css' => 'notif-item--dinner' ],
        'dinner_invitacion'  => [ 'class' => 'notif-icon--dinner',  'icon' => '',               'css' => 'notif-item--dinner' ],
        'dinner_asignado'    => [ 'class' => 'notif-icon--success', 'icon' => 'ti-circle-check','css' => 'notif-item--success' ],
        'perfil_propuesta'   => [ 'class' => 'notif-icon--primary', 'icon' => 'ti-user-edit',   'css' => '' ],
        'default'            => [ 'class' => 'notif-icon--neutral', 'icon' => 'ti-bell',        'css' => '' ],
    ];

    // Agrupar por fecha — $n['fecha'] es Unix timestamp (int), no string
    $hoy = $ayer = $semana = [];
    $now = time();
    foreach ( $notifs as $n ) {
        $ts   = (int) $n['fecha'];  // ya es timestamp, no usar strtotime()
        $diff = $now - $ts;
        if ( $diff < DAY_IN_SECONDS )       $hoy[]    = $n;
        elseif ( $diff < 2 * DAY_IN_SECONDS ) $ayer[] = $n;
        else                                  $semana[] = $n;
    }

    ob_start();
    ?>
    <div class="page-header-vx">
      <div class="container">
        <div class="page-header-vx__inner">
          <div>
            <h1 class="page-header-vx__title">Notificaciones</h1>
            <p class="page-header-vx__lead">Lo que pasó mientras no estabas.</p>
          </div>
        </div>
      </div>
    </div>

    <main>
    <div class="container container-narrow py-4">

      <?php
      $grupos = array_filter( [
          'Hoy'         => $hoy,
          'Ayer'        => $ayer,
          'Esta semana' => $semana,
      ] );

      if ( $grupos ) :
        foreach ( $grupos as $label => $items ) :
      ?>
      <div class="mb-4">
        <span class="subsection-label mb-2 d-block"><?php echo esc_html( $label ); ?></span>
        <div class="d-flex flex-column gap-2">
          <?php foreach ( $items as $notif ) :
            $tipo      = $notif['tipo'] ?? 'default';
            $icon_cfg  = $icon_map[ $tipo ] ?? $icon_map['default'];
            $tipo_css  = $icon_cfg['css'];
            $unread    = ! $notif['leida'];
            $has_link  = ! empty( $notif['link'] );
            $texto     = $notif['titulo'] ?? $notif['tipo'] ?? '';
            $time_str  = human_time_diff( (int) $notif['fecha'], time() ) . ' atrás';

            // visita_perfil con actor conocido → nombre como enlace, sin envolver toda la card
            $texto_html    = null;
            $use_card_link = $has_link;
            if ( 'visita_perfil' === $tipo && ! empty( $notif['actor']['perfil_url'] ) ) {
                $a_nombre   = esc_html( $notif['actor']['nombre'] );
                $a_url      = esc_url( $notif['actor']['perfil_url'] );
                $texto_html = '<a href="' . $a_url . '" class="notif-actor-link">' . $a_nombre . '</a> visitó tu perfil';
                $use_card_link = false;
            }

            $classes = 'card-vx notif-item' . ( $tipo_css ? ' ' . $tipo_css : '' ) . ( $unread ? ' notif-item--unread' : '' );
          ?>
          <?php if ( $use_card_link ) : ?><a href="<?php echo esc_url( $notif['link'] ); ?>" class="text-decoration-none"><?php endif; ?>
          <div class="<?php echo esc_attr( $classes ); ?>">
            <div class="notif-icon <?php echo esc_attr( $icon_cfg['class'] ); ?>">
              <?php if ( 'dinner' === $tipo ) : ?><i class="ti ti-tools-kitchen-2" aria-hidden="true"></i><?php else : ?><i class="ti <?php echo esc_attr( $icon_cfg['icon'] ); ?>"></i><?php endif; ?>
            </div>
            <div class="notif-body">
              <p class="notif-text">
                <?php if ( $texto_html ) : echo wp_kses( $texto_html, [ 'a' => [ 'href' => true, 'class' => true ] ] ); else : echo esc_html( $texto ); endif; ?>
              </p>
              <span class="notif-time"><?php echo esc_html( $time_str ); ?></span>
            </div>
            <?php if ( $use_card_link ) : ?><i class="ti ti-chevron-right notif-chevron"></i><?php endif; ?>
          </div>
          <?php if ( $use_card_link ) : ?></a><?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
      <?php else : ?>
      <div class="empty-state-vx py-5 text-center">
        <div class="empty-state-vx__icon"><i class="ti ti-bell-off"></i></div>
        <p class="empty-state-vx__title">Sin notificaciones</p>
        <p class="empty-state-vx__desc">Cuando haya actividad en tu cuenta, te avisaremos aquí.</p>
      </div>
      <?php endif; ?>

      <!-- Paginación -->
      <?php if ( ! empty( $pagination ) && ( $pagination['total_pages'] ?? 1 ) > 1 ) : ?>
      <nav class="d-flex justify-content-center gap-2 mt-4">
        <?php for ( $i = 1; $i <= $pagination['total_pages']; $i++ ) : ?>
        <a href="<?php echo esc_url( add_query_arg( 'pagina', $i ) ); ?>"
           class="btn-vx <?php echo $i === $pagina ? 'btn-primary-vx' : 'btn-ghost-vx'; ?> btn-vx-sm">
          <?php echo $i; ?>
        </a>
        <?php endfor; ?>
      </nav>
      <?php endif; ?>

    </div>
    </main>
    <?php
    // Marcar todas como leídas
    VX_Notification::mark_all_read( $user_id );
    return ob_get_clean();
} );

// [vx_configuracion] — configuración de cuenta
add_shortcode( 'vx_configuracion', function (): string {
    $user_id   = get_current_user_id();
    $user      = VX_User::get( $user_id );
    if ( ! $user ) return '';
    $wp_user   = get_userdata( $user_id );
    $membresia = VX_Membership::get( $user_id );
    $nonce     = wp_create_nonce( 'wp_rest' );
    $ep_pass   = rest_url( VX_REST_NAMESPACE . '/cuenta/cambiar-password' );

    ob_start();
    ?>
    <div class="page-header-vx">
      <div class="container">
        <div class="page-header-vx__inner">
          <div>
            <div class="d-flex align-items-center gap-2 mb-1">
              <span class="section-landing-label" style="margin:0">Cuenta</span>
            </div>
            <h1 class="page-header-vx__title">Configuración</h1>
            <p class="page-header-vx__lead">Ajustes de cuenta, seguridad y plan.</p>
          </div>
        </div>
      </div>
    </div>

    <main>
    <div class="container py-4">
      <div class="row g-4">

        <!-- SIDEBAR NAV -->
        <div class="col-12 col-lg-3">
          <div class="card-vx p-0 overflow-hidden">
            <nav class="d-flex flex-column">
              <a href="#cuenta"       class="config-nav-item config-nav-item--active" onclick="vxShowSection('cuenta',this)"><i class="ti ti-user"></i> Cuenta</a>
              <a href="#seguridad"    class="config-nav-item" onclick="vxShowSection('seguridad',this)"><i class="ti ti-lock"></i> Seguridad</a>
              <a href="#plan"         class="config-nav-item" onclick="vxShowSection('plan',this)"><i class="ti ti-star"></i> Plan</a>
              <a href="#comunidades"  class="config-nav-item" onclick="vxShowSection('comunidades',this)"><i class="ti ti-users-group"></i> Comunidades</a>
              <a href="#peligro"      class="config-nav-item config-nav-item--danger" onclick="vxShowSection('peligro',this)"><i class="ti ti-trash"></i> Zona de peligro</a>
            </nav>
          </div>
        </div>

        <!-- CONTENT -->
        <div class="col-12 col-lg-9">

          <!-- CUENTA -->
          <div id="section-cuenta" class="config-section">
            <div class="card-vx mb-3">
              <h2 class="section-title-sm mb-1">Información de cuenta</h2>
              <p class="cta-card__desc mb-4">Estos datos son de tu cuenta, no de tu perfil público.</p>
              <div class="row g-3 mb-4">
                <div class="col-md-6">
                  <label class="form-label-vx">Nombre</label>
                  <input type="text" class="form-control-vx input-readonly-vx" value="<?php echo esc_attr( $user->get_nombre() ); ?>" readonly>
                </div>
                <div class="col-md-6">
                  <label class="form-label-vx">Apellido</label>
                  <input type="text" class="form-control-vx input-readonly-vx" value="<?php echo esc_attr( $user->get_apellido() ); ?>" readonly>
                </div>
                <div class="col-12">
                  <label class="form-label-vx">Email de cuenta</label>
                  <div class="input-group-vx mb-1">
                    <span class="input-icon"><i class="ti ti-mail"></i></span>
                    <input type="email" value="<?php echo esc_attr( $wp_user->user_email ); ?>" readonly class="input-readonly-vx" id="current-email-display">
                  </div>
                  <button type="button" class="btn-vx btn-ghost-vx btn-vx-sm" id="vx-show-change-email">
                    <i class="ti ti-pencil me-1"></i>Cambiar email
                  </button>

                  <!-- Formulario cambio de email (oculto hasta clic) -->
                  <div id="vx-change-email-form" class="mt-3 p-3" style="display:none;background:var(--hueso-2);border-radius:var(--radius-sm)">
                    <p class="cta-card__desc mb-3">
                      <i class="ti ti-info-circle me-1 ic-primary"></i>
                      Se enviará un enlace de confirmación al nuevo email. El cambio solo se aplica cuando hagas clic en ese enlace.
                    </p>
                    <div class="mb-3">
                      <label class="form-label-vx">Nuevo email</label>
                      <input type="email" id="vx-new-email" class="form-control-vx" placeholder="tu@nuevoemail.com" autocomplete="off">
                    </div>
                    <div class="mb-3">
                      <label class="form-label-vx">Contraseña actual <span class="form-hint d-inline">(para confirmar)</span></label>
                      <input type="password" id="vx-email-pass" class="form-control-vx" placeholder="Tu contraseña actual" autocomplete="current-password">
                    </div>
                    <div id="vx-change-email-msg" class="d-none mb-3 small"></div>
                    <div class="d-flex gap-2">
                      <button type="button" class="btn-vx btn-primary-vx btn-vx-sm" id="vx-btn-change-email">
                        <i class="ti ti-send me-1"></i>Enviar confirmación
                      </button>
                      <button type="button" class="btn-vx btn-ghost-vx btn-vx-sm" onclick="document.getElementById('vx-change-email-form').style.display='none'">
                        Cancelar
                      </button>
                    </div>
                  </div>
                </div>
              </div>
              <a href="<?php echo esc_url( home_url( '/editar-perfil/' ) ); ?>" class="btn-vx btn-primary-vx btn-vx-sm">
                <i class="ti ti-pencil me-1"></i>Editar perfil público
              </a>
            </div>
          </div>

          <!-- SEGURIDAD -->
          <div id="section-seguridad" class="config-section" style="display:none">
            <div class="card-vx mb-3">
              <h2 class="section-title-sm mb-1">Cambiar contraseña</h2>
              <p class="cta-card__desc mb-4">Usa una contraseña de al menos 8 caracteres con letras y números.</p>
              <div class="d-flex flex-column gap-3 mb-4" style="max-width:400px">
                <div>
                  <label class="form-label-vx">Contraseña actual</label>
                  <div class="input-group-vx" style="position:relative">
                    <span class="input-icon"><i class="ti ti-lock"></i></span>
                    <input type="password" id="vx-pass-actual" placeholder="Tu contraseña actual" style="padding-right:38px">
                    <button type="button" class="vx-pass-eye" data-target="vx-pass-actual" tabindex="-1" aria-label="Mostrar u ocultar contraseña" style="position:absolute;right:2px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--color-text-muted)"><i class="ti ti-eye"></i></button>
                  </div>
                </div>
                <div>
                  <label class="form-label-vx">Nueva contraseña</label>
                  <div class="input-group-vx" style="position:relative">
                    <span class="input-icon"><i class="ti ti-lock-plus"></i></span>
                    <input type="password" id="vx-pass-nuevo" placeholder="Mínimo 8 caracteres" style="padding-right:38px">
                    <button type="button" class="vx-pass-eye" data-target="vx-pass-nuevo" tabindex="-1" aria-label="Mostrar u ocultar contraseña" style="position:absolute;right:2px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--color-text-muted)"><i class="ti ti-eye"></i></button>
                  </div>
                </div>
              </div>
              <div id="vx-pass-msg" class="mb-3 d-none small"></div>
              <button class="btn-vx btn-primary-vx btn-vx-sm" id="vx-btn-cambiar-pass">Actualizar contraseña</button>
              <script>
              document.querySelectorAll('.vx-pass-eye').forEach(function(b){
                b.addEventListener('click', function(){
                  var inp = document.getElementById(this.dataset.target);
                  if (!inp) return;
                  var show = inp.type === 'password';
                  inp.type = show ? 'text' : 'password';
                  var ic = this.querySelector('i');
                  if (ic) ic.className = show ? 'ti ti-eye-off' : 'ti ti-eye';
                });
              });
              </script>
            </div>
          </div>

          <!-- PLAN -->
          <div id="section-plan" class="config-section" style="display:none">
            <?php
            $planes_config  = function_exists('vx_get_planes_config') ? vx_get_planes_config() : [];
            $plan_actual    = $membresia->get_plan() ?: 'gratuito';
            $plan_estado    = $membresia->get_plan_estado() ?: 'activo';
            $expiry         = $membresia->get_expiry();
            $days_left      = $membresia->days_until_expiry();
            $es_fundador    = $user->is_founder();
            $is_vencido     = 'vencido' === $plan_estado;
            $nonce_rest     = wp_create_nonce( 'wp_rest' );
            $ep_checkout    = rest_url( VX_REST_NAMESPACE . '/stripe/checkout' );
            $ep_portal      = rest_url( VX_REST_NAMESPACE . '/stripe/portal' );

            // Alerta de plan vencido
            $motivo = isset( $_GET['motivo'] ) ? sanitize_key( $_GET['motivo'] ) : '';
            if ( $is_vencido || $motivo === 'acceso' ) :
            ?>
            <div class="alert-vx alert-error mb-4">
              <i class="ti ti-alert-circle" style="font-size:20px;flex-shrink:0"></i>
              <div>
                <strong>Tu acceso gratuito ha vencido.</strong><br>
                Elige un plan para seguir conectando con la red Vitrinexo.
              </div>
            </div>
            <?php elseif ( $days_left !== null && $days_left <= 30 && $days_left > 0 ) : ?>
            <div class="alert-vx alert-info mb-4">
              <i class="ti ti-clock" style="font-size:20px;flex-shrink:0"></i>
              <div>
                Tu plan gratuito vence en <strong><?php echo $days_left; ?> día<?php echo 1 === $days_left ? '' : 's'; ?></strong>
                (<?php echo esc_html( date_i18n( 'd/m/Y', $expiry ) ); ?>).
              </div>
            </div>
            <?php endif; ?>

            <!-- Estado actual -->
            <div class="card-vx mb-4">
              <h2 class="section-title-sm mb-3">Tu membresía actual</h2>
              <div class="d-flex align-items-center gap-3 flex-wrap mb-3">
                <?php if ( $es_fundador ) : ?>
                <span style="background:var(--mantequilla-suave);color:var(--color-text-primary);border-radius:6px;padding:4px 12px;font-weight:700;font-size:14px">Miembro Pionero</span>
                <?php endif; ?>
                <span class="badge-vx <?php echo $is_vencido ? 'badge-neutral' : 'badge-primary'; ?>" style="font-size:13px">
                  <?php echo esc_html( ucfirst( $plan_actual ) ); ?>
                  <?php echo $is_vencido ? ' · Vencido' : ' · Activo'; ?>
                </span>
                <?php if ( $expiry ) : ?>
                <span class="text-body-muted" style="font-size:13px">
                  <?php echo $is_vencido ? 'Venció el ' : 'Vence el '; ?>
                  <?php echo esc_html( date_i18n( 'd/m/Y', $expiry ) ); ?>
                </span>
                <?php else : ?>
                <span class="text-body-muted" style="font-size:13px">Sin vencimiento</span>
                <?php endif; ?>
              </div>

              <?php if ( $membresia->is_paid() && ! $is_vencido ) : ?>
              <!-- Tiene plan pagado activo: mostrar botón portal de Stripe -->
              <button class="btn-vx btn-ghost-vx btn-vx-sm" id="vx-plan-portal-btn">
                <i class="ti ti-credit-card me-1"></i>Gestionar suscripción
              </button>
              <p class="text-xs-muted mt-1">Cambiar tarjeta, ver facturas o cancelar.</p>
              <?php endif; ?>
            </div>

            <!-- Planes disponibles -->
            <?php if ( $is_vencido || $membresia->is_gratuito() ) : ?>
            <h3 class="section-title-sm mb-3">Elige tu plan</h3>
            <div class="row g-3 mb-4" id="vx-planes-container">
              <?php
              // Filtrar planes disponibles según si es fundador
              /* Ya no hay planes solo para fundadores: el filtro que los
                 separaba se fue con el precio preferencial. Los dos planes que
                 quedan son los mismos para todos. */
              foreach ( $planes_config as $plan ) :
                  $es_actual  = $plan['id'] === $plan_actual && ! $is_vencido;
                  $precio_str = number_format( $plan['precio'], 0, ',', '.' ) . ' ' . $plan['moneda'] . '/' . $plan['intervalo'];
              ?>
              <div class="col-12 col-md-4">
                <div class="card-vx h-100">
                  <?php if ( isset( $plan['ahorro'] ) && $plan['ahorro'] > 0 ) : ?>
                  <span class="badge-vx badge-neutral mb-2" style="font-size:11px">Ahorra <?php echo $plan['ahorro']; ?> <?php echo $plan['moneda']; ?></span>
                  <?php endif; ?>
                  <h3 class="fw-semibold mb-1" style="font-size:16px"><?php echo esc_html( $plan['nombre'] ); ?></h3>
                  <p class="stat-num-vx stat-num-vx--primary" style="font-size:28px;margin:8px 0">
                    <?php echo number_format( $plan['precio'], 0, ',', '.' ); ?>
                    <span style="font-size:14px;color:var(--color-text-secondary)"><?php echo esc_html( $plan['moneda'] . '/' . $plan['intervalo'] ); ?></span>
                  </p>
                  <p class="text-body-muted mb-3" style="font-size:14px"><?php echo esc_html( $plan['descripcion'] ); ?></p>
                  <?php if ( $es_actual ) : ?>
                  <button class="btn-vx btn-ghost-vx btn-vx-sm w-100" disabled>Plan actual</button>
                  <?php else : ?>
                  <button class="btn-vx btn-primary-vx btn-vx-sm w-100 vx-plan-checkout-btn"
                          data-plan="<?php echo esc_attr( $plan['id'] ); ?>">
                    <i class="ti ti-credit-card me-1"></i>Suscribirme
                  </button>
                  <?php endif; ?>
                </div>
              </div>
              <?php endforeach; ?>
              <?php if ( empty( $planes_mostrar ) ) : ?>
              <div class="col-12">
                <div class="alert-vx alert-info">
                  <i class="ti ti-info-circle"></i>
                  <span>Los planes aún no están disponibles. Escríbenos a <a href="mailto:hola@vitrinexo.com">hola@vitrinexo.com</a> para coordinar tu suscripción.</span>
                </div>
              </div>
              <?php endif; ?>
            </div>
            <div id="vx-plan-msg" class="d-none mb-3"></div>
            <?php endif; ?>
          </div>

          <!-- COMUNIDADES -->
          <div id="section-comunidades" class="config-section" style="display:none">
            <div class="card-vx">
              <h2 class="section-title-sm mb-1">Mis comunidades</h2>
              <p class="cta-card__desc mb-4">Comunidades a las que perteneces en Vitrinexo.</p>
              <?php
              $coms_map = [
                  'out2b'  => [ 'Vitrinexo LGBTQ+',  'ti-rainbow',       'color:var(--lavanda-presion)' ],
                  'woman'  => [ 'Vitrinexo Woman',  'ti-gender-female', 'color:var(--rosa-presion)' ],
                  'senior' => [ 'Vitrinexo Senior', 'ti-award',         'color:var(--mantequilla-presion)' ],
              ];
              foreach ( $coms_map as $id => [ $nombre, $icon, $style ] ) :
                  $activa = $user->is_in_community( $id );
              ?>
              <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                <div class="d-flex align-items-center gap-2">
                  <i class="ti <?php echo esc_attr( $icon ); ?>" style="font-size:18px;<?php echo esc_attr( $style ); ?>"></i>
                  <span class="fw-semibold"><?php echo esc_html( $nombre ); ?></span>
                </div>
                <?php if ( $activa ) : ?>
                <span class="badge-vx badge-primary"><i class="ti ti-check me-1"></i>Activo</span>
                <?php else : ?>
                <span class="badge-vx badge-neutral">Inactivo</span>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
              <?php if ( ! $user->is_in_community( 'senior' ) ) : ?>
              <div class="mt-4 pt-3 border-top">
                <p class="cta-card__desc mb-0"><i class="ti ti-award me-1"></i>Vitrinexo Senior se asigna automáticamente a partir de 45 años o 20 de experiencia.</p>
              </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- ZONA DE PELIGRO -->
          <div id="section-peligro" class="config-section" style="display:none">
            <!-- Cambiar email -->
            <div class="card-vx mb-4" style="border-left:3px solid var(--color-accent)">
              <h2 class="section-title-sm mb-1">Cambiar email de cuenta</h2>
              <p class="cta-card__desc mb-3">
                Se envía un enlace al nuevo email. El cambio solo se aplica cuando confirmas desde ahí.
                Tu email actual es <strong><?php echo esc_html( $wp_user->user_email ); ?></strong>.
              </p>
              <div class="row g-3" style="max-width:480px">
                <div class="col-12">
                  <label class="form-label-vx">Nuevo email</label>
                  <input type="email" id="vx-danger-new-email" class="form-control-vx" placeholder="nuevo@email.com">
                </div>
                <div class="col-12">
                  <label class="form-label-vx">Contraseña actual <span class="form-hint d-inline">(para confirmar identidad)</span></label>
                  <input type="password" id="vx-danger-email-pass" class="form-control-vx" placeholder="Tu contraseña">
                </div>
              </div>
              <div id="vx-change-email-danger-msg" class="d-none mt-3 small"></div>
              <button type="button" class="btn-vx btn-primary-vx btn-vx-sm mt-3" id="vx-btn-change-email-danger">
                <i class="ti ti-send me-1"></i>Enviar enlace de confirmación
              </button>
            </div>

            <!-- Borrar cuenta -->
            <div class="card-vx" style="border-left:3px solid var(--rosa)">
              <h2 class="section-title-sm mb-1" style="color:var(--rosa-hover)">
                <i class="ti ti-trash me-2"></i>Eliminar mi cuenta
              </h2>
              <p class="cta-card__desc mb-3">
                Esta acción es <strong>permanente e irreversible</strong>. Se eliminará:
              </p>
              <ul class="text-body-muted mb-4" style="font-size:14px;padding-left:1.2rem;line-height:1.8">
                <li>Tu perfil y todos tus datos personales</li>
                <li>Tus empresas registradas en Vitrinexo</li>
                <li>Tu historial de conexiones y solicitudes</li>
                <li>Tus notificaciones e invitaciones</li>
                <li>Tu membresía y plan actual</li>
              </ul>
              <div class="row g-3" style="max-width:480px">
                <div class="col-12">
                  <label class="form-label-vx">
                    Escribe <strong>ELIMINAR</strong> para confirmar
                  </label>
                  <input type="text" id="vx-delete-confirm-text" class="form-control-vx"
                         placeholder="ELIMINAR" autocomplete="off" spellcheck="false">
                </div>
                <div class="col-12">
                  <label class="form-label-vx">Contraseña actual</label>
                  <input type="password" id="vx-delete-pass" class="form-control-vx" placeholder="Tu contraseña">
                </div>
              </div>
              <div id="vx-delete-msg" class="d-none mt-3 small"></div>
              <button type="button" class="btn-vx btn-vx-sm mt-3" id="vx-btn-delete-account"
                      style="background:var(--rosa);color:var(--blanco);border:none">
                <i class="ti ti-trash me-1"></i>Eliminar mi cuenta definitivamente
              </button>
              <p class="text-xs-muted mt-2">Si tienes dudas, escríbenos a hola@vitrinexo.com antes de proceder.</p>
            </div>
          </div>

        </div>
      </div>
    </div>
    </main>

    <script>
    function vxShowSection(id, el) {
      document.querySelectorAll('.config-section').forEach(function(s){ s.style.display='none'; });
      document.querySelectorAll('.config-nav-item').forEach(function(a){ a.classList.remove('config-nav-item--active'); });
      document.getElementById('section-'+id).style.display = '';
      if(el) el.classList.add('config-nav-item--active');
      return false;
    }
    // Auto-abrir tab Plan si viene de redirección por pago
    (function(){
      var params = new URLSearchParams(window.location.search);
      if (params.get('tab') === 'plan' || params.get('plan') || params.get('motivo')) {
        var planNav = document.querySelector('[href="#plan"].config-nav-item');
        vxShowSection('plan', planNav);
        // Quitar parámetros de URL sin recargar
        if (window.history && window.history.replaceState) {
          window.history.replaceState({}, '', window.location.pathname + '#plan');
        }
      }
    })();
    (function(){
      var epCheckout = <?php echo wp_json_encode( $ep_checkout ); ?>;
      var epPortal   = <?php echo wp_json_encode( $ep_portal ); ?>;
      var nonce      = <?php echo wp_json_encode( $nonce_rest ); ?>;

      // Botones de checkout
      document.querySelectorAll('.vx-plan-checkout-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
          var plan = this.dataset.plan;
          var msgEl = document.getElementById('vx-plan-msg');
          this.disabled = true;
          this.innerHTML = '<i class="ti ti-loader-2"></i> Procesando...';
          fetch(epCheckout, {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-WP-Nonce': nonce},
            body: JSON.stringify({plan: plan})
          }).then(r => r.json()).then(d => {
            if (d.success && d.checkout_url) {
              window.location.href = d.checkout_url; // redirect a Stripe
            } else {
              // Stripe no configurado aún — mostrar mensaje informativo
              var msg = d.message || 'El sistema de pagos se está configurando. Escríbenos a hola@vitrinexo.com';
              if (msgEl) { msgEl.textContent = msg; msgEl.className = 'mb-3 small text-body-muted'; }
              this.disabled = false;
              this.innerHTML = '<i class="ti ti-credit-card me-1"></i>Suscribirme';
            }
          }).catch(() => {
            if (msgEl) { msgEl.textContent='Error de conexión.'; msgEl.className='mb-3 small text-danger'; }
            this.disabled = false;
            this.innerHTML = '<i class="ti ti-credit-card me-1"></i>Suscribirme';
          });
        });
      });

      // Botón portal de Stripe
      var portalBtn = document.getElementById('vx-plan-portal-btn');
      if (portalBtn) {
        portalBtn.addEventListener('click', function() {
          this.disabled = true;
          this.textContent = 'Redirigiendo...';
          fetch(epPortal, {method:'POST', headers:{'Content-Type':'application/json','X-WP-Nonce':nonce}})
          .then(r => r.json()).then(d => {
            if (d.success && d.portal_url) {
              window.location.href = d.portal_url;
            } else {
              alert(d.message || 'Portal no disponible aún.');
              this.disabled = false;
              this.innerHTML = '<i class="ti ti-credit-card me-1"></i>Gestionar suscripción';
            }
          });
        });
      }
    })();
    (function(){
      var ep    = <?php echo wp_json_encode( $ep_pass ); ?>;
      var nonce = <?php echo wp_json_encode( $nonce ); ?>;
      var btn   = document.getElementById('vx-btn-cambiar-pass');
      if(!btn) return;
      btn.addEventListener('click', function(){
        var actual = document.getElementById('vx-pass-actual').value.trim();
        var nuevo  = document.getElementById('vx-pass-nuevo').value.trim();
        var msgEl  = document.getElementById('vx-pass-msg');
        msgEl.className='mb-3 d-none small';
        if(!actual||!nuevo){ msgEl.textContent='Completa ambos campos.'; msgEl.className='mb-3 small text-danger'; return; }
        if(nuevo.length<8){ msgEl.textContent='La nueva contraseña debe tener al menos 8 caracteres.'; msgEl.className='mb-3 small text-danger'; return; }
        this.disabled=true;
        fetch(ep,{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},body:JSON.stringify({password_actual:actual,password_nuevo:nuevo})})
        .then(r=>r.json()).then(d=>{
          if(d.success){ msgEl.textContent='¡Contraseña actualizada!'; msgEl.className='mb-3 small text-success'; document.getElementById('vx-pass-actual').value=''; document.getElementById('vx-pass-nuevo').value=''; }
          else{ var e2=d.data?.message||d.error||'Error al actualizar.'; if(d.error==='password_actual_incorrecta') e2='La contraseña actual es incorrecta.'; msgEl.textContent=e2; msgEl.className='mb-3 small text-danger'; }
          this.disabled=false;
        }).catch(()=>{ msgEl.textContent='Error de red.'; msgEl.className='mb-3 small text-danger'; this.disabled=false; });
      });
    })();

    // ── Cambio de email (dos formularios: en cuenta y en zona peligro) ───────
    (function(){
      var ep    = <?php echo wp_json_encode( rest_url( VX_REST_NAMESPACE . '/cuenta/cambiar-email' ) ); ?>;
      var nonce = <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;

      function bindEmailChange(btnId, emailInputId, passInputId, msgId) {
        var btn = document.getElementById(btnId);
        if (!btn) return;
        btn.addEventListener('click', function() {
          var email = document.getElementById(emailInputId).value.trim();
          var pass  = document.getElementById(passInputId).value.trim();
          var msgEl = document.getElementById(msgId);
          msgEl.className = 'd-none small';
          if (!email) { msgEl.textContent = 'Escribe el nuevo email.'; msgEl.className = 'mt-2 small text-danger'; return; }
          if (!pass)  { msgEl.textContent = 'Escribe tu contraseña actual.'; msgEl.className = 'mt-2 small text-danger'; return; }
          this.disabled = true;
          fetch(ep, {method:'POST', headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},
            body: JSON.stringify({email_nuevo: email, password_actual: pass})})
          .then(r=>r.json()).then(d=>{
            if (d.success) {
              msgEl.textContent = 'Enlace de confirmación enviado a ' + email + '. Revisa tu bandeja y haz clic para aplicar el cambio.';
              msgEl.className = 'mt-2 small text-success';
            } else {
              var e2 = d.data?.message || d.error || 'Error.';
              if (d.error==='password_incorrecta') e2 = 'La contraseña es incorrecta.';
              if (d.error==='email_en_uso')       e2 = 'Ese email ya está en uso por otra cuenta.';
              if (d.error==='email_invalido')     e2 = 'El formato del email no es válido.';
              msgEl.textContent = e2;
              msgEl.className = 'mt-2 small text-danger';
              this.disabled = false;
            }
          }).catch(()=>{ msgEl.textContent='Error de red.'; msgEl.className='mt-2 small text-danger'; this.disabled=false; });
        });
      }

      // Botón en sección Cuenta
      var showBtn = document.getElementById('vx-show-change-email');
      if (showBtn) showBtn.addEventListener('click', function(){ document.getElementById('vx-change-email-form').style.display=''; });
      bindEmailChange('vx-btn-change-email', 'vx-new-email', 'vx-email-pass', 'vx-change-email-msg');
      // Botón en Zona de peligro
      bindEmailChange('vx-btn-change-email-danger', 'vx-danger-new-email', 'vx-danger-email-pass', 'vx-change-email-danger-msg');
    })();

    // ── Borrar cuenta ─────────────────────────────────────────────────────────
    (function(){
      var ep    = <?php echo wp_json_encode( rest_url( VX_REST_NAMESPACE . '/cuenta/eliminar' ) ); ?>;
      var nonce = <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;
      var btn   = document.getElementById('vx-btn-delete-account');
      if (!btn) return;
      btn.addEventListener('click', function() {
        var confirmText = document.getElementById('vx-delete-confirm-text').value.trim();
        var pass        = document.getElementById('vx-delete-pass').value.trim();
        var msgEl       = document.getElementById('vx-delete-msg');
        msgEl.className = 'd-none small';
        if (confirmText !== 'ELIMINAR') { msgEl.textContent = 'Escribe ELIMINAR (en mayúsculas) para confirmar.'; msgEl.className = 'mt-2 small text-danger'; return; }
        if (!pass) { msgEl.textContent = 'Escribe tu contraseña actual.'; msgEl.className = 'mt-2 small text-danger'; return; }
        if (!confirm('¿Estás completamente seguro? Esta acción no se puede deshacer.')) return;
        this.disabled = true;
        this.textContent = 'Eliminando...';
        fetch(ep, {method:'POST', headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},
          body: JSON.stringify({password_actual: pass, confirmacion: confirmText})})
        .then(r=>r.json()).then(d=>{
          if (d.success) {
            msgEl.textContent = 'Cuenta eliminada. Redirigiendo...';
            msgEl.className = 'mt-2 small text-success';
            setTimeout(()=>{ window.location.href = <?php echo wp_json_encode( home_url( '/' ) ); ?>; }, 1500);
          } else {
            var e2 = d.data?.message || d.error || 'Error al eliminar.';
            if (d.error === 'password_incorrecta') e2 = 'La contraseña es incorrecta.';
            msgEl.textContent = e2;
            msgEl.className = 'mt-2 small text-danger';
            this.disabled = false;
            this.textContent = 'Eliminar mi cuenta definitivamente';
          }
        }).catch(()=>{ msgEl.textContent='Error de red.'; msgEl.className='mt-2 small text-danger'; this.disabled=false; this.textContent='Eliminar mi cuenta definitivamente'; });
      });
    })();
    </script>
    <?php
    return ob_get_clean();
} );

// [vx_perfil] — perfil público/privado de un miembro
add_shortcode( 'vx_perfil', function (): string {
    $slug   = get_query_var( 'vx_perfil_slug' );
    $viewer = get_current_user_id();

    // Los perfiles son dinámicos (bloqueos + gating por sesión): nunca cachear.
    do_action( 'litespeed_control_set_nocache', 'Vitrinexo: perfil dinámico' );

    if ( ! $slug ) {
        return '<div class="container py-5"><p class="text-muted">Perfil no encontrado.</p></div>';
    }

    $users = get_users( [
        'meta_key'   => VX_User_Meta::PERFIL_SLUG,
        'meta_value' => sanitize_title( $slug ),
        'number'     => 1,
        'fields'     => 'ids',
    ] );

    if ( empty( $users ) ) {
        return '<div class="container py-5"><p class="text-muted">Perfil no encontrado.</p></div>';
    }

    $user_id     = (int) $users[0];
    $user        = VX_User::get( $user_id );
    $is_owner    = $viewer && $viewer === $user_id;
    $is_wp_admin = $viewer && current_user_can( 'manage_options' );

    if ( ! $user ) {
        return '<div class="container py-5"><p class="text-muted">Perfil no encontrado.</p></div>';
    }

    if ( ! $user->is_active() && ! $is_owner && ! $is_wp_admin ) {
        return '<div class="container py-5"><p class="text-muted">Este perfil no está disponible.</p></div>';
    }

    // Bloqueo: si hay relación de bloqueo (en cualquier dirección) no se puede ver el perfil.
    if ( ! $is_owner && ! $is_wp_admin && function_exists( 'vx_hay_bloqueo' ) && vx_hay_bloqueo( $viewer, $user_id ) ) {
        return '<div class="container py-5"><p class="text-muted">Este perfil no está disponible.</p></div>';
    }

    // Gating del embudo: los perfiles son para miembros. Un visitante sin sesión
    // ve un CTA para iniciar sesión o inscribirse (no el perfil completo).
    if ( ! $viewer ) {
        $nombre_pub = esc_html( $user->get_nombre_completo() );
        $login_u    = esc_url( home_url( '/login/' ) );
        ob_start();
        ?>
        <div class="container py-5" style="max-width:540px">
          <div class="card-vx text-center" style="padding:36px 28px">
            <?php echo get_avatar( $user_id, 76, '', $nombre_pub, [ 'style' => 'width:76px;height:76px;border-radius:var(--radius-md);object-fit:cover;margin:0 auto 1rem' ] ); ?>
            <h2 style="font-size:1.3rem;font-weight:700;color:var(--color-text-primary);margin:0 0 .5rem">Para ver el perfil de <?php echo $nombre_pub; ?></h2>
            <p class="text-body-muted" style="margin-bottom:1.25rem">Los perfiles de Vitrinexo son para miembros. Inicia sesión o inscríbete para conectar con la comunidad.</p>
            <div class="d-flex gap-2 justify-content-center flex-wrap">
              <a href="<?php echo $login_u; ?>" class="btn-vx btn-ghost-vx btn-vx-sm">Ingresar</a>
              <a href="<?php echo $login_u; ?>" class="btn-vx btn-primary-vx btn-vx-sm">Inscríbete</a>
            </div>
          </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // Disparar visita
    if ( $viewer && $viewer !== $user_id ) {
        do_action( 'vx_profile_visited', $user_id, $viewer );
    }

    // Estado del botón conectar
    $btn_state = 'none';
    if ( $viewer && $viewer !== $user_id ) {
        $btn_state = 'disponible';
        $conexion  = VX_Connection::get_between( $viewer, $user_id );
        if ( $conexion ) {
            $est = $conexion->get_estado();
            if ( 'aceptado' === $est ) {
                $btn_state = 'conectado';
            } elseif ( 'pendiente' === $est ) {
                $btn_state = $conexion->get_emisor_id() === $viewer ? 'enviada' : 'recibida';
            }
        }
    }

    $empresas         = $user->get_empresas();
    $conexion_actual  = ( $viewer && $viewer !== $user_id ) ? VX_Connection::get_between( $viewer, $user_id ) : null;
    $contacto_visible = $conexion_actual && 'aceptado' === $conexion_actual->get_estado();

    // Favorito
    $viewer_obj = $viewer ? VX_User::get( $viewer ) : null;
    $is_fav     = $viewer_obj && in_array( $user_id, (array) $viewer_obj->get_favoritos(), true );

    // Banner: usar banner de la primera empresa
    $empresa_activa = $user->get_empresa_activa();
    $banner_url     = '';
    $banner_id      = $empresa_activa ? get_post_meta( $empresa_activa->ID, 'vx_banner', true ) : '';
    if ( $banner_id ) {
        $banner_url = wp_get_attachment_image_url( $banner_id, 'full' ) ?: '';
    }

    // Cargo del usuario en empresa activa
    $cargo_activo = $empresa_activa ? (string) get_post_meta( $empresa_activa->ID, 'vx_cargo', true ) : '';

    $offer_texto = (string) get_user_meta( $user_id, VX_User_Meta::OFFER_TEXTO, true );
    $seek_texto  = (string) get_user_meta( $user_id, VX_User_Meta::SEEK_TEXTO,  true );

    ob_start();
    ?>
<main>
<div class="container">
  <div class="profile my-5">

    <!-- HEADER -->
    <div class="profile-top">

      <!-- Acciones (favorito + conectar) -->
      <div class="profile-actions">
        <?php if ( $viewer && $viewer !== $user_id ) : ?>
        <button class="btn-vx btn-soft-accent btn-vx-sm btn-vx-icon-sm vx-fav-btn"
                data-user-id="<?php echo $user_id; ?>"
                data-activo="<?php echo $is_fav ? '1' : '0'; ?>"
                <?php if ( $is_fav ) echo 'style="color:var(--color-text-primary)"'; ?>
                aria-label="<?php echo $is_fav ? 'Quitar de favoritos' : 'Guardar en favoritos'; ?>">
          <i class="ti <?php echo $is_fav ? 'ti-heart-filled' : 'ti-heart'; ?>"></i>
        </button>
        <?php endif; ?>

        <?php if ( 'disponible' === $btn_state ) : ?>
        <button class="btn-vx btn-soft-primary btn-vx-sm" data-bs-toggle="modal" data-bs-target="#modalConectar" data-receptor-id="<?php echo $user_id; ?>">
          <i class="ti ti-send"></i> Conectar
        </button>
        <?php elseif ( 'enviada' === $btn_state ) : ?>
        <button class="btn-vx btn-ghost-vx btn-vx-sm" disabled><i class="ti ti-clock-hour-4 me-1"></i>Solicitud enviada</button>
        <?php elseif ( 'recibida' === $btn_state ) : ?>
        <a href="<?php echo esc_url( home_url( '/conexiones/' ) ); ?>" class="btn-vx btn-soft-primary btn-vx-sm"><i class="ti ti-inbox me-1"></i>Ver solicitud</a>
        <?php elseif ( 'conectado' === $btn_state ) : ?>
        <button class="btn-vx btn-ghost-vx btn-vx-sm" disabled><i class="ti ti-circle-check me-1 ic-success"></i>Conectados</button>
        <?php endif; ?>

        <?php if ( $is_owner ) : ?>
        <a href="<?php echo esc_url( home_url( '/editar-perfil/' ) ); ?>" class="btn-vx btn-ghost-vx btn-vx-sm"><i class="ti ti-pencil me-1"></i>Editar perfil</a>
        <?php endif; ?>
      </div>

      <!-- Avatar + info -->
      <div class="profile-row">
        <div class="img-profile">
          <img src="<?php echo esc_url( $user->get_foto_url( 'vx-avatar' ) ); ?>" alt="<?php echo esc_attr( $user->get_nombre_completo() ); ?>">
        </div>
        <div class="profile-info">
          <h1 class="profile-name">
            <?php echo esc_html( $user->get_nombre_completo() ); ?>
            <?php if ( $user->is_founder() ) : ?>
            <span class="founder-tooltip ms-1" data-tooltip="Miembro Pionero" tabindex="0"><i class="founder-tag ti ti-star"></i></span>
            <?php endif; ?>
            <?php if ( $user->is_senior_verified() ) : ?>
            <span class="badge-vx badge-neutral ms-1" style="font-size:11px">Vitrinexo Senior</span>
            <?php endif; ?>
          </h1>

          <?php if ( $empresa_activa ) : ?>
          <?php $emp_web_link = get_post_meta( $empresa_activa->ID, 'vx_web', true ); ?>
          <?php if ( $emp_web_link ) : ?>
          <a href="<?php echo esc_url( $emp_web_link ); ?>" class="profile-company" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $empresa_activa->post_title ); ?></a>
          <?php else : ?>
          <span class="profile-company"><?php echo esc_html( $empresa_activa->post_title ); ?></span>
          <?php endif; ?>
          <?php endif; ?>

          <?php if ( $cargo_activo ) : ?>
          <p class="profile-title"><?php echo esc_html( $cargo_activo ); ?></p>
          <?php endif; ?>

          <?php
          // Industria principal del usuario (de su empresa activa)
          $industria_user = $user->get_industria();
          if ( $industria_user ) :
          ?>
          <p class="text-body-muted" style="font-size:13px;margin:2px 0 4px">
            <i class="ti ti-briefcase me-1" style="font-size:12px;color:var(--color-primary-ink)"></i><?php echo esc_html( $industria_user ); ?>
          </p>
          <?php endif; ?>

          <?php $ciudad = $user->get_ciudad(); $pais = $user->get_pais(); $pais_codigo = $user->get_pais_codigo(); ?>
          <?php if ( $ciudad || $pais ) : ?>
          <p class="profile-location">
            <i class="ti ti-map-pin"></i>
            <?php echo esc_html( $ciudad ?: $pais ); ?>
            <?php if ( $pais_codigo ) : ?>
            <span class="profile-country-chip"><?php echo esc_html( $pais_codigo ); ?></span>
            <?php endif; ?>
          </p>
          <?php endif; ?>

          <!-- Comunidades activas (solo visibles si el visitante comparte la comunidad) -->
          <?php
          $coms_activas = $user->get_comunidades_activas();
          // Las etiquetas de comunidad son privadas: solo las ve quien pertenece a la
          // misma comunidad. El dueño del perfil y los admins ven todas.
          if ( ! $is_owner && ! $is_wp_admin ) {
              $viewer_com = ( $viewer && class_exists( 'VX_User' ) ) ? VX_User::get( $viewer ) : null;
              if ( $viewer_com ) {
                  $coms_activas = array_values( array_filter( $coms_activas, function ( $c ) use ( $viewer_com ) {
                      return $viewer_com->is_in_community( $c );
                  } ) );
              } else {
                  $coms_activas = [];
              }
          }
          if ( $coms_activas ) :
            // Cada pastilla va en su familia: el -suave de fondo y el -tinta
            // encima, que es el peldaño calculado para dar 4,5 sobre ese
            // fondo. Antes decía `var(--lavanda)22`, pegándole la opacidad a
            // un var(), que no es CSS válido: el fondo se caía y el texto
            // quedaba en lavanda sobre blanco, 1,64:1. Nadie lo había visto
            // porque el perfil no se medía.
            $com_labels = [
              'out2b'  => [ 'Vitrinexo LGBTQ+', 'lavanda' ],
              'woman'  => [ 'Vitrinexo Woman',  'rosa' ],
              'senior' => [ 'Vitrinexo Senior', 'mantequilla' ],
            ];
          ?>
          <div class="d-flex gap-1 flex-wrap mt-2">
            <?php foreach ( $coms_activas as $com ) :
              [ $lbl, $fam ] = $com_labels[ $com ] ?? [ ucfirst($com), '' ];
              $fondo  = $fam ? "var(--{$fam}-suave)" : 'var(--blanco)';
              $tinta  = $fam ? "var(--{$fam}-tinta)" : 'var(--color-text-primary)';
              $borde  = $fam ? "var(--{$fam})"       : 'var(--linea)';
            ?>
            <span style="background:<?php echo esc_attr( $fondo ); ?>;color:<?php echo esc_attr( $tinta ); ?>;border:1px solid <?php echo esc_attr( $borde ); ?>;border-radius:999px;font-size:11px;font-weight:600;padding:2px 10px"><?php echo esc_html( $lbl ); ?></span>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- TAGS ROW — tags independientes del perfil -->
    <?php $profile_tags = $user->get_profile_tags(); if ( $profile_tags ) : ?>
    <div class="profile-tags-row">
      <?php foreach ( $profile_tags as $tag ) : ?>
      <span class="tag-vx"><?php echo esc_html( $tag ); ?></span>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- CONTENT -->
    <div class="profile-content">

      <!-- Bio / Sobre mí -->
      <?php if ( $user->get_bio() ) : ?>
      <div class="profile-section">
        <h2 class="profile-section-title">Sobre mí</h2>
        <p><?php echo nl2br( esc_html( $user->get_bio() ) ); ?></p>
      </div>
      <?php endif; ?>

      <!-- Tabs de empresa (si hay más de una, mostrar tabs; si hay una sola, mostrar directamente) -->
      <?php if ( count( $empresas ) > 1 ) : ?>
      <div class="profile-tabs">
        <?php foreach ( $empresas as $idx => $emp ) : ?>
        <button class="profile-tab <?php echo 0 === $idx ? 'profile-tab-active' : ''; ?>"
                onclick="vxSwitchProfileTab(<?php echo $idx; ?>)">
          <?php echo esc_html( $emp->post_title ); ?>
        </button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php foreach ( $empresas as $idx => $emp ) :
        $emp_logo_id   = get_post_meta( $emp->ID, 'vx_logo', true );
        $emp_logo_url  = $emp_logo_id ? wp_get_attachment_image_url( $emp_logo_id, 'vx-logo' ) : '';
        $emp_cargo     = (string) get_post_meta( $emp->ID, 'vx_cargo',                true );
        $emp_web       = (string) get_post_meta( $emp->ID, 'vx_web',                  true );
        $emp_linkedin  = (string) get_post_meta( $emp->ID, 'vx_linkedin',             true );
        $emp_desc      = (string) get_post_meta( $emp->ID, 'vx_descripcion',          true );
        $emp_cliente   = (string) get_post_meta( $emp->ID, 'vx_descripcion_cliente',  true );
        $emp_industria = (string) get_post_meta( $emp->ID, 'vx_industria',            true );
        $emp_sector    = (string) get_post_meta( $emp->ID, 'vx_sector',               true );
        $logo_initial  = strtoupper( mb_substr( $emp->post_title, 0, 1 ) );
        // Sector: convertir string CSV a array
        $emp_sector_arr = $emp_sector
            ? array_values( array_filter( array_map( 'trim', explode( ',', $emp_sector ) ) ) )
            : [];
      ?>
      <div class="profile-company-block" id="company-block-<?php echo $idx; ?>"
           <?php if ( $idx > 0 ) echo 'style="display:none"'; ?>>
        <div class="profile-company-card">
          <div class="profile-company-logo">
            <?php if ( $emp_logo_url ) : ?>
            <img src="<?php echo esc_url( $emp_logo_url ); ?>" alt="<?php echo esc_attr( $emp->post_title ); ?>" style="width:100%;height:100%;object-fit:contain;border-radius:var(--radius-sm)">
            <?php else : ?>
            <span class="profile-company-logo-initial"><?php echo esc_html( $logo_initial ); ?></span>
            <?php endif; ?>
          </div>
          <div class="profile-company-meta">
            <div class="profile-company-meta-name"><?php echo esc_html( $emp->post_title ); ?></div>
            <?php if ( $emp_cargo ) : ?>
            <div class="profile-company-meta-title"><?php echo esc_html( $emp_cargo ); ?></div>
            <?php endif; ?>
            <?php if ( $emp_industria ) : ?>
            <div style="font-size:12px;color:var(--color-primary-ink);font-weight:500;margin:3px 0">
              <i class="ti ti-briefcase me-1" style="font-size:11px"></i><?php echo esc_html( $emp_industria ); ?>
            </div>
            <?php endif; ?>
            <?php if ( $emp_sector_arr ) : ?>
            <div class="d-flex flex-wrap gap-1 mt-1 mb-1">
              <?php foreach ( $emp_sector_arr as $rubro ) : ?>
              <span class="tag-vx" style="font-size:11px"><?php echo esc_html( $rubro ); ?></span>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="profile-company-meta-links">
              <?php if ( $emp_web ) : ?>
              <a href="<?php echo esc_url( $emp_web ); ?>" class="profile-company-link" target="_blank" rel="noopener noreferrer">
                <i class="ti ti-world"></i> <?php echo esc_html( preg_replace( '#^https?://#', '', rtrim( $emp_web, '/' ) ) ); ?>
              </a>
              <?php endif; ?>
              <?php if ( $emp_linkedin ) : ?>
              <a href="<?php echo esc_url( $emp_linkedin ); ?>" class="profile-company-link" target="_blank" rel="noopener noreferrer">
                <i class="ti ti-brand-linkedin"></i> LinkedIn
              </a>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <?php if ( $emp_desc ) : ?>
        <div class="profile-section">
          <h2 class="profile-section-title">Sobre <?php echo esc_html( $emp->post_title ); ?></h2>
          <p><?php echo nl2br( esc_html( $emp_desc ) ); ?></p>
        </div>
        <?php endif; ?>

        <?php if ( $emp_cliente ) : ?>
        <div class="profile-section">
          <h2 class="profile-section-title">Cliente ideal</h2>
          <p><?php echo nl2br( esc_html( $emp_cliente ) ); ?></p>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>

    </div><!-- /.profile-content -->

    <!-- OFFER / SEEK PILLS -->
    <?php if ( $user->get_offer_tags() || $offer_texto || $user->get_seek_tags() || $seek_texto ) : ?>
    <div class="profile-duo">
      <div class="profile-pill profile-pill-offer">
        <h3 class="profile-pill-title"><i class="ti ti-circle-check"></i> Qué ofrezco</h3>
        <?php if ( $offer_texto ) : ?><p><?php echo esc_html( $offer_texto ); ?></p><?php endif; ?>
        <?php if ( $user->get_offer_tags() ) : ?>
        <div class="profile-pill-tags">
          <?php foreach ( $user->get_offer_tags() as $tag ) : ?>
          <span class="tag-vx tag-offers"><?php echo esc_html( $tag ); ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <div class="profile-pill profile-pill-seek">
        <h3 class="profile-pill-title"><i class="ti ti-search"></i> Qué busco</h3>
        <?php if ( $seek_texto ) : ?><p><?php echo esc_html( $seek_texto ); ?></p><?php endif; ?>
        <?php if ( $user->get_seek_tags() ) : ?>
        <div class="profile-pill-tags">
          <?php foreach ( $user->get_seek_tags() as $tag ) : ?>
          <span class="tag-vx tag-seeks"><?php echo esc_html( $tag ); ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Datos de contacto (solo si conectados) — muestra todos, preferido destacado -->
    <?php if ( $contacto_visible ) :
      $contacto_pref_perfil = $user->get_contacto_preferido() ?: 'email';
    ?>
    <div class="card-vx mt-5">
      <h2 class="section-title-sm mb-3"><i class="ti ti-address-book me-2 ic-success"></i>Datos de contacto</h2>
      <?php echo vx_render_contact_links( $user, $contacto_pref_perfil ); ?>
    </div>
    <?php endif; ?>

    <?php
    // ── Publicaciones del feed ──────────────────────────────────────────────
    $pubs = new WP_Query( [
        'post_type'      => 'vx_publicacion',
        'post_status'    => 'publish',
        'author'         => $user->get_id(),
        'posts_per_page' => 10,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ] );
    if ( $pubs->have_posts() ) :
    ?>
    <div class="card-vx mt-5">
      <h2 class="section-title-sm mb-4"><i class="ti ti-layout-board me-2"></i>Publicaciones en Oportunidades</h2>
      <div class="vx-profile-feed">
        <?php while ( $pubs->have_posts() ) : $pubs->the_post();
          echo vx_render_pub_card( get_post(), (int) get_current_user_id(), true );
        endwhile; wp_reset_postdata(); ?>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /.profile -->
</div><!-- /.container -->
</main>

    <?php if ( count( $empresas ) > 1 ) : ?>
    <script>
    function vxSwitchProfileTab(idx) {
      document.querySelectorAll('.profile-tab').forEach(function(b,i){ b.classList.toggle('profile-tab-active', i===idx); });
      document.querySelectorAll('.profile-company-block').forEach(function(b,i){ b.style.display = i===idx ? '' : 'none'; });
    }
    </script>
    <?php endif; ?>

    <?php if ( 'disponible' === $btn_state && $viewer ) : ?>
    <?php echo vx_modal_conectar_html(); ?>
    <?php endif; ?>

    <?php
    return ob_get_clean();
} );

// [vx_editor_perfil] — editor del propio perfil
add_shortcode( 'vx_editor_perfil', function (): string {
    $user_id = get_current_user_id();
    $user    = VX_User::get( $user_id );
    if ( ! $user ) return '';

    $empresas        = $user->get_empresas();
    $nombre_completo = trim( $user->get_nombre() . ' ' . $user->get_apellido() );
    $foto_url        = $user->get_foto_url( 'vx-avatar' );
    $offer_tags      = $user->get_offer_tags() ?: [];
    $seek_tags       = $user->get_seek_tags() ?: [];
    $offer_texto     = (string) get_user_meta( $user_id, VX_User_Meta::OFFER_TEXTO, true );
    $seek_texto      = (string) get_user_meta( $user_id, VX_User_Meta::SEEK_TEXTO,  true );
    $is_out2b        = (bool)   get_user_meta( $user_id, VX_User_Meta::COMUNIDAD_OUT2B, true );
    $is_senior       = (bool)   get_user_meta( $user_id, VX_User_Meta::COMUNIDAD_SENIOR, true );
    $current_pais    = $user->get_pais();
    $current_industria = $user->get_industria();
    $current_profile_tags = $user->get_profile_tags();
    $paises          = vx_get_paises_latam();
    $industrias      = vx_get_industrias();
    $tags_preset     = vx_get_tags_preset();
    $propuesta       = function_exists( 'vx_get_pending_profile_proposal' ) ? vx_get_pending_profile_proposal( $user_id ) : null;
    $tiene_propuesta = null !== $propuesta;

    // Pre-compute sector tags per empresa for JS
    $sector_tags_map = [];
    foreach ( $empresas as $emp ) {
        $sector = (string) get_post_meta( $emp->ID, 'vx_sector', true );
        $sector_tags_map[ (string) $emp->ID ] = $sector
            ? array_values( array_filter( array_map( 'trim', explode( ',', $sector ) ) ) )
            : [];
    }

    ob_start();
    ?>
    <div class="container py-4">

      <!-- Título + Guardar -->
      <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
          <h1 class="fw-bold mb-1 page-title-vx">Editar perfil</h1>
          <p class="text-md-muted mb-0">Los cambios se reflejarán en el directorio inmediatamente</p>
        </div>
        <button type="button" class="btn-vx btn-primary-vx btn-vx-md" id="vx-btn-guardar-top">
          <i class="ti ti-check me-1"></i>Guardar cambios
        </button>
      </div>

      <!-- Alertas -->
      <div class="alert-vx alert-success d-flex align-items-center gap-3 mb-4 d-none" id="vx-alert-ok">
        <i class="ti ti-circle-check" style="font-size:20px;flex-shrink:0"></i>
        <span>Cambios guardados correctamente.</span>
        <button type="button" onclick="document.getElementById('vx-alert-ok').classList.add('d-none')" class="btn-alert-close">&times;</button>
      </div>
      <div class="alert-vx alert-error d-flex align-items-center gap-3 mb-4 d-none" id="vx-alert-err">
        <i class="ti ti-alert-circle" style="font-size:20px;flex-shrink:0"></i>
        <span id="vx-alert-err-msg">Error al guardar.</span>
        <button type="button" onclick="document.getElementById('vx-alert-err').classList.add('d-none')" class="btn-alert-close">&times;</button>
      </div>

      <?php $propuesta_error = isset( $_GET['vx_propuesta_error'] ) ? sanitize_key( wp_unslash( $_GET['vx_propuesta_error'] ) ) : ''; ?>
      <?php if ( $propuesta_error ) : ?>
      <div class="alert-vx alert-error mb-4">
        <i class="ti ti-alert-circle" style="font-size:20px;flex-shrink:0"></i>
        <span>
          <?php if ( 'no_autorizado' === $propuesta_error ) : ?>
          Esa propuesta no te pertenece.
          <?php else : ?>
          Ese link ya no es válido — puede que la propuesta ya haya sido respondida o reemplazada por una más nueva.
          <?php endif; ?>
        </span>
      </div>
      <?php endif; ?>

      <?php if ( $tiene_propuesta ) : ?>
      <div class="alert-vx alert-info mb-4" id="vx-propuesta-banner">
        <div>
          <strong>Un admin de Vitrinexo sugiere actualizar tu perfil:</strong>
          <?php
          $propuesta_campos = (array) ( $propuesta['campos'] ?? [] );
          foreach ( VX_Profile_Fields::definitions() as $def ) :
              if ( ! array_key_exists( $def['post_key'], $propuesta_campos ) ) continue;
              $valor = $propuesta_campos[ $def['post_key'] ];
              $texto = is_array( $valor ) ? implode( ', ', $valor ) : (string) $valor;
              if ( '' === $texto ) continue;
          ?>
          <div><?php echo esc_html( $def['label'] ); ?>: <?php echo esc_html( $texto ); ?></div>
          <?php endforeach; ?>
        </div>
        <div class="mt-2 d-flex gap-2">
          <button type="button" class="btn-vx btn-primary-vx btn-vx-sm" onclick="vxResolverPropuesta('aceptar')">Aceptar</button>
          <button type="button" class="btn-vx btn-danger-vx btn-vx-sm" onclick="vxResolverPropuesta('rechazar')">Rechazar</button>
        </div>
      </div>
      <?php endif; ?>

      <form id="vx-editor-form" novalidate>

        <!-- ========== SECCIÓN PERSONA ========== -->
        <div class="modal-vx profile-editor-card mb-4 p-0 overflow-hidden">
          <div class="card-section-header">
            <h4 class="fw-semibold mb-0" style="font-size:1rem">
              <i class="ti ti-user me-2 ic-success"></i>Información personal
            </h4>
          </div>
          <div class="card-section-body">

            <!-- Foto -->
            <div class="d-flex align-items-center gap-3 mb-4 pb-4 border-bottom-vx">
              <div id="vx-foto-preview" style="flex-shrink:0">
                <img src="<?php echo esc_url( $foto_url ); ?>" alt="Foto"
                     style="width:60px;height:60px;border-radius:var(--radius-sm);object-fit:cover;border:2px solid var(--color-border)">
              </div>
              <div>
                <p class="fw-semibold mb-1" style="font-size:14px"><?php echo esc_html( $nombre_completo ); ?></p>
                <label for="vx-foto-input" class="btn-vx btn-ghost-vx btn-vx-sm" style="cursor:pointer">
                  <i class="ti ti-camera me-1"></i>Cambiar foto
                </label>
                <input type="file" id="vx-foto-input" accept="image/jpeg,image/png,image/webp" class="d-none" data-upload-type="foto" data-upload-container="editor-foto-wrap">
                <p style="font-size:11px;color:var(--color-text-secondary);margin:4px 0 0"><i class="ti ti-info-circle" style="font-size:10px"></i> Máx. 15 MB · JPG, PNG o WebP · Se convierte automáticamente a WebP</p>
                <p style="font-size:11px;color:var(--color-primary-ink);margin:2px 0 0"><i class="ti ti-crop" style="font-size:10px"></i> Usa una imagen cuadrada con tu foto centrada para que se vea bien en el círculo.</p>
                <div class="vx-upload-progress d-none" style="margin-top:6px;min-width:180px">
                  <div style="height:3px;background:var(--color-border);border-radius:2px;overflow:hidden">
                    <div class="vx-progress-fill" style="height:100%;width:0%;background:var(--color-primary);transition:width 0.15s ease;border-radius:2px"></div>
                  </div>
                  <span class="vx-progress-label" style="font-size:11px;color:var(--color-text-secondary);margin-top:2px;display:block">Subiendo...</span>
                </div>
              </div>
            </div>

            <!-- Nombre + Apellido -->
            <div class="row g-3 mb-4 pb-4 border-bottom-vx">
              <div class="col-md-6">
                <label class="form-label-vx">Nombre *</label>
                <input type="text" name="nombre" class="form-control-vx"
                       value="<?php echo esc_attr( $user->get_nombre() ); ?>"
                       placeholder="Tu nombre" required>
              </div>
              <div class="col-md-6">
                <label class="form-label-vx">Apellido *</label>
                <input type="text" name="apellido" class="form-control-vx"
                       value="<?php echo esc_attr( $user->get_apellido() ); ?>"
                       placeholder="Tu apellido" required>
              </div>
              <div class="col-12">
                <p class="text-sm-muted" style="margin-top:2px;font-size:12px">
                  <i class="ti ti-info-circle me-1"></i>Si cambias tu nombre, tu URL de perfil también cambiará (los links anteriores dejarán de funcionar).
                </p>
              </div>
            </div>

            <!-- Bio -->
            <div class="mb-4 pb-4 border-bottom-vx">
              <label class="form-label-vx">Bio profesional <span class="text-sm-muted">(máx. 1500 caracteres)</span></label>
              <textarea name="bio" class="form-control-vx" rows="5" maxlength="1500"><?php echo esc_textarea( $user->get_bio() ); ?></textarea>
            </div>

            <!-- Ciudad + País -->
            <?php
            $ep_pais   = $current_pais;
            $ep_ciudad = $user->get_ciudad();
            $ep_ciudades_pais = vx_get_ciudades_por_pais()[ $ep_pais ] ?? [];
            // Si la ciudad actual no está en la lista, la agrego como primera opción (migración)
            $ep_ciudad_en_lista = in_array( $ep_ciudad, $ep_ciudades_pais, true );
            ?>
            <div class="row g-3 mb-4 pb-4 border-bottom-vx">
              <div class="col-md-6">
                <label class="form-label-vx">País</label>
                <select name="pais" id="ep-pais" class="form-control-vx">
                  <option value="">Selecciona tu país</option>
                  <?php foreach ( vx_get_paises_latam() as $p ) : ?>
                  <option value="<?php echo esc_attr( $p ); ?>" <?php selected( $ep_pais, $p ); ?>><?php echo esc_html( $p ); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label-vx">Ciudad</label>
                <?php $ep_ciudad_es_otra = $ep_ciudad && ! $ep_ciudad_en_lista; ?>
                <select name="ciudad" id="ep-ciudad" class="form-control-vx">
                  <?php if ( ! $ep_pais ) : ?>
                  <option value="">Selecciona primero el país</option>
                  <?php else : ?>
                    <option value="">Selecciona tu ciudad</option>
                    <?php foreach ( $ep_ciudades_pais as $c ) : ?>
                    <option value="<?php echo esc_attr( $c ); ?>" <?php selected( $ep_ciudad_es_otra ? '' : $ep_ciudad, $c ); ?>><?php echo esc_html( $c ); ?></option>
                    <?php endforeach; ?>
                    <option value="__otra__" <?php echo $ep_ciudad_es_otra ? 'selected' : ''; ?>>Otra ciudad...</option>
                  <?php endif; ?>
                </select>
                <input type="text" id="ep-ciudad-custom" class="form-control-vx mt-2"
                       placeholder="Escribe tu ciudad"
                       value="<?php echo $ep_ciudad_es_otra ? esc_attr( $ep_ciudad ) : ''; ?>"
                       style="<?php echo $ep_ciudad_es_otra ? '' : 'display:none'; ?>">
              </div>
            </div>

            <!-- Teléfono + LinkedIn -->
            <div class="row g-3 mb-4 pb-4 border-bottom-vx">
              <div class="col-md-6">
                <label class="form-label-vx">Teléfono <span class="text-sm-muted">(incluye prefijo de país)</span></label>
                <?php echo vx_phone_input_html( 'editor-telefono', 'telefono', $user->get_telefono() ); ?>
              </div>
              <div class="col-md-6">
                <label class="form-label-vx">LinkedIn</label>
                <div class="input-group-vx">
                  <span class="input-icon"><i class="ti ti-brand-linkedin"></i></span>
                  <input type="text" name="linkedin" class="form-control-vx vx-linkedin-input"
                         value="<?php echo esc_attr( $user->get_linkedin() ); ?>"
                         placeholder="https://linkedin.com/in/tu-nombre"
                         autocomplete="off"
                         spellcheck="false">
                </div>
              </div>
            </div>

            <!-- Tags de perfil (aparecen bajo el nombre en la ficha pública) -->
            <div class="mb-4 pb-4 border-bottom-vx">
              <label class="form-label-vx">Tags de perfil <span class="text-sm-muted">(aparecen en tu ficha pública)</span></label>
              <p class="text-sm-muted mb-2">Palabras clave que te describen. Selecciona o escribe los tuyos.</p>
              <div class="tag-selector mb-2" id="vx-profile-tags-suggestions">
                <?php foreach ( $tags_preset as $tag ) :
                    $sel = in_array( $tag, $current_profile_tags, true );
                ?>
                <span class="tag-option <?php echo $sel ? 'tag-option--selected-profile' : ''; ?>"
                      onclick="vxToggleProfileTag(this)"><?php echo esc_html( $tag ); ?></span>
                <?php endforeach; ?>
              </div>
              <div class="d-flex flex-wrap gap-2 mb-2" id="vx-profile-tags-custom"></div>
            </div>

            <!-- Tags que ofreces -->
            <div class="mb-4 pb-4 border-bottom-vx">
              <label class="form-label-vx">Tags de lo que ofreces</label>
              <div class="tag-selector mb-2" id="vx-offer-tags-suggestions">
                <?php foreach ( $tags_preset as $tag ) :
                    $sel = in_array( $tag, $user->get_offer_tags(), true );
                ?>
                <span class="tag-option <?php echo $sel ? 'tag-option--selected-offer' : ''; ?>"
                      onclick="vxToggleSuggestionTag(this,'offer')"><?php echo esc_html( $tag ); ?></span>
                <?php endforeach; ?>
              </div>
              <div class="d-flex flex-wrap gap-2 mb-2" id="vx-offer-tags-container"></div>
              <label class="form-label-vx">Descripción de tu oferta</label>
              <textarea name="offer_texto" class="form-control-vx" rows="2"><?php echo esc_textarea( $offer_texto ); ?></textarea>
            </div>

            <!-- Tags que buscas -->
            <div class="mb-4 pb-4 border-bottom-vx">
              <label class="form-label-vx">Tags de lo que buscas</label>
              <div class="tag-selector mb-2" id="vx-seek-tags-suggestions">
                <?php foreach ( $tags_preset as $tag ) :
                    $sel = in_array( $tag, $user->get_seek_tags(), true );
                ?>
                <span class="tag-option <?php echo $sel ? 'tag-option--selected-seek' : ''; ?>"
                      onclick="vxToggleSuggestionTag(this,'seek')"><?php echo esc_html( $tag ); ?></span>
                <?php endforeach; ?>
              </div>
              <div class="d-flex flex-wrap gap-2 mb-2" id="vx-seek-tags-container"></div>
              <label class="form-label-vx">Descripción de lo que buscas</label>
              <textarea name="seek_texto" class="form-control-vx" rows="2"><?php echo esc_textarea( $seek_texto ); ?></textarea>
            </div>

            <!-- Comunidades -->
            <div>
              <h5 class="card-title-sm mb-3">Comunidades</h5>
              <div class="mb-3">
                <label class="d-flex align-items-start gap-3" style="cursor:pointer">
                  <input type="checkbox" name="comunidad_out2b"
                    style="margin-top:3px;accent-color:var(--color-text-primary);width:16px;height:16px;flex-shrink:0"
                    <?php checked( $is_out2b ); ?>>
                  <div>
                    <span class="fw-medium" style="font-size:14px">Soy una persona LGBTQ+</span>
                    <p class="text-sm-muted" style="margin:2px 0 0">Aparecer en Vitrinexo LGBTQ+</p>
                  </div>
                </label>
              </div>
              <div>
                <label class="d-flex align-items-start gap-3" style="cursor:not-allowed;opacity:.7">
                  <input type="checkbox" disabled
                    style="margin-top:3px;accent-color:var(--color-text-primary);width:16px;height:16px;flex-shrink:0"
                    <?php checked( $is_senior ); ?>>
                  <div>
                    <span class="fw-medium" style="font-size:14px">Ejecutivo/a senior</span>
                    <p class="text-sm-muted" style="margin:2px 0 0"><i class="ti ti-lock" style="font-size:11px"></i> Se asigna automáticamente con 45+ años o 20+ de experiencia</p>
                  </div>
                </label>
              </div>
            </div>

          </div>
        </div>

      </form>

      <!-- ========== SECCIÓN EMPRESAS ========== -->
      <div class="modal-vx profile-editor-card mb-4 p-0 overflow-hidden">
        <div class="card-section-header">
          <h4 class="fw-semibold mb-0" style="font-size:1rem">
            <i class="ti ti-building me-2 ic-success"></i>Empresa
          </h4>
        </div>
        <div class="card-section-body">

          <?php foreach ( $empresas as $i => $emp ) :
            $emp_id       = (int) $emp->ID;
            $cargo        = (string) get_post_meta( $emp_id, 'vx_cargo',               true );
            $desc         = (string) get_post_meta( $emp_id, 'vx_descripcion',          true );
            $desc_cli     = (string) get_post_meta( $emp_id, 'vx_descripcion_cliente',  true );
            $web          = (string) get_post_meta( $emp_id, 'vx_web',                  true );
            $linkedin     = (string) get_post_meta( $emp_id, 'vx_linkedin',             true );
            $emp_industria = (string) get_post_meta( $emp_id, 'vx_industria',           true );
            $logo_id      = (int)    get_post_meta( $emp_id, 'vx_logo',                 true );
            $banner_id    = (int)    get_post_meta( $emp_id, 'vx_banner',               true );
            $logo_url     = $logo_id   ? wp_get_attachment_image_url( $logo_id,   'vx-logo'   ) : '';
            $banner_url   = $banner_id ? wp_get_attachment_image_url( $banner_id, 'vx-banner' ) : '';
            $is_last      = ( $i === count( $empresas ) - 1 );
          ?>
          <div class="<?php echo $is_last ? '' : 'mb-4 pb-4 border-bottom-vx'; ?>">
            <div class="d-flex align-items-center justify-content-between mb-3">
              <div>
                <p class="fw-semibold mb-0" style="font-size:15px"><?php echo esc_html( $emp->post_title ); ?></p>
                <?php if ( $cargo ) : ?>
                <p class="text-body-muted" style="margin:2px 0 0"><?php echo esc_html( $cargo ); ?></p>
                <?php endif; ?>
              </div>
              <button type="button" class="btn-vx btn-ghost-vx btn-vx-sm vx-toggle-btn"
                onclick="vxToggleEmpresa(this, <?php echo $emp_id; ?>)">
                <i class="ti ti-pencil me-1"></i>Editar
              </button>
            </div>

            <!-- Formulario expandible -->
            <div class="empresa-form" id="vx-emp-form-<?php echo $emp_id; ?>" style="display:none">

              <!-- Logo -->
              <div class="mb-4 pb-3 border-bottom-vx">
                <label class="form-label-vx mb-2">Logo de empresa</label>
                <div class="d-flex align-items-center gap-3">
                  <div class="logo-upload-zone"
                       onclick="document.getElementById('vx-logo-input-<?php echo $emp_id; ?>').click()">
                    <img src="<?php echo esc_url( $logo_url ); ?>" alt="Logo"
                         id="vx-logo-img-<?php echo $emp_id; ?>"
                         <?php echo $logo_url ? '' : 'style="display:none"'; ?>>
                    <div id="vx-logo-placeholder-<?php echo $emp_id; ?>"
                         <?php echo $logo_url ? 'style="display:none"' : ''; ?>
                         style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:var(--color-text-tertiary)">
                      <i class="ti ti-building" style="font-size:24px"></i>
                    </div>
                    <div class="logo-upload-overlay"><i class="ti ti-camera"></i></div>
                  </div>
                  <input type="file" id="vx-logo-input-<?php echo $emp_id; ?>" accept="image/*" class="d-none"
                    data-upload-type="logo" data-empresa-id="<?php echo $emp_id; ?>"
                    onchange="vxPreviewLogo(this, <?php echo $emp_id; ?>)">
                  <div>
                    <p class="text-body-label" style="margin:0 0 2px">Logo de la empresa</p>
                    <p class="text-sm-muted mb-0">Circular · Fondo blanco recomendado</p>
                    <button type="button" class="btn-vx btn-ghost-vx btn-vx-sm mt-2"
                      onclick="document.getElementById('vx-logo-input-<?php echo $emp_id; ?>').click()">
                      <i class="ti ti-upload me-1"></i>Subir logo
                    </button>
                    <p style="font-size:11px;color:var(--color-text-secondary);margin:4px 0 0"><i class="ti ti-info-circle" style="font-size:10px"></i> Máx. 15 MB · JPG, PNG o WebP</p>
                    <div class="vx-upload-progress d-none" id="vx-logo-progress-<?php echo $emp_id; ?>" style="margin-top:6px;min-width:160px">
                      <div style="height:3px;background:var(--color-border);border-radius:2px;overflow:hidden">
                        <div class="vx-progress-fill" style="height:100%;width:0%;background:var(--color-primary);transition:width 0.15s ease;border-radius:2px"></div>
                      </div>
                      <span class="vx-progress-label" style="font-size:11px;color:var(--color-text-secondary);margin-top:2px;display:block">Subiendo...</span>
                    </div>
                  </div>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label-vx">Nombre de la empresa</label>
                <input type="text" name="emp_nombre" class="form-control-vx"
                       value="<?php echo esc_attr( $emp->post_title ); ?>">
              </div>
              <div class="mb-3">
                <label class="form-label-vx">Tu cargo / título</label>
                <input type="text" name="emp_cargo" class="form-control-vx"
                       value="<?php echo esc_attr( $cargo ); ?>">
              </div>
              <div class="mb-3">
                <label class="form-label-vx">Sobre la empresa</label>
                <textarea name="emp_descripcion" class="form-control-vx" rows="3"><?php echo esc_textarea( $desc ); ?></textarea>
              </div>
              <div class="mb-3">
                <label class="form-label-vx">Cliente ideal</label>
                <textarea name="emp_descripcion_cliente" class="form-control-vx" rows="2"><?php echo esc_textarea( $desc_cli ); ?></textarea>
              </div>
              <div class="mb-3">
                <label class="form-label-vx">Industria principal</label>
                <select name="emp_industria" class="form-control-vx" id="vx-industria-<?php echo $emp_id; ?>">
                  <option value="">Sin especificar</option>
                  <?php foreach ( $industrias as $ind ) : ?>
                  <option value="<?php echo esc_attr( $ind ); ?>" <?php selected( $emp_industria, $ind ); ?>><?php echo esc_html( $ind ); ?></option>
                  <?php endforeach; ?>
                </select>
                <p class="text-sm-muted mt-1">Usada para filtrar en el directorio.</p>
              </div>
              <div class="mb-3">
                <label class="form-label-vx">Rubros / especialidades</label>
                <p class="text-sm-muted mb-2">Selecciona los más relevantes o escribe los tuyos.</p>
                <div class="tag-selector mb-2" id="vx-sector-suggestions-<?php echo $emp_id; ?>">
                  <?php
                  $existing_sector = array_filter( array_map( 'trim', explode( ',', (string) get_post_meta( $emp_id, 'vx_sector', true ) ) ) );
                  foreach ( $tags_preset as $tag ) :
                      $sel = in_array( $tag, $existing_sector, true );
                  ?>
                  <span class="tag-option <?php echo $sel ? 'tag-option--selected-sector' : ''; ?>"
                        onclick="vxToggleSectorSuggestion(this,<?php echo $emp_id; ?>)"><?php echo esc_html( $tag ); ?></span>
                  <?php endforeach; ?>
                </div>
                <div class="d-flex flex-wrap gap-2 mb-2"
                     id="vx-sector-tags-<?php echo $emp_id; ?>"></div>
                <input type="text" class="form-control-vx input-sm-vx"
                       placeholder="Agregar rubro personalizado y Enter..."
                       id="vx-sector-input-<?php echo $emp_id; ?>"
                       onkeydown="vxAddSectorTag(event,<?php echo $emp_id; ?>)">
              </div>
              <div class="row g-2 mb-3">
                <div class="col-md-6">
                  <label class="form-label-vx">Sitio web</label>
                  <input type="url" name="emp_web" class="form-control-vx"
                         value="<?php echo esc_attr( $web ); ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label-vx">LinkedIn empresa</label>
                  <input type="url" name="emp_linkedin" class="form-control-vx vx-linkedin-input"
                         value="<?php echo esc_attr( $linkedin ); ?>"
                         placeholder="https://linkedin.com/company/tuempresa">
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label-vx">Banner de empresa</label>
                <?php if ( $banner_url ) : ?>
                <div id="vx-banner-preview-<?php echo $emp_id; ?>" class="mb-2">
                  <img src="<?php echo esc_url( $banner_url ); ?>"
                       style="width:100%;height:100px;object-fit:cover;border-radius:var(--radius-sm)">
                </div>
                <?php else : ?>
                <div id="vx-banner-preview-<?php echo $emp_id; ?>" class="mb-2" style="display:none"></div>
                <?php endif; ?>
                <div class="banner-upload-zone"
                     onclick="document.getElementById('vx-banner-input-<?php echo $emp_id; ?>').click()">
                  <i class="ti ti-photo-up upload-zone-icon"></i>
                  <p class="text-body-muted mb-0">Arrastra una imagen o
                    <span class="link-primary-color">haz clic para subir</span></p>
                  <p class="text-xs-muted" style="margin:4px 0 0">Recomendado: 1200×300px · Máx. 15 MB</p>
                </div>
                <input type="file" id="vx-banner-input-<?php echo $emp_id; ?>" accept="image/*" class="d-none"
                  data-upload-type="banner" data-empresa-id="<?php echo $emp_id; ?>"
                  onchange="vxPreviewBanner(this,<?php echo $emp_id; ?>)">
                <div class="vx-upload-progress d-none" id="vx-banner-progress-<?php echo $emp_id; ?>" style="margin-top:6px">
                  <div style="height:3px;background:var(--color-border);border-radius:2px;overflow:hidden">
                    <div class="vx-progress-fill" style="height:100%;width:0%;background:var(--color-primary);transition:width 0.15s ease;border-radius:2px"></div>
                  </div>
                  <span class="vx-progress-label" style="font-size:11px;color:var(--color-text-secondary);margin-top:2px;display:block">Subiendo...</span>
                </div>
              </div>
              <div class="d-flex gap-2">
                <button type="button" class="btn-vx btn-primary-vx btn-vx-sm vx-emp-save-btn"
                  onclick="vxGuardarEmpresa(<?php echo $emp_id; ?>,this)">
                  Guardar empresa
                </button>
                <button type="button" class="btn-vx btn-ghost-vx btn-vx-sm"
                  onclick="vxToggleEmpresa(document.querySelector('[onclick*=\'vxToggleEmpresa\'][onclick*=\'<?php echo $emp_id; ?>\']'),<?php echo $emp_id; ?>)">
                  Cancelar
                </button>
              </div>

            </div><!-- /empresa-form -->
          </div>
          <?php endforeach; ?>

          <?php if ( empty( $empresas ) ) : ?>
          <p class="text-body-muted mb-4">No tienes empresas registradas aún.</p>
          <?php endif; ?>

          <!-- Formulario nueva empresa (oculto hasta clic) -->
          <div id="vx-nueva-empresa-form" class="<?php echo empty( $empresas ) ? '' : 'mt-4 pt-4 border-top-vx'; ?>" style="display:none">
            <h5 class="card-title-sm mb-3">Nueva empresa</h5>

            <!-- Logo -->
            <div class="mb-4 pb-3 border-bottom-vx">
              <label class="form-label-vx mb-2">Logo de empresa</label>
              <div class="d-flex align-items-center gap-3">
                <div class="logo-upload-zone" onclick="document.getElementById('vx-new-logo-input').click()">
                  <img alt="Logo" id="vx-new-logo-img" style="display:none">
                  <div id="vx-new-logo-placeholder"
                       style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:var(--color-text-tertiary)">
                    <i class="ti ti-building" style="font-size:24px"></i>
                  </div>
                  <div class="logo-upload-overlay"><i class="ti ti-camera"></i></div>
                </div>
                <input type="file" id="vx-new-logo-input" accept="image/*" class="d-none"
                  onchange="vxPreviewNewLogo(this)">
                <div>
                  <p class="text-body-label" style="margin:0 0 2px">Logo de la empresa</p>
                  <p class="text-sm-muted mb-0">Circular · Usa una imagen cuadrada centrada · Fondo blanco recomendado · PNG o JPG</p>
                  <button type="button" class="btn-vx btn-ghost-vx btn-vx-sm mt-2"
                    onclick="document.getElementById('vx-new-logo-input').click()">
                    <i class="ti ti-upload me-1"></i>Subir logo
                  </button>
                  <p style="font-size:11px;color:var(--color-text-secondary);margin:4px 0 0"><i class="ti ti-info-circle" style="font-size:10px"></i> Máx. 15 MB · JPG, PNG o WebP</p>
                  <div class="vx-upload-progress d-none" id="vx-new-logo-progress" style="margin-top:6px;min-width:160px">
                    <div style="height:3px;background:var(--color-border);border-radius:2px;overflow:hidden">
                      <div class="vx-progress-fill" style="height:100%;width:0%;background:var(--color-primary);transition:width 0.15s ease;border-radius:2px"></div>
                    </div>
                    <span class="vx-progress-label" style="font-size:11px;color:var(--color-text-secondary);margin-top:2px;display:block">Subiendo...</span>
                  </div>
                </div>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label-vx">Nombre de la empresa <span style="color:var(--color-text-primary)">*</span></label>
              <input type="text" name="new_emp_nombre" class="form-control-vx" placeholder="Ej: Mi Empresa S.A.">
            </div>
            <div class="mb-3">
              <label class="form-label-vx">Tu cargo / título</label>
              <input type="text" name="new_emp_cargo" class="form-control-vx" placeholder="Ej: CEO, Directora Comercial">
            </div>
            <div class="mb-3">
              <label class="form-label-vx">Sobre la empresa</label>
              <textarea name="new_emp_descripcion" class="form-control-vx" rows="3"></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label-vx">Cliente ideal</label>
              <textarea name="new_emp_descripcion_cliente" class="form-control-vx" rows="2"></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label-vx">Industria principal</label>
              <select name="new_emp_industria" class="form-control-vx">
                <option value="">Sin especificar</option>
                <?php foreach ( $industrias as $ind ) : ?>
                <option value="<?php echo esc_attr( $ind ); ?>"><?php echo esc_html( $ind ); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label-vx">Rubros / especialidades</label>
              <div class="d-flex flex-wrap gap-2 mb-2" id="vx-sector-tags-new"></div>
              <input type="text" class="form-control-vx input-sm-vx" placeholder="Agregar rubro y Enter..."
                onkeydown="vxAddSectorTag(event,'new')">
            </div>
            <div class="row g-2 mb-3">
              <div class="col-md-6">
                <label class="form-label-vx">Sitio web</label>
                <input type="url" name="new_emp_web" class="form-control-vx" placeholder="https://...">
              </div>
              <div class="col-md-6">
                <label class="form-label-vx">LinkedIn empresa</label>
                <input type="url" name="new_emp_linkedin" class="form-control-vx vx-linkedin-input" placeholder="https://linkedin.com/company/tuempresa">
              </div>
            </div>

            <!-- Banner -->
            <div class="mb-3">
              <label class="form-label-vx">Banner de empresa</label>
              <div id="vx-new-banner-preview" class="mb-2" style="display:none"></div>
              <div class="banner-upload-zone" onclick="document.getElementById('vx-new-banner-input').click()">
                <i class="ti ti-photo-up upload-zone-icon"></i>
                <p class="text-body-muted mb-0">Arrastra una imagen o
                  <span class="link-primary-color">haz clic para subir</span></p>
                <p class="text-xs-muted" style="margin:4px 0 0">Recomendado: 1200×300px · Máx. 15 MB</p>
              </div>
              <input type="file" id="vx-new-banner-input" accept="image/*" class="d-none"
                onchange="vxPreviewNewBanner(this)">
              <div class="vx-upload-progress d-none" id="vx-new-banner-progress" style="margin-top:6px">
                <div style="height:3px;background:var(--color-border);border-radius:2px;overflow:hidden">
                  <div class="vx-progress-fill" style="height:100%;width:0%;background:var(--color-primary);transition:width 0.15s ease;border-radius:2px"></div>
                </div>
                <span class="vx-progress-label" style="font-size:11px;color:var(--color-text-secondary);margin-top:2px;display:block">Subiendo...</span>
              </div>
            </div>

            <div class="d-flex gap-2 mt-4">
              <button type="button" class="btn-vx btn-primary-vx btn-vx-sm" id="vx-crear-empresa-btn"
                onclick="vxCrearEmpresa(this)">
                <i class="ti ti-plus me-1"></i>Crear empresa
              </button>
              <button type="button" class="btn-vx btn-ghost-vx btn-vx-sm"
                onclick="vxToggleNuevaEmpresa()">
                Cancelar
              </button>
            </div>
          </div>

          <button type="button" id="vx-agregar-empresa-btn"
            class="btn-vx btn-ghost-vx btn-vx-md btn-vx-dashed <?php echo empty( $empresas ) ? '' : 'mt-4'; ?>"
            onclick="vxToggleNuevaEmpresa()">
            <i class="ti ti-plus me-1"></i>Agregar otra empresa
          </button>

        </div>
      </div>

      <!-- Guardar al fondo -->
      <div class="d-flex justify-content-end mb-5">
        <button type="button" class="btn-vx btn-primary-vx btn-vx-lg" id="vx-btn-guardar-bottom">
          <i class="ti ti-check me-1"></i>Guardar cambios
        </button>
      </div>

    </div>

    <script>
    window.vxCiudadesPorPais = <?php echo wp_json_encode( vx_get_ciudades_por_pais() ); ?>;
    </script>
    <script>
    (function () {
      // ── Tag state ────────────────────────────────────────────────────────────
      window._vxOfferTags   = <?php echo wp_json_encode( array_values( $offer_tags ) ); ?>;
      window._vxSeekTags    = <?php echo wp_json_encode( array_values( $seek_tags ) ); ?>;
      window._vxProfileTags = <?php echo wp_json_encode( array_values( $current_profile_tags ) ); ?>;
      window._vxSectorTags  = <?php echo wp_json_encode( $sector_tags_map ); ?>;
      window._vxActiveEmpresaId = <?php echo wp_json_encode( $empresas ? (string) $empresas[0]->ID : '' ); ?>;

      // ── Valores originales del servidor (para detectar autofill silencioso) ──
      // El navegador a veces borra el value de inputs type=url/email con autofill.
      // Guardamos los valores del servidor y los restauramos si el campo queda vacío
      // justo antes de guardar.
      var _vxServerValues = {
        linkedin: <?php echo wp_json_encode( $user->get_linkedin() ); ?>,
        telefono: <?php echo wp_json_encode( $user->get_telefono() ); ?>,
      };

      function vxRenderTagChips( containerId, tags, type, empId ) {
        const c = document.getElementById( containerId );
        if ( ! c ) return;
        c.innerHTML = '';
        tags.forEach( function ( tag ) {
          const span = document.createElement( 'span' );
          span.className = 'tag-vx d-flex align-items-center gap-1'
            + ( type === 'offer' ? ' tag-offers' : type === 'seek' ? ' tag-seeks' : '' );
          span.appendChild( document.createTextNode( tag + ' ' ) );
          const btn = document.createElement( 'button' );
          btn.type      = 'button';
          btn.className = 'btn-tag-remove';
          btn.innerHTML = '&times;';
          btn.addEventListener( 'click', function () {
            if ( type === 'offer' ) {
              window._vxOfferTags = window._vxOfferTags.filter( function (t) { return t !== tag; } );
              vxRenderTagChips( 'vx-offer-tags-container', window._vxOfferTags, 'offer' );
              // Deselect suggestion if present
              vxDeselectSuggestion( 'vx-offer-tags-suggestions', tag, 'tag-option--selected-offer' );
            } else if ( type === 'seek' ) {
              window._vxSeekTags = window._vxSeekTags.filter( function (t) { return t !== tag; } );
              vxRenderTagChips( 'vx-seek-tags-container', window._vxSeekTags, 'seek' );
              vxDeselectSuggestion( 'vx-seek-tags-suggestions', tag, 'tag-option--selected-seek' );
            } else if ( type === 'profile' ) {
              window._vxProfileTags = window._vxProfileTags.filter( function (t) { return t !== tag; } );
              vxRenderTagChips( 'vx-profile-tags-custom', window._vxProfileTags, 'profile' );
              vxDeselectSuggestion( 'vx-profile-tags-suggestions', tag, 'tag-option--selected-profile' );
            } else if ( empId ) {
              window._vxSectorTags[ empId ] = ( window._vxSectorTags[ empId ] || [] ).filter( function (t) { return t !== tag; } );
              vxRenderTagChips( 'vx-sector-tags-' + empId, window._vxSectorTags[ empId ], 'sector', empId );
            }
          } );
          span.appendChild( btn );
          c.appendChild( span );
        } );
      }

      function vxDeselectSuggestion( containerId, tag, cls ) {
        var c = document.getElementById( containerId );
        if ( !c ) return;
        c.querySelectorAll( '.tag-option' ).forEach( function(el) {
          if ( el.textContent.trim() === tag ) el.classList.remove( cls );
        } );
      }

      // ── Suggestion tags (clickable preset chips) ───────────────────────────
      window.vxToggleSuggestionTag = function( el, type ) {
        var cls = type === 'offer' ? 'tag-option--selected-offer' : 'tag-option--selected-seek';
        var arr = type === 'offer' ? window._vxOfferTags : window._vxSeekTags;
        var containerId = type === 'offer' ? 'vx-offer-tags-container' : 'vx-seek-tags-container';
        var tag = el.textContent.trim();
        if ( el.classList.contains( cls ) ) {
          el.classList.remove( cls );
          if ( type === 'offer' ) window._vxOfferTags = arr.filter( function(t){ return t !== tag; } );
          else window._vxSeekTags = arr.filter( function(t){ return t !== tag; } );
        } else {
          el.classList.add( cls );
          arr.push( tag );
        }
        vxRenderTagChips( containerId, type === 'offer' ? window._vxOfferTags : window._vxSeekTags, type );
      };

      // Profile tags: el estado visual ES el chip de sugerencia seleccionado.
      // Los tags de sugerencias seleccionados se guardan en _vxProfileTags.
      // El área "custom" solo muestra tags que el usuario escribió manualmente.
      window.vxToggleProfileTag = function( el ) {
        var cls = 'tag-option--selected-profile';
        var tag = el.textContent.trim();
        if ( el.classList.contains( cls ) ) {
          el.classList.remove( cls );
          window._vxProfileTags = window._vxProfileTags.filter( function(t){ return t !== tag; } );
        } else {
          el.classList.add( cls );
          if ( window._vxProfileTags.indexOf( tag ) === -1 ) window._vxProfileTags.push( tag );
        }
        // No need to render chips for suggestion-based tags —
        // the selected class on the suggestion IS the visual indicator.
        // _vxProfileTags is sent on save including both suggestion & custom tags.
      };

      window.vxAddProfileTagCustom = function( e ) {
        if ( e.key !== 'Enter' && e.key !== ',' ) return;
        e.preventDefault();
        var val = e.target.value.trim();
        if ( !val || window._vxProfileTags.indexOf( val ) !== -1 ) { e.target.value = ''; return; }
        window._vxProfileTags.push( val );
        // Render only the non-preset custom tags in the chips area
        var preset = Array.from( (document.getElementById('vx-profile-tags-suggestions') || {}).querySelectorAll ? document.getElementById('vx-profile-tags-suggestions').querySelectorAll('.tag-option') : [] ).map(function(el){ return el.textContent.trim(); });
        var customOnly = window._vxProfileTags.filter( function(t){ return preset.indexOf(t) === -1; } );
        vxRenderTagChips( 'vx-profile-tags-custom', customOnly, 'profile' );
        e.target.value = '';
      };

      // Init tags
      vxRenderTagChips( 'vx-offer-tags-container', window._vxOfferTags,  'offer' );
      vxRenderTagChips( 'vx-seek-tags-container',  window._vxSeekTags,   'seek' );
      Object.keys( window._vxSectorTags ).forEach( function ( id ) {
        // Mark suggestion chips that are already selected
        var sectorArr = window._vxSectorTags[ id ] || [];
        sectorArr.forEach( function( tag ) {
          var sugg = document.querySelector( '#vx-sector-suggestions-' + id + ' .tag-option' );
          if ( sugg ) {
            var allSugg = document.querySelectorAll( '#vx-sector-suggestions-' + id + ' .tag-option' );
            allSugg.forEach( function(el) {
              if ( el.textContent.trim() === tag ) el.classList.add('tag-option--selected-sector');
            });
          }
        });
        vxRenderSectorCustom( id );
      } );

      // Tag input handlers
      function vxBindTagInput( inputId, arr, containerId, type ) {
        const el = document.getElementById( inputId );
        if ( ! el ) return;
        el.addEventListener( 'keydown', function ( e ) {
          if ( [ 'Enter', 'Tab', ',' ].includes( e.key ) ) {
            e.preventDefault();
            const val = el.value.trim().toLowerCase();
            if ( val && ! arr.includes( val ) && arr.length < 10 ) {
              arr.push( val );
              vxRenderTagChips( containerId, arr, type );
            }
            el.value = '';
          }
        } );
      }
      vxBindTagInput( 'vx-offer-tag-input', window._vxOfferTags, 'vx-offer-tags-container', 'offer' );
      vxBindTagInput( 'vx-seek-tag-input',  window._vxSeekTags,  'vx-seek-tags-container',  'seek' );

      // ── Empresa helpers ──────────────────────────────────────────────────────
      window.vxAddSectorTag = function ( e, empId ) {
        if ( [ 'Enter', 'Tab', ',' ].includes( e.key ) ) {
          e.preventDefault();
          var val = e.target.value.trim();
          if ( ! val ) return;
          window._vxSectorTags[ empId ] = window._vxSectorTags[ empId ] || [];
          if ( ! window._vxSectorTags[ empId ].includes( val ) ) {
            window._vxSectorTags[ empId ].push( val );
            vxRenderSectorCustom( empId );
          }
          e.target.value = '';
        }
      };

      window.vxToggleSectorSuggestion = function( el, empId ) {
        var cls = 'tag-option--selected-sector';
        var tag = el.textContent.trim();
        window._vxSectorTags[ empId ] = window._vxSectorTags[ empId ] || [];
        if ( el.classList.contains( cls ) ) {
          el.classList.remove( cls );
          window._vxSectorTags[ empId ] = window._vxSectorTags[ empId ].filter( function(t){ return t !== tag; } );
        } else {
          el.classList.add( cls );
          if ( window._vxSectorTags[ empId ].indexOf( tag ) === -1 ) window._vxSectorTags[ empId ].push( tag );
        }
        vxRenderSectorCustom( empId );
      };

      function vxRenderSectorCustom( empId ) {
        // Only render in chips area the tags NOT in the suggestions list
        var presetEls = document.querySelectorAll( '#vx-sector-suggestions-' + empId + ' .tag-option' );
        var preset = Array.from( presetEls ).map( function(el){ return el.textContent.trim(); } );
        var custom = ( window._vxSectorTags[ empId ] || [] ).filter( function(t){ return preset.indexOf(t) === -1; } );
        vxRenderTagChips( 'vx-sector-tags-' + empId, custom, 'sector', empId );
      }

      window.vxToggleEmpresa = function ( btn, empId ) {
        const form = document.getElementById( 'vx-emp-form-' + empId );
        if ( ! form ) return;
        const isOpen = form.style.display !== 'none';
        form.style.display = isOpen ? 'none' : 'block';
        btn.innerHTML = isOpen
          ? '<i class="ti ti-pencil me-1"></i>Editar'
          : '<i class="ti ti-x me-1"></i>Cerrar';
      };

      window.vxPreviewLogo = function ( input, empId ) {
        if ( ! input.files || ! input.files[0] ) return;
        var file = input.files[0];
        var progressEl = document.getElementById( 'vx-logo-progress-' + empId );
        function preview( src ) {
          const img = document.getElementById( 'vx-logo-img-' + empId );
          const ph  = document.getElementById( 'vx-logo-placeholder-' + empId );
          if ( img ) { img.src = src; img.style.display = ''; }
          if ( ph )  { ph.style.display = 'none'; }
        }
        function upload( f ) {
          if ( typeof window.vxUploadXHR === 'function' ) {
            window.vxUploadXHR( f, 'logo', empId, progressEl,
              function(){},
              function( msg ) { if ( typeof vxShowError === 'function' ) vxShowError( msg ); }
            );
          }
        }
        if ( typeof window.vxCropSquare === 'function' ) {
          window.vxCropSquare( file ).then( function ( blob ) {
            var cropped = new File( [ blob ], 'logo.webp', { type: 'image/webp' } );
            try { preview( URL.createObjectURL( blob ) ); } catch ( e ) {}
            input.value = '';
            upload( cropped );
          } ).catch( function ( err ) {
            if ( err && err.reason === 'cancel' ) { input.value = ''; return; }
            var r = new FileReader(); r.onload = function ( e ) { preview( e.target.result ); }; r.readAsDataURL( file );
            upload( file );
          } );
        } else {
          var r = new FileReader(); r.onload = function ( e ) { preview( e.target.result ); }; r.readAsDataURL( file );
          upload( file );
        }
      };

      window.vxPreviewBanner = function ( input, empId ) {
        if ( ! input.files || ! input.files[0] ) return;
        var file = input.files[0];
        // Preview optimista
        const reader = new FileReader();
        reader.onload = function ( e ) {
          const div = document.getElementById( 'vx-banner-preview-' + empId );
          if ( div ) {
            div.style.display = '';
            div.innerHTML = '<img src="' + e.target.result + '" style="width:100%;height:100px;object-fit:cover;border-radius:var(--radius-sm)">';
          }
        };
        reader.readAsDataURL( file );
        // Upload con progreso
        var progressEl = document.getElementById( 'vx-banner-progress-' + empId );
        if ( typeof window.vxUploadXHR === 'function' ) {
          window.vxUploadXHR( file, 'banner', empId, progressEl,
            function(){},
            function( msg ) { if ( typeof vxShowError === 'function' ) vxShowError( msg ); }
          );
        }
      };

      // ── Save helpers ─────────────────────────────────────────────────────────
      var _vxSaving = false; // Fix: flag para evitar doble guardado simultáneo

      function vxShowError( msg ) {
        const err = document.getElementById( 'vx-alert-err' );
        const msgEl = document.getElementById( 'vx-alert-err-msg' );
        if ( err && msgEl ) { msgEl.textContent = msg; err.classList.remove( 'd-none' ); }
        window.scrollTo( { top: 0, behavior: 'smooth' } );
      }

      async function vxPost( payload, btnEl ) {
        if ( _vxSaving ) return; // Fix: prevenir doble guardado
        _vxSaving = true;

        // Deshabilitar ambos botones de guardar
        const btnTop    = document.getElementById( 'vx-btn-guardar-top' );
        const btnBottom = document.getElementById( 'vx-btn-guardar-bottom' );
        const orig = btnEl.innerHTML;
        [ btnTop, btnBottom, btnEl ].forEach( b => { if(b) { b.disabled = true; } } );
        btnEl.innerHTML = '<i class="ti ti-loader-2 me-1"></i>Guardando...';

        try {
          const res  = await fetch( vx_data.api_url + 'perfil/guardar', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': vx_data.nonce },
            body:    JSON.stringify( payload ),
          } );
          const json = await res.json();
          if ( json.success ) {
            const ok = document.getElementById( 'vx-alert-ok' );
            if ( ok ) { ok.classList.remove( 'd-none' ); setTimeout( () => ok.classList.add( 'd-none' ), 4000 ); }
            window.scrollTo( { top: 0, behavior: 'smooth' } );
          } else {
            vxShowError( json.error || json.data?.message || 'Error al guardar.' );
          }
        } catch ( ex ) {
          vxShowError( 'Error de conexión. Intenta de nuevo.' );
        }

        _vxSaving = false;
        [ btnTop, btnBottom, btnEl ].forEach( b => { if(b) b.disabled = false; } );
        btnEl.innerHTML = orig;
      }

      // ── Cascade país → ciudad (editor de perfil) ─────────────────────────────
      (function() {
        var paisSel     = document.getElementById('ep-pais');
        var ciudadSel   = document.getElementById('ep-ciudad');
        var customInput = document.getElementById('ep-ciudad-custom');
        if ( !paisSel || !ciudadSel ) return;

        var ciudadesPorPais = window.vxCiudadesPorPais || {};

        function toggleCustom() {
          var isOtra = ciudadSel.value === '__otra__';
          if ( customInput ) {
            customInput.style.display = isOtra ? '' : 'none';
            if ( isOtra ) customInput.focus();
            else customInput.value = '';
          }
        }

        function populateCiudades( pais, selected ) {
          var ciudades = ciudadesPorPais[ pais ] || [];
          ciudadSel.innerHTML = '';
          if ( !ciudades.length ) {
            ciudadSel.innerHTML = '<option value="">Sin ciudades para este país</option>';
            if ( customInput ) customInput.style.display = 'none';
            return;
          }
          var ph = document.createElement('option');
          ph.value = ''; ph.textContent = 'Selecciona tu ciudad';
          ciudadSel.appendChild(ph);
          ciudades.forEach(function(c) {
            var opt = document.createElement('option');
            opt.value = c; opt.textContent = c;
            if ( c === selected ) opt.selected = true;
            ciudadSel.appendChild(opt);
          });
          var otra = document.createElement('option');
          otra.value = '__otra__'; otra.textContent = 'Otra ciudad...';
          ciudadSel.appendChild(otra);
          toggleCustom();
        }

        // Al cambiar el país, refrescar ciudades
        paisSel.addEventListener('change', function() {
          populateCiudades( this.value, '' );
        });

        // Al cambiar ciudad, mostrar/ocultar input custom
        ciudadSel.addEventListener('change', toggleCustom);

        // No repoblamos al cargar (el PHP ya renderizó las opciones correctas)
        // Solo inicializamos el toggle por si viene con "Otra" preseleccionada
        toggleCustom();
      })();

      // Fix: null-safety en todos los querySelector (evita TypeError si el campo no existe)
      function vxVal( f, selector, fallback ) {
        const el = f.querySelector( selector );
        return el !== null ? el.value : ( fallback !== undefined ? fallback : '' );
      }
      function vxChecked( f, selector ) {
        const el = f.querySelector( selector );
        return el !== null ? el.checked : false;
      }

      // Devuelve el valor de un campo, con fallback al valor original del servidor
      // si el campo aparece vacío (protege contra autofill silencioso del navegador).
      function vxSafeVal( f, selector, serverKey ) {
        var v = vxVal( f, selector );
        if ( v === '' && serverKey && _vxServerValues[ serverKey ] ) {
          // El campo quedó vacío pero el servidor tenía un valor — restaurar
          var el = f.querySelector( selector );
          if ( el ) el.value = _vxServerValues[ serverKey ];
          return _vxServerValues[ serverKey ];
        }
        return v;
      }

      function vxCollectPersonal() {
        const f = document.getElementById( 'vx-editor-form' );
        if ( ! f ) return {};
        return {
          nombre:              vxVal( f, '[name="nombre"]' ).trim(),
          apellido:            vxVal( f, '[name="apellido"]' ).trim(),
          bio:                 vxVal( f, '[name="bio"]' ),
          ciudad:              (function(){ var s=f.querySelector('[name="ciudad"]'); var c=document.getElementById('ep-ciudad-custom'); return s && s.value==='__otra__' ? (c ? c.value.trim() : '') : vxVal(f,'[name="ciudad"]'); })(),
          pais:                vxVal( f, '[name="pais"]' ),
          telefono:            vxSafeVal( f, '[name="telefono"]', 'telefono' ),
          linkedin:            vxSafeVal( f, '[name="linkedin"]', 'linkedin' ),
          contacto_preferido:  vxVal( f, '[name="contacto_preferido"]' ),
          offer_tags:          window._vxOfferTags  || [],
          seek_tags:           window._vxSeekTags   || [],
          profile_tags:        window._vxProfileTags || [],
          industria:           ( document.getElementById( 'vx-industria-' + ( window._vxActiveEmpresaId || '' ) ) || {} ).value || '',
          offer_texto:         vxVal( f, '[name="offer_texto"]' ),
          seek_texto:          vxVal( f, '[name="seek_texto"]' ),
          comunidad_out2b:     vxChecked( f, '[name="comunidad_out2b"]' ),
          comunidad_senior:    vxChecked( f, '[name="comunidad_senior"]' ),
        };
      }

      document.getElementById( 'vx-btn-guardar-top' ).addEventListener( 'click', function () {
        vxPost( vxCollectPersonal(), this );
      } );
      document.getElementById( 'vx-btn-guardar-bottom' ).addEventListener( 'click', function () {
        vxPost( vxCollectPersonal(), this );
      } );

      window.vxGuardarEmpresa = function ( empId, btn ) {
        const form = document.getElementById( 'vx-emp-form-' + empId );
        if ( ! form ) return;

        // Fix: validar nombre antes de enviar
        const nombreEl = form.querySelector( '[name="emp_nombre"]' );
        if ( ! nombreEl || ! nombreEl.value.trim() ) {
          if ( nombreEl ) { nombreEl.style.borderColor = 'var(--rosa)'; nombreEl.focus(); }
          vxShowError( 'El nombre de la empresa es obligatorio.' );
          return;
        }

        vxPost( {
          empresas: [ {
            id:                  empId,
            nombre:              nombreEl.value.trim(),
            cargo:               vxVal( form, '[name="emp_cargo"]' ),
            descripcion:         vxVal( form, '[name="emp_descripcion"]' ),
            descripcion_cliente: vxVal( form, '[name="emp_descripcion_cliente"]' ),
            sector:              ( window._vxSectorTags[ empId ] || [] ).join( ',' ),
            industria:           ( document.getElementById( 'vx-industria-' + empId ) || {} ).value || '',
            web:                 vxVal( form, '[name="emp_web"]' ),
            linkedin:            vxVal( form, '[name="emp_linkedin"]' ),
          } ],
        }, btn );
      };

      // ── Nueva empresa ────────────────────────────────────────────────────────
      window._vxSectorTags[ 'new' ] = [];

      window.vxToggleNuevaEmpresa = function () {
        const form = document.getElementById( 'vx-nueva-empresa-form' );
        const btn  = document.getElementById( 'vx-agregar-empresa-btn' );
        const isOpen = form.style.display !== 'none';
        form.style.display = isOpen ? 'none' : 'block';
        btn.innerHTML = isOpen
          ? '<i class="ti ti-plus me-1"></i>Agregar otra empresa'
          : '<i class="ti ti-x me-1"></i>Cancelar';
        if ( ! isOpen ) {
          form.scrollIntoView( { behavior: 'smooth', block: 'start' } );
        }
      };

      window.vxPreviewNewLogo = function ( input ) {
        if ( ! input.files || ! input.files[0] ) return;
        var file = input.files[0];
        function preview( src ) {
          const img = document.getElementById( 'vx-new-logo-img' );
          const ph  = document.getElementById( 'vx-new-logo-placeholder' );
          if ( img ) { img.src = src; img.style.display = ''; }
          if ( ph )  { ph.style.display = 'none'; }
        }
        if ( typeof window.vxCropSquare === 'function' ) {
          window.vxCropSquare( file ).then( function ( blob ) {
            var cropped = new File( [ blob ], 'logo.webp', { type: 'image/webp' } );
            try { var dt = new DataTransfer(); dt.items.add( cropped ); input.files = dt.files; } catch ( e ) {}
            try { preview( URL.createObjectURL( blob ) ); } catch ( e ) {}
          } ).catch( function ( err ) {
            if ( err && err.reason === 'cancel' ) { input.value = ''; return; }
            var r = new FileReader(); r.onload = function ( e ) { preview( e.target.result ); }; r.readAsDataURL( file );
          } );
        } else {
          var r = new FileReader(); r.onload = function ( e ) { preview( e.target.result ); }; r.readAsDataURL( file );
        }
      };

      window.vxPreviewNewBanner = function ( input ) {
        if ( ! input.files || ! input.files[0] ) return;
        const reader = new FileReader();
        reader.onload = function ( e ) {
          const div = document.getElementById( 'vx-new-banner-preview' );
          if ( div ) {
            div.style.display = '';
            div.innerHTML = '<img src="' + e.target.result + '" style="width:100%;height:100px;object-fit:cover;border-radius:var(--radius-sm)">';
          }
        };
        reader.readAsDataURL( input.files[0] );
      };

      // vxUploadImagen — usa vxUploadXHR con barra de progreso
      function vxUploadImagen( file, tipo, empresaId, progressEl ) {
        return new Promise( function( resolve, reject ) {
          if ( typeof window.vxUploadXHR !== 'function' ) {
            reject( new Error( 'vxUploadXHR no disponible' ) );
            return;
          }
          window.vxUploadXHR(
            file, tipo, empresaId, progressEl || null,
            function( json ) { resolve( json ); },
            function( msg  ) { vxShowError( msg ); reject( new Error( msg ) ); }
          );
        } );
      }

      window.vxCrearEmpresa = async function ( btn ) {
        const form   = document.getElementById( 'vx-nueva-empresa-form' );
        const nombreInput = form.querySelector( '[name="new_emp_nombre"]' );
        const nombre = nombreInput ? nombreInput.value.trim() : '';

        if ( ! nombre ) {
          if ( nombreInput ) { nombreInput.style.borderColor = 'var(--rosa)'; nombreInput.focus(); }
          vxShowError( 'El nombre de la empresa es obligatorio.' );
          return;
        }

        const orig    = btn.innerHTML;
        btn.disabled  = true;
        btn.innerHTML = '<i class="ti ti-loader-2 me-1"></i>Creando...';

        try {
          // 1. Crear la empresa y obtener su ID
          const industriaEl = form.querySelector( '[name="new_emp_industria"]' );
          const res  = await fetch( vx_data.api_url + 'empresa/crear', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': vx_data.nonce },
            body:    JSON.stringify( {
              nombre:              nombre,
              cargo:               vxVal( form, '[name="new_emp_cargo"]' ),
              descripcion:         vxVal( form, '[name="new_emp_descripcion"]' ),
              descripcion_cliente: vxVal( form, '[name="new_emp_descripcion_cliente"]' ),
              sector:              ( window._vxSectorTags[ 'new' ] || [] ).join( ',' ),
              industria:           industriaEl ? industriaEl.value : '',
              web:                 vxVal( form, '[name="new_emp_web"]' ),
              linkedin:            vxVal( form, '[name="new_emp_linkedin"]' ),
            } ),
          } );
          const json = await res.json();

          if ( ! json.success ) {
            throw new Error( json.error || 'Error al crear la empresa.' );
          }

          // 2. Subir logo y banner (en paralelo) usando el ID recién creado
          const empId     = json.empresa_id;
          const logoInput   = document.getElementById( 'vx-new-logo-input' );
          const bannerInput = document.getElementById( 'vx-new-banner-input' );
          const uploads = [];

          if ( logoInput && logoInput.files[0] ) {
            uploads.push( vxUploadImagen( logoInput.files[0], 'logo', empId, document.getElementById('vx-new-logo-progress') ) );
          }
          if ( bannerInput && bannerInput.files[0] ) {
            uploads.push( vxUploadImagen( bannerInput.files[0], 'banner', empId, document.getElementById('vx-new-banner-progress') ) );
          }
          if ( uploads.length ) {
            btn.innerHTML = '<i class="ti ti-loader-2 me-1"></i>Subiendo imágenes...';
            await Promise.all( uploads );
          }

          // 3. Recargar para mostrar la empresa con logo y banner
          window.location.reload();

        } catch ( ex ) {
          btn.disabled  = false;
          btn.innerHTML = orig;
          const errEl = document.getElementById( 'vx-alert-err' );
          document.getElementById( 'vx-alert-err-msg' ).textContent = ex.message || 'Error de conexión.';
          errEl.classList.remove( 'd-none' );
          window.scrollTo( { top: 0, behavior: 'smooth' } );
        }
      };

    }());

    // ── Restaurar campos vaciados por autofill del navegador ─────────────────
    (function () {
      var f = document.getElementById( 'vx-editor-form' );
      if ( ! f ) return;
      var checks = [
        { selector: '[name="linkedin"]', key: 'linkedin' },
        { selector: '[name="telefono"]', key: 'telefono' },
      ];
      checks.forEach( function( item ) {
        var el = f.querySelector( item.selector );
        if ( ! el ) return;
        var serverVal = ( window._vxServerValues || {} )[ item.key ] || '';
        if ( serverVal && el.value === '' ) {
          el.value = serverVal;
        }
      } );
    } )();

    // ── Detección de cambios no guardados ──────────────────────────────────────
    (function () {
      var dirty    = false;
      var saving   = false;
      var form     = document.getElementById( 'vx-editor-form' );
      var btnTop   = document.getElementById( 'vx-btn-guardar-top' );
      var btnBot   = document.getElementById( 'vx-btn-guardar-bottom' );

      function markDirty() {
        if ( saving ) return;
        dirty = true;
      }

      function markClean() {
        dirty  = false;
        saving = false;
      }

      // Escuchar cualquier cambio en el formulario
      if ( form ) {
        form.addEventListener( 'input',  markDirty );
        form.addEventListener( 'change', markDirty );
      }

      // También cuando el usuario modifica los tags de oferta/búsqueda/perfil
      // (se actualizan via JS, no via input estándar — usar MutationObserver)
      var tagContainers = document.querySelectorAll(
        '.tags-selected-offer, .tags-selected-seek, .tags-selected-profile, [id$="-sector-tags"]'
      );
      tagContainers.forEach( function ( el ) {
        new MutationObserver( markDirty ).observe( el, { childList: true, subtree: true } );
      } );

      // Marcar como limpio cuando el guardado es exitoso
      // El botón de guardar llama a vxGuardar() — hookeamos el alert de éxito
      var alertOk = document.getElementById( 'vx-alert-ok' );
      if ( alertOk ) {
        new MutationObserver( function( muts ) {
          muts.forEach( function( m ) {
            if ( m.type === 'attributes' && ! alertOk.classList.contains( 'd-none' ) ) {
              markClean();
            }
          } );
        } ).observe( alertOk, { attributes: true, attributeFilter: ['class'] } );
      }

      // Marcar saving=true mientras el guardado está en progreso
      // (evita que el dirty se reactive durante el guardado)
      function onGuardarClick() { saving = true; }
      if ( btnTop ) btnTop.addEventListener( 'click', onGuardarClick );
      if ( btnBot ) btnBot.addEventListener( 'click', onGuardarClick );

      // Aviso de navegador antes de salir con cambios no guardados
      window.addEventListener( 'beforeunload', function ( e ) {
        if ( ! dirty ) return;
        e.preventDefault();
        e.returnValue = '';   // requerido por Chrome para mostrar el diálogo
      } );

      // Limpiar el flag si el usuario recarga (reload cuenta como salida)
      // no hacemos nada extra — el beforeunload lo maneja
    }());
    </script>
    <?php
    return ob_get_clean();
} );

// [vx_4dinner] — página de 4Dinners para miembros
add_shortcode( 'vx_4dinner', function (): string {
    $user_id = get_current_user_id();
    $dinners = VX_Dinner::get_upcoming();
    $pasados = VX_Dinner::get_past();

    $nonce_rest = wp_create_nonce( 'wp_rest' );
    $api_base   = rest_url( VX_REST_NAMESPACE . '/' );

    $gradients = [
        'linear-gradient(135deg,var(--salvia-hover),var(--salvia))',
        'linear-gradient(135deg,var(--lavanda-hover),var(--lavanda))',
        'linear-gradient(135deg,var(--mantequilla-presion),var(--coral))',
        'linear-gradient(135deg,var(--celeste-presion),var(--lavanda-presion))',
    ];

    ob_start();
    ?>
    <!-- PAGE HEADER -->
    <div class="page-header-vx page-header-vx--4dinner">
        <div class="container">
            <div class="page-header-vx__inner">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge-vx badge-4dinner-light">
                            <i class="ti ti-tools-kitchen-2 me-1"></i> Eventos presenciales
                        </span>
                    </div>
                    <h1 class="page-header-vx__title">Vitrinexo 4Dinner</h1>
                    <p class="page-header-vx__lead">4 personas. 1 mesa. Una conversación que importa.</p>
                </div>
                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <a href="#proximos" class="btn-vx btn-vx-sm btn-4dinner-ghost">
                        <i class="ti ti-calendar-event"></i> Próximos eventos
                    </a>
                    <a href="#form-interes" class="btn-vx btn-vx-sm btn-4dinner-cta">
                        Quiero participar <i class="ti ti-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container py-4">

        <!-- ABOUT STRIP -->
        <div class="card-vx mb-4">
            <div class="row g-4 align-items-center">
                <div class="col-12 col-md-7">
                    <p class="text-md-muted" style="line-height:1.7;margin:0">
                        Cada miércoles a las 20:00, 4 miembros de la red Vitrinexo se sientan a cenar en una ciudad distinta. Sin agenda, sin pitches formales. Solo conversación real entre personas que ya se conocen por sus fichas. Cada quien paga su consumo.
                    </p>
                </div>
                <div class="col-12 col-md-5">
                    <div class="row g-2">
                        <div class="col-4 text-center">
                            <div class="stat-num-vx stat-num-vx--success stat-num-vx--md">4</div>
                            <div class="stat-label-vx">personas por mesa</div>
                        </div>
                        <div class="col-4 text-center">
                            <div class="stat-num-vx stat-num-vx--primary stat-num-vx--md">20:00</div>
                            <div class="stat-label-vx">hora local, miércoles</div>
                        </div>
                        <div class="col-4 text-center">
                            <div class="stat-num-vx stat-num-vx--secondary stat-num-vx--md">18+</div>
                            <div class="stat-label-vx">ciudades activas</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- PRÓXIMOS EVENTOS -->
        <section id="proximos" class="mb-5">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <span class="subsection-label">Agenda</span>
                    <h2 class="subsection-title">Próximos eventos</h2>
                </div>
                <?php if ( $dinners ) : ?>
                <span class="badge-vx badge-primary"><?php echo count( $dinners ); ?> ciudad<?php echo count( $dinners ) !== 1 ? 'es' : ''; ?> en agenda</span>
                <?php endif; ?>
            </div>

            <?php if ( $dinners ) : ?>
            <div class="d-flex flex-column gap-3">
                <?php foreach ( $dinners as $dinner ) :
                    $asignados   = $dinner->get_asignados();
                    $interesados        = $dinner->get_interesados();
                    $user_asig          = in_array( $user_id, $asignados, true );
                    $user_inter         = in_array( $user_id, $interesados, true );
                    $cupos_disp         = max( 0, 4 - count( $asignados ) );
                    $completo           = $cupos_disp === 0 && ! $user_asig;
                    $fecha_ts           = $dinner->get_fecha();
                    $deadline_ts        = $dinner->get_deadline();
                    $deadline_passed    = $dinner->is_deadline_passed();
                    $inscripciones_open = $dinner->is_open_for_registration();
                ?>
                <div class="card-vx d-flex gap-4 align-items-start flex-wrap" <?php echo $completo ? 'style="opacity:.7"' : ''; ?>>
                    <div class="text-center event-date-col">
                        <div class="subsection-label" style="margin-bottom:2px"><?php echo esc_html( strtoupper( date_i18n( 'M', $fecha_ts ) ) ); ?></div>
                        <div class="stat-num-vx <?php echo $completo ? '' : 'stat-num-vx--success'; ?> event-date-day" <?php echo $completo ? 'style="color:var(--linea)"' : ''; ?>><?php echo esc_html( date_i18n( 'j', $fecha_ts ) ); ?></div>
                        <div class="text-xs-muted"><?php echo esc_html( strtoupper( date_i18n( 'D', $fecha_ts ) ) ); ?></div>
                    </div>
                    <div class="event-card-body">
                        <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                            <h3 class="fw-semibold" style="font-size:15px;margin:0">Vitrinexo 4Dinner <?php echo esc_html( $dinner->get_ciudad() ); ?></h3>
                            <?php if ( $user_asig ) : ?>
                                <span class="badge-vx badge-success">Confirmado</span>
                            <?php elseif ( $completo ) : ?>
                                <span class="badge-vx badge-neutral">Completo</span>
                            <?php elseif ( $deadline_passed && ! $user_asig ) : ?>
                                <span class="badge-vx badge-neutral">Inscripciones cerradas</span>
                            <?php else : ?>
                                <span class="badge-vx badge-primary">Cupos disponibles</span>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex flex-wrap gap-3 mb-2 text-sm-muted">
                            <span><i class="ti ti-map-pin me-1 <?php echo ( $completo || $deadline_passed ) ? '' : 'ic-success'; ?>"></i><?php echo esc_html( $dinner->get_ciudad() . ', ' . $dinner->get_pais() ); ?></span>
                            <span><i class="ti ti-clock me-1 <?php echo ( $completo || $deadline_passed ) ? '' : 'ic-success'; ?>"></i>20:00 hora local</span>
                            <?php if ( $deadline_ts && ! $user_asig ) : ?>
                            <span style="color:<?php echo $deadline_passed ? 'var(--terracota-presion)' : 'var(--mantequilla-presion)'; ?>">
                                <i class="ti ti-calendar-x me-1"></i>
                                <?php if ( $deadline_passed ) : ?>
                                Inscripciones cerradas
                                <?php else : ?>
                                Cierra <?php echo esc_html( date_i18n( 'j M · H:i', $deadline_ts ) ); ?>
                                <?php endif; ?>
                            </span>
                            <?php endif; ?>
                            <span><i class="ti ti-users me-1 <?php echo $completo ? '' : 'ic-success'; ?>"></i>
                                <?php echo $completo ? '4/4 · Completo' : esc_html( $cupos_disp . ( $cupos_disp === 1 ? ' cupo libre' : ' cupos libres' ) ); ?>
                            </span>
                        </div>
                        <?php if ( $dinner->get_restaurante() ) : ?>
                        <p class="text-body-muted mb-3"><?php echo esc_html( $dinner->get_restaurante() ); ?></p>
                        <?php endif; ?>
                        <?php if ( count( $asignados ) > 0 ) : ?>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <div class="avatar-group">
                                <?php foreach ( array_slice( $asignados, 0, 8 ) as $uid ) :
                                    $au = VX_User::get( (int) $uid );
                                    $slug = $au ? $au->get_slug() : '';
                                    $foto = $au ? $au->get_foto_url( 'vx-avatar' ) : get_avatar_url( $uid, [ 'size' => 40 ] );
                                    $nombre = $au ? $au->get_nombre_completo() : '';
                                ?>
                                <?php if ( $slug ) : ?>
                                <a href="<?php echo esc_url( home_url( '/perfil/' . $slug . '/' ) ); ?>" title="<?php echo esc_attr( $nombre ); ?>">
                                  <div class="av"><img src="<?php echo esc_url( $foto ); ?>" alt="<?php echo esc_attr( $nombre ); ?>"></div>
                                </a>
                                <?php else : ?>
                                <div class="av"><img src="<?php echo esc_url( $foto ); ?>" alt=""></div>
                                <?php endif; ?>
                                <?php endforeach; ?>
                                <?php if ( count( $asignados ) > 8 ) : ?>
                                <div class="av" style="background:var(--linea);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:var(--color-text-secondary)">+<?php echo count($asignados)-8; ?></div>
                                <?php endif; ?>
                            </div>
                            <span class="text-sm-muted">
                                <?php echo count( $asignados ); ?> confirmado<?php echo count( $asignados ) !== 1 ? 's' : ''; ?>
                                <?php if ( count( $asignados ) >= 4 ) : ?>
                                <span class="badge-vx badge-success" style="font-size:10px;margin-left:4px">Cena confirmada</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="event-card-cta">
                        <?php if ( $user_asig ) : ?>
                            <span class="text-sm-muted" style="font-size:13px">Recibirás los detalles<br>por email</span>
                        <?php elseif ( ! $inscripciones_open ) : ?>
                            <button class="btn-vx btn-ghost-vx btn-vx-sm" disabled style="opacity:.5;cursor:not-allowed">
                                <i class="ti ti-lock me-1"></i> Inscripciones cerradas
                            </button>
                        <?php elseif ( $completo ) : ?>
                            <button class="btn-vx btn-ghost-vx btn-vx-sm vx-dinner-interes-btn <?php echo $user_inter ? 'vx-dinner-interes-btn--active' : ''; ?>"
                                    data-dinner-id="<?php echo esc_attr( $dinner->get_id() ); ?>" data-activo="<?php echo $user_inter ? '1' : '0'; ?>">
                                <i class="ti ti-list"></i> <?php echo $user_inter ? 'En lista de espera' : 'Lista de espera'; ?>
                            </button>
                        <?php else : ?>
                            <button class="btn-vx btn-primary-vx btn-vx-sm vx-dinner-interes-btn <?php echo $user_inter ? 'vx-dinner-interes-btn--active' : ''; ?>"
                                    data-dinner-id="<?php echo esc_attr( $dinner->get_id() ); ?>" data-activo="<?php echo $user_inter ? '1' : '0'; ?>">
                                <i class="ti ti-send"></i> <?php echo $user_inter ? 'Quitar interés' : 'Quiero ir'; ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else : ?>
            <div class="empty-state-vx py-4">
                <i class="ti ti-restaurant empty-state-vx__icon"></i>
                <h2 class="empty-state-vx__title">No hay eventos próximos</h2>
                <p class="empty-state-vx__desc">Cuando se creen nuevas cenas de Vitrinexo 4Dinner recibirás una notificación.</p>
            </div>
            <?php endif; ?>
        </section>

        <!-- FORMULARIO DE INTERÉS -->
        <section id="form-interes" class="mb-5">
            <div class="row justify-content-center">
                <div class="col-12 col-lg-8">
                    <div class="card-vx">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="icon-box icon-box--sm icon-box--green" style="width:44px;height:44px;display:flex;align-items:center;justify-content:center;background:var(--salvia-suave);border-radius:10px;flex-shrink:0">
                                <i class="ti ti-tools-kitchen-2" style="font-size:22px;color:var(--salvia-hover)"></i>
                            </div>
                            <div>
                                <h2 class="subsection-title" style="margin:0">Quiero participar en un Vitrinexo 4Dinner</h2>
                                <p class="text-body-muted" style="margin:0">El equipo Vitrinexo te asignará a la próxima mesa disponible</p>
                            </div>
                        </div>
                        <?php
                        $confirmed = ! empty( $_GET['dinner_confirmado'] );
                        $declined  = ! empty( $_GET['dinner_rechazado'] );
                        $err       = isset( $_GET['dinner_error'] ) ? sanitize_key( $_GET['dinner_error'] ) : '';

                        if ( $confirmed ) : ?>
                        <div class="alert-vx alert-success mb-4">
                            <i class="ti ti-circle-check"></i>
                            <span>¡Perfecto! Tu asistencia fue confirmada. Recibirás un correo con los detalles de la cena.</span>
                        </div>
                        <?php elseif ( $declined ) : ?>
                        <div class="alert-vx alert-info mb-4">
                            <i class="ti ti-info-circle"></i>
                            <span>Lamentamos que no puedas asistir. Tu cupo quedó disponible para otro miembro.</span>
                        </div>
                        <?php elseif ( $err ) : ?>
                        <div class="alert-vx alert-error mb-4">
                            <i class="ti ti-alert-circle"></i>
                            <span>El enlace no es válido o ya fue utilizado.</span>
                        </div>
                        <?php else : ?>
                        <div class="alert-vx alert-info mb-4">
                            <i class="ti ti-info-circle"></i>
                            <span>Selecciona el evento que te interesa y cuéntanos brevemente por qué quieres participar.</span>
                        </div>
                        <?php endif; ?>

                        <?php if ( $dinners ) : ?>
                        <div class="mb-3">
                            <label class="form-label-vx">Evento de interés</label>
                            <select class="form-control-vx" id="dinner-select-id">
                                <option value="">Selecciona un evento...</option>
                                <?php foreach ( $dinners as $d ) :
                                    $cupos = max( 0, 4 - count( $d->get_asignados() ) );
                                    if ( $cupos === 0 ) continue;
                                ?>
                                <option value="<?php echo $d->get_id(); ?>">
                                    <?php echo esc_html( date_i18n( 'd M', $d->get_fecha() ) . ' · Vitrinexo 4Dinner ' . $d->get_ciudad() . ' (' . $cupos . ' cupo' . ( $cupos > 1 ? 's' : '' ) . ')' ); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label-vx">Mensaje (opcional)</label>
                            <textarea class="form-control-vx" id="dinner-mensaje" rows="3"
                                placeholder="Cuéntanos sobre tu perfil, intereses o cualquier preferencia para la mesa..."></textarea>
                        </div>
                        <div id="dinner-interes-msg" class="d-none mb-3"></div>
                        <button class="btn-vx btn-primary-vx btn-vx-md w-100" id="dinner-interes-btn">
                            <i class="ti ti-send me-1"></i> Registrar mi interés
                        </button>
                        <p class="text-xs-muted text-center mt-2">El equipo revisará tu solicitud y te contactará en 24–48 horas hábiles.</p>

                        <script>
                        document.getElementById('dinner-interes-btn').addEventListener('click', function(){
                            var dinnerId = document.getElementById('dinner-select-id').value;
                            var mensaje  = document.getElementById('dinner-mensaje').value.trim();
                            var msgEl    = document.getElementById('dinner-interes-msg');
                            if (!dinnerId) { msgEl.textContent='Selecciona un evento.'; msgEl.className='mb-3 small text-danger'; return; }
                            this.disabled = true;
                            this.textContent = 'Enviando...';
                            fetch(<?php echo wp_json_encode( $api_base ); ?> + 'dinners/' + dinnerId + '/interes', {
                                method: 'POST',
                                headers: {'Content-Type':'application/json','X-WP-Nonce': <?php echo wp_json_encode( $nonce_rest ); ?>},
                                body: JSON.stringify({mensaje: mensaje})
                            }).then(r=>r.json()).then(d=>{
                                if(d.success){
                                    // Reemplazar el formulario con mensaje de confirmación prominente
                                    var formArea = document.getElementById('dinner-interes-btn').closest('.card-vx');
                                    if(formArea){
                                        formArea.innerHTML = '<div class="text-center py-4">'
                                            + '<div style="font-size:48px;margin-bottom:12px"><i class="ti ti-tools-kitchen-2" aria-hidden="true"></i></div>'
                                            + '<h3 style="color:var(--color-text-primary);margin-bottom:8px">¡Interés registrado!</h3>'
                                            + '<p style="color:var(--color-text-secondary);max-width:440px;margin:0 auto 16px">Tu solicitud fue recibida. El equipo de Vitrinexo te asignará a la próxima mesa disponible en tu ciudad.</p>'
                                            + '<div class="alert-vx alert-info d-inline-flex gap-2 align-items-start text-start" style="max-width:440px">'
                                            + '<i class="ti ti-bell" style="font-size:18px;flex-shrink:0;margin-top:1px"></i>'
                                            + '<span><strong>¿Qué sigue?</strong> Cuando el equipo te asigne una mesa recibirás un <strong>correo de confirmación</strong> con los detalles del restaurante y tus compañeros de cena. También verás una <strong>notificación</strong> en la plataforma. Revisa tu bandeja de entrada.</span>'
                                            + '</div>'
                                            + '</div>';
                                    }
                                } else {
                                    var msg = d.message || (d.error==='ya_registrado' ? 'Ya tienes un interés registrado para este evento. Revisa tu correo o las notificaciones.' : 'Error al enviar.');
                                    msgEl.innerHTML = '<i class="ti ti-alert-circle me-1"></i>' + msg;
                                    msgEl.className='mb-3 small text-danger d-flex align-items-center gap-1';
                                    document.getElementById('dinner-interes-btn').disabled=false;
                                    document.getElementById('dinner-interes-btn').innerHTML='<i class="ti ti-send me-1"></i> Registrar mi interés';
                                }
                            }).catch(()=>{
                                msgEl.textContent='Error de red. Intenta de nuevo.'; msgEl.className='mb-3 small text-danger';
                                document.getElementById('dinner-interes-btn').disabled=false;
                                document.getElementById('dinner-interes-btn').innerHTML='<i class="ti ti-send me-1"></i> Registrar mi interés';
                            });
                        });
                        </script>
                        <?php else : ?>
                        <p class="text-body-muted">No hay eventos con cupos disponibles en este momento. Vuelve pronto.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- EVENTOS PASADOS -->
        <?php if ( $pasados ) : ?>
        <section class="mb-5">
            <div class="mb-3">
                <span class="subsection-label">Historia</span>
                <h2 class="subsection-title">Eventos pasados</h2>
            </div>
            <div class="row g-3">
                <?php foreach ( array_slice( $pasados, 0, 4 ) as $i => $dinner ) :
                    $grad     = $gradients[ $i % 4 ];
                    $asig     = $dinner->get_asignados();
                    $fecha_ts = strtotime( $dinner->get_fecha() );
                ?>
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="card-vx p-0 overflow-hidden">
                        <div class="event-past-header" style="background:<?php echo esc_attr( $grad ); ?>">
                            <div>
                                <div class="event-past-header__month"><?php echo esc_html( date_i18n( 'F Y', $fecha_ts ) ); ?></div>
                                <div class="event-past-header__city"><?php echo esc_html( $dinner->get_ciudad() ); ?></div>
                            </div>
                            <span class="badge-overlay">Realizado</span>
                        </div>
                        <div class="event-past-card-body">
                            <?php if ( $asig ) : ?>
                            <div class="avatar-group mb-2">
                                <?php foreach ( array_slice( $asig, 0, 4 ) as $uid ) : ?>
                                <div class="av"><img src="<?php echo esc_url( get_avatar_url( $uid, [ 'size' => 40 ] ) ); ?>" alt=""></div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            <?php if ( $dinner->get_restaurante() ) : ?>
                            <div class="text-xs-muted"><i class="ti ti-map-pin me-1 ic-success"></i><?php echo esc_html( $dinner->get_restaurante() . ', ' . $dinner->get_ciudad() ); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

    </div>
    <?php
    return ob_get_clean();
} );

// [vx_comunidad] — página de comunidad (out2b, woman, senior)
add_shortcode( 'vx_comunidad', function ( $atts ): string {
    $atts = shortcode_atts( [ 'slug' => '' ], $atts );
    $slug = sanitize_key( $atts['slug'] );

    if ( ! in_array( $slug, [ 'out2b', 'woman', 'senior' ], true ) ) return '';

    $user_id  = get_current_user_id();
    $user     = VX_User::get( $user_id );
    $is_member = $user && $user->is_in_community( $slug );

    // Acceso restringido: solo miembros de la comunidad (o administradores) pueden verla,
    // aunque tengan el enlace directo.
    if ( ! $is_member && ! current_user_can( 'manage_options' ) ) {
        $com_label = [ 'out2b' => 'Vitrinexo LGBTQ+', 'woman' => 'Vitrinexo Woman', 'senior' => 'Vitrinexo Senior' ][ $slug ] ?? '';
        ob_start();
        ?>
        <div class="container py-5" style="max-width:560px">
          <div class="card-vx text-center" style="padding:36px 28px">
            <div class="comunidad-icon comunidad-icon--inactive" style="margin:0 auto 1rem"><i class="ti ti-lock"></i></div>
            <h2 style="font-size:1.3rem;font-weight:700;color:var(--color-text-primary);margin:0 0 .5rem">Comunidad privada</h2>
            <p class="text-body-muted" style="margin-bottom:1.25rem">La comunidad <?php echo esc_html( $com_label ); ?> es solo para sus miembros. Las comunidades se asignan según tu perfil, no es posible unirse manualmente.</p>
            <a href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>" class="btn-vx btn-primary-vx btn-vx-sm">Volver al dashboard</a>
          </div>
        </div>
        <?php
        return ob_get_clean();
    }

    $pagina     = max( 1, (int) ( $_GET['pagina'] ?? 1 ) );
    $result     = VX_Community::get_members( $slug, [ 'page' => $pagina ] );
    $members    = array_map( fn( $u ) => $u->to_card_array(), $result['users'] ?? [] );
    $pagination = $result['pagination'] ?? [];

    $meta = [
        'out2b'  => [
            'titulo'      => 'Vitrinexo LGBTQ+',
            'descripcion' => 'Un espacio de networking seguro para profesionales LGBTQ+ en el mundo de los negocios hispanohablante. Conecta con pares que comparten tu experiencia y tu visión.',
            'label'       => 'Comunidad vertical',
        ],
        'woman'  => [
            'titulo'      => 'Vitrinexo Woman',
            'descripcion' => 'Comunidad de empresarias y directivas de habla hispana. Un espacio de apoyo, colaboración y crecimiento para mujeres líderes en el mundo empresarial.',
            'label'       => 'Comunidad vertical',
        ],
        'senior' => [
            'titulo'      => 'Vitrinexo Senior',
            'descripcion' => 'Espacio exclusivo para ejecutivos y empresarios con trayectoria consolidada. Más de 15 años de experiencia verificada como requisito.',
            'label'       => 'Comunidad verificada',
        ],
    ];

    $m = $meta[ $slug ];
    $total = $result['total'] ?? count( $members );

    ob_start();
    ?>
    <div class="community-header-vx community-header-vx--<?php echo esc_attr( $slug ); ?>">
      <div class="container">
        <div class="community-header-vx__inner">
          <div>
            <span class="community-header-vx__label"><?php echo esc_html( $m['label'] ); ?></span>
            <h1 class="community-header-vx__title"><?php echo esc_html( $m['titulo'] ); ?></h1>
            <p class="community-header-vx__desc"><?php echo esc_html( $m['descripcion'] ); ?></p>
          </div>
          <span class="community-header-vx__count"><?php echo $total; ?> miembro<?php echo 1 !== $total ? 's' : ''; ?></span>
        </div>
      </div>
    </div>

    <main>
    <div class="container py-4">

      <?php if ( $members ) : ?>
      <div class="page-section-heading mb-3">
        <h2 class="page-section-heading__title">Miembros de <?php echo esc_html( $m['titulo'] ); ?></h2>
        <span class="badge-vx badge-neutral"><?php echo $total; ?> perfiles</span>
      </div>

      <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5 g-2 mb-5">
        <?php foreach ( $members as $m_card ) : ?>
        <div class="col"><?php echo vx_render_member_card( $m_card, $user_id ); ?></div>
        <?php endforeach; ?>
      </div>

      <?php if ( ! empty( $pagination ) && ( $pagination['total_pages'] ?? 1 ) > 1 ) : ?>
      <nav class="d-flex justify-content-center gap-2 mt-4">
        <?php for ( $i = 1; $i <= $pagination['total_pages']; $i++ ) : ?>
        <a href="<?php echo esc_url( add_query_arg( 'pagina', $i ) ); ?>"
           class="btn-vx <?php echo $i === $pagina ? 'btn-primary-vx' : 'btn-ghost-vx'; ?> btn-vx-sm"><?php echo $i; ?></a>
        <?php endfor; ?>
      </nav>
      <?php endif; ?>

      <?php else : ?>
      <div class="empty-state-vx py-5 text-center">
        <div class="empty-state-vx__icon"><i class="ti ti-users-group"></i></div>
        <p class="empty-state-vx__title">Aún no hay miembros en esta comunidad</p>
        <p class="empty-state-vx__desc">Sé el primero en unirte y construir esta red.</p>
      </div>
      <?php endif; ?>

    </div>
    </main>
    <?php echo vx_modal_conectar_html(); ?>
    <?php
    return ob_get_clean();
} );

// ─────────────────────────────────────────────────────────────────────────────
// [vx_mis_eventos] — Historial de 4Dinners a los que el usuario asistió
// Solo muestra dinners donde el usuario es asignado Y estado = 'realizado'.
// Compañeros de mesa solo visibles en eventos realizados.
// ─────────────────────────────────────────────────────────────────────────────
add_shortcode( 'vx_mis_eventos', function (): string {
    $guard = VX_Auth::check_access();
    if ( $guard ) return $guard;

    $user_id = get_current_user_id();
    $user    = VX_User::get( $user_id );
    if ( ! $user ) return '';

    // ── Próximo dinner confirmado (el usuario está asignado, aún no ocurre) ──────
    $proximos_posts = get_posts( [
        'post_type'      => 'vx_dinner',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [
            'relation' => 'AND',
            [ 'key' => VX_Dinner_Meta::ESTADO, 'value' => [ 'abierto', 'confirmado' ], 'compare' => 'IN' ],
            [ 'key' => VX_Dinner_Meta::FECHA,  'value' => time(), 'compare' => '>=', 'type' => 'NUMERIC' ],
        ],
        'meta_key'   => VX_Dinner_Meta::FECHA,
        'orderby'    => 'meta_value_num',
        'order'      => 'ASC',
    ] );

    $proximo_dinner = null;
    foreach ( $proximos_posts as $post ) {
        $d = VX_Dinner::get( $post->ID );
        if ( $d && in_array( $user_id, $d->get_asignados(), true ) ) {
            $proximo_dinner = $d;
            break; // solo el más próximo
        }
    }

    // ── Historial (dinners realizados en que asistió) ──────────────────────────
    $posts = get_posts( [
        'post_type'      => 'vx_dinner',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [
            [
                'key'     => VX_Dinner_Meta::ESTADO,
                'value'   => 'realizado',
                'compare' => '=',
            ],
        ],
        'meta_key'   => VX_Dinner_Meta::FECHA,
        'orderby'    => 'meta_value_num',
        'order'      => 'DESC',
    ] );

    $mis_dinners = [];
    foreach ( $posts as $post ) {
        $dinner = VX_Dinner::get( $post->ID );
        if ( ! $dinner ) continue;
        if ( in_array( $user_id, $dinner->get_asignados(), true ) ) {
            $mis_dinners[] = $dinner;
        }
    }

    ob_start();
    ?>
    <!-- PAGE HEADER -->
    <div class="page-header-vx page-header-vx--4dinner">
        <div class="container">
            <div class="page-header-vx__inner">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge-vx badge-4dinner-light">
                            <i class="ti ti-tools-kitchen-2 me-1"></i> Historial
                        </span>
                    </div>
                    <h1 class="page-header-vx__title">Mis cenas</h1>
                    <p class="page-header-vx__lead">Cenas de networking en las que participaste</p>
                </div>
                <a href="<?php echo esc_url( home_url( '/4dinner/' ) ); ?>" class="btn-vx btn-vx-sm btn-4dinner-ghost">
                    <i class="ti ti-calendar-event me-1"></i> Próximos eventos
                </a>
            </div>
        </div>
    </div>

    <div class="container py-4">

    <?php if ( $proximo_dinner ) :
        $pd_fecha    = $proximo_dinner->get_fecha();
        $pd_ciudad   = $proximo_dinner->get_ciudad();
        $pd_pais     = $proximo_dinner->get_pais();
        $pd_rest     = $proximo_dinner->get_restaurante();
        $pd_dir      = $proximo_dinner->get_direccion();
        $pd_asig     = $proximo_dinner->get_asignados();
        $pd_mesa     = $proximo_dinner->get_user_mesa( $user_id );
        $pd_estado   = $proximo_dinner->get_estado();
        $pd_dias     = (int) floor( ( $pd_fecha - time() ) / DAY_IN_SECONDS );

        // Compañeros de mesa del próximo evento
        $pd_companeros = [];
        if ( $pd_mesa ) {
            foreach ( (array) ( $pd_mesa['asignados'] ?? [] ) as $cid ) {
                if ( (int) $cid === $user_id ) continue;
                $c = VX_User::get( (int) $cid );
                if ( $c ) $pd_companeros[] = $c;
            }
        }
    ?>
    <!-- ── PRÓXIMO DINNER CONFIRMADO ─────────────────────────────────── -->
    <div class="mb-5">
        <div class="d-flex align-items-center gap-2 mb-3">
            <span class="subsection-label">Confirmado</span>
            <h2 class="subsection-title mb-0">Tu próximo Vitrinexo 4Dinner</h2>
            <?php if ( $pd_dias === 0 ) : ?>
            <span class="badge-vx badge-4dinner-light" style="font-size:11px;font-weight:700">¡Es hoy!</span>
            <?php elseif ( $pd_dias === 1 ) : ?>
            <span class="badge-vx badge-4dinner-light" style="font-size:11px">Mañana</span>
            <?php elseif ( $pd_dias <= 7 ) : ?>
            <span class="badge-vx badge-4dinner-light" style="font-size:11px">En <?php echo $pd_dias; ?> días</span>
            <?php endif; ?>
        </div>

        <div class="card-vx p-0 overflow-hidden">
            <!-- Cabecera -->
            <div style="background:var(--mantequilla-suave);border-bottom:3px solid var(--mantequilla-presion);padding:1.25rem 1.5rem">
                <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge-vx badge-success" style="font-size:11px">Estás confirmado</span>
                            <?php if ( 'confirmado' === $pd_estado ) : ?>
                            <span class="badge-vx" style="background:var(--mantequilla);color:var(--color-text-primary);font-size:11px">Cena confirmada</span>
                            <?php endif; ?>
                        </div>
                        <h3 style="margin:0;font-size:1.25rem;font-weight:700;color:var(--color-text-primary)">
                            Vitrinexo 4Dinner <?php echo esc_html( $pd_ciudad ); ?>
                        </h3>
                    </div>
                    <!-- Fecha destacada -->
                    <div class="text-center" style="background:var(--blanco);border:2px solid var(--mantequilla);border-radius:var(--radius-md);padding:.75rem 1.25rem;min-width:90px">
                        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:var(--ls-sobretitulo-xs);color:var(--mantequilla-presion)"><?php echo esc_html( strtoupper( date_i18n( 'M', $pd_fecha ) ) ); ?></div>
                        <div style="font-size:2.2rem;font-weight:800;line-height:1;color:var(--color-text-primary)"><?php echo esc_html( date_i18n( 'j', $pd_fecha ) ); ?></div>
                        <div style="font-size:11px;color:var(--color-text-primary)"><?php echo esc_html( strtoupper( date_i18n( 'D', $pd_fecha ) ) ); ?></div>
                    </div>
                </div>
            </div>

            <!-- Datos del evento -->
            <div class="p-4">
                <div class="row g-4">
                    <!-- Info logística -->
                    <div class="col-12 col-md-<?php echo $pd_companeros ? '6' : '12'; ?>">
                        <h4 class="subsection-label mb-3">Detalles del evento</h4>
                        <div class="d-flex flex-column gap-2" style="font-size:.93rem">
                            <div class="d-flex align-items-center gap-2">
                                <i class="ti ti-clock" style="color:var(--mantequilla-presion);font-size:18px;flex-shrink:0"></i>
                                <div>
                                    <span style="font-weight:600"><?php echo esc_html( date_i18n( 'l j \d\e F \d\e Y', $pd_fecha ) ); ?></span>
                                    <span style="color:var(--color-text-secondary)"> · 20:00 hora local</span>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <i class="ti ti-map-pin" style="color:var(--mantequilla-presion);font-size:18px;flex-shrink:0"></i>
                                <span><?php echo esc_html( $pd_ciudad . ', ' . $pd_pais ); ?></span>
                            </div>
                            <?php if ( $pd_rest ) : ?>
                            <div class="d-flex align-items-center gap-2">
                                <i class="ti ti-building" style="color:var(--mantequilla-presion);font-size:18px;flex-shrink:0"></i>
                                <span style="font-weight:600"><?php echo esc_html( $pd_rest ); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ( $pd_dir ) : ?>
                            <div class="d-flex align-items-start gap-2">
                                <i class="ti ti-route" style="color:var(--mantequilla-presion);font-size:18px;flex-shrink:0;margin-top:2px"></i>
                                <span style="color:var(--color-text-secondary)"><?php echo esc_html( $pd_dir ); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ( ! $pd_rest ) : ?>
                            <div class="d-flex align-items-center gap-2 mt-1">
                                <i class="ti ti-info-circle" style="color:var(--mantequilla-presion);font-size:16px;flex-shrink:0"></i>
                                <span style="font-size:.82rem;color:var(--color-text-secondary)">El restaurante se confirmará próximamente por email.</span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Recordatorio -->
                        <div class="mt-3 p-3 rounded" style="background:var(--mantequilla-suave);border:1px solid var(--mantequilla);font-size:.82rem;color:var(--color-text-primary)">
                            <i class="ti ti-circle-check me-1"></i>
                            Cada quien paga su consumo. Sin agenda formal, la conversación fluye sola.
                        </div>
                    </div>

                    <!-- Compañeros de mesa -->
                    <?php if ( $pd_companeros ) : ?>
                    <div class="col-12 col-md-6">
                        <h4 class="subsection-label mb-3">
                            <?php echo $pd_mesa ? esc_html( $pd_mesa['nombre'] ?? 'Tu mesa' ) : 'Tus compañeros'; ?>
                        </h4>
                        <div class="d-flex flex-column gap-2">
                            <?php foreach ( $pd_companeros as $comp ) :
                                $comp_slug    = $comp->get_slug();
                                $comp_empresa = $comp->get_empresa_principal();
                                $comp_cargo   = $comp_empresa ? (string) get_post_meta( $comp_empresa->get_id(), 'vx_empresa_cargo', true ) : '';
                                $comp_emp_nom = $comp_empresa ? $comp_empresa->get_nombre() : '';
                                $comp_url     = $comp_slug ? home_url( '/perfil/' . $comp_slug . '/' ) : '';
                            ?>
                            <div class="d-flex align-items-center gap-3 p-2 rounded" style="background:var(--color-surface);border:1px solid var(--color-border)">
                                <?php if ( $comp_url ) : ?><a href="<?php echo esc_url( $comp_url ); ?>"><?php endif; ?>
                                <img src="<?php echo esc_url( $comp->get_foto_url( 'vx-avatar' ) ); ?>"
                                     alt="<?php echo esc_attr( $comp->get_nombre_completo() ); ?>"
                                     style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid var(--color-border);flex-shrink:0">
                                <?php if ( $comp_url ) : ?></a><?php endif; ?>
                                <div style="min-width:0">
                                    <?php if ( $comp_url ) : ?>
                                    <a href="<?php echo esc_url( $comp_url ); ?>" class="fw-semibold text-decoration-none" style="font-size:.9rem;color:var(--color-text-primary)"><?php echo esc_html( $comp->get_nombre_completo() ); ?></a>
                                    <?php else : ?>
                                    <span class="fw-semibold" style="font-size:.9rem"><?php echo esc_html( $comp->get_nombre_completo() ); ?></span>
                                    <?php endif; ?>
                                    <?php if ( $comp_cargo || $comp_emp_nom ) : ?>
                                    <div class="text-truncate" style="font-size:.78rem;color:var(--color-text-secondary)"><?php echo esc_html( implode( ' · ', array_filter( [ $comp_cargo, $comp_emp_nom ] ) ) ); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php elseif ( count( $pd_asig ) > 1 ) : ?>
                    <!-- Asignados pero sin mesas definidas aún -->
                    <div class="col-12 col-md-6">
                        <h4 class="subsection-label mb-3">Comensales</h4>
                        <p style="font-size:.88rem;color:var(--color-text-secondary)">
                            Serán <strong><?php echo count( $pd_asig ); ?> personas</strong> en la cena.
                            Los datos de tus compañeros se mostrarán aquí cuando el equipo asigne las mesas.
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── HISTORIAL ─────────────────────────────────────────────────── -->
    <?php if ( $proximo_dinner ) : ?>
    <div class="d-flex align-items-center gap-2 mb-3">
        <span class="subsection-label">Historial</span>
        <h2 class="subsection-title mb-0">Cenas anteriores</h2>
    </div>
    <?php endif; ?>

    <?php if ( empty( $mis_dinners ) && ! $proximo_dinner ) : ?>
        <div class="empty-state-vx py-5 text-center">
            <div class="empty-state-vx__icon"><i class="ti ti-bowl"></i></div>
            <p class="empty-state-vx__title">Aún no has asistido a ningún Vitrinexo 4Dinner</p>
            <p class="empty-state-vx__desc">Cuando confirmes tu asistencia y la cena se marque como realizada, aparecerá aquí junto a tus compañeros de mesa.</p>
            <a href="<?php echo esc_url( home_url( '/4dinner/' ) ); ?>" class="btn-vx btn-primary-vx btn-vx-sm mt-3">Ver próximas cenas</a>
        </div>
    <?php elseif ( empty( $mis_dinners ) && $proximo_dinner ) : ?>
        <p class="text-sm-muted" style="font-size:13px;padding:.5rem 0">Cuando asistas a tu primer Vitrinexo 4Dinner y el equipo lo marque como realizado, aparecerá aquí.</p>
    <?php else : ?>
        <div class="row g-4">
        <?php foreach ( $mis_dinners as $dinner ) :
            $fecha_ts   = $dinner->get_fecha();
            $asignados  = $dinner->get_asignados();
            $user_mesa  = $dinner->get_user_mesa( $user_id );

            // Compañeros de mesa (excluye al propio usuario)
            $companeros_ids = [];
            if ( $user_mesa ) {
                $companeros_ids = array_values( array_filter(
                    (array) ( $user_mesa['asignados'] ?? [] ),
                    fn( $uid ) => (int) $uid !== $user_id
                ) );
            }
        ?>
        <div class="col-12 col-lg-6">
            <div class="card-vx h-100 p-0 overflow-hidden">

                <!-- Cabecera ámbar -->
                <div style="background:var(--mantequilla-suave);border-bottom:1px solid var(--mantequilla);padding:1rem 1.25rem;">
                    <div class="d-flex align-items-start justify-content-between gap-2 flex-wrap">
                        <div>
                            <span class="badge-vx badge-success mb-1" style="font-size:11px">Asistí</span>
                            <h3 class="mb-0 fw-semibold" style="font-size:1rem;color:var(--color-text-primary)">
                                <?php echo esc_html( $dinner->get_title() ); ?>
                            </h3>
                        </div>
                        <div style="font-size:12px;color:var(--color-text-primary);text-align:right;line-height:1.6">
                            <div><i class="ti ti-calendar me-1"></i><?php echo esc_html( date_i18n( 'j \d\e F Y', $fecha_ts ) ); ?></div>
                            <div><i class="ti ti-map-pin me-1"></i><?php echo esc_html( $dinner->get_ciudad() . ', ' . $dinner->get_pais() ); ?></div>
                            <?php if ( $dinner->get_restaurante() ) : ?>
                            <div><i class="ti ti-building me-1"></i><?php echo esc_html( $dinner->get_restaurante() ); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Cuerpo -->
                <div class="p-3">

                    <?php if ( $user_mesa ) : ?>
                    <p class="subsection-label mb-2"><?php echo esc_html( $user_mesa['nombre'] ?? 'Tu mesa' ); ?></p>
                    <?php if ( ! empty( $companeros_ids ) ) : ?>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ( $companeros_ids as $comp_id ) :
                            $comp         = VX_User::get( (int) $comp_id );
                            if ( ! $comp ) continue;
                            $comp_foto    = $comp->get_foto_url( 'vx-avatar' );
                            $comp_nombre  = $comp->get_nombre_completo();
                            $comp_slug    = $comp->get_slug();
                            $comp_empresa = $comp->get_empresa_principal();
                            $comp_cargo   = $comp_empresa ? (string) get_post_meta( $comp_empresa->get_id(), 'vx_empresa_cargo', true ) : '';
                            $comp_emp_nom = $comp_empresa ? $comp_empresa->get_nombre() : '';
                            $comp_url     = $comp_slug ? home_url( '/perfil/' . $comp_slug . '/' ) : '';
                        ?>
                        <div class="d-flex align-items-center gap-3 p-2 rounded" style="background:var(--color-surface);border:1px solid var(--color-border)">
                            <?php if ( $comp_url ) : ?>
                            <a href="<?php echo esc_url( $comp_url ); ?>" style="flex-shrink:0">
                            <?php else : ?>
                            <div style="flex-shrink:0">
                            <?php endif; ?>
                                <img src="<?php echo esc_url( $comp_foto ); ?>"
                                     alt="<?php echo esc_attr( $comp_nombre ); ?>"
                                     style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid var(--color-border);display:block"
                                     loading="lazy">
                            <?php if ( $comp_url ) : ?>
                            </a>
                            <?php else : ?>
                            </div>
                            <?php endif; ?>
                            <div style="min-width:0">
                                <?php if ( $comp_url ) : ?>
                                <a href="<?php echo esc_url( $comp_url ); ?>" class="fw-semibold text-decoration-none" style="font-size:.9rem;color:var(--color-text-primary)"><?php echo esc_html( $comp_nombre ); ?></a>
                                <?php else : ?>
                                <span class="fw-semibold" style="font-size:.9rem"><?php echo esc_html( $comp_nombre ); ?></span>
                                <?php endif; ?>
                                <?php if ( $comp_cargo || $comp_emp_nom ) : ?>
                                <div class="text-truncate" style="font-size:.78rem;color:var(--color-text-secondary)">
                                    <?php echo esc_html( implode( ' · ', array_filter( [ $comp_cargo, $comp_emp_nom ] ) ) ); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else : ?>
                    <p class="text-sm-muted mb-0" style="font-size:13px">Solo tú estabas asignado a esta mesa.</p>
                    <?php endif; ?>
                    <?php else : ?>
                    <p class="text-sm-muted mb-0" style="font-size:13px">
                        <i class="ti ti-info-circle me-1"></i>
                        <?php if ( count( $asignados ) > 1 ) : ?>
                        Asististe junto a <?php echo count( $asignados ) - 1; ?> persona<?php echo ( count( $asignados ) - 1 ) !== 1 ? 's' : ''; ?> más. Los compañeros de mesa estarán disponibles cuando el admin asigne las mesas.
                        <?php else : ?>
                        Fuiste el único asistente a esta cena.
                        <?php endif; ?>
                    </p>
                    <?php endif; ?>

                </div>

                <!-- Pie -->
                <div class="px-3 pb-3">
                    <div class="text-sm-muted" style="font-size:12px;border-top:1px solid var(--color-border);padding-top:.65rem">
                        <i class="ti ti-users me-1"></i><?php echo count( $asignados ); ?> asistente<?php echo count( $asignados ) !== 1 ? 's' : ''; ?> en total
                    </div>
                </div>

            </div>
        </div>
        <?php endforeach; ?>
        </div><!-- /.row -->
    <?php endif; ?>
    </div><!-- /.container -->
    <?php
    return ob_get_clean();
} );
