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
