# BT-Support — Guía del proyecto para Claude

Sistema de tickets de soporte web desarrollado para **Better Technologies** (bettertechcr.com).
Desplegado en cPanel con PHP + MySQL. Bilingüe ES/EN completo.

---

## Stack técnico

| Capa | Tecnología |
|------|-----------|
| Backend | PHP 7.4+ (compatible 8.x) |
| Base de datos | MySQL 5.7+ / MariaDB, PDO con ERRMODE_EXCEPTION |
| Frontend | Bootstrap 5.3, Bootstrap Icons, Chart.js 4.4 |
| Servidor | Apache + mod_rewrite (cPanel shared hosting) |
| Sesiones | Almacenadas en `storage/sessions/` (evita problemas de permisos en /tmp) |
| Correo | PHPMailer vía `includes/mail.php` |

---

## Estructura de archivos

```
bt-support/
├── index.php              ← Router central (lee ?_url= o ?page=)
├── config/
│   ├── config.php         ← Configuración del sistema (BD, SMTP, app_url…) — NO commitear con credenciales
│   └── database.php       ← Singleton PDO: db()
├── includes/
│   ├── auth.php           ← Sesiones, roles, CSRF, remember-me, login/logout
│   ├── functions.php      ← Helpers globales: base_url(), t(), time_ago(), paginate(), check_sla_breach()…
│   ├── lang.php           ← load_lang(), current_lang(), switch_lang(), t()
│   └── mail.php           ← Clase Mailer: send(), sendTicketReply(), sendTicketResolved(), sendPasswordReset()
├── languages/
│   ├── es.php             ← Todas las cadenas en español (array asociativo)
│   └── en.php             ← Todas las cadenas en inglés
├── pages/                 ← Una carpeta/archivo por sección
│   ├── dashboard.php
│   ├── tickets/{index,create,view,edit}.php
│   ├── users/{index,create,edit}.php
│   ├── departments/{index,create,edit}.php
│   ├── categories/index.php
│   ├── knowledge/{index,article,manage,edit}.php
│   ├── reports/index.php
│   ├── settings/{general,email,sla,templates,canned,tags}.php
│   ├── login.php, logout.php, register.php
│   ├── forgot-password.php, reset-password.php
│   └── profile.php
├── templates/
│   ├── header.php         ← <!DOCTYPE>, navbar, sidebar include, flash messages
│   ├── sidebar.php        ← Menú lateral (muestra/oculta según rol)
│   └── footer.php         ← Scripts JS, cierre </body></html>
├── ajax/
│   ├── notifications.php  ← GET/POST para el panel de notificaciones
│   ├── tickets.php        ← Acciones AJAX (bulk actions)
│   └── upload.php         ← Subida de archivos
├── assets/
│   ├── css/style.css
│   └── js/main.js
├── install/
│   ├── index.php          ← Instalador web de 5 pasos
│   └── install.sql        ← Schema completo de la BD
├── uploads/               ← Archivos subidos por usuarios (avatars/, logos/, tickets/)
├── storage/sessions/      ← Sesiones PHP
└── docs/                  ← Manuales Markdown (instalación, admin, supervisor, agente, cliente)
```

---

## Router

`index.php` mapea URLs a archivos de página mediante un array `$routes[]`. Cada página incluye las cabeceras/pie a través de `include ROOT . '/templates/header.php'` y `include ROOT . '/templates/footer.php'`.

```php
// Patrón de una página típica
if (!defined('BTSUPPORT')) exit;   // Seguridad: no acceso directo
require_role(['super_admin','admin']);  // Control de acceso
// ... lógica ...
$page_title = t('clave');
include ROOT . '/templates/header.php';
// ... HTML ...
include ROOT . '/templates/footer.php';
```

---

## Base de datos

### Tablas principales

