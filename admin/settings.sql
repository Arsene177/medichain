-- Create system_settings table
CREATE TABLE IF NOT EXISTS system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    appointment_duration INT NOT NULL DEFAULT 30,
    max_appointments_per_day INT NOT NULL DEFAULT 20,
    enable_waiting_list TINYINT(1) NOT NULL DEFAULT 1,
    enable_email_notifications TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create email_settings table
CREATE TABLE IF NOT EXISTS email_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    smtp_host VARCHAR(255) NOT NULL,
    smtp_port INT NOT NULL DEFAULT 587,
    smtp_username VARCHAR(255) NOT NULL,
    smtp_password VARCHAR(255) NOT NULL,
    smtp_encryption ENUM('tls', 'ssl', 'none') NOT NULL DEFAULT 'tls',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT INTO system_settings (id, appointment_duration, max_appointments_per_day, enable_waiting_list, enable_email_notifications)
VALUES (1, 30, 20, 1, 1)
ON DUPLICATE KEY UPDATE
    appointment_duration = VALUES(appointment_duration),
    max_appointments_per_day = VALUES(max_appointments_per_day),
    enable_waiting_list = VALUES(enable_waiting_list),
    enable_email_notifications = VALUES(enable_email_notifications);

INSERT INTO email_settings (id, smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption)
VALUES (1, 'smtp.gmail.com', 587, '', '', 'tls')
ON DUPLICATE KEY UPDATE
    smtp_host = VALUES(smtp_host),
    smtp_port = VALUES(smtp_port),
    smtp_username = VALUES(smtp_username),
    smtp_password = VALUES(smtp_password),
    smtp_encryption = VALUES(smtp_encryption); 