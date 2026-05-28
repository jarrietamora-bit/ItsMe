# Manual de Instalación — BT-Support en cPanel

**Versión:** 1.0 | **Plataforma:** PHP 7.4+ / MySQL 5.7+ / cPanel

---

## Requisitos del servidor

| Requisito | Versión mínima |
|-----------|---------------|
| PHP | 7.4 o superior (recomendado 8.x) |
| MySQL / MariaDB | 5.7 / 10.3 o superior |
| Extensión PDO MySQL | Habilitada |
| Extensión OpenSSL | Habilitada |
| Extensión Fileinfo | Habilitada |
| Servidor web | Apache (con mod_rewrite) |

---

## Paso 1: Descargar y preparar los archivos

1. Descargue el archivo `bt-support.zip` del repositorio.
2. Descomprima el archivo en su computadora.
3. Verá la carpeta `bt-support/` con todos los archivos del sistema.

---

## Paso 2: Subir los archivos al servidor con cPanel

### Opción A: Administrador de archivos de cPanel

1. Inicie sesión en su cPanel (normalmente `https://sudominio.com:2083` o `https://sudominio.com/cpanel`).
2. Haga clic en **Administrador de archivos**.
3. Navegue a la carpeta `public_html` (o la carpeta donde quiere instalar).
4. Haga clic en **Subir** → Seleccione el archivo ZIP.
5. Espere a que suba completamente.
6. Haga clic derecho sobre el ZIP → **Extraer**.
7. Extraiga en `public_html/` o en una subcarpeta (ej. `public_html/soporte/`).

### Opción B: FTP (FileZilla)

1. Abra FileZilla y conéctese con sus credenciales FTP de cPanel.
2. En la parte derecha (servidor), navegue a `public_html/`.
3. Arrastre la carpeta `bt-support/` a la carpeta destino.
4. Espere a que se complete la transferencia.

---

## Paso 3: Crear la base de datos MySQL en cPanel

1. En cPanel, busque y haga clic en **MySQL Databases** (Bases de datos MySQL).
2. En **Create New Database**, escriba un nombre (ej. `bt_support`) → clic **Create Database**.
3. En **MySQL Users** → **Add New User**:
   - Username: `bt_user` (o el nombre que prefiera)
   - Password: use el generador de contraseñas
   - Haga clic en **Create User**
4. En **Add User to Database**:
   - Seleccione el usuario y la base de datos creados
   - Haga clic en **Add**
   - En permisos, seleccione **All Privileges** → **Make Changes**
5. **Anote** el nombre completo de la BD (suele ser `tuusuario_bt_support`), usuario y contraseña.

---

## Paso 4: Verificar configuración de PHP

1. En cPanel, busque **MultiPHP Manager** o **PHP Version**.
2. Asegúrese de que la versión de PHP sea **7.4 o superior** (recomendado 8.1 o 8.2).
3. En **PHP Extensions** o **Select PHP Version**, verifique que estén habilitadas:
   - `pdo_mysql`
   - `openssl`
   - `fileinfo`
   - `mbstring`
4. En **PHP Options**, configure:
   - `upload_max_filesize` = `10M`
   - `post_max_size` = `12M`
   - `max_execution_time` = `60`

---

## Paso 5: Ejecutar el instalador web

1. Abra su navegador y vaya a:
   ```
   https://sudominio.com/bt-support/install/
   ```
   *(Ajuste la URL según donde subió los archivos)*

2. **Paso 1 — Requisitos:**
   - El sistema verifica automáticamente todos los requisitos.
   - Todos deben aparecer con ✓ verde.
   - Si alguno falla, revise la configuración de PHP en cPanel.
   - Haga clic en **Continuar**.

3. **Paso 2 — Base de datos:**
   - Servidor MySQL: `localhost` (en cPanel casi siempre es localhost)
   - Nombre de la BD: el nombre completo que anotó en el Paso 3
   - Usuario MySQL: el usuario que creó en el Paso 3
   - Contraseña: la contraseña del usuario MySQL
   - Puerto: `3306` (predeterminado)
   - Haga clic en **Conectar y crear tablas**
   - El instalador crea todas las tablas automáticamente.

4. **Paso 3 — Cuenta de administrador:**
   - Nombre completo del administrador
   - Correo electrónico (será el usuario de acceso)
   - Contraseña (mínimo 8 caracteres)
   - Haga clic en **Crear cuenta**

