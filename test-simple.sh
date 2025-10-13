#!/bin/bash

# Script ULTRA SIMPLE para probar WhatsApp sin problemas
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

print_header "PRUEBA SIMPLE - WhatsApp Service"

# 1. Detener todo
print_status "Deteniendo procesos..."
pkill -f "node" 2>/dev/null || true
sleep 2

# 2. Limpiar sesiones
print_status "Limpiando sesiones..."
rm -rf sessions/* 2>/dev/null || true
mkdir -p sessions

# 3. Crear configuración mínima
print_status "Creando configuración mínima..."
cat > .env.simple << EOF
NODE_ENV=development
PORT=3003
LOG_LEVEL=info
WHATSAPP_SESSION_PATH=./sessions
RECONNECT_INTERVAL=60000
QR_REFRESH_INTERVAL=15000
RATE_LIMIT_WINDOW_MS=900000
RATE_LIMIT_MAX_REQUESTS=1000
DB_HOST=localhost
DB_USER=wordpress_user
DB_PASSWORD=wordpress_password
DB_NAME=wordpress_db
DB_PORT=3306
API_SECRET_KEY=condo360_whatsapp_secret_2025
EOF

# 4. Usar configuración simple
cp .env.simple .env

# 5. Iniciar servicio
print_status "Iniciando servicio simple..."
print_warning "Monitorea los logs para ver si aparece el QR..."

# Iniciar directamente (no en background para ver logs)
npm start
