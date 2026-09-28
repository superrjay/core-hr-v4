<?php
require_once __DIR__ . '/config/config.php';
$user = require_auth();
$pdo = db();
$userId = (int) $user['id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['form_action'] ?? '');
    if ($action === 'read_all') {
        $pdo->prepare('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0')->execute([$userId]);
        flash('success', 'All notifications marked as read.');
    } elseif ($action === 'read') {
        $pdo->prepare('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?')->execute([(int) ($_POST['id'] ?? 0), $userId]);
        flash('success', 'Notification marked as read.');
    }
    redirect('notifications.php');
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$count = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ?');
$count->execute([$userId]);
$total = (int) $count->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
if ($page > $pages) {
    $page = $pages;
}
$offset = ($page - 1) * $perPage;
$statement = $pdo->prepare('SELECT id, title, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?');
$statement->bindValue(1, $userId, PDO::PARAM_INT);
$statement->bindValue(2, $perPage, PDO::PARAM_INT);
$statement->bindValue(3, $offset, PDO::PARAM_INT);
$statement->execute();
$notes = $statement->fetchAll();
$pageTitle = 'Notifications';
require __DIR__ . '/includes/header.php';
?>
<div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-6">
  <div>
    <p class="text-sm text-slate-500">Your account only</p>
    <h1 class="font-display text-3xl font-bold mt-1">Notifications</h1>
  </div>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="form_action" value="read_all"><button class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-bold">Mark all read</button></form>
</div>
<section class="bg-white border border-slate-200 rounded-xl overflow-hidden">
  <?php if (!$notes): ?><p class="p-8 text-sm text-slate-500">No notifications yet.</p><?php endif; ?>
  <?php foreach ($notes as $note): ?>
    <article class="px-6 py-4 border-b border-slate-100 flex flex-col md:flex-row md:items-start md:justify-between gap-3">
      <div>
        <p class="font-bold <?= (int) $note['is_read'] === 1 ? 'text-slate-500' : '' ?>"><?= e($note['title']) ?></p>
        <p class="text-sm text-slate-600 mt-1 whitespace-pre-wrap"><?= e($note['message']) ?></p>
        <p class="text-xs text-slate-400 mt-2"><?= e(date('M j, Y g:i A', strtotime((string) $note['created_at']))) ?></p>
      </div>
      <?php if ((int) $note['is_read'] !== 1): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="form_action" value="read"><input type="hidden" name="id" value="<?= e((string) $note['id']) ?>"><button class="text-sm font-bold text-teal-700">Mark read</button></form>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
</section>
<?php if ($pages > 1): ?>
  <div class="flex gap-2 mt-6 text-sm font-bold">
    <?php if ($page > 1): ?><a class="rounded-lg border px-3 py-2" href="?page=<?= e((string) ($page - 1)) ?>">Previous</a><?php endif; ?>
    <span class="px-3 py-2 text-slate-500">Page <?= e((string) $page) ?> of <?= e((string) $pages) ?></span>
    <?php if ($page < $pages): ?><a class="rounded-lg border px-3 py-2" href="?page=<?= e((string) ($page + 1)) ?>">Next</a><?php endif; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
