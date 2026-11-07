# Vitrinexo — Plugin Core

## El proyecto

Vitrinexo es una plataforma B2B de directorio de servicios profesionales. Empresa independiente. **Nunca mencionar Maggiore Marketing** en ningún contenido público. **No restringir el alcance geográfico** a Latinoamérica — la visión incluye países de habla hispana, Brasil, Estados Unidos y Europa.

**Sitio:** https://vitrinexo.com  
**Admin WP:** https://vitrinexo.com/wp-admin  
**Par de repositorios:** `luismaggiore/vitrinexo-core` (este) y `luismaggiore/vitrinexo-theme`

## Reglas no negociables

- Siempre **Vitrinexo** — nunca "VitriNexo"
- Programa: **Miembro Pionero / Miembros Pioneros**
- Siempre **"distintivo"** — nunca "badge"
- Sin guiones largos (—) en copy
- Español chileno: "tienes", "puedes" — nunca voseo argentino/uruguayo

## Stack

- WordPress 7.0, PHP, Bootstrap 5.3.3, Tabler Icons 3.19.0, fuente Switzer Variable
- Colores: los tokens del tema (`--coral`, `--hueso`, `--tinta`, y las familias lavanda, rosa, celeste,
  salvia, mantequilla y terracota, cada una con hover, presión, suave y tinta). El teal `#00aeb8` y el
  navy `#1a2335` son de antes del repintado y ya no existen en la paleta
- En el plugin se escribe `var(--token)`. El hexadecimal literal vale solo donde la hoja del tema no
  carga: correos, pantallas de wp-admin y páginas de `wp_die()`. Lo mide `pruebas-colores.mjs`
- REST namespace: `vitrinexo/v1`
- Email: FluentSMTP + Resend desde `hola@vitrinexo.com`

## Estructura del plugin

```
vitrinexo-core/
├── vitrinexo-core.php               ← archivo principal, hooks, vx_get_planes()
├── rest/
│   └── rest-auth.php                ← /registrar, /aprobar-usuario, /rechazar-usuario, /stats/inscritos
├── modules/
│   ├── admin/
│   │   ├── class-vx-admin-users.php     ← tabla usuarios con columnas custom + hover-to-edit
│   │   ├── class-vx-admin-emails.php    ← panel Vitrinexo → Emails (5 plantillas editables)
│   │   └── class-vx-admin-membership.php
│   ├── email/
│   │   ├── class-vx-mailer.php          ← wrapper wp_mail
│   │   └── class-vx-email-templates.php ← templates HTML + tpl_notificacion_admin()
│   └── users/
│       ├── class-vx-user-meta.php       ← constantes de meta keys
│       └── class-vx-verification.php    ← activate_account(): pionero auto + vencimiento 3 meses
└── shortcodes/
    └── shortcodes-public.php            ← landing page + formulario de registro
```

## Deploy

Push a `main` → GitHub Actions → rsync SSH → Hostinger.

Antes del rsync corren, en este orden: `pruebas-emojis.mjs`, `pruebas-vencimiento.php` y
`pruebas-correos.php` (ninguna necesita el tema), después se clona el tema al lado, y recién ahí `pruebas-colores.mjs` y el banco del tema.
La prueba de colores va después del clonado porque compara su copia de la paleta contra el `:root` del
tema, y eso solo puede hacerlo con el tema presente. **Si el tema repinta, el deploy del plugin frena
hasta que la copia de `pruebas-colores.mjs` se ponga al día**: es a propósito, una copia que se
desincroniza en silencio deja pasar colores que ya no existen.

```yaml
# Secrets requeridos en el repo
SSH_HOST: 195.200.3.42
SSH_PORT: 65002
SSH_USERNAME: u969893599
SSH_PRIVATE_KEY: <clave Ed25519 privada>
SSH_TARGET: /home/u969893599/public_html/wp-content/plugins/vitrinexo-core
```

El workflow tiene **retry automático** (espera 90s si el primer intento falla). No usar `sshpass` — la autenticación es por clave SSH.

