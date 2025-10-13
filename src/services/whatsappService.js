/**
 * Servicio de WhatsApp usando Baileys
 * Maneja la conexión, QR, persistencia de sesión y envío de mensajes
 */

const { 
    default: makeWASocket, 
    DisconnectReason, 
    useMultiFileAuthState,
    fetchLatestBaileysVersion,
    makeCacheableSignalKeyStore
} = require('@whiskeysockets/baileys');
const qrcode = require('qrcode');
const fs = require('fs').promises;
const path = require('path');
const logger = require('../utils/logger');

class WhatsAppService {
    constructor() {
        this.sock = null;
        this.isConnected = false;
        this.isQRGenerated = false;
        this.qrCode = null;
        this.lastConnectionTime = null;
        this.sessionPath = process.env.WHATSAPP_SESSION_PATH || './sessions';
        this.reconnectInterval = null;
        this.qrRefreshInterval = null;
        
        // Configurar directorio de sesiones
        this.ensureSessionDirectory();
    }

    /**
     * Asegura que el directorio de sesiones existe
     */
    async ensureSessionDirectory() {
        try {
            await fs.mkdir(this.sessionPath, { recursive: true });
        } catch (error) {
            logger.error('Error creando directorio de sesiones:', error);
        }
    }

    /**
     * Inicializa el servicio de WhatsApp
     */
    async initialize() {
        try {
            logger.info('Inicializando servicio de WhatsApp...');
            await this.connect();
        } catch (error) {
            logger.error('Error inicializando WhatsApp:', error);
            throw error;
        }
    }

    /**
     * Conecta a WhatsApp usando Baileys
     */
    async connect() {
        try {
            const { state, saveCreds } = await useMultiFileAuthState(this.sessionPath);
            const { version, isLatest } = await fetchLatestBaileysVersion();
            
            logger.info(`Usando versión de Baileys: ${version.join('.')}, última: ${isLatest}`);

            this.sock = makeWASocket({
                version,
                printQRInTerminal: false,
                auth: state,
                browser: ['Condo360', 'Chrome', '1.0.0'],
                logger: logger,
                generateHighQualityLinkPreview: true,
                getMessage: async (key) => {
                    return {
                        conversation: 'Mensaje de Condo360'
                    };
                }
            });

            // Manejar eventos de conexión
            this.setupEventHandlers(saveCreds);

        } catch (error) {
            logger.error('Error conectando a WhatsApp:', error);
            throw error;
        }
    }

    /**
     * Configura los manejadores de eventos de WhatsApp
     */
    setupEventHandlers(saveCreds) {
        // Evento de credenciales guardadas
        this.sock.ev.on('creds.update', saveCreds);

        // Evento de conexión
        this.sock.ev.on('connection.update', async (update) => {
            const { connection, lastDisconnect, qr } = update;

            if (qr) {
                logger.info('Generando nuevo QR...');
                await this.generateQR(qr);
            }

            if (connection === 'close') {
                const shouldReconnect = (lastDisconnect?.error)?.output?.statusCode !== DisconnectReason.loggedOut;
                
                logger.info(`Conexión cerrada. Reconectar: ${shouldReconnect}`);
                
                this.isConnected = false;
                this.isQRGenerated = false;
                this.qrCode = null;

                if (shouldReconnect) {
                    this.scheduleReconnect();
                } else {
                    logger.info('Sesión cerrada. Se requiere nuevo QR.');
                }
            } else if (connection === 'open') {
                logger.info('✅ WhatsApp conectado exitosamente');
                this.isConnected = true;
                this.isQRGenerated = false;
                this.qrCode = null;
                this.lastConnectionTime = new Date();
                
                // Cancelar intervalos de reconexión y QR
                this.clearIntervals();
                
                // Obtener información del usuario
                const user = this.sock.user;
                if (user) {
                    logger.info(`Conectado como: ${user.name || user.id}`);
                }
            }
        });

        // Evento de mensajes recibidos (opcional, para logging)
        this.sock.ev.on('messages.upsert', (m) => {
            const msg = m.messages[0];
            if (!msg.key.fromMe && m.type === 'notify') {
                logger.info(`Mensaje recibido de ${msg.key.remoteJid}: ${msg.message?.conversation || 'Multimedia'}`);
            }
        });
    }

