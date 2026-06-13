<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/auth.php';

$user = require_auth();

if (!request_method_is('POST')) {
    redirect('approvals/index.php');
}

verify_csrf();

$requestId = (int) input_string('request_id');
$decision = input_string('decision');
$comments = input_string('comments');

if ($requestId <= 0) {
    flash('error', 'Invalid approval request.');
    redirect('approvals/index.php');
}

$request = ApprovalRequest::findById($requestId);
if (!$request || !ApprovalService::canView($request, $user)) {
    flash('error', 'Unauthorized access');
    redirect('dashboard.php');
}

$result = match ($decision) {
    'approve' => ApprovalService::approve($requestId, $user, $comments),
    'reject' => ApprovalService::reject($requestId, $user, $comments),
    'return' => ApprovalService::returnRequest($requestId, $user, $comments),
    'comment' => ApprovalService::comment($requestId, $user, $comments),
    default => ['ok' => false, 'message' => 'Invalid approval action.'],
};

if (!$result['ok']) {
    $_SESSION['_errors'] = ['comments' => $result['message'] ?? 'Unable to process approval action.'];
    redirect('approvals/show.php?id=' . $requestId);
}

flash('success', 'Approval action saved.');
redirect('approvals/show.php?id=' . $requestId);
