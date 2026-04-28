<?php
/**
 * Database Connection Configuration
 * GoodRussian Authentication Module
 */

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'goodrussian');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Get PDO database connection
 * 
 * @return PDO
 * @throws PDOException
 */
function db_connect()
{
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        error_log('Database connection error: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Initialize database schema
 * Creates users table if it doesn't exist
 */
function init_database()
{
    $db = db_connect();
    
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    language_level ENUM('A1','A2','B1','B2','C1','C2'),
    date_of_birth DATE,
    country VARCHAR(100),
    profession VARCHAR(100),
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login DATETIME,
    reset_token VARCHAR(255),
    reset_token_expires DATETIME,
    INDEX idx_email (email),
    INDEX idx_reset_token (reset_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

    try {
        $db->exec($sql);
    } catch (PDOException $e) {
        error_log('Error creating users table: ' . $e->getMessage());
    }
}