5. **Paso 4 — Información de la empresa:**
   - Nombre de la empresa
   - URL del sistema: `https://sudominio.com/bt-support` (URL exacta)
   - Idioma predeterminado: Español o English
   - Zona horaria: seleccione la de su país
   - Color principal: color de la marca
   - Haga clic en **Finalizar instalación**

6. **Paso 5 — ¡Listo!**
   - Haga clic en **Ir al sistema**.
   - Inicie sesión con el correo y contraseña del administrador.

---

## Paso 6: Seguridad post-instalación (IMPORTANTE)

### Eliminar la carpeta `install/`

Por seguridad, **elimine o renombre** la carpeta de instalación:

1. En cPanel → Administrador de archivos.
2. Navegue a `public_html/bt-support/`.
3. Haga clic derecho en la carpeta `install/` → **Eliminar**.

O renómbrela: clic derecho → **Renombrar** → `install_backup_eliminar`.

### Proteger archivos sensibles

El archivo `.htaccess` incluido ya protege los archivos `.sql`, `.json` y `.env`.
Verifique que esté presente en la raíz del sistema.

---

## Paso 7: Configurar correo electrónico

1. Inicie sesión como administrador.
2. Vaya a **Configuración → Correo electrónico**.
3. Elija el método de envío:

   **Opción A — PHP mail() (más fácil):**
   - Seleccione "PHP mail()" en el método de envío.
   - Funciona directamente con el servidor de correo de cPanel.
   - No requiere configuración adicional.

   **Opción B — SMTP personalizado (más confiable):**
   - Seleccione "SMTP personalizado".
   - Complete los datos de su servidor SMTP.
   - Ejemplos:
     - **Gmail:** smtp.gmail.com, puerto 587, TLS
     - **cPanel email:** mail.sudominio.com, puerto 587
   - Para Gmail: necesita una "Contraseña de aplicación" (activar verificación en 2 pasos primero).
4. Haga clic en **Guardar**.
5. Use el botón **Enviar correo de prueba** para verificar que funcione.

---

## Paso 8: Configuración de URL amigables (.htaccess)

El sistema incluye un archivo `.htaccess` preconfigurado. Si las URLs no funcionan:

1. En cPanel → Administrador de archivos → `bt-support/`.
2. Verifique que el archivo `.htaccess` existe.
3. Si el sistema está en una subcarpeta, edite la línea `RewriteBase`:
   ```apache
   RewriteBase /bt-support/
   ```
   Cambie `/bt-support/` por la ruta real (ej. `/soporte/`).

4. Si tiene problemas, active `mod_rewrite` en cPanel:
   - Busque **Apache Handlers** o contacte a su proveedor de hosting.

---

## Instalar en múltiples dominios o subdominios

Para instalar BT-Support en varios servidores:

1. Suba los mismos archivos a cada servidor.
2. Cree una base de datos MySQL **diferente** en cada servidor.
3. Ejecute el instalador en cada uno: `https://dominio2.com/bt-support/install/`
4. Cada instalación es completamente independiente.
5. No olvide eliminar la carpeta `install/` en cada servidor.

---

## Solución de problemas comunes

| Problema | Solución |
|---------|---------|
| Error "mod_rewrite not found" | Active mod_rewrite en cPanel o contacte al hosting |
| Error de conexión a BD | Verifique nombre completo de BD (incluye prefijo del hosting) |
| Página en blanco | Active el log de errores PHP en cPanel para ver el error |
| Archivos no suben | Aumente `upload_max_filesize` en PHP Options |
| Correos no llegan | Verifique configuración SMTP, revise carpeta de spam |
| Error 500 | Revise el archivo `.htaccess` (¿está en la ruta correcta?) |
| "Permission denied" en uploads/ | Cambie permisos de la carpeta a 755 en cPanel |

### Cambiar permisos de carpetas

Si los archivos no se suben:
1. cPanel → Administrador de archivos
2. Clic derecho en la carpeta `uploads/` → **Cambiar permisos**
3. Establezca permisos en **755**
4. Marque "Recurse into subdirectories"

---

## Actualización del sistema

Para actualizar a una nueva versión:
1. Haga **copia de seguridad** de `config/config.php` y la carpeta `uploads/`.
2. Suba los nuevos archivos (sobreescriba todo excepto `config/config.php` y `uploads/`).
3. Si hay cambios de base de datos, se indica en las notas de la versión con SQL a ejecutar.

---

## Soporte técnico

Si necesita ayuda con la instalación, revise:
- Que PHP sea versión 7.4+
- Que las extensiones PDO, OpenSSL y Fileinfo estén activas
- Que la carpeta `uploads/` tenga permisos 755
- Que el archivo `.htaccess` esté presente y correcto
