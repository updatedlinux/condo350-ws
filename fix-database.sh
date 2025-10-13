#!/bin/bash

# Script para configurar credenciales de base de datos
# Soluciona el error: Access denied for user 'wordpress_user'@'172.18.0.1'

print_header() {
    echo -e "\033[0;34m=== $1 ===\033[0m"
}

print_status() {
    echo -e "\033[0;32m[INFO]\033[0m $1"
}

print_warning() {
    echo -e "\033[1;33m[WARNING]\033[0m $1"
}

print_error() {
    echo -e "\033[0;31m[ERROR]\033[0m $1"
}

print_header "Configuración de Base de Datos"

# Mostrar configuración actual
print_status "Configuración actual en .env:"
echo "DB_HOST: $(grep '^DB_HOST=' .env 2>/dev/null | cut -d'=' -f2 || echo 'No configurado')"
echo "DB_USER: $(grep '^DB_USER=' .env 2>/dev/null | cut -d'=' -f2 || echo 'No configurado')"
echo "DB_NAME: $(grep '^DB_NAME=' .env 2>/dev/null | cut -d'=' -f2 || echo 'No configurado')"

echo ""
print_warning "El error indica que las credenciales de MySQL son incorrectas."
print_warning "Necesitas configurar las credenciales correctas."

echo ""
print_status "Opciones comunes:"
echo "1. Usuario root con contraseña"
echo "2. Usuario específico de WordPress"
echo "3. Usuario con permisos completos"

echo ""
read -p "¿Quieres configurar las credenciales ahora? (y/N): " -n 1 -r
echo ""

if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    print_status "Operación cancelada."
    exit 0
fi

# Solicitar credenciales
echo ""
read -p "Host de MySQL (localhost): " DB_HOST
DB_HOST=${DB_HOST:-localhost}

read -p "Usuario de MySQL (root): " DB_USER
DB_USER=${DB_USER:-root}

read -s -p "Contraseña de MySQL: " DB_PASSWORD
echo ""

read -p "Nombre de la base de datos (wordpress): " DB_NAME
DB_NAME=${DB_NAME:-wordpress}

read -p "Puerto de MySQL (3306): " DB_PORT
DB_PORT=${DB_PORT:-3306}

# Crear configuración temporal
print_status "Creando configuración temporal..."

cat > .env.temp << EOF
# Configuración de base de datos corregida
NODE_ENV=development
PORT=3003
LOG_LEVEL=debug
WHATSAPP_SESSION_PATH=./sessions
RECONNECT_INTERVAL=30000
QR_REFRESH_INTERVAL=10000
RATE_LIMIT_WINDOW_MS=900000
RATE_LIMIT_MAX_REQUESTS=1000

# Configuración de base de datos
DB_HOST=$DB_HOST
DB_USER=$DB_USER
DB_PASSWORD=$DB_PASSWORD
DB_NAME=$DB_NAME
DB_PORT=$DB_PORT

# Configuración de seguridad
API_SECRET_KEY=condo360_whatsapp_secret_2025
EOF

print_status "Configuración creada en .env.temp"
print_status "Para aplicar la configuración:"
print_status "1. cp .env.temp .env"
print_status "2. npm start"

echo ""
read -p "¿Aplicar la configuración ahora? (y/N): " -n 1 -r
echo ""

if [[ $REPLY =~ ^[Yy]$ ]]; then
    cp .env.temp .env
    print_status "✅ Configuración aplicada"
    print_status "Ahora puedes ejecutar: npm start"
else
    print_status "Configuración guardada en .env.temp"
    print_status "Ejecuta: cp .env.temp .env && npm start"
fi
