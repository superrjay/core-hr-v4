<?php
declare(strict_types=1);

function security_env(string $key, string $default = ''): string
{
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }
    $value = trim((string) $value);
    return $value === '' ? $default : $value;
}

function security_config(): array
{
    $threshold = security_env('RECAPTCHA_THRESHOLD', '0.5');
    $lifetime = security_env('SESSION_LIFETIME', '120');
    $otpTtl = security_env('OTP_TTL_MINUTES', '5');
    $otpEnforce = strtolower(security_env('OTP_ENFORCE', '1'));
    $sessionName = security_env('SESSION_NAME', 'sems_session');
    if (!preg_match('/^[A-Za-z0-9_-]+$/', $sessionName)) {
        $sessionName = 'sems_session';
    }

    return [
        'APP_ENV' => strtolower(security_env('APP_ENV', 'production')),
        'SESSION_NAME' => $sessionName,
        'SESSION_LIFETIME' => max(1, (int) $lifetime),
        'CSRF_TOKEN_NAME' => security_env('CSRF_TOKEN_NAME', '_token'),
        'MAIL_DRIVER' => strtolower(security_env('MAIL_DRIVER', 'smtp')),
        'MAIL_HOST' => security_env('MAIL_HOST', 'smtp.gmail.com'),
        'MAIL_PORT' => max(1, (int) security_env('MAIL_PORT', '465')),
        'MAIL_ENCRYPTION' => strtolower(security_env('MAIL_ENCRYPTION', 'ssl')),
        'MAIL_USERNAME' => security_env('MAIL_USERNAME', ''),
        'MAIL_PASSWORD' => security_env('MAIL_PASSWORD', ''),
        'MAIL_FROM_ADDRESS' => security_env('MAIL_FROM_ADDRESS', ''),
        'MAIL_FROM_NAME' => security_env('MAIL_FROM_NAME', 'CORE HR'),
        'APPLICATION_STATUS_SECRET' => security_env('APPLICATION_STATUS_SECRET', ''),
        'RECAPTCHA_SITE_KEY' => security_env('RECAPTCHA_SITE_KEY', ''),
        'RECAPTCHA_SECRET_KEY' => security_env('RECAPTCHA_SECRET_KEY', ''),
        'RECAPTCHA_THRESHOLD' => is_numeric($threshold) ? (float) $threshold : 0.5,
        'OTP_TTL_MINUTES' => max(1, (int) $otpTtl),
        'OTP_ENFORCE' => !in_array($otpEnforce, ['0', 'false', 'off', 'no'], true),
    ];
}

function security_is_local(): bool
{
    return security_config()['APP_ENV'] === 'local';
}

function security_is_production(): bool
{
    return security_config()['APP_ENV'] === 'production';
}

function security_log(string $message): void
{
    $dir = dirname(__DIR__) . '/storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    if (@file_put_contents($dir . '/app.log', $line, FILE_APPEND | LOCK_EX) === false) {
        error_log($message);
    }
}

function security_configuration_problems(): array
{
    $config = security_config();
    if (!security_is_production() || $config['OTP_ENFORCE'] !== true) {
        return [];
    }

    $problems = [];
    $secret = $config['APPLICATION_STATUS_SECRET'];
    if ($secret === '' || hash_equals('change-this-production-secret', $secret)) {
        $problems[] = 'APPLICATION_STATUS_SECRET';
    }
    if ($config['MAIL_PASSWORD'] === '') {
        $problems[] = 'MAIL_PASSWORD';
    }
    if ($config['RECAPTCHA_SECRET_KEY'] === '') {
        $problems[] = 'RECAPTCHA_SECRET_KEY';
    }
    return $problems;
}

function security_request_is_https(): bool
{
    $https = $_SERVER['HTTPS'] ?? '';
    if (is_string($https) && $https !== '' && strtolower($https) !== 'off') {
        return true;
    }
    $forwarded = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
    return $forwarded === 'https';
}

function security_enforce_configuration(): void
{
    if (security_is_production()) {
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
    }
    ini_set('log_errors', '1');
    $logDir = dirname(__DIR__) . '/storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0750, true);
    }
    if (is_dir($logDir) && is_writable($logDir)) {
        ini_set('error_log', $logDir . '/app.log');
    }

    $problems = security_configuration_problems();
    if ($problems === []) {
        return;
    }

    security_log('Security configuration error: ' . implode(', ', $problems));
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Security configuration error</title></head><body><p>Security configuration error</p></body></html>';
    exit;
}

security_enforce_configuration();
