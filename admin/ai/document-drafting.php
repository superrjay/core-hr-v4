<?php
require_once __DIR__ . '/../../config/config.php';
require_roles(['ADMIN', 'HR']);
require_permission('ai.document.generate');
$pdo = db();
$employees = $pdo->query('SELECT id, employee_number, first_name, last_name FROM employees ORDER BY last_name, first_name')->fetchAll();
$documentTypes = $pdo->query('SELECT * FROM document_types WHERE is_active = 1 ORDER BY name')->fetchAll();
$templates = $pdo->query('SELECT t.*, d.name AS document_type_name FROM document_templates t JOIN document_types d ON d.id = t.document_type_id WHERE t.is_active = 1 ORDER BY t.name')->fetchAll();
$selectedEmployeeId = (int) ($_GET['employee_id'] ?? $_POST['employee_id'] ?? 0);
$selectedTemplateId = (int) ($_GET['template_id'] ?? $_POST['template_id'] ?? 0);
$selectedEmployee = $selectedEmployeeId ? phase2_employee($pdo, $selectedEmployeeId) : null;
$error = null;
$draft = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $employeeId = (int) ($_POST['employee_id'] ?? 0);
    $templateId = (int) ($_POST['template_id'] ?? 0);
    $action = $_POST['action'] ?? 'generate';

    if (!$employeeId || !$templateId) {
        $error = 'Employee and template are required.';
    } else {
        if ($action === 'generate') {
            $result = DocumentDraftingAI::generateDraft($pdo, $employeeId, $templateId);
            if (!$result['ok']) {
                $error = $result['message'];
            } else {
                $draft = $result['draft'];
                $draft['id'] = $result['draft_id'];
            }
        } elseif ($action === 'save-review') {
            $content = trim((string) ($_POST['draft_content'] ?? ''));
            $draftId = (int) ($_POST['draft_id'] ?? 0);
            if ($content === '' || !$draftId) {
                $error = 'Draft content cannot be empty.';
            } else {
                $current = $pdo->prepare('SELECT status FROM document_drafts WHERE id = ?');
                $current->execute([$draftId]);
                $row = $current->fetch();
                if (!$row || !document_transition_allowed((string) $row['status'], 'FOR_REVIEW')) {
                    $error = 'Invalid document status transition.';
                } else {
                    $statement = $pdo->prepare('UPDATE document_drafts SET content = ?, status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?');
                    $statement->execute([$content, 'FOR_REVIEW', $_SESSION['user_id'], $draftId]);
                    $draft = ['content' => $content, 'id' => $draftId, 'status' => 'FOR_REVIEW'];
                }
            }
        } elseif ($action === 'approve') {
            $draftId = (int) ($_POST['draft_id'] ?? 0);
            $current = $pdo->prepare('SELECT status FROM document_drafts WHERE id = ?');
            $current->execute([$draftId]);
            $row = $current->fetch();
            if (!$draftId || !$row || !document_transition_allowed((string) $row['status'], 'APPROVED')) {
                $error = 'Invalid document status transition.';
            } else {
                $statement = $pdo->prepare('UPDATE document_drafts SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?');
                $statement->execute(['APPROVED', $_SESSION['user_id'], $draftId]);
                $draft = ['status' => 'APPROVED', 'id' => $draftId];
            }
        } elseif ($action === 'finalize') {
            $draftId = (int) ($_POST['draft_id'] ?? 0);
            $current = $pdo->prepare('SELECT status FROM document_drafts WHERE id = ?');
            $current->execute([$draftId]);
            $row = $current->fetch();
            if (!$draftId || !$row || !document_transition_allowed((string) $row['status'], 'FINALIZED')) {
                $error = 'Invalid document status transition.';
            } else {
                $statement = $pdo->prepare('UPDATE document_drafts SET status = ?, finalized_by = ?, finalized_at = NOW() WHERE id = ?');
                $statement->execute(['FINALIZED', $_SESSION['user_id'], $draftId]);
                $draft = ['status' => 'FINALIZED', 'id' => $draftId];
            }
        }
    }
}
$pageTitle = 'AI document drafting';
require __DIR__ . '/../../includes/header.php';
?>
<div class="mb-7">
  <p class="text-sm text-slate-500">AI-assisted HR drafting</p>
  <h1 class="font-display text-3xl font-bold mt-1">Document drafting</h1>
