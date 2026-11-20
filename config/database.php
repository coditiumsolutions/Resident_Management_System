<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));

/**
 * Load key=value pairs from .env into $_ENV and putenv().
 */
function loadEnv(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        $_ENV[$name] = $value;
        putenv($name . '=' . $value);
    }
}

loadEnv(ROOT_PATH . '/.env');

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Asia/Karachi');

define('APP_NAME', $_ENV['APP_NAME'] ?? 'Service Management');
define('APP_SHORT_NAME', $_ENV['APP_SHORT_NAME'] ?? 'Ready Plaza');
define('APP_ENV', $_ENV['APP_ENV'] ?? 'local');
define('APP_DEBUG', filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN));

/**
 * Detect public base URL path (empty when served from web root).
 */
function base_url(string $path = ''): string
{
    static $base = null;

    if ($base === null) {
        // Prefer deriving from filesystem so nested pages resolve to project root.
        $docRoot = isset($_SERVER['DOCUMENT_ROOT'])
            ? realpath($_SERVER['DOCUMENT_ROOT'])
            : false;
        $projectRoot = realpath(ROOT_PATH);

        if ($docRoot && $projectRoot && str_starts_with($projectRoot, $docRoot)) {
            $relative = str_replace('\\', '/', substr($projectRoot, strlen($docRoot)));
            $base = rtrim($relative, '/');
        } else {
            $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
            $dir = str_replace('\\', '/', dirname($script));
            $dir = ($dir === '/' || $dir === '\\' || $dir === '.') ? '' : rtrim($dir, '/');

            if (preg_match('#/modules/[^/]+$#', $dir) || str_ends_with($dir, '/dashboard')) {
                $dir = preg_replace('#/(dashboard|modules/[^/]+)$#', '', $dir) ?? '';
            }

            $base = ($dir === '' || $dir === '/' || $dir === '.') ? '' : $dir;
        }
    }

    $path = ltrim($path, '/');
    if ($path === '') {
        return $base === '' ? '/' : $base . '/';
    }

    return ($base === '' ? '' : $base) . '/' . $path;
}

/**
 * Shared PDO connection.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
    $port = $_ENV['DB_PORT'] ?? '3306';
    $database = $_ENV['DB_DATABASE'] ?? 'db_rms';
    $username = $_ENV['DB_USERNAME'] ?? 'root';
    $password = $_ENV['DB_PASSWORD'] ?? '';
    $charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

    try {
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        error_log('DB connection failed: ' . $e->getMessage());

        if (APP_DEBUG) {
            throw $e;
        }

        http_response_code(500);
        exit('Database connection failed. Please contact the administrator.');
    }

    return $pdo;
}
