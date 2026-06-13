<?php

declare(strict_types=1);

final class ApprovalRequest
{
    public static function findById(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT ar.*, aw.workflow_key, aw.name AS workflow_name, aw.escalation_hours,
                    submitter.full_name AS submitter_name, submitter.email AS submitter_email
             FROM approval_requests ar
             INNER JOIN approval_workflows aw ON aw.id = ar.workflow_id
             INNER JOIN users submitter ON submitter.id = ar.requested_by
             WHERE ar.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $request = $stmt->fetch();

        return $request ?: null;
    }

    public static function findOpenForEntity(string $workflowKey, string $entityType, int $entityId): ?array
    {
        $stmt = db()->prepare(
            "SELECT ar.*
             FROM approval_requests ar
             INNER JOIN approval_workflows aw ON aw.id = ar.workflow_id
             WHERE aw.workflow_key = :workflow_key
                AND ar.entity_type = :entity_type
                AND ar.entity_id = :entity_id
                AND ar.status IN ('pending', 'under_review', 'returned')
             ORDER BY ar.id DESC
             LIMIT 1"
        );
        $stmt->execute([
            'workflow_key' => $workflowKey,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ]);
        $request = $stmt->fetch();

        return $request ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO approval_requests
                (workflow_id, entity_type, entity_id, title, requested_by, current_step_order, status, escalation_due_at, created_at, updated_at)
             VALUES
                (:workflow_id, :entity_type, :entity_id, :title, :requested_by, :current_step_order, :status, :escalation_due_at, NOW(), NOW())'
        );
        $stmt->execute([
            'workflow_id' => $data['workflow_id'],
            'entity_type' => $data['entity_type'],
            'entity_id' => $data['entity_id'],
            'title' => $data['title'],
            'requested_by' => $data['requested_by'],
            'current_step_order' => $data['current_step_order'] ?? 1,
            'status' => $data['status'] ?? 'pending',
            'escalation_due_at' => $data['escalation_due_at'] ?? null,
        ]);

        return (int) db()->lastInsertId();
    }

    public static function updateStatus(int $id, string $status, int $stepOrder, ?int $finalDecisionBy = null): void
    {
        $completedStatuses = ['approved', 'rejected', 'cancelled'];
        $stmt = db()->prepare(
            'UPDATE approval_requests
             SET status = :status,
                 current_step_order = :current_step_order,
                 final_decision_by = :final_decision_by,
                 completed_at = :completed_at,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'status' => $status,
            'current_step_order' => $stepOrder,
            'final_decision_by' => $finalDecisionBy,
            'completed_at' => in_array($status, $completedStatuses, true) ? date('Y-m-d H:i:s') : null,
        ]);
    }

    public static function listVisibleForUser(array $user): array
    {
        if (in_array($user['role_key'], ['faculty_coordinator', 'student_coordinator'], true)) {
            $stmt = db()->prepare(
                'SELECT ar.*, aw.name AS workflow_name, aw.workflow_key,
                        submitter.full_name AS submitter_name,
                        step.step_name AS current_step_name,
                        role.role_name AS current_approver_role
                 FROM approval_requests ar
                 INNER JOIN approval_workflows aw ON aw.id = ar.workflow_id
                 INNER JOIN users submitter ON submitter.id = ar.requested_by
                 LEFT JOIN approval_workflow_steps step
                    ON step.workflow_id = ar.workflow_id AND step.step_order = ar.current_step_order
                 LEFT JOIN roles role ON role.id = step.approver_role_id
                 ORDER BY ar.updated_at DESC, ar.created_at DESC
                 LIMIT 100'
            );
            $stmt->execute();

            return $stmt->fetchAll();
        }

        $stmt = db()->prepare(
            'SELECT ar.*, aw.name AS workflow_name, aw.workflow_key,
                    submitter.full_name AS submitter_name,
                    step.step_name AS current_step_name,
                    role.role_name AS current_approver_role
             FROM approval_requests ar
             INNER JOIN approval_workflows aw ON aw.id = ar.workflow_id
             INNER JOIN users submitter ON submitter.id = ar.requested_by
             LEFT JOIN approval_workflow_steps step
                ON step.workflow_id = ar.workflow_id AND step.step_order = ar.current_step_order
             LEFT JOIN roles role ON role.id = step.approver_role_id
             WHERE ar.requested_by = :user_id
             ORDER BY ar.updated_at DESC, ar.created_at DESC
             LIMIT 100'
        );
        $stmt->execute(['user_id' => $user['id']]);

        return $stmt->fetchAll();
    }

    public static function countByStatusForUser(array $user, string $status): int
    {
        if (in_array($user['role_key'], ['faculty_coordinator', 'student_coordinator'], true)) {
            $stmt = db()->prepare('SELECT COUNT(*) FROM approval_requests WHERE status = :status');
            $stmt->execute(['status' => $status]);
            return (int) $stmt->fetchColumn();
        }

        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM approval_requests WHERE requested_by = :user_id AND status = :status'
        );
        $stmt->execute([
            'user_id' => $user['id'],
            'status' => $status,
        ]);

        return (int) $stmt->fetchColumn();
    }
}
