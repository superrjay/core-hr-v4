<?php
require_once __DIR__ . '/../config/config.php';
if (current_user()) {
    redirect('dashboard.php');
}
$userId = pending_challenge_user_id('pending_reset');
if (!$userId) {
    flash('error', auth_generic_failure());
    redirect('auth/forgot-password.php');
}
$error = null;
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['form_action'] ?? 'reset');
    if (!recaptcha_verify('forgot_password')) {
        $error = auth_captcha_failure();
    } elseif ($action === 'resend') {
        $block = otp_resend_block_message($pdo, $userId, 'PASSWORD_RESET');
        $lookup = $pdo->prepare('SELECT username FROM users WHERE id = ?');
        $lookup->execute([$userId]);
        $account = find_login_account($pdo, (string) $lookup->fetchColumn());
        $email = $account ? account_email($account) : null;
        if ($block !== null) {
            $error = $block;
        } elseif ($email === null || !issue_otp($pdo, $userId, 'PASSWORD_RESET', $email, 'Your Core HR password reset code', 'Use this code to choose a new Core HR password.')) {
            $error = 'A new code could not be sent. Try again later.';
        } else {
            flash('success', 'A new reset code has been sent.');
            redirect('auth/reset-password.php');
        }
    } else {
        $newPassword = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirmation'] ?? '');
        $code = trim((string) ($_POST['code'] ?? ''));
        if (!password_meets_policy($newPassword)) {
            $error = password_policy_message();
        } elseif (!hash_equals($newPassword, $confirm)) {
            $error = 'The new password and confirmation do not match.';
        } else {
            $current = $pdo->prepare('SELECT password_hash, is_locked, is_active FROM users WHERE id = ?');
            $current->execute([$userId]);
            $row = $current->fetch();
            if (!$row || (int) $row['is_active'] !== 1 || (int) $row['is_locked'] === 1) {
                clear_pending_challenges();
                flash('error', auth_generic_failure());
                redirect('auth/forgot-password.php');
            }
            if (password_verify($newPassword, (string) $row['password_hash'])) {
                $error = 'Choose a different password.';
            } else {
                $result = consume_otp($pdo, $userId, 'PASSWORD_RESET', $code);
                if ($result === 'ok') {
                    clear_pending_challenges();
                    store_new_password($pdo, $userId, $newPassword, false, false);
                    flash('success', 'Your password was changed. Sign in with the new password.');
                    redirect('auth/login.php');
                }
                if ($result === 'restart') {
                    clear_pending_challenges();
                    flash('error', auth_generic_failure());
                    redirect('auth/forgot-password.php');
                }
                $error = 'That code is not valid.';
            }
        }
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Reset password | Core HR</title><script src="https://cdn.tailwindcss.com"></script><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="<?= url('assets/css/app.css') ?>"></head><body><main class="min-h-screen grid lg:grid-cols-2"><section class="bg-[#073b3a] text-white p-8 lg:p-16 flex flex-col justify-between"><div><p class="text-xs uppercase tracking-[.25em] text-teal-300 font-bold">Microfinancial Management System</p><h1 class="font-display text-5xl lg:text-7xl font-bold mt-10 max-w-xl">Choose a new password.</h1></div><p class="text-sm text-teal-200">Core Human Resource Management Subsystem</p></section><section class="bg-white flex items-center justify-center p-8"><div class="w-full max-w-md"><?php if ($message = flash('success')): ?><div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= e($message) ?></div><?php endif; ?><?php if ($error): ?><div class="mb-6 rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-700"><?= e($error) ?></div><?php endif; ?><form method="post" data-recaptcha-action="forgot_password"><p class="text-sm font-bold text-teal-700">Account recovery</p><h2 class="font-display text-3xl font-bold mt-2">Reset password</h2><?= csrf_field() ?><input type="hidden" name="form_action" value="reset"><?= recaptcha_widget('forgot_password') ?><label class="block mt-8 text-sm font-semibold">Reset code<input required name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" class="mt-2 w-full rounded-lg border-slate-300 border px-4 py-3"></label><label class="block mt-5 text-sm font-semibold">New password<input required type="password" name="password" autocomplete="new-password" class="mt-2 w-full rounded-lg border-slate-300 border px-4 py-3"></label><label class="block mt-5 text-sm font-semibold">Confirm password<input required type="password" name="password_confirmation" autocomplete="new-password" class="mt-2 w-full rounded-lg border-slate-300 border px-4 py-3"></label><button class="mt-7 w-full rounded-lg bg-teal-700 px-4 py-3 font-bold text-white hover:bg-teal-800">Update password</button></form><form method="post" data-recaptcha-action="forgot_password" class="mt-4"><?= csrf_field() ?><input type="hidden" name="form_action" value="resend"><?= recaptcha_widget('forgot_password') ?><button class="w-full rounded-lg border border-slate-200 px-4 py-3 font-bold text-slate-700">Resend code</button></form></div></section></main></body></html>
