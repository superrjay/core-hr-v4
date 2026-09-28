<?php
declare(strict_types=1);

final class DocumentDraftingAI
{
    public static function generateDraft(PDO $pdo, int $employeeId, int $templateId): array
    {
        $template = $pdo->prepare('SELECT t.*, d.name AS document_type_name FROM document_templates t JOIN document_types d ON d.id = t.document_type_id WHERE t.id = ? AND t.is_active = 1 LIMIT 1');
        $template->execute([$templateId]);
        $templateRow = $template->fetch();
        if (!$templateRow) {
            return ['ok' => false, 'status' => 'INVALID_TEMPLATE', 'message' => 'Selected template is invalid.'];
        }

        $employee = phase2_employee($pdo, $employeeId);
        if (!$employee) {
            return ['ok' => false, 'status' => 'INVALID_EMPLOYEE', 'message' => 'Selected employee does not exist.'];
        }

        $context = AIContextBuilder::buildEmployeeContext($pdo, $employeeId, 'DOCUMENT');
        $context['template_context'] = [
            'document_type' => $templateRow['document_type_name'] ?? 'Not available',
            'template_name' => $templateRow['name'] ?? 'Not available',
            'template_requirements' => $templateRow['description'] ?? 'Not available',
        ];

        $prompt = PromptBuilder::documentDraftPrompt($context, $templateRow);
        $service = new GeminiService();
        $response = $service->generate($prompt, [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
                'document_type' => ['type' => 'string'],
                'content' => ['type' => 'string'],
                'used_employee_fields' => ['type' => 'array', 'items' => ['type' => 'string']],
                'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['title', 'document_type', 'content', 'used_employee_fields', 'warnings'],
        ]);

        if (!$response['ok']) {
            self::logAIGeneration($pdo, $employeeId, 'DOCUMENT_DRAFT', $response['status'], $response['message'], $service->isConfigured() ? 'gemini' : 'unconfigured');
            return ['ok' => false, 'status' => $response['status'], 'message' => $response['message']];
        }

        $validator = new AIOutputValidator();
        $validated = $validator->validateDocument($response['data'], $context, $templateRow);
        if (!$validated['valid']) {
            self::logAIGeneration($pdo, $employeeId, 'DOCUMENT_DRAFT', 'VALIDATION_FAILED', implode('; ', $validated['errors']), $response['model'] ?? 'gemini');
            return ['ok' => false, 'status' => 'VALIDATION_FAILED', 'message' => 'AI-generated content contains information requiring HR verification.', 'issues' => $validated['errors']];
        }

        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        $statement = $pdo->prepare('INSERT INTO document_drafts (employee_id, document_type_id, template_id, title, content, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $statement->execute([$employeeId, (int) $templateRow['document_type_id'], $templateId, $validated['normalized']['title'], $validated['normalized']['content'], 'DRAFT', $userId]);
        $draftId = (int) $pdo->lastInsertId();
        $versionStatement = $pdo->prepare('INSERT INTO document_versions (draft_id, version_number, content, change_description, created_by) VALUES (?, 1, ?, ?, ?)');
        $versionStatement->execute([$draftId, $validated['normalized']['content'], 'AI-generated draft', $userId]);

        self::logAIGeneration($pdo, $employeeId, 'DOCUMENT_DRAFT', 'DRAFT', 'AI document draft created', $response['model'] ?? 'gemini');

        return ['ok' => true, 'draft_id' => $draftId, 'draft' => $validated['normalized']];
    }

    public static function logAIGeneration(PDO $pdo, int $employeeId, string $feature, string $status, string $message, string $model): void
    {
        $statement = $pdo->prepare('INSERT INTO ai_generation_logs (employee_id, feature, model, status, generation_time, request_identifier, error_type, created_at) VALUES (?, ?, ?, ?, NOW(), ?, ?, NOW())');
        $statement->execute([$employeeId, $feature, $model, $status, 'manual', $message]);
    }
}
