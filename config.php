<?php
// config.php - Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'u344370922_beeguard');
define('DB_PASS', 'BeeGuard2026!');
define('DB_NAME', 'u344370922_beeguard');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8");

// Sync MySQL session timezone with PHP — Hostinger MySQL runs UTC by default
$conn->query("SET time_zone = '+08:00'");

// Timezone
date_default_timezone_set('Asia/Manila');

// Safe one-time migrations (try/catch so PHP 8.1+ mysqli exceptions never crash the app)
try { $conn->query("ALTER TABLE activity_log ADD COLUMN IF NOT EXISTS user_id INT DEFAULT NULL"); } catch (Throwable $e) {}
try { $conn->query("ALTER TABLE inspection_notes
    ADD COLUMN IF NOT EXISTS hive_condition ENUM('excellent','good','fair','poor','critical') NOT NULL DEFAULT 'good' AFTER inspection_date,
    ADD COLUMN IF NOT EXISTS colony_strength ENUM('strong','moderate','weak','queenless') NOT NULL DEFAULT 'moderate' AFTER hive_condition,
    ADD COLUMN IF NOT EXISTS observations TEXT DEFAULT NULL AFTER colony_strength,
    ADD COLUMN IF NOT EXISTS recommendations TEXT DEFAULT NULL AFTER observations"); } catch (Throwable $e) {}
try { $conn->query("CREATE TABLE IF NOT EXISTS group_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_created_at (created_at)
)"); } catch (Throwable $e) {}
try { $conn->query("CREATE TABLE IF NOT EXISTS chat_reads (
    user_id INT PRIMARY KEY,
    last_read_id INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)"); } catch (Throwable $e) {}

try { $conn->query("ALTER TABLE hives ADD COLUMN IF NOT EXISTS camera_index INT DEFAULT NULL"); } catch (Throwable $e) {}
try { $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS security_question VARCHAR(255) DEFAULT NULL"); } catch (Throwable $e) {}
try { $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS security_answer VARCHAR(255) DEFAULT NULL"); } catch (Throwable $e) {}
try { $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS contact_number VARCHAR(30) DEFAULT NULL"); } catch (Throwable $e) {}
try { $conn->query("ALTER TABLE users MODIFY COLUMN role ENUM('admin','user','beekeeper') DEFAULT 'user'"); } catch (Throwable $e) {}
try { $conn->query("ALTER TABLE user_settings ADD COLUMN IF NOT EXISTS last_inspection_seen DATETIME DEFAULT NULL"); } catch (Throwable $e) {}
try { $conn->query("ALTER TABLE user_settings ADD COLUMN IF NOT EXISTS last_alerts_seen DATETIME DEFAULT NULL"); } catch (Throwable $e) {}
try { $conn->query("CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_user (user_id)
)"); } catch (Throwable $e) {}

try { $conn->query("ALTER TABLE hives ADD COLUMN IF NOT EXISTS last_checked_at DATETIME DEFAULT NULL"); } catch (Throwable $e) {}
try { $conn->query("ALTER TABLE hives ADD COLUMN IF NOT EXISTS last_checked_by INT DEFAULT NULL"); } catch (Throwable $e) {}
try { $conn->query("ALTER TABLE hives ADD COLUMN IF NOT EXISTS camera_url VARCHAR(255) DEFAULT NULL"); } catch (Throwable $e) {}

// Error reporting — off in production
error_reporting(0);
ini_set('display_errors', 0);
?>
