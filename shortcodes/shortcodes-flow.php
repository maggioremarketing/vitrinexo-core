<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// [vx_login] — formulario de login + registro con tabs
add_shortcode( 'vx_login', function (): string {
    if ( is_user_logged_in() ) {
        wp_safe_redirect( home_url( '/dashboard/' ) );
        exit;
    }

    $tab   = isset( $_GET['tab'] ) && 'registro' === $_GET['tab'] ? 'registro' : 'login';
    $error = isset( $_GET['error'] ) ? sanitize_key( $_GET['error'] ) : '';

    ob_start();
    ?>
    <div class="vx-auth-page">
        <div class="container-sm py-5">
            <div class="vx-auth-card">

                <div class="vx-auth-card__logo mb-4">
                    <img src="<?php echo esc_url( vx_logo_url() ); ?>" alt="Vitrinexo" height="40">
                </div>

                <!-- Tabs -->
                <div class="vx-tabs mb-4">
                    <button class="vx-tab <?php echo 'login' === $tab ? 'vx-tab--active' : ''; ?>" data-tab="login">Iniciar sesión</button>
                    <button class="vx-tab <?php echo 'registro' === $tab ? 'vx-tab--active' : ''; ?>" data-tab="registro">Crear cuenta</button>
                </div>

                <?php if ( $error ) : ?>
                    <div class="vx-alert vx-alert--error mb-3">
                        <?php
                        $error_messages = [
                            'credenciales_invalidas' => 'Email o contraseña incorrectos.',
                            'usuario_no_encontrado'  => 'No encontramos ese usuario.',
                            'token_invalido'         => 'El enlace de activación es inválido o expiró.',
                            'cuenta_pendiente'       => 'Tu cuenta está pendiente de verificación.',
                        ];
                        echo esc_html( $error_messages[ $error ] ?? 'Ha ocurrido un error.' );
                        ?>
                    </div>
                <?php endif; ?>

                <!-- Panel Login -->
                <div class="vx-tab-panel <?php echo 'login' === $tab ? 'vx-tab-panel--active' : ''; ?>" data-panel="login">
                    <form id="vx-login-form" class="vx-form" novalidate>
                        <div class="vx-form__group">
                            <label class="vx-form__label" for="login-email">Correo electrónico</label>
                            <input type="email" id="login-email" name="email" class="vx-form__input" required autocomplete="email">
                        </div>
                        <div class="vx-form__group">
                            <label class="vx-form__label" for="login-password">
                                Contraseña
                                <a href="<?php echo esc_url( home_url( '/recuperar-contrasena/' ) ); ?>" class="vx-form__label-link">¿Olvidaste tu contraseña?</a>
                            </label>
                            <div style="position:relative">
                                <input type="password" id="login-password" name="password" class="vx-form__input" required autocomplete="current-password" style="padding-right:40px">
                                <button type="button" id="login-pwd-toggle" tabindex="-1" aria-label="Mostrar u ocultar contraseña" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--color-text-muted);padding:4px">
                                    <i class="ti ti-eye" id="login-pwd-icon" style="font-size:16px"></i>
                                </button>
                            </div>
                        </div>
                        <!-- Recordar + recuperar -->
                        <div class="d-flex align-items-center justify-content-between mt-3 mb-1">
                            <label class="d-flex align-items-center gap-2" style="cursor:pointer;font-size:14px;color:var(--color-text-secondary)">
                                <input type="checkbox" id="login-remember" name="remember" value="1" style="accent-color:var(--color-primary)">
                                Recordar en este dispositivo
                            </label>
                        </div>
                        <div id="vx-login-error" class="vx-alert vx-alert--error d-none"></div>
                        <button type="submit" class="btn-vx btn-vx--primary btn-vx-md w-100 mt-2">Iniciar sesión</button>
                    </form>
                </div>

                <!-- Panel Registro -->
                <div class="vx-tab-panel <?php echo 'registro' === $tab ? 'vx-tab-panel--active' : ''; ?>" data-panel="registro">
                    <form id="vx-registro-form" class="vx-form" novalidate>
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="vx-form__group">
                                    <label class="vx-form__label" for="reg-nombre">Nombre</label>
                                    <input type="text" id="reg-nombre" name="nombre" class="vx-form__input" required>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="vx-form__group">
                                    <label class="vx-form__label" for="reg-apellido">Apellido</label>
                                    <input type="text" id="reg-apellido" name="apellido" class="vx-form__input" required>
                                </div>
                            </div>
                        </div>
                        <div class="vx-form__group">
                            <label class="vx-form__label" for="reg-email">Correo electrónico</label>
                            <input type="email" id="reg-email" name="email" class="vx-form__input" required autocomplete="email">
                        </div>
                        <div class="vx-form__group">
                            <label class="vx-form__label" for="reg-password">Contraseña <small class="text-muted">(mínimo 8 caracteres)</small></label>
                            <input type="password" id="reg-password" name="password" class="vx-form__input" required minlength="8">
                        </div>
                        <div class="vx-form__group">
                            <label class="vx-form__label" for="reg-pais">País <span style="color:var(--rosa)">*</span></label>
                            <select id="reg-pais" name="pais" class="vx-form__input" required>
                                <option value="">Selecciona tu país</option>
                                <?php foreach ( vx_get_paises_latam() as $p ) : ?>
                                <option value="<?php echo esc_attr( $p ); ?>"><?php echo esc_html( $p ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="vx-form__group">
                            <label class="vx-form__label" for="reg-telefono">
                                Teléfono <span style="color:var(--rosa)">*</span>
                                <small class="text-muted">(necesario para coordinar eventos)</small>
                            </label>
                            <?php echo vx_phone_input_html( 'reg-telefono', 'telefono', '' ); ?>
                        </div>
                        <div class="vx-form__group">
                            <label class="vx-form__label" for="reg-empresa">
                                Empresa <span style="color:var(--rosa)">*</span>
                                <small class="text-muted">(ayuda a verificar tu identidad)</small>
                            </label>
                            <input type="text" id="reg-empresa" name="empresa" class="vx-form__input" required placeholder="Nombre de tu empresa o negocio">
                        </div>
                        <div class="vx-form__group">
                            <label class="vx-form__label" for="reg-cargo">
                                Cargo <span style="color:var(--rosa)">*</span>
                            </label>
                            <input type="text" id="reg-cargo" name="cargo" class="vx-form__input" required placeholder="Tu cargo o rol en la empresa">
                        </div>
                        <div class="vx-form__group">
                            <label class="vx-form__label" for="reg-linkedin">
                                LinkedIn <span style="color:var(--rosa)">*</span>
                            </label>
                            <input type="url" id="reg-linkedin" name="linkedin" class="vx-form__input" required placeholder="https://linkedin.com/in/tu-perfil">
                        </div>
                        <!-- Aceptación obligatoria de términos -->
                        <div class="vx-form__group mt-3">
                            <label class="d-flex align-items-start gap-2" style="cursor:pointer;font-size:14px;line-height:1.5">
                                <input type="checkbox" id="reg-terminos" name="terminos" required
                                       style="margin-top:3px;accent-color:var(--color-primary);flex-shrink:0">
                                <span>
                                    He leído y acepto los
                                    <a href="#" class="link-primary-color fw-semibold"
                                       data-bs-toggle="modal" data-bs-target="#modalTerminos"
                                       onclick="return false;">Términos y Condiciones</a>
                                    y la
                                    <a href="#" class="link-primary-color fw-semibold"
                                       data-bs-toggle="modal" data-bs-target="#modalTerminos"
                                       onclick="return false;">Política de Privacidad</a>
                                    de Vitrinexo.
                                </span>
                            </label>
                        </div>
                        <div id="vx-registro-error" class="vx-alert vx-alert--error d-none"></div>
                        <button type="submit" class="btn-vx btn-vx--primary btn-vx-md w-100 mt-3">Crear cuenta</button>
                    </form>

                    <!-- Modal Términos y Condiciones -->
                    <div class="modal fade" id="modalTerminos" tabindex="-1" aria-labelledby="modalTerminosLabel" aria-hidden="true">
                      <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
                        <div class="modal-content modal-vx">
                          <div class="modal-header border-0">
                            <h5 class="modal-title fw-semibold" id="modalTerminosLabel">Términos y Condiciones de Vitrinexo</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                          </div>
                          <div class="modal-body" style="font-size:14px;line-height:1.7;color:var(--color-text-secondary)">

                            <h6 class="fw-semibold text-body-label mb-2">1. Aceptación de los Términos</h6>
                            <p>Al crear una cuenta en Vitrinexo, aceptas quedar vinculado por estos Términos y Condiciones de Uso. Si no estás de acuerdo con alguna de las condiciones aquí establecidas, te pedimos que no uses la plataforma. Vitrinexo se reserva el derecho de modificar estos términos en cualquier momento, notificando los cambios a través de la plataforma.</p>

                            <h6 class="fw-semibold text-body-label mb-2 mt-4">2. Descripción del Servicio</h6>
                            <p>Vitrinexo es una plataforma de networking B2B para profesionales y empresarios hispanohablantes. Facilita la conexión entre miembros a través de un directorio verificado, sistema de matches por afinidad de tags, y eventos presenciales como 4Dinner. El acceso completo está reservado para miembros verificados.</p>

                            <h6 class="fw-semibold text-body-label mb-2 mt-4">3. Registro y Cuenta</h6>
                            <p>Para usar Vitrinexo debes registrarte con datos verídicos y mantenerte como único responsable de la confidencialidad de tu contraseña. Vitrinexo puede rechazar o dar de baja cualquier cuenta que proporcione información falsa, incompleta o que infrinja estos términos. El registro implica la verificación de identidad profesional antes de acceder al directorio completo.</p>

                            <h6 class="fw-semibold text-body-label mb-2 mt-4">4. Uso Aceptable</h6>
                            <p>Los miembros se comprometen a usar Vitrinexo de forma ética y profesional. Está estrictamente prohibido:</p>
                            <ul style="padding-left:1.2rem">
                              <li>Publicar información falsa o engañosa sobre tu empresa o experiencia.</li>
                              <li>Enviar mensajes no solicitados (spam) a otros miembros.</li>
                              <li>Usar la plataforma para actividades ilegales o contrarias a la buena fe comercial.</li>
                              <li>Compartir datos de contacto de otros miembros con terceros sin su consentimiento.</li>
                              <li>Suplantar identidades o crear cuentas en nombre de otras personas.</li>
                            </ul>

                            <h6 class="fw-semibold text-body-label mb-2 mt-4">5. Privacidad y Datos Personales</h6>
                            <p>Vitrinexo recopila y trata tus datos personales conforme a la legislación vigente en materia de protección de datos. Tu información de perfil es visible para otros miembros verificados de la plataforma. Los datos de contacto solo se revelan a conexiones mutuamente aceptadas. Puedes solicitar la eliminación de tus datos en cualquier momento escribiendo a <a href="mailto:hola@vitrinexo.com" class="link-primary-color">hola@vitrinexo.com</a>.</p>

                            <h6 class="fw-semibold text-body-label mb-2 mt-4">6. Propiedad Intelectual</h6>
                            <p>Todo el contenido de Vitrinexo (diseño, textos, código, marca) es propiedad de Vitrinexo SpA o sus licenciantes. Los miembros conservan la propiedad de los contenidos que publican, pero otorgan a Vitrinexo una licencia no exclusiva para mostrarlos dentro de la plataforma.</p>

                            <h6 class="fw-semibold text-body-label mb-2 mt-4">7. Limitación de Responsabilidad</h6>
                            <p>Vitrinexo actúa como intermediario entre profesionales y no garantiza los resultados de las conexiones establecidas. No somos responsables de las relaciones comerciales que se deriven del uso de la plataforma, ni de la veracidad de la información publicada por terceros miembros.</p>

                            <h6 class="fw-semibold text-body-label mb-2 mt-4">8. Cancelación y Baja</h6>
                            <p>Puedes cancelar tu cuenta en cualquier momento desde la sección de Configuración o escribiendo a hola@vitrinexo.com. Vitrinexo puede suspender o eliminar cuentas que incumplan estos términos, sin previo aviso en casos de infracción grave.</p>

                            <h6 class="fw-semibold text-body-label mb-2 mt-4">9. Ley Aplicable</h6>
                            <p>Estos términos se rigen por las leyes de la República de Chile. Cualquier disputa será sometida a los tribunales competentes de Santiago de Chile, salvo acuerdo expreso entre las partes para someterse a otra jurisdicción.</p>

                            <p class="mt-4" style="font-size:12px;color:var(--color-text-tertiary)">Última actualización: Junio 2026 · Vitrinexo SpA · hola@vitrinexo.com</p>

                          </div>
                          <div class="modal-footer border-0">
                            <button type="button" class="btn-vx btn-primary-vx btn-vx-sm" data-bs-dismiss="modal"
                                    onclick="document.getElementById('reg-terminos').checked=true">
                              He leído y acepto
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
} );

