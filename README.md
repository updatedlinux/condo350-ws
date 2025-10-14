# Condo360 WhatsApp Service

Servicio completo de WhatsApp para WordPress que permite conectar WhatsApp vía QR y enviar mensajes a grupos específicos.

## 🚀 Características

- ✅ **Conexión vía QR**: Escanea el código QR con WhatsApp para conectar
- ✅ **Gestión de Grupos**: Visualiza y selecciona grupos de WhatsApp
- ✅ **Persistencia de Sesión**: Mantiene la conexión activa
- ✅ **API REST**: Endpoints para integrar con WordPress
- ✅ **Plugin WordPress**: Shortcode `[wa_connect_qr]` para administradores
- ✅ **Interfaz Intuitiva**: Diseño moderno y responsive

## 📋 Requisitos

- Node.js 16+
- PHP 7.4+
- WordPress 5.0+
- MySQL/MariaDB
- Chromium (para whatsapp-web.js)

## 🛠️ Instalación

### 1. Backend Node.js

```bash
# Clonar el repositorio
git clone <repository-url>
cd condo350-ws

# Instalar dependencias
npm install

# Configurar variables de entorno
cp env.example .env
# Editar .env con tus configuraciones

# Iniciar el servicio
npm start
```

### 2. Plugin WordPress

1. Sube la carpeta `wordpress/` a `/wp-content/plugins/condo360-whatsapp/`
2. Activa el plugin "Condo360 WhatsApp Service" en WordPress
3. Usa el shortcode `[wa_connect_qr]` en cualquier página

## ⚙️ Configuración

### Variables de Entorno (.env)

```env
# Puerto del servidor
PORT=3003

# Base de datos WordPress
DB_HOST=localhost
DB_USER=wordpress_user
DB_PASSWORD=tu_password
DB_NAME=wordpress_db

# WhatsApp
WHATSAPP_GROUP_ID=

# API
API_SECRET=condo360_whatsapp_secret_2025
```

### Nginx Proxy Manager

Configura el proxy para `wschat.bonaventurecclub.com` apuntando al puerto 3003.

## 📱 Uso

### 1. Conectar WhatsApp

1. Ve a la página con el shortcode `[wa_connect_qr]`
2. Escanea el código QR con WhatsApp
3. Espera a que aparezca "WhatsApp está conectado"

### 2. Seleccionar Grupo

1. Una vez conectado, se mostrarán los grupos disponibles
2. Haz clic en el grupo que deseas usar
3. Confirma la selección
4. El grupo quedará guardado en la base de datos

### 3. Enviar Mensajes

Usa el endpoint `/api/send-message` para enviar mensajes:

```bash
curl -X POST https://wschat.bonaventurecclub.com/api/send-message \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer condo360_whatsapp_secret_2025" \
  -d '{
    "message": "Hola desde Condo360!",
    "groupId": "grupo_id_aqui"
  }'
```

## 🔌 API Endpoints

### GET /api/status
Obtiene el estado de la conexión de WhatsApp.

**Respuesta:**
```json
{
  "success": true,
  "data": {
    "connected": true,
    "qrGenerated": false,
    "groupId": "grupo_seleccionado"
  }
}
```

### GET /api/qr
Obtiene el código QR para conectar WhatsApp.

**Respuesta:**
```json
{
  "success": true,
  "connected": false,
  "qr": "base64_image_data",
  "expiresAt": "2025-10-13T23:32:16.417Z"
}
```

### GET /api/groups
Obtiene la lista de grupos de WhatsApp.

**Respuesta:**
```json
{
  "success": true,
  "data": {
    "groups": [
      {
        "id": "grupo_id",
        "subject": "Nombre del Grupo",
        "participants": 5,
        "creation": "2025-01-01T00:00:00.000Z",
        "description": "Descripción del grupo"
      }
    ]
  }
}
```

### POST /api/send-message
Envía un mensaje a un grupo específico.

**Body:**
```json
{
  "message": "Mensaje a enviar",
  "groupId": "grupo_id_opcional"
}
```

**Respuesta:**
```json
{
  "success": true,
  "message": "Mensaje enviado correctamente",
  "groupId": "grupo_id"
}
```

## 🗄️ Base de Datos

El plugin crea la tabla `wp_condo360ws_config` para almacenar:

- `whatsapp_group_id`: ID del grupo seleccionado
- `api_url`: URL del API
- `api_secret`: Clave secreta del API

## 🔧 Solución de Problemas

### Error de Conexión
- Verifica que el puerto 3003 esté disponible
- Revisa los logs del servicio
- Confirma que Chromium esté instalado

### QR No Aparece
- Limpia las sesiones: `rm -rf sessions/*`
- Reinicia el servicio
- Verifica que no haya procesos de Chrome bloqueados

### Plugin No Funciona
- Verifica que el plugin esté activado
- Confirma que el usuario sea administrador
- Revisa la consola del navegador para errores

## 📝 Logs

Los logs se guardan en:
- Backend: Consola del terminal
- WordPress: Logs de PHP y JavaScript

## 🔒 Seguridad

- Solo administradores pueden usar el shortcode
- Autenticación requerida para endpoints
- Validación de entrada en todos los endpoints
- Rate limiting implementado

## 📞 Soporte

Para soporte técnico, contacta a Condo360.

---

**Versión:** 1.0.0  
**Autor:** Condo360  
**Licencia:** MIT