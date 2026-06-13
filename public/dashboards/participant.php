<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/role.php';
require_once BASE_PATH . '/app/Views/dashboard/components.php';

$user = require_role('guest_participant');
$title = 'Guest / Student Participant Dashboard';
$navItems = dashboard_nav($user['role_key']);
require BASE_PATH . '/app/Views/layouts/dashboard_header.php';

render_metric_cards([
    ['label' => 'Open Events', 'value' => '0', 'hint' => 'Public event listing placeholder'],
    ['label' => 'My Registrations', 'value' => '0', 'hint' => 'Registration history placeholder'],
    ['label' => 'Attendance', 'value' => '0', 'hint' => 'Check-in/check-out placeholder'],
    ['label' => 'Certificates', 'value' => '0', 'hint' => 'Certificate access placeholder'],
]);

render_placeholder_panel('Participant Area', [
    ['title' => 'Event access', 'body' => 'Approved event registration will become available in the event chunk.'],
    ['title' => 'Certificate verification', 'body' => 'Public certificate verification will be added in the certificate chunk.'],
    ['title' => 'Privacy boundary', 'body' => 'Participants cannot see unrelated approval requests.'],
]);

require BASE_PATH . '/app/Views/layouts/dashboard_footer.php';
