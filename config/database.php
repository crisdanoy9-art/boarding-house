<?php
// ============================================================
// DATABASE CONFIGURATION
// ============================================================

define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'boarding_house');
define('DB_USER', 'postgres');
define('DB_PASS', '2007');
define('DB_CHARSET', 'utf8');

// Application Settings
define('APP_NAME', 'BoardingHouse Pro');
define('APP_URL', 'http://localhost/boarding-house-system');
define('APP_VERSION', '1.0.0');

// Session Settings
define('SESSION_NAME', 'bh_session');
define('SESSION_LIFETIME', 3600); // 1 hour

// Security
define('CSRF_TOKEN_NAME', 'csrf_token');
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutes

/**
 * Get PDO Database Connection (Singleton Pattern)
 */
function getDB(): PDO {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME
            );
            
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            die(json_encode([
                'error' => 'Database connection failed. Please contact administrator.'
            ]));
        }
    }
    
    return $pdo;
}
