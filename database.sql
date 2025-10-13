-- Script SQL para crear las tablas de Condo360 WhatsApp Service
-- Ejecutar este script en la base de datos de WordPress

-- Tabla para configuración del servicio
CREATE TABLE IF NOT EXISTS `condo360ws_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_key` varchar(100) NOT NULL,
  `config_value` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `config_key` (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para logs de mensajes
CREATE TABLE IF NOT EXISTS `condo360ws_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `status` enum('sent','failed','pending') DEFAULT 'pending',
  `message_id` varchar(100),
  `error_message` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_group_id` (`group_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para logs de conexión
CREATE TABLE IF NOT EXISTS `condo360ws_connections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `status` enum('connected','disconnected','qr_generated','error') NOT NULL,
  `qr_code` text,
  `error_message` text,
  `user_info` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar configuración inicial
INSERT IGNORE INTO `condo360ws_config` (`config_key`, `config_value`) VALUES
('whatsapp_group_id', ''),
('api_secret_key', 'condo360_whatsapp_secret_2025'),
('service_status', 'active'),
('last_connection', ''),
('qr_expires_at', '');

-- Mostrar información de las tablas creadas
SELECT 'Tablas creadas exitosamente' as status;
SELECT TABLE_NAME, TABLE_ROWS, CREATE_TIME 
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME LIKE 'condo360ws_%';
