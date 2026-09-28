<?php
declare(strict_types=1);

if (!function_exists('core_hr_unwrap_env_value')) {
    function core_hr_unwrap_env_value(string $value): string
    {
        $value = trim($value);
        $length = strlen($value);
        if ($length < 2) {
            return $value;
        }
        $first = $value[0];
        $last = $value[$length - 1];
        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
            return substr($value, 1, -1);
        }
        return $value;
    }
}

if (!function_exists('core_hr_load_env')) {
    function core_hr_load_env(): void
    {
        $envFile = dirname(__DIR__) . '/.env';
        if (!is_file($envFile)) {
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $trimmed, 2), 2, '');
            $key = trim($key);
            $value = core_hr_unwrap_env_value($value);
            if ($key === '') {
                continue;
            }

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

core_hr_load_env();

return [
    'api_key' => getenv('GEMINI_API_KEY') ?: '',
    'model' => getenv('GEMINI_MODEL') ?: 'gemini-2.5-flash',
];