</div>
<?php if ($error): ?><div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= e($error) ?></div><?php endif; ?>
<div class="grid xl:grid-cols-[340px_1fr] gap-6">
  <form method="post" class="bg-white border border-slate-200 rounded-xl p-6 space-y-4">
    <?= csrf_field() ?>
    <label class="text-sm font-semibold text-slate-700">Select employee
      <select name="employee_id" class="field !mt-2">
        <option value="">Choose employee</option>
        <?php foreach ($employees as $employee): ?>
          <option value="<?= e($employee['id']) ?>" <?= (string) $selectedEmployeeId === (string) $employee['id'] ? 'selected' : '' ?>><?= e($employee['employee_number']) ?> · <?= e($employee['first_name']) ?> <?= e($employee['last_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="text-sm font-semibold text-slate-700">Select template
      <select name="template_id" class="field !mt-2">
        <option value="">Choose template</option>
        <?php foreach ($templates as $template): ?>
          <option value="<?= e($template['id']) ?>" <?= (string) $selectedTemplateId === (string) $template['id'] ? 'selected' : '' ?>><?= e($template['document_type_name']) ?> · <?= e($template['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <input type="hidden" name="action" value="generate">
    <button type="submit" class="w-full rounded-lg bg-teal-700 px-5 py-3 text-sm font-bold text-white">Generate draft</button>
  </form>

  <div class="bg-white border border-slate-200 rounded-xl p-6">
    <?php if ($draft): ?>
      <div class="mb-4 flex items-center justify-between gap-3">
        <div>
          <p class="text-sm text-slate-500">AI-generated draft · HR review required</p>
          <h2 class="font-display text-2xl font-bold">Draft preview</h2>
        </div>
        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold uppercase tracking-wide text-amber-800">AI GENERATED</span>
      </div>
      <form method="post" class="space-y-4">
        <?= csrf_field() ?>
        <input type="hidden" name="employee_id" value="<?= e((string) ($selectedEmployeeId ?: $_POST['employee_id'] ?? 0)) ?>">
        <input type="hidden" name="template_id" value="<?= e((string) ($selectedTemplateId ?: $_POST['template_id'] ?? 0)) ?>">
        <input type="hidden" name="draft_id" value="<?= e((string) ($draft['id'] ?? $_POST['draft_id'] ?? 0)) ?>">
        <label class="text-sm font-semibold text-slate-700">Title
          <input name="draft_title" class="field !mt-2" value="<?= e((string) ($draft['title'] ?? '')) ?>">
        </label>
        <label class="text-sm font-semibold text-slate-700">Content
          <textarea name="draft_content" rows="18" class="field !mt-2"><?= e((string) ($draft['content'] ?? '')) ?></textarea>
        </label>
        <div class="flex flex-wrap gap-3">
          <button type="submit" name="action" value="save-review" class="rounded-lg bg-slate-900 px-5 py-3 text-sm font-bold text-white">Save for review</button>
          <button type="submit" name="action" value="approve" class="rounded-lg bg-emerald-700 px-5 py-3 text-sm font-bold text-white">Approve</button>
          <button type="submit" name="action" value="finalize" class="rounded-lg bg-indigo-700 px-5 py-3 text-sm font-bold text-white">Finalize</button>
        </div>
      </form>
    <?php else: ?>
      <div class="rounded-xl border border-dashed border-slate-300 p-10 text-center text-slate-500">Choose an employee and a template to generate an AI-assisted document draft.</div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
