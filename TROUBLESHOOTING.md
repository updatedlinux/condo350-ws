# 🔧 Solución de Problemas - Error de Conexión WhatsApp

## Problema Identificado

El error que estás viendo:
```
[object Object] "connected to WA"
[object Object] "error in validating connection"
[object Object] "connection errored"
```

Indica que WhatsApp está intentando conectarse pero falla en la validación de la conexión. Esto es común cuando:

1. **Sesión corrupta**: Las credenciales guardadas están dañadas
2. **Conexión interrumpida**: La sesión anterior no se cerró correctamente
3. **Problemas de red**: Timeout o problemas de conectividad

## Solución Paso a Paso

### 1. Crear las Tablas de Base de Datos

Primero ejecuta el script SQL para crear las tablas necesarias:

```bash
# Conectar a MySQL
mysql -u root -p

# Seleccionar la base de datos de WordPress
USE tu_base_datos_wordpress;

# Ejecutar el script SQL
source /ruta/completa/a/condo350-ws/database.sql;
```

O ejecuta directamente:
```bash
mysql -u root -p tu_base_datos_wordpress < database.sql
```

### 2. Limpiar Sesiones y Reiniciar

Ejecuta el script de limpieza:

```bash
./reset-session.sh
```

Este script:
- Detiene el servicio actual
- Limpia todas las sesiones de WhatsApp
- Reinicia el servicio con configuración limpia

### 3. Verificar Configuración

Asegúrate de que tu archivo `.env` tenga la configuración correcta:

```env
# Puerto del servidor
PORT=3003

# Configuración de WhatsApp
WHATSAPP_GROUP_ID=
WHATSAPP_SESSION_PATH=./sessions

# Configuración de base de datos WordPress
DB_HOST=localhost
DB_USER=tu_usuario_wordpress
DB_PASSWORD=tu_password_wordpress
DB_NAME=tu_base_datos_wordpress
DB_PORT=3306

# Configuración de seguridad
API_SECRET_KEY=condo360_whatsapp_secret_2025
```

### 4. Verificar Logs

Después de reiniciar, monitorea los logs:

```bash
tail -f logs/whatsapp-service.log
```

Deberías ver:
```
[INFO] Inicializando servicio de WhatsApp...
[INFO] Usando versión de Baileys: 2.3000.1027934701, última: true
[INFO] 🚀 Servidor Condo360 WhatsApp iniciado en puerto 3003
```

### 5. Probar Conexión

1. Ve a WordPress y usa el shortcode `[wa_connect_qr]`
2. Deberías ver el código QR para escanear
3. Escanea el QR con WhatsApp
4. Una vez conectado, podrás cargar grupos

## Comandos Útiles

### Detener el servicio
```bash
pkill -f "node src/index.js"
```

### Ver procesos ejecutándose
```bash
ps aux | grep node
```

### Verificar puerto 3003
```bash
netstat -tlnp | grep 3003
```

### Limpiar solo sesiones (sin reiniciar)
```bash
rm -rf sessions/*
```

## Verificación de Base de Datos

Verifica que las tablas se crearon correctamente:

```sql
SHOW TABLES LIKE 'condo360ws_%';
```

Deberías ver:
- `condo360ws_config`
- `condo360ws_messages`
- `condo360ws_connections`

## Si el Problema Persiste

### 1. Verificar Dependencias
```bash
npm install
```

### 2. Verificar Node.js
```bash
node --version  # Debe ser 16+
```

### 3. Verificar Permisos
```bash
chmod 755 sessions/
chmod 755 logs/
```

### 4. Verificar Conectividad
```bash
curl http://localhost:3003/health
```

### 5. Reinstalar Baileys
```bash
npm uninstall @whiskeysockets/baileys
npm install @whiskeysockets/baileys@latest
```

## Configuración de Nginx Proxy Manager

Asegúrate de que NPM esté configurado correctamente:

1. **Proxy Host**: `wschat.bonaventurecclub.com`
2. **Forward Host**: `127.0.0.1`
3. **Forward Port**: `3003`
4. **SSL**: Habilitado
5. **WebSocket Support**: Habilitado

## Próximos Pasos

Una vez que el servicio esté funcionando:

1. Escanea el QR desde WordPress
2. Haz clic en "Cargar Grupos"
3. Selecciona el grupo de destino
4. Configura el grupo para recibir mensajes

## Soporte Adicional

Si sigues teniendo problemas:

1. Revisa los logs completos
2. Verifica la configuración de red
3. Asegúrate de que no haya otros servicios usando el puerto 3003
4. Considera usar un puerto diferente si hay conflictos

---

**Nota**: El error de conexión es temporal y se resuelve limpiando las sesiones. Una vez que escanees un nuevo QR, la conexión debería establecerse correctamente.
