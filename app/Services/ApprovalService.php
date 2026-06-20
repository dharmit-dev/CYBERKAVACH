<?php

declare(strict_types=1);

final class ApprovalService
{
    public static function submit(
        string $workflowKey,
        string $entityType,
        int $entityId,
        int $requestedBy,
        string $title,
        ?string $comments = null
    ): int {
        $existing = ApprovalRequest::findOpenForEntity($workflowKey, $entityType, $entityId);

        if ($existing) {
            return (int) $existing['id'];
        }

        $workflow = self::workflowByKey($workflowKey);

        if (!$workflow) {
            throw new RuntimeException('Approval workflow is not configured.');
        }

        $escalationDueAt = null;
        if (!empty($workflow['escalation_hours'])) {
            $escalationDueAt = date('Y-m-d H:i:s', time() + ((int) $workflow['escalation_hours'] * 3600));
        }

        $requestId = ApprovalRequest::create([
            'workflow_id' => $workflow['id'],
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'title' => $title,
            'requested_by' => $requestedBy,
            'current_step_order' => 1,
            'status' => 'pending',
            'escalation_due_at' => $escalationDueAt,
        ]);

        $submitter = User::findById($requestedBy);
        ApprovalAction::create([
            'approval_request_id' => $requestId,
            'step_order' => 1,
            'actor_user_id' => $requestedBy,
            'actor_role_id' => $submitter['role_id'],
            'action' => 'submitted',
            'comments' => $comments,
        ]);

        NotificationService::notifyRole('student_coordinator', [
            'title' => 'Approval submitted',
            'message' => $title . ' is ready for Student Coordinator review.',
            'type' => 'approval_submitted',
            'entity_type' => 'approval_request',
            'entity_id' => $requestId,
            'created_by' => $requestedBy,
        ]);

        AuditService::record('approval_submitted', 'approvals', $requestedBy, 'approval_requests', $requestId);

        $submitter = User::findById($requestedBy);
        if ($submitter) {
            $mailSubject = app_config('name') . ' - Request Submitted';
            $mailMessage = "Hello {$submitter['full_name']},\n\nYour request: \"{$title}\" has been successfully submitted and is pending review.\n\nBest regards,\n" . app_config('name');
            MailService::sendEmail($submitter['email'], $mailSubject, $mailMessage);
        }

        return $requestId;
    }

    public static function approve(int $requestId, array $actor, string $comments): array
    {
        $request = self::authorizedRequestForReview($requestId, $actor);
        $currentStep = self::stepForRequest($request);

        ApprovalAction::create([
            'approval_request_id' => $requestId,
            'step_order' => $request['current_step_order'],
            'actor_user_id' => $actor['id'],
            'actor_role_id' => $actor['role_id'],
            'action' => 'approved',
            'comments' => $comments,
        ]);

        $nextStep = self::nextStep((int) $request['workflow_id'], (int) $request['current_step_order']);

        if ($nextStep) {
            ApprovalRequest::updateStatus($requestId, 'under_review', (int) $nextStep['step_order']);
            if ($request['workflow_key'] === 'event_approval' && $request['entity_type'] === 'event') {
                Event::markUnderReview((int) $request['entity_id']);
            }
            self::notifyRoleId((int) $nextStep['approver_role_id'], [
                'title' => 'Approval moved forward',
                'message' => $request['title'] . ' is ready for your review.',
                'type' => 'approval_under_review',
                'entity_type' => 'approval_request',
                'entity_id' => $requestId,
                'created_by' => $actor['id'],
            ]);
        } else {
            ApprovalRequest::updateStatus($requestId, 'approved', (int) $request['current_step_order'], (int) $actor['id']);
            self::applyApprovedEntityChange($request, (int) $actor['id']);
            NotificationService::notifyUsers([(int) $request['requested_by']], [
                'title' => 'Approval approved',
                'message' => $request['title'] . ' has been approved.',
                'type' => 'approval_approved',
                'entity_type' => 'approval_request',
                'entity_id' => $requestId,
                'created_by' => $actor['id'],
            ]);
        }

        AuditService::record('approval_approved', 'approvals', (int) $actor['id'], 'approval_requests', $requestId);

        $requesterEmail = $request['submitter_email'] ?? '';
        $requesterName = $request['submitter_name'] ?? 'Member';
        if ($requesterEmail === '') {
            $req = User::findById((int) $request['requested_by']);
            if ($req) {
                $requesterEmail = $req['email'];
                $requesterName = $req['full_name'];
            }
        }
        if ($requesterEmail !== '') {
            $mailSubject = app_config('name') . ' - Request Status Update';
            if ($nextStep) {
                $mailMessage = "Hello {$requesterName},\n\nYour request: \"{$request['title']}\" has been updated to \"Under Review\".\n\nRemarks by {$actor['full_name']}:\n{$comments}\n\nBest regards,\n" . app_config('name');
            } else {
                $mailMessage = "Hello {$requesterName},\n\nYour request: \"{$request['title']}\" has been fully APPROVED!\n\nFinal remarks by {$actor['full_name']}:\n{$comments}\n\nBest regards,\n" . app_config('name');
            }
            MailService::sendEmail($requesterEmail, $mailSubject, $mailMessage);
        }

        return ['ok' => true, 'step' => $currentStep];
    }

