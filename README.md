# Condo360 WhatsApp Service

Un servicio completo de Node.js con Baileys para conectar WhatsApp y enviar mensajes a grupos específicos, integrado con WordPress mediante shortcodes.

## 📋 Características

- ✅ Conexión a WhatsApp mediante código QR
- ✅ Persistencia de sesión para evitar reconexiones frecuentes
- ✅ API REST para obtener QR y enviar mensajes
- ✅ Shortcode de WordPress para mostrar estado y QR
- ✅ Actualización automática del QR cada 10 segundos
- ✅ Reconexión automática en caso de desconexión
- ✅ Integración con base de datos de WordPress
- ✅ Logging completo de mensajes y conexiones
- ✅ Seguridad básica con tokens de API
- ✅ Interfaz responsive y moderna

## 🏗️ Arquitectura

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   WordPress     │    │   Node.js API   │    │   WhatsApp      │
│   Shortcode     │◄──►│   (Puerto 3003) │◄──►│   (Baileys)     │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │
         │                       │
         ▼                       ▼
┌─────────────────┐    ┌─────────────────┐
│   WordPress DB  │    │   Sesiones      │
│   condo360ws_*  │    │   (JSON files)  │
└─────────────────┘    └─────────────────┘
```

## 🚀 Instalación

### Prerrequisitos

- Node.js 16+ 
- MySQL/MariaDB (base de datos de WordPress)
- WordPress 5.0+
- Nginx Proxy Manager (para SSL)

### 1. Clonar e instalar dependencias

```bash
git clone <repository-url>
cd condo350-ws
npm install
```

### 2. Configurar variables de entorno

Copia el archivo de ejemplo y configura las variables:

```bash
cp env.example .env
```

Edita el archivo `.env`:

```env
# Puerto del servidor
PORT=3003

# Configuración de WhatsApp
WHATSAPP_GROUP_ID=
WHATSAPP_SESSION_PATH=./sessions

# Configuración de base de datos WordPress
DB_HOST=localhost
DB_USER=wordpress_user
DB_PASSWORD=wordpress_password
DB_NAME=wordpress_db
DB_PORT=3306

# Configuración de seguridad
API_SECRET_KEY=condo360_whatsapp_secret_2025
RATE_LIMIT_WINDOW_MS=900000
RATE_LIMIT_MAX_REQUESTS=100

# Configuración de logs
LOG_LEVEL=info
LOG_FILE=./logs/whatsapp-service.log

# Configuración de reconexión
RECONNECT_INTERVAL=30000
QR_REFRESH_INTERVAL=10000
```

### 3. Crear directorios necesarios

```bash
mkdir -p sessions logs
```

### 4. Instalar plugin de WordPress

1. Copia la carpeta `wordpress/` a `wp-content/plugins/condo360-whatsapp/`
2. Activa el plugin desde el panel de administración de WordPress
3. Ve a `Configuración > WhatsApp Service` y configura la URL del API

### 5. Configurar Nginx Proxy Manager

1. Crea un nuevo proxy host en NPM
2. Configura el dominio: `wschat.bonaventurecclub.com`
3. Configura el destino: `http://localhost:3003`
4. Habilita SSL y fuerza HTTPS

## 🎯 Uso

### Iniciar el servicio

```bash
# Desarrollo
npm run dev

# Producción
npm start
```

### Usar el shortcode en WordPress

Agrega el shortcode en cualquier página o entrada:

```php
[wa_connect_qr]
```

Parámetros disponibles:

- `show_status="true"` - Mostrar estado de conexión
- `auto_refresh="true"` - Actualizar automáticamente
- `refresh_interval="10000"` - Intervalo en milisegundos

Ejemplo completo:

```php
[wa_connect_qr show_status="true" auto_refresh="true" refresh_interval="10000"]
```

### Conectar WhatsApp

1. El shortcode mostrará automáticamente el código QR
2. Abre WhatsApp en tu teléfono
3. Ve a `Configuración > Dispositivos vinculados`
4. Toca `Vincular un dispositivo`
5. Escanea el código QR mostrado

