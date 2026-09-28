<?php
require_once __DIR__ . '/../../config/config.php';
require_roles(['ADMIN']);
require_permission('users.view');
$pdo = db();
$error = null;
$canUpdate = hasPermission('users.update');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $formAction = (string) ($_POST['form_action'] ?? 'create');
    if ($formAction === 'unlock' || $formAction === 'reset_password') {
        require_permission('users.update');
        $targetId = (int) ($_POST['user_id'] ?? 0);
        try {
            $lookup = $pdo->prepare('SELECT id, password_hash, is_active FROM users WHERE id = ? LIMIT 1');
            $lookup->execute([$targetId]);
            $target = $lookup->fetch();
            if (!$target) {
                $error = 'That user account could not be updated.';
            } elseif ($formAction === 'unlock') {
                $pdo->prepare('UPDATE users SET is_locked = 0, failed_login_attempts = 0, locked_at = NULL, lock_reason = NULL, last_failed_login_at = NULL, unlocked_by = ?, unlocked_at = NOW() WHERE id = ?')->execute([(int) current_user()['id'], $targetId]);
                bump_session_version($pdo, $targetId);
                audit('ACCOUNT_UNLOCKED', 'users', $targetId, 'AUTH', 'SUCCESS');
                notify_user($pdo, $targetId, 'ACCOUNT_UNLOCKED', 'Account unlocked', 'An administrator unlocked your Core HR account at ' . security_event_stamp() . '.', true);
                flash('success', 'Account unlocked.');
                redirect('admin/users/index.php');
            } else {
                $newPassword = (string) ($_POST['password'] ?? '');
                if (!password_meets_policy($newPassword)) {
                    $error = password_policy_message();
                } elseif (password_verify($newPassword, (string) $target['password_hash'])) {
                    $error = 'Choose a different password.';
                } else {
                    $actorId = (int) current_user()['id'];
                    store_new_password($pdo, $targetId, $newPassword, true, $actorId === $targetId);
                    flash('success', 'Password updated. The user must choose a new password at the next sign-in.');
                    redirect('admin/users/index.php');
                }
            }
        } catch (Throwable $exception) {
            security_log('User admin action failed.');
            $error = 'That user account could not be updated. Import database/security-migration-v2.sql if this continues.';
        }
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $email = trim((string) ($_POST['email'] ?? ''));
        $employeeId = (int) ($_POST['employee_id'] ?? 0) ?: null;
        $roleId = (int) ($_POST['role_id'] ?? 0) ?: null;
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($username === '' || $password === '' || !$roleId || $email === '') {
            $error = 'Username, email, password, and role are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address.';
        } elseif (!password_meets_policy($password)) {
            $error = password_policy_message();
        } else {
            try {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO users (employee_id, role_id, username, password_hash, is_active, email) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$employeeId, $roleId, $username, $hashed, $isActive, $email]);
                $userId = (int) $pdo->lastInsertId();
                $pdo->prepare('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)')->execute([$userId, $roleId]);
                audit('USER_CREATED', 'users', $userId, 'USER_MANAGEMENT', 'SUCCESS', ['username' => $username]);
                flash('success', 'User account created.');
                redirect('admin/users/index.php');
            } catch (Throwable $exception) {
                security_log('User create failed.');
                $error = 'Could not create the user account. Check that the username and employee link are unique.';
            }
        }
    }
}

$users = [];
try {
    $users = $pdo->query('SELECT u.id, u.username, u.email, u.is_active, u.is_locked, u.failed_login_attempts, u.locked_at, u.employee_id, CONCAT(e.first_name, " ", e.last_name) AS employee_name, r.name AS role_name FROM users u LEFT JOIN employees e ON e.id = u.employee_id JOIN roles r ON r.id = u.role_id ORDER BY u.username')->fetchAll();
} catch (Throwable $exception) {
    security_log('User list failed.');
    $error = $error ?: 'User security columns are missing. Import database/security-migration-v2.sql.';
}
$roles = $pdo->query('SELECT id, name FROM roles ORDER BY name')->fetchAll();
$employees = $pdo->query('SELECT id, CONCAT(first_name, " ", last_name) AS name, employee_number FROM employees ORDER BY last_name, first_name')->fetchAll();
$pageTitle = 'User management';
require __DIR__ . '/../../includes/header.php';
?>
<div class="flex justify-between items-end mb-6">
  <div>
    <p class="text-sm text-slate-500">User account administration</p>
    <h1 class="font-display text-3xl font-bold mt-1">Users</h1>
  </div>
</div>