| Tabla | Propósito |
|-------|-----------|
| `users` | Todos los roles: super_admin, admin, supervisor, agent, client |
| `tickets` | Ticket principal con SLA, prioridad, departamento, estado |
| `ticket_replies` | Mensajes/respuestas de cada ticket (is_internal para notas privadas) |
| `ticket_attachments` | Archivos adjuntos a replies |
| `ticket_tags` | Relación N:M tickets ↔ tags |
| `related_tickets` | Relación N:M entre tickets relacionados |
| `departments` | Departamentos activos/inactivos |
| `department_users` | Relación N:M usuarios ↔ departamentos (is_supervisor flag) |
| `categories` | Categorías con soporte para subcategorías (parent_id) |
| `priorities` | Prioridades con colores y tiempos SLA (name_es, name_en) |
| `sla_policies` | Políticas SLA (first_response_h, resolution_h) |
| `tags` | Etiquetas de tickets |
| `ratings` | Calificaciones CSAT de clientes (1–5 estrellas) |
| `notifications` | Notificaciones in-app (campana) |
| `activity_log` | Log de acciones del sistema |
| `login_attempts` | Rate limiting de intentos de login |
| `password_resets` | Tokens de restablecimiento de contraseña |
| `canned_responses` | Respuestas predefinidas para agentes |
| `email_templates` | Plantillas de correo editables (subject/body en ES y EN) |
| `kb_articles` | Artículos de base de conocimiento (title/content en ES y EN) |
| `kb_categories` | Categorías de KB (name_es, name_en, icon) |
| `settings` | Configuración key→value de la empresa |

### Funciones de acceso a BD

```php
db()                     // Singleton PDO — usar siempre en vez de new PDO
db()->prepare($sql)      // Prepared statement (SIEMPRE para queries con parámetros)
db()->query($sql)        // Solo para queries sin parámetros del usuario
```

---

## Sistema de roles

```
super_admin → admin → supervisor → agent → client
```

| Función | super_admin | admin | supervisor | agent | client |
|---------|:-----------:|:-----:|:----------:|:-----:|:------:|
| Configuración sistema | ✅ | ✅ | ❌ | ❌ | ❌ |
| Gestionar usuarios | ✅ | ✅ | ❌ | ❌ | ❌ |
| Ver todos los tickets | ✅ | ✅ | ❌ | ❌ | ❌ |
| Ver tickets de su dpto. | ✅ | ✅ | ✅ | ❌ | ❌ |
| Ver tickets asignados | ✅ | ✅ | ✅ | ✅ | ❌ |
| Ver propios tickets | ✅ | ✅ | ✅ | ✅ | ✅ |
| Asignar/reasignar agentes | ✅ | ✅ | ✅ | ❌ | ❌ |
| Responder tickets | ✅ | ✅ | ✅ | ✅ | ✅ |
| Notas internas | ✅ | ✅ | ✅ | ✅ | ❌ |
| Reportes | ✅ | ✅ | ✅ (solo su dpto.) | ❌ | ❌ |

### Helpers de rol

```php
is_admin()        // true para super_admin y admin
is_supervisor()   // true para super_admin, admin, supervisor
is_agent()        // true para super_admin, admin, supervisor, agent
current_role()    // string: 'super_admin'|'admin'|'supervisor'|'agent'|'client'
current_user()    // array con datos del usuario logueado (cacheado con static)
can_view_ticket($ticket)  // Verifica acceso al ticket según rol
```

---

## Sistema de traducción

```php
t('clave')                      // Retorna string traducido según sesión activa
current_lang()                  // 'es' o 'en'
switch_lang('en')               // Cambia idioma en sesión
_t_lang('clave', 'en', $arg1)  // Traduce en idioma específico (para emails a destinatarios)
```

**Reglas:**
- Toda cadena visible al usuario debe usar `t('clave')` — nunca hardcodear español
- Los archivos `languages/es.php` y `languages/en.php` deben mantenerse sincronizados
- Para strings con `sprintf`, usar `sprintf(t('clave'), $valor)` o `_t_lang('clave', $lang, $valor)`
- Para queries SQL con nombres de columna por idioma: `p.name_{$lang}` (ej. prioridades, KB)

---

## Patrones comunes

