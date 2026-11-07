<?php
/**
 * Plugin Name: Vitrinexo Core
 * Plugin URI:  https://vitrinexo.com
 * Description: Lógica de negocio de la plataforma Vitrinexo: directorio B2B hispanohablante.
 * Version:     1.0.0
 * Author:      Vitrinexo SpA
 * Author URI:  https://vitrinexo.com
 * Text Domain: vitrinexo-core
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Seguridad: no mostrar trazas de error PHP a los visitantes del sitio público.
// Los errores se siguen registrando (WP_DEBUG_LOG) y siguen visibles en el admin.
if ( ! is_admin() ) {
    @ini_set( 'display_errors', '0' );
    @ini_set( 'display_startup_errors', '0' );
}

define( 'VX_VERSION',        '1.0.5' );
define( 'VX_PLUGIN_DIR',     plugin_dir_path( __FILE__ ) );
define( 'VX_PLUGIN_URL',     plugin_dir_url( __FILE__ ) );
define( 'VX_REST_NAMESPACE', 'vitrinexo/v1' );

// Orden de carga: helpers → meta keys → CPTs → modelos → flujos → email → notif → REST → admin
$vx_modules = [
    // Helpers (sin dependencias)
    'helpers/helper-domains.php',
    'helpers/helper-tokens.php',
    'helpers/helper-tags.php',
    'helpers/helper-profile-fields.php',
    'helpers/helper-pagination.php',
    'helpers/helper-slugs.php',
    'helpers/dial-codes.php',
    'helpers/avatar.php',
    'helpers/helper-pioneros.php',

    // Meta keys (sin dependencias)
    'modules/users/class-vx-user-meta.php',
    'modules/membership/class-vx-membership-meta.php',
    'modules/connections/class-vx-connection-meta.php',
    'modules/dinner/class-vx-dinner-meta.php',

    // CPTs
    'cpts/cpt-empresa.php',
    'cpts/cpt-conexion.php',
    'cpts/cpt-dinner.php',
    'cpts/cpt-notification.php',
    'cpts/cpt-publicacion.php',

    // Modelos
    'modules/users/class-vx-user.php',
    'modules/membership/class-vx-membership.php',
    'modules/membership/class-vx-plans.php',
    'modules/connections/class-vx-connection.php',
    'modules/dinner/class-vx-dinner.php',
    'modules/notifications/class-vx-notification.php',

    // Email (antes de flujos porque los flujos la usan)
    'modules/email/class-vx-email-templates.php',
    'modules/email/class-vx-mailer.php',
    'modules/email/class-vx-foto-reminder.php',

    // Flujos
    'modules/users/class-vx-verification.php',
    'modules/users/class-vx-auth.php',
    'modules/membership/class-vx-membership-hooks.php',
    'modules/onboarding/class-vx-onboarding.php',
    'modules/directory/class-vx-directory.php',
    'modules/directory/class-vx-search.php',
    'modules/directory/class-vx-matches.php',
    'modules/connections/class-vx-connection-flow.php',
    'modules/communities/class-vx-community.php',
    'modules/communities/class-vx-senior-verification.php',
    'modules/dinner/class-vx-dinner-assignment.php',
    'modules/dinner/class-vx-dinner-invite.php',

    // Stats (contadores permanentes e independientes de cuentas)
    'modules/stats/class-vx-stats.php',

    // Cron
    'modules/email/class-vx-cron.php',

    // Notificaciones (depende de flujos)
    'modules/notifications/class-vx-notification-triggers.php',

    // REST API
    'rest/rest-auth.php',
    'rest/rest-account.php',
    'rest/rest-onboarding.php',
    'rest/rest-directory.php',
    'rest/rest-connections.php',
    'rest/rest-favorites.php',
    'rest/rest-notifications.php',
    'rest/rest-dinner.php',
    'rest/rest-communities.php',
    'rest/rest-stripe.php',
    'rest/rest-upload.php',
    'rest/rest-feed.php',
    'rest/rest-blog.php',
    'rest/rest-profile-proposal.php',

    // Admin
    'modules/admin/class-vx-admin-emails.php',
    'modules/admin/class-vx-admin-users.php',
    'modules/admin/class-vx-admin-connections.php',
    'modules/admin/class-vx-admin-dinner.php',
    'modules/admin/class-vx-admin-membership.php',

    // Shortcodes
    'shortcodes/shortcodes-public.php',
    'shortcodes/shortcodes-auth.php',
    'shortcodes/shortcodes-blog.php',
    'shortcodes/shortcodes-feed.php',
    'shortcodes/shortcodes-flow.php',
    'shortcodes/shortcodes-fragments.php',
    'shortcodes/shortcodes-faq.php',
];

foreach ( $vx_modules as $file ) {
    $path = VX_PLUGIN_DIR . $file;
    if ( file_exists( $path ) ) {
        require_once $path;
    }
}

// Inicializar SMTP de Resend — debe registrarse antes del hook init
if ( class_exists( 'VX_Mailer' ) ) {
    VX_Mailer::init();

    if ( class_exists( 'VX_Foto_Reminder' ) ) {
        VX_Foto_Reminder::init();
    }
}

add_action( 'init', function () {
    if ( class_exists( 'VX_User_Meta' ) ) {
        VX_User_Meta::register();
    }
    if ( class_exists( 'VX_Dinner_Invite' ) ) {
        VX_Dinner_Invite::register_cpt();
    }
    if ( class_exists( 'VX_Auth' ) ) {
        VX_Auth::init();
    }
    // Emails admin — debe registrar sus acciones antes de que corra admin_menu
    if ( is_admin() && class_exists( 'VX_Admin_Emails' ) ) {
        VX_Admin_Emails::init();
    }
    if ( class_exists( 'VX_Notification_Triggers' ) ) {
        VX_Notification_Triggers::init();
    }
    if ( class_exists( 'VX_Stats' ) ) {
        VX_Stats::init();
    }

    // Registrar los callbacks de cron en cada carga de página (WP Cron los necesita
    // disponibles cuando dispara el hook). También programa cualquier hook faltante,
    // lo que cubre el caso de plugins ya activos cuando se agrega un nuevo hook.
    if ( class_exists( 'VX_Cron' ) ) {
        VX_Cron::register_hooks();
        VX_Cron::schedule_missing();
    }

    // ── Rewrite rule para perfiles dinámicos: /perfil/{slug}/ ─────────────────
    add_rewrite_rule(
        '^perfil/([a-z0-9][a-z0-9\-]*)/?$',
        'index.php?pagename=perfil&vx_perfil_slug=$matches[1]',
        'top'
    );

    // Flush automático una sola vez cuando la regla no está registrada aún
    $rules = get_option( 'rewrite_rules', [] );
    if ( ! isset( $rules['^perfil/([a-z0-9][a-z0-9\-]*)/?$'] ) ) {
        flush_rewrite_rules( false );
    }
} );

// Registrar vx_perfil_slug como query var reconocida por WordPress
add_filter( 'query_vars', function ( array $vars ): array {
    $vars[] = 'vx_perfil_slug';
    return $vars;
} );

// ── Migración única: política de aprobación manual para todos ─────────────────
// Convierte a 'manual' cualquier cuenta pendiente antigua que haya quedado con
// tipo 'automatica' (institucional auto-verificable), para que también requiera
// aprobación de un administrador. Se ejecuta una sola vez (protegida por opción)
// y es idempotente. Puede eliminarse en el futuro.
add_action( 'init', function () {
    if ( get_option( 'vx_migr_all_manual_v1' ) !== false ) {
        return;
    }
    if ( ! class_exists( 'VX_User_Meta' ) ) {
        return;
    }

    $ids = get_users( [
        'fields'     => 'ids',
        'number'     => -1,
        'meta_query' => [
            'relation' => 'AND',
            [ 'key' => VX_User_Meta::ESTADO,            'value' => 'pendiente'  ],
            [ 'key' => VX_User_Meta::TIPO_VERIFICACION, 'value' => 'automatica' ],
        ],
    ] );

    foreach ( $ids as $uid ) {
        update_user_meta( $uid, VX_User_Meta::TIPO_VERIFICACION, 'manual' );
    }

    // Guarda cuántas cuentas se migraron (0 si no había ninguna).
    update_option( 'vx_migr_all_manual_v1', count( $ids ) );
}, 20 );

// ── Migración: sanear plantilla 'aprobacion' guardada en el admin ─────────────
// Si un admin guardó el cuerpo con el texto viejo ("Activa tu cuenta") lo
// reemplaza por el nuevo ("ya está activa. Ingresa..."). Idempotente y una vez.
add_action( 'init', function () {
    if ( get_option( 'vx_migr_aprobacion_login_v1' ) !== false ) {
        return;
    }

    $body = get_option( 'vx_email_tpl_body_aprobacion', '' );
    if ( is_string( $body ) && $body !== '' && strpos( $body, 'Activa tu cuenta' ) !== false ) {
        $nuevo = "Hola {{nombre}},\n\nTu solicitud para unirte a Vitrinexo fue aprobada y tu cuenta ya está activa. Ingresa con el botón de abajo:\n\n{{link}}";
        update_option( 'vx_email_tpl_body_aprobacion', $nuevo );
    }

    update_option( 'vx_migr_aprobacion_login_v1', 1 );
}, 20 );

// ── Manual de activación Stripe (página oculta del admin, imprimible como PDF) ─

// ── Planes disponibles ──────────────────────────────────────────────────────

function vx_get_planes(): array {
    $saved = get_option( 'vx_planes_disponibles', '' );
    if ( $saved ) {
        $planes = array_filter( array_map( 'trim', explode( "\n", $saved ) ) );
        if ( ! empty( $planes ) ) return array_values( $planes );
    }
    return [ 'Gratuito', 'Miembro Pionero', 'Mensual', 'Anual', 'Preferencial' ];
}

add_action( 'admin_menu', function () {
    add_submenu_page(
        'vitrinexo-core',
        'Planes',
        'Planes',
        'manage_options',
        'vx-planes',
        function () {
            if ( isset( $_POST['vx_planes_save'] ) && check_admin_referer( 'vx_planes' ) ) {
                update_option( 'vx_planes_disponibles', sanitize_textarea_field( wp_unslash( $_POST['vx_planes'] ?? '' ) ) );
                echo '<div class="notice notice-success is-dismissible"><p>Planes guardados.</p></div>';
            }
            $planes_raw = get_option( 'vx_planes_disponibles', implode( "\n", [ 'Gratuito', 'Miembro Pionero', 'Mensual', 'Anual', 'Preferencial' ] ) );
            ?>
            <div class="wrap">
                <h1>Planes de membresía</h1>
                <p style="color:#5C5F6B">Un plan por línea. El primero será el plan por defecto.</p>
                <form method="post">
                    <?php wp_nonce_field( 'vx_planes' ); ?>
                    <input type="hidden" name="vx_planes_save" value="1">
                    <textarea name="vx_planes" rows="10" class="large-text" style="font-family:monospace;max-width:400px"><?php echo esc_textarea( $planes_raw ); ?></textarea>
                    <p class="submit"><button type="submit" class="button button-primary">Guardar planes</button></p>
                </form>
                <hr>
                <h3>Planes actuales</h3>
                <ul><?php foreach ( vx_get_planes() as $p ) echo '<li>' . esc_html( $p ) . '</li>'; ?></ul>
            </div>
            <?php
        }
    );
}, 25 );

// ── Limpiar la interfaz admin de WordPress ──────────────────────────────────

// 1. Menú lateral — ocultar todo lo que no es de Vitrinexo
add_action( 'admin_menu', function () {
    remove_menu_page( 'edit.php' );                    // Entradas
    remove_menu_page( 'upload.php' );                  // Medios
    remove_menu_page( 'edit.php?post_type=page' );     // Páginas
    remove_menu_page( 'edit-comments.php' );           // Comentarios
    remove_menu_page( 'tools.php' );                   // Herramientas
    remove_menu_page( 'hostinger' );                   // Hostinger branding
    remove_menu_page( 'hostinger-reach' );             // Hostinger Reach
    remove_menu_page( 'hostinger-easy-onboarding' );   // Hostinger Onboarding
    remove_submenu_page( 'themes.php', 'site-editor.php?path=/pattern' ); // Patrones
}, 999 );

// 2. Dashboard — eliminar widgets innecesarios
add_action( 'wp_dashboard_setup', function () {
    remove_meta_box( 'dashboard_quick_press',       'dashboard', 'side' );   // Borrador rápido
    remove_meta_box( 'dashboard_primary',           'dashboard', 'side' );   // Noticias WordPress
    remove_meta_box( 'dashboard_activity',          'dashboard', 'normal' ); // Actividad
    remove_meta_box( 'dashboard_right_now',         'dashboard', 'normal' ); // De un vistazo
    remove_meta_box( 'fluentsmtp_reports_widget',   'dashboard', 'normal' ); // FluentSMTP stats
    remove_meta_box( 'fluentsmtp_report_widget',    'dashboard', 'normal' ); // FluentSMTP stats (alt ID)
    remove_meta_box( 'dashboard_site_health',       'dashboard', 'normal' ); // Site health
    remove_meta_box( 'hostinger_dashboard_widget',  'dashboard', 'normal' ); // Hostinger widget
}, 999 );

// Quitar el panel "¡Bienvenido a WordPress!"
remove_action( 'welcome_panel', 'wp_welcome_panel' );

// 4. Suprimir avisos (notices) de Hostinger y LiteSpeed — no relevantes para Vitrinexo
add_action( 'admin_head', function () {
    echo '<style>
        .notice.hostinger-notice,
        .notice[class*="hostinger"],
        .hostinger-reach-notice,
        #hostinger-reach-dashboard-widget,
        .litespeed_icon.notice,
        .litespeed-notice,
        div[class*="litespeed"][class*="notice"] {
            display: none !important;
        }
    </style>';
} );

// 3. Toolbar (barra superior) — quitar elementos innecesarios
add_action( 'admin_bar_menu', function ( $bar ) {
    $bar->remove_node( 'wp-logo' );
    $bar->remove_node( 'new-content' );
    $bar->remove_node( 'comments' );
    $bar->remove_node( 'updates' );

    // Todos los nodos de Hostinger en la toolbar
    foreach ( [
        'hostinger_admin_bar',
        'hostinger-easy-onboarding-admin-bar-onboarding',
        'hostinger-tools-admin-bar',
        'hostinger-ai-assistant-ai-content-creator',
        'hostinger-reach',
        'hostinger_hpanel_home_admin_bar',
        'hostinger_website_list_admin_bar',
        'hostinger_billings_admin_bar',
    ] as $node ) {
        $bar->remove_node( $node );
    }

    // LiteSpeed en la toolbar
    $bar->remove_node( 'litespeed-menu' );
    $bar->remove_node( 'litespeed-bar-manage' );
}, 99999 );

add_action( 'admin_menu', function () {
    add_submenu_page(
        null,                        // sin padre · no aparece en el menú lateral
        'Manual de activación Stripe | Vitrinexo',
        'Manual Stripe',
        'manage_options',
        'vx-stripe-manual',
        'vx_admin_stripe_manual_page'
    );
} );

function vx_admin_stripe_manual_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $webhook_url  = esc_url( get_rest_url( null, 'vitrinexo/v1/stripe/webhook' ) );
    $site_url     = esc_url( get_site_url() );
    $admin_url    = esc_url( admin_url( 'admin.php?page=vitrinexo-core' ) );
    $precio_m     = esc_html( get_option( 'vx_precio_mensual', 29 ) );
    $precio_a     = esc_html( get_option( 'vx_precio_anual', 249 ) );
    $precio_f     = esc_html( get_option( 'vx_precio_preferencial', 19 ) );
    $moneda       = esc_html( get_option( 'vx_moneda', 'USD' ) );

    ?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Manual de activación Stripe | Vitrinexo</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', system-ui, sans-serif; font-size: 14px; line-height: 1.7; color: #14161F; background: #F4F1EC; }
  .doc { max-width: 860px; margin: 0 auto; background: #fff; padding: 48px 56px; }
  .doc-header { border-bottom: 3px solid #FF7A4D; padding-bottom: 24px; margin-bottom: 32px; }
  .doc-header h1 { font-size: 26px; font-weight: 700; color: #14161F; }
  .doc-header p { color: #5C5F6B; margin-top: 6px; }
  .doc-header .badge { display:inline-block;background:#FFE3D8;color:#AD4319;font-size:11px;font-weight:700;padding:3px 10px;border-radius:4px;letter-spacing:.05em;text-transform:uppercase;margin-top:8px; }
  h2 { font-size: 17px; font-weight: 700; margin: 36px 0 12px; padding-bottom: 6px; border-bottom: 1px solid #E6E2DB; color: #14161F; display:flex;align-items:center;gap:8px; }
  h2 .step-num { display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:50%;background:#FF7A4D;color:#fff;font-size:13px;font-weight:700;flex-shrink:0; }
  h3 { font-size: 14px; font-weight: 700; margin: 20px 0 8px; color: #5C5F6B; }
  p { margin-bottom: 12px; color: #14161F; }
  ul, ol { padding-left: 20px; margin-bottom: 14px; }
  li { margin-bottom: 6px; }
  code { background: #E6E2DB; border: 1px solid #E6E2DB; border-radius: 4px; padding: 1px 6px; font-family: 'Consolas', monospace; font-size: 12px; color: #14161F; word-break: break-all; }
  .code-block { background: #14161F; color: #E6E2DB; border-radius: 8px; padding: 16px 20px; margin: 12px 0; font-family: 'Consolas', monospace; font-size: 12px; line-height: 1.6; overflow-x: auto; }
  .code-block .comment { color: #5C5F6B; }
  .info-box { background: #CBE5D8; border: 1px solid #CBE5D8; border-left: 4px solid #7DBF9E; border-radius: 4px; padding: 12px 16px; margin: 14px 0; }
  .warn-box { background: #FAF7C9; border: 1px solid #F2EC79; border-left: 4px solid #C6C167; border-radius: 4px; padding: 12px 16px; margin: 14px 0; }
  .danger-box { background: #E8D1C9; border: 1px solid #E8D1C9; border-left: 4px solid #C58D78; border-radius: 4px; padding: 12px 16px; margin: 14px 0; }
  .field-table { width:100%; border-collapse:collapse; margin:14px 0; font-size:13px; }
  .field-table th { background:#F4F1EC; padding:8px 12px; text-align:left; font-weight:700; border:1px solid #E6E2DB; }
  .field-table td { padding:8px 12px; border:1px solid #E6E2DB; vertical-align:top; }
  .field-table td:first-child { font-weight:600; white-space:nowrap; }
  .field-table .url-cell { font-family:monospace; font-size:12px; word-break:break-all; background:#F4F1EC; }
  .checklist { list-style:none; padding-left:0; }
  .checklist li { padding: 5px 0; padding-left: 28px; position: relative; }
  .checklist li::before { content: ''; position: absolute; left: 4px; top: 10px; width: 11px; height: 11px; border: 1.5px solid #5C5F6B; border-radius: 2px; }
  .print-btn { position:fixed; top:20px; right:20px; background:#FF7A4D; color:#fff; border:none; padding:10px 20px; border-radius:6px; font-size:13px; font-weight:600; cursor:pointer; box-shadow:0 2px 8px color-mix(in srgb, var(--tinta, #14161F) 15%, transparent); z-index:999; }
  .print-btn:hover { background:#F26A3B; }
  .toc { background:#F4F1EC; border:1px solid #E6E2DB; border-radius:6px; padding:16px 20px; margin:20px 0 32px; }
  .toc h3 { margin:0 0 10px; font-size:13px; color:#5C5F6B; text-transform:uppercase; letter-spacing:.06em; }
  .toc ol { margin:0; padding-left:18px; }
  .toc li { margin-bottom:4px; }
  .toc a { color:#AD4319; text-decoration:none; }
  .toc a:hover { text-decoration:underline; }
  .footer-doc { margin-top:48px; padding-top:20px; border-top:1px solid #E6E2DB; text-align:center; color:#5C5F6B; font-size:12px; }
  @media print {
    body { background:#fff; }
    .print-btn { display:none; }
    .doc { padding: 24px; max-width:100%; }
    h2 { break-before: avoid; }
    .code-block { break-inside: avoid; }
  }
</style>
</head>
<body>

<button class="print-btn" onclick="window.print()">Imprimir / Guardar PDF</button>

<div class="doc">

  <div class="doc-header">
    <h1>Manual de activación: Integración Stripe</h1>
    <p>Plataforma Vitrinexo · <?php echo esc_html( get_site_url() ); ?></p>
    <span class="badge">Generado <?php echo date_i18n( 'j \d\e F \d\e Y' ); ?></span>
  </div>

  <!-- ÍNDICE -->
  <div class="toc">
    <h3>Contenido</h3>
    <ol>
      <li><a href="#paso1">Crear cuenta y proyecto en Stripe</a></li>
      <li><a href="#paso2">Crear los 3 productos y precios</a></li>
      <li><a href="#paso3">Configurar el webhook</a></li>
      <li><a href="#paso4">Instalar el SDK de Stripe en el servidor</a></li>
      <li><a href="#paso5">Activar el código en Vitrinexo</a></li>
      <li><a href="#paso6">Llenar las claves en el admin</a></li>
      <li><a href="#paso7">Probar con modo Test</a></li>
      <li><a href="#paso8">Pasar a producción (live)</a></li>
      <li><a href="#checklist">Checklist final</a></li>
    </ol>
  </div>

  <div class="info-box">
    <strong>Estado actual:</strong> La integración está <strong>preparada pero no activa</strong>. El código de los endpoints existe y está documentado en <code>rest/rest-stripe.php</code>. Solo hay que instalar el SDK, descomentar el código y configurar las claves.
  </div>

  <!-- PASO 1 -->
  <h2 id="paso1"><span class="step-num">1</span> Crear cuenta y proyecto en Stripe</h2>
  <ol>
    <li>Ir a <code>https://dashboard.stripe.com</code> y crear cuenta (o iniciar sesión).</li>
    <li>Verificar la cuenta con datos bancarios del negocio para poder recibir pagos reales.</li>
    <li>En el dashboard, activar el modo <strong>Test</strong> para las primeras pruebas (toggle en la barra superior derecha).</li>
  </ol>
  <div class="warn-box">
    <strong>Importante:</strong> Stripe tiene dos conjuntos de claves: <em>Test</em> (pk_test_..., sk_test_...) y <em>Live</em> (pk_live_..., sk_live_...). Usa las de Test para probar, las de Live solo cuando estés listo para cobrar real.
  </div>

  <!-- PASO 2 -->
  <h2 id="paso2"><span class="step-num">2</span> Crear los 3 productos y precios en Stripe</h2>
  <p>Ir a <strong>Catalog · Products · Add product</strong> y crear los siguientes tres:</p>

  <table class="field-table">
    <thead><tr><th>Producto</th><th>Precio</th><th>Tipo</th><th>Moneda</th></tr></thead>
    <tbody>
      <tr><td>Vitrinexo Mensual</td><td><?php echo $precio_m; ?> <?php echo $moneda; ?></td><td>Recurrente / mes</td><td><?php echo $moneda; ?></td></tr>
      <tr><td>Vitrinexo Anual</td><td><?php echo $precio_a; ?> <?php echo $moneda; ?></td><td>Recurrente / año</td><td><?php echo $moneda; ?></td></tr>
      <tr><td>Vitrinexo Fundador</td><td><?php echo $precio_f; ?> <?php echo $moneda; ?></td><td>Recurrente / mes</td><td><?php echo $moneda; ?></td></tr>
    </tbody>
  </table>

  <p>Tras crear cada producto, <strong>copiar el Price ID</strong> (formato <code>price_1ABC...</code>), se necesita en el paso 6.</p>
  <div class="info-box">
    En modo Test, los Price IDs empiezan con <code>price_</code> pero usan el mismo formato que en Live. Puedes crear los productos una vez y usar el mismo ID para test y live si los montos son iguales.
  </div>

  <!-- PASO 3 -->
  <h2 id="paso3"><span class="step-num">3</span> Configurar el webhook</h2>
  <p>Stripe necesita una URL para notificar a Vitrinexo cuando ocurre un pago.</p>
  <ol>
    <li>Ir a <strong>Developers · Webhooks · Add endpoint</strong>.</li>
    <li>Pegar esta URL como Endpoint URL:</li>
  </ol>
  <div class="code-block"><?php echo $webhook_url; ?></div>
  <ol start="3">
    <li>En "Select events", seleccionar estos 4 eventos:</li>
  </ol>
  <ul>
    <li><code>checkout.session.completed</code>: activa el plan al pagar</li>
    <li><code>invoice.payment_succeeded</code>: renueva el vencimiento mensual/anual</li>
    <li><code>invoice.payment_failed</code>: notifica al usuario de pago fallido</li>
    <li><code>customer.subscription.deleted</code>: cancela el plan</li>
  </ul>
  <ol start="4">
    <li>Hacer click en <strong>Add endpoint</strong>.</li>
    <li>En la página del webhook creado, hacer click en <strong>"Reveal" junto a Signing secret</strong> y copiar el valor <code>whsec_...</code>.</li>
  </ol>

  <!-- PASO 4 -->
  <h2 id="paso4"><span class="step-num">4</span> Instalar el SDK de Stripe en el servidor</h2>
  <p>Conectarse al servidor vía SSH y ejecutar desde la raíz del plugin:</p>
  <div class="code-block"><span class="comment"># Navegar al directorio del plugin</span>
cd <?php echo esc_html( WP_PLUGIN_DIR . '/vitrinexo-core' ); ?>

<span class="comment"># Instalar el SDK de Stripe (requiere Composer instalado)</span>
composer require stripe/stripe-php</div>

  <p>Si no hay Composer en el servidor:</p>
  <div class="code-block"><span class="comment"># Instalar Composer primero</span>
curl -sS https://getcomposer.org/installer | php
php composer.phar require stripe/stripe-php</div>

  <div class="info-box">
    Esto crea la carpeta <code>vendor/</code> dentro del plugin con el SDK de Stripe. El archivo <code>vendor/autoload.php</code> se referencia en el código del paso siguiente.
  </div>
  <div class="warn-box">
    <strong>Hostinger:</strong> En Hostinger puedes usar el terminal SSH desde hPanel · Advanced · SSH Access. Si no hay Composer, contacta al soporte de Hostinger para que lo habiliten, o sube manualmente la carpeta <code>vendor/</code> generada localmente.
  </div>

  <!-- PASO 5 -->
  <h2 id="paso5"><span class="step-num">5</span> Activar el código en Vitrinexo</h2>
  <p>Abrir el archivo <code><?php echo esc_html( WP_PLUGIN_DIR . '/vitrinexo-core/rest/rest-stripe.php' ); ?></code> y buscar los 3 bloques marcados con:</p>
  <div class="code-block"><span class="comment">// ── STRIPE TODO ────────────────────────────────────────────</span></div>
  <p>En cada bloque hay que:</p>
  <ol>
    <li><strong>Eliminar</strong> los <code>//</code> al inicio de cada línea dentro del bloque (descomentar).</li>
    <li><strong>Eliminar</strong> el bloque STUB que empieza con <code>// STUB:</code> justo después.</li>
  </ol>
  <p>Los 3 bloques a activar son:</p>
  <ul>
    <li><code>vx_rest_stripe_checkout()</code>: crea sesiones de pago</li>
    <li><code>vx_rest_stripe_webhook()</code>: procesa eventos de Stripe</li>
    <li><code>vx_rest_stripe_portal()</code>: acceso al portal de facturación</li>
  </ul>
  <div class="info-box">
    Todo el código ya está escrito y documentado. Solo hay que descomentar, no hay que escribir nada nuevo.
  </div>

  <!-- PASO 6 -->
  <h2 id="paso6"><span class="step-num">6</span> Llenar las claves en el admin de WordPress</h2>
  <p>Ir a <strong><?php echo $admin_url; ?></strong> · sección "Precios y Stripe" y completar:</p>

  <table class="field-table">
    <thead><tr><th>Campo</th><th>Valor</th><th>Dónde encontrarlo en Stripe</th></tr></thead>
    <tbody>
      <tr>
        <td>Stripe Publishable Key</td>
        <td><code>pk_test_...</code> o <code>pk_live_...</code></td>
        <td>Developers · API Keys · Publishable key</td>
      </tr>
      <tr>
        <td>Stripe Secret Key</td>
        <td><code>sk_test_...</code> o <code>sk_live_...</code></td>
        <td>Developers · API Keys · Secret key (click "Reveal")</td>
      </tr>
      <tr>
        <td>Stripe Webhook Secret</td>
        <td><code>whsec_...</code></td>
        <td>Developers · Webhooks · tu endpoint · Signing secret</td>
      </tr>
      <tr>
        <td>Price ID Mensual</td>
        <td><code>price_...</code></td>
        <td>Catalog · Products · Vitrinexo Mensual · Price ID</td>
      </tr>
      <tr>
        <td>Price ID Anual</td>
        <td><code>price_...</code></td>
        <td>Catalog · Products · Vitrinexo Anual · Price ID</td>
      </tr>
      <tr>
        <td>Price ID Fundador</td>
        <td><code>price_...</code></td>
        <td>Catalog · Products · Vitrinexo Fundador · Price ID</td>
      </tr>
    </tbody>
  </table>

  <!-- PASO 7 -->
  <h2 id="paso7"><span class="step-num">7</span> Probar con modo Test</h2>
  <p>Stripe provee tarjetas de prueba que puedes usar en el checkout sin cobrar dinero real:</p>

  <table class="field-table">
    <thead><tr><th>Tarjeta</th><th>Número</th><th>Resultado</th></tr></thead>
    <tbody>
      <tr><td>Visa exitosa</td><td><code>4242 4242 4242 4242</code></td><td>Pago aprobado</td></tr>
      <tr><td>Pago declinado</td><td><code>4000 0000 0000 0002</code></td><td>Error de pago</td></tr>
      <tr><td>Requiere autenticación</td><td><code>4000 0025 0000 3155</code></td><td>Solicita 3D Secure</td></tr>
    </tbody>
  </table>
  <p>Para todas: fecha de expiración cualquiera futura, CVV cualquier 3 dígitos, código postal cualquiera.</p>

  <p>Flujo de prueba completo:</p>
  <ol>
    <li>Iniciar sesión como usuario de prueba en Vitrinexo.</li>
    <li>Ir a <code>/configuracion/?tab=plan</code> y hacer click en "Suscribirse".</li>
    <li>Completar el checkout con la tarjeta <code>4242 4242 4242 4242</code>.</li>
    <li>Verificar que el plan se actualizó en la columna "Plan" de Usuarios en el admin.</li>
    <li>Verificar que el usuario recibió el email de confirmación de plan activo.</li>
    <li>En Stripe Dashboard · Events verificar que se recibió el evento <code>checkout.session.completed</code>.</li>
  </ol>

  <!-- PASO 8 -->
  <h2 id="paso8"><span class="step-num">8</span> Pasar a producción (live)</h2>
  <div class="danger-box">
    <strong>Solo hacer esto cuando las pruebas con modo Test estén 100% verificadas.</strong>
  </div>
  <ol>
    <li>En Stripe Dashboard, cambiar el toggle de <strong>Test · Live</strong>.</li>
    <li>Repetir los pasos 2 y 3 en modo Live (crear productos, configurar webhook).</li>
    <li>Actualizar las claves en el admin de Vitrinexo con las versiones <code>pk_live_</code> / <code>sk_live_</code>.</li>
    <li>Actualizar el Webhook Secret con el del webhook Live.</li>
    <li>Actualizar los Price IDs con los del modo Live.</li>
    <li>Hacer un pago real de prueba de <code>$1</code> y reembolsarlo inmediatamente para verificar.</li>
  </ol>

  <!-- CHECKLIST -->
  <h2 id="checklist">Checklist final</h2>
  <ul class="checklist">
    <li>Cuenta de Stripe creada y verificada con datos bancarios</li>
    <li>3 productos creados en Stripe (Mensual, Anual, Fundador) con sus Price IDs</li>
    <li>Webhook configurado en Stripe con la URL correcta y los 4 eventos</li>
    <li>Webhook Secret copiado</li>
    <li>SDK de Stripe instalado en el servidor (<code>vendor/autoload.php</code> existe)</li>
    <li>Código en <code>rest-stripe.php</code> descomentado (3 bloques STRIPE TODO)</li>
    <li>Publishable Key configurada en el admin</li>
    <li>Secret Key configurada en el admin</li>
    <li>Webhook Secret configurado en el admin</li>
    <li>3 Price IDs configurados en el admin</li>
    <li>Prueba exitosa con tarjeta de test <code>4242 4242 4242 4242</code></li>
    <li>Email de confirmación de plan recibido correctamente</li>
    <li>Claves cambiadas a Live (cuando esté listo para cobrar real)</li>
  </ul>

  <div class="footer-doc">
    <p>Vitrinexo · <?php echo esc_html( get_site_url() ); ?></p>
    <p style="margin-top:4px">Manual generado automáticamente, siempre refleja la configuración actual del sitio</p>
  </div>

</div>
</body>
</html><?php
}

add_action( 'admin_menu', function () {
    add_menu_page(
        'Vitrinexo',
        'Vitrinexo',
        'manage_options',
        'vitrinexo-core',
        'vx_admin_ajustes_page',
        'dashicons-networking',
        30
    );

    add_submenu_page(
        'vitrinexo-core',
        'Ajustes Vitrinexo',
        'Ajustes',
        'manage_options',
        'vitrinexo-core',
        'vx_admin_ajustes_page'
    );

    // Contar validaciones pendientes para mostrar badge en el menú
    $pendientes_count = count( get_users( [
        'role'       => 'subscriber',
        'fields'     => 'ids',
        'number'     => -1,
        'meta_query' => [
            'relation' => 'AND',
            [ 'key' => VX_User_Meta::ESTADO,              'value' => 'pendiente' ],
            [ 'key' => VX_User_Meta::TIPO_VERIFICACION,   'value' => 'manual' ],
        ],
    ] ) );

    $senior_count = count( get_users( [
        'role'       => 'subscriber',
        'fields'     => 'ids',
        'number'     => -1,
        'meta_query' => [
            'relation' => 'AND',
            [ 'key' => VX_User_Meta::SENIOR_SOLICITADO, 'value' => '1' ],
            [ 'key' => VX_User_Meta::SENIOR_VERIFICADO, 'compare' => 'NOT EXISTS' ],
        ],
    ] ) );

    $total_badge = $pendientes_count + $senior_count;
    $badge_html  = $total_badge > 0
        ? ' <span class="awaiting-mod count-' . $total_badge . '"><span class="pending-count">' . $total_badge . '</span></span>'
        : '';

    add_submenu_page(
        'vitrinexo-core',
        'Validaciones | Vitrinexo',
        'Validaciones' . $badge_html,
        'manage_options',
        'vx-validaciones',
        'vx_admin_validaciones_page'
    );

    add_submenu_page(
        'vitrinexo-core',
        'Tags sugeridos | Vitrinexo',
        'Tags sugeridos',
        'manage_options',
        'vx-tags',
        'vx_admin_tags_page'
    );
} );

// ── Tags sugeridos — guardar ──────────────────────────────────────────────────

add_action( 'admin_post_vx_save_tags', function () {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permiso.' );
    check_admin_referer( 'vx_save_tags' );

    $raw = json_decode( wp_unslash( $_POST['vx_tags_json'] ?? '[]' ), true );
    if ( ! is_array( $raw ) ) $raw = [];

    $tags = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $raw ) ) ) );
    update_option( 'vx_tags_preset', $tags );

    wp_safe_redirect( admin_url( 'admin.php?page=vx-tags&vx_ok=1' ) );
    exit;
} );

function vx_admin_tags_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permiso.' );

    $default = [
        'Marketing digital','Diseño y branding','Desarrollo web','Consultoría',
        'Ventas B2B','CRM','Automatización','Legal','Finanzas','RRHH',
        'Data e inteligencia','Logística','Producción audiovisual','Real estate',
        'Alianzas tecnológicas','Alianzas comerciales','E-commerce','SaaS',
        'Transformación digital','Startups','Venture capital','Exportaciones',
    ];
    $saved = get_option( 'vx_tags_preset', null );
    $tags  = is_array( $saved ) && ! empty( $saved ) ? $saved : $default;
    ?>
    <div class="wrap">
    <h1>Tags sugeridos</h1>
    <p style="color:#5C5F6B">Estos tags aparecen como sugerencias cuando un miembro edita su perfil (sección Ofrece / Busca). Los miembros también pueden escribir tags propios.</p>

    <?php if ( ! empty( $_GET['vx_ok'] ) ) : ?>
    <div class="notice notice-success is-dismissible"><p>Tags guardados correctamente.</p></div>
    <?php endif; ?>

    <div style="max-width:720px;margin-top:20px">
      <div id="vx-tags-wrap" style="display:flex;flex-wrap:wrap;gap:8px;padding:16px;border:1px solid #E6E2DB;border-radius:4px;background:#fff;min-height:60px;margin-bottom:16px">
        <?php foreach ( $tags as $tag ) : ?>
        <span class="vx-tag-chip" style="display:inline-flex;align-items:center;gap:6px;background:#F4F1EC;border:1px solid #DDE8F6;border-radius:99px;padding:5px 12px;font-size:13px">
          <?php echo esc_html( $tag ); ?>
          <button type="button" onclick="vxRemoveTag(this)" style="border:none;background:none;cursor:pointer;color:#5C5F6B;font-size:14px;line-height:1;padding:0" title="Eliminar">×</button>
        </span>
        <?php endforeach; ?>
      </div>

      <div style="display:flex;gap:8px;margin-bottom:20px">
        <input type="text" id="vx-new-tag" placeholder="Nuevo tag…" class="regular-text" style="flex:1"
               onkeydown="if(event.key==='Enter'){event.preventDefault();vxAddTag();}">
        <button type="button" class="button" onclick="vxAddTag()">+ Agregar</button>
      </div>

      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'vx_save_tags' ); ?>
        <input type="hidden" name="action" value="vx_save_tags">
        <input type="hidden" name="vx_tags_json" id="vx-tags-json" value="<?php echo esc_attr( wp_json_encode( $tags ) ); ?>">
        <button type="button" class="button button-primary" onclick="vxGuardarTags()">Guardar tags</button>
        <span style="margin-left:12px;font-size:13px;color:#5C5F6B" id="vx-tag-count"><?php echo count( $tags ); ?> tags</span>
      </form>
    </div>
    </div>

    <script>
    (function () {
      var tags = <?php echo wp_json_encode( $tags ); ?>;

      function render() {
        var wrap = document.getElementById('vx-tags-wrap');
        wrap.innerHTML = '';
        tags.forEach(function (t, i) {
          var s = document.createElement('span');
          s.className = 'vx-tag-chip';
          s.style.cssText = 'display:inline-flex;align-items:center;gap:6px;background:#F4F1EC;border:1px solid #DDE8F6;border-radius:99px;padding:5px 12px;font-size:13px';
          s.innerHTML = escHtml(t) + '<button type="button" onclick="vxRemoveTag(this)" data-idx="'+i+'" style="border:none;background:none;cursor:pointer;color:#5C5F6B;font-size:14px;line-height:1;padding:0" title="Eliminar">×</button>';
          wrap.appendChild(s);
        });
        document.getElementById('vx-tag-count').textContent = tags.length + ' tags';
        document.getElementById('vx-tags-json').value = JSON.stringify(tags);
      }

      window.vxAddTag = function () {
        var input = document.getElementById('vx-new-tag');
        var val   = input.value.trim();
        if ( ! val ) return;
        if ( tags.some(function(t){ return t.toLowerCase() === val.toLowerCase(); }) ) {
          input.focus(); input.select(); return;
        }
        tags.push(val);
        input.value = '';
        render();
        input.focus();
      };

      window.vxRemoveTag = function (btn) {
        var idx = parseInt(btn.dataset.idx, 10);
        tags.splice(idx, 1);
        render();
      };

      window.vxGuardarTags = function () {
        document.getElementById('vx-tags-json').value = JSON.stringify(tags);
        btn = document.querySelector('.button-primary');
        btn.disabled = true;
        btn.closest('form').submit();
      };

      function escHtml(s) {
        return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
      }
    })();
    </script>
    <?php
}

// ── Handlers de acción para la página de validaciones ─────────────────────────

add_action( 'admin_action_vx_val_aprobar_cuenta', function () {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permiso.' );
    $user_id = absint( $_GET['user_id'] ?? 0 );
    check_admin_referer( 'vx_val_aprobar_cuenta_' . $user_id );
    VX_Verification::approve_manual( $user_id );
    wp_safe_redirect( admin_url( 'admin.php?page=vx-validaciones&vx_ok=cuenta_aprobada' ) );
    exit;
} );

add_action( 'admin_action_vx_val_rechazar_cuenta', function () {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permiso.' );
    $user_id = absint( $_GET['user_id'] ?? 0 );
    check_admin_referer( 'vx_val_rechazar_cuenta_' . $user_id );
    update_user_meta( $user_id, VX_User_Meta::ESTADO, 'rechazado' );
    delete_user_meta( $user_id, 'vx_token_confirmacion' );
    delete_user_meta( $user_id, 'vx_token_expira' );
    wp_safe_redirect( admin_url( 'admin.php?page=vx-validaciones&vx_ok=cuenta_rechazada' ) );
    exit;
} );

add_action( 'admin_action_vx_val_aprobar_senior', function () {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permiso.' );
    $user_id = absint( $_GET['user_id'] ?? 0 );
    check_admin_referer( 'vx_val_aprobar_senior_' . $user_id );
    VX_Senior_Verification::approve( $user_id );
    wp_safe_redirect( admin_url( 'admin.php?page=vx-validaciones&vx_ok=senior_aprobado' ) );
    exit;
} );

add_action( 'admin_action_vx_val_rechazar_senior', function () {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permiso.' );
    $user_id = absint( $_GET['user_id'] ?? 0 );
    check_admin_referer( 'vx_val_rechazar_senior_' . $user_id );
    VX_Senior_Verification::reject( $user_id );
    wp_safe_redirect( admin_url( 'admin.php?page=vx-validaciones&vx_ok=senior_rechazado' ) );
    exit;
} );

// ── Página de Validaciones ─────────────────────────────────────────────────────

function vx_admin_validaciones_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) return;

    // Mensajes de feedback
    $mensajes = [
        'cuenta_aprobada'  => [ 'success', 'Cuenta aprobada. El usuario recibirá un email para activarla.' ],
        'cuenta_rechazada' => [ 'warning', 'Cuenta rechazada.' ],
        'senior_aprobado'  => [ 'success', 'Distintivo Senior activado. El usuario recibió un email de confirmación.' ],
        'senior_rechazado' => [ 'warning', 'Solicitud Senior rechazada.' ],
    ];
    $ok = sanitize_key( $_GET['vx_ok'] ?? '' );

    // ── Pendientes de verificación (correo genérico) ──────────────────────────
    $pendientes = get_users( [
        'role'       => 'subscriber',
        'number'     => -1,
        'orderby'    => 'registered',
        'order'      => 'ASC',
        'meta_query' => [
            'relation' => 'AND',
            [ 'key' => VX_User_Meta::ESTADO,            'value' => 'pendiente' ],
            [ 'key' => VX_User_Meta::TIPO_VERIFICACION, 'value' => 'manual' ],
        ],
    ] );

    // ── Solicitudes Senior ────────────────────────────────────────────────────
    $senior_reqs = get_users( [
        'role'       => 'subscriber',
        'number'     => -1,
        'orderby'    => 'registered',
        'order'      => 'ASC',
        'meta_query' => [
            'relation' => 'AND',
            [ 'key' => VX_User_Meta::SENIOR_SOLICITADO, 'value' => '1' ],
            [ 'key' => VX_User_Meta::SENIOR_VERIFICADO, 'compare' => 'NOT EXISTS' ],
        ],
    ] );

    ?>
    <div class="wrap">
    <h1 style="display:flex;align-items:center;gap:10px">
        <span>Validaciones</span>
        <?php if ( $pendientes || $senior_reqs ) : ?>
        <span class="awaiting-mod"><span class="pending-count"><?php echo count( $pendientes ) + count( $senior_reqs ); ?></span></span>
        <?php endif; ?>
    </h1>

    <?php if ( $ok && isset( $mensajes[ $ok ] ) ) : ?>
    <div class="notice notice-<?php echo $mensajes[ $ok ][0]; ?> is-dismissible" style="margin:16px 0">
        <p><?php echo esc_html( $mensajes[ $ok ][1] ); ?></p>
    </div>
    <?php endif; ?>

    <style>
    .vx-val-section { background:#fff; border:1px solid #E6E2DB; border-radius:4px; margin-bottom:24px; }
    .vx-val-section-head { padding:14px 18px; border-bottom:1px solid #F4F1EC; display:flex; align-items:center; gap:10px; }
    .vx-val-section-head h2 { margin:0; font-size:14px; font-weight:600; }
    .vx-val-badge { display:inline-flex; align-items:center; justify-content:center; background:#AD4319; color:#fff; border-radius:10px; min-width:20px; height:20px; font-size:11px; font-weight:700; padding:0 6px; line-height:1; }
    .vx-val-badge--ok { background:#7DBF9E; }
    .vx-val-empty { padding:24px 18px; color:#5C5F6B; font-style:italic; font-size:13px; }
    .vx-val-table { width:100%; border-collapse:collapse; font-size:13px; }
    .vx-val-table th { background:#F4F1EC; padding:10px 14px; text-align:left; font-weight:600; color:#14161F; border-bottom:1px solid #E6E2DB; white-space:nowrap; }
    .vx-val-table td { padding:12px 14px; border-bottom:1px solid #F4F1EC; vertical-align:middle; }
    .vx-val-table tr:last-child td { border-bottom:none; }
    .vx-val-table tr:hover td { background:#F4F1EC; }
    .vx-val-name { font-weight:600; color:#14161F; }
    .vx-val-email { color:#5C5F6B; font-size:12px; margin-top:2px; }
    .vx-val-date { color:#5C5F6B; white-space:nowrap; }
    .vx-val-actions { display:flex; gap:6px; flex-wrap:wrap; }
    .vx-val-btn-approve { background:#7DBF9E; color:#fff; border:none; padding:5px 12px; border-radius:3px; font-size:12px; font-weight:600; cursor:pointer; text-decoration:none; display:inline-block; }
    .vx-val-btn-approve:hover { background:#689D85; color:#fff; }
    .vx-val-btn-reject { background:#fff; color:#AD4319; border:1px solid #AD4319; padding:5px 12px; border-radius:3px; font-size:12px; font-weight:600; cursor:pointer; text-decoration:none; display:inline-block; }
    .vx-val-btn-reject:hover { background:#E8D1C9; }
    .vx-val-btn-view { background:#F4F1EC; color:#14161F; border:1px solid #E6E2DB; padding:5px 10px; border-radius:3px; font-size:12px; cursor:pointer; text-decoration:none; display:inline-block; }
    .vx-val-btn-view:hover { background:#E6E2DB; }
    .vx-val-tipo { display:inline-block; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; padding:2px 7px; border-radius:3px; }
    .vx-val-tipo--manual { background:#FAF7C9; color:#C6C167; }
    .vx-val-tipo--senior { background:#EAE7FF; color:#14161F; }
    .vx-val-onboarding { font-size:11px; color:#5C5F6B; }
    </style>

    <?php

    // ── SECCIÓN 1: Cuentas pendientes de aprobación ───────────────────────────
    ?>
    <div class="vx-val-section">
        <div class="vx-val-section-head">
            <h2>Cuentas pendientes de aprobación</h2>
            <?php if ( $pendientes ) : ?>
            <span class="vx-val-badge"><?php echo count( $pendientes ); ?></span>
            <?php else : ?>
            <span class="vx-val-badge vx-val-badge--ok"><span class="dashicons dashicons-yes" aria-hidden="true"></span></span>
            <?php endif; ?>
            <span style="color:#5C5F6B;font-size:12px;margin-left:auto">Usuarios con correo genérico (@gmail, @hotmail…) que requieren aprobación manual</span>
        </div>

        <?php if ( empty( $pendientes ) ) : ?>
        <p class="vx-val-empty">No hay cuentas pendientes de aprobación.</p>
        <?php else : ?>
        <table class="vx-val-table">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Empresa</th>
                    <th>Ubicación</th>
                    <th>Registro</th>
                    <th>Onboarding</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $pendientes as $wp_user ) :
                $vx          = VX_User::get( $wp_user->ID );
                $nombre      = $vx ? $vx->get_nombre_completo() : $wp_user->display_name;
                $onb_paso    = (int) get_user_meta( $wp_user->ID, VX_User_Meta::ONBOARDING_PASO, true );
                $onb_txt     = $onb_paso <= 1 ? 'Sin iniciar' : 'Paso ' . $onb_paso . '/6';
                $reg_date    = date_i18n( 'd/m/Y H:i', strtotime( $wp_user->user_registered ) );
                $edit_url    = get_edit_user_link( $wp_user->ID );

                // Datos para verificar identidad
                $empresa_obj    = $vx ? $vx->get_empresa_activa() : null;
                $empresa_nombre = $empresa_obj ? $empresa_obj->post_title : ( $vx ? (string) get_user_meta( $wp_user->ID, 'vx_empresa_inicial', true ) : '' );
                $industria    = $vx ? $vx->get_industria() : '';
                $cargo        = $vx ? (string) get_user_meta( $wp_user->ID, VX_User_Meta::CARGO, true ) : '';
                $ciudad       = $vx ? $vx->get_ciudad() : '';
                $pais         = $vx ? $vx->get_pais() : '';

                // Link de búsqueda rápida en LinkedIn
                $linkedin_search = 'https://www.linkedin.com/search/results/people/?keywords=' . urlencode( trim( $nombre . ' ' . $empresa_nombre ) );

                $url_aprobar = wp_nonce_url(
                    admin_url( 'admin.php?action=vx_val_aprobar_cuenta&user_id=' . $wp_user->ID ),
                    'vx_val_aprobar_cuenta_' . $wp_user->ID
                );
                $url_rechazar = wp_nonce_url(
                    admin_url( 'admin.php?action=vx_val_rechazar_cuenta&user_id=' . $wp_user->ID ),
                    'vx_val_rechazar_cuenta_' . $wp_user->ID
                );
            ?>
            <tr>
                <td>
                    <div class="vx-val-name"><?php echo esc_html( $nombre ?: '(sin nombre)' ); ?></div>
                    <div class="vx-val-email"><?php echo esc_html( $wp_user->user_email ); ?></div>
                </td>
                <td>
                    <?php if ( $empresa_nombre ) : ?>
                        <div style="font-weight:600;color:#14161F"><?php echo esc_html( $empresa_nombre ); ?></div>
                    <?php else : ?>
                        <span style="color:#5C5F6B;font-style:italic;font-size:12px">Sin completar</span>
                    <?php endif; ?>
                    <?php if ( $cargo ) : ?>
                        <div style="font-size:12px;color:#5C5F6B"><?php echo esc_html( $cargo ); ?></div>
                    <?php endif; ?>
                    <?php if ( $industria ) : ?>
                        <div style="margin-top:4px"><span class="vx-val-tipo vx-val-tipo--manual"><?php echo esc_html( $industria ); ?></span></div>
                    <?php endif; ?>
                </td>
                <td style="font-size:12px;color:#5C5F6B;white-space:nowrap">
                    <?php
                    $ubicacion_parts = array_filter( [ $ciudad, $pais ] );
                    echo $ubicacion_parts ? esc_html( implode( ', ', $ubicacion_parts ) ) : '<span style="color:#5C5F6B;font-style:italic">-</span>';
                    ?>
                </td>
                <td class="vx-val-date"><?php echo esc_html( $reg_date ); ?></td>
                <td class="vx-val-onboarding"><?php echo esc_html( $onb_txt ); ?></td>
                <td>
                    <div class="vx-val-actions">
                        <a href="<?php echo esc_url( $url_aprobar ); ?>"
                           class="vx-val-btn-approve"
                           onclick="return confirm('¿Aprobar la cuenta de <?php echo esc_js( $nombre ?: $wp_user->user_email ); ?>?')">
                            Aprobar
                        </a>
                        <a href="<?php echo esc_url( $url_rechazar ); ?>"
                           class="vx-val-btn-reject"
                           onclick="return confirm('¿Rechazar la cuenta de <?php echo esc_js( $nombre ?: $wp_user->user_email ); ?>? El usuario no podrá activarla.')">
                            Rechazar
                        </a>
                        <a href="<?php echo esc_url( $linkedin_search ); ?>" class="vx-val-btn-view" target="_blank" title="Buscar en LinkedIn">
                            LinkedIn
                        </a>
                        <a href="<?php echo esc_url( $edit_url ); ?>" class="vx-val-btn-view" target="_blank">
                            Admin
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <?php
    // ── SECCIÓN 2: Solicitudes de badge Senior ────────────────────────────────
    ?>
    <div class="vx-val-section">
        <div class="vx-val-section-head">
            <h2>Solicitudes de verificación Senior</h2>
            <?php if ( $senior_reqs ) : ?>
            <span class="vx-val-badge"><?php echo count( $senior_reqs ); ?></span>
            <?php else : ?>
            <span class="vx-val-badge vx-val-badge--ok"><span class="dashicons dashicons-yes" aria-hidden="true"></span></span>
            <?php endif; ?>
            <span style="color:#5C5F6B;font-size:12px;margin-left:auto">Miembros que solicitaron verificación de su trayectoria Senior (ya aparecen en la comunidad; la verificación agrega el distintivo)</span>
        </div>

        <?php if ( empty( $senior_reqs ) ) : ?>
        <p class="vx-val-empty">No hay solicitudes Senior pendientes.</p>
        <?php else : ?>
        <table class="vx-val-table">
            <thead>
                <tr>
                    <th>Miembro</th>
                    <th>Email</th>
                    <th>Industria</th>
                    <th>Plan</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $senior_reqs as $wp_user ) :
                $vx        = VX_User::get( $wp_user->ID );
                $nombre    = $vx ? $vx->get_nombre_completo() : $wp_user->display_name;
                $industria = $vx ? $vx->get_industria() : '';
                $plan      = (string) get_user_meta( $wp_user->ID, VX_User_Meta::PLAN, true );
                $edit_url  = get_edit_user_link( $wp_user->ID );
                $slug      = $vx ? $vx->get_slug() : '';
                $perfil_url = $slug ? home_url( '/perfil/' . $slug . '/' ) : '';

                $url_aprobar = wp_nonce_url(
                    admin_url( 'admin.php?action=vx_val_aprobar_senior&user_id=' . $wp_user->ID ),
                    'vx_val_aprobar_senior_' . $wp_user->ID
                );
                $url_rechazar = wp_nonce_url(
                    admin_url( 'admin.php?action=vx_val_rechazar_senior&user_id=' . $wp_user->ID ),
                    'vx_val_rechazar_senior_' . $wp_user->ID
                );
            ?>
            <tr>
                <td>
                    <div class="vx-val-name"><?php echo esc_html( $nombre ?: '(sin nombre)' ); ?></div>
                    <span class="vx-val-tipo vx-val-tipo--senior" style="margin-top:4px;display:inline-block">Senior</span>
                </td>
                <td>
                    <div><?php echo esc_html( $wp_user->user_email ); ?></div>
                </td>
                <td><?php echo esc_html( $industria ?: '-' ); ?></td>
                <td><?php echo esc_html( ucfirst( $plan ?: 'gratuito' ) ); ?></td>
                <td>
                    <div class="vx-val-actions">
                        <a href="<?php echo esc_url( $url_aprobar ); ?>"
                           class="vx-val-btn-approve"
                           onclick="return confirm('¿Aprobar distintivo Senior para <?php echo esc_js( $nombre ?: $wp_user->user_email ); ?>?')">
                            Aprobar Senior
                        </a>
                        <a href="<?php echo esc_url( $url_rechazar ); ?>"
                           class="vx-val-btn-reject"
                           onclick="return confirm('¿Rechazar la solicitud Senior de <?php echo esc_js( $nombre ?: $wp_user->user_email ); ?>?')">
                            Rechazar
                        </a>
                        <?php if ( $perfil_url ) : ?>
                        <a href="<?php echo esc_url( $perfil_url ); ?>" class="vx-val-btn-view" target="_blank">
                            Ver perfil público
                        </a>
                        <?php endif; ?>
                        <a href="<?php echo esc_url( $edit_url ); ?>" class="vx-val-btn-view" target="_blank">
                            Admin
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <?php if ( empty( $pendientes ) && empty( $senior_reqs ) ) : ?>
    <div style="text-align:center;padding:40px 20px;color:#5C5F6B">
        
        <p style="font-size:16px;font-weight:600;margin:8px 0 4px">Todo al día</p>
        <p style="margin:0">No hay validaciones pendientes en este momento.</p>
    </div>
    <?php endif; ?>

    </div><!-- /.wrap -->
    <?php
}

function vx_admin_ajustes_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $saved = false;

    if ( isset( $_POST['vx_ajustes_nonce'] ) && wp_verify_nonce( $_POST['vx_ajustes_nonce'], 'vx_ajustes_save' ) ) {
        update_option( 'vx_auto_fundador', isset( $_POST['vx_auto_fundador'] ) ? '1' : '0' );
        // Precios
        foreach ( [ 'vx_precio_mensual', 'vx_precio_anual', 'vx_precio_preferencial' ] as $k ) {
            if ( isset( $_POST[ $k ] ) ) update_option( $k, (float) $_POST[ $k ] );
        }
        update_option( 'vx_moneda', sanitize_key( $_POST['vx_moneda'] ?? 'USD' ) );
        // Stripe keys (escritura segura — no mostrar en pantalla)
        foreach ( [ 'vx_stripe_publishable_key', 'vx_stripe_secret_key', 'vx_stripe_webhook_secret',
                    'vx_stripe_price_mensual', 'vx_stripe_price_anual', 'vx_stripe_price_preferencial' ] as $k ) {
            if ( isset( $_POST[ $k ] ) && $_POST[ $k ] !== '' ) {
                update_option( $k, sanitize_text_field( $_POST[ $k ] ) );
            }
        }

        // Vencimiento global del plan gratuito para fundadores (fecha en que dejan de ser gratis)
        $fecha_fin = sanitize_text_field( $_POST['vx_fundador_gratuito_fin'] ?? '' );
        update_option( 'vx_fundador_gratuito_fin', $fecha_fin ? strtotime( $fecha_fin . ' 23:59:59' ) : 0 );

        // Si se marca "Aplicar vencimiento ahora" → actualizar todos los fundadores en plan gratuito
        if ( ! empty( $_POST['vx_aplicar_vencimiento'] ) && $fecha_fin ) {
            $timestamp = strtotime( $fecha_fin . ' 23:59:59' );
            $fundadores = get_users( [
                'role'       => 'subscriber',
                'number'     => -1,
                'meta_query' => [
                    [ 'key' => VX_User_Meta::ES_FUNDADOR, 'value' => '1' ],
                    [ 'key' => VX_User_Meta::PLAN,        'value' => 'gratuito' ],
                ],
            ] );
            foreach ( $fundadores as $u ) {
                update_user_meta( $u->ID, VX_User_Meta::PLAN_VENCIMIENTO, $timestamp );
            }
            $saved = count( $fundadores ) . ' fundadores actualizados.';
        }

        echo '<div class="notice notice-success"><p>Ajustes guardados.' . ( $saved ? ' ' . esc_html( $saved ) : '' ) . '</p></div>';
    }

    $auto_fundador       = get_option( 'vx_auto_fundador', '1' );
    $fin_ts              = (int) get_option( 'vx_fundador_gratuito_fin', 0 );
    $fin_date            = $fin_ts ? date( 'Y-m-d', $fin_ts ) : '';

    // Conteos
    $total_fundadores = count( get_users( [
        'role' => 'subscriber', 'number' => -1,
        'meta_query' => [ [ 'key' => VX_User_Meta::ES_FUNDADOR, 'value' => '1' ] ],
    ] ) );
    $fundadores_gratis = count( get_users( [
        'role' => 'subscriber', 'number' => -1,
        'meta_query' => [
            [ 'key' => VX_User_Meta::ES_FUNDADOR, 'value' => '1' ],
            [ 'key' => VX_User_Meta::PLAN,        'value' => 'gratuito' ],
        ],
    ] ) );
    ?>
    <div class="wrap">
        <h1>Ajustes Vitrinexo</h1>

        <!-- Resumen -->
        <div style="display:flex;gap:16px;margin:16px 0;flex-wrap:wrap">
            <div style="background:#fff;border:1px solid #E6E2DB;border-radius:8px;padding:16px 24px;text-align:center">
                <div style="font-size:28px;font-weight:700;color:#14161F"><?php echo $total_fundadores; ?></div>
                <div style="color:#5C5F6B;font-size:13px">Miembros Pioneros en total</div>
            </div>
            <div style="background:#fff;border:1px solid #E6E2DB;border-radius:8px;padding:16px 24px;text-align:center">
                <div style="font-size:28px;font-weight:700;color:#8BA3C0"><?php echo $fundadores_gratis; ?></div>
                <div style="color:#5C5F6B;font-size:13px">En plan gratuito activo</div>
            </div>
            <div style="background:#fff;border:1px solid #E6E2DB;border-radius:8px;padding:16px 24px;text-align:center">
                <div style="font-size:28px;font-weight:700;color:#A6A0D2"><?php echo $total_fundadores - $fundadores_gratis; ?></div>
                <div style="color:#5C5F6B;font-size:13px">En plan de pago</div>
            </div>
        </div>

        <form method="post">
            <?php wp_nonce_field( 'vx_ajustes_save', 'vx_ajustes_nonce' ); ?>
            <table class="form-table">

                <tr>
                    <th>Distintivo Pionero automático</th>
                    <td>
                        <label>
                            <input type="checkbox" name="vx_auto_fundador" value="1" <?php checked( $auto_fundador, '1' ); ?>>
                            Asignar el distintivo de <strong>Miembro Pionero</strong> a cada miembro que completa el onboarding
                        </label>
                        <p class="description">
                            El distintivo es <strong>permanente</strong>, una vez asignado, no se quita aunque cambien de plan.<br>
                            Desactívalo cuando quieras cerrar la fase fundador (nuevos miembros = usuarios regulares).
                        </p>
                    </td>
                </tr>

                <tr>
                    <th>Fin del plan gratuito para Fundadores</th>
                    <td>
                        <input type="date" name="vx_fundador_gratuito_fin"
                               value="<?php echo esc_attr( $fin_date ); ?>"
                               style="margin-right:12px">
                        <?php if ( $fin_ts ) : ?>
                            <span style="color:#C6C167;font-weight:600">
                                Los Fundadores en plan gratuito vencen el <?php echo date_i18n( 'd/m/Y', $fin_ts ); ?>
                            </span>
                        <?php else : ?>
                            <span style="color:#5C5F6B">Sin fecha definida, acceso gratuito indefinido</span>
                        <?php endif; ?>
                        <p class="description">
                            Cuando llegue esta fecha, los Fundadores en plan gratuito quedarán con <em>plan_estado = vencido</em>
                            y se les pedirá que se suscriban al <strong>precio preferencial</strong> para seguir accediendo.
                        </p>
                        <label style="margin-top:8px;display:block">
                            <input type="checkbox" name="vx_aplicar_vencimiento" value="1">
                            Aplicar esta fecha a todos los Fundadores en plan gratuito ahora mismo
                            <span style="color:#5C5F6B">(actualiza <?php echo $fundadores_gratis; ?> usuarios)</span>
                        </label>
                    </td>
                </tr>

                <tr>
                    <td colspan="2">
                        <hr>
                        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin:8px 0">
                            <h3 style="margin:0">Precios y Stripe</h3>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=vx-stripe-manual' ) ); ?>"
                               target="_blank"
                               class="button"
                               style="display:inline-flex;align-items:center;gap:5px">
                                Ver manual de activación Stripe
                            </a>
                        </div>
                    </td>
                </tr>

                <tr>
                    <th>Moneda</th>
                    <td>
                        <select name="vx_moneda">
                            <?php foreach ( [ 'USD', 'CLP', 'COP', 'MXN', 'PEN', 'UYU', 'EUR' ] as $cur ) : ?>
                            <option value="<?php echo esc_attr($cur); ?>" <?php selected(get_option('vx_moneda','USD'),$cur); ?>><?php echo esc_html($cur); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <?php
                $price_fields = [
                    'vx_precio_mensual'      => [ 'Plan Mensual',       29  ],
                    'vx_precio_anual'        => [ 'Plan Anual',        249  ],
                    'vx_precio_preferencial' => [ 'Plan Fundador (preferencial)', 19 ],
                ];
                foreach ( $price_fields as $opt => [ $label, $default ] ) : ?>
                <tr>
                    <th><?php echo esc_html( $label ); ?></th>
                    <td>
                        <input type="number" name="<?php echo esc_attr($opt); ?>"
                               value="<?php echo esc_attr( get_option($opt, $default) ); ?>"
                               step="0.01" min="0" style="width:100px">
                        <span style="color:#5C5F6B"><?php echo esc_html( get_option('vx_moneda','USD') ); ?>/mes o año</span>
                    </td>
                </tr>
                <?php endforeach; ?>

                <tr><td colspan="2"><p class="description" style="margin:0">Los siguientes campos se completan cuando Stripe esté configurado. No son obligatorios ahora.</p></td></tr>

                <?php
                $stripe_fields = [
                    'vx_stripe_publishable_key'    => 'Stripe Publishable Key (pk_live_...)',
                    'vx_stripe_secret_key'          => 'Stripe Secret Key (sk_live_...)',
                    'vx_stripe_webhook_secret'      => 'Stripe Webhook Secret (whsec_...)',
                    'vx_stripe_price_mensual'       => 'Stripe Price ID: Mensual',
                    'vx_stripe_price_anual'         => 'Stripe Price ID: Anual',
                    'vx_stripe_price_preferencial'  => 'Stripe Price ID: Fundador',
                ];
                foreach ( $stripe_fields as $opt => $label ) :
                    $val = get_option( $opt, '' );
                    $is_secret = str_contains( $opt, 'secret' );
                ?>
                <tr>
                    <th><?php echo esc_html( $label ); ?></th>
                    <td>
                        <input type="<?php echo $is_secret ? 'password' : 'text'; ?>"
                               name="<?php echo esc_attr($opt); ?>"
                               value="<?php echo $val ? esc_attr( $val ) : ''; ?>"
                               placeholder="<?php echo $val ? '(guardada, escribe para cambiar)' : 'Sin configurar'; ?>"
                               class="regular-text"
                               autocomplete="off">
                        <?php if ( $val ) : ?>
                        <span style="color:#14161F;font-size:12px">Configurada</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>

            </table>
            <p><button type="submit" class="button button-primary button-large">Guardar ajustes</button></p>
        </form>

        <hr>
        <h2>Flujo de pagos</h2>
        <ol style="max-width:640px;line-height:1.8">
            <li><strong>Ahora (beta)</strong>: los usuarios completan el onboarding · reciben distintivo Pionero permanente · plan gratuito sin fecha de vencimiento.</li>
            <li><strong>Cuando decidas cobrar</strong>: fija una fecha de fin del plan gratuito y marca "Aplicar a todos". Los Fundadores reciben aviso y pueden suscribirse al precio preferencial desde su configuración.</li>
            <li><strong>Usuarios nuevos post-fundador</strong>: no reciben distintivo (auto-pionero desactivado) · plan gratuito con acceso limitado · para acceso completo deben suscribirse al plan mensual o anual.</li>
        </ol>
        <p>Para modificar el plan o distintivo de un miembro individual · <a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>">Usuarios</a> · columna <em>Plan</em>.</p>
    </div>
    <?php
}

// También en front-end (init) para que las páginas nuevas se creen sin entrar al admin.
add_action( 'init', function () {
    if ( get_option( 'vx_pages_version' ) !== VX_VERSION ) {
        vx_create_pages();
        update_option( 'vx_pages_version', VX_VERSION );
    }
}, 5 );

add_action( 'admin_init', function () {
    // Auto-create pages (runs once per version change or on first install)
    if ( get_option( 'vx_pages_version' ) !== VX_VERSION ) {
        vx_create_pages();
        update_option( 'vx_pages_version', VX_VERSION );
    }

    if ( class_exists( 'VX_Admin_Users' ) ) {
        VX_Admin_Users::init();
        VX_Admin_Users::init_profile_fields();
    }
    if ( class_exists( 'VX_Admin_Connections' ) ) {
        VX_Admin_Connections::init();
    }
    if ( class_exists( 'VX_Admin_Dinner' ) ) {
        VX_Admin_Dinner::init();
    }
    if ( class_exists( 'VX_Admin_Membership' ) ) {
        VX_Admin_Membership::init();
    }
} );

register_activation_hook( __FILE__, function () {
    if ( class_exists( 'VX_Cron' ) ) {
        VX_Cron::schedule();
    }
    vx_create_pages();
    flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function () {
    if ( class_exists( 'VX_Cron' ) ) {
        VX_Cron::unschedule();
    }
    flush_rewrite_rules();
} );

/**
 * Crea las páginas de WordPress con sus shortcodes al activar el plugin.
 */
