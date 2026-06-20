<?php

declare(strict_types=1);

final class ContentPost
{
    public static function findById(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT cp.*, u.full_name as requester_name
             FROM content_posts cp
             INNER JOIN users u ON u.id = cp.requested_by
             WHERE cp.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO content_posts (title, content_body, category, requested_by, status, created_at, updated_at)
             VALUES (:title, :content_body, :category, :requested_by, :status, NOW(), NOW())'
        );
        $stmt->execute([
            'title' => $data['title'],
            'content_body' => $data['content_body'],
            'category' => $data['category'],
            'requested_by' => $data['requested_by'],
            'status' => $data['status'] ?? 'pending',
        ]);

        return (int) db()->lastInsertId();
    }

    public static function updateStatus(int $id, string $status): void
    {
        $stmt = db()->prepare(
            'UPDATE content_posts SET status = :status, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['id' => $id, 'status' => $status]);
    }
}
