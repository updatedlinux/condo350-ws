#!/bin/bash

# Script de PRUEBA para diagnosticar el problema de QR
# Muestra logs detallados para entender qué está pasando

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

print_header "DIAGNÓSTICO - Problema de QR WhatsApp"

# 1. Detener servicio
print_status "Deteniendo servicio actual..."
pkill -f "node src/index.js" 2>/dev/null || true
sleep 2

# 2. Limpiar sesiones
print_status "Limpiando sesiones..."
rm -rf sessions/* 2>/dev/null || true
mkdir -p sessions

# 3. Verificar que no hay sesiones
print_status "Verificando limpieza de sesiones..."
if [ -d "sessions" ] && [ "$(ls -A sessions 2>/dev/null)" ]; then
    print_warning "Aún hay archivos en sessions:"
    ls -la sessions/
    print_warning "Eliminando..."
    rm -rf sessions/*
else
    print_status "✓ Directorio sessions limpio"
fi

# 4. Crear configuración de debug
print_status "Creando configuración de debug..."
cat > .env.debug << EOF
NODE_ENV=development
PORT=3003
LOG_LEVEL=debug
WHATSAPP_SESSION_PATH=./sessions
RECONNECT_INTERVAL=30000
QR_REFRESH_INTERVAL=10000
RATE_LIMIT_WINDOW_MS=900000
RATE_LIMIT_MAX_REQUESTS=1000
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=tu_password_mysql
DB_NAME=wordpress_db
DB_PORT=3306
API_SECRET_KEY=condo360_whatsapp_secret_2025
EOF

# 5. Aplicar configuración de debug
cp .env.debug .env

print_status "✅ Configuración de debug aplicada"
print_status "✅ Sesiones completamente limpias"
print_status "✅ Logs detallados habilitados"

echo ""
print_warning "IMPORTANTE:"
print_warning "1. Edita .env con las credenciales correctas de MySQL"
print_warning "2. Ejecuta: npm start"
print_warning "3. Observa los logs para ver si aparece el QR"
print_warning "4. Busca el mensaje: '🎯 QR RECIBIDO'"

echo ""
print_status "Para iniciar con logs detallados:"
print_status "npm start"

echo ""
print_status "Para monitorear logs en tiempo real:"
print_status "tail -f logs/whatsapp-service.log"
