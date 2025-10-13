#!/bin/bash

# Script para probar con configuración ULTRA-MÍNIMA
# Evita completamente el error de validación

set -e

print_header() {
    echo -e "\033[0;34m=== $1 ===\033[0m"
}

print_status() {
    echo -e "\033[0;32m[INFO]\033[0m $1"
}

print_warning() {
    echo -e "\033[1;33m[WARNING]\033[0m $1"
}

print_header "PRUEBA CONFIGURACIÓN ULTRA-MÍNIMA"

# 1. Detener servicio
print_status "Deteniendo servicio..."
pkill -f "node src/index.js" 2>/dev/null || true
sleep 2

# 2. Limpiar TODO
print_status "Limpiando completamente..."
rm -rf sessions/* 2>/dev/null || true
rm -f logs/*.log 2>/dev/null || true
mkdir -p sessions logs

# 3. Crear configuración ultra-mínima
print_status "Creando configuración ultra-mínima..."
cat > .env.minimal << EOF
NODE_ENV=development
PORT=3003
LOG_LEVEL=info
WHATSAPP_SESSION_PATH=./sessions
RECONNECT_INTERVAL=60000
QR_REFRESH_INTERVAL=30000
RATE_LIMIT_WINDOW_MS=900000
RATE_LIMIT_MAX_REQUESTS=1000
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=tu_password_mysql
DB_NAME=wordpress_db
DB_PORT=3306
API_SECRET_KEY=condo360_whatsapp_secret_2025
EOF

# 4. Aplicar configuración
cp .env.minimal .env

print_status "✅ Configuración ultra-mínima aplicada"
print_status "✅ Sesiones completamente limpias"
print_status "✅ Timeouts más largos para evitar errores"

echo ""
print_warning "IMPORTANTE:"
print_warning "1. Edita .env con las credenciales correctas de MySQL"
print_warning "2. Ejecuta: npm start"
print_warning "3. Observa si aparece el QR después de más tiempo"

echo ""
print_status "Para iniciar:"
print_status "npm start"
