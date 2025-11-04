/**
 * Servicio principal de WhatsApp para Condo360
 * Maneja la conexión con WhatsApp usando Baileys y expone endpoints REST
 */

const express = require('express');
const cors = require('cors');
const helmet = require('helmet');
// const rateLimit = require('express-rate-limit'); // Deshabilitado temporalmente
require('dotenv').config();

const WhatsAppService = require('./services/whatsappService');
const DatabaseService = require('./services/databaseService');
const logger = require('./utils/logger');

class Condo360WhatsAppService {
    constructor() {
        this.app = express();
        this.port = process.env.PORT || 3003;
        this.databaseService = new DatabaseService();
        this.whatsappService = new WhatsAppService(this.databaseService);
        this.syncInterval = null; // Intervalo de sincronización periódica
        
        this.setupMiddleware();
        this.setupRoutes();
        this.setupErrorHandling();
    }

    /**
     * Configura el middleware de Express
     */
    setupMiddleware() {
        // Configurar trust proxy para Nginx Proxy Manager
        this.app.set('trust proxy', true);
        
        // Seguridad básica
        this.app.use(helmet());
        
        // CORS para permitir requests desde WordPress
        this.app.use(cors({
            origin: process.env.WORDPRESS_URL || '*',
            credentials: true
        }));

        // Rate limiting deshabilitado temporalmente para desarrollo
        // Para reactivar en producción, descomentar las siguientes líneas:
        // const rateLimit = require('express-rate-limit');
        // const limiter = rateLimit({
        //     windowMs: parseInt(process.env.RATE_LIMIT_WINDOW_MS) || 15 * 60 * 1000, // 15 minutos
        //     max: parseInt(process.env.RATE_LIMIT_MAX_REQUESTS) || 100,
        //     message: {
        //         error: 'Demasiadas solicitudes, intenta más tarde',
        //         retryAfter: Math.ceil((parseInt(process.env.RATE_LIMIT_WINDOW_MS) || 15 * 60 * 1000) / 1000)
        //     },
        //     standardHeaders: true,
        //     legacyHeaders: false,
        //     // Configurar para funcionar con proxy
        //     keyGenerator: (req) => {
        //         return req.ip || req.connection.remoteAddress;
        //     }
        // });
        // this.app.use('/api/', limiter);

        // Parse JSON
        this.app.use(express.json({ limit: '10mb' }));
        this.app.use(express.urlencoded({ extended: true }));
    }