// [vx_confirmar_correo] — pantalla de "revisa tu correo"
add_shortcode( 'vx_confirmar_correo', function (): string {
    $user_id = get_current_user_id();
    $email   = $user_id ? wp_get_current_user()->user_email : '';

    ob_start();
    ?>
    <div class="vx-flow-page">
        <div class="container-sm py-5">
            <div class="vx-flow-card text-center">
                <div class="vx-flow-card__icon mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--color-primary)">
                      <path d="M3 7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                      <path d="m3 7 9 6 9-6"/>
                      <path d="m9 14 2 2 4-4"/>
                    </svg>
                </div>
                <h1 class="vx-flow-card__title">Revisa tu correo</h1>
                <p class="vx-flow-card__desc">
                    Enviamos un enlace de activación a <strong><?php echo esc_html( $email ); ?></strong>.<br>
                    Haz clic en el enlace para activar tu cuenta.
                </p>
                <p class="text-muted mt-3">¿No recibiste el correo?</p>
                <?php if ( $user_id ) : ?>
                    <button id="vx-reenviar-token" class="btn-vx btn-vx--ghost">Reenviar correo</button>
                    <div id="vx-reenviar-msg" class="vx-alert mt-3 d-none"></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
    (function () {
        var btn = document.getElementById('vx-reenviar-token');
        var msg = document.getElementById('vx-reenviar-msg');
        if (!btn) return;
        btn.addEventListener('click', function () {
            btn.disabled = true;
            btn.textContent = 'Enviando...';
            fetch('/wp-json/vx/v1/reenviar-token', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': vxData?.nonce || '' },
                credentials: 'same-origin',
            })
            .then(function(r){ return r.json(); })
            .then(function(data) {
                msg.classList.remove('d-none', 'vx-alert--error', 'vx-alert--success');
                if (data.success) {
                    msg.classList.add('vx-alert--success');
                    msg.textContent = 'Correo reenviado. Revisa tu bandeja de entrada.';
                    btn.textContent = 'Correo reenviado';
                } else {
                    msg.classList.add('vx-alert--error');
                    msg.textContent = 'No se pudo reenviar. Intenta más tarde.';
                    btn.disabled = false;
                    btn.textContent = 'Reenviar correo';
                }
            })
            .catch(function() {
                msg.classList.remove('d-none');
                msg.classList.add('vx-alert--error');
                msg.textContent = 'Error de conexión. Intenta más tarde.';
                btn.disabled = false;
                btn.textContent = 'Reenviar correo';
            });
        });
    })();
    </script>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
} );

