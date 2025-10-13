<?php
/**
 * Plugin Name: Condo360 WhatsApp Service (Admin Test)
 * Plugin URI: https://bonaventurecclub.com
 * Description: Plugin para conectar WhatsApp con el servicio Condo360 usando QR y gestionar grupos - VERSIÓN ADMIN TEST.
 * Version: 1.0.0-admin-test
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
 * Clase principal del plugin Condo360 WhatsApp (Admin Test)
 */
class Condo360WhatsAppPluginAdminTest {
    
    private $api_url;
    private $api_secret;
    
    /**
     * Constructor del plugin
     */
    public function __construct() {
        $this->api_url = get_option('condo360ws_api_url', 'https://wschat.bonaventurecclub.com');
        $this->api_secret = get_option('condo360ws_api_secret', 'condo360_whatsapp_secret_2025');
        
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_condo360ws_test_api', array($this, 'ajax_test_api'));
        
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
     * Agregar menú de administración
     */
    public function add_admin_menu() {
        add_menu_page(
            'Condo360 WhatsApp Test',
            'WhatsApp Test',
            'manage_options',
            'condo360ws-test',
            array($this, 'admin_page'),
            'dashicons-whatsapp',
            30
        );
    }
    
    /**
     * Página de administración
     */
    public function admin_page() {
        if (isset($_POST['submit'])) {
            update_option('condo360ws_api_url', sanitize_url($_POST['api_url']));
            update_option('condo360ws_api_secret', sanitize_text_field($_POST['api_secret']));
            echo '<div class="notice notice-success"><p>Configuración guardada</p></div>';
            $this->api_url = get_option('condo360ws_api_url', 'https://wschat.bonaventurecclub.com');
            $this->api_secret = get_option('condo360ws_api_secret', 'condo360_whatsapp_secret_2025');
        }
        
        $api_url = $this->api_url;
        $api_secret = $this->api_secret;
        
        // Probar conexión
        $test_results = $this->test_all_endpoints();
        ?>
        <div class="wrap">
            <h1>Condo360 WhatsApp Service - Prueba de Administración</h1>
            
            <div style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; border-radius: 4px;">
                <h2>Configuración</h2>
                <form method="post" action="">
                    <table class="form-table">
                        <tr>
                            <th scope="row">URL del API</th>
                            <td>
                                <input type="url" name="api_url" value="<?php echo esc_attr($api_url); ?>" class="regular-text" />
                                <p class="description">URL base del servicio de WhatsApp</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Clave Secreta del API</th>
                            <td>
                                <input type="text" name="api_secret" value="<?php echo esc_attr($api_secret); ?>" class="regular-text" />
                                <p class="description">Clave secreta para autenticar las peticiones al API</p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Guardar Configuración'); ?>
                </form>
            </div>
            
            <div style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; border-radius: 4px;">
                <h2>Pruebas de Conexión</h2>
                
                <div style="margin-bottom: 20px;">
                    <button type="button" id="test-api-btn" class="button button-primary">Probar Conexión con API</button>
                    <span id="test-status" style="margin-left: 10px;"></span>
                </div>
                
                <div id="test-results" style="background: #f9f9f9; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 12px;">
                    <strong>Resultados de Prueba:</strong><br>
                    <?php foreach ($test_results as $test): ?>
                        <div style="margin: 5px 0;">
                            <strong><?php echo esc_html($test['name']); ?>:</strong>
                            <?php if ($test['success']): ?>
                                <span style="color: green;">✅ Exitoso</span>
                            <?php else: ?>
                                <span style="color: red;">❌ Error</span>
                            <?php endif; ?>
                            <br>
                            <span style="color: #666;"><?php echo esc_html($test['message']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; border-radius: 4px;">
                <h2>Información del Sistema</h2>
                <table class="widefat">
                    <tr>
                        <td><strong>WordPress Version:</strong></td>
                        <td><?php echo get_bloginfo('version'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>PHP Version:</strong></td>
                        <td><?php echo PHP_VERSION; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Plugin Version:</strong></td>
                        <td>1.0.0-admin-test</td>
                    </tr>
                    <tr>
                        <td><strong>API URL:</strong></td>
                        <td><?php echo esc_html($api_url); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Clave Secreta:</strong></td>
                        <td><?php echo esc_html($api_secret); ?></td>
                    </tr>
                </table>
            </div>
            
            <div style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; border-radius: 4px;">
                <h2>Enlaces de Prueba Directa</h2>
                <p>Prueba estos enlaces directamente en tu navegador:</p>
                <ul>
                    <li><a href="<?php echo esc_url($api_url . '/api/status'); ?>" target="_blank">API Status</a></li>
                    <li><a href="<?php echo esc_url($api_url . '/api/qr'); ?>" target="_blank">API QR</a></li>
                    <li><a href="<?php echo esc_url($api_url . '/health'); ?>" target="_blank">API Health</a></li>
                </ul>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#test-api-btn').on('click', function() {
                var btn = $(this);
                var status = $('#test-status');
                
                btn.prop('disabled', true).text('Probando...');
                status.text('Probando conexión...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'condo360ws_test_api',
                        nonce: '<?php echo wp_create_nonce('condo360ws_test'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            status.html('<span style="color: green;">✅ Conexión exitosa</span>');
                            $('#test-results').html('<strong>Resultados de Prueba:</strong><br>' + response.data.html);
                        } else {
                            status.html('<span style="color: red;">❌ Error: ' + response.data + '</span>');
                        }
                    },
                    error: function(xhr, status, error) {
                        status.html('<span style="color: red;">❌ Error AJAX: ' + error + '</span>');
                    },
                    complete: function() {
                        btn.prop('disabled', false).text('Probar Conexión con API');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Probar todos los endpoints
     */
    private function test_all_endpoints() {
        $results = array();
        
        // Probar /api/status
        $results[] = $this->test_endpoint('/api/status', 'API Status');
        
        // Probar /api/qr
        $results[] = $this->test_endpoint('/api/qr', 'API QR');
        
        // Probar /health
        $results[] = $this->test_endpoint('/health', 'API Health');
        
        return $results;
    }
    
    /**
     * Probar un endpoint específico
     */
    private function test_endpoint($endpoint, $name) {
        $url = $this->api_url . $endpoint;
        
        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
            )
        ));
        
        if (is_wp_error($response)) {
            return array(
                'name' => $name,
                'success' => false,
                'message' => 'wp_remote_get error: ' . $response->get_error_message()
            );
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($http_code !== 200) {
            return array(
                'name' => $name,
                'success' => false,
                'message' => "HTTP Error $http_code: " . substr($body, 0, 200)
            );
        }
        
        $data = json_decode($body, true);
        
        if (!$data) {
            return array(
                'name' => $name,
                'success' => false,
                'message' => 'Invalid JSON response: ' . substr($body, 0, 200)
            );
        }
        
        return array(
            'name' => $name,
            'success' => true,
            'message' => 'Response: ' . json_encode($data, JSON_PRETTY_PRINT)
        );
    }
    
    /**
     * AJAX: Probar API
     */
    public function ajax_test_api() {
        check_ajax_referer('condo360ws_test', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción');
        }
        
        $results = $this->test_all_endpoints();
        $html = '';
        
        foreach ($results as $test) {
            $html .= '<div style="margin: 5px 0;">';
            $html .= '<strong>' . esc_html($test['name']) . ':</strong>';
            if ($test['success']) {
                $html .= ' <span style="color: green;">✅ Exitoso</span>';
            } else {
                $html .= ' <span style="color: red;">❌ Error</span>';
            }
            $html .= '<br>';
            $html .= '<span style="color: #666;">' . esc_html($test['message']) . '</span>';
            $html .= '</div>';
        }
        
        wp_send_json_success(array('html' => $html));
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
new Condo360WhatsAppPluginAdminTest();
