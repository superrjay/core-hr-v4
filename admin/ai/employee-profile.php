<?php
require_once __DIR__ . '/../../config/config.php';
require_roles(['ADMIN', 'HR']);
require_permission('ai.profile.generate');
$pdo = db();
$employees = $pdo->query('SELECT id, employee_number, first_name, last_name FROM employees ORDER BY last_name, first_name')->fetchAll();
$selectedEmployeeId = (int) ($_GET['employee_id'] ?? $_POST['employee_id'] ?? 0);
$selectedEmployee = $selectedEmployeeId ? phase2_employee($pdo, $selectedEmployeeId) : null;
$error = null;
$result = null;
$generation = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'generate';
    $employeeId = (int) ($_POST['employee_id'] ?? 0);
    if (!$employeeId) {
        $error = 'Select an employee before generating the profile.';
    } else {
        if ($action === 'generate') {
            $result = EmployeeProfiler::generate($pdo, $employeeId);
            if (!$result['ok']) {
                $error = $result['message'];
            } else {
                $generation = $result['profile'];
            }
        } elseif ($action === 'save-review') {
            $payload = trim((string) ($_POST['profile_content'] ?? ''));
            if ($payload === '') {
                $error = 'Profile content cannot be empty.';
            } else {
                $decoded = json_decode($payload, true);
                if (!is_array($decoded)) {
                    $error = 'Profile content must be valid JSON.';
                } else {
                    $update = $pdo->prepare('UPDATE profile_generations SET generated_content = ?, status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE employee_id = ? ORDER BY created_at DESC LIMIT 1');
                    $update->execute([json_encode($decoded, JSON_UNESCAPED_SLASHES), 'REVIEWED', $_SESSION['user_id'], $employeeId]);
                    $generation = $decoded;
                }
            }
        } elseif ($action === 'approve') {
            $update = $pdo->prepare('UPDATE profile_generations SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE employee_id = ? ORDER BY created_at DESC LIMIT 1');
            $update->execute(['APPROVED', $_SESSION['user_id'], $employeeId]);
            $generation = ['status' => 'APPROVED'];
        } elseif ($action === 'reject') {
            $update = $pdo->prepare('UPDATE profile_generations SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE employee_id = ? ORDER BY created_at DESC LIMIT 1');
            $update->execute(['REJECTED', $_SESSION['user_id'], $employeeId]);
            $generation = ['status' => 'REJECTED'];
        }
    }
}

$latestProfile = $selectedEmployeeId ? $pdo->prepare('SELECT * FROM profile_generations WHERE employee_id = ? ORDER BY created_at DESC LIMIT 1') : null;
if ($selectedEmployeeId && $latestProfile) {
    $latestProfile->execute([$selectedEmployeeId]);
    $latestProfileRow = $latestProfile->fetch();
    if ($latestProfileRow) {
        $generation = $generation ?: json_decode((string) $latestProfileRow['generated_content'], true);
    }
}
$pageTitle = 'Employee profiling';
require __DIR__ . '/../../includes/header.php';
?>
<div class="mb-7">
  <p class="text-sm text-slate-500">AI-assisted employee profiling</p>
  <h1 class="font-display text-3xl font-bold mt-1">Profile generation</h1>
</div>
<?php if ($error): ?><div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= e($error) ?></div><?php endif; ?>
<div class="grid xl:grid-cols-[340px_1fr] gap-6">
  <form method="post" class="bg-white border border-slate-200 rounded-xl p-6 space-y-4">
    <?= csrf_field() ?>
    <label class="text-sm font-semibold text-slate-700">Select employee
      <select name="employee_id" class="field !mt-2" onchange="this.form.submit()">
        <option value="">Choose employee</option>
        <?php foreach ($employees as $employee): ?>
          <option value="<?= e($employee['id']) ?>" <?= (string) $selectedEmployeeId === (string) $employee['id'] ? 'selected' : '' ?>><?= e($employee['employee_number']) ?> · <?= e($employee['first_name']) ?> <?= e($employee['last_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <?php if ($selectedEmployee): ?>
      <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm space-y-1">
        <div><strong>Verified data</strong></div>
        <div><?= e($selectedEmployee['employee_number']) ?> · <?= e($selectedEmployee['first_name']) ?> <?= e($selectedEmployee['last_name']) ?></div>
        <div><?= e($selectedEmployee['position_name'] ?? 'Not available') ?> · <?= e($selectedEmployee['department_name'] ?? 'Not available') ?></div>
        <div><?= e($selectedEmployee['branch_name'] ?? 'Not available') ?> · <?= e($selectedEmployee['employment_status'] ?? 'Not available') ?></div>
      </div>
    <?php endif; ?>
    <input type="hidden" name="action" value="generate">
    <button type="submit" class="w-full rounded-lg bg-teal-700 px-5 py-3 text-sm font-bold text-white">Generate AI profile</button>
  </form>

  <div class="bg-white border border-slate-200 rounded-xl p-6">
    <?php if ($generation): ?>
      <div class="mb-6 flex items-center justify-between gap-3">
        <div>
          <p class="text-sm text-slate-500">AI-generated content — HR review required</p>
          <h2 class="font-display text-2xl font-bold">Generated profile</h2>
        </div>
        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold uppercase tracking-wide text-amber-800">AI GENERATED</span>
      </div>
      <div class="space-y-4">
        <?php foreach ($generation as $key => $value): ?>
          <?php if (is_string($value)): ?>
            <div class="rounded-xl border border-slate-200 p-4">
              <h3 class="font-semibold capitalize text-slate-800 mb-2"><?= e(str_replace('_', ' ', $key)) ?></h3>
              <p class="text-sm text-slate-700 whitespace-pre-wrap"><?= e($value) ?></p>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
      <form method="post" class="mt-6 space-y-4">
        <?= csrf_field() ?>
        <input type="hidden" name="employee_id" value="<?= e((string) $selectedEmployeeId) ?>">
        <textarea name="profile_content" rows="12" class="field !mt-0" placeholder='Paste JSON profile from Gemini'><?= e(json_encode($generation, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></textarea>
        <div class="flex flex-wrap gap-3">
          <button type="submit" name="action" value="save-review" class="rounded-lg bg-slate-900 px-5 py-3 text-sm font-bold text-white">Save review</button>
          <button type="submit" name="action" value="approve" class="rounded-lg bg-emerald-700 px-5 py-3 text-sm font-bold text-white">Approve</button>
          <button type="submit" name="action" value="reject" class="rounded-lg bg-red-600 px-5 py-3 text-sm font-bold text-white">Reject</button>
        </div>
      </form>
    <?php else: ?>
      <div class="rounded-xl border border-dashed border-slate-300 p-10 text-center text-slate-500">Select an employee and generate the AI profile to begin.</div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
