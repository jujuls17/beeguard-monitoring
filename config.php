<?php
// config.php - Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'bee_monitoring');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8");

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

try { $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS security_question VARCHAR(255) DEFAULT NULL"); } catch (Throwable $e) {}
try { $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS security_answer VARCHAR(255) DEFAULT NULL"); } catch (Throwable $e) {}
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

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
