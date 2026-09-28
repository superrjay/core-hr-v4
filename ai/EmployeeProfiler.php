<?php
declare(strict_types=1);

final class EmployeeProfiler
{
    public static function generate(PDO $pdo, int $employeeId): array
    {
        ensure_ai_tables($pdo);

        $context = AIContextBuilder::buildEmployeeContext($pdo, $employeeId, 'PROFILE');
        $prompt = PromptBuilder::employeeProfilePrompt($context);
        $gemini = new GeminiService();
        $response = $gemini->generate($prompt, [
            'type' => 'object',
            'properties' => [
                'professional_summary' => ['type' => 'string'],
                'current_role_summary' => ['type' => 'string'],
                'employment_history_summary' => ['type' => 'string'],
                'career_progression' => ['type' => 'string'],
                'skills_summary' => ['type' => 'string'],
                'training_summary' => ['type' => 'string'],
                'development_notes' => ['type' => 'string'],
            ],
            'required' => ['professional_summary', 'current_role_summary', 'employment_history_summary', 'career_progression', 'skills_summary', 'training_summary', 'development_notes'],
        ]);

        if (!$response['ok']) {
            self::logGeneration($pdo, $employeeId, 'EMPLOYEE_PROFILE', $response['status'], $response['message'], $gemini->isConfigured() ? 'gemini' : 'unconfigured');
            return ['ok' => false, 'status' => $response['status'], 'message' => $response['message']];
        }

        $validator = new AIOutputValidator();
        $validated = $validator->validateProfile($response['data'], $context);
        if (!$validated['valid']) {
            self::logGeneration($pdo, $employeeId, 'EMPLOYEE_PROFILE', 'VALIDATION_FAILED', implode('; ', $validated['errors']), $response['model'] ?? 'gemini');
            return ['ok' => false, 'status' => 'VALIDATION_FAILED', 'message' => 'AI output requires review or regeneration.', 'issues' => $validated['errors']];
        }

        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        $statement = $pdo->prepare('INSERT INTO profile_generations (employee_id, generated_content, status, generated_by, model) VALUES (?, ?, ?, ?, ?)');
        $statement->execute([
            $employeeId,
            json_encode($validated['normalized'], JSON_UNESCAPED_SLASHES),
            'GENERATED',
            $userId,
            $response['model'] ?? 'gemini',
        ]);

        self::logGeneration($pdo, $employeeId, 'EMPLOYEE_PROFILE', 'GENERATED', 'AI profile generated', $response['model'] ?? 'gemini');

        return ['ok' => true, 'profile' => $validated['normalized'], 'generation_id' => (int) $pdo->lastInsertId()];
    }

    public static function logGeneration(PDO $pdo, int $employeeId, string $feature, string $status, string $message, string $model): void
    {
        $statement = $pdo->prepare('INSERT INTO ai_generation_logs (employee_id, feature, model, status, generation_time, request_identifier, error_type, created_at) VALUES (?, ?, ?, ?, NOW(), ?, ?, NOW())');
        $statement->execute([$employeeId, $feature, $model, $status, 'manual', $message]);
    }
}
