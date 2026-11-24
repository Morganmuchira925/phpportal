<?php
/**
 * config/db.php
 * Dedicated database connection file (PDO + exceptions + native prepared statements).
 * Edit the four values below (or set environment variables on your host).
 */

define('DB_HOST', getenv('DB_HOST') ?: 'https://phpportal.infinityfree.io/');
define('DB_PORT', getenv('DB_PORT') ?: '3306'); 
define('DB_NAME', getenv('DB_NAME') ?: 'if0_43524598_php_portal');
define('DB_USER', getenv('DB_USER') ?: 'if0_43524598');
define('DB_PASS', getenv('DB_PASS') ?: 'vambv5E5tr8');

/**
 * Returns a single shared PDO connection.
 */
function getPDO(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,   // real prepared statements
            ]);
        } catch (PDOException $e) {
            // error_log('DB connection failed: ' . $e->getMessage());
            // http_response_code(500);
            // exit('Database connection failed. Check config/db.php.');
            exit($e->getMessage());
        }
    }
    return $pdo;
}
