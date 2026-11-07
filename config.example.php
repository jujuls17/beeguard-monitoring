<?php
// config.example.php — Copy this to config.php and fill in your real credentials.
// config.php is in .gitignore and should NEVER be committed.

define('DB_HOST', 'your-mysql-host');       // e.g. localhost or Hostinger's DB host
define('DB_USER', 'your-db-username');
define('DB_PASS', 'your-db-password');
define('DB_NAME', 'your-db-name');          // e.g. u123456_beeguard

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed']));
}

$conn->set_charset("utf8mb4");

date_default_timezone_set('Asia/Manila');

// ── One-time migrations (safe to run on every boot) ───────────────────────────
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

// ── Production settings ───────────────────────────────────────────────────────
error_reporting(0);
ini_set('display_errors', 0);
?>
