<?php

declare(strict_types=1);

function app_config(?string $key = null): mixed
{
    static $config = null;

    if ($config === null) {
        $config = require BASE_PATH . '/config/app.php';
    }

    return $key === null ? $config : ($config[$key] ?? null);
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function url(string $path = ''): string
{
    $base = (string) app_config('url');
    $path = ltrim($path, '/');

    if ($base === '') {
        return '/' . $path;
    }

    return $path === '' ? $base : $base . '/' . $path;
}

function old(string $key, string $default = ''): string
{
    $value = $_SESSION['_old'][$key] ?? $default;
    return is_scalar($value) ? (string) $value : $default;
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }

    $message = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);

    return $message;
}

function back_with_errors(array $errors, array $old = []): never
{
    $_SESSION['_errors'] = $errors;
    $_SESSION['_old'] = $old;
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? url('login.php')));
    exit;
}

function errors(): array
{
    $errors = $_SESSION['_errors'] ?? [];
    unset($_SESSION['_errors']);
    return is_array($errors) ? $errors : [];
}

function clear_old(): void
{
    unset($_SESSION['_old']);
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['_csrf'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['_csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }
}

function request_method_is(string $method): bool
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === strtoupper($method);
}

function input_string(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);
}

function user_agent(): string
{
    return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), 0, 255);
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $user = User::findById((int) $_SESSION['user_id']);

    if (
        !$user
        || empty($user['id'])
        || empty($user['role_id'])
        || empty($user['role_key'])
        || !is_valid_role_key((string) $user['role_key'])
    ) {
        unset($_SESSION['user_id'], $_SESSION['role_id'], $_SESSION['role_key']);
        return null;
    }

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['role_id'] = (int) $user['role_id'];
    $_SESSION['role_key'] = (string) $user['role_key'];

    return $user;
}

function auth_user(): ?array
{
    return current_user();
}

function is_valid_role_key(string $roleKey): bool
{
    return in_array($roleKey, [
        'faculty_coordinator',
        'student_coordinator',
        'tech_coordinator',
        'content_coordinator',
        'social_media_coordinator',
        'club_member',
        'guest_participant',
    ], true);
}

function role_dashboard_path(?string $roleKey): string
{
    return match ($roleKey) {
        'faculty_coordinator' => 'dashboards/faculty.php',
        'student_coordinator' => 'dashboards/student-coordinator.php',
        'tech_coordinator' => 'dashboards/tech.php',
        'content_coordinator' => 'dashboards/content.php',
        'social_media_coordinator' => 'dashboards/social-media.php',
        'club_member' => 'dashboards/member.php',
        'guest_participant' => 'dashboards/participant.php',
        default => 'login.php',
    };
}

function user_has_permission(array $user, string $permissionKey): bool
{
    if (empty($user['role_id'])) {
        return false;
    }

    $sql = 'SELECT COUNT(*) FROM role_permissions rp
            INNER JOIN permissions p ON p.id = rp.permission_id
            WHERE rp.role_id = :role_id AND p.permission_key = :permission_key';

    $stmt = db()->prepare($sql);
    $stmt->execute([
        'role_id' => $user['role_id'],
        'permission_key' => $permissionKey,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

function is_nav_item_active(string $href): bool
{
    $requestUri = $_SERVER['REQUEST_URI'] ?? $_SERVER['SCRIPT_NAME'] ?? '';
    
    // Extract path
    $currentPath = parse_url($requestUri, PHP_URL_PATH) ?? '';
    $currentPath = rtrim(preg_replace('#/+#', '/', $currentPath), '/');

    $targetUrl = url($href);
    $targetPath = parse_url($targetUrl, PHP_URL_PATH) ?? '';
    $targetPath = rtrim(preg_replace('#/+#', '/', $targetPath), '/');

    $currentPath = strtolower($currentPath);
    $targetPath = strtolower($targetPath);

    if ($currentPath === '' || $targetPath === '') {
        return false;
    }
    
    if (!str_ends_with($currentPath, $targetPath) && !str_ends_with($targetPath, $currentPath)) {
        return false;
    }

    // Compare query parameters
    $currentQueryStr = parse_url($requestUri, PHP_URL_QUERY) ?? '';
    parse_str($currentQueryStr, $currentQuery);

    $targetQueryStr = parse_url($targetUrl, PHP_URL_QUERY) ?? '';
    parse_str($targetQueryStr, $targetQuery);

    if (!empty($targetQuery)) {
        foreach ($targetQuery as $key => $val) {
            if (!isset($currentQuery[$key]) || (string)$currentQuery[$key] !== (string)$val) {
                return false;
            }
        }
    } else {
        if (!empty($currentQuery) && (isset($currentQuery['type']) || isset($currentQuery['panel']))) {
            return false;
        }
    }

    return true;
}

