<?php
/**
 * NESTORA.my - One-time installer.
 *
 * Run ONCE after configuring config/db_config.php:
 *   CLI:     php install.php
 *   Browser: https://yourdomain/install.php?key=INSTALL
 *
 * Creates all tables (schema.sql) and demo content (seed.sql),
 * then DELETE this file from the server for security.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db_config.php';

$isCli = PHP_SAPI === 'cli';

// Minimal guard for browser execution.
if (!$isCli && ($_GET['key'] ?? '') !== 'INSTALL') {
    http_response_code(403);
    exit('Forbidden. Append ?key=INSTALL to run the installer, then delete this file.');
}

function out(string $msg, bool $cli): void
{
    echo $cli ? $msg . "\n" : nl2br(htmlspecialchars($msg)) . "<br>";
}

try {
    // Connect without database to create it if missing.
    $root = new PDO(
        sprintf('mysql:host=%s;charset=%s', DB_HOST, DB_CHARSET),
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $root->exec(
        'CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', DB_NAME) . '`
         CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
    );
    out('Database ready: ' . DB_NAME, $isCli);

    $pdo = db();

    foreach (['schema.sql', 'seed.sql', 'phase2.sql', 'phase3.sql'] as $file) {
        $sql = file_get_contents(__DIR__ . '/database/' . $file);
        if ($sql === false) {
            throw new RuntimeException("Cannot read $file");
        }
        $pdo->exec($sql);
        out("Executed $file", $isCli);
    }

    out('', $isCli);
    out('Installation complete.', $isCli);
    out('Admin login: admin@nestora.my / NestoraAdmin123', $isCli);
    out('IMPORTANT: delete install.php now, then change the admin password.', $isCli);
} catch (Throwable $e) {
    http_response_code(500);
    out('Install failed: ' . $e->getMessage(), $isCli);
}