    public static function reject(int $requestId, array $actor, string $comments): array
    {
        if ($comments === '') {
            return ['ok' => false, 'message' => 'Remarks are required when rejecting a request.'];
        }

        $request = self::authorizedRequestForReview($requestId, $actor);

        ApprovalAction::create([
            'approval_request_id' => $requestId,
            'step_order' => $request['current_step_order'],
            'actor_user_id' => $actor['id'],
            'actor_role_id' => $actor['role_id'],
            'action' => 'rejected',
            'comments' => $comments,
        ]);

        ApprovalRequest::updateStatus($requestId, 'rejected', (int) $request['current_step_order'], (int) $actor['id']);
        self::applyRejectedEntityChange($request);

        NotificationService::notifyUsers([(int) $request['requested_by']], [
            'title' => 'Approval rejected',
            'message' => $request['title'] . ' has been rejected.',
            'type' => 'approval_rejected',
            'entity_type' => 'approval_request',
            'entity_id' => $requestId,
            'created_by' => $actor['id'],
        ]);

        AuditService::record('approval_rejected', 'approvals', (int) $actor['id'], 'approval_requests', $requestId);

        $requesterEmail = $request['submitter_email'] ?? '';
        $requesterName = $request['submitter_name'] ?? 'Member';
        if ($requesterEmail === '') {
            $req = User::findById((int) $request['requested_by']);
            if ($req) {
                $requesterEmail = $req['email'];
                $requesterName = $req['full_name'];
            }
        }
        if ($requesterEmail !== '') {
            $mailSubject = app_config('name') . ' - Request Status Update';
            $mailMessage = "Hello {$requesterName},\n\nYour request: \"{$request['title']}\" has been REJECTED.\n\nRemarks by {$actor['full_name']}:\n{$comments}\n\nBest regards,\n" . app_config('name');
            MailService::sendEmail($requesterEmail, $mailSubject, $mailMessage);
        }

        return ['ok' => true];
    }

    public static function returnRequest(int $requestId, array $actor, string $comments): array
    {
        if ($comments === '') {
            return ['ok' => false, 'message' => 'Remarks are required when returning a request.'];
        }

        $request = self::authorizedRequestForReview($requestId, $actor);

        ApprovalAction::create([
            'approval_request_id' => $requestId,
            'step_order' => $request['current_step_order'],
            'actor_user_id' => $actor['id'],
            'actor_role_id' => $actor['role_id'],
            'action' => 'returned',
            'comments' => $comments,
        ]);

        ApprovalRequest::updateStatus($requestId, 'returned', (int) $request['current_step_order']);

        NotificationService::notifyUsers([(int) $request['requested_by']], [
            'title' => 'Approval returned',
            'message' => $request['title'] . ' needs changes. Please check the remarks.',
            'type' => 'approval_returned',
            'entity_type' => 'approval_request',
            'entity_id' => $requestId,
            'created_by' => $actor['id'],
        ]);

        AuditService::record('approval_returned', 'approvals', (int) $actor['id'], 'approval_requests', $requestId);

        $requesterEmail = $request['submitter_email'] ?? '';
        $requesterName = $request['submitter_name'] ?? 'Member';
        if ($requesterEmail === '') {
            $req = User::findById((int) $request['requested_by']);
            if ($req) {
                $requesterEmail = $req['email'];
                $requesterName = $req['full_name'];
            }
        }
        if ($requesterEmail !== '') {
            $mailSubject = app_config('name') . ' - Request Status Update';
            $mailMessage = "Hello {$requesterName},\n\nYour request: \"{$request['title']}\" was returned for changes.\n\nRemarks by {$actor['full_name']}:\n{$comments}\n\nPlease review and update your submission.\n\nBest regards,\n" . app_config('name');
            MailService::sendEmail($requesterEmail, $mailSubject, $mailMessage);
        }

        return ['ok' => true];
    }

