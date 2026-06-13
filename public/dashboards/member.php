<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/role.php';
require_once BASE_PATH . '/app/Views/dashboard/components.php';

$user = require_role('club_member');
$title = 'Club Member Dashboard';
$navItems = dashboard_nav($user['role_key']);
require BASE_PATH . '/app/Views/layouts/dashboard_header.php';

render_metric_cards([
    ['label' => 'Registered Events', 'value' => '0', 'hint' => 'Event registration placeholder'],
    ['label' => 'Attendance', 'value' => '0', 'hint' => 'QR attendance placeholder'],
    ['label' => 'Reward Points', 'value' => '0', 'hint' => 'Rewards module placeholder'],
    ['label' => 'Certificates', 'value' => '0', 'hint' => 'Certificate module placeholder'],
]);

render_placeholder_panel('Member Activity', [
    ['title' => 'Participation', 'body' => 'Event registration and attendance will appear here later.'],
    ['title' => 'Rewards', 'body' => 'Points and badges will connect after event participation exists.'],
    ['title' => 'Access boundary', 'body' => 'Members cannot view coordinator approval queues.'],
]);

require BASE_PATH . '/app/Views/layouts/dashboard_footer.php';
