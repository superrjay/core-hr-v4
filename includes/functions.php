<?php
declare(strict_types=1);

function e(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function url(string $path = ''): string { return '/core-hr/' . ltrim($path, '/'); }
function redirect(string $path): never { header('Location: ' . (str_starts_with($path, '/') ? $path : url($path))); exit; }
function csrf_token_name(): string { return (string) security_config()['CSRF_TOKEN_NAME']; }
function csrf_token(): string { if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); return $_SESSION['csrf_token']; }
function csrf_field(): string { return '<input type="hidden" name="' . e(csrf_token_name()) . '" value="' . e(csrf_token()) . '">'; }
function request_wants_json(): bool { return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/'); }
function verify_csrf(): void
{
    $expected = (string) ($_SESSION['csrf_token'] ?? '');
    $token = $_POST[csrf_token_name()] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($expected === '' || !is_string($token) || !hash_equals($expected, $token)) {
        $userId = !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        audit_as($userId, 'CSRF_FAILED', 'security', null, 'AUTH', 'DENIED', ['uri' => (string) ($_SERVER['REQUEST_URI'] ?? '')]);
        header('HTTP/1.1 419 Authentication Timeout', true, 419);
        if (request_wants_json()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Invalid security token. Please go back and try again.'], JSON_UNESCAPED_SLASHES);
            exit;
        }
        exit('Invalid security token. Please go back and try again.');
    }
}
function flash(string $key, ?string $message = null): ?string { if ($message !== null) { $_SESSION['flash'][$key] = $message; return null; } $value = $_SESSION['flash'][$key] ?? null; unset($_SESSION['flash'][$key]); return $value; }
function destroy_auth_session(): void
{
    current_user(true);
    if (session_status() !== PHP_SESSION_ACTIVE) return;
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
}
function idle_timeout_seconds(): int { return max(1, (int) security_config()['SESSION_LIFETIME']) * 60; }
function session_is_idle(): bool
{
    if (empty($_SESSION['last_activity'])) return false;
    return (time() - (int) $_SESSION['last_activity']) > idle_timeout_seconds();
}
function regenerate_session_id(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
}
function establish_login_session(int $userId, int $sessionVersion): void
{
    unset($_SESSION['pending_2fa'], $_SESSION['pending_reset']);
    regenerate_session_id();
    $_SESSION['user_id'] = $userId;
    $_SESSION['last_activity'] = time();
    $_SESSION['session_version'] = $sessionVersion;
}
function bump_session_version(PDO $pdo, int $userId): int
{
    $pdo->prepare('UPDATE users SET session_version = session_version + 1 WHERE id = ?')->execute([$userId]);
    $statement = $pdo->prepare('SELECT session_version FROM users WHERE id = ?');
    $statement->execute([$userId]);
    $version = (int) $statement->fetchColumn();
    if (!empty($_SESSION['user_id']) && (int) $_SESSION['user_id'] === $userId) $_SESSION['session_version'] = $version;
    return $version;
}
function current_user(bool $reset = false): ?array
{
    static $user = false;
    if ($reset) { $user = false; return null; }
    if ($user !== false) return $user;
    if (!empty($_SESSION['pending_2fa']) || !empty($_SESSION['pending_reset']) || empty($_SESSION['user_id'])) return $user = null;
    try {
        $statement = db()->prepare('SELECT u.*, r.name AS role_name, CONCAT(e.first_name, " ", e.last_name) AS employee_name FROM users u JOIN roles r ON r.id = u.role_id LEFT JOIN employees e ON e.id = u.employee_id WHERE u.id = ? AND u.is_active = 1 LIMIT 1');
        $statement->execute([$_SESSION['user_id']]);
        $row = $statement->fetch() ?: null;
    } catch (Throwable $exception) {
        if (function_exists('security_log')) security_log('current_user lookup failed.');
        return $user = null;
    }
    if (!$row) return $user = null;
    if (array_key_exists('is_locked', $row) && (int) $row['is_locked'] === 1) {
        audit_as((int) $row['id'], 'SESSION_REJECTED', 'users', (int) $row['id'], 'AUTH', 'DENIED', ['reason' => 'locked']);
        destroy_auth_session();
        return $user = null;
    }
    if (array_key_exists('session_version', $row)) {
        $stored = $_SESSION['session_version'] ?? null;
        if ($stored === null || (int) $stored !== (int) $row['session_version']) {
            audit_as((int) $row['id'], 'SESSION_REJECTED', 'users', (int) $row['id'], 'AUTH', 'DENIED', ['reason' => 'session_version']);
            destroy_auth_session();
            return $user = null;
        }
    }
    return $user = $row;
}
function user_role_names(): array
{
    $user = current_user();
    if (!$user) return [];
    $roles = [];
    $primary = trim((string) ($user['role_name'] ?? ''));
    if ($primary !== '') $roles[] = $primary;

    $statement = db()->prepare('SELECT r.name FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ?');
    $statement->execute([(int) $user['id']]);
    foreach ($statement->fetchAll() as $row) {
        $roleName = trim((string) ($row['name'] ?? ''));
        if ($roleName !== '') $roles[] = $roleName;
    }

    $roles = array_values(array_unique(array_filter($roles, static fn ($value) => $value !== '')));
    return $roles;
}
function hasRole(string $role): bool
{
    $target = strtoupper(trim($role));
    foreach (user_role_names() as $assigned) {
        if (strtoupper(trim((string) $assigned)) === $target) return true;
    }
    return false;
}
function hasAnyRole(array $roles): bool
{
    foreach ($roles as $role) {
        if (hasRole((string) $role)) return true;
    }
    return false;
}
function user_permission_names(): array
{
    $user = current_user();
    if (!$user) return [];
    $pdo = db();
    $query = 'SELECT DISTINCT p.name FROM permissions p JOIN role_permissions rp ON rp.permission_id = p.id WHERE rp.role_id IN (SELECT id FROM roles WHERE name IN (?, ?))';
    $params = [(string) ($user['role_name'] ?? ''), ''];
    if (!empty($user['role_name'])) {
        $statement = $pdo->prepare('SELECT DISTINCT p.name FROM permissions p JOIN role_permissions rp ON rp.permission_id = p.id JOIN roles r ON r.id = rp.role_id WHERE r.name = ? UNION SELECT DISTINCT p.name FROM permissions p JOIN role_permissions rp ON rp.permission_id = p.id JOIN user_roles ur ON ur.role_id = rp.role_id WHERE ur.user_id = ?');
        $statement->execute([(string) $user['role_name'], (int) $user['id']]);
        $permissions = $statement->fetchAll(PDO::FETCH_COLUMN, 0);
        return array_values(array_unique(array_filter(array_map('trim', $permissions), static fn ($value) => $value !== '')));
    }

    $statement = $pdo->prepare('SELECT DISTINCT p.name FROM permissions p JOIN role_permissions rp ON rp.permission_id = p.id JOIN user_roles ur ON ur.role_id = rp.role_id WHERE ur.user_id = ?');
    $statement->execute([(int) $user['id']]);
    $permissions = $statement->fetchAll(PDO::FETCH_COLUMN, 0);
    return array_values(array_unique(array_filter(array_map('trim', $permissions), static fn ($value) => $value !== '')));
}
function hasPermission(string $permission): bool
{
    $user = current_user();
    if (!$user) return false;
    $check = trim($permission);
    $permissions = user_permission_names();
    return in_array($check, $permissions, true);
}
function require_auth(): array
{
    $user = current_user();
    if (!$user) redirect('auth/login.php');
    if (session_is_idle()) {
        audit('SESSION_EXPIRED', 'users', (int) $_SESSION['user_id'], 'AUTH', 'DENIED');
        destroy_auth_session();
        redirect('auth/login.php');
    }
    $_SESSION['last_activity'] = time();
    if (user_must_change_password() && !request_is_password_change_page()) {
        redirect('auth/change-password.php');
    }
    return $user;
}
function require_api_session(callable $reject): void
{
    if (!empty($_SESSION['pending_2fa']) || !empty($_SESSION['pending_reset'])) { $reject(); exit; }
    if (!current_user()) { $reject(); exit; }
    if (session_is_idle()) {
        $userId = !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        audit_as($userId, 'SESSION_EXPIRED', 'users', $userId, 'AUTH', 'DENIED');
        destroy_auth_session();
        $reject();
        exit;
    }
    if (user_must_change_password()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Password change required.'], JSON_UNESCAPED_SLASHES);
        exit;
    }
    $_SESSION['last_activity'] = time();
}
function require_roles(array $roles): array { $user = require_auth(); if (!hasAnyRole($roles)) { http_response_code(403); exit('You are not authorized to access this page.'); } return $user; }
function require_permission(string $permission): void
{
    if (hasPermission($permission)) return;
    http_response_code(403);
    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'You do not have permission to perform this action.'], JSON_UNESCAPED_SLASHES);
        exit;
    }
    exit('You do not have permission to perform this action.');
}
function employee_can_access_employee(array $user, int $employeeId): bool
{
    if (hasAnyRole(['ADMIN', 'HR'])) return true;
    return !empty($user['employee_id']) && ((int) $user['employee_id']) === $employeeId;
}
function require_employee_access(PDO $pdo, int $employeeId): void
{
    $user = current_user();
    if (!$user) {
        http_response_code(401);
        exit('Authentication required.');
    }
    if (!employee_can_access_employee($user, $employeeId)) {
        http_response_code(403);
        exit('You do not have permission to access this employee record.');
    }
}
function audit(string $action, string $entity, ?int $entityId = null, ?string $module = null, ?string $result = null, mixed $details = null): void
{
    if (empty($_SESSION['user_id'])) return;
    audit_as((int) $_SESSION['user_id'], $action, $entity, $entityId, $module, $result, $details);
}
function audit_as(?int $userId, string $action, string $entity, ?int $entityId = null, ?string $module = null, ?string $result = null, mixed $details = null): void
{
    try {
        $pdo = db();
        $columns = $pdo->query('SHOW COLUMNS FROM audit_logs')->fetchAll(PDO::FETCH_COLUMN, 0);
        $sql = 'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address';
        $params = [$userId, $action, $entity, $entityId, $_SERVER['REMOTE_ADDR'] ?? null];
        $placeholders = '?, ?, ?, ?, ?';
        foreach (['module', 'result', 'details'] as $column) {
            if (in_array($column, $columns, true)) {
                $sql .= ', ' . $column;
                $placeholders .= ', ?';
                if ($column === 'module') $params[] = $module ?: 'CORE_HR';
                elseif ($column === 'result') $params[] = $result ?: 'SUCCESS';
                else $params[] = $details !== null ? json_encode($details, JSON_UNESCAPED_SLASHES) : null;
            }
        }
        $sql .= ') VALUES (' . $placeholders . ')';
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
    } catch (Throwable $exception) {
        if (function_exists('security_log')) security_log('audit_as failed for ' . $action);
    }
}
function document_status_transitions(): array
{
    return [
        'DRAFT' => ['FOR_REVIEW'],
        'FOR_REVIEW' => ['APPROVED', 'DRAFT'],
        'APPROVED' => ['FINALIZED'],
        'FINALIZED' => ['ARCHIVED'],
        'ARCHIVED' => [],
    ];
}
function document_transition_allowed(string $from, string $to): bool
{
    return in_array($to, document_status_transitions()[$from] ?? [], true);
}
function notify_hr_reviewers(PDO $pdo, string $title, string $message, string $type = 'INFO', ?int $relatedId = null): void
{
    $reviewers = $pdo->query("SELECT DISTINCT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE u.is_active = 1 AND r.name IN ('ADMIN','HR')")->fetchAll();
    foreach ($reviewers as $reviewer) {
        notify_user($pdo, (int) $reviewer['id'], 'HR_REVIEW', $title, $message, true);
    }
}
function ensure_system_rbac(): void
{
    $pdo = db();
    $permissionCount = (int) $pdo->query('SELECT COUNT(*) FROM permissions')->fetchColumn();
    if ($permissionCount >= 28 && $pdo->query("SHOW TABLES LIKE 'user_roles'")->fetchColumn()) {
        return;
    }
    $pdo->exec('CREATE TABLE IF NOT EXISTS user_roles (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, user_id INT UNSIGNED NOT NULL, role_id INT UNSIGNED NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY uq_user_role (user_id, role_id), KEY idx_user_roles_user (user_id), KEY idx_user_roles_role (role_id), CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $columnList = $pdo->query('SHOW COLUMNS FROM audit_logs')->fetchAll(PDO::FETCH_COLUMN, 0);
    foreach (['module', 'result', 'details'] as $column) {
        if (!in_array($column, $columnList, true)) {
            $pdo->exec('ALTER TABLE audit_logs ADD COLUMN ' . $column . ' ' . ($column === 'details' ? 'JSON NULL' : 'VARCHAR(80) NULL') . ' AFTER entity_id');
        }
    }

    $roleSeeds = [
        'ADMIN' => 'Full system access',
        'HR' => 'Human resource management access',
        'EMPLOYEE' => 'Employee self-service access',
        'MANAGER' => 'Manager-level team access',
        'SYSTEM_INTEGRATION' => 'Service-role access for module integrations',
    ];
    foreach ($roleSeeds as $name => $description) {
        $pdo->prepare('INSERT IGNORE INTO roles (name, description) VALUES (?, ?)')->execute([$name, $description]);
    }

    $permissionSeeds = [
        'dashboard.view' => 'View the dashboard',
        'users.view' => 'View users',
        'users.create' => 'Create users',
        'users.update' => 'Update users',
        'users.delete' => 'Delete users',
        'roles.view' => 'View roles',
        'roles.manage' => 'Create, edit, and manage roles',
        'permissions.view' => 'View permissions',
        'employees.view' => 'View employees',
        'employees.create' => 'Create employees',
        'employees.update' => 'Update employees',
        'employees.archive' => 'Archive employees',
        'departments.manage' => 'Manage departments',
        'positions.manage' => 'Manage positions',
        'branches.manage' => 'Manage branches',
        'ess.review' => 'Review employee change requests',
        'employee_documents.manage' => 'Manage employee documents',
        'ai.profile.generate' => 'Generate employee profiles with AI',
        'ai.profile.review' => 'Review AI profiles',
        'ai.document.generate' => 'Generate AI documents',
        'ai.document.review' => 'Review AI documents',
        'ai.document.approve' => 'Approve AI documents',
        'ai.document.finalize' => 'Finalize AI-reviewed documents',
        'audit.view' => 'View audit logs',
        'notifications.manage' => 'Manage notifications',
        'integration.view' => 'View integration output',
        'employee.profile.view' => 'View own profile',
        'employee.documents.view' => 'View own documents',
        'employee.requests.manage' => 'Submit and track own requests',
    ];
    foreach ($permissionSeeds as $name => $description) {
        $pdo->prepare('INSERT IGNORE INTO permissions (name, description) VALUES (?, ?)')->execute([$name, $description]);
    }

    $rolePermissionSeeds = [
        'ADMIN' => array_keys($permissionSeeds),
        'HR' => ['dashboard.view','employees.view','employees.create','employees.update','employees.archive','departments.manage','positions.manage','branches.manage','ess.review','employee_documents.manage','ai.profile.generate','ai.profile.review','ai.document.generate','ai.document.review','ai.document.approve','ai.document.finalize','audit.view','notifications.manage','integration.view','employee.profile.view','employee.documents.view','employee.requests.manage'],
        'EMPLOYEE' => ['dashboard.view','employee.profile.view','employee.documents.view','employee.requests.manage'],
        'MANAGER' => ['dashboard.view','employees.view','ess.review'],
        'SYSTEM_INTEGRATION' => ['integration.view'],
    ];
    foreach ($rolePermissionSeeds as $roleName => $permissions) {
        $roleId = (int) $pdo->query('SELECT id FROM roles WHERE name = ' . $pdo->quote($roleName))->fetchColumn();
        if (!$roleId) continue;
        foreach ($permissions as $permissionName) {
            $permissionId = (int) $pdo->query('SELECT id FROM permissions WHERE name = ' . $pdo->quote($permissionName))->fetchColumn();
            if (!$permissionId) continue;
            $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)')->execute([$roleId, $permissionId]);
        }
    }

    $users = ['admin' => 'ADMIN', 'hr.manager' => 'HR', 'employee.benjie' => 'EMPLOYEE', 'employee.celeste' => 'EMPLOYEE'];
    foreach ($users as $username => $roleName) {
        $userId = (int) $pdo->query('SELECT id FROM users WHERE username = ' . $pdo->quote($username))->fetchColumn();
        $roleId = (int) $pdo->query('SELECT id FROM roles WHERE name = ' . $pdo->quote($roleName))->fetchColumn();
        if ($userId && $roleId) {
            $pdo->prepare('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)')->execute([$userId, $roleId]);
        }
    }
}
function post_value(string $key): ?string { $value = trim((string) ($_POST[$key] ?? '')); return $value === '' ? null : $value; }
function valid_date(?string $date): ?string { if ($date === null) return null; $parsed = DateTime::createFromFormat('Y-m-d', $date); return $parsed && $parsed->format('Y-m-d') === $date ? $date : null; }
function phase2_employee(PDO $pdo, int $id): ?array { $statement = $pdo->prepare('SELECT e.*, d.name AS department_name, p.name AS position_name, b.name AS branch_name FROM employees e LEFT JOIN departments d ON d.id=e.department_id LEFT JOIN positions p ON p.id=e.position_id LEFT JOIN branches b ON b.id=e.branch_id WHERE e.id=?'); $statement->execute([$id]); return $statement->fetch() ?: null; }
function phase2_require_employee(PDO $pdo, int $id): array { $employee = phase2_employee($pdo, $id); if (!$employee) { http_response_code(404); exit('Employee not found.'); } return $employee; }
function render_template(string $content, array $employee): string
{
    $values = [
        'employee.employee_id' => $employee['employee_number'] ?? '',
        'employee.first_name' => $employee['first_name'] ?? '',
        'employee.last_name' => $employee['last_name'] ?? '',
        'employee.position' => $employee['position_name'] ?? '',
        'employee.department' => $employee['department_name'] ?? '',
        'employee.branch' => $employee['branch_name'] ?? '',
        'employee.date_hired' => $employee['date_hired'] ?? '',
    ];
    foreach ($values as $placeholder => $value) {
        $content = str_replace('{{' . $placeholder . '}}', e((string) $value), $content);
    }
    return preg_replace('/\{\{[^}]+\}\}/', '', $content) ?? $content;
}
function lifecycle(PDO $pdo, int $employeeId, string $eventType, array $changes, string $reason, string $effectiveDate): void { $userId = (int) $_SESSION['user_id']; $before = phase2_require_employee($pdo, $employeeId); $pdo->beginTransaction(); try { $set = []; $params = []; foreach ($changes as $field => $value) { $set[] = $field . '=?'; $params[] = $value; } if (!$set) throw new InvalidArgumentException('No lifecycle change supplied.'); $params[] = $employeeId; $pdo->prepare('UPDATE employees SET ' . implode(',', $set) . ' WHERE id=?')->execute($params); $after = phase2_require_employee($pdo, $employeeId); $history = [$employeeId, $eventType, $before['department_id'], $before['position_id'], $before['branch_id'], $before['employment_status'], $after['department_id'], $after['position_id'], $after['branch_id'], $after['employment_status'], $effectiveDate, $reason, $userId]; $pdo->prepare('INSERT INTO employment_histories (employee_id,event_type,previous_department_id,previous_position_id,previous_branch_id,previous_status,department_id,position_id,branch_id,new_status,effective_date,reason,performed_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute($history); $pdo->prepare('INSERT INTO audit_logs (user_id,action,entity_type,entity_id,ip_address) VALUES (?,?,?,?,?)')->execute([$userId, strtoupper($eventType), 'employees', $employeeId, $_SERVER['REMOTE_ADDR'] ?? null]); $pdo->commit(); notify_linked_employee($pdo, $employeeId, array_keys($changes), 'Your employment record was updated.'); } catch (Throwable $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $exception; } }
function require_employee_user(): array { $user = require_roles(['EMPLOYEE']); if (empty($user['employee_id'])) { http_response_code(403); exit('No employee record is linked to this account.'); } return $user; }
function employee_owner(PDO $pdo, array $user): array { return phase2_require_employee($pdo, (int) $user['employee_id']); }
function create_notification(PDO $pdo, int $userId, string $title, string $message, string $type = 'INFO', ?int $relatedId = null, ?string $eventKey = null): int
{
    static $hasEventKey = null;
    if ($hasEventKey === null) {
        $columns = $pdo->query('SHOW COLUMNS FROM notifications')->fetchAll(PDO::FETCH_COLUMN);
        $hasEventKey = in_array('event_key', $columns, true);
    }
    if ($hasEventKey) {
        $pdo->prepare('INSERT INTO notifications (user_id,title,message,type,related_record_id,event_key) VALUES (?,?,?,?,?,?)')->execute([$userId, $title, $message, $type, $relatedId, $eventKey]);
    } else {
        $pdo->prepare('INSERT INTO notifications (user_id,title,message,type,related_record_id) VALUES (?,?,?,?,?)')->execute([$userId, $title, $message, $type, $relatedId]);
    }
    return (int) $pdo->lastInsertId();
}
function ensure_ai_tables(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS profile_generations (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        employee_id INT UNSIGNED NOT NULL,
        generated_content JSON NOT NULL,
        status ENUM('GENERATED','REVIEWED','APPROVED','REJECTED') NOT NULL DEFAULT 'GENERATED',
        generated_by INT UNSIGNED NULL,
        model VARCHAR(120) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        reviewed_by INT UNSIGNED NULL,
        reviewed_at DATETIME NULL,
        unique_key VARCHAR(120) NULL,
        PRIMARY KEY (id),
        KEY idx_profile_generation_employee (employee_id, created_at),
        CONSTRAINT fk_profile_generations_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        CONSTRAINT fk_profile_generations_generated_by FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL,
        CONSTRAINT fk_profile_generations_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_generation_logs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        employee_id INT UNSIGNED NULL,
        feature VARCHAR(80) NOT NULL,
        model VARCHAR(120) NULL,
        status VARCHAR(40) NOT NULL,
        generation_time DATETIME NULL,
        request_identifier VARCHAR(120) NULL,
        error_type VARCHAR(120) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_ai_generation_employee (employee_id, created_at),
        CONSTRAINT fk_ai_generation_logs_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
function ess_request_fields(): array { return ['email' => ['label'=>'Email','column'=>'email','type'=>'CONTACT_UPDATE'], 'phone' => ['label'=>'Phone','column'=>'phone','type'=>'CONTACT_UPDATE'], 'address' => ['label'=>'Address','column'=>'address','type'=>'CONTACT_UPDATE'], 'city' => ['label'=>'City','column'=>'city','type'=>'CONTACT_UPDATE'], 'province' => ['label'=>'Province','column'=>'province','type'=>'CONTACT_UPDATE'], 'postal_code' => ['label'=>'Postal code','column'=>'postal_code','type'=>'CONTACT_UPDATE']]; }
function review_change_request(PDO $pdo, int $requestId, int $reviewerId, string $decision, string $remarks): void
{
    $pdo->beginTransaction();
    try {
        $q = $pdo->prepare('SELECT * FROM profile_change_requests WHERE id=? FOR UPDATE'); $q->execute([$requestId]); $request = $q->fetch();
        if (!$request || $request['status'] !== 'PENDING') throw new RuntimeException('Only pending requests can be reviewed.');
        $fields = ess_request_fields(); if (!isset($fields[$request['requested_field']])) throw new RuntimeException('Request field is not allowed.');
        if ($decision === 'APPROVED') {
            $column = $fields[$request['requested_field']]['column'];
            $pdo->prepare("UPDATE employees SET {$column}=? WHERE id=?")->execute([$request['requested_value'], $request['employee_id']]);
            $status = 'APPROVED'; $message = 'HR approved your profile change request.'; $type = 'SUCCESS';
        } elseif ($decision === 'REJECTED' && trim($remarks) !== '') { $status = 'REJECTED'; $message = 'HR rejected your profile change request.'; $type = 'WARNING'; }
        else throw new RuntimeException('A rejection reason is required.');
        $pdo->prepare('UPDATE profile_change_requests SET status=?,reviewed_by=?,reviewed_at=NOW(),review_reason=? WHERE id=?')->execute([$status,$reviewerId,$remarks,$requestId]);
        $pdo->prepare('INSERT INTO audit_logs (user_id,action,entity_type,entity_id,ip_address) VALUES (?,?,?,?,?)')->execute([$reviewerId,'REQUEST_'.$status,'profile_change_requests',$requestId,$_SERVER['REMOTE_ADDR'] ?? null]);
        $pdo->commit();
    } catch (Throwable $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $exception; }
    $label = $fields[$request['requested_field']]['label'];
    $notice = $decision === 'APPROVED'
        ? 'Your profile change request for ' . $label . ' was approved.'
        : 'Your profile change request for ' . $label . ' was rejected.';
    notify_linked_employee($pdo, (int) $request['employee_id'], [$label], $notice);
}

ensure_system_rbac();