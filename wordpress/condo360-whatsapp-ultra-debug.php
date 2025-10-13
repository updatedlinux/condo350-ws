<?php
/**
 * Plugin Name: Condo360 WhatsApp Service (Ultra Debug)
 * Plugin URI: https://bonaventurecclub.com
 * Description: Plugin para conectar WhatsApp con el servicio Condo360 usando QR y gestionar grupos - VERSIÓN ULTRA DEBUG.
 * Version: 1.0.0-ultra-debug
 * Author: Condo360
 * Author URI: https://bonaventurecclub.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: condo360ws
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase principal del plugin Condo360 WhatsApp (Ultra Debug)
 */
class Condo360WhatsAppPluginUltraDebug {
    
    private $api_url;
    private $api_secret;
    
    /**
     * Constructor del plugin
     */
    public function __construct() {
        $this->api_url = get_option('condo360ws_api_url', 'https://wschat.bonaventurecclub.com');
        $this->api_secret = get_option('condo360ws_api_secret', 'condo360_whatsapp_secret_2025');
        
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_condo360ws_get_status', array($this, 'ajax_get_status'));
        add_action('wp_ajax_condo360ws_get_qr', array($this, 'ajax_get_qr'));
        
        // Shortcode
        add_shortcode('wa_connect_qr', array($this, 'shortcode_wa_connect_qr'));
        
        // Activar/desactivar plugin
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Inicialización del plugin
     */
    public function init() {
        // Crear tablas si no existen
        $this->create_tables();
    }
    
    /**
     * Cargar scripts y estilos
     */
    public function enqueue_scripts() {
        // Cargar jQuery
        wp_enqueue_script('jquery');
        
        // Cargar CSS
        wp_enqueue_style(
            'condo360ws-style',
            plugin_dir_url(__FILE__) . 'assets/style.css',
            array(),
            '1.0.0-ultra-debug'
        );
        
        // JavaScript inline para evitar problemas de carga
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('condo360ws_nonce');
        $api_url = $this->api_url;
        
        $script = "
        console.log('Condo360 WhatsApp: Script inline cargado');
        
        jQuery(document).ready(function($) {
            console.log('Condo360 WhatsApp: jQuery listo');
            
            // Verificar que el container existe
            const container = $('.condo360ws-container');
            console.log('Condo360 WhatsApp: Container encontrado:', container.length);
            
            if (container.length === 0) {
                console.log('Condo360 WhatsApp: No se encontró el container');
                return;
            }
            
            // Variables AJAX
            const ajaxUrl = '$ajax_url';
            const nonce = '$nonce';
            const apiUrl = '$api_url';
            
            console.log('Condo360 WhatsApp: AJAX URL:', ajaxUrl);
            console.log('Condo360 WhatsApp: Nonce:', nonce);
            console.log('Condo360 WhatsApp: API URL:', apiUrl);
            
            // Función para obtener estado
            function getStatus() {
                console.log('Condo360 WhatsApp: Obteniendo estado...');
                
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'condo360ws_get_status',
                        nonce: nonce
                    },
                    success: function(response) {
                        console.log('Condo360 WhatsApp: Respuesta recibida:', response);
                        
                        if (response.success && response.data) {
                            const status = response.data;
                            console.log('Condo360 WhatsApp: Estado procesado:', status);
                            
                            const statusDot = $('#condo360ws-status .status-dot');
                            const statusText = $('#condo360ws-status .status-text');
                            const qrContainer = $('#condo360ws-qr-container');
                            const connectedContainer = $('#condo360ws-connected');
                            const errorContainer = $('#condo360ws-error');
                            
                            // Limpiar estados anteriores
                            qrContainer.hide();
                            connectedContainer.hide();
                            errorContainer.hide();
                            
                            if (status.connected) {
                                console.log('Condo360 WhatsApp: Mostrando estado conectado');
                                statusDot.removeClass('checking disconnected').addClass('connected');
                                statusText.text('WhatsApp Conectado');
                                connectedContainer.show();
                            } else if (status.qrGenerated) {
                                console.log('Condo360 WhatsApp: Mostrando QR');
                                statusDot.removeClass('connected disconnected').addClass('checking');
                                statusText.text('Escanea el código QR');
                                qrContainer.show();
                                
                                // Cargar QR
                                loadQR();
                            } else {
                                console.log('Condo360 WhatsApp: Mostrando error');
                                statusDot.removeClass('connected checking').addClass('disconnected');
                                statusText.text('WhatsApp Desconectado');
                                errorContainer.show();
                            }
                        } else {
                            console.error('Condo360 WhatsApp: Respuesta no exitosa:', response);
                            $('#condo360ws-status .status-text').text('Error obteniendo estado');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Condo360 WhatsApp: Error AJAX:', xhr, status, error);
                        $('#condo360ws-status .status-text').text('Error: ' + error);
                    }
                });
            }
            
            // Función para cargar QR
            function loadQR() {
                console.log('Condo360 WhatsApp: Cargando QR...');
                
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'condo360ws_get_qr',
                        nonce: nonce
                    },
                    success: function(response) {
                        console.log('Condo360 WhatsApp: QR recibido:', response);
                        
                        if (response.success && response.data && response.data.qr) {
                            const qrImage = $('#condo360ws-qr-image');
                            const qrDataUrl = 'data:image/png;base64,' + response.data.qr;
                            qrImage.attr('src', qrDataUrl);
                            console.log('Condo360 WhatsApp: QR cargado en imagen');
                        } else {
                            console.error('Condo360 WhatsApp: Error cargando QR:', response);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Condo360 WhatsApp: Error cargando QR:', xhr, status, error);
                    }
                });
            }
            
