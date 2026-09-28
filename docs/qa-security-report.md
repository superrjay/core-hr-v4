# Security QA report

Executed 2026-09-28 against a local Apache 2.4.58, PHP 8.3.6, and MariaDB 10.11.14 instance. The database was imported from `schema.sql`, `seed.sql`, `phase2-seed.sql`, `phase3-seed.sql`, and `security-migration-v2.sql`. Sign-in codes were delivered to the local mail log (`APP_ENV=local`). reCAPTCHA responses came from a local siteverify stub. Production mode was checked separately and did not use that stub.

82 checks passed after the defects below were fixed. This run did not call Google or a real SMTP server, and it did not exercise the graphical browser.

## Defects fixed during this run

- Apache turned PHP's bare `419` status into `500`. CSRF failures now send `419 Authentication Timeout`, and both HTML and JSON responses keep that status.
- After an employee submitted a profile change, the browser was sent to `/core-hr/requests.php`. It now goes to `/core-hr/employee/requests.php`.
- A lifecycle save redirected to `/core-hr/view.php`. It now goes to `/core-hr/admin/employees/view.php`.
- Document record and draft saves redirected to `/core-hr/records.php` and `/core-hr/draft.php`. They now stay under `/core-hr/admin/employee-records/`.

## Coverage

The suite signed in through the real login and code pages, locked accounts with wrong passwords and wrong codes, unlocked an account from the users page, changed and reset passwords, and checked idle sessions, session regeneration, and session-version logout. It also checked notification ownership, ESS and employee-update notices, API rejection for signed-out, pending-code, and must-change-password sessions, role boundaries, deny rules, and the production configuration guard.


