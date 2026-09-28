<?php
$pageTitle = $pageTitle ?? APP_NAME;
$user = current_user();
$isAdmin = hasRole('ADMIN');
$isHr = hasRole('HR') || $isAdmin;
$isEmployee = hasRole('EMPLOYEE') && !$isHr;
$isManager = hasRole('MANAGER');
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> | Core HR</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
</head>
<body>
<div class="min-h-screen lg:flex">
  <aside id="main-menu" class="hidden lg:flex lg:w-64 lg:flex-col bg-white border-r border-slate-200 fixed inset-y-0 z-20">
    <div class="p-6 border-b border-slate-100">
      <p class="text-xs font-bold uppercase tracking-[.22em] text-teal-700">Microfinancial</p>
      <h1 class="font-display text-2xl font-bold mt-2">Core HR</h1>
      <p class="text-xs text-slate-500 mt-1"><?= $isEmployee ? 'Employee self-service' : 'People operations hub' ?></p>
    </div>
    <nav class="p-4 space-y-1 text-sm font-semibold overflow-y-auto">
      <?php if ($isEmployee): ?>
        <a class="sidebar-link block rounded-lg px-4 py-3" href="<?= url('employee/dashboard.php') ?>">My Dashboard</a>
        <a class="sidebar-link block rounded-lg px-4 py-3" href="<?= url('employee/profile.php') ?>">My Profile</a>
        <a class="sidebar-link block rounded-lg px-4 py-3" href="<?= url('employee/employment.php') ?>">My Employment</a>
        <a class="sidebar-link block rounded-lg px-4 py-3" href="<?= url('employee/documents.php') ?>">My Documents</a>
        <a class="sidebar-link block rounded-lg px-4 py-3" href="<?= url('employee/requests.php') ?>">My Requests</a>
      <?php else: ?>
        <a class="sidebar-link block rounded-lg px-4 py-3 <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>" href="<?= url('dashboard.php') ?>">Dashboard</a>
        <?php if ($isHr): ?>
          <a class="sidebar-link block rounded-lg px-4 py-3" href="<?= url('admin/employees/index.php') ?>">Employees</a>
          <a class="sidebar-link block rounded-lg px-4 py-3" href="<?= url('admin/departments.php') ?>">Departments</a>
          <a class="sidebar-link block rounded-lg px-4 py-3" href="<?= url('admin/positions.php') ?>">Positions</a>
          <a class="sidebar-link block rounded-lg px-4 py-3" href="<?= url('admin/branches.php') ?>">Branches</a>
          <a class="sidebar-link block rounded-lg px-4 py-3" href="<?= url('admin/change-requests/index.php') ?>">ESS Requests</a>
          <a class="sidebar-link block rounded-lg px-4 py-3" href="<?= url('admin/ai/index.php') ?>">AI Tools</a>
          <div class="pt-5 pb-2 px-4 text-[10px] tracking-widest uppercase text-slate-400">Core workflows</div>
          <a class="sidebar-link block rounded-lg px-4 py-3" href="<?= url('admin/employee-records/index.php') ?>">Employee Records</a>
          <a class="sidebar-link block rounded-lg px-4 py-3" href="<?= url('admin/document-types/index.php') ?>">Document Types</a>
          <a class="sidebar-link block rounded-lg px-4 py-3" href="<?= url('admin/document-templates/index.php') ?>">Document Templates</a>
        <?php elseif ($isManager): ?>
          <a class="sidebar-link block rounded-lg px-4 py-3" href="<?= url('admin/change-requests/index.php') ?>">ESS Requests</a>
        <?php endif; ?>
        <?php if ($isAdmin): ?>
          <div class="pt-5 pb-2 px-4 text-[10px] tracking-widest uppercase text-slate-400">Administration</div>
          <a class="sidebar-link block rounded-lg px-4 py-3" href="<?= url('admin/users/index.php') ?>">Users</a>
          <a class="sidebar-link block rounded-lg px-4 py-3" href="<?= url('admin/roles/index.php') ?>">Roles</a>
          <a class="sidebar-link block rounded-lg px-4 py-3" href="<?= url('admin/audit-logs.php') ?>">Audit logs</a>
        <?php endif; ?>
      <?php endif; ?>
      <a class="sidebar-link block rounded-lg px-4 py-3" href="<?= url('auth/logout.php') ?>">Sign out</a>
    </nav>
    <div class="mt-auto p-4">
      <div class="rounded-xl bg-slate-50 border border-slate-200 p-3 text-xs text-slate-500">
        <?= e($isAdmin ? 'Administrator access' : ($isHr ? 'HR access' : ($isManager ? 'Manager access' : ($isEmployee ? 'Employee access' : 'Limited access')))) ?>
      </div>
    </div>
  </aside>
  <div class="lg:pl-64 flex-1">
    <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-5 lg:px-10">
      <button data-menu-toggle="#main-menu" class="lg:hidden rounded-lg border px-3 py-2" type="button">Menu</button>
      <div>
        <p class="text-sm text-slate-500"><?= e($isAdmin ? 'Administration' : ($isHr ? 'Human Resources' : ($isEmployee ? 'Employee Self-Service' : 'Core HR'))) ?></p>
        <h2 class="font-display text-xl font-bold mt-1"><?= e($pageTitle) ?></h2>
      </div>
      <div class="flex items-center gap-3">
        <span class="rounded-full bg-teal-50 text-teal-700 px-3 py-1 text-xs font-bold"><?= e($user['role_name'] ?? 'USER') ?></span>
        <a href="<?= url('auth/logout.php') ?>" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-bold">Sign out</a>
      </div>
    </header>
    <main class="p-5 lg:p-10">
      <?php if ($message = flash('success')): ?>
        <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= e($message) ?></div>
      <?php endif; ?>
      <?php if ($message = flash('error')): ?>
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= e($message) ?></div>
      <?php endif; ?>
