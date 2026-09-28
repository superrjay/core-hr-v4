<?php
require_once __DIR__ . '/../config/config.php';
if (current_user()) audit('LOGOUT', 'users', (int) $_SESSION['user_id']);
destroy_auth_session();
redirect('auth/login.php');