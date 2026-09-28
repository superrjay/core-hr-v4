<?php
require_once __DIR__ . '/../../config/config.php';
require_roles(['ADMIN','HR']);
$pdo = db();
$history = $pdo->query('SELECT l.*, e.employee_number, e.first_name, e.last_name FROM ai_generation_logs l LEFT JOIN employees e ON e.id = l.employee_id ORDER BY l.created_at DESC LIMIT 30')->fetchAll();
$pageTitle = 'AI generation history';
require __DIR__ . '/../../includes/header.php';
?>
<div class="mb-7"><p class="text-sm text-slate-500">Audit and generation log</p><h1 class="font-display text-3xl font-bold mt-1">AI generation history</h1></div>
<div class="bg-white border border-slate-200 rounded-xl overflow-x-auto">
  <table class="w-full text-left text-sm">
    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
      <tr><th class="px-5 py-3">Employee</th><th class="px-5 py-3">Feature</th><th class="px-5 py-3">Model</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Time</th></tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
      <?php foreach ($history as $row): ?>
        <tr>
          <td class="px-5 py-3"><?= e($row['employee_number'] ?? 'N/A') ?> · <?= e(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?></td>
          <td class="px-5 py-3"><?= e($row['feature']) ?></td>
          <td class="px-5 py-3"><?= e($row['model'] ?? 'unconfigured') ?></td>
          <td class="px-5 py-3"><?= e($row['status']) ?></td>
          <td class="px-5 py-3"><?= e($row['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
