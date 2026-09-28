<?php
require_once __DIR__ . '/../config/config.php';
if (current_user()) redirect('dashboard.php');
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    try {
        $statement = db()->prepare('SELECT id, password_hash, session_version FROM users WHERE username = ? AND is_active = 1 LIMIT 1');
        $statement->execute([$username]);
        $account = $statement->fetch();
    } catch (Throwable $exception) {
        security_log('Login lookup failed.');
        $error = 'Sign-in is unavailable. Please try again later.';
        $account = false;
    }
    if ($account && password_verify($password, $account['password_hash'])) {
        establish_login_session((int) $account['id'], (int) ($account['session_version'] ?? 1));
        db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$account['id']]);
        audit('LOGIN', 'users', (int) $account['id']);
        redirect('dashboard.php');
    }
    if ($error === null) $error = 'The username or password is incorrect.';
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Sign in | Core HR</title><script src="https://cdn.tailwindcss.com"></script><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="<?= url('assets/css/app.css') ?>"></head><body><main class="min-h-screen grid lg:grid-cols-2"><section class="bg-[#073b3a] text-white p-8 lg:p-16 flex flex-col justify-between"><div><p class="text-xs uppercase tracking-[.25em] text-teal-300 font-bold">Microfinancial Management System</p><h1 class="font-display text-5xl lg:text-7xl font-bold mt-10 max-w-xl">People work, made clearer.</h1><p class="mt-6 text-teal-100 max-w-md leading-7">A focused home for the people records and daily signals that keep your organization moving.</p></div><p class="text-sm text-teal-200">Core Human Resource Management Subsystem · Phase 1</p></section><section class="bg-white flex items-center justify-center p-8"><form method="post" class="w-full max-w-md"><p class="text-sm font-bold text-teal-700">Welcome back</p><h2 class="font-display text-3xl font-bold mt-2">Sign in to Core HR</h2><?php if ($error): ?><div class="mt-6 rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-700"><?= e($error) ?></div><?php endif; ?><?= csrf_field() ?><label class="block mt-8 text-sm font-semibold">Username<input required name="username" autocomplete="username" class="mt-2 w-full rounded-lg border-slate-300 border px-4 py-3 focus:border-teal-600 focus:ring-teal-600" value="<?= e($_POST['username'] ?? '') ?>"></label><label class="block mt-5 text-sm font-semibold">Password<input required type="password" name="password" autocomplete="current-password" class="mt-2 w-full rounded-lg border-slate-300 border px-4 py-3"></label><button class="mt-7 w-full rounded-lg bg-teal-700 px-4 py-3 font-bold text-white hover:bg-teal-800">Sign in</button><p class="mt-6 text-xs text-slate-400">Development accounts are listed in the project README.</p></form></section></main></body></html>