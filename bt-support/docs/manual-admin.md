# Manual del Administrador — BT-Support

**Rol:** Administrador (Admin) / Super Admin

---

## Acceso al sistema

1. Vaya a la URL del sistema (ej. `https://sudominio.com/bt-support/`).
2. Ingrese su **correo electrónico** y **contraseña**.
3. Haga clic en **Iniciar sesión**.
4. Si olvidó su contraseña, haga clic en **¿Olvidó su contraseña?** e ingrese su email. Recibirá un enlace de restablecimiento.

---

## Panel principal (Dashboard)

Al iniciar sesión verá el panel con:
- **Tickets abiertos** — número de tickets pendientes
- **En progreso** — tickets siendo atendidos
- **Resueltos hoy** — tickets resueltos en el día
- **Vencidos (SLA)** — tickets que superaron el tiempo límite
- **Sin asignar** — tickets que no tienen agente asignado
- **Gráfica de estados** — distribución visual de tickets
- **Lista de tickets recientes** — últimos tickets del sistema
- **Carga de trabajo por agente** — cuántos tickets tiene cada agente

---

## Gestión de Usuarios

### Crear un nuevo usuario

1. Menú lateral → **Usuarios** → **Nuevo usuario**
2. Complete los campos:
   - **Nombre** (obligatorio)
   - **Correo electrónico** (obligatorio, único)
   - **Teléfono** (opcional)
   - **Contraseña** (mínimo 8 caracteres)
   - **Rol:** seleccione el rol apropiado
   - **Idioma:** español o inglés
   - **Foto de perfil** (opcional)
3. **Asignar departamentos:** marque los departamentos a los que pertenece.
4. Si el rol es **Supervisor**, seleccione el departamento principal del que será supervisor.
5. Haga clic en **Crear**.

### Roles disponibles

| Rol | Acceso |
|-----|--------|
| **Super Admin** | Control total del sistema |
| **Admin** | Todo excepto funciones de super admin |
| **Supervisor** | Ve y gestiona tickets de su departamento |
| **Agente** | Responde tickets que le son asignados |
| **Cliente** | Crea y ve sus propios tickets |

### Editar usuario

1. **Usuarios** → clic en el ícono de lápiz del usuario.
2. Modifique los campos necesarios.
3. Para cambiar contraseña: complete el campo "Nueva contraseña".
4. Para no cambiar contraseña: deje el campo vacío.
5. Haga clic en **Guardar**.

### Desactivar/Eliminar usuario

- **Desactivar:** clic en el ícono de pausa (⏸). El usuario no podrá iniciar sesión pero sus tickets se conservan.
- **Reactivar:** clic en el ícono de play (▶).
- **Eliminar:** clic en el ícono de papelera (🗑). Esta acción es permanente.

---

## Gestión de Departamentos

### Crear departamento

1. Menú → **Departamentos** → **Nuevo departamento**
2. Complete:
   - **Nombre** (ej. "Soporte Técnico")
   - **Descripción** (opcional)
   - **Correo del departamento** (opcional, para referencia)
   - **Color** (identificador visual del departamento)
3. **Asignar miembros:** marque los agentes que pertenecen a este departamento.
4. **Supervisor:** seleccione quién será el supervisor del departamento.
5. Haga clic en **Crear**.

### Editar departamento

- Clic en el botón **Editar** en la tarjeta del departamento.
- Modifique los miembros y supervisor según necesidad.
- Haga clic en **Guardar**.

---

## Gestión de Categorías

Las categorías ayudan a clasificar los tickets.

1. Menú → **Categorías** → **Nueva categoría**
2. Complete:
   - **Nombre** de la categoría
   - **Categoría padre** (opcional, para subcategorías)
   - **Departamento** (opcional, si aplica solo a un dpto.)
3. Haga clic en **Crear**.

Ejemplo de estructura:
```
Soporte Técnico
  └─ Hardware
  └─ Software
  └─ Red/Internet
Facturación
  └─ Pagos
  └─ Facturas
```

---

## Gestión de Tickets

### Ver todos los tickets

Menú → **Tickets** — muestra todos los tickets del sistema.

### Filtros disponibles

- **Búsqueda:** número de ticket, asunto, nombre o email del cliente
- **Estado:** Abierto, En progreso, En espera, Resuelto, Cerrado
- **Prioridad:** Baja, Normal, Alta, Urgente, Crítica
- **Departamento:** filtra por departamento específico

### Acciones masivas

1. Marque las casillas de los tickets a modificar.
2. Seleccione la acción en el menú desplegable:
   - Asignarme los tickets
   - Cambiar estado
3. Haga clic en **Enviar**.

### Ver un ticket