    /**
     * Genera el código QR en formato base64
     */
    async generateQR(qrString) {
        try {
            this.qrCode = await qrcode.toDataURL(qrString, {
                width: 300,
                margin: 2,
                color: {
                    dark: '#000000',
                    light: '#FFFFFF'
                }
            });
            
            this.isQRGenerated = true;
            logger.info('QR generado exitosamente');

            // Programar refresco del QR cada 10 segundos si no está conectado
            this.scheduleQRRefresh();

        } catch (error) {
            logger.error('Error generando QR:', error);
            throw error;
        }
    }

    /**
     * Programa el refresco automático del QR
     */
    scheduleQRRefresh() {
        if (this.qrRefreshInterval) {
            clearInterval(this.qrRefreshInterval);
        }

        this.qrRefreshInterval = setInterval(() => {
            if (!this.isConnected && this.sock) {
                logger.info('Refrescando QR...');
                // El QR se regenerará automáticamente si es necesario
            }
        }, parseInt(process.env.QR_REFRESH_INTERVAL) || 10000);
    }

    /**
     * Programa la reconexión automática
     */
    scheduleReconnect() {
        if (this.reconnectInterval) {
            clearInterval(this.reconnectInterval);
        }

        this.reconnectInterval = setInterval(async () => {
            if (!this.isConnected) {
                logger.info('Intentando reconectar...');
                try {
                    await this.connect();
                } catch (error) {
                    logger.error('Error en reconexión:', error);
                }
            }
        }, parseInt(process.env.RECONNECT_INTERVAL) || 30000);
    }

    /**
     * Limpia los intervalos programados
     */
    clearIntervals() {
        if (this.reconnectInterval) {
            clearInterval(this.reconnectInterval);
            this.reconnectInterval = null;
        }
        if (this.qrRefreshInterval) {
            clearInterval(this.qrRefreshInterval);
            this.qrRefreshInterval = null;
        }
    }

    /**
     * Obtiene el código QR actual
     */
    async getQRCode() {
        if (this.isConnected) {
            return null;
        }
        return this.qrCode;
    }

    /**
     * Envía un mensaje a un grupo específico
     */
    async sendMessageToGroup(groupId, message) {
        try {
            if (!this.isConnected || !this.sock) {
                throw new Error('WhatsApp no está conectado');
            }

            // Validar que el grupo existe
            const groupInfo = await this.sock.groupMetadata(groupId);
            if (!groupInfo) {
                throw new Error('Grupo no encontrado');
            }

            // Enviar mensaje
            const sentMessage = await this.sock.sendMessage(groupId, {
                text: message
            });

            logger.info(`Mensaje enviado al grupo ${groupInfo.subject}: ${message}`);

            return {
                success: true,
                messageId: sentMessage.key.id,
                groupName: groupInfo.subject
            };

        } catch (error) {
            logger.error('Error enviando mensaje:', error);
            return {
                success: false,
                error: error.message
            };
        }
    }

    /**
     * Obtiene información de un grupo
     */
    async getGroupInfo(groupId) {
        try {
            if (!this.isConnected || !this.sock) {
                throw new Error('WhatsApp no está conectado');
            }

            const groupInfo = await this.sock.groupMetadata(groupId);
            return {
                success: true,
                data: {
                    id: groupInfo.id,
                    subject: groupInfo.subject,
                    participants: groupInfo.participants.length,
                    creation: groupInfo.creation
                }
            };
        } catch (error) {
            logger.error('Error obteniendo información del grupo:', error);
            return {
                success: false,
                error: error.message
            };
        }
    }

    /**
     * Desconecta WhatsApp
     */
    async disconnect() {
        try {
            this.clearIntervals();
            
            if (this.sock) {
                await this.sock.logout();
                this.sock = null;
            }
            
            this.isConnected = false;
            this.isQRGenerated = false;
            this.qrCode = null;
            
            logger.info('WhatsApp desconectado');
        } catch (error) {
            logger.error('Error desconectando WhatsApp:', error);
        }
    }

    /**
     * Verifica si WhatsApp está conectado
     */
    isConnected() {
        return this.isConnected && this.sock !== null;
    }

    /**
     * Verifica si hay un QR generado
     */
    isQRGenerated() {
        return this.isQRGenerated && this.qrCode !== null;
    }

    /**
     * Obtiene la fecha de la última conexión
     */
    getLastConnectionTime() {
        return this.lastConnectionTime;
    }

    /**
     * Obtiene información del usuario conectado
     */
    getUserInfo() {
        if (!this.isConnected || !this.sock || !this.sock.user) {
            return null;
        }

        return {
            id: this.sock.user.id,
            name: this.sock.user.name,
            phone: this.sock.user.id.split('@')[0]
        };
    }
}

module.exports = WhatsAppService;
