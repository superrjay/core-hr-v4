<?php
declare(strict_types=1);

const DUMMY_PASSWORD_HASH = '$2y$10$vlMyftJF4nmASEfEZcNWJ.tEfedcDHX64/8ke0YeOpeGofTjTBs5W';

function auth_generic_failure(): string
{
    return 'Invalid credentials, or the account is locked. Contact an administrator if this continues.';
}

function auth_captcha_failure(): string
{
    return 'Sign-in could not be verified. Please try again.';
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function client_browser(): string
{
    $agent = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    return $agent === '' ? 'Unknown browser' : substr($agent, 0, 180);
}

function security_event_stamp(): string
{
    return date('M j, Y g:i A');
}

function password_meets_policy(string $password): bool
{
    return strlen($password) >= 10
        && preg_match('/[A-Z]/', $password) === 1
        && preg_match('/[a-z]/', $password) === 1
        && preg_match('/\d/', $password) === 1
        && preg_match('/[^A-Za-z0-9]/', $password) === 1;
}

function password_policy_message(): string
{
    return 'Password must be at least 10 characters and include an uppercase letter, a lowercase letter, a number, and a symbol.';
}

function account_email(array $account): ?string
{
    foreach (['email', 'employee_email'] as $key) {
        $value = trim((string) ($account[$key] ?? ''));
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return $value;
        }
    }
    return null;
}

function record_login_attempt(PDO $pdo, string $username, bool $successful): void
{
    try {
        $pdo->prepare('INSERT INTO login_attempts (username, ip_address, successful) VALUES (?,?,?)')->execute([substr($username, 0, 80), client_ip(), $successful ? 1 : 0]);
    } catch (Throwable $exception) {
        security_log('login_attempts insert failed.');
    }
}

function login_ip_is_throttled(PDO $pdo): bool
{
    $statement = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND successful = 0 AND created_at >= (NOW() - INTERVAL 15 MINUTE)');
    $statement->execute([client_ip()]);
    return (int) $statement->fetchColumn() > 10;
}

function active_admin_ids(PDO $pdo): array
{
    $statement = $pdo->query("SELECT DISTINCT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE u.is_active = 1 AND r.name = 'ADMIN' UNION SELECT DISTINCT u.id FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id WHERE u.is_active = 1 AND r.name = 'ADMIN'");
    return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
}

function lock_account(PDO $pdo, int $userId, string $reason): void
{
    $reason = substr($reason, 0, 120);
    $statement = $pdo->prepare('UPDATE users SET is_locked = 1, locked_at = NOW(), lock_reason = ? WHERE id = ? AND is_locked = 0');
    $statement->execute([$reason, $userId]);
    if ($statement->rowCount() < 1) {
        return;
    }
    audit_as($userId, 'ACCOUNT_LOCKED', 'users', $userId, 'AUTH', 'DENIED', ['reason' => $reason]);
    $account = $pdo->prepare('SELECT username FROM users WHERE id = ?');
    $account->execute([$userId]);
    $username = (string) ($account->fetchColumn() ?: 'account');
    $when = security_event_stamp();
    notify_user($pdo, $userId, 'ACCOUNT_LOCKED', 'Account locked', 'Your Core HR account was locked at ' . $when . '. An administrator must unlock it.', true);
    $adminMessage = 'Account ' . $username . ' was locked at ' . $when . ' after failed sign-in attempts.';
    foreach (active_admin_ids($pdo) as $adminId) {
        if ($adminId === $userId) {
            continue;
        }
        notify_user($pdo, $adminId, 'ACCOUNT_LOCKED', 'Account locked', $adminMessage, true);
    }
}

