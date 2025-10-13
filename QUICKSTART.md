# 🚀 Instrucciones Rápidas - Condo360 WhatsApp Service

## Instalación Rápida

### 1. Instalar dependencias
```bash
./install.sh
```

### 2. Configurar variables de entorno
Edita el archivo `.env` con tus credenciales de WordPress:
```env
DB_HOST=tu_host_mysql
DB_USER=tu_usuario_wordpress
DB_PASSWORD=tu_password_wordpress
DB_NAME=tu_base_datos_wordpress
API_SECRET_KEY=tu_clave_secreta_unica
```

### 3. Instalar plugin de WordPress
1. Copia la carpeta `wordpress/` a `wp-content/plugins/condo360-whatsapp/`
2. Activa el plugin desde el panel de administración
3. Ve a `Configuración > WhatsApp Service` y configura la URL del API

### 4. Configurar Nginx Proxy Manager
1. Crea un nuevo proxy host
2. Dominio: `wschat.bonaventurecclub.com`
3. Destino: `http://localhost:3003`
4. Habilita SSL

### 5. Iniciar el servicio
```bash
./start.sh
```

## Uso del Shortcode

Agrega en cualquier página de WordPress:
```php
[wa_connect_qr]
```

## Conectar WhatsApp

1. El shortcode mostrará el código QR
2. Abre WhatsApp en tu teléfono
3. Ve a `Configuración > Dispositivos vinculados`
4. Escanea el código QR

## Seleccionar Grupo de Destino

Una vez conectado WhatsApp:

1. Haz clic en **"Cargar Grupos"** para ver todos los grupos disponibles
2. Selecciona el grupo al que quieres enviar mensajes
3. Haz clic en **"Configurar como Grupo de Destino"**
4. El grupo quedará configurado para recibir mensajes automáticamente

## Enviar Mensajes

Una vez conectado, puedes enviar mensajes usando el API:

```bash
curl -X POST https://wschat.bonaventurecclub.com/api/send-message \
  -H "Content-Type: application/json" \
  -d '{
    "message": "Hola desde Condo360!",
    "secretKey": "tu_clave_secreta"
  }'
```

## Comandos Útiles

```bash
# Desarrollo
./dev.sh

# Producción
./start.sh

# Ver logs
tail -f logs/whatsapp-service.log

# Estado del servicio
curl https://wschat.bonaventurecclub.com/health
```

## Solución de Problemas

### QR no aparece
- Verifica que el servicio esté ejecutándose
- Revisa los logs: `tail -f logs/whatsapp-service.log`

### Error de base de datos
- Verifica las credenciales en `.env`
- Asegúrate de que MySQL esté ejecutándose

### Shortcode no funciona
- Verifica que eres administrador de WordPress
- Revisa la consola del navegador para errores

### Mensajes no se envían
- Verifica que WhatsApp esté conectado
- Confirma que el grupo ID esté configurado

## Obtener ID de Grupo

1. Agrega el bot a un grupo
2. Envía cualquier mensaje
3. Revisa los logs del servicio
4. Busca el ID del grupo (formato: `120363123456789012@g.us`)

## Configurar Grupo de Destino

```bash
curl -X POST https://wschat.bonaventurecclub.com/api/set-group \
  -H "Content-Type: application/json" \
  -d '{
    "groupId": "120363123456789012@g.us",
    "secretKey": "tu_clave_secreta"
  }'
```

## Despliegue en Producción

```bash
sudo ./deploy.sh
```

Esto instalará el servicio como un demonio de systemd.

## Soporte

Para más información, consulta el `README.md` completo.

---
**¡Listo para usar!** 🎉
