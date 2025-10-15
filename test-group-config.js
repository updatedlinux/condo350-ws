#!/usr/bin/env node

/**
 * Script para probar la configuración de grupo directamente
 */

const mysql = require('mysql2/promise');
require('dotenv').config();

async function testGroupConfiguration() {
    console.log('🧪 Probando configuración de grupo...\n');
    
    const config = {
        host: process.env.DB_HOST || 'localhost',
        user: process.env.DB_USER || 'wordpress_user',
        password: process.env.DB_PASSWORD || 'wordpress_password',
        database: process.env.DB_NAME || 'wordpress_db',
        port: process.env.DB_PORT || 3306,
        charset: 'utf8mb4',
        timezone: '+00:00'
    };
    
    let connection;
    
    try {
        connection = await mysql.createConnection(config);
        console.log('✅ Conectado a la base de datos\n');
        
        // Grupo de prueba
        const testGroupId = '120363421771836255@g.us';
        const testGroupName = 'Grupo de Prueba Condo360';
        
        console.log(`📝 Configurando grupo de prueba:`);
        console.log(`   ID: ${testGroupId}`);
        console.log(`   Nombre: ${testGroupName}\n`);
        
        // Insertar/actualizar configuración
        const result = await connection.execute(
            'INSERT INTO condo360ws_config (config_key, config_value, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE config_value = ?, updated_at = NOW()',
            ['whatsapp_group_id', testGroupId, testGroupId]
        );
        
        console.log('✅ Grupo ID guardado:', result[0].affectedRows > 0 ? 'Éxito' : 'Sin cambios');
        
        // Verificar que se guardó
        const [rows] = await connection.execute(
            'SELECT config_value, updated_at FROM condo360ws_config WHERE config_key = ?',
            ['whatsapp_group_id']
        );
        
        if (rows.length > 0) {
            console.log('✅ Verificación exitosa:');
            console.log(`   Valor guardado: ${rows[0].config_value}`);
            console.log(`   Actualizado: ${rows[0].updated_at}`);
        } else {
            console.log('❌ Error: No se encontró la configuración guardada');
        }
        
        // Probar el endpoint
        console.log('\n🌐 Probando endpoint /api/configured-group...');
        
        const axios = require('axios');
        try {
            const response = await axios.get('https://wschat.bonaventurecclub.com/api/configured-group');
            console.log('✅ Respuesta del endpoint:');
            console.log(JSON.stringify(response.data, null, 2));
        } catch (error) {
            console.log('❌ Error llamando al endpoint:', error.message);
        }
        
    } catch (error) {
        console.error('❌ Error:', error.message);
    } finally {
        if (connection) {
            await connection.end();
            console.log('\n🔌 Conexión cerrada');
        }
    }
}

testGroupConfiguration().catch(console.error);
