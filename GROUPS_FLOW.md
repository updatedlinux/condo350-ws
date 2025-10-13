# Flujo de Funcionalidad - Lista de Grupos WhatsApp

## Flujo Completo del Usuario

```
1. Usuario accede a página con shortcode [wa_connect_qr]
   ↓
2. Shortcode muestra estado "Verificando..."
   ↓
3. Si WhatsApp NO está conectado:
   - Muestra código QR
   - Usuario escanea QR con WhatsApp
   ↓
4. Si WhatsApp SÍ está conectado:
   - Muestra "WhatsApp Conectado" ✓
   - Muestra botón "Cargar Grupos"
   ↓
5. Usuario hace clic en "Cargar Grupos"
   ↓
6. Sistema obtiene grupos desde WhatsApp API
   ↓
7. Muestra lista de grupos disponibles:
   - Nombre del grupo
   - Número de participantes
   - ID del grupo (formato: 120363123456789012@g.us)
   ↓
8. Usuario selecciona un grupo (click)
   ↓
9. Sistema muestra información del grupo seleccionado
   ↓
10. Usuario hace clic en "Configurar como Grupo de Destino"
    ↓
11. Sistema guarda el grupo en base de datos
    ↓
12. Grupo configurado para recibir mensajes automáticamente
```

## Endpoints API Utilizados

### GET /api/groups
- **Propósito**: Obtener todos los grupos de WhatsApp
- **Autenticación**: Requiere WhatsApp conectado
- **Respuesta**: Lista de grupos con metadata

### POST /api/set-group
- **Propósito**: Configurar grupo de destino
- **Parámetros**: groupId, secretKey
- **Respuesta**: Confirmación de configuración

## Estructura de Datos de Grupo

```json
{
  "id": "120363123456789012@g.us",
  "subject": "Grupo de Trabajo Condo360",
  "participants": 15,
  "creation": 1640995200,
  "description": "Grupo para coordinación de trabajo",
  "isGroup": true
}
```

## Interfaz de Usuario

### Estados del Shortcode

1. **Verificando**: Estado inicial, verificando conexión
2. **QR Disponible**: Mostrando código QR para escanear
3. **Conectado**: WhatsApp conectado, mostrando opciones de grupos
4. **Cargando Grupos**: Obteniendo lista de grupos
5. **Grupos Disponibles**: Lista de grupos mostrada
6. **Grupo Seleccionado**: Grupo específico seleccionado
7. **Grupo Configurado**: Grupo configurado como destino

### Elementos de la Interfaz

- **Botón "Cargar Grupos"**: Inicia la obtención de grupos
- **Lista de Grupos**: Muestra todos los grupos disponibles
- **Información del Grupo**: Nombre, participantes, ID
- **Botón "Configurar"**: Establece el grupo como destino
- **Indicadores Visuales**: Estados de carga, selección, etc.

## Seguridad

- Solo administradores pueden ver y usar el shortcode
- Validación de nonce en todas las peticiones AJAX
- Verificación de permisos en cada endpoint
- Clave secreta requerida para configurar grupos

## Manejo de Errores

- **WhatsApp no conectado**: Muestra QR para conectar
- **Error obteniendo grupos**: Mensaje de error específico
- **Sin grupos disponibles**: Mensaje informativo
- **Error configurando grupo**: Alerta con detalles del error

## Responsive Design

- Lista de grupos adaptada para móviles
- Botones optimizados para touch
- Texto legible en pantallas pequeñas
- Scroll vertical para listas largas
