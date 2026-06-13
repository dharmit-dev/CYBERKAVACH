<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'CyberKavach Club'),
    'env' => env('APP_ENV', 'production'),
    'url' => rtrim((string) env('APP_URL', ''), '/'),
    'debug' => (bool) env('APP_DEBUG', false),
    'otp_expiry_minutes' => (int) env('OTP_EXPIRY_MINUTES', 10),
    'password_reset_expiry_minutes' => (int) env('PASSWORD_RESET_EXPIRY_MINUTES', 30),
];
