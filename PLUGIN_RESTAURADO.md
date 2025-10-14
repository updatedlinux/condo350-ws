# Condo360 WhatsApp Service - Plugin Restaurado

## ✅ **Plugin Restaurado y Mejorado**

He restaurado el plugin a la versión original que funcionaba y agregado solo las funciones que pediste:

### 🔧 **Funciones Agregadas**

1. **👥 Gestión de Grupos**:
   - Carga automática de grupos cuando WhatsApp está conectado
   - Visualización de grupos con nombre, ID y participantes
   - Selección de grupo destino

2. **💾 Persistencia en Base de Datos**:
   - Guarda el grupo seleccionado en `wp_condo360ws_config`
   - Campo `whatsapp_group_id` con el ID del grupo
   - Persistencia entre sesiones

### 📁 **Archivos del Plugin**

- ✅ `wordpress/condo360-whatsapp.php` - Archivo principal del plugin
- ✅ `wordpress/condo360-whatsapp-plugin.php` - Lógica del plugin (original + grupos)
- ✅ `wordpress/assets/style.css` - Estilos (original + grupos)
- ✅ `wordpress/assets/script.js` - JavaScript (original + grupos)

### 🚀 **Cómo Usar**

1. **Instalar Plugin**:
   - Sube la carpeta `wordpress/` a `/wp-content/plugins/condo360-whatsapp/`
   - Activa el plugin "Condo360 WhatsApp Service"

2. **Conectar WhatsApp**:
   - Usa el shortcode `[wa_connect_qr]` en cualquier página
   - Escanea el código QR con WhatsApp
   - Espera a que aparezca "WhatsApp Conectado"

3. **Seleccionar Grupo**:
   - Los grupos se cargan automáticamente cuando WhatsApp está conectado
   - Haz clic en el grupo que deseas usar
   - Haz clic en "Configurar como Grupo de Destino"
   - El grupo quedará guardado en la base de datos

### 🔌 **API Endpoints**

El plugin usa los mismos endpoints del API:

- `GET /api/status` - Estado de WhatsApp
- `GET /api/qr` - Código QR
- `GET /api/groups` - Lista de grupos
- `POST /api/send-message` - Enviar mensaje

### 💾 **Base de Datos**

El grupo seleccionado se guarda en:
- **Tabla**: `wp_condo360ws_config`
- **Campo**: `whatsapp_group_id`
- **Valor**: ID del grupo seleccionado

### 🎯 **Funcionalidades**

- ✅ **Conexión vía QR**: Compatible con WhatsApp
- ✅ **Carga automática de grupos**: Cuando WhatsApp está conectado
- ✅ **Selección visual**: Interfaz intuitiva para seleccionar grupos
- ✅ **Persistencia**: Guarda el grupo seleccionado automáticamente
- ✅ **Solo administradores**: Seguridad implementada
- ✅ **Auto-actualización**: Se actualiza automáticamente

### 🔧 **Diferencias con la Versión Anterior**

**Mantenido (funcionaba bien)**:
- ✅ Estructura original del plugin
- ✅ Shortcode `[wa_connect_qr]`
- ✅ Conexión vía QR
- ✅ Estados de conexión
- ✅ Auto-actualización

**Agregado (nuevas funciones)**:
- ✅ Carga automática de grupos
- ✅ Visualización de grupos disponibles
- ✅ Selección de grupo destino
- ✅ Guardado en base de datos

### 🆘 **Si Hay Problemas**

- **Plugin no funciona**: Verifica que esté activado y que seas administrador
- **Grupos no cargan**: Confirma que WhatsApp esté conectado
- **Error de base de datos**: Verifica que la tabla `wp_condo360ws_config` exista
- **QR no aparece**: Revisa que el servicio Node.js esté corriendo

---

**¡El plugin está restaurado y listo para usar!** 🎉

La funcionalidad de grupos está integrada de manera que no rompe la funcionalidad original que ya funcionaba.
