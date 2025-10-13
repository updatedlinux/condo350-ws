<?php
/**
 * Plugin Name: Condo360 WhatsApp Service (Direct Test)
 * Plugin URI: https://bonaventurecclub.com
 * Description: Plugin para conectar WhatsApp con el servicio Condo360 usando QR y gestionar grupos - VERSIÓN PRUEBA DIRECTA.
 * Version: 1.0.0-direct-test
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
 * Clase principal del plugin Condo360 WhatsApp (Direct Test)
 */
class Condo360WhatsAppPluginDirectTest {
    
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
            '1.0.0-direct-test'
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
        
        // Probar comunicación directa con el API
        $api_test = $this->test_api_connection();
        
        ob_start();
        ?>
        <div class="condo360ws-container">
            <div class="condo360ws-header">
                <h3>Estado de WhatsApp (DIRECT TEST)</h3>
                <div class="condo360ws-status-indicator" id="condo360ws-status">
                    <span class="status-dot <?php echo $api_test['success'] ? 'connected' : 'disconnected'; ?>"></span>
                    <span class="status-text">
                        <?php echo $api_test['success'] ? 'API Conectado' : 'API Error'; ?>
                    </span>
                </div>
            </div>
            
            <div class="condo360ws-content">
                <?php if ($api_test['success']): ?>
                    <div class="condo360ws-connected">
                        <div class="success-icon">✓</div>
                        <h4>Comunicación con API Exitosa</h4>
                        <p>El plugin puede comunicarse correctamente con el API.</p>
                        
                        <?php if ($api_test['data']['connected']): ?>
                            <div style="margin-top: 20px; padding: 15px; background: #e8f5e8; border-radius: 4px;">
                                <strong>WhatsApp está conectado</strong>
                            </div>
                        <?php elseif ($api_test['data']['qrGenerated']): ?>
                            <div style="margin-top: 20px;">
                                <h5>QR Disponible:</h5>
                                <?php 
                                $qr_data = $this->get_qr_direct();
                                if ($qr_data): 
                                ?>
                                    <div class="qr-code-wrapper">
                                        <img src="data:image/png;base64,<?php echo esc_attr($qr_data); ?>" alt="Código QR de WhatsApp" style="max-width: 250px;" />
                                    </div>
                                <?php else: ?>
                                    <p>Error obteniendo QR</p>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 4px;">
                                <strong>WhatsApp desconectado y sin QR</strong>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="condo360ws-error">
                        <div class="error-icon">⚠</div>
                        <h4>Error de Comunicación con API</h4>
                        <p><strong>Error:</strong> <?php echo esc_html($api_test['error']); ?></p>
                        <p><strong>URL:</strong> <?php echo esc_html($this->api_url . '/api/status'); ?></p>
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
            
            <!-- Información de depuración detallada -->
            <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 4px; font-size: 12px; border: 1px solid #dee2e6;">
                <strong>DIRECT TEST INFO:</strong><br>
                API URL: <?php echo esc_html($this->api_url); ?><br>
                Plugin Version: 1.0.0-direct-test<br>
                WordPress Version: <?php echo get_bloginfo('version'); ?><br>
                PHP Version: <?php echo PHP_VERSION; ?><br>
                <br>
                <strong>Prueba de Conexión:</strong><br>
                <?php if ($api_test['success']): ?>
                    ✅ Conexión exitosa<br>
                    <?php if (isset($api_test['data'])): ?>
                        Conectado: <?php echo $api_test['data']['connected'] ? 'Sí' : 'No'; ?><br>
                        QR Generado: <?php echo $api_test['data']['qrGenerated'] ? 'Sí' : 'No'; ?><br>
                        Grupo ID: <?php echo esc_html($api_test['data']['groupId'] ?? 'No configurado'); ?><br>
                    <?php endif; ?>
                <?php else: ?>
                    ❌ Error de conexión<br>
                    Error: <?php echo esc_html($api_test['error']); ?><br>
                <?php endif; ?>
                <br>
                <strong>Prueba Manual:</strong><br>
                <a href="<?php echo esc_url($this->api_url . '/api/status'); ?>" target="_blank" style="color: #007cba;">
                    Abrir API Status en nueva pestaña
                </a><br>
                <a href="<?php echo esc_url($this->api_url . '/api/qr'); ?>" target="_blank" style="color: #007cba;">
                    Abrir API QR en nueva pestaña
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Probar conexión directa con el API
     */
    private function test_api_connection() {
        $url = $this->api_url . '/api/status';
        
        // Probar con wp_remote_get
        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
            )
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => 'wp_remote_get error: ' . $response->get_error_message()
            );
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($http_code !== 200) {
            return array(
                'success' => false,
                'error' => "HTTP Error $http_code: " . $body
            );
        }
        
        $data = json_decode($body, true);
        
        if (!$data) {
            return array(
                'success' => false,
                'error' => 'Invalid JSON response: ' . $body
            );
        }
        
        if (!$data['success']) {
            return array(
                'success' => false,
                'error' => 'API Error: ' . ($data['error'] ?? 'Unknown error')
            );
        }
        
        return array(
            'success' => true,
            'data' => $data['data']
        );
    }
    
    /**
     * Obtener QR directamente del API
     */
    private function get_qr_direct() {
        $url = $this->api_url . '/api/qr';
        
        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
            )
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($http_code !== 200) {
            return false;
        }
        
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
        
        $test = $this->test_api_connection();
        
        if ($test['success']) {
            wp_send_json_success($test['data']);
        } else {
            wp_send_json_error(array(
                'message' => $test['error']
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
        
        $qr_data = $this->get_qr_direct();
        
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
new Condo360WhatsAppPluginDirectTest();
