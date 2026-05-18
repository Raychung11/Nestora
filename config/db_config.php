<?php
/**
 * NESTORA.my - Database & App Configuration
 *
 * On Hostinger shared hosting: edit the constants below with your
 * cPanel MySQL database name, user and password.
 * Values can also be supplied via environment variables (recommended
 * for local/dev) which take precedence over the defaults here.
 */

declare(strict_types=1);

// ---- Database credentials ------------------------------------------
define('DB_HOST', getenv('NESTORA_DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('NESTORA_DB_NAME') ?: 'nestora');
define('DB_USER', getenv('NESTORA_DB_USER') ?: 'root');
define('DB_PASS', getenv('NESTORA_DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// ---- App settings ---------------------------------------------------
define('APP_NAME', 'NESTORA');
define('APP_ENV', getenv('NESTORA_ENV') ?: 'production'); // production | development

// Base URL path (set to '' if site is at domain root, e.g. nestora.my)
define('BASE_URL', getenv('NESTORA_BASE_URL') ?: '');

// Absolute filesystem root of the project
define('APP_ROOT', dirname(__DIR__));

// Upload directories (relative to project root)
define('UPLOAD_PRODUCTS_DIR', APP_ROOT . '/uploads/products');
define('UPLOAD_BANNERS_DIR',  APP_ROOT . '/uploads/banners');
define('UPLOAD_PAYMENTS_DIR', APP_ROOT . '/uploads/payments');
define('UPLOAD_BRAND_DIR',    APP_ROOT . '/uploads/brand');

// ---- PDO connection (singleton) ------------------------------------
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_NAME,
        DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        if (APP_ENV === 'development') {
            http_response_code(500);
            exit('Database connection failed: ' . $e->getMessage());
        }
        http_response_code(500);
        exit('We are experiencing a temporary issue. Please try again shortly.');
    }

    return $pdo;
}