### Formulario con POST + CSRF

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    // procesar
    flash('success', t('settings_saved'));
    redirect(base_url('pagina'));
}
```

### Flash messages

```php
flash('success', t('user_created'));   // success | danger | warning | info
flash('danger',  t('error'));
// Se muestran automáticamente en header.php mediante get_flash()
```

### Paginación

```php
$total = (int)db()->query("SELECT COUNT(*) FROM tickets")->fetchColumn();
$pag   = paginate($total, 25, (int)($_GET['page'] ?? 1));
// ... query con LIMIT {$pag['per_page']} OFFSET {$pag['offset']} ...
```

### Notificación in-app

```php
send_notification($user_id, 'assigned', sprintf(t('ticket_assigned_notif'), $num), '', $url);
```

### Envío de correo

```php
mailer()->send($email, $subject, $html_body, $recipient_name);
mailer()->sendTicketReply($ticket, $client, ['message' => $msg]);
mailer()->sendTicketResolved($ticket, $client);
mailer()->sendPasswordReset($email, $name, $reset_url, $lang);
```

---

## Seguridad — patrones aplicados

| Vulnerabilidad | Mitigación |
|---------------|------------|
| SQL Injection | PDO prepared statements en todas las queries con parámetros |
| XSS | `h($var)` = `htmlspecialchars()` en todo output de datos de usuario |
| CSRF | `csrf_field()` en formularios + `csrf_verify()` al procesar POST |
| Open redirect | `$_SERVER['HTTP_REFERER']` validado contra `base_url()` antes de redirigir |
| Brute force | Rate limiting: 5 intentos / 15 min por email+IP en `login_attempts` |
| IDOR | `can_view_ticket()` verifica acceso por rol antes de mostrar ticket |
| Path traversal | `upload_file()` usa nombre aleatorio + whitelist de extensiones |
| Direct file access | `if (!defined('BTSUPPORT')) exit;` en todas las páginas |

---

## SLA y escalación

`check_sla_breach()` en `includes/functions.php` se ejecuta en cada request de usuario logueado:
1. Busca tickets con `sla_due_at < NOW()` y `sla_breached = 0`
2. Los marca como `sla_breached = 1` en batch
3. Si `sla_escalation_enabled = 1` en settings: envía notificaciones in-app + email a supervisores y/o admins
4. Usa `_t_lang($key, $user['language'])` para enviar cada email en el idioma del destinatario

---

## Configuración (config/config.php)

```php
return [
    'installed'      => true,
    'db_host'        => 'localhost',
    'db_name'        => '...',
    'db_user'        => '...',
    'db_pass'        => '...',         // NO commitear
    'app_url'        => 'https://dominio.com/bt-support',
    'timezone'       => 'America/Costa_Rica',
    'session_timeout'=> 120,           // minutos
    'upload_max_mb'  => 10,
    'smtp_host'      => '...',
    'smtp_port'      => 587,
    'smtp_user'      => '...',
    'smtp_pass'      => '...',         // NO commitear
    'smtp_from'      => '...',
    'smtp_from_name' => 'BT-Support',
    'smtp_secure'    => 'tls',
    'mail_method'    => 'php',         // 'php' o 'smtp'
];
```

**Importante:** `config/config.php` contiene credenciales — nunca se sube al repositorio. El `.gitignore` lo excluye. El instalador lo crea automáticamente.

---

## Instalación en servidor nuevo

1. Subir carpeta `bt-support/` a `public_html/` en cPanel
2. Crear BD MySQL en cPanel → anotar nombre completo (`usuario_nombrebd`), usuario y contraseña
3. Abrir `https://dominio.com/bt-support/install/` y seguir el asistente (5 pasos)
4. **Eliminar** la carpeta `install/` después de instalar
5. Verificar permisos 755 en `uploads/` y `storage/`

Ver manual completo: `docs/manual-instalacion.md`

---

## Tareas frecuentes de desarrollo

### Agregar una nueva clave de traducción

1. Añadir en `languages/es.php`: `'nueva_clave' => 'Texto en español',`
2. Añadir en `languages/en.php`: `'nueva_clave' => 'Text in English',`
3. Usar en PHP: `t('nueva_clave')`

### Agregar una nueva página

1. Crear `pages/nueva-seccion.php` con `if (!defined('BTSUPPORT')) exit;`
2. Agregar entrada en el array `$routes` de `index.php`
3. Agregar enlace en `templates/sidebar.php` con la visibilidad de rol correcta

### Agregar una nueva columna a la BD

Ejecutar el SQL directamente en phpMyAdmin / terminal MySQL y actualizar `install/install.sql` para que nuevas instalaciones la incluyan.

---

## Estado del proyecto

- ✅ Sistema completo de tickets (crear, responder, asignar, resolver, cerrar, reabrir)
- ✅ SLA con escalación automática bilingüe
- ✅ Gestión de usuarios, departamentos, categorías, prioridades
- ✅ Base de conocimiento pública/privada bilingüe
- ✅ Reportes con gráficas + exportación CSV
- ✅ Calificaciones CSAT
- ✅ Notificaciones in-app y por correo
- ✅ Plantillas de correo editables por el admin
- ✅ Respuestas predefinidas (canned responses)
- ✅ Etiquetas en tickets
- ✅ Tickets relacionados
- ✅ Remember-me con rotación de token
- ✅ Traducción completa ES/EN en todos los archivos
- ✅ Instalador web de 5 pasos
