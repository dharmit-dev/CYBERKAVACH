<?php

declare(strict_types=1);

final class Notification
{
    public static function create(array $data, array $recipientIds): int
    {
        $stmt = db()->prepare(
            'INSERT INTO notifications
                (title, message, type, entity_type, entity_id, created_by, created_at)
             VALUES
                (:title, :message, :type, :entity_type, :entity_id, :created_by, NOW())'
        );
        $stmt->execute([
            'title' => $data['title'],
            'message' => $data['message'],
            'type' => $data['type'],
            'entity_type' => $data['entity_type'] ?? null,
            'entity_id' => $data['entity_id'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);

        $notificationId = (int) db()->lastInsertId();
        $insertRecipient = db()->prepare(
            'INSERT IGNORE INTO notification_recipients (notification_id, user_id, created_at)
             VALUES (:notification_id, :user_id, NOW())'
        );

        foreach (array_unique(array_map('intval', $recipientIds)) as $userId) {
            if ($userId > 0) {
                $insertRecipient->execute([
                    'notification_id' => $notificationId,
                    'user_id' => $userId,
                ]);
            }
        }

        return $notificationId;
    }

    public static function unreadForUser(int $userId, int $limit = 8): array
    {
        $stmt = db()->prepare(
            'SELECT n.*, nr.is_read
             FROM notification_recipients nr
             INNER JOIN notifications n ON n.id = nr.notification_id
             WHERE nr.user_id = :user_id AND nr.is_read = 0
             ORDER BY n.created_at DESC
             LIMIT ' . max(1, min(25, $limit))
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    public static function countUnreadForUser(int $userId): int
    {
        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM notification_recipients WHERE user_id = :user_id AND is_read = 0'
        );
        $stmt->execute(['user_id' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    public static function markRead(int $notificationId, int $userId): void
    {
        $stmt = db()->prepare(
            'UPDATE notification_recipients
             SET is_read = 1, read_at = NOW()
             WHERE notification_id = :notification_id AND user_id = :user_id'
        );
        $stmt->execute([
            'notification_id' => $notificationId,
            'user_id' => $userId,
        ]);
    }

    public static function markAllRead(int $userId): void
    {
        $stmt = db()->prepare(
            'UPDATE notification_recipients
             SET is_read = 1, read_at = NOW()
             WHERE user_id = :user_id AND is_read = 0'
        );
        $stmt->execute(['user_id' => $userId]);
    }
}
