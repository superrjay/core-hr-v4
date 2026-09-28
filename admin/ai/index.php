<?php
require_once __DIR__ . '/../../config/config.php';
require_roles(['ADMIN','HR']);
$pdo = db();
$apiConfigured = (bool) getenv('GEMINI_API_KEY');
$profileCount = (int) $pdo->query('SELECT COUNT(*) FROM profile_generations')->fetchColumn();
$profileReviewCount = (int) $pdo->query("SELECT COUNT(*) FROM profile_generations WHERE status IN ('GENERATED','REVIEWED')")->fetchColumn();
$draftCount = (int) $pdo->query('SELECT COUNT(*) FROM document_drafts')->fetchColumn();
$draftReviewCount = (int) $pdo->query("SELECT COUNT(*) FROM document_drafts WHERE status IN ('DRAFT','FOR_REVIEW')")->fetchColumn();
$approvedCount = (int) $pdo->query("SELECT COUNT(*) FROM profile_generations WHERE status = 'APPROVED'")->fetchColumn() + (int) $pdo->query("SELECT COUNT(*) FROM document_drafts WHERE status = 'APPROVED'")->fetchColumn();
$errorCount = (int) $pdo->query("SELECT COUNT(*) FROM ai_generation_logs WHERE status IN ('VALIDATION_FAILED','INVALID_JSON','MISSING_API_KEY','RATE_LIMIT','INVALID_API_KEY','TIMEOUT','HTTP_ERROR')")->fetchColumn();
$pageTitle = 'AI dashboard';
require __DIR__ . '/../../includes/header.php';
?>
<div class="mb-8 flex flex-col md:flex-row md:items-end md:justify-between gap-4">
  <div>
    <p class="text-sm text-slate-500">AI-assisted HR operations</p>
    <h1 class="font-display text-3xl font-bold mt-1">AI dashboard</h1>
  </div>
  <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800">
    <?php if ($apiConfigured): ?>Gemini is configured for server-side use.<?php else: ?>Gemini live testing is blocked until the API key is configured server-side.<?php endif; ?>
  </div>
</div>

<div class="grid md:grid-cols-2 xl:grid-cols-6 gap-4 mb-8">
  <?php foreach ([['AI Profile Generations', $profileCount], ['Pending AI Reviews', $profileReviewCount], ['AI Document Drafts', $draftCount], ['Documents Awaiting Review', $draftReviewCount], ['Approved AI-Assisted Documents', $approvedCount], ['AI Generation Errors', $errorCount]] as [$label, $value]): ?>
    <div class="metric-card bg-white border border-slate-200 rounded-xl p-5">
      <p class="text-xs uppercase tracking-wide text-slate-500 font-bold"><?= e($label) ?></p>
      <p class="font-display text-3xl font-bold mt-3"><?= e($value) ?></p>
    </div>
  <?php endforeach; ?>
</div>

<div class="grid xl:grid-cols-2 gap-6">
  <div class="bg-white border border-slate-200 rounded-xl p-6">
    <h2 class="font-display text-xl font-bold mb-4">AI tools</h2>
    <div class="space-y-3">
      <a href="<?= url('admin/ai/employee-profile.php') ?>" class="block rounded-lg border border-slate-200 p-4 hover:bg-slate-50 font-semibold">Employee profiling</a>
      <a href="<?= url('admin/ai/document-drafting.php') ?>" class="block rounded-lg border border-slate-200 p-4 hover:bg-slate-50 font-semibold">Document drafting</a>
      <a href="<?= url('admin/ai/generation-history.php') ?>" class="block rounded-lg border border-slate-200 p-4 hover:bg-slate-50 font-semibold">Generation history</a>
    </div>
  </div>
  <div class="bg-white border border-slate-200 rounded-xl p-6">
    <h2 class="font-display text-xl font-bold mb-4">Recent AI activity</h2>
    <div class="space-y-3 text-sm">
      <?php
      $recent = $pdo->query('SELECT l.*, e.first_name, e.last_name FROM ai_generation_logs l LEFT JOIN employees e ON e.id = l.employee_id ORDER BY l.created_at DESC LIMIT 8')->fetchAll();
      foreach ($recent as $row): ?>
        <div class="flex justify-between gap-3 border-b pb-2">
          <div>
            <div class="font-semibold"><?= e($row['first_name'] ?? 'Unknown') ?> <?= e($row['last_name'] ?? '') ?></div>
            <div class="text-slate-500"><?= e($row['feature']) ?> · <?= e($row['status']) ?></div>
          </div>
          <div class="text-right text-xs text-slate-500"><?= e($row['created_at']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
