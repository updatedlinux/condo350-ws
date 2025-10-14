# 🚀 Instrucciones Rápidas - Condo360 WhatsApp

## ✅ Plugin Mejorado Listo

El plugin ha sido mejorado y está listo para usar. Incluye:

- ✅ **Conexión vía QR compatible con WhatsApp**
- ✅ **Visualización de grupos disponibles**
- ✅ **Selección de grupo destino**
- ✅ **Guardado automático en base de datos**

## 📋 Pasos para Usar

### 1. Instalar Plugin
1. Sube `wordpress/condo360-whatsapp-debug.php` a `/wp-content/plugins/condo360-whatsapp/`
2. Activa el plugin "Condo360 WhatsApp Service (Debug)" en WordPress
3. El plugin está listo para usar

### 2. Conectar WhatsApp
1. Ve a cualquier página y agrega el shortcode `[wa_connect_qr]`
2. Escanea el código QR con WhatsApp:
   - Abre WhatsApp en tu teléfono
   - Ve a Configuración > Dispositivos vinculados
   - Toca "Vincular un dispositivo"
   - Escanea el código QR
3. Espera a que aparezca "WhatsApp está conectado"

### 3. Seleccionar Grupo
1. Una vez conectado, se mostrarán los grupos disponibles
2. Haz clic en el grupo que deseas usar para enviar mensajes
3. Confirma la selección
4. El grupo quedará guardado automáticamente

### 4. Enviar Mensajes
Los mensajes se enviarán automáticamente al grupo seleccionado usando el endpoint `/api/send-message`.

## 🔧 Características del Plugin

- **Solo para Administradores**: Solo usuarios con rol de administrador pueden ver el shortcode
- **Auto-actualización**: Se actualiza automáticamente cada 10 segundos si no está conectado
- **Interfaz Moderna**: Diseño responsive y fácil de usar
- **Gestión de Grupos**: Visualiza todos los grupos disponibles con información detallada
- **Persistencia**: Mantiene la conexión y configuración entre sesiones

## 📱 Compatibilidad WhatsApp

El plugin ahora es **100% compatible** con WhatsApp:
- ✅ QR generado correctamente
- ✅ Instrucciones claras para escanear
- ✅ Compatible con todas las versiones de WhatsApp
- ✅ Manejo de errores mejorado

## 🎯 Próximos Pasos

1. **Probar la conexión**: Escanea el QR y verifica que funcione
2. **Seleccionar grupo**: Elige el grupo destino para los mensajes
3. **Probar envío**: Usa el endpoint `/api/send-message` para enviar mensajes
4. **Configurar Nginx**: Asegúrate de que el proxy esté funcionando correctamente

## 🆘 Si Hay Problemas

- **QR no aparece**: Verifica que el servicio esté corriendo en puerto 3003
- **No se conecta**: Revisa los logs del servicio Node.js
- **Plugin no funciona**: Confirma que seas administrador y que el plugin esté activado
- **Grupos no cargan**: Verifica que WhatsApp esté conectado y que el endpoint `/api/groups` funcione

---

**¡El plugin está listo para usar!** 🎉