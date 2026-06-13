<?php

declare(strict_types=1);

final class EventService
{
    public static function save(array $input, array $user, ?array $existing = null): array
    {
        $errors = self::validate($input);
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $posterPath = $existing['poster_path'] ?? null;
        if (!empty($_FILES['poster']['name'])) {
            $upload = self::uploadPoster($_FILES['poster']);
            if (!$upload['ok']) {
                return ['ok' => false, 'errors' => ['poster' => $upload['message']]];
            }
            $posterPath = $upload['path'];
        }

        $data = [
            'category_id' => (int) $input['category_id'],
            'title' => $input['title'],
            'slug' => self::uniqueSlug($input['title'], $existing['id'] ?? null),
            'description' => $input['description'],
            'event_date' => $input['event_date'],
            'start_time' => $input['start_time'],
            'end_time' => $input['end_time'],
            'venue' => $input['venue'],
            'registration_deadline' => $input['registration_deadline_date'] . ' ' . $input['registration_deadline_time'] . ':00',
            'capacity' => (int) $input['capacity'],
            'poster_path' => $posterPath,
            'team_allowed' => !empty($input['team_allowed']),
            'min_team_size' => (int) ($input['min_team_size'] ?? 0),
            'max_team_size' => (int) ($input['max_team_size'] ?? 0),
            'event_rules' => $input['event_rules'],
            'tags' => $input['tags'],
            'status' => 'draft',
            'created_by' => $existing['created_by'] ?? $user['id'],
        ];

        if ($existing) {
            Event::update((int) $existing['id'], $data);
            $eventId = (int) $existing['id'];
            AuditService::record('event_updated', 'events', (int) $user['id'], 'events', $eventId);
        } else {
            $eventId = Event::create($data);
            AuditService::record('event_created', 'events', (int) $user['id'], 'events', $eventId);
        }

        return ['ok' => true, 'event_id' => $eventId];
    }

    public static function submitForApproval(int $eventId, array $user): void
    {
        $event = Event::findById($eventId);
        if (!$event) {
            throw new RuntimeException('Event not found.');
        }

        Event::submitForApproval($eventId);
        ApprovalService::submit(
            'event_approval',
            'event',
            $eventId,
            (int) $user['id'],
            'Event approval: ' . $event['title'],
            'Event submitted for review and publication approval.'
        );

        NotificationService::notifyRole('student_coordinator', [
            'title' => 'Event submitted',
            'message' => $event['title'] . ' is ready for event approval.',
            'type' => 'event_submitted',
            'entity_type' => 'event',
            'entity_id' => $eventId,
            'created_by' => $user['id'],
        ]);
        AuditService::record('event_submitted', 'events', (int) $user['id'], 'events', $eventId);
    }

