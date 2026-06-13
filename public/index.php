<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/bootstrap.php';

$user = current_user();

if ($user) {
    redirect(role_dashboard_path($user['role_key'] ?? null));
}

redirect('login.php');
