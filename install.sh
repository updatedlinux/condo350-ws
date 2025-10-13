#!/bin/bash

# Script de instalación para Condo360 WhatsApp Service
# Este script automatiza la instalación y configuración inicial

set -e

echo "🚀 Instalando Condo360 WhatsApp Service..."

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Función para imprimir mensajes
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Verificar Node.js
if ! command -v node &> /dev/null; then
    print_error "Node.js no está instalado. Por favor instala Node.js 16+ primero."
    exit 1
fi

NODE_VERSION=$(node -v | cut -d'v' -f2 | cut -d'.' -f1)
if [ "$NODE_VERSION" -lt 16 ]; then
    print_error "Node.js versión 16+ requerida. Versión actual: $(node -v)"
    exit 1
fi

print_status "Node.js $(node -v) detectado ✓"

# Verificar npm
if ! command -v npm &> /dev/null; then
    print_error "npm no está instalado."
    exit 1
fi

print_status "npm $(npm -v) detectado ✓"

# Instalar dependencias
print_status "Instalando dependencias de Node.js..."
npm install

# Crear directorios necesarios
print_status "Creando directorios necesarios..."
mkdir -p sessions logs

# Configurar archivo .env si no existe
if [ ! -f .env ]; then
    print_status "Creando archivo de configuración .env..."
    cp env.example .env
    print_warning "Archivo .env creado. Por favor configura las variables de entorno."
else
    print_status "Archivo .env ya existe ✓"
fi

# Verificar permisos de escritura
print_status "Verificando permisos..."
if [ ! -w sessions ] || [ ! -w logs ]; then
    print_error "Sin permisos de escritura en directorios sessions/ o logs/"
    exit 1
fi

print_status "Permisos verificados ✓"

# Crear script de inicio
print_status "Creando script de inicio..."
cat > start.sh << 'EOF'
#!/bin/bash
echo "🚀 Iniciando Condo360 WhatsApp Service..."
echo "📱 Puerto: ${PORT:-3003}"
echo "🔗 URL: https://wschat.bonaventurecclub.com"
echo ""
echo "Para detener el servicio, presiona Ctrl+C"
echo ""

# Crear directorios si no existen
mkdir -p sessions logs

# Iniciar el servicio
npm start
EOF

chmod +x start.sh

# Crear script de desarrollo
print_status "Creando script de desarrollo..."
cat > dev.sh << 'EOF'
#!/bin/bash
echo "🔧 Iniciando Condo360 WhatsApp Service en modo desarrollo..."
echo "📱 Puerto: ${PORT:-3003}"
echo "🔗 URL: https://wschat.bonaventurecclub.com"
echo ""
echo "Modo desarrollo con auto-reload habilitado"
echo "Para detener el servicio, presiona Ctrl+C"
echo ""

# Crear directorios si no existen
mkdir -p sessions logs

# Iniciar en modo desarrollo
npm run dev
EOF

chmod +x dev.sh

# Verificar configuración de base de datos
print_status "Verificando configuración de base de datos..."
if grep -q "DB_HOST=localhost" .env; then
    print_warning "Configuración de base de datos por defecto detectada."
    print_warning "Por favor actualiza las variables DB_* en .env con tus credenciales de WordPress."
fi

# Mostrar información de configuración
echo ""
echo "✅ Instalación completada!"
echo ""
echo "📋 Próximos pasos:"
echo "1. Configura las variables de entorno en .env"
echo "2. Instala el plugin de WordPress desde la carpeta wordpress/"
echo "3. Configura Nginx Proxy Manager para wschat.bonaventurecclub.com"
echo "4. Ejecuta './start.sh' para iniciar el servicio"
echo ""
echo "🔧 Comandos disponibles:"
echo "  ./start.sh     - Iniciar en modo producción"
echo "  ./dev.sh       - Iniciar en modo desarrollo"
echo "  npm start      - Iniciar directamente"
echo "  npm run dev    - Desarrollo con auto-reload"
echo ""
echo "📖 Para más información, consulta README.md"
echo ""
print_status "¡Instalación exitosa! 🎉"