    /**
     * Configura las rutas de la API
     */
    setupRoutes() {
        // Health check con información de base de datos
        this.app.get('/api/health', async (req, res) => {
            try {
                const dbHealth = await this.databaseService.healthCheck();
                const configuredGroup = await this.databaseService.getConfiguredGroup();
                
                res.json({
                    status: 'ok',
                    timestamp: new Date().toISOString(),
                    uptime: process.uptime(),
                    database: dbHealth,
                    whatsapp: {
                        connected: this.whatsappService.isConnected(),
                        qrGenerated: this.whatsappService.isQRGenerated,
                        isReconnecting: this.whatsappService.isReconnecting,
                        reconnectAttempts: this.whatsappService.reconnectAttempts,
                        maxReconnectAttempts: this.whatsappService.maxReconnectAttempts
                    },
                    configuredGroup: configuredGroup
                });
            } catch (error) {
                logger.error('Error en health check:', error);
                res.status(500).json({
                    status: 'error',
                    timestamp: new Date().toISOString(),
                    error: error.message
                });
            }
        });

        // Obtener estado de conexión
        this.app.get('/api/status', (req, res) => {
            try {
                const status = {
                    connected: this.whatsappService.isConnected(),
                    qrGenerated: this.whatsappService.isQRGenerated,
                    groupId: process.env.WHATSAPP_GROUP_ID,
                    clientInfo: this.whatsappService.getClientInfo(),
                    reconnection: {
                        isReconnecting: this.whatsappService.isReconnecting,
                        attempts: this.whatsappService.reconnectAttempts,
                        maxAttempts: this.whatsappService.maxReconnectAttempts
                    }
                };
                res.json({ success: true, data: status });
            } catch (error) {
                logger.error('Error obteniendo estado:', error);
                res.status(500).json({ success: false, error: 'Error interno del servidor' });
            }
        });

        // Obtener QR para conexión
        this.app.get('/api/qr', async (req, res) => {
            try {
                if (this.whatsappService.isConnected()) {
                    return res.json({
                        success: true,
                        connected: true,
                        message: 'WhatsApp ya está conectado'
                    });
                }

                const qrData = this.whatsappService.getQRCode();
                if (!qrData) {
                    return res.status(404).json({
                        success: false,
                        error: 'QR no disponible. Intenta reconectar.'
                    });
                }

                res.json({
                    success: true,
                    connected: false,
                    qr: qrData,
                    expiresAt: new Date(Date.now() + 60000).toISOString() // QR expira en 1 minuto
                });
            } catch (error) {
                logger.error('Error obteniendo QR:', error);
                res.status(500).json({ success: false, error: 'Error generando QR' });
            }
        });

        // Enviar mensaje a grupo
        this.app.post('/api/send-message', async (req, res) => {
            try {
                const { message, secretKey } = req.body;

                // Validar secret key
                if (secretKey !== process.env.API_SECRET_KEY) {
                    return res.status(401).json({
                        success: false,
                        error: 'Clave de API inválida'
                    });
                }

                // Validar mensaje
                if (!message || typeof message !== 'string' || message.trim().length === 0) {
                    return res.status(400).json({
                        success: false,
                        error: 'Mensaje requerido'
                    });
                }

                // Verificar conexión real (no solo el flag)
                const isActive = await this.whatsappService.isConnectionActive();
                if (!isActive) {
                    // Si no está activa pero el flag dice que está conectado, iniciar reconexión
                    if (this.whatsappService.isConnected() && !this.whatsappService.isReconnecting) {
                        logger.warn('Conexión inactiva detectada en /api/send-message, iniciando reconexión...');
                        this.whatsappService.startReconnectionProcess();
                    }
                    return res.status(503).json({
                        success: false,
                        error: 'WhatsApp no está conectado o la conexión está inactiva. Escanea el QR o espera a la reconexión automática.',
                        reconnecting: this.whatsappService.isReconnecting
                    });
                }

                // Verificar grupo configurado
                const configuredGroup = await this.databaseService.getConfiguredGroup();
                if (!configuredGroup || !configuredGroup.groupId) {
                    return res.status(400).json({
                        success: false,
                        error: 'Grupo de WhatsApp no configurado'
                    });
                }

                // Enviar mensaje
                const result = await this.whatsappService.sendMessage(message.trim(), configuredGroup.groupId);
                
                // Guardar en base de datos
                await this.databaseService.logMessage({
                    groupId: configuredGroup.groupId,
                    groupName: configuredGroup.groupName,
                    message: message.trim(),
                    status: result.success ? 'sent' : 'failed',
                    timestamp: new Date(),
                    error: result.error || null
                });

                res.json({
                    success: result.success,
                    messageId: result.messageId,
                    groupId: result.groupId,
                    error: result.error
                });

            } catch (error) {
                logger.error('Error enviando mensaje:', error);
                res.status(500).json({
                    success: false,
                    error: 'Error interno enviando mensaje'
                });
            }
        });

        // Obtener grupos disponibles
        this.app.get('/api/groups', async (req, res) => {
            try {
                // Verificar conexión real (no solo el flag)
                const isActive = await this.whatsappService.isConnectionActive();
                if (!isActive) {
                    // Si no está activa pero el flag dice que está conectado, iniciar reconexión
                    if (this.whatsappService.isConnected() && !this.whatsappService.isReconnecting) {
                        logger.warn('Conexión inactiva detectada en /api/groups, iniciando reconexión...');
                        this.whatsappService.startReconnectionProcess();
                    }
                    return res.status(503).json({
                        success: false,
                        error: 'WhatsApp no está conectado o la conexión está inactiva. Escanea el QR o espera a la reconexión automática.',
                        reconnecting: this.whatsappService.isReconnecting
                    });
                }

                // Obtener grupos
                const groups = await this.whatsappService.getGroups();
                
                res.json({
                    success: true,
                    data: groups
                });

            } catch (error) {
                logger.error('Error obteniendo grupos:', error);
                
                // Si el error indica conexión cerrada, proporcionar más información
                if (error.message && error.message.includes('closed state')) {
                    return res.status(503).json({
                        success: false,
                        error: 'La conexión de WhatsApp está cerrada. Se está intentando reconectar automáticamente.',
                        reconnecting: this.whatsappService.isReconnecting
                    });
                }
                
                res.status(500).json({
                    success: false,
                    error: 'Error obteniendo grupos',
                    details: error.message
                });
            }
        });

        // Obtener grupo configurado desde la base de datos
        this.app.get('/api/configured-group', async (req, res) => {
            try {
                // Intentar obtener desde la base de datos (método principal)
                let configuredGroup = null;
                try {
                    configuredGroup = await this.databaseService.getConfiguredGroup();
                } catch (dbError) {
                    logger.warn('Error leyendo configuración desde BD, intentando fallback:', dbError.message);
                    
                    // Fallback 1: Intentar leer directamente desde la BD con consulta simple
                    try {
                        const groupId = await this.databaseService.getGroupId();
                        if (groupId) {
                            configuredGroup = {
                                groupId: groupId,
                                groupName: await this.databaseService.getConfig('whatsapp_group_name') || null,
                                configuredAt: null
                            };
                        }
                    } catch (fallbackError) {
                        logger.warn('Error en fallback 1:', fallbackError.message);
                    }
                }

                // Fallback 2: Si aún no hay datos, intentar desde el servicio de WhatsApp (solo lectura, no requiere conexión activa)
                if (!configuredGroup || !configuredGroup.groupId) {
                    if (this.whatsappService.groupId) {
                        logger.info('Usando configuración del grupo desde servicio de WhatsApp como respaldo');
                        configuredGroup = {
                            groupId: this.whatsappService.groupId,
                            groupName: null,
                            configuredAt: null
                        };
                    }
                }

                // Fallback 3: Intentar desde variable de entorno
                if (!configuredGroup || !configuredGroup.groupId) {
                    if (process.env.WHATSAPP_GROUP_ID) {
                        logger.info('Usando configuración del grupo desde variable de entorno como respaldo');
                        configuredGroup = {
                            groupId: process.env.WHATSAPP_GROUP_ID,
                            groupName: null,
                            configuredAt: null
                        };
                    }
                }

                res.json({ 
                    success: true, 
                    data: {
                        groupId: configuredGroup?.groupId || null,
                        groupName: configuredGroup?.groupName || null,
                        configuredAt: configuredGroup?.configuredAt || null
                    }
                });
            } catch (error) {
                logger.error('Error obteniendo grupo configurado:', error);
                // En caso de error total, devolver respuesta vacía pero exitosa
                res.json({ 
                    success: true, 
                    data: {
                        groupId: null,
                        groupName: null,
                        configuredAt: null
                    }
                });
            }
        });

        // Desconectar WhatsApp
        this.app.post('/api/disconnect', async (req, res) => {
            try {
                const { secretKey } = req.body;

                // Validar secret key
                if (secretKey !== process.env.API_SECRET_KEY) {
                    return res.status(401).json({
                        success: false,
                        error: 'Clave de API inválida'
                    });
                }

                // Desconectar WhatsApp
                await this.whatsappService.destroy();
                
                // Limpiar configuración del grupo para forzar nueva selección
                await this.databaseService.clearGroupConfiguration();
                
                logger.info('WhatsApp desconectado manualmente y configuración del grupo limpiada');
                
                // Reinicializar para generar nuevo QR
                setTimeout(async () => {
                    try {
                        // Crear un nuevo cliente completamente limpio
                        await this.whatsappService.setupClient();
                        await this.whatsappService.initialize();
                        logger.info('WhatsApp reinicializado para generar nuevo QR');
                    } catch (error) {
                        logger.error('Error reinicializando WhatsApp:', error);
                    }
                }, 3000);
                
                res.json({
                    success: true,
                    message: 'WhatsApp desconectado correctamente y configuración del grupo limpiada'
                });

            } catch (error) {
                logger.error('Error desconectando WhatsApp:', error);
                res.status(500).json({ 
                    success: false, 
                    error: 'Error interno del servidor' 
                });
            }
        });

        // Configurar grupo de destino
        this.app.post('/api/set-group', async (req, res) => {
            try {
                const { groupId, groupName, secretKey } = req.body;

                if (secretKey !== process.env.API_SECRET_KEY) {
                    return res.status(401).json({
                        success: false,
                        error: 'Clave de API inválida'
                    });
                }

                if (!groupId || typeof groupId !== 'string') {
                    return res.status(400).json({
                        success: false,
                        error: 'ID de grupo requerido'
                    });
                }

                // PASO 1: Guardar en base de datos PRIMERO (esto es lo más importante)
                // No importa el estado de WhatsApp, la configuración debe guardarse
                try {
                    await this.databaseService.setGroupId(groupId, groupName);
                    logger.info(`✅ Grupo guardado en BD: ${groupId}${groupName ? ` (${groupName})` : ''}`);
                } catch (dbError) {
                    logger.error('Error crítico guardando grupo en BD:', dbError);
                    return res.status(500).json({
                        success: false,
                        error: 'Error guardando configuración en base de datos',
                        details: dbError.message
                    });
                }

                // PASO 2: Sincronizar en todos los lugares posibles
                process.env.WHATSAPP_GROUP_ID = groupId;
                this.whatsappService.groupId = groupId;

                // PASO 3: Verificar conexión (opcional, solo para validación)
                if (!this.whatsappService.isReconnecting) {
                    try {
                        const isActive = await this.whatsappService.isConnectionActive();
                        if (!isActive && this.whatsappService.isConnected()) {
                            logger.warn('Conexión inactiva detectada, iniciando reconexión...');
                            this.whatsappService.startReconnectionProcess();
                        }
                    } catch (connError) {
                        // No fallar si hay error de conexión, la configuración ya está guardada
                        logger.warn('No se pudo verificar conexión, pero el grupo ya está guardado:', connError.message);
                    }
                }

                // PASO 4: Verificar que se guardó correctamente
                const verifyGroup = await this.databaseService.getConfiguredGroup();
                if (verifyGroup && verifyGroup.groupId === groupId) {
                    logger.info(`✅ Verificación exitosa: Grupo persistido correctamente`);
                } else {
                    logger.warn(`⚠️ Verificación: El grupo puede no haberse guardado correctamente`);
                }

                res.json({
                    success: true,
                    message: 'Grupo configurado correctamente',
                    groupId,
                    groupName: groupName || 'Sin nombre'
                });

            } catch (error) {
                logger.error('Error configurando grupo:', error);
                
                // Si el error indica conexión cerrada, aún así intentar guardar en BD
                if (error.message && error.message.includes('closed state')) {
                    logger.warn('Error de conexión detectado, pero la configuración debería estar guardada en BD');
                }
                
                res.status(500).json({
                    success: false,
                    error: 'Error configurando grupo',
                    details: error.message
                });
            }
        });

        // Forzar reconexión de WhatsApp
        this.app.post('/api/reconnect', async (req, res) => {
            try {
                const { secretKey } = req.body;

                // Validar secret key
                if (secretKey !== process.env.API_SECRET_KEY) {
                    return res.status(401).json({
                        success: false,
                        error: 'Clave de API inválida'
                    });
                }

                logger.info('🔄 Reconexión manual iniciada...');
                
                // Detener reconexión automática si está en curso
                this.whatsappService.stopReconnectionProcess();
                
                // Intentar reconectar
                await this.whatsappService.reconnect();
                
                res.json({
                    success: true,
                    message: 'Reconexión iniciada correctamente',
                    connected: this.whatsappService.isConnected()
                });

            } catch (error) {
                logger.error('Error en reconexión manual:', error);
                res.status(500).json({
                    success: false,
                    error: 'Error iniciando reconexión'
                });
            }
        });
    }

