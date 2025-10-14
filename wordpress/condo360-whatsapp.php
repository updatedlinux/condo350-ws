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
        add_action('wp_ajax_condo360ws_get_groups', array($this, 'ajax_get_groups'));
        add_action('wp_ajax_condo360ws_set_group', array($this, 'ajax_set_group'));
        
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
                <h3>Estado de WhatsApp</h3>
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
                                <div id="groups-section" style="margin-top: 15px;">
                                    <h5>Grupos Disponibles:</h5>
                                    <div id="groups-loading" style="text-align: center; padding: 20px;">
                                        <div class="spinner"></div>
                                        <p>Cargando grupos...</p>
                                    </div>
                                    <div id="groups-list" style="display: none;">
                                        <!-- Los grupos se cargarán aquí -->
                                    </div>
                                </div>
                            </div>
                        <?php elseif ($api_test['data']['qrGenerated']): ?>
                            <div style="margin-top: 20px;">
                                <h5>Escanea este código QR con WhatsApp:</h5>
                                <?php 
                                $qr_data = $this->get_qr_direct();
                                if ($qr_data): 
                                ?>
                                    <div class="qr-code-wrapper">
                                        <img src="data:image/png;base64,<?php echo esc_attr($qr_data); ?>" alt="Código QR de WhatsApp" style="max-width: 300px; border: 2px solid #ddd; border-radius: 8px;" />
                                    </div>
                                    <p style="margin-top: 10px; font-size: 14px; color: #666;">
                                        <strong>Instrucciones:</strong><br>
                                        1. Abre WhatsApp en tu teléfono<br>
                                        2. Ve a Configuración > Dispositivos vinculados<br>
                                        3. Toca "Vincular un dispositivo"<br>
                                        4. Escanea este código QR
                                    </p>
                                <?php else: ?>
                                    <p>Error obteniendo QR</p>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 4px;">
                                <strong>WhatsApp desconectado y sin QR</strong>
                                <p>Esperando conexión...</p>
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
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Función para cargar grupos
            function loadGroups() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'condo360ws_get_groups',
                        nonce: '<?php echo wp_create_nonce('condo360ws_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success && response.data.groups) {
                            displayGroups(response.data.groups);
                        } else {
                            $('#groups-loading').html('<p style="color: red;">Error cargando grupos: ' + (response.data || 'Error desconocido') + '</p>');
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#groups-loading').html('<p style="color: red;">Error AJAX: ' + error + '</p>');
                    }
                });
            }
            
            // Función para mostrar grupos
            function displayGroups(groups) {
                var html = '<div class="groups-list">';
                
                if (groups.length === 0) {
                    html += '<p>No se encontraron grupos.</p>';
                } else {
                    html += '<p>Selecciona un grupo para enviar mensajes:</p>';
                    html += '<div class="groups-grid">';
                    
                    groups.forEach(function(group) {
                        html += '<div class="group-item" data-group-id="' + group.id + '">';
                        html += '<div class="group-name">' + group.name + '</div>';
                        html += '<div class="group-id">ID: ' + group.id + '</div>';
                        html += '<div class="group-participants">' + group.participantsCount + ' participantes</div>';
                        html += '</div>';
                    });
                    
                    html += '</div>';
                }
                
                html += '</div>';
                
                $('#groups-loading').hide();
                $('#groups-list').html(html).show();
                
                // Agregar evento click a los grupos
                $('.group-item').on('click', function() {
                    var groupId = $(this).data('group-id');
                    var groupName = $(this).find('.group-name').text();
                    
                    if (confirm('¿Deseas seleccionar el grupo "' + groupName + '" como destino para los mensajes?')) {
                        selectGroup(groupId, groupName);
                    }
                });
            }
            
            // Función para seleccionar grupo
            function selectGroup(groupId, groupName) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'condo360ws_set_group',
                        group_id: groupId,
                        nonce: '<?php echo wp_create_nonce('condo360ws_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Grupo "' + groupName + '" seleccionado correctamente.\nID: ' + groupId);
                            location.reload();
                        } else {
                            alert('Error seleccionando grupo: ' + (response.data || 'Error desconocido'));
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('Error AJAX: ' + error);
                    }
                });
            }
            
            // Cargar grupos si WhatsApp está conectado
            <?php if ($api_test['success'] && $api_test['data']['connected']): ?>
                loadGroups();
            <?php endif; ?>
            
            // Auto-refresh cada 10 segundos si no está conectado
            <?php if (!$api_test['success'] || !$api_test['data']['connected']): ?>
                setInterval(function() {
                    location.reload();
                }, 10000);
            <?php endif; ?>
        });
        </script>
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
     * Obtener grupos directamente del API
     */
    private function get_groups_direct() {
        $url = $this->api_url . '/api/groups';
        
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
        
        if ($data && $data['success'] && isset($data['data']['groups'])) {
            return $data['data']['groups'];
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
     * AJAX: Obtener grupos de WhatsApp
     */
    public function ajax_get_groups() {
        check_ajax_referer('condo360ws_nonce', 'nonce');
        
        if (!current_user_can('administrator')) {
            wp_die('No tienes permisos para realizar esta acción');
        }
        
        $groups = $this->get_groups_direct();
        
        if ($groups !== false) {
            wp_send_json_success(array('groups' => $groups));
        } else {
            wp_send_json_error(array(
                'message' => 'Error obteniendo grupos'
            ));
        }
    }
    
    /**
     * AJAX: Establecer grupo seleccionado
     */
    public function ajax_set_group() {
        check_ajax_referer('condo360ws_nonce', 'nonce');
        
        if (!current_user_can('administrator')) {
            wp_die('No tienes permisos para realizar esta acción');
        }
        
        $group_id = sanitize_text_field($_POST['group_id']);
        
        if (empty($group_id)) {
            wp_send_json_error(array(
                'message' => 'ID de grupo requerido'
            ));
        }
        
        // Guardar en la base de datos
        global $wpdb;
        $config_table = $wpdb->prefix . 'condo360ws_config';
        
        $result = $wpdb->replace(
            $config_table,
            array(
                'config_key' => 'whatsapp_group_id',
                'config_value' => $group_id,
                'updated_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s')
        );
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => 'Grupo seleccionado correctamente',
                'group_id' => $group_id
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Error guardando grupo en base de datos'
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
