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

try {
    EventService::submitForApproval($eventId, $user);
    flash('success', 'Event submitted for approval.');
} catch (Throwable $exception) {
    flash('error', $exception->getMessage());
}

redirect('events/show.php?id=' . $eventId);
