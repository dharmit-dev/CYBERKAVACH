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
