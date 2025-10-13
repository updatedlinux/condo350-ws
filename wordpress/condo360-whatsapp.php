<?php
/**
 * Plugin Name: Condo360 WhatsApp Service
 * Plugin URI: https://bonaventurecclub.com
 * Description: Plugin para conectar WhatsApp con el servicio Condo360 usando QR y gestionar grupos.
 * Version: 1.0.0
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

// Definir constantes del plugin
define('CONDO360WS_VERSION', '1.0.0');
define('CONDO360WS_PLUGIN_FILE', __FILE__);
define('CONDO360WS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CONDO360WS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CONDO360WS_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Clase principal del plugin Condo360 WhatsApp
 */
class Condo360WhatsAppPlugin {
    
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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_condo360ws_get_status', array($this, 'ajax_get_status'));
        add_action('wp_ajax_condo360ws_get_qr', array($this, 'ajax_get_qr'));
        add_action('wp_ajax_condo360ws_get_groups', array($this, 'ajax_get_groups'));
        add_action('wp_ajax_condo360ws_set_group', array($this, 'ajax_set_group'));
        add_action('wp_ajax_condo360ws_send_message', array($this, 'ajax_send_message'));
        
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
        // Cargar traducciones
        load_plugin_textdomain('condo360ws', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Crear tablas si no existen
        $this->create_tables();
    }
    
    /**
     * Cargar scripts y estilos
     */
    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_style(
            'condo360ws-style',
            CONDO360WS_PLUGIN_URL . 'assets/style.css',
            array(),
            CONDO360WS_VERSION
        );
        wp_enqueue_script(
            'condo360ws-script',
            CONDO360WS_PLUGIN_URL . 'assets/script.js',
            array('jquery'),
            CONDO360WS_VERSION,
            true
        );
        