// [vx_verificacion_pendiente] — pantalla de espera de verificación manual
add_shortcode( 'vx_verificacion_pendiente', function (): string {
    ob_start();
    ?>
    <div class="vx-flow-page">
        <div class="container-sm py-5">
            <div class="vx-flow-card text-center">
                <div class="vx-flow-card__icon mb-4">
                    <i class="ti ti-clock-hour-4"></i>
                </div>
                <h1 class="vx-flow-card__title">Verificación en proceso</h1>
                <p class="vx-flow-card__desc">
                    Tu solicitud fue recibida y está siendo revisada por nuestro equipo.<br>
                    Te notificaremos por correo cuando sea aprobada.
                </p>
                <p class="text-muted mt-3">El proceso toma entre 24 y 72 horas hábiles.</p>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
} );

// [vx_recuperar_contrasena] — solicitar reseteo
add_shortcode( 'vx_recuperar_contrasena', function (): string {
    ob_start();
    ?>
    <div class="vx-auth-page">
        <div class="container-sm py-5">
            <div class="vx-auth-card">
                <a href="<?php echo esc_url( home_url( '/login/' ) ); ?>" class="vx-back-link mb-4 d-block">
                    <i class="ti ti-arrow-left"></i> Volver al login
                </a>
                <h1 class="vx-auth-card__title">Recuperar contraseña</h1>
                <p class="text-muted mb-4">Ingresa tu correo y te enviaremos un enlace para restablecer tu contraseña.</p>
                <form id="vx-recuperar-form" class="vx-form" novalidate>
                    <div class="vx-form__group">
                        <label class="vx-form__label" for="rec-email">Correo electrónico</label>
                        <input type="email" id="rec-email" name="email" class="vx-form__input" required>
                    </div>
                    <div id="vx-recuperar-msg" class="vx-alert d-none mt-2"></div>
                    <button type="submit" class="btn-vx btn-vx--primary btn-vx-md w-100 mt-3">Enviar enlace</button>
                </form>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
} );

