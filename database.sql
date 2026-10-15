-- BeeGuard IoT Monitor Database
CREATE DATABASE IF NOT EXISTS bee_monitoring;
USE bee_monitoring;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(100) DEFAULT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    role ENUM('admin', 'user') DEFAULT 'user',
    account_status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    assigned_hive_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Hives table
CREATE TABLE IF NOT EXISTS hives (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    hive_id VARCHAR(50) UNIQUE,
    location VARCHAR(200),
    install_date DATE,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sensor data table
CREATE TABLE IF NOT EXISTS sensor_data (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hive_id INT NOT NULL,
    temperature DECIMAL(5,2),
    humidity DECIMAL(5,2),
    weight DECIMAL(6,2),
    co2 INT,
    sound_level DECIMAL(5,2),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hive_id) REFERENCES hives(id) ON DELETE CASCADE,
    INDEX idx_hive_timestamp (hive_id, timestamp)
);

-- Audio analysis table
CREATE TABLE IF NOT EXISTS audio_analysis (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hive_id INT NOT NULL,
    colony_status VARCHAR(50),
    confidence DECIMAL(5,2),
    dominant_freq DECIMAL(8,2),
    sound_level DECIMAL(5,2),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hive_id) REFERENCES hives(id) ON DELETE CASCADE,
    INDEX idx_hive_audio_timestamp (hive_id, timestamp)
);

-- Alerts table
CREATE TABLE IF NOT EXISTS alerts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hive_id INT NOT NULL,
    alert_type VARCHAR(50),
    severity ENUM('info', 'warning', 'critical') DEFAULT 'info',
    message TEXT,
    recommended_action TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hive_id) REFERENCES hives(id) ON DELETE CASCADE,
    INDEX idx_read_status (is_read, created_at)
);

-- Activity log table
CREATE TABLE IF NOT EXISTS activity_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hive_id INT,
    user_id INT DEFAULT NULL,
    event_type VARCHAR(50),
    details TEXT,
    severity ENUM('info', 'warning', 'critical', 'success') DEFAULT 'info',
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_timestamp (timestamp DESC)
);

-- Edge devices table
CREATE TABLE IF NOT EXISTS edge_devices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    device_name VARCHAR(100),
    device_id VARCHAR(50) UNIQUE,
    device_type VARCHAR(50),
    hive_id INT,
    firmware_version VARCHAR(20),
    cpu_usage DECIMAL(5,2),
    memory_usage DECIMAL(5,2),
    battery_level DECIMAL(5,2),
    status ENUM('online', 'offline', 'warning') DEFAULT 'offline',
    last_sync TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hive_id) REFERENCES hives(id) ON DELETE SET NULL
);

-- Detected (unregistered) ESP32 devices
CREATE TABLE IF NOT EXISTS detected_devices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    device_id VARCHAR(100) NOT NULL UNIQUE,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    times_seen INT DEFAULT 1
);

-- Inspection notes table
CREATE TABLE IF NOT EXISTS inspection_notes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hive_id INT NOT NULL,
    user_id INT NOT NULL,
    inspection_date DATE NOT NULL,
    hive_condition ENUM('excellent','good','fair','poor','critical') NOT NULL DEFAULT 'good',
    colony_strength ENUM('strong','moderate','weak','queenless') NOT NULL DEFAULT 'moderate',
    observations TEXT DEFAULT NULL,
    recommendations TEXT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hive_id) REFERENCES hives(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_hive_date (hive_id, inspection_date DESC)
);

-- Insert default admin user (password: beekeeper123)
INSERT INTO users (full_name, username, password, email, role, account_status) VALUES
('Administrator', 'admin', '$2y$10$8K1p/a0dL3LKzF5rTa9Au.oGskkwKEZQ9LmY7sWDy0RlFYMjMqE76', 'admin@beeguard.com', 'admin', 'active');

-- Default hive
INSERT INTO hives (name, hive_id, location, install_date, status) VALUES
('Hive Alpha', 'HIVE-001', 'North Field', '2026-01-15', 'active');

-- Assign ESP32-001 to Hive Alpha
INSERT INTO edge_devices (device_name, device_id, device_type, hive_id, firmware_version, status) VALUES
('Alpha Sensor Node', 'ESP32-001', 'ESP32', 1, 'v2.4.1', 'offline');

-- User settings table
CREATE TABLE IF NOT EXISTS user_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    email_notifications TINYINT(1) DEFAULT 1,
    push_notifications TINYINT(1) DEFAULT 1,
    alert_critical TINYINT(1) DEFAULT 1,
    alert_warnings TINYINT(1) DEFAULT 1,
    alert_informational TINYINT(1) DEFAULT 0,
    data_retention VARCHAR(20) DEFAULT '90',
    sync_interval INT DEFAULT 5,
    ml_confidence INT DEFAULT 85,
    auto_backup TINYINT(1) DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Sound uploads table (ML model training)
CREATE TABLE IF NOT EXISTS sound_uploads (
    id INT PRIMARY KEY AUTO_INCREMENT,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    classification ENUM('normal', 'environmental_stress', 'queenless', 'swarming', 'varroa_mite') NOT NULL,
    file_size_kb DECIMAL(10,2),
    uploaded_by INT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_classification (classification),
    INDEX idx_uploaded_at (uploaded_at DESC)
);

-- -------------------------------------------------------
-- Safe migrations: add missing columns to existing tables
-- (runs harmlessly if columns already exist)
-- -------------------------------------------------------
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS full_name VARCHAR(100) DEFAULT NULL AFTER id,
    ADD COLUMN IF NOT EXISTS account_status ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER role,
    ADD COLUMN IF NOT EXISTS assigned_hive_id INT DEFAULT NULL AFTER account_status;

ALTER TABLE activity_log
    ADD COLUMN IF NOT EXISTS user_id INT DEFAULT NULL AFTER hive_id;

ALTER TABLE inspection_notes
    ADD COLUMN IF NOT EXISTS hive_condition ENUM('excellent','good','fair','poor','critical') NOT NULL DEFAULT 'good' AFTER inspection_date,
    ADD COLUMN IF NOT EXISTS colony_strength ENUM('strong','moderate','weak','queenless') NOT NULL DEFAULT 'moderate' AFTER hive_condition,
    ADD COLUMN IF NOT EXISTS observations TEXT DEFAULT NULL AFTER colony_strength,
    ADD COLUMN IF NOT EXISTS recommendations TEXT DEFAULT NULL AFTER observations;

-- Group chat tables
CREATE TABLE IF NOT EXISTS group_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_created_at (created_at)
);

CREATE TABLE IF NOT EXISTS chat_reads (
    user_id INT PRIMARY KEY,
    last_read_id INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

SELECT 'Database created successfully!' AS status;
