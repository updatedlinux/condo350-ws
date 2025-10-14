const { Client, LocalAuth, MessageMedia } = require('whatsapp-web.js');
const QRCode = require('qrcode');
const fs = require('fs');
const path = require('path');
const logger = require('../utils/logger');

/**
 * Servicio de WhatsApp usando whatsapp-web.js
 * Más estable que Baileys y con mejor mantenimiento
 */
class WhatsAppService {
    constructor() {
        this.client = null;
        this._isConnected = false;
        this._isQRGenerated = false;
        this.qrCode = null;
        this.sessionPath = path.join(__dirname, '../../sessions');
        this.groupId = process.env.WHATSAPP_GROUP_ID || '';
        
        // Configurar cliente con autenticación local
        this.setupClient();
    }

    /**
     * Configura el cliente de WhatsApp con whatsapp-web.js
     */
    setupClient() {
        try {
            logger.info('Configurando cliente de WhatsApp...');
            
            // Generar directorio de sesión único con timestamp
            const timestamp = Date.now();
            const uniqueSessionPath = path.join(__dirname, '../../sessions', `session-${timestamp}`);
            
            // Crear directorio único si no existe
            if (!fs.existsSync(uniqueSessionPath)) {
                fs.mkdirSync(uniqueSessionPath, { recursive: true });
            }
            
            logger.info(`Directorio de sesiones: ${uniqueSessionPath}`);

            // Limpiar sesiones antiguas
            this.cleanupOldSessions();

            this.client = new Client({
                authStrategy: new LocalAuth({
                    clientId: 'condo360-whatsapp',
                    dataPath: uniqueSessionPath
                }),
                puppeteer: {
                    headless: true,
                    executablePath: '/usr/lib64/chromium-browser/chromium-browser',
                    args: [
                        '--no-sandbox',
                        '--disable-setuid-sandbox',
                        '--disable-dev-shm-usage',
                        '--disable-accelerated-2d-canvas',
                        '--no-first-run',
                        '--no-zygote',
                        '--disable-gpu',
                        '--disable-web-security',
                        '--disable-features=VizDisplayCompositor',
                        '--disable-background-timer-throttling',
                        '--disable-backgrounding-occluded-windows',
                        '--disable-renderer-backgrounding',
                        '--single-process',
                        '--disable-software-rasterizer'
                    ],
                    timeout: 60000
                },
                webVersionCache: {
                    type: 'remote',
                    remotePath: 'https://raw.githubusercontent.com/wppconnect-team/wa-version/main/html/2.2412.54.html'
                }
            });

            this.setupEventHandlers();
            logger.info('Cliente de WhatsApp configurado correctamente');
        } catch (error) {
            logger.error('Error configurando cliente de WhatsApp:', error);
            logger.error('Detalles del error de configuración:', {
                message: error.message,
                stack: error.stack,
                name: error.name
            });
            throw error;
        }
    }

    /**
     * Asegura que el directorio de sesiones existe
     */
    async ensureSessionDirectory() {
        try {
            fs.mkdirSync(this.sessionPath, { recursive: true });
            logger.info(`Directorio de sesiones: ${this.sessionPath}`);
        } catch (error) {
            logger.error('Error creando directorio de sesiones:', error);
            throw error;
        }
    }

    /**
     * Configura los event handlers del cliente
     */
    setupEventHandlers() {
        // QR Code generado
        this.client.on('qr', async (qr) => {
            try {
                logger.info('🎯 QR RECIBIDO - Generando imagen...');
                await this.generateQR(qr);
            } catch (error) {
                logger.error('Error generando QR:', error);
            }
        });

        // Cliente listo
        this.client.on('ready', () => {
            logger.info('✅ WhatsApp conectado y listo!');
            this._isConnected = true;
            this._isQRGenerated = false;
            this.qrCode = null;
        });

        // Cliente autenticado
        this.client.on('authenticated', () => {
            logger.info('🔐 WhatsApp autenticado correctamente');
        });

        // Cliente desconectado
        this.client.on('disconnected', (reason) => {
            logger.warn(`❌ WhatsApp desconectado: ${reason}`);
            this._isConnected = false;
            this.qrCode = null;
        });

        // Error de autenticación
        this.client.on('auth_failure', (msg) => {
            logger.error('❌ Error de autenticación:', msg);
            this._isConnected = false;
            this.qrCode = null;
        });

        // Error general
        this.client.on('error', (error) => {
            logger.error('❌ Error en cliente WhatsApp:', error);
        });

        // Cambio de estado
        this.client.on('change_state', (state) => {
            logger.info(`📱 Estado de WhatsApp: ${state}`);
        });
    }

    /**
     * Genera imagen QR a partir del código QR
     */
    async generateQR(qrCode) {
        try {
            const qrImageBuffer = await QRCode.toBuffer(qrCode, {
                type: 'png',
                width: 300,
                margin: 2,
                color: {
                    dark: '#000000',
                    light: '#FFFFFF'
                }
            });

            this.qrCode = qrImageBuffer.toString('base64');
            this._isQRGenerated = true;
            
            logger.info('✅ QR generado exitosamente');
        } catch (error) {
            logger.error('Error generando QR:', error);
            throw error;
        }
    }

