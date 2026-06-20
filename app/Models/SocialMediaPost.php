<?php

declare(strict_types=1);

final class SocialMediaPost
{
    public static function findById(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT smp.*, u.full_name as requester_name
             FROM social_media_posts smp
             INNER JOIN users u ON u.id = smp.requested_by
             WHERE smp.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO social_media_posts (platforms, caption, image_path, schedule_time, requested_by, status, created_at, updated_at)
             VALUES (:platforms, :caption, :image_path, :schedule_time, :requested_by, :status, NOW(), NOW())'
        );
        $stmt->execute([
            'platforms' => $data['platforms'],
            'caption' => $data['caption'],
            'image_path' => $data['image_path'] ?: null,
            'schedule_time' => $data['schedule_time'] ?: null,
            'requested_by' => $data['requested_by'],
            'status' => $data['status'] ?? 'pending',
        ]);

        return (int) db()->lastInsertId();
    }

    public static function updateStatus(int $id, string $status): void
    {
        $stmt = db()->prepare(
            'UPDATE social_media_posts SET status = :status, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['id' => $id, 'status' => $status]);
    }
}