| ID | Area | Test | Expected | Actual | Status |
|---|---|---|---|---|---|
| ENV-001 | Environment | PHP 8 | 8.x | 8.3.6 | PASS |
| ENV-002 | Environment | MariaDB | 10.11 | 10.11.14-MariaDB-0ubuntu0.24.04.1 | PASS |
| ENV-003 | Environment | Tables imported | at least 28 | 28 | PASS |
| ENV-004 | Environment | Login page loads | 200 | 200 | PASS |
| ENV-005 | Environment | Login cookie flags | HttpOnly SameSite=Lax sems_session | present | PASS |
| ENV-006 | Environment | Security headers | nosniff DENY strict-origin | present | PASS |
| ENV-007 | Environment | Login form markers | captcha, csrf, forgot link | present | PASS |
| ENV-008 | Environment | Logged-out index | 302 login | 302 /core-hr/auth/login.php | PASS |
| ENV-009 | Environment | Assets | 200/200 | 200/200 | PASS |
| SEC-DENY-f579cc | Hardening | Deny .env | 403 | 403 | PASS |
| SEC-DENY-8a025a | Hardening | Deny config/database.php | 403 | 403 | PASS |
| SEC-DENY-31c860 | Hardening | Deny database/seed.sql | 403 | 403 | PASS |
| SEC-DENY-ce36fb | Hardening | Deny docs/qa-test-report.md | 403 | 403 | PASS |
| SEC-DENY-032d01 | Hardening | Deny ai/GeminiService.php | 403 | 403 | PASS |
| SEC-DENY-27cabb | Hardening | Deny includes/lib/PHPMailer/PHPMailer.php | 403 | 403 | PASS |
| SEC-CSRF-001 | CSRF | Login without token | 419 | 419 | PASS |
| SEC-CAPTCHA-001 | reCAPTCHA | Missing token | captcha failure | captcha message | PASS |
| SEC-CAPTCHA-002 | reCAPTCHA | Low score | captcha failure | captcha message | PASS |
| SEC-CAPTCHA-003 | reCAPTCHA | Hostname mismatch | captcha failure | captcha message | PASS |
| SEC-CAPTCHA-004 | reCAPTCHA | Action mismatch | captcha failure | captcha message | PASS |
| SEC-CAPTCHA-005 | reCAPTCHA | Captcha failure does not burn password attempts | 0 | 0 | PASS |
| SEC-AUTH-001 | Authentication | Unknown user | Invalid credentials, or the account is locked. Contact an administrator if this continues. | generic | PASS |
| SEC-AUTH-002 | Authentication | Inactive user | Invalid credentials, or the account is locked. Contact an administrator if this continues. | generic | PASS |
| SEC-AUTH-003 | Authentication | Active user without email | Invalid credentials, or the account is locked. Contact an administrator if this continues. | generic | PASS |
| SEC-LOCK-001 | Lockout | Three wrong passwords use the generic message | generic, no remaining count | generic | PASS |
| SEC-LOCK-002 | Lockout | Account locks at 3 | locked | locked | PASS |
| SEC-LOCK-003 | Lockout | Locked user and another admin are notified | 2 notices | 2 | PASS |
| SEC-LOCK-004 | Lockout | Correct password on a locked account stays generic | Invalid credentials, or the account is locked. Contact an administrator if this continues. | generic | PASS |
| SEC-AUTH-004 | Authentication | Admin password step reaches OTP | verify or change-password | /core-hr/dashboard.php | PASS |
| SEC-AUTH-005 | Authentication | Admin dashboard after OTP | 200 | 200 | PASS |
| SEC-AUTH-006 | Authentication | Session id changes when the password is accepted | new id | changed | PASS |
| SEC-LOCK-005 | Lockout | Users page shows the locked account | Locked qa.lock | shown | PASS |
| SEC-LOCK-006 | Lockout | Admin unlock clears the lock | 302 unlocked | 302 locked=0 | PASS |
| SEC-LOCK-007 | Lockout | Unlocked user is notified | 1 | 1 | PASS |
| SEC-OTP-001 | OTP | Code is emailed and kept out of notifications and audit details | email only | hidden | PASS |
| SEC-OTP-002 | OTP | LOGIN audit waits until the code is accepted | 0 before verify | 0 | PASS |
| SEC-OTP-003 | OTP | Two wrong codes stay on the form and do not lock | not valid, unlocked | ok | PASS |
| SEC-OTP-004 | OTP | Third wrong code locks and returns to sign-in | locked redirect | 302 /core-hr/auth/login.php | PASS |
| SEC-OTP-005 | OTP | Expired code returns to sign-in | 302 login | 302 /core-hr/auth/login.php | PASS |
| SEC-OTP-006 | OTP | Used code returns to sign-in | 302 login | 302 /core-hr/auth/login.php | PASS |
| SEC-OTP-007 | OTP | Resend cooldown | wait a minute | cooldown | PASS |
| SEC-OTP-008 | OTP | Third resend is still allowed | 302 | 302 | PASS |
| SEC-OTP-009 | OTP | Fourth resend in 15 minutes is blocked | cannot be sent | blocked | PASS |
| SEC-OTP-010 | OTP | Valid code signs in and notifies | 302 dashboard and notice | /core-hr/dashboard.php | PASS |
| SEC-OTP-011 | OTP | Employee dashboard after OTP | 200 | 200 | PASS |
| SEC-PW-001 | Password | Forced change redirects before the dashboard | change-password | /core-hr/auth/change-password.php | PASS |
| SEC-PW-002 | Password | Other pages stay on the change form | 302 change-password | 302 /core-hr/auth/change-password.php | PASS |
| SEC-PW-003 | Password | API rejects a session that must change its password | 403 | 403 {"error":"Password change required."} | PASS |
| SEC-PW-004 | Password | Weak password is rejected | policy message | policy | PASS |
| SEC-PW-005 | Password | Same password is rejected | different password | accepted | PASS |
| SEC-PW-006 | Password | Successful change keeps this session and notifies | 200 and notice | /core-hr/dashboard.php dash=200 note=Your password was changed at Sep 28, 2026 1:50 PM from IP 127.0.0.1. Browser: Unknown browser. If this was not you, contact your administrator. | PASS |
| SEC-PW-007 | Password | Other sessions are signed out | 302 login | 302 /core-hr/auth/login.php | PASS |
| SEC-API-001 | API | Signed-out API is rejected | 401/401 | 401/401 | PASS |
| SEC-API-002 | API | Pending OTP session cannot call the API or open the dashboard | 401 and 302 | 401 302 /core-hr/auth/login.php | PASS |
| SEC-API-003 | API | Pending reset session cannot call the integration API | 401 | 401 | PASS |
| SEC-API-004 | API | Logout clears a pending OTP session | 302 login | 302 /core-hr/auth/login.php | PASS |
| SEC-RBAC-001 | RBAC | Employee lands on the employee dashboard | employee dashboard | /core-hr/dashboard.php | PASS |
| SEC-RBAC-002 | RBAC | Employee HR dashboard redirects | 302 employee dashboard | 302 /core-hr/employee/dashboard.php | PASS |
| SEC-RBAC-003 | RBAC | Employee cannot open user admin | 403 | 403 | PASS |
| SEC-RBAC-004 | RBAC | HR can open employees | 200 | 200 | PASS |
| SEC-RBAC-005 | RBAC | Manager dashboard is allowed and employee admin is not | 200/403 | 200/403 | PASS |
| SEC-RBAC-006 | RBAC | Integration role is denied the dashboard and allowed the API | 403/200/403 | 403/200/403 | PASS |
| SEC-CSRF-002 | CSRF | User admin without token | 419 | 419 | PASS |
| SEC-CSRF-003 | CSRF | API write without token | 419 JSON | 419 {"error":"Invalid security token. Please go back and try again."} | PASS |
| SEC-NOTE-001 | Notifications | Owner sees the notification | 200 listed | listed | PASS |
| SEC-NOTE-002 | Notifications | Another user does not see it | hidden | hidden | PASS |
| SEC-NOTE-003 | Notifications | Mark-read cannot target another user | still unread | 0 | PASS |
| SEC-NOTE-004 | Notifications | Owner can mark it read | 1 | 1 302 | PASS |
| SEC-ESS-001 | Notifications | ESS submit notifies reviewers with the field name only | Phone, no value | 302 /core-hr/employee/requests.php notes=5 | PASS |
| SEC-ESS-002 | Notifications | ESS submit redirects to the employee request list | 302 employee/requests.php | 302 /core-hr/employee/requests.php | PASS |
| SEC-ESS-003 | Notifications | Approval notice names the field and updates the record | Phone updated | 302 phone=SECRET-VALUE-99 note=Your profile change request for Phone was approved. Changed fields: Phone. | PASS |
| SEC-EMP-001 | Notifications | Employee update notifies with field names | phone, no value | 302 Your employee record was updated. Changed fields: phone. | PASS |
| SEC-LIFE-001 | Notifications | Lifecycle update notifies the linked manager account | MANAGER_INFO_CHANGED | 302 /core-hr/admin/employees/view.php?id=6 Your employment record was updated. Changed fields: employment_status. | PASS |
| SEC-PW-008 | Password | Admin reset forces a change and does not email the password | must change | 302 must=1 | PASS |
| SEC-RESET-001 | Recovery | Locked and emailless resets share one notice and send no code | same notice | generic | PASS |
| SEC-RESET-002 | Recovery | A matching account receives a reset code | 302 reset | 302 /core-hr/auth/reset-password.php | PASS |
| SEC-RESET-003 | Recovery | Reset does not sign the user in and kills the old session | 302 login | /core-hr/auth/login.php old=302 | PASS |
| SEC-RESET-004 | Recovery | The new password signs in | dashboard | /core-hr/dashboard.php | PASS |
| SEC-THR-001 | Throttle | More than 10 recent failures stop sign-in before the password | Invalid credentials, or the account is locked. Contact an administrator if this continues. | throttled | PASS |
| SEC-SESS-001 | Session | Idle session is signed out | 302 login | 302 /core-hr/auth/login.php | PASS |
| SEC-PROD-001 | Hardening | Production ignores the local verify URL | fail and no stub hit | fail stub 165->165 | PASS |
| SEC-PROD-002 | Hardening | Production without secrets fails closed | Security configuration error | closed | PASS |

Passed 82, failed 0.