    public static function register(array $input, array $user): array
    {
        $event = Event::findById((int) $input['event_id']);
        if (!$event || $event['status'] !== 'published') {
            return ['ok' => false, 'message' => 'Event is not available for registration.'];
        }

        if (strtotime($event['registration_deadline']) < time()) {
            return ['ok' => false, 'message' => 'Registration deadline has passed.'];
        }

        if (EventRegistration::countForEvent((int) $event['id']) >= (int) $event['capacity']) {
            return ['ok' => false, 'message' => 'Event capacity is full.'];
        }

        $type = $input['registration_type'] ?? 'individual';

        try {
            db()->beginTransaction();

            if ($type === 'individual') {
                if (EventRegistration::userRegistered((int) $event['id'], (int) $user['id'])) {
                    throw new RuntimeException('You are already registered for this event.');
                }

                EventRegistration::registerIndividual((int) $event['id'], (int) $user['id']);
                $message = 'Registration successful.';
            } else {
                if ((int) $event['team_allowed'] !== 1) {
                    throw new RuntimeException('Team registration is not enabled for this event.');
                }

                if (trim((string) ($input['team_name'] ?? '')) === '') {
                    throw new RuntimeException('Team name is required.');
                }

                $savedTeamId = (int) ($input['saved_team_id'] ?? 0);
                $memberIds = $savedTeamId > 0
                    ? EventRegistration::savedTeamMemberIds($savedTeamId, (int) $user['id'])
                    : array_filter(array_unique(array_map('intval', explode(',', (string) ($input['member_ids'] ?? '')))));
                $memberIds[] = (int) $user['id'];
                $memberIds = array_values(array_unique($memberIds));

                $min = (int) $event['min_team_size'];
                $max = (int) $event['max_team_size'];
                if (count($memberIds) < $min || count($memberIds) > $max) {
                    throw new RuntimeException('Team size must be between ' . $min . ' and ' . $max . ' members.');
                }

                foreach ($memberIds as $memberId) {
                    $member = User::findById($memberId);
                    if (!$member || $member['status'] !== 'active') {
                        throw new RuntimeException('One or more selected members are not active registered users.');
                    }

                    if (EventRegistration::userRegistered((int) $event['id'], $memberId)) {
                        throw new RuntimeException('One or more selected members are already registered for this event.');
                    }
                }

                EventRegistration::createTeamRegistration((int) $event['id'], (int) $user['id'], trim((string) $input['team_name']), $memberIds);
                NotificationService::notifyUsers($memberIds, [
                    'title' => 'Team created',
                    'message' => 'Team ' . trim((string) $input['team_name']) . ' was registered for ' . $event['title'] . '.',
                    'type' => 'team_created',
                    'entity_type' => 'event',
                    'entity_id' => (int) $event['id'],
                    'created_by' => $user['id'],
                ]);
                $message = 'Team registration successful. Team QR has been generated.';
            }

            db()->commit();
        } catch (Throwable $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            return ['ok' => false, 'message' => $exception->getMessage()];
        }

        NotificationService::notifyUsers([(int) $user['id']], [
            'title' => 'Registration success',
            'message' => $message . ' Event: ' . $event['title'],
            'type' => 'registration_success',
            'entity_type' => 'event',
            'entity_id' => (int) $event['id'],
            'created_by' => $user['id'],
        ]);
        AuditService::record('event_registered', 'events', (int) $user['id'], 'events', (int) $event['id']);

        return ['ok' => true, 'message' => $message];
    }

    private static function validate(array $input): array
    {
        $errors = [];
        foreach (['category_id', 'title', 'description', 'event_date', 'start_time', 'end_time', 'venue', 'registration_deadline_date', 'registration_deadline_time', 'capacity'] as $field) {
            if (trim((string) ($input[$field] ?? '')) === '') {
                $errors[$field] = 'This field is required.';
            }
        }

        if (!empty($input['event_date']) && strtotime($input['event_date']) === false) {
            $errors['event_date'] = 'Enter a valid event date.';
        }

        if (!empty($input['start_time']) && !empty($input['end_time']) && $input['end_time'] <= $input['start_time']) {
            $errors['end_time'] = 'End time must be after start time.';
        }

        if ((int) ($input['capacity'] ?? 0) < 1) {
            $errors['capacity'] = 'Capacity must be at least 1.';
        }

        if (!empty($input['team_allowed'])) {
            $min = (int) ($input['min_team_size'] ?? 0);
            $max = (int) ($input['max_team_size'] ?? 0);
            if ($min < 2 || $max < $min) {
                $errors['max_team_size'] = 'Team size limits are invalid.';
            }
        }

        return $errors;
    }

    private static function uploadPoster(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'message' => 'Poster upload failed.'];
        }

        if ((int) $file['size'] > 2 * 1024 * 1024) {
            return ['ok' => false, 'message' => 'Poster must be 2MB or smaller.'];
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

        if (!isset($allowed[$mime])) {
            return ['ok' => false, 'message' => 'Poster must be JPG, PNG, or WEBP.'];
        }

        $filename = 'event-' . bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
        $relativePath = 'uploads/event-posters/' . $filename;
        $absolutePath = BASE_PATH . '/public/' . $relativePath;

        if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
            return ['ok' => false, 'message' => 'Unable to save poster.'];
        }

        return ['ok' => true, 'path' => $relativePath];
    }

    private static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $title), '-')) ?: 'event';
        $slug = $base;
        $i = 2;

        while (self::slugExists($slug, $ignoreId)) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    private static function slugExists(string $slug, ?int $ignoreId): bool
    {
        $sql = 'SELECT COUNT(*) FROM events WHERE slug = :slug';
        $params = ['slug' => $slug];
        if ($ignoreId) {
            $sql .= ' AND id != :id';
            $params['id'] = $ignoreId;
        }
        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }
}
