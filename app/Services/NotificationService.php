<?php

declare(strict_types=1);

final class NotificationService
{
    public static function notifyRole(string $roleKey, array $payload): void
    {
        $stmt = db()->prepare(
            'SELECT users.id
             FROM users
             INNER JOIN roles ON roles.id = users.role_id
             WHERE roles.role_key = :role_key AND users.status = :status'
        );
        $stmt->execute([
            'role_key' => $roleKey,
            'status' => 'active',
        ]);

        $recipientIds = array_column($stmt->fetchAll(), 'id');
        Notification::create($payload, $recipientIds);
    }

    public static function notifyUsers(array $userIds, array $payload): void
    {
        Notification::create($payload, $userIds);
    }
}