        // Variables para JavaScript
        wp_localize_script('condo360ws-script', 'condo360ws_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('condo360ws_nonce'),
            'api_url' => $this->api_url,
            'refresh_interval' => 10000, // 10 segundos
            'strings' => array(
                'connected' => __('WhatsApp Conectado', 'condo360ws'),
                'disconnected' => __('WhatsApp Desconectado', 'condo360ws'),
                'scan_qr' => __('Escanea el código QR para conectar WhatsApp', 'condo360ws'),
                'qr_expired' => __('El código QR ha expirado. Actualizando...', 'condo360ws'),
                'error_loading' => __('Error cargando estado de WhatsApp', 'condo360ws'),
                'admin_only' => __('Solo administradores pueden ver este contenido', 'condo360ws')
            )
        ));
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
        
        // Tabla de mensajes
        $messages_table = $wpdb->prefix . 'condo360ws_messages';
        $messages_sql = "CREATE TABLE IF NOT EXISTS $messages_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            group_id varchar(100) NOT NULL,
            message text NOT NULL,
            status enum('sent','failed','pending') DEFAULT 'pending',
            message_id varchar(100),
            error_message text,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_group_id (group_id),
            KEY idx_status (status),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        
        // Tabla de conexiones
        $connections_table = $wpdb->prefix . 'condo360ws_connections';
        $connections_sql = "CREATE TABLE IF NOT EXISTS $connections_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            status enum('connected','disconnected','qr_generated','error') NOT NULL,
            qr_code text,
            error_message text,
            user_info text,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($config_sql);
        dbDelta($messages_sql);
        dbDelta($connections_sql);
    }
    
    /**
     * Shortcode para mostrar QR de conexión
     */
    public function shortcode_wa_connect_qr($atts) {
        // Solo mostrar a administradores
        if (!current_user_can('administrator')) {
            return '<div class="condo360ws-error">' . __('Solo administradores pueden ver este contenido', 'condo360ws') . '</div>';
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
                <h3><?php _e('Estado de WhatsApp', 'condo360ws'); ?></h3>
                <div class="condo360ws-status-indicator" id="condo360ws-status">
                    <span class="status-dot"></span>
                    <span class="status-text"><?php _e('Verificando...', 'condo360ws'); ?></span>
                </div>
            </div>
            
            <div class="condo360ws-content">
                <div class="condo360ws-qr-container" id="condo360ws-qr-container" style="display: none;">
                    <h4><?php _e('Escanea el código QR para conectar WhatsApp', 'condo360ws'); ?></h4>
                    <div class="qr-code-wrapper">
                        <img id="condo360ws-qr-image" src="" alt="Código QR de WhatsApp" />
                    </div>
                    <p class="qr-instructions">
                        <?php _e('1. Abre WhatsApp en tu teléfono', 'condo360ws'); ?><br>
                        <?php _e('2. Toca el menú (⋮) y selecciona "Dispositivos vinculados"', 'condo360ws'); ?><br>
                        <?php _e('3. Toca "Vincular un dispositivo"', 'condo360ws'); ?><br>
                        <?php _e('4. Escanea este código QR', 'condo360ws'); ?>
                    </p>
                </div>
                
                <div class="condo360ws-connected" id="condo360ws-connected" style="display: none;">
                    <div class="success-icon">✓</div>
                    <h4><?php _e('WhatsApp Conectado', 'condo360ws'); ?></h4>
                    <p><?php _e('El servicio de WhatsApp está funcionando correctamente.', 'condo360ws'); ?></p>
                    
                    <div class="groups-section">
                        <h5><?php _e('Grupos Disponibles', 'condo360ws'); ?></h5>
                        <button type="button" id="condo360ws-load-groups-btn" class="load-groups-btn">
                            <?php _e('Cargar Grupos', 'condo360ws'); ?>
                        </button>
                        
                        <div class="groups-container" id="condo360ws-groups-container" style="display: none;">
                            <div class="groups-loading" id="condo360ws-groups-loading" style="display: none;">
                                <div class="loading-spinner"></div>
                                <span><?php _e('Cargando grupos...', 'condo360ws'); ?></span>
                            </div>
                            
                            <div class="groups-list" id="condo360ws-groups-list">
                                <!-- Los grupos se cargarán aquí dinámicamente -->
                            </div>
                            
                            <div class="selected-group" id="condo360ws-selected-group" style="display: none;">
                                <h6><?php _e('Grupo Seleccionado:', 'condo360ws'); ?></h6>
                                <div class="selected-group-info">
                                    <span class="group-name"></span>
                                    <span class="group-id"></span>
                                </div>
                                <button type="button" id="condo360ws-set-group-btn" class="set-group-btn">
                                    <?php _e('Configurar como Grupo de Destino', 'condo360ws'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="condo360ws-error" id="condo360ws-error" style="display: none;">
                    <div class="error-icon">⚠</div>
                    <h4><?php _e('Error de Conexión', 'condo360ws'); ?></h4>
                    <p id="condo360ws-error-message"></p>
                </div>
            </div>
            
            <div class="condo360ws-footer">
                <button type="button" id="condo360ws-refresh-btn" class="refresh-btn">
                    <?php _e('Actualizar Estado', 'condo360ws'); ?>
                </button>
                <div class="last-updated">
                    <?php _e('Última actualización:', 'condo360ws'); ?> <span id="condo360ws-last-update">--</span>
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
            wp_die(__('No tienes permisos para realizar esta acción', 'condo360ws'));
        }
        
        $response = wp_remote_get($this->api_url . '/api/status', array(
            'timeout' => 10,
            'headers' => array(
                'Content-Type' => 'application/json'
            )
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => __('Error conectando con el servicio de WhatsApp', 'condo360ws'),
                'error' => $response->get_error_message()
            ));
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($data && $data['success']) {
            wp_send_json_success($data['data']);
        } else {
            wp_send_json_error(array(
                'message' => __('Error obteniendo estado de WhatsApp', 'condo360ws'),
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
            wp_die(__('No tienes permisos para realizar esta acción', 'condo360ws'));
        }
        
        $response = wp_remote_get($this->api_url . '/api/qr', array(
            'timeout' => 10,
            'headers' => array(
                'Content-Type' => 'application/json'
            )
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => __('Error obteniendo código QR', 'condo360ws'),
                'error' => $response->get_error_message()
            ));
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($data && $data['success']) {
            wp_send_json_success($data);
        } else {
            wp_send_json_error(array(
                'message' => __('Error generando código QR', 'condo360ws'),
                'error' => $data['error'] ?? 'Error desconocido'
            ));
        }
    }
    
    /**
     * AJAX: Obtener grupos disponibles
     */
    public function ajax_get_groups() {
        check_ajax_referer('condo360ws_nonce', 'nonce');
        
        if (!current_user_can('administrator')) {
            wp_die(__('No tienes permisos para realizar esta acción', 'condo360ws'));
        }
        
        $response = wp_remote_get($this->api_url . '/api/groups', array(
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json'
            )
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => __('Error obteniendo grupos de WhatsApp', 'condo360ws'),
                'error' => $response->get_error_message()
            ));
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($data && $data['success']) {
            wp_send_json_success($data['data']);
        } else {
            wp_send_json_error(array(
                'message' => __('Error obteniendo grupos', 'condo360ws'),
                'error' => $data['error'] ?? 'Error desconocido'
            ));
        }
    }
    
    /**
     * AJAX: Configurar grupo de destino
     */
    public function ajax_set_group() {
        check_ajax_referer('condo360ws_nonce', 'nonce');
        
        if (!current_user_can('administrator')) {
            wp_die(__('No tienes permisos para realizar esta acción', 'condo360ws'));
        }
        
        $groupId = sanitize_text_field($_POST['groupId'] ?? '');
        
        if (empty($groupId)) {
            wp_send_json_error(array(
                'message' => __('ID de grupo requerido', 'condo360ws')
            ));
        }
        
        $response = wp_remote_post($this->api_url . '/api/set-group', array(
            'timeout' => 10,
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'groupId' => $groupId,
                'secretKey' => $this->api_secret
            ))
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => __('Error configurando grupo', 'condo360ws'),
                'error' => $response->get_error_message()
            ));
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($data && $data['success']) {
            wp_send_json_success(array(
                'message' => __('Grupo configurado correctamente', 'condo360ws'),
                'groupId' => $data['groupId']
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Error configurando grupo', 'condo360ws'),
                'error' => $data['error'] ?? 'Error desconocido'
            ));
        }
    }

    /**
     * AJAX: Enviar mensaje
     */
    public function ajax_send_message() {
        check_ajax_referer('condo360ws_nonce', 'nonce');
        
        if (!current_user_can('administrator')) {
            wp_die(__('No tienes permisos para realizar esta acción', 'condo360ws'));
        }
        
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        
        if (empty($message)) {
            wp_send_json_error(array(
                'message' => __('El mensaje no puede estar vacío', 'condo360ws')
            ));
        }
        
        $response = wp_remote_post($this->api_url . '/api/send-message', array(
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'message' => $message,
                'secretKey' => $this->api_secret
            ))
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => __('Error enviando mensaje', 'condo360ws'),
                'error' => $response->get_error_message()
            ));
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($data && $data['success']) {
            wp_send_json_success(array(
                'message' => __('Mensaje enviado correctamente', 'condo360ws'),
                'messageId' => $data['messageId'] ?? null
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Error enviando mensaje', 'condo360ws'),
                'error' => $data['error'] ?? 'Error desconocido'
            ));
        }
    }
    
    /**
     * Agregar menú de administración
     */
    public function add_admin_menu() {
        add_options_page(
            __('Condo360 WhatsApp', 'condo360ws'),
            __('WhatsApp Service', 'condo360ws'),
            'manage_options',
            'condo360ws-settings',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Página de administración
     */
    public function admin_page() {
        if (isset($_POST['submit'])) {
            update_option('condo360ws_api_url', sanitize_url($_POST['api_url']));
            update_option('condo360ws_api_secret', sanitize_text_field($_POST['api_secret']));
            echo '<div class="notice notice-success"><p>' . __('Configuración guardada', 'condo360ws') . '</p></div>';
        }
        
        $api_url = get_option('condo360ws_api_url', 'https://wschat.bonaventurecclub.com');
        $api_secret = get_option('condo360ws_api_secret', 'condo360_whatsapp_secret_2025');
        ?>
        <div class="wrap">
            <h1><?php _e('Configuración de Condo360 WhatsApp Service', 'condo360ws'); ?></h1>
            
            <form method="post" action="">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('URL del API', 'condo360ws'); ?></th>
                        <td>
                            <input type="url" name="api_url" value="<?php echo esc_attr($api_url); ?>" class="regular-text" />
                            <p class="description"><?php _e('URL base del servicio de WhatsApp (ej: https://wschat.bonaventurecclub.com)', 'condo360ws'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Clave Secreta del API', 'condo360ws'); ?></th>
                        <td>
                            <input type="text" name="api_secret" value="<?php echo esc_attr($api_secret); ?>" class="regular-text" />
                            <p class="description"><?php _e('Clave secreta para autenticar las peticiones al API', 'condo360ws'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <h2><?php _e('Uso del Shortcode', 'condo360ws'); ?></h2>
            <p><?php _e('Usa el shortcode <code>[wa_connect_qr]</code> en cualquier página o entrada para mostrar el estado de WhatsApp y el código QR.', 'condo360ws'); ?></p>
            
            <h3><?php _e('Parámetros disponibles:', 'condo360ws'); ?></h3>
            <ul>
                <li><code>show_status="true"</code> - <?php _e('Mostrar estado de conexión', 'condo360ws'); ?></li>
                <li><code>auto_refresh="true"</code> - <?php _e('Actualizar automáticamente', 'condo360ws'); ?></li>
                <li><code>refresh_interval="10000"</code> - <?php _e('Intervalo de actualización en milisegundos', 'condo360ws'); ?></li>
            </ul>
            
            <h3><?php _e('Ejemplo:', 'condo360ws'); ?></h3>
            <code>[wa_connect_qr show_status="true" auto_refresh="true" refresh_interval="10000"]</code>
        </div>
        <?php
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
new Condo360WhatsAppPlugin();
