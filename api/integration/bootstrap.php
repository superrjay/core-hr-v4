<?php
require_once __DIR__.'/../../config/config.php';
header('Content-Type: application/json; charset=utf-8');
function integration_response(mixed $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode(['success' => $status < 400, 'data' => $status < 400 ? $data : null, 'error' => $status >= 400 ? $data : null], JSON_UNESCAPED_SLASHES);
    exit;
}
require_api_session(static function (): void { integration_response('Authentication required.', 401); });
$user = current_user();
if (!hasPermission('integration.view') && !hasAnyRole(['ADMIN', 'HR', 'SYSTEM_INTEGRATION'])) {
    integration_response('Integration authorization required.', 403);
}
function integration_id(): int
{
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        integration_response('A valid ID is required.', 422);
    }
    return $id;
}
