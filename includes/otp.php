<?php
declare(strict_types=1);

function otp_secret_ready(): bool
{
    $secret = (string) security_config()['APPLICATION_STATUS_SECRET'];
    return $secret !== '' && !hash_equals('change-this-production-secret', $secret);
}

function otp_hash(int $userId, string $purpose, string $code): string
{
    return hash_hmac('sha256', $code, (string) security_config()['APPLICATION_STATUS_SECRET'] . '|' . $userId . '|' . $purpose);
}

function pending_challenge_user_id(string $sessionKey): ?int
{
    $pending = $_SESSION[$sessionKey] ?? null;
    if (!is_array($pending) || empty($pending['user_id']) || empty($pending['started_at'])) {
        return null;
    }
    if ((time() - (int) $pending['started_at']) > 600) {
        unset($_SESSION[$sessionKey]);
        return null;
    }
    return (int) $pending['user_id'];
}

function begin_pending_challenge(string $sessionKey, int $userId): void
{
    regenerate_session_id();
    unset($_SESSION['user_id'], $_SESSION['session_version'], $_SESSION['pending_2fa'], $_SESSION['pending_reset']);
    $_SESSION[$sessionKey] = ['user_id' => $userId, 'started_at' => time()];
    $_SESSION['last_activity'] = time();
}

function clear_pending_challenges(): void
{
    unset($_SESSION['pending_2fa'], $_SESSION['pending_reset']);
}

function issue_otp(PDO $pdo, int $userId, string $purpose, string $email, string $subject, string $intro): bool
{
    if (!otp_secret_ready() || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        security_log('OTP was not issued.');
        return false;
    }
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $hash = otp_hash($userId, $purpose, $code);
    $minutes = (int) security_config()['OTP_TTL_MINUTES'];
    $expires = (new DateTimeImmutable('+' . $minutes . ' minutes'))->format('Y-m-d H:i:s');
    $pdo->prepare('UPDATE otp_tokens SET used_at = NOW() WHERE user_id = ? AND purpose = ? AND used_at IS NULL')->execute([$userId, $purpose]);
    $pdo->prepare('INSERT INTO otp_tokens (user_id, purpose, token_hash, expires_at, max_attempts, request_ip) VALUES (?,?,?,?,3,?)')->execute([$userId, $purpose, $hash, $expires, client_ip()]);
    $tokenId = (int) $pdo->lastInsertId();
    if ($purpose === 'PASSWORD_RESET') {
        $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')->execute([$userId]);
        $pdo->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, request_ip) VALUES (?,?,?,?)')->execute([$userId, $hash, $expires, client_ip()]);
    }
    $html = '<p>' . mail_escape($intro) . '</p><p><strong>' . mail_escape($code) . '</strong></p><p>This code expires in ' . mail_escape((string) $minutes) . ' minutes.</p>';
    $text = $intro . "\n\n" . $code . "\n\nThis code expires in {$minutes} minutes.";
    $sent = send_mail($email, $subject, $html, $text);
    audit_as($userId, 'OTP_SENT', 'otp_tokens', $tokenId, 'AUTH', $sent ? 'SUCCESS' : 'DENIED', ['purpose' => $purpose]);
    if (!$sent) {
        $pdo->prepare('UPDATE otp_tokens SET used_at = NOW() WHERE id = ?')->execute([$tokenId]);
        if ($purpose === 'PASSWORD_RESET') {
            $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND token_hash = ? AND used_at IS NULL')->execute([$userId, $hash]);
        }
        return false;
    }
    return true;
}

function otp_resend_block_message(PDO $pdo, int $userId, string $purpose): ?string
{
    $latest = $pdo->prepare('SELECT created_at >= (NOW() - INTERVAL 60 SECOND) AS too_soon FROM otp_tokens WHERE user_id = ? AND purpose = ? ORDER BY id DESC LIMIT 1');
    $latest->execute([$userId, $purpose]);
    $row = $latest->fetch();
    if ($row && (int) $row['too_soon'] === 1) {
        return 'Please wait a minute before requesting another code.';
    }
    $userCount = $pdo->prepare('SELECT COUNT(*) FROM otp_tokens WHERE user_id = ? AND purpose = ? AND created_at >= (NOW() - INTERVAL 15 MINUTE)');
    $userCount->execute([$userId, $purpose]);
    $ipCount = $pdo->prepare('SELECT COUNT(*) FROM otp_tokens WHERE request_ip = ? AND purpose = ? AND created_at >= (NOW() - INTERVAL 15 MINUTE)');
    $ipCount->execute([client_ip(), $purpose]);
    if ((int) $userCount->fetchColumn() >= 4 || (int) $ipCount->fetchColumn() >= 4) {
        return 'A new code cannot be sent right now. Sign in again later.';
    }
    return null;
}

function consume_otp(PDO $pdo, int $userId, string $purpose, string $code): string
{
    $statement = $pdo->prepare('SELECT id, token_hash, attempts, max_attempts, (expires_at < NOW()) AS is_expired FROM otp_tokens WHERE user_id = ? AND purpose = ? AND used_at IS NULL ORDER BY id DESC LIMIT 1');
    $statement->execute([$userId, $purpose]);
    $row = $statement->fetch();
    if (!$row || (int) $row['is_expired'] === 1 || (int) $row['attempts'] >= (int) $row['max_attempts']) {
        if ($row) {
            $pdo->prepare('UPDATE otp_tokens SET used_at = NOW() WHERE id = ? AND used_at IS NULL')->execute([(int) $row['id']]);
        }
        return 'restart';
    }
    $hash = otp_hash($userId, $purpose, $code);
    if (!hash_equals((string) $row['token_hash'], $hash)) {
        $pdo->prepare('UPDATE otp_tokens SET attempts = attempts + 1 WHERE id = ?')->execute([(int) $row['id']]);
        register_failed_credential($pdo, $userId);
        record_login_attempt($pdo, 'otp:' . $purpose, false);
        $attempts = $pdo->prepare('SELECT attempts, max_attempts FROM otp_tokens WHERE id = ?');
        $attempts->execute([(int) $row['id']]);
        $updated = $attempts->fetch() ?: ['attempts' => 0, 'max_attempts' => 3];
        if ((int) $updated['attempts'] >= (int) $updated['max_attempts'] || account_is_locked($pdo, $userId)) {
            return 'restart';
        }
        return 'mismatch';
    }
    $pdo->prepare('UPDATE otp_tokens SET used_at = NOW() WHERE id = ?')->execute([(int) $row['id']]);
    if ($purpose === 'PASSWORD_RESET') {
        $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND token_hash = ? AND used_at IS NULL')->execute([$userId, $hash]);
    }
    return 'ok';
}
