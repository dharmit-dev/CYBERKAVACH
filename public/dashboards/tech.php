<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/role.php';
require_once BASE_PATH . '/app/Views/dashboard/components.php';

$user = require_role('tech_coordinator');
$title = 'Tech Coordinator Dashboard';
$navItems = dashboard_nav($user['role_key']);
require BASE_PATH . '/app/Views/layouts/dashboard_header.php';

render_metric_cards([
    ['label' => 'QR Sessions', 'value' => '0', 'hint' => 'QR attendance module arrives later'],
    ['label' => 'Attendance Logs', 'value' => '0', 'hint' => 'Check-in/check-out records placeholder'],
    ['label' => 'Certificate Jobs', 'value' => '0', 'hint' => 'Generation queue placeholder'],
    ['label' => 'Tech Alerts', 'value' => '0', 'hint' => 'Technical notices placeholder'],
]);

render_placeholder_panel('Technical Workspace', [
    ['title' => 'QR attendance', 'body' => 'QR session controls will be restricted here in the attendance chunk.'],
    ['title' => 'Platform support', 'body' => 'Technical operations remain separate from approval visibility.'],
    ['title' => 'Security posture', 'body' => 'This role cannot see unrelated approval requests.'],
]);

require BASE_PATH . '/app/Views/layouts/dashboard_footer.php';