### Enviar mensajes

Una vez conectado, puedes enviar mensajes usando el API:

```bash
curl -X POST https://wschat.bonaventurecclub.com/api/send-message \
  -H "Content-Type: application/json" \
  -d '{
    "message": "Hola desde Condo360!",
    "secretKey": "condo360_whatsapp_secret_2025"
  }'
```

## 📡 API Endpoints

### GET /health
Verifica el estado del servicio.

**Respuesta:**
```json
{
  "status": "ok",
  "timestamp": "2025-01-27T10:30:00.000Z",
  "uptime": 3600,
  "whatsapp": {
    "connected": true,
    "qrGenerated": false
  }
}
```

### GET /api/status
Obtiene el estado de conexión de WhatsApp.

**Respuesta:**
```json
{
  "success": true,
  "data": {
    "connected": true,
    "qrGenerated": false,
    "lastConnection": "2025-01-27T10:30:00.000Z",
    "groupId": "120363123456789012@g.us"
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
  "qr": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA...",
  "expiresAt": "2025-01-27T10:31:00.000Z"
}
```

### POST /api/send-message
Envía un mensaje al grupo configurado.

**Parámetros:**
```json
{
  "message": "Texto del mensaje",
  "secretKey": "condo360_whatsapp_secret_2025"
}
```

**Respuesta:**
```json
{
  "success": true,
  "messageId": "3EB0C767D26A8A6C",
  "error": null
}
```

### POST /api/set-group
Configura el ID del grupo de destino.

**Parámetros:**
```json
{
  "groupId": "120363123456789012@g.us",
  "secretKey": "condo360_whatsapp_secret_2025"
}
```

## 🗄️ Base de Datos

El plugin crea las siguientes tablas en la base de datos de WordPress:

### condo360ws_config
Almacena la configuración del servicio.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | INT | ID único |
| config_key | VARCHAR(100) | Clave de configuración |
| config_value | TEXT | Valor de configuración |
| created_at | TIMESTAMP | Fecha de creación |
| updated_at | TIMESTAMP | Fecha de actualización |

### condo360ws_messages
Registra todos los mensajes enviados.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | INT | ID único |
| group_id | VARCHAR(100) | ID del grupo |
| message | TEXT | Contenido del mensaje |
| status | ENUM | Estado: sent, failed, pending |
| message_id | VARCHAR(100) | ID del mensaje en WhatsApp |
| error_message | TEXT | Mensaje de error si falla |
| created_at | TIMESTAMP | Fecha de envío |

### condo360ws_connections
Registra eventos de conexión.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | INT | ID único |
| status | ENUM | Estado: connected, disconnected, qr_generated, error |
| qr_code | TEXT | Código QR generado |
| error_message | TEXT | Mensaje de error |
| user_info | JSON | Información del usuario |
| created_at | TIMESTAMP | Fecha del evento |

## 🔧 Configuración Avanzada

### Variables de Entorno

| Variable | Descripción | Valor por defecto |
|----------|-------------|-------------------|
| `PORT` | Puerto del servidor | 3003 |
| `WHATSAPP_GROUP_ID` | ID del grupo de destino | - |
| `WHATSAPP_SESSION_PATH` | Ruta de sesiones | ./sessions |
| `DB_HOST` | Host de la base de datos | localhost |
| `DB_USER` | Usuario de la base de datos | wordpress_user |
| `DB_PASSWORD` | Contraseña de la base de datos | wordpress_password |
| `DB_NAME` | Nombre de la base de datos | wordpress_db |
| `DB_PORT` | Puerto de la base de datos | 3306 |
| `API_SECRET_KEY` | Clave secreta del API | condo360_whatsapp_secret_2025 |
| `RATE_LIMIT_WINDOW_MS` | Ventana de rate limiting | 900000 |
| `RATE_LIMIT_MAX_REQUESTS` | Máximo de requests | 100 |
| `LOG_LEVEL` | Nivel de logging | info |
| `LOG_FILE` | Archivo de logs | ./logs/whatsapp-service.log |
| `RECONNECT_INTERVAL` | Intervalo de reconexión | 30000 |
| `QR_REFRESH_INTERVAL` | Intervalo de refresco QR | 10000 |

