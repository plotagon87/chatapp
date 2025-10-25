-- Migration 001: Create system_settings table and insert default settings
-- Safe to run independently. Does not DROP or ALTER other tables.

USE lan_chat_db;

CREATE TABLE IF NOT EXISTS system_settings (
    setting_id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO system_settings (setting_key, setting_value, setting_description) VALUES
('site_name', 'LAN Chat', 'The name of the chat application'),
('site_description', 'Local Area Network Chat Application', 'A short description of the application'),
('max_file_size', '10485760', 'Maximum allowed file size in bytes'),
('allowed_file_types', 'jpg,jpeg,png,pdf,doc,docx,txt,zip', 'Comma separated list of allowed file extensions'),
('user_registration', '1', 'Allow new user registrations (0 or 1)'),
('default_user_role', 'user', 'Default role for new users'),
('theme', 'light', 'Default theme (light or dark)')
ON DUPLICATE KEY UPDATE
    setting_value = VALUES(setting_value),
    setting_description = VALUES(setting_description),
    updated_at = NOW();
