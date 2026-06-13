<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function require_role(string|array $roles): array
{
    $user = require_auth();

    $allowedRoles = is_array($roles) ? $roles : [$roles];

    if (empty($user['role_key'])) {
        AuthService::logout();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        flash('error', 'Please login again.');
        redirect('login.php');
    }

    if (!in_array($user['role_key'], $allowedRoles, true)) {
        flash('error', 'You were redirected to your assigned dashboard.');
        redirect(role_dashboard_path((string) $user['role_key']));
    }

    return $user;
}

function require_permission(string $permissionKey): array
{
    $user = require_auth();

    if (!user_has_permission($user, $permissionKey)) {
        flash('error', 'You do not have permission to access that page.');
        redirect(role_dashboard_path((string) $user['role_key']));
    }

    return $user;
}
