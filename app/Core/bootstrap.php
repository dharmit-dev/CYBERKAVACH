<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/app/Core/env.php';
require_once BASE_PATH . '/app/Helpers/functions.php';
require_once BASE_PATH . '/app/Middleware/headers.php';

load_env(BASE_PATH . '/.env');

date_default_timezone_set(env('APP_TIMEZONE', 'Asia/Kolkata'));

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? null) === '443');

ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');

if ($isHttps) {
    ini_set('session.cookie_secure', '1');
}

session_name('CYBERKAVACH_SESSION');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['_initiated'])) {
    session_regenerate_id(true);
    $_SESSION['_initiated'] = true;
}

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/app/Core/database.php';

if (is_file(BASE_PATH . '/vendor/autoload.php')) {
    require_once BASE_PATH . '/vendor/autoload.php';
}

require_once BASE_PATH . '/app/Services/AuditService.php';
require_once BASE_PATH . '/app/Services/MailService.php';
require_once BASE_PATH . '/app/Services/OtpService.php';
require_once BASE_PATH . '/app/Models/User.php';

// Session Hijacking Protection
if (isset($_SESSION['user_id'])) {
    if (empty($_SESSION['_client_ip']) || empty($_SESSION['_client_ua'])) {
        $_SESSION['_client_ip'] = client_ip();
        $_SESSION['_client_ua'] = user_agent();
    } elseif ($_SESSION['_client_ip'] !== client_ip() || $_SESSION['_client_ua'] !== user_agent()) {
        AuditService::record('session_hijack_blocked', 'auth', (int) $_SESSION['user_id'], 'users', (int) $_SESSION['user_id']);
        
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name('CYBERKAVACH_SESSION');
            session_start();
        }
        $_SESSION['_initiated'] = true;
        
        flash('error', 'Session validation failed due to network change.');
        redirect('login.php');
    }
}
require_once BASE_PATH . '/app/Models/Event.php';
require_once BASE_PATH . '/app/Models/EventRegistration.php';
require_once BASE_PATH . '/app/Models/ApprovalRequest.php';
require_once BASE_PATH . '/app/Models/ApprovalAction.php';
require_once BASE_PATH . '/app/Models/Notification.php';
require_once BASE_PATH . '/app/Models/BudgetRequest.php';
require_once BASE_PATH . '/app/Models/VenueResourceRequest.php';
require_once BASE_PATH . '/app/Models/SocialMediaPost.php';
require_once BASE_PATH . '/app/Models/ContentPost.php';
require_once BASE_PATH . '/app/Models/ExternalCollaboration.php';
require_once BASE_PATH . '/app/Services/NotificationService.php';
require_once BASE_PATH . '/app/Services/ApprovalService.php';
require_once BASE_PATH . '/app/Services/QRService.php';
require_once BASE_PATH . '/app/Services/EventService.php';
require_once BASE_PATH . '/app/Services/AuthService.php';
