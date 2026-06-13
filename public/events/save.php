<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/role.php';

$user = require_role(['student_coordinator', 'faculty_coordinator', 'tech_coordinator']);

if (!request_method_is('POST')) {
    redirect('events/manage.php');
}

verify_csrf();

$id = (int) input_string('id');
$existing = $id > 0 ? Event::findById($id) : null;
$input = [
    'category_id' => input_string('category_id'),
    'title' => input_string('title'),
    'description' => input_string('description'),
    'event_date' => input_string('event_date'),
    'start_time' => input_string('start_time'),
    'end_time' => input_string('end_time'),
    'venue' => input_string('venue'),
    'registration_deadline_date' => input_string('registration_deadline_date'),
    'registration_deadline_time' => input_string('registration_deadline_time'),
    'capacity' => input_string('capacity'),
    'team_allowed' => isset($_POST['team_allowed']) ? '1' : '',
    'min_team_size' => input_string('min_team_size'),
    'max_team_size' => input_string('max_team_size'),
    'event_rules' => input_string('event_rules'),
    'tags' => input_string('tags'),
];

$result = EventService::save($input, $user, $existing);

if (!$result['ok']) {
    back_with_errors($result['errors'], $input);
}

if (isset($_POST['action']) && $_POST['action'] === 'submit') {
    EventService::submitForApproval((int) $result['event_id'], $user);
    flash('success', 'Event saved and submitted for approval.');
} else {
    $statusMsg = ($existing && in_array($existing['status'], ['approved', 'published', 'pending_approval', 'under_review'], true)) 
        ? 'Event updated and reverted to draft. Please submit for approval when ready.' 
        : 'Event saved as draft.';
    flash('success', $statusMsg);
}

redirect('events/edit.php?id=' . (int) $result['event_id']);
