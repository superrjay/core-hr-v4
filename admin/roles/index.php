<?php
require_once __DIR__ . '/../../config/config.php';
require_roles(['ADMIN']);
require_permission('roles.manage');
$pdo = db();
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    if ($name === '') {
        $error = 'Role name is required.';
    } else {
        try {
            $pdo->prepare('INSERT INTO roles (name, description) VALUES (?, ?)')->execute([$name, $description]);
            audit('ROLE_CREATED', 'roles', (int) $pdo->lastInsertId(), 'USER_MANAGEMENT', 'SUCCESS', ['name' => $name]);
            flash('success', 'Role created.');
            redirect('admin/roles/index.php');
        } catch (Throwable $e) {
            $error = 'Role could not be created.';
        }
    }
}

$roles = $pdo->query('SELECT r.id, r.name, r.description, COUNT(rp.permission_id) AS permission_count FROM roles r LEFT JOIN role_permissions rp ON rp.role_id = r.id GROUP BY r.id, r.name, r.description ORDER BY r.name')->fetchAll();
$permissions = $pdo->query('SELECT id, name, description FROM permissions ORDER BY name')->fetchAll();
$pageTitle = 'Role management';
require __DIR__ . '/../../includes/header.php';
?>
<div class="flex justify-between items-end mb-6">
  <div>
    <p class="text-sm text-slate-500">Authorization administration</p>
    <h1 class="font-display text-3xl font-bold mt-1">Roles</h1>
  </div>
</div>

<div class="bg-white border border-slate-200 rounded-xl p-6 mb-8">
  <h2 class="font-display text-xl font-bold mb-4">Create role</h2>
  <?php if ($error): ?><div class="mb-4 rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-700"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="grid md:grid-cols-2 gap-4">
    <?= csrf_field() ?>
    <label class="text-sm font-semibold">Role name<input required name="name" class="field"></label>
    <label class="text-sm font-semibold">Description<input name="description" class="field"></label>
    <div class="md:col-span-2 text-right"><button class="rounded-lg bg-teal-700 text-white px-5 py-3 font-bold">Create role</button></div>
  </form>
</div>

<div class="grid xl:grid-cols-2 gap-6">
  <section class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200"><h2 class="font-display text-xl font-bold">Role list</h2></div>
    <div class="divide-y divide-slate-100">
      <?php foreach ($roles as $role): ?>
        <div class="px-6 py-4 flex justify-between items-center">
          <div>
            <div class="font-bold"><?= e($role['name']) ?></div>
            <div class="text-xs text-slate-500"><?= e($role['description'] ?: 'No description') ?></div>
          </div>
          <span class="text-xs font-bold text-slate-600"><?= e((string) $role['permission_count']) ?> permissions</span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200"><h2 class="font-display text-xl font-bold">Permissions</h2></div>
    <div class="divide-y divide-slate-100">
      <?php foreach ($permissions as $permission): ?>
        <div class="px-6 py-4">
          <div class="font-semibold"><?= e($permission['name']) ?></div>
          <div class="text-xs text-slate-500"><?= e($permission['description'] ?: 'No description') ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
