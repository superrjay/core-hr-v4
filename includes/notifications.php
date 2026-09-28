<?php
declare(strict_types=1);

function notification_table_columns(PDO $pdo): array
{
    static $columns = null;
    if ($columns === null) {
        $columns = $pdo->query('SHOW COLUMNS FROM notifications')->fetchAll(PDO::FETCH_COLUMN);
    }
    return $columns;
}

function user_email_address(PDO $pdo, int $userId): ?string
{
    $statement = $pdo->prepare('SELECT u.email, e.email AS employee_email FROM users u LEFT JOIN employees e ON e.id = u.employee_id WHERE u.id = ? LIMIT 1');
    $statement->execute([$userId]);
    $row = $statement->fetch();
    if (!$row) {
        return null;
    }
    foreach (['email', 'employee_email'] as $key) {
        $value = trim((string) ($row[$key] ?? ''));
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return $value;
        }
    }
    return null;
}

function user_has_named_role(PDO $pdo, int $userId, string $role): bool
{
    $statement = $pdo->prepare('SELECT 1 FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND r.name = ? UNION SELECT 1 FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? AND r.name = ? LIMIT 1');
    $statement->execute([$userId, $role, $userId, $role]);
    return (bool) $statement->fetchColumn();
}

function notify_user(PDO $pdo, int $userId, string $eventKey, string $title, string $message, bool $email = true): void
{
    try {
        $notificationId = create_notification($pdo, $userId, $title, $message, 'INFO', null, $eventKey);
        if (!$email || $notificationId <= 0) {
            return;
        }
        $columns = notification_table_columns($pdo);
        $to = user_email_address($pdo, $userId);
        if ($to === null) {
            if (in_array('email_error', $columns, true)) {
                $pdo->prepare('UPDATE notifications SET email_error = ? WHERE id = ?')->execute(['No email address', $notificationId]);
            }
            return;
        }
        $sent = send_mail($to, $title, '<p>' . nl2br(mail_escape($message), false) . '</p>', $message);
        if ($sent && in_array('email_sent_at', $columns, true)) {
            $pdo->prepare('UPDATE notifications SET email_sent_at = NOW(), email_error = NULL WHERE id = ?')->execute([$notificationId]);
        } elseif (!$sent && in_array('email_error', $columns, true)) {
            $pdo->prepare('UPDATE notifications SET email_error = ? WHERE id = ?')->execute(['Delivery failed', $notificationId]);
        }
    } catch (Throwable $exception) {
        security_log('notify_user failed for ' . $eventKey);
    }
}

function notify_roles(PDO $pdo, array $roles, string $eventKey, string $title, string $message): void
{
    $roles = array_values(array_filter(array_map('strval', $roles)));
    if (!$roles) {
        return;
    }
    $placeholders = implode(',', array_fill(0, count($roles), '?'));
    $sql = 'SELECT DISTINCT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE u.is_active = 1 AND r.name IN (' . $placeholders . ') UNION SELECT DISTINCT u.id FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id WHERE u.is_active = 1 AND r.name IN (' . $placeholders . ')';
    $statement = $pdo->prepare($sql);
    $statement->execute([...$roles, ...$roles]);
    foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $userId) {
        notify_user($pdo, (int) $userId, $eventKey, $title, $message, true);
    }
}

function notify_linked_employee(PDO $pdo, int $employeeId, array $fieldNames, string $summary): void
{
    try {
        $statement = $pdo->prepare('SELECT id FROM users WHERE employee_id = ? AND is_active = 1 LIMIT 1');
        $statement->execute([$employeeId]);
        $userId = (int) $statement->fetchColumn();
        if ($userId <= 0) {
            return;
        }
        $names = [];
        foreach ($fieldNames as $name) {
            $name = trim((string) $name);
            if ($name !== '' && !in_array($name, ['id', 'created_at', 'updated_at'], true)) {
                $names[] = $name;
            }
        }
        $names = array_values(array_unique($names));
        $message = $summary;
        if ($names) {
            $message .= ' Changed fields: ' . implode(', ', $names) . '.';
        }
        $event = user_has_named_role($pdo, $userId, 'MANAGER') ? 'MANAGER_INFO_CHANGED' : 'EMPLOYEE_INFO_CHANGED';
        notify_user($pdo, $userId, $event, 'Employee record updated', $message, true);
    } catch (Throwable $exception) {
        security_log('notify_linked_employee failed.');
    }
}

function security_scalar(mixed $value): string
{
    if ($value === null) {
        return '';
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    return trim((string) $value);
}

function notify_employee_saved(PDO $pdo, int $employeeId, ?array $before): void
{
    if (!$before) {
        return;
    }
    try {
        $statement = $pdo->prepare('SELECT * FROM employees WHERE id = ?');
        $statement->execute([$employeeId]);
        $after = $statement->fetch();
        if (!$after) {
            return;
        }
        $changed = [];
        foreach ($after as $field => $value) {
            if (!is_string($field) || in_array($field, ['id', 'created_at', 'updated_at'], true)) {
                continue;
            }
            if (!array_key_exists($field, $before)) {
                continue;
            }
            if (security_scalar($before[$field]) !== security_scalar($value)) {
                $changed[] = $field;
            }
        }
        if ($changed) {
            notify_linked_employee($pdo, $employeeId, $changed, 'Your employee record was updated.');
        }
    } catch (Throwable $exception) {
        security_log('notify_employee_saved failed.');
    }
}

function unread_notification_count(PDO $pdo, int $userId): int
{
    try {
        $statement = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $statement->execute([$userId]);
        return (int) $statement->fetchColumn();
    } catch (Throwable $exception) {
        return 0;
    }
}

function notification_bell_html(): string
{
    $user = current_user();
    if (!$user) {
        return '';
    }
    $count = unread_notification_count(db(), (int) $user['id']);
    $badge = $count > 0 ? '<span class="rounded-full bg-teal-700 px-1.5 text-xs text-white">' . e((string) $count) . '</span>' : '';
    return '<a href="' . e(url('notifications.php')) . '" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm font-bold" aria-label="Notifications"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5"/><path d="M9 17a3 3 0 0 0 6 0"/></svg>' . $badge . '</a>';
}

function change_password_link_html(): string
{
    return '<a href="' . e(url('auth/change-password.php')) . '" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-bold">Password</a>';
}

function otp_enforcement_banner_html(): string
{
    $user = current_user();
    if (!$user || !hasRole('ADMIN') || security_config()['OTP_ENFORCE']) {
        return '';
    }
    return '<div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Email sign-in codes are turned off. Password, captcha, and account lockout still apply.</div>';
}
