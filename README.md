# Core HR Phase 1

Pure PHP/MySQL foundation for Group 4 of the Microfinancial Management System. The employee record is the authoritative master record; a user account is optional and linked with `users.employee_id`.

## XAMPP setup

1. Copy this folder to `C:\xampp\htdocs\core-hr`.
2. Start Apache and MySQL from the XAMPP Control Panel.
3. Open phpMyAdmin, select **Import**, and run `database/schema.sql`, then `database/seed.sql`.
4. Review `config/database.php` if your MySQL host, port, username, or password differs from the XAMPP defaults. Environment variables beginning with `CORE_HR_DB_` also override the defaults.
5. Open [http://localhost/core-hr/](http://localhost/core-hr/).

## Development accounts

All seed accounts use the password `password` for local development only. Change or remove these accounts before deployment.

| Username | Role |
| --- | --- |
| `admin` | ADMIN |
| `hr.manager` | HR |
| `employee.benjie` | EMPLOYEE |
| `employee.celeste` | EMPLOYEE |

## Phase 1 scope

Implemented: session login/logout, role checks, CSRF-protected browser forms, dashboard metrics and recent activity, employee CRUD, department/position/branch CRUD, PDO prepared statements, escaped output, audit events, and authenticated JSON APIs. ESS, advanced records, Gemini, payroll, recruitment, workforce, performance, fleet, and financial features are deliberately deferred.

See [docs/implementation-progress.md](docs/implementation-progress.md) for the full implementation record, schema, routes, validation status, and known issues.

## Phase 2

Phase 2 adds Core HCM and Employee Records: searchable employee management, profile cards, lifecycle transactions, employment history, document types, safe templates, employee documents, manual drafts, version records, review transitions, and authenticated history/lifecycle/document APIs. Import `database/phase2-seed.sql` after the schema and Phase 1 seed. Gemini, ESS, and other group subsystems remain deferred.

## Phase 3 ESS and integration

Phase 3 adds ownership-scoped Employee Self-Service pages, allowlisted profile change requests, HR approval/rejection, database notifications, and authenticated read-only integration endpoints. Import `database/phase3-seed.sql` after the Phase 2 seed. The configured development database is `core_hr_1`; the import order is `database/schema.sql`, `database/seed.sql`, `database/phase2-seed.sql`, then `database/phase3-seed.sql`.

Employee accounts can access `/employee/dashboard.php`, `/employee/profile.php`, `/employee/employment.php`, `/employee/documents.php`, `/employee/change-request.php`, and `/employee/requests.php`. HR/Admin request review is available at `/admin/change-requests/index.php`. See [docs/integration-contract.md](docs/integration-contract.md) for future subsystem relationships and API contracts.