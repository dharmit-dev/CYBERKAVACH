<?php

declare(strict_types=1);

final class ExternalCollaboration
{
    public static function findById(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT ec.*, u.full_name as requester_name
             FROM external_collaborations ec
             INNER JOIN users u ON u.id = ec.requested_by
             WHERE ec.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO external_collaborations (partner_name, description, contact_person, contact_email, requested_by, status, created_at, updated_at)
             VALUES (:partner_name, :description, :contact_person, :contact_email, :requested_by, :status, NOW(), NOW())'
        );
        $stmt->execute([
            'partner_name' => $data['partner_name'],
            'description' => $data['description'],
            'contact_person' => $data['contact_person'],
            'contact_email' => $data['contact_email'],
            'requested_by' => $data['requested_by'],
            'status' => $data['status'] ?? 'pending',
        ]);

        return (int) db()->lastInsertId();
    }

    public static function updateStatus(int $id, string $status): void
    {
        $stmt = db()->prepare(
            'UPDATE external_collaborations SET status = :status, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['id' => $id, 'status' => $status]);
    }
}
