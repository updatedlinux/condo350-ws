#!/bin/bash

# Script de despliegue para producción - Condo360 WhatsApp Service
# Este script configura el servicio para producción con systemd

set -e

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuración
SERVICE_NAME="condo360-whatsapp"
SERVICE_USER="www-data"
SERVICE_GROUP="www-data"
INSTALL_DIR="/opt/condo360-whatsapp"
SERVICE_FILE="/etc/systemd/system/${SERVICE_NAME}.service"

print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_header() {
    echo -e "${BLUE}=== $1 ===${NC}"
}

# Verificar que se ejecute como root
if [ "$EUID" -ne 0 ]; then
    print_error "Este script debe ejecutarse como root (usar sudo)"
    exit 1
fi

print_header "Despliegue de Condo360 WhatsApp Service"

# Verificar Node.js
if ! command -v node &> /dev/null; then
    print_error "Node.js no está instalado. Instalando Node.js..."
    
    # Instalar Node.js desde NodeSource
    curl -fsSL https://deb.nodesource.com/setup_18.x | bash -
    apt-get install -y nodejs
    
    print_status "Node.js instalado ✓"
fi

NODE_VERSION=$(node -v)
print_status "Node.js $NODE_VERSION detectado ✓"

# Crear usuario del servicio si no existe
if ! id "$SERVICE_USER" &>/dev/null; then
    print_status "Creando usuario $SERVICE_USER..."
    useradd -r -s /bin/false -d "$INSTALL_DIR" "$SERVICE_USER"
fi

# Crear directorio de instalación
print_status "Creando directorio de instalación..."
mkdir -p "$INSTALL_DIR"
cd "$INSTALL_DIR"

# Copiar archivos del proyecto
print_status "Copiando archivos del proyecto..."
cp -r /Users/jmelendez/Documents/github_repos/condo360-2025/condo350-ws/* .

# Instalar dependencias
print_status "Instalando dependencias de producción..."
npm ci --only=production

# Crear directorios necesarios
print_status "Creando directorios necesarios..."
mkdir -p sessions logs
chown -R "$SERVICE_USER:$SERVICE_GROUP" sessions logs

# Configurar archivo .env para producción
if [ ! -f .env ]; then
    print_status "Creando configuración de producción..."
    cat > .env << EOF
# Configuración de producción
NODE_ENV=production
PORT=3003

# Configuración de WhatsApp
WHATSAPP_GROUP_ID=
WHATSAPP_SESSION_PATH=$INSTALL_DIR/sessions

# Configuración de base de datos WordPress
DB_HOST=localhost
DB_USER=wordpress_user
DB_PASSWORD=wordpress_password
DB_NAME=wordpress_db
DB_PORT=3306

# Configuración de seguridad
API_SECRET_KEY=condo360_whatsapp_secret_2025_prod
RATE_LIMIT_WINDOW_MS=900000
RATE_LIMIT_MAX_REQUESTS=100

# Configuración de logs
LOG_LEVEL=info
LOG_FILE=$INSTALL_DIR/logs/whatsapp-service.log

# Configuración de reconexión
RECONNECT_INTERVAL=30000
QR_REFRESH_INTERVAL=10000
EOF
    
    print_warning "Archivo .env creado. Por favor configura las variables de entorno."
fi

# Configurar permisos
print_status "Configurando permisos..."
chown -R "$SERVICE_USER:$SERVICE_GROUP" "$INSTALL_DIR"
chmod +x src/index.js

# Instalar servicio systemd
print_status "Instalando servicio systemd..."
cp condo360-whatsapp.service "$SERVICE_FILE"

# Recargar systemd
systemctl daemon-reload

# Habilitar servicio
systemctl enable "$SERVICE_NAME"

print_status "Servicio instalado y habilitado ✓"

# Configurar logrotate
print_status "Configurando rotación de logs..."
cat > /etc/logrotate.d/condo360-whatsapp << EOF
$INSTALL_DIR/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 $SERVICE_USER $SERVICE_GROUP
    postrotate
        systemctl reload $SERVICE_NAME > /dev/null 2>&1 || true
    endscript
}
EOF

# Configurar firewall (opcional)
if command -v ufw &> /dev/null; then
    print_status "Configurando firewall..."
    ufw allow 3003/tcp comment "Condo360 WhatsApp Service"
fi

# Crear script de gestión
print_status "Creando script de gestión..."
cat > /usr/local/bin/condo360-whatsapp << 'EOF'
#!/bin/bash

case "$1" in
    start)
        systemctl start condo360-whatsapp
        echo "Servicio iniciado"
        ;;
    stop)
        systemctl stop condo360-whatsapp
        echo "Servicio detenido"
        ;;
    restart)
        systemctl restart condo360-whatsapp
        echo "Servicio reiniciado"
        ;;
    status)
        systemctl status condo360-whatsapp
        ;;
    logs)
        journalctl -u condo360-whatsapp -f
        ;;
    logs-file)
        tail -f /opt/condo360-whatsapp/logs/whatsapp-service.log
        ;;
    *)
        echo "Uso: $0 {start|stop|restart|status|logs|logs-file}"
        exit 1
        ;;
esac
EOF

chmod +x /usr/local/bin/condo360-whatsapp

# Mostrar información de configuración
echo ""
print_header "Despliegue Completado"
echo ""
echo "✅ Servicio instalado en: $INSTALL_DIR"
echo "✅ Usuario del servicio: $SERVICE_USER"
echo "✅ Puerto: 3003"
echo "✅ URL: https://wschat.bonaventurecclub.com"
echo ""
echo "🔧 Comandos de gestión:"
echo "  sudo condo360-whatsapp start     - Iniciar servicio"
echo "  sudo condo360-whatsapp stop      - Detener servicio"
echo "  sudo condo360-whatsapp restart   - Reiniciar servicio"
echo "  sudo condo360-whatsapp status    - Ver estado"
echo "  sudo condo360-whatsapp logs      - Ver logs en tiempo real"
echo "  sudo condo360-whatsapp logs-file - Ver archivo de logs"
echo ""
echo "📋 Próximos pasos:"
echo "1. Configura las variables de entorno en $INSTALL_DIR/.env"
echo "2. Instala el plugin de WordPress"
echo "3. Configura Nginx Proxy Manager"
echo "4. Ejecuta 'sudo condo360-whatsapp start' para iniciar"
echo ""
echo "📖 Para más información, consulta README.md"
echo ""
print_status "¡Despliegue exitoso! 🎉"
