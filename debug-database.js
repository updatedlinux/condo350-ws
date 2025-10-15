#!/usr/bin/env node

/**
 * Script de diagnóstico para verificar el estado de la base de datos
 * y la configuración del grupo de WhatsApp
 */

const mysql = require('mysql2/promise');
require('dotenv').config();

async function debugDatabase() {
    console.log('🔍 Iniciando diagnóstico de base de datos...\n');
    
    const config = {
        host: process.env.DB_HOST || 'localhost',
        user: process.env.DB_USER || 'wordpress_user',
        password: process.env.DB_PASSWORD || 'wordpress_password',
        database: process.env.DB_NAME || 'wordpress_db',
        port: process.env.DB_PORT || 3306,
        charset: 'utf8mb4',
        timezone: '+00:00'
    };
    
    console.log('📋 Configuración de conexión:');
    console.log(`   Host: ${config.host}`);
    console.log(`   Database: ${config.database}`);
    console.log(`   User: ${config.user}`);
    console.log(`   Port: ${config.port}\n`);
    
    let connection;
    
    try {
        // Conectar a la base de datos
        console.log('🔌 Conectando a la base de datos...');
        connection = await mysql.createConnection(config);
        console.log('✅ Conexión establecida correctamente\n');
        
        // Verificar tablas existentes
        console.log('📊 Verificando tablas existentes...');
        const [tables] = await connection.execute("SHOW TABLES LIKE 'condo360ws_%'");
        
        if (tables.length === 0) {
            console.log('❌ No se encontraron tablas de Condo360');
            console.log('   Las tablas necesarias son:');
            console.log('   - condo360ws_config');
            console.log('   - condo360ws_messages');
            console.log('   - condo360ws_connections');
            return;
        }
        
        console.log('✅ Tablas encontradas:');
        tables.forEach(table => {
            const tableName = Object.values(table)[0];
            console.log(`   - ${tableName}`);
        });
        console.log('');
        
        // Verificar tabla de configuración
        console.log('⚙️ Verificando configuración...');
        const [configRows] = await connection.execute(
            'SELECT config_key, config_value, updated_at FROM condo360ws_config ORDER BY config_key'
        );
        
        if (configRows.length === 0) {
            console.log('❌ No hay configuraciones guardadas');
        } else {
            console.log('✅ Configuraciones encontradas:');
            configRows.forEach(row => {
                console.log(`   ${row.config_key}: ${row.config_value} (${row.updated_at})`);
            });
        }
        console.log('');
        
        // Verificar grupo configurado específicamente
        console.log('🎯 Verificando grupo configurado...');
        const [groupRows] = await connection.execute(
            'SELECT config_value, updated_at FROM condo360ws_config WHERE config_key = ?',
            ['whatsapp_group_id']
        );
        
        if (groupRows.length === 0) {
            console.log('❌ No hay grupo configurado (whatsapp_group_id)');
        } else {
            const groupId = groupRows[0].config_value;
            const configuredAt = groupRows[0].updated_at;
            console.log(`✅ Grupo configurado: ${groupId}`);
            console.log(`   Configurado el: ${configuredAt}`);
            
            // Verificar nombre del grupo
            const [nameRows] = await connection.execute(
                'SELECT config_value FROM condo360ws_config WHERE config_key = ?',
                ['whatsapp_group_name']
            );
            
            if (nameRows.length > 0) {
                console.log(`   Nombre: ${nameRows[0].config_value}`);
            } else {
                console.log('   Nombre: No guardado');
            }
        }
        console.log('');
        
        // Verificar logs de mensajes
        console.log('📝 Verificando logs de mensajes...');
        const [messageRows] = await connection.execute(
            'SELECT COUNT(*) as total FROM condo360ws_messages'
        );
        
        console.log(`✅ Total de mensajes registrados: ${messageRows[0].total}`);
        
        if (messageRows[0].total > 0) {
            const [recentMessages] = await connection.execute(
                'SELECT group_id, group_name, status, created_at FROM condo360ws_messages ORDER BY created_at DESC LIMIT 5'
            );
            
            console.log('📋 Últimos 5 mensajes:');
            recentMessages.forEach(msg => {
                console.log(`   ${msg.created_at}: ${msg.status} - ${msg.group_name || 'Sin nombre'} (${msg.group_id})`);
            });
        }
        console.log('');
        
        // Verificar logs de conexión
        console.log('🔗 Verificando logs de conexión...');
        const [connectionRows] = await connection.execute(
            'SELECT COUNT(*) as total FROM condo360ws_connections'
        );
        
        console.log(`✅ Total de eventos de conexión: ${connectionRows[0].total}`);
        
        if (connectionRows[0].total > 0) {
            const [recentConnections] = await connection.execute(
                'SELECT status, created_at FROM condo360ws_connections ORDER BY created_at DESC LIMIT 3'
            );
            
            console.log('📋 Últimos 3 eventos de conexión:');
            recentConnections.forEach(conn => {
                console.log(`   ${conn.created_at}: ${conn.status}`);
            });
        }
        console.log('');
        
        console.log('✅ Diagnóstico completado exitosamente');
        
    } catch (error) {
        console.error('❌ Error durante el diagnóstico:', error.message);
        
        if (error.code === 'ECONNREFUSED') {
            console.log('\n💡 Posibles soluciones:');
            console.log('   - Verificar que MySQL/MariaDB esté ejecutándose');
            console.log('   - Verificar la configuración de conexión');
            console.log('   - Verificar que el usuario tenga permisos');
        } else if (error.code === 'ER_ACCESS_DENIED_ERROR') {
            console.log('\n💡 Posibles soluciones:');
            console.log('   - Verificar usuario y contraseña');
            console.log('   - Verificar permisos del usuario');
        } else if (error.code === 'ER_BAD_DB_ERROR') {
            console.log('\n💡 Posibles soluciones:');
            console.log('   - Verificar que la base de datos existe');
            console.log('   - Crear la base de datos si no existe');
        }
    } finally {
        if (connection) {
            await connection.end();
            console.log('🔌 Conexión cerrada');
        }
    }
}

// Ejecutar diagnóstico
debugDatabase().catch(console.error);
