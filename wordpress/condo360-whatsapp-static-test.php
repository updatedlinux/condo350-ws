<?php
/**
 * Plugin Name: Condo360 WhatsApp Service (Static Test)
 * Plugin URI: https://bonaventurecclub.com
 * Description: Plugin para conectar WhatsApp con el servicio Condo360 usando QR y gestionar grupos - VERSIÓN ESTÁTICA.
 * Version: 1.0.0-static-test
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
 * Clase principal del plugin Condo360 WhatsApp (Static Test)
 */
class Condo360WhatsAppPluginStaticTest {
    
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
            '1.0.0-static-test'
        );
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
        
        // Obtener estado directamente del API
        $status = $this->get_api_status();
        $qr_data = null;
        
        if ($status && !$status['connected'] && $status['qrGenerated']) {
            $qr_data = $this->get_api_qr();
        }
        
        ob_start();
        ?>
        <div class="condo360ws-container">
            <div class="condo360ws-header">
                <h3>Estado de WhatsApp (STATIC TEST)</h3>
                <div class="condo360ws-status-indicator" id="condo360ws-status">
                    <span class="status-dot <?php echo $status['connected'] ? 'connected' : ($status['qrGenerated'] ? 'checking' : 'disconnected'); ?>"></span>
                    <span class="status-text">
                        <?php 
                        if ($status['connected']) {
                            echo 'WhatsApp Conectado';
                        } elseif ($status['qrGenerated']) {
                            echo 'Escanea el código QR';
                        } else {
                            echo 'WhatsApp Desconectado';
                        }
                        ?>
                    </span>
                </div>
            </div>
            
            <div class="condo360ws-content">
                <?php if ($status['connected']): ?>
                    <div class="condo360ws-connected">
                        <div class="success-icon">✓</div>
                        <h4>WhatsApp Conectado</h4>
                        <p>El servicio de WhatsApp está funcionando correctamente.</p>
                    </div>
                <?php elseif ($status['qrGenerated'] && $qr_data): ?>
                    <div class="condo360ws-qr-container">
                        <h4>Escanea el código QR para conectar WhatsApp</h4>
                        <div class="qr-code-wrapper">
                            <img src="data:image/png;base64,<?php echo esc_attr($qr_data); ?>" alt="Código QR de WhatsApp" />
                        </div>
                        <p class="qr-instructions">
                            1. Abre WhatsApp en tu teléfono<br>
                            2. Toca el menú (⋮) y selecciona "Dispositivos vinculados"<br>
                            3. Toca "Vincular un dispositivo"<br>
                            4. Escanea este código QR
                        </p>
                    </div>
                <?php else: ?>
                    <div class="condo360ws-error">
                        <div class="error-icon">⚠</div>
                        <h4>Error de Conexión</h4>
                        <p>No se pudo obtener el estado de WhatsApp o generar el código QR.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="condo360ws-footer">
                <button type="button" onclick="location.reload();" class="refresh-btn">
                    Actualizar Estado
                </button>
                <div class="last-updated">
                    Última actualización: <?php echo date('H:i:s'); ?>
                </div>
            </div>
            
            <!-- Información de depuración -->
            <div style="margin-top: 20px; padding: 10px; background: #f0f0f0; border-radius: 4px; font-size: 12px;">
                <strong>STATIC TEST INFO:</strong><br>
                API URL: <?php echo esc_html($this->api_url); ?><br>
                Plugin Version: 1.0.0-static-test<br>
                WordPress Version: <?php echo get_bloginfo('version'); ?><br>
                PHP Version: <?php echo PHP_VERSION; ?><br>
                Estado del API: <?php echo $status ? 'Conectado' : 'Error'; ?><br>
                <?php if ($status): ?>
                    Conectado: <?php echo $status['connected'] ? 'Sí' : 'No'; ?><br>
                    QR Generado: <?php echo $status['qrGenerated'] ? 'Sí' : 'No'; ?><br>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Obtener estado del API directamente
     */
    private function get_api_status() {
        $response = wp_remote_get($this->api_url . '/api/status', array(
            'timeout' => 10,
            'headers' => array(
                'Content-Type' => 'application/json'
            )
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($data && $data['success']) {
            return $data['data'];
        }
        
        return false;
    }
    
    /**
     * Obtener QR del API directamente
     */
    private function get_api_qr() {
        $response = wp_remote_get($this->api_url . '/api/qr', array(
            'timeout' => 10,
            'headers' => array(
                'Content-Type' => 'application/json'
            )
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($data && $data['success'] && $data['qr']) {
            return $data['qr'];
        }
        
        return false;
    }
    
    /**
     * AJAX: Obtener estado de WhatsApp
     */
    public function ajax_get_status() {
        check_ajax_referer('condo360ws_nonce', 'nonce');
        
        if (!current_user_can('administrator')) {
            wp_die('No tienes permisos para realizar esta acción');
        }
        
        $status = $this->get_api_status();
        
        if ($status) {
            wp_send_json_success($status);
        } else {
            wp_send_json_error(array(
                'message' => 'Error obteniendo estado de WhatsApp'
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
        
        $qr_data = $this->get_api_qr();
        
        if ($qr_data) {
            wp_send_json_success(array('qr' => $qr_data));
        } else {
            wp_send_json_error(array(
                'message' => 'Error generando código QR'
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
new Condo360WhatsAppPluginStaticTest();
