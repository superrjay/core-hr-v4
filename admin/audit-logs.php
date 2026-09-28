<?php
require_once __DIR__ . '/../config/config.php';
require_roles(['ADMIN', 'HR']);
require_permission('audit.view');
$pdo = db();
$logs = $pdo->query('SELECT a.id, a.action, a.entity_type, a.entity_id, a.module, a.result, a.ip_address, a.created_at, u.username FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.created_at DESC LIMIT 100')->fetchAll();
$pageTitle = 'Audit logs';
require __DIR__ . '/../includes/header.php';
?>
<div class="mb-7">
  <p class="text-sm text-slate-500">Security and activity trail</p>
  <h1 class="font-display text-3xl font-bold mt-1">Audit logs</h1>
</div>
<section class="bg-white border border-slate-200 rounded-xl overflow-x-auto">
  <table class="w-full text-left text-sm">
    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
      <tr>
        <th class="px-5 py-3">Time</th>
        <th class="px-5 py-3">User</th>
        <th class="px-5 py-3">Action</th>
        <th class="px-5 py-3">Entity</th>
        <th class="px-5 py-3">Result</th>
        <th class="px-5 py-3">IP</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
      <?php foreach ($logs as $log): ?>
        <tr>
          <td class="px-5 py-3 whitespace-nowrap"><?= e($log['created_at']) ?></td>
          <td class="px-5 py-3"><?= e($log['username'] ?? 'System') ?></td>
          <td class="px-5 py-3 font-semibold"><?= e($log['action']) ?></td>
          <td class="px-5 py-3"><?= e($log['entity_type']) ?> #<?= e((string) ($log['entity_id'] ?? '')) ?></td>
          <td class="px-5 py-3"><?= e($log['result'] ?? 'SUCCESS') ?></td>
          <td class="px-5 py-3"><?= e($log['ip_address'] ?? '—') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php if (!$logs): ?><p class="p-10 text-center text-slate-500">No audit events yet.</p><?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
