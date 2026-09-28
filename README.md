# Core HR Phase 1

Pure PHP/MySQL foundation for Group 4 of the Microfinancial Management System. The employee record is the authoritative master record; a user account is optional and linked with `users.employee_id`.

## XAMPP setup

1. Copy this folder to `C:\xampp\htdocs\core-hr`.
2. Start Apache and MySQL from the XAMPP Control Panel.
3. Open phpMyAdmin, select **Import**, and run `database/schema.sql`, then `database/seed.sql`.
4. Copy `.env.example` to `.env` and set `CORE_HR_DB_HOST`, `CORE_HR_DB_PORT`, `CORE_HR_DB_NAME`, `CORE_HR_DB_USER`, and `CORE_HR_DB_PASS`. `config/database.php` reads those values only. It does not contain a database password.
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

## Security rollout

Import `database/security-migration-v2.sql` after the database already exists. Do not re-import `database/schema.sql` or `database/profreehost-import.sql` on a live database.

- New database: `schema.sql`, `seed.sql`, `phase2-seed.sql`, `phase3-seed.sql`, then `security-migration-v2.sql`.
- Existing ProFreeHost database: `security-migration-v2.sql` only. `profreehost-import.sql` already created `otp_tokens`, `password_reset_tokens`, and `login_attempts`.

Set these keys in the server `.env`. `.env.example` has placeholders only.

| Key | Purpose |
| --- | --- |
| `APP_ENV` | `production` on the server. Only the exact value `local` writes mail to `storage/logs/mail.log`. |
| `SESSION_NAME` | `sems_session`. Existing sessions are signed out when this changes. |
| `SESSION_LIFETIME` | Idle minutes. Default `120`. |
| `CSRF_TOKEN_NAME` | POST field name. Default `_token`. |
| `MAIL_DRIVER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_ENCRYPTION`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | SMTP. `MAIL_PASSWORD` stays on the server. |
| `APPLICATION_STATUS_SECRET` | 64 hex characters. Used to hash OTP codes. |
| `RECAPTCHA_SITE_KEY`, `RECAPTCHA_SECRET_KEY`, `RECAPTCHA_THRESHOLD` | reCAPTCHA v3. The secret stays on the server. The hostname registered with Google must match `HTTP_HOST` without the port. |
| `RECAPTCHA_VERIFY_URL` | Optional. Used only when `APP_ENV` is exactly `local` and the value starts with `http://127.0.0.1`. Automated QA points this at a local siteverify stub. Every other environment keeps the Google URL. |
| `OTP_TTL_MINUTES` | Default `5`. |
| `OTP_ENFORCE` | `1` sends a sign-in code. `0` skips only that code and shows a warning to administrators. Password, captcha, and lockout still apply. |
| `CORE_HR_DB_*` | Database connection. |

With `APP_ENV=production` and `OTP_ENFORCE` left on, the site returns a generic security configuration error until `APPLICATION_STATUS_SECRET`, `MAIL_PASSWORD`, and `RECAPTCHA_SECRET_KEY` are set. A copied `.env` that only has the older Gemini and database keys will do that.

The seed admin has no employee row, so set an email before that account can receive a code:

```sql
UPDATE users SET email = 'your-admin@example.com' WHERE username = 'admin';
```

Rotate the database password that used to be inside the application zip, and rotate the Gemini key that used to be in `.env.example`. Do not put either value back into the repository.

If the only administrator is locked, another administrator can unlock the account from Users. If no administrator can sign in, run this in phpMyAdmin and then set the admin email:

```sql
UPDATE users
SET is_locked = 0,
    failed_login_attempts = 0,
    locked_at = NULL,
    lock_reason = NULL,
    unlocked_at = NOW(),
    session_version = session_version + 1
WHERE username = 'admin';
```

Sign-in uses one message for a wrong password, an unknown user, a missing email, and a locked account: “Invalid credentials, or the account is locked. Contact an administrator if this continues.” Three failed passwords or three failed codes lock the account until an administrator unlocks it. A shared public IP can also pause sign-in after more than 10 failures in 15 minutes, and a shared IP can hit the code resend limit.

Forgot-password always shows the same notice. It does not send a code for a locked account or an account with no email, and it does not sign the user in after a reset.

### Manual checks

- Three wrong passwords lock the account, and an administrator unlock clears it.
- Three wrong sign-in codes lock the account.
- An expired or already used code returns the user to sign-in.
- Resend waits 60 seconds and stops after 3 resends in 15 minutes.
- A low reCAPTCHA score is rejected.
- A form posted without the CSRF field returns HTTP 419.
- An idle session ends after `SESSION_LIFETIME` minutes.
- A password change notifies the user and signs out other sessions.
- A notification for another user cannot be marked read.
- `/api/*` and `/api/integration/*` reject a signed-out session, a session waiting on a code, and a session that still must change its password.