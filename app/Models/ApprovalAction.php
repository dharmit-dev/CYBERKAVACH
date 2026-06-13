<?php

declare(strict_types=1);

final class ApprovalAction
{
    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO approval_actions
                (approval_request_id, step_order, actor_user_id, actor_role_id, action, comments, created_at)
             VALUES
                (:approval_request_id, :step_order, :actor_user_id, :actor_role_id, :action, :comments, NOW())'
        );
        $stmt->execute([
            'approval_request_id' => $data['approval_request_id'],
            'step_order' => $data['step_order'],
            'actor_user_id' => $data['actor_user_id'],
            'actor_role_id' => $data['actor_role_id'],
            'action' => $data['action'],
            'comments' => $data['comments'] ?? null,
        ]);

        return (int) db()->lastInsertId();
    }

    public static function timelineForRequest(int $approvalRequestId): array
    {
        $stmt = db()->prepare(
            'SELECT aa.*, users.full_name AS actor_name, roles.role_name AS actor_role_name
             FROM approval_actions aa
             INNER JOIN users ON users.id = aa.actor_user_id
             INNER JOIN roles ON roles.id = aa.actor_role_id
             WHERE aa.approval_request_id = :approval_request_id
             ORDER BY aa.created_at ASC, aa.id ASC'
        );
        $stmt->execute(['approval_request_id' => $approvalRequestId]);

        return $stmt->fetchAll();
    }
}
