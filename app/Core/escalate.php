<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/app/Core/env.php';
load_env(BASE_PATH . '/.env');
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/app/Core/database.php';
require_once BASE_PATH . '/app/Models/User.php';
require_once BASE_PATH . '/app/Models/ApprovalRequest.php';
require_once BASE_PATH . '/app/Models/ApprovalAction.php';
require_once BASE_PATH . '/app/Models/Notification.php';
require_once BASE_PATH . '/app/Services/NotificationService.php';
require_once BASE_PATH . '/app/Services/AuditService.php';
require_once BASE_PATH . '/app/Services/ApprovalService.php';

// Access gating if called from web
if (PHP_SAPI !== 'cli') {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('CYBERKAVACH_SESSION');
        session_start();
    }
    require_once BASE_PATH . '/app/Helpers/functions.php';
    $user = current_user();
    if (!$user || !in_array($user['role_key'], ['student_coordinator', 'faculty_coordinator'], true)) {
        http_response_code(403);
        exit('Access denied.');
    }
}

try {
    $db = db();
    $candidates = ApprovalService::escalationCandidates();
    $count = 0;

    foreach ($candidates as $req) {
        $reqId = (int) $req['id'];
        $title = $req['title'];
        $workflowKey = $req['workflow_key'];
        $hours = $req['escalation_hours'] ?: 48;

        // 1. Log escalation action
        $stmtAction = $db->prepare("
            INSERT INTO approval_actions (approval_request_id, step_order, actor_user_id, actor_role_id, action, comments, created_at)
            SELECT :request_id, current_step_order, requested_by, 6, 'escalated', 'Request idle beyond configured threshold.', NOW()
            FROM approval_requests WHERE id = :id_1
        ");
        $stmtAction->execute([
            'request_id' => $reqId,
            'id_1' => $reqId
        ]);

        // 2. Disable future escalation for this request to prevent double alerts
        $stmtUpdate = $db->prepare("
            UPDATE approval_requests 
            SET escalation_due_at = NULL, updated_at = NOW() 
            WHERE id = :id
        ");
        $stmtUpdate->execute(['id' => $reqId]);

        // 3. Notify Student and Faculty Coordinators
        $recipients = [];
        $stmtUsers = $db->query("
            SELECT u.id 
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            WHERE r.role_key IN ('student_coordinator', 'faculty_coordinator') AND u.status = 'active'
        ");
        $recipients = $stmtUsers->fetchAll(PDO::FETCH_COLUMN);

        NotificationService::notifyUsers($recipients, [
            'title' => 'Request Escalated (Idle Alert)',
            'message' => "The request \"{$title}\" has been escalated due to being idle for over {$hours} hours.",
            'type' => 'approval_escalated',
            'entity_type' => 'approval_request',
            'entity_id' => $reqId,
            'created_by' => null,
        ]);

        // 4. Record Audit log
        AuditService::record('request_escalated', 'approvals', null, 'approval_requests', $reqId);

        $count++;
        
        echo "Escalated request #{$reqId}: \"{$title}\"\n";
        if (PHP_SAPI !== 'cli') {
            echo "Escalated request #{$reqId}: \"" . htmlspecialchars($title) . "\"<br>";
        }
    }

    echo "Escalation check completed. Actions taken: {$count}\n";
    if (PHP_SAPI !== 'cli') {
        echo "Escalation check completed. Actions taken: {$count}<br>";
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    if (PHP_SAPI !== 'cli') {
        echo "<strong>ERROR:</strong> " . htmlspecialchars($e->getMessage()) . "<br>";
    }
    exit(1);
}
