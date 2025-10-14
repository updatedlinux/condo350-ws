/**
 * Servicio principal de WhatsApp para Condo360
 * Maneja la conexión con WhatsApp usando Baileys y expone endpoints REST
 */

const express = require('express');
const cors = require('cors');
const helmet = require('helmet');
const rateLimit = require('express-rate-limit');
require('dotenv').config();

const WhatsAppService = require('./services/whatsappService');
const DatabaseService = require('./services/databaseService');
const logger = require('./utils/logger');

class Condo360WhatsAppService {
    constructor() {
        this.app = express();
        this.port = process.env.PORT || 3003;
        this.whatsappService = new WhatsAppService();
        this.databaseService = new DatabaseService();
        
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

        // Rate limiting con configuración para proxy
        const limiter = rateLimit({
            windowMs: parseInt(process.env.RATE_LIMIT_WINDOW_MS) || 15 * 60 * 1000, // 15 minutos
            max: parseInt(process.env.RATE_LIMIT_MAX_REQUESTS) || 100,
            message: {
                error: 'Demasiadas solicitudes, intenta más tarde',
                retryAfter: Math.ceil((parseInt(process.env.RATE_LIMIT_WINDOW_MS) || 15 * 60 * 1000) / 1000)
            },
            standardHeaders: true,
            legacyHeaders: false,
            // Configurar para funcionar con proxy
            keyGenerator: (req) => {
                return req.ip || req.connection.remoteAddress;
            }
        });
        this.app.use('/api/', limiter);

        // Parse JSON
        this.app.use(express.json({ limit: '10mb' }));
        this.app.use(express.urlencoded({ extended: true }));
    }

    /**
     * Configura las rutas de la API
     */
    setupRoutes() {
        // Health check
        this.app.get('/health', (req, res) => {
            res.json({
                status: 'ok',
                timestamp: new Date().toISOString(),
                uptime: process.uptime(),
                whatsapp: {
                    connected: this.whatsappService.isConnected(),
                    qrGenerated: this.whatsappService.isQRGenerated
                }
            });
        });

        // Obtener estado de conexión
        this.app.get('/api/status', (req, res) => {
            try {
                const status = {
                    connected: this.whatsappService.isConnected(),
                    qrGenerated: this.whatsappService.isQRGenerated,
                    groupId: process.env.WHATSAPP_GROUP_ID,
                    clientInfo: this.whatsappService.getClientInfo()
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

                // Verificar conexión
                if (!this.whatsappService.isConnected()) {
                    return res.status(503).json({
                        success: false,
                        error: 'WhatsApp no está conectado. Escanea el QR primero.'
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
                // Verificar conexión
                if (!this.whatsappService.isConnected()) {
                    return res.status(503).json({
                        success: false,
                        error: 'WhatsApp no está conectado. Escanea el QR primero.'
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
                res.status(500).json({
                    success: false,
                    error: 'Error obteniendo grupos'
                });
            }
        });

        // Obtener grupo configurado desde la base de datos
        this.app.get('/api/configured-group', async (req, res) => {
            try {
                const configuredGroup = await this.databaseService.getConfiguredGroup();
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
                res.status(500).json({ success: false, error: 'Error interno del servidor' });
            }
        });

        // Configurar grupo de destino
        this.app.post('/api/set-group', async (req, res) => {
            try {
                const { groupId, secretKey } = req.body;

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

                // Guardar en base de datos
                await this.databaseService.setGroupId(groupId);
                
                // Actualizar variable de entorno
                process.env.WHATSAPP_GROUP_ID = groupId;

                res.json({
                    success: true,
                    message: 'Grupo configurado correctamente',
                    groupId
                });

            } catch (error) {
                logger.error('Error configurando grupo:', error);
                res.status(500).json({
                    success: false,
                    error: 'Error configurando grupo'
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
            
            // Cargar configuración de grupo
            const groupId = await this.databaseService.getGroupId();
            if (groupId) {
                process.env.WHATSAPP_GROUP_ID = groupId;
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

        } catch (error) {
            logger.error('Error iniciando servidor:', error);
            process.exit(1);
        }
    }

    /**
     * Detiene el servidor
     */
    async stop() {
        try {
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
