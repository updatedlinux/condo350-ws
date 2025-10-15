/**
 * Servicio de base de datos para integrar con WordPress
 * Maneja la persistencia de configuración y logs de mensajes
 */

const mysql = require('mysql2/promise');
const logger = require('../utils/logger');

class DatabaseService {
    constructor() {
        this.connection = null;
        this.config = {
            host: process.env.DB_HOST || 'localhost',
            user: process.env.DB_USER || 'wordpress_user',
            password: process.env.DB_PASSWORD || 'wordpress_password',
            database: process.env.DB_NAME || 'wordpress_db',
            port: process.env.DB_PORT || 3306,
            charset: 'utf8mb4',
            timezone: '+00:00'
        };
    }

    /**
     * Inicializa la conexión a la base de datos
     */
    async initialize() {
        try {
            this.connection = await mysql.createConnection(this.config);
            logger.info('Conexión a base de datos establecida');

            // Crear tablas si no existen
            await this.createTables();
            
        } catch (error) {
            logger.error('Error conectando a base de datos:', error);
            throw error;
        }
    }

    /**
     * Crea las tablas necesarias en la base de datos de WordPress
     */
    async createTables() {
        try {
            // Tabla para configuración del servicio
            const configTable = `
                CREATE TABLE IF NOT EXISTS condo360ws_config (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    config_key VARCHAR(100) UNIQUE NOT NULL,
                    config_value TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            `;

            // Tabla para logs de mensajes
            const messagesTable = `
                CREATE TABLE IF NOT EXISTS condo360ws_messages (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    group_id VARCHAR(100) NOT NULL,
                    group_name VARCHAR(255),
                    message TEXT NOT NULL,
                    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
                    message_id VARCHAR(100),
                    error_message TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_group_id (group_id),
                    INDEX idx_status (status),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            `;

            // Tabla para logs de conexión
            const connectionTable = `
                CREATE TABLE IF NOT EXISTS condo360ws_connections (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    status ENUM('connected', 'disconnected', 'qr_generated', 'error') NOT NULL,
                    qr_code TEXT,
                    error_message TEXT,
                    user_info JSON,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_status (status),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            `;

            await this.connection.execute(configTable);
            await this.connection.execute(messagesTable);
            await this.connection.execute(connectionTable);
            
            // Verificar y agregar columna group_name si no existe
            await this.addGroupNameColumnIfNotExists();
            
            logger.info('Tablas de base de datos creadas/verificadas correctamente');

        } catch (error) {
            logger.error('Error creando tablas:', error);
            throw error;
        }
    }

    /**
     * Verifica y agrega la columna group_name si no existe
     */
    async addGroupNameColumnIfNotExists() {
        try {
            // Verificar si la columna group_name existe
            const [columns] = await this.connection.execute(
                "SHOW COLUMNS FROM condo360ws_messages LIKE 'group_name'"
            );
            
            if (columns.length === 0) {
                // Agregar la columna group_name
                await this.connection.execute(
                    "ALTER TABLE condo360ws_messages ADD COLUMN group_name VARCHAR(255) AFTER group_id"
                );
                logger.info('Columna group_name agregada a condo360ws_messages');
            }
        } catch (error) {
            logger.error('Error verificando/agregando columna group_name:', error);
        }
    }

    /**
     * Obtiene el ID del grupo configurado
     */
    async getGroupId() {
        try {
            const [rows] = await this.connection.execute(
                'SELECT config_value FROM condo360ws_config WHERE config_key = ?',
                ['whatsapp_group_id']
            );

            return rows.length > 0 ? rows[0].config_value : null;
        } catch (error) {
            logger.error('Error obteniendo grupo ID:', error);
            return null;
        }
    }

    /**
     * Configura el ID del grupo de WhatsApp
     */
    async setGroupId(groupId, groupName = null) {
        try {
            await this.connection.execute(
                'INSERT INTO condo360ws_config (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = ?, updated_at = CURRENT_TIMESTAMP',
                ['whatsapp_group_id', groupId, groupId]
            );

            // Si se proporciona el nombre del grupo, también guardarlo
            if (groupName) {
                await this.connection.execute(
                    'INSERT INTO condo360ws_config (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = ?, updated_at = CURRENT_TIMESTAMP',
                    ['whatsapp_group_name', groupName, groupName]
                );
            }

            logger.info(`Grupo ID configurado: ${groupId}${groupName ? ` (${groupName})` : ''}`);
            return true;
        } catch (error) {
            logger.error('Error configurando grupo ID:', error);
            throw error;
        }
    }

    /**
     * Obtiene una configuración específica
     */
    async getConfig(key) {
        try {
            const [rows] = await this.connection.execute(
                'SELECT config_value FROM condo360ws_config WHERE config_key = ?',
                [key]
            );

            return rows.length > 0 ? rows[0].config_value : null;
        } catch (error) {
            logger.error(`Error obteniendo configuración ${key}:`, error);
            return null;
        }
    }

    /**
     * Establece una configuración específica
     */
    async setConfig(key, value) {
        try {
            await this.connection.execute(
                'INSERT INTO condo360ws_config (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = ?, updated_at = CURRENT_TIMESTAMP',
                [key, value, value]
            );

            return true;
        } catch (error) {
            logger.error(`Error configurando ${key}:`, error);
            throw error;
        }
    }