Haga clic en el número del ticket. Verá:
- **Conversación completa** (mensajes del cliente y agentes)
- **Panel lateral** con información del cliente, detalles del ticket, asignación
- Opción de **transferir departamento**
- Botones de acción: Resolver, Cerrar, Reabrir

---

## Configuración General (Empresa)

**Configuración → General**

| Campo | Descripción |
|-------|-------------|
| Nombre de la empresa | Aparece en el header y correos |
| Slogan | Subtítulo bajo el nombre |
| Dirección, Teléfono, Web | Información de contacto |
| Color principal | Color de navbar, botones y badges |
| Logo | Imagen que aparece en el header (recomendado: PNG transparente 200×60px) |
| Favicon | Ícono de la pestaña del navegador (32×32px) |
| Idioma predeterminado | Español o English |
| Zona horaria | Zona horaria de su país |
| Permitir registro público | Los clientes pueden registrarse solos |
| Cerrar tickets inactivos | Días sin actividad para cierre automático |

**Para cambiar el logo:**
1. Haga clic en **Subir logo** → seleccione el archivo.
2. Formatos soportados: JPG, PNG, GIF, SVG.
3. Haga clic en **Guardar**.
4. El logo aparecerá de inmediato en el header.

---

## Configuración de Correo Electrónico

**Configuración → Correo electrónico**

### Método PHP mail() (recomendado para cPanel)
- No requiere configuración adicional.
- Usa el servidor de correo del hosting.

### Método SMTP personalizado
- **Servidor:** dirección del servidor SMTP
- **Puerto:** 587 (TLS) o 465 (SSL)
- **Usuario:** su dirección de correo
- **Contraseña:** contraseña de la cuenta de correo
- **Seguridad:** TLS o SSL

### Probar configuración
- Ingrese un email de destino en **Enviar correo de prueba**.
- Haga clic en el botón.
- Si recibe el correo, la configuración es correcta.

---

## Configuración SLA

**Configuración → SLA**

Los SLA (Service Level Agreements) definen los tiempos máximos de respuesta y resolución según la prioridad del ticket.

| Campo | Descripción |
|-------|-------------|
| Primera respuesta (horas) | Tiempo máximo para dar la primera respuesta |
| Resolución (horas) | Tiempo máximo para resolver el ticket |
| Solo horas hábiles | Si activo, no cuenta fuera del horario laboral |

Los tickets con SLA vencido aparecen marcados en rojo.

---

## Plantillas de Correo

**Configuración → Plantillas de correo**

Edite los correos que el sistema envía automáticamente:
- **Ticket creado** — se envía al cliente cuando abre un ticket
- **Nueva respuesta** — se envía cuando hay una nueva respuesta
- **Ticket resuelto** — se envía cuando el ticket es marcado como resuelto
- **Restablecer contraseña** — para recuperación de cuenta

**Variables disponibles en las plantillas:**
- `{{ticket_number}}` — número del ticket
- `{{subject}}` — asunto del ticket
- `{{name}}` — nombre del destinatario
- `{{reply_message}}` — contenido de la respuesta
- `{{ticket_url}}` — enlace al ticket
- `{{reset_url}}` — enlace para restablecer contraseña
- `{{company_name}}` — nombre de la empresa

Cada plantilla tiene versión en español e inglés.

---

## Respuestas Predefinidas

**Configuración → Respuestas predefinidas**

Las respuestas predefinidas son textos que los agentes pueden insertar con un clic al responder tickets.

1. Haga clic en **Nueva respuesta predefinida**.
2. Ingrese un nombre descriptivo (ej. "Bienvenida") y el contenido.
3. Puede asociarla a un departamento específico o dejarla disponible para todos.

---

## Base de Conocimiento

**Conocimiento → Gestionar artículos**

1. Haga clic en **Nuevo artículo**.
2. Complete título y contenido en español e inglés.
3. Asigne una categoría.
4. Elija el estado:
   - **Borrador** — solo visible para administradores y agentes
   - **Publicado** — visible para todos
5. Defina si es **público** (visible sin iniciar sesión) o **privado** (solo agentes).

---

## Reportes y Estadísticas

**Reportes**

Seleccione el rango de fechas y opcionalmente filtre por departamento.

**Métricas disponibles:**
- Total de tickets en el período
- Tickets por estado
- SLA incumplidos
- Tiempo promedio de primera respuesta
- Tiempo promedio de resolución
- Calificación CSAT (satisfacción del cliente)
- Rendimiento por agente (tabla con totales, resueltos, tiempo promedio)
- Volumen diario (gráfica de líneas)
- Distribución por departamento (gráfica de barras)
- Top 10 categorías

**Exportar datos:**
Haga clic en **Exportar CSV** para descargar el reporte en formato Excel/CSV.
