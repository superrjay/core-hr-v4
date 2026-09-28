<?php
require_once __DIR__ . '/../bootstrap.php';
require_permission('ai.profile.generate');
$pdo = db();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$action = $_GET['action'] ?? ($_SERVER['REQUEST_METHOD'] === 'POST' ? 'generate' : 'view');
if (!$id) {
    api_response(['success' => false, 'message' => 'A valid employee ID is required.'], 422);
}
if (!phase2_employee($pdo, $id)) {
    api_response(['success' => false, 'message' => 'Employee not found.'], 404);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'view') {
    $statement = $pdo->prepare('SELECT id, employee_id, generated_content, status, model, created_at FROM profile_generations WHERE employee_id = ? ORDER BY created_at DESC LIMIT 1');
    $statement->execute([$id]);
    $row = $statement->fetch();
    if (!$row) {
        api_response(['success' => false, 'message' => 'No AI profile generation found.'], 404);
    }
    api_response(['success' => true, 'data' => $row]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}
verify_csrf();
if ($action === 'generate' || $action === 'regenerate') {
    $result = EmployeeProfiler::generate($pdo, $id);
    if (!$result['ok']) {
        api_response(['success' => false, 'message' => $result['message'], 'status' => $result['status']], 422);
    }
    api_response(['success' => true, 'data' => $result], 201);
}
api_response(['success' => false, 'message' => 'Unsupported AI profile action.'], 422);
