<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/bootstrap.php';

AuthService::logout();
session_start();
flash('success', 'You have been logged out.');
redirect('login.php');
