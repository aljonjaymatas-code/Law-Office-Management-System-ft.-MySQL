<?php

date_default_timezone_set('Asia/Manila');

$envPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
if (is_file($envPath) && is_readable($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if ($name === '' || getenv($name) !== false) {
            continue;
        }
        if (
            strlen($value) >= 2
            && (($value[0] === '"' && $value[strlen($value) - 1] === '"')
                || ($value[0] === "'" && $value[strlen($value) - 1] === "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
    }
}

$driver = strtolower(getenv('DB_DRIVER') ?: 'mysql');
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$database = getenv('DB_NAME') ?: 'law_office_db';
$username = getenv('DB_USERNAME') ?: 'root';
$password = getenv('DB_PASSWORD');

if ($driver !== 'mysql') {
    throw new RuntimeException('DB_DRIVER must be set to mysql.');
}
if (
    $host === ''
    || $database === ''
    || preg_match('/[;\r\n]/', $host . $database) === 1
) {
    throw new RuntimeException('DB_HOST and DB_NAME must contain valid MySQL connection values.');
}
if (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
    throw new RuntimeException('DB_PORT must be a valid TCP port.');
}

if ($username === '') {
    throw new RuntimeException('DB_USERNAME must not be empty.');
}

$password = $password === false ? '' : $password;
$dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET time_zone = '+08:00'");
} catch (PDOException $exception) {
    error_log('MySQL/MariaDB connection failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Database connection failed. Check that MySQL is running in XAMPP, the database credentials are correct, and the PDO_MYSQL driver is enabled.');
}
