<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/role.php';

$user = require_role(['student_coordinator', 'faculty_coordinator', 'tech_coordinator']);
$event = Event::findById((int) ($_GET['id'] ?? 0));

if (!$event) {
    http_response_code(404);
    exit('Event not found.');
}

$participants = EventRegistration::participantsForEvent((int) $event['id']);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="event-' . (int) $event['id'] . '-registrations.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Name', 'Email', 'Phone', 'Registration Type', 'Team Name', 'Team ID', 'Registered At']);

foreach ($participants as $participant) {
    fputcsv($out, [
        $participant['full_name'],
        $participant['email'],
        $participant['phone'],
        $participant['registration_type'],
        $participant['team_name'] ?? '',
        $participant['team_identifier'] ?? '',
        $participant['registered_at'],
    ]);
}

AuditService::record('event_registrations_exported', 'events', (int) $user['id'], 'events', (int) $event['id']);
