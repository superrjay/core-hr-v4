<?php
require_once __DIR__ . '/bootstrap.php';
$definitions = ['departments' => 'departments', 'positions' => 'positions', 'branches' => 'branches']; $type = $_GET['type'] ?? ''; if (!isset($definitions[$type])) api_response(['error' => 'Unknown catalog.'], 404); $table = $definitions[$type];
if ($_SERVER['REQUEST_METHOD'] !== 'GET') api_response(['error' => 'Read-only endpoint in Phase 1.'], 405);
api_response(['data' => db()->query("SELECT * FROM {$table} WHERE is_active=1 ORDER BY name")->fetchAll()]);