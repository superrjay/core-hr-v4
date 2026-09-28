<?php
declare(strict_types=1);

final class AIContextBuilder
{
    public static function buildEmployeeContext(PDO $pdo, int $employeeId, string $feature = 'PROFILE'): array
    {
        $employee = phase2_employee($pdo, $employeeId);
        if (!$employee) {
            throw new InvalidArgumentException('Employee not found.');
        }

        $context = [
            'employee' => [
                'employee_id' => $employee['employee_number'] ?? 'Not available',
                'name' => trim((string) (($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''))),
                'first_name' => $employee['first_name'] ?? 'Not available',
                'last_name' => $employee['last_name'] ?? 'Not available',
                'position' => $employee['position_name'] ?? 'Not available',
                'department' => $employee['department_name'] ?? 'Not available',
                'branch' => $employee['branch_name'] ?? 'Not available',
                'employment_status' => $employee['employment_status'] ?? 'Not available',
                'employment_type' => $employee['employment_type'] ?? 'Not available',
                'date_hired' => $employee['date_hired'] ?? 'Not available',
                'email' => $employee['email'] ?? 'Not available',
                'phone' => $employee['phone'] ?? 'Not available',
            ],
            'employment_history' => [],
        ];

        $historyQuery = $pdo->prepare('SELECT eh.*, d.name AS department_name, p.name AS position_name, b.name AS branch_name FROM employment_histories eh LEFT JOIN departments d ON d.id = eh.department_id LEFT JOIN positions p ON p.id = eh.position_id LEFT JOIN branches b ON b.id = eh.branch_id WHERE eh.employee_id = ? ORDER BY eh.effective_date DESC, eh.id DESC');
        $historyQuery->execute([$employeeId]);

        foreach ($historyQuery->fetchAll() as $row) {
            $context['employment_history'][] = [
                'effective_date' => $row['effective_date'] ?? 'Not available',
                'event_type' => $row['event_type'] ?? 'Not available',
                'department' => $row['department_name'] ?? 'Not available',
                'position' => $row['position_name'] ?? 'Not available',
                'branch' => $row['branch_name'] ?? 'Not available',
                'status' => $row['new_status'] ?? $row['previous_status'] ?? 'Not available',
                'reason' => $row['reason'] ?? 'Not available',
            ];
        }

        if ($feature === 'DOCUMENT') {
            $context['template_context'] = [
                'document_type' => 'Not available',
                'template_name' => 'Not available',
                'template_requirements' => 'Use only verified employee data and do not invent facts.',
            ];
        }

        return $context;
    }
}
