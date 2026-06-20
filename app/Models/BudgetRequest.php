<?php

declare(strict_types=1);

final class BudgetRequest
{
    public static function findById(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT br.*, e.title as event_title, u.full_name as requester_name
             FROM budget_requests br
             LEFT JOIN events e ON e.id = br.event_id
             INNER JOIN users u ON u.id = br.requested_by
             WHERE br.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO budget_requests (event_id, amount, purpose, requested_by, status, created_at, updated_at)
             VALUES (:event_id, :amount, :purpose, :requested_by, :status, NOW(), NOW())'
        );
        $stmt->execute([
            'event_id' => $data['event_id'] ?: null,
            'amount' => $data['amount'],
            'purpose' => $data['purpose'],
            'requested_by' => $data['requested_by'],
            'status' => $data['status'] ?? 'pending',
        ]);

        return (int) db()->lastInsertId();
    }

    public static function updateStatus(int $id, string $status): void
    {
        $stmt = db()->prepare(
            'UPDATE budget_requests SET status = :status, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['id' => $id, 'status' => $status]);
    }
}
