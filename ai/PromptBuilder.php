<?php
declare(strict_types=1);

final class PromptBuilder
{
    public static function employeeProfilePrompt(array $context): string
    {
        $payload = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return "You are an HR documentation assistant. Use only the verified employee information provided in the JSON below. Do not invent, infer, or assume unsupported facts. If information is unavailable, write 'Not available'. Do not make employment decisions, promote or terminate employees, or rank employees. Return valid JSON only with the following fields: professional_summary, current_role_summary, employment_history_summary, career_progression, skills_summary, training_summary, development_notes.\n\nJSON CONTEXT:\n" . $payload;
    }

    public static function documentDraftPrompt(array $context, array $template): string
    {
        $payload = json_encode([
            'employee' => $context['employee'],
            'employment_history' => $context['employment_history'],
            'document_template' => [
                'title' => $template['name'] ?? 'Document draft',
                'document_type' => $template['document_type_name'] ?? 'Not available',
                'purpose' => $template['description'] ?? 'Not available',
                'template_content' => $template['content'] ?? 'Not available',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return "You are an HR document drafting assistant. Generate a professional draft using only the verified information below. The authoritative employee values always win. Do not invent dates, names, departments, positions, or employment history. Do not change factual employee information. Do not make employment decisions or promotions. The output must be a draft for HR review. Return valid JSON only with fields: title, document_type, content, used_employee_fields, warnings.\n\nAUTHORITATIVE DATA:\n" . $payload . "\n\nINSTRUCTIONS:\n- Preserve required factual information from the template.\n- Use only the verified employee data.\n- If a value is unavailable, state 'Not available'.\n- The generated content should be ready for HR review and editing.\n- Keep the document professional and neutral.";
    }
}
