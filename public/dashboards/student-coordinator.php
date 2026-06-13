<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/role.php';
require_once BASE_PATH . '/app/Views/dashboard/components.php';

$user = require_role('student_coordinator');
$title = 'Student Coordinator Dashboard';
$navItems = dashboard_nav($user['role_key']);
require BASE_PATH . '/app/Views/layouts/dashboard_header.php';

render_metric_cards([
    ['label' => 'Review Queue', 'value' => ApprovalRequest::countByStatusForUser($user, 'pending'), 'hint' => 'Requests awaiting first-level review'],
    ['label' => 'Under Review', 'value' => ApprovalRequest::countByStatusForUser($user, 'under_review'), 'hint' => 'Requests moved to next approval level'],
    ['label' => 'Pending Accounts', 'value' => User::countByStatus('pending_approval'), 'hint' => 'Verified student accounts waiting'],
    ['label' => 'Returned', 'value' => ApprovalRequest::countByStatusForUser($user, 'returned'), 'hint' => 'Requests needing submitter correction'],
]);

render_placeholder_panel('Operations Queue', [
    ['title' => 'Account review', 'body' => 'Screen verified registrations before faculty final approval.'],
    ['title' => 'Workflow coordination', 'body' => 'Future event and resource requests will appear in the same approval queue.'],
    ['title' => 'Remarks tracking', 'body' => 'Coordinator comments are preserved in the approval timeline.'],
]);

require BASE_PATH . '/app/Views/layouts/dashboard_footer.php';
