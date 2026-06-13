<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/role.php';
require_once BASE_PATH . '/app/Views/dashboard/components.php';

$user = require_role('content_coordinator');
$title = 'Content Coordinator Dashboard';
$navItems = dashboard_nav($user['role_key']);
require BASE_PATH . '/app/Views/layouts/dashboard_header.php';

render_metric_cards([
    ['label' => 'Drafts', 'value' => '0', 'hint' => 'Content module placeholder'],
    ['label' => 'Pending Publish', 'value' => '0', 'hint' => 'Content approval placeholder'],
    ['label' => 'Published', 'value' => '0', 'hint' => 'Published content placeholder'],
    ['label' => 'Returned Drafts', 'value' => '0', 'hint' => 'Revision queue placeholder'],
]);

render_placeholder_panel('Content Workspace', [
    ['title' => 'Event descriptions', 'body' => 'Content drafting will be added after core event management.'],
    ['title' => 'Publishing requests', 'body' => 'Publishing approval will use the generic approval engine.'],
    ['title' => 'Visibility rule', 'body' => 'This dashboard does not expose unrelated approval queues.'],
]);

require BASE_PATH . '/app/Views/layouts/dashboard_footer.php';
