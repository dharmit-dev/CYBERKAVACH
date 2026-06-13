<?php

declare(strict_types=1);

function require_guest(): void
{
    $user = current_user();

    if ($user) {
        redirect(role_dashboard_path($user['role_key'] ?? null));
    }
}