function vx_create_pages(): void {
    $pages = [
        'home'                     => [ 'title' => 'Inicio',                     'shortcode' => '[vx_landing]' ],
        'login'                    => [ 'title' => 'Ingresar',                   'shortcode' => '[vx_login]' ],
        'recuperar-contrasena'     => [ 'title' => 'Recuperar contraseña',       'shortcode' => '[vx_recuperar_contrasena]' ],
        'nueva-contrasena'         => [ 'title' => 'Nueva contraseña',           'shortcode' => '[vx_nueva_contrasena]' ],
        'confirmar-correo'         => [ 'title' => 'Confirmar correo',           'shortcode' => '[vx_confirmar_correo]' ],
        'verificacion-pendiente'   => [ 'title' => 'Verificación pendiente',     'shortcode' => '[vx_verificacion_pendiente]' ],
        'onboarding'               => [ 'title' => 'Completa tu perfil',         'shortcode' => '[vx_onboarding]' ],
        'dashboard'                => [ 'title' => 'Dashboard',                  'shortcode' => '[vx_dashboard]' ],
        'directorio'               => [ 'title' => 'Directorio',                 'shortcode' => '[vx_directorio]' ],
        'matches'                  => [ 'title' => 'Mis nexos',                'shortcode' => '[vx_matches]' ],
        'match-seeks'              => [ 'title' => 'Ofrecen lo que buscas',      'shortcode' => '[vx_match_seeks]' ],
        'match-offers'             => [ 'title' => 'Buscan lo que ofreces',      'shortcode' => '[vx_match_offers]' ],
        'perfil'                   => [ 'title' => 'Perfil',                     'shortcode' => '[vx_perfil]' ],
        'editar-perfil'            => [ 'title' => 'Editar perfil',              'shortcode' => '[vx_editor_perfil]' ],
        'favoritos'                => [ 'title' => 'Mis favoritos',              'shortcode' => '[vx_favoritos]' ],
        'conexiones'               => [ 'title' => 'Mis conexiones',             'shortcode' => '[vx_conexiones]' ],
        'conexion-aceptada'        => [ 'title' => 'Conexión aceptada',          'shortcode' => '[vx_conexion_aceptada]' ],
        'conexion-rechazada'       => [ 'title' => 'Conexión rechazada',         'shortcode' => '[vx_conexion_rechazada]' ],
        'notificaciones'           => [ 'title' => 'Notificaciones',             'shortcode' => '[vx_notificaciones]' ],
        'configuracion'            => [ 'title' => 'Configuración',              'shortcode' => '[vx_configuracion]' ],
        'ayuda'                    => [ 'title' => 'Ayuda',                      'shortcode' => '[vx_ayuda]' ],
        'blog-nuevo'               => [ 'title' => 'Escribir artículo',          'shortcode' => '[vx_blog_nuevo]' ],
        'blog-moderacion'          => [ 'title' => 'Moderación del blog',        'shortcode' => '[vx_blog_moderacion]' ],
        'landing-4dinner'           => [ 'title' => '4Dinner: Sobre el evento',   'shortcode' => '[vx_landing_4dinner]' ],
        '4dinner'                  => [ 'title' => '4Dinner',                    'shortcode' => '[vx_4dinner]' ],
        'comunidad-out2b'          => [ 'title' => 'Comunidad LGBTQ+',           'shortcode' => '[vx_comunidad slug="out2b"]' ],
        'comunidad-woman'          => [ 'title' => 'Comunidad Woman',            'shortcode' => '[vx_comunidad slug="woman"]' ],
        'comunidad-senior'         => [ 'title' => 'Comunidad Senior',           'shortcode' => '[vx_comunidad slug="senior"]' ],
        'mis-eventos'              => [ 'title' => 'Mis eventos',                'shortcode' => '[vx_mis_eventos]' ],
    ];

    // Eliminar la página mi-4dinner si existe (fue renombrada a 4dinner)
    $mi4dinner = get_page_by_path( 'mi-4dinner' );
    if ( $mi4dinner ) {
        wp_delete_post( $mi4dinner->ID, true );
    }

    foreach ( $pages as $slug => $data ) {
        $existing = get_page_by_path( $slug );
        if ( ! $existing ) {
            wp_insert_post( [
                'post_title'   => $data['title'],
                'post_name'    => $slug,
                'post_content' => $data['shortcode'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ] );
        } else {
            // Actualizar el shortcode si cambió (útil cuando se refactoriza la estructura)
            if ( trim( $existing->post_content ) !== $data['shortcode'] ) {
                wp_update_post( [
                    'ID'           => $existing->ID,
                    'post_content' => $data['shortcode'],
                    'post_title'   => $data['title'],
                ] );
            }
        }
    }

    // Set the home page as the static front page
    $home_page = get_page_by_path( 'home' );
    if ( $home_page ) {
        update_option( 'show_on_front', 'page' );
        update_option( 'page_on_front', $home_page->ID );
    }
}
