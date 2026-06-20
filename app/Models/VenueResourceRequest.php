<?php

declare(strict_types=1);

final class VenueResourceRequest
{
    public static function findById(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT vrr.*, e.title as event_title, u.full_name as requester_name
             FROM venue_resource_requests vrr
             LEFT JOIN events e ON e.id = vrr.event_id
             INNER JOIN users u ON u.id = vrr.requested_by
             WHERE vrr.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO venue_resource_requests (event_id, venue, resources_needed, start_time, end_time, requested_by, status, created_at, updated_at)
             VALUES (:event_id, :venue, :resources_needed, :start_time, :end_time, :requested_by, :status, NOW(), NOW())'
        );
        $stmt->execute([
            'event_id' => $data['event_id'] ?: null,
            'venue' => $data['venue'],
            'resources_needed' => $data['resources_needed'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'requested_by' => $data['requested_by'],
            'status' => $data['status'] ?? 'pending',
        ]);

        return (int) db()->lastInsertId();
    }

    public static function updateStatus(int $id, string $status): void
    {
        $stmt = db()->prepare(
            'UPDATE venue_resource_requests SET status = :status, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['id' => $id, 'status' => $status]);
    }
}
