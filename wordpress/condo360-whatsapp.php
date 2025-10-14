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

// Incluir el archivo principal de la lógica del plugin
require_once plugin_dir_path(__FILE__) . 'condo360-whatsapp-plugin.php';

// Inicializar plugin
new Condo360WhatsAppPlugin();