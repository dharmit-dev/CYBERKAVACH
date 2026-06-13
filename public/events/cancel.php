<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/role.php';

$user = require_role(['student_coordinator', 'faculty_coordinator', 'tech_coordinator']);

if (!request_method_is('POST')) {
    redirect('events/manage.php');
}

verify_csrf();
$eventId = (int) input_string('id');
Event::cancel($eventId);
AuditService::record('event_cancelled', 'events', (int) $user['id'], 'events', $eventId);
flash('success', 'Event cancelled.');
redirect('events/manage.php');