    public static function comment(int $requestId, array $actor, string $comments): array
    {
        if ($comments === '') {
            return ['ok' => false, 'message' => 'Comment cannot be empty.'];
        }

        $request = ApprovalRequest::findById($requestId);

        if (!$request || !self::canView($request, $actor)) {
            self::denyApprovalAccess();
        }

        ApprovalAction::create([
            'approval_request_id' => $requestId,
            'step_order' => $request['current_step_order'],
            'actor_user_id' => $actor['id'],
            'actor_role_id' => $actor['role_id'],
            'action' => 'commented',
            'comments' => $comments,
        ]);

        $recipients = [(int) $request['requested_by']];
        foreach (['student_coordinator', 'faculty_coordinator'] as $roleKey) {
            $recipients = array_merge($recipients, self::activeUserIdsByRole($roleKey));
        }

        NotificationService::notifyUsers($recipients, [
            'title' => 'Approval remark added',
            'message' => $actor['full_name'] . ' added remarks to ' . $request['title'] . '.',
            'type' => 'approval_comment',
            'entity_type' => 'approval_request',
            'entity_id' => $requestId,
            'created_by' => $actor['id'],
        ]);

        AuditService::record('approval_commented', 'approvals', (int) $actor['id'], 'approval_requests', $requestId);

        return ['ok' => true];
    }

    public static function canView(array $request, array $user): bool
    {
        if (empty($user['id']) || empty($user['role_key']) || empty($request['requested_by'])) {
            return false;
        }

        return (int) $request['requested_by'] === (int) $user['id']
            || in_array((string) $user['role_key'], ['student_coordinator', 'faculty_coordinator'], true);
    }

    public static function canAct(array $request, array $user): bool
    {
        if (!in_array($request['status'], ['pending', 'under_review'], true)) {
            return false;
        }

        $step = self::stepForRequest($request);

        return $step && (int) $step['approver_role_id'] === (int) $user['role_id'];
    }

    public static function statusBadge(string $status): string
    {
        $labels = [
            'pending' => 'Pending',
            'under_review' => 'Under Review',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'returned' => 'Returned',
            'cancelled' => 'Cancelled',
            'draft' => 'Draft',
            'pending_approval' => 'Pending Approval',
            'published' => 'Published',
            'completed' => 'Completed',
        ];
        $class = 'badge badge-' . str_replace('_', '-', $status);

        return '<span class="' . h($class) . '">' . h($labels[$status] ?? ucfirst($status)) . '</span>';
    }

    public static function workflowByKey(string $workflowKey): ?array
    {
        $stmt = db()->prepare(
            'SELECT * FROM approval_workflows WHERE workflow_key = :workflow_key AND is_active = 1 LIMIT 1'
        );
        $stmt->execute(['workflow_key' => $workflowKey]);
        $workflow = $stmt->fetch();

        return $workflow ?: null;
    }

    public static function stepForRequest(array $request): ?array
    {
        $stmt = db()->prepare(
            'SELECT aws.*, roles.role_name, roles.role_key
             FROM approval_workflow_steps aws
             INNER JOIN roles ON roles.id = aws.approver_role_id
             WHERE aws.workflow_id = :workflow_id AND aws.step_order = :step_order
             LIMIT 1'
        );
        $stmt->execute([
            'workflow_id' => $request['workflow_id'],
            'step_order' => $request['current_step_order'],
        ]);
        $step = $stmt->fetch();

        return $step ?: null;
    }

    public static function escalationCandidates(): array
    {
        $stmt = db()->query(
            "SELECT ar.*, aw.workflow_key, aw.name AS workflow_name, aw.escalation_hours
             FROM approval_requests ar
             INNER JOIN approval_workflows aw ON aw.id = ar.workflow_id
             WHERE ar.status IN ('pending', 'under_review')
                AND ar.escalation_due_at IS NOT NULL
                AND ar.escalation_due_at < NOW()
             ORDER BY ar.escalation_due_at ASC"
        );

        return $stmt->fetchAll();
    }

    private static function authorizedRequestForReview(int $requestId, array $actor): array
    {
        $request = ApprovalRequest::findById($requestId);

        if (!$request || !self::canView($request, $actor) || !self::canAct($request, $actor)) {
            self::denyApprovalAccess();
        }

        return $request;
    }

    private static function denyApprovalAccess(): never
    {
        flash('error', 'Unauthorized access');
        redirect('dashboard.php');
    }

