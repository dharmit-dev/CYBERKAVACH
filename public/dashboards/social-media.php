<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/role.php';
require_once BASE_PATH . '/app/Views/dashboard/components.php';

$user = require_role('social_media_coordinator');
$title = 'Social Media Coordinator Dashboard';
$navItems = dashboard_nav($user['role_key']);
require BASE_PATH . '/app/Views/layouts/dashboard_header.php';

render_metric_cards([
    ['label' => 'Campaigns', 'value' => '0', 'hint' => 'Social module placeholder'],
    ['label' => 'Scheduled Posts', 'value' => '0', 'hint' => 'Post scheduling placeholder'],
    ['label' => 'Awaiting Approval', 'value' => '0', 'hint' => 'Social approval placeholder'],
    ['label' => 'Published Posts', 'value' => '0', 'hint' => 'Publishing history placeholder'],
]);

render_placeholder_panel('Social Workspace', [
    ['title' => 'Creative queue', 'body' => 'Captions and media planning will be added in a later chunk.'],
    ['title' => 'Approval-ready', 'body' => 'Social post approvals will use the same reusable workflow engine.'],
    ['title' => 'Strict access', 'body' => 'Approval requests remain hidden unless submitted by this user.'],
]);

require BASE_PATH . '/app/Views/layouts/dashboard_footer.php';
