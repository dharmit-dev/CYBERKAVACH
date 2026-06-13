<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/role.php';
require_once BASE_PATH . '/app/Views/dashboard/components.php';

$user = require_role(['student_coordinator', 'faculty_coordinator', 'tech_coordinator']);
$event = Event::findById((int) ($_GET['id'] ?? 0));

if (!$event) {
    flash('error', 'Event not found.');
    redirect('events/manage.php');
}

$categories = Event::categories();
$title = 'Edit Event';
$navItems = dashboard_nav($user['role_key']);
require BASE_PATH . '/app/Views/layouts/dashboard_header.php';
require BASE_PATH . '/public/events/form.php';
require BASE_PATH . '/app/Views/layouts/dashboard_footer.php';