function register_failed_credential(PDO $pdo, int $userId): void
{
    $pdo->prepare('UPDATE users SET failed_login_attempts = failed_login_attempts + 1, last_failed_login_at = NOW() WHERE id = ? AND is_locked = 0')->execute([$userId]);
    $statement = $pdo->prepare('SELECT failed_login_attempts, is_locked FROM users WHERE id = ?');
    $statement->execute([$userId]);
    $row = $statement->fetch();
    if ($row && (int) $row['is_locked'] === 0 && (int) $row['failed_login_attempts'] >= 3) {
        lock_account($pdo, $userId, 'Too many failed sign-in attempts');
    }
}

function account_is_locked(PDO $pdo, int $userId): bool
{
    $statement = $pdo->prepare('SELECT is_locked FROM users WHERE id = ?');
    $statement->execute([$userId]);
    return (int) $statement->fetchColumn() === 1;
}

function sign_in_notice(string $action): string
{
    return $action . ' at ' . security_event_stamp() . ' from IP ' . client_ip() . '. Browser: ' . client_browser() . '. If this was not you, contact your administrator.';
}

function complete_authenticated_login(PDO $pdo, int $userId): void
{
    $statement = $pdo->prepare('SELECT id, session_version, must_change_password, is_locked, is_active FROM users WHERE id = ? LIMIT 1');
    $statement->execute([$userId]);
    $account = $statement->fetch();
    if (!$account || (int) $account['is_active'] !== 1 || (int) $account['is_locked'] === 1) {
        unset($_SESSION['pending_2fa'], $_SESSION['pending_reset']);
        flash('error', auth_generic_failure());
        redirect('auth/login.php');
    }
    $pdo->prepare('UPDATE users SET failed_login_attempts = 0, last_failed_login_at = NULL, last_login_at = NOW() WHERE id = ?')->execute([$userId]);
    establish_login_session($userId, (int) $account['session_version']);
    audit('LOGIN', 'users', $userId);
    notify_user($pdo, $userId, 'LOGIN_SUCCESS', 'New sign-in', sign_in_notice('New sign-in'), true);
    if ((int) ($account['must_change_password'] ?? 0) === 1) {
        redirect('auth/change-password.php');
    }
    redirect('dashboard.php');
}

function user_must_change_password(): bool
{
    $user = current_user();
    return $user !== null && array_key_exists('must_change_password', $user) && (int) $user['must_change_password'] === 1;
}

function request_is_password_change_page(): bool
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? ''));
    return str_ends_with($script, '/auth/change-password.php');
}

function find_login_account(PDO $pdo, string $username): ?array
{
    $statement = $pdo->prepare('SELECT u.id, u.username, u.password_hash, u.session_version, u.email, u.employee_id, u.is_locked, u.is_active, u.failed_login_attempts, u.must_change_password, e.email AS employee_email FROM users u LEFT JOIN employees e ON e.id = u.employee_id WHERE u.username = ? LIMIT 1');
    $statement->execute([$username]);
    $account = $statement->fetch();
    return $account ?: null;
}

function store_new_password(PDO $pdo, int $userId, string $newPassword, bool $mustChange, bool $keepCurrentSession): void
{
    $pdo->prepare('UPDATE users SET password_hash = ?, password_changed_at = NOW(), must_change_password = ? WHERE id = ?')->execute([
        password_hash($newPassword, PASSWORD_DEFAULT),
        $mustChange ? 1 : 0,
        $userId,
    ]);
    $version = bump_session_version($pdo, $userId);
    if ($keepCurrentSession && !empty($_SESSION['user_id']) && (int) $_SESSION['user_id'] === $userId) {
        regenerate_session_id();
        $_SESSION['user_id'] = $userId;
        $_SESSION['session_version'] = $version;
        $_SESSION['last_activity'] = time();
        unset($_SESSION['pending_2fa'], $_SESSION['pending_reset']);
    }
    audit_as($keepCurrentSession ? $userId : (!empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : $userId), 'PASSWORD_CHANGED', 'users', $userId, 'AUTH', 'SUCCESS');
    notify_user($pdo, $userId, 'PASSWORD_CHANGED', 'Password changed', sign_in_notice('Your password was changed'), true);
}
