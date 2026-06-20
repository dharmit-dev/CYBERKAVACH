CREATE DATABASE IF NOT EXISTS cyberkavach
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE cyberkavach;

CREATE TABLE roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(100) NOT NULL,
    role_key VARCHAR(80) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    permission_key VARCHAR(120) NOT NULL UNIQUE,
    module VARCHAR(80) NOT NULL,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_permissions_module (module)
) ENGINE=InnoDB;

CREATE TABLE role_permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_role_permission (role_id, permission_id),
    CONSTRAINT fk_role_permissions_role
        FOREIGN KEY (role_id) REFERENCES roles(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission
        FOREIGN KEY (permission_id) REFERENCES permissions(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id INT UNSIGNED NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('pending_email', 'pending_approval', 'active', 'blocked', 'rejected') NOT NULL DEFAULT 'pending_email',
    email_verified_at DATETIME NULL,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role
        FOREIGN KEY (role_id) REFERENCES roles(id)
        ON DELETE RESTRICT,
    INDEX idx_users_role_status (role_id, status),
    INDEX idx_users_status (status)
) ENGINE=InnoDB;

CREATE TABLE user_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    college_id VARCHAR(80) NULL,
    department VARCHAR(120) NULL,
    year_of_study VARCHAR(40) NULL,
    section VARCHAR(40) NULL,
    roll_number VARCHAR(80) NULL,
    profile_photo VARCHAR(255) NULL,
    bio TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_profiles_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE otp_codes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(190) NOT NULL,
    otp_hash VARCHAR(255) NOT NULL,
    purpose ENUM('email_verification', 'login_verification') NOT NULL,
    expires_at DATETIME NOT NULL,
    is_used TINYINT(1) NOT NULL DEFAULT 0,
    used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_otp_codes_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    INDEX idx_otp_user_purpose (user_id, purpose, is_used),
    INDEX idx_otp_expires_at (expires_at)
) ENGINE=InnoDB;

CREATE TABLE password_resets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    is_used TINYINT(1) NOT NULL DEFAULT 0,
    used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_resets_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    UNIQUE KEY uq_password_resets_token_hash (token_hash),
    INDEX idx_password_resets_user (user_id, is_used),
    INDEX idx_password_resets_expires_at (expires_at)
) ENGINE=InnoDB;

CREATE TABLE login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    email VARCHAR(190) NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    failure_reason VARCHAR(80) NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NOT NULL,
    attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_login_attempts_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL,
    INDEX idx_login_attempts_email_ip_time (email, ip_address, attempted_at),
    INDEX idx_login_attempts_user_time (user_id, attempted_at)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    module VARCHAR(80) NOT NULL,
    entity_type VARCHAR(80) NULL,
    entity_id BIGINT UNSIGNED NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_logs_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL,
    INDEX idx_audit_logs_user_time (user_id, created_at),
    INDEX idx_audit_logs_module_time (module, created_at),
    INDEX idx_audit_logs_entity (entity_type, entity_id)
) ENGINE=InnoDB;

INSERT INTO roles (role_name, role_key, description) VALUES
('Faculty Coordinator', 'faculty_coordinator', 'Final authority for approvals and club governance'),
('Student Coordinator', 'student_coordinator', 'Student-level operational coordinator'),
('Tech Coordinator', 'tech_coordinator', 'Technical coordinator for QR, attendance, and platform support'),
('Content Coordinator', 'content_coordinator', 'Coordinator for content drafting and review'),
('Social Media Coordinator', 'social_media_coordinator', 'Coordinator for social media posts and promotion'),
('Club Member', 'club_member', 'Approved CyberKavach club member'),
('Guest / Student Participant', 'guest_participant', 'Student participant or guest user');

