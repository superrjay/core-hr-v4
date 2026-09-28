<?php
require_once __DIR__ . '/../config/config.php';
$user = require_auth();
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $current = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirmation'] ?? '');
    if (!recaptcha_verify('change_password')) {
        $error = auth_captcha_failure();
    } elseif (!password_verify($current, (string) $user['password_hash'])) {
        $error = 'The current password is incorrect.';
    } elseif (!password_meets_policy($newPassword)) {
        $error = password_policy_message();
    } elseif (!hash_equals($newPassword, $confirm)) {
        $error = 'The new password and confirmation do not match.';
    } elseif (password_verify($newPassword, (string) $user['password_hash'])) {
        $error = 'Choose a different password.';
    } else {
        store_new_password(db(), (int) $user['id'], $newPassword, false, true);
        flash('success', 'Your password was changed.');
        redirect('dashboard.php');
    }
}
$pageTitle = 'Change password';
require __DIR__ . '/../includes/header.php';
?>
<div class="max-w-xl">
  <p class="text-sm text-slate-500">Account security</p>
  <h1 class="font-display text-3xl font-bold mt-1">Change password</h1>
  <?php if (!empty($user['must_change_password'])): ?><p class="mt-3 text-sm text-amber-800">Choose a new password before using the rest of Core HR.</p><?php endif; ?>
  <?php if ($error): ?><div class="mt-6 rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-700"><?= e($error) ?></div><?php endif; ?>
  <form method="post" data-recaptcha-action="change_password" class="bg-white border border-slate-200 rounded-xl p-6 mt-6 space-y-4">
    <?= csrf_field() ?>
    <?= recaptcha_widget('change_password') ?>
    <label class="block text-sm font-semibold">Current password<input required type="password" name="current_password" autocomplete="current-password" class="field"></label>
    <label class="block text-sm font-semibold">New password<input required type="password" name="password" autocomplete="new-password" class="field"></label>
    <label class="block text-sm font-semibold">Confirm new password<input required type="password" name="password_confirmation" autocomplete="new-password" class="field"></label>
    <p class="text-xs text-slate-500"><?= e(password_policy_message()) ?></p>
    <button class="rounded-lg bg-teal-700 px-5 py-3 text-sm font-bold text-white">Update password</button>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
