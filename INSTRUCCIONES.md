# It's Me — App Android

## Credenciales iniciales
- **Usuario:** `admin`
- **Contraseña:** `admin123`

---

## Opción A: Obtener el APK con GitHub (RECOMENDADA — gratis y automático)

### Paso 1: Crear cuenta en GitHub
1. Vaya a [github.com](https://github.com) y cree una cuenta gratuita

### Paso 2: Crear repositorio
1. Click en el botón verde **"New"** o **"+"** → *New repository*
2. Nombre: `ItsMe`
3. Asegúrese de que esté en **Public**
4. Click **"Create repository"**

### Paso 3: Subir el proyecto
1. En la página del repositorio, click en **"uploading an existing file"**
2. Arrastre TODOS los archivos y carpetas de este ZIP al navegador
3. Click **"Commit changes"**

### Paso 4: Obtener el APK
1. Click en la pestaña **"Actions"**
2. Verá el workflow **"Build Android APK"** ejecutándose (tarda ~5 minutos)
3. Cuando termine (✓ verde), click en el workflow
4. Abajo en **"Artifacts"**, click en **"ItsMe-APK"**
5. Se descargará un ZIP que contiene el archivo `app-debug.apk`

### Paso 5: Instalar en el celular
1. Pase el APK al celular (WhatsApp, cable, Drive)
2. En el celular: **Ajustes → Seguridad → Fuentes desconocidas** (activar)
3. Abra el APK con el administrador de archivos e instale

---

## Opción B: Compilar con Android Studio

1. Descargue [Android Studio](https://developer.android.com/studio)
2. Abra la carpeta `ItsMe` con *File → Open*
3. Espere que Gradle sincronice (~5 min la primera vez)
4. **Build → Build Bundle(s)/APK(s) → Build APK(s)**
5. Click en **"Locate"** para encontrar el APK

---

## Funcionalidades

| Módulo | Funciones |
|--------|-----------|
| 🔐 Login | Usuario + contraseña. Admin gestiona usuarios desde menú ⋮ |
| ✈️ Viajes | Crear viajes, activar uno, finalizarlo (pasa a historial) |
| 👥 Clientes | Crear/editar/eliminar. Botón directo a WhatsApp |
| 📦 Artículos | Foto (galería/cámara), costo/venta, ganancia automática |
| ❤️ Listas de Deseos | Listas por cliente y viaje, items con foto |
| 📋 Pedidos | Agregar artículos, suma automática, exportar PDF |

> **Nota:** Primero cree un Viaje y actívelo. Luego todos los módulos trabajarán sobre ese viaje.