INSERT INTO permissions (permission_key, module, description) VALUES
('dashboard.view', 'dashboard', 'Access assigned dashboard'),
('users.approve', 'auth', 'Approve pending user accounts'),
('users.manage', 'auth', 'Manage user accounts'),
('auth.audit.view', 'auth', 'View authentication audit logs');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE p.permission_key = 'dashboard.view';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.permission_key IN ('users.approve', 'users.manage', 'auth.audit.view')
WHERE r.role_key = 'faculty_coordinator';
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
USE cyberkavach;

CREATE TABLE IF NOT EXISTS event_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    title VARCHAR(190) NOT NULL,
    slug VARCHAR(220) NOT NULL UNIQUE,
    description TEXT NOT NULL,
    event_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    venue VARCHAR(190) NOT NULL,
    registration_deadline DATETIME NOT NULL,
    capacity INT UNSIGNED NOT NULL,
    poster_path VARCHAR(255) NULL,
    team_allowed TINYINT(1) NOT NULL DEFAULT 0,
    min_team_size INT UNSIGNED NULL,
    max_team_size INT UNSIGNED NULL,
    event_rules TEXT NULL,
    status ENUM('draft', 'pending_approval', 'under_review', 'approved', 'published', 'rejected', 'cancelled', 'completed') NOT NULL DEFAULT 'draft',
    created_by BIGINT UNSIGNED NOT NULL,
    approved_by BIGINT UNSIGNED NULL,
    published_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_events_category
        FOREIGN KEY (category_id) REFERENCES event_categories(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_events_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_events_approved_by
        FOREIGN KEY (approved_by) REFERENCES users(id)
        ON DELETE SET NULL,
    INDEX idx_events_status_date (status, event_date),
    INDEX idx_events_category (category_id),
    INDEX idx_events_created_by (created_by)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS event_tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS event_tag_mapping (
    event_id BIGINT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (event_id, tag_id),
    CONSTRAINT fk_event_tag_mapping_event
        FOREIGN KEY (event_id) REFERENCES events(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_event_tag_mapping_tag
        FOREIGN KEY (tag_id) REFERENCES event_tags(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS saved_teams (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    team_name VARCHAR(150) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_saved_teams_owner
        FOREIGN KEY (owner_user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    INDEX idx_saved_teams_owner (owner_user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS saved_team_members (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    saved_team_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_saved_team_members_team
        FOREIGN KEY (saved_team_id) REFERENCES saved_teams(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_saved_team_members_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    UNIQUE KEY uq_saved_team_member (saved_team_id, user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS event_teams (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT UNSIGNED NOT NULL,
    saved_team_id BIGINT UNSIGNED NULL,
    team_identifier VARCHAR(40) NOT NULL UNIQUE,
    team_name VARCHAR(150) NOT NULL,
    leader_user_id BIGINT UNSIGNED NOT NULL,
    qr_payload VARCHAR(255) NOT NULL UNIQUE,
    qr_path VARCHAR(255) NULL,
    status ENUM('registered', 'cancelled') NOT NULL DEFAULT 'registered',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_event_teams_event
        FOREIGN KEY (event_id) REFERENCES events(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_event_teams_saved_team
        FOREIGN KEY (saved_team_id) REFERENCES saved_teams(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_event_teams_leader
        FOREIGN KEY (leader_user_id) REFERENCES users(id)
        ON DELETE RESTRICT,
    INDEX idx_event_teams_event (event_id),
    INDEX idx_event_teams_leader (leader_user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS event_registrations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    team_id BIGINT UNSIGNED NULL,
    registration_type ENUM('individual', 'team') NOT NULL,
    status ENUM('registered', 'cancelled') NOT NULL DEFAULT 'registered',
    registered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_event_registrations_event
        FOREIGN KEY (event_id) REFERENCES events(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_event_registrations_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_event_registrations_team
        FOREIGN KEY (team_id) REFERENCES event_teams(id)
        ON DELETE SET NULL,
    UNIQUE KEY uq_event_registration_user (event_id, user_id),
    INDEX idx_event_registrations_event_status (event_id, status),
    INDEX idx_event_registrations_team (team_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS event_team_members (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    team_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    is_leader TINYINT(1) NOT NULL DEFAULT 0,
    joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_event_team_members_team
        FOREIGN KEY (team_id) REFERENCES event_teams(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_event_team_members_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE RESTRICT,
    UNIQUE KEY uq_event_team_member (team_id, user_id),
    INDEX idx_event_team_members_user (user_id)
) ENGINE=InnoDB;

INSERT IGNORE INTO event_categories (name, description) VALUES
('Workshop', 'Hands-on technical or awareness workshop'),
('Competition', 'Contest or challenge event'),
('Seminar', 'Talk, guest lecture, or seminar'),
('Webinar', 'Online learning session'),
('Club Activity', 'Internal club activity');

INSERT IGNORE INTO permissions (permission_key, module, description) VALUES
('events.manage', 'events', 'Create and manage events'),
('events.approve', 'events', 'Submit and review event approvals'),
('events.register', 'events', 'Register for published events'),
('events.export', 'events', 'Export event registrations');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.permission_key IN ('events.manage', 'events.approve', 'events.export')
WHERE r.role_key IN ('faculty_coordinator', 'student_coordinator', 'tech_coordinator');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.permission_key = 'events.register'
WHERE r.role_key IN ('faculty_coordinator', 'student_coordinator', 'tech_coordinator', 'content_coordinator', 'social_media_coordinator', 'club_member', 'guest_participant');
USE cyberkavach;

CREATE TABLE IF NOT EXISTS event_attendance (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT UNSIGNED NOT NULL,
    registration_id BIGINT UNSIGNED NULL,
    team_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    attendance_type ENUM('check_in', 'check_out') NOT NULL,
    scanned_by_user_id BIGINT UNSIGNED NULL,
    scanned_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_event_attendance_event
        FOREIGN KEY (event_id) REFERENCES events(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_event_attendance_registration
        FOREIGN KEY (registration_id) REFERENCES event_registrations(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_event_attendance_team
        FOREIGN KEY (team_id) REFERENCES event_teams(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_event_attendance_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_event_attendance_scanner
        FOREIGN KEY (scanned_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL,
    INDEX idx_event_attendance_event_user (event_id, user_id),
    INDEX idx_event_attendance_event_team (event_id, team_id),
    INDEX idx_event_attendance_type (attendance_type),
    INDEX idx_event_attendance_scanned_at (scanned_at)
) ENGINE=InnoDB;

INSERT IGNORE INTO permissions (permission_key, module, description) VALUES
('events.attendance', 'events', 'Scan and manage event attendance');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.permission_key = 'events.attendance'
WHERE r.role_key IN ('faculty_coordinator', 'student_coordinator', 'tech_coordinator');
USE cyberkavach;

-- 1. Alter otp_codes table to support password_reset purpose
ALTER TABLE otp_codes MODIFY COLUMN purpose ENUM('email_verification', 'login_verification', 'password_reset') NOT NULL;

-- 2. Create budget_requests table
CREATE TABLE IF NOT EXISTS budget_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT UNSIGNED NULL,
    amount DECIMAL(12,2) NOT NULL,
    purpose TEXT NOT NULL,
    requested_by BIGINT UNSIGNED NOT NULL,
    status ENUM('pending', 'under_review', 'approved', 'rejected', 'returned', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_budget_requests_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT fk_budget_requests_user FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 3. Create venue_resource_requests table
CREATE TABLE IF NOT EXISTS venue_resource_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT UNSIGNED NULL,
    venue VARCHAR(190) NOT NULL,
    resources_needed TEXT NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    requested_by BIGINT UNSIGNED NOT NULL,
    status ENUM('pending', 'under_review', 'approved', 'rejected', 'returned', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_venue_resource_requests_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT fk_venue_resource_requests_user FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 4. Create social_media_posts table
CREATE TABLE IF NOT EXISTS social_media_posts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    platforms VARCHAR(190) NOT NULL,
    caption TEXT NOT NULL,
    image_path VARCHAR(255) NULL,
    schedule_time DATETIME NULL,
    requested_by BIGINT UNSIGNED NOT NULL,
    status ENUM('pending', 'under_review', 'approved', 'rejected', 'returned', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_social_media_posts_user FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 5. Create content_posts table
CREATE TABLE IF NOT EXISTS content_posts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(190) NOT NULL,
    content_body TEXT NOT NULL,
    category VARCHAR(100) NOT NULL,
    requested_by BIGINT UNSIGNED NOT NULL,
    status ENUM('pending', 'under_review', 'approved', 'rejected', 'returned', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_content_posts_user FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 6. Create external_collaborations table
CREATE TABLE IF NOT EXISTS external_collaborations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    partner_name VARCHAR(190) NOT NULL,
    description TEXT NOT NULL,
    contact_person VARCHAR(150) NOT NULL,
    contact_email VARCHAR(190) NOT NULL,
    requested_by BIGINT UNSIGNED NOT NULL,
    status ENUM('pending', 'under_review', 'approved', 'rejected', 'returned', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_external_collaborations_user FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 7. Register external_collaboration_approval workflow
INSERT IGNORE INTO approval_workflows (workflow_key, name, entity_type, description, escalation_hours) VALUES
('external_collaboration_approval', 'External Collaboration Approval', 'external_collaboration', 'Review of external collaboration requests', 48);

INSERT IGNORE INTO approval_workflow_steps (workflow_id, step_order, step_name, approver_role_id, is_final_step)
SELECT w.id, 1, 'Student Coordinator Review', r.id, 0
FROM approval_workflows w
INNER JOIN roles r ON r.role_key = 'student_coordinator'
WHERE w.workflow_key = 'external_collaboration_approval';

INSERT IGNORE INTO approval_workflow_steps (workflow_id, step_order, step_name, approver_role_id, is_final_step)
SELECT w.id, 2, 'Faculty Coordinator Final Approval', r.id, 1
FROM approval_workflows w
INNER JOIN roles r ON r.role_key = 'faculty_coordinator'
WHERE w.workflow_key = 'external_collaboration_approval';
USE cyberkavach;

-- 1. Alter otp_codes table to support failed attempts tracking and client network context
ALTER TABLE otp_codes 
    ADD COLUMN failed_attempts INT UNSIGNED DEFAULT 0 AFTER is_used,
    ADD COLUMN ip_address VARCHAR(45) NULL AFTER failed_attempts,
    ADD COLUMN user_agent VARCHAR(255) NULL AFTER ip_address;

-- 2. Alter password_resets table to support client network context
ALTER TABLE password_resets 
    ADD COLUMN ip_address VARCHAR(45) NULL AFTER is_used,
    ADD COLUMN user_agent VARCHAR(255) NULL AFTER ip_address;
USE cyberkavach;

-- 1. Alter events table to support configurable late arrival and early exit thresholds
ALTER TABLE events 
    ADD COLUMN late_arrival_threshold_minutes INT UNSIGNED DEFAULT 15 AFTER capacity,
    ADD COLUMN early_exit_threshold_minutes INT UNSIGNED DEFAULT 15 AFTER late_arrival_threshold_minutes;

-- 2. Alter event_attendance table to store audit flags
ALTER TABLE event_attendance
    ADD COLUMN is_late TINYINT(1) UNSIGNED DEFAULT 0 AFTER attendance_type,
    ADD COLUMN is_early_exit TINYINT(1) UNSIGNED DEFAULT 0 AFTER is_late;
USE cyberkavach;

-- 1. Create certificate_templates table
CREATE TABLE IF NOT EXISTS certificate_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    text_settings TEXT NOT NULL, -- JSON coordinates: name_x, name_y, event_x, event_y, etc.
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Create certificates table
CREATE TABLE IF NOT EXISTS certificates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    certificate_code VARCHAR(50) NOT NULL UNIQUE,
    template_id INT UNSIGNED NOT NULL,
    event_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,
    recipient_name VARCHAR(150) NOT NULL,
    recipient_email VARCHAR(150) NOT NULL,
    cryptographic_signature VARCHAR(64) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_certificates_template
        FOREIGN KEY (template_id) REFERENCES certificate_templates(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_certificates_event
        FOREIGN KEY (event_id) REFERENCES events(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_certificates_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL,
    INDEX idx_certificates_code (certificate_code)
) ENGINE=InnoDB;

-- 3. Register permissions for certificate management
INSERT IGNORE INTO permissions (permission_key, module, description) VALUES
('certificates.manage', 'certificates', 'Upload templates and generate bulk certificates');

-- 4. Assign permissions to coordinators
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.permission_key = 'certificates.manage'
WHERE r.role_key IN ('faculty_coordinator', 'student_coordinator', 'tech_coordinator');
USE cyberkavach;

-- 1. Create member_points table
CREATE TABLE IF NOT EXISTS member_points (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    points INT NOT NULL,
    reason VARCHAR(255) NOT NULL,
    category VARCHAR(50) NOT NULL, -- 'attendance', 'manual_award', 'manual_deduction', 'redemption'
    awarded_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_member_points_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_member_points_awarded_by
        FOREIGN KEY (awarded_by) REFERENCES users(id)
        ON DELETE SET NULL,
    INDEX idx_member_points_user (user_id)
) ENGINE=InnoDB;

-- 2. Create badges table
CREATE TABLE IF NOT EXISTS badges (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NOT NULL,
    threshold_points INT NOT NULL,
    icon VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

-- 3. Create user_badges table
CREATE TABLE IF NOT EXISTS user_badges (
    user_id BIGINT UNSIGNED NOT NULL,
    badge_id INT UNSIGNED NOT NULL,
    unlocked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, badge_id),
    CONSTRAINT fk_user_badges_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_user_badges_badge
        FOREIGN KEY (badge_id) REFERENCES badges(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. Create reward_items table
CREATE TABLE IF NOT EXISTS reward_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NOT NULL,
    points_cost INT NOT NULL,
    stock INT NOT NULL DEFAULT 999,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 5. Seed default badges
INSERT IGNORE INTO badges (name, description, threshold_points, icon) VALUES
('Novice Participant', 'Awarded upon reaching 10 reward points for participating in club events.', 10, 'shield-halved'),
('Dedicated Challenger', 'Awarded upon reaching 50 reward points for continuous active participation.', 50, 'award'),
('Cyber Sentinel', 'Awarded upon reaching 100 reward points. A recognized guardian of club activities.', 100, 'shield-virus'),
('Elite CyberKavach', 'Awarded upon reaching 200 reward points. The highest echelon of club recognition.', 200, 'crown');

-- 6. Seed default reward items
INSERT IGNORE INTO reward_items (name, description, points_cost, stock) VALUES
('CyberKavach Sticker Pack', 'A pack of premium developer stickers to style your gear.', 30, 100),
('VIP Event Registration Voucher', 'Guarantees registration for any high-demand workshop or hackathon.', 50, 50),
('Custom Premium Certificate Theme', 'Unlock a special custom template style for your next certificate.', 60, 999),
('CyberKavach Exclusive Hoodie', 'Ultra-premium cotton hoodie with custom branding (limited stock).', 200, 10);

-- 7. Register permission for managing rewards
INSERT IGNORE INTO permissions (permission_key, module, description) VALUES
('rewards.manage', 'rewards', 'Manually award or deduct reward points for club members');

-- 8. Assign permission to coordinator roles
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.permission_key = 'rewards.manage'
WHERE r.role_key IN ('faculty_coordinator', 'student_coordinator', 'tech_coordinator');
