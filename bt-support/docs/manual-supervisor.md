# Manual del Supervisor — BT-Support

**Rol:** Supervisor

---

## ¿Qué puede hacer un Supervisor?

El Supervisor gestiona los tickets y agentes de **su(s) departamento(s) asignado(s)**. No puede ver tickets de otros departamentos ni modificar la configuración global del sistema.

### Resumen de permisos

| Función | Supervisor |
|---------|-----------|
| Ver tickets de su departamento | ✅ |
| Ver tickets de otros departamentos | ❌ |
| Responder tickets | ✅ |
| Asignar ticket a agente | ✅ |
| Reasignar ticket a otro agente | ✅ |
| Transferir ticket a otro departamento | ✅ |
| Agregar notas internas | ✅ |
| Resolver / Cerrar tickets | ✅ |
| Ver reportes de su departamento | ✅ |
| Crear/editar usuarios | ❌ |
| Modificar configuración del sistema | ❌ |

---

## Acceso al sistema

1. Ingrese a la URL del sistema.
2. Use su correo y contraseña asignados.
3. Al iniciar, verá el **Panel principal** con métricas de su departamento.

---

## Panel principal (Dashboard)

El panel muestra estadísticas **solo de su(s) departamento(s)**:

- **Tickets abiertos** en su departamento
- **En progreso** — tickets siendo atendidos
- **Resueltos hoy**
- **Vencidos (SLA)** — alerta de tickets con tiempo vencido
- **Sin asignar** — tickets del departamento que nadie tiene asignado
- **Carga de trabajo por agente** — tabla con tickets activos de cada agente

---

## Ver los tickets del departamento

1. Menú → **Tickets**
2. Verá **todos los tickets** de su(s) departamento(s).
3. Use los filtros para buscar por estado, prioridad, texto o agente asignado.
4. Las filas en **rojo** indican que el SLA está vencido — deben atenderse con urgencia.

---

## Asignar un ticket sin asignar

1. Haga clic en el número del ticket.
2. En el panel lateral derecho, al lado de **Asignado a**, haga clic en el ícono de persona (👤).
3. Se abrirá una ventana con la lista de agentes del departamento.
4. Seleccione el agente y haga clic en **Guardar**.
5. El agente recibirá una notificación automática.

---

## Reasignar un ticket a otro agente

Si un agente ya tiene asignado el ticket pero necesita transferirlo:

1. Abra el ticket.
2. En el panel lateral → **Asignado a** → clic en el ícono de edición (👤).
3. Seleccione el nuevo agente en la lista desplegable.
4. Haga clic en **Guardar**.
5. Ambos agentes recibirán notificación del cambio.

---

## Transferir ticket a otro departamento

Si el ticket corresponde a otro departamento:

1. Abra el ticket.
2. En el panel lateral → sección **Transferir departamento**.
3. Seleccione el nuevo departamento en el menú desplegable.
4. Haga clic en **Enviar**.
5. El ticket se asignará automáticamente al nuevo departamento (sin agente asignado hasta que alguien lo tome).

---

## Responder a un ticket

1. Abra el ticket.
2. En la sección de respuesta al final:
   - **Respuesta pública** — el cliente la verá
   - **Nota interna** — solo visible para agentes y supervisores
3. Escriba el mensaje.
4. Opcionalmente adjunte archivos.
5. Haga clic en **Enviar**.

---

## Revisar la carga de trabajo del equipo

En el **Dashboard**, verá una tabla con:
- Nombre del agente
- Tickets activos asignados
- Tickets resueltos

Esto le ayuda a distribuir el trabajo equitativamente. Si un agente tiene demasiados tickets, puede reasignar algunos a otro agente con menos carga.

---

## Escalar un ticket al administrador

Si un ticket requiere atención superior:
1. Abra el ticket.
2. Agregue una **nota interna** explicando por qué necesita escalarlo.
3. Contacte directamente al administrador (el sistema no tiene botón de escalado automático, pero las notas internas son visibles para admins).

---

## Ver reportes de su departamento

1. Menú → **Reportes**
2. Los reportes muestran **solo datos de su departamento**.
3. Seleccione el rango de fechas.
4. Verá métricas de rendimiento, tiempos promedio y carga por agente.

---

## Notificaciones

Recibirá notificaciones cuando:
- Llegue un ticket nuevo al departamento
- Un ticket esté por vencer el SLA
- Un cliente responda a un ticket del departamento
- Se le asigne un ticket directamente

Haga clic en el ícono de campana (🔔) en el header para ver las notificaciones.

---

## Cambiar idioma

Haga clic en **ES / EN** en el header superior derecho para cambiar entre español e inglés.
