<?php
declare(strict_types=1);

function recaptcha_failure_reason(array $data, string $expectedAction, string $expectedHost, float $threshold): ?string
{
    if (($data['success'] ?? false) !== true) {
        return 'unsuccessful';
    }
    $score = $data['score'] ?? null;
    if (!is_numeric($score) || (float) $score < $threshold) {
        return 'low_score';
    }
    if ((string) ($data['action'] ?? '') !== $expectedAction) {
        return 'action_mismatch';
    }
    $host = strtolower(trim((string) ($data['hostname'] ?? '')));
    $expected = strtolower(trim($expectedHost));
    if ($host === '' || $expected === '' || $host !== $expected) {
        return 'hostname_mismatch';
    }
    return null;
}

function recaptcha_request_host(): string
{
    $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
    return (string) preg_replace('/:\d+$/', '', $host);
}

function recaptcha_verify(string $expectedAction, ?string $token = null): bool
{
    $config = security_config();
    $token ??= (string) ($_POST['g-recaptcha-response'] ?? '');
    $fail = static function (string $reason) use ($expectedAction): bool {
        security_log('reCAPTCHA failed: ' . $reason . ' action=' . $expectedAction);
        if (function_exists('audit_as')) {
            audit_as(
                !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
                'RECAPTCHA_FAILED',
                'security',
                null,
                'AUTH',
                'DENIED',
                ['reason' => $reason, 'action' => $expectedAction]
            );
        }
        return false;
    };

    try {
        if ($token === '' || $config['RECAPTCHA_SECRET_KEY'] === '') {
            return $fail($token === '' ? 'missing_token' : 'missing_secret');
        }
        if (!function_exists('curl_init')) {
            return $fail('network');
        }

        $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';
        $override = security_env('RECAPTCHA_VERIFY_URL', '');
        if ($override !== '' && security_is_local() && preg_match('#^http://127\\.0\\.0\\.1(?::\\d+)?/#', $override) === 1) {
            $verifyUrl = $override;
        }
        $handle = curl_init($verifyUrl);
        if ($handle === false) {
            return $fail('network');
        }
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'secret' => $config['RECAPTCHA_SECRET_KEY'],
                'response' => $token,
                'remoteip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $raw = curl_exec($handle);
        $errno = curl_errno($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        if ($errno !== 0 || !is_string($raw) || $raw === '' || $status !== 200) {
            return $fail('network');
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return $fail('invalid_response');
        }
        $reason = recaptcha_failure_reason($data, $expectedAction, recaptcha_request_host(), (float) $config['RECAPTCHA_THRESHOLD']);
        if ($reason !== null) {
            return $fail($reason);
        }
        return true;
    } catch (Throwable $exception) {
        security_log('reCAPTCHA verification error.');
        return $fail('network');
    }
}

function recaptcha_widget(string $action): string
{
    static $scriptLoaded = false;
    $allowed = ['login', 'verify_otp', 'resend_otp', 'change_password', 'forgot_password'];
    if (!in_array($action, $allowed, true)) {
        return '';
    }
    $siteKey = (string) security_config()['RECAPTCHA_SITE_KEY'];
    $keyJson = json_encode($siteKey, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    $actionJson = json_encode($action, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    $script = '';
    if ($siteKey !== '' && !$scriptLoaded) {
        $scriptLoaded = true;
        $src = 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode($siteKey);
        $script = '<script src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"></script>';
    }
    return $script
        . '<input type="hidden" name="g-recaptcha-response" value="">'
        . '<script>(function(){var action=' . $actionJson . ';var siteKey=' . $keyJson . ';'
        . 'document.querySelectorAll("form").forEach(function(form){'
        . 'if(form.getAttribute("data-recaptcha-action")!==action||form.dataset.recaptchaBound)return;'
        . 'form.dataset.recaptchaBound="1";'
        . 'form.addEventListener("submit",function(event){'
        . 'if(form.dataset.recaptchaOk==="1")return;'
        . 'event.preventDefault();'
        . 'var button=form.querySelector("[type=submit]");'
        . 'if(button)button.disabled=true;'
        . 'if(!window.grecaptcha||!siteKey){if(button)button.disabled=false;return;}'
        . 'grecaptcha.ready(function(){grecaptcha.execute(siteKey,{action:action}).then(function(token){'
        . 'var field=form.querySelector("[name=g-recaptcha-response]");'
        . 'if(field)field.value=token;'
        . 'form.dataset.recaptchaOk="1";'
        . 'form.submit();'
        . '}).catch(function(){if(button)button.disabled=false;});});'
        . '});});})();</script>';
}