// [vx_nueva_contrasena] — ingresar nueva contraseña con el token de WP
add_shortcode( 'vx_nueva_contrasena', function (): string {
    $key    = isset( $_GET['key'] )   ? sanitize_text_field( wp_unslash( $_GET['key'] ) )   : '';
    $login  = isset( $_GET['login'] ) ? sanitize_user( wp_unslash( $_GET['login'] ) ) : '';
    $result = check_password_reset_key( $key, $login );

    ob_start();
    if ( is_wp_error( $result ) ) {
        ?>
        <div class="container-sm py-5 text-center">
            <div class="vx-alert vx-alert--error">El enlace de recuperación es inválido o ha expirado. <a href="<?php echo esc_url( home_url( '/recuperar-contrasena/' ) ); ?>">Solicitar uno nuevo</a>.</div>
        </div>
        <?php
    } else {
        ?>
        <div class="vx-auth-page">
            <div class="container-sm py-5">
                <div class="vx-auth-card">
                    <h1 class="vx-auth-card__title">Nueva contraseña</h1>
                    <form id="vx-nueva-pass-form" class="vx-form" novalidate>
                        <input type="hidden" name="key"   value="<?php echo esc_attr( $key ); ?>">
                        <input type="hidden" name="login" value="<?php echo esc_attr( $login ); ?>">
                        <div class="vx-form__group">
                            <label class="vx-form__label" for="new-pass">Nueva contraseña</label>
                            <input type="password" id="new-pass" name="password" class="vx-form__input" required minlength="8">
                        </div>
                        <div class="vx-form__group">
                            <label class="vx-form__label" for="new-pass-confirm">Confirmar contraseña</label>
                            <input type="password" id="new-pass-confirm" name="password_confirm" class="vx-form__input" required minlength="8">
                        </div>
                        <div id="vx-nueva-pass-msg" class="vx-alert d-none mt-2"></div>
                        <button type="submit" class="btn-vx btn-vx--primary btn-vx-md w-100 mt-3">Guardar contraseña</button>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
    return ob_get_clean();
} );

// [vx_conexion_aceptada] — confirmación de aceptación
add_shortcode( 'vx_conexion_aceptada', function (): string {
    ob_start();
    ?>
    <div class="vx-flow-page">
        <div class="container-sm py-5">
            <div class="vx-flow-card text-center">
                <div class="vx-flow-card__icon vx-flow-card__icon--success mb-4">
                    <i class="ti ti-circle-check"></i>
                </div>
                <h1 class="vx-flow-card__title">¡Conexión aceptada!</h1>
                <p class="vx-flow-card__desc">Se notificó al otro miembro. Ahora pueden ver sus datos de contacto mutuamente.</p>
                <a href="<?php echo esc_url( home_url( '/conexiones/' ) ); ?>" class="btn-vx btn-vx--primary btn-vx-md mt-4">Ver mis conexiones</a>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
} );

// [vx_conexion_rechazada] — confirmación de rechazo (discreta)
add_shortcode( 'vx_conexion_rechazada', function (): string {
    ob_start();
    ?>
    <div class="vx-flow-page">
        <div class="container-sm py-5">
            <div class="vx-flow-card text-center">
                <div class="vx-flow-card__icon mb-4">
                    <i class="ti ti-check"></i>
                </div>
                <h1 class="vx-flow-card__title">Respuesta registrada</h1>
                <p class="vx-flow-card__desc">Tu respuesta fue registrada correctamente.</p>
                <a href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>" class="btn-vx btn-vx--ghost mt-4">Ir al inicio</a>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
} );
