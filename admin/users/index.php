<?php
require_once __DIR__ . '/../../config/config.php';
require_roles(['ADMIN']);
require_permission('users.view');
$pdo = db();
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $employeeId = (int) ($_POST['employee_id'] ?? 0) ?: null;
    $roleId = (int) ($_POST['role_id'] ?? 0) ?: null;
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($username === '' || $password === '' || !$roleId) {
        $error = 'Username, password, and role are required.';
    } else {
        try {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (employee_id, role_id, username, password_hash, is_active) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$employeeId, $roleId, $username, $hashed, $isActive]);
            $userId = (int) $pdo->lastInsertId();
            $pdo->prepare('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)')->execute([$userId, $roleId]);
            audit('USER_CREATED', 'users', $userId, 'USER_MANAGEMENT', 'SUCCESS', ['username' => $username]);
            flash('success', 'User account created.');
            redirect('admin/users/index.php');
        } catch (Throwable $e) {
            $error = 'Could not create the user account. Check that the username and employee link are unique.';
        }
    }
}

$users = $pdo->query('SELECT u.id, u.username, u.is_active, u.employee_id, CONCAT(e.first_name, " ", e.last_name) AS employee_name, r.name AS role_name FROM users u LEFT JOIN employees e ON e.id = u.employee_id JOIN roles r ON r.id = u.role_id ORDER BY u.username')->fetchAll();
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
  <form method="post" class="grid md:grid-cols-2 xl:grid-cols-5 gap-4">
    <?= csrf_field() ?>
    <label class="text-sm font-semibold">Username<input required name="username" class="field"></label>
    <label class="text-sm font-semibold">Password<input required type="password" name="password" class="field"></label>
    <label class="text-sm font-semibold">Role<select name="role_id" class="field"><option value="">Select</option><?php foreach ($roles as $role): ?><option value="<?= e((string) $role['id']) ?>"><?= e($role['name']) ?></option><?php endforeach; ?></select></label>
    <label class="text-sm font-semibold">Linked employee<select name="employee_id" class="field"><option value="">None</option><?php foreach ($employees as $employee): ?><option value="<?= e((string) $employee['id']) ?>"><?= e($employee['name']) ?> (<?= e($employee['employee_number']) ?>)</option><?php endforeach; ?></select></label>
    <label class="inline-flex items-center gap-2 text-sm font-semibold mt-6"><input type="checkbox" name="is_active" checked> Active</label>
    <div class="md:col-span-2 xl:col-span-5 text-right"><button class="rounded-lg bg-teal-700 text-white px-5 py-3 font-bold">Create user</button></div>
  </form>
</div>

<section class="bg-white border border-slate-200 rounded-xl overflow-hidden">
  <div class="px-6 py-4 border-b border-slate-200"><h2 class="font-display text-xl font-bold">User list</h2></div>
  <div class="overflow-x-auto">
    <table class="w-full text-left text-sm">
      <thead class="bg-slate-50 text-xs uppercase text-slate-500">
        <tr><th class="px-6 py-3">Username</th><th class="px-6 py-3">Employee</th><th class="px-6 py-3">Role</th><th class="px-6 py-3">Status</th></tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($users as $user): ?>
          <tr>
            <td class="px-6 py-4 font-semibold"><?= e($user['username']) ?></td>
            <td class="px-6 py-4"><?= e($user['employee_name'] ?? 'Unassigned') ?></td>
            <td class="px-6 py-4"><?= e($user['role_name'] ?? 'Unassigned') ?></td>
            <td class="px-6 py-4"><span class="rounded-full px-2 py-1 text-xs font-bold <?= $user['is_active'] ? 'bg-green-100 text-green-700' : 'bg-slate-200 text-slate-600' ?>"><?= $user['is_active'] ? 'Active' : 'Inactive' ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
