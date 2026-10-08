<?php
declare(strict_types=1);

function load_local_env(): void {
    static $loaded = false;
    if ($loaded) return;
    $loaded = true;
    $file = dirname(__DIR__, 2).'/.env';
    if (!is_file($file)) return;
    $items = parse_ini_file($file, false, INI_SCANNER_RAW);
    if (!is_array($items)) throw new RuntimeException('Invalid local .env format.');
    foreach ($items as $key => $value) {
        if (!is_string($key) || !preg_match('/^[A-Z][A-Z0-9_]*$/', $key) || getenv($key) !== false) continue;
        putenv($key.'='.trim((string)$value, "\"'"));
    }
}
function db_env(string $name, string $fallback = ''): string {
    load_local_env();
    $value = getenv($name);
    return $value === false ? $fallback : (string)$value;
}
function database(): PDO {
    static $db = null;
    if ($db instanceof PDO) return $db;
    $host = db_env('DB_HOST', '127.0.0.1');
    $port = db_env('DB_PORT', '3306');
    $name = db_env('DB_NAME', 'company_training');
    $user = db_env('DB_USER', 'root');
    $password = db_env('DB_PASSWORD', '');
    if (!ctype_digit($port) || (int)$port < 1 || (int)$port > 65535) throw new RuntimeException('Invalid DB_PORT.');
    $dsn = 'mysql:host='.$host.';port='.$port.';dbname='.$name.';charset=utf8mb4';
    $db = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    return $db;
}
