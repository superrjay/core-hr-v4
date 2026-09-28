<?php
declare(strict_types=1);

const APP_NAME = 'Core HR';
const APP_TIMEZONE = 'Asia/Manila';
date_default_timezone_set(APP_TIMEZONE);

require_once __DIR__ . '/ai.php';
require_once __DIR__ . '/security.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    $https = security_request_is_https();
    session_name(security_config()['SESSION_NAME']);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', $https ? '1' : '0');
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => $https,
        'use_strict_mode' => true,
        'use_only_cookies' => true,
    ]);
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/recaptcha.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/account_security.php';
require_once __DIR__ . '/../includes/otp.php';
foreach ([
    'GeminiService.php',
    'AIContextBuilder.php',
    'PromptBuilder.php',
    'AIOutputValidator.php',
    'EmployeeProfiler.php',
    'DocumentDraftingAI.php',
] as $aiClassFile) {
    require_once dirname(__DIR__) . '/ai/' . $aiClassFile;
}
