<?php
/**
 * Plugin Name: Condo360 WhatsApp Service (Debug)
 * Plugin URI: https://bonaventurecclub.com
 * Description: Plugin para conectar WhatsApp con el servicio Condo360 usando QR y gestionar grupos - VERSIÓN DEBUG.
 * Version: 1.0.0-debug
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
 * Clase principal del plugin Condo360 WhatsApp (Debug)
 */
class Condo360WhatsAppPluginDebug {
    
    private $api_url;
    private $api_secret;
    
    /**
     * Constructor del plugin
     */
    public function __construct() {
        // Configuración básica
        $this->api_url = 'https://wschat.bonaventurecclub.com';
        $this->api_secret = 'condo360_whatsapp_secret_2025';
        
        // Solo agregar hooks esenciales
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_condo360ws_get_groups', array($this, 'ajax_get_groups'));
        add_action('wp_ajax_condo360ws_set_group_db', array($this, 'ajax_set_group_db'));
        
        // Shortcode simple
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
        // Solo cargar jQuery y CSS básico
        wp_enqueue_script('jquery');
        wp_enqueue_style(
            'condo360ws-style',
            plugin_dir_url(__FILE__) . 'assets/style.css',
            array(),
            '1.0.0-debug'
        );
        
        // Variables para JavaScript (incluyendo ajaxurl para frontend)
        wp_localize_script('jquery', 'condo360ws_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('condo360ws_nonce'),
            'api_url' => $this->api_url
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
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($config_sql);
    }
    
    /**
     * Shortcode para mostrar QR de conexión (versión simple)
     */
    public function shortcode_wa_connect_qr($atts) {
        // Solo mostrar a administradores
        if (!current_user_can('administrator')) {
            return '<div class="condo360ws-error">Solo administradores pueden ver este contenido</div>';
        }
        
        // Probar comunicación básica con el API
        $api_test = $this->test_api_connection();
        
        ob_start();
        ?>
        <div class="condo360ws-container">
            <div class="condo360ws-header">
                <h3>Estado de WhatsApp (DEBUG)</h3>
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
                                    <button type="button" id="load-groups-btn" style="background: #007cba; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; margin-bottom: 15px;">
                                        Cargar Grupos
                                    </button>
                                    <div id="groups-loading" style="text-align: center; padding: 20px; display: none;">
                                        <div style="border: 3px solid #f3f3f3; border-top: 3px solid #007cba; border-radius: 50%; width: 30px; height: 30px; animation: spin 1s linear infinite; margin: 0 auto 10px;"></div>
                                        <p>Cargando grupos...</p>
                                    </div>
                                    <div id="groups-list" style="display: none;">
                                        <!-- Los grupos se cargarán aquí -->
                                    </div>
                                    <div id="selected-group" style="margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 4px; display: none;">
                                        <h6>Grupo Seleccionado:</h6>
                                        <div id="selected-group-info"></div>
                                        <button type="button" id="set-group-btn" style="background: #28a745; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; margin-top: 10px;">
                                            Configurar como Grupo de Destino
                                        </button>
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
            
            <!-- Información de depuración -->
            <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 4px; font-size: 12px; border: 1px solid #dee2e6;">
                <strong>DEBUG INFO:</strong><br>
                Plugin Version: 1.0.0-debug<br>
                WordPress Version: <?php echo get_bloginfo('version'); ?><br>
                PHP Version: <?php echo PHP_VERSION; ?><br>
                API URL: <?php echo esc_html($this->api_url); ?><br>
                <br>
                <strong>Prueba de Conexión:</strong><br>
                <?php if ($api_test['success']): ?>
                    ✅ Conexión exitosa<br>
                    <?php if (isset($api_test['data'])): ?>
                        Conectado: <?php echo $api_test['data']['connected'] ? 'Sí' : 'No'; ?><br>
                        QR Generado: <?php echo $api_test['data']['qrGenerated'] ? 'Sí' : 'No'; ?><br>
                    <?php endif; ?>
                <?php else: ?>
                    ❌ Error de conexión<br>
                    Error: <?php echo esc_html($api_test['error']); ?><br>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Verificar que las variables AJAX estén disponibles
            if (typeof condo360ws_ajax === 'undefined') {
                console.error('Condo360 WhatsApp Debug: Variables AJAX no disponibles');
                $('#groups-loading').html('<p style="color: red;">Error: Variables AJAX no disponibles</p>').show();
                return;
            }
            
            console.log('Condo360 WhatsApp Debug: Variables AJAX disponibles', condo360ws_ajax);
            
            // Función para cargar grupos
            function loadGroups() {
                console.log('Condo360 WhatsApp Debug: Iniciando carga de grupos...');
                $('#load-groups-btn').prop('disabled', true).text('Cargando...');
                $('#groups-loading').show();
                $('#groups-list').hide();
                
                console.log('Condo360 WhatsApp Debug: Enviando petición AJAX...', {
                    url: condo360ws_ajax.ajax_url,
                    action: 'condo360ws_get_groups',
                    nonce: condo360ws_ajax.nonce
                });
                
                $.ajax({
                    url: condo360ws_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'condo360ws_get_groups',
                        nonce: condo360ws_ajax.nonce
                    },
                    success: function(response) {
                        console.log('Condo360 WhatsApp Debug: Respuesta AJAX recibida:', response);
                        $('#groups-loading').hide();
                        
                        if (response.success && response.data.groups) {
                            console.log('Condo360 WhatsApp Debug: Mostrando grupos:', response.data.groups.length);
                            displayGroups(response.data.groups);
                        } else {
                            console.error('Condo360 WhatsApp Debug: Error en respuesta:', response);
                            $('#groups-list').html('<p style="color: red;">Error cargando grupos: ' + (response.data || 'Error desconocido') + '</p>').show();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Condo360 WhatsApp Debug: Error AJAX:', xhr, status, error);
                        $('#groups-loading').hide();
                        $('#groups-list').html('<p style="color: red;">Error AJAX: ' + error + '</p>').show();
                    },
                    complete: function() {
                        console.log('Condo360 WhatsApp Debug: Petición AJAX completada');
                        $('#load-groups-btn').prop('disabled', false).text('Cargar Grupos');
                    }
                });
            }
            
            // Función para mostrar grupos
            function displayGroups(groups) {
                var html = '<div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px;">';
                
                if (groups.length === 0) {
                    html += '<p style="padding: 20px; text-align: center; color: #666;">No se encontraron grupos.</p>';
                } else {
                    html += '<p style="padding: 15px; margin: 0; background: #f8f9fa; border-bottom: 1px solid #ddd;">Selecciona un grupo para enviar mensajes:</p>';
                    
                    groups.forEach(function(group) {
                        var isBroadcast = group.id.includes('@broadcast');
                        var typeLabel = isBroadcast ? '📢 CANAL DE AVISOS' : '👥 GRUPO';
                        var typeColor = isBroadcast ? '#e74c3c' : '#3498db';
                        
                        html += '<div class="group-item" data-group-id="' + group.id + '" style="padding: 12px 15px; border-bottom: 1px solid #f0f0f0; cursor: pointer; transition: background 0.3s;" onmouseover="this.style.background=\'#f9f9f9\'" onmouseout="this.style.background=\'#fff\'">';
                        html += '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">';
                        html += '<div style="font-weight: bold; color: #333;">' + group.subject + '</div>';
                        html += '<div style="font-size: 10px; color: ' + typeColor + '; background: ' + typeColor + '20; padding: 2px 6px; border-radius: 10px; font-weight: bold;">' + typeLabel + '</div>';
                        html += '</div>';
                        html += '<div style="font-size: 12px; color: #666; font-family: monospace; background: #f5f5f5; padding: 4px 8px; border-radius: 3px; margin-bottom: 5px;">ID: ' + group.id + '</div>';
                        html += '<div style="font-size: 12px; color: #666;">' + group.participants + ' participantes</div>';
                        if (group.description && group.description.length > 0) {
                            html += '<div style="font-size: 11px; color: #888; margin-top: 5px; max-height: 40px; overflow: hidden;">' + group.description.substring(0, 100) + (group.description.length > 100 ? '...' : '') + '</div>';
                        }
                        html += '</div>';
                    });
                }
                
                html += '</div>';
                
                $('#groups-list').html(html).show();
                
                // Agregar evento click a los grupos
                $('.group-item').on('click', function() {
                    // Remover selección anterior
                    $('.group-item').css('background', '#fff');
                    
                    // Seleccionar grupo actual
                    $(this).css('background', '#e3f2fd');
                    
                    var groupId = $(this).data('group-id');
                    var groupName = $(this).find('div:first').text();
                    
                    // Mostrar información del grupo seleccionado
                    $('#selected-group-info').html(
                        '<div style="font-weight: bold; color: #333; margin-bottom: 5px;">' + groupName + '</div>' +
                        '<div style="font-size: 12px; color: #666; font-family: monospace; background: #e9ecef; padding: 4px 8px; border-radius: 3px;">ID: ' + groupId + '</div>'
                    );
                    $('#selected-group').show();
                });
            }
            
            // Función para configurar grupo
            function setGroup() {
                var groupId = $('#selected-group-info').find('div:last').text().replace('ID: ', '');
                var groupName = $('#selected-group-info').find('div:first').text();
                
                if (!groupId) {
                    alert('No hay grupo seleccionado');
                    return;
                }
                
                $('#set-group-btn').prop('disabled', true).text('Configurando...');
                
                $.ajax({
                    url: condo360ws_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'condo360ws_set_group_db',
                        group_id: groupId,
                        group_name: groupName,
                        nonce: condo360ws_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Grupo "' + groupName + '" configurado correctamente como destino.\nID: ' + groupId);
                            location.reload();
                        } else {
                            alert('Error configurando grupo: ' + (response.data || 'Error desconocido'));
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('Error AJAX: ' + error);
                    },
                    complete: function() {
                        $('#set-group-btn').prop('disabled', false).text('Configurar como Grupo de Destino');
                    }
                });
            }
            
            // Eventos
            $('#load-groups-btn').on('click', loadGroups);
            $('#set-group-btn').on('click', setGroup);
            
            // Cargar grupos automáticamente si WhatsApp está conectado
            <?php if ($api_test['success'] && $api_test['data']['connected']): ?>
                loadGroups();
            <?php endif; ?>
        });
        </script>
        
        <style>
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        </style>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Probar conexión básica con el API
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
     * AJAX: Obtener grupos de WhatsApp
     */
    public function ajax_get_groups() {
        check_ajax_referer('condo360ws_nonce', 'nonce');
        
        if (!current_user_can('administrator')) {
            wp_die('No tienes permisos para realizar esta acción');
        }
        
        $response = wp_remote_get($this->api_url . '/api/groups', array(
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
            )
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => 'Error obteniendo grupos de WhatsApp: ' . $response->get_error_message()
            ));
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($http_code !== 200) {
            wp_send_json_error(array(
                'message' => "HTTP Error $http_code: " . $body
            ));
        }
        
        $data = json_decode($body, true);
        
        if ($data && $data['success']) {
            wp_send_json_success(array('groups' => $data['data']));
        } else {
            wp_send_json_error(array(
                'message' => 'Error obteniendo grupos: ' . ($data['error'] ?? 'Error desconocido')
            ));
        }
    }
    
    /**
     * AJAX: Guardar grupo seleccionado en base de datos
     */
    public function ajax_set_group_db() {
        check_ajax_referer('condo360ws_nonce', 'nonce');
        
        if (!current_user_can('administrator')) {
            wp_die('No tienes permisos para realizar esta acción');
        }
        
        $group_id = sanitize_text_field($_POST['group_id'] ?? '');
        $group_name = sanitize_text_field($_POST['group_name'] ?? '');
        
        // Log para depuración
        error_log("Condo360 Debug: Intentando guardar grupo - ID: $group_id, Name: $group_name");
        
        if (empty($group_id)) {
            wp_send_json_error(array(
                'message' => 'ID de grupo requerido'
            ));
        }
        
        // Guardar en la base de datos
        global $wpdb;
        $config_table = $wpdb->prefix . 'condo360ws_config';
        
        // Verificar que la tabla existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$config_table'");
        if (!$table_exists) {
            error_log("Condo360 Debug: Tabla $config_table no existe, creándola...");
            $this->create_tables();
        }
        
        // Verificar estructura de la tabla
        $table_structure = $wpdb->get_results("DESCRIBE $config_table");
        error_log("Condo360 Debug: Estructura de tabla: " . print_r($table_structure, true));
        
        $result = $wpdb->replace(
            $config_table,
            array(
                'config_key' => 'whatsapp_group_id',
                'config_value' => $group_id,
                'updated_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s')
        );
        
        error_log("Condo360 Debug: Resultado de replace: " . print_r($result, true));
        error_log("Condo360 Debug: Último error de DB: " . $wpdb->last_error);
        
        if ($result !== false) {
            // Verificar que se guardó correctamente
            $saved_value = $wpdb->get_var($wpdb->prepare(
                "SELECT config_value FROM $config_table WHERE config_key = %s",
                'whatsapp_group_id'
            ));
            
            error_log("Condo360 Debug: Valor guardado verificado: $saved_value");
            
            wp_send_json_success(array(
                'message' => 'Grupo seleccionado correctamente',
                'group_id' => $group_id,
                'group_name' => $group_name,
                'saved_value' => $saved_value
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Error guardando grupo en base de datos: ' . $wpdb->last_error
            ));
        }
    }
    
    /**
     * Activar plugin
     */
    public function activate() {
        $this->create_tables();
        
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
new Condo360WhatsAppPluginDebug();