    /**
     * Configura el manejo de errores
     */
    setupErrorHandling() {
        // Error 404
        this.app.use('*', (req, res) => {
            res.status(404).json({
                success: false,
                error: 'Endpoint no encontrado'
            });
        });

        // Error handler global
        this.app.use((error, req, res, next) => {
            logger.error('Error no manejado:', error);
            res.status(500).json({
                success: false,
                error: 'Error interno del servidor'
            });
        });
    }

    /**
     * Inicia el servidor
     */
    async start() {
        try {
            // Inicializar base de datos
            await this.databaseService.initialize();
            
            // Cargar configuración de grupo desde la base de datos
            const configuredGroup = await this.databaseService.getConfiguredGroup();
            if (configuredGroup && configuredGroup.groupId) {
                process.env.WHATSAPP_GROUP_ID = configuredGroup.groupId;
                this.whatsappService.groupId = configuredGroup.groupId;
                logger.info(`📋 Configuración del grupo cargada al iniciar: ${configuredGroup.groupName || configuredGroup.groupId}`);
            } else {
                // Fallback al método anterior por compatibilidad
                const groupId = await this.databaseService.getGroupId();
                if (groupId) {
                    process.env.WHATSAPP_GROUP_ID = groupId;
                    this.whatsappService.groupId = groupId;
                }
            }

            // Inicializar WhatsApp
            await this.whatsappService.initialize();

            // Iniciar servidor
            this.app.listen(this.port, () => {
                logger.info(`🚀 Servidor Condo360 WhatsApp iniciado en puerto ${this.port}`);
                logger.info(`📱 Endpoint QR: http://localhost:${this.port}/api/qr`);
                logger.info(`💬 Endpoint mensajes: http://localhost:${this.port}/api/send-message`);
                logger.info(`🔍 Estado: http://localhost:${this.port}/api/status`);
            });

            // Iniciar sincronización periódica de configuración
            this.startPeriodicSync();

        } catch (error) {
            logger.error('Error iniciando servidor:', error);
            process.exit(1);
        }
    }

