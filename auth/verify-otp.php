<?php
require_once __DIR__ . '/../config/config.php';
if (current_user()) {
    redirect('dashboard.php');
}
if (!security_config()['OTP_ENFORCE']) {
    redirect('auth/login.php');
}
$userId = pending_challenge_user_id('pending_2fa');
if (!$userId) {
    flash('error', auth_generic_failure());
    redirect('auth/login.php');
}
$error = null;
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['form_action'] ?? 'verify');
    $captchaAction = $action === 'resend' ? 'resend_otp' : 'verify_otp';
    if (!recaptcha_verify($captchaAction)) {
        $error = auth_captcha_failure();
    } elseif ($action === 'resend') {
        $block = otp_resend_block_message($pdo, $userId, 'LOGIN');
        $lookup = $pdo->prepare('SELECT username FROM users WHERE id = ?');
        $lookup->execute([$userId]);
        $account = find_login_account($pdo, (string) $lookup->fetchColumn());
        $email = $account ? account_email($account) : null;
        if ($block !== null) {
            $error = $block;
        } elseif ($email === null || !issue_otp($pdo, $userId, 'LOGIN', $email, 'Your Core HR sign-in code', 'Use this code to finish signing in to Core HR.')) {
            $error = 'A new code could not be sent. Try again later.';
        } else {
            flash('success', 'A new sign-in code has been sent.');
            redirect('auth/verify-otp.php');
        }
    } else {
        $result = consume_otp($pdo, $userId, 'LOGIN', trim((string) ($_POST['code'] ?? '')));
        if ($result === 'ok') {
            clear_pending_challenges();
            complete_authenticated_login($pdo, $userId);
        }
        if ($result === 'restart') {
            clear_pending_challenges();
            flash('error', auth_generic_failure());
            redirect('auth/login.php');
        }
        $error = 'That code is not valid.';
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Verify sign-in | Core HR</title><script src="https://cdn.tailwindcss.com"></script><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="<?= url('assets/css/app.css') ?>"></head><body><main class="min-h-screen grid lg:grid-cols-2"><section class="bg-[#073b3a] text-white p-8 lg:p-16 flex flex-col justify-between"><div><p class="text-xs uppercase tracking-[.25em] text-teal-300 font-bold">Microfinancial Management System</p><h1 class="font-display text-5xl lg:text-7xl font-bold mt-10 max-w-xl">Check your email.</h1><p class="mt-6 text-teal-100 max-w-md leading-7">Enter the 6-digit code that was sent to your account email.</p></div><p class="text-sm text-teal-200">Core Human Resource Management Subsystem</p></section><section class="bg-white flex items-center justify-center p-8"><div class="w-full max-w-md"><?php if ($message = flash('success')): ?><div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= e($message) ?></div><?php endif; ?><?php if ($error): ?><div class="mb-6 rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-700"><?= e($error) ?></div><?php endif; ?><form method="post" data-recaptcha-action="verify_otp"><p class="text-sm font-bold text-teal-700">Two-step sign-in</p><h2 class="font-display text-3xl font-bold mt-2">Enter your code</h2><?= csrf_field() ?><input type="hidden" name="form_action" value="verify"><?= recaptcha_widget('verify_otp') ?><label class="block mt-8 text-sm font-semibold">Sign-in code<input required name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" class="mt-2 w-full rounded-lg border-slate-300 border px-4 py-3 tracking-[.4em]"></label><button class="mt-7 w-full rounded-lg bg-teal-700 px-4 py-3 font-bold text-white hover:bg-teal-800">Verify code</button></form><form method="post" data-recaptcha-action="resend_otp" class="mt-4"><?= csrf_field() ?><input type="hidden" name="form_action" value="resend"><?= recaptcha_widget('resend_otp') ?><button class="w-full rounded-lg border border-slate-200 px-4 py-3 font-bold text-slate-700">Resend code</button></form><p class="mt-6 text-sm"><a class="font-bold text-teal-700" href="<?= url('auth/logout.php') ?>">Cancel and sign in again</a></p></div></section></main></body></html>
