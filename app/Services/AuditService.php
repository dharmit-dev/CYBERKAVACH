<?php

declare(strict_types=1);

final class AuditService
{
    public static function record(
        string $action,
        string $module,
        ?int $userId = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        $stmt = db()->prepare(
            'INSERT INTO audit_logs
                (user_id, action, module, entity_type, entity_id, old_values, new_values, ip_address, user_agent, created_at)
             VALUES
                (:user_id, :action, :module, :entity_type, :entity_id, :old_values, :new_values, :ip_address, :user_agent, NOW())'
        );

        $stmt->execute([
            'user_id' => $userId,
            'action' => $action,
            'module' => $module,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $oldValues ? json_encode($oldValues, JSON_THROW_ON_ERROR) : null,
            'new_values' => $newValues ? json_encode($newValues, JSON_THROW_ON_ERROR) : null,
            'ip_address' => client_ip(),
            'user_agent' => user_agent(),
        ]);
    }

    public static function loginAttempt(?int $userId, string $email, bool $success, string $reason): void
    {
        $stmt = db()->prepare(
            'INSERT INTO login_attempts
                (user_id, email, success, failure_reason, ip_address, user_agent, attempted_at)
             VALUES
                (:user_id, :email, :success, :failure_reason, :ip_address, :user_agent, NOW())'
        );

        $stmt->execute([
            'user_id' => $userId,
            'email' => strtolower($email),
            'success' => $success ? 1 : 0,
            'failure_reason' => $success ? null : $reason,
            'ip_address' => client_ip(),
            'user_agent' => user_agent(),
        ]);
    }
}
