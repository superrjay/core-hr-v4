# Core HR Integration Contract

## Ownership

Group 4 Core HR owns the authoritative employee master record and employment state. Other modules must reference `employee_id` rather than duplicate employee master data. The authoritative tables remain in Core HR and are not copied into the other management groups.

## Ownership boundaries

### Group 1 — Supply Chain & Inventory
May reference `employee_id`, employee name, department, position, branch, and employment status. It owns operational records such as purchase orders and inventory movement.

### Group 2 — Recruitment & Onboarding
Owns applicants, hiring workflow, and onboarding tasks. It creates or activates the authoritative employee record in Core HR, then receives the resulting `employee_id` from Core HR.

### Group 3 — Financial Management
Owns financial transactions and accounting data. It may reference only the employee identity and org metadata required for an accounting transaction.

### Group 4 — Core HR
Owns employee master data, employment information, organizational assignment, HR records, employee documents, and HR AI tooling.

### Group 5 — Fleet & Transportation
Owns vehicles and trips, but references `employee_id` for driver assignment.

### Group 6 — Payroll & Benefits
Owns compensation and claimant transactions. It consumes Core HR `employee_id`, `department_id`, `position_id`, `branch_id`, `employment_status`, `employment_type`, and `date_hired` as reference dimensions.

### Group 7 — Workforce Management
Owns schedules, leave, attendance, and shift records. It references employee and organization IDs only.

### Group 8 — Performance & Development
Owns performance and training records. It references `employee_id`, `department_id`, `position_id`, and `branch_id` only.

## Integration direction

- Recruitment & Onboarding -> Core HR: approved hires create the authoritative employee record.
- Core HR -> Payroll: employee identity and employment reference data.
- Core HR -> Workforce Management: employee and org reference data.
- Core HR -> Performance & Development: employee and org reference data.
- Core HR -> Fleet: `employee_id` for driver assignments.
- Core HR -> Financial Management: employee reference and org metadata.

## Read endpoints

All current integration endpoints require an authenticated ADMIN or HR session. Public access is prohibited.

- `GET /api/integration/employees/{id}`
- `GET /api/integration/employees/{id}/employment`
- `GET /api/integration/employees/{id}/status`
- `GET /api/integration/departments/{id}`
- `GET /api/integration/positions/{id}`
- `GET /api/integration/branches/{id}`

Example response:

```json
{
  "success": true,
  "data": {
    "employee_id": 1,
    "employee_number": "EMP-0001",
    "first_name": "Amara",
    "last_name": "Reyes",
    "department_id": 1,
    "position_id": 2,
    "branch_id": 1,
    "employment_status": "Active"
  },
  "error": null
}
```

Errors use the same structure with `success: false` and a safe HTTP response. The endpoints do not expose passwords, password hashes, session tokens, API credentials, or unrelated sensitive employee information.

## Required request/response expectations

- Request IDs must be validated as integers.
- The calling user must already be authenticated and authorized.
- The responding system returns only the minimal necessary fields.
- Missing records return a safe `404` response.
- Unauthorized requests return a `403` response.

## Security requirements

- Use HTTPS in deployment.
- Enforce server-side authorization with session-based authentication.
- Validate IDs before using them in SQL.
- Only return the fields needed by the consuming module.
- Avoid exposing government numbers, bank data, credentials, or other unnecessary personal data.
- Do not rely on a browser-supplied employee ID as authorization.

## Ownership rules

- Core HR remains the source of truth for employee identity and employment lifecycle.
- Other modules remain authoritative for their own transaction data.
- Duplicate master tables are not allowed.
- Historical snapshots may only be used when explicitly required and must not replace the Core HR relationship.
