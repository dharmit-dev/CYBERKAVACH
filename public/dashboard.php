<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/auth.php';

$user = require_auth();
redirect(role_dashboard_path($user['role_key'] ?? null));
