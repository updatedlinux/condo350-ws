# Instalación del Plugin Condo360 WhatsApp Service

## Estructura del Plugin

El plugin debe tener la siguiente estructura en WordPress:

```
/wp-content/plugins/condo360-whatsapp/
├── condo360-whatsapp.php          # Archivo principal del plugin
├── readme.txt                     # Información del plugin
└── assets/
    ├── style.css                  # Estilos CSS
    └── script.js                  # JavaScript
```

## Pasos de Instalación

### 1. Subir Archivos al Servidor

Copia todos los archivos del directorio `wordpress/` a:
```
/wp-content/plugins/condo360-whatsapp/
```

**Archivos necesarios:**
- `condo360-whatsapp.php` (archivo principal)
- `readme.txt`
- `assets/style.css`
- `assets/script.js`

### 2. Activar el Plugin

1. Ve al panel de administración de WordPress
2. Navega a **Plugins > Plugins Instalados**
3. Busca "Condo360 WhatsApp Service"
4. Haz clic en **Activar**

### 3. Configurar el Plugin

1. Ve a **Configuración > WhatsApp Service**
2. Configura la URL del API: `https://wschat.bonaventurecclub.com`
3. Configura la clave secreta: `condo360_whatsapp_secret_2025`
4. Guarda la configuración

### 4. Usar el Shortcode

Agrega el shortcode `[wa_connect_qr]` en cualquier página o entrada:

```php
[wa_connect_qr]
```

O con parámetros personalizados:

```php
[wa_connect_qr show_status="true" auto_refresh="true" refresh_interval="10000"]
```

## Verificación de Instalación

### 1. Verificar que el Plugin Aparece

- Ve a **Plugins > Plugins Instalados**
- Deberías ver "Condo360 WhatsApp Service" en la lista
- El estado debe ser "Activo"

### 2. Verificar el Menú de Configuración

- Ve a **Configuración > WhatsApp Service**
- Deberías ver la página de configuración del plugin

### 3. Probar el Shortcode

- Crea una nueva página o entrada
- Agrega el shortcode `[wa_connect_qr]`
- Publica la página
- Ve la página como administrador
- Deberías ver la interfaz de WhatsApp

## Solución de Problemas

### El Plugin No Aparece en la Lista

**Causa:** Estructura de archivos incorrecta o archivo principal mal nombrado.

**Solución:**
1. Verifica que el archivo principal se llame `condo360-whatsapp.php`
2. Verifica que esté en `/wp-content/plugins/condo360-whatsapp/`
3. Verifica que tenga el header correcto de WordPress

### Error al Activar el Plugin

**Causa:** Error de sintaxis PHP o dependencias faltantes.

**Solución:**
1. Revisa los logs de error de WordPress
2. Verifica que PHP sea versión 7.4 o superior
3. Verifica que WordPress sea versión 5.0 o superior

### El Shortcode No Funciona

**Causa:** Permisos insuficientes o JavaScript no cargado.

**Solución:**
1. Verifica que seas administrador
2. Verifica que los archivos CSS y JS se carguen correctamente
3. Revisa la consola del navegador para errores JavaScript

## Configuración Avanzada

### Personalizar la URL del API

Si el API está en una URL diferente, cambia la configuración en:
**Configuración > WhatsApp Service > URL del API**

### Personalizar la Clave Secreta

Para mayor seguridad, cambia la clave secreta en:
**Configuración > WhatsApp Service > Clave Secreta del API**

### Personalizar el Intervalo de Actualización

Puedes cambiar el intervalo de actualización usando parámetros del shortcode:

```php
[wa_connect_qr refresh_interval="5000"]  <!-- 5 segundos -->
[wa_connect_qr refresh_interval="30000"] <!-- 30 segundos -->
```

## Soporte

Si tienes problemas con la instalación o configuración:

1. Revisa los logs de error de WordPress
2. Verifica que el servicio API esté funcionando
3. Verifica los permisos de archivos en el servidor
4. Contacta al administrador del sistema