    /**
     * Registra un mensaje enviado
     */
    async logMessage(messageData) {
        try {
            const { groupId, message, status, messageId, error } = messageData;
            
            await this.connection.execute(
                'INSERT INTO condo360ws_messages (group_id, message, status, message_id, error_message) VALUES (?, ?, ?, ?, ?)',
                [groupId, message, status, messageId || null, error || null]
            );

            logger.info(`Mensaje registrado: ${status} - ${message.substring(0, 50)}...`);
            return true;
        } catch (error) {
            logger.error('Error registrando mensaje:', error);
            throw error;
        }
    }

    /**
     * Registra eventos de conexión
     */
    async logConnection(status, data = {}) {
        try {
            const { qrCode, errorMessage, userInfo } = data;
            
            await this.connection.execute(
                'INSERT INTO condo360ws_connections (status, qr_code, error_message, user_info) VALUES (?, ?, ?, ?)',
                [status, qrCode || null, errorMessage || null, userInfo ? JSON.stringify(userInfo) : null]
            );

            logger.info(`Conexión registrada: ${status}`);
            return true;
        } catch (error) {
            logger.error('Error registrando conexión:', error);
            throw error;
        }
    }

    /**
     * Obtiene el historial de mensajes
     */
    async getMessageHistory(limit = 50, offset = 0) {
        try {
            const [rows] = await this.connection.execute(
                'SELECT * FROM condo360ws_messages ORDER BY created_at DESC LIMIT ? OFFSET ?',
                [limit, offset]
            );

            return rows;
        } catch (error) {
            logger.error('Error obteniendo historial de mensajes:', error);
            return [];
        }
    }

    /**
     * Obtiene estadísticas de mensajes
     */
    async getMessageStats() {
        try {
            const [rows] = await this.connection.execute(`
                SELECT 
                    status,
                    COUNT(*) as count,
                    DATE(created_at) as date
                FROM condo360ws_messages 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY status, DATE(created_at)
                ORDER BY date DESC
            `);

            return rows;
        } catch (error) {
            logger.error('Error obteniendo estadísticas:', error);
            return [];
        }
    }

    /**
     * Obtiene el estado de conexión más reciente
     */
    async getLastConnectionStatus() {
        try {
            const [rows] = await this.connection.execute(
                'SELECT * FROM condo360ws_connections ORDER BY created_at DESC LIMIT 1'
            );

            return rows.length > 0 ? rows[0] : null;
        } catch (error) {
            logger.error('Error obteniendo último estado de conexión:', error);
            return null;
        }
    }

    /**
     * Obtiene el grupo configurado desde la base de datos de WordPress
     */
    async getConfiguredGroup() {
        try {
            // Buscar en la tabla de configuración de WordPress
            const [rows] = await this.connection.execute(
                'SELECT config_value, updated_at FROM condo360ws_config WHERE config_key = ?',
                ['whatsapp_group_id']
            );

            if (rows.length > 0) {
                const groupId = rows[0].config_value;
                const configuredAt = rows[0].updated_at;
                
                // Intentar obtener el nombre del grupo desde la tabla de configuración
                try {
                    const [nameRows] = await this.connection.execute(
                        'SELECT config_value FROM condo360ws_config WHERE config_key = ?',
                        ['whatsapp_group_name']
                    );
                    
                    let groupName = 'Grupo desconocido';
                    if (nameRows.length > 0 && nameRows[0].config_value) {
                        groupName = nameRows[0].config_value;
                    } else {
                        // Fallback: buscar en logs de mensajes
                        const [messageRows] = await this.connection.execute(
                            'SELECT group_name FROM condo360ws_messages WHERE group_id = ? ORDER BY created_at DESC LIMIT 1',
                            [groupId]
                        );
                        
                        if (messageRows.length > 0 && messageRows[0].group_name) {
                            groupName = messageRows[0].group_name;
                        }
                    }
                    
                    return {
                        groupId,
                        groupName,
                        configuredAt
                    };
                } catch (nameError) {
                    // Si no se puede obtener el nombre, usar solo el ID
                    logger.warn('No se pudo obtener nombre del grupo:', nameError.message);
                    return {
                        groupId,
                        groupName: 'Grupo desconocido',
                        configuredAt
                    };
                }
            }

            return null;
        } catch (error) {
            logger.error('Error obteniendo grupo configurado:', error);
            return null;
        }
    }

    /**
     * Limpia logs antiguos (más de 30 días)
     */
    async cleanOldLogs() {
        try {
            const [result] = await this.connection.execute(
                'DELETE FROM condo360ws_messages WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)'
            );

            const [result2] = await this.connection.execute(
                'DELETE FROM condo360ws_connections WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)'
            );

            logger.info(`Logs limpiados: ${result.affectedRows} mensajes, ${result2.affectedRows} conexiones`);
            return { messages: result.affectedRows, connections: result2.affectedRows };
        } catch (error) {
            logger.error('Error limpiando logs antiguos:', error);
            return { messages: 0, connections: 0 };
        }
    }

    /**
     * Cierra la conexión a la base de datos
     */
    async close() {
        try {
            if (this.connection) {
                await this.connection.end();
                this.connection = null;
                logger.info('Conexión a base de datos cerrada');
            }
        } catch (error) {
            logger.error('Error cerrando conexión a base de datos:', error);
        }
    }

    /**
     * Verifica la salud de la conexión
     */
    async healthCheck() {
        try {
            if (!this.connection) {
                return { healthy: false, error: 'No hay conexión activa' };
            }

            await this.connection.execute('SELECT 1');
            return { healthy: true };
        } catch (error) {
            return { healthy: false, error: error.message };
        }
    }
}

module.exports = DatabaseService;
