<?php
require_once __DIR__ . '/../config/config.php';
if (current_user()) {
    redirect('dashboard.php');
}
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if (!recaptcha_verify('login')) {
        $error = auth_captcha_failure();
    } else {
        try {
            $pdo = db();
            if (login_ip_is_throttled($pdo)) {
                record_login_attempt($pdo, $username, false);
                $error = auth_generic_failure();
            } else {
                $account = find_login_account($pdo, $username);
                $usable = $account && (int) $account['is_active'] === 1;
                if (!$usable) {
                    password_verify($password, DUMMY_PASSWORD_HASH);
                    record_login_attempt($pdo, $username, false);
                    $error = auth_generic_failure();
                } elseif ((int) $account['is_locked'] === 1) {
                    record_login_attempt($pdo, $username, false);
                    audit_as((int) $account['id'], 'LOGIN_BLOCKED', 'users', (int) $account['id'], 'AUTH', 'DENIED', ['reason' => 'locked']);
                    $error = auth_generic_failure();
                } elseif (!password_verify($password, (string) $account['password_hash'])) {
                    record_login_attempt($pdo, $username, false);
                    register_failed_credential($pdo, (int) $account['id']);
                    $error = auth_generic_failure();
                } else {
                    record_login_attempt($pdo, $username, true);
                    $email = account_email($account);
                    if ($email === null) {
                        audit_as((int) $account['id'], 'LOGIN_BLOCKED', 'users', (int) $account['id'], 'AUTH', 'DENIED', ['reason' => 'missing_email']);
                        $error = auth_generic_failure();
                    } elseif (!security_config()['OTP_ENFORCE']) {
                        complete_authenticated_login($pdo, (int) $account['id']);
                    } else {
                        begin_pending_challenge('pending_2fa', (int) $account['id']);
                        if (!issue_otp($pdo, (int) $account['id'], 'LOGIN', $email, 'Your Core HR sign-in code', 'Use this code to finish signing in to Core HR.')) {
                            clear_pending_challenges();
                            $error = auth_generic_failure();
                        } else {
                            redirect('auth/verify-otp.php');
                        }
                    }
                }
            }
        } catch (Throwable $exception) {
            security_log('Login failed.');
            $error = 'Sign-in is unavailable. Please try again later.';
        }
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Sign in | Core HR</title><script src="https://cdn.tailwindcss.com"></script><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="<?= url('assets/css/app.css') ?>"></head><body><main class="min-h-screen grid lg:grid-cols-2"><section class="bg-[#073b3a] text-white p-8 lg:p-16 flex flex-col justify-between"><div><p class="text-xs uppercase tracking-[.25em] text-teal-300 font-bold">Microfinancial Management System</p><h1 class="font-display text-5xl lg:text-7xl font-bold mt-10 max-w-xl">People work, made clearer.</h1><p class="mt-6 text-teal-100 max-w-md leading-7">A focused home for the people records and daily signals that keep your organization moving.</p></div><p class="text-sm text-teal-200">Core Human Resource Management Subsystem</p></section><section class="bg-white flex items-center justify-center p-8"><form method="post" data-recaptcha-action="login" class="w-full max-w-md"><p class="text-sm font-bold text-teal-700">Welcome back</p><h2 class="font-display text-3xl font-bold mt-2">Sign in to Core HR</h2><?php if ($error): ?><div class="mt-6 rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-700"><?= e($error) ?></div><?php endif; ?><?= csrf_field() ?><?= recaptcha_widget('login') ?><label class="block mt-8 text-sm font-semibold">Username<input required name="username" autocomplete="username" class="mt-2 w-full rounded-lg border-slate-300 border px-4 py-3 focus:border-teal-600 focus:ring-teal-600" value="<?= e($_POST['username'] ?? '') ?>"></label><label class="block mt-5 text-sm font-semibold">Password<input required type="password" name="password" autocomplete="current-password" class="mt-2 w-full rounded-lg border-slate-300 border px-4 py-3"></label><button class="mt-7 w-full rounded-lg bg-teal-700 px-4 py-3 font-bold text-white hover:bg-teal-800">Sign in</button><p class="mt-6 text-sm"><a class="font-bold text-teal-700" href="<?= url('auth/forgot-password.php') ?>">Forgot password?</a></p></form></section></main></body></html>