    private static function nextStep(int $workflowId, int $currentStepOrder): ?array
    {
        $stmt = db()->prepare(
            'SELECT * FROM approval_workflow_steps
             WHERE workflow_id = :workflow_id AND step_order > :step_order
             ORDER BY step_order ASC
             LIMIT 1'
        );
        $stmt->execute([
            'workflow_id' => $workflowId,
            'step_order' => $currentStepOrder,
        ]);
        $step = $stmt->fetch();

        return $step ?: null;
    }

    private static function applyApprovedEntityChange(array $request, ?int $approvedBy = null): void
    {
        if ($request['workflow_key'] === 'user_account_approval' && $request['entity_type'] === 'user') {
            User::activate((int) $request['entity_id']);
        }

        if ($request['workflow_key'] === 'event_approval' && $request['entity_type'] === 'event') {
            Event::publish((int) $request['entity_id'], $approvedBy);
            $event = Event::findById((int) $request['entity_id']);
            if ($event) {
                NotificationService::notifyUsers([(int) $event['created_by']], [
                    'title' => 'Event approved',
                    'message' => $event['title'] . ' has been approved and published.',
                    'type' => 'event_approved',
                    'entity_type' => 'event',
                    'entity_id' => (int) $event['id'],
                    'created_by' => null,
                ]);
            }
        }

        if ($request['workflow_key'] === 'budget_request_approval' && $request['entity_type'] === 'budget_request') {
            BudgetRequest::updateStatus((int) $request['entity_id'], 'approved');
        }

        if ($request['workflow_key'] === 'venue_resource_approval' && $request['entity_type'] === 'venue_resource_request') {
            VenueResourceRequest::updateStatus((int) $request['entity_id'], 'approved');
        }

        if ($request['workflow_key'] === 'social_media_approval' && $request['entity_type'] === 'social_media_post') {
            SocialMediaPost::updateStatus((int) $request['entity_id'], 'approved');
        }

        if ($request['workflow_key'] === 'content_publishing_approval' && $request['entity_type'] === 'content_post') {
            ContentPost::updateStatus((int) $request['entity_id'], 'approved');
        }

        if ($request['workflow_key'] === 'external_collaboration_approval' && $request['entity_type'] === 'external_collaboration') {
            ExternalCollaboration::updateStatus((int) $request['entity_id'], 'approved');
        }
    }

    private static function applyRejectedEntityChange(array $request): void
    {
        if ($request['workflow_key'] === 'user_account_approval' && $request['entity_type'] === 'user') {
            User::reject((int) $request['entity_id']);
        }

        if ($request['workflow_key'] === 'event_approval' && $request['entity_type'] === 'event') {
            Event::markRejected((int) $request['entity_id']);
            $event = Event::findById((int) $request['entity_id']);
            if ($event) {
                NotificationService::notifyUsers([(int) $event['created_by']], [
                    'title' => 'Event rejected',
                    'message' => $event['title'] . ' was rejected. Check approval remarks.',
                    'type' => 'event_rejected',
                    'entity_type' => 'event',
                    'entity_id' => (int) $event['id'],
                    'created_by' => null,
                ]);
            }
        }

        if ($request['workflow_key'] === 'budget_request_approval' && $request['entity_type'] === 'budget_request') {
            BudgetRequest::updateStatus((int) $request['entity_id'], 'rejected');
        }

        if ($request['workflow_key'] === 'venue_resource_approval' && $request['entity_type'] === 'venue_resource_request') {
            VenueResourceRequest::updateStatus((int) $request['entity_id'], 'rejected');
        }

        if ($request['workflow_key'] === 'social_media_approval' && $request['entity_type'] === 'social_media_post') {
            SocialMediaPost::updateStatus((int) $request['entity_id'], 'rejected');
        }

        if ($request['workflow_key'] === 'content_publishing_approval' && $request['entity_type'] === 'content_post') {
            ContentPost::updateStatus((int) $request['entity_id'], 'rejected');
        }

        if ($request['workflow_key'] === 'external_collaboration_approval' && $request['entity_type'] === 'external_collaboration') {
            ExternalCollaboration::updateStatus((int) $request['entity_id'], 'rejected');
        }
    }

    private static function notifyRoleId(int $roleId, array $payload): void
    {
        $stmt = db()->prepare('SELECT id FROM users WHERE role_id = :role_id AND status = :status');
        $stmt->execute([
            'role_id' => $roleId,
            'status' => 'active',
        ]);

        Notification::create($payload, array_column($stmt->fetchAll(), 'id'));
    }

    private static function activeUserIdsByRole(string $roleKey): array
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

        return array_column($stmt->fetchAll(), 'id');
    }
}