## Flujo de registro

1. Formulario en `/#afiliado-original` → POST `/wp-json/vitrinexo/v1/registrar`
2. Se crea usuario WP + CPT `vx_empresa`
3. Email institucional → verificación automática; email genérico → pendiente manual
4. Notificación a admins con botones Aprobar/Rechazar (tokens de un solo uso, editables en Vitrinexo → Emails)
5. Al aprobar → `VX_Verification::activate_account()`:
   - `vx_estado = 'activo'`
   - Si activos ≤ 100 → `vx_es_fundador = 1` (Pionero automático)
   - Vencimiento = aprobación + 3 meses, guardado como **timestamp** en `vx_plan_vencimiento` (0 = sin vencimiento)

El vencimiento se escribe una sola vez, al aprobar, y después solo lo cambia un administrador. Todo lo demás lo lee con `VX_Membership::parse_expiry()`, que es lo único que sabe interpretar el meta. Nadie recalcula la fecha por su cuenta.

## Panel admin (users.php)

Columnas: Nombre de usuario, Nombre, Empresa, Cargo, Teléfono, Correo, Estado, Registro (solo lectura), Plan (dropdown en hover), Vencimiento (date input en hover), Pionero.

- Los administradores muestran "—" en Plan/Vencimiento/Pionero
- Plan y Vencimiento usan `window.location` con nonce en URL (no `<form>`) para no interferir con el bulk delete de WordPress
- La columna Vencimiento **solo lee** el meta. Si no hay fecha guardada (cuenta sin aprobar) estima desde el registro y lo dice en la celda, pero no guarda nada: dibujar una tabla no decide un vencimiento

## Planes

Configurables en Vitrinexo → Planes (`vx_get_planes()`). Defaults: Gratuito, Miembro Pionero, Mensual, Anual, Preferencial.

## Lecciones técnicas

- Heredoc (`<< 'EOF'`) dentro de `run:` en GitHub Actions YAML causa errores de parseo → usar base64
- `wp eval-file` requiere `<?php` al inicio del archivo
- `admin_menu` debe registrarse desde `init`, no desde `admin_init`
- Formularios inline en tablas de WP admin no pueden usar `<form>` anidado con el bulk form → usar JS navigation

## Comunidades

Las piezas de la línea **se nombran siempre con la marca madre adelante**, sin excepción y también en
conversación: se escribe Vitrinexo 4Dinner, nunca 4Dinner a secas. Lo dice el manual en
`estrategia-linea-de-producto` y lo mide `pruebas/pantalla/vocabulario.mjs` del tema, sobre el texto
renderizado. En el código los identificadores siguen siendo cortos (`vx_landing_4dinner`,
`comunidad-card--woman`): la regla es sobre el habla, no sobre los nombres de variable.

Esa prueba mide páginas. Los correos no son una página —no los sirve el sitio ni los abre Playwright—
y por eso los mide `pruebas-correos.php` acá en el plugin: renderiza las veinticuatro plantillas y les
pasa el mismo vocabulario y la misma regla. Es el texto que más lejos llega, porque le llega a cada
miembro sin que entre al sitio. **Si se agrega una palabra prohibida en el tema, se agrega también en
`pruebas-correos.php`**: la lista está repetida a propósito, porque el plugin se despliega solo y no
puede depender de que el tema esté al lado para saber cómo se habla.

- **Vitrinexo LGBTQ+** — comunidad LGBTQ+ en el mundo empresarial
- **Vitrinexo Woman** — mujeres líderes
- **Vitrinexo Senior** — ejecutivos con trayectoria
- **Vitrinexo 4Dinner** — cenas de networking (4 personas, miércoles 20:00 hora local)

Las dos únicas excepciones son las palabras que además son español corriente y ahí nombran a la gente,
no a la pieza: «ejecutivos senior» (el cargo, en minúscula) y «profesionales LGBTQ+» (las personas).

## Pendientes

- Integración de pagos con Stripe
- Contenido de la sección 4Dinner