    /**
     * Inicia la sincronización periódica de configuración del grupo
     * Esto asegura que la configuración siempre esté sincronizada entre BD y memoria
     */
    startPeriodicSync() {
        // Sincronizar cada 5 minutos
        const syncIntervalMs = 5 * 60 * 1000; // 5 minutos
        
        this.syncInterval = setInterval(async () => {
            try {
                // Leer configuración desde BD
                const configuredGroup = await this.databaseService.getConfiguredGroup();
                
                if (configuredGroup && configuredGroup.groupId) {
                    // Sincronizar con memoria
                    const needsSync = 
                        this.whatsappService.groupId !== configuredGroup.groupId ||
                        process.env.WHATSAPP_GROUP_ID !== configuredGroup.groupId;
                    
                    if (needsSync) {
                        logger.info(`🔄 Sincronizando configuración del grupo desde BD: ${configuredGroup.groupId}`);
                        this.whatsappService.groupId = configuredGroup.groupId;
                        process.env.WHATSAPP_GROUP_ID = configuredGroup.groupId;
                    }
                } else {
                    // Si no hay configuración en BD pero hay en memoria, intentar recuperarla
                    if (this.whatsappService.groupId) {
                        logger.warn('⚠️ Configuración en memoria pero no en BD, esto no debería pasar');
                    }
                }
            } catch (error) {
                logger.warn('Error en sincronización periódica:', error.message);
            }
        }, syncIntervalMs);
        
        logger.info(`🔄 Sincronización periódica iniciada (cada ${syncIntervalMs / 1000} segundos)`);
    }

    /**
     * Detiene la sincronización periódica
     */
    stopPeriodicSync() {
        if (this.syncInterval) {
            clearInterval(this.syncInterval);
            this.syncInterval = null;
            logger.info('🛑 Sincronización periódica detenida');
        }
    }

    /**
     * Detiene el servidor
     */
    async stop() {
        try {
            // Detener sincronización periódica
            this.stopPeriodicSync();
            
            await this.whatsappService.destroy();
            await this.databaseService.close();
            logger.info('Servidor detenido correctamente');
        } catch (error) {
            logger.error('Error deteniendo servidor:', error);
        }
    }
}

// Manejo de señales del sistema
process.on('SIGINT', async () => {
    logger.info('Recibida señal SIGINT, cerrando servidor...');
    await app.stop();
    process.exit(0);
});

process.on('SIGTERM', async () => {
    logger.info('Recibida señal SIGTERM, cerrando servidor...');
    await app.stop();
    process.exit(0);
});

// Iniciar aplicación
const app = new Condo360WhatsAppService();
app.start().catch(error => {
    logger.error('Error fatal:', error);
    process.exit(1);
});

module.exports = Condo360WhatsAppService;
