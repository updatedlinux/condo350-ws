#!/bin/bash

# Script para FORZAR limpieza completa de sesiones WhatsApp
# Soluciona el error persistente de validación

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

print_error() {
    echo -e "\033[0;31m[ERROR]\033[0m $1"
}

print_header "FORZAR LIMPIEZA COMPLETA - Error Validación WhatsApp"

# 1. Detener servicio
print_status "Deteniendo servicio WhatsApp..."
pkill -f "node src/index.js" 2>/dev/null || true
sleep 2

# 2. FORZAR limpieza completa de sesiones
print_status "FORZANDO limpieza completa de sesiones..."
if [ -d "sessions" ]; then
    rm -rf sessions/*
    print_status "✓ Todas las sesiones eliminadas"
else
    mkdir -p sessions
fi

# 3. Limpiar logs
print_status "Limpiando logs..."
if [ -d "logs" ]; then
    rm -f logs/*.log 2>/dev/null || true
    print_status "✓ Logs limpiados"
else
    mkdir -p logs
fi

# 4. Verificar que no hay archivos de sesión
print_status "Verificando limpieza..."
if [ -d "sessions" ] && [ "$(ls -A sessions 2>/dev/null)" ]; then
    print_warning "Aún hay archivos en sessions/. Eliminando..."
    rm -rf sessions/*
fi

# 5. Crear configuración ultra-simple
print_status "Creando configuración ultra-simple..."
cat > .env.simple << EOF
NODE_ENV=development
PORT=3003
LOG_LEVEL=info
WHATSAPP_SESSION_PATH=./sessions
RECONNECT_INTERVAL=60000
QR_REFRESH_INTERVAL=20000
RATE_LIMIT_WINDOW_MS=900000
RATE_LIMIT_MAX_REQUESTS=1000
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=tu_password_mysql
DB_NAME=wordpress_db
DB_PORT=3306
API_SECRET_KEY=condo360_whatsapp_secret_2025
EOF

# 6. Aplicar configuración simple
cp .env.simple .env

print_status "✅ Limpieza completa realizada"
print_status "✅ Configuración ultra-simple aplicada"
print_status "✅ Sesiones completamente limpias"

echo ""
print_warning "IMPORTANTE:"
print_warning "1. Edita .env con las credenciales correctas de MySQL"
print_warning "2. Ejecuta: npm start"
print_warning "3. Deberías ver el QR sin errores de validación"

echo ""
print_status "Para iniciar el servicio:"
print_status "npm start"
