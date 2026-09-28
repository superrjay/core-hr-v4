<?php
require_once __DIR__ . '/../config/config.php';
if (current_user()) {
    redirect('dashboard.php');
}
$error = null;
$notice = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    if (!recaptcha_verify('forgot_password')) {
        $error = auth_captcha_failure();
    } else {
        $notice = 'If an account matches, a reset code has been sent.';
        try {
            $pdo = db();
            if (login_ip_is_throttled($pdo)) {
                record_login_attempt($pdo, $username, false);
            } else {
                $account = find_login_account($pdo, $username);
                $email = $account ? account_email($account) : null;
                if (!$account || (int) $account['is_active'] !== 1 || (int) $account['is_locked'] === 1 || $email === null) {
                    password_verify('reset', DUMMY_PASSWORD_HASH);
                    record_login_attempt($pdo, $username, false);
                    if ($account && (int) $account['is_active'] === 1) {
                        audit_as((int) $account['id'], 'PASSWORD_RESET_BLOCKED', 'users', (int) $account['id'], 'AUTH', 'DENIED', ['reason' => (int) $account['is_locked'] === 1 ? 'locked' : 'missing_email']);
                    }
                } else {
                    begin_pending_challenge('pending_reset', (int) $account['id']);
                    if (!issue_otp($pdo, (int) $account['id'], 'PASSWORD_RESET', $email, 'Your Core HR password reset code', 'Use this code to choose a new Core HR password.')) {
                        clear_pending_challenges();
                    } else {
                        redirect('auth/reset-password.php');
                    }
                }
            }
        } catch (Throwable $exception) {
            security_log('Password reset request failed.');
            $error = 'Password reset is unavailable. Please try again later.';
            $notice = null;
        }
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Forgot password | Core HR</title><script src="https://cdn.tailwindcss.com"></script><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="<?= url('assets/css/app.css') ?>"></head><body><main class="min-h-screen grid lg:grid-cols-2"><section class="bg-[#073b3a] text-white p-8 lg:p-16 flex flex-col justify-between"><div><p class="text-xs uppercase tracking-[.25em] text-teal-300 font-bold">Microfinancial Management System</p><h1 class="font-display text-5xl lg:text-7xl font-bold mt-10 max-w-xl">Reset access.</h1><p class="mt-6 text-teal-100 max-w-md leading-7">We will email a code if the account can be reset.</p></div><p class="text-sm text-teal-200">Core Human Resource Management Subsystem</p></section><section class="bg-white flex items-center justify-center p-8"><form method="post" data-recaptcha-action="forgot_password" class="w-full max-w-md"><p class="text-sm font-bold text-teal-700">Account recovery</p><h2 class="font-display text-3xl font-bold mt-2">Forgot password</h2><?php if ($error): ?><div class="mt-6 rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-700"><?= e($error) ?></div><?php endif; ?><?php if ($notice): ?><div class="mt-6 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700"><?= e($notice) ?></div><?php endif; ?><?= csrf_field() ?><?= recaptcha_widget('forgot_password') ?><label class="block mt-8 text-sm font-semibold">Username<input required name="username" autocomplete="username" class="mt-2 w-full rounded-lg border-slate-300 border px-4 py-3" value="<?= e($_POST['username'] ?? '') ?>"></label><button class="mt-7 w-full rounded-lg bg-teal-700 px-4 py-3 font-bold text-white hover:bg-teal-800">Send reset code</button><p class="mt-6 text-sm"><a class="font-bold text-teal-700" href="<?= url('auth/login.php') ?>">Back to sign in</a></p></form></section></main></body></html>