            // Configurar botón de actualización
            $('#condo360ws-refresh-btn').on('click', function() {
                console.log('Condo360 WhatsApp: Botón actualizar clickeado');
                getStatus();
            });
            
            // Actualización inicial
            console.log('Condo360 WhatsApp: Iniciando actualización inicial...');
            getStatus();
            
            // Actualización automática cada 10 segundos
            setInterval(function() {
                console.log('Condo360 WhatsApp: Actualización automática...');
                getStatus();
            }, 10000);
            
            console.log('Condo360 WhatsApp: Inicialización completada');
        });
        ";
        
        wp_add_inline_script('jquery', $script);
    }
    
    /**
     * Crear tablas de base de datos
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabla de configuración
        $config_table = $wpdb->prefix . 'condo360ws_config';
        $config_sql = "CREATE TABLE IF NOT EXISTS $config_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            config_key varchar(100) NOT NULL,
            config_value text,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY config_key (config_key)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($config_sql);
    }
    
    /**
     * Shortcode para mostrar QR de conexión
     */
    public function shortcode_wa_connect_qr($atts) {
        // Solo mostrar a administradores
        if (!current_user_can('administrator')) {
            return '<div class="condo360ws-error">Solo administradores pueden ver este contenido</div>';
        }
        
        $atts = shortcode_atts(array(
            'show_status' => 'true',
            'auto_refresh' => 'true',
            'refresh_interval' => '10000'
        ), $atts);
        
        ob_start();
        ?>
        <div class="condo360ws-container" data-auto-refresh="<?php echo esc_attr($atts['auto_refresh']); ?>" data-refresh-interval="<?php echo esc_attr($atts['refresh_interval']); ?>">
            <div class="condo360ws-header">
                <h3>Estado de WhatsApp (ULTRA DEBUG)</h3>
                <div class="condo360ws-status-indicator" id="condo360ws-status">
                    <span class="status-dot"></span>
                    <span class="status-text">Verificando...</span>
                </div>
            </div>
            
            <div class="condo360ws-content">
                <div class="condo360ws-qr-container" id="condo360ws-qr-container" style="display: none;">
                    <h4>Escanea el código QR para conectar WhatsApp</h4>
                    <div class="qr-code-wrapper">
                        <img id="condo360ws-qr-image" src="" alt="Código QR de WhatsApp" />
                    </div>
                    <p class="qr-instructions">
                        1. Abre WhatsApp en tu teléfono<br>
                        2. Toca el menú (⋮) y selecciona "Dispositivos vinculados"<br>
                        3. Toca "Vincular un dispositivo"<br>
                        4. Escanea este código QR
                    </p>
                </div>
                
                <div class="condo360ws-connected" id="condo360ws-connected" style="display: none;">
                    <div class="success-icon">✓</div>
                    <h4>WhatsApp Conectado</h4>
                    <p>El servicio de WhatsApp está funcionando correctamente.</p>
                </div>
                
                <div class="condo360ws-error" id="condo360ws-error" style="display: none;">
                    <div class="error-icon">⚠</div>
                    <h4>Error de Conexión</h4>
                    <p id="condo360ws-error-message"></p>
                </div>
            </div>
            
            <div class="condo360ws-footer">
                <button type="button" id="condo360ws-refresh-btn" class="refresh-btn">
                    Actualizar Estado
                </button>
                <div class="last-updated">
                    Última actualización: <span id="condo360ws-last-update">--</span>
                </div>
            </div>
            
            <!-- Información de depuración -->
            <div style="margin-top: 20px; padding: 10px; background: #f0f0f0; border-radius: 4px; font-size: 12px;">
                <strong>ULTRA DEBUG INFO:</strong><br>
                API URL: <?php echo esc_html($this->api_url); ?><br>
                Plugin Version: 1.0.0-ultra-debug<br>
                WordPress Version: <?php echo get_bloginfo('version'); ?><br>
                PHP Version: <?php echo PHP_VERSION; ?><br>
                jQuery Version: <?php echo wp_script_is('jquery', 'registered') ? 'Registrado' : 'NO registrado'; ?><br>
                <div id="debug-console" style="margin-top: 10px; font-family: monospace; font-size: 11px;">
                    <!-- Los logs de JavaScript aparecerán aquí -->
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX: Obtener estado de WhatsApp
     */
    public function ajax_get_status() {
        check_ajax_referer('condo360ws_nonce', 'nonce');
        
        if (!current_user_can('administrator')) {
            wp_die('No tienes permisos para realizar esta acción');
        }
        
        $response = wp_remote_get($this->api_url . '/api/status', array(
            'timeout' => 10,
            'headers' => array(
                'Content-Type' => 'application/json'
            )
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => 'Error conectando con el servicio de WhatsApp',
                'error' => $response->get_error_message()
            ));
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($data && $data['success']) {
            wp_send_json_success($data['data']);
        } else {
            wp_send_json_error(array(
                'message' => 'Error obteniendo estado de WhatsApp',
                'error' => $data['error'] ?? 'Error desconocido'
            ));
        }
    }
    
    /**
     * AJAX: Obtener código QR
     */
    public function ajax_get_qr() {
        check_ajax_referer('condo360ws_nonce', 'nonce');
        
        if (!current_user_can('administrator')) {
            wp_die('No tienes permisos para realizar esta acción');
        }
        
        $response = wp_remote_get($this->api_url . '/api/qr', array(
            'timeout' => 10,
            'headers' => array(
                'Content-Type' => 'application/json'
            )
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => 'Error obteniendo código QR',
                'error' => $response->get_error_message()
            ));
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($data && $data['success']) {
            wp_send_json_success($data);
        } else {
            wp_send_json_error(array(
                'message' => 'Error generando código QR',
                'error' => $data['error'] ?? 'Error desconocido'
            ));
        }
    }
    
    /**
     * Activar plugin
     */
    public function activate() {
        $this->create_tables();
        
        // Configuración por defecto
        add_option('condo360ws_api_url', 'https://wschat.bonaventurecclub.com');
        add_option('condo360ws_api_secret', 'condo360_whatsapp_secret_2025');
        
        // Limpiar cache
        wp_cache_flush();
    }
    
    /**
     * Desactivar plugin
     */
    public function deactivate() {
        // Limpiar cache
        wp_cache_flush();
    }
}

// Inicializar plugin
new Condo360WhatsAppPluginUltraDebug();
