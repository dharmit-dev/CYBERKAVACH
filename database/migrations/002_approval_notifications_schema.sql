USE cyberkavach;

CREATE TABLE IF NOT EXISTS approval_workflows (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workflow_key VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    entity_type VARCHAR(80) NOT NULL,
    description VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    escalation_hours INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_approval_workflows_entity (entity_type, is_active)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS approval_workflow_steps (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workflow_id INT UNSIGNED NOT NULL,
    step_order INT UNSIGNED NOT NULL,
    step_name VARCHAR(120) NOT NULL,
    approver_role_id INT UNSIGNED NOT NULL,
    is_final_step TINYINT(1) NOT NULL DEFAULT 0,
    can_return TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_workflow_step_order (workflow_id, step_order),
    CONSTRAINT fk_approval_steps_workflow
        FOREIGN KEY (workflow_id) REFERENCES approval_workflows(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_approval_steps_role
        FOREIGN KEY (approver_role_id) REFERENCES roles(id)
        ON DELETE RESTRICT,
    INDEX idx_approval_steps_role (approver_role_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS approval_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workflow_id INT UNSIGNED NOT NULL,
    entity_type VARCHAR(80) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(190) NOT NULL,
    requested_by BIGINT UNSIGNED NOT NULL,
    current_step_order INT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('pending', 'under_review', 'approved', 'rejected', 'returned', 'cancelled') NOT NULL DEFAULT 'pending',
    escalation_due_at DATETIME NULL,
    completed_at DATETIME NULL,
    final_decision_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_approval_requests_workflow
        FOREIGN KEY (workflow_id) REFERENCES approval_workflows(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_approval_requests_submitter
        FOREIGN KEY (requested_by) REFERENCES users(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_approval_requests_final_user
        FOREIGN KEY (final_decision_by) REFERENCES users(id)
        ON DELETE SET NULL,
    INDEX idx_approval_requests_entity (entity_type, entity_id),
    INDEX idx_approval_requests_submitter (requested_by, status),
    INDEX idx_approval_requests_status_step (status, current_step_order),
    INDEX idx_approval_requests_escalation (escalation_due_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS approval_actions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    approval_request_id BIGINT UNSIGNED NOT NULL,
    step_order INT UNSIGNED NOT NULL,
    actor_user_id BIGINT UNSIGNED NOT NULL,
    actor_role_id INT UNSIGNED NOT NULL,
    action ENUM('submitted', 'approved', 'rejected', 'returned', 'commented', 'escalated') NOT NULL,
    comments TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_approval_actions_request
        FOREIGN KEY (approval_request_id) REFERENCES approval_requests(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_approval_actions_actor
        FOREIGN KEY (actor_user_id) REFERENCES users(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_approval_actions_role
        FOREIGN KEY (actor_role_id) REFERENCES roles(id)
        ON DELETE RESTRICT,
    INDEX idx_approval_actions_request_time (approval_request_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(160) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(80) NOT NULL,
    entity_type VARCHAR(80) NULL,
    entity_id BIGINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE SET NULL,
    INDEX idx_notifications_entity (entity_type, entity_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notification_recipients (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    notification_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    read_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notification_recipients_notification
        FOREIGN KEY (notification_id) REFERENCES notifications(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_notification_recipients_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    UNIQUE KEY uq_notification_user (notification_id, user_id),
    INDEX idx_notification_recipients_user_read (user_id, is_read, created_at)
) ENGINE=InnoDB;

INSERT IGNORE INTO permissions (permission_key, module, description) VALUES
('approvals.view', 'approvals', 'View allowed approval requests'),
('approvals.review', 'approvals', 'Review approval requests assigned to coordinator role'),
('approvals.comment', 'approvals', 'Add approval remarks'),
('notifications.view', 'notifications', 'View in-app notifications');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.permission_key IN ('approvals.view', 'approvals.review', 'approvals.comment', 'notifications.view')
WHERE r.role_key IN ('faculty_coordinator', 'student_coordinator');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.permission_key IN ('notifications.view')
WHERE r.role_key IN ('tech_coordinator', 'content_coordinator', 'social_media_coordinator', 'club_member', 'guest_participant');

INSERT IGNORE INTO approval_workflows (workflow_key, name, entity_type, description, escalation_hours) VALUES
('user_account_approval', 'User Account Approval', 'user', 'Student Coordinator review followed by Faculty Coordinator final approval', 48),
('event_approval', 'Event Approval', 'event', 'Reusable workflow foundation for event approval', 72),
('budget_request_approval', 'Budget Request Approval', 'budget_request', 'Reusable workflow foundation for budget requests', 72),
('venue_resource_approval', 'Venue / Resource Request Approval', 'venue_resource_request', 'Reusable workflow foundation for venue and resource requests', 48),
('social_media_approval', 'Social Media Approval', 'social_media_post', 'Reusable workflow foundation for social media posts', 24),
('content_publishing_approval', 'Content Publishing Approval', 'content_post', 'Reusable workflow foundation for content publishing', 24),
('certificate_generation_approval', 'Certificate Generation Approval', 'certificate_batch', 'Reusable workflow foundation for certificate generation', 48);

INSERT IGNORE INTO approval_workflow_steps (workflow_id, step_order, step_name, approver_role_id, is_final_step)
SELECT w.id, 1, 'Student Coordinator Review', r.id, 0
FROM approval_workflows w
INNER JOIN roles r ON r.role_key = 'student_coordinator'
WHERE w.workflow_key IN (
    'user_account_approval',
    'event_approval',
    'budget_request_approval',
    'venue_resource_approval',
    'social_media_approval',
    'content_publishing_approval',
    'certificate_generation_approval'
);

INSERT IGNORE INTO approval_workflow_steps (workflow_id, step_order, step_name, approver_role_id, is_final_step)
SELECT w.id, 2, 'Faculty Coordinator Final Approval', r.id, 1
FROM approval_workflows w
INNER JOIN roles r ON r.role_key = 'faculty_coordinator'
WHERE w.workflow_key IN (
    'user_account_approval',
    'event_approval',
    'budget_request_approval',
    'venue_resource_approval',
    'social_media_approval',
    'content_publishing_approval',
    'certificate_generation_approval'
);
