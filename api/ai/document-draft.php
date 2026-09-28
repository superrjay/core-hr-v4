<?php
require_once __DIR__ . '/../bootstrap.php';
require_permission('ai.document.generate');
$pdo = db();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$action = $_GET['action'] ?? ($_SERVER['REQUEST_METHOD'] === 'POST' ? 'generate' : 'view');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!$id) {
        api_response(['success' => false, 'message' => 'A valid draft ID is required.'], 422);
    }
    $statement = $pdo->prepare('SELECT id, employee_id, title, status, created_at, updated_at FROM document_drafts WHERE id = ?');
    $statement->execute([$id]);
    $row = $statement->fetch();
    if (!$row) {
        api_response(['success' => false, 'message' => 'Draft not found.'], 404);
    }
    api_response(['success' => true, 'data' => $row]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}
verify_csrf();
$input = api_input();
$employeeId = (int) ($input['employee_id'] ?? $id);
$templateId = (int) ($input['template_id'] ?? 0);
if ($action === 'generate' || $action === 'regenerate') {
    if (!$employeeId || !$templateId) {
        api_response(['success' => false, 'message' => 'Employee ID and template ID are required.'], 422);
    }
    $result = DocumentDraftingAI::generateDraft($pdo, $employeeId, $templateId);
    if (!$result['ok']) {
        api_response(['success' => false, 'message' => $result['message'], 'status' => $result['status']], 422);
    }
    api_response(['success' => true, 'data' => $result], 201);
}

if (in_array($action, ['approve', 'finalize', 'submit-review', 'reject'], true)) {
    if (!$id) {
        api_response(['success' => false, 'message' => 'A valid draft ID is required.'], 422);
    }
    $map = ['submit-review' => 'FOR_REVIEW', 'approve' => 'APPROVED', 'reject' => 'DRAFT', 'finalize' => 'FINALIZED'];
    $target = $map[$action];
    $current = $pdo->prepare('SELECT status FROM document_drafts WHERE id = ?');
    $current->execute([$id]);
    $row = $current->fetch();
    if (!$row || !document_transition_allowed((string) $row['status'], $target)) {
        api_response(['success' => false, 'message' => 'Invalid document status transition.'], 422);
    }
    $pdo->prepare('UPDATE document_drafts SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?')->execute([$target, $_SESSION['user_id'], $id]);
    audit('DRAFT_' . strtoupper($action), 'document_drafts', $id);
    api_response(['success' => true, 'message' => 'Draft updated.', 'data' => ['id' => $id, 'status' => $target]]);
}

api_response(['success' => false, 'message' => 'Unsupported AI document action.'], 422);
