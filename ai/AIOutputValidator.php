<?php
declare(strict_types=1);

final class AIOutputValidator
{
    public function validateProfile(array $payload, array $context): array
    {
        $required = ['professional_summary', 'current_role_summary', 'employment_history_summary', 'career_progression', 'skills_summary', 'training_summary', 'development_notes'];
        $issues = [];

        if (!is_array($payload)) {
            return ['valid' => false, 'errors' => ['Profile payload is not a valid object.']];
        }

        foreach ($required as $field) {
            if (!array_key_exists($field, $payload) || !is_string($payload[$field])) {
                $issues[] = 'Missing or invalid field: ' . $field;
            }
        }

        if ($issues !== []) {
            return ['valid' => false, 'errors' => $issues];
        }

        $employee = $context['employee'] ?? [];
        foreach ($required as $field) {
            $text = strtolower((string) $payload[$field]);
            if (preg_match('/\b(?:certification|degree|salary|bonus|promotion eligibility|termination|disciplinary|ranked|hire decision)\b/', $text)) {
                $issues[] = 'Field "' . $field . '" contains unsupported employment recommendation or decision language.';
            }

            $foundYears = preg_match_all('/\b(?:19|20)\d{2}\b/', $text, $matches);
            if ($foundYears > 0) {
                $contextValues = [
                    (string) ($employee['date_hired'] ?? ''),
                    (string) ($employee['employment_status'] ?? ''),
                    (string) ($employee['position'] ?? ''),
                    (string) ($employee['department'] ?? ''),
                    (string) ($employee['branch'] ?? ''),
                ];
                $contextYears = implode(' ', array_filter($contextValues, static fn($value) => $value !== ''));
                foreach ($matches[0] as $year) {
                    if (!str_contains($contextYears, (string) $year)) {
                        $issues[] = 'Field "' . $field . '" contains an unsupported year: ' . $year;
                    }
                }
            }
        }

        $generated = [
            'professional_summary' => (string) ($payload['professional_summary'] ?? ''),
            'current_role_summary' => (string) ($payload['current_role_summary'] ?? ''),
            'employment_history_summary' => (string) ($payload['employment_history_summary'] ?? ''),
            'career_progression' => (string) ($payload['career_progression'] ?? ''),
            'skills_summary' => (string) ($payload['skills_summary'] ?? ''),
            'training_summary' => (string) ($payload['training_summary'] ?? ''),
            'development_notes' => (string) ($payload['development_notes'] ?? ''),
        ];

        return ['valid' => empty($issues), 'errors' => $issues, 'normalized' => $generated];
    }

    public function validateDocument(array $payload, array $context, array $template): array
    {
        $required = ['title', 'document_type', 'content', 'used_employee_fields', 'warnings'];
        $issues = [];

        if (!is_array($payload)) {
            return ['valid' => false, 'errors' => ['Document payload is not a valid object.']];
        }

        foreach ($required as $field) {
            if (!array_key_exists($field, $payload)) {
                $issues[] = 'Missing field: ' . $field;
            }
        }

        $employee = $context['employee'] ?? [];
        $content = (string) ($payload['content'] ?? '');
        $title = (string) ($payload['title'] ?? '');
        $documentType = (string) ($payload['document_type'] ?? '');

        if ($content === '' || $title === '') {
            $issues[] = 'Document title and content are required.';
        }

        $inDatabaseFields = [
            strtolower((string) ($employee['employee_id'] ?? '')),
            strtolower((string) ($employee['name'] ?? '')),
            strtolower((string) ($employee['position'] ?? '')),
            strtolower((string) ($employee['department'] ?? '')),
            strtolower((string) ($employee['branch'] ?? '')),
            strtolower((string) ($employee['date_hired'] ?? '')),
        ];

        foreach ($inDatabaseFields as $value) {
            if ($value !== '' && $value !== 'not available') {
                $lower = strtolower($content);
                if (str_contains($lower, $value) === false && $content !== '') {
                    $issues[] = 'Document content appears missing an authoritative employee value.';
                    break;
                }
            }
        }

        $unsupported = ['promotion eligibility', 'termination', 'disciplinary', 'salary', 'bonus', 'rank employee'];
        foreach ($unsupported as $phrase) {
            if (stripos($content, $phrase) !== false) {
                $issues[] = 'Document content contains unsupported HR recommendation language.';
            }
        }

        $safeWarnings = is_array($payload['warnings'] ?? null) ? $payload['warnings'] : [];

        $normalized = [
            'title' => $title,
            'document_type' => $documentType,
            'content' => $content,
            'used_employee_fields' => is_array($payload['used_employee_fields'] ?? null) ? array_values($payload['used_employee_fields']) : [],
            'warnings' => $safeWarnings,
        ];

        return ['valid' => empty($issues), 'errors' => $issues, 'normalized' => $normalized];
    }
}
