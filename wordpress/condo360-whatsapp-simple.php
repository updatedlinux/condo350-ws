<?php
/**
 * Plugin Name: Condo360 WhatsApp Service (Ultra Simple)
 * Plugin URI: https://bonaventurecclub.com
 * Description: Plugin ultra simple para probar conexión con API.
 * Version: 1.0.0-simple
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
 * Plugin ultra simple para probar
 */
class Condo360WhatsAppSimple {
    
    public function __construct() {
        add_shortcode('wa_connect_qr', array($this, 'simple_shortcode'));
    }
    
    public function simple_shortcode($atts) {
        // Solo mostrar a administradores
        if (!current_user_can('administrator')) {
            return '<div style="color: red;">Solo administradores pueden ver este contenido</div>';
        }
        
        $api_url = 'https://wschat.bonaventurecclub.com';
        
        // Probar conexión simple
        $response = wp_remote_get($api_url . '/api/status', array(
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            $error = $response->get_error_message();
            return '<div style="border: 1px solid red; padding: 20px; margin: 20px;">
                <h3>Error de Conexión</h3>
                <p><strong>Error:</strong> ' . esc_html($error) . '</p>
                <p><strong>URL:</strong> ' . esc_html($api_url . '/api/status') . '</p>
            </div>';
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($http_code !== 200) {
            return '<div style="border: 1px solid red; padding: 20px; margin: 20px;">
                <h3>Error HTTP</h3>
                <p><strong>Código:</strong> ' . $http_code . '</p>
                <p><strong>Respuesta:</strong> ' . esc_html($body) . '</p>
            </div>';
        }
        
        $data = json_decode($body, true);
        
        if (!$data) {
            return '<div style="border: 1px solid red; padding: 20px; margin: 20px;">
                <h3>Error de JSON</h3>
                <p><strong>Respuesta:</strong> ' . esc_html($body) . '</p>
            </div>';
        }
        
        return '<div style="border: 1px solid green; padding: 20px; margin: 20px;">
            <h3>✅ Conexión Exitosa</h3>
            <p><strong>Conectado:</strong> ' . ($data['data']['connected'] ? 'Sí' : 'No') . '</p>
            <p><strong>QR Generado:</strong> ' . ($data['data']['qrGenerated'] ? 'Sí' : 'No') . '</p>
            <p><strong>Grupo ID:</strong> ' . esc_html($data['data']['groupId'] ?? 'No configurado') . '</p>
            <p><strong>Última actualización:</strong> ' . date('H:i:s') . '</p>
        </div>';
    }
}

// Inicializar plugin
new Condo360WhatsAppSimple();
