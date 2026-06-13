<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/role.php';
require_once BASE_PATH . '/app/Views/dashboard/components.php';

$user = require_role('faculty_coordinator');
$title = 'Faculty Coordinator Dashboard';
$navItems = dashboard_nav($user['role_key']);
require BASE_PATH . '/app/Views/layouts/dashboard_header.php';

render_metric_cards([
    ['label' => 'Pending Approvals', 'value' => ApprovalRequest::countByStatusForUser($user, 'pending') + ApprovalRequest::countByStatusForUser($user, 'under_review'), 'hint' => 'Requests waiting across approval workflows'],
    ['label' => 'Pending Accounts', 'value' => User::countByStatus('pending_approval'), 'hint' => 'Verified accounts needing approval'],
    ['label' => 'Club Members', 'value' => User::countByRoleKey('club_member'), 'hint' => 'Active approved members'],
    ['label' => 'Escalations', 'value' => count(ApprovalService::escalationCandidates()), 'hint' => 'Requests past configured review window'],
]);

render_placeholder_panel('Governance Overview', [
    ['title' => 'Final approvals', 'body' => 'Review account approvals and future event, budget, venue, content, social, and certificate requests.'],
    ['title' => 'Analytics oversight', 'body' => 'Club-wide analytics dashboards will connect in a later chunk.'],
    ['title' => 'Audit visibility', 'body' => 'Authentication and approval decisions are logged for accountability.'],
]);

require BASE_PATH . '/app/Views/layouts/dashboard_footer.php';
