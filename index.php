<?php
require_once __DIR__ . '/config/config.php';
if (!current_user()) {
    redirect('auth/login.php');
}
if (hasRole('EMPLOYEE') && !hasAnyRole(['ADMIN', 'HR', 'MANAGER'])) {
    redirect('employee/dashboard.php');
}
redirect('dashboard.php');