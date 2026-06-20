<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/auth.php';

// 1. Require user authentication
$user = require_auth();

// 2. Bonus Security: Session-based API rate limiting (max 60 requests per minute)
if (empty($_SESSION['search_rate_limit_time']) || time() - $_SESSION['search_rate_limit_time'] > 60) {
    $_SESSION['search_rate_limit_time'] = time();
    $_SESSION['search_rate_limit_count'] = 1;
} else {
    $_SESSION['search_rate_limit_count']++;
    if ($_SESSION['search_rate_limit_count'] > 60) {
        http_response_code(429);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Too many search requests. Please try again in a minute.']);
        exit;
    }
}

// 3. Process search query
$q = trim((string) ($_GET['q'] ?? ''));
if ($q === '') {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

// 4. Retrieve matching active users
$results = User::searchActiveUsers($q, 10);

// 5. Bonus Security: Filter out the current logged-in user from selection to prevent self-addition
$filtered = array_filter($results, function ($m) use ($user) {
    return (int) $m['id'] !== (int) $user['id'];
});
$filtered = array_values($filtered);

// 6. Return response
header('Content-Type: application/json');
echo json_encode($filtered);
exit;