<div class="bg-white border border-slate-200 rounded-xl p-6 mb-8">
  <h2 class="font-display text-xl font-bold mb-4">Create user</h2>
  <?php if ($error): ?><div class="mb-4 rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-700"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
    <?= csrf_field() ?>
    <input type="hidden" name="form_action" value="create">
    <label class="text-sm font-semibold">Username<input required name="username" class="field" value="<?= e($_POST['username'] ?? '') ?>"></label>
    <label class="text-sm font-semibold">Email<input required type="email" name="email" class="field" value="<?= e($_POST['email'] ?? '') ?>"></label>
    <label class="text-sm font-semibold">Password<input required type="password" name="password" class="field" autocomplete="new-password"></label>
    <label class="text-sm font-semibold">Role<select name="role_id" class="field"><option value="">Select</option><?php foreach ($roles as $role): ?><option value="<?= e((string) $role['id']) ?>"><?= e($role['name']) ?></option><?php endforeach; ?></select></label>
    <label class="text-sm font-semibold">Linked employee<select name="employee_id" class="field"><option value="">None</option><?php foreach ($employees as $employee): ?><option value="<?= e((string) $employee['id']) ?>"><?= e($employee['name']) ?> (<?= e($employee['employee_number']) ?>)</option><?php endforeach; ?></select></label>
    <label class="inline-flex items-center gap-2 text-sm font-semibold mt-6"><input type="checkbox" name="is_active" checked> Active</label>
    <p class="md:col-span-2 xl:col-span-3 text-xs text-slate-500"><?= e(password_policy_message()) ?></p>
    <div class="md:col-span-2 xl:col-span-3 text-right"><button class="rounded-lg bg-teal-700 text-white px-5 py-3 font-bold">Create user</button></div>
  </form>
</div>

<section class="bg-white border border-slate-200 rounded-xl overflow-hidden">
  <div class="px-6 py-4 border-b border-slate-200"><h2 class="font-display text-xl font-bold">User list</h2></div>
  <div class="overflow-x-auto">
    <table class="w-full text-left text-sm">
      <thead class="bg-slate-50 text-xs uppercase text-slate-500">
        <tr><th class="px-6 py-3">Username</th><th class="px-6 py-3">Email</th><th class="px-6 py-3">Employee</th><th class="px-6 py-3">Role</th><th class="px-6 py-3">Status</th><th class="px-6 py-3">Failed attempts</th><th class="px-6 py-3">Locked at</th><?php if ($canUpdate): ?><th class="px-6 py-3">Actions</th><?php endif; ?></tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($users as $account): ?>
          <?php
            $locked = (int) ($account['is_locked'] ?? 0) === 1;
            $active = (int) ($account['is_active'] ?? 0) === 1;
            $status = $locked ? 'Locked' : ($active ? 'Active' : 'Inactive');
            $statusClass = $locked ? 'bg-red-100 text-red-700' : ($active ? 'bg-green-100 text-green-700' : 'bg-slate-200 text-slate-600');
          ?>
          <tr>
            <td class="px-6 py-4 font-semibold"><?= e($account['username']) ?></td>
            <td class="px-6 py-4"><?= e($account['email'] ?: 'No email') ?></td>
            <td class="px-6 py-4"><?= e($account['employee_name'] ?? 'Unassigned') ?></td>
            <td class="px-6 py-4"><?= e($account['role_name'] ?? 'Unassigned') ?></td>
            <td class="px-6 py-4"><span class="rounded-full px-2 py-1 text-xs font-bold <?= e($statusClass) ?>"><?= e($status) ?></span></td>
            <td class="px-6 py-4"><?= e((string) ($account['failed_login_attempts'] ?? 0)) ?></td>
            <td class="px-6 py-4"><?= e($account['locked_at'] ?: '—') ?></td>
            <?php if ($canUpdate): ?>
              <td class="px-6 py-4 space-y-2">
                <?php if ($locked): ?>
                  <form method="post"><?= csrf_field() ?><input type="hidden" name="form_action" value="unlock"><input type="hidden" name="user_id" value="<?= e((string) $account['id']) ?>"><button class="font-bold text-teal-700">Unlock</button></form>
                <?php endif; ?>
                <form method="post" class="flex gap-2 items-center"><?= csrf_field() ?><input type="hidden" name="form_action" value="reset_password"><input type="hidden" name="user_id" value="<?= e((string) $account['id']) ?>"><input type="password" name="password" required autocomplete="new-password" placeholder="New password" class="field"><button class="rounded-lg border px-3 py-2 font-bold">Set password</button></form>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
