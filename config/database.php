<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('CORE_HR_DB_HOST');
    $port = getenv('CORE_HR_DB_PORT');
    $name = getenv('CORE_HR_DB_NAME');
    $user = getenv('CORE_HR_DB_USER');
    $pass = getenv('CORE_HR_DB_PASS');
    $host = is_string($host) ? trim($host) : '';
    $port = is_string($port) && trim($port) !== '' ? trim($port) : '3306';
    $name = is_string($name) ? trim($name) : '';
    $user = is_string($user) ? trim($user) : '';
    $pass = is_string($pass) ? $pass : '';

    if ($host === '' || $name === '' || $user === '') {
        if (function_exists('security_log')) {
            security_log('Database configuration error: CORE_HR_DB_HOST, CORE_HR_DB_NAME, or CORE_HR_DB_USER is missing.');
        } else {
            error_log('Database configuration error.');
        }
        http_response_code(500);
        exit('Database configuration error.');
    }

    try {
        $pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (Throwable $exception) {
        if (function_exists('security_log')) {
            security_log('Database connection failed.');
        } else {
            error_log('Database connection failed.');
        }
        http_response_code(500);
        exit('Database configuration error.');
    }

    return $pdo;
}
