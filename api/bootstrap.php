<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json; charset=utf-8');
function api_response(mixed $data, int $status = 200): never { http_response_code($status); echo json_encode($data, JSON_UNESCAPED_SLASHES); exit; }
require_api_session(static function (): void { api_response(['error' => 'Authentication required.'], 401); });
if (!hasAnyRole(['ADMIN', 'HR'])) api_response(['success' => false, 'message' => 'You do not have permission to perform this action.'], 403);
function api_input(): array { $input = json_decode(file_get_contents('php://input'), true); return is_array($input) ? $input : $_POST; }