    /**
     * Inicializa el servicio de WhatsApp
     */
    async initialize() {
        try {
            logger.info('Inicializando servicio de WhatsApp con whatsapp-web.js...');
            
            // Si no hay cliente o fue destruido, crear uno nuevo
            if (!this.client) {
                this.setupClient();
            }
            
            // Inicializar el cliente
            await this.client.initialize();
            logger.info('Cliente de WhatsApp inicializado correctamente');
        } catch (error) {
            logger.error('Error inicializando WhatsApp:', error);
            logger.error('Detalles del error:', {
                message: error.message,
                stack: error.stack,
                name: error.name
            });
            throw error;
        }
    }

    /**
     * Verifica si WhatsApp está conectado
     */
    isConnected() {
        return this._isConnected && this.client && this.client.info;
    }

    /**
     * Verifica si el QR está generado
     */
    get isQRGenerated() {
        return this._isQRGenerated;
    }

    /**
     * Obtiene el código QR actual
     */
    getQRCode() {
        return this.qrCode;
    }

    /**
     * Envía un mensaje de texto a un grupo
     */
    async sendMessage(message, groupId = null) {
        try {
            if (!this.isConnected()) {
                throw new Error('WhatsApp no está conectado');
            }

            const targetGroupId = groupId || this.groupId;
            if (!targetGroupId) {
                throw new Error('ID de grupo no configurado');
            }

            // Asegurar que el ID tenga el formato correcto
            const formattedGroupId = targetGroupId.includes('@g.us') 
                ? targetGroupId 
                : `${targetGroupId}@g.us`;

            logger.info(`Enviando mensaje a grupo: ${formattedGroupId}`);
            
            const result = await this.client.sendMessage(formattedGroupId, message);
            
            logger.info('✅ Mensaje enviado correctamente');
            return {
                success: true,
                messageId: result.id._serialized,
                groupId: formattedGroupId
            };
        } catch (error) {
            logger.error('Error enviando mensaje:', error);
            throw error;
        }
    }

    /**
     * Obtiene la lista de grupos de WhatsApp
     */
    async getGroups() {
        try {
            if (!this.isConnected()) {
                throw new Error('WhatsApp no está conectado');
            }

            const chats = await this.client.getChats();
            const groups = [];

            for (const chat of chats) {
                if (chat.isGroup) {
                    // Usar información básica del chat (getGroupInfo no existe en whatsapp-web.js)
                    groups.push({
                        id: chat.id._serialized,
                        subject: chat.name || 'Grupo sin nombre',
                        participants: chat.participantsCount || 0,
                        creation: chat.createdAt || null,
                        description: chat.description || '',
                        isGroup: true,
                        isBroadcast: chat.id._serialized.includes('@broadcast')
                    });
                }
            }

            // Ordenar grupos por nombre
            groups.sort((a, b) => a.subject.localeCompare(b.subject));
            
            logger.info(`Se encontraron ${groups.length} grupos`);
            return groups;
        } catch (error) {
            logger.error('Error obteniendo grupos:', error.message);
            throw error;
        }
    }

    /**
     * Configura el ID del grupo objetivo
     */
    setGroupId(groupId) {
        this.groupId = groupId;
        process.env.WHATSAPP_GROUP_ID = groupId;
        logger.info(`Grupo objetivo configurado: ${groupId}`);
    }

    /**
     * Obtiene información del cliente
     */
    getClientInfo() {
        if (!this.isConnected()) {
            return null;
        }

        return {
            name: this.client.info?.pushname || 'Usuario',
            phone: this.client.info?.wid?._serialized || 'N/A',
            platform: this.client.info?.platform || 'N/A',
            isConnected: this.isConnected()
        };
    }

    /**
     * Limpia las sesiones almacenadas
     */
    async clearSessions() {
        try {
            const files = fs.readdirSync(this.sessionPath);
            for (const file of files) {
                const filePath = path.join(this.sessionPath, file);
                fs.unlinkSync(filePath);
                logger.info(`Sesión eliminada: ${file}`);
            }
            logger.info('Todas las sesiones eliminadas');
        } catch (error) {
            logger.error('Error limpiando sesiones:', error);
        }
    }

    /**
     * Limpia directorios de sesión antiguos (más de 1 hora)
     */
    cleanupOldSessions() {
        try {
            const sessionsBasePath = path.join(__dirname, '../../sessions');
            
            if (!fs.existsSync(sessionsBasePath)) {
                return;
            }
            
            const dirs = fs.readdirSync(sessionsBasePath);
            const oneHourAgo = Date.now() - (60 * 60 * 1000);
            
            for (const dir of dirs) {
                if (dir.startsWith('session-')) {
                    const dirPath = path.join(sessionsBasePath, dir);
                    const stats = fs.statSync(dirPath);
                    
                    if (stats.isDirectory() && stats.mtime.getTime() < oneHourAgo) {
                        try {
                            fs.rmSync(dirPath, { recursive: true, force: true });
                            logger.info(`Directorio de sesión antiguo eliminado: ${dir}`);
                        } catch (err) {
                            logger.warn(`No se pudo eliminar directorio antiguo: ${dir}`);
                        }
                    }
                }
            }
        } catch (error) {
            logger.error('Error limpiando sesiones antiguas:', error);
        }
    }
    
    /**
     * Cierra la conexión de WhatsApp y limpia la sesión
     */
    async destroy() {
        try {
            if (this.client) {
                await this.client.destroy();
                logger.info('Cliente de WhatsApp cerrado');
            }
            
            // Limpiar estado
            this._isConnected = false;
            this._isQRGenerated = false;
            this.qrCode = null;
            this.client = null;
            
            logger.info('Estado de WhatsApp limpiado');
            
        } catch (error) {
            logger.error('Error cerrando cliente:', error);
        }
    }
}

module.exports = WhatsAppService;