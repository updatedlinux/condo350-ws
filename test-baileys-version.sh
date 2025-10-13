#!/bin/bash

# Script para probar con versión diferente de Baileys
# El problema puede ser la versión actual

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

print_header "PRUEBA CON VERSIÓN DIFERENTE DE BAILEYS"

# 1. Detener servicio
print_status "Deteniendo servicio..."
pkill -f "node src/index.js" 2>/dev/null || true
sleep 2

# 2. Verificar versión actual
print_status "Versión actual de Baileys:"
npm list @whiskeysockets/baileys

# 3. Limpiar sesiones
print_status "Limpiando sesiones..."
rm -rf sessions/* 2>/dev/null || true
mkdir -p sessions

# 4. Probar con versión estable anterior
print_status "Probando con versión estable anterior..."
npm install @whiskeysockets/baileys@6.5.0

print_status "✅ Versión 6.5.0 instalada"
print_status "✅ Sesiones limpias"

echo ""
print_warning "IMPORTANTE:"
print_warning "1. Edita .env con las credenciales correctas de MySQL"
print_warning "2. Ejecuta: npm start"
print_warning "3. Observa si la versión anterior funciona mejor"

echo ""
print_status "Para iniciar:"
print_status "npm start"

echo ""
print_status "Si funciona, mantén esta versión."
print_status "Si no funciona, podemos probar otras versiones."
