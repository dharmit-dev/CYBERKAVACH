<?php

declare(strict_types=1);

function require_auth(): array
{
    $user = current_user();

    if (!$user) {
        flash('error', 'Please login to continue.');
        redirect('login.php');
    }

    if (($user['status'] ?? null) !== 'active') {
        AuthService::logout();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        flash('error', 'Your account is not active.');
        redirect('login.php');
    }

    return $user;
}