### Obtener ID de Grupo

Para obtener el ID de un grupo de WhatsApp:

1. Agrega el bot a un grupo
2. Envía cualquier mensaje al grupo
3. Revisa los logs del servicio
4. Busca el ID del grupo en los logs

El formato del ID es: `120363123456789012@g.us`

## 🛠️ Desarrollo

### Estructura del Proyecto

```
condo350-ws/
├── src/
│   ├── index.js                 # Servidor principal
│   ├── services/
│   │   ├── whatsappService.js   # Servicio de WhatsApp
│   │   └── databaseService.js  # Servicio de base de datos
│   └── utils/
│       └── logger.js            # Sistema de logging
├── wordpress/
│   ├── condo360-whatsapp-plugin.php  # Plugin principal
│   └── assets/
│       ├── style.css            # Estilos del shortcode
│       └── script.js           # JavaScript del shortcode
├── sessions/                    # Sesiones de WhatsApp
├── logs/                       # Archivos de log
├── package.json
├── env.example
└── README.md
```

### Scripts Disponibles

```bash
# Desarrollo con nodemon
npm run dev

# Producción
npm start

# Ver logs en tiempo real
tail -f logs/whatsapp-service.log
```

### Debugging

Para habilitar logs detallados:

```env
LOG_LEVEL=debug
```

## 🔒 Seguridad

### Recomendaciones

1. **Cambiar la clave secreta**: Modifica `API_SECRET_KEY` por una clave única y segura
2. **Rate limiting**: El servicio incluye rate limiting por defecto
3. **HTTPS**: Usa siempre HTTPS en producción
4. **Firewall**: Restringe el acceso al puerto 3003 solo desde Nginx
5. **Logs**: Revisa regularmente los logs para detectar actividad sospechosa

### Permisos de WordPress

- Solo usuarios administradores pueden ver el shortcode
- Todas las peticiones AJAX requieren nonce válido
- Validación de permisos en cada endpoint

## 🐛 Solución de Problemas

### Problemas Comunes

#### QR no se genera
- Verifica que el servicio esté ejecutándose
- Revisa los logs para errores
- Asegúrate de que no haya otra sesión activa

#### Mensajes no se envían
- Verifica que WhatsApp esté conectado
- Confirma que el grupo ID esté configurado
- Revisa que la clave secreta sea correcta

#### Error de conexión a base de datos
- Verifica las credenciales en `.env`
- Asegúrate de que MySQL esté ejecutándose
- Confirma que la base de datos existe

#### Shortcode no aparece
- Verifica que el plugin esté activado
- Confirma que eres administrador
- Revisa la consola del navegador para errores

### Logs

Los logs se guardan en `logs/whatsapp-service.log`. Para monitorear en tiempo real:

```bash
tail -f logs/whatsapp-service.log
```

### Reiniciar Sesión

Si necesitas reiniciar la sesión de WhatsApp:

1. Detén el servicio
2. Elimina la carpeta `sessions/`
3. Reinicia el servicio
4. Escanea el nuevo QR

## 📞 Soporte

Para soporte técnico o reportar bugs:

1. Revisa los logs del servicio
2. Verifica la configuración
3. Consulta este README
4. Contacta al equipo de desarrollo

## 📄 Licencia

Este proyecto está bajo la licencia MIT. Ver el archivo LICENSE para más detalles.

## 🤝 Contribuciones

Las contribuciones son bienvenidas. Por favor:

1. Fork el proyecto
2. Crea una rama para tu feature
3. Commit tus cambios
4. Push a la rama
5. Abre un Pull Request

---

**Desarrollado para Condo360** 🏢